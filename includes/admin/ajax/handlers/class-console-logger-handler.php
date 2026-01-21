<?php
/**
 * Console Logger Handler Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers/class-console-logger-handler.php
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
 * Console Logger Handler
 *
 * @since 1.0.0
 */
class WSSCD_Console_Logger_Handler extends WSSCD_Abstract_Ajax_Handler {

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
		return 'wsscd_console_logger';
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

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional debug logging when WP_DEBUG is enabled.
				error_log(
					sprintf(
						'[WSSCD JS %s] %s | %s',
						$level,
						$timestamp,
						$message
					)
				);
			}
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
