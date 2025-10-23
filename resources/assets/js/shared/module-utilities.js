/**
 * Module Utilities
 *
 * Centralized utilities for module registration, initialization checks,
 * and common patterns to reduce code duplication across all modules.
 *
 * @package SmartCycleDiscounts
 * @since 1.0.0
 */

( function() {
	'use strict';

	window.SCD = window.SCD || {};
	SCD.Utils = SCD.Utils || {};

	/**
	 * Module registration utility
	 *
	 * Safely creates namespace hierarchy and registers modules
	 *
	 * @param {string} namespace - Full namespace path (e.g., 'SCD.Modules.Products')
	 * @param {string} name - Module name
	 * @param {Function | object} implementation - Module implementation
	 */
	SCD.Utils.registerModule = function( namespace, name, implementation ) {
		var parts = namespace.split( '.' );
		var current = window;
		var i;

		// Create namespace hierarchy
		for ( i = 0; i < parts.length; i++ ) {
			if ( 'undefined' === typeof current[parts[i]] ) {
				current[parts[i]] = {};
			}
			current = current[parts[i]];
		}

		// Register the module
		current[name] = implementation;

		// Auto-register with ModuleLoader if available
		if ( window.SCD && window.SCD.ModuleLoader && window.SCD.ModuleLoader.modules ) {
			SCD.ModuleLoader.modules[namespace + '.' + name] = true;
		}
	};

	/**
	 * Initialization helper with overload support
	 *
	 * Can be called in two ways:
	 * 1. ensureInitialized(modulePath, factory) - Creates module instance
	 * 2. ensureInitialized(context, dependencies, moduleName) - Checks initialization
	 *
	 * @param {string | object} pathOrContext - Module path string or context object
	 * @param {Function | object} factoryOrDeps - Factory function or dependencies
	 * @param {string} moduleName - Module name for error reporting
	 * @returns {object | boolean} Module instance or initialization status
	 */
	SCD.Utils.ensureInitialized = function( pathOrContext, factoryOrDeps, moduleName ) {
		// Overload 1: Module creation (path, factory)
		if ( 'string' === typeof pathOrContext && 'function' === typeof factoryOrDeps ) {
			var modulePath = pathOrContext;
			var factory = factoryOrDeps;

			// Get the module constructor
			var ModuleConstructor = SCD.Utils.get( window, modulePath );

			if ( 'function' === typeof ModuleConstructor ) {
				// Module exists, create instance
				return factory();
			} else {
				throw new Error( 'Module not found: ' + modulePath );
			}
		}

		// Overload 2: Original initialization check (context, dependencies, moduleName)
		var context = pathOrContext;
		var dependencies = factoryOrDeps;

		// Check if already initialized
		if ( context.initialized ) {
			return false;
		}

		// Check all dependencies
		if ( dependencies ) {
			var key;
			for ( key in dependencies ) {
				if ( Object.prototype.hasOwnProperty.call( dependencies, key ) && !dependencies[key] ) {
					if ( SCD.ErrorHandler ) {
						SCD.ErrorHandler.handle(
							new Error( 'Missing dependency: ' + key ),
							moduleName || 'Unknown Module'
						);
					} else {
						console.error( '[' + ( moduleName || 'SCD' ) + '] Missing dependency: ' + key );
					}
					return false;
				}
			}
		}

		// Mark as initialized
		context.initialized = true;
		return true;
	};

	/**
	 * Extend object utility
	 *
	 * Copies properties from source to target object
	 *
	 * @param {object} target - Target object
	 * @param {object} source - Source object
	 * @returns {object} Extended target object
	 */
	SCD.Utils.extend = function( target, source ) {
		if ( !target || !source ) {
			return target;
		}

		for ( var key in source ) {
			if ( Object.prototype.hasOwnProperty.call( source, key ) ) {
				target[key] = source[key];
			}
		}

		return target;
	};

	/**
	 * Safe property getter
	 *
	 * Safely gets nested object properties without throwing errors
	 *
	 * @param {object} obj - Source object
	 * @param {string} path - Property path (e.g., 'data.user.name')
	 * @param {*} defaultValue - Default value if property doesn't exist
	 * @returns {*} Property value or default
	 */
	SCD.Utils.get = function( obj, path, defaultValue ) {
		if ( !obj || 'string' !== typeof path ) {
			return defaultValue;
		}

		var parts = path.split( '.' );
		var current = obj;
		var i;

		for ( i = 0; i < parts.length; i++ ) {
			if ( null === current || 'undefined' === typeof current[parts[i]] ) {
				return defaultValue;
			}
			current = current[parts[i]];
		}

		return current;
	};

	// Debounce function removed - use SCD.Utils.debounce from utils.js instead

	/**
	 * Extend objects (WordPress-compatible alternative to $.extend)
	 *
	 * @param {object} target - Target object
	 * @returns {object} Extended object
	 */
	SCD.Utils.extend = function( target ) {
		var sources = Array.prototype.slice.call( arguments, 1 );
		var source, key, i;

		for ( i = 0; i < sources.length; i++ ) {
			source = sources[i];
			if ( source ) {
				for ( key in source ) {
					if ( Object.prototype.hasOwnProperty.call( source, key ) ) {
						target[key] = source[key];
					}
				}
			}
		}

		return target;
	};

	// isEmpty function removed - use SCD.Utils.isEmpty from utils.js instead

} )();