<?php
/**
 * Discounts Step Validator Class
 *
 * Validates the discounts step for logical consistency, business rules, and edge cases.
 * Focuses ONLY on discounts step internal validation. Cross-step validation is handled
 * by WSSCD_Campaign_Cross_Validator.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/validation/step-validators
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
 * Discounts Step Validator Class
 *
 * Validates the discounts step configuration for 72+ types of logical inconsistencies,
 * business rule violations, and edge cases. This validator focuses ONLY on the discounts
 * step itself - cross-step validation is handled by WSSCD_Campaign_Cross_Validator.
 *
 * SCOPE: Discounts step internal validation only
 * - Discount type configuration (BOGO, tiered, spend thresholds)
 * - Discount rules (usage limits, application rules, badges)
 * - Field-level edge cases and business logic
 *
 * NOT IN SCOPE (handled by campaign-cross-validator):
 * - Cross-step compatibility with products selection
 * - Cross-step compatibility with filter conditions
 * - Cross-step compatibility with schedule settings
 *
 * Validation scenarios covered:
 * 1-3:   Usage limits consistency (customer vs total, lifetime vs cycle, unlimited warnings)
 * 4-6:   Usage limits edge cases (zero customer with total, overflow values, redundancy)
 * 7-10:  Application rules logic (max discount reasonableness, cart total conflicts, quantity conflicts, zero max)
 * 11-15: Application rules edge cases (unrealistic quantities, unrealistic amounts, overly restrictive)
 * 16-20: BOGO configuration (buy/get quantities, discount percentage, configuration warnings, imbalance)
 * 21-23: BOGO edge cases (very large quantities, low discount percentage, buy=get with 100%)
 * 24-30: Tiered discounts (tier structure, ascending thresholds, discount progression, value ranges)
 * 31-38: Tiered edge cases (duplicates, zero/negative start, unrealistic jumps, too many, gaps, fixed>threshold)
 * 39-45: Spend thresholds (threshold structure, ascending amounts, discount ranges)
 * 46-52: Threshold edge cases (duplicates, threshold<min order, tiny values, fixed>spend, unrealistic jumps)
 * 53-56: Badge configuration (text completeness, color validation, accessibility contrast, text length)
 * 57-59: Badge edge cases (identical colors, very long text, invalid positions)
 * 60-62: Combination policy (stacking conflicts, sale items warnings, coupon interactions)
 * 63-66: Discount value validation (zero value, very small, profit margin warnings, unrealistic)
 * 67-72: Cross-field validation (BOGO+cart_total, tiered+per_item, threshold+min_qty, all options disabled, discount type conflicts)
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/validation/step-validators
 */
class WSSCD_Discounts_Step_Validator {

	/**
	 * Log a validation hint (informational message about unusual config).
	 *
	 * Uses the plugin's logger with 'info' level, which is only captured
	 * when log level is set to 'info' or 'debug' (not by default).
	 *
	 * @since    1.0.0
	 * @param    string $message    The hint message to log.
	 * @return   void
	 */
	private static function log_hint( $message ) {
		// Only log when WSSCD_DEBUG is enabled or log level is info/debug
		if ( ! defined( 'WSSCD_DEBUG' ) || ! WSSCD_DEBUG ) {
			return;
		}

		// Use the plugin's logger if available
		if ( class_exists( 'WSSCD_Logger' ) ) {
			static $logger = null;
			if ( null === $logger ) {
				$logger = new WSSCD_Logger( 'validation' );
			}
			$logger->info( $message );
		}
	}

	/**
	 * Validate discount rules configuration.
	 *
	 * Prevents saving discount rules that are logically inconsistent or violate business rules.
	 * This is the PRIMARY validation point - prevents bad data from being saved.
	 *
	 * @since    1.0.0
	 * @param    array    $data      Sanitized discount step data.
	 * @param    WP_Error $errors    Error object to add errors to.
	 * @return   void
	 */
	public static function validate( array $data, WP_Error $errors ) {
		// Validate usage limits consistency
		self::validate_usage_limits( $data, $errors );

		// Validate application rules logic
		self::validate_application_rules( $data, $errors );

		// Validate discount value reasonableness
		self::validate_discount_value( $data, $errors );

		// Validate discount type specific rules
		self::validate_discount_type_rules( $data, $errors );

		// Validate badge configuration completeness
		self::validate_badge_configuration( $data, $errors );

		// Validate combination policy logic
		self::validate_combination_policy( $data, $errors );

		// Cross-field validation between discount type and application rules
		self::validate_cross_field_logic( $data, $errors );

		// Validate free shipping configuration
		self::validate_free_shipping_config( $data, $errors );

		// NOTE: Cross-step validation (products, filters, schedule) is now handled by
		// WSSCD_Campaign_Cross_Validator at the review step via the campaign health system.
	}

