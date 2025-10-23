/**
 * Description Module
 *
 * Handles campaign description input and validation
 *
 * @param $
 * @package SmartCycleDiscounts
 * @since 1.0.0
 */

( function( $ ) {
	'use strict';

	window.SCD = window.SCD || {};
	SCD.Modules = SCD.Modules || {};
	SCD.Modules.Basic = SCD.Modules.Basic || {};

	/**
	 * Description Handler
	 *
	 * @param state
	 * @class SCD.Modules.Basic.Description
	 */
	SCD.Modules.Basic.Description = function( state ) {
		this.state = state;
		this.elements = {};
		this.maxLength = 1000; // Match HTML maxlength attribute
	};

	/**
	 * Initialize module
	 */
	SCD.Modules.Basic.Description.prototype.init = function() {
		this.cacheElements();
		this.bindEvents();
		this.loadInitialValue();
		this.initCharacterCounter();
	};

	/**
	 * Cache DOM elements
	 */
	SCD.Modules.Basic.Description.prototype.cacheElements = function() {
		this.elements = {
			$textarea: $( '[name="description"]' ),
			$container: $( '[name="description"]' ).closest( '.scd-field-container' )
		};
	};

	/**
	 * Bind event handlers
	 */
	SCD.Modules.Basic.Description.prototype.bindEvents = function() {
		var self = this;

		// Description input
		this.elements.$textarea.on( 'input', function( _e ) {
			self.handleDescriptionInput( $( this ) );
		} );

		// Listen to state changes
		$( document ).on( 'scd:basic:state:changed', function( e, data ) {
			if ( 'description' === data.property ) {
				self.updateUI( data.value );
			}
		} );
	};

	/**
	 * Load initial value
	 */
	SCD.Modules.Basic.Description.prototype.loadInitialValue = function() {
		var currentDescription = this.state.getState().description;
		if ( currentDescription ) {
			this.elements.$textarea.val( currentDescription );
			this.updateCharacterCount();
		}
	};

	/**
	 * Initialize character counter
	 */
	SCD.Modules.Basic.Description.prototype.initCharacterCounter = function() {
		// Add counter element if not exists
		if ( !this.elements.$container.find( '.scd-char-counter' ).length ) {
			this.elements.$container.append( '<div class="scd-char-counter">' +
                    '<span class="current">0</span> / <span class="max">' + this.maxLength + '</span> characters' +
                '</div>' );
		}

		this.elements.$counter = this.elements.$container.find( '.scd-char-counter' );
		this.updateCharacterCount();
	};

	/**
	 * Handle description input
	 * @param $textarea
	 */
	SCD.Modules.Basic.Description.prototype.handleDescriptionInput = function( $textarea ) {
		var value = $textarea.val();

		// Update state
		this.state.setState( { description: value } );

		// Update character count
		this.updateCharacterCount();

		// Validate
		this.validateDescription( value );
	};

	/**
	 * Update character count
	 */
	SCD.Modules.Basic.Description.prototype.updateCharacterCount = function() {
		var currentLength = this.elements.$textarea.val().length;

		if ( this.elements.$counter ) {
			this.elements.$counter.find( '.current' ).text( currentLength );

			// Add warning class if near limit
			if ( currentLength > this.maxLength * 0.9 ) {
				this.elements.$counter.addClass( 'warning' );
			} else {
				this.elements.$counter.removeClass( 'warning' );
			}

			// Add error class if over limit
			if ( currentLength > this.maxLength ) {
				this.elements.$counter.addClass( 'error' );
			} else {
				this.elements.$counter.removeClass( 'error' );
			}
		}
	};

	/**
	 * Validate description field
	 * @param value
	 */
	SCD.Modules.Basic.Description.prototype.validateDescription = function( value ) {
		// Clear previous errors
		this.clearValidation();

		if ( value ) {
			// Check length
			if ( value.length > this.maxLength ) {
				this.showError( 'Description cannot exceed ' + this.maxLength + ' characters' );
				return false;
			}

			// Check for potentially harmful content
			if ( /<script|<iframe|javascript:/i.test( value ) ) {
				this.showError( 'Description cannot contain scripts or iframes' );
				return false;
			}

			// Warn about URLs
			if ( /https?:\/\//i.test( value ) ) {
				this.showWarning( 'URLs in descriptions may not be clickable' );
			}
		}

		return true;
	};

	/**
	 * Update UI based on state
	 * @param value
	 */
	SCD.Modules.Basic.Description.prototype.updateUI = function( value ) {
		if ( this.elements.$textarea.val() !== value ) {
			this.elements.$textarea.val( value );
			this.updateCharacterCount();
		}
	};

	/**
	 * Clear validation messages
	 */
	SCD.Modules.Basic.Description.prototype.clearValidation = function() {
		// Use centralized ValidationError component
		if ( window.SCD && window.SCD.ValidationError ) {
			window.SCD.ValidationError.clear( this.elements.$textarea );
		}
		// Clear warnings (not handled by ValidationError)
		this.elements.$container.find( '.scd-field-warning' ).remove();
	};

	/**
	 * Show error message
	 * @param message
	 */
	SCD.Modules.Basic.Description.prototype.showError = function( message ) {
		this.clearValidation();
		// Use centralized ValidationError component
		if ( window.SCD && window.SCD.ValidationError ) {
			window.SCD.ValidationError.show( this.elements.$textarea, message );
		}
	};

	/**
	 * Show warning message
	 * @param message
	 */
	SCD.Modules.Basic.Description.prototype.showWarning = function( message ) {
		this.elements.$container.find( '.scd-field-warning' ).remove();
		// Create element safely with .text() to prevent XSS
		var $warning = $( '<div class="scd-field-warning"></div>' ).text( message );
		this.elements.$container.append( $warning );

		// Auto-hide after 5 seconds
		setTimeout( function() {
			$warning.fadeOut( function() { $warning.remove(); } );
		}, 5000 );
	};

	/**
	 * Validate module
	 */
	SCD.Modules.Basic.Description.prototype.validate = function() {
		var description = this.state.getState().description;
		var errors = [];

		if ( description ) {
			// Check length
			if ( description.length > this.maxLength ) {
				errors.push( {
					field: 'description',
					message: 'Description cannot exceed ' + this.maxLength + ' characters'
				} );
			}

			// Check for harmful content
			if ( /<script|<iframe|javascript:/i.test( description ) ) {
				errors.push( {
					field: 'description',
					message: 'Description cannot contain scripts or iframes'
				} );
			}
		}

		return {
			valid: 0 === errors.length,
			errors: errors
		};
	};

	/**
	 * Destroy module
	 */
	SCD.Modules.Basic.Description.prototype.destroy = function() {
		this.elements.$textarea.off( 'input' );
		$( document ).off( 'scd:basic:state:changed' );
	};

} )( jQuery );