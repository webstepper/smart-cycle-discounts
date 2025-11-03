<?php
/**
 * Scd Debug Functions
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/utilities/scd-debug-functions.php
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


if ( ! function_exists( 'scd_debug' ) ) {
	/**
	 * Get the debug logger instance.
	 *
	 * @since    1.0.0
	 * @return   SCD_Debug_Logger|null    Debug logger instance or null if not available.
	 */
	function scd_debug() {
		static $debug_logger = null;

		// Only initialize if debugging is enabled
		if ( ! SCD_DEBUG ) {
			return null;
		}

		if ( null === $debug_logger ) {
			// Try to get from container first
			$container = class_exists( 'Smart_Cycle_Discounts' ) ? Smart_Cycle_Discounts::get_container_static() : null;
			if ( $container && method_exists( $container, 'get' ) && method_exists( $container, 'has' ) && $container->has( 'debug_logger' ) ) {
				try {
					$debug_logger = $container->get( 'debug_logger' );
				} catch ( Exception $e ) {
					// Container not ready, create directly
					// Ensure base logger class is loaded first
					if ( ! class_exists( 'SCD_Logger' ) ) {
						require_once SCD_INCLUDES_DIR . 'utilities/class-logger.php';
					}
					if ( ! class_exists( 'SCD_Debug_Logger' ) ) {
						require_once SCD_INCLUDES_DIR . 'utilities/class-debug-logger.php';
					}
					$debug_logger = new SCD_Debug_Logger();
				}
			} else {
				// Container not available, create directly
				// Ensure base logger class is loaded first
				if ( ! class_exists( 'SCD_Logger' ) ) {
					require_once SCD_INCLUDES_DIR . 'utilities/class-logger.php';
				}
				if ( ! class_exists( 'SCD_Debug_Logger' ) ) {
					require_once SCD_INCLUDES_DIR . 'utilities/class-debug-logger.php';
				}
				$debug_logger = new SCD_Debug_Logger();
			}
		}

		return $debug_logger;
	}
}

if ( ! function_exists( 'scd_debug_wizard' ) ) {
	/**
	 * Log wizard initialization.
	 *
	 * @since    1.0.0
	 * @param    string $step         Current step.
	 * @param    array  $init_data    Initialization data.
	 * @return   void
	 */
	function scd_debug_wizard( string $step, array $init_data = array() ): void {
		$logger = scd_debug();
		if ( $logger ) {
			$logger->log_wizard_init( $step, $init_data );
		}
	}
}

if ( ! function_exists( 'scd_debug_navigation' ) ) {
	/**
	 * Log wizard navigation.
	 *
	 * @since    1.0.0
	 * @param    string $from_step    Source step.
	 * @param    string $to_step      Target step.
	 * @param    string $action       Navigation action.
	 * @param    array  $context      Additional context.
	 * @return   void
	 */
	function scd_debug_navigation( string $from_step, string $to_step, string $action, array $context = array() ): void {
		$logger = scd_debug();
		if ( $logger ) {
			$logger->log_navigation( $from_step, $to_step, $action, $context );
		}
	}
}

if ( ! function_exists( 'scd_debug_ajax' ) ) {
	/**
	 * Log AJAX request.
	 *
	 * @since    1.0.0
	 * @param    string $action       AJAX action.
	 * @param    array  $params       Request parameters.
	 * @param    string $nonce        Nonce value.
	 * @return   void
	 */
	function scd_debug_ajax( string $action, array $params = array(), string $nonce = '' ): void {
		$logger = scd_debug();
		if ( $logger ) {
			$logger->log_ajax_request( $action, $params, $nonce );
		}
	}
}

if ( ! function_exists( 'scd_debug_ajax_response' ) ) {
	/**
	 * Log AJAX response.
	 *
	 * @since    1.0.0
	 * @param    string $action       AJAX action.
	 * @param    mixed  $response     Response data.
	 * @param    bool   $success      Success status.
	 * @param    float  $duration     Request duration.
	 * @return   void
	 */
	function scd_debug_ajax_response( string $action, $response, bool $success, float $duration ): void {
		$logger = scd_debug();
		if ( $logger ) {
			$logger->log_ajax_response( $action, $response, $success, $duration );
		}
	}
}

