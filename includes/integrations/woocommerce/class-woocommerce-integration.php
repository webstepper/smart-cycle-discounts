<?php
/**
 * Woocommerce Integration Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/integrations/woocommerce/class-woocommerce-integration.php
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
 * WooCommerce Integration Coordinator class.
 *
 * Coordinates WooCommerce sub-integrations using the coordinator pattern.
 * Delegates all functionality to specialized classes:
 * - SCD_WC_Discount_Query_Service: Discount lookup logic
 * - SCD_WC_Price_Integration: Price modifications
 * - SCD_WC_Display_Integration: Badges and display
 * - SCD_WC_Cart_Message_Service: Cart messages
 * - SCD_WC_Admin_Integration: Admin fields
 * - SCD_WC_Order_Integration: Order tracking
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/integrations/woocommerce
 */
class SCD_WooCommerce_Integration {

	/**
	 * Container instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      object    $container    Container instance.
	 */
	private object $container;

	/**
	 * Logger instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      object|null    $logger    Logger instance.
	 */
	private ?object $logger = null;

	/**
	 * Discount engine instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Discount_Engine|null    $discount_engine    Discount engine.
	 */
	private ?SCD_Discount_Engine $discount_engine = null;

	/**
	 * Campaign manager instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Campaign_Manager|null    $campaign_manager    Campaign manager.
	 */
	private ?SCD_Campaign_Manager $campaign_manager = null;

	/**
	 * Customer usage manager instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Customer_Usage_Manager|null    $customer_usage_manager    Customer usage manager.
	 */
	private ?SCD_Customer_Usage_Manager $customer_usage_manager = null;

	/**
	 * Discount query service instance.
	 *
	 * Handles all discount lookup and calculation logic.
	 * Shared across all sub-integrations.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_WC_Discount_Query_Service|null    $discount_query    Discount query service.
	 */
	private ?SCD_WC_Discount_Query_Service $discount_query = null;

	/**
	 * Price integration instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_WC_Price_Integration|null    $price_integration    Price integration.
	 */
	private ?SCD_WC_Price_Integration $price_integration = null;

	/**
	 * Display integration instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_WC_Display_Integration|null    $display_integration    Display integration.
	 */
	private ?SCD_WC_Display_Integration $display_integration = null;

	/**
	 * Cart message service instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_WC_Cart_Message_Service|null    $cart_message_service    Cart message service.
	 */
	private ?SCD_WC_Cart_Message_Service $cart_message_service = null;

	/**
	 * Admin integration instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_WC_Admin_Integration|null    $admin_integration    Admin integration.
	 */
	private ?SCD_WC_Admin_Integration $admin_integration = null;

	/**
	 * Order integration instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_WC_Order_Integration|null    $order_integration    Order integration.
	 */
	private ?SCD_WC_Order_Integration $order_integration = null;

	/**
	 * WooCommerce compatibility status.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      bool    $is_compatible    Compatibility status.
	 */
	private bool $is_compatible = false;

	/**
	 * HPOS compatibility status.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      bool    $hpos_enabled    HPOS status.
	 */
	private bool $hpos_enabled = false;

	/**
	 * Initialize the WooCommerce integration coordinator.
	 *
	 * @since    1.0.0
	 * @param    object $container    Container instance.
	 */
	public function __construct( object $container ) {
		$this->container = $container;
		$this->logger    = $container->has( 'logger' ) ? $container->get( 'logger' ) : null;
	}

	/**
	 * Initialize the integration.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function init(): void {
		// Check WooCommerce compatibility first
		if ( ! $this->check_compatibility() ) {
			$this->log( 'error', 'WooCommerce compatibility check failed' );
			return;
		}

		// Initialize components
		$this->init_components();

		// Setup hooks immediately
		$this->setup_hooks();

		// Check HPOS compatibility
		$this->check_hpos_compatibility();

		$this->log( 'info', 'WooCommerce integration coordinator initialized successfully' );
	}

	/**
	 * Check WooCommerce compatibility.
	 *
	 * @since    1.0.0
	 * @return   bool    True if compatible, false otherwise.
	 */
	public function check_compatibility(): bool {
		if ( ! class_exists( 'WooCommerce' ) ) {
			$this->log( 'error', 'WooCommerce is not active' );
			return false;
		}

		// Version check handled by Requirements Checker (WC 8.0+ required)
		$this->is_compatible = true;
		return true;
	}

