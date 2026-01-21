<?php
/**
 * Admin Manager Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/class-admin-manager.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


require_once WSSCD_PLUGIN_DIR . 'includes/admin/ajax/class-ajax-security.php';
require_once WSSCD_PLUGIN_DIR . 'includes/admin/ajax/class-wsscd-ajax-response.php';
require_once WSSCD_PLUGIN_DIR . 'includes/admin/class-review-notice.php';

/**
 * Admin Manager
 *
 * Orchestrates all admin functionality.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin
 * @author     Webstepper <contact@webstepper.io>
 */
class WSSCD_Admin_Manager {

	/**
	 * Container instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      object    $container    Container instance.
	 */
	private object $container;

	/**
	 * Initialize the admin manager.
	 *
	 * @since    1.0.0
	 * @param    object $container    Container instance.
	 */
	public function __construct( object $container ) {
		$this->container = $container;
	}

	/**
	 * Initialize admin functionality.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function init(): void {
		// Only run in admin
		if ( ! is_admin() ) {
			return;
		}

		$this->add_hooks();
		$this->init_components();
	}

	/**
	 * Add WordPress hooks.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function add_hooks(): void {
		// Admin initialization
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );

		// Admin notices
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );

		// AJAX handlers now handled by unified router

		// Wizard AJAX is now initialized early in main plugin class
		// to ensure hooks are registered before WordPress processes AJAX

		// Admin footer
		add_filter( 'admin_footer_text', array( $this, 'admin_footer_text' ) );

		// Screen options
		add_filter( 'screen_options_show_screen', array( $this, 'show_screen_options' ), 10, 2 );

		// Help tabs
		add_action( 'current_screen', array( $this, 'add_help_tabs' ) );
	}

	/**
	 * Initialize admin components.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function init_components(): void {
		// Note: capability_manager auto-initializes in __construct, no need to call init()

		$notice_suppressor = new WSSCD_Notice_Suppressor();
		$notice_suppressor->init();

		if ( $this->container->has( 'menu_manager' ) ) {
			$menu_manager = $this->container->get( 'menu_manager' );
			$menu_manager->set_admin( $this );
			$menu_manager->init();
		}
		if ( $this->container->has( 'settings_manager' ) ) {
			$settings_manager = $this->container->get( 'settings_manager' );
			$settings_manager->init();
		}
		if ( $this->container->has( 'notifications_page' ) ) {
			$notifications_page = $this->container->get( 'notifications_page' );
			$notifications_page->init();
		}
		if ( $this->container->has( 'admin_asset_manager' ) ) {
			$asset_manager = $this->container->get( 'admin_asset_manager' );
			$asset_manager->init();
		}
		if ( $this->container->has( 'ajax_router' ) ) {
			$ajax_router = $this->container->get( 'ajax_router' );
			$ajax_router->init();
		}
		if ( $this->container->has( 'recurring_handler' ) ) {
			$recurring_handler = $this->container->get( 'recurring_handler' );
			// Recurring handler hooks are set up in constructor
		}

		// Initialize review notice.
		$review_notice = new WSSCD_Review_Notice( $this->container );
		$review_notice->init();
	}

	/**
	 * Admin initialization.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function admin_init(): void {
		if ( $this->container->has( 'capability_manager' ) ) {
			$capability_manager = $this->container->get( 'capability_manager' );
			if ( ! $capability_manager->current_user_can( 'manage_campaigns' ) ) {
				return;
			}
		}

		$this->register_settings();

		$this->add_meta_boxes();
	}

	/**
	 * Setup admin menu.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function admin_menu(): void {
		if ( $this->container->has( 'menu_manager' ) ) {
			$menu_manager = $this->container->get( 'menu_manager' );
			$menu_manager->add_menus();
		}
	}

	/**
	 * Display admin notices.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function admin_notices(): void {
		$screen = get_current_screen();
		if ( ! $screen || ! $this->is_wsscd_admin_screen( $screen ) ) {
			return;
		}

		// Display notices
		$this->display_notices();
	}

	/**
	 * Handle admin AJAX requests.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function handle_admin_ajax(): void {
		// SECURITY: Extract and sanitize only security-relevant fields for verification.
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Extracting nonce for verification.
		$security_data = array(
			'nonce'    => isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '',
			'_wpnonce' => isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '',
		);
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		// SECURITY: Nonce verification is performed inside WSSCD_Ajax_Security::verify_ajax_request().
		// That method calls verify_nonce() at line 341, which calls wp_verify_nonce() at line 414 of class-ajax-security.php.
		$result = WSSCD_Ajax_Security::verify_ajax_request( 'wsscd_admin_action', $security_data );

		if ( is_wp_error( $result ) ) {
			WSSCD_AJAX_Response::wp_error( $result );
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above via WSSCD_Ajax_Security::verify_ajax_request().
		$action = isset( $_POST['action_type'] ) ? sanitize_text_field( wp_unslash( $_POST['action_type'] ) ) : '';

		switch ( $action ) {
			case 'test_connection':
				$this->ajax_test_connection();
				break;
			case 'clear_cache':
				$this->ajax_clear_cache();
				break;
			default:
				WSSCD_AJAX_Response::error(
					'invalid_action',
					__( 'Invalid action.', 'smart-cycle-discounts' )
				);
		}
	}

	/**
	 * Render notifications page.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function render_notifications_page(): void {

		if ( $this->container->has( 'notifications_page' ) ) {
			$notifications_page = $this->container->get( 'notifications_page' );
			$notifications_page->render();
		} else {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Error: Notifications page service not initialized.', 'smart-cycle-discounts' ) . '</p></div>';
		}
	}

	/**
	 * Render settings page.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function render_settings_page(): void {
		if ( $this->container->has( 'settings_manager' ) ) {
			$settings_manager = $this->container->get( 'settings_manager' );
			if ( method_exists( $settings_manager, 'render_page' ) ) {
				$settings_manager->render_page();
			}
		}
	}

	/**
	 * Customize admin footer text.
	 *
	 * @since    1.0.0
	 * @param    string $footer_text    Current footer text.
	 * @return   string                    Modified footer text.
	 */
	public function admin_footer_text( string $footer_text ): string {
		$screen = get_current_screen();

		if ( $screen && $this->is_wsscd_admin_screen( $screen ) ) {
			$footer_text = sprintf(
				/* translators: %s: plugin name */
				__( 'Thank you for using %s.', 'smart-cycle-discounts' ),
				'<strong>Smart Cycle Discounts</strong>'
			);
		}

		return $footer_text;
	}

