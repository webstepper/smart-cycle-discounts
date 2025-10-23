/**
 * Base Orchestrator Module
 *
 * Foundation for wizard step orchestrators.
 * Provides common lifecycle methods and module management.
 *
 * Features:
 * - Module lifecycle management
 * - Data collection and validation
 * - Event handling
 * - Error management
 * - Performance monitoring ( optional )
 *
 * @param $
 * @package SmartCycleDiscounts
 * @since 1.0.0
 */

( function( $ ) {
	'use strict';

	window.SCD = window.SCD || {};
	SCD.Shared = SCD.Shared || {};

	/**
	 * Base Orchestrator Class
	 *
	 * @class SCD.Shared.BaseOrchestrator
	 * @param {string} stepName - Name of the step
	 * @param {object} moduleFactories - Object containing module factory functions
	 */
	SCD.Shared.BaseOrchestrator = function( stepName, moduleFactories ) {
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

		// Initialize modules if factories provided
		if ( moduleFactories ) {
			this.initializeModulesFromFactories();
		}
	};

	SCD.Shared.BaseOrchestrator.prototype = {
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

			// Initialize modules
			this.initializeModules();

			// Bind events
			this.bindEvents();

			// Custom initialization
			this.onInit();

			this.initialized = true;

			// Trigger ready event
			$( document ).trigger( 'scd:' + this.stepName + ':ready', [ this ] );

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
				autoSave: true,
				autoSaveDelay: 2000,
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

			// Initialize all modules - proper dependency injection is the solution
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
			var self = this;

			// Auto-save functionality
			if ( this.config.autoSave ) {
				this.bindEvent( 'change', '#scd-step-' + this.stepName + ' input, #scd-step-' + this.stepName + ' select, #scd-step-' + this.stepName + ' textarea', function() {
					self.debounce( 'autoSave', function() {
						self.autoSave();
					}, self.config.autoSaveDelay );
				} );
			}

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

			var data = this.collectData();
			var result = this.validateData( data );

			// Handle validation result - legacy format from validateData
			if ( result.valid ) {
				deferred.resolve( true );
			} else {
				// Show validation errors if we have a method for it
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
		 * Setup real-time field validation
		 * Standard implementation for all steps - 150ms debounce
		 */
		setupFieldValidation: function() {
			var self = this;

			// Skip if container not available
			if ( ! this.$container || ! this.$container.length ) {
				return;
			}

			// Use event delegation for better performance
			this.$container.on( 'input change', ':input[name]', function() {
				var $field = $( this );
				var fieldName = this.name || this.id;

				// Clear existing timer
				if ( self._validationTimers[fieldName] ) {
					clearTimeout( self._validationTimers[fieldName] );
				}

				// Set new timer with standard debounce
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
			if ( window.SCD && window.SCD.ValidationManager ) {
				var context = {
					stepId: this.stepName,
					allValues: this.modules && this.modules.state ? this.modules.state.getState() : {},
					visibilityMap: null // Real-time validation doesn't need full visibility computation
				};
				
				var result = window.SCD.ValidationManager.validateField(
					fieldName,
					value,
					context
				);

				// Use centralized ValidationError component for UI updates
				if ( window.SCD && window.SCD.ValidationError ) {
					if ( result.ok ) {
						window.SCD.ValidationError.clear( $field );
					} else {
						// New format: result.errors is array of { code, message } objects
						var errorMessage = result.errors[0] && result.errors[0].message ? 
							result.errors[0].message : 
							result.errors[0];
						window.SCD.ValidationError.show( $field, errorMessage );
					}
				}
			}
			// If ValidationManager not available, skip validation rather than using deprecated methods
		},

		/**
		 * Auto-save functionality
		 */
		autoSave: function() {
			if ( !this.wizard || !this.wizard.saveProgress ) {
				return;
			}

			var data = this.collectData();
			this.wizard.saveProgress( this.stepName, data );

			// Show save indicator
			this.showSaveIndicator();
		},

		/**
		 * Show save indicator
		 */
		showSaveIndicator: function() {
			var $indicator = $( '<div class="scd-save-indicator">Saved</div>' );
			$( 'body' ).append( $indicator );

			setTimeout( function() {
				$indicator.fadeOut( function() { $indicator.remove(); } );
			}, 2000 );
		},

		/**
		 * Get or create module
		 *
		 * @param {string} name Module name
		 * @param {Function} factory Module factory function
		 * @returns {object} Module instance
		 */
		getModule: function( name, factory ) {
			if ( !this.modules[name] && factory ) {
				this.modules[name] = factory();
			}
			return this.modules[name];
		},

		/**
		 * Register module
		 *
		 * @param {string} name Module name
		 * @param {object} module Module instance
		 */
		registerModule: function( name, module ) {
			this.modules[name] = module;
		},

		/**
		 * Debounce utility
		 *
		 * @param {string} key Timer key
		 * @param {Function} callback Callback function
		 * @param {number} delay Delay in ms
		 */
		debounce: function( key, callback, delay ) {
			// Use centralized debounce if available
			if ( window.SCD && SCD.Utils && SCD.Utils.debounce ) {
				// Create a debounced function for this key if not exists
				if ( !this._debouncedFunctions ) {
					this._debouncedFunctions = {};
				}

				if ( !this._debouncedFunctions[key] ) {
					var self = this;
					this._debouncedFunctions[key] = SCD.Utils.debounce( function() {
						callback();
						delete self._debouncedFunctions[key];
					}, delay );
				}

				this._debouncedFunctions[key]();
			} else {
				// Fallback implementation
				if ( this._timers[key] ) {
					clearTimeout( this._timers[key] );
				}

				var orchestrator = this;
				this._timers[key] = setTimeout( function() {
					callback();
					delete orchestrator._timers[key];
				}, delay );
			}
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
				validation: { type: 'warning', duration: 5000 },
				network: { type: 'error', duration: 8000 },
				initialization: { type: 'error', duration: 10000 },
				general: { type: 'error', duration: 5000 }
			};

			var config = errorConfigs[category] || errorConfigs.general;

			// Show user-friendly error
			this.showError( message, config.type, config.duration );

			// Trigger error event
			$( document ).trigger( 'scd:' + this.stepName + ':error', [ error, category ] );
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
			if ( window.SCD && window.SCD.Shared && window.SCD.Shared.NotificationService ) {
				window.SCD.Shared.NotificationService.show( message, type, duration );
				return; // Exit to prevent duplication
			}

			// If NotificationService not available, use console as fallback
			console.error( 'SCD Error:', message );
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
		if ( window.SCD && window.SCD.ErrorHandler ) {
			severity = severity || SCD.ErrorHandler.SEVERITY.MEDIUM;
			SCD.ErrorHandler.handle( error, context, severity );
		} else if ( window.console && window.console.error ) {
			console.error( '[' + context + ']', error );
		}
	},

	/**
	 * Safe wrapper for TooltipManager initialization
	 * Eliminates defensive checks when initializing tooltips
	 *
	 * @param {jQuery} $container Container element
	 * @param {string} selector Tooltip trigger selector (optional)
	 */
	safeTooltipInit: function( $container, selector ) {
		if ( window.SCD && window.SCD.TooltipManager ) {
			SCD.TooltipManager.initialize( $container || this.$container, selector );
		}
	},

	/**
	 * Safe wrapper for TooltipManager destroy
	 *
	 * @param {jQuery} $container Container element
	 * @param {string} selector Tooltip trigger selector (optional)
	 */
	safeTooltipDestroy: function( $container, selector ) {
		if ( window.SCD && window.SCD.TooltipManager ) {
			SCD.TooltipManager.destroy( $container || this.$container, selector );
		}
	},

	/**
	 * Safe wrapper for ValidationError.show
	 * Eliminates defensive checks when showing validation errors
	 *
	 * @param {jQuery} $field Field element
	 * @param {string} message Error message
	 */
	safeValidationError: function( $field, message ) {
		if ( window.SCD && window.SCD.ValidationError ) {
			SCD.ValidationError.show( $field, message );
		}
	},

	/**
	 * Safe wrapper for ValidationError.clear
	 * Eliminates defensive checks when clearing validation errors
	 *
	 * @param {jQuery} $field Field element
	 */
	safeValidationClear: function( $field ) {
		if ( window.SCD && window.SCD.ValidationError ) {
			SCD.ValidationError.clear( $field );
		}
	},

	/**
	 * Safe wrapper for ValidationError.clearAll
	 *
	 * @param {jQuery} $container Container element
	 */
	safeValidationClearAll: function( $container ) {
		if ( window.SCD && window.SCD.ValidationError ) {
			SCD.ValidationError.clearAll( $container || this.$container );
		}
	},

	/**
	 * Safe wrapper for ValidationError.showMultiple
	 *
	 * @param {object} errors Error object with field names as keys
	 * @param {jQuery} $container Container element
	 * @param {object} options Display options
	 */
	safeValidationShowMultiple: function( errors, $container, options ) {
		if ( window.SCD && window.SCD.ValidationError && Object.keys( errors ).length > 0 ) {
			SCD.ValidationError.showMultiple( errors, $container || this.$container, options );
		}
	},

	/**
	 * Enable/disable step
	 *
	 * @param {boolean} enabled Enable state
	 */
		setEnabled: function( enabled ) {
			var $step = $( '#scd-step-' + this.stepName );

			if ( enabled ) {
				$step.find( 'input, select, textarea, button' ).prop( 'disabled', false );
				$step.removeClass( 'disabled' );
			} else {
				$step.find( 'input, select, textarea, button' ).prop( 'disabled', true );
				$step.addClass( 'disabled' );
			}
		},

		/**
		 * Show/hide loading state
		 *
		 * @param {boolean} loading Loading state
		 * @param {string} message Optional loading message
		 */
		setLoading: function( loading, message ) {
			message = message || 'Loading...';
			var $step = $( '#scd-step-' + this.stepName );

			if ( loading ) {
				$step.addClass( 'loading' );

				if ( !$step.find( '.scd-loading-overlay' ).length ) {
					$step.append( '' +
                        '<div class="scd-loading-overlay">' +
                            '<div class="scd-loading-spinner"></div>' +
                            '<div class="scd-loading-message">' + message + '</div>' +
                        '</div>' );
					/* Old multiline string removed */
					/* $step.append('\n                        <div class="scd-loading-overlay">\n                            <div class="scd-loading-spinner"></div>\n                            <div class="scd-loading-message">' + ( message ) + '</div>\n                        </div>\n                    '); */
				}
			} else {
				$step.removeClass( 'loading' );
				$step.find( '.scd-loading-overlay' ).remove();
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
		if ( !$field.length && window.SCD && window.SCD.Utils && window.SCD.Utils.camelToSnakeCase ) {
			var snakeName = window.SCD.Utils.camelToSnakeCase( fieldName );
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
		if ( window.SCD && window.SCD.ValidationError ) {
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

			window.SCD.ValidationError.show( $field, errorMessage );
		}
	},

	/**
	 * Clear field-specific error using centralized ValidationError component
	 *
	 * @param {jQuery|string} fieldOrName Field element or field name
	 */
	clearFieldError: function( fieldOrName ) {
		// Use centralized ValidationError component
		if ( window.SCD && window.SCD.ValidationError ) {
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

			window.SCD.ValidationError.clear( $field );
		}
	},

		/**
		 * Cleanup and destroy - comprehensive memory leak prevention
		 */
		destroy: function() {
			// Clear all validation timers first
			for ( var timerId in this._validationTimers ) {
				if ( Object.prototype.hasOwnProperty.call( this._validationTimers, timerId ) ) {
					clearTimeout( this._validationTimers[timerId] );
					delete this._validationTimers[timerId];
				}
			}
			this._validationTimers = {};

			// Clear general timers
			for ( var timerKey in this._timers ) {
				if ( Object.prototype.hasOwnProperty.call( this._timers, timerKey ) ) {
					clearTimeout( this._timers[timerKey] );
					delete this._timers[timerKey];
				}
			}
			this._timers = {};

			// Remove event listeners with namespace for better cleanup
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

			// Clear references
			this.wizard = null;
			this.config = null;
			this.$container = null;

			// Custom cleanup
			this.onDestroy();

			this.initialized = false;

			// Trigger destroyed event
			$( document ).trigger( 'scd:' + this.stepName + ':destroyed' );
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
	SCD.Shared.BaseOrchestrator.createStep = function( stepName, customMethods ) {
		// Validate parameters
		if ( !stepName || 'string' !== typeof stepName ) {
			throw new Error( 'Step name must be a non-empty string' );
		}

		customMethods = customMethods || {};

		// Create constructor function
		var OrchestratorClass = function() {
			// Call parent constructor
			SCD.Shared.BaseOrchestrator.call( this, stepName );

			// Initialize step-specific properties
			this.modules = {};
		};

		// Set up proper prototype chain
		OrchestratorClass.prototype = Object.create( SCD.Shared.BaseOrchestrator.prototype );
		OrchestratorClass.prototype.constructor = OrchestratorClass;

		// Extend with EventManager mixin BEFORE adding other methods
		if ( SCD.Mixins && SCD.Mixins.EventManager ) {
			$.extend( OrchestratorClass.prototype, SCD.Mixins.EventManager );
		} else if ( SCD.EventManager ) {
			$.extend( OrchestratorClass.prototype, SCD.EventManager );
		}

		// Mix in step persistence functionality
		if ( SCD.Mixins && SCD.Mixins.StepPersistence ) {
			$.extend( OrchestratorClass.prototype, SCD.Mixins.StepPersistence );
		}

		// Add standard init method with common setup
		var originalInit = customMethods.init;
		customMethods.init = function( wizard, config ) {
			// Initialize event manager if available and not already initialized
			if ( !this._eventHandlers && 'function' === typeof this.initEventManager ) {
				this.initEventManager();
			}

			// Set container reference with fallback
			this.$container = $( '#scd-step-' + stepName );
			if ( !this.$container.length ) {
				this.$container = $( '.scd-wizard-step--' + stepName );
			}

			// Call parent init
			var self = this;
			var parentPromise = SCD.Shared.BaseOrchestrator.prototype.init.call( this, wizard, config );

			// Initialize persistence
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

		// Add custom methods to prototype
		$.extend( OrchestratorClass.prototype, customMethods );

		// Auto-register factory if SCD.Steps.registerFactory is available
		if ( 'function' === typeof SCD.Steps.registerFactory ) {
			SCD.Steps.registerFactory( stepName, function() {
				return new OrchestratorClass();
			} );
		}

		// Initialize field definitions property
		OrchestratorClass.fields = {};

		// Auto-load field definitions when document is ready
		$( document ).ready( function() {
			if ( window.SCD && window.SCD.FieldDefinitions ) {
				var stepFields = SCD.FieldDefinitions.getStepFields( stepName );
				if ( stepFields ) {
					OrchestratorClass.fields = stepFields;
				}
			}
		} );

		return OrchestratorClass;
	};

} )( jQuery );