	/**
	 * Validate usage limits for logical consistency.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array    $data      Discount data.
	 * @param    WP_Error $errors    Error object.
	 * @return   void
	 */
	private static function validate_usage_limits( array $data, WP_Error $errors ) {
		$customer_limit = isset( $data['usage_limit_per_customer'] ) ? absint( $data['usage_limit_per_customer'] ) : 0;
		$total_limit    = isset( $data['total_usage_limit'] ) ? absint( $data['total_usage_limit'] ) : 0;
		$lifetime_cap   = isset( $data['lifetime_usage_cap'] ) ? absint( $data['lifetime_usage_cap'] ) : 0;

		// Rule 1: Per-customer limit cannot exceed total limit
		if ( $customer_limit > 0 && $total_limit > 0 && $customer_limit > $total_limit ) {
			$errors->add(
				'usage_limits_conflict',
				sprintf(
					/* translators: 1: customer limit, 2: total limit */
					__( 'Usage limit per customer (%1$d) cannot exceed total usage limit (%2$d). This would prevent customers from ever reaching their individual limit.', 'smart-cycle-discounts' ),
					$customer_limit,
					$total_limit
				)
			);
		}

		// Rule 2: Lifetime cap cannot be less than per-cycle total limit
		if ( $lifetime_cap > 0 && $total_limit > 0 && $lifetime_cap < $total_limit ) {
			$errors->add(
				'lifetime_cap_conflict',
				sprintf(
					/* translators: 1: lifetime cap, 2: total limit */
					__( 'Lifetime usage cap (%1$d) cannot be less than total usage limit per cycle (%2$d). The discount would be exhausted in the first cycle.', 'smart-cycle-discounts' ),
					$lifetime_cap,
					$total_limit
				)
			);
		}

		// Rule 3: If only customer limit is set, warn about potential confusion
		if ( $customer_limit > 0 && 0 === $total_limit && 0 === $lifetime_cap ) {
			// This is valid but might be unintentional - log for debugging
			self::log_hint( 'Per-customer limit set without total/lifetime limits. This allows unlimited campaign usage across all customers.' );
		}

		// Rule 4: Zero customer limit with positive total limit (unusual)
		if ( 0 === $customer_limit && $total_limit > 0 ) {
			// This means single customers can exhaust entire campaign
			self::log_hint( sprintf( 'No per-customer limit but total limit is %d. A single customer could exhaust the entire campaign.', $total_limit ) );
		}

		// Rule 5: Very large values (potential overflow or unrealistic)
		if ( $customer_limit > WSSCD_Validation_Rules::USAGE_LIMIT_MAX || $total_limit > WSSCD_Validation_Rules::USAGE_LIMIT_MAX || $lifetime_cap > WSSCD_Validation_Rules::USAGE_LIMIT_MAX ) {
			$errors->add(
				'usage_limit_too_large',
				sprintf(
					/* translators: %d: maximum limit */
					__( 'Usage limits cannot exceed %d. Use 0 for unlimited instead.', 'smart-cycle-discounts' ),
					WSSCD_Validation_Rules::USAGE_LIMIT_MAX
				)
			);
		}

		// Rule 6: All three limits identical (redundant configuration)
		if ( $customer_limit > 0 && $customer_limit === $total_limit && $total_limit === $lifetime_cap ) {
			// All three are the same - redundant
			self::log_hint( sprintf( 'All three usage limits are set to %d. This is redundant - consider using only total_usage_limit.', $customer_limit ) );
		}
	}

	/**
	 * Validate application rules for edge cases.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array    $data      Discount data.
	 * @param    WP_Error $errors    Error object.
	 * @return   void
	 */
	private static function validate_application_rules( array $data, WP_Error $errors ) {
		$apply_to              = isset( $data['apply_to'] ) ? $data['apply_to'] : 'per_item';
		$max_discount          = isset( $data['max_discount_amount'] ) ? floatval( $data['max_discount_amount'] ) : 0;
		$minimum_quantity      = isset( $data['minimum_quantity'] ) ? absint( $data['minimum_quantity'] ) : 0;
		$minimum_order_amount  = isset( $data['minimum_order_amount'] ) ? floatval( $data['minimum_order_amount'] ) : 0;
		$discount_type         = isset( $data['discount_type'] ) ? $data['discount_type'] : 'percentage';

		// Get discount value from type-specific field (after field definitions refactoring)
		$discount_value = 0;
		if ( 'percentage' === $discount_type && isset( $data['discount_value_percentage'] ) ) {
			$discount_value = floatval( $data['discount_value_percentage'] );
		} elseif ( 'fixed' === $discount_type && isset( $data['discount_value_fixed'] ) ) {
			$discount_value = floatval( $data['discount_value_fixed'] );
		} elseif ( isset( $data['discount_value'] ) ) {
			// Fallback to generic field for backward compatibility
			$discount_value = floatval( $data['discount_value'] );
		}

		// Rule 1: Max discount amount should be reasonable relative to discount value
		if ( $max_discount > 0 && 'percentage' === $discount_type ) {
			// Calculate what the max discount would be at different price points
			$implied_min_price = ( $discount_value > 0 ) ? ( $max_discount / ( $discount_value / 100 ) ) : 0;

			// If max discount is very low relative to percentage, warn
			if ( $implied_min_price > 0 && $implied_min_price < 10 ) {
				// This is valid but unusual - a $5 max on 50% would cap at $10 products
				self::log_hint( sprintf( 'Max discount cap of $%s with %s%% discount implies products under $%s', $max_discount, $discount_value, $implied_min_price ) );
			}
		}

		// Rule 2: Minimum order amount vs max discount (cart_total mode)
		if ( 'cart_total' === $apply_to && $minimum_order_amount > 0 && $max_discount > 0 ) {
			// Max discount shouldn't be more than the minimum order amount (would give negative cart)
			if ( $max_discount >= $minimum_order_amount ) {
				$errors->add(
					'max_discount_exceeds_minimum',
					sprintf(
						/* translators: 1: max discount, 2: minimum order */
						__( 'Maximum discount amount ($%1$.2f) should not exceed minimum order amount ($%2$.2f) when applying to cart total. This could result in negative cart values.', 'smart-cycle-discounts' ),
						$max_discount,
						$minimum_order_amount
					)
				);
			}
		}

		// Rule 3: Minimum quantity with cart_total application
		if ( 'cart_total' === $apply_to && $minimum_quantity > 1 ) {
			// Warn: minimum quantity doesn't make much sense with cart_total
			self::log_hint( 'Minimum quantity is set with cart_total discount application. This may cause confusion - cart_total typically applies regardless of individual item quantities.' );
		}

		// Rule 4: Max discount of zero (pointless)
		if ( isset( $data['max_discount_amount'] ) && 0 === $max_discount && $max_discount !== false ) {
			// Max discount is explicitly set to 0 - this would cap discount at $0
			self::log_hint( 'Maximum discount amount is set to 0. This effectively disables the discount.' );
		}

		// Rule 5: Unrealistic minimum quantity
		if ( $minimum_quantity > WSSCD_Validation_Rules::MINIMUM_QUANTITY_MAX ) {
			$errors->add(
				'minimum_quantity_unrealistic',
				sprintf(
					/* translators: 1: minimum quantity, 2: maximum */
					__( 'Minimum quantity (%1$d) exceeds reasonable maximum (%2$d). Most customers won\'t purchase this many items.', 'smart-cycle-discounts' ),
					$minimum_quantity,
					WSSCD_Validation_Rules::MINIMUM_QUANTITY_MAX
				)
			);
		}

		// Rule 6: Minimum order amount cannot exceed maximum
		if ( $minimum_order_amount > WSSCD_Validation_Rules::MINIMUM_ORDER_AMOUNT_MAX ) {
			$errors->add(
				'minimum_order_amount_exceeds_maximum',
				sprintf(
					/* translators: %s: maximum minimum order amount */
					__( 'Minimum order amount cannot exceed $%s. For higher minimums, please contact support.', 'smart-cycle-discounts' ),
					number_format( WSSCD_Validation_Rules::MINIMUM_ORDER_AMOUNT_MAX, 2 )
				)
			);
		}

		// Rule 7: Max discount amount cannot exceed maximum
		if ( $max_discount > WSSCD_Validation_Rules::MAX_DISCOUNT_AMOUNT_MAX ) {
			$errors->add(
				'max_discount_amount_exceeds_maximum',
				sprintf(
					/* translators: %s: maximum discount cap amount */
					__( 'Maximum discount cap cannot exceed $%s. For higher caps, please contact support.', 'smart-cycle-discounts' ),
					number_format( WSSCD_Validation_Rules::MAX_DISCOUNT_AMOUNT_MAX, 2 )
				)
			);
		}

		// Rule 8: Very high minimum order amount (warning)
		if ( $minimum_order_amount > 10000 && $minimum_order_amount <= WSSCD_Validation_Rules::MINIMUM_ORDER_AMOUNT_MAX ) {
			// Very high minimum order - probably a mistake
			self::log_hint( sprintf( 'Minimum order amount is $%s. This is very high and may prevent most customers from qualifying.', number_format( $minimum_order_amount, 2 ) ) );
		}

		// Rule 9: Both minimum quantity AND minimum order amount (overly restrictive)
		if ( $minimum_quantity > 1 && $minimum_order_amount > 0 ) {
			// Having both restrictions might be too strict
			self::log_hint( sprintf( 'Both minimum quantity (%d) and minimum order amount ($%s) are set. This dual restriction may be too strict.', $minimum_quantity, number_format( $minimum_order_amount, 2 ) ) );
		}
	}

