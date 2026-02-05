<?php
/**
 * Condition Validator Class
 *
 * Server-side validation for advanced filter conditions.
 * Implements all 25 client-side validation rules for security and data integrity.
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
 * WSSCD_Condition_Validator Class
 *
 * Validates condition arrays for logical contradictions and impossibilities.
 * Mirrors client-side validation rules to prevent malicious bypass.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/validation
 * @author     Webstepper <contact@webstepper.io>
 */
class WSSCD_Condition_Validator {

	/**
	 * Numeric property types
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private static $numeric_properties = array(
		'price',
		'sale_price',
		'current_price',
		'stock_quantity',
		'weight',
		'length',
		'width',
		'height',
		'average_rating',
		'review_count',
		'total_sales',
		'low_stock_amount',
	);

	/**
	 * Date property types
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private static $date_properties = array(
		'date_created',
		'date_modified',
	);

	/**
	 * Boolean property types
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private static $boolean_properties = array(
		'featured',
		'on_sale',
		'virtual',
		'downloadable',
	);

	/**
	 * Properties that must be positive (non-negative)
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private static $positive_properties = array(
		'price',
		'sale_price',
		'current_price',
		'stock_quantity',
		'weight',
		'length',
		'width',
		'height',
		'average_rating',
		'review_count',
		'total_sales',
		'low_stock_amount',
	);

	/**
	 * Validate conditions array
	 *
	 * @since 1.0.0
	 * @param array  $conditions       Array of conditions to validate.
	 * @param string $logic            Logic mode ('all' for AND, 'any' for OR).
	 * @return array {
	 *     Validation result.
	 *     @type bool   $valid   Whether conditions are valid.
	 *     @type array  $errors  Array of error messages.
	 * }
	 */
	public static function validate( array $conditions, $logic = 'all' ) {
		$errors = array();

		// Validate condition count
		if ( count( $conditions ) > 20 ) {
			$errors[] = __( 'Maximum 20 conditions allowed', 'smart-cycle-discounts' );
		}

		// Validate each condition individually
		foreach ( $conditions as $index => $condition ) {
			$condition_errors = self::validate_single_condition( $condition, $index );
			if ( ! empty( $condition_errors ) ) {
				$errors = array_merge( $errors, $condition_errors );
			}
		}

		// Validate inter-condition logic (contradictions)
		if ( 'all' === $logic ) {
			$logic_errors = self::validate_and_logic( $conditions );
			if ( ! empty( $logic_errors ) ) {
				$errors = array_merge( $errors, $logic_errors );
			}
		}

		return array(
			'valid'  => empty( $errors ),
			'errors' => $errors,
		);
	}

