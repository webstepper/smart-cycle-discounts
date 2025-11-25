<?php
/**
 * Abstract Class Ajax Handler
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/abstract-class-ajax-handler.php
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
 * Abstract AJAX Handler Class
 *
 * Provides a consistent foundation for all AJAX request handlers
 * with built-in security, logging, and error handling.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax
 * @author     Webstepper <contact@webstepper.io>
 */
abstract class SCD_Abstract_Ajax_Handler {

	/**
	 * Logger instance.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      SCD_Logger    $logger    Logger instance.
	 */
	protected $logger;

	/**
	 * Initialize the handler.
	 *
	 * @since    1.0.0
	 * @param    SCD_Logger $logger    Logger instance (optional).
	 */
	public function __construct( $logger = null ) {
		$this->logger = $logger;

		if ( null === $this->logger && class_exists( 'SCD_Logger' ) ) {
			$this->logger = new SCD_Logger( 'ajax' );
		}
	}

	/**
	 * Execute the AJAX request with security and error handling.
	 *
	 * This is the main entry point that wraps the handle() method
	 * with security checks and exception handling.
	 *
	 * @since    1.0.0
	 * @param    array $request    Request data ($_POST or $_GET).
	 * @return   array                Response array.
	 */
	final public function execute( $request = array() ) {
		try {
			// Verify security (nonce + capability)
			$verification = $this->verify_request( $request );
			if ( is_wp_error( $verification ) ) {
				return $this->handle_wp_error( $verification );
			}

			// Execute handler logic
			$result = $this->handle( $request );

			// Handle WP_Error returns from handle() FIRST before any conversion
			if ( is_wp_error( $result ) ) {
				return $this->handle_wp_error( $result );
			}

			// Ensure result is an array (for non-error responses)
			if ( ! is_array( $result ) ) {
				$result = array();
			}

			// Ensure success flag is set if not explicitly defined
			if ( ! isset( $result['success'] ) ) {
				$result['success'] = true;
			}

			return $result;

		} catch ( Exception $e ) {
			return $this->handle_exception( $e );
		}
	}

	/**
	 * Handle the AJAX request.
	 *
	 * Subclasses must implement this method with their specific logic.
	 *
	 * @since    1.0.0
	 * @param    array $request    Request data.
	 * @return   array                Response array.
	 */
	abstract protected function handle( $request );

	/**
	 * Get the AJAX action name for this handler.
	 *
	 * Used for security verification via SCD_Ajax_Security.
	 *
	 * @since    1.0.0
	 * @return   string    Action name (e.g., 'scd_save_step').
	 */
	abstract protected function get_action_name();

	/**
	 * Verify request security.
	 *
	 * Uses centralized SCD_Ajax_Security to verify:
	 * - Nonce validity
	 * - User capability
	 * - Rate limiting
	 * - Request size
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @param    array $request    Request data.
	 * @return   true|WP_Error              True if valid, WP_Error otherwise.
	 */
	protected function verify_request( $request ) {
		// Ensure SCD_Ajax_Security is loaded
		if ( ! class_exists( 'SCD_Ajax_Security' ) ) {
			return new WP_Error(
				'security_unavailable',
				__( 'Security verification unavailable', 'smart-cycle-discounts' ),
				array( 'status' => 500 )
			);
		}

		$action = $this->get_action_name();

		// Use centralized security verification
		$result = SCD_Ajax_Security::verify_ajax_request( $action, $request );

		if ( is_wp_error( $result ) ) {
			$this->log_security_failure( $action, $result );
		}

		return $result;
	}

	/**
	 * Return success response.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @param    array $data    Response data.
	 * @return   array             Success response array.
	 */
	protected function success( $data = array() ) {
		return array(
			'success' => true,
			'data'    => $data,
		);
	}

