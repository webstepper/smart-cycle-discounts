<?php
/**
 * Ajax Security Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/class-ajax-security.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// Removed strict types for PHP compatibility

// Ensure WSSCD_Validation is loaded for constants
if ( ! class_exists( 'WSSCD_Validation' ) ) {
	require_once WSSCD_PLUGIN_DIR . 'includes/core/validation/class-validation.php';
}

/**
 * AJAX Security Class
 *
 * Centralized security handler for all AJAX requests in the plugin.
 * This class provides:
 * - Nonce verification via wp_verify_nonce() (see verify_nonce() method at line 383)
 * - Capability checks via current_user_can()
 * - Rate limiting to prevent abuse
 * - Request size validation
 *
 * All AJAX handlers in the plugin use verify_ajax_request() which calls wp_verify_nonce().
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/security
 * @author     Webstepper <contact@webstepper.io>
 */
class WSSCD_Ajax_Security {

	/**
	 * Note: Security constants are now centralized in WSSCD_Validation class
	 * Use WSSCD_Validation_Rules::MAX_REQUEST_SIZE, WSSCD_Validation_Rules::NONCE_LIFETIME
	 *
	 * @since    1.0.0
	 */

	/**
	 * Centralized nonce map: action => nonce_name
	 * Each AJAX action has exactly one valid nonce
	 * Using full action names with wsscd_ prefix for consistency
	 */
	private static $nonce_map = array(
		// Wizard actions
		'wsscd_save_step'                      => 'wsscd_wizard_nonce',
		'wsscd_load_data'                      => 'wsscd_wizard_nonce',
		'wsscd_session_status'                 => 'wsscd_wizard_nonce',
		'wsscd_complete_wizard'                => 'wsscd_wizard_nonce',
		'wsscd_import_calculator_preset'       => 'wsscd_wizard_nonce',
		'wsscd_get_summary'                    => 'wsscd_wizard_nonce',
		'wsscd_get_product_stats'              => 'wsscd_wizard_nonce',
		'wsscd_get_shipping_methods'           => 'wsscd_wizard_nonce',
		'wsscd_health_check'                   => 'wsscd_admin_nonce',
		'wsscd_campaign_health'                => 'wsscd_wizard_nonce',
		'wsscd_calculate_discount_impact'      => 'wsscd_wizard_nonce',

		// Tools page actions
		'wsscd_export'                         => 'wsscd_admin_nonce',
		'wsscd_import'                         => 'wsscd_admin_nonce',
		'wsscd_database_maintenance'           => 'wsscd_admin_nonce',
		'wsscd_cache_management'               => 'wsscd_admin_nonce',
		'wsscd_log_viewer'                     => 'wsscd_admin_nonce',

		// Validation actions
		'wsscd_check_campaign_name'            => 'wsscd_validation_nonce',
		'wsscd_validation_rules_batch'         => 'wsscd_validation_nonce',

		// Product actions
		'wsscd_product_search'                 => 'wsscd_wizard_nonce', // Use wizard nonce since it's called from wizard
		'product_search'                     => 'wsscd_wizard_nonce', // Also support without prefix for backward compatibility

		// Analytics actions
		'wsscd_analytics_overview'             => 'wsscd_analytics_nonce',
		'wsscd_analytics_campaign_performance' => 'wsscd_analytics_nonce',
		'wsscd_analytics_revenue_trend'        => 'wsscd_analytics_nonce',
		'wsscd_analytics_top_products'         => 'wsscd_analytics_nonce',
		'wsscd_analytics_activity_feed'        => 'wsscd_analytics_nonce',
		'wsscd_analytics_export'               => 'wsscd_analytics_nonce',
		'wsscd_analytics_refresh_cache'        => 'wsscd_analytics_nonce',

		// Campaign actions
		'wsscd_campaign_overview'              => 'wsscd_admin_nonce',
		'wsscd_toggle_campaign_status'         => 'wsscd_admin_nonce',

		// WooCommerce integration
		'wsscd_apply_discount'                 => 'wsscd_discount_nonce',

		// Discount validation and calculation
		'wsscd_validate_discounts'             => 'wsscd_wizard_nonce', // Called from wizard
		'wsscd_validate_discount_rules'        => 'wsscd_wizard_nonce', // Called from wizard
		'wsscd_get_discount_preview'           => 'wsscd_wizard_nonce', // Called from wizard
		'wsscd_calculate_discount'             => 'wsscd_discount_nonce',
		'wsscd_preview_discount'               => 'wsscd_wizard_nonce', // Called from wizard

		// Draft handlers
		'wsscd_delete_draft'                   => 'wsscd_wizard_nonce',

		// Dashboard actions
		'wsscd_main_dashboard_data'            => 'wsscd_main_dashboard',
		'wsscd_get_planner_insights'           => 'wsscd_admin_nonce',

		// Email/Notification actions
		'wsscd_send_test_email'                => 'wsscd_admin_nonce',
		'wsscd_process_queue'                  => 'wsscd_admin_nonce',
		'wsscd_retry_failed_emails'            => 'wsscd_admin_nonce',
		'wsscd_clear_queue'                    => 'wsscd_admin_nonce',

		// Debug handlers
		'wsscd_write_debug_log'                => 'wsscd_wizard_nonce',
		'wsscd_log_console'                    => 'wsscd_wizard_nonce',

		// Sidebar handlers
		'wsscd_get_help_topic'                 => 'wsscd_wizard_nonce',
	);