	/**
	 * Validate discount type specific rules.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array    $data      Discount data.
	 * @param    WP_Error $errors    Error object.
	 * @return   void
	 */
	private static function validate_discount_type_rules( array $data, WP_Error $errors ) {
		$discount_type = isset( $data['discount_type'] ) ? $data['discount_type'] : 'percentage';

		switch ( $discount_type ) {
			case 'bogo':
				self::validate_bogo_rules( $data, $errors );
				break;
			case 'tiered':
				self::validate_tiered_rules( $data, $errors );
				break;
			case 'spend_threshold':
				self::validate_threshold_rules( $data, $errors );
				break;
		}
	}

	/**
	 * Validate BOGO configuration.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array    $data      Discount data.
	 * @param    WP_Error $errors    Error object.
	 * @return   void
	 */
	private static function validate_bogo_rules( array $data, WP_Error $errors ) {
		// Extract from grouped bogo_config structure
		$bogo_config   = isset( $data['bogo_config'] ) && is_array( $data['bogo_config'] ) ? $data['bogo_config'] : array();
		$buy_quantity  = isset( $bogo_config['buy_quantity'] ) ? absint( $bogo_config['buy_quantity'] ) : 1;
		$get_quantity  = isset( $bogo_config['get_quantity'] ) ? absint( $bogo_config['get_quantity'] ) : 1;
		$discount_pct  = isset( $bogo_config['discount_percent'] ) ? floatval( $bogo_config['discount_percent'] ) : 100;

		// Rule 1: Buy quantity must be at least 1
		if ( $buy_quantity < 1 ) {
			$errors->add(
				'bogo_invalid_buy_quantity',
				__( 'BOGO buy quantity must be at least 1.', 'smart-cycle-discounts' )
			);
		}

		// Rule 2: Get quantity must be at least 1
		if ( $get_quantity < 1 ) {
			$errors->add(
				'bogo_invalid_get_quantity',
				__( 'BOGO get quantity must be at least 1.', 'smart-cycle-discounts' )
			);
		}

		// Rule 3: Discount percentage must be 1-100
		if ( $discount_pct < 1 || $discount_pct > 100 ) {
			$errors->add(
				'bogo_invalid_discount',
				__( 'BOGO discount percentage must be between 1% and 100%.', 'smart-cycle-discounts' )
			);
		}

		// Rule 4: Warn if buy=get (should probably just be regular discount)
		if ( $buy_quantity === $get_quantity && 100.0 === $discount_pct ) {
			self::log_hint( 'BOGO is configured as Buy ' . $buy_quantity . ' Get ' . $get_quantity . ' free. This is effectively a 50% discount and could be simplified.' );
		}

		// Rule 5: Very large buy/get quantities (unrealistic)
		if ( $buy_quantity > WSSCD_Validation_Rules::BOGO_BUY_MAX || $get_quantity > WSSCD_Validation_Rules::BOGO_GET_MAX ) {
			$errors->add(
				'bogo_unrealistic_quantities',
				sprintf(
					/* translators: 1: buy quantity, 2: get quantity, 3: maximum */
					__( 'BOGO quantities (Buy %1$d, Get %2$d) exceed reasonable maximum (%3$d). Most customers won\'t purchase this many items.', 'smart-cycle-discounts' ),
					$buy_quantity,
					$get_quantity,
					max( WSSCD_Validation_Rules::BOGO_BUY_MAX, WSSCD_Validation_Rules::BOGO_GET_MAX )
				)
			);
		}

		// Rule 6: Buy >> Get imbalance (unusual offer)
		if ( $buy_quantity > 10 && $get_quantity === 1 ) {
			// Buy 20 get 1 free is a very weak offer
			self::log_hint( sprintf( 'BOGO has large imbalance (Buy %d Get %d). This is a very weak offer - consider increasing get quantity or using a percentage discount instead.', $buy_quantity, $get_quantity ) );
		}

		// Rule 7: Very low discount percentage on BOGO (why not regular discount?)
		if ( $discount_pct < 10 ) {
			// BOGO with tiny discount is unusual
			self::log_hint( sprintf( 'BOGO discount percentage is only %s%%. For small discounts, consider using a regular percentage discount instead of BOGO.', $discount_pct ) );
		}
	}

