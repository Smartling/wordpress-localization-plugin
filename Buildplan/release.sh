#!/usr/bin/env bash
svn -q checkout https://plugins.svn.wordpress.org/smartling-connector/trunk trunk
cd trunk
ls -la
cp ../readme.txt ../smartling-connector.php .
cp -r ../css .
cp -r ../inc/config ./inc
cp -r ../inc/Smartling ./inc
cp -r ../js .
cp -r ../languages .
svn add --force * --auto-props --parents --depth infinity -q
TAG=`grep '* Version' smartling-connector.php | sed 's/ \\* Version: *//'`
svn status
echo "About to commit $TAG in a minute!"
sleep 1m
svn commit -m 'Update to v $TAG' --username smartling --password $WORDPRESS_ORG_SVN_PASSWORD
svn copy https://plugins.svn.wordpress.org/smartling-connector/trunk https://plugins.svn.wordpress.org/smartling-connector/tags/$TAG -m 'Tagging new version $TAG'
