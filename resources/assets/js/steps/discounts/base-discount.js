/**
 * Base Discount
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/steps/discounts/base-discount.js
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 */

( function( $ ) {
	'use strict';

	// Ensure namespace exists
	window.SCD = window.SCD || {};
	SCD.Modules = SCD.Modules || {};
	SCD.Modules.Discounts = SCD.Modules.Discounts || {};
	SCD.Modules.Discounts.Types = SCD.Modules.Discounts.Types || {};

	/**
	 * Base Discount Type Constructor
	 * @param state
	 */
	SCD.Modules.Discounts.Types.BaseDiscount = function( state ) {
		this.state = state;
		this.type = 'base'; // Override in child classes
		this.initialized = false;

		// Initialize event manager if available
		if ( 'function' === typeof this.initEventManager ) {
			this.initEventManager();
		}
	};

	// Extend with EventManager mixin if available
	if ( SCD.EventManager ) {
		$.extend( SCD.Modules.Discounts.Types.BaseDiscount.prototype, SCD.EventManager );
	} else if ( SCD.Mixins && SCD.Mixins.EventManager ) {
		$.extend( SCD.Modules.Discounts.Types.BaseDiscount.prototype, SCD.Mixins.EventManager );
	}

	$.extend( SCD.Modules.Discounts.Types.BaseDiscount.prototype, {
		/**
		 * Initialize the discount type
		 */
		init: function() {
			if ( this.initialized ) {
				return;
			}

			this.setupStateSubscription();
			this.initialized = true;
		},

		/**
		 * Setup state event listeners using event manager
		 */
		setupStateSubscription: function() {
			var self = this;

			// Listen for state changes using event manager
			this.bindCustomEvent( 'scd:discounts:state:changed', function( event, change ) {
				// Only react if this is the active discount type
				if ( self.state.getData && self.state.getData( 'discountType' ) === self.type ) {
					self.handleStateChange( change );
				}
			} );
		},

		/**
		 * Handle state changes specific to this discount type
		 * Override in child classes
		 * @param _change
		 */
		handleStateChange: function( _change ) {
			// To be implemented by child classes
		},

		/**
		 * Activate this discount type
		 */
		activate: function() {
			this.setDefaults();
			this.showUI();
			this.bindEvents();
			this.triggerCustomEvent( 'scd:discount:type:activated', [ this.type ] );
		},

		/**
		 * Deactivate this discount type
		 */
		deactivate: function() {
			this.hideUI();
			this.unbindEvents();
			this.clearData();
			this.triggerCustomEvent( 'scd:discount:type:deactivated', [ this.type ] );
		},

		/**
		 * Set default values for this discount type
		 * Override in child classes
		 */
		setDefaults: function() {
			// To be implemented by child classes
		},

		/**
		 * Clear data specific to this discount type
		 * Override in child classes
		 */
		clearData: function() {
			// To be implemented by child classes
		},

		/**
		 * Show UI elements for this discount type
		 * Override in child classes
		 */
		showUI: function() {
			// To be implemented by child classes
		},

		/**
		 * Hide UI elements for this discount type
		 * Override in child classes
		 */
		hideUI: function() {
			// To be implemented by child classes
		},

		/**
		 * Bind events specific to this discount type
		 * Override in child classes
		 */
		bindEvents: function() {
			// To be implemented by child classes
		},

		/**
		 * Unbind events specific to this discount type
		 * Override in child classes
		 */
		unbindEvents: function() {
			// To be implemented by child classes
		},

		/**
		 * Validate discount configuration
		 * Override in child classes for specific validation
		 */
		validate: function() {
			return {
				valid: true,
				errors: {},
				warnings: {}
			};
		},

		/**
		 * Collect data for this discount type
		 * Override in child classes
		 */
		collectData: function() {
			return {};
		},

		/**
		 * Load data for this discount type
		 * Override in child classes
		 * @param _data
		 */
		loadData: function( _data ) {
			// To be implemented by child classes
		},

		/**
		 * Get summary text for this discount
		 * Override in child classes
		 */
		getSummary: function() {
			return 'Discount configured';
		},

		/**
		 * Calculate effective discount value
		 * Override in child classes
		 */
		calculateValue: function() {
			return 0;
		},

		/**
		 * Get configuration for preview
		 * Override in child classes
		 */
		getPreviewConfig: function() {
			return {
				type: this.type,
				value: this.calculateValue()
			};
		},

		/**
		 * Update discount preview using event system
		 */
		updatePreview: function() {
			this.triggerCustomEvent( 'scd:discount:preview:update', [ {
				type: this.type,
				value: this.calculateValue(),
				config: this.getPreviewConfig()
			} ] );
		},

		/**
		 * Show field error message
		 * @param $field jQuery field element
		 * @param message Error message to display
		 */
		showFieldError: function( $field, message ) {
			if ( !$field || !$field.length ) {
				return;
			}

		// Use centralized ValidationError component
		if ( window.SCD && window.SCD.ValidationError ) {
			window.SCD.ValidationError.show( $field, message );
		}
		},

		/**
		 * Check if this discount type has all required data
		 */
		isComplete: function() {
			var validation = this.validate();
			return validation.valid;
		},

		/**
		 * Destroy this discount type instance
		 */
		destroy: function() {
			this.deactivate();
			this.unbindAllEvents();
			this.initialized = false;
			this.state = null;
		}
	} );

} )( jQuery );