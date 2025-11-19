<?php
/**
 * Api Authentication Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/api/class-api-authentication.php
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
 * API Authentication Manager
 *
 * Handles authentication for REST API requests.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/api
 * @author     Webstepper <contact@webstepper.io>
 */
class SCD_API_Authentication {

	/**
	 * Logger instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Logger    $logger    Logger instance.
	 */
	private SCD_Logger $logger;

	/**
	 * JWT enabled status.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      bool    $jwt_enabled    Whether JWT is enabled.
	 */
	private bool $jwt_enabled = false;

	/**
	 * JWT secret key.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $jwt_secret    JWT secret key.
	 */
	private string $jwt_secret;

	/**
	 * Supported authentication methods.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $auth_methods    Supported authentication methods.
	 */
	private array $auth_methods = array(
		'cookie',
		'application_password',
		'jwt',
		'api_key',
	);

	/**
	 * Initialize the authentication manager.
	 *
	 * @since    1.0.0
	 * @param    SCD_Logger $logger    Logger instance.
	 */
	public function __construct( SCD_Logger $logger ) {
		$this->logger      = $logger;
		$this->jwt_secret  = $this->get_jwt_secret();
		$this->jwt_enabled = ! empty( $this->jwt_secret );
	}

	/**
	 * Initialize authentication.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function init(): void {
		$this->add_hooks();

		$this->logger->debug(
			'API authentication initialized',
			array(
				'jwt_enabled' => $this->jwt_enabled,
				'methods'     => $this->auth_methods,
			)
		);
	}

	/**
	 * Add WordPress hooks.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function add_hooks(): void {
		// Add JWT authentication if enabled
		if ( $this->jwt_enabled ) {
			add_filter( 'determine_current_user', array( $this, 'determine_current_user' ), 20 );
		}

		// Add API key authentication
		add_filter( 'determine_current_user', array( $this, 'authenticate_api_key' ), 15 );
	}

	/**
	 * Authenticate API request.
	 *
	 * @since    1.0.0
	 * @return   WP_Error|null|bool    Authentication result.
	 */
	public function authenticate() {
		// Try different authentication methods in order of preference
		$auth_methods = array(
			'authenticate_jwt',
			'authenticate_application_password',
			'authenticate_api_key',
			'authenticate_cookie',
		);

		foreach ( $auth_methods as $method ) {
			if ( method_exists( $this, $method ) ) {
				$result = $this->$method();

				if ( ! is_wp_error( $result ) && null !== $result && false !== $result ) {
					$this->logger->debug( 'Authentication successful', array( 'method' => $method ) );
					return $result;
				}

				if ( is_wp_error( $result ) ) {
					$this->logger->warning(
						'Authentication failed',
						array(
							'method' => $method,
							'error'  => $result->get_error_message(),
						)
					);
					return $result;
				}
			}
		}

		// No authentication method succeeded
		return new WP_Error(
			'rest_not_authenticated',
			__( 'You are not currently logged in.', 'smart-cycle-discounts' ),
			array( 'status' => 401 )
		);
	}

	/**
	 * Authenticate using JWT token.
	 *
	 * @since    1.0.0
	 * @return   WP_User|WP_Error|null    User object, error, or null.
	 */
	public function authenticate_jwt() {
		if ( ! $this->jwt_enabled ) {
			return null;
		}

		$token = $this->get_jwt_token();
		if ( ! $token ) {
			return null;
		}

		try {
			$payload = $this->decode_jwt_token( $token );

			if ( ! $payload || ! isset( $payload['user_id'] ) ) {
				return new WP_Error(
					'jwt_invalid_token',
					__( 'Invalid JWT token.', 'smart-cycle-discounts' ),
					array( 'status' => 401 )
				);
			}

			if ( isset( $payload['exp'] ) && time() > $payload['exp'] ) {
				return new WP_Error(
					'jwt_token_expired',
					__( 'JWT token has expired.', 'smart-cycle-discounts' ),
					array( 'status' => 401 )
				);
			}

			$user = get_user_by( 'id', $payload['user_id'] );
			if ( ! $user ) {
				return new WP_Error(
					'jwt_user_not_found',
					__( 'User not found.', 'smart-cycle-discounts' ),
					array( 'status' => 401 )
				);
			}

			return $user;

		} catch ( Exception $e ) {
			return new WP_Error(
				'jwt_decode_error',
				__( 'Failed to decode JWT token.', 'smart-cycle-discounts' ),
				array( 'status' => 401 )
			);
		}
	}

