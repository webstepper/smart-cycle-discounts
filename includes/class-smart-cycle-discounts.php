<?php
/**
 * Smart Cycle Discounts Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/class-smart-cycle-discounts.php
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
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes
 * @author     Webstepper <contact@webstepper.io>
 */
class Smart_Cycle_Discounts {

	/**
	 * The single instance of the class.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      Smart_Cycle_Discounts|null    $instance    The single instance.
	 */
	private static ?Smart_Cycle_Discounts $instance = null;

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      WSSCD_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected WSSCD_Loader $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected string $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected string $version;

	/**
	 * The dependency injection container.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      object    $container    The DI container instance.
	 */
	protected object $container;

	/**
	 * Static container reference for service access.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      object|null    $static_container    Static container reference.
	 */
	private static ?object $static_container = null;

	/**
	 * The service registry.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      object|null    $service_registry    The service registry instance.
	 */
	protected ?object $service_registry = null;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	private function __construct() {
		if ( defined( 'WSSCD_VERSION' ) ) {
			$this->version = WSSCD_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'smart-cycle-discounts';

		$this->load_dependencies();
		$this->init_error_handling();
		$this->setup_container();
		// AJAX hooks are now registered directly in the main plugin file
		$this->setup_performance_optimizations();
		$this->set_locale();

		// Defer ActionScheduler initialization until after it's ready
		// ActionScheduler initializes on 'init' priority 1, so we use priority 20
		add_action( 'init', array( $this, 'init_cron_scheduler' ), 20 );

		$this->init_campaign_event_hooks();  // Register campaign cron event hooks
		$this->define_admin_hooks();
		$this->define_public_hooks();

		// Clear license caches on plugin update
		add_action( 'upgrader_process_complete', array( $this, 'on_plugin_update' ), 10, 2 );
	}

	/**
	 * Get the single instance of the class.
	 *
	 * @since    1.0.0
	 * @return   Smart_Cycle_Discounts    The single instance.
	 */
	public static function get_instance(): Smart_Cycle_Discounts {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Prevent cloning of the instance.
	 *
	 * @since    1.0.0
	 */
	private function __clone() {
		_doing_it_wrong( __FUNCTION__, 'Cloning is forbidden.', '1.0.0' );
	}

	/**
	 * Prevent unserializing of the instance.
	 *
	 * @since    1.0.0
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, 'Unserializing is forbidden.', '1.0.0' );
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - WSSCD_Loader. Orchestrates the hooks of the plugin.
	 * - WSSCD_i18n. Defines internationalization functionality.
	 * - WSSCD_Admin. Defines all hooks for the admin area.
	 * - WSSCD_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies(): void {
		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once WSSCD_INCLUDES_DIR . 'class-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once WSSCD_INCLUDES_DIR . 'class-i18n.php';

		/**
		 * Load the container and service registry classes first
		 */
		require_once WSSCD_INCLUDES_DIR . 'bootstrap/class-container.php';
		require_once WSSCD_INCLUDES_DIR . 'bootstrap/class-service-definitions.php';
		require_once WSSCD_INCLUDES_DIR . 'bootstrap/class-service-registry.php';

		/**
		 * Load autoloader first
		 */
		require_once WSSCD_INCLUDES_DIR . 'class-autoloader.php';
		WSSCD_Autoloader::register();

		/**
		 * Load essential classes only
		 */
		require_once WSSCD_INCLUDES_DIR . 'utilities/class-logger.php';
		require_once WSSCD_INCLUDES_DIR . 'utilities/class-wsscd-log.php';
		require_once WSSCD_INCLUDES_DIR . 'utilities/class-requirements-checker.php';
		require_once WSSCD_INCLUDES_DIR . 'utilities/class-time-helpers.php';
		require_once WSSCD_INCLUDES_DIR . 'utilities/class-datetime-builder.php';
		require_once WSSCD_INCLUDES_DIR . 'utilities/class-datetime-splitter.php';
		require_once WSSCD_INCLUDES_DIR . 'cache/class-cache-manager.php';
		require_once WSSCD_INCLUDES_DIR . 'database/class-database-manager.php';
		require_once WSSCD_INCLUDES_DIR . 'database/class-migration-manager.php';
		require_once WSSCD_INCLUDES_DIR . 'core/campaigns/class-campaign.php';
		require_once WSSCD_INCLUDES_DIR . 'database/repositories/class-campaign-repository.php';
		require_once WSSCD_INCLUDES_DIR . 'database/repositories/class-discount-repository.php';
		require_once WSSCD_INCLUDES_DIR . 'database/repositories/class-analytics-repository.php';
		require_once WSSCD_INCLUDES_DIR . 'database/repositories/class-customer-usage-repository.php';
		require_once WSSCD_INCLUDES_DIR . 'core/campaigns/class-campaign-manager.php';
		require_once WSSCD_INCLUDES_DIR . 'core/managers/class-customer-usage-manager.php';
		require_once WSSCD_INCLUDES_DIR . 'core/cron/class-cron-scheduler.php';
		require_once WSSCD_INCLUDES_DIR . 'core/analytics/class-analytics-collector.php';
		require_once WSSCD_INCLUDES_DIR . 'core/analytics/class-metrics-calculator.php';

		// Pro-only: Report Generator (loaded conditionally)
		if ( file_exists( WSSCD_INCLUDES_DIR . 'core/analytics/class-report-generator.php' ) ) {
			require_once WSSCD_INCLUDES_DIR . 'core/analytics/class-report-generator.php';
		}

		require_once WSSCD_INCLUDES_DIR . 'core/validation/class-validation.php';
		require_once WSSCD_INCLUDES_DIR . 'utilities/traits/trait-admin-notice.php';
		// Removed: class-input-sanitizer.php - Using Field_Definitions instead
		require_once WSSCD_INCLUDES_DIR . 'security/class-nonce-manager.php';
		require_once WSSCD_INCLUDES_DIR . 'security/class-rate-limiter.php';
		require_once WSSCD_INCLUDES_DIR . 'security/class-audit-logger.php';
		require_once WSSCD_INCLUDES_DIR . 'admin/class-capability-manager.php';
		require_once WSSCD_INCLUDES_DIR . 'admin/class-admin-asset-manager.php';
		require_once WSSCD_INCLUDES_DIR . 'admin/class-menu-manager.php';
		require_once WSSCD_INCLUDES_DIR . 'admin/class-admin-manager.php';

		/**
		 * Load AJAX handlers and services
		 */
		require_once WSSCD_INCLUDES_DIR . 'core/wizard/class-wizard-state-service.php';
		// AJAX endpoints removed - now handled by unified router

		/**
		 * Load wizard components and services
		 */
		require_once WSSCD_INCLUDES_DIR . 'utilities/class-session-service.php';
		require_once WSSCD_INCLUDES_DIR . 'core/wizard/class-wizard-manager.php';
		require_once WSSCD_INCLUDES_DIR . 'core/wizard/class-sidebar-base.php';
		require_once WSSCD_INCLUDES_DIR . 'utilities/class-session-lock-service.php';

		// Load wizard services
		require_once WSSCD_INCLUDES_DIR . 'core/wizard/class-wizard-step-registry.php';
		require_once WSSCD_INCLUDES_DIR . 'core/wizard/class-idempotency-service.php';
		require_once WSSCD_INCLUDES_DIR . 'core/wizard/class-step-data-transformer.php';
		require_once WSSCD_INCLUDES_DIR . 'core/wizard/class-campaign-change-tracker.php';

		if ( ! class_exists( 'WSSCD_Concurrent_Modification_Exception' ) ) {
			require_once WSSCD_INCLUDES_DIR . 'core/exceptions/class-concurrent-modification-exception.php';
		}

		/**
		 * Load security manager if not already loaded
		 */
		if ( file_exists( WSSCD_INCLUDES_DIR . 'security/class-security-manager.php' ) ) {
			require_once WSSCD_INCLUDES_DIR . 'security/class-security-manager.php';
		}

		/**
		 * Load discount classes
		 */
		require_once WSSCD_INCLUDES_DIR . 'core/discounts/interface-discount-strategy.php';

		// Free discount strategies (always loaded)
		require_once WSSCD_INCLUDES_DIR . 'core/discounts/strategies/class-percentage-strategy.php';
		require_once WSSCD_INCLUDES_DIR . 'core/discounts/strategies/class-fixed-strategy.php';

		// Pro-only discount strategies (loaded conditionally)
		if ( file_exists( WSSCD_INCLUDES_DIR . 'core/discounts/strategies/class-bogo-strategy.php' ) ) {
			require_once WSSCD_INCLUDES_DIR . 'core/discounts/strategies/class-bogo-strategy.php';
		}
		if ( file_exists( WSSCD_INCLUDES_DIR . 'core/discounts/strategies/class-tiered-strategy.php' ) ) {
			require_once WSSCD_INCLUDES_DIR . 'core/discounts/strategies/class-tiered-strategy.php';
		}
		if ( file_exists( WSSCD_INCLUDES_DIR . 'core/discounts/strategies/class-spend-threshold-strategy.php' ) ) {
			require_once WSSCD_INCLUDES_DIR . 'core/discounts/strategies/class-spend-threshold-strategy.php';
		}

		require_once WSSCD_INCLUDES_DIR . 'core/discounts/class-discount-engine.php';
		require_once WSSCD_INCLUDES_DIR . 'core/discounts/class-discount-applicator.php';

		/**
		 * Load product selection components
		 */
		require_once WSSCD_INCLUDES_DIR . 'core/products/class-product-selector.php';
		require_once WSSCD_INCLUDES_DIR . 'core/products/class-product-filter.php';
		require_once WSSCD_INCLUDES_DIR . 'core/products/class-condition-engine.php';
		require_once WSSCD_INCLUDES_DIR . 'admin/components/class-condition-builder.php';

		/**
		 * Load currency change system
		 */
		require_once WSSCD_INCLUDES_DIR . 'core/services/class-currency-change-service.php';
		require_once WSSCD_INCLUDES_DIR . 'admin/class-currency-change-notices.php';
		require_once WSSCD_INCLUDES_DIR . 'admin/class-campaign-expiration-notices.php';
		require_once WSSCD_INCLUDES_DIR . 'admin/pages/class-currency-review-page.php';

		/**
		 * Load licensing classes
		 */
		require_once WSSCD_INCLUDES_DIR . 'admin/licensing/class-feature-gate.php';
		if ( file_exists( WSSCD_INCLUDES_DIR . 'admin/licensing/class-license-manager.php' ) ) {
			require_once WSSCD_INCLUDES_DIR . 'admin/licensing/class-license-manager.php';
		}
		require_once WSSCD_INCLUDES_DIR . 'admin/licensing/license-functions.php';
		require_once WSSCD_INCLUDES_DIR . 'admin/licensing/class-license-notices.php';
		require_once WSSCD_INCLUDES_DIR . 'admin/licensing/class-upgrade-prompt-manager.php';

		/**
		 * Load AJAX licensing validation trait
		 */
		require_once WSSCD_INCLUDES_DIR . 'admin/ajax/trait-license-validation.php';

		/**
		 * Load admin page classes
		 */
		require_once WSSCD_INCLUDES_DIR . 'admin/pages/class-campaigns-page.php';
		require_once WSSCD_INCLUDES_DIR . 'admin/pages/dashboard/class-main-dashboard-page.php';

		if ( is_admin() ) {
			require_once WSSCD_INCLUDES_DIR . 'admin/components/class-badge-helper.php';
			require_once WSSCD_INCLUDES_DIR . 'admin/components/class-campaigns-list-table.php';
		}

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once WSSCD_INCLUDES_DIR . 'admin/class-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once WSSCD_INCLUDES_DIR . 'frontend/class-frontend-manager.php';
		require_once WSSCD_INCLUDES_DIR . 'frontend/class-frontend-asset-manager.php';
		require_once WSSCD_INCLUDES_DIR . 'frontend/class-discount-display.php';
		require_once WSSCD_INCLUDES_DIR . 'frontend/class-countdown-timer.php';
		require_once WSSCD_INCLUDES_DIR . 'frontend/class-shortcodes.php';
		require_once WSSCD_INCLUDES_DIR . 'frontend/class-template-loader.php';

		/**
		 * Load integration classes
		 */
		require_once WSSCD_INCLUDES_DIR . 'integrations/interface-ecommerce-integration.php';
		require_once WSSCD_INCLUDES_DIR . 'integrations/woocommerce/class-wc-analytics-integration.php';
		require_once WSSCD_INCLUDES_DIR . 'integrations/woocommerce/class-woocommerce-integration.php';
		require_once WSSCD_INCLUDES_DIR . 'integrations/class-privacy-integration.php';
		require_once WSSCD_INCLUDES_DIR . 'integrations/class-integration-manager.php';

		/**
		 * Load API classes (Pro-only)
		 */
		if ( file_exists( WSSCD_INCLUDES_DIR . 'api/class-rest-api-manager.php' ) ) {
			require_once WSSCD_INCLUDES_DIR . 'api/class-rest-api-manager.php';
		}

		/**
		 * Load health check if needed
		 */
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			require_once WSSCD_INCLUDES_DIR . 'utilities/class-service-health-check.php';
			require_once WSSCD_INCLUDES_DIR . 'cli/class-wsscd-cli-health-check.php';
		}

		$this->loader = new WSSCD_Loader();
	}

