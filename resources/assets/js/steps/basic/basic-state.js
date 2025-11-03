/**
 * Basic State
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/steps/basic/basic-state.js
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
	SCD.Modules.Basic = SCD.Modules.Basic || {};

	/**
	 * Basic State Constructor
	 * Inherits from BaseState
	 */
	SCD.Modules.Basic.State = function() {
		// Get initial state from field definitions
		var initialState = {};
		var fields = SCD.FieldDefinitions.basic;
		if ( fields ) {
			for ( var fieldName in fields ) {
				if ( Object.prototype.hasOwnProperty.call( fields, fieldName ) ) {
					// Use deepClone if available, otherwise use jQuery extend
					if ( SCD.Utils && SCD.Utils.deepClone ) {
						initialState[fieldName] = SCD.Utils.deepClone( fields[fieldName].default );
					} else {
						initialState[fieldName] = $.extend( true, {}, fields[fieldName].default );
					}
				}
			}
		}

		// Call parent constructor
		SCD.Shared.BaseState.call( this, initialState );

		// Subscribe to our own state changes for validation
		var self = this;
		this.subscribe( function( changes ) {
			if ( changes.property && window.SCD && window.SCD.ValidationManager ) {
				// Validate using ValidationManager directly
				var result = window.SCD.ValidationManager.validateField(
					changes.property,
					changes.newValue,
					{
						stepId: 'basic',
						allValues: self.getState(),
						visibilityMap: null
					}
				);

				if ( !result.ok && result.errors && 0 < result.errors.length ) {
					self._validationErrors = self._validationErrors || {};
					// Handle new error format with code and message
					var error = result.errors[0];
					self._validationErrors[changes.property] = error.message || error;
				} else if ( self._validationErrors && self._validationErrors[changes.property] ) {
					delete self._validationErrors[changes.property];
				}
			}
		} );
	};

	// Set up proper prototype chain
	SCD.Modules.Basic.State.prototype = Object.create( SCD.Shared.BaseState.prototype );
	SCD.Modules.Basic.State.prototype.constructor = SCD.Modules.Basic.State;

	// Add basic step specific methods
	$.extend( SCD.Modules.Basic.State.prototype, {

		/**
		 * Validate all data using ValidationManager
		 */
		validate: function() {
			var state = this.getState();
			var validation = { valid: true, errors: {} };

			if ( window.SCD && window.SCD.ValidationManager ) {
				// Create a temporary form element with the data
				var $form = $( '<form>' );
				for ( var fieldName in state ) {
					if ( Object.prototype.hasOwnProperty.call( state, fieldName ) ) {
						$form.append(
							$( '<input>' )
								.attr( 'name', fieldName )
								.val( state[fieldName] )
						);
					}
				}

				// Use ValidationManager to validate
				validation = window.SCD.ValidationManager.validateForm( $form, {
					stepId: 'basic',
					allValues: state,
					visibilityMap: null
				} );

				// Clean up temporary form
				$form.remove();
			}

			this._validationErrors = validation.errors;

			// Log validation if debug enabled  
			if ( ! validation.ok && window.SCD && window.SCD.ErrorHandler ) {
				SCD.ErrorHandler.handle(
					new Error( 'Basic step validation failed' ),
					'BasicState.validate',
					SCD.ErrorHandler.SEVERITY.LOW,
					{ errors: this._validationErrors }
				);
			}

			return validation.ok;
		},

		/**
		 * Get validation errors
		 */
		getErrors: function() {
			return this._validationErrors || {};
		},

		/**
		 * Clear validation errors
		 */
		clearErrors: function() {
			this._validationErrors = {};
		},

		/**
		 * Set data with optional silent mode
		 * Overrides parent to maintain compatibility
		 * @param updates
		 * @param silent
		 */
		setData: function( updates, silent ) {
			if ( 'boolean' === typeof silent && silent ) {
				// Temporarily disable notifications
				var subscribers = this._subscribers;
				this._subscribers = [];
				this.setState( updates, true );
				this._subscribers = subscribers;
			} else {
				this.setState( updates, false );
			}
		},

		/**
		 * Get data
		 * Wrapper for getState to maintain compatibility
		 * @param key
		 */
		getData: function( key ) {
			var state = this.getState();
			return key ? state[key] : state;
		},

		/**
		 * Override reset to use field definition defaults
		 */
		reset: function() {
			var defaults = {};
			var fields = SCD.FieldDefinitions.basic;
			if ( fields ) {
				for ( var fieldName in fields ) {
					if ( Object.prototype.hasOwnProperty.call( fields, fieldName ) ) {
						// Use deepClone if available, otherwise use jQuery extend
						if ( SCD.Utils && SCD.Utils.deepClone ) {
							defaults[fieldName] = SCD.Utils.deepClone( fields[fieldName].default );
						} else {
							defaults[fieldName] = $.extend( true, {}, fields[fieldName].default );
						}
					}
				}
			}
			SCD.Shared.BaseState.prototype.reset.call( this, defaults );
			this._validationErrors = {};
		}
	} );

} )( jQuery );