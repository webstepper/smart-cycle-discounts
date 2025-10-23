/**
 * Products Selector Module
 *
 * Handles product selection UI using consolidated utilities
 *
 * @param $
 * @package SmartCycleDiscounts
 * @since 1.0.0
 */

( function( $ ) {
	'use strict';

	// Register module using utility
	SCD.Utils.registerModule( 'SCD.Modules.Products', 'Selector', function( state, api ) {
		this.state = state;
		this.api = api;
		this.elements = {};
		this.config = {
			productsPerPage: 12,
			debounceDelay: 300
		};

		// Initialize event manager
		this.initEventManager();
	} );

	// Extend with event manager mixin
	SCD.Utils.extend( SCD.Modules.Products.Selector.prototype, SCD.Mixins.EventManager );

	SCD.Modules.Products.Selector.prototype = SCD.Utils.extend( SCD.Modules.Products.Selector.prototype, {
		constructor: SCD.Modules.Products.Selector,

		/**
		 * Initialize module
		 * 
		 * @since 1.0.0
		 * @returns {void}
		 */
		init: function() {
			try {
				this.cacheElements();
				this.bindEvents();
				this.initializeCategorySelect();
				this.syncStateToUI();
			} catch ( error ) {
				if ( window.SCD && window.SCD.ErrorHandler ) {
					window.SCD.ErrorHandler.handle( error, 'products-selector-init', window.SCD.ErrorHandler.SEVERITY.HIGH );
				}
				throw error;
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
			var fieldDef = null;
			if ( window.SCD && window.SCD.FieldDefinitions && 'function' === typeof window.SCD.FieldDefinitions.getField ) {
				fieldDef = window.SCD.FieldDefinitions.getField( 'products', 'productSelectionType' );
			}
			
			var selectionTypeSelector = fieldDef && fieldDef.selector ? 
				fieldDef.selector : '[name="product_selection_type"]';

			this.elements = {
				$selectionType: $( selectionTypeSelector ),
				$specificSection: $( '.scd-specific-products' ),
				$randomSection: $( '.scd-random-count' ),
				$conditionsSection: $( '.scd-conditions-section' ),
				$categorySelect: $( '#scd-campaign-categories' ),
				$randomCount: $( '#scd-random-count' ),
				$productSearch: $( '#scd-product-search' ),
				$selectedCount: $( '.scd-selected-count' ),
				$totalCount: $( '.scd-total-count' )
			};
		},

		/**
		 * Bind events using event manager
		 * 
		 * @since 1.0.0
		 * @returns {void}
		 */
		bindEvents: function() {
			var self = this;

			// Card click handler
			if ( 'function' === typeof this.bindDelegatedEvent ) {
				this.bindDelegatedEvent( document, '.scd-card-option', 'click', function( e ) {
					if ( 'radio' !== e.target.type && ! $( e.target ).closest( 'input[type="radio"]' ).length ) {
						var $radio = $( this ).find( 'input[type="radio"]' );
						if ( $radio.length && ! $radio.prop( 'checked' ) ) {
							$radio.prop( 'checked', true ).trigger( 'change' );
						}
					}
				} );
			}

			// Category changes with debouncing
			var categoryHandler = function() {
				self.handleCategoryChange();
			};
			
			if ( window.SCD && window.SCD.Utils && 'function' === typeof window.SCD.Utils.debounce ) {
				categoryHandler = window.SCD.Utils.debounce( categoryHandler, this.config.debounceDelay );
			}

			if ( 'function' === typeof this.bindDelegatedEvent ) {
				this.bindDelegatedEvent( document, '#scd-campaign-categories', 'change', categoryHandler );
			}

			// Random count changes
			if ( 'function' === typeof this.bindDelegatedEvent ) {
				this.bindDelegatedEvent( document, '#scd-random-count', 'change', function( e ) {
					self.handleRandomCountChange( e );
				} );

				// Smart criteria changes
				this.bindDelegatedEvent( document, 'input[name="smart_criteria"]', 'change', function( e ) {
					self.handleSmartCriteriaChange( e );
				} );
			}

			// State change listener
			if ( 'function' === typeof this.bindCustomEvent ) {
				this.bindCustomEvent( 'scd:products:state:changed', function( e, data ) {
					self.handleStateChange( data );
				} );
			}
		},

		/**
		 * Sync current state to UI
		 */
		syncStateToUI: function() {
			var currentState = this.state.getState();

			// Selection type
			if ( currentState.productSelectionType ) {
				var $radio = this.elements.$selectionType
					.filter( '[value="' + currentState.productSelectionType + '"]' );

				if ( ! $radio.prop( 'checked' ) ) {
					$radio.prop( 'checked', true );
					this.updateProductCounts();
				}
			}

			// Categories
			if ( currentState.categoryIds && this.elements.$categorySelect.length ) {
				this.elements.$categorySelect.val( currentState.categoryIds );
			}

			// Random count
			if ( currentState.randomCount ) {
				this.elements.$randomCount.val( currentState.randomCount );
			}

			// Smart criteria
			if ( currentState.smartCriteria ) {
				$( 'input[name="smart_criteria"][value="' + currentState.smartCriteria + '"]' )
					.prop( 'checked', true );
			}

			this.updateProductCounts();
		},

		/**
		 * Update product counts display
		 */
		updateProductCounts: function() {
			var state = this.state.getState();
			var selectedCount = this._calculateSelectedCount( state );
			var totalCount = state.totalProducts || 0;
			var percentage = totalCount > 0 ? 
				Math.round( ( selectedCount / totalCount ) * 100 ) : 0;

			// Update UI elements
			this.elements.$selectedCount.text( selectedCount );
			this.elements.$totalCount.text( totalCount );
			$( '.scd-selected-percentage' ).text( percentage + '%' );

			// Emit changes only if values changed
			if ( ( state.selectedCount || 0 ) !== selectedCount ) {
				this.triggerCustomEvent( 'scd:products:field:changed', [ {
					field: 'selectedCount',
					value: selectedCount
				} ] );
			}

			if ( ( state.selectionPercentage || 0 ) !== percentage ) {
				this.triggerCustomEvent( 'scd:products:field:changed', [ {
					field: 'selectionPercentage',
					value: percentage
				} ] );
			}
		},

		/**
		 * Calculate selected count based on selection type
		 * @private
		 * @param {Object} state Current state
		 * @returns {number} Selected count
		 */
		_calculateSelectedCount: function( state ) {
			var selectionType = state.productSelectionType || 'all_products';
			var totalCount = state.totalProducts || 0;

			switch ( selectionType ) {
				case 'specific_products':
					return ( state.productIds || [] ).length;

				case 'random_products':
					return Math.min( state.randomCount || 10, totalCount );

				case 'all_products':
				case 'smart_selection':
					return totalCount;

				default:
					return 0;
			}
		},

		/**
		 * Handle random count change
		 * @param {Event} e Change event
		 */
		handleRandomCountChange: function( e ) {
			var count = parseInt( $( e.target ).val() ) || 10;
			this.triggerCustomEvent( 'scd:products:field:changed', [ {
				field: 'randomCount',
				value: count
			} ] );
		},

		/**
		 * Handle smart criteria changes
		 * @param {Event} e Change event
		 */
		handleSmartCriteriaChange: function( e ) {
			var criteria = $( e.target ).val();
			this.triggerCustomEvent( 'scd:products:field:changed', [ {
				field: 'smartCriteria',
				value: criteria
			} ] );
		},

		/**
		 * Initialize category select
		 */
		initializeCategorySelect: function() {
			var $select = this.elements.$categorySelect;
			
			if ( ! $select.length ) {
				return;
			}

			// Native multiple select
			$select.attr( 'multiple', 'multiple' );

			// Set initial values from state
			var stateCategories = this.state.getState().categoryIds;
			if ( stateCategories && stateCategories.length > 0 ) {
				if ( stateCategories.indexOf( 'all' ) !== -1 ) {
					$select.find( 'option[value="all"]' ).prop( 'selected', true );
					$select.find( 'option:not([value="all"])' ).prop( 'selected', false );
				} else {
					$select.val( stateCategories );
				}
			}
		},

		/**
		 * Handle category change
		 */
		handleCategoryChange: function() {
			var selectedCategories = this.elements.$categorySelect.val() || [];
			this.triggerCustomEvent( 'scd:products:field:changed', [ {
				field: 'categoryIds',
				value: selectedCategories
			} ] );
		},

		/**
		 * Handle state changes from other modules
		 * @param {Object} data State change data
		 */
		handleStateChange: function( data ) {
			var updateCountProperties = [
				'productIds',
				'totalProducts', 
				'randomCount',
				'selectionType',
				'categoryIds'
			];

			if ( updateCountProperties.indexOf( data.property ) !== -1 ) {
				this.updateProductCounts();
			}

			// Sync selection type radio buttons
			if ( 'selectionType' === data.property ) {
				this.elements.$selectionType
					.filter( '[value="' + data.value + '"]' )
					.prop( 'checked', true );
			}
		},

		/**
		 * Get selected categories
		 * @returns {Array} Selected category IDs
		 */
		getSelectedCategories: function() {
			return this.elements.$categorySelect.val() || [];
		},

		/**
		 * Update selected count (public API)
		 */
		updateSelectedCount: function() {
			this.updateProductCounts();
		},

		/**
		 * Get selected products from state
		 * @returns {Array} Selected product IDs
		 */
		getSelectedProducts: function() {
			if ( this.state ) {
				var state = this.state.getState();
				return state.productIds || [];
			}
			return [];
		},

		/**
		 * Destroy module
		 */
		destroy: function() {
			this.unbindAllEvents();
			this.elements = {};
		}
	} );

} )( jQuery );