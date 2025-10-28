/**
 * Campaign List Modal Handlers
 *
 * Handles draft conflict modal and campaign list interactions.
 *
 * @package SmartCycleDiscounts
 * @since   1.0.0
 */

( function( $ ) {
	'use strict';

	/**
	 * Session Conflict Modal Handler
	 *
	 * Manages the modal shown when user tries to create a new campaign
	 * while having an unsaved draft session.
	 *
	 * @since 1.0.0
	 */
	var SessionConflictModal = {
		/**
		 * Initialize the modal handler.
		 *
		 * @since 1.0.0
		 */
		init: function() {
			this.bindEvents();
		},

		/**
		 * Bind event listeners.
		 *
		 * @since 1.0.0
		 */
		bindEvents: function() {
			var self = this;

			$( '.scd-new-campaign-btn' ).on( 'click', function( e ) {
				var hasSession = $( this ).data( 'has-session' ) === 'true' || $( this ).data( 'has-session' ) === true;
				var sessionType = $( this ).data( 'session-type' );
				var campaignName = $( this ).data( 'campaign-name' );
				var currentStep = $( this ).data( 'current-step' );
				var stepNumber = $( this ).data( 'step-number' );
				var totalSteps = $( this ).data( 'total-steps' );
				var lastActivity = $( this ).data( 'last-activity' );

				// Only show modal if we have a session
				if ( hasSession === true && campaignName ) {
					e.preventDefault();
					self.showModal( sessionType, campaignName, currentStep, stepNumber, totalSteps, lastActivity );
				}
			} );
		},

		/**
		 * Show the conflict modal.
		 *
		 * @since 1.0.0
		 * @param {string} sessionType   Type of session (draft/edit).
		 * @param {string} campaignName  Name of the campaign.
		 */
		showModal: function( sessionType, campaignName ) {
			// Update modal message with campaign name
			var message = '<p>' + window.scdCampaignListL10n.unsavedDraftText + ' <strong>' + campaignName + '</strong></p>' +
			              '<p>' + window.scdCampaignListL10n.whatToDoText + '</p>';
			$( '#scd-modal-message' ).html( message );

			// Show draft action buttons
			$( '.scd-save-btn' ).show();
			$( '.scd-discard-btn' ).show();

			// Show the modal using SCD Modal API
			if ( typeof SCD !== 'undefined' && SCD.Modal ) {
				SCD.Modal.show( 'scd-draft-conflict-modal' );
			}
		}
	};

	/**
	 * Modal Action Handlers
	 *
	 * Handles clicks on modal buttons (save, discard, cancel, close).
	 *
	 * @since 1.0.0
	 */
	var ModalActions = {
		/**
		 * Initialize action handlers.
		 *
		 * @since 1.0.0
		 */
		init: function() {
			this.bindEvents();
		},

		/**
		 * Bind event listeners for modal actions.
		 *
		 * @since 1.0.0
		 */
		bindEvents: function() {
			var self = this;

			$( document ).on( 'click', '.scd-modal__actions button[data-action], .scd-modal-cancel, .scd-modal__close', function( e ) {
				e.preventDefault();
				var action = $( this ).data( 'action' );

				self.handleAction( action, $( this ) );
			} );
		},

		/**
		 * Handle modal action.
		 *
		 * @since 1.0.0
		 * @param {string} action  Action to perform.
		 * @param {jQuery} $button Button that was clicked.
		 */
		handleAction: function( action, $button ) {
			switch ( action ) {
				case 'save-new':
					this.saveDraftAndCreateNew( $button );
					break;

				case 'discard-new':
					this.discardDraftAndCreateNew( $button );
					break;

				case 'close':
					this.closeModal();
					break;

				default:
					// Handle cancel button and close button
					if ( $button.hasClass( 'scd-modal-cancel' ) || $button.hasClass( 'scd-modal__close' ) ) {
						this.closeModal();
					}
					break;
			}
		},

		/**
		 * Save current draft as campaign, then create new.
		 *
		 * @since 1.0.0
		 * @param {jQuery} $button Button that was clicked.
		 */
		saveDraftAndCreateNew: function( $button ) {
			// Disable button and update text
			$button.prop( 'disabled', true ).text( window.scdCampaignListL10n.savingDraftText );

			// Make AJAX call to save the current draft
			$.ajax( {
				url: window.ajaxurl,
				type: 'POST',
				data: {
					action: 'scd_ajax',
					scd_action: 'save_draft',
					save_as_draft: true,
					nonce: window.scdCampaignListL10n.nonce
				},
				success: function( response ) {
					// After saving, redirect to create new
					window.location.href = window.scdCampaignListL10n.adminUrl + '?page=scd-campaigns&action=wizard&intent=new';
				},
				error: function( xhr, status, error ) {
					// Enable button and show error
					$button.prop( 'disabled', false ).text( window.scdCampaignListL10n.saveDraftButtonText );
					alert( window.scdCampaignListL10n.saveDraftErrorText );
				}
			} );
		},

		/**
		 * Discard current draft, then create new.
		 *
		 * @since 1.0.0
		 * @param {jQuery} $button Button that was clicked.
		 */
		discardDraftAndCreateNew: function( $button ) {
			// Disable button and update text
			$button.prop( 'disabled', true ).text( window.scdCampaignListL10n.discardingText );

			// Make AJAX call to discard the draft
			$.ajax( {
				url: window.ajaxurl,
				type: 'POST',
				data: {
					action: 'scd_ajax',
					scd_action: 'delete_draft',
					draft_action: 'delete',
					draft_type: 'session',
					draft_id: 'current',
					nonce: window.scdCampaignListL10n.nonce
				},
				success: function( response ) {
					window.location.href = window.scdCampaignListL10n.adminUrl + '?page=scd-campaigns&action=wizard&intent=new';
				},
				error: function( xhr, status, error ) {
					// Still redirect even on error (graceful degradation)
					window.location.href = window.scdCampaignListL10n.adminUrl + '?page=scd-campaigns&action=wizard&intent=new';
				}
			} );
		},

		/**
		 * Close the modal.
		 *
		 * @since 1.0.0
		 */
		closeModal: function() {
			if ( typeof SCD !== 'undefined' && SCD.Modal ) {
				SCD.Modal.hide( 'scd-draft-conflict-modal' );
			}
		}
	};

	/**
	 * Initialize on document ready.
	 *
	 * @since 1.0.0
	 */
	$( document ).ready( function() {
		// Initialize modal handlers
		SessionConflictModal.init();
		ModalActions.init();
	} );

} )( jQuery );
