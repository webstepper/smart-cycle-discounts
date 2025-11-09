<?php
/**
 * PHPUnit bootstrap file for Smart Cycle Discounts plugin tests.
 *
 * @package Smart_Cycle_Discounts
 */

// Composer autoloader (if using Composer for PHPUnit).
if ( file_exists( dirname( __DIR__ ) . '/vendor/autoload.php' ) ) {
	require_once dirname( __DIR__ ) . '/vendor/autoload.php';
}

// WordPress tests library directory.
// You can set WP_TESTS_DIR environment variable or adjust the path below.
$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

// Forward compatible PHPUnit Polyfills (for PHPUnit 5.x-9.x compatibility).
if ( file_exists( dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php' ) ) {
	require_once dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find WordPress tests library at: {$_tests_dir}\n";
	echo "Please install WordPress tests or set WP_TESTS_DIR environment variable.\n";
	echo "See: https://make.wordpress.org/core/handbook/testing/automated-testing/phpunit/\n";
	exit( 1 );
}

// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
	require dirname( __DIR__ ) . '/smart-cycle-discounts.php';
}

// Load the plugin before loading WordPress test framework.
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';

// Load plugin test utilities if needed.
if ( file_exists( __DIR__ . '/utilities/class-test-helpers.php' ) ) {
	require_once __DIR__ . '/utilities/class-test-helpers.php';
}
