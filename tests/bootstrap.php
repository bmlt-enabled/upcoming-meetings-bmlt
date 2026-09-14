<?php
/**
 * PHPUnit bootstrap file for Upcoming Meetings BMLT plugin tests.
 *
 * Uses wp-phpunit/wp-phpunit (installed via Composer) as the test library.
 * WordPress core must be downloaded to WP_CORE_DIR (default: /tmp/wordpress).
 */

// Point wp-phpunit at our config file.
putenv( 'WP_PHPUNIT__TESTS_CONFIG=' . __DIR__ . '/wp-tests-config.php' );

$um_tests_dir = dirname( __DIR__ ) . '/vendor/wp-phpunit/wp-phpunit';

if ( ! file_exists( "{$um_tests_dir}/includes/functions.php" ) ) {
	echo 'Could not find wp-phpunit. Run: composer install' . PHP_EOL;
	exit( 1 );
}

require_once "{$um_tests_dir}/includes/functions.php";

/**
 * Load the plugin being tested.
 */
function upcoming_meetings_tests_manually_load_plugin() {
	require dirname( __DIR__ ) . '/upcoming-meetings.php';
}
tests_add_filter( 'muplugins_loaded', 'upcoming_meetings_tests_manually_load_plugin' );

require "{$um_tests_dir}/includes/bootstrap.php";
