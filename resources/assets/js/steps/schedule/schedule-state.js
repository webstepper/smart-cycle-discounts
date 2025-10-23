/**
 * Schedule State Module
 *
 * Simple state management for the schedule step - no complex inheritance
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

	/**
	 * Schedule State - Direct implementation without inheritance
	 */
	SCD.Modules.Schedule.State = function() {
		if ( SCD.Modules.Schedule.Debug ) {
			SCD.Modules.Schedule.Debug.logInit( 'Schedule State', {
				method: 'constructor'
			} );
		}

		// Initialize state directly
		this._state = {
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
			isDirty: false,
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

		// Simple subscriber pattern for state changes
		this._subscribers = [];
	};

	// Add methods directly to prototype
	SCD.Modules.Schedule.State.prototype = {
		/**
		 * Get current state
		 */
		getState: function() {
			return this._state;
		},

		/**
		 * Get value of a specific field
		 * @param fieldName
		 * @return mixed
		 */
		getData: function( fieldName ) {
			if ( !fieldName ) {
				return undefined;
			}
			return this._state[fieldName];
		},

		/**
		 * Set state and notify subscribers
		 * @param updates
		 */
		setState: function( updates ) {
			if ( SCD.Modules.Schedule.Debug ) {
				SCD.Modules.Schedule.Debug.log( 'info', 'State', 'setState called with:', updates );
			}

			var key;
			var changed = false;
			var oldValues = {};

			// Apply updates and track changes
			for ( key in updates ) {
				if ( Object.prototype.hasOwnProperty.call( updates, key ) && this._state[key] !== updates[key] ) {
					oldValues[key] = this._state[key];
					this._state[key] = updates[key];
					changed = true;
				}
			}

			// Mark as dirty if changed
			if ( changed && !this._state.isDirty ) {
				this._state.isDirty = true;
			}

			// Calculate duration if dates changed
			if ( changed && ( 'startDate' in updates || 'endDate' in updates ||
					'startTime' in updates || 'endTime' in updates ) ) {
				this._calculateDuration();
			}

			// Update validation state if errors changed
			if ( 'errors' in updates ) {
				this._state.isValid = 0 === Object.keys( this._state.errors ).length;
			}

			// Notify subscribers of changes
			if ( changed ) {
				if ( SCD.Modules.Schedule.Debug ) {
					SCD.Modules.Schedule.Debug.logStateChange( 'State', updates, oldValues );
				}
				this._notifySubscribers( updates, oldValues );
			}

			// Emit jQuery events for compatibility
			if ( changed ) {
				for ( key in updates ) {
					if ( Object.prototype.hasOwnProperty.call( updates, key ) ) {
						$( document ).trigger( 'scd:schedule:state:changed', {
							property: key,
							value: updates[key],
							oldValue: oldValues[key],
							state: this._state
						} );
					}
				}
			}
		},

		/**
		 * Subscribe to state changes
		 * @param callback
		 */
		subscribe: function( callback ) {
			if ( 'function' === typeof callback ) {
				this._subscribers.push( callback );

				if ( SCD.Modules.Schedule.Debug ) {
					SCD.Modules.Schedule.Debug.log( 'info', 'State', 'New subscriber added. Total subscribers:', this._subscribers.length );
				}
			}
		},

		/**
		 * Unsubscribe from state changes
		 * @param callback
		 */
		unsubscribe: function( callback ) {
			var index = this._subscribers.indexOf( callback );
			if ( -1 < index ) {
				this._subscribers.splice( index, 1 );
			}
		},

		/**
		 * Notify all subscribers of state changes
		 * @param changes
		 * @param oldValues
		 */
		_notifySubscribers: function( changes, oldValues ) {
			var self = this;
			$.each( this._subscribers, function( i, callback ) {
				try {
					callback( self._state, changes, oldValues );
				} catch ( error ) {
					console.error( '[Schedule State] Subscriber error:', error );
				}
			} );
		},

		/**
		 * Calculate duration based on current dates/times
		 */
		_calculateDuration: function() {
			if ( SCD.Modules.Schedule.Debug ) {
				SCD.Modules.Schedule.Debug.time( 'Duration Calculation' );
			}

			if ( !this._state.startDate || !this._state.endDate ) {
				this._state.duration = null;
				this._state.durationText = '';

				if ( SCD.Modules.Schedule.Debug ) {
					SCD.Modules.Schedule.Debug.log( 'info', 'State', 'Duration calculation skipped - missing dates' );
					SCD.Modules.Schedule.Debug.timeEnd( 'Duration Calculation' );
				}
				return;
			}

			// Parse dates
			var start = new Date( this._state.startDate + ' ' + this._state.startTime );
			var end = new Date( this._state.endDate + ' ' + this._state.endTime );
			var diff = end - start;

			if ( 0 < diff ) {
				var days = Math.floor( diff / ( 1000 * 60 * 60 * 24 ) );
				var hours = Math.floor( ( diff % ( 1000 * 60 * 60 * 24 ) ) / ( 1000 * 60 * 60 ) );

				this._state.duration = {
					days: days,
					hours: hours,
					totalMs: diff,
					totalDays: days + ( hours / 24 )
				};

				// Format duration text
				if ( 0 < days ) {
					this._state.durationText = days + ( 1 === days ? ' day' : ' days' );
					if ( 0 < hours ) {
						this._state.durationText += ', ' + hours + ( 1 === hours ? ' hour' : ' hours' );
					}
				} else {
					this._state.durationText = hours + ( 1 === hours ? ' hour' : ' hours' );
				}
			} else {
				this._state.duration = null;
				this._state.durationText = 'Invalid duration';
			}

			if ( SCD.Modules.Schedule.Debug ) {
				SCD.Modules.Schedule.Debug.log( 'data', 'State', 'Duration calculated:', {
					duration: this._state.duration,
					text: this._state.durationText
				} );
				SCD.Modules.Schedule.Debug.timeEnd( 'Duration Calculation' );
			}
		},

		/**
		 * Convert state to JSON for saving
		 */
		toJSON: function() {
			var json = {
				// Router will convert camelCase to snake_case for backend
				startDate: this._state.startDate,
				endDate: this._state.endDate,
				startTime: this._state.startTime,
				endTime: this._state.endTime,
				timezone: this._state.timezone,
				startType: this._state.startType,
				presetId: this._state.presetId,
				// Recurring fields
				enableRecurring: this._state.enableRecurring,
				recurrencePattern: this._state.recurrencePattern,
				recurrenceInterval: this._state.recurrenceInterval,
				recurrenceDays: this._state.recurrenceDays,
				recurrenceEndType: this._state.recurrenceEndType,
				recurrenceCount: this._state.recurrenceCount,
				recurrenceEndDate: this._state.recurrenceEndDate
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
			this._state.isDirty = false;
			this._state.lastSaved = new Date();
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
			this._state = {
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

			// Notify of reset
			this._notifySubscribers( this._state, {} );
		},

		/**
		 * Clean up module
		 */
		destroy: function() {
			if ( SCD.Modules.Schedule.Debug ) {
				SCD.Modules.Schedule.Debug.log( 'warning', 'State', 'Destroying state module' );
			}

			// Clear all subscribers
			this._subscribers = [];

			// Reset state
			this.reset();
		}
	};

} )( jQuery );