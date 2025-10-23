/**
 * Review Step API Service
 *
 * Handles AJAX operations for the review step using consolidated utilities
 *
 * @param $
 * @package SmartCycleDiscounts
 * @since 1.0.0
 */

( function( $ ) {
	'use strict';

	// Register module using utility - eliminates boilerplate
	SCD.Utils.registerModule( 'SCD.Modules.Review', 'API', function() {
		// Initialize with dependency checks
		if ( !SCD.Utils.ensureInitialized( this, {
			'SCD.Ajax': SCD.Ajax,
			'SCD.ErrorHandler': SCD.ErrorHandler
		}, 'ReviewAPI' ) ) {
			return;
		}
	} );

	SCD.Modules.Review.API.prototype = {
		/**
		 * Get campaign summary
		 */
		getCampaignSummary: function() {
			return SCD.Ajax.post( 'scd_get_summary', {} ).fail( function( xhr ) {
				SCD.ErrorHandler.handleAjaxError( xhr, 'scd_get_summary', {} );
			} );
		},

		/**
		 * Complete wizard and create campaign
		 * @param data
		 */
		completeWizard: function( data ) {
			if ( SCD.Utils.isEmpty( data ) ) {
				var error = new Error( 'Wizard completion data is required' );
				SCD.ErrorHandler.handle( error, 'ReviewAPI.completeWizard', SCD.ErrorHandler.SEVERITY.MEDIUM );
				return $.Deferred().reject( error ).promise();
			}

			return SCD.Ajax.post( 'scd_complete_wizard', {
				launchOption: data.launchOption || 'draft',
				data: data
			} ).fail( function( xhr ) {
				SCD.ErrorHandler.handleAjaxError( xhr, 'scd_complete_wizard', { launchOption: data.launchOption } );
			} );
		},

		/**
		 * Save review step data
		 * @param data
		 */
		saveStepData: function( data ) {
			return SCD.Ajax.post( 'scd_save_step', {
				step: 'review',
				data: data
			} ).fail( function( xhr ) {
				SCD.ErrorHandler.handleAjaxError( xhr, 'scd_save_step', { step: 'review' } );
			} );
		},

		/**
		 * Load review step data
		 */
		loadStepData: function() {
			return SCD.Ajax.post( 'scd_load_data', {
				step: 'review'
			} ).fail( function( xhr ) {
				SCD.ErrorHandler.handleAjaxError( xhr, 'scd_load_data', { step: 'review' } );
			} );
		},

		/**
		 * Validate campaign data before submission
		 * @param data
		 */
		validateCampaignData: function( data ) {
			return SCD.Ajax.post( 'scd_validate_campaign', {
				data: data
			} ).fail( function( xhr ) {
				SCD.ErrorHandler.handleAjaxError( xhr, 'scd_validate_campaign', data );
			} );
		},

		/**
		 * Get all wizard data
		 */
		getWizardData: function() {
			return SCD.Ajax.post( 'scd_get_wizard_data', {} ).fail( function( xhr ) {
				SCD.ErrorHandler.handleAjaxError( xhr, 'scd_get_wizard_data', {} );
			} );
		}
	};

} )( jQuery );