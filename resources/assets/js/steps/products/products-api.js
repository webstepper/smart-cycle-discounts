/**
 * Products Api
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/steps/products/products-api.js
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress/plugins/smart-cycle-discounts/
 * @since      1.0.0
 */

( function( $ ) {
	'use strict';

	// Register module using utility
	WSSCD.Utils.registerModule( 'WSSCD.Modules.Products', 'API', function( wizard ) {
		this.wizard = wizard;

		if ( !WSSCD.Utils.ensureInitialized( this, {
			'WSSCD.Ajax': WSSCD.Ajax,
			'WSSCD.ErrorHandler': WSSCD.ErrorHandler
		}, 'ProductsAPI' ) ) {
			return;
		}

		this.cache = {
			categories: null,
			categoryTimestamp: 0,
			products: {}, // Keyed by search query + categories
			productTimestamps: {}
		};

		this.cacheDuration = 5 * 60 * 1000;

		this.searchProductsInternalBound = this.searchProductsInternal.bind( this );

		// Keep track of pending deferred for debounced searches
		this.debouncedDeferred = null;

		// Category search debouncing state
		this.categoryDebounceTimer = null;
		this.pendingCategorySearch = null;
	} );

	WSSCD.Modules.Products.API.prototype = {
		/**
		 * Initialize the API module
		 * 
		 * @since 1.0.0
		 * @returns {boolean} Always returns true
		 */
		init: function() {
			// API module is ready - all initialization done in constructor
			// This method exists for consistency with other modules
			return true;
		},

		/**
		 * Search products with debouncing and caching
		 * 
		 * @since 1.0.0
		 * @param {object} params Search parameters
		 * @param {string} params.term Search term
		 * @param {number} params.page Page number
		 * @param {boolean} params.skipDebounce Skip debounce flag
		 * @returns {Promise} jQuery promise
		 */
		searchProducts: function( params ) {
			params = params || {};
			
			// For page 1 with no search term (initial load), skip debouncing
			if ( params.page > 1 || params.skipDebounce || ! params.term ) {
				return this.searchProductsInternal( params );
			}

			// For searches with terms, use debounced version
			var deferred = $.Deferred();

			// Cancel any pending search
			if ( this.pendingSearch ) {
				this.pendingSearch.reject( 'cancelled' );
			}

			this.pendingSearch = deferred;

			// Cancel existing debounce timer
			if ( this.debounceTimer ) {
				clearTimeout( this.debounceTimer );
			}

			// Execute debounced search
			var self = this;
			this.debounceTimer = setTimeout( function() {
				self.searchProductsInternalBound( params )
					.done( function( response ) {
						deferred.resolve( response );
					} )
					.fail( function( error ) {
						if ( 'cancelled' !== error ) {
							deferred.reject( error );
						}
					} );
			}, 300 );

			return deferred.promise();
		},

		/**
		 * Internal product search with caching
		 * 
		 * @since 1.0.0
		 * @private
		 * @param {object} params Search parameters
		 * @returns {Promise} jQuery promise
		 */
		searchProductsInternal: function( params ) {
			params = params || {};
			var categories = params.categories || [];

			var data = {
				search: params.term || '',
				page: params.page || 1,
				perPage: params.perPage || 20,
				categories: categories
			};

			var cacheKey = JSON.stringify( {
				search: data.search,
				page: data.page,
				perPage: data.perPage,
				categories: categories.slice().sort() // Sort for consistent key
			} );
			var now = Date.now();

			if ( this.cache.products[cacheKey] &&
				 this.cache.productTimestamps[cacheKey] &&
				 ( now - this.cache.productTimestamps[cacheKey] ) < this.cacheDuration ) {
				return $.Deferred().resolve( this.cache.products[cacheKey] ).promise();
			}

			// Use centralized AJAX helper
			var self = this;
			
			if ( ! window.WSSCD || ! window.WSSCD.Ajax ) {
				return $.Deferred().reject( 'Ajax not available' ).promise();
			}

			return window.WSSCD.Ajax.post( 'wsscd_product_search', data )
				.done( function( response ) {
					self.cache.products[cacheKey] = response;
					self.cache.productTimestamps[cacheKey] = now;
				} );
		},

		/**
		 * Get product statistics
		 * 
		 * @since 1.0.0
		 * @param {array} productIds Array of product IDs
		 * @returns {Promise} jQuery promise
		 */
		getProductStats: function( productIds ) {
			if ( ! productIds || ! Array.isArray( productIds ) || 0 === productIds.length ) {
				var error = new Error( 'Product IDs are required' );
				if ( window.WSSCD && window.WSSCD.ErrorHandler ) {
					window.WSSCD.ErrorHandler.handle( error, 'ProductsAPI.getProductStats', window.WSSCD.ErrorHandler.SEVERITY.LOW );
				}
				return $.Deferred().reject( error ).promise();
			}

			return WSSCD.Ajax.post( 'wsscd_get_product_stats', {
				productIds: productIds
			} ).fail( function( xhr ) {
				// Automatic error handling via BaseAPI - removed: WSSCD.ErrorHandler.handleAjaxError( xhr, 'wsscd_get_product_stats', { productIds: productIds } );
			} );
		},

		/**
		 * Get product details including categories
		 * 
		 * @since 1.0.0
		 * @param {array} productIds Array of product IDs
		 * @returns {Promise} jQuery promise
		 */
		getProductDetails: function( productIds ) {
			if ( ! productIds || ! Array.isArray( productIds ) || 0 === productIds.length ) {
				var error = new Error( 'Product IDs are required' );
				if ( window.WSSCD && window.WSSCD.ErrorHandler ) {
					window.WSSCD.ErrorHandler.handle( error, 'ProductsAPI.getProductDetails', window.WSSCD.ErrorHandler.SEVERITY.LOW );
				}
				return $.Deferred().reject( error ).promise();
			}

			// Use the existing product search endpoint with the get_products_by_ids action
			return WSSCD.Ajax.post( 'wsscd_product_search', {
				wizardAction: 'get_products_by_ids',
				productIds: productIds
			} ).fail( function( xhr ) {
				// Automatic error handling via BaseAPI - removed: WSSCD.ErrorHandler.handleAjaxError( xhr, 'get_product_details', { productIds: productIds } );
			} );
		},

		/**
		 * Get products by IDs - alias for getProductDetails for compatibility
		 * 
		 * @since 1.0.0
		 * @param {array} productIds Array of product IDs
		 * @returns {Promise} jQuery promise
		 */
		getProductsByIds: function( productIds ) {
			return this.getProductDetails( productIds );
		},

		/**
		 * Get categories
		 * 
		 * @since 1.0.0
		 * @returns {Promise} jQuery promise
		 */
		getCategories: function() {
			if ( ! window.WSSCD || ! window.WSSCD.Ajax ) {
				return $.Deferred().reject( 'Ajax not available' ).promise();
			}

			return window.WSSCD.Ajax.get( 'wsscd_get_categories' );
		},

		/**
	 *
	 * @since 1.0.0
	 * @param {object} params Search parameters
	 * @param {string} params.search Search term
	 * @param {number} params.parent Parent category ID
	 * @param {boolean} params.skipDebounce Skip debounce flag
	 * @param {array} params.ids Specific category IDs to load
	 * @returns {Promise} jQuery promise
	 */
	searchCategories: function( params ) {
		params = params || {};

		// Skip debouncing for:
		// 1. Initial load (no search term)
		// 2. Explicit skipDebounce flag
		// 3. Loading specific IDs (used during restoration)
		if ( ! params.search || params.skipDebounce || ( params.ids && params.ids.length > 0 ) ) {
			return this.searchCategoriesInternal( params );
		}

		// For searches with terms, use debounced version
		var deferred = $.Deferred();

		// Cancel any pending search
		if ( this.pendingCategorySearch ) {
			this.pendingCategorySearch.reject( 'cancelled' );
		}

		this.pendingCategorySearch = deferred;

		// Cancel existing debounce timer
		if ( this.categoryDebounceTimer ) {
			clearTimeout( this.categoryDebounceTimer );
		}

		// Execute debounced search
		var self = this;
		this.categoryDebounceTimer = setTimeout( function() {
			self.searchCategoriesInternal( params )
				.done( function( response ) {
					deferred.resolve( response );
				} )
				.fail( function( error ) {
					if ( 'cancelled' !== error ) {
						deferred.reject( error );
					}
				} );
		}, 300 );

		return deferred.promise();
	},

	/**
	 * Internal category search with caching
	 *
	 * @since 1.0.0
	 * @private
	 * @param {object} params Search parameters
	 * @returns {Promise} jQuery promise
	 */
	searchCategoriesInternal: function( params ) {
		params = params || {};

		var data = {
			wizardAction: 'get_product_categories',
			search: params.search || '',
			parent: params.parent || 0
		};

		if ( params.includeProductCounts ) {
			data.includeProductCounts = true;
		}

		if ( params.ids && 0 < params.ids.length ) {
			data.ids = params.ids;
		}

		if ( ! data.search && 0 === data.parent ) {
			var now = Date.now();
			if ( this.cache.categories &&
				 ( now - this.cache.categoryTimestamp ) < this.cacheDuration ) {
				return $.Deferred().resolve( this.cache.categories ).promise();
			}
		}

		var self = this;
		return WSSCD.Ajax.post( 'wsscd_product_search', data )
			.done( function( response ) {
				if ( !data.search && 0 === data.parent ) {
					self.cache.categories = response;
					self.cache.categoryTimestamp = Date.now();
				}
			} )
			.fail( function( xhr ) {
				// Automatic error handling via BaseAPI - removed: WSSCD.ErrorHandler.handleAjaxError( xhr, 'search_categories', data );
			} );
	},

/**
		 * Load previously saved product selection
		 * 
		 * @since 1.0.0
		 * @returns {Promise} jQuery promise
		 */
		loadSelection: function() {
			if ( ! window.WSSCD || ! window.WSSCD.Ajax ) {
				return $.Deferred().reject( 'Ajax not available' ).promise();
			}

			return window.WSSCD.Ajax.post( 'wsscd_load_data', {
				step: 'products'
			} );
		},

		/**
		 * Clear cache
		 * 
		 * @since 1.0.0
		 * @returns {void}
		 */
		clearCache: function() {
			this.cache = {
				categories: null,
				categoryTimestamp: 0,
				products: {},
				productTimestamps: {}
			};
			
			if ( this.debounceTimer ) {
				clearTimeout( this.debounceTimer );
				this.debounceTimer = null;
			}
			
			// Cancel pending search
			if ( this.pendingSearch ) {
				this.pendingSearch.reject( 'cancelled' );
				this.pendingSearch = null;
			}
			if ( this.categoryDebounceTimer ) {
				clearTimeout( this.categoryDebounceTimer );
				this.categoryDebounceTimer = null;
			}
			
			// Cancel pending category search
			if ( this.pendingCategorySearch ) {
				this.pendingCategorySearch.reject( 'cancelled' );
				this.pendingCategorySearch = null;
			}
		},
		
		/**
		 * Destroy and cleanup
		 * 
		 * @since 1.0.0
		 * @returns {void}
		 */
		destroy: function() {
			this.clearCache();
			
			this.wizard = null;
			this.searchProductsInternalBound = null;
			this.debouncedDeferred = null;
		}
	};

} )( jQuery );