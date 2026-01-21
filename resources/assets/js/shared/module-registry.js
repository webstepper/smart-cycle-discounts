/**
 * Module Registry - Declarative Module Instantiation System
 *
 * Eliminates manual module initialization duplication by providing
 * a declarative system for defining modules and their dependencies.
 *
 * @since 2.0.0
 * @package Smart_Cycle_Discounts
 */

( function( window, $, WSSCD ) {
	'use strict';

	if ( ! WSSCD || ! WSSCD.Shared ) {
		console.error( 'WSSCD.Shared is not defined. Module Registry cannot initialize.' );
		return;
	}

	/**
	 * Module Registry
	 *
	 * Provides declarative module instantiation with automatic dependency injection
	 *
	 * @example
	 * // Define module configuration
	 * var config = {
	 *     state: {
	 *         class: WSSCD.Modules.Basic.State,
	 *         deps: []
	 *     },
	 *     api: {
	 *         class: WSSCD.Modules.Basic.API,
	 *         deps: []
	 *     },
	 *     fields: {
	 *         class: WSSCD.Modules.Basic.Fields,
	 *         deps: ['state'] // Depends on state module
	 *     }
	 * };
	 *
	 * // Initialize all modules with dependency injection
	 * var modules = WSSCD.Shared.ModuleRegistry.initialize( config );
	 */
	WSSCD.Shared.ModuleRegistry = {

		/**
		 * Initialize modules from configuration
		 *
		 * @param {Object} config Module configuration
		 * @param {Object} context Optional context object (usually orchestrator instance)
		 * @return {Object} Initialized modules object
		 */
		initialize: function( config, context ) {
			var self = this;
			var modules = {};
			var initialized = {};

			// Validate configuration
			if ( ! config || typeof config !== 'object' ) {
				WSSCD.ErrorHandler.handle(
					new Error( 'Module Registry: Invalid configuration provided' ),
					'ModuleRegistry.initialize'
				);
				return modules;
			}

			/**
			 * Initialize a single module with dependency resolution
			 *
			 * @param {string} name Module name
			 * @return {Object|null} Initialized module instance
			 */
			function initializeModule( name ) {
				// Already initialized
				if ( initialized[name] ) {
					return modules[name];
				}

				// Module not in config
				if ( ! config[name] ) {
					WSSCD.ErrorHandler.handle(
						new Error( 'Module Registry: Module "' + name + '" not found in configuration' ),
						'ModuleRegistry.initializeModule'
					);
					return null;
				}

				var moduleConfig = config[name];

				// Validate module configuration
				if ( ! moduleConfig.class ) {
					WSSCD.ErrorHandler.handle(
						new Error( 'Module Registry: Module "' + name + '" missing class definition' ),
						'ModuleRegistry.initializeModule'
					);
					return null;
				}

				// Resolve dependencies first (recursive)
				var deps = [];
				if ( moduleConfig.deps && moduleConfig.deps.length > 0 ) {
					for ( var i = 0; i < moduleConfig.deps.length; i++ ) {
						var depName = moduleConfig.deps[i];

						// Check for circular dependencies
						if ( depName === name ) {
							WSSCD.ErrorHandler.handle(
								new Error( 'Module Registry: Circular dependency detected for module "' + name + '"' ),
								'ModuleRegistry.initializeModule'
							);
							return null;
						}

						// Initialize dependency
						var dep = initializeModule( depName );
						if ( ! dep ) {
							WSSCD.ErrorHandler.handle(
								new Error( 'Module Registry: Failed to resolve dependency "' + depName + '" for module "' + name + '"' ),
								'ModuleRegistry.initializeModule'
							);
							return null;
						}

						deps.push( dep );
					}
				}

				// Add context to dependencies if specified
				if ( moduleConfig.injectContext && context ) {
					deps.push( context );
				}

				// Instantiate module with dependencies
				try {
					var ModuleClass = self._resolveClass( moduleConfig.class );

					if ( ! ModuleClass ) {
						WSSCD.ErrorHandler.handle(
							new Error( 'Module Registry: Class not found for module "' + name + '"' ),
							'ModuleRegistry.initializeModule'
						);
						return null;
					}

					// Use Function.prototype.bind to pass dependencies as constructor arguments
					var BoundClass = Function.prototype.bind.apply( ModuleClass, [null].concat( deps ) );
					var instance = new BoundClass();

					modules[name] = instance;
					initialized[name] = true;


					// Call optional initialization hook (unless autoInit is false)
					var autoInit = moduleConfig.autoInit !== false; // Default to true
					if ( autoInit && typeof instance.init === 'function' ) {
						instance.init();
					}

					return instance;

				} catch ( error ) {
					WSSCD.ErrorHandler.handle(
						error,
						'ModuleRegistry.initializeModule',
						{ module: name, deps: moduleConfig.deps }
					);
					return null;
				}
			}

			// Initialize all modules
			for ( var moduleName in config ) {
				if ( config.hasOwnProperty( moduleName ) ) {
					initializeModule( moduleName );
				}
			}

			return modules;
		},

		/**
		 * Resolve class from string path or direct reference
		 *
		 * @param {string|Function} classRef Class reference (string path or function)
		 * @return {Function|null} Class constructor
		 */
		_resolveClass: function( classRef ) {
			// Already a function/constructor
			if ( typeof classRef === 'function' ) {
				return classRef;
			}

			// String path (e.g., 'WSSCD.Modules.Basic.State')
			if ( typeof classRef === 'string' ) {
				var parts = classRef.split( '.' );
				var current = window;

				for ( var i = 0; i < parts.length; i++ ) {
					if ( ! current[parts[i]] ) {
						return null;
					}
					current = current[parts[i]];
				}

				return typeof current === 'function' ? current : null;
			}

			return null;
		},

		/**
		 * Create module configuration for a step
		 *
		 * Helper method to create standard module configuration for wizard steps
		 *
		 * @param {string} stepName Step name (e.g., 'basic', 'products')
		 * @param {Object} customModules Optional custom modules to add
		 * @return {Object} Module configuration
		 *
		 * @example
		 * var config = WSSCD.Shared.ModuleRegistry.createStepConfig( 'basic', {
		 *     customModule: {
		 *         class: WSSCD.Modules.Basic.CustomModule,
		 *         deps: ['state', 'api']
		 *     }
		 * } );
		 */
		createStepConfig: function( stepName, customModules ) {
			var capitalizedStep = stepName.charAt( 0 ).toUpperCase() + stepName.slice( 1 );

			// Standard module pattern for wizard steps
			var config = {
				state: {
					class: 'WSSCD.Modules.' + capitalizedStep + '.State',
					deps: []
				},
				api: {
					class: 'WSSCD.Modules.' + capitalizedStep + '.API',
					deps: []
				}
			};

			// Check if step has a Fields module
			var FieldsClass = this._resolveClass( 'WSSCD.Modules.' + capitalizedStep + '.Fields' );
			if ( FieldsClass ) {
				config.fields = {
					class: 'WSSCD.Modules.' + capitalizedStep + '.Fields',
					deps: ['state']
				};
			}

			// Merge custom modules
			if ( customModules && typeof customModules === 'object' ) {
				for ( var moduleName in customModules ) {
					if ( customModules.hasOwnProperty( moduleName ) ) {
						config[moduleName] = customModules[moduleName];
					}
				}
			}

			return config;
		},

		/**
		 * Validate module configuration
		 *
		 * @param {Object} config Module configuration
		 * @return {Object} Validation result {valid: boolean, errors: array}
		 */
		validate: function( config ) {
			var errors = [];

			if ( ! config || typeof config !== 'object' ) {
				return {
					valid: false,
					errors: ['Configuration must be an object']
				};
			}

			// Check each module
			for ( var moduleName in config ) {
				if ( config.hasOwnProperty( moduleName ) ) {
					var moduleConfig = config[moduleName];

					// Must have class
					if ( ! moduleConfig.class ) {
						errors.push( 'Module "' + moduleName + '" missing class definition' );
					}

					// Validate class reference
					var ModuleClass = this._resolveClass( moduleConfig.class );
					if ( ! ModuleClass ) {
						errors.push( 'Module "' + moduleName + '" class "' + moduleConfig.class + '" not found' );
					}

					// Validate dependencies
					if ( moduleConfig.deps ) {
						if ( ! Array.isArray( moduleConfig.deps ) ) {
							errors.push( 'Module "' + moduleName + '" deps must be an array' );
						} else {
							// Check for self-dependency
							if ( moduleConfig.deps.indexOf( moduleName ) !== -1 ) {
								errors.push( 'Module "' + moduleName + '" has circular dependency (self-reference)' );
							}
						}
					}
				}
			}

			return {
				valid: errors.length === 0,
				errors: errors
			};
		}

	};

	// Module loaded
	if ( window.WSSCD && window.WSSCD.DebugLogger ) {
	WSSCD.DebugLogger.log( 'info', 'ModuleRegistry', 'Module Registry loaded' );
	}

} )( window, jQuery, window.WSSCD || {} );
