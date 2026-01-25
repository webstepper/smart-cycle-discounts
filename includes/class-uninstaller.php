<?php
/**
 * Uninstaller Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/class-uninstaller.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Uninstall Smart Cycle Discounts Plugin
 *
 * Removes all plugin data from the database including:
 * - All database tables
 * - All plugin options
 * - All user capabilities
 * - All cron jobs
 * - All uploaded files and directories
 *
 * @since    1.0.0
 */
class WSSCD_Uninstaller {

	/**
	 * Run the uninstaller.
	 *
	 * Called by Freemius after_uninstall hook.
	 *
	 * @since    1.0.0
	 */
	public static function uninstall() {
		global $wpdb;

		// Check if user wants to remove data on uninstall
		// Settings are stored in wsscd_settings array under ['advanced']['uninstall_data']
		// uninstall_data = true means REMOVE data (delete everything)
		// uninstall_data = false means KEEP data (preserve campaigns and settings)
		$settings = get_option( 'wsscd_settings', array() );
		$remove_data = isset( $settings['advanced']['uninstall_data'] ) && $settings['advanced']['uninstall_data'];
		$keep_data = ! $remove_data;

		if ( $keep_data ) {
			// User wants to keep data, just clear transients and caches
			self::clear_transients();
			self::clear_cron_jobs();
			return;
		}

		// Full uninstall - remove everything
		self::drop_database_tables();
		self::delete_plugin_options();
		self::delete_user_meta();
		self::remove_user_capabilities();
		self::clear_cron_jobs();
		self::delete_plugin_directories();
		self::clear_transients();
		self::flush_rewrite_rules();
		self::log_uninstall();
	}

