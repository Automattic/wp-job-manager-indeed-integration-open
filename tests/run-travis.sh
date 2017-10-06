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
    run_phpunit_for "latest" "master"
    run_phpunit_for "latest" "latest"
    run_phpunit_for "latest" "previous"

    run_phpunit_for "master" "latest"
    run_phpunit_for "previous" "latest"
else

    gem install less
    rm -rf ~/.yarn
    curl -o- -L https://yarnpkg.com/install.sh | bash -s -- --version 0.20.3
    yarn

    if $WP_TRAVISCI; then
	# Everything is fine
	:
    else
        exit 1
    fi
fi

exit 0