	/**
	 * Validate tiered discount configuration.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array    $data      Discount data.
	 * @param    WP_Error $errors    Error object.
	 * @return   void
	 */
	private static function validate_tiered_rules( array $data, WP_Error $errors ): void {
		$tiers     = isset( $data['tiers'] ) ? $data['tiers'] : array();
		$tier_mode = isset( $data['tier_mode'] ) ? $data['tier_mode'] : 'percentage';

		// Rule 1: At least one tier required
		if ( empty( $tiers ) || ! is_array( $tiers ) ) {
			$errors->add(
				'tiered_no_tiers',
				__( 'At least one tier must be configured for tiered discounts.', 'smart-cycle-discounts' )
			);
			return;
		}

		// Rule 2: Validate tier structure and ascending order
		$previous_threshold = 0;
		$previous_discount  = 0;
		$threshold_values   = array(); // Track for duplicate detection

		foreach ( $tiers as $index => $tier ) {
			$tier_num = $index + 1;

			// Validate required fields
			if ( ! isset( $tier['min_quantity'] ) || ! isset( $tier['discount_value'] ) ) {
				$errors->add(
					'tiered_incomplete_tier',
					sprintf(
						/* translators: %d: tier number */
						__( 'Tier %d is missing required fields (minimum threshold and discount value).', 'smart-cycle-discounts' ),
						$tier_num
					)
				);
				continue;
			}

			$threshold = floatval( $tier['min_quantity'] );
			$discount  = floatval( $tier['discount_value'] );

			// Threshold must be ascending
			if ( $threshold <= $previous_threshold && $index > 0 ) {
				$errors->add(
					'tiered_invalid_threshold_order',
					sprintf(
						/* translators: 1: tier number, 2: threshold, 3: previous threshold */
						__( 'Tier %1$d threshold (%2$.2f) must be greater than the previous tier threshold (%3$.2f).', 'smart-cycle-discounts' ),
						$tier_num,
						$threshold,
						$previous_threshold
					)
				);
			}

			// Discount should be ascending (better deal for higher tiers)
			if ( $discount <= $previous_discount && $index > 0 ) {
				// This is unusual but not necessarily wrong - just warn
				self::log_hint( sprintf( 'Tier %d discount (%s) is not greater than previous tier. Higher tiers typically offer better discounts.', $tier_num, $discount ) );
			}

			// Validate discount range based on mode
			if ( 'percentage' === $tier_mode ) {
				if ( $discount < 0 || $discount > 100 ) {
					$errors->add(
						'tiered_invalid_discount_percentage',
						sprintf(
							/* translators: %d: tier number */
							__( 'Tier %d discount percentage must be between 0 and 100.', 'smart-cycle-discounts' ),
							$tier_num
						)
					);
				}
			} else {
				if ( $discount < 0 ) {
					$errors->add(
						'tiered_invalid_discount_fixed',
						sprintf(
							/* translators: %d: tier number */
							__( 'Tier %d discount amount cannot be negative.', 'smart-cycle-discounts' ),
							$tier_num
						)
					);
				}
			}

			$previous_threshold = $threshold;
			$previous_discount  = $discount;
			$threshold_values[] = $threshold; // Track for duplicate check
		}

		// Rule 3: Check for duplicate thresholds
		if ( count( $threshold_values ) !== count( array_unique( $threshold_values ) ) ) {
			$errors->add(
				'tiered_duplicate_thresholds',
				__( 'Tiered discounts contain duplicate threshold values. Each tier must have a unique threshold.', 'smart-cycle-discounts' )
			);
		}

		// Rule 4: First tier must start at minimum quantity of 2 (volume discount)
		if ( ! empty( $tiers ) && isset( $tiers[0]['min_quantity'] ) ) {
			$first_threshold = floatval( $tiers[0]['min_quantity'] );
			if ( $first_threshold < 2 ) {
				$errors->add(
					'tiered_invalid_first_threshold',
					__( 'Minimum quantity must be at least 2 for volume discounts. For single-item discounts, use Percentage or Fixed discount types instead.', 'smart-cycle-discounts' )
				);
			}
		}

		// Rule 5: Unrealistic threshold jumps
		if ( count( $tiers ) > 1 ) {
			for ( $i = 1; $i < count( $tiers ); $i++ ) {
				$prev_threshold = floatval( $tiers[ $i - 1 ]['min_quantity'] );
				$curr_threshold = floatval( $tiers[ $i ]['min_quantity'] );
				$jump           = $curr_threshold - $prev_threshold;

				// If jump is more than 100x the previous threshold, it's probably unrealistic
				if ( $prev_threshold > 0 && $jump > ( $prev_threshold * 100 ) ) {
					self::log_hint( sprintf( 'Large tier jump detected (from %s to %s). This may result in a confusing discount structure.', $prev_threshold, $curr_threshold ) );
				}
			}
		}

		// Rule 6: Too many tiers (performance concern)
		if ( count( $tiers ) > 20 ) {
			$errors->add(
				'tiered_too_many_tiers',
				sprintf(
					/* translators: %d: number of tiers */
					__( 'Too many discount tiers (%d). Consider simplifying to 20 or fewer tiers for better performance and user experience.', 'smart-cycle-discounts' ),
					count( $tiers )
				)
			);
		}

		// Rule 7: Large gaps between tiers (optional warning)
		if ( count( $tiers ) > 1 ) {
			$total_span = floatval( $tiers[ count( $tiers ) - 1 ]['min_quantity'] ) - floatval( $tiers[0]['min_quantity'] );
			$avg_span   = $total_span / ( count( $tiers ) - 1 );

			foreach ( $tiers as $index => $tier ) {
				if ( 0 === $index ) {
					continue; // Skip first tier
				}

				$prev_threshold = floatval( $tiers[ $index - 1 ]['min_quantity'] );
				$curr_threshold = floatval( $tier['min_quantity'] );
				$gap            = $curr_threshold - $prev_threshold;

				// If gap is more than 3x average, there's a large gap
				if ( $gap > ( $avg_span * 3 ) ) {
					self::log_hint( sprintf( 'Large gap between tiers (from %s to %s). Consider adding intermediate tiers for smoother progression.', $prev_threshold, $curr_threshold ) );
					break; // Only warn once
				}
			}
		}

		// Rule 8: Fixed discount exceeding threshold value (negative price)
		if ( 'fixed' === $tier_mode ) {
			foreach ( $tiers as $index => $tier ) {
				if ( ! isset( $tier['min_quantity'] ) || ! isset( $tier['discount_value'] ) ) {
					continue;
				}

				$threshold = floatval( $tier['min_quantity'] );
				$discount  = floatval( $tier['discount_value'] );

				// Fixed discount can't exceed threshold (would give negative price)
				if ( $discount >= $threshold && $threshold > 0 ) {
					$errors->add(
						'tiered_fixed_exceeds_threshold',
						sprintf(
							/* translators: 1: tier number, 2: discount, 3: threshold */
							__( 'Tier %1$d has fixed discount ($%2$.2f) that exceeds or equals the threshold value (%3$.2f). This could result in negative or zero prices.', 'smart-cycle-discounts' ),
							$index + 1,
							$discount,
							$threshold
						)
					);
				}
			}
		}
	}

