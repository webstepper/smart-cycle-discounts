<?php
/**
 * @fs_premium_only
 *
 * License Manager Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/licensing/class-license-manager.php
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
 * License Manager Class
 *
 * Provides centralized license validation with periodic health checks.
 * Implements server-side verification to prevent local bypassing.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/licensing
 * @author     Webstepper <contact@webstepper.io>
 */
class WSSCD_License_Manager {

	/**
	 * Option key for last license check timestamp.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string
	 */
	private $last_check_option = 'wsscd_license_last_check';

	/**
	 * Option key for cached validation result.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string
	 */
	private $validation_cache_option = 'wsscd_license_validation_cache';

	/**
	 * License check interval in seconds (24 hours - industry standard).
	 *
	 * This ensures expired or revoked licenses are detected within 24 hours,
	 * preventing unauthorized extended access to Pro features.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      int
	 */
	private $check_interval = 86400; // DAY_IN_SECONDS

	/**
	 * Validation cache duration for offline tolerance (24 hours).
	 *
	 * Balances performance with security. Allows plugin to function for 24 hours
	 * even if Freemius API is temporarily unreachable.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      int
	 */
	private $cache_duration = 86400; // DAY_IN_SECONDS

	/**
	 * Grace period after license expiration (3 days).
	 *
	 * Provides users time to renew without immediate feature lockout.
	 * Applied explicitly after cache duration expires.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      int
	 */
	private $grace_period = 259200; // 3 * DAY_IN_SECONDS

	/**
	 * Singleton instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_License_Manager|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @since    1.0.0
	 * @return   WSSCD_License_Manager
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since    1.0.0
	 */
	private function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Setup WordPress hooks.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function setup_hooks() {
		// Daily cron to verify license health
		add_action( 'wsscd_license_health_check', array( $this, 'run_health_check' ) );

		// Admin init for periodic checks
		add_action( 'admin_init', array( $this, 'maybe_run_periodic_check' ) );
	}

	/**
	 * Schedule periodic license health checks.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function schedule_periodic_checks() {
		if ( ! wp_next_scheduled( 'wsscd_license_health_check' ) ) {
			wp_schedule_event( time(), 'daily', 'wsscd_license_health_check' );
		}
	}

	/**
	 * Unschedule periodic license health checks.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function unschedule_periodic_checks() {
		$timestamp = wp_next_scheduled( 'wsscd_license_health_check' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'wsscd_license_health_check' );
		}
	}

	/**
	 * Check if license is currently valid.
	 *
	 * This is the main method for validating premium access.
	 * It checks cache first, then validates with Freemius API if needed.
	 *
	 * @since    1.0.0
	 * @return   bool    True if license is valid and active.
	 */
	public function is_license_valid() {
		if ( ! function_exists( 'wsscd_fs' ) || ! wsscd_fs() ) {
			return false;
		}

		// Quick check: if not registered, definitely not valid
		if ( ! wsscd_fs()->is_registered() ) {
			return false;
		}

		$cached = $this->get_cached_validation();
		if ( null !== $cached ) {
			return $cached;
		}

		// Perform fresh validation
		return $this->validate_with_api();
	}

	/**
	 * Get cached validation result if still valid.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   bool|null    Cached result or null if expired/unavailable.
	 */
	private function get_cached_validation() {
		$cache = get_option( $this->validation_cache_option );

		if ( ! $cache || ! is_array( $cache ) ) {
			return null;
		}

		$cache_time = isset( $cache['timestamp'] ) ? $cache['timestamp'] : 0;
		$cache_age  = time() - $cache_time;

		if ( $this->cache_duration < $cache_age ) {
			return null;
		}

		return isset( $cache['is_valid'] ) ? (bool) $cache['is_valid'] : null;
	}

	/**
	 * Validate license with Freemius API.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   bool    True if license is valid and active.
	 */
	private function validate_with_api() {
		if ( ! function_exists( 'wsscd_fs' ) || ! wsscd_fs() ) {
			$this->cache_validation( false );
			return false;
		}

		try {
			$is_premium = wsscd_fs()->is_premium();
			$is_trial   = wsscd_fs()->is_trial();

			// Additional verification: check license object
			if ( $is_premium || $is_trial ) {
				$license = wsscd_fs()->_get_license();

				// Verify license is active
				if ( $license && method_exists( $license, 'is_active' ) ) {
					$is_active = $license->is_active();

					$is_valid = $is_active && ( $is_premium || $is_trial );
					$this->cache_validation( $is_valid );
					return $is_valid;
				}
			}

			// No valid license found
			$this->cache_validation( false );
			return false;

		} catch ( Exception $e ) {
			// On API error, use cached result if available (offline tolerance)
			$cached = get_option( $this->validation_cache_option );
			if ( $cached && isset( $cached['is_valid'] ) ) {
				return (bool) $cached['is_valid'];
			}

			return false;
		}
	}

	/**
	 * Cache validation result with timestamp.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    bool $is_valid    Validation result to cache.
	 * @return   void
	 */
	private function cache_validation( $is_valid ) {
		$cache = array(
			'is_valid'  => (bool) $is_valid,
			'timestamp' => time(),
		);

		update_option( $this->validation_cache_option, $cache, false );
	}

