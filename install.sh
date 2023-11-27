#!/bin/bash

BASEDIR=$(dirname $0)

RUNDATE=$(date +"wp-autoinstall-%Y%m%d%H%M%S")

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
    echo -e "Usage:\n\t$0 --site-url=<url> --database-host=<host> --database-user=<user> --database-pass=<pass> --database-name=<name> --site-title=<title> --site-user=<login> --site-pass=<password>"
    echo -e "\n\nWhere:"
    echo -e "\t--site-url\t- The Wordpress URL. Note it is quite hard to change it after installation! E.g.: mysite.com or localhost"
    echo -e "\t--database-host\t- The hostname/ip of MySQL server. Optional. Default:localhost"
    echo -e "\t--database-user\t- The username of MySQL server for Wordpress installation. Is used to create database"
    echo -e "\t--database-pass\t- The password for database user"
    echo -e "\t--database-name\t- The Wordpress database name. Note: Script will try to delete it and create."
    echo -e "\t--site-title\t- The title of the installed Wordpress main blog. Due to installer limitations should be ONE WORD. Optional. Default:Demosite"
    echo -e "\t--site-user\t- The wordpress admin login. Optional. Default:wp"
    echo -e "\t--site-pass\t- The wordpress admin password. Optional. Default:wp"
    exit 1
}

# read options

set -- $(getopt -n$0 -u --longoptions="site-url: database-host: database-user: database-pass: database-name: site-title: site-user: site-pass: " "h" "$@") || usage

while [ $# -gt 0 ];do
    case "$1" in
        --site-url) SITE_URL=$2;shift;;
        --database-host) MYSQL_HOST=$2;shift;;
        --database-user) MYSQL_USER=$2;shift;;
        --database-pass) MYSQL_PASS=$2;shift;;
        --database-name) MYSQL_BASE=$2;shift;;
        --site-title) SITE_TITLE=$2;shift;;
        --site-user) SITE_USER=$2;shift;;
        --site-pass) SITE_PASS=$2;shift;;
        --)     shift;break;;
        -*)     usage;;
        *)      break;;
    esac
    shift
done

[ "x$SITE_URL" == "x" ] && { logit err "--site-url should be set"; usage; }
[ "x$MYSQL_HOST" == "x" ] && { logit warn "--database-host is not set. Using 'localhost'"; MYSQL_HOST=localhost; }
[ "x$MYSQL_USER" == "x" ] && { logit err "--database-user should be set"; usage; }
[ "x$MYSQL_PASS" == "x" ] && { logit err "--database-pass should be set"; usage; }
[ "x$MYSQL_BASE" == "x" ] && { logit warn "--database-name is not set. Using '$RUNDATE'"; MYSQL_BASE=$RUNDATE; }
[ "x$SITE_TITLE" == "x" ] && { logit warn "--site-title is not set. Using 'Demosite'"; SITE_TITLE=Demosite; }
[ "x$SITE_USER" == "x" ] && { logit warn "--site-user is not set. Using 'wp'"; SITE_USER=wp; }
[ "x$SITE_PASS" == "x" ] && { logit warn "--site-pass is not set. Using 'wp'"; SITE_PASS=wp; }

## dependencies for smartling-connector, separated by ';'
DEPENDENCIES="multilingual-press;ad-code-manager;post-customizer;wordpress-seo;facebook"
echo "Downloading wp.cli"
curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
WPCLI=./wp-cli.phar
echo "Downloading latest Wordpress distribution"
# Downloading last version of wordpress
$WPCLI core download
echo "Generating wp-config.php file"
# Generating wp-config.php file
$WPCLI core config --dbname=$MYSQL_BASE --dbuser=$MYSQL_USER --dbpass=$MYSQL_PASS
$WPCLI config set ALLOW_UNFILTERED_UPLOADS true --raw
echo "Trying to drop and create database"
SQL_RESET_DB="DROP DATABASE IF EXISTS \`$MYSQL_BASE\`;CREATE DATABASE IF NOT EXISTS \`$MYSQL_BASE\` DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;"
echo $SQL_RESET_DB > ./db-reset.sql
mysql -u$MYSQL_USER -p$MYSQL_PASS < ./db-reset.sql
rm ./db-reset.sql
echo "Setting up multisite Wordpress installation"
# newtwork installing wordpress
$WPCLI core multisite-install --url=$SITE_URL --base=/ --title=$SITE_TITLE --admin_user=$SITE_USER --admin_password=$SITE_PASS --admin_email=no@e.mail
echo "Installing plugins..."
IFS=';' read -a array <<< "$DEPENDENCIES"
for plugin_name in "${array[@]}"
do
echo "Installing plugin $plugin_name"
$WPCLI plugin install $plugin_name
echo "Activating plugin $plugin_name"
$WPCLI plugin activate $plugin_name
done
