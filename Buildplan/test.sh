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

exit $PHPUNIT_EXIT_CODE
