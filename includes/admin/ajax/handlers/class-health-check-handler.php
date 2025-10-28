<?php
/**
 * Health Check AJAX Handler
 *
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax
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
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Health_Check_Handler extends SCD_Abstract_Ajax_Handler {

	/**
	 * Constructor.
	 *
	 * @since    1.0.0
	 * @param    SCD_Logger $logger    Logger instance (optional).
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
		return 'scd_health_check';
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
		$results[]  = array(
			'test'    => 'WordPress Version',
			'status'  => 'pass',
			'message' => $wp_version,
		);

		// WooCommerce version
		$wc_version   = defined( 'WC_VERSION' ) ? WC_VERSION : 'Not installed';
		$wc_installed = defined( 'WC_VERSION' );
		$results[]    = array(
			'test'    => 'WooCommerce Version',
			'status'  => $wc_installed ? 'pass' : 'fail',
			'message' => $wc_version,
		);
		if ( ! $wc_installed ) {
			$failed_tests[] = 'WooCommerce';
		}

		// Memory limit check
		$memory_limit = ini_get( 'memory_limit' );
		$memory_usage = size_format( memory_get_usage( true ) );
		$results[]    = array(
			'test'    => 'PHP Memory',
			'status'  => 'pass',
			'message' => $memory_usage . ' / ' . $memory_limit,
		);

		// Plugin version
		$results[] = array(
			'test'    => 'Plugin Version',
			'status'  => 'pass',
			'message' => SCD_VERSION,
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
				'plugin_version'  => SCD_VERSION,
				'_start_time'     => $start_time,
				'_include_memory' => true,
			)
		);

		return array(
			'results' => $results,
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
}