	/**
	 * Clear validation cache.
	 *
	 * Called when license status changes (activation, upgrade, etc.).
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function clear_validation_cache() {
		delete_option( $this->validation_cache_option );
		delete_option( $this->last_check_option );
	}

	/**
	 * Run periodic check if interval has elapsed.
	 *
	 * Enhanced to check on plugin dashboard/main pages for active admins,
	 * ensuring fresh license status for users actively managing the plugin.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function maybe_run_periodic_check() {
		// Only run in admin
		if ( ! is_admin() ) {
			return;
		}

		// Only run for logged-in users with admin capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$last_check       = get_option( $this->last_check_option, 0 );
		$time_since_check = time() - $last_check;

		// Priority check: On plugin pages, check more frequently (every hour)
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && false !== strpos( $screen->id, 'smart-cycle-discounts' ) ) {
			// On plugin pages, check if more than 1 hour has passed
			if ( HOUR_IN_SECONDS < $time_since_check ) {
				$this->run_health_check();
				return;
			}
		}

		// Standard periodic check: Run if main interval has elapsed
		if ( $this->check_interval < $time_since_check ) {
			$this->run_health_check();
		}
	}

	/**
	 * Run license health check.
	 *
	 * Validates license with Freemius API and updates cache.
	 *
	 * @since    1.0.0
	 * @return   bool    Validation result.
	 */
	public function run_health_check() {
		delete_option( $this->validation_cache_option );

		// Perform validation
		$is_valid = $this->validate_with_api();

		update_option( $this->last_check_option, time(), false );

		// Log result if logging is available
		if ( function_exists( 'wsscd_log_info' ) ) {
			wsscd_log_info(
				'License health check completed',
				array(
					'is_valid'  => $is_valid,
					'timestamp' => time(),
				)
			);
		}

		return $is_valid;
	}

	/**
	 * Get license information.
	 *
	 * @since    1.0.0
	 * @return   array|null    License info or null if not available.
	 */
	public function get_license_info() {
		if ( ! function_exists( 'wsscd_fs' ) || ! wsscd_fs() ) {
			return null;
		}

		if ( ! wsscd_fs()->is_registered() ) {
			return null;
		}

		$license = wsscd_fs()->_get_license();
		if ( ! $license ) {
			return null;
		}

		return array(
			'is_active'  => method_exists( $license, 'is_active' ) ? $license->is_active() : false,
			'is_expired' => isset( $license->is_expired ) ? $license->is_expired : false,
			'expiration' => isset( $license->expiration ) ? $license->expiration : null,
			'is_trial'   => wsscd_fs()->is_trial(),
			'is_premium' => wsscd_fs()->is_premium(),
			'plan_name'  => wsscd_fs()->get_plan_name(),
		);
	}

	/**
	 * Get days until license check is required.
	 *
	 * @since    1.0.0
	 * @return   int    Days remaining until next check.
	 */
	public function get_days_until_next_check() {
		$last_check       = get_option( $this->last_check_option, 0 );
		$time_since_check = time() - $last_check;
		$time_until_check = $this->check_interval - $time_since_check;

		return max( 0, floor( $time_until_check / DAY_IN_SECONDS ) );
	}

	/**
	 * Check if user is a free user (never purchased).
	 *
	 * @since    1.0.0
	 * @return   bool    True if user has never purchased premium.
	 */
	public function is_free_user() {
		if ( ! function_exists( 'wsscd_fs' ) || ! wsscd_fs() ) {
			return true; // No Freemius = treat as free
		}

		// Not registered = free user
		if ( ! wsscd_fs()->is_registered() ) {
			return true;
		}

		// Registered but never purchased = free user
		if ( ! wsscd_fs()->has_paid_plan() ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if user is a pro user with an invalid/expired license.
	 *
	 * @since    1.0.0
	 * @return   bool    True if user purchased but license is now invalid.
	 */
	public function is_license_expired() {
		if ( ! function_exists( 'wsscd_fs' ) || ! wsscd_fs() ) {
			return false;
		}

		// Must be registered and have purchased before
		if ( ! wsscd_fs()->is_registered() || ! wsscd_fs()->has_paid_plan() ) {
			return false;
		}

		// Has paid plan but license is not valid = expired/revoked
		if ( ! $this->is_license_valid() ) {
			return true;
		}

		return false;
	}

	/**
	 * Force immediate license validation.
	 *
	 * Bypasses cache and checks directly with Freemius API.
	 *
	 * @since    1.0.0
	 * @return   bool    Validation result.
	 */
	public function force_validation() {
		$this->clear_validation_cache();
		return $this->run_health_check();
	}

	/**
	 * Clean up orphaned user meta from old notification system.
	 *
	 * Removes the old 'wsscd_dismissed_upgrade_notice' user meta that is no longer used.
	 * This was replaced by the Upgrade Prompt Manager's banner system.
	 *
	 * @since    1.0.0
	 * @return   int    Number of user meta entries deleted.
	 */
	public static function cleanup_orphaned_user_meta() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Bulk usermeta cleanup; no WP abstraction for bulk meta_key delete.
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->usermeta} WHERE meta_key = %s",
				'wsscd_dismissed_upgrade_notice'
			)
		);

		return absint( $deleted );
	}
}
