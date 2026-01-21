<?php
/**
 * Health Check Handler Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers/class-health-check-handler.php
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
 * Health Check Handler
 *
 * Handles health check requests for server status monitoring.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax
 * @author     Webstepper <contact@webstepper.io>
 */
class WSSCD_Health_Check_Handler extends WSSCD_Abstract_Ajax_Handler {

	/**
	 * Constructor.
	 *
	 * @since    1.0.0
	 * @param    WSSCD_Logger $logger    Logger instance (optional).
	 */
	public function __construct( $logger = null ) {
		parent::__construct( $logger );
	}

	/**
	 * Get AJAX action name.
	 *
	 * @since    1.0.0
	 * @return   string    Action name.
	 */
	protected function get_action_name() {
		return 'wsscd_health_check';
	}

	/**
	 * Handle health check request.
	 *
	 * @since    1.0.0
	 * @param    array $request    Request data.
	 * @return   array                Health check response.
	 */
	protected function handle( $request ) {
		$start_time = microtime( true );

		// Log health check start
		$this->logger->flow(
			'info',
			'AJAX START',
			'Processing health check',
			array(
				'user_id' => get_current_user_id(),
			)
		);

		$results      = array();
		$failed_tests = array();

		// Database connection check
		$db_connected = $this->check_database_connection();
		$results[]    = array(
			'test'    => 'Database Connection',
			'status'  => $db_connected ? 'pass' : 'fail',
			'message' => $db_connected ? 'Connected successfully' : 'Connection failed',
		);
		if ( ! $db_connected ) {
			$failed_tests[] = 'Database Connection';
		}

		// Plugin tables check
		$tables_check = $this->check_plugin_tables();
		$results[]    = array(
			'test'    => 'Plugin Tables',
			'status'  => $tables_check['status'],
			'message' => $tables_check['message'],
		);
		if ( 'fail' === $tables_check['status'] ) {
			$failed_tests[] = 'Plugin Tables';
		}

		// PHP version check
		$php_version = PHP_VERSION;
		$php_ok      = version_compare( $php_version, '7.4', '>=' );
		$results[]   = array(
			'test'    => 'PHP Version',
			'status'  => $php_ok ? 'pass' : 'warning',
			'message' => 'PHP ' . $php_version . ( $php_ok ? ' (OK)' : ' (Upgrade recommended)' ),
		);
		if ( ! $php_ok ) {
			$failed_tests[] = 'PHP Version';
		}

		// WordPress version
		$wp_version = get_bloginfo( 'version' );
		$wp_ok      = version_compare( $wp_version, '6.0', '>=' );
		$results[]  = array(
			'test'    => 'WordPress Version',
			'status'  => $wp_ok ? 'pass' : 'warning',
			'message' => $wp_version . ( $wp_ok ? '' : ' (6.0+ recommended)' ),
		);

		// WooCommerce version
		$wc_version   = defined( 'WC_VERSION' ) ? WC_VERSION : 'Not installed';
		$wc_installed = defined( 'WC_VERSION' );
		$wc_ok        = $wc_installed && version_compare( WC_VERSION, '7.0', '>=' );
		$results[]    = array(
			'test'    => 'WooCommerce Version',
			'status'  => $wc_installed ? ( $wc_ok ? 'pass' : 'warning' ) : 'fail',
			'message' => $wc_version . ( $wc_installed && ! $wc_ok ? ' (7.0+ recommended)' : '' ),
		);
		if ( ! $wc_installed ) {
			$failed_tests[] = 'WooCommerce';
		}

		// Memory limit check
		$memory_limit    = ini_get( 'memory_limit' );
		$memory_bytes    = wp_convert_hr_to_bytes( $memory_limit );
		$memory_ok       = $memory_bytes >= 67108864; // 64MB minimum
		$memory_usage    = size_format( memory_get_usage( true ) );
		$results[]       = array(
			'test'    => 'PHP Memory',
			'status'  => $memory_ok ? 'pass' : 'warning',
			'message' => $memory_usage . ' / ' . $memory_limit . ( $memory_ok ? '' : ' (64MB+ recommended)' ),
		);

		// Cache status
		$cache_check = $this->check_cache_status();
		$results[]   = array(
			'test'    => 'Cache Status',
			'status'  => $cache_check['status'],
			'message' => $cache_check['message'],
		);

		// Log directory writable
		$log_check = $this->check_log_directory();
		$results[] = array(
			'test'    => 'Log Directory',
			'status'  => $log_check['status'],
			'message' => $log_check['message'],
		);
		if ( 'fail' === $log_check['status'] ) {
			$failed_tests[] = 'Log Directory';
		}

		// Plugin version
		$results[] = array(
			'test'    => 'Plugin Version',
			'status'  => 'pass',
			'message' => WSSCD_VERSION,
		);

		// Log health check results
		$log_level = empty( $failed_tests ) ? 'info' : 'warning';
		$this->logger->flow(
			$log_level,
			'AJAX SUCCESS',
			'Health check completed',
			array(
				'total_tests'     => count( $results ),
				'failed_tests'    => $failed_tests,
				'php_version'     => $php_version,
				'wp_version'      => $wp_version,
				'wc_version'      => $wc_version,
				'plugin_version'  => WSSCD_VERSION,
				'_start_time'     => $start_time,
				'_include_memory' => true,
			)
		);

		return $this->success(
			array(
				'results' => $results,
			)
		);
	}

