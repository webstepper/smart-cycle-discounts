<?php
/**
 * Campaign Cross-Step Validator Class
 *
 * Validates compatibility and consistency ACROSS wizard steps (discounts, products, schedule).
 * This validator is called at the review step to ensure all step configurations work together.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/validation
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress/plugins/smart-cycle-discounts/
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Campaign Cross-Step Validator Class
 *
 * Validates cross-step compatibility for 30+ types of integration issues, configuration
 * conflicts, and business rule violations that span multiple wizard steps.
 *
 * SCOPE: Cross-step validation only
 * - Discounts + Products compatibility
 * - Discounts + Schedule compatibility
 * - Products + Schedule compatibility
 * - Three-way validation scenarios
 * - Campaign-level business rules
 *
 * NOT IN SCOPE (handled by step validators):
 * - Internal discounts step validation
 * - Internal products step validation
 * - Internal schedule step validation
 *
 * Validation scenarios covered:
 * 1-8:   Discounts + Products compatibility (from discounts validator)
 * 9-15:  Discounts + Filters compatibility (from discounts validator)
 * 16-20: Discounts + Schedule compatibility (new scenarios)
 * 21-25: Products + Schedule compatibility (new scenarios)
 * 26-30: Three-way validation and campaign-level rules (new scenarios)
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/validation
 */
class WSSCD_Campaign_Cross_Validator {

	/**
	 * Validate cross-step compatibility.
	 *
	 * Checks that configurations from different wizard steps work together properly.
	 * Called at the review step to catch integration issues before campaign activation.
	 *
	 * @since    1.0.0
	 * @param    array    $data      Complete campaign data (all steps).
	 * @param    WP_Error $errors    Error object to add errors to.
	 * @return   void
	 */
	public static function validate( array $data, WP_Error $errors ) {
		if ( ! class_exists( 'WSSCD_Validation_Rules' ) ) {
			require_once WSSCD_INCLUDES_DIR . 'core/validation/class-validation-rules.php';
		}

		// Validate discounts + products compatibility
		self::validate_discounts_products( $data, $errors );

		// Validate discounts + schedule compatibility
		self::validate_discounts_schedule( $data, $errors );

		// Validate products + schedule compatibility
		self::validate_products_schedule( $data, $errors );

		// Three-way validation
		self::validate_three_way( $data, $errors );

		// Campaign-level business rules
		self::validate_campaign_rules( $data, $errors );
	}

