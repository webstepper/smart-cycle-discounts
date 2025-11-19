<?php
/**
 * Rest Api Manager Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/api/class-rest-api-manager.php
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
 * REST API Manager
 *
 * Manages all REST API endpoints and functionality for the plugin.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/api
 * @author     Webstepper <contact@webstepper.io>
 */
class SCD_REST_API_Manager {

	/**
	 * Cache manager instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Cache_Manager    $cache    Cache manager.
	 */
	private SCD_Cache_Manager $cache;


	/**
	 * API namespace.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $namespace    API namespace.
	 */
	private string $namespace = 'scd/v1';

	/**
	 * API version.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    API version.
	 */
	private string $version = '1.0';

	/**
	 * Authentication manager.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_API_Authentication    $auth_manager    Authentication manager.
	 */
	private SCD_API_Authentication $auth_manager;

	/**
	 * Permissions manager.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_API_Permissions    $permissions_manager    Permissions manager.
	 */
	private SCD_API_Permissions $permissions_manager;

	/**
	 * Logger instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Logger    $logger    Logger instance.
	 */
	private SCD_Logger $logger;

	/**
	 * Registered endpoints.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $endpoints    Registered endpoints.
	 */
	private array $endpoints = array();

	/**
	 * Rate limiting enabled.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      bool    $rate_limiting_enabled    Rate limiting status.
	 */
	private bool $rate_limiting_enabled = true;

	/**
	 * Initialize the REST API manager.
	 *
	 * @since    1.0.0
	 * @param    SCD_Container $container    Container instance.
	 */
	public function __construct(SCD_Cache_Manager $cache, SCD_Container $container) {
		$this->cache = $cache;
		$this->logger = $container->get( 'logger' );
	}

	/**
	 * Initialize the REST API.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function init(): void {
		$this->add_hooks();
		$this->register_core_endpoints();
	}

	/**
	 * Add WordPress hooks.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function add_hooks(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'rest_api_init', array( $this, 'register_fields' ) );
		add_filter( 'rest_pre_dispatch', array( $this, 'handle_cors' ), 10, 3 );
		add_filter( 'rest_authentication_errors', array( $this, 'handle_authentication' ) );
		add_filter( 'rest_request_before_callbacks', array( $this, 'handle_rate_limiting' ), 10, 3 );
		add_filter( 'rest_post_dispatch', array( $this, 'add_response_headers' ), 10, 3 );
	}

	/**
	 * Register all API routes.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_api_info' ),
				'permission_callback' => '__return_true',
			)
		);

		foreach ( $this->endpoints as $endpoint ) {
			$endpoint->register_routes();
		}

		$this->logger->debug(
			'REST API routes registered',
			array(
				'namespace'       => $this->namespace,
				'endpoints_count' => count( $this->endpoints ),
			)
		);
	}

	/**
	 * Register custom fields for existing endpoints.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function register_fields(): void {
		register_rest_field(
			'product',
			'scd_discount_info',
			array(
				'get_callback' => array( $this, 'get_product_discount_info' ),
				'schema'       => array(
					'description' => __( 'Smart Cycle Discounts information for this product.', 'smart-cycle-discounts' ),
					'type'        => 'object',
					'context'     => array( 'view', 'edit' ),
					'properties'  => array(
						'has_active_discount' => array(
							'type'        => 'boolean',
							'description' => __( 'Whether the product has an active discount.', 'smart-cycle-discounts' ),
						),
						'discount_amount'     => array(
							'type'        => 'number',
							'description' => __( 'Current discount amount.', 'smart-cycle-discounts' ),
						),
						'discount_type'       => array(
							'type'        => 'string',
							'description' => __( 'Type of discount (percentage, fixed).', 'smart-cycle-discounts' ),
						),
						'campaign_id'         => array(
							'type'        => 'integer',
							'description' => __( 'ID of the active campaign.', 'smart-cycle-discounts' ),
						),
						'campaign_name'       => array(
							'type'        => 'string',
							'description' => __( 'Name of the active campaign.', 'smart-cycle-discounts' ),
						),
					),
				),
			)
		);

		$this->logger->debug( 'REST API custom fields registered' );
	}

	/**
	 * Handle CORS requests.
	 *
	 * @since    1.0.0
	 * @param    mixed           $result   Response to replace the requested version with.
	 * @param    WP_REST_Server  $server   Server instance.
	 * @param    WP_REST_Request $request  Request used to generate the response.
	 * @return   mixed                       Response or null.
	 */
	public function handle_cors( $result, WP_REST_Server $server, WP_REST_Request $request ) {
		// Only handle our API namespace
		if ( strpos( $request->get_route(), '/' . $this->namespace ) !== 0 ) {
			return $result;
		}

		// Handle preflight requests
		if ( $request->get_method() === 'OPTIONS' ) {
			$response = new WP_REST_Response();
			$response->set_status( 200 );
			return $response;
		}

		return $result;
	}

