/**
 * Module Loader
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/shared/module-loader.js
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress/plugins/smart-cycle-discounts/
 * @since      1.0.0
 */

( function( window, document ) {
	'use strict';

	// Ensure namespace exists
	window.WSSCD = window.WSSCD || {};

	/**
	 * Module Loader
	 */
	WSSCD.ModuleLoader = {

		// Module registry
		modules: {},

		// Loading states
		states: {},

		// Module dependencies
		dependencies: {},

		// Base path for modules ( lazy initialization )
		basePath: null,

		/**
		 * Get base path for modules
		 */
		getBasePath: function() {
			if ( !this.basePath ) {
				// Plugin URL should be properly localized by WordPress
				if ( window.wsscdWizardData && window.wsscdWizardData.pluginUrl ) {
					// Ensure proper URL construction with trailing slash handling
					var pluginUrl = window.wsscdWizardData.pluginUrl;
					this.basePath = pluginUrl.replace( /\/$/, '' ) + '/resources/assets/js/steps/';
				} else {
					// Configuration error - plugin URL must be localized
					throw new Error( 'Plugin URL not properly localized. Check wp_localize_script configuration.' );
				}
			}
			return this.basePath;
		},

		/**
		 * Register a module
		 *
		 * @param {string} name Module name
		 * @param {object} module Module object
		 * @param {Array} deps Module dependencies
		 */
		register: function( name, module, deps ) {
			deps = deps || [];
			this.modules[name] = module;
			this.dependencies[name] = deps;
			this.states[name] = 'registered';
		},

		/**
		 * Load a single module
		 *
		 * @param {string} moduleName Module name
		 * @param {Function} callback Callback when loaded
		 */
		load: function( moduleName, callback ) {
			if ( 'loaded' === this.states[moduleName] ) {
				if ( callback ) {callback( this.modules[moduleName] );}
				return;
			}

			if ( 'loading' === this.states[moduleName] ) {
				// Wait for it to load
				this.waitForModule( moduleName, callback );
				return;
			}

			// Mark as loading
			this.states[moduleName] = 'loading';

			// Determine module path
			var modulePath = this.getModulePath( moduleName );

			var script = document.createElement( 'script' );
			script.type = 'text/javascript';
			script.src = modulePath;
			script.async = true;

			// Handle load success
			var self = this;
			script.onload = function() {
				// Module should register itself synchronously
				self.states[moduleName] = 'loaded';

				// Module must register itself immediately on load
				if ( !self.modules[moduleName] ) {
					self.states[moduleName] = 'error';

					// Trigger error event
					if ( 'undefined' !== typeof $ ) {
						$( document ).trigger( 'wsscd:module:error:' + moduleName );
					}

					throw new Error( 'Module ' + moduleName + ' failed to register itself properly' );
				}

				// Trigger loaded event for waiting callbacks
				if ( 'undefined' !== typeof $ ) {
					$( document ).trigger( 'wsscd:module:loaded:' + moduleName, [ self.modules[moduleName] ] );
				}

				if ( callback ) {
					callback( self.modules[moduleName] );
				}
			};

			// Handle load error
			script.onerror = function() {
				self.states[moduleName] = 'error';

				// Trigger error events
				if ( 'undefined' !== typeof $ && $( document ).trigger ) {
					// General error event
					$( document ).trigger( 'wsscd:module:error', {
						module: moduleName,
						path: modulePath
					} );

					// Specific module error event for waiting callbacks
					$( document ).trigger( 'wsscd:module:error:' + moduleName );
				}

				if ( callback ) {callback( null );}
			};

			// Append to document
			document.head.appendChild( script );
		},

		/**
		 * Load multiple modules
		 *
		 * @param {Array} moduleNames Array of module names
		 * @param {Function} callback Callback when all loaded
		 */
		loadModules: function( moduleNames, callback ) {
			var loadedCount = 0;
			var totalModules = moduleNames.length;

			if ( 0 === totalModules ) {
				if ( callback ) {callback();}
				return;
			}

			var self = this;
			for ( var i = 0; i < moduleNames.length; i++ ) {
				( function( moduleName ) {
					self.load( moduleName, function() {
						loadedCount++;
						if ( loadedCount === totalModules && callback ) {
							callback();
						}
					} );
				} )( moduleNames[i] );
			}
		},

		/**
		 * Load module with dependencies
		 *
		 * @param {string} moduleName Module name
		 * @param {Function} callback Callback when loaded
		 */
		loadWithDependencies: function( moduleName, callback ) {
			var deps = this.dependencies[moduleName] || [];

			var self = this;
			this.loadModules( deps, function() {
				// Then load the module
				self.load( moduleName, callback );
			} );
		},

		/**
		 * Get module path
		 *
		 * @param {string} moduleName Module name
		 * @returns {string} Module path
		 */
		getModulePath: function( moduleName ) {
			var basePath = this.getBasePath();

			// Ensure base path ends with a slash
			if ( '/' !== basePath.charAt( basePath.length - 1 ) ) {
				basePath += '/';
			}

			// Handle shared modules
			if ( -1 !== moduleName.indexOf( 'utils' ) ) {
				return basePath + 'shared/' + moduleName + '.js';
			}

			// Handle basic step modules
			if ( 0 === moduleName.indexOf( 'basic-' ) ) {
				return basePath + 'basic/' + moduleName + '.js';
			}

			// Handle products step modules
			if ( 0 === moduleName.indexOf( 'products-' ) ) {
				return basePath + 'products/' + moduleName + '.js';
			}

			// Handle discounts step modules
			if ( 0 === moduleName.indexOf( 'discounts-' ) || 0 === moduleName.indexOf( 'discount-' ) ) {
				return basePath + 'discounts/' + moduleName + '.js';
			}

			// Handle review step modules
			if ( 0 === moduleName.indexOf( 'review-' ) ) {
				return basePath + 'review/' + moduleName + '.js';
			}

			// Handle schedule modules
			var scheduleModules = [
				'schedule-config',
				'schedule-validation',
				'date-time-picker',
				'timeline-visualizer'
			];

			if ( 0 === moduleName.indexOf( 'schedule-' ) || -1 !== scheduleModules.indexOf( moduleName ) ) {
				return basePath + 'schedule/' + moduleName + '.js';
			}

			// Default path
			return basePath + moduleName + '.js';
		},

		/**
		 * Wait for module to load using proper event-based waiting
		 *
		 * @param {string} moduleName Module name
		 * @param {Function} callback Callback when loaded
		 */
		waitForModule: function( moduleName, callback ) {
			var self = this;

			if ( 'loaded' === this.states[moduleName] ) {
				if ( callback ) {callback( this.modules[moduleName] );}
				return;
			} else if ( 'error' === this.states[moduleName] ) {
				if ( callback ) {callback( null );}
				return;
			}

			// Module is loading - create a proper event listener
			var waitingCallbacks = this.waitingCallbacks = this.waitingCallbacks || {};
			if ( !waitingCallbacks[moduleName] ) {
				waitingCallbacks[moduleName] = [];
			}

			if ( callback ) {
				waitingCallbacks[moduleName].push( callback );
			}

			if ( !this.moduleListeners || !this.moduleListeners[moduleName] ) {
				this.moduleListeners = this.moduleListeners || {};
				this.moduleListeners[moduleName] = true;

				// Use proper event system if available
				if ( 'undefined' !== typeof $ ) {
					$( document ).one( 'wsscd:module:loaded:' + moduleName, function( e, module ) {
						var callbacks = waitingCallbacks[moduleName] || [];
						for ( var i = 0; i < callbacks.length; i++ ) {
							callbacks[i]( module );
						}
						waitingCallbacks[moduleName] = [];
						self.moduleListeners[moduleName] = false;
					} );

					$( document ).one( 'wsscd:module:error:' + moduleName, function() {
						var callbacks = waitingCallbacks[moduleName] || [];
						for ( var i = 0; i < callbacks.length; i++ ) {
							callbacks[i]( null );
						}
						waitingCallbacks[moduleName] = [];
						self.moduleListeners[moduleName] = false;
					} );
				}
			}
		},

		/**
		 * Get loaded module
		 *
		 * @param {string} moduleName Module name
		 * @returns {object | null} Module or null
		 */
		get: function( moduleName ) {
			return this.modules[moduleName] || null;
		},

		/**
		 * Check if module is loaded
		 *
		 * @param {string} moduleName Module name
		 * @returns {boolean} True if loaded
		 */
		isLoaded: function( moduleName ) {
			return 'loaded' === this.states[moduleName];
		}
	};

	// Shorthand
	WSSCD.loadModule = WSSCD.ModuleLoader.load.bind( WSSCD.ModuleLoader );
	WSSCD.loadModules = WSSCD.ModuleLoader.loadModules.bind( WSSCD.ModuleLoader );

} )( window, document );