	/**
	 * Validate discounts + products compatibility.
	 *
	 * Scenarios 1-15: Moved from discounts-step-validator
	 * - Product selection type vs discount type compatibility
	 * - Filter conditions vs discount configuration
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array    $data      Campaign data.
	 * @param    WP_Error $errors    Error object.
	 * @return   void
	 */
	private static function validate_discounts_products( array $data, WP_Error $errors ) {
		$discount_type           = isset( $data['discount_type'] ) ? $data['discount_type'] : '';
		$product_selection_type  = isset( $data['product_selection_type'] ) ? $data['product_selection_type'] : '';
		$has_conditions          = isset( $data['conditions'] ) && ! empty( $data['conditions'] );

		// Scenario 1: BOGO with all_products (warning - works but inefficient)
		if ( 'bogo' === $discount_type && 'all_products' === $product_selection_type ) {
			$errors->add(
				'cross_bogo_all_products',
				__( 'BOGO discount applied to all products may cause performance issues. Consider using specific products or filters.', 'smart-cycle-discounts' ),
				array( 'severity' => 'warning' )
			);
		}

		// Scenario 2: Tiered discount with random_products (warning - unpredictable)
		if ( 'tiered' === $discount_type && 'random_products' === $product_selection_type ) {
			$errors->add(
				'cross_tiered_random_products',
				__( 'Tiered discounts with randomly selected products may create inconsistent customer experiences. Consider using specific products or smart selection.', 'smart-cycle-discounts' ),
				array( 'severity' => 'warning' )
			);
		}

		// Scenario 3: Spend threshold with single specific product (warning - illogical)
		if ( 'spend_threshold' === $discount_type && 'specific_products' === $product_selection_type ) {
			$product_ids = isset( $data['product_ids'] ) ? $data['product_ids'] : array();
			if ( is_array( $product_ids ) && 1 === count( $product_ids ) ) {
				$errors->add(
					'cross_threshold_single_product',
					__( 'Spend threshold discount with a single product is unusual. Ensure minimum spend threshold makes sense for this product.', 'smart-cycle-discounts' ),
					array( 'severity' => 'info' )
				);
			}
		}

		// Scenario 4: Bundle discount without specific products (critical - needs grouped items)
		if ( 'bundle' === $discount_type && 'all_products' === $product_selection_type ) {
			$errors->add(
				'cross_bundle_all_products',
				__( 'Bundle discounts require specific products or categories. "All Products" selection is not recommended for bundle discounts.', 'smart-cycle-discounts' ),
				array( 'severity' => 'critical' )
			);
		}

		// Scenario 5: Fixed discount with broad product selection (warning - profit margin risk)
		if ( 'fixed' === $discount_type && 'all_products' === $product_selection_type ) {
			// Get fixed discount value from type-specific field (after field definitions refactoring)
			$fixed_amount = isset( $data['discount_value_fixed'] ) ? floatval( $data['discount_value_fixed'] ) : 0;
			if ( 0 === $fixed_amount && isset( $data['discount_value'] ) ) {
				$fixed_amount = floatval( $data['discount_value'] ); // Fallback for backward compatibility
			}
			if ( $fixed_amount > 50 ) {
				$errors->add(
					'cross_fixed_all_products',
					sprintf(
						/* translators: %s: Fixed amount */
						__( 'Fixed discount of %s applied to all products may cause negative margins on low-priced items. Consider using percentage discount or specific products.', 'smart-cycle-discounts' ),
						wc_price( $fixed_amount )
					),
					array( 'severity' => 'warning' )
				);
			}
		}

		// Scenario 6: BOGO with very large random count (warning - complexity)
		if ( 'bogo' === $discount_type && 'random_products' === $product_selection_type ) {
			$random_count = isset( $data['random_count'] ) ? intval( $data['random_count'] ) : 0;
			if ( $random_count > 20 ) {
				$errors->add(
					'cross_bogo_many_random',
					sprintf(
						/* translators: %d: Random product count */
						__( 'BOGO discount with %d random products is complex to manage. Consider using specific product selection.', 'smart-cycle-discounts' ),
						$random_count
					),
					array( 'severity' => 'info' )
				);
			}
		}

		// Scenario 7: Percentage discount with smart_selection (optimization note)
		if ( 'percentage' === $discount_type && 'smart_selection' === $product_selection_type ) {
			$errors->add(
				'cross_percentage_smart',
				__( 'Percentage discount with smart selection is a powerful combination for revenue optimization.', 'smart-cycle-discounts' ),
				array( 'severity' => 'info' )
			);
		}

		// Scenario 8: Multiple discount types with complex filters (performance warning)
		if ( $has_conditions && in_array( $discount_type, array( 'tiered', 'bundle', 'bogo' ), true ) ) {
			$condition_count = count( $data['conditions'] );
			if ( $condition_count > 5 ) {
				$errors->add(
					'cross_complex_discount_many_filters',
					sprintf(
						/* translators: 1: Discount type, 2: Condition count */
						__( '%1$s discount with %2$d filter conditions may impact performance. Consider simplifying filters or using categories.', 'smart-cycle-discounts' ),
						ucfirst( $discount_type ),
						$condition_count
					),
					array( 'severity' => 'warning' )
				);
			}
		}

		// Scenarios 9-15: Filter conditions compatibility
		if ( $has_conditions ) {
			self::validate_discount_filter_conditions( $data, $errors );
		}
	}

