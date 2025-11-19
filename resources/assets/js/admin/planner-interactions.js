/**
 * Planner Interactions
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/admin/planner-interactions.js
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

( function( $, window, document ) {
	'use strict';
	/**
	 * Track pending AJAX request to prevent race conditions
	 */
	var pendingRequest = null;
	/**
	 * Campaign Planner interactions module
	 */
	var PlannerInteractions = {
		/**
		 * Initialize
		 */
		init: function() {
			this.bindEvents();
			this.initializeDefaultFocus();
		},
		/**
		 * Bind event handlers
		 */
		bindEvents: function() {
			// Card click to focus
			$( document ).on( 'click', '.scd-planner-card', this.handleCardClick.bind( this ) );
			// Keyboard support for campaign cards
			$( document ).on( 'keydown', '.scd-planner-card', this.handleCardKeydown.bind( this ) );
			// Timeline item click (triggers card click)
			$( document ).on( 'click', '.scd-timeline-item', this.handleTimelineItemClick.bind( this ) );
			// Keyboard support for timeline items
			$( document ).on( 'keydown', '.scd-timeline-item', this.handleTimelineItemKeydown.bind( this ) );
			// Collapsible section toggles
			$( document ).on( 'click', '.scd-insights-section-toggle', this.handleToggleClick.bind( this ) );
			// Keyboard support for toggles
			$( document ).on( 'keydown', '.scd-insights-section-toggle', this.handleToggleKeydown.bind( this ) );
			$( document ).on( 'click', '.scd-planner-create-cta', this.handleCreateCampaign.bind( this ) );
		},
		/**
		 * Initialize default focus on Slot 2 (ACTIVE/NEXT campaign)
		 *
		 * The middle slot always contains the most relevant campaign:
		 * - If a campaign is active: shows active campaign
		 * - If no campaign active: shows next upcoming campaign
		 */
		initializeDefaultFocus: function() {
			var $cards = $( '.scd-planner-card' );
			var $defaultCard = $cards.eq( 1 ); // Index 1 = second card = Slot 2

			if ( $defaultCard.length ) {
				$defaultCard.attr( 'data-focused', 'true' );
				var campaignPosition = $defaultCard.data( 'position' );
				this.updateTimelineFocus( campaignPosition );

				// Server already renders insights for default campaign, no need to reload
			}
		},
		/**
		 * Handle card click - switch focus and load insights
		 *
		 * @param {Event} e Click event
		 */
		handleCardClick: function( e ) {
			// Allow buttons and links to work normally
			var $target = $( e.target );
			if ( $target.is( 'button, a, input, select, textarea' ) || $target.closest( 'button, a' ).length ) {
				return;
			}
			e.preventDefault();
			var $card = $( e.currentTarget );
			var campaignId = $card.data( 'campaign-id' );
			var campaignState = $card.data( 'state' );
			var campaignPosition = $card.data( 'position' );
			var isMajorEvent = $card.attr( 'data-major-event' ) === 'true';
			// Don't reload if already focused
			if ( $card.attr( 'data-focused' ) === 'true' ) {
				return;
			}
			$( '.scd-planner-card' ).attr( 'data-focused', 'false' );
			$card.attr( 'data-focused', 'true' );
			this.updateTimelineFocus( campaignPosition );
			this.loadInsights( campaignId, campaignState, isMajorEvent, campaignPosition );
		},
		/**
		 * Handle timeline item click - triggers corresponding card click
		 *
		 * @param {Event} e Click event
		 */
		handleTimelineItemClick: function( e ) {
			e.preventDefault();
			var $timelineItem = $( e.currentTarget );
			var campaignId = $timelineItem.data( 'campaign-id' );
			var campaignState = $timelineItem.data( 'state' );
			// Only proceed if timeline item has campaign data
			if ( ! campaignId || ! campaignState ) {
				return;
			}
			// Find and trigger click on corresponding card
			var $correspondingCard = $( '.scd-planner-card[data-campaign-id="' + campaignId + '"]' );
			if ( $correspondingCard.length ) {
				$correspondingCard.trigger( 'click' );
			}
		},
		/**
		 * Handle keyboard navigation on timeline items
		 *
		 * @param {Event} e Keydown event
		 */
		handleTimelineItemKeydown: function( e ) {
			// Only handle Enter and Space keys
			if ( 13 === e.which || 32 === e.which ) {
				e.preventDefault();
				$( e.currentTarget ).trigger( 'click' );
			}
		},
		/**
		 * Handle keyboard navigation on campaign cards
		 *
		 * @param {Event} e Keydown event
		 */
		handleCardKeydown: function( e ) {
			// Only handle Enter and Space keys
			if ( 13 === e.which || 32 === e.which ) {
				e.preventDefault();
				$( e.currentTarget ).trigger( 'click' );
			}
		},
		/**
		 * Handle keyboard navigation on collapsible toggles
		 *
		 * @param {Event} e Keydown event
		 */
		handleToggleKeydown: function( e ) {
			// Only handle Enter and Space keys
			if ( 13 === e.which || 32 === e.which ) {
				e.preventDefault();
				$( e.currentTarget ).trigger( 'click' );
			}
		},
		/**
		 * Load insights via AJAX
		 *
		 * @param {string}  campaignId       Campaign ID
		 * @param {string}  campaignState    Campaign state (past/active/future)
		 * @param {boolean} isMajorEvent     Is this a major event
		 * @param {string}  campaignPosition Timeline position (past/active/future)
		 */
		loadInsights: function( campaignId, campaignState, isMajorEvent, campaignPosition ) {
			var $insightsContent = $( '.scd-insights-content' );

			// Abort previous request if still pending
			if ( pendingRequest ) {
				pendingRequest.abort();
			}
			$insightsContent.addClass( 'scd-insights-loading' );
			if ( typeof scdAdmin === 'undefined' || ! scdAdmin.ajaxUrl || ! scdAdmin.nonce ) {
				console.error( 'scdAdmin data not properly configured' );
				$insightsContent.removeClass( 'scd-insights-loading' );
				if ( window.SCD && window.SCD.Shared && window.SCD.Shared.NotificationService ) {
					SCD.Shared.NotificationService.error( 'Configuration error. Please refresh the page.' );
				}
				return;
			}
			pendingRequest = $.ajax( {
				url: scdAdmin.ajaxUrl,
				method: 'POST',
				data: {
					action: 'scd_get_planner_insights',
					campaignId: campaignId,
					state: campaignState,
					position: campaignPosition,
					isMajorEvent: isMajorEvent ? '1' : '0',
					nonce: scdAdmin.nonce
				},
				success: function( response ) {
					pendingRequest = null;
					if ( response.success && response.data.html ) {
						// Replace content directly without fade animation
						$insightsContent
							.html( response.data.html )
							.removeClass( 'scd-insights-loading' );
					} else {
						$insightsContent.removeClass( 'scd-insights-loading' );
						console.error( '[SCD Planner] Failed to load insights:', response );
						if ( window.SCD && window.SCD.Shared && window.SCD.Shared.NotificationService ) {
							var errorMsg = 'Failed to load campaign insights. Please try again.';
							if ( response.error && response.error[0] && response.error[0].message ) {
								errorMsg = response.error[0].message;
							}
							SCD.Shared.NotificationService.error( errorMsg );
						}
					}
				},
				error: function( xhr, status, error ) {
					pendingRequest = null;
					$insightsContent.removeClass( 'scd-insights-loading' );
					console.error( 'AJAX error loading insights:', error );
					if ( window.SCD && window.SCD.Shared && window.SCD.Shared.NotificationService ) {
						SCD.Shared.NotificationService.error( 'Network error loading insights. Please check your connection.' );
					}
				}
			} );
		},
		/**
		 * Update timeline item focus to match card selection
		 *
		 * @param {string} position Timeline position (past/active/future)
		 */
		updateTimelineFocus: function( position ) {
			$( '.scd-timeline-item' ).removeClass( 'scd-timeline-item--focused' );
			$( '.scd-timeline-item--' + position ).addClass( 'scd-timeline-item--focused' );
		},
		/**
		 * Handle collapsible section toggle
		 *
		 * @param {Event} e Click event
		 */
		handleToggleClick: function( e ) {
			e.preventDefault();
			var $toggle = $( e.currentTarget );
			var $section = $toggle.closest( '.scd-insights-section' );
			var $content = $section.find( '.scd-insights-section-content' );
			var $icon = $toggle.find( '.scd-icon' ).first();
			if ( $content.is( ':visible' ) ) {
				// Collapse
				$content.slideUp( 300 );
				$section.removeClass( 'scd-insights-section--open' );
				$icon.css( 'transform', 'rotate(-90deg)' );
				$toggle.attr( 'aria-expanded', 'false' );
			} else {
				// Expand
				$content.slideDown( 300 );
				$section.addClass( 'scd-insights-section--open' );
				$icon.css( 'transform', 'rotate(0deg)' );
				$toggle.attr( 'aria-expanded', 'true' );
			}
		},
		/**
		 * Handle create campaign CTA click
		 *
		 * @param {Event} e Click event
		 */
		handleCreateCampaign: function( e ) {
			// Let default link behavior work
			// Could add analytics tracking here if needed
		}
	};
	/**
	 * Initialize on document ready
	 */
	$( document ).ready( function() {
		// Only initialize if Campaign Planner exists on page
		if ( $( '.scd-planner-grid' ).length ) {
			PlannerInteractions.init();
		}
	} );
} )( jQuery, window, document );