	/**
	 * Handle authentication.
	 *
	 * @since    1.0.0
	 * @param    WP_Error|null|bool $result    Authentication result.
	 * @return   WP_Error|null|bool               Authentication result.
	 */
	public function handle_authentication( $result ) {
		// Skip if already authenticated or error
		if ( ! empty( $result ) ) {
			return $result;
		}

		// Only handle our API namespace
		$request_uri = $_SERVER['REQUEST_URI'] ?? '';
		if ( strpos( $request_uri, '/wp-json/' . $this->namespace ) === false ) {
			return $result;
		}

		// Skip authentication for now - auth manager doesn't exist
		return $result;
	}

	/**
	 * Handle rate limiting.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Response|WP_HTTP_Response|WP_Error|mixed $response    Result.
	 * @param    array                                            $handler     Route handler.
	 * @param    WP_REST_Request                                  $request     Request object.
	 * @return   WP_REST_Response|WP_HTTP_Response|WP_Error|mixed                 Response.
	 */
	public function handle_rate_limiting( $response, array $handler, WP_REST_Request $request ) {
		// Only handle our API namespace
		if ( strpos( $request->get_route(), '/' . $this->namespace ) !== 0 ) {
			return $response;
		}

		if ( ! $this->rate_limiting_enabled ) {
			return $response;
		}

		$rate_limit_result = $this->check_rate_limit( $request );
		if ( is_wp_error( $rate_limit_result ) ) {
			return $rate_limit_result;
		}

		return $response;
	}

	/**
	 * Add response headers.
	 *
	 * @since    1.0.0
	 * @param    WP_HTTP_Response $result     Result to send to the client.
	 * @param    WP_REST_Server   $server     Server instance.
	 * @param    WP_REST_Request  $request    Request used to generate the response.
	 * @return   WP_HTTP_Response                Modified response.
	 */
	public function add_response_headers( WP_HTTP_Response $result, WP_REST_Server $server, WP_REST_Request $request ): WP_HTTP_Response {
		// Only handle our API namespace
		if ( strpos( $request->get_route(), '/' . $this->namespace ) !== 0 ) {
			return $result;
		}

		// Add CORS headers
		$result->header( 'Access-Control-Allow-Origin', $this->get_allowed_origin( $request ) );
		$result->header( 'Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS' );
		$result->header( 'Access-Control-Allow-Headers', 'Authorization, Content-Type, X-WP-Nonce' );
		$result->header( 'Access-Control-Allow-Credentials', 'true' );

		// Add API version header
		$result->header( 'X-SCD-API-Version', $this->version );

		if ( $this->rate_limiting_enabled ) {
			$rate_limit_info = $this->get_rate_limit_info( $request );
			$result->header( 'X-RateLimit-Limit', (string) $rate_limit_info['limit'] );
			$result->header( 'X-RateLimit-Remaining', (string) $rate_limit_info['remaining'] );
			$result->header( 'X-RateLimit-Reset', (string) $rate_limit_info['reset'] );
		}

		if ( $request->get_method() === 'GET' ) {
			$result->header( 'Cache-Control', 'public, max-age=300' );
			$result->header( 'ETag', $this->generate_etag( $request, $result ) );
		}

		return $result;
	}

