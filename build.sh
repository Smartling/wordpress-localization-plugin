#!/bin/bash
####################
CUR_DIR=$(pwd)
BASEDIR=$(dirname $0)
cd $BASEDIR
BASEDIR=$(pwd)

BUILD_FILENAME="smartling-connector.zip"

TMP_BUILD_DIR=/tmp
SMARTLING_BUILD_DIR=$TMP_BUILD_DIR/smartling-builds

#clean composer cache
rm -rf ~/.composer/cache

# remove old build
rm -rf $BASEDIR/$BUILD_FILENAME

# create temporary build directory
rm -rf $SMARTLING_BUILD_DIR
mkdir $SMARTLING_BUILD_DIR

$BASEDIR/composer update --no-dev

cp -r $BASEDIR/* $SMARTLING_BUILD_DIR
# remove dev dependencies

$BASEDIR/composer update

cd $SMARTLING_BUILD_DIR

cd ./inc/third-party/

# cleanup from tests
find . -name "tests" -type d|xargs rm -Rf
find . -name "Tests" -type d|xargs rm -Rf
find . -name "docs" -type d|xargs rm -Rf
find . -name "phpunit.xml*" -type f|xargs rm -Rf
find . -name "composer.phar" -type f|xargs rm -Rf
find . -name "*.md" -type f|xargs rm -Rf
find . -name "*travis*" -type f|xargs rm -Rf

cd ./../../

rm -f ./*.zip
rm -f ./*.log
rm -f ./composer*
rm -Rf ./*.sh
rm -Rf ./*.sql
rm -Rf ./phpunit*
rm -Rf ./tests*
rm -Rf ./upload
rm -Rf ./logs/logfile*
rm -Rf ./*.pid
rm -Rf ./nginx*

zip -9 ./$BUILD_FILENAME -r ./*

echo "#$BASEDIR#"

mv ./$BUILD_FILENAME $BASEDIR/

cd $CUR_DIR

rm -rf $SMARTLING_BUILD_DIR
