#!/usr/bin/env bash

export CUR_DIR=$(pwd)                  # save source dir
export SCRIPT_DIR=$(dirname $0)
cd $SCRIPT_DIR
export SCRIPT_DIR=$(pwd)               # save script dir

cd $SCRIPT_DIR/../
export PLUGIN_DEV_DIR=$(pwd)

# Installation settings
export WP_TEMP_INSTALL_FOLDER="src"
export WP_INSTALL_DIR="$SCRIPT_DIR/IntegrationTests/$WP_TEMP_INSTALL_FOLDER"

export WP_DB_HOST="localhost"
export WP_DB_PORT=3306
export WP_DB_USER="wp"
export WP_DB_PASS="wp"
export WP_DB_NAME="wp"
export WP_DB_TABLE_PREFIX="wp_"
export WP_INSTALLATION_DOMAIN="mlp.wp.dev.local"
export WPCLI="$WP_INSTALL_DIR/wp-cli.phar"

export GITHUB_OAUTH_TOKEN="e5e0aa9f98ee27f4661efcc99b5bc3a5017cccb2"

export AUTOLOADER="$WP_INSTALL_DIR/wp-content/plugins/smartling-connector/inc/autoload.php"
export TEST_DATA_DIR="$SCRIPT_DIR/IntegrationTests/testdata"
export TEST_CONFIG="$SCRIPT_DIR/IntegrationTests/testdata/wp-tests-config.php"

INSTALL_COMPOSER () {
    cd ~
    USERDIR=$(pwd)
    COMPOSER_DIR="$USERDIR/.composer"

    if [ ! -d "$COMPOSER_DIR" ]; then
        mkdir "$COMPOSER_DIR"
        cd $PLUGIN_DEV_DIR
        echo "{\"github-oauth\":{\"github.com\":\"$GITHUB_OAUTH_TOKEN\"}}" > "$COMPOSER_DIR/auth.json"
    fi
    COMPOSER_INSTALL_DIR="$PLUGIN_DEV_DIR/inc/third-party/bin"
    if [ ! -d "$COMPOSER_INSTALL_DIR" ]; then
        mkdir -p "$COMPOSER_INSTALL_DIR"
    fi

    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    php composer-setup.php --install-dir="$PLUGIN_DEV_DIR/inc/third-party/bin" --filename=composer --version=1.0.0
    php -r "unlink('composer-setup.php');"

    export COMPOSER_BIN="$PLUGIN_DEV_DIR/inc/third-party/bin/composer"

    cd $PLUGIN_DEV_DIR
    $COMPOSER_BIN update
}

CREATE_BUILD () {
    INSTALL_COMPOSER
    $PLUGIN_DEV_DIR/composer update
    $PLUGIN_DEV_DIR/build-dev.sh
}

INSTALL_WORDPRESS () {
if [ -d "$WP_INSTALL_DIR" ]; then
  rm -rf "$WP_INSTALL_DIR"
fi
mkdir "$WP_INSTALL_DIR"

# Downloading WP_CLI
cd "$WP_INSTALL_DIR"
curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
chmod +x ./wp-cli.phar


$WPCLI core download


$WPCLI config create --dbname=$WP_DB_NAME --dbuser=$WP_DB_USER --dbpass=$WP_DB_PASS --dbprefix=$WP_DB_TABLE_PREFIX
$WPCLI core install --url=$WP_INSTALLATION_DOMAIN --title=Test --admin_user=wp --admin_password=wp --admin_email=test@wp.org --skip-email
$WPCLI core multisite-convert
echo Creating sites...
$WPCLI site create --slug=es --title="Spanish site" --email=es@no.mail
$WPCLI site create --slug=fr --title="French site" --email=fr@no.mail
$WPCLI site create --slug=ru --title="Russian site" --email=ru@no.mail
$WPCLI site create --slug=ua --title="Ukrainian site" --email=ua@no.mail


echo Installing plugins...
$WPCLI plugin install multilingual-press advanced-custom-fields wordpress-seo
echo Activating plugins...
$WPCLI plugin activate multilingual-press advanced-custom-fields wordpress-seo --network
}

EXECUTE_TESTS () {
    cd $WP_INSTALL_DIR/wp-content/plugins/smartling-connector/inc/third-party/bin
    PHPUNIT_BIN="$(pwd)/phpunit"
    PHPUNIT_XML="$WP_INSTALL_DIR/wp-content/plugins/smartling-connector/tests/integration.xml"
    $PHPUNIT_BIN -c $PHPUNIT_XML
}

CLEAN_DATABASE () {
    $WPCLI db reset --yes
}

INSTALL_SMARTLING_CONNECTOR () {
if [ -f "$PLUGIN_DEV_DIR/smartling-connector.zip" ]; then
  rm -rf "$PLUGIN_DEV_DIR/smartling-connector.zip"
fi
if [ -d "$WP_INSTALL_DIR/wp-content/plugins/smartling-connector" ]; then
  rm -rf "$WP_INSTALL_DIR/wp-content/plugins/smartling-connector"
fi
CREATE_BUILD
$WPCLI plugin install "$PLUGIN_DEV_DIR/smartling-connector.zip" --activate-network --path="$WP_INSTALL_DIR"
$WPCLI cron event run wp_version_check --path="$WP_INSTALL_DIR"

}

if [ "--full" == "$1" ]; then
    CLEAN_DATABASE
    INSTALL_WORDPRESS
    INSTALL_SMARTLING_CONNECTOR
fi

EXECUTE_TESTS