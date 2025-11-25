/**
 * Step Persistence
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/shared/mixins/step-persistence.js
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

( function( $ ) {
	'use strict';

	/**
	 * Step persistence mixin
	 *
	 * Requirements for orchestrators using this mixin:
	 * - Must call initPersistence() in init()
	 * - Must have modules.state (BaseState instance)
	 * - Must have triggerCustomEvent() from EventManager
	 * - Field definitions must exist for the step
	 * 
	 * @since 1.0.0
	 */
	SCD.Mixins.StepPersistence = {

		/**
		 * Track if we're currently updating state to prevent recursion
		 */
		_isUpdatingState: false,

		/**
		 * Initialize persistence for a step
		 *
		 * @since 1.0.0
		 * @param {string} stepName The step identifier
		 * @returns {void}
		 */
		initPersistence: function( stepName ) {
			if ( 'string' !== typeof stepName ) {
				throw new Error( 'Step name must be a string' );
			}

			this.stepName = stepName;

			// CRITICAL: These must be instance properties, not prototype properties
			// to avoid sharing handlers between different step orchestrators
			// Don't overwrite if already initialized (by custom init before initPersistence)
			this._complexFieldHandlers = this._complexFieldHandlers || {};
			this._complexFieldQueue = this._complexFieldQueue || {};

			if ( this.modules && this.modules.state ) {
				var currentState = this.modules.state.getState();
				if ( ( SCD.Utils && SCD.Utils.isEmpty && SCD.Utils.isEmpty( currentState ) ) || $.isEmptyObject( currentState ) ) {
					var fields = SCD.FieldDefinitions.getStepFields( stepName );
					if ( fields ) {
						var defaults = {};
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
						this._isUpdatingState = true;
						this.modules.state.setState( defaults );
						this._isUpdatingState = false;
					} else {
						if ( window.scdDebugPersistence ) {
						}
					}
				}
			}

			// Generate unique namespace for this instance
			this._persistenceNamespace = '.persistence-' + stepName + '-' + Date.now();

			// Listen for save requests with namespaced events
			var self = this;
			this.bindCustomEvent( 'scd:wizard:save-step' + this._persistenceNamespace, function( event, step ) {
				if ( step === self.stepName ) {
					self.saveStep();
				}
			} );

			// Listen for validation requests with namespaced events
			this.bindCustomEvent( 'scd:wizard:validate-step' + this._persistenceNamespace, function( event, step ) {
				if ( step === self.stepName ) {
					self.validateStep();
				}
			} );
		},

		/**
		 * Collect data from fields using field definitions
		 * Enhanced to support complex field handlers
		 * 
		 * @since 1.0.0
		 * @returns {object} Collected data or error object
		 */
		collectData: function() {
			try {
				if ( ! this._hasFieldDefinitions() ) {
					return this._createErrorResponse( 'Field definitions not available' );
				}

				// Verify required dependencies are loaded (fail loudly if missing)
				if ( ! window.SCD || ! window.SCD.Utils || ! window.SCD.Utils.Fields ) {
					console.error( '[StepPersistence] CRITICAL: SCD.Utils.Fields not loaded for step:', this.stepName );
					console.error( '[StepPersistence] This indicates a script dependency issue. Check script-registry.php.' );
					return this._createErrorResponse( 'Required dependencies (SCD.Utils.Fields) not loaded. Cannot collect field data.' );
				}

				var fieldDefs = window.SCD.FieldDefinitions.getStepFields( this.stepName ) || {};

				var data = {};

				// Collect each field
				for ( var fieldName in fieldDefs ) {
					if ( Object.prototype.hasOwnProperty.call( fieldDefs, fieldName ) ) {
						var fieldDef = fieldDefs[fieldName];

						// Skip fields that don't meet their conditional visibility requirements
						if ( ! this._isFieldVisible( fieldDef, data ) ) {
							continue;
						}

						if ( 'complex' === fieldDef.type ) {
							// Handle complex fields using handler
							data[fieldName] = this.collectComplexField( fieldDef );
						} else {
							// SCD.Utils.Fields is guaranteed to be loaded (verified above)
							data[fieldName] = window.SCD.Utils.Fields.getFieldValue( fieldName, fieldDef );
						}
					}
				}

				// Sanitize the collected data
				var sanitizedData = data;
				if ( window.SCD && window.SCD.ValidationManager && 'function' === typeof window.SCD.ValidationManager.sanitizeFieldValue ) {
					var fields = SCD.FieldDefinitions.getStepFields( this.stepName );
					if ( fields ) {
						sanitizedData = {};
						for ( var fieldKey in fields ) {
							if ( Object.prototype.hasOwnProperty.call( fields, fieldKey ) && Object.prototype.hasOwnProperty.call( data, fieldKey ) ) {
								sanitizedData[fieldKey] = window.SCD.ValidationManager.sanitizeFieldValue( data[fieldKey], fields[fieldKey].type );
							}
						}
					}
				}

				// Return sanitized field data in camelCase format
				// AJAX router will automatically convert camelCase to snake_case for PHP backend
				return sanitizedData;
			} catch ( error ) {
				console.error( '[StepPersistence] Error collecting data:', error );
				// Return error indicator instead of stale data
				return { _error: true, _message: error.message || 'Unknown error collecting data' };
			}
		},

		/**
		 * Validate data using pure ValidationManager
		 * Uses the unified validation system with visibility awareness
		 * @param data
		 */
		validateData: function( data ) {
			if ( window.scdDebugPersistence ) {
			}
			try {
				if ( data && data._error ) {
					return {
						valid: false,
						errors: [ {
							field: 'system',
							message: data._message || 'Data collection failed'
						} ]
					};
				}

				if ( !window.SCD || !window.SCD.ValidationManager ) {
					if ( window.scdDebugPersistence ) {
					}
					return {
						valid: true,
						errors: []
					};
				}

				// Use pure ValidationManager.validateStep method
				var result = window.SCD.ValidationManager.validateStep( this.stepName, data );
				
				// Convert ValidationManager format { ok: ..., errors: {...} } to orchestrator format { valid: ..., errors: [...] }
				// This adapter layer maintains the public interface used by all orchestrators
				var errors = [];
				if ( !result.ok ) {
					for ( var fieldName in result.errors ) {
						if ( result.errors.hasOwnProperty( fieldName ) ) {
							var fieldErrors = result.errors[fieldName];
							// Handle array of error objects with codes and messages
							if ( Array.isArray( fieldErrors ) ) {
								for ( var i = 0; i < fieldErrors.length; i++ ) {
									errors.push( {
										field: fieldName,
										message: fieldErrors[i].message || fieldErrors[i]
									} );
								}
							} else {
								errors.push( {
									field: fieldName,
									message: fieldErrors.message || fieldErrors
								} );
							}
						}
					}
				}

				return {
					valid: result.ok,
					errors: errors,
					clean: result.clean
				};
			} catch ( error ) {
				if ( window.SCD && window.SCD.ErrorHandler ) {
					window.SCD.ErrorHandler.handle( error, 'StepPersistence.validateData' );
				}
				return this._createValidationErrorResult( 'system', 'Validation error: ' + ( error.message || 'Unknown error' ) );
			}
		},

		/**
		 * Populate fields from saved data - Enhanced with standard wrapper logic
		 * This method now includes all standard pre-checks, error handling, and logging
		 * that was previously duplicated in each orchestrator.
		 *
		 * Orchestrators can override onPopulateFieldsComplete() for custom post-population logic
		 *
		 * @param {object} data - Data to populate fields with
		 * @returns {void}
		 */
		populateFields: function( data ) {
			// Standard pre-check: Skip if no data provided

			if ( !data || 'object' !== typeof data ) {
				return;
			}

			// Standard pre-check: Skip if data is empty (using SCD.Utils.isEmpty if available)
			if ( window.SCD && window.SCD.Utils && window.SCD.Utils.isEmpty && window.SCD.Utils.isEmpty( data ) ) {
				return;
			}

			// Standard pre-check: Skip population on initial page load with intent=new
			// This prevents overwriting empty form when creating a new campaign
			var urlParams = new URLSearchParams( window.location.search );
			var intent = urlParams.get( 'intent' );
			if ( 'new' === intent ) {
				return;
			}

			// Wrap everything in try-catch for consistent error handling
			try {
				// Standard debug logging
				if ( window.scdDebugWizard ) {
					scdDebugWizard( this.stepName, 'populate_fields', {
						dataKeys: Object.keys( data ),
						hasData: !!data
					} );
				}

				// Load data into state module (data is already in camelCase from field definitions)
				if ( this.modules && this.modules.state && !this._isUpdatingState ) {
					this._isUpdatingState = true;
					// Use fromJSON() if available (for custom data normalization), otherwise setState()
					if ( 'function' === typeof this.modules.state.fromJSON ) {
						this.modules.state.fromJSON( data );
					} else {
						this.modules.state.setState( data );
					}
					this._isUpdatingState = false;
				}

				if ( !window.SCD || !window.SCD.FieldDefinitions || !window.SCD.FieldDefinitions.getStepFields ) {
					return;
				}

				var fieldDefs = window.SCD.FieldDefinitions.getStepFields( this.stepName );
				if ( !fieldDefs ) {
					return;
				}

				// Populate each field from definitions
				for ( var fieldName in fieldDefs ) {
					if ( ! Object.prototype.hasOwnProperty.call( fieldDefs, fieldName ) ) {
						continue;
					}

					var fieldDef = fieldDefs[fieldName];

					// Data from Asset Localizer uses camelCase keys (snake_to_camel_keys conversion)
					// So prefer camelCase key (fieldName) for data lookups, fall back to snake_case if needed
					var dataKey = Object.prototype.hasOwnProperty.call( data, fieldName ) ? fieldName : ( fieldDef.fieldName || fieldName );

					if ( Object.prototype.hasOwnProperty.call( data, dataKey ) ) {
						// Skip fields that don't meet their conditional visibility requirements
						if ( ! this._isFieldVisible( fieldDef, data ) ) {
							continue;
						}

						if ( 'complex' === fieldDef.type ) {
							// Handle complex fields using handler
							// Handle complex fields using handler
							this.populateComplexField( fieldDef, data[dataKey] );
						} else if ( window.SCD && window.SCD.Utils && window.SCD.Utils.Fields ) {
							// Handle standard fields
							window.SCD.Utils.Fields.setFieldValue( fieldName, data[dataKey], fieldDef );
						}
					}
				}

				// Hook for custom post-population logic (orchestrators can override this)
				if ( 'function' === typeof this.onPopulateFieldsComplete ) {
					this.onPopulateFieldsComplete( data );
				}

				// Trigger populated event
				this.triggerCustomEvent( 'scd:' + this.stepName + ':populated', [ data ] );

			} catch ( error ) {
				// Standard error handling
				if ( window.SCD && window.SCD.ErrorHandler ) {
					window.SCD.ErrorHandler.handle( error, this.stepName + '-populate-fields' );
				} else {
					console.error( '[StepPersistence] Error populating fields for ' + this.stepName + ':', error );
				}

				if ( 'function' === typeof this.showError ) {
					this.showError( 'Failed to load saved ' + this.stepName + ' data.', 'error', 5000 );
				}
			}
		},

		/**
		 * Hook for custom field population logic
		 * Override this in orchestrators to add step-specific UI updates after field population
		 *
		 * @param {object} data - The populated data
		 * @returns {void}
		 */
		onPopulateFieldsComplete: function( data ) {
			// Override in orchestrators for custom post-population logic
			// Example: Update UI state, trigger animations, update derived fields, etc.
		},

		/**
		 * Get step data from state
		 */
		getData: function() {
			if ( this.modules && this.modules.state ) {
				return this.modules.state.getState();
			}
			return {};
		},

		/**
		 * Set step data
		 * @param data
		 */
		setData: function( data ) {
			this.populateFields( data );
		},

		/**
		 * Save step data
		 *
		 * @returns {jQuery.Promise}
		 */
		saveStep: function() {
			var self = this;

			var data = this.collectData();

			if ( data && data._error ) {
				var errors = [ { field: 'system', message: data._message || 'Failed to collect data' } ];
				this.showErrors( errors );
				this.triggerCustomEvent( 'scd:' + this.stepName + ':save-failed', [ errors ] );
				return $.Deferred().reject( errors ).promise();
			}

			var validation = this.validateData( data );

			if ( !validation.valid ) {
				this.showErrors( validation.errors );
				this.triggerCustomEvent( 'scd:' + this.stepName + ':save-failed', [ validation.errors ] );
				return $.Deferred().reject( validation.errors ).promise();
			}

			return SCD.Ajax.post( 'scd_save_step', {
				step: this.stepName,
				data: data
			} ).done( function( response ) {
				// This ensures that when navigating back to this step, the updated data is used
				// instead of the original data loaded from PHP on page load
				if ( window.SCD && window.SCD.Wizard && window.SCD.Wizard.modules && window.SCD.Wizard.modules.stateManager ) {
					var currentStepData = window.SCD.Wizard.modules.stateManager.get( 'stepData' ) || {};
					currentStepData[self.stepName] = data;
					window.SCD.Wizard.modules.stateManager.set( 'stepData', currentStepData );
				}

				// CRITICAL FIX: Update window.scdWizardData to prevent stale data from being re-used
				// window.scdWizardData contains PHP-localized data from initial page load
				// After save, this data becomes stale. We must update it with the latest saved data
				// to ensure that any future step initializations use fresh data
				if ( window.scdWizardData && window.scdWizardData.currentCampaign ) {
					if ( ! window.scdWizardData.currentCampaign[self.stepName] ) {
						window.scdWizardData.currentCampaign[self.stepName] = {};
					}
					// Deep copy to prevent reference issues
					window.scdWizardData.currentCampaign[self.stepName] = $.extend( true, {}, data );
				}

				self.triggerCustomEvent( 'scd:' + self.stepName + ':saved', [ response ] );
			} ).fail( function( xhr ) {
				SCD.ErrorHandler.handleAjaxError( xhr, 'scd_save_step', { step: self.stepName } );
				self.triggerCustomEvent( 'scd:' + self.stepName + ':save-failed', [ xhr ] );
			} );
		},

		/**
		 * Validate step
		 *
		 * @returns {Promise} Promise that resolves to boolean (true if valid)
		 */
		validateStep: function() {
			var deferred = $.Deferred();

			try {
				var data = this.collectData();

				if ( data && data._error ) {
					var errors = [ { field: 'system', message: data._message || 'Failed to collect data' } ];
					this.showValidationErrors( errors );
					this.triggerCustomEvent( 'scd:' + this.stepName + ':validated', [ false ] );
					deferred.resolve( false );
					return deferred.promise();
				}

				// CRITICAL FIX: Use direct validateData to prevent recursion
				// This prevents the circular call: StepPersistence.validateStep -> ValidationManager.validateStep -> StepPersistence.validateStep
				var validation = this.validateData( data );

				if ( !validation.valid ) {
					// Use ValidationError component for proper error display
					this.showValidationErrors( validation.errors );
				}

				this.triggerCustomEvent( 'scd:' + this.stepName + ':validated', [ validation.valid ] );
				deferred.resolve( validation.valid );
			} catch ( error ) {
				console.error( '[StepPersistence] Validation error:', error );
				this.triggerCustomEvent( 'scd:' + this.stepName + ':validated', [ false ] );
				deferred.resolve( false );
			}

			return deferred.promise();
		},

		/**
		 * Check if step is valid
		 *
		 * @returns {boolean}
		 */
		isValid: function() {
			var validation = this.validateData( this.collectData() );
			return validation.valid;
		},

		/**
		 * Check if step has changes
		 *
		 * @returns {boolean}
		 */
		isDirty: function() {
			return this.modules && this.modules.state ? this.modules.state.isDirty() : false;
		},

		/**
		 * Show validation errors using ValidationError component
		 *
		 * Displays field-level errors with proper accessibility and styling.
		 * Uses centralized ValidationError component for consistent display.
		 *
		 * @param {Array|Object} errors - Validation errors (array or object format)
		 */
		showValidationErrors: function( errors ) {
			if ( !errors || 0 === errors.length ) {
				return;
			}

			// Find step container
			var $stepContainer = $( '#scd-step-' + this.stepName );
			if ( ! $stepContainer.length ) {
				$stepContainer = $( '.scd-wizard-step--' + this.stepName );
			}
			if ( ! $stepContainer.length ) {
				$stepContainer = $( '.scd-wizard-content' );
			}

			// Convert errors to the format expected by ValidationError.showMultiple()
			var formattedErrors = {};
			if ( Array.isArray( errors ) ) {
				// Convert array format to object format
				for ( var i = 0; i < errors.length; i++ ) {
					var error = errors[i];
					if ( error && error.field && error.message ) {
						formattedErrors[error.field] = error.message;
					}
				}
			} else if ( 'object' === typeof errors ) {
				// Already in object format
				formattedErrors = errors;
			}

			// Use ValidationError component for proper display
			if ( window.SCD && window.SCD.ValidationError && 0 < Object.keys( formattedErrors ).length ) {
				window.SCD.ValidationError.showMultiple( formattedErrors, $stepContainer, {
					clearFirst: true,
					showSummary: true,
					summaryPosition: 'top',
					animate: true,
					linkedSummary: true,
					focusFirstError: true
				} );
			} else if ( window.console ) {
				// ValidationError not available - log to console
			}
		},

		/**
		 * Show validation errors (convenience alias)
		 *
		 * Delegates to showValidationErrors() for consistent error display.
		 *
		 * @param {Array|Object} errors - Validation errors
		 */
		showErrors: function( errors ) {
			// Delegate to showValidationErrors for consistent display
			this.showValidationErrors( errors );
		},

		/**
		 * Reset step to defaults
		 */
		reset: function() {
			try {
				var fields = SCD.FieldDefinitions[this.stepName];
				if ( !fields ) {
					console.error( '[StepPersistence] Cannot reset - Field definitions not available for step:', this.stepName );
					return;
				}

				// Build defaults from field definitions
				var defaults = {};
				for ( var fieldName in fields ) {
					if ( Object.prototype.hasOwnProperty.call( fields, fieldName ) ) {
						defaults[fieldName] = SCD.Utils.deepClone( fields[fieldName].default );
					}
				}

				if ( this.modules && this.modules.state ) {
					this._isUpdatingState = true;
					this.modules.state.reset( defaults );
					this._isUpdatingState = false;
				}

				// Reset DOM
				this.populateFields( defaults );

				this.triggerCustomEvent( 'scd:' + this.stepName + ':reset', [] );
			} catch ( error ) {
				console.error( '[StepPersistence] Error during reset:', error );
				if ( 'function' === typeof this.showError ) {
					this.showError( 'Failed to reset form. Please refresh the page.', 'error' );
				}
			}
		},

		/**
		 * Complex field handlers registry
		 * Manages handlers for complex field types following DRY principle
		 * Note: _complexFieldHandlers and _complexFieldQueue are initialized
		 * as instance properties in initPersistence() to avoid sharing between orchestrators
		 */

		/**
		 * Register a complex field handler
		 * @param {string} handlerPath - Dot notation path to handler
		 * @param {object} instance - Handler instance
		 */
		registerComplexFieldHandler: function( handlerPath, instance ) {
			if ( !handlerPath || !instance ) {
				return;
			}

			this._complexFieldHandlers = this._complexFieldHandlers || {};
			this._complexFieldHandlers[handlerPath] = instance;
		},

		/**
		 * Get complex field handler
		 * @param {string} handlerPath - Handler path
		 * @returns {object | null} Handler instance
		 */
		getComplexFieldHandler: function( handlerPath ) {
			if ( !handlerPath || 'string' !== typeof handlerPath ) {
				console.warn( '[StepPersistence] getComplexFieldHandler - invalid handlerPath:', handlerPath );
				return null;
			}

			this._complexFieldHandlers = this._complexFieldHandlers || {};

			// Return from cache if already registered locally
			if ( this._complexFieldHandlers[handlerPath] ) {
				return this._complexFieldHandlers[handlerPath];
			}

			// Try to get from orchestrator's handler registry
			// This connects to the registry we created in discounts-orchestrator.js
			var handler = null;

			if ( this.complexFieldHandlers && this.complexFieldHandlers[handlerPath] ) {
				handler = this.complexFieldHandlers[handlerPath];
				this._complexFieldHandlers[handlerPath] = handler;
			}

			return handler;
		},

		/**
		 * Check if complex field handler is ready
		 * @param {string} handlerPath - Handler path
		 * @returns {boolean} True if ready
		 */
		isComplexFieldReady: function( handlerPath ) {
			var handler = this.getComplexFieldHandler( handlerPath );
			return handler && ( !handler.isReady || handler.isReady() );
		},

		/**
		 * Collect value from complex field
		 * @param {object} fieldDef - Field definition
		 * @returns {*} Collected value
		 */
		collectComplexField: function( fieldDef ) {
			if ( !fieldDef || !fieldDef.handler ) {
				console.warn( '[StepPersistence] collectComplexField - no fieldDef or handler' );
				return fieldDef && fieldDef.default || null;
			}

			var handler = this.getComplexFieldHandler( fieldDef.handler );
			var methodName = fieldDef.methods && fieldDef.methods.collect || 'getValue';

			if ( handler && 'function' === typeof handler[methodName] ) {
				try {
					// Call with correct 'this' context (the handler instance)
					return handler[methodName].call( handler );
				} catch ( e ) {
					console.error( '[StepPersistence] Error collecting complex field:', e );
					return fieldDef.default || null;
				}
			}

			return fieldDef.default || null;
		},

		/**
		 * Populate complex field with value
		 * @param {object} fieldDef - Field definition
		 * @param {*} value - Value to set
		 */
		populateComplexField: function( fieldDef, value ) {
			if ( !fieldDef || !fieldDef.handler ) {
				console.warn( '[StepPersistence] populateComplexField - no fieldDef or handler' );
				return;
			}

			var handlerPath = fieldDef.handler;

			this._complexFieldQueue = this._complexFieldQueue || {};
			this._complexFieldRetries = this._complexFieldRetries || {};

			// Queue the value in case handler isn't ready
			if ( !this._complexFieldQueue[handlerPath] ) {
				this._complexFieldQueue[handlerPath] = [];
			}
			this._complexFieldQueue[handlerPath].push( {
				fieldDef: fieldDef,
				value: value
			} );

			if ( !this._complexFieldRetries[handlerPath] ) {
				this._complexFieldRetries[handlerPath] = 0;
			}

			// Try to populate immediately if ready
			this._processComplexFieldQueue( handlerPath );
		},

		/**
		 * Process queued complex field populations
		 * @private
		 * @param {string} handlerPath - Handler path
		 */
		_processComplexFieldQueue: function( handlerPath ) {
			var self = this;
			var maxRetries = 50; // 50 retries * 100ms = 5 seconds max wait

			if ( !this.isComplexFieldReady( handlerPath ) ) {
				this._complexFieldRetries = this._complexFieldRetries || {};
				this._complexFieldRetries[handlerPath] = ( this._complexFieldRetries[handlerPath] || 0 ) + 1;

				if ( this._complexFieldRetries[handlerPath] > maxRetries ) {
					console.error( '❌ [StepPersistence] Max retries reached for handler:', handlerPath );
					if ( this._complexFieldQueue && this._complexFieldQueue[handlerPath] ) {
						delete this._complexFieldQueue[handlerPath];
					}
					delete this._complexFieldRetries[handlerPath];
					return;
				}

				// Try again later
				setTimeout( function() {
					self._processComplexFieldQueue( handlerPath );
				}, 100 );
				return;
			}

			if ( this._complexFieldRetries && this._complexFieldRetries[handlerPath] ) {
				delete this._complexFieldRetries[handlerPath];
			}

			var queue = this._complexFieldQueue[handlerPath];
			if ( !queue || 0 === queue.length ) {
				return;
			}

			var handler = this.getComplexFieldHandler( handlerPath );
			if ( !handler ) {
				console.error( '❌ [StepPersistence] Could not get handler for:', handlerPath );
				return;
			}

			// Process all queued items
			while ( 0 < queue.length ) {
				var item = queue.shift();
				var methodName = item.fieldDef.methods && item.fieldDef.methods.populate || 'setValue';

				if ( 'function' === typeof handler[methodName] ) {
					try {
						handler[methodName]( item.value );
					} catch ( e ) {
						console.error( '❌ [StepPersistence] Error calling handler.' + methodName + '():', e );
					}
				} else {
					console.error( '❌ [StepPersistence] Handler method not found:', methodName );
				}
			}
		},

		/**
		 * Check if field definitions are available
		 * @private
		 * @since 1.0.0
		 * @returns {boolean} True if available
		 */
		_hasFieldDefinitions: function() {
			return window.SCD && 
			       window.SCD.FieldDefinitions && 
			       'function' === typeof window.SCD.FieldDefinitions.getStepFields;
		},

	/**
	 * Check if a field is visible based on its conditional property
	 * @private
	 * @since 1.0.0
	 * @param {object} fieldDef - Field definition
	 * @param {object} currentData - Current form data to check conditionals against
	 * @returns {boolean} True if field should be visible/processed
	 */
	_isFieldVisible: function( fieldDef, currentData ) {
		// If no conditional defined, field is always visible
		if ( ! fieldDef.conditional ) {
			return true;
		}

		var conditional = fieldDef.conditional;
		// Conditional field names come from PHP in snake_case (e.g., 'product_selection_type')
		// But JavaScript data uses camelCase keys, so we need to convert for lookup
		var conditionalFieldSnake = conditional.field;
		var conditionalValue = conditional.value;

		var actualValue = null;

		// Convert to camelCase for JavaScript data lookup
		var conditionalFieldName = window.SCD && window.SCD.Utils && window.SCD.Utils.Fields
			? window.SCD.Utils.Fields.toCamelCase( conditionalFieldSnake )
			: conditionalFieldSnake;

		// First check the data parameter (for populateFields)
		if ( currentData && Object.prototype.hasOwnProperty.call( currentData, conditionalFieldName ) ) {
			actualValue = currentData[conditionalFieldName];
		}
		// Fall back to state (for collectData when data is being built)
		else if ( this.modules && this.modules.state ) {
			// State uses camelCase keys (JavaScript layer)
			actualValue = this.modules.state.getData( conditionalFieldName );
		}
		// Finally check DOM (for collectData early execution)
		else {
			// DOM uses snake_case (WordPress forms use snake_case field names)
			var $field = $( '[name="' + conditionalFieldSnake + '"]' );
			if ( $field.length ) {
				// Handle checkboxes properly - use checked state, not value attribute
				// Also respect disabled state - disabled checkboxes should return false
				if ( $field.is( ':checkbox' ) ) {
					// Disabled checkbox = feature not available, treat as false
					if ( $field.prop( 'disabled' ) ) {
						actualValue = false;
					} else {
						actualValue = $field.is( ':checked' );
					}
				} else {
					actualValue = $field.val();
				}
			}
		}

		var conditionMatches = false;
		if ( 'boolean' === typeof conditionalValue ) {
			// Boolean comparison - coerce actual value to boolean
			// Handle checkbox values: '1', 1, true, 'true' -> true
			// Handle: '0', 0, false, 'false', '', null, undefined -> false
			conditionMatches = !! actualValue && '0' !== actualValue && 'false' !== actualValue;
			conditionMatches = conditionMatches === conditionalValue;
		} else {
			// Non-boolean comparison - use strict equality
			conditionMatches = actualValue === conditionalValue;
		}

		if ( window.scdDebugPersistence ) {
		}

		return conditionMatches;
	},

		/**
		 * Create error response object
		 * @private
		 * @since 1.0.0
		 * @param {string} message Error message
		 * @returns {object} Error response
		 */
		_createErrorResponse: function( message ) {
			return {
				_error: true,
				_message: message || 'Unknown error'
			};
		},

		/**
		 * Create validation error result
		 * @private
		 * @since 1.0.0
		 * @param {string} field Field name
		 * @param {string} message Error message
		 * @returns {object} Validation result
		 */
		_createValidationErrorResult: function( field, message ) {
			return {
				valid: false,
				errors: [ {
					field: field,
					message: message
				} ]
			};
		},

		/**
		 * Cleanup method to remove event listeners
		 */
		cleanup: function() {
			// Unbind namespaced events if namespace exists
			if ( this._persistenceNamespace && 'function' === typeof this.unbindCustomEvent ) {
				this.unbindCustomEvent( 'scd:wizard:save-step' + this._persistenceNamespace );
				this.unbindCustomEvent( 'scd:wizard:validate-step' + this._persistenceNamespace );
			}

			this._isUpdatingState = false;
			this._persistenceNamespace = null;

			this._complexFieldHandlers = {};
			this._complexFieldQueue = {};
		}
	};

} )( jQuery );