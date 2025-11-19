/**
 * Products Orchestrator
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/steps/products/products-orchestrator.js
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

( function( $ ) {
	'use strict';

	// Ensure namespaces exist
	window.SCD = window.SCD || {};
	SCD.Steps = SCD.Steps || {};
	SCD.Modules = SCD.Modules || {};
	SCD.Modules.Products = SCD.Modules.Products || {};

	/**
	 * Products Orchestrator
	 * Created using BaseOrchestrator.createStep() factory method
	 * Inherits from BaseOrchestrator with EventManager and StepPersistence mixins
	 *
	 * @since 1.0.0
	 */
	SCD.Steps.ProductsOrchestrator = SCD.Shared.BaseOrchestrator.createStep( 'products', {

		// =========================================================================
		// CUSTOM PROPERTIES (set on each instance in custom init)
		// =========================================================================

		_boundHandlers: {},

		// =========================================================================
		// INITIALIZATION METHODS
		// =========================================================================

		/**
		 * Custom initialization for products step
		 * The factory's init handles standard setup, this handles products-specific UI initialization
		 *
		 * @since 1.0.0
		 * @param {object} wizard - Wizard instance
		 * @param {object} config - Configuration options
		 * @returns {Promise}
		 */
		init: function( wizard, config ) {
			var self = this;

			this._boundHandlers = {};

			this.initializeModules();

			// Initialize UI components (async) and wait for completion
			var uiPromise = this.initializeUI();

			// Return promise that resolves when UI is ready
			return $.when( uiPromise ).then( function() {

				// CRITICAL: Bind events before setting initial state
				// This ensures the 'scd:populate-nested-array' handler is registered
				// BEFORE populateFields() is called by the wizard
				self.bindEvents();

				self._setInitialState();

				// Expose globally for field definitions
				window.scdProductsOrchestrator = self;

				return self;
			} ).fail( function( error ) {
				// Handle initialization errors
				this.safeErrorHandle( error, 'ProductsOrchestrator.init', SCD.ErrorHandler.SEVERITY.CRITICAL );
				throw error;
			} );
		},

		/**
		 * Initialize modules (State, API, Picker, ConditionsValidator)
		 *
		 * Uses Module Registry for declarative initialization with post-init hooks
		 *
		 * @since 1.0.0
		 * @private
		 * @returns {void}
		 */
		initializeModules: function() {
			var self = this;

			if ( Object.keys( this.modules ).length === 0 ) {
				// Use Module Registry for declarative module initialization
				var moduleConfig = SCD.Shared.ModuleRegistry.createStepConfig( 'products', {
					picker: {
						class: 'SCD.Modules.Products.Picker',
						deps: ['state', 'api']
					},
					conditionsValidator: {
						class: 'SCD.Modules.Products.ConditionsValidator',
					deps: ['state'],
					autoInit: false // Requires manual init with $container argument
					}
				} );

				this.modules = SCD.Shared.ModuleRegistry.initialize( moduleConfig, this );

				// Post-initialization: Register complex field handlers
				if ( 'function' === typeof this.registerComplexFieldHandler ) {
					this.registerComplexFieldHandler( 'products.state', this.modules.state );
					this.registerComplexFieldHandler( 'SCD.Modules.Products.Picker', this.modules.picker );
				}

				// Post-initialization: Initialize ConditionsValidator
				if ( this.modules.conditionsValidator && 'function' === typeof this.modules.conditionsValidator.init ) {
					this.modules.conditionsValidator.init( this.$container );
				}
			}

			// CRITICAL: Register state subscriber AFTER module init
			// This runs on EVERY initializeModules call to ensure subscriber is present
			// when navigating back to this step
			if ( this.modules.state && 'function' === typeof this.modules.state.subscribe ) {
				this.modules.state.subscribe( function( changes ) {
					// Only re-render if conditions changed
					if ( changes && 'conditions' === changes.property ) {
						self.renderConditions( changes.newValue );
						self.updateConditionsSummary( changes.newValue );
					}
				} );
			}
		},

		/**
		 * Initialize UI components
		 *
		 * @since 1.0.0
		 * @private
		 * @returns {Promise} Promise that resolves when UI initialized
		 */
		initializeUI: function() {
			var self = this;

			if ( this.modules.picker && 'function' === typeof this.modules.picker.init ) {
				return this.modules.picker.init()
					.then( function() {
						return self;
					} )
					.catch( function( error ) {
						SCD.ErrorHandler.handle( error, 'products-init-picker', SCD.ErrorHandler.SEVERITY.HIGH );
						throw error;
					} );
			}

			return Promise.resolve( this );
		},

		/**
		 * Custom initialization hook
		 * Called after all modules and UI are initialized
		 *
		 * NOTE: Do NOT populate TomSelect fields here!
		 * TomSelect restoration is handled by step-persistence.js via populateComplexField()
		 * which properly queues restoration until handlers are fully ready.
		 *
		 * Attempting to populate TomSelect here causes race conditions because:
		 * 1. TomSelect init() is asynchronous
		 * 2. Complex field handlers may not be fully registered yet
		 * 3. Step persistence system handles proper retry logic
		 */
		onInit: function() {
			// Reserved for future orchestrator-level initialization
			// Do NOT add TomSelect population logic here
		},

		/**
		 * Get default configuration
		 * @returns {object} Default config
		 */
		getDefaultConfig: function() {
			// Use parent default config if available
			if ( SCD.Shared.BaseOrchestrator.prototype.getDefaultConfig ) {
				return SCD.Shared.BaseOrchestrator.prototype.getDefaultConfig.call( this );
			}
			// Fallback
			return {
				validateOnChange: true,
				enableUndo: false
			};
		}
	} );

	// =========================================================================
	// EVENT BINDING METHODS
	// =========================================================================

	$.extend( SCD.Steps.ProductsOrchestrator.prototype, {

		/**
		 * Bind all events
		 * 
		 * @since 1.0.0
		 * @private
		 * @returns {void}
		 */
		bindEvents: function() {
			var self = this;

			// Call parent bindEvents for common functionality
			if ( 'function' === typeof SCD.Shared.BaseOrchestrator.prototype.bindEvents ) {
				SCD.Shared.BaseOrchestrator.prototype.bindEvents.call( this );
			}

			// Ensure container is available
			if ( ! this.$container || ! this.$container.length ) {
				return;
			}

			// Unbind previous events to prevent duplicates
			this.unbindEvents();

			this._boundHandlers.selectionTypeChange = function() {
				var selectedType = $( this ).val();

				self.updateSectionVisibility( selectedType );
				self.clearSelectionsForType( selectedType );

				if ( self.modules.state && 'function' === typeof self.modules.state.setState ) {
					self.modules.state.setState( { productSelectionType: selectedType } );
				}
			};

			// Selection type change
			this.$container.on( 'change.scd-products', '[name="product_selection_type"]', this._boundHandlers.selectionTypeChange );

			// Random count change
			this._boundHandlers.randomCountChange = function() {
				self.handleRandomCountChange( $( this ).val() );
			};
			this.$container.on( 'change.scd-products', '#scd-random-count', this._boundHandlers.randomCountChange );

			// Smart criteria change
			this._boundHandlers.smartCriteriaChange = function() {
				self.handleSmartCriteriaChange( $( this ).val() );
			};
			this.$container.on( 'change.scd-products', '[name="smart_criteria"]', this._boundHandlers.smartCriteriaChange );

			// Conditions logic change
			this._boundHandlers.conditionsLogicChange = function() {
				if ( self.modules.state && 'function' === typeof self.modules.state.setState ) {
					var logic = $( this ).val();
				self.modules.state.setState( { conditionsLogic: logic } );
				// Update data-logic attribute for AND/OR badges
				$( '#scd-conditions-list' ).attr( 'data-logic', logic );
				}
			};
			this.$container.on( 'change.scd-products', '[name="conditions_logic"]', this._boundHandlers.conditionsLogicChange );

			this._boundHandlers.addCondition = function( e ) {
				e.preventDefault();
				self.handleAddCondition();
			};
			this.$container.on( 'click.scd-products', '.scd-add-condition', this._boundHandlers.addCondition );

			this._boundHandlers.removeCondition = function( e ) {
				e.preventDefault();
				self.handleRemoveCondition( $( this ).closest( '.scd-condition-row' ) );
			};
			this.$container.on( 'click.scd-products', '.scd-remove-condition', this._boundHandlers.removeCondition );

			// Nested array population (for conditions field restoration)
			this._boundHandlers.populateNestedArray = function( e, data ) {
				if ( data && 'conditions' === data.fieldName ) {
					self.handlePopulateConditions( data.value || [] );
				}
			};
			$( document ).on( 'scd:populate-nested-array', this._boundHandlers.populateNestedArray );

			// Custom events from modules
			this._bindModuleEvents();
		},

		/**
		 * Unbind events to prevent memory leaks
		 * 
		 * @since 1.0.0
		 * @private
		 * @returns {void}
		 */
		unbindEvents: function() {
			if ( this.$container && this.$container.length ) {
				this.$container.off( '.scd-products' );
			}

			// Unbind document-level events
			if ( this._boundHandlers.populateNestedArray ) {
				$( document ).off( 'scd:populate-nested-array', this._boundHandlers.populateNestedArray );
			}

			this._boundHandlers = {};
		},

		/**
		 * Bind custom events from modules
		 *
		 * @since 1.0.0
		 * @private
		 * @returns {void}
		 */
		_bindModuleEvents: function() {
			var self = this;

			// NOTE: State subscriber for conditions is now registered in initializeModules()
			// This ensures it's active BEFORE any setState() calls, preventing missed renders

			// Condition field change handlers
			this._boundHandlers.conditionTypeChange = function() {
				var $row = $( this ).closest( '.scd-condition-row' );
				var index = parseInt( $row.data( 'index' ), 10 );
				var newType = $( this ).val();
				self.handleConditionTypeChange( index, newType, $row );
			};
			this.$container.on( 'change.scd-products', '.scd-condition-type', this._boundHandlers.conditionTypeChange );

			this._boundHandlers.conditionOperatorChange = function() {
				var $row = $( this ).closest( '.scd-condition-row' );
				var index = parseInt( $row.data( 'index' ), 10 );
				var newOperator = $( this ).val();
				self.handleConditionOperatorChange( index, newOperator, $row );
			};
			this.$container.on( 'change.scd-products', '.scd-condition-operator', this._boundHandlers.conditionOperatorChange );

			this._boundHandlers.conditionModeChange = function() {
				var $row = $( this ).closest( '.scd-condition-row' );
				var index = parseInt( $row.data( 'index' ), 10 );
				var newMode = $( this ).val();
				self.handleConditionModeChange( index, newMode );
			};
			this.$container.on( 'change.scd-products', '.scd-condition-mode', this._boundHandlers.conditionModeChange );

			this._boundHandlers.conditionValueChange = function() {
				var $row = $( this ).closest( '.scd-condition-row' );
				var index = parseInt( $row.data( 'index' ), 10 );
				self.handleConditionValueChange( index, $row );
			};
			this.$container.on( 'input.scd-products change.scd-products', '.scd-condition-value', this._boundHandlers.conditionValueChange );

			// Product selection events
			if ( 'function' === typeof this.bindCustomEvent ) {
				this.bindCustomEvent( 'scd:products:selected', function() {
					self.updateProductsList();
				} );

				this.bindCustomEvent( 'scd:products:deselected', function() {
					self.updateProductsList();
				} );

				// Field changes
				this.bindCustomEvent( 'scd:products:field:changed', function( event, data ) {
					if ( data && data.field && self.modules.state && 'function' === typeof self.modules.state.setState ) {
						var update = {};
						update[data.field] = data.value;
						self.modules.state.setState( update );
					}
				} );
			}
		}
	} );

	// =========================================================================
	// UI UPDATE METHODS
	// =========================================================================

	$.extend( SCD.Steps.ProductsOrchestrator.prototype, {

		/**
		 * Update section visibility based on selection type
		 * 
		 * @since 1.0.0
		 * @param {string} selectionType - The selected product selection type
		 * @returns {void}
		 */
		updateSectionVisibility: function( selectionType ) {
			var self = this;

			if ( ! this.$container || ! this.$container.length ) {
				return;
			}

			this.$container.find( '.scd-random-count, .scd-specific-products, .scd-smart-criteria' ).each( function() {
				$( this ).removeClass( 'scd-active-section' ).hide();
			} );

			var sectionSelector = '';
			switch ( selectionType ) {
				case 'random_products':
					sectionSelector = '.scd-random-count';
					break;
				case 'specific_products':
					sectionSelector = '.scd-specific-products';
					break;
				case 'smart_selection':
					sectionSelector = '.scd-smart-criteria';
					break;
			}


			if ( sectionSelector ) {
				var $section = this.$container.find( sectionSelector );
				$section.addClass( 'scd-active-section' ).show();

				// If the section is inside a card option, ensure the parent is visible
				var $parentCard = $section.closest( '.scd-card-option' );
				if ( $parentCard.length ) {
					$parentCard.find( sectionSelector ).show();
				}
			}

			// NOTE: We DO NOT update state here - this method is for UI updates only.
			// State should already be set before this method is called.
			// Updating state here causes the wrong value to overwrite correct loaded data.

			// Enable/disable Advanced Filters (conditions) based on selection type
			// Advanced Filters are only available for 'all_products' and 'random_products'
			var $conditionsSection = this.$container.find( '.scd-conditions-section' );
			if ( 'all_products' === selectionType || 'random_products' === selectionType ) {
				$conditionsSection.removeClass( 'scd-disabled' );
			} else {
				$conditionsSection.addClass( 'scd-disabled' );
			}

			// Initialize Product Select if needed (for specific_products mode)
			if ( 'specific_products' === selectionType && this.modules.picker ) {
				// Delay to ensure DOM is ready
				setTimeout( function() {
					if ( self.modules.picker && 'function' === typeof self.modules.picker.initProductSelect ) {
						self.modules.picker.initProductSelect();
					}
				}, 100 );
			}
		},

		/**
		 * Update products list display
		 *
		 * @since 1.0.0
		 * @returns {void}
		 */
		updateProductsList: function() {
			var state = this.modules.state ? this.modules.state.getState() : {};
			var count = 0;

			// Calculate count based on selection type
			if ( 'specific_products' === state.productSelectionType ) {
				count = state.productIds ? state.productIds.length : 0;
			} else if ( 'random_products' === state.productSelectionType ) {
				count = state.randomCount || 0;
			}

			// Update UI count display
			this.$container.find( '.scd-selected-count' ).text( count );
		},

		/**
		 * Render all condition rows
		 *
		 * @since 1.0.0
		 * @param {Array} conditions - Array of condition objects
		 * @returns {void}
		 */
		renderConditions: function( conditions ) {
			var $list = this.$container.find( '#scd-conditions-list' );
			if ( ! $list || ! $list.length ) {
				return;
			}

			// Clear existing rows
			$list.empty();

			// Render each condition
			if ( conditions && conditions.length > 0 ) {
				for ( var i = 0; i < conditions.length; i++ ) {
					var $row = this.renderConditionRow( i, conditions[i] );
					$list.append( $row );
				}
			} else {
			}
		},

		/**
		 * Render a single condition row
		 *
		 * @since 1.0.0
		 * @param {number} index - Condition index
		 * @param {object} condition - Condition data
		 * @returns {jQuery} Rendered row element
		 */
		renderConditionRow: function( index, condition ) {
			var conditionTypes = window.scdProductsState && window.scdProductsState.condition_types || {};
			var operatorMappings = window.scdProductsState && window.scdProductsState.operator_mappings || {};

			var conditionType = condition.conditionType || '';
			var conditionMode = condition.mode || 'include';
			var conditionOperator = condition.operator || '';
			var conditionValue = condition.value || '';
			var conditionValue2 = condition.value2 || '';

			// Get operators for this condition type
			var operators = this._getOperatorsForType( conditionType, operatorMappings );
			var hasType = '' !== conditionType;
			var hasOperator = '' !== conditionOperator;
			var isBetween = 'between' === conditionOperator || 'not_between' === conditionOperator;

			// Build row HTML
			var $row = $( '<div class="scd-condition-row" data-index="' + index + '"></div>' );

			// Build fields container
			var $fields = $( '<div class="scd-condition-fields"></div>' );

			// Mode select
			var $mode = $( '<select name="conditions[' + index + '][mode]" class="scd-condition-mode scd-enhanced-select" data-index="' + index + '"></select>' );
			$mode.append( '<option value="include"' + ( 'include' === conditionMode ? ' selected' : '' ) + '>Include</option>' );
			$mode.append( '<option value="exclude"' + ( 'exclude' === conditionMode ? ' selected' : '' ) + '>Exclude</option>' );
			$fields.append( $mode );

			// Type select
			var $type = $( '<select name="conditions[' + index + '][type]" class="scd-condition-type scd-enhanced-select" data-index="' + index + '"></select>' );
			$type.append( '<option value="">Select condition type</option>' );
			for ( var groupKey in conditionTypes ) {
				if ( conditionTypes.hasOwnProperty( groupKey ) ) {
					var group = conditionTypes[groupKey];
					var $optgroup = $( '<optgroup label="' + this._escapeHtml( group.label ) + '"></optgroup>' );
					for ( var optValue in group.options ) {
						if ( group.options.hasOwnProperty( optValue ) ) {
							var optLabel = group.options[optValue];
							var selected = conditionType === optValue ? ' selected' : '';
							$optgroup.append( '<option value="' + this._escapeHtml( optValue ) + '"' + selected + '>' + this._escapeHtml( optLabel ) + '</option>' );
						}
					}
					$type.append( $optgroup );
				}
			}
			$fields.append( $type );

			// Operator select
			var $operator = $( '<select name="conditions[' + index + '][operator]" class="scd-condition-operator scd-enhanced-select" data-index="' + index + '"' + ( ! hasType ? ' disabled' : '' ) + '></select>' );
			$operator.append( '<option value="">Select operator</option>' );
			if ( hasType && operators ) {
				for ( var opValue in operators ) {
					if ( operators.hasOwnProperty( opValue ) ) {
						var opLabel = operators[opValue];
						var opSelected = conditionOperator === opValue ? ' selected' : '';
						$operator.append( '<option value="' + this._escapeHtml( opValue ) + '"' + opSelected + '>' + this._escapeHtml( opLabel ) + '</option>' );
					}
				}
			}
			$fields.append( $operator );

			// Value wrapper
			var $valueWrapper = $( '<div class="scd-condition-value-wrapper" data-index="' + index + '"></div>' );

			// Value input
			var $value = $( '<input type="text" name="conditions[' + index + '][value]" class="scd-condition-value scd-condition-value-single scd-enhanced-input" value="' + this._escapeHtml( conditionValue ) + '" placeholder="Enter value"' + ( ! hasOperator ? ' disabled' : '' ) + ' />' );
			$valueWrapper.append( $value );

			// Separator and value2 for "between" operators
			var $separator = $( '<span class="scd-condition-value-separator' + ( isBetween ? '' : ' scd-hidden' ) + '">and</span>' );
			$valueWrapper.append( $separator );

			var $value2 = $( '<input type="text" name="conditions[' + index + '][value2]" class="scd-condition-value scd-condition-value-between scd-enhanced-input' + ( isBetween ? '' : ' scd-hidden' ) + '" value="' + this._escapeHtml( conditionValue2 ) + '" placeholder="Max value"' + ( ! hasOperator ? ' disabled' : '' ) + ' />' );
			$valueWrapper.append( $value2 );

			$fields.append( $valueWrapper );
			$row.append( $fields );

			// Actions
			var $actions = $( '<div class="scd-condition-actions"></div>' );
			var trashIcon = SCD.IconHelper ? SCD.IconHelper.get( 'trash', { size: 16 } ) : '<span class="scd-icon scd-icon-trash"></span>';
			var $removeBtn = $( '<button type="button" class="button scd-remove-condition" title="Remove this condition">' + trashIcon + '</button>' );
			$actions.append( $removeBtn );
			$row.append( $actions );

			return $row;
		},

		/**
		 * Get operators for a condition type
		 *
		 * @since 1.0.0
		 * @private
		 * @param {string} conditionType - Condition type
		 * @param {object} operatorMappings - Operator mappings from field definitions
		 * @returns {object} Operators for the type
		 */
		_getOperatorsForType: function( conditionType, operatorMappings ) {
			if ( ! conditionType || ! operatorMappings ) {
				return {};
			}

			// Iterate through operator mappings to find the one that includes this type
			for ( var mappingKey in operatorMappings ) {
				if ( operatorMappings.hasOwnProperty( mappingKey ) ) {
					var mapping = operatorMappings[mappingKey];
					if ( mapping.types && -1 !== mapping.types.indexOf( conditionType ) ) {
						return mapping.operators || {};
					}
				}
			}

			// Default to numeric operators if type not found
			return operatorMappings.numeric && operatorMappings.numeric.operators ? operatorMappings.numeric.operators : {};
		},

		/**
		 * Escape HTML for safe output
		 *
		 * @since 1.0.0
		 * @private
		 * @param {string} text - Text to escape
		 * @returns {string} Escaped text
		 */
		_escapeHtml: function( text ) {
			var map = {
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				'"': '&quot;',
				"'": '&#039;'
			};
			return String( text ).replace( /[&<>"']/g, function( m ) {
				return map[m];
			} );
		}

	} );

	// =========================================================================
	// DATA HANDLING METHODS
	// =========================================================================

	$.extend( SCD.Steps.ProductsOrchestrator.prototype, {

		/**
		 * Custom logic after field population
		 * Called by StepPersistence mixin after standard field population
		 * @param {object} data - Data to populate
		 */
		onPopulateFieldsComplete: function( data ) {
			var selectionType = data.productSelectionType || 'all_products';
			this.updateSectionVisibility( selectionType );
		},

		/**
		 * Process category selection with business logic
		 * Handles "All Categories" exclusive selection logic
		 *
		 * @since 1.0.0
		 * @param {Array} categories - Selected category IDs
		 * @returns {Array} Processed category IDs
		 */
		processCategorySelection: function( categories ) {
			// Ensure array format
			if ( ! Array.isArray( categories ) ) {
				categories = categories ? [ categories ] : [ 'all' ];
			}

			categories = categories.filter( function( cat ) {
				return null !== cat && cat !== undefined && '' !== cat;
			} ).map( String );

			// If no valid categories, default to "All Categories"
			if ( 0 === categories.length ) {
				categories = [ 'all' ];
			}

			// Handle "All Categories" exclusive logic
			var state = this.modules.state.getState();
			var previousCategories = state.categoryIds || [ 'all' ];
			var allWasSelected = -1 !== previousCategories.indexOf( 'all' );
			var allIsNowSelected = -1 !== categories.indexOf( 'all' );

			// Case 1: "All Categories" is selected along with other categories
			if ( allIsNowSelected && 1 < categories.length ) {
				if ( ! allWasSelected ) {
					// User just added "All Categories" - it takes precedence
					categories = [ 'all' ];
				} else {
					// "All Categories" was already selected, user added specific category - remove "All"
					categories = categories.filter( function( cat ) {
						return 'all' !== cat;
					} );
					// If filtering removed everything, default back to "All Categories"
					if ( 0 === categories.length ) {
						categories = [ 'all' ];
					}
				}
			}

			return categories;
		},

		/**
		 * Handle category changes and trigger necessary updates
		 * Business logic for category filter changes
		 *
		 * @since 1.0.0
		 * @param {Array} categories - New category selection
		 * @returns {void}
		 */
		handleCategoryChange: function( categories ) {

			var state = this.modules.state.getState();
			var oldCategories = state.categoryIds || [ 'all' ];

			// Process categories with business logic
			categories = this.processCategorySelection( categories );

			this.modules.state.setState( { categoryIds: categories } );

			var categoriesChanged = JSON.stringify( categories.slice().sort() ) !==
									JSON.stringify( oldCategories.slice().sort() );

			if ( categoriesChanged ) {
				// Trigger event for other modules (e.g., TomSelect to reload products)
				$( document ).trigger( 'scd:categories:changed', {
					categories: categories,
					oldCategories: oldCategories
				} );
			}
		},

		/**
		 * Clear selections based on type
		 * @param {string} selectionType - The selection type
		 */
		clearSelectionsForType: function( selectionType ) {
			if ( !this.modules.state ) {
				return;
			}

			var clearData = {
				productIds: [],
				randomCount: '',
				smartCriteria: '',
				conditions: []
			};

			switch ( selectionType ) {
				case 'specific_products':
					delete clearData.randomCount;
					delete clearData.smartCriteria;
					delete clearData.conditions;
					break;
				case 'random_products':
					delete clearData.productIds;
					delete clearData.smartCriteria;
					delete clearData.conditions; // Don't clear conditions - this type uses them!
					clearData.randomCount = 10; // Default
					break;
				case 'smart_selection':
					delete clearData.productIds;
					delete clearData.randomCount;
					delete clearData.conditions;
					break;
				case 'all_products':
					delete clearData.productIds;
					delete clearData.randomCount;
					delete clearData.smartCriteria;
					delete clearData.conditions; // Don't clear conditions - this type uses them!
					break;
			}

			this.modules.state.setState( clearData );
		}

	} );

	// =========================================================================
	// EVENT HANDLER METHODS
	// =========================================================================

	$.extend( SCD.Steps.ProductsOrchestrator.prototype, {

		/**
		 * Handle random count change
		 * 
		 * @since 1.0.0
		 * @param {string} value - The new value
		 * @returns {void}
		 */
		handleRandomCountChange: function( value ) {
			if ( this.modules.state && 'function' === typeof this.modules.state.setState ) {
				var numValue = parseInt( value, 10 ) || 0;
				this.modules.state.setState( { randomCount: numValue } );
			}
		},

		/**
		 * Handle smart criteria change
		 * @param {string} value - The new value
		 */
		handleSmartCriteriaChange: function( value ) {
			if ( this.modules.state ) {
				this.modules.state.setState( { smartCriteria: value } );
			}
		},

		/**
		 * Handle add condition
		 * 
		 * @since 1.0.0
		 * @returns {void}
		 */
		handleAddCondition: function() {
			if ( ! this.modules.state || 'function' !== typeof this.modules.state.getState ) {
				return;
			}

			var state = this.modules.state.getState();
			var currentConditions = state.conditions || [];
			var newCondition = {
				conditionType: '',
				operator: '',
				value: '',
				value2: '',
				mode: 'include'
			};

			var updatedConditions = currentConditions.slice();
			updatedConditions.push( newCondition );
			
			this.modules.state.setState( { conditions: updatedConditions } );
		},

		/**
		 * Handle remove condition
		 *
		 * @since 1.0.0
		 * @param {jQuery} $row - Row to remove
		 * @returns {void}
		 */
		handleRemoveCondition: function( $row ) {
			if ( ! this.modules.state || ! $row || ! $row.length ) {
				return;
			}

			var index = parseInt( $row.data( 'index' ), 10 );
			if ( isNaN( index ) || index < 0 ) {
				return;
			}

			var state = this.modules.state.getState();
			var currentConditions = state.conditions || [];

			if ( index < currentConditions.length ) {
				var updatedConditions = [];
				for ( var i = 0; i < currentConditions.length; i++ ) {
					if ( i !== index ) {
						updatedConditions.push( currentConditions[i] );
					}
				}
				this.modules.state.setState( { conditions: updatedConditions } );
			}
		},

		/**
		 * Handle populate conditions (restore from saved data)
		 *
		 * @since 1.0.0
		 * @param {Array} conditions - Array of condition objects
		 * @returns {void}
		 */
		handlePopulateConditions: function( conditions ) {
			if ( ! this.modules.state ) {
				return;
			}

			var validConditions = Array.isArray( conditions ) ? conditions : [];

			// State module will trigger UI re-render via existing mechanisms
			this.modules.state.setState( { conditions: validConditions } );
		},

		/**
		 * Handle condition type change
		 *
		 * @since 1.0.0
		 * @param {number} index - Condition index
		 * @param {string} newType - New condition type
		 * @param {jQuery} $row - Condition row element
		 * @returns {void}
		 */
		handleConditionTypeChange: function( index, newType, $row ) {
			if ( ! this.modules.state ) {
				return;
			}

			var state = this.modules.state.getState();
			var conditions = state.conditions || [];
			if ( index >= conditions.length ) {
				return;
			}

			// Update condition in state - reset operator and values when conditionType changes
			var updatedConditions = conditions.slice();
			updatedConditions[index] = {
				conditionType: newType,
				operator: '',
				value: '',
				value2: '',
				mode: updatedConditions[index].mode || 'include'
			};

			this.modules.state.setState( { conditions: updatedConditions } );
		},

		/**
		 * Handle condition operator change
		 *
		 * @since 1.0.0
		 * @param {number} index - Condition index
		 * @param {string} newOperator - New operator
		 * @param {jQuery} $row - Condition row element
		 * @returns {void}
		 */
		handleConditionOperatorChange: function( index, newOperator, $row ) {
			if ( ! this.modules.state ) {
				return;
			}

			var state = this.modules.state.getState();
			var conditions = state.conditions || [];
			if ( index >= conditions.length ) {
				return;
			}

			// Update condition operator
			var updatedConditions = conditions.slice();
			updatedConditions[index] = $.extend( {}, updatedConditions[index], {
				operator: newOperator
			} );

			// If switching away from "between", clear value2
			var isBetween = 'between' === newOperator || 'not_between' === newOperator;
			if ( ! isBetween ) {
				updatedConditions[index].value2 = '';
			}

			this.modules.state.setState( { conditions: updatedConditions } );
		},

		/**
		 * Handle condition mode change (include/exclude)
		 *
		 * @since 1.0.0
		 * @param {number} index - Condition index
		 * @param {string} newMode - New mode
		 * @returns {void}
		 */
		handleConditionModeChange: function( index, newMode ) {
			if ( ! this.modules.state ) {
				return;
			}

			var state = this.modules.state.getState();
			var conditions = state.conditions || [];
			if ( index >= conditions.length ) {
				return;
			}

			// Update condition mode
			var updatedConditions = conditions.slice();
			updatedConditions[index] = $.extend( {}, updatedConditions[index], {
				mode: newMode
			} );

			this.modules.state.setState( { conditions: updatedConditions } );
		},

		/**
		 * Handle condition value change
		 *
		 * @since 1.0.0
		 * @param {number} index - Condition index
		 * @param {jQuery} $row - Condition row element
		 * @returns {void}
		 */
		handleConditionValueChange: function( index, $row ) {
			if ( ! this.modules.state ) {
				return;
			}

			var state = this.modules.state.getState();
			var conditions = state.conditions || [];
			if ( index >= conditions.length ) {
				return;
			}

			// Get values from inputs
			var value = $row.find( '.scd-condition-value-single' ).val() || '';
			var value2 = $row.find( '.scd-condition-value-between' ).val() || '';

			// Update condition values
			var updatedConditions = conditions.slice();
			updatedConditions[index] = $.extend( {}, updatedConditions[index], {
				value: value,
				value2: value2
			} );

			// Use batch update to avoid re-render on every keystroke
			this.modules.state.setState( { conditions: updatedConditions }, true );
		}
	} );

	// =========================================================================
	// VALIDATION METHODS
	// =========================================================================

	$.extend( SCD.Steps.ProductsOrchestrator.prototype, {

		/**
		 * Collect complex field data (for conditions)
		 *
		 * Called by StepPersistence.collectData() for fields with type 'complex'.
		 * The conditions field is defined as 'complex' type in PHP and needs
		 * special handling because it's stored in state, not in form fields.
		 *
		 * @since 1.0.0
		 * @param {object} fieldDef Field definition
		 * @returns {*} Field value
		 */
		collectComplexField: function( fieldDef ) {
			// Handle conditions field - get from state
			if ( 'conditions' === fieldDef.fieldName ) {
				if ( this.modules && this.modules.state ) {
					var state = this.modules.state.getState();
					var conditions = state.conditions || [];
					return conditions;
				}
				return [];
			}

			// Handle other complex fields using default behavior
			// (fallback to empty array for unknown complex fields)
			return [];
		},

		/**
		 * Custom validation for the products step
		 *
		 * @since 1.0.0
		 * @param {object} data Optional data to validate
		 * @returns {object} Validation result
		 */
		validate: function( data ) {
			try {
				data = data || ( 'function' === typeof this.collectData ? this.collectData() : {} );
			} catch ( e ) {
				return {
					valid: false,
					errors: { general: 'Error collecting data for validation' }
				};
			}
			
			var errors = {};
			var selectionType = data.productSelectionType || 'all_products';

			// Business logic validation by selection type
			switch ( selectionType ) {
				case 'all_products':
					// No additional validation needed
					break;

				case 'random_products':
					this._validateRandomProducts( data, errors );
					break;

				case 'specific_products':
					this._validateSpecificProducts( data, errors );
					break;

				case 'smart_selection':
					this._validateSmartSelection( data, errors );
					break;
			}

			return {
				valid: Object.keys( errors ).length === 0,
				errors: errors
			};
		},

		/**
		 * Validate random products configuration
		 * 
		 * @since 1.0.0
		 * @private
		 * @param {object} data - Data to validate
		 * @param {object} errors - Errors object to populate
		 * @returns {void}
		 */
		_validateRandomProducts: function( data, errors ) {
			var randomCount = parseInt( data.randomCount || 0, 10 );
			
			var hasConditions = data.conditions && Array.isArray( data.conditions ) && data.conditions.length > 0;
			
			var hasCategories = false;
			if ( data.categoryIds && Array.isArray( data.categoryIds ) ) {
				hasCategories = data.categoryIds.length > 0 && data.categoryIds[0] !== 'all';
			}
			
			var hasFilters = hasConditions || hasCategories;

			if ( hasFilters && randomCount > 50 ) {
				errors.randomCount = 'When using filters, random count should not exceed 50 to ensure sufficient product availability';
			}
		},

		/**
		 * Validate specific products configuration
		 * 
		 * @since 1.0.0
		 * @private
		 * @param {object} data - Data to validate
		 * @param {object} errors - Errors object to populate
		 * @returns {void}
		 */
		_validateSpecificProducts: function( data, errors ) {
			var productIds = data.productIds || [];

			// Ensure it's an array
			if ( ! Array.isArray( productIds ) ) {
				productIds = [];
			}

			if ( productIds.length === 0 ) {
				errors.productIds = 'Please select at least one product';
				return;
			}

			if ( productIds.length > 100 ) {
				errors.productIds = 'Selecting more than 100 specific products may impact performance';
			}
		},

		/**
		 * Validate smart selection configuration
		 * @private
		 * @param {object} data - Data to validate
		 * @param {object} errors - Errors object to populate
		 */
		_validateSmartSelection: function( data, _errors ) {
			var smartCriteria = data.smartCriteria || '';
			var categoryIds = data.categoryIds || [ 'all' ];

			if ( 'low_stock' === smartCriteria && 'all' === categoryIds[0] ) {
				// Warning only - not an error
			}
		},

		/**
		 * Validate step for navigation
		 * Called by wizard-navigation.js before allowing navigation to next step
		 *
		 * @since 1.0.0
		 * @returns {Promise} Promise that resolves to boolean (true = valid, false = invalid)
		 */
		validateStep: function() {
			var isValid = true;

			// Validate filter conditions if conditions validator is available
			if ( this.modules.conditionsValidator && 'function' === typeof this.modules.conditionsValidator.validateAllConditions ) {
				isValid = this.modules.conditionsValidator.validateAllConditions();

				// Show notification if validation failed
				if ( ! isValid ) {
					if ( window.SCD && window.SCD.Shared && window.SCD.Shared.NotificationService ) {
						window.SCD.Shared.NotificationService.error(
							'Please fix the validation errors in your product filters before proceeding.',
							5000
						);
					}
				}
			}

			// Return promise that resolves to validation result
			return $.Deferred().resolve( isValid ).promise();
		}
	} );

	// =========================================================================
	// ERROR DISPLAY METHODS
	// =========================================================================

	$.extend( SCD.Steps.ProductsOrchestrator.prototype, {

		// Note: showErrors() is inherited from StepPersistence mixin

		/**
		 * Show field error using centralized ValidationError component
		 * @param {jQuery|string} fieldOrName - Field element or field name
		 * @param {Array|string} errorMessages - Error messages
		 */
	// Note: showFieldError(), clearFieldError(), and _findField() are inherited from BaseOrchestrator

		/**
		 * Clear field error using centralized ValidationError component
		 * @param {jQuery|string} fieldOrName - Field element or field name
		 */

		/**
		 * Set initial state
		 * @private
		 */
		_setInitialState: function() {
			var currentState = this.modules.state ? this.modules.state.getState() : {};
			var selectedType = currentState.productSelectionType || 'all_products';

			// Use snake_case for DOM selector since that's what the HTML uses
			var selector = '[name="product_selection_type"]';
			var $selectedInput = $( selector + '[value="' + selectedType + '"]' );

			if ( $selectedInput.length ) {
				// Ensure the radio button is checked
				$selectedInput.prop( 'checked', true );

				this.updateSectionVisibility( selectedType );

				$( '.scd-card-option' ).removeClass( 'scd-card-option--selected' );
				$selectedInput.closest( '.scd-card-option' ).addClass( 'scd-card-option--selected' );
			} else {
				// If specific value not found, default to all_products
				var $defaultInput = $( selector + '[value="all_products"]' );
				if ( $defaultInput.length ) {
					$defaultInput.prop( 'checked', true );
					this.updateSectionVisibility( 'all_products' );
					$defaultInput.closest( '.scd-card-option' ).addClass( 'scd-card-option--selected' );
				}
			}
		},

		/**
		 * Find field element by name
		 * @private
		 * @param {string} fieldName - Field name
		 * @returns {jQuery}
		 */

	} );

	// =========================================================================
	// LIFECYCLE METHODS
	// =========================================================================

	$.extend( SCD.Steps.ProductsOrchestrator.prototype, {

		/**
		 * Update conditions summary panel
		 *
		 * @since 1.0.0
		 * @param {Array} conditions Current conditions array
		 * @returns {void}
		 */
		updateConditionsSummary: function( conditions ) {
			var $summary = $( '.scd-conditions-summary' );
			if ( ! $summary.length ) {
				return;
			}

			conditions = conditions || [];
			var conditionCount = conditions.length;

			// Show/hide summary panel
			if ( conditionCount > 0 ) {
				$summary.show();
			} else {
				$summary.hide();
				return;
			}

			// Update logic display
			var logic = $( '[name="conditions_logic"]:checked' ).val() || 'all';
			var logicText = 'all' === logic ? 'AND (all must match)' : 'OR (any can match)';
			$summary.find( '.scd-summary-logic-value' ).text( logicText );

			// Update condition count
			$summary.find( '.scd-condition-count' ).text( conditionCount );

			// Get condition types and operator mappings
			var conditionTypes = window.scdProductsState && window.scdProductsState.condition_types || {};
			var operatorMappings = window.scdProductsState && window.scdProductsState.operator_mappings || {};

			// Build summary list
			var $summaryList = $summary.find( '.scd-summary-list' );
			$summaryList.empty();

			for ( var i = 0; i < conditions.length; i++ ) {
				var cond = conditions[i];
				var typeLabel = this._getConditionTypeLabel( cond.type, conditionTypes );
				var operatorLabel = this._getOperatorLabel( cond.operator, operatorMappings );
				var mode = cond.mode || 'include';

				var summaryText = typeLabel + ' ' + operatorLabel;

				if ( cond.value ) {
					summaryText += ' <span class="scd-summary-value">' + this._escapeHtml( cond.value ) + '</span>';
				}

				if ( cond.value2 && -1 !== [ 'between', 'not_between' ].indexOf( cond.operator ) ) {
					summaryText += ' and <span class="scd-summary-value">' + this._escapeHtml( cond.value2 ) + '</span>';
				}

				var iconName = 'include' === mode ? 'check' : 'close';
				var iconHtml = SCD.IconHelper ? SCD.IconHelper.get( iconName, { size: 16 } ) : '<span class="scd-icon scd-icon-' + iconName + '"></span>';
				var modeClass = 'include' === mode ? 'summary-include' : 'summary-exclude';

				var $li = $( '<li>' )
					.addClass( modeClass )
					.html( iconHtml + '<span class="scd-summary-item-text">' + summaryText + '</span>' );

				$summaryList.append( $li );
			}

			// Show warning if at limit
			var $warning = $summary.find( '.scd-summary-warning' );
			if ( conditionCount >= 20 ) {
				if ( ! $warning.length ) {
					var warningIcon = SCD.IconHelper ? SCD.IconHelper.warning( { size: 16 } ) : '<span class="scd-icon scd-icon-warning"></span>';
					$warning = $( '<div class="scd-summary-warning">' + warningIcon + '<span>Maximum condition limit reached (20). Remove conditions to add more.</span></div>' );
					$summary.find( '.scd-summary-count' ).after( $warning );
				}
			} else {
				$warning.remove();
			}

			// Bind toggle handler if not already bound
			var $toggleBtn = $summary.find( '.scd-toggle-summary' );
			if ( ! $toggleBtn.data( 'bound' ) ) {
				$toggleBtn.data( 'bound', true ).on( 'click.scd-summary', function() {
					$summary.toggleClass( 'collapsed' );
				} );
			}
		},

		/**
		 * Get condition type label
		 *
		 * @since 1.0.0
		 * @param {string} type Condition type
		 * @param {object} conditionTypes Condition types object
		 * @returns {string} Type label
		 */
		_getConditionTypeLabel: function( type, conditionTypes ) {
			for ( var groupKey in conditionTypes ) {
				var group = conditionTypes[groupKey];
				if ( group.options && group.options[type] ) {
					return group.options[type];
				}
			}
			return type;
		},

		/**
		 * Get operator label
		 *
		 * @since 1.0.0
		 * @param {string} operator Operator value
		 * @param {object} operatorMappings Operator mappings object
		 * @returns {string} Operator label
		 */
		_getOperatorLabel: function( operator, operatorMappings ) {
			for ( var category in operatorMappings ) {
				var operators = operatorMappings[category];
				if ( operators && operators[operator] ) {
					return operators[operator];
				}
			}
			return operator;
		},

		/**
		 * Escape HTML for safe display
		 *
		 * @since 1.0.0
		 * @param {string} text Text to escape
		 * @returns {string} Escaped text
		 */
		_escapeHtml: function( text ) {
			var map = {
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				'"': '&quot;',
				"'": '&#039;'
			};
			return String( text ).replace( /[&<>"']/g, function( m ) {
				return map[m];
			} );
		},

		/**
		 * Clean up when leaving step
		 *
		 * @since 1.0.0
		 * @returns {void}
		 */
		destroy: function() {
			this.setLoading( false );

			// Unbind events
			this.unbindEvents();

			// Destroy modules
			if ( this.modules ) {
				// Destroy Picker instance (handles both category and product selection)
				if ( this.modules.picker && 'function' === typeof this.modules.picker.destroy ) {
					this.modules.picker.destroy();
				}

				// Destroy other modules
				$.each( this.modules, function( name, module ) {
					if ( module && 'function' === typeof module.destroy ) {
						module.destroy();
					}
				} );

				this.modules = {};
			}

			this.$container = null;

			this.initialized = false;

			// Call parent destroy
			if ( 'function' === typeof SCD.Shared.BaseOrchestrator.prototype.destroy ) {
				SCD.Shared.BaseOrchestrator.prototype.destroy.call( this );
			}
		}
	} );

} )( jQuery );