	/**
	 * Return error response.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @param    string $message    Error message.
	 * @param    string $code       Error code.
	 * @param    int    $status     HTTP status code.
	 * @return   array                 Error response array.
	 */
	protected function error( $message, $code = 'error', $status = 400 ) {
		// Log error
		if ( $this->logger ) {
			$this->logger->error(
				$message,
				array(
					'code'   => $code,
					'status' => $status,
					'action' => $this->get_action_name(),
				)
			);
		}

		return array(
			'success' => false,
			'error'   => array(
				'code'    => $code,
				'message' => $message,
			),
			'data'    => array(
				'status' => $status,
			),
		);
	}

	/**
	 * Handle WP_Error objects.
	 *
	 * Converts WP_Error to standardized error response format.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @param    WP_Error $error    WP_Error object.
	 * @return   array                 Error response array.
	 */
	protected function handle_wp_error( $error ) {
		if ( ! is_wp_error( $error ) ) {
			return $this->error( __( 'Unknown error', 'smart-cycle-discounts' ) );
		}

		$error_data = $error->get_error_data();
		$status     = 400;

		if ( is_array( $error_data ) && isset( $error_data['status'] ) ) {
			$status = $error_data['status'];
		}

		// Log error
		if ( $this->logger ) {
			$this->logger->error(
				$error->get_error_message(),
				array(
					'code'       => $error->get_error_code(),
					'status'     => $status,
					'action'     => $this->get_action_name(),
					'error_data' => $error_data,
				)
			);
		}

		return array(
			'success' => false,
			'error'   => array(
				'code'    => $error->get_error_code(),
				'message' => $error->get_error_message(),
			),
			'data'    => is_array( $error_data ) ? $error_data : array( 'status' => $status ),
		);
	}

	/**
	 * Handle exceptions.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @param    Exception $e    Exception object.
	 * @return   array              Error response array.
	 */
	protected function handle_exception( $e ) {
		// Log exception
		if ( $this->logger ) {
			$this->logger->critical(
				'Handler exception: ' . $e->getMessage(),
				array(
					'exception' => get_class( $e ),
					'file'      => $e->getFile(),
					'line'      => $e->getLine(),
					'trace'     => $e->getTraceAsString(),
					'action'    => $this->get_action_name(),
				)
			);
		}

		// Generic error message for users (don't expose internal details)
		$message = __( 'An error occurred while processing your request. Please try again.', 'smart-cycle-discounts' );

		$response = array(
			'success' => false,
			'error'   => array(
				'code'    => 'internal_error',
				'message' => $message,
			),
			'data'    => array(
				'status' => 500,
			),
		);

		// Include exception details in debug mode
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$response['debug'] = array(
				'exception' => get_class( $e ),
				'message'   => $e->getMessage(),
				'file'      => $e->getFile(),
				'line'      => $e->getLine(),
			);
		}