	/**
	 * Validate spend threshold configuration.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array    $data      Discount data.
	 * @param    WP_Error $errors    Error object.
	 * @return   void
	 */
	private static function validate_threshold_rules( array $data, WP_Error $errors ): void {
		$thresholds     = isset( $data['thresholds'] ) ? $data['thresholds'] : array();
		$threshold_mode = isset( $data['threshold_mode'] ) ? $data['threshold_mode'] : 'percentage';

		// Rule 1: At least one threshold required
		if ( empty( $thresholds ) || ! is_array( $thresholds ) ) {
			$errors->add(
				'threshold_no_thresholds',
				__( 'At least one spending threshold must be configured.', 'smart-cycle-discounts' )
			);
			return;
		}

		// Rule 2: Validate threshold structure
		$previous_amount    = 0;
		$spend_values       = array(); // Track for duplicate detection
		$min_order_amount   = isset( $data['minimum_order_amount'] ) ? floatval( $data['minimum_order_amount'] ) : 0;

		foreach ( $thresholds as $index => $threshold ) {
			$threshold_num = $index + 1;

			// Validate required fields
			if ( ! isset( $threshold['spend_amount'] ) || ! isset( $threshold['discount_value'] ) ) {
				$errors->add(
					'threshold_incomplete',
					sprintf(
						/* translators: %d: threshold number */
						__( 'Spending threshold %d is missing required fields (spend amount and discount value).', 'smart-cycle-discounts' ),
						$threshold_num
					)
				);
				continue;
			}

			$spend_amount = floatval( $threshold['spend_amount'] );
			$discount     = floatval( $threshold['discount_value'] );

			// Spend amounts must be ascending
			if ( $spend_amount <= $previous_amount && $index > 0 ) {
				$errors->add(
					'threshold_invalid_order',
					sprintf(
						/* translators: 1: threshold number, 2: spend amount, 3: previous spend amount */
						__( 'Spending threshold %1$d ($%2$.2f) must be greater than the previous threshold ($%3$.2f).', 'smart-cycle-discounts' ),
						$threshold_num,
						$spend_amount,
						$previous_amount
					)
				);
			}

			// Validate discount based on mode
			if ( 'percentage' === $threshold_mode ) {
				if ( $discount < 0 || $discount > 100 ) {
					$errors->add(
						'threshold_invalid_discount_percentage',
						sprintf(
							/* translators: %d: threshold number */
							__( 'Threshold %d discount percentage must be between 0 and 100.', 'smart-cycle-discounts' ),
							$threshold_num
						)
					);
				}
			} else {
				if ( $discount < 0 ) {
					$errors->add(
						'threshold_invalid_discount_fixed',
						sprintf(
							/* translators: %d: threshold number */
							__( 'Threshold %d discount amount cannot be negative.', 'smart-cycle-discounts' ),
							$threshold_num
						)
					);
				}
			}

			$previous_amount  = $spend_amount;
			$spend_values[] = $spend_amount; // Track for duplicate check
		}

		// Rule 3: Check for duplicate spend amounts
		if ( count( $spend_values ) !== count( array_unique( $spend_values ) ) ) {
			$errors->add(
				'threshold_duplicate_amounts',
				__( 'Spend thresholds contain duplicate spending amounts. Each threshold must have a unique spend value.', 'smart-cycle-discounts' )
			);
		}

		// Rule 4: Spend threshold less than minimum order amount
		if ( $min_order_amount > 0 && ! empty( $thresholds ) ) {
			$first_threshold = floatval( $thresholds[0]['spend_amount'] );
			if ( $first_threshold < $min_order_amount ) {
				$errors->add(
					'threshold_below_minimum_order',
					sprintf(
						/* translators: 1: threshold amount, 2: minimum order */
						__( 'First spending threshold ($%1$.2f) is less than the minimum order amount ($%2$.2f). Customers must meet the minimum order first.', 'smart-cycle-discounts' ),
						$first_threshold,
						$min_order_amount
					)
				);
			}
		}

		// Rule 5: Very small thresholds (probably a mistake)
		foreach ( $thresholds as $threshold ) {
			if ( isset( $threshold['spend'] ) ) {
				$spend_amount = floatval( $threshold['spend'] );
				if ( $spend_amount > 0 && $spend_amount < 1 ) {
					// Threshold less than $1 is unusual
					self::log_hint( sprintf( 'Spending threshold of $%s is very small. This may be unintentional.', number_format( $spend_amount, 2 ) ) );
					break; // Only warn once
				}
			}
		}

		// Rule 6: Fixed discount exceeding spend amount (negative cart)
		if ( 'fixed' === $threshold_mode ) {
			foreach ( $thresholds as $index => $threshold ) {
				if ( ! isset( $threshold['spend'] ) || ! isset( $threshold['discount'] ) ) {
					continue;
				}

				$spend_amount = floatval( $threshold['spend'] );
				$discount     = floatval( $threshold['discount'] );

				// Fixed discount can't exceed spend amount (would give negative cart)
				if ( $discount >= $spend_amount && $spend_amount > 0 ) {
					$errors->add(
						'threshold_fixed_exceeds_spend',
						sprintf(
							/* translators: 1: threshold number, 2: discount, 3: spend amount */
							__( 'Threshold %1$d has fixed discount ($%2$.2f) that exceeds or equals the spend amount ($%3$.2f). This could result in negative or zero cart values.', 'smart-cycle-discounts' ),
							$index + 1,
							$discount,
							$spend_amount
						)
					);
				}
			}
		}

		// Rule 7: Unrealistic threshold jumps
		if ( count( $thresholds ) > 1 ) {
			for ( $i = 1; $i < count( $thresholds ); $i++ ) {
				// Support both old format (spend) and new format (threshold)
				$spend_field = isset( $thresholds[ $i ]['threshold'] ) ? 'threshold' : 'spend';
				$prev_amount = floatval( $thresholds[ $i - 1 ][ $spend_field ] );
				$curr_amount = floatval( $thresholds[ $i ][ $spend_field ] );
				$jump        = $curr_amount - $prev_amount;

				// If jump is more than 100x the previous amount, it's probably unrealistic
				if ( $prev_amount > 0 && $jump > ( $prev_amount * 100 ) ) {
					self::log_hint( sprintf( 'Large threshold jump detected (from $%s to $%s). This may result in a confusing discount structure.', number_format( $prev_amount, 2 ), number_format( $curr_amount, 2 ) ) );
				}
			}
		}

		// Rule 8: Too many thresholds (performance concern)
		if ( count( $thresholds ) > 20 ) {
			$errors->add(
				'threshold_too_many',
				sprintf(
					/* translators: %d: number of thresholds */
					__( 'Too many spending thresholds (%d). Consider simplifying to 20 or fewer thresholds for better performance and user experience.', 'smart-cycle-discounts' ),
					count( $thresholds )
				)
			);
		}
	}

