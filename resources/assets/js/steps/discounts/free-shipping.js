/**
 * Free Shipping Module
 *
 * Handles free shipping UI in the discounts step of the campaign wizard.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/steps/discounts/free-shipping.js
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.2.0
 */

( function( $ ) {
	'use strict';

	window.WSSCD = window.WSSCD || {};
	WSSCD.Modules = WSSCD.Modules || {};
	WSSCD.Modules.Discounts = WSSCD.Modules.Discounts || {};

	/**
	 * Free Shipping Module
	 *
	 * @class WSSCD.Modules.Discounts.FreeShipping
	 */
	WSSCD.Modules.Discounts.FreeShipping = {
		/**
		 * Cached DOM elements
		 */
		$elements: {},

		/**
		 * Loaded shipping methods
		 */
		shippingMethods: [],

		/**
		 * Loading state
		 */
		isLoading: false,

		/**
		 * Initialization flag
		 */
		initialized: false,

		/**
		 * Initialize the module
		 */
		init: function() {
			if ( this.initialized ) {
				return;
			}

			this.cacheElements();

			// Check if required elements exist before proceeding.
			if ( ! this.$elements.$enableToggle.length ) {
				return;
			}

			this.bindEvents();
			this.initialized = true;

			// Check if we need to load shipping methods on init
			// (when "selected" is already chosen)
			if ( this.$elements.$selectedRadio.length && this.$elements.$selectedRadio.is( ':checked' ) ) {
				this.loadShippingMethods();
			}
		},

		/**
		 * Cache DOM elements
		 */
		cacheElements: function() {
			this.$elements = {
				$enableToggle: $( '#free_shipping_enabled' ),
				$configWrapper: $( '.wsscd-free-shipping-config-wrapper' ),
				$allRadio: $( 'input[name="free_shipping_method_type"][value="all"]' ),
				$selectedRadio: $( 'input[name="free_shipping_method_type"][value="selected"]' ),
				$methodsList: $( '#wsscd-shipping-methods-list' ),
				$loadingIndicator: $( '.wsscd-shipping-methods-loading' ),
				$checkboxesContainer: $( '.wsscd-shipping-methods-checkboxes' ),
				$hiddenInput: $( '#free_shipping_methods' ),
				$thresholdNote: $( '#wsscd-free-shipping-threshold-note' )
			};
		},

		/**
		 * Bind event handlers
		 */
		bindEvents: function() {
			var self = this;

			// Toggle free shipping enable/disable
			this.$elements.$enableToggle.on( 'change', function() {
				self.toggleConfigVisibility( $( this ).is( ':checked' ) );
			} );

			// Toggle between all/selected methods
			$( 'input[name="free_shipping_method_type"]' ).on( 'change', function() {
				self.handleMethodTypeChange( $( this ).val() );
			} );

			// Listen for discount type changes to show/hide threshold note
			$( document ).on( 'wsscd:discounts:type:changed', function( event, data ) {
				self.handleDiscountTypeChange( data.type );
			} );

			// Handle checkbox changes
			this.$elements.$checkboxesContainer.on( 'change', 'input[type="checkbox"]', function() {
				self.updateSelectedMethods();
			} );
		},

		/**
		 * Toggle config visibility based on enable checkbox
		 *
		 * @param {boolean} enabled Whether free shipping is enabled
		 */
		toggleConfigVisibility: function( enabled ) {
			if ( enabled ) {
				this.$elements.$configWrapper.removeClass( 'wsscd-hidden' );
			} else {
				this.$elements.$configWrapper.addClass( 'wsscd-hidden' );
			}
		},

		/**
		 * Handle method type radio change
		 *
		 * @param {string} type 'all' or 'selected'
		 */
		handleMethodTypeChange: function( type ) {
			if ( 'all' === type ) {
				this.$elements.$methodsList.addClass( 'wsscd-hidden' );
				this.$elements.$hiddenInput.val( 'all' );
			} else {
				this.$elements.$methodsList.removeClass( 'wsscd-hidden' );

				// Load shipping methods if not already loaded
				if ( 0 === this.shippingMethods.length && ! this.isLoading ) {
					this.loadShippingMethods();
				}
			}
		},

		/**
		 * Handle discount type change
		 *
		 * @param {string} discountType The selected discount type
		 */
		handleDiscountTypeChange: function( discountType ) {
			if ( 'spend_threshold' === discountType ) {
				this.$elements.$thresholdNote.removeClass( 'wsscd-hidden' );
			} else {
				this.$elements.$thresholdNote.addClass( 'wsscd-hidden' );
			}
		},

		/**
		 * Load shipping methods via AJAX
		 */
		loadShippingMethods: function() {
			var self = this;

			if ( this.isLoading ) {
				return;
			}

			this.isLoading = true;
			this.$elements.$loadingIndicator.show();
			this.$elements.$checkboxesContainer.empty();

			var ajaxData = {
				action: 'wsscd_ajax',
				wsscdAction: 'get_shipping_methods',
				nonce: this.getNonce()
			};

			$.ajax( {
				url: this.getAjaxUrl(),
				type: 'POST',
				data: ajaxData,
				success: function( response ) {
					self.isLoading = false;
					self.$elements.$loadingIndicator.hide();

					// Handle different response structures
					var methods = null;

					if ( response && response.success ) {
						// Standard structure: { success: true, data: { methods: [...] } }
						if ( response.data && response.data.methods ) {
							methods = response.data.methods;
						}
						// Alternative: { success: true, data: [...] } (methods directly in data)
						else if ( response.data && Array.isArray( response.data ) ) {
							methods = response.data;
						}
						// Alternative: { success: true, methods: [...] }
						else if ( response.methods ) {
							methods = response.methods;
						}
					}

					if ( null !== methods ) {
						self.shippingMethods = methods;
						self.renderShippingMethods();
					} else {
						self.showError( self.getI18n( 'loadingError' ) );
					}
				},
				error: function() {
					self.isLoading = false;
					self.$elements.$loadingIndicator.hide();
					self.showError( self.getI18n( 'loadingError' ) );
				}
			} );
		},

		/**
		 * Render shipping methods as checkboxes
		 */
		renderShippingMethods: function() {
			var self = this;
			var $container = this.$elements.$checkboxesContainer;
			var selectedMethods = this.getSelectedMethods();

			$container.empty();

			if ( 0 === this.shippingMethods.length ) {
				$container.html(
					'<p class="wsscd-shipping-methods-empty">' +
					this.getI18n( 'noShippingMethods' ) +
					'</p>'
				);
				return;
			}

			$.each( this.shippingMethods, function( index, method ) {
				var isChecked = -1 !== $.inArray( method.id, selectedMethods );
				var $checkbox = $(
					'<label class="wsscd-shipping-method-item">' +
					'<input type="checkbox" ' +
					'name="shipping_method_' + index + '" ' +
					'value="' + self.escapeAttr( method.id ) + '" ' +
					( isChecked ? 'checked' : '' ) + '>' +
					'<span class="wsscd-shipping-method-label">' +
					self.escapeHtml( method.title ) +
					'</span>' +
					'</label>'
				);

				$container.append( $checkbox );
			} );
		},

		/**
		 * Update selected methods in hidden input
		 */
		updateSelectedMethods: function() {
			var selectedIds = [];

			this.$elements.$checkboxesContainer.find( 'input:checked' ).each( function() {
				selectedIds.push( $( this ).val() );
			} );

			if ( 0 === selectedIds.length ) {
				// If none selected and "selected" mode, store empty array
				this.$elements.$hiddenInput.val( '[]' );
			} else {
				this.$elements.$hiddenInput.val( JSON.stringify( selectedIds ) );
			}
		},

		/**
		 * Get currently selected methods
		 *
		 * @return {Array} Array of selected method IDs
		 */
		getSelectedMethods: function() {
			var value = this.$elements.$hiddenInput.val();

			if ( ! value || 'all' === value ) {
				return [];
			}

			try {
				var parsed = JSON.parse( value );
				return Array.isArray( parsed ) ? parsed : [];
			} catch ( e ) {
				return [];
			}
		},

		/**
		 * Show error message
		 *
		 * @param {string} message Error message
		 */
		showError: function( message ) {
			this.$elements.$checkboxesContainer.html(
				'<p class="wsscd-shipping-methods-error">' +
				this.escapeHtml( message ) +
				'</p>'
			);
		},

		/**
		 * Get AJAX URL
		 *
		 * @return {string} AJAX URL
		 */
		getAjaxUrl: function() {
			return window.ajaxurl ||
				( window.wsscdWizardData && window.wsscdWizardData.ajaxUrl ) ||
				( window.wsscdAdmin && window.wsscdAdmin.ajaxUrl ) ||
				'';
		},

		/**
		 * Get nonce for AJAX requests
		 *
		 * @return {string} Nonce value
		 */
		getNonce: function() {
			return ( window.wsscdWizardData && window.wsscdWizardData.nonce ) ||
				( window.wsscdAdmin && window.wsscdAdmin.nonce ) ||
				'';
		},

		/**
		 * Get i18n string
		 *
		 * @param {string} key Translation key
		 * @return {string} Translated string
		 */
		getI18n: function( key ) {
			var i18n = ( window.wsscdWizardData && window.wsscdWizardData.i18n ) ||
				( window.wsscdAdmin && window.wsscdAdmin.i18n ) ||
				{};

			var defaults = {
				noShippingMethods: 'No shipping methods found. Please configure shipping zones in WooCommerce.',
				loadingError: 'Error loading shipping methods. Please try again.'
			};

			return i18n[ key ] || defaults[ key ] || key;
		},

		/**
		 * Escape HTML
		 *
		 * @param {string} str String to escape
		 * @return {string} Escaped string
		 */
		escapeHtml: function( str ) {
			var div = document.createElement( 'div' );
			div.textContent = str;
			return div.innerHTML;
		},

		/**
		 * Escape attribute value
		 *
		 * @param {string} str String to escape
		 * @return {string} Escaped string
		 */
		escapeAttr: function( str ) {
			return String( str )
				.replace( /&/g, '&amp;' )
				.replace( /"/g, '&quot;' )
				.replace( /'/g, '&#39;' )
				.replace( /</g, '&lt;' )
				.replace( />/g, '&gt;' );
		},

		/**
		 * Get free shipping configuration data
		 *
		 * @return {object} Free shipping config
		 */
		getData: function() {
			var enabled = this.$elements.$enableToggle.is( ':checked' );
			var methodType = $( 'input[name="free_shipping_method_type"]:checked' ).val();
			var methods = 'all';

			if ( 'selected' === methodType ) {
				var hiddenValue = this.$elements.$hiddenInput.val();
				if ( hiddenValue && 'all' !== hiddenValue ) {
					try {
						methods = JSON.parse( hiddenValue );
					} catch ( e ) {
						methods = [];
					}
				} else {
					methods = [];
				}
			}

			return {
				enabled: enabled,
				methods: methods
			};
		},

		/**
		 * Set free shipping configuration data
		 *
		 * @param {object} data Free shipping config
		 */
		setData: function( data ) {
			if ( ! data ) {
				return;
			}

			// Set enabled state
			this.$elements.$enableToggle.prop( 'checked', !! data.enabled );
			this.toggleConfigVisibility( !! data.enabled );

			// Set method type
			if ( 'all' === data.methods || ! Array.isArray( data.methods ) ) {
				this.$elements.$allRadio.prop( 'checked', true );
				this.$elements.$methodsList.addClass( 'wsscd-hidden' );
				this.$elements.$hiddenInput.val( 'all' );
			} else {
				this.$elements.$selectedRadio.prop( 'checked', true );
				this.$elements.$methodsList.removeClass( 'wsscd-hidden' );
				this.$elements.$hiddenInput.val( JSON.stringify( data.methods ) );

				// Load shipping methods if needed
				if ( 0 === this.shippingMethods.length && ! this.isLoading ) {
					this.loadShippingMethods();
				}
			}
		}
	};

	// Initialize when document is ready and on step activation
	$( document ).ready( function() {
		// Initialize immediately if on discounts step
		if ( $( '#free_shipping_enabled' ).length ) {
			WSSCD.Modules.Discounts.FreeShipping.init();
		}
	} );

	// Also initialize when discounts step is activated
	$( document ).on( 'wsscd:step:activated', function( event, data ) {
		if ( 'discounts' === data.step ) {
			WSSCD.Modules.Discounts.FreeShipping.init();
		}
	} );

} )( jQuery );
