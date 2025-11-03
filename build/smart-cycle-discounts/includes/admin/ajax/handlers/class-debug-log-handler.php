<?php
/**
 * Debug Log Handler Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers/class-debug-log-handler.php
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Debug Log Handler Class
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers
 */
class SCD_Debug_Log_Handler {

	/**
	 * Handle debug log write request
	 *
	 * @since    1.0.0
	 * @param    array $request    Request data
	 * @return   array                Response data
	 */
	public function handle( $request = array() ) {
		// Verify nonce
		if ( ! isset( $request['nonce'] ) || ! wp_verify_nonce( $request['nonce'], 'scd_wizard' ) ) {
			return array(
				'success' => false,
				'data'    => array(
					'message' => __( 'Security check failed', 'smart-cycle-discounts' ),
				),
			);
		}

		// Check user capability
		if ( ! current_user_can( 'manage_options' ) ) {
			return array(
				'success' => false,
				'data'    => array(
					'message' => __( 'Insufficient permissions', 'smart-cycle-discounts' ),
				),
			);
		}

		// Get logs from request
		$logs_json = isset( $request['logs'] ) ? $request['logs'] : '';
		if ( empty( $logs_json ) ) {
			return array(
				'success' => true,
				'data'    => array(
					'message' => __( 'No logs to write', 'smart-cycle-discounts' ),
				),
			);
		}

		// Decode logs
		$logs = json_decode( $logs_json, true );
		if ( ! is_array( $logs ) ) {
			return array(
				'success' => false,
				'data'    => array(
					'message' => __( 'Invalid log format', 'smart-cycle-discounts' ),
				),
			);
		}

		// Write logs to file
		$result = $this->write_logs( $logs );

		if ( is_wp_error( $result ) ) {
			return array(
				'success' => false,
				'data'    => array(
					'message' => $result->get_error_message(),
				),
			);
		}

		return array(
			'success' => true,
			'data'    => array(
				'message'      => __( 'Logs written successfully', 'smart-cycle-discounts' ),
				'logs_written' => count( $logs ),
			),
		);
	}

	/**
	 * Write logs to debug file
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $logs    Array of log entries
	 * @return   true|WP_Error     True on success, WP_Error on failure
	 */
	private function write_logs( $logs ) {
		// Get log file path
		$log_file = SCD_PLUGIN_DIR . 'debug.log';

		// Format log entries
		$log_content = '';
		foreach ( $logs as $log ) {
			$timestamp = isset( $log['timestamp'] ) ? $log['timestamp'] : gmdate( 'Y-m-d H:i:s' );
			$level     = isset( $log['level'] ) ? strtoupper( $log['level'] ) : 'INFO';
			$category  = isset( $log['category'] ) ? $log['category'] : 'General';
			$message   = isset( $log['message'] ) ? $log['message'] : '';
			$data      = isset( $log['data'] ) ? $log['data'] : null;

			$log_line = sprintf(
				'[%s] [%s] [%s] %s',
				$timestamp,
				str_pad( $level, 5 ),
				$category,
				$message
			);

			if ( null !== $data ) {
				$log_line .= ' | Data: ' . $data;
			}

			$log_content .= $log_line . "\n";
		}

		// Append to log file
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
		$result = file_put_contents( $log_file, $log_content, FILE_APPEND );

		if ( false === $result ) {
			return new WP_Error(
				'log_write_failed',
				sprintf(
					/* translators: %s: Log file path */
					__( 'Failed to write to log file: %s', 'smart-cycle-discounts' ),
					$log_file
				)
			);
		}

		return true;
	}

	/**
	 * Clear debug log file
	 *
	 * @since    1.0.0
	 * @return   true|WP_Error     True on success, WP_Error on failure
	 */
	public static function clear_log() {
		$log_file = SCD_PLUGIN_DIR . 'debug.log';

		if ( ! file_exists( $log_file ) ) {
			return true;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		$result = unlink( $log_file );

		if ( ! $result ) {
			return new WP_Error(
				'log_clear_failed',
				__( 'Failed to clear log file', 'smart-cycle-discounts' )
			);
		}

		return true;
	}

	/**
	 * Get debug log contents
	 *
	 * @since    1.0.0
	 * @param    int $lines    Number of lines to read (0 for all)
	 * @return   string|WP_Error   Log contents or error
	 */
	public static function get_log( $lines = 0 ) {
		$log_file = SCD_PLUGIN_DIR . 'debug.log';

		if ( ! file_exists( $log_file ) ) {
			return '';
		}

		if ( 0 === $lines ) {
			// Read entire file
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			return file_get_contents( $log_file );
		}

		// Read last N lines
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file
		$file_lines = file( $log_file );
		if ( false === $file_lines ) {
			return new WP_Error(
				'log_read_failed',
				__( 'Failed to read log file', 'smart-cycle-discounts' )
			);
		}

		return implode( '', array_slice( $file_lines, -$lines ) );
	}
}
