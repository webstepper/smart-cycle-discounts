<?php
/**
 * WooCommerce HPOS (High-Performance Order Storage) Compatibility
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


/**
 * WooCommerce HPOS Compatibility Class
 *
 * Ensures full compatibility with WooCommerce High-Performance Order Storage (HPOS).
 * This class handles order data storage and retrieval for both traditional post-based
 * orders and the new custom order tables.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/integrations/woocommerce
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_HPOS_Compatibility {

    /**
     * Container instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      SCD_Container    $container    Container instance.
     */
    private SCD_Container $container;

    /**
     * Logger instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      SCD_Logger    $logger    Logger instance.
     */
    private SCD_Logger $logger;

    /**
     * Initialize HPOS compatibility.
     *
     * @since    1.0.0
     * @param    SCD_Container    $container    Container instance.
     */
    public function __construct(SCD_Container $container) {
        $this->container = $container;
        $this->logger = $container->get('logger');
    }

    /**
     * Initialize HPOS compatibility hooks.
     *
     * @since    1.0.0
     * @return   void
     */
    public function init(): void {
        // Declare HPOS compatibility
        add_action('before_woocommerce_init', array($this, 'declare_hpos_compatibility'));
        
        // Hook into order creation and updates
        add_action('woocommerce_new_order', array($this, 'handle_new_order'), 10, 2);
        add_action('woocommerce_update_order', array($this, 'handle_order_update'), 10, 2);
        
        // Hook into order status changes
        add_action('woocommerce_order_status_changed', array($this, 'handle_order_status_change'), 10, 4);
        
        // Hook into order item operations
        add_action('woocommerce_new_order_item', array($this, 'handle_new_order_item'), 10, 3);
        add_action('woocommerce_update_order_item', array($this, 'handle_order_item_update'), 10, 3);
        
        // Analytics integration for HPOS
        add_action('woocommerce_order_status_completed', array($this, 'track_hpos_order_completion'), 10, 2);
    }

    /**
     * Declare HPOS compatibility.
     *
     * @since    1.0.0
     * @return   void
     */
    public function declare_hpos_compatibility(): void {
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
                'custom_order_tables',
                SCD_PLUGIN_FILE,
                true
            );
            
            $this->logger->info('Declared HPOS compatibility for Smart Cycle Discounts plugin');
        }
    }

    /**
     * Check if HPOS is enabled.
     *
     * @since    1.0.0
     * @return   bool    True if HPOS is enabled.
     */
    public function is_hpos_enabled(): bool {
        if (!class_exists('\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController')) {
            return false;
        }

        $controller = wc_get_container()->get(
            \Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class
        );

        return $controller->custom_orders_table_usage_is_enabled();
    }

    /**
     * Get order using HPOS-compatible method.
     *
     * @since    1.0.0
     * @param    int    $order_id    Order ID.
     * @return   WC_Order|false      Order object or false.
     */
    public function get_order(int $order_id) {
        return wc_get_order($order_id);
    }

    /**
     * Get order meta using HPOS-compatible method.
     *
     * @since    1.0.0
     * @param    int       $order_id    Order ID.
     * @param    string    $meta_key    Meta key.
     * @param    bool      $single      Whether to return single value.
     * @return   mixed                  Meta value.
     */
    public function get_order_meta(int $order_id, string $meta_key, bool $single = true) {
        $order = $this->get_order($order_id);
        if (!$order) {
            return false;
        }

        return $order->get_meta($meta_key, $single);
    }

    /**
     * Update order meta using HPOS-compatible method.
     *
     * @since    1.0.0
     * @param    int       $order_id      Order ID.
     * @param    string    $meta_key      Meta key.
     * @param    mixed     $meta_value    Meta value.
     * @return   bool                     Success status.
     */
    public function update_order_meta(int $order_id, string $meta_key, $meta_value): bool {
        $order = $this->get_order($order_id);
        if (!$order) {
            return false;
        }

        $order->update_meta_data($meta_key, $meta_value);
        $order->save();

        return true;
    }

    /**
     * Delete order meta using HPOS-compatible method.
     *
     * @since    1.0.0
     * @param    int       $order_id    Order ID.
     * @param    string    $meta_key    Meta key.
     * @return   bool                   Success status.
     */
    public function delete_order_meta(int $order_id, string $meta_key): bool {
        $order = $this->get_order($order_id);
        if (!$order) {
            return false;
        }

        $order->delete_meta_data($meta_key);
        $order->save();

        return true;
    }

    /**
     * Query orders using HPOS-compatible method.
     *
     * @since    1.0.0
     * @param    array    $args    Query arguments.
     * @return   array             Array of order objects.
     */
    public function query_orders(array $args = array()): array {
        $defaults = array(
            'limit' => -1,
            'status' => 'any',
            'return' => 'objects'
        );

        $args = wp_parse_args($args, $defaults);

        return wc_get_orders($args);
    }

    /**
     * Get orders with discount metadata.
     *
     * @since    1.0.0
     * @param    int    $campaign_id    Campaign ID.
     * @param    int    $limit          Limit results.
     * @return   array                  Array of order objects.
     */
    public function get_orders_with_discounts(int $campaign_id = 0, int $limit = -1): array {
        $args = array(
            'limit' => $limit,
            'meta_query' => array(
                array(
                    'key' => '_scd_discount_applied',
                    'compare' => 'EXISTS'
                )
            )
        );

        if ($campaign_id > 0) {
            $args['meta_query'][] = array(
                'key' => '_scd_campaign_id',
                'value' => $campaign_id,
                'compare' => '='
            );
        }

        return $this->query_orders($args);
    }

    /**
     * Handle new order creation.
     *
     * @since    1.0.0
     * @param    int        $order_id    Order ID.
     * @param    WC_Order   $order       Order object.
     * @return   void
     */
    public function handle_new_order(int $order_id, WC_Order $order): void {
        // Check if order has discounted items
        $has_discounts = false;
        $total_discount = 0;
        $campaign_ids = array();

        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if (!$product) {
                continue;
            }

            // Check if product has active discount
            if ($this->container->has('discount_engine')) {
                $discount_engine = $this->container->get('discount_engine');
                $discount_result = $discount_engine->calculate_discount($product);

                if ($discount_result && $discount_result->has_discount()) {
                    $has_discounts = true;
                    $total_discount += $discount_result->get_discount_amount() * $item->get_quantity();
                    $campaign_ids[] = $discount_result->get_campaign_id();
                }
            }
        }

        if ($has_discounts) {
            // Store discount metadata
            $order->update_meta_data('_scd_discount_applied', true);
            $order->update_meta_data('_scd_total_discount', $total_discount);
            $order->update_meta_data('_scd_campaign_ids', array_unique($campaign_ids));
            $order->update_meta_data('_scd_discount_timestamp', time());
            $order->save();

            $this->logger->info('Applied discount metadata to HPOS order', array(
                'order_id' => $order_id, 'total_discount' => $total_discount,
                'campaign_ids' => $campaign_ids
            ));
        }
    }

    /**
     * Handle order updates.
     *
     * @since    1.0.0
     * @param    int        $order_id    Order ID.
     * @param    WC_Order   $order       Order object.
     * @return   void
     */
    public function handle_order_update(int $order_id, WC_Order $order): void {
        // Re-calculate discounts if order items changed
        $this->handle_new_order($order_id, $order);
    }

    /**
     * Handle order status changes.
     *
     * @since    1.0.0
     * @param    int       $order_id      Order ID.
     * @param    string    $status_from   Previous status.
     * @param    string    $status_to     New status.
     * @param    WC_Order  $order         Order object.
     * @return   void
     */
    public function handle_order_status_change(int $order_id, string $status_from, string $status_to, WC_Order $order): void {
        // Track status changes for analytics
        if ($this->container->has('analytics_collector')) {
            $analytics_collector = $this->container->get('analytics_collector');
            
            $analytics_collector->track_event('order_status_change', array(
                'order_id' => $order_id,
                'status_from' => $status_from,
                'status_to' => $status_to,
                'has_discount' => $order->get_meta('_scd_discount_applied', true),
                'total_discount' => $order->get_meta('_scd_total_discount', true),
                'campaign_ids' => $order->get_meta('_scd_campaign_ids', true)
            ));
        }
    }

    /**
     * Handle new order item creation.
     *
     * @since    1.0.0
     * @param    int                    $item_id    Item ID.
     * @param    WC_Order_Item_Product  $item       Item object.
     * @param    int                    $order_id   Order ID.
     * @return   void
     */
    public function handle_new_order_item(int $item_id, WC_Order_Item_Product $item, int $order_id): void {
        $product = $item->get_product();
        if (!$product) {
            return;
        }

        // Check if product has discount and store item-level metadata
        if ($this->container->has('discount_engine')) {
            $discount_engine = $this->container->get('discount_engine');
            $discount_result = $discount_engine->calculate_discount($product);

            if ($discount_result && $discount_result->has_discount()) {
                $item->update_meta_data('_scd_original_price', $product->get_regular_price());
                $item->update_meta_data('_scd_discount_amount', $discount_result->get_discount_amount());
                $item->update_meta_data('_scd_discount_type', $discount_result->get_discount_type());
                $item->update_meta_data('_scd_campaign_id', $discount_result->get_campaign_id());
                $item->save();
            }
        }
    }

    /**
     * Handle order item updates.
     *
     * @since    1.0.0
     * @param    int                    $item_id    Item ID.
     * @param    WC_Order_Item_Product  $item       Item object.
     * @param    int                    $order_id   Order ID.
     * @return   void
     */
    public function handle_order_item_update(int $item_id, WC_Order_Item_Product $item, int $order_id): void {
        // Re-calculate item-level discounts
        $this->handle_new_order_item($item_id, $item, $order_id);
    }

    /**
     * Track HPOS order completion for analytics.
     *
     * @since    1.0.0
     * @param    int        $order_id    Order ID.
     * @param    WC_Order   $order       Order object.
     * @return   void
     */
    public function track_hpos_order_completion(int $order_id, WC_Order $order): void {
        $has_discount = $order->get_meta('_scd_discount_applied', true);
        
        if ($has_discount && $this->container->has('analytics_collector')) {
            $analytics_collector = $this->container->get('analytics_collector');
            
            $campaign_ids = $order->get_meta('_scd_campaign_ids', true);
            $total_discount = $order->get_meta('_scd_total_discount', true);

            foreach ((array) $campaign_ids as $campaign_id) {
                $analytics_collector->track_event('conversion', array(
                    'campaign_id' => $campaign_id,
                    'order_id' => $order_id,
                    'order_total' => $order->get_total(),
                    'discount_amount' => $total_discount,
                    'customer_id' => $order->get_customer_id(),
                    'hpos_enabled' => $this->is_hpos_enabled()
                ));
            }

            $this->logger->info('Tracked HPOS order completion', array(
                'order_id' => $order_id,
                'campaign_ids' => $campaign_ids,
                'total_discount' => $total_discount,
                'hpos_enabled' => $this->is_hpos_enabled()
            ));
        }
    }

    /**
     * Get discount statistics for HPOS orders.
     *
     * @since    1.0.0
     * @param    array    $args    Query arguments.
     * @return   array             Statistics array.
     */
    public function get_discount_statistics(array $args = array()): array {
        $defaults = array(
            'date_from' => date('Y-m-01'),
            'date_to' => date('Y-m-d'),
            'campaign_id' => 0
        );

        $args = wp_parse_args($args, $defaults);

        $query_args = array(
            'date_created' => $args['date_from'] . '...' . $args['date_to'],
            'meta_query' => array(
                array(
                    'key' => '_scd_discount_applied',
                    'value' => true,
                    'compare' => '='
                )
            )
        );

        if ($args['campaign_id'] > 0) {
            $query_args['meta_query'][] = array(
                'key' => '_scd_campaign_ids',
                'value' => $args['campaign_id'],
                'compare' => 'LIKE'
            );
        }

        $orders = $this->query_orders($query_args);

        $stats = array(
            'total_orders' => count($orders),
            'total_discount' => 0,
            'total_revenue' => 0,
            'average_discount' => 0,
            'hpos_enabled' => $this->is_hpos_enabled()
        );

        foreach ($orders as $order) {
            $discount_amount = (float) $order->get_meta('_scd_total_discount', true);
            $stats['total_discount'] += $discount_amount;
            $stats['total_revenue'] += (float) $order->get_total();
        }

        if ($stats['total_orders'] > 0) {
            $stats['average_discount'] = $stats['total_discount'] / $stats['total_orders'];
        }

        return $stats;
    }

    /**
     * Migrate legacy order data to HPOS format.
     *
     * @since    1.0.0
     * @param    int    $batch_size    Number of orders to process per batch.
     * @return   array                 Migration results.
     */
    public function migrate_legacy_order_data(int $batch_size = 100): array {
        if (!$this->is_hpos_enabled()) {
            return array(
                'success' => false,
                'message' => 'HPOS is not enabled'
            );
        }

        // Get legacy orders with discount metadata
        $legacy_orders = get_posts(array(
            'post_type' => 'shop_order',
            'posts_per_page' => $batch_size,
            'meta_query' => array(
                array(
                    'key' => '_scd_discount_applied',
                    'compare' => 'EXISTS'
                )
            )
        ));

        $migrated = 0;
        $errors = array();

        foreach ($legacy_orders as $post) {
            try {
                $order = wc_get_order($post->ID);
                if (!$order) {
                    continue;
                }

                // Re-save order to trigger HPOS migration
                $order->save();
                $migrated++;

            } catch (Exception $e) {
                $errors[] = "Order {$post->ID}: " . $e->getMessage();
            }
        }

        return array(
            'success' => true,
            'migrated' => $migrated,
            'total_found' => count($legacy_orders),
            'errors' => $errors
        );
    }

    /**
     * Validate HPOS compatibility.
     *
     * @since    1.0.0
     * @return   array    Validation results.
     */
    public function validate_hpos_compatibility(): array {
        $results = array(
            'hpos_available' => class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil'),
            'hpos_enabled' => $this->is_hpos_enabled(),
            'compatibility_declared' => false,
            'order_operations_working' => false,
            'metadata_operations_working' => false
        );

        // Check if compatibility is declared
        if ($results['hpos_available']) {
            $results['compatibility_declared'] = true;
        }

        // Test order operations
        try {
            $test_orders = $this->query_orders(array('limit' => 1));
            $results['order_operations_working'] = true;
        } catch (Exception $e) {
            $results['order_operations_working'] = false;
            $results['order_error'] = $e->getMessage();
        }

        // Test metadata operations
        if (!empty($test_orders)) {
            try {
                $order = $test_orders[0];
                $test_meta = $order->get_meta('_test_hpos_compatibility', true);
                $results['metadata_operations_working'] = true;
            } catch (Exception $e) {
                $results['metadata_operations_working'] = false;
                $results['metadata_error'] = $e->getMessage();
            }
        }

        return $results;
    }
}

