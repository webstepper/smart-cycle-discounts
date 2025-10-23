/**
 * Products Step State Manager
 *
 * Manages state for product selection following Option 1 naming conventions.
 * Uses camelCase internally with boundary conversion for DOM/PHP interaction.
 *
 * @param _$
 * @package SmartCycleDiscounts
 * @since 1.0.0
 */

( function( _$ ) {
	'use strict';

	// Register module using utility
	SCD.Utils.registerModule( 'SCD.Modules.Products', 'State', function() {
		// Initialize state data with camelCase naming (Option 1)
		this.data = {
			productIds: [],
			categoryIds: [ 'all' ], // Default to "All Categories"
			tagIds: [],
			searchTerm: '',
			filters: {},
			productSelectionType: 'all_products', // Match field definition name
			randomCount: 10,
			conditionsLogic: 'all',
			smartCriteria: '', // For smart selection type
			conditions: [], // Array of condition objects
			totalProducts: 0,
			productData: {} // Cache product information
		};

		this.errors = {};
		this.dirty = false;
		this.validated = false;

		// Filtering state
		this.filteringInProgress = false;
		this.filterTimeout = null;

		// Initialize event manager
		this.initEventManager();
	} );

	SCD.Modules.Products.State.prototype = {
		/**
		 * Set API reference
		 * @param api
		 */
		setApi: function( api ) {
			this.api = api;
		},

		/**
		 * Set data with validation
		 *
		 * @since 1.0.0
		 * @param {string|object} key Property name or object of properties
		 * @param {*} value Value to set (if key is string)
		 * @param {object} options Optional settings {source: 'module-name'}
		 * @returns {void}
		 */
		setData: function( key, value, options ) {
			options = options || {};

			if ( 'object' === typeof key ) {
				// Bulk update - check for actual changes
				var hasChanges = false;
				var changedProperties = {};

				for ( var prop in key ) {
					if ( Object.prototype.hasOwnProperty.call( key, prop ) ) {
						var oldValue = this.data[prop];
						var newValue = key[prop];

						// Only update if value actually changed
						if ( ! this._isEqual( oldValue, newValue ) ) {
							this.data[prop] = newValue;
							changedProperties[prop] = { old: oldValue, new: newValue };
							hasChanges = true;
						}
					}
				}

				// Only trigger events if something changed
				if ( hasChanges ) {
					this.dirty = true;
					// Trigger individual change events for each property
					for ( var changedProp in changedProperties ) {
						if ( Object.prototype.hasOwnProperty.call( changedProperties, changedProp ) ) {
							this.triggerChange( changedProp, changedProperties[changedProp].old, options.source );
						}
					}
				}
			} else {
				var previousValue = this.data[key];
				// Only update if value actually changed
				if ( ! this._isEqual( previousValue, value ) ) {
					this.data[key] = value;
					this.dirty = true;
					this.triggerChange( key, previousValue, options.source );
				}
			}
		},

		/**
		 * Get state - single method for retrieving state data
		 * @param {string} key - Optional key to get specific value
		 * @returns {*} State data or specific value
		 */
		getState: function( key ) {
			if ( key ) {
				return SCD.Utils.get( this.data, key );
			}
			return this.data;
		},

		/**
		 * Get value of a specific field (alias for getState with key)
		 * @param {string} fieldName Field name to retrieve
		 * @returns {*} Field value or undefined
		 */
		getData: function( fieldName ) {
			return this.getState( fieldName );
		},

		/**
		 * Normalize update keys - converts snake_case to camelCase for internal use
		 * @param updates
		 * @private
		 */
		normalizeUpdateKeys: function( updates ) {
			if ( !updates || 'object' !== typeof updates ) {
				return {};
			}

			var normalized = {};
			var keyMap = {
				product_ids: 'productIds',
				category_ids: 'categoryIds',
				tag_ids: 'tagIds',
				search_term: 'searchTerm',
				selection_type: 'productSelectionType', // Map old key to new
				product_selection_type: 'productSelectionType', // Also support correct key
				random_count: 'randomCount',
				conditions_logic: 'conditionsLogic',
				smart_criteria: 'smartCriteria',
				total_products: 'totalProducts',
				product_data: 'productData',
				selected_count: 'selectedCount',
				selection_percentage: 'selectionPercentage'
			};

			// Convert keys using map, preserving camelCase keys as-is
			for ( var key in updates ) {
				if ( Object.prototype.hasOwnProperty.call( updates, key ) ) {
					var normalizedKey = keyMap[key] || key;
					normalized[normalizedKey] = updates[key];
				}
			}

			return normalized;
		},

		/**
		 * Set state (for compatibility with Tom Select module)
		 * Handles both camelCase and snake_case inputs for backward compatibility
		 * @param updates
		 */
		setState: function( updates ) {
			// Convert snake_case keys to camelCase
			var normalizedUpdates = this.normalizeUpdateKeys( updates );

			// Track which properties changed to avoid duplicate events
			var changedProperties = {};
			var hasChanges = false;

			// Handle category selection logic here
			if ( normalizedUpdates.categoryIds !== undefined ) {
				var processedCategories = this.processCategorySelection( normalizedUpdates.categoryIds );
				// Only update if actually changed
				if ( JSON.stringify( processedCategories ) !== JSON.stringify( this.data.categoryIds ) ) {
					changedProperties.categoryIds = {
						old: this.data.categoryIds,
						new: processedCategories
					};
					this.data.categoryIds = processedCategories;
					hasChanges = true;
				}
				delete normalizedUpdates.categoryIds;
			}

			// Handle product selection
			if ( normalizedUpdates.productIds !== undefined ) {
				var processedProducts = this.processProductSelection( normalizedUpdates.productIds );
				// Only update if actually changed
				if ( JSON.stringify( processedProducts ) !== JSON.stringify( this.data.productIds ) ) {
					changedProperties.productIds = {
						old: this.data.productIds,
						new: processedProducts
					};
					this.data.productIds = processedProducts;
					hasChanges = true;
				}
				delete normalizedUpdates.productIds;
			}

			// Special handling for productSelectionType
			if ( normalizedUpdates.productSelectionType !== undefined ) {
				if ( this.data.productSelectionType !== normalizedUpdates.productSelectionType ) {
					changedProperties.productSelectionType = {
						old: this.data.productSelectionType,
						new: normalizedUpdates.productSelectionType
					};
					this.data.productSelectionType = normalizedUpdates.productSelectionType;
					hasChanges = true;
				}
				delete normalizedUpdates.productSelectionType;
			}

			// Update remaining properties
			for ( var key in normalizedUpdates ) {
				if ( Object.prototype.hasOwnProperty.call( normalizedUpdates, key ) ) {
					var oldValue = this.data[key];
					var newValue = normalizedUpdates[key];

					// Check if value actually changed (avoid triggering for same value)
					if ( JSON.stringify( oldValue ) !== JSON.stringify( newValue ) ) {
						changedProperties[key] = {
							old: oldValue,
							new: newValue
						};
						hasChanges = true;
					}
				}
			}

			// Apply all updates AFTER comparison
			for ( var updateKey in normalizedUpdates ) {
				if ( Object.prototype.hasOwnProperty.call( normalizedUpdates, updateKey ) ) {
					this.data[updateKey] = normalizedUpdates[updateKey];
				}
			}

			// Only trigger change events if something actually changed
			if ( hasChanges ) {
				this.dirty = true;

				// Batch trigger change events for all changed properties
				// This prevents duplicate events for the same change
				for ( var prop in changedProperties ) {
					if ( Object.prototype.hasOwnProperty.call( changedProperties, prop ) ) {
						this.triggerChange( prop, changedProperties[prop].old );
					}
				}
			}
		},

		/**
		 * Process category selection with mutual exclusivity logic
		 * @param categories
		 */
		processCategorySelection: function( categories ) {
			// Ensure array format and handle null/undefined
			if ( !Array.isArray( categories ) ) {
				categories = categories ? [ categories ] : [ 'all' ]; // Default to "All Categories"
			}

			// Remove empty/invalid values and convert to strings for consistent comparison
			categories = categories.filter( function( cat ) {
				return null !== cat && cat !== undefined && '' !== cat;
			} ).map( function( cat ) {
				return String( cat );
			} );

			// If no valid categories remain, default to "All Categories"
			if ( 0 === categories.length ) {
				categories = [ 'all' ];
			}

			// Handle "All Categories" mutual exclusivity logic
			var previousCategories = this.data.categoryIds || [ 'all' ];
			var allWasSelected = -1 !== previousCategories.indexOf( 'all' );
			var allIsNowSelected = -1 !== categories.indexOf( 'all' );

			// Case 1: "All Categories" is selected along with other categories
			if ( allIsNowSelected && 1 < categories.length ) {
				if ( !allWasSelected ) {
					// User just added "All Categories" - it takes precedence, remove all others
					categories = [ 'all' ];
				} else {
					// "All Categories" was already selected, user added specific categories - remove "All Categories"
					categories = categories.filter( function( cat ) {
						return 'all' !== cat;
					} );
					// If filtering removed everything, default back to "All Categories"
					if ( 0 === categories.length ) {
						categories = [ 'all' ];
					}
				}
			}
			// Case 2: No categories selected (shouldn't happen due to earlier check, but safety)
			else if ( 0 === categories.length ) {
				categories = [ 'all' ];
			}

			// Check if categories actually changed before filtering products
			var previousCategoriesSorted = previousCategories.slice().sort();
			var categoriesSorted = categories.slice().sort();
			var categoriesChanged = JSON.stringify( categoriesSorted ) !== JSON.stringify( previousCategoriesSorted );


			// When categories change, remove products that don't match
			// This enforces that selected products MUST belong to selected categories
			if ( categoriesChanged && 'specific_products' === this.data.productSelectionType ) {
				if ( 'all' === categories[0] ) {
					// "All Categories" selected - keep all products
				} else {
					// Specific categories selected - remove non-matching products
					this.filterProductsByCategories( categories );
				}
			}

			// Update total product count when categories change for all selection types
			if ( categoriesChanged ) {
				this.updateTotalProductCount( categories );
			}

			return categories;
		},

		/**
		 * Process product selection
		 * @param products
		 */
		processProductSelection: function( products ) {
			// Ensure array format
			if ( !Array.isArray( products ) ) {
				products = products ? [ products ] : [];
			}

			// Convert to strings and validate
			return products.map( function( id ) {
				return String( parseInt( id, 10 ) );
			} ).filter( function( id ) {
				return 'NaN' !== id && '0' !== id;
			} );
		},

		/**
		 * Set search filters
		 * @param filters
		 */
		setFilters: function( filters ) {
			this.data.filters = SCD.Utils.extend( this.data.filters, filters || {} );
			this.dirty = true;
			this.triggerChange( 'filters' );
		},

		/**
		 * Get selected product count
		 */
		getSelectedCount: function() {
			return this.data.productIds ? this.data.productIds.length : 0;
		},

		/**
		 * Check if product is selected
		 * @param productId
		 */
		isProductSelected: function( productId ) {
			if ( SCD.Utils.isEmpty( this.data.productIds ) ) {
				return false;
			}

			// Ensure string comparison
			productId = String( productId );
			return -1 !== this.data.productIds.indexOf( productId );
		},

		/**
		 * Get selected product IDs
		 */
		getSelectedProductIds: function() {
			// productIds already stores IDs as strings
			return this.data.productIds || [];
		},

		/**
		 * Validate state using ValidationManager
		 */
		validate: function() {
			if ( !window.SCD || !window.SCD.ValidationManager ) {
				// ValidationManager not available
				this.errors = { validation: 'ValidationManager not available' };
				this.validated = true;
				return false;
			}

			// Use ValidationManager for step validation
			var self = this;
			return window.SCD.ValidationManager.validateStep( 'products' )
				.done( function( isValid ) {
					if ( isValid ) {
						self.errors = {};
					} else {
						// Get validation errors from ValidationManager
						var validationState = window.SCD.ValidationManager.getValidationState( 'products' );
						self.errors = validationState.errors || {};
					}
					self.validated = true;
					return isValid;
				} )
				.fail( function() {
					self.errors = { validation: 'Validation failed' };
					self.validated = true;
					return false;
				} );
		},

		/**
		 * Trigger change event - only triggers if value actually changed
		 * @param property
		 * @param oldValue
		 * @param source - Optional source identifier (e.g., 'tom-select', 'category-filter')
		 */
		triggerChange: function( property, oldValue, source ) {
			var eventData = {
				data: this.data,
				property: property || null,
				source: source || null
			};

			if ( property ) {
				eventData.value = this.data[property];
				if ( oldValue !== undefined ) {
					eventData.oldValue = oldValue;
				}
			}

			// Immediately trigger the event
			this.triggerCustomEvent( 'scd:products:state:changed', [ eventData ] );
		},

		/**
		 * Store product data in cache
		 * @param productId
		 * @param data
		 */
		storeProductData: function( productId, data ) {
			this.data.productData[String( productId )] = data;
		},

		/**
		 * Filter selected products by categories
		 * @param categories
		 */
		filterProductsByCategories: function( categories ) {
			var self = this;


			// Clear any pending filter operation
			if ( this.filterTimeout ) {
				clearTimeout( this.filterTimeout );
			}

			// Debounce the filtering to avoid multiple executions
			this.filterTimeout = setTimeout( function() {
				self.filterProductsByCategoriesDebounced( categories );
			}, 300 );
		},

		/**
		 * Internal debounced filter method
		 * @param categories
		 */
		filterProductsByCategoriesDebounced: function( categories ) {
			var self = this;


			// Prevent multiple concurrent filtering operations
			if ( this.filteringInProgress ) {
				return;
			}

			// If we don't have selected products, nothing to filter
			if ( !this.data.productIds || 0 === this.data.productIds.length ) {
				return;
			}

			// Check if we have category data for all selected products
			var needsApiCall = false;
			this.data.productIds.forEach( function( productId ) {
				var productData = self.data.productData[productId];
				if ( !productData || !productData.categories ) {
					needsApiCall = true;
				}
			} );


			if ( needsApiCall ) {

				// Set flag to prevent multiple API calls
				this.filteringInProgress = true;

				// We need to fetch product data to know their categories
				if ( SCD.Shared && SCD.Shared.NotificationService ) {
					SCD.Shared.NotificationService.info(
						'Products will be filtered based on selected categories. Please wait...',
						{
							id: 'products-filtering-loading',
							replace: true
						}
					);
				}

				// Trigger API call to get product details
				if ( this.api && this.api.getProductDetails ) {
					this.api.getProductDetails( this.data.productIds )
						.done( function( response ) {

							// Handle different response formats
							var products = null;
							if ( response.success && response.data && response.data.products ) {
								// Standard format: {success: true, data: {products: [...]}}
								products = response.data.products;
							} else if ( response.products && Array.isArray( response.products ) ) {
								// Direct format: {products: [...]}
								products = response.products;
							}

							// Store product data with categories
							if ( products && products.length > 0 ) {
								products.forEach( function( product ) {
									if ( product.id && product.categoryIds ) {
										self.storeProductData( product.id, {
											categories: product.categoryIds || []
										} );
									}
								} );

								// Now filter the products
								self.filterProductsByCategoriesWithData( categories );

								// Clear the loading notification
								if ( SCD.Shared && SCD.Shared.NotificationService ) {
									SCD.Shared.NotificationService.dismiss( 'products-filtering-loading' );
								}
							} else {
								console.error( '[Products State] API response has no products:', response );
							}
						} )
						.fail( function( error ) {
							console.error( '[Products State] API call failed:', error );
						} )
						.always( function() {
							// Reset the flag
							self.filteringInProgress = false;

							// Clear loading notification in case of error
							if ( SCD.Shared && SCD.Shared.NotificationService ) {
								SCD.Shared.NotificationService.dismiss( 'products-filtering-loading' );
							}
						} );
				} else {
					// Reset the flag if no API available
					console.error( '[Products State] API or getProductDetails method not available!' );
					this.filteringInProgress = false;
				}
			} else {
				// We have all the data, filter immediately
				this.filterProductsByCategoriesWithData( categories );
			}
		},

		/**
		 * Filter products when we have category data
		 * @param categories
		 */
		filterProductsByCategoriesWithData: function( categories ) {
			var self = this;
			var filteredProducts = [];
			var removedProducts = [];


			// If 'all' is selected, keep all products without filtering
			if ( 'all' === categories[0] ) {
				return; // Early return - don't filter anything
			}

			// Filter products based on selected categories
			this.data.productIds.forEach( function( productId ) {
				var productData = self.data.productData[productId];
				var keepProduct = false;


				if ( productData && productData.categories ) {
					// Check if product belongs to any of the selected categories
					// Convert both to strings for comparison
					var productCategories = productData.categories.map( function( id ) {
						return String( id );
					} );


					productCategories.forEach( function( productCatId ) {
						if ( -1 !== categories.indexOf( productCatId ) ) {
							keepProduct = true;
						}
					} );
				} else {
					// If we don't have category data, this is an error state
					// Product should have been stored with category data when selected
					keepProduct = false;
				}


				if ( keepProduct ) {
					filteredProducts.push( productId );
				} else {
					removedProducts.push( productId );
				}
			} );

			// Update selected products only if there are changes
			if ( 0 < removedProducts.length ) {
				this.data.productIds = filteredProducts;
				this.dirty = true;
				this.triggerChange( 'productIds' );

				// Show notification about removed products
				if ( SCD.Shared && SCD.Shared.NotificationService ) {
					var message = 1 === removedProducts.length
						? '1 product was removed because its category was deselected.'
						: removedProducts.length + ' products were removed because their categories were deselected.';

					// Use a unique ID to prevent duplicate notifications
					SCD.Shared.NotificationService.info( message, {
						id: 'products-filtered-by-category',
						replace: true,
						timeout: 4000  // Auto-dismiss after 4 seconds
					} );
				}
			}
		},

		/**
		 * Update total product count based on categories
		 * This is called when categories change for any selection type
		 * @param categories
		 */
		updateTotalProductCount: function( categories ) {
			var self = this;

			// Skip if we don't have the API module
			if ( !this.api || 'function' !== typeof this.api.searchProducts ) {
				return;
			}

			// Convert 'all' to empty array for the API
			var categoryFilter = categories;
			if ( categories && 1 === categories.length && 'all' === categories[0] ) {
				categoryFilter = [];
			}

			// Fetch product count for the selected categories
			this.api.searchProducts( {
				term: '',
				page: 1,
				perPage: 1, // We only need the count, not the actual products
				categories: categoryFilter
			} )
				.done( function( response ) {
					var totalProducts = 0;

					// Extract total count from response
					if ( response && response.pagination && response.pagination.totalItems !== undefined ) {
						totalProducts = parseInt( response.pagination.totalItems, 10 ) || 0;
					} else if ( response && response.total !== undefined ) {
						totalProducts = parseInt( response.total, 10 ) || 0;
					}

					// Update state with new total
					if ( self.data.totalProducts !== totalProducts ) {
						self.data.totalProducts = totalProducts;
						self.triggerChange( 'totalProducts', self.data.totalProducts );
					}
				} )
				.fail( function( error ) {
				// Log error but don't break functionality
					if ( window.SCD && window.SCD.ErrorHandler ) {
						window.SCD.ErrorHandler.handle( error, 'products-state-update-count', window.SCD.ErrorHandler.SEVERITY.LOW );
					}
				} );
		},

		/**
		 * Get cached product data
		 * @param productId
		 */
		getProductData: function( productId ) {
			return this.data.productData[String( productId )] || null;
		},

		/**
		 * Get validation errors
		 */
		getErrors: function() {
			return this.errors || {};
		},

		/**
		 * Clear validation errors
		 */
		clearErrors: function() {
			this.errors = {};
			this.validated = false;
		},

		/**
		 * Check if state is dirty
		 */
		isDirty: function() {
			return this.dirty;
		},

		/**
		 * Mark state as clean
		 */
		markClean: function() {
			this.dirty = false;
		},

		/**
		 * Reset state to defaults
		 */
		reset: function() {
			this.data = {
				productIds: [],
				categoryIds: [ 'all' ],
				tagIds: [],
				searchTerm: '',
				filters: {},
				productSelectionType: 'all_products',
				randomCount: 10,
				conditionsLogic: 'all',
				smartCriteria: '',
				conditions: [],
				totalProducts: 0,
				productData: {}
			};

			this.errors = {};
			this.dirty = false;
			this.validated = false;

			this.triggerChange();
		},

		/**
		 * Export state for saving - converts camelCase to snake_case for backend
		 * Only saves relevant data based on selection type
		 */
		export: function() {
			var exportData = {
				product_selection_type: this.data.productSelectionType
			};


			// Only save data relevant to the selected type
			if ( 'specific_products' === this.data.productSelectionType ) {
				// For specific products: save selected products and category filters
				exportData.product_ids = this.getSelectedProductIds();
				exportData.category_ids = this.data.categoryIds;

			} else if ( 'random_products' === this.data.productSelectionType ) {
				// For random products: save count, category filters, and conditions
				exportData.random_count = this.data.randomCount;
				exportData.category_ids = this.data.categoryIds;
				exportData.conditions_logic = this.data.conditionsLogic; // Use snake_case for PHP
				exportData.conditions = this.data.conditions || [];
				exportData.filters = this.data.filters;

			} else if ( 'all_products' === this.data.productSelectionType ) {
				// For all products: save category filters and conditions only
				exportData.category_ids = this.data.categoryIds;
				exportData.conditions_logic = this.data.conditionsLogic; // Use snake_case for PHP
				exportData.conditions = this.data.conditions || [];
				exportData.filters = this.data.filters;
			} else if ( 'smart_selection' === this.data.productSelectionType ) {
				// For smart selection: save smart criteria
				exportData.smart_criteria = this.data.smartCriteria;
				exportData.category_ids = this.data.categoryIds;
			}


			return exportData;
		},

		/**
		 * Convert state to JSON format for backend - uses export() method
		 * This method is called by step persistence to get properly formatted data
		 */
		toJSON: function() {
			return this.export();
		},

		/**
		 * Import state from saved data - converts snake_case from backend to camelCase
		 * @param data
		 */
		import: function( data ) {
			if ( SCD.Utils.isEmpty( data ) ) {
				return;
			}

			// Data already converted to camelCase by server
			// Import data
			this.data.categoryIds = this.processCategorySelection( data.categoryIds || [] );
			this.data.tagIds = data.tagIds || [];
			this.data.productSelectionType = data.productSelectionType || data.selectionType || 'all_products';
			this.data.randomCount = data.randomCount || 10;
			this.data.conditionsLogic = data.conditionsLogic || 'all';
			this.data.smartCriteria = data.smartCriteria || '';
			// Only update conditions if they're provided in the import data
			if ( data.conditions !== undefined ) {
				this.data.conditions = data.conditions;
			}
			this.data.filters = SCD.Utils.extend( this.data.filters, data.filters || {} );

			// Import categoryData if provided (enriched by server with full category objects)
			if ( data.categoryData !== undefined ) {
				this.data.categoryData = data.categoryData;
			}

			// Handle product IDs (need to be resolved to full product objects)
			var productIds = data.productIds || [];
			if ( productIds && 0 < productIds.length ) {
				this.triggerCustomEvent( 'scd:products:resolve-products', [ productIds ] );
			}

			this.triggerChange();
		},

		/**
		 * Check if two values are equal
		 * 
		 * @since 1.0.0
		 * @private
		 * @param {*} val1 First value
		 * @param {*} val2 Second value
		 * @returns {boolean} True if equal
		 */
		_isEqual: function( val1, val2 ) {
			// Handle null/undefined
			if ( val1 === val2 ) {
				return true;
			}
			
			// Handle different types
			if ( typeof val1 !== typeof val2 ) {
				return false;
			}
			
			// Handle arrays
			if ( Array.isArray( val1 ) && Array.isArray( val2 ) ) {
				if ( val1.length !== val2.length ) {
					return false;
				}
				for ( var i = 0; i < val1.length; i++ ) {
					if ( ! this._isEqual( val1[i], val2[i] ) ) {
						return false;
					}
				}
				return true;
			}
			
			// Handle objects
			if ( val1 && typeof val1 === 'object' && val2 && typeof val2 === 'object' ) {
				var keys1 = Object.keys( val1 );
				var keys2 = Object.keys( val2 );
				
				if ( keys1.length !== keys2.length ) {
					return false;
				}
				
				for ( var j = 0; j < keys1.length; j++ ) {
					var key = keys1[j];
					if ( ! this._isEqual( val1[key], val2[key] ) ) {
						return false;
					}
				}
				return true;
			}
			
			// Use JSON comparison as fallback
			try {
				return JSON.stringify( val1 ) === JSON.stringify( val2 );
			} catch ( e ) {
				return false;
			}
		},

		/**
		 * Cleanup
		 * 
		 * @since 1.0.0
		 * @returns {void}
		 */
		destroy: function() {
			// Clear any pending timeouts
			if ( this.filterTimeout ) {
				clearTimeout( this.filterTimeout );
				this.filterTimeout = null;
			}

			this.unbindAllEvents();
			this.data = null;
			this.errors = null;
		}
	};

	// Mix in event manager functionality
	SCD.Utils.extend( SCD.Modules.Products.State.prototype, SCD.Mixins.EventManager );

} )( jQuery );