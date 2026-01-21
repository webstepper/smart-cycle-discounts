/**
 * Wizard Lifecycle
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/wizard/wizard-lifecycle.js
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

( function( $ ) {
	'use strict';

	window.WSSCD = window.WSSCD || {};
	window.WSSCD.Wizard = window.WSSCD.Wizard || {};

	/**
	 * Lifecycle Manager
	 *
	 * Handles wizard initialization, cleanup, and lifecycle events
	 */
	WSSCD.Wizard.Lifecycle = {
		// Lifecycle phases
		phases: {
			UNINITIALIZED: 'uninitialized',
			INITIALIZING: 'initializing',
			INITIALIZED: 'initialized',
			READY: 'ready',
			ACTIVE: 'active',
			PAUSED: 'paused',
			DESTROYING: 'destroying',
			DESTROYED: 'destroyed',
			ERROR: 'error'
		},

		// Current phase
		currentPhase: 'uninitialized',

		// Lifecycle hooks
		hooks: {
			beforeInit: [],
			afterInit: [],
			beforeReady: [],
			afterReady: [],
			beforeDestroy: [],
			afterDestroy: [],
			onError: []
		},

		// Components registry
		components: {},

		// Initialization queue
		initQueue: [],

		// Cleanup tasks
		cleanupTasks: [],

		/**
		 * Register a lifecycle hook
		 * @param phase
		 * @param callback
		 * @param priority
		 */
		addHook: function( phase, callback, priority ) {
			if ( !this.hooks[phase] ) {
				throw new Error( 'Invalid lifecycle phase: ' + phase );
			}

			if ( 'function' !== typeof callback ) {
				throw new Error( 'Callback must be a function' );
			}

			this.hooks[phase].push( {
				callback: callback,
				priority: priority || 10
			} );

			// Sort by priority
			this.hooks[phase].sort( function( a, b ) {
				return a.priority - b.priority;
			} );

			return this;
		},

		/**
		 * Execute hooks for a phase
		 * @param phase
		 * @param context
		 */
		executeHooks: function( phase, context ) {
			var self = this;
			var hooks = this.hooks[phase] || [];
			var results = [];

			hooks.forEach( function( hook ) {
				try {
					var result = hook.callback.call( context || self, self );
					results.push( result );
				} catch ( error ) {
					self.handleError( error, phase );
				}
			} );

			return results;
		},

		/**
		 * Register a component
		 * @param name
		 * @param component
		 * @param options
		 */
		registerComponent: function( name, component, options ) {
			options = options || {};

			this.components[name] = {
				instance: component,
				initialized: false,
				dependencies: options.dependencies || [],
				priority: options.priority || 10,
				required: false !== options.required
			};

			return this;
		},

		/**
		 * Initialize the wizard
		 * @param config
		 */
		init: function( config ) {
			var self = this;

			if ( this.currentPhase !== this.phases.UNINITIALIZED ) {
				// Already initialized - this is an error condition
				return $.Deferred().reject( new Error( 'Wizard already initialized' ) );
			}

			this.setPhase( this.phases.INITIALIZING );

			// Execute before init hooks
			this.executeHooks( 'beforeInit', this );

			return this.initializeComponents( config )
				.then( function() {
					self.setPhase( self.phases.INITIALIZED );
					self.executeHooks( 'afterInit', self );
					return self.ready();
				} )
				.fail( function( error ) {
					self.handleError( error, 'initialization' );
					return $.Deferred().reject( error );
				} );
		},

		/**
		 * Initialize components
		 * @param config
		 */
		initializeComponents: function( config ) {
			var self = this;
			var deferred = $.Deferred();

			// Sort components by dependencies and priority
			var sortedComponents = this.sortComponentsByDependencies();

			var initChain = $.Deferred().resolve();

			sortedComponents.forEach( function( componentName ) {
				initChain = initChain.then( function() {
					return self.initializeComponent( componentName, config );
				} );
			} );

			initChain
				.then( function() {
					deferred.resolve();
				} )
				.fail( function( error ) {
					deferred.reject( error );
				} );

			return deferred.promise();
		},

		/**
		 * Initialize single component
		 * @param name
		 * @param config
		 */
		initializeComponent: function( name, config ) {
			var component = this.components[name];

			if ( !component ) {
				return $.Deferred().reject( new Error( 'Component not found: ' + name ) );
			}

			if ( component.initialized ) {
				return $.Deferred().resolve();
			}

			var instance = component.instance;

			if ( instance && 'function' === typeof instance.init ) {
				try {
					var result = instance.init( config );
					component.initialized = true;

					// Handle promise or direct return
					if ( result && 'function' === typeof result.then ) {
						return result;
					} else {
						return $.Deferred().resolve( result );
					}
				} catch ( error ) {
					// All components must initialize successfully - no silent failures
					return $.Deferred().reject( error );
				}
			}

			component.initialized = true;
			return $.Deferred().resolve();
		},

		/**
		 * Sort components by dependencies
		 */
		sortComponentsByDependencies: function() {
			var sorted = [];
			var visited = {};
			var visiting = {};
			var self = this;

			var visit = function( name ) {
				if ( visited[name] ) {
					return;
				}

				if ( visiting[name] ) {
					throw new Error( 'Circular dependency detected: ' + name );
				}

				visiting[name] = true;

				var component = self.components[name];
				if ( component ) {
					component.dependencies.forEach( function( dep ) {
						visit( dep );
					} );
					sorted.push( name );
				}

				visiting[name] = false;
				visited[name] = true;
			};

			// Visit all components
			for ( var name in this.components ) {
				if ( !visited[name] ) {
					visit( name );
				}
			}

			return sorted;
		},

		/**
		 * Mark wizard as ready
		 */
		ready: function() {
			var self = this;

			this.executeHooks( 'beforeReady', this );

			// DOM should be ready - no artificial delays
			self.setPhase( self.phases.READY );
			self.executeHooks( 'afterReady', self );
			self.setPhase( self.phases.ACTIVE );
			return $.Deferred().resolve().promise();
		},

		/**
		 * Pause wizard
		 */
		pause: function() {
			if ( this.currentPhase === this.phases.ACTIVE ) {
				this.setPhase( this.phases.PAUSED );

				// Pause all components
				this.forEachComponent( function( component, _name ) {
					if ( component.instance && 'function' === typeof component.instance.pause ) {
						component.instance.pause();
					}
				} );
			}

			return this;
		},

		/**
		 * Resume wizard
		 */
		resume: function() {
			if ( this.currentPhase === this.phases.PAUSED ) {
				this.setPhase( this.phases.ACTIVE );

				// Resume all components
				this.forEachComponent( function( component, _name ) {
					if ( component.instance && 'function' === typeof component.instance.resume ) {
						component.instance.resume();
					}
				} );
			}

			return this;
		},

		/**
		 * Destroy wizard
		 */
		destroy: function() {
			var self = this;

			if ( this.currentPhase === this.phases.DESTROYED ) {
				return $.Deferred().resolve();
			}

			this.setPhase( this.phases.DESTROYING );

			// Execute before destroy hooks
			this.executeHooks( 'beforeDestroy', this );

			// Destroy components in reverse order
			var componentNames = Object.keys( this.components ).reverse();
			var destroyChain = $.Deferred().resolve();

			componentNames.forEach( function( name ) {
				destroyChain = destroyChain.then( function() {
					return self.destroyComponent( name );
				} );
			} );

			return destroyChain.then( function() {
				// Execute cleanup tasks
				self.executeCleanupTasks();

				self.components = {};
				self.hooks = {
					beforeInit: [],
					afterInit: [],
					beforeReady: [],
					afterReady: [],
					beforeDestroy: [],
					afterDestroy: [],
					onError: []
				};

				self.setPhase( self.phases.DESTROYED );
				self.executeHooks( 'afterDestroy', self );
			} );
		},

		/**
		 * Destroy single component
		 * @param name
		 */
		destroyComponent: function( name ) {
			var component = this.components[name];

			if ( !component || !component.initialized ) {
				return $.Deferred().resolve();
			}

			var instance = component.instance;

			if ( instance && 'function' === typeof instance.destroy ) {
				try {
					var result = instance.destroy();
					component.initialized = false;

					// Handle promise or direct return
					if ( result && 'function' === typeof result.then ) {
						return result;
					} else {
						return $.Deferred().resolve( result );
					}
				} catch ( error ) {
					// Component destruction errors must be handled properly
					return $.Deferred().reject( new Error( 'Error destroying component ' + name + ': ' + error.message ) );
				}
			}

			component.initialized = false;
			return $.Deferred().resolve();
		},

		/**
		 * Register cleanup task
		 * @param task
		 */
		registerCleanup: function( task ) {
			if ( 'function' === typeof task ) {
				this.cleanupTasks.push( task );
			}
			return this;
		},

		/**
		 * Execute cleanup tasks
		 */
		executeCleanupTasks: function() {
			var errors = [];

			this.cleanupTasks.forEach( function( task, index ) {
				try {
					task();
				} catch ( error ) {
					// Collect errors but continue cleanup
					errors.push( {
						task: index,
						error: error
					} );
				}
			} );
			this.cleanupTasks = [];

			// If there were errors, report them after all cleanup attempts
			if ( 0 < errors.length ) {
				var errorMessage = 'Cleanup failed for ' + errors.length + ' task(s): ' +
                    errors.map( function( e ) { return e.error.message; } ).join( ', ' );
				throw new Error( errorMessage );
			}
		},

		/**
		 * Set current phase
		 * @param phase
		 */
		setPhase: function( phase ) {
			var oldPhase = this.currentPhase;
			this.currentPhase = phase;

			// Emit phase change event
			if ( window.WSSCD && window.WSSCD.Wizard && window.WSSCD.Wizard.EventBus ) {
				window.WSSCD.Wizard.EventBus.emit( 'wizard:phaseChange', {
					oldPhase: oldPhase,
					newPhase: phase
				} );
			}
		},

		/**
		 * Get current phase
		 */
		getPhase: function() {
			return this.currentPhase;
		},

		/**
		 * Check if wizard is in specific phase
		 * @param phase
		 */
		isPhase: function( phase ) {
			return this.currentPhase === phase;
		},

		/**
		 * Check if wizard is ready
		 */
		isReady: function() {
			return this.currentPhase === this.phases.READY ||
                   this.currentPhase === this.phases.ACTIVE;
		},

		/**
		 * Handle lifecycle error
		 * @param error
		 * @param context
		 */
		handleError: function( error, context ) {
			this.setPhase( this.phases.ERROR );

			// Execute error hooks
			this.executeHooks( 'onError', { error: error, context: context } );

			// Emit error event
			if ( window.WSSCD && window.WSSCD.Wizard && window.WSSCD.Wizard.EventBus ) {
				window.WSSCD.Wizard.EventBus.emit( 'wizard:lifecycleError', {
					error: error,
					context: context,
					phase: this.currentPhase
				} );
			}

			// Now propagate the error after handling it
			throw error;
		},

		/**
		 * Iterate over components
		 * @param callback
		 */
		forEachComponent: function( callback ) {
			for ( var name in this.components ) {
				if ( Object.prototype.hasOwnProperty.call( this.components, name ) ) {
					callback( this.components[name], name );
				}
			}
		},

		/**
		 * Get component
		 * @param name
		 */
		getComponent: function( name ) {
			var component = this.components[name];
			return component ? component.instance : null;
		},

		/**
		 * Export lifecycle data
		 */
		exportLifecycleData: function() {
			var self = this;

			return {
				currentPhase: this.currentPhase,
				components: Object.keys( this.components ).map( function( name ) {
					var comp = self.components[name];
					return {
						name: name,
						initialized: comp.initialized,
						required: comp.required,
						priority: comp.priority,
						dependencies: comp.dependencies
					};
				} ),
				hooks: Object.keys( this.hooks ).reduce( function( acc, phase ) {
					acc[phase] = self.hooks[phase].length;
					return acc;
				}, {} ),
				cleanupTasks: this.cleanupTasks.length
			};
		}
	};

	// Expose to WSSCD namespace
	window.WSSCD = window.WSSCD || {};
	window.WSSCD.Wizard = window.WSSCD.Wizard || {};
	window.WSSCD.Wizard.LifecycleManager = WSSCD.Wizard.Lifecycle;

} )( jQuery );