	/**
	 * Initialize components with dependency injection.
	 *
	 * Follows fail-fast pattern: Throws RuntimeException if required dependencies
	 * are missing from the service container.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @throws   RuntimeException If required dependencies are missing.
	 * @return   void
	 */
	private function init_components(): void {
		try {
			// Require discount engine from container (fail fast if missing)
			if ( ! $this->container->has( 'discount_engine' ) ) {
				throw new RuntimeException(
					'SCD_Discount_Engine not registered in service container. Check includes/bootstrap/class-service-definitions.php'
				);
			}
			$this->discount_engine = $this->container->get( 'discount_engine' );

			// Require campaign manager from container (fail fast if missing)
			if ( ! $this->container->has( 'campaign_manager' ) ) {
				throw new RuntimeException(
					'SCD_Campaign_Manager not registered in service container. Check includes/bootstrap/class-service-definitions.php'
				);
			}
			$this->campaign_manager = $this->container->get( 'campaign_manager' );

			// Customer usage manager is optional but should be registered
			if ( $this->container->has( 'customer_usage_manager' ) ) {
				$this->customer_usage_manager = $this->container->get( 'customer_usage_manager' );
			} else {
				$this->log( 'warning', 'Customer usage manager not available in container' );
			}

			// Initialize discount query service (shared by all sub-integrations)
			$this->discount_query = new SCD_WC_Discount_Query_Service(
				$this->campaign_manager,
				$this->discount_engine,
				$this->logger
			);

			// Initialize sub-integrations with dependency injection
			$this->price_integration = new SCD_WC_Price_Integration(
				$this->discount_query,
				$this->customer_usage_manager,
				$this->logger
			);

			$this->display_integration = new SCD_WC_Display_Integration(
				$this->discount_query,
				$this->logger
			);

			$this->cart_message_service = new SCD_WC_Cart_Message_Service(
				$this->discount_query,
				$this->campaign_manager,
				$this->logger
			);

			$this->admin_integration = new SCD_WC_Admin_Integration(
				$this->logger
			);

			$this->order_integration = new SCD_WC_Order_Integration(
				$this->discount_query,
				$this->customer_usage_manager,
				$this->logger
			);

			$this->log( 'debug', 'WooCommerce integration components initialized successfully' );

		} catch ( RuntimeException $e ) {
			// Re-throw runtime exceptions (DI container issues)
			$this->log(
				'critical',
				'Failed to initialize WooCommerce integration - missing dependencies',
				array(
					'error' => $e->getMessage(),
				)
			);
			throw $e;

		} catch ( Exception $e ) {
			// Log other exceptions but don't halt execution
			$this->log(
				'error',
				'Error initializing WooCommerce integration components',
				array(
					'error' => $e->getMessage(),
				)
			);
		}
	}

	/**
	 * Setup WordPress and WooCommerce hooks.
	 *
	 * Coordinator pattern: Delegates hook registration to specialized sub-integrations.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function setup_hooks(): void {
		// Register price integration hooks
		if ( $this->price_integration ) {
			$this->price_integration->register_hooks();
		}

		// Register display hooks (skip during AJAX)
		if ( ! wp_doing_ajax() && $this->display_integration ) {
			$this->display_integration->register_hooks();
		}

		// Register cart message hooks (skip during AJAX)
		if ( ! wp_doing_ajax() && $this->cart_message_service ) {
			$this->cart_message_service->register_hooks();
		}

		// Register admin hooks
		if ( is_admin() && $this->admin_integration ) {
			$this->admin_integration->register_hooks();
		}

		// Register order tracking hooks
		if ( $this->order_integration ) {
			$this->order_integration->register_hooks();
		}

		// WooCommerce compatibility hooks
		add_action( 'woocommerce_init', array( $this, 'on_woocommerce_init' ) );
		add_action( 'before_woocommerce_init', array( $this, 'declare_compatibility' ) );

		$this->log( 'debug', 'WooCommerce hooks delegated to sub-integrations successfully' );
	}

	/**
	 * Check HPOS compatibility.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function check_hpos_compatibility(): void {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) ) {
			$this->hpos_enabled = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();

			if ( $this->hpos_enabled ) {
				$this->log( 'info', 'HPOS (High-Performance Order Storage) is enabled' );
				$this->setup_hpos_hooks();
			}
		}
	}

	/**
	 * Setup HPOS-specific hooks.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function setup_hpos_hooks(): void {
		// Add HPOS-specific hooks here if needed in the future
		$this->log( 'debug', 'HPOS hooks setup completed' );
	}

	/**
	 * Handle WooCommerce initialization.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function on_woocommerce_init(): void {
		$this->log( 'debug', 'WooCommerce initialized' );
	}

	/**
	 * Declare WooCommerce feature compatibility.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function declare_compatibility(): void {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				SCD_PLUGIN_FILE,
				true
			);

			$this->log( 'debug', 'WooCommerce HPOS compatibility declared' );
		}
	}

	/**
	 * Get integration status.
	 *
	 * @since    1.0.0
	 * @return   array    Status information.
	 */
	public function get_status(): array {
		return array(
			'compatible'             => $this->is_compatible,
			'hpos_enabled'           => $this->hpos_enabled,
			'components_initialized' => null !== $this->discount_query,
		);
	}

	/**
	 * Check if integration is ready.
	 *
	 * @since    1.0.0
	 * @return   bool    True if ready, false otherwise.
	 */
	public function is_ready(): bool {
		return $this->is_compatible && null !== $this->discount_query;
	}

	/**
	 * Log message.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $level      Level.
	 * @param    string $message    Message.
	 * @param    array  $context    Context.
	 * @return   void
	 */
	private function log( string $level, string $message, array $context = array() ): void {
		if ( $this->logger && method_exists( $this->logger, $level ) ) {
			$this->logger->$level( '[WC_Integration] ' . $message, $context );
		}
	}
}