		return $response;
	}

	/**
	 * Log security failure.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string   $action    Action name.
	 * @param    WP_Error $error     Error object.
	 * @return   void
	 */
	private function log_security_failure( $action, $error ) {
		if ( ! $this->logger ) {
			return;
		}

		$this->logger->warning(
			'Security verification failed',
			array(
				'action'     => $action,
				'error_code' => $error->get_error_code(),
				'error_msg'  => $error->get_error_message(),
				'user_id'    => get_current_user_id(),
				'ip'         => SCD_Ajax_Security::get_client_ip(),
			)
		);
	}

	/**
	 * Sanitize text field.
	 *
	 * Helper method for common sanitization.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @param    mixed  $value      Value to sanitize.
	 * @param    string $default    Default value if empty.
	 * @return   string                Sanitized value.
	 */
	protected function sanitize_text( $value, $default = '' ) {
		return isset( $value ) ? sanitize_text_field( $value ) : $default;
	}

	/**
	 * Sanitize integer.
	 *
	 * Helper method for common sanitization.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @param    mixed $value      Value to sanitize.
	 * @param    int   $default    Default value if empty.
	 * @return   int                  Sanitized integer.
	 */
	protected function sanitize_int( $value, $default = 0 ) {
		return isset( $value ) ? absint( $value ) : $default;
	}

	/**
	 * Sanitize array of integers.
	 *
	 * Helper method for product IDs, etc.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @param    mixed $value    Value to sanitize.
	 * @return   array              Array of integers.
	 */
	protected function sanitize_int_array( $value ) {
		if ( ! is_array( $value ) ) {
			if ( is_string( $value ) ) {
				$value = explode( ',', $value );
			} else {
				$value = array( $value );
			}
		}

		$value = array_map( 'absint', $value );
		$value = $this->filter_valid_ids( $value );

		return array_values( $value );
	}

	/**
	 * Filter valid IDs from array.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $ids    Array of IDs to filter.
	 * @return   array            Filtered array of valid IDs.
	 */
	private function filter_valid_ids( $ids ) {
		$filtered = array();
		foreach ( $ids as $id ) {
			if ( $id > 0 && $id <= PHP_INT_MAX ) {
				$filtered[] = $id;
			}
		}
		return $filtered;
	}

	/**
	 * Sanitize boolean.
	 *
	 * Helper method for common sanitization.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @param    mixed $value      Value to sanitize.
	 * @param    bool  $default    Default value if not set.
	 * @return   bool                 Boolean value.
	 */
	protected function sanitize_bool( $value, $default = false ) {
		if ( ! isset( $value ) ) {
			return $default;
		}

		return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
	}

	/**
	 * Get request parameter with default.
	 *
	 * Helper method to safely get request parameters.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @param    array  $request    Request array.
	 * @param    string $key        Parameter key.
	 * @param    mixed  $default    Default value.
	 * @return   mixed                 Parameter value or default.
	 */
	protected function get_param( $request, $key, $default = null ) {
		return isset( $request[ $key ] ) ? $request[ $key ] : $default;
	}

	/**
	 * Validate required parameters.
	 *
	 * Helper method to validate that required parameters are present.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @param    array $request     Request array.
	 * @param    array $required    Required parameter keys.
	 * @return   true|WP_Error         True if valid, WP_Error otherwise.
	 */
	protected function validate_required_params( $request, $required ) {
		$missing = array();

		foreach ( $required as $param ) {
			if ( ! isset( $request[ $param ] ) || '' === $request[ $param ] ) {
				$missing[] = $param;
			}
		}

		if ( ! empty( $missing ) ) {
			return new WP_Error(
				'missing_parameters',
				sprintf(
					/* translators: %s: comma-separated list of missing parameters */
					__( 'Missing required parameters: %s', 'smart-cycle-discounts' ),
					implode( ', ', $missing )
				),
				array(
					'status'  => 400,
					'missing' => $missing,
				)
			);
		}

		return true;
	}

	/**
	 * Log debug message.
	 *
	 * Helper method for debug logging.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @param    string $message    Log message.
	 * @param    array  $context    Log context.
	 * @return   void
	 */
	protected function log_debug( $message, $context = array() ) {
		if ( $this->logger && ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ) {
			$context['action'] = $this->get_action_name();
			$this->logger->debug( $message, $context );
		}
	}

	/**
	 * Log info message.
	 *
	 * Helper method for info logging.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @param    string $message    Log message.
	 * @param    array  $context    Log context.
	 * @return   void
	 */
	protected function log_info( $message, $context = array() ) {
		if ( $this->logger ) {
			$context['action'] = $this->get_action_name();
			$this->logger->info( $message, $context );
		}
	}

	/**
	 * Log warning message.
	 *
	 * Helper method for warning logging.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @param    string $message    Log message.
	 * @param    array  $context    Log context.
	 * @return   void
	 */
	protected function log_warning( $message, $context = array() ) {
		if ( $this->logger ) {
			$context['action'] = $this->get_action_name();
			$this->logger->warning( $message, $context );
		}
	}

	/**
	 * Log error message.
	 *
	 * Helper method for error logging.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @param    string $message    Log message.
	 * @param    array  $context    Log context.
	 * @return   void
	 */
	protected function log_error( $message, $context = array() ) {
		if ( $this->logger ) {
			$context['action'] = $this->get_action_name();
			$this->logger->error( $message, $context );
		}
	}
}
