/**
 * Date Time Picker
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/components/date-time-picker.js
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

( function( window, $ ) {
	'use strict';

	// Ensure namespace exists
	window.WSSCD = window.WSSCD || {};
	WSSCD.Modules = WSSCD.Modules || {};

	/**
	 * Date Time Picker Module
	 */
	WSSCD.Modules.DateTimePicker = {

		// Module state
		state: {
			isInitialized: false,
			pickers: {},
			config: null,
			callbacks: {}
		},

		/**
		 * Initialize date time picker module
		 *
		 * @param {object} options Configuration options
		 */
		init: function( options ) {
			options = options || {};
			if ( this.state.isInitialized ) {
				return;
			}

			this.state.callbacks = {
				onDateChange: options.onDateChange || function() {},
				onTimeChange: options.onTimeChange || function() {}
			};

			this.state.config = WSSCD.ModuleLoader['schedule-config'];

			if ( options.startDate ) {
				this.initDatePicker( options.startDate, 'start' );
			}
			if ( options.endDate ) {
				this.initDatePicker( options.endDate, 'end' );
			}

			// Bind global events
			this.bindGlobalEvents();

			this.state.isInitialized = true;
		},

		/**
		 * Initialize date picker for element
		 *
		 * @param {jQuery} $element Element to attach picker to
		 * @param {string} type 'start' or 'end'
		 */
		initDatePicker: function( $element, type ) {
			if ( !$element || !$element.length ) {return;}

			var self = this;
			var pickerId = $element.attr( 'id' ) || 'picker-' + type;

			this.state.pickers[pickerId] = {
				element: $element,
				type: type,
				isOpen: false
			};

			// Bind click event
			$element.on( 'click.datepicker', function( e ) {
				e.preventDefault();
				self.showCalendarPicker( $( this ), type );
			} );

			if ( !$element.next( '.wsscd-calendar-icon' ).length ) {
				var buttonLabel = 'start' === type ?
					wp.i18n.__( 'Choose start date', 'smart-cycle-discounts' ) :
					wp.i18n.__( 'Choose end date', 'smart-cycle-discounts' );

				var calendarIcon = WSSCD.IconHelper ? WSSCD.IconHelper.get( 'calendar', { size: 16 } ) : '<span class="wsscd-icon wsscd-icon-calendar"></span>';
				var $button = $( '<button type="button" class="wsscd-calendar-icon" data-target="' + pickerId + '">' +
                    calendarIcon +
                    '<span class="screen-reader-text">' + buttonLabel + '</span>' +
                    '</button>' );

				// Add ARIA attributes
				$button.attr( {
					'aria-label': buttonLabel,
					'aria-controls': pickerId,
					'aria-expanded': 'false'
				} );

				// Bind click event to button
				$button.on( 'click.datepicker', function( e ) {
					e.preventDefault();
					self.showCalendarPicker( $element, type );
				} );

				$element.after( $button );
			}
		},

		/**
		 * Show calendar picker
		 *
		 * @param {jQuery} $element Target element
		 * @param {string} type 'start' or 'end'
		 */
		showCalendarPicker: function( $element, type ) {
			// Use Flatpickr if available, otherwise fallback to native
			if ( 'undefined' !== typeof window.flatpickr ) {
				this.showFlatpickr( $element, type );
			} else {
				this.showNativePicker( $element, type );
			}
		},

		/**
		 * Show Flatpickr calendar
		 *
		 * @param {jQuery} $element Target element
		 * @param {string} type 'start' or 'end'
		 */
		showFlatpickr: function( $element, type ) {
			var self = this;

			if ( $element[0]._flatpickr ) {
				$element[0]._flatpickr.open();
				return;
			}

			var constraints = this.getDateConstraints( type );

			// Initialize Flatpickr
			window.flatpickr( $element[0], {
				dateFormat: 'M d, Y',
				altInput: true,
				altFormat: 'F j, Y',
				minDate: constraints.minDate,
				maxDate: constraints.maxDate,
				defaultDate: constraints.defaultDate,
				onChange: function( selectedDates, _dateStr, _instance ) {
					self.handleDateSelect( type, selectedDates[0] );
				},
				onReady: function( selectedDates, dateStr, instance ) {
					var ariaLabel = 'start' === type ?
						wp.i18n.__( 'Start date', 'smart-cycle-discounts' ) :
						wp.i18n.__( 'End date', 'smart-cycle-discounts' );
					instance.altInput.setAttribute( 'aria-label', ariaLabel );
					instance.altInput.setAttribute( 'aria-describedby', type + '_date_format_hint' );

					var hint = document.createElement( 'span' );
					hint.id = type + '_date_format_hint';
					hint.className = 'screen-reader-text';
					hint.textContent = wp.i18n.__( 'Format: Month Day, Year. Use arrow keys to navigate calendar.', 'smart-cycle-discounts' );
					instance.altInput.parentNode.appendChild( hint );
				}
			} );

			// Don't open automatically - wait for user interaction
		},

		/**
		 * Show native date picker
		 *
		 * @param {jQuery} $element Target element
		 * @param {string} type 'start' or 'end'
		 */
		showNativePicker: function( $element, type ) {
			var self = this;

			var hiddenId = $element.attr( 'id' ) + '-native';
			var $hidden = $( '#' + hiddenId );

			if ( !$hidden.length ) {
				$hidden = $( '<input type="date" id="' + hiddenId + '" class="wsscd-hidden-date-input">' );
				$element.after( $hidden );

				var constraints = this.getDateConstraints( type );
				if ( constraints.minDate ) {
					$hidden.attr( 'min', this.formatDateForInput( constraints.minDate ) );
				}
				if ( constraints.maxDate ) {
					$hidden.attr( 'max', this.formatDateForInput( constraints.maxDate ) );
				}

				// Handle change
				$hidden.on( 'change', function() {
					var date = new Date( $( this ).val() );
					if ( !isNaN( date.getTime() ) ) {
						self.handleDateSelect( type, date );
					}
				} );
			}

			// Trigger click on hidden input
			$hidden.trigger( 'click' );
		},

		/**
		 * Show time picker
		 *
		 * @param {jQuery} $element Target element
		 * @param {string} type 'start' or 'end'
		 */
		showTimePicker: function( $element, type ) {
			var self = this;

			this.state.lastTriggerElement = $element;

			// Close any existing pickers
			this.closeTimePicker();

			var $picker = this.createTimePicker( type );

			// Position picker
			var offset = $element.offset();
			$picker.css( {
				top: offset.top + $element.outerHeight() + 5,
				left: offset.left
			} );

			// Append to body
			$( 'body' ).append( $picker );

			// Focus first element
			$picker.find( 'select:first' ).focus();

			// Bind close events using requestAnimationFrame
			requestAnimationFrame( function() {
				$( document ).on( 'click.timepicker', function( e ) {
					if ( !$( e.target ).closest( '.wsscd-time-picker-dropdown' ).length &&
                        !$( e.target ).is( $element ) ) {
						self.closeTimePicker();
					}
				} );
			} );
		},

		/**
		 * Create time picker dropdown
		 *
		 * @param {string} type 'start' or 'end'
		 * @returns {jQuery} Picker element
		 */
		createTimePicker: function( type ) {
			var self = this;
			var pickerId = 'wsscd-time-picker-' + type;
			var $picker = $( '<div class="wsscd-time-picker-dropdown" role="dialog" aria-modal="true"></div>' );

			// Add ARIA attributes
			var pickerLabel = 'start' === type ?
				wp.i18n.__( 'Choose start time', 'smart-cycle-discounts' ) :
				wp.i18n.__( 'Choose end time', 'smart-cycle-discounts' );

			$picker.attr( {
				'id': pickerId,
				'aria-label': pickerLabel,
				'aria-describedby': pickerId + '-desc'
			} );

			var description = wp.i18n.__( 'Use arrow keys to navigate options. Press Enter to select.', 'smart-cycle-discounts' );
			$picker.append( '<div id="' + pickerId + '-desc" class="screen-reader-text">' + description + '</div>' );

			var $commonTimes = $( '<div class="wsscd-time-picker-common" role="group"></div>' );
			$commonTimes.attr( 'aria-label', wp.i18n.__( 'Common times', 'smart-cycle-discounts' ) );
			var commonTimes = [
				'12:00 AM', '6:00 AM', '9:00 AM', '12:00 PM',
				'3:00 PM', '6:00 PM', '9:00 PM', '11:59 PM'
			];

			for ( var i = 0; i < commonTimes.length; i++ ) {
				var time = commonTimes[i];
				var index = i;
				var $option = $( '<button type="button" class="wsscd-time-option" role="option">' + time + '</button>' );
				$option.attr( {
					'aria-selected': 'false',
					'tabindex': 0 === index ? '0' : '-1'
				} );

				$option.on( 'click.timepicker', function() {
					self.handleTimeSelect( type, time );
					self.closeTimePicker();
				} );

				// Keyboard navigation
				$option.on( 'keydown.timepicker', function( e ) {
					self.handleTimePickerKeyboard( e, $( this ) );
				} );

				$commonTimes.append( $option );
			}

			$picker.append( '<h3 class="wsscd-time-picker-header" id="' + pickerId + '-common">' +
                wp.i18n.__( 'Common Times', 'smart-cycle-discounts' ) + '</h3>' );
			$commonTimes.attr( 'aria-labelledby', pickerId + '-common' );
			$picker.append( $commonTimes );

			var $custom = this.createCustomTimeSelector( type );
			$picker.append( '<h3 class="wsscd-time-picker-header" id="' + pickerId + '-custom">' +
                wp.i18n.__( 'Custom Time', 'smart-cycle-discounts' ) + '</h3>' );
			$custom.attr( 'aria-labelledby', pickerId + '-custom' );
			$picker.append( $custom );

			var $closeBtn = $( '<button type="button" class="wsscd-time-picker-close" aria-label="' +
                wp.i18n.__( 'Close time picker', 'smart-cycle-discounts' ) + '">&times;</button>' );
			var closeBtnSelf = this;
			$closeBtn.on( 'click', function() { return closeBtnSelf.closeTimePicker(); } );
			$picker.prepend( $closeBtn );

			// Trap focus and handle Escape
			$picker.on( 'keydown', function( e ) {
				if ( 'Escape' === e.key ) {
					self.closeTimePicker();
					return;
				}

				// Focus trap for Tab navigation
				if ( 'Tab' === e.key ) {
					var $focusableElements = $picker.find( 'button:visible, select:visible, [tabindex="0"]:visible' );
					var $firstElement = $focusableElements.first();
					var $lastElement = $focusableElements.last();

					if ( e.shiftKey ) {
						// Shift+Tab - wrap to last element if on first
						if ( $( e.target.ownerDocument.activeElement ).is( $firstElement ) ) {
							e.preventDefault();
							$lastElement.focus();
						}
					} else {
						// Tab - wrap to first element if on last
						if ( $( e.target.ownerDocument.activeElement ).is( $lastElement ) ) {
							e.preventDefault();
							$firstElement.focus();
						}
					}
				}
			} );

			return $picker;
		},

		/**
		 * Handle keyboard navigation in time picker
		 *
		 * @param {Event} e Keyboard event
		 * @param {jQuery} $element Current element
		 */
		handleTimePickerKeyboard: function( e, $element ) {
			var $options = $element.parent().find( '.wsscd-time-option' );
			var currentIndex = $options.index( $element );
			var newIndex = currentIndex;

			switch( e.key ) {
				case 'ArrowRight':
				case 'ArrowDown':
					e.preventDefault();
					newIndex = ( currentIndex + 1 ) % $options.length;
					break;

				case 'ArrowLeft':
				case 'ArrowUp':
					e.preventDefault();
					newIndex = ( currentIndex - 1 + $options.length ) % $options.length;
					break;

				case 'Home':
					e.preventDefault();
					newIndex = 0;
					break;

				case 'End':
					e.preventDefault();
					newIndex = $options.length - 1;
					break;

				case 'Enter':
				case ' ':
					e.preventDefault();
					$element.click();
					return;

				default:
					return;
			}

			$options.attr( 'tabindex', '-1' );
			$options.eq( newIndex ).attr( 'tabindex', '0' ).focus();
		},

		/**
		 * Create custom time selector
		 *
		 * @param {string} type 'start' or 'end'
		 * @returns {jQuery} Custom selector element
		 */
		createCustomTimeSelector: function( type ) {
			var self = this;
			var $custom = $( '<div class="wsscd-time-picker-custom" role="group"></div>' );

			// Hour selector
			var $hourSelect = $( '<select class="wsscd-time-hour" aria-label="' +
                wp.i18n.__( 'Hour', 'smart-cycle-discounts' ) + '"></select>' );
			for ( var i = 1; 12 >= i; i++ ) {
				$hourSelect.append( '<option value="' + i + '">' + i + '</option>' );
			}

			// Minute selector
			var $minuteSelect = $( '<select class="wsscd-time-minute" aria-label="' +
                wp.i18n.__( 'Minute', 'smart-cycle-discounts' ) + '"></select>' );
			for ( var j = 0; 60 > j; j += 5 ) {
				var val = 10 > j ? '0' + j : String( j );
				$minuteSelect.append( '<option value="' + val + '">' + val + '</option>' );
			}

			// AM/PM selector
			var $ampmSelect = $( '<select class="wsscd-time-ampm" aria-label="' +
                wp.i18n.__( 'AM or PM', 'smart-cycle-discounts' ) + '"></select>' );
			$ampmSelect.append( '<option value="AM">AM</option>' );
			$ampmSelect.append( '<option value="PM">PM</option>' );

			// Apply button
			var $applyBtn = $( '<button type="button" class="button button-primary">' +
                wp.i18n.__( 'Apply', 'smart-cycle-discounts' ) + '</button>' );
			$applyBtn.on( 'click', function() {
				var time = $hourSelect.val() + ':' + $minuteSelect.val() + ' ' + $ampmSelect.val();
				self.handleTimeSelect( type, time );
				self.closeTimePicker();
			} );

			$custom.find( 'select' ).on( 'keydown', function( e ) {
				if ( 'Enter' === e.key ) {
					$applyBtn.click();
				}
			} );

			$custom.append( $hourSelect );
			$custom.append( '<span aria-hidden="true">:</span>' );
			$custom.append( $minuteSelect );
			$custom.append( $ampmSelect );
			$custom.append( $applyBtn );

			return $custom;
		},

		/**
		 * Close time picker
		 */
		closeTimePicker: function() {
			$( '.wsscd-time-picker-dropdown' ).remove();
			$( document ).off( 'click.timepicker' );

			// Return focus to trigger element
			if ( this.state.lastTriggerElement && this.state.lastTriggerElement.length ) {
				this.state.lastTriggerElement.focus();
				this.state.lastTriggerElement = null;
			}
		},

		/**
		 * Get date constraints
		 *
		 * @param {string} type 'start' or 'end'
		 * @returns {object} Constraints
		 */
		getDateConstraints: function( type ) {
			var today = new Date();
			today.setHours( 0, 0, 0, 0 );

			var constraints = {
				minDate: null,
				maxDate: null,
				defaultDate: null
			};

			if ( 'start' === type ) {
				constraints.minDate = today;
				constraints.defaultDate = new Date( today.getTime() + 24 * 60 * 60 * 1000 ); // Tomorrow
			} else {
				// For end date, use start date as minimum if available
				var startDateField = $( '#start_date' );
				if ( startDateField.val() ) {
					constraints.minDate = new Date( startDateField.val() );
				} else {
					constraints.minDate = today;
				}
				constraints.defaultDate = new Date( constraints.minDate.getTime() + 7 * 24 * 60 * 60 * 1000 ); // Week later
			}

			return constraints;
		},

		/**
		 * Handle date selection
		 *
		 * @param {string} type 'start' or 'end'
		 * @param {Date} date Selected date
		 */
		handleDateSelect: function( type, date ) {
			if ( !date || isNaN( date.getTime() ) ) {return;}

			// Format date for hidden input
			var dateValue = this.formatDateForInput( date );

			var $hiddenField = $( '#' + type + '_date' );
			$hiddenField.val( dateValue );

			var $displayField = $( '#' + type + '_date_display' );
			$displayField.val( this.formatDateForDisplay( date ) );
			$displayField.data( 'date-value', dateValue );

			// Trigger callback
			if ( this.state.callbacks.onDateChange ) {
				this.state.callbacks.onDateChange( type, dateValue );
			}
		},

		/**
		 * Handle time selection
		 *
		 * @param {string} type 'start' or 'end'
		 * @param {string} time Selected time
		 */
		handleTimeSelect: function( type, time ) {
			var $displayField = $( '#' + type + '_time_display' );
			$displayField.val( time );

			// Parse time and update individual fields
			var parsed = this.parseTime( time );
			if ( parsed ) {
				var hourStr = 10 > parsed.hour ? '0' + parsed.hour : String( parsed.hour );
				var minuteStr = 10 > parsed.minute ? '0' + parsed.minute : String( parsed.minute );
				$( '#' + type + '_time_hour' ).val( hourStr );
				$( '#' + type + '_time_minute' ).val( minuteStr );
				$( '#' + type + '_time_ampm' ).val( parsed.ampm );
			}

			// Trigger callback
			if ( this.state.callbacks.onTimeChange ) {
				this.state.callbacks.onTimeChange( type, time );
			}
		},

		/**
		 * Parse time string
		 *
		 * @param {string} timeStr Time string
		 * @returns {object} Parsed time
		 */
		parseTime: function( timeStr ) {
			var match = timeStr.match( /^( \d{1,2} ):( \d{2} )\s*( AM|PM )$/i );
			if ( match ) {
				return {
					hour: parseInt( match[1], 10 ),
					minute: parseInt( match[2], 10 ),
					ampm: match[3].toUpperCase()
				};
			}
			return null;
		},

		/**
		 * Format date for input field
		 *
		 * @param {Date} date Date to format
		 * @returns {string} Formatted date
		 */
		formatDateForInput: function( date ) {
			var year = date.getFullYear();
			var month = date.getMonth() + 1;
			var day = date.getDate();
			var monthStr = 10 > month ? '0' + month : String( month );
			var dayStr = 10 > day ? '0' + day : String( day );
			return year + '-' + monthStr + '-' + dayStr;
		},

		/**
		 * Format date for display
		 *
		 * @param {Date} date Date to format
		 * @returns {string} Formatted date
		 */
		formatDateForDisplay: function( date ) {
			var months = [ 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
				'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec' ];
			return months[date.getMonth()] + ' ' + date.getDate() + ', ' + date.getFullYear();
		},

		/**
		 * Update date display
		 *
		 * @param {string} type 'start' or 'end'
		 * @param {string} dateString Date string in ISO format
		 */
		updateDisplay: function( type, dateString ) {
			if ( !dateString ) {return;}

			// Parse the date string
			var date = new Date( dateString );
			if ( isNaN( date.getTime() ) ) {return;}

			// Format for display
			var displayDate = this.formatDateForDisplay( date );

			// Find the display element
			var $displayElement = null;
			for ( var pickerId in this.state.pickers ) {
				if ( Object.prototype.hasOwnProperty.call( this.state.pickers, pickerId ) ) {
					var picker = this.state.pickers[pickerId];
					if ( picker.type === type ) {
						$displayElement = picker.element;
					}
				}
			}

			if ( $displayElement && $displayElement.length ) {
				$displayElement.val( displayDate );

				// Update Flatpickr if initialized
				if ( $displayElement[0]._flatpickr ) {
					$displayElement[0]._flatpickr.setDate( date, false );
				}

				if ( $displayElement.hasClass( 'hasDatepicker' ) ) {
					$displayElement.datepicker( 'setDate', date );
				}
			}

			// Also update the corresponding hidden datetime input
			var dateInputId = 'start' === type ? '#start_date' : '#end_date';
			var $dateInput = $( dateInputId );
			if ( $dateInput.length ) {
				$dateInput.val( dateString );
			}
		},

		/**
		 * Bind global events
		 */
		bindGlobalEvents: function() {
			var self = this;

			// Calendar icon clicks
			$( document ).on( 'click.datepicker', '.wsscd-calendar-icon', function( e ) {
				e.preventDefault();
				var targetId = $( this ).data( 'target' );
				var $target = $( '#' + targetId );
				if ( $target.length ) {
					$target.trigger( 'click' );
				}
			} );

			// Escape key to close pickers
			$( document ).on( 'keydown.datepicker', function( e ) {
				if ( 27 === e.keyCode ) { // ESC
					self.closeTimePicker();
				}
			} );

			// Listen for preset selection events
			$( document ).on( 'wsscd:preset:selected', function( e, preset, dates ) {
				if ( dates && dates.start ) {
					self.updateDisplay( 'start', dates.start );
				}
				if ( dates && dates.end ) {
					self.updateDisplay( 'end', dates.end );
				}
			} );

			// Listen for date changes from other components
			$( document ).on( 'wsscd:dates:changed', function( e, startDate, endDate ) {
				if ( startDate ) {
					self.updateDisplay( 'start', startDate );
				}
				if ( endDate ) {
					self.updateDisplay( 'end', endDate );
				}
			} );
		},

		/**
		 * Destroy module
		 */
		destroy: function() {
			$( document ).off( '.datepicker' );
			$( document ).off( 'click.timepicker' );

			// Destroy pickers
			for ( var pickerId in this.state.pickers ) {
				if ( Object.prototype.hasOwnProperty.call( this.state.pickers, pickerId ) ) {
					var picker = this.state.pickers[pickerId];
					var $element = picker.element;

					// Destroy Flatpickr
					if ( $element[0]._flatpickr ) {
						$element[0]._flatpickr.destroy();
					}

					// Destroy jQuery UI datepicker
					if ( $element.hasClass( 'hasDatepicker' ) ) {
						$element.datepicker( 'destroy' );
					}

					$element.off( '.datepicker' );
				}
			}

			// Close any open pickers
			this.closeTimePicker();

			this.state = {
				isInitialized: false,
				pickers: {},
				config: null,
				callbacks: {}
			};
		}
	};

	// Register module with loader
	if ( WSSCD.ModuleLoader ) {
		WSSCD.ModuleLoader.register( 'date-time-picker', WSSCD.Modules.DateTimePicker );
	}

} )( window, jQuery );