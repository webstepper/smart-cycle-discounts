/**
 * Discounts Conditions
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/steps/discounts/discounts-conditions.js
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

( function( $ ) {
	'use strict';

	window.SCD = window.SCD || {};
	SCD.Modules = SCD.Modules || {};
	SCD.Modules.Discounts = SCD.Modules.Discounts || {};

	/**
	 * Discount Conditions Manager
	 *
	 * @param state
	 * @class SCD.Modules.Discounts.Conditions
	 */
	SCD.Modules.Discounts.Conditions = function( state ) {
		this.state = state;
		this.conditionTypes = {};
		this.maxConditions = 10;
		this.operators = {
			equals: { label: 'is equal to', symbol: '=' },
			not_equals: { label: 'is not equal to', symbol: '≠' },
			contains: { label: 'contains', symbol: '∋' },
			not_contains: { label: 'does not contain', symbol: '∌' },
			greater_than: { label: 'is greater than', symbol: '>' },
			less_than: { label: 'is less than', symbol: '<' },
			greater_equal: { label: 'is greater than or equal to', symbol: '≥' },
			less_equal: { label: 'is less than or equal to', symbol: '≤' },
			in_list: { label: 'is in', symbol: '∈' },
			not_in_list: { label: 'is not in', symbol: '∉' }
		};
	};

	SCD.Modules.Discounts.Conditions.prototype = {
		/**
		 * Initialize conditions module
		 */
		init: function() {
			this.registerBuiltInConditionTypes();
			this.setupEventHandlers();
			this.initializeConditions();
		},

		/**
		 * Register built-in condition types
		 */
		registerBuiltInConditionTypes: function() {
			// Product conditions
			this.registerConditionType( 'product', {
				label: 'Product',
				icon: 'box',
				operators: [ 'in_list', 'not_in_list' ],
				inputType: 'product_search',
				multiple: true,
				placeholder: 'Select products...',
				validate: function( value ) {
					return Array.isArray( value ) && 0 < value.length;
				}
			} );

			// Category conditions
			this.registerConditionType( 'category', {
				label: 'Product Category',
				icon: 'folder',
				operators: [ 'in_list', 'not_in_list' ],
				inputType: 'category_select',
				multiple: true,
				placeholder: 'Select categories...',
				validate: function( value ) {
					return Array.isArray( value ) && 0 < value.length;
				}
			} );

			// User role conditions
			this.registerConditionType( 'user_role', {
				label: 'User Role',
				icon: 'user',
				operators: [ 'in_list', 'not_in_list' ],
				inputType: 'role_select',
				multiple: true,
				placeholder: 'Select roles...',
				options: this.getUserRoles(),
				validate: function( value ) {
					return Array.isArray( value ) && 0 < value.length;
				}
			} );

			// Cart total conditions
			this.registerConditionType( 'cart_total', {
				label: 'Cart Total',
				icon: 'shopping-cart',
				operators: [ 'greater_than', 'less_than', 'greater_equal', 'less_equal' ],
				inputType: 'number',
				placeholder: 'Enter amount...',
				step: 0.01,
				min: 0,
				validate: function( value ) {
					return !isNaN( value ) && 0 <= value;
				}
			} );

			// Cart item count conditions
			this.registerConditionType( 'cart_items', {
				label: 'Cart Item Count',
				icon: 'list',
				operators: [ 'equals', 'not_equals', 'greater_than', 'less_than' ],
				inputType: 'number',
				placeholder: 'Enter quantity...',
				step: 1,
				min: 1,
				validate: function( value ) {
					return !isNaN( value ) && 0 < value;
				}
			} );

			// Date range conditions
			this.registerConditionType( 'date_range', {
				label: 'Date Range',
				icon: 'calendar',
				operators: [ 'between' ],
				inputType: 'date_range',
				placeholder: 'Select date range...',
				validate: function( value ) {
					return value && value.start && value.end;
				}
			} );

			// Coupon code conditions
			this.registerConditionType( 'coupon_code', {
				label: 'Coupon Code',
				icon: 'ticket',
				operators: [ 'equals', 'not_equals', 'contains' ],
				inputType: 'text',
				placeholder: 'Enter coupon code...',
				validate: function( value ) {
					return value && 0 < value.trim().length;
				}
			} );

			// Customer conditions
			this.registerConditionType( 'customer_type', {
				label: 'Customer Type',
				icon: 'users',
				operators: [ 'equals' ],
				inputType: 'select',
				options: [
					{ value: 'new', label: 'New Customer' },
					{ value: 'returning', label: 'Returning Customer' },
					{ value: 'vip', label: 'VIP Customer' }
				],
				validate: function( value ) {
					return -1 !== [ 'new', 'returning', 'vip' ].indexOf( value );
				}
			} );
		},

		/**
		 * Register a condition type
		 * @param type
		 * @param config
		 */
		registerConditionType: function( type, config ) {
			this.conditionTypes[type] = $.extend( {
				type: type
			}, config );
		},

		/**
		 * Setup event handlers
		 */
		setupEventHandlers: function() {
			var self = this;

			$( document ).on( 'click.conditions', '.scd-add-condition', function( e ) {
				e.preventDefault();
				self.addCondition();
			} );

			$( document ).on( 'click.conditions', '.scd-remove-condition', function( e ) {
				e.preventDefault();
				var id = $( this ).data( 'condition-id' );
				self.removeCondition( id );
			} );

			// Condition type change
			$( document ).on( 'change.conditions', '.scd-condition-type', function() {
				var $row = $( this ).closest( '.scd-condition-row' );
				var id = $row.data( 'condition-id' );
				var type = $( this ).val();
				self.handleTypeChange( id, type );
			} );

			// Operator change
			$( document ).on( 'change.conditions', '.scd-condition-operator', function() {
				var $row = $( this ).closest( '.scd-condition-row' );
				var id = $row.data( 'condition-id' );
				var operator = $( this ).val();
				self.updateCondition( id, { operator: operator } );
			} );

			// Value change
			$( document ).on( 'change.conditions input.conditions', '.scd-condition-value', function() {
				var $row = $( this ).closest( '.scd-condition-row' );
				var id = $row.data( 'condition-id' );
				var value = self.getValueFromInput( $( this ) );
				self.updateCondition( id, { value: value } );
			} );

			// Enable/disable toggle
			$( document ).on( 'change.conditions', '.scd-condition-enabled', function() {
				var $row = $( this ).closest( '.scd-condition-row' );
				var id = $row.data( 'condition-id' );
				var enabled = $( this ).is( ':checked' );
				self.updateCondition( id, { enabled: enabled } );
				$row.toggleClass( 'disabled', !enabled );
			} );

			// Logic operator change ( AND/OR )
			$( document ).on( 'change.conditions', '[name="condition_logic"]', function() {
				self.state.setState( { conditionLogic: $( this ).val() } );
			} );
		},

		/**
		 * Initialize conditions
		 */
		initializeConditions: function() {
			var conditions = this.state.getState( 'conditions' );

			if ( !conditions || !Array.isArray( conditions ) ) {
				this.state.setState( {
					conditions: [],
					conditionLogic: 'all' // 'all' ( AND ) or 'any' ( OR )
				} );
			}

			this.renderConditions();
		},

		/**
		 * Render all conditions
		 */
		renderConditions: function() {
			var conditions = this.state.getState( 'conditions' ) || [];
			var $container = $( '.scd-conditions-container' );

			if ( !$container.length ) {return;}

			var html = '';

			if ( 0 === conditions.length ) {
				html = '<p class="scd-no-conditions">No conditions added. Discount will apply to all eligible items.</p>';
			} else {
				html = '<div class="scd-conditions-list">';
				conditions.forEach( function( condition ) {
					html += this.renderConditionRow( condition );
				}.bind( this ) );
				html += '</div>';
			}

			$container.html( html );

			var $addButton = $( '.scd-add-condition' );
			if ( conditions.length >= this.maxConditions ) {
				$addButton.prop( 'disabled', true ).text( 'Maximum conditions reached' );
			} else {
				$addButton.prop( 'disabled', false ).text( 'Add Condition' );
			}

			var logic = this.state.getState( 'conditionLogic' ) || 'all';
			$( '[name="condition_logic"][value="' + logic + '"]' ).prop( 'checked', true );

			// Show/hide logic selector based on condition count
			$( '.scd-condition-logic' ).toggle( 1 < conditions.length );
		},

		/**
		 * Render a single condition row
		 * @param condition
		 */
		renderConditionRow: function( condition ) {
			var typeConfig = this.conditionTypes[condition.type];
			if ( !typeConfig ) {
				return ''; // Unknown type
			}

			var operators = this.getOperatorsForType( condition.type );
			var self = this;

			// Build type options
			var typeOptions = '';
			for ( var typeName in this.conditionTypes ) {
				if ( Object.prototype.hasOwnProperty.call( this.conditionTypes, typeName ) ) {
					var config = this.conditionTypes[typeName];
					typeOptions += '<option value="' + typeName + '" ' + ( condition.type === typeName ? 'selected' : '' ) + '>' +
                                   config.label + '</option>';
				}
			}

			// Build operator options
			var operatorOptions = '';
			operators.forEach( function( op ) {
				operatorOptions += '<option value="' + op + '" ' + ( condition.operator === op ? 'selected' : '' ) + '>' +
                                  self.operators[op].label + '</option>';
			} );

			return '<div class="scd-condition-row ' + ( false === condition.enabled ? 'disabled' : '' ) + '" ' +
                   'data-condition-id="' + condition.id + '">' +
                   '<div class="scd-condition-toggle">' +
                       '<input type="checkbox" ' +
                              'class="scd-condition-enabled" ' +
                              ( false !== condition.enabled ? 'checked' : '' ) + '>' +
                   '</div>' +
                   '<div class="scd-condition-content">' +
                       '<div class="scd-condition-type-wrapper">' +
                           '<select class="scd-condition-type">' +
                               typeOptions +
                           '</select>' +
                       '</div>' +
                       '<div class="scd-condition-operator-wrapper">' +
                           '<select class="scd-condition-operator">' +
                               operatorOptions +
                           '</select>' +
                       '</div>' +
                       '<div class="scd-condition-value-wrapper">' +
                           this.renderValueInput( condition, typeConfig ) +
                       '</div>' +
                   '</div>' +
                   '<div class="scd-condition-actions">' +
                       '<button type="button" ' +
                               'class="scd-remove-condition" ' +
                               'data-condition-id="' + condition.id + '" ' +
                               'title="Remove condition">×</button>' +
                   '</div>' +
               '</div>';
		},

		/**
		 * Render value input based on type
		 * @param condition
		 * @param typeConfig
		 */
		renderValueInput: function( condition, typeConfig ) {
			var inputType = typeConfig.inputType;
			var value = condition.value || '';

			switch ( inputType ) {
				case 'text':
					return '<input type="text" ' +
                           'class="scd-condition-value" ' +
                           'value="' + value + '" ' +
                           'placeholder="' + ( typeConfig.placeholder || '' ) + '">';

				case 'number':
					return '<input type="number" ' +
                           'class="scd-condition-value" ' +
                           'value="' + value + '" ' +
                           'min="' + ( typeConfig.min || '' ) + '" ' +
                           'step="' + ( typeConfig.step || 1 ) + '" ' +
                           'placeholder="' + ( typeConfig.placeholder || '' ) + '">';

				case 'select':
					var selectOptions = '<option value="">Select...</option>';
					( typeConfig.options || [] ).forEach( function( opt ) {
						selectOptions += '<option value="' + opt.value + '" ' +
                                        ( value === opt.value ? 'selected' : '' ) + '>' +
                                        opt.label + '</option>';
					} );
					return '<select class="scd-condition-value">' + selectOptions + '</select>';

				case 'role_select':
				case 'category_select':
					return this.renderMultiSelect( condition, typeConfig );

				case 'product_search':
					return this.renderProductSearch( condition, typeConfig );

				case 'date_range':
					return this.renderDateRange( condition, typeConfig );

				default:
					return '<input type="text" ' +
                           'class="scd-condition-value" ' +
                           'value="' + value + '" ' +
                           'placeholder="Enter value...">';
			}
		},

		/**
		 * Render multi-select input
		 * @param condition
		 * @param typeConfig
		 */
		renderMultiSelect: function( condition, typeConfig ) {
			var values = Array.isArray( condition.value ) ? condition.value : [];
			var options = typeConfig.options || [];

			var multiOptions = '';
			options.forEach( function( opt ) {
				multiOptions += '<option value="' + opt.value + '" ' +
                               ( -1 !== values.indexOf( opt.value ) ? 'selected' : '' ) + '>' +
                               opt.label + '</option>';
			} );
			return '<select class="scd-condition-value scd-multi-select" multiple>' + multiOptions + '</select>';
		},

		/**
		 * Render product search input
		 * @param condition
		 * @param typeConfig
		 */
		renderProductSearch: function( condition, typeConfig ) {
			var products = Array.isArray( condition.value ) ? condition.value : [];

			var productItems = '';
			products.forEach( function( product ) {
				productItems += '<span class="scd-selected-item" data-id="' + product.id + '">' +
                               product.name +
                               '<button type="button" class="scd-remove-item" data-id="' + product.id + '">×</button>' +
                               '</span>';
			} );
			return '<div class="scd-product-search-wrapper" data-condition-id="' + condition.id + '">' +
                   '<input type="text" ' +
                          'class="scd-product-search-input" ' +
                          'placeholder="' + ( typeConfig.placeholder || 'Search products...' ) + '">' +
                   '<div class="scd-selected-products">' +
                       productItems +
                   '</div>' +
               '</div>';
		},

		/**
		 * Render date range input
		 * @param condition
		 * @param _typeConfig
		 */
		renderDateRange: function( condition, _typeConfig ) {
			var value = condition.value || {};

			return '<div class="scd-date-range-wrapper">' +
                   '<input type="date" ' +
                          'class="scd-condition-value scd-date-start" ' +
                          'value="' + ( value.start || '' ) + '" ' +
                          'data-field="start">' +
                   '<span>to</span>' +
                   '<input type="date" ' +
                          'class="scd-condition-value scd-date-end" ' +
                          'value="' + ( value.end || '' ) + '" ' +
                          'data-field="end">' +
               '</div>';
		},

		/**
		 * Get operators for condition type
		 * @param type
		 */
		getOperatorsForType: function( type ) {
			var typeConfig = this.conditionTypes[type];
			return typeConfig ? typeConfig.operators : [ 'equals' ];
		},

		/**
		 * Add a new condition
		 */
		addCondition: function() {
			var conditions = this.state.getState( 'conditions' ) || [];

			if ( conditions.length >= this.maxConditions ) {return;}

			var newCondition = {
				id: this.generateConditionId(),
				type: 'product', // Default type
				operator: 'in_list',
				value: [],
				enabled: true
			};

			conditions.push( newCondition );
			this.state.setState( {
				conditions: conditions
			} );

			this.renderConditions();
		},

		/**
		 * Remove a condition
		 * @param id
		 */
		removeCondition: function( id ) {
			var conditions = this.state.getState( 'conditions' ) || [];
			var filtered = conditions.filter( function( c ) { return c.id !== id; } );

			this.state.setState( { conditions: filtered } );
			this.renderConditions();
		},

		/**
		 * Update a condition
		 * @param id
		 * @param updates
		 */
		updateCondition: function( id, updates ) {
			var conditions = this.state.getState( 'conditions' ) || [];
			var updated = conditions.map( function( c ) {
				return c.id === id ? $.extend( {}, c, updates ) : c;
			} );

			this.state.setState( { conditions: updated } );
		},

		/**
		 * Handle condition type change
		 * @param id
		 * @param newType
		 */
		handleTypeChange: function( id, newType ) {
			var typeConfig = this.conditionTypes[newType];
			if ( !typeConfig ) {return;}

			this.updateCondition( id, {
				type: newType,
				operator: typeConfig.operators[0],
				value: 'number' === typeConfig.inputType ? 0 :
					typeConfig.multiple ? [] : ''
			} );

			this.renderConditions();
		},

		/**
		 * Get value from input element
		 * @param $input
		 */
		getValueFromInput: function( $input ) {
			if ( $input.hasClass( 'scd-multi-select' ) ) {
				return $input.val() || [];
			} else if ( $input.hasClass( 'scd-date-start' ) || $input.hasClass( 'scd-date-end' ) ) {
				var $wrapper = $input.closest( '.scd-date-range-wrapper' );
				return {
					start: $wrapper.find( '.scd-date-start' ).val(),
					end: $wrapper.find( '.scd-date-end' ).val()
				};
			} else if ( 'number' === $input.attr( 'type' ) ) {
				return parseFloat( $input.val() ) || 0;
			} else {
				return $input.val();
			}
		},

		/**
		 * Get user roles from WordPress
		 */
		getUserRoles: function() {
			var roles = window.scdDiscountStepData && window.scdDiscountStepData.userRoles || [
				{ value: 'subscriber', label: 'Subscriber' },
				{ value: 'customer', label: 'Customer' },
				{ value: 'shop_manager', label: 'Shop Manager' },
				{ value: 'administrator', label: 'Administrator' }
			];

			return roles;
		},

		/**
		 * Generate unique condition ID
		 */
		generateConditionId: function() {
			return 'cond_' + Date.now() + '_' + Math.random().toString( 36 ).substr( 2, 9 );
		},

		/**
		 * Validate all conditions
		 */
		validateConditions: function() {
			var conditions = this.state.getState( 'conditions' ) || [];
			var errors = {};
			var warnings = {};

			conditions.forEach( function( condition, index ) {
				if ( !condition.enabled ) {return;}

				var typeConfig = this.conditionTypes[condition.type];
				if ( !typeConfig ) {
					errors['condition_' + index] = 'Invalid condition type';
					return;
				}

				if ( typeConfig.validate && !typeConfig.validate( condition.value ) ) {
					errors['condition_' + index + '_value'] = 'Invalid value for ' + typeConfig.label;
				}

				if ( !condition.value ||
                    ( Array.isArray( condition.value ) && 0 === condition.value.length ) ||
                    ( 'string' === typeof condition.value && '' === condition.value.trim() ) ) {
					errors['condition_' + index + '_value'] = typeConfig.label + ' value is required';
				}
			} );

			var productIncludes = conditions.filter( function( c ) { return 'product' === c.type && 'in_list' === c.operator; } );
			var productExcludes = conditions.filter( function( c ) { return 'product' === c.type && 'not_in_list' === c.operator; } );

			if ( 0 < productIncludes.length && 0 < productExcludes.length ) {
				warnings.productConflict = 'Both include and exclude product conditions are set';
			}

			return {
				valid: 0 === Object.keys( errors ).length,
				errors: errors,
				warnings: warnings
			};
		},

		/**
		 * Get condition summary
		 */
		getConditionSummary: function() {
			var conditions = this.state.getState( 'conditions' ) || [];
			var enabledConditions = conditions.filter( function( c ) { return false !== c.enabled; } );

			if ( 0 === enabledConditions.length ) {
				return 'No conditions ( applies to all )';
			}

			var logic = this.state.getState( 'conditionLogic' ) || 'all';
			var logicText = 'all' === logic ? 'all' : 'any';

			return enabledConditions.length + ' condition' + ( 1 < enabledConditions.length ? 's' : '' ) + ' (match ' + logicText + ')';
		},

		/**
		 * Clean up
		 */
		destroy: function() {
			$( document ).off( 'click.conditions' );
			$( document ).off( 'change.conditions' );
			$( document ).off( 'input.conditions' );
		}
	};

} )( jQuery );