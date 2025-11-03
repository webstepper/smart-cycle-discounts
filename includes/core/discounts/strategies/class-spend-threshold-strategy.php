<?php
/**
 * Spend Threshold Strategy Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/discounts/strategies/class-spend-threshold-strategy.php
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
 * Spend Threshold Strategy
 *
 * Implements spend threshold discount calculations based on cart total.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/discounts/strategies
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Spend_Threshold_Strategy implements SCD_Discount_Strategy_Interface {

	use SCD_Discount_Preview_Trait;

	/**
	 * Calculate spend threshold discount.
	 *
	 * @since    1.0.0
	 * @param    float $original_price      Original price.
	 * @param    array $discount_config     Strategy configuration.
	 * @param    array $context            Additional context.
	 * @return   SCD_Discount_Result         Calculation result.
	 */
	public function calculate_discount( float $original_price, array $discount_config, array $context = array() ): SCD_Discount_Result {
		try {
			// CRITICAL: Defensive validation for NULL or invalid prices
			if ( ! is_numeric( $original_price ) || $original_price < 0 ) {
				return SCD_Discount_Result::no_discount(
					0.0,
					$this->get_strategy_id(),
					'Invalid price: must be a non-negative number'
				);
			}

			// Validate configuration
			$validation_errors = $this->validate_config( $discount_config );
			if ( ! empty( $validation_errors ) ) {
				return SCD_Discount_Result::no_discount( $original_price, $this->get_strategy_id(), 'Invalid configuration' );
			}

			// Get thresholds
			$thresholds = $discount_config['thresholds'] ?? array();

			if ( empty( $thresholds ) ) {
				return SCD_Discount_Result::no_discount( $original_price, $this->get_strategy_id(), 'No thresholds configured' );
			}

			// Get cart total from context
			$cart_total = $this->get_cart_total( $context );

			// Find applicable threshold
			$applicable_threshold = $this->find_applicable_threshold( $thresholds, $cart_total );

			if ( ! $applicable_threshold ) {
				return SCD_Discount_Result::no_discount( $original_price, $this->get_strategy_id(), 'No applicable threshold found' );
			}

			// Calculate discount based on threshold
			$discounted_price = $this->calculate_threshold_discount( $original_price, $applicable_threshold );

			// Add threshold-specific metadata
			$metadata = array(
				'cart_total'               => $cart_total,
				'applicable_threshold'     => $applicable_threshold,
				'threshold_amount'         => $applicable_threshold['threshold'],
				'threshold_discount_type'  => $applicable_threshold['discount_type'],
				'threshold_discount_value' => $applicable_threshold['discount_value'],
			);

			return new SCD_Discount_Result( $original_price, $discounted_price, $this->get_strategy_id(), true, $metadata );

		} catch ( Exception $e ) {
			return SCD_Discount_Result::no_discount( $original_price, $this->get_strategy_id(), $e->getMessage() );
		}
	}

	/**
	 * Validate spend threshold configuration.
	 *
	 * @since    1.0.0
	 * @param    array $discount_config    Configuration to validate.
	 * @return   array                       Validation errors (empty if valid).
	 */
	public function validate_config( array $discount_config ): array {
		$errors = array();

		// Validate thresholds
		if ( ! isset( $discount_config['thresholds'] ) || ! is_array( $discount_config['thresholds'] ) ) {
			$errors[] = __( 'Thresholds configuration is required and must be an array', 'smart-cycle-discounts' );
		} else {
			$thresholds = $discount_config['thresholds'];

			if ( empty( $thresholds ) ) {
				$errors[] = __( 'At least one threshold must be configured', 'smart-cycle-discounts' );
			} else {
				// HIGH: Prevent excessive threshold counts that impact performance
				if ( count( $thresholds ) > 20 ) {
					$errors[] = __( 'Maximum 20 thresholds allowed (performance limit)', 'smart-cycle-discounts' );
				}

				foreach ( $thresholds as $index => $threshold ) {
					$threshold_errors = $this->validate_threshold( $threshold, $index );
					$errors           = array_merge( $errors, $threshold_errors );
				}

				// Validate threshold amounts are in ascending order
				$threshold_amounts = array_column( $thresholds, 'threshold' );
				$sorted_amounts    = $threshold_amounts;
				sort( $sorted_amounts );

				if ( $threshold_amounts !== $sorted_amounts ) {
					$errors[] = __( 'Threshold amounts must be in ascending order', 'smart-cycle-discounts' );
				}
			}
		}

		return $errors;
	}

	/**
	 * Validate individual threshold configuration.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $threshold     Threshold configuration.
	 * @param    int   $index        Threshold index.
	 * @return   array                  Validation errors.
	 */
	private function validate_threshold( array $threshold, int $index ): array {
		$errors          = array();
		$threshold_label = sprintf( __( 'Threshold %d', 'smart-cycle-discounts' ), $index + 1 );

		// Validate threshold amount
		if ( ! isset( $threshold['threshold'] ) || ! is_numeric( $threshold['threshold'] ) ) {
			$errors[] = sprintf( __( '%s: Threshold amount is required and must be numeric', 'smart-cycle-discounts' ), $threshold_label );
		} elseif ( floatval( $threshold['threshold'] ) < 0 ) {
			$errors[] = sprintf( __( '%s: Threshold amount must be non-negative', 'smart-cycle-discounts' ), $threshold_label );
		}

		// Validate discount type
		if ( ! isset( $threshold['discount_type'] ) || ! in_array( $threshold['discount_type'], array( 'percentage', 'fixed' ), true ) ) {
			$errors[] = sprintf( __( '%s: Discount type must be either "percentage" or "fixed"', 'smart-cycle-discounts' ), $threshold_label );
		}

		// Validate discount value
		if ( ! isset( $threshold['discount_value'] ) || ! is_numeric( $threshold['discount_value'] ) ) {
			$errors[] = sprintf( __( '%s: Discount value is required and must be numeric', 'smart-cycle-discounts' ), $threshold_label );
		} else {
			$discount_value   = floatval( $threshold['discount_value'] );
			$discount_type    = $threshold['discount_type'] ?? '';
			$threshold_amount = isset( $threshold['threshold'] ) ? floatval( $threshold['threshold'] ) : 0;

			if ( $discount_value < 0 ) {
				$errors[] = sprintf( __( '%s: Discount value must be non-negative', 'smart-cycle-discounts' ), $threshold_label );
			}

			if ( 'percentage' === $discount_type && $discount_value > 100 ) {
				$errors[] = sprintf( __( '%s: Percentage discount cannot exceed 100%%', 'smart-cycle-discounts' ), $threshold_label );
			}

			// Warning: Low percentage discount
			if ( 'percentage' === $discount_type && $discount_value > 0 && $discount_value < 5 ) {
				$errors[] = sprintf(
					__( '%s: Percentage discount less than 5%% may not effectively incentivize purchases. Consider increasing to at least 5%% or using fixed amount.', 'smart-cycle-discounts' ),
					$threshold_label
				);
			}

			// Warning: Fixed discount that's too high relative to threshold
			if ( 'fixed' === $discount_type && $threshold_amount > 0 && $discount_value / $threshold_amount > 0.5 ) {
				$errors[] = sprintf(
					__( '%s: Discount is more than 50%% of threshold amount. Consider using percentage discount for better scaling.', 'smart-cycle-discounts' ),
					$threshold_label
				);
			}

			// Warning: Very low threshold amount
			if ( $threshold_amount > 0 && $threshold_amount < 10 ) {
				$errors[] = sprintf(
					__( '%1$s: Threshold amount is very low (%2$s). Consider setting a higher threshold to encourage meaningful spending.', 'smart-cycle-discounts' ),
					$threshold_label,
					wc_price( $threshold_amount )
				);
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
		return 'spend_threshold';
	}

	/**
	 * Get strategy name.
	 *
	 * @since    1.0.0
	 * @return   string    Human-readable strategy name.
	 */
	public function get_strategy_name(): string {
		return __( 'Spend Threshold', 'smart-cycle-discounts' );
	}

	/**
	 * Get strategy description.
	 *
	 * @since    1.0.0
	 * @return   string    Strategy description.
	 */
	public function get_strategy_description(): string {
		return __( 'Apply different discount rates based on cart total amount', 'smart-cycle-discounts' );
	}

	/**
	 * Round currency value to 2 decimal places.
	 *
	 * CRITICAL FIX: Prevents float precision errors in currency calculations.
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
		// Spend threshold can work with or without cart total context
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
		$thresholds = $discount_config['thresholds'] ?? array();
		if ( empty( $thresholds ) ) {
			return 0.0;
		}

		// Find the threshold with the smallest discount
		$min_discount = PHP_FLOAT_MAX;
		foreach ( $thresholds as $threshold ) {
			$discount_value = floatval( $threshold['discount_value'] ?? 0 );
			if ( 'percentage' === $threshold['discount_type'] ) {
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
		$thresholds = $discount_config['thresholds'] ?? array();
		if ( empty( $thresholds ) ) {
			return 0.0;
		}

		// Find the threshold with the largest discount
		$max_discount = 0.0;
		foreach ( $thresholds as $threshold ) {
			$discount_value = floatval( $threshold['discount_value'] ?? 0 );
			if ( 'percentage' === $threshold['discount_type'] ) {
				$max_discount = max( $max_discount, $discount_value );
			}
		}

		return $max_discount;
	}

	/**
	 * Get cart total for threshold matching.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $context    Context data.
	 * @return   float                 Cart total.
	 */
	private function get_cart_total( array $context ): float {
		// Try to get cart total from context
		if ( isset( $context['cart_total'] ) ) {
			return floatval( $context['cart_total'] );
		}

		// Try to get from WooCommerce cart
		if ( function_exists( 'WC' ) && WC()->cart ) {
			return floatval( WC()->cart->get_subtotal() );
		}

		return 0.0;
	}

	/**
	 * Find applicable threshold for given cart total.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $thresholds    Available thresholds.
	 * @param    float $cart_total    Cart total to match against.
	 * @return   array|null              Applicable threshold or null.
	 */
	private function find_applicable_threshold( array $thresholds, float $cart_total ): ?array {
		$applicable_threshold = null;

		// Sort thresholds by amount in descending order to find the highest applicable threshold
		usort(
			$thresholds,
			function ( $a, $b ) {
				return floatval( $b['threshold'] ) <=> floatval( $a['threshold'] );
			}
		);

		foreach ( $thresholds as $threshold ) {
			$threshold_amount = floatval( $threshold['threshold'] );
			if ( $cart_total >= $threshold_amount ) {
				$applicable_threshold = $threshold;
				break;
			}
		}

		return $applicable_threshold;
	}

	/**
	 * Calculate discount for a specific threshold.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    float $original_price    Original price.
	 * @param    array $threshold        Threshold configuration.
	 * @return   float                      Discounted price.
	 */
	private function calculate_threshold_discount( float $original_price, array $threshold ): float {
		$discount_type  = $threshold['discount_type'];
		$discount_value = floatval( $threshold['discount_value'] );

		switch ( $discount_type ) {
			case 'percentage':
				// CRITICAL FIX: Apply rounding to prevent float precision errors
				$discount_amount = $this->round_currency( $original_price * ( $discount_value / 100 ) );
				return $this->round_currency( max( 0, $original_price - $discount_amount ) );

			case 'fixed':
				// CRITICAL FIX: Apply rounding to prevent float precision errors
				return $this->round_currency( max( 0, $original_price - $discount_value ) );

			default:
				return $original_price;
		}
	}

	/**
	 * Get threshold description for display.
	 *
	 * @since    1.0.0
	 * @param    array $threshold    Threshold configuration.
	 * @return   string                 Threshold description.
	 */
	public function get_threshold_description( array $threshold ): string {
		$threshold_amount = $threshold['threshold'] ?? 0;
		$discount_type    = $threshold['discount_type'] ?? 'percentage';
		$discount_value   = $threshold['discount_value'] ?? 0;

		if ( 'percentage' === $discount_type ) {
			return sprintf(
				__( '%1$s%% off when cart total is %2$s or more', 'smart-cycle-discounts' ),
				number_format( $discount_value, 1 ),
				wc_price( $threshold_amount )
			);
		} else {
			return sprintf(
				__( '%1$s off when cart total is %2$s or more', 'smart-cycle-discounts' ),
				wc_price( $discount_value ),
				wc_price( $threshold_amount )
			);
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
			'thresholds' => array(
				'type'        => 'repeater',
				'label'       => __( 'Spend Thresholds', 'smart-cycle-discounts' ),
				'description' => __( 'Configure spend thresholds with discount values', 'smart-cycle-discounts' ),
				'fields'      => array(
					'threshold'      => array(
						'type'        => 'number',
						'label'       => __( 'Cart Total Threshold', 'smart-cycle-discounts' ),
						'description' => __( 'Minimum cart total to qualify for this threshold', 'smart-cycle-discounts' ),
						'min'         => 0,
						'step'        => 0.01,
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
				'title'       => __( 'Percentage-Based Thresholds', 'smart-cycle-discounts' ),
				'description' => __( '5% off orders over $50, 10% off orders over $100', 'smart-cycle-discounts' ),
				'config'      => array(
					'thresholds' => array(
						array(
							'threshold'      => 50,
							'discount_type'  => 'percentage',
							'discount_value' => 5.0,
						),
						array(
							'threshold'      => 100,
							'discount_type'  => 'percentage',
							'discount_value' => 10.0,
						),
					),
				),
			),
			array(
				'title'       => __( 'Fixed Amount Thresholds', 'smart-cycle-discounts' ),
				'description' => __( '$5 off orders over $50, $15 off orders over $100', 'smart-cycle-discounts' ),
				'config'      => array(
					'thresholds' => array(
						array(
							'threshold'      => 50,
							'discount_type'  => 'fixed',
							'discount_value' => 5.0,
						),
						array(
							'threshold'      => 100,
							'discount_type'  => 'fixed',
							'discount_value' => 15.0,
						),
					),
				),
			),
		);
	}

	/**
	 * Get next threshold information for upselling.
	 *
	 * @since    1.0.0
	 * @param    float $current_total    Current cart total.
	 * @param    array $config          Strategy configuration.
	 * @return   array|null                Next threshold info or null.
	 */
	public function get_next_threshold( float $current_total, array $config ): ?array {
		$thresholds = $config['thresholds'] ?? array();
		if ( empty( $thresholds ) ) {
			return null;
		}

		// Sort thresholds by amount in ascending order
		usort(
			$thresholds,
			function ( $a, $b ) {
				return floatval( $a['threshold'] ) <=> floatval( $b['threshold'] );
			}
		);

		foreach ( $thresholds as $threshold ) {
			$threshold_amount = floatval( $threshold['threshold'] );
			if ( $current_total < $threshold_amount ) {
				return array(
					'threshold'         => $threshold_amount,
					'discount_type'     => $threshold['discount_type'],
					'discount_value'    => $threshold['discount_value'],
					'additional_needed' => $threshold_amount - $current_total,
					'description'       => $this->get_threshold_description( $threshold ),
				);
			}
		}

		return null;
	}
}
