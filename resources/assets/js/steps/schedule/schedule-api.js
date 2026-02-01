/**
 * Schedule Api
 *
 * Provides API methods for schedule step data operations.
 * Note: Most operations use the centralized wizard save mechanism.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/steps/schedule/schedule-api.js
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

( function( $ ) {
	'use strict';

	// Ensure namespaces exist
	window.WSSCD = window.WSSCD || {};
	WSSCD.Modules = WSSCD.Modules || {};
	WSSCD.Modules.Schedule = WSSCD.Modules.Schedule || {};

	// Register module using utility - eliminates boilerplate
	if ( WSSCD.Utils && 'function' === typeof WSSCD.Utils.registerModule ) {
		WSSCD.Utils.registerModule( 'WSSCD.Modules.Schedule', 'API', function() {
			if ( WSSCD.Utils && 'function' === typeof WSSCD.Utils.ensureInitialized ) {
				if ( !WSSCD.Utils.ensureInitialized( this, {
					'WSSCD.Ajax': WSSCD.Ajax,
					'WSSCD.ErrorHandler': WSSCD.ErrorHandler
				}, 'ScheduleAPI' ) ) {
					return;
				}
			}
		} );
	}

	if ( WSSCD.Modules && WSSCD.Modules.Schedule && WSSCD.Modules.Schedule.API ) {
		WSSCD.Modules.Schedule.API.prototype = {
			/**
			 * Save step data
			 * @param data
			 */
			saveStepData: function( data ) {
				return WSSCD.Ajax.post( 'wsscd_save_step', {
					step: 'schedule',
					data: data
				} ).fail( function( xhr ) {
					WSSCD.ErrorHandler.handleAjaxError( xhr, 'wsscd_save_step', { step: 'schedule' } );
				} );
			}
		};
	} else {
		// Fallback: Define API module directly if registerModule isn't available
		WSSCD.Modules.Schedule.API = function() {};
		WSSCD.Modules.Schedule.API.prototype = {
			saveStepData: function( data ) {
				if ( !WSSCD.Ajax || !WSSCD.Ajax.post ) {
					return $.Deferred().reject( 'Ajax module not available' );
				}
				return WSSCD.Ajax.post( 'wsscd_save_step', {
					step: 'schedule',
					data: data
				} );
			}
		};
	}

} )( jQuery );
