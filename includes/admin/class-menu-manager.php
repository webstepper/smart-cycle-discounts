<?php
/**
 * Menu Manager Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/class-menu-manager.php
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
 * Menu Manager
 *
 * Handles admin menu creation and management.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Menu_Manager {

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
	 * Admin instance for page rendering.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      object|null    $admin    Admin instance (SCD_Admin or SCD_Admin_Manager).
	 */
	private ?object $admin = null;

	/**
	 * Menu pages.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $menu_pages    Registered menu pages.
	 */
	private array $menu_pages = array();

	/**
	 * Initialize the menu manager.
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
	 * Initialize menu manager.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function init(): void {
		// Don't register menu pages here - wait until add_menus() is called
		$this->logger->debug( 'Menu manager initialized' );
	}

	/**
	 * Set admin instance for page rendering.
	 *
	 * @since    1.0.0
	 * @param    object $admin    Admin instance (SCD_Admin or SCD_Admin_Manager).
	 * @return   void
	 */
	public function set_admin( object $admin ): void {
		$this->admin = $admin;
	}

	/**
	 * Add admin menus.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function add_menus(): void {
		// Register menu pages when we actually need them
		if ( empty( $this->menu_pages ) ) {
			$this->register_menu_pages();
		}

		// Add main menu page
		$this->add_main_menu();

		// Add submenu pages
		$this->add_submenu_pages();

		$this->logger->debug( 'Admin menus added' );
	}

	/**
	 * Register menu pages.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function register_menu_pages(): void {
		$this->menu_pages = array(
			'main'          => array(
				'page_title' => 'Smart Cycle Discounts Dashboard',
				'menu_title' => 'SC Discounts',
				'capability' => 'manage_campaigns',
				'menu_slug'  => 'smart-cycle-discounts',
				'callback'   => array( $this, 'render_dashboard_page' ),
				'icon_url'   => $this->get_menu_icon(),
				'position'   => 56, // After WooCommerce
				'type'       => 'main',
			),
			'dashboard'     => array(
				'parent_slug' => 'smart-cycle-discounts',
				'page_title'  => 'Dashboard',
				'menu_title'  => 'Dashboard',
				'capability'  => 'manage_campaigns',
				'menu_slug'   => 'smart-cycle-discounts',
				'callback'    => array( $this, 'render_dashboard_page' ),
				'type'        => 'submenu',
			),
			'campaigns'     => array(
				'parent_slug' => 'smart-cycle-discounts',
				'page_title'  => 'Campaigns',
				'menu_title'  => 'Campaigns',
				'capability'  => 'manage_campaigns',
				'menu_slug'   => 'scd-campaigns',
				'callback'    => array( $this, 'render_campaigns_page' ),
				'type'        => 'submenu',
			),
			'analytics'     => array(
				'parent_slug' => 'smart-cycle-discounts',
				'page_title'  => 'Analytics',
				'menu_title'  => $this->get_analytics_menu_title(),
				'capability'  => 'view_analytics',
				'menu_slug'   => 'scd-analytics',
				'callback'    => array( $this, 'render_analytics_page' ),
				'type'        => 'submenu',
				'pro_only'    => true,
			),
			'notifications' => array(
				'parent_slug' => 'smart-cycle-discounts',
				'page_title'  => 'Email Notifications',
				'menu_title'  => 'Notifications',
				'capability'  => 'manage_settings',
				'menu_slug'   => 'scd-notifications',
				'callback'    => array( $this, 'render_notifications_page' ),
				'type'        => 'submenu',
			),
			'settings'      => array(
				'parent_slug' => 'smart-cycle-discounts',
				'page_title'  => 'Settings',
				'menu_title'  => 'Settings',
				'capability'  => 'manage_settings',
				'menu_slug'   => 'scd-settings',
				'callback'    => array( $this, 'render_settings_page' ),
				'type'        => 'submenu',
			),
			'tools'         => array(
				'parent_slug' => 'smart-cycle-discounts',
				'page_title'  => 'Tools & Maintenance',
				'menu_title'  => 'Tools',
				'capability'  => 'manage_options',
				'menu_slug'   => 'scd-tools',
				'callback'    => array( $this, 'render_tools_page' ),
				'type'        => 'submenu',
			),
			'license_fix'   => array(
				'parent_slug' => 'smart-cycle-discounts',
				'page_title'  => '🔧 License Fix',
				'menu_title'  => '🔧 License Fix',
				'capability'  => 'manage_options',
				'menu_slug'   => 'scd-license-fix',
				'callback'    => array( $this, 'render_license_fix_page' ),
				'type'        => 'submenu',
			),
		);

		// Allow filtering of menu pages
		$this->menu_pages = apply_filters( 'scd_admin_menu_pages', $this->menu_pages );
	}

	/**
	 * Get translated menu title.
	 *
	 * @since    1.0.0
	 * @param    string $key    Menu key.
	 * @param    string $type   Title type (page_title or menu_title).
	 * @return   string    Translated title.
	 */
	private function get_menu_title( string $key, string $type ): string {
		$titles = array(
			'main'          => array(
				'page_title' => __( 'Smart Cycle Discounts Dashboard', 'smart-cycle-discounts' ),
				'menu_title' => __( 'SC Discounts', 'smart-cycle-discounts' ),
			),
			'dashboard'     => array(
				'page_title' => __( 'Dashboard', 'smart-cycle-discounts' ),
				'menu_title' => __( 'Dashboard', 'smart-cycle-discounts' ),
			),
			'campaigns'     => array(
				'page_title' => __( 'Campaigns', 'smart-cycle-discounts' ),
				'menu_title' => __( 'Campaigns', 'smart-cycle-discounts' ),
			),
			'analytics'     => array(
				'page_title' => __( 'Analytics', 'smart-cycle-discounts' ),
				'menu_title' => __( 'Analytics', 'smart-cycle-discounts' ),
			),
			'notifications' => array(
				'page_title' => __( 'Email Notifications', 'smart-cycle-discounts' ),
				'menu_title' => __( 'Notifications', 'smart-cycle-discounts' ),
			),
			'settings'      => array(
				'page_title' => __( 'Settings', 'smart-cycle-discounts' ),
				'menu_title' => __( 'Settings', 'smart-cycle-discounts' ),
			),
			'tools'         => array(
				'page_title' => __( 'Tools & Maintenance', 'smart-cycle-discounts' ),
				'menu_title' => __( 'Tools', 'smart-cycle-discounts' ),
			),
		);

		return isset( $titles[ $key ][ $type ] ) ? $titles[ $key ][ $type ] : $this->menu_pages[ $key ][ $type ];
	}

	/**
	 * Get analytics menu title with PRO badge for free users.
	 *
	 * @since    1.0.0
	 * @return   string    Menu title with PRO badge if needed.
	 */
	private function get_analytics_menu_title(): string {
		$title = __( 'Analytics', 'smart-cycle-discounts' );

		// Add PRO badge for free users
		if ( ! function_exists( 'scd_is_premium' ) || ! scd_is_premium() ) {
			$title .= ' <span class="scd-pro-badge">PRO</span>';
		}

		return $title;
	}

	/**
	 * Add main menu.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function add_main_menu(): void {
		$main = $this->menu_pages['main'];

		// Use WordPress core capability for now to avoid early capability check
		$capability = 'manage_options';

		$hook_suffix = add_menu_page(
			$this->get_menu_title( 'main', 'page_title' ),
			$this->get_menu_title( 'main', 'menu_title' ),
			$capability,
			$main['menu_slug'],
			$main['callback'],
			$main['icon_url'],
			$main['position']
		);

		// Store hook suffix for later use
		$this->menu_pages['main']['hook_suffix'] = $hook_suffix;

		// Add page-specific actions
		add_action( "load-{$hook_suffix}", array( $this, 'dashboard_page_load' ) );
	}

	/**
	 * Add submenu pages.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function add_submenu_pages(): void {
		$parent_slug = 'smart-cycle-discounts';

		// Process submenu pages in specific order
		$ordered_keys = array( 'dashboard', 'campaigns', 'analytics', 'notifications', 'settings', 'tools' );

		foreach ( $ordered_keys as $key ) {
			if ( ! isset( $this->menu_pages[ $key ] ) || $this->menu_pages[ $key ]['type'] !== 'submenu' ) {
				continue;
			}

			$page = $this->menu_pages[ $key ];

			// Use WordPress core capability for now
			$capability = 'manage_options';

			$hook_suffix = add_submenu_page(
				$page['parent_slug'],
				$this->get_menu_title( $key, 'page_title' ),
				$this->get_menu_title( $key, 'menu_title' ),
				$capability,
				$page['menu_slug'],
				$page['callback']
			);

			// Store hook suffix
			$this->menu_pages[ $key ]['hook_suffix'] = $hook_suffix;

			// Debug logging
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			}

			// Add page-specific load actions
			if ( method_exists( $this, $key . '_page_load' ) ) {
				add_action( "load-{$hook_suffix}", array( $this, $key . '_page_load' ) );
			}
		}
	}

	/**
	 * Get menu icon.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   string    Menu icon URL or dashicon.
	 */
	private function get_menu_icon(): string {
		// Use dashicon for now, can be replaced with custom SVG
		return 'dashicons-marker';
	}

	/**
	 * Dashboard page load handler.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function dashboard_page_load(): void {
		// Add screen options
		add_screen_option(
			'layout_columns',
			array(
				'max'     => 2,
				'default' => 2,
			)
		);

		// Enqueue dashboard-specific assets
		wp_enqueue_script( 'dashboard' );
		wp_enqueue_script( 'postbox' );

		do_action( 'scd_dashboard_page_load' );
	}

	/**
	 * Campaigns page load handler.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function campaigns_page_load(): void {
		// Bulk actions are handled by the campaigns list table itself

		// Add screen options
		$this->add_campaigns_screen_options();

		do_action( 'scd_campaigns_page_load' );
	}

	/**
	 * Analytics page load handler.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function analytics_page_load(): void {
		// Add screen options for analytics
		add_screen_option(
			'per_page',
			array(
				'label'   => __( 'Reports per page', 'smart-cycle-discounts' ),
				'default' => 20,
				'option'  => 'scd_analytics_per_page',
			)
		);

		do_action( 'scd_analytics_page_load' );
	}

	/**
	 * Settings page load handler.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function settings_page_load(): void {
		// Handle settings form submission
		if ( isset( $_POST['submit'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'scd_settings_nonce' ) ) {
			$this->handle_settings_save();
		}

		do_action( 'scd_settings_page_load' );
	}

	/**
	 * Tools page load handler.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function tools_page_load(): void {
		do_action( 'scd_tools_page_load' );
	}


	/**
	 * Render dashboard page.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function render_dashboard_page(): void {
		// Use admin instance if available
		if ( $this->admin && method_exists( $this->admin, 'render_dashboard_page' ) ) {
			$this->admin->render_dashboard_page();
			return;
		}

		$this->render_page_header( __( 'Dashboard', 'smart-cycle-discounts' ) );

		echo '<div class="wrap scd-dashboard">';
		echo '<div id="dashboard-widgets-wrap">';
		echo '<div id="dashboard-widgets" class="metabox-holder">';

		// Left column
		echo '<div class="postbox-container" id="postbox-container-1">';
		do_meta_boxes( 'scd_dashboard', 'normal', null );
		echo '</div>';

		// Right column
		echo '<div class="postbox-container" id="postbox-container-2">';
		do_meta_boxes( 'scd_dashboard', 'side', null );
		echo '</div>';

		echo '</div></div></div>';

		$this->render_page_footer();
	}

	/**
	 * Render campaigns page.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function render_campaigns_page(): void {
		// Use admin instance if available
		if ( $this->admin && method_exists( $this->admin, 'render_campaigns_page' ) ) {
			$this->admin->render_campaigns_page();
			return;
		}

		$this->render_page_header( __( 'Campaigns', 'smart-cycle-discounts' ) );

		echo '<div class="wrap scd-campaigns">';

		// Page content will be handled by the campaigns page class
		do_action( 'scd_render_campaigns_page' );

		echo '</div>';

		$this->render_page_footer();
	}

	/**
	 * Render analytics page.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function render_analytics_page(): void {
		// Use admin instance if available
		if ( $this->admin && method_exists( $this->admin, 'render_analytics_page' ) ) {
			$this->admin->render_analytics_page();
			return;
		}

		$this->render_page_header( __( 'Analytics', 'smart-cycle-discounts' ) );

		echo '<div class="wrap scd-analytics">';

		// Page content will be handled by the analytics page class
		do_action( 'scd_render_analytics_page' );

		echo '</div>';

		$this->render_page_footer();
	}

	/**
	 * Render notifications page.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function render_notifications_page(): void {
		// Use admin instance if available
		if ( $this->admin && method_exists( $this->admin, 'render_notifications_page' ) ) {
			$this->admin->render_notifications_page();
			return;
		}

		$this->render_page_header( __( 'Email Notifications', 'smart-cycle-discounts' ) );

		// Page content will be handled by the notifications page class
		do_action( 'scd_render_notifications_page' );

		$this->render_page_footer();
	}

	/**
	 * Render settings page.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function render_settings_page(): void {
		// Use admin instance if available
		if ( $this->admin && method_exists( $this->admin, 'render_settings_page' ) ) {
			$this->admin->render_settings_page();
			return;
		}

		$this->render_page_header( __( 'Settings', 'smart-cycle-discounts' ) );

		echo '<div class="wrap scd-settings">';

		// Page content will be handled by the settings page class
		do_action( 'scd_render_settings_page' );

		echo '</div>';

		$this->render_page_footer();
	}

	/**
	 * Render tools page.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function render_tools_page(): void {
		// Use admin instance if available
		if ( $this->admin && method_exists( $this->admin, 'render_tools_page' ) ) {
			$this->admin->render_tools_page();
			return;
		}

		$this->render_page_header( __( 'Tools & Maintenance', 'smart-cycle-discounts' ) );

		echo '<div class="wrap scd-tools">';

		// Page content will be handled by the tools page class
		do_action( 'scd_render_tools_page' );

		echo '</div>';

		$this->render_page_footer();
	}

	/**
	 * Render license fix page.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function render_license_fix_page(): void {
		// Load the emergency fix class
		require_once SCD_INCLUDES_DIR . 'admin/pages/class-license-emergency-fix.php';

		// Render the page
		SCD_License_Emergency_Fix::render();
	}


	/**
	 * Render page header.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $title    Page title.
	 * @return   void
	 */
	private function render_page_header( string $title ): void {
		echo '<div class="scd-page-header">';
		echo '<h1 class="wp-heading-inline">' . esc_html( $title ) . '</h1>';

		// Add page-specific action buttons
		$this->render_page_actions();

		echo '<hr class="wp-header-end">';
		echo '</div>';
	}

	/**
	 * Render page actions.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function render_page_actions(): void {
		$screen = get_current_screen();

		switch ( $screen->id ) {
			case 'smart-cycle-discounts_page_scd-campaigns':
				if ( $this->capability_manager->current_user_can( 'create_campaigns' ) ) {
					printf(
						'<a href="%s" class="page-title-action">%s</a>',
						esc_url( admin_url( 'admin.php?page=scd-campaigns&action=wizard&intent=new' ) ),
						esc_html__( 'Add New Campaign', 'smart-cycle-discounts' )
					);
				}
				break;
		}
	}

	/**
	 * Render page footer.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function render_page_footer(): void {
		// Add any common footer content
		do_action( 'scd_admin_page_footer' );
	}

	/**
	 * Add campaigns screen options.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function add_campaigns_screen_options(): void {
		add_screen_option(
			'per_page',
			array(
				'label'   => __( 'Campaigns per page', 'smart-cycle-discounts' ),
				'default' => 20,
				'option'  => 'scd_campaigns_per_page',
			)
		);
	}

	/**
	 * Handle settings save.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function handle_settings_save(): void {
		// Settings saving will be handled by the settings page class
		do_action( 'scd_handle_settings_save' );

		// Redirect to prevent resubmission
		wp_redirect( add_query_arg( 'settings-updated', 'true', wp_get_referer() ) );
		exit;
	}


	/**
	 * Add admin notice.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $message    Notice message.
	 * @param    string $type       Notice type.
	 * @return   void
	 */
	private function add_admin_notice( string $message, string $type = 'info' ): void {
		$notices   = get_transient( 'scd_admin_notices' ) ?: array();
		$notices[] = array(
			'message' => $message,
			'type'    => $type,
		);
		set_transient( 'scd_admin_notices', $notices, 300 );
	}

	/**
	 * Get menu page by slug.
	 *
	 * @since    1.0.0
	 * @param    string $slug    Menu slug.
	 * @return   array|null         Menu page data or null.
	 */
	public function get_menu_page( string $slug ): ?array {
		foreach ( $this->menu_pages as $page ) {
			if ( $page['menu_slug'] === $slug ) {
				return $page;
			}
		}

		return null;
	}

	/**
	 * Get all menu pages.
	 *
	 * @since    1.0.0
	 * @return   array    Menu pages.
	 */
	public function get_menu_pages(): array {
		return $this->menu_pages;
	}

	/**
	 * Check if current page is a plugin admin page.
	 *
	 * @since    1.0.0
	 * @return   bool    True if plugin admin page.
	 */
	public function is_plugin_admin_page(): bool {
		$screen = get_current_screen();

		if ( ! $screen ) {
			return false;
		}

		foreach ( $this->menu_pages as $page ) {
			if ( isset( $page['hook_suffix'] ) && $screen->id === $page['hook_suffix'] ) {
				return true;
			}
		}

		return false;
	}
}
