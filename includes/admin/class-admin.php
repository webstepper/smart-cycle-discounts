<?php
/**
 * Admin Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/class-admin.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress/plugins/smart-cycle-discounts/
 * @since      1.0.0
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


if ( ! class_exists( 'WSSCD_AJAX_Response' ) ) {
	require_once WSSCD_INCLUDES_DIR . 'admin/ajax/class-wsscd-ajax-response.php';
}
if ( ! trait_exists( 'WSSCD_Admin_Notice_Trait' ) ) {
	require_once WSSCD_INCLUDES_DIR . 'utilities/traits/trait-admin-notice.php';
}


/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin
 * @author     Webstepper <contact@webstepper.io>
 */
class WSSCD_Admin {

	use WSSCD_Admin_Notice_Trait;

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private string $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private string $version;

	/**
	 * The container instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      object    $container    The container instance.
	 */
	private object $container;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param    string $plugin_name    The name of this plugin.
	 * @param    string $version        The version of this plugin.
	 * @param    object $container      The container instance.
	 */
	public function __construct( string $plugin_name, string $version, object $container ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
		$this->container   = $container;

		// Only initialize wizard navigation on wizard pages or AJAX requests
		if ( $this->is_wizard_context() ) {
			$this->init_wizard_navigation();
		}
	}

	/**
	 * Initialize admin functionality.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function admin_init(): void {
		// Admin initialization logic
		// Admin manager is now initialized in define_admin_hooks
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function enqueue_styles(): void {
		/**
		 * All styles are handled by the centralized Asset Management System:
		 * - WSSCD_Admin_Asset_Manager orchestrates all asset loading
		 * - WSSCD_Style_Registry defines all styles with dependencies
		 * - WSSCD_Asset_Loader conditionally loads based on context
		 *
		 * @see includes/admin/class-admin-asset-manager.php
		 * @see includes/admin/assets/class-style-registry.php
		 * @see includes/admin/assets/class-asset-loader.php
		 */
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function enqueue_scripts(): void {
		/**
		 * All scripts are handled by the centralized Asset Management System:
		 * - WSSCD_Admin_Asset_Manager orchestrates all asset loading
		 * - WSSCD_Script_Registry defines all scripts with dependencies
		 * - WSSCD_Asset_Loader conditionally loads based on context
		 * - WSSCD_Asset_Localizer handles all script localization
		 *
		 * @see includes/admin/class-admin-asset-manager.php
		 * @see includes/admin/assets/class-script-registry.php
		 * @see includes/admin/assets/class-asset-loader.php
		 * @see includes/admin/assets/class-asset-localizer.php
		 */
	}

