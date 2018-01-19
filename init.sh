#!/usr/bin/env bash
###############################################################################
#                                                                             #
#                                                                             #
#                                                                             #
#                                                                             #
#                                                                             #
###############################################################################

# Understanding pathes
export CUR_DIR=$(pwd)
SCRIPT_DIR=$(dirname $0)
cd $SCRIPT_DIR
export SCRIPT_DIR=$(pwd)
cd "$SCRIPT_DIR/../../../"
export WP_DIR=$(pwd)

cd $SCRIPT_DIR

#### wget -O - https://raw.github.com/luismartingil/commands/master/101_remote2local_wireshark.sh | bash
#### curl -s http://server/path/script.sh | bash -s arg1 arg2


mkdir -p "$SCRIPT_DIR/inc/third-party/bin"

cd "$SCRIPT_DIR/inc/third-party/bin"
COMPOSER_VER="1.0.0"
COMPOSER_BIN="$(pwd)/composer"

php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php --filename="composer" --version="$COMPOSER_VER"
php -r "unlink('composer-setup.php');"

cd $SCRIPT_DIR


WPCLI_DIST="https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar"

cd $WP_DIR

echo Downloading wp-cli...
curl -O $WPCLI_DIST
chmod +x ./wp-cli.phar
WP_CLI="$WP_DIR/wp-cli.phar"

echo Downloadind Wordpress distribution...
$WP_CLI core download
echo Configuring Wordpress...
$WP_CLI core config --dbname=wp --dbuser=wp --dbpass=wp --dbhost=localhost
echo Installing Wordpress...
$WP_CLI core install --url=mlp.wp.dev.local --title="Dev installation" --admin_user=wp --admin_password=wp --admin_email=no@ema.il
echo Enabling network mode...
$WP_CLI core multisite-convert
echo Creating sites...
$WP_CLI site create --slug=es --title="Spanish site" --email=es@no.mail
$WP_CLI site create --slug=fr --title="French site" --email=fr@no.mail
$WP_CLI site create --slug=ru --title="Russian site" --email=ru@no.mail
$WP_CLI site create --slug=ua --title="Ukrainian site" --email=ua@no.mail
echo Installing plugins...
$WP_CLI plugin install multilingual-press advanced-custom-fields wordpress-seo debug cmb2 debug-bar debug-bar-extender duplicate-post edit-flow image-widget stream wordpress-importer smartling-connector
echo Activating plugins...
$WP_CLI plugin activate multilingual-press advanced-custom-fields wordpress-seo debug cmb2 debug-bar debug-bar-extender duplicate-post edit-flow image-widget stream wordpress-importer --network
