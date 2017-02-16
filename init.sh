#!/bin/sh

cd ./wp-content/plugins/smartling-connector-dev/
mkdir ./inc/third-party/
mkdir ./inc/third-party/bin/
cd ./inc/third-party/bin/

php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php --filename=composer --version=1.0.0
php -r "unlink('composer-setup.php');"


//curl -sS https://getcomposer.org/installer | php
cd ./../../../
./composer self-update
./composer update
cd ./../../../

WPCLI_DIST="https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar"

echo Downloading wp-cli...
curl -O $WPCLI_DIST
mv ./wp-cli.phar ./wp
chmod +x ./wp
echo Downloadind Wordpress distribution...
./wp core download
echo Configuring Wordpress...
./wp core config --dbname=wp --dbuser=wp --dbpass=wp --dbhost=localhost
echo Installing Wordpress...
./wp core install --url=mlp.wp.dev.local --title="Dev installation" --admin_user=wp --admin_password=wp --admin_email=no@ema.il
echo Enabling network mode...
./wp core multisite-convert
echo Creating sites...
./wp site create --slug=es --title="Spanish site" --email=es@no.mail
./wp site create --slug=fr --title="French site" --email=fr@no.mail
./wp site create --slug=ru --title="Russian site" --email=ru@no.mail
./wp site create --slug=ua --title="Ukrainian site" --email=ua@no.mail
echo Installing plugins...
./wp plugin install multilingual-press advanced-custom-fields wordpress-seo debug cmb2 debug-bar debug-bar-extender duplicate-post edit-flow image-widget stream wordpress-importer smartling-connector
echo Activating plugins...
./wp plugin activate multilingual-press advanced-custom-fields wordpress-seo debug cmb2 debug-bar debug-bar-extender duplicate-post edit-flow image-widget stream wordpress-importer --network
