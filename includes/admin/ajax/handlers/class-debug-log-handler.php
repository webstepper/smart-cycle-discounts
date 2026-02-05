<?php
/**
 * Debug Log Handler Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers/class-debug-log-handler.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress/plugins/smart-cycle-discounts/
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
class WSSCD_Debug_Log_Handler {

	/**
	 * Handle debug log write request
	 *
	 * @since    1.0.0
	 * @param    array $request    Request data
	 * @return   array                Response data
	 */
	public function handle( $request = array() ) {
		// Security Note: Nonce and capability verification is handled by AJAX Router
		// via WSSCD_Ajax_Security::verify_ajax_request() before this handler is called.
		// See: class-ajax-router.php lines 174-190 and class-ajax-security.php

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
	 * Get the log directory path in uploads.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   string    Log directory path.
	 */
	private function get_log_directory() {
		$upload_dir = wp_upload_dir();
		return $upload_dir['basedir'] . '/smart-cycle-discounts/logs';
	}

	/**
	 * Ensure log directory exists.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   bool    True if directory exists or was created.
	 */
	private function ensure_log_directory() {
		$log_dir = $this->get_log_directory();

		if ( ! file_exists( $log_dir ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Creating plugin's own log directory.
			wp_mkdir_p( $log_dir );

			// Add .htaccess to protect log files.
			$htaccess = $log_dir . '/.htaccess';
			if ( ! file_exists( $htaccess ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Creating protective .htaccess file.
				file_put_contents( $htaccess, 'deny from all' );
			}

			// Add index.php to prevent directory listing.
			$index = $log_dir . '/index.php';
			if ( ! file_exists( $index ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Creating protective index.php file.
				file_put_contents( $index, '<?php // Silence is golden.' );
			}
		}

		return is_dir( $log_dir );
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
		// Ensure log directory exists in uploads folder (not plugin folder).
		$this->ensure_log_directory();
		$log_file = $this->get_log_directory() . '/debug.log';

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
	 * Get the static log file path.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   string    Log file path.
	 */
	private static function get_static_log_path() {
		$upload_dir = wp_upload_dir();
		return $upload_dir['basedir'] . '/smart-cycle-discounts/logs/debug.log';
	}

	/**
	 * Clear debug log file
	 *
	 * @since    1.0.0
	 * @return   true|WP_Error     True on success, WP_Error on failure
	 */
	public static function clear_log() {
		$log_file = self::get_static_log_path();

		if ( ! file_exists( $log_file ) ) {
			return true;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Deleting plugin's own log file.
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
		$log_file = self::get_static_log_path();

		if ( ! file_exists( $log_file ) ) {
			return '';
		}

		if ( 0 === $lines ) {
			// Read entire file.
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading plugin's own log file.
			return file_get_contents( $log_file );
		}

		// Read last N lines.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file -- Reading plugin's own log file.
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
