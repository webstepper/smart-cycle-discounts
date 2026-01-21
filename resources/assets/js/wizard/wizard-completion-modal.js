/**
 * Wizard Completion Modal
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/wizard/wizard-completion-modal.js
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
	 * Completion Modal Handler
	 *
	 * @since 1.0.0
	 */
	var CompletionModal = {
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
		 * Store completion data for retry
		 *
		 * @since 1.0.0
		 */
		completionData: null,

		/**
		 * Retry in progress flag
		 *
		 * @since 1.0.0
		 */
		retryInProgress: false,

		/**
		 * Configuration
		 *
		 * @since 1.0.0
		 */
		config: {
			overlayClass: 'wsscd-completion-overlay',
			modalClass: 'wsscd-completion-modal',
			loadingClass: 'is-loading',
			successClass: 'is-success',
			errorClass: 'is-error',
			activeClass: 'is-active',
			redirectDelay: 3000
		},

		/**
		 * Initialize modal handler
		 *
		 * @since 1.0.0
		 */
		init: function() {
			if ( this.initialized ) {
				return;
			}

			this.createModal();
			this.bindEvents();
			this.initialized = true;
		},

		/**
		 * Create modal DOM structure
		 *
		 * @since 1.0.0
		 */
		createModal: function() {
			var successIcon = WSSCD.IconHelper ? WSSCD.IconHelper.check( { size: 48 } ) : '<span class="wsscd-icon wsscd-icon-check"></span>';
			var errorIcon = WSSCD.IconHelper ? WSSCD.IconHelper.close( { size: 48 } ) : '<span class="wsscd-icon wsscd-icon-close"></span>';

			var modalHTML =
				'<div class="' + this.config.overlayClass + '" role="dialog" aria-modal="true" aria-labelledby="wsscd-completion-title" aria-busy="false">' +
					'<div class="' + this.config.modalClass + '">' +
						'<div class="wsscd-completion-icon-wrapper">' +
							'<span class="wsscd-completion-icon wsscd-completion-icon--success" aria-hidden="true">' + successIcon + '</span>' +
							'<span class="wsscd-completion-icon wsscd-completion-icon--error" aria-hidden="true">' + errorIcon + '</span>' +
						'</div>' +
						'<h2 id="wsscd-completion-title" class="wsscd-completion-title"></h2>' +
						'<p class="wsscd-completion-message"></p>' +
						'<div class="wsscd-completion-actions"></div>' +
						'<div class="wsscd-completion-status" role="status" aria-live="polite" aria-atomic="true"></div>' +
					'</div>' +
				'</div>';

			this.$modal = $( modalHTML );
			$( 'body' ).append( this.$modal );
		},

		/**
		 * Bind events
		 *
		 * @since 1.0.0
		 */
		bindEvents: function() {
			var self = this;

			// Listen for wizard completion success
			$( document ).on( 'wsscd:wizard:completed', function( e, data ) {
				self.showSuccess( data );
			} );

			// Listen for wizard completion error
			$( document ).on( 'wsscd:wizard:error', function( e, data ) {
				self.showError( data );
			} );

			// Handle retry button click
			this.$modal.on( 'click', '.wsscd-completion-retry', function( e ) {
				e.preventDefault();
				self.handleRetry();
			} );

			// Handle cancel button click
			this.$modal.on( 'click', '.wsscd-completion-cancel', function( e ) {
				e.preventDefault();
				self.hide();
			} );

			// Handle view campaign button click
			this.$modal.on( 'click', '.wsscd-completion-view', function( e ) {
				e.preventDefault();
				var url = $( this ).attr( 'href' );
				if ( url ) {
					window.location.href = url;
				}
			} );
		},

		/**
		 * Show success state
		 *
		 * @since 1.0.0
		 * @param {object} data Completion data
		 */
		showSuccess: function( data ) {
			var self = this;

			if ( ! this.$modal ) {
				return;
			}

			// Debug logging

			this.completionData = data;

			var campaignName = data.campaignName || '';
			var isEditMode = data.isEditMode || false;

			// Build success message based on create vs. edit mode
			var message;
			if ( isEditMode ) {
				message = campaignName ?
					'Campaign "' + campaignName + '" Successfully Updated!' :
					( data.message || 'Campaign Successfully Updated!' );
			} else {
				message = campaignName ?
					'Campaign "' + campaignName + '" Successfully Created!' :
					( data.message || 'Campaign Successfully Created!' );
			}

			var redirectUrl = data.redirectUrl;
			var campaignId = data.campaignId;
			var viewUrl = redirectUrl;


			// If we have a campaign ID but no redirect URL, construct view URL (fallback)
			if ( campaignId && ! viewUrl ) {
				viewUrl = '/wp-admin/admin.php?page=wsscd-campaigns&action=view&id=' + campaignId;
			}

			// Show the modal overlay with active class
			self.$modal.addClass( self.config.activeClass );
			$( 'body' ).addClass( 'wsscd-completion-active' );

			// Transition to success state
			setTimeout( function() {
				self.$modal
					.removeClass( self.config.loadingClass )
					.addClass( self.config.successClass )
					.attr( 'aria-busy', 'false' );

				self.$modal.find( '.wsscd-completion-title' ).text( message );

				var statusMessage;
				if ( 'active' === data.status ) {
					statusMessage = isEditMode ?
						'Your changes have been saved and the campaign is now active.' :
						'Your campaign is now active and applying discounts.';
				} else if ( 'scheduled' === data.status ) {
					statusMessage = isEditMode ?
						'Your changes have been saved and the campaign will start at the scheduled time.' :
						'Your campaign has been scheduled and will start automatically at the scheduled time.';
				} else if ( 'paused' === data.status ) {
					statusMessage = 'Your campaign has been paused and is not currently active.';
				} else if ( 'draft' === data.status ) {
					statusMessage = isEditMode ?
						'Your changes have been saved as a draft. Publish when ready to activate discounts.' :
						'Your campaign has been saved as a draft. Publish when ready to activate discounts.';
				} else {
					statusMessage = isEditMode ?
						'Your changes have been saved.' :
						'Your campaign has been saved.';
				}
				self.$modal.find( '.wsscd-completion-message' ).text( statusMessage );

				if ( viewUrl ) {
					var viewButton = '<a href="' + viewUrl + '" class="button button-primary wsscd-completion-view">View Campaign</a>';
					self.$modal.find( '.wsscd-completion-actions' ).html( viewButton );
				}

				self.$modal.find( '.wsscd-completion-status' ).text( message + ' ' + statusMessage );

				// Schedule redirect
				if ( redirectUrl ) {
					setTimeout( function() {
						window.location.href = redirectUrl;
					}, self.config.redirectDelay );
				}
			}, 300 );
		},

		/**
		 * Show error state
		 *
		 * @since 1.0.0
		 * @param {object} data Error data
		 */
		showError: function( data ) {
			var self = this;

			if ( ! this.$modal ) {
				return;
			}

			this.completionData = data;

			// SINGLE SOURCE OF TRUTH: Prefer data.isEditMode, fallback to State Manager
			var isEditMode = data.isEditMode || this.isEditMode();

			var errorMessage = data.message || data.error || ( isEditMode ? 'Failed to update campaign. Please try again.' : 'Failed to create campaign. Please try again.' );

			// Show the modal overlay with active class
			self.$modal.addClass( self.config.activeClass );
			$( 'body' ).addClass( 'wsscd-completion-active' );

			// Transition to error state
			setTimeout( function() {
				self.$modal
					.removeClass( self.config.loadingClass )
					.addClass( self.config.errorClass )
					.attr( 'aria-busy', 'false' );

				var errorTitle = isEditMode ? 'Campaign Update Failed' : 'Campaign Creation Failed';
				self.$modal.find( '.wsscd-completion-title' ).text( errorTitle );

				self.$modal.find( '.wsscd-completion-message' ).text( errorMessage );

				var buttons = '<button type="button" class="button button-primary wsscd-completion-retry">Retry</button>' +
					'<button type="button" class="button wsscd-completion-cancel">Cancel</button>';
				self.$modal.find( '.wsscd-completion-actions' ).html( buttons );

				self.$modal.find( '.wsscd-completion-status' ).text( 'Error: ' + errorMessage );
			}, 300 );
		},

		/**
		 * Handle retry action
		 *
		 * @since 1.0.0
		 */
		handleRetry: function() {
			// Prevent multiple simultaneous retries
			if ( this.retryInProgress ) {
				return;
			}

			this.retryInProgress = true;

			// Hide modal and show fullscreen loader
			this.hide();
			if ( window.WSSCD && window.WSSCD.LoaderUtil ) {
				WSSCD.LoaderUtil.show( 'wsscd-wizard-completion-loading' );
			}

			// Trigger retry event for wizard orchestrator to handle
			$( document ).trigger( 'wsscd:wizard:retry' );

			var self = this;
			setTimeout( function() {
				self.retryInProgress = false;
			}, 2000 );
		},

		/**
		 * Hide modal
		 *
		 * @since 1.0.0
		 */
		hide: function() {
			if ( ! this.$modal ) {
				return;
			}

			this.$modal
				.removeClass( this.config.activeClass )
				.attr( 'aria-busy', 'false' );
			$( 'body' ).removeClass( 'wsscd-completion-active' );

			this.$modal.find( '.wsscd-completion-status' ).empty();
		},

		/**
		 * Check if in edit mode (SINGLE SOURCE OF TRUTH)
		 *
		 * @since 1.0.0
		 * @return {boolean} True if editing existing campaign
		 */
		isEditMode: function() {
			if ( window.WSSCD && window.WSSCD.Wizard && window.WSSCD.Wizard.StateManager ) {
				var wizardState = window.WSSCD.Wizard.StateManager.getInstance().get();
				return wizardState && ( wizardState.wizardMode === 'edit' || wizardState.campaignId > 0 );
			}
			return false;
		},

		/**
		 * Cleanup modal handler
		 *
		 * @since 1.0.0
		 */
		destroy: function() {
			$( document ).off( 'wsscd:wizard:completed wsscd:wizard:error' );

			if ( this.$modal ) {
				this.$modal.off( 'click' );
				this.$modal.remove();
				this.$modal = null;
			}

			$( 'body' ).removeClass( 'wsscd-completion-active' );
			this.initialized = false;
			this.completionData = null;
		}
	};

	/**
	 * Auto-initialize on document ready
	 *
	 * @since 1.0.0
	 */
	$( function() {
		window.WSSCD.Wizard.CompletionModal = CompletionModal;

		if ( $( '.wsscd-wizard-page' ).length > 0 ||
			$( '.wsscd-wizard-wrap' ).length > 0 ||
			window.location.href.indexOf( 'action=wizard' ) !== -1 ) {

			CompletionModal.init();
		}
	} );

} )( jQuery );
