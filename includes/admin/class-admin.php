<?php
/**
 * The admin-specific functionality of the plugin.
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


// Load required classes
if (!class_exists('SCD_AJAX_Response')) {
    require_once SCD_INCLUDES_DIR . 'admin/ajax/class-scd-ajax-response.php';
}
if (!trait_exists('SCD_Admin_Notice_Trait')) {
    require_once SCD_INCLUDES_DIR . 'utilities/traits/trait-admin-notice.php';
}


/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Admin {

    use SCD_Admin_Notice_Trait;

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
     * @param    string    $plugin_name    The name of this plugin.
     * @param    string    $version        The version of this plugin.
     * @param    object    $container      The container instance.
     */
    public function __construct(string $plugin_name, string $version, object $container) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->container = $container;
        
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
         * - SCD_Admin_Asset_Manager orchestrates all asset loading
         * - SCD_Style_Registry defines all styles with dependencies
         * - SCD_Asset_Loader conditionally loads based on context
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
         * - SCD_Admin_Asset_Manager orchestrates all asset loading
         * - SCD_Script_Registry defines all scripts with dependencies
         * - SCD_Asset_Loader conditionally loads based on context
         * - SCD_Asset_Localizer handles all script localization
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
        if ($this->container && $this->container->has('notifications_page')) {
            $notifications_page = $this->container->get('notifications_page');
            $notifications_page->render();
        } else {
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
            echo '<div class="wrap">';
            echo '<h1>' . esc_html__( 'Smart Cycle Discounts Dashboard', 'smart-cycle-discounts' ) . '</h1>';
            echo '<div class="notice notice-error">';
            echo '<p>' . esc_html__( 'Failed to load dashboard. Please try again later.', 'smart-cycle-discounts' ) . '</p>';
            echo '</div>';
            echo '</div>';

            // Log error
            if ( $this->container->has( 'logger' ) ) {
                $logger = $this->container->get( 'logger' );
                $logger->error( 'Failed to render main dashboard page', array(
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ) );
            }
        }
    }

    /**
     * Check if current context requires wizard initialization.
     *
     * @since    1.0.0
     * @access   private
     * @return   bool    True if wizard should be initialized.
     */
    private function is_wizard_context(): bool {
        // Check if it's an AJAX request
        if ( wp_doing_ajax() ) {
            // Check if it's a wizard-related AJAX action
            $action = $_POST['scd_action'] ?? $_REQUEST['scd_action'] ?? '';
            $wizard_actions = array(
                'save_step',
                'load_data',
                'validate_step',
                'complete_wizard',
                'check_session',
                'recover_session',
                'product_search'
            );
            return in_array( $action, $wizard_actions, true );
        }
        
        // Check if it's a wizard page
        $page = $_GET['page'] ?? '';
        $action = $_GET['action'] ?? '';
        
        return $page === 'scd-campaigns' && $action === 'wizard';
    }

    /**
     * Initialize wizard navigation component.
     *
     * @since    1.0.0
     * @access   private
     * @return   void
     */
    private function init_wizard_navigation(): void {
        // Initialize navigation through wizard manager to ensure single instance
        $wizard_manager = $this->container->get('wizard_manager');
        // Navigation will be initialized through wizard manager when needed
        $wizard_manager->get_navigation();
    }

    /**
     * Render campaigns page.
     *
     * @since    1.0.0
     * @return   void
     */
    public function render_campaigns_page(): void {
        if (defined('SCD_DEBUG') && SCD_DEBUG) {
        }
        
        // Load diagnostic tool if requested
        if ( isset( $_GET['scd_db_check'] ) && current_user_can( 'manage_options' ) ) {
            require_once SCD_PLUGIN_DIR . 'database/tools/database-diagnostic.php';
            return;
        }
        
        // Check if wizard action is requested
        if ( isset( $_GET['action'] ) && 'wizard' === sanitize_text_field( wp_unslash( $_GET['action'] ) ) ) {
            $this->render_wizard();
            return;
        }
        
        // Render campaigns page
        try {
            $campaigns_page = $this->container->get('campaigns_page');
            if (defined('SCD_DEBUG') && SCD_DEBUG) {
            }
            $campaigns_page->render();
        } catch (Exception $e) {
            if (defined('SCD_DEBUG') && SCD_DEBUG) {
            }
            // Show error to admin
            echo '<div class="wrap">';
            echo '<h1>' . esc_html__('Campaigns', 'smart-cycle-discounts') . '</h1>';
            echo '<div class="notice notice-error">';
            echo '<p>' . esc_html__('Error loading campaigns page. Please check error logs.', 'smart-cycle-discounts') . '</p>';
            echo '</div>';
            echo '</div>';
        }
    }
    
    /**
     * Render wizard page.
     *
     * @since    1.0.0
     * @return   void
     */
    private function render_wizard(): void {
        if ( defined( 'SCD_DEBUG' ) && SCD_DEBUG ) {
        }
        
        // Get wizard controller and handle request
        try {
            $wizard_controller = $this->container->get( 'campaign_wizard_controller' );
            if ( defined( 'SCD_DEBUG' ) && SCD_DEBUG ) {
            }
            $wizard_controller->handle();
        } catch ( Exception $e ) {
            if ( defined( 'SCD_DEBUG' ) && SCD_DEBUG ) {
            }
            // Show error to admin
            echo '<div class="wrap">';
            echo '<h1>' . esc_html__( 'Campaign Wizard', 'smart-cycle-discounts' ) . '</h1>';
            echo '<div class="notice notice-error">';
            echo '<p>' . esc_html__( 'Error loading wizard. Please check error logs.', 'smart-cycle-discounts' ) . '</p>';
            echo '</div>';
            echo '</div>';
        }
    }

    /**
     * Render analytics page.
     *
     * @since    1.0.0
     * @return   void
     */
    public function render_analytics_page(): void {
        // Render analytics page using container service
        try {
            $analytics_page = $this->container->get( 'analytics_page' );
            $analytics_page->render();
        } catch ( Exception $e ) {
            if ( defined( 'SCD_DEBUG' ) && SCD_DEBUG ) {
            }

            // Show error to admin
            echo '<div class="wrap">';
            echo '<h1>' . esc_html__( 'Analytics', 'smart-cycle-discounts' ) . '</h1>';
            echo '<div class="notice notice-error">';
            echo '<p>' . esc_html__( 'Error loading analytics dashboard. Please check error logs.', 'smart-cycle-discounts' ) . '</p>';
            echo '</div>';
            echo '</div>';
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
        // Get settings manager from container
        if ( ! $this->container->has( 'settings_manager' ) ) {
            echo '<div class="wrap">';
            echo '<h1>' . esc_html__( 'Settings', 'smart-cycle-discounts' ) . '</h1>';
            echo '<div class="notice notice-error">';
            echo '<p>' . esc_html__( 'Settings manager not available. Please check configuration.', 'smart-cycle-discounts' ) . '</p>';
            echo '</div>';
            echo '</div>';
            return;
        }

        try {
            $settings_manager = $this->container->get( 'settings_manager' );
            $settings_manager->render_page();
        } catch ( Exception $e ) {
            if ( defined( 'SCD_DEBUG' ) && SCD_DEBUG ) {
            }

            echo '<div class="wrap">';
            echo '<h1>' . esc_html__( 'Settings', 'smart-cycle-discounts' ) . '</h1>';
            echo '<div class="notice notice-error">';
            echo '<p>' . esc_html__( 'Error loading settings page. Please check error logs.', 'smart-cycle-discounts' ) . '</p>';
            echo '</div>';
            echo '</div>';
        }
    }

    /**
     * Render tools page.
     *
     * @since    1.0.0
     * @return   void
     */
    public function render_tools_page(): void {
        // Get tools page from container
        if ( ! $this->container->has( 'tools_page' ) ) {
            echo '<div class="wrap">';
            echo '<h1>' . esc_html__( 'Tools & Maintenance', 'smart-cycle-discounts' ) . '</h1>';
            echo '<div class="notice notice-error">';
            echo '<p>' . esc_html__( 'Tools page not available. Please check configuration.', 'smart-cycle-discounts' ) . '</p>';
            echo '</div>';
            echo '</div>';
            return;
        }

        try {
            $tools_page = $this->container->get( 'tools_page' );
            $tools_page->render();
        } catch ( Exception $e ) {
            if ( defined( 'SCD_DEBUG' ) && SCD_DEBUG ) {
            }

            echo '<div class="wrap">';
            echo '<h1>' . esc_html__( 'Tools & Maintenance', 'smart-cycle-discounts' ) . '</h1>';
            echo '<div class="notice notice-error">';
            echo '<p>' . esc_html__( 'Error loading tools page. Please check error logs.', 'smart-cycle-discounts' ) . '</p>';
            echo '</div>';
            echo '</div>';
        }
    }

}