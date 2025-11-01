<?php
/**
 * Temporary Freemius Cleanup Script
 *
 * This script clears old Freemius account data to allow reconnection
 * with new account credentials.
 *
 * USAGE:
 * 1. Access this file via: http://vvmdov.local/wp-content/plugins/smart-cycle-discounts/freemius-cleanup.php
 * 2. After running, DELETE THIS FILE for security
 *
 * @package SmartCycleDiscounts
 */

// Load WordPress
require_once dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) . '/wp-load.php';

// Security check - only allow in development mode
if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
	wp_die( 'This script can only run in development mode (WP_DEBUG must be true)' );
}

// Security check - only allow administrators
if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( 'You must be an administrator to run this script' );
}

echo '<html><head><title>Freemius Cleanup</title></head><body>';
echo '<h1>Freemius Account Cleanup</h1>';
echo '<p>This will disconnect Smart Cycle Discounts from the old Freemius account.</p>';

// Get confirmation
if ( ! isset( $_GET['confirm'] ) || 'yes' !== $_GET['confirm'] ) {
	echo '<p><strong>Are you sure?</strong></p>';
	echo '<p><a href="?confirm=yes" style="background:#0073aa;color:#fff;padding:10px 20px;text-decoration:none;border-radius:3px;">Yes, Clear Freemius Data</a></p>';
	echo '<p><a href="' . admin_url() . '">Cancel & Go Back</a></p>';
	echo '</body></html>';
	exit;
}

echo '<h2>Clearing Freemius Options...</h2>';
echo '<ul>';

global $wpdb;

// Get all Freemius options
$fs_options = $wpdb->get_results(
	"SELECT option_name FROM {$wpdb->options}
	WHERE option_name LIKE '%fs_%'
	OR option_name LIKE '%freemius%'",
	ARRAY_A
);

$deleted_count = 0;

// Delete each option
foreach ( $fs_options as $option ) {
	$option_name = $option['option_name'];

	if ( delete_option( $option_name ) ) {
		echo '<li>✅ Deleted: ' . esc_html( $option_name ) . '</li>';
		$deleted_count++;
	} else {
		echo '<li>⚠️ Could not delete: ' . esc_html( $option_name ) . '</li>';
	}
}

echo '</ul>';

if ( $deleted_count > 0 ) {
	echo '<h2>✅ Success!</h2>';
	echo '<p><strong>' . $deleted_count . '</strong> Freemius options deleted.</p>';
	echo '<h3>Next Steps:</h3>';
	echo '<ol>';
	echo '<li><strong>DELETE THIS FILE</strong> (freemius-cleanup.php) for security</li>';
	echo '<li>Go to <a href="' . admin_url( 'plugins.php' ) . '">Plugins page</a></li>';
	echo '<li>Deactivate Smart Cycle Discounts</li>';
	echo '<li>Reactivate Smart Cycle Discounts</li>';
	echo '<li>You should see Freemius opt-in screen with NEW account (vvmdov@gmail.com)</li>';
	echo '</ol>';
} else {
	echo '<h2>⚠️ No Data Found</h2>';
	echo '<p>No Freemius options were found in the database.</p>';
	echo '<p>The plugin may already be disconnected.</p>';
}

echo '<hr>';
echo '<p><a href="' . admin_url() . '" style="background:#0073aa;color:#fff;padding:10px 20px;text-decoration:none;border-radius:3px;">Go to WordPress Admin</a></p>';
echo '</body></html>';
