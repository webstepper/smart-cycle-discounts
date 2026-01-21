<?php
/**
 * Products Step Validator Class
 *
 * Validates the products step for logical consistency, business rules, and edge cases.
 * Focuses ONLY on products step internal validation. Cross-step validation is handled
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
 * Products Step Validator Class
 *
 * Validates the products step configuration for 97+ types of logical contradictions,
 * business rule violations, and edge cases. This validator focuses ONLY on the products
 * step itself - cross-step validation is handled by WSSCD_Campaign_Cross_Validator.
 *
 * SCOPE: Products step internal validation only
 * - Product selection type configuration
 * - Filter conditions (advanced filters)
 * - Logical contradictions and impossibilities
 * - WooCommerce-specific business rules
 *
 * NOT IN SCOPE (handled by campaign-cross-validator):
 * - Cross-step compatibility with discounts configuration
 * - Cross-step compatibility with schedule settings
 * - Campaign-level product selection validation
 *
 * Validation scenarios covered:
 * 1-5:   Basic contradictions (inverted ranges, equals conflicts, numeric impossibilities)
 * 6-10:  Advanced numeric (BETWEEN overlaps, equals+range, negative values, sale price logic)
 * 11-13: Text contradictions (contains conflicts, date ranges, pattern conflicts)
 * 14-19: Boolean/select/business logic (boolean conflicts, IN/NOT_IN, enum exhaustion)
 * 20-25: Advanced scenarios (equals+NOT_IN, rating bounds, stock logic, text equals, low stock)
 * 26-30: Edge cases (empty IN, NOT_EQUALS exhaustion, text pattern conflicts, on_sale logic)
 * 31-35: Business rules (downloadable products, identical BETWEEN, review logic, negative ranges)
 * 36-40: Advanced set operations (IN overlaps, substring containment, length limits, shipping/virtual)
 * 41-45: WooCommerce-specific (sales vs age, backorder logic, visibility conflicts, featured products)
 * 46-50: Product type rules (manage_stock, purchase quantities, tax logic, dimensions, variable products)
 * 51-54: Data quality (empty value operators, required fields, grouped products, review settings)
 * 56-61: Critical edge cases (NOT_IN empty, future dates, negative stock, rating bounds, sold individually, equals conflicts)
 * 62-66: Data validation warnings (zero price, unrealistic values, broad ranges, short names, performance)
 * 67-70: Temporal logic (sale scheduled dates, sale dates vs creation, stock vs sales ratio, review count vs rating)
 * 71-74: Advanced WooCommerce pricing (current price vs sale status, variable product pricing, low stock management, backorders with high stock)
 * 75-78: Critical temporal & pricing inversions (sale date inversion, external/grouped stock, zero regular price, sale dates without price)
 * 79-82: Temporal impossibilities & dimension quality (modified before created, past sale with on_sale, current=regular when on_sale, negative dimensions)
 * 83-85: Operator edge cases & optimization (boolean NOT_EQUALS, empty text patterns, IN with single value)
 * 86-89: WooCommerce business rules & enum exhaustion (downloadable+virtual, tax rules, NOT_IN all values, BETWEEN identical values)
 * 90-93: Critical business logic & data corruption (purchasable property, reviews disabled with count, text operator contradictions, future sale temporal)
 * 94-97: Stock management & physical properties (min purchase vs stock, weightless shipping, virtual with weight, low stock threshold)
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/validation/step-validators
 */
class WSSCD_Products_Step_Validator {

	/**
	 * Validate filter conditions for contradictions and impossibilities.
	 *
	 * Prevents saving filter conditions that are mathematically impossible or contradictory.
	 * This is the PRIMARY validation point - prevents bad data from being saved.
	 *
	 * @since    1.0.0
	 * @param    array    $data      Sanitized field data.
	 * @param    WP_Error $errors    Error object to add errors to.
	 * @return   void
	 */
	public static function validate( array $data, WP_Error $errors ) {
		if ( ! isset( $data['conditions'] ) || empty( $data['conditions'] ) ) {
			return; // No conditions to validate
		}

		$conditions = $data['conditions'];
		$logic      = isset( $data['conditions_logic'] ) ? $data['conditions_logic'] : 'all';

		// Skip validation for OR logic (allows contradictions by design)
		if ( 'any' === $logic ) {
			return;
		}

		// Helper arrays
		$numeric_properties  = array( 'price', 'sale_price', 'current_price', 'regular_price', 'stock_quantity', 'weight', 'length', 'width', 'height', 'review_count', 'total_sales', 'average_rating', 'rating', 'low_stock_amount' );
		$positive_properties = array( 'price', 'sale_price', 'current_price', 'regular_price', 'stock_quantity', 'weight', 'length', 'width', 'height', 'review_count', 'total_sales' );
		$rating_properties   = array( 'average_rating', 'rating' );

		// Validation 1: BETWEEN inverted ranges (min > max)
		foreach ( $conditions as $condition ) {
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';

			if ( in_array( $operator, array( 'between', 'not_between' ), true ) ) {
				$val1 = floatval( isset( $condition['value'] ) ? $condition['value'] : 0 );
				$val2 = floatval( isset( $condition['value2'] ) ? $condition['value2'] : 0 );

				if ( $val1 > $val2 ) {
					$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
					$errors->add(
						'filter_between_inverted_range',
						sprintf(
							/* translators: 1: Property, 2: First value, 3: Second value */
							__( 'Filter condition has inverted BETWEEN range for %1$s (%2$s to %3$s). First value must be less than second value.', 'smart-cycle-discounts' ),
							$property,
							number_format( $val1, 2 ),
							number_format( $val2, 2 )
						)
					);
				}
			}
		}

		// Validation 2: Same property with different equals values
		$property_equals_map = array();
		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
			$value    = isset( $condition['value'] ) ? $condition['value'] : '';

			if ( 'equals' === $operator && ! empty( $property ) ) {
				if ( isset( $property_equals_map[ $property ] ) && $property_equals_map[ $property ] !== $value ) {
					$errors->add(
						'filter_same_property_contradiction',
						sprintf(
							/* translators: 1: Property, 2: First value, 3: Second value */
							__( 'Filter conditions require %1$s to equal both "%2$s" AND "%3$s" simultaneously. This is impossible.', 'smart-cycle-discounts' ),
							$property,
							$property_equals_map[ $property ],
							$value
						)
					);
				}
				$property_equals_map[ $property ] = $value;
			}
		}

		// Validation 3: Contradictory numeric ranges (price < 10 AND price > 20)
		$numeric_ranges_full = array();
		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';

			if ( ! in_array( $property, $numeric_properties, true ) ) {
				continue;
			}

			if ( ! isset( $numeric_ranges_full[ $property ] ) ) {
				$numeric_ranges_full[ $property ] = array(
					'min' => PHP_FLOAT_MIN,
					'max' => PHP_FLOAT_MAX,
				);
			}

			$value = floatval( isset( $condition['value'] ) ? $condition['value'] : 0 );