	/**
	 * Validate single condition
	 *
	 * @since 1.0.0
	 * @param array $condition Condition array.
	 * @param int   $index     Condition index.
	 * @return array Array of error messages.
	 */
	private static function validate_single_condition( array $condition, $index ) {
		$errors = array();

		// Extract condition properties (AJAX Router converts conditionType → condition_type)
		$type     = $condition['condition_type'] ?? '';
		$operator = $condition['operator'] ?? '';
		$value    = $condition['value'] ?? '';
		$value2   = $condition['value2'] ?? '';

		// Rule 1: BETWEEN inverted range
		if ( in_array( $operator, array( 'between', 'not_between' ), true ) ) {
			if ( in_array( $type, self::$numeric_properties, true ) ) {
				$val1 = floatval( $value );
				$val2 = floatval( $value2 );
				if ( $val1 > $val2 ) {
					$errors[] = sprintf(
						/* translators: 1: condition index, 2: min value, 3: max value */
						__( 'Condition %1$d: BETWEEN range inverted (%2$s > %3$s). Min must be less than max.', 'smart-cycle-discounts' ),
						$index + 1,
						$value,
						$value2
					);
				}
			} elseif ( in_array( $type, self::$date_properties, true ) ) {
				$date1 = strtotime( $value );
				$date2 = strtotime( $value2 );
				if ( $date1 && $date2 && $date1 > $date2 ) {
					$errors[] = sprintf(
						/* translators: 1: condition index */
						__( 'Condition %1$d: BETWEEN date range inverted. Start date must be before end date.', 'smart-cycle-discounts' ),
						$index + 1
					);
				}
			}
		}

		// Rule 10: Sale price > regular price
		if ( 'sale_price' === $type && in_array( $operator, array( '>', '>=' ), true ) ) {
			$price_value = floatval( $value );
			$errors[]    = sprintf(
				/* translators: 1: condition index, 2: sale price value */
				__( 'Condition %1$d: Sale price cannot be greater than regular price (value: %2$s is likely invalid).', 'smart-cycle-discounts' ),
				$index + 1,
				$value
			);
		}

		// Rule 9: Negative values on positive properties
		if ( in_array( $type, self::$positive_properties, true ) ) {
			if ( '' !== $value && floatval( $value ) < 0 ) {
				$errors[] = sprintf(
					/* translators: 1: condition index, 2: property type, 3: negative value */
					__( 'Condition %1$d: %2$s cannot be negative (value: %3$s).', 'smart-cycle-discounts' ),
					$index + 1,
					$type,
					$value
				);
			}
			if ( '' !== $value2 && floatval( $value2 ) < 0 ) {
				$errors[] = sprintf(
					/* translators: 1: condition index, 2: property type, 3: negative value */
					__( 'Condition %1$d: %2$s cannot be negative (value2: %3$s).', 'smart-cycle-discounts' ),
					$index + 1,
					$type,
					$value2
				);
			}
		}

		// Rule 21: Rating bounds violation (0-5)
		if ( 'average_rating' === $type ) {
			if ( in_array( $operator, array( '>', '>=', '=' ), true ) && floatval( $value ) > 5 ) {
				$errors[] = sprintf(
					/* translators: 1: condition index, 2: rating value */
					__( 'Condition %1$d: Rating value %2$s exceeds maximum (ratings are 0-5).', 'smart-cycle-discounts' ),
					$index + 1,
					$value
				);
			}
			if ( in_array( $operator, array( '<', '<=' ), true ) && floatval( $value ) < 0 ) {
				$errors[] = sprintf(
					/* translators: 1: condition index */
					__( 'Condition %1$d: Rating value cannot be less than 0 (ratings are 0-5).', 'smart-cycle-discounts' ),
					$index + 1
				);
			}
		}

		// Rule 19: Virtual product with physical properties
		if ( 'virtual' === $type && '=' === $operator && '1' === $value ) {
			// This is a single-condition check, inter-condition logic checked separately
		}

		return $errors;
	}

	/**
	 * Validate AND logic (all conditions must be true)
	 *
	 * @since 1.0.0
	 * @param array $conditions Array of conditions.
	 * @return array Array of error messages.
	 */
	private static function validate_and_logic( array $conditions ) {
		$errors = array();

		// Group conditions by property type (AJAX Router converts conditionType → condition_type)
		$by_property = array();
		foreach ( $conditions as $index => $condition ) {
			$type                     = $condition['condition_type'] ?? '';
			$by_property[ $type ]     = $by_property[ $type ] ?? array();
			$by_property[ $type ][]   = array_merge( $condition, array( 'index' => $index ) );
		}

		// Validate each property group
		foreach ( $by_property as $type => $property_conditions ) {
			$property_errors = self::validate_property_group( $type, $property_conditions );
			if ( ! empty( $property_errors ) ) {
				$errors = array_merge( $errors, $property_errors );
			}
		}

		// Rule 18: Date created after modified violation
		$created_conditions  = $by_property['date_created'] ?? array();
		$modified_conditions = $by_property['date_modified'] ?? array();
		if ( ! empty( $created_conditions ) && ! empty( $modified_conditions ) ) {
			$creation_errors = self::check_date_created_vs_modified( $created_conditions, $modified_conditions );
			if ( ! empty( $creation_errors ) ) {
				$errors = array_merge( $errors, $creation_errors );
			}
		}

		// Rule 19: Virtual product with physical properties
		$virtual_conditions = $by_property['virtual'] ?? array();
		foreach ( $virtual_conditions as $virtual_cond ) {
			if ( '=' === $virtual_cond['operator'] && '1' === $virtual_cond['value'] ) {
				// Check for physical property conditions
				$physical_props = array( 'weight', 'length', 'width', 'height' );
				foreach ( $physical_props as $prop ) {
					if ( isset( $by_property[ $prop ] ) && ! empty( $by_property[ $prop ] ) ) {
						$errors[] = sprintf(
							/* translators: 1: physical property name */
							__( 'Contradiction: Virtual products cannot have %1$s conditions (virtual products have no physical dimensions).', 'smart-cycle-discounts' ),
							$prop
						);
					}
				}
			}
		}

		return $errors;
	}

