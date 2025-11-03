/**
 * Complex Field Handler
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/steps/discounts/complex-field-handler.js
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

( function( _$ ) {
	'use strict';

	/**
	 * Complex Field Handler Factory
	 * 
	 * Creates a configured handler instance based on provided configuration
	 * 
	 * @param {Object} config Configuration object
	 * @param {string} config.handlerName Name for the handler (e.g., 'TieredHandler', 'ThresholdHandler')
	 * @param {string} config.namespace Namespace path (e.g., 'SCD.Modules.Discounts')
	 * @param {Object} config.propertyNames Property name mappings
	 * @param {string} config.propertyNames.percentageItems Internal property name for percentage items
	 * @param {string} config.propertyNames.fixedItems Internal property name for fixed items
	 * @param {string} config.propertyNames.mode Internal property name for mode
	 * @param {string} config.propertyNames.additionalProps Additional property names (optional)
	 * @param {Object} config.statePropertyNames State property name mappings for getState()
	 * @param {Object} config.defaults Default values for items
	 * @param {Function} config.itemParser Function to parse/validate individual items
	 * @param {boolean} config.logEnabled Enable console logging (default: true)
	 */
	SCD.Utils.createComplexFieldHandler = function( config ) {
		// Validate required config
		if ( !config || !config.handlerName || !config.namespace ) {
			throw new Error( 'Complex field handler requires handlerName and namespace in config' );
		}

		// Default configuration
		var defaults = {
			propertyNames: {
				percentageItems: '_percentageItems',
				fixedItems: '_fixedItems',
				mode: '_mode'
			},
			statePropertyNames: {
				percentageItems: 'percentageItems',
				fixedItems: 'fixedItems',
				mode: 'mode'
			},
			defaults: {
				percentageItems: [],
				fixedItems: []
			},
			itemParser: function( item ) {
				return item;
			},
			logEnabled: true
		};

		// Merge config with defaults
		config = _$.extend( true, {}, defaults, config );

		// Register handler using utility
		SCD.Utils.registerModule( config.namespace, config.handlerName, function() {
			// Internal state - no DOM dependency
			var self = this;
			
			// Initialize properties based on config
			this[config.propertyNames.percentageItems] = [];
			this[config.propertyNames.fixedItems] = [];
			this[config.propertyNames.mode] = 'percentage';
			
			// Additional properties if specified
			if ( config.propertyNames.additionalProps ) {
				_$.each( config.propertyNames.additionalProps, function( key, value ) {
					self[value] = null;
				} );
			}

			// Ready state
			this._ready = false;
			this._queuedPercentageItems = null;
			this._queuedFixedItems = null;

			// Currency symbol
			this.currencySymbol = '$';
			if ( window.scdDiscountStepData && window.scdDiscountStepData.currencySymbol ) {
				var parser = new DOMParser();
				var doc = parser.parseFromString( window.scdDiscountStepData.currencySymbol, 'text/html' );
				this.currencySymbol = doc.documentElement.textContent || '$';
			}

			// Store config for later use
			this._config = config;

			// Default values
			this.defaults = config.defaults;
		} );

		// Create prototype
		var HandlerClass = SCD.Utils.getNestedProperty( window, config.namespace + '.' + config.handlerName );
		
		HandlerClass.prototype = {
			/**
			 * Initialize handler
			 */
			init: function() {
				var self = this;

				// Use requestAnimationFrame for optimal timing
				requestAnimationFrame( function() {
					// Process any queued data
					if ( null !== self._queuedPercentageItems ) {
						self.setPercentageItems( self._queuedPercentageItems );
						self._queuedPercentageItems = null;
					}

					if ( null !== self._queuedFixedItems ) {
						self.setFixedItems( self._queuedFixedItems );
						self._queuedFixedItems = null;
					}

					// Mark as ready
					self._ready = true;

					// Registration is handled by the type modules that use this handler
				} );
			},

			/**
			 * Check if handler is ready
			 */
			isReady: function() {
				return true === this._ready;
			},

			/**
			 * Get percentage items (for field definitions)
			 */
			getPercentageItems: function() {
				var items = this[this._config.propertyNames.percentageItems];
				return 0 < items.length ? items : this.defaults.percentageItems.slice();
			},

			/**
			 * Set percentage items (from field definitions)
			 * @param items
			 */
			setPercentageItems: function( items ) {
				// Queue if not ready
				if ( !this.isReady() ) {
					this._queuedPercentageItems = items;
					return;
				}

				// Validate and store
				if ( Array.isArray( items ) ) {
					this[this._config.propertyNames.percentageItems] = items.map( this._config.itemParser );
					
					if ( this._config.logEnabled ) {
					}
				}
			},

			/**
			 * Get fixed items (for field definitions)
			 */
			getFixedItems: function() {
				var items = this[this._config.propertyNames.fixedItems];
				return 0 < items.length ? items : this.defaults.fixedItems.slice();
			},

			/**
			 * Set fixed items (from field definitions)
			 * @param items
			 */
			setFixedItems: function( items ) {
				// Queue if not ready
				if ( !this.isReady() ) {
					this._queuedFixedItems = items;
					return;
				}

				// Validate and store
				if ( Array.isArray( items ) ) {
					this[this._config.propertyNames.fixedItems] = items.map( this._config.itemParser );
					
					if ( this._config.logEnabled ) {
					}
				}
			},

			/**
			 * Get current state for discount type module
			 */
			getState: function() {
				var state = {};
				
				// Map internal properties to state properties
				state[this._config.statePropertyNames.percentageItems] = this.getPercentageItems();
				state[this._config.statePropertyNames.fixedItems] = this.getFixedItems();
				state[this._config.statePropertyNames.mode] = this[this._config.propertyNames.mode];
				
				// Add additional properties if configured
				if ( this._config.propertyNames.additionalProps ) {
					var self = this;
					_$.each( this._config.propertyNames.additionalProps, function( stateKey, propName ) {
						if ( self._config.statePropertyNames[stateKey] ) {
							state[self._config.statePropertyNames[stateKey]] = self[propName];
						}
					} );
				}
				
				return state;
			},

			/**
			 * Update state from discount type module
			 * @param updates
			 */
			updateState: function( updates ) {
				var self = this;
				
				// Update percentage items
				var percentageKey = this._config.statePropertyNames.percentageItems;
				if ( updates[percentageKey] !== undefined ) {
					this.setPercentageItems( updates[percentageKey] );
				}
				
				// Update fixed items
				var fixedKey = this._config.statePropertyNames.fixedItems;
				if ( updates[fixedKey] !== undefined ) {
					this.setFixedItems( updates[fixedKey] );
				}
				
				// Update mode
				var modeKey = this._config.statePropertyNames.mode;
				if ( updates[modeKey] !== undefined ) {
					this[this._config.propertyNames.mode] = updates[modeKey];
				}
				
				// Update additional properties
				if ( this._config.propertyNames.additionalProps ) {
					_$.each( this._config.propertyNames.additionalProps, function( stateKey, propName ) {
						var updateKey = self._config.statePropertyNames[stateKey];
						if ( updateKey && updates[updateKey] !== undefined ) {
							self[propName] = updates[updateKey];
						}
					} );
				}
			}
		};

		// Add custom methods if provided
		if ( config.customMethods ) {
			_$.extend( HandlerClass.prototype, config.customMethods );
		}

		return HandlerClass;
	};

	/**
	 * Helper to get nested property safely
	 */
	SCD.Utils.getNestedProperty = function( obj, path ) {
		return path.split( '.' ).reduce( function( current, prop ) {
			return current ? current[prop] : undefined;
		}, obj );
	};

} )( jQuery );