<?php
/**
 * Fixed Strategy Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/discounts/strategies/class-fixed-strategy.php
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
 * Fixed Amount Discount Strategy
 *
 * Implements fixed amount discount calculations.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/discounts/strategies
 * @author     Webstepper <contact@webstepper.io>
 */
class SCD_Fixed_Strategy implements SCD_Discount_Strategy_Interface {

	use SCD_Discount_Preview_Trait;

	/**
	 * Strategy identifier.
	 */
	public const STRATEGY_ID = 'fixed';

	/**
	 * Calculate fixed amount discount.
	 *
	 * @since    1.0.0
	 * @param    float $original_price    Original product price.
	 * @param    array $discount_config   Discount configuration.
	 * @param    array $context          Additional context.
	 * @return   SCD_Discount_Result        Discount calculation result.
	 */
	public function calculate_discount( float $original_price, array $discount_config, array $context = array() ): SCD_Discount_Result {
		// CRITICAL: Defensive validation for NULL or invalid prices
		if ( ! is_numeric( $original_price ) || $original_price < 0 ) {
			return SCD_Discount_Result::no_discount(
				0.0,
				self::STRATEGY_ID,
				'Invalid price: must be a non-negative number'
			);
		}

		$errors = $this->validate_config( $discount_config );
		if ( ! empty( $errors ) ) {
			return SCD_Discount_Result::no_discount( $original_price, self::STRATEGY_ID, 'Invalid configuration' );
		}

		$fixed_amount = (float) $discount_config['amount'];

		// CRITICAL: Add defensive validation even if config validation passed
		if ( $fixed_amount < 0 ) {
			return SCD_Discount_Result::no_discount(
				$original_price,
				self::STRATEGY_ID,
				'Invalid negative discount amount'
			);
		}

		$min_price      = (float) ( $discount_config['min_price'] ?? 0 );
		$max_percentage = (float) ( $discount_config['max_percentage'] ?? 0 );

		if ( $min_price > 0 && $original_price < $min_price ) {
			return SCD_Discount_Result::no_discount(
				$original_price,
				self::STRATEGY_ID,
				"Price below minimum threshold of {$min_price}"
			);
		}

		// CRITICAL FIX: Calculate discount with proper rounding to prevent float precision errors
		$discount_amount = $this->round_currency( $fixed_amount );

		// Apply maximum percentage limit
		if ( $max_percentage > 0 ) {
			$max_discount_by_percentage = $this->round_currency( ( $original_price * $max_percentage ) / 100 );
			$discount_amount            = $this->round_currency( min( $discount_amount, $max_discount_by_percentage ) );
		}

		// Ensure discount doesn't exceed original price
		$discount_amount = $this->round_currency( min( $discount_amount, $original_price ) );

		$discounted_price = $this->round_currency( $original_price - $discount_amount );

		$min_discounted_price = (float) ( $discount_config['min_discounted_price'] ?? 0 );
		if ( $min_discounted_price > 0 && $discounted_price < $min_discounted_price ) {
			$discounted_price = $this->round_currency( $min_discounted_price );
			$discount_amount  = $this->round_currency( $original_price - $discounted_price );
		}

		$metadata = array(
			'fixed_amount_configured' => $fixed_amount,
			'actual_discount'         => $discount_amount,
			'min_price_threshold'     => $min_price,
			'max_percentage_limit'    => $max_percentage,
			'min_discounted_price'    => $min_discounted_price,
			'percentage_applied'      => $original_price > 0 ? ( $discount_amount / $original_price ) * 100 : 0,
		);

		return new SCD_Discount_Result(
			$original_price,
			$discounted_price,
			self::STRATEGY_ID,
			$discount_amount > 0,
			$metadata
		);
	}