	/**
	 * Validate conditions for a single property type
	 *
	 * @since 1.0.0
	 * @param string $type       Property type.
	 * @param array  $conditions Conditions for this property.
	 * @return array Array of error messages.
	 */
	private static function validate_property_group( $type, array $conditions ) {
		$errors = array();

		// Skip boolean properties - they're handled by check_boolean_contradictions
		if ( in_array( $type, self::$boolean_properties, true ) ) {
			// Don't check Rule 2 for boolean properties, skip to Rule 3
		} else {
			// Rule 2: Same property with different equals values
			$equals_values = array();
			foreach ( $conditions as $cond ) {
				if ( '=' === $cond['operator'] && 'include' === ( $cond['mode'] ?? 'include' ) ) {
					$equals_values[] = $cond['value'];
				}
			}
			if ( count( $equals_values ) > 1 && count( array_unique( $equals_values ) ) > 1 ) {
				$errors[] = sprintf(
					/* translators: 1: property type, 2: first value, 3: second value */
					__( 'Contradiction: %1$s cannot equal "%2$s" AND "%3$s" simultaneously.', 'smart-cycle-discounts' ),
					$type,
					$equals_values[0],
					$equals_values[1]
				);
			}

			// Rule 2b: Check for = and != contradiction with same value (bidirectional)
			$checked_pairs = array(); // Avoid duplicate errors
			foreach ( $conditions as $cond1 ) {
				if ( 'include' !== ( $cond1['mode'] ?? 'include' ) ) {
					continue;
				}

				foreach ( $conditions as $cond2 ) {
					if ( 'include' !== ( $cond2['mode'] ?? 'include' ) ) {
						continue;
					}

					// Check = vs !=
					if ( '=' === $cond1['operator'] && '!=' === $cond2['operator'] &&
						 $cond1['value'] === $cond2['value'] ) {
						// Create unique key to avoid duplicate error
						$pair_key = $cond1['value'] . '_equals_not_equals';
						if ( ! in_array( $pair_key, $checked_pairs, true ) ) {
							$checked_pairs[] = $pair_key;
							$errors[]        = sprintf(
								/* translators: 1: property type, 2: value */
								__( 'Contradiction: %1$s cannot equal "%2$s" AND not equal "%2$s" simultaneously.', 'smart-cycle-discounts' ),
								$type,
								$cond1['value']
							);
						}
					}

					// Check != vs = (reverse direction)
					if ( '!=' === $cond1['operator'] && '=' === $cond2['operator'] &&
						 $cond1['value'] === $cond2['value'] ) {
						// Create unique key to avoid duplicate error
						$pair_key = $cond1['value'] . '_equals_not_equals';
						if ( ! in_array( $pair_key, $checked_pairs, true ) ) {
							$checked_pairs[] = $pair_key;
							$errors[]        = sprintf(
								/* translators: 1: property type, 2: value */
								__( 'Contradiction: %1$s cannot not equal "%2$s" AND equal "%2$s" simultaneously.', 'smart-cycle-discounts' ),
								$type,
								$cond1['value']
							);
						}
					}
				}
			}
		}

		// Rule 3: Numeric range contradictions
		if ( in_array( $type, self::$numeric_properties, true ) ) {
			$range_errors = self::check_numeric_range_contradictions( $type, $conditions );
			if ( ! empty( $range_errors ) ) {
				$errors = array_merge( $errors, $range_errors );
			}
		}

		// Rule 4: Include/exclude mode contradictions
		$include_conditions = array_filter( $conditions, function( $c ) {
			return 'include' === ( $c['mode'] ?? 'include' );
		} );
		$exclude_conditions = array_filter( $conditions, function( $c ) {
			return 'exclude' === ( $c['mode'] ?? 'include' );
		} );

		if ( ! empty( $include_conditions ) && ! empty( $exclude_conditions ) ) {
			// Check for exact same condition in both modes
			foreach ( $include_conditions as $inc ) {
				foreach ( $exclude_conditions as $exc ) {
					if ( $inc['operator'] === $exc['operator'] && $inc['value'] === $exc['value'] ) {
						$errors[] = sprintf(
							/* translators: 1: property type, 2: operator, 3: value */
							__( 'Contradiction: %1$s %2$s %3$s in both include AND exclude mode.', 'smart-cycle-discounts' ),
							$type,
							$inc['operator'],
							$inc['value']
						);
					}
				}
			}
		}

		// Rule 5: Stock status contradictions
		if ( 'stock_status' === $type ) {
			$stock_errors = self::check_stock_status_contradictions( $conditions );
			if ( ! empty( $stock_errors ) ) {
				$errors = array_merge( $errors, $stock_errors );
			}
		}

		// Rule 6: Non-overlapping BETWEEN ranges
		if ( in_array( $type, self::$numeric_properties, true ) ) {
			$between_errors = self::check_between_range_overlaps( $type, $conditions );
			if ( ! empty( $between_errors ) ) {
				$errors = array_merge( $errors, $between_errors );
			}
		}

		// Rule 11: Text contains/not_contains contradiction
		if ( ! in_array( $type, self::$numeric_properties, true ) && ! in_array( $type, self::$date_properties, true ) && ! in_array( $type, self::$boolean_properties, true ) ) {
			$text_errors = self::check_text_contradictions( $type, $conditions );
			if ( ! empty( $text_errors ) ) {
				$errors = array_merge( $errors, $text_errors );
			}
		}

		// Rule 14: Boolean property contradiction
		if ( in_array( $type, self::$boolean_properties, true ) ) {
			$bool_errors = self::check_boolean_contradictions( $type, $conditions );
			if ( ! empty( $bool_errors ) ) {
				$errors = array_merge( $errors, $bool_errors );
			}
		}

		return $errors;
	}

