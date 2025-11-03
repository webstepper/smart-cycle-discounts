/**
 * Products State
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/steps/products/products-state.js
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
	 * Products State Constructor
	 * Extends BaseState for state management
	 */
	SCD.Modules.Products.State = function() {
		// Define initial state
		var initialState = {
			productSelectionType: 'all_products',
			productIds: [],
			categoryIds: [ 'all' ],
			randomCount: 10,
			smartCriteria: '',
			conditions: [],
			conditionsLogic: 'all'
		};

		// Call parent constructor
		if ( window.SCD && window.SCD.Shared && window.SCD.Shared.BaseState ) {
			SCD.Shared.BaseState.call( this, initialState );
		}

		this.initEventManager();
	};

	if ( window.SCD && window.SCD.Shared && window.SCD.Shared.BaseState ) {
		SCD.Modules.Products.State.prototype = Object.create( SCD.Shared.BaseState.prototype );
		SCD.Modules.Products.State.prototype.constructor = SCD.Modules.Products.State;
	}

	// Extend prototype with custom methods
	SCD.Utils.extend( SCD.Modules.Products.State.prototype, {

		/**
		 * Override setState to add data normalization only
		 *
		 * @since 1.0.0
		 * @param {object} updates - Properties to update
		 * @param {boolean} batch - Whether to batch the update
		 * @returns {void}
		 */
		setState: function( updates, batch ) {
			// Normalize arrays
			if ( updates.categoryIds !== undefined ) {
				updates.categoryIds = this._normalizeArray( updates.categoryIds, [ 'all' ] );
			}
			if ( updates.productIds !== undefined ) {
				updates.productIds = this._normalizeArray( updates.productIds, [] );
			}
			if ( updates.conditions !== undefined ) {
				updates.conditions = Array.isArray( updates.conditions ) ? updates.conditions : [];
			}

			// Call parent setState (triggers change events)
			if ( window.SCD && window.SCD.Shared && window.SCD.Shared.BaseState ) {
				SCD.Shared.BaseState.prototype.setState.call( this, updates, batch );
			}
		},

		/**
		 * Normalize value to array
		 *
		 * @since 1.0.0
		 * @private
		 * @param {*} value - Value to normalize
		 * @param {Array} defaultValue - Default value if normalization fails
		 * @returns {Array} Normalized array
		 */
		_normalizeArray: function( value, defaultValue ) {
			if ( ! value ) {
				return defaultValue;
			}
			if ( ! Array.isArray( value ) ) {
				return [ String( value ) ];
			}
			return value.map( String ).filter( function( v ) {
				return v && '' !== v;
			} );
		},

		/**
		 * Export state for backend (snake_case conversion)
		 * Only exports relevant data based on selection type
		 *
		 * @since 1.0.0
		 * @returns {object} Exported state data
		 */
		export: function() {
			var state = this.getState();
			var exportData = {
				product_selection_type: state.productSelectionType
			};

			// Only save data relevant to the selected type
			if ( 'specific_products' === state.productSelectionType ) {
				// For specific products: save selected products and category filter
				exportData.product_ids = state.productIds;
				exportData.category_ids = state.categoryIds;

			} else if ( 'random_products' === state.productSelectionType ) {
				// For random products: save count, category filter, and conditions
				exportData.random_count = state.randomCount;
				exportData.category_ids = state.categoryIds;
				exportData.conditions_logic = state.conditionsLogic;
				exportData.conditions = state.conditions || [];

			} else if ( 'all_products' === state.productSelectionType ) {
				// For all products: save category filter and conditions
				exportData.category_ids = state.categoryIds;
				exportData.conditions_logic = state.conditionsLogic;
				exportData.conditions = state.conditions || [];

			} else if ( 'smart_selection' === state.productSelectionType ) {
				// For smart selection: save criteria and category filter
				exportData.smart_criteria = state.smartCriteria;
				exportData.category_ids = state.categoryIds;
			}

			return exportData;
		},

		/**
		 * toJSON for JSON.stringify and StepPersistence collectData()
		 * Returns raw state - AJAX router will auto-convert camelCase to snake_case
		 *
		 * @since 1.0.0
		 * @returns {object} Raw state data with camelCase keys
		 */
		toJSON: function() {
			return this.getState();
		},

		/**
		 * Import state from backend (camelCase conversion)
		 *
		 * @since 1.0.0
		 * @param {object} data - Data from backend
		 * @returns {void}
		 */
		import: function( data ) {
			if ( ! data || 'object' !== typeof data ) {
				return;
			}

			// Convert snake_case to camelCase and normalize
			var importData = {
				productSelectionType: data.productSelectionType || data.product_selection_type || 'all_products',
				productIds: this._normalizeArray( data.productIds || data.product_ids, [] ),
				categoryIds: this._normalizeArray( data.categoryIds || data.category_ids, [ 'all' ] ),
				randomCount: parseInt( data.randomCount || data.random_count, 10 ) || 10,
				smartCriteria: data.smartCriteria || data.smart_criteria || '',
				conditions: Array.isArray( data.conditions ) ? data.conditions : [],
				conditionsLogic: data.conditionsLogic || data.conditions_logic || 'all'
			};

			// Apply state
			this.setState( importData );
		},

		/**
		 * Reset state to defaults
		 *
		 * @since 1.0.0
		 * @returns {void}
		 */
		reset: function() {
			var defaults = {
				productSelectionType: 'all_products',
				productIds: [],
				categoryIds: [ 'all' ],
				randomCount: 10,
				smartCriteria: '',
				conditions: [],
				conditionsLogic: 'all'
			};

			// Call parent reset
			if ( window.SCD && window.SCD.Shared && window.SCD.Shared.BaseState ) {
				SCD.Shared.BaseState.prototype.reset.call( this, defaults );
			}
		},

		/**
		 * Get selected product count
		 * Simple getter - no business logic
		 *
		 * @since 1.0.0
		 * @returns {number} Number of selected products
		 */
		getSelectedCount: function() {
			var state = this.getState();
			return state.productIds ? state.productIds.length : 0;
		},

		/**
		 * Check if product is selected
		 * Simple checker - no business logic
		 *
		 * @since 1.0.0
		 * @param {string|number} productId - Product ID to check
		 * @returns {boolean} True if product is selected
		 */
		isProductSelected: function( productId ) {
			var state = this.getState();
			if ( ! state.productIds || 0 === state.productIds.length ) {
				return false;
			}

			// Ensure string comparison
			productId = String( productId );
			return -1 !== state.productIds.indexOf( productId );
		},

		/**
		 * Cleanup
		 *
		 * @since 1.0.0
		 * @returns {void}
		 */
		destroy: function() {
			// Unbind all events
			this.unbindAllEvents();

			// Call parent destroy
			if ( window.SCD && window.SCD.Shared && window.SCD.Shared.BaseState ) {
				SCD.Shared.BaseState.prototype.destroy.call( this );
			}
		}
	} );

	// Mix in event manager functionality
	SCD.Utils.extend( SCD.Modules.Products.State.prototype, SCD.Mixins.EventManager );

} )( jQuery );
