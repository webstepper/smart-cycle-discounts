/**
 * Products Orchestrator Module
 *
 * Manages the products step with modular organization following SOLID principles
 *
 * @package SmartCycleDiscounts
 * @since 1.0.0
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

			// Initialize custom properties
			this._boundHandlers = {};

			// Initialize modules (synchronous)
			this.initializeModules();

			// Initialize UI components (async) and wait for completion
			var uiPromise = this.initializeUI();

			// Return promise that resolves when UI is ready
			return $.when( uiPromise ).then( function() {
				// Set initial state
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
		 * Initialize modules (State, API, Selector, Filter, CategoryFilter, TomSelect)
		 * 
		 * @since 1.0.0
		 * @private
		 * @returns {void}
		 */
		initializeModules: function() {
			// State management module (required)
			if ( ! this.modules.state && SCD.Modules.Products.State ) {
				this.modules.state = new SCD.Modules.Products.State();
				if ( 'function' === typeof this.modules.state.init ) {
					this.modules.state.init();
				}
			}

			// API module (required)
			if ( ! this.modules.api && SCD.Modules.Products.API ) {
				this.modules.api = new SCD.Modules.Products.API( {
					ajaxUrl: window.scdAjax && window.scdAjax.ajaxUrl || '',
					nonce: window.scdAjax && window.scdAjax.nonce || ''
				} );

				// Set API reference on state module so it can make API calls
				if ( this.modules.state && 'function' === typeof this.modules.state.setApi ) {
					this.modules.state.setApi( this.modules.api );
				}
			}

			// Selector module
			if ( ! this.modules.selector && SCD.Modules.Products.Selector ) {
				this.modules.selector = new SCD.Modules.Products.Selector( this.modules.state, this.modules.api );
				if ( 'function' === typeof this.modules.selector.init ) {
					this.modules.selector.init();
				}
			}

			// Filter module
			if ( !this.modules.filter && SCD.Modules.Products.Filter ) {
				this.modules.filter = new SCD.Modules.Products.Filter( this.modules.state, this.modules.api );
				if ( this.modules.filter.init ) {
					this.modules.filter.init();
				}
				// Register instance for complex field handling
				if ( 'function' === typeof this.registerComplexFieldHandler ) {
					this.registerComplexFieldHandler( 'SCD.Modules.Products.Filter', this.modules.filter );
				}
			}

			// Category Filter module
			if ( !this.modules.categoryFilter && SCD.Modules.Products.CategoryFilter ) {
				this.modules.categoryFilter = new SCD.Modules.Products.CategoryFilter( this.modules.state, this.modules.api );
				if ( this.modules.categoryFilter.init ) {
					this.modules.categoryFilter.init();
				}
				// Register instance for complex field handling
				if ( 'function' === typeof this.registerComplexFieldHandler ) {
					this.registerComplexFieldHandler( 'SCD.Modules.Products.CategoryFilter', this.modules.categoryFilter );
				}
			}

			// Tom Select module (depends on CategoryFilter for category events)
			if ( !this.modules.tomSelect && SCD.Modules.Products.TomSelect ) {
				this.modules.tomSelect = new SCD.Modules.Products.TomSelect( this.modules.state, this.modules.api );
				if ( this.modules.tomSelect.init ) {
					this.modules.tomSelect.init();
				}
				// Register instance for complex field handling
				if ( 'function' === typeof this.registerComplexFieldHandler ) {
					this.registerComplexFieldHandler( 'SCD.Modules.Products.TomSelect', this.modules.tomSelect );
				}
			}
		},

		/**
		 * Initialize UI components
		 * @private
		 * @returns {Promise}
		 */
		initializeUI: function() {
			var self = this;
			var promises = [];

			// Initialize tooltips using safe wrapper
			this.safeTooltipInit();

			// Initialize Category Filter Tom Select
			// Note: This initializes empty on first load. If restoring data, persistence service will call setValue
			// which triggers pending restoration pattern (setValue stores in pendingCategoryIds before init completes)
			if ( this.modules.categoryFilter && 'function' === typeof this.modules.categoryFilter.initializeCategorySelect ) {
				var categoryPromise = this.modules.categoryFilter.initializeCategorySelect()
					.catch( function( _error ) {
						SCD.ErrorHandler.handle( _error, 'products-init-category-select', SCD.ErrorHandler.SEVERITY.HIGH );
						throw _error;
					} );
				promises.push( categoryPromise );
			}

			// Initialize Product Tom Select only if selection type is specific_products
			var currentState = this.modules.state ? this.modules.state.getState() : {};
			if ( 'specific_products' === currentState.productSelectionType ) {
				if ( this.modules.tomSelect && 'function' === typeof this.modules.tomSelect.initializeProductSearch ) {
					var productPromise = this.modules.tomSelect.initializeProductSearch()
						.catch( function( error ) {
							SCD.ErrorHandler.handle( error, 'products-init-product-search', SCD.ErrorHandler.SEVERITY.HIGH );
							throw error;
						} );
					promises.push( productPromise );
				}
			}

			// Return a promise that resolves when all UI components are ready
			return Promise.all( promises ).then( function() {
				return self;
			} );
		},

		/**
		 * Custom initialization hook
		 */
		onInit: function() {
			// Custom initialization if needed
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
				autoSave: true,
				autoSaveDelay: 2000,
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

			// Create bound handlers for proper cleanup
			this._boundHandlers.selectionTypeChange = function() {
				var selectedType = $( this ).val();
				self.updateSectionVisibility( selectedType );
				self.clearSelectionsForType( selectedType );

				// Update state
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
					self.modules.state.setState( { conditionsLogic: $( this ).val() } );
				}
			};
			this.$container.on( 'change.scd-products', '[name="conditions_logic"]', this._boundHandlers.conditionsLogicChange );

			// Add condition button
			this._boundHandlers.addCondition = function( e ) {
				e.preventDefault();
				self.handleAddCondition();
			};
			this.$container.on( 'click.scd-products', '.scd-add-condition', this._boundHandlers.addCondition );

			// Remove condition button
			this._boundHandlers.removeCondition = function( e ) {
				e.preventDefault();
				self.handleRemoveCondition( $( this ).closest( '.scd-condition-row' ) );
			};
			this.$container.on( 'click.scd-products', '.scd-remove-condition', this._boundHandlers.removeCondition );

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

			// Clear bound handlers
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

			// State changes
			if ( 'function' === typeof this.bindCustomEvent ) {
				this.bindCustomEvent( 'scd:products:state:changed', function( event, data ) {
					// Check if conditions property changed
					if ( data && 'conditions' === data.property ) {
						self.updateConditions();
					}
				} );
			}

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
			
			// Hide all conditional sections first
			this.$container.find( '.scd-random-count, .scd-specific-products, .scd-smart-criteria' ).each( function() {
				$( this ).removeClass( 'scd-active-section' ).hide();
			} );

			// Show the appropriate section based on selection type
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

			// Update state
			if ( this.modules.state ) {
				this.modules.state.setState( { productSelectionType: selectionType } );
			}

			// Enable/disable Advanced Filters (conditions) based on selection type
			// Advanced Filters are only available for 'all_products' and 'random_products'
			var $conditionsSection = this.$container.find( '.scd-conditions-section' );
			if ( 'all_products' === selectionType || 'random_products' === selectionType ) {
				$conditionsSection.removeClass( 'scd-disabled' );
			} else {
				$conditionsSection.addClass( 'scd-disabled' );
			}

			// Initialize Tom Select if needed
			if ( 'specific_products' === selectionType && this.modules.tomSelect ) {
				// Delay to ensure DOM is ready
				setTimeout( function() {
					if ( self.modules.tomSelect && 'function' === typeof self.modules.tomSelect.initProductSearch ) {
						self.modules.tomSelect.initProductSearch();
					}
				}, 100 );
			}
		},

		/**
		 * Update products list display
		 */
		updateProductsList: function() {
			if ( this.modules && this.modules.selector && this.modules.selector.updateProductCounts ) {
				this.modules.selector.updateProductCounts();
			}
		},

		/**
		 * Update conditions display
		 */
		updateConditions: function() {
			if ( this.modules && this.modules.filter && this.modules.filter.render ) {
				this.modules.filter.render();
			}
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
			// After parent handles field population, get the transformed state
			var transformedData = this.modules.state ? this.modules.state.getState() : data;

			// Handle selection type UI update - use camelCase from state
			var selectionType = transformedData.productSelectionType || 'all_products';
			this.updateSectionVisibility( selectionType );
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

			// Clear non-relevant fields based on selection type
			switch ( selectionType ) {
				case 'specific_products':
					delete clearData.randomCount;
					delete clearData.smartCriteria;
					delete clearData.conditions;
					break;
				case 'random_products':
					delete clearData.productIds;
					delete clearData.smartCriteria;
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
				type: '',
				operator: '',
				value: '',
				value2: '',
				mode: 'include'
			};

			// Create new array to avoid mutation
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
				// Create new array without the removed condition
				var updatedConditions = [];
				for ( var i = 0; i < currentConditions.length; i++ ) {
					if ( i !== index ) {
						updatedConditions.push( currentConditions[i] );
					}
				}
				this.modules.state.setState( { conditions: updatedConditions } );
			}
		}
	} );

	// =========================================================================
	// VALIDATION METHODS
	// =========================================================================

	$.extend( SCD.Steps.ProductsOrchestrator.prototype, {

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
			
			// Check conditions array
			var hasConditions = data.conditions && Array.isArray( data.conditions ) && data.conditions.length > 0;
			
			// Check categories
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
			// Get the current state to check what should be selected
			var currentState = this.modules.state ? this.modules.state.getState() : {};
			var selectedType = currentState.productSelectionType || 'all_products';

			// Use snake_case for DOM selector since that's what the HTML uses
			var selector = '[name="product_selection_type"]';
			var $selectedInput = $( selector + '[value="' + selectedType + '"]' );

			if ( $selectedInput.length ) {
				// Ensure the radio button is checked
				$selectedInput.prop( 'checked', true );

				// Update section visibility
				this.updateSectionVisibility( selectedType );

				// Update the parent card's selected state
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
		 * Clean up when leaving step
		 * 
		 * @since 1.0.0
		 * @returns {void}
		 */
		destroy: function() {
			// Hide tooltip
			this.hideTooltip();

			// Remove loading state
			this.setLoading( false );

			// Unbind events
			this.unbindEvents();

			// Destroy modules
			if ( this.modules ) {
				// Destroy Tom Select instances
				if ( this.modules.tomSelect && 'function' === typeof this.modules.tomSelect.destroy ) {
					this.modules.tomSelect.destroy();
				}

				// Destroy CategoryFilter instance
				if ( this.modules.categoryFilter && 'function' === typeof this.modules.categoryFilter.destroy ) {
					this.modules.categoryFilter.destroy();
				}

				// Destroy other modules
				$.each( this.modules, function( name, module ) {
					if ( module && 'function' === typeof module.destroy ) {
						module.destroy();
					}
				} );

				this.modules = {};
			}

			// Clear reference
			this.$container = null;

			// Reset initialization flag
			this.initialized = false;

			// Call parent destroy
			if ( 'function' === typeof SCD.Shared.BaseOrchestrator.prototype.destroy ) {
				SCD.Shared.BaseOrchestrator.prototype.destroy.call( this );
			}
		}
	} );

} )( jQuery );
