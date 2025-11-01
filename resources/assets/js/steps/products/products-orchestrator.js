/**
 * Products Orchestrator Module
 *
 * Manages the products step with modular organization following SOLID principles
 *
 * @package SmartCycleDiscounts
 * @since 1.0.0
 *
 * CACHE BUST: 2025-10-25-DEBUG-V3
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
				console.error('[Products] ========== INIT FAILED ==========');
				console.error('[Products] Initialization error:', error);
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

			// State management module (required) - Pure data storage, no business logic
			if ( ! this.modules.state && SCD.Modules.Products.State ) {
				this.modules.state = new SCD.Modules.Products.State();
				if ( 'function' === typeof this.modules.state.init ) {
					this.modules.state.init();
				}

				// Load existing step data from wizard state manager (for edit mode)

				if ( this.wizard && this.wizard.modules && this.wizard.modules.stateManager ) {
					var stateManager = this.wizard.modules.stateManager;
					var allStepData = stateManager.get( 'stepData' );

					var stepData = allStepData ? allStepData.products : null;

					if ( stepData && 'function' === typeof this.modules.state.setState ) {
						this.modules.state.setState( stepData );
					} else {
					}
				} else {
				}

				// Register state instance for complex field handling
				if ( 'function' === typeof this.registerComplexFieldHandler ) {
					this.registerComplexFieldHandler( 'products.state', this.modules.state );
				}
			}

			// API module (required)
			if ( ! this.modules.api && SCD.Modules.Products.API ) {
				this.modules.api = new SCD.Modules.Products.API( {
					ajaxUrl: window.scdAjax && window.scdAjax.ajaxUrl || '',
					nonce: window.scdAjax && window.scdAjax.nonce || ''
				} );
			}

			// Unified Picker module (replaces CategoryFilter + TomSelect)
			if ( ! this.modules.picker && SCD.Modules.Products.Picker ) {
				this.modules.picker = new SCD.Modules.Products.Picker( this.modules.state, this.modules.api );
				// Register instance for complex field handling
				if ( 'function' === typeof this.registerComplexFieldHandler ) {
					this.registerComplexFieldHandler( 'SCD.Modules.Products.Picker', this.modules.picker );
				}
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

			// Initialize unified Picker (handles both category and product selection)
			if ( this.modules.picker && 'function' === typeof this.modules.picker.init ) {
				return this.modules.picker.init()
					.then( function() {
						return self;
					} )
					.catch( function( error ) {
						console.error('[Products] Picker initialization FAILED:', error);
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

			// Note: Conditions are handled directly by PHP template and field definitions

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
		 * Moved from products-selector.js (deleted in Phase 2)
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

			// Remove empty/invalid values
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

			// Update state
			this.modules.state.setState( { categoryIds: categories } );

			// Check if categories actually changed
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

			// Validate conditions is array
			var validConditions = Array.isArray( conditions ) ? conditions : [];

			// Update state with restored conditions
			// State module will trigger UI re-render via existing mechanisms
			this.modules.state.setState( { conditions: validConditions } );
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

			// Check for empty selection (matches server-side required validation)
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
			// Remove loading state
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
