#!/bin/bash

export MYSQL_HOST="localhost"
export MYSQL_USER="root"
export MYSQL_PASS="root"
export MYSQL_BASE="wp"

export USER=$(whoami)

chown -R mysql:mysql /var/lib/mysql
service mysql start

echo "Create database $MYSQL_BASE;" > /tmp/script.sql
mysql -u$MYSQL_USER -p$MYSQL_PASS < /tmp/script.sql

cd tests
sudo /bin/bash ./runIntegrationTests.sh --oauth="$GITHUB_OAUTH" --db-host="$MYSQL_HOST" --db-user="$MYSQL_USER" --db-pass="$MYSQL_PASS" --db-name="$MYSQL_BASE" --project-id="$PROJECT_ID" --user-ident="$USER_IDENTIFIER" --token-secret="$TOKEN_SECRET"