	/**
	 * Validate discount + filter conditions compatibility.
	 *
	 * Moved from discounts-step-validator. Checks that discount configuration
	 * is compatible with advanced filter conditions.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array    $data      Campaign data.
	 * @param    WP_Error $errors    Error object.
	 * @return   void
	 */
	private static function validate_discount_filter_conditions( array $data, WP_Error $errors ) {
		$discount_type = isset( $data['discount_type'] ) ? $data['discount_type'] : '';
		$conditions    = isset( $data['conditions'] ) ? $data['conditions'] : array();

		// Scenario 9: Fixed discount with price-based filters (margin risk)
		if ( 'fixed' === $discount_type ) {
			// Get fixed discount value from type-specific field (after field definitions refactoring)
			$fixed_amount = isset( $data['discount_value_fixed'] ) ? floatval( $data['discount_value_fixed'] ) : 0;
			if ( 0 === $fixed_amount && isset( $data['discount_value'] ) ) {
				$fixed_amount = floatval( $data['discount_value'] ); // Fallback for backward compatibility
			}
			$has_price_lower = false;

			foreach ( $conditions as $condition ) {
				$property = $condition['condition_type'] ?? '';
				$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';

				if ( in_array( $property, array( 'price', 'current_price', 'sale_price' ), true ) ) {
					if ( in_array( $operator, array( '<', '<=', 'between' ), true ) ) {
						$has_price_lower = true;
						break;
					}
				}
			}

			if ( $has_price_lower && $fixed_amount > 20 ) {
				$errors->add(
					'cross_fixed_discount_low_price_filter',
					sprintf(
						/* translators: %s: Fixed amount */
						__( 'Fixed discount of %s combined with low-price filters may result in negative margins. Verify profit margins for filtered products.', 'smart-cycle-discounts' ),
						wc_price( $fixed_amount )
					),
					array( 'severity' => 'warning' )
				);
			}
		}

		// Scenario 10: BOGO with low stock filters (inventory warning)
		if ( 'bogo' === $discount_type ) {
			foreach ( $conditions as $condition ) {
				$property = $condition['condition_type'] ?? '';
				$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';

				if ( 'stock_quantity' === $property && in_array( $operator, array( '<', '<=', 'between' ), true ) ) {
					$errors->add(
						'cross_bogo_low_stock_filter',
						__( 'BOGO discount targeting low-stock products may quickly deplete inventory. Monitor stock levels closely.', 'smart-cycle-discounts' ),
						array( 'severity' => 'warning' )
					);
					break;
				}
			}
		}

		// Scenario 11: Percentage discount with high-value filters (warning - verify margin)
		if ( 'percentage' === $discount_type ) {
			// Get percentage discount value from type-specific field (after field definitions refactoring)
			$percentage = isset( $data['discount_value_percentage'] ) ? floatval( $data['discount_value_percentage'] ) : 0;
			if ( 0 === $percentage && isset( $data['discount_value'] ) ) {
				$percentage = floatval( $data['discount_value'] ); // Fallback for backward compatibility
			}
			$has_high_price  = false;

			foreach ( $conditions as $condition ) {
				$property = $condition['condition_type'] ?? '';
				$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
				$value    = isset( $condition['value'] ) ? $condition['value'] : '';

				if ( in_array( $property, array( 'price', 'current_price' ), true ) ) {
					if ( '>' === $operator && ! empty( $values ) && floatval( $value ) > 1000 ) {
						$has_high_price = true;
					}
				}
			}

			if ( $has_high_price && $percentage > 30 ) {
				$errors->add(
					'cross_percentage_high_value_filter',
					sprintf(
						/* translators: %d: Percentage */
						__( '%d%% discount on high-value products (>$1000) may significantly impact revenue. Verify this discount strategy.', 'smart-cycle-discounts' ),
						$percentage
					),
					array( 'severity' => 'info' )
				);
			}
		}

		// Scenario 12: Tiered discount with rating filters (optimization)
		if ( 'tiered' === $discount_type ) {
			$has_rating_filter = false;

			foreach ( $conditions as $condition ) {
				$property = $condition['condition_type'] ?? '';
				if ( in_array( $property, array( 'average_rating', 'rating' ), true ) ) {
					$has_rating_filter = true;
					break;
				}
			}

			if ( $has_rating_filter ) {
				$errors->add(
					'cross_tiered_rating_filter',
					__( 'Tiered discount targeting high-rated products is a good strategy for promoting quality items.', 'smart-cycle-discounts' ),
					array( 'severity' => 'info' )
				);
			}
		}

		// Scenario 13: Spend threshold with total_sales filter (strategic alignment)
		if ( 'spend_threshold' === $discount_type ) {
			$has_sales_filter = false;

			foreach ( $conditions as $condition ) {
				$property = $condition['condition_type'] ?? '';
				if ( 'total_sales' === $property ) {
					$has_sales_filter = true;
					break;
				}
			}

			if ( $has_sales_filter ) {
				$errors->add(
					'cross_threshold_sales_filter',
					__( 'Spend threshold on best-selling products can drive higher cart values effectively.', 'smart-cycle-discounts' ),
					array( 'severity' => 'info' )
				);
			}
		}

		// Scenario 14: BOGO with on_sale filter (stacking warning)
		if ( 'bogo' === $discount_type ) {
			foreach ( $conditions as $condition ) {
				$property = $condition['condition_type'] ?? '';
				$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';

				if ( 'on_sale' === $property && '=' === $operator ) {
					$value = isset( $condition['value'] ) ? $condition['value'] : '';
					if ( ! empty( $value ) && 'true' === $value ) {
						$errors->add(
							'cross_bogo_onsale_stacking',
							__( 'BOGO discount applied to products already on sale. Verify that discount stacking is intentional and profit margins are acceptable.', 'smart-cycle-discounts' ),
							array( 'severity' => 'warning' )
						);
						break;
					}
				}
			}
		}

		// Scenario 15: Bundle discount with virtual/downloadable filter (logical)
		if ( 'bundle' === $discount_type ) {
			$has_virtual_filter = false;

			foreach ( $conditions as $condition ) {
				$property = $condition['condition_type'] ?? '';
				if ( in_array( $property, array( 'virtual', 'downloadable' ), true ) ) {
					$has_virtual_filter = true;
					break;
				}
			}

			if ( $has_virtual_filter ) {
				$errors->add(
					'cross_bundle_virtual_filter',
					__( 'Bundle discount for virtual/downloadable products is a great strategy for digital product packages.', 'smart-cycle-discounts' ),
					array( 'severity' => 'info' )
				);
			}
		}
	}

