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

// Define test mode constant before anything else loads.
// This tells the plugin to skip certain checks during testing.
if ( ! defined( 'SCD_TESTING' ) ) {
	define( 'SCD_TESTING', true );
}

// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

/**
 * Mock WooCommerce for testing.
 * The plugin requires WooCommerce to be active and loaded.
 */
function _mock_woocommerce() {
	// Create minimal WooCommerce class to satisfy class_exists() checks.
	if ( ! class_exists( 'WooCommerce' ) ) {
		class WooCommerce {
			public $version = '8.0.0';

			public function __construct() {
				// Define WC constants that might be checked.
				if ( ! defined( 'WC_VERSION' ) ) {
					define( 'WC_VERSION', '8.0.0' );
				}
			}
		}

		// Initialize WooCommerce instance.
		$GLOBALS['woocommerce'] = new WooCommerce();
	}

	// Mock WooCommerce functions that the plugin uses.
	if ( ! function_exists( 'get_woocommerce_currency' ) ) {
		function get_woocommerce_currency() {
			return 'USD';
		}
	}

	if ( ! function_exists( 'wc_price' ) ) {
		function wc_price( $price, $args = array() ) {
			return '$' . number_format( $price, 2 );
		}
	}

	if ( ! function_exists( 'get_woocommerce_currency_symbol' ) ) {
		function get_woocommerce_currency_symbol( $currency = '' ) {
			return '$';
		}
	}

	if ( ! function_exists( 'wc_get_price_decimal_separator' ) ) {
		function wc_get_price_decimal_separator() {
			return '.';
		}
	}

	if ( ! function_exists( 'wc_get_price_thousand_separator' ) ) {
		function wc_get_price_thousand_separator() {
			return ',';
		}
	}

	if ( ! function_exists( 'wc_get_price_decimals' ) ) {
		function wc_get_price_decimals() {
			return 2;
		}
	}

	if ( ! function_exists( 'get_woocommerce_price_format' ) ) {
		function get_woocommerce_price_format() {
			return '%1$s%2$s'; // Symbol before price (e.g., $10.00)
		}
	}

	if ( ! function_exists( 'wc_format_decimal' ) ) {
		function wc_format_decimal( $number, $decimals = '' ) {
			$decimals = $decimals === '' ? wc_get_price_decimals() : absint( $decimals );
			return number_format( (float) $number, $decimals, '.', '' );
		}
	}

	if ( ! function_exists( 'wc_get_product' ) ) {
		function wc_get_product( $product_id = false ) {
			// Return false for test environment - tests should mock products
			return false;
		}
	}

	if ( ! function_exists( 'wc_format_sale_price' ) ) {
		function wc_format_sale_price( $from, $to ) {
			return '<del>' . wc_price( $from ) . '</del> <ins>' . wc_price( $to ) . '</ins>';
		}
	}

	// Mock WooCommerce conditional tags.
	if ( ! function_exists( 'is_shop' ) ) {
		function is_shop() {
			return false;
		}
	}

	if ( ! function_exists( 'is_product' ) ) {
		function is_product() {
			return false;
		}
	}

	if ( ! function_exists( 'is_cart' ) ) {
		function is_cart() {
			return false;
		}
	}

	if ( ! function_exists( 'is_checkout' ) ) {
		function is_checkout() {
			return false;
		}
	}

	if ( ! function_exists( 'is_account_page' ) ) {
		function is_account_page() {
			return false;
		}
	}
}

// Create WooCommerce mock immediately, before WordPress loads.
_mock_woocommerce();

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
	// WooCommerce mock is already loaded above.

	// Load Container class BEFORE plugin loads (it needs this class)
	if ( ! class_exists( 'SCD_Container' ) ) {
		require_once dirname( __DIR__ ) . '/includes/bootstrap/class-container.php';
	}

	// Now load the plugin.
	require dirname( __DIR__ ) . '/smart-cycle-discounts.php';
}

// Load the plugin before loading WordPress test framework.
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';

// Activate the plugin after WordPress loads.
// This creates database tables and runs activation hooks.
if ( function_exists( 'scd_activate_plugin' ) ) {
	scd_activate_plugin();
}

// Initialize the plugin manually (normally happens on plugins_loaded hook).
// This loads all plugin classes and makes them available for tests.
if ( function_exists( 'scd_init_plugin' ) ) {
	scd_init_plugin();
}

// Load step validator classes explicitly for tests (autoloader may not catch them)
$validator_dir = dirname( __DIR__ ) . '/includes/core/validation/step-validators';
foreach ( glob( $validator_dir . '/class-*.php' ) as $validator_file ) {
	require_once $validator_file;
}

// Load plugin test utilities if needed.
if ( file_exists( __DIR__ . '/utilities/class-test-helpers.php' ) ) {
	require_once __DIR__ . '/utilities/class-test-helpers.php';
}
