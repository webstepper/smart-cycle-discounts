/**
 * Schedule Api
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
				if ( WSSCD.Modules.Schedule && WSSCD.Modules.Schedule.Debug ) {
					WSSCD.Modules.Schedule.Debug.logApiCall( 'POST', 'wsscd_save_step', data );
				}

				return WSSCD.Ajax.post( 'wsscd_save_step', {
					step: 'schedule',
					data: data
				} ).done( function( response ) {
					if ( WSSCD.Modules.Schedule && WSSCD.Modules.Schedule.Debug ) {
						WSSCD.Modules.Schedule.Debug.log( 'success', 'API', 'Save step data successful', response );
					}
				} ).fail( function( xhr ) {
					if ( WSSCD.Modules.Schedule && WSSCD.Modules.Schedule.Debug ) {
						WSSCD.Modules.Schedule.Debug.logError( 'API', xhr.responseJSON || xhr, 'wsscd_save_step' );
					}
					WSSCD.ErrorHandler.handleAjaxError( xhr, 'wsscd_save_step', { step: 'schedule' } );
				} );
			},

			/**
			 * Validate schedule dates
			 * @param data
			 */
			validateSchedule: function( data ) {
				if ( WSSCD.Modules.Schedule && WSSCD.Modules.Schedule.Debug ) {
					WSSCD.Modules.Schedule.Debug.logApiCall( 'POST', 'wsscd_validate_schedule', data );
				}

				if ( WSSCD.Utils.isEmpty( data ) ) {
					var error = new Error( 'Schedule data is required for validation' );
					WSSCD.ErrorHandler.handle( error, 'ScheduleAPI.validateSchedule', WSSCD.ErrorHandler.SEVERITY.MEDIUM );
					return $.Deferred().reject( error ).promise();
				}

				return WSSCD.Ajax.post( 'wsscd_validate_schedule', {
					startDate: data.startDate,
					endDate: data.endDate,
					startTime: data.startTime,
					endTime: data.endTime,
					timezone: data.timezone
				} ).done( function( response ) {
					if ( WSSCD.Modules.Schedule && WSSCD.Modules.Schedule.Debug ) {
						WSSCD.Modules.Schedule.Debug.log( 'success', 'API', 'Schedule validation result', response );
					}
				} ).fail( function( xhr ) {
					if ( WSSCD.Modules.Schedule && WSSCD.Modules.Schedule.Debug ) {
						WSSCD.Modules.Schedule.Debug.logError( 'API', xhr.responseJSON || xhr, 'wsscd_validate_schedule' );
					}
					WSSCD.ErrorHandler.handleAjaxError( xhr, 'wsscd_validate_schedule', {
						startDate: data.startDate,
						endDate: data.endDate
					} );
				} );
			},

			/**
			 * Get schedule presets
			 */
			getPresets: function() {
				if ( WSSCD.Modules.Schedule && WSSCD.Modules.Schedule.Debug ) {
					WSSCD.Modules.Schedule.Debug.logApiCall( 'POST', 'wsscd_get_schedule_presets', {} );
				}

				return WSSCD.Ajax.post( 'wsscd_get_schedule_presets', {} ).done( function( response ) {
					if ( WSSCD.Modules.Schedule && WSSCD.Modules.Schedule.Debug ) {
						WSSCD.Modules.Schedule.Debug.log( 'success', 'API', 'Presets loaded', response );
					}
				} ).fail( function( xhr ) {
					WSSCD.ErrorHandler.handleAjaxError( xhr, 'wsscd_get_schedule_presets', {} );
				} );
			},

			/**
			 * Check for schedule conflicts
			 * @param data
			 */
			checkConflicts: function( data ) {
				if ( WSSCD.Modules.Schedule && WSSCD.Modules.Schedule.Debug ) {
					WSSCD.Modules.Schedule.Debug.logApiCall( 'POST', 'wsscd_check_schedule_conflicts', data );
				}

				return WSSCD.Ajax.post( 'wsscd_check_schedule_conflicts', {
					startDate: data.startDate,
					endDate: data.endDate,
					campaignId: data.campaignId || null,
					productIds: data.productIds || []
				} ).done( function( response ) {
					if ( WSSCD.Modules.Schedule && WSSCD.Modules.Schedule.Debug ) {
						var hasConflicts = response && response.data && response.data.conflicts && 0 < response.data.conflicts.length;
						var logType = hasConflicts ? 'warning' : 'success';
						WSSCD.Modules.Schedule.Debug.log( logType, 'API', 'Conflict check result', response );
					}
				} ).fail( function( xhr ) {
					if ( WSSCD.Modules.Schedule && WSSCD.Modules.Schedule.Debug ) {
						WSSCD.Modules.Schedule.Debug.logError( 'API', xhr.responseJSON || xhr, 'wsscd_check_schedule_conflicts' );
					}
					WSSCD.ErrorHandler.handleAjaxError( xhr, 'wsscd_check_schedule_conflicts', {
						startDate: data.startDate,
						endDate: data.endDate
					} );
				} );
			},

			/**
			 * Load schedule step data
			 */
			loadStepData: function() {
				if ( WSSCD.Modules.Schedule && WSSCD.Modules.Schedule.Debug ) {
					WSSCD.Modules.Schedule.Debug.logApiCall( 'POST', 'wsscd_load_data', { step: 'schedule' } );
				}

				return WSSCD.Ajax.post( 'wsscd_load_data', {
					step: 'schedule'
				} ).done( function( response ) {
					if ( WSSCD.Modules.Schedule && WSSCD.Modules.Schedule.Debug ) {
						WSSCD.Modules.Schedule.Debug.log( 'success', 'API', 'Step data loaded', response );
					}
				} ).fail( function( xhr ) {
					if ( WSSCD.Modules.Schedule && WSSCD.Modules.Schedule.Debug ) {
						WSSCD.Modules.Schedule.Debug.logError( 'API', xhr.responseJSON || xhr, 'wsscd_load_data' );
					}
					WSSCD.ErrorHandler.handleAjaxError( xhr, 'wsscd_load_data', { step: 'schedule' } );
				} );
			}
		};
	} else {
		// Fallback: Define API module directly if registerModule isn't available
		WSSCD.Modules.Schedule.API = function() {};
		WSSCD.Modules.Schedule.API.prototype = {
			saveStepData: function( data ) {
				if ( !WSSCD.Ajax || !WSSCD.Ajax.post ) {
					console.error( '[Schedule API] WSSCD.Ajax not available' );
					return $.Deferred().reject( 'Ajax module not available' );
				}
				return WSSCD.Ajax.post( 'wsscd_save_step', {
					step: 'schedule',
					data: data
				} );
			},

			validateSchedule: function( data ) {
				if ( !data ) {
					return $.Deferred().reject( 'Schedule data is required' );
				}
				if ( !WSSCD.Ajax || !WSSCD.Ajax.post ) {
					console.error( '[Schedule API] WSSCD.Ajax not available' );
					return $.Deferred().reject( 'Ajax module not available' );
				}
				return WSSCD.Ajax.post( 'wsscd_validate_schedule', data );
			},

			getPresets: function() {
				if ( !WSSCD.Ajax || !WSSCD.Ajax.post ) {
					console.error( '[Schedule API] WSSCD.Ajax not available' );
					return $.Deferred().reject( 'Ajax module not available' );
				}
				return WSSCD.Ajax.post( 'wsscd_get_schedule_presets', {} );
			},

			loadStepData: function() {
				if ( !WSSCD.Ajax || !WSSCD.Ajax.post ) {
					console.error( '[Schedule API] WSSCD.Ajax not available' );
					return $.Deferred().reject( 'Ajax module not available' );
				}
				return WSSCD.Ajax.post( 'wsscd_load_data', { step: 'schedule' } );
			}
		};
	}

} )( jQuery );