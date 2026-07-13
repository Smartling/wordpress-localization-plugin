#!/usr/bin/env bash
# Pre-defined env vars:
#
#
# WP_INSTALL_DIR            -       Path to installed WP
# PLUGIN_DIR                -       Path to $WP_INSTALL_DIR/wp-content/plugins/smartling-connector
# WPCLI                     -       Path to wp-cli.phar
# WP_DB_USER
# WP_DB_PASS
# WP_DB_NAME
# WP_DB_TABLE_PREFIX        -       default wp_
# WP_INSTALLATION_DOMAIN    -       default test.com
# SITES
# LOCAL_GIT_DIR             -       /plugin-dir
# MYSQL_HOST                -       localhost
# CRE_PROJECT_ID
# CRE_USER_IDENTIFIER
# CRE_TOKEN_SECRET

# install composer
COMPOSER_INSTALL_DIR="$LOCAL_GIT_DIR/inc/third-party/bin"
if [ ! -d "$COMPOSER_INSTALL_DIR" ]; then
    mkdir -p "$COMPOSER_INSTALL_DIR"
fi
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php --install-dir="$COMPOSER_INSTALL_DIR" --filename=composer
php -r "unlink('composer-setup.php');"
COMPOSER_BIN="$COMPOSER_INSTALL_DIR/composer"

chown -R mysql:mysql /var/lib/mysql && service mysql start

cd "$LOCAL_GIT_DIR"
$COMPOSER_BIN update --no-scripts

# Run namespacer entirely in /tmp to avoid Docker overlay fs stat() failures.
# inc/third-party/ is gitignored and freshly created by 'composer update' above.
# Docker's overlay filesystem causes stat()/lstat() to return ENOENT for newly-
# created files in volume-mounted directories, breaking both shell 'cp -r' and
# PHP's Filesystem::mirror(). Running 'composer install' and namespacer entirely
# in /tmp means packages are installed and processed on the container's own
# filesystem where stat() works correctly; the final inc/lib/ is then copied back.
NS_WORK="$(mktemp -d)"
mkdir -p "$NS_WORK/inc"
# Use the original composer.json so its hash matches composer.lock — a mismatch
# would make Composer fall back to full re-resolution (which fails because it
# can't find vsolovei-smartling/namespacer without the GitHub repository entry).
cp "$LOCAL_GIT_DIR/composer.json" "$NS_WORK/"
cp "$LOCAL_GIT_DIR/composer.lock" "$NS_WORK/"
cp "$LOCAL_GIT_DIR/namespacer.config.php" "$NS_WORK/"
cp "$LOCAL_GIT_DIR/fix-double-namespace.php" "$NS_WORK/"

# Install prod packages directly into /tmp (not copied from Docker volume) so all
# package files are in /tmp and readable by namespacer without stat() issues.
# --no-dev: skips vsolovei-smartling/namespacer (dev dep) without needing GitHub.
# --no-scripts: prevents post-install-cmd (empty anyway) from running.
# The namespacer binary itself comes from LOCAL_GIT_DIR (installed by the first
# 'composer update --no-scripts' above, executed via PHP's open() not stat()).
cd "$NS_WORK"
$COMPOSER_BIN install --no-scripts --no-dev --no-interaction

# Replace composer.json with a stripped version before running namespacer so that
# namespacer's inner 'composer update --no-dev' doesn't inherit:
#   - 'scripts': would try to run namespacer recursively → exit 127
#   - 'repositories': would include the GitHub URL; inner composer only needs the
#     path repos that namespacer itself provides, so GitHub can be removed.
#   - 'require-dev'/'autoload-dev': inner composer would try to find
#     vsolovei-smartling/namespacer which isn't resolvable without GitHub repo.
php -r "\$c=json_decode(file_get_contents('$LOCAL_GIT_DIR/composer.json'),true); unset(\$c['scripts'],\$c['repositories'],\$c['require-dev'],\$c['autoload-dev']); file_put_contents('$NS_WORK/composer.json', json_encode(\$c, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));"

