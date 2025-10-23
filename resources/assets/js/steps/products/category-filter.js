/**
 * Category Filter Module
 *
 * Handles category filtering functionality for products step.
 * Manages category selection with "All Categories" exclusive logic.
 *
 * @param $
 * @package SmartCycleDiscounts
 * @since 1.0.0
 */

( function( $ ) {
	'use strict';

	// Register module
	SCD.Utils.registerModule( 'SCD.Modules.Products', 'CategoryFilter', function( state, api ) {
		if ( !state ) {
			throw new Error( 'CategoryFilter module requires state dependency' );
		}
		if ( !api ) {
			throw new Error( 'CategoryFilter module requires api dependency' );
		}

		this.state = state;
		this.api = api;
		this.tomSelect = null;
		this.initialized = false;

		// Operation locks to prevent race conditions
		this.locks = {
			categoryUpdate: false,
			initialization: false
		};

		// Cache for performance
		this.cache = {
			loadedCategories: new Map(),
			totalProductCount: 0
		};

		// Timers
		this.timers = {
			categoryChange: null
		};

		// Target categories for initialization
		this.targetCategories = [ 'all' ];

		// Pending restoration (for setValue calls before Tom Select initialized)
		this.pendingCategoryIds = null;

		// Initialize event manager
		this.initEventManager();

		// Check dependencies
		this.checkDependencies();
	} );

	// Extend event manager mixin
	SCD.Utils.extend( SCD.Modules.Products.CategoryFilter.prototype, SCD.Mixins.EventManager );

	SCD.Modules.Products.CategoryFilter.prototype = SCD.Utils.extend( SCD.Modules.Products.CategoryFilter.prototype, {
		constructor: SCD.Modules.Products.CategoryFilter,

		/**
		 * Check dependencies availability
		 */
		checkDependencies: function() {
			var deps = {
				tomSelectBase: 'undefined' !== typeof SCD.Shared.TomSelectBase,
				jquery: 'undefined' !== typeof $,
				state: !!this.state,
				api: !!this.api
			};

			// Debug: Log missing dependencies
			if ( window.scdDebugTomSelect ) {
				if ( 'undefined' === typeof SCD.Shared ) {
				} else if ( 'undefined' === typeof SCD.Shared.TomSelectBase ) {
				}
			}

			return Object.values( deps ).every( function( d ) { return true === d; } );
		},

		/**
		 * Initialize module
		 */
		init: function() {
			var self = this;

			if ( this.initPromise ) {
				return this.initPromise;
			}

			if ( this.initialized ) {
				return Promise.resolve();
			}

			this.initPromise = new Promise( function( resolve, reject ) {
				try {
					if ( self.locks.initialization ) {
						resolve();
						return;
					}
					self.locks.initialization = true;

					// Wait for dependencies with retry mechanism
					self.waitForDependencies( 0 ).then( function() {
						self.bindEvents();
						self.initialized = true;
						self.locks.initialization = false;
						resolve();
					} ).catch( function( error ) {
						self.locks.initialization = false;
						self.handleInitError( error );
						reject( error );
					} );

				} catch ( error ) {
					self.locks.initialization = false;
					self.handleInitError( error );
					reject( error );
				}
			} );

			return this.initPromise;
		},

		/**
		 * Wait for dependencies with retry mechanism
		 */
		waitForDependencies: function( attempt ) {
			var self = this;
			var maxAttempts = 10;
			var retryDelay = 100; // ms

			return new Promise( function( resolve, reject ) {
				if ( self.checkDependencies() ) {
					resolve();
					return;
				}

				if ( attempt >= maxAttempts ) {
					reject( new Error( 'CategoryFilter dependencies not available after ' + maxAttempts + ' attempts' ) );
					return;
				}

				setTimeout( function() {
					self.waitForDependencies( attempt + 1 ).then( resolve ).catch( reject );
				}, retryDelay );
			} );
		},

		/**
		 * Handle initialization errors
		 */
		handleInitError: function( error ) {
			SCD.ErrorHandler.handle( error, 'category-filter-init', SCD.ErrorHandler.SEVERITY.HIGH );

			if ( SCD.Shared && SCD.Shared.NotificationService ) {
				SCD.Shared.NotificationService.error( 'Category filter failed to load. Please refresh the page.' );
			}
		},

		/**
		 * Initialize category select field
		 */
		initializeCategorySelect: function() {
			var self = this;

			return this.init().then( function() {
				try {
					var $categorySelect = $( '#scd-campaign-categories' );

					if ( !$categorySelect.length ) {
						return;
					}

					if ( $categorySelect[0].tomselect ) {
						return;
					}

					if ( 'undefined' === typeof SCD.Shared.TomSelectBase ) {
						if ( window.scdDebugTomSelect ) {
							console.error( '[CategoryFilter] TomSelectBase not loaded' );
						}
						return;
					}

					// Create Tom Select instance using base class
					var config = self.getCategorySelectConfig();
					self.tomSelect = new SCD.Shared.TomSelectBase( $categorySelect[0], config );

					return self.tomSelect.init().then( function() {
						self._categoriesReady = true;
						return self;
					} );

				} catch ( error ) {
					SCD.ErrorHandler.handle( error, 'category-filter-init-select', SCD.ErrorHandler.SEVERITY.HIGH );
				}
			} );
		},

		/**
		 * Get category select configuration
		 */
		getCategorySelectConfig: function() {
			var self = this;

			return {
				placeholder: 'Filter by categories...',
				preload: false, // Disabled to prevent race condition - we load manually when needed
				sortField: [ { field: '$order' }, { field: 'text' } ],

				load: function( query, callback ) {
					self.loadCategories( query, callback );
				},

				render: {
					option: function( data, escape ) {
						return self.renderCategoryOption( data, escape );
					},
					item: function( data, escape ) {
						return '<div>' + escape( data.text ) + '</div>';
					},
					no_results: function( _data, _escape ) {
						// Check if query is too short (minimum 3 characters required)
						var currentQuery = this.lastQuery || '';
						if ( currentQuery.length > 0 && currentQuery.length < 3 ) {
							return '<div class="no-results">Type at least 3 characters to search...</div>';
						}
						return '<div class="no-results">No categories found</div>';
					}
				},

				// Override base event handlers
				onChange: function( value ) {
					self.handleCategoryFilterChange( value );
				},

				onItemAdd: function( value, item ) {
					self.handleCategoryItemAdd( value, item );
				},

				onItemRemove: function( value ) {
					self.handleCategoryItemRemove( value );
				},

				onInitialize: function() {
					self.onCategorySelectInitialize.call( this, self );
				},

				onDropdownOpen: function( dropdown ) {
					self.onCategoryDropdownOpen( dropdown );
				}
			};
		},

		/**
		 * Handle category Tom Select initialization
		 */
		onCategorySelectInitialize: function( self ) {

			// Add "All Categories" option immediately
			this.addOption( {
				value: 'all',
				text: 'All Categories',
				count: 0,
				level: 0,
				$order: 0
			} );

			// Check for pending restoration (from setValue calls before init)
			if ( self.pendingCategoryIds ) {
				var pendingIds = self.pendingCategoryIds;
				self.pendingCategoryIds = null;

				// Load options and set values as part of initialization
				self.ensureCategoryOptionsLoaded( pendingIds ).then( function() {
					self.setCategoriesOnInstance( pendingIds );
				} );
			} else {
				// No pending restoration - read from state (which has default ['all'])
				var stateCategories = self.state.getState().categoryIds || [ 'all' ];
				this.setValue( stateCategories );
			}

		},

		/**
		 * Handle category dropdown open
		 */
		onCategoryDropdownOpen: function( _dropdown ) {
			// Manual preload: Load initial categories if dropdown has no options yet
			// (except for the "All Categories" option which is always present)
			if ( this.tomSelect && this.tomSelect.instance ) {
				var optionCount = Object.keys( this.tomSelect.instance.options ).length;
				// Only "All Categories" option exists (count = 1) - need to load others
				if ( optionCount <= 1 ) {
					// Trigger initial load with empty query to load all categories
					this.tomSelect.instance.load( '' );
				}
			}
		},

		/**
		 * Handle category item addition - implement exclusive "All Categories" logic
		 *
		 * Lock purpose: Prevent UI manipulations (clear/addItem/removeItem) from triggering
		 * onChange events during the exclusive selection logic. After lock released,
		 * manually call processCategoryFilterChange() to update state.
		 */
		handleCategoryItemAdd: function( value, _item ) {
			if ( !this.tomSelect || !this.tomSelect.instance || this.locks.categoryUpdate ) {
				return;
			}

			this.locks.categoryUpdate = true;

			try {
				var currentValues = this.tomSelect.getValue();
				var values = Array.isArray( currentValues ) ? currentValues : ( currentValues ? [ currentValues ] : [] );

				// Handle "All Categories" exclusive selection
				if ( 'all' === value && values.length > 1 ) {
					// User selected "All Categories" - clear all other selections
					this.tomSelect.clear();
					this.tomSelect.instance.addItem( 'all' );
				} else if ( 'all' !== value && values.indexOf( 'all' ) !== -1 ) {
					// User selected a specific category while "All Categories" is selected
					// Remove "All Categories"
					this.tomSelect.instance.removeItem( 'all' );
				}

			} finally {
				this.locks.categoryUpdate = false;
			}

			// Now process the change
			this.processCategoryFilterChange();
		},

		/**
		 * Handle category item removal - ensure "All Categories" is selected if all removed
		 *
		 * Lock purpose: Prevent addItem('all') from triggering onChange when auto-selecting
		 * "All Categories" after user removed all specific categories.
		 */
		handleCategoryItemRemove: function( _value ) {
			if ( !this.tomSelect || !this.tomSelect.instance || this.locks.categoryUpdate ) {
				return;
			}

			// Process the change first
			this.processCategoryFilterChange();

			// Then check if we need to add "All Categories"
			var currentValues = this.tomSelect.getValue();
			var values = Array.isArray( currentValues ) ? currentValues : ( currentValues ? [ currentValues ] : [] );

			// If no categories are selected, automatically select "All Categories"
			if ( values.length === 0 ) {
				this.locks.categoryUpdate = true;
				try {
					this.tomSelect.instance.addItem( 'all' );
				} finally {
					this.locks.categoryUpdate = false;
				}
			}
		},

		/**
		 * Handle category filter change
		 */
		handleCategoryFilterChange: function( value ) {

			// Prevent concurrent updates
			if ( this.locks.categoryUpdate ) {
				return;
			}

			var self = this;

			// Debounce rapid changes
			clearTimeout( this.timers.categoryChange );
			this.timers.categoryChange = setTimeout( function() {
				self.processCategoryFilterChange( value );
			}, SCD.Shared.TomSelectBase.CONFIG.SEARCH.THROTTLE_MS );
		},

		/**
		 * Process category filter change with validation
		 */
		processCategoryFilterChange: function( _value ) {
			// Prevent recursive updates from our own category manipulation
			if ( this.locks.categoryUpdate ) {
				return;
			}

			this.locks.categoryUpdate = true;

			try {
				// Get actual current values from Tom Select instance
				var currentCategoryValues = this.tomSelect ? this.tomSelect.getValue() : [];
				var newCategories = Array.isArray( currentCategoryValues ) ? currentCategoryValues : ( currentCategoryValues ? [ currentCategoryValues ] : [] );

				if ( newCategories.length === 0 ) {
					newCategories = [ 'all' ];
				}

				var oldCategories = this.state.getState().categoryIds || [ 'all' ];

				// Check if there's an actual change
				if ( !this.hasCategoryChanges( newCategories, oldCategories ) ) {
					this.locks.categoryUpdate = false;
					return;
				}

				// Update state
				this.state.setState( { categoryIds: newCategories } );

				// Sync with original select element for form submission
				this.syncOriginalSelect( newCategories );

				// Emit category change event for other modules
				var eventData = {
					categories: newCategories,
					previousCategories: oldCategories,
					source: 'user-action'
				};


				this.triggerCustomEvent( 'scd:categories:changed', [ eventData ] );

				// Refresh category dropdown visibility
				if ( this.tomSelect && this.tomSelect.instance && this.tomSelect.isOpen() ) {
					this.tomSelect.refreshOptions( false );
				}

			} catch ( error ) {
				SCD.ErrorHandler.handle( error, 'category-filter-process-change' );
			} finally {
				this.locks.categoryUpdate = false;
			}
		},

		/**
		 * Load categories
		 */
		loadCategories: function( query, callback ) {
			var self = this;

			// Require minimum 3 characters for search (or empty for all)
			// Prevents rapid-fire requests as user types "Ca" -> "Cat" -> "Cats"
			if ( query && query.length > 0 && query.length < 3 ) {
				callback( [] );
				return;
			}

			this.api.searchCategories( { search: query } )
				.done( function( response ) {
					self.handleCategoryLoadSuccess( response, query, callback );
				} )
				.fail( function( _xhr ) {
					if ( SCD.Shared && SCD.Shared.NotificationService ) {
						SCD.Shared.NotificationService.error( 'Failed to load categories.' );
					}
					callback( [] );
				} );
		},

		/**
		 * Handle successful category load
		 */
		handleCategoryLoadSuccess: function( response, query, callback ) {
			try {
				var categoriesData = this.extractCategoriesFromResponse( response );

				if ( !query ) {
					this.calculateTotalProductCount( categoriesData );
				}

				var categories = this.buildCategoriesArray( categoriesData );

				// Cache categories
				var self = this;
				categories.forEach( function( cat ) {
					self.cache.loadedCategories.set( cat.value, cat );
				} );

				callback( categories );

			} catch ( error ) {
				SCD.ErrorHandler.handle( error, 'category-filter-handle-success' );
				callback( [] );
			}
		},

		/**
		 * Extract categories from response
		 */
		extractCategoriesFromResponse: function( response ) {
			if ( response.data && response.data.categories ) {
				return response.data.categories;
			} else if ( response.categories ) {
				return response.categories;
			}
			return [];
		},

		/**
		 * Calculate total product count from categories
		 */
		calculateTotalProductCount: function( categoriesData ) {
			var total = 0;

			if ( Array.isArray( categoriesData ) ) {
				categoriesData.forEach( function( category ) {
					if ( 'all' !== category.value && category.count !== undefined ) {
						total += parseInt( category.count, 10 ) || 0;
					}
				} );
			}

			this.cache.totalProductCount = total;
		},

		/**
		 * Build categories array with "All Categories" option
		 */
		buildCategoriesArray: function( categoriesData ) {
			var categories = [ {
				value: 'all',
				text: 'All Categories',
				count: this.cache.totalProductCount || 0,
				level: 0,
				$order: 0
			} ];

			if ( Array.isArray( categoriesData ) ) {
				categoriesData.forEach( function( cat, index ) {
					if ( 'all' !== cat.value ) {
						cat.$order = index + 1;
						categories.push( cat );
					}
				} );
			}

			return categories;
		},

		/**
		 * Render category option
		 */
		renderCategoryOption: function( data, escape ) {
			var isAllCategories = 'all' === data.value;
			var currentValues = this.tomSelect ? this.tomSelect.getValue() : [];
			var normalizedValues = Array.isArray( currentValues ) ? currentValues : [ currentValues || [] ];

			if ( isAllCategories && normalizedValues.indexOf( 'all' ) !== -1 ) {
				return '';
			}

			var prefix = '';
			if ( data.level && data.level > 0 ) {
				prefix = '— '.repeat( data.level );
			}

			var countHtml = '';
			if ( data.count !== undefined ) {
				var productWord = 1 === data.count ? 'product' : 'products';
				countHtml = ' <span class="category-count">(' + escape( data.count ) + ' ' + escape( productWord ) + ')</span>';
			}

			return '<div class="category-option' + ( data.level ? ' level-' + data.level : '' ) + '">' +
				prefix + escape( data.text ) + countHtml +
				'</div>';
		},

		/**
		 * Sync with original select element
		 */
		syncOriginalSelect: function( values ) {
			var $originalSelect = $( '#scd-campaign-categories' );

			if ( $originalSelect.length ) {
				// Clear current options
				$originalSelect.empty();

				// Add selected values as options
				if ( values && values.length > 0 ) {
					values.forEach( function( value ) {
						var option = new Option( value, value, true, true );
						$originalSelect.append( option );
					} );
				}

				// Don't trigger change event - it can cause feedback loops with state management
				// The Tom Select UI and state are already in sync at this point
				// $originalSelect.trigger( 'change' );
			}
		},

		/**
		 * Check if categories have changed
		 */
		hasCategoryChanges: function( newCategories, currentCategories ) {
			if ( newCategories.length !== currentCategories.length ) {
				return true;
			}

			var sortedNew = newCategories.slice().sort();
			var sortedCurrent = currentCategories.slice().sort();

			return JSON.stringify( sortedNew ) !== JSON.stringify( sortedCurrent );
		},

		/**
		 * Get selected categories
		 */
		getSelectedCategories: function() {
			var value;
			if ( this.tomSelect ) {
				value = this.tomSelect.getValue();
				return value;
			}

			// Fallback to state
			value = this.state.getState().categoryIds || [ 'all' ];
			return value;
		},

		/**
		 * Set categories
		 */
		setCategories: function( categoryIds ) {

			categoryIds = Array.isArray( categoryIds ) ? categoryIds : ( categoryIds ? [ categoryIds ] : [] );

			// Empty array means "all categories"
			if ( categoryIds.length === 0 ) {
				categoryIds = [ 'all' ];
			}

			// Ensure categories are strings
			categoryIds = categoryIds.map( function( id ) {
				return String( id );
			} );


			// Store the target categories for initialization
			this.targetCategories = categoryIds;

			var self = this;

			// If Tom Select is already initialized, ensure options are loaded then set values
			if ( this.tomSelect && this.tomSelect.isReady() ) {
				return this.ensureCategoryOptionsLoaded( categoryIds ).then( function() {
					return self.setCategoriesOnInstance( categoryIds );
				} );
			}

			// Tom Select not initialized yet - store for pending restoration
			// This prevents empty flash by processing categories during initialization
		this.pendingCategoryIds = categoryIds;

			// Initialize Tom Select - it will process pendingCategoryIds during initialization
			return this.initializeCategorySelect();
		},

		/**
		 * Set categories on existing instance
		 * Note: Category options must already be loaded before calling this method
		 */
		setCategoriesOnInstance: function( categoryIds ) {

			if ( !this.tomSelect || !this.tomSelect.instance ) {
				return Promise.resolve();
			}

			// Ensure "All Categories" option exists if needed (special option not from API)
			if ( categoryIds.indexOf( 'all' ) !== -1 && !this.tomSelect.instance.options['all'] ) {
				this.tomSelect.instance.addOption( {
					value: 'all',
					text: 'All Categories',
					count: 0,
					level: 0,
					$order: 0
				} );
			}

			// No lock needed during restoration - let onChange handler update state naturally
			this.tomSelect.setValue( categoryIds );
			this.syncOriginalSelect( categoryIds );
			// State will be updated by onChange handler (processCategoryFilterChange)

			return Promise.resolve();
		},

		/**
		 * Get preloaded category data from PHP
		 * @private
		 */
		getPreloadedCategoryData: function( categoryIds ) {
			// Check for PHP preloaded category data
			if ( !window.scdWizardData ||
				!window.scdWizardData.current_campaign ||
				!window.scdWizardData.current_campaign.products ||
				!window.scdWizardData.current_campaign.products.selected_categories_data ) {
				return [];
			}

			var preloadedData = window.scdWizardData.current_campaign.products.selected_categories_data;
			var categoryIdStrings = categoryIds.map( String );

			return preloadedData.filter( function( category ) {
				return categoryIdStrings.indexOf( String( category.value ) ) !== -1;
			} );
		},

		/**
		 * Ensure category options are loaded in Tom Select instance
		 * Used when setting categories on an already-initialized instance
		 */
		ensureCategoryOptionsLoaded: function( categoryIds ) {

			if ( !this.tomSelect || !this.tomSelect.instance ) {
				return Promise.resolve();
			}

			var self = this;
			var missingCategories = [];

			// Check which categories are missing from options
			for ( var i = 0; i < categoryIds.length; i++ ) {
				var catId = categoryIds[i];
				if ( catId !== 'all' && !this.tomSelect.instance.options[catId] ) {
					missingCategories.push( catId );
				}
			}

			// If all categories are already loaded, return immediately
			if ( missingCategories.length === 0 ) {
				return Promise.resolve();
			}

			// Try to use preloaded data first (performance optimization)
			var preloadedData = this.getPreloadedCategoryData( missingCategories );
			if ( preloadedData.length === missingCategories.length ) {
				// All missing categories available in preload - use it
				preloadedData.forEach( function( cat ) {
					if ( cat.value && !self.tomSelect.instance.options[String( cat.value )] ) {
						self.tomSelect.instance.addOption( {
							value: String( cat.value ),
							text: cat.text || cat.name,
							count: cat.count || 0,
							level: cat.level || 0,
							$order: cat.level || 0
						} );
					}
				} );
				return Promise.resolve();
			}

			// Load missing categories from API (fallback)
			return this.api.searchCategories( { ids: missingCategories } )
				.then( function( response ) {
					var categories = self.extractCategoriesFromResponse( response );

					// Add each category as an option
					categories.forEach( function( cat ) {
						// API returns categories with 'value' and 'text' fields already formatted
						if ( cat.value && !self.tomSelect.instance.options[String( cat.value )] ) {
							self.tomSelect.instance.addOption( {
								value: String( cat.value ),
								text: cat.text,
								count: cat.count || 0,
								level: cat.level || 0,
								$order: cat.level || 0
							} );
						}
					} );

					return Promise.resolve();
				} )
				.catch( function( error ) {
					SCD.ErrorHandler.handle( error, 'category-filter-load-options', SCD.ErrorHandler.SEVERITY.MEDIUM );

					// Notify user of the failure
					if ( SCD.Shared && SCD.Shared.NotificationService ) {
						SCD.Shared.NotificationService.warning( 'Some category options could not be loaded. Please refresh the page if categories are missing.' );
					}

					// Don't reject - allow setValue to proceed even if some categories couldn't be loaded
					return Promise.resolve();
				} );
		},

		/**
		 * Sync categories with state
		 *
		 * Lock purpose: Prevent circular updates when responding to external state changes.
		 * This is called when state changes externally (not from user interaction), so we
		 * update the UI to match state but don't want onChange to update state again.
		 */
		syncCategoriesWithState: function( categories ) {
			if ( !this.tomSelect || !this.tomSelect.instance || this.locks.categoryUpdate ) {
				return;
			}

			// Ensure "All Categories" option exists if needed
			if ( categories && categories.indexOf( 'all' ) !== -1 && !this.tomSelect.instance.options['all'] ) {
				this.tomSelect.instance.addOption( {
					value: 'all',
					text: 'All Categories',
					count: 0,
					level: 0,
					$order: 0
				} );
			}

			var currentValue = this.tomSelect.getValue();
			if ( JSON.stringify( currentValue ) !== JSON.stringify( categories ) ) {
				// Set lock to prevent triggering change events that would loop back
				this.locks.categoryUpdate = true;
				try {
					this.tomSelect.setValue( categories );
					this.syncOriginalSelect( categories );
				} finally {
					this.locks.categoryUpdate = false;
				}
			}
		},

		/**
		 * Bind module events
		 */
		bindEvents: function() {
			var self = this;

			try {
				// Listen for external state changes
				this.bindCustomEvent( 'scd:products:state:changed', function( e, data ) {
					if ( data && data.property === 'categoryIds' ) {
						self.syncCategoriesWithState( data.value );
					}
				} );

			} catch ( error ) {
				SCD.ErrorHandler.handle( error, 'category-filter-bind-events' );
			}
		},

		/**
		 * Complex field handler interface: setValue
		 * Maps to setCategories for consistency with the field handler pattern
		 */
		setValue: function( categoryIds ) {
			return this.setCategories( categoryIds );
		},

		/**
		 * Complex field handler interface: getValue
		 * Maps to getSelectedCategories for consistency with the field handler pattern
		 */
		getValue: function() {
			return this.getSelectedCategories();
		},

		/**
		 * Check if module is ready
		 */
		isReady: function() {
			// Return true as long as module is initialized
			// setCategories handles both initialized and uninitialized Tom Select states
			// This allows persistence service to call setValue, which triggers Tom Select initialization if needed
			return this.initialized;
		},

		/**
		 * Destroy and cleanup
		 */
		destroy: function() {
			try {
				// Clear timers
				clearTimeout( this.timers.categoryChange );

				// Destroy Tom Select instance
				if ( this.tomSelect ) {
					this.tomSelect.destroy();
					this.tomSelect = null;
				}

				// Cleanup events
				this.unbindAllEvents();

				// Reset state
				this.initialized = false;
				this.initPromise = null;

				// Reset locks
				this.locks = {
					categoryUpdate: false,
					initialization: false
				};

				// Clear cache
				this.cache.loadedCategories.clear();
				this.cache.totalProductCount = 0;

			} catch ( error ) {
				SCD.ErrorHandler.handle( error, 'category-filter-destroy' );
			}
		}
	} );

} )( jQuery );