	/**
	 * Drop all plugin database tables.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private static function drop_database_tables() {
		global $wpdb;

		// Suppress errors to prevent issues during uninstall
		$wpdb->suppress_errors();

		// Define tables to drop (in order - respecting foreign key dependencies)
		$tables = array(
			$wpdb->prefix . 'wsscd_customer_usage',
			$wpdb->prefix . 'wsscd_campaign_recurring',
			$wpdb->prefix . 'wsscd_analytics',
			$wpdb->prefix . 'wsscd_active_discounts',
			$wpdb->prefix . 'wsscd_campaigns',
			$wpdb->prefix . 'wsscd_migrations',
		);

		foreach ( $tables as $table ) {
			// First, drop all foreign keys to avoid constraint errors
			self::drop_table_foreign_keys( $table );

			// Then drop the table - table name prepared with %i identifier placeholder.
			$drop_table_sql = $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls, PluginCheck.Security.DirectDB.UnescapedDBParameter -- DROP TABLE during uninstall; query prepared above.
			$wpdb->query( $drop_table_sql );
		}

		// Re-enable error display
		$wpdb->suppress_errors( false );
	}

	/**
	 * Drop foreign key constraints from a table.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $table_name    Table name.
	 */
	private static function drop_table_foreign_keys( $table_name ) {
		global $wpdb;

		$check_table_sql = $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls, PluginCheck.Security.DirectDB.UnescapedDBParameter -- SHOW TABLES has no WP abstraction; query prepared above.
		if ( $wpdb->get_var( $check_table_sql ) !== $table_name ) {
			return;
		}

		$fk_query_sql = $wpdb->prepare(
			'SELECT CONSTRAINT_NAME
			FROM information_schema.KEY_COLUMN_USAGE
			WHERE TABLE_SCHEMA = %s
			AND TABLE_NAME = %s
			AND REFERENCED_TABLE_NAME IS NOT NULL',
			DB_NAME,
			$table_name
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls, PluginCheck.Security.DirectDB.UnescapedDBParameter -- INFORMATION_SCHEMA query has no WP abstraction; query prepared above.
		$foreign_keys = $wpdb->get_results( $fk_query_sql );

		// Drop each foreign key
		foreach ( $foreign_keys as $fk ) {
			$drop_fk_sql = $wpdb->prepare( 'ALTER TABLE %i DROP FOREIGN KEY %i', $table_name, $fk->CONSTRAINT_NAME );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls, PluginCheck.Security.DirectDB.UnescapedDBParameter -- ALTER TABLE during uninstall; query prepared above with %i.
			$wpdb->query( $drop_fk_sql );
		}
	}

	/**
	 * Delete all plugin options from wp_options table.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private static function delete_plugin_options() {
		global $wpdb;

		$delete_options_sql = $wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
			'wsscd_%'
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Bulk option cleanup during uninstall; query prepared above.
		$wpdb->query( $delete_options_sql );

		$specific_options = array(
			'wsscd_version',
			'wsscd_db_version',
			'wsscd_settings',
			'wsscd_activated_at',
			'wsscd_deactivated_at',
			'wsscd_activation_logs',
			'wsscd_deactivation_logs',
			'wsscd_preserved_data',
			'wsscd_keep_data_on_uninstall',
			'wsscd_allow_api_key_via_get',
			'wsscd_allow_jwt_via_get',
		);

		foreach ( $specific_options as $option ) {
			delete_option( $option );
		}

		// Clean up site options for multisite
		if ( is_multisite() ) {
			delete_site_option( 'wsscd_version' );
			delete_site_option( 'wsscd_db_version' );
			delete_site_option( 'wsscd_network_settings' );
		}
	}

	/**
	 * Delete all plugin user meta from wp_usermeta table.
	 *
	 * @since    1.1.5
	 * @access   private
	 */
	private static function delete_user_meta() {
		global $wpdb;

		// Delete all wsscd_ prefixed user meta
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Bulk user meta cleanup during uninstall; no WP abstraction for pattern-based delete.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
				'wsscd_%'
			)
		);
	}

	/**
	 * Remove plugin capabilities from all roles.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private static function remove_user_capabilities() {
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

		$roles = array( 'administrator', 'shop_manager', 'editor' );

		foreach ( $roles as $role_name ) {
			$role = get_role( $role_name );

			if ( $role ) {
				foreach ( $capabilities as $capability ) {
					$role->remove_cap( $capability );
				}
			}
		}
	}

	/**
	 * Clear all scheduled cron jobs.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private static function clear_cron_jobs() {
		$cron_hooks = array(
			'wsscd_update_campaign_status',
			'wsscd_collect_analytics',
			'wsscd_cleanup_tasks',
			'wsscd_cleanup_wizard_sessions',
			'wsscd_cleanup_audit_logs',
			'wsscd_cleanup_expired_sessions',
			'wsscd_cleanup_old_analytics',
			'wsscd_auto_purge_trash',
		);

		foreach ( $cron_hooks as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}
	}

	/**
	 * Delete plugin directories and files.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private static function delete_plugin_directories() {
		$upload_dir = wp_upload_dir();
		$wsscd_dir    = $upload_dir['basedir'] . '/smart-cycle-discounts';

		// Only delete if directory exists
		if ( is_dir( $wsscd_dir ) ) {
			self::delete_directory_recursively( $wsscd_dir );
		}
	}

	/**
	 * Recursively delete a directory and all its contents.
	 *
	 * Uses WP_Filesystem for WordPress.org compliance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $dir    Directory path.
	 */
	private static function delete_directory_recursively( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		// Initialize WP_Filesystem
		global $wp_filesystem;

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		// Try to initialize WP_Filesystem
		if ( WP_Filesystem() && $wp_filesystem ) {
			// Use WP_Filesystem for directory deletion
			$wp_filesystem->delete( $dir, true );
		} else {
			// Fallback to direct PHP methods if WP_Filesystem unavailable (during uninstall)
			$files = array_diff( scandir( $dir ), array( '.', '..' ) );

			foreach ( $files as $file ) {
				$file_path = $dir . DIRECTORY_SEPARATOR . $file;

				if ( is_dir( $file_path ) ) {
					self::delete_directory_recursively( $file_path );
				} else {
					wp_delete_file( $file_path );
				}
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Fallback when WP_Filesystem unavailable during uninstall; directory validated above.
			rmdir( $dir );
		}
	}

	/**
	 * Clear all plugin transients.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private static function clear_transients() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Bulk transient cleanup during uninstall; no WP abstraction for pattern-based delete.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options}
				WHERE option_name LIKE %s
				OR option_name LIKE %s",
				'_transient_wsscd_%',
				'_transient_timeout_wsscd_%'
			)
		);

		if ( is_multisite() ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Multisite transient cleanup during uninstall; no WP abstraction for pattern-based delete.
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->sitemeta}
					WHERE meta_key LIKE %s
					OR meta_key LIKE %s",
					'_site_transient_wsscd_%',
					'_site_transient_timeout_wsscd_%'
				)
			);
		}

		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}
	}

	/**
	 * Flush WordPress rewrite rules.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private static function flush_rewrite_rules() {
		flush_rewrite_rules();
	}

	/**
	 * Log the uninstall event.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private static function log_uninstall() {
		$log_data = array(
			'event'       => 'plugin_uninstalled',
			'timestamp'   => current_time( 'mysql' ),
			'user_id'     => get_current_user_id(),
			'site_url'    => get_site_url(),
			'wp_version'  => get_bloginfo( 'version' ),
			'php_version' => PHP_VERSION,
		);

		// Log to WordPress debug log if enabled
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional debug logging when WP_DEBUG_LOG is enabled.
			error_log(
				'[Smart Cycle Discounts] Plugin uninstalled: ' .
				wp_json_encode( $log_data )
			);
		}
	}
}
