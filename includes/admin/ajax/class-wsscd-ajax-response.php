<?php
/**
 * Scd Ajax Response Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/class-wsscd-ajax-response.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress/plugins/smart-cycle-discounts/
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// Removed strict types for PHP compatibility

/**
 * Secure AJAX Response Handler class.
 *
 * Provides standardized and secure AJAX response formatting
 * with proper sanitization and consistent error handling.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes
 * @author     Webstepper <contact@webstepper.io>
 */
class WSSCD_AJAX_Response {

	/**
	 * Get translated safe error messages.
	 *
	 * Messages are translated at call time to ensure proper text domain loading.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   array    Translated safe error messages.
	 */
	private static function get_safe_messages(): array {
		return array(
			'unauthorized'         => __( 'You do not have permission to perform this action.', 'smart-cycle-discounts' ),
			'invalid_nonce'        => __( 'Security verification failed. Please refresh and try again.', 'smart-cycle-discounts' ),
			'missing_parameter'    => __( 'Required information is missing. Please check your input.', 'smart-cycle-discounts' ),
			'invalid_parameter'    => __( 'Invalid data provided. Please check your input.', 'smart-cycle-discounts' ),
			'database_error'       => __( 'A database error occurred. Please try again later.', 'smart-cycle-discounts' ),
			'not_found'            => __( 'The requested item could not be found.', 'smart-cycle-discounts' ),
			'duplicate'            => __( 'This item already exists.', 'smart-cycle-discounts' ),
			'rate_limit'           => __( 'Too many requests. Please wait and try again.', 'smart-cycle-discounts' ),
			'rate_limited'         => __( 'You have reached the Cycle AI usage limit. Please try again later.', 'smart-cycle-discounts' ),
			'general_error'        => __( 'An error occurred. Please try again.', 'smart-cycle-discounts' ),
			'validation_failed'    => __( 'Validation failed. Please check the form and try again.', 'smart-cycle-discounts' ),
			'server_error'         => __( 'An internal server error occurred.', 'smart-cycle-discounts' ),
			'service_unavailable'  => __( 'Service temporarily unavailable. Please try again later.', 'smart-cycle-discounts' ),
			'bad_gateway'          => __( 'Bad gateway. Please try again.', 'smart-cycle-discounts' ),
			'gateway_timeout'      => __( 'Gateway timeout. Please try again.', 'smart-cycle-discounts' ),
			'processing_error'     => __( 'An error occurred while processing your request.', 'smart-cycle-discounts' ),
			'pro_feature_required' => __( 'This feature requires a PRO license.', 'smart-cycle-discounts' ),
			// Cycle AI errors (user-facing messages).
			'wsscd_cycle_ai_not_configured'   => __( 'Cycle AI is not configured. Please add an API key in the plugin settings.', 'smart-cycle-discounts' ),
			'wsscd_cycle_ai_http_error'       => __( 'Cycle AI request failed. Please try again later.', 'smart-cycle-discounts' ),
			'wsscd_cycle_ai_api_error'         => __( 'Cycle AI returned an error response.', 'smart-cycle-discounts' ),
			'wsscd_cycle_ai_invalid_response'  => __( 'Cycle AI returned an invalid response.', 'smart-cycle-discounts' ),
			'wsscd_cycle_ai_no_choices'        => __( 'Cycle AI did not return any suggestions.', 'smart-cycle-discounts' ),
			'wsscd_cycle_ai_empty_content'     => __( 'Cycle AI returned an empty suggestion.', 'smart-cycle-discounts' ),
			'wsscd_cycle_ai_invalid_json'      => __( 'Cycle AI returned suggestions in an unexpected format.', 'smart-cycle-discounts' ),
			'wsscd_cycle_ai_no_suggestions'    => __( 'Cycle AI did not return any campaign suggestions.', 'smart-cycle-discounts' ),
		);
	}

	/**
	 * Send success response with modular data handling
	 *
	 * @since    1.0.0
	 * @param    mixed $data    Response data.
	 * @param    array $meta    Optional metadata.
	 * @return   void
	 */
	public static function success( $data = null, $meta = array() ) {
		$response = self::format_response( true, $data, null, $meta );
		self::send_response( $response );
	}

	/**
	 * Send error response with categorized error handling
	 *
	 * @since    1.0.0
	 * @param    string $message    Error message.
	 * @param    string $code       Error code.
	 * @param    mixed  $data       Additional data.
	 * @return   void
	 */
	public static function error( $message = '', $code = 'general_error', $data = null ) {
		$safe_message = self::get_safe_error_message( $message, $code );
		$response     = self::format_response(
			false,
			$data,
			array(
				'code'    => $code,
				'message' => $safe_message,
			)
		);

		self::send_response( $response );
	}

