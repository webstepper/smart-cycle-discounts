/**
 * Basic State
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/steps/basic/basic-state.js
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
	WSSCD.Modules = WSSCD.Modules || {};
	WSSCD.Modules.Basic = WSSCD.Modules.Basic || {};

	/**
	 * Basic State Constructor
	 * Inherits from BaseState
	 */
	WSSCD.Modules.Basic.State = function() {
		var initialState = {};
		var fields = WSSCD.FieldDefinitions.basic;
		if ( fields ) {
			for ( var fieldName in fields ) {
				if ( Object.prototype.hasOwnProperty.call( fields, fieldName ) ) {
					// Use deepClone if available, otherwise use jQuery extend
					if ( WSSCD.Utils && WSSCD.Utils.deepClone ) {
						initialState[fieldName] = WSSCD.Utils.deepClone( fields[fieldName].default );
					} else {
						initialState[fieldName] = $.extend( true, {}, fields[fieldName].default );
					}
				}
			}
		}

		// Call parent constructor
		WSSCD.Shared.BaseState.call( this, initialState );

		// Subscribe to our own state changes for validation
		var self = this;
		this.subscribe( function( changes ) {
			if ( changes.property && window.WSSCD && window.WSSCD.ValidationManager ) {
				var result = window.WSSCD.ValidationManager.validateField(
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

	WSSCD.Modules.Basic.State.prototype = Object.create( WSSCD.Shared.BaseState.prototype );
	WSSCD.Modules.Basic.State.prototype.constructor = WSSCD.Modules.Basic.State;

	$.extend( WSSCD.Modules.Basic.State.prototype, {

		/**
		 * Validate all data using ValidationManager
		 */
		validate: function() {
			var state = this.getState();
			var validation = { valid: true, errors: {} };

			if ( window.WSSCD && window.WSSCD.ValidationManager ) {
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
				validation = window.WSSCD.ValidationManager.validateForm( $form, {
					stepId: 'basic',
					allValues: state,
					visibilityMap: null
				} );

				// Clean up temporary form
				$form.remove();
			}

			this._validationErrors = validation.errors;

			// Log validation if debug enabled  
			if ( ! validation.ok && window.WSSCD && window.WSSCD.ErrorHandler ) {
				WSSCD.ErrorHandler.handle(
					new Error( 'Basic step validation failed' ),
					'BasicState.validate',
					WSSCD.ErrorHandler.SEVERITY.LOW,
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
			var fields = WSSCD.FieldDefinitions.basic;
			if ( fields ) {
				for ( var fieldName in fields ) {
					if ( Object.prototype.hasOwnProperty.call( fields, fieldName ) ) {
						// Use deepClone if available, otherwise use jQuery extend
						if ( WSSCD.Utils && WSSCD.Utils.deepClone ) {
							defaults[fieldName] = WSSCD.Utils.deepClone( fields[fieldName].default );
						} else {
							defaults[fieldName] = $.extend( true, {}, fields[fieldName].default );
						}
					}
				}
			}
			WSSCD.Shared.BaseState.prototype.reset.call( this, defaults );
			this._validationErrors = {};
		}
	} );

} )( jQuery );