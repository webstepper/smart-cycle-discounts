<?php
/**
 * WooCommerce Order Integration
 *
 * Handles order tracking and customer usage tracking.
 *
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/integrations/woocommerce
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce Order Integration class.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/integrations/woocommerce
 */
class SCD_WC_Order_Integration {

	/**
	 * Discount query service.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_WC_Discount_Query_Service    $discount_query
	 */
	private SCD_WC_Discount_Query_Service $discount_query;

	/**
	 * Customer usage manager.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Customer_Usage_Manager|null    $usage_manager
	 */
	private ?SCD_Customer_Usage_Manager $usage_manager;

	/**
	 * Logger.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      object|null    $logger
	 */
	private ?object $logger;

	/**
	 * Initialize order integration.
	 *
	 * @since    1.0.0
	 * @param    SCD_WC_Discount_Query_Service   $discount_query    Discount query service.
	 * @param    SCD_Customer_Usage_Manager|null $usage_manager     Usage manager.
	 * @param    object|null                     $logger            Logger.
	 */
	public function __construct(
		SCD_WC_Discount_Query_Service $discount_query,
		?SCD_Customer_Usage_Manager $usage_manager = null,
		?object $logger = null
	) {
		$this->discount_query = $discount_query;
		$this->usage_manager  = $usage_manager;
		$this->logger         = $logger;
	}

	/**
	 * Register hooks.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function register_hooks(): void {
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'add_order_item_meta' ), 10, 4 );
		add_action( 'woocommerce_payment_complete', array( $this, 'track_customer_usage' ), 10, 1 );
		add_action( 'woocommerce_order_status_completed', array( $this, 'track_customer_usage' ), 10, 1 );
	}

	/**
	 * Add order item meta for discount tracking.
	 *
	 * @since    1.0.0
	 * @param    WC_Order_Item_Product $item             Order item.
	 * @param    string                $cart_item_key    Cart item key.
	 * @param    array                 $cart_item        Cart item data.
	 * @param    WC_Order              $order            Order object.
	 * @return   void
	 */
	public function add_order_item_meta( $item, string $cart_item_key, array $cart_item, $order ): void {
		if ( isset( $cart_item['scd_discount'] ) ) {
			$discount = $cart_item['scd_discount'];

			$item->add_meta_data( '_scd_discount_applied', 'yes', true );
			$item->add_meta_data( '_scd_campaign_id', $discount['campaign_id'] ?? 0, true );
			$item->add_meta_data( '_scd_original_price', $discount['original_price'] ?? 0, true );
			$item->add_meta_data( '_scd_discounted_price', $discount['discounted_price'] ?? 0, true );
			$item->add_meta_data( '_scd_discount_amount', $discount['discount_amount'] ?? 0, true );
		}
	}

	/**
	 * Track customer usage when order is completed.
	 *
	 * @since    1.0.0
	 * @param    int $order_id    Order ID.
	 * @return   void
	 */
	public function track_customer_usage( int $order_id ): void {
		if ( ! $this->usage_manager ) {
			return;
		}

		try {
			$order = wc_get_order( $order_id );

			if ( ! $order ) {
				return;
			}

			$customer_id = $order->get_customer_id();

			if ( $customer_id <= 0 ) {
				return;
			}

			foreach ( $order->get_items() as $item ) {
				$campaign_id = $item->get_meta( '_scd_campaign_id' );

				if ( $campaign_id ) {
					$this->usage_manager->track_usage( $customer_id, (int) $campaign_id, $order_id );

					$this->log(
						'info',
						'Tracked customer usage',
						array(
							'customer_id' => $customer_id,
							'campaign_id' => $campaign_id,
							'order_id'    => $order_id,
						)
					);
				}
			}
		} catch ( Exception $e ) {
			$this->log(
				'error',
				'Failed to track customer usage',
				array(
					'order_id' => $order_id,
					'error'    => $e->getMessage(),
				)
			);
		}
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
			$this->logger->$level( '[WC_Order] ' . $message, $context );
		}
	}
}
