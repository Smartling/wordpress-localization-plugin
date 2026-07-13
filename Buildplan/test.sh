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

echo "--- DIAG: deprecation-contracts after outer composer install ---"
ls -la "$NS_WORK/inc/third-party/symfony/deprecation-contracts/" 2>&1 || echo "MISSING: $NS_WORK/inc/third-party/symfony/deprecation-contracts/"

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

echo "--- DIAG: deprecation-contracts after namespacer ---"
ls -la "$NS_WORK/inc/lib/smartling-connector-symfony/deprecation-contracts/" 2>&1 || echo "MISSING: $NS_WORK/inc/lib/smartling-connector-symfony/deprecation-contracts/"

rm -rf "$LOCAL_GIT_DIR/inc/lib"
cp -r "$NS_WORK/inc/lib" "$LOCAL_GIT_DIR/inc/"
rm -rf "$NS_WORK"

echo "--- DIAG: deprecation-contracts after cp to LOCAL_GIT_DIR ---"
ls -la "$LOCAL_GIT_DIR/inc/lib/smartling-connector-symfony/deprecation-contracts/" 2>&1 || echo "MISSING: $LOCAL_GIT_DIR/inc/lib/smartling-connector-symfony/deprecation-contracts/"

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
# Resolve WP_INSTALLATION_DOMAIN so the PHP server and Playwright agree on the URL.
E2E_DOMAIN="${WP_INSTALLATION_DOMAIN:-test.com}"

echo "127.0.0.1 ${E2E_DOMAIN}" >> /etc/hosts

# Playwright tests use absolute paths (/wp-login.php, /wp-admin/...). Absolute
# paths in Playwright ignore the base URL's path component, so WordPress MUST
# be at the domain root (http://test.com), not a sub-path like
# http://test.com/WP_INSTALL_DIR. multisite-convert may store the install
# directory name as a URL path component — detect and fix that here.
#
# Three things must be consistent for WordPress to serve correctly at the root:
#   1. DB options (siteurl, home) — via wp search-replace
#   2. Multisite path columns (wp_site.path, wp_blogs.path) — via direct SQL
#   3. PATH_CURRENT_SITE constant in wp-config.php — via wp config set
EXPECTED_SITEURL="http://${E2E_DOMAIN}"
CURRENT_SITEURL=$(${WPCLI} option get siteurl 2>/dev/null | tr -d '\n\r ')
echo "Current siteurl: ${CURRENT_SITEURL:-<empty>}"
if [ -n "${CURRENT_SITEURL}" ] && [ "${CURRENT_SITEURL}" != "${EXPECTED_SITEURL}" ]; then
    echo "Normalizing WordPress base URL to ${EXPECTED_SITEURL}"
    # Replace all full-URL occurrences in the database (handles serialized data)
    ${WPCLI} search-replace "${CURRENT_SITEURL}" "${EXPECTED_SITEURL}" \
        --all-tables --skip-columns=guid
    # Fix path-only multisite columns (not updated by search-replace above)
    ${WPCLI} db query "UPDATE ${WP_DB_TABLE_PREFIX}site \
        SET path=REPLACE(path, '${WP_INSTALL_DIR}', '') \
        WHERE domain='${E2E_DOMAIN}' AND path LIKE '${WP_INSTALL_DIR}%'"
    ${WPCLI} db query "UPDATE ${WP_DB_TABLE_PREFIX}blogs \
        SET path=REPLACE(path, '${WP_INSTALL_DIR}', '') \
        WHERE domain='${E2E_DOMAIN}' AND path LIKE '${WP_INSTALL_DIR}%'"
    # Sync the PATH_CURRENT_SITE constant in wp-config.php
    ${WPCLI} config set PATH_CURRENT_SITE "/"
fi

# Start WordPress via the wp-cli built-in server. wp server uses a router
# script that correctly handles WordPress multisite initialization; bare
# php -S hangs on multisite bootstrap after URL normalization.
PHP_CLI_SERVER_WORKERS=4 ${WPCLI} server --host=0.0.0.0 --port=80 \
    > /var/log/php-e2e-server.log 2>&1 &
WP_SERVER_PID=$!
sleep 3  # wait for server to bind

# Create test fixtures: one post + one Smartling profile
E2E_TEST_POST_ID=$(${WPCLI} post create \
    --url="${E2E_DOMAIN}" \
    --post_title="E2E Test Post" \
    --post_status=publish \
    --porcelain)

${WPCLI} eval-file "${LOCAL_GIT_DIR}/tests/playwright/fixtures/create-profile.php" \
    --url="${E2E_DOMAIN}"

# Run Playwright — @playwright/test and Chromium are pre-installed globally in
# the Docker image; no runtime npm install needed.
# NODE_PATH exposes the global node_modules so that require('@playwright/test')
# inside playwright.config.js resolves correctly without a local node_modules.
cd "${LOCAL_GIT_DIR}"
NODE_PATH="$(npm root -g)" \
    PLAYWRIGHT_BASE_URL="${EXPECTED_SITEURL}" \
    E2E_TEST_POST_ID="${E2E_TEST_POST_ID}" \
    WP_ADMIN_USER=wp \
    WP_ADMIN_PASSWORD=wp \
    playwright test --reporter=junit,line

E2E_EXIT_CODE=$?

kill ${WP_SERVER_PID} 2>/dev/null || true
# ── END E2E ────────────────────────────────────────────────────────────────────

${PHPUNIT_BIN} -c ${PHPUNIT_XML}

PHPUNIT_EXIT_CODE=$?

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
