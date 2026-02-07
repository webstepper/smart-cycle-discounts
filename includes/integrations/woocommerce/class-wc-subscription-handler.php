<?php
/**
 * WooCommerce Subscription Handler
 *
 * Handles WooCommerce Subscriptions integration for discount campaigns.
 * Provides subscription detection, sign-up fee discounting, and renewal tracking.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/integrations/woocommerce
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress/plugins/smart-cycle-discounts/
 * @since      1.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Subscription Handler Class
 *
 * Integrates with WooCommerce Subscriptions to support discount campaigns
 * on subscription products. Free tier: recurring price discounts.
 * Pro tier: sign-up fee discounts and renewal limits.
 *
 * @since 1.6.0
 */
class WSSCD_WC_Subscription_Handler {

	/**
	 * Logger instance.
	 *
	 * @since  1.6.0
	 * @access private
	 * @var    object|null $logger Logger.
	 */
	private ?object $logger;

	/**
	 * Campaign manager instance.
	 *
	 * @since  1.6.0
	 * @access private
	 * @var    WSSCD_Campaign_Manager|null $campaign_manager Campaign manager.
	 */
	private ?WSSCD_Campaign_Manager $campaign_manager;

	/**
	 * Initialize the handler.
	 *
	 * @since 1.6.0
	 * @param object|null                $logger           Logger instance.
	 * @param WSSCD_Campaign_Manager|null $campaign_manager Campaign manager instance.
	 */
	public function __construct( ?object $logger = null, ?WSSCD_Campaign_Manager $campaign_manager = null ) {
		$this->logger           = $logger;
		$this->campaign_manager = $campaign_manager;
	}

	/**
	 * Register subscription-specific hooks.
	 *
	 * @since 1.6.0
	 * @return void
	 */
	public function register_hooks(): void {
		if ( ! self::is_available() ) {
			return;
		}

		if ( wsscd_fs()->is__premium_only() ) {
			// Initialize subscription meta from order items at checkout (Pro).
			add_action( 'woocommerce_checkout_subscription_created', array( $this, 'initialize_subscription_meta__premium_only' ), 10, 3 );

			// Renewal tracking (Pro) — increment count and enforce limits.
			add_action( 'woocommerce_subscription_renewal_payment_complete', array( $this, 'track_renewal__premium_only' ), 10, 1 );

			// Apply discount to renewal orders (Pro) — before payment.
			add_action( 'wcs_renewal_order_created', array( $this, 'apply_renewal_discount__premium_only' ), 10, 2 );
		}

		$this->log( 'debug', 'Subscription handler hooks registered' );
	}

	// ============================================================================
	// Static Utility Methods
	// ============================================================================

	/**
	 * Check if WooCommerce Subscriptions is available.
	 *
	 * @since  1.6.0
	 * @return bool True if WC Subscriptions is active.
	 */
	public static function is_available(): bool {
		return class_exists( 'WC_Subscriptions_Product' );
	}

	/**
	 * Check if a product is a subscription.
	 *
	 * @since  1.6.0
	 * @param  WC_Product|mixed $product Product to check.
	 * @return bool True if subscription product.
	 */
	public static function is_subscription( $product ): bool {
		if ( ! self::is_available() || ! ( $product instanceof WC_Product ) ) {
			return false;
		}

		return WC_Subscriptions_Product::is_subscription( $product );
	}

	/**
	 * Get sign-up fee for a subscription product.
	 *
	 * @since  1.6.0
	 * @param  WC_Product|mixed $product Product.
	 * @return float Sign-up fee amount, 0 if none.
	 */
	public static function get_sign_up_fee( $product ): float {
		if ( ! self::is_subscription( $product ) ) {
			return 0.0;
		}

		$fee = get_post_meta( $product->get_id(), '_subscription_sign_up_fee', true );

		return $fee ? (float) $fee : 0.0;
	}

	/**
	 * Get billing period label for display.
	 *
	 * @since  1.6.0
	 * @param  WC_Product|mixed $product Product.
	 * @return string Period label (e.g., "/ month") or empty string.
	 */
	public static function get_billing_period_label( $product ): string {
		if ( ! self::is_subscription( $product ) ) {
			return '';
		}

		$period   = get_post_meta( $product->get_id(), '_subscription_period', true );
		$interval = (int) get_post_meta( $product->get_id(), '_subscription_period_interval', true );

		if ( empty( $period ) ) {
			return '';
		}

		if ( 1 < $interval ) {
			/* translators: 1: interval number, 2: period name */
			return sprintf( __( ' / %1$d %2$ss', 'smart-cycle-discounts' ), $interval, $period );
		}

		/* translators: %s: period name (day, week, month, year) */
		return sprintf( __( ' / %s', 'smart-cycle-discounts' ), $period );
	}

