/**
 * Campaign Planner Interactions
 *
 * Handles card focus switching, insights loading, and collapsible sections.
 *
 * @package SmartCycleDiscounts
 * @since   1.0.0
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
	var TimelineInteractions = {

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
			$( document ).on( 'click', '.scd-timeline-card', this.handleCardClick.bind( this ) );

			// Keyboard support for campaign cards
			$( document ).on( 'keydown', '.scd-timeline-card', this.handleCardKeydown.bind( this ) );

			// Collapsible section toggles
			$( document ).on( 'click', '.scd-insights-section-toggle', this.handleToggleClick.bind( this ) );

			// Keyboard support for toggles
			$( document ).on( 'keydown', '.scd-insights-section-toggle', this.handleToggleKeydown.bind( this ) );

			// Create campaign CTA
			$( document ).on( 'click', '.scd-timeline-create-cta', this.handleCreateCampaign.bind( this ) );

			// Tab switching (alternative to card click)
			$( document ).on( 'click', '.scd-insights-tab', this.handleTabClick.bind( this ) );
		},

		/**
		 * Initialize default focus on active campaign
		 */
		initializeDefaultFocus: function() {
			var $activeCard = $( '.scd-timeline-card[data-state="active"]' );
			if ( $activeCard.length ) {
				$activeCard.addClass( 'scd-timeline-card--focused' );
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
			var isMajorEvent = $card.data( 'is-major-event' );

			// Don't reload if already focused
			if ( $card.hasClass( 'scd-timeline-card--focused' ) ) {
				return;
			}

			// Update visual focus
			$( '.scd-timeline-card' ).removeClass( 'scd-timeline-card--focused' );
			$card.addClass( 'scd-timeline-card--focused' );

			// Load insights for this campaign
			this.loadInsights( campaignId, campaignState, isMajorEvent );
		},

		/**
		 * Handle content-type tab click (Why/How/When switching)
		 *
		 * @param {Event} e Click event
		 */
		handleTabClick: function( e ) {
			e.preventDefault();

			var $tab = $( e.currentTarget );
			var tabId = $tab.data( 'tab-id' );

			// Update active tab
			$( '.scd-insights-tab' ).removeClass( 'scd-insights-tab--active' ).attr( 'aria-selected', 'false' );
			$tab.addClass( 'scd-insights-tab--active' ).attr( 'aria-selected', 'true' );

			// Switch tab panels
			$( '.scd-insights-tab-panel' ).removeClass( 'scd-insights-tab-panel--active' ).hide();
			$( '#scd-tab-panel-' + tabId ).addClass( 'scd-insights-tab-panel--active' ).fadeIn( 200 );
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
		 * @param {string}  campaignId     Campaign ID
		 * @param {string}  campaignState  Campaign state (past/active/future)
		 * @param {boolean} isMajorEvent   Is this a major event
		 */
		loadInsights: function( campaignId, campaignState, isMajorEvent ) {
			var $insightsContent = $( '.scd-insights-content' );

			// Abort previous request if still pending
			if ( pendingRequest ) {
				pendingRequest.abort();
			}

			// Show loading state
			$insightsContent.addClass( 'scd-insights-loading' );

			// Check if scdAdmin is available (localized data)
			if ( typeof scdAdmin === 'undefined' || ! scdAdmin.ajaxUrl || ! scdAdmin.nonce ) {
				console.error( 'scdAdmin data not properly configured' );
				$insightsContent.removeClass( 'scd-insights-loading' );

				// Show error message to user
				if ( window.SCD && window.SCD.Shared && window.SCD.Shared.NotificationService ) {
					SCD.Shared.NotificationService.error( 'Configuration error. Please refresh the page.' );
				}
				return;
			}

			pendingRequest = $.ajax( {
				url: scdAdmin.ajaxUrl,
				method: 'POST',
				data: {
					action: 'scd_get_timeline_insights',
					campaign_id: campaignId,
					state: campaignState,
					is_major_event: isMajorEvent ? '1' : '0',
					nonce: scdAdmin.nonce
				},
				success: function( response ) {
					pendingRequest = null;
					if ( response.success && response.data.html ) {
						// Smooth transition
						$insightsContent.fadeOut( 200, function() {
							$( this )
								.html( response.data.html )
								.removeClass( 'scd-insights-loading' )
								.fadeIn( 200 );
						} );
					} else {
						$insightsContent.removeClass( 'scd-insights-loading' );
						console.error( 'Failed to load insights:', response );

						// Show error message to user
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

					// Show error message to user
					if ( window.SCD && window.SCD.Shared && window.SCD.Shared.NotificationService ) {
						SCD.Shared.NotificationService.error( 'Network error loading insights. Please check your connection.' );
					}
				}
			} );
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
			var $icon = $toggle.find( '.dashicons' ).first();

			if ( $content.is( ':visible' ) ) {
				// Collapse
				$content.slideUp( 300 );
				$section.removeClass( 'scd-insights-section--open' );
				$icon.removeClass( 'dashicons-arrow-down' ).addClass( 'dashicons-arrow-right' );
				$toggle.attr( 'aria-expanded', 'false' );
			} else {
				// Expand
				$content.slideDown( 300 );
				$section.addClass( 'scd-insights-section--open' );
				$icon.removeClass( 'dashicons-arrow-right' ).addClass( 'dashicons-arrow-down' );
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
		if ( $( '.scd-timeline-grid' ).length ) {
			TimelineInteractions.init();
		}
	} );

} )( jQuery, window, document );
