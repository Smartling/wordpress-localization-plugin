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
php composer-setup.php --install-dir="$COMPOSER_INSTALL_DIR" --filename=composer --version=1.0.0
php -r "unlink('composer-setup.php');"
COMPOSER_BIN="$COMPOSER_INSTALL_DIR/composer"


cd "$LOCAL_GIT_DIR"
$COMPOSER_BIN update

echo ---
pwd


#COPY smartling-connector-dev ${PLUGIN_DIR}

#RUN ln -s "${PLUGIN_DIR}/tests/IntegrationTests/testdata/acf-pro-test-definitions" "${WP_PLUGINS_DIR}/acf-pro-test-definitions"
#RUN ln -s "${PLUGIN_DIR}/tests/IntegrationTests/testdata/exec-plugin" "${WP_PLUGINS_DIR}/exec-plugin"

#RUN chown -R mysql:mysql /var/lib/mysql && service mysql start && \
#    ${WPCLI} plugin activate \
#    exec-plugin \
#    advanced-custom-fields-pro \
#    --network



ls -lah /

