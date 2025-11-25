/**
 * Input Filter - Real-time input validation and filtering
 *
 * Provides real-time character filtering, range validation, and mobile keyboard
 * optimization for numeric input fields across the plugin.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/shared
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

( function( $, window ) {
	'use strict';

	// Ensure SCD namespace exists
	window.SCD = window.SCD || {};
	window.SCD.Shared = window.SCD.Shared || {};

	/**
	 * Input Filter Module
	 *
	 * Handles real-time input filtering for numeric fields to prevent invalid
	 * character entry, enforce range constraints, and optimize mobile keyboards.
	 *
	 * @since 1.0.0
	 */
	var InputFilter = {

		/**
		 * Initialize the input filter system
		 *
		 * Attaches event handlers to all numeric input fields and provides
		 * real-time validation feedback.
		 *
		 * @since 1.0.0
		 */
		init: function() {
			InputFilter.attachFilters();
			InputFilter.logDebug( 'Input filter system initialized' );
		},

		/**
		 * Attach input filtering event handlers
		 *
		 * Sets up global event delegation for all numeric input fields.
		 * Uses data-input-type attribute to determine filtering strategy.
		 *
		 * @since 1.0.0
		 */
		attachFilters: function() {
			// Integer fields - only digits, no decimals
			$( document ).on( 'input.scd-filter', 'input[data-input-type="integer"]', function() {
				InputFilter.filterInteger.call( this );
			} );

			// Decimal fields - digits and single decimal point
			$( document ).on( 'input.scd-filter', 'input[data-input-type="decimal"]', function() {
				InputFilter.filterDecimal.call( this );
			} );

			// Percentage fields - 0-100 range with validation
			$( document ).on( 'input.scd-filter', 'input[data-input-type="percentage"]', function() {
				InputFilter.filterPercentage.call( this );
			} );

			// Range validation on blur for all numeric fields
			$( document ).on( 'blur.scd-filter', 'input[type="number"]', function() {
				InputFilter.validateRange.call( this );
			} );

			// Prevent mousewheel changes on numeric inputs (UX improvement)
			$( document ).on( 'mousewheel.scd-filter wheel.scd-filter', 'input[type="number"]', function( e ) {
				if ( $( this ).is( ':focus' ) ) {
					e.preventDefault();
				}
			} );
		},

		/**
		 * Filter integer input - allow only digits
		 *
		 * Removes all non-numeric characters from the input value.
		 * Provides visual feedback when characters are filtered.
		 *
		 * @since 1.0.0
		 */
		filterInteger: function() {
			var $field = $( this );
			var value = $field.val();
			var filtered = value.replace( /[^0-9]/g, '' );

			if ( filtered !== value ) {
				$field.val( filtered );
				InputFilter.showFilterFeedback( $field );
			}
		},

		/**
		 * Filter decimal input - allow digits and single decimal point
		 *
		 * Removes invalid characters and ensures only one decimal point.
		 * Limits to 2 decimal places based on step attribute.
		 *
		 * @since 1.0.0
		 */
		filterDecimal: function() {
			var $field = $( this );
			var value = $field.val();
			var step = parseFloat( $field.attr( 'step' ) ) || 0.01;
			var filtered = value;

			// Remove all non-numeric except decimal point
			filtered = filtered.replace( /[^0-9.]/g, '' );

			// Ensure only one decimal point
			var parts = filtered.split( '.' );
			if ( 1 < parts.length ) {
				filtered = parts[0] + '.' + parts.slice( 1 ).join( '' );
			}

			// Limit decimal places based on step
			var decimalPlaces = InputFilter.getDecimalPlaces( step );
			if ( -1 !== filtered.indexOf( '.' ) && 0 < decimalPlaces ) {
				var regex = new RegExp( '^(\\d*\\.\\d{0,' + decimalPlaces + '}).*$' );
				filtered = filtered.replace( regex, '$1' );
			}

			if ( filtered !== value ) {
				$field.val( filtered );
				InputFilter.showFilterFeedback( $field );
			}
		},

		/**
		 * Filter percentage input - delegate to decimal filter
		 *
		 * Percentages are treated as decimals with additional 0-100 range
		 * validation handled in validateRange().
		 *
		 * @since 1.0.0
		 */
		filterPercentage: function() {
			InputFilter.filterDecimal.call( this );
		},

		/**
		 * Validate and clamp input to min/max range
		 *
		 * Automatically adjusts values that exceed the allowed range.
		 * Shows user-friendly notification when clamping occurs.
		 *
		 * @since 1.0.0
		 */
		validateRange: function() {
			var $field = $( this );
			var value = parseFloat( $field.val() );
			var min = parseFloat( $field.attr( 'min' ) );
			var max = parseFloat( $field.attr( 'max' ) );
			var label = $field.attr( 'data-label' ) || 'Value';

			// Skip if value is empty or NaN
			if ( '' === $field.val() || isNaN( value ) ) {
				return;
			}

			// Clamp to minimum
			if ( ! isNaN( min ) && value < min ) {
				$field.val( min );
				InputFilter.clearValidationError( $field );
				InputFilter.showRangeNotification( label, 'minimum', min );
				return;
			}

			// Clamp to maximum
			if ( ! isNaN( max ) && value > max ) {
				$field.val( max );
				InputFilter.clearValidationError( $field );
				InputFilter.showRangeNotification( label, 'maximum', max );
				return;
			}
		},

		/**
		 * Show visual feedback when characters are filtered
		 *
		 * Briefly highlights the field to indicate filtering occurred.
		 *
		 * @since 1.0.0
		 * @param {jQuery} $field The input field
		 */
		showFilterFeedback: function( $field ) {
			$field.addClass( 'scd-input-filtered' );
			setTimeout( function() {
				$field.removeClass( 'scd-input-filtered' );
			}, 300 );
		},

		/**
		 * Show notification when value is clamped to range
		 *
		 * Displays user-friendly message when automatic range adjustment occurs.
		 *
		 * @since 1.0.0
		 * @param {string} label Field label
		 * @param {string} type 'minimum' or 'maximum'
		 * @param {number} value The clamped value
		 */
		showRangeNotification: function( label, type, value ) {
			if ( window.SCD && window.SCD.Shared && window.SCD.Shared.NotificationService ) {
				var message = label + ' adjusted to ' + type + ': ' + value;
				window.SCD.Shared.NotificationService.info( message, 2000 );
			}
		},

		/**
		 * Clear validation error from field
		 *
		 * Removes validation error styling when value is auto-corrected.
		 * Integrates with ValidationError component if available.
		 *
		 * @since 1.0.0
		 * @param {jQuery} $field The input field
		 */
		clearValidationError: function( $field ) {
			// Use ValidationError component if available
			if ( window.SCD && window.SCD.ValidationError ) {
				window.SCD.ValidationError.clear( $field );
			}

			// Fallback: remove error classes manually
			$field.removeClass( 'error' );
			$field.closest( '.scd-field-wrapper' ).removeClass( 'has-error' );
		},

		/**
		 * Get number of decimal places from step value
		 *
		 * Determines how many decimal places are allowed based on
		 * the field's step attribute.
		 *
		 * @since 1.0.0
		 * @param {number} step The step attribute value
		 * @return {number} Number of decimal places
		 */
		getDecimalPlaces: function( step ) {
			if ( ! step || step === Math.floor( step ) ) {
				return 0;
			}

			var stepString = step.toString();
			if ( -1 === stepString.indexOf( '.' ) ) {
				return 0;
			}

			return stepString.split( '.' )[1].length || 0;
		},

		/**
		 * Log debug message
		 *
		 * Logs to console if window.SCD.debug is enabled.
		 *
		 * @since 1.0.0
		 * @param {string} message Debug message
		 */
		logDebug: function( message ) {
			if ( window.SCD && window.SCD.debug ) {
				console.log( '[SCD Input Filter] ' + message );
			}
		}

	};

	// Expose to SCD namespace
	window.SCD.Shared.InputFilter = InputFilter;

	// Auto-initialize on document ready
	$( document ).ready( function() {
		InputFilter.init();
	} );

} )( jQuery, window );
