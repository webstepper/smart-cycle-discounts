<?php
/**
 * Console Logger AJAX Handler
 *
 * Receives JavaScript console logs and writes them to debug.log
 *
 * @package SmartCycleDiscounts
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Console Logger Handler
 *
 * @since 1.0.0
 */
class SCD_Console_Logger_Handler extends SCD_Abstract_Ajax_Handler {

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
		return 'scd_console_logger';
	}

	/**
	 * Handle console log request
	 *
	 * @since 1.0.0
	 * @param array $request Request data from router
	 * @return array Response data
	 */
	protected function handle( $request ) {
		$logs = isset( $request['logs'] ) ? $request['logs'] : '';

		if ( empty( $logs ) ) {
			return $this->success(
				array(
					'logged' => 0,
				)
			);
		}

		$decoded_logs = json_decode( stripslashes( $logs ), true );

		if ( ! is_array( $decoded_logs ) ) {
			return $this->success(
				array(
					'logged' => 0,
					'error'  => __( 'Invalid log format', 'smart-cycle-discounts' ),
				)
			);
		}

		$logged_count = 0;
		foreach ( $decoded_logs as $log ) {
			$level     = isset( $log['level'] ) ? strtoupper( $log['level'] ) : 'LOG';
			$message   = isset( $log['message'] ) ? $log['message'] : '';
			$timestamp = isset( $log['timestamp'] ) ? $log['timestamp'] : '';

			error_log(
				sprintf(
					'[SCD JS %s] %s | %s',
					$level,
					$timestamp,
					$message
				)
			);
			++$logged_count;
		}

		return $this->success(
			array(
				'logged'  => $logged_count,
				'message' => sprintf(
				/* translators: %d: number of log entries */
					__( 'Logged %d console entries', 'smart-cycle-discounts' ),
					$logged_count
				),
			)
		);
	}
}
