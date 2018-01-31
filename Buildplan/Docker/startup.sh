#!/bin/bash

export MYSQL_HOST="localhost"
export MYSQL_USER="root"
export MYSQL_PASS="root"
export MYSQL_BASE="wp"

export USER=$(whoami)

TEST_RESULTS_DIRECTORY="/var/www/html/test_results"
SONAR_SOURCES_DIRECTORY="/var/www/html/sonar_sources"

BUILD_PATH="/var/www/testpath"
GITHUB_REPOSITORY="Smartling/wordpress-localization-plugin"

chown -R mysql:mysql /var/lib/mysql
service mysql start

echo "Create database $MYSQL_BASE;" > /tmp/script.sql
mysql -u$MYSQL_USER -p$MYSQL_PASS < /tmp/script.sql

if [ -d "$BUILD_PATH" ]; then
  rm -rf "$BUILD_PATH"
fi
mkdir -p "$BUILD_PATH";


git clone https://github.com/$GITHUB_REPOSITORY.git "$BUILD_PATH"
TEST_DIR="$BUILD_PATH/tests"
cd $TEST_DIR
sudo /bin/bash ./runIntegrationTests.sh --oauth="$GITHUB_OAUTH" --db-host="$MYSQL_HOST" --db-user="$MYSQL_USER" --db-pass="$MYSQL_PASS" --db-name="$MYSQL_BASE" --project-id="$PROJECT_ID" --user-ident="$USER_IDENTIFIER" --token-secret="$TOKEN_SECRET"

mv -f "$TEST_DIR/log-integration.xml" ${TEST_RESULTS_DIRECTORY}
mv -f "$TEST_DIR/log-unit-tests.xml" ${TEST_RESULTS_DIRECTORY}

git clone https://github.com/$GITHUB_REPOSITORY.git ${SONAR_SOURCES_DIRECTORY}
