/**
 * Product Selection Types
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/constants/product-selection-types.js
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress/plugins/smart-cycle-discounts/
 * @since      1.0.0
 */

( function( window ) {
	'use strict';

	window.WSSCD = window.WSSCD || {};
	WSSCD.Constants = WSSCD.Constants || {};

	var localizedConstants = window.wsscdWizardData &&
                           window.wsscdWizardData.constants &&
                           window.wsscdWizardData.constants.productSelectionTypes || null;

	/**
	 * Product Selection Types
	 * Uses PHP constants if available, falls back to hardcoded values
	 */
	WSSCD.Constants.ProductSelectionTypes = {
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