<?php
/**
 * Prevent direct access
 *
 * @package SmartCycleDiscounts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Check what jQuery UI styles are registered in WordPress
 * Run this as: wp eval-file check-jquery-ui-css.php
 */

global $wp_styles;

echo "=== Registered jQuery UI Related Styles ===\n\n";

if ( isset( $wp_styles->registered ) && is_array( $wp_styles->registered ) ) {
    foreach ( $wp_styles->registered as $handle => $style ) {
        if ( stripos( $handle, 'jquery' ) !== false || stripos( $handle, 'ui' ) !== false ) {
            echo "Handle: $handle\n";
            echo "Source: " . ( isset( $style->src ) ? $style->src : 'N/A' ) . "\n";
            echo "Dependencies: " . ( isset( $style->deps ) && is_array( $style->deps ) ? implode( ', ', $style->deps ) : 'none' ) . "\n";
            echo "---\n";
        }
    }
} else {
    echo "No styles registered yet.\n";
}

echo "\n=== Checking specific handles ===\n";
$handles_to_check = array( 'wp-jquery-ui-core', 'wp-jquery-ui-dialog', 'wp-jquery-ui-datepicker', 'jquery-ui-core', 'jquery-ui' );

foreach ( $handles_to_check as $handle ) {
    if ( wp_style_is( $handle, 'registered' ) ) {
        echo "✓ $handle is registered\n";
        $style = $wp_styles->registered[ $handle ];
        echo "  Source: " . $style->src . "\n";
    } else {
        echo "✗ $handle is NOT registered\n";
    }
}