if ( ! function_exists( 'scd_debug_validation' ) ) {
	/**
	 * Log validation process.
	 *
	 * @since    1.0.0
	 * @param    string $step         Step being validated.
	 * @param    array  $data         Data being validated.
	 * @param    array  $rules        Validation rules applied.
	 * @param    bool   $valid        Validation result.
	 * @param    array  $errors       Validation errors.
	 * @return   void
	 */
	function scd_debug_validation( string $step, array $data, array $rules, bool $valid, array $errors = array() ): void {
		$logger = scd_debug();
		if ( $logger ) {
			$logger->log_validation( $step, $data, $rules, $valid, $errors );
		}
	}
}

if ( ! function_exists( 'scd_debug_sanitization' ) ) {
	/**
	 * Log sanitization process.
	 *
	 * @since    1.0.0
	 * @param    string $field        Field being sanitized.
	 * @param    mixed  $raw_value    Raw value.
	 * @param    mixed  $clean_value  Sanitized value.
	 * @param    string $method       Sanitization method.
	 * @return   void
	 */
	function scd_debug_sanitization( string $field, $raw_value, $clean_value, string $method ): void {
		$logger = scd_debug();
		if ( $logger ) {
			$logger->log_sanitization( $field, $raw_value, $clean_value, $method );
		}
	}
}

if ( ! function_exists( 'scd_debug_database' ) ) {
	/**
	 * Log database operation.
	 *
	 * @since    1.0.0
	 * @param    string $operation    Operation type.
	 * @param    string $table        Table name.
	 * @param    array  $data         Operation data.
	 * @param    mixed  $result       Operation result.
	 * @param    float  $duration     Operation duration.
	 * @return   void
	 */
	function scd_debug_database( string $operation, string $table, array $data, $result, float $duration ): void {
		$logger = scd_debug();
		if ( $logger ) {
			$logger->log_database_operation( $operation, $table, $data, $result, $duration );
		}
	}
}

if ( ! function_exists( 'scd_debug_persistence' ) ) {
	/**
	 * Log persistence operation.
	 *
	 * @since    1.0.0
	 * @param    string $action       Persistence action.
	 * @param    string $step         Step name.
	 * @param    array  $data         Data being persisted.
	 * @param    bool   $success      Success status.
	 * @param    string $message      Result message.
	 * @return   void
	 */
	function scd_debug_persistence( string $action, string $step, array $data, bool $success, string $message = '' ): void {
		$logger = scd_debug();
		if ( $logger ) {
			$logger->log_persistence( $action, $step, $data, $success, $message );
		}
	}
}

if ( ! function_exists( 'scd_debug_interaction' ) ) {
	/**
	 * Log user interaction.
	 *
	 * @since    1.0.0
	 * @param    string $interaction  Interaction type.
	 * @param    string $element      Element identifier.
	 * @param    mixed  $value        Interaction value.
	 * @param    array  $context      Additional context.
	 * @return   void
	 */
	function scd_debug_interaction( string $interaction, string $element, $value = null, array $context = array() ): void {
		$logger = scd_debug();
		if ( $logger ) {
			$logger->log_user_interaction( $interaction, $element, $value, $context );
		}
	}
}

if ( ! function_exists( 'scd_debug_error' ) ) {
	/**
	 * Log error with full context.
	 *
	 * @since    1.0.0
	 * @param    string     $message      Error message.
	 * @param    \Throwable $exception    Exception object.
	 * @param    array      $context      Additional context.
	 * @return   void
	 */
	function scd_debug_error( string $message, \Throwable $exception, array $context = array() ): void {
		$logger = scd_debug();
		if ( $logger ) {
			$logger->log_error_with_trace( $message, $exception, $context );
		}
	}
}

if ( ! function_exists( 'scd_debug_performance' ) ) {
	/**
	 * Log performance metrics.
	 *
	 * @since    1.0.0
	 * @param    string $operation    Operation name.
	 * @param    float  $duration     Duration in seconds.
	 * @param    array  $metrics      Additional metrics.
	 * @return   void
	 */
	function scd_debug_performance( string $operation, float $duration, array $metrics = array() ): void {
		$logger = scd_debug();
		if ( $logger ) {
			$logger->log_performance( $operation, $duration, $metrics );
		}
	}
}

if ( ! function_exists( 'scd_debug_end' ) ) {
	/**
	 * Log request end.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	function scd_debug_end(): void {
		$logger = scd_debug();
		if ( $logger ) {
			$logger->log_request_end();
		}
	}
}

// Register shutdown function to log request end
if ( SCD_DEBUG ) {
	register_shutdown_function( 'scd_debug_end' );
}
