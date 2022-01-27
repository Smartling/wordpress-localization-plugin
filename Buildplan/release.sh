#!/usr/bin/env bash
cd trunk
ls -la
TAG=`grep '* Version' smartling-connector.php | sed 's/ \\* Version: *//'`

echo "About to commit $TAG with these changes in a minute!"
#sleep 1m
#svn commit -m "Update to v $TAG" --username smartling --password $WORDPRESS_ORG_SVN_PASSWORD
#svn copy https://plugins.svn.wordpress.org/smartling-connector/trunk https://plugins.svn.wordpress.org/smartling-connector/tags/$TAG -m "Tagging new version $TAG" --username smartling --password $WORDPRESS_ORG_SVN_PASSWORD
