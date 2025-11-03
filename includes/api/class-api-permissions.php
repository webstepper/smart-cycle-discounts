<?php
/**
 * Api Permissions Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/api/class-api-permissions.php
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


/**
 * API Permissions Manager
 *
 * Handles permissions and authorization for REST API requests.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/api
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_API_Permissions {

	/**
	 * Capability manager instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Admin_Capability_Manager    $capability_manager    Capability manager.
	 */
	private SCD_Admin_Capability_Manager $capability_manager;

	/**
	 * Logger instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Logger    $logger    Logger instance.
	 */
	private SCD_Logger $logger;

	/**
	 * API capabilities mapping.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $api_capabilities    API capabilities mapping.
	 */
	private array $api_capabilities = array(
		// Campaign endpoints
		'campaigns' => array(
			'GET'    => 'view_campaigns',
			'POST'   => 'create_campaigns',
			'PUT'    => 'edit_campaigns',
			'PATCH'  => 'edit_campaigns',
			'DELETE' => 'delete_campaigns',
		),

		// Discount endpoints
		'discounts' => array(
			'GET'    => 'view_discounts',
			'POST'   => 'manage_discounts',
			'PUT'    => 'manage_discounts',
			'PATCH'  => 'manage_discounts',
			'DELETE' => 'manage_discounts',
		),

		// Analytics endpoints
		'analytics' => array(
			'GET'    => 'view_analytics',
			'POST'   => 'manage_analytics',
			'PUT'    => 'manage_analytics',
			'PATCH'  => 'manage_analytics',
			'DELETE' => 'manage_analytics',
		),

		// Settings endpoints
		'settings'  => array(
			'GET'    => 'view_settings',
			'POST'   => 'manage_settings',
			'PUT'    => 'manage_settings',
			'PATCH'  => 'manage_settings',
			'DELETE' => 'manage_settings',
		),
	);

	/**
	 * Public endpoints that don't require authentication.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $public_endpoints    Public endpoints.
	 */
	private array $public_endpoints = array(
		'GET:/info',
		'GET:/docs',
		'GET:/status',
	);

	/**
	 * Rate limit exemptions by capability.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $rate_limit_exemptions    Rate limit exemptions.
	 */
	private array $rate_limit_exemptions = array(
		'manage_options' => true, // WordPress administrators
		'scd_admin'      => true,       // Plugin administrators
	);

	/**
	 * Initialize the permissions manager.
	 *
	 * @since    1.0.0
	 * @param    SCD_Admin_Capability_Manager $capability_manager    Capability manager.
	 * @param    SCD_Logger                   $logger                Logger instance.
	 */
	public function __construct(
		SCD_Admin_Capability_Manager $capability_manager,
		SCD_Logger $logger
	) {
		$this->capability_manager = $capability_manager;
		$this->logger             = $logger;
	}

	/**
	 * Initialize permissions.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function init(): void {
		$this->logger->debug( 'API permissions manager initialized' );
	}

	/**
	 * Check if user can access endpoint.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request $request    Request object.
	 * @return   bool|WP_Error                  True if allowed, WP_Error if denied.
	 */
	public function check_permissions( WP_REST_Request $request ) {
		$route  = $request->get_route();
		$method = $request->get_method();

		// Check if endpoint is public
		if ( $this->is_public_endpoint( $method, $route ) ) {
			return true;
		}

		// Check if user is authenticated
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return new WP_Error(
				'rest_not_authenticated',
				__( 'You are not currently logged in.', 'smart-cycle-discounts' ),
				array( 'status' => 401 )
			);
		}

		// Get required capability for endpoint
		$required_capability = $this->get_required_capability( $method, $route );
		if ( ! $required_capability ) {
			// No specific capability required, but authentication is needed
			return true;
		}

		// Check if user has required capability
		if ( ! $this->capability_manager->current_user_can( $required_capability ) ) {
			$this->logger->warning(
				'API access denied',
				array(
					'user_id'             => $user_id,
					'route'               => $route,
					'method'              => $method,
					'required_capability' => $required_capability,
				)
			);

			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to access this resource.', 'smart-cycle-discounts' ),
				array( 'status' => 403 )
			);
		}

		// Check resource-specific permissions
		$resource_check = $this->check_resource_permissions( $request );
		if ( is_wp_error( $resource_check ) ) {
			return $resource_check;
		}

		$this->logger->debug(
			'API access granted',
			array(
				'user_id'    => $user_id,
				'route'      => $route,
				'method'     => $method,
				'capability' => $required_capability,
			)
		);

		return true;
	}

	/**
	 * Check permissions for campaigns endpoint.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request $request    Request object.
	 * @return   bool|WP_Error                  Permission result.
	 */
	public function check_campaigns_permissions( WP_REST_Request $request ) {
		$method      = $request->get_method();
		$campaign_id = $request->get_param( 'id' );

		// For specific campaign operations, check ownership or admin rights
		if ( $campaign_id && in_array( $method, array( 'PUT', 'PATCH', 'DELETE' ) ) ) {
			return $this->check_campaign_ownership( $campaign_id );
		}

		return $this->check_permissions( $request );
	}

	/**
	 * Check permissions for analytics endpoint.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request $request    Request object.
	 * @return   bool|WP_Error                  Permission result.
	 */
	public function check_analytics_permissions( WP_REST_Request $request ) {
		// Analytics requires special view_analytics capability
		if ( ! $this->capability_manager->current_user_can( 'view_analytics' ) ) {
			return new WP_Error(
				'rest_forbidden_analytics',
				__( 'You do not have permission to view analytics data.', 'smart-cycle-discounts' ),
				array( 'status' => 403 )
			);
		}

		return $this->check_permissions( $request );
	}

	/**
	 * Check permissions for settings endpoint.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request $request    Request object.
	 * @return   bool|WP_Error                  Permission result.
	 */
	public function check_settings_permissions( WP_REST_Request $request ) {
		$method = $request->get_method();

		// Settings modifications require admin capabilities
		if ( in_array( $method, array( 'POST', 'PUT', 'PATCH', 'DELETE' ) ) ) {
			if ( ! $this->capability_manager->current_user_can( 'manage_settings' ) ) {
				return new WP_Error(
					'rest_forbidden_settings',
					__( 'You do not have permission to modify settings.', 'smart-cycle-discounts' ),
					array( 'status' => 403 )
				);
			}
		}

		return $this->check_permissions( $request );
	}

	/**
	 * Check if user is exempt from rate limiting.
	 *
	 * @since    1.0.0
	 * @param    int $user_id    User ID.
	 * @return   bool               True if exempt.
	 */
	public function is_rate_limit_exempt( int $user_id ): bool {
		if ( ! $user_id ) {
			return false;
		}

		foreach ( $this->rate_limit_exemptions as $capability => $exempt ) {
			if ( $exempt && $this->capability_manager->user_can( $user_id, $capability ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get API capabilities for user.
	 *
	 * @since    1.0.0
	 * @param    int $user_id    User ID.
	 * @return   array              User's API capabilities.
	 */
	public function get_user_api_capabilities( int $user_id ): array {
		$capabilities = array();

		foreach ( $this->api_capabilities as $endpoint => $methods ) {
			foreach ( $methods as $method => $capability ) {
				if ( $this->capability_manager->user_can( $user_id, $capability ) ) {
					$capabilities[] = "{$method}:{$endpoint}";
				}
			}
		}

		return $capabilities;
	}

	/**
	 * Validate API key permissions.
	 *
	 * @since    1.0.0
	 * @param    string $api_key    API key.
	 * @param    string $endpoint   Endpoint being accessed.
	 * @param    string $method     HTTP method.
	 * @return   bool                  True if allowed.
	 */
	public function validate_api_key_permissions( string $api_key, string $endpoint, string $method ): bool {
		// Get user ID from API key
		$user_id = $this->get_user_id_from_api_key( $api_key );
		if ( ! $user_id ) {
			return false;
		}

		// Check if user has required capability
		$required_capability = $this->get_required_capability( $method, $endpoint );
		if ( ! $required_capability ) {
			return true; // No specific capability required
		}

		return $this->capability_manager->user_can( $user_id, $required_capability );
	}

	/**
	 * Get permission schema for OpenAPI documentation.
	 *
	 * @since    1.0.0
	 * @return   array    Permission schema.
	 */
	public function get_permission_schema(): array {
		return array(
			'authentication'   => array(
				'methods' => array(
					'cookie'               => array(
						'description'  => 'WordPress cookie authentication',
						'required_for' => 'admin_access',
					),
					'application_password' => array(
						'description'  => 'WordPress application passwords',
						'required_for' => 'programmatic_access',
					),
					'jwt'                  => array(
						'description'  => 'JSON Web Token authentication',
						'required_for' => 'mobile_apps',
					),
					'api_key'              => array(
						'description'  => 'Custom API key authentication',
						'required_for' => 'third_party_integrations',
					),
				),
			),
			'capabilities'     => $this->api_capabilities,
			'public_endpoints' => $this->public_endpoints,
			'rate_limiting'    => array(
				'enabled'    => true,
				'exemptions' => array_keys( $this->rate_limit_exemptions ),
			),
		);
	}

	/**
	 * Check if endpoint is public.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $method    HTTP method.
	 * @param    string $route     Route path.
	 * @return   bool                 True if public.
	 */
	private function is_public_endpoint( string $method, string $route ): bool {
		$endpoint_key = $method . ':' . $route;

		foreach ( $this->public_endpoints as $public_endpoint ) {
			if ( fnmatch( $public_endpoint, $endpoint_key ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get required capability for endpoint.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $method    HTTP method.
	 * @param    string $route     Route path.
	 * @return   string|null          Required capability or null.
	 */
	private function get_required_capability( string $method, string $route ): ?string {
		// Extract endpoint from route
		$endpoint = $this->extract_endpoint_from_route( $route );

		if ( ! $endpoint || ! isset( $this->api_capabilities[ $endpoint ] ) ) {
			return null;
		}

		return $this->api_capabilities[ $endpoint ][ $method ] ?? null;
	}

	/**
	 * Extract endpoint name from route.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $route    Route path.
	 * @return   string|null         Endpoint name or null.
	 */
	private function extract_endpoint_from_route( string $route ): ?string {
		// Remove namespace prefix
		$route = preg_replace( '#^/scd/v1/?#', '', $route );

		// Extract first path segment
		$segments = explode( '/', trim( $route, '/' ) );
		return $segments[0] ?? null;
	}

	/**
	 * Check resource-specific permissions.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    WP_REST_Request $request    Request object.
	 * @return   bool|WP_Error                  Permission result.
	 */
	private function check_resource_permissions( WP_REST_Request $request ) {
		$route   = $request->get_route();
		$method  = $request->get_method();
		$user_id = get_current_user_id();

		// Check for resource ID in route
		if ( preg_match( '#/(\d+)(?:/|$)#', $route, $matches ) ) {
			$resource_id = (int) $matches[1];

			// For campaigns, check ownership
			if ( strpos( $route, '/campaigns/' ) !== false ) {
				return $this->check_campaign_ownership( $resource_id );
			}
		}

		return true;
	}

	/**
	 * Check campaign ownership.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    int $campaign_id    Campaign ID.
	 * @return   bool|WP_Error          Permission result.
	 */
	private function check_campaign_ownership( int $campaign_id ) {
		$user_id = get_current_user_id();

		// Must be logged in
		if ( ! $user_id ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You must be logged in to access campaigns.', 'smart-cycle-discounts' ),
				array( 'status' => 401 )
			);
		}

		// Administrators can access all campaigns
		if ( $this->capability_manager->current_user_can( 'manage_options' ) ||
			$this->capability_manager->current_user_can( 'scd_admin' ) ) {
			return true;
		}

		// Must have basic campaign capability
		if ( ! $this->capability_manager->current_user_can( 'edit_campaigns' ) ) {
			return new WP_Error(
				'rest_forbidden_capability',
				__( 'You do not have permission to manage campaigns.', 'smart-cycle-discounts' ),
				array( 'status' => 403 )
			);
		}

		// Get campaign to check ownership
		global $wpdb;
		$table_name = $wpdb->prefix . 'scd_campaigns';

		$campaign_owner = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT created_by FROM {$table_name} WHERE id = %d AND deleted_at IS NULL",
				$campaign_id
			)
		);

		if ( $campaign_owner === null ) {
			return new WP_Error(
				'rest_campaign_not_found',
				__( 'Campaign not found.', 'smart-cycle-discounts' ),
				array( 'status' => 404 )
			);
		}

		// Check if user owns the campaign
		if ( (int) $campaign_owner === $user_id ) {
			return true;
		}

		return new WP_Error(
			'rest_forbidden_campaign',
			__( 'You can only access campaigns you created.', 'smart-cycle-discounts' ),
			array( 'status' => 403 )
		);
	}

	/**
	 * Get user ID from API key.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $api_key    API key.
	 * @return   int|false             User ID or false.
	 */
	private function get_user_id_from_api_key( string $api_key ) {
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

		// Check each user's API keys
		foreach ( $user_ids as $user_id ) {
			$api_keys = get_user_meta( $user_id, 'scd_api_keys', true ) ?: array();

			foreach ( $api_keys as $stored_key ) {
				if ( wp_check_password( $api_key, $stored_key['key_hash'] ) ) {
					return (int) $user_id;
				}
			}
		}

		return false;
	}

	/**
	 * Add custom capability.
	 *
	 * @since    1.0.0
	 * @param    string $endpoint      Endpoint name.
	 * @param    string $method        HTTP method.
	 * @param    string $capability    Required capability.
	 * @return   void
	 */
	public function add_capability_mapping( string $endpoint, string $method, string $capability ): void {
		if ( ! isset( $this->api_capabilities[ $endpoint ] ) ) {
			$this->api_capabilities[ $endpoint ] = array();
		}

		$this->api_capabilities[ $endpoint ][ $method ] = $capability;

		$this->logger->debug(
			'API capability mapping added',
			array(
				'endpoint'   => $endpoint,
				'method'     => $method,
				'capability' => $capability,
			)
		);
	}

	/**
	 * Add public endpoint.
	 *
	 * @since    1.0.0
	 * @param    string $method    HTTP method.
	 * @param    string $route     Route pattern.
	 * @return   void
	 */
	public function add_public_endpoint( string $method, string $route ): void {
		$endpoint_key = $method . ':' . $route;

		if ( ! in_array( $endpoint_key, $this->public_endpoints ) ) {
			$this->public_endpoints[] = $endpoint_key;

			$this->logger->debug(
				'Public endpoint added',
				array(
					'method' => $method,
					'route'  => $route,
				)
			);
		}
	}

	/**
	 * Add rate limit exemption.
	 *
	 * @since    1.0.0
	 * @param    string $capability    Capability that grants exemption.
	 * @return   void
	 */
	public function add_rate_limit_exemption( string $capability ): void {
		$this->rate_limit_exemptions[ $capability ] = true;

		$this->logger->debug( 'Rate limit exemption added', array( 'capability' => $capability ) );
	}

	/**
	 * Get all API capabilities.
	 *
	 * @since    1.0.0
	 * @return   array    API capabilities mapping.
	 */
	public function get_api_capabilities(): array {
		return $this->api_capabilities;
	}

	/**
	 * Get public endpoints.
	 *
	 * @since    1.0.0
	 * @return   array    Public endpoints.
	 */
	public function get_public_endpoints(): array {
		return $this->public_endpoints;
	}

	/**
	 * Get rate limit exemptions.
	 *
	 * @since    1.0.0
	 * @return   array    Rate limit exemptions.
	 */
	public function get_rate_limit_exemptions(): array {
		return $this->rate_limit_exemptions;
	}
}