	/**
	 * Validate discounts + schedule compatibility.
	 *
	 * Scenarios 16-20: New cross-step validation
	 * - Discount timing vs schedule configuration
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array    $data      Campaign data.
	 * @param    WP_Error $errors    Error object.
	 * @return   void
	 */
	private static function validate_discounts_schedule( array $data, WP_Error $errors ) {
		$discount_type = isset( $data['discount_type'] ) ? $data['discount_type'] : '';
		$start_date    = isset( $data['start_date'] ) ? $data['start_date'] : '';
		$end_date      = isset( $data['end_date'] ) ? $data['end_date'] : '';

		if ( empty( $start_date ) || empty( $end_date ) ) {
			return;
		}

		$duration_hours = ( strtotime( $end_date ) - strtotime( $start_date ) ) / HOUR_IN_SECONDS;
		$has_rotation   = isset( $data['enable_rotation'] ) && 'yes' === $data['enable_rotation'];
		$is_recurring   = ! empty( $data[ WSSCD_Schedule_Field_Names::ENABLE_RECURRING ] );

		// Scenario 16: BOGO with very short campaign (warning - limited impact)
		if ( 'bogo' === $discount_type && $duration_hours < 24 ) {
			$errors->add(
				'cross_bogo_short_campaign',
				sprintf(
					/* translators: %d: Duration in hours */
					__( 'BOGO discount with %d-hour campaign may have limited customer reach. Consider extending duration for better results.', 'smart-cycle-discounts' ),
					round( $duration_hours )
				),
				array( 'severity' => 'info' )
			);
		}

		// Scenario 17: Complex discount with high-frequency rotation (performance)
		if ( in_array( $discount_type, array( 'tiered', 'bundle', 'spend_threshold' ), true ) && $has_rotation ) {
			$rotation_interval = isset( $data['rotation_interval'] ) ? intval( $data['rotation_interval'] ) : 24;
			if ( $rotation_interval < 12 ) {
				$errors->add(
					'cross_complex_discount_frequent_rotation',
					sprintf(
						/* translators: 1: Discount type, 2: Rotation interval */
						__( '%1$s discount with %2$d-hour rotation may impact performance. Consider longer rotation intervals for complex discounts.', 'smart-cycle-discounts' ),
						ucfirst( $discount_type ),
						$rotation_interval
					),
					array( 'severity' => 'warning' )
				);
			}
		}

		// Scenario 18: Recurring discount with usage limits (tracking complexity)
		if ( $is_recurring ) {
			$usage_limit_per_customer = isset( $data['usage_limit_per_customer'] ) ? intval( $data['usage_limit_per_customer'] ) : 0;
			$usage_limit_total        = isset( $data['usage_limit_total'] ) ? intval( $data['usage_limit_total'] ) : 0;

			if ( $usage_limit_per_customer > 0 || $usage_limit_total > 0 ) {
				$errors->add(
					'cross_recurring_with_usage_limits',
					__( 'Recurring campaign with usage limits requires careful tracking. Usage limits reset with each recurrence cycle.', 'smart-cycle-discounts' ),
					array( 'severity' => 'info' )
				);
			}
		}

		// Scenario 19: Fixed discount with long campaign (price change risk)
		if ( 'fixed' === $discount_type && $duration_hours > ( 30 * 24 ) ) {
			$errors->add(
				'cross_fixed_long_campaign',
				__( 'Fixed discount over a long campaign (>30 days) may become outdated if product prices change. Consider reviewing mid-campaign.', 'smart-cycle-discounts' ),
				array( 'severity' => 'info' )
			);
		}

		// Scenario 20: Spend threshold with weekend-only schedule (strategic alignment)
		if ( 'spend_threshold' === $discount_type ) {
			$schedule_type = isset( $data['schedule_type'] ) ? $data['schedule_type'] : 'daily';
			if ( 'weekly' === $schedule_type ) {
				$weekly_days = isset( $data['weekly_days'] ) ? $data['weekly_days'] : array();
				$weekend_only = array( 'saturday', 'sunday' );
				if ( count( array_intersect( array_map( 'strtolower', $weekly_days ), $weekend_only ) ) === 2 && 2 === count( $weekly_days ) ) {
					$errors->add(
						'cross_threshold_weekend_only',
						__( 'Spend threshold discount on weekends only is a strategic approach to boost weekend sales.', 'smart-cycle-discounts' ),
						array( 'severity' => 'info' )
					);
				}
			}
		}
	}