	/**
	 * Authenticate using application password.
	 *
	 * @since    1.0.0
	 * @return   WP_User|WP_Error|null    User object, error, or null.
	 */
	public function authenticate_application_password() {
		if ( ! function_exists( 'wp_is_application_passwords_available' ) ||
			! wp_is_application_passwords_available() ) {
			return null;
		}

		$auth_header = $this->get_authorization_header();
		if ( ! $auth_header || 0 !== strpos( $auth_header, 'Basic ' ) ) {
			return null;
		}

		// Decode basic auth
		$credentials = base64_decode( substr( $auth_header, 6 ) );
		if ( ! $credentials || false === strpos( $credentials, ':' ) ) {
			return null;
		}

		list($username, $password) = explode( ':', $credentials, 2 );

		// Authenticate with application password
		$user = wp_authenticate_application_password( null, $username, $password );

		if ( is_wp_error( $user ) ) {
			return $user;
		}

		return $user ?: null;
	}

	/**
	 * Authenticate using API key.
	 *
	 * @since    1.0.0
	 * @param    int|false $user_id    Current user ID.
	 * @return   int|false                User ID or false.
	 */
	public function authenticate_api_key( $user_id = false ) {
		// Skip if already authenticated
		if ( $user_id ) {
			return $user_id;
		}

		$api_key = $this->get_api_key();
		if ( ! $api_key ) {
			return false;
		}

		// Validate API key
		$user_id = $this->validate_api_key( $api_key );
		if ( $user_id ) {
			$this->logger->debug( 'API key authentication successful', array( 'user_id' => $user_id ) );
			return $user_id;
		}

		return false;
	}

	/**
	 * Authenticate using cookie.
	 *
	 * @since    1.0.0
	 * @return   WP_User|null    User object or null.
	 */
	public function authenticate_cookie() {
		// Use WordPress cookie authentication
		$user_id = get_current_user_id();

		if ( $user_id ) {
			return get_user_by( 'id', $user_id );
		}

		return null;
	}

	/**
	 * Determine current user for JWT authentication.
	 *
	 * @since    1.0.0
	 * @param    int|false $user_id    Current user ID.
	 * @return   int|false                User ID or false.
	 */
	public function determine_current_user( $user_id = false ) {
		// Skip if already authenticated
		if ( $user_id ) {
			return $user_id;
		}

		// Only handle REST API requests
		if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) {
			return $user_id;
		}

		$user = $this->authenticate_jwt();
		if ( $user && ! is_wp_error( $user ) ) {
			return $user->ID;
		}

