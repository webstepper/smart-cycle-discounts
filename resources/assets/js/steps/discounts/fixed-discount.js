/**
 * Fixed Discount
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/steps/discounts/fixed-discount.js
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

( function( $ ) {
	'use strict';

	// Register fixed discount using utility with inheritance
	WSSCD.Utils.registerModule( 'WSSCD.Modules.Discounts.Types', 'FixedDiscount', function( state ) {
		// Call parent constructor
		WSSCD.Modules.Discounts.Types.BaseDiscount.call( this, state );

		this.type = 'fixed';
		this.config = WSSCD.Modules.Discounts.Config;
		this.currencySymbol = WSSCD.Utils.get( window, 'wsscdSettings.currencySymbol', '$' );
		this.currencyPosition = WSSCD.Utils.get( window, 'wsscdSettings.currencyPos', 'left' );

		if ( !WSSCD.Utils.ensureInitialized( this, {
			'config': this.config
		}, 'FixedDiscount' ) ) {
			return;
		}
	} );

	// Inherit from BaseDiscount
	WSSCD.Modules.Discounts.Types.FixedDiscount.prototype = Object.create( WSSCD.Modules.Discounts.Types.BaseDiscount.prototype );
	WSSCD.Modules.Discounts.Types.FixedDiscount.prototype.constructor =
		WSSCD.Modules.Discounts.Types.FixedDiscount;

	// Override methods using utility extend
	WSSCD.Utils.extend( WSSCD.Modules.Discounts.Types.FixedDiscount.prototype, {
		/**
		 * Initialize fixed discount
		 */
		init: function() {
			WSSCD.Modules.Discounts.Types.BaseDiscount.prototype.init.call( this );
			this.setupFixedHandlers();
		},

		/**
		 * Set default values for fixed discount
		 */
		setDefaults: function() {
			this.updateInlinePreview();
		},

		/**
		 * Setup fixed amount-specific event handlers
		 */
	setupFixedHandlers: function() {
		var self = this;

		// Handle fixed amount value changes with real-time validation
		$( document ).on( 'input.fixed change.fixed', '#discount_value_fixed', function() {
			var $field = $( this );
			var value = parseFloat( $field.val() );

		if ( window.WSSCD && window.WSSCD.ValidationError ) {
			window.WSSCD.ValidationError.clear( $field );
		}

			// Update state immediately to maintain single source of truth
			if ( self.state && self.state.setState ) {
				self.state.setState( {
					discountValueFixed: isNaN( value ) ? 0 : value
				} );
			}

			// Real-time validation
			if ( !isNaN( value ) && 0 >= value ) {
				self.showFieldError( $field, 'Amount must be greater than 0' );
			}

			self.updateInlinePreview();
		} );
	},

		/**
		 * Clear fixed amount-specific data
		 */
		clearData: function() {
			if ( this.state.setState ) {
				this.state.setState( {
					discountValueFixed: 0
				} );
			}
		},

		/**
		 * Show fixed amount UI elements
		 */
		showUI: function() {
			this.updateFieldConstraints();
			this.triggerCustomEvent( 'wsscd:discount:ui:show', [ 'fixed' ] );
		},

		/**
		 * Hide fixed amount UI elements
		 */
		hideUI: function() {
			$( '.wsscd-fixed-options, .wsscd-strategy-fixed' ).removeClass( 'active' );
			this.triggerCustomEvent( 'wsscd:discount:ui:hide', [ 'fixed' ] );
		},

		/**
		 * Update field constraints for fixed amount
		 */
		updateFieldConstraints: function() {
			var defaults = WSSCD.Utils.get( this.config, 'defaults.values.fixed', {} );
			var selector = WSSCD.Utils.get( this.config, 'constants.selectors.discountValue', '[name="discount_value_fixed"]' );
			var $field = $( selector );

			if ( $field.length ) {
				$field
					.attr( 'min', defaults.min || 0 )
					.attr( 'step', defaults.step || 0.01 )
					.attr( 'placeholder', defaults.placeholder || 'Enter amount' )
					.removeAttr( 'max' );
			}
		},

		/**
		 * Handle state changes specific to fixed discount
		 * @param change
		 */
		handleStateChange: function( change ) {
			if ( 'discountValueFixed' === change.property ) {
				this.updateInlinePreview();
			}
		},

		/**
		 * Validate fixed discount configuration
		 */
		validate: function() {
			console.group( '🔍 FIXED DISCOUNT - validate()' );
			var errors = {};
			var warnings = {};

			// Get value from state (now always synchronized via input handler)
			var value = this.state.getData ? parseFloat( this.state.getData( 'discountValueFixed' ) ) : 0;

			console.log( 'State value:', value );
			console.log( 'Field exists:', $( '#discount_value_fixed' ).length > 0 );
			console.log( 'Field value from DOM:', $( '#discount_value_fixed' ).val() );

			if ( isNaN( value ) || 0 >= value ) {
				errors.discount_value_fixed = 'Please enter a valid amount';
				console.warn( '❌ Validation failed: Invalid or missing fixed amount' );
			}

			var minOrder = this.state.getState ?
				parseFloat( this.state.getData( 'minimumOrderAmount' ) ) : 0;
			if ( !isNaN( minOrder ) && 0 < minOrder && value >= minOrder ) {
				warnings.discount_value_fixed = 'Discount amount equals or exceeds minimum order amount';
				console.log( '⚠️ Warning: Amount exceeds minimum order' );
			} else if ( 1000 < value ) {
				warnings.discount_value_fixed = 'Large discount amount. Please verify this is intended.';
				console.log( '⚠️ Warning: Large discount amount' );
			}

			var result = {
				valid: 0 === Object.keys( errors ).length,
				errors: errors,
				warnings: warnings
			};

			console.log( 'Validation result:', result );
			console.groupEnd();
			return result;
		},

		/**
		 * Collect fixed discount data
		 */
		collectData: function() {
			// Get value from state (now always synchronized via input handler)
			var value = this.state.getData ? parseFloat( this.state.getData( 'discountValueFixed' ) ) : 0;
			value = isNaN( value ) ? 0 : value;

			return {
				discountType: 'fixed',
				discountValue: value,
				discountValueFixed: value
			};
		},

		/**
		 * Load fixed discount data
		 * @param data
		 */
		loadData: function( data ) {
			if ( data && data.discountValueFixed !== undefined && this.state.setState ) {
				this.state.setState( {
					discountValueFixed: parseFloat( data.discountValueFixed ) || 0
				} );
			}
		},

		/**
		 * Get summary text for fixed discount
		 */
		getSummary: function() {
			var value = this.state.getState ? this.state.getData( 'discountValueFixed' ) : 0;
			return this.formatCurrency( value ) + ' off';
		},

		/**
		 * Calculate effective discount value
		 */
		calculateValue: function() {
			return this.state.getState ? this.state.getData( 'discountValueFixed' ) || 0 : 0;
		},

		/**
		 * Get configuration for preview
		 */
		getPreviewConfig: function() {
			var value = this.calculateValue();

			return {
				type: 'fixed',
				value: value,
				formatted: this.formatCurrency( value ),
				currency: {
					symbol: this.currencySymbol,
					position: this.currencyPosition
				}
			};
		},

		/**
		 * Get help text for this discount type
		 */
		getHelpText: function() {
			return 'Enter a fixed amount to discount from the product price. The amount is in ' + this.currencySymbol + '.';
		},

		/**
		 * Format currency value using utility or fallback
		 * @param value
		 */
		formatCurrency: function( value ) {
			// Use centralized utility if available
			if ( WSSCD.Utils && WSSCD.Utils.formatCurrency ) {
				return WSSCD.Utils.formatCurrency( value );
			}

			// Fallback implementation
			var formatted = parseFloat( value || 0 ).toFixed( 2 );

			switch ( this.currencyPosition ) {
				case 'left':
					return this.currencySymbol + formatted;
				case 'left_space':
					return this.currencySymbol + ' ' + formatted;
				case 'right':
					return formatted + this.currencySymbol;
				case 'right_space':
					return formatted + ' ' + this.currencySymbol;
				default:
					return this.currencySymbol + formatted;
			}
		},

		/**
		 * Check if discount would exceed product price
		 * @param productPrice
		 */
		wouldExceedPrice: function( productPrice ) {
			if ( !productPrice ) {
				return false;
			}
			return this.calculateValue() >= productPrice;
		},

		/**
		 * Format value for display
		 * @param value
		 */
		formatValue: function( value ) {
			return this.formatCurrency( value );
		},

		/**
		 * Update inline preview text
		 */
		updateInlinePreview: function() {
			var $preview = $( '#fixed-preview .preview-text' );
			if ( !$preview.length ) {return;}

			var value = parseFloat( $( '#discount_value_fixed' ).val() ) || 0;

			if ( 0 < value ) {
				$preview.removeClass( 'placeholder' ).text( this.formatCurrency( value ) + ' off each selected product' );
			} else {
				$preview.addClass( 'placeholder' ).text( 'Enter an amount to see the discount preview' );
			}
		},

		/**
		 * Clean up
		 */
		destroy: function() {
			$( document ).off( 'input.fixed change.fixed' );

			WSSCD.Modules.Discounts.Types.BaseDiscount.prototype.destroy.call( this );
		}
	} );

} )( jQuery );