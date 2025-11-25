/**
 * Discounts Api
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/steps/discounts/discounts-api.js
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

( function( $ ) {
	'use strict';

	// Ensure namespace exists
	window.SCD = window.SCD || {};
	SCD.Modules = SCD.Modules || {};
	SCD.Modules.Discounts = SCD.Modules.Discounts || {};

	/**
	 * API Module Constructor
	 */
	SCD.Modules.Discounts.API = function() {
		// API module doesn't need initialization
	};

	SCD.Modules.Discounts.API.prototype = {
		/**
		 * Save discount configuration
		 * @param discountData
		 */
		saveDiscountConfig: function( discountData ) {
			if ( !discountData || $.isEmptyObject( discountData ) ) {
				var error = new Error( 'Discount data is required' );
				SCD.ErrorHandler.handle( error, 'DiscountsAPI.saveDiscountConfig', SCD.ErrorHandler.SEVERITY.MEDIUM );
				return $.Deferred().reject( error ).promise();
			}

			return SCD.Ajax.post( 'scd_save_step', {
				step: 'discounts',
				data: discountData
			} ).fail( function( xhr ) {
				SCD.ErrorHandler.handleAjaxError( xhr, 'scd_save_step', { step: 'discounts' } );
			} );
		},

		/**
		 * Load discount configuration
		 */
		loadDiscountConfig: function() {
			return SCD.Ajax.post( 'scd_load_data', {
				step: 'discounts'
			} ).fail( function( xhr ) {
				SCD.ErrorHandler.handleAjaxError( xhr, 'scd_load_data', { step: 'discounts' } );
			} );
		},

		/**
		 * Validate discount rules
		 * @param rules
		 */
		validateDiscountRules: function( rules ) {
			if ( !rules || 0 === Object.keys( rules ).length ) {
				var error = new Error( 'Discount rules are required' );
				SCD.ErrorHandler.handle( error, 'DiscountsAPI.validateDiscountRules', SCD.ErrorHandler.SEVERITY.LOW );
				return $.Deferred().reject( error ).promise();
			}

			return SCD.Ajax.post( 'scd_validate_discount_rules', {
				rules: rules
			} ).fail( function( xhr ) {
				SCD.ErrorHandler.handleAjaxError( xhr, 'scd_validate_discount_rules', { rules: rules } );
			} );
		},

		/**
		 * Get discount preview
		 * @param config
		 */
		getDiscountPreview: function( config ) {
			return SCD.Ajax.post( 'scd_get_discount_preview', {
				config: config
			} ).fail( function( xhr ) {
				SCD.ErrorHandler.handleAjaxError( xhr, 'scd_get_discount_preview', { config: config } );
			} );
		},

		/**
		 * Calculate discount impact
		 * @param productIds
		 * @param discountConfig
		 */
		calculateDiscountImpact: function( productIds, discountConfig ) {
			return SCD.Ajax.post( 'scd_calculate_discount_impact', {
				productIds: productIds,
				discountConfig: discountConfig
			} ).fail( function( xhr ) {
				SCD.ErrorHandler.handleAjaxError( xhr, 'scd_calculate_discount_impact', { productIds: productIds } );
			} );
		}
	};

} )( jQuery );