		return $user_id;
	}

	/**
	 * Generate JWT token for user.
	 *
	 * @since    1.0.0
	 * @param    int   $user_id    User ID.
	 * @param    array $claims     Additional claims.
	 * @return   string|WP_Error      JWT token or error.
	 */
	public function generate_jwt_token( int $user_id, array $claims = array() ) {
		if ( ! $this->jwt_enabled ) {
			return new WP_Error(
				'jwt_not_enabled',
				__( 'JWT authentication is not enabled.', 'smart-cycle-discounts' )
			);
		}

		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return new WP_Error(
				'user_not_found',
				__( 'User not found.', 'smart-cycle-discounts' )
			);
		}

		$issued_at  = time();
		$expires_at = $issued_at + ( 24 * 60 * 60 ); // 24 hours

		$payload = array_merge(
			array(
				'iss'        => home_url(),
				'aud'        => home_url(),
				'iat'        => $issued_at,
				'exp'        => $expires_at,
				'user_id'    => $user_id,
				'username'   => $user->user_login,
				'user_email' => $user->user_email,
			),
			$claims
		);

		try {
			return $this->encode_jwt_token( $payload );
		} catch ( Exception $e ) {
			return new WP_Error(
				'jwt_encode_error',
				__( 'Failed to generate JWT token.', 'smart-cycle-discounts' )
			);
		}
	}

	/**
	 * Generate API key for user.
	 *
	 * @since    1.0.0
	 * @param    int    $user_id      User ID.
	 * @param    string $description  API key description.
	 * @return   string|WP_Error         API key or error.
	 */
	public function generate_api_key( int $user_id, string $description = '' ): string|WP_Error {
		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return new WP_Error(
				'user_not_found',
				__( 'User not found.', 'smart-cycle-discounts' )
			);
		}

		// Generate secure API key
		$api_key      = 'scd_' . wp_generate_password( 32, false );
		$api_key_hash = wp_hash_password( $api_key );

		// Store API key
		$api_keys   = get_user_meta( $user_id, 'scd_api_keys', true ) ?: array();
		$api_keys[] = array(
			'key_hash'    => $api_key_hash,
			'description' => $description,
			'created_at'  => current_time( 'mysql' ),
			'last_used'   => null,
			'usage_count' => 0,
		);

		update_user_meta( $user_id, 'scd_api_keys', $api_keys );

		$this->logger->info(
			'API key generated',
			array(
				'user_id'     => $user_id,
				'description' => $description,
			)
		);

		return $api_key;
	}

	/**
	 * Revoke API key.
	 *
	 * @since    1.0.0
	 * @param    int    $user_id    User ID.
	 * @param    string $api_key    API key to revoke.
	 * @return   bool                  Success status.
	 */
	public function revoke_api_key( int $user_id, string $api_key ): bool {
		$api_keys = get_user_meta( $user_id, 'scd_api_keys', true ) ?: array();

		foreach ( $api_keys as $index => $stored_key ) {
			if ( wp_check_password( $api_key, $stored_key['key_hash'] ) ) {
				unset( $api_keys[ $index ] );
				update_user_meta( $user_id, 'scd_api_keys', array_values( $api_keys ) );

				$this->logger->info( 'API key revoked', array( 'user_id' => $user_id ) );
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if JWT is enabled.
	 *
	 * @since    1.0.0
	 * @return   bool    JWT enabled status.
	 */
	public function is_jwt_enabled(): bool {
		return $this->jwt_enabled;
	}

	/**
	 * Get JWT token from request.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   string|null    JWT token or null.
	 */
	private function get_jwt_token(): ?string {
		// Check Authorization header
		$auth_header = $this->get_authorization_header();
		if ( $auth_header && 0 === strpos( $auth_header, 'Bearer ' ) ) {
			return substr( $auth_header, 7 );
		}

		if ( get_option( 'scd_allow_jwt_via_get', false ) || apply_filters( 'scd_allow_jwt_via_get', false ) ) {
			return isset( $_GET['jwt'] ) ? sanitize_text_field( wp_unslash( $_GET['jwt'] ) ) : null;
		}

		return null;
	}

	/**
	 * Get API key from request.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   string|null    API key or null.
	 */
	private function get_api_key(): ?string {
		$api_key = $_SERVER['HTTP_X_API_KEY'] ?? null;
		if ( $api_key ) {
			return $api_key;
		}

		if ( get_option( 'scd_allow_api_key_via_get', false ) || apply_filters( 'scd_allow_api_key_via_get', false ) ) {
			return isset( $_GET['api_key'] ) ? sanitize_text_field( wp_unslash( $_GET['api_key'] ) ) : null;
		}

		return null;
	}

	/**
	 * Get authorization header.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   string|null    Authorization header or null.
	 */
	private function get_authorization_header(): ?string {
		$headers = array(
			'HTTP_AUTHORIZATION',
			'REDIRECT_HTTP_AUTHORIZATION',
		);

		foreach ( $headers as $header ) {
			if ( isset( $_SERVER[ $header ] ) ) {
				return $_SERVER[ $header ];
			}
		}

		if ( function_exists( 'getallheaders' ) ) {
			$headers = getallheaders();
			if ( isset( $headers['Authorization'] ) ) {
				return $headers['Authorization'];
			}
		}

		return null;
	}

	/**
	 * Validate API key.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $api_key    API key to validate.
	 * @return   int|false             User ID or false.
	 */
	private function validate_api_key( string $api_key ) {
		global $wpdb;

		// Optimized approach: Query only user IDs that have API keys
		$user_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT user_id 
                FROM {$wpdb->usermeta} 
                WHERE meta_key = %s",
				'scd_api_keys'
			)
		);

		if ( empty( $user_ids ) ) {
			return false;
		}

		foreach ( $user_ids as $user_id ) {
			$api_keys = get_user_meta( $user_id, 'scd_api_keys', true ) ?: array();

			foreach ( $api_keys as $index => $stored_key ) {
				if ( wp_check_password( $api_key, $stored_key['key_hash'] ) ) {
					$api_keys[ $index ]['last_used']   = current_time( 'mysql' );
					$api_keys[ $index ]['usage_count'] = ( $api_keys[ $index ]['usage_count'] ?? 0 ) + 1;
					update_user_meta( $user_id, 'scd_api_keys', $api_keys );

					return (int) $user_id;
				}
			}
		}

		return false;
	}

	/**
	 * Get JWT secret key.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   string    JWT secret key.
	 */
	private function get_jwt_secret(): string {
		// Try to get from WordPress constants
		if ( defined( 'SCD_JWT_SECRET' ) ) {
			return SCD_JWT_SECRET;
		}

		// Try to get from WordPress auth keys
		if ( defined( 'AUTH_KEY' ) ) {
			return AUTH_KEY;
		}

		// Generate and store a secret
		$secret = get_option( 'scd_jwt_secret' );
		if ( ! $secret ) {
			$secret = wp_generate_password( 64, true, true );
			update_option( 'scd_jwt_secret', $secret );
		}

		return $secret;
	}

	/**
	 * Encode JWT token.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $payload    Token payload.
	 * @return   string               Encoded token.
	 */
	private function encode_jwt_token( array $payload ): string {
		// Simple JWT implementation for basic functionality
		$header = array(
			'typ' => 'JWT',
			'alg' => 'HS256',
		);

		$header_encoded  = $this->base64url_encode( wp_json_encode( $header ) );
		$payload_encoded = $this->base64url_encode( wp_json_encode( $payload ) );

		$signature         = hash_hmac(
			'sha256',
			$header_encoded . '.' . $payload_encoded,
			$this->jwt_secret,
			true
		);
		$signature_encoded = $this->base64url_encode( $signature );

		return $header_encoded . '.' . $payload_encoded . '.' . $signature_encoded;
	}

	/**
	 * Decode JWT token.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $token    JWT token.
	 * @return   array|null          Decoded payload or null.
	 */
	private function decode_jwt_token( string $token ): ?array {
		$parts = explode( '.', $token );
		if ( 3 !== count( $parts ) ) {
			return null;
		}

		list($header_encoded, $payload_encoded, $signature_encoded) = $parts;

		// Verify signature
		$signature          = $this->base64url_decode( $signature_encoded );
		$expected_signature = hash_hmac(
			'sha256',
			$header_encoded . '.' . $payload_encoded,
			$this->jwt_secret,
			true
		);

		if ( ! hash_equals( $signature, $expected_signature ) ) {
			return null;
		}

		// Decode payload
		$payload = json_decode( $this->base64url_decode( $payload_encoded ), true );
		return $payload ?: null;
	}

	/**
	 * Base64 URL encode.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $data    Data to encode.
	 * @return   string             Encoded data.
	 */
	private function base64url_encode( string $data ): string {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}

	/**
	 * Base64 URL decode.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $data    Data to decode.
	 * @return   string             Decoded data.
	 */
	private function base64url_decode( string $data ): string {
		return base64_decode( str_pad( strtr( $data, '-_', '+/' ), strlen( $data ) % 4, '=', STR_PAD_RIGHT ) );
	}
}
