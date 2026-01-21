/**
 * Planner Interactions
 *
 * Handles Campaign Planner card interactions, insights loading via AJAX,
 * and timeline navigation on the dashboard.
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
	 * Track pending AJAX request to prevent race conditions.
	 */
	var pendingRequest = null;

	/**
	 * Track currently loaded campaign ID to prevent duplicate loads.
	 */
	var currentCampaignId = null;

	/**
	 * Campaign Planner interactions module.
	 */
	var PlannerInteractions = {

		/**
		 * Initialize the module.
		 */
		init: function() {
			this.cacheElements();
			this.bindEvents();
			this.initializeDefaultFocus();
		},

		/**
		 * Cache frequently used DOM elements.
		 */
		cacheElements: function() {
			this.$insightsBody = $( '.wsscd-insights-body' );
			this.$insightsTitle = $( '.wsscd-insights-title' );
		},

		/**
		 * Bind event handlers.
		 */
		bindEvents: function() {
			// Card click to focus.
			$( document ).on( 'click', '.wsscd-planner-card', this.handleCardClick.bind( this ) );

			// Keyboard support for campaign cards.
			$( document ).on( 'keydown', '.wsscd-planner-card', this.handleCardKeydown.bind( this ) );

			// Timeline item click (triggers card click).
			$( document ).on( 'click', '.wsscd-timeline-item', this.handleTimelineItemClick.bind( this ) );

			// Keyboard support for timeline items.
			$( document ).on( 'keydown', '.wsscd-timeline-item', this.handleTimelineItemKeydown.bind( this ) );

			// CTA button click tracking.
			$( document ).on( 'click', '.wsscd-planner-create-cta', this.handleCreateCampaign.bind( this ) );
		},

		/**
		 * Initialize default focus on Slot 2 (ACTIVE/NEXT campaign).
		 *
		 * The middle slot always contains the most relevant campaign:
		 * - If a campaign is active: shows active campaign
		 * - If no campaign active: shows next upcoming campaign
		 */
		initializeDefaultFocus: function() {
			var $cards = $( '.wsscd-planner-card' );
			var $defaultCard = $cards.eq( 1 ); // Index 1 = second card = Slot 2

			if ( ! $defaultCard.length ) {
				return;
			}

			$defaultCard.attr( 'data-focused', 'true' );

			var campaignId = $defaultCard.data( 'campaign-id' );
			var campaignPosition = $defaultCard.data( 'position' );
			this.updateTimelineFocus( campaignPosition );

			// Check if insights content already rendered by server.
			var hasContent = this.$insightsBody.find( '.wsscd-insights-columns' ).length > 0;

			if ( hasContent ) {
				// Server already rendered insights, just track the ID.
				currentCampaignId = campaignId;
			} else {
				// No insights rendered by server, load via AJAX.
				var campaignState = $defaultCard.data( 'state' );
				var isMajorEvent = 'true' === $defaultCard.attr( 'data-major-event' );

				this.loadInsights( campaignId, campaignState, isMajorEvent, campaignPosition );
			}
		},

		/**
		 * Handle card click - switch focus and load insights.
		 *
		 * @param {Event} e Click event.
		 */
		handleCardClick: function( e ) {
			// Allow buttons and links to work normally.
			var $target = $( e.target );
			if ( $target.is( 'button, a, input, select, textarea' ) || $target.closest( 'button, a' ).length ) {
				return;
			}

			e.preventDefault();

			var $card = $( e.currentTarget );

			// Don't reload if already focused.
			if ( 'true' === $card.attr( 'data-focused' ) ) {
				return;
			}

			var campaignId = $card.data( 'campaign-id' );
			var campaignState = $card.data( 'state' );
			var campaignPosition = $card.data( 'position' );
			var isMajorEvent = 'true' === $card.attr( 'data-major-event' );

			// Update focus state.
			$( '.wsscd-planner-card' ).attr( 'data-focused', 'false' );
			$card.attr( 'data-focused', 'true' );

			this.updateTimelineFocus( campaignPosition );
			this.loadInsights( campaignId, campaignState, isMajorEvent, campaignPosition );
		},

		/**
		 * Handle timeline item click - triggers corresponding card click.
		 *
		 * @param {Event} e Click event.
		 */
		handleTimelineItemClick: function( e ) {
			e.preventDefault();

			var $timelineItem = $( e.currentTarget );
			var campaignId = $timelineItem.data( 'campaign-id' );

			// Only proceed if timeline item has campaign data.
			if ( ! campaignId ) {
				return;
			}

			// Find and trigger click on corresponding card.
			var $correspondingCard = $( '.wsscd-planner-card[data-campaign-id="' + campaignId + '"]' );
			if ( $correspondingCard.length ) {
				$correspondingCard.trigger( 'click' );
			}
		},

		/**
		 * Handle keyboard navigation on timeline items.
		 *
		 * @param {Event} e Keydown event.
		 */
		handleTimelineItemKeydown: function( e ) {
			// Only handle Enter and Space keys.
			if ( 13 === e.which || 32 === e.which ) {
				e.preventDefault();
				$( e.currentTarget ).trigger( 'click' );
			}
		},

		/**
		 * Handle keyboard navigation on campaign cards.
		 *
		 * @param {Event} e Keydown event.
		 */
		handleCardKeydown: function( e ) {
			// Only handle Enter and Space keys.
			if ( 13 === e.which || 32 === e.which ) {
				e.preventDefault();
				$( e.currentTarget ).trigger( 'click' );
			}
		},

		/**
		 * Scroll to insights section smoothly.
		 */
		scrollToInsights: function() {
			var $insights = $( '.wsscd-planner-insights' );
			if ( $insights.length ) {
				$( 'html, body' ).animate( {
					scrollTop: $insights.offset().top - 50
				}, 300 );
			}
		},

		/**
		 * Load insights via AJAX.
		 *
		 * @param {string}  campaignId       Campaign ID.
		 * @param {string}  campaignState    Campaign state (past/active/future).
		 * @param {boolean} isMajorEvent     Is this a major event.
		 * @param {string}  campaignPosition Timeline position (past/active/future).
		 */
		loadInsights: function( campaignId, campaignState, isMajorEvent, campaignPosition ) {
			var self = this;

			// Skip if same campaign already loaded.
			if ( campaignId === currentCampaignId ) {
				this.scrollToInsights();
				return;
			}

			// Abort previous request if still pending.
			if ( pendingRequest ) {
				pendingRequest.abort();
			}

			// Scroll to insights section.
			this.scrollToInsights();

			// Show loading state.
			this.$insightsBody.addClass( 'wsscd-insights-loading' );

			// Validate configuration.
			if ( 'undefined' === typeof wsscdAdmin || ! wsscdAdmin.ajaxUrl || ! wsscdAdmin.nonce ) {
				this.$insightsBody.removeClass( 'wsscd-insights-loading' );
				this.showError( 'Configuration error. Please refresh the page.' );
				return;
			}

			pendingRequest = $.ajax( {
				url: wsscdAdmin.ajaxUrl,
				method: 'POST',
				data: {
					action: 'wsscd_get_planner_insights',
					campaignId: campaignId,
					state: campaignState,
					position: campaignPosition,
					isMajorEvent: isMajorEvent ? '1' : '0',
					nonce: wsscdAdmin.nonce
				},
				success: function( response ) {
					pendingRequest = null;

					if ( response.success && response.data ) {
						// Track loaded campaign.
						currentCampaignId = campaignId;

						// Update header from AJAX response (includes emoji).
						if ( response.data.title ) {
							self.updateHeader( response.data.title );
						}

						// Replace body content.
						if ( response.data.html ) {
							self.$insightsBody
								.html( response.data.html )
								.removeClass( 'wsscd-insights-loading' );
						} else {
							self.$insightsBody.removeClass( 'wsscd-insights-loading' );
						}
					} else {
						self.$insightsBody.removeClass( 'wsscd-insights-loading' );
						self.showError( 'Failed to load campaign insights. Please try again.' );
					}
				},
				error: function( xhr, status ) {
					pendingRequest = null;
					self.$insightsBody.removeClass( 'wsscd-insights-loading' );

					// Don't show error for aborted requests.
					if ( 'abort' !== status ) {
						self.showError( 'Network error loading insights. Please check your connection.' );
					}
				}
			} );
		},

		/**
		 * Update the persistent header with new campaign info.
		 *
		 * @param {string} title Campaign title.
		 */
		updateHeader: function( title ) {
			if ( this.$insightsTitle.length && title ) {
				this.$insightsTitle.text( title );
			}
		},

		/**
		 * Update timeline item focus to match card selection.
		 *
		 * @param {string} position Timeline position (past/active/future).
		 */
		updateTimelineFocus: function( position ) {
			$( '.wsscd-timeline-item' ).removeClass( 'wsscd-timeline-item--focused' );
			$( '.wsscd-timeline-item--' + position ).addClass( 'wsscd-timeline-item--focused' );
		},

		/**
		 * Show error notification.
		 *
		 * @param {string} message Error message.
		 */
		showError: function( message ) {
			if ( window.WSSCD && window.WSSCD.Shared && window.WSSCD.Shared.NotificationService ) {
				WSSCD.Shared.NotificationService.error( message );
			}
		},

		/**
		 * Handle create campaign CTA click.
		 *
		 * @param {Event} e Click event.
		 */
		handleCreateCampaign: function( e ) {
			// Let default link behavior work.
			// Could add analytics tracking here if needed.
		}
	};

	/**
	 * Initialize on document ready.
	 */
	$( document ).ready( function() {
		// Only initialize if Campaign Planner exists on page.
		if ( $( '.wsscd-planner-grid' ).length ) {
			PlannerInteractions.init();
		}
	} );

} )( jQuery, window, document );
