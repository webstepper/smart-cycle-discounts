<?php
/**
 * Percentage discount strategy
 *
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/discounts/strategies
 */

declare(strict_types=1);


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Percentage Discount Strategy
 *
 * Implements percentage-based discount calculations.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/discounts/strategies
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Percentage_Strategy implements SCD_Discount_Strategy_Interface {

    use SCD_Discount_Preview_Trait;

    /**
     * Strategy identifier.
     */
    public const STRATEGY_ID = 'percentage';

    /**
     * Calculate percentage discount.
     *
     * @since    1.0.0
     * @param    float    $original_price    Original product price.
     * @param    array    $discount_config   Discount configuration.
     * @param    array    $context          Additional context.
     * @return   SCD_Discount_Result        Discount calculation result.
     */
    public function calculate_discount(float $original_price, array $discount_config, array $context = array()): SCD_Discount_Result {
        // CRITICAL: Defensive validation for NULL or invalid prices
        if ( ! is_numeric( $original_price ) || $original_price < 0 ) {
            return SCD_Discount_Result::no_discount(
                0.0,
                self::STRATEGY_ID,
                'Invalid price: must be a non-negative number'
            );
        }

        // Validate configuration
        $errors = $this->validate_config($discount_config);
        if ( ! empty( $errors ) ) {
            return SCD_Discount_Result::no_discount( $original_price, self::STRATEGY_ID, 'Invalid configuration' );
        }

        $percentage = (float) $discount_config['percentage'];

        // CRITICAL: Add defensive validation even if config validation passed
        if ( $percentage < 0 || $percentage > 100 ) {
            return SCD_Discount_Result::no_discount(
                $original_price,
                self::STRATEGY_ID,
                'Invalid percentage value'
            );
        }
        $min_amount = (float) ($discount_config['min_amount'] ?? 0);
        $max_amount = (float) ($discount_config['max_amount'] ?? 0);

        // Check minimum price requirement
        if ($min_amount > 0 && $original_price < $min_amount) {
            return SCD_Discount_Result::no_discount(
                $original_price, 
                self::STRATEGY_ID, 
                "Price below minimum threshold of {$min_amount}"
            );
        }

        // CRITICAL FIX: Calculate discount with proper rounding to prevent float precision errors
        // Example: 10.1 + 0.2 = 10.300000000000001 without rounding
        $discount_amount = $this->round_currency( ($original_price * $percentage) / 100 );

        // Apply maximum discount limit
        if ($max_amount > 0 && $discount_amount > $max_amount) {
            $discount_amount = $this->round_currency( $max_amount );
        }

        // Ensure discount doesn't exceed original price
        $discount_amount = $this->round_currency( min( $discount_amount, $original_price ) );

        $discounted_price = $this->round_currency( $original_price - $discount_amount );

        // Check for minimum discounted price
        $min_discounted_price = (float) ($discount_config['min_discounted_price'] ?? 0);
        if ($min_discounted_price > 0 && $discounted_price < $min_discounted_price) {
            $discounted_price = $this->round_currency( $min_discounted_price );
            $discount_amount = $this->round_currency( $original_price - $discounted_price );
        }

        $metadata = array(
            'percentage_applied' => $percentage,
            'calculated_discount' => ($original_price * $percentage) / 100,
            'actual_discount' => $discount_amount,
            'min_amount_threshold' => $min_amount,
            'max_discount_limit' => $max_amount,
            'min_discounted_price' => $min_discounted_price
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
     * @param    array    $discount_config    Discount configuration.
     * @return   array                       Validation errors.
     */
    public function validate_config(array $discount_config): array {
        $errors = array();

        // Check required percentage field
        if ( ! isset( $discount_config['percentage'] ) ) {
            $errors['percentage'] = 'Percentage is required for percentage discount.';
        } else {
            $percentage = (float) $discount_config['percentage'];

            // CRITICAL: Fix broken constant names - use PERCENTAGE_MIN/MAX not DISCOUNT_PERCENTAGE_MIN/MAX
            if ( $percentage < SCD_Validation_Rules::PERCENTAGE_MIN ) {
                $errors['percentage'] = sprintf( 'Percentage must be at least %d.', SCD_Validation_Rules::PERCENTAGE_MIN );
            } elseif ( $percentage > SCD_Validation_Rules::PERCENTAGE_MAX ) {
                $errors['percentage'] = sprintf( 'Percentage cannot exceed %d%%.', SCD_Validation_Rules::PERCENTAGE_MAX );
            } elseif ( $percentage < 0 ) {
                $errors['percentage'] = 'Percentage cannot be negative.';
            }
        }

        // Validate minimum amount
        if (isset($discount_config['min_amount'])) {
            $min_amount = (float) $discount_config['min_amount'];
            if ($min_amount < 0) {
                $errors['min_amount'] = 'Minimum amount cannot be negative.';
            }
        }

        // Validate maximum discount amount
        if (isset($discount_config['max_amount'])) {
            $max_amount = (float) $discount_config['max_amount'];
            if ($max_amount < 0) {
                $errors['max_amount'] = 'Maximum discount amount cannot be negative.';
            }
        }

        // Validate minimum discounted price
        if (isset($discount_config['min_discounted_price'])) {
            $min_discounted_price = (float) $discount_config['min_discounted_price'];
            if ($min_discounted_price < 0) {
                $errors['min_discounted_price'] = 'Minimum discounted price cannot be negative.';
            }
        }

        // Check logical consistency
        if (isset($discount_config['min_amount']) && isset($discount_config['min_discounted_price'])) {
            $min_amount = (float) $discount_config['min_amount'];
            $min_discounted_price = (float) $discount_config['min_discounted_price'];
            
            if ($min_discounted_price >= $min_amount) {
                $errors['consistency'] = 'Minimum discounted price should be less than minimum amount threshold.';
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
        return 'Percentage Discount';
    }

    /**
     * Get strategy description.
     *
     * @since    1.0.0
     * @return   string    Strategy description.
     */
    public function get_strategy_description(): string {
        return 'Apply a percentage-based discount to product prices.';
    }

    /**
     * Check if strategy supports given context.
     *
     * @since    1.0.0
     * @param    array    $context    Context to check.
     * @return   bool                 True if strategy supports context.
     */
    public function supports_context(array $context): bool {
        // Percentage strategy supports all contexts
        return true;
    }

    /**
     * Get minimum discount amount.
     *
     * @since    1.0.0
     * @param    array    $discount_config    Discount configuration.
     * @return   float                        Minimum discount amount.
     */
    public function get_minimum_discount(array $discount_config): float {
        $percentage = (float) ($discount_config['percentage'] ?? 0);
        $min_amount = (float) ($discount_config['min_amount'] ?? 0);

        if ($min_amount > 0) {
            return ($min_amount * $percentage) / 100;
        }

        return 0.0;
    }

    /**
     * Round currency value to 2 decimal places.
     *
     * CRITICAL FIX: Prevents float precision errors in currency calculations.
     * Example: Without rounding, 10.1 + 0.2 = 10.300000000000001
     *
     * @since    1.0.0
     * @param    float    $value    Value to round.
     * @return   float             Rounded value.
     */
    private function round_currency( float $value ): float {
        return round( $value, 2 );
    }

    /**
     * Get maximum discount amount.
     *
     * @since    1.0.0
     * @param    array    $discount_config    Discount configuration.
     * @return   float                        Maximum discount amount.
     */
    public function get_maximum_discount(array $discount_config): float {
        $max_amount = (float) ($discount_config['max_amount'] ?? 0);

        if ($max_amount > 0) {
            return $max_amount;
        }

        // If no max amount is set, return a theoretical maximum based on percentage
        $percentage = (float) ($discount_config['percentage'] ?? 0);
        return PHP_FLOAT_MAX; // Unlimited, but will be capped by original price
    }

    /**
     * Get configuration schema.
     *
     * @since    1.0.0
     * @return   array    Configuration schema.
     */
    public function get_config_schema(): array {
        return array(
            'percentage' => array(
                'type' => 'number',
                'required' => true,
                'min' => 0.01,
                'max' => 100,
                'step' => 0.01,
                'label' => 'Discount Percentage',
                'description' => 'Percentage to discount from the original price (1-100%).'
            ),
            'min_amount' => array(
                'type' => 'number',
                'required' => false,
                'min' => 0,
                'step' => 0.01,
                'label' => 'Minimum Amount',
                'description' => 'Minimum product price required for discount to apply.'
            ),
            'max_amount' => array(
                'type' => 'number',
                'required' => false,
                'min' => 0,
                'step' => 0.01,
                'label' => 'Maximum Discount Amount',
                'description' => 'Maximum discount amount that can be applied.'
            ),
            'min_discounted_price' => array(
                'type' => 'number',
                'required' => false,
                'min' => 0,
                'step' => 0.01,
                'label' => 'Minimum Discounted Price',
                'description' => 'Minimum price after discount is applied.'
            )
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
            'percentage' => 10.0,
            'min_amount' => 0.0,
            'max_amount' => 0.0,
            'min_discounted_price' => 0.0
        );
    }

    /**
     * Format savings text for percentage discount.
     *
     * Override to include percentage in savings text.
     *
     * @since    1.0.0
     * @param    SCD_Discount_Result    $result    Discount calculation result.
     * @return   string                            Formatted savings text with percentage.
     */
    protected function format_savings_text( SCD_Discount_Result $result ): string {
        return sprintf(
            /* translators: 1: discount amount, 2: discount percentage */
            __( 'Save %1$s (%2$s%%)', 'smart-cycle-discounts' ),
            wc_price( $result->get_discount_amount() ),
            number_format( $result->get_discount_percentage(), 1 )
        );
    }
}

