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
	SCD.Utils.registerModule( 'SCD.Modules.Discounts.Types', 'FixedDiscount', function( state ) {
		// Call parent constructor
		SCD.Modules.Discounts.Types.BaseDiscount.call( this, state );

		this.type = 'fixed';
		this.config = SCD.Modules.Discounts.Config;
		this.currencySymbol = SCD.Utils.get( window, 'scdDiscountStepData.currencySymbol', '$' )
		this.currencyPosition = SCD.Utils.get( window, 'scdDiscountStepData.currencyPos', 'left' );

		if ( !SCD.Utils.ensureInitialized( this, {
			'config': this.config
		}, 'FixedDiscount' ) ) {
			return;
		}
	} );

	// Inherit from BaseDiscount
	SCD.Modules.Discounts.Types.FixedDiscount.prototype = Object.create( SCD.Modules.Discounts.Types.BaseDiscount.prototype );
	SCD.Modules.Discounts.Types.FixedDiscount.prototype.constructor =
		SCD.Modules.Discounts.Types.FixedDiscount;

	// Override methods using utility extend
	SCD.Utils.extend( SCD.Modules.Discounts.Types.FixedDiscount.prototype, {
		/**
		 * Initialize fixed discount
		 */
		init: function() {
			SCD.Modules.Discounts.Types.BaseDiscount.prototype.init.call( this );
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

		if ( window.SCD && window.SCD.ValidationError ) {
			window.SCD.ValidationError.clear( $field );
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
			this.triggerCustomEvent( 'scd:discount:ui:show', [ 'fixed' ] );
		},

		/**
		 * Hide fixed amount UI elements
		 */
		hideUI: function() {
			$( '.scd-fixed-options, .scd-strategy-fixed' ).removeClass( 'active' );
			this.triggerCustomEvent( 'scd:discount:ui:hide', [ 'fixed' ] );
		},

		/**
		 * Update field constraints for fixed amount
		 */
		updateFieldConstraints: function() {
			var defaults = SCD.Utils.get( this.config, 'defaults.values.fixed', {} );
			var selector = SCD.Utils.get( this.config, 'constants.selectors.discountValue', '[name="discount_value_fixed"]' );
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
			var errors = {};
			var warnings = {};

			var value = this.state.getState ?
				parseFloat( this.state.getState( 'discountValueFixed' ) ) : 0;

			if ( isNaN( value ) || 0 >= value ) {
				errors.discountValueFixed = 'Please enter a valid amount';
			}

			var minOrder = this.state.getState ?
				parseFloat( this.state.getState( 'minimumOrderAmount' ) ) : 0;
			if ( !isNaN( minOrder ) && 0 < minOrder && value >= minOrder ) {
				warnings.discountValueFixed = 'Discount amount equals or exceeds minimum order amount';
			} else if ( 1000 < value ) {
				warnings.discountValueFixed = 'Large discount amount. Please verify this is intended.';
			}

			var isValid = SCD.Utils.isEmpty( errors );

			// Log validation if errors exist
			if ( !isValid ) {
				SCD.ErrorHandler.handle(
					new Error( 'Fixed discount validation failed' ),
					'FixedDiscount.validate',
					SCD.ErrorHandler.SEVERITY.LOW,
					{ errors: errors, warnings: warnings }
				);
			}

			return {
				valid: isValid,
				errors: errors,
				warnings: warnings
			};
		},

		/**
		 * Collect fixed discount data
		 */
		collectData: function() {
			var value = this.state.getState ? this.state.getState( 'discountValueFixed' ) : 0;

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
			if ( !SCD.Utils.isEmpty( data ) ) {
				if ( data.discountValueFixed !== undefined && this.state.setState ) {
					this.state.setState( {
						discountValueFixed: parseFloat( data.discountValueFixed ) || 0
					} );
				}
			}
		},

		/**
		 * Get summary text for fixed discount
		 */
		getSummary: function() {
			var value = this.state.getState ? this.state.getState( 'discountValueFixed' ) : 0;
			return this.formatCurrency( value ) + ' off';
		},

		/**
		 * Calculate effective discount value
		 */
		calculateValue: function() {
			return this.state.getState ? this.state.getState( 'discountValueFixed' ) || 0 : 0;
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
			if ( SCD.Utils && SCD.Utils.formatCurrency ) {
				return SCD.Utils.formatCurrency( value );
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

			SCD.Modules.Discounts.Types.BaseDiscount.prototype.destroy.call( this );
		}
	} );

} )( jQuery );