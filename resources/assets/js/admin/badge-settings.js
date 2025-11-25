/**
 * Badge Settings JavaScript
 *
 * Handles badge configuration UI interactions in the campaign wizard.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/admin
 * @since      1.0.0
 */

( function( $ ) {
	'use strict';

	/**
	 * Badge Settings Handler
	 *
	 * @since 1.0.0
	 */
	var BadgeSettings = {

		/**
		 * Cache of jQuery selectors
		 *
		 * @since 1.0.0
		 * @type {Object}
		 */
		cache: {},

		/**
		 * Initialization state
		 *
		 * @since 1.0.0
		 * @type {boolean}
		 */
		initialized: false,

		/**
		 * Context warnings for discount types
		 *
		 * @since 1.0.0
		 * @type {Object}
		 */
		contextWarnings: {
			'bogo': 'BOGO badges only display on product pages (require quantity context).',
			'spend_threshold': 'Spend threshold badges only display in cart (require cart total context).'
		},

		/**
		 * Initialize badge settings
		 *
		 * @since 1.0.0
		 */
		init: function() {
			if ( this.initialized ) {
				return;
			}

			this.cacheSelectors();
			this.bindEvents();
			this.initColorPickers();
			this.updateBadgeSettingsVisibility();
			this.updateCustomTextVisibility();
			this.updateContextWarning();

			// Wait for discount state to load before initial preview
			this.waitForStateAndRender();

			this.initialized = true;
		},

		/**
		 * Wait for discount state to load before rendering preview
		 * Fixes initial state jumping issue
		 *
		 * @since 1.0.0
		 */
		waitForStateAndRender: function() {
			var self = this;
			var maxAttempts = 20;
			var attempts = 0;
			var interval = 50;

			var checkState = function() {
				attempts++;

				// Check if discount type is loaded in DOM
				var discountType = $( '#discount_type' ).val();

				if ( discountType && '' !== discountType ) {
					// State is loaded, render preview
					self.updatePreview();
				} else if ( attempts < maxAttempts ) {
					// State not loaded yet, try again
					setTimeout( checkState, interval );
				} else {
					// Timeout reached, render with defaults
					self.updatePreview();
				}
			};

			// Start checking
			setTimeout( checkState, interval );
		},

		/**
		 * Cache jQuery selectors for performance
		 *
		 * @since 1.0.0
		 */
		cacheSelectors: function() {
			this.cache = {
				badgeEnabled: $( '#badge_enabled' ),
				badgeSettings: $( '.scd-badge-setting' ),
				badgeTextMode: $( '#badge_text_mode' ),
				customTextContainer: $( '.scd-custom-badge-text' ),
				badgeTextCustom: $( '#badge_text_custom' ),
				badgeText: $( '#badge_text' ),
				badgeBgColor: $( '#badge_bg_color' ),
				badgeTextColor: $( '#badge_text_color' ),
				badgePosition: $( 'input[name="badge_position"]' ),
				previewBadge: $( '.scd-badge-preview-badge' ),
				contextWarning: $( '.scd-badge-context-warning' ),
				contextWarningText: $( '.scd-context-warning-text' )
			};
		},

		/**
		 * Bind event handlers
		 *
		 * @since 1.0.0
		 */
		bindEvents: function() {
			var self = this;

			// Badge enabled toggle
			this.cache.badgeEnabled.on( 'change', function() {
				self.updateBadgeSettingsVisibility();
				self.updatePreview();
			} );

			// Badge text mode change
			this.cache.badgeTextMode.on( 'change', function() {
				self.updateCustomTextVisibility();
				self.syncBadgeTextValue();
				self.updatePreview();
			} );

			// Custom badge text input
			this.cache.badgeTextCustom.on( 'input', function() {
				self.syncBadgeTextValue();
				self.updatePreview();
			} );

			// Color pickers
			this.cache.badgeBgColor.on( 'change', function() {
				self.updatePreview();
			} );

			this.cache.badgeTextColor.on( 'change', function() {
				self.updatePreview();
			} );

			// Position radio buttons
			this.cache.badgePosition.on( 'change', function() {
				self.updatePreview();
			} );

			// Discount type change - update context warning and preview
			$( document ).on( 'change', '#discount_type', function() {
				self.updateContextWarning();
				// Delay to allow discount type fields to load
				setTimeout( function() {
					self.updatePreview();
				}, 50 );
			} );

			// Discount value changes - update preview in real-time
			// Use event delegation to handle dynamically loaded fields
			$( document ).on( 'input change', '#discount_value_percentage, #discount_value_fixed', function() {
				self.updatePreview();
			} );

			// BOGO discount fields
			$( document ).on( 'input change', '#bogo_buy_quantity, #bogo_get_quantity, #bogo_discount_percentage', function() {
				self.updatePreview();
			} );

			// Tiered discount changes
			$( document ).on( 'scd:discounts:value:changed', function() {
				self.updatePreview();
			} );

			// Listen for discount state changes
			$( document ).on( 'scd:discounts:type:changed', function() {
				self.updateContextWarning();
				// Delay to allow new discount type UI to render
				setTimeout( function() {
					self.updatePreview();
				}, 100 );
			} );
		},

		/**
		 * Initialize WordPress color pickers
		 *
		 * @since 1.0.0
		 */
		initColorPickers: function() {
			var self = this;

			if ( $.fn.wpColorPicker ) {
				this.cache.badgeBgColor.wpColorPicker( {
					change: function() {
						self.updatePreview();
					},
					clear: function() {
						self.updatePreview();
					}
				} );

				this.cache.badgeTextColor.wpColorPicker( {
					change: function() {
						self.updatePreview();
					},
					clear: function() {
						self.updatePreview();
					}
				} );
			}
		},

		/**
		 * Update visibility of badge settings based on badge_enabled
		 *
		 * @since 1.0.0
		 */
		updateBadgeSettingsVisibility: function() {
			var isEnabled = this.cache.badgeEnabled.is( ':checked' );

			if ( isEnabled ) {
				this.cache.badgeSettings.show();
			} else {
				this.cache.badgeSettings.hide();
			}
		},

		/**
		 * Update visibility of custom text input based on mode
		 *
		 * @since 1.0.0
		 */
		updateCustomTextVisibility: function() {
			var mode = this.cache.badgeTextMode.val();

			if ( 'custom' === mode ) {
				this.cache.customTextContainer.show();
			} else {
				this.cache.customTextContainer.hide();
			}
		},

		/**
		 * Sync badge_text hidden field with current state
		 *
		 * @since 1.0.0
		 */
		syncBadgeTextValue: function() {
			var mode = this.cache.badgeTextMode.val();
			var value;

			if ( 'auto' === mode ) {
				value = 'auto';
			} else {
				value = this.cache.badgeTextCustom.val();
			}

			this.cache.badgeText.val( value );
		},

		/**
		 * Update context warning based on discount type
		 *
		 * @since 1.0.0
		 */
		updateContextWarning: function() {
			// Use fresh selector to get current value
			var discountType = $( '#discount_type' ).val();
			var warning = this.contextWarnings[ discountType ];

			if ( warning ) {
				this.cache.contextWarningText.text( warning );
				this.cache.contextWarning.show();
			} else {
				this.cache.contextWarning.hide();
			}
		},

		/**
		 * Update badge preview in real-time
		 *
		 * @since 1.0.0
		 */
		updatePreview: function() {
			// Ensure preview element exists
			if ( 0 === this.cache.previewBadge.length ) {
				return;
			}

			try {
				var isEnabled = this.cache.badgeEnabled.is( ':checked' );

				if ( ! isEnabled ) {
					this.cache.previewBadge.hide();
					return;
				}

				this.cache.previewBadge.show();

				// Update text
				var badgeText = this.getBadgeText();
				this.cache.previewBadge.text( badgeText );

				// Update colors
				var bgColor = this.cache.badgeBgColor.val() || '#ff0000';
				var textColor = this.cache.badgeTextColor.val() || '#ffffff';
				this.cache.previewBadge.css( {
					'background-color': bgColor,
					'color': textColor
				} );

				// Update position
				var position = this.cache.badgePosition.filter( ':checked' ).val() || 'top-right';
				this.cache.previewBadge.attr( 'data-position', position );
				this.updatePreviewPosition( position );
			} catch ( error ) {
				// Silently handle errors to prevent breaking other functionality
				if ( window.console && window.console.error ) {
					window.console.error( 'Badge preview error:', error );
				}
			}
		},

		/**
		 * Get badge text based on current settings
		 *
		 * @since 1.0.0
		 * @return {string} Badge text
		 */
		getBadgeText: function() {
			var mode = this.cache.badgeTextMode.val();

			if ( 'custom' === mode ) {
				var customText = this.cache.badgeTextCustom.val();
				return customText || 'SALE';
			}

			// Auto-generate based on discount type
			// Use fresh selectors to get current values in real-time
			var discountType = $( '#discount_type' ).val();
			var text = 'SALE';

			if ( 'percentage' === discountType ) {
				var percentValue = $( '#discount_value_percentage' ).val();
				if ( percentValue && '' !== percentValue ) {
					text = percentValue + '% OFF';
				}
			} else if ( 'fixed' === discountType ) {
				var fixedValue = $( '#discount_value_fixed' ).val();
				if ( fixedValue && '' !== fixedValue ) {
					var symbol = ( window.scdSettings && window.scdSettings.currencySymbol ) || '$';
					text = symbol + fixedValue + ' OFF';
				}
			} else if ( 'tiered' === discountType ) {
				text = 'VOLUME DISCOUNT';
			} else if ( 'bogo' === discountType ) {
				var buyQty = $( '#bogo_buy_quantity' ).val() || '1';
				var getQty = $( '#bogo_get_quantity' ).val() || '1';
				if ( buyQty && '' !== buyQty && getQty && '' !== getQty ) {
					text = 'BUY ' + buyQty + ' GET ' + getQty;
				} else {
					text = 'BOGO';
				}
			} else if ( 'spend_threshold' === discountType ) {
				text = 'SPEND & SAVE';
			}

			return text;
		},

		/**
		 * Update preview badge position
		 *
		 * @since 1.0.0
		 * @param {string} position Position value
		 */
		updatePreviewPosition: function( position ) {
			// Remove all position classes
			this.cache.previewBadge.removeClass( 'scd-position-top-left scd-position-top-right scd-position-bottom-left scd-position-bottom-right' );

			// Add current position class
			this.cache.previewBadge.addClass( 'scd-position-' + position );

			// Apply inline positioning
			var positionStyles = {
				'top-left': { top: '10px', left: '10px', right: 'auto', bottom: 'auto' },
				'top-right': { top: '10px', right: '10px', left: 'auto', bottom: 'auto' },
				'bottom-left': { bottom: '10px', left: '10px', top: 'auto', right: 'auto' },
				'bottom-right': { bottom: '10px', right: '10px', top: 'auto', left: 'auto' }
			};

			if ( positionStyles[ position ] ) {
				this.cache.previewBadge.css( positionStyles[ position ] );
			}
		}
	};

	/**
	 * Initialize on document ready
	 */
	$( document ).ready( function() {
		// Only initialize if we're on the discounts step
		if ( $( '#badge_enabled' ).length > 0 ) {
			BadgeSettings.init();
		}
	} );

} )( jQuery );
