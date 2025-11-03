/**
 * Wizard Save Indicator
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/wizard/wizard-save-indicator.js
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

( function( $ ) {
	'use strict';

	window.SCD = window.SCD || {};
	window.SCD.Wizard = window.SCD.Wizard || {};

	/**
	 * Save Indicator Module
	 *
	 * Shows visual feedback when the wizard auto-saves
	 */
	SCD.Wizard.SaveIndicator = {
		// Configuration
		config: {
			indicatorSelector: '.scd-save-indicator',
			fadeInDuration: 200,
			fadeOutDuration: 400,
			displayDuration: 2000,
			errorDisplayMultiplier: 2
		},

		// State
		$indicator: null,
		$icon: null,
		$text: null,
		hideTimeout: null,
		initialized: false,

		/**
		 * Initialize save indicator
		 */
		init: function() {
			if ( this.initialized ) {
				return;
			}

			this.createIndicator();

			this.cacheElements();

			// Bind events
			this.bindEvents();

			this.initialized = true;
		},

		/**
		 * Create indicator element
		 */
		createIndicator: function() {
			if ( 0 < $( this.config.indicatorSelector ).length ) {
				return;
			}

			var indicatorHtml = '<div class="scd-save-indicator" style="display: none;">' +
				'<span class="scd-save-icon"></span>' +
				'<span class="scd-save-text"></span>' +
				'</div>';

			var $header = $( '.scd-wizard-header' );
			if ( 0 < $header.length ) {
				$header.append( indicatorHtml );
			}
		},

		/**
		 * Cache jQuery elements
		 */
		cacheElements: function() {
			this.$indicator = $( this.config.indicatorSelector );
			if ( 0 < this.$indicator.length ) {
				this.$icon = this.$indicator.find( '.scd-save-icon' );
				this.$text = this.$indicator.find( '.scd-save-text' );
			}
		},

		/**
		 * Bind events
		 */
		bindEvents: function() {
			if ( ! window.SCD || ! window.SCD.Wizard || ! window.SCD.Wizard.EventBus ) {
				return;
			}

			var self = this;
			var eventBus = window.SCD.Wizard.EventBus;

			// Save start (generic - works with Smart Save)
			eventBus.on( 'save:start', function() {
				self.showSaving();
			} );

			// Save success
			eventBus.on( 'save:success', function() {
				self.showSuccess();
			} );

			// Save error
			eventBus.on( 'save:error', function() {
				self.showError();
			} );
		},

		/**
		 * Show saving state
		 */
		showSaving: function() {
			if ( ! this.$indicator || 0 === this.$indicator.length ) {
				return;
			}

			this.clearHideTimeout();

			this.$indicator
				.removeClass( 'scd-save-success scd-save-error' )
				.addClass( 'scd-save-saving' );

			this.$icon.html( '<span class="dashicons dashicons-update scd-spin"></span>' );

			this.$text.text( this.getTranslation( 'saving', 'Saving...' ) );

			this.$indicator.stop( true, true ).fadeIn( this.config.fadeInDuration );
		},

		/**
		 * Show success state
		 */
		showSuccess: function() {
			if ( ! this.$indicator || 0 === this.$indicator.length ) {
				return;
			}

			this.$indicator
				.removeClass( 'scd-save-saving scd-save-error' )
				.addClass( 'scd-save-success' );

			this.$icon.html( '<span class="dashicons dashicons-yes-alt"></span>' );

			this.$text.text( this.getTranslation( 'saved', 'Saved' ) );

			// Auto hide after delay
			this.scheduleHide( this.config.displayDuration );
		},

		/**
		 * Show error state
		 */
		showError: function() {
			if ( ! this.$indicator || 0 === this.$indicator.length ) {
				return;
			}

			this.$indicator
				.removeClass( 'scd-save-saving scd-save-success' )
				.addClass( 'scd-save-error' );

			this.$icon.html( '<span class="dashicons dashicons-dismiss"></span>' );

			this.$text.text( this.getTranslation( 'save_failed', 'Save failed' ) );

			// Keep error visible longer
			this.scheduleHide( this.config.displayDuration * this.config.errorDisplayMultiplier );
		},

		/**
		 * Schedule indicator hide
		 *
		 * @param {number} delay Delay in milliseconds
		 */
		scheduleHide: function( delay ) {
			var self = this;

			this.clearHideTimeout();

			this.hideTimeout = setTimeout( function() {
				if ( self.$indicator ) {
					self.$indicator.fadeOut( self.config.fadeOutDuration );
				}
			}, delay );
		},

		/**
		 * Clear hide timeout
		 */
		clearHideTimeout: function() {
			if ( this.hideTimeout ) {
				clearTimeout( this.hideTimeout );
				this.hideTimeout = null;
			}
		},

		/**
		 * Get translation
		 *
		 * @param {string} key Translation key
		 * @param {string} fallback Fallback text
		 * @returns {string} Translated text
		 */
		getTranslation: function( key, fallback ) {
			if ( window.scdAdmin && window.scdAdmin.i18n && window.scdAdmin.i18n[key] ) {
				return window.scdAdmin.i18n[key];
			}
			return fallback;
		},

		/**
		 * Destroy save indicator
		 */
		destroy: function() {
			this.clearHideTimeout();

			if ( this.$indicator ) {
				this.$indicator.remove();
			}

			this.$indicator = null;
			this.$icon = null;
			this.$text = null;
			this.initialized = false;
		}
	};

	$( document ).ready( function() {
		if ( 0 < $( '.scd-wizard-wrap' ).length ) {
			SCD.Wizard.SaveIndicator.init();
		}
	} );

} )( jQuery );