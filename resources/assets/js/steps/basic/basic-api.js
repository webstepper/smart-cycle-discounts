/**
 * Basic Api
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/steps/basic/basic-api.js
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress/plugins/smart-cycle-discounts/
 * @since      1.0.0
 */

( function( $ ) {
	'use strict';

	// Ensure namespaces exist
	window.WSSCD = window.WSSCD || {};
	WSSCD.Modules = WSSCD.Modules || {};
	WSSCD.Modules.Basic = WSSCD.Modules.Basic || {};

	/**
	 * Basic API Class
	 * Extends BaseAPI for automatic error handling
	 *
	 * @param {object} config Configuration options
	 */
	WSSCD.Modules.Basic.API = function( config ) {
		// Call parent constructor
		if ( window.WSSCD && window.WSSCD.Shared && window.WSSCD.Shared.BaseAPI ) {
			WSSCD.Shared.BaseAPI.call( this, config );
		}
		this.stepName = 'basic';
	};

	if ( window.WSSCD && window.WSSCD.Shared && window.WSSCD.Shared.BaseAPI ) {
		WSSCD.Modules.Basic.API.prototype = Object.create( WSSCD.Shared.BaseAPI.prototype );
		WSSCD.Modules.Basic.API.prototype.constructor = WSSCD.Modules.Basic.API;
	}

	/**
	 * Check campaign name uniqueness
	 *
	 * @param {string} name Campaign name to check
	 * @param {number} excludeId Campaign ID to exclude from check (for editing)
	 * @returns {jQuery.Promise}
	 */
	WSSCD.Modules.Basic.API.prototype.checkCampaignName = function( name, excludeId ) {
		// Use inherited request() method for automatic error handling
		return this.request( 'wsscd_check_campaign_name', {
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
	WSSCD.Modules.Basic.API.prototype.saveStepData = function( data ) {
		// Use inherited request() method for automatic error handling
		return this.request( 'wsscd_save_step', {
			step: 'basic',
			data: data
		} );
	};

} )( jQuery );
