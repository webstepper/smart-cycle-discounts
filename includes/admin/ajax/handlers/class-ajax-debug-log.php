<?php
/**
 * Ajax Debug Log Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers/class-ajax-debug-log.php
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * AJAX Debug Log Handler Class
 *
 * Handles client-side debug log submissions when SCD_DEBUG is enabled.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax
 */
class SCD_Ajax_Debug_Log extends SCD_Abstract_Ajax_Handler {

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
		return 'scd_ajax_debug_log';
	}

	/**
	 * Handle the AJAX request.
	 *
	 * @since    1.0.0
	 * @param    array $request    Request data.
	 * @return   array                Response data.
	 */
	protected function handle( $request ) {
		// Only process if debugging is enabled
		if ( ! defined( 'SCD_DEBUG' ) || ! SCD_DEBUG ) {
			return $this->error(
				__( 'Debug logging is disabled', 'smart-cycle-discounts' ),
				'debug_disabled'
			);
		}

		// Get logs from request
		$logs_json = isset( $request['logs'] ) ? $request['logs'] : '';
		if ( empty( $logs_json ) ) {
			return $this->error(
				__( 'No logs provided', 'smart-cycle-discounts' ),
				'no_logs_provided'
			);
		}

		// Decode logs
		$logs = json_decode( stripslashes( $logs_json ), true );
		if ( ! is_array( $logs ) ) {
			return $this->error(
				__( 'Invalid log format', 'smart-cycle-discounts' ),
				'invalid_log_format'
			);
		}

		// Get debug logger
		$debug_logger = function_exists( 'scd_debug' ) ? scd_debug() : null;
		if ( ! $debug_logger ) {
			return $this->error(
				__( 'Debug logger not available', 'smart-cycle-discounts' ),
				'logger_unavailable'
			);
		}

		// Process each log entry
		$processed = 0;
		foreach ( $logs as $log ) {
			if ( ! is_array( $log ) ) {
				continue;
			}

			$type       = isset( $log['type'] ) ? $log['type'] : 'unknown';
			$message    = isset( $log['message'] ) ? $log['message'] : '';
			$data       = isset( $log['data'] ) ? $log['data'] : array();
			$timestamp  = isset( $log['timestamp'] ) ? $log['timestamp'] : '';
			$elapsed    = isset( $log['elapsed'] ) ? $log['elapsed'] : 0;
			$request_id = isset( $log['request_id'] ) ? $log['request_id'] : '';

			// Add metadata to context
			$context = array_merge(
				$data,
				array(
					'source'        => 'javascript',
					'js_timestamp'  => $timestamp,
					'js_elapsed_ms' => $elapsed,
					'js_request_id' => $request_id,
				)
			);

			// Log based on type
			switch ( $type ) {
				case 'error':
					$debug_logger->error( '[JS] ' . $message, $context );
					break;

				case 'warning':
					$debug_logger->warning( '[JS] ' . $message, $context );
					break;

				case 'wizard':
					if ( function_exists( 'scd_debug_wizard' ) ) {
						$step   = isset( $data['step'] ) ? $data['step'] : 'unknown';
						$action = isset( $data['action'] ) ? $data['action'] : 'unknown';
						scd_debug_wizard( $step, array_merge( array( 'js_action' => $action ), $context ) );
					} else {
						$debug_logger->info( '[JS] ' . $message, $context );
					}
					break;

				case 'navigation':
					if ( function_exists( 'scd_debug_navigation' ) ) {
						$from_step  = isset( $data['from_step'] ) ? $data['from_step'] : '';
						$to_step    = isset( $data['to_step'] ) ? $data['to_step'] : '';
						$nav_action = isset( $data['nav_action'] ) ? $data['nav_action'] : '';
						scd_debug_navigation( $from_step, $to_step, $nav_action, $context );
					} else {
						$debug_logger->info( '[JS] ' . $message, $context );
					}
					break;

				case 'ajax_request':
				case 'ajax_response':
					// Already logged server-side, but add JS perspective
					$debug_logger->debug( '[JS] ' . $message, $context );
					break;

				case 'validation':
					if ( function_exists( 'scd_debug_validation' ) ) {
						$step   = isset( $data['step'] ) ? $data['step'] : '';
						$fields = isset( $data['fields'] ) ? $data['fields'] : array();
						$valid  = isset( $data['valid'] ) ? $data['valid'] : false;
						$errors = isset( $data['errors'] ) ? $data['errors'] : array();
						scd_debug_validation( $step, array( 'fields' => $fields ), array(), $valid, $errors );
					} else {
						$debug_logger->info( '[JS] ' . $message, $context );
					}
					break;

				case 'interaction':
					if ( function_exists( 'scd_debug_interaction' ) ) {
						$interaction = isset( $data['interaction'] ) ? $data['interaction'] : '';
						$element     = isset( $data['element'] ) ? $data['element'] : '';
						$value       = isset( $data['value'] ) ? $data['value'] : null;
						scd_debug_interaction( $interaction, $element, $value, $context );
					} else {
						$debug_logger->debug( '[JS] ' . $message, $context );
					}
					break;

				case 'performance':
					if ( function_exists( 'scd_debug_performance' ) ) {
						$operation      = isset( $data['operation'] ) ? $data['operation'] : '';
						$duration_value = isset( $data['duration'] ) ? $data['duration'] : 0;
						$duration       = $duration_value / 1000; // Convert to seconds
						scd_debug_performance( $operation, $duration, $context );
					} else {
						$debug_logger->info( '[JS] ' . $message, $context );
					}
					break;

				default:
					$debug_logger->info( '[JS] ' . $message, $context );
					break;
			}

			++$processed;
		}

		return $this->success(
			array(
				'processed' => $processed,
				'message'   => sprintf(
				/* translators: %d: number of log entries processed */
					__( 'Processed %d log entries', 'smart-cycle-discounts' ),
					$processed
				),
			)
		);
	}
}
