<?php
/**
 * Lightweight PHPUnit bootstrap for Smart Cycle Discounts plugin tests.
 *
 * This bootstrap loads WordPress functions and the plugin WITHOUT requiring database.
 * It's designed for unit testing validator logic, field definitions, and other
 * non-database-dependent code.
 *
 * @package Smart_Cycle_Discounts
 */

// Composer autoloader (for PHPUnit).
if ( file_exists( dirname( __DIR__ ) . '/vendor/autoload.php' ) ) {
	require_once dirname( __DIR__ ) . '/vendor/autoload.php';
}

// Define WordPress constants that plugin expects.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/mnt/c/Users/Alienware/Local Sites/vvmdov/app/public/' );
}

if ( ! defined( 'WP_CONTENT_DIR' ) ) {
	define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
}

if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
	define( 'WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins' );
}

if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', true );
}

if ( ! defined( 'WPINC' ) ) {
	define( 'WPINC', 'wp-includes' );
}

// Plugin constants.
if ( ! defined( 'WSSCD_PLUGIN_FILE' ) ) {
	define( 'WSSCD_PLUGIN_FILE', dirname( __DIR__ ) . '/smart-cycle-discounts.php' );
}

if ( ! defined( 'WSSCD_PLUGIN_DIR' ) ) {
	define( 'WSSCD_PLUGIN_DIR', dirname( __DIR__ ) );
}

if ( ! defined( 'WSSCD_INCLUDES_DIR' ) ) {
	define( 'WSSCD_INCLUDES_DIR', WSSCD_PLUGIN_DIR . '/includes/' );
}

// Load minimal WordPress functions needed for tests.
require_once ABSPATH . 'wp-includes/class-wp-error.php';
require_once ABSPATH . 'wp-includes/functions.php';
require_once ABSPATH . 'wp-includes/plugin.php';
require_once ABSPATH . 'wp-includes/l10n.php';
require_once ABSPATH . 'wp-includes/formatting.php';

// Mock WordPress functions that aren't available.
if ( ! function_exists( 'current_time' ) ) {
	/**
	 * Mock current_time function.
	 *
	 * @param string $type Type of time to retrieve ('mysql', 'timestamp', 'U').
	 * @param int    $gmt  Whether to use GMT.
	 * @return int|string
	 */
	function current_time( $type, $gmt = 0 ) {
		switch ( $type ) {
			case 'mysql':
				return ( 0 === $gmt ) ? gmdate( 'Y-m-d H:i:s' ) : gmdate( 'Y-m-d H:i:s', time() );
			case 'timestamp':
			case 'U':
				return time();
			default:
				return time();
		}
	}
}

if ( ! function_exists( '__' ) ) {
	/**
	 * Mock translation function.
	 *
	 * @param string $text   Text to translate.
	 * @param string $domain Text domain.
	 * @return string
	 */
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	/**
	 * Mock escaped translation function.
	 *
	 * @param string $text   Text to translate.
	 * @param string $domain Text domain.
	 * @return string
	 */
	function esc_html__( $text, $domain = 'default' ) {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	/**
	 * Mock esc_html function.
	 *
	 * @param string $text Text to escape.
	 * @return string
	 */
	function esc_html( $text ) {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * Mock sanitize_text_field function.
	 *
	 * @param string $str String to sanitize.
	 * @return string
	 */
	function sanitize_text_field( $str ) {
		return strip_tags( $str );
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	/**
	 * Mock wp_unslash function.
	 *
	 * @param string|array $value Value to unslash.
	 * @return string|array
	 */
	function wp_unslash( $value ) {
		return is_array( $value ) ? array_map( 'wp_unslash', $value ) : stripslashes( $value );
	}
}

if ( ! function_exists( 'absint' ) ) {
	/**
	 * Mock absint function.
	 *
	 * @param mixed $maybeint Value to convert to absolute integer.
	 * @return int
	 */
	function absint( $maybeint ) {
		return abs( (int) $maybeint );
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	/**
	 * Mock current_user_can function (always returns true for tests).
	 *
	 * @param string $capability Capability to check.
	 * @return bool
	 */
	function current_user_can( $capability ) {
		return true; // For testing purposes
	}
}

if ( ! function_exists( 'wp_installing' ) ) {
	/**
	 * Mock wp_installing function.
	 *
	 * @return bool
	 */
	function wp_installing() {
		return false;
	}
}

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * Mock get_option function.
	 *
	 * @param string $option  Option name.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	function get_option( $option, $default = false ) {
		return $default;
	}
}

// Define time constants if not defined.
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}
if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}
if ( ! defined( 'WEEK_IN_SECONDS' ) ) {
	define( 'WEEK_IN_SECONDS', 604800 );
}
if ( ! defined( 'MONTH_IN_SECONDS' ) ) {
	define( 'MONTH_IN_SECONDS', 2592000 );
}
if ( ! defined( 'YEAR_IN_SECONDS' ) ) {
	define( 'YEAR_IN_SECONDS', 31536000 );
}

// Load plugin's autoloader.
if ( file_exists( WSSCD_INCLUDES_DIR . 'class-autoloader.php' ) ) {
	require_once WSSCD_INCLUDES_DIR . 'class-autoloader.php';
	WSSCD_Autoloader::register();
}

// Load validation classes manually if autoloader doesn't catch them.
if ( ! class_exists( 'WSSCD_Schedule_Step_Validator' ) ) {
	require_once WSSCD_INCLUDES_DIR . 'core/validation/step-validators/class-schedule-step-validator.php';
}

if ( ! class_exists( 'WSSCD_Validation_Rules' ) ) {
	require_once WSSCD_INCLUDES_DIR . 'core/validation/class-validation-rules.php';
}

if ( ! class_exists( 'WSSCD_Field_Definitions' ) ) {
	require_once WSSCD_INCLUDES_DIR . 'core/validation/class-field-definitions.php';
}

echo "Lightweight WordPress bootstrap loaded successfully.\n";
echo "Testing plugin validators without database dependency.\n";
