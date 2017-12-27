#!/bin/bash
####################
CUR_DIR=$(pwd)
BASEDIR=$(dirname $0)
cd $BASEDIR
BASEDIR=$(pwd)
BUILD_FILENAME="smartling-connector.zip"
TMP_BUILD_DIR=/tmp
SMARTLING_BUILD_DIR=$TMP_BUILD_DIR/smartling-builds
rm -rf $BASEDIR/$BUILD_FILENAME
rm -rf $SMARTLING_BUILD_DIR
mkdir $SMARTLING_BUILD_DIR
cp -r $BASEDIR/* $SMARTLING_BUILD_DIR
cd $SMARTLING_BUILD_DIR
rm -f ./*.zip
rm -f ./*.log
rm -Rf ./logs/logfile*
rm -rf ./tests/IntegrationTests/src/*
zip -9 ./$BUILD_FILENAME -r ./*
echo "#$BASEDIR#"
mv ./$BUILD_FILENAME $BASEDIR/
cd $CUR_DIR
rm -rf $SMARTLING_BUILD_DIR