	/**
	 * Initialize error handling system.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function init_error_handling(): void {
		if ( ! class_exists( 'WSSCD_Error_Handler' ) ) {
			require_once WSSCD_PLUGIN_DIR . 'includes/utilities/class-error-handler.php';
		}

		// Connect error handler to logger after container is set up
		add_action( 'wsscd_error_logged', array( $this, 'log_error_to_logger' ), 10, 3 );
		add_action( 'wsscd_exception_logged', array( $this, 'log_exception_to_logger' ), 10, 2 );
	}

	/**
	 * Setup the dependency injection container.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function setup_container(): void {
		// Ensure container class is loaded
		if ( ! class_exists( 'WSSCD_Container' ) ) {
			require_once WSSCD_INCLUDES_DIR . 'bootstrap/class-container.php';
		}

		$this->container = new WSSCD_Container();

		self::$static_container = $this->container;

		// Also set in GLOBALS for AJAX handlers
		$GLOBALS['wsscd_container'] = $this->container;

		// Ensure service definitions class is loaded
		if ( ! class_exists( 'WSSCD_Service_Definitions' ) ) {
			require_once WSSCD_INCLUDES_DIR . 'bootstrap/class-service-definitions.php';
		}

		// Ensure key classes are loaded before service registration
		if ( ! class_exists( 'WSSCD_Campaign_Repository' ) ) {
			require_once WSSCD_INCLUDES_DIR . 'database/repositories/class-campaign-repository.php';
		}
		if ( ! class_exists( 'WSSCD_Database_Manager' ) ) {
			require_once WSSCD_INCLUDES_DIR . 'database/class-database-manager.php';
		}
		if ( ! class_exists( 'WSSCD_Cache_Manager' ) ) {
			require_once WSSCD_INCLUDES_DIR . 'cache/class-cache-manager.php';
		}

		if ( ! class_exists( 'WSSCD_Service_Registry' ) ) {
			require_once WSSCD_INCLUDES_DIR . 'bootstrap/class-service-registry.php';
		}

		$service_registry = new WSSCD_Service_Registry( $this->container );

		$result = $service_registry->register_all_services();

		if ( ! $result ) {
			// Log validation errors
			$errors = $service_registry->get_errors();

			$error_details = '';
			foreach ( $errors as $service => $error ) {
				WSSCD_Log::error( sprintf( 'Service init failed: %s', $service ), array( 'error' => $error ) );
				$error_details .= sprintf( "\n- %s: %s", $service, $error );
			}

			// Fatal error - container system must work
			wp_die(
				'<h1>Smart Cycle Discounts: Service Container Initialization Failed</h1>' .
				'<p>The following services failed to register:</p>' .
				'<pre>' . esc_html( $error_details ) . '</pre>' .
				'<p>Please check the error logs for more details.</p>',
				'Service Container Error',
				array( 'back_link' => true )
			);
		}
	}

	/**
	 * Handle AJAX request directly
	 *
	 * @since    1.0.0
	 * @access   public
	 */
	public function handle_ajax_request(): void {
		require_once WSSCD_INCLUDES_DIR . 'admin/ajax/class-ajax-router.php';

		if ( class_exists( 'WSSCD_Ajax_Router' ) ) {
			$router = new WSSCD_Ajax_Router();
			$router->route_request();
		} else {
			wp_send_json_error(
				array(
					'message' => 'AJAX router unavailable',
				)
			);
		}
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the WSSCD_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale(): void {
		$plugin_i18n = new WSSCD_i18n();

		$this->loader->add_action( 'init', $plugin_i18n, 'load_plugin_textdomain' );
	}

	/**
	 * Initialize cron scheduler.
	 *
	 * Called on 'init' hook after ActionScheduler is ready.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function init_cron_scheduler(): void {
		if ( $this->container && $this->container->has( 'cron_scheduler' ) ) {
			$cron_scheduler = $this->container->get( 'cron_scheduler' );
			$cron_scheduler->init();
		}
	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks(): void {
		$plugin_admin = new WSSCD_Admin( $this->get_plugin_name(), $this->get_version(), $this->container );

		if ( $this->container->has( 'license_manager' ) ) {
			$this->container->get( 'license_manager' );
		}

		if ( $this->container->has( 'license_notices' ) ) {
			$license_notices = $this->container->get( 'license_notices' );
			$license_notices->init();
		}

		if ( $this->container->has( 'upgrade_prompt_manager' ) ) {
			$this->container->get( 'upgrade_prompt_manager' );
		}

		// Initialize AJAX router to register AJAX action hooks
		if ( $this->container->has( 'ajax_router' ) ) {
			$ajax_router = $this->container->get( 'ajax_router' );
			$ajax_router->init();
		}

		// Initialize wizard intent handler early to process intents before headers are sent.
		// This is critical for session cookie management - setcookie() must run before output.
		if ( $this->container->has( 'wizard_intent_handler' ) ) {
			$wizard_intent_handler = $this->container->get( 'wizard_intent_handler' );
			$wizard_intent_handler->init();
		}

		if ( $this->container->has( 'admin_manager' ) ) {
			$admin_manager = $this->container->get( 'admin_manager' );
			$admin_manager->init();
		}

		if ( $this->container->has( 'analytics_collector' ) ) {
			$analytics_collector = $this->container->get( 'analytics_collector' );
			$analytics_collector->init();
		}

		if ( $this->container->has( 'email_manager' ) ) {
			$email_manager = $this->container->get( 'email_manager' );
			$email_manager->init();
		}

		if ( $this->container->has( 'alert_monitor' ) ) {
			$alert_monitor = $this->container->get( 'alert_monitor' );
			// Alert Monitor schedules its own cron tasks via init hook
			// No explicit init() call needed - schedules itself on 'init' hook
		}

		if ( $this->container->has( 'security_headers' ) ) {
			$security_headers = $this->container->get( 'security_headers' );
			$this->loader->add_action( 'init', $security_headers, 'set_headers' );
			$this->loader->add_action( 'admin_init', $security_headers, 'set_admin_headers' );
		}

		// Pass admin instance to menu manager if available
		if ( $this->container->has( 'menu_manager' ) ) {
			$menu_manager = $this->container->get( 'menu_manager' );
			$menu_manager->set_admin( $plugin_admin );
		}

		if ( $this->container->has( 'campaigns_page' ) ) {
			$this->container->get( 'campaigns_page' ); // This will instantiate it and run constructor
		}

		// Note: AJAX routing handled by wsscd_handle_ajax_request() in main plugin file
		// which uses the unified 'wsscd_ajax' endpoint. Individual action hooks not needed.

		// Initialize WooCommerce integration early if available
		if ( class_exists( 'WooCommerce' ) ) {
			if ( $this->container->has( 'woocommerce_integration' ) ) {
				$wc_integration = $this->container->get( 'woocommerce_integration' );

				if ( $wc_integration ) {
					// Initialize WooCommerce integration coordinator
					// The coordinator's init() method handles all hook registration via sub-integrations
					if ( method_exists( $wc_integration, 'init' ) ) {
						$wc_integration->init();
					}

					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						WSSCD_Log::info( 'WooCommerce integration coordinator initialized successfully' );
					}
				} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						WSSCD_Log::warning( 'WooCommerce integration is null' );
				}
			} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					WSSCD_Log::warning( 'WooCommerce integration not found in container' );
			}
		} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				WSSCD_Log::warning( 'WooCommerce class not found' );
		}

		if ( $this->container->has( 'campaign_repository' ) ) {
			$campaign_repository = $this->container->get( 'campaign_repository' );
			$currency_service    = new WSSCD_Currency_Change_Service( $campaign_repository );
			$currency_service->init();

			$currency_notices = new WSSCD_Currency_Change_Notices();
			$currency_notices->init();

			$currency_review_page = new WSSCD_Currency_Review_Page( $currency_service );
			$currency_review_page->init();
		}

		$expiration_notices = new WSSCD_Campaign_Expiration_Notices();
		$expiration_notices->init();

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			require_once WSSCD_INCLUDES_DIR . 'admin/pages/class-campaign-cron-diagnostic.php';
			$cron_diagnostic = new WSSCD_Campaign_Cron_Diagnostic();
			$cron_diagnostic->init();
		}

		// Admin initialization
		$this->loader->add_action( 'admin_init', $plugin_admin, 'admin_init' );

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );

		// Register WordPress Settings API for validation
		// Using add_action directly since WSSCD_Validation has static methods
		add_action( 'admin_init', array( 'WSSCD_Validation', 'register_wizard_settings' ) );

		// Validation assets are now handled by the admin asset manager

		// NOTE: AJAX hooks are now registered directly by their respective handlers
		// wizard-ajax.php and product-ajax.php handle their own registrations

		// Admin notices
		$this->loader->add_action( 'admin_notices', $plugin_admin, 'display_admin_notices' );

		// ActionScheduler action handlers (scheduled tasks run via ActionScheduler)
		// Wizard session cleanup
		add_action( 'wsscd_cleanup_wizard_sessions', array( 'WSSCD_Session_Service', 'cleanup_expired_sessions' ) );

		// Audit log cleanup
		$this->loader->add_action( 'wsscd_cleanup_audit_logs', $this, 'cleanup_audit_logs' );
	}

	/**
	 * Initialize campaign event hooks.
	 *
	 * CRITICAL: This method registers ALL campaign event hooks on EVERY WordPress load.
	 * This ensures that when WP-Cron fires events like 'wsscd_activate_campaign_97',
	 * there's always a handler listening.
	 *
	 * The issue: add_action() is NOT persistent. Hooks must be registered on every
	 * page load, not just when scheduling events.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function init_campaign_event_hooks(): void {
		// Register ActionScheduler action handlers for campaign events
		add_action( 'wsscd_activate_campaign', array( $this, 'handle_campaign_activation' ), 10, 1 );
		add_action( 'wsscd_deactivate_campaign', array( $this, 'handle_campaign_deactivation' ), 10, 1 );
		add_action( 'wsscd_update_campaign_status', array( $this, 'handle_campaign_status_update' ), 10 );
	}

	/**
	 * Handle campaign activation action from ActionScheduler.
	 *
	 * @since    1.0.0
	 * @param    int $campaign_id    Campaign ID.
	 * @return   void
	 */
	public function handle_campaign_activation( int $campaign_id ) {
		if ( $this->container && $this->container->has( 'campaign_event_scheduler' ) ) {
			$scheduler = $this->container->get( 'campaign_event_scheduler' );
			$scheduler->handle_activation_event( $campaign_id );
		}
	}

	/**
	 * Handle campaign deactivation action from ActionScheduler.
	 *
	 * @since    1.0.0
	 * @param    int $campaign_id    Campaign ID.
	 * @return   void
	 */
	public function handle_campaign_deactivation( int $campaign_id ) {
		if ( $this->container && $this->container->has( 'campaign_event_scheduler' ) ) {
			$scheduler = $this->container->get( 'campaign_event_scheduler' );
			$scheduler->handle_deactivation_event( $campaign_id );
		}
	}

	/**
	 * Handle campaign status safety check action from ActionScheduler.
	 *
	 * This runs periodically (every 15 minutes) to catch any campaigns that should
	 * have activated or deactivated but missed their scheduled event.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function handle_campaign_status_update(): void {
		if ( $this->container && $this->container->has( 'campaign_event_scheduler' ) ) {
			$scheduler = $this->container->get( 'campaign_event_scheduler' );
			$scheduler->run_safety_check();
		}
	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks(): void {
		// Don't register frontend hooks during AJAX requests
		if ( wp_doing_ajax() ) {
			return;
		}

		$plugin_public = new WSSCD_Frontend_Manager( $this->get_plugin_name(), $this->get_version(), $this->container );

		// Frontend initialization
		$this->loader->add_action( 'init', $plugin_public, 'init' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );

		// Shortcodes
		$this->loader->add_action( 'init', $plugin_public, 'register_shortcodes' );

		// Template hooks - shop page badges
		$this->loader->add_action( 'woocommerce_after_shop_loop_item_title', $plugin_public, 'display_shop_discount_badge', 15 );
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run(): void {
		// Run the loader
		$this->loader->run();

		// Hook into WordPress lifecycle events
		$this->setup_lifecycle_hooks();
	}

	/**
	 * Setup WordPress lifecycle hooks.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function setup_lifecycle_hooks(): void {
		// Plugin activation/deactivation
		register_activation_hook( WSSCD_PLUGIN_FILE, array( $this, 'activate' ) );
		register_deactivation_hook( WSSCD_PLUGIN_FILE, array( $this, 'deactivate' ) );

		// WordPress shutdown
		add_action( 'shutdown', array( $this, 'shutdown' ) );

		// Handle plugin updates
		add_action( 'upgrader_process_complete', array( $this, 'handle_plugin_update' ), 10, 2 );
	}

	/**
	 * Handle plugin activation.
	 *
	 * @since    1.0.0
	 */
	public function activate(): void {
		require_once WSSCD_INCLUDES_DIR . 'class-activator.php';
		WSSCD_Activator::activate();
	}

	/**
	 * Handle plugin deactivation.
	 *
	 * @since    1.0.0
	 */
	public function deactivate(): void {
		require_once WSSCD_INCLUDES_DIR . 'class-deactivator.php';
		WSSCD_Deactivator::deactivate();
	}

	/**
	 * Handle WordPress shutdown.
	 *
	 * @since    1.0.0
	 */
	public function shutdown(): void {
		// Flush any remaining logs
		if ( $this->container->has( 'logger' ) ) {
			$logger = $this->container->get( 'logger' );
			if ( method_exists( $logger, 'flush' ) ) {
				$logger->flush();
			}
		}

		// Clean up any temporary data
		$this->cleanup_temporary_data();
	}

	/**
	 * Handle plugin updates.
	 *
	 * @since    1.0.0
	 * @param    WP_Upgrader $upgrader    The upgrader instance.
	 * @param    array       $hook_extra  Extra data for the hook.
	 */
	public function handle_plugin_update( WP_Upgrader $upgrader, array $hook_extra ) {
		if ( isset( $hook_extra['plugin'] ) && $hook_extra['plugin'] === WSSCD_PLUGIN_BASENAME ) {
			// Handle plugin update logic
			$this->run_update_procedures();
		}
	}

	/**
	 * Run update procedures.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function run_update_procedures(): void {
		$current_db_version = get_option( 'wsscd_db_version', '0.0.0' );
		if ( version_compare( $current_db_version, WSSCD_DB_VERSION, '<' ) ) {
			// Run database migrations
			if ( $this->container->has( 'migration_manager' ) ) {
				$migration_manager = $this->container->get( 'migration_manager' );
				if ( method_exists( $migration_manager, 'migrate' ) ) {
					$result = $migration_manager->migrate();
					if ( $result['status'] !== 'success' && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional debug logging when WP_DEBUG is enabled.
						error_log( '[WSSCD] Migration failed during update: ' . wp_json_encode( $result ) );
					}
				}
			}

			update_option( 'wsscd_db_version', WSSCD_DB_VERSION );
		}

		if ( $this->container->has( 'cache_manager' ) ) {
			$cache_manager = $this->container->get( 'cache_manager' );
			if ( method_exists( $cache_manager, 'flush_all' ) ) {
				$cache_manager->flush_all();
			}
		}
	}

	/**
	 * Clean up temporary data.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function cleanup_temporary_data(): void {
		// Clean up expired transients
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Bulk expired transient cleanup; no WP abstraction for pattern-based delete.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d",
				'_transient_timeout_' . WSSCD_TRANSIENT_PREFIX . '%',
				time()
			)
		);
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name(): string {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    WSSCD_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader(): WSSCD_Loader {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version(): string {
		return $this->version;
	}

	/**
	 * Get the dependency injection container.
	 *
	 * @since     1.0.0
	 * @return    object    The DI container instance.
	 */
	public function get_container(): object {
		return $this->container;
	}

	/**
	 * Get the container statically.
	 *
	 * @since    1.0.0
	 * @return   object|null    The container instance or null if not available.
	 */
	public static function get_container_static(): ?object {
		return self::$static_container;
	}

	/**
	 * Get a service from the container statically.
	 *
	 * @since    1.0.0
	 * @param    string $service_id    The service identifier.
	 * @return   mixed                    The service instance or null if not found.
	 */
	public static function get_service( string $service_id ) {
		if ( self::$static_container && self::$static_container->has( $service_id ) ) {
			return self::$static_container->get( $service_id );
		}

		// Fallback to instance container if static not available
		$instance = self::get_instance();
		if ( $instance && $instance->container && $instance->container->has( $service_id ) ) {
			return $instance->container->get( $service_id );
		}

		return null;
	}

	/**
	 * Setup performance optimizations.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function setup_performance_optimizations(): void {
		require_once WSSCD_INCLUDES_DIR . 'utilities/class-performance-bootstrapper.php';

		if ( class_exists( 'WSSCD_Performance_Bootstrapper' ) ) {
			try {
				$performance_bootstrapper = new WSSCD_Performance_Bootstrapper( $this->loader, $this->container );
				$performance_bootstrapper->init();

				if ( ! $this->container->has( 'performance_bootstrapper' ) ) {
					$this->container->instance( 'performance_bootstrapper', $performance_bootstrapper );
				}
			} catch ( Exception $e ) {
				WSSCD_Log::exception( $e, 'Performance optimization init failed' );
			}
		}
	}

	/**
	 * Cleanup audit logs.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function cleanup_audit_logs(): void {
		try {
			// Use wizard context logger for audit log cleanup
			$wizard_logger = WSSCD_Logger::with_context( 'wizard' );

			global $wpdb;
			$table_name = $wpdb->prefix . 'wsscd_audit_logs';

			$check_table_sql = $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls, PluginCheck.Security.DirectDB.UnescapedDBParameter -- SHOW TABLES has no WP abstraction; query prepared above.
			if ( $wpdb->get_var( $check_table_sql ) === $table_name ) {
				// Clean up old logs (older than 30 days).
				// SECURITY: Use %i placeholder for table identifier (WordPress 6.2+).
				$delete_sql = $wpdb->prepare(
					'DELETE FROM %i WHERE timestamp < DATE_SUB(NOW(), INTERVAL %d DAY)',
					$table_name,
					30
				);
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Scheduled log cleanup; query prepared above.
				$deleted = $wpdb->query( $delete_sql );

				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					$wizard_logger->info( 'Cleaned up old audit log entries', array( 'count' => $deleted ) );
				}
			}
		} catch ( Exception $e ) {
			WSSCD_Log::exception( $e, 'Error cleaning up audit logs' );
		}
	}

	/**
	 * Log error to logger service.
	 *
	 * @since    1.0.0
	 * @param    string $code       Error code.
	 * @param    string $message    Error message.
	 * @param    array  $data       Error data.
	 * @return   void
	 */
	public function log_error_to_logger( string $code, string $message, array $data ) {
		if ( $this->container && $this->container->has( 'logger' ) ) {
			$logger = $this->container->get( 'logger' );
			$logger->error( sprintf( '[%s] %s', $code, $message ), $data );
		}
	}

	/**
	 * Log exception to logger service.
	 *
	 * @since    1.0.0
	 * @param    Throwable $exception    Exception to log.
	 * @param    string    $context      Error context.
	 * @return   void
	 */
	public function log_exception_to_logger( Throwable $exception, string $context ) {
		if ( $this->container && $this->container->has( 'logger' ) ) {
			$logger = $this->container->get( 'logger' );
			$logger->critical(
				sprintf( 'Exception in %s: %s', $context, $exception->getMessage() ),
				array(
					'file'  => $exception->getFile(),
					'line'  => $exception->getLine(),
					'trace' => $exception->getTraceAsString(),
				)
			);
		}
	}

	/**
	 * Handle dynamic campaign event hooks.
	 *
	 * Intercepts scheduled campaign activation/deactivation events and routes them
	 * to the event scheduler for processing.
	 *
	 * @since    1.0.0
	 * @param    string $tag    Hook name.
	 * @return   void
	 */
	public function handle_dynamic_campaign_events( string $tag ) {
		if ( preg_match( '/^wsscd_activate_campaign_(\d+)$/', $tag, $matches ) ) {
			$campaign_id = absint( $matches[1] );
			if ( $campaign_id > 0 && $this->container && $this->container->has( 'campaign_event_scheduler' ) ) {
				$scheduler = $this->container->get( 'campaign_event_scheduler' );
				$scheduler->handle_activation_event( $campaign_id );
			}
			return;
		}

		if ( preg_match( '/^wsscd_deactivate_campaign_(\d+)$/', $tag, $matches ) ) {
			$campaign_id = absint( $matches[1] );
			if ( $campaign_id > 0 && $this->container && $this->container->has( 'campaign_event_scheduler' ) ) {
				$scheduler = $this->container->get( 'campaign_event_scheduler' );
				$scheduler->handle_deactivation_event( $campaign_id );
			}
			return;
		}
	}

	/**
	 * Handle plugin update completion.
	 *
	 * Clears license and feature caches when the plugin is updated
	 * to ensure fresh validation with new code/configuration.
	 *
	 * @since    1.0.0
	 * @param    object $upgrader    WP_Upgrader instance.
	 * @param    array  $options     Update options.
	 * @return   void
	 */
	public function on_plugin_update( $upgrader, $options ) {
		// Only process plugin updates
		if ( ! isset( $options['type'] ) || 'plugin' !== $options['type'] ) {
			return;
		}

		// Only process this plugin's updates
		if ( ! isset( $options['plugins'] ) || ! is_array( $options['plugins'] ) ) {
			return;
		}

		// Check if our plugin was updated
		foreach ( $options['plugins'] as $plugin ) {
			if ( WSSCD_PLUGIN_BASENAME === $plugin ) {
				// Clear license and feature caches
				delete_option( 'wsscd_license_validation_cache' );
				delete_option( 'wsscd_license_last_check' );
				delete_transient( 'wsscd_license_status' );
				delete_transient( 'wsscd_feature_gate_cache' );

				// Force fresh validation
				if ( class_exists( 'WSSCD_License_Manager' ) ) {
					$license_manager = WSSCD_License_Manager::instance();
					if ( method_exists( $license_manager, 'force_validation' ) ) {
						$license_manager->force_validation();
					}
				}

				// Log the cache clear
				if ( function_exists( 'wsscd_log_info' ) ) {
					wsscd_log_info(
						'License caches cleared after plugin update',
						array(
							'version' => WSSCD_VERSION,
						)
					);
				}

				break;
			}
		}
	}
}
