#!/bin/bash

BASEDIR=$(dirname $0)

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
    echo -e "\t<url>\t\t- The final of Wordpress URL. Note it is quite hard to change it after installation! E.g.: mysite.com"
    echo -e "\t<host>\t\t- The hostname/ip of MySQL server"
    echo -e "\t<user>\t\t- The username of MySQL server for Wordpress installation. Is used to create database"
    echo -e "\t<pass>\t\t- The password for database user"
    echo -e "\t<name>\t\t- The Wordpress database name. Note: Script will try to delete it and create."
    echo -e "\t<title>\t\t- The title of the installed Wordpress main blog. Due to installer limitations should be ONE WORD."
    echo -e "\t<login>\t\t- The wordpress admin login."
    echo -e "\t<password>\t- The wordpress admin password."
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
[ "x$MYSQL_HOST" == "x" ] && { logit err "--database-host should be set"; usage; }
[ "x$MYSQL_USER" == "x" ] && { logit err "--database-user should be set"; usage; }
[ "x$MYSQL_PASS" == "x" ] && { logit err "--database-pass should be set"; usage; }
[ "x$MYSQL_BASE" == "x" ] && { logit err "--database-name should be set"; usage; }
[ "x$SITE_TITLE" == "x" ] && { logit err "--site-title should be set"; usage; }
[ "x$SITE_USER" == "x" ] && { logit err "--site-user should be set"; usage; }
[ "x$SITE_PASS" == "x" ] && { logit err "--site-pass should be set"; usage; }

## dependencies for smartling-connector, separated by ';'
DEPENDENCIES=multilingual-press;ad-code-manager;post-customizer;wordpress-seo;facebook

curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
WPCLI=./wp-cli.phar

# Downloading last version of wordpress
$WPCLI core download

# Generating wp-config.php file
$WPCLI core config --dbname=$MYSQL_BASE --dbuser=$MYSQL_USER --dbpass=$MYSQL_PASS

SQL_RESET_DB="DROP DATABASE IF EXISTS \`$MYSQL_BASE\`;CREATE DATABASE IF NOT EXISTS \`$MYSQL_BASE\` DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;"

echo $SQL_RESET_DB > ./db-reset.sql

mysql -u$MYSQL_USER -p$MYSQL_PASS < ./db-reset.sql

rm ./db-reset.sql

# newtwork installing wordpress
$WPCLI core multisite-install --url=$SITE_URL --base=/ --title=$SITE_TITLE --admin_user=$SITE_USER --admin_password=$SITE_PASS --admin_email=no@e.mail

dep_arr=$(echo $DEPENDENCIES | tr ";" "\n")

for dep in $dep_arr
do
$WPCLI plugin install $dep
$WPCLI plugin activate $dep
done
