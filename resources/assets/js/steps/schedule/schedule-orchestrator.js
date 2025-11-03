/**
 * Schedule Orchestrator
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/steps/schedule/schedule-orchestrator.js
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 */

( function( $ ) {
	'use strict';

	// Ensure namespaces exist
	window.SCD = window.SCD || {};
	SCD.Steps = SCD.Steps || {};
	SCD.Modules = SCD.Modules || {};
	SCD.Modules.Schedule = SCD.Modules.Schedule || {};

	/**
	 * Schedule Orchestrator
	 * Created using BaseOrchestrator.createStep() factory method
	 * Inherits from BaseOrchestrator with EventManager and StepPersistence mixins
	 */
	SCD.Steps.ScheduleOrchestrator = SCD.Shared.BaseOrchestrator.createStep( 'schedule', {

		/**
		 * Initialize step modules
		 * Called by BaseOrchestrator's initializeModules
		 */
		initializeStep: function() {
			try {
				// Create modules if they don't exist
				if ( !this.modules.state ) {
					this.modules.state = new SCD.Modules.Schedule.State();
				}

				if ( !this.modules.api ) {
					this.modules.api = new SCD.Modules.Schedule.API();
				}

				// Validation is handled by ValidationManager through step-persistence mixin
			} catch ( error ) {
				this.safeErrorHandle( error, 'schedule-orchestrator-init-step', SCD.ErrorHandler.SEVERITY.HIGH );
				this.showError( 'Failed to initialize schedule components. Please refresh the page.', 'error', 10000 );
			}
		},

		/**
		 * Custom initialization for schedule step
		 * The factory's init handles standard setup, this handles schedule-specific UI setup
		 * @param wizard
		 * @param config
		 */
		init: function( wizard, config ) {
			// Initialize UI components (data will be loaded by wizard orchestrator)
			this.setupDatePickers();
			this.initializePresets();

			// Set initial visual state
			this.$container.find( 'input[name="start_type"]:checked' )
				.closest( '.scd-radio-option' )
				.addClass( 'scd-radio-option--selected' );

			// Set initial time constraints
			this.updateTimeConstraints();
		},

		/**
		 * Custom event binding
		 */
		onBindEvents: function() {
			var self = this;

			// Update time constraints when step is shown (in case time has passed)
			$( document ).on( 'scd:step:shown', function( event, stepName ) {
				if ( 'schedule' === stepName ) {
					self.updateTimeConstraints();
				}
			} );

			// Date and time changes
		// Real-time date validation using centralized validation methods
		// Provides immediate feedback when user changes date fields
		this.$container.on( 'change', '#start_date, #end_date', function() {
			var field = $( this ).attr( 'id' ).replace( '_date', '' );
			var $input = $( this );
			var selectedDate = $input.val();

			// Auto-select "Scheduled Start" when user interacts with start date
			if ( 'start' === field ) {
				var $scheduledRadio = $( 'input[name="start_type"][value="scheduled"]' );
				if ( ! $scheduledRadio.is( ':checked' ) ) {
					$scheduledRadio.prop( 'checked', true ).trigger( 'change' );
				}
			}

			// Clear stale duration_seconds when user manually changes dates
			if ( 'end' === field ) {
				var $durationField = $( 'input[name="duration_seconds"]' );
				if ( $durationField.length ) {
					$durationField.remove();
				}
			}

			// Real-time validation using centralized methods
			if ( selectedDate ) {
				var state = self.modules.state.getState();
				var validation;

				// Use centralized validation methods (single source of truth)
				if ( 'start' === field ) {
					validation = self._validateStartTime( selectedDate, state.startTime || '00:00' );
				} else {
					validation = self._validateEndTime( selectedDate, state.endTime || '23:59', state.startDate, state.startTime );
				}

				// Show validation errors with consistent messages (date-level only)
				if ( ! validation.valid && validation.field === field + '_date' ) {
					if ( window.SCD && window.SCD.ValidationError ) {
						SCD.ValidationError.show( $input, validation.message );
					}
				} else {
					// Valid date - clear any errors
					if ( window.SCD && window.SCD.ValidationError ) {
						SCD.ValidationError.clear( $input );
					}
				}
			}

			self.handleDateChange( field, selectedDate );
			self.updateDurationDisplay();
		} );



		// Real-time time validation using centralized validation methods
		// Provides immediate feedback when user changes time fields
		this.$container.on( 'change', '#start_time, #end_time', function() {
			var field = $( this ).attr( 'id' ).replace( '_time', '' );
			var $input = $( this );
			var selectedTime = $input.val();

			// Skip validation if time is empty
			if ( ! selectedTime ) {
				if ( window.SCD && window.SCD.ValidationError ) {
					SCD.ValidationError.clear( $input );
				}
				self.handleTimeChange( field, selectedTime );
				self.updateDurationDisplay();
				return;
			}

			// Clear stale duration_seconds when user manually changes end time
			if ( 'end' === field ) {
				var $durationField = $( 'input[name="duration_seconds"]' );
				if ( $durationField.length ) {
					$durationField.remove();
				}
			}

			// Get current state for validation context
			var state = self.modules.state.getState();
			var validation;

			// Use centralized validation methods (single source of truth)
			if ( 'start' === field ) {
				validation = self._validateStartTime( state.startDate, selectedTime );
			} else {
				validation = self._validateEndTime( state.endDate, selectedTime, state.startDate, state.startTime );
			}

			// Show validation errors with consistent messages
			if ( ! validation.valid ) {
				if ( window.SCD && window.SCD.ValidationError ) {
					SCD.ValidationError.show( $input, validation.message );
				}
			} else {
				// Valid time - clear any errors
				if ( window.SCD && window.SCD.ValidationError ) {
					SCD.ValidationError.clear( $input );
				}
			}

			self.handleTimeChange( field, selectedTime );
			self.updateDurationDisplay();
		} );

			// Start type changes
			this.$container.on( 'change', 'input[name="start_type"]', function() {
				var value = $( this ).val();
				var $radioOptions = self.$container.find( '.scd-radio-option' );
				$radioOptions.removeClass( 'scd-radio-option--selected' );
				$( this ).closest( '.scd-radio-option' ).addClass( 'scd-radio-option--selected' );
				self.handleStartTypeChange( value );
			} );

			// Clear end date
			this.$container.on( 'click', '.scd-clear-end-date', function( e ) {
				e.preventDefault();
				// Clear end date fields
				$( '#end_date' ).val( '' );
				$( '#end_date_display' ).val( '' );
				$( '#end_time' ).val( self.getDefaultEndTime() );
				// Update duration display
				self.updateDurationDisplay();
				// Trigger change event
				$( '#end_date' ).trigger( 'change' );
			} );

			// Calendar icon buttons
			this.$container.on( 'click', '.scd-calendar-icon', function( e ) {
				e.preventDefault();
				var targetId = $( this ).data( 'target' );
				if ( targetId ) {
					$( '#' + targetId ).datepicker( 'show' );
				}
			} );

			// Preset selection
			$( document ).on( 'scd:preset:selected', function( e, preset ) {
				self.applyPreset( preset );
			} );

			// Recurring schedule
			this.$container.on( 'change', '#enable_recurring', function() {
				var isChecked = $( this ).prop( 'checked' );
				var $recurringOptions = $( '#scd-recurring-options' );
				$recurringOptions.toggle( isChecked );
				$recurringOptions.attr( 'aria-hidden', !isChecked );
				if ( self.modules.state && 'function' === typeof self.modules.state.setState ) {
					self.modules.state.setState( { enableRecurring: isChecked } );
				}
				self.updateRecurrencePreview();
			} );

			// Recurrence pattern
			this.$container.on( 'change', '#recurrence_pattern', function() {
				var pattern = $( this ).val();
				$( '#scd-weekly-options' ).toggle( 'weekly' === pattern );
				if ( self.modules.state && 'function' === typeof self.modules.state.setState ) {
					self.modules.state.setState( { recurrencePattern: pattern } );
				}
				self.updateRecurrencePreview();
			} );

			// Recurrence interval
			this.$container.on( 'change', '#recurrence_interval', function() {
				var interval = parseInt( $( this ).val() ) || 1;
				if ( self.modules.state && 'function' === typeof self.modules.state.setState ) {
					self.modules.state.setState( { recurrenceInterval: interval } );
				}
				self.updateRecurrencePreview();
			} );

			// Recurrence days
			this.$container.on( 'change', 'input[name="recurrence_days[]"]', function() {
				var days = self.$container.find( 'input[name="recurrence_days[]"]:checked' ).map( function() {
					return $( this ).val();
				} ).get();
				if ( self.modules.state && 'function' === typeof self.modules.state.setState ) {
					self.modules.state.setState( { recurrenceDays: days } );
				}
				self.updateRecurrencePreview();
			} );

			// Recurrence end type
			this.$container.on( 'change', 'input[name="recurrence_end_type"]', function() {
				var endType = $( this ).val();
				$( '#recurrence_count' ).prop( 'disabled', 'after' !== endType );
				$( '#recurrence_end_date' ).prop( 'disabled', 'on' !== endType );
				if ( self.modules.state && 'function' === typeof self.modules.state.setState ) {
					self.modules.state.setState( { recurrenceEndType: endType } );
				}
				self.updateRecurrencePreview();
			} );

			// Recurrence end count
			this.$container.on( 'change', '#recurrence_count', function() {
				var count = parseInt( $( this ).val() ) || 10;
				if ( self.modules.state && 'function' === typeof self.modules.state.setState ) {
					self.modules.state.setState( { recurrenceCount: count } );
				}
				self.updateRecurrencePreview();
			} );

			// Recurrence end date
			this.$container.on( 'change', '#recurrence_end_date', function() {
				var endDate = $( this ).val();
				if ( self.modules.state && 'function' === typeof self.modules.state.setState ) {
					self.modules.state.setState( { recurrenceEndDate: endDate } );
				}
				self.updateRecurrencePreview();
			} );
		},

		/**
		 * Handle date changes
		 * @param field
		 * @param value
		 */
		handleDateChange: function( field, value ) {
			try {
				var stateUpdate = {};
				stateUpdate[field + 'Date'] = value;
				if ( this.modules.state && 'function' === typeof this.modules.state.setState ) {
					this.modules.state.setState( stateUpdate );
				}
				$( document ).trigger( 'scd:schedule:' + field + ':changed', value );

				// Update time constraints when date changes
				this.updateTimeConstraints();
			} catch ( error ) {
			this.safeErrorHandle( error, 'schedule-date-change' );
				this.showError( 'Failed to update date. Please check your input.', 'error', 5000 );
			}
		},

		/**
		 * Handle time changes
		 * @param field
		 * @param value
		 */
		handleTimeChange: function( field, value ) {
			try {
				var stateUpdate = {};
				stateUpdate[field + 'Time'] = value;
				if ( this.modules.state && 'function' === typeof this.modules.state.setState ) {
					this.modules.state.setState( stateUpdate );
				}
				$( document ).trigger( 'scd:schedule:' + field + '-time:changed', value );

				// Update time constraints when time changes (affects end time constraint)
				if ( 'start' === field ) {
					this.updateTimeConstraints();
				}
			} catch ( error ) {
			this.safeErrorHandle( error, 'schedule-time-change' );
				this.showError( 'Failed to update time. Please check your input.', 'error', 5000 );
			}
		},

		/**
		 * Update time input constraints to prevent past time selection
		 *
		 * Dynamically sets min attribute on time inputs based on:
		 * - Current time (if date is today)
		 * - Start time (for end time if same day)
		 */
		updateTimeConstraints: function() {
			var $startDate = $( '#start_date' );
			var $startTime = $( '#start_time' );
			var $endDate = $( '#end_date' );
			var $endTime = $( '#end_time' );

			if ( !$startDate.length || !$startTime.length ) {
				return;
			}

			var startDateValue = $startDate.val();
			var startTimeValue = $startTime.val();
			var endDateValue = $endDate.val();

			var now = new Date();
			var todayStr = this.formatDateISO( now );
			var currentTimeStr = this.formatTimeHHMM( now );

			// Constraint 1: If start_date is today, set start_time min to current time
			if ( startDateValue === todayStr ) {
				$startTime.attr( 'min', currentTimeStr );

				if ( startTimeValue && startTimeValue < currentTimeStr ) {
					if ( window.SCD && window.SCD.ValidationError ) {
						SCD.ValidationError.show(
							$startTime,
							'Start time cannot be in the past. Please select ' + currentTimeStr + ' or later.'
						);
					}
				} else {
					if ( window.SCD && window.SCD.ValidationError ) {
						SCD.ValidationError.clear( $startTime );
					}
				}
			} else {
				$startTime.removeAttr( 'min' );
				if ( window.SCD && window.SCD.ValidationError ) {
					SCD.ValidationError.clear( $startTime );
				}
			}

			// Constraint 2: If end_date is same as start_date, end_time must be after start_time
			if ( endDateValue && startDateValue && endDateValue === startDateValue && startTimeValue ) {
				var minEndTime = this.addMinutesToTime( startTimeValue, 1 );
				$endTime.attr( 'min', minEndTime );

				var endTimeValue = $endTime.val();
				if ( endTimeValue && endTimeValue <= startTimeValue ) {
					if ( window.SCD && window.SCD.ValidationError ) {
						SCD.ValidationError.show(
							$endTime,
							'End time must be after start time (' + minEndTime + ' or later).'
						);
					}
				} else {
					if ( window.SCD && window.SCD.ValidationError ) {
						SCD.ValidationError.clear( $endTime );
					}
				}
			} else if ( endDateValue && endDateValue === todayStr && !startDateValue ) {
				// Immediate start with today's end date
				$endTime.attr( 'min', currentTimeStr );

				var endTimeValue = $endTime.val();
				if ( endTimeValue && endTimeValue < currentTimeStr ) {
					if ( window.SCD && window.SCD.ValidationError ) {
						SCD.ValidationError.show(
							$endTime,
							'End time cannot be in the past. Please select ' + currentTimeStr + ' or later.'
						);
					}
				} else {
					if ( window.SCD && window.SCD.ValidationError ) {
						SCD.ValidationError.clear( $endTime );
					}
				}
			} else {
				// Different dates - no constraint needed
				$endTime.removeAttr( 'min' );
				if ( window.SCD && window.SCD.ValidationError ) {
					SCD.ValidationError.clear( $endTime );
				}
			}
		},

		/**
		 * Format Date object as YYYY-MM-DD (ISO format)
		 *
		 * @param {Date} date Date object
		 * @return {string} Date in YYYY-MM-DD format
		 */
		formatDateISO: function( date ) {
			var year = date.getFullYear();
			var month = String( date.getMonth() + 1 ).padStart( 2, '0' );
			var day = String( date.getDate() ).padStart( 2, '0' );
			return year + '-' + month + '-' + day;
		},

		/**
		 * Format Date object as HH:MM (24-hour format)
		 *
		 * @param {Date} date Date object
		 * @return {string} Time in HH:MM format
		 */
		formatTimeHHMM: function( date ) {
			var hours = String( date.getHours() ).padStart( 2, '0' );
			var minutes = String( date.getMinutes() ).padStart( 2, '0' );
			return hours + ':' + minutes;
		},

		/**
		 * Add minutes to a time string (HH:MM format)
		 *
		 * @param {string} timeStr Time in HH:MM format
		 * @param {number} minutesToAdd Minutes to add
		 * @return {string} New time in HH:MM format
		 */
		addMinutesToTime: function( timeStr, minutesToAdd ) {
			var parts = timeStr.split( ':' );
			var hours = parseInt( parts[0], 10 );
			var minutes = parseInt( parts[1], 10 );

			minutes += minutesToAdd;

			if ( minutes >= 60 ) {
				hours += Math.floor( minutes / 60 );
				minutes = minutes % 60;
			}

			if ( hours >= 24 ) {
				hours = 23;
				minutes = 59;
			}

			return String( hours ).padStart( 2, '0' ) + ':' + String( minutes ).padStart( 2, '0' );
		},

		/**
		 * Handle start type changes
		 * @param startType
		 */
		handleStartTypeChange: function( startType ) {
			try {
				var $startDateRow = this.$container.find( '.scd-scheduled-start-fields' );

				if ( 'scheduled' === startType ) {
					$startDateRow.show();
				} else {
					$startDateRow.hide();
				}

				if ( this.modules.state && 'function' === typeof this.modules.state.setState ) {
					this.modules.state.setState( { startType: startType } );
				}
				$( document ).trigger( 'scd:schedule:start-type:changed', startType );
			} catch ( error ) {
				this.safeErrorHandle( error, 'schedule-start-type-change' );
		}
	},

		/**
		 * Update recurrence preview
		 */
		updateRecurrencePreview: function() {
			var $preview = $( '#scd-recurrence-preview-text' );

			// Check if state module is available
			if ( !this.modules.state || 'function' !== typeof this.modules.state.getState ) {
				$preview.html( '<em>Configure recurrence settings to see preview</em>' );
				return;
			}

			var state = this.modules.state.getState();

			if ( !state.enableRecurring ) {
				$preview.html( '<em>Configure recurrence settings to see preview</em>' );
				return;
			}

			// Build preview text
			var text = 'Repeats every ' + state.recurrenceInterval + ' ';

			// Add pattern
			if ( 'daily' === state.recurrencePattern ) {
				text += 1 === state.recurrenceInterval ? 'day' : 'days';
			} else if ( 'weekly' === state.recurrencePattern ) {
				text += 1 === state.recurrenceInterval ? 'week' : 'weeks';

				if ( state.recurrenceDays && 0 < state.recurrenceDays.length ) {
					var dayLabels = this.getDayLabels( state.recurrenceDays );
					text += ' on ' + dayLabels.join( ', ' );
				}
			} else if ( 'monthly' === state.recurrencePattern ) {
				text += 1 === state.recurrenceInterval ? 'month' : 'months';
			}

			// Add end condition
			if ( 'after' === state.recurrenceEndType ) {
				text += ', ' + state.recurrenceCount + ' ' + ( 1 === state.recurrenceCount ? 'time' : 'times' );
			} else if ( 'on' === state.recurrenceEndType && state.recurrenceEndDate ) {
				var formattedDate = new Date( state.recurrenceEndDate ).toLocaleDateString();
				text += ', until ' + formattedDate;
			}

			// Generate occurrences
			var occurrences = this.calculateNextOccurrences( this.getPreviewOccurrenceCount() );
			if ( 0 < occurrences.length ) {
				text += '<ul class="scd-occurrence-list">';
				for ( var i = 0; i < occurrences.length; i++ ) {
					text += '<li>' + occurrences[i] + '</li>';
				}
				text += '</ul>';
			}

			$preview.html( text );
		},

		/**
		 * Calculate next occurrences
		 * @param count
		 */
		calculateNextOccurrences: function( count ) {
			var occurrences = [];

			// Check if state module is available
			if ( !this.modules.state || 'function' !== typeof this.modules.state.getState ) {
				return occurrences;
			}

			var state = this.modules.state.getState();

			if ( !state.startDate ) {
				return occurrences;
			}

			var startDate = new Date( state.startDate );
			var interval = state.recurrenceInterval;
			var pattern = state.recurrencePattern;

			for ( var i = 0; i < count; i++ ) {
				var nextDate = new Date( startDate );

				if ( 'daily' === pattern ) {
					nextDate.setDate( nextDate.getDate() + ( interval * ( i + 1 ) ) );
				} else if ( 'weekly' === pattern ) {
					nextDate.setDate( nextDate.getDate() + ( interval * 7 * ( i + 1 ) ) );
				} else if ( 'monthly' === pattern ) {
					nextDate.setMonth( nextDate.getMonth() + ( interval * ( i + 1 ) ) );
				}

				occurrences.push( nextDate.toLocaleDateString() );
			}

			return occurrences;
		},

		/**
		 * Apply preset
		 * @param preset
		 */
		applyPreset: function( preset ) {
			try {
				// Validate preset object
				if ( !preset || !preset.duration || !preset.unit ) {
					if ( window.console && window.console.error ) {
						console.error( '[Schedule] Invalid preset object:', preset );
					}
					this.showError( 'Invalid preset configuration', 'error', 3000 );
					return;
				}

				// Validate preset duration is reasonable (5 minutes to 365 days)
				var maxDurationSeconds = 365 * 24 * 60 * 60; // 1 year
				var minDurationSeconds = 5 * 60; // 5 minutes (for testing purposes)
				var calculatedSeconds = 0;

				if ( 'days' === preset.unit ) {
					calculatedSeconds = preset.duration * 24 * 60 * 60;
				} else if ( 'weeks' === preset.unit ) {
					calculatedSeconds = preset.duration * 7 * 24 * 60 * 60;
				} else if ( 'hours' === preset.unit ) {
					calculatedSeconds = preset.duration * 60 * 60;
				} else {
					if ( window.console && window.console.error ) {
						console.error( '[Schedule] Invalid preset unit:', preset.unit );
					}
					this.showError( 'Invalid preset duration unit', 'error', 3000 );
					return;
				}

				// Validate duration range
				if ( calculatedSeconds < minDurationSeconds || calculatedSeconds > maxDurationSeconds ) {
					if ( window.console && window.console.error ) {
						console.error( '[Schedule] Preset duration out of range:', calculatedSeconds );
					}
					this.showError( 'Preset duration must be between 5 minutes and 365 days', 'error', 3000 );
					return;
				}

				var startDate, endDate, startTime, endTime, durationSeconds;
				durationSeconds = calculatedSeconds;

				// Get current schedule type selection
				var startType = $( 'input[name="start_type"]:checked' ).val();

				// Determine start date based on schedule type and existing values
				if ( 'scheduled' === startType ) {
					// Use existing start date if already set
					var existingStart = $( '#start_date' ).val();
					var existingTime = $( '#start_time' ).val();

					if ( existingStart ) {
						// Keep user's chosen start date
						startDate = new Date( existingStart );
						startTime = existingTime || '00:00';
					} else {
						// No start date set yet, default to tomorrow
						startDate = new Date();
						startDate.setDate( startDate.getDate() + 1 );
						startTime = '00:00';
					}
				} else {
					// "immediate" mode or no selection - use today
					startDate = new Date();
					startTime = '00:00';

					// If no schedule type selected yet, default to "scheduled" for better UX
					if ( !startType ) {
						startDate.setDate( startDate.getDate() + 1 );
						$( 'input[name="start_type"][value="scheduled"]' ).prop( 'checked', true ).trigger( 'change' );
					}
				}

				// Calculate end date based on preset duration
				endDate = new Date( startDate );
				if ( 'days' === preset.unit ) {
					endDate.setDate( endDate.getDate() + preset.duration );
				} else if ( 'weeks' === preset.unit ) {
					endDate.setDate( endDate.getDate() + ( preset.duration * 7 ) );
				} else if ( 'hours' === preset.unit ) {
					endDate.setTime( endDate.getTime() + ( preset.duration * 60 * 60 * 1000 ) );
				}

				// Get end time
				var existingEndTime = $( '#end_time' ).val();
				endTime = existingEndTime || this.getDefaultEndTime();

			// CRITICAL: Format dates in LOCAL timezone, NOT UTC
			// Do NOT use toISOString() as it converts to UTC and shifts the date
			// User picks "Jan 15" → we send "Jan 15" (their timezone)
				var dates = {
					startDate: this.formatDateLocal( startDate ),
					endDate: this.formatDateLocal( endDate ),
					startTime: startTime,
					endTime: endTime,
					durationSeconds: durationSeconds
				};

				// Update fields
				this.updateDateTimeFields( dates );

				// Update state
				if ( this.modules.state && 'function' === typeof this.modules.state.setState ) {
					this.modules.state.setState( dates );
				}

				// Update duration display
				this.updateDurationDisplay();

				// Scroll to schedule configuration section
				var $scheduleSection = $( '.scd-scheduled-start-fields' ).closest( '.scd-card' );
				if ( 0 === $scheduleSection.length ) {
					$scheduleSection = $( '#end_date' ).closest( '.scd-card' );
				}
				if ( $scheduleSection.length ) {
					$( 'html, body' ).animate( {
						scrollTop: $scheduleSection.offset().top - 100
					}, 500 );
				}

				// Emit event
				$( document ).trigger( 'scd:schedule:preset:applied', preset );
			} catch ( error ) {
				this.safeErrorHandle( error, 'schedule-apply-preset' );
				this.showError( 'Failed to apply preset. Please try again.', 'error', 5000 );
		}
	},
		/**
		 * Update date and time fields
		 * @param dates
		 */
		updateDateTimeFields: function( dates ) {
			// Update hidden date fields
			if ( dates.startDate !== undefined ) {
				$( '#start_date' ).val( dates.startDate );
				// Update display field
				$( '#start_date_display' ).val( dates.startDate );
			}
			if ( dates.endDate !== undefined ) {
				$( '#end_date' ).val( dates.endDate );
				// Update display field
				$( '#end_date_display' ).val( dates.endDate );
			}
			if ( dates.startTime !== undefined ) {
				$( '#start_time' ).val( dates.startTime );
			}
			if ( dates.endTime !== undefined ) {
				$( '#end_time' ).val( dates.endTime );
			}
			if ( dates.durationSeconds !== undefined ) {
				// Create or update hidden field for duration
				var $durationField = $( 'input[name="duration_seconds"]' );
				if ( !$durationField.length ) {
					$( '<input>' ).attr( {
						type: 'hidden',
						name: 'duration_seconds',
						id: 'duration_seconds',
						value: dates.durationSeconds
					} ).appendTo( '.scd-wizard-form' );
				} else {
					$durationField.val( dates.durationSeconds );
				}
			}

			// Trigger change events to update any dependent UI
			$( '#start_date, #end_date, #start_time, #end_time' ).trigger( 'change' );
		},

		/**
		 * Custom logic after field population
		 * Called by StepPersistence mixin after standard field population
		 * @param {object} data - Populated data
		 */
		onPopulateFieldsComplete: function( data ) {
			// All form fields are populated by parent method
			// Only handle UI state updates based on field values

			var startType = this.getPropertyValue( data, [ 'startType' ] );
			if ( startType ) {
				this.handleStartTypeChange( startType );
			}

			// Update UI state for recurring options
			var enableRecurring = this.getPropertyValue( data, [ 'enableRecurring' ] );
			if ( enableRecurring !== undefined ) {
				var $recurringOptions = $( '#scd-recurring-options' );
				$recurringOptions.toggle( enableRecurring );
				$recurringOptions.attr( 'aria-hidden', !enableRecurring );
			}

			var recurrencePattern = this.getPropertyValue( data, [ 'recurrencePattern' ] );
			if ( recurrencePattern ) {
				$( '#scd-weekly-options' ).toggle( 'weekly' === recurrencePattern );
			}

			var recurrenceEndType = this.getPropertyValue( data, [ 'recurrenceEndType' ] );
			if ( recurrenceEndType ) {
				$( '#recurrence_count' ).prop( 'disabled', 'after' !== recurrenceEndType );
				$( '#recurrence_end_date' ).prop( 'disabled', 'on' !== recurrenceEndType );
			}

			// Update recurrence preview
			this.updateRecurrencePreview();

			// Update duration display
			this.updateDurationDisplay();

			// Trigger event (Note: mixin already triggers scd:schedule:populated, but keeping for backward compat)
			$( document ).trigger( 'scd:schedule:populated', data );
		},

		// getSavedData method removed - data is provided by wizard orchestrator via populateFields

		/**
		 * Set loading state
		 * @param loading
		 */
	// Note: setLoading() is inherited from BaseOrchestrator


		/**
		 * Setup date pickers
		 */
		setupDatePickers: function() {
			var self = this;

			if ( !$.fn.datepicker ) {
				return;
			}

			var $dateInputs = this.$container.find( '#start_date_display, #end_date_display' );

			$dateInputs.each( function() {
				var $input = $( this );

				if ( $input.hasClass( 'hasDatepicker' ) ) {
					return;
				}

				$input.datepicker( {
					dateFormat: 'yy-mm-dd',
					minDate: 0,
					showAnim: '',
					duration: 0,
					onSelect: function( dateText ) {
						var field = $input.attr( 'id' ).replace( '_date_display', '' );

						// Auto-select "Scheduled Start" when user picks a start date
						if ( 'start' === field ) {
							var $scheduledRadio = $( 'input[name="start_type"][value="scheduled"]' );
							if ( !$scheduledRadio.is( ':checked' ) ) {
								$scheduledRadio.prop( 'checked', true ).trigger( 'change' );
							}
						}

						// Update the actual date field
						$( '#' + field + '_date' ).val( dateText );
						self.handleDateChange( field, dateText );
						// Update duration display
						self.updateDurationDisplay();
					}
				} );
			} );
		},

		/**
		 * Initialize presets
		 */
		initializePresets: function() {
			var self = this;
			var $presetsContainer = this.$container.find( '#scd-preset-recommendations' );

			if ( !$presetsContainer.length || !SCD.Modules.ScheduleConfig ) {
				return;
			}

			$presetsContainer.empty();

			var presets = SCD.Modules.ScheduleConfig.presets.durations;

			$.each( presets, function( key, preset ) {
				var $presetCard = $( '<div>', {
					'class': 'scd-timeline-preset',
					'data-preset-type': key,
					'data-days': preset.days,
					'role': 'button',
					'tabindex': '0'
				} );

				var $button = $( '<button>', {
					'class': 'scd-timeline-preset__button',
					'type': 'button',
					'aria-label': preset.label
				} );

				$button.append(
					$( '<span>', {
						'class': 'scd-timeline-preset__icon dashicons ' + ( preset.icon || 'dashicons-clock' ),
						'aria-hidden': 'true'
					} ),
					$( '<span>', {
						'class': 'scd-timeline-preset__duration',
						'text': preset.days + ' days'
					} ),
					$( '<span>', {
						'class': 'scd-timeline-preset__name',
						'text': preset.label
					} )
				);

				var $tooltip = $( '<span>', {
					'class': 'scd-timeline-preset__tooltip',
					'text': 'Click to set campaign duration to ' + preset.days + ' days'
				} );

				$presetCard.append( $button, $tooltip );

				$presetCard.on( 'click', function() {
					self.applyPreset( {
						id: key,
						duration: preset.days,
						unit: 'days'
					} );

					$presetsContainer.find( '.scd-timeline-preset' ).removeClass( 'scd-timeline-preset--active' );
					$( this ).addClass( 'scd-timeline-preset--active' );
				} );

				$presetsContainer.append( $presetCard );
			} );

			$( document ).trigger( 'scd:schedule:presets:loaded', presets );
		},

		// Note: collectData is now handled by StepPersistence mixin
		// The mixin uses field definitions to automatically collect all fields
		// We override it only if we need to handle special cases

		/**
		 * Collect data - overrides mixin to handle special cases
		 * @override
		 */
		collectData: function() {
			// Call parent method from mixin - this is the single source of truth
			var data = SCD.Mixins.StepPersistence.collectData.call( this );

			// Add timezone from global data
			if ( window.scdWizardData && window.scdWizardData.timezone ) {
				data.timezone = window.scdWizardData.timezone;
			}

			return data;
		},

		// Note: validateData is now handled by StepPersistence mixin
		// The mixin uses field definitions for automatic validation
		// Business logic validation is handled by the PHP unified validator

		// Note: showErrors() is inherited from StepPersistence mixin

		/**
		 * Get default end time
		 */
		getDefaultEndTime: function() {
			return '23:59';
		},

		/**
		 * Update duration display
		 */
		updateDurationDisplay: function() {
			var startDate = $( '#start_date' ).val();
			var endDate = $( '#end_date' ).val();
			var $durationDisplay = $( '#scd-duration-display' );
			var $durationText = $( '#scd-duration-text' );
			var $durationHint = $( '.scd-hint-text' );

			// If no dates set, hide duration display
			if ( !startDate || !endDate ) {
				$durationDisplay.hide();
				return;
			}

			try {
				// Parse dates
				var start = new Date( startDate );
				var end = new Date( endDate );

				// Calculate duration in milliseconds
				var diffMs = end - start;
				if ( diffMs <= 0 ) {
					$durationDisplay.hide();
					return;
				}

				// Calculate days and hours
				var diffDays = Math.floor( diffMs / ( 1000 * 60 * 60 * 24 ) );
				var diffHours = Math.floor( diffMs / ( 1000 * 60 * 60 ) );

				// Format duration text
				var durationText = '';
				if ( diffDays > 0 ) {
					durationText = diffDays + ' day' + ( 1 === diffDays ? '' : 's' );
					if ( diffHours > 0 ) {
						durationText += ' (' + diffHours + ' hour' + ( 1 === diffHours ? '' : 's' ) + ')';
					}
				} else {
					durationText = diffHours + ' hour' + ( 1 === diffHours ? '' : 's' );
				}

				// Update text
				$durationText.text( durationText );

				// Update hint based on duration
				var hintText = '';
				if ( 1 === diffDays ) {
					hintText = 'Perfect for flash sales';
				} else if ( diffDays >= 2 && diffDays <= 3 ) {
					hintText = 'Great for weekend promotions';
				} else if ( diffDays >= 4 && diffDays <= 10 ) {
					hintText = 'Ideal for weekly campaigns';
				} else if ( diffDays >= 11 && diffDays <= 21 ) {
					hintText = 'Good for extended sales';
				} else if ( diffDays >= 22 ) {
					hintText = 'Perfect for seasonal campaigns';
				}

				if ( hintText ) {
					$durationHint.text( hintText );
					$( '#scd-duration-hint' ).show();
				} else {
					$( '#scd-duration-hint' ).hide();
				}

				// Show duration display
				$durationDisplay.show();
			} catch ( error ) {
				// Silently fail - don't disrupt user experience
				$durationDisplay.hide();
			}
		},

		/**
		 * Get preview occurrence count
		 */
		getPreviewOccurrenceCount: function() {
			return 5;
		},

		/**
		 * Format date in local timezone (YYYY-MM-DD)
		 * CRITICAL: Do NOT use toISOString() as it converts to UTC
		 * @param date
		 * @returns {string}
		 */
		formatDateLocal: function( date ) {
			if ( ! ( date instanceof Date ) || isNaN( date.getTime() ) ) {
				return '';
			}

			var year = date.getFullYear();
			var month = date.getMonth() + 1;
			var day = date.getDate();

			var monthStr = 10 > month ? '0' + month : String( month );
			var dayStr = 10 > day ? '0' + day : String( day );

			return year + '-' + monthStr + '-' + dayStr;
		},

		/**
		 * Get day labels from day values
		 * @param dayValues
		 */
		getDayLabels: function( dayValues ) {
			var dayMap = {
				'mon': 'Monday',
				'tue': 'Tuesday',
				'wed': 'Wednesday',
				'thu': 'Thursday',
				'fri': 'Friday',
				'sat': 'Saturday',
				'sun': 'Sunday',
				// Legacy numeric format support
				'0': 'Sunday',
				'1': 'Monday',
				'2': 'Tuesday',
				'3': 'Wednesday',
				'4': 'Thursday',
				'5': 'Friday',
				'6': 'Saturday'
			};

			var labels = [];
			for ( var i = 0; i < dayValues.length; i++ ) {
				if ( dayMap[dayValues[i]] ) {
					labels.push( dayMap[dayValues[i]] );
				}
			}

			return labels;
		},


	/* ===== CENTRALIZED VALIDATION METHODS ===== */

	/**
	 * Validate start date/time against current time
	 * Single source of truth - used by both real-time handlers and validateStep()
	 * @private
	 * @param {string} startDate - Date in ISO format (YYYY-MM-DD)
	 * @param {string} startTime - Time in HH:MM format
	 * @return {object} Validation result {valid: boolean, field: string, message: string}
	 */
	_validateStartTime: function( startDate, startTime ) {
		if ( ! startDate || ! startTime ) {
			return { valid: true };
		}

		var now = new Date();
		var currentDateStr = this.formatDateISO( now );
		var currentTimeStr = this.formatTimeHHMM( now );

		// Check if start date is in the past
		if ( startDate < currentDateStr ) {
			return {
				valid: false,
				field: 'start_date',
				message: 'Campaign start date cannot be in the past'
			};
		}

		// Check if start time is in the past (same day)
		if ( startDate === currentDateStr && startTime < currentTimeStr ) {
			return {
				valid: false,
				field: 'start_time',
				message: 'Campaign start time cannot be in the past'
			};
		}

		return { valid: true };
	},

	/**
	 * Validate end date/time (checks both past validation and start/end ordering)
	 * Single source of truth - used by both real-time handlers and validateStep()
	 * @private
	 * @param {string} endDate - Date in ISO format (YYYY-MM-DD)
	 * @param {string} endTime - Time in HH:MM format
	 * @param {string} startDate - Start date for comparison (optional)
	 * @param {string} startTime - Start time for comparison (optional)
	 * @return {object} Validation result {valid: boolean, field: string, message: string}
	 */
	_validateEndTime: function( endDate, endTime, startDate, startTime ) {
		if ( ! endDate || ! endTime ) {
			return { valid: true };
		}

		var now = new Date();
		var currentDateStr = this.formatDateISO( now );
		var currentTimeStr = this.formatTimeHHMM( now );

		// CONTEXT 1: Check if end date is in the past
		if ( endDate < currentDateStr ) {
			return {
				valid: false,
				field: 'end_date',
				message: 'Campaign end date cannot be in the past'
			};
		}

		// CONTEXT 2: Check if end time is in the past (same day)
		if ( endDate === currentDateStr && endTime < currentTimeStr ) {
			return {
				valid: false,
				field: 'end_time',
				message: 'Campaign end time cannot be in the past'
			};
		}

		// CONTEXT 3: Check if end is before start (if start provided)
		if ( startDate && endDate < startDate ) {
			return {
				valid: false,
				field: 'end_date',
				message: 'Campaign end date must be after start date'
			};
		}

		// CONTEXT 4: Check if end time is before start time (same day)
		if ( startDate && startTime && endDate === startDate && endTime <= startTime ) {
			return {
				valid: false,
				field: 'end_time',
				message: 'Campaign end time must be after start time'
			};
		}

		return { valid: true };
	},

	/**
	 * Validate schedule step before allowing navigation
	 * Called by wizard orchestrator when user tries to proceed
	 * Uses centralized validation methods for consistency
	 * @return {jQuery.Deferred} Promise that resolves to true if valid, false if invalid
	 */
	validateStep: function() {
		var errors = [];
		var state = this.modules.state.getState();

		// Validate start (if scheduled)
		if ( 'scheduled' === state.startType && state.startDate ) {
			var startValidation = this._validateStartTime( state.startDate, state.startTime || '00:00' );
			if ( ! startValidation.valid ) {
				errors.push( startValidation );
			}
		}

		// Validate end
		if ( state.endDate ) {
			var endValidation = this._validateEndTime(
				state.endDate,
				state.endTime || '23:59',
				state.startDate,
				state.startTime
			);
			if ( ! endValidation.valid ) {
				errors.push( endValidation );
			}
		}

		// Show errors
		if ( errors.length > 0 ) {
			var self = this;
			errors.forEach( function( error ) {
				var $field = self.$container.find( '#' + error.field );
				if ( $field.length && window.SCD && window.SCD.ValidationError ) {
					SCD.ValidationError.show( $field, error.message );
				}
			} );

			if ( window.SCD && window.SCD.Shared && window.SCD.Shared.NotificationService ) {
				SCD.Shared.NotificationService.error(
					'Please fix the schedule errors before proceeding',
					5000
				);
			}
		}

		var deferred = $.Deferred();
		deferred.resolve( 0 === errors.length );
		return deferred.promise();
	},

		/**
		 * Custom cleanup
		 */
		onDestroy: function() {
			// Note: Module destruction, event unbinding, and reference clearing
			// are automatically handled by BaseOrchestrator

			// Clean up datepickers (step-specific jQuery UI component)
			if ( this.$container && $.fn.datepicker ) {
				this.$container.find( '.hasDatepicker' ).datepicker( 'destroy' );
			}

			// Unbind document event that was bound directly (not tracked by BaseOrchestrator)
			$( document ).off( 'scd:preset:selected' );
		}
	} );

} )( jQuery );