	// ============================================================================
	// Pro-Only Methods (stripped from free version by Freemius __premium_only suffix)
	// ============================================================================

	/**
	 * Initialize subscription meta from order items at checkout.
	 *
	 * When a subscription is created from checkout, copy the campaign ID from
	 * the order item meta to the subscription object and initialize the renewal
	 * count. Without this, renewal tracking cannot function because the
	 * subscription itself would have no _wsscd_campaign_id meta.
	 *
	 * @since  1.6.0
	 * @param  WC_Subscription $subscription Subscription object.
	 * @param  WC_Order        $order        Parent order.
	 * @param  WC_Cart         $cart         Cart object (unused).
	 * @return void
	 */
	public function initialize_subscription_meta__premium_only( $subscription, $order, $cart ): void {
		if ( ! is_object( $subscription ) || ! is_object( $order ) ) {
			return;
		}

		// Find campaign ID from the parent order's line items.
		$campaign_id = 0;

		foreach ( $order->get_items() as $item ) {
			$item_campaign_id = $item->get_meta( '_wsscd_campaign_id' );

			if ( $item_campaign_id ) {
				$campaign_id = (int) $item_campaign_id;
				break;
			}
		}

		if ( 0 >= $campaign_id ) {
			return;
		}

		// Store campaign ID and initialize renewal count on the subscription.
		$subscription->update_meta_data( '_wsscd_campaign_id', $campaign_id );
		$subscription->update_meta_data( '_wsscd_renewal_count', 0 );
		$subscription->save_meta_data();

		$this->log(
			'info',
			'Subscription meta initialized from checkout',
			array(
				'subscription_id' => $subscription->get_id(),
				'order_id'        => $order->get_id(),
				'campaign_id'     => $campaign_id,
			)
		);
	}

	// ============================================================================
	// Renewal Tracking (Pro)
	// ============================================================================

	/**
	 * Track renewal payment completion.
	 *
	 * Increments the renewal count on the subscription and stops applying
	 * the discount if the renewal limit has been reached.
	 *
	 * @since  1.6.0
	 * @param  WC_Subscription $subscription Subscription object.
	 * @return void
	 */
	public function track_renewal__premium_only( $subscription ): void {
		if ( ! is_object( $subscription ) || ! method_exists( $subscription, 'get_id' ) ) {
			return;
		}

		$campaign_id = $subscription->get_meta( '_wsscd_campaign_id' );

		if ( ! $campaign_id ) {
			return;
		}

		// Increment renewal count.
		$renewal_count = (int) $subscription->get_meta( '_wsscd_renewal_count' );
		$renewal_count++;
		$subscription->update_meta_data( '_wsscd_renewal_count', $renewal_count );
		$subscription->save_meta_data();

		// Check if renewal limit reached.
		$renewal_limit = $this->get_campaign_renewal_limit__premium_only( (int) $campaign_id );

		if ( 0 < $renewal_limit && $renewal_count >= $renewal_limit ) {
			// Discount period complete — remove campaign link.
			$subscription->delete_meta_data( '_wsscd_campaign_id' );
			$subscription->save_meta_data();

			$this->log(
				'info',
				'Subscription renewal limit reached — discount removed',
				array(
					'subscription_id' => $subscription->get_id(),
					'campaign_id'     => $campaign_id,
					'renewal_count'   => $renewal_count,
					'renewal_limit'   => $renewal_limit,
				)
			);
		} else {
			$this->log(
				'debug',
				'Subscription renewal tracked',
				array(
					'subscription_id' => $subscription->get_id(),
					'campaign_id'     => $campaign_id,
					'renewal_count'   => $renewal_count,
				)
			);
		}
	}

