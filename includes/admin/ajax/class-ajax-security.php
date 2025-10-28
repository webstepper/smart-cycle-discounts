<?php
/**
 * AJAX Security Handler
 *
 * Centralized security management for all AJAX operations
 *
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/security
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// Removed strict types for PHP compatibility

// Ensure SCD_Validation is loaded for constants
if ( ! class_exists( 'SCD_Validation' ) ) {
	require_once SCD_PLUGIN_DIR . 'includes/core/validation/class-validation.php';
}

/**
 * AJAX Security Class
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/security
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Ajax_Security {

	/**
	 * Note: Security constants are now centralized in SCD_Validation class
	 * Use SCD_Validation_Rules::MAX_REQUEST_SIZE, SCD_Validation_Rules::NONCE_LIFETIME
	 *
	 * @since    1.0.0
	 */

	/**
	 * Centralized nonce map: action => nonce_name
	 * Each AJAX action has exactly one valid nonce
	 * Using full action names with scd_ prefix for consistency
	 */
	private static $nonce_map = array(
		// Wizard actions
		'scd_save_step'                      => 'scd_wizard_nonce',
		'scd_load_data'                      => 'scd_wizard_nonce',
		'scd_load_session'                   => 'scd_wizard_nonce',
		'scd_check_session'                  => 'scd_wizard_nonce',
		'scd_session_status'                 => 'scd_wizard_nonce',
		'scd_complete_wizard'                => 'scd_wizard_nonce',
		'scd_get_summary'                    => 'scd_wizard_nonce',
		'scd_get_product_stats'              => 'scd_wizard_nonce',
		'scd_health_check'                   => 'scd_admin_nonce',
		'scd_campaign_health'                => 'scd_wizard_nonce',
		'scd_check_conflicts'                => 'scd_wizard_nonce',
		'scd_preview_coverage'               => 'scd_wizard_nonce',
		'scd_calculate_discount_impact'      => 'scd_wizard_nonce',
		'scd_sale_items_filter'              => 'scd_wizard_nonce',
		'scd_profit_margin_warning'          => 'scd_wizard_nonce',
		'scd_recover_session'                => 'scd_wizard_nonce',

		// Tools page actions
		'scd_export'                         => 'scd_admin_nonce',
		'scd_import'                         => 'scd_admin_nonce',
		'scd_database_maintenance'           => 'scd_admin_nonce',
		'scd_cache_management'               => 'scd_admin_nonce',
		'scd_clear_cache'                    => 'scd_admin_nonce',
		'scd_log_viewer'                     => 'scd_admin_nonce',

		// Validation actions
		'scd_check_campaign_name'            => 'scd_validation_nonce',
		'scd_validation_rules_batch'         => 'scd_validation_nonce',

		// Product actions
		'scd_product_search'                 => 'scd_wizard_nonce', // Use wizard nonce since it's called from wizard
		'product_search'                     => 'scd_wizard_nonce', // Also support without prefix for backward compatibility

		// Analytics actions
		'scd_analytics_overview'             => 'scd_analytics_nonce',
		'scd_analytics_campaign_performance' => 'scd_analytics_nonce',
		'scd_analytics_revenue_trend'        => 'scd_analytics_nonce',
		'scd_analytics_top_products'         => 'scd_analytics_nonce',
		'scd_analytics_activity_feed'        => 'scd_analytics_nonce',
		'scd_analytics_export'               => 'scd_analytics_nonce',
		'scd_analytics_refresh_cache'        => 'scd_analytics_nonce',

		// Campaign actions
		'scd_get_active_campaigns'           => 'scd_campaign_nonce',

		// Public tracking (special handling)
		'scd_track_event'                    => 'scd_public_tracking_nonce',

		// WooCommerce integration
		'scd_apply_discount'                 => 'scd_discount_nonce',

		// Discount validation and calculation
		'scd_validate_discounts'             => 'scd_wizard_nonce', // Called from wizard
		'scd_validate_discount_rules'        => 'scd_wizard_nonce', // Called from wizard
		'scd_get_discount_preview'           => 'scd_wizard_nonce', // Called from wizard
		'scd_calculate_discount'             => 'scd_discount_nonce',
		'scd_preview_discount'               => 'scd_wizard_nonce', // Called from wizard

		// Draft handlers
		'scd_save_draft'                     => 'scd_wizard_nonce',
		'scd_delete_draft'                   => 'scd_wizard_nonce',
		'scd_draft_list'                     => 'scd_wizard_nonce',
		'scd_draft_preview'                  => 'scd_wizard_nonce',

		// Dashboard actions
		'scd_main_dashboard_data'            => 'scd_main_dashboard',

		// Email/Notification actions
		'scd_send_test_email'                => 'scd_admin_nonce',
		'scd_process_queue'                  => 'scd_admin_nonce',
		'scd_retry_failed_emails'            => 'scd_admin_nonce',
		'scd_clear_queue'                    => 'scd_admin_nonce',

		// Debug handlers
		'scd_debug_log'                      => 'scd_wizard_nonce',
		'scd_log_console'                    => 'scd_wizard_nonce',
	);

	/**
	 * Capability requirements map: action => capability
	 * Using full action names with scd_ prefix for consistency
	 */
	private static $capability_map = array(
		// Wizard actions - require campaign management
		'scd_save_step'                      => 'scd_manage_campaigns',
		'scd_load_data'                      => 'scd_manage_campaigns',
		'scd_load_session'                   => 'scd_manage_campaigns',
		'scd_check_session'                  => 'scd_manage_campaigns',
		'scd_session_status'                 => 'scd_manage_campaigns',
		'scd_complete_wizard'                => 'scd_manage_campaigns',
		'scd_get_summary'                    => 'scd_manage_campaigns',
		'scd_get_product_stats'              => 'scd_manage_campaigns',
		'scd_health_check'                   => 'manage_options',
		'scd_campaign_health'                => 'scd_manage_campaigns',
		'scd_check_conflicts'                => 'scd_manage_campaigns',
		'scd_preview_coverage'               => 'scd_manage_campaigns',
		'scd_calculate_discount_impact'      => 'scd_manage_campaigns',
		'scd_sale_items_filter'              => 'scd_manage_campaigns',
		'scd_profit_margin_warning'          => 'scd_manage_campaigns',
		'scd_recover_session'                => 'scd_manage_campaigns',

		// Tools page actions - require admin capabilities
		'scd_export'                         => 'manage_options',
		'scd_import'                         => 'manage_options',
		'scd_database_maintenance'           => 'manage_options',
		'scd_cache_management'               => 'manage_options',
		'scd_clear_cache'                    => 'manage_options',
		'scd_log_viewer'                     => 'manage_options',

		// Validation actions - require campaign management
		'scd_check_campaign_name'            => 'scd_manage_campaigns',
		'scd_validation_rules_batch'         => 'scd_manage_campaigns',

		// Product actions - require campaign management in wizard context
		'scd_product_search'                 => 'scd_manage_campaigns',

		// Analytics view actions - require view analytics
		'scd_analytics_overview'             => 'scd_view_analytics',
		'scd_analytics_campaign_performance' => 'scd_view_analytics',
		'scd_analytics_revenue_trend'        => 'scd_view_analytics',
		'scd_analytics_top_products'         => 'scd_view_analytics',
		'scd_analytics_activity_feed'        => 'scd_view_analytics',

		// Analytics management actions - require manage analytics
		'scd_analytics_export'               => 'scd_manage_analytics',
		'scd_analytics_refresh_cache'        => 'scd_manage_analytics',

		// Campaign actions - require campaign management
		'scd_get_active_campaigns'           => 'scd_manage_campaigns',

		// Public tracking - no capability required
		'scd_track_event'                    => '',

		// WooCommerce integration - no capability for public discount application
		'scd_apply_discount'                 => '',

		// Discount validation and calculation
		'scd_validate_discounts'             => 'scd_manage_campaigns',
		'scd_validate_discount_rules'        => 'scd_manage_campaigns',
		'scd_get_discount_preview'           => 'scd_manage_campaigns',
		'scd_calculate_discount'             => '',  // Public endpoint
		'scd_preview_discount'               => 'scd_manage_campaigns',

		// Draft handlers
		'scd_save_draft'                     => 'scd_manage_campaigns',
		'scd_delete_draft'                   => 'scd_manage_campaigns',
		'scd_draft_list'                     => 'scd_manage_campaigns',
		'scd_draft_preview'                  => 'scd_manage_campaigns',

		// Dashboard actions
		'scd_main_dashboard_data'            => 'scd_view_analytics',

		// Email/Notification actions - require admin capabilities
		'scd_send_test_email'                => 'manage_options',
		'scd_process_queue'                  => 'manage_options',
		'scd_retry_failed_emails'            => 'manage_options',
		'scd_clear_queue'                    => 'manage_options',

		// Debug handlers
		'scd_debug_log'                      => 'scd_manage_campaigns',
		'scd_log_console'                    => 'scd_manage_campaigns',
	);

	/**
	 * Rate limits map: action => requests per minute
	 * Using full action names with scd_ prefix for consistency
	 */
	private static $rate_limits = array(
		// Strict limits for public endpoints
		'scd_track_event'                    => 30,
		'scd_apply_discount'                 => 20,

		// Discount actions
		'scd_validate_discounts'             => 60, // Allow frequent validation during wizard
		'scd_calculate_discount'             => 30,  // Public but rate limited
		'scd_preview_discount'               => 60,    // Allow frequent preview updates

		// Wizard actions
		'scd_save_step'                      => 30,
		'scd_load_data'                      => 30,
		'scd_load_session'                   => 30,
		'scd_complete_wizard'                => 5,
		'scd_recover_session'                => 5,
		'scd_check_session'                  => 60,
		'scd_get_summary'                    => 30,
		'scd_get_product_stats'              => 30,
		'scd_health_check'                   => 10,
		'scd_campaign_health'                => 10,
		'scd_check_conflicts'                => 20,
		'scd_preview_coverage'               => 20,
		'scd_calculate_discount_impact'      => 20,
		'scd_sale_items_filter'              => 30,
		'scd_profit_margin_warning'          => 20,

		// Validation actions
		'scd_check_campaign_name'            => 10,
		'scd_validation_rules_batch'         => 20,

		// Product actions
		'scd_product_search'                 => 60,  // Increased from 20 to handle wizard usage patterns

		// Analytics actions
		'scd_analytics_overview'             => 30,
		'scd_analytics_campaign_performance' => 30,
		'scd_analytics_revenue_trend'        => 30,
		'scd_analytics_top_products'         => 30,
		'scd_analytics_activity_feed'        => 30,
		'scd_analytics_export'               => 5,
		'scd_analytics_refresh_cache'        => 10,

		// Settings/Admin actions
		'scd_clear_cache'                    => 10,

		// Campaign actions
		'scd_get_active_campaigns'           => 30,

		// Draft handlers
		'scd_save_draft'                     => 10,
		'scd_delete_draft'                   => 10,
		'scd_draft_list'                     => 30,
		'scd_draft_preview'                  => 20,

		// Dashboard actions
		'scd_main_dashboard_data'            => 30,

		// Discount API handlers
		'scd_validate_discount_rules'        => 30,
		'scd_get_discount_preview'           => 30,

		// Debug handlers
		'scd_debug_log'                      => 60,
		'scd_log_console'                    => 60,

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

		// Get unique nonce names
		$unique_nonces = array_unique( array_values( self::$nonce_map ) );

		// Generate nonces for each unique name
		foreach ( $unique_nonces as $nonce_name ) {
			$config[ $nonce_name ] = wp_create_nonce( $nonce_name );
		}

		// Add action to nonce mapping for frontend
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
		// Check if doing AJAX
		if ( ! wp_doing_ajax() ) {
			return new WP_Error(
				'not_ajax',
				'Invalid request context',
				array( 'status' => 400 )
			);
		}

		// Check request size first (before expensive operations)
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

		// Check rate limit
		$rate_check = self::check_rate_limit( $action );
		if ( is_wp_error( $rate_check ) ) {
			return $rate_check;
		}

		// Verify capability
		$capability_check = self::verify_capability( $action );
		if ( is_wp_error( $capability_check ) ) {
			return $capability_check;
		}

		// Additional checks for public endpoints
		if ( $action === 'scd_track_event' ) {
			$public_check = self::verify_public_request( $request );
			if ( is_wp_error( $public_check ) ) {
				return $public_check;
			}
		}

		// Verify request signature if provided (for sensitive operations)
		if ( isset( $request['_signature'] ) && in_array( $action, self::get_signed_actions(), true ) ) {
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
	 * Verify nonce for action
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $action     AJAX action
	 * @param    array  $request    Request data
	 * @return   true|WP_Error              True if valid, WP_Error otherwise
	 */
	private static function verify_nonce( $action, $request ) {
		// Get expected nonce name for this action
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

		// Get nonce from request
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
		// Get required capability
		$required_capability = self::$capability_map[ $action ] ?? 'manage_options';

		// Skip capability check for public endpoints
		if ( empty( $required_capability ) ) {
			return true;
		}

		// Check if user is logged in
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
		if ( strpos( $required_capability, 'scd_' ) === 0 && current_user_can( 'manage_woocommerce' ) ) {
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

		// Get the action to check for special cases
		$action = isset( $request['scd_action'] ) ? $request['scd_action'] : '';

		// Allow larger requests for complete_wizard action (500KB)
		if ( $action === 'complete_wizard' ) {
			$max_size = apply_filters( 'scd_ajax_max_request_size_complete_wizard', 512000 );
		} else {
			$max_size = apply_filters( 'scd_ajax_max_request_size', SCD_Validation_Rules::MAX_REQUEST_SIZE );
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
		// Get rate limit for action
		$limit = self::$rate_limits[ $action ] ?? self::$rate_limits['default'];

		// Create unique identifier combining user ID and IP for better limiting
		$user_id = get_current_user_id();
		$ip      = self::get_client_ip();

		// Combine user and IP for better rate limiting
		$identifier = $user_id > 0
			? 'user_' . $user_id . '_ip_' . md5( $ip )
			: 'ip_' . md5( $ip );

		$transient_key = 'scd_rl_' . $identifier . '_' . $action;

		// Get current request count
		$requests     = get_transient( $transient_key ) ?: array();
		$current_time = time();

		// Clean old requests (sliding window)
		$requests = array_filter(
			$requests,
			function ( $timestamp ) use ( $current_time ) {
				return ( $current_time - $timestamp ) < 60;
			}
		);

		// Check limit
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

		// Add current request
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
		// Check referrer
		$referrer = wp_get_referer();
		if ( ! $referrer || strpos( $referrer, home_url() ) !== 0 ) {
			return new WP_Error(
				'invalid_referrer',
				'Invalid request origin',
				array( 'status' => 403 )
			);
		}

		// Check user agent
		$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
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
			$expected_token = wp_hash( 'scd_tracking_' . date( 'Y-m-d' ) . '_' . self::get_client_ip() );
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
		$remote_addr = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

		// Only trust forwarded headers if request comes from trusted proxy
		// For security, we DON'T trust proxy headers by default
		// Users can add trusted proxies via wp-config.php if needed:
		// define( 'SCD_TRUSTED_PROXIES', array( '10.0.0.1', '192.168.1.1' ) );
		$trusted_proxies = defined( 'SCD_TRUSTED_PROXIES' ) ? SCD_TRUSTED_PROXIES : array();

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
	public static function verify_scd_nonce( $nonce, $action ) {
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
			'scd_save_campaign',
			'scd_delete_campaign',
			'scd_complete_wizard',
			'scd_toggle_campaign_status',

			// Financial/analytics exports
			'scd_export_analytics',
			'scd_generate_report',
			'scd_download_export',

			// Validation operations with sensitive data
			'scd_get_validation_rules_batch',
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