	/**
	 * Validate products + schedule compatibility.
	 *
	 * Scenarios 21-25: New cross-step validation
	 * - Product selection vs schedule timing
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array    $data      Campaign data.
	 * @param    WP_Error $errors    Error object.
	 * @return   void
	 */
	private static function validate_products_schedule( array $data, WP_Error $errors ) {
		$product_selection_type = isset( $data['product_selection_type'] ) ? $data['product_selection_type'] : '';
		$start_date             = isset( $data['start_date'] ) ? $data['start_date'] : '';
		$end_date               = isset( $data['end_date'] ) ? $data['end_date'] : '';

		if ( empty( $start_date ) || empty( $end_date ) ) {
			return;
		}

		$duration_hours = ( strtotime( $end_date ) - strtotime( $start_date ) ) / HOUR_IN_SECONDS;
		$has_rotation   = isset( $data['enable_rotation'] ) && 'yes' === $data['enable_rotation'];

		// Scenario 21: Random products without rotation (limited randomness)
		if ( 'random_products' === $product_selection_type && ! $has_rotation ) {
			$errors->add(
				'cross_random_no_rotation',
				__( 'Random product selection without rotation means the same random products will be used for the entire campaign. Consider enabling rotation for true randomness.', 'smart-cycle-discounts' ),
				array( 'severity' => 'info' )
			);
		}

		// Scenario 22: All products with rotation enabled (unnecessary complexity)
		if ( 'all_products' === $product_selection_type && $has_rotation ) {
			$errors->add(
				'cross_all_products_rotation',
				__( 'Product rotation is enabled but "All Products" is selected. Rotation has no effect when all products are already included.', 'smart-cycle-discounts' ),
				array( 'severity' => 'warning' )
			);
		}

		// Scenario 23: Smart selection with very short campaign (insufficient data)
		if ( 'smart_selection' === $product_selection_type && $duration_hours < 48 ) {
			$errors->add(
				'cross_smart_short_campaign',
				sprintf(
					/* translators: %d: Duration in hours */
					__( 'Smart selection with %d-hour campaign may not allow enough time for optimization algorithms to work effectively.', 'smart-cycle-discounts' ),
					round( $duration_hours )
				),
				array( 'severity' => 'info' )
			);
		}

		// Scenario 24: Specific products with high rotation frequency (inventory risk)
		if ( 'specific_products' === $product_selection_type && $has_rotation ) {
			$rotation_interval = isset( $data['rotation_interval'] ) ? intval( $data['rotation_interval'] ) : 24;
			$product_ids       = isset( $data['product_ids'] ) ? $data['product_ids'] : array();

			if ( $rotation_interval < 12 && is_array( $product_ids ) && count( $product_ids ) < 10 ) {
				$errors->add(
					'cross_specific_frequent_rotation',
					__( 'Frequent rotation with few specific products may cause confusion. Consider adding more products or reducing rotation frequency.', 'smart-cycle-discounts' ),
					array( 'severity' => 'info' )
				);
			}
		}

		// Scenario 25: Advanced filters with recurring campaign (filter staleness)
		$has_conditions = isset( $data['conditions'] ) && ! empty( $data['conditions'] );
		$is_recurring   = ! empty( $data[ WSSCD_Schedule_Field_Names::ENABLE_RECURRING ] );

		if ( $has_conditions && $is_recurring ) {
			$recurrence_interval = isset( $data['recurrence_interval'] ) ? intval( $data['recurrence_interval'] ) : 1;
			$recurrence_unit     = isset( $data['recurrence_unit'] ) ? $data['recurrence_unit'] : 'days';

			// Check if long recurrence period
			$is_long_recurrence = false;
			if ( 'months' === $recurrence_unit && $recurrence_interval >= 3 ) {
				$is_long_recurrence = true;
			} elseif ( 'weeks' === $recurrence_unit && $recurrence_interval >= 12 ) {
				$is_long_recurrence = true;
			}

			if ( $is_long_recurrence ) {
				$errors->add(
					'cross_filters_long_recurrence',
					__( 'Advanced filters with long recurrence period may become outdated. Product inventory and attributes can change between cycles.', 'smart-cycle-discounts' ),
					array( 'severity' => 'info' )
				);
			}
		}
	}

