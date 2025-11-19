<?php
/**
 * License Functions
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/licensing/license-functions.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
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
function scd_is_license_valid() {
	$license_manager = SCD_License_Manager::instance();
	return $license_manager->is_license_valid();
}

/**
 * Get license information.
 *
 * @since    1.0.0
 * @return   array|null    License info or null if not available.
 */
function scd_get_license_info() {
	$license_manager = SCD_License_Manager::instance();
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
function scd_force_license_validation() {
	$license_manager = SCD_License_Manager::instance();
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
function scd_clear_license_cache() {
	$license_manager = SCD_License_Manager::instance();
	$license_manager->clear_validation_cache();
}

/**
 * Get days until next license check.
 *
 * @since    1.0.0
 * @return   int    Days remaining until next check.
 */
function scd_get_days_until_license_check() {
	$license_manager = SCD_License_Manager::instance();
	return $license_manager->get_days_until_next_check();
}

/**
 * Check if user is a free user (never purchased).
 *
 * @since    1.0.0
 * @return   bool    True if user has never purchased premium.
 */
function scd_is_free_user() {
	$license_manager = SCD_License_Manager::instance();
	return $license_manager->is_free_user();
}

/**
 * Check if user is a pro user with expired/invalid license.
 *
 * @since    1.0.0
 * @return   bool    True if user purchased but license is now invalid.
 */
function scd_is_license_expired() {
	$license_manager = SCD_License_Manager::instance();
	return $license_manager->is_license_expired();
}

/**
 * Get upgrade URL.
 *
 * @since    1.0.0
 * @return   string    Upgrade URL.
 */
if ( ! function_exists( 'scd_get_upgrade_url' ) ) {
	function scd_get_upgrade_url() {
		if ( ! function_exists( 'scd_fs' ) || ! scd_fs() ) {
			return admin_url( 'admin.php?page=smart-cycle-discounts-pricing' );
		}

		return scd_fs()->get_upgrade_url();
	}
}

/**
 * Get trial URL.
 *
 * @since    1.0.0
 * @return   string    Trial URL.
 */
if ( ! function_exists( 'scd_get_trial_url' ) ) {
	function scd_get_trial_url() {
		if ( ! function_exists( 'scd_fs' ) || ! scd_fs() ) {
			return admin_url( 'admin.php?page=smart-cycle-discounts-pricing' );
		}

		return scd_fs()->get_trial_url();
	}
}
