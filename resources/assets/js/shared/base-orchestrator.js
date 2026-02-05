/**
 * Base Orchestrator
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/shared/base-orchestrator.js
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress/plugins/smart-cycle-discounts/
 * @since      1.0.0
 */

( function( $ ) {
	'use strict';

	window.WSSCD = window.WSSCD || {};
	WSSCD.Shared = WSSCD.Shared || {};

	/**
	 * Base Orchestrator Class
	 *
	 * @class WSSCD.Shared.BaseOrchestrator
	 * @param {string} stepName - Name of the step
	 * @param {object} moduleFactories - Object containing module factory functions
	 */
	WSSCD.Shared.BaseOrchestrator = function( stepName, moduleFactories ) {
		this.modules = {};
		this.initialized = false;
		this.wizard = null;
		this.stepName = stepName || '';
		this.config = {};
		this._eventHandlers = [];
		this._timers = {};
		this._moduleFactories = moduleFactories || {};
		this._validationTimers = {}; // Separate timers for validation
		this._domCache = null; // DOM element cache

		// Standard validation debounce delay - consistent across all steps
		this.VALIDATION_DEBOUNCE = 150;

		if ( moduleFactories ) {
			this.initializeModulesFromFactories();
		}
	};

	WSSCD.Shared.BaseOrchestrator.prototype = {
		/**
		 * Initialize orchestrator
		 *
		 * @param {object} wizard Wizard instance
		 * @param {object} config Configuration options
		 * @returns {Promise} Initialization promise
		 */
		init: function( wizard, config ) {
			config = config || {};
			if ( this.initialized ) {

				return ( function() { var d = $.Deferred(); d.resolve(); return d.promise(); } )();
			}

			this.wizard = wizard;
			this.config = $.extend( {}, this.getDefaultConfig(), config );

			this.initializeModules();

			// Bind events
			this.bindEvents();

			// Custom initialization
			this.onInit();

			this.initialized = true;

			// Trigger ready event
			$( document ).trigger( 'wsscd:' + this.stepName + ':ready', [ this ] );

			return $.Deferred().resolve().promise();
		},

		/**
		 * Get default configuration
		 * Override in child classes
		 *
		 * @returns {object} Default config
		 */
		getDefaultConfig: function() {
			return {
				validateOnChange: true,
				enableUndo: false
			};
		},

		/**
		 * Initialize modules from factories
		 */
		initializeModulesFromFactories: function() {
			if ( !this._moduleFactories ) {
				return;
			}

			// Single-pass initialization only
			for ( var moduleName in this._moduleFactories ) {
				if ( Object.prototype.hasOwnProperty.call( this._moduleFactories, moduleName ) ) {
					var factory = this._moduleFactories[moduleName];
					if ( 'function' === typeof factory ) {
						var moduleInstance = factory.call( this );
						if ( null !== moduleInstance ) {
							this.modules[moduleName] = moduleInstance;
						}
					}
				}
			}
		},

		// Modules must initialize properly the first time

		/**
		 * Initialize modules
		 * Override in child classes to load specific modules
		 *
		 * @returns {Promise}
		 */
		initializeModules: function() {
			// Call step-specific initialization if defined
			if ( 'function' === typeof this.initializeStep ) {
				this.initializeStep();
			}
			return ( function() { var d = $.Deferred(); d.resolve(); return d.promise(); } )();
		},

		/**
		 * Custom initialization hook
		 * Override in child classes
		 *
		 * @returns {Promise}
		 */
		onInit: function() {
			// Override in child classes
			return ( function() { var d = $.Deferred(); d.resolve(); return d.promise(); } )();
		},

		/**
		 * Bind common events
		 */
		bindEvents: function() {
			// Real-time validation with standard debounce
			this.setupFieldValidation();

			// Custom events
			this.onBindEvents();
		},

		/**
		 * Custom event binding hook
		 * Override in child classes
		 */
		onBindEvents: function() {
			// Override in child classes
		},

		/**
		 * Bind event with tracking
		 *
		 * @param {string} event Event name
		 * @param {string} selector jQuery selector
		 * @param {Function} handler Event handler
		 */
		bindEvent: function( event, selector, handler ) {
			$( document ).on( event, selector, handler );

			this._eventHandlers.push( {
				event: event,
				selector: selector,
				handler: handler
			} );
		},

		/**
		 * Collect step data
		 * Must be implemented in child classes
		 *
		 * @returns {object} Step data
		 */
		collectData: function() {
			throw new Error( 'collectData must be implemented in child class' );
		},

		/**
		 * Validate step
		 *
		 * @returns {Promise} Promise that resolves to boolean (true if valid)
		 */
		validateStep: function() {
			var deferred = $.Deferred();
			var data = null;

			// Try to get data from state manager first (for unloaded steps)
			if ( window.WSSCD && window.WSSCD.Wizard && window.WSSCD.Wizard.stateManager ) {
				var stateManager = window.WSSCD.Wizard.stateManager;
				if ( 'function' === typeof stateManager.get ) {
					var fullState = stateManager.get();
					if ( fullState.stepData && fullState.stepData[this.stepName] ) {
						data = fullState.stepData[this.stepName];
					}
				}
			}

			// Fallback to collecting from DOM if step is loaded
			if ( ! data || 0 === Object.keys( data ).length ) {
				data = this.collectData();
			}

			var result = this.validateData( data );

			// Handle validation result
			if ( result.valid ) {
				deferred.resolve( true );
			} else {
				if ( 'function' === typeof this.showErrors && result.errors ) {
					// Convert array of errors to object format expected by showErrors
					var errorObject = {};
					if ( Array.isArray( result.errors ) ) {
						result.errors.forEach( function( error ) {
							if ( error.field && error.message ) {
								errorObject[error.field] = error.message;
							}
						} );
						this.showErrors( errorObject );
					} else {
						// Already in object format
						this.showErrors( result.errors );
					}
				}
				deferred.resolve( false );
			}

			return deferred.promise();
		},

		/**
		 * Validate data
		 * Override in child classes for custom validation
		 *
		 * @param {object} _data Data to validate (unused in base implementation)
		 * @returns {object} Validation result
		 */
		validateData: function( _data ) {
			// Base implementation - override in child classes
			return {
				valid: true,
				errors: []
			};
		},

		/**
		 * Get property value with fallback for multiple naming conventions
		 * Handles both snake_case (from PHP) and camelCase (from JS)
		 *
		 * @param {object} data Data object to search
		 * @param {Array} propertyNames Array of possible property names
		 * @returns {*} Property value or undefined
		 */
		getPropertyValue: function( data, propertyNames ) {
			if ( !data || !Array.isArray( propertyNames ) ) {
				return undefined;
			}

			for ( var i = 0; i < propertyNames.length; i++ ) {
				if ( data[propertyNames[i]] !== undefined ) {
					return data[propertyNames[i]];
				}
			}

			return undefined;
		},

		/**
		 * Custom field validation hook
		 * Override in child classes
		 *
		 * @param {string} _fieldName Field name
		 * @param {*} _value Field value
		 * @param {jQuery} _$field Field element
		 * @returns {object} Validation result
		 */
		onValidateField: function( _fieldName, _value, _$field ) {
			// Override in child classes
			return { valid: true };
		},

		/**
		 * Field names this step validates itself (not via ValidationManager).
		 * Override in steps that own validation for specific fields (e.g. schedule date/time).
		 * Base will skip real-time validation for these so step controls show/clear.
		 *
		 * @returns {string[]} Field names to skip in setupFieldValidation
		 */
		getStepValidatedFieldNames: function() {
			return [];
		},

		/**
		 * Setup real-time field validation
		 * Standard implementation for all steps - 150ms debounce.
		 * Skips fields returned by getStepValidatedFieldNames() so steps can own their validation.
		 */
		setupFieldValidation: function() {
			var self = this;

			if ( ! this.$container || ! this.$container.length ) {
				return;
			}

			var stepHandled = this.getStepValidatedFieldNames();
			var stepHandledSet = {};
			for ( var i = 0; i < stepHandled.length; i++ ) {
				stepHandledSet[ stepHandled[i] ] = true;
			}

			this.$container.on( 'input change', ':input[name]', function() {
				var $field = $( this );
				var fieldName = this.name || this.id;

				if ( stepHandledSet[ fieldName ] ) {
					return;
				}

				if ( self._validationTimers[fieldName] ) {
					clearTimeout( self._validationTimers[fieldName] );
				}

				self._validationTimers[fieldName] = setTimeout( function() {
					self.validateFieldRealTime( $field );
					delete self._validationTimers[fieldName];
				}, self.VALIDATION_DEBOUNCE );
			} );
		},

		/**
		 * Validate field in real-time using centralized ValidationManager
		 * @param {jQuery} $field Field to validate
		 */
		validateFieldRealTime: function( $field ) {
			var fieldName = $field.attr( 'name' ) || $field.attr( 'id' );
			var value = $field.val();

			// Always use centralized validation with new context format
			if ( window.WSSCD && window.WSSCD.ValidationManager ) {
				var context = {
					stepId: this.stepName,
					allValues: this.modules && this.modules.state ? this.modules.state.getState() : {},
					visibilityMap: null // Real-time validation doesn't need full visibility computation
				};
				
				var result = window.WSSCD.ValidationManager.validateField(
					fieldName,
					value,
					context
				);

				// Use centralized ValidationError component for UI updates
				if ( window.WSSCD && window.WSSCD.ValidationError ) {
					if ( result.ok ) {
						window.WSSCD.ValidationError.clear( $field );
					} else {
						// New format: result.errors is array of { code, message } objects
						var errorMessage = result.errors[0] && result.errors[0].message ? 
							result.errors[0].message : 
							result.errors[0];
						window.WSSCD.ValidationError.show( $field, errorMessage );
					}
				}
			}
			// If ValidationManager not available, skip validation rather than using deprecated methods
		},


		/**
		 * Debounce utility
		 *
		 * @param {string} key Timer key
		 * @param {Function} callback Callback function
		 * @param {number} delay Delay in ms
		 */
		debounce: function( key, callback, delay ) {
			// Use centralized debounce
			if ( !this._debouncedFunctions ) {
				this._debouncedFunctions = {};
			}

			if ( !this._debouncedFunctions[key] ) {
				var self = this;
				this._debouncedFunctions[key] = WSSCD.Utils.debounce( function() {
					callback();
					delete self._debouncedFunctions[key];
				}, delay );
			}

			this._debouncedFunctions[key]();
		},

		/**
		 * Handle errors
		 *
		 * @param {Error|string} error Error object or message
		 * @param {string} category Error category: 'validation', 'network', 'initialization', 'general'
		 */
		handleError: function( error, category ) {
			var message = 'string' === typeof error ? error : ( error.message || 'An error occurred' );
			category = category || 'general';

			// Define error handling strategies by category
			var errorConfigs = {
				validation: { type: 'warning', duration: 3000 },
				network: { type: 'error', duration: 3000 },
				initialization: { type: 'error', duration: 3000 },
				general: { type: 'error', duration: 3000 }
			};

			var config = errorConfigs[category] || errorConfigs.general;

			this.showError( message, config.type, config.duration );

			// Trigger error event
			$( document ).trigger( 'wsscd:' + this.stepName + ':error', [ error, category ] );
		},

		/**
		 * Show error message to user
		 *
		 * @param {string} message Error message
		 * @param {string} type Error type: 'error', 'warning'
		 * @param {number} duration Display duration in ms
		 */
		showError: function( message, type, duration ) {
			type = type || 'error';
			duration = duration || 5000;

			// Use ONLY NotificationService for consistent display at top of page
			if ( window.WSSCD && window.WSSCD.Shared && window.WSSCD.Shared.NotificationService ) {
				window.WSSCD.Shared.NotificationService.show( message, type, duration );
				return; // Exit to prevent duplication
			}

			// If NotificationService not available, use console as fallback
			console.error( 'WSSCD Error:', message );
		},


	/**
	 * Safe wrapper for ErrorHandler
	 * Eliminates need for defensive checks throughout codebase
	 *
	 * @param {Error} error Error object
	 * @param {string} context Error context/location
	 * @param {string} severity Error severity (optional, defaults to MEDIUM)
	 */
	safeErrorHandle: function( error, context, severity ) {
		if ( window.WSSCD && window.WSSCD.ErrorHandler ) {
			severity = severity || WSSCD.ErrorHandler.SEVERITY.MEDIUM;
			WSSCD.ErrorHandler.handle( error, context, severity );
		} else if ( window.console && window.console.error ) {
			console.error( '[' + context + ']', error );
		}
	},

	/**
	 * Set loading state
	 *
	 * Delegates to centralized LoaderUtil for consistent loading UX.
	 *
	 * @param {boolean} loading - Loading state
	 * @param {string} message - Loading message
	 */
	setLoading: function( loading, message ) {
		var $step = $( '#wsscd-step-' + this.stepName );

		if ( loading ) {
			$step.addClass( 'loading' );

			// Delegate to centralized LoaderUtil
			if ( window.WSSCD && window.WSSCD.LoaderUtil ) {
				WSSCD.LoaderUtil.showOverlay( $step, message );
			}
		} else {
			$step.removeClass( 'loading' );

			// Delegate to centralized LoaderUtil
			if ( window.WSSCD && window.WSSCD.LoaderUtil ) {
				WSSCD.LoaderUtil.hideOverlay( $step );
			}
		}
	},


		/**
		 * Get step state
		 * Override in child classes if using state management
		 *
		 * @returns {object} Current state
		 */
		getState: function() {
			return this.collectData();
		},

		/**
		 * Set step state
		 * Override in child classes if using state management
		 *
		 * @param {object} _state State to set
		 */
		setState: function( _state ) {
			// Override in child classes
		},

	/**
	 * Find field element by name with fallback strategies
	 *
	 * @param {string} fieldName Field name to find
	 * @returns {jQuery} jQuery field element (may be empty)
	 */
	_findField: function( fieldName ) {
		if ( !this.$container || !this.$container.length ) {
			return $( [] );
		}

		// Try direct name
		var $field = this.$container.find( '[name="' + fieldName + '"]' );

		// Try snake_case conversion
		if ( !$field.length && window.WSSCD && window.WSSCD.Utils && window.WSSCD.Utils.camelToSnakeCase ) {
			var snakeName = window.WSSCD.Utils.camelToSnakeCase( fieldName );
			$field = this.$container.find( '[name="' + snakeName + '"]' );
		}

		// Try ID
		if ( !$field.length ) {
			$field = this.$container.find( '#' + fieldName );
		}

		return $field;
	},

	/**
	 * Show field-specific error using centralized ValidationError component
	 *
	 * @param {jQuery|string} fieldOrName Field element or field name
	 * @param {string|Array} errorMessages Error message(s)
	 */
	showFieldError: function( fieldOrName, errorMessages ) {
		// Use centralized ValidationError component
		if ( window.WSSCD && window.WSSCD.ValidationError ) {
			var $field;

			// Handle both jQuery object and string
			if ( 'string' === typeof fieldOrName ) {
				$field = this._findField( fieldOrName );
			} else if ( fieldOrName && fieldOrName.jquery ) {
				$field = fieldOrName;
			} else {
				return;
			}

			if ( !$field || !$field.length ) {
				return;
			}

			// Join multiple messages if array
			var errorMessage = Array.isArray( errorMessages ) ? errorMessages.join( ' ' ) : errorMessages;

			window.WSSCD.ValidationError.show( $field, errorMessage );
		}
	},

	/**
	 * Clear field-specific error using centralized ValidationError component
	 *
	 * @param {jQuery|string} fieldOrName Field element or field name
	 */
	clearFieldError: function( fieldOrName ) {
		// Use centralized ValidationError component
		if ( window.WSSCD && window.WSSCD.ValidationError ) {
			var $field;

			// Handle both jQuery object and string
			if ( 'string' === typeof fieldOrName ) {
				$field = this._findField( fieldOrName );
			} else if ( fieldOrName && fieldOrName.jquery ) {
				$field = fieldOrName;
			} else {
				return;
			}

			if ( !$field || !$field.length ) {
				return;
			}

			window.WSSCD.ValidationError.clear( $field );
		}
	},

		/**
		 * Cleanup and destroy - comprehensive memory leak prevention
		 */
		destroy: function() {
			for ( var timerId in this._validationTimers ) {
				if ( Object.prototype.hasOwnProperty.call( this._validationTimers, timerId ) ) {
					clearTimeout( this._validationTimers[timerId] );
					delete this._validationTimers[timerId];
				}
			}
			this._validationTimers = {};

			for ( var timerKey in this._timers ) {
				if ( Object.prototype.hasOwnProperty.call( this._timers, timerKey ) ) {
					clearTimeout( this._timers[timerKey] );
					delete this._timers[timerKey];
				}
			}
			this._timers = {};

			if ( this.$container && this.$container.length ) {
				this.$container.off( '.validation' );
				this.$container.off( '.orchestrator' );
			}

			// Unbind document events
			for ( var i = 0; i < this._eventHandlers.length; i++ ) {
				var handler = this._eventHandlers[i];
				$( document ).off( handler.event, handler.selector, handler.handler );
			}
			this._eventHandlers = [];

			// Destroy modules
			for ( var key in this.modules ) {
				if ( Object.prototype.hasOwnProperty.call( this.modules, key ) ) {
					if ( this.modules[key] && 'function' === typeof this.modules[key].destroy ) {
						this.modules[key].destroy();
					}
					delete this.modules[key];
				}
			}
			this.modules = {};

			this.wizard = null;
			this.config = null;
			this.$container = null;

			// Custom cleanup
			this.onDestroy();

			this.initialized = false;

			// Trigger destroyed event
			$( document ).trigger( 'wsscd:' + this.stepName + ':destroyed' );
		},

		/**
		 * Custom cleanup hook
		 * Override in child classes
		 */
		onDestroy: function() {
			// Override in child classes
		},

	};

	/**
	 * Factory method to create a step orchestrator class
	 * Eliminates boilerplate code for constructor setup, inheritance, mixins, and registration
	 *
	 * @param {string} stepName - Name of the step (e.g., 'basic', 'products')
	 * @param {object} customMethods - Custom methods for the step orchestrator
	 * @returns {Function} Orchestrator constructor function
	 */
	WSSCD.Shared.BaseOrchestrator.createStep = function( stepName, customMethods ) {
		if ( !stepName || 'string' !== typeof stepName ) {
			throw new Error( 'Step name must be a non-empty string' );
		}

		customMethods = customMethods || {};

		var OrchestratorClass = function() {
			// Call parent constructor
			WSSCD.Shared.BaseOrchestrator.call( this, stepName );

			this.modules = {};
		};

		OrchestratorClass.prototype = Object.create( WSSCD.Shared.BaseOrchestrator.prototype );
		OrchestratorClass.prototype.constructor = OrchestratorClass;

		// Extend with EventManager mixin BEFORE adding other methods
		if ( WSSCD.Mixins && WSSCD.Mixins.EventManager ) {
			$.extend( OrchestratorClass.prototype, WSSCD.Mixins.EventManager );
		}

		// Mix in step persistence functionality
		if ( WSSCD.Mixins && WSSCD.Mixins.StepPersistence ) {
			$.extend( OrchestratorClass.prototype, WSSCD.Mixins.StepPersistence );
		}

		var originalInit = customMethods.init;
		customMethods.init = function( wizard, config ) {
			if ( !this._eventHandlers && 'function' === typeof this.initEventManager ) {
				this.initEventManager();
			}

			this.$container = $( '#wsscd-step-' + stepName );
			if ( !this.$container.length ) {
				this.$container = $( '.wsscd-wizard-step--' + stepName );
			}

			// Call parent init
			var self = this;
			var parentPromise = WSSCD.Shared.BaseOrchestrator.prototype.init.call( this, wizard, config );

			this.initPersistence( stepName );

			// Return promise for proper async handling
			return parentPromise.then( function() {
				// Call custom init if provided
				if ( 'function' === typeof originalInit ) {
					return originalInit.call( self, wizard, config );
				}
			} ).then( function() {
				// Call onStepLoaded if defined (not all steps have this method)
				if ( 'function' === typeof self.onStepLoaded ) {
					self.onStepLoaded();
				}
			} );
		};

		$.extend( OrchestratorClass.prototype, customMethods );

		// Auto-register factory if WSSCD.Steps.registerFactory is available
		if ( 'function' === typeof WSSCD.Steps.registerFactory ) {
			WSSCD.Steps.registerFactory( stepName, function() {
				return new OrchestratorClass();
			} );
		}

		OrchestratorClass.fields = {};

		// Auto-load field definitions when document is ready
		$( document ).ready( function() {
			if ( window.WSSCD && window.WSSCD.FieldDefinitions ) {
				var stepFields = WSSCD.FieldDefinitions.getStepFields( stepName );
				if ( stepFields ) {
					OrchestratorClass.fields = stepFields;
				}
			}
		} );

		return OrchestratorClass;
	};

} )( jQuery );
