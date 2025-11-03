/**
 * Percentage Discount
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/steps/discounts/percentage-discount.js
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

( function( $ ) {
	'use strict';

	// Register percentage discount using utility with inheritance
	SCD.Utils.registerModule( 'SCD.Modules.Discounts.Types', 'PercentageDiscount', function( state ) {
		// Call parent constructor
		SCD.Modules.Discounts.Types.BaseDiscount.call( this, state );

		this.type = 'percentage';
		this.config = SCD.Modules.Discounts.Config;

		if ( !SCD.Utils.ensureInitialized( this, {
			'config': this.config
		}, 'PercentageDiscount' ) ) {
			return;
		}
	} );

	// Inherit from BaseDiscount
	SCD.Modules.Discounts.Types.PercentageDiscount.prototype = Object.create( SCD.Modules.Discounts.Types.BaseDiscount.prototype );
	SCD.Modules.Discounts.Types.PercentageDiscount.prototype.constructor =
		SCD.Modules.Discounts.Types.PercentageDiscount;

	// Override methods using utility extend
	SCD.Utils.extend( SCD.Modules.Discounts.Types.PercentageDiscount.prototype, {
		/**
		 * Initialize percentage discount
		 */
		init: function() {
			SCD.Modules.Discounts.Types.BaseDiscount.prototype.init.call( this );
			this.setupPercentageHandlers();
		},

		/**
		 * Set default values for percentage discount
		 */
		setDefaults: function() {
			this.updateInlinePreview();
		},

		/**
		 * Setup percentage-specific event handlers
		 */
	setupPercentageHandlers: function() {
		var self = this;

		// Handle percentage value changes with real-time validation
		$( document ).on( 'input.percentage change.percentage', '#discount_value_percentage', function() {
			var $field = $( this );
			var value = parseFloat( $field.val() );

		if ( window.SCD && window.SCD.ValidationError ) {
			window.SCD.ValidationError.clear( $field );
		}

			// Real-time validation
			if ( !isNaN( value ) ) {
				if ( 0 >= value ) {
					self.showFieldError( $field, 'Percentage must be greater than 0' );
				} else if ( 100 < value ) {
					self.showFieldError( $field, 'Percentage cannot exceed 100%' );
				}
			}

			self.updateInlinePreview();
		} );
	},

		/**
		 * Clear percentage-specific data
		 */
		clearData: function() {
			if ( this.state.updateCurrentConfig ) {
				this.state.updateCurrentConfig( {
					value: 0
				} );
			}
		},

		/**
		 * Show percentage UI elements
		 */
		showUI: function() {
			this.updateFieldConstraints();
			this.triggerCustomEvent( 'scd:discount:ui:show', [ 'percentage' ] );
		},

		/**
		 * Hide percentage UI elements
		 */
		hideUI: function() {
			$( '.scd-percentage-options, .scd-strategy-percentage' ).removeClass( 'active' );
			this.triggerCustomEvent( 'scd:discount:ui:hide', [ 'percentage' ] );
		},

		/**
		 * Update field constraints for percentage
		 */
		updateFieldConstraints: function() {
			var defaults = SCD.Utils.get( this.config, 'defaults.values.percentage', {} );
			var selector = SCD.Utils.get( this.config, 'constants.selectors.discountValue', '[name="discount_value_percentage"]' );
			var $field = $( selector );

			if ( $field.length ) {
				$field
					.attr( 'min', defaults.min || 0 )
					.attr( 'max', defaults.max || 100 )
					.attr( 'step', defaults.step || 0.01 )
					.attr( 'placeholder', defaults.placeholder || 'Enter percentage' );
			}
		},

		/**
		 * Handle state changes specific to percentage discount
		 * @param change
		 */
		handleStateChange: function( change ) {
			if ( 'discountConfig' === change.property ) {
				var newConfig = change.value;
				var oldConfig = change.oldValue;

				var newValue = SCD.Utils.get( newConfig, 'percentage.value' );
				var oldValue = SCD.Utils.get( oldConfig, 'percentage.value' );

				if ( newValue !== oldValue && newValue !== undefined ) {
					this.updateInlinePreview();
				}
			}
		},

		/**
		 * Validate percentage discount configuration
		 */
		validate: function() {
			var errors = {};
			var warnings = {};

			var value = this.state.getState ?
				parseFloat( this.state.getState( 'discountValuePercentage' ) ) : 0;

			if ( isNaN( value ) || 0 >= value ) {
				errors.discountValuePercentage = 'Please enter a valid percentage';
			} else if ( 100 < value ) {
				errors.discountValuePercentage = 'Percentage cannot exceed 100%';
			} else if ( 50 < value ) {
				warnings.discountValuePercentage = 'Large discount percentage. Please verify this is intended.';
			}

			var isValid = SCD.Utils.isEmpty( errors );

			// Log validation if errors exist
			if ( !isValid ) {
				SCD.ErrorHandler.handle(
					new Error( 'Percentage discount validation failed' ),
					'PercentageDiscount.validate',
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
		 * Collect percentage discount data
		 */
		collectData: function() {
			var value = this.state.getState ? this.state.getState( 'discountValuePercentage' ) : 0;

			return {
				discountType: 'percentage',
				discountValue: value,
				discountValuePercentage: value
			};
		},

		/**
		 * Load percentage discount data
		 * @param data
		 */
		loadData: function( data ) {
			if ( !SCD.Utils.isEmpty( data ) ) {
				// Data already converted to camelCase by server
				if ( data.discountValuePercentage !== undefined && this.state.setState ) {
					this.state.setState( {
						discountValuePercentage: parseFloat( data.discountValuePercentage ) || 0
					} );
				}
			}
		},

		/**
		 * Get summary text for percentage discount
		 */
		getSummary: function() {
			var value = this.state.getState ? this.state.getState( 'discountValuePercentage' ) : 0;
			return value + '% off';
		},

		/**
		 * Calculate effective discount value
		 */
		calculateValue: function() {
			var config = this.state.getCurrentConfig ? this.state.getCurrentConfig() : {};
			return SCD.Utils.get( config, 'value', 0 );
		},

		/**
		 * Get configuration for preview
		 */
		getPreviewConfig: function() {
			var value = this.calculateValue();
			return {
				type: 'percentage',
				value: value,
				formatted: value + '%'
			};
		},

		/**
		 * Get help text for this discount type
		 */
		getHelpText: function() {
			return 'Enter a percentage between 0 and 100. This percentage will be deducted from the product price.';
		},

		/**
		 * Check if configuration would result in free products
		 */
		wouldBeFree: function() {
			return 100 <= this.calculateValue();
		},

		/**
		 * Format value for display
		 * @param value
		 */
		formatValue: function( value ) {
			return value + '%';
		},

		/**
		 * Update inline preview text
		 */
		updateInlinePreview: function() {
			var $preview = $( '#percentage-preview .preview-text' );
			if ( !$preview.length ) {return;}

			var value = parseFloat( $( '#discount_value_percentage' ).val() ) || 0;

			if ( 0 < value ) {
				$preview.removeClass( 'placeholder' ).text( value + '% off all selected products' );
			} else {
				$preview.addClass( 'placeholder' ).text( 'Enter a percentage to see the discount preview' );
			}
		},

		/**
		 * Clean up
		 */
		destroy: function() {
			$( document ).off( 'input.percentage change.percentage' );

			SCD.Modules.Discounts.Types.BaseDiscount.prototype.destroy.call( this );
		}
	} );

} )( jQuery );