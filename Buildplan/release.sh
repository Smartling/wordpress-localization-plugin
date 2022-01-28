#!/usr/bin/env bash
cd trunk
TAG=`grep '* Version' smartling-connector.php | sed 's/ \\* Version: *//'`
svn commit -m "Update to v $TAG" --username smartling --password $WORDPRESS_ORG_SVN_PASSWORD
svn copy https://plugins.svn.wordpress.org/smartling-connector/trunk https://plugins.svn.wordpress.org/smartling-connector/tags/$TAG -m "Tagging new version $TAG" --username smartling --password $WORDPRESS_ORG_SVN_PASSWORD
