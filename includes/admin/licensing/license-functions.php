<?php
/**
 * License Functions
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/licensing/license-functions.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress/plugins/smart-cycle-discounts/
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Check if license is currently valid.
 *
 * This performs server-side validation with Freemius API.
 * Results are cached for offline tolerance.
 *
 * @since    1.0.0
 * @return   bool    True if license is valid and active.
 */
function wsscd_is_license_valid() {
	if ( ! class_exists( 'WSSCD_License_Manager' ) ) {
		return false;
	}
	$license_manager = WSSCD_License_Manager::instance();
	return $license_manager->is_license_valid();
}

/**
 * Get license information.
 *
 * @since    1.0.0
 * @return   array|null    License info or null if not available.
 */
function wsscd_get_license_info() {
	if ( ! class_exists( 'WSSCD_License_Manager' ) ) {
		return null;
	}
	$license_manager = WSSCD_License_Manager::instance();
	return $license_manager->get_license_info();
}

/**
 * Force immediate license validation.
 *
 * Bypasses cache and checks directly with Freemius API.
 * Useful after license changes or for admin actions.
 *
 * @since    1.0.0
 * @return   bool    Validation result.
 */
function wsscd_force_license_validation() {
	if ( ! class_exists( 'WSSCD_License_Manager' ) ) {
		return false;
	}
	$license_manager = WSSCD_License_Manager::instance();
	return $license_manager->force_validation();
}

/**
 * Clear license validation cache.
 *
 * Called when license status changes (activation, upgrade, etc.).
 *
 * @since    1.0.0
 * @return   void
 */
function wsscd_clear_license_cache() {
	if ( ! class_exists( 'WSSCD_License_Manager' ) ) {
		return;
	}
	$license_manager = WSSCD_License_Manager::instance();
	$license_manager->clear_validation_cache();
}

/**
 * Get days until next license check.
 *
 * @since    1.0.0
 * @return   int    Days remaining until next check.
 */
function wsscd_get_days_until_license_check() {
	if ( ! class_exists( 'WSSCD_License_Manager' ) ) {
		return 0;
	}
	$license_manager = WSSCD_License_Manager::instance();
	return $license_manager->get_days_until_next_check();
}

/**
 * Check if user is a free user (never purchased).
 *
 * @since    1.0.0
 * @return   bool    True if user has never purchased premium.
 */
function wsscd_is_free_user() {
	if ( ! class_exists( 'WSSCD_License_Manager' ) ) {
		return true;
	}
	$license_manager = WSSCD_License_Manager::instance();
	return $license_manager->is_free_user();
}

/**
 * Check if user is a pro user with expired/invalid license.
 *
 * @since    1.0.0
 * @return   bool    True if user purchased but license is now invalid.
 */
function wsscd_is_license_expired() {
	if ( ! class_exists( 'WSSCD_License_Manager' ) ) {
		return false;
	}
	$license_manager = WSSCD_License_Manager::instance();
	return $license_manager->is_license_expired();
}

// Note: wsscd_get_upgrade_url() is defined in smart-cycle-discounts.php
// to ensure it's available early in the plugin lifecycle.
