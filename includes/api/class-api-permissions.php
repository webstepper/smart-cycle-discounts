<?php
/**
 * Api Permissions Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/api/class-api-permissions.php
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
 * API Permissions Manager
 *
 * Handles permissions and authorization for REST API requests.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/api
 * @author     Webstepper <contact@webstepper.io>
 */
class WSSCD_API_Permissions {

	/**
	 * Capability manager instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Admin_Capability_Manager    $capability_manager    Capability manager.
	 */
	private WSSCD_Admin_Capability_Manager $capability_manager;

	/**
	 * Feature gate instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Feature_Gate|null    $feature_gate    Feature gate for license checks.
	 */
	private ?WSSCD_Feature_Gate $feature_gate = null;

	/**
	 * Logger instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Logger    $logger    Logger instance.
	 */
	private WSSCD_Logger $logger;

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
		'wsscd_admin'      => true,       // Plugin administrators
	);

	/**
	 * Initialize the permissions manager.
	 *
	 * @since    1.0.0
	 * @param    WSSCD_Admin_Capability_Manager $capability_manager    Capability manager.
	 * @param    WSSCD_Logger                   $logger                Logger instance.
	 * @param    WSSCD_Feature_Gate|null        $feature_gate          Feature gate for license checks.
	 */
	public function __construct(
		WSSCD_Admin_Capability_Manager $capability_manager,
		WSSCD_Logger $logger,
		?WSSCD_Feature_Gate $feature_gate = null
	) {
		$this->capability_manager = $capability_manager;
		$this->logger             = $logger;
		$this->feature_gate       = $feature_gate;
	}

	/**
	 * Set feature gate instance.
	 *
	 * @since    1.0.0
	 * @param    WSSCD_Feature_Gate $feature_gate    Feature gate instance.
	 * @return   void
	 */
	public function set_feature_gate( WSSCD_Feature_Gate $feature_gate ): void {
		$this->feature_gate = $feature_gate;
	}

	/**
	 * Get feature gate instance.
	 *
	 * Falls back to container lookup if not set.
	 *
	 * @since    1.0.0
	 * @return   WSSCD_Feature_Gate|null    Feature gate instance or null.
	 */
	private function get_feature_gate(): ?WSSCD_Feature_Gate {
		if ( null !== $this->feature_gate ) {
			return $this->feature_gate;
		}

		// Try to get from container
		if ( function_exists( 'wsscd_get_instance' ) ) {
			$container = wsscd_get_instance()->get_container();
			if ( $container && $container->has( 'feature_gate' ) ) {
				$this->feature_gate = $container->get( 'feature_gate' );
				return $this->feature_gate;
			}
		}

		return null;
	}

	/**
	 * Initialize permissions.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function init(): void {
		// Initialization complete
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

		if ( $this->is_public_endpoint( $method, $route ) ) {
			return true;
		}

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return new WP_Error(
				'rest_not_authenticated',
				__( 'You are not currently logged in.', 'smart-cycle-discounts' ),
				array( 'status' => 401 )
			);
		}

		$required_capability = $this->get_required_capability( $method, $route );
		if ( ! $required_capability ) {
			// No specific capability required, but authentication is needed
			return true;
		}

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
		if ( $campaign_id && in_array( $method, array( 'PUT', 'PATCH', 'DELETE' ), true ) ) {
			return $this->check_campaign_ownership( $campaign_id );
		}

		return $this->check_permissions( $request );
	}

	/**
	 * Check read-only permissions for campaigns endpoint.
	 *
	 * Read access is free for all authenticated users.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request $request    Request object.
	 * @return   bool|WP_Error                  Permission result.
	 */
	public function check_campaigns_read_permissions( WP_REST_Request $request ) {
		// Must be authenticated
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return new WP_Error(
				'rest_not_authenticated',
				__( 'You are not currently logged in.', 'smart-cycle-discounts' ),
				array( 'status' => 401 )
			);
		}

		// Check basic campaign view capability
		if ( ! $this->capability_manager->current_user_can( 'view_campaigns' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to view campaigns.', 'smart-cycle-discounts' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Check write permissions for campaigns endpoint.
	 *
	 * Write access requires premium license.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request $request    Request object.
	 * @return   bool|WP_Error                  Permission result.
	 */
	public function check_campaigns_write_permissions( WP_REST_Request $request ) {
		// First check basic permissions
		$basic_check = $this->check_campaigns_permissions( $request );
		if ( is_wp_error( $basic_check ) ) {
			return $basic_check;
		}

		// Then check premium license for write operations
		$license_check = $this->check_api_write_license();
		if ( is_wp_error( $license_check ) ) {
			return $license_check;
		}

		return true;
	}

	/**
	 * Check bulk operations permissions.
	 *
	 * Bulk operations require premium license.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request $request    Request object.
	 * @return   bool|WP_Error                  Permission result.
	 */
	public function check_bulk_permissions( WP_REST_Request $request ) {
		// First check basic permissions
		$basic_check = $this->check_campaigns_permissions( $request );
		if ( is_wp_error( $basic_check ) ) {
			return $basic_check;
		}

		// Then check premium license for bulk operations
		$feature_gate = $this->get_feature_gate();
		if ( $feature_gate && ! $feature_gate->can_use_api_bulk() ) {
			return new WP_Error(
				'rest_api_bulk_premium_required',
				__( 'Bulk API operations require a Pro license.', 'smart-cycle-discounts' ),
				array(
					'status'      => 403,
					'upgrade_url' => $feature_gate->get_upgrade_url(),
				)
			);
		}

		return true;
	}

	/**
	 * Check API write license.
	 *
	 * @since    1.0.0
	 * @return   bool|WP_Error    True if allowed, WP_Error if not.
	 */
	private function check_api_write_license() {
		$feature_gate = $this->get_feature_gate();
		if ( $feature_gate && ! $feature_gate->can_use_api_write() ) {
			return new WP_Error(
				'rest_api_write_premium_required',
				__( 'API write operations require a Pro license. Read access is free.', 'smart-cycle-discounts' ),
				array(
					'status'      => 403,
					'upgrade_url' => $feature_gate->get_upgrade_url(),
				)
			);
		}

		return true;
	}

	/**
	 * Check read-only permissions for discounts endpoint.
	 *
	 * Read access is free for all authenticated users.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request $request    Request object.
	 * @return   bool|WP_Error                  Permission result.
	 */
	public function check_discounts_read_permissions( WP_REST_Request $request ) {
		// Must be authenticated
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return new WP_Error(
				'rest_not_authenticated',
				__( 'You are not currently logged in.', 'smart-cycle-discounts' ),
				array( 'status' => 401 )
			);
		}

		// Check basic discount view capability
		if ( ! $this->capability_manager->current_user_can( 'view_discounts' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to view discounts.', 'smart-cycle-discounts' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Check write permissions for discounts endpoint.
	 *
	 * Write access requires premium license.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request $request    Request object.
	 * @return   bool|WP_Error                  Permission result.
	 */
	public function check_discounts_write_permissions( WP_REST_Request $request ) {
		// First check basic permissions
		$basic_check = $this->check_permissions( $request );
		if ( is_wp_error( $basic_check ) ) {
			return $basic_check;
		}

		// Then check premium license for write operations
		$license_check = $this->check_api_write_license();
		if ( is_wp_error( $license_check ) ) {
			return $license_check;
		}

		return true;
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
		$user_id = $this->get_user_id_from_api_key( $api_key );
		if ( ! $user_id ) {
			return false;
		}

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
		$route = preg_replace( '#^/wsscd/v1/?#', '', $route );

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
			$this->capability_manager->current_user_can( 'wsscd_admin' ) ) {
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

		global $wpdb;
		$table_name = $wpdb->prefix . 'wsscd_campaigns';

		// SECURITY: Use %i placeholder for table identifier (WordPress 6.2+).
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Permission check on plugin's custom campaigns table; must be real-time.
		$campaign_owner = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT created_by FROM %i WHERE id = %d AND deleted_at IS NULL',
				$table_name,
				$campaign_id
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls

		if ( null === $campaign_owner ) {
			return new WP_Error(
				'rest_campaign_not_found',
				__( 'Campaign not found.', 'smart-cycle-discounts' ),
				array( 'status' => 404 )
			);
		}

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
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching , PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Usermeta lookup for API key validation; must query all users with API keys.
		$user_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT user_id
                FROM {$wpdb->usermeta}
                WHERE meta_key = %s",
				'wsscd_api_keys'
			)
		);

		if ( empty( $user_ids ) ) {
			return false;
		}

		foreach ( $user_ids as $user_id ) {
			$api_keys = get_user_meta( $user_id, 'wsscd_api_keys', true ) ?: array();

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