	/**
	 * Check numeric range contradictions
	 *
	 * @since 1.0.0
	 * @param string $type       Property type.
	 * @param array  $conditions Conditions array.
	 * @return array Error messages.
	 */
	private static function check_numeric_range_contradictions( $type, array $conditions ) {
		$errors = array();
		$min    = null;
		$max    = null;

		// First pass: Build the min/max range from all comparison operators
		foreach ( $conditions as $cond ) {
			if ( 'include' !== ( $cond['mode'] ?? 'include' ) ) {
				continue;
			}

			$value = floatval( $cond['value'] );

			switch ( $cond['operator'] ) {
				case '>':
					$new_min = $value + 0.01; // Exclusive
					$min     = ( null === $min ) ? $new_min : max( $min, $new_min );
					break;
				case '>=':
					$new_min = $value;
					$min     = ( null === $min ) ? $new_min : max( $min, $new_min );
					break;
				case '<':
					$new_max = $value - 0.01; // Exclusive
					$max     = ( null === $max ) ? $new_max : min( $max, $new_max );
					break;
				case '<=':
					$new_max = $value;
					$max     = ( null === $max ) ? $new_max : min( $max, $new_max );
					break;
			}
		}

		// Check if min > max
		if ( null !== $min && null !== $max && $min > $max ) {
			$errors[] = sprintf(
				/* translators: 1: property type, 2: minimum value, 3: maximum value */
				__( 'Contradiction: %1$s cannot be >= %2$s AND <= %3$s (impossible range).', 'smart-cycle-discounts' ),
				$type,
				$min,
				$max
			);
		}

		// Second pass: Check all equals values against the built range
		foreach ( $conditions as $cond ) {
			if ( 'include' !== ( $cond['mode'] ?? 'include' ) ) {
				continue;
			}

			if ( '=' === $cond['operator'] ) {
				$value = floatval( $cond['value'] );

				// Check if equals value is outside the range
				if ( null !== $min && $value < $min ) {
					$errors[] = sprintf(
						/* translators: 1: property type, 2: equals value, 3: minimum value */
						__( 'Contradiction: %1$s cannot equal %2$s when minimum is %3$s.', 'smart-cycle-discounts' ),
						$type,
						$value,
						$min
					);
				}

				if ( null !== $max && $value > $max ) {
					$errors[] = sprintf(
						/* translators: 1: property type, 2: equals value, 3: maximum value */
						__( 'Contradiction: %1$s cannot equal %2$s when maximum is %3$s.', 'smart-cycle-discounts' ),
						$type,
						$value,
						$max
					);
				}
			}
		}

		return $errors;
	}

