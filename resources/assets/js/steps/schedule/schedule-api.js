/**
 * Schedule Step API Service
 *
 * Handles AJAX operations for the schedule step using consolidated utilities
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
	SCD.Modules.Schedule = SCD.Modules.Schedule || {};

	// Register module using utility - eliminates boilerplate
	if ( SCD.Utils && 'function' === typeof SCD.Utils.registerModule ) {
		SCD.Utils.registerModule( 'SCD.Modules.Schedule', 'API', function() {
			// Initialize with dependency checks
			if ( SCD.Utils && 'function' === typeof SCD.Utils.ensureInitialized ) {
				if ( !SCD.Utils.ensureInitialized( this, {
					'SCD.Ajax': SCD.Ajax,
					'SCD.ErrorHandler': SCD.ErrorHandler
				}, 'ScheduleAPI' ) ) {
					return;
				}
			}
		} );
	}

	if ( SCD.Modules && SCD.Modules.Schedule && SCD.Modules.Schedule.API ) {
		SCD.Modules.Schedule.API.prototype = {
		/**
		 * Save step data
		 * @param data
		 */
			saveStepData: function( data ) {
				if ( SCD.Modules.Schedule && SCD.Modules.Schedule.Debug ) {
					SCD.Modules.Schedule.Debug.logApiCall( 'POST', 'scd_save_step', data );
				}

				return SCD.Ajax.post( 'scd_save_step', {
					step: 'schedule',
					data: data
				} ).done( function( response ) {
					if ( SCD.Modules.Schedule && SCD.Modules.Schedule.Debug ) {
						SCD.Modules.Schedule.Debug.log( 'success', 'API', 'Save step data successful', response );
					}
				} ).fail( function( xhr ) {
					if ( SCD.Modules.Schedule && SCD.Modules.Schedule.Debug ) {
						SCD.Modules.Schedule.Debug.logError( 'API', xhr.responseJSON || xhr, 'scd_save_step' );
					}
					SCD.ErrorHandler.handleAjaxError( xhr, 'scd_save_step', { step: 'schedule' } );
				} );
			},

			/**
			 * Validate schedule dates
			 * @param data
			 */
			validateSchedule: function( data ) {
				if ( SCD.Modules.Schedule && SCD.Modules.Schedule.Debug ) {
					SCD.Modules.Schedule.Debug.logApiCall( 'POST', 'scd_validate_schedule', data );
				}

				if ( SCD.Utils.isEmpty( data ) ) {
					var error = new Error( 'Schedule data is required for validation' );
					SCD.ErrorHandler.handle( error, 'ScheduleAPI.validateSchedule', SCD.ErrorHandler.SEVERITY.MEDIUM );
					return $.Deferred().reject( error ).promise();
				}

				return SCD.Ajax.post( 'scd_validate_schedule', {
					startDate: data.startDate,
					endDate: data.endDate,
					startTime: data.startTime,
					endTime: data.endTime,
					timezone: data.timezone
				} ).done( function( response ) {
					if ( SCD.Modules.Schedule && SCD.Modules.Schedule.Debug ) {
						SCD.Modules.Schedule.Debug.log( 'success', 'API', 'Schedule validation result', response );
					}
				} ).fail( function( xhr ) {
					if ( SCD.Modules.Schedule && SCD.Modules.Schedule.Debug ) {
						SCD.Modules.Schedule.Debug.logError( 'API', xhr.responseJSON || xhr, 'scd_validate_schedule' );
					}
					SCD.ErrorHandler.handleAjaxError( xhr, 'scd_validate_schedule', {
						startDate: data.startDate,
						endDate: data.endDate
					} );
				} );
			},

			/**
			 * Get schedule presets
			 */
			getPresets: function() {
				if ( SCD.Modules.Schedule && SCD.Modules.Schedule.Debug ) {
					SCD.Modules.Schedule.Debug.logApiCall( 'POST', 'scd_get_schedule_presets', {} );
				}

				return SCD.Ajax.post( 'scd_get_schedule_presets', {} ).done( function( response ) {
					if ( SCD.Modules.Schedule && SCD.Modules.Schedule.Debug ) {
						SCD.Modules.Schedule.Debug.log( 'success', 'API', 'Presets loaded', response );
					}
				} ).fail( function( xhr ) {
					SCD.ErrorHandler.handleAjaxError( xhr, 'scd_get_schedule_presets', {} );
				} );
			},

			/**
			 * Check for schedule conflicts
			 * @param data
			 */
			checkConflicts: function( data ) {
				if ( SCD.Modules.Schedule && SCD.Modules.Schedule.Debug ) {
					SCD.Modules.Schedule.Debug.logApiCall( 'POST', 'scd_check_schedule_conflicts', data );
				}

				return SCD.Ajax.post( 'scd_check_schedule_conflicts', {
					startDate: data.startDate,
					endDate: data.endDate,
					campaignId: data.campaignId || null,
					productIds: data.productIds || []
				} ).done( function( response ) {
					if ( SCD.Modules.Schedule && SCD.Modules.Schedule.Debug ) {
						var hasConflicts = response && response.data && response.data.conflicts && 0 < response.data.conflicts.length;
						var logType = hasConflicts ? 'warning' : 'success';
						SCD.Modules.Schedule.Debug.log( logType, 'API', 'Conflict check result', response );
					}
				} ).fail( function( xhr ) {
					if ( SCD.Modules.Schedule && SCD.Modules.Schedule.Debug ) {
						SCD.Modules.Schedule.Debug.logError( 'API', xhr.responseJSON || xhr, 'scd_check_schedule_conflicts' );
					}
					SCD.ErrorHandler.handleAjaxError( xhr, 'scd_check_schedule_conflicts', {
						startDate: data.startDate,
						endDate: data.endDate
					} );
				} );
			},

			/**
			 * Load schedule step data
			 */
			loadStepData: function() {
				if ( SCD.Modules.Schedule && SCD.Modules.Schedule.Debug ) {
					SCD.Modules.Schedule.Debug.logApiCall( 'POST', 'scd_load_data', { step: 'schedule' } );
				}

				return SCD.Ajax.post( 'scd_load_data', {
					step: 'schedule'
				} ).done( function( response ) {
					if ( SCD.Modules.Schedule && SCD.Modules.Schedule.Debug ) {
						SCD.Modules.Schedule.Debug.log( 'success', 'API', 'Step data loaded', response );
					}
				} ).fail( function( xhr ) {
					if ( SCD.Modules.Schedule && SCD.Modules.Schedule.Debug ) {
						SCD.Modules.Schedule.Debug.logError( 'API', xhr.responseJSON || xhr, 'scd_load_data' );
					}
					SCD.ErrorHandler.handleAjaxError( xhr, 'scd_load_data', { step: 'schedule' } );
				} );
			}
		};
	} else {
		// Fallback: Define API module directly if registerModule isn't available
		SCD.Modules.Schedule.API = function() {};
		SCD.Modules.Schedule.API.prototype = {
			saveStepData: function( data ) {
				if ( !SCD.Ajax || !SCD.Ajax.post ) {
					console.error( '[Schedule API] SCD.Ajax not available' );
					return $.Deferred().reject( 'Ajax module not available' );
				}
				return SCD.Ajax.post( 'scd_save_step', {
					step: 'schedule',
					data: data
				} );
			},

			validateSchedule: function( data ) {
				if ( !data ) {
					return $.Deferred().reject( 'Schedule data is required' );
				}
				if ( !SCD.Ajax || !SCD.Ajax.post ) {
					console.error( '[Schedule API] SCD.Ajax not available' );
					return $.Deferred().reject( 'Ajax module not available' );
				}
				return SCD.Ajax.post( 'scd_validate_schedule', data );
			},

			getPresets: function() {
				if ( !SCD.Ajax || !SCD.Ajax.post ) {
					console.error( '[Schedule API] SCD.Ajax not available' );
					return $.Deferred().reject( 'Ajax module not available' );
				}
				return SCD.Ajax.post( 'scd_get_schedule_presets', {} );
			},

			loadStepData: function() {
				if ( !SCD.Ajax || !SCD.Ajax.post ) {
					console.error( '[Schedule API] SCD.Ajax not available' );
					return $.Deferred().reject( 'Ajax module not available' );
				}
				return SCD.Ajax.post( 'scd_load_data', { step: 'schedule' } );
			}
		};
	}

} )( jQuery );