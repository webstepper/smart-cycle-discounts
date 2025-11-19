<?php
/**
 * E-Commerce Integration Interface
 *
 * Defines the contract for e-commerce platform integrations.
 * This allows the analytics system to work with WooCommerce, EDD, or any other platform.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/integrations
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
 * E-Commerce Integration Interface
 *
 * Provides platform-agnostic methods for analytics tracking.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/integrations
 * @author     Webstepper <contact@webstepper.io>
 */
interface SCD_Ecommerce_Integration {

	/**
	 * Get platform name.
	 *
	 * @since    1.0.0
	 * @return   string    Platform name (e.g., 'woocommerce', 'edd').
	 */
	public function get_platform_name();

	/**
	 * Check if platform is active.
	 *
	 * @since    1.0.0
	 * @return   bool    True if platform is active.
	 */
	public function is_active();

	/**
	 * Get order by ID.
	 *
	 * @since    1.0.0
	 * @param    int $order_id    Order ID.
	 * @return   object|null      Order object or null if not found.
	 */
	public function get_order( $order_id );

	/**
	 * Get order items.
	 *
	 * @since    1.0.0
	 * @param    object $order    Order object.
	 * @return   array            Array of order items.
	 */
	public function get_order_items( $order );

	/**
	 * Get item product ID.
	 *
	 * @since    1.0.0
	 * @param    mixed $item    Order item.
	 * @return   int            Product ID.
	 */
	public function get_item_product_id( $item );

	/**
	 * Get item total.
	 *
	 * @since    1.0.0
	 * @param    mixed $item    Order item.
	 * @return   float          Item total.
	 */
	public function get_item_total( $item );

	/**
	 * Get item subtotal (before discount).
	 *
	 * @since    1.0.0
	 * @param    mixed $item    Order item.
	 * @return   float          Item subtotal.
	 */
	public function get_item_subtotal( $item );

	/**
	 * Get item discount amount.
	 *
	 * @since    1.0.0
	 * @param    mixed $item    Order item.
	 * @return   float          Discount amount.
	 */
	public function get_item_discount( $item );

	/**
	 * Get item quantity.
	 *
	 * @since    1.0.0
	 * @param    mixed $item    Order item.
	 * @return   int            Quantity.
	 */
	public function get_item_quantity( $item );

	/**
	 * Get order customer ID.
	 *
	 * @since    1.0.0
	 * @param    object $order    Order object.
	 * @return   int              Customer/user ID.
	 */
	public function get_order_customer_id( $order );

	/**
	 * Get order total.
	 *
	 * @since    1.0.0
	 * @param    object $order    Order object.
	 * @return   float            Order total.
	 */
	public function get_order_total( $order );

	/**
	 * Get campaign ID from order item.
	 *
	 * @since    1.0.0
	 * @param    mixed $item    Order item.
	 * @return   int|null       Campaign ID or null.
	 */
	public function get_item_campaign_id( $item );

	/**
	 * Set campaign ID on order item.
	 *
	 * @since    1.0.0
	 * @param    mixed $item          Order item.
	 * @param    int   $campaign_id   Campaign ID.
	 * @return   void
	 */
	public function set_item_campaign_id( $item, $campaign_id );

	/**
	 * Register order completion hook.
	 *
	 * @since    1.0.0
	 * @param    callable $callback    Callback function.
	 * @return   void
	 */
	public function register_order_complete_hook( $callback );

	/**
	 * Get product price.
	 *
	 * @since    1.0.0
	 * @param    int $product_id    Product ID.
	 * @return   float              Product price.
	 */
	public function get_product_price( $product_id );

	/**
	 * Check if product exists.
	 *
	 * @since    1.0.0
	 * @param    int $product_id    Product ID.
	 * @return   bool               True if product exists.
	 */
	public function product_exists( $product_id );
}