	/**
	 * Capability requirements map: action => capability
	 * Using full action names with wsscd_ prefix for consistency
	 */
	private static $capability_map = array(
		// Wizard actions - require campaign management
		'wsscd_save_step'                      => 'wsscd_manage_campaigns',
		'wsscd_load_data'                      => 'wsscd_manage_campaigns',
		'wsscd_session_status'                 => 'wsscd_manage_campaigns',
		'wsscd_complete_wizard'                => 'wsscd_manage_campaigns',
		'wsscd_import_calculator_preset'       => 'wsscd_manage_campaigns',
		'wsscd_get_summary'                    => 'wsscd_manage_campaigns',
		'wsscd_get_product_stats'              => 'wsscd_manage_campaigns',
		'wsscd_get_shipping_methods'           => 'wsscd_manage_campaigns',
		'wsscd_health_check'                   => 'manage_options',
		'wsscd_campaign_health'                => 'wsscd_manage_campaigns',
		'wsscd_calculate_discount_impact'      => 'wsscd_manage_campaigns',

		// Tools page actions - require admin capabilities
		'wsscd_export'                         => 'manage_options',
		'wsscd_import'                         => 'manage_options',
		'wsscd_database_maintenance'           => 'manage_options',
		'wsscd_cache_management'               => 'manage_options',
		'wsscd_log_viewer'                     => 'manage_options',

		// Validation actions - require campaign management
		'wsscd_check_campaign_name'            => 'wsscd_manage_campaigns',
		'wsscd_validation_rules_batch'         => 'wsscd_manage_campaigns',

		// Product actions - require campaign management in wizard context
		'wsscd_product_search'                 => 'wsscd_manage_campaigns',

		// Analytics view actions - require view analytics
		'wsscd_analytics_overview'             => 'wsscd_view_analytics',
		'wsscd_analytics_campaign_performance' => 'wsscd_view_analytics',
		'wsscd_analytics_revenue_trend'        => 'wsscd_view_analytics',
		'wsscd_analytics_top_products'         => 'wsscd_view_analytics',
		'wsscd_analytics_activity_feed'        => 'wsscd_view_analytics',

		// Analytics management actions - require manage analytics
		'wsscd_analytics_export'               => 'wsscd_manage_analytics',
		'wsscd_analytics_refresh_cache'        => 'wsscd_manage_analytics',

		// Campaign actions - require campaign management
		'wsscd_campaign_overview'              => 'wsscd_view_campaigns',
		'wsscd_toggle_campaign_status'         => 'wsscd_activate_campaigns',

		// WooCommerce integration - no capability for public discount application
		'wsscd_apply_discount'                 => '',

		// Discount validation and calculation
		'wsscd_validate_discounts'             => 'wsscd_manage_campaigns',
		'wsscd_validate_discount_rules'        => 'wsscd_manage_campaigns',
		'wsscd_get_discount_preview'           => 'wsscd_manage_campaigns',
		'wsscd_calculate_discount'             => '',  // Public endpoint
		'wsscd_preview_discount'               => 'wsscd_manage_campaigns',

		// Draft handlers
		'wsscd_delete_draft'                   => 'wsscd_manage_campaigns',

		// Dashboard actions
		'wsscd_main_dashboard_data'            => 'wsscd_view_analytics',
		'wsscd_get_planner_insights'           => 'wsscd_view_analytics',

		// Email/Notification actions - require admin capabilities
		'wsscd_send_test_email'                => 'manage_options',
		'wsscd_process_queue'                  => 'manage_options',
		'wsscd_retry_failed_emails'            => 'manage_options',
		'wsscd_clear_queue'                    => 'manage_options',

		// Debug handlers
		'wsscd_write_debug_log'                => 'wsscd_manage_campaigns',
		'wsscd_log_console'                    => 'wsscd_manage_campaigns',

		// Sidebar handlers
		'wsscd_get_help_topic'                 => 'wsscd_manage_campaigns',
	);

