<?php
/**
 * Bogo Strategy Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/discounts/strategies/class-bogo-strategy.php
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
 * BOGO Strategy
 *
 * Implements Buy-One-Get-One discount calculations.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/discounts/strategies
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_BOGO_Strategy implements SCD_Discount_Strategy_Interface {

	/**
	 * Calculate BOGO discount.
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

			// Get quantity from context
			$quantity = intval( $context['quantity'] ?? 1 );
			if ( $quantity < 1 ) {
				return SCD_Discount_Result::no_discount( $original_price, $this->get_strategy_id(), 'Quantity must be at least 1' );
			}

			// Get BOGO configuration
			$buy_quantity            = intval( $discount_config['buy_quantity'] ?? 1 );
			$get_quantity            = intval( $discount_config['get_quantity'] ?? 1 );
			$get_discount_percentage = floatval( $discount_config['get_discount_percentage'] ?? 100.0 );
			$max_applications        = isset( $discount_config['max_applications'] ) ? intval( $discount_config['max_applications'] ) : null;

			// CRITICAL FIX: Calculate how many BOGO sets can be applied
			// intval() truncates decimals: 7 items / 3 per set = 2.33 → 2 sets (1 item wasted)
			$set_size        = $buy_quantity + $get_quantity;
			$bogo_sets       = intval( $quantity / $set_size );
			$remainder_items = $quantity % $set_size;

			// Apply maximum applications limit if set
			if ( $max_applications !== null && $bogo_sets > $max_applications ) {
				$bogo_sets = $max_applications;
			}

			if ( $bogo_sets > 0 ) {
				// CRITICAL FIX: Calculate discount with proper rounding to prevent float precision errors
				$discounted_items  = $bogo_sets * $get_quantity;
				$discount_per_item = $this->round_currency( $original_price * ( $get_discount_percentage / 100 ) );
				$total_discount    = $this->round_currency( $discounted_items * $discount_per_item );

				// Calculate average discount per item
				$average_discount_per_item = $this->round_currency( $total_discount / $quantity );
				$discounted_price          = $this->round_currency( $original_price - $average_discount_per_item );

				// Add BOGO-specific information with decimal handling warning
				$metadata = array(
					'buy_quantity'            => $buy_quantity,
					'get_quantity'            => $get_quantity,
					'get_discount_percentage' => $get_discount_percentage,
					'bogo_sets_applied'       => $bogo_sets,
					'discounted_items'        => $discounted_items,
					'total_discount_amount'   => $total_discount,
					'remainder_items'         => $remainder_items,
					'items_qualifying'        => $bogo_sets * $set_size,
				);

				// CRITICAL WARNING: Alert if items don't qualify due to decimal truncation
				if ( $remainder_items > 0 ) {
					$metadata['warning'] = sprintf(
						'%d item(s) do not qualify for discount (need %d items per set)',
						$remainder_items,
						$set_size
					);
				}

				return new SCD_Discount_Result( $original_price, $discounted_price, $this->get_strategy_id(), true, $metadata );
			}

			return SCD_Discount_Result::no_discount( $original_price, $this->get_strategy_id(), 'Insufficient quantity for BOGO' );

		} catch ( Exception $e ) {
			return SCD_Discount_Result::no_discount( $original_price, $this->get_strategy_id(), $e->getMessage() );
		}
	}

	/**
	 * Validate BOGO configuration.
	 *
	 * @since    1.0.0
	 * @param    array $discount_config    Configuration to validate.
	 * @return   array                       Validation errors (empty if valid).
	 */
	public function validate_config( array $discount_config ): array {
		$errors = array();

		// Validate buy quantity with business logic limits
		if ( ! isset( $discount_config['buy_quantity'] ) || ! is_numeric( $discount_config['buy_quantity'] ) ) {
			$errors[] = __( 'Buy quantity is required and must be numeric', 'smart-cycle-discounts' );
		} else {
			$buy_qty = intval( $discount_config['buy_quantity'] );
			if ( $buy_qty < SCD_Validation_Rules::BOGO_BUY_MIN || $buy_qty > SCD_Validation_Rules::BOGO_BUY_MAX ) {
				$errors[] = sprintf(
					__( 'Buy quantity must be between %1$d and %2$d', 'smart-cycle-discounts' ),
					SCD_Validation_Rules::BOGO_BUY_MIN,
					SCD_Validation_Rules::BOGO_BUY_MAX
				);
			}
		}

		// Validate get quantity with business logic limits
		if ( ! isset( $discount_config['get_quantity'] ) || ! is_numeric( $discount_config['get_quantity'] ) ) {
			$errors[] = __( 'Get quantity is required and must be numeric', 'smart-cycle-discounts' );
		} else {
			$get_qty = intval( $discount_config['get_quantity'] );
			if ( $get_qty < SCD_Validation_Rules::BOGO_GET_MIN || $get_qty > SCD_Validation_Rules::BOGO_GET_MAX ) {
				$errors[] = sprintf(
					__( 'Get quantity must be between %1$d and %2$d', 'smart-cycle-discounts' ),
					SCD_Validation_Rules::BOGO_GET_MIN,
					SCD_Validation_Rules::BOGO_GET_MAX
				);
			}
		}

		// HIGH: Add business logic validation - prevent absurd ratios like "Buy 1 Get 100"
		if ( isset( $discount_config['buy_quantity'] ) && isset( $discount_config['get_quantity'] ) ) {
			$buy_qty = intval( $discount_config['buy_quantity'] );
			$get_qty = intval( $discount_config['get_quantity'] );

			if ( $get_qty > ( $buy_qty * 5 ) ) {
				$errors[] = __( 'Get quantity cannot exceed 5 times the buy quantity', 'smart-cycle-discounts' );
			}
		}

		// Validate get discount percentage
		if ( isset( $discount_config['get_discount_percentage'] ) ) {
			$percentage = floatval( $discount_config['get_discount_percentage'] );
			if ( $percentage < 0 || $percentage > 100 ) {
				$errors[] = __( 'Get discount percentage must be between 0 and 100', 'smart-cycle-discounts' );
			}
		}

		// Validate max applications
		if ( isset( $discount_config['max_applications'] ) ) {
			if ( ! is_numeric( $discount_config['max_applications'] ) || intval( $discount_config['max_applications'] ) < 1 ) {
				$errors[] = __( 'Max applications must be a positive number', 'smart-cycle-discounts' );
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
		return 'bogo';
	}

	/**
	 * Get strategy name.
	 *
	 * @since    1.0.0
	 * @return   string    Human-readable strategy name.
	 */
	public function get_strategy_name(): string {
		return 'Buy-One-Get-One';
	}

	/**
	 * Get strategy description.
	 *
	 * @since    1.0.0
	 * @return   string    Strategy description.
	 */
	public function get_strategy_description(): string {
		return 'Buy a certain quantity and get additional items at a discount or free';
	}

	/**
	 * Round currency value to 2 decimal places.
	 *
	 * CRITICAL FIX: Prevents float precision errors in currency calculations.
	 * Example: Without rounding, (10.0 * 50) / 100 = 4.9999999999999999
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
		// CRITICAL: BOGO requires quantity information in context
		return isset( $context['quantity'] ) && intval( $context['quantity'] ) > 0;
	}

	/**
	 * Get minimum discount amount.
	 *
	 * @since    1.0.0
	 * @param    array $discount_config    Discount configuration.
	 * @return   float                        Minimum discount amount.
	 */
	public function get_minimum_discount( array $discount_config ): float {
		return 0.0; // BOGO can have no discount if quantity requirements aren't met
	}

	/**
	 * Get maximum discount amount.
	 *
	 * @since    1.0.0
	 * @param    array $discount_config    Discount configuration.
	 * @return   float                        Maximum discount amount.
	 */
	public function get_maximum_discount( array $discount_config ): float {
		$get_discount_percentage = floatval( $discount_config['get_discount_percentage'] ?? 100.0 );
		return $get_discount_percentage; // Maximum is the percentage configured
	}

	/**
	 * Get configuration schema for admin interface.
	 *
	 * @since    1.0.0
	 * @return   array    Configuration schema.
	 */
	public function get_config_schema(): array {
		return array(
			'buy_quantity'            => array(
				'type'        => 'integer',
				'label'       => __( 'Buy Quantity', 'smart-cycle-discounts' ),
				'description' => __( 'Number of items customer must buy', 'smart-cycle-discounts' ),
				'default'     => 1,
				'min'         => 1,
				'required'    => true,
			),
			'get_quantity'            => array(
				'type'        => 'integer',
				'label'       => __( 'Get Quantity', 'smart-cycle-discounts' ),
				'description' => __( 'Number of items customer gets discounted', 'smart-cycle-discounts' ),
				'default'     => 1,
				'min'         => 1,
				'required'    => true,
			),
			'get_discount_percentage' => array(
				'type'        => 'number',
				'label'       => __( 'Get Discount Percentage', 'smart-cycle-discounts' ),
				'description' => __( 'Discount percentage for the "get" items (100% = free)', 'smart-cycle-discounts' ),
				'default'     => 100.0,
				'min'         => 0,
				'max'         => 100,
				'step'        => 0.1,
				'required'    => false,
			),
			'max_applications'        => array(
				'type'        => 'integer',
				'label'       => __( 'Maximum Applications', 'smart-cycle-discounts' ),
				'description' => __( 'Maximum number of times this BOGO can be applied (leave empty for unlimited)', 'smart-cycle-discounts' ),
				'min'         => 1,
				'required'    => false,
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
				'title'  => __( 'Buy 1 Get 1 Free', 'smart-cycle-discounts' ),
				'config' => array(
					'buy_quantity'            => 1,
					'get_quantity'            => 1,
					'get_discount_percentage' => 100.0,
				),
			),
			array(
				'title'  => __( 'Buy 2 Get 1 Free', 'smart-cycle-discounts' ),
				'config' => array(
					'buy_quantity'            => 2,
					'get_quantity'            => 1,
					'get_discount_percentage' => 100.0,
				),
			),
			array(
				'title'  => __( 'Buy 1 Get 1 Half Off', 'smart-cycle-discounts' ),
				'config' => array(
					'buy_quantity'            => 1,
					'get_quantity'            => 1,
					'get_discount_percentage' => 50.0,
				),
			),
			array(
				'title'  => __( 'Buy 3 Get 2 Free (Max 1 Application)', 'smart-cycle-discounts' ),
				'config' => array(
					'buy_quantity'            => 3,
					'get_quantity'            => 2,
					'get_discount_percentage' => 100.0,
					'max_applications'        => 1,
				),
			),
		);
	}
}