	/**
	 * Show screen options.
	 *
	 * @since    1.0.0
	 * @param    bool      $show_screen    Whether to show screen options.
	 * @param    WP_Screen $screen         Current screen object.
	 * @return   bool                       Whether to show screen options.
	 */
	public function show_screen_options( bool $show_screen, WP_Screen $screen ): bool {
		if ( $this->is_wsscd_admin_screen( $screen ) ) {
			return true;
		}

		return $show_screen;
	}

	/**
	 * Add help tabs to admin screens.
	 *
	 * @since    1.0.0
	 * @param    WP_Screen $screen    Current screen object.
	 * @return   void
	 */
	public function add_help_tabs( WP_Screen $screen ): void {
		if ( ! $this->is_wsscd_admin_screen( $screen ) ) {
			return;
		}

		$this->add_general_help_tab( $screen );
		$this->add_screen_specific_help_tabs( $screen );
	}

	/**
	 * Register admin settings.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function register_settings(): void {
		// General settings
		register_setting(
			'wsscd_general_settings',
			'wsscd_general_options',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_general_settings' ),
				'default'           => $this->get_default_general_settings(),
			)
		);

		// Campaign settings
		register_setting(
			'wsscd_campaign_settings',
			'wsscd_campaign_options',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_campaign_settings' ),
				'default'           => $this->get_default_campaign_settings(),
			)
		);
	}

	/**
	 * Add meta boxes.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function add_meta_boxes(): void {
		add_action(
			'add_meta_boxes',
			function () {
				add_meta_box(
					'wsscd_campaign_settings',
					__( 'Campaign Settings', 'smart-cycle-discounts' ),
					array( $this, 'render_campaign_settings_meta_box' ),
					'wsscd_campaign',
					'normal',
					'high'
				);
			}
		);
	}

	/**
	 * Check if current page is an SCD admin page.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $hook_suffix    Hook suffix.
	 * @return   bool                      True if SCD admin page.
	 */
	private function is_wsscd_admin_page( string $hook_suffix ): bool {
		$wsscd_pages = array(
			'toplevel_page_smart-cycle-discounts',
			'smart-cycle-discounts_page_wsscd-campaigns',
			'smart-cycle-discounts_page_wsscd-analytics',
			'smart-cycle-discounts_page_wsscd-settings',
			'smart-cycle-discounts_page_wsscd-tools',
		);

		return in_array( $hook_suffix, $wsscd_pages );
	}