	/**
	 * Rate limits map: action => requests per minute
	 * Using full action names with wsscd_ prefix for consistency
	 */
	private static $rate_limits = array(
		// Strict limits for public endpoints
		'wsscd_apply_discount'                 => 20,

		// Discount actions
		'wsscd_validate_discounts'             => 60, // Allow frequent validation during wizard
		'wsscd_calculate_discount'             => 30,  // Public but rate limited
		'wsscd_preview_discount'               => 60,    // Allow frequent preview updates

		// Wizard actions
		'wsscd_save_step'                      => 30,
		'wsscd_load_data'                      => 30,
		'wsscd_complete_wizard'                => 5,
		'wsscd_get_summary'                    => 30,
		'wsscd_get_product_stats'              => 30,
		'wsscd_health_check'                   => 10,
		'wsscd_campaign_health'                => 10,
		'wsscd_calculate_discount_impact'      => 20,

		// Validation actions
		'wsscd_check_campaign_name'            => 10,
		'wsscd_validation_rules_batch'         => 20,

		// Product actions
		'wsscd_product_search'                 => 60,  // Increased from 20 to handle wizard usage patterns

		// Analytics actions
		'wsscd_analytics_overview'             => 30,
		'wsscd_analytics_campaign_performance' => 30,
		'wsscd_analytics_revenue_trend'        => 30,
		'wsscd_analytics_top_products'         => 30,
		'wsscd_analytics_activity_feed'        => 30,
		'wsscd_analytics_export'               => 5,
		'wsscd_analytics_refresh_cache'        => 10,

		// Draft handlers
		'wsscd_delete_draft'                   => 10,

		// Dashboard actions
		'wsscd_main_dashboard_data'            => 30,
		'wsscd_get_planner_insights'           => 30,

		// Discount API handlers
		'wsscd_validate_discount_rules'        => 30,
		'wsscd_get_discount_preview'           => 30,

		// Debug handlers
		'wsscd_write_debug_log'                => 60,
		'wsscd_log_console'                    => 60,

		// Default for other actions
		'default'                            => 60,
	);

	/**
	 * Get all nonce configurations for frontend localization
	 *
	 * @since    1.0.0
	 * @return   array    Nonce configurations
	 */
	public static function get_nonce_config() {

		$config = array();

		$unique_nonces = array_unique( array_values( self::$nonce_map ) );

		// Generate nonces for each unique name
		foreach ( $unique_nonces as $nonce_name ) {
			$config[ $nonce_name ] = wp_create_nonce( $nonce_name );
		}

		$config['action_map'] = self::$nonce_map;

		// Add REST nonce separately
		$config['rest_nonce'] = wp_create_nonce( 'wp_rest' );

		return $config;
	}