	/**
	 * Register an endpoint.
	 *
	 * @since    1.0.0
	 * @param    string $endpoint_class    Endpoint class name.
	 * @return   bool                         Success status.
	 */
	public function register_endpoint( string $endpoint_class ): bool {
		if ( ! class_exists( $endpoint_class ) ) {
			$this->logger->error( 'Endpoint class not found', array( 'class' => $endpoint_class ) );
			return false;
		}

		try {
			$endpoint          = new $endpoint_class( $this->namespace, null, $this->logger );
			$this->endpoints[] = $endpoint;

			$this->logger->debug( 'Endpoint registered', array( 'class' => $endpoint_class ) );
			return true;

		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to register endpoint',
				array(
					'class' => $endpoint_class,
					'error' => $e->getMessage(),
				)
			);
			return false;
		}
	}

	/**
	 * Get API information.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request $request    Request object.
	 * @return   WP_REST_Response               API information.
	 */
	public function get_api_info( WP_REST_Request $request ): WP_REST_Response {
		$info = array(
			'name'           => 'Smart Cycle Discounts API',
			'version'        => $this->version,
			'namespace'      => $this->namespace,
			'description'    => __( 'REST API for Smart Cycle Discounts plugin', 'smart-cycle-discounts' ),
			'authentication' => array(
				'cookie'               => true,
				'application_password' => true,
				'jwt'                  => false, // Skip JWT for now - auth manager doesn't exist
			),
			'endpoints'      => $this->get_endpoint_list(),
			'rate_limiting'  => array(
				'enabled' => $this->rate_limiting_enabled,
				'limits'  => $this->get_rate_limits(),
			),
			'documentation'  => home_url( '/wp-json/' . $this->namespace . '/docs' ),
			'timestamp'      => current_time( 'timestamp' ),
			'timezone'       => wp_timezone_string(),
		);

		return new WP_REST_Response( $info, 200 );
	}

	/**
	 * Get product discount information.
	 *
	 * @since    1.0.0
	 * @param    array           $product    Product data.
	 * @param    string          $field      Field name.
	 * @param    WP_REST_Request $request    Request object.
	 * @return   array                         Discount information.
	 */
	public function get_product_discount_info( array $product, string $field, WP_REST_Request $request ): array {
		$product_id = $product['id'] ?? 0;

		if ( ! $product_id ) {
			return array(
				'has_active_discount' => false,
				'discount_amount'     => 0,
				'discount_type'       => '',
				'campaign_id'         => 0,
				'campaign_name'       => '',
			);
		}

		// This would integrate with the discount engine to get current discount info
		// For now, return placeholder data
		return array(
			'has_active_discount' => false,
			'discount_amount'     => 0,
			'discount_type'       => '',
			'campaign_id'         => 0,
			'campaign_name'       => '',
		);
	}

	/**
	 * Register core endpoints.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function register_core_endpoints(): void {
		$core_endpoints = array(
			'SCD_Campaigns_Controller',
			'SCD_Discounts_Controller',
			'SCD_Analytics_Controller',
		);

		foreach ( $core_endpoints as $endpoint_class ) {
			$this->register_endpoint( $endpoint_class );
		}
	}

	/**
	 * Check rate limit for request.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    WP_REST_Request $request    Request object.
	 * @return   bool|WP_Error                  True if allowed, WP_Error if rate limited.
	 */
	private function check_rate_limit( WP_REST_Request $request ) {
		$client_ip = $this->get_client_ip();
		$user_id   = get_current_user_id();

		$rate_limit_key = 'scd_rate_limit_' . ( $user_id ?: $client_ip );

		$current_requests = $this->cache->get( $rate_limit_key ) ?: 0;
		$rate_limit       = $this->get_rate_limit_for_request( $request );

		if ( $current_requests >= $rate_limit['limit'] ) {
			return new WP_Error(
				'rate_limit_exceeded',
				__( 'Rate limit exceeded. Please try again later.', 'smart-cycle-discounts' ),
				array( 'status' => 429 )
			);
		}

		// Increment counter
		$this->cache->set( $rate_limit_key, $current_requests + 1, $rate_limit['window'] );

		return true;
	}

	/**
	 * Get rate limit for specific request.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    WP_REST_Request $request    Request object.
	 * @return   array                          Rate limit configuration.
	 */
	private function get_rate_limit_for_request( WP_REST_Request $request ): array {
		$defaults = array(
			'limit'  => 100,
			'window' => 3600, // 1 hour
		);

		// Different limits for different endpoints
		$route  = $request->get_route();
		$method = $request->get_method();

		// Analytics endpoints
		if ( strpos( $route, '/analytics' ) !== false ) {
			return array(
				'limit'  => 50,
				'window' => 3600,
			);
		}

		// Campaign creation
		if ( false !== strpos( $route, '/campaigns' ) && 'POST' === $method ) {
			return array(
				'limit'  => 20,
				'window' => 3600,
			);
		}

		// Discount calculation endpoints - more restrictive
		if ( false !== strpos( $route, '/discounts/calculate/bulk' ) ) {
			return array(
				'limit'  => 10,
				'window' => 300,
			); // 10 requests per 5 minutes
		}

		if ( strpos( $route, '/discounts/calculate' ) !== false ) {
			return array(
				'limit'  => 30,
				'window' => 300,
			); // 30 requests per 5 minutes
		}

		if ( strpos( $route, '/discounts/preview' ) !== false ) {
			return array(
				'limit'  => 60,
				'window' => 300,
			); // 60 requests per 5 minutes
		}

		if ( strpos( $route, '/discounts/validate' ) !== false ) {
			return array(
				'limit'  => 60,
				'window' => 300,
			); // 60 requests per 5 minutes
		}

		// Public discount endpoint - stricter
		if ( false !== strpos( $route, '/discounts/best' ) && 'GET' === $method ) {
			return array(
				'limit'  => 100,
				'window' => 3600,
			); // 100 per hour
		}

		return $defaults;
	}

	/**
	 * Get rate limit information.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    WP_REST_Request $request    Request object.
	 * @return   array                          Rate limit info.
	 */
	private function get_rate_limit_info( WP_REST_Request $request ): array {
		$client_ip      = $this->get_client_ip();
		$user_id        = get_current_user_id();
		$rate_limit_key = 'scd_rate_limit_' . ( $user_id ?: $client_ip );

		$current_requests = $this->cache->get( $rate_limit_key ) ?: 0;
		$rate_limit       = $this->get_rate_limit_for_request( $request );

		return array(
			'limit'     => $rate_limit['limit'],
			'remaining' => max( 0, $rate_limit['limit'] - $current_requests ),
			'reset'     => time() + $rate_limit['window'],
		);
	}

	/**
	 * Get client IP address.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   string    Client IP address.
	 */
	private function get_client_ip(): string {
		$ip_keys = array( 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' );

		foreach ( $ip_keys as $key ) {
			if ( array_key_exists( $key, $_SERVER ) === true ) {
				foreach ( explode( ',', $_SERVER[ $key ] ) as $ip ) {
					$ip = trim( $ip );
					if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) !== false ) {
						return $ip;
					}
				}
			}
		}

		return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
	}

	/**
	 * Get allowed origin for CORS.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    WP_REST_Request $request    Request object.
	 * @return   string                         Allowed origin.
	 */
	private function get_allowed_origin( WP_REST_Request $request ): string {
		$origin          = $request->get_header( 'origin' );
		$allowed_origins = apply_filters(
			'scd_api_allowed_origins',
			array(
				home_url(),
				admin_url(),
			)
		);

		if ( in_array( $origin, $allowed_origins ) ) {
			return $origin;
		}

		return home_url();
	}

	/**
	 * Get list of available endpoints.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   array    Endpoint list.
	 */
	private function get_endpoint_list(): array {
		$endpoints = array();

		foreach ( $this->endpoints as $endpoint ) {
			if ( method_exists( $endpoint, 'get_endpoint_info' ) ) {
				$endpoints[] = $endpoint->get_endpoint_info();
			}
		}

		return $endpoints;
	}

	/**
	 * Get rate limits configuration.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   array    Rate limits.
	 */
	private function get_rate_limits(): array {
		return array(
			'default'          => array(
				'limit'  => 100,
				'window' => 3600,
			),
			'analytics'        => array(
				'limit'  => 50,
				'window' => 3600,
			),
			'campaigns_create' => array(
				'limit'  => 20,
				'window' => 3600,
			),
		);
	}

	/**
	 * Get API namespace.
	 *
	 * @since    1.0.0
	 * @return   string    API namespace.
	 */
	public function get_namespace(): string {
		return $this->namespace;
	}

	/**
	 * Get API version.
	 *
	 * @since    1.0.0
	 * @return   string    API version.
	 */
	public function get_version(): string {
		return $this->version;
	}

	/**
	 * Enable or disable rate limiting.
	 *
	 * @since    1.0.0
	 * @param    bool $enabled    Whether to enable rate limiting.
	 * @return   void
	 */
	public function set_rate_limiting( bool $enabled ): void {
		$this->rate_limiting_enabled = $enabled;
	}

	/**
	 * Generate ETag for response.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    WP_REST_Request  $request    Request object.
	 * @param    WP_HTTP_Response $response   Response object.
	 * @return   string                          ETag value.
	 */
	private function generate_etag( WP_REST_Request $request, WP_HTTP_Response $response ): string {
		$content = $response->get_data();
		return '"' . md5( serialize( $content ) ) . '"';
	}

	/**
	 * Pre-dispatch handler (placeholder for compatibility).
	 *
	 * @since    1.0.0
	 * @param    mixed           $result   Response to replace the requested version with.
	 * @param    WP_REST_Server  $server   Server instance.
	 * @param    WP_REST_Request $request  Request used to generate the response.
	 * @return   mixed                       Response or null.
	 */
	public function pre_dispatch( $result, WP_REST_Server $server, WP_REST_Request $request ) {
		return $result;
	}

	/**
	 * Post-dispatch handler (placeholder for compatibility).
	 *
	 * @since    1.0.0
	 * @param    WP_HTTP_Response $result     Result to send to the client.
	 * @param    WP_REST_Server   $server     Server instance.
	 * @param    WP_REST_Request  $request    Request used to generate the response.
	 * @return   WP_HTTP_Response                Modified response.
	 */
	public function post_dispatch( WP_HTTP_Response $result, WP_REST_Server $server, WP_REST_Request $request ): WP_HTTP_Response {
		return $this->add_response_headers( $result, $server, $request );
	}

	/**
	 * Authenticate request (placeholder for compatibility).
	 *
	 * @since    1.0.0
	 * @param    WP_Error|null|bool $result    Authentication result.
	 * @return   WP_Error|null|bool               Authentication result.
	 */
	public function authenticate_request( $result ) {
		return $this->handle_authentication( $result );
	}
}