	/**
	 * Validate three-way cross-step scenarios.
	 *
	 * Scenarios 26-28: Complex three-way validation
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array    $data      Campaign data.
	 * @param    WP_Error $errors    Error object.
	 * @return   void
	 */
	private static function validate_three_way( array $data, WP_Error $errors ) {
		$discount_type          = isset( $data['discount_type'] ) ? $data['discount_type'] : '';
		$product_selection_type = isset( $data['product_selection_type'] ) ? $data['product_selection_type'] : '';
		$has_rotation           = isset( $data['enable_rotation'] ) && 'yes' === $data['enable_rotation'];
		$is_recurring           = ! empty( $data[ WSSCD_Schedule_Field_Names::ENABLE_RECURRING ] );

		// Scenario 26: BOGO + Random + Rotation (complexity warning)
		if ( 'bogo' === $discount_type && 'random_products' === $product_selection_type && $has_rotation ) {
			$errors->add(
				'cross_bogo_random_rotation',
				__( 'BOGO discount with random products and rotation is complex. Ensure customers understand which products qualify for the offer at any given time.', 'smart-cycle-discounts' ),
				array( 'severity' => 'info' )
			);
		}

		// Scenario 27: Tiered + Filters + Recurring (performance concern)
		$has_conditions = isset( $data['conditions'] ) && ! empty( $data['conditions'] );
		if ( 'tiered' === $discount_type && $has_conditions && $is_recurring ) {
			$condition_count = count( $data['conditions'] );
			if ( $condition_count > 3 ) {
				$errors->add(
					'cross_tiered_filters_recurring',
					__( 'Tiered discount with multiple filters in a recurring campaign requires significant processing. Monitor performance during first cycle.', 'smart-cycle-discounts' ),
					array( 'severity' => 'warning' )
				);
			}
		}

		// Scenario 28: Fixed + All Products + Long Campaign (strategic review)
		$start_date = isset( $data['start_date'] ) ? $data['start_date'] : '';
		$end_date   = isset( $data['end_date'] ) ? $data['end_date'] : '';

		if ( ! empty( $start_date ) && ! empty( $end_date ) ) {
			$duration_days = ( strtotime( $end_date ) - strtotime( $start_date ) ) / DAY_IN_SECONDS;

			if ( 'fixed' === $discount_type && 'all_products' === $product_selection_type && $duration_days > 60 ) {
				$errors->add(
					'cross_fixed_all_long',
					__( 'Fixed discount on all products for >60 days requires careful margin monitoring as product prices and costs may change.', 'smart-cycle-discounts' ),
					array( 'severity' => 'info' )
				);
			}
		}
	}