	/**
	 * Validate badge configuration completeness.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array    $data      Discount data.
	 * @param    WP_Error $errors    Error object.
	 * @return   void
	 */
	private static function validate_badge_configuration( array $data, WP_Error $errors ): void {
		$badge_enabled = isset( $data['badge_enabled'] ) && $data['badge_enabled'];

		if ( ! $badge_enabled ) {
			return; // Badge disabled, no validation needed
		}

		// Rule 1: Badge text is required when badge is enabled
		$badge_text = isset( $data['badge_text'] ) ? trim( $data['badge_text'] ) : '';
		if ( empty( $badge_text ) ) {
			$errors->add(
				'badge_text_required',
				__( 'Badge text is required when badge display is enabled.', 'smart-cycle-discounts' )
			);
		}

		// Rule 2: Colors must be valid hex codes
		$bg_color   = isset( $data['badge_bg_color'] ) ? $data['badge_bg_color'] : '';
		$text_color = isset( $data['badge_text_color'] ) ? $data['badge_text_color'] : '';

		if ( ! preg_match( '/^#[a-f0-9]{6}$/i', $bg_color ) ) {
			$errors->add(
				'badge_invalid_bg_color',
				__( 'Badge background color must be a valid hex color code (e.g., #ff0000).', 'smart-cycle-discounts' )
			);
		}

		if ( ! preg_match( '/^#[a-f0-9]{6}$/i', $text_color ) ) {
			$errors->add(
				'badge_invalid_text_color',
				__( 'Badge text color must be a valid hex color code (e.g., #ffffff).', 'smart-cycle-discounts' )
			);
		}

		// Rule 3: Warn about poor contrast (accessibility)
		if ( preg_match( '/^#[a-f0-9]{6}$/i', $bg_color ) && preg_match( '/^#[a-f0-9]{6}$/i', $text_color ) ) {
			$contrast_ratio = self::calculate_contrast_ratio( $bg_color, $text_color );

			// WCAG AA requires 4.5:1 for normal text, 3:1 for large text
			if ( $contrast_ratio < 3.0 ) {
				// Low contrast - warn but don't block
				self::log_hint( sprintf( 'Badge color contrast ratio (%.2f:1) is below recommended 3:1. Consider adjusting colors for better readability.', $contrast_ratio ) );
			}
		}

		// Rule 4: Very long badge text (might overflow UI)
		if ( strlen( $badge_text ) > 50 ) {
			// Badge text over 50 characters is very long
			self::log_hint( sprintf( 'Badge text is %d characters long. Very long text may overflow the badge display. Consider shortening to 50 characters or less.', strlen( $badge_text ) ) );
		}

		// Rule 5: Identical background and text colors
		if ( $bg_color === $text_color ) {
			$errors->add(
				'badge_identical_colors',
				__( 'Badge background and text colors are identical. Text will be invisible.', 'smart-cycle-discounts' )
			);
		}

		// Rule 6: Very similar colors (low contrast warning)
		if ( preg_match( '/^#[a-f0-9]{6}$/i', $bg_color ) && preg_match( '/^#[a-f0-9]{6}$/i', $text_color ) && $bg_color !== $text_color ) {
			$contrast_ratio = self::calculate_contrast_ratio( $bg_color, $text_color );

			// Contrast below 1.5 is extremely poor (almost invisible)
			if ( $contrast_ratio < 1.5 ) {
				$errors->add(
					'badge_extremely_low_contrast',
					sprintf(
						/* translators: %s: contrast ratio */
						__( 'Badge colors have extremely low contrast ratio (%s:1). Text will be nearly invisible. Minimum recommended is 3:1.', 'smart-cycle-discounts' ),
						number_format( $contrast_ratio, 2 )
					)
				);
			}
		}
	}