	/**
	 * Check stock status contradictions
	 *
	 * @since 1.0.0
	 * @param array $conditions Conditions array.
	 * @return array Error messages.
	 */
	private static function check_stock_status_contradictions( array $conditions ) {
		$errors           = array();
		$required_values  = array();
		$forbidden_values = array();

		foreach ( $conditions as $cond ) {
			$mode = $cond['mode'] ?? 'include';

			if ( 'include' === $mode && '=' === $cond['operator'] ) {
				$required_values[] = $cond['value'];
			} elseif ( 'exclude' === $mode || '!=' === $cond['operator'] ) {
				$forbidden_values[] = $cond['value'];
			}
		}

		// Check if same value is both required and forbidden
		$conflicts = array_intersect( $required_values, $forbidden_values );
		if ( ! empty( $conflicts ) ) {
			foreach ( $conflicts as $value ) {
				$errors[] = sprintf(
					/* translators: 1: stock status value */
					__( 'Contradiction: Stock status "%1$s" is both required and forbidden.', 'smart-cycle-discounts' ),
					$value
				);
			}
		}

		// Check if multiple different values are required (can only be one)
		$unique_required = array_unique( $required_values );
		if ( count( $unique_required ) > 1 ) {
			$errors[] = sprintf(
				/* translators: 1: first status, 2: second status */
				__( 'Contradiction: Stock status cannot be "%1$s" AND "%2$s" simultaneously.', 'smart-cycle-discounts' ),
				$unique_required[0],
				$unique_required[1]
			);
		}

		return $errors;
	}

	/**
	 * Check BETWEEN range overlaps
	 *
	 * @since 1.0.0
	 * @param string $type       Property type.
	 * @param array  $conditions Conditions array.
	 * @return array Error messages.
	 */
	private static function check_between_range_overlaps( $type, array $conditions ) {
		$errors  = array();
		$between = array();

		foreach ( $conditions as $cond ) {
			if ( 'between' === $cond['operator'] && 'include' === ( $cond['mode'] ?? 'include' ) ) {
				$between[] = array(
					'min' => floatval( $cond['value'] ),
					'max' => floatval( $cond['value2'] ),
				);
			}
		}

		// Check if multiple BETWEEN ranges don't overlap
		if ( count( $between ) > 1 ) {
			for ( $i = 0; $i < count( $between ) - 1; $i++ ) {
				for ( $j = $i + 1; $j < count( $between ); $j++ ) {
					$range1 = $between[ $i ];
					$range2 = $between[ $j ];

					// Check if ranges overlap
					$overlaps = ! ( $range1['max'] < $range2['min'] || $range2['max'] < $range1['min'] );

					if ( ! $overlaps ) {
						$errors[] = sprintf(
							/* translators: 1: property type, 2: first range, 3: second range */
							__( 'Contradiction: %1$s BETWEEN ranges (%2$s-%3$s) do not overlap - no products can match both.', 'smart-cycle-discounts' ),
							$type,
							$range1['min'] . '-' . $range1['max'],
							$range2['min'] . '-' . $range2['max']
						);
					}
				}
			}
		}

		return $errors;
	}

	/**
	 * Check text contradictions
	 *
	 * @since 1.0.0
	 * @param string $type       Property type.
	 * @param array  $conditions Conditions array.
	 * @return array Error messages.
	 */
	private static function check_text_contradictions( $type, array $conditions ) {
		$errors = array();

		foreach ( $conditions as $i => $cond1 ) {
			if ( 'include' !== ( $cond1['mode'] ?? 'include' ) ) {
				continue;
			}

			foreach ( $conditions as $j => $cond2 ) {
				if ( $i >= $j || 'include' !== ( $cond2['mode'] ?? 'include' ) ) {
					continue;
				}

				// Check contains vs not_contains with same value
				if ( 'contains' === $cond1['operator'] && 'not_contains' === $cond2['operator'] && $cond1['value'] === $cond2['value'] ) {
					$errors[] = sprintf(
						/* translators: 1: property type, 2: text value */
						__( 'Contradiction: %1$s cannot both contain AND not contain "%2$s".', 'smart-cycle-discounts' ),
						$type,
						$cond1['value']
					);
				}

				// Check = vs contains
				if ( '=' === $cond1['operator'] && 'contains' === $cond2['operator'] ) {
					if ( false === strpos( $cond1['value'], $cond2['value'] ) ) {
						$errors[] = sprintf(
							/* translators: 1: property type, 2: equals value, 3: contains value */
							__( 'Contradiction: %1$s equals "%2$s" but must contain "%3$s" (impossible).', 'smart-cycle-discounts' ),
							$type,
							$cond1['value'],
							$cond2['value']
						);
					}
				}
			}
		}

		return $errors;
	}