# Namespacer's Package.process() uses 'cp -r src/ dst/' expecting the macOS
# behaviour (copy CONTENTS into dst).  On Linux, 'cp -r src/ dst/' copies the
# directory itself, creating dst/src_leaf/ — one nesting level too deep.
# Shim cp so that trailing-slash source paths are rewritten to use '/.' which
# copies contents on both Linux and macOS.
NS_BIN="$(mktemp -d)"
cat > "$NS_BIN/cp" << 'CPEOF'
#!/bin/bash
args=()
last=$(($# - 1))
i=0
for arg; do
    if [[ $i -lt $last && "$arg" != -* && "$arg" == */ ]]; then
        args+=("${arg}.")
    else
        args+=("$arg")
    fi
    ((i++))
done
exec /bin/cp "${args[@]}"
CPEOF
chmod +x "$NS_BIN/cp"

PATH="$NS_BIN:$COMPOSER_INSTALL_DIR:$PATH" \
    "$LOCAL_GIT_DIR/inc/third-party/vsolovei-smartling/namespacer/bin/namespacer" \
    --source . \
    --package smartling-connector \
    --namespace "Smartling\\Vendor" \
    --config ./namespacer.config.php \
    inc
php fix-double-namespace.php
rm -rf "$NS_BIN"

rm -rf "$LOCAL_GIT_DIR/inc/lib"
cp -r "$NS_WORK/inc/lib" "$LOCAL_GIT_DIR/inc/"
rm -rf "$NS_WORK"

cd "$LOCAL_GIT_DIR"

svn -q checkout https://plugins.svn.wordpress.org/smartling-connector/trunk trunk

chown -R mysql:mysql /var/lib/mysql && service mysql start

# remove installer plugin dir and replace with dev dir
rm -rf "$PLUGIN_DIR"
ln -s "$LOCAL_GIT_DIR" "$PLUGIN_DIR"

export AUTOLOADER="${PLUGIN_DIR}/inc/autoload.php"
export PHP_IDE_CONFIG="serverName=Docker"
export TEST_DATA_DIR="${PLUGIN_DIR}/tests/IntegrationTests/testdata"
export TEST_CONFIG="$TEST_DATA_DIR/wp-tests-config.php"

ln -s "${TEST_DATA_DIR}/acf-pro-test-definitions" "${WP_PLUGINS_DIR}/acf-pro-test-definitions"
ln -s "${TEST_DATA_DIR}/exec-plugin" "${WP_PLUGINS_DIR}/exec-plugin"

cd ${PLUGIN_DIR}

${WPCLI} cron event run wp_version_check --path="${WP_INSTALL_DIR}"

sed -i 's/cron.interval.throttle: 120/cron.interval.throttle: 0/' inc/config/cron.yml

cd "${PLUGIN_DIR}/inc/third-party/bin"

PHPUNIT_BIN="$(pwd)/phpunit"

chmod +x $PHPUNIT_BIN

PHPUNIT_XML="${PLUGIN_DIR}/tests/phpunit.xml"

# ── E2E (Playwright) ───────────────────────────────────────────────────────────
# Always use localhost as the WordPress domain for E2E tests instead of the
# installed domain (test.com).  test.com is a real internet domain — WordPress
# makes outbound PHP HTTP requests using its own siteurl (wp_remote_get for
# cron, heartbeat, REST pre-loads, plugin update checks).  When siteurl is
# test.com those requests leave the container and reach the public internet;
# the remote server may hang the connection for 30-60 s, causing the body of
# admin pages to stall mid-render while domcontentloaded never fires.
# localhost always resolves to 127.0.0.1 in PHP without any DNS lookup, so
# every loopback request hits the local PHP server instantly.
INSTALLED_DOMAIN="${WP_INSTALLATION_DOMAIN:-test.com}"
E2E_DOMAIN="localhost"

# Playwright tests use absolute paths (/wp-login.php, /wp-admin/...). Absolute
# paths in Playwright ignore the base URL's path component, so WordPress MUST
# be at the domain root (http://localhost), not a sub-path like
# http://localhost/WP_INSTALL_DIR. multisite-convert may store the install
# directory name as a URL path component — detect and fix that here.
#
# Four things must be consistent for WordPress to serve correctly at the root:
#   1. DB options (siteurl, home) — via wp search-replace
#   2. Multisite path columns (wp_site.path, wp_blogs.path) — via direct SQL
#   3. Multisite domain columns (wp_site.domain, wp_blogs.domain) — via SQL
#   4. DOMAIN_CURRENT_SITE / PATH_CURRENT_SITE constants in wp-config.php
EXPECTED_SITEURL="http://${E2E_DOMAIN}"
CURRENT_SITEURL=$(${WPCLI} option get siteurl 2>/dev/null | tr -d '\n\r ')
echo "Current siteurl: ${CURRENT_SITEURL:-<empty>}"
if [ -n "${CURRENT_SITEURL}" ] && [ "${CURRENT_SITEURL}" != "${EXPECTED_SITEURL}" ]; then
    echo "Normalizing WordPress base URL to ${EXPECTED_SITEURL}"
    # Replace all full-URL occurrences in the database (handles serialized data)
    ${WPCLI} search-replace "${CURRENT_SITEURL}" "${EXPECTED_SITEURL}" \
        --all-tables --skip-columns=guid
    # Fix path-only multisite columns (domain still holds INSTALLED_DOMAIN here)
    ${WPCLI} db query "UPDATE ${WP_DB_TABLE_PREFIX}site \
        SET path=REPLACE(path, '${WP_INSTALL_DIR}', '') \
        WHERE domain='${INSTALLED_DOMAIN}' AND path LIKE '${WP_INSTALL_DIR}%'"
    ${WPCLI} db query "UPDATE ${WP_DB_TABLE_PREFIX}blogs \
        SET path=REPLACE(path, '${WP_INSTALL_DIR}', '') \
        WHERE domain='${INSTALLED_DOMAIN}' AND path LIKE '${WP_INSTALL_DIR}%'"
    # Migrate domain columns from installed value to localhost
    ${WPCLI} db query \
        "UPDATE ${WP_DB_TABLE_PREFIX}site  SET domain='localhost' WHERE domain='${INSTALLED_DOMAIN}'"
    ${WPCLI} db query \
        "UPDATE ${WP_DB_TABLE_PREFIX}blogs SET domain='localhost' WHERE domain='${INSTALLED_DOMAIN}'"
    # Sync wp-config.php constants (DOMAIN_CURRENT_SITE must match wp_site.domain)
    ${WPCLI} config set DOMAIN_CURRENT_SITE "localhost"
    ${WPCLI} config set PATH_CURRENT_SITE "/"
fi

# Disable WordPress core cron spawning for E2E tests: wp-cron.php makes
# outbound HTTP requests to api.wordpress.org which can hang for 30+ seconds,
# occupying all PHP workers and causing test page loads to time out.
${WPCLI} config set DISABLE_WP_CRON true --raw

# Serve scripts as individual static files instead of via load-scripts.php,
# which bootstraps WordPress on every script request.
${WPCLI} config set CONCATENATE_SCRIPTS false --raw

# Block external outbound WordPress HTTP API calls during E2E tests.
# Plugins make synchronous licence checks / update pings that each time out at
# 5 s — with 15-20 plugins this can delay admin page rendering by 75-200 s.
# The pre_http_request filter returns WP_Error before any socket is opened,
# so external calls fail in < 1 ms.  Smartling API calls use Guzzle directly
# (not WordPress HTTP API) and are unaffected.  Localhost requests pass through.
# The mu-plugin is removed before PHPUnit runs so integration tests retain
# full Smartling API access.
mkdir -p "${WP_INSTALL_DIR}/wp-content/mu-plugins"
cat > "${WP_INSTALL_DIR}/wp-content/mu-plugins/e2e-fast-http.php" << 'MU_EOF'
<?php
add_filter('pre_http_request', static function($preempt, $parsed_args, $url) {
    if (strpos($url, '://localhost') !== false || strpos($url, '://127.0.0.1') !== false) {
        return $preempt;
    }
    return new WP_Error('e2e_blocked', 'External HTTP blocked during E2E tests');
}, 1, 3);
MU_EOF

# Custom router: serve static files (CSS, JS, fonts, images) directly without
# bootstrapping WordPress; only .php files and virtual URLs go through WP.
# This keeps PHP workers free for actual page requests.
cat > /tmp/wp-e2e-router.php << 'ROUTER_EOF'
<?php
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = $_SERVER['DOCUMENT_ROOT'] . $uri;
if (is_file($file) || is_dir($file)) {
    return false;
}
chdir($_SERVER['DOCUMENT_ROOT']);
require_once $_SERVER['DOCUMENT_ROOT'] . '/index.php';
ROUTER_EOF

PHP_CLI_SERVER_WORKERS=4 php -S 0.0.0.0:80 -t "${WP_INSTALL_DIR}" /tmp/wp-e2e-router.php \
    > /var/log/php-e2e-server.log 2>&1 &
WP_SERVER_PID=$!

# Wait for the server TCP port to open — do NOT make HTTP requests here.
# HTTP health checks trigger WordPress initialization (cron spawning,
# outbound API calls) which occupies PHP workers for 30+ seconds and
# causes the subsequent Playwright login to time out.
echo "Waiting for WP server to accept connections on port 80..."
WP_SERVER_READY=0
for i in $(seq 1 30); do
    if (echo >/dev/tcp/localhost/80) 2>/dev/null; then
        echo "WP server port 80 open after ${i}s"
        WP_SERVER_READY=1
        break
    fi
    sleep 1
done
if [ "${WP_SERVER_READY}" -eq 0 ]; then
    echo "ERROR: WP server did not start within 30 seconds"
    echo "--- PHP server log ---"
    cat /var/log/php-e2e-server.log 2>/dev/null || echo "(empty)"
    echo "--- END ---"
fi

# Create test fixtures: one post + one Smartling profile
E2E_TEST_POST_ID=$(${WPCLI} post create \
    --url="${E2E_DOMAIN}" \
    --post_title="E2E Test Post" \
    --post_status=publish \
    --porcelain)

${WPCLI} eval-file "${LOCAL_GIT_DIR}/tests/playwright/fixtures/create-profile.php" \
    --url="${E2E_DOMAIN}"

echo "--- DIAGNOSTIC: Profile table ---"
${WPCLI} db query \
    "SELECT id, profile_name, is_active, original_blog_id, LEFT(target_locales,120) AS locales \
     FROM ${WP_DB_TABLE_PREFIX}smartling_configuration_profiles" \
    --url="${E2E_DOMAIN}" 2>&1 || true
echo "--- DIAGNOSTIC: Plugin status ---"
${WPCLI} plugin status smartling-connector --url="${E2E_DOMAIN}" 2>&1 || true

# Pre-warm all 4 PHP workers so OPcache is hot before Playwright starts.
# Each worker must compile WordPress + plugin sources on its first request
# (60-90 s); firing 4 concurrent requests ensures all workers are warm.
# /wp-admin/ triggers a full bootstrap (including plugin loading) before
# redirecting to wp-login.php, so all plugin files get compiled.
echo "Pre-warming PHP workers (OPcache cold-start)..."
WARMUP_PIDS=()
for i in $(seq 1 4); do
    curl -sf --max-time 180 "http://localhost/wp-admin/" > /dev/null 2>&1 &
    WARMUP_PIDS+=($!)
done
# Wait only for the 4 curl processes, NOT for the PHP server (which is also a
# background job and runs indefinitely — a bare 'wait' would block forever).
for pid in "${WARMUP_PIDS[@]}"; do wait "$pid" 2>/dev/null || true; done
unset WARMUP_PIDS
echo "PHP workers pre-warmed."

# Run Playwright — @playwright/test and Chromium are pre-installed globally in
# the Docker image; no runtime npm install needed.
# NODE_PATH exposes the global node_modules so that require('@playwright/test')
# inside playwright.config.js resolves correctly without a local node_modules.
# timeout 900: Playwright can hang after all tests complete while waiting for
# Chromium child processes to exit cleanly. Without a ceiling, test.sh blocks
# at this line indefinitely and PHPUnit never runs. 900 s is well above the
# longest expected E2E run (9 tests × 240 s = 2160 s worst case, but retries
# run on warm workers and finish in < 30 s each — real budget is ~400 s).
cd "${LOCAL_GIT_DIR}"
NODE_PATH="$(npm root -g)" \
    CI=true \
    PLAYWRIGHT_BASE_URL="${EXPECTED_SITEURL}" \
    E2E_TEST_POST_ID="${E2E_TEST_POST_ID}" \
    WP_ADMIN_USER=wp \
    WP_ADMIN_PASSWORD=wp \
    timeout 900 playwright test --reporter=junit,line

E2E_EXIT_CODE=$?
if [ "${E2E_EXIT_CODE}" -eq 124 ]; then
    echo "WARNING: playwright test timed out after 900 s — Chromium may have hung on shutdown"
fi

echo "--- WP PHP SERVER LOG (last 100 lines) ---"
tail -100 /var/log/php-e2e-server.log 2>/dev/null || echo "(log empty or missing)"
echo "--- END WP PHP SERVER LOG ---"

kill ${WP_SERVER_PID} 2>/dev/null || true
# Remove the E2E HTTP block before PHPUnit runs so integration tests retain
# full access to the Smartling API.
rm -f "${WP_INSTALL_DIR}/wp-content/mu-plugins/e2e-fast-http.php"

# Restore the original WordPress domain for PHPUnit.  The E2E section changed
# the domain to localhost so PHP loopback requests don't hit the real internet.
# PHPUnit's bootstrap sets HTTP_HOST = WP_INSTALLATION_DOMAIN (test.com) and
# expects the WordPress multisite DB to have that domain — so we must undo the
# search-replace before PHPUnit runs, or WordPress can't find the current site.
if [ -n "${CURRENT_SITEURL}" ] && [ "${CURRENT_SITEURL}" != "${EXPECTED_SITEURL}" ]; then
    echo "Restoring WordPress domain for PHPUnit (localhost → ${INSTALLED_DOMAIN})..."
    ${WPCLI} search-replace "${EXPECTED_SITEURL}" "${CURRENT_SITEURL}" \
        --all-tables --skip-columns=guid
    ${WPCLI} db query \
        "UPDATE ${WP_DB_TABLE_PREFIX}site SET domain='${INSTALLED_DOMAIN}' WHERE domain='${E2E_DOMAIN}'"
    ${WPCLI} db query \
        "UPDATE ${WP_DB_TABLE_PREFIX}blogs SET domain='${INSTALLED_DOMAIN}' WHERE domain='${E2E_DOMAIN}'"
    ${WPCLI} config set DOMAIN_CURRENT_SITE "${INSTALLED_DOMAIN}"
    # Delete BB's cached siteurl option so BB's admin_init handler sees no
    # stored value and skips URL-change detection entirely.  The search-replace
    # above may have produced a value with a trailing slash (e.g.
    # "http://test.com/" vs "http://test.com") that still triggers a mismatch.
    # Deleting by option_name avoids the value-format ambiguity; BB will just
    # write a fresh value on the next request.
    ${WPCLI} db query \
        "DELETE FROM ${WP_DB_TABLE_PREFIX}options \
         WHERE (option_name LIKE 'fl_%' OR option_name LIKE '_fl_%') \
         AND option_name LIKE '%url%'" \
        2>/dev/null || true
fi
# FLUpdater stub: BB Lite's admin_init hook calls FLBuilderUpdate::init() when
# a URL change is detected; that function instantiates FLUpdater — a class that
# only exists in the premium version — and fatals with "Class FLUpdater not found".
# The stub below satisfies the instantiation so the test suite can continue.
mkdir -p "${WP_INSTALL_DIR}/wp-content/mu-plugins"
cat > "${WP_INSTALL_DIR}/wp-content/mu-plugins/fl-updater-shim.php" << 'SHIM_EOF'
<?php
if ( ! class_exists( 'FLUpdater' ) ) {
    class FLUpdater {
        public function __construct( array $args = [] ) {}
    }
}
SHIM_EOF
export WP_DB_HOST="${MYSQL_HOST:-localhost}"
# ── END E2E ────────────────────────────────────────────────────────────────────

echo "--- Starting PHPUnit ---"
${PHPUNIT_BIN} -c ${PHPUNIT_XML}
PHPUNIT_EXIT_CODE=$?
echo "--- PHPUnit finished (exit code ${PHPUNIT_EXIT_CODE}) ---"

service mysql stop

cd ${PLUGIN_DIR}
cd trunk
cp ../readme.txt ../smartling-connector.php .
cp -r ../css .
cp -r ../inc/config ./inc
cp -r ../inc/lib ./inc
cp -r ../inc/Smartling ./inc
cp -r ../js .
cp -r ../languages .
svn add --force * --auto-props --parents --depth infinity -q
svn status

zip -q -r ${PLUGIN_DIR}/release.zip ${PLUGIN_DIR} -x trunk/**\*

if [ "${E2E_EXIT_CODE}" -ne 0 ] || [ "${PHPUNIT_EXIT_CODE}" -ne 0 ]; then
    exit 1
fi
exit 0
