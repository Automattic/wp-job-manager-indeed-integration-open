#!/bin/bash

# From Jetpack package

# If this is an NPM environment test we don't need a developer WordPress checkout

if [ "$WP_TRAVISCI" != "phpunit" ]; then
	exit 0;
fi

# This prepares a developer checkout of WordPress for running the test suite on Travis

mysql -u root -e "CREATE DATABASE wordpress_tests;"

CURRENT_DIR=$(pwd)

for WPJM_SLUG in 'master' 'latest' 'previous'; do
	echo "Preparing $WPJM_SLUG WPJM...";

	cd $CURRENT_DIR/..

    rm -rf "/tmp/wpjm-$WPJM_SLUG"
	case $WPJM_SLUG in
	master)
		git clone --depth=1 --branch master https://github.com/Automattic/WP-Job-Manager.git /tmp/wpjm-master
		;;
	latest)
	    echo "Version: $(php ./$PLUGIN_BASE_DIR/tests/get-wpjm-version.php)"
		git clone --depth=1 --branch `php ./$PLUGIN_BASE_DIR/tests/get-wpjm-version.php` https://github.com/Automattic/WP-Job-Manager.git /tmp/wpjm-latest
		;;
	previous)
		git clone --depth=1 --branch `php ./$PLUGIN_BASE_DIR/tests/get-wpjm-version.php --previous` https://github.com/Automattic/WP-Job-Manager.git /tmp/wpjm-previous
		;;
	esac
done

for WP_SLUG in 'master' 'latest' 'previous'; do
	echo "Preparing $WP_SLUG WordPress...";

	cd $CURRENT_DIR/..

    rm -rf "/tmp/wordpress-$WP_SLUG"
	case $WP_SLUG in
	master)
		git clone --depth=1 --branch master git://develop.git.wordpress.org/ /tmp/wordpress-master
		;;
	latest)
		git clone --depth=1 --branch `php ./$PLUGIN_BASE_DIR/tests/get-wp-version.php` git://develop.git.wordpress.org/ /tmp/wordpress-latest
		;;
	previous)
		git clone --depth=1 --branch `php ./$PLUGIN_BASE_DIR/tests/get-wp-version.php --previous` git://develop.git.wordpress.org/ /tmp/wordpress-previous
		;;
	esac

	cp -r $PLUGIN_BASE_DIR "/tmp/wordpress-$WP_SLUG/src/wp-content/plugins/$PLUGIN_SLUG"
	cd /tmp/wordpress-$WP_SLUG

	cp wp-tests-config-sample.php wp-tests-config.php
	sed -i "s/youremptytestdbnamehere/wordpress_tests/" wp-tests-config.php
	sed -i "s/yourusernamehere/root/" wp-tests-config.php
	sed -i "s/yourpasswordhere//" wp-tests-config.php

	echo "Done!";
done

exit 0;