	/**
	 * Display admin notices.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function display_admin_notices(): void {
		// Display stored notices using the Admin_Notice trait
		$this->display_persistent_notices();
	}

	/**
	 * Render notifications page.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function render_notifications_page(): void {
		if ( $this->container && $this->container->has( 'notifications_page' ) ) {
			$notifications_page = $this->container->get( 'notifications_page' );
			$notifications_page->render();
		} else {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static fallback HTML.
			echo '<div class="wrap"><h1>Email Notifications</h1><p>Notifications page not available.</p></div>';
		}
	}

	/**
	 * Render dashboard page.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function render_dashboard_page(): void {
		// Use the new main dashboard page controller
		try {
			$main_dashboard_page = $this->container->get( 'main_dashboard_page' );
			$main_dashboard_page->render();
		} catch ( Exception $e ) {
			// Fallback to simple error display
			// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Static HTML structure with escaped content.
			echo '<div class="wrap">';
			echo '<h1>' . esc_html__( 'Smart Cycle Discounts Dashboard', 'smart-cycle-discounts' ) . '</h1>';
			echo '<div class="notice notice-error">';
			echo '<p>' . esc_html__( 'Failed to load dashboard. Please try again later.', 'smart-cycle-discounts' ) . '</p>';
			echo '</div>';
			echo '</div>';
			// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped

			// Log error
			if ( $this->container->has( 'logger' ) ) {
				$logger = $this->container->get( 'logger' );
				$logger->error(
					'Failed to render main dashboard page',
					array(
						'error' => $e->getMessage(),
						'trace' => $e->getTraceAsString(),
					)
				);
			}
		}
	}

	/**
	 * Check if current context requires wizard initialization.
	 *
	 * SECURITY: This method ONLY determines whether to initialize wizard components.
	 * It reads URL/POST parameters for routing purposes only - no data processing occurs.
	 * All parameter values are sanitized and validated against whitelists.
	 * Actual AJAX handlers verify nonces before processing any data.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   bool    True if wizard should be initialized.
	 */
	private function is_wizard_context(): bool {
		// Only check context for admin users - this is called from constructor
		// so we need to be careful about capability checks timing.
		if ( ! is_admin() ) {
			return false;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Context detection for routing only. No data modification. Values validated against whitelist.
		if ( wp_doing_ajax() ) {
			// Read action from POST first, then fallback to REQUEST.
			$action = isset( $_POST['wsscd_action'] ) ? sanitize_key( wp_unslash( $_POST['wsscd_action'] ) ) : '';
			if ( empty( $action ) ) {
				$action = isset( $_REQUEST['wsscd_action'] ) ? sanitize_key( wp_unslash( $_REQUEST['wsscd_action'] ) ) : '';
			}

			// Whitelist of wizard-related actions - only these will trigger wizard initialization.
			$wizard_actions = array(
				'save_step',
				'load_data',
				'validate_step',
				'complete_wizard',
				'check_session',
				'recover_session',
				'product_search',
			);

			// Strict whitelist validation - action must match exactly.
			return in_array( $action, $wizard_actions, true );
		}

		// For non-AJAX requests, check URL parameters.
		$page   = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended

		return 'wsscd-campaigns' === $page && 'wizard' === $action;
	}

	/**
	 * Initialize wizard navigation component.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function init_wizard_navigation(): void {
		$wizard_manager = $this->container->get( 'wizard_manager' );
		// Navigation will be initialized through wizard manager when needed
		$wizard_manager->get_navigation();
	}

	/**
	 * Render campaigns page.
	 *
	 * SECURITY: This method renders admin pages. GET parameters are read for routing
	 * only (determining which view to display). No data modification occurs.
	 * Capability checks are enforced via WordPress menu registration.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function render_campaigns_page(): void {
		if ( defined( 'WSSCD_DEBUG' ) && WSSCD_DEBUG ) {
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Reading URL params for page routing only. Capability checked via menu registration.
		if ( isset( $_GET['wsscd_db_check'] ) && current_user_can( 'manage_options' ) ) {
			require_once WSSCD_PLUGIN_DIR . 'database/tools/database-diagnostic.php';
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Reading URL params for page routing only.
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		if ( 'wizard' === $action ) {
			$this->render_wizard();
			return;
		}

		try {
			$campaigns_page = $this->container->get( 'campaigns_page' );
			if ( defined( 'WSSCD_DEBUG' ) && WSSCD_DEBUG ) {
			}
			$campaigns_page->render();
		} catch ( Exception $e ) {
			if ( defined( 'WSSCD_DEBUG' ) && WSSCD_DEBUG ) {
			}
			// Show error to admin
			// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Static HTML with escaped content.
			echo '<div class="wrap">';
			echo '<h1>' . esc_html__( 'Campaigns', 'smart-cycle-discounts' ) . '</h1>';
			echo '<div class="notice notice-error">';
			echo '<p>' . esc_html__( 'Error loading campaigns page. Please check error logs.', 'smart-cycle-discounts' ) . '</p>';
			echo '</div>';
			echo '</div>';
			// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	/**
	 * Render wizard page.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function render_wizard(): void {
		if ( defined( 'WSSCD_DEBUG' ) && WSSCD_DEBUG ) {
		}

		try {
			$wizard_controller = $this->container->get( 'campaign_wizard_controller' );
			if ( defined( 'WSSCD_DEBUG' ) && WSSCD_DEBUG ) {
			}
			$wizard_controller->handle();
		} catch ( Exception $e ) {
			if ( defined( 'WSSCD_DEBUG' ) && WSSCD_DEBUG ) {
			}
			// Show error to admin
			// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Static HTML with escaped content.
			echo '<div class="wrap">';
			echo '<h1>' . esc_html__( 'Campaign Wizard', 'smart-cycle-discounts' ) . '</h1>';
			echo '<div class="notice notice-error">';
			echo '<p>' . esc_html__( 'Error loading wizard. Please check error logs.', 'smart-cycle-discounts' ) . '</p>';
			echo '</div>';
			echo '</div>';
			// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	/**
	 * Render analytics page.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function render_analytics_page(): void {
		try {
			$analytics_page = $this->container->get( 'analytics_page' );
			$analytics_page->render();
		} catch ( Exception $e ) {
			if ( defined( 'WSSCD_DEBUG' ) && WSSCD_DEBUG ) {
			}

			// Show error to admin
			// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Static HTML with escaped content.
			echo '<div class="wrap">';
			echo '<h1>' . esc_html__( 'Analytics', 'smart-cycle-discounts' ) . '</h1>';
			echo '<div class="notice notice-error">';
			echo '<p>' . esc_html__( 'Error loading analytics dashboard. Please check error logs.', 'smart-cycle-discounts' ) . '</p>';
			echo '</div>';
			echo '</div>';
			// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	/**
	 * Render settings page with tabs.
	 *
	 * Uses the new Settings Manager for unified settings handling.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function render_settings_page(): void {
		if ( ! $this->container->has( 'settings_manager' ) ) {
			// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Static HTML with escaped content.
			echo '<div class="wrap">';
			echo '<h1>' . esc_html__( 'Settings', 'smart-cycle-discounts' ) . '</h1>';
			echo '<div class="notice notice-error">';
			echo '<p>' . esc_html__( 'Settings manager not available. Please check configuration.', 'smart-cycle-discounts' ) . '</p>';
			echo '</div>';
			echo '</div>';
			// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}

		try {
			$settings_manager = $this->container->get( 'settings_manager' );
			$settings_manager->render_page();
		} catch ( Exception $e ) {
			if ( defined( 'WSSCD_DEBUG' ) && WSSCD_DEBUG ) {
			}

			// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Static HTML with escaped content.
			echo '<div class="wrap">';
			echo '<h1>' . esc_html__( 'Settings', 'smart-cycle-discounts' ) . '</h1>';
			echo '<div class="notice notice-error">';
			echo '<p>' . esc_html__( 'Error loading settings page. Please check error logs.', 'smart-cycle-discounts' ) . '</p>';
			echo '</div>';
			echo '</div>';
			// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	/**
	 * Render tools page.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function render_tools_page(): void {
		if ( ! $this->container->has( 'tools_page' ) ) {
			// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Static HTML with escaped content.
			echo '<div class="wrap">';
			echo '<h1>' . esc_html__( 'Tools & Maintenance', 'smart-cycle-discounts' ) . '</h1>';
			echo '<div class="notice notice-error">';
			echo '<p>' . esc_html__( 'Tools page not available. Please check configuration.', 'smart-cycle-discounts' ) . '</p>';
			echo '</div>';
			echo '</div>';
			// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}

		try {
			$tools_page = $this->container->get( 'tools_page' );
			$tools_page->render();
		} catch ( Exception $e ) {
			if ( defined( 'WSSCD_DEBUG' ) && WSSCD_DEBUG ) {
			}

			// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Static HTML with escaped content.
			echo '<div class="wrap">';
			echo '<h1>' . esc_html__( 'Tools & Maintenance', 'smart-cycle-discounts' ) . '</h1>';
			echo '<div class="notice notice-error">';
			echo '<p>' . esc_html__( 'Error loading tools page. Please check error logs.', 'smart-cycle-discounts' ) . '</p>';
			echo '</div>';
			echo '</div>';
			// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}
}
