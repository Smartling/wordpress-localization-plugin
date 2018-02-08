#!/usr/bin/env bash
export CUR_DIR=$(pwd)
export SCRIPT_DIR=$(dirname $0)
cd $SCRIPT_DIR
export SCRIPT_DIR=$(pwd)
cd $SCRIPT_DIR/../
export PLUGIN_DEV_DIR=$(pwd)

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
    echo -e "Usage:\n$0 --oauth=<token> --db-host=<host> --db-user=<user> --db-pass=<pass> --db-name=<name> --project-id=<project> --user-ident=<identifier> --token-secret=<secret>"
    echo -e "\n\nWhere:"
    echo -e "\t--oauth\t\t- The github oauth token"
    echo -e "\t--db-host\t- The hostname/ip of MySQL server. Optional. Default:localhost"
    echo -e "\t--db-user\t- The username of MySQL server for Wordpress installation. Is used to create database"
    echo -e "\t--db-pass\t- The password for database user"
    echo -e "\t--db-name\t- The Wordpress database name. Note: Script will try to delete it and create."
    echo -e "\t--project-id\t- Smartling connection credential: Project ID"
    echo -e "\t--user-ident\t- Smartling connection credential: User Identifier"
    echo -e "\t--token-secret\t- Smartling connection credential: Token Secret"
    exit 1
}

set -- $(getopt -n$0 -u --longoptions="oauth: db-host: db-user: db-pass: db-name: project-id: user-ident: token-secret: " "h" "$@") || usage
while [ $# -gt 0 ];do
    case "$1" in
        --oauth) export GITHUB_OAUTH_TOKEN="$2";shift;;
        --db-host) export WP_DB_HOST="$2";shift;;
        --db-user) export WP_DB_USER="$2";shift;;
        --db-pass) export WP_DB_PASS="$2";shift;;
        --db-name) export WP_DB_NAME="$2";shift;;
        --project-id) export CRE_PROJECT_ID="$2";shift;;
        --user-ident) export CRE_USER_IDENTIFIER="$2";shift;;
        --token-secret) export CRE_TOKEN_SECRET="$2";shift;;
        --)     shift;break;;
        -*)     usage;break;;
        *)      break;;
    esac
    shift
done

validateParams () {
    [ -z "$GITHUB_OAUTH_TOKEN" ] && { logit err "--oauth should be set"; usage; }
    [ -z "$WP_DB_HOST" ] && { logit warn "--db-host is not set. Using 'localhost'"; export WP_DB_HOST="localhost"; }
    [ -z "$WP_DB_USER" ] && { logit err "--db-user should be set"; usage; }
    [ -z "$WP_DB_PASS" ] && { logit err "--db-pass should be set"; usage; }
    [ -z "$WP_DB_NAME" ] && { logit err "--db-name should be set"; usage; }
    [ -z "$CRE_PROJECT_ID" ] && { logit err "--project-id should be set"; usage; }
    [ -z "$CRE_USER_IDENTIFIER" ] && { logit err "--user-ident should be set"; usage; }
    [ -z "$CRE_TOKEN_SECRET" ] && { logit err "--token-secret should be set"; usage; }
}

validateParams

# Installation settings
export WP_TEMP_INSTALL_FOLDER="src"
export WP_INSTALL_DIR="$SCRIPT_DIR/IntegrationTests/$WP_TEMP_INSTALL_FOLDER"
export WP_DB_TABLE_PREFIX="wp_"
export WP_INSTALLATION_DOMAIN="mlp.wp.dev.local"
export WPCLI="$WP_INSTALL_DIR/wp-cli.phar"
export AUTOLOADER="$WP_INSTALL_DIR/wp-content/plugins/smartling-connector/inc/autoload.php"
export TEST_DATA_DIR="$SCRIPT_DIR/IntegrationTests/testdata"
export TEST_CONFIG="$TEST_DATA_DIR/wp-tests-config.php"

