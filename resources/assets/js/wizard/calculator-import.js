/**
 * Calculator Import Modal
 *
 * Handles importing discount presets from the external Profit Calculator
 * tool into the campaign wizard.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/wizard/calculator-import.js
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

( function( $ ) {
	'use strict';

	if ( 'undefined' === typeof $ ) {
		return;
	}

	window.WSSCD = window.WSSCD || {};
	window.WSSCD.Wizard = window.WSSCD.Wizard || {};

	/**
	 * Calculator Import Handler
	 *
	 * @since 1.0.0
	 */
	var CalculatorImport = {
		/**
		 * Initialization state
		 *
		 * @since 1.0.0
		 */
		initialized: false,

		/**
		 * Modal element
		 *
		 * @since 1.0.0
		 */
		$modal: null,

		/**
		 * Import in progress flag
		 *
		 * @since 1.0.0
		 */
		importing: false,

		/**
		 * Selectors
		 *
		 * @since 1.0.0
		 */
		selectors: {
			modal: '#wsscd-import-modal',
			openButton: '#wsscd-open-import-modal',
			closeButton: '#wsscd-close-import-modal',
			cancelButton: '#wsscd-cancel-import',
			submitButton: '#wsscd-submit-import',
			codeInput: '#wsscd-preset-code',
			errorContainer: '#wsscd-import-error',
			overlay: '.wsscd-modal__overlay'
		},

		/**
		 * Initialize calculator import handler
		 *
		 * @since 1.0.0
		 */
		init: function() {
			if ( this.initialized ) {
				return;
			}

			this.$modal = $( this.selectors.modal );

			if ( 0 === this.$modal.length ) {
				return;
			}

			this.bindEvents();
			this.initialized = true;
		},

		/**
		 * Bind events
		 *
		 * @since 1.0.0
		 */
		bindEvents: function() {
			var self = this;

			// Open modal
			$( document ).on( 'click', this.selectors.openButton, function( e ) {
				e.preventDefault();
				self.open();
			} );

			// Close modal via close button
			$( document ).on( 'click', this.selectors.closeButton, function( e ) {
				e.preventDefault();
				self.close();
			} );

			// Close modal via cancel button
			$( document ).on( 'click', this.selectors.cancelButton, function( e ) {
				e.preventDefault();
				self.close();
			} );

			// Close modal via overlay click
			$( document ).on( 'click', this.selectors.overlay, function( e ) {
				if ( $( e.target ).is( self.selectors.overlay ) ) {
					self.close();
				}
			} );

			// Submit import
			$( document ).on( 'click', this.selectors.submitButton, function( e ) {
				e.preventDefault();
				self.submit();
			} );

			// Handle enter key in input
			$( document ).on( 'keypress', this.selectors.codeInput, function( e ) {
				if ( 13 === e.which ) {
					e.preventDefault();
					self.submit();
				}
			} );

			// Close on escape key
			$( document ).on( 'keydown', function( e ) {
				if ( 27 === e.which && self.$modal.is( ':visible' ) ) {
					self.close();
				}
			} );

			// Clear error on input
			$( document ).on( 'input', this.selectors.codeInput, function() {
				self.clearError();
			} );
		},

		/**
		 * Open modal
		 *
		 * @since 1.0.0
		 */
		open: function() {
			this.$modal.show();
			this.clearError();
			$( this.selectors.codeInput ).val( '' ).focus();
			$( 'body' ).addClass( 'wsscd-modal-open' );
		},

		/**
		 * Close modal
		 *
		 * @since 1.0.0
		 */
		close: function() {
			if ( this.importing ) {
				return;
			}

			this.$modal.hide();
			this.clearError();
			$( this.selectors.codeInput ).val( '' );
			$( 'body' ).removeClass( 'wsscd-modal-open' );
		},

		/**
		 * Submit import
		 *
		 * @since 1.0.0
		 */
		submit: function() {
			var self = this;
			var code = $( this.selectors.codeInput ).val().trim();

			if ( this.importing ) {
				return;
			}

			// Validate code format
			if ( ! code ) {
				this.showError( 'Please enter a preset code.' );
				return;
			}

			if ( 0 !== code.indexOf( 'SCD:' ) ) {
				this.showError( 'Invalid code format. Code must start with "SCD:"' );
				return;
			}

			// Set loading state
			this.importing = true;
			this.setLoading( true );

			// Get nonce from available sources
			var nonce = '';
			if ( window.scdCalculatorImport && window.scdCalculatorImport.nonce ) {
				nonce = window.scdCalculatorImport.nonce;
			} else if ( window.wsscdCampaignListL10n && window.wsscdCampaignListL10n.nonce ) {
				nonce = window.wsscdCampaignListL10n.nonce;
			} else if ( window.scdWizard && window.scdWizard.nonce ) {
				nonce = window.scdWizard.nonce;
			}

			if ( ! nonce ) {
				self.showError( 'Security token not found. Please refresh the page and try again.' );
				self.importing = false;
				self.setLoading( false );
				return;
			}

			// Make AJAX request
			$.ajax( {
				url: window.ajaxurl,
				type: 'POST',
				data: {
					action: 'wsscd_import_calculator_preset',
					nonce: nonce,
					code: code
				},
				success: function( response ) {
					if ( response.success && response.data && response.data.redirectUrl ) {
						// Show success notification before redirect
						if ( window.SCD && window.SCD.Shared && window.SCD.Shared.NotificationService ) {
							SCD.Shared.NotificationService.success( response.data.message || 'Preset imported successfully!' );
						}

						// Redirect to wizard
						setTimeout( function() {
							window.location.href = response.data.redirectUrl;
						}, 500 );
					} else {
						var errorMsg = response.data && response.data.message ?
							response.data.message :
							'Failed to import preset. Please try again.';
						self.showError( errorMsg );
						self.importing = false;
						self.setLoading( false );
					}
				},
				error: function( xhr ) {
					var errorMsg = 'An error occurred. Please try again.';

					if ( xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ) {
						errorMsg = xhr.responseJSON.data.message;
					} else if ( xhr.status === 0 ) {
						errorMsg = 'Network error. Please check your connection.';
					} else if ( xhr.status === 403 ) {
						errorMsg = 'Access denied. Please refresh and try again.';
					} else if ( xhr.status === 500 ) {
						errorMsg = 'Server error. Please check the error logs.';
					}

					self.showError( errorMsg );
					self.importing = false;
					self.setLoading( false );
				}
			} );
		},

		/**
		 * Set loading state
		 *
		 * @since 1.0.0
		 * @param {boolean} loading Loading state
		 */
		setLoading: function( loading ) {
			var $button = $( this.selectors.submitButton );
			var $input = $( this.selectors.codeInput );
			var $cancelButton = $( this.selectors.cancelButton );
			var $closeButton = $( this.selectors.closeButton );

			if ( loading ) {
				$button.find( '.wsscd-btn__text' ).hide();
				$button.find( '.wsscd-btn__loading' ).show();
				$button.prop( 'disabled', true );
				$input.prop( 'disabled', true );
				$cancelButton.prop( 'disabled', true );
				$closeButton.prop( 'disabled', true );
			} else {
				$button.find( '.wsscd-btn__text' ).show();
				$button.find( '.wsscd-btn__loading' ).hide();
				$button.prop( 'disabled', false );
				$input.prop( 'disabled', false );
				$cancelButton.prop( 'disabled', false );
				$closeButton.prop( 'disabled', false );
			}
		},

		/**
		 * Show error message
		 *
		 * @since 1.0.0
		 * @param {string} message Error message
		 */
		showError: function( message ) {
			var $error = $( this.selectors.errorContainer );
			$error.text( message ).show();
			$( this.selectors.codeInput ).focus();
		},

		/**
		 * Clear error message
		 *
		 * @since 1.0.0
		 */
		clearError: function() {
			$( this.selectors.errorContainer ).hide().text( '' );
		},

		/**
		 * Cleanup handler
		 *
		 * @since 1.0.0
		 */
		destroy: function() {
			$( document ).off( 'click', this.selectors.openButton );
			$( document ).off( 'click', this.selectors.closeButton );
			$( document ).off( 'click', this.selectors.cancelButton );
			$( document ).off( 'click', this.selectors.submitButton );
			$( document ).off( 'click', this.selectors.overlay );
			$( document ).off( 'keypress', this.selectors.codeInput );
			$( document ).off( 'input', this.selectors.codeInput );

			this.initialized = false;
			this.$modal = null;
		}
	};

	/**
	 * Auto-initialize on document ready
	 *
	 * @since 1.0.0
	 */
	$( function() {
		window.WSSCD.Wizard.CalculatorImport = CalculatorImport;

		// Initialize if on wizard page with the import card
		if ( $( '#wsscd-open-import-modal' ).length > 0 ) {
			CalculatorImport.init();
		}
	} );

} )( jQuery );