	/**
	 * Check database connection.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   bool    True if connected, false otherwise.
	 */
	private function check_database_connection() {
		global $wpdb;

		try {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching , PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Database connectivity test; caching defeats purpose.
			$result    = $wpdb->get_var( 'SELECT 1' );
			$connected = '1' === $result;

			if ( ! $connected ) {
				$this->logger->flow(
					'error',
					'DB ERROR',
					'Database connection test failed',
					array(
						'result'     => $result,
						'last_error' => $wpdb->last_error,
					)
				);
			}

			return $connected;
		} catch ( Exception $e ) {
			$this->logger->flow(
				'error',
				'DB ERROR',
				'Database connection exception',
				array(
					'error' => $e->getMessage(),
					'file'  => $e->getFile(),
					'line'  => $e->getLine(),
				)
			);
			return false;
		}
	}

	/**
	 * Check if all plugin tables exist.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   array    Status and message.
	 */
	private function check_plugin_tables() {
		global $wpdb;

		$required_tables = array(
			'wsscd_campaigns',
			'wsscd_campaign_conditions',
			'wsscd_active_discounts',
		);

		$missing_tables = array();
		foreach ( $required_tables as $table ) {
			$full_table = $wpdb->prefix . $table;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching , PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- SHOW TABLES has no WP abstraction; ephemeral check.
			$exists     = $wpdb->get_var(
				$wpdb->prepare( 'SHOW TABLES LIKE %s', $full_table )
			);

			if ( $exists !== $full_table ) {
				$missing_tables[] = $table;
			}
		}

		if ( empty( $missing_tables ) ) {
			return array(
				'status'  => 'pass',
				'message' => count( $required_tables ) . ' tables found',
			);
		}

		return array(
			'status'  => 'fail',
			'message' => 'Missing: ' . implode( ', ', $missing_tables ),
		);
	}

	/**
	 * Check cache status.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   array    Status and message.
	 */
	private function check_cache_status() {
		$cache_manager = Smart_Cycle_Discounts::get_service( 'cache_manager' );

		if ( ! $cache_manager ) {
			return array(
				'status'  => 'warning',
				'message' => 'Cache manager not available',
			);
		}

		$stats = method_exists( $cache_manager, 'get_stats' ) ? $cache_manager->get_stats() : array();

		$object_cache = isset( $stats['object_cache_available'] ) && $stats['object_cache_available'];
		$transients   = isset( $stats['transient_count'] ) ? $stats['transient_count'] : 0;

		if ( $object_cache ) {
			return array(
				'status'  => 'pass',
				'message' => 'Object cache active, ' . $transients . ' transients',
			);
		}

		return array(
			'status'  => 'pass',
			'message' => $transients . ' transients (no object cache)',
		);
	}

	/**
	 * Check log directory is writable.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   array    Status and message.
	 */
	private function check_log_directory() {
		$upload_dir = wp_upload_dir();
		$log_dir    = $upload_dir['basedir'] . '/smart-cycle-discounts/logs';

		if ( ! file_exists( $log_dir ) ) {
			// Try to create it
			if ( wp_mkdir_p( $log_dir ) ) {
				return array(
					'status'  => 'pass',
					'message' => 'Directory created successfully',
				);
			}

			return array(
				'status'  => 'fail',
				'message' => 'Cannot create log directory',
			);
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- Simple permission check for log directory.
		if ( is_writable( $log_dir ) ) {
			return array(
				'status'  => 'pass',
				'message' => 'Writable',
			);
		}

		return array(
			'status'  => 'fail',
			'message' => 'Directory not writable',
		);
	}
}
