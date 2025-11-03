/**
 * Schedule State
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/steps/schedule/schedule-state.js
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

( function( $ ) {
	'use strict';

	// Ensure namespaces exist
	window.SCD = window.SCD || {};
	SCD.Modules = SCD.Modules || {};
	SCD.Modules.Schedule = SCD.Modules.Schedule || {};

	/**
	 * Schedule State Constructor
	 * Extends BaseState for state management
	 */
	SCD.Modules.Schedule.State = function() {
		if ( SCD.Modules.Schedule.Debug ) {
			SCD.Modules.Schedule.Debug.logInit( 'Schedule State', {
				method: 'constructor'
			} );
		}

		// Define initial state
		var initialState = {
			// Date and time values
			startDate: '',
			endDate: '',
			startTime: '00:00',
			endTime: '23:59',

			// Timezone
			timezone: ( window.scdWizardData && window.scdWizardData.timezone ) || 'UTC',

			// Start type
			startType: 'immediate',

			// Duration calculation
			duration: null,
			durationText: '',

			// Preset information
			currentPreset: null,
			presetId: '',

			// UI state
			isLoading: false,
			isSaving: false,
			isValidating: false,
			showTimeline: false,

			// Validation state
			isValid: true,
			errors: {},
			warnings: {},

			// Change tracking
			lastSaved: null,
			originalState: null,

			// Campaign context
			campaignId: null,
			campaignName: '',
			productCount: 0,

			// Recurring schedule fields
			enableRecurring: false,
			recurrencePattern: 'daily',
			recurrenceInterval: 1,
			recurrenceDays: [],
			recurrenceEndType: 'never',
			recurrenceCount: 10,
			recurrenceEndDate: ''
		};

		// Call parent constructor
		if ( window.SCD && window.SCD.Shared && window.SCD.Shared.BaseState ) {
			SCD.Shared.BaseState.call( this, initialState );
		}
	};

	// Set up proper prototype chain
	if ( window.SCD && window.SCD.Shared && window.SCD.Shared.BaseState ) {
		SCD.Modules.Schedule.State.prototype = Object.create( SCD.Shared.BaseState.prototype );
		SCD.Modules.Schedule.State.prototype.constructor = SCD.Modules.Schedule.State;
	}

	// Extend prototype with custom methods
	$.extend( SCD.Modules.Schedule.State.prototype, {
		/**
		 * Override setState to add duration calculation
		 * @param updates
		 */
		setState: function( updates ) {
			if ( SCD.Modules.Schedule.Debug ) {
				SCD.Modules.Schedule.Debug.log( 'info', 'State', 'setState called with:', updates );
			}

			// Calculate duration if dates changed
			if ( updates && ( 'startDate' in updates || 'endDate' in updates ||
					'startTime' in updates || 'endTime' in updates ) ) {
				// Apply updates first via parent
				if ( window.SCD && window.SCD.Shared && window.SCD.Shared.BaseState ) {
					SCD.Shared.BaseState.prototype.setState.call( this, updates, false );
				}
				// Then calculate duration with updated state
				this._calculateDuration();
			} else {
				// No date changes, just apply updates
				if ( window.SCD && window.SCD.Shared && window.SCD.Shared.BaseState ) {
					SCD.Shared.BaseState.prototype.setState.call( this, updates, false );
				}
			}

			// Update validation state if errors changed
			if ( updates && 'errors' in updates ) {
				var state = this.getState();
				var newUpdates = { isValid: 0 === Object.keys( state.errors ).length };
				if ( window.SCD && window.SCD.Shared && window.SCD.Shared.BaseState ) {
					SCD.Shared.BaseState.prototype.setState.call( this, newUpdates, false );
				}
			}
		},

		/**
		 * Calculate duration based on current dates/times
		 */
		_calculateDuration: function() {
			if ( SCD.Modules.Schedule.Debug ) {
				SCD.Modules.Schedule.Debug.time( 'Duration Calculation' );
			}

			var state = this.getState();
			if ( !state.startDate || !state.endDate ) {
				var updates = {
					duration: null,
					durationText: ''
				};
				if ( window.SCD && window.SCD.Shared && window.SCD.Shared.BaseState ) {
					SCD.Shared.BaseState.prototype.setState.call( this, updates, false );
				}

				if ( SCD.Modules.Schedule.Debug ) {
					SCD.Modules.Schedule.Debug.log( 'info', 'State', 'Duration calculation skipped - missing dates' );
					SCD.Modules.Schedule.Debug.timeEnd( 'Duration Calculation' );
				}
				return;
			}

			// Parse dates
			var start = new Date( state.startDate + ' ' + state.startTime );
			var end = new Date( state.endDate + ' ' + state.endTime );

			// Check for invalid dates
			if ( isNaN( start.getTime() ) || isNaN( end.getTime() ) ) {
				if ( SCD.Modules.Schedule.Debug ) {
					SCD.Modules.Schedule.Debug.log( 'error', 'State', 'Invalid dates for duration calculation' );
				}
				var invalidUpdates = {
					duration: null,
					durationText: 'Invalid dates'
				};
				if ( window.SCD && window.SCD.Shared && window.SCD.Shared.BaseState ) {
					SCD.Shared.BaseState.prototype.setState.call( this, invalidUpdates, false );
				}
				return;
			}

			var diff = end - start;

			var durationUpdates = {};
			if ( 0 < diff ) {
				// Calculate difference in minutes for better precision
				var totalMinutes = Math.floor( diff / ( 1000 * 60 ) );

				// Account for timezone offset changes (DST transitions)
				var startOffset = start.getTimezoneOffset();
				var endOffset = end.getTimezoneOffset();
				var offsetDiffMinutes = startOffset - endOffset;

				// Adjust total minutes for DST transition
				totalMinutes += offsetDiffMinutes;

				// Convert to days, hours, minutes
				var days = Math.floor( totalMinutes / ( 60 * 24 ) );
				var hours = Math.floor( ( totalMinutes % ( 60 * 24 ) ) / 60 );
				var minutes = totalMinutes % 60;

				durationUpdates.duration = {
					days: days,
					hours: hours,
					minutes: minutes,
					totalMs: diff,
					totalMinutes: totalMinutes,
					totalDays: days + ( hours / 24 ) + ( minutes / 1440 )
				};

				// Format duration text
				if ( 0 < days ) {
					durationUpdates.durationText = days + ( 1 === days ? ' day' : ' days' );
					if ( 0 < hours ) {
						durationUpdates.durationText += ', ' + hours + ( 1 === hours ? ' hour' : ' hours' );
					}
				} else if ( 0 < hours ) {
					durationUpdates.durationText = hours + ( 1 === hours ? ' hour' : ' hours' );
				} else {
					durationUpdates.durationText = minutes + ( 1 === minutes ? ' minute' : ' minutes' );
				}
			} else {
				durationUpdates.duration = null;
				durationUpdates.durationText = 'Invalid duration';
			}

			if ( window.SCD && window.SCD.Shared && window.SCD.Shared.BaseState ) {
				SCD.Shared.BaseState.prototype.setState.call( this, durationUpdates, false );
			}

			if ( SCD.Modules.Schedule.Debug ) {
				SCD.Modules.Schedule.Debug.log( 'data', 'State', 'Duration calculated:', {
					duration: durationUpdates.duration,
					text: durationUpdates.durationText
				} );
				SCD.Modules.Schedule.Debug.timeEnd( 'Duration Calculation' );
			}
		},

		/**
		 * Convert state to JSON for saving
		 */
		toJSON: function() {
			var state = this.getState();
			var json = {
				// Router will convert camelCase to snake_case for backend
				startDate: state.startDate,
				endDate: state.endDate,
				startTime: state.startTime,
				endTime: state.endTime,
				timezone: state.timezone,
				startType: state.startType,
				presetId: state.presetId,
				// Recurring fields
				enableRecurring: state.enableRecurring,
				recurrencePattern: state.recurrencePattern,
				recurrenceInterval: state.recurrenceInterval,
				recurrenceDays: state.recurrenceDays,
				recurrenceEndType: state.recurrenceEndType,
				recurrenceCount: state.recurrenceCount,
				recurrenceEndDate: state.recurrenceEndDate
			};

			if ( SCD.Modules.Schedule.Debug ) {
				SCD.Modules.Schedule.Debug.log( 'data', 'State', 'toJSON output:', json );
			}

			return json;
		},

		/**
		 * Load state from saved data
		 * @param data
		 */
		fromJSON: function( data ) {
			if ( !data ) {return;}

			if ( SCD.Modules.Schedule.Debug ) {
				SCD.Modules.Schedule.Debug.log( 'data', 'State', 'fromJSON input:', data );
			}

			// Data already converted to camelCase by server

			var updates = {};

			// Map backend fields to state
			if ( data.startDate !== undefined ) {updates.startDate = data.startDate;}
			if ( data.endDate !== undefined ) {updates.endDate = data.endDate;}
			if ( data.startTime !== undefined ) {updates.startTime = data.startTime;}
			if ( data.endTime !== undefined ) {updates.endTime = data.endTime;}
			if ( data.timezone !== undefined ) {updates.timezone = data.timezone;}
			if ( data.startType !== undefined ) {updates.startType = data.startType;}
			if ( data.presetId !== undefined ) {updates.presetId = data.presetId;}
			// Recurring fields
			if ( data.enableRecurring !== undefined ) {updates.enableRecurring = data.enableRecurring;}
			if ( data.recurrencePattern !== undefined ) {updates.recurrencePattern = data.recurrencePattern;}
			if ( data.recurrenceInterval !== undefined ) {updates.recurrenceInterval = data.recurrenceInterval;}
			if ( data.recurrenceDays !== undefined ) {updates.recurrenceDays = data.recurrenceDays;}
			if ( data.recurrenceEndType !== undefined ) {updates.recurrenceEndType = data.recurrenceEndType;}
			if ( data.recurrenceCount !== undefined ) {updates.recurrenceCount = data.recurrenceCount;}
			if ( data.recurrenceEndDate !== undefined ) {updates.recurrenceEndDate = data.recurrenceEndDate;}

			// Apply updates
			this.setState( updates );

			// Clear dirty flag since we're loading saved data
			this.clearDirty();
			var lastSavedUpdate = { lastSaved: new Date() };
			if ( window.SCD && window.SCD.Shared && window.SCD.Shared.BaseState ) {
				SCD.Shared.BaseState.prototype.setState.call( this, lastSavedUpdate, false );
			}
		},

		/**
		 * Apply preset to state
		 * @param preset
		 * @param dates
		 */
		applyPreset: function( preset, dates ) {
			if ( !preset || !dates ) {return;}

			if ( SCD.Modules.Schedule.Debug ) {
				SCD.Modules.Schedule.Debug.log( 'info', 'State', 'Applying preset:', { preset: preset, dates: dates } );
			}

			this.setState( {
				currentPreset: preset,
				presetId: preset.id || '',
				startDate: dates.start || '',
				endDate: dates.end || '',
				startTime: dates.startTime || '00:00',
				endTime: dates.endTime || '23:59'
			} );

			// Emit preset applied event
			$( document ).trigger( 'scd:schedule:preset:applied', preset );
		},

		/**
		 * Clear schedule
		 */
		clearSchedule: function() {
			this.setState( {
				startDate: '',
				endDate: '',
				startTime: '00:00',
				endTime: '23:59',
				currentPreset: null,
				presetId: '',
				duration: null,
				durationText: ''
			} );
		},

		/**
		 * Reset to initial state
		 */
		reset: function() {
			if ( SCD.Modules.Schedule.Debug ) {
				SCD.Modules.Schedule.Debug.log( 'warning', 'State', 'Resetting state to initial values' );
			}

			// Store original values for reset
			var originalTimezone = ( window.scdWizardData && window.scdWizardData.timezone ) || 'UTC';

			// Reset all values
			var defaults = {
				startDate: '',
				endDate: '',
				startTime: '00:00',
				endTime: '23:59',
				timezone: originalTimezone,
				startType: 'immediate',
				duration: null,
				durationText: '',
				currentPreset: null,
				presetId: '',
				isLoading: false,
				isSaving: false,
				isValidating: false,
				showTimeline: false,
				isValid: true,
				errors: {},
				warnings: {},
				isDirty: false,
				lastSaved: null,
				originalState: null,
				campaignId: null,
				campaignName: '',
				productCount: 0,
				enableRecurring: false,
				recurrencePattern: 'daily',
				recurrenceInterval: 1,
				recurrenceDays: [],
				recurrenceEndType: 'never',
				recurrenceCount: 10,
				recurrenceEndDate: ''
			};

			// Call parent reset
			if ( window.SCD && window.SCD.Shared && window.SCD.Shared.BaseState ) {
				SCD.Shared.BaseState.prototype.reset.call( this, defaults );
			}
		},

		/**
		 * Clean up module
		 */
		destroy: function() {
			if ( SCD.Modules.Schedule.Debug ) {
				SCD.Modules.Schedule.Debug.log( 'warning', 'State', 'Destroying state module' );
			}

			// Call parent destroy
			if ( window.SCD && window.SCD.Shared && window.SCD.Shared.BaseState ) {
				SCD.Shared.BaseState.prototype.destroy.call( this );
			}
		}
	} );

} )( jQuery );
