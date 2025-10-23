/**
 * Basic Step API Service
 *
 * Extends BaseAPI to handle AJAX operations for the basic step.
 * Automatic error handling inherited from BaseAPI eliminates duplicate error handling code.
 *
 * @param $
 * @package SmartCycleDiscounts
 * @since 1.0.0
 */

( function( $ ) {
	'use strict';

	// Ensure namespaces exist
	window.SCD = window.SCD || {};
	SCD.Modules = SCD.Modules || {};
	SCD.Modules.Basic = SCD.Modules.Basic || {};

	/**
	 * Basic API Class
	 * Extends BaseAPI for automatic error handling
	 *
	 * @param {object} config Configuration options
	 */
	SCD.Modules.Basic.API = function( config ) {
		// Call parent constructor
		if ( window.SCD && window.SCD.Shared && window.SCD.Shared.BaseAPI ) {
			SCD.Shared.BaseAPI.call( this, config );
		}
		this.stepName = 'basic';
	};

	// Set up proper prototype chain if BaseAPI is available
	if ( window.SCD && window.SCD.Shared && window.SCD.Shared.BaseAPI ) {
		SCD.Modules.Basic.API.prototype = Object.create( SCD.Shared.BaseAPI.prototype );
		SCD.Modules.Basic.API.prototype.constructor = SCD.Modules.Basic.API;
	}

	/**
	 * Check campaign name uniqueness
	 *
	 * @param {string} name Campaign name to check
	 * @param {number} excludeId Campaign ID to exclude from check (for editing)
	 * @returns {jQuery.Promise}
	 */
	SCD.Modules.Basic.API.prototype.checkCampaignName = function( name, excludeId ) {
		// Use inherited request() method for automatic error handling
		return this.request( 'scd_check_campaign_name', {
			name: name,
			excludeId: excludeId || ''
		} );
	};

	/**
	 * Save step data
	 * Note: This overrides the default saveStepData from BaseAPI
	 *
	 * @param {object} data Step data to save
	 * @returns {jQuery.Promise}
	 */
	SCD.Modules.Basic.API.prototype.saveStepData = function( data ) {
		// Use inherited request() method for automatic error handling
		return this.request( 'scd_save_step', {
			step: 'basic',
			data: data
		} );
	};

} )( jQuery );