	/**
	 * Verify AJAX request security
	 *
	 * @since    1.0.0
	 * @param    string $action       AJAX action
	 * @param    array  $request      Request data ($_POST or $_GET)
	 * @param    bool   $check_size   Whether to check request size
	 * @return   true|WP_Error               True if valid, WP_Error otherwise
	 */
	public static function verify_ajax_request( $action, $request = array(), $check_size = true ) {
		if ( ! wp_doing_ajax() ) {
			return new WP_Error(
				'not_ajax',
				'Invalid request context',
				array( 'status' => 400 )
			);
		}

		if ( $check_size ) {
			$size_check = self::check_request_size( $request );
			if ( is_wp_error( $size_check ) ) {
				return $size_check;
			}
		}

		// Verify nonce
		$nonce_check = self::verify_nonce( $action, $request );
		if ( is_wp_error( $nonce_check ) ) {
			return $nonce_check;
		}

		$rate_check = self::check_rate_limit( $action );
		if ( is_wp_error( $rate_check ) ) {
			return $rate_check;
		}

		// Verify capability
		$capability_check = self::verify_capability( $action );
		if ( is_wp_error( $capability_check ) ) {
			return $capability_check;
		}

		// Verify request signature if provided (for sensitive operations)
		// Use ! empty() instead of isset() because router sets empty string when not provided
		if ( ! empty( $request['_signature'] ) && in_array( $action, self::get_signed_actions(), true ) ) {
			$signature = $request['_signature'];
			unset( $request['_signature'] ); // Remove signature from data before verification

			if ( ! self::verify_signature( $request, $signature ) ) {
				return new WP_Error(
					'invalid_signature',
					'Request signature verification failed',
					array( 'status' => 403 )
				);
			}
		}

		return true;
	}

	/**
	 * Verify nonce for action using wp_verify_nonce().
	 *
	 * This method performs the actual WordPress nonce verification by calling
	 * wp_verify_nonce() at line 423. All AJAX actions in the plugin are routed
	 * through this centralized security check.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $action     AJAX action
	 * @param    array  $request    Request data
	 * @return   true|WP_Error      True if valid, WP_Error otherwise
	 */
	private static function verify_nonce( $action, $request ) {
		$expected_nonce = self::$nonce_map[ $action ] ?? null;

		if ( ! $expected_nonce ) {
			return new WP_Error(
				'unknown_action',
				'Unknown AJAX action',
				array(
					'action' => $action,
					'status' => 400,
				)
			);
		}

		$nonce = $request['nonce'] ?? $request['_wpnonce'] ?? '';

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		}

		if ( empty( $nonce ) ) {
			return new WP_Error(
				'missing_nonce',
				'Security token missing',
				array( 'status' => 403 )
			);
		}

		// Sanitize nonce value before verification (WordPress.org requirement)
		$nonce = sanitize_text_field( wp_unslash( $nonce ) );

