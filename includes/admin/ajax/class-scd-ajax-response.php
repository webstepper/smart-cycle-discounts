<?php
/**
 * Secure AJAX Response Handler
 *
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes
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
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_AJAX_Response {

    /**
     * Predefined safe error messages.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $safe_messages    Safe error messages.
     */
    private static $safe_messages = array(
        'unauthorized' => 'You do not have permission to perform this action.',
        'invalid_nonce' => 'Security verification failed. Please refresh and try again.',
        'missing_parameter' => 'Required information is missing. Please check your input.',
        'invalid_parameter' => 'Invalid data provided. Please check your input.',
        'database_error' => 'A database error occurred. Please try again later.',
        'not_found' => 'The requested item could not be found.',
        'duplicate' => 'This item already exists.',
        'rate_limit' => 'Too many requests. Please wait and try again.',
        'general_error' => 'An error occurred. Please try again.',
        'validation_failed' => 'Validation failed. Please check the form and try again.',
        'server_error' => 'An internal server error occurred.',
        'service_unavailable' => 'Service temporarily unavailable. Please try again later.',
        'bad_gateway' => 'Bad gateway. Please try again.',
        'gateway_timeout' => 'Gateway timeout. Please try again.',
        'processing_error' => 'An error occurred while processing your request.'
    );

    /**
     * Send success response with modular data handling
     *
     * @since    1.0.0
     * @param    mixed    $data    Response data.
     * @param    array    $meta    Optional metadata.
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
     * @param    string      $message    Error message.
     * @param    string      $code       Error code.
     * @param    mixed       $data       Additional data.
     * @return   void
     */
    public static function error( $message = '', $code = 'general_error', $data = null ) {
        $safe_message = self::get_safe_error_message( $message, $code );
        $response = self::format_response( false, $data, array(
            'code' => $code,
            'message' => $safe_message
        ) );
        
        self::send_response( $response );
    }

    /**
     * Send WP_Error response with proper error extraction
     *
     * @since    1.0.0
     * @param    WP_Error    $error    WordPress error object.
     * @return   void
     */
    public static function wp_error( $error ) {
        // Validate input is actually a WP_Error
        if ( ! is_wp_error( $error ) ) {
            self::error( 'Invalid error object', 'invalid_error' );
            return;
        }
        
        $errors = array();
        
        foreach ( $error->get_error_codes() as $code ) {
            $errors[] = array(
                'code' => $code,
                'message' => $error->get_error_message( $code ),
                'data' => $error->get_error_data( $code )
            );
        }
        
        $response = self::format_response( false, null, $errors );
        self::send_response( $response );
    }

    /**
     * Send validation error response with field-specific errors
     *
     * @since    1.0.0
     * @param    array    $errors    Field => message pairs.
     * @return   void
     */
    public static function validation_error( $errors ) {
        $formatted_errors = array();
        
        foreach ( $errors as $field => $messages ) {
            $formatted_errors[] = array(
                'field' => $field,
                'messages' => is_array( $messages ) ? $messages : array( $messages )
            );
        }
        
        $response = self::format_response( false, null, array(
            'code' => 'validation_failed',
            'message' => __( 'Validation failed. Please check the form and try again.', 'smart-cycle-discounts' ),
            'fields' => $formatted_errors
        ) );
        
        self::send_response( $response );
    }

    /**
     * Modular response formatter for consistent structure
     *
     * @since    1.0.0
     * @access   private
     * @param    bool      $success    Success flag.
     * @param    mixed     $data       Response data.
     * @param    mixed     $error      Error data.
     * @param    array     $meta       Metadata.
     * @return   array                 Formatted response.
     */
    private static function format_response( $success, $data = null, $error = null, $meta = array() ) {
        // Store original data for filter
        $original_data = $data;

        // Convert data keys to camelCase for JavaScript
        if ( null !== $data ) {
            $data = self::snake_to_camel_keys( $data );
        }

        $response = array(
            'success' => $success,
            'data' => $data
        );

        if ( ! $success && null !== $error ) {
            $response['error'] = $error;
        }

        // Add metadata if provided
        if ( ! empty( $meta ) ) {
            $response['meta'] = array_merge( array(
                'timestamp' => current_time( 'timestamp' ),
                'version' => defined( 'SCD_VERSION' ) ? SCD_VERSION : '1.0.0'
            ), $meta );
        }

        // Apply response filters for extensibility (pass original data to maintain BC)
        return apply_filters( 'scd_ajax_response', $response, $success, $original_data, $error );
    }

    /**
     * Send response with proper headers and encoding
     *
     * @since    1.0.0
     * @access   private
     * @param    array    $response    Response data.
     * @return   void
     */
    private static function send_response( $response ) {
        // Ensure proper content type
        if ( ! headers_sent() ) {
            header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) );
        }
        
        // Sanitize response data
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
     * @since    1.0.0
     * @access   private
     * @param    mixed    $data    Data to convert.
     * @return   mixed             Converted data.
     */
    private static function snake_to_camel_keys( $data ) {
        if ( ! is_array( $data ) ) {
            return $data;
        }

        // Check if this is a numeric array (list) - preserve as-is but recurse into values
        $is_list = array_keys( $data ) === range( 0, count( $data ) - 1 );
        if ( $is_list ) {
            $result = array();
            foreach ( $data as $value ) {
                $result[] = is_array( $value ) ? self::snake_to_camel_keys( $value ) : $value;
            }
            return $result;
        }

        // Associative array - convert keys
        $result = array();
        foreach ( $data as $key => $value ) {
            // Convert snake_case key to camelCase
            $camel_key = lcfirst( str_replace( '_', '', ucwords( $key, '_' ) ) );

            // Recursively convert nested arrays
            $result[ $camel_key ] = is_array( $value ) ? self::snake_to_camel_keys( $value ) : $value;
        }

        return $result;
    }

    /**
     * Categorized safe error messages
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $message    Original message.
     * @param    string    $code       Error code.
     * @return   string                Safe message.
     */
    private static function get_safe_error_message( $message, $code ) {
        // Use safe message if available, otherwise use general error
        if ( isset( self::$safe_messages[ $code ] ) ) {
            return __( self::$safe_messages[ $code ], 'smart-cycle-discounts' );
        }

        // Log the actual error for debugging
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        }

        return __( self::$safe_messages['general_error'], 'smart-cycle-discounts' );
    }

    /**
     * Recursively sanitize response data
     *
     * @since    1.0.0
     * @access   private
     * @param    mixed    $data    Data to sanitize.
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
            return wp_kses_post( $data );
        }
        
        return $data;
    }

    /**
     * Send server error response.
     *
     * @since    1.0.0
     * @param    string    $message    Optional custom message.
     * @param    mixed     $data       Optional error details.
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
     * @param    string    $message       Optional custom message.
     * @param    int       $retry_after   Retry after seconds (default: 30).
     * @param    mixed     $data          Optional data.
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
     * @param    string    $message       Optional custom message.
     * @param    int       $retry_after   Retry after seconds (default: 60).
     * @param    mixed     $data          Optional data.
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
     * @param    string    $message    Optional custom message.
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
     * @param    string    $message    Optional custom message.
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
     * @param    string    $message    Optional custom message.
     * @param    mixed     $data       Optional data.
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