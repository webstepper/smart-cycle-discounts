/**
 * Product Selection Types Constants
 *
 * Single source of truth for product selection types.
 * Matches PHP constants in SCD_Product_Selection_Types class.
 *
 * @param window
 * @package SmartCycleDiscounts
 * @since 1.0.0
 */

( function( window ) {
	'use strict';

	window.SCD = window.SCD || {};
	SCD.Constants = SCD.Constants || {};

	// Check if constants are available from PHP localization
	var localizedConstants = window.scdWizardData &&
                           window.scdWizardData.constants &&
                           window.scdWizardData.constants.product_selection_types || null;

	/**
	 * Product Selection Types
	 * Uses PHP constants if available, falls back to hardcoded values
	 */
	SCD.Constants.ProductSelectionTypes = {
		ALL_PRODUCTS: localizedConstants ? localizedConstants.ALL_PRODUCTS : 'all_products',
		SPECIFIC_PRODUCTS: localizedConstants ? localizedConstants.SPECIFIC_PRODUCTS : 'specific_products',
		RANDOM_PRODUCTS: localizedConstants ? localizedConstants.RANDOM_PRODUCTS : 'random_products',
		SMART_SELECTION: localizedConstants ? localizedConstants.SMART_SELECTION : 'smart_selection',

		/**
		 * Check if a type is valid
		 *
		 * @param {string} type Selection type to check
		 * @returns {boolean} True if valid
		 */
		isValid: function( type ) {
			return type === this.ALL_PRODUCTS ||
                   type === this.SPECIFIC_PRODUCTS ||
                   type === this.RANDOM_PRODUCTS ||
                   type === this.SMART_SELECTION;
		},

		/**
		 * Get default type
		 *
		 * @returns {string} Default selection type
		 */
		getDefault: function() {
			return this.ALL_PRODUCTS;
		}
	};

} )( window );