	/**
	 * Check if current screen is an SCD admin screen.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    WP_Screen $screen    Screen object.
	 * @return   bool                    True if SCD admin screen.
	 */
	private function is_wsscd_admin_screen( WP_Screen $screen ): bool {
		return strpos( $screen->id, 'smart-cycle-discounts' ) !== false;
	}

	/**
	 * Display admin notices.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function display_notices(): void {
		if ( ! $this->can_show_notices() ) {
			return;
		}

		$notices = get_transient( 'wsscd_admin_notices' );
		if ( $notices ) {
			foreach ( $notices as $notice ) {
				printf(
					'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
					esc_attr( $notice['type'] ),
					esc_html( $notice['message'] )
				);
			}
			delete_transient( 'wsscd_admin_notices' );
		}

		$this->check_system_requirements();
		$this->check_woocommerce_compatibility();
	}

	/**
	 * Check if user can see admin notices.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   bool    True if user can see notices.
	 */
	private function can_show_notices(): bool {
		return current_user_can( 'manage_options' ) ||
				current_user_can( 'edit_posts' ) ||
				current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Check system requirements.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function check_system_requirements(): void {
		// Check PHP version
		if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: %s: current PHP version */
						__( 'Smart Cycle Discounts requires PHP 7.4 or higher. You are running PHP %s.', 'smart-cycle-discounts' ),
						PHP_VERSION
					)
				)
			);
		}

		// Check WordPress version
		if ( version_compare( get_bloginfo( 'version' ), '6.4', '<' ) ) {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: %s: current WordPress version */
						__( 'Smart Cycle Discounts requires WordPress 6.4 or higher. You are running WordPress %s.', 'smart-cycle-discounts' ),
						get_bloginfo( 'version' )
					)
				)
			);
		}
	}

	/**
	 * Check WooCommerce compatibility.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function check_woocommerce_compatibility(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			printf(
				'<div class="notice notice-warning"><p>%s</p></div>',
				esc_html__( 'Smart Cycle Discounts requires WooCommerce to be installed and activated.', 'smart-cycle-discounts' )
			);
			return;
		}

		if ( version_compare( WC_VERSION, '8.0', '<' ) ) {
			printf(
				'<div class="notice notice-warning"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: %s: current WooCommerce version */
						__( 'Smart Cycle Discounts requires WooCommerce 8.0 or higher. You are running WooCommerce %s.', 'smart-cycle-discounts' ),
						WC_VERSION
					)
				)
			);
		}
	}

	/**
	 * Add general help tab.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    WP_Screen $screen    Screen object.
	 * @return   void
	 */
	private function add_general_help_tab( WP_Screen $screen ): void {
		$screen->add_help_tab(
			array(
				'id'      => 'wsscd_general_help',
				'title'   => __( 'General Help', 'smart-cycle-discounts' ),
				'content' => $this->get_general_help_content(),
			)
		);
	}

	/**
	 * Add screen-specific help tabs.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    WP_Screen $screen    Screen object.
	 * @return   void
	 */
	private function add_screen_specific_help_tabs( WP_Screen $screen ): void {
		switch ( $screen->id ) {
			case 'toplevel_page_smart-cycle-discounts':
				$this->add_dashboard_help_tabs( $screen );
				break;
			case 'smart-cycle-discounts_page_wsscd-campaigns':
				$this->add_campaigns_help_tabs( $screen );
				break;
			case 'smart-cycle-discounts_page_wsscd-analytics':
				$this->add_analytics_help_tabs( $screen );
				break;
		}
	}

	/**
	 * Get general help content.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   string    Help content.
	 */
	private function get_general_help_content(): string {
		return '<p>' . __( 'Smart Cycle Discounts helps you create and manage dynamic discount campaigns for your WooCommerce store.', 'smart-cycle-discounts' ) . '</p>' .
				'<p>' . __( 'Use the navigation menu to access different sections of the plugin.', 'smart-cycle-discounts' ) . '</p>';
	}

	/**
	 * Add dashboard help tabs.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    WP_Screen $screen    Screen object.
	 * @return   void
	 */
	private function add_dashboard_help_tabs( WP_Screen $screen ): void {
		$screen->add_help_tab(
			array(
				'id'      => 'wsscd_dashboard_help',
				'title'   => __( 'Dashboard', 'smart-cycle-discounts' ),
				'content' => '<p>' . __( 'The dashboard provides an overview of your discount campaigns and their performance.', 'smart-cycle-discounts' ) . '</p>',
			)
		);
	}

	/**
	 * Add campaigns help tabs.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    WP_Screen $screen    Screen object.
	 * @return   void
	 */
	private function add_campaigns_help_tabs( WP_Screen $screen ): void {
		$screen->add_help_tab(
			array(
				'id'      => 'wsscd_campaigns_help',
				'title'   => __( 'Campaigns', 'smart-cycle-discounts' ),
				'content' => '<p>' . __( 'Create and manage your discount campaigns here.', 'smart-cycle-discounts' ) . '</p>',
			)
		);
	}

	/**
	 * Add analytics help tabs.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    WP_Screen $screen    Screen object.
	 * @return   void
	 */
	private function add_analytics_help_tabs( WP_Screen $screen ): void {
		$screen->add_help_tab(
			array(
				'id'      => 'wsscd_analytics_help',
				'title'   => __( 'Analytics', 'smart-cycle-discounts' ),
				'content' => '<p>' . __( 'View detailed analytics and reports for your discount campaigns.', 'smart-cycle-discounts' ) . '</p>',
			)
		);
	}

	/**
	 * AJAX test connection.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function ajax_test_connection(): void {
		WSSCD_AJAX_Response::success(
			array(
				'message' => __( 'Connection test successful.', 'smart-cycle-discounts' ),
			)
		);
	}

	/**
	 * AJAX clear cache.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function ajax_clear_cache(): void {
		wp_cache_flush();

		WSSCD_AJAX_Response::success(
			array(
				'message' => __( 'Cache cleared successfully.', 'smart-cycle-discounts' ),
			)
		);
	}

	/**
	 * Sanitize general settings.
	 *
	 * @since    1.0.0
	 * @param    array $input    Input settings.
	 * @return   array              Sanitized settings.
	 */
	public function sanitize_general_settings( array $input ): array {
		$sanitized = array();

		$sanitized['enable_logging'] = ! empty( $input['enable_logging'] );
		$sanitized['log_level']      = sanitize_text_field( $input['log_level'] ?? 'error' );
		$sanitized['cache_duration'] = absint( $input['cache_duration'] ?? 3600 );

		return $sanitized;
	}

	/**
	 * Sanitize campaign settings.
	 *
	 * @since    1.0.0
	 * @param    array $input    Input settings.
	 * @return   array              Sanitized settings.
	 */
	public function sanitize_campaign_settings( array $input ): array {
		$sanitized = array();

		$sanitized['default_priority']   = absint( $input['default_priority'] ?? 3 );
		$sanitized['auto_activate']      = ! empty( $input['auto_activate'] );
		$sanitized['notification_email'] = sanitize_email( $input['notification_email'] ?? '' );

		return $sanitized;
	}

	/**
	 * Get default general settings.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   array    Default settings.
	 */
	private function get_default_general_settings(): array {
		return array(
			'enable_logging' => true,
			'log_level'      => 'error',
			'cache_duration' => 3600,
		);
	}

	/**
	 * Get default campaign settings.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   array    Default settings.
	 */
	private function get_default_campaign_settings(): array {
		return array(
			'default_priority'   => 3,
			'auto_activate'      => false,
			'notification_email' => get_option( 'admin_email' ),
		);
	}

	/**
	 * Render campaign settings meta box.
	 *
	 * @since    1.0.0
	 * @param    WP_Post $post    Post object.
	 * @return   void
	 */
	public function render_campaign_settings_meta_box( WP_Post $post ): void {
		wp_nonce_field( 'wsscd_campaign_meta_box', 'wsscd_campaign_meta_box_nonce' );

		echo '<p>' . esc_html__( 'Campaign settings will be displayed here.', 'smart-cycle-discounts' ) . '</p>';
	}

	/**
	 * Add admin notice.
	 *
	 * @since    1.0.0
	 * @param    string $message    Notice message.
	 * @param    string $type       Notice type (success, error, warning, info).
	 * @return   void
	 */
	public function add_notice( string $message, string $type = 'info' ): void {
		$notices   = get_transient( 'wsscd_admin_notices' ) ?: array();
		$notices[] = array(
			'message' => $message,
			'type'    => $type,
		);
		set_transient( 'wsscd_admin_notices', $notices, 300 ); // 5 minutes
	}
}
