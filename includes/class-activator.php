<?php
/**
 * Activator Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/class-activator.php
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
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes
 * @author     Webstepper <contact@webstepper.io>
 */
class SCD_Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
		// Suppress all output during activation
		ob_start();

		try {
			self::check_requirements();

			self::create_database_tables();

			self::set_default_options();

			self::create_directories();

			self::setup_cron_jobs();

			self::create_capabilities();

			// Flush rewrite rules
			self::flush_rewrite_rules();

			self::set_activation_timestamp();

			// Clear license and feature caches (ensures fresh validation after updates)
			self::clear_license_caches();

			// Log activation
			self::log_activation();

			self::set_activation_redirect();
		} finally {
			// Clean any output buffer
			ob_end_clean();
		}
	}

	/**
	 * Check system requirements before activation.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @throws   Exception If requirements are not met.
	 */
	private static function check_requirements() {
		// Check PHP version
		if ( version_compare( PHP_VERSION, SCD_MIN_PHP_VERSION, '<' ) ) {
			if ( ! function_exists( 'deactivate_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			deactivate_plugins( plugin_basename( __FILE__ ) );
			wp_die(
				sprintf(
					'Smart Cycle Discounts requires PHP %s or higher. You are running PHP %s.',
					SCD_MIN_PHP_VERSION,
					PHP_VERSION
				),
				'Plugin Activation Error',
				array( 'back_link' => true )
			);
		}

		// Check WordPress version
		if ( version_compare( get_bloginfo( 'version' ), SCD_MIN_WP_VERSION, '<' ) ) {
			if ( ! function_exists( 'deactivate_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			deactivate_plugins( plugin_basename( __FILE__ ) );
			wp_die(
				sprintf(
					'Smart Cycle Discounts requires WordPress %s or higher. You are running WordPress %s.',
					SCD_MIN_WP_VERSION,
					get_bloginfo( 'version' )
				),
				'Plugin Activation Error',
				array( 'back_link' => true )
			);
		}

		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( ! is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
			deactivate_plugins( plugin_basename( __FILE__ ) );
			wp_die(
				'Smart Cycle Discounts requires WooCommerce to be installed and activated.',
				'Plugin Activation Error',
				array( 'back_link' => true )
			);
		}

		// Check WooCommerce version
		if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, SCD_MIN_WC_VERSION, '<' ) ) {
			if ( ! function_exists( 'deactivate_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			deactivate_plugins( plugin_basename( __FILE__ ) );
			wp_die(
				sprintf(
					'Smart Cycle Discounts requires WooCommerce %s or higher. You are running WooCommerce %s.',
					SCD_MIN_WC_VERSION,
					WC_VERSION
				),
				'Plugin Activation Error',
				array( 'back_link' => true )
			);
		}

		$required_extensions = array( 'json', 'mbstring', 'openssl' );
		foreach ( $required_extensions as $extension ) {
			if ( ! extension_loaded( $extension ) ) {
				if ( ! function_exists( 'deactivate_plugins' ) ) {
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
				}
				deactivate_plugins( plugin_basename( __FILE__ ) );
				wp_die(
					sprintf(
						'Smart Cycle Discounts requires the PHP %s extension to be installed.',
						$extension
					),
					'Plugin Activation Error',
					array( 'back_link' => true )
				);
			}
		}
	}

	/**
	 * Create database tables.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private static function create_database_tables() {
		global $wpdb;

		// Use migration manager instead of hardcoded schema
		try {
			if ( ! class_exists( 'SCD_Database_Manager' ) ) {
				require_once SCD_PLUGIN_DIR . 'includes/database/class-database-manager.php';
			}
			if ( ! class_exists( 'SCD_Migration_Manager' ) ) {
				require_once SCD_PLUGIN_DIR . 'includes/database/class-migration-manager.php';
			}

			$db_manager = new SCD_Database_Manager( $wpdb );

			// Clean up any partially-created tables from previous failed attempts
			self::cleanup_partial_installation( $db_manager );

			$migration_manager = new SCD_Migration_Manager( $db_manager );

			// Run migrations
			$result = $migration_manager->migrate();

			if ( $result['status'] !== 'success' ) {
				$error_msg  = '<h2>Smart Cycle Discounts: Database Setup Failed</h2>';
				$error_msg .= '<p>Failed to create database tables. Please check the details below:</p>';

				if ( ! empty( $result['message'] ) ) {
					$error_msg .= '<p><strong>Error:</strong> ' . esc_html( $result['message'] ) . '</p>';
				}

				if ( ! empty( $result['migrations'] ) && is_array( $result['migrations'] ) ) {
					$error_msg .= '<h3>Migration Details:</h3><ul>';
					foreach ( $result['migrations'] as $migration ) {
						$status     = ! empty( $migration['success'] ) ? '✓' : '✗';
						$msg        = ! empty( $migration['message'] ) ? esc_html( $migration['message'] ) : 'No message';
						$name       = ! empty( $migration['migration'] ) ? esc_html( $migration['migration'] ) : 'Unknown';
						$error_msg .= "<li>{$status} {$name}: {$msg}</li>";
					}
					$error_msg .= '</ul>';
				}

				if ( ! empty( $result['errors'] ) && is_array( $result['errors'] ) ) {
					$error_msg .= '<h3>Additional Errors:</h3><ul>';
					foreach ( $result['errors'] as $error ) {
						$error_msg .= '<li>' . esc_html( $error ) . '</li>';
					}
					$error_msg .= '</ul>';
				}

				$error_msg .= '<h3>System Information:</h3><ul>';
				$error_msg .= '<li>PHP Version: ' . PHP_VERSION . '</li>';
				$error_msg .= '<li>WordPress Version: ' . get_bloginfo( 'version' ) . '</li>';
				$error_msg .= '<li>MySQL Version: ' . $wpdb->db_version() . '</li>';
				$error_msg .= '<li>Plugin Directory: ' . SCD_PLUGIN_DIR . '</li>';
				$error_msg .= '</ul>';

				wp_die(
					$error_msg,
					'Plugin Activation Error',
					array( 'back_link' => true )
				);
			}
		} catch ( Exception $e ) {
			$error_msg  = '<h2>Smart Cycle Discounts: Activation Exception</h2>';
			$error_msg .= '<p><strong>Error:</strong> ' . esc_html( $e->getMessage() ) . '</p>';
			$error_msg .= '<p><strong>File:</strong> ' . esc_html( $e->getFile() ) . '</p>';
			$error_msg .= '<p><strong>Line:</strong> ' . $e->getLine() . '</p>';

			if ( SCD_DEBUG ) {
				$error_msg .= '<h3>Stack Trace:</h3><pre>' . esc_html( $e->getTraceAsString() ) . '</pre>';
			}

			wp_die(
				$error_msg,
				'Plugin Activation Error',
				array( 'back_link' => true )
			);
		}
	}

	/**
	 * Clean up partially-created tables from previous failed installations.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    SCD_Database_Manager $db_manager    Database manager instance.
	 * @return   void
	 */
	private static function cleanup_partial_installation( SCD_Database_Manager $db_manager ): void {
		// (migrations table exists but is malformed, or has no executed migrations)
		global $wpdb;

		$table_name = $db_manager->get_table_name( 'migrations' );

		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );

		if ( $table_exists ) {
			$columns      = $wpdb->get_results( "SHOW COLUMNS FROM {$table_name}" );
			$column_names = array_map( fn( $col ) => $col->Field, $columns );

			// If table exists but doesn't have the right columns, drop it
			if ( ! in_array( 'migration', $column_names, true ) || ! in_array( 'batch', $column_names, true ) ) {
				$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
			}
		}
	}

	/**
	 * Set default plugin options.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private static function set_default_options() {
		$default_options = array(
			'scd_version'    => SCD_VERSION,
			'scd_db_version' => SCD_DB_VERSION,
			'scd_settings'   => array(
				'general'     => array(
					'enable_plugin'     => true,
					'debug_mode'        => false,
					'cache_enabled'     => true,
					'analytics_enabled' => true,
				),
				'campaigns'   => array(
					'max_active_campaigns' => 10,
					'default_timezone'     => wp_timezone_string(),
					'auto_archive_expired' => true,
				),
				'discounts'   => array(
					'stack_discounts'             => false,
					'minimum_discount_amount'     => 0.01,
					'maximum_discount_percentage' => 90,
				),
				'performance' => array(
					'cache_duration'     => 3600,
					'batch_size'         => 100,
					'max_execution_time' => 30,
				),
				'security'    => array(
					'allow_api_key_via_get' => false,
					'allow_jwt_via_get'     => false,
				),
			),
		);

		foreach ( $default_options as $option_name => $option_value ) {
			if ( ! get_option( $option_name ) ) {
				add_option( $option_name, $option_value );
			}
		}

		if ( ! get_option( 'scd_allow_api_key_via_get' ) ) {
			add_option( 'scd_allow_api_key_via_get', false );
		}
		if ( ! get_option( 'scd_allow_jwt_via_get' ) ) {
			add_option( 'scd_allow_jwt_via_get', false );
		}
	}

	/**
	 * Create necessary directories.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private static function create_directories() {
		$upload_dir = wp_upload_dir();
		$scd_dir    = $upload_dir['basedir'] . '/smart-cycle-discounts';

		$directories = array(
			$scd_dir,
			$scd_dir . '/logs',
			$scd_dir . '/cache',
			$scd_dir . '/exports',
			$scd_dir . '/imports',
			$scd_dir . '/temp',
		);

		foreach ( $directories as $dir ) {
			if ( ! file_exists( $dir ) ) {
				wp_mkdir_p( $dir );

				// Create .htaccess file to protect directory
				$htaccess_content = "Order deny,allow\nDeny from all\n";
				file_put_contents( $dir . '/.htaccess', $htaccess_content );

				file_put_contents( $dir . '/index.php', '<?php // Silence is golden' );
			}
		}
	}

	/**
	 * Set up scheduled actions.
	 *
	 * ActionScheduler-based tasks will be registered when the plugin initializes on the init hook.
	 * This method is intentionally left minimal to avoid timing issues during activation.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private static function setup_cron_jobs() {
		// ActionScheduler may not be fully initialized during plugin activation.
		// Scheduling will be handled by SCD_Cron_Scheduler::init() which runs on the 'init' hook.
		// This ensures ActionScheduler is ready before we attempt to schedule actions.

		// Schedule native WordPress cron jobs (don't require ActionScheduler)
		// License health check - runs daily
		if ( ! wp_next_scheduled( 'scd_license_health_check' ) ) {
			wp_schedule_event( time(), 'daily', 'scd_license_health_check' );
		}

		// Log that ActionScheduler scheduling will be deferred
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Smart Cycle Discounts: ActionScheduler cron job scheduling deferred to init hook' );
		}
	}

	/**
	 * Create custom capabilities.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private static function create_capabilities() {
		$capabilities = array(
			'manage_scd_campaigns',
			'create_scd_campaigns',
			'edit_scd_campaigns',
			'delete_scd_campaigns',
			'view_scd_analytics',
			'export_scd_data',
			'manage_scd_settings',
		);

		$admin_role = get_role( 'administrator' );
		if ( $admin_role ) {
			foreach ( $capabilities as $capability ) {
				$admin_role->add_cap( $capability );
			}
		}

		$shop_manager_role = get_role( 'shop_manager' );
		if ( $shop_manager_role ) {
			$shop_manager_caps = array(
				'manage_scd_campaigns',
				'create_scd_campaigns',
				'edit_scd_campaigns',
				'view_scd_analytics',
			);
			foreach ( $shop_manager_caps as $capability ) {
				$shop_manager_role->add_cap( $capability );
			}
		}
	}

	/**
	 * Flush rewrite rules.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private static function flush_rewrite_rules() {
		add_rewrite_rule(
			'^scd-api/([^/]+)/?',
			'index.php?scd_api_endpoint=$matches[1]',
			'top'
		);

		// Flush rewrite rules
		flush_rewrite_rules();
	}

	/**
	 * Set activation timestamp.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private static function set_activation_timestamp() {
		if ( ! get_option( 'scd_activated_at' ) ) {
			add_option( 'scd_activated_at', current_time( 'mysql' ) );
		}
	}

	/**
	 * Log plugin activation.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private static function log_activation() {
		$log_data = array(
			'event'       => 'plugin_activated',
			'version'     => SCD_VERSION,
			'php_version' => PHP_VERSION,
			'wp_version'  => get_bloginfo( 'version' ),
			'wc_version'  => defined( 'WC_VERSION' ) ? WC_VERSION : 'unknown',
			'timestamp'   => current_time( 'mysql' ),
			'user_id'     => get_current_user_id(),
			'site_url'    => get_site_url(),
		);

		// Do not use SCD_Log during activation as it may produce output
		$logs   = get_option( 'scd_activation_logs', array() );
		$logs[] = $log_data;

		// Keep only last 10 activation logs
		if ( count( $logs ) > 10 ) {
			$logs = array_slice( $logs, -10 );
		}

		update_option( 'scd_activation_logs', $logs );
	}

	/**
	 * Check if this is a fresh installation or an upgrade.
	 *
	 * @since    1.0.0
	 * @return   bool    True if fresh installation, false if upgrade.
	 */
	private static function is_fresh_installation(): bool {
		return ! get_option( 'scd_version' );
	}

	/**
	 * Handle plugin upgrade.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private static function handle_upgrade() {
		$current_version = get_option( 'scd_version', '0.0.0' );

		if ( version_compare( $current_version, SCD_VERSION, '<' ) ) {
			// Run upgrade procedures
			self::run_upgrade_procedures( $current_version );

			update_option( 'scd_version', SCD_VERSION );
		}
	}

	/**
	 * Run upgrade procedures.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $from_version    The version being upgraded from.
	 */
	private static function run_upgrade_procedures( string $from_version ) {
		// Example upgrade procedures
		if ( version_compare( $from_version, '1.0.0', '<' ) ) {
			// Upgrade to 1.0.0
			self::upgrade_to_1_0_0();
		}
	}

	/**
	 * Upgrade to version 1.0.0.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private static function upgrade_to_1_0_0() {
		// Perform any necessary upgrades for version 1.0.0
		// This is a placeholder for future upgrade logic
	}

	/**
	 * Set activation redirect transient.
	 *
	 * Sets a transient to trigger redirect to dashboard on first activation.
	 * Prevents redirect during bulk activations and reactivations.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private static function set_activation_redirect() {
		// Skip redirect if activating multiple plugins at once.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Core WordPress parameter during activation, no nonce available.
		if ( isset( $_GET['activate-multi'] ) ) {
			return;
		}

		// Only redirect on first activation (not reactivations).
		$first_activation = get_option( 'scd_first_activation_done' );

		if ( ! $first_activation ) {
			set_transient( 'scd_activation_redirect', true, 30 );

			// Mark that first activation has occurred.
			add_option( 'scd_first_activation_done', true );
		}
	}

	/**
	 * Clear license and feature gate caches.
	 *
	 * Ensures fresh license validation after plugin updates/reactivations.
	 * Prevents stale cached data from causing feature access issues.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private static function clear_license_caches() {
		// Clear License Manager caches
		delete_option( 'scd_license_validation_cache' );
		delete_option( 'scd_license_last_check' );

		// Clear transient-based caches
		delete_transient( 'scd_license_status' );
		delete_transient( 'scd_feature_gate_cache' );
	}
}