	/**
	 * Validate discount configuration.
	 *
	 * @since    1.0.0
	 * @param    array $discount_config    Discount configuration.
	 * @return   array                       Validation errors.
	 */
	public function validate_config( array $discount_config ): array {
		$errors = array();

		if ( ! isset( $discount_config['amount'] ) ) {
			$errors['amount'] = 'Fixed amount is required for fixed discount.';
		} else {
			$amount = (float) $discount_config['amount'];

			// CRITICAL: Explicitly reject negative amounts
			if ( $amount < 0 ) {
				$errors['amount'] = 'Fixed amount cannot be negative.';
			} elseif ( $amount === 0.0 ) {
				$errors['amount'] = 'Fixed amount must be greater than 0.';
			}
		}

		if ( isset( $discount_config['min_price'] ) ) {
			$min_price = (float) $discount_config['min_price'];
			if ( $min_price < 0 ) {
				$errors['min_price'] = 'Minimum price cannot be negative.';
			}
		}

		if ( isset( $discount_config['max_percentage'] ) ) {
			$max_percentage = (float) $discount_config['max_percentage'];
			if ( $max_percentage < 0 ) {
				$errors['max_percentage'] = 'Maximum percentage cannot be negative.';
			} elseif ( $max_percentage > 100 ) {
				$errors['max_percentage'] = 'Maximum percentage cannot exceed 100%.';
			}
		}

		if ( isset( $discount_config['min_discounted_price'] ) ) {
			$min_discounted_price = (float) $discount_config['min_discounted_price'];
			if ( $min_discounted_price < 0 ) {
				$errors['min_discounted_price'] = 'Minimum discounted price cannot be negative.';
			}
		}

		if ( isset( $discount_config['min_price'] ) && isset( $discount_config['min_discounted_price'] ) ) {
			$min_price            = (float) $discount_config['min_price'];
			$min_discounted_price = (float) $discount_config['min_discounted_price'];

			if ( $min_discounted_price >= $min_price ) {
				$errors['consistency'] = 'Minimum discounted price should be less than minimum price threshold.';
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
		return self::STRATEGY_ID;
	}

	/**
	 * Get strategy name.
	 *
	 * @since    1.0.0
	 * @return   string    Human-readable strategy name.
	 */
	public function get_strategy_name(): string {
		return 'Fixed Amount Discount';
	}

	/**
	 * Get strategy description.
	 *
	 * @since    1.0.0
	 * @return   string    Strategy description.
	 */
	public function get_strategy_description(): string {
		return 'Apply a fixed amount discount to product prices.';
	}

	/**
	 * Check if strategy supports given context.
	 *
	 * @since    1.0.0
	 * @param    array $context    Context to check.
	 * @return   bool                 True if strategy supports context.
	 */
	public function supports_context( array $context ): bool {
		// Fixed strategy supports all contexts
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
		$amount         = (float) ( $discount_config['amount'] ?? 0 );
		$min_price      = (float) ( $discount_config['min_price'] ?? 0 );
		$max_percentage = (float) ( $discount_config['max_percentage'] ?? 0 );

		if ( $min_price > 0 && $max_percentage > 0 ) {
			$max_discount_by_percentage = ( $min_price * $max_percentage ) / 100;
			return min( $amount, $max_discount_by_percentage );
		}

		return $amount;
	}

	/**
	 * Round currency value to 2 decimal places.
	 *
	 * CRITICAL FIX: Prevents float precision errors in currency calculations.
	 * Example: Without rounding, 10.1 - 5.0 = 5.0999999999999996
	 *
	 * @since    1.0.0
	 * @param    float $value    Value to round.
	 * @return   float             Rounded value.
	 */
	private function round_currency( float $value ): float {
		return round( $value, 2 );
	}

	/**
	 * Get maximum discount amount.
	 *
	 * @since    1.0.0
	 * @param    array $discount_config    Discount configuration.
	 * @return   float                        Maximum discount amount.
	 */
	public function get_maximum_discount( array $discount_config ): float {
		$amount = (float) ( $discount_config['amount'] ?? 0 );
		return $amount;
	}

	/**
	 * Get configuration schema.
	 *
	 * @since    1.0.0
	 * @return   array    Configuration schema.
	 */
	public function get_config_schema(): array {
		return array(
			'amount'               => array(
				'type'        => 'number',
				'required'    => true,
				'min'         => 0.01,
				'step'        => 0.01,
				'label'       => 'Discount Amount',
				'description' => 'Fixed amount to discount from the original price.',
			),
			'min_price'            => array(
				'type'        => 'number',
				'required'    => false,
				'min'         => 0,
				'step'        => 0.01,
				'label'       => 'Minimum Price',
				'description' => 'Minimum product price required for discount to apply.',
			),
			'max_percentage'       => array(
				'type'        => 'number',
				'required'    => false,
				'min'         => 0,
				'max'         => 100,
				'step'        => 0.01,
				'label'       => 'Maximum Percentage',
				'description' => 'Maximum percentage of original price that can be discounted.',
			),
			'min_discounted_price' => array(
				'type'        => 'number',
				'required'    => false,
				'min'         => 0,
				'step'        => 0.01,
				'label'       => 'Minimum Discounted Price',
				'description' => 'Minimum price after discount is applied.',
			),
		);
	}

	/**
	 * Get default configuration.
	 *
	 * @since    1.0.0
	 * @return   array    Default configuration.
	 */
	public function get_default_config(): array {
		return array(
			'amount'               => 5.0,
			'min_price'            => 0.0,
			'max_percentage'       => 0.0,
			'min_discounted_price' => 0.0,
		);
	}
}
