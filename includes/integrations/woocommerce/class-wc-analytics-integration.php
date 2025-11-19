<?php
/**
 * WooCommerce Analytics Integration
 *
 * Implements the e-commerce integration interface for WooCommerce.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/integrations/woocommerce
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
 * WooCommerce Analytics Integration Class
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/integrations/woocommerce
 * @author     Webstepper <contact@webstepper.io>
 */
class SCD_WC_Analytics_Integration implements SCD_Ecommerce_Integration {

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
	 * @return   bool    True if WooCommerce is active.
	 */
	public function is_active() {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Get order by ID.
	 *
	 * @since    1.0.0
	 * @param    int $order_id    Order ID.
	 * @return   WC_Order|null    Order object or null.
	 */
	public function get_order( $order_id ) {
		return wc_get_order( $order_id );
	}

	/**
	 * Get order items.
	 *
	 * @since    1.0.0
	 * @param    WC_Order $order    Order object.
	 * @return   array              Array of order items.
	 */
	public function get_order_items( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return array();
		}

		return $order->get_items();
	}

	/**
	 * Get item product ID.
	 *
	 * @since    1.0.0
	 * @param    WC_Order_Item_Product $item    Order item.
	 * @return   int                            Product ID.
	 */
	public function get_item_product_id( $item ) {
		if ( ! $item instanceof WC_Order_Item_Product ) {
			return 0;
		}

		return $item->get_product_id();
	}

	/**
	 * Get item total.
	 *
	 * @since    1.0.0
	 * @param    WC_Order_Item_Product $item    Order item.
	 * @return   float                          Item total.
	 */
	public function get_item_total( $item ) {
		if ( ! $item instanceof WC_Order_Item_Product ) {
			return 0.0;
		}

		return (float) $item->get_total();
	}

	/**
	 * Get item subtotal (before discount).
	 *
	 * @since    1.0.0
	 * @param    WC_Order_Item_Product $item    Order item.
	 * @return   float                          Item subtotal.
	 */
	public function get_item_subtotal( $item ) {
		if ( ! $item instanceof WC_Order_Item_Product ) {
			return 0.0;
		}

		return (float) $item->get_subtotal();
	}

	/**
	 * Get item discount amount.
	 *
	 * @since    1.0.0
	 * @param    WC_Order_Item_Product $item    Order item.
	 * @return   float                          Discount amount.
	 */
	public function get_item_discount( $item ) {
		if ( ! $item instanceof WC_Order_Item_Product ) {
			return 0.0;
		}

		$subtotal = $this->get_item_subtotal( $item );
		$total    = $this->get_item_total( $item );

		return max( 0.0, $subtotal - $total );
	}

	/**
	 * Get item quantity.
	 *
	 * @since    1.0.0
	 * @param    WC_Order_Item_Product $item    Order item.
	 * @return   int                            Quantity.
	 */
	public function get_item_quantity( $item ) {
		if ( ! $item instanceof WC_Order_Item_Product ) {
			return 0;
		}

		return (int) $item->get_quantity();
	}

	/**
	 * Get order customer ID.
	 *
	 * @since    1.0.0
	 * @param    WC_Order $order    Order object.
	 * @return   int                Customer/user ID.
	 */
	public function get_order_customer_id( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return 0;
		}

		return (int) $order->get_customer_id();
	}

	/**
	 * Get order total.
	 *
	 * @since    1.0.0
	 * @param    WC_Order $order    Order object.
	 * @return   float              Order total.
	 */
	public function get_order_total( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return 0.0;
		}

		return (float) $order->get_total();
	}

	/**
	 * Get campaign ID from order item.
	 *
	 * @since    1.0.0
	 * @param    WC_Order_Item_Product $item    Order item.
	 * @return   int|null                       Campaign ID or null.
	 */
	public function get_item_campaign_id( $item ) {
		if ( ! $item instanceof WC_Order_Item_Product ) {
			return null;
		}

		$campaign_id = $item->get_meta( '_scd_campaign_id', true );

		return $campaign_id ? (int) $campaign_id : null;
	}

	/**
	 * Set campaign ID on order item.
	 *
	 * @since    1.0.0
	 * @param    WC_Order_Item_Product $item          Order item.
	 * @param    int                   $campaign_id   Campaign ID.
	 * @return   void
	 */
	public function set_item_campaign_id( $item, $campaign_id ) {
		if ( ! $item instanceof WC_Order_Item_Product ) {
			return;
		}

		$item->add_meta_data( '_scd_campaign_id', $campaign_id, true );
		$item->save();
	}

	/**
	 * Register order completion hook.
	 *
	 * @since    1.0.0
	 * @param    callable $callback    Callback function.
	 * @return   void
	 */
	public function register_order_complete_hook( $callback ) {
		// Hook into order processing (checkout complete)
		add_action( 'woocommerce_checkout_order_processed', $callback, 10, 1 );

		// Also hook into order status changes to completed
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

		if ( ! $product ) {
			return 0.0;
		}

		return (float) $product->get_price();
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

		return ! empty( $product );
	}
}