	/**
	 * Send WP_Error response with proper error extraction
	 *
	 * @since    1.0.0
	 * @param    WP_Error $error    WordPress error object.
	 * @return   void
	 */
	public static function wp_error( $error ) {
		if ( ! is_wp_error( $error ) ) {
			self::error( 'Invalid error object', 'invalid_error' );
			return;
		}

		$errors = array();

		foreach ( $error->get_error_codes() as $code ) {
			$errors[] = array(
				'code'    => $code,
				'message' => $error->get_error_message( $code ),
				'data'    => $error->get_error_data( $code ),
			);
		}

		$response = self::format_response( false, null, $errors );
		self::send_response( $response );
	}

	/**
	 * Send validation error response with field-specific errors
	 *
	 * @since    1.0.0
	 * @param    array $errors    Field => message pairs.
	 * @return   void
	 */
	public static function validation_error( $errors ) {
		$formatted_errors = array();

		foreach ( $errors as $field => $messages ) {
			$formatted_errors[] = array(
				'field'    => $field,
				'messages' => is_array( $messages ) ? $messages : array( $messages ),
			);
		}

		$response = self::format_response(
			false,
			null,
			array(
				'code'    => 'validation_failed',
				'message' => __( 'Validation failed. Please check the form and try again.', 'smart-cycle-discounts' ),
				'fields'  => $formatted_errors,
			)
		);

		self::send_response( $response );
	}

	/**
	 * Modular response formatter for consistent structure
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    bool  $success    Success flag.
	 * @param    mixed $data       Response data.
	 * @param    mixed $error      Error data.
	 * @param    array $meta       Metadata.
	 * @return   array                 Formatted response.
	 */
	private static function format_response( $success, $data = null, $error = null, $meta = array() ) {
		$original_data = $data;

		if ( null !== $data ) {
			$data = self::snake_to_camel_keys( $data );
		}

		$response = array(
			'success' => $success,
			'data'    => $data,
		);

		if ( ! $success && null !== $error ) {
			$response['error'] = $error;
		}

		if ( ! empty( $meta ) ) {
			$response['meta'] = array_merge(
				array(
					'timestamp' => current_time( 'timestamp' ),
					'version'   => defined( 'WSSCD_VERSION' ) ? WSSCD_VERSION : '1.0.0',
				),
				$meta
			);
		}

		// Apply response filters for extensibility (pass original data to maintain BC)
		return apply_filters( 'wsscd_ajax_response', $response, $success, $original_data, $error );
	}

