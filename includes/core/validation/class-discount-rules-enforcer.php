<?php
/**
 * Discount Rules Enforcer Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/validation/class-discount-rules-enforcer.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Discount Rules Enforcer
 *
 * Runtime enforcement of Configure Discount Rules constraints.
 * Integrates with existing validation system but focuses on runtime checks
 * during discount calculation rather than wizard validation.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/validation
 * @author     Webstepper <contact@webstepper.io>
 */
class SCD_Discount_Rules_Enforcer {

	/**
	 * Customer usage manager.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Customer_Usage_Manager|null    $usage_manager    Usage manager.
	 */
	private ?SCD_Customer_Usage_Manager $usage_manager;

	/**
	 * Logger instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      object|null    $logger    Logger.
	 */
	private ?object $logger;

	/**
	 * Initialize the enforcer.
	 *
	 * @since    1.0.0
	 * @param    SCD_Customer_Usage_Manager|null $usage_manager    Usage manager.
	 * @param    object|null                     $logger           Logger.
	 */
	public function __construct( ?SCD_Customer_Usage_Manager $usage_manager = null, ?object $logger = null ) {
		$this->usage_manager = $usage_manager;
		$this->logger        = $logger;
	}

	/**
	 * Check if discount can be applied based on all rules.
	 *
	 * @since    1.0.0
	 * @param    array      $discount_rules    Discount rules from campaign.
	 * @param    array      $context           Context (product, quantity, cart_total, etc).
	 * @param    int|null   $campaign_id       Campaign ID for usage tracking.
	 * @return   array                            Check result with 'allowed' and 'reason'.
	 */
	public function can_apply_discount( array $discount_rules, array $context, ?int $campaign_id = null ): array {
		// Check minimum order amount
		$min_order_check = $this->check_minimum_order_amount( $discount_rules, $context );
		if ( ! $min_order_check['allowed'] ) {
			return $min_order_check;
		}

		// Check minimum quantity
		$min_qty_check = $this->check_minimum_quantity( $discount_rules, $context );
		if ( ! $min_qty_check['allowed'] ) {
			return $min_qty_check;
		}

		// Check sale items eligibility
		$sale_items_check = $this->check_sale_items( $discount_rules, $context );
		if ( ! $sale_items_check['allowed'] ) {
			return $sale_items_check;
		}

		// Check usage limits (requires campaign_id)
		if ( $campaign_id && $this->usage_manager ) {
			$usage_check = $this->check_usage_limits( $discount_rules, $campaign_id );
			if ( ! $usage_check['allowed'] ) {
				return $usage_check;
			}
		}

		return array(
			'allowed' => true,
			'reason'  => null,
		);
	}

	/**
	 * Check minimum order amount requirement.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $discount_rules    Discount rules.
	 * @param    array $context           Context with cart_total.
	 * @return   array                       Check result.
	 */
	private function check_minimum_order_amount( array $discount_rules, array $context ): array {
		$minimum = floatval( $discount_rules['minimum_order_amount'] ?? 0 );

		if ( 0 >= $minimum ) {
			return array( 'allowed' => true );
		}

		$cart_total = floatval( $context['cart_total'] ?? 0 );

		if ( $minimum > $cart_total ) {
			$this->log(
				'debug',
				'Discount blocked by minimum order amount',
				array(
					'cart_total' => $cart_total,
					'minimum'    => $minimum,
				)
			);

			return array(
				'allowed' => false,
				'reason'  => sprintf(
					/* translators: %s: minimum amount */
					__( 'Minimum order amount of %s required', 'smart-cycle-discounts' ),
					wc_price( $minimum )
				),
			);
		}

		return array( 'allowed' => true );
	}

	/**
	 * Check minimum quantity requirement.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $discount_rules    Discount rules.
	 * @param    array $context           Context with quantity.
	 * @return   array                       Check result.
	 */
	private function check_minimum_quantity( array $discount_rules, array $context ): array {
		$minimum = intval( $discount_rules['minimum_quantity'] ?? 0 );

		if ( 0 >= $minimum ) {
			return array( 'allowed' => true );
		}

		$quantity = intval( $context['quantity'] ?? 1 );

		if ( $minimum > $quantity ) {
			$this->log(
				'debug',
				'Discount blocked by minimum quantity',
				array(
					'quantity' => $quantity,
					'minimum'  => $minimum,
				)
			);

			return array(
				'allowed' => false,
				'reason'  => sprintf(
					/* translators: %d: minimum quantity */
					_n(
						'Minimum %d item required',
						'Minimum %d items required',
						$minimum,
						'smart-cycle-discounts'
					),
					$minimum
				),
			);
		}

		return array( 'allowed' => true );
	}

