<?php
/**
 * Tiered Strategy Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/discounts/strategies/class-tiered-strategy.php
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Tiered Strategy
 *
 * Implements tiered pricing discount calculations with two application modes:
 * - Per Item: Discount applies to each item's unit price (volume/bulk pricing)
 * - Order Total: Fixed discount applies to order total (promotional discounts)
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/discounts/strategies
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Tiered_Strategy implements SCD_Discount_Strategy_Interface {

	use SCD_Discount_Preview_Trait;

	/**
	 * Calculate tiered discount.
	 *
	 * @since    1.0.0
	 * @param    float $original_price      Original price.
	 * @param    array $discount_config     Strategy configuration.
	 * @param    array $context            Additional context.
	 * @return   SCD_Discount_Result         Calculation result.
	 */
	public function calculate_discount( float $original_price, array $discount_config, array $context = array() ): SCD_Discount_Result {
		try {
			// Defensive validation for NULL or invalid prices
			if ( ! is_numeric( $original_price ) || 0 > $original_price ) {
				return SCD_Discount_Result::no_discount(
					0.0,
					$this->get_strategy_id(),
					'Invalid price: must be a non-negative number'
				);
			}

			$validation_errors = $this->validate_config( $discount_config );
			if ( ! empty( $validation_errors ) ) {
				return SCD_Discount_Result::no_discount( $original_price, $this->get_strategy_id(), 'Invalid configuration: ' . implode( ', ', $validation_errors ) );
			}

			$tiers = $discount_config['tiers'] ?? array();
			if ( empty( $tiers ) ) {
				return SCD_Discount_Result::no_discount( $original_price, $this->get_strategy_id(), 'No tiers configured' );
			}

			$quantity = isset( $context['quantity'] ) ? absint( $context['quantity'] ) : 1;
			if ( 1 > $quantity ) {
				$quantity = 1;
			}

			// Find applicable tier based on quantity
			$applicable_tier = $this->find_applicable_tier( $tiers, $quantity );
			if ( ! $applicable_tier ) {
				return SCD_Discount_Result::no_discount( $original_price, $this->get_strategy_id(), 'No applicable tier found' );
			}

			// Determine application mode (default to per_item for backward compatibility)
			$apply_to = $discount_config['apply_to'] ?? 'per_item';

			if ( 'order_total' === $apply_to ) {
				return $this->calculate_order_total_discount( $original_price, $applicable_tier, $quantity );
			} else {
				return $this->calculate_per_item_discount( $original_price, $applicable_tier, $quantity );
			}
		} catch ( Exception $e ) {
			return SCD_Discount_Result::no_discount( $original_price, $this->get_strategy_id(), $e->getMessage() );
		}
	}

	/**
	 * Calculate per-item discount (volume/bulk pricing mode).
	 *
	 * Each item's unit price is reduced. The more you buy, the lower the per-unit price.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    float $original_price    Original price per item.
	 * @param    array $tier             Applicable tier configuration.
	 * @param    int   $quantity         Quantity of items.
	 * @return   SCD_Discount_Result        Calculation result.
	 */
	private function calculate_per_item_discount( float $original_price, array $tier, int $quantity ): SCD_Discount_Result {
		$discount_type  = $tier['discount_type'] ?? 'percentage';
		$discount_value = floatval( $tier['discount_value'] ?? 0 );

		if ( 'percentage' === $discount_type ) {
			// Apply percentage discount to each item
			$discount_amount  = $this->round_currency( $original_price * ( $discount_value / 100 ) );
			$discounted_price = $this->round_currency( max( 0, $original_price - $discount_amount ) );
		} else {
			// Apply fixed amount discount to each item
			$discounted_price = $this->round_currency( max( 0, $original_price - $discount_value ) );
		}

		$metadata = array(
			'apply_to'            => 'per_item',
			'quantity'            => $quantity,
			'applicable_tier'     => $tier,
			'tier_min_quantity'   => $tier['min_quantity'] ?? 0,
			'tier_discount_type'  => $discount_type,
			'tier_discount_value' => $discount_value,
		);

		return new SCD_Discount_Result( $original_price, $discounted_price, $this->get_strategy_id(), true, $metadata );
	}

	/**
	 * Calculate order total discount (promotional discount mode).
	 *
	 * Fixed or percentage discount applies to the order total when quantity threshold is met.
	 * Example: "Buy 3 items, get $10 off your order"
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    float $original_price    Original price per item.
	 * @param    array $tier             Applicable tier configuration.
	 * @param    int   $quantity         Quantity of items.
	 * @return   SCD_Discount_Result        Calculation result.
	 */
	private function calculate_order_total_discount( float $original_price, array $tier, int $quantity ): SCD_Discount_Result {
		$discount_type  = $tier['discount_type'] ?? 'fixed';
		$discount_value = floatval( $tier['discount_value'] ?? 0 );

		$order_subtotal = $this->round_currency( $original_price * $quantity );

		if ( 'percentage' === $discount_type ) {
			// Percentage of order total
			$total_discount = $this->round_currency( $order_subtotal * ( $discount_value / 100 ) );
		} else {
			// Fixed amount off order total (but never more than subtotal)
			$total_discount = $this->round_currency( min( $discount_value, $order_subtotal ) );
		}

		$final_total               = $this->round_currency( max( 0, $order_subtotal - $total_discount ) );
		$discounted_price_per_item = $this->round_currency( $final_total / $quantity );

		$metadata = array(
			'apply_to'            => 'order_total',
			'quantity'            => $quantity,
			'order_subtotal'      => $order_subtotal,
			'total_discount'      => $total_discount,
			'applicable_tier'     => $tier,
			'tier_min_quantity'   => $tier['min_quantity'] ?? 0,
			'tier_discount_type'  => $discount_type,
			'tier_discount_value' => $discount_value,
		);

		return new SCD_Discount_Result( $original_price, $discounted_price_per_item, $this->get_strategy_id(), true, $metadata );
	}

	/**
	 * Validate tiered configuration.
	 *
	 * @since    1.0.0
	 * @param    array $discount_config    Configuration to validate.
	 * @return   array                       Validation errors (empty if valid).
	 */
	public function validate_config( array $discount_config ): array {
		$errors = array();

		$apply_to = $discount_config['apply_to'] ?? 'per_item';
		if ( ! in_array( $apply_to, array( 'per_item', 'order_total' ), true ) ) {
			$errors[] = __( 'Apply to must be either "per_item" or "order_total"', 'smart-cycle-discounts' );
		}

		if ( ! isset( $discount_config['tiers'] ) || ! is_array( $discount_config['tiers'] ) ) {
			$errors[] = __( 'Tiers configuration is required and must be an array', 'smart-cycle-discounts' );
		} else {
			$tiers = $discount_config['tiers'];

			if ( empty( $tiers ) ) {
				$errors[] = __( 'At least one tier must be configured', 'smart-cycle-discounts' );
			} else {
				// Prevent excessive tier counts that impact performance
				if ( 20 < count( $tiers ) ) {
					$errors[] = __( 'Maximum 20 tiers allowed (performance limit)', 'smart-cycle-discounts' );
				}

				foreach ( $tiers as $index => $tier ) {
					$tier_errors = $this->validate_tier( $tier, $index, $apply_to );
					$errors      = array_merge( $errors, $tier_errors );
				}

				$quantities = array();
				foreach ( $tiers as $tier ) {
					if ( isset( $tier['min_quantity'] ) ) {
						$quantities[] = $tier['min_quantity'];
					}
				}

				if ( ! empty( $quantities ) ) {
					$sorted_quantities = $quantities;
					sort( $sorted_quantities );

					if ( $sorted_quantities !== $quantities ) {
						$errors[] = __( 'Tier quantities must be in ascending order', 'smart-cycle-discounts' );
					}
				}
			}
		}

		return $errors;
	}

	/**
	 * Validate individual tier configuration.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array  $tier        Tier configuration.
	 * @param    int    $index       Tier index.
	 * @param    string $apply_to    Application mode (per_item or order_total).
	 * @return   array                  Validation errors.
	 */
	private function validate_tier( array $tier, int $index, string $apply_to = 'per_item' ): array {
		$errors     = array();
		$tier_label = sprintf( __( 'Tier %d', 'smart-cycle-discounts' ), $index + 1 );

		if ( ! isset( $tier['min_quantity'] ) || ! is_numeric( $tier['min_quantity'] ) ) {
			$errors[] = sprintf( __( '%s: Minimum quantity is required and must be numeric', 'smart-cycle-discounts' ), $tier_label );
		} else {
			$min_quantity = intval( $tier['min_quantity'] );

			if ( 0 > $min_quantity ) {
				$errors[] = sprintf( __( '%s: Minimum quantity must be non-negative', 'smart-cycle-discounts' ), $tier_label );
			} elseif ( 0 === $min_quantity ) {
				$errors[] = sprintf( __( '%s: Minimum quantity cannot be zero', 'smart-cycle-discounts' ), $tier_label );
			} elseif ( 1000000 < $min_quantity ) {
				$errors[] = sprintf( __( '%s: Minimum quantity exceeds maximum allowed (1,000,000)', 'smart-cycle-discounts' ), $tier_label );
			}
		}

		if ( ! isset( $tier['discount_type'] ) || ! in_array( $tier['discount_type'], array( 'percentage', 'fixed' ), true ) ) {
			$errors[] = sprintf( __( '%s: Discount type must be either "percentage" or "fixed"', 'smart-cycle-discounts' ), $tier_label );
		}

		if ( ! isset( $tier['discount_value'] ) || ! is_numeric( $tier['discount_value'] ) ) {
			$errors[] = sprintf( __( '%s: Discount value is required and must be numeric', 'smart-cycle-discounts' ), $tier_label );
		} else {
			$discount_value = floatval( $tier['discount_value'] );
			$discount_type  = $tier['discount_type'] ?? 'percentage';

			if ( 0 > $discount_value ) {
				$errors[] = sprintf( __( '%s: Discount value must be non-negative', 'smart-cycle-discounts' ), $tier_label );
			} elseif ( 0 === $discount_value ) {
				$errors[] = sprintf( __( '%s: Discount value cannot be zero', 'smart-cycle-discounts' ), $tier_label );
			}

			if ( 'percentage' === $discount_type ) {
				if ( 100 < $discount_value ) {
					$errors[] = sprintf( __( '%s: Percentage discount cannot exceed 100%%', 'smart-cycle-discounts' ), $tier_label );
				} elseif ( 0.01 > $discount_value ) {
					$errors[] = sprintf( __( '%s: Percentage discount must be at least 0.01%%', 'smart-cycle-discounts' ), $tier_label );
				}
			} elseif ( 'fixed' === $discount_type ) {
				if ( 1000000 < $discount_value ) {
					$errors[] = sprintf( __( '%s: Fixed discount amount exceeds maximum allowed', 'smart-cycle-discounts' ), $tier_label );
				} elseif ( 0.01 > $discount_value ) {
					$errors[] = sprintf( __( '%s: Fixed discount must be at least 0.01', 'smart-cycle-discounts' ), $tier_label );
				} elseif ( 'per_item' === $apply_to && 10000 < $discount_value ) {
					// Warning: High fixed discount per item could exceed product prices
					$errors[] = sprintf(
						__( '%1$s: Fixed discount of %2$s per item seems very high. Consider using "Order Total" mode or percentage instead.', 'smart-cycle-discounts' ),
						$tier_label,
						function_exists( 'wc_price' ) ? wc_price( $discount_value ) : '$' . number_format( $discount_value, 2 )
					);
				}
			}
		}

		return $errors;
	}

	/**
	 * Get strategy identifier.
	 *
	 * @since    1.0.0
	 * @return   string    Strategy identifier.
	 */
	public function get_strategy_id(): string {
		return 'tiered';
	}

	/**
	 * Get strategy name.
	 *
	 * @since    1.0.0
	 * @return   string    Human-readable strategy name.
	 */
	public function get_strategy_name(): string {
		return __( 'Tiered Pricing', 'smart-cycle-discounts' );
	}

	/**
	 * Get strategy description.
	 *
	 * @since    1.0.0
	 * @return   string    Strategy description.
	 */
	public function get_strategy_description(): string {
		return __( 'Apply different discount rates based on quantity purchased', 'smart-cycle-discounts' );
	}

	/**
	 * Round currency value to 2 decimal places.
	 *
	 * CRITICAL FIX: Prevents float precision errors in currency calculations.
	 * Example: Without rounding, (100.0 * 15) / 100 = 14.999999999999998
	 *
	 * @since    1.0.0
	 * @param    float $value    Value to round.
	 * @return   float             Rounded value.
	 */
	private function round_currency( float $value ): float {
		return round( $value, 2 );
	}

	/**
	 * Check if strategy supports given context.
	 *
	 * @since    1.0.0
	 * @param    array $context    Context to check.
	 * @return   bool                 True if strategy supports context.
	 */
	public function supports_context( array $context ): bool {
		// Tiered pricing can work with or without quantity/amount context
		return true;
	}

	/**
	 * Get minimum discount amount.
	 *
	 * @since    1.0.0
	 * @param    array $discount_config    Discount configuration.
	 * @return   float                        Minimum discount amount.
	 */
	public function get_minimum_discount( array $discount_config ): float {
		$tiers = $discount_config['tiers'] ?? array();
		if ( empty( $tiers ) ) {
			return 0.0;
		}

		// Find the tier with the smallest discount
		$min_discount = PHP_FLOAT_MAX;
		foreach ( $tiers as $tier ) {
			$discount_value = floatval( $tier['discount_value'] ?? 0 );
			if ( 'percentage' === $tier['discount_type'] ) {
				$min_discount = min( $min_discount, $discount_value );
			}
		}

		return PHP_FLOAT_MAX === $min_discount ? 0.0 : $min_discount;
	}

	/**
	 * Get maximum discount amount.
	 *
	 * @since    1.0.0
	 * @param    array $discount_config    Discount configuration.
	 * @return   float                        Maximum discount amount.
	 */
	public function get_maximum_discount( array $discount_config ): float {
		$tiers = $discount_config['tiers'] ?? array();
		if ( empty( $tiers ) ) {
			return 0.0;
		}

		// Find the tier with the largest discount
		$max_discount = 0.0;
		foreach ( $tiers as $tier ) {
			$discount_value = floatval( $tier['discount_value'] ?? 0 );
			if ( 'percentage' === $tier['discount_type'] ) {
				$max_discount = max( $max_discount, $discount_value );
			}
		}

		return $max_discount;
	}

	/**
	 * Find applicable tier for given value.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $tiers    Available tiers.
	 * @param    float $value    Value to match against.
	 * @return   array|null         Applicable tier or null.
	 */
	private function find_applicable_tier( array $tiers, float $value ): ?array {
		$applicable_tier = null;

		usort(
			$tiers,
			function ( $a, $b ) {
				// Use numeric comparison to handle both int and float properly
				$a_qty = (float) ( $a['min_quantity'] ?? 0 );
				$b_qty = (float) ( $b['min_quantity'] ?? 0 );
				return $b_qty <=> $a_qty;
			}
		);

		foreach ( $tiers as $tier ) {
			// Cast to float for consistent comparison
			$min_quantity = (float) ( $tier['min_quantity'] ?? 0 );
			if ( $min_quantity <= $value ) {
				$applicable_tier = $tier;
				break;
			}
		}

		return $applicable_tier;
	}

	/**
	 * Get tier description for display.
	 *
	 * @since    1.0.0
	 * @param    array  $tier      Tier configuration.
	 * @param    string $apply_to  Application mode (per_item or order_total).
	 * @return   string              Tier description.
	 */
	public function get_tier_description( array $tier, string $apply_to = 'per_item' ): string {
		$min_quantity   = $tier['min_quantity'] ?? 0;
		$discount_type  = $tier['discount_type'] ?? 'percentage';
		$discount_value = $tier['discount_value'] ?? 0;

		if ( 'order_total' === $apply_to ) {
			// Order total mode
			if ( 'percentage' === $discount_type ) {
				return sprintf(
					__( '%1$s%% off your order when purchasing %2$s or more items', 'smart-cycle-discounts' ),
					number_format( $discount_value, 1 ),
					number_format( $min_quantity )
				);
			} else {
				return sprintf(
					__( '%1$s off your order when purchasing %2$s or more items', 'smart-cycle-discounts' ),
					wc_price( $discount_value ),
					number_format( $min_quantity )
				);
			}
		} else {
			// Per item mode
			if ( 'percentage' === $discount_type ) {
				return sprintf(
					__( '%1$s%% off each item when purchasing %2$s or more', 'smart-cycle-discounts' ),
					number_format( $discount_value, 1 ),
					number_format( $min_quantity )
				);
			} else {
				return sprintf(
					__( '%1$s off each item when purchasing %2$s or more', 'smart-cycle-discounts' ),
					wc_price( $discount_value ),
					number_format( $min_quantity )
				);
			}
		}
	}

	/**
	 * Get configuration schema for admin interface.
	 *
	 * @since    1.0.0
	 * @return   array    Configuration schema.
	 */
	public function get_config_schema(): array {
		return array(
			'apply_to' => array(
				'type'        => 'radio',
				'label'       => __( 'Apply Discount To', 'smart-cycle-discounts' ),
				'description' => __( 'Choose how the discount is applied', 'smart-cycle-discounts' ),
				'options'     => array(
					'per_item'    => __( 'Each Item - Unit price decreases (volume/bulk pricing)', 'smart-cycle-discounts' ),
					'order_total' => __( 'Order Total - Fixed discount on order (promotional)', 'smart-cycle-discounts' ),
				),
				'default'     => 'per_item',
				'required'    => true,
			),
			'tiers'    => array(
				'type'        => 'repeater',
				'label'       => __( 'Discount Tiers', 'smart-cycle-discounts' ),
				'description' => __( 'Configure discount tiers with minimum quantities and discount values', 'smart-cycle-discounts' ),
				'fields'      => array(
					'min_quantity'   => array(
						'type'        => 'number',
						'label'       => __( 'Minimum Quantity', 'smart-cycle-discounts' ),
						'description' => __( 'Minimum quantity to qualify for this tier', 'smart-cycle-discounts' ),
						'min'         => 1,
						'step'        => 1,
						'required'    => true,
					),
					'discount_type'  => $this->get_discount_type_field_schema(),
					'discount_value' => $this->get_discount_value_field_schema(),
				),
				'min_items'   => 1,
				'required'    => true,
			),
		);
	}

	/**
	 * Get examples for this strategy.
	 *
	 * @since    1.0.0
	 * @return   array    Strategy examples.
	 */
	public function get_examples(): array {
		return array(
			array(
				'title'       => __( 'Quantity-Based Tiers', 'smart-cycle-discounts' ),
				'description' => __( '5% off for 5+ items, 10% off for 10+ items', 'smart-cycle-discounts' ),
				'config'      => array(
					'tier_type' => 'quantity',
					'tiers'     => array(
						array(
							'min_quantity'   => 5,
							'discount_type'  => 'percentage',
							'discount_value' => 5.0,
						),
						array(
							'min_quantity'   => 10,
							'discount_type'  => 'percentage',
							'discount_value' => 10.0,
						),
					),
				),
			),
			array(
				'title'       => __( 'Amount-Based Tiers', 'smart-cycle-discounts' ),
				'description' => __( '$5 off orders over $50, $15 off orders over $100', 'smart-cycle-discounts' ),
				'config'      => array(
					'tier_type' => 'amount',
					'tiers'     => array(
						array(
							'min_quantity'   => 50,
							'discount_type'  => 'fixed',
							'discount_value' => 5.0,
						),
						array(
							'min_quantity'   => 100,
							'discount_type'  => 'fixed',
							'discount_value' => 15.0,
						),
					),
				),
			),
			array(
				'title'       => __( 'Progressive Percentage Tiers', 'smart-cycle-discounts' ),
				'description' => __( 'Increasing discounts for larger quantities', 'smart-cycle-discounts' ),
				'config'      => array(
					'tier_type' => 'quantity',
					'tiers'     => array(
						array(
							'min_quantity'   => 3,
							'discount_type'  => 'percentage',
							'discount_value' => 5.0,
						),
						array(
							'min_quantity'   => 6,
							'discount_type'  => 'percentage',
							'discount_value' => 10.0,
						),
						array(
							'min_quantity'   => 12,
							'discount_type'  => 'percentage',
							'discount_value' => 15.0,
						),
					),
				),
			),
		);
	}

	/**
	 * Get tier breakdown for display.
	 *
	 * @since    1.0.0
	 * @param    SCD_Discount_Result $result    Discount result.
	 * @return   array                            Tier breakdown.
	 */
	public function get_tier_breakdown( SCD_Discount_Result $result ): array {
		if ( ! $result->is_applied() ) {
			return array();
		}

		$metadata = $result->get_metadata();
		$tier     = $metadata['applicable_tier'] ?? array();
		$apply_to = $metadata['apply_to'] ?? 'per_item';

		if ( empty( $tier ) ) {
			return array();
		}

		return array(
			'type'           => 'tiered',
			'apply_to'       => $apply_to,
			'min_quantity'   => $metadata['tier_min_quantity'] ?? 0,
			'quantity'       => $metadata['quantity'] ?? 0,
			'discount_type'  => $metadata['tier_discount_type'] ?? 'percentage',
			'discount_value' => $metadata['tier_discount_value'] ?? 0,
			'description'    => $this->get_tier_description( $tier, $apply_to ),
		);
	}

	/**
	 * Check if quantity/amount qualifies for any tier.
	 *
	 * @since    1.0.0
	 * @param    float $value     Value to check.
	 * @param    array $config    Strategy configuration.
	 * @return   bool               True if qualifies.
	 */
	public function qualifies_for_discount( float $value, array $config ): bool {
		$tiers = $config['tiers'] ?? array();
		if ( empty( $tiers ) ) {
			return false;
		}

		$quantities = array();
		foreach ( $tiers as $tier ) {
			if ( isset( $tier['min_quantity'] ) && is_numeric( $tier['min_quantity'] ) ) {
				$quantities[] = (float) $tier['min_quantity'];
			}
		}

		if ( empty( $quantities ) ) {
			return false;
		}

		$min_quantity = min( $quantities );
		return $min_quantity <= $value;
	}

	/**
	 * Get next tier information for upselling.
	 *
	 * @since    1.0.0
	 * @param    float $current_value    Current quantity/amount.
	 * @param    array $config          Strategy configuration.
	 * @return   array|null                Next tier info or null.
	 */
	public function get_next_tier( float $current_value, array $config ): ?array {
		$tiers    = $config['tiers'] ?? array();
		$apply_to = $config['apply_to'] ?? 'per_item';

		if ( empty( $tiers ) ) {
			return null;
		}

		usort(
			$tiers,
			function ( $a, $b ) {
				return floatval( $a['min_quantity'] ) <=> floatval( $b['min_quantity'] );
			}
		);

		foreach ( $tiers as $tier ) {
			$min_quantity = floatval( $tier['min_quantity'] );
			if ( $current_value < $min_quantity ) {
				return array(
					'min_quantity'      => $min_quantity,
					'discount_type'     => $tier['discount_type'],
					'discount_value'    => $tier['discount_value'],
					'additional_needed' => $min_quantity - $current_value,
					'description'       => $this->get_tier_description( $tier, $apply_to ),
				);
			}
		}

		return null;
	}
}
