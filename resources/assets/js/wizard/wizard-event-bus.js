/**
 * Wizard Event Bus
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/wizard/wizard-event-bus.js
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

( function( $ ) {
	'use strict';

	window.SCD = window.SCD || {};
	window.SCD.Wizard = window.SCD.Wizard || {};

	/**
	 * Event Bus
	 *
	 * Manages all wizard events with namespacing and cleanup
	 */
	window.SCD.Wizard.EventBus = {
		// Event namespaces
		namespaces: {
			wizard: 'scd-wizard',
			steps: 'scd-wizard-steps',
			validation: 'scd-wizard-validation',
			ajax: 'scd-wizard-ajax',
			ui: 'scd-wizard-ui'
		},

		// Registered events
		events: {},

		// Event history for debugging
		history: [],
		maxHistorySize: 100,

		// Event statistics
		stats: {
			emitted: 0,
			handled: 0,
			errors: 0
		},

		/**
		 * Initialize event bus
		 */
		init: function() {
			this.setupGlobalHandlers();
			this.startEventMonitoring();
			return this;
		},

		/**
		 * Emit an event
		 * @param eventName
		 * @param data
		 * @param options
		 */
		emit: function( eventName, data, options ) {
			options = options || {};

			// Add to history
			this.addToHistory( 'emit', eventName, data );

			// Update stats
			this.stats.emitted++;

			// Create event object
			var eventData = $.extend( {
				timestamp: new Date().toISOString(),
				source: options.source || 'unknown',
				eventName: eventName
			}, data );

			// Trigger jQuery event
			var $target = options.target || $( document );
			$target.trigger( this.getEventName( eventName, options.namespace ), eventData );

			// Handle direct listeners
			this.handleDirectListeners( eventName, eventData );

			return this;
		},

		/**
		 * Listen to an event
		 * @param eventName
		 * @param handler
		 * @param options
		 */
		on: function( eventName, handler, options ) {
			options = options || {};

			if ( 'function' !== typeof handler ) {
				return;
			}

			// Store in registry
			if ( !this.events[eventName] ) {
				this.events[eventName] = [];
			}

			var listener = {
				handler: handler,
				namespace: options.namespace || 'wizard',
				once: options.once || false,
				priority: options.priority || 10,
				id: this.generateListenerId()
			};

			this.events[eventName].push( listener );

			// Sort by priority
			this.events[eventName].sort( function( a, b ) {
				return b.priority - a.priority;
			} );

			// Also bind to jQuery if using DOM events
			if ( false !== options.useDom ) {
				var $target = options.target || $( document );
				var fullEventName = this.getEventName( eventName, options.namespace );

				if ( options.once ) {
					$target.one( fullEventName, handler );
				} else {
					$target.on( fullEventName, handler );
				}
			}

			// Return unsubscribe function
			var self = this;
			return function() {
				self.off( eventName, handler );
			};
		},

		/**
		 * Listen to an event once
		 * @param eventName
		 * @param handler
		 * @param options
		 */
		once: function( eventName, handler, options ) {
			options = options || {};
			options.once = true;
			return this.on( eventName, handler, options );
		},

		/**
		 * Remove event listener
		 * @param eventName
		 * @param handler
		 * @param options
		 */
		off: function( eventName, handler, options ) {
			options = options || {};

			// Remove from registry
			if ( this.events[eventName] ) {
				this.events[eventName] = this.events[eventName].filter( function( listener ) {
					return listener.handler !== handler;
				} );

				if ( 0 === this.events[eventName].length ) {
					delete this.events[eventName];
				}
			}

			// Also unbind from jQuery
			var $target = options.target || $( document );
			var fullEventName = this.getEventName( eventName, options.namespace );
			$target.off( fullEventName, handler );

			return this;
		},

		/**
		 * Remove all listeners for an event
		 * @param eventName
		 * @param options
		 */
		offAll: function( eventName, options ) {
			options = options || {};

			// Remove from registry
			if ( eventName ) {
				delete this.events[eventName];
			} else {
				this.events = {};
			}

			// Remove jQuery listeners
			var $target = options.target || $( document );
			if ( eventName ) {
				$target.off( this.getEventName( eventName, options.namespace ) );
			} else {
				// Remove all wizard events
				for ( var ns in this.namespaces ) {
					$target.off( '.' + this.namespaces[ns] );
				}
			}

			return this;
		},

		/**
		 * Handle direct listeners
		 * @param eventName
		 * @param data
		 */
		handleDirectListeners: function( eventName, data ) {
			var self = this;
			var listeners = this.events[eventName] || [];

			listeners.forEach( function( listener ) {
				try {
					self.stats.handled++;

					// Call handler
					listener.handler.call( null, { type: eventName }, data );

					// Remove if once
					if ( listener.once ) {
						self.off( eventName, listener.handler );
					}
				} catch ( error ) {
					self.stats.errors++;
					self.handleError( eventName, error, listener );
				}
			} );
		},

		/**
		 * Get full event name with namespace
		 * @param eventName
		 * @param namespace
		 */
		getEventName: function( eventName, namespace ) {
			namespace = namespace || 'wizard';
			var ns = this.namespaces[namespace] || namespace;
			return eventName + '.' + ns;
		},

		/**
		 * Clean up events for a namespace
		 * @param namespace
		 */
		cleanup: function( namespace ) {
			var ns = this.namespaces[namespace] || namespace;

			// Remove jQuery events
			$( document ).off( '.' + ns );
			$( window ).off( '.' + ns );

			// Remove from registry
			if ( namespace ) {
				for ( var eventName in this.events ) {
					this.events[eventName] = this.events[eventName].filter( function( listener ) {
						return listener.namespace !== namespace;
					} );

					if ( 0 === this.events[eventName].length ) {
						delete this.events[eventName];
					}
				}
			}

			return this;
		},

		/**
		 * Setup global event handlers
		 */
		setupGlobalHandlers: function() {
			var self = this;


			// Error handling
			$( window ).on( 'error.' + this.namespaces.wizard, function( e ) {
				self.emit( 'wizard:error', {
					message: e.originalEvent.message,
					filename: e.originalEvent.filename,
					line: e.originalEvent.lineno,
					column: e.originalEvent.colno,
					error: e.originalEvent.error
				} );
			} );
		},

		/**
		 * Start event monitoring
		 */
		startEventMonitoring: function() {
			// Monitor performance
			if ( window.performance && window.performance.mark ) {
				this.on( '*', function( e, _data ) {
					window.performance.mark( 'scd-event-' + e.type );
				} );
			}
		},

		/**
		 * Add event to history
		 * @param action
		 * @param eventName
		 * @param data
		 */
		addToHistory: function( action, eventName, data ) {
			this.history.push( {
				action: action,
				eventName: eventName,
				data: data,
				timestamp: new Date().toISOString(),
				stack: this.getCallStack()
			} );

			// Limit history size
			if ( this.history.length > this.maxHistorySize ) {
				this.history.shift();
			}
		},

		/**
		 * Get call stack for debugging
		 */
		getCallStack: function() {
			try {
				throw new Error();
			} catch ( e ) {
				var stack = e.stack || '';
				var lines = stack.split( '\n' );
				// Remove first 3 lines (Error, getCallStack, addToHistory)
				return lines.slice( 3, 8 ).join( '\n' );
			}
		},

		/**
		 * Handle event error
		 * @param eventName
		 * @param error
		 * @param listener
		 */
		handleError: function( eventName, error, listener ) {
			// Emit error event for proper handling
			this.emit( 'wizard:eventError', {
				eventName: eventName,
				error: error.message || error,
				listenerId: listener.id
			}, { namespace: 'system' } );
		},

		/**
		 * Generate unique listener ID
		 */
		generateListenerId: function() {
			return 'listener_' + Date.now() + '_' + Math.random().toString( 36 ).substr( 2, 9 );
		}
	};

	// Trigger service ready event
	$( document ).trigger( 'scd:service:ready', [ 'EventBus' ] );

} )( jQuery );