<?php
/**
 * Woocommerce Integration Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/integrations/woocommerce/class-woocommerce-integration.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
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
class SCD_WooCommerce_Integration implements SCD_Ecommerce_Integration {

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
	 * Discount map service instance.
	 *
	 * Provides efficient bulk product-to-campaign mapping.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_WC_Discount_Map_Service|null    $discount_map_service    Discount map service.
	 */
	private ?SCD_WC_Discount_Map_Service $discount_map_service = null;

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
	 * Coupon restriction instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_WC_Coupon_Restriction|null    $coupon_restriction    Coupon restriction.
	 */
	private ?SCD_WC_Coupon_Restriction $coupon_restriction = null;

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

			// Get discount rules enforcer from container
			if ( ! $this->container->has( 'discount_rules_enforcer' ) ) {
				throw new RuntimeException(
					'SCD_Discount_Rules_Enforcer not registered in service container. Check includes/bootstrap/class-service-definitions.php'
				);
			}
			$rules_enforcer = $this->container->get( 'discount_rules_enforcer' );

			// Get cache manager if available
			$cache_manager = $this->container->has( 'cache_manager' ) ? $this->container->get( 'cache_manager' ) : null;

			// Create discount map service for efficient bulk product lookups
			$this->discount_map_service = new SCD_WC_Discount_Map_Service(
				$this->campaign_manager,
				$cache_manager,
				$this->logger
			);

			$this->discount_query = new SCD_WC_Discount_Query_Service(
				$this->campaign_manager,
				$this->discount_engine,
				$this->logger,
				$rules_enforcer,
				$this->discount_map_service
			);

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

			$this->coupon_restriction = new SCD_WC_Coupon_Restriction(
				$this->campaign_manager,
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
		// Initialize discount map very early, before any product price queries
		// wp action fires after plugins loaded but before template/query setup
		if ( ! is_admin() && ! wp_doing_ajax() && $this->discount_map_service ) {
			add_action( 'wp', array( $this, 'init_discount_map' ), 1 );
		}

		if ( $this->price_integration ) {
			$this->price_integration->register_hooks();
		}

		if ( ! wp_doing_ajax() && $this->display_integration ) {
			$this->display_integration->register_hooks();
		}

		if ( ! wp_doing_ajax() && $this->cart_message_service ) {
			$this->cart_message_service->register_hooks();
		}

		if ( is_admin() && $this->admin_integration ) {
			$this->admin_integration->register_hooks();
		}

		if ( $this->order_integration ) {
			$this->order_integration->register_hooks();
		}

		if ( $this->coupon_restriction ) {
			$this->coupon_restriction->register_hooks();
		}

		// WooCommerce compatibility hooks
		add_action( 'woocommerce_init', array( $this, 'on_woocommerce_init' ) );
		add_action( 'before_woocommerce_init', array( $this, 'declare_compatibility' ) );

		$this->log( 'debug', 'WooCommerce hooks delegated to sub-integrations successfully' );
	}

	/**
	 * Initialize discount map for efficient bulk product lookups.
	 *
	 * Called on template_redirect hook to prepare discount mappings
	 * before products are loaded on shop/archive pages.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function init_discount_map(): void {
		if ( $this->discount_map_service ) {
			$this->discount_map_service->init_map();
			$this->log( 'debug', 'Discount map initialized for page request' );
		}
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

	// ============================================================================
	// SCD_Ecommerce_Integration Interface Implementation
	// ============================================================================

	/**
	 * Get platform name.
	 *
	 * @since    1.0.0
	 * @return   string    Platform name.
	 */
	public function get_platform_name() {
		return 'woocommerce';
	}

	/**
	 * Check if platform is active.
	 *
	 * @since    1.0.0
	 * @return   bool    True if platform is active.
	 */
	public function is_active() {
		return $this->is_compatible;
	}

	/**
	 * Get order by ID.
	 *
	 * @since    1.0.0
	 * @param    int $order_id    Order ID.
	 * @return   object|null      Order object or null if not found.
	 */
	public function get_order( $order_id ) {
		return wc_get_order( $order_id );
	}

	/**
	 * Get order items.
	 *
	 * @since    1.0.0
	 * @param    object $order    Order object.
	 * @return   array            Array of order items.
	 */
	public function get_order_items( $order ) {
		return $order->get_items();
	}

	/**
	 * Get item product ID.
	 *
	 * @since    1.0.0
	 * @param    mixed $item    Order item.
	 * @return   int            Product ID.
	 */
	public function get_item_product_id( $item ) {
		return $item->get_product_id();
	}

	/**
	 * Get item total.
	 *
	 * @since    1.0.0
	 * @param    mixed $item    Order item.
	 * @return   float          Item total.
	 */
	public function get_item_total( $item ) {
		return (float) $item->get_total();
	}

	/**
	 * Get item subtotal (before discount).
	 *
	 * @since    1.0.0
	 * @param    mixed $item    Order item.
	 * @return   float          Item subtotal.
	 */
	public function get_item_subtotal( $item ) {
		return (float) $item->get_subtotal();
	}

	/**
	 * Get item discount amount.
	 *
	 * @since    1.0.0
	 * @param    mixed $item    Order item.
	 * @return   float          Discount amount.
	 */
	public function get_item_discount( $item ) {
		return (float) ( $item->get_subtotal() - $item->get_total() );
	}

	/**
	 * Get item quantity.
	 *
	 * @since    1.0.0
	 * @param    mixed $item    Order item.
	 * @return   int            Quantity.
	 */
	public function get_item_quantity( $item ) {
		return (int) $item->get_quantity();
	}

	/**
	 * Get order customer ID.
	 *
	 * @since    1.0.0
	 * @param    object $order    Order object.
	 * @return   int              Customer/user ID.
	 */
	public function get_order_customer_id( $order ) {
		return (int) $order->get_customer_id();
	}

	/**
	 * Get order total.
	 *
	 * @since    1.0.0
	 * @param    object $order    Order object.
	 * @return   float            Order total.
	 */
	public function get_order_total( $order ) {
		return (float) $order->get_total();
	}

	/**
	 * Get campaign ID from order item.
	 *
	 * @since    1.0.0
	 * @param    mixed $item    Order item.
	 * @return   int|null       Campaign ID or null.
	 */
	public function get_item_campaign_id( $item ) {
		$campaign_id = $item->get_meta( '_scd_campaign_id', true );
		return $campaign_id ? (int) $campaign_id : null;
	}

	/**
	 * Set campaign ID on order item.
	 *
	 * @since    1.0.0
	 * @param    mixed $item          Order item.
	 * @param    int   $campaign_id   Campaign ID.
	 * @return   void
	 */
	public function set_item_campaign_id( $item, $campaign_id ) {
		$item->add_meta_data( '_scd_campaign_id', $campaign_id, true );
		$item->save_meta_data();
	}

	/**
	 * Register order completion hook.
	 *
	 * @since    1.0.0
	 * @param    callable $callback    Callback function.
	 * @return   void
	 */
	public function register_order_complete_hook( $callback ) {
		add_action( 'woocommerce_order_status_completed', $callback, 10, 1 );
	}

	/**
	 * Get product price.
	 *
	 * @since    1.0.0
	 * @param    int $product_id    Product ID.
	 * @return   float              Product price.
	 */
	public function get_product_price( $product_id ) {
		$product = wc_get_product( $product_id );
		return $product ? (float) $product->get_price() : 0.0;
	}

	/**
	 * Check if product exists.
	 *
	 * @since    1.0.0
	 * @param    int $product_id    Product ID.
	 * @return   bool               True if product exists.
	 */
	public function product_exists( $product_id ) {
		$product = wc_get_product( $product_id );
		return $product && is_a( $product, 'WC_Product' );
	}

	/**
	 * Get discount info for a product.
	 * Delegates to the discount query service.
	 *
	 * @since    1.0.0
	 * @param    int   $product_id    Product ID.
	 * @param    array $context       Context information (e.g., quantity, product).
	 * @return   array|null           Discount info or null if no discount.
	 */
	public function get_discount_info( $product_id, $context = array() ) {
		if ( ! $this->discount_query ) {
			return null;
		}

		return $this->discount_query->get_discount_info( $product_id, $context );
	}

	/**
	 * Get campaign badge info for a product.
	 * Delegates to the discount query service.
	 *
	 * @since    1.0.0
	 * @param    int $product_id    Product ID.
	 * @return   array|null         Badge info or null if no campaigns.
	 */
	public function get_campaign_badge_info( $product_id ) {
		if ( ! $this->discount_query ) {
			return null;
		}

		return $this->discount_query->get_campaign_badge_info( $product_id );
	}
}