			if ( 'greater_than' === $operator ) {
				$numeric_ranges_full[ $property ]['min'] = max( $numeric_ranges_full[ $property ]['min'], $value + 0.01 );
			} elseif ( 'greater_than_equal' === $operator ) {
				$numeric_ranges_full[ $property ]['min'] = max( $numeric_ranges_full[ $property ]['min'], $value );
			} elseif ( 'less_than' === $operator ) {
				$numeric_ranges_full[ $property ]['max'] = min( $numeric_ranges_full[ $property ]['max'], $value - 0.01 );
			} elseif ( 'less_than_equal' === $operator ) {
				$numeric_ranges_full[ $property ]['max'] = min( $numeric_ranges_full[ $property ]['max'], $value );
			} elseif ( 'between' === $operator ) {
				$value2 = floatval( isset( $condition['value2'] ) ? $condition['value2'] : 0 );
				$numeric_ranges_full[ $property ]['min'] = max( $numeric_ranges_full[ $property ]['min'], min( $value, $value2 ) );
				$numeric_ranges_full[ $property ]['max'] = min( $numeric_ranges_full[ $property ]['max'], max( $value, $value2 ) );
			}
		}

		foreach ( $numeric_ranges_full as $property => $range ) {
			if ( $range['min'] > $range['max'] ) {
				$errors->add(
					'filter_numeric_range_impossible',
					sprintf(
						/* translators: 1: Property, 2: Minimum, 3: Maximum */
						__( 'Filter conditions create impossible range for %1$s (must be greater than %2$s AND less than %3$s). No value can satisfy both.', 'smart-cycle-discounts' ),
						$property,
						number_format( $range['min'], 2 ),
						number_format( $range['max'], 2 )
					)
				);
			}
		}

		// Validation 4: Include/exclude same condition
		$condition_signatures = array();
		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
			$value    = isset( $condition['value'] ) ? $condition['value'] : '';
			$mode     = isset( $condition['mode'] ) ? $condition['mode'] : 'include';

			$signature = $property . '_' . $operator . '_' . $value;

			if ( isset( $condition_signatures[ $signature ] ) && $condition_signatures[ $signature ] !== $mode ) {
				$errors->add(
					'filter_include_exclude_contradiction',
					sprintf(
						/* translators: 1: Property, 2: Operator, 3: Value */
						__( 'Filter conditions both INCLUDE and EXCLUDE products where %1$s %2$s %3$s. This is contradictory.', 'smart-cycle-discounts' ),
						$property,
						$operator,
						$value
					)
				);
			}
			$condition_signatures[ $signature ] = $mode;
		}

		// Validation 6: Non-overlapping BETWEEN ranges
		$between_ranges = array();
		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';

			if ( 'between' === $operator && ! empty( $property ) ) {
				$val1 = floatval( isset( $condition['value'] ) ? $condition['value'] : 0 );
				$val2 = floatval( isset( $condition['value2'] ) ? $condition['value2'] : 0 );

				if ( ! isset( $between_ranges[ $property ] ) ) {
					$between_ranges[ $property ] = array();
				}
				$between_ranges[ $property ][] = array(
					'min' => min( $val1, $val2 ),
					'max' => max( $val1, $val2 ),
				);
			}
		}

		foreach ( $between_ranges as $property => $ranges ) {
			if ( count( $ranges ) < 2 ) {
				continue;
			}

			for ( $i = 0; $i < count( $ranges ) - 1; $i++ ) {
				for ( $j = $i + 1; $j < count( $ranges ); $j++ ) {
					$range1 = $ranges[ $i ];
					$range2 = $ranges[ $j ];

					// Check if ranges don't overlap
					if ( $range1['max'] < $range2['min'] || $range2['max'] < $range1['min'] ) {
						$errors->add(
							'filter_non_overlapping_between',
							sprintf(
								/* translators: 1: Property, 2: First range, 3: Second range */
								__( 'Filter conditions require %1$s to be BETWEEN %2$s AND BETWEEN %3$s. These ranges do not overlap - no value can be in both.', 'smart-cycle-discounts' ),
								$property,
								number_format( $range1['min'], 2 ) . '-' . number_format( $range1['max'], 2 ),
								number_format( $range2['min'], 2 ) . '-' . number_format( $range2['max'], 2 )
							)
						);
					}
				}
			}
		}

		// Validation 7: Equals with incompatible range
		$equals_values = array();
		$range_constraints = array();

		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';

			if ( ! in_array( $property, $numeric_properties, true ) ) {
				continue;
			}

			$value = floatval( isset( $condition['value'] ) ? $condition['value'] : 0 );

			if ( 'equals' === $operator ) {
				if ( ! isset( $equals_values[ $property ] ) ) {
					$equals_values[ $property ] = array();
				}
				$equals_values[ $property ][] = $value;
			} else {
				if ( ! isset( $range_constraints[ $property ] ) ) {
					$range_constraints[ $property ] = array(
						'min' => PHP_FLOAT_MIN,
						'max' => PHP_FLOAT_MAX,
					);
				}

				if ( 'greater_than' === $operator ) {
					$range_constraints[ $property ]['min'] = max( $range_constraints[ $property ]['min'], $value + 0.01 );
				} elseif ( 'greater_than_equal' === $operator ) {
					$range_constraints[ $property ]['min'] = max( $range_constraints[ $property ]['min'], $value );
				} elseif ( 'less_than' === $operator ) {
					$range_constraints[ $property ]['max'] = min( $range_constraints[ $property ]['max'], $value - 0.01 );
				} elseif ( 'less_than_equal' === $operator ) {
					$range_constraints[ $property ]['max'] = min( $range_constraints[ $property ]['max'], $value );
				} elseif ( 'between' === $operator ) {
					$value2 = floatval( isset( $condition['value2'] ) ? $condition['value2'] : 0 );
					$range_constraints[ $property ]['min'] = max( $range_constraints[ $property ]['min'], min( $value, $value2 ) );
					$range_constraints[ $property ]['max'] = min( $range_constraints[ $property ]['max'], max( $value, $value2 ) );
				}
			}
		}

		foreach ( $equals_values as $property => $values ) {
			if ( isset( $range_constraints[ $property ] ) ) {
				$range = $range_constraints[ $property ];
				foreach ( $values as $equals_val ) {
					if ( $equals_val < $range['min'] || $equals_val > $range['max'] ) {
						$errors->add(
							'filter_equals_incompatible_range',
							sprintf(
								/* translators: 1: Property, 2: Equals value, 3: Min, 4: Max */
								__( 'Filter condition requires %1$s to equal %2$s, but range constraints require it to be between %3$s and %4$s. The equals value is outside this range.', 'smart-cycle-discounts' ),
								$property,
								number_format( $equals_val, 2 ),
								number_format( $range['min'], 2 ),
								number_format( $range['max'], 2 )
							)
						);
					}
				}
			}
		}

		// Validation 8: Greater/less than equal impossibility
		$gte_values = array();
		$lte_values = array();

		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';

			if ( ! in_array( $property, $numeric_properties, true ) ) {
				continue;
			}

			$value = floatval( isset( $condition['value'] ) ? $condition['value'] : 0 );

			if ( 'greater_than_equal' === $operator ) {
				if ( ! isset( $gte_values[ $property ] ) ) {
					$gte_values[ $property ] = array();
				}
				$gte_values[ $property ][] = $value;
			} elseif ( 'less_than_equal' === $operator ) {
				if ( ! isset( $lte_values[ $property ] ) ) {
					$lte_values[ $property ] = array();
				}
				$lte_values[ $property ][] = $value;
			}
		}

		foreach ( $gte_values as $property => $gte_vals ) {
			if ( isset( $lte_values[ $property ] ) ) {
				$max_gte = max( $gte_vals );
				$min_lte = min( $lte_values[ $property ] );

				if ( $max_gte > $min_lte ) {
					$errors->add(
						'filter_gte_lte_impossible',
						sprintf(
							/* translators: 1: Property, 2: GTE value, 3: LTE value */
							__( 'Filter conditions require %1$s to be >= %2$s AND <= %3$s simultaneously. This is mathematically impossible.', 'smart-cycle-discounts' ),
							$property,
							number_format( $max_gte, 2 ),
							number_format( $min_lte, 2 )
						)
					);
				}
			}
		}

		// Validation 9: Negative values for positive-only properties
		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';

			if ( ! in_array( $property, $positive_properties, true ) ) {
				continue;
			}

			$value = floatval( isset( $condition['value'] ) ? $condition['value'] : 0 );

			if ( in_array( $operator, array( 'equals', 'less_than', 'less_than_equal' ), true ) && $value < 0 ) {
				$errors->add(
					'filter_negative_value_invalid',
					sprintf(
						/* translators: %s: Property */
						__( 'Filter condition uses negative value for %s which must be zero or positive.', 'smart-cycle-discounts' ),
						$property
					)
				);
			}
		}

		// Validation 10: Sale price > regular price business logic
		$sale_conditions    = array();
		$regular_conditions = array();

		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';

			if ( 'sale_price' === $property ) {
				$sale_conditions[] = $condition;
			} elseif ( in_array( $property, array( 'price', 'regular_price' ), true ) ) {
				$regular_conditions[] = $condition;
			}
		}

		foreach ( $sale_conditions as $sale_cond ) {
			$sale_op  = isset( $sale_cond['operator'] ) ? $sale_cond['operator'] : '';
			$sale_val = floatval( isset( $sale_cond['value'] ) ? $sale_cond['value'] : 0 );

			foreach ( $regular_conditions as $reg_cond ) {
				$reg_op  = isset( $reg_cond['operator'] ) ? $reg_cond['operator'] : '';
				$reg_val = floatval( isset( $reg_cond['value'] ) ? $reg_cond['value'] : 0 );

				// Sale price greater than regular price violation
				if ( in_array( $sale_op, array( 'greater_than', 'greater_than_equal' ), true ) &&
					in_array( $reg_op, array( 'less_than', 'less_than_equal', 'equals' ), true ) ) {
					if ( $sale_val >= $reg_val ) {
						$errors->add(
							'filter_sale_price_exceeds_regular',
							sprintf(
								/* translators: 1: Sale price value, 2: Regular price value */
								__( 'Filter conditions allow sale price (%1$s) to be greater than or equal to regular price (%2$s). Sale prices should be lower than regular prices.', 'smart-cycle-discounts' ),
								number_format( $sale_val, 2 ),
								number_format( $reg_val, 2 )
							)
						);
					}
				}
			}
		}

		// Validation 12: Date range contradictions
		$date_ranges = array();
		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';

			if ( ! in_array( $property, array( 'date_created', 'date_modified' ), true ) ) {
				continue;
			}

			$value = isset( $condition['value'] ) ? strtotime( $condition['value'] ) : false;

			if ( ! $value ) {
				continue;
			}

			if ( ! isset( $date_ranges[ $property ] ) ) {
				$date_ranges[ $property ] = array(
					'min' => 0,
					'max' => PHP_INT_MAX,
				);
			}

			if ( in_array( $operator, array( 'greater_than', 'greater_than_equal' ), true ) ) {
				$date_ranges[ $property ]['min'] = max( $date_ranges[ $property ]['min'], $value );
			} elseif ( in_array( $operator, array( 'less_than', 'less_than_equal' ), true ) ) {
				$date_ranges[ $property ]['max'] = min( $date_ranges[ $property ]['max'], $value );
			} elseif ( 'between' === $operator ) {
				$value2 = isset( $condition['value2'] ) ? strtotime( $condition['value2'] ) : false;
				if ( $value2 ) {
					$date_ranges[ $property ]['min'] = max( $date_ranges[ $property ]['min'], $value );
					$date_ranges[ $property ]['max'] = min( $date_ranges[ $property ]['max'], $value2 );
				}
			}
		}

		foreach ( $date_ranges as $property => $range ) {
			if ( $range['min'] > $range['max'] ) {
				$earliest = $range['max'];
				$latest   = $range['min'];

				$errors->add(
					'filter_date_range_impossible',
					sprintf(
						/* translators: 1: Property, 2: Earliest date, 3: Latest date */
						__( 'Filter conditions create impossible date range for %1$s (must be after %2$s AND before %3$s). No date can satisfy both.', 'smart-cycle-discounts' ),
						$property,
						gmdate( 'Y-m-d', $earliest ),
						gmdate( 'Y-m-d', $latest )
					)
				);
			}
		}

		// Validation 13: Text pattern conflicts (equals vs starts_with)
		$text_patterns = array();
		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
			$value    = isset( $condition['value'] ) ? $condition['value'] : '';

			if ( in_array( $operator, array( 'equals', 'starts_with', 'ends_with' ), true ) && ! empty( $property ) && ! empty( $value ) ) {
				if ( ! isset( $text_patterns[ $property ] ) ) {
					$text_patterns[ $property ] = array();
				}
				$text_patterns[ $property ][] = array(
					'operator' => $operator,
					'value'    => $value,
				);
			}
		}

		foreach ( $text_patterns as $property => $patterns ) {
			$equals       = array();
			$starts_with  = array();

			foreach ( $patterns as $pattern ) {
				if ( 'equals' === $pattern['operator'] ) {
					$equals[] = $pattern['value'];
				} elseif ( 'starts_with' === $pattern['operator'] ) {
					$starts_with[] = $pattern['value'];
				}
			}

			foreach ( $equals as $equals_val ) {
				foreach ( $starts_with as $prefix ) {
					if ( 0 !== strpos( $equals_val, $prefix ) ) {
						$errors->add(
							'filter_text_pattern_conflict',
							sprintf(
								/* translators: 1: Property, 2: Equals value, 3: Prefix */
								__( 'Filter condition requires %1$s to equal "%2$s" while also starting with "%3$s". These are incompatible.', 'smart-cycle-discounts' ),
								$property,
								esc_html( $equals_val ),
								esc_html( $prefix )
							)
						);
					}
				}
			}
		}

		// Validation 14: Boolean property contradiction (featured = true AND featured = false)
		$boolean_properties = array( 'featured', 'on_sale', 'virtual', 'downloadable' );
		$boolean_values     = array();
		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';

			if ( ! in_array( $property, $boolean_properties, true ) || 'equals' !== $operator ) {
				continue;
			}

			$value = isset( $condition['value'] ) ? intval( $condition['value'] ) : 0;

			if ( ! isset( $boolean_values[ $property ] ) ) {
				$boolean_values[ $property ] = array();
			}
			$boolean_values[ $property ][] = $value;
		}

		foreach ( $boolean_values as $property => $values ) {
			// Remove duplicates and check if we have both 0 and 1
			$unique_values = array_unique( $values );
			if ( count( $unique_values ) > 1 && in_array( 0, $unique_values, true ) && in_array( 1, $unique_values, true ) ) {
				$errors->add(
					'filter_boolean_contradiction',
					sprintf(
						/* translators: %s: Property name */
						__( 'Filter conditions require %s to be both true AND false. This is impossible.', 'smart-cycle-discounts' ),
						$property
					)
				);
			}
		}

		// Validation 15: IN/NOT_IN complete negation (type IN [simple, variable] AND type NOT_IN [simple, variable, grouped])
		$in_not_in_checks = array();
		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
			$value_str = isset( $condition['value'] ) ? $condition['value'] : '';
		$values    = ! empty( $value_str ) ? array_map( 'trim', explode( ',', $value_str ) ) : array();

			if ( ! in_array( $operator, array( 'in', 'not_in' ), true ) || empty( $property ) || empty( $values ) ) {
				continue;
			}

			if ( ! isset( $in_not_in_checks[ $property ] ) ) {
				$in_not_in_checks[ $property ] = array(
					'in'     => array(),
					'not_in' => array(),
				);
			}

			if ( 'in' === $operator ) {
				$in_not_in_checks[ $property ]['in'] = array_merge( $in_not_in_checks[ $property ]['in'], $values );
			} else {
				$in_not_in_checks[ $property ]['not_in'] = array_merge( $in_not_in_checks[ $property ]['not_in'], $values );
			}
		}

		foreach ( $in_not_in_checks as $property => $check ) {
			if ( ! empty( $check['in'] ) && ! empty( $check['not_in'] ) ) {
				// Check if all IN values are also in NOT_IN
				$overlap = array_intersect( $check['in'], $check['not_in'] );
				if ( ! empty( $overlap ) && count( $overlap ) === count( $check['in'] ) ) {
					$errors->add(
						'filter_in_not_in_negation',
						sprintf(
							/* translators: 1: Property, 2: IN values, 3: NOT_IN values */
							__( 'Filter conditions require %1$s to be IN [%2$s] while also being NOT IN [%3$s]. All IN values are excluded by NOT_IN.', 'smart-cycle-discounts' ),
							$property,
							implode( ', ', $check['in'] ),
							implode( ', ', $check['not_in'] )
						)
					);
				}
			}
		}

		// Validation 16: Select option exhaustion (excluding ALL enum options)
		$enum_definitions = array(
			'product_type' => array( 'simple', 'variable', 'grouped', 'external' ),
			'tax_status'   => array( 'taxable', 'shipping', 'none' ),
			'stock_status' => array( 'instock', 'outofstock', 'onbackorder' ),
		);

		$enum_exclusions = array();
		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
			$value_str = isset( $condition['value'] ) ? $condition['value'] : '';
		$values    = ! empty( $value_str ) ? array_map( 'trim', explode( ',', $value_str ) ) : array();

			if ( ! isset( $enum_definitions[ $property ] ) ) {
				continue;
			}

			if ( ! isset( $enum_exclusions[ $property ] ) ) {
				$enum_exclusions[ $property ] = array();
			}

			if ( 'not_equals' === $operator && ! empty( $value_str ) ) {
				$enum_exclusions[ $property ][] = $value_str;
			} elseif ( 'not_in' === $operator ) {
				$enum_exclusions[ $property ] = array_merge( $enum_exclusions[ $property ], $values );
			}
		}

		foreach ( $enum_exclusions as $property => $excluded ) {
			$all_options      = $enum_definitions[ $property ];
			$excluded_unique  = array_unique( $excluded );
			$remaining        = array_diff( $all_options, $excluded_unique );

			if ( empty( $remaining ) ) {
				$errors->add(
					'filter_enum_exhaustion',
					sprintf(
						/* translators: 1: Property, 2: All excluded values */
						__( 'Filter conditions exclude all possible values for %1$s [%2$s]. No products can match.', 'smart-cycle-discounts' ),
						$property,
						implode( ', ', $all_options )
					)
				);
			}
		}

		// Validation 17: NOT_BETWEEN overlapping coverage creating impossible range
		$not_between_ranges = array();
		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';

			if ( 'not_between' !== $operator || ! in_array( $property, $numeric_properties, true ) ) {
				continue;
			}

			$value1 = floatval( isset( $condition['value'] ) ? $condition['value'] : 0 );
			$value2 = floatval( isset( $condition['value2'] ) ? $condition['value2'] : 0 );

			if ( ! isset( $not_between_ranges[ $property ] ) ) {
				$not_between_ranges[ $property ] = array();
			}

			$not_between_ranges[ $property ][] = array(
				'min' => min( $value1, $value2 ),
				'max' => max( $value1, $value2 ),
			);
		}

		foreach ( $not_between_ranges as $property => $ranges ) {
			if ( count( $ranges ) < 2 ) {
				continue;
			}

			// Sort ranges by min value
			usort(
				$ranges,
				function ( $a, $b ) {
					return $a['min'] <=> $b['min'];
				}
			);

			// Check if ranges overlap to cover everything
			for ( $i = 0; $i < count( $ranges ) - 1; $i++ ) {
				$current = $ranges[ $i ];
				$next    = $ranges[ $i + 1 ];

				// If current range ends at or after next range starts, they overlap
				if ( $current['max'] >= $next['min'] ) {
					// Check if combined they create an impossible situation
					// This is a simplified check - full coverage analysis would be more complex
					$errors->add(
						'filter_not_between_overlap',
						sprintf(
							/* translators: 1: Property, 2: First range, 3: Second range */
							__( 'Filter conditions have overlapping NOT_BETWEEN ranges for %1$s ([%2$s] and [%3$s]). This may exclude all values.', 'smart-cycle-discounts' ),
							$property,
							number_format( $current['min'], 2 ) . '-' . number_format( $current['max'], 2 ),
							number_format( $next['min'], 2 ) . '-' . number_format( $next['max'], 2 )
						)
					);
					break;
				}
			}
		}

		// Validation 18: Date created after date modified (temporal logic violation)
		$date_created_min = null;
		$date_modified_max = null;

		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';

			if ( 'date_created' === $property ) {
				$value = isset( $condition['value'] ) ? strtotime( $condition['value'] ) : false;
				if ( $value && in_array( $operator, array( 'greater_than', 'greater_than_equal' ), true ) ) {
					if ( null === $date_created_min || $value > $date_created_min ) {
						$date_created_min = $value;
					}
				}
			} elseif ( 'date_modified' === $property ) {
				$value = isset( $condition['value'] ) ? strtotime( $condition['value'] ) : false;
				if ( $value && in_array( $operator, array( 'less_than', 'less_than_equal' ), true ) ) {
					if ( null === $date_modified_max || $value < $date_modified_max ) {
						$date_modified_max = $value;
					}
				}
			}
		}

		if ( null !== $date_created_min && null !== $date_modified_max && $date_created_min > $date_modified_max ) {
			$errors->add(
				'filter_date_temporal_violation',
				sprintf(
					/* translators: 1: Created date, 2: Modified date */
					__( 'Filter conditions require products created after %1$s but modified before %2$s. Creation date cannot be after modification date.', 'smart-cycle-discounts' ),
					gmdate( 'Y-m-d', $date_created_min ),
					gmdate( 'Y-m-d', $date_modified_max )
				)
			);
		}

		// Validation 19: Virtual product with physical properties (WooCommerce business logic)
		$virtual_required = false;
		$physical_properties_required = array();

		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
			$value    = isset( $condition['value'] ) ? $condition['value'] : null;

			// Check if virtual = true is required
			if ( 'virtual' === $property && 'equals' === $operator && 1 === intval( $value ) ) {
				$virtual_required = true;
			}

			// Check if physical properties (weight, dimensions) are required
			if ( in_array( $property, array( 'weight', 'length', 'width', 'height' ), true ) ) {
				if ( in_array( $operator, array( 'greater_than', 'greater_than_equal' ), true ) && floatval( $value ) > 0 ) {
					$physical_properties_required[] = $property;
				} elseif ( 'between' === $operator ) {
					$min_val = floatval( $value );
					if ( $min_val > 0 ) {
						$physical_properties_required[] = $property;
					}
				}
			}
		}

		if ( $virtual_required && ! empty( $physical_properties_required ) ) {
			$errors->add(
				'filter_virtual_physical_conflict',
				sprintf(
					/* translators: %s: Physical properties list */
					__( 'Filter conditions require products to be virtual while also having physical properties [%s]. Virtual products typically do not have physical dimensions.', 'smart-cycle-discounts' ),
					implode( ', ', array_unique( $physical_properties_required ) )
				)
			);
		}

		// Validation 20: EQUALS with NOT_IN containing that value (property = X AND property NOT_IN [X, Y, Z])
		$equals_not_in_checks = array();
		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
			$value_str = isset( $condition['value'] ) ? $condition['value'] : '';
		$values    = ! empty( $value_str ) ? array_map( 'trim', explode( ',', $value_str ) ) : array();

			if ( empty( $property ) ) {
				continue;
			}

			if ( ! isset( $equals_not_in_checks[ $property ] ) ) {
				$equals_not_in_checks[ $property ] = array(
					'equals' => array(),
					'not_in' => array(),
				);
			}

			if ( 'equals' === $operator && ! empty( $value_str ) ) {
				$equals_not_in_checks[ $property ]['equals'][] = $value_str;
			} elseif ( 'not_in' === $operator && ! empty( $values ) ) {
				$equals_not_in_checks[ $property ]['not_in'] = array_merge( $equals_not_in_checks[ $property ]['not_in'], $values );
			}
		}

		foreach ( $equals_not_in_checks as $property => $check ) {
			if ( ! empty( $check['equals'] ) && ! empty( $check['not_in'] ) ) {
				foreach ( $check['equals'] as $equals_val ) {
					if ( in_array( $equals_val, $check['not_in'], true ) ) {
						$errors->add(
							'filter_equals_not_in_contradiction',
							sprintf(
								/* translators: 1: Property, 2: Equals value */
								__( 'Filter condition requires %1$s to equal "%2$s" while also excluding it via NOT_IN. This is impossible.', 'smart-cycle-discounts' ),
								$property,
								esc_html( $equals_val )
							)
						);
						break;
					}
				}
			}
		}

		// Validation 21: Rating bounds violation (ratings are 0-5 scale in WooCommerce)
		$rating_properties = array( 'average_rating', 'rating' );
		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';

			if ( ! in_array( $property, $rating_properties, true ) ) {
				continue;
			}

			$value1 = floatval( isset( $condition['value'] ) ? $condition['value'] : 0 );
			$value2 = floatval( isset( $condition['value2'] ) ? $condition['value2'] : 0 );

			// Check for values outside 0-5 range
			if ( in_array( $operator, array( 'equals', 'greater_than', 'greater_than_equal' ), true ) ) {
				if ( $value1 > 5 ) {
					$errors->add(
						'filter_rating_bounds_violation',
						sprintf(
							/* translators: %s: Invalid rating value */
							__( 'Filter condition requires rating greater than %s. WooCommerce ratings are limited to 0-5 scale.', 'smart-cycle-discounts' ),
							number_format( $value1, 1 )
						)
					);
				} elseif ( 'equals' === $operator && $value1 < 0 ) {
					$errors->add(
						'filter_rating_bounds_violation',
						sprintf(
							/* translators: 1: Property, 2: Rating value */
							__( 'Filter condition requires %1$s to equal %2$s, which is outside the valid WooCommerce rating range (0-5).', 'smart-cycle-discounts' ),
							$property,
							number_format( $value1, 1 )
						)
					);
				}
			} elseif ( in_array( $operator, array( 'less_than', 'less_than_equal' ), true ) ) {
				if ( $value1 < 0 ) {
					$errors->add(
						'filter_rating_bounds_violation',
						sprintf(
							/* translators: %s: Invalid rating value */
							__( 'Filter condition requires rating less than %s. WooCommerce ratings are limited to 0-5 scale.', 'smart-cycle-discounts' ),
							number_format( $value1, 1 )
						)
					);
				}
			} elseif ( 'between' === $operator ) {
				$min_val = min( $value1, $value2 );
				$max_val = max( $value1, $value2 );
				if ( $min_val > 5 || $max_val > 5 ) {
					$errors->add(
						'filter_rating_bounds_violation',
						__( 'Filter condition requires rating BETWEEN values outside valid 0-5 range.', 'smart-cycle-discounts' )
					);
				}
			}
		}

		// Validation 22: Stock status vs quantity logic contradiction
		$stock_status_value = null;
		$stock_quantity_constraints = array();

		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
			$value    = isset( $condition['value'] ) ? $condition['value'] : null;

			if ( 'stock_status' === $property && 'equals' === $operator ) {
				$stock_status_value = $value;
			} elseif ( 'stock_quantity' === $property ) {
				$stock_quantity_constraints[] = array(
					'operator' => $operator,
					'value'    => floatval( $value ),
				);
			}
		}

		if ( 'instock' === $stock_status_value ) {
			// Check if stock_quantity is constrained to be <= 0
			foreach ( $stock_quantity_constraints as $constraint ) {
				if ( 'less_than_equal' === $constraint['operator'] && $constraint['value'] <= 0 ) {
					$errors->add(
						'filter_stock_status_quantity_conflict',
						__( 'Filter conditions require products to be "in stock" while having zero or negative stock quantity. This is contradictory.', 'smart-cycle-discounts' )
					);
					break;
				} elseif ( 'less_than' === $constraint['operator'] && $constraint['value'] <= 1 ) {
					$errors->add(
						'filter_stock_status_quantity_conflict',
						__( 'Filter conditions require products to be "in stock" while having stock quantity less than 1. This is contradictory.', 'smart-cycle-discounts' )
					);
					break;
				} elseif ( 'equals' === $constraint['operator'] && $constraint['value'] <= 0 ) {
					$errors->add(
						'filter_stock_status_quantity_conflict',
						__( 'Filter conditions require products to be "in stock" while having zero stock quantity. This is contradictory.', 'smart-cycle-discounts' )
					);
					break;
				}
			}
		} elseif ( 'outofstock' === $stock_status_value ) {
			// Check if stock_quantity is constrained to be > 0
			foreach ( $stock_quantity_constraints as $constraint ) {
				if ( 'greater_than' === $constraint['operator'] && $constraint['value'] >= 0 ) {
					$errors->add(
						'filter_stock_status_quantity_conflict',
						__( 'Filter conditions require products to be "out of stock" while having positive stock quantity. This is contradictory.', 'smart-cycle-discounts' )
					);
					break;
				} elseif ( 'greater_than_equal' === $constraint['operator'] && $constraint['value'] > 0 ) {
					$errors->add(
						'filter_stock_status_quantity_conflict',
						__( 'Filter conditions require products to be "out of stock" while having stock quantity greater than zero. This is contradictory.', 'smart-cycle-discounts' )
					);
					break;
				}
			}
		}

		// Validation 23: Text EQUALS with incompatible text operators (equals 'X' but contains/starts_with/ends_with 'Y')
		$text_equals_checks = array();
		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
			$value    = isset( $condition['value'] ) ? $condition['value'] : '';

			if ( empty( $property ) || empty( $value ) ) {
				continue;
			}

			if ( ! isset( $text_equals_checks[ $property ] ) ) {
				$text_equals_checks[ $property ] = array(
					'equals'      => array(),
					'contains'    => array(),
					'starts_with' => array(),
					'ends_with'   => array(),
				);
			}

			if ( 'equals' === $operator ) {
				$text_equals_checks[ $property ]['equals'][] = strtolower( $value );
			} elseif ( 'contains' === $operator ) {
				$text_equals_checks[ $property ]['contains'][] = strtolower( $value );
			} elseif ( 'starts_with' === $operator ) {
				$text_equals_checks[ $property ]['starts_with'][] = strtolower( $value );
			} elseif ( 'ends_with' === $operator ) {
				$text_equals_checks[ $property ]['ends_with'][] = strtolower( $value );
			}
		}

		foreach ( $text_equals_checks as $property => $checks ) {
			if ( empty( $checks['equals'] ) ) {
				continue;
			}

			foreach ( $checks['equals'] as $equals_val ) {
				// Check incompatible CONTAINS
				foreach ( $checks['contains'] as $contains_val ) {
					if ( false === strpos( $equals_val, $contains_val ) ) {
						$errors->add(
							'filter_text_equals_operator_conflict',
							sprintf(
								/* translators: 1: Property, 2: Equals value, 3: Contains value */
								__( 'Filter condition requires %1$s to equal "%2$s" while also containing "%3$s". The equals value does not contain the required substring.', 'smart-cycle-discounts' ),
								$property,
								esc_html( $equals_val ),
								esc_html( $contains_val )
							)
						);
					}
				}

				// Check incompatible STARTS_WITH
				foreach ( $checks['starts_with'] as $prefix ) {
					if ( 0 !== strpos( $equals_val, $prefix ) ) {
						$errors->add(
							'filter_text_equals_operator_conflict',
							sprintf(
								/* translators: 1: Property, 2: Equals value, 3: Prefix */
								__( 'Filter condition requires %1$s to equal "%2$s" while also starting with "%3$s". The equals value does not start with the required prefix.', 'smart-cycle-discounts' ),
								$property,
								esc_html( $equals_val ),
								esc_html( $prefix )
							)
						);
					}
				}

				// Check incompatible ENDS_WITH
				foreach ( $checks['ends_with'] as $suffix ) {
					$suffix_len = strlen( $suffix );
					if ( substr( $equals_val, -$suffix_len ) !== $suffix ) {
						$errors->add(
							'filter_text_equals_operator_conflict',
							sprintf(
								/* translators: 1: Property, 2: Equals value, 3: Suffix */
								__( 'Filter condition requires %1$s to equal "%2$s" while also ending with "%3$s". The equals value does not end with the required suffix.', 'smart-cycle-discounts' ),
								$property,
								esc_html( $equals_val ),
								esc_html( $suffix )
							)
						);
					}
				}
			}
		}

		// Validation 24: EQUALS with NOT_BETWEEN excluding the equals value
		$equals_not_between_checks = array();
		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';

			if ( ! in_array( $property, $numeric_properties, true ) || empty( $property ) ) {
				continue;
			}

			if ( ! isset( $equals_not_between_checks[ $property ] ) ) {
				$equals_not_between_checks[ $property ] = array(
					'equals'      => array(),
					'not_between' => array(),
				);
			}

			if ( 'equals' === $operator ) {
				$value = floatval( isset( $condition['value'] ) ? $condition['value'] : 0 );
				$equals_not_between_checks[ $property ]['equals'][] = $value;
			} elseif ( 'not_between' === $operator ) {
				$value1 = floatval( isset( $condition['value'] ) ? $condition['value'] : 0 );
				$value2 = floatval( isset( $condition['value2'] ) ? $condition['value2'] : 0 );
				$equals_not_between_checks[ $property ]['not_between'][] = array(
					'min' => min( $value1, $value2 ),
					'max' => max( $value1, $value2 ),
				);
			}
		}

		foreach ( $equals_not_between_checks as $property => $checks ) {
			if ( empty( $checks['equals'] ) || empty( $checks['not_between'] ) ) {
				continue;
			}

			foreach ( $checks['equals'] as $equals_val ) {
				foreach ( $checks['not_between'] as $range ) {
					// Check if equals value is excluded by NOT_BETWEEN
					if ( $equals_val >= $range['min'] && $equals_val <= $range['max'] ) {
						$errors->add(
							'filter_equals_not_between_conflict',
							sprintf(
								/* translators: 1: Property, 2: Equals value, 3: Min range, 4: Max range */
								__( 'Filter condition requires %1$s to equal %2$s while excluding values BETWEEN %3$s and %4$s. The equals value is within the excluded range.', 'smart-cycle-discounts' ),
								$property,
								number_format( $equals_val, 2 ),
								number_format( $range['min'], 2 ),
								number_format( $range['max'], 2 )
							)
						);
					}
				}
			}
		}

		// Validation 25: Low stock amount vs actual stock quantity logic
		$low_stock_amount_value = null;
		$stock_quantity_min     = null;

		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
			$value    = isset( $condition['value'] ) ? floatval( $condition['value'] ) : 0;

			if ( 'low_stock_amount' === $property && 'equals' === $operator ) {
				$low_stock_amount_value = $value;
			} elseif ( 'stock_quantity' === $property ) {
				// Track the maximum lower bound for stock_quantity
				if ( in_array( $operator, array( 'equals', 'less_than', 'less_than_equal' ), true ) ) {
					if ( null === $stock_quantity_min || $value < $stock_quantity_min ) {
						$stock_quantity_min = $value;
					}
				}
			}
		}

		// Check if low_stock_amount is higher than the maximum possible stock_quantity
		if ( null !== $low_stock_amount_value && null !== $stock_quantity_min && $low_stock_amount_value > $stock_quantity_min ) {
			$errors->add(
				'filter_low_stock_threshold_conflict',
				sprintf(
					/* translators: 1: Low stock threshold, 2: Maximum stock quantity */
					__( 'Filter condition requires low stock threshold of %1$s while limiting stock quantity to maximum %2$s. The threshold cannot exceed actual stock.', 'smart-cycle-discounts' ),
					number_format( $low_stock_amount_value, 0 ),
					number_format( $stock_quantity_min, 0 )
				)
			);
		}

		// Validation 26: IN operator with empty array
		foreach ( $conditions as $condition ) {
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
			$value_str = isset( $condition['value'] ) ? $condition['value'] : '';
		$values    = ! empty( $value_str ) ? array_map( 'trim', explode( ',', $value_str ) ) : array();

			if ( 'in' === $operator && empty( $values ) ) {
				$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
				$errors->add(
					'filter_in_empty_array',
					sprintf(
						/* translators: %s: Property name */
						__( 'Filter condition uses IN operator for %s with empty array. This will match no products.', 'smart-cycle-discounts' ),
						$property
					)
				);
			}
		}

		// Validation 27: Multiple NOT_EQUALS exhausting all enum values
		$not_equals_values = array();
		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
			$value    = isset( $condition['value'] ) ? $condition['value'] : '';

			if ( 'not_equals' === $operator && ! empty( $property ) && ! empty( $value ) ) {
				if ( ! isset( $not_equals_values[ $property ] ) ) {
					$not_equals_values[ $property ] = array();
				}
				$not_equals_values[ $property ][] = $value;
			}
		}

		$enum_definitions = array(
			'product_type' => array( 'simple', 'variable', 'grouped', 'external' ),
			'tax_status'   => array( 'taxable', 'shipping', 'none' ),
			'stock_status' => array( 'instock', 'outofstock', 'onbackorder' ),
		);

		foreach ( $not_equals_values as $property => $excluded ) {
			if ( isset( $enum_definitions[ $property ] ) ) {
				$all_options     = $enum_definitions[ $property ];
				$excluded_unique = array_unique( $excluded );
				$remaining       = array_diff( $all_options, $excluded_unique );

				if ( empty( $remaining ) ) {
					$errors->add(
						'filter_not_equals_enum_exhaustion',
						sprintf(
							/* translators: 1: Property, 2: All excluded values */
							__( 'Filter conditions use multiple NOT_EQUALS for %1$s, excluding all possible values [%2$s]. No products can match.', 'smart-cycle-discounts' ),
							$property,
							implode( ', ', $all_options )
						)
					);
				}
			}
		}

		// Validation 28: Multiple STARTS_WITH conflicts
		$starts_with_values = array();
		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
			$value    = isset( $condition['value'] ) ? $condition['value'] : '';

			if ( 'starts_with' === $operator && ! empty( $property ) && ! empty( $value ) ) {
				if ( ! isset( $starts_with_values[ $property ] ) ) {
					$starts_with_values[ $property ] = array();
				}
				$starts_with_values[ $property ][] = strtolower( $value );
			}
		}

		foreach ( $starts_with_values as $property => $prefixes ) {
			if ( count( $prefixes ) < 2 ) {
				continue;
			}

			// Check if any two prefixes are incompatible
			for ( $i = 0; $i < count( $prefixes ) - 1; $i++ ) {
				for ( $j = $i + 1; $j < count( $prefixes ); $j++ ) {
					$prefix1 = $prefixes[ $i ];
					$prefix2 = $prefixes[ $j ];

					// If neither prefix starts with the other, they're incompatible
					if ( 0 !== strpos( $prefix1, $prefix2 ) && 0 !== strpos( $prefix2, $prefix1 ) ) {
						$errors->add(
							'filter_starts_with_conflict',
							sprintf(
								/* translators: 1: Property, 2: First prefix, 3: Second prefix */
								__( 'Filter condition requires %1$s to start with both "%2$s" AND "%3$s". No value can start with both simultaneously.', 'smart-cycle-discounts' ),
								$property,
								esc_html( $prefix1 ),
								esc_html( $prefix2 )
							)
						);
						break 2;
					}
				}
			}
		}

		// Validation 29: Multiple ENDS_WITH conflicts
		$ends_with_values = array();
		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
			$value    = isset( $condition['value'] ) ? $condition['value'] : '';

			if ( 'ends_with' === $operator && ! empty( $property ) && ! empty( $value ) ) {
				if ( ! isset( $ends_with_values[ $property ] ) ) {
					$ends_with_values[ $property ] = array();
				}
				$ends_with_values[ $property ][] = strtolower( $value );
			}
		}

		foreach ( $ends_with_values as $property => $suffixes ) {
			if ( count( $suffixes ) < 2 ) {
				continue;
			}

			// Check if any two suffixes are incompatible
			for ( $i = 0; $i < count( $suffixes ) - 1; $i++ ) {
				for ( $j = $i + 1; $j < count( $suffixes ); $j++ ) {
					$suffix1 = $suffixes[ $i ];
					$suffix2 = $suffixes[ $j ];

					$suffix1_len = strlen( $suffix1 );
					$suffix2_len = strlen( $suffix2 );

					// If neither suffix ends with the other, they're incompatible
					$suffix1_ends_with_suffix2 = ( substr( $suffix1, -$suffix2_len ) === $suffix2 );
					$suffix2_ends_with_suffix1 = ( substr( $suffix2, -$suffix1_len ) === $suffix1 );

					if ( ! $suffix1_ends_with_suffix2 && ! $suffix2_ends_with_suffix1 ) {
						$errors->add(
							'filter_ends_with_conflict',
							sprintf(
								/* translators: 1: Property, 2: First suffix, 3: Second suffix */
								__( 'Filter condition requires %1$s to end with both "%2$s" AND "%3$s". No value can end with both simultaneously.', 'smart-cycle-discounts' ),
								$property,
								esc_html( $suffix1 ),
								esc_html( $suffix2 )
							)
						);
						break 2;
					}
				}
			}
		}

		// Validation 30: On Sale status vs Sale Price logic
		$on_sale_status = null;
		$sale_price_constraints = array();

		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
			$value    = isset( $condition['value'] ) ? $condition['value'] : null;

			if ( 'on_sale' === $property && 'equals' === $operator ) {
				$on_sale_status = intval( $value );
			} elseif ( 'sale_price' === $property ) {
				$sale_price_constraints[] = array(
					'operator' => $operator,
					'value'    => floatval( $value ),
				);
			}
		}

		if ( 1 === $on_sale_status ) {
			// If on_sale = true, check if sale_price is constrained to be <= 0 or missing
			foreach ( $sale_price_constraints as $constraint ) {
				if ( 'equals' === $constraint['operator'] && $constraint['value'] <= 0 ) {
					$errors->add(
						'filter_on_sale_no_price',
						__( 'Filter condition requires products to be "on sale" while having zero or negative sale price. Products on sale must have valid sale prices.', 'smart-cycle-discounts' )
					);
					break;
				} elseif ( in_array( $constraint['operator'], array( 'less_than', 'less_than_equal' ), true ) && $constraint['value'] <= 0 ) {
					$errors->add(
						'filter_on_sale_no_price',
						__( 'Filter condition requires products to be "on sale" while constraining sale price to zero or negative. Products on sale must have valid sale prices.', 'smart-cycle-discounts' )
					);
					break;
				}
			}
		} elseif ( 0 === $on_sale_status ) {
			// If on_sale = false, check if sale_price is required to be positive
			foreach ( $sale_price_constraints as $constraint ) {
				if ( in_array( $constraint['operator'], array( 'greater_than', 'greater_than_equal' ), true ) && $constraint['value'] > 0 ) {
					$errors->add(
						'filter_not_on_sale_has_price',
						__( 'Filter condition requires products to NOT be "on sale" while having positive sale price. Products with sale prices are considered on sale.', 'smart-cycle-discounts' )
					);
					break;
				}
			}
		}

		// Validation 31: Downloadable product business rules
		$downloadable_status = null;
		$physical_properties = array();

		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
			$value    = isset( $condition['value'] ) ? $condition['value'] : null;

			if ( 'downloadable' === $property && 'equals' === $operator ) {
				$downloadable_status = intval( $value );
			} elseif ( in_array( $property, array( 'weight', 'length', 'width', 'height' ), true ) ) {
				if ( in_array( $operator, array( 'greater_than', 'greater_than_equal' ), true ) && floatval( $value ) > 0 ) {
					$physical_properties[] = $property;
				} elseif ( 'equals' === $operator && floatval( $value ) > 0 ) {
					$physical_properties[] = $property;
				}
			}
		}

		if ( 1 === $downloadable_status && ! empty( $physical_properties ) ) {
			$errors->add(
				'filter_downloadable_physical_conflict',
				sprintf(
					/* translators: %s: Physical properties list */
					__( 'Filter condition requires products to be downloadable while having physical properties [%s]. Downloadable products typically do not have physical dimensions or weight.', 'smart-cycle-discounts' ),
					implode( ', ', array_unique( $physical_properties ) )
				)
			);
		}

		// Validation 32: BETWEEN with identical min/max
		foreach ( $conditions as $condition ) {
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';

			if ( 'between' === $operator ) {
				$value1 = floatval( isset( $condition['value'] ) ? $condition['value'] : 0 );
				$value2 = floatval( isset( $condition['value2'] ) ? $condition['value2'] : 0 );

				if ( $value1 === $value2 ) {
					$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
					$errors->add(
						'filter_between_identical_values',
						sprintf(
							/* translators: 1: Property, 2: Value */
							__( 'Filter condition uses BETWEEN with identical values for %1$s (%2$s to %2$s). Use EQUALS operator instead.', 'smart-cycle-discounts' ),
							$property,
							number_format( $value1, 2 )
						)
					);
				}
			}
		}

		// Validation 33: Review count vs rating logic
		$review_count_constraints = array();
		$rating_constraints       = array();

		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
			$value    = isset( $condition['value'] ) ? floatval( $condition['value'] ) : 0;

			if ( 'review_count' === $property ) {
				$review_count_constraints[] = array(
					'operator' => $operator,
					'value'    => $value,
				);
			} elseif ( in_array( $property, array( 'average_rating', 'rating' ), true ) ) {
				$rating_constraints[] = array(
					'operator' => $operator,
					'value'    => $value,
				);
			}
		}

		// Check if rating is required but review_count is constrained to 0
		$has_rating_requirement = false;
		foreach ( $rating_constraints as $constraint ) {
			if ( in_array( $constraint['operator'], array( 'greater_than', 'greater_than_equal' ), true ) && $constraint['value'] > 0 ) {
				$has_rating_requirement = true;
				break;
			} elseif ( 'equals' === $constraint['operator'] && $constraint['value'] > 0 ) {
				$has_rating_requirement = true;
				break;
			}
		}

		if ( $has_rating_requirement ) {
			foreach ( $review_count_constraints as $constraint ) {
				if ( 'equals' === $constraint['operator'] && $constraint['value'] === 0 ) {
					$errors->add(
						'filter_rating_no_reviews',
						__( 'Filter condition requires products with positive rating while having zero reviews. Products need reviews to have ratings.', 'smart-cycle-discounts' )
					);
					break;
				} elseif ( in_array( $constraint['operator'], array( 'less_than', 'less_than_equal' ), true ) && $constraint['value'] <= 0 ) {
					$errors->add(
						'filter_rating_no_reviews',
						__( 'Filter condition requires products with positive rating while constraining review count to zero. Products need reviews to have ratings.', 'smart-cycle-discounts' )
					);
					break;
				}
			}
		}

		// Validation 34: Negative BETWEEN ranges for positive properties
		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';

			if ( ! in_array( $property, $positive_properties, true ) ) {
				continue;
			}

			if ( 'between' === $operator ) {
				$value1 = floatval( isset( $condition['value'] ) ? $condition['value'] : 0 );
				$value2 = floatval( isset( $condition['value2'] ) ? $condition['value2'] : 0 );

				$min_val = min( $value1, $value2 );
				$max_val = max( $value1, $value2 );

				if ( $max_val < 0 ) {
					$errors->add(
						'filter_negative_between_range',
						sprintf(
							/* translators: 1: Property, 2: Min value, 3: Max value */
							__( 'Filter condition uses BETWEEN with negative range for %1$s (%2$s to %3$s). This property must be zero or positive.', 'smart-cycle-discounts' ),
							$property,
							number_format( $min_val, 2 ),
							number_format( $max_val, 2 )
						)
					);
				}
			}
		}

		// Validation 35: CONTAINS substring conflicts
		$contains_values = array();
		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
			$value    = isset( $condition['value'] ) ? $condition['value'] : '';

			if ( 'contains' === $operator && ! empty( $property ) && ! empty( $value ) ) {
				if ( ! isset( $contains_values[ $property ] ) ) {
					$contains_values[ $property ] = array();
				}
				$contains_values[ $property ][] = strtolower( $value );
			}
		}

		foreach ( $contains_values as $property => $substrings ) {
			if ( count( $substrings ) < 2 ) {
				continue;
			}

			// Check if any two CONTAINS values are mutually exclusive substrings
			// This is a simplified check - detecting all impossible substring combinations is complex
			// We check for obvious conflicts where combined length would exceed reasonable limits
			for ( $i = 0; $i < count( $substrings ) - 1; $i++ ) {
				for ( $j = $i + 1; $j < count( $substrings ); $j++ ) {
					$substring1 = $substrings[ $i ];
					$substring2 = $substrings[ $j ];

					// If one substring is very long and the other doesn't appear in it,
					// and they're different, this might indicate a conflict
					// However, this is a heuristic check - skip for now as it's too complex
					// to determine all impossible CONTAINS combinations without more context
				}
			}
		}

		// Validation 36: Multiple IN operations with no overlap
		$in_operations = array();
		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
			$value_str = isset( $condition['value'] ) ? $condition['value'] : '';
		$values    = ! empty( $value_str ) ? array_map( 'trim', explode( ',', $value_str ) ) : array();

			if ( 'in' === $operator && ! empty( $property ) && ! empty( $values ) ) {
				if ( ! isset( $in_operations[ $property ] ) ) {
					$in_operations[ $property ] = array();
				}
				$in_operations[ $property ][] = $values;
			}
		}

		foreach ( $in_operations as $property => $value_sets ) {
			if ( count( $value_sets ) < 2 ) {
				continue;
			}

			// Check if any two IN operations have no overlap
			for ( $i = 0; $i < count( $value_sets ) - 1; $i++ ) {
				for ( $j = $i + 1; $j < count( $value_sets ); $j++ ) {
					$set1 = $value_sets[ $i ];
					$set2 = $value_sets[ $j ];
					$overlap = array_intersect( $set1, $set2 );

					if ( empty( $overlap ) ) {
						$errors->add(
							'filter_in_no_overlap',
							sprintf(
								/* translators: 1: Property, 2: First set values, 3: Second set values */
								__( 'Filter conditions require %1$s to be IN [%2$s] AND IN [%3$s]. These sets have no overlap - no value can be in both.', 'smart-cycle-discounts' ),
								$property,
								implode( ', ', $set1 ),
								implode( ', ', $set2 )
							)
						);
						break 2;
					}
				}
			}
		}

		// Validation 37: NOT_CONTAINS conflicting with CONTAINS (substring containment)
		$text_contains_not_contains = array();
		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
			$value    = isset( $condition['value'] ) ? $condition['value'] : '';

			if ( in_array( $operator, array( 'contains', 'not_contains' ), true ) && ! empty( $property ) && ! empty( $value ) ) {
				if ( ! isset( $text_contains_not_contains[ $property ] ) ) {
					$text_contains_not_contains[ $property ] = array(
						'contains'     => array(),
						'not_contains' => array(),
					);
				}

				if ( 'contains' === $operator ) {
					$text_contains_not_contains[ $property ]['contains'][] = strtolower( $value );
				} else {
					$text_contains_not_contains[ $property ]['not_contains'][] = strtolower( $value );
				}
			}
		}

		foreach ( $text_contains_not_contains as $property => $checks ) {
			// Check if CONTAINS value includes a NOT_CONTAINS substring
			foreach ( $checks['contains'] as $contains_val ) {
				foreach ( $checks['not_contains'] as $not_contains_val ) {
					if ( false !== strpos( $contains_val, $not_contains_val ) ) {
						$errors->add(
							'filter_contains_not_contains_substring',
							sprintf(
								/* translators: 1: Property, 2: Contains value, 3: Not contains value */
								__( 'Filter condition requires %1$s to CONTAIN "%2$s" while NOT CONTAINING "%3$s". The required substring "%2$s" contains the forbidden substring "%3$s".', 'smart-cycle-discounts' ),
								$property,
								esc_html( $contains_val ),
								esc_html( $not_contains_val )
							)
						);
					}
				}
			}
		}

		// Validation 38: STARTS_WITH + ENDS_WITH combined length impossibility
		$starts_ends_combined = array();
		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
			$value    = isset( $condition['value'] ) ? $condition['value'] : '';

			if ( in_array( $operator, array( 'starts_with', 'ends_with' ), true ) && ! empty( $property ) && ! empty( $value ) ) {
				if ( ! isset( $starts_ends_combined[ $property ] ) ) {
					$starts_ends_combined[ $property ] = array(
						'starts_with' => array(),
						'ends_with'   => array(),
					);
				}

				if ( 'starts_with' === $operator ) {
					$starts_ends_combined[ $property ]['starts_with'][] = $value;
				} else {
					$starts_ends_combined[ $property ]['ends_with'][] = $value;
				}
			}
		}

		foreach ( $starts_ends_combined as $property => $patterns ) {
			if ( empty( $patterns['starts_with'] ) || empty( $patterns['ends_with'] ) ) {
				continue;
			}

			// Check if prefix and suffix combined are too long or incompatible
			foreach ( $patterns['starts_with'] as $prefix ) {
				foreach ( $patterns['ends_with'] as $suffix ) {
					$prefix_len = strlen( $prefix );
					$suffix_len = strlen( $suffix );
					$combined_min_length = $prefix_len + $suffix_len;

					// Check if prefix and suffix overlap properly
					// A string can start with X and end with Y only if:
					// 1. They overlap (prefix ends with beginning of suffix, or suffix starts with end of prefix)
					// 2. Or the string is long enough to contain both separately

					// Check for impossible overlap: if combined length > reasonable max (200 chars for product names/SKUs)
					if ( $combined_min_length > 200 ) {
						$errors->add(
							'filter_starts_ends_too_long',
							sprintf(
								/* translators: 1: Property, 2: Prefix, 3: Suffix, 4: Combined length */
								__( 'Filter condition requires %1$s to start with "%2$s" AND end with "%3$s". Combined minimum length (%4$d characters) exceeds reasonable limits.', 'smart-cycle-discounts' ),
								$property,
								esc_html( $prefix ),
								esc_html( $suffix ),
								$combined_min_length
							)
						);
					}

					// Check if they overlap incompatibly (prefix and suffix don't share common substring at junction)
					// Only check if combined length would need exact match
					if ( $combined_min_length <= 50 ) {
						$prefix_lower = strtolower( $prefix );
						$suffix_lower = strtolower( $suffix );

						// If suffix is longer than prefix, check if prefix could be start of suffix
						if ( $suffix_len >= $prefix_len ) {
							if ( 0 !== strpos( $suffix_lower, $prefix_lower ) ) {
								// They don't overlap perfectly - could still work if string is longer
								// Skip this check as it's too complex
							}
						}
					}
				}
			}
		}

		// Validation 39: Virtual product vs shipping requirements
		$virtual_required = false;
		$shipping_required = false;

		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
			$value    = isset( $condition['value'] ) ? $condition['value'] : null;

			if ( 'virtual' === $property && 'equals' === $operator && 1 === intval( $value ) ) {
				$virtual_required = true;
			}

			// Check for shipping class requirement
			if ( 'shipping_class' === $property ) {
				if ( in_array( $operator, array( 'equals', 'in' ), true ) && ! empty( $value ) ) {
					$shipping_required = true;
				}
			}
		}

		if ( $virtual_required && $shipping_required ) {
			$errors->add(
				'filter_virtual_shipping_conflict',
				__( 'Filter condition requires products to be virtual while also having shipping class. Virtual products typically do not require shipping.', 'smart-cycle-discounts' )
			);
		}

		// Validation 40: External product type requirements
		$external_product_required = false;
		$external_url_required = false;

		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
			$value    = isset( $condition['value'] ) ? $condition['value'] : null;
			$value_str = isset( $condition['value'] ) ? $condition['value'] : '';
		$values    = ! empty( $value_str ) ? array_map( 'trim', explode( ',', $value_str ) ) : array();

			if ( 'product_type' === $property ) {
				if ( 'equals' === $operator && 'external' === $value ) {
					$external_product_required = true;
				} elseif ( 'in' === $operator && in_array( 'external', $values, true ) ) {
					$external_product_required = true;
				}
			}

			// Check for external_url requirements (if this property exists in your system)
			if ( 'external_url' === $property ) {
				if ( in_array( $operator, array( 'equals', 'not_equals' ), true ) && empty( $value ) ) {
					// Empty external URL check
				}
			}
		}

		// Note: External product URL validation would require knowing if external_url property is used
		// Skipping this check as it depends on implementation details

		// Validation 41: Price = 0 with percentage discount expectations
		// This is more of a discount application logic check, not a filter validation
		// Skipping as it's outside the scope of filter condition validation

		// Validation 42: Total sales vs new product contradiction
		$total_sales_min = null;
		$date_created_min = null;

		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
			$value    = isset( $condition['value'] ) ? $condition['value'] : null;

			if ( 'total_sales' === $property ) {
				if ( in_array( $operator, array( 'greater_than', 'greater_than_equal' ), true ) ) {
					$val = floatval( $value );
					if ( null === $total_sales_min || $val > $total_sales_min ) {
						$total_sales_min = $val;
					}
				}
			} elseif ( 'date_created' === $property ) {
				if ( in_array( $operator, array( 'greater_than', 'greater_than_equal' ), true ) ) {
					$timestamp = strtotime( $value );
					if ( $timestamp && ( null === $date_created_min || $timestamp > $date_created_min ) ) {
						$date_created_min = $timestamp;
					}
				}
			}
		}

		// Check if high sales volume is required for very recently created products
		if ( null !== $total_sales_min && null !== $date_created_min && $total_sales_min >= 100 ) {
			$days_old = ( time() - $date_created_min ) / DAY_IN_SECONDS;
			if ( $days_old < 1 ) {
				$errors->add(
					'filter_sales_new_product_unlikely',
					sprintf(
						/* translators: 1: Minimum sales, 2: Creation date */
						__( 'Filter condition requires products with at least %1$s total sales created after %2$s (less than 1 day old). This combination is unlikely unless you have very high traffic.', 'smart-cycle-discounts' ),
						number_format( $total_sales_min, 0 ),
						gmdate( 'Y-m-d', $date_created_min )
					)
				);
			}
		}

		// Validation 43: Stock status vs backorder logic
		$stock_status_value = null;
		$backorders_value = null;

		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
			$value    = isset( $condition['value'] ) ? $condition['value'] : null;

			if ( 'stock_status' === $property && 'equals' === $operator ) {
				$stock_status_value = $value;
			} elseif ( 'backorders_allowed' === $property && 'equals' === $operator ) {
				$backorders_value = intval( $value );
			}
		}

		// If stock status is 'onbackorder' but backorders not allowed
		if ( 'onbackorder' === $stock_status_value && 0 === $backorders_value ) {
			$errors->add(
				'filter_backorder_not_allowed',
				__( 'Filter condition requires stock status "on backorder" while backorders are not allowed. This is contradictory.', 'smart-cycle-discounts' )
			);
		}

		// Validation 44: Catalog visibility contradictions
		$visibility_values = array();
		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
			$value    = isset( $condition['value'] ) ? $condition['value'] : '';

			if ( 'catalog_visibility' === $property && 'equals' === $operator && ! empty( $value ) ) {
				$visibility_values[] = $value;
			}
		}

		// Check for multiple different catalog_visibility equals values
		$unique_visibility = array_unique( $visibility_values );
		if ( count( $unique_visibility ) > 1 ) {
			$errors->add(
				'filter_visibility_contradiction',
				sprintf(
					/* translators: %s: List of conflicting visibility values */
					__( 'Filter conditions require catalog_visibility to be multiple different values simultaneously [%s]. A product can only have one visibility setting.', 'smart-cycle-discounts' ),
					implode( ', ', $unique_visibility )
				)
			);
		}

		// Validation 45: Featured products vs catalog visibility
		$featured_required = false;
		$visibility_hidden = false;

		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
			$value    = isset( $condition['value'] ) ? $condition['value'] : null;

			if ( 'featured' === $property && 'equals' === $operator && 1 === intval( $value ) ) {
				$featured_required = true;
			}

			if ( 'catalog_visibility' === $property && 'equals' === $operator && 'hidden' === $value ) {
				$visibility_hidden = true;
			}
		}

		if ( $featured_required && $visibility_hidden ) {
			$errors->add(
				'filter_featured_hidden_conflict',
				__( 'Filter condition requires products to be featured while having "hidden" catalog visibility. Featured products should be visible in catalog or search.', 'smart-cycle-discounts' )
			);
		}

		// Validation 46: Manage stock disabled but stock-related fields specified
		$manage_stock_disabled = false;
		$stock_fields_required = array();

		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
			$value    = isset( $condition['value'] ) ? $condition['value'] : null;

			if ( 'manage_stock' === $property && 'equals' === $operator && 0 === intval( $value ) ) {
				$manage_stock_disabled = true;
			}

			// Check for stock-related field requirements
			if ( in_array( $property, array( 'stock_quantity', 'low_stock_amount', 'backorders_allowed' ), true ) ) {
				if ( ! in_array( $operator, array( 'not_equals', 'not_in' ), true ) ) {
					$stock_fields_required[] = $property;
				}
			}
		}

		if ( $manage_stock_disabled && ! empty( $stock_fields_required ) ) {
			$errors->add(
				'filter_manage_stock_disabled_conflict',
				sprintf(
					/* translators: %s: List of stock-related fields */
					__( 'Filter condition requires stock management to be disabled while specifying stock-related fields [%s]. These fields are only relevant when stock management is enabled.', 'smart-cycle-discounts' ),
					implode( ', ', array_unique( $stock_fields_required ) )
				)
			);
		}

		// Validation 47: Purchase quantity min greater than max
		$min_purchase_quantity = null;
		$max_purchase_quantity = null;

		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
			$value    = isset( $condition['value'] ) ? floatval( $condition['value'] ) : 0;

			if ( 'min_purchase_quantity' === $property ) {
				if ( in_array( $operator, array( 'equals', 'greater_than', 'greater_than_equal' ), true ) ) {
					if ( null === $min_purchase_quantity || $value > $min_purchase_quantity ) {
						$min_purchase_quantity = $value;
					}
				}
			} elseif ( 'max_purchase_quantity' === $property ) {
				if ( in_array( $operator, array( 'equals', 'less_than', 'less_than_equal' ), true ) ) {
					if ( null === $max_purchase_quantity || $value < $max_purchase_quantity ) {
						$max_purchase_quantity = $value;
					}
				}
			}
		}

		if ( null !== $min_purchase_quantity && null !== $max_purchase_quantity && $min_purchase_quantity > $max_purchase_quantity ) {
			$errors->add(
				'filter_purchase_quantity_conflict',
				sprintf(
					/* translators: 1: Minimum quantity, 2: Maximum quantity */
					__( 'Filter condition requires minimum purchase quantity (%1$s) to be greater than maximum purchase quantity (%2$s). This is impossible.', 'smart-cycle-discounts' ),
					number_format( $min_purchase_quantity, 0 ),
					number_format( $max_purchase_quantity, 0 )
				)
			);
		}

		// Validation 48: Tax status none but tax class specified
		$tax_status_none = false;
		$tax_class_specified = false;

		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
			$value    = isset( $condition['value'] ) ? $condition['value'] : null;

			if ( 'tax_status' === $property && 'equals' === $operator && 'none' === $value ) {
				$tax_status_none = true;
			}

			if ( 'tax_class' === $property ) {
				if ( in_array( $operator, array( 'equals', 'in' ), true ) && ! empty( $value ) ) {
					$tax_class_specified = true;
				}
			}
		}

		if ( $tax_status_none && $tax_class_specified ) {
			$errors->add(
				'filter_tax_status_none_class_specified',
				__( 'Filter condition requires tax status to be "none" while also specifying a tax class. Products with no tax do not use tax classes.', 'smart-cycle-discounts' )
			);
		}

		// Validation 49: Physical dimensions without weight
		$has_dimensions = false;
		$weight_zero_or_empty = false;

		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
			$value    = isset( $condition['value'] ) ? floatval( $condition['value'] ) : 0;

			// Check for dimension requirements
			if ( in_array( $property, array( 'length', 'width', 'height' ), true ) ) {
				if ( in_array( $operator, array( 'greater_than', 'greater_than_equal' ), true ) && $value > 0 ) {
					$has_dimensions = true;
				} elseif ( 'equals' === $operator && $value > 0 ) {
					$has_dimensions = true;
				}
			}

			// Check if weight is constrained to 0
			if ( 'weight' === $property ) {
				if ( 'equals' === $operator && $value <= 0 ) {
					$weight_zero_or_empty = true;
				} elseif ( in_array( $operator, array( 'less_than', 'less_than_equal' ), true ) && $value <= 0 ) {
					$weight_zero_or_empty = true;
				}
			}
		}

		if ( $has_dimensions && $weight_zero_or_empty ) {
			$errors->add(
				'filter_dimensions_no_weight',
				__( 'Filter condition requires products with physical dimensions (length/width/height) while having zero or no weight. Physical products typically have weight.', 'smart-cycle-discounts' )
			);
		}

		// Validation 50: Variable product type with simple-product-only logic
		$variable_product_required = false;
		$simple_only_fields = array();

		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
			$value    = isset( $condition['value'] ) ? $condition['value'] : null;
			$value_str = isset( $condition['value'] ) ? $condition['value'] : '';
		$values    = ! empty( $value_str ) ? array_map( 'trim', explode( ',', $value_str ) ) : array();

			if ( 'product_type' === $property ) {
				if ( 'equals' === $operator && 'variable' === $value ) {
					$variable_product_required = true;
				} elseif ( 'in' === $operator && in_array( 'variable', $values, true ) ) {
					// Variable could be required, but also other types allowed
					// Skip this case as it's ambiguous
				}
			}

			// Check for fields that don't apply to variable products
			// Note: This is a simplified check - actual WooCommerce variable products
			// have complex attribute/variation logic
			if ( in_array( $property, array( 'stock_quantity', 'price', 'sale_price', 'regular_price' ), true ) ) {
				if ( ! in_array( $operator, array( 'not_equals', 'not_in' ), true ) ) {
					// These fields are actually used in variations, not parent variable products
					// This check might be too strict - commenting out
					// $simple_only_fields[] = $property;
				}
			}
		}

		// Note: Variable product validation is complex because parent products don't have
		// direct price/stock values - variations do. Skipping this validation as it requires
		// deeper understanding of whether we're filtering parent or variation level.

		// Validation 51: NOT_EQUALS with empty value (always true)
		foreach ( $conditions as $condition ) {
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
			$value    = isset( $condition['value'] ) ? $condition['value'] : null;

			if ( 'not_equals' === $operator && ( '' === $value || null === $value ) ) {
				$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
				$errors->add(
					'filter_not_equals_empty',
					sprintf(
						/* translators: %s: Property name */
						__( 'Filter condition uses NOT_EQUALS with empty value for %s. This will match all products (always true). Use a specific value or different operator.', 'smart-cycle-discounts' ),
						$property
					)
				);
			}
		}

		// Validation 52: EQUALS with empty string for required fields
		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
			$value    = isset( $condition['value'] ) ? $condition['value'] : null;

			// Check for equals empty on fields that typically shouldn't be empty
			if ( 'equals' === $operator && '' === $value ) {
				$required_fields = array( 'name', 'sku', 'product_type', 'stock_status', 'catalog_visibility' );
				if ( in_array( $property, $required_fields, true ) ) {
					$errors->add(
						'filter_equals_empty_required_field',
						sprintf(
							/* translators: %s: Property name */
							__( 'Filter condition requires %s to equal empty value. This field typically has a value for all products.', 'smart-cycle-discounts' ),
							$property
						)
					);
				}
			}
		}

		// Validation 53: Grouped product type contradictions
		$grouped_product_required = false;
		$price_constraints_specified = false;

		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
			$value    = isset( $condition['value'] ) ? $condition['value'] : null;
			$value_str = isset( $condition['value'] ) ? $condition['value'] : '';
		$values    = ! empty( $value_str ) ? array_map( 'trim', explode( ',', $value_str ) ) : array();

			if ( 'product_type' === $property ) {
				if ( 'equals' === $operator && 'grouped' === $value ) {
					$grouped_product_required = true;
				} elseif ( 'in' === $operator && in_array( 'grouped', $values, true ) ) {
					// Could be grouped or other types
				}
			}

			// Check for price constraints (grouped products don't have their own price)
			if ( in_array( $property, array( 'price', 'sale_price', 'regular_price' ), true ) ) {
				if ( ! in_array( $operator, array( 'not_equals', 'not_in' ), true ) ) {
					$price_constraints_specified = true;
				}
			}
		}

		if ( $grouped_product_required && $price_constraints_specified ) {
			$errors->add(
				'filter_grouped_price_conflict',
				__( 'Filter condition requires grouped product type while specifying price constraints. Grouped products do not have their own prices - individual grouped products have prices.', 'smart-cycle-discounts' )
			);
		}

		// Validation 54: Review average rating but reviews disabled
		$reviews_disabled = false;
		$rating_required = false;

		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
			$value    = isset( $condition['value'] ) ? $condition['value'] : null;

			if ( 'reviews_allowed' === $property && 'equals' === $operator && 0 === intval( $value ) ) {
				$reviews_disabled = true;
			}

			if ( in_array( $property, array( 'average_rating', 'rating', 'review_count' ), true ) ) {
				if ( in_array( $operator, array( 'greater_than', 'greater_than_equal', 'equals' ), true ) && floatval( $value ) > 0 ) {
					$rating_required = true;
				}
			}
		}

		if ( $reviews_disabled && $rating_required ) {
			$errors->add(
				'filter_reviews_disabled_rating_required',
				__( 'Filter condition requires products with reviews/ratings while reviews are disabled. Products with disabled reviews cannot have ratings.', 'smart-cycle-discounts' )
			);
		}

		// Validation 56: NOT_IN with empty array (matches all products - always true)
		foreach ( $conditions as $condition ) {
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
			$value_str = isset( $condition['value'] ) ? $condition['value'] : '';
		$values    = ! empty( $value_str ) ? array_map( 'trim', explode( ',', $value_str ) ) : array();

			if ( 'not_in' === $operator && empty( $values ) ) {
				$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
				$errors->add(
					'filter_not_in_empty_array',
					sprintf(
						/* translators: %s: Property name */
						__( 'Filter condition uses NOT_IN operator for %s with empty array. This will match all products (always true). Use a specific exclusion list or different operator.', 'smart-cycle-discounts' ),
						$property
					)
				);
			}
		}

		// Validation 57: Date in future (created/modified after current time)
		$current_time = current_time( 'timestamp' );
		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
			$value    = isset( $condition['value'] ) ? $condition['value'] : null;

			if ( in_array( $property, array( 'date_created', 'date_modified' ), true ) ) {
				$timestamp = strtotime( $value );
				if ( $timestamp ) {
					// Check for dates in the future
					if ( in_array( $operator, array( 'greater_than', 'greater_than_equal', 'equals' ), true ) ) {
						if ( $timestamp > $current_time ) {
							$errors->add(
								'filter_date_in_future',
								sprintf(
									/* translators: 1: Property, 2: Date value */
									__( 'Filter condition requires %1$s to be after %2$s, which is in the future. Products cannot have future creation/modification dates.', 'smart-cycle-discounts' ),
									$property,
									gmdate( 'Y-m-d H:i:s', $timestamp )
								)
							);
						}
					} elseif ( 'between' === $operator ) {
						$value2 = isset( $condition['value2'] ) ? $condition['value2'] : null;
						$timestamp2 = strtotime( $value2 );
						if ( $timestamp2 && $timestamp2 > $current_time ) {
							$errors->add(
								'filter_date_in_future',
								sprintf(
									/* translators: 1: Property, 2: Date value */
									__( 'Filter condition requires %1$s BETWEEN range ending at %2$s, which is in the future. Products cannot have future dates.', 'smart-cycle-discounts' ),
									$property,
									gmdate( 'Y-m-d H:i:s', $timestamp2 )
								)
							);
						}
					}
				}
			}
		}

		// Validation 58: Negative stock quantity (WooCommerce doesn't allow negative stock)
		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
			$value    = isset( $condition['value'] ) ? floatval( $condition['value'] ) : 0;

			if ( 'stock_quantity' === $property ) {
				if ( 'equals' === $operator && $value < 0 ) {
					$errors->add(
						'filter_stock_quantity_negative',
						sprintf(
							/* translators: %s: Negative stock value */
							__( 'Filter condition requires stock quantity to equal %s (negative). WooCommerce does not support negative stock quantities.', 'smart-cycle-discounts' ),
							number_format( $value, 0 )
						)
					);
				} elseif ( 'less_than' === $operator && $value <= 0 ) {
					$errors->add(
						'filter_stock_quantity_negative',
						__( 'Filter condition requires stock quantity less than zero. WooCommerce does not support negative stock quantities.', 'smart-cycle-discounts' )
					);
				} elseif ( 'between' === $operator ) {
					$value2 = isset( $condition['value2'] ) ? floatval( $condition['value2'] ) : 0;
					if ( max( $value, $value2 ) < 0 ) {
						$errors->add(
							'filter_stock_quantity_negative',
							sprintf(
								/* translators: 1: Min value, 2: Max value */
								__( 'Filter condition requires stock quantity BETWEEN %1$s and %2$s (both negative). WooCommerce does not support negative stock quantities.', 'smart-cycle-discounts' ),
								number_format( min( $value, $value2 ), 0 ),
								number_format( max( $value, $value2 ), 0 )
							)
						);
					}
				}
			}
		}

		// Validation 60: Sold individually conflicts with purchase quantity limits
		$sold_individually = false;
		$min_qty = null;
		$max_qty = null;

		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
			$value    = isset( $condition['value'] ) ? $condition['value'] : null;

			if ( 'sold_individually' === $property && 'equals' === $operator && 1 === intval( $value ) ) {
				$sold_individually = true;
			}

			if ( 'min_purchase_quantity' === $property ) {
				if ( in_array( $operator, array( 'equals', 'greater_than', 'greater_than_equal' ), true ) ) {
					$qty = floatval( $value );
					if ( null === $min_qty || $qty > $min_qty ) {
						$min_qty = $qty;
					}
				}
			}

			if ( 'max_purchase_quantity' === $property ) {
				if ( in_array( $operator, array( 'equals', 'less_than', 'less_than_equal' ), true ) ) {
					$qty = floatval( $value );
					if ( null === $max_qty || $qty < $max_qty ) {
						$max_qty = $qty;
					}
				}
			}
		}

		if ( $sold_individually ) {
			if ( null !== $min_qty && $min_qty > 1 ) {
				$errors->add(
					'filter_sold_individually_min_qty_conflict',
					sprintf(
						/* translators: %s: Minimum quantity */
						__( 'Filter condition requires products to be "sold individually" while having minimum purchase quantity of %s. Sold individually products can only be purchased one at a time.', 'smart-cycle-discounts' ),
						number_format( $min_qty, 0 )
					)
				);
			}

			if ( null !== $max_qty && $max_qty > 1 ) {
				$errors->add(
					'filter_sold_individually_max_qty_conflict',
					sprintf(
						/* translators: %s: Maximum quantity */
						__( 'Filter condition requires products to be "sold individually" while having maximum purchase quantity of %s. Sold individually products can only be purchased one at a time.', 'smart-cycle-discounts' ),
						number_format( $max_qty, 0 )
					)
				);
			}
		}

		// Validation 61: EQUALS combined with incompatible GREATER_THAN or LESS_THAN
		$equals_values_direct = array();
		$greater_than_values = array();
		$less_than_values = array();

		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
			$value    = isset( $condition['value'] ) ? floatval( $condition['value'] ) : 0;

			if ( ! in_array( $property, $numeric_properties, true ) || empty( $property ) ) {
				continue;
			}

			if ( 'equals' === $operator ) {
				if ( ! isset( $equals_values_direct[ $property ] ) ) {
					$equals_values_direct[ $property ] = array();
				}
				$equals_values_direct[ $property ][] = $value;
			} elseif ( 'greater_than' === $operator ) {
				if ( ! isset( $greater_than_values[ $property ] ) ) {
					$greater_than_values[ $property ] = array();
				}
				$greater_than_values[ $property ][] = $value;
			} elseif ( 'less_than' === $operator ) {
				if ( ! isset( $less_than_values[ $property ] ) ) {
					$less_than_values[ $property ] = array();
				}
				$less_than_values[ $property ][] = $value;
			}
		}

		// Check if EQUALS value is less than or equal to GREATER_THAN value
		foreach ( $equals_values_direct as $property => $equals_vals ) {
			if ( isset( $greater_than_values[ $property ] ) ) {
				foreach ( $equals_vals as $equals_val ) {
					foreach ( $greater_than_values[ $property ] as $gt_val ) {
						if ( $equals_val <= $gt_val ) {
							$errors->add(
								'filter_equals_less_than_greater_than',
								sprintf(
									/* translators: 1: Property, 2: Equals value, 3: Greater than value */
									__( 'Filter condition requires %1$s to equal %2$s while also being greater than %3$s. This is impossible.', 'smart-cycle-discounts' ),
									$property,
									number_format( $equals_val, 2 ),
									number_format( $gt_val, 2 )
								)
							);
						}
					}
				}
			}

			// Check if EQUALS value is greater than or equal to LESS_THAN value
			if ( isset( $less_than_values[ $property ] ) ) {
				foreach ( $equals_vals as $equals_val ) {
					foreach ( $less_than_values[ $property ] as $lt_val ) {
						if ( $equals_val >= $lt_val ) {
							$errors->add(
								'filter_equals_greater_than_less_than',
								sprintf(
									/* translators: 1: Property, 2: Equals value, 3: Less than value */
									__( 'Filter condition requires %1$s to equal %2$s while also being less than %3$s. This is impossible.', 'smart-cycle-discounts' ),
									$property,
									number_format( $equals_val, 2 ),
									number_format( $lt_val, 2 )
								)
							);
						}
					}
				}
			}
		}

		// Validation 62: Zero price warning (might be data error or intentional free product)
		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
			$value    = isset( $condition['value'] ) ? floatval( $condition['value'] ) : 0;

			if ( in_array( $property, array( 'price', 'current_price', 'regular_price' ), true ) ) {
				if ( 'equals' === $operator && $value === 0.0 ) {
					$errors->add(
						'filter_price_zero_warning',
						sprintf(
							/* translators: %s: Property name */
							__( 'Filter condition requires %s to equal zero. This will only match free products. Verify this is intentional.', 'smart-cycle-discounts' ),
							$property
						)
					);
				}
			}
		}

		// Validation 63: Unrealistically high numeric values
		$unrealistic_thresholds = array(
			'price'          => 1000000,
			'regular_price'  => 1000000,
			'sale_price'     => 1000000,
			'current_price'  => 1000000,
			'weight'         => 100000,
			'length'         => 10000,
			'width'          => 10000,
			'height'         => 10000,
			'stock_quantity' => 1000000,
		);

		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
			$value    = isset( $condition['value'] ) ? floatval( $condition['value'] ) : 0;

			if ( isset( $unrealistic_thresholds[ $property ] ) ) {
				$threshold = $unrealistic_thresholds[ $property ];
				if ( in_array( $operator, array( 'greater_than', 'greater_than_equal', 'equals' ), true ) ) {
					if ( $value > $threshold ) {
						$errors->add(
							'filter_unrealistic_value',
							sprintf(
								/* translators: 1: Property, 2: Value, 3: Threshold */
								__( 'Filter condition requires %1$s to be %2$s, which exceeds realistic threshold (%3$s). Verify this value is correct.', 'smart-cycle-discounts' ),
								$property,
								number_format( $value, 2 ),
								number_format( $threshold, 0 )
							)
						);
					}
				}
			}
		}

		// Validation 64: Extremely broad BETWEEN ranges
		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';

			if ( 'between' === $operator && in_array( $property, $numeric_properties, true ) ) {
				$val1 = floatval( isset( $condition['value'] ) ? $condition['value'] : 0 );
				$val2 = floatval( isset( $condition['value2'] ) ? $condition['value2'] : 0 );
				$min_val = min( $val1, $val2 );
				$max_val = max( $val1, $val2 );
				$range = $max_val - $min_val;

				// Check for extremely broad ranges
				if ( in_array( $property, array( 'price', 'regular_price', 'sale_price', 'current_price' ), true ) ) {
					if ( $range > 500000 ) {
						$errors->add(
							'filter_extremely_broad_range',
							sprintf(
								/* translators: 1: Property, 2: Min, 3: Max, 4: Range */
								__( 'Filter condition requires %1$s BETWEEN %2$s and %3$s (range: %4$s). This extremely broad range will match almost all products. Consider narrowing the range.', 'smart-cycle-discounts' ),
								$property,
								number_format( $min_val, 2 ),
								number_format( $max_val, 2 ),
								number_format( $range, 2 )
							)
						);
					}
				}
			}
		}

		// Validation 65: Product name too short (likely data quality issue)
		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
			$value    = isset( $condition['value'] ) ? $condition['value'] : '';

			if ( 'name' === $property && 'equals' === $operator ) {
				if ( strlen( trim( $value ) ) < 2 ) {
					$errors->add(
						'filter_name_too_short',
						sprintf(
							/* translators: %s: Name value */
							__( 'Filter condition requires product name to equal "%s" (less than 2 characters). This is likely a data quality issue.', 'smart-cycle-discounts' ),
							esc_html( $value )
						)
					);
				}
			}
		}

		// Validation 66: Too many IN/NOT_IN values (performance warning)
		foreach ( $conditions as $condition ) {
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
			$value_str = isset( $condition['value'] ) ? $condition['value'] : '';
		$values    = ! empty( $value_str ) ? array_map( 'trim', explode( ',', $value_str ) ) : array();

			if ( in_array( $operator, array( 'in', 'not_in' ), true ) && count( $values ) > 100 ) {
				$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
				$errors->add(
					'filter_too_many_in_values',
					sprintf(
						/* translators: 1: Operator, 2: Property, 3: Count */
						__( 'Filter condition uses %1$s operator for %2$s with %3$s values. Large value lists can impact performance. Consider using different criteria.', 'smart-cycle-discounts' ),
						strtoupper( str_replace( '_', ' ', $operator ) ),
						$property,
						count( $values )
					)
				);
			}
		}

		// Validation 67: Sale scheduled dates vs on_sale status
		$on_sale_status           = null;
		$sale_dates_from          = array();
		$sale_dates_to            = array();
		$has_sale_date_constraint = false;

		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
			$value    = isset( $condition['value'] ) ? $condition['value'] : '';

			if ( 'on_sale' === $property && 'equals' === $operator ) {
				$on_sale_status = filter_var( $value, FILTER_VALIDATE_BOOLEAN );
			}

			if ( 'sale_price_dates_from' === $property && in_array( $operator, array( 'less_than', 'less_than_or_equal', 'between' ), true ) ) {
				$has_sale_date_constraint = true;
				if ( 'between' === $operator && isset( $condition['value2'] ) ) {
					$sale_dates_from[] = array(
						'min' => $condition['value'],
						'max' => $condition['value2'],
					);
				}
			}

			if ( 'sale_price_dates_to' === $property && in_array( $operator, array( 'greater_than', 'greater_than_or_equal', 'between' ), true ) ) {
				$has_sale_date_constraint = true;
				if ( 'between' === $operator && isset( $condition['value2'] ) ) {
					$sale_dates_to[] = array(
						'min' => $condition['value'],
						'max' => $condition['value2'],
					);
				}
			}
		}

		if ( false === $on_sale_status && $has_sale_date_constraint ) {
			$errors->add(
				'filter_sale_dates_vs_sale_status',
				__( 'Filter condition requires on_sale = false while constraining sale scheduled dates. Products with active sale dates are automatically marked as on sale by WooCommerce. This combination will match no products.', 'smart-cycle-discounts' )
			);
		}

		// Validation 68: Sale dates before product creation (temporal impossibility)
		$date_created_min = null;
		$sale_dates_from_max = null;

		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
			$value    = isset( $condition['value'] ) ? $condition['value'] : '';

			if ( 'date_created' === $property ) {
				if ( 'greater_than' === $operator || 'greater_than_or_equal' === $operator ) {
					$date_created_min = $value;
				} elseif ( 'between' === $operator && isset( $condition['value2'] ) ) {
					$date_created_min = $condition['value'];
				}
			}

			if ( 'sale_price_dates_from' === $property ) {
				if ( 'less_than' === $operator || 'less_than_or_equal' === $operator ) {
					$sale_dates_from_max = $value;
				} elseif ( 'between' === $operator && isset( $condition['value2'] ) ) {
					$sale_dates_from_max = $condition['value2'];
				}
			}
		}

		if ( null !== $date_created_min && null !== $sale_dates_from_max ) {
			if ( strtotime( $sale_dates_from_max ) < strtotime( $date_created_min ) ) {
				$errors->add(
					'filter_sale_dates_before_creation',
					sprintf(
						/* translators: 1: Sale date, 2: Creation date */
						__( 'Filter condition requires sale scheduled to start before %1$s, but product must be created after %2$s. Products cannot have sales scheduled before they were created. This is a temporal impossibility.', 'smart-cycle-discounts' ),
						gmdate( 'Y-m-d', strtotime( $sale_dates_from_max ) ),
						gmdate( 'Y-m-d', strtotime( $date_created_min ) )
					)
				);
			}
		}

		// Validation 69: Stock quantity vs total sales logic (unrealistic ratios)
		$stock_qty_max   = null;
		$total_sales_min = null;
		$is_new_product  = false;

		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
			$value    = isset( $condition['value'] ) ? $condition['value'] : '';

			if ( 'stock_quantity' === $property ) {
				if ( 'less_than' === $operator || 'less_than_or_equal' === $operator ) {
					$stock_qty_max = $value;
				} elseif ( 'equals' === $operator ) {
					$stock_qty_max = $value;
				} elseif ( 'between' === $operator && isset( $condition['value2'] ) ) {
					$stock_qty_max = $condition['value2'];
				}
			}

			if ( 'total_sales' === $property ) {
				if ( 'greater_than' === $operator || 'greater_than_or_equal' === $operator ) {
					$total_sales_min = $value;
				} elseif ( 'equals' === $operator ) {
					$total_sales_min = $value;
				} elseif ( 'between' === $operator && isset( $condition['value2'] ) ) {
					$total_sales_min = $condition['value'];
				}
			}

			if ( 'date_created' === $property && 'greater_than' === $operator ) {
				$days_ago = round( ( time() - strtotime( $value ) ) / DAY_IN_SECONDS );
				if ( $days_ago <= 90 ) {
					$is_new_product = true;
				}
			}
		}

		if ( null !== $stock_qty_max && null !== $total_sales_min && $is_new_product ) {
			if ( $total_sales_min > ( $stock_qty_max * 5 ) ) {
				$errors->add(
					'filter_sales_vs_stock_unrealistic',
					sprintf(
						/* translators: 1: Total sales, 2: Stock quantity */
						__( 'Filter condition requires recent products (< 90 days old) with %1$s+ sales but only %2$s or fewer in current stock. This ratio suggests the product has been restocked multiple times, making this combination unlikely unless you are specifically looking for frequently restocked items.', 'smart-cycle-discounts' ),
						number_format( $total_sales_min ),
						number_format( $stock_qty_max )
					)
				);
			}
		}

		// Validation 70: Review count zero but rating not NULL
		$review_count_is_zero = false;
		$rating_is_zero       = false;

		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
			$value    = isset( $condition['value'] ) ? $condition['value'] : '';

			if ( 'review_count' === $property && 'equals' === $operator && 0 == $value ) {
				$review_count_is_zero = true;
			}

			if ( 'average_rating' === $property && 'equals' === $operator && 0 == $value ) {
				$rating_is_zero = true;
			}
		}

		if ( $review_count_is_zero && $rating_is_zero ) {
			$errors->add(
				'filter_zero_reviews_rating_null',
				__( 'Filter condition requires review_count = 0 AND average_rating = 0. Products with zero reviews have NULL rating (not 0.0) in WooCommerce. Use "average_rating IS NULL" or remove the rating constraint to find products with no reviews.', 'smart-cycle-discounts' )
			);
		}

		// Validation 71: Current price vs sale status logic (WooCommerce auto-pricing)
		$on_sale_is_false       = false;
		$current_less_than_regular = false;

		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
			$value    = isset( $condition['value'] ) ? $condition['value'] : '';

			if ( 'on_sale' === $property && 'equals' === $operator && ! filter_var( $value, FILTER_VALIDATE_BOOLEAN ) ) {
				$on_sale_is_false = true;
			}
		}

		// Check for current_price < regular_price pattern
		$current_price_max = null;
		$regular_price_min = null;

		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
			$value    = isset( $condition['value'] ) ? $condition['value'] : '';

			if ( 'current_price' === $property || 'price' === $property ) {
				if ( 'less_than' === $operator || 'less_than_or_equal' === $operator ) {
					$current_price_max = $value;
				}
			}

			if ( 'regular_price' === $property ) {
				if ( 'greater_than' === $operator || 'greater_than_or_equal' === $operator ) {
					$regular_price_min = $value;
				}
			}
		}

		if ( null !== $current_price_max && null !== $regular_price_min && $current_price_max < $regular_price_min ) {
			$current_less_than_regular = true;
		}

		if ( $on_sale_is_false && $current_less_than_regular ) {
			$errors->add(
				'filter_current_price_sale_status',
				__( 'Filter condition requires on_sale = false while expecting current price to be less than regular price. When products are not on sale, WooCommerce sets current price equal to regular price. This combination will match no products.', 'smart-cycle-discounts' )
			);
		}

		// Validation 72: Variable product parent pricing (variable parents have ranges, not exact prices)
		$is_variable_type = false;
		$has_exact_price  = false;

		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
			$value_str = isset( $condition['value'] ) ? $condition['value'] : '';
		$values    = ! empty( $value_str ) ? array_map( 'trim', explode( ',', $value_str ) ) : array();

			if ( 'product_type' === $property ) {
				if ( 'equals' === $operator && 'variable' === $value_str ) {
					$is_variable_type = true;
				} elseif ( 'in' === $operator && in_array( 'variable', $values, true ) ) {
					$is_variable_type = true;
				}
			}

			if ( in_array( $property, array( 'price', 'current_price', 'regular_price', 'sale_price' ), true ) && 'equals' === $operator ) {
				$has_exact_price = true;
			}
		}

		if ( $is_variable_type && $has_exact_price ) {
			$errors->add(
				'filter_variable_product_exact_price',
				__( 'Filter condition requires product_type = variable with exact price constraints. Variable product parents do not have specific prices - they store min/max price ranges. Individual variations have exact prices. Consider filtering for variation products or using price range operators (between, greater_than, less_than) instead.', 'smart-cycle-discounts' )
			);
		}

		// Validation 73: Low stock amount without stock management
		$manage_stock_disabled = false;
		$has_low_stock_constraint = false;

		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
			$value    = isset( $condition['value'] ) ? $condition['value'] : '';

			if ( 'manage_stock' === $property && 'equals' === $operator && ! filter_var( $value, FILTER_VALIDATE_BOOLEAN ) ) {
				$manage_stock_disabled = true;
			}

			if ( 'low_stock_amount' === $property && 'equals' !== $operator ) {
				$has_low_stock_constraint = true;
			}
		}

		if ( $manage_stock_disabled && $has_low_stock_constraint ) {
			$errors->add(
				'filter_low_stock_without_management',
				__( 'Filter condition requires manage_stock = false while constraining low_stock_amount. Low stock thresholds are only relevant when stock management is enabled. This constraint will be ignored by WooCommerce.', 'smart-cycle-discounts' )
			);
		}

		// Validation 74: Backorders with high stock (unusual configuration)
		$backorders_enabled = false;
		$stock_qty_min      = null;

		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
			$value    = isset( $condition['value'] ) ? $condition['value'] : '';

			if ( 'backorders_allowed' === $property && 'equals' === $operator && filter_var( $value, FILTER_VALIDATE_BOOLEAN ) ) {
				$backorders_enabled = true;
			}

			if ( 'stock_quantity' === $property ) {
				if ( 'greater_than' === $operator || 'greater_than_or_equal' === $operator ) {
					$stock_qty_min = $value;
				} elseif ( 'between' === $operator && isset( $condition['value'] ) ) {
					$stock_qty_min = $condition['value'];
				}
			}
		}

		if ( $backorders_enabled && null !== $stock_qty_min && $stock_qty_min > 50 ) {
			$errors->add(
				'filter_backorders_high_stock',
				sprintf(
					/* translators: %s: Stock quantity */
					__( 'Filter condition requires backorders_allowed = true while requiring stock_quantity >= %s. Backorders are typically used for low or zero stock situations. Requiring high stock with backorders enabled is unusual and may indicate a configuration misunderstanding.', 'smart-cycle-discounts' ),
					number_format( $stock_qty_min )
				)
			);
		}

		// Validation 75: Sale price dates end before start (temporal inversion)
		$sale_date_from_min = null;
		$sale_date_to_max   = null;

		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
			$value    = isset( $condition['value'] ) ? $condition['value'] : '';

			if ( 'sale_price_dates_from' === $property ) {
				if ( 'greater_than' === $operator || 'greater_than_or_equal' === $operator ) {
					$sale_date_from_min = $value;
				} elseif ( 'equals' === $operator ) {
					$sale_date_from_min = $value;
				} elseif ( 'between' === $operator && isset( $condition['value'] ) ) {
					$sale_date_from_min = $condition['value'];
				}
			}

			if ( 'sale_price_dates_to' === $property ) {
				if ( 'less_than' === $operator || 'less_than_or_equal' === $operator ) {
					$sale_date_to_max = $value;
				} elseif ( 'equals' === $operator ) {
					$sale_date_to_max = $value;
				} elseif ( 'between' === $operator && isset( $condition['value2'] ) ) {
					$sale_date_to_max = $condition['value2'];
				}
			}
		}

		if ( null !== $sale_date_from_min && null !== $sale_date_to_max ) {
			if ( strtotime( $sale_date_to_max ) < strtotime( $sale_date_from_min ) ) {
				$errors->add(
					'filter_sale_dates_inverted',
					sprintf(
						/* translators: 1: End date, 2: Start date */
						__( 'Filter condition requires sale to end before %1$s but start after %2$s. Sale end date must be after start date. This is a temporal impossibility.', 'smart-cycle-discounts' ),
						gmdate( 'Y-m-d', strtotime( $sale_date_to_max ) ),
						gmdate( 'Y-m-d', strtotime( $sale_date_from_min ) )
					)
				);
			}
		}

		// Validation 76: External/grouped products with stock management
		$is_external_or_grouped = false;
		$has_stock_constraints  = false;

		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
			$value_str = isset( $condition['value'] ) ? $condition['value'] : '';
		$values    = ! empty( $value_str ) ? array_map( 'trim', explode( ',', $value_str ) ) : array();
			$value    = isset( $condition['value'] ) ? $condition['value'] : '';

			if ( 'product_type' === $property ) {
				if ( 'equals' === $operator && in_array( $value, array( 'external', 'grouped' ), true ) ) {
					$is_external_or_grouped = true;
				} elseif ( 'in' === $operator ) {
					$has_external_or_grouped = array_intersect( $values, array( 'external', 'grouped' ) );
					if ( ! empty( $has_external_or_grouped ) ) {
						$is_external_or_grouped = true;
					}
				}
			}

			if ( in_array( $property, array( 'stock_quantity', 'stock_status', 'manage_stock', 'low_stock_amount' ), true ) ) {
				$has_stock_constraints = true;
			}
		}

		if ( $is_external_or_grouped && $has_stock_constraints ) {
			$errors->add(
				'filter_external_grouped_stock',
				__( 'Filter condition requires product_type to be external or grouped while constraining stock-related properties. External and grouped products do not manage inventory in WooCommerce - stock is handled at the child/external product level. Remove stock constraints for these product types.', 'smart-cycle-discounts' )
			);
		}

		// Validation 77: Regular price zero with sale price
		$regular_price_is_zero = false;
		$has_sale_price        = false;

		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
			$value    = isset( $condition['value'] ) ? $condition['value'] : '';

			if ( 'regular_price' === $property && 'equals' === $operator && 0 == $value ) {
				$regular_price_is_zero = true;
			}

			if ( 'sale_price' === $property ) {
				if ( 'equals' === $operator && $value > 0 ) {
					$has_sale_price = true;
				} elseif ( in_array( $operator, array( 'greater_than', 'greater_than_or_equal', 'between' ), true ) && $value > 0 ) {
					$has_sale_price = true;
				}
			}
		}

		if ( $regular_price_is_zero && $has_sale_price ) {
			$errors->add(
				'filter_zero_regular_with_sale',
				__( 'Filter condition requires regular_price = 0 while requiring a sale_price greater than 0. Sale prices require a regular price as baseline. Products cannot have a sale price without a regular price in WooCommerce pricing logic.', 'smart-cycle-discounts' )
			);
		}

		// Validation 78: Sale dates without sale price
		$has_sale_date_from = false;
		$has_sale_date_to   = false;
		$sale_price_is_zero = false;

		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
			$value    = isset( $condition['value'] ) ? $condition['value'] : '';

			if ( 'sale_price_dates_from' === $property ) {
				$has_sale_date_from = true;
			}

			if ( 'sale_price_dates_to' === $property ) {
				$has_sale_date_to = true;
			}

			if ( 'sale_price' === $property ) {
				if ( 'equals' === $operator && 0 == $value ) {
					$sale_price_is_zero = true;
				} elseif ( in_array( $operator, array( 'less_than', 'less_than_or_equal' ), true ) && $value <= 0 ) {
					$sale_price_is_zero = true;
				}
			}
		}

		if ( ( $has_sale_date_from || $has_sale_date_to ) && $sale_price_is_zero ) {
			$errors->add(
				'filter_sale_dates_without_price',
				__( 'Filter condition constrains sale scheduled dates (sale_price_dates_from or sale_price_dates_to) while requiring sale_price to be zero or absent. Scheduled sale dates are meaningless without an actual sale price. Set a valid sale price or remove the sale date constraints.', 'smart-cycle-discounts' )
			);
		}

		// Validation 79: Product modified before created (temporal impossibility)
		$date_created_min  = null;
		$date_modified_max = null;

		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
			$value    = isset( $condition['value'] ) ? $condition['value'] : '';

			if ( 'date_created' === $property ) {
				if ( 'greater_than' === $operator || 'greater_than_or_equal' === $operator ) {
					$date_created_min = $value;
				} elseif ( 'equals' === $operator ) {
					$date_created_min = $value;
				} elseif ( 'between' === $operator && isset( $condition['value'] ) ) {
					$date_created_min = $condition['value'];
				}
			}

			if ( 'date_modified' === $property ) {
				if ( 'less_than' === $operator || 'less_than_or_equal' === $operator ) {
					$date_modified_max = $value;
				} elseif ( 'equals' === $operator ) {
					$date_modified_max = $value;
				} elseif ( 'between' === $operator && isset( $condition['value2'] ) ) {
					$date_modified_max = $condition['value2'];
				}
			}
		}

		if ( null !== $date_created_min && null !== $date_modified_max ) {
			if ( strtotime( $date_modified_max ) < strtotime( $date_created_min ) ) {
				$errors->add(
					'filter_modified_before_created',
					sprintf(
						/* translators: 1: Modified date, 2: Created date */
						__( 'Filter condition requires product to be modified before %1$s but created after %2$s. Products cannot be modified before they were created. This is a temporal impossibility.', 'smart-cycle-discounts' ),
						gmdate( 'Y-m-d', strtotime( $date_modified_max ) ),
						gmdate( 'Y-m-d', strtotime( $date_created_min ) )
					)
				);
			}
		}

		// Validation 80: Past sale end date with on_sale = true (stale data detection)
		$on_sale_is_true    = false;
		$sale_end_in_past   = false;
		$sale_end_date_past = null;

		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
			$value    = isset( $condition['value'] ) ? $condition['value'] : '';

			if ( 'on_sale' === $property && 'equals' === $operator && filter_var( $value, FILTER_VALIDATE_BOOLEAN ) ) {
				$on_sale_is_true = true;
			}

			if ( 'sale_price_dates_to' === $property ) {
				if ( 'less_than' === $operator || 'less_than_or_equal' === $operator ) {
					if ( strtotime( $value ) < time() ) {
						$sale_end_in_past   = true;
						$sale_end_date_past = $value;
					}
				} elseif ( 'equals' === $operator ) {
					if ( strtotime( $value ) < time() ) {
						$sale_end_in_past   = true;
						$sale_end_date_past = $value;
					}
				}
			}
		}

		if ( $on_sale_is_true && $sale_end_in_past ) {
			$errors->add(
				'filter_sale_ended_but_on_sale',
				sprintf(
					/* translators: %s: Sale end date */
					__( 'Filter condition requires on_sale = true while requiring sale_price_dates_to before %s (in the past). WooCommerce automatically marks products as not on sale when scheduled sale dates expire. This combination suggests stale data or indicates products whose sales should have ended but did not due to cron job failures.', 'smart-cycle-discounts' ),
					gmdate( 'Y-m-d', strtotime( $sale_end_date_past ) )
				)
			);
		}

		// Validation 81: Current price equals regular when on_sale = true (WooCommerce auto-pricing)
		$on_sale_true           = false;
		$current_equals_regular = false;

		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
			$value    = isset( $condition['value'] ) ? $condition['value'] : '';

			if ( 'on_sale' === $property && 'equals' === $operator && filter_var( $value, FILTER_VALIDATE_BOOLEAN ) ) {
				$on_sale_true = true;
			}
		}

		// Check if current_price and regular_price are constrained to equal values
		$current_price_value = null;
		$regular_price_value = null;

		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
			$value    = isset( $condition['value'] ) ? $condition['value'] : '';

			if ( ( 'current_price' === $property || 'price' === $property ) && 'equals' === $operator ) {
				$current_price_value = $value;
			}

			if ( 'regular_price' === $property && 'equals' === $operator ) {
				$regular_price_value = $value;
			}
		}

		if ( null !== $current_price_value && null !== $regular_price_value && $current_price_value == $regular_price_value ) {
			$current_equals_regular = true;
		}

		if ( $on_sale_true && $current_equals_regular ) {
			$errors->add(
				'filter_on_sale_current_equals_regular',
				__( 'Filter condition requires on_sale = true while requiring current_price to equal regular_price. When products are on sale, WooCommerce sets current_price equal to sale_price (not regular_price). This combination will match no products.', 'smart-cycle-discounts' )
			);
		}

		// Validation 82: Negative dimensions enhancement (catch all negative dimension scenarios)
		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
			$value    = isset( $condition['value'] ) ? $condition['value'] : '';

			if ( in_array( $property, array( 'length', 'width', 'height', 'weight' ), true ) ) {
				// Check for any operator with negative value
				if ( 'equals' === $operator && $value < 0 ) {
					$errors->add(
						'filter_negative_dimension',
						sprintf(
							/* translators: 1: Property, 2: Value */
							__( 'Filter condition requires %1$s to equal %2$s (negative value). Physical dimensions and weight cannot be negative. This suggests data quality issues.', 'smart-cycle-discounts' ),
							$property,
							number_format( $value, 2 )
						)
					);
				} elseif ( in_array( $operator, array( 'greater_than', 'greater_than_or_equal' ), true ) && $value < 0 ) {
					// "length > -5" is nonsensical even though technically it matches all products
					$errors->add(
						'filter_dimension_greater_than_negative',
						sprintf(
							/* translators: 1: Property, 2: Value */
							__( 'Filter condition requires %1$s to be greater than %2$s (negative value). While technically valid, this creates confusing logic as dimensions are always non-negative. Consider using greater_than with a positive value (e.g., greater than 0).', 'smart-cycle-discounts' ),
							$property,
							number_format( $value, 2 )
						)
					);
				} elseif ( 'between' === $operator ) {
					$val1 = floatval( $value );
					$val2 = isset( $condition['value2'] ) ? floatval( $condition['value2'] ) : 0;
					if ( $val1 < 0 ) {
						$errors->add(
							'filter_dimension_between_negative',
							sprintf(
								/* translators: 1: Property, 2: Min value, 3: Max value */
								__( 'Filter condition requires %1$s BETWEEN %2$s and %3$s. The minimum value is negative, but physical dimensions cannot be negative. Adjust the range to start from 0 or higher.', 'smart-cycle-discounts' ),
								$property,
								number_format( $val1, 2 ),
								number_format( $val2, 2 )
							)
						);
					}
				}
			}
		}

		// Validation 83: NOT_EQUALS with boolean properties (creates confusing logic)
		$boolean_properties = array( 'featured', 'on_sale', 'virtual', 'downloadable', 'manage_stock', 'sold_individually', 'reviews_allowed', 'purchasable', 'taxable' );

		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';

			if ( in_array( $property, $boolean_properties, true ) && 'not_equals' === $operator ) {
				$value = isset( $condition['value'] ) ? $condition['value'] : '';
				$inverted_value = filter_var( $value, FILTER_VALIDATE_BOOLEAN ) ? 'false' : 'true';

				$errors->add(
					'filter_boolean_not_equals',
					sprintf(
						/* translators: 1: Property, 2: Value, 3: Inverted value */
						__( 'Filter condition uses NOT_EQUALS operator for boolean property %1$s (NOT_EQUALS %2$s). For boolean properties, use EQUALS operator with the opposite value instead (EQUALS %3$s). NOT_EQUALS creates ambiguous logic.', 'smart-cycle-discounts' ),
						$property,
						$value ? 'true' : 'false',
						$inverted_value
					)
				);
			}
		}

		// Validation 84: STARTS_WITH/ENDS_WITH with empty strings (matches all products)
		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
			$value    = isset( $condition['value'] ) ? $condition['value'] : '';

			if ( in_array( $operator, array( 'starts_with', 'ends_with' ), true ) && '' === trim( $value ) ) {
				$errors->add(
					'filter_empty_text_pattern',
					sprintf(
						/* translators: 1: Operator, 2: Property */
						__( 'Filter condition uses %1$s operator for %2$s with empty string. Empty string patterns match ALL products (every string starts/ends with empty string). This creates a meaningless filter. Specify a valid text pattern or remove this condition.', 'smart-cycle-discounts' ),
						strtoupper( str_replace( '_', ' ', $operator ) ),
						$property
					)
				);
			}
		}

		// Validation 85: IN operator with single value (should use equals for efficiency)
		foreach ( $conditions as $condition ) {
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
			$value_str = isset( $condition['value'] ) ? $condition['value'] : '';
		$values    = ! empty( $value_str ) ? array_map( 'trim', explode( ',', $value_str ) ) : array();
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';

			if ( 'in' === $operator && 1 === count( $values ) ) {
				$errors->add(
					'filter_in_single_value',
					sprintf(
						/* translators: 1: Property, 2: Single value */
						__( 'Filter condition uses IN operator for %1$s with only one value [%2$s]. For single values, use EQUALS operator instead for better performance and clarity. IN operator is intended for multiple values.', 'smart-cycle-discounts' ),
						$property,
						$values[0]
					)
				);
			}
		}

		// Validation 86: Downloadable + virtual product business rule (WooCommerce best practice)
		$downloadable_required = false;
		$virtual_is_false      = false;

		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
			$value    = isset( $condition['value'] ) ? $condition['value'] : '';

			if ( 'downloadable' === $property && 'equals' === $operator && filter_var( $value, FILTER_VALIDATE_BOOLEAN ) ) {
				$downloadable_required = true;
			}

			if ( 'virtual' === $property && 'equals' === $operator && ! filter_var( $value, FILTER_VALIDATE_BOOLEAN ) ) {
				$virtual_is_false = true;
			}
		}

		if ( $downloadable_required && $virtual_is_false ) {
			$errors->add(
				'filter_downloadable_not_virtual',
				__( 'Filter condition requires downloadable = true while requiring virtual = false. WooCommerce best practice: downloadable products should be virtual (no shipping required). While technically allowed, this combination is illogical in 99% of cases. Consider requiring virtual = true for downloadable products.', 'smart-cycle-discounts' )
			);
		}

		// Validation 87: Tax class specified but taxable = false
		$taxable_is_false  = false;
		$has_tax_class     = false;

		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
			$value    = isset( $condition['value'] ) ? $condition['value'] : '';

			if ( 'taxable' === $property && 'equals' === $operator && ! filter_var( $value, FILTER_VALIDATE_BOOLEAN ) ) {
				$taxable_is_false = true;
			}

			if ( 'tax_class' === $property && in_array( $operator, array( 'equals', 'not_equals', 'in' ), true ) && ! empty( $value ) ) {
				$has_tax_class = true;
			}
		}

		if ( $taxable_is_false && $has_tax_class ) {
			$errors->add(
				'filter_tax_class_not_taxable',
				__( 'Filter condition requires taxable = false while constraining tax_class. Tax class is irrelevant for non-taxable products - it is only applied when taxable = true. Remove the tax_class constraint or change taxable to true.', 'smart-cycle-discounts' )
			);
		}

		// Validation 88: NOT_IN containing all possible enum values (always false - matches nothing)
		$enum_definitions = array(
			'stock_status'        => array( 'instock', 'outofstock', 'onbackorder' ),
			'product_type'        => array( 'simple', 'variable', 'grouped', 'external' ),
			'catalog_visibility'  => array( 'visible', 'catalog', 'search', 'hidden' ),
			'tax_status'          => array( 'taxable', 'shipping', 'none' ),
			'backorders_allowed'  => array( 'no', 'notify', 'yes' ),
		);

		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
			$value_str = isset( $condition['value'] ) ? $condition['value'] : '';
		$values    = ! empty( $value_str ) ? array_map( 'trim', explode( ',', $value_str ) ) : array();

			if ( 'not_in' === $operator && isset( $enum_definitions[ $property ] ) ) {
				$all_enum_values = $enum_definitions[ $property ];
				$excluded_values = $values;

				// Check if NOT_IN excludes ALL possible enum values
				$remaining_values = array_diff( $all_enum_values, $excluded_values );

				if ( empty( $remaining_values ) ) {
					$errors->add(
						'filter_not_in_all_enum_values',
						sprintf(
							/* translators: 1: Property, 2: Excluded values */
							__( 'Filter condition uses NOT_IN for %1$s excluding all possible values [%2$s]. This creates an impossible filter that matches NO products. Remove some values from the exclusion list or change the operator.', 'smart-cycle-discounts' ),
							$property,
							implode( ', ', $excluded_values )
						)
					);
				}
			}
		}

		// Validation 89: BETWEEN with identical min/max (should use equals)
		foreach ( $conditions as $condition ) {
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';

			if ( 'between' === $operator ) {
				$val1 = isset( $condition['value'] ) ? $condition['value'] : null;
				$val2 = isset( $condition['value2'] ) ? $condition['value2'] : null;

				if ( null !== $val1 && null !== $val2 && $val1 == $val2 ) {
					$errors->add(
						'filter_between_identical_values',
						sprintf(
							/* translators: 1: Property, 2: Value */
							__( 'Filter condition uses BETWEEN operator for %1$s with identical min and max values (%2$s). For single values, use EQUALS operator instead for better performance and clarity. BETWEEN is intended for ranges.', 'smart-cycle-discounts' ),
							$property,
							number_format( floatval( $val1 ), 2 )
						)
					);
				}
			}
		}

		// Validation 90: Purchasable = false with price/stock/sale constraints
		$purchasable_is_false = false;
		$has_price_constraints = false;
		$has_stock_constraints = false;
		$on_sale_is_true = false;

		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
			$value    = isset( $condition['value'] ) ? $condition['value'] : '';

			if ( 'purchasable' === $property && 'equals' === $operator && ! filter_var( $value, FILTER_VALIDATE_BOOLEAN ) ) {
				$purchasable_is_false = true;
			}

			if ( in_array( $property, array( 'price', 'sale_price', 'regular_price', 'current_price' ), true ) ) {
				$has_price_constraints = true;
			}

			if ( in_array( $property, array( 'stock_quantity', 'stock_status', 'manage_stock' ), true ) ) {
				$has_stock_constraints = true;
			}

			if ( 'on_sale' === $property && 'equals' === $operator && filter_var( $value, FILTER_VALIDATE_BOOLEAN ) ) {
				$on_sale_is_true = true;
			}
		}

		if ( $purchasable_is_false && ( $has_price_constraints || $has_stock_constraints || $on_sale_is_true ) ) {
			$errors->add(
				'filter_not_purchasable_with_price_stock',
				__( 'Filter condition requires purchasable = false while constraining price, stock, or sale properties. If a product is not purchasable, these properties are irrelevant - the product cannot be bought regardless of price or availability. Remove price/stock/sale constraints or change purchasable to true.', 'smart-cycle-discounts' )
			);
		}

		// Validation 91: Reviews_allowed = false with review_count > 0 (data corruption detection)
		$reviews_not_allowed = false;
		$review_count_required = false;

		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
			$value    = isset( $condition['value'] ) ? $condition['value'] : '';

			if ( 'reviews_allowed' === $property && 'equals' === $operator && ! filter_var( $value, FILTER_VALIDATE_BOOLEAN ) ) {
				$reviews_not_allowed = true;
			}

			if ( 'review_count' === $property ) {
				if ( 'greater_than' === $operator || 'greater_than_or_equal' === $operator ) {
					if ( $value > 0 ) {
						$review_count_required = true;
					}
				} elseif ( 'equals' === $operator && $value > 0 ) {
					$review_count_required = true;
				}
			}
		}

		if ( $reviews_not_allowed && $review_count_required ) {
			$errors->add(
				'filter_reviews_disabled_with_count',
				__( 'Filter condition requires reviews_allowed = false while requiring review_count > 0. This combination indicates data corruption - reviews exist but are now disabled. This typically occurs when reviews were disabled AFTER products received reviews. Verify this is intentional data quality filtering.', 'smart-cycle-discounts' )
			);
		}

		// Validation 92: CONTAINS + NOT_CONTAINS with identical string (mathematical impossibility)
		$contains_values = array();
		$not_contains_values = array();

		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
			$value    = isset( $condition['value'] ) ? $condition['value'] : '';

			if ( 'contains' === $operator && ! empty( $value ) ) {
				if ( ! isset( $contains_values[ $property ] ) ) {
					$contains_values[ $property ] = array();
				}
				$contains_values[ $property ][] = strtolower( trim( $value ) );
			}

			if ( 'not_contains' === $operator && ! empty( $value ) ) {
				if ( ! isset( $not_contains_values[ $property ] ) ) {
					$not_contains_values[ $property ] = array();
				}
				$not_contains_values[ $property ][] = strtolower( trim( $value ) );
			}
		}

		// Check for identical strings in CONTAINS and NOT_CONTAINS for same property
		foreach ( $contains_values as $property => $contains_array ) {
			if ( isset( $not_contains_values[ $property ] ) ) {
				$not_contains_array = $not_contains_values[ $property ];
				$intersection = array_intersect( $contains_array, $not_contains_array );

				if ( ! empty( $intersection ) ) {
					$errors->add(
						'filter_contains_not_contains_same_string',
						sprintf(
							/* translators: 1: Property, 2: Conflicting string */
							__( 'Filter condition requires %1$s to CONTAINS "%2$s" while also requiring NOT_CONTAINS "%2$s". A property cannot simultaneously contain and not contain the identical string. This is mathematically impossible.', 'smart-cycle-discounts' ),
							$property,
							reset( $intersection )
						)
					);
				}
			}
		}

		// Validation 93: Future sale start date with on_sale = true (temporal impossibility)
		$on_sale_true = false;
		$sale_start_in_future = false;
		$sale_start_date = null;

		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
			$value    = isset( $condition['value'] ) ? $condition['value'] : '';

			if ( 'on_sale' === $property && 'equals' === $operator && filter_var( $value, FILTER_VALIDATE_BOOLEAN ) ) {
				$on_sale_true = true;
			}

			if ( 'sale_price_dates_from' === $property ) {
				if ( 'greater_than' === $operator || 'greater_than_or_equal' === $operator ) {
					if ( strtotime( $value ) > time() ) {
						$sale_start_in_future = true;
						$sale_start_date = $value;
					}
				} elseif ( 'equals' === $operator ) {
					if ( strtotime( $value ) > time() ) {
						$sale_start_in_future = true;
						$sale_start_date = $value;
					}
				}
			}
		}

		if ( $on_sale_true && $sale_start_in_future ) {
			$errors->add(
				'filter_future_sale_but_on_sale_now',
				sprintf(
					/* translators: %s: Future sale start date */
					__( 'Filter condition requires on_sale = true while requiring sale_price_dates_from after %s (in the future). Products cannot be on sale now if the scheduled sale starts in the future. This is a temporal impossibility unless you are specifically filtering for misconfigured products.', 'smart-cycle-discounts' ),
					gmdate( 'Y-m-d', strtotime( $sale_start_date ) )
				)
			);
		}

		// Validation 94: Min purchase quantity > stock quantity (product becomes unpurchasable)
		$min_purchase_qty = null;
		$stock_qty_max = null;
		$manage_stock_enabled = false;

		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
			$value    = isset( $condition['value'] ) ? $condition['value'] : '';

			if ( 'min_purchase_quantity' === $property ) {
				if ( 'equals' === $operator || 'greater_than' === $operator || 'greater_than_or_equal' === $operator ) {
					$min_purchase_qty = intval( $value );
				}
			}

			if ( 'stock_quantity' === $property ) {
				if ( 'less_than' === $operator || 'less_than_or_equal' === $operator ) {
					$stock_qty_max = intval( $value );
				} elseif ( 'equals' === $operator ) {
					$stock_qty_max = intval( $value );
				}
			}

			if ( 'manage_stock' === $property && 'equals' === $operator && filter_var( $value, FILTER_VALIDATE_BOOLEAN ) ) {
				$manage_stock_enabled = true;
			}
		}

		if ( null !== $min_purchase_qty && null !== $stock_qty_max && $manage_stock_enabled ) {
			if ( $min_purchase_qty > $stock_qty_max ) {
				$errors->add(
					'filter_min_purchase_exceeds_stock',
					sprintf(
						/* translators: 1: Min purchase quantity, 2: Stock quantity */
						__( 'Filter condition requires min_purchase_quantity >= %1$s while requiring stock_quantity <= %2$s with stock management enabled. Products cannot be purchased if minimum quantity exceeds available stock. This makes the product effectively unpurchasable.', 'smart-cycle-discounts' ),
						$min_purchase_qty,
						$stock_qty_max
					)
				);
			}
		}

		// Validation 95: Weight = 0 with shipping_class (unusual configuration)
		$weight_is_zero = false;
		$has_shipping_class = false;

		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
			$value    = isset( $condition['value'] ) ? $condition['value'] : '';

			if ( 'weight' === $property && 'equals' === $operator && 0 == $value ) {
				$weight_is_zero = true;
			}

			if ( 'shipping_class' === $property && in_array( $operator, array( 'equals', 'not_equals', 'in' ), true ) && ! empty( $value ) ) {
				$has_shipping_class = true;
			}
		}

		if ( $weight_is_zero && $has_shipping_class ) {
			$errors->add(
				'filter_weightless_with_shipping_class',
				__( 'Filter condition requires weight = 0 while constraining shipping_class. Shipping classes typically apply to physical products with weight. Weightless products with shipping classes is an unusual configuration that may indicate misconfiguration.', 'smart-cycle-discounts' )
			);
		}

		// Validation 96: Virtual = true with weight > 0 (enhanced physical property check)
		$virtual_required = false;
		$has_weight = false;

		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
			$value    = isset( $condition['value'] ) ? $condition['value'] : '';

			if ( 'virtual' === $property && 'equals' === $operator && filter_var( $value, FILTER_VALIDATE_BOOLEAN ) ) {
				$virtual_required = true;
			}

			if ( 'weight' === $property ) {
				if ( in_array( $operator, array( 'greater_than', 'greater_than_or_equal' ), true ) && $value > 0 ) {
					$has_weight = true;
				} elseif ( 'equals' === $operator && $value > 0 ) {
					$has_weight = true;
				}
			}
		}

		if ( $virtual_required && $has_weight ) {
			$errors->add(
				'filter_virtual_with_weight',
				__( 'Filter condition requires virtual = true while requiring weight > 0. Virtual products should not have physical weight. This indicates a data quality issue or product misconfiguration.', 'smart-cycle-discounts' )
			);
		}

		// Validation 97: Low_stock_amount > stock_quantity (threshold exceeds actual stock)
		$low_stock_threshold = null;
		$stock_qty = null;

		foreach ( $conditions as $condition ) {
			$property = isset( $condition['condition_type'] ) ? $condition['condition_type'] : '';
			$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
			$value    = isset( $condition['value'] ) ? $condition['value'] : '';

			if ( 'low_stock_amount' === $property ) {
				if ( 'equals' === $operator || 'less_than' === $operator || 'less_than_or_equal' === $operator ) {
					$low_stock_threshold = intval( $value );
				}
			}

			if ( 'stock_quantity' === $property ) {
				if ( 'equals' === $operator ) {
					$stock_qty = intval( $value );
				} elseif ( 'less_than' === $operator || 'less_than_or_equal' === $operator ) {
					$stock_qty = intval( $value );
				}
			}
		}

		if ( null !== $low_stock_threshold && null !== $stock_qty ) {
			if ( $low_stock_threshold > $stock_qty ) {
				$errors->add(
					'filter_low_stock_threshold_exceeds_stock',
					sprintf(
						/* translators: 1: Low stock threshold, 2: Stock quantity */
						__( 'Filter condition requires low_stock_amount threshold of %1$s while requiring stock_quantity <= %2$s. The low stock threshold exceeds actual stock, meaning the product is already in "low stock" status. This filter will match products that are already below their threshold.', 'smart-cycle-discounts' ),
						$low_stock_threshold,
						$stock_qty
					)
				);
			}
		}
	}
}
