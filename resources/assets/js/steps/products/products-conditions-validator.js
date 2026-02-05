/**
 * Products Conditions Validator
 *
 * Validates advanced filter conditions for contradictions and impossibilities.
 * Real-time validation during condition configuration.
 * Uses central ValidationError component for consistent UI feedback.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/steps/products/products-conditions-validator.js
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress/plugins/smart-cycle-discounts/
 * @since      1.0.0
 */

( function( $ ) {
	'use strict';

	window.WSSCD = window.WSSCD || {};
	WSSCD.Modules = WSSCD.Modules || {};
	WSSCD.Modules.Products = WSSCD.Modules.Products || {};

	/**
	 * Conditions Validator Module
	 *
	 * @param {Object} state - State manager instance
	 * @class WSSCD.Modules.Products.ConditionsValidator
	 */
	WSSCD.Modules.Products.ConditionsValidator = function( state ) {
		this.state = state;
		this.$container = null;
		this.conditionTypes = {};
		this.numericProperties = [
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
			'low_stock_amount'
		];

		// Select field properties (dropdown/radio selections)
		this.selectProperties = [
			'stock_status',
			'product_type',
			'tax_status'
		];

		// Boolean properties (true/false)
		this.booleanProperties = [
			'featured',
			'on_sale',
			'virtual',
			'downloadable'
		];
	};

	WSSCD.Modules.Products.ConditionsValidator.prototype = {

		// ========================================
		// INITIALIZATION
		// ========================================

		/**
		 * Initialize validator
		 *
		 * @since 1.0.0
		 * @param {jQuery} $container Container element
		 * @returns {Object} This instance for chaining
		 */
		init: function( $container ) {
			this.$container = $container;
			this.loadConditionTypes();
			this.setupEventHandlers();
			return this;
		},

		/**
		 * Load condition types from field definitions
		 *
		 * @since 1.0.0
		 * @private
		 */
		loadConditionTypes: function() {
			// Get condition types from field definitions
			var fieldDefs = window.wsscdAdmin && window.wsscdAdmin.wsscdFieldDefinitions && window.wsscdAdmin.wsscdFieldDefinitions.products || {};
			if ( fieldDefs.conditionTypes ) {
				this.conditionTypes = fieldDefs.conditionTypes;
			}
		},

		/**
		 * Setup validation event handlers
		 *
		 * @since 1.0.0
		 * @private
		 */
		setupEventHandlers: function() {
			var self = this;

			// Validate on type change
			this.$container.on( 'change.wsscd-validator', '.wsscd-condition-type', function() {
				self.validateConditionRow( $( this ).closest( '.wsscd-condition-row' ) );
			} );

			// Validate on operator change
			this.$container.on( 'change.wsscd-validator', '.wsscd-condition-operator', function() {
				self.validateConditionRow( $( this ).closest( '.wsscd-condition-row' ) );
			} );

			// Validate on value change
			this.$container.on( 'input.wsscd-validator change.wsscd-validator', '.wsscd-condition-value', function() {
				self.validateConditionRow( $( this ).closest( '.wsscd-condition-row' ) );
			} );

			// Validate on mode change
			this.$container.on( 'change.wsscd-validator', '.wsscd-condition-mode', function() {
				self.validateConditionRow( $( this ).closest( '.wsscd-condition-row' ) );
			} );

			// Revalidate all on logic change
			this.$container.on( 'change.wsscd-validator', '[name="conditions_logic"]', function() {
				self.validateAllConditions();
			} );
		},

		// ========================================
		// VALIDATION LOGIC
		// ========================================

		/**
		 * Validate single condition row
		 *
		 * @since 1.0.0
		 * @param {jQuery} $row Condition row element
		 * @returns {boolean} True if valid
		 */
		validateConditionRow: function( $row ) {
			// Get current condition
			var condition = this.getConditionFromRow( $row );

			// Skip validation if condition is not complete
			if ( ! condition.conditionType || ! condition.operator ) {
				this.clearValidationErrors( $row );
				return true;
			}

			// Get all conditions
			var allConditions = this.collectAllConditions();

			// Get logic - must select the CHECKED radio button
			var logic = this.$container.find( '[name="conditions_logic"]:checked' ).val() || 'all';

			// Get current row index to exclude from comparison
			var currentIndex = $row.data( 'index' );

			// Run validations
			var issues = this.checkForIssues( condition, allConditions, logic, currentIndex );

			// Show/hide UI feedback
			if ( issues.length > 0 ) {
				this.showValidationErrors( $row, issues );
				return false;
			} else {
				this.clearValidationErrors( $row );
				return true;
			}
		},

		/**
		 * Validate all condition rows
		 *
		 * @since 1.0.0
		 * @returns {boolean} True if all valid
		 */
		validateAllConditions: function() {
			var self = this;
			var allValid = true;

			this.$container.find( '.wsscd-condition-row' ).each( function() {
				if ( ! self.validateConditionRow( $( this ) ) ) {
					allValid = false;
				}
			} );

			return allValid;
		},

		/**
		 * Check for validation issues
		 *
		 * @since 1.0.0
		 * @param {Object} condition Current condition
		 * @param {Array} allConditions All conditions
		 * @param {string} logic AND or OR logic
		 * @param {number} currentIndex Current condition index to exclude
		 * @returns {Array} Array of issue objects
		 */
		checkForIssues: function( condition, allConditions, logic, currentIndex ) {
			var issues = [];

			// 1. Check BETWEEN inverted range (applies to all logic types)
			var betweenIssue = this.checkBetweenInverted( condition );
			if ( betweenIssue ) {
				issues.push( betweenIssue );
			}

			// Only check contradictions for AND logic
			if ( 'all' === logic ) {
				// Filter out current condition
				var otherConditions = [];
				for ( var i = 0; i < allConditions.length; i++ ) {
					if ( i !== currentIndex ) {
						otherConditions.push( allConditions[i] );
					}
				}

				// 2. Check same property contradiction
				var samePropIssue = this.checkSamePropertyContradiction( condition, otherConditions );
				if ( samePropIssue ) {
					issues.push( samePropIssue );
				}

				// 3. Check numeric range contradiction
				if ( this.isNumericProperty( condition.conditionType ) ) {
					var numericIssue = this.checkNumericRangeContradiction( condition, otherConditions );
					if ( numericIssue ) {
						issues.push( numericIssue );
					}
				}

				// 4. Check include/exclude contradiction
				var modeIssue = this.checkIncludeExcludeContradiction( condition, otherConditions );
				if ( modeIssue ) {
					issues.push( modeIssue );
				}

				// 5. Check stock status contradiction
				var stockIssue = this.checkStockStatusContradiction( condition, otherConditions );
				if ( stockIssue ) {
					issues.push( stockIssue );
				}

				// 6. Check non-overlapping BETWEEN ranges
				var betweenIssue = this.checkNonOverlappingBetween( condition, otherConditions );
				if ( betweenIssue ) {
					issues.push( betweenIssue );
				}

				// 7. Check equals with incompatible range
				var equalsRangeIssue = this.checkEqualsIncompatibleRange( condition, otherConditions );
				if ( equalsRangeIssue ) {
					issues.push( equalsRangeIssue );
				}

				// 8. Check greater/less than equal impossibility
				var gteLteIssue = this.checkGreaterLessEqualImpossible( condition, otherConditions );
				if ( gteLteIssue ) {
					issues.push( gteLteIssue );
				}

				// 9. Check negative values on positive properties
				var negativeIssue = this.checkNegativeValueInvalid( condition );
				if ( negativeIssue ) {
					issues.push( negativeIssue );
				}

				// 10. Check sale price vs regular price logic
				var salePriceIssue = this.checkSalePriceGreaterThanRegular( condition, otherConditions );
				if ( salePriceIssue ) {
					issues.push( salePriceIssue );
				}

				// 11. Check text contains/not_contains contradiction
				var textContainsIssue = this.checkTextContainsContradiction( condition, otherConditions );
				if ( textContainsIssue ) {
					issues.push( textContainsIssue );
				}

				// 12. Check date range contradictions
				var dateRangeIssue = this.checkDateRangeImpossible( condition, otherConditions );
				if ( dateRangeIssue ) {
					issues.push( dateRangeIssue );
				}

				// 13. Check text pattern conflicts
				var textPatternIssue = this.checkTextPatternConflict( condition, otherConditions );
				if ( textPatternIssue ) {
					issues.push( textPatternIssue );
				}

				// 14. Check boolean property contradiction
				var booleanIssue = this.checkBooleanContradiction( condition, otherConditions );
				if ( booleanIssue ) {
					issues.push( booleanIssue );
				}

				// 15. Check IN/NOT_IN complete negation
				var inNotInIssue = this.checkInNotInNegation( condition, otherConditions );
				if ( inNotInIssue ) {
					issues.push( inNotInIssue );
				}

				// 16. Check select option exhaustion
				var exhaustionIssue = this.checkSelectExhaustion( condition, otherConditions );
				if ( exhaustionIssue ) {
					issues.push( exhaustionIssue );
				}

				// 17. Check NOT_BETWEEN overlapping coverage
				var notBetweenIssue = this.checkNotBetweenOverlap( condition, otherConditions );
				if ( notBetweenIssue ) {
					issues.push( notBetweenIssue );
				}

				// 18. Check date created after modified
				var dateTemporalIssue = this.checkDateTemporalViolation( condition, otherConditions );
				if ( dateTemporalIssue ) {
					issues.push( dateTemporalIssue );
				}

				// 19. Check virtual product with physical properties
				var virtualPhysicalIssue = this.checkVirtualPhysicalConflict( condition, otherConditions );
				if ( virtualPhysicalIssue ) {
					issues.push( virtualPhysicalIssue );
				}

				// 20. Check EQUALS with NOT_IN contradiction
				var equalsNotInIssue = this.checkEqualsNotInContradiction( condition, otherConditions );
				if ( equalsNotInIssue ) {
					issues.push( equalsNotInIssue );
				}

				// 21. Check rating bounds violation
				var ratingBoundsIssue = this.checkRatingBoundsViolation( condition );
				if ( ratingBoundsIssue ) {
					issues.push( ratingBoundsIssue );
				}

				// 22. Check stock status vs quantity logic
				var stockStatusIssue = this.checkStockStatusQuantityConflict( condition, otherConditions );
				if ( stockStatusIssue ) {
					issues.push( stockStatusIssue );
				}

				// 23. Check text EQUALS vs text operators
				var textEqualsIssue = this.checkTextEqualsOperatorConflict( condition, otherConditions );
				if ( textEqualsIssue ) {
					issues.push( textEqualsIssue );
				}

				// 24. Check EQUALS with NOT_BETWEEN excluding value
				var equalsNotBetweenIssue = this.checkEqualsNotBetweenConflict( condition, otherConditions );
				if ( equalsNotBetweenIssue ) {
					issues.push( equalsNotBetweenIssue );
				}

				// 25. Check low stock threshold logic
				var lowStockIssue = this.checkLowStockThresholdConflict( condition, otherConditions );
				if ( lowStockIssue ) {
					issues.push( lowStockIssue );
				}
			}

			return issues;
		},

		/**
		 * Check for BETWEEN inverted range (min > max)
		 *
		 * @since 1.0.0
		 * @param {Object} condition Condition to check
		 * @returns {Object|null} Issue object or null
		 */
		checkBetweenInverted: function( condition ) {
			if ( 'between' !== condition.operator && 'not_between' !== condition.operator ) {
				return null;
			}

			if ( ! condition.value || ! condition.value2 ) {
				return null;
			}

			var val1 = parseFloat( condition.value );
			var val2 = parseFloat( condition.value2 );

			if ( isNaN( val1 ) || isNaN( val2 ) ) {
				return null;
			}

			if ( val1 > val2 ) {
				return {
					type: 'between_inverted',
					message: 'First value must be less than second value',
					severity: 'error',
					field: 'value'
				};
			}

			return null;
		},

		/**
		 * Check same property with different equals values
		 *
		 * @since 1.0.0
		 * @param {Object} condition Current condition
		 * @param {Array} otherConditions Other conditions
		 * @returns {Object|null} Issue object or null
		 */
	checkSamePropertyContradiction: function( condition, otherConditions ) {
		// Only check = and != operators
		if ( '=' !== condition.operator && '!=' !== condition.operator ) {
			return null;
		}

		// Skip boolean properties - they're handled by checkBooleanContradiction
		if ( this.isBooleanProperty && this.isBooleanProperty( condition.conditionType ) ) {
			return null;
		}

		for ( var i = 0; i < otherConditions.length; i++ ) {
			var other = otherConditions[i];

			// CASE 1: Current is = operator
			if ( '=' === condition.operator ) {
				// 1a. Check another = with different value
				if ( other.conditionType === condition.conditionType &&
					 '=' === other.operator &&
					 other.value !== condition.value ) {
					return {
						type: 'same_property_different_values',
						message: condition.conditionType + ' cannot equal "' + condition.value + '" AND "' + other.value + '" simultaneously',
						severity: 'error',
						field: 'operator'
					};
				}

				// 1b. Check != with same value (forward direction)
				if ( other.conditionType === condition.conditionType &&
					 '!=' === other.operator &&
					 other.value === condition.value ) {
					return {
						type: 'equals_not_equals_contradiction',
						message: condition.conditionType + ' cannot equal "' + condition.value + '" AND not equal "' + condition.value + '" simultaneously',
						severity: 'error',
						field: 'operator'
					};
				}
			}

			// CASE 2: Current is != operator (reverse direction check)
			if ( '!=' === condition.operator ) {
				// 2a. Check = with same value (bidirectional)
				if ( other.conditionType === condition.conditionType &&
					 '=' === other.operator &&
					 other.value === condition.value ) {
					return {
						type: 'equals_not_equals_contradiction',
						message: condition.conditionType + ' cannot not equal "' + condition.value + '" AND equal "' + condition.value + '" simultaneously',
						severity: 'error',
						field: 'operator'
					};
				}
			}
		}

		return null;
	},

		/**
		 * Check numeric range contradictions
		 *
		 * @since 1.0.0
		 * @param {Object} condition Current condition
		 * @param {Array} otherConditions Other conditions
		 * @returns {Object|null} Issue object or null
		 */
		checkNumericRangeContradiction: function( condition, otherConditions ) {
			// Build range constraints for this property
			var min = -Infinity;
			var max = Infinity;

			// Apply current condition constraints
			var currentValue = parseFloat( condition.value );
			if ( isNaN( currentValue ) ) {
				return null;
			}

			if ( 'greater_than' === condition.operator || '>' === condition.operator ) {
				min = Math.max( min, currentValue + 0.01 );
			} else if ( 'greater_than_equal' === condition.operator || '>=' === condition.operator ) {
				min = Math.max( min, currentValue );
			} else if ( 'less_than' === condition.operator || '<' === condition.operator ) {
				max = Math.min( max, currentValue - 0.01 );
			} else if ( 'less_than_equal' === condition.operator || '<=' === condition.operator ) {
				max = Math.min( max, currentValue );
			} else if ( 'between' === condition.operator ) {
				var value2 = parseFloat( condition.value2 );
				if ( ! isNaN( value2 ) ) {
					min = Math.max( min, currentValue );
					max = Math.min( max, value2 );
				}
			}

			// Apply other conditions constraints
			for ( var i = 0; i < otherConditions.length; i++ ) {
				var other = otherConditions[i];

				if ( other.conditionType !== condition.conditionType ) {
					continue;
				}

				var otherValue = parseFloat( other.value );
				if ( isNaN( otherValue ) ) {
					continue;
				}

				if ( 'greater_than' === other.operator || '>' === other.operator ) {
					min = Math.max( min, otherValue + 0.01 );
				} else if ( 'greater_than_equal' === other.operator || '>=' === other.operator ) {
					min = Math.max( min, otherValue );
				} else if ( 'less_than' === other.operator || '<' === other.operator ) {
					max = Math.min( max, otherValue - 0.01 );
				} else if ( 'less_than_equal' === other.operator || '<=' === other.operator ) {
					max = Math.min( max, otherValue );
				} else if ( 'between' === other.operator ) {
					var otherValue2 = parseFloat( other.value2 );
					if ( ! isNaN( otherValue2 ) ) {
						min = Math.max( min, otherValue );
						max = Math.min( max, otherValue2 );
					}
				}
			}

			// Check if range is impossible
			if ( min > max ) {
				return {
					type: 'numeric_range_impossible',
					message: 'This creates an impossible range (min > max). No products can match.',
					severity: 'error',
					field: 'operator'
				};
			}

			return null;
		},

		/**
		 * Check include/exclude mode contradictions
		 *
		 * @since 1.0.0
		 * @param {Object} condition Current condition
		 * @param {Array} otherConditions Other conditions
		 * @returns {Object|null} Issue object or null
		 */
		checkIncludeExcludeContradiction: function( condition, otherConditions ) {
			var signature = condition.conditionType + '_' + condition.operator + '_' + condition.value;
			var currentMode = condition.mode || 'include';

			for ( var i = 0; i < otherConditions.length; i++ ) {
				var other = otherConditions[i];
				var otherSignature = other.conditionType + '_' + other.operator + '_' + other.value;
				var otherMode = other.mode || 'include';

				if ( signature === otherSignature && currentMode !== otherMode ) {
					return {
						type: 'include_exclude_contradiction',
						message: 'Cannot both INCLUDE and EXCLUDE the same condition',
						severity: 'error',
						field: 'mode'
					};
				}
			}

			return null;
		},

		/**
		 * Check stock status contradictions
		 *
		 * @since 1.0.0
		 * @param {Object} condition Current condition
		 * @param {Array} otherConditions Other conditions
		 * @returns {Object|null} Issue object or null
		 */
		checkStockStatusContradiction: function( condition, otherConditions ) {
			var hasStockQtyPositive = false;
			var stockStatusOutOfStock = false;

			// Check current condition
			if ( 'stock_quantity' === condition.conditionType ) {
				var operators = [ 'greater_than', '>', 'greater_than_equal', '>=' ];
				if ( -1 !== operators.indexOf( condition.operator ) ) {
					var value = parseFloat( condition.value );
					if ( ! isNaN( value ) && value > 0 ) {
						hasStockQtyPositive = true;
					}
				}
			}

			if ( 'stock_status' === condition.conditionType && 'equals' === condition.operator ) {
				if ( 'outofstock' === condition.value ) {
					stockStatusOutOfStock = true;
				}
			}

			// Check other conditions
			for ( var i = 0; i < otherConditions.length; i++ ) {
				var other = otherConditions[i];

				if ( 'stock_quantity' === other.conditionType ) {
					var otherOperators = [ 'greater_than', '>', 'greater_than_equal', '>=' ];
					if ( -1 !== otherOperators.indexOf( other.operator ) ) {
						var otherValue = parseFloat( other.value );
						if ( ! isNaN( otherValue ) && otherValue > 0 ) {
							hasStockQtyPositive = true;
						}
					}
				}

				if ( 'stock_status' === other.conditionType && 'equals' === other.operator ) {
					if ( 'outofstock' === other.value ) {
						stockStatusOutOfStock = true;
					}
				}
			}

			if ( hasStockQtyPositive && stockStatusOutOfStock ) {
				return {
					type: 'stock_status_contradiction',
					message: 'Cannot have positive stock quantity AND be out of stock',
					severity: 'error',
					field: 'operator'
				};
			}

			return null;
		},

		/**
		 * Check for non-overlapping BETWEEN ranges (Validation 6)
		 *
		 * @since 1.0.0
		 * @param {Object} condition Current condition
		 * @param {Array} otherConditions Other conditions
		 * @returns {Object|null} Issue object or null
		 */
		checkNonOverlappingBetween: function( condition, otherConditions ) {
			if ( 'between' !== condition.operator ) {
				return null;
			}

			var val1 = parseFloat( condition.value ) || 0;
			var val2 = parseFloat( condition.value2 ) || 0;
			var currentMin = Math.min( val1, val2 );
			var currentMax = Math.max( val1, val2 );

			for ( var i = 0; i < otherConditions.length; i++ ) {
				var other = otherConditions[i];

				if ( other.conditionType !== condition.conditionType || 'between' !== other.operator ) {
					continue;
				}

				var otherVal1 = parseFloat( other.value ) || 0;
				var otherVal2 = parseFloat( other.value2 ) || 0;
				var otherMin = Math.min( otherVal1, otherVal2 );
				var otherMax = Math.max( otherVal1, otherVal2 );

				// Check if ranges don't overlap
				if ( currentMax < otherMin || otherMax < currentMin ) {
					return {
						type: 'non_overlapping_between',
						message: 'BETWEEN ranges (' + currentMin + '-' + currentMax + ' and ' + otherMin + '-' + otherMax + ') do not overlap',
						severity: 'error',
						field: 'value'
					};
				}
			}

			return null;
		},

		/**
		 * Check for equals with incompatible range (Validation 7)
		 *
		 * @since 1.0.0
		 * @param {Object} condition Current condition
		 * @param {Array} otherConditions Other conditions
		 * @returns {Object|null} Issue object or null
		 */
		checkEqualsIncompatibleRange: function( condition, otherConditions ) {
			// Check if current condition is EQUALS (fix: was checking 'equals' instead of '=')
			if ( '=' !== condition.operator && 'equals' !== condition.operator ) {
				return null;
			}

			// Skip if not numeric property
			if ( ! this.isNumericProperty( condition.conditionType ) ) {
				return null;
			}

			var equalsValue = parseFloat( condition.value );
			if ( isNaN( equalsValue ) ) {
				return null;
			}

			// Check against all other conditions with same property
			for ( var i = 0; i < otherConditions.length; i++ ) {
				var other = otherConditions[i];

				if ( other.conditionType !== condition.conditionType ) {
					continue;
				}

				// Skip if not include mode
				if ( 'exclude' === ( other.mode || 'include' ) ) {
					continue;
				}

				var otherValue = parseFloat( other.value );
				if ( isNaN( otherValue ) ) {
					continue;
				}

				var incompatible = false;
				var operatorLabel = other.operator;

				// Check against comparison operators
				if ( '>' === other.operator || 'greater_than' === other.operator ) {
					incompatible = ( equalsValue <= otherValue );
					operatorLabel = 'greater than ' + otherValue;
				} else if ( '>=' === other.operator || 'greater_than_equal' === other.operator ) {
					incompatible = ( equalsValue < otherValue );
					operatorLabel = 'greater than or equal to ' + otherValue;
				} else if ( '<' === other.operator || 'less_than' === other.operator ) {
					incompatible = ( equalsValue >= otherValue );
					operatorLabel = 'less than ' + otherValue;
				} else if ( '<=' === other.operator || 'less_than_equal' === other.operator ) {
					incompatible = ( equalsValue > otherValue );
					operatorLabel = 'less than or equal to ' + otherValue;
				} else if ( 'between' === other.operator ) {
					var otherVal2 = parseFloat( other.value2 );
					if ( ! isNaN( otherVal2 ) ) {
						var rangeMin = Math.min( otherValue, otherVal2 );
						var rangeMax = Math.max( otherValue, otherVal2 );
						incompatible = ( equalsValue < rangeMin || equalsValue > rangeMax );
						operatorLabel = 'between ' + rangeMin + ' and ' + rangeMax;
					}
				} else if ( 'not_between' === other.operator ) {
					var otherVal2 = parseFloat( other.value2 );
					if ( ! isNaN( otherVal2 ) ) {
						var rangeMin = Math.min( otherValue, otherVal2 );
						var rangeMax = Math.max( otherValue, otherVal2 );
						// Value is incompatible if it falls within the excluded range
						incompatible = ( equalsValue >= rangeMin && equalsValue <= rangeMax );
						operatorLabel = 'not between ' + rangeMin + ' and ' + rangeMax;
					}
				}

				if ( incompatible ) {
					return {
						type: 'equals_incompatible_range',
						message: condition.conditionType + ' cannot equal ' + equalsValue + ' while also being ' + operatorLabel,
						severity: 'error',
						field: 'value'
					};
				}
			}

			return null;
		},

		/**
		 * Check for >= X AND <= Y impossibility (Validation 8)
		 *
		 * @since 1.0.0
		 * @param {Object} condition Current condition
		 * @param {Array} otherConditions Other conditions
		 * @returns {Object|null} Issue object or null
		 */
		checkGreaterLessEqualImpossible: function( condition, otherConditions ) {
			// Skip if not numeric property
			if ( ! this.isNumericProperty( condition.conditionType ) ) {
				return null;
			}

			var minGte = null;
			var maxLte = null;

			// Check current condition
			if ( 'greater_than_equal' === condition.operator || '>=' === condition.operator ) {
				minGte = parseFloat( condition.value ) || 0;
			} else if ( 'less_than_equal' === condition.operator || '<=' === condition.operator ) {
				maxLte = parseFloat( condition.value ) || 0;
			}

			// Check other conditions with same property
			for ( var i = 0; i < otherConditions.length; i++ ) {
				var other = otherConditions[i];

				if ( other.conditionType !== condition.conditionType ) {
					continue;
				}

				// Skip if not include mode
				if ( 'exclude' === ( other.mode || 'include' ) ) {
					continue;
				}

				if ( 'greater_than_equal' === other.operator || '>=' === other.operator ) {
					var gteValue = parseFloat( other.value ) || 0;
					minGte = ( null === minGte ) ? gteValue : Math.max( minGte, gteValue );
				} else if ( 'less_than_equal' === other.operator || '<=' === other.operator ) {
					var lteValue = parseFloat( other.value ) || 0;
					maxLte = ( null === maxLte ) ? lteValue : Math.min( maxLte, lteValue );
				}

				// NEW: Check if OTHER condition is EQUALS and incompatible with current range
				if ( '=' === other.operator || 'equals' === other.operator ) {
					var equalsValue = parseFloat( other.value );
					if ( ! isNaN( equalsValue ) ) {
						var currentValue = parseFloat( condition.value );
						if ( ! isNaN( currentValue ) ) {
							var incompatible = false;
							var operatorLabel = '';

							if ( '>' === condition.operator || 'greater_than' === condition.operator ) {
								incompatible = ( equalsValue <= currentValue );
								operatorLabel = 'greater than ' + currentValue;
							} else if ( '>=' === condition.operator || 'greater_than_equal' === condition.operator ) {
								incompatible = ( equalsValue < currentValue );
								operatorLabel = 'greater than or equal to ' + currentValue;
							} else if ( '<' === condition.operator || 'less_than' === condition.operator ) {
								incompatible = ( equalsValue >= currentValue );
								operatorLabel = 'less than ' + currentValue;
							} else if ( '<=' === condition.operator || 'less_than_equal' === condition.operator ) {
								incompatible = ( equalsValue > currentValue );
								operatorLabel = 'less than or equal to ' + currentValue;
							}

							if ( incompatible ) {
								return {
									type: 'range_incompatible_equals',
									message: condition.conditionType + ' cannot equal ' + equalsValue + ' while also being ' + operatorLabel,
									severity: 'error',
									field: 'value'
								};
							}
						}
					}
				}
			}

			if ( null !== minGte && null !== maxLte && minGte > maxLte ) {
				return {
					type: 'greater_less_equal_impossible',
					message: 'Cannot be >= ' + minGte + ' AND <= ' + maxLte,
					severity: 'error',
					field: 'value'
				};
			}

			return null;
		},

		/**
		 * Check for negative values on positive-only properties (Validation 9)
		 *
		 * @since 1.0.0
		 * @param {Object} condition Current condition
		 * @returns {Object|null} Issue object or null
		 */
		checkNegativeValueInvalid: function( condition ) {
			var positiveProperties = [ 'price', 'sale_price', 'current_price', 'regular_price', 'stock_quantity', 'weight', 'length', 'width', 'height', 'review_count', 'total_sales' ];
			var ratingProperties = [ 'average_rating', 'rating' ];

			if ( -1 === positiveProperties.indexOf( condition.conditionType ) && -1 === ratingProperties.indexOf( condition.conditionType ) ) {
				return null;
			}

			var value = parseFloat( condition.value ) || 0;
			var value2 = parseFloat( condition.value2 ) || 0;

			// Check positive-only properties
			if ( -1 !== positiveProperties.indexOf( condition.conditionType ) ) {
				var equalsLessOps = [ 'equals', 'less_than', '<', 'less_than_equal', '<=' ];
				if ( -1 !== equalsLessOps.indexOf( condition.operator ) && value < 0 ) {
					return {
						type: 'negative_value_invalid',
						message: condition.conditionType + ' cannot be negative',
						severity: 'error',
						field: 'value'
					};
				}

				var betweenOps = [ 'between', 'not_between' ];
				if ( -1 !== betweenOps.indexOf( condition.operator ) && ( value < 0 || value2 < 0 ) ) {
					return {
						type: 'negative_value_invalid',
						message: condition.conditionType + ' cannot be negative',
						severity: 'error',
						field: 'value'
					};
				}
			}

			// Check rating bounds (0-5)
			if ( -1 !== ratingProperties.indexOf( condition.conditionType ) ) {
				var greaterOps = [ 'equals', 'greater_than', '>', 'greater_than_equal', '>=' ];
				if ( -1 !== greaterOps.indexOf( condition.operator ) && value > 5 ) {
					return {
						type: 'rating_out_of_bounds',
						message: 'Rating must be between 0-5',
						severity: 'error',
						field: 'value'
					};
				}

				var lessOps = [ 'equals', 'less_than', '<', 'less_than_equal', '<=' ];
				if ( -1 !== lessOps.indexOf( condition.operator ) && value < 0 ) {
					return {
						type: 'rating_out_of_bounds',
						message: 'Rating must be between 0-5',
						severity: 'error',
						field: 'value'
					};
				}

				var ratingBetweenOps = [ 'between', 'not_between' ];
				if ( -1 !== ratingBetweenOps.indexOf( condition.operator ) ) {
					if ( value < 0 || value > 5 || value2 < 0 || value2 > 5 ) {
						return {
							type: 'rating_out_of_bounds',
							message: 'Rating must be between 0-5',
							severity: 'error',
							field: 'value'
						};
					}
				}
			}

			return null;
		},

		/**
		 * Check for sale price > regular price (Validation 10)
		 *
		 * @since 1.0.0
		 * @param {Object} condition Current condition
		 * @param {Array} otherConditions Other conditions
		 * @returns {Object|null} Issue object or null
		 */
		checkSalePriceGreaterThanRegular: function( condition, otherConditions ) {
			var saleMin = Number.MIN_VALUE;
			var regularMax = Number.MAX_VALUE;

			// Check if current condition is sale price with >= or >
			if ( 'sale_price' === condition.conditionType ) {
				if ( 'greater_than' === condition.operator || '>' === condition.operator ) {
					saleMin = Math.max( saleMin, parseFloat( condition.value ) + 0.01 );
				} else if ( 'greater_than_equal' === condition.operator || '>=' === condition.operator ) {
					saleMin = Math.max( saleMin, parseFloat( condition.value ) );
				}
			}

			// Check if current condition is regular price with <= or <
			var regularPriceTypes = [ 'price', 'regular_price' ];
			if ( -1 !== regularPriceTypes.indexOf( condition.conditionType ) ) {
				if ( 'less_than' === condition.operator || '<' === condition.operator ) {
					regularMax = Math.min( regularMax, parseFloat( condition.value ) - 0.01 );
				} else if ( 'less_than_equal' === condition.operator || '<=' === condition.operator ) {
					regularMax = Math.min( regularMax, parseFloat( condition.value ) );
				}
			}

			// Check other conditions
			for ( var i = 0; i < otherConditions.length; i++ ) {
				var other = otherConditions[i];

				if ( 'sale_price' === other.conditionType ) {
					if ( 'greater_than' === other.operator || '>' === other.operator ) {
						saleMin = Math.max( saleMin, parseFloat( other.value ) + 0.01 );
					} else if ( 'greater_than_equal' === other.operator || '>=' === other.operator ) {
						saleMin = Math.max( saleMin, parseFloat( other.value ) );
					}
				}

				if ( -1 !== regularPriceTypes.indexOf( other.conditionType ) ) {
					if ( 'less_than' === other.operator || '<' === other.operator ) {
						regularMax = Math.min( regularMax, parseFloat( other.value ) - 0.01 );
					} else if ( 'less_than_equal' === other.operator || '<=' === other.operator ) {
						regularMax = Math.min( regularMax, parseFloat( other.value ) );
					}
				}
			}

			if ( saleMin > regularMax && saleMin !== Number.MIN_VALUE && regularMax !== Number.MAX_VALUE ) {
				return {
					type: 'sale_greater_than_regular',
					message: 'Sale price (min ' + saleMin.toFixed( 2 ) + ') greater than regular price (max ' + regularMax.toFixed( 2 ) + ')',
					severity: 'warning',
					field: 'value'
				};
			}

			return null;
		},

		/**
		 * Check for text contains/not_contains contradiction (Validation 11)
		 *
		 * @since 1.0.0
		 * @param {Object} condition Current condition
		 * @param {Array} otherConditions Other conditions
		 * @returns {Object|null} Issue object or null
		 */
		checkTextContainsContradiction: function( condition, otherConditions ) {
			var textOps = [ 'contains', 'not_contains' ];
			if ( -1 === textOps.indexOf( condition.operator ) ) {
				return null;
			}

			var currentValue = ( condition.value || '' ).toLowerCase().trim();
			if ( '' === currentValue ) {
				return null;
			}

			var hasContains = ( 'contains' === condition.operator );
			var hasNotContains = ( 'not_contains' === condition.operator );

			for ( var i = 0; i < otherConditions.length; i++ ) {
				var other = otherConditions[i];

				if ( other.conditionType !== condition.conditionType ) {
					continue;
				}

				var otherValue = ( other.value || '' ).toLowerCase().trim();
				if ( otherValue !== currentValue ) {
					continue;
				}

				if ( 'contains' === other.operator ) {
					hasContains = true;
				} else if ( 'not_contains' === other.operator ) {
					hasNotContains = true;
				}
			}

			if ( hasContains && hasNotContains ) {
				return {
					type: 'text_contains_contradiction',
					message: 'Cannot both contain and not contain "' + condition.value + '"',
					severity: 'error',
					field: 'value'
				};
			}

			return null;
		},

		/**
		 * Check for date range contradictions (Validation 12)
		 *
		 * @since 1.0.0
		 * @param {Object} condition Current condition
		 * @param {Array} otherConditions Other conditions
		 * @returns {Object|null} Issue object or null
		 */
		checkDateRangeImpossible: function( condition, otherConditions ) {
			var dateProperties = [ 'date_created', 'date_modified' ];
			if ( -1 === dateProperties.indexOf( condition.conditionType ) ) {
				return null;
			}

			var earliest = null;
			var latest = null;

			// Parse current condition
			if ( 'greater_than' === condition.operator || '>' === condition.operator || 'greater_than_equal' === condition.operator || '>=' === condition.operator ) {
				var currentDate = new Date( condition.value );
				if ( ! isNaN( currentDate.getTime() ) ) {
					earliest = currentDate.getTime();
				}
			} else if ( 'less_than' === condition.operator || '<' === condition.operator || 'less_than_equal' === condition.operator || '<=' === condition.operator ) {
				var currentDateLess = new Date( condition.value );
				if ( ! isNaN( currentDateLess.getTime() ) ) {
					latest = currentDateLess.getTime();
				}
			}

			// Check other conditions
			for ( var i = 0; i < otherConditions.length; i++ ) {
				var other = otherConditions[i];

				if ( other.conditionType !== condition.conditionType ) {
					continue;
				}

				if ( 'greater_than' === other.operator || '>' === other.operator || 'greater_than_equal' === other.operator || '>=' === other.operator ) {
					var otherDate = new Date( other.value );
					if ( ! isNaN( otherDate.getTime() ) ) {
						earliest = ( null === earliest ) ? otherDate.getTime() : Math.max( earliest, otherDate.getTime() );
					}
				} else if ( 'less_than' === other.operator || '<' === other.operator || 'less_than_equal' === other.operator || '<=' === other.operator ) {
					var otherDateLess = new Date( other.value );
					if ( ! isNaN( otherDateLess.getTime() ) ) {
						latest = ( null === latest ) ? otherDateLess.getTime() : Math.min( latest, otherDateLess.getTime() );
					}
				}
			}

			if ( null !== earliest && null !== latest && earliest > latest ) {
				return {
					type: 'date_range_impossible',
					message: 'Date range is impossible (after must be before before)',
					severity: 'error',
					field: 'value'
				};
			}

			return null;
		},

		/**
		 * Check for text pattern conflicts (Validation 13)
		 *
		 * @since 1.0.0
		 * @param {Object} condition Current condition
		 * @param {Array} otherConditions Other conditions
		 * @returns {Object|null} Issue object or null
		 */
		checkTextPatternConflict: function( condition, otherConditions ) {
			var patternOps = [ 'equals', 'starts_with', 'ends_with' ];
			if ( -1 === patternOps.indexOf( condition.operator ) ) {
				return null;
			}

			var currentValue = condition.value || '';

			// If current is equals, check for starts_with conflicts
			if ( 'equals' === condition.operator ) {
				for ( var i = 0; i < otherConditions.length; i++ ) {
					var other = otherConditions[i];

					if ( other.conditionType !== condition.conditionType ) {
						continue;
					}

					if ( 'starts_with' === other.operator ) {
						var prefix = other.value || '';
						if ( 0 !== currentValue.indexOf( prefix ) ) {
							return {
								type: 'text_pattern_conflict',
								message: 'Cannot equal "' + currentValue + '" while starting with "' + prefix + '"',
								severity: 'error',
								field: 'value'
							};
						}
					}
				}
			}

			// If current is starts_with, check for equals conflicts
			if ( 'starts_with' === condition.operator ) {
				var currentPrefix = currentValue;

				for ( var j = 0; j < otherConditions.length; j++ ) {
					var otherEq = otherConditions[j];

					if ( otherEq.conditionType !== condition.conditionType ) {
						continue;
					}

					if ( 'equals' === otherEq.operator ) {
						var equalsValue = otherEq.value || '';
						if ( 0 !== equalsValue.indexOf( currentPrefix ) ) {
							return {
								type: 'text_pattern_conflict',
								message: 'Cannot start with "' + currentPrefix + '" while equaling "' + equalsValue + '"',
								severity: 'error',
								field: 'value'
							};
						}
					}
				}
			}

			return null;
		},

		/**
		 * Check for boolean property contradiction
		 * Scenario 14: featured = true AND featured = false
		 *
		 * @since 1.0.0
		 * @param {Object} condition Current condition
		 * @param {Array} otherConditions Other conditions to check
		 * @returns {Object|null} Issue object or null
		 */
		checkBooleanContradiction: function( condition, otherConditions ) {
			var booleanProperties = [ 'featured', 'on_sale', 'virtual', 'downloadable' ];

			if ( -1 === booleanProperties.indexOf( condition.conditionType ) || '=' !== condition.operator ) {
				return null;
			}

			var currentValue = parseInt( condition.value, 10 );

			for ( var i = 0; i < otherConditions.length; i++ ) {
				var other = otherConditions[ i ];
				if ( other.conditionType === condition.conditionType && '=' === other.operator ) {
					var otherValue = parseInt( other.value, 10 );
					if ( currentValue !== otherValue ) {
						return {
							message: condition.conditionType + ' cannot be both true AND false',
							severity: 'error',
							field: 'value'
						};
					}
				}
			}

			return null;
		},

		/**
		 * Check for IN/NOT_IN complete negation
		 * Scenario 15: type IN [simple, variable] AND type NOT_IN [simple, variable, grouped]
		 *
		 * @since 1.0.0
		 * @param {Object} condition Current condition
		 * @param {Array} otherConditions Other conditions to check
		 * @returns {Object|null} Issue object or null
		 */
		checkInNotInNegation: function( condition, otherConditions ) {
			if ( 'in' !== condition.operator && 'not_in' !== condition.operator ) {
				return null;
			}

			var currentValues = condition.value ? condition.value.split( ',' ).map( function( v ) {
				return v.trim();
			} ) : [];

			if ( 0 === currentValues.length ) {
				return null;
			}

			for ( var i = 0; i < otherConditions.length; i++ ) {
				var other = otherConditions[ i ];
				if ( other.conditionType !== condition.conditionType ) {
					continue;
				}

				var otherValues = other.value ? other.value.split( ',' ).map( function( v ) {
					return v.trim();
				} ) : [];

				// Check IN vs NOT_IN contradiction
				if ( 'in' === condition.operator && 'not_in' === other.operator ) {
					var allExcluded = true;
					for ( var j = 0; j < currentValues.length; j++ ) {
						if ( -1 === otherValues.indexOf( currentValues[ j ] ) ) {
							allExcluded = false;
							break;
						}
					}
					if ( allExcluded ) {
						return {
							message: 'All IN values are excluded by NOT_IN',
							severity: 'error',
							field: 'value'
						};
					}
				} else if ( 'not_in' === condition.operator && 'in' === other.operator ) {
					var allExcluded2 = true;
					for ( var k = 0; k < otherValues.length; k++ ) {
						if ( -1 === currentValues.indexOf( otherValues[ k ] ) ) {
							allExcluded2 = false;
							break;
						}
					}
					if ( allExcluded2 ) {
						return {
							message: 'All IN values are excluded by NOT_IN',
							severity: 'error',
							field: 'value'
						};
					}
				}
			}

			return null;
		},

		/**
		 * Check for select option exhaustion
		 * Scenario 16: Excluding ALL enum options
		 *
		 * @since 1.0.0
		 * @param {Object} condition Current condition
		 * @param {Array} otherConditions Other conditions to check
		 * @returns {Object|null} Issue object or null
		 */
		checkSelectExhaustion: function( condition, otherConditions ) {
			var enumDefinitions = {
				product_type: [ 'simple', 'variable', 'grouped', 'external' ],
				tax_status: [ 'taxable', 'shipping', 'none' ],
				stock_status: [ 'instock', 'outofstock', 'onbackorder' ]
			};

			if ( ! enumDefinitions[ condition.conditionType ] ) {
				return null;
			}

			if ( 'not_equals' !== condition.operator && 'not_in' !== condition.operator ) {
				return null;
			}

			var allOptions = enumDefinitions[ condition.conditionType ];
			var excluded = [];

			// Add current condition's excluded values
			if ( 'not_equals' === condition.operator && condition.value ) {
				excluded.push( condition.value );
			} else if ( 'not_in' === condition.operator && condition.value ) {
				excluded = condition.value.split( ',' ).map( function( v ) {
					return v.trim();
				} );
			}

			// Add other conditions' excluded values
			for ( var i = 0; i < otherConditions.length; i++ ) {
				var other = otherConditions[ i ];
				if ( other.conditionType !== condition.conditionType ) {
					continue;
				}

				if ( 'not_equals' === other.operator && other.value ) {
					excluded.push( other.value );
				} else if ( 'not_in' === other.operator && other.value ) {
					var otherExcluded = other.value.split( ',' ).map( function( v ) {
						return v.trim();
					} );
					excluded = excluded.concat( otherExcluded );
				}
			}

			// Check if all options are excluded
			var uniqueExcluded = [];
			for ( var j = 0; j < excluded.length; j++ ) {
				if ( -1 === uniqueExcluded.indexOf( excluded[ j ] ) ) {
					uniqueExcluded.push( excluded[ j ] );
				}
			}

			var remaining = allOptions.filter( function( opt ) {
				return -1 === uniqueExcluded.indexOf( opt );
			} );

			if ( 0 === remaining.length ) {
				return {
					message: 'All possible ' + condition.conditionType + ' values are excluded',
					severity: 'error',
					field: 'value'
				};
			}

			return null;
		},

		/**
		 * Check for NOT_BETWEEN overlapping coverage
		 * Scenario 17: Multiple NOT_BETWEEN that overlap
		 *
		 * @since 1.0.0
		 * @param {Object} condition Current condition
		 * @param {Array} otherConditions Other conditions to check
		 * @returns {Object|null} Issue object or null
		 */
		checkNotBetweenOverlap: function( condition, otherConditions ) {
			if ( 'not_between' !== condition.operator || ! this.isNumericProperty( condition.conditionType ) ) {
				return null;
			}

			var min1 = parseFloat( condition.value ) || 0;
			var max1 = parseFloat( condition.value2 ) || 0;
			if ( min1 > max1 ) {
				var temp = min1;
				min1 = max1;
				max1 = temp;
			}

			for ( var i = 0; i < otherConditions.length; i++ ) {
				var other = otherConditions[ i ];
				if ( other.conditionType !== condition.conditionType || 'not_between' !== other.operator ) {
					continue;
				}

				var min2 = parseFloat( other.value ) || 0;
				var max2 = parseFloat( other.value2 ) || 0;
				if ( min2 > max2 ) {
					var temp2 = min2;
					min2 = max2;
					max2 = temp2;
				}

				// Check if ranges overlap
				if ( max1 >= min2 && min1 <= max2 ) {
					return {
						message: 'Overlapping NOT_BETWEEN ranges may exclude all values',
						severity: 'warning',
						field: 'value'
					};
				}
			}

			return null;
		},

		/**
		 * Check for date created after modified violation
		 * Scenario 18: date_created > X AND date_modified < Y where X > Y
		 *
		 * @since 1.0.0
		 * @param {Object} condition Current condition
		 * @param {Array} otherConditions Other conditions to check
		 * @returns {Object|null} Issue object or null
		 */
		checkDateTemporalViolation: function( condition, otherConditions ) {
			var createdMin = null;
			var modifiedMax = null;

			// Check current condition
			if ( 'date_created' === condition.conditionType && ( 'greater_than' === condition.operator || 'greater_than_equal' === condition.operator ) ) {
				createdMin = new Date( condition.value );
			} else if ( 'date_modified' === condition.conditionType && ( 'less_than' === condition.operator || 'less_than_equal' === condition.operator ) ) {
				modifiedMax = new Date( condition.value );
			}

			// Check other conditions
			for ( var i = 0; i < otherConditions.length; i++ ) {
				var other = otherConditions[ i ];
				if ( 'date_created' === other.conditionType && ( 'greater_than' === other.operator || 'greater_than_equal' === other.operator ) ) {
					var otherCreated = new Date( other.value );
					if ( ! createdMin || otherCreated > createdMin ) {
						createdMin = otherCreated;
					}
				} else if ( 'date_modified' === other.conditionType && ( 'less_than' === other.operator || 'less_than_equal' === other.operator ) ) {
					var otherModified = new Date( other.value );
					if ( ! modifiedMax || otherModified < modifiedMax ) {
						modifiedMax = otherModified;
					}
				}
			}

			if ( createdMin && modifiedMax && createdMin > modifiedMax ) {
				return {
					message: 'Created date cannot be after modified date',
					severity: 'error',
					field: 'value'
				};
			}

			return null;
		},

		/**
		 * Check for virtual product with physical properties
		 * Scenario 19: virtual = true AND weight > 0
		 *
		 * @since 1.0.0
		 * @param {Object} condition Current condition
		 * @param {Array} otherConditions Other conditions to check
		 * @returns {Object|null} Issue object or null
		 */
		checkVirtualPhysicalConflict: function( condition, otherConditions ) {
			var virtualRequired = false;
			var physicalRequired = [];
			var physicalProperties = [ 'weight', 'length', 'width', 'height' ];

			// Check current condition
			if ( 'virtual' === condition.conditionType && 'equals' === condition.operator && '1' === condition.value ) {
				virtualRequired = true;
			} else if ( -1 !== physicalProperties.indexOf( condition.conditionType ) ) {
				if ( ( 'greater_than' === condition.operator || 'greater_than_equal' === condition.operator ) && parseFloat( condition.value ) > 0 ) {
					physicalRequired.push( condition.conditionType );
				} else if ( 'between' === condition.operator && parseFloat( condition.value ) > 0 ) {
					physicalRequired.push( condition.conditionType );
				}
			}

			// Check other conditions
			for ( var i = 0; i < otherConditions.length; i++ ) {
				var other = otherConditions[ i ];
				if ( 'virtual' === other.conditionType && 'equals' === other.operator && '1' === other.value ) {
					virtualRequired = true;
				} else if ( -1 !== physicalProperties.indexOf( other.conditionType ) ) {
					if ( ( 'greater_than' === other.operator || 'greater_than_equal' === other.operator ) && parseFloat( other.value ) > 0 ) {
						if ( -1 === physicalRequired.indexOf( other.conditionType ) ) {
							physicalRequired.push( other.conditionType );
						}
					} else if ( 'between' === other.operator && parseFloat( other.value ) > 0 ) {
						if ( -1 === physicalRequired.indexOf( other.conditionType ) ) {
							physicalRequired.push( other.conditionType );
						}
					}
				}
			}

			if ( virtualRequired && physicalRequired.length > 0 ) {
				return {
					message: 'Virtual products cannot have physical properties [' + physicalRequired.join( ', ' ) + ']',
					severity: 'warning',
					field: 'value'
				};
			}

			return null;
		},

		/**
		 * Check for EQUALS with NOT_IN contradiction
		 * Scenario 20: property = 'simple' AND property NOT_IN ['simple', 'variable']
		 *
		 * @since 1.0.0
		 * @param {Object} condition Current condition
		 * @param {Array} otherConditions Other conditions to check
		 * @returns {Object|null} Issue object or null
		 */
		checkEqualsNotInContradiction: function( condition, otherConditions ) {
			if ( 'equals' !== condition.operator || ! condition.value ) {
				return null;
			}

			var equalsValue = condition.value;

			for ( var i = 0; i < otherConditions.length; i++ ) {
				var other = otherConditions[ i ];
				if ( other.conditionType !== condition.conditionType || 'not_in' !== other.operator ) {
					continue;
				}

				var notInValues = other.value ? other.value.split( ',' ).map( function( v ) {
					return v.trim();
				} ) : [];

				if ( -1 !== notInValues.indexOf( equalsValue ) ) {
					return {
						message: 'Value "' + equalsValue + '" cannot equal while also excluded by NOT_IN',
						severity: 'error',
						field: 'value'
					};
				}
			}

			return null;
		},

		/**
		 * Check for rating bounds violation
		 * Scenario 21: rating > 5 or rating BETWEEN 6-10
		 *
		 * @since 1.0.0
		 * @param {Object} condition Current condition
		 * @returns {Object|null} Issue object or null
		 */
		checkRatingBoundsViolation: function( condition ) {
			var ratingProperties = [ 'average_rating', 'rating' ];
			if ( -1 === ratingProperties.indexOf( condition.conditionType ) ) {
				return null;
			}

			var value1 = parseFloat( condition.value ) || 0;
			var value2 = parseFloat( condition.value2 ) || 0;

			// Check greater than operations
			if ( ( 'greater_than' === condition.operator || 'greater_than_equal' === condition.operator || 'equals' === condition.operator ) && value1 > 5 ) {
				return {
					message: 'Rating value ' + value1 + ' exceeds maximum (ratings are 0-5)',
					severity: 'error',
					field: 'value'
				};
			}

			// Check less than operations
			if ( ( 'less_than' === condition.operator || 'less_than_equal' === condition.operator ) && value1 < 0 ) {
				return {
					message: 'Rating value ' + value1 + ' is below minimum (ratings are 0-5)',
					severity: 'error',
					field: 'value'
				};
			}

			// Check BETWEEN operations
			if ( ( 'between' === condition.operator || 'not_between' === condition.operator ) ) {
				var minVal = Math.min( value1, value2 );
				var maxVal = Math.max( value1, value2 );
				if ( minVal > 5 || maxVal > 5 ) {
					return {
						message: 'Rating range exceeds maximum (ratings are 0-5)',
						severity: 'error',
						field: 'value'
					};
				}
			}

			return null;
		},

		/**
		 * Check for stock status vs quantity logic
		 * Scenario 22: stock_status = 'instock' AND stock_quantity <= 0
		 *
		 * @since 1.0.0
		 * @param {Object} condition Current condition
		 * @param {Array} otherConditions Other conditions to check
		 * @returns {Object|null} Issue object or null
		 */
		checkStockStatusQuantityConflict: function( condition, otherConditions ) {
			var stockStatus = null;
			var stockQuantityChecks = [];

			// Collect stock status and quantity conditions
			if ( 'stock_status' === condition.conditionType && 'equals' === condition.operator ) {
				stockStatus = condition.value;
			} else if ( 'stock_quantity' === condition.conditionType ) {
				stockQuantityChecks.push( {
					operator: condition.operator,
					value: parseFloat( condition.value ) || 0
				} );
			}

			for ( var i = 0; i < otherConditions.length; i++ ) {
				var other = otherConditions[ i ];
				if ( 'stock_status' === other.conditionType && 'equals' === other.operator ) {
					stockStatus = other.value;
				} else if ( 'stock_quantity' === other.conditionType ) {
					stockQuantityChecks.push( {
						operator: other.operator,
						value: parseFloat( other.value ) || 0
					} );
				}
			}

			// Check for conflicts
			if ( 'instock' === stockStatus ) {
				for ( var j = 0; j < stockQuantityChecks.length; j++ ) {
					var check = stockQuantityChecks[ j ];
					if ( ( 'less_than_equal' === check.operator && check.value <= 0 ) ||
						( 'less_than' === check.operator && check.value <= 1 ) ||
						( 'equals' === check.operator && check.value <= 0 ) ) {
						return {
							message: 'Cannot be "in stock" with zero or negative quantity',
							severity: 'error',
							field: 'value'
						};
					}
				}
			} else if ( 'outofstock' === stockStatus ) {
				for ( var k = 0; k < stockQuantityChecks.length; k++ ) {
					var check2 = stockQuantityChecks[ k ];
					if ( ( 'greater_than' === check2.operator && check2.value >= 0 ) ||
						( 'greater_than_equal' === check2.operator && check2.value > 0 ) ) {
						return {
							message: 'Cannot be "out of stock" with positive quantity',
							severity: 'error',
							field: 'value'
						};
					}
				}
			}

			return null;
		},

		/**
		 * Check for text EQUALS vs text operators
		 * Scenario 23: product_name = 'Laptop' AND product_name CONTAINS 'Phone'
		 *
		 * @since 1.0.0
		 * @param {Object} condition Current condition
		 * @param {Array} otherConditions Other conditions to check
		 * @returns {Object|null} Issue object or null
		 */
		checkTextEqualsOperatorConflict: function( condition, otherConditions ) {
			var equalsValue = null;
			var textChecks = {
				contains: [],
				starts_with: [],
				ends_with: []
			};

			// Collect equals and text operator conditions
			if ( 'equals' === condition.operator && condition.value ) {
				equalsValue = condition.value.toLowerCase();
			} else if ( 'contains' === condition.operator && condition.value ) {
				textChecks.contains.push( condition.value.toLowerCase() );
			} else if ( 'starts_with' === condition.operator && condition.value ) {
				textChecks.starts_with.push( condition.value.toLowerCase() );
			} else if ( 'ends_with' === condition.operator && condition.value ) {
				textChecks.ends_with.push( condition.value.toLowerCase() );
			}

			for ( var i = 0; i < otherConditions.length; i++ ) {
				var other = otherConditions[ i ];
				if ( other.conditionType !== condition.conditionType ) {
					continue;
				}

				if ( 'equals' === other.operator && other.value ) {
					equalsValue = other.value.toLowerCase();
				} else if ( 'contains' === other.operator && other.value ) {
					textChecks.contains.push( other.value.toLowerCase() );
				} else if ( 'starts_with' === other.operator && other.value ) {
					textChecks.starts_with.push( other.value.toLowerCase() );
				} else if ( 'ends_with' === other.operator && other.value ) {
					textChecks.ends_with.push( other.value.toLowerCase() );
				}
			}

			if ( ! equalsValue ) {
				return null;
			}

			// Check CONTAINS conflicts
			for ( var j = 0; j < textChecks.contains.length; j++ ) {
				if ( -1 === equalsValue.indexOf( textChecks.contains[ j ] ) ) {
					return {
						message: 'Equals "' + equalsValue + '" does not contain "' + textChecks.contains[ j ] + '"',
						severity: 'error',
						field: 'value'
					};
				}
			}

			// Check STARTS_WITH conflicts
			for ( var k = 0; k < textChecks.starts_with.length; k++ ) {
				if ( 0 !== equalsValue.indexOf( textChecks.starts_with[ k ] ) ) {
					return {
						message: 'Equals "' + equalsValue + '" does not start with "' + textChecks.starts_with[ k ] + '"',
						severity: 'error',
						field: 'value'
					};
				}
			}

			// Check ENDS_WITH conflicts
			for ( var l = 0; l < textChecks.ends_with.length; l++ ) {
				var suffix = textChecks.ends_with[ l ];
				if ( equalsValue.substr( -suffix.length ) !== suffix ) {
					return {
						message: 'Equals "' + equalsValue + '" does not end with "' + suffix + '"',
						severity: 'error',
						field: 'value'
					};
				}
			}

			return null;
		},

		/**
		 * Check for EQUALS with NOT_BETWEEN excluding value
		 * Scenario 24: price = 50 AND price NOT_BETWEEN 40-60
		 *
		 * @since 1.0.0
		 * @param {Object} condition Current condition
		 * @param {Array} otherConditions Other conditions to check
		 * @returns {Object|null} Issue object or null
		 */
		checkEqualsNotBetweenConflict: function( condition, otherConditions ) {
			if ( ! this.isNumericProperty( condition.conditionType ) ) {
				return null;
			}

			var equalsValue = null;
			var notBetweenRanges = [];

			// Collect equals and NOT_BETWEEN conditions
			if ( 'equals' === condition.operator ) {
				equalsValue = parseFloat( condition.value ) || 0;
			} else if ( 'not_between' === condition.operator ) {
				var min1 = parseFloat( condition.value ) || 0;
				var max1 = parseFloat( condition.value2 ) || 0;
				notBetweenRanges.push( {
					min: Math.min( min1, max1 ),
					max: Math.max( min1, max1 )
				} );
			}

			for ( var i = 0; i < otherConditions.length; i++ ) {
				var other = otherConditions[ i ];
				if ( other.conditionType !== condition.conditionType ) {
					continue;
				}

				if ( 'equals' === other.operator ) {
					equalsValue = parseFloat( other.value ) || 0;
				} else if ( 'not_between' === other.operator ) {
					var min2 = parseFloat( other.value ) || 0;
					var max2 = parseFloat( other.value2 ) || 0;
					notBetweenRanges.push( {
						min: Math.min( min2, max2 ),
						max: Math.max( min2, max2 )
					} );
				}
			}

			if ( null === equalsValue || 0 === notBetweenRanges.length ) {
				return null;
			}

			// Check if equals value is within any NOT_BETWEEN range
			for ( var j = 0; j < notBetweenRanges.length; j++ ) {
				var range = notBetweenRanges[ j ];
				if ( equalsValue >= range.min && equalsValue <= range.max ) {
					return {
						message: 'Value ' + equalsValue + ' is excluded by NOT_BETWEEN ' + range.min + '-' + range.max,
						severity: 'error',
						field: 'value'
					};
				}
			}

			return null;
		},

		/**
		 * Check for low stock threshold logic
		 * Scenario 25: low_stock_amount > stock_quantity
		 *
		 * @since 1.0.0
		 * @param {Object} condition Current condition
		 * @param {Array} otherConditions Other conditions to check
		 * @returns {Object|null} Issue object or null
		 */
		checkLowStockThresholdConflict: function( condition, otherConditions ) {
			var lowStockAmount = null;
			var stockQuantityMax = null;

			// Collect low_stock_amount and stock_quantity conditions
			if ( 'low_stock_amount' === condition.conditionType && 'equals' === condition.operator ) {
				lowStockAmount = parseFloat( condition.value ) || 0;
			} else if ( 'stock_quantity' === condition.conditionType ) {
				if ( 'equals' === condition.operator || 'less_than' === condition.operator || 'less_than_equal' === condition.operator ) {
					stockQuantityMax = parseFloat( condition.value ) || 0;
				}
			}

			for ( var i = 0; i < otherConditions.length; i++ ) {
				var other = otherConditions[ i ];
				if ( 'low_stock_amount' === other.conditionType && 'equals' === other.operator ) {
					lowStockAmount = parseFloat( other.value ) || 0;
				} else if ( 'stock_quantity' === other.conditionType ) {
					if ( 'equals' === other.operator || 'less_than' === other.operator || 'less_than_equal' === other.operator ) {
						var otherMax = parseFloat( other.value ) || 0;
						if ( null === stockQuantityMax || otherMax < stockQuantityMax ) {
							stockQuantityMax = otherMax;
						}
					}
				}
			}

			if ( null !== lowStockAmount && null !== stockQuantityMax && lowStockAmount > stockQuantityMax ) {
				return {
					message: 'Low stock threshold (' + lowStockAmount + ') cannot exceed max stock quantity (' + stockQuantityMax + ')',
					severity: 'warning',
					field: 'value'
				};
			}

			return null;
		},

		// ========================================
		// UI FEEDBACK (Uses Central ValidationError)
		// ========================================

		/**
		 * Show validation errors on row
		 *
		 * @since 1.0.0
		 * @param {jQuery} $row Condition row
		 * @param {Array} issues Array of issue objects
		 */
		showValidationErrors: function( $row, issues ) {
			// Find the wrapper (parent of the row)
			var $wrapper = $row.closest( '.wsscd-condition-wrapper' );
			if ( ! $wrapper.length ) {
				$wrapper = $row.parent(); // Fallback
			}

			// Get or create error container (sibling of row, child of wrapper)
			var $errorContainer = $wrapper.find( '.wsscd-condition-error-container' );

			if ( ! $errorContainer.length ) {
				// Fallback: create container if it doesn't exist
				$errorContainer = $( '<div class="wsscd-condition-error-container"></div>' );
				$wrapper.append( $errorContainer );
			}

			// Clear any existing errors
			$errorContainer.empty();

			// Add condition-specific styling
			$row.addClass( 'wsscd-condition-error' );

			// Build error message HTML
			var warningIcon = WSSCD.IconHelper ? WSSCD.IconHelper.warning( { size: 16 } ) : '<span class="wsscd-icon wsscd-icon-warning"></span>';
			var errorHtml = '<div class="wsscd-condition-validation-error">';
			errorHtml += warningIcon;
			errorHtml += '<div class="wsscd-condition-error-messages">';

			// Show all issues
			for ( var i = 0; i < issues.length; i++ ) {
				errorHtml += '<p class="wsscd-condition-error-message">' + this._escapeHtml( issues[i].message ) + '</p>';
			}

			errorHtml += '</div>';
			errorHtml += '</div>';

			// Append to container
			$errorContainer.html( errorHtml );

			// Make container visible
			$errorContainer.show();
		},

		/**
		 * Clear validation errors from row
		 *
		 * @since 1.0.0
		 * @param {jQuery} $row Condition row
		 */
		clearValidationErrors: function( $row ) {
			// Find the wrapper (parent of the row)
			var $wrapper = $row.closest( '.wsscd-condition-wrapper' );
			if ( ! $wrapper.length ) {
				$wrapper = $row.parent(); // Fallback
			}

			// Clear the error container (sibling of row, child of wrapper)
			var $errorContainer = $wrapper.find( '.wsscd-condition-error-container' );
			$errorContainer.empty().hide();

			// Remove condition-specific styling
			$row.removeClass( 'wsscd-condition-error' );
		},

		/**
		 * Show inline warning banner
		 *
		 * @since 1.0.0
		 * @private
		 * @param {jQuery} $row Condition row
		 * @param {Array} issues Array of issue objects
		 */
		_showInlineWarning: function( $row, issues ) {
			var $existing = $row.find( '.wsscd-condition-inline-warning' );
			if ( $existing.length ) {
				$existing.remove();
			}

			var warningIcon = WSSCD.IconHelper ? WSSCD.IconHelper.warning( { size: 16 } ) : '<span class="wsscd-icon wsscd-icon-warning"></span>';
			var $warning = $( '<div class="wsscd-condition-inline-warning"></div>' );
			$warning.html( warningIcon );

			var $message = $( '<span class="wsscd-warning-message"></span>' );
			$message.text( issues.length + ' issue' + ( issues.length > 1 ? 's' : '' ) + ' detected' );
			$warning.append( $message );

			$row.find( '.wsscd-condition-fields' ).after( $warning );
		},

		/**
		 * Get field element from issue
		 *
		 * @since 1.0.0
		 * @private
		 * @param {jQuery} $row Condition row
		 * @param {Object} issue Issue object
		 * @returns {jQuery} Field element
		 */
		_getFieldFromIssue: function( $row, issue ) {
			var fieldClass = '.wsscd-condition-' + issue.field;
			var $field = $row.find( fieldClass );

			if ( ! $field.length ) {
				$field = $row.find( '.wsscd-condition-operator' );
			}

			return $field;
		},

		/**
		 * Escape HTML to prevent XSS
		 *
		 * @since 1.0.0
		 * @private
		 * @param {string} text Text to escape
		 * @returns {string} Escaped text
		 */
		_escapeHtml: function( text ) {
			var div = document.createElement( 'div' );
			div.textContent = text;
			return div.innerHTML;
		},

		// ========================================
		// HELPER METHODS
		// ========================================

		/**
		 * Collect all conditions from DOM
		 *
		 * @since 1.0.0
		 * @returns {Array} Array of condition objects
		 */
		collectAllConditions: function() {
			var conditions = [];
			var self = this;

			this.$container.find( '.wsscd-condition-row' ).each( function() {
				var condition = self.getConditionFromRow( $( this ) );
				if ( condition.conditionType && condition.operator ) {
					conditions.push( condition );
				}
			} );

			return conditions;
		},

		/**
		 * Get condition data from row
		 *
		 * @since 1.0.0
		 * @param {jQuery} $row Condition row element
		 * @returns {Object} Condition object
		 */
		getConditionFromRow: function( $row ) {
			return {
				conditionType: $row.find( '.wsscd-condition-type' ).val() || '',
				operator: $row.find( '.wsscd-condition-operator' ).val() || '',
				value: $row.find( '.wsscd-condition-value' ).first().val() || '',
				value2: $row.find( '.wsscd-condition-value-between' ).val() || '',
				mode: $row.find( '.wsscd-condition-mode' ).val() || 'include'
			};
		},

		/**
		 * Check if property is numeric type
		 *
		 * @since 1.0.0
		 * @param {string} propertyType Property type name
		 * @returns {boolean} True if numeric
		 */
		isNumericProperty: function( propertyType ) {
			return -1 !== this.numericProperties.indexOf( propertyType );
		},

		/**
		 * Check if property type is a select field
		 *
		 * @since 1.0.0
		 * @param {string} propertyType Property type
		 * @returns {boolean} True if select property
		 */
		isSelectProperty: function( propertyType ) {
			return -1 !== this.selectProperties.indexOf( propertyType );
		},

		/**
		 * Check if property type is boolean
		 *
		 * @since 1.0.0
		 * @param {string} propertyType Property type
		 * @returns {boolean} True if boolean property
		 */
		isBooleanProperty: function( propertyType ) {
			return -1 !== this.booleanProperties.indexOf( propertyType );
		},

		/**
		 * Destroy validator and cleanup
		 *
		 * @since 1.0.0
		 */
		destroy: function() {
			if ( this.$container ) {
				this.$container.off( '.wsscd-validator' );
			}
			this.$container = null;
			this.conditionTypes = {};
		}
	};

} )( jQuery );
