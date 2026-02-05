/**
 * Products Picker
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/steps/products/products-picker.js
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress/plugins/smart-cycle-discounts/
 * @since      1.0.0
 */

( function( $ ) {
	'use strict';

	// Ensure namespaces exist
	window.WSSCD = window.WSSCD || {};
	WSSCD.Modules = WSSCD.Modules || {};
	WSSCD.Modules.Products = WSSCD.Modules.Products || {};

	/**
	 * Products Picker Constructor
	 *
	 * @param {object} state - State module instance
	 * @param {object} api - API module instance
	 */
	WSSCD.Modules.Products.Picker = function( state, api ) {
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
		this.pendingProducts = null;

		// Initialization state
		this.initialized = false;

		this.initEventManager();
	};

	// Mix in event manager functionality
	WSSCD.Utils.extend( WSSCD.Modules.Products.Picker.prototype, WSSCD.Mixins.EventManager );

	// Extend prototype with methods
	WSSCD.Utils.extend( WSSCD.Modules.Products.Picker.prototype, {

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
			var $select = $( '#wsscd-campaign-categories' );

			if ( ! $select.length ) {
				return Promise.resolve();
			}

			if ( this.categorySelect ) {
				return Promise.resolve();
			}

			// Get category data from PHP-provided savedData (via window.wsscdProductsState)
			// This ensures data is available immediately, avoiding race conditions with populateFields()
			//
			// Category Filter Model:
			// - [] (empty) from PHP = all categories (no filter applied to product pool)
			// - ['all'] = UI marker for "All Categories" option (equivalent to empty)
			// - [id1, id2] = specific category filter

			var savedData = window.wsscdProductsState && window.wsscdProductsState.savedData ? window.wsscdProductsState.savedData : {};
			var preloadedOptions = savedData.categoryOptions || [];
			var selectedCategoryIds = savedData.categoryIds;

			// Normalize: treat empty array as ['all'] for UI display
			if ( ! selectedCategoryIds || 0 === selectedCategoryIds.length ) {
				selectedCategoryIds = [ 'all' ];
			}
			var initialOptions = [
				{
					value: 'all',
					text: 'All Categories',
					count: 0,
					level: 0,
					$order: 0
				}
			];

			// Add preloaded category options (from server-side render)
			if ( preloadedOptions && preloadedOptions.length > 0 ) {
				initialOptions = initialOptions.concat( preloadedOptions );
			}

			var config = {
				placeholder: 'Filter by categories...',
				preload: 'focus', // Load on focus/dropdown open (same as product select)
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

				onDropdownOpen: function() {
					// Check if already loaded
					if ( self.categorySelect.instance.loadedSearches && self.categorySelect.instance.loadedSearches[''] ) {
						return; // Already loaded, Tom Select will render from cache
					}

					if ( self.categorySelect.instance.loading ) {
						return;
					}

					self.categorySelect.instance.loading = 1;

					self.loadCategories( '', function( categories ) {
						// Add options to instance
						categories.forEach( function( category ) {
							if ( ! self.categorySelect.instance.options[category.value] ) {
								self.categorySelect.instance.addOption( category );
							}
						} );

						self.categorySelect.instance.loading = 0;

						// Mark as loaded to prevent re-loading
						if ( ! self.categorySelect.instance.loadedSearches ) {
							self.categorySelect.instance.loadedSearches = {};
						}
						self.categorySelect.instance.loadedSearches[''] = true;

						// Refresh options to show the newly loaded categories
						self.categorySelect.instance.refreshOptions( false );
					} );
				},

				onItemAdd: function( value, _item ) {
					self.handleCategoryItemAdd( value );
				},

				onItemRemove: function( _value ) {
					self.handleCategoryItemRemove();
				}
			};

			this.categorySelect = new WSSCD.Shared.TomSelectBase( $select[0], config );

			return this.categorySelect.init().then( function() {
				// Add "All Categories" option immediately after init
				if ( self.categorySelect && self.categorySelect.instance ) {
					// Add initial options (at minimum "All Categories")
					initialOptions.forEach( function( option ) {
						if ( ! self.categorySelect.instance.options[option.value] ) {
							self.categorySelect.instance.addOption( option );
						}
					} );

					// Set selected items (silent mode to prevent onChange during init)
					self.categorySelect.instance.setValue( selectedCategoryIds, true );
				}
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
			var $select = $( '#wsscd-product-search' );

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
				preload: 'focus', // Load on focus/dropdown open
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
					// Tom Select architectural limitation with openOnFocus: false
					// When using openOnFocus: false (to fix bug #701), the dropdown opens
					// via click handler, but Tom Select's load() doesn't automatically
					// re-render options added to an already-open dropdown.
					//
					// Root cause: Tom Select expects options to load DURING open(), not AFTER.
					// Solution: Load products, then force re-render via close/refresh/open cycle.

					// Tom Select best practice: Only load if not already cached
					// Check if we have cached results for empty query
					if ( self.productSelect.instance.loadedSearches && self.productSelect.instance.loadedSearches[''] ) {
						return; // Already loaded, no need to reload
					}

					if ( self.productSelect.isRefreshing || self.productSelect.instance.loading ) {
						return;
					}

					self.productSelect.instance.loading = 1;

					self.loadProducts( '', function( products ) {
						// Add options to instance
						products.forEach( function( product ) {
							if ( ! self.productSelect.instance.options[product.value] ) {
								self.productSelect.instance.addOption( product );
							}
						} );

						self.productSelect.instance.loading = 0;

						// Force re-render: close, refresh options, re-open
						setTimeout( function() {
							if ( self.productSelect && self.productSelect.instance ) {
								self.productSelect.isRefreshing = true;
								self.productSelect.instance.close();
								self.productSelect.instance.refreshOptions( false );
								self.productSelect.instance.open();

								setTimeout( function() {
									self.productSelect.isRefreshing = false;
								}, 100 );
							}
						}, 10 );
					} );
				}
			};

			this.productSelect = new WSSCD.Shared.TomSelectBase( $select[0], config );

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
		 * Invalidate Tom Select caches
		 *
		 * Call this method when underlying data changes (e.g., categories/products added/deleted)
		 * to force fresh data load on next dropdown open.
		 *
		 * @since 1.0.0
		 * @param {string} [target='both'] - Which cache to invalidate: 'categories', 'products', or 'both'
		 * @returns {void}
		 */
		invalidateCache: function( target ) {
			target = target || 'both';

			// Invalidate category select cache
			if ( ( 'categories' === target || 'both' === target ) && this.categorySelect && this.categorySelect.instance ) {
				if ( this.categorySelect.instance.loadedSearches ) {
					this.categorySelect.instance.loadedSearches = {};
				}
				this.categorySelect.instance.lastQuery = null;
			}

			// Invalidate product select cache
			if ( ( 'products' === target || 'both' === target ) && this.productSelect && this.productSelect.instance ) {
				if ( this.productSelect.instance.loadedSearches ) {
					this.productSelect.instance.loadedSearches = {};
				}
				this.productSelect.instance.lastQuery = null;
			}
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

			// Tom Select automatically syncs to the underlying <select> element
			// No manual sync needed

			// Trigger event for other modules (e.g., orchestrator)
			$( document ).trigger( 'wsscd:categories:changed', {
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

			if ( window.WSSCD && window.WSSCD.Wizard && window.WSSCD.Wizard.modules && window.WSSCD.Wizard.modules.stateManager ) {
				var currentStepData = window.WSSCD.Wizard.modules.stateManager.get( 'stepData' ) || {};
				var productsStepData = currentStepData.products || {};
				productsStepData.productIds = productIds;
				currentStepData.products = productsStepData;
				window.WSSCD.Wizard.modules.stateManager.set( 'stepData', currentStepData );
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
					if ( WSSCD.Shared && WSSCD.Shared.NotificationService ) {
						WSSCD.Shared.NotificationService.show(
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
				// IMPORTANT: Pass categories explicitly to avoid race condition with state updates
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
				}, { categories: categories } );

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
			// Empty or "All" = no filtering (all categories selected)
			if ( this.isAllCategoriesSelected( categories ) ) {
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
				.fail( function( jqXHR, textStatus ) {
					// Ignore cancelled requests (expected when user changes selection rapidly)
					if ( 'abort' === textStatus ) {
						callback( [] );
						return;
					}

					if ( WSSCD.Shared && WSSCD.Shared.NotificationService ) {
						WSSCD.Shared.NotificationService.error( 'Failed to load categories.' );
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
		 * @param {Object} options - Optional parameters
		 * @param {Array} options.categories - Category filter override (bypasses getCurrentCategoryFilter)
		 * @returns {void}
		 */
		loadProducts: function( query, callback, options ) {
			var self = this;
			options = options || {};

			// For non-empty queries, require minimum 2 characters
			// Empty query (dropdown open with no search) is allowed to show initial products
			if ( query && 0 < query.length && query.length < 2 ) {
				callback( [] );
				return;
			}

			// Use provided categories or get from current filter
			// Explicit categories are passed during category change to avoid race conditions
			var categories = options.categories !== undefined ? options.categories : this.getCurrentCategoryFilter();
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
							categoryIds: p.categoryIds || [],
							// Enhanced product data
							stockStatus: p.stockStatus || '',
							onSale: p.onSale || false,
							type: p.type || 'simple',
							primaryCategory: p.primaryCategory || '',
							variationCount: p.variationCount || 0,
							discountPercent: p.discountPercent || 0,
							regularPrice: p.regularPrice || '',
							salePrice: p.salePrice || ''
						};

						self.cache.products.set( product.id, product );

						return product;
					} );
					callback( normalized );
				} )
				.fail( function( jqXHR, textStatus, errorThrown ) {
					// Ignore cancelled requests (expected when user changes selection rapidly)
					if ( 'abort' === textStatus ) {
						callback( [] );
						return;
					}

					if ( WSSCD.Shared && WSSCD.Shared.NotificationService ) {
						WSSCD.Shared.NotificationService.error( 'Failed to load products.' );
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
				this.syncProductState( productIds );
				return Promise.resolve();
			}

			return this.api.getProductsByIds( productIds )
				.then( function( response ) {
					var products = self.extractProducts( response );
					self.addProductOptions( products );
					self.productSelect.setValue( productIds, true );
					self.syncProductState( productIds );
				} )
				.catch( function( error ) {
					WSSCD.ErrorHandler.handle( error, 'picker-restore-products' );
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
		 * Category Filter Model:
		 * - [] (empty) = all categories (no filter applied to product pool)
		 * - ['all'] = UI marker for "All Categories" (equivalent to empty)
		 * Both are treated as "all categories selected" for product filtering purposes.
		 *
		 * @since 1.0.0
		 * @param {Array} categories - Category IDs
		 * @returns {boolean} True if all categories selected
		 */
		isAllCategoriesSelected: function( categories ) {
			// Empty array = all categories (no filter)
			if ( ! categories || 0 === categories.length ) {
				return true;
			}
			// ['all'] = UI marker for all categories
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
			if ( ! window.wsscdWizardData ||
				! window.wsscdWizardData.currentCampaign ||
				! window.wsscdWizardData.currentCampaign.products ||
				! window.wsscdWizardData.currentCampaign.products.selectedProductsData ) {
				return [];
			}

			var preloaded = window.wsscdWizardData.currentCampaign.products.selectedProductsData;
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

		/**
		 * Render category option
		 *
		 * @since 1.0.0
		 * @param {object} data - Category data
		 * @param {function} escape - Escape function
		 * @returns {string} HTML
		 */
		renderCategoryOption: function( data, escape ) {
			// Determine if category is empty
			var isEmpty = 0 === data.count;
			var stockPercent = data.stockPercent || 0;

			// Stock status classification
			var stockStatus = 'empty';
			if ( stockPercent > 0 ) {
				if ( stockPercent >= 75 ) {
					stockStatus = 'healthy';
				} else if ( stockPercent >= 25 ) {
					stockStatus = 'warning';
				} else {
					stockStatus = 'critical';
				}
			}

			// Stock badge
			var stockBadgeHtml = '';
			if ( ! isEmpty ) {
				var stockClass = 'category-stock-badge stock-' + stockStatus;
				var stockIcon = '';
				var stockTitle = '';

				switch ( stockStatus ) {
					case 'healthy':
						stockIcon = WSSCD.IconHelper.get( 'yes', { size: 12 } );
						stockTitle = stockPercent + '% in stock';
						break;
					case 'warning':
						stockIcon = WSSCD.IconHelper.get( 'warning', { size: 12 } );
						stockTitle = stockPercent + '% in stock';
						break;
					case 'critical':
						stockIcon = WSSCD.IconHelper.get( 'dismiss', { size: 12 } );
						stockTitle = stockPercent + '% in stock';
						break;
				}

				if ( stockIcon ) {
					stockBadgeHtml = '<span class="' + stockClass + '" title="' + stockTitle + '">' + stockIcon + '</span>';
				}
			}

			// Product count with subcategory info
			var countText = '';
			if ( data.count !== undefined ) {
				countText = '(' + data.count + ')';
			}

			var subcategoryText = '';
			if ( data.subcategoryCount && data.subcategoryCount > 0 ) {
				subcategoryText = '<span class="category-subcount" title="' + data.subcategoryCount + ' subcategories">' +
					WSSCD.IconHelper.get( 'category', { size: 14 } ) + ' ' + data.subcategoryCount + '</span>';
			}

			// Category image (same pattern as products)
			var hasImage = data.image;
			var imageHtml = hasImage
				? '<img src="' + escape( data.image ) + '" alt="' + escape( data.text ) + '" class="category-image" loading="lazy">'
				: '<span class="category-image-placeholder">' + WSSCD.IconHelper.get( 'category', { size: 20 } ) + '</span>';

			// Tree connector for hierarchical categories
			var treeConnector = '';
			if ( data.level && data.level > 0 ) {
				var isLast = data.isLast || false;
				var connector = isLast ? '\u2514\u2500' : '\u251C\u2500'; // └─ or ├─

				// Add vertical lines for parent levels
				for ( var i = 1; i < data.level; i++ ) {
					treeConnector += '\u2502  '; // │  (vertical line + spaces)
				}
				treeConnector += connector + ' '; // ├─ or └─
			}

			// Build the option HTML
			var optionClass = 'category-option';
			if ( isEmpty ) {
				optionClass += ' category-empty';
			}

			return '<div class="' + optionClass + '" data-level="' + ( data.level || 0 ) + '" data-stock-status="' + stockStatus + '">' +
				( treeConnector ? '<span class="category-tree">' + treeConnector + '</span>' : '' ) +
				'<div class="category-main">' +
					'<span class="category-icon' + ( hasImage ? '' : ' no-image' ) + '">' +
						imageHtml +
					'</span>' +
					'<span class="category-text-wrapper">' +
						'<span class="category-name">' +
							'<span class="category-name-text">' + escape( data.text ) + ' ' + countText + '</span>' +
							subcategoryText +
						'</span>' +
						stockBadgeHtml +
					'</span>' +
				'</div>' +
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
			// Product image with placeholder
			var hasImage = data.image;
			var imageHtml = hasImage
				? '<img src="' + escape( data.image ) + '" alt="' + escape( data.text ) + '" loading="lazy">'
				: '<span class="product-image-placeholder">' + WSSCD.IconHelper.get( 'products', { size: 24 } ) + '</span>';

			// Stock status badge
			var stockBadgeHtml = '';
			if ( data.stockStatus ) {
				var stockClass = 'stock-badge stock-' + data.stockStatus;
				var stockText = '';
				var stockIcon = '';

				switch ( data.stockStatus ) {
					case 'instock':
						stockText = 'In Stock';
						stockIcon = WSSCD.IconHelper.get( 'check', { size: 12 } );
						break;
					case 'outofstock':
						stockText = 'Out of Stock';
						stockIcon = WSSCD.IconHelper.get( 'close', { size: 12 } );
						break;
					case 'onbackorder':
						stockText = 'Backorder';
						stockIcon = WSSCD.IconHelper.get( 'schedule', { size: 12 } );
						break;
				}

				if ( stockText ) {
					stockBadgeHtml = '<span class="' + stockClass + '" title="' + stockText + '">' + stockIcon + '</span>';
				}
			}

			// Sale badge
			var saleBadgeHtml = '';
			if ( data.onSale ) {
				var saleText = 'SALE';
				if ( data.discountPercent && data.discountPercent > 0 ) {
					saleText = '-' + data.discountPercent + '%';
				}
				saleBadgeHtml = '<span class="sale-badge">' + saleText + '</span>';
			}

			// Product type icon
			var typeIconHtml = '';
			if ( data.type ) {
				var typeIcon = '';
				var typeTitle = '';

				switch ( data.type ) {
					case 'simple':
						typeIcon = WSSCD.IconHelper.get( 'products', { size: 14 } );
						typeTitle = 'Simple Product';
						break;
					case 'variable':
						typeIcon = WSSCD.IconHelper.get( 'admin-settings', { size: 14 } );
						typeTitle = 'Variable Product';
						break;
					case 'grouped':
						typeIcon = WSSCD.IconHelper.get( 'list-view', { size: 14 } );
						typeTitle = 'Grouped Product';
						break;
					case 'external':
						typeIcon = WSSCD.IconHelper.get( 'admin-links', { size: 14 } );
						typeTitle = 'External Product';
						break;
				}

				if ( typeIcon ) {
					typeIconHtml = '<span class="product-type-icon" title="' + typeTitle + '">' + typeIcon + '</span>';
				}
			}

			// Variation count for variable products
			var variationCountHtml = '';
			if ( 'variable' === data.type && data.variationCount > 0 ) {
				variationCountHtml = '<span class="product-variation-count" title="Number of variations">' +
					data.variationCount + ' variations</span>';
			}

			// Enhanced price display
			var priceHtml = '';
			if ( data.onSale && data.regularPrice && data.salePrice ) {
				// Show both regular and sale price with discount percentage
				priceHtml = '<span class="product-price-wrapper">' +
					'<span class="product-price-regular">' + escape( data.regularPrice ) + '</span>' +
					'<span class="product-price-sale">' + escape( data.salePrice ) + '</span>' +
				'</span>';
			} else if ( data.price ) {
				// Regular price display
				priceHtml = '<span class="product-price">' + data.price + '</span>';
			}

			// Category tag
			var categoryHtml = '';
			if ( data.primaryCategory ) {
				categoryHtml = '<span class="product-category-tag">' +
					WSSCD.IconHelper.get( 'category', { size: 12 } ) +
					' ' + escape( data.primaryCategory ) +
				'</span>';
			}

			// SKU
			var skuHtml = '';
			if ( data.sku ) {
				skuHtml = '<span class="product-sku" data-sku="' + escape( data.sku ) + '">' + escape( data.sku ) + '</span>';
			}

			return '<div class="wsscd-tom-select-product-option">' +
				'<div class="product-image-wrapper">' +
					'<div class="product-image' + ( hasImage ? '' : ' no-image' ) + '">' +
						imageHtml +
					'</div>' +
					stockBadgeHtml +
				'</div>' +
				'<div class="product-details">' +
					'<div class="product-header">' +
						'<div class="product-name" title="' + escape( data.text ) + '">' + escape( data.text ) + '</div>' +
						saleBadgeHtml +
					'</div>' +
					'<div class="product-meta">' +
						typeIconHtml +
						priceHtml +
						variationCountHtml +
						categoryHtml +
						skuHtml +
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
		 * Sync product select element (for TomSelect to track selections)
		 *
		 * @since 1.0.0
		 * @param {Array} values - Product IDs
		 * @returns {void}
		 */
		syncProductSelect: function( values ) {
			var $originalSelect = $( '#wsscd-product-search' );

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
			var $hiddenField = $( '#wsscd-product-ids-hidden' );

			if ( $hiddenField.length ) {
				var csvValue = values && 0 < values.length ? values.join( ',' ) : '';
				$hiddenField.val( csvValue );
				$hiddenField.trigger( 'change' );
			}
		},

		/**
		 * Sync product state across all data layers
		 * Updates state manager, hidden field, and select element
		 *
		 * @since 1.0.0
		 * @param {Array} productIds - Product IDs to sync
		 * @returns {void}
		 */
		syncProductState: function( productIds ) {
			this.state.setState( { productIds: productIds } );
			this.syncHiddenField( productIds );
			this.syncProductSelect( productIds );
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
						// Categories are preloaded, just set the value
						promises.push( this.setCategoryIds( data.categoryIds ) );
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
		var categoryIds = this.categorySelect ? this.categorySelect.getValue() : [ 'all' ];
		return categoryIds;
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
	/**
	 * Set category IDs only (for category_ids field population)
	 * Simplified - categories are now preloaded via options+items pattern
	 *
	 * Category Filter Model:
	 * - [] (empty) from PHP = all categories (no filter applied to product pool)
	 * - ['all'] = UI marker for "All Categories" option (equivalent to empty)
	 *
	 * @since 1.0.0
	 * @param {Array} value - Category IDs to set
	 * @returns {Promise} Promise that resolves when set
	 */
	setCategoryIds: function( value ) {
		if ( ! this.categorySelect || ! this.categorySelect.instance ) {
			return Promise.resolve();
		}

		// Normalize: empty array from PHP means "all categories" in UI
		var categoriesToSet = value && value.length > 0 ? value : [ 'all' ];

		// Simply set the value - options are already loaded
		this.categorySelect.instance.setValue( categoriesToSet, true );

		// Update state to keep in sync
		this.state.setState( { categoryIds: categoriesToSet } );

		return Promise.resolve();
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
