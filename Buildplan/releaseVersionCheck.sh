#!/usr/bin/env bash
CONNECTOR_VERSION=`grep '* Version' smartling-connector.php | sed 's/ \\* Version: *//'`
COMPOSER_VERSION=`grep '"version"' composer.json | sed 's/  "version": "\(.\+\)",/\1/'`
STABLE_TAG=`grep 'Stable tag:' readme.txt | sed 's/Stable tag: //'`
if [ "$CONNECTOR_VERSION" != "$COMPOSER_VERSION" ] || [ "$COMPOSER_VERSION" != "$STABLE_TAG" ]; then
  echo "Version mismatch: connectorVersion=$CONNECTOR_VERSION, stableTag=$STABLE_TAG, composerVersion=$COMPOSER_VERSION"
  exit 1;
fi
