#!/usr/bin/env bash
cd trunk
ls -la
svn --version
cp ../readme.txt ../smartling-connector.php .
cp -r ../css ./css
cp -r ../js ./js
cp -r ../languages ./languages
cp -r ../inc/config ./inc
cp -r ../inc/Smartling ./inc
svn add --force * --auto-props --parents --depth infinity -q
TAG=`grep '* Version' smartling-connector.php | sed 's/ \\* Version: *//'`
echo $TAG
#svn commit -m 'Update to v $TAG' --username smartling --password $WORDPRESS_ORG_SVN_PASSWORD
#svn copy https://plugins.svn.wordpress.org/smartling-connector/trunk https://plugins.svn.wordpress.org/smartling-connector/tags/\$TAG -m 'Tagging new version $TAG'
