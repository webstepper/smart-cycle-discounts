/**
 * UI State Manager - Declarative State-Driven UI System
 *
 * Eliminates manual show/hide logic by providing
 * automatic UI updates based on state changes via data attributes.
 *
 * @since 2.0.0
 * @package Smart_Cycle_Discounts
 */

( function( window, $, SCD ) {
	'use strict';

	if ( ! SCD || ! SCD.Shared ) {
		console.error( 'SCD.Shared is not defined. UI State Manager cannot initialize.' );
		return;
	}

	/**
	 * UI State Manager
	 *
	 * Provides declarative state-driven UI visibility and state management
	 *
	 * @example
	 * HTML:
	 * <div data-scd-show-when="discountType" data-scd-show-value="percentage">
	 *     Percentage discount options
	 * </div>
	 *
	 * <div data-scd-hide-when="mode" data-scd-hide-value="simple">
	 *     Advanced options
	 * </div>
	 *
	 * <button data-scd-enable-when="hasProducts" data-scd-enable-value="true">
	 *     Save Campaign
	 * </button>
	 *
	 * JavaScript:
	 * var state = { discountType: 'percentage', mode: 'simple', hasProducts: true };
	 * SCD.Shared.UIStateManager.bind( this.$container, state );
	 * // Updates UI automatically when state.discountType changes
	 */
	SCD.Shared.UIStateManager = {

		/**
		 * Active bindings registry
		 */
		_bindings: [],

		/**
		 * Bind state to UI elements in container
		 *
		 * @param {jQuery} $container Container element
		 * @param {Object} state State object to watch
		 * @param {Object} options Optional configuration
		 * @return {Object} Binding descriptor for cleanup
		 */
		bind: function( $container, state, options ) {
			var self = this;

			// Validate parameters
			if ( ! $container || ! $container.length ) {
				SCD.ErrorHandler.handle(
					new Error( 'UI State Manager: Invalid container provided' ),
					'UIStateManager.bind'
				);
				return null;
			}

			if ( ! state || typeof state !== 'object' ) {
				SCD.ErrorHandler.handle(
					new Error( 'UI State Manager: Invalid state object provided' ),
					'UIStateManager.bind'
				);
				return null;
			}

			// Default options
			var settings = $.extend( {
				immediate: true,
				watchInterval: null // Use event-driven updates instead
			}, options || {} );

			// Find all elements with state binding attributes
			var $elements = this._findElements( $container );

			// Create binding descriptor
			var binding = {
				$container: $container,
				state: state,
				$elements: $elements,
				settings: settings,
				updateHandler: function( propertyName ) {
					self._updateUI( binding, propertyName );
				}
			};

			// Perform immediate update if requested
			if ( settings.immediate ) {
				this._updateUI( binding );
			}

			// Store binding
			this._bindings.push( binding );

			if ( window.SCD && window.SCD.DebugLogger ) {
				SCD.DebugLogger.log(
					'info',
					'UIStateManager',
					'UI State Manager: Bound ' + $elements.length + ' elements to state'
				);
			}

			return binding;
		},

		/**
		 * Find all elements with state binding attributes
		 *
		 * @param {jQuery} $container Container element
		 * @return {jQuery} Elements with state bindings
		 */
		_findElements: function( $container ) {
			var selectors = [
				'[data-scd-show-when]',
				'[data-scd-hide-when]',
				'[data-scd-enable-when]',
				'[data-scd-disable-when]',
				'[data-scd-class-when]'
			];

			return $container.find( selectors.join( ', ' ) ).addBack( selectors.join( ', ' ) );
		},

		/**
		 * Update UI based on current state
		 *
		 * @param {Object} binding Binding descriptor
		 * @param {string} propertyName Optional specific property to update
		 * @return {void}
		 */
		_updateUI: function( binding, propertyName ) {
			var self = this;

			binding.$elements.each( function() {
				var $element = $( this );

				// Show/hide conditions
				if ( $element.is( '[data-scd-show-when]' ) ) {
					var showProp = $element.attr( 'data-scd-show-when' );

					// Only update if this property changed (or updating all)
					if ( ! propertyName || propertyName === showProp ) {
						self._handleShowCondition( $element, binding.state );
					}
				}

				if ( $element.is( '[data-scd-hide-when]' ) ) {
					var hideProp = $element.attr( 'data-scd-hide-when' );

					if ( ! propertyName || propertyName === hideProp ) {
						self._handleHideCondition( $element, binding.state );
					}
				}

				// Enable/disable conditions
				if ( $element.is( '[data-scd-enable-when]' ) ) {
					var enableProp = $element.attr( 'data-scd-enable-when' );

					if ( ! propertyName || propertyName === enableProp ) {
						self._handleEnableCondition( $element, binding.state );
					}
				}

				if ( $element.is( '[data-scd-disable-when]' ) ) {
					var disableProp = $element.attr( 'data-scd-disable-when' );

					if ( ! propertyName || propertyName === disableProp ) {
						self._handleDisableCondition( $element, binding.state );
					}
				}

				// Class toggling
				if ( $element.is( '[data-scd-class-when]' ) ) {
					var classProp = $element.attr( 'data-scd-class-when' );

					if ( ! propertyName || propertyName === classProp ) {
						self._handleClassCondition( $element, binding.state );
					}
				}
			} );
		},

		/**
		 * Handle show condition
		 *
		 * @param {jQuery} $element Element
		 * @param {Object} state State object
		 * @return {void}
		 */
		_handleShowCondition: function( $element, state ) {
			var property = $element.attr( 'data-scd-show-when' );
			var expectedValue = $element.attr( 'data-scd-show-value' );
			var operator = $element.attr( 'data-scd-show-operator' ) || 'equals';

			var shouldShow = this._evaluateCondition(
				state[property],
				expectedValue,
				operator
			);

			if ( shouldShow ) {
				$element.show();
				$element.removeAttr( 'aria-hidden' );
			} else {
				$element.hide();
				$element.attr( 'aria-hidden', 'true' );
			}
		},

		/**
		 * Handle hide condition
		 *
		 * @param {jQuery} $element Element
		 * @param {Object} state State object
		 * @return {void}
		 */
		_handleHideCondition: function( $element, state ) {
			var property = $element.attr( 'data-scd-hide-when' );
			var expectedValue = $element.attr( 'data-scd-hide-value' );
			var operator = $element.attr( 'data-scd-hide-operator' ) || 'equals';

			var shouldHide = this._evaluateCondition(
				state[property],
				expectedValue,
				operator
			);

			if ( shouldHide ) {
				$element.hide();
				$element.attr( 'aria-hidden', 'true' );
			} else {
				$element.show();
				$element.removeAttr( 'aria-hidden' );
			}
		},

		/**
		 * Handle enable condition
		 *
		 * @param {jQuery} $element Element
		 * @param {Object} state State object
		 * @return {void}
		 */
		_handleEnableCondition: function( $element, state ) {
			var property = $element.attr( 'data-scd-enable-when' );
			var expectedValue = $element.attr( 'data-scd-enable-value' );
			var operator = $element.attr( 'data-scd-enable-operator' ) || 'equals';

			var shouldEnable = this._evaluateCondition(
				state[property],
				expectedValue,
				operator
			);

			if ( shouldEnable ) {
				$element.prop( 'disabled', false );
				$element.removeAttr( 'aria-disabled' );
				$element.removeClass( 'disabled' );
			} else {
				$element.prop( 'disabled', true );
				$element.attr( 'aria-disabled', 'true' );
				$element.addClass( 'disabled' );
			}
		},

		/**
		 * Handle disable condition
		 *
		 * @param {jQuery} $element Element
		 * @param {Object} state State object
		 * @return {void}
		 */
		_handleDisableCondition: function( $element, state ) {
			var property = $element.attr( 'data-scd-disable-when' );
			var expectedValue = $element.attr( 'data-scd-disable-value' );
			var operator = $element.attr( 'data-scd-disable-operator' ) || 'equals';

			var shouldDisable = this._evaluateCondition(
				state[property],
				expectedValue,
				operator
			);

			if ( shouldDisable ) {
				$element.prop( 'disabled', true );
				$element.attr( 'aria-disabled', 'true' );
				$element.addClass( 'disabled' );
			} else {
				$element.prop( 'disabled', false );
				$element.removeAttr( 'aria-disabled' );
				$element.removeClass( 'disabled' );
			}
		},

		/**
		 * Handle class toggling condition
		 *
		 * @param {jQuery} $element Element
		 * @param {Object} state State object
		 * @return {void}
		 */
		_handleClassCondition: function( $element, state ) {
			var property = $element.attr( 'data-scd-class-when' );
			var expectedValue = $element.attr( 'data-scd-class-value' );
			var className = $element.attr( 'data-scd-class-name' );
			var operator = $element.attr( 'data-scd-class-operator' ) || 'equals';

			if ( ! className ) {
				return;
			}

			var shouldAddClass = this._evaluateCondition(
				state[property],
				expectedValue,
				operator
			);

			if ( shouldAddClass ) {
				$element.addClass( className );
			} else {
				$element.removeClass( className );
			}
		},

		/**
		 * Evaluate condition
		 *
		 * @param {*} actualValue Actual state value
		 * @param {string} expectedValue Expected value (as string from attribute)
		 * @param {string} operator Comparison operator
		 * @return {boolean} Result
		 */
		_evaluateCondition: function( actualValue, expectedValue, operator ) {
			// Convert expectedValue to appropriate type
			var expected = this._parseValue( expectedValue );

			switch ( operator ) {
				case 'equals':
				case '==':
					return String( actualValue ) === String( expected );

				case 'not-equals':
				case '!=':
					return String( actualValue ) !== String( expected );

				case 'includes':
					if ( Array.isArray( actualValue ) ) {
						return actualValue.indexOf( expected ) !== -1;
					}
					return String( actualValue ).indexOf( String( expected ) ) !== -1;

				case 'not-includes':
					if ( Array.isArray( actualValue ) ) {
						return actualValue.indexOf( expected ) === -1;
					}
					return String( actualValue ).indexOf( String( expected ) ) === -1;

				case 'greater-than':
				case '>':
					return Number( actualValue ) > Number( expected );

				case 'less-than':
				case '<':
					return Number( actualValue ) < Number( expected );

				case 'truthy':
					return !! actualValue;

				case 'falsy':
					return ! actualValue;

				case 'empty':
					if ( Array.isArray( actualValue ) ) {
						return actualValue.length === 0;
					}
					return ! actualValue || String( actualValue ).trim() === '';

				case 'not-empty':
					if ( Array.isArray( actualValue ) ) {
						return actualValue.length > 0;
					}
					return actualValue && String( actualValue ).trim() !== '';

				default:
					return false;
			}
		},

		/**
		 * Parse value from string
		 *
		 * @param {string} value Value to parse
		 * @return {*} Parsed value
		 */
		_parseValue: function( value ) {
			if ( value === 'true' ) {
				return true;
			}
			if ( value === 'false' ) {
				return false;
			}
			if ( value === 'null' ) {
				return null;
			}
			if ( value === 'undefined' ) {
				return undefined;
			}
			if ( /^-?\d+(\.\d+)?$/.test( value ) ) {
				return parseFloat( value );
			}
			return value;
		},

		/**
		 * Trigger update for specific property
		 *
		 * Call this when state changes to update UI
		 *
		 * @param {Object} binding Binding descriptor from bind()
		 * @param {string} propertyName Property that changed
		 * @return {void}
		 */
		update: function( binding, propertyName ) {
			if ( ! binding || ! binding.$container ) {
				return;
			}

			this._updateUI( binding, propertyName );
		},

		/**
		 * Unbind state from container
		 *
		 * @param {Object} binding Binding descriptor from bind()
		 * @return {void}
		 */
		unbind: function( binding ) {
			if ( ! binding ) {
				return;
			}

			// Remove from bindings registry
			var index = this._bindings.indexOf( binding );
			if ( index !== -1 ) {
				this._bindings.splice( index, 1 );
			}

			if ( window.SCD && window.SCD.DebugLogger ) {
				SCD.DebugLogger.log( 'info', 'UIStateManager', 'UI State Manager: Unbound container' );
			}
		},

		/**
		 * Create reactive state wrapper
		 *
		 * Wraps a state object to automatically update UI when properties change
		 *
		 * @param {Object} state Initial state
		 * @param {Object} binding Binding descriptor
		 * @return {Object} Reactive state wrapper
		 */
		createReactive: function( state, binding ) {
			var self = this;
			var reactive = {};

			// Create getters and setters for each property
			for ( var prop in state ) {
				if ( state.hasOwnProperty( prop ) ) {
					( function( propertyName ) {
						Object.defineProperty( reactive, propertyName, {
							get: function() {
								return state[propertyName];
							},
							set: function( value ) {
								var oldValue = state[propertyName];
								state[propertyName] = value;

								// Update UI if value changed
								if ( oldValue !== value ) {
									self.update( binding, propertyName );
								}
							},
							enumerable: true,
							configurable: true
						} );
					} )( prop );
				}
			}

			return reactive;
		},

		/**
		 * Helper to create data attributes string
		 *
		 * @param {string} condition Condition type ('show', 'hide', 'enable', etc.)
		 * @param {string} property State property name
		 * @param {*} value Expected value
		 * @param {string} operator Optional operator (default: 'equals')
		 * @return {string} Data attributes string
		 *
		 * @example
		 * var attrs = SCD.Shared.UIStateManager.attrs( 'show', 'discountType', 'percentage' );
		 * // Returns: 'data-scd-show-when="discountType" data-scd-show-value="percentage"'
		 */
		attrs: function( condition, property, value, operator ) {
			var attrString = 'data-scd-' + condition + '-when="' + property + '" ' +
			                 'data-scd-' + condition + '-value="' + value + '"';

			if ( operator && operator !== 'equals' ) {
				attrString += ' data-scd-' + condition + '-operator="' + operator + '"';
			}

			return attrString;
		}

	};

	// Module loaded
	if ( window.SCD && window.SCD.DebugLogger ) {
		SCD.DebugLogger.log( 'info', 'UIStateManager', 'UI State Manager loaded' );
	}

} )( window, jQuery, window.SCD || {} );