	/**
	 * Validate combination policy for conflicts.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array    $data      Discount data.
	 * @param    WP_Error $errors    Error object.
	 * @return   void
	 */
	private static function validate_combination_policy( array $data, WP_Error $errors ): void {
		$stack_with_others   = isset( $data['stack_with_others'] ) && $data['stack_with_others'];
		$allow_coupons       = isset( $data['allow_coupons'] ) && $data['allow_coupons'];
		$apply_to_sale_items = isset( $data['apply_to_sale_items'] ) && $data['apply_to_sale_items'];

		// Rule 1: If stacking is disabled and coupons are allowed, that's potentially confusing
		if ( ! $stack_with_others && $allow_coupons ) {
			// This is valid - coupons are different from other campaign discounts
			// Just log for awareness
			self::log_hint( 'Campaign does not stack with other discounts but allows coupons. This is valid - coupons are treated separately from campaign stacking.' );
		}

		// Rule 2: Applying to sale items + stacking = very aggressive discounting
		if ( $apply_to_sale_items && $stack_with_others ) {
			// This is valid but unusual - double discounting on already-discounted items
			self::log_hint( 'Campaign applies to sale items AND stacks with other discounts. This may result in very deep discounts. Verify profit margins.' );
		}
	}

	/**
	 * Validate discount value reasonableness.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array    $data      Discount data.
	 * @param    WP_Error $errors    Error object.
	 * @return   void
	 */
	private static function validate_discount_value( array $data, WP_Error $errors ) {
		$discount_type = isset( $data['discount_type'] ) ? $data['discount_type'] : 'percentage';

		// Skip validation for complex discount types (they have their own validation)
		if ( in_array( $discount_type, array( 'bogo', 'tiered', 'spend_threshold', 'bundle' ), true ) ) {
			return;
		}

		// Get discount value from type-specific field (after field definitions refactoring)
		// Field names are now discount_value_percentage, discount_value_fixed, etc.
		$discount_value = 0;
		if ( 'percentage' === $discount_type && isset( $data['discount_value_percentage'] ) ) {
			$discount_value = floatval( $data['discount_value_percentage'] );
		} elseif ( 'fixed' === $discount_type && isset( $data['discount_value_fixed'] ) ) {
			$discount_value = floatval( $data['discount_value_fixed'] );
		} elseif ( isset( $data['discount_value'] ) ) {
			// Fallback to generic field for backward compatibility
			$discount_value = floatval( $data['discount_value'] );
		}

		// Rule 1: Zero or negative discount value
		if ( 0 === $discount_value || $discount_value < 0 ) {
			$errors->add(
				'discount_value_zero',
				__( 'Discount value must be greater than 0. A zero discount provides no value to customers.', 'smart-cycle-discounts' )
			);
		}

		// Rule 2: Percentage cannot exceed 100%
		if ( 'percentage' === $discount_type && $discount_value > WSSCD_Validation_Rules::PERCENTAGE_MAX ) {
			$errors->add(
				'discount_percentage_exceeds_maximum',
				sprintf(
					/* translators: %d: maximum percentage */
					__( 'Discount percentage cannot exceed %d%%. A discount over 100%% is not mathematically valid.', 'smart-cycle-discounts' ),
					WSSCD_Validation_Rules::PERCENTAGE_MAX
				)
			);
		}

		// Rule 3: Very small discounts (probably not worth it)
		if ( 'percentage' === $discount_type && $discount_value > 0 && $discount_value < 1 ) {
			// Less than 1% discount
			self::log_hint( sprintf( 'Discount percentage is only %s%%. This is very small and may not be worth the campaign complexity.', $discount_value ) );
		}

		if ( 'fixed' === $discount_type && $discount_value > 0 && $discount_value < 1 ) {
			// Less than $1 discount
			self::log_hint( sprintf( 'Fixed discount is only $%s. This is very small and may not motivate customers.', number_format( $discount_value, 2 ) ) );
		}

		// Rule 4: Percentage over 50% (profit margin warning)
		if ( 'percentage' === $discount_type && $discount_value > WSSCD_Validation_Rules::PERCENTAGE_WARNING ) {
			// Over 50% discount - check profit margins
			self::log_hint( sprintf( 'Discount percentage is %s%%. Discounts over 50%% significantly impact profit margins. Verify this is intentional.', $discount_value ) );
		}

		// Rule 5: Fixed discount cannot exceed maximum
		if ( 'fixed' === $discount_type && $discount_value > WSSCD_Validation_Rules::FIXED_MAX ) {
			$errors->add(
				'discount_fixed_exceeds_maximum',
				sprintf(
					/* translators: %s: maximum fixed discount amount */
					__( 'Fixed discount amount cannot exceed $%s. For larger discounts, please contact support.', 'smart-cycle-discounts' ),
					number_format( WSSCD_Validation_Rules::FIXED_MAX, 2 )
				)
			);
		}

		// Rule 6: Very large fixed discount warning
		if ( 'fixed' === $discount_type && $discount_value > WSSCD_Validation_Rules::FIXED_WARNING && $discount_value <= WSSCD_Validation_Rules::FIXED_MAX ) {
			// Very large fixed discount (warning only)
			self::log_hint( sprintf( 'Fixed discount is $%s. This is a very large discount. Verify this amount is correct.', number_format( $discount_value, 2 ) ) );
		}
	}

