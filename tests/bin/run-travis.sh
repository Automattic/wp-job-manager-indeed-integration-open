#!/bin/bash

set -e

run_phpunit_for() {
  wp_test_branch="$1";
  wpjm_test_branch="$2";
  echo "Testing on WPJM ($wpjm_test_branch) on WordPress $wp_test_branch..."
  export WP_TESTS_DIR="/tmp/wordpress-$wp_test_branch/tests/phpunit"
  export JOB_MANAGER_PLUGIN_DIR="/tmp/wpjm-$wpjm_test_branch"
  cd "/tmp/wordpress-$wp_test_branch/src/wp-content/plugins/$PLUGIN_SLUG"

  phpunit

  if [ $? -ne 0 ]; then
    exit 1
  fi
}

if [ "$WP_TRAVISCI" == "phpunit" ]; then
	WPJM_SLUGS=('master' 'latest' 'previous')
	WP_SLUGS=('master' 'latest' 'previous')

	if [ ! -z "$WP_VERSION" ]; then
		WP_SLUGS=("$WP_VERSION")
	fi

	if [ ! -z "$WPJM_VERSION" ]; then
		WPJM_SLUGS=("$WPJM_VERSION")
	fi

	for WPJM_SLUG in "${WPJM_SLUGS[@]}"; do
		for WP_SLUG in "${WP_SLUGS[@]}"; do
			run_phpunit_for "$WP_SLUG" "$WPJM_SLUG"
		done
	done
elif [ "$WP_TRAVISCI" == "phpcs" ]; then
	composer install

	echo "Testing PHP code formatting..."

	bash ./tests/bin/phpcs.sh

	if [ $? -ne 0 ]; then
		exit 1
	fi
else

	npm install npm -g
	npm install

		if $WP_TRAVISCI; then
	# Everything is fine
	:
		else
				exit 1
		fi
fi

exit 0