installComposer () {
    cd ~
    USERDIR=$(pwd)
    COMPOSER_DIR="$USERDIR/.composer"
    if [ ! -d "$COMPOSER_DIR" ]; then
        mkdir "$COMPOSER_DIR"
    fi
    echo "{\"github-oauth\":{\"github.com\":\"$GITHUB_OAUTH_TOKEN\"}}" > "$COMPOSER_DIR/auth.json"
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

createBuild () {
    installComposer
    cd "$PLUGIN_DEV_DIR"
    BUILD_FILENAME="smartling-connector.zip"
    TMP_BUILD_DIR="/tmp"
    SMARTLING_BUILD_DIR="$TMP_BUILD_DIR/smartling-builds"
    if [ -f "$PLUGIN_DEV_DIR/$BUILD_FILENAME" ]; then
        rm -rf "$PLUGIN_DEV_DIR/$BUILD_FILENAME"
    fi
    if [ -d "$SMARTLING_BUILD_DIR" ]; then
        rm -rf "$SMARTLING_BUILD_DIR"
    fi
    mkdir $SMARTLING_BUILD_DIR
    cp -r "$PLUGIN_DEV_DIR"/* "$SMARTLING_BUILD_DIR"
    cd "$SMARTLING_BUILD_DIR"
    rm -f ./*.zip
    rm -f ./*.log
    rm -Rf ./logs/logfile*
    rm -rf ./tests/IntegrationTests/src/*
    rm -rf ./*.phar
    zip -9 ./$BUILD_FILENAME -r ./*
    mv "./$BUILD_FILENAME" "$PLUGIN_DEV_DIR/"
    cd "$PLUGIN_DEV_DIR"
    rm -rf "$SMARTLING_BUILD_DIR"
}

installWPCLI () {
    if [ -d "$WP_INSTALL_DIR" ]; then
        echo "Removing temporary install directory: \"$WP_INSTALL_DIR\""
        rm -rf "$WP_INSTALL_DIR"
    fi
    mkdir "$WP_INSTALL_DIR"
    cd "$WP_INSTALL_DIR"
    export WP_INSTALL_DIR=$(pwd)
    curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
    chmod +x ./wp-cli.phar
    export WPCLI="$WP_INSTALL_DIR/wp-cli.phar"
    if [ "root" == "$USER" ]; then
        echo WARNING! Execution as root. Adding "--allow-root" to wp-cli requests...
        export WPCLI="$WPCLI --allow-root"
    fi
}

installWordpress () {
    installWPCLI
    cd "$WP_INSTALL_DIR"
    $WPCLI core download
    $WPCLI config create --dbname=$WP_DB_NAME --dbuser=$WP_DB_USER --dbpass=$WP_DB_PASS --dbprefix=$WP_DB_TABLE_PREFIX
    $WPCLI core install --url=$WP_INSTALLATION_DOMAIN --title=Test --admin_user=wp --admin_password=wp --admin_email=test@wp.org --skip-email
    $WPCLI core multisite-convert
    echo Creating sites...
    # List of created sites is separated by ',' char
    # Definition of each site has 3 fields: Site title, Smartling locale, Site slug all fields are separated by ':' char
    export SITES="Spanish Site:es:es,French Site:fr-FR:fr,Russian Site:ru-RU:ru,Ukrainian Site:uk-UA:ua"
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
}

runTests () {
    cd $WP_INSTALL_DIR/wp-content/plugins/smartling-connector/inc/third-party/bin
    PHPUNIT_BIN="$(pwd)/phpunit"
    chmod +x $PHPUNIT_BIN
    PHPUNIT_XML_INTEGRATION="$WP_INSTALL_DIR/wp-content/plugins/smartling-connector/tests/integration.xml"
    PHPUNIT_XML_UNIT="$WP_INSTALL_DIR/wp-content/plugins/smartling-connector/tests/phpunit.xml"
    $PHPUNIT_BIN -c $PHPUNIT_XML_INTEGRATION
    $PHPUNIT_BIN -c $PHPUNIT_XML_UNIT
}

cleanDatabase () {
    $WPCLI db reset --yes
}

installSmartlingConnector () {
    if [ -f "$PLUGIN_DEV_DIR/smartling-connector.zip" ]; then
        rm -rf "$PLUGIN_DEV_DIR/smartling-connector.zip"
    fi
    if [ -d "$WP_INSTALL_DIR/wp-content/plugins/smartling-connector" ]; then
        rm -rf "$WP_INSTALL_DIR/wp-content/plugins/smartling-connector"
    fi
    createBuild
    $WPCLI plugin install "$PLUGIN_DEV_DIR/smartling-connector.zip" --activate-network --path="$WP_INSTALL_DIR"
    $WPCLI cron event run wp_version_check --path="$WP_INSTALL_DIR"
}


installWordpress
installSmartlingConnector
runTests
mv "$WP_INSTALL_DIR/wp-content/plugins/smartling-connector/tests/log-integration.xml" "$PLUGIN_DEV_DIR/tests"
mv "$WP_INSTALL_DIR/wp-content/plugins/smartling-connector/tests/log-unit-tests.xml" "$PLUGIN_DEV_DIR/tests"
mv "$WP_INSTALL_DIR/wp-content/plugins/smartling-connector/tests/unit-coverage.xml" "$PLUGIN_DEV_DIR/tests"
mv "$WP_INSTALL_DIR/wp-content/plugins/smartling-connector/tests/integration-coverage.xml" "$PLUGIN_DEV_DIR/tests"
cleanDatabase