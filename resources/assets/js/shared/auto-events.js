/**
 * Auto Events - Convention-Based Event Binding System
 *
 * Eliminates manual event binding duplication by providing
 * automatic event binding via data attributes.
 *
 * @since 2.0.0
 * @package Smart_Cycle_Discounts
 */

( function( window, $, WSSCD ) {
	'use strict';

	if ( ! WSSCD || ! WSSCD.Shared ) {
		console.error( 'WSSCD.Shared is not defined. Auto Events cannot initialize.' );
		return;
	}

	/**
	 * Auto Events System
	 *
	 * Provides declarative event binding using data attributes
	 *
	 * @example
	 * HTML:
	 * <button data-wsscd-on="click" data-wsscd-action="handleAddTier">Add Tier</button>
	 * <input data-wsscd-on="change input" data-wsscd-action="updateDiscount">
	 * <select data-wsscd-on="change" data-wsscd-action="handleTypeChange" data-wsscd-args='{"type":"discount"}'>
	 *
	 * JavaScript:
	 * WSSCD.Shared.AutoEvents.bind( this.$container, this );
	 * // Automatically binds all events in container to 'this' context
	 */
	WSSCD.Shared.AutoEvents = {

		/**
		 * Bind all auto events in container
		 *
		 * Scans container for elements with data-wsscd-on attributes
		 * and automatically binds event handlers
		 *
		 * @param {jQuery} $container Container element to scan
		 * @param {Object} context Context object containing handler methods
		 * @param {Object} options Optional configuration
		 * @return {Array} Array of bound event descriptors for cleanup
		 */
		bind: function( $container, context, options ) {
			var self = this;
			var boundEvents = [];

			// Validate parameters
			if ( ! $container || ! $container.length ) {
				WSSCD.ErrorHandler.handle(
					new Error( 'Auto Events: Invalid container provided' ),
					'AutoEvents.bind'
				);
				return boundEvents;
			}

			if ( ! context || typeof context !== 'object' ) {
				WSSCD.ErrorHandler.handle(
					new Error( 'Auto Events: Invalid context provided' ),
					'AutoEvents.bind'
				);
				return boundEvents;
			}

			// Default options
			var settings = $.extend( {
				namespace: 'wsscd-auto',
				eventAttr: 'data-wsscd-on',
				actionAttr: 'data-wsscd-action',
				argsAttr: 'data-wsscd-args',
				delegated: true,
				preventDefault: true,
				stopPropagation: false
			}, options || {} );

			// Find all elements with event bindings
			var selector = '[' + settings.eventAttr + ']';
			var $elements = $container.find( selector ).addBack( selector );

			$elements.each( function() {
				var $element = $( this );
				var events = $element.attr( settings.eventAttr );
				var action = $element.attr( settings.actionAttr );

				if ( ! events || ! action ) {
					return; // Continue to next element
				}

				// Split multiple events (e.g., "change input")
				var eventList = events.split( ' ' );

				// Get optional arguments
				var args = null;
				var argsStr = $element.attr( settings.argsAttr );
				if ( argsStr ) {
					try {
						args = JSON.parse( argsStr );
					} catch ( e ) {
						WSSCD.DebugLogger.warn( 'Auto Events: Failed to parse args for action "' + action + '"', 'AutoEvents' );
					}
				}

				// Bind each event
				for ( var i = 0; i < eventList.length; i++ ) {
					var eventType = eventList[i].trim();
					if ( ! eventType ) {
						continue;
					}

					var eventDescriptor = self._bindEvent(
						$container,
						$element,
						eventType,
						action,
						context,
						settings,
						args
					);

					if ( eventDescriptor ) {
						boundEvents.push( eventDescriptor );
					}
				}
			} );

			if ( window.WSSCD && window.WSSCD.DebugLogger ) {
				WSSCD.DebugLogger.log(
					'info',
					'AutoEvents',
					'Auto Events: Bound ' + boundEvents.length + ' events in container'
				);
			}

			return boundEvents;
		},

		/**
		 * Bind a single event
		 *
		 * @param {jQuery} $container Container element
		 * @param {jQuery} $element Target element
		 * @param {string} eventType Event type (e.g., 'click', 'change')
		 * @param {string} action Handler method name
		 * @param {Object} context Context object
		 * @param {Object} settings Settings object
		 * @param {*} args Optional arguments to pass to handler
		 * @return {Object|null} Event descriptor for cleanup
		 */
		_bindEvent: function( $container, $element, eventType, action, context, settings, args ) {
			// Check if handler exists
			if ( typeof context[action] !== 'function' ) {
				WSSCD.ErrorHandler.handle(
					new Error( 'Auto Events: Handler method "' + action + '" not found in context' ),
					'AutoEvents._bindEvent'
				);
				return null;
			}

			// Generate event name with namespace
			var namespacedEvent = eventType + '.' + settings.namespace;

			// Create handler wrapper
			var handler = function( event ) {
				// Prevent default if configured
				if ( settings.preventDefault ) {
					event.preventDefault();
				}

				// Stop propagation if configured
				if ( settings.stopPropagation ) {
					event.stopPropagation();
				}

				// Call handler with context
				try {
					if ( args ) {
						context[action].call( context, event, args );
					} else {
						context[action].call( context, event );
					}
				} catch ( error ) {
					WSSCD.ErrorHandler.handle(
						error,
						'AutoEvents.handler',
						{ action: action, eventType: eventType }
					);
				}
			};

			// Bind event (delegated or direct)
			if ( settings.delegated ) {
				// Get selector for delegation
				var selector = this._getSelector( $element );
				if ( selector ) {
					$container.on( namespacedEvent, selector, handler );
				} else {
					// Fallback to direct binding if no selector available
					$element.on( namespacedEvent, handler );
				}
			} else {
				// Direct binding
				$element.on( namespacedEvent, handler );
			}

			// Return descriptor for cleanup
			return {
				$container: $container,
				$element: $element,
				event: namespacedEvent,
				action: action,
				delegated: settings.delegated,
				selector: settings.delegated ? this._getSelector( $element ) : null
			};
		},

		/**
		 * Generate selector for element
		 *
		 * @param {jQuery} $element Element to generate selector for
		 * @return {string|null} CSS selector
		 */
		_getSelector: function( $element ) {
			// Use ID if available
			var id = $element.attr( 'id' );
			if ( id ) {
				return '#' + id;
			}

			// Use name attribute if available
			var name = $element.attr( 'name' );
			if ( name ) {
				return '[name="' + name + '"]';
			}

			// Use class if unique
			var classes = $element.attr( 'class' );
			if ( classes ) {
				var classList = classes.split( ' ' );
				for ( var i = 0; i < classList.length; i++ ) {
					var className = classList[i].trim();
					if ( className && className.indexOf( 'wsscd-' ) === 0 ) {
						return '.' + className;
					}
				}
			}

			// Use data-wsscd-action as last resort
			var action = $element.attr( 'data-wsscd-action' );
			if ( action ) {
				return '[data-wsscd-action="' + action + '"]';
			}

			return null;
		},

		/**
		 * Unbind all auto events
		 *
		 * @param {jQuery} $container Container element
		 * @param {string} namespace Event namespace (default: 'wsscd-auto')
		 * @return {void}
		 */
		unbind: function( $container, namespace ) {
			namespace = namespace || 'wsscd-auto';

			if ( ! $container || ! $container.length ) {
				return;
			}

			$container.off( '.' + namespace );

			if ( window.WSSCD && window.WSSCD.DebugLogger ) {
				WSSCD.DebugLogger.log(
					'info',
					'AutoEvents',
					'Auto Events: Unbound all events with namespace "' + namespace + '"'
				);
			}
		},

		/**
		 * Unbind specific events by descriptors
		 *
		 * @param {Array} descriptors Array of event descriptors from bind()
		 * @return {void}
		 */
		unbindDescriptors: function( descriptors ) {
			if ( ! descriptors || ! Array.isArray( descriptors ) ) {
				return;
			}

			for ( var i = 0; i < descriptors.length; i++ ) {
				var descriptor = descriptors[i];

				if ( descriptor.delegated && descriptor.selector ) {
					descriptor.$container.off( descriptor.event, descriptor.selector );
				} else {
					descriptor.$element.off( descriptor.event );
				}
			}
		},

		/**
		 * Rebind events after DOM changes
		 *
		 * Useful when container content is dynamically updated
		 *
		 * @param {jQuery} $container Container element
		 * @param {Object} context Context object
		 * @param {Object} options Optional configuration
		 * @return {Array} New array of bound event descriptors
		 */
		rebind: function( $container, context, options ) {
			// Unbind existing events
			var namespace = ( options && options.namespace ) || 'wsscd-auto';
			this.unbind( $container, namespace );

			// Bind again
			return this.bind( $container, context, options );
		},

		/**
		 * Create data attributes helper
		 *
		 * Helper to generate HTML data attributes for event binding
		 *
		 * @param {string} events Space-separated event types
		 * @param {string} action Handler method name
		 * @param {Object} args Optional arguments object
		 * @return {string} HTML attribute string
		 *
		 * @example
		 * var attrs = WSSCD.Shared.AutoEvents.attrs( 'click', 'handleAddTier', {type: 'percentage'} );
		 * // Returns: 'data-wsscd-on="click" data-wsscd-action="handleAddTier" data-wsscd-args='{"type":"percentage"}''
		 */
		attrs: function( events, action, args ) {
			var attrString = 'data-wsscd-on="' + events + '" data-wsscd-action="' + action + '"';

			if ( args && typeof args === 'object' ) {
				var argsJson = JSON.stringify( args ).replace( /"/g, '&quot;' );
				attrString += ' data-wsscd-args="' + argsJson + '"';
			}

			return attrString;
		},

		/**
		 * Check if auto events are supported
		 *
		 * @return {boolean} True if supported
		 */
		isSupported: function() {
			return typeof jQuery !== 'undefined' &&
			       typeof jQuery.fn.on === 'function' &&
			       typeof document.querySelectorAll === 'function';
		}

	};

	// Module loaded
	if ( window.WSSCD && window.WSSCD.DebugLogger ) {
		WSSCD.DebugLogger.log( 'info', 'AutoEvents', 'Auto Events loaded' );
	}

} )( window, jQuery, window.WSSCD || {} );