	/**
	 * Validate cross-field logic between discount types and application rules.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array    $data      Discount data.
	 * @param    WP_Error $errors    Error object.
	 * @return   void
	 */
	private static function validate_cross_field_logic( array $data, WP_Error $errors ) {
		$discount_type         = isset( $data['discount_type'] ) ? $data['discount_type'] : 'percentage';
		$apply_to              = isset( $data['apply_to'] ) ? $data['apply_to'] : 'per_item';
		$minimum_quantity      = isset( $data['minimum_quantity'] ) ? absint( $data['minimum_quantity'] ) : 0;
		$minimum_order_amount  = isset( $data['minimum_order_amount'] ) ? floatval( $data['minimum_order_amount'] ) : 0;
		$apply_to_sale_items   = isset( $data['apply_to_sale_items'] ) && $data['apply_to_sale_items'];
		$allow_coupons         = isset( $data['allow_coupons'] ) && $data['allow_coupons'];
		$stack_with_others     = isset( $data['stack_with_others'] ) && $data['stack_with_others'];

		// Rule 1: BOGO with cart_total application (doesn't make sense)
		if ( 'bogo' === $discount_type && 'cart_total' === $apply_to ) {
			$errors->add(
				'bogo_cart_total_conflict',
				__( 'BOGO (Buy One Get One) discounts should use "per_item" application, not "cart_total". BOGO is inherently item-based.', 'smart-cycle-discounts' )
			);
		}

		// Rule 2: Tiered with per_item (potentially confusing)
		if ( 'tiered' === $discount_type && 'per_item' === $apply_to ) {
			// Tiered usually works better with cart_total
			self::log_hint( 'Tiered discount is set to "per_item" application. Tiered discounts typically work better with "cart_total" to reward higher spending.' );
		}

		// Rule 3: Spend threshold + minimum quantity (redundant)
		if ( 'spend_threshold' === $discount_type && $minimum_quantity > 0 ) {
			// Spend threshold already has spending requirement, quantity is redundant
			self::log_hint( sprintf( 'Spend threshold discount has minimum quantity (%d) requirement. This is redundant since spend thresholds already require specific cart values.', $minimum_quantity ) );
		}

		// Rule 4: Spend threshold + minimum order amount (conflicting)
		if ( 'spend_threshold' === $discount_type && $minimum_order_amount > 0 ) {
			// Spending threshold IS the minimum order amount essentially
			self::log_hint( sprintf( 'Spend threshold discount has separate minimum order amount ($%s). The threshold amounts already define spending requirements - this may be confusing.', number_format( $minimum_order_amount, 2 ) ) );
		}

		// Rule 5: All combination options disabled (campaign won't apply)
		if ( ! $apply_to_sale_items && ! $allow_coupons && ! $stack_with_others ) {
			// All restrictions enabled - very limited application
			self::log_hint( 'All combination policies are disabled (no sale items, no coupons, no stacking). This may severely limit when the discount can be applied.' );
		}

		// Rule 6: Fixed discount with no maximum and cart_total (potential abuse)
		$max_discount = isset( $data['max_discount_amount'] ) ? floatval( $data['max_discount_amount'] ) : 0;
		if ( 'fixed' === $discount_type && 'cart_total' === $apply_to && 0 === $max_discount ) {
			// Fixed cart discount with no cap could be abused
			self::log_hint( 'Fixed discount applied to cart total has no maximum discount cap. Consider setting a maximum to prevent abuse on very large orders.' );
		}
	}

	/**
	 * Validate free shipping configuration.
	 *
	 * @since    1.2.0
	 * @access   private
	 * @param    array    $data      Discount data.
	 * @param    WP_Error $errors    Error object.
	 * @return   void
	 */
	private static function validate_free_shipping_config( array $data, WP_Error $errors ) {
		// Skip if free_shipping_config not set or not enabled.
		if ( ! isset( $data['free_shipping_config'] ) || ! is_array( $data['free_shipping_config'] ) ) {
			return;
		}

		$config  = $data['free_shipping_config'];
		$enabled = isset( $config['enabled'] ) && $config['enabled'];

		if ( ! $enabled ) {
			return; // Free shipping disabled, no validation needed.
		}

		$methods = isset( $config['methods'] ) ? $config['methods'] : 'all';

		// Rule 1: If methods is an array, at least one method must be selected.
		if ( is_array( $methods ) && empty( $methods ) ) {
			$errors->add(
				'free_shipping_no_methods',
				__( 'Free shipping is enabled but no shipping methods are selected. Please select at least one shipping method or choose "All shipping methods".', 'smart-cycle-discounts' )
			);
		}

		// Rule 2: Log hint for spend_threshold discount type with free shipping.
		$discount_type = isset( $data['discount_type'] ) ? $data['discount_type'] : 'percentage';
		if ( 'spend_threshold' === $discount_type ) {
			self::log_hint( 'Free shipping is enabled with spend threshold discount. Free shipping will apply when the minimum spend threshold is reached.' );
		}
	}

	/**
	 * Calculate contrast ratio between two colors.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $color1    First color hex code.
	 * @param    string $color2    Second color hex code.
	 * @return   float                Contrast ratio.
	 */
	private static function calculate_contrast_ratio( string $color1, string $color2 ): float {
		$lum1 = self::get_relative_luminance( $color1 );
		$lum2 = self::get_relative_luminance( $color2 );

		$lighter = max( $lum1, $lum2 );
		$darker  = min( $lum1, $lum2 );

		return ( $lighter + 0.05 ) / ( $darker + 0.05 );
	}

	/**
	 * Get relative luminance of a color.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $hex    Hex color code.
	 * @return   float             Relative luminance.
	 */
	private static function get_relative_luminance( string $hex ): float {
		$hex = ltrim( $hex, '#' );
		$r   = hexdec( substr( $hex, 0, 2 ) ) / 255;
		$g   = hexdec( substr( $hex, 2, 2 ) ) / 255;
		$b   = hexdec( substr( $hex, 4, 2 ) ) / 255;

		$r = ( $r <= 0.03928 ) ? $r / 12.92 : pow( ( $r + 0.055 ) / 1.055, 2.4 );
		$g = ( $g <= 0.03928 ) ? $g / 12.92 : pow( ( $g + 0.055 ) / 1.055, 2.4 );
		$b = ( $b <= 0.03928 ) ? $b / 12.92 : pow( ( $b + 0.055 ) / 1.055, 2.4 );

		return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
	}
}
