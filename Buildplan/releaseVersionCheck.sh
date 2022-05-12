#!/usr/bin/env bash
cd trunk
CONNECTOR_VERSION=`grep '* Version' smartling-connector.php | sed 's/ \\* Version: *//'`
COMPOSER_VERSION=`grep '"version"' composer.json | sed 's/  "version": "(\d+\.\d+\.\d+)"*/\1/'`
STABLE_TAG=`grep 'Stable tag:' readme.txt | sed 's/Stable tag: //'`
echo $CONNECTOR_VERSION
echo $COMPOSER_VERSION
echo $STABLE_TAG
