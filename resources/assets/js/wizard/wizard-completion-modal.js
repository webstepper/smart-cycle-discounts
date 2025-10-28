/**
 * Wizard Completion Modal
 *
 * Handles centered modal for campaign creation process
 * with loading, success, and error states.
 *
 * @param $
 * @package SmartCycleDiscounts
 * @since 1.0.0
 */

( function( $ ) {
	'use strict';

	if ( 'undefined' === typeof $ ) {
		return;
	}

	window.SCD = window.SCD || {};
	window.SCD.Wizard = window.SCD.Wizard || {};

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
			overlayClass: 'scd-completion-overlay',
			modalClass: 'scd-completion-modal',
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
			var modalHTML =
				'<div class="' + this.config.overlayClass + '" role="dialog" aria-modal="true" aria-labelledby="scd-completion-title" aria-busy="false">' +
					'<div class="' + this.config.modalClass + '">' +
						'<div class="scd-completion-icon-wrapper">' +
							'<span class="dashicons dashicons-update scd-completion-icon scd-completion-icon--loading" aria-hidden="true"></span>' +
							'<span class="dashicons dashicons-yes-alt scd-completion-icon scd-completion-icon--success" aria-hidden="true"></span>' +
							'<span class="dashicons dashicons-dismiss scd-completion-icon scd-completion-icon--error" aria-hidden="true"></span>' +
						'</div>' +
						'<h2 id="scd-completion-title" class="scd-completion-title"></h2>' +
						'<p class="scd-completion-message"></p>' +
						'<div class="scd-completion-actions"></div>' +
						'<div class="scd-completion-status" role="status" aria-live="polite" aria-atomic="true"></div>' +
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

			// Listen for wizard completion start
			$( document ).on( 'scd:wizard:completing', function() {
				self.showLoading();
			} );

			// Listen for wizard completion success
			$( document ).on( 'scd:wizard:completed', function( e, data ) {
				self.showSuccess( data );
			} );

			// Listen for wizard completion error
			$( document ).on( 'scd:wizard:error', function( e, data ) {
				self.showError( data );
			} );

			// Handle retry button click
			this.$modal.on( 'click', '.scd-completion-retry', function( e ) {
				e.preventDefault();
				self.handleRetry();
			} );

			// Handle cancel button click
			this.$modal.on( 'click', '.scd-completion-cancel', function( e ) {
				e.preventDefault();
				self.hide();
			} );

			// Handle view campaign button click
			this.$modal.on( 'click', '.scd-completion-view', function( e ) {
				e.preventDefault();
				var url = $( this ).attr( 'href' );
				if ( url ) {
					// Navigate directly (no beforeunload warning to suppress)
					window.location.href = url;
				}
			} );
		},

		/**
		 * Show loading state
		 *
		 * @since 1.0.0
		 */
		showLoading: function() {
			if ( ! this.$modal ) {
				return;
			}

			// Set loading state
			this.$modal
				.removeClass( this.config.successClass + ' ' + this.config.errorClass )
				.addClass( this.config.loadingClass + ' ' + this.config.activeClass )
				.attr( 'aria-busy', 'true' );

			// Update title
			this.$modal.find( '.scd-completion-title' ).text( 'Creating Campaign...' );

			// Clear message and actions
			this.$modal.find( '.scd-completion-message' ).empty();
			this.$modal.find( '.scd-completion-actions' ).empty();

			// Update screen reader status
			this.$modal.find( '.scd-completion-status' ).text( 'Creating campaign. Please wait.' );

			// Prevent body scroll
			$( 'body' ).addClass( 'scd-completion-active' );
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

			// Store data for potential future use
			this.completionData = data;

			var campaignName = data.campaignName || '';
			var message = campaignName ?
				'Campaign "' + campaignName + '" Successfully Created!' :
				( data.message || 'Campaign Successfully Created!' );

			var redirectUrl = data.redirectUrl;
			var campaignId = data.campaignId;
			var viewUrl = redirectUrl;


			// If we have a campaign ID, construct edit URL
			if ( campaignId && ! viewUrl ) {
				viewUrl = '/wp-admin/admin.php?page=scd-campaigns&action=edit&id=' + campaignId;
			}

			// Transition to success state
			setTimeout( function() {
				self.$modal
					.removeClass( self.config.loadingClass )
					.addClass( self.config.successClass )
					.attr( 'aria-busy', 'false' );

				// Update title
				self.$modal.find( '.scd-completion-title' ).text( message );

				// Add message
				var statusMessage;
				if ( 'active' === data.status ) {
					statusMessage = 'Your campaign is now active and applying discounts.';
				} else if ( 'scheduled' === data.status ) {
					statusMessage = 'Your campaign has been scheduled and will start automatically at the scheduled time.';
				} else {
					statusMessage = 'Your campaign has been saved as a draft.';
				}
				self.$modal.find( '.scd-completion-message' ).text( statusMessage );

				// Add view button if we have a URL
				if ( viewUrl ) {
					var viewButton = '<a href="' + viewUrl + '" class="button button-primary scd-completion-view">View Campaign</a>';
					self.$modal.find( '.scd-completion-actions' ).html( viewButton );
				}

				// Update screen reader status
				self.$modal.find( '.scd-completion-status' ).text( message + ' ' + statusMessage );

				// Schedule redirect
				if ( redirectUrl ) {
					setTimeout( function() {
						// Navigate directly (no beforeunload warning to suppress)
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

			// Store data for retry
			this.completionData = data;

			var errorMessage = data.message || data.error || 'Failed to create campaign. Please try again.';

			// Transition to error state
			setTimeout( function() {
				self.$modal
					.removeClass( self.config.loadingClass )
					.addClass( self.config.errorClass )
					.attr( 'aria-busy', 'false' );

				// Update title
				self.$modal.find( '.scd-completion-title' ).text( 'Campaign Creation Failed' );

				// Add error message
				self.$modal.find( '.scd-completion-message' ).text( errorMessage );

				// Add action buttons
				var buttons = '<button type="button" class="button button-primary scd-completion-retry">Retry</button>' +
					'<button type="button" class="button scd-completion-cancel">Cancel</button>';
				self.$modal.find( '.scd-completion-actions' ).html( buttons );

				// Update screen reader status
				self.$modal.find( '.scd-completion-status' ).text( 'Error: ' + errorMessage );
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

			// Set retry flag
			this.retryInProgress = true;

			// Hide error state and show loading
			this.showLoading();

			// Trigger retry event for wizard orchestrator to handle
			$( document ).trigger( 'scd:wizard:retry' );

			// Reset flag after a delay (prevents rapid clicking)
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
			$( 'body' ).removeClass( 'scd-completion-active' );

			// Clear status
			this.$modal.find( '.scd-completion-status' ).empty();
		},

		/**
		 * Cleanup modal handler
		 *
		 * @since 1.0.0
		 */
		destroy: function() {
			$( document ).off( 'scd:wizard:completing scd:wizard:completed scd:wizard:error' );

			if ( this.$modal ) {
				this.$modal.off( 'click' );
				this.$modal.remove();
				this.$modal = null;
			}

			$( 'body' ).removeClass( 'scd-completion-active' );
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
		window.SCD.Wizard.CompletionModal = CompletionModal;

		if ( $( '.scd-wizard-page' ).length > 0 ||
			$( '.scd-wizard-wrap' ).length > 0 ||
			window.location.href.indexOf( 'action=wizard' ) !== -1 ) {

			CompletionModal.init();
		}
	} );

} )( jQuery );
