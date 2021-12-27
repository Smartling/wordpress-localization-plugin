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
cp $COMPOSER_INSTALL_DIR/composer /usr/local/bin/composer
composer global require ilab/namespacer

chown -R mysql:mysql /var/lib/mysql && service mysql start

cd "$LOCAL_GIT_DIR"
chdir
curl -s -o ~/.composer/vendor/ilab/namespacer/Source/Commands/RenamespaceCommand.php https://raw.githubusercontent.com/vsolovei-smartling/namespacer/master/Source/Commands/RenamespaceCommand.php
curl -s -o ~/.composer/vendor/ilab/namespacer/Source/Models/Package.php https://raw.githubusercontent.com/vsolovei-smartling/namespacer/master/Source/Models/Package.php
curl -s -o ~/.composer/vendor/ilab/namespacer/Source/Models/Project.php  https://raw.githubusercontent.com/vsolovei-smartling/namespacer/master/Source/Models/Project.php

composer update
~/.composer/vendor/ilab/namespacer/bin/namespacer --composer ./composer.json --package smartling-connector --namespace "Smartling\Vendor" inc
# two instances of replace not a typo
find ./inc/lib -type f -name '*.php' -exec sed -i '' 's~Smartling\\Vendor\\Smartling\\Vendor\\~Smartling\\Vendor\\~g' {} +
find ./inc/lib -type f -name '*.php' -exec sed -i '' 's~Smartling\\Vendor\\Smartling\\Vendor\\~Smartling\\Vendor\\~g' {} +

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

cd "${PLUGIN_DIR}/inc/third-party/bin"

PHPUNIT_BIN="$(pwd)/phpunit"

chmod +x $PHPUNIT_BIN

PHPUNIT_XML="${PLUGIN_DIR}/tests/phpunit.xml"

#${PHPUNIT_BIN} -c ${PHPUNIT_XML}

PHPUNIT_EXIT_CODE=$?

service mysql stop

#svn checkout https://plugins.svn.wordpress.org/smartling-connector/trunk trunk

chdir

zip -r wordpress-connector.zip trunk/