	/**
	 * Check sale items eligibility.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $discount_rules    Discount rules.
	 * @param    array $context           Context with product.
	 * @return   array                       Check result.
	 */
	private function check_sale_items( array $discount_rules, array $context ): array {
		$apply_to_sale = isset( $discount_rules['apply_to_sale_items'] ) ?
			(bool) $discount_rules['apply_to_sale_items'] : true;

		// If sale items are allowed, skip check
		if ( $apply_to_sale ) {
			return array( 'allowed' => true );
		}

		// Check if product is on sale - support both WC_Product object and boolean flag
		$is_on_sale = false;

		// Option 1: Direct boolean flag (for testing)
		if ( isset( $context['is_on_sale'] ) ) {
			$is_on_sale = (bool) $context['is_on_sale'];
		}
		// Option 2: WC_Product object (for production)
		elseif ( isset( $context['product'] ) && is_a( $context['product'], 'WC_Product' ) ) {
			$is_on_sale = $context['product']->is_on_sale();
		}

		if ( $is_on_sale ) {
			$product_id = isset( $context['product'] ) && is_a( $context['product'], 'WC_Product' )
				? $context['product']->get_id()
				: ( $context['product_id'] ?? 0 );

			$this->log(
				'debug',
				'Discount blocked - product is on sale',
				array( 'product_id' => $product_id )
			);

			return array(
				'allowed' => false,
				'reason'  => __( 'Cannot be applied to sale items', 'smart-cycle-discounts' ),
			);
		}

		return array( 'allowed' => true );
	}

	/**
	 * Check usage limits.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $discount_rules    Discount rules.
	 * @param    int   $campaign_id       Campaign ID.
	 * @return   array                       Check result.
	 */
	private function check_usage_limits( array $discount_rules, int $campaign_id ): array {
		if ( ! $this->usage_manager ) {
			return array( 'allowed' => true );
		}

		// Check per-customer limit
		$per_customer_limit = intval( $discount_rules['usage_limit_per_customer'] ?? 0 );
		if ( 0 < $per_customer_limit ) {
			$customer_check = $this->usage_manager->validate_customer_usage(
				$campaign_id,
				array( 'max_uses_per_customer' => $per_customer_limit )
			);

			if ( ! $customer_check['valid'] ) {
				return array(
					'allowed' => false,
					'reason'  => $customer_check['error'] ?? __( 'Usage limit reached', 'smart-cycle-discounts' ),
				);
			}
		}

		// Check total usage limit per cycle
		$total_limit = intval( $discount_rules['total_usage_limit'] ?? 0 );
		if ( 0 < $total_limit ) {
			$total_usage = $this->usage_manager->get_total_usage( $campaign_id );
			if ( $total_limit <= $total_usage ) {
				return array(
					'allowed' => false,
					'reason'  => __( 'Discount usage limit reached', 'smart-cycle-discounts' ),
				);
			}
		}

		// Check lifetime usage cap
		$lifetime_cap = intval( $discount_rules['lifetime_usage_cap'] ?? 0 );
		if ( 0 < $lifetime_cap ) {
			$lifetime_usage = $this->usage_manager->get_lifetime_usage( $campaign_id );
			if ( $lifetime_cap <= $lifetime_usage ) {
				return array(
					'allowed' => false,
					'reason'  => __( 'Discount lifetime limit reached', 'smart-cycle-discounts' ),
				);
			}
		}

		return array( 'allowed' => true );
	}

	/**
	 * Apply maximum discount cap to calculated amount.
	 *
	 * @since    1.0.0
	 * @param    float $discount_amount    Calculated discount amount.
	 * @param    array $discount_rules     Discount rules.
	 * @return   float                        Capped discount amount.
	 */
	public function apply_max_discount_cap( float $discount_amount, array $discount_rules ): float {
		$max_discount = floatval( $discount_rules['max_discount_amount'] ?? 0 );

		if ( 0 >= $max_discount ) {
			return $discount_amount;
		}

		if ( $max_discount < $discount_amount ) {
			$this->log(
				'debug',
				'Discount capped at maximum',
				array(
					'original_discount' => $discount_amount,
					'capped_discount'   => $max_discount,
				)
			);

			return $max_discount;
		}

		return $discount_amount;
	}

	/**
	 * Log message.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $level      Log level.
	 * @param    string $message    Message.
	 * @param    array  $context    Context.
	 * @return   void
	 */
	private function log( string $level, string $message, array $context = array() ): void {
		if ( $this->logger && method_exists( $this->logger, $level ) ) {
			$this->logger->$level( '[Discount_Rules_Enforcer] ' . $message, $context );
		}
	}
}
