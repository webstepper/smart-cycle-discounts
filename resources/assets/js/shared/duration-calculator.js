/**
 * Duration Calculator Module
 *
 * Calculates and formats campaign durations
 *
 * @param window
 * @package SmartCycleDiscounts
 * @since 1.0.0
 */

( function( window ) {
	'use strict';

	// Ensure namespace exists
	window.SCD = window.SCD || {};
	SCD.Modules = SCD.Modules || {};

	/**
	 * Duration Calculator Module
	 */
	SCD.Modules.DurationCalculator = {

		/**
		 * Calculate duration between dates
		 *
		 * @param {string|Date} startDate Start date
		 * @param {string|Date} endDate End date
		 * @param {string} startTime Start time ( optional )
		 * @param {string} endTime End time ( optional )
		 * @returns {object} Duration object
		 */
		calculate: function( startDate, endDate, startTime, endTime ) {
			startTime = startTime || null;
			endTime = endTime || null;

			if ( !startDate || !endDate ) {
				return null;
			}

			// Parse dates
			var start = this.parseDateTime( startDate, startTime );
			var end = this.parseDateTime( endDate, endTime );

			if ( !start || !end ) {
				return null;
			}

			// Calculate difference in milliseconds
			var diffMs = end.getTime() - start.getTime();

			if ( 0 > diffMs ) {
				return null; // End is before start
			}

			// Calculate components
			var days = Math.floor( diffMs / ( 1000 * 60 * 60 * 24 ) );
			var hours = Math.floor( ( diffMs % ( 1000 * 60 * 60 * 24 ) ) / ( 1000 * 60 * 60 ) );
			var minutes = Math.floor( ( diffMs % ( 1000 * 60 * 60 ) ) / ( 1000 * 60 ) );
			var seconds = Math.floor( ( diffMs % ( 1000 * 60 ) ) / 1000 );

			// Total calculations
			var totalDays = diffMs / ( 1000 * 60 * 60 * 24 );
			var totalHours = diffMs / ( 1000 * 60 * 60 );
			var totalMinutes = diffMs / ( 1000 * 60 );

			return {
				days: days,
				hours: hours,
				minutes: minutes,
				seconds: seconds,
				totalDays: totalDays,
				totalHours: totalHours,
				totalMinutes: totalMinutes,
				totalMilliseconds: diffMs,
				isValid: true
			};
		},

		/**
		 * Parse date and time into Date object
		 *
		 * @param {string|Date} date Date string or object
		 * @param {string} time Time string ( e.g., "2:30 PM" )
		 * @returns {Date|null} Parsed date
		 */
		parseDateTime: function( date, time ) {
			time = time || null;
			var dateObj;

			// Handle Date object
			if ( date instanceof Date ) {
				dateObj = new Date( date );
			} else {
				// Parse date string
				dateObj = new Date( date );
			}

			if ( isNaN( dateObj.getTime() ) ) {
				return null;
			}

			// Parse and apply time if provided
			if ( time ) {
				var timeParts = this.parseTime( time );
				if ( timeParts ) {
					dateObj.setHours( timeParts.hours );
					dateObj.setMinutes( timeParts.minutes );
					dateObj.setSeconds( 0 );
					dateObj.setMilliseconds( 0 );
				}
			}

			return dateObj;
		},

		/**
		 * Parse time string
		 *
		 * @param {string} timeString Time string ( e.g., "2:30 PM", "14:30" )
		 * @returns {object | null} Parsed time with hours and minutes
		 */
		parseTime: function( timeString ) {
			if ( !timeString ) {return null;}

			// Try 12-hour format with AM/PM
			var time12Match = timeString.match( /^( \d{1,2} ):( \d{2} )\s*( AM|PM )$/i );
			if ( time12Match ) {
				var hours = parseInt( time12Match[1], 10 );
				var minutes = parseInt( time12Match[2], 10 );
				var ampm = time12Match[3].toUpperCase();

				// Convert to 24-hour format
				if ( 'PM' === ampm && 12 !== hours ) {
					hours += 12;
				} else if ( 'AM' === ampm && 12 === hours ) {
					hours = 0;
				}

				return { hours: hours, minutes: minutes };
			}

			// Try 24-hour format
			var time24Match = timeString.match( /^( \d{1,2} ):( \d{2} )$/ );
			if ( time24Match ) {
				return {
					hours: parseInt( time24Match[1], 10 ),
					minutes: parseInt( time24Match[2], 10 )
				};
			}

			return null;
		},

		/**
		 * Format duration to human-readable string
		 *
		 * @param {object} duration Duration object from calculate()
		 * @param {object} options Formatting options
		 * @returns {string} Formatted duration
		 */
		formatDuration: function( duration, options ) {
			options = options || {};

			if ( !duration || !duration.isValid ) {
				return '';
			}

			var defaults = {
				showDays: true,
				showHours: true,
				showMinutes: true,
				showSeconds: false,
				compact: false,
				maxUnits: 2
			};

			var opts = Object.assign( defaults, options );
			var parts = [];

			if ( opts.showDays && 0 < duration.days ) {
				parts.push(
					opts.compact
						? duration.days + 'd'
						: duration.days + ( 1 === duration.days ? ' ' + wp.i18n.__( 'day', 'smart-cycle-discounts' ) : ' ' + wp.i18n.__( 'days', 'smart-cycle-discounts' ) )
				);
			}

			if ( opts.showHours && 0 < duration.hours && parts.length < opts.maxUnits ) {
				parts.push(
					opts.compact
						? duration.hours + 'h'
						: duration.hours + ( 1 === duration.hours ? ' ' + wp.i18n.__( 'hour', 'smart-cycle-discounts' ) : ' ' + wp.i18n.__( 'hours', 'smart-cycle-discounts' ) )
				);
			}

			if ( opts.showMinutes && 0 < duration.minutes && parts.length < opts.maxUnits ) {
				parts.push(
					opts.compact
						? duration.minutes + 'm'
						: duration.minutes + ( 1 === duration.minutes ? ' ' + wp.i18n.__( 'minute', 'smart-cycle-discounts' ) : ' ' + wp.i18n.__( 'minutes', 'smart-cycle-discounts' ) )
				);
			}

			if ( opts.showSeconds && 0 < duration.seconds && parts.length < opts.maxUnits ) {
				parts.push(
					opts.compact
						? duration.seconds + 's'
						: duration.seconds + ( 1 === duration.seconds ? ' ' + wp.i18n.__( 'second', 'smart-cycle-discounts' ) : ' ' + wp.i18n.__( 'seconds', 'smart-cycle-discounts' ) )
				);
			}

			// Handle edge case of very short durations
			if ( 0 === parts.length ) {
				if ( 1 > duration.totalMinutes ) {
					return wp.i18n.__( 'Less than 1 minute', 'smart-cycle-discounts' );
				} else {
					return wp.i18n.__( '0 minutes', 'smart-cycle-discounts' );
				}
			}

			return parts.join( opts.compact ? ' ' : ', ' );
		},

		/**
		 * Get duration in specific unit
		 *
		 * @param {object} duration Duration object
		 * @param {string} unit Unit ( days, hours, minutes, seconds )
		 * @returns {number} Duration in specified unit
		 */
		getDurationIn: function( duration, unit ) {
			if ( !duration || !duration.isValid ) {
				return 0;
			}

			switch ( unit ) {
				case 'days':
					return duration.totalDays;
				case 'hours':
					return duration.totalHours;
				case 'minutes':
					return duration.totalMinutes;
				case 'seconds':
					return duration.totalMilliseconds / 1000;
				default:
					return duration.totalMilliseconds;
			}
		},

		/**
		 * Validate duration against constraints
		 *
		 * @param {object} duration Duration object
		 * @param {object} constraints Validation constraints
		 * @returns {object} Validation result
		 */
		validateDuration: function( duration, constraints ) {
			constraints = constraints || {};

			var result = {
				valid: true,
				errors: []
			};

			if ( !duration || !duration.isValid ) {
				result.valid = false;
				result.errors.push( wp.i18n.__( 'Invalid duration', 'smart-cycle-discounts' ) );
				return result;
			}

			// Check minimum duration
			if ( constraints.minMinutes && duration.totalMinutes < constraints.minMinutes ) {
				result.valid = false;
				result.errors.push(
					wp.i18n.sprintf( wp.i18n.__( 'Duration must be at least %s minutes', 'smart-cycle-discounts' ), constraints.minMinutes )
				);
			}

			// Check maximum duration
			if ( constraints.maxDays && duration.totalDays > constraints.maxDays ) {
				result.valid = false;
				result.errors.push(
					wp.i18n.sprintf( wp.i18n.__( 'Duration cannot exceed %s days', 'smart-cycle-discounts' ), constraints.maxDays )
				);
			}

			return result;
		},

		/**
		 * Add duration to date
		 *
		 * @param {Date} date Base date
		 * @param {object} duration Duration to add
		 * @returns {Date} New date
		 */
		addDuration: function( date, duration ) {
			var dateUtils = SCD.Shared && SCD.Shared.Utils && SCD.Shared.Utils.Date;
			if ( !dateUtils ) {
				// Fallback implementation
				var result = new Date( date );
				if ( duration.days ) {
					result.setDate( result.getDate() + duration.days );
				}
				if ( duration.hours ) {
					result.setHours( result.getHours() + duration.hours );
				}
				if ( duration.minutes ) {
					result.setMinutes( result.getMinutes() + duration.minutes );
				}
				return result;
			}

			result = new Date( date );
			if ( duration.days ) {
				result = dateUtils.addDays( result, duration.days );
			}
			if ( duration.hours ) {
				result = dateUtils.addHours( result, duration.hours );
			}
			// Add minutes support to dateUtils if needed
			if ( duration.minutes ) {
				result.setMinutes( result.getMinutes() + duration.minutes );
			}

			return result;
		},

		/**
		 * Get human-readable time until date
		 *
		 * @param {Date} date Target date
		 * @returns {string} Time until string
		 */
		getTimeUntil: function( date ) {
			var now = new Date();
			var duration = this.calculate( now, date );

			if ( !duration || 0 > duration.totalMilliseconds ) {
				return wp.i18n.__( 'Already started', 'smart-cycle-discounts' );
			}

			if ( 1 < duration.totalDays ) {
				return wp.i18n.sprintf( wp.i18n.__( 'Starts in %s days', 'smart-cycle-discounts' ), Math.floor( duration.totalDays ) );
			} else if ( 1 < duration.totalHours ) {
				return wp.i18n.sprintf( wp.i18n.__( 'Starts in %s hours', 'smart-cycle-discounts' ), Math.floor( duration.totalHours ) );
			} else if ( 1 < duration.totalMinutes ) {
				return wp.i18n.sprintf( wp.i18n.__( 'Starts in %s minutes', 'smart-cycle-discounts' ), Math.floor( duration.totalMinutes ) );
			} else {
				return wp.i18n.__( 'Starting soon', 'smart-cycle-discounts' );
			}
		}
	};

	// Register module with loader
	if ( SCD.ModuleLoader ) {
		SCD.ModuleLoader.register( 'duration-calculator', SCD.Modules.DurationCalculator );
	}

} )( window );