	/**
	 * Apply discount to renewal order if within renewal limit.
	 *
	 * @since  1.6.0
	 * @param  WC_Order        $renewal_order Renewal order.
	 * @param  WC_Subscription $subscription  Subscription object.
	 * @return void
	 */
	public function apply_renewal_discount__premium_only( $renewal_order, $subscription ): void {
		if ( ! is_object( $subscription ) || ! is_object( $renewal_order ) ) {
			return;
		}

		$campaign_id = $subscription->get_meta( '_wsscd_campaign_id' );

		if ( ! $campaign_id ) {
			return;
		}

		// Check renewal count against limit.
		$renewal_count = (int) $subscription->get_meta( '_wsscd_renewal_count' );
		$renewal_limit = $this->get_campaign_renewal_limit__premium_only( (int) $campaign_id );

		if ( 0 < $renewal_limit && $renewal_count >= $renewal_limit ) {
			return;
		}

		// Verify campaign still exists and is active.
		if ( ! $this->campaign_manager ) {
			return;
		}

		$campaign = $this->campaign_manager->get_campaign( (int) $campaign_id );

		if ( ! $campaign || 'active' !== $campaign->get_status() ) {
			return;
		}

		// Apply discount to renewal order line items.
		$discount_rules = $campaign->get_discount_rules();
		$discount_type  = $campaign->get_discount_type();
		$discount_value = $campaign->get_discount_value();

		foreach ( $renewal_order->get_items() as $item ) {
			$product_id = $item->get_product_id();
			$product    = wc_get_product( $product_id );

			if ( ! $product ) {
				continue;
			}

			$original_price   = (float) $product->get_regular_price();
			$discounted_price = $this->calculate_simple_discount__premium_only( $original_price, $discount_type, $discount_value );

			if ( $discounted_price < $original_price && 0 < $discounted_price ) {
				$item->set_subtotal( $discounted_price * $item->get_quantity() );
				$item->set_total( $discounted_price * $item->get_quantity() );
				$item->add_meta_data( '_wsscd_campaign_id', $campaign_id, true );
				$item->add_meta_data( '_wsscd_discount_applied', 'yes', true );
				$item->add_meta_data( '_wsscd_original_price', $original_price, true );
				$item->add_meta_data( '_wsscd_discounted_price', $discounted_price, true );
				$item->save();
			}
		}

		$renewal_order->calculate_totals();

		$this->log(
			'info',
			'Discount applied to renewal order',
			array(
				'renewal_order_id' => $renewal_order->get_id(),
				'subscription_id'  => $subscription->get_id(),
				'campaign_id'      => $campaign_id,
				'renewal_count'    => $renewal_count,
			)
		);
	}

	// ============================================================================
	// Private Helpers (Pro)
	// ============================================================================

	/**
	 * Get renewal limit for a campaign.
	 *
	 * @since  1.6.0
	 * @access private
	 * @param  int $campaign_id Campaign ID.
	 * @return int Renewal limit (0 = unlimited).
	 */
	private function get_campaign_renewal_limit__premium_only( int $campaign_id ): int {
		if ( ! $this->campaign_manager ) {
			return 0;
		}

		$campaign = $this->campaign_manager->get_campaign( $campaign_id );

		if ( ! $campaign ) {
			return 0;
		}

		$discount_rules = $campaign->get_discount_rules();

		return isset( $discount_rules['subscription_renewal_limit'] )
			? (int) $discount_rules['subscription_renewal_limit']
			: 0;
	}

	/**
	 * Calculate a simple discount (percentage or fixed).
	 *
	 * Used for renewal orders where the full discount engine context is not needed.
	 *
	 * @since  1.6.0
	 * @access private
	 * @param  float  $price         Original price.
	 * @param  string $discount_type Discount type (percentage, fixed).
	 * @param  float  $discount_value Discount value.
	 * @return float Discounted price.
	 */
	private function calculate_simple_discount__premium_only( float $price, string $discount_type, float $discount_value ): float {
		if ( 'percentage' === $discount_type ) {
			return $price - ( $price * ( $discount_value / 100 ) );
		}

		if ( 'fixed' === $discount_type ) {
			return max( 0, $price - $discount_value );
		}

		return $price;
	}

	/**
	 * Log message.
	 *
	 * @since  1.6.0
	 * @access private
	 * @param  string $level   Log level.
	 * @param  string $message Log message.
	 * @param  array  $context Log context.
	 * @return void
	 */
	private function log( string $level, string $message, array $context = array() ): void {
		if ( $this->logger && method_exists( $this->logger, $level ) ) {
			$this->logger->$level( '[WC_Subscription] ' . $message, $context );
		}
	}
}
