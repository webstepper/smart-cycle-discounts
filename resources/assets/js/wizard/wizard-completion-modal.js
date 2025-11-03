/**
 * Wizard Completion Modal
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/wizard/wizard-completion-modal.js
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
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

			// SINGLE SOURCE OF TRUTH: Wizard State Manager
			var isEditMode = this.isEditMode();

			// Set loading state
			this.$modal
				.removeClass( this.config.successClass + ' ' + this.config.errorClass )
				.addClass( this.config.loadingClass + ' ' + this.config.activeClass )
				.attr( 'aria-busy', 'true' );

			// Update title based on mode
			var loadingTitle = isEditMode ? 'Updating Campaign...' : 'Creating Campaign...';
			this.$modal.find( '.scd-completion-title' ).text( loadingTitle );

			// Clear message and actions
			this.$modal.find( '.scd-completion-message' ).empty();
			this.$modal.find( '.scd-completion-actions' ).empty();

			// Update screen reader status
			var loadingStatus = isEditMode ? 'Updating campaign. Please wait.' : 'Creating campaign. Please wait.';
			this.$modal.find( '.scd-completion-status' ).text( loadingStatus );

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

				// Add message based on status
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

			// SINGLE SOURCE OF TRUTH: Prefer data.isEditMode, fallback to State Manager
			var isEditMode = data.isEditMode || this.isEditMode();

			var errorMessage = data.message || data.error || ( isEditMode ? 'Failed to update campaign. Please try again.' : 'Failed to create campaign. Please try again.' );

			// Transition to error state
			setTimeout( function() {
				self.$modal
					.removeClass( self.config.loadingClass )
					.addClass( self.config.errorClass )
					.attr( 'aria-busy', 'false' );

				// Update title based on mode
				var errorTitle = isEditMode ? 'Campaign Update Failed' : 'Campaign Creation Failed';
				self.$modal.find( '.scd-completion-title' ).text( errorTitle );

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
		 * Check if in edit mode (SINGLE SOURCE OF TRUTH)
		 *
		 * @since 1.0.0
		 * @return {boolean} True if editing existing campaign
		 */
		isEditMode: function() {
			// Get from Wizard State Manager - the ONLY source of truth
			if ( window.SCD && window.SCD.Wizard && window.SCD.Wizard.StateManager ) {
				var wizardState = window.SCD.Wizard.StateManager.get();
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
