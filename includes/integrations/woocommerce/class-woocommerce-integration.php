<?php
/**
 * WooCommerce Integration
 *
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/integrations/woocommerce
 */

declare(strict_types=1);


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


// Load standardized response handler
require_once SCD_PLUGIN_DIR . 'includes/admin/ajax/class-scd-ajax-response.php';

/**
 * WooCommerce Integration
 *
 * Main WooCommerce integration coordinator.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/integrations/woocommerce
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
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
     * Initialize the WooCommerce integration.
     *
     * @since    1.0.0
     * @param    object    $container    Container instance.
     */
    public function __construct(object $container) {
        $this->container = $container;
        $this->logger = $container->has('logger') ? $container->get('logger') : null;
    }

    /**
     * Initialize the integration.
     *
     * @since    1.0.0
     * @return   void
     */
    public function init(): void {
        // Check WooCommerce compatibility first
        if (!$this->check_compatibility()) {
            $this->log('error', 'WooCommerce compatibility check failed');
            return;
        }

        // Initialize components
        $this->init_components();

        // Setup hooks immediately - don't wait for any other actions
        $this->setup_hooks();

        // Check HPOS compatibility
        $this->check_hpos_compatibility();
    }

    /**
     * Helper method for logging.
     *
     * @since    1.0.0
     * @param    string    $level      Log level.
     * @param    string    $message    Log message.
     * @param    array     $context    Context data.
     * @return   void
     */
    private function log(string $level, string $message, array $context = array()): void {
        if ($this->logger && method_exists($this->logger, $level)) {
            $this->logger->$level($message, $context);
        }
    }

    /**
     * Check WooCommerce compatibility.
     *
     * @since    1.0.0
     * @return   bool    True if compatible.
     */
    public function check_compatibility(): bool {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            $this->log('error', 'WooCommerce is not active');
            return false;
        }

        // Check WooCommerce version
        if (!defined('WC_VERSION')) {
            $this->log('error', 'WooCommerce version not defined');
            return false;
        }

        $required_version = SCD_MIN_WC_VERSION;
        if (version_compare(WC_VERSION, $required_version, '<')) {
            $this->log('error', 'WooCommerce version too old', array(
                'current' => WC_VERSION, 'required' => $required_version
            ));
            return false;
        }

        // Check for required WooCommerce features
        if (!function_exists('wc_get_product')) {
            $this->log('error', 'Required WooCommerce functions not available');
            return false;
        }

        $this->is_compatible = true;
        return true;
    }

    /**
     * Initialize integration components.
     *
     * @since    1.0.0
     * @access   private
     * @return   void
     */
    private function init_components(): void {
        try {
            // Get discount engine from container
            if ($this->container->has('discount_engine')) {
                $this->discount_engine = $this->container->get('discount_engine');
            } else {
                // Create a minimal discount engine if not available
                if (class_exists('SCD_Discount_Engine')) {
                    $this->discount_engine = new SCD_Discount_Engine(
                        $this->logger,
                        $this->container->has('cache_manager') ? $this->container->get('cache_manager') : null
                    );
                }
            }

            // Get campaign manager from container
            if ($this->container->has('campaign_manager')) {
                $this->campaign_manager = $this->container->get('campaign_manager');
            } else {
                // Create a minimal campaign manager if not available
                if (class_exists('SCD_Campaign_Manager')) {
                    $campaign_repository = $this->container->has('campaign_repository') ?
                        $this->container->get('campaign_repository') : null;
                    if ($campaign_repository) {
                        $this->campaign_manager = new SCD_Campaign_Manager(
                            $campaign_repository,
                            $this->logger,
                            $this->container->has('cache_manager') ? $this->container->get('cache_manager') : null,
                            $this->container
                        );
                    }
                }
            }

            // Get customer usage manager from container
            if ($this->container->has('customer_usage_manager')) {
                $this->customer_usage_manager = $this->container->get('customer_usage_manager');
            }

            $this->log('debug', 'WooCommerce integration components initialized');

        } catch (Exception $e) {
            $this->log('error', 'Failed to initialize WooCommerce integration components', array(
                'error' => $e->getMessage()
            ));
        }
    }

    /**
     * Setup WordPress and WooCommerce hooks.
     *
     * @since    1.0.0
     * @access   private
     * @return   void
     */
    private function setup_hooks(): void {
        // NOTE: Primary product price hooks are registered through the loader in the main plugin class
        // to ensure proper timing. Only register additional hooks here.

        // Don't register display hooks during AJAX requests
        if ( ! wp_doing_ajax() ) {
            // Product display hooks
            add_action( 'woocommerce_single_product_summary', array( $this, 'display_discount_badge' ), 15 );
            add_action( 'woocommerce_single_product_summary', array( $this, 'display_product_discount_details' ), 25 );
            add_action( 'woocommerce_after_shop_loop_item_title', array( $this, 'display_shop_discount_badge' ), 15 );

            // Cart display hooks
            add_action( 'woocommerce_before_cart', array( $this, 'display_cart_discount_messages' ), 10 );
            // Note: cart item price/subtotal hooks registered in main plugin class

            // Hide WooCommerce core sale badge when our discount is active
            add_filter( 'woocommerce_sale_flash', array( $this, 'maybe_hide_sale_badge' ), 10, 3 );

            // Add CSS classes for theme compatibility (allows users to add custom CSS if needed)
            add_filter( 'body_class', array( $this, 'add_discount_body_class' ) );
            add_filter( 'post_class', array( $this, 'add_discount_post_class' ), 10, 3 );
        }

        // Admin hooks
        add_action( 'woocommerce_product_options_pricing', array( $this, 'add_product_discount_fields' ) );
        add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_discount_fields' ) );

        // Order hooks
        add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'add_order_item_meta' ), 10, 4 );
        add_action( 'woocommerce_payment_complete', array( $this, 'track_customer_usage' ), 10, 1 );
        add_action( 'woocommerce_order_status_completed', array( $this, 'track_customer_usage' ), 10, 1 );

        // AJAX hooks - now handled by unified router

        // Compatibility hooks
        add_action( 'woocommerce_init', array( $this, 'on_woocommerce_init' ) );
        add_action( 'before_woocommerce_init', array( $this, 'declare_compatibility' ) );

        $this->log( 'debug', 'WooCommerce hooks setup completed' );
    }

    /**
     * Check HPOS compatibility.
     *
     * @since    1.0.0
     * @access   private
     * @return   void
     */
    private function check_hpos_compatibility(): void {
        if (class_exists('\Automattic\WooCommerce\Utilities\OrderUtil')) {
            $this->hpos_enabled = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
            
            if ($this->hpos_enabled) {
                $this->log('info', 'HPOS (High-Performance Order Storage) is enabled');
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
        // Add HPOS-specific hooks here if needed
        $this->log('debug', 'HPOS hooks setup completed');
    }

    /**
     * Modify product price based on active discounts.
     *
     * @since    1.0.0
     * @param    string      $price      Original price.
     * @param    WC_Product  $product    Product object.
     * @return   string                  Modified price.
     */
    public function modify_product_price($price, WC_Product $product): string {
        $product_id = $product->get_id();

        // Skip in admin and during cart/checkout operations
        if ( is_admin() && ! wp_doing_ajax() ) {
            return strval( $price );
        }

        // CRITICAL: Never modify prices during cart operations
        // Cart has dedicated hook (woocommerce_before_calculate_totals) that handles all pricing
        $in_cart_calc = did_action( 'woocommerce_before_calculate_totals' ) && ! did_action( 'woocommerce_after_calculate_totals' );
        if ( $in_cart_calc ) {
            return strval( $price ); // Cart calculation in progress, don't interfere
        }

        // Skip on cart/checkout pages entirely
        if ( is_cart() || is_checkout() ) {
            return strval( $price );
        }

        if ( ! $this->discount_engine || ! $this->campaign_manager || ! $price ) {
            return strval( $price );
        }

        try {
            $regular_price = floatval( $product->get_regular_price() );

            // Only for product/shop pages: show tier 1 price
            if ( $regular_price > 0 && $this->has_active_discount( $product_id ) ) {
                $discount_info = $this->get_discount_info( $product_id, array( 'quantity' => 1 ) );

                if ( $discount_info ) {
                    return strval( $discount_info['discounted_price'] );
                }
            }

        } catch ( Exception $e ) {
            $this->log( 'error', 'Failed to modify product price', array(
                'product_id' => $product->get_id(),
                'error' => $e->getMessage()
            ) );
        }

        return strval( $price );
    }

    /**
     * Modify sale price based on active discounts.
     *
     * @since    1.0.0
     * @param    string      $sale_price    Original sale price.
     * @param    WC_Product  $product       Product object.
     * @return   string                     Modified sale price.
     */
    public function modify_sale_price($sale_price, WC_Product $product): string {
        // CRITICAL: Never modify prices during cart operations
        if ( did_action( 'woocommerce_before_calculate_totals' ) && ! did_action( 'woocommerce_after_calculate_totals' ) ) {
            return strval( $sale_price ); // Cart calculation in progress
        }

        // Skip on cart/checkout pages
        if ( is_cart() || is_checkout() ) {
            return strval( $sale_price );
        }

        if ( ! $this->discount_engine || ! $this->campaign_manager ) {
            return strval( $sale_price );
        }

        try {
            $product_id = $product->get_id();
            $regular_price = floatval( $product->get_regular_price() );

            if ( $regular_price > 0 && $this->has_active_discount( $product_id ) ) {
                // For product pages, show tier 1 price
                $discount_info = $this->get_discount_info( $product_id, array( 'quantity' => 1 ) );

                if ( $discount_info ) {
                    return strval( $discount_info['discounted_price'] );
                }
            }

        } catch ( Exception $e ) {
            $this->log( 'error', 'Failed to modify sale price', array(
                'product_id' => $product->get_id(),
                'error' => $e->getMessage()
            ));
        }

        return strval( $sale_price );
    }

    /**
     * Modify price HTML display.
     *
     * @since    1.0.0
     * @param    string      $html      Original price HTML.
     * @param    WC_Product  $product   Product object.
     * @return   string                 Modified price HTML.
     */
    public function modify_price_html(string $html, WC_Product $product): string {
        if ( ! $this->discount_engine || ! $this->campaign_manager ) {
            return $html;
        }

        try {
            $product_id = $product->get_id();

            // Check if product has active discount
            if ( $this->has_active_discount( $product_id ) ) {
                $discount_info = $this->get_discount_info( $product_id );

                if ( $discount_info ) {
                    $regular_price = floatval( $product->get_regular_price() );
                    $discounted_price = floatval( $discount_info['discounted_price'] );

                    // Format the price with strikethrough for original price
                    if ( $regular_price > $discounted_price ) {
                        $html = wc_format_sale_price( $regular_price, $discounted_price );
                    }
                }
            }

        } catch ( Exception $e ) {
            $this->log( 'error', 'Failed to modify price HTML', array(
                'product_id' => $product->get_id(),
                'error' => $e->getMessage()
            ) );
        }

        return $html;
    }

    /**
     * Modify cart item prices before calculation.
     *
     * @since    1.0.0
     * @param    WC_Cart    $cart    Cart object.
     * @return   void
     */
    public function modify_cart_item_prices(WC_Cart $cart): void {
        // Check if cart exists and is valid
        if ( ! $cart || ! is_object( $cart ) || ! WC()->cart || ! is_object( WC()->cart ) ) {
            return;
        }

        if (!$this->discount_engine || is_admin() && !defined('DOING_AJAX')) {
            return;
        }

        // Use performance optimizer to prevent recursion and batch load
        if (class_exists('SCD_Performance_Optimizer')) {
            $optimized_callback = SCD_Performance_Optimizer::optimize_cart_calculation(
                [$this, 'modify_cart_item_prices_internal']
            );
            call_user_func($optimized_callback, $cart);
        } else {
            // Fallback to direct implementation
            $this->modify_cart_item_prices_internal($cart);
        }
    }

    /**
     * Internal cart price modification (called by optimizer).
     *
     * @since    1.0.0
     * @access   public
     * @param    WC_Cart    $cart    WooCommerce cart object.
     * @return   void
     */
    public function modify_cart_item_prices_internal(WC_Cart $cart): void {
        // Validate cart
        if ( ! $cart || ! is_callable( array( $cart, 'get_cart' ) ) ) {
            return;
        }

        // Process each cart item
        foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
            try {
                // Get product and quantity
                $product = $cart_item['data'];
                $product_id = $product->get_id();
                $quantity = isset( $cart_item['quantity'] ) ? absint( $cart_item['quantity'] ) : 1;

                // Get base price directly from database (always fresh, never cached)
                $original_price = (float) get_post_meta( $product_id, '_regular_price', true );

                // Fallback to product object if meta not found
                if ( 0 >= $original_price ) {
                    $original_price = (float) $product->get_regular_price();
                }

                if ( 0 >= $original_price ) {
                    continue; // Skip invalid products
                }

                // Build context for discount calculation
                $context = array(
                    'quantity' => $quantity,
                    'cart_item' => $cart_item,
                    'cart_item_key' => $cart_item_key,
                    'cart_total' => floatval( $cart->get_subtotal() )  // For spend_threshold strategy
                );

                // Get discount info
                $discount_info = $this->get_discount_info( $product_id, $context );

                // Apply discount if eligible
                if ( $discount_info && $this->should_apply_discount( $product, $discount_info ) ) {
                    $discounted_price = (float) $discount_info['discounted_price'];

                    // Validate discount
                    if ( $discounted_price < $original_price && $discounted_price > 0 ) {
                        // Set the discounted price on THIS cart item's product clone
                        $product->set_price( $discounted_price );

                        // Store discount metadata in cart session
                        WC()->cart->cart_contents[ $cart_item_key ]['scd_discount'] = array(
                            'original_price' => $original_price,
                            'discounted_price' => $discounted_price,
                            'discount_amount' => $original_price - $discounted_price,
                            'discount_type' => $discount_info['discount_type'] ?? '',
                            'discount_value' => $discount_info['discount_value'] ?? 0,
                            'campaign_id' => $discount_info['campaign_id'] ?? 0,
                        );
                    }
                } else {
                    // No discount - ensure original price is set
                    $product->set_price( $original_price );

                    // Clear any stale discount data
                    if ( isset( WC()->cart->cart_contents[ $cart_item_key ]['scd_discount'] ) ) {
                        unset( WC()->cart->cart_contents[ $cart_item_key ]['scd_discount'] );
                    }
                }

            } catch ( Exception $e ) {
                $this->log( 'error', 'Failed to modify cart item price', array(
                    'cart_item_key' => $cart_item_key,
                    'product_id' => isset( $product_id ) ? $product_id : 0,
                    'error' => $e->getMessage()
                ) );
            }
        }
    }

    /**
     * Check if discount should be applied.
     *
     * @since    1.0.0
     * @access   private
     * @param    WC_Product    $product        Product object.
     * @param    array         $discount_info  Discount information.
     * @return   bool                          True if should apply.
     */
    private function should_apply_discount(WC_Product $product, array $discount_info): bool {
        // Use memoization if available
        if (class_exists('SCD_Performance_Optimizer')) {
            return SCD_Performance_Optimizer::memoize(
                'should_apply_discount',
                function() use ($product, $discount_info) {
                    return $this->should_apply_discount_internal($product, $discount_info);
                },
                array($product->get_id(), $discount_info)
            );
        }
        
        // Direct check
        return $this->should_apply_discount_internal($product, $discount_info);
    }

    /**
     * Internal check if discount should be applied.
     *
     * @since    1.0.0
     * @access   private
     * @param    WC_Product    $product        Product object.
     * @param    array         $discount_info  Discount information.
     * @return   bool                          True if should apply.
     */
    private function should_apply_discount_internal(WC_Product $product, array $discount_info): bool {
        $original_price = floatval( $product->get_regular_price() );

        // Basic price validation
        if ( $original_price <= 0 || empty( $discount_info['type'] ) ) {
            return false;
        }

        // Check if product is on sale and handle based on discount type
        if ( ! empty( $discount_info['campaign_id'] ) ) {
            $campaign = $this->campaign_manager->find( intval( $discount_info['campaign_id'] ) );
            if ( $campaign ) {
                $settings = $campaign->get_settings();
                $apply_to_sale_items = isset( $settings['apply_to_sale_items'] ) ? (bool) $settings['apply_to_sale_items'] : false;
                $discount_type = $discount_info['type'] ?? '';

                // Check if product has WooCommerce sale price WITHOUT triggering hooks
                // Using 'edit' context bypasses price filters and prevents infinite recursion
                $sale_price = $product->get_sale_price( 'edit' );
                $has_sale_price = ! empty( $sale_price ) && is_numeric( $sale_price ) && floatval( $sale_price ) > 0;

                // If product has WooCommerce sale price set
                if ( $has_sale_price ) {
                    // TIERED discounts REPLACE WooCommerce sale prices (not stack)
                    // They calculate from regular price and override sale price when quantity increases
                    // Always allow tiered discounts regardless of apply_to_sale_items setting
                    if ( $discount_type === 'tiered' ) {
                        return true;
                    }

                    // For other discount types (percentage, fixed), respect the campaign setting
                    if ( ! $apply_to_sale_items ) {
                        return false;
                    }
                }
            }
        }

        // Check customer usage limits
        if ( $this->customer_usage_manager && ! empty( $discount_info['campaign_id'] ) ) {
            try {
                $campaign = $this->campaign_manager->find( intval( $discount_info['campaign_id'] ) );
                if ( $campaign ) {
                    $settings = $campaign->get_settings();
                    $campaign_data = array(
                        'max_uses_per_customer' => isset( $settings['max_uses_per_customer'] ) ? $settings['max_uses_per_customer'] : 0
                    );

                    $validation = $this->customer_usage_manager->validate_customer_usage(
                        intval( $discount_info['campaign_id'] ),
                        $campaign_data
                    );

                    if ( ! $validation['valid'] ) {
                        // Display notice to customer
                        if ( function_exists( 'wc_add_notice' ) ) {
                            wc_add_notice( $validation['error'], 'notice' );
                        }
                        return false;
                    }
                }
            } catch ( Exception $e ) {
                // Log error but allow discount to proceed
                $this->log( 'error', 'Failed to validate customer usage', array(
                    'error' => $e->getMessage()
                ) );
            }
        }

        return true;
    }

    /**
     * Display custom cart item price.
     *
     * @since    1.0.0
     * @param    string    $price         Price HTML.
     * @param    array     $cart_item     Cart item data.
     * @param    string    $cart_item_key Cart item key.
     * @return   string                   Modified price HTML.
     */
    public function display_cart_item_price(string $price, array $cart_item, string $cart_item_key): string {
        if ( ! $this->discount_engine ) {
            return $price;
        }

        try {
            // Check if discount metadata is stored in cart session
            if ( isset( $cart_item['scd_discount'] ) && is_array( $cart_item['scd_discount'] ) ) {
                $discount_data = $cart_item['scd_discount'];
                $original_price = floatval( $discount_data['original_price'] );
                $discounted_price = floatval( $discount_data['discounted_price'] );

                if ( $discounted_price < $original_price && $discounted_price > 0 ) {
                    $price = sprintf(
                        '<del>%s</del> <ins>%s</ins>',
                        wc_price( $original_price ),
                        wc_price( $discounted_price )
                    );
                }
            }

        } catch ( Exception $e ) {
            $this->log( 'error', 'Failed to display cart item price', array(
                'cart_item_key' => $cart_item_key,
                'error' => $e->getMessage()
            ) );
        }

        return $price;
    }

    /**
     * Display custom cart item subtotal.
     *
     * Shows strikethrough original subtotal with discounted subtotal for items with active discounts.
     *
     * @since    1.0.0
     * @param    string    $subtotal      Subtotal HTML.
     * @param    array     $cart_item     Cart item data.
     * @param    string    $cart_item_key Cart item key.
     * @return   string                   Modified subtotal HTML.
     */
    public function display_cart_item_subtotal( string $subtotal, array $cart_item, string $cart_item_key ): string {
        if ( ! $this->discount_engine ) {
            return $subtotal;
        }

        try {
            $quantity = isset( $cart_item['quantity'] ) ? absint( $cart_item['quantity'] ) : 1;

            // Check if discount metadata is stored in cart session
            if ( isset( $cart_item['scd_discount'] ) && is_array( $cart_item['scd_discount'] ) ) {
                $discount_data = $cart_item['scd_discount'];
                $original_price = floatval( $discount_data['original_price'] );
                $discounted_price = floatval( $discount_data['discounted_price'] );

                if ( $discounted_price < $original_price && $discounted_price > 0 ) {
                    $regular_subtotal = $original_price * $quantity;
                    $discounted_subtotal = $discounted_price * $quantity;

                    $subtotal = sprintf(
                        '<del>%s</del> <ins>%s</ins>',
                        wc_price( $regular_subtotal ),
                        wc_price( $discounted_subtotal )
                    );
                }
            }

        } catch ( Exception $e ) {
            $this->log( 'error', 'Failed to display cart item subtotal', array(
                'cart_item_key' => $cart_item_key,
                'error' => $e->getMessage()
            ) );
        }

        return $subtotal;
    }

    /**
     * Maybe hide WooCommerce's generic sale badge.
     *
     * Hides WooCommerce's default "Sale!" badge when our plugin
     * is applying a discount, to prevent duplicate badges.
     *
     * @since    1.0.0
     * @param    string      $html       Original sale badge HTML.
     * @param    WP_Post     $post       Post object.
     * @param    WC_Product  $product    Product object.
     * @return   string                  Modified or empty badge HTML.
     */
    public function maybe_hide_sale_badge( string $html, $post, $product ): string {
        // If no product object, return original
        if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
            return $html;
        }

        // Check if our plugin has an active discount for this product
        $product_id = $product->get_id();
        if ( $this->has_active_discount( $product_id ) ) {
            // Hide WooCommerce's badge since we're showing our own
            return '';
        }

        // No SCD discount, show WooCommerce's badge
        return $html;
    }

    /**
     * Add body class for products with SCD discounts.
     *
     * Adds 'scd-discount-active' class when viewing a product with
     * an active discount. Themes can use CSS to hide their badges:
     *
     * body.scd-discount-active .your-sale-badge { display: none; }
     *
     * @since    1.0.0
     * @param    array    $classes    Body classes.
     * @return   array                Modified classes.
     */
    public function add_discount_body_class( array $classes ): array {
        if ( ! is_product() ) {
            return $classes;
        }

        // Get product ID safely
        $product_id = get_the_ID();
        if ( ! $product_id ) {
            return $classes;
        }

        // Check if product has active discount
        if ( $this->has_active_discount( $product_id ) ) {
            $classes[] = 'scd-discount-active';
        }

        return $classes;
    }

    /**
     * Add post classes for products with SCD discounts.
     *
     * Adds 'scd-has-discount' class to product posts in loops.
     * Themes can use CSS to hide their sale badges:
     *
     * .scd-has-discount .sale-badge { display: none; }
     *
     * @since    1.0.0
     * @param    array    $classes    Post classes.
     * @param    string   $class      Additional class.
     * @param    int      $post_id    Post ID.
     * @return   array                Modified classes.
     */
    public function add_discount_post_class( array $classes, $class = '', $post_id = 0 ): array {
        // Only for product post type
        if ( 'product' !== get_post_type( $post_id ) ) {
            return $classes;
        }

        // Check if product has active discount
        if ( $this->has_active_discount( $post_id ) ) {
            $classes[] = 'scd-has-discount';
        }

        return $classes;
    }

    /**
     * Display discount badge on single product page.
     *
     * @since    1.0.0
     * @return   void
     */
    public function display_discount_badge(): void {
        global $product;

        if ( ! $product || ! $this->discount_engine ) {
            return;
        }

        try {
            $product_id = $product->get_id();

            if ( $this->has_active_discount( $product_id ) ) {
                $discount_info = $this->get_discount_info( $product_id );

                if ( $discount_info ) {
                    echo wp_kses_post( $this->render_discount_badge( $discount_info ) );
                }
            }

        } catch ( Exception $e ) {
            $this->log( 'error', 'Failed to display discount badge', array(
                'product_id' => $product->get_id(),
                'error' => $e->getMessage()
            ) );
        }
    }

    /**
     * Display discount badge on shop loop.
     *
     * @since    1.0.0
     * @return   void
     */
    public function display_shop_discount_badge(): void {
        global $product;

        if ( ! $product || ! $this->discount_engine ) {
            return;
        }

        try {
            $product_id = $product->get_id();

            if ( $this->has_active_discount( $product_id ) ) {
                $discount_info = $this->get_discount_info( $product_id );

                if ( $discount_info ) {
                    echo wp_kses_post( $this->render_discount_badge( $discount_info, 'shop' ) );
                }
            }

        } catch ( Exception $e ) {
            $this->log( 'error', 'Failed to display shop discount badge', array(
                'product_id' => $product->get_id(),
                'error' => $e->getMessage()
            ) );
        }
    }

    /**
     * Display detailed discount information on single product page.
     *
     * @since    1.0.0
     * @return   void
     */
    public function display_product_discount_details(): void {
        global $product;

        if ( ! $product || ! $this->discount_engine ) {
            return;
        }

        try {
            $product_id = $product->get_id();

            if ( $this->has_active_discount( $product_id ) ) {
                $discount_info = $this->get_discount_info( $product_id );

                if ( $discount_info ) {
                    $discount_type = $discount_info['type'];

                    // Only show detailed info for complex discount types
                    if ( in_array( $discount_type, array( 'tiered', 'bogo', 'spend_threshold' ), true ) ) {
                        echo wp_kses_post( $this->render_discount_details( $discount_info ) );
                    }
                }
            }

        } catch ( Exception $e ) {
            $this->log( 'error', 'Failed to display product discount details', array(
                'product_id' => $product->get_id(),
                'error' => $e->getMessage()
            ) );
        }
    }

    /**
     * Display cart discount messages and upsells.
     *
     * @since    1.0.0
     * @return   void
     */
    public function display_cart_discount_messages(): void {
        if ( ! WC()->cart || ! $this->campaign_manager ) {
            return;
        }

        try {
            $cart_items = WC()->cart->get_cart();
            $messages = array();

            // Group cart items by product for quantity-based discounts
            $product_quantities = array();
            foreach ( $cart_items as $cart_item ) {
                $product_id = $cart_item['product_id'];
                if ( ! isset( $product_quantities[ $product_id ] ) ) {
                    $product_quantities[ $product_id ] = 0;
                }
                $product_quantities[ $product_id ] += $cart_item['quantity'];
            }

            // Check each product for discount upsells
            foreach ( $product_quantities as $product_id => $quantity ) {
                if ( ! $this->has_active_discount( $product_id ) ) {
                    continue;
                }

                $discount_info = $this->get_discount_info( $product_id );
                if ( ! $discount_info ) {
                    continue;
                }

                $discount_type = $discount_info['type'];

                // Generate upsell message based on discount type
                if ( 'tiered' === $discount_type && ! empty( $discount_info['campaign_id'] ) ) {
                    $message = $this->get_tiered_cart_message( $discount_info, $product_id, $quantity );
                    if ( $message ) {
                        $messages[] = $message;
                    }
                } elseif ( 'bogo' === $discount_type && ! empty( $discount_info['campaign_id'] ) ) {
                    $message = $this->get_bogo_cart_message( $discount_info, $product_id, $quantity );
                    if ( $message ) {
                        $messages[] = $message;
                    }
                }
            }

            // Check for spend threshold discounts
            $cart_subtotal = floatval( WC()->cart->get_subtotal() );
            $threshold_message = $this->get_threshold_cart_message( $cart_subtotal );
            if ( $threshold_message ) {
                $messages[] = $threshold_message;
            }

            // Display messages
            if ( ! empty( $messages ) ) {
                echo '<div class="scd-cart-discount-messages">';
                foreach ( $messages as $message ) {
                    echo wp_kses_post( $message );
                }
                echo '</div>';
            }

        } catch ( Exception $e ) {
            $this->log( 'error', 'Failed to display cart discount messages', array(
                'error' => $e->getMessage()
            ) );
        }
    }

    /**
     * Get tiered discount cart message.
     *
     * @since    1.0.0
     * @access   private
     * @param    array    $discount_info    Discount information.
     * @param    int      $product_id       Product ID.
     * @param    int      $quantity         Current quantity in cart.
     * @return   string                     Message HTML.
     */
    private function get_tiered_cart_message(array $discount_info, int $product_id, int $quantity): string {
        $tiers = $this->get_sorted_campaign_tiers( $discount_info );
        if ( empty( $tiers ) ) {
            return '';
        }

        // Find current and next tier
        $current_tier = null;
        $next_tier = null;

        foreach ( $tiers as $tier ) {
            $min_qty = isset( $tier['min_quantity'] ) ? intval( $tier['min_quantity'] ) : 1;
            $max_qty = isset( $tier['max_quantity'] ) ? intval( $tier['max_quantity'] ) : 0;

            if ( $quantity >= $min_qty && ( 0 === $max_qty || $quantity <= $max_qty ) ) {
                $current_tier = $tier;
            } elseif ( $quantity < $min_qty && null === $next_tier ) {
                $next_tier = $tier;
            }
        }

        $product = wc_get_product( $product_id );
        $product_name = $product ? $product->get_name() : __( 'this product', 'smart-cycle-discounts' );

        // Build message
        if ( $current_tier && $next_tier ) {
            $current_discount = isset( $current_tier['discount_value'] ) ? floatval( $current_tier['discount_value'] ) : 0;
            $current_type = isset( $current_tier['discount_type'] ) ? $current_tier['discount_type'] : 'percentage';
            $next_discount = isset( $next_tier['discount_value'] ) ? floatval( $next_tier['discount_value'] ) : 0;
            $next_type = isset( $next_tier['discount_type'] ) ? $next_tier['discount_type'] : 'percentage';
            $next_min_qty = isset( $next_tier['min_quantity'] ) ? intval( $next_tier['min_quantity'] ) : 0;
            $qty_needed = $next_min_qty - $quantity;

            // Format current discount
            $current_text = 'percentage' === $current_type
                ? number_format( $current_discount, 0 ) . '% off each'
                : wc_price( $current_discount ) . ' off each';

            // Format next discount
            $next_text = 'percentage' === $next_type
                ? number_format( $next_discount, 0 ) . '% OFF'
                : wc_price( $next_discount ) . ' OFF';

            return sprintf(
                '<div class="scd-cart-message scd-cart-message--upsell"><span class="scd-icon">🎯</span> %s</div>',
                sprintf(
                    __( 'You\'re getting %s on %s! Add %d more to unlock %s per item.', 'smart-cycle-discounts' ),
                    $current_text,
                    esc_html( $product_name ),
                    $qty_needed,
                    $next_text
                )
            );
        } elseif ( $current_tier ) {
            $current_discount = isset( $current_tier['discount_value'] ) ? floatval( $current_tier['discount_value'] ) : 0;
            $current_type = isset( $current_tier['discount_type'] ) ? $current_tier['discount_type'] : 'percentage';

            // Format discount text
            $discount_text = 'percentage' === $current_type
                ? number_format( $current_discount, 0 ) . '% off each'
                : wc_price( $current_discount ) . ' off each';

            return sprintf(
                '<div class="scd-cart-message scd-cart-message--applied"><span class="scd-icon">✓</span> %s</div>',
                sprintf(
                    __( 'You\'re getting %s on %s!', 'smart-cycle-discounts' ),
                    $discount_text,
                    esc_html( $product_name )
                )
            );
        } elseif ( $next_tier ) {
            $next_discount = isset( $next_tier['discount_value'] ) ? floatval( $next_tier['discount_value'] ) : 0;
            $next_type = isset( $next_tier['discount_type'] ) ? $next_tier['discount_type'] : 'percentage';
            $next_min_qty = isset( $next_tier['min_quantity'] ) ? intval( $next_tier['min_quantity'] ) : 0;
            $qty_needed = $next_min_qty - $quantity;

            // Format discount text
            $discount_text = 'percentage' === $next_type
                ? number_format( $next_discount, 0 ) . '% OFF'
                : wc_price( $next_discount ) . ' OFF';

            return sprintf(
                '<div class="scd-cart-message scd-cart-message--upsell"><span class="scd-icon">🎯</span> %s</div>',
                sprintf(
                    __( 'Add %d more %s to unlock %s per item!', 'smart-cycle-discounts' ),
                    $qty_needed,
                    esc_html( $product_name ),
                    $discount_text
                )
            );
        }

        return '';
    }

    /**
     * Get BOGO discount cart message.
     *
     * @since    1.0.0
     * @access   private
     * @param    array    $discount_info    Discount information.
     * @param    int      $product_id       Product ID.
     * @param    int      $quantity         Current quantity in cart.
     * @return   string                     Message HTML.
     */
    private function get_bogo_cart_message(array $discount_info, int $product_id, int $quantity): string {
        $campaign = $this->campaign_manager->find( intval( $discount_info['campaign_id'] ) );
        if ( ! $campaign ) {
            return '';
        }

        $discount_rules = $campaign->get_discount_rules();
        $buy_qty = isset( $discount_rules['buy_quantity'] ) ? intval( $discount_rules['buy_quantity'] ) : 1;
        $get_qty = isset( $discount_rules['get_quantity'] ) ? intval( $discount_rules['get_quantity'] ) : 1;

        $cycle_size = $buy_qty + $get_qty;
        $complete_cycles = floor( $quantity / $cycle_size );
        $remaining = $quantity % $cycle_size;

        $product = wc_get_product( $product_id );
        $product_name = $product ? $product->get_name() : __( 'this product', 'smart-cycle-discounts' );

        if ( $complete_cycles > 0 ) {
            if ( $remaining > 0 ) {
                $needed = $cycle_size - $remaining;
                return sprintf(
                    '<div class="scd-cart-message scd-cart-message--upsell"><span class="scd-icon">🎁</span> %s</div>',
                    sprintf(
                        __( 'BOGO applied! Add %d more %s for another free item.', 'smart-cycle-discounts' ),
                        $needed,
                        esc_html( $product_name )
                    )
                );
            } else {
                return sprintf(
                    '<div class="scd-cart-message scd-cart-message--applied"><span class="scd-icon">✓</span> %s</div>',
                    sprintf(
                        __( 'BOGO applied to %s!', 'smart-cycle-discounts' ),
                        esc_html( $product_name )
                    )
                );
            }
        } else {
            $needed = $cycle_size - $quantity;
            return sprintf(
                '<div class="scd-cart-message scd-cart-message--upsell"><span class="scd-icon">🎯</span> %s</div>',
                sprintf(
                    __( 'Add %d more %s to activate BOGO offer!', 'smart-cycle-discounts' ),
                    $needed,
                    esc_html( $product_name )
                )
            );
        }
    }

    /**
     * Get spend threshold cart message.
     *
     * @since    1.0.0
     * @access   private
     * @param    float    $cart_subtotal    Current cart subtotal.
     * @return   string                     Message HTML.
     */
    private function get_threshold_cart_message(float $cart_subtotal): string {
        if ( ! $this->campaign_manager ) {
            return '';
        }

        // Get all active spend threshold campaigns
        $all_campaigns = $this->campaign_manager->get_active_campaigns();
        $threshold_campaigns = array();

        foreach ( $all_campaigns as $campaign ) {
            if ( 'spend_threshold' === $campaign->get_discount_type() ) {
                $threshold_campaigns[] = $campaign;
            }
        }

        if ( empty( $threshold_campaigns ) ) {
            return '';
        }

        // Find the best matching campaign
        foreach ( $threshold_campaigns as $campaign ) {
            $discount_rules = $campaign->get_discount_rules();
            $thresholds = isset( $discount_rules['thresholds'] ) ? $discount_rules['thresholds'] : array();

            if ( empty( $thresholds ) ) {
                continue;
            }

            // Sort thresholds by amount
            usort( $thresholds, function( $a, $b ) {
                $a_amount = isset( $a['threshold'] ) ? floatval( $a['threshold'] ) : 0;
                $b_amount = isset( $b['threshold'] ) ? floatval( $b['threshold'] ) : 0;
                return $a_amount <=> $b_amount;
            } );

            // Find current and next threshold
            $current_threshold = null;
            $next_threshold = null;

            foreach ( $thresholds as $threshold ) {
                $threshold_amount = isset( $threshold['threshold'] ) ? floatval( $threshold['threshold'] ) : 0;

                if ( $cart_subtotal >= $threshold_amount ) {
                    $current_threshold = $threshold;
                } elseif ( $cart_subtotal < $threshold_amount && null === $next_threshold ) {
                    $next_threshold = $threshold;
                }
            }

            // Build message
            if ( $current_threshold && $next_threshold ) {
                $current_discount = isset( $current_threshold['discount_value'] ) ? floatval( $current_threshold['discount_value'] ) : 0;
                $next_discount = isset( $next_threshold['discount_value'] ) ? floatval( $next_threshold['discount_value'] ) : 0;
                $next_amount = isset( $next_threshold['threshold'] ) ? floatval( $next_threshold['threshold'] ) : 0;
                $amount_needed = $next_amount - $cart_subtotal;

                return sprintf(
                    '<div class="scd-cart-message scd-cart-message--upsell"><span class="scd-icon">🎯</span> %s</div>',
                    sprintf(
                        __( 'You\'ve unlocked %s%% OFF! Spend %s more to get %s%% OFF.', 'smart-cycle-discounts' ),
                        number_format( $current_discount, 0 ),
                        wc_price( $amount_needed ),
                        number_format( $next_discount, 0 )
                    )
                );
            } elseif ( $current_threshold ) {
                $current_discount = isset( $current_threshold['discount_value'] ) ? floatval( $current_threshold['discount_value'] ) : 0;

                return sprintf(
                    '<div class="scd-cart-message scd-cart-message--applied"><span class="scd-icon">✓</span> %s</div>',
                    sprintf(
                        __( 'You\'ve unlocked %s%% OFF your entire order!', 'smart-cycle-discounts' ),
                        number_format( $current_discount, 0 )
                    )
                );
            } elseif ( $next_threshold ) {
                $next_discount = isset( $next_threshold['discount_value'] ) ? floatval( $next_threshold['discount_value'] ) : 0;
                $next_amount = isset( $next_threshold['threshold'] ) ? floatval( $next_threshold['threshold'] ) : 0;
                $amount_needed = $next_amount - $cart_subtotal;

                return sprintf(
                    '<div class="scd-cart-message scd-cart-message--upsell"><span class="scd-icon">🎯</span> %s</div>',
                    sprintf(
                        __( 'Spend %s more to unlock %s%% OFF your entire order!', 'smart-cycle-discounts' ),
                        wc_price( $amount_needed ),
                        number_format( $next_discount, 0 )
                    )
                );
            }
        }

        return '';
    }

    /**
     * Render discount badge HTML.
     *
     * @since    1.0.0
     * @access   private
     * @param    array     $discount_info    Discount information.
     * @param    string    $context          Display context.
     * @return   string                      Badge HTML.
     */
    private function render_discount_badge(array $discount_info, string $context = 'single'): string {
        $badge_class = 'scd-discount-badge scd-discount-badge--' . $context;
        $discount_text = '';
        $discount_type = $discount_info['type'];

        switch ( $discount_type ) {
            case 'percentage':
                $discount_text = sprintf( __( '%s%% OFF', 'smart-cycle-discounts' ), $discount_info['value'] );
                break;

            case 'fixed':
                $discount_text = sprintf( __( '%s OFF', 'smart-cycle-discounts' ), wc_price( $discount_info['value'] ) );
                break;

            case 'tiered':
                // Get campaign to retrieve tier information
                if ( ! empty( $discount_info['campaign_id'] ) && $this->campaign_manager ) {
                    $campaign = $this->campaign_manager->find( intval( $discount_info['campaign_id'] ) );
                    if ( $campaign ) {
                        $discount_rules = $campaign->get_discount_rules();
                        $tiers = isset( $discount_rules['tiers'] ) ? $discount_rules['tiers'] : array();

                        if ( ! empty( $tiers ) ) {
                            // Get highest discount percentage for badge
                            $max_discount = 0;
                            foreach ( $tiers as $tier ) {
                                if ( isset( $tier['discount_value'] ) && 'percentage' === $tier['discount_type'] ) {
                                    $max_discount = max( $max_discount, floatval( $tier['discount_value'] ) );
                                }
                            }

                            if ( $max_discount > 0 ) {
                                $discount_text = sprintf( __( 'Save up to %s%%', 'smart-cycle-discounts' ), number_format( $max_discount, 0 ) );
                            } else {
                                $discount_text = __( 'Volume Discounts', 'smart-cycle-discounts' );
                            }
                        }
                    }
                }

                if ( empty( $discount_text ) ) {
                    $discount_text = __( 'Volume Discounts', 'smart-cycle-discounts' );
                }
                break;

            case 'bogo':
                // Get campaign to retrieve BOGO information
                if ( ! empty( $discount_info['campaign_id'] ) && $this->campaign_manager ) {
                    $campaign = $this->campaign_manager->find( intval( $discount_info['campaign_id'] ) );
                    if ( $campaign ) {
                        $discount_rules = $campaign->get_discount_rules();
                        $buy_qty = isset( $discount_rules['buy_quantity'] ) ? intval( $discount_rules['buy_quantity'] ) : 1;
                        $get_qty = isset( $discount_rules['get_quantity'] ) ? intval( $discount_rules['get_quantity'] ) : 1;
                        $bogo_discount = isset( $discount_rules['discount'] ) ? floatval( $discount_rules['discount'] ) : 100;

                        if ( 100 === $bogo_discount ) {
                            $discount_text = sprintf( __( 'Buy %d Get %d Free', 'smart-cycle-discounts' ), $buy_qty, $get_qty );
                        } else {
                            $discount_text = sprintf( __( 'Buy %d Get %d @ %s%% OFF', 'smart-cycle-discounts' ), $buy_qty, $get_qty, number_format( $bogo_discount, 0 ) );
                        }
                    }
                }

                if ( empty( $discount_text ) ) {
                    $discount_text = __( 'BOGO Offer', 'smart-cycle-discounts' );
                }
                break;

            case 'spend_threshold':
                // Get campaign to retrieve threshold information
                if ( ! empty( $discount_info['campaign_id'] ) && $this->campaign_manager ) {
                    $campaign = $this->campaign_manager->find( intval( $discount_info['campaign_id'] ) );
                    if ( $campaign ) {
                        $discount_rules = $campaign->get_discount_rules();
                        $thresholds = isset( $discount_rules['thresholds'] ) ? $discount_rules['thresholds'] : array();

                        if ( ! empty( $thresholds ) ) {
                            // Get highest discount for badge
                            $max_discount = 0;
                            $min_threshold = PHP_FLOAT_MAX;

                            foreach ( $thresholds as $threshold ) {
                                if ( isset( $threshold['discount_value'] ) && 'percentage' === $threshold['discount_type'] ) {
                                    $max_discount = max( $max_discount, floatval( $threshold['discount_value'] ) );
                                }
                                if ( isset( $threshold['threshold'] ) ) {
                                    $min_threshold = min( $min_threshold, floatval( $threshold['threshold'] ) );
                                }
                            }

                            if ( $max_discount > 0 && $min_threshold < PHP_FLOAT_MAX ) {
                                $discount_text = sprintf(
                                    __( 'Up to %s%% OFF at %s', 'smart-cycle-discounts' ),
                                    number_format( $max_discount, 0 ),
                                    wc_price( $min_threshold )
                                );
                            }
                        }
                    }
                }

                if ( empty( $discount_text ) ) {
                    $discount_text = __( 'Spend & Save', 'smart-cycle-discounts' );
                }
                break;

            default:
                // Unknown discount type
                $discount_text = __( 'Special Offer', 'smart-cycle-discounts' );
                break;
        }

        // Allow filtering of badge text
        $discount_text = apply_filters( 'scd_discount_badge_text', $discount_text, $discount_info, $context );

        if ( empty( $discount_text ) ) {
            return '';
        }

        return sprintf(
            '<div class="%s">%s</div>',
            esc_attr( $badge_class ),
            wp_kses_post( $discount_text )
        );
    }

    /**
     * Render detailed discount information for product pages.
     *
     * @since    1.0.0
     * @access   private
     * @param    array    $discount_info    Discount information.
     * @return   string                     Details HTML.
     */
    private function render_discount_details(array $discount_info): string {
        $discount_type = $discount_info['type'];
        $html = '';

        switch ( $discount_type ) {
            case 'tiered':
                $html = $this->render_tiered_details( $discount_info );
                break;

            case 'bogo':
                $html = $this->render_bogo_details( $discount_info );
                break;

            case 'spend_threshold':
                $html = $this->render_threshold_details( $discount_info );
                break;

            case 'percentage':
            case 'fixed':
                // Simple discounts don't need detailed display
                break;

            default:
                break;
        }

        return apply_filters( 'scd_discount_details_html', $html, $discount_info );
    }

    /**
     * Render tiered discount details table.
     *
     * @since    1.0.0
     * @access   private
     * @param    array    $discount_info    Discount information.
     * @return   string                     HTML table.
     */
    private function render_tiered_details(array $discount_info): string {
        $tiers = $this->get_sorted_campaign_tiers( $discount_info );
        if ( empty( $tiers ) ) {
            return '';
        }

        global $product;
        $base_price = $product ? floatval( $product->get_regular_price() ) : 0;

        ob_start();
        ?>
        <div class="scd-discount-details scd-tiered-details">
            <h4 class="scd-details-title"><?php esc_html_e( 'Volume Discounts Available', 'smart-cycle-discounts' ); ?></h4>
            <table class="scd-tier-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Quantity', 'smart-cycle-discounts' ); ?></th>
                        <th><?php esc_html_e( 'Discount', 'smart-cycle-discounts' ); ?></th>
                        <?php if ( $base_price > 0 ) : ?>
                            <th><?php esc_html_e( 'Price Per Item', 'smart-cycle-discounts' ); ?></th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $tiers as $tier ) :
                        $min_qty = isset( $tier['min_quantity'] ) ? intval( $tier['min_quantity'] ) : 1;
                        $max_qty = isset( $tier['max_quantity'] ) ? intval( $tier['max_quantity'] ) : 0;
                        $discount_value = isset( $tier['discount_value'] ) ? floatval( $tier['discount_value'] ) : 0;
                        $tier_type = isset( $tier['discount_type'] ) ? $tier['discount_type'] : 'percentage';

                        // Format quantity range
                        if ( $max_qty > 0 ) {
                            $qty_display = sprintf( '%d - %d', $min_qty, $max_qty );
                        } else {
                            $qty_display = sprintf( '%d+', $min_qty );
                        }

                        // Format discount
                        if ( 'percentage' === $tier_type ) {
                            $discount_display = sprintf( '%s%%', number_format( $discount_value, 0 ) );
                            $price_per_item = $base_price > 0 ? $base_price * ( 1 - ( $discount_value / 100 ) ) : 0;
                        } else {
                            $discount_display = wc_price( $discount_value );
                            $price_per_item = $base_price > 0 ? $base_price - $discount_value : 0;
                        }
                        ?>
                        <tr>
                            <td><?php echo esc_html( $qty_display ); ?></td>
                            <td>
                                <?php
                                if ( 'percentage' === $tier_type ) {
                                    echo esc_html( $discount_display );
                                } else {
                                    echo wp_kses_post( $discount_display );
                                }
                                ?>
                            </td>
                            <?php if ( $base_price > 0 ) : ?>
                                <td><?php echo wp_kses_post( wc_price( $price_per_item ) ); ?></td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render BOGO discount details.
     *
     * @since    1.0.0
     * @access   private
     * @param    array    $discount_info    Discount information.
     * @return   string                     HTML.
     */
    private function render_bogo_details(array $discount_info): string {
        if ( empty( $discount_info['campaign_id'] ) || ! $this->campaign_manager ) {
            return '';
        }

        $campaign = $this->campaign_manager->find( intval( $discount_info['campaign_id'] ) );
        if ( ! $campaign ) {
            return '';
        }

        $discount_rules = $campaign->get_discount_rules();
        $buy_qty = isset( $discount_rules['buy_quantity'] ) ? intval( $discount_rules['buy_quantity'] ) : 1;
        $get_qty = isset( $discount_rules['get_quantity'] ) ? intval( $discount_rules['get_quantity'] ) : 1;
        $bogo_discount = isset( $discount_rules['discount'] ) ? floatval( $discount_rules['discount'] ) : 100;

        ob_start();
        ?>
        <div class="scd-discount-details scd-bogo-details">
            <h4 class="scd-details-title">
                <?php
                if ( 100 === $bogo_discount ) {
                    echo esc_html( sprintf( __( 'Buy %d Get %d Free!', 'smart-cycle-discounts' ), $buy_qty, $get_qty ) );
                } else {
                    echo esc_html( sprintf( __( 'Buy %d Get %d at %s%% OFF', 'smart-cycle-discounts' ), $buy_qty, $get_qty, number_format( $bogo_discount, 0 ) ) );
                }
                ?>
            </h4>
            <div class="scd-bogo-info">
                <p class="scd-bogo-description">
                    <?php
                    if ( 100 === $bogo_discount ) {
                        echo esc_html( sprintf(
                            __( 'Add %d items to your cart and get %d free! The lowest priced items will be discounted.', 'smart-cycle-discounts' ),
                            $buy_qty + $get_qty,
                            $get_qty
                        ) );
                    } else {
                        echo esc_html( sprintf(
                            __( 'Add %d items to your cart and get %d at %s%% off! The lowest priced items will be discounted.', 'smart-cycle-discounts' ),
                            $buy_qty + $get_qty,
                            $get_qty,
                            number_format( $bogo_discount, 0 )
                        ) );
                    }
                    ?>
                </p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render spend threshold discount details.
     *
     * @since    1.0.0
     * @access   private
     * @param    array    $discount_info    Discount information.
     * @return   string                     HTML table.
     */
    private function render_threshold_details(array $discount_info): string {
        if ( empty( $discount_info['campaign_id'] ) || ! $this->campaign_manager ) {
            return '';
        }

        $campaign = $this->campaign_manager->find( intval( $discount_info['campaign_id'] ) );
        if ( ! $campaign ) {
            return '';
        }

        $discount_rules = $campaign->get_discount_rules();
        $thresholds = isset( $discount_rules['thresholds'] ) ? $discount_rules['thresholds'] : array();

        if ( empty( $thresholds ) ) {
            return '';
        }

        // Sort thresholds by amount
        usort( $thresholds, function( $a, $b ) {
            $a_amount = isset( $a['threshold'] ) ? floatval( $a['threshold'] ) : 0;
            $b_amount = isset( $b['threshold'] ) ? floatval( $b['threshold'] ) : 0;
            return $a_amount <=> $b_amount;
        } );

        ob_start();
        ?>
        <div class="scd-discount-details scd-threshold-details">
            <h4 class="scd-details-title"><?php esc_html_e( 'Spend More, Save More!', 'smart-cycle-discounts' ); ?></h4>
            <table class="scd-threshold-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Spend', 'smart-cycle-discounts' ); ?></th>
                        <th><?php esc_html_e( 'Save', 'smart-cycle-discounts' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $thresholds as $threshold ) :
                        $threshold_amount = isset( $threshold['threshold'] ) ? floatval( $threshold['threshold'] ) : 0;
                        $discount_value = isset( $threshold['discount_value'] ) ? floatval( $threshold['discount_value'] ) : 0;
                        $threshold_type = isset( $threshold['discount_type'] ) ? $threshold['discount_type'] : 'percentage';

                        // Format discount
                        if ( 'percentage' === $threshold_type ) {
                            $discount_display = sprintf( '%s%%', number_format( $discount_value, 0 ) );
                        } else {
                            $discount_display = wc_price( $discount_value );
                        }
                        ?>
                        <tr>
                            <td><?php echo wp_kses_post( wc_price( $threshold_amount ) ); ?></td>
                            <td>
                                <?php
                                if ( 'percentage' === $threshold_type ) {
                                    echo esc_html( $discount_display );
                                } else {
                                    echo wp_kses_post( $discount_display );
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p class="scd-threshold-note"><?php esc_html_e( 'Discount applies to your entire order when you reach the spending threshold.', 'smart-cycle-discounts' ); ?></p>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Add product discount fields to admin.
     *
     * @since    1.0.0
     * @return   void
     */
    public function add_product_discount_fields(): void {
        global $post;

        if (!$post) {
            return;
        }

        $exclude_from_discounts = get_post_meta($post->ID, '_scd_exclude_from_discounts', true);

        woocommerce_wp_checkbox(array(
            'id' => '_scd_exclude_from_discounts',
            'label' => __('Exclude from Smart Cycle Discounts', 'smart-cycle-discounts'),
            'description' => __('Check this to exclude this product from all Smart Cycle Discount campaigns.', 'smart-cycle-discounts'),
            'value' => $exclude_from_discounts
        ));
    }

    /**
     * Save product discount fields.
     *
     * @since    1.0.0
     * @param    int    $post_id    Product ID.
     * @return   void
     */
    public function save_product_discount_fields(int $post_id): void {
        // Validate product meta data using centralized validation
        $validation_result = SCD_Validation::validate( $_POST, 'product_meta' );
        
        if ( is_wp_error( $validation_result ) ) {
            // Log validation errors if logger available
            if ( $this->logger ) {
                $this->logger->warning( 'Product meta validation failed', array(
                    'post_id' => $post_id,
                    'errors'  => SCD_Validation::extract_error_codes( $validation_result )
                ) );
            }
            return;
        }
        
        $validated = $validation_result;
        
        // Check permissions
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }
        
        // Save validated fields
        if ( isset( $validated['scd_exclude_from_discounts'] ) ) {
            $exclude_from_discounts = $validated['scd_exclude_from_discounts'] ? 'yes' : 'no';
            update_post_meta( $post_id, '_scd_exclude_from_discounts', $exclude_from_discounts );
        }
        
        if ( isset( $validated['scd_max_discount_percentage'] ) ) {
            update_post_meta( $post_id, '_scd_max_discount_percentage', $validated['scd_max_discount_percentage'] );
        }
        
        if ( isset( $validated['scd_custom_priority'] ) ) {
            update_post_meta( $post_id, '_scd_custom_priority', $validated['scd_custom_priority'] );
        }
    }

    /**
     * Add order item meta for discount tracking.
     *
     * @since    1.0.0
     * @param    WC_Order_Item_Product    $item           Order item.
     * @param    string                   $cart_item_key  Cart item key.
     * @param    array                    $values         Cart item values.
     * @param    WC_Order                 $order          Order object.
     * @return   void
     */
    public function add_order_item_meta(WC_Order_Item_Product $item, string $cart_item_key, array $values, WC_Order $order): void {
        if (!$this->discount_engine) {
            return;
        }

        try {
            $product_id = $item->get_product_id();
            $quantity = $item->get_quantity();

            if ($this->has_active_discount($product_id)) {
                // Build context with quantity for tiered/bogo/spend_threshold discounts
                $context = array(
                    'quantity' => $quantity,
                    'cart_item' => $values
                );

                $discount_info = $this->get_discount_info($product_id, $context);

                if ($discount_info) {
                    // Get the product to store original price
                    $product = wc_get_product($product_id);
                    if ($product) {
                        $original_price = floatval($product->get_regular_price());
                        $item->add_meta_data('_scd_original_price', $original_price);
                    }

                    $item->add_meta_data('_scd_discount_applied', 'yes');
                    $item->add_meta_data('_scd_discount_type', $discount_info['type']);
                    $item->add_meta_data('_scd_discount_value', $discount_info['value']);
                    $item->add_meta_data('_scd_campaign_id', $discount_info['campaign_id']);

                    // For tiered discounts, store which tier was applied
                    if ( 'tiered' === $discount_info['type'] && isset( $discount_info['metadata']['applicable_tier'] ) ) {
                        $tier = $discount_info['metadata']['applicable_tier'];
                        if ( isset( $tier['min_quantity'], $tier['discount_value'], $tier['discount_type'] ) ) {
                            $item->add_meta_data('_scd_tier_applied', sprintf(
                                '%s%s at %d+ items',
                                $tier['discount_value'],
                                'percentage' === $tier['discount_type'] ? '%' : '',
                                $tier['min_quantity']
                            ), true);
                        }
                    }
                }
            }

        } catch (Exception $e) {
            $this->log('error', 'Failed to add order item meta', array(
                'product_id' => $item->get_product_id(),
                'error' => $e->getMessage()
            ));
        }
    }

    /**
     * Handle AJAX discount application.
     *
     * @since    1.0.0
     * @return   void
     */
    public function ajax_apply_discount(): void {
        // Validate AJAX data using centralized validation
        $validation_result = SCD_Validation::validate( $_POST, 'ajax_action' );
        
        if ( is_wp_error( $validation_result ) ) {
            SCD_AJAX_Response::error(
                'validation_failed',
                $validation_result->get_error_message(),
                array( 'errors' => SCD_Validation::extract_error_codes( $validation_result ) )
            );
            return;
        }
        
        $validated = $validation_result;
        $product_id = isset( $validated['product_id'] ) ? $validated['product_id'] : 0;
        
        if (!$product_id || !$this->discount_engine) {
            SCD_AJAX_Response::error('invalid_product', __('Invalid product or discount engine not available', 'smart-cycle-discounts'));
            return;
        }

        try {
            $discount_info = $this->discount_engine->get_discount_info($product_id);
            
            if ($discount_info) {
                SCD_AJAX_Response::success(array(
                    'discount_applied' => true,
                    'discount_info' => $discount_info,
                    'message' => __('Discount applied successfully!', 'smart-cycle-discounts')
                ));
            } else {
                SCD_AJAX_Response::error('no_active_discount', __('No active discount for this product', 'smart-cycle-discounts'));
            }

        } catch (Exception $e) {
            $this->log('error', 'AJAX discount application failed', array(
                'product_id' => $product_id,
                'error' => $e->getMessage()
            ));
            
            SCD_AJAX_Response::server_error(__('Failed to apply discount', 'smart-cycle-discounts'));
        }
    }

    /**
     * Handle WooCommerce initialization.
     *
     * @since    1.0.0
     * @return   void
     */
    public function on_woocommerce_init(): void {
        $this->log('debug', 'WooCommerce initialized, integration ready');
    }

    /**
     * Declare WooCommerce feature compatibility.
     *
     * @since    1.0.0
     * @return   void
     */
    public function declare_compatibility(): void {
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
                'custom_order_tables',
                SCD_PLUGIN_FILE,
                true
            );

            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
                'cart_checkout_blocks',
                SCD_PLUGIN_FILE,
                true
            );
        }
    }

    /**
     * Get integration status.
     *
     * @since    1.0.0
     * @return   array    Integration status data.
     */
    public function get_status(): array {
        return array(
            'is_compatible' => $this->is_compatible,
            'woocommerce_version' => defined('WC_VERSION') ? WC_VERSION : 'unknown',
            'hpos_enabled' => $this->hpos_enabled,
            'discount_engine_available' => $this->discount_engine !== null,
            'campaign_manager_available' => $this->campaign_manager !== null
        );
    }

    /**
     * Check if integration is ready.
     *
     * @since    1.0.0
     * @return   bool    True if ready.
     */
    public function is_ready(): bool {
        return $this->is_compatible && $this->discount_engine !== null;
    }

    /**
     * Check if product has active discount.
     *
     * @since    1.0.0
     * @param    int    $product_id    Product ID.
     * @return   bool                  True if has active discount.
     */
    public function has_active_discount(int $product_id): bool {
        // Use memoization if available
        if (class_exists('SCD_Performance_Optimizer')) {
            return SCD_Performance_Optimizer::memoize(
                'has_active_discount',
                function() use ($product_id) {
                    return $this->has_active_discount_internal($product_id);
                },
                [$product_id]
            );
        }
        
        return $this->has_active_discount_internal($product_id);
    }
    
    /**
     * Internal check for active discount.
     *
     * @since    1.0.0
     * @access   private
     * @param    int    $product_id    Product ID.
     * @return   bool                  True if has active discount.
     */
    private function has_active_discount_internal(int $product_id): bool {
        if (!$this->campaign_manager) {
            return false;
        }

        try {
            // Could also check via get_discount_info for consistency
            $discount_info = $this->get_discount_info($product_id);
            return !empty($discount_info);
        } catch (Exception $e) {
            $this->log('error', 'Failed to check active discount', array(
                'product_id' => $product_id,
                'error' => $e->getMessage()
            ));
            return false;
        }
    }

    /**
     * Get discount information for a product.
     *
     * @since    1.0.0
     * @param    int    $product_id    Product ID.
     * @return   array|null            Discount info or null if no discount.
     */
    public function get_discount_info(int $product_id, array $context = array()): ?array {
        if (!$this->campaign_manager || !$this->discount_engine) {
            return null;
        }

        try {
            // Get the product
            $product = wc_get_product($product_id);
            if (!$product) {
                return null;
            }

            // For variations, check both the variation and parent product for campaigns
            // CRITICAL: Always use the VARIATION's price for calculations, not the parent's
            $product_ids_to_check = array($product_id);
            $price_source_product = $product; // Always calculate from THIS product's price

            if ( $product->is_type('variation') ) {
                $parent_id = $product->get_parent_id();
                if ( $parent_id ) {
                    // Check parent for campaigns too, but keep variation's price
                    $product_ids_to_check[] = $parent_id;
                }
            }

            $campaigns = array();

            // Check campaigns for both variation and parent
            foreach ( $product_ids_to_check as $check_id ) {
                $product_campaigns = $this->campaign_manager->get_active_campaigns_for_product($check_id);
                if ( ! empty( $product_campaigns ) ) {
                    $campaigns = array_merge( $campaigns, $product_campaigns );
                }
            }

            if (empty($campaigns)) {
                return null;
            }

            // Sort by priority (highest first), then by creation order (oldest first)
            usort( $campaigns, function( $a, $b ) {
                // First compare priority (higher priority wins)
                $priority_diff = $b->get_priority() <=> $a->get_priority();
                if ( $priority_diff !== 0 ) {
                    return $priority_diff;
                }

                // If same priority, older campaign wins (lower ID = created first)
                return $a->get_id() <=> $b->get_id();
            } );

            // CRITICAL: Detect and log campaign conflicts (same priority)
            if ( count( $campaigns ) > 1 ) {
                $highest_priority = $campaigns[0]->get_priority();
                $same_priority = array_filter( $campaigns, function( $c ) use ( $highest_priority ) {
                    return $c->get_priority() === $highest_priority;
                } );

                if ( count( $same_priority ) > 1 ) {
                    // Multiple campaigns with same priority - log warning
                    $this->log( 'warning', 'Multiple campaigns with same priority for product', array(
                        'product_id' => $product_id,
                        'priority' => $highest_priority,
                        'campaigns' => array_map( function( $c ) {
                            return array(
                                'id' => $c->get_id(),
                                'name' => $c->get_name(),
                                'priority' => $c->get_priority()
                            );
                        }, $same_priority ),
                        'selected_campaign' => array(
                            'id' => $campaigns[0]->get_id(),
                            'name' => $campaigns[0]->get_name()
                        )
                    ) );
                }
            }

            $campaign = reset($campaigns);

            // Build discount configuration from campaign properties
            $discount_type = $campaign->get_discount_type();
            $discount_config = array(
                'type' => $discount_type,
                'value' => $campaign->get_discount_value()
            );

            // For complex discount types (tiered, bogo, spend_threshold), include full discount_rules
            if ( in_array( $discount_type, array( 'tiered', 'bogo', 'spend_threshold' ), true ) ) {
                $discount_rules = $campaign->get_discount_rules();
                if ( ! empty( $discount_rules ) ) {
                    // Merge discount_rules into discount_config
                    $discount_config = array_merge( $discount_config, $discount_rules );
                }
            }

            // Map value to percentage for percentage strategy
            if ($discount_config['type'] === 'percentage' && isset($discount_config['value'])) {
                $discount_config['percentage'] = $discount_config['value'];
            }

            // Map value to amount for fixed strategy
            if ($discount_config['type'] === 'fixed' && isset($discount_config['value'])) {
                $discount_config['amount'] = $discount_config['value'];
            }

            // CRITICAL: Always use the actual product's price, not parent's
            // For variations, this ensures we calculate discount from variation price
            $original_price = floatval($price_source_product->get_regular_price());

            // Build context with product information and any passed context (e.g., quantity from cart)
            $discount_context = array_merge(
                array(
                    'product' => $price_source_product, // Use the actual product (variation, not parent)
                    'product_id' => $product_id,
                    'product_type' => $price_source_product->get_type(),
                    'quantity' => 1 // Default to 1 for product pages
                ),
                $context // Override with any passed context (e.g., cart quantity)
            );

            // Calculate discount with context
            // CRITICAL: Wrap in try-catch to prevent cart/checkout crashes
            try {
                $result = $this->discount_engine->calculate_discount($original_price, $discount_config, $discount_context);
            } catch ( Exception $e ) {
                $this->log( 'error', 'Discount calculation exception', array(
                    'product_id' => $product_id,
                    'campaign_id' => $campaign->get_id(),
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ) );
                // Return null gracefully - don't break checkout for one failed discount
                return null;
            }

            if (!$result->is_applied()) {
                return null;
            }

            $discount_data = array(
                'type' => $discount_config['type'] ?? 'percentage',
                'value' => $discount_config['value'] ?? 0,
                'campaign_id' => $campaign->get_id(),
                'discount_amount' => $result->get_discount_amount(),
                'discounted_price' => $result->get_discounted_price(),
                'percentage' => $result->get_discount_percentage()
            );

            // Add metadata if available (e.g., applicable tier for tiered discounts)
            if ( method_exists( $result, 'get_metadata' ) ) {
                $metadata = $result->get_metadata();
                if ( ! empty( $metadata ) ) {
                    $discount_data['metadata'] = $metadata;
                }
            }

            return $discount_data;
            
        } catch (Exception $e) {
            $this->log('error', 'Failed to get discount info', array(
                'product_id' => $product_id,
                'error' => $e->getMessage()
            ));
            return null;
        }
    }


    /**
     * Track customer usage when order is completed.
     *
     * @since    1.0.0
     * @param    int    $order_id    Order ID.
     * @return   void
     */
    public function track_customer_usage(int $order_id): void {
        if (!$this->customer_usage_manager) {
            return;
        }

        try {
            $order = wc_get_order($order_id);
            if (!$order || !$order instanceof WC_Order) {
                $this->log('warning', 'Invalid order object', array('order_id' => $order_id));
                return;
            }

            // Check if usage was already tracked for this order
            if ($order->get_meta('_scd_usage_tracked') === 'yes') {
                $this->log('debug', 'Usage already tracked for order', array('order_id' => $order_id));
                return;
            }
            
            // Only track for eligible order statuses
            $valid_statuses = array('completed', 'processing');
            if (!in_array($order->get_status(), $valid_statuses)) {
                $this->log('debug', 'Order status not eligible for tracking', array(
                    'order_id' => $order_id,
                    'status' => $order->get_status()
                ));
                return;
            }

            // Track usage
            $this->customer_usage_manager->record_order_usage($order_id, $order);

            // Mark order as tracked
            $order->update_meta_data('_scd_usage_tracked', 'yes');
            $order->save();
            
            $this->log('info', 'Customer usage tracked successfully', array('order_id' => $order_id));

        } catch (Exception $e) {
            $this->log('error', 'Failed to track customer usage', array(
                'order_id' => $order_id,
                'error' => $e->getMessage()
            ));
        }
    }

    /**
     * Get sorted tiers from campaign.
     *
     * @since    1.0.0
     * @access   private
     * @param    array    $discount_info    Discount information with campaign_id.
     * @return   array                     Sorted tiers array or empty array.
     */
    private function get_sorted_campaign_tiers( array $discount_info ): array {
        if ( empty( $discount_info['campaign_id'] ) || ! $this->campaign_manager ) {
            return array();
        }

        $campaign = $this->campaign_manager->find( intval( $discount_info['campaign_id'] ) );
        if ( ! $campaign ) {
            return array();
        }

        $discount_rules = $campaign->get_discount_rules();
        $tiers = isset( $discount_rules['tiers'] ) ? $discount_rules['tiers'] : array();

        if ( empty( $tiers ) ) {
            return array();
        }

        // Sort tiers by minimum quantity
        usort( $tiers, function( $a, $b ) {
            $a_min = isset( $a['min_quantity'] ) ? intval( $a['min_quantity'] ) : 0;
            $b_min = isset( $b['min_quantity'] ) ? intval( $b['min_quantity'] ) : 0;
            return $a_min <=> $b_min;
        } );

        return $tiers;
    }
}

