/**
 * Products Filter Module
 *
 * Handles advanced filtering and conditions following single source of truth
 * State is the source of truth, DOM is updated from state
 *
 * @param $
 * @package SmartCycleDiscounts
 * @since 1.0.0
 */

( function( $ ) {
	'use strict';

	// Register module using utility
	SCD.Utils.registerModule( 'SCD.Modules.Products', 'Filter', function( state, api ) {
		this.state = state;
		this.api = api;
		this.elements = {};

		// Initialize event manager
		this.initEventManager();

		// Condition configuration
		this.conditionTypes = {};
		this.operators = {};

		// Ready state tracking
		this._ready = false;
		this._queuedConditions = null;

		// Track current conditions from state
		this._currentConditions = [];
		this._currentLogic = 'all';
	} );

	// Extend event manager mixin
	SCD.Utils.extend( SCD.Modules.Products.Filter.prototype, SCD.Mixins.EventManager );

	SCD.Modules.Products.Filter.prototype = SCD.Utils.extend( SCD.Modules.Products.Filter.prototype, {
		constructor: SCD.Modules.Products.Filter,

		/**
		 * Initialize filter module
		 * 
		 * @since 1.0.0
		 * @returns {void}
		 */
		init: function() {
			try {
				var self = this;

				this.loadConditionConfig();

				// Use setTimeout as fallback for older browsers
				var initFunction = function() {
					self.cacheElements();
					self.bindEvents();

					// Listen to state changes to update DOM
					self.bindStateListeners();

					// Process any queued conditions before marking as ready
					if ( self._queuedConditions ) {
						self.setConditions( self._queuedConditions );
						self._queuedConditions = null;
					}

					// Mark as ready after processing queue
					self._ready = true;

					// Trigger ready event
					self.triggerCustomEvent( 'scd:products:filter:ready', [ self ] );
				};
				
				if ( 'function' === typeof window.requestAnimationFrame ) {
					window.requestAnimationFrame( initFunction );
				} else {
					setTimeout( initFunction, 0 );
				}
			} catch ( e ) {
				if ( window.SCD && window.SCD.ErrorHandler ) {
					window.SCD.ErrorHandler.handle( e, 'products-filter-init', window.SCD.ErrorHandler.SEVERITY.HIGH );
				}
			}
		},

		/**
		 * Cache DOM elements
		 * 
		 * @since 1.0.0
		 * @private
		 * @returns {void}
		 */
		cacheElements: function() {
			try {
				this.elements = {
					$container: $( '.scd-conditions-section' ),
					$list: $( '#scd-conditions-list' ),
					$addButton: $( '.scd-add-condition' ),
					$conditionsLogic: $( '[name="conditions_logic"]' )
				};

				// Ensure jQuery collections exist even if elements don't
				$.each( this.elements, function( _key, $element ) {
					if ( ! $element || ! $element.length ) {
						this.elements[_key] = $();
					}
				}.bind( this ) );
			} catch ( e ) {
				if ( window.SCD && window.SCD.ErrorHandler ) {
					window.SCD.ErrorHandler.handle( e, 'products-filter-cache-elements', window.SCD.ErrorHandler.SEVERITY.LOW );
				}
			}
		},

		/**
		 * Load condition configuration
		 */
		loadConditionConfig: function() {
			try {
				// Define available operators for different field types
				this.operators = {
					number: [
						{ value: 'equals', text: 'equals', symbol: '=' },
						{ value: 'not_equals', text: 'not equals', symbol: '≠' },
						{ value: 'greater_than', text: 'greater than', symbol: '>' },
						{ value: 'less_than', text: 'less than', symbol: '<' },
						{ value: 'greater_equal', text: 'greater than or equal', symbol: '≥' },
						{ value: 'less_equal', text: 'less than or equal', symbol: '≤' },
						{ value: 'between', text: 'between', symbol: '↔' },
						{ value: 'not_between', text: 'not between', symbol: '↮' }
					],
					text: [
						{ value: 'equals', text: 'equals', symbol: '=' },
						{ value: 'not_equals', text: 'not equals', symbol: '≠' },
						{ value: 'contains', text: 'contains', symbol: '∋' },
						{ value: 'not_contains', text: 'does not contain', symbol: '∌' },
						{ value: 'starts_with', text: 'starts with', symbol: '⊃' },
						{ value: 'ends_with', text: 'ends with', symbol: '⊂' }
					],
					select: [
						{ value: 'equals', text: 'is', symbol: '=' },
						{ value: 'not_equals', text: 'is not', symbol: '≠' },
						{ value: 'in', text: 'is one of', symbol: '∈' },
						{ value: 'not_in', text: 'is not one of', symbol: '∉' }
					],
					boolean: [
						{ value: 'equals', text: 'is', symbol: '=' }
					],
					date: [
						{ value: 'equals', text: 'on', symbol: '=' },
						{ value: 'not_equals', text: 'not on', symbol: '≠' },
						{ value: 'greater_than', text: 'after', symbol: '>' },
						{ value: 'less_than', text: 'before', symbol: '<' },
						{ value: 'between', text: 'between', symbol: '↔' }
					]
				};
			} catch ( e ) {
				SCD.ErrorHandler.handle( e, 'products-filter-load-config' );
			}
		},

		/**
		 * Bind event handlers using event manager
		 */
		bindEvents: function() {
			var self = this;

			try {
				// Condition field changes
				this.bindDelegatedEvent( document, '.scd-condition-type', 'change', function( e ) {
					self.handleConditionTypeChange( e );
				} );

				this.bindDelegatedEvent( document, '.scd-condition-operator', 'change', function( e ) {
					self.handleConditionOperatorChange( e );
				} );

				this.bindDelegatedEvent( document, '.scd-condition-value', 'change input', function( e ) {
					self.handleConditionValueChange( e );
				} );

				this.bindDelegatedEvent( document, '.scd-condition-mode', 'change', function( e ) {
					self.handleConditionModeChange( e );
				} );

				// Conditions logic change
				this.bindDelegatedEvent( document, '[name="conditions_logic"]', 'change', function( e ) {
					self.handleConditionsLogicChange( e );
				} );

			} catch ( e ) {
				SCD.ErrorHandler.handle( e, 'products-filter-bind-events' );
			}
		},

		/**
		 * Bind state listeners - single source of truth
		 */
		bindStateListeners: function() {
			var self = this;

			// Listen to state changes to update DOM
			this.bindCustomEvent( 'scd:products:state:changed', function( e, data ) {
				if ( 'conditions' === data.property ) {
					self._currentConditions = data.value || [];
					self.renderConditionsFromState();
				}

				if ( 'conditionsLogic' === data.property ) {
					self._currentLogic = data.value || 'all';
					self.updateLogicUI();
				}

				// Clear conditions when switching to specific products
				if ( 'selectionType' === data.property && 'specific_products' === data.value ) {
					self.emitConditionsChange( [] );
				}
			} );

			// Get initial state
			if ( this.state ) {
				var currentState = this.state.getState();

				if ( currentState ) {
					this._currentConditions = currentState.conditions || [];
					this._currentLogic = currentState.conditionsLogic || 'all';
				} else {
					this._currentConditions = [];
					this._currentLogic = 'all';
				}
			}
		},

		/**
		 * Handle condition type change
		 * @param {Event} e Event object
		 */
		handleConditionTypeChange: function( e ) {
			try {
				var $select = $( e.target );
				var $row = $select.closest( '.scd-condition-row' );
				var _index = parseInt( $row.data( 'index' ) );
				var conditionType = $select.val();

				// Update operator dropdown for UX
				if ( conditionType ) {
					this.updateConditionOperators( $row, conditionType );
				} else {
					$row.find( '.scd-condition-operator' )
						.html( '<option value="">Select operator</option>' )
						.prop( 'disabled', true );
				}

				// Update state from current DOM
				this.updateStateFromDOM();

			} catch ( e ) {
				SCD.ErrorHandler.handle( e, 'products-filter-condition-type-change' );
			}
		},

		/**
		 * Handle condition operator change
		 * @param {Event} e Event object
		 */
		handleConditionOperatorChange: function( e ) {
			try {
				var $select = $( e.target );
				var $row = $select.closest( '.scd-condition-row' );
				var index = parseInt( $row.data( 'index' ) );
				var operator = $select.val();
				var conditionType = $row.find( '.scd-condition-type' ).val();

				// Update value field for UX
				if ( conditionType && operator ) {
					this.updateConditionValueField( $row, conditionType, operator, index );
				}

				// Update state from current DOM
				this.updateStateFromDOM();

			} catch ( e ) {
				SCD.ErrorHandler.handle( e, 'products-filter-operator-change' );
			}
		},

		/**
		 * Handle condition value change
		 * @param {Event} e Event object
		 */
		handleConditionValueChange: function( e ) {
			try {
				var $input = $( e.target );
				var $row = $input.closest( '.scd-condition-row' );
				var _index = parseInt( $row.data( 'index' ) );

				// For input events, debounce the sync
				if ( 'input' === e.type ) {
					var self = this;
					clearTimeout( this._valueChangeTimeout );
					this._valueChangeTimeout = setTimeout( function() {
						self.updateStateFromDOM();
					}, 300 );
				} else {
					// For change events, sync immediately
					this.updateStateFromDOM();
				}

			} catch ( e ) {
				SCD.ErrorHandler.handle( e, 'products-filter-value-change' );
			}
		},

		/**
		 * Handle condition mode change
		 * @param {Event} e Event object
		 */
		handleConditionModeChange: function( e ) {
			try {
				var $select = $( e.target );
				var $row = $select.closest( '.scd-condition-row' );
				var index = parseInt( $row.data( 'index' ) );
				var mode = $select.val();

				if ( ! isNaN( index ) && index >= 0 && index < this._currentConditions.length ) {
					// Update condition and emit event
					var updatedConditions = this._currentConditions.slice();
					updatedConditions[index].mode = mode;

					this.emitConditionsChange( updatedConditions );
				}
			} catch ( e ) {
				SCD.ErrorHandler.handle( e, 'products-filter-mode-change' );
			}
		},

		/**
		 * Handle conditions logic change
		 * @param {Event} e Event object
		 */
		handleConditionsLogicChange: function( e ) {
			try {
				var logic = $( e.target ).val();

				// Emit event for orchestrator to handle state update
				this.triggerCustomEvent( 'scd:products:field:changed', [ {
					field: 'conditionsLogic',
					value: logic
				} ] );
			} catch ( e ) {
				SCD.ErrorHandler.handle( e, 'products-filter-logic-change' );
			}
		},

		/**
		 * Emit conditions change event
		 * @param {Array} conditions Conditions array
		 */
		emitConditionsChange: function( conditions ) {
			try {
				// Emit event for orchestrator to handle state update
				this.triggerCustomEvent( 'scd:products:field:changed', [ {
					field: 'conditions',
					value: conditions
				} ] );
			} catch ( e ) {
				SCD.ErrorHandler.handle( e, 'products-filter-emit-conditions-change' );
				// Try to recover by updating local state at least
				this._currentConditions = conditions || [];
			}
		},

		/**
		 * Render conditions from state - single source of truth
		 */
		renderConditionsFromState: function() {
			try {
				// Try to find the list element again if it's missing
				if ( ! this.elements.$list || !this.elements.$list.length ) {
					this.elements.$list = $( '#scd-conditions-list' );

					if ( ! this.elements.$list.length ) {
						// Create the conditions list if it doesn't exist
						var $conditionsSection = $( '.scd-conditions-section' );
						if ( $conditionsSection.length ) {
							this.elements.$list = $( '<div id="scd-conditions-list" class="scd-conditions-list"></div>' );
							// Find the add button and insert list before it
							var $addButton = $conditionsSection.find( '.scd-add-condition' );
							if ( $addButton.length ) {
								this.elements.$list.insertBefore( $addButton );
							} else {
								$conditionsSection.append( this.elements.$list );
							}
						} else {
							return;
						}
					}
				}

				// Clear existing DOM
				this.elements.$list.empty();

				// Render each condition
				for ( var i = 0; i < this._currentConditions.length; i++ ) {
					var condition = this._currentConditions[i];
					var template = this.getConditionTemplate( i );

					// Append template
					this.elements.$list.append( template );

					// Find the newly added row
					var $row = this.elements.$list.find( '.scd-condition-row' ).last();

					if ( $row.length ) {
						this.populateConditionRow( $row, condition, i );
					}
				}

			} catch ( e ) {
				SCD.ErrorHandler.handle( e, 'products-filter-render-conditions' );
			}
		},

		/**
		 * Populate a condition row with data
		 * @param {jQuery} $row Row element
		 * @param {Object} condition Condition data
		 * @param {number} index Row index
		 */
		populateConditionRow: function( $row, condition, index ) {
			// Set mode
			$row.find( '.scd-condition-mode' ).val( condition.mode || 'include' );

			// Set type
			var $typeSelect = $row.find( '.scd-condition-type' );
			$typeSelect.val( condition.type || '' );

			// Update operators if type is set
			if ( condition.type ) {
				this.updateConditionOperators( $row, condition.type );

				// Set operator value after dropdown is populated
				if ( condition.operator ) {
					$row.find( '.scd-condition-operator' ).val( condition.operator );
					this.updateConditionValueField( $row, condition.type, condition.operator, index );
				} else {
					// Clear operator value
					$row.find( '.scd-condition-operator' ).val( '' );
					// Update value field for no operator
					this.updateConditionValueField( $row, condition.type, '', index );
				}
			} else {
				// No type selected - disable operator
				$row.find( '.scd-condition-operator' )
					.html( '<option value="">Select operator</option>' )
					.prop( 'disabled', true );
			}

			// Set values if they exist
			if ( condition.value !== undefined && '' !== condition.value ) {
				var $valueField = $row.find( '.scd-condition-value' ).first();
				if ( $valueField.is( '[multiple]' ) && 'string' === typeof condition.value ) {
					// Multiple select - try JSON parse first, fallback to comma-split
					var values;
					try {
						values = JSON.parse( condition.value );
						if ( ! Array.isArray( values ) ) {
							values = condition.value.split( ',' );
						}
					} catch ( e ) {
						values = condition.value.split( ',' );
					}
					$valueField.val( values );
				} else {
					$valueField.val( condition.value );
				}
			}

			// Set second value for between operator
			if ( condition.value2 !== undefined && '' !== condition.value2 ) {
				$row.find( '.scd-condition-value.scd-condition-value-between' ).val( condition.value2 );
			}
		},

		/**
		 * Update logic UI from state
		 */
		updateLogicUI: function() {
			if ( this.elements.$conditionsLogic.length ) {
				this.elements.$conditionsLogic.val( this._currentLogic );
			}
			if ( this.elements.$list.length ) {
				this.elements.$list.attr( 'data-logic', this._currentLogic );
			}
		},

		/**
		 * Update condition operators based on type
		 * @param {jQuery} $row Row element
		 * @param {string} conditionType Condition type
		 */
		updateConditionOperators: function( $row, conditionType ) {
			try {
				var $operatorSelect = $row.find( '.scd-condition-operator' );

				if ( ! $operatorSelect.length ) {
					return;
				}

				var fieldConfig = this.getConditionFieldConfig( conditionType );

				if ( ! fieldConfig ) {
					$operatorSelect.empty()
						.append( $( '<option></option>' ).attr( 'value', '' ).text( 'Select operator' ) )
						.prop( 'disabled', true );
					return;
				}

				// Get operators for this field type
				var operators = this.operators[fieldConfig.type] || this.operators.text;

				// Clear and rebuild options
				$operatorSelect.empty();

				// Add default option
				$operatorSelect.append( $( '<option></option>' )
					.attr( 'value', '' )
					.text( 'Select operator' )
				);

				// Add operator options
				for ( var i = 0; i < operators.length; i++ ) {
					var op = operators[i];
					$operatorSelect.append( $( '<option></option>' )
						.attr( 'value', op.value )
						.text( op.text )
					);
				}

				// Enable the select
				$operatorSelect.prop( 'disabled', false );

			} catch ( e ) {
				SCD.ErrorHandler.handle( e, 'products-filter-update-operators' );
			}
		},

		/**
		 * Update condition value field based on type and operator
		 * @param {jQuery} $row Row element
		 * @param {string} conditionType Condition type
		 * @param {string} operator Operator value
		 * @param {number} index Row index
		 */
		updateConditionValueField: function( $row, conditionType, operator, index ) {
			try {
				var $wrapper = $row.find( '.scd-condition-value-wrapper' );

				if ( ! conditionType || !operator ) {
					var $placeholderInput = $( '<input>' )
						.attr( 'type', 'text' )
						.addClass( 'scd-condition-value' )
						.attr( 'placeholder', 'Select condition type and operator' )
						.prop( 'disabled', true );
					$wrapper.empty().append( $placeholderInput );
					return;
				}

				var fieldConfig = this.getConditionFieldConfig( conditionType );
				var needsTwoValues = 'between' === operator || 'not_between' === operator;
				var needsMultipleValues = 'in' === operator || 'not_in' === operator;

				// Clear wrapper
				$wrapper.empty();

				if ( 'boolean' === fieldConfig.type ) {
					// Boolean select
					var $select = $( '<select></select>' )
						.attr( 'name', 'conditions[' + index + '][value]' )
						.addClass( 'scd-condition-value scd-condition-value-boolean' );

					$select.append( $( '<option></option>' ).attr( 'value', '1' ).text( 'Yes' ) );
					$select.append( $( '<option></option>' ).attr( 'value', '0' ).text( 'No' ) );

					$wrapper.append( $select );
				} else if ( 'select' === fieldConfig.type && fieldConfig.options ) {
					// Select with options
					if ( needsMultipleValues ) {
						var $multipleSelect = $( '<select></select>' )
							.attr( 'name', 'conditions[' + index + '][value][]' )
							.addClass( 'scd-condition-value scd-condition-value-select' )
							.prop( 'multiple', true );

						for ( var i = 0; i < fieldConfig.options.length; i++ ) {
							var multipleOpt = fieldConfig.options[i];
							$multipleSelect.append( $( '<option></option>' )
								.attr( 'value', multipleOpt.value )
								.text( multipleOpt.text )
							);
						}

						var $description = $( '<span></span>' )
							.addClass( 'description' )
							.text( 'Hold Ctrl/Cmd to select multiple' );

						$wrapper.append( $multipleSelect ).append( $description );
					} else {
						var $singleSelect = $( '<select></select>' )
							.attr( 'name', 'conditions[' + index + '][value]' )
							.addClass( 'scd-condition-value scd-condition-value-select' );

						$singleSelect.append( $( '<option></option>' ).attr( 'value', '' ).text( 'Select...' ) );

						for ( var j = 0; j < fieldConfig.options.length; j++ ) {
							var singleOpt = fieldConfig.options[j];
							$singleSelect.append( $( '<option></option>' )
								.attr( 'value', singleOpt.value )
								.text( singleOpt.text )
							);
						}

						$wrapper.append( $singleSelect );
					}
				} else if ( needsTwoValues ) {
					// Two value inputs
					var $input1 = $( '<input>' )
						.attr( 'name', 'conditions[' + index + '][value]' )
						.addClass( 'scd-condition-value scd-condition-value-single' )
						.attr( 'placeholder', fieldConfig.placeholder || 'Min value' );

					var $separator = $( '<span></span>' )
						.addClass( 'scd-condition-value-separator' )
						.text( 'and' );

					var $input2 = $( '<input>' )
						.attr( 'name', 'conditions[' + index + '][value2]' )
						.addClass( 'scd-condition-value scd-condition-value-between' )
						.attr( 'placeholder', fieldConfig.placeholder || 'Max value' );

					// Set input attributes based on field type
					if ( 'number' === fieldConfig.type ) {
						$input1.attr( 'type', 'number' )
							.attr( 'min', fieldConfig.min )
							.attr( 'step', fieldConfig.step );
						$input2.attr( 'type', 'number' )
							.attr( 'min', fieldConfig.min )
							.attr( 'step', fieldConfig.step );
					} else {
						$input1.attr( 'type', fieldConfig.type || 'text' );
						$input2.attr( 'type', fieldConfig.type || 'text' );
					}

					$wrapper.append( $input1 ).append( $separator ).append( $input2 );
				} else {
					// Single value input
					var $input = $( '<input>' )
						.attr( 'name', 'conditions[' + index + '][value]' )
						.addClass( 'scd-condition-value' )
						.attr( 'placeholder', fieldConfig.placeholder || 'Enter value' );

					// Set input attributes based on field type
					if ( 'number' === fieldConfig.type ) {
						$input.attr( 'type', 'number' )
							.attr( 'min', fieldConfig.min )
							.attr( 'step', fieldConfig.step );
					} else {
						$input.attr( 'type', fieldConfig.type || 'text' );
					}

					$wrapper.append( $input );
				}
			} catch ( e ) {
				SCD.ErrorHandler.handle( e, 'products-filter-update-value-field' );
			}
		},

		/**
		 * Get condition template
		 * @param {number} index Row index
		 * @returns {string} HTML template
		 */
		getConditionTemplate: function( index ) {
			// Validate index to prevent XSS
			index = parseInt( index );
			if ( isNaN( index ) || index < 0 ) {
				index = 0;
			}

			return '<div class="scd-condition-row" data-index="' + index + '">' +
					'<div class="scd-condition-fields">' +
						'<select name="conditions[' + index + '][mode]" class="scd-condition-mode" data-index="' + index + '">' +
							'<option value="include">Include</option>' +
							'<option value="exclude">Exclude</option>' +
						'</select>' +
						' ' +
						'<select name="conditions[' + index + '][type]" class="scd-condition-type" data-index="' + index + '" title="Choose a product attribute to filter by">' +
						'<option value="">Select condition type</option>' +
						'<optgroup label="Price & Inventory">' +
							'<option value="price" title="Regular product price">Regular Price</option>' +
							'<option value="sale_price" title="Current sale price ( if on sale )">Sale Price</option>' +
							'<option value="stock_quantity" title="Current stock level">Stock Quantity</option>' +
							'<option value="stock_status" title="In stock, out of stock, or on backorder">Stock Status</option>' +
							'<option value="low_stock_amount" title="Low stock threshold">Low Stock Amount</option>' +
						'</optgroup>' +
						'<optgroup label="Product Attributes">' +
							'<option value="weight" title="Product weight">Weight</option>' +
							'<option value="length" title="Product length">Length</option>' +
							'<option value="width" title="Product width">Width</option>' +
							'<option value="height" title="Product height">Height</option>' +
							'<option value="sku" title="Product SKU code">SKU</option>' +
						'</optgroup>' +
						'<optgroup label="Product Status">' +
							'<option value="featured" title="Featured product status">Featured Product</option>' +
							'<option value="on_sale" title="Currently on sale">On Sale</option>' +
							'<option value="virtual" title="Virtual product">Virtual Product</option>' +
							'<option value="downloadable" title="Downloadable product">Downloadable</option>' +
							'<option value="product_type" title="Simple, variable, grouped, etc.">Product Type</option>' +
						'</optgroup>' +
						'<optgroup label="Shipping & Tax">' +
							'<option value="tax_status" title="Taxable, shipping only, or none">Tax Status</option>' +
							'<option value="tax_class" title="Tax class">Tax Class</option>' +
							'<option value="shipping_class" title="Product shipping class">Shipping Class</option>' +
						'</optgroup>' +
						'<optgroup label="Reviews & Ratings">' +
							'<option value="average_rating" title="Average customer rating">Average Rating</option>' +
							'<option value="review_count" title="Number of reviews">Review Count</option>' +
						'</optgroup>' +
						'<optgroup label="Sales Data">' +
							'<option value="total_sales" title="Total units sold">Total Sales</option>' +
							'<option value="date_created" title="Product creation date">Date Created</option>' +
							'<option value="date_modified" title="Last modified date">Date Modified</option>' +
						'</optgroup>' +
					'</select>' +
					' ' +
					'<select name="conditions[' + index + '][operator]" class="scd-condition-operator" data-index="' + index + '">' +
						'<option value="">Select operator</option>' +
					'</select>' +
					' ' +
					'<div class="scd-condition-value-wrapper" data-index="' + index + '">' +
						'<input type="text" class="scd-condition-value" placeholder="Select condition type and operator" disabled>' +
					'</div>' +
					'</div>' +
					' ' +
					'<div class="scd-condition-actions">' +
						'<button type="button" class="button scd-remove-condition" title="Remove condition">' +
							'<span class="dashicons dashicons-trash"></span>' +
						'</button>' +
					'</div>' +
				'</div>';
		},

		/**
		 * Get field configuration for condition type
		 * @param {string} type Condition type
		 * @returns {Object} Field configuration
		 */
		getConditionFieldConfig: function( type ) {
			var configs = {
				// Price & Inventory
				'price': { type: 'number', min: '0', step: '0.01', placeholder: 'Price' },
				'sale_price': { type: 'number', min: '0', step: '0.01', placeholder: 'Sale price' },
				'stock_quantity': { type: 'number', min: '0', step: '1', placeholder: 'Quantity' },
				'stock_status': {
					type: 'select',
					options: [
						{ value: 'instock', text: 'In stock' },
						{ value: 'outofstock', text: 'Out of stock' },
						{ value: 'onbackorder', text: 'On backorder' }
					]
				},
				'low_stock_amount': { type: 'number', min: '0', step: '1', placeholder: 'Low stock threshold' },

				// Product Attributes
				'weight': { type: 'number', min: '0', step: '0.01', placeholder: 'Weight' },
				'length': { type: 'number', min: '0', step: '0.01', placeholder: 'Length' },
				'width': { type: 'number', min: '0', step: '0.01', placeholder: 'Width' },
				'height': { type: 'number', min: '0', step: '0.01', placeholder: 'Height' },
				'sku': { type: 'text', placeholder: 'SKU' },

				// Product Status
				'featured': { type: 'boolean' },
				'on_sale': { type: 'boolean' },
				'virtual': { type: 'boolean' },
				'downloadable': { type: 'boolean' },
				'product_type': {
					type: 'select',
					options: [
						{ value: 'simple', text: 'Simple product' },
						{ value: 'variable', text: 'Variable product' },
						{ value: 'grouped', text: 'Grouped product' },
						{ value: 'external', text: 'External/Affiliate product' }
					]
				},

				// Shipping & Tax
				'tax_status': {
					type: 'select',
					options: [
						{ value: 'taxable', text: 'Taxable' },
						{ value: 'shipping', text: 'Shipping only' },
						{ value: 'none', text: 'None' }
					]
				},
				'tax_class': { type: 'text', placeholder: 'Tax class' },
				'shipping_class': { type: 'text', placeholder: 'Shipping class ID' },

				// Reviews & Ratings
				'average_rating': { type: 'number', min: '0', max: '5', step: '0.1', placeholder: 'Rating ( 0-5 )' },
				'review_count': { type: 'number', min: '0', step: '1', placeholder: 'Number of reviews' },

				// Sales Data
				'total_sales': { type: 'number', min: '0', step: '1', placeholder: 'Total sales' },
				'date_created': { type: 'date', placeholder: 'Creation date' },
				'date_modified': { type: 'date', placeholder: 'Modified date' }
			};

			return configs[type] || { type: 'text', placeholder: 'Enter value' };
		},

		/**
		 * Get conditions - always syncs from DOM first
		 * @returns {Array} Current conditions
		 */
		getConditions: function() {
			// Get conditions from state
			if ( this.state && 'function' === typeof this.state.getState ) {
				return this.state.getState( 'conditions' ) || [];
			}

			// Fallback to internal state
			return this._currentConditions || [];
		},

		/**
		 * Check if module is ready
		 * @returns {boolean} Ready state
		 */
		isReady: function() {
			return true === this._ready;
		},

		/**
		 * Set conditions from data - used by field definitions for persistence
		 * @param {Array} conditions Conditions array
		 */
		setConditions: function( conditions ) {
			try {
				if ( ! conditions || !Array.isArray( conditions ) ) {
					conditions = [];
				}

				// Queue conditions if not ready
				if ( ! this.isReady() ) {
					this._queuedConditions = conditions;
					return;
				}

				// Update internal state
				this._currentConditions = conditions;

				// Update the state module immediately
				if ( this.state && 'function' === typeof this.state.setState ) {
					this.state.setState( { conditions: conditions } );
				}

				// Render to DOM
				this.renderConditionsFromState();

			} catch ( e ) {
				SCD.ErrorHandler.handle( e, 'products-filter-set-conditions' );
			}
		},

		/**
		 * Update state from current DOM values
		 */
		updateStateFromDOM: function() {
			if ( ! this.elements.$list || !this.elements.$list.length ) {
				return;
			}

			var conditions = [];
			var $rows = this.elements.$list.find( '.scd-condition-row' );

			$rows.each( function( _index ) {
				var $row = $( this );

				// Skip if row is being removed
				if ( $row.hasClass( 'removing' ) ) {
					return;
				}

				var condition = {
					type: $row.find( '.scd-condition-type' ).val() || '',
					operator: $row.find( '.scd-condition-operator' ).val() || '',
					value: $row.find( '.scd-condition-value' ).val() || '',
					mode: $row.find( '.scd-condition-mode' ).val() || 'include'
				};

				// Check for second value (for between operators)
				var $value2 = $row.find( '.scd-condition-value-between' );
				if ( $value2.length && $value2.val() ) {
					condition.value2 = $value2.val();
				}

				conditions.push( condition );
			} );

			// Update internal state
			this._currentConditions = conditions;

			// Update state module
			if ( this.state && 'function' === typeof this.state.setState ) {
				this.state.setState( { conditions: conditions } );
			}
		},

		/**
		 * Validate conditions
		 * @returns {Object} Validation result
		 */
		validateConditions: function() {
			// Basic validation
			var errors = [];

			for ( var i = 0; i < this._currentConditions.length; i++ ) {
				var condition = this._currentConditions[i];

				if ( ! condition.type ) {
					errors.push( 'Condition ' + ( i + 1 ) + ': Please select a condition type' );
				}
				if ( ! condition.operator ) {
					errors.push( 'Condition ' + ( i + 1 ) + ': Please select an operator' );
				}
				if ( 'between' !== condition.operator && 'not_between' !== condition.operator ) {
					if ( ! condition.value && 0 !== condition.value ) {
						errors.push( 'Condition ' + ( i + 1 ) + ': Please enter a value' );
					}
				} else {
					// Between operators need two values
					if ( ! condition.value && 0 !== condition.value ) {
						errors.push( 'Condition ' + ( i + 1 ) + ': Please enter the first value' );
					}
					if ( ! condition.value2 && 0 !== condition.value2 ) {
						errors.push( 'Condition ' + ( i + 1 ) + ': Please enter the second value' );
					}
				}
			}

			return {
				valid: errors.length === 0,
				errors: errors
			};
		},

		/**
		 * Destroy module
		 */
		destroy: function() {
			try {
				// Clear timeout
				if ( this._valueChangeTimeout ) {
					clearTimeout( this._valueChangeTimeout );
					this._valueChangeTimeout = null;
				}

				// Cleanup events using event manager
				if ( 'function' === typeof this.unbindAllEvents ) {
					this.unbindAllEvents();
				}

				// Clear all references
				this.elements = null;
				this._currentConditions = null;
				this._currentLogic = null;
				this.state = null;
				this.api = null;
				this._ready = false;
				this._queuedConditions = null;

				// Clear operator and condition type configs
				this.conditionTypes = null;
				this.operators = null;

			} catch ( e ) {
				SCD.ErrorHandler.handle( e, 'products-filter-destroy' );
			}
		}
	} );

} )( jQuery );