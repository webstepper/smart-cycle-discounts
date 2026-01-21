/**
 * Campaign List Modals
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/admin/campaign-list-modals.js
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
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

			$( '.wsscd-new-campaign-btn' ).on( 'click', function( e ) {
				var hasSession = $( this ).data( 'has-session' ) === 'true' || $( this ).data( 'has-session' ) === true;
				var sessionType = $( this ).data( 'session-type' );
				var campaignName = $( this ).data( 'campaign-name' );
				var currentStep = $( this ).data( 'current-step' );
				var stepNumber = $( this ).data( 'step-number' );
				var totalSteps = $( this ).data( 'total-steps' );
				var lastActivity = $( this ).data( 'last-activity' );

				// Only show modal if we have a session
				if ( true === hasSession && campaignName ) {
					e.preventDefault();
					self.showModal( sessionType, campaignName, currentStep, stepNumber, totalSteps, lastActivity );
				}
			} );
		},

		/**
		 * Show the conflict modal.
		 *
		 * Modal content is now pre-populated server-side with draft info.
		 *
		 * @since 1.0.0
		 * @param {string} sessionType   Type of session (draft/edit).
		 * @param {string} campaignName  Name of the campaign.
		 */
		showModal: function( sessionType, campaignName ) {
			var $modal = $( '#wsscd-draft-conflict-modal' );

			if ( $modal.length ) {
				$modal.addClass( 'wsscd-modal--visible' ).fadeIn( 200 ).css( 'display', 'flex' );
				$( 'body' ).addClass( 'wsscd-modal-open' );

				// Focus first button for accessibility
				setTimeout( function() {
					$modal.find( '.wsscd-modal__actions button:first' ).focus();
				}, 250 );
			}
		}
	};

	/**
	 * Modal Action Handlers
	 *
	 * Handles clicks on modal buttons (continue, discard, cancel, close).
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

			$( document ).on( 'click', '#wsscd-draft-conflict-modal .wsscd-modal__actions button[data-action], #wsscd-draft-conflict-modal .wsscd-modal-cancel, #wsscd-draft-conflict-modal .wsscd-modal__close', function( e ) {
				e.preventDefault();
				var action = $( this ).data( 'action' );

				self.handleAction( action, $( this ) );
			} );

			// Close modal on overlay click
			$( document ).on( 'click', '#wsscd-draft-conflict-modal .wsscd-modal__overlay', function( e ) {
				e.preventDefault();
				self.closeModal();
			} );

			// ESC key to close modal
			$( document ).on( 'keydown', function( e ) {
				if ( 'Escape' === e.key && $( '#wsscd-draft-conflict-modal.wsscd-modal--visible' ).length ) {
					self.closeModal();
				}
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
				case 'continue':
					this.continueDraft( $button );
					break;

				case 'discard-new':
					this.discardDraftAndCreateNew( $button );
					break;

				case 'close':
					this.closeModal();
					break;

				default:
					// Handle cancel button and close button
					if ( $button.hasClass( 'wsscd-modal-cancel' ) || $button.hasClass( 'wsscd-modal__close' ) ) {
						this.closeModal();
					}
					break;
			}
		},

		/**
		 * Continue editing the draft campaign.
		 *
		 * @since 1.0.0
		 * @param {jQuery} $button Button that was clicked.
		 */
		continueDraft: function( $button ) {
			// Redirect to wizard with continue intent
			window.location.href = window.wsscdCampaignListL10n.adminUrl + '?page=wsscd-campaigns&action=wizard&intent=continue';
		},


		/**
		 * Discard current draft, then create new.
		 *
		 * @since 1.0.0
		 * @param {jQuery} $button Button that was clicked.
		 */
		discardDraftAndCreateNew: function( $button ) {
			// Disable button and update text
			$button.prop( 'disabled', true ).text( window.wsscdCampaignListL10n.discardingText );

			var redirectUrl = window.wsscdCampaignListL10n.adminUrl + '?page=wsscd-campaigns&action=wizard&intent=new';

			// Clear client-side storage FIRST (before AJAX, ensures clean state)
			if ( window.WSSCD && window.WSSCD.Utils && window.WSSCD.Utils.WizardSession ) {
				WSSCD.Utils.WizardSession.clearClientStorage();
			}

			// Make AJAX call to discard the server-side draft
			$.ajax( {
				url: window.ajaxurl,
				type: 'POST',
				data: {
					action: 'wsscd_ajax',
					wsscdAction: 'delete_draft',
					draftAction: 'delete',
					draftType: 'session',
					draftId: 'current',
					nonce: window.wsscdCampaignListL10n.nonce
				},
				success: function() {
					window.location.href = redirectUrl;
				},
				error: function() {
					// Still redirect even on error - client storage already cleared
					window.location.href = redirectUrl;
				}
			} );
		},

		/**
		 * Close the modal.
		 *
		 * @since 1.0.0
		 */
		closeModal: function() {
			var $modal = $( '#wsscd-draft-conflict-modal' );

			if ( $modal.length ) {
				$modal.removeClass( 'wsscd-modal--visible' ).fadeOut( 200 );
				$( 'body' ).removeClass( 'wsscd-modal-open' );
			}
		}
	};

	/**
	 * Initialize on document ready.
	 *
	 * @since 1.0.0
	 */
	$( document ).ready( function() {
		SessionConflictModal.init();
		ModalActions.init();
	} );

} )( jQuery );
