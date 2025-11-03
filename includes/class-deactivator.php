<?php
/**
 * Deactivator Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/class-deactivator.php
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Deactivator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function deactivate(): void {
		// Clear scheduled cron jobs
		self::clear_cron_jobs();

		// Deactivate all active campaigns
		self::deactivate_campaigns();

		// Clear cache
		self::clear_cache();

		// Flush rewrite rules
		self::flush_rewrite_rules();

		// Set deactivation timestamp
		self::set_deactivation_timestamp();

		// Log deactivation
		self::log_deactivation();

		// Clean up temporary data
		self::cleanup_temporary_data();
	}

	/**
	 * Clear all scheduled actions.
	 *
	 * Unschedules all ActionScheduler actions for this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private static function clear_cron_jobs(): void {
		// Verify ActionScheduler is available
		if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
			return;
		}

		$action_hooks = array(
			'scd_update_campaign_status',
			'scd_cleanup_wizard_sessions',
			'scd_cleanup_audit_logs',
			'scd_analytics_hourly_aggregation',
			'scd_analytics_daily_aggregation',
			'scd_cleanup_expired_sessions',
			'scd_cleanup_old_analytics',
			'scd_activate_campaign',
			'scd_deactivate_campaign',
		);

		foreach ( $action_hooks as $hook ) {
			// Unschedule all instances of this action
			as_unschedule_all_actions( $hook, array(), 'scd_actions' );
		}
	}

	/**
	 * Deactivate all active campaigns.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private static function deactivate_campaigns(): void {
		global $wpdb;

		$campaigns_table = $wpdb->prefix . 'scd_campaigns';
		$discounts_table = $wpdb->prefix . 'scd_active_discounts';

		// Check if tables exist
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $campaigns_table ) ) !== $campaigns_table ) {
			return;
		}

		// Update all active campaigns to paused status
		$wpdb->update(
			$campaigns_table,
			array(
				'status'     => 'paused',
				'updated_at' => current_time( 'mysql' ),
			),
			array(
				'status' => 'active',
			),
			array( '%s', '%s' ),
			array( '%s' )
		);

		// Update all active discounts to paused status
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $discounts_table ) ) === $discounts_table ) {
			$wpdb->update(
				$discounts_table,
				array(
					'status'     => 'paused',
					'updated_at' => current_time( 'mysql' ),
				),
				array(
					'status' => 'active',
				),
				array( '%s', '%s' ),
				array( '%s' )
			);
		}

		// Log campaign deactivation
		$affected_campaigns = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id, name FROM %i WHERE status = %s',
				$campaigns_table,
				'paused'
			)
		);

		if ( ! empty( $affected_campaigns ) ) {
			$log_data = array(
				'event'     => 'campaigns_deactivated',
				'count'     => count( $affected_campaigns ),
				'campaigns' => array_map(
					function ( $campaign ) {
						return array(
							'id'   => $campaign->id,
							'name' => $campaign->name,
						);
					},
					$affected_campaigns
				),
				'timestamp' => current_time( 'mysql' ),
			);

			if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				// Load SCD_Log if not already loaded
				if ( ! class_exists( 'SCD_Log' ) ) {
					require_once SCD_PLUGIN_DIR . 'includes/utilities/class-scd-log.php';
				}
				SCD_Log::info( 'Campaigns Deactivated', $log_data );
			}
		}
	}

	/**
	 * Clear all plugin cache.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private static function clear_cache(): void {
		// Clear WordPress transients
		global $wpdb;

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				'_transient_' . SCD_TRANSIENT_PREFIX . '%',
				'_transient_timeout_' . SCD_TRANSIENT_PREFIX . '%'
			)
		);

		// Clear object cache if available
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}

		// Clear file-based cache
		$upload_dir = wp_upload_dir();
		$cache_dir  = $upload_dir['basedir'] . '/smart-cycle-discounts/cache';

		if ( is_dir( $cache_dir ) ) {
			self::delete_directory_contents( $cache_dir );
		}

		// Clear any WooCommerce cache
		if ( function_exists( 'wc_delete_product_transients' ) ) {
			// This will be handled by WooCommerce's own cache clearing
		}
	}

	/**
	 * Flush rewrite rules.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private static function flush_rewrite_rules(): void {
		flush_rewrite_rules();
	}

	/**
	 * Set deactivation timestamp.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private static function set_deactivation_timestamp(): void {
		update_option( 'scd_deactivated_at', current_time( 'mysql' ) );
	}

	/**
	 * Log plugin deactivation.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private static function log_deactivation(): void {
		$log_data = array(
			'event'       => 'plugin_deactivated',
			'version'     => SCD_VERSION,
			'php_version' => PHP_VERSION,
			'wp_version'  => get_bloginfo( 'version' ),
			'wc_version'  => defined( 'WC_VERSION' ) ? WC_VERSION : 'unknown',
			'timestamp'   => current_time( 'mysql' ),
			'user_id'     => get_current_user_id(),
			'site_url'    => get_site_url(),
			'active_time' => self::calculate_active_time(),
		);

		// Log to WordPress debug log if enabled
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			// Load logger if not already loaded
			if ( ! class_exists( 'SCD_Log' ) ) {
				require_once SCD_PLUGIN_DIR . 'includes/utilities/class-scd-log.php';
			}
			SCD_Log::info( 'Plugin Deactivated', $log_data );
		}

		// Store deactivation log in database
		$logs   = get_option( 'scd_deactivation_logs', array() );
		$logs[] = $log_data;

		// Keep only last 10 deactivation logs
		if ( count( $logs ) > 10 ) {
			$logs = array_slice( $logs, -10 );
		}

		update_option( 'scd_deactivation_logs', $logs );
	}

	/**
	 * Calculate how long the plugin was active.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   string    Human readable time difference.
	 */
	private static function calculate_active_time(): string {
		$activated_at = get_option( 'scd_activated_at' );
		if ( ! $activated_at ) {
			return 'unknown';
		}

		$activated_timestamp = strtotime( $activated_at );
		$current_timestamp   = current_time( 'timestamp' );
		$diff                = $current_timestamp - $activated_timestamp;

		return human_time_diff( $activated_timestamp, $current_timestamp );
	}

	/**
	 * Clean up temporary data.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private static function cleanup_temporary_data(): void {
		$upload_dir = wp_upload_dir();
		$scd_dir    = $upload_dir['basedir'] . '/smart-cycle-discounts';

		$temp_directories = array(
			$scd_dir . '/temp',
			$scd_dir . '/cache',
		);

		foreach ( $temp_directories as $dir ) {
			if ( is_dir( $dir ) ) {
				self::delete_directory_contents( $dir );
			}
		}

		// Clean up any temporary options
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				'scd_temp_%'
			)
		);
	}

	/**
	 * Delete contents of a directory but keep the directory.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $dir    Directory path.
	 */
	private static function delete_directory_contents( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		$files = array_diff( scandir( $dir ), array( '.', '..' ) );

		foreach ( $files as $file ) {
			$file_path = $dir . DIRECTORY_SEPARATOR . $file;

			if ( is_dir( $file_path ) ) {
				self::delete_directory_recursively( $file_path );
			} else {
				unlink( $file_path );
			}
		}
	}

	/**
	 * Delete a directory and all its contents recursively.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $dir    Directory path.
	 */
	private static function delete_directory_recursively( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		$files = array_diff( scandir( $dir ), array( '.', '..' ) );

		foreach ( $files as $file ) {
			$file_path = $dir . DIRECTORY_SEPARATOR . $file;

			if ( is_dir( $file_path ) ) {
				self::delete_directory_recursively( $file_path );
			} else {
				unlink( $file_path );
			}
		}

		rmdir( $dir );
	}

	/**
	 * Remove custom capabilities (optional - usually kept for reactivation).
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private static function remove_capabilities(): void {
		$capabilities = array(
			'manage_scd_campaigns',
			'create_scd_campaigns',
			'edit_scd_campaigns',
			'delete_scd_campaigns',
			'view_scd_analytics',
			'export_scd_data',
			'manage_scd_settings',
		);

		// Remove capabilities from administrator role
		$admin_role = get_role( 'administrator' );
		if ( $admin_role ) {
			foreach ( $capabilities as $capability ) {
				$admin_role->remove_cap( $capability );
			}
		}

		// Remove capabilities from shop manager role
		$shop_manager_role = get_role( 'shop_manager' );
		if ( $shop_manager_role ) {
			foreach ( $capabilities as $capability ) {
				$shop_manager_role->remove_cap( $capability );
			}
		}
	}

	/**
	 * Get deactivation statistics.
	 *
	 * @since    1.0.0
	 * @return   array    Deactivation statistics.
	 */
	public static function get_deactivation_stats(): array {
		global $wpdb;

		$stats = array(
			'campaigns_count'        => 0,
			'active_campaigns_count' => 0,
			'total_revenue'          => 0,
			'total_orders'           => 0,
		);

		$campaigns_table = $wpdb->prefix . 'scd_campaigns';

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $campaigns_table ) ) === $campaigns_table ) {
			// Get campaign counts
			$stats['campaigns_count'] = (int) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(*) FROM %i WHERE deleted_at IS NULL',
					$campaigns_table
				)
			);

			$stats['active_campaigns_count'] = (int) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(*) FROM %i WHERE status = %s AND deleted_at IS NULL',
					$campaigns_table,
					'active'
				)
			);

			// Get revenue and orders totals
			$totals = $wpdb->get_row(
				$wpdb->prepare(
					'SELECT SUM(revenue_generated) as total_revenue, SUM(orders_count) as total_orders 
                     FROM %i WHERE deleted_at IS NULL',
					$campaigns_table
				)
			);

			if ( $totals ) {
				$stats['total_revenue'] = (float) $totals->total_revenue;
				$stats['total_orders']  = (int) $totals->total_orders;
			}
		}

		return $stats;
	}

	/**
	 * Send deactivation feedback (optional).
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $feedback    Feedback data.
	 */
	private static function send_deactivation_feedback( array $feedback = array() ): void {
		// This is optional and would send anonymous usage data
		// Only implement if user has opted in to data collection

		$send_feedback = get_option( 'scd_send_usage_data', false );
		if ( ! $send_feedback ) {
			return;
		}

		$data = array_merge(
			array(
				'action'      => 'deactivate',
				'version'     => SCD_VERSION,
				'site_url'    => get_site_url(),
				'wp_version'  => get_bloginfo( 'version' ),
				'wc_version'  => defined( 'WC_VERSION' ) ? WC_VERSION : 'unknown',
				'php_version' => PHP_VERSION,
				'stats'       => self::get_deactivation_stats(),
			),
			$feedback
		);

		// Send data to remote server (implement as needed)
		$api_url = defined( 'SCD_API_URL' ) ? SCD_API_URL : 'https://api.smartcyclediscounts.com';
		wp_remote_post(
			$api_url . '/feedback',
			array(
				'body'     => wp_json_encode( $data ),
				'headers'  => array(
					'Content-Type' => 'application/json',
				),
				'timeout'  => 5,
				'blocking' => false, // Non-blocking request
			)
		);
	}

	/**
	 * Preserve important data for potential reactivation.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private static function preserve_data(): void {
		// Create a backup of important settings
		$important_data = array(
			'settings'       => get_option( 'scd_settings' ),
			'version'        => get_option( 'scd_version' ),
			'activated_at'   => get_option( 'scd_activated_at' ),
			'deactivated_at' => current_time( 'mysql' ),
		);

		update_option( 'scd_preserved_data', $important_data );
	}

	/**
	 * Check if this is a temporary deactivation or permanent removal.
	 *
	 * @since    1.0.0
	 * @return   bool    True if temporary, false if permanent.
	 */
	private static function is_temporary_deactivation(): bool {
		// Check if this is part of an update process
		if ( defined( 'WP_INSTALLING' ) && WP_INSTALLING ) {
			return true;
		}

		// Check if user is updating the plugin
		if ( isset( $_GET['action'] ) && 'upgrade-plugin' === sanitize_text_field( wp_unslash( $_GET['action'] ) ) ) {
			return true;
		}

		return false;
	}
}
