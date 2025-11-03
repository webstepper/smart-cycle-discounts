/**
 * Products Picker
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/steps/products/products-picker.js
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

( function( $ ) {
	'use strict';

	// Ensure namespaces exist
	window.SCD = window.SCD || {};
	SCD.Modules = SCD.Modules || {};
	SCD.Modules.Products = SCD.Modules.Products || {};

	/**
	 * Products Picker Constructor
	 *
	 * @param {object} state - State module instance
	 * @param {object} api - API module instance
	 */
	SCD.Modules.Products.Picker = function( state, api ) {
		if ( ! state ) {
			throw new Error( 'Picker requires state dependency' );
		}
		if ( ! api ) {
			throw new Error( 'Picker requires api dependency' );
		}

		this.state = state;
		this.api = api;

		// TomSelect instances
		this.categorySelect = null;
		this.productSelect = null;

		this.cache = {
			products: new Map(),
			categories: new Map()
		};

		// Timers for debouncing
		this.timers = {
			categoryChange: null,
			categoryReload: null
		};

		// Pending restoration (setValue before initialization)
		this.pendingCategories = null;
		this.pendingProducts = null;

		// Initialization state
		this.initialized = false;

		this.initEventManager();
	};

	// Mix in event manager functionality
	SCD.Utils.extend( SCD.Modules.Products.Picker.prototype, SCD.Mixins.EventManager );

	// Extend prototype with methods
	SCD.Utils.extend( SCD.Modules.Products.Picker.prototype, {

		/**
		 * Initialize both TomSelect instances
		 *
		 * @since 1.0.0
		 * @returns {Promise} Promise that resolves when both are initialized
		 */
		init: function() {
			var self = this;

			if ( this.initialized ) {
				return Promise.resolve( this );
			}

			return Promise.all( [
				this.initCategorySelect(),
				this.maybeInitProductSelect()
			] ).then( function() {
				self.bindEvents();
				self.initialized = true;
				return self;
			} );
		},

		/**
		 * Initialize category TomSelect
		 *
		 * @since 1.0.0
		 * @returns {Promise} Promise that resolves when initialized
		 */
		initCategorySelect: function() {
			var self = this;
			var $select = $( '#scd-campaign-categories' );

			if ( ! $select.length ) {
				return Promise.resolve();
			}

			if ( this.categorySelect ) {
				return Promise.resolve();
			}

			var config = {
				placeholder: 'Filter by categories...',
				preload: false,
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
						var currentQuery = this.lastQuery || '';
						if ( currentQuery.length > 0 && currentQuery.length < 3 ) {
							return '<div class="no-results">Type at least 3 characters to search...</div>';
						}
						return '<div class="no-results">No categories found</div>';
					}
				},

				onChange: function( value ) {
					self.handleCategoryChange( value );
				},

				onInitialize: function() {
					// Add "All Categories" option immediately
					this.addOption( {
						value: 'all',
						text: 'All Categories',
						count: 0,
						level: 0,
						$order: 0
					} );

					if ( self.pendingCategories ) {
						var pendingIds = self.pendingCategories;
						self.pendingCategories = null;
						self.ensureCategoryOptionsLoaded( pendingIds ).then( function() {
							self.setCategoriesOnInstance( pendingIds );
						} );
					} else {
						// Default to "All Categories"
						this.setValue( [ 'all' ], true );
					}
				},

				onDropdownOpen: function() {
					// Clear API cache to load ALL categories
					if ( self.api && self.api.cache && self.api.cache.categories ) {
						self.api.cache.categories = null;
						self.api.cache.categoryTimestamp = 0;
					}

					// Clear TomSelect cache
					if ( this.loadedSearches ) {
						this.loadedSearches = {};
					}
					this.lastQuery = null;

					// Trigger initial load
					this.load( '' );
				},

				onItemAdd: function( value, _item ) {
					self.handleCategoryItemAdd( value );
				},

				onItemRemove: function( _value ) {
					self.handleCategoryItemRemove();
				}
			};

			this.categorySelect = new SCD.Shared.TomSelectBase( $select[0], config );

			return this.categorySelect.init().then( function() {
				return self;
			} );
		},

		/**
		 * Initialize product TomSelect (only if selection type is specific_products)
		 *
		 * @since 1.0.0
		 * @returns {Promise} Promise that resolves when initialized
		 */
		maybeInitProductSelect: function() {
			var state = this.state.getState();

			// Only init if selection type is specific_products
			if ( 'specific_products' === state.productSelectionType ) {
				return this.initProductSelect();
			}

			return Promise.resolve();
		},

		/**
		 * Initialize product TomSelect
		 *
		 * @since 1.0.0
		 * @returns {Promise} Promise that resolves when initialized
		 */
		initProductSelect: function() {
			var self = this;
			var $select = $( '#scd-product-search' );

			if ( ! $select.length ) {
				return Promise.resolve();
			}

			if ( this.productSelect ) {
				return Promise.resolve();
			}

			var config = {
				placeholder: 'Search and select products...',
				searchField: [ 'text', 'sku' ],
				hidePlaceholder: true,
				preload: false,
				sortField: [
					{ field: '$order' },
					{ field: '$score' }
				],

				load: function( query, callback ) {
					self.loadProducts( query, callback );
				},

				render: {
					option: function( data, escape ) {
						return self.renderProductOption( data, escape );
					},
					no_results: function( _data, _escape ) {
						var currentQuery = this.lastQuery || '';
						if ( currentQuery.length > 0 && currentQuery.length < 2 ) {
							return '<div class="no-results">Type at least 2 characters to search...</div>';
						}

						var categories = self.getCurrentCategoryFilter();
						var filterText = self.isAllCategoriesSelected( categories ) ? '' : ' in selected categories';
						return '<div class="no-results">No products found' + filterText + '</div>';
					}
				},

				onChange: function( value ) {
					self.handleProductChange( value );
				},

				onDropdownOpen: function() {
					if ( ! self.productSelect.instance.loading ) {
						self.productSelect.instance.load( '' );
					}
				}
			};

			this.productSelect = new SCD.Shared.TomSelectBase( $select[0], config );

			return this.productSelect.init().then( function() {
				if ( self.pendingProducts ) {
					var pendingIds = self.pendingProducts;
					self.pendingProducts = null;
					return self.restoreProducts( pendingIds );
				}
				return self;
			} );
		},

		/**
		 * Bind events
		 *
		 * @since 1.0.0
		 * @returns {void}
		 */
		bindEvents: function() {
			// No events needed - category and product are in same module
			// Direct method calls instead of events
		},

		/**
		 * Handle category selection change
		 * Implements "All Categories" exclusive logic at UI level
		 *
		 * @since 1.0.0
		 * @param {string|Array} value - Selected category value(s)
		 * @returns {void}
		 */
		handleCategoryChange: function( value ) {
			var self = this;

			// Debounce rapid changes
			clearTimeout( this.timers.categoryChange );
			this.timers.categoryChange = setTimeout( function() {
				self.processCategoryChange( value );
			}, 150 );
		},

		/**
		 * Process category change
		 *
		 * @since 1.0.0
		 * @param {string|Array} _value - Category value (not used, read from instance)
		 * @returns {void}
		 */
		processCategoryChange: function( _value ) {

			var currentValues = this.categorySelect ? this.categorySelect.getValue() : [];
			var newCategories = Array.isArray( currentValues ) ? currentValues : ( currentValues ? [ currentValues ] : [] );

			if ( 0 === newCategories.length ) {
				newCategories = [ 'all' ];
			}

			var oldCategories = this.state.getState().categoryIds;
			if ( ! oldCategories || 0 === oldCategories.length ) {
				oldCategories = [ 'all' ];
			}


			if ( ! this.hasCategoryChanges( newCategories, oldCategories ) ) {
				return;
			}


			this.state.setState( { categoryIds: newCategories } );

			// Sync with original select element for form submission
			this.syncCategorySelect( newCategories );

			// Trigger event for other modules (e.g., orchestrator)
			$( document ).trigger( 'scd:categories:changed', {
				categories: newCategories,
				previousCategories: oldCategories,
				source: 'picker'
			} );

			// If product select is initialized, reload products with new filter
			if ( this.productSelect ) {
				this.reloadProductsForNewCategories( newCategories );
			}
		},

		/**
		 * Handle category item add (exclusive "All Categories" logic)
		 *
		 * @since 1.0.0
		 * @param {string} value - Added category value
		 * @returns {void}
		 */
		handleCategoryItemAdd: function( value ) {
			if ( ! this.categorySelect || ! this.categorySelect.instance ) {
				return;
			}

			var currentValues = this.categorySelect.getValue();
			var values = Array.isArray( currentValues ) ? currentValues : ( currentValues ? [ currentValues ] : [] );

			// Handle "All Categories" exclusive selection
			if ( 'all' === value && 1 < values.length ) {
				// User selected "All Categories" - clear others
				this.categorySelect.clear();
				this.categorySelect.instance.addItem( 'all' );
			} else if ( 'all' !== value && -1 !== values.indexOf( 'all' ) ) {
				// User selected specific category while "All" selected - remove "All"
				this.categorySelect.instance.removeItem( 'all' );
			}
		},

		/**
		 * Handle category item remove (ensure "All Categories" if all removed)
		 *
		 * @since 1.0.0
		 * @returns {void}
		 */
		handleCategoryItemRemove: function() {
			if ( ! this.categorySelect || ! this.categorySelect.instance ) {
				return;
			}

			var currentValues = this.categorySelect.getValue();
			var values = Array.isArray( currentValues ) ? currentValues : ( currentValues ? [ currentValues ] : [] );

			// If no categories selected, automatically select "All Categories"
			if ( 0 === values.length ) {
				this.categorySelect.instance.addItem( 'all' );
			}
		},

		/**
		 * Handle product selection change
		 *
		 * @since 1.0.0
		 * @param {string|Array} value - Selected product value(s)
		 * @returns {void}
		 */
		handleProductChange: function( value ) {
			var productIds = Array.isArray( value ) ? value.map( String ) : [];
			var previousProducts = this.state.getState().productIds || [];


			if ( ! this.hasProductChanges( productIds, previousProducts ) ) {
				return;
			}


			var self = this;
			productIds.forEach( function( productId ) {
				var product = self.cache.products.get( productId );
				if ( product && product.categoryIds ) {
					// Product data already cached with categories
				}
			} );

			this.state.setState( { productIds: productIds } );

			// Sync to hidden field (single source of truth for form submission)
			this.syncHiddenField( productIds );

			// Sync with TomSelect element (for TomSelect to track selections)
			this.syncProductSelect( productIds );

			if ( window.SCD && window.SCD.Wizard && window.SCD.Wizard.modules && window.SCD.Wizard.modules.stateManager ) {
				var currentStepData = window.SCD.Wizard.modules.stateManager.get( 'stepData' ) || {};
				var productsStepData = currentStepData.products || {};
				productsStepData.productIds = productIds;
				currentStepData.products = productsStepData;
				window.SCD.Wizard.modules.stateManager.set( 'stepData', currentStepData );
			}
		},

		/**
		 * Reload products when categories change
		 *
		 * @since 1.0.0
		 * @param {Array} categories - New category filter
		 * @returns {void}
		 */
		reloadProductsForNewCategories: function( categories ) {
			var self = this;

			// Skip if product select not initialized
			if ( ! this.productSelect || ! this.productSelect.instance ) {
				return;
			}

			// Debounce to prevent rapid API calls
			clearTimeout( this.timers.categoryReload );
			this.timers.categoryReload = setTimeout( function() {

				// Save selected products
				var selected = self.productSelect.getValue() || [];

				// Filter currently selected products by new categories FIRST
				var filtered = self.filterProductsByCategories( selected, categories );

				if ( filtered.length < selected.length ) {
					var removedCount = selected.length - filtered.length;
					if ( SCD.Shared && SCD.Shared.NotificationService ) {
						SCD.Shared.NotificationService.show(
							removedCount + ' product(s) removed - not in selected categories',
							'info',
							3000  // 3 seconds
						);
					}
				}

				var instance = self.productSelect.instance;

				// CRITICAL: Clear selected items FIRST, then clear options
				// TomSelect keeps rendered items even after clearOptions() if they're selected
				instance.clear( true );  // true = silent mode (no onChange event)

				// Now clear all options from dropdown
				instance.clearOptions();
				if ( instance.loadedSearches ) {
					instance.loadedSearches = {};
				}
				instance.lastQuery = null;

				// Immediately reload dropdown with new category filter (AJAX)
				// This ensures TomSelect shows ONLY products from selected categories in real-time
				self.loadProducts( '', function( newProducts ) {
					// Handle empty response (AJAX failure OR category has no products)
					if ( ! newProducts || 0 === newProducts.length ) {
						// If category is "All" with no products, something is wrong (likely AJAX failure)
						// If specific category with no products, that's normal

						// For now, restore filtered products from cache if we have any
						// This gracefully handles both AJAX failures and empty categories
						if ( 0 < filtered.length ) {
							// Restore products using cache (fallback behavior)
							self.restoreProducts( filtered );
						} else {
							// No filtered products - dropdown remains empty (correct for empty category)
							// No need to show error - this is expected for categories with no products
						}
						return;  // Exit early - don't proceed with empty response
					}

					// Lock TomSelect to prevent visual updates during batch operations
					// This prevents flickering/bouncing when adding multiple options
					instance.lock();

					newProducts.forEach( function( product ) {
						if ( ! instance.options[product.value] ) {
							instance.addOption( product );
						}
					} );

					// CRITICAL: After AJAX completes, set the filtered selection
					// DO NOT call restoreProducts() as it would re-add old products from cache
					// Instead, directly set the filtered products on TomSelect instance
					if ( 0 < filtered.length ) {
						var validFiltered = filtered.filter( function( id ) {
							return instance.options[id];
						} );

						instance.setValue( validFiltered, true );

						// Sync state and hidden field
						self.state.setState( { productIds: validFiltered } );
						self.syncHiddenField( validFiltered );
						self.syncProductSelect( validFiltered );
					} else if ( 0 < selected.length ) {
						// All selected products were filtered out - clear everything
						self.clearProductSelection();
					}

					// Unlock and refresh once - single visual update (no flicker!)
					instance.unlock();
					instance.refreshOptions( false );
				} );

			}, 300 );
		},

		/**
		 * Filter product IDs by categories (synchronous, uses cache)
		 *
		 * @since 1.0.0
		 * @param {Array} productIds - Product IDs to filter
		 * @param {Array} categories - Category IDs to filter by
		 * @returns {Array} Filtered product IDs
		 */
		filterProductsByCategories: function( productIds, categories ) {
			// "All" = no filtering
			if ( 1 === categories.length && 'all' === categories[0] ) {
				return productIds;
			}

			var self = this;
			return productIds.filter( function( id ) {
				var product = self.cache.products.get( id );
				if ( ! product || ! product.categoryIds ) {
					return false;
				}

				return product.categoryIds.some( function( catId ) {
					return -1 !== categories.indexOf( String( catId ) );
				} );
			} );
		},

		/**
		 * Load categories from API
		 *
		 * @since 1.0.0
		 * @param {string} query - Search query
		 * @param {function} callback - Callback function
		 * @returns {void}
		 */
		loadCategories: function( query, callback ) {
			var self = this;

			// Require minimum 3 characters for search
			if ( query && 0 < query.length && query.length < 3 ) {
				callback( [] );
				return;
			}

			this.api.searchCategories( { search: query } )
				.done( function( response ) {
					var categories = self.extractCategories( response );

					categories.forEach( function( cat ) {
						self.cache.categories.set( cat.value, cat );
					} );

					// Add "All Categories" option first
					var options = [ {
						value: 'all',
						text: 'All Categories',
						count: 0,
						level: 0,
						$order: 0
					} ].concat( categories );

					callback( options );
				} )
				.fail( function() {
					if ( SCD.Shared && SCD.Shared.NotificationService ) {
						SCD.Shared.NotificationService.error( 'Failed to load categories.' );
					}
					callback( [] );
				} );
		},

		/**
		 * Load products from API (with category filter applied)
		 *
		 * @since 1.0.0
		 * @param {string} query - Search query
		 * @param {function} callback - Callback function
		 * @returns {void}
		 */
		loadProducts: function( query, callback ) {
			var self = this;

			// Require minimum 2 characters for search
			if ( query && 0 < query.length && query.length < 2 ) {
				callback( [] );
				return;
			}

			var categories = this.getCurrentCategoryFilter();
			var categoryFilter = this.isAllCategoriesSelected( categories ) ? [] : categories;

			this.api.searchProducts( {
				term: query,
				page: 1,
				perPage: 50,
				categories: categoryFilter
			} )
				.done( function( response ) {
					var products = self.extractProducts( response );

					// Normalize and cache
					var normalized = products.map( function( p ) {
						var product = {
							id: String( p.id ),
							value: String( p.id ),
							text: p.name || p.text,
							price: p.price || '',
							sku: p.sku || '',
							image: p.image || '',
							categoryIds: p.categoryIds || []
						};

						self.cache.products.set( product.id, product );

						return product;
					} );

					callback( normalized );
				} )
				.fail( function() {
					if ( SCD.Shared && SCD.Shared.NotificationService ) {
						SCD.Shared.NotificationService.error( 'Failed to load products.' );
					}
					callback( [] );
				} );
		},

		/**
		 * Restore products (for initialization/restoration)
		 *
		 * @since 1.0.0
		 * @param {Array} productIds - Product IDs to restore
		 * @returns {Promise} Promise that resolves when restored
		 */
		restoreProducts: function( productIds ) {

			if ( ! this.productSelect || ! productIds || 0 === productIds.length ) {
				return Promise.resolve();
			}

			var self = this;

			// Try preloaded data first
			var preloaded = this.getPreloadedProducts( productIds );

			if ( preloaded.length === productIds.length ) {
				this.addProductOptions( preloaded );
				this.productSelect.setValue( productIds, true );

				// Sync state and hidden field (critical for form submission)
				this.state.setState( { productIds: productIds } );
				this.syncHiddenField( productIds );
				this.syncProductSelect( productIds );

				return Promise.resolve();
			}

			return this.api.getProductsByIds( productIds )
				.then( function( response ) {
					var products = self.extractProducts( response );
					self.addProductOptions( products );
					self.productSelect.setValue( productIds, true );

					// Sync state and hidden field (critical for form submission)
					self.state.setState( { productIds: productIds } );
					self.syncHiddenField( productIds );
					self.syncProductSelect( productIds );
				} )
				.catch( function( error ) {
					console.error( '[ProductsPicker] Restore failed:', error );
					SCD.ErrorHandler.handle( error, 'picker-restore-products' );
				} );
		},

		/**
		 * Add product options to TomSelect
		 *
		 * @since 1.0.0
		 * @param {Array} products - Products to add
		 * @returns {void}
		 */
		addProductOptions: function( products ) {
			if ( ! this.productSelect ) {
				return;
			}

			var self = this;
			products.forEach( function( p ) {
				var option = {
					id: String( p.id ),
					value: String( p.id ),
					text: p.name || p.text,
					price: p.price || '',
					sku: p.sku || '',
					image: p.image || '',
					categoryIds: p.categoryIds || []
				};

				// Cache
				self.cache.products.set( option.id, option );

				if ( ! self.productSelect.instance.options[option.id] ) {
					self.productSelect.instance.addOption( option );
				}
			} );
		},

		/**
		 * Get current category filter
		 *
		 * @since 1.0.0
		 * @returns {Array} Selected category IDs
		 */
		getCurrentCategoryFilter: function() {
			if ( ! this.categorySelect ) {
				return [ 'all' ];
			}

			var selected = this.categorySelect.getValue();
			if ( selected && 0 < selected.length ) {
				return selected;
			}

			return [ 'all' ];
		},

		/**
		 * Check if "All Categories" is selected
		 *
		 * @since 1.0.0
		 * @param {Array} categories - Category IDs
		 * @returns {boolean} True if all categories selected
		 */
		isAllCategoriesSelected: function( categories ) {
			return 1 === categories.length && 'all' === categories[0];
		},

		/**
		 * Extract categories from API response
		 *
		 * @since 1.0.0
		 * @param {object} response - API response
		 * @returns {Array} Extracted categories
		 */
		extractCategories: function( response ) {
			var categoriesData = [];

			if ( response && response.data && response.data.categories ) {
				categoriesData = response.data.categories;
			} else if ( response && response.categories ) {
				categoriesData = response.categories;
			}

			return categoriesData;
		},

		/**
		 * Extract products from API response
		 *
		 * @since 1.0.0
		 * @param {object} response - API response
		 * @returns {Array} Extracted products
		 */
		extractProducts: function( response ) {
			var products = [];

			if ( Array.isArray( response ) ) {
				products = response;
			} else if ( response && 'object' === typeof response ) {
				if ( response.data ) {
					products = response.data.products || response.data;
				} else {
					products = response.products || [];
				}
			}

			return Array.isArray( products ) ? products : [];
		},

		/**
		 * Get preloaded products from PHP
		 *
		 * @since 1.0.0
		 * @param {Array} productIds - Product IDs to get
		 * @returns {Array} Preloaded products
		 */
		getPreloadedProducts: function( productIds ) {
			if ( ! window.scdWizardData ||
				! window.scdWizardData.currentCampaign ||
				! window.scdWizardData.currentCampaign.products ||
				! window.scdWizardData.currentCampaign.products.selectedProductsData ) {
				return [];
			}

			var preloaded = window.scdWizardData.currentCampaign.products.selectedProductsData;
			var idStrings = productIds.map( String );

			return preloaded.filter( function( p ) {
				return -1 !== idStrings.indexOf( String( p.id ) );
			} );
		},

		/**
		 * Ensure category options are loaded
		 *
		 * @since 1.0.0
		 * @param {Array} categoryIds - Category IDs
		 * @returns {Promise} Promise that resolves when loaded
		 */
		ensureCategoryOptionsLoaded: function( categoryIds ) {
			var self = this;

			// Filter out 'all' as it's always available
			var idsToLoad = categoryIds.filter( function( id ) {
				return 'all' !== id;
			} );

			if ( 0 === idsToLoad.length ) {
				return Promise.resolve();
			}

			return this.api.searchCategories( {
				ids: idsToLoad,
				skipDebounce: true
			} ).then( function( response ) {
				var categories = self.extractCategories( response );

				categories.forEach( function( cat ) {
					if ( ! self.categorySelect.instance.options[cat.value] ) {
						self.categorySelect.instance.addOption( cat );
					}
				} );
			} );
		},

		/**
		 * Set categories on TomSelect instance
		 *
		 * @since 1.0.0
		 * @param {Array} categoryIds - Category IDs to set
		 * @returns {void}
		 */
		setCategoriesOnInstance: function( categoryIds ) {
			if ( ! this.categorySelect ) {
				return;
			}

			this.categorySelect.setValue( categoryIds, true );
		},

		/**
		 * Render category option
		 *
		 * @since 1.0.0
		 * @param {object} data - Category data
		 * @param {function} escape - Escape function
		 * @returns {string} HTML
		 */
		renderCategoryOption: function( data, escape ) {
			var prefix = data.level ? '\u2014 '.repeat( data.level ) : '';
			var count = data.count !== undefined
				? ' <span class="category-count">(' + data.count + ')</span>'
				: '';

			return '<div class="category-option">' +
				prefix + escape( data.text ) + count +
				'</div>';
		},

		/**
		 * Render product option
		 *
		 * @since 1.0.0
		 * @param {object} data - Product data
		 * @param {function} escape - Escape function
		 * @returns {string} HTML
		 */
		renderProductOption: function( data, escape ) {
			var image = data.image
				? '<img src="' + escape( data.image ) + '" alt="">'
				: '<span class="dashicons dashicons-format-image"></span>';

			return '<div class="scd-tom-select-product-option">' +
				'<div class="product-image">' + image + '</div>' +
				'<div class="product-details">' +
					'<div class="product-name">' + escape( data.text ) + '</div>' +
					'<div class="product-meta">' +
						( data.price || '' ) + ' ' +
						( data.sku ? 'SKU: ' + escape( data.sku ) : '' ) +
					'</div>' +
				'</div>' +
			'</div>';
		},

		/**
		 * Check if categories changed
		 *
		 * @since 1.0.0
		 * @param {Array} newCategories - New categories
		 * @param {Array} oldCategories - Old categories
		 * @returns {boolean} True if changed
		 */
		hasCategoryChanges: function( newCategories, oldCategories ) {
			if ( newCategories.length !== oldCategories.length ) {
				return true;
			}

			return ! newCategories.every( function( id ) {
				return -1 !== oldCategories.indexOf( id );
			} );
		},

		/**
		 * Check if products changed
		 *
		 * @since 1.0.0
		 * @param {Array} newProducts - New products
		 * @param {Array} oldProducts - Old products
		 * @returns {boolean} True if changed
		 */
		hasProductChanges: function( newProducts, oldProducts ) {
			if ( newProducts.length !== oldProducts.length ) {
				return true;
			}

			return ! newProducts.every( function( id ) {
				return -1 !== oldProducts.indexOf( id );
			} );
		},

		/**
		 * Sync category select element (for form submission)
		 *
		 * @since 1.0.0
		 * @param {Array} values - Category IDs
		 * @returns {void}
		 */
		syncCategorySelect: function( values ) {
			var $originalSelect = $( '#scd-campaign-categories' );

			if ( $originalSelect.length ) {
				$originalSelect.empty();

				if ( values && 0 < values.length ) {
					values.forEach( function( value ) {
						var option = new Option( value, value, true, true );
						$originalSelect.append( option );
					} );
				}

				$originalSelect.trigger( 'change' );
			}
		},

		/**
		 * Sync product select element (for TomSelect to track selections)
		 *
		 * @since 1.0.0
		 * @param {Array} values - Product IDs
		 * @returns {void}
		 */
		syncProductSelect: function( values ) {
			var $originalSelect = $( '#scd-product-search' );

			if ( $originalSelect.length ) {
				$originalSelect.empty();

				if ( values && 0 < values.length ) {
					values.forEach( function( value ) {
						var option = new Option( value, value, true, true );
						$originalSelect.append( option );
					} );
				}

				$originalSelect.trigger( 'change' );
			}
		},

		/**
		 * Sync hidden field (single source of truth for form submission)
		 *
		 * @since 1.0.0
		 * @param {Array} values - Product IDs
		 * @returns {void}
		 */
		syncHiddenField: function( values ) {
			var $hiddenField = $( '#scd-product-ids-hidden' );

			if ( $hiddenField.length ) {
				var csvValue = values && 0 < values.length ? values.join( ',' ) : '';
				$hiddenField.val( csvValue );
				$hiddenField.trigger( 'change' );
			}
		},

		/**
		 * Complex field handler interface - Set value
		 *
		 * @since 1.0.0
		 * @param {string|Object} field - Field name ('categories' or 'products'), or object with {categoryIds, productIds}
		 * @param {Array} value - Value to set (if field is string)
		 * @returns {Promise|void} Promise for products, void for categories
		 */
		setValue: function( field, value ) {

			// If called with array of product IDs directly (from complex field population)
			if ( Array.isArray( field ) ) {
				if ( this.productSelect ) {
					return this.restoreProducts( field );
				} else {
					this.pendingProducts = field;
					return Promise.resolve();
				}
			}

			// If field is an object (not array), it's the complex field handler interface
			if ( 'object' === typeof field && field !== null && ! Array.isArray( field ) ) {
				var data = field;
				var promises = [];


				if ( data.categoryIds ) {
					if ( this.categorySelect ) {
						promises.push( this.ensureCategoryOptionsLoaded( data.categoryIds ).then( function() {
							this.setCategoriesOnInstance( data.categoryIds );
						}.bind( this ) ) );
					} else {
						this.pendingCategories = data.categoryIds;
					}
				}

				if ( data.productIds && 0 < data.productIds.length ) {
					if ( this.productSelect ) {
						promises.push( this.restoreProducts( data.productIds ) );
					} else {
						this.pendingProducts = data.productIds;
					}
				}

				// Return promise if we have any
				if ( 0 < promises.length ) {
					return Promise.all( promises );
				}
			}
		},

		/**
		 * Complex field handler interface - Get value
		 *
		 * @since 1.0.0
		 * @returns {Object} Current value with categoryIds and productIds
		 */
		getValue: function() {
			return {
				categoryIds: this.categorySelect ? this.categorySelect.getValue() : [ 'all' ],
				productIds: this.productSelect ? this.productSelect.getValue() : []
			};
		},

		/**
		 * Get category IDs only (for category_ids field collection)
		 *
		 * @since 1.0.0
		 * @returns {Array} Selected category IDs
		 */
		getCategoryIds: function() {
			return this.categorySelect ? this.categorySelect.getValue() : [ 'all' ];
		},

		/**
		 * Get product IDs only (for product_ids field collection)
		 *
		 * @since 1.0.0
		 * @returns {Array} Selected product IDs
		 */
		getProductIds: function() {
			return this.productSelect ? this.productSelect.getValue() : [];
		},

		/**
		 * Set category IDs only (for category_ids field population)
		 *
		 * @since 1.0.0
		 * @param {Array} value - Category IDs to set
		 * @returns {Promise} Promise that resolves when set
		 */
		setCategoryIds: function( value ) {

			if ( ! this.categorySelect ) {
				this.pendingCategories = value;
				return Promise.resolve();
			}

			// If empty array, treat as 'all' (default)
			var categoriesToSet = value && value.length > 0 ? value : [ 'all' ];

			return this.ensureCategoryOptionsLoaded( categoriesToSet ).then( function() {
				this.setCategoriesOnInstance( categoriesToSet );
			}.bind( this ) );
		},

		/**
		 * Complex field handler interface - Check if ready
		 *
		 * @since 1.0.0
		 * @returns {boolean} True if initialized
		 */
		isReady: function() {
			return this.initialized;
		},

		/**
		 * Show error on field
		 *
		 * @since 1.0.0
		 * @param {string} message - Error message
		 * @returns {void}
		 */
		showError: function( message ) {
			if ( this.productSelect ) {
				this.productSelect.showError( message );
			}
		},

		/**
		 * Clear error from field
		 *
		 * @since 1.0.0
		 * @returns {void}
		 */
		clearError: function() {
			if ( this.productSelect ) {
				this.productSelect.clearError();
			}
		},

		/**
		 * Clear all product selections (all data layers)
		 *
		 * Used when category filter removes all selected products.
		 * Ensures TomSelect UI, state, hidden field, and select element are all cleared.
		 *
		 * @since 1.0.0
		 * @returns {void}
		 */
		clearProductSelection: function() {
			// Clear TomSelect UI
			if ( this.productSelect && this.productSelect.instance ) {
				this.productSelect.instance.clear();
			}

			if ( this.state && 'function' === typeof this.state.setState ) {
				this.state.setState( { productIds: [] } );
			}

			this.syncHiddenField( [] );

			this.syncProductSelect( [] );
		},

		/**
		 * Cleanup
		 *
		 * @since 1.0.0
		 * @returns {void}
		 */
		destroy: function() {
			clearTimeout( this.timers.categoryChange );
			clearTimeout( this.timers.categoryReload );

			// Destroy TomSelect instances
			if ( this.categorySelect ) {
				this.categorySelect.destroy();
				this.categorySelect = null;
			}
			if ( this.productSelect ) {
				this.productSelect.destroy();
				this.productSelect = null;
			}

			this.cache.products.clear();
			this.cache.categories.clear();

			// Unbind events
			this.unbindAllEvents();

			this.initialized = false;
		}
	} );

} )( jQuery );
