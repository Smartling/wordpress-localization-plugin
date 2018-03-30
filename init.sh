#!/usr/bin/env bash
#############################################################################################################################################
#    Script allows to bring up default development-environment for                                                                          #
# smartling-connector plugin.                                                                                                               #
#                                                                                                                                           #
# Usage:                                                                                                                                    #
#  curl -s https://raw.githubusercontent.com/Smartling/wordpress-localization-plugin/master/init.sh | bash /dev/stdin --target-dir=<dir>    #
# e.g.:                                                                                                                                     #
#  curl -s https://raw.githubusercontent.com/Smartling/wordpress-localization-plugin/master/init.sh | bash /dev/stdin --target-dir=/var/www #
#                                                                                                                                           #
#############################################################################################################################################

export CUR_DIR=$(pwd)

logit () {
    DATE=$(date +"[%Y-%m-%d %H:%M:%S]")
    echo -n "$DATE "
    case $1 in
        info) echo -n '[INFO] '
            ;;
        warn) echo -n '[WARNING] '
            ;;
        err)  echo -n '[ERROR] '
            ;;
        *) echo $1
            ;;
    esac
    echo $2
}

usage () {
    echo -e "\n\n"
    echo -e "Usage:\n$0 --target-dir=<target directory>"
    echo -e "\n\nWhere:"
    echo -e "\t--target-dir\t\t- Path to directory where wordpress should be deployed"
    exit 1
}

set -- $(getopt -n$0 -u --longoptions="target-dir: " "h" "$@") || usage
while [ $# -gt 0 ];do
    case "$1" in
        --target-dir) export WP_INSTALL_DIR="$2";shift;;
        --)     shift;break;;
        -*)     usage;break;;
        *)      break;;
    esac
    shift
done

validateParams () {
    [ -z "$WP_INSTALL_DIR" ] && { logit err "--target-dir should be set"; usage; }
}

validateParams

# Installation settings
export WP_INSTALLATION_DOMAIN="mlp.wp.dev.local"

download_wp_cli() {
    INSTALLPATH=$1
    if [ ! -d "$INSTALLPATH" ]; then
        mkdir -p "$INSTALLPATH"
    fi
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
    WPCLI="$WP_INSTALL_DIR/wp-cli.phar"
    URL=mlp.wp.dev.local
    $WPCLI core download
    $WPCLI core config --dbname=wp --dbuser=wp --dbpass=wp --dbhost=localhost
    $WPCLI core install --url=$URL --title="Dev installation" --admin_user=wp --admin_password=wp --admin_email=no@ema.il --skip-email

    $WPCLI option update siteurl "http://$URL"
    $WPCLI option update home "http://$URL"

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
    PLUGIN_DIR=$1
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

install_connector() {
    CONNECTOR_DIR_NAME="smarting-connector-dev-test"
    CONNECTOR_DIR="$WP_INSTALL_DIR/wp-content/plugins/$CONNECTOR_DIR_NAME"
    if [ -d "$CONNECTOR_DIR" ]; then
        rm -rf "$CONNECTOR_DIR"
    fi
    mkdir -p $CONNECTOR_DIR
    git clone https://github.com/Smartling/wordpress-localization-plugin.git $CONNECTOR_DIR
    install_composer $CONNECTOR_DIR
}

install_wordpress
install_connector