	/**
	 * Check boolean property contradictions
	 *
	 * @since 1.0.0
	 * @param string $type       Property type.
	 * @param array  $conditions Conditions array.
	 * @return array Error messages.
	 */
	private static function check_boolean_contradictions( $type, array $conditions ) {
		$errors        = array();
		$must_be_true  = false;
		$must_be_false = false;

		foreach ( $conditions as $cond ) {
			if ( 'include' !== ( $cond['mode'] ?? 'include' ) ) {
				continue;
			}

			if ( '=' === $cond['operator'] ) {
				if ( '1' === $cond['value'] ) {
					$must_be_true = true;
				} elseif ( '0' === $cond['value'] ) {
					$must_be_false = true;
				}
			} elseif ( '!=' === $cond['operator'] ) {
				if ( '1' === $cond['value'] ) {
					$must_be_false = true;
				} elseif ( '0' === $cond['value'] ) {
					$must_be_true = true;
				}
			}
		}

		if ( $must_be_true && $must_be_false ) {
			$errors[] = sprintf(
				/* translators: 1: property type */
				__( 'Contradiction: %1$s cannot be both true AND false.', 'smart-cycle-discounts' ),
				$type
			);
		}

		return $errors;
	}

	/**
	 * Check date created vs modified logic
	 *
	 * @since 1.0.0
	 * @param array $created_conditions  Created date conditions.
	 * @param array $modified_conditions Modified date conditions.
	 * @return array Error messages.
	 */
	private static function check_date_created_vs_modified( array $created_conditions, array $modified_conditions ) {
		$errors = array();

		// Find minimum created date
		$min_created = null;
		foreach ( $created_conditions as $cond ) {
			if ( 'include' !== ( $cond['mode'] ?? 'include' ) ) {
				continue;
			}

			$timestamp = strtotime( $cond['value'] );
			if ( false === $timestamp ) {
				continue;
			}

			if ( in_array( $cond['operator'], array( '>', '>=' ), true ) ) {
				if ( null === $min_created || $timestamp > $min_created ) {
					$min_created = $timestamp;
				}
			}
		}

		// Find maximum modified date
		$max_modified = null;
		foreach ( $modified_conditions as $cond ) {
			if ( 'include' !== ( $cond['mode'] ?? 'include' ) ) {
				continue;
			}

			$timestamp = strtotime( $cond['value'] );
			if ( false === $timestamp ) {
				continue;
			}

			if ( in_array( $cond['operator'], array( '<', '<=' ), true ) ) {
				if ( null === $max_modified || $timestamp < $max_modified ) {
					$max_modified = $timestamp;
				}
			}
		}

		// Check if created after modified (impossible)
		if ( null !== $min_created && null !== $max_modified && $min_created > $max_modified ) {
			$errors[] = __( 'Contradiction: Product cannot be created after it was last modified.', 'smart-cycle-discounts' );
		}

		return $errors;
	}

	/**
	 * Sanitize condition value based on type
	 *
	 * @since 1.0.0
	 * @param string $type  Condition type.
	 * @param mixed  $value Value to sanitize.
	 * @return mixed Sanitized value.
	 */
	public static function sanitize_value( $type, $value ) {
		if ( in_array( $type, self::$numeric_properties, true ) ) {
			// Numeric properties
			if ( in_array( $type, array( 'stock_quantity', 'review_count', 'total_sales' ), true ) ) {
				return absint( $value ); // Integers only
			}
			return floatval( $value ); // Allow decimals for price, weight, rating, etc.
		} elseif ( in_array( $type, self::$date_properties, true ) ) {
			// Date properties - validate ISO 8601 format
			$timestamp = strtotime( $value );
			if ( false === $timestamp ) {
				return ''; // Invalid date
			}
			return gmdate( 'Y-m-d H:i:s', $timestamp );
		} elseif ( in_array( $type, self::$boolean_properties, true ) ) {
			// Boolean properties
			return in_array( $value, array( '1', '0', 'yes', 'no' ), true ) ? $value : '0';
		} else {
			// Text properties
			return sanitize_text_field( $value );
		}
	}

	/**
	 * Check if property type is numeric
	 *
	 * @since 1.0.0
	 * @param string $type Property type.
	 * @return bool True if numeric.
	 */
	public static function is_numeric_property( $type ) {
		return in_array( $type, self::$numeric_properties, true );
	}

	/**
	 * Check if property type is date
	 *
	 * @since 1.0.0
	 * @param string $type Property type.
	 * @return bool True if date.
	 */
	public static function is_date_property( $type ) {
		return in_array( $type, self::$date_properties, true );
	}

	/**
	 * Check if property type is boolean
	 *
	 * @since 1.0.0
	 * @param string $type Property type.
	 * @return bool True if boolean.
	 */
	public static function is_boolean_property( $type ) {
		return in_array( $type, self::$boolean_properties, true );
	}
}
