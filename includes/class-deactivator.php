<?php
/**
 * Deactivator Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/class-deactivator.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */


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
 * @author     Webstepper <contact@webstepper.io>
 */
class WSSCD_Deactivator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function deactivate(): void {
		self::clear_cron_jobs();

		// Deactivate all active campaigns
		self::deactivate_campaigns();

		self::clear_cache();

		// Flush rewrite rules
		self::flush_rewrite_rules();

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
			'wsscd_update_campaign_status',
			'wsscd_cleanup_wizard_sessions',
			'wsscd_cleanup_audit_logs',
			'wsscd_analytics_hourly_aggregation',
			'wsscd_analytics_daily_aggregation',
			'wsscd_cleanup_expired_sessions',
			'wsscd_cleanup_old_analytics',
			'wsscd_activate_campaign',
			'wsscd_deactivate_campaign',
		);

		foreach ( $action_hooks as $hook ) {
			// Unschedule all instances of this action
			as_unschedule_all_actions( $hook, array(), 'wsscd_actions' );
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

		$campaigns_table = $wpdb->prefix . 'wsscd_campaigns';
		$discounts_table = $wpdb->prefix . 'wsscd_active_discounts';

		$check_table_sql = $wpdb->prepare( 'SHOW TABLES LIKE %s', $campaigns_table );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls, PluginCheck.Security.DirectDB.UnescapedDBParameter -- SHOW TABLES has no WP abstraction; query prepared above.
		if ( $wpdb->get_var( $check_table_sql ) !== $campaigns_table ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Bulk update on plugin's custom table during deactivation.
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

		$check_discounts_sql = $wpdb->prepare( 'SHOW TABLES LIKE %s', $discounts_table );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls, PluginCheck.Security.DirectDB.UnescapedDBParameter -- SHOW TABLES has no WP abstraction; query prepared above.
		if ( $wpdb->get_var( $check_discounts_sql ) === $discounts_table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Bulk update on plugin's custom table during deactivation.
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
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Query for logging purposes; results not cached.
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
				// Load WSSCD_Log if not already loaded
				if ( ! class_exists( 'WSSCD_Log' ) ) {
					require_once WSSCD_PLUGIN_DIR . 'includes/utilities/class-wsscd-log.php';
				}
				WSSCD_Log::info( 'Campaigns Deactivated', $log_data );
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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Bulk transient deletion during deactivation; no WP abstraction for pattern-based delete.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				'_transient_' . WSSCD_TRANSIENT_PREFIX . '%',
				'_transient_timeout_' . WSSCD_TRANSIENT_PREFIX . '%'
			)
		);

		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}

		$upload_dir = wp_upload_dir();
		$cache_dir  = $upload_dir['basedir'] . '/smart-cycle-discounts/cache';

		if ( is_dir( $cache_dir ) ) {
			self::delete_directory_contents( $cache_dir );
		}

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
		update_option( 'wsscd_deactivated_at', current_time( 'mysql' ) );
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
			'version'     => WSSCD_VERSION,
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
			if ( ! class_exists( 'WSSCD_Log' ) ) {
				require_once WSSCD_PLUGIN_DIR . 'includes/utilities/class-wsscd-log.php';
			}
			WSSCD_Log::info( 'Plugin Deactivated', $log_data );
		}

		$logs   = get_option( 'wsscd_deactivation_logs', array() );
		$logs[] = $log_data;

		// Keep only last 10 deactivation logs
		if ( count( $logs ) > 10 ) {
			$logs = array_slice( $logs, -10 );
		}

		update_option( 'wsscd_deactivation_logs', $logs );
	}

	/**
	 * Calculate how long the plugin was active.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   string    Human readable time difference.
	 */
	private static function calculate_active_time(): string {
		$activated_at = get_option( 'wsscd_activated_at' );
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
		$wsscd_dir    = $upload_dir['basedir'] . '/smart-cycle-discounts';

		$temp_directories = array(
			$wsscd_dir . '/temp',
			$wsscd_dir . '/cache',
		);

		foreach ( $temp_directories as $dir ) {
			if ( is_dir( $dir ) ) {
				self::delete_directory_contents( $dir );
			}
		}

		// Clean up any temporary options
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Bulk temporary option cleanup during deactivation; no WP abstraction for pattern-based delete.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				'wsscd_temp_%'
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
				wp_delete_file( $file_path );
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
				wp_delete_file( $file_path );
			}
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Plugin uninstall cleanup; WP_Filesystem overhead unnecessary.
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
			'wsscd_view_campaigns',
			'wsscd_manage_campaigns',
			'wsscd_create_campaigns',
			'wsscd_edit_campaigns',
			'wsscd_delete_campaigns',
			'wsscd_activate_campaigns',
			'wsscd_view_analytics',
			'wsscd_manage_analytics',
			'wsscd_export_analytics',
			'wsscd_view_products',
			'wsscd_manage_settings',
			'wsscd_manage_tools',
			'wsscd_import_export',
		);

		$admin_role = get_role( 'administrator' );
		if ( $admin_role ) {
			foreach ( $capabilities as $capability ) {
				$admin_role->remove_cap( $capability );
			}
		}

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

		$campaigns_table = $wpdb->prefix . 'wsscd_campaigns';

		$check_campaigns_sql = $wpdb->prepare( 'SHOW TABLES LIKE %s', $campaigns_table );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls, PluginCheck.Security.DirectDB.UnescapedDBParameter -- SHOW TABLES has no WP abstraction; query prepared above.
		if ( $wpdb->get_var( $check_campaigns_sql ) === $campaigns_table ) {
			$count_sql = $wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE deleted_at IS NULL',
				$campaigns_table
			);
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Deactivation stats query; query prepared above.
			$stats['campaigns_count'] = (int) $wpdb->get_var( $count_sql );

			$active_count_sql = $wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE status = %s AND deleted_at IS NULL',
				$campaigns_table,
				'active'
			);
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Deactivation stats query; query prepared above.
			$stats['active_campaigns_count'] = (int) $wpdb->get_var( $active_count_sql );

			$totals_sql = $wpdb->prepare(
				'SELECT SUM(revenue_generated) as total_revenue, SUM(orders_count) as total_orders
                     FROM %i WHERE deleted_at IS NULL',
				$campaigns_table
			);
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Deactivation stats query; query prepared above.
			$totals = $wpdb->get_row( $totals_sql );

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

		$send_feedback = get_option( 'wsscd_send_usage_data', false );
		if ( ! $send_feedback ) {
			return;
		}

		$data = array_merge(
			array(
				'action'      => 'deactivate',
				'version'     => WSSCD_VERSION,
				'site_url'    => get_site_url(),
				'wp_version'  => get_bloginfo( 'version' ),
				'wc_version'  => defined( 'WC_VERSION' ) ? WC_VERSION : 'unknown',
				'php_version' => PHP_VERSION,
				'stats'       => self::get_deactivation_stats(),
			),
			$feedback
		);

		// Send data to remote server (implement as needed)
		$api_url = defined( 'WSSCD_API_URL' ) ? WSSCD_API_URL : 'https://api.smartcyclediscounts.com';
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
		$important_data = array(
			'settings'       => get_option( 'wsscd_settings' ),
			'version'        => get_option( 'wsscd_version' ),
			'activated_at'   => get_option( 'wsscd_activated_at' ),
			'deactivated_at' => current_time( 'mysql' ),
		);

		update_option( 'wsscd_preserved_data', $important_data );
	}

	/**
	 * Check if this is a temporary deactivation or permanent removal.
	 *
	 * @since    1.0.0
	 * @return   bool    True if temporary, false if permanent.
	 */
	private static function is_temporary_deactivation(): bool {
		if ( defined( 'WP_INSTALLING' ) && WP_INSTALLING ) {
			return true;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Checking WordPress core action during plugin deactivation, no nonce available.
		if ( isset( $_GET['action'] ) && 'upgrade-plugin' === sanitize_text_field( wp_unslash( $_GET['action'] ) ) ) {
			return true;
		}

		return false;
	}
}
