/**
 * Base State
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/shared/base-state.js
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

( function( $ ) {
	'use strict';

	window.SCD = window.SCD || {};
	SCD.Shared = SCD.Shared || {};

	/**
	 * Base State Class
	 *
	 * @param initialState
	 * @class SCD.Shared.BaseState
	 */
	SCD.Shared.BaseState = function( initialState ) {
		initialState = initialState || {};
		this._state = $.extend( {}, initialState );
		this._subscribers = [];
		this._isDirty = false;
		this._history = [];
		this._historyIndex = -1;
		this._maxHistory = 50;
		this._batchUpdates = null;
		this._proxy = null;

		// Only call prototype methods if they exist (for child class .call() compatibility)
		if ( this._createProxy ) {
			this._createProxy();
		}
		if ( this._saveHistory ) {
			this._saveHistory();
		}
	};

	SCD.Shared.BaseState.prototype = {
		/**
		 * Create reactive proxy for state (ES5 compatible)
		 *
		 * @private
		 */
		_createProxy: function() {
			// ES5 compatible - no proxy needed, use direct state access
			this._proxy = this._state;
		},

		/**
		 * Subscribe to state changes
		 *
		 * @param {Function} callback Callback function( changes )
		 * @param {string|Array|null} filter Property name( s ) to watch, null for all
		 * @returns {Function} Unsubscribe function
		 */
		subscribe: function( callback, filter ) {
			filter = 'undefined' === typeof filter ? null : filter;
			var subscriber = {
				callback: callback,
				filter: filter
			};

			this._subscribers.push( subscriber );

			// Return unsubscribe function with proper scope
			var self = this;
			return function() {
				var index = self._subscribers.indexOf( subscriber );
				if ( -1 < index ) {
					self._subscribers.splice( index, 1 );
				}
			};
		},

		/**
		 * Notify subscribers of state changes
		 *
		 * @private
		 * @param {object} changes Change details
		 */
		_notifySubscribers: function( changes ) {
			this._subscribers.forEach( function( subscriber ) {
				if ( subscriber.filter ) {
					var filters = Array.isArray( subscriber.filter ) ?
						subscriber.filter : [ subscriber.filter ];

					if ( -1 === filters.indexOf( changes.property ) ) {
						return;
					}
				}

				// Let errors propagate for proper debugging
				subscriber.callback( changes );
			} );

			// Trigger jQuery event for modern implementation
			$( document ).trigger( 'scd:state:changed', [ changes ] );
		},

		/**
		 * Get current state ( returns a copy )
		 *
		 * @returns {object} Current state copy
		 */
		getState: function() {
			return $.extend( {}, this._state );
		},

		/**
		 * Get value of a specific field
		 *
		 * @param {string} fieldName Field name to retrieve
		 * @returns {*} Field value or undefined
		 */
		getData: function( fieldName ) {
			if ( !fieldName ) {
				return undefined;
			}
			return this._state[fieldName];
		},

		/**
		 * Set state properties
		 *
		 * @param {object} updates Properties to update
		 * @param {boolean} batch Whether to batch notifications
		 */
		setState: function( updates, batch ) {
			if ( !updates || 'object' !== typeof updates ) {
				return;
			}

			// Save history before changes
			this._saveHistory();

			if ( batch ) {
				this._batchUpdates = {};

				// Apply all updates
				var self = this;
				Object.keys( updates ).forEach( function( key ) {
					var oldValue = self._state[key];
					self._state[key] = updates[key];
					self._isDirty = true;
					self._batchUpdates[key] = { oldValue: oldValue, newValue: updates[key] };
				} );

				// Notify once with all changes
				if ( 0 < Object.keys( this._batchUpdates ).length ) {
					this._notifySubscribers( {
						batch: true,
						changes: this._batchUpdates,
						state: this.getState()
					} );
				}

				this._batchUpdates = null;
			} else {
				// Apply updates one by one ( triggers individual notifications )
				var stateUpdater = this;
				Object.keys( updates ).forEach( function( key ) {
					var oldValue = stateUpdater._state[key];
					stateUpdater._state[key] = updates[key];
					stateUpdater._isDirty = true;
					stateUpdater._notifySubscribers( {
						property: key,
						value: updates[key],
						oldValue: oldValue,
						newValue: updates[key],
						state: stateUpdater.getState()
					} );
				} );
			}
		},

		/**
		 * Reset state to initial or provided state
		 *
		 * @param {object} newState State to reset to
		 */
		reset: function( newState ) {
			newState = 'undefined' === typeof newState ? {} : newState;
			this._saveHistory();

			Object.keys( this._state ).forEach( function( key ) {
				delete this._state[key];
			}.bind( this ) );

			// Apply new state
			$.extend( this._state, newState );

			this._isDirty = false;

			this._notifySubscribers( {
				reset: true,
				state: this.getState()
			} );
		},

		/**
		 * Check if state has been modified
		 *
		 * @returns {boolean} True if modified
		 */
		isDirty: function() {
			return this._isDirty;
		},

		/**
		 * Clear dirty flag
		 */
		clearDirty: function() {
			this._isDirty = false;
		},

		/**
		 * Convert state to JSON
		 *
		 * @returns {string} JSON string
		 */
		toJSON: function() {
			return JSON.stringify( this.getState() );
		},

		/**
		 * Load state from JSON
		 *
		 * @param {string|object} json JSON string or object
		 */
		fromJSON: function( json ) {
			var data;

			// Handle both JSON strings and already-parsed objects
			if ( 'string' === typeof json ) {
				// JSON.parse will throw if invalid - let it propagate
				data = JSON.parse( json );
			} else if ( 'object' === typeof json && null !== json ) {
				// Already an object, use as-is
				data = json;
			} else {
				console.error( '[SCD] fromJSON received invalid data type:', typeof json );
				return;
			}

			this.reset( data );
		},

		/**
		 * Save current state to history
		 *
		 * @private
		 */
		_saveHistory: function() {
			this._history = this._history.slice( 0, this._historyIndex + 1 );

			this._history.push( this.toJSON() );

			// Limit history size
			if ( this._history.length > this._maxHistory ) {
				this._history.shift();
			} else {
				this._historyIndex++;
			}
		},

		/**
		 * Undo last state change
		 *
		 * @returns {boolean} True if undo was possible
		 */
		undo: function() {
			if ( 0 < this._historyIndex ) {
				this._historyIndex--;
				this.fromJSON( this._history[this._historyIndex] );
				return true;
			}
			return false;
		},

		/**
		 * Redo previously undone state change
		 *
		 * @returns {boolean} True if redo was possible
		 */
		redo: function() {
			if ( this._historyIndex < this._history.length - 1 ) {
				this._historyIndex++;
				this.fromJSON( this._history[this._historyIndex] );
				return true;
			}
			return false;
		},

		/**
		 * Can undo check
		 *
		 * @returns {boolean} True if can undo
		 */
		canUndo: function() {
			return 0 < this._historyIndex;
		},

		/**
		 * Can redo check
		 *
		 * @returns {boolean} True if can redo
		 */
		canRedo: function() {
			return this._historyIndex < this._history.length - 1;
		},

		/**
		 * Watch specific property for changes
		 *
		 * @param {string} property Property name
		 * @param {Function} callback Callback function
		 * @returns {Function} Unsubscribe function
		 */
		watch: function( property, callback ) {
			return this.subscribe( function( changes ) {
				if ( property === changes.property || changes.reset ) {
					callback( changes.newValue, changes.oldValue, changes );
				}
			}, property );
		},

		/**
		 * Update a single field - convenience method
		 *
		 * @param {string} field Field name
		 * @param {*} value New value
		 */
		updateField: function( field, value ) {
			var update = {};
			update[field] = value;
			this.setState( update );
		},

		/**
		 * Clear all subscriptions
		 */
		clearSubscriptions: function() {
			this._subscribers = [];
		},

		/**
		 * Destroy state and cleanup
		 */
		destroy: function() {
			this.clearSubscriptions();

			this._history = [];
			this._historyIndex = -1;

			this._proxy = null;

			this._state = {};

			$( document ).off( 'scd:state:changed' );
		}
	};

} )( jQuery );