		// Verify nonce - NO FALLBACKS!
		$nonce_valid = wp_verify_nonce( $nonce, $expected_nonce );
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		}

		if ( ! $nonce_valid ) {
			return new WP_Error(
				'invalid_nonce',
				'Security check failed. Please refresh the page and try again.',
				array(
					'expected'         => $expected_nonce,
					'status'           => 403,
					'refresh_required' => true,
				)
			);
		}

		return true;
	}

	/**
	 * Verify user capability for action
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $action     AJAX action
	 * @return   true|WP_Error              True if valid, WP_Error otherwise
	 */
	private static function verify_capability( $action ) {
		$required_capability = self::$capability_map[ $action ] ?? 'manage_options';

		// Skip capability check for public endpoints
		if ( empty( $required_capability ) ) {
			return true;
		}

		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'not_logged_in',
				'You must be logged in to perform this action',
				array( 'status' => 401 )
			);
		}

		// Try plugin-specific capability first
		if ( current_user_can( $required_capability ) ) {
			return true;
		}

		// Fallback to manage_options for admins
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		// Also allow manage_woocommerce as secondary fallback
		if ( strpos( $required_capability, 'wsscd_' ) === 0 && current_user_can( 'manage_woocommerce' ) ) {
			return true;
		}

		return new WP_Error(
			'insufficient_permissions',
			'You do not have permission to perform this action',
			array(
				'required_capability' => $required_capability,
				'status'              => 403,
			)
		);
	}

	/**
	 * Check request size
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $request    Request data
	 * @return   true|WP_Error              True if valid, WP_Error otherwise
	 */
	private static function check_request_size( $request ) {
		$request_size = strlen( serialize( $request ) );

		$action = isset( $request['wsscd_action'] ) ? $request['wsscd_action'] : '';

		// Allow larger requests for complete_wizard action (500KB)
		if ( 'complete_wizard' === $action ) {
			$max_size = apply_filters( 'wsscd_ajax_max_request_size_complete_wizard', 512000 );
		} else {
			$max_size = apply_filters( 'wsscd_ajax_max_request_size', WSSCD_Validation_Rules::MAX_REQUEST_SIZE );
		}

		if ( $request_size > $max_size ) {
			return new WP_Error(
				'payload_too_large',
				sprintf(
					'Request size (%s) exceeds maximum allowed size (%s)',
					size_format( $request_size ),
					size_format( $max_size )
				),
				array(
					'request_size' => $request_size,
					'max_size'     => $max_size,
					'status'       => 413,
				)
			);
		}

		return true;
	}

	/**
	 * Check rate limit for action - Improved with user ID + IP combination
	 * Following Phase 6.3 of the validation fix plan
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $action     AJAX action
	 * @return   true|WP_Error              True if allowed, WP_Error otherwise
	 */
	private static function check_rate_limit( $action ) {
		$limit = self::$rate_limits[ $action ] ?? self::$rate_limits['default'];

		$user_id = get_current_user_id();
		$ip      = self::get_client_ip();

		// Combine user and IP for better rate limiting
		$identifier = $user_id > 0
			? 'user_' . $user_id . '_ip_' . md5( $ip )
			: 'ip_' . md5( $ip );

		$transient_key = 'wsscd_rl_' . $identifier . '_' . $action;

		$requests     = get_transient( $transient_key ) ?: array();
		$current_time = time();

		// Clean old requests (sliding window)
		$requests = array_filter(
			$requests,
			function ( $timestamp ) use ( $current_time ) {
				return ( $current_time - $timestamp ) < 60;
			}
		);

		if ( count( $requests ) >= $limit ) {
			$retry_after = 60 - ( $current_time - min( $requests ) );

			return new WP_Error(
				'rate_limit_exceeded',
				'Too many requests. Please try again later.',
				array(
					'retry_after' => $retry_after,
					'limit'       => $limit,
					'window'      => '1 minute',
					'status'      => 429,
				)
			);
		}

		$requests[] = $current_time;
		set_transient( $transient_key, $requests, 120 ); // 2 minute expiry

		return true;
	}

	/**
	 * Verify public request (for guest endpoints)
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $request    Request data
	 * @return   true|WP_Error              True if valid, WP_Error otherwise
	 */
	private static function verify_public_request( $request ) {
		$referrer = wp_get_referer();
		if ( ! $referrer || strpos( $referrer, home_url() ) !== 0 ) {
			return new WP_Error(
				'invalid_referrer',
				'Invalid request origin',
				array( 'status' => 403 )
			);
		}

		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		if ( empty( $user_agent ) || self::is_bot_user_agent( $user_agent ) ) {
			return new WP_Error(
				'invalid_user_agent',
				'Invalid client',
				array( 'status' => 403 )
			);
		}

		// Verify tracking token if provided
		$token = $request['tracking_token'] ?? '';
		if ( ! empty( $token ) ) {
			$expected_token = wp_hash( 'wsscd_tracking_' . gmdate( 'Y-m-d' ) . '_' . self::get_client_ip() );
			if ( ! hash_equals( $expected_token, $token ) ) {
				return new WP_Error(
					'invalid_tracking_token',
					'Invalid tracking token',
					array( 'status' => 403 )
				);
			}
		}

		return true;
	}

	/**
	 * Sign request data to prevent tampering
	 * Following Phase 6.2 of the validation fix plan
	 *
	 * @since    1.0.0
	 * @param    array $data    Request data to sign
	 * @return   string             HMAC signature
	 */
	private static function sign_request( $data ) {
		$secret  = wp_salt( 'auth' );
		$payload = wp_json_encode( $data );
		return hash_hmac( 'sha256', $payload, $secret );
	}

	/**
	 * Verify request signature
	 *
	 * @since    1.0.0
	 * @param    array  $data        Request data
	 * @param    string $signature   Provided signature
	 * @return   bool                   True if signature is valid
	 */
	private static function verify_signature( $data, $signature ) {
		$expected = self::sign_request( $data );
		return hash_equals( $expected, $signature );
	}

	/**
	 * Get client IP address
	 *
	 * Only trusts proxy headers if the request comes from a trusted proxy.
	 * This prevents IP spoofing attacks where attackers can bypass rate
	 * limiting by forging HTTP headers.
	 *
	 * @since    1.0.0
	 * @return   string    Client IP
	 */
	public static function get_client_ip() {
		// Always use REMOTE_ADDR as the base (most reliable)
		$remote_addr = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '127.0.0.1';

		// Only trust forwarded headers if request comes from trusted proxy
		// For security, we DON'T trust proxy headers by default
		// Users can add trusted proxies via wp-config.php if needed:
		// define( 'WSSCD_TRUSTED_PROXIES', array( '10.0.0.1', '192.168.1.1' ) );
		$trusted_proxies = defined( 'WSSCD_TRUSTED_PROXIES' ) ? WSSCD_TRUSTED_PROXIES : array();

		// If not from trusted proxy, always use REMOTE_ADDR
		if ( ! in_array( $remote_addr, $trusted_proxies, true ) ) {
			return $remote_addr;
		}

		// Only check proxy headers if from trusted proxy
		$proxy_headers = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP' );

		foreach ( $proxy_headers as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );
				// Handle comma-separated IPs (take first one)
				if ( strpos( $ip, ',' ) !== false ) {
					$ip = trim( explode( ',', $ip )[0] );
				}
				// Validate IP format
				if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
					return $ip;
				}
			}
		}

		// Fallback to REMOTE_ADDR
		return $remote_addr;
	}

	/**
	 * Check if user agent is a bot
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $user_agent    User agent string
	 * @return   bool                     True if bot
	 */
	private static function is_bot_user_agent( $user_agent ) {
		$bot_patterns = array(
			'bot',
			'crawler',
			'spider',
			'scraper',
			'curl',
			'wget',
			'python',
			'java',
			'perl',
			'ruby',
			'go-http-client',
		);

		$user_agent_lower = strtolower( $user_agent );
		foreach ( $bot_patterns as $pattern ) {
			if ( strpos( $user_agent_lower, $pattern ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Standardized nonce verification helper
	 *
	 * @since    1.0.0
	 * @param    string $nonce      Nonce value
	 * @param    string $action     AJAX action
	 * @return   true|WP_Error              True if valid, WP_Error otherwise
	 */
	public static function verify_wsscd_nonce( $nonce, $action ) {
		return self::verify_nonce( $action, array( 'nonce' => $nonce ) );
	}

	/**
	 * Get actions that require request signing
	 *
	 * @since    1.0.0
	 * @return   array    Actions requiring signatures
	 */
	private static function get_signed_actions() {
		return array(
			// Critical operations
			'wsscd_save_campaign',
			'wsscd_delete_campaign',
			'wsscd_complete_wizard',
			'wsscd_toggle_campaign_status',

			// Financial/analytics exports
			'wsscd_export_analytics',
			'wsscd_generate_report',
			'wsscd_download_export',

			// Validation operations with sensitive data
			'wsscd_get_validation_rules_batch',
		);
	}

	/**
	 * Generate signed request for frontend
	 *
	 * @since    1.0.0
	 * @param    array $data    Request data
	 * @return   array              Data with signature
	 */
	public static function sign_ajax_request( $data ) {
		$signature          = self::sign_request( $data );
		$data['_signature'] = $signature;
		return $data;
	}
}
