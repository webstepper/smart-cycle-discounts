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
class SCD_Uninstaller {

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
		// Settings are stored in scd_settings array under ['advanced']['uninstall_data']
		// uninstall_data = true means REMOVE data (delete everything)
		// uninstall_data = false means KEEP data (preserve campaigns and settings)
		$settings = get_option( 'scd_settings', array() );
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
			$wpdb->prefix . 'scd_customer_usage',
			$wpdb->prefix . 'scd_campaign_recurring',
			$wpdb->prefix . 'scd_analytics',
			$wpdb->prefix . 'scd_active_discounts',
			$wpdb->prefix . 'scd_campaigns',
			$wpdb->prefix . 'scd_migrations',
		);

		foreach ( $tables as $table ) {
			// First, drop all foreign keys to avoid constraint errors
			self::drop_table_foreign_keys( $table );

			// Then drop the table
			$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) !== $table_name ) {
			return;
		}

		$foreign_keys = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT CONSTRAINT_NAME
				FROM information_schema.KEY_COLUMN_USAGE
				WHERE TABLE_SCHEMA = %s
				AND TABLE_NAME = %s
				AND REFERENCED_TABLE_NAME IS NOT NULL',
				DB_NAME,
				$table_name
			)
		);

		// Drop each foreign key
		foreach ( $foreign_keys as $fk ) {
			$wpdb->query(
				"ALTER TABLE `{$table_name}` DROP FOREIGN KEY `{$fk->CONSTRAINT_NAME}`" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			);
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

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				'scd_%'
			)
		);

		$specific_options = array(
			'scd_version',
			'scd_db_version',
			'scd_settings',
			'scd_activated_at',
			'scd_deactivated_at',
			'scd_activation_logs',
			'scd_deactivation_logs',
			'scd_preserved_data',
			'scd_keep_data_on_uninstall',
			'scd_allow_api_key_via_get',
			'scd_allow_jwt_via_get',
		);

		foreach ( $specific_options as $option ) {
			delete_option( $option );
		}

		// Clean up site options for multisite
		if ( is_multisite() ) {
			delete_site_option( 'scd_version' );
			delete_site_option( 'scd_db_version' );
			delete_site_option( 'scd_network_settings' );
		}
	}

	/**
	 * Remove plugin capabilities from all roles.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private static function remove_user_capabilities() {
		$capabilities = array(
			'manage_scd_campaigns',
			'create_scd_campaigns',
			'edit_scd_campaigns',
			'delete_scd_campaigns',
			'view_scd_analytics',
			'export_scd_data',
			'manage_scd_settings',
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
			'scd_update_campaign_status',
			'scd_collect_analytics',
			'scd_cleanup_tasks',
			'scd_warm_cache',
			'scd_cleanup_wizard_sessions',
			'scd_cleanup_audit_logs',
			'scd_cleanup_expired_sessions',
			'scd_cleanup_old_analytics',
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
		$scd_dir    = $upload_dir['basedir'] . '/smart-cycle-discounts';

		// Only delete if directory exists
		if ( is_dir( $scd_dir ) ) {
			self::delete_directory_recursively( $scd_dir );
		}
	}

	/**
	 * Recursively delete a directory and all its contents.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $dir    Directory path.
	 */
	private static function delete_directory_recursively( $dir ) {
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

		rmdir( $dir );
	}

	/**
	 * Clear all plugin transients.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private static function clear_transients() {
		global $wpdb;

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options}
				WHERE option_name LIKE %s
				OR option_name LIKE %s",
				'_transient_scd_%',
				'_transient_timeout_scd_%'
			)
		);

		if ( is_multisite() ) {
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->sitemeta}
					WHERE meta_key LIKE %s
					OR meta_key LIKE %s",
					'_site_transient_scd_%',
					'_site_transient_timeout_scd_%'
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
			error_log(
				'[Smart Cycle Discounts] Plugin uninstalled: ' .
				wp_json_encode( $log_data )
			);
		}
	}
}
