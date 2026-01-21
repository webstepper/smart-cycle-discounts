/**
 * Event Manager Mixin
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/shared/mixins/event-manager-mixin.js
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

( function( $ ) {
	'use strict';

	window.WSSCD = window.WSSCD || {};
	WSSCD.Mixins = WSSCD.Mixins || {};

	/**
	 * Event Manager Mixin
	 *
	 * Provides methods for managing event listeners with automatic cleanup
	 *
	 * Usage:
	 * WSSCD.Utils.extend( MyClass.prototype, WSSCD.Mixins.EventManager );
	 */
	WSSCD.Mixins.EventManager = {

		/**
		 * Initialize event manager
		 * Call this in your module's constructor
		 */
		initEventManager: function() {
			this._eventHandlers = this._eventHandlers || [];
			this._eventNamespaces = this._eventNamespaces || [];
			this._moduleId = this._moduleId || 'wsscd_' + Math.random().toString( 36 ).substr( 2, 9 );
		},

		/**
		 * Bind an event with automatic tracking for cleanup
		 *
		 * @param {jQuery|Element|string} element - Element to bind to
		 * @param {string} events - Event types (space separated)
		 * @param {Function} handler - Event handler function
		 * @param {object} data - Optional data to pass to handler
		 */
		bindEvent: function( element, events, handler, data ) {
			if ( !this._eventHandlers ) {
				this.initEventManager();
			}

			var $element = $( element );
			var eventTypes = events.split( ' ' );
			var namespacedEvents = [];
			var i;

			for ( i = 0; i < eventTypes.length; i++ ) {
				namespacedEvents.push( eventTypes[i] + '.' + this._moduleId );
			}

			var namespacedEventString = namespacedEvents.join( ' ' );

			// Bind the event
			if ( data ) {
				$element.on( namespacedEventString, data, handler );
			} else {
				$element.on( namespacedEventString, handler );
			}

			// Track for cleanup
			this._eventHandlers.push( {
				element: $element,
				events: namespacedEventString,
				handler: handler,
				data: data
			} );
		},

		/**
		 * Bind a delegated event
		 *
		 * @param {jQuery|Element|string} container - Container element
		 * @param {string} selector - Selector for delegated elements
		 * @param {string} events - Event types (space separated)
		 * @param {Function} handler - Event handler function
		 * @param {object} data - Optional data to pass to handler
		 */
		bindDelegatedEvent: function( container, selector, events, handler, data ) {
			if ( !this._eventHandlers ) {
				this.initEventManager();
			}

			var $container = $( container );
			var eventTypes = events.split( ' ' );
			var namespacedEvents = [];
			var i;

			for ( i = 0; i < eventTypes.length; i++ ) {
				namespacedEvents.push( eventTypes[i] + '.' + this._moduleId );
			}

			var namespacedEventString = namespacedEvents.join( ' ' );

			// Bind the delegated event
			if ( data ) {
				$container.on( namespacedEventString, selector, data, handler );
			} else {
				$container.on( namespacedEventString, selector, handler );
			}

			// Track for cleanup
			this._eventHandlers.push( {
				element: $container,
				events: namespacedEventString,
				handler: handler,
				selector: selector,
				data: data,
				delegated: true
			} );
		},

		/**
		 * Bind a custom event on document
		 *
		 * @param {string} eventName - Custom event name
		 * @param {Function} handler - Event handler function
		 */
		bindCustomEvent: function( eventName, handler ) {
			if ( !this._eventHandlers ) {
				this.initEventManager();
			}

			var namespacedEvent = eventName + '.' + this._moduleId;

			$( document ).on( namespacedEvent, handler );

			// Track for cleanup
			this._eventHandlers.push( {
				element: $( document ),
				events: namespacedEvent,
				handler: handler,
				custom: true
			} );
		},

		/**
		 * Trigger a custom event
		 *
		 * @param {string} eventName - Event name to trigger
		 * @param {Array} data - Data to pass with event
		 */
		triggerCustomEvent: function( eventName, data ) {
			if ( data ) {
				$( document ).trigger( eventName, data );
			} else {
				$( document ).trigger( eventName );
			}
		},

		/**
		 * Unbind a specific event
		 *
		 * @param {jQuery|Element|string} element - Element to unbind from
		 * @param {string} events - Event types to unbind
		 */
		unbindEvent: function( element, events ) {
			if ( !this._eventHandlers ) {
				return;
			}

			var $element = $( element );
			var eventTypes = events.split( ' ' );
			var namespacedEvents = [];
			var i;

			for ( i = 0; i < eventTypes.length; i++ ) {
				namespacedEvents.push( eventTypes[i] + '.' + this._moduleId );
			}

			var namespacedEventString = namespacedEvents.join( ' ' );

			// Unbind the event
			$element.off( namespacedEventString );

			for ( i = this._eventHandlers.length - 1; 0 <= i; i-- ) {
				var handler = this._eventHandlers[i];
				if ( handler.element.is( $element ) && handler.events === namespacedEventString ) {
					this._eventHandlers.splice( i, 1 );
				}
			}
		},

		/**
		 * Unbind all events managed by this instance
		 */
		unbindAllEvents: function() {
			if ( !this._eventHandlers ) {
				return;
			}

			var i, handler;

			// Unbind all tracked events
			for ( i = 0; i < this._eventHandlers.length; i++ ) {
				handler = this._eventHandlers[i];
				handler.element.off( handler.events );
			}

			this._eventHandlers = [];
			this._eventNamespaces = [];
		},

		/**
		 * Get count of managed events
		 *
		 * @returns {number} Number of managed events
		 */
		getEventCount: function() {
			return this._eventHandlers ? this._eventHandlers.length : 0;
		},

		/**
		 * Get list of managed events (for debugging)
		 *
		 * @returns {Array} Array of event handler objects
		 */
		getEventList: function() {
			if ( !this._eventHandlers ) {
				return [];
			}

			return this._eventHandlers.map( function( handler ) {
				return {
					events: handler.events,
					element: handler.element.get( 0 ),
					selector: handler.selector || null,
					delegated: !!handler.delegated,
					custom: !!handler.custom
				};
			} );
		},

		/**
		 * Cleanup method - call this when destroying the module
		 */
		destroy: function() {
			this.unbindAllEvents();

			if ( this._eventHandlers ) {
				this._eventHandlers = null;
			}
			if ( this._eventNamespaces ) {
				this._eventNamespaces = null;
			}
		}
	};

	WSSCD.EventManager = WSSCD.Mixins.EventManager;

} )( jQuery );