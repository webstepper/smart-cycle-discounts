<?php
/**
 * Admin capability manager
 *
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin
 */

declare(strict_types=1);


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Admin Capability Manager
 *
 * Handles user capabilities and permissions for admin functionality.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Admin_Capability_Manager {

	/**
	 * Logger instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Logger    $logger    Logger instance.
	 */
	private SCD_Logger $logger;

	/**
	 * Plugin capabilities.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $capabilities    Plugin capabilities.
	 */
	private array $capabilities = array();

	/**
	 * Role capabilities mapping.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $role_capabilities    Role capabilities mapping.
	 */
	private array $role_capabilities = array();

	/**
	 * Initialize the capability manager.
	 *
	 * @since    1.0.0
	 * @param    SCD_Logger $logger    Logger instance.
	 */
	public function __construct( SCD_Logger $logger ) {
		$this->logger = $logger;

		// Auto-initialize on construction
		$this->init();
	}

	/**
	 * Initialize capability manager.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function init(): void {
		// Don't define capabilities here - wait for init hook
		$this->add_hooks();

		$this->logger->debug( 'Capability manager initialized' );
	}

	/**
	 * Add WordPress hooks.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function add_hooks(): void {
		// Initialize capabilities after translations are loaded
		add_action( 'init', array( $this, 'late_init' ), 1 );

		// Map meta capabilities
		add_filter( 'map_meta_cap', array( $this, 'map_meta_capabilities' ), 10, 4 );

		// User role changes
		add_action( 'set_user_role', array( $this, 'handle_user_role_change' ), 10, 3 );
	}

	/**
	 * Late initialization after WordPress init.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function late_init(): void {
		$this->define_capabilities();
		$this->define_role_capabilities();

		// Ensure capabilities are added (in case plugin was activated before capabilities were defined)
		$this->ensure_capabilities_exist();
	}

	/**
	 * Define plugin capabilities.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function define_capabilities(): void {
		$this->capabilities = array(
			// Campaign management
			'scd_view_campaigns'     => array(
				'label'       => __( 'View Campaigns', 'smart-cycle-discounts' ),
				'description' => __( 'View discount campaigns.', 'smart-cycle-discounts' ),
				'group'       => 'campaigns',
			),
			'scd_manage_campaigns'   => array(
				'label'       => __( 'Manage Campaigns', 'smart-cycle-discounts' ),
				'description' => __( 'Create, edit, and manage discount campaigns.', 'smart-cycle-discounts' ),
				'group'       => 'campaigns',
			),
			'scd_create_campaigns'   => array(
				'label'       => __( 'Create Campaigns', 'smart-cycle-discounts' ),
				'description' => __( 'Create new discount campaigns.', 'smart-cycle-discounts' ),
				'group'       => 'campaigns',
			),
			'scd_edit_campaigns'     => array(
				'label'       => __( 'Edit Campaigns', 'smart-cycle-discounts' ),
				'description' => __( 'Edit existing discount campaigns.', 'smart-cycle-discounts' ),
				'group'       => 'campaigns',
			),
			'scd_delete_campaigns'   => array(
				'label'       => __( 'Delete Campaigns', 'smart-cycle-discounts' ),
				'description' => __( 'Delete discount campaigns.', 'smart-cycle-discounts' ),
				'group'       => 'campaigns',
			),
			'scd_activate_campaigns' => array(
				'label'       => __( 'Activate Campaigns', 'smart-cycle-discounts' ),
				'description' => __( 'Activate and deactivate discount campaigns.', 'smart-cycle-discounts' ),
				'group'       => 'campaigns',
			),

			// Analytics and reporting
			'scd_view_analytics'     => array(
				'label'       => __( 'View Analytics', 'smart-cycle-discounts' ),
				'description' => __( 'View campaign analytics and reports.', 'smart-cycle-discounts' ),
				'group'       => 'analytics',
			),
			'scd_manage_analytics'   => array(
				'label'       => __( 'Manage Analytics', 'smart-cycle-discounts' ),
				'description' => __( 'Manage analytics settings, generate reports, and clear cache.', 'smart-cycle-discounts' ),
				'group'       => 'analytics',
			),
			'scd_export_analytics'   => array(
				'label'       => __( 'Export Analytics', 'smart-cycle-discounts' ),
				'description' => __( 'Export analytics data and reports.', 'smart-cycle-discounts' ),
				'group'       => 'analytics',
			),

			// Product management
			'scd_view_products'      => array(
				'label'       => __( 'View Products', 'smart-cycle-discounts' ),
				'description' => __( 'View and search products for campaigns.', 'smart-cycle-discounts' ),
				'group'       => 'products',
			),

			// Settings management
			'scd_manage_settings'    => array(
				'label'       => __( 'Manage Settings', 'smart-cycle-discounts' ),
				'description' => __( 'Manage plugin settings and configuration.', 'smart-cycle-discounts' ),
				'group'       => 'settings',
			),

			// Tools and utilities
			'scd_manage_tools'       => array(
				'label'       => __( 'Manage Tools', 'smart-cycle-discounts' ),
				'description' => __( 'Access plugin tools and utilities.', 'smart-cycle-discounts' ),
				'group'       => 'tools',
			),
			'scd_import_export'      => array(
				'label'       => __( 'Import/Export', 'smart-cycle-discounts' ),
				'description' => __( 'Import and export campaign data.', 'smart-cycle-discounts' ),
				'group'       => 'tools',
			),
		);

		// Allow filtering of capabilities
		$this->capabilities = apply_filters( 'scd_admin_capabilities', $this->capabilities );
	}

	/**
	 * Define role capabilities mapping.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function define_role_capabilities(): void {
		$this->role_capabilities = array(
			'administrator' => array(
				'scd_view_campaigns',
				'scd_manage_campaigns',
				'scd_create_campaigns',
				'scd_edit_campaigns',
				'scd_delete_campaigns',
				'scd_activate_campaigns',
				'scd_view_analytics',
				'scd_manage_analytics',
				'scd_export_analytics',
				'scd_view_products',
				'scd_manage_settings',
				'scd_manage_tools',
				'scd_import_export',
			),
			'shop_manager'  => array(
				'scd_view_campaigns',
				'scd_manage_campaigns',
				'scd_create_campaigns',
				'scd_edit_campaigns',
				'scd_delete_campaigns',
				'scd_activate_campaigns',
				'scd_view_analytics',
				'scd_manage_analytics',
				'scd_export_analytics',
				'scd_view_products',
			),
			'editor'        => array(
				'scd_view_campaigns',
				'scd_manage_campaigns',
				'scd_create_campaigns',
				'scd_edit_campaigns',
				'scd_view_analytics',
				'scd_view_products',
			),
			'author'        => array(
				'scd_view_campaigns',
				'scd_create_campaigns',
				'scd_edit_campaigns',
				'scd_view_analytics',
				'scd_view_products',
			),
			'contributor'   => array(
				'scd_view_campaigns',
				'scd_create_campaigns',
				'scd_view_analytics',
				'scd_view_products',
			),
		);

		// Allow filtering of role capabilities
		$this->role_capabilities = apply_filters( 'scd_role_capabilities', $this->role_capabilities );
	}

	/**
	 * Add capabilities to WordPress roles.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function add_capabilities(): void {
		foreach ( $this->role_capabilities as $role_name => $capabilities ) {
			$role = get_role( $role_name );

			if ( ! $role ) {
				continue;
			}

			foreach ( $capabilities as $capability ) {
				if ( isset( $this->capabilities[ $capability ] ) ) {
					$role->add_cap( $capability );
				}
			}
		}

		$this->logger->info( 'Plugin capabilities added to WordPress roles' );
	}

	/**
	 * Remove capabilities from WordPress roles.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function remove_capabilities(): void {
		foreach ( $this->role_capabilities as $role_name => $capabilities ) {
			$role = get_role( $role_name );

			if ( ! $role ) {
				continue;
			}

			foreach ( $capabilities as $capability ) {
				if ( isset( $this->capabilities[ $capability ] ) ) {
					$role->remove_cap( $capability );
				}
			}
		}

		$this->logger->info( 'Plugin capabilities removed from WordPress roles' );
	}

	/**
	 * Check if current user has capability.
	 *
	 * @since    1.0.0
	 * @param    string $capability    Capability to check.
	 * @param    mixed  $object_id     Object ID for meta capabilities.
	 * @return   bool                     True if user has capability.
	 */
	public function current_user_can( string $capability, mixed $object_id = null ): bool {
		if ( $object_id !== null ) {
			return current_user_can( $capability, $object_id );
		}

		return current_user_can( $capability );
	}

	/**
	 * Check if user has capability.
	 *
	 * @since    1.0.0
	 * @param    int|WP_User $user         User ID or user object.
	 * @param    string      $capability   Capability to check.
	 * @param    mixed       $object_id    Object ID for meta capabilities.
	 * @return   bool                         True if user has capability.
	 */
	public function user_can( int|WP_User $user, string $capability, mixed $object_id = null ): bool {
		if ( $object_id !== null ) {
			return user_can( $user, $capability, $object_id );
		}

		return user_can( $user, $capability );
	}

	/**
	 * Map meta capabilities.
	 *
	 * @since    1.0.0
	 * @param    array  $caps         Mapped capabilities.
	 * @param    string $cap          Capability being checked.
	 * @param    int    $user_id      User ID.
	 * @param    array  $args         Additional arguments.
	 * @return   array                   Mapped capabilities.
	 */
	public function map_meta_capabilities( array $caps, string $cap, int $user_id, array $args ): array {
		switch ( $cap ) {
			case 'edit_campaign':
			case 'delete_campaign':
			case 'activate_campaign':
			case 'deactivate_campaign':
				$caps = $this->map_campaign_capabilities( $caps, $cap, $user_id, $args );
				break;
		}

		return $caps;
	}

	/**
	 * Map campaign-specific capabilities.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array  $caps         Mapped capabilities.
	 * @param    string $cap          Capability being checked.
	 * @param    int    $user_id      User ID.
	 * @param    array  $args         Additional arguments.
	 * @return   array                   Mapped capabilities.
	 */
	private function map_campaign_capabilities( array $caps, string $cap, int $user_id, array $args ): array {
		// Get campaign ID from args
		$campaign_id = isset( $args[0] ) ? (int) $args[0] : 0;

		if ( ! $campaign_id ) {
			return array( 'do_not_allow' );
		}

		// Map meta capabilities to primitive capabilities
		switch ( $cap ) {
			case 'edit_campaign':
				return array( 'scd_edit_campaigns' );
			case 'delete_campaign':
				return array( 'scd_delete_campaigns' );
			case 'activate_campaign':
			case 'deactivate_campaign':
				return array( 'scd_activate_campaigns' );
		}

		return array( 'do_not_allow' );
	}

	/**
	 * Handle user role change.
	 *
	 * @since    1.0.0
	 * @param    int    $user_id      User ID.
	 * @param    string $role         New role.
	 * @param    array  $old_roles    Previous roles.
	 * @return   void
	 */
	public function handle_user_role_change( int $user_id, string $role, array $old_roles ): void {
		$user = get_user_by( 'id', $user_id );

		if ( ! $user ) {
			return;
		}

		// Remove old capabilities
		foreach ( $old_roles as $old_role ) {
			if ( isset( $this->role_capabilities[ $old_role ] ) ) {
				foreach ( $this->role_capabilities[ $old_role ] as $capability ) {
					$user->remove_cap( $capability );
				}
			}
		}

		// Add new capabilities
		if ( isset( $this->role_capabilities[ $role ] ) ) {
			foreach ( $this->role_capabilities[ $role ] as $capability ) {
				$user->add_cap( $capability );
			}
		}

		$this->logger->debug(
			'User role changed, capabilities updated',
			array(
				'user_id'   => $user_id,
				'new_role'  => $role,
				'old_roles' => $old_roles,
			)
		);
	}

	/**
	 * Get all plugin capabilities.
	 *
	 * @since    1.0.0
	 * @return   array    Plugin capabilities.
	 */
	public function get_capabilities(): array {
		return $this->capabilities;
	}

	/**
	 * Get capabilities by group.
	 *
	 * @since    1.0.0
	 * @param    string $group    Capability group.
	 * @return   array              Capabilities in group.
	 */
	public function get_capabilities_by_group( string $group ): array {
		$group_capabilities = array();

		foreach ( $this->capabilities as $capability => $data ) {
			if ( $data['group'] === $group ) {
				$group_capabilities[ $capability ] = $data;
			}
		}

		return $group_capabilities;
	}

	/**
	 * Get role capabilities.
	 *
	 * @since    1.0.0
	 * @param    string $role    Role name.
	 * @return   array             Role capabilities.
	 */
	public function get_role_capabilities( string $role ): array {
		return $this->role_capabilities[ $role ] ?? array();
	}

	/**
	 * Add capability to role.
	 *
	 * @since    1.0.0
	 * @param    string $role         Role name.
	 * @param    string $capability   Capability to add.
	 * @return   bool                    True on success.
	 */
	public function add_capability_to_role( string $role, string $capability ): bool {
		$wp_role = get_role( $role );

		if ( ! $wp_role || ! isset( $this->capabilities[ $capability ] ) ) {
			return false;
		}

		$wp_role->add_cap( $capability );

		// Update our mapping
		if ( ! isset( $this->role_capabilities[ $role ] ) ) {
			$this->role_capabilities[ $role ] = array();
		}

		if ( ! in_array( $capability, $this->role_capabilities[ $role ] ) ) {
			$this->role_capabilities[ $role ][] = $capability;
		}

		$this->logger->debug(
			'Capability added to role',
			array(
				'role'       => $role,
				'capability' => $capability,
			)
		);

		return true;
	}

	/**
	 * Remove capability from role.
	 *
	 * @since    1.0.0
	 * @param    string $role         Role name.
	 * @param    string $capability   Capability to remove.
	 * @return   bool                    True on success.
	 */
	public function remove_capability_from_role( string $role, string $capability ): bool {
		$wp_role = get_role( $role );

		if ( ! $wp_role ) {
			return false;
		}

		$wp_role->remove_cap( $capability );

		// Update our mapping
		if ( isset( $this->role_capabilities[ $role ] ) ) {
			$key = array_search( $capability, $this->role_capabilities[ $role ] );
			if ( $key !== false ) {
				unset( $this->role_capabilities[ $role ][ $key ] );
			}
		}

		$this->logger->debug(
			'Capability removed from role',
			array(
				'role'       => $role,
				'capability' => $capability,
			)
		);

		return true;
	}

	/**
	 * Check if capability exists.
	 *
	 * @since    1.0.0
	 * @param    string $capability    Capability to check.
	 * @return   bool                     True if capability exists.
	 */
	public function capability_exists( string $capability ): bool {
		return isset( $this->capabilities[ $capability ] );
	}

	/**
	 * Get capability groups.
	 *
	 * @since    1.0.0
	 * @return   array    Capability groups.
	 */
	public function get_capability_groups(): array {
		$groups = array();

		foreach ( $this->capabilities as $capability => $data ) {
			$group = $data['group'];
			if ( ! isset( $groups[ $group ] ) ) {
				$groups[ $group ] = array();
			}
			$groups[ $group ][] = $capability;
		}

		return $groups;
	}

	/**
	 * Get users with capability.
	 *
	 * @since    1.0.0
	 * @param    string $capability    Capability to check.
	 * @return   array                    Array of user IDs.
	 */
	public function get_users_with_capability( string $capability ): array {
		$users = get_users(
			array(
				'meta_query' => array(
					array(
						'key'     => 'wp_capabilities',
						'value'   => $capability,
						'compare' => 'LIKE',
					),
				),
			)
		);

		return array_map(
			function ( $user ) {
				return $user->ID;
			},
			$users
		);
	}

	/**
	 * Validate user permissions for action.
	 *
	 * @since    1.0.0
	 * @param    string $action       Action to validate.
	 * @param    mixed  $object_id    Object ID if applicable.
	 * @return   bool|WP_Error           True if valid, error otherwise.
	 */
	public function validate_user_permissions( string $action, mixed $object_id = null ): bool|WP_Error {
		$capability_map = array(
			'view_campaigns'    => 'scd_view_campaigns',
			'create_campaign'   => 'scd_create_campaigns',
			'edit_campaign'     => 'edit_campaign',
			'delete_campaign'   => 'delete_campaign',
			'activate_campaign' => 'activate_campaign',
			'view_analytics'    => 'scd_view_analytics',
			'manage_settings'   => 'scd_manage_settings',
		);

		$capability = $capability_map[ $action ] ?? $action;

		if ( ! $this->current_user_can( $capability, $object_id ) ) {
			return new WP_Error(
				'insufficient_permissions',
				__( 'You do not have permission to perform this action.', 'smart-cycle-discounts' )
			);
		}

		return true;
	}

	/**
	 * Get capability requirements for admin pages.
	 *
	 * @since    1.0.0
	 * @return   array    Page capability requirements.
	 */
	public function get_page_capabilities(): array {
		return array(
			'smart-cycle-discounts' => 'scd_view_campaigns',
			'scd-campaigns'         => 'scd_view_campaigns',
			'scd-analytics'         => 'scd_view_analytics',
			'scd-settings'          => 'scd_manage_settings',
			'scd-tools'             => 'scd_manage_tools',
		);
	}

	/**
	 * Ensure capabilities exist in WordPress roles.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function ensure_capabilities_exist(): void {
		// Check if capabilities are already added
		$admin_role = get_role( 'administrator' );
		if ( ! $admin_role || ! $admin_role->has_cap( 'scd_view_campaigns' ) ) {
			// Capabilities not added yet, add them now
			$this->add_capabilities();
		}
	}
}