	/**
	 * Validate campaign-level business rules.
	 *
	 * Scenarios 29-30: Campaign-wide validation
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array    $data      Campaign data.
	 * @param    WP_Error $errors    Error object.
	 * @return   void
	 */
	private static function validate_campaign_rules( array $data, WP_Error $errors ) {
		// Scenario 29: Campaign complexity score (performance indicator)
		$complexity_score = self::calculate_complexity_score( $data );
		if ( $complexity_score > 15 ) {
			$errors->add(
				'cross_high_complexity',
				sprintf(
					/* translators: %d: Complexity score */
					__( 'Campaign complexity score is %d (high). This may impact performance. Consider simplifying configuration or monitoring closely.', 'smart-cycle-discounts' ),
					$complexity_score
				),
				array( 'severity' => 'warning' )
			);
		}

		// Scenario 30: Campaign has all optional features enabled (over-engineering check)
		$has_rotation     = isset( $data['enable_rotation'] ) && 'yes' === $data['enable_rotation'];
		$is_recurring     = ! empty( $data[ WSSCD_Schedule_Field_Names::ENABLE_RECURRING ] );
		$has_conditions   = isset( $data['conditions'] ) && ! empty( $data['conditions'] );
		$has_usage_limits = ( isset( $data['usage_limit_per_customer'] ) && intval( $data['usage_limit_per_customer'] ) > 0 ) ||
		                    ( isset( $data['usage_limit_total'] ) && intval( $data['usage_limit_total'] ) > 0 );
		$has_min_quantity = isset( $data['minimum_quantity'] ) && intval( $data['minimum_quantity'] ) > 1;
		$has_min_amount   = isset( $data['minimum_order_amount'] ) && floatval( $data['minimum_order_amount'] ) > 0;

		$features_enabled = 0;
		if ( $has_rotation ) {
			++$features_enabled;
		}
		if ( $is_recurring ) {
			++$features_enabled;
		}
		if ( $has_conditions ) {
			++$features_enabled;
		}
		if ( $has_usage_limits ) {
			++$features_enabled;
		}
		if ( $has_min_quantity ) {
			++$features_enabled;
		}
		if ( $has_min_amount ) {
			++$features_enabled;
		}

		if ( $features_enabled >= 5 ) {
			$errors->add(
				'cross_many_features',
				__( 'Campaign has many optional features enabled. While functional, this may make the campaign harder to manage and troubleshoot. Consider simplifying if issues arise.', 'smart-cycle-discounts' ),
				array( 'severity' => 'info' )
			);
		}
	}

	/**
	 * Calculate campaign complexity score.
	 *
	 * Higher scores indicate more complex campaigns that may require more resources.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $data    Campaign data.
	 * @return   int               Complexity score (0-30+).
	 */
	private static function calculate_complexity_score( array $data ): int {
		$score = 0;

		// Discount type complexity
		$discount_type = isset( $data['discount_type'] ) ? $data['discount_type'] : '';
		$discount_complexity = array(
			'percentage'       => 1,
			'fixed'            => 1,
			'bogo'             => 3,
			'tiered'           => 4,
			'bundle'           => 4,
			'spend_threshold'  => 3,
		);
		$score += $discount_complexity[ $discount_type ] ?? 1;

		// Product selection complexity
		$product_selection_type = isset( $data['product_selection_type'] ) ? $data['product_selection_type'] : '';
		$selection_complexity = array(
			'all_products'      => 1,
			'specific_products' => 2,
			'random_products'   => 3,
			'smart_selection'   => 4,
		);
		$score += $selection_complexity[ $product_selection_type ] ?? 1;

		// Filter conditions
		if ( isset( $data['conditions'] ) && ! empty( $data['conditions'] ) ) {
			$score += count( $data['conditions'] );
		}

		// Rotation adds complexity
		if ( isset( $data['enable_rotation'] ) && 'yes' === $data['enable_rotation'] ) {
			$score += 2;
		}

		// Recurrence adds complexity
		if ( ! empty( $data[ WSSCD_Schedule_Field_Names::ENABLE_RECURRING ] ) ) {
			$score += 3;
		}

		// Usage limits add tracking complexity
		if ( ( isset( $data['usage_limit_per_customer'] ) && intval( $data['usage_limit_per_customer'] ) > 0 ) ||
		     ( isset( $data['usage_limit_total'] ) && intval( $data['usage_limit_total'] ) > 0 ) ) {
			$score += 2;
		}

		// Application rules add complexity
		if ( ( isset( $data['minimum_quantity'] ) && intval( $data['minimum_quantity'] ) > 1 ) ||
		     ( isset( $data['minimum_order_amount'] ) && floatval( $data['minimum_order_amount'] ) > 0 ) ||
		     ( isset( $data['max_discount_amount'] ) && floatval( $data['max_discount_amount'] ) > 0 ) ) {
			$score += 2;
		}

		return $score;
	}
}
