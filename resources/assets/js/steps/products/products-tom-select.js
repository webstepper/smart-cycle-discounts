/**
 * Products Tom Select Module
 * Handles product selection functionality only - category filtering handled by separate module
 *
 * @param $
 * @package SmartCycleDiscounts
 * @since 1.0.0
 */

( function( $ ) {
	'use strict';

	// Register module
	SCD.Utils.registerModule( 'SCD.Modules.Products', 'TomSelect', function( state, api ) {
		if ( !state ) {
			throw new Error( 'ProductsTomSelect module requires state dependency' );
		}
		if ( !api ) {
			throw new Error( 'ProductsTomSelect module requires api dependency' );
		}

		this.state = state;
		this.api = api;
		this.tomSelect = null;
		this.initialized = false;

		// Atomic lock to prevent duplicate initialization during cleanup
		this._isInitializing = false;

		// Pagination state for product search
		this.pagination = {
			currentQuery: '',
			currentPage: 1,
			totalPages: 1,
			isLoading: false
		};

		// Cache for performance
		this.cache = {
			loadedProducts: new Map(),
			productCategoryMap: new Map()
		};

		// Timers and restoration queue
		this.timers = {
			productRestore: null,
			externalSync: null, // Debounce external state synchronization
			categoryFilter: null // Debounce category filter changes to prevent rate limiting
		};

		this.pendingRestoration = null;

		// Initialize event manager
		this.initEventManager();

		// Check dependencies
		this.checkDependencies();
	} );

	// Extend event manager mixin
	SCD.Utils.extend( SCD.Modules.Products.TomSelect.prototype, SCD.Mixins.EventManager );

	SCD.Modules.Products.TomSelect.prototype = SCD.Utils.extend( SCD.Modules.Products.TomSelect.prototype, {
		constructor: SCD.Modules.Products.TomSelect,

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
					// Wait for dependencies with retry mechanism
					self.waitForDependencies( 0 ).then( function() {
						self.bindEvents();
						self.initialized = true;
						resolve();
					} ).catch( function( error ) {
						self.handleInitError( error );
						reject( error );
					} );

				} catch ( error ) {
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
					reject( new Error( 'ProductsTomSelect dependencies not available after ' + maxAttempts + ' attempts' ) );
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
			SCD.ErrorHandler.handle( error, 'products-tom-select-init', SCD.ErrorHandler.SEVERITY.HIGH );

			if ( SCD.Shared && SCD.Shared.NotificationService ) {
				SCD.Shared.NotificationService.error( 'Product search component failed to load. Please refresh the page.' );
			}
		},

		/**
		 * Initialize product search field (wrapper for public API)
		 */
		initializeProductSearch: function() {
			var self = this;

			return this.init().then( function() {
				var currentState = self.state.getState();
				var selectionType = currentState.productSelectionType || 'all_products';

				if ( 'specific_products' === selectionType ) {
					return self.initProductSearch();
				}

				return self;
			} ).catch( function( error ) {
				SCD.ErrorHandler.handle( error, 'products-tom-select-initialize', SCD.ErrorHandler.SEVERITY.HIGH );
				throw error; // Re-throw to propagate the error
			} );
		},

		/**
		 * Initialize product search field
		 */
		initProductSearch: function() {
			var self = this;
			var $productSearch = $( '#scd-product-search' );

			if ( !$productSearch.length ) {
				return Promise.reject( new Error( 'Product search element not found' ) );
			}

			// Prevent duplicate initialization
			if ( this.tomSelect && this.tomSelect.instance ) {
				return Promise.resolve( this );
			}

			// Atomic lock - prevent re-entry during initialization/cleanup
			if ( this._isInitializing ) {
				if ( window.scdDebugTomSelect ) {
					console.warn( '[ProductsTomSelect] Already initializing, skipping duplicate call' );
				}
				return this._initPromise || Promise.resolve( this );
			}

			// Check if native Tom Select instance exists from previous initialization
			// Must wait for async destroy to complete before creating new instance
			if ( $productSearch[0].tomselect ) {
				this._isInitializing = true;

				this._initPromise = new Promise( function( resolve, reject ) {
					var existingInstance = $productSearch[0].tomselect;
					existingInstance.destroy();

					// Wait for destroy to complete (check every 50ms, max 500ms)
					var checkCount = 0;
					var maxChecks = 10;
					var checkInterval = setInterval( function() {
						checkCount++;
						if ( !$productSearch[0].tomselect ) {
							// Destroy complete - now safe to reinitialize
							clearInterval( checkInterval );
							self._isInitializing = false;
							self._initPromise = null;
							// Continue with initialization (not recursive call)
							self._createProductSearchInstance( $productSearch ).then( resolve ).catch( reject );
						} else if ( checkCount >= maxChecks ) {
							// Timeout - force cleanup and try anyway
							clearInterval( checkInterval );
							$productSearch[0].tomselect = null;
							self._isInitializing = false;
							self._initPromise = null;
							self._createProductSearchInstance( $productSearch ).then( resolve ).catch( reject );
						}
					}, 50 );
				} );

				return this._initPromise;
			}

			// Direct initialization (no cleanup needed)
			return this._createProductSearchInstance( $productSearch );
		},

		/**
		 * Create product search Tom Select instance (extracted to avoid recursion)
		 * @private
		 */
		_createProductSearchInstance: function( $productSearch ) {
			var self = this;

			if ( 'undefined' === typeof SCD.Shared.TomSelectBase ) {
				var error = new Error( 'TomSelectBase not loaded' );
				if ( window.scdDebugTomSelect ) {
					console.error( '[ProductsTomSelect]', error.message );
				}
				return Promise.reject( error );
			}

			// Get initially selected products (if editing)
			var initialProducts = this.getInitialProductValues();

			// Create Tom Select instance using base class
			var config = this.getProductSelectConfig();
			this.tomSelect = new SCD.Shared.TomSelectBase( $productSearch[0], config );

			return this.tomSelect.init().then( function() {

				// Mark as initialized so isReady() returns true
				self.initialized = true;

				// Check for pending restoration first (from setValue calls before init)
				if ( self.pendingRestoration ) {
					var pendingIds = self.pendingRestoration;
					self.pendingRestoration = null;
					self.restoreProducts( pendingIds );
				} else {
					// Initialize search with initial products
					self.initializeProductSearchData( initialProducts );
				}
				return self;
			} ).catch( function( error ) {
				SCD.ErrorHandler.handle( error, 'products-tom-select-init-product-search', SCD.ErrorHandler.SEVERITY.HIGH );
				throw error; // Re-throw to propagate the error
			} );
		},

		/**
		 * Get product select configuration
		 */
		getProductSelectConfig: function() {
			var self = this;

			return {
				placeholder: 'Search and select products...',
				searchField: [ 'text', 'sku' ],
				hidePlaceholder: true,
				preload: false, // Disabled to prevent race condition - we load manually in onDropdownOpen
				sortField: [
					{ field: '$order' }, // Preserve insertion order
					{ field: '$score' }  // Then by search relevance
				],

				load: function( query, callback ) {
					self.loadProductsWithCategoryFilter( query, callback );
				},

				render: {
					option: function( data, escape ) {
						return self.renderProductOption( data, escape );
					},
					no_results: function( _data, _escape ) {
						// Check if query is too short (minimum 2 characters required)
						var currentQuery = this.lastQuery || '';
						if ( currentQuery.length > 0 && currentQuery.length < SCD.Shared.TomSelectBase.CONFIG.SEARCH.MIN_CHARS ) {
							return '<div class="no-results">Type at least 2 characters to search...</div>';
						}

						// No results with valid query
						var categories = self.getCurrentCategoryFilter();
						var filterText = self.isAllCategoriesSelected( categories ) ? '' : ' in selected categories';
						return '<div class="no-results">No products found' + filterText + '</div>';
					}
				},

				// Override base event handlers
				onChange: function( value ) {
					self.handleProductSelectionChange( value );
				},

				onDropdownOpen: function( dropdown ) {
					self.onProductDropdownOpen( dropdown );
				},

			};
		},

		/**
		 * Handle product selection change
		 */
		handleProductSelectionChange: function( value ) {
			var self = this;
			var productIds = Array.isArray( value ) ? value.map( String ) : [];
			var previousProducts = this.state.getState().productIds || [];

			if ( this.hasProductChanges( productIds, previousProducts ) ) {
				// Store category data for selected products in state
				// This is CRITICAL for category filtering to work
				productIds.forEach( function( productId ) {
					var product = self.cache.loadedProducts.get( productId );


					if ( product ) {
						// PHP response converts category_ids to categoryIds (camelCase)
						var categories = Array.isArray( product.categoryIds ) ? product.categoryIds : [];


						self.state.storeProductData( productId, {
							id: productId,
							name: product.text || product.name,
							categories: categories
						} );

						if ( window.scdDebugProducts ) {
							if ( categories.length > 0 ) {
							} else {
								console.error( '[ProductsTomSelect] ✗ Product', productId, 'has EMPTY categoryIds! This should not happen if filtered by category.' );
							}
						}
					} else {
						if ( window.scdDebugProducts ) {
							console.error( '[ProductsTomSelect] Product', productId, 'not found in cache!' );
						}
					}
				} );

				// Update state with source tracking - this prevents circular sync
				this.state.setData( 'productIds', productIds, { source: 'tom-select' } );

				// Sync with original select element for form submission
				this.syncOriginalSelect( productIds );

				// Emit change event for other modules
				$( document ).trigger( 'scd:products:selected', {
					selected: productIds,
					previous: previousProducts,
					source: 'tom-select'
				} );

				// Update UI count
				this.updateUICount();
			}
		},

		/**
		 * Load products with current category filter applied
		 */
		loadProductsWithCategoryFilter: function( query, callback ) {
			if ( 'function' !== typeof callback ) {
				return;
			}

			// Prevent concurrent loads
			if ( this.pagination.isLoading ) {
				callback( [] );
				return;
			}

			// Reset pagination for new queries
			if ( query !== this.pagination.currentQuery ) {
				this.resetPagination();
			}

			// Check query length
			if ( query && query.length > 0 && query.length < SCD.Shared.TomSelectBase.CONFIG.SEARCH.MIN_CHARS ) {
				callback( [] );
				return;
			}

			// Load products with filter
			this.loadProducts( query || '', 1, callback );
		},

		/**
		 * Load products from API with category filtering
		 */
		loadProducts: function( query, page, callback ) {
			var self = this;

			// Set loading state
			this.pagination.isLoading = true;
			this.pagination.currentQuery = query;

			// Get current category filter from state or category filter module
			var categories = this.getCurrentCategoryFilter();

			// Convert "all" to empty array (no filter) for backend
			if ( this.isAllCategoriesSelected( categories ) ) {
				categories = [];
			}

			var requestParams = {
				term: query,
				page: page,
				perPage: query ? SCD.Shared.TomSelectBase.CONFIG.SEARCH.PAGE_SIZE : SCD.Shared.TomSelectBase.CONFIG.SEARCH.INITIAL_PAGE_SIZE,
				categories: categories
			};

			// Make API request with category filter
			this.api.searchProducts( requestParams )
				.done( function( response ) {
					self.handleProductLoadSuccess( response, query, page, callback );
				} )
				.fail( function( xhr ) {
					self.handleProductLoadError( xhr, callback );
				} )
				.always( function() {
					self.pagination.isLoading = false;
				} );
		},

		/**
		 * Get current category filter
		 */
		getCurrentCategoryFilter: function() {
			var state = this.state ? this.state.getState() : {};
			return state && state.categoryIds ? state.categoryIds : [ 'all' ];
		},

		/**
		 * Check if "all categories" is selected
		 */
		isAllCategoriesSelected: function( categories ) {
			return categories.length === 1 && 'all' === categories[0];
		},

		/**
		 * Handle successful product load
		 */
		handleProductLoadSuccess: function( response, query, page, callback ) {
			try {
				var products = this.extractProducts( response );

				// Update pagination
				var pagination = response.pagination || ( response.data && response.data.pagination ) || {};
				this.pagination.currentPage = page;
				this.pagination.totalPages = pagination.totalPages || 1;


				// Cache products and their category relationships
				var self = this;
				products.forEach( function( product ) {
					self.cache.loadedProducts.set( product.id, product );
					if ( product.categoryIds && product.categoryIds.length > 0 ) {
						self.cache.productCategoryMap.set( product.id, product.categoryIds );
					}
				} );

				// Return products to Tom Select
				callback( Array.isArray( products ) ? products : [] );

				// Add Load More button if needed
				if ( this.tomSelect &&
					this.tomSelect.isOpen() &&
					this.pagination.currentPage < this.pagination.totalPages ) {
					setTimeout( function() {
						self.addLoadMoreButton();
					}, SCD.Shared.TomSelectBase.CONFIG.TIMEOUTS.LOAD_MORE_UPDATE );
				}

			} catch ( error ) {
				SCD.ErrorHandler.handle( error, 'products-tom-select-load-response' );
				callback( [] );
			}
		},

		/**
		 * Handle product load error
		 */
		handleProductLoadError: function( error, callback ) {
			// Clear loading state
			this.pagination.isLoading = false;

			// Clear Tom Select loading indicator
			if ( this.tomSelect && this.tomSelect.instance ) {
				this.tomSelect.instance.loading = 0;
				this.tomSelect.instance.load_callback = null;
			}

			SCD.ErrorHandler.handleAjaxError( error, 'loadProducts' );

			if ( SCD.Shared && SCD.Shared.NotificationService ) {
				SCD.Shared.NotificationService.error( 'Failed to load products. Please try again.' );
			}

			callback( [] );
		},

		/**
		 * Extract and normalize products from API response
		 */
		extractProducts: function( response ) {
			try {
				var products = [];

				// Extract products from various response structures
				if ( Array.isArray( response ) ) {
					products = response;
				} else if ( response && 'object' === typeof response ) {
					if ( response.data ) {
						products = response.data.products || response.data;
					} else {
						products = response.products || [];
					}
				}

				return this.normalizeProducts( products );

			} catch ( error ) {
				SCD.ErrorHandler.handle( error, 'products-tom-select-extract-products' );
				return [];
			}
		},

		/**
		 * Normalize product data structure
		 */
		normalizeProducts: function( products ) {
			if ( !Array.isArray( products ) ) {
				return [];
			}

			var normalized = [];
			var self = this;

			products.forEach( function( product ) {
				if ( !product ) {
					return;
				}

				var productId = product.id || product.ID || product.product_id;
				if ( !productId ) {
					return;
				}

				// PHP response converts category_ids to categoryIds (camelCase)
				var categories = product.categoryIds || [];
				if ( !Array.isArray( categories ) ) {
					categories = [];
				}

				var normalizedProduct = {
					id: String( productId ),
					text: String( product.name || product.title || product.product_name || 'Unknown Product' ),
					value: String( productId ),
					price: product.price || product.price_html || '',
					sku: product.sku || '',
					image: product.image || product.thumbnail || product.image_url || '',
					categoryIds: categories
				};

				normalized.push( normalizedProduct );

				// Store category mapping for validation
				if ( Array.isArray( normalizedProduct.categoryIds ) && normalizedProduct.categoryIds.length > 0 ) {
					self.cache.productCategoryMap.set(
						normalizedProduct.id,
						normalizedProduct.categoryIds.map( String )
					);
				}
			} );

			return normalized;
		},

		/**
		 * Render product option
		 */
		renderProductOption: function( data, escape ) {
			var image = data.image
				? '<div class="product-image"><img src="' + escape( data.image ) + '" alt=""></div>'
				: '<div class="product-image no-image"><span class="dashicons dashicons-format-image"></span></div>';

			return '<div class="scd-tom-select-product-option">' +
				image +
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
		 * Handle product dropdown open
		 */
		onProductDropdownOpen: function( _dropdown ) {
			var self = this;

			// Manual preload: Load initial products if dropdown has no options yet
			if ( this.tomSelect && this.tomSelect.instance ) {
				var hasOptions = Object.keys( this.tomSelect.instance.options ).length > 0;
				if ( !hasOptions && !this.pagination.isLoading ) {
					// Trigger initial load with empty query
					this.tomSelect.instance.load( '' );
				}
			}

			if ( this.pagination.currentPage < this.pagination.totalPages ) {
				setTimeout( function() {
					self.addLoadMoreButton();
				}, SCD.Shared.TomSelectBase.CONFIG.TIMEOUTS.DROPDOWN_UPDATE );
			}
		},

		/**
		 * Add load more button
		 */
		addLoadMoreButton: function() {
			try {
				if ( !this.tomSelect || !this.tomSelect.instance || !this.tomSelect.instance.dropdown ) {
					return;
				}

				var $dropdown = $( this.tomSelect.instance.dropdown );


				// Remove existing footer
				$dropdown.find( '.scd-tom-select-footer' ).remove();

				// Check if more pages available
				if ( this.pagination.currentPage >= this.pagination.totalPages ) {
					return;
				}

				// Create footer with load more button
				var $footer = $(
					'<div class="scd-tom-select-footer">' +
						'<button type="button" class="scd-load-more-products">' +
							'<span class="dashicons dashicons-arrow-down-alt2"></span> ' +
							'Load More Products' +
						'</button>' +
					'</div>'
				);

				$dropdown.append( $footer );


				// Bind click handler (store reference for cleanup)
				var self = this;
				var clickHandler = function( e ) {
					e.preventDefault();
					e.stopPropagation();
					self.loadMoreProducts();
				};

				$footer.find( '.scd-load-more-products' ).on( 'click', clickHandler );
				$footer.data( 'scd-click-handler', clickHandler ); // Store for cleanup

			} catch ( error ) {
				SCD.ErrorHandler.handle( error, 'products-tom-select-add-load-more' );
			}
		},

		/**
		 * Load more products
		 */
		loadMoreProducts: function() {
			if ( !this.tomSelect || this.pagination.isLoading ) {
				return;
			}

			var self = this;
			var nextPage = this.pagination.currentPage + 1;

			var $button = $( '.scd-load-more-products' );
			var originalText = $button.text();
			$button.prop( 'disabled', true ).text( 'Loading...' );

			this.loadProducts( this.pagination.currentQuery || '', nextPage, function( products ) {
				try {
					products.forEach( function( product ) {
						if ( !self.tomSelect.instance.options[product.id] ) {
							self.tomSelect.instance.addOption( product );
						}
					} );

					self.tomSelect.refreshOptions( false );

					if ( self.pagination.currentPage < self.pagination.totalPages ) {
						$button.prop( 'disabled', false ).text( originalText );
					} else {
						$button.parent().remove();
					}

				} catch ( error ) {
					$button.prop( 'disabled', false ).text( originalText );
					SCD.ErrorHandler.handle( error, 'products-tom-select-load-more-process' );
				}
			} );
		},

		/**
		 * Restore products after filter change
		 */
		restoreProducts: function( productIds ) {
			var self = this;


			if ( !this.tomSelect ) {
				return Promise.resolve();
			}

			// If productIds is empty or null, clear the Tom Select
			if ( !productIds || productIds.length === 0 ) {
				this.tomSelect.instance.clear();
				this.tomSelect.instance.clearOptions();
				this.syncOriginalSelect( [] );
				this._restorationPromise = null;
				return Promise.resolve();
			}

			// Check if we're already restoring - if so, return existing promise
			if ( this._restorationPromise ) {
				return this._restorationPromise;
			}


			this._restorationPromise = new Promise( function( resolve, reject ) {
				try {
					// Single source of truth: Check PHP preloaded data FIRST
					var preloadedData = self.getPreloadedProductData( productIds );

					if ( preloadedData.length === productIds.length ) {
						// All products available in preload - use it (most efficient)

						// Sort preloaded products to match productIds order
						var sortedProducts = [];
						productIds.forEach( function( productId ) {
							var product = preloadedData.find( function( p ) {
								return String( p.id ) === String( productId );
							} );
							if ( product ) {
								sortedProducts.push( self.normalizeProducts( [ product ] )[0] );
							}
						} );

						self.tomSelect.instance.clearOptions();

						// Add options with explicit $order to preserve sequence
						sortedProducts.forEach( function( product, index ) {
							product.$order = index; // Explicit order
							self.tomSelect.instance.addOption( product );
						} );

						// Set values in correct order
						var allValidIds = sortedProducts.map( function( p ) { return p.id; } );
						self.tomSelect.instance.setValue( allValidIds );
						self.syncOriginalSelect( allValidIds.map( String ) );

						self._restorationPromise = null;
						resolve();
						return;
					}

					// Fallback: Load missing products from API
					self.loadProductsByIds( productIds )
						.then( function( response ) {
							var loadedProducts = self.extractProducts( response );

							// Sort products to match productIds order
							var sortedProducts = [];
							productIds.forEach( function( productId ) {
								var product = loadedProducts.find( function( p ) {
									return String( p.id ) === String( productId );
								} );
								if ( product ) {
									sortedProducts.push( product );
								}
							} );

							// Clear and rebuild in correct order
							self.tomSelect.instance.clearOptions();

							// Add options with explicit $order to preserve sequence
							sortedProducts.forEach( function( product, index ) {
								product.$order = index;
								self.tomSelect.instance.addOption( product );
							} );

							// Set values in correct order
							var allValidIds = sortedProducts.map( function( p ) { return p.id; } );
							self.tomSelect.instance.setValue( allValidIds );
							self.syncOriginalSelect( allValidIds.map( String ) );

							// Clear restoration promise
							self._restorationPromise = null;

							resolve();
						} )
						.catch( function( error ) {
							if ( window.scdDebugProducts ) {
								console.error( '[ProductsTomSelect] restoreProducts - API error:', error );
							}
							SCD.ErrorHandler.handle( error, 'products-tom-select-restore-products' );

							// Clear restoration promise on error
							self._restorationPromise = null;

							reject( error );
						} );
				} catch ( error ) {
					SCD.ErrorHandler.handle( error, 'products-tom-select-restore-products-sync' );

					// Clear restoration promise on error
					self._restorationPromise = null;

					reject( error );
				}
			} );

			return this._restorationPromise;
		},

		/**
		 * Load products by specific IDs
		 */
		loadProductsByIds: function( productIds ) {

			if ( !this.api || 'function' !== typeof this.api.getProductsByIds ) {
				if ( window.scdDebugTomSelect ) {
					console.error( '[ProductsTomSelect] loadProductsByIds - API not available' );
				}
				return $.Deferred().reject( 'API not available' ).promise();
			}

			var promise = this.api.getProductsByIds( productIds );

			if ( window.scdDebugTomSelect ) {
				promise.then( function( _response ) {
				} ).catch( function( error ) {
					console.error( '[ProductsTomSelect] loadProductsByIds - error:', error );
				} );
			}

			return promise;
		},

		/**
		 * Initialize product search after Tom Select creation
		 */
		initializeProductSearchData: function( initialProducts ) {
			var self = this;

			if ( initialProducts.length > 0 ) {
				// Add a small delay to ensure TomSelect is fully ready
				setTimeout( function() {
					self.loadSelectedProducts( initialProducts );
				}, 50 );
			}
			// For new campaigns (no initial products), manual preload in onDropdownOpen loads when dropdown opens

			// Force refresh after initialization
			setTimeout( function() {
				if ( self.tomSelect && self.tomSelect.instance ) {
					self.tomSelect.refreshOptions( false );
					// Force recalculation of dropdown position
					if ( self.tomSelect.instance.dropdown ) {
						self.tomSelect.instance.positionDropdown();
					}
				}
			}, 100 );
		},

		/**
		 * Load selected products (for initialization)
		 */
		loadSelectedProducts: function( productIds ) {
			var self = this;

			if ( !this.state || !this.api ) {
				return Promise.reject( 'Dependencies not available' );
			}

			var selectedProducts = productIds || this.state.getState().productIds || [];
			if ( selectedProducts.length === 0 ) {
				return Promise.resolve();
			}

			// Debug logging
			if ( window.scdDebugWizard ) {
				scdDebugWizard( 'products', 'load_selected_products', {
					productIds: selectedProducts,
					hasInstance: !!this.tomSelect
				} );
			}

			// Try preloaded data first
			var preloadedData = this.getPreloadedProductData( selectedProducts );
			if ( preloadedData.length > 0 ) {
				this.addProductOptions( preloadedData );
				var stringIds = selectedProducts.map( String );
				self.tomSelect.instance.setValue( stringIds );
				this.syncOriginalSelect( stringIds );
				return Promise.resolve();
			}

			// Load from API
			return this.loadProductsByIds( selectedProducts )
				.then( function( response ) {
					var products = self.extractProducts( response );
					if ( products && products.length > 0 ) {
						self.addProductOptions( products );
						var stringIds = selectedProducts.map( String );
						self.tomSelect.instance.setValue( stringIds );
						self.syncOriginalSelect( stringIds );
					}
				} )
				.catch( function( error ) {
					SCD.ErrorHandler.handle( error, 'products-tom-select-load-selected-products' );
					if ( SCD.Shared && SCD.Shared.NotificationService ) {
						SCD.Shared.NotificationService.error( 'Failed to load selected products. Please try refreshing the page.' );
					}
				} );
		},

		/**
		 * Get preloaded product data
		 */
		getPreloadedProductData: function( productIds ) {
			// Single source of truth: window.scdWizardData.current_campaign
			if ( !window.scdWizardData ||
				!window.scdWizardData.current_campaign ||
				!window.scdWizardData.current_campaign.products ||
				!window.scdWizardData.current_campaign.products.selected_products_data ) {
				return [];
			}

			var preloadedData = window.scdWizardData.current_campaign.products.selected_products_data;
			var productIdStrings = productIds.map( String );

			return preloadedData.filter( function( product ) {
				return productIdStrings.indexOf( String( product.id ) ) !== -1;
			} );
		},

		/**
		 * Add product options
		 */
		addProductOptions: function( products ) {
			if ( !this.tomSelect ) {
				return;
			}

			var self = this;
			products.forEach( function( product ) {
				var option = {
					id: String( product.id ),
					value: String( product.id ),
					text: product.name || product.text,
					price: product.price || '',
					sku: product.sku || '',
					image: product.image || ''
				};

				if ( !self.tomSelect.instance.options[option.id] ) {
					self.tomSelect.instance.addOption( option );
				}
			} );
		},

		/**
		 * Get initial product values
		 */
		getInitialProductValues: function() {
			// Defensive check for state availability
			if ( !this.state || 'function' !== typeof this.state.getState ) {
				return []; // Default fallback
			}

			var state = this.state.getState();
			var selectedProducts = state && state.productIds ? state.productIds : [];

			// Also check if it's an array - if not, convert to array
			if ( !Array.isArray( selectedProducts ) ) {
				selectedProducts = selectedProducts ? [ selectedProducts ] : [];
			}

			return selectedProducts.map( String );
		},

		/**
		 * Check if products have changed
		 */
		hasProductChanges: function( newProducts, previousProducts ) {
			if ( newProducts.length !== previousProducts.length ) {
				return true;
			}

			return !newProducts.every( function( id ) { 
				return previousProducts.indexOf( id ) !== -1; 
			} );
		},

		/**
		 * Reset pagination
		 */
		resetPagination: function() {
			this.pagination.currentPage = 1;
			this.pagination.totalPages = 1;
			this.pagination.currentQuery = '';
			this.pagination.isLoading = false;
		},

		/**
		 * Update UI count
		 */
		updateUICount: function() {
			if ( window.scdProductsOrchestrator &&
				window.scdProductsOrchestrator.modules &&
				window.scdProductsOrchestrator.modules.selector &&
				'function' === typeof window.scdProductsOrchestrator.modules.selector.updateSelectedCount ) {
				window.scdProductsOrchestrator.modules.selector.updateSelectedCount();
			}
		},

		/**
		 * Sync with original select element
		 */
		syncOriginalSelect: function( values ) {
			var $originalSelect = $( '#scd-product-search' );

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

				// Trigger change event
				$originalSelect.trigger( 'change' );
			}
		},


		/**
		 * Handle category filter changes from external module
		 * Category filter affects SEARCH ONLY, not user selections
		 * Debounced to prevent rate limit errors when user changes categories rapidly
		 */
		filterByCategories: function( _categories ) {
			var self = this;

			// Clear any pending category filter reload
			if ( this.timers.categoryFilter ) {
				clearTimeout( this.timers.categoryFilter );
			}

			// Clear API cache to force fresh data with new category filter
			if ( this.api && 'function' === typeof this.api.clearCache ) {
				this.api.clearCache();
			}

			// Reset pagination for new search context
			this.resetPagination();

			// Debounce the reload to prevent rate limiting
			// Wait 400ms after last category change before reloading
			this.timers.categoryFilter = setTimeout( function() {
				if ( self.tomSelect && self.tomSelect.instance ) {
					var instance = self.tomSelect.instance;
					var wasOpen = instance.isOpen;

					// ROOT CAUSE FIX: Preserve selected products when clearing options
					//
					// CRITICAL BUG: clearOptions() removes ALL options, including options for selected items
					// This creates inconsistent state: instance.items = ["123"] but instance.options = {}
					// Tom Select cannot render items without their option data, causing dropdown to fail
					//
					// Solution: Save selected items, clear options, restore selected items with their data
					// This ensures Tom Select always has option data for selected items

					// Save currently selected products BEFORE clearing options
					var selectedProducts = instance.items ? instance.items.slice() : [];

					// Reset Tom Select's loaded state so it knows to reload on next open
					if ( instance.loadedSearches ) {
						instance.loadedSearches = {};
					}
					// CRITICAL: Set lastQuery to null (not '') to force fresh search
					// Setting to '' causes search('') to match lastQuery='' and use cached path
					instance.lastQuery = null;
					// Initialize currentResults with valid empty structure to prevent errors
					instance.currentResults = { items: [], options: [], query: '', tokens: [] };
					// Clear the search input field
					if ( instance.control_input ) {
						instance.control_input.value = '';
					}
					// Clear options to force reload with new category filter
					// This removes all search result options but also removes selected items' options
					instance.clearOptions();

					// CRITICAL: Restore selected products to prevent inconsistent state
					// Without this, instance.items contains IDs but instance.options is empty
					// Tom Select cannot render the control or open dropdown in this state
					if ( selectedProducts.length > 0 ) {
						self.restoreProducts( selectedProducts ).then( function() {
							// After restoration, reload if dropdown was open
							if ( wasOpen ) {
								instance.close();
								setTimeout( function() {
									instance.open();
								}, 50 );
							}
						} ).catch( function( error ) {
							// Handle restoration errors gracefully
							if ( window.scdDebugTomSelect ) {
								console.error( '[ProductsTomSelect] Failed to restore products after category filter:', error );
							}
							// Still try to reopen if it was open
							if ( wasOpen ) {
								instance.close();
								setTimeout( function() {
									instance.open();
								}, 50 );
							}
						} );
					} else {
						// No selected products - just reload if dropdown was open
						// If closed, products will load fresh when user next opens the dropdown (via manual preload)
						if ( wasOpen ) {
							// Close and reopen to trigger fresh load with new category filter
							instance.close();
							// Small delay to ensure close completes before reopening
							setTimeout( function() {
								instance.open();
							}, 50 );
						}
					}
				}

				// Clear timer reference
				self.timers.categoryFilter = null;
			}, 400 ); // 400ms debounce delay
		},

		/**
		 * Bind module events
		 */
		bindEvents: function() {
			var self = this;

			try {
				// Listen for external state changes
				this.bindCustomEvent( 'scd:products:state:changed', function( e, data ) {
					self.handleExternalStateChange( data );
				} );

				// Listen for category changes from category filter module
				this.bindCustomEvent( 'scd:categories:changed', function( e, data ) {

					if ( data && data.categories ) {
						// Validate categories array
						var categories = Array.isArray( data.categories ) ? data.categories : [ data.categories ];


						self.filterByCategories( categories );
					}
				} );

				// Listen for product selection type changes
				this.bindCustomEvent( 'scd:products:init-tom-select', function() {
					var $productSearch = $( '#scd-product-search' );
					if ( $productSearch.length && !$productSearch[0].tomselect ) {
						self.initProductSearch();
					}
				} );

			} catch ( error ) {
				SCD.ErrorHandler.handle( error, 'products-tom-select-bind-events' );
			}
		},

		/**
		 * Handle external state changes
		 */
		handleExternalStateChange: function( data ) {
			if ( !data || !data.property ) {
				return;
			}

			// Don't sync back changes that originated from TomSelect itself
			if ( 'productIds' === data.property && 'tom-select' === data.source ) {
				return;
			}

			// Debounce external updates to prevent rapid circular update cycles
			var self = this;
			clearTimeout( this.timers.externalSync );

			this.timers.externalSync = setTimeout( function() {
				switch ( data.property ) {
					case 'productIds':
						// Sync changes from other sources (category filter, import, restore)
						self.syncProductsWithState( data.value );
						break;

					case 'productSelectionType':
						self.handleSelectionTypeChange( data.value );
						break;
				}
			}, 50 ); // 50ms delay to batch rapid changes
		},

		/**
		 * Sync products with state (only called for external changes, not TomSelect-originated changes)
		 */
		syncProductsWithState: function( products ) {
			if ( !this.tomSelect || !this.tomSelect.instance ) {
				return;
			}


			// Use the module's setValue method, not TomSelectBase's setValue
			// This ensures proper handling of product restoration
			this.setValue( products );
		},

		/**
		 * Handle selection type change
		 */
		handleSelectionTypeChange: function( selectionType ) {
			if ( 'specific_products' === selectionType ) {
				// Initialize product search if not already initialized
				var $productSearch = $( '#scd-product-search' );

				if ( $productSearch.length && !$productSearch[0].tomselect ) {
					// Delay initialization to ensure the element is visible
					var self = this;
					setTimeout( function() {
						// Check if element is now visible
						var $container = $productSearch.closest( '.scd-specific-products' );
						var isVisible = $container.hasClass( 'scd-active-section' );

						if ( isVisible ) {
							self.initProductSearch();
						} else {
							// Try again after another short delay
							setTimeout( function() {
								if ( $container.hasClass( 'scd-active-section' ) ) {
									self.initProductSearch();
								}
							}, 50 );
						}
					}, 100 );
				}
			}
		},

		/**
		 * Complex field handler interface methods
		 */
		isReady: function() {
			return this.initialized;
		},

		getSelectedProducts: function() {
			// If product instance exists, get values from it
			if ( this.tomSelect ) {
				var values = this.tomSelect.getValue();
				return Array.isArray( values ) ? values : ( values ? [ values ] : [] );
			}

			// Fallback: Try to get from state
			if ( this.state && 'function' === typeof this.state.getState ) {
				var state = this.state.getState();
				var productIds = state.productIds || [];
				return Array.isArray( productIds ) ? productIds : ( productIds ? [ productIds ] : [] );
			}

			// Final fallback: Check the original select element
			var $originalSelect = $( '#scd-product-search' );
			if ( $originalSelect.length ) {
				var selectedValues = $originalSelect.val();
				if ( selectedValues ) {
					return Array.isArray( selectedValues ) ? selectedValues : [ selectedValues ];
				}
			}

			return [];
		},

		getValue: function() {
			var productIds = this.getSelectedProducts();
			return productIds;
		},

		/**
		 * Show validation error
		 */
		showError: function( message ) {
			if ( this.tomSelect ) {
				this.tomSelect.showError( message );
			}
		},

		/**
		 * Clear validation error
		 */
		clearError: function() {
			if ( this.tomSelect ) {
				this.tomSelect.clearError();
			}
		},

		setValue: function( productIds ) {

			// Normalize to array of strings
			var normalizedIds = Array.isArray( productIds ) ? productIds.map( String ) : [];

			// If Tom Select isn't ready yet, queue the values to be set later
			if ( !this.tomSelect ) {
				this.pendingRestoration = normalizedIds;
				return;
			}

			this.restoreProducts( normalizedIds );
		},

		/**
		 * Destroy and cleanup
		 */
		destroy: function() {
			try {
				// Clear timers
				clearTimeout( this.timers.productRestore );
				clearTimeout( this.timers.externalSync );
				clearTimeout( this.timers.categoryFilter );

				// Clear pending restoration
				this.pendingRestoration = null;

				// Cleanup "Load More" button event handler before destroying Tom Select
				if ( this.tomSelect && this.tomSelect.instance && this.tomSelect.instance.dropdown ) {
					var $dropdown = $( this.tomSelect.instance.dropdown );
					var $footer = $dropdown.find( '.scd-tom-select-footer' );
					if ( $footer.length ) {
						var clickHandler = $footer.data( 'scd-click-handler' );
						if ( clickHandler ) {
							$footer.find( '.scd-load-more-products' ).off( 'click', clickHandler );
						}
						$footer.remove();
					}
				}

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
				this._isInitializing = false;
				this._initPromise = null;

				// Clear cache
				this.cache.loadedProducts.clear();
				this.cache.productCategoryMap.clear();

			} catch ( error ) {
				SCD.ErrorHandler.handle( error, 'products-tom-select-destroy' );
			}
		}
	} );

} )( jQuery );