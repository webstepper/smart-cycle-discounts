/**
 * Percentage Discount
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/steps/discounts/percentage-discount.js
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

( function( $ ) {
	'use strict';

	// Register percentage discount using utility with inheritance
	WSSCD.Utils.registerModule( 'WSSCD.Modules.Discounts.Types', 'PercentageDiscount', function( state ) {
		// Call parent constructor
		WSSCD.Modules.Discounts.Types.BaseDiscount.call( this, state );

		this.type = 'percentage';
		this.config = WSSCD.Modules.Discounts.Config;

		if ( !WSSCD.Utils.ensureInitialized( this, {
			'config': this.config
		}, 'PercentageDiscount' ) ) {
			return;
		}
	} );

	// Inherit from BaseDiscount
	WSSCD.Modules.Discounts.Types.PercentageDiscount.prototype = Object.create( WSSCD.Modules.Discounts.Types.BaseDiscount.prototype );
	WSSCD.Modules.Discounts.Types.PercentageDiscount.prototype.constructor =
		WSSCD.Modules.Discounts.Types.PercentageDiscount;

	// Override methods using utility extend
	WSSCD.Utils.extend( WSSCD.Modules.Discounts.Types.PercentageDiscount.prototype, {
		/**
		 * Initialize percentage discount
		 */
		init: function() {
			WSSCD.Modules.Discounts.Types.BaseDiscount.prototype.init.call( this );
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

		if ( window.WSSCD && window.WSSCD.ValidationError ) {
			window.WSSCD.ValidationError.clear( $field );
		}

			// Update state immediately to maintain single source of truth
			if ( self.state && self.state.setState ) {
				self.state.setState( {
					discountValuePercentage: isNaN( value ) ? 0 : value
				} );
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
			this.triggerCustomEvent( 'wsscd:discount:ui:show', [ 'percentage' ] );
		},

		/**
		 * Hide percentage UI elements
		 */
		hideUI: function() {
			$( '.wsscd-percentage-options, .wsscd-strategy-percentage' ).removeClass( 'active' );
			this.triggerCustomEvent( 'wsscd:discount:ui:hide', [ 'percentage' ] );
		},

		/**
		 * Update field constraints for percentage
		 */
		updateFieldConstraints: function() {
			var defaults = WSSCD.Utils.get( this.config, 'defaults.values.percentage', {} );
			var selector = WSSCD.Utils.get( this.config, 'constants.selectors.discountValue', '[name="discount_value_percentage"]' );
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

				var newValue = WSSCD.Utils.get( newConfig, 'percentage.value' );
				var oldValue = WSSCD.Utils.get( oldConfig, 'percentage.value' );

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

			// Get value from state (now always synchronized via input handler)
			var value = this.state.getData ? parseFloat( this.state.getData( 'discountValuePercentage' ) ) : 0;

			if ( isNaN( value ) || 0 >= value ) {
				errors.discount_value_percentage = 'Please enter a valid percentage';
			} else if ( 100 < value ) {
				errors.discount_value_percentage = 'Percentage cannot exceed 100%';
			} else if ( 50 < value ) {
				warnings.discount_value_percentage = 'Large discount percentage. Please verify this is intended.';
			}

			var result = {
				valid: 0 === Object.keys( errors ).length,
				errors: errors,
				warnings: warnings
			};

			return result;
		},

		/**
		 * Collect percentage discount data
		 */
		collectData: function() {
			// Get value from state (now always synchronized via input handler)
			var value = this.state.getData ? parseFloat( this.state.getData( 'discountValuePercentage' ) ) : 0;
			value = isNaN( value ) ? 0 : value;

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
			if ( data && data.discountValuePercentage !== undefined && this.state.setState ) {
				this.state.setState( {
					discountValuePercentage: parseFloat( data.discountValuePercentage ) || 0
				} );
			}
		},

		/**
		 * Get summary text for percentage discount
		 */
		getSummary: function() {
			var value = this.state.getData ? this.state.getData( 'discountValuePercentage' ) : 0;
			return value + '% off';
		},

		/**
		 * Calculate effective discount value
		 */
		calculateValue: function() {
			var config = this.state.getCurrentConfig ? this.state.getCurrentConfig() : {};
			return WSSCD.Utils.get( config, 'value', 0 );
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

			WSSCD.Modules.Discounts.Types.BaseDiscount.prototype.destroy.call( this );
		}
	} );

} )( jQuery );