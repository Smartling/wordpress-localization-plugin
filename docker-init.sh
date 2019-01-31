#!/usr/bin/env bash

export CUR_DIR=$(pwd)
export WP_INSTALL_DIR="/var/www"
export WP_INSTALLATION_DOMAIN="localhost:8004"

download_wp_cli() {
    INSTALLPATH=$WP_INSTALL_DIR
    cd $INSTALLPATH
    if [ -f "wp-cli.phar" ]; then
        rm wp-cli.phar
    fi
    WPCLI_DIST="https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar"
    curl -O $WPCLI_DIST
    chmod +x ./wp-cli.phar
}

install_wordpress() {
    download_wp_cli $WP_INSTALL_DIR
    WPCLI="$WP_INSTALL_DIR/wp-cli.phar --allow-root"
    URL=localhost
    $WPCLI config create --dbname=wp --dbuser=wp --dbpass=wp --dbhost=wp_connector_db --skip-check
    $WPCLI core install --url=$URL --title="Dev installation" --admin_user=wp --admin_password=wp --admin_email=no@ema.il --skip-email

    $WPCLI option update siteurl "http://$URL:8004"
    $WPCLI option update home "http://$URL:8004"

    $WPCLI core multisite-convert
    echo Creating sites...
    # List of created sites is separated by ',' char
    # Definition of each site has 3 fields: Site title, Smartling locale, Site slug all fields are separated by ':' char
    export SITES="Spanish Site:es:es,French Site:fr-FR:fr,Russian Site:ru-RU:ru,Ukrainian Site:uk-UA:ua"
    PREV_IFS=$IFS
    IFS=',' read -a array <<< "$SITES"
    for site in "${array[@]}"
    do
        $WPCLI site create --slug="${site##*\:}" --title="${site%%\:*}" --email=test@wp.org
    done
    DEPENDENCIES="multilingual-press;advanced-custom-fields;wordpress-seo"
    IFS=';' read -a array <<< "$DEPENDENCIES"
    for plugin_name in "${array[@]}"
    do
        $WPCLI plugin install $plugin_name
        $WPCLI plugin activate $plugin_name --network
    done
    IFS=$PREV_IFS
}

install_composer() {
    PLUGIN_DIR="/var/www/wp-content/plugins/smartling-connector-dev"
    COMPOSER_INSTALL_DIR="$PLUGIN_DIR/inc/third-party/bin"
    if [ ! -d "$COMPOSER_INSTALL_DIR" ]; then
        mkdir -p "$COMPOSER_INSTALL_DIR"
    fi
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    php composer-setup.php --install-dir="$COMPOSER_INSTALL_DIR" --filename=composer --version=1.0.0
    php -r "unlink('composer-setup.php');"
    export COMPOSER_BIN="$COMPOSER_INSTALL_DIR/composer"
    cd $PLUGIN_DIR
    $COMPOSER_BIN update
}



install_wordpress
install_composer