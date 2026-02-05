<?php
/**
 * Menu Manager Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/class-menu-manager.php
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
 * Menu Manager
 *
 * Handles admin menu creation and management.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin
 * @author     Webstepper <contact@webstepper.io>
 */
class WSSCD_Menu_Manager {

	/**
	 * Capability manager instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Admin_Capability_Manager    $capability_manager    Capability manager.
	 */
	private WSSCD_Admin_Capability_Manager $capability_manager;

	/**
	 * Logger instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Logger    $logger    Logger instance.
	 */
	private WSSCD_Logger $logger;

	/**
	 * Admin instance for page rendering.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      object|null    $admin    Admin instance (WSSCD_Admin or WSSCD_Admin_Manager).
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
	 * @param    WSSCD_Admin_Capability_Manager $capability_manager    Capability manager.
	 * @param    WSSCD_Logger                   $logger                Logger instance.
	 */
	public function __construct(
		WSSCD_Admin_Capability_Manager $capability_manager,
		WSSCD_Logger $logger
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
	}

	/**
	 * Set admin instance for page rendering.
	 *
	 * @since    1.0.0
	 * @param    object $admin    Admin instance (WSSCD_Admin or WSSCD_Admin_Manager).
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
		if ( empty( $this->menu_pages ) ) {
			$this->register_menu_pages();
		}

		$this->add_main_menu();

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
				'menu_slug'   => 'wsscd-campaigns',
				'callback'    => array( $this, 'render_campaigns_page' ),
				'type'        => 'submenu',
			),
			'analytics'     => array(
				'parent_slug' => 'smart-cycle-discounts',
				'page_title'  => 'Analytics',
				'menu_title'  => $this->get_analytics_menu_title(),
				'capability'  => 'view_analytics',
				'menu_slug'   => 'wsscd-analytics',
				'callback'    => array( $this, 'render_analytics_page' ),
				'type'        => 'submenu',
				'pro_only'    => true,
			),
			'notifications' => array(
				'parent_slug' => 'smart-cycle-discounts',
				'page_title'  => 'Email Notifications',
				'menu_title'  => 'Notifications',
				'capability'  => 'manage_settings',
				'menu_slug'   => 'wsscd-notifications',
				'callback'    => array( $this, 'render_notifications_page' ),
				'type'        => 'submenu',
			),
			'settings'      => array(
				'parent_slug' => 'smart-cycle-discounts',
				'page_title'  => 'Settings',
				'menu_title'  => 'Settings',
				'capability'  => 'manage_settings',
				'menu_slug'   => 'wsscd-settings',
				'callback'    => array( $this, 'render_settings_page' ),
				'type'        => 'submenu',
			),
			'tools'         => array(
				'parent_slug' => 'smart-cycle-discounts',
				'page_title'  => 'Tools & Maintenance',
				'menu_title'  => 'Tools',
				'capability'  => 'manage_options',
				'menu_slug'   => 'wsscd-tools',
				'callback'    => array( $this, 'render_tools_page' ),
				'type'        => 'submenu',
			),
			'help'          => array(
				'parent_slug' => 'smart-cycle-discounts',
				'page_title'  => 'Help & Support',
				'menu_title'  => 'Help',
				'capability'  => 'manage_options',
				'menu_slug'   => 'wsscd-help',
				'callback'    => array( $this, 'render_help_page' ),
				'type'        => 'submenu',
			),
			'license_fix'   => array(
				'parent_slug' => 'smart-cycle-discounts',
				'page_title'  => '🔧 License Fix',
				'menu_title'  => '🔧 License Fix',
				'capability'  => 'manage_options',
				'menu_slug'   => 'wsscd-license-fix',
				'callback'    => array( $this, 'render_license_fix_page' ),
				'type'        => 'submenu',
			),
		);

		// Allow filtering of menu pages
		$this->menu_pages = apply_filters( 'wsscd_admin_menu_pages', $this->menu_pages );
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
			'help'          => array(
				'page_title' => __( 'Help & Support', 'smart-cycle-discounts' ),
				'menu_title' => __( 'Help', 'smart-cycle-discounts' ),
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
		if ( ! function_exists( 'wsscd_is_premium' ) || ! wsscd_is_premium() ) {
			$title .= ' ' . wp_kses_post( WSSCD_Badge_Helper::pro_badge() );
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

		$this->menu_pages['main']['hook_suffix'] = $hook_suffix;

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

		$ordered_keys = array( 'dashboard', 'campaigns', 'analytics', 'notifications', 'settings', 'tools', 'help' );

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

			$this->menu_pages[ $key ]['hook_suffix'] = $hook_suffix;

			// Debug logging
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			}

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

		do_action( 'wsscd_dashboard_page_load' );
	}

	/**
	 * Campaigns page load handler.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function campaigns_page_load(): void {
		// Bulk actions are handled by the campaigns list table itself.

		$this->add_campaigns_screen_options();

		do_action( 'wsscd_campaigns_page_load' );
	}

	/**
	 * Analytics page load handler.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function analytics_page_load(): void {
		add_screen_option(
			'per_page',
			array(
				'label'   => __( 'Reports per page', 'smart-cycle-discounts' ),
				'default' => 20,
				'option'  => 'wsscd_analytics_per_page',
			)
		);

		do_action( 'wsscd_analytics_page_load' );
	}

	/**
	 * Settings page load handler.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function settings_page_load(): void {
		// Handle settings form submission.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is being extracted for verification on next line.
		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification happens in wp_verify_nonce() on this line.
		if ( isset( $_POST['submit'] ) && wp_verify_nonce( $nonce, 'wsscd_settings_nonce' ) ) {
			$this->handle_settings_save();
		}

		do_action( 'wsscd_settings_page_load' );
	}

	/**
	 * Tools page load handler.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function tools_page_load(): void {
		do_action( 'wsscd_tools_page_load' );
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

		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Static HTML structure, no dynamic content.
		echo '<div class="wrap wsscd-dashboard">';
		echo '<div id="dashboard-widgets-wrap">';
		echo '<div id="dashboard-widgets" class="metabox-holder">';

		// Left column
		echo '<div class="postbox-container" id="postbox-container-1">';
		do_meta_boxes( 'wsscd_dashboard', 'normal', null );
		echo '</div>';

		// Right column
		echo '<div class="postbox-container" id="postbox-container-2">';
		do_meta_boxes( 'wsscd_dashboard', 'side', null );
		echo '</div>';

		echo '</div></div></div>';
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped

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

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static HTML structure.
		echo '<div class="wrap wsscd-campaigns">';

		// Page content will be handled by the campaigns page class
		do_action( 'wsscd_render_campaigns_page' );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static HTML structure.
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

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static HTML structure.
		echo '<div class="wrap wsscd-analytics">';

		// Page content will be handled by the analytics page class
		do_action( 'wsscd_render_analytics_page' );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static HTML structure.
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
		do_action( 'wsscd_render_notifications_page' );

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

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static HTML structure.
		echo '<div class="wrap wsscd-settings">';

		// Page content will be handled by the settings page class
		do_action( 'wsscd_render_settings_page' );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static HTML structure.
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

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static HTML structure.
		echo '<div class="wrap wsscd-tools">';

		// Page content will be handled by the tools page class
		do_action( 'wsscd_render_tools_page' );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static HTML structure.
		echo '</div>';

		$this->render_page_footer();
	}

	/**
	 * Render help & support page.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function render_help_page(): void {
		$this->render_page_header( __( 'Help & Support', 'smart-cycle-discounts' ) );

		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Static HTML structure with escaped dynamic content.
		echo '<div class="wrap wsscd-help-page wsscd-main-dashboard">';

		echo '<div class="wsscd-dashboard-section wsscd-help-section">';
		echo '<div class="wsscd-section-header">';
		echo '<div class="wsscd-section-header-content">';
		echo '<div class="wsscd-section-header-text">';
		echo '<h2>' . esc_html__( 'How can we help?', 'smart-cycle-discounts' ) . '</h2>';
		echo '</div>';
		echo '</div>';
		echo '<p class="wsscd-section-header-description">';
		echo esc_html__( 'Get answers fast with docs, direct support, and real campaign ideas tailored to how you sell.', 'smart-cycle-discounts' );
		echo '</p>';
		echo '</div>';

		echo '<div class="wsscd-section-content">';
		echo '<div class="wsscd-help-grid wsscd-campaigns-grid">';

		// Documentation card.
		echo '<div class="wsscd-card wsscd-help-card">';
		echo '<div class="wsscd-card__header">';
		echo '<h2 class="wsscd-card__title">';
		if ( class_exists( 'WSSCD_Icon_Helper' ) ) {
			WSSCD_Icon_Helper::render( 'book', array( 'size' => 18 ) );
		}
		echo esc_html__( 'Documentation & Guides', 'smart-cycle-discounts' );
		echo '</h2>';
		echo '</div>';
		echo '<div class="wsscd-card__content">';
		echo '<p>';
		echo esc_html__( 'Step-by-step guides, FAQs, and best-practice tutorials to help you set up and optimize your discount campaigns.', 'smart-cycle-discounts' );
		echo '</p>';
		if ( class_exists( 'WSSCD_Button_Helper' ) ) {
			WSSCD_Button_Helper::primary(
				__( 'View Documentation', 'smart-cycle-discounts' ),
				array(
					'href' => esc_url( 'https://webstepper.io/docs/' ),
					'icon' => 'external',
					'attributes' => array(
						'target' => '_blank',
						'rel'    => 'noopener noreferrer',
					),
				)
			);
		} else {
			echo '<p><a href="' . esc_url( 'https://webstepper.io/docs/' ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'View Documentation', 'smart-cycle-discounts' ) . '</a></p>';
		}
		echo '</div>';
		echo '</div>';

		// Support Center card.
		echo '<div class="wsscd-card wsscd-help-card">';
		echo '<div class="wsscd-card__header">';
		echo '<h2 class="wsscd-card__title">';
		if ( class_exists( 'WSSCD_Icon_Helper' ) ) {
			WSSCD_Icon_Helper::render( 'support', array( 'size' => 18 ) );
		}
		echo esc_html__( 'Customer Support', 'smart-cycle-discounts' );
		echo '</h2>';
		echo '</div>';
		echo '<div class="wsscd-card__content">';
		echo '<p>';
		echo esc_html__( 'Licensed customers get priority email support from the people who built the plugin. We aim to respond within 24 hours.', 'smart-cycle-discounts' );
		echo '</p>';
		if ( class_exists( 'WSSCD_Button_Helper' ) ) {
			WSSCD_Button_Helper::primary(
				__( 'Open Support Center', 'smart-cycle-discounts' ),
				array(
					'href' => esc_url( 'https://webstepper.io/contact-us/' ),
					'icon' => 'email',
					'attributes' => array(
						'target' => '_blank',
						'rel'    => 'noopener noreferrer',
					),
				)
			);
		} else {
			echo '<p><a href="' . esc_url( 'https://webstepper.io/contact-us/' ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Open Support Center', 'smart-cycle-discounts' ) . '</a></p>';
		}
		echo '</div>';
		echo '</div>';

		// Recipes / inspiration card.
		echo '<div class="wsscd-card wsscd-help-card">';
		echo '<div class="wsscd-card__header">';
		echo '<h2 class="wsscd-card__title">';
		if ( class_exists( 'WSSCD_Icon_Helper' ) ) {
			WSSCD_Icon_Helper::render( 'lightbulb', array( 'size' => 18 ) );
		}
		echo esc_html__( 'Campaign Ideas & Recipes', 'smart-cycle-discounts' );
		echo '</h2>';
		echo '</div>';
		echo '<div class="wsscd-card__content">';
		echo '<p>';
		echo esc_html__( 'Not sure where to start? Explore proven discount campaign ideas to increase average order value and repeat purchases.', 'smart-cycle-discounts' );
		echo '</p>';
		if ( class_exists( 'WSSCD_Button_Helper' ) ) {
			WSSCD_Button_Helper::secondary(
				__( 'Browse Campaign Ideas', 'smart-cycle-discounts' ),
				array(
					'href' => esc_url( 'https://webstepper.io/blog/' ),
					'icon' => 'lightbulb',
					'attributes' => array(
						'target' => '_blank',
						'rel'    => 'noopener noreferrer',
					),
				)
			);
		} else {
			echo '<p><a href="' . esc_url( 'https://webstepper.io/blog/' ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Browse Campaign Ideas', 'smart-cycle-discounts' ) . '</a></p>';
		}
		echo '</div>';
		echo '</div>';

		echo '</div>'; // .wsscd-help-grid
		echo '</div>'; // .wrap
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped

		$this->render_page_footer();
	}

	/**
	 * Render license fix page.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function render_license_fix_page(): void {
		require_once WSSCD_INCLUDES_DIR . 'admin/pages/class-license-emergency-fix.php';

		WSSCD_License_Emergency_Fix::render();
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
		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Static HTML structure with escaped dynamic content.
		echo '<div class="wsscd-page-header">';
		echo '<h1 class="wp-heading-inline">' . esc_html( $title ) . '</h1>';

		$this->render_page_actions();

		echo '<hr class="wp-header-end">';
		echo '</div>';
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
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
			case 'smart-cycle-discounts_page_wsscd-campaigns':
				if ( $this->capability_manager->current_user_can( 'create_campaigns' ) ) {
					printf(
						'<a href="%s" class="page-title-action">%s</a>',
						esc_url( admin_url( 'admin.php?page=wsscd-campaigns&action=wizard&intent=new' ) ),
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
		do_action( 'wsscd_admin_page_footer' );
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
				'option'  => 'wsscd_campaigns_per_page',
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
		do_action( 'wsscd_handle_settings_save' );

		// Redirect to prevent resubmission
		wp_safe_redirect( add_query_arg( 'settings-updated', 'true', wp_get_referer() ) );
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
		$notices   = get_transient( 'wsscd_admin_notices' ) ?: array();
		$notices[] = array(
			'message' => $message,
			'type'    => $type,
		);
		set_transient( 'wsscd_admin_notices', $notices, 300 );
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
