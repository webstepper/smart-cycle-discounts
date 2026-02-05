/**
 * Basic Fields
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/steps/basic/basic-fields.js
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress/plugins/smart-cycle-discounts/
 * @since      1.0.0
 */

( function( $ ) {
	'use strict';

	// Register module using utility
	WSSCD.Utils.registerModule( 'WSSCD.Modules.Basic', 'Fields', function( state ) {
		this.state = state;

		this.initEventManager();

		if ( !WSSCD.Utils.ensureInitialized( this, {
			'state': this.state
		}, 'BasicFields' ) ) {
			return;
		}
	} );

	WSSCD.Modules.Basic.Fields.prototype = {
		/**
		 * Initialize field handlers
		 */
		init: function() {
			this.bindFieldEvents();
			this.setupValidation();
		},

		/**
		 * Bind field events using event manager
		 */
		bindFieldEvents: function() {
			var self = this;

			var fieldDefs = WSSCD.FieldDefinitions.basic || {};

			// Campaign name field
			if ( fieldDefs.name && fieldDefs.name.selector ) {
				this.bindDelegatedEvent( document, fieldDefs.name.selector, 'input',
					WSSCD.Utils.debounce( function( e ) {
						self.handleNameChange( e );
					}, 300 )
				);
			}

			// Description field
			if ( fieldDefs.description && fieldDefs.description.selector ) {
				this.bindDelegatedEvent( document, fieldDefs.description.selector, 'input', function( e ) {
					self.handleDescriptionChange( e );
				} );
			}

			// Priority field
			if ( fieldDefs.priority && fieldDefs.priority.selector ) {
				this.bindDelegatedEvent( document, fieldDefs.priority.selector, 'change', function( e ) {
					self.handlePriorityChange( e );
				} );
			}
		},

		/**
		 * Setup validation
		 */
		setupValidation: function() {
			// Validation is handled by orchestrator's StepPersistence mixin
			// No duplicate validation logic needed here
		},

		/**
		 * Handle campaign name changes - delegates to ValidationManager
		 * @param e
		 */
		handleNameChange: function( e ) {
			var $field = $( e.target );
			var value = $field.val().trim();

			// Use ValidationManager for field validation
			if ( window.WSSCD && window.WSSCD.ValidationManager ) {
				window.WSSCD.ValidationManager.validateFieldDebounced( e.target, {
					stepId: 'basic'
				} );
			}

			// Emit event for orchestrator to handle state update
			this.triggerCustomEvent( 'wsscd:basic:field:changed', [ {
				field: 'name',
				value: value
			} ] );

			if ( 3 <= value.length ) {
				this.checkNameUniqueness( value );
			}
		},

		/**
		 * Check campaign name uniqueness
		 * @param name
		 */
		checkNameUniqueness: function( name ) {
			var self = this;
			// Use field definitions for selector
			var fieldDef = WSSCD.FieldDefinitions.basic && WSSCD.FieldDefinitions.basic.name;
			var $field = fieldDef && fieldDef.selector ? $( fieldDef.selector ) : $( '[name="name"]' );

			// Ensure API is available
			if ( ! window.WSSCD || ! window.WSSCD.Modules || ! window.WSSCD.Modules.Basic || ! window.WSSCD.Modules.Basic.API ) {
				return;
			}

			// Cancel any pending request
			if ( this.nameCheckRequest && this.nameCheckRequest.abort ) {
				this.nameCheckRequest.abort();
			}

			var api = new WSSCD.Modules.Basic.API();
			var excludeId = this.state.getData( 'id' );

			this.nameCheckRequest = api.checkCampaignName( name, excludeId )
				.done( function( response ) {
					// Only process if this is still the current name
					var currentName = self.state.getData( 'name' );
					if ( currentName === name ) {
						if ( ! response.unique ) {
							// Use centralized message from constants
							var message = window.WSSCD && window.WSSCD.Constants && window.WSSCD.Constants.Validation ?
								window.WSSCD.Constants.Validation.DEFAULT_MESSAGES.DUPLICATE_NAME :
								'A campaign with this name already exists';
							// Use ValidationError component directly
							if ( window.WSSCD && window.WSSCD.ValidationError ) {
								window.WSSCD.ValidationError.show( $field, message );
							}
						} else {
							// Use ValidationError component directly
							if ( window.WSSCD && window.WSSCD.ValidationError ) {
								window.WSSCD.ValidationError.clear( $field );
							}
						}
					}
				} )
				.fail( function( jqXHR ) {
					// Ignore aborted requests
					if ( 'abort' !== jqXHR.statusText ) {
						// Error already handled by ErrorHandler
					}
				} )
				.always( function() {
					self.nameCheckRequest = null;
				} );
		},

		/**
		 * Handle description changes - delegates to ValidationManager
		 * @param e
		 */
		handleDescriptionChange: function( e ) {
			var $field = $( e.target );
			var value = $field.val().trim();

			// Use ValidationManager for field validation
			if ( window.WSSCD && window.WSSCD.ValidationManager ) {
				window.WSSCD.ValidationManager.validateFieldDebounced( e.target, {
					stepId: 'basic'
				} );
			}

			// Emit event for orchestrator to handle state update
			this.triggerCustomEvent( 'wsscd:basic:field:changed', [ {
				field: 'description',
				value: value
			} ] );
		},

		/**
		 * Handle priority changes - delegates to ValidationManager
		 * @param e
		 */
		handlePriorityChange: function( e ) {
			var $field = $( e.target );
			var value = parseInt( $field.val() );

			// Use ValidationManager for field validation
			if ( window.WSSCD && window.WSSCD.ValidationManager ) {
				window.WSSCD.ValidationManager.validateFieldDebounced( e.target, {
					stepId: 'basic'
				} );
			}

			// Emit event for orchestrator to handle state update
			this.triggerCustomEvent( 'wsscd:basic:field:changed', [ {
				field: 'priority',
				value: value
			} ] );
		},

		// validateAllFields method removed - handled by orchestrator's StepPersistence mixin
		// This ensures single source of truth for validation through ValidationManager

		/**
		 * Show field error or warning
		 * @param $field
		 * @param message
		 * @param severity
		 */
		showFieldError: function( $field, message, severity ) {
			severity = severity || 'error';

			if ( window.WSSCD && window.WSSCD.ValidationError ) {
				window.WSSCD.ValidationError.show( $field, message, {
					type: severity,
					animate: true
				} );
			} else {
				console.error( '[BasicFields] ValidationError component not available' );
			}

			// Log actual errors
			if ( 'error' === severity && window.WSSCD && window.WSSCD.ErrorHandler ) {
				WSSCD.ErrorHandler.handle(
					new Error( 'Field validation error: ' + message ),
					'BasicFields.showFieldError',
					WSSCD.ErrorHandler.SEVERITY.LOW,
					{ field: $field.attr( 'name' ), message: message }
				);
			}
		},

		/**
		 * Clear field error using ValidationError component
		 * @param $field
		 */
		clearFieldError: function( $field ) {
			if ( window.WSSCD && window.WSSCD.ValidationError ) {
				window.WSSCD.ValidationError.clear( $field );
			}
		},

		// populateFields method removed - handled by orchestrator's StepPersistence mixin
		// This ensures single source of truth for field population

		// getFieldValues method removed - handled by orchestrator's StepPersistence mixin
		// This ensures single source of truth for field collection

		/**
		 * Reset all fields
		 */
		resetFields: function() {
			$( '[name="name"]' ).val( '' ).trigger( 'change' );
			$( '[name="description"]' ).val( '' ).trigger( 'change' );
			$( '[name="priority"]' ).val( 3 ).trigger( 'change' );

			// Also reset using Utils.Fields if available for consistency
			if ( window.WSSCD && window.WSSCD.Utils && window.WSSCD.Utils.Fields ) {
				// Emit events for orchestrator to handle
				this.triggerCustomEvent( 'wsscd:basic:field:changed', [ {
					field: 'name',
					value: ''
				} ] );
				this.triggerCustomEvent( 'wsscd:basic:field:changed', [ {
					field: 'description',
					value: ''
				} ] );
				this.triggerCustomEvent( 'wsscd:basic:field:changed', [ {
					field: 'priority',
					value: 5
				} ] );
			}

			if ( window.WSSCD && window.WSSCD.ValidationError ) {
				var $container = $( '[name="name"]' ).closest( 'form' );
				if ( $container.length ) {
					window.WSSCD.ValidationError.clearAll( $container );
				}
			}
		},

		/**
		 * Cleanup
		 */
		destroy: function() {
			// Cancel any pending name check request
			if ( this.nameCheckRequest && this.nameCheckRequest.abort ) {
				this.nameCheckRequest.abort();
			}

			this.unbindAllEvents();
			this.state = null;
		}
	};

	// Mix in event manager functionality
	WSSCD.Utils.extend( WSSCD.Modules.Basic.Fields.prototype, WSSCD.Mixins.EventManager );

} )( jQuery );