	/**
	 * Send response with proper headers and encoding
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $response    Response data.
	 * @return   void
	 */
	private static function send_response( $response ) {
		// Ensure proper content type
		if ( ! headers_sent() ) {
			header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) );
		}

		$response = self::sanitize_response_data( $response );

		// Send JSON response
		echo wp_json_encode( $response );

		// Properly terminate execution
		if ( wp_doing_ajax() ) {
			wp_die();
		} else {
			exit;
		}
	}

	/**
	 * Convert array keys from snake_case to camelCase recursively
	 * Normalizes data at the API boundary for JavaScript consumption
	 *
	 * Delegates to WSSCD_Case_Converter utility for actual conversion.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    mixed $data    Data to convert.
	 * @return   mixed             Converted data.
	 */
	private static function snake_to_camel_keys( $data ) {
		// Ensure utility class is loaded
		if ( ! class_exists( 'WSSCD_Case_Converter' ) ) {
			require_once WSSCD_PLUGIN_DIR . 'includes/utilities/class-case-converter.php';
		}

		return WSSCD_Case_Converter::snake_to_camel( $data );
	}

	/**
	 * Get categorized safe error message.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $message    Original message.
	 * @param    string $code       Error code.
	 * @return   string                Safe message.
	 */
	private static function get_safe_error_message( $message, $code ) {
		$messages = self::get_safe_messages();

		// When code is numeric (e.g. HTTP 500), use the provided message if non-empty so
		// handlers can return specific translated messages (e.g. "No campaign data found in session.").
		if ( is_numeric( $code ) && is_string( $message ) && '' !== trim( $message ) ) {
			return $message;
		}

		// Allow handlers to pass through a custom message for rate_limited (e.g. Cycle AI limit).
		if ( 'rate_limited' === $code && is_string( $message ) && '' !== trim( $message ) ) {
			return $message;
		}

		// Allow handlers to pass through a custom message for request_failed (e.g. overview load failure).
		if ( 'request_failed' === $code && is_string( $message ) && '' !== trim( $message ) ) {
			return $message;
		}

		// Use safe message if available, otherwise use general error
		if ( isset( $messages[ $code ] ) ) {
			return $messages[ $code ];
		}

		return $messages['general_error'];
	}

	/**
	 * Recursively sanitize response data
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    mixed $data    Data to sanitize.
	 * @return   mixed             Sanitized data.
	 */
	private static function sanitize_response_data( $data ) {
		if ( is_array( $data ) ) {
			return array_map( array( __CLASS__, 'sanitize_response_data' ), $data );
		}

		if ( is_object( $data ) ) {
			$sanitized = new stdClass();
			foreach ( $data as $key => $value ) {
				$sanitized->$key = self::sanitize_response_data( $value );
			}
			return $sanitized;
		}

		if ( is_string( $data ) ) {
			// Don't sanitize URLs as it can break them (converts & to &amp;)
			if ( filter_var( $data, FILTER_VALIDATE_URL ) ) {
				return esc_url_raw( $data );
			}

			// Allow SVG elements in admin AJAX responses
			// SVG icons are generated by trusted WSSCD_Icon_Helper class
			$allowed_html = wp_kses_allowed_html( 'post' );

			// Add SVG support to allowed HTML
			$svg_args = array(
				'svg'   => array(
					'class'       => true,
					'aria-hidden' => true,
					'aria-labelledby' => true,
					'role'        => true,
					'xmlns'       => true,
					'width'       => true,
					'height'      => true,
					'viewbox'     => true, // lowercase for XML compatibility
					'viewBox'     => true, // camelCase for SVG compatibility
					'fill'        => true,
					'style'       => true,
				),
				'path'  => array(
					'd'    => true,
					'fill' => true,
				),
				'circle' => array(
					'cx'   => true,
					'cy'   => true,
					'r'    => true,
					'fill' => true,
				),
			);

			$allowed_html = array_merge( $allowed_html, $svg_args );

			return wp_kses( $data, $allowed_html );
		}

		return $data;
	}

	/**
	 * Send server error response.
	 *
	 * @since    1.0.0
	 * @param    string $message    Optional custom message.
	 * @param    mixed  $data       Optional error details.
	 * @return   void
	 */
	public static function server_error( $message = '', $data = null ) {
		if ( ! headers_sent() ) {
			http_response_code( 500 );
		}

		if ( empty( $message ) ) {
			$message = __( 'An internal server error occurred.', 'smart-cycle-discounts' );
		}

		self::error( $message, 'server_error', $data );
	}

	/**
	 * Send service unavailable response with Retry-After header.
	 *
	 * @since    1.0.0
	 * @param    string $message       Optional custom message.
	 * @param    int    $retry_after   Retry after seconds (default: 30).
	 * @param    mixed  $data          Optional data.
	 * @return   void
	 */
	public static function service_unavailable( $message = '', $retry_after = 30, $data = null ) {
		if ( ! headers_sent() ) {
			http_response_code( 503 );
			header( "Retry-After: $retry_after" );
		}

		if ( empty( $message ) ) {
			$message = __( 'Service temporarily unavailable. Please try again later.', 'smart-cycle-discounts' );
		}

		self::error( $message, 'service_unavailable', $data );
	}

	/**
	 * Send rate limit exceeded response with Retry-After header.
	 *
	 * @since    1.0.0
	 * @param    string $message       Optional custom message.
	 * @param    int    $retry_after   Retry after seconds (default: 60).
	 * @param    mixed  $data          Optional data.
	 * @return   void
	 */
	public static function rate_limit_exceeded( $message = '', $retry_after = 60, $data = null ) {
		if ( ! headers_sent() ) {
			http_response_code( 429 );
			header( "Retry-After: $retry_after" );
		}

		if ( empty( $message ) ) {
			$message = __( 'Too many requests. Please try again later.', 'smart-cycle-discounts' );
		}

		self::error( $message, 'rate_limit', $data );
	}

	/**
	 * Send unauthorized response.
	 *
	 * @since    1.0.0
	 * @param    string $message    Optional custom message.
	 * @return   void
	 */
	public static function unauthorized( $message = '' ) {
		if ( ! headers_sent() ) {
			http_response_code( 403 );
		}

		if ( empty( $message ) ) {
			$message = __( 'You are not authorized to perform this action.', 'smart-cycle-discounts' );
		}

		self::error( $message, 'unauthorized' );
	}

	/**
	 * Send not found response.
	 *
	 * @since    1.0.0
	 * @param    string $message    Optional custom message.
	 * @return   void
	 */
	public static function not_found( $message = '' ) {
		if ( ! headers_sent() ) {
			http_response_code( 404 );
		}

		if ( empty( $message ) ) {
			$message = __( 'The requested resource was not found.', 'smart-cycle-discounts' );
		}

		self::error( $message, 'not_found' );
	}

	/**
	 * Send bad request response.
	 *
	 * @since    1.0.0
	 * @param    string $message    Optional custom message.
	 * @param    mixed  $data       Optional data.
	 * @return   void
	 */
	public static function bad_request( $message = '', $data = null ) {
		if ( ! headers_sent() ) {
			http_response_code( 400 );
		}

		if ( empty( $message ) ) {
			$message = __( 'Bad request. Please check your input.', 'smart-cycle-discounts' );
		}

		self::error( $message, 'invalid_parameter', $data );
	}
}
