/**
 * Review Api
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/steps/review/review-api.js
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

( function( $ ) {
	'use strict';

	// Register module using utility - eliminates boilerplate
	WSSCD.Utils.registerModule( 'WSSCD.Modules.Review', 'API', function() {
		if ( !WSSCD.Utils.ensureInitialized( this, {
			'WSSCD.Ajax': WSSCD.Ajax,
			'WSSCD.ErrorHandler': WSSCD.ErrorHandler
		}, 'ReviewAPI' ) ) {
			return;
		}
	} );

	WSSCD.Modules.Review.API.prototype = {
		/**
		 * Get campaign summary
		 */
		getCampaignSummary: function() {
			return WSSCD.Ajax.post( 'wsscd_get_summary', {} ).fail( function( xhr ) {
				WSSCD.ErrorHandler.handleAjaxError( xhr, 'wsscd_get_summary', {} );
			} );
		},

		/**
		 * Save review step data
		 * @param data
		 */
		saveStepData: function( data ) {
			return WSSCD.Ajax.post( 'wsscd_save_step', {
				step: 'review',
				data: data
			} ).fail( function( xhr ) {
				WSSCD.ErrorHandler.handleAjaxError( xhr, 'wsscd_save_step', { step: 'review' } );
			} );
		},

		/**
		 * Load review step data
		 */
		loadStepData: function() {
			return WSSCD.Ajax.post( 'wsscd_load_data', {
				step: 'review'
			} ).fail( function( xhr ) {
				WSSCD.ErrorHandler.handleAjaxError( xhr, 'wsscd_load_data', { step: 'review' } );
			} );
		},

		/**
		 * Get all wizard data
		 */
		getWizardData: function() {
			return WSSCD.Ajax.post( 'wsscd_get_wizard_data', {} ).fail( function( xhr ) {
				WSSCD.ErrorHandler.handleAjaxError( xhr, 'wsscd_get_wizard_data', {} );
			} );
		}
	};

} )( jQuery );