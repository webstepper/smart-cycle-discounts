/**
 * Schedule Orchestrator
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/steps/schedule/schedule-orchestrator.js
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
	WSSCD.Steps = WSSCD.Steps || {};
	WSSCD.Modules = WSSCD.Modules || {};
	WSSCD.Modules.Schedule = WSSCD.Modules.Schedule || {};

	/**
	 * Schedule Orchestrator
	 * Created using BaseOrchestrator.createStep() factory method
	 * Inherits from BaseOrchestrator with EventManager and StepPersistence mixins
	 */
	WSSCD.Steps.ScheduleOrchestrator = WSSCD.Shared.BaseOrchestrator.createStep( 'schedule', {

		/**
		 * Initialize step modules using Module Registry
		 * Called by BaseOrchestrator's initializeModules
		 */
		initializeStep: function() {
			var moduleConfig = WSSCD.Shared.ModuleRegistry.createStepConfig( 'schedule' );
			this.modules = WSSCD.Shared.ModuleRegistry.initialize( moduleConfig, this );
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

			// Initialize clear button visibility and date box styling based on end_date value
			var endDate = $( '#end_date' ).val();
			var $clearButton = this.$container.find( '.wsscd-clear-end-date' );
			var $endDateBox = this.$container.find( '.wsscd-date-box--end' );
			if ( endDate ) {
				$clearButton.show();
				$endDateBox.removeClass( 'wsscd-date-box--indefinite' );
			} else {
				$clearButton.hide();
				$endDateBox.addClass( 'wsscd-date-box--indefinite' );
			}

			// Initialize end_time field type and state based on end_date
			var $endTime = $( '#end_time' );
			if ( ! endDate ) {
				// No end date - show placeholder
				$endTime.attr( 'type', 'text' )
					.addClass( 'wsscd-time-placeholder' );
			} else {
				// Has end date - ensure it's a time input
				$endTime.attr( 'type', 'time' )
					.removeClass( 'wsscd-time-placeholder' );
			}

			// Initialize recurring schedule state from DOM values
			this.initializeRecurringState();
		},

		/**
		 * Initialize recurring schedule state from DOM values
		 * This syncs the PHP-rendered values into JavaScript state
		 */
		initializeRecurringState: function() {
			if ( ! this.modules.state || 'function' !== typeof this.modules.state.setState ) {
				return;
			}

			var stateUpdate = {
				// Read values from DOM
				startDate: $( '#start_date' ).val() || '',
				endDate: $( '#end_date' ).val() || '',
				startTime: $( '#start_time' ).val() || '00:00',
				endTime: $( '#end_time' ).val() || '23:59',
				startType: $( 'input[name="start_type"]:checked' ).val() || 'immediate',

				// Recurring schedule values
				enableRecurring: '1' === $( '#enable_recurring' ).val(),
				recurrencePattern: $( '#recurrence_pattern' ).val() || 'daily',
				recurrenceInterval: parseInt( $( '#recurrence_interval' ).val(), 10 ) || 1,
				recurrenceEndType: $( '#recurrence_end_type' ).val() || 'never',
				recurrenceCount: parseInt( $( '#recurrence_count' ).val(), 10 ) || 10,
				recurrenceEndDate: $( '#recurrence_end_date' ).val() || '',
				recurrenceMode: $( 'input[name="recurrence_mode"]:checked' ).val() || 'continuous'
			};

			// Get recurrence days (checkboxes)
			var recurrenceDays = [];
			this.$container.find( 'input[name="recurrence_days[]"]:checked' ).each( function() {
				recurrenceDays.push( $( this ).val() );
			} );
			stateUpdate.recurrenceDays = recurrenceDays;

			// Set state
			this.modules.state.setState( stateUpdate );

			// Update preview with initial values
			this.updateRecurrencePreview();

			// Run initial validations if recurring is enabled (for editing existing campaigns)
			if ( stateUpdate.enableRecurring && stateUpdate.endDate ) {
				var self = this;

				// Small delay to ensure DOM is ready
				setTimeout( function() {
					// Update weekly day constraints and validate
					if ( 'weekly' === stateUpdate.recurrencePattern ) {
						self.updateWeeklyDayConstraints();
						var daysValidation = self.validateWeeklyDays( stateUpdate.recurrenceDays );
						self.showWeeklyDaysValidation( daysValidation );
					}

					// Validate interval vs duration
					var intervalValidation = self.validateIntervalVsDuration();
					self.showIntervalValidation( intervalValidation );

					// Validate monthly edge case
					if ( 'monthly' === stateUpdate.recurrencePattern ) {
						var monthlyValidation = self.validateMonthlyEdgeCase();
						self.showMonthlyEdgeCaseInfo( monthlyValidation );
					}

					// Update recurrence end date min date constraint
					self.updateRecurrenceEndDateMinDate();
				}, 100 );
			}
		},

		/**
		 * Custom event binding
		 */
		onBindEvents: function() {
			var self = this;

			// Date field changes
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

				// Handle end date changes - enable/disable time field
				if ( 'end' === field ) {
					$( 'input[name="duration_seconds"]' ).remove();

					var $endTime = $( '#end_time' );
					if ( selectedDate ) {
						// End date set - enable time field with default value
						// Set valid time value FIRST (while still type="text"), then change type
						var currentValue = $endTime.val();
						if ( ! currentValue || '' === currentValue || '--:--' === currentValue ) {
							$endTime.val( '23:59' );
						}
						$endTime.attr( 'type', 'time' )
							.prop( 'disabled', false )
							.removeClass( 'wsscd-time-placeholder' );
					} else {
						// End date cleared - switch to text input showing placeholder
						$endTime.attr( 'type', 'text' )
							.prop( 'disabled', true )
							.val( '--:--' )
							.addClass( 'wsscd-time-placeholder' );
					}

					// Validate recurring requirements when end_date changes
					var enableRecurring = $( '#enable_recurring' ).prop( 'checked' );
					var durationSeconds = $( 'input[name="duration_seconds"]' ).val();
					var recurringValidation = self._validateRecurring( enableRecurring, selectedDate, durationSeconds );

					// Show/hide clear button and update date box styling based on whether date is selected
					var $clearButton = self.$container.find( '.wsscd-clear-end-date' );
					var $endDateBox = self.$container.find( '.wsscd-date-box--end' );
					if ( selectedDate ) {
						$clearButton.show();
						$endDateBox.removeClass( 'wsscd-date-box--indefinite' );
					} else {
						$clearButton.hide();
						$endDateBox.addClass( 'wsscd-date-box--indefinite' );
					}

					// Show error at end of row, but apply red border to date field
					if ( ! recurringValidation.valid ) {
						self._showOrClearValidation( recurringValidation, $( '#end_time' ) );
						// Apply error styling to date field
						$( '#end_date_display' ).addClass( 'error' ).attr( 'aria-invalid', 'true' );
						// Open calendar to help user fix the issue
						$( '#end_date_display' ).datepicker( 'show' );
					} else {
						self._showOrClearValidation( recurringValidation, $( '#end_time' ) );
						// Clear error styling from date field
						$( '#end_date_display' ).removeClass( 'error' ).attr( 'aria-invalid', 'false' );
					}
				}

				// Validate if date is entered
				if ( selectedDate ) {
					var startDate = $( '#start_date' ).val();
					var startTime = $( '#start_time' ).val() || '00:00';
					var endDate = $( '#end_date' ).val();
					var endTime = $( '#end_time' ).val() || '23:59';

					var validation = ( 'start' === field )
						? self._validateStartTime( startDate, startTime )
						: self._validateEndTime( endDate, endTime, startDate, startTime );

					self._showOrClearValidation( validation, $input );
				}

				self.handleDateChange( field, selectedDate );
				self.updateDurationDisplay();
			} );

			// Time field changes - validate on blur (when user finishes typing)
			this.$container.on( 'blur', '#start_time, #end_time', function() {
				var field = $( this ).attr( 'id' ).replace( '_time', '' );
				var $input = $( this );
				var selectedTime = $input.val();

				// Check if field is required based on context
				var startType = $( 'input[name="start_type"]:checked' ).val() || 'immediate';
				var endDate = $( '#end_date' ).val();

				// Determine if field is required
				var isRequired = false;
				if ( 'start' === field && 'scheduled' === startType ) {
					isRequired = true;
				} else if ( 'end' === field && endDate ) {
					isRequired = true;
				}

				// If empty and required, show error and persist it
				if ( ! selectedTime && isRequired ) {
					if ( window.WSSCD && window.WSSCD.ValidationError ) {
						var requiredMessage = ( 'start' === field )
							? 'Start time is required for scheduled campaigns'
							: 'End time is required when end date is set';
						WSSCD.ValidationError.show( $input, requiredMessage );
					}
					return;
				}

				// If empty and not required, just clear any error
				if ( ! selectedTime ) {
					if ( window.WSSCD && window.WSSCD.ValidationError ) {
						WSSCD.ValidationError.clear( $input );
					}
					return;
				}

				// Remove duration field when user manually changes end time
				if ( 'end' === field ) {
					$( 'input[name="duration_seconds"]' ).remove();
				}

				// Validate with current DOM values
				var startDate = $( '#start_date' ).val();
				var startTime = $( '#start_time' ).val();
				var endDateVal = $( '#end_date' ).val();
				var endTime = $( '#end_time' ).val();

				var validation = ( 'start' === field )
					? self._validateStartTime( startDate, startTime )
					: self._validateEndTime( endDateVal, endTime, startDate, startTime );

				self._showOrClearValidation( validation, $input );
			} );

			// Time field changes - update state on change
			this.$container.on( 'change', '#start_time, #end_time', function() {
				var field = $( this ).attr( 'id' ).replace( '_time', '' );
				var selectedTime = $( this ).val();
				self.handleTimeChange( field, selectedTime );
				self.updateDurationDisplay();
			} );

			// Start type changes - toggle buttons
			this.$container.on( 'change', 'input[name="start_type"]', function() {
				var value = $( this ).val();

				// Update toggle button states
				var $toggleBtns = self.$container.find( '.wsscd-toggle-btn' );
				$toggleBtns.removeClass( 'wsscd-toggle-btn--active' );
				$( this ).closest( '.wsscd-toggle-btn' ).addClass( 'wsscd-toggle-btn--active' );

				self.handleStartTypeChange( value );
			} );

			this.$container.on( 'click', '.wsscd-clear-end-date', function( e ) {
				e.preventDefault();
				$( '#end_date' ).val( '' );
				$( '#end_date_display' ).val( '' );
				var $endTime = $( '#end_time' );
				$endTime.attr( 'type', 'text' )
					.prop( 'disabled', true )
					.val( '--:--' )
					.addClass( 'wsscd-time-placeholder' );

				// Update end date box styling
				self.$container.find( '.wsscd-date-box--end' ).addClass( 'wsscd-date-box--indefinite' );
				$( this ).hide();

				self.updateDurationDisplay();
				$( '#end_date' ).trigger( 'change' );
			} );

			// Calendar buttons in date boxes (Campaign Period section)
			this.$container.on( 'click', '.wsscd-date-box__calendar-btn', function( e ) {
				e.preventDefault();
				var targetId = $( this ).data( 'target' );
				if ( targetId ) {
					$( '#' + targetId ).datepicker( 'show' );
				}
			} );

			// Calendar icon buttons (Recurring Schedule section)
			this.$container.on( 'click', '.wsscd-calendar-icon', function( e ) {
				e.preventDefault();
				var targetId = $( this ).data( 'target' );
				if ( targetId ) {
					$( '#' + targetId ).datepicker( 'show' );
				}
			} );

			// Preset selection
			$( document ).on( 'wsscd:preset:selected', function( e, preset ) {
				self.applyPreset( preset );
			} );

			// Schedule type cards (One-time vs Recurring) - NEW REDESIGNED STRUCTURE
			this.$container.on( 'change', 'input[name="schedule_type"]', function() {
				var scheduleType = $( this ).val();
				var isRecurring = 'recurring' === scheduleType;
				var $cards = self.$container.find( '.wsscd-schedule-type-card' );
				var $recurringConfig = $( '#wsscd-recurring-options' );

				// Update card visual selection
				$cards.removeClass( 'selected' );
				$( this ).closest( '.wsscd-schedule-type-card' ).addClass( 'selected' );

				// Sync hidden field for backend compatibility
				$( '#enable_recurring' ).val( isRecurring ? '1' : '0' );

				// Toggle recurring configuration section
				$recurringConfig.toggle( isRecurring );
				$recurringConfig.attr( 'aria-hidden', !isRecurring );

				if ( self.modules.state && 'function' === typeof self.modules.state.setState ) {
					self.modules.state.setState( { enableRecurring: isRecurring } );
				}
				self.updateRecurrencePreview();

				// Validate recurring requirements when switching to recurring
				if ( isRecurring ) {
					var endDate = $( '#end_date' ).val();
					var durationSeconds = $( 'input[name="duration_seconds"]' ).val();
					var validation = self._validateRecurring( isRecurring, endDate, durationSeconds );

					if ( ! validation.valid ) {
						self._showOrClearValidation( validation, $( '#end_time' ) );
						$( '#end_date_display' ).addClass( 'error' ).attr( 'aria-invalid', 'true' );
						$( '#end_date_display' ).datepicker( 'show' );
					} else {
						self._showOrClearValidation( validation, $( '#end_time' ) );
						$( '#end_date_display' ).removeClass( 'error' ).attr( 'aria-invalid', 'false' );
					}
				} else {
					// Clear validation errors when switching to one-time
					if ( window.WSSCD && window.WSSCD.ValidationError ) {
						WSSCD.ValidationError.clear( $( '#end_time' ) );
					}
					$( '#end_date_display' ).removeClass( 'error' ).attr( 'aria-invalid', 'false' );
				}
			} );

			// Recurrence pattern
			this.$container.on( 'change', '#recurrence_pattern', function() {
				var pattern = $( this ).val();
				$( '#wsscd-weekly-options' ).toggle( 'weekly' === pattern );
				if ( self.modules.state && 'function' === typeof self.modules.state.setState ) {
					self.modules.state.setState( { recurrencePattern: pattern } );
				}

				// Handle weekly pattern
				if ( 'weekly' === pattern ) {
					// Update day constraints based on campaign duration
					self.updateWeeklyDayConstraints();

					var days = self.$container.find( 'input[name="recurrence_days[]"]:checked' ).map( function() {
						return $( this ).val();
					} ).get();
					var daysValidation = self.validateWeeklyDays( days );
					self.showWeeklyDaysValidation( daysValidation );
				} else {
					// Clear weekly-related UI when not weekly
					self.$container.find( '.wsscd-days-warning, .wsscd-days-suggestion, .wsscd-days-info' ).remove();
					self.$container.find( '.wsscd-recurring-days' ).removeClass( 'has-warning has-suggestion has-info has-error' );
					// Re-enable all day chips
					self.$container.find( '.wsscd-day-chip' ).removeClass( 'disabled' );
					self.$container.find( '.wsscd-day-chip input[type="checkbox"]' ).prop( 'disabled', false );
				}

				// Validate interval vs duration (changes based on pattern)
				var intervalValidation = self.validateIntervalVsDuration();
				self.showIntervalValidation( intervalValidation );

				// Show monthly edge case info if applicable
				if ( 'monthly' === pattern ) {
					var monthlyValidation = self.validateMonthlyEdgeCase();
					self.showMonthlyEdgeCaseInfo( monthlyValidation );
				} else {
					// Clear monthly info when not monthly
					self.$container.find( '.wsscd-monthly-info' ).remove();
				}

				// Update recurrence end date min constraint (affected by pattern)
				self.updateRecurrenceEndDateMinDate();

				self.updateRecurrencePreview();
			} );

			// Recurrence interval - with validation against campaign duration
			this.$container.on( 'change', '#recurrence_interval', function() {
				var interval = parseInt( $( this ).val() ) || 1;
				if ( self.modules.state && 'function' === typeof self.modules.state.setState ) {
					self.modules.state.setState( { recurrenceInterval: interval } );
				}

				// Validate interval against campaign duration
				var validation = self.validateIntervalVsDuration();
				self.showIntervalValidation( validation );

				// Update recurrence end date min constraint (affected by interval)
				self.updateRecurrenceEndDateMinDate();

				self.updateRecurrencePreview();
			} );

			// Recurrence days - with visual selection update and constraint-based disabling
			this.$container.on( 'change', 'input[name="recurrence_days[]"]', function() {
				var $checkbox = $( this );
				var $chip = $checkbox.closest( '.wsscd-day-chip' );

				// Toggle selected class based on checkbox state
				if ( $chip.length ) {
					if ( $checkbox.is( ':checked' ) ) {
						$chip.addClass( 'selected' );
					} else {
						$chip.removeClass( 'selected' );
					}
				}

				var days = self.$container.find( 'input[name="recurrence_days[]"]:checked' ).map( function() {
					return $( this ).val();
				} ).get();

				// Update constraints (disable days that would cause overlap)
				self.updateWeeklyDayConstraints();

				// Simple validation (no days, all days)
				var validation = self.validateWeeklyDays( days );
				self.showWeeklyDaysValidation( validation );

				if ( self.modules.state && 'function' === typeof self.modules.state.setState ) {
					self.modules.state.setState( { recurrenceDays: days } );
				}
				self.updateRecurrencePreview();
			} );

			// Recurrence end type - NEW dropdown structure
			this.$container.on( 'change', '#recurrence_end_type_select', function() {
				var endType = $( this ).val();
				var $afterSection = $( '.wsscd-recurring-until__after' );
				var $onSection = $( '.wsscd-recurring-until__on' );

				// Sync hidden field for backend compatibility
				$( '#recurrence_end_type' ).val( endType );

				// Show/hide conditional sections
				$afterSection.toggle( 'after' === endType );
				$onSection.toggle( 'on' === endType );

				// Enable/disable fields based on selection
				$( '#recurrence_count' ).prop( 'disabled', 'after' !== endType );
				$( '#recurrence_end_date' ).prop( 'disabled', 'on' !== endType );

				if ( self.modules.state && 'function' === typeof self.modules.state.setState ) {
					self.modules.state.setState( { recurrenceEndType: endType } );
				}

				// Update min date constraint when switching to "on" type
				if ( 'on' === endType ) {
					self.updateRecurrenceEndDateMinDate();
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

			// Recurrence mode selection
			this.$container.on( 'change', 'input[name="recurrence_mode"]', function() {
				var mode = $( this ).val();
				var $recurringWarning = $( '#wsscd-recurring-warning' );
				var $modeOptions = self.$container.find( '.wsscd-mode-option' );

				// Update mode option selection visual
				$modeOptions.removeClass( 'selected' );
				$( this ).closest( '.wsscd-mode-option' ).addClass( 'selected' );

				// Toggle warning visibility (only show for instances mode)
				$recurringWarning.toggle( 'instances' === mode );

				if ( self.modules.state && 'function' === typeof self.modules.state.setState ) {
					self.modules.state.setState( { recurrenceMode: mode } );
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
				$( document ).trigger( 'wsscd:schedule:' + field + ':changed', value );

				// Re-validate when dates change (duration affects all validations)
				var pattern = $( '#recurrence_pattern' ).val();

				// Update weekly day constraints (duration change affects which days can be selected)
				if ( 'weekly' === pattern ) {
					this.updateWeeklyDayConstraints();

					var days = this.$container.find( 'input[name="recurrence_days[]"]:checked' ).map( function() {
						return $( this ).val();
					} ).get();
					var daysValidation = this.validateWeeklyDays( days );
					this.showWeeklyDaysValidation( daysValidation );
				}

				// Validate interval vs duration
				var intervalValidation = this.validateIntervalVsDuration();
				this.showIntervalValidation( intervalValidation );

				// Validate monthly edge case
				if ( 'monthly' === pattern ) {
					var monthlyValidation = this.validateMonthlyEdgeCase();
					this.showMonthlyEdgeCaseInfo( monthlyValidation );
				}

				// Update recurrence end date min constraint (affected by campaign dates)
				this.updateRecurrenceEndDateMinDate();

				// Update recurrence preview when dates change (affects calculations)
				this.updateRecurrencePreview();
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
				$( document ).trigger( 'wsscd:schedule:' + field + '-time:changed', value );
			} catch ( error ) {
				this.safeErrorHandle( error, 'schedule-time-change' );
				this.showError( 'Failed to update time. Please check your input.', 'error', 5000 );
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
				var $startDateRow = this.$container.find( '.wsscd-scheduled-start-fields' );
				var $immediateDisplay = this.$container.find( '.wsscd-date-box__immediate' );
				var $startDateBox = this.$container.find( '.wsscd-date-box--start' );

				if ( 'scheduled' === startType ) {
					$startDateRow.show();
					$immediateDisplay.hide();
					$startDateBox.removeClass( 'wsscd-date-box--immediate' );
					// Re-enable fields and restore tabindex when showing
					$startDateRow.find( 'input, button' ).removeAttr( 'tabindex' );
					$startDateRow.find( '#start_time' ).prop( 'disabled', false );
				} else {
					$startDateRow.hide();
					$immediateDisplay.show();
					$startDateBox.addClass( 'wsscd-date-box--immediate' );
					// Set tabindex to -1 for hidden fields to remove from tab order
					$startDateRow.find( 'input, button' ).attr( 'tabindex', '-1' );
				}

				if ( this.modules.state && 'function' === typeof this.modules.state.setState ) {
					this.modules.state.setState( { startType: startType } );
				}
				$( document ).trigger( 'wsscd:schedule:start-type:changed', startType );
			} catch ( error ) {
				this.safeErrorHandle( error, 'schedule-start-type-change' );
			}
		},

		/**
		 * Update recurrence preview
		 * Uses timeline visualization for visual preview
		 */
		updateRecurrencePreview: function() {
			var $summaryText = $( '#wsscd-schedule-summary-text .wsscd-schedule-summary__description' );

			if ( !this.modules.state || 'function' !== typeof this.modules.state.getState ) {
				if ( $summaryText.length ) {
					$summaryText.text( 'Configure your recurring schedule above' );
				}
				this.updateTimelineVisualization( [] );
				return;
			}

			var state = this.modules.state.getState();

			if ( !state.enableRecurring ) {
				if ( $summaryText.length ) {
					$summaryText.text( 'Configure your recurring schedule above' );
				}
				this.updateTimelineVisualization( [] );
				return;
			}

			// Parse values ensuring correct types
			var interval = parseInt( state.recurrenceInterval, 10 ) || 1;
			var count = parseInt( state.recurrenceCount, 10 ) || 10;
			var pattern = state.recurrencePattern || 'daily';
			var endType = state.recurrenceEndType || 'never';

			// Check if we have required dates for calculation
			if ( ! state.startDate || ! state.endDate ) {
				if ( $summaryText.length ) {
					$summaryText.text( 'Set campaign dates to see recurrence schedule' );
				}
				this.updateTimelineVisualization( [] );
				return;
			}

			// Calculate campaign duration for context
			var startDate = new Date( state.startDate );
			var endDate = new Date( state.endDate );
			var durationMs = endDate.getTime() - startDate.getTime();
			var durationDays = Math.ceil( durationMs / ( 1000 * 60 * 60 * 24 ) );

			// Build natural language summary
			var summaryParts = [];

			// Interval description
			var intervalText = 'Every ' + interval + ' ';
			if ( 'daily' === pattern ) {
				intervalText += ( 1 === interval ? 'day' : 'days' );
			} else if ( 'weekly' === pattern ) {
				intervalText += ( 1 === interval ? 'week' : 'weeks' );
				if ( state.recurrenceDays && 0 < state.recurrenceDays.length ) {
					var dayLabels = this.getDayLabels( state.recurrenceDays );
					intervalText += ' on ' + dayLabels.join( ', ' );
				}
			} else if ( 'monthly' === pattern ) {
				intervalText += ( 1 === interval ? 'month' : 'months' );
			}
			summaryParts.push( intervalText );

			// Add context about what this means
			summaryParts.push( 'after each ' + durationDays + '-day campaign ends' );

			// End type
			if ( 'after' === endType ) {
				summaryParts.push( '(' + count + ' ' + ( 1 === count ? 'repeat' : 'repeats' ) + ')' );
			} else if ( 'on' === endType && state.recurrenceEndDate ) {
				var formattedDate = new Date( state.recurrenceEndDate ).toLocaleDateString();
				summaryParts.push( '(until ' + formattedDate + ')' );
			} else if ( 'never' === endType ) {
				summaryParts.push( '(ongoing)' );
			}

			var summaryTextContent = summaryParts.join( ' ' );

			// Update summary text
			if ( $summaryText.length ) {
				$summaryText.text( summaryTextContent );
			}

			// Generate and display timeline
			var occurrences = this.calculateNextOccurrences( this.getPreviewOccurrenceCount() );
			this.updateTimelineVisualization( occurrences );
		},

		/**
		 * Update timeline visualization
		 * Shows original campaign + recurrence instances
		 * @param occurrences Array of date strings (recurrence start dates)
		 */
		updateTimelineVisualization: function( occurrences ) {
			var $timeline = $( '#wsscd-schedule-timeline .wsscd-schedule-timeline__track' );
			if ( ! $timeline.length ) {
				return;
			}

			var state = this.modules.state ? this.modules.state.getState() : {};

			// Check if we have required dates
			if ( ! state.startDate ) {
				$timeline.html(
					'<div class="wsscd-schedule-timeline__placeholder">' +
						'<span class="wsscd-icon wsscd-icon--info-outline"></span>' +
						'<span>Set a start date to see the timeline</span>' +
					'</div>'
				);
				return;
			}

			if ( ! state.endDate ) {
				$timeline.html(
					'<div class="wsscd-schedule-timeline__placeholder">' +
						'<span class="wsscd-icon wsscd-icon--info-outline"></span>' +
						'<span>Set an end date to calculate recurrences</span>' +
					'</div>'
				);
				return;
			}

			if ( ! occurrences || 0 === occurrences.length ) {
				$timeline.html(
					'<div class="wsscd-schedule-timeline__placeholder">' +
						'<span class="wsscd-icon wsscd-icon--update"></span>' +
						'<span>Configure recurrence settings above</span>' +
					'</div>'
				);
				return;
			}

			// Build timeline with original campaign + recurrences
			var markersHtml = '<div class="wsscd-schedule-timeline__markers">';

			// Add original campaign as first marker (highlighted differently)
			var startDate = new Date( state.startDate );
			var endDate = new Date( state.endDate );
			var originalDateRange = this.formatDateRange( startDate, endDate );
			var originalTooltip = this.formatTooltipDate( startDate, endDate );

			markersHtml += '<div class="wsscd-schedule-timeline__marker wsscd-schedule-timeline__marker--original" data-tooltip="' + originalTooltip + '">';
			markersHtml += '<div class="wsscd-timeline-pulse"></div>';
			markersHtml += '<span class="wsscd-schedule-timeline__marker-day">' + originalDateRange.days + '</span>';
			markersHtml += '<span class="wsscd-schedule-timeline__marker-month">' + originalDateRange.months + '</span>';
			markersHtml += '<span class="wsscd-schedule-timeline__marker-label">Original</span>';
			markersHtml += '</div>';

			// Add recurrence markers (dynamic count based on end type)
			var maxMarkers = Math.min( occurrences.length, this.getMaxDisplayMarkers() );
			var hasMore = occurrences.length > maxMarkers;

			for ( var i = 0; i < maxMarkers; i++ ) {
				var occurrence = occurrences[i];
				var dateRange = this.formatDateRange( occurrence.start, occurrence.end );
				var tooltip = this.formatTooltipDate( occurrence.start, occurrence.end );

				// Add connector before each recurrence marker
				var connectorClass = 'wsscd-schedule-timeline__connector';
				if ( 0 === i ) {
					connectorClass += ' wsscd-schedule-timeline__connector--first';
				}
				markersHtml += '<div class="' + connectorClass + '"></div>';

				// Add recurrence marker
				markersHtml += '<div class="wsscd-schedule-timeline__marker wsscd-schedule-timeline__marker--recurrence" data-tooltip="' + tooltip + '">';
				markersHtml += '<span class="wsscd-schedule-timeline__marker-day">' + dateRange.days + '</span>';
				markersHtml += '<span class="wsscd-schedule-timeline__marker-month">' + dateRange.months + '</span>';
				markersHtml += '</div>';
			}

			// Show "more" indicator based on end type
			var endType = state.recurrenceEndType || 'never';

			if ( 'never' === endType ) {
				// For "forever" - show infinity indicator
				markersHtml += '<div class="wsscd-schedule-timeline__connector"></div>';
				markersHtml += '<div class="wsscd-schedule-timeline__marker wsscd-schedule-timeline__marker--more wsscd-schedule-timeline__marker--infinite" data-tooltip="Continues indefinitely">';
				markersHtml += '<span class="wsscd-schedule-timeline__marker-day">∞</span>';
				markersHtml += '<span class="wsscd-schedule-timeline__marker-month">forever</span>';
				markersHtml += '</div>';
			} else if ( hasMore ) {
				// For finite recurrence with more items
				var moreCount = occurrences.length - maxMarkers;
				var moreTooltip = moreCount + ' more occurrence' + ( 1 !== moreCount ? 's' : '' );

				markersHtml += '<div class="wsscd-schedule-timeline__connector"></div>';
				markersHtml += '<div class="wsscd-schedule-timeline__marker wsscd-schedule-timeline__marker--more" data-tooltip="' + moreTooltip + '">';
				markersHtml += '<span class="wsscd-schedule-timeline__marker-day">+' + moreCount + '</span>';
				markersHtml += '<span class="wsscd-schedule-timeline__marker-month">more</span>';
				markersHtml += '</div>';
			}

			markersHtml += '</div>';
			$timeline.html( markersHtml );
		},

		/**
		 * Calculate next occurrences (campaign instance start dates)
		 *
		 * Recurrence means the campaign REPEATS after it ends.
		 * Each occurrence = previous instance END + interval.
		 * Each instance runs for the same duration as the original campaign.
		 *
		 * For weekly pattern with specific days:
		 * - Find the next selected weekday after previous instance ends + interval
		 *
		 * Example: 3-day campaign (Feb 1-3), repeat every 1 day, 3 times:
		 * - Instance 1: Feb 1-3 (original)
		 * - Instance 2: Feb 4-6 (Feb 3 + 1 day = Feb 4 start)
		 * - Instance 3: Feb 7-9 (Feb 6 + 1 day = Feb 7 start)
		 *
		 * @param maxCount Maximum number of occurrences to return for preview
		 */
		calculateNextOccurrences: function( maxCount ) {
			var self = this;
			var occurrences = [];

			if ( !this.modules.state || 'function' !== typeof this.modules.state.getState ) {
				return occurrences;
			}

			var state = this.modules.state.getState();

			// Need both start and end date to calculate recurrences
			if ( !state.startDate || !state.endDate ) {
				return occurrences;
			}

			var campaignStart = new Date( state.startDate );
			var campaignEnd = new Date( state.endDate );

			// Calculate campaign duration in days
			var durationMs = campaignEnd.getTime() - campaignStart.getTime();
			var durationDays = Math.ceil( durationMs / ( 1000 * 60 * 60 * 24 ) );

			var interval = parseInt( state.recurrenceInterval, 10 ) || 1;
			var pattern = state.recurrencePattern || 'daily';
			var endType = state.recurrenceEndType || 'never';
			var recurrenceCount = parseInt( state.recurrenceCount, 10 ) || 10;

			// Determine maximum occurrences based on end type
			var maxOccurrences = maxCount;
			if ( 'after' === endType ) {
				maxOccurrences = Math.min( recurrenceCount, maxCount );
			}

			// Get recurrence end date if specified
			var recurrenceEndDate = null;
			if ( 'on' === endType && state.recurrenceEndDate ) {
				recurrenceEndDate = new Date( state.recurrenceEndDate );
				recurrenceEndDate.setHours( 23, 59, 59, 999 );
			}

			// Get selected weekdays for weekly pattern (convert to JS day numbers: 0=Sun, 1=Mon, etc.)
			var selectedWeekdays = [];
			if ( 'weekly' === pattern && state.recurrenceDays && state.recurrenceDays.length > 0 ) {
				var dayMap = { 'sun': 0, 'mon': 1, 'tue': 2, 'wed': 3, 'thu': 4, 'fri': 5, 'sat': 6 };
				for ( var d = 0; d < state.recurrenceDays.length; d++ ) {
					var dayVal = state.recurrenceDays[d];
					if ( dayMap.hasOwnProperty( dayVal ) ) {
						selectedWeekdays.push( dayMap[dayVal] );
					}
				}
				selectedWeekdays.sort( function( a, b ) { return a - b; } );
			}

			// Track the end of the previous instance (starts with original campaign end)
			var previousEndDate = new Date( campaignEnd );

			// Calculate occurrences
			for ( var i = 0; i < maxOccurrences; i++ ) {
				var nextStartDate = new Date( previousEndDate );

				// Add interval after previous instance ends
				if ( 'daily' === pattern ) {
					nextStartDate.setDate( nextStartDate.getDate() + interval );
				} else if ( 'weekly' === pattern ) {
					// First, move forward by the interval in weeks
					nextStartDate.setDate( nextStartDate.getDate() + ( interval * 7 ) );

					// If specific days are selected, find the next occurrence on one of those days
					if ( selectedWeekdays.length > 0 ) {
						nextStartDate = self.findNextWeekday( nextStartDate, selectedWeekdays, durationDays );
					}
				} else if ( 'monthly' === pattern ) {
					nextStartDate.setMonth( nextStartDate.getMonth() + interval );
				}

				// Check recurrence end date limit
				if ( recurrenceEndDate && nextStartDate > recurrenceEndDate ) {
					break;
				}

				// Calculate when this instance ends
				var nextEndDate = new Date( nextStartDate );
				nextEndDate.setDate( nextEndDate.getDate() + durationDays );

				// Return object with both start and end dates
				occurrences.push( {
					start: new Date( nextStartDate ),
					end: new Date( nextEndDate )
				} );

				// Track end for next iteration
				previousEndDate = nextEndDate;
			}

			return occurrences;
		},

		/**
		 * Find the next occurrence date that falls on one of the selected weekdays
		 * Ensures the campaign duration doesn't overlap with the next selected day
		 *
		 * @param {Date} fromDate - Start searching from this date
		 * @param {array} selectedWeekdays - Array of JS day numbers (0=Sun, 6=Sat)
		 * @param {number} durationDays - Campaign duration in days
		 * @return {Date} The next valid start date
		 */
		findNextWeekday: function( fromDate, selectedWeekdays, durationDays ) {
			var currentDay = fromDate.getDay();
			var result = new Date( fromDate );

			// Find next selected weekday on or after fromDate
			var daysToAdd = 0;
			var found = false;

			for ( var offset = 0; offset < 7; offset++ ) {
				var checkDay = ( currentDay + offset ) % 7;
				if ( selectedWeekdays.indexOf( checkDay ) !== -1 ) {
					daysToAdd = offset;
					found = true;
					break;
				}
			}

			if ( found ) {
				result.setDate( result.getDate() + daysToAdd );
			}

			return result;
		},

		/**
		 * Apply preset
		 * @param preset
		 */
		applyPreset: function( preset ) {
			try {
				if ( !preset || !preset.duration || !preset.unit ) {
					this.showError( 'Invalid preset configuration', 'error', 3000 );
					return;
				}

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
					this.showError( 'Invalid preset duration unit', 'error', 3000 );
					return;
				}

				if ( calculatedSeconds < minDurationSeconds || calculatedSeconds > maxDurationSeconds ) {
					this.showError( 'Preset duration must be between 5 minutes and 365 days', 'error', 3000 );
					return;
				}

				var startDate, endDate, startTime, endTime, durationSeconds;
				durationSeconds = calculatedSeconds;

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

				this.updateDateTimeFields( dates );

				if ( this.modules.state && 'function' === typeof this.modules.state.setState ) {
					this.modules.state.setState( dates );
				}

				this.updateDurationDisplay();

				// Emit event
				$( document ).trigger( 'wsscd:schedule:preset:applied', preset );
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
			if ( dates.startDate !== undefined ) {
				$( '#start_date' ).val( dates.startDate );
				$( '#start_date_display' ).val( dates.startDate );
			}
			if ( dates.endDate !== undefined ) {
				$( '#end_date' ).val( dates.endDate );
				$( '#end_date_display' ).val( dates.endDate );
			}
			if ( dates.startTime !== undefined ) {
				$( '#start_time' ).val( dates.startTime );
			}
			if ( dates.endTime !== undefined ) {
				$( '#end_time' ).val( dates.endTime );
			}
			if ( dates.durationSeconds !== undefined ) {
				var $durationField = $( 'input[name="duration_seconds"]' );
				if ( !$durationField.length ) {
					$( '<input>' ).attr( {
						type: 'hidden',
						name: 'duration_seconds',
						id: 'duration_seconds',
						value: dates.durationSeconds
					} ).appendTo( '.wsscd-wizard-form' );
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
			var recurrenceMode = this.getPropertyValue( data, [ 'recurrenceMode' ] ) || 'continuous';

			if ( enableRecurring !== undefined ) {
				var $recurringOptions = $( '#wsscd-recurring-options' );
				var $recurrenceModeOptions = $( '#wsscd-recurrence-mode-options' );
				var $recurringWarning = $( '#wsscd-recurring-warning' );
				var $scheduleTypeCards = this.$container.find( '.wsscd-schedule-type-card' );

				// NEW: Update schedule type cards visual state
				$scheduleTypeCards.removeClass( 'selected' );
				var scheduleTypeValue = enableRecurring ? 'recurring' : 'one-time';
				$( 'input[name="schedule_type"][value="' + scheduleTypeValue + '"]' )
					.prop( 'checked', true )
					.closest( '.wsscd-schedule-type-card' )
					.addClass( 'selected' );

				$recurringOptions.toggle( enableRecurring );
				$recurringOptions.attr( 'aria-hidden', !enableRecurring );
				if ( $recurrenceModeOptions.length ) {
					$recurrenceModeOptions.toggle( enableRecurring );
					$recurrenceModeOptions.attr( 'aria-hidden', !enableRecurring );
				}

				// Only show warning for instances mode
				if ( $recurringWarning.length ) {
					$recurringWarning.toggle( enableRecurring && 'instances' === recurrenceMode );
				}
			}

			// Update recurrence mode selection
			if ( recurrenceMode ) {
				var $modeOptions = this.$container.find( '.wsscd-mode-option' );
				$modeOptions.removeClass( 'selected' );

				$( 'input[name="recurrence_mode"][value="' + recurrenceMode + '"]' )
					.prop( 'checked', true )
					.closest( '.wsscd-mode-option' )
					.addClass( 'selected' );
			}

			var recurrencePattern = this.getPropertyValue( data, [ 'recurrencePattern' ] );
			if ( recurrencePattern ) {
				$( '#wsscd-weekly-options' ).toggle( 'weekly' === recurrencePattern );
			}

			var recurrenceEndType = this.getPropertyValue( data, [ 'recurrenceEndType' ] );
			if ( recurrenceEndType ) {
				// NEW: Update until dropdown and conditional sections
				$( '#recurrence_end_type_select' ).val( recurrenceEndType );
				$( '.wsscd-recurring-until__after' ).toggle( 'after' === recurrenceEndType );
				$( '.wsscd-recurring-until__on' ).toggle( 'on' === recurrenceEndType );

				$( '#recurrence_count' ).prop( 'disabled', 'after' !== recurrenceEndType );
				$( '#recurrence_end_date' ).prop( 'disabled', 'on' !== recurrenceEndType );
			}

			// Update day chips visual state
			var recurrenceDays = this.getPropertyValue( data, [ 'recurrenceDays' ] );
			if ( recurrenceDays && Array.isArray( recurrenceDays ) ) {
				var $dayChips = this.$container.find( '.wsscd-day-chip' );
				$dayChips.each( function() {
					var $chip = $( this );
					var $checkbox = $chip.find( 'input[type="checkbox"]' );
					var dayValue = $checkbox.val();

					if ( recurrenceDays.indexOf( dayValue ) !== -1 ) {
						$checkbox.prop( 'checked', true );
						$chip.addClass( 'selected' );
					} else {
						$checkbox.prop( 'checked', false );
						$chip.removeClass( 'selected' );
					}
				} );
			}

			this.updateRecurrencePreview();

			this.updateDurationDisplay();
		},

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

			var $dateInputs = this.$container.find( '#start_date_display, #end_date_display, #recurrence_end_date' );

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
						var inputId = $input.attr( 'id' );

						// Handle recurrence_end_date (no _display suffix)
						if ( 'recurrence_end_date' === inputId ) {
							$input.val( dateText ).trigger( 'change' );
							if ( self.modules.state && 'function' === typeof self.modules.state.setState ) {
								self.modules.state.setState( { recurrence_end_date: dateText } );
							}
							return;
						}

						// Handle regular date fields (start_date_display, end_date_display)
						var field = inputId.replace( '_date_display', '' );

						// Auto-select "Scheduled Start" when user picks a start date
						if ( 'start' === field ) {
							var $scheduledRadio = $( 'input[name="start_type"][value="scheduled"]' );
							if ( !$scheduledRadio.is( ':checked' ) ) {
								$scheduledRadio.prop( 'checked', true ).trigger( 'change' );
							}
						}

						// Update hidden field and trigger change event for dependent logic
						$( '#' + field + '_date' ).val( dateText ).trigger( 'change' );
						self.handleDateChange( field, dateText );
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
			var $presetsContainer = this.$container.find( '#wsscd-preset-recommendations' );

			if ( !$presetsContainer.length || !WSSCD.Modules.ScheduleConfig ) {
				return;
			}

			$presetsContainer.empty();

			var presets = WSSCD.Modules.ScheduleConfig.presets.durations;

			$.each( presets, function( key, preset ) {
				var $presetCard = $( '<div>', {
					'class': 'wsscd-timeline-preset',
					'data-preset-type': key,
					'data-days': preset.days,
					'role': 'button',
					'tabindex': '0'
				} );

				var $button = $( '<button>', {
					'class': 'wsscd-timeline-preset__button',
					'type': 'button',
					'aria-label': preset.label
				} );

				var iconHtml = WSSCD.IconHelper ? WSSCD.IconHelper.get( preset.icon || 'clock', { size: 20 } ) : '<span class="wsscd-icon wsscd-icon-' + ( preset.icon || 'clock' ) + '"></span>';
				$button.append(
					$( '<span>', {
						'class': 'wsscd-timeline-preset__icon',
						'aria-hidden': 'true'
					} ).html( iconHtml ),
					$( '<span>', {
						'class': 'wsscd-timeline-preset__duration',
						'text': preset.days + ' days'
					} ),
					$( '<span>', {
						'class': 'wsscd-timeline-preset__name',
						'text': preset.label
					} )
				);

				var $tooltip = $( '<span>', {
					'class': 'wsscd-timeline-preset__tooltip',
					'text': 'Click to set campaign duration to ' + preset.days + ' days'
				} );

				$presetCard.append( $button, $tooltip );

				$presetCard.on( 'click', function() {
					self.applyPreset( {
						id: key,
						duration: preset.days,
						unit: 'days'
					} );

					$presetsContainer.find( '.wsscd-timeline-preset' ).removeClass( 'wsscd-timeline-preset--active' );
					$( this ).addClass( 'wsscd-timeline-preset--active' );
				} );

				$presetsContainer.append( $presetCard );
			} );

			$( document ).trigger( 'wsscd:schedule:presets:loaded', presets );
		},

		/**
		 * Collect data - overrides mixin to handle special cases
		 * @override
		 */
		collectData: function() {
			// Call parent method from mixin - this is the single source of truth
			var data = WSSCD.Mixins.StepPersistence.collectData.call( this );

			if ( window.wsscdWizardData && window.wsscdWizardData.timezone ) {
				data.timezone = window.wsscdWizardData.timezone;
			}

			return data;
		},

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
			var $durationDisplay = $( '#wsscd-duration-display' );
			var $durationText = $( '#wsscd-duration-text' );
			var $durationHint = $( '.wsscd-hint-text' );

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

				$durationText.text( durationText );

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
					$( '#wsscd-duration-hint' ).show();
				} else {
					$( '#wsscd-duration-hint' ).hide();
				}

				$durationDisplay.show();
			} catch ( error ) {
				// Silently fail - don't disrupt user experience
				$durationDisplay.hide();
			}
		},

		/**
		 * Get preview occurrence count
		 */
		/**
		 * Format a date range for display in timeline markers
		 * Handles same-month and cross-month ranges
		 *
		 * @param {Date} startDate - Start date
		 * @param {Date} endDate - End date
		 * @return {object} { days: '2-5', months: 'Feb' } or { days: '28-2', months: 'Feb-Mar' }
		 */
		formatDateRange: function( startDate, endDate ) {
			var startDay = startDate.getDate();
			var endDay = endDate.getDate();
			var startMonth = startDate.toLocaleDateString( 'en-US', { month: 'short' } );
			var endMonth = endDate.toLocaleDateString( 'en-US', { month: 'short' } );

			// Same day (1-day campaign)
			if ( startDay === endDay && startMonth === endMonth ) {
				return {
					days: String( startDay ),
					months: startMonth
				};
			}

			// Same month
			if ( startMonth === endMonth ) {
				return {
					days: startDay + '-' + endDay,
					months: startMonth
				};
			}

			// Cross-month
			return {
				days: startDay + '-' + endDay,
				months: startMonth + '-' + endMonth
			};
		},

		/**
		 * Format tooltip date range for hover display
		 *
		 * @param {Date} startDate - Start date
		 * @param {Date} endDate - End date
		 * @return {string} Formatted date range (e.g., "Feb 1-3, 2026" or "Feb 28 - Mar 2, 2026")
		 */
		formatTooltipDate: function( startDate, endDate ) {
			var startDay = startDate.getDate();
			var endDay = endDate.getDate();
			var startMonth = startDate.toLocaleDateString( 'en-US', { month: 'short' } );
			var endMonth = endDate.toLocaleDateString( 'en-US', { month: 'short' } );
			var startYear = startDate.getFullYear();
			var endYear = endDate.getFullYear();

			// Same day (1-day campaign)
			if ( startDay === endDay && startMonth === endMonth && startYear === endYear ) {
				return startMonth + ' ' + startDay + ', ' + startYear;
			}

			// Same month and year
			if ( startMonth === endMonth && startYear === endYear ) {
				return startMonth + ' ' + startDay + '-' + endDay + ', ' + startYear;
			}

			// Same year but different months
			if ( startYear === endYear ) {
				return startMonth + ' ' + startDay + ' - ' + endMonth + ' ' + endDay + ', ' + startYear;
			}

			// Cross-year
			return startMonth + ' ' + startDay + ', ' + startYear + ' - ' + endMonth + ' ' + endDay + ', ' + endYear;
		},

		/**
		 * Get the number of occurrences to calculate for preview
		 * Dynamic based on end type
		 */
		getPreviewOccurrenceCount: function() {
			var state = this.modules.state ? this.modules.state.getState() : {};
			var endType = state.recurrenceEndType || 'never';
			var count = parseInt( state.recurrenceCount, 10 ) || 10;

			if ( 'after' === endType ) {
				// Calculate all occurrences up to 12 for "after X" type
				return Math.min( count, 12 );
			}

			if ( 'never' === endType ) {
				// For "forever", only calculate what we display (5) + infinity indicator
				return 5;
			}

			// For "on date", calculate more to show accurate count
			return 10;
		},

		/**
		 * Get the maximum number of markers to display in timeline
		 * Dynamic based on end type:
		 * - "After X" with small X (≤6): Show all
		 * - "After X" with large X (>6): Show 6 + "+X more"
		 * - "Never"/"On date": Show 5 as reasonable preview
		 */
		getMaxDisplayMarkers: function() {
			var state = this.modules.state ? this.modules.state.getState() : {};
			var endType = state.recurrenceEndType || 'never';
			var count = parseInt( state.recurrenceCount, 10 ) || 10;

			if ( 'after' === endType ) {
				// Show all if count is small (≤6), otherwise cap at 6
				return count <= 6 ? count : 6;
			}

			// For "never" or "on date", show 5 as reasonable preview
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
				'sun': 'Sunday'
			};

			var labels = [];
			for ( var i = 0; i < dayValues.length; i++ ) {
				if ( dayMap[dayValues[i]] ) {
					labels.push( dayMap[dayValues[i]] );
				}
			}

			return labels;
		},


	/**
	 * Show or clear validation based on result
	 *
	 * Centralizes error display logic to avoid duplication.
	 * Shows error on the correct field (from validation.field) or clears current field error.
	 *
	 * @private
	 * @param {object} validation - Validation result {valid: boolean, field: string, message: string}
	 * @param {jQuery} $input - Current input field being validated
	 * @return {void}
	 */
	_showOrClearValidation: function( validation, $input ) {
		if ( ! window.WSSCD || ! window.WSSCD.ValidationError ) {
			return;
		}

		if ( ! validation.valid ) {
			// Map hidden field IDs to visible display field IDs
			var fieldId = validation.field;
			if ( 'start_date' === fieldId || 'end_date' === fieldId ) {
				fieldId = fieldId + '_display';
			}

			var $errorField = this.$container.find( '#' + fieldId );
			if ( $errorField.length ) {
				WSSCD.ValidationError.show( $errorField, validation.message );
			}
		} else {
			// Clear from both the input and its display counterpart
			WSSCD.ValidationError.clear( $input );

			// Also clear from display field if applicable
			var inputId = $input.attr( 'id' );
			if ( inputId && ( 'start_date' === inputId || 'end_date' === inputId ) ) {
				var $displayField = this.$container.find( '#' + inputId + '_display' );
				if ( $displayField.length ) {
					WSSCD.ValidationError.clear( $displayField );
				}
			}
		}
	},

	/**
	 * Validate recurring campaign requirements
	 * Single source of truth - used by both real-time handlers and validateStep()
	 * @private
	 * @param {boolean} enableRecurring - Whether recurring is enabled
	 * @param {string} endDate - End date value
	 * @param {number} durationSeconds - Duration in seconds
	 * @return {object} Validation result {valid: boolean, field: string, message: string}
	 */
	_validateRecurring: function( enableRecurring, endDate, durationSeconds ) {
		if ( ! enableRecurring ) {
			return { valid: true };
		}

		var hasEndDate = endDate && '' !== endDate;
		var hasDuration = durationSeconds && durationSeconds > 0;

		if ( ! hasEndDate && ! hasDuration ) {
			return {
				valid: false,
				field: 'end_time', // Error at end of row, matching start_time error position
				message: 'Recurring campaigns must have an end date'
			};
		}

		return { valid: true };
	},

	/**
	 * Get campaign duration in days
	 * @return {number} Duration in days, or 0 if dates not set
	 */
	getCampaignDurationDays: function() {
		var state = this.modules.state ? this.modules.state.getState() : {};
		if ( ! state.startDate || ! state.endDate ) {
			return 0;
		}

		var startDate = new Date( state.startDate );
		var endDate = new Date( state.endDate );
		var durationMs = endDate.getTime() - startDate.getTime();
		return Math.ceil( durationMs / ( 1000 * 60 * 60 * 24 ) );
	},

	/**
	 * Update weekly day chips - disable days that would cause overlap
	 * Called when: days selected, campaign dates change, pattern changes
	 */
	updateWeeklyDayConstraints: function() {
		var self = this;
		var durationDays = this.getCampaignDurationDays();
		var $daysContainer = this.$container.find( '.wsscd-recurring-days' );
		var $dayChips = $daysContainer.find( '.wsscd-day-chip' );
		var $infoEl = $daysContainer.find( '.wsscd-days-info' );

		// Day name to number mapping
		var dayMap = { 'sun': 0, 'mon': 1, 'tue': 2, 'wed': 3, 'thu': 4, 'fri': 5, 'sat': 6 };
		var dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

		// Get currently selected days
		var selectedDays = [];
		$dayChips.each( function() {
			var $chip = $( this );
			var $checkbox = $chip.find( 'input[type="checkbox"]' );
			if ( $checkbox.is( ':checked' ) ) {
				selectedDays.push( dayMap[ $checkbox.val() ] );
			}
		} );

		// Reset all chips first
		$dayChips.removeClass( 'disabled' );
		$dayChips.find( 'input[type="checkbox"]' ).prop( 'disabled', false );

		// If no duration or duration is 1 day, no constraints needed
		if ( durationDays <= 1 ) {
			$infoEl.remove();
			$daysContainer.removeClass( 'has-info' );
			return;
		}

		// Calculate which days should be disabled based on selected days
		var disabledDays = [];

		selectedDays.forEach( function( selectedDay ) {
			// Disable days within (durationDays - 1) before and after
			for ( var offset = 1; offset < durationDays; offset++ ) {
				// Days after
				var afterDay = ( selectedDay + offset ) % 7;
				if ( selectedDays.indexOf( afterDay ) === -1 ) {
					disabledDays.push( afterDay );
				}
				// Days before
				var beforeDay = ( selectedDay - offset + 7 ) % 7;
				if ( selectedDays.indexOf( beforeDay ) === -1 ) {
					disabledDays.push( beforeDay );
				}
			}
		} );

		// Remove duplicates
		disabledDays = disabledDays.filter( function( day, index, arr ) {
			return arr.indexOf( day ) === index;
		} );

		// Apply disabled state to chips
		$dayChips.each( function() {
			var $chip = $( this );
			var $checkbox = $chip.find( 'input[type="checkbox"]' );
			var dayNum = dayMap[ $checkbox.val() ];

			if ( disabledDays.indexOf( dayNum ) !== -1 ) {
				$chip.addClass( 'disabled' );
				$checkbox.prop( 'disabled', true );
			}
		} );

		// Show info message about constraints
		if ( durationDays > 1 ) {
			if ( ! $infoEl.length ) {
				$infoEl = $( '<div class="wsscd-days-info"></div>' );
				$daysContainer.append( $infoEl );
			}

			var availableDays = 7 - disabledDays.length - selectedDays.length;
			var infoText = 'Campaign runs ' + durationDays + ' days. Days must be ' + durationDays + '+ days apart.';

			if ( selectedDays.length > 0 && availableDays === 0 && disabledDays.length > 0 ) {
				infoText += ' No more days available.';
			}

			$infoEl.html(
				'<span class="wsscd-days-info__icon">ℹ️</span>' +
				'<span class="wsscd-days-info__text">' + infoText + '</span>'
			);
			$daysContainer.addClass( 'has-info' );
		} else {
			$infoEl.remove();
			$daysContainer.removeClass( 'has-info' );
		}
	},

	/**
	 * Validate weekly recurrence days - simplified for constrained UI
	 * Only checks for no days selected or all days selected
	 *
	 * @param {array} selectedDays - Array of selected day values ('mon', 'tue', etc.)
	 * @return {object} Validation result {valid: boolean, warning: string}
	 */
	validateWeeklyDays: function( selectedDays ) {
		// Check if no days selected
		if ( ! selectedDays || 0 === selectedDays.length ) {
			return {
				valid: false,
				warning: 'Please select at least one day for weekly recurrence.',
				type: 'error'
			};
		}

		// Check if all 7 days selected - suggest daily pattern instead
		if ( 7 === selectedDays.length ) {
			return {
				valid: true,
				warning: 'All days selected. Consider using "Daily" pattern instead for simpler configuration.',
				type: 'suggestion'
			};
		}

		return { valid: true };
	},

	/**
	 * Show validation feedback for weekly days selection
	 * @param {object} validation - Result from validateWeeklyDays
	 */
	showWeeklyDaysValidation: function( validation ) {
		var $daysContainer = this.$container.find( '.wsscd-recurring-days' );
		var $warningEl = $daysContainer.find( '.wsscd-days-warning' );
		var $suggestionEl = $daysContainer.find( '.wsscd-days-suggestion' );

		// Clear warning/suggestion (but not info)
		$warningEl.remove();
		$suggestionEl.remove();
		$daysContainer.removeClass( 'has-warning has-suggestion has-error' );

		// No message needed
		if ( validation.valid && ! validation.warning ) {
			return;
		}

		// Show warning or suggestion
		if ( validation.warning ) {
			var cssClass = 'suggestion' === validation.type ? 'wsscd-days-suggestion' : 'wsscd-days-warning';
			var icon = 'suggestion' === validation.type ? '💡' : ( 'error' === validation.type ? '❌' : '⚠️' );
			var containerClass = 'suggestion' === validation.type ? 'has-suggestion' : ( 'error' === validation.type ? 'has-error' : 'has-warning' );

			var $el = $( '<div class="' + cssClass + '"></div>' );
			$el.html(
				'<span class="' + cssClass + '__icon">' + icon + '</span>' +
				'<span class="' + cssClass + '__text">' + validation.warning + '</span>'
			);
			$daysContainer.append( $el );
			$daysContainer.addClass( containerClass );
		}
	},

	/**
	 * Validate recurrence interval against campaign duration
	 *
	 * NOTE: Always returns valid because the recurrence logic is:
	 *   nextInstanceStart = previousInstanceEnd + interval
	 *
	 * This means ANY positive interval is valid - overlap is impossible.
	 * Example: 3-day campaign (Feb 1-3), 1-day interval:
	 *   Instance 1: Feb 1-3
	 *   Instance 2: Feb 3 + 1 = Feb 4-6 (no overlap)
	 *
	 * For weekly patterns with specific days, overlap prevention is handled
	 * by updateWeeklyDayConstraints() which disables invalid day selections.
	 *
	 * @return {object} Validation result {valid: boolean}
	 */
	validateIntervalVsDuration: function() {
		return { valid: true };
	},

	/**
	 * Show validation feedback for interval (clears any existing warnings)
	 * @param {object} validation - Result from validateIntervalVsDuration
	 */
	showIntervalValidation: function( validation ) {
		var $frequencyRow = this.$container.find( '.wsscd-recurring-frequency' );
		var $warningEl = $frequencyRow.find( '.wsscd-interval-warning' );

		// Always clear since validation always passes
		$warningEl.remove();
		$frequencyRow.removeClass( 'has-warning' );
	},

	/**
	 * Validate recurrence end date
	 * Checks:
	 * 1. End date is not in the past
	 * 2. End date allows at least one recurrence to occur
	 *
	 * @return {object} Validation result {valid: boolean, warning: string}
	 */
	validateRecurrenceEndDate: function() {
		var state = this.modules.state ? this.modules.state.getState() : {};

		// Only validate if using "on" end type
		if ( 'on' !== state.recurrenceEndType || ! state.recurrenceEndDate ) {
			return { valid: true };
		}

		var recurrenceEnd = new Date( state.recurrenceEndDate );
		recurrenceEnd.setHours( 23, 59, 59, 999 );

		// Check if end date is in the past
		var today = new Date();
		today.setHours( 0, 0, 0, 0 );
		if ( recurrenceEnd < today ) {
			return {
				valid: false,
				warning: 'Recurrence end date (' + recurrenceEnd.toLocaleDateString() + ') is in the past.',
				type: 'error'
			};
		}

		// Need campaign dates to calculate first recurrence
		if ( ! state.startDate || ! state.endDate ) {
			return { valid: true };
		}

		var campaignEnd = new Date( state.endDate );
		var interval = parseInt( state.recurrenceInterval, 10 ) || 1;
		var pattern = state.recurrencePattern || 'daily';

		// Calculate when first recurrence would start
		var firstRecurrenceStart = new Date( campaignEnd );
		if ( 'daily' === pattern ) {
			firstRecurrenceStart.setDate( firstRecurrenceStart.getDate() + interval );
		} else if ( 'weekly' === pattern ) {
			firstRecurrenceStart.setDate( firstRecurrenceStart.getDate() + ( interval * 7 ) );
		} else if ( 'monthly' === pattern ) {
			firstRecurrenceStart.setMonth( firstRecurrenceStart.getMonth() + interval );
		}

		// Check if end date is before first recurrence would even start
		if ( recurrenceEnd < firstRecurrenceStart ) {
			var firstStartFormatted = firstRecurrenceStart.toLocaleDateString();
			return {
				valid: false,
				warning: 'Recurrence end date (' + recurrenceEnd.toLocaleDateString() + ') is before the first recurrence would start (' + firstStartFormatted + ').',
				type: 'error'
			};
		}

		return { valid: true };
	},

	/**
	 * Update the minimum selectable date for recurrence end date picker
	 * Disables dates before the first recurrence would start
	 */
	updateRecurrenceEndDateMinDate: function() {
		var $recurrenceEndDate = this.$container.find( '#recurrence_end_date' );

		if ( ! $recurrenceEndDate.length || ! $.fn.datepicker ) {
			return;
		}

		var state = this.modules.state ? this.modules.state.getState() : {};

		// Default to today if no campaign dates set
		if ( ! state.endDate ) {
			$recurrenceEndDate.datepicker( 'option', 'minDate', 0 );
			return;
		}

		var campaignEnd = new Date( state.endDate );
		var interval = parseInt( state.recurrenceInterval, 10 ) || 1;
		var pattern = state.recurrencePattern || 'daily';

		// Calculate when first recurrence would start
		var firstRecurrenceStart = new Date( campaignEnd );
		if ( 'daily' === pattern ) {
			firstRecurrenceStart.setDate( firstRecurrenceStart.getDate() + interval );
		} else if ( 'weekly' === pattern ) {
			firstRecurrenceStart.setDate( firstRecurrenceStart.getDate() + ( interval * 7 ) );
		} else if ( 'monthly' === pattern ) {
			firstRecurrenceStart.setMonth( firstRecurrenceStart.getMonth() + interval );
		}

		// Set min date to first recurrence start
		$recurrenceEndDate.datepicker( 'option', 'minDate', firstRecurrenceStart );

		// If current value is before min date, clear it
		var currentVal = $recurrenceEndDate.val();
		if ( currentVal ) {
			var currentDate = new Date( currentVal );
			if ( currentDate < firstRecurrenceStart ) {
				$recurrenceEndDate.val( '' );
				if ( this.modules.state && 'function' === typeof this.modules.state.setState ) {
					this.modules.state.setState( { recurrenceEndDate: '' } );
				}
			}
		}
	},

	/**
	 * Validate monthly pattern for end-of-month edge cases
	 * Warns if campaign start date is 29th, 30th, or 31st
	 *
	 * @return {object} Validation result {valid: boolean, info: string}
	 */
	validateMonthlyEdgeCase: function() {
		var state = this.modules.state ? this.modules.state.getState() : {};

		// Only validate monthly pattern
		if ( 'monthly' !== state.recurrencePattern ) {
			return { valid: true };
		}

		if ( ! state.endDate ) {
			return { valid: true };
		}

		var endDate = new Date( state.endDate );
		var dayOfMonth = endDate.getDate();

		// Days 29-31 can cause month-end shifts
		if ( dayOfMonth >= 29 ) {
			return {
				valid: true, // Not blocking, just informational
				info: 'Note: Campaign ends on day ' + dayOfMonth + '. For months with fewer days, recurrence will shift to the last day of that month (e.g., Feb ' + ( dayOfMonth > 28 ? '28/29' : dayOfMonth ) + ').'
			};
		}

		return { valid: true };
	},

	/**
	 * Show informational message for monthly edge case
	 * @param {object} validation - Result from validateMonthlyEdgeCase
	 */
	showMonthlyEdgeCaseInfo: function( validation ) {
		var $patternRow = this.$container.find( '.wsscd-recurring-frequency' );
		var $infoEl = $patternRow.find( '.wsscd-monthly-info' );

		if ( ! validation.info ) {
			$infoEl.remove();
			return;
		}

		// Create or update info element
		if ( ! $infoEl.length ) {
			$infoEl = $( '<div class="wsscd-monthly-info"></div>' );
			$patternRow.append( $infoEl );
		}

		$infoEl.html(
			'<span class="wsscd-monthly-info__icon">ℹ️</span>' +
			'<span class="wsscd-monthly-info__text">' + validation.info + '</span>'
		);
	},

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

		if ( startDate < currentDateStr ) {
			return {
				valid: false,
				field: 'start_date',
				message: 'Campaign start date cannot be in the past'
			};
		}

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
				message: 'Campaign must end after it starts'
			};
		}

		// CONTEXT 4: Check if end time is before start time (same day)
		if ( startDate && startTime && endDate === startDate && endTime <= startTime ) {
			return {
				valid: false,
				field: 'end_time',
				message: 'Campaign must end after it starts'
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

		if ( 'scheduled' === state.startType && state.startDate ) {
			var startValidation = this._validateStartTime( state.startDate, state.startTime || '00:00' );
			if ( ! startValidation.valid ) {
				errors.push( startValidation );
			}
		}

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

		// Validate recurring campaigns require an end date or duration
		// IMPORTANT: For free users, enableRecurring should always be false (enforced server-side)
		var recurringValidation = this._validateRecurring(
			state.enableRecurring,
			state.endDate,
			state.durationSeconds
		);
		if ( ! recurringValidation.valid ) {
			errors.push( recurringValidation );
		}

		// Additional smart validations for recurring campaigns
		if ( state.enableRecurring && state.endDate ) {
			// Validate weekly days selection
			if ( 'weekly' === state.recurrencePattern ) {
				var weeklyValidation = this.validateWeeklyDays( state.recurrenceDays );
				if ( ! weeklyValidation.valid && 'error' === weeklyValidation.type ) {
					errors.push( {
						valid: false,
						field: 'recurrence_days',
						message: weeklyValidation.warning
					} );
				}
			}

			// Validate recurrence end date
			if ( 'on' === state.recurrenceEndType ) {
				var endDateValidation = this.validateRecurrenceEndDate();
				if ( ! endDateValidation.valid ) {
					errors.push( {
						valid: false,
						field: 'recurrence_end_date',
						message: endDateValidation.warning
					} );
				}
			}
		}

		if ( errors.length > 0 ) {
			// Convert errors array to object format for ValidationError.showMultiple
			// Map hidden field IDs to visible display field IDs
			var errorObject = {};
			errors.forEach( function( error ) {
				var fieldId = error.field;
				if ( 'start_date' === fieldId || 'end_date' === fieldId ) {
					fieldId = fieldId + '_display';
				}
				errorObject[fieldId] = error.message;
			} );

			// Use wizard validation pattern - handles inline errors and auto-scroll to field
			if ( window.WSSCD && window.WSSCD.ValidationError ) {
				WSSCD.ValidationError.showMultiple( errorObject, this.$container, {
					clearFirst: true,
					showSummary: false, // No banner for single field errors - just scroll to field
					focusFirstError: true
				} );

				// If recurring validation error, apply red border to date field and open calendar
				if ( errorObject.end_time && ! recurringValidation.valid ) {
					$( '#end_date_display' ).addClass( 'error' ).attr( 'aria-invalid', 'true' );
					// Small delay to ensure error display is complete before opening calendar
					setTimeout( function() {
						$( '#end_date_display' ).datepicker( 'show' );
					}, 100 );
				}
			}
		} else {
			// Clear any error styling from date field when validation passes
			$( '#end_date_display' ).removeClass( 'error' ).attr( 'aria-invalid', 'false' );
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
			$( document ).off( 'wsscd:preset:selected' );
		}
	} );

} )( jQuery );
