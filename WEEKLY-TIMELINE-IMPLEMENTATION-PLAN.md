# Weekly Campaign Timeline - Complete Implementation Plan

## 📋 Executive Summary

Transform campaign suggestions into a **Dynamic Campaign Timeline** showing 3 time-sequenced campaigns:
- **LEFT (PAST)**: Most relevant recently-ended campaign (major event OR weekly)
- **MIDDLE (ACTIVE)**: Most relevant active campaign (major event OR weekly)
- **RIGHT (FUTURE)**: Most relevant upcoming campaign (major event OR weekly)

The timeline **intelligently mixes** major events (Black Friday, Christmas, etc.) with weekly campaigns (Monday boost, Wednesday wins, Weekend flash), showing whichever is most relevant for each position.

**Key Innovation**: The timeline dynamically selects the best campaign for each position based on priority rules, creating a seamless flow from past major events → weekly campaigns → future major events.

---

## 🎯 Core Features

1. **Always 3 Campaigns Visible**: Never an empty state
2. **Dynamic Campaign Selection**: Intelligently mixes major events + weekly campaigns
3. **Priority-Based Positioning**: Major events take precedence, weekly campaigns fill gaps
4. **Smart State Detection**: Automatically determines past/active/future for each campaign
5. **Click-to-Focus**: Click any card to see its specific insights
6. **Unified Insights Section**: Bottom area shows contextual content based on selection
7. **Historical Learning**: See past major event performance or missed opportunities
8. **Mobile Responsive**: Works on all screen sizes

---

## 🎨 Visual Examples

### Normal Week (No Major Events Active)
```
┌─────────────┬─────────────┬─────────────┐
│  PAST       │  ACTIVE     │  FUTURE     │
│  Mon-Tue    │  Wed-Fri    │  Weekend    │
│  (weekly)   │  (weekly)   │  (weekly)   │
│  ENDED 2d   │  ACTIVE NOW │  STARTS 3d  │
└─────────────┴─────────────┴─────────────┘
```

### Black Friday Just Ended
```
┌─────────────┬─────────────┬─────────────┐
│  PAST       │  ACTIVE     │  FUTURE     │
│ Black Friday│  Wed-Fri    │  Christmas  │
│  (major) 🛍️│  (weekly)   │  (major) 🎄 │
│  ENDED 3d   │  ACTIVE NOW │  STARTS 20d │
└─────────────┴─────────────┴─────────────┘
```

### Cyber Monday Active
```
┌─────────────┬─────────────┬─────────────┐
│  PAST       │  ACTIVE     │  FUTURE     │
│ Black Friday│ Cyber Monday│  Christmas  │
│  (major) 🛍️│  (major) 💻 │  (major) 🎄 │
│  ENDED 3d   │  ACTIVE NOW │  STARTS 20d │
└─────────────┴─────────────┴─────────────┘
```

### Between Major Events
```
┌─────────────┬─────────────┬─────────────┐
│  PAST       │  ACTIVE     │  FUTURE     │
│ Cyber Monday│  Wed-Fri    │  Christmas  │
│  (major) 💻 │  (weekly)   │  (major) 🎄 │
│  ENDED 10d  │  ACTIVE NOW │  STARTS 15d │
└─────────────┴─────────────┴─────────────┘
```

---

## 🏗️ Architecture Overview

**Service-Based Architecture** (follows Single Responsibility Principle)

```
┌─────────────────────────────────────────────────────────────┐
│         Campaign Timeline Service (NEW)                     │
│  ┌────────────────────────────────────────────────────────┐ │
│  │ get_weekly_timeline_campaigns()                        │ │
│  │  ├─ Get all opportunities (weekly+major)               │ │
│  │  ├─ Calculate state for each campaign                  │ │
│  │  ├─ Select best PAST campaign                          │ │
│  │  ├─ Select best ACTIVE campaign                        │ │
│  │  └─ Select best FUTURE campaign                        │ │
│  └────────────────────────────────────────────────────────┘ │
│  ┌────────────────────────────────────────────────────────┐ │
│  │ Priority Rules for Each Position:                      │ │
│  │ PAST:   major > weekly, recent > old                   │ │
│  │ ACTIVE: major > weekly, in_window > not                │ │
│  │ FUTURE: major > weekly, soon > far                     │ │
│  └────────────────────────────────────────────────────────┘ │
│                                                             │
│  Dependencies:                                              │
│  • Campaign Repository (fetch existing campaigns)           │
│  • Campaign Suggestions Service (get major events)          │
│  • Logger (debug logging)                                   │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│           Dashboard Service (Orchestrator)                  │
│  ┌────────────────────────────────────────────────────────┐ │
│  │ get_weekly_timeline_campaigns()                        │ │
│  │  └─ Delegates to → Campaign Timeline Service           │ │
│  └────────────────────────────────────────────────────────┘ │
│                                                             │
│  Orchestrates between:                                      │
│  • Campaign Timeline Service (timeline display)             │
│  • Campaign Suggestions Service (major events)              │
│  • Campaign Display Service (display preparation)           │
│  • Campaign Health Service (health monitoring)              │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│          View Template (main-dashboard.php)                 │
│  ┌─────────┬─────────┬─────────┐                           │
│  │  PAST   │ ACTIVE  │ FUTURE  │                           │
│  │ (major/ │ (major/ │ (major/ │                           │
│  │ weekly) │ weekly) │ weekly) │                           │
│  └─────────┴─────────┴─────────┘                           │
│  ┌────────────────────────────────────────────────────────┐ │
│  │  Unified Insights Section (Bottom)                     │ │
│  │  - Content adapts to campaign type                     │ │
│  │  - Major events: industry data                         │ │
│  │  - Weekly: quick setup tips                            │ │
│  └────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────┘
```

**Architecture Benefits:**
- ✅ Single Responsibility: Timeline Service focuses only on timeline logic
- ✅ Testability: Timeline logic can be unit tested in isolation
- ✅ Maintainability: Changes to timeline don't affect other dashboard features
- ✅ Consistency: Follows same pattern as Campaign Suggestions/Display Services
- ✅ Reusability: Timeline Service can be used outside dashboard if needed

---

## 📊 Dynamic Selection Logic

### Priority Rules

**PAST Position:**
1. Most recent ended **major event** (if within 30 days)
2. Most recent ended **weekly campaign** (if no major event)
3. Skip position if nothing ended recently

**ACTIVE Position:**
1. Current **major event** (if in creation window)
2. Current **weekly campaign** (if no major event)
3. Next upcoming campaign if nothing currently active (edge case)

**FUTURE Position:**
1. Next upcoming **major event** (if within 60 days)
2. Next **weekly campaign** (if no major event soon)
3. Major event further out (if no weekly campaigns apply)

### Why These Rules Work

- **Major events are rare but high-value** → They take priority when available
- **Weekly campaigns fill gaps** → Consistent rhythm when no major events
- **Recency matters for PAST** → More relevant to learn from recent campaigns
- **Proximity matters for FUTURE** → More urgent to prepare for soon campaigns
- **Creation window matters for ACTIVE** → Only show major events when timing is optimal

---

## 📅 Campaign Definitions

### Weekly Campaigns (Recurring)

#### Campaign 1: Fresh Start Monday
- **ID**: `weekly_early_boost`
- **Schedule**: Monday 00:00 - Tuesday 23:59
- **Discount**: 10-15% (optimal: 12%)
- **Psychology**: New week optimism, goal-oriented purchases
- **Best For**: New launches, self-improvement items, business products
- **Prep Time**: 0 days (can create day-of)
- **Priority**: Medium (overridden by major events)

#### Campaign 2: Wednesday Wins
- **ID**: `weekly_midweek_push`
- **Schedule**: Wednesday 00:00 - Friday 17:00
- **Discount**: 12-18% (optimal: 15%)
- **Psychology**: "Treat yourself" mid-week reward mentality
- **Best For**: Impulse purchases, small luxuries, pick-me-ups
- **Prep Time**: 0 days (can create day-of)
- **Peak Traffic**: Wed 12PM-2PM (lunch browsing)
- **Priority**: Medium (overridden by major events)

#### Campaign 3: Weekend Flash Sale
- **ID**: `weekly_weekend_flash`
- **Schedule**: Friday 18:00 - Sunday 23:59
- **Discount**: 15-25% (optimal: 20%)
- **Psychology**: Free time + browsing mindset
- **Best For**: Bigger items, family products, entertainment
- **Prep Time**: 1 day (prepare Thursday/Friday)
- **Peak Traffic**: Saturday 10AM-2PM
- **Priority**: Medium (overridden by major events)

### Major Events (From Registry)

Major events come from existing `SCD_Campaign_Suggestions_Registry`:
- Black Friday, Cyber Monday, Christmas, Valentine's Day, etc.
- **Priority**: High (override weekly campaigns when in creation window)
- **Display**: Show in timeline based on state (past/active/future)

---

## 📂 Files to Create

### 1. New PHP Class: Weekly Campaign Definitions
**File**: `includes/core/campaigns/class-weekly-campaign-definitions.php`

```php
<?php
/**
 * Weekly Campaign Definitions
 *
 * Defines recurring weekly campaign templates that appear every week.
 * Separated from seasonal events for clarity.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/campaigns
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Weekly Campaign Definitions Class
 *
 * @since 1.0.0
 */
class SCD_Weekly_Campaign_Definitions {

	/**
	 * Get all weekly campaign definitions.
	 *
	 * @since  1.0.0
	 * @return array Array of weekly campaign definitions.
	 */
	public static function get_definitions(): array {
		return array(
			// Campaign 1: Early Week
			array(
				'id'                 => 'weekly_early_boost',
				'name'               => __( 'Fresh Start Monday', 'smart-cycle-discounts' ),
				'icon'               => '🌅',
				'category'           => 'recurring_weekly',
				'position'           => 'first',
				'priority'           => 10, // Medium priority (major events = 100)
				'is_major_event'     => false,
				'schedule'           => array(
					'start_day'  => 1, // Monday (1-7, where 1=Monday)
					'start_time' => '00:00',
					'end_day'    => 2, // Tuesday
					'end_time'   => '23:59',
				),
				'suggested_discount' => array(
					'min'     => 10,
					'max'     => 15,
					'optimal' => 12,
				),
				'description'        => __( 'Start the week strong - capture motivated Monday shoppers', 'smart-cycle-discounts' ),
				'psychology'         => __( 'New week optimism - goal-oriented purchases', 'smart-cycle-discounts' ),
				'best_for'           => array(
					__( 'New product launches', 'smart-cycle-discounts' ),
					__( 'Self-improvement items', 'smart-cycle-discounts' ),
					__( 'Business/professional products', 'smart-cycle-discounts' ),
				),
				'statistics'         => array(
					'conversion_lift' => __( '18% higher conversion vs. other weekdays', 'smart-cycle-discounts' ),
					'peak_time'       => __( 'Monday 9-11AM (start-of-week planning)', 'smart-cycle-discounts' ),
					'avg_order'       => __( '$75 average order value', 'smart-cycle-discounts' ),
				),
				'recommendations'    => array(
					__( 'Feature "New This Week" product collections', 'smart-cycle-discounts' ),
					__( 'Target goal-oriented messaging ("Start Strong")', 'smart-cycle-discounts' ),
					__( 'Launch new products on Monday for maximum visibility', 'smart-cycle-discounts' ),
					__( 'Send campaign email Monday 8AM', 'smart-cycle-discounts' ),
				),
				'prep_time'          => 0, // Can create day-of
			),

			// Campaign 2: Mid-Week
			array(
				'id'                 => 'weekly_midweek_push',
				'name'               => __( 'Wednesday Wins', 'smart-cycle-discounts' ),
				'icon'               => '⚡',
				'category'           => 'recurring_weekly',
				'position'           => 'middle',
				'priority'           => 10,
				'is_major_event'     => false,
				'schedule'           => array(
					'start_day'  => 3, // Wednesday
					'start_time' => '00:00',
					'end_day'    => 5, // Friday
					'end_time'   => '17:00', // Ends before weekend campaign
				),
				'suggested_discount' => array(
					'min'     => 12,
					'max'     => 18,
					'optimal' => 15,
				),
				'description'        => __( 'Beat the hump day - mid-week rewards', 'smart-cycle-discounts' ),
				'psychology'         => __( '"Treat yourself" mentality - deserve a reward', 'smart-cycle-discounts' ),
				'best_for'           => array(
					__( 'Impulse purchases', 'smart-cycle-discounts' ),
					__( 'Small luxuries', 'smart-cycle-discounts' ),
					__( 'Pick-me-up items', 'smart-cycle-discounts' ),
				),
				'statistics'         => array(
					'conversion_lift' => __( '23% higher conversion than Mon-Tue', 'smart-cycle-discounts' ),
					'peak_time'       => __( 'Wednesday 12-2PM (lunch browsing)', 'smart-cycle-discounts' ),
					'impulse_rate'    => __( '35% of purchases are impulse buys', 'smart-cycle-discounts' ),
				),
				'recommendations'    => array(
					__( 'Feature affordable "treat yourself" items ($20-50)', 'smart-cycle-discounts' ),
					__( 'Use mid-week reward messaging', 'smart-cycle-discounts' ),
					__( 'Post to social media Wednesday morning', 'smart-cycle-discounts' ),
					__( 'Highlight fast shipping for instant gratification', 'smart-cycle-discounts' ),
				),
				'prep_time'          => 0,
			),

			// Campaign 3: Weekend
			array(
				'id'                 => 'weekly_weekend_flash',
				'name'               => __( 'Weekend Flash Sale', 'smart-cycle-discounts' ),
				'icon'               => '🎉',
				'category'           => 'recurring_weekly',
				'position'           => 'last',
				'priority'           => 10,
				'is_major_event'     => false,
				'schedule'           => array(
					'start_day'  => 5, // Friday
					'start_time' => '18:00', // Evening start
					'end_day'    => 7, // Sunday
					'end_time'   => '23:59',
				),
				'suggested_discount' => array(
					'min'     => 15,
					'max'     => 25,
					'optimal' => 20,
				),
				'description'        => __( 'Weekend shoppers ready to spend - highest traffic days', 'smart-cycle-discounts' ),
				'psychology'         => __( 'Free time + browsing mindset = higher conversions', 'smart-cycle-discounts' ),
				'best_for'           => array(
					__( 'Bigger ticket items (more time to decide)', 'smart-cycle-discounts' ),
					__( 'Family/household products', 'smart-cycle-discounts' ),
					__( 'Entertainment/leisure items', 'smart-cycle-discounts' ),
				),
				'statistics'         => array(
					'traffic_lift'    => __( '40% more traffic than weekdays', 'smart-cycle-discounts' ),
					'session_length'  => __( '2.3x longer browsing sessions', 'smart-cycle-discounts' ),
					'peak_time'       => __( 'Saturday 10AM-2PM', 'smart-cycle-discounts' ),
					'mobile_share'    => __( '62% of purchases from mobile', 'smart-cycle-discounts' ),
				),
				'recommendations'    => array(
					__( 'Feature family bundles and household items', 'smart-cycle-discounts' ),
					__( 'Ensure mobile checkout is optimized', 'smart-cycle-discounts' ),
					__( 'Add countdown timer "Ends Sunday 11:59PM"', 'smart-cycle-discounts' ),
					__( 'Stock 1.5x normal inventory levels', 'smart-cycle-discounts' ),
					__( 'Promote Thursday evening on social media', 'smart-cycle-discounts' ),
				),
				'prep_time'          => 1, // Prepare Thursday/Friday
			),
		);
	}

	/**
	 * Get single weekly campaign by ID.
	 *
	 * @since  1.0.0
	 * @param  string $campaign_id Campaign ID.
	 * @return array|null Campaign definition or null if not found.
	 */
	public static function get_by_id( string $campaign_id ): ?array {
		$campaigns = self::get_definitions();

		foreach ( $campaigns as $campaign ) {
			if ( $campaign['id'] === $campaign_id ) {
				return $campaign;
			}
		}

		return null;
	}
}
```

### 2. New Service: Campaign Timeline Service
**File**: `includes/services/class-campaign-timeline-service.php`

This service handles all weekly timeline logic, following the same architectural pattern as `Campaign_Suggestions_Service` and `Campaign_Display_Service`.

```php
<?php
/**
 * Campaign Timeline Service
 *
 * Handles weekly campaign timeline logic including:
 * - Combining weekly campaigns with major events
 * - Calculating campaign states (past/active/future)
 * - Selecting best campaign for each timeline position
 * - Applying priority-based selection rules
 *
 * Follows Single Responsibility Principle - focuses only on timeline logic.
 *
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/services
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Campaign Timeline Service Class
 *
 * Responsible for generating and managing the weekly campaign timeline
 * with intelligent mixing of major events and weekly campaigns.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/services
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Campaign_Timeline_Service {

	/**
	 * Campaign repository instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Campaign_Repository    $campaign_repository    Campaign repository.
	 */
	private SCD_Campaign_Repository $campaign_repository;

	/**
	 * Campaign suggestions service instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Campaign_Suggestions_Service    $suggestions_service    Suggestions service.
	 */
	private SCD_Campaign_Suggestions_Service $suggestions_service;

	/**
	 * Logger instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Logger    $logger    Logger instance.
	 */
	private SCD_Logger $logger;

	/**
	 * Initialize the timeline service.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign_Repository           $campaign_repository    Campaign repository.
	 * @param    SCD_Campaign_Suggestions_Service  $suggestions_service    Suggestions service.
	 * @param    SCD_Logger                        $logger                 Logger instance.
	 */
	public function __construct(
		SCD_Campaign_Repository $campaign_repository,
		SCD_Campaign_Suggestions_Service $suggestions_service,
		SCD_Logger $logger
	) {
		$this->campaign_repository = $campaign_repository;
		$this->suggestions_service = $suggestions_service;
		$this->logger              = $logger;
	}

	/**
	 * Get weekly timeline campaigns with dynamic selection.
	 *
	 * Intelligently mixes major events and weekly campaigns based on priority.
	 * Each position (past/active/future) shows the most relevant campaign.
	 *
	 * @since  1.0.0
	 * @return array Timeline data with 3 selected campaigns.
	 */
	public function get_weekly_timeline_campaigns(): array {
		// Implementation will include all the timeline logic
		// that was originally in the plan for Dashboard Service

		// See full implementation in the plan below (lines 584-850)
	}

	/**
	 * Get all campaign opportunities (weekly + major events).
	 *
	 * @since  1.0.0
	 * @return array Combined array of all campaign opportunities.
	 */
	private function get_all_campaign_opportunities(): array {
		// Combines weekly campaigns with major events
	}

	/**
	 * Calculate campaign state (past/active/future).
	 *
	 * @since  1.0.0
	 * @param  array $campaign Campaign data.
	 * @return string State: 'past', 'active', or 'future'.
	 */
	private function calculate_campaign_state( array $campaign ): string {
		// State detection logic
	}

	/**
	 * Get best campaign for a specific position.
	 *
	 * @since  1.0.0
	 * @param  array  $campaigns All campaign opportunities.
	 * @param  string $position  Position: 'past', 'active', or 'future'.
	 * @return array|null Best campaign for position, or null if none.
	 */
	private function get_best_campaign_for_position( array $campaigns, string $position ): ?array {
		// Priority-based selection logic
	}

	// Additional private helper methods for timestamp calculations,
	// wizard URL generation, etc.
}
```

**Service Container Registration** (to be added in Phase 3):
```php
// In includes/bootstrap/class-service-definitions.php

'campaign_timeline_service' => array(
	'class'        => 'SCD_Campaign_Timeline_Service',
	'singleton'    => true,
	'dependencies' => array( 'campaign_repository', 'campaign_suggestions_service', 'logger' ),
	'factory'      => function ( $container ) {
		return new SCD_Campaign_Timeline_Service(
			$container->get( 'campaign_repository' ),
			$container->get( 'campaign_suggestions_service' ),
			$container->get( 'logger' )
		);
	},
),
```

### 3. New JavaScript: Timeline Interactions
**File**: `resources/assets/js/admin/timeline-interactions.js`

```javascript
/**
 * Weekly Campaign Timeline Interactions
 *
 * Handles card focus switching, insights loading, and collapsible sections.
 *
 * @package SmartCycleDiscounts
 * @since   1.0.0
 */

( function( $, window, document ) {
	'use strict';

	/**
	 * Timeline interactions module
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

			// Collapsible section toggles
			$( document ).on( 'click', '.scd-insights-section-toggle', this.handleToggleClick.bind( this ) );

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

			// Update tab indicator if exists
			$( '.scd-insights-tab' ).removeClass( 'scd-insights-tab--active' );
			$( '.scd-insights-tab[data-campaign-id="' + campaignId + '"]' ).addClass( 'scd-insights-tab--active' );

			// Load insights for this campaign
			this.loadInsights( campaignId, campaignState, isMajorEvent );
		},

		/**
		 * Handle tab click (alternative UI for card selection)
		 *
		 * @param {Event} e Click event
		 */
		handleTabClick: function( e ) {
			e.preventDefault();

			var $tab = $( e.currentTarget );
			var campaignId = $tab.data( 'campaign-id' );

			// Trigger click on corresponding card
			$( '.scd-timeline-card[data-campaign-id="' + campaignId + '"]' ).trigger( 'click' );
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

			// Show loading state
			$insightsContent.addClass( 'scd-insights-loading' );

			$.ajax( {
				url: scdData.ajaxUrl,
				method: 'POST',
				data: {
					action: 'scd_get_timeline_insights',
					campaign_id: campaignId,
					state: campaignState,
					is_major_event: isMajorEvent ? '1' : '0',
					nonce: scdData.nonce
				},
				success: function( response ) {
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
					}
				},
				error: function( xhr, status, error ) {
					$insightsContent.removeClass( 'scd-insights-loading' );
					console.error( 'AJAX error loading insights:', error );
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
			var $icon = $toggle.find( '.dashicons' );

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
		// Only initialize if timeline exists on page
		if ( $( '.scd-timeline-grid' ).length ) {
			TimelineInteractions.init();
		}
	} );

} )( jQuery, window, document );
```

---

## 📝 Files to Edit

### 1. Dashboard Service - Add Delegation to Timeline Service
**File**: `includes/services/class-dashboard-service.php`

**Update constructor to inject Timeline Service:**

```php
/**
 * Initialize the dashboard service.
 *
 * @since    1.0.0
 * @param    SCD_Analytics_Dashboard           $analytics_dashboard     Analytics dashboard.
 * @param    SCD_Campaign_Repository           $campaign_repository     Campaign repository.
 * @param    SCD_Campaign_Health_Service       $health_service          Health service.
 * @param    SCD_Feature_Gate                  $feature_gate            Feature gate.
 * @param    SCD_Logger                        $logger                  Logger instance.
 * @param    SCD_Campaign_Suggestions_Service  $suggestions_service     Suggestions service.
 * @param    SCD_Campaign_Display_Service      $display_service         Display service.
 * @param    SCD_Campaign_Timeline_Service     $timeline_service        Timeline service (NEW).
 */
public function __construct(
	SCD_Analytics_Dashboard $analytics_dashboard,
	SCD_Campaign_Repository $campaign_repository,
	SCD_Campaign_Health_Service $health_service,
	SCD_Feature_Gate $feature_gate,
	SCD_Logger $logger,
	SCD_Campaign_Suggestions_Service $suggestions_service,
	SCD_Campaign_Display_Service $display_service,
	SCD_Campaign_Timeline_Service $timeline_service  // NEW
) {
	$this->analytics_dashboard = $analytics_dashboard;
	$this->campaign_repository = $campaign_repository;
	$this->health_service      = $health_service;
	$this->feature_gate        = $feature_gate;
	$this->logger              = $logger;
	$this->suggestions_service = $suggestions_service;
	$this->display_service     = $display_service;
	$this->timeline_service    = $timeline_service;  // NEW
	$this->register_cache_hooks();
}
```

**Add property declaration:**

```php
/**
 * Campaign timeline service instance.
 *
 * @since    1.0.0
 * @access   private
 * @var      SCD_Campaign_Timeline_Service    $timeline_service    Timeline service.
 */
private SCD_Campaign_Timeline_Service $timeline_service;
```

**Add delegation method (follows same pattern as other delegations):**

```php
/**
 * Get weekly timeline campaigns with dynamic selection.
 *
 * Delegates to Campaign Timeline Service.
 * Intelligently mixes major events and weekly campaigns based on priority.
 * Each position (past/active/future) shows the most relevant campaign.
 *
 * @since  1.0.0
 * @return array Timeline data with 3 selected campaigns.
 */
public function get_weekly_timeline_campaigns(): array {
	return $this->timeline_service->get_weekly_timeline_campaigns();
}
```

**That's it!** Following the delegation pattern from Campaign Suggestions and Display services.

All the timeline logic implementation (900+ lines) goes into `SCD_Campaign_Timeline_Service` (see File #2 in "Files to Create" section above).

---

### 1b. Service Container - Register Timeline Service
**File**: `includes/bootstrap/class-service-definitions.php`

**Add Timeline Service registration (BEFORE Dashboard Service):**

```php
// Add this BEFORE 'dashboard_service' definition

'campaign_timeline_service' => array(
	'class'        => 'SCD_Campaign_Timeline_Service',
	'singleton'    => true,
	'dependencies' => array( 'campaign_repository', 'campaign_suggestions_service', 'logger' ),
	'factory'      => function ( $container ) {
		return new SCD_Campaign_Timeline_Service(
			$container->get( 'campaign_repository' ),
			$container->get( 'campaign_suggestions_service' ),
			$container->get( 'logger' )
		);
	},
),
```

**Update Dashboard Service registration to include Timeline Service:**

```php
'dashboard_service' => array(
	'class'        => 'SCD_Dashboard_Service',
	'singleton'    => true,
	'dependencies' => array(
		'analytics_dashboard',
		'campaign_repository',
		'campaign_health_service',
		'feature_gate',
		'logger',
		'campaign_suggestions_service',
		'campaign_display_service',
		'campaign_timeline_service'  // ADD THIS
	),
	'factory'      => function ( $container ) {
		return new SCD_Dashboard_Service(
			$container->get( 'analytics_dashboard' ),
			$container->get( 'campaign_repository' ),
			$container->get( 'campaign_health_service' ),
			$container->get( 'feature_gate' ),
			$container->get( 'logger' ),
			$container->get( 'campaign_suggestions_service' ),
			$container->get( 'campaign_display_service' ),
			$container->get( 'campaign_timeline_service' )  // ADD THIS
		);
	},
),
```

**Implementation Note:** The full timeline logic (combining campaigns, state detection, priority selection, etc.) that was previously planned for Dashboard Service now belongs in `class-campaign-timeline-service.php`. This keeps the code organized, testable, and consistent with the refactored architecture.

---

### 2. Dashboard Page Controller - Update get_dashboard_data Call
**File**: `includes/admin/pages/dashboard/class-main-dashboard-page.php`

**Modify the `render()` method to include timeline campaigns:**

```php
// Find this line in the render() method:
$dashboard_data = $this->dashboard_service->get_dashboard_data();

// Add this line after it:
$timeline_data = $this->dashboard_service->get_weekly_timeline_campaigns();

// Pass $timeline_data to the view
$view_data = array_merge(
	$dashboard_data,
	array(
		'timeline_data' => $timeline_data,
	)
);

// Then pass $view_data to require statement
extract( $view_data, EXTR_SKIP );
require SCD_VIEWS_DIR . 'admin/pages/dashboard/main-dashboard.php';
```

### 3. AJAX Handler - Add Insights Endpoint
**File**: `includes/admin/ajax/class-ajax-router.php`

**Add new route in `register_routes()` method:**

```php
// Add this to the routes array
$this->routes['scd_get_timeline_insights'] = array(
	'callback'   => array( $this, 'handle_timeline_insights' ),
	'capability' => 'manage_woocommerce',
);
```

**Add new handler method:**

```php
/**
 * Handle timeline insights AJAX request.
 *
 * @since  1.0.0
 * @return void
 */
public function handle_timeline_insights(): void {
	// Sanitize inputs
	$campaign_id = isset( $_POST['campaign_id'] ) ? sanitize_text_field( wp_unslash( $_POST['campaign_id'] ) ) : '';
	$state = isset( $_POST['state'] ) ? sanitize_text_field( wp_unslash( $_POST['state'] ) ) : 'active';
	$is_major_event = isset( $_POST['is_major_event'] ) && '1' === $_POST['is_major_event'];

	// Validate state
	if ( ! in_array( $state, array( 'past', 'active', 'future' ), true ) ) {
		wp_send_json_error(
			array(
				'message' => __( 'Invalid campaign state', 'smart-cycle-discounts' ),
			)
		);
		return;
	}

	// Get insights from service
	$insights_data = $this->dashboard_service->get_unified_insights( $campaign_id, $state, $is_major_event );

	if ( empty( $insights_data ) ) {
		wp_send_json_error(
			array(
				'message' => __( 'Campaign not found', 'smart-cycle-discounts' ),
			)
		);
		return;
	}

	// Render insights HTML
	ob_start();
	require SCD_VIEWS_DIR . 'admin/pages/dashboard/partials/timeline-insights.php';
	$html = ob_get_clean();

	wp_send_json_success(
		array(
			'html' => $html,
		)
	);
}
```

### 4. View Template - Replace Campaign Suggestions Section
**File**: `resources/views/admin/pages/dashboard/main-dashboard.php`

**Replace the current campaign suggestions section (lines ~99-250) with:**

```php
<?php
// 3. WEEKLY CAMPAIGN TIMELINE
$timeline_data = $timeline_data ?? array();
$campaigns = $timeline_data['campaigns'] ?? array();

if ( ! empty( $campaigns ) ) :
	?>
	<div class="scd-campaign-timeline dashboard-section">
		<div class="scd-timeline-header">
			<div class="scd-timeline-header-content">
				<div class="scd-timeline-header-icon">
					<span class="dashicons dashicons-calendar-alt"></span>
				</div>
				<div class="scd-timeline-header-text">
					<h2><?php esc_html_e( 'Your Campaign Timeline', 'smart-cycle-discounts' ); ?></h2>
					<p><?php esc_html_e( 'Smart suggestions at optimal times - weekly campaigns + major events', 'smart-cycle-discounts' ); ?></p>
				</div>
			</div>
		</div>

		<!-- 3-Card Timeline Grid -->
		<div class="scd-timeline-grid">
			<?php
			foreach ( $campaigns as $campaign ) :
				$state = $campaign['state'];
				$is_major_event = ! empty( $campaign['is_major_event'] );
				$state_class = 'scd-timeline-card--' . $state;

				if ( $is_major_event ) {
					$state_class .= ' scd-timeline-card--major';
				}

				$state_labels = array(
					'past' => __( 'Ended', 'smart-cycle-discounts' ),
					'active' => __( 'Active Now', 'smart-cycle-discounts' ),
					'future' => __( 'Coming Soon', 'smart-cycle-discounts' ),
				);
				$state_label = $state_labels[ $state ] ?? '';
				?>
				<div class="scd-timeline-card <?php echo esc_attr( $state_class ); ?>"
					 data-campaign-id="<?php echo esc_attr( $campaign['id'] ); ?>"
					 data-state="<?php echo esc_attr( $state ); ?>"
					 data-is-major-event="<?php echo $is_major_event ? '1' : '0'; ?>">

					<!-- Card Header -->
					<div class="scd-timeline-card-header">
						<div class="scd-timeline-card-title">
							<h3>
								<?php echo esc_html( $campaign['name'] ); ?>
								<span class="scd-timeline-icon"><?php echo esc_html( $campaign['icon'] ); ?></span>
							</h3>
							<?php if ( $is_major_event ) : ?>
								<span class="scd-timeline-major-badge"><?php esc_html_e( 'Major Event', 'smart-cycle-discounts' ); ?></span>
							<?php endif; ?>
						</div>
						<div class="scd-timeline-card-badge scd-badge-<?php echo esc_attr( $state ); ?>">
							<?php
							$badge_icons = array(
								'past' => 'clock',
								'active' => 'star-filled',
								'future' => 'calendar',
							);
							$badge_icon = $badge_icons[ $state ] ?? 'info';
							?>
							<span class="dashicons dashicons-<?php echo esc_attr( $badge_icon ); ?>"></span>
							<?php echo esc_html( $state_label ); ?>
						</div>
					</div>

					<!-- Card Description -->
					<div class="scd-timeline-card-content">
						<p class="scd-timeline-card-description">
							<?php echo esc_html( $campaign['description'] ); ?>
						</p>

						<?php if ( 'active' === $state ) : ?>
							<!-- Active campaign - show discount -->
							<div class="scd-timeline-card-discount">
								<span class="dashicons dashicons-tag"></span>
								<span>
									<?php
									$discount = $campaign['suggested_discount'];
									echo esc_html(
										sprintf(
											/* translators: 1: min discount, 2: max discount */
											__( 'Suggested: %1$d-%2$d%% off', 'smart-cycle-discounts' ),
											$discount['min'],
											$discount['max']
										)
									);
									?>
								</span>
							</div>
						<?php endif; ?>

						<?php if ( ! empty( $campaign['statistics'] ) && 'future' === $state ) : ?>
							<!-- Future campaign - show key stat -->
							<div class="scd-timeline-card-stat">
								<span class="dashicons dashicons-chart-line"></span>
								<span><?php echo esc_html( reset( $campaign['statistics'] ) ); ?></span>
							</div>
						<?php endif; ?>
					</div>

					<!-- Card Actions -->
					<div class="scd-timeline-card-actions">
						<?php if ( 'active' === $state ) : ?>
							<a href="<?php echo esc_url( $campaign['wizard_url'] ); ?>" class="button button-primary scd-timeline-create-cta">
								<?php echo $is_major_event ? esc_html__( '✨ Create Major Campaign', 'smart-cycle-discounts' ) : esc_html__( '⚡ Create Campaign', 'smart-cycle-discounts' ); ?>
							</a>
						<?php elseif ( 'future' === $state ) : ?>
							<button type="button" class="button button-secondary scd-timeline-view-details">
								<span class="dashicons dashicons-visibility"></span>
								<?php esc_html_e( 'View Details', 'smart-cycle-discounts' ); ?>
							</button>
						<?php else : ?>
							<button type="button" class="button button-link scd-timeline-view-insights">
								<?php esc_html_e( 'See What Happened', 'smart-cycle-discounts' ); ?>
							</button>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>

		<!-- Unified Insights Section (Bottom) -->
		<div class="scd-timeline-insights">
			<div class="scd-insights-tabs" role="tablist">
				<?php foreach ( $campaigns as $index => $campaign ) : ?>
					<button type="button"
							class="scd-insights-tab <?php echo 'active' === $campaign['state'] ? 'scd-insights-tab--active' : ''; ?>"
							data-campaign-id="<?php echo esc_attr( $campaign['id'] ); ?>"
							role="tab"
							aria-selected="<?php echo 'active' === $campaign['state'] ? 'true' : 'false'; ?>">
						<span class="dashicons dashicons-<?php echo esc_attr( 'past' === $campaign['state'] ? 'clock' : ( 'active' === $campaign['state'] ? 'star-filled' : 'calendar' ) ); ?>"></span>
						<?php echo esc_html( $campaign['name'] ); ?>
						<?php if ( ! empty( $campaign['is_major_event'] ) ) : ?>
							<span class="scd-tab-major-indicator">🎯</span>
						<?php endif; ?>
					</button>
				<?php endforeach; ?>
			</div>

			<div class="scd-insights-content" role="tabpanel">
				<?php
				// Load default insights for active campaign
				$active_campaign = null;
				foreach ( $campaigns as $campaign ) {
					if ( 'active' === $campaign['state'] ) {
						$active_campaign = $campaign;
						break;
					}
				}

				if ( $active_campaign ) {
					$insights_data = $this->dashboard_service->get_unified_insights(
						$active_campaign['id'],
						'active',
						! empty( $active_campaign['is_major_event'] )
					);
					require __DIR__ . '/partials/timeline-insights.php';
				}
				?>
			</div>
		</div>
	</div>
<?php endif; ?>
```

### 5. New View Partial - Timeline Insights Template
**File**: `resources/views/admin/pages/dashboard/partials/timeline-insights.php`

```php
<?php
/**
 * Timeline Insights Partial
 *
 * Displays collapsible insight sections for a campaign.
 * Loaded via AJAX when user clicks different timeline cards.
 *
 * @var array $insights_data Insights data from Dashboard Service
 *
 * @package SmartCycleDiscounts
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$title = $insights_data['title'] ?? '';
$icon = $insights_data['icon'] ?? 'info';
$sections = $insights_data['sections'] ?? array();
?>

<div class="scd-insights-wrapper">
	<div class="scd-insights-header">
		<span class="dashicons dashicons-<?php echo esc_attr( $icon ); ?>"></span>
		<h3><?php echo esc_html( $title ); ?></h3>
	</div>

	<div class="scd-insights-sections">
		<?php foreach ( $sections as $section ) : ?>
			<?php
			$section_class = 'scd-insights-section';
			if ( ! empty( $section['default_open'] ) ) {
				$section_class .= ' scd-insights-section--open';
			}
			?>
			<div class="<?php echo esc_attr( $section_class ); ?>">
				<button type="button"
						class="scd-insights-section-toggle"
						aria-expanded="<?php echo ! empty( $section['default_open'] ) ? 'true' : 'false'; ?>">
					<span class="dashicons dashicons-<?php echo ! empty( $section['default_open'] ) ? 'arrow-down' : 'arrow-right'; ?>"></span>
					<span class="dashicons dashicons-<?php echo esc_attr( $section['icon'] ?? 'info' ); ?>"></span>
					<span class="scd-insights-section-heading"><?php echo esc_html( $section['heading'] ); ?></span>
				</button>

				<div class="scd-insights-section-content" <?php echo empty( $section['default_open'] ) ? 'style="display:none;"' : ''; ?>>
					<?php
					$content = $section['content'] ?? array();
					foreach ( $content as $item ) :
						$item_type = $item['type'] ?? 'text';

						switch ( $item_type ) {
							case 'message':
								?>
								<div class="scd-insights-message">
									<?php if ( ! empty( $item['icon'] ) ) : ?>
										<span class="dashicons dashicons-<?php echo esc_attr( $item['icon'] ); ?>"></span>
									<?php endif; ?>
									<span><?php echo esc_html( $item['text'] ); ?></span>
								</div>
								<?php
								break;

							case 'stat':
								?>
								<div class="scd-insights-stat">
									<span class="scd-insights-stat-label"><?php echo esc_html( $item['label'] ); ?></span>
									<span class="scd-insights-stat-value"><?php echo esc_html( $item['value'] ); ?></span>
								</div>
								<?php
								break;

							case 'stat_text':
								?>
								<div class="scd-insights-stat-text">
									<?php if ( ! empty( $item['icon'] ) ) : ?>
										<span class="dashicons dashicons-<?php echo esc_attr( $item['icon'] ); ?>"></span>
									<?php endif; ?>
									<span><?php echo esc_html( $item['text'] ); ?></span>
								</div>
								<?php
								break;

							case 'tip':
								?>
								<div class="scd-insights-tip">
									<span class="dashicons dashicons-<?php echo esc_attr( $item['icon'] ?? 'yes' ); ?>"></span>
									<span><?php echo esc_html( $item['text'] ); ?></span>
								</div>
								<?php
								break;

							case 'checklist_item':
								?>
								<div class="scd-insights-checklist-item">
									<input type="checkbox" disabled <?php checked( ! empty( $item['checked'] ) ); ?>>
									<span><?php echo esc_html( $item['text'] ); ?></span>
								</div>
								<?php
								break;

							case 'cta':
								?>
								<div class="scd-insights-cta">
									<a href="<?php echo esc_url( $item['url'] ); ?>" class="button button-primary">
										<?php echo esc_html( $item['text'] ); ?> →
									</a>
								</div>
								<?php
								break;

							case 'heading':
								?>
								<h4 class="scd-insights-subheading"><?php echo esc_html( $item['text'] ); ?></h4>
								<?php
								break;

							case 'text':
								?>
								<p class="scd-insights-text"><?php echo esc_html( $item['text'] ); ?></p>
								<?php
								break;

							case 'button_link':
								?>
								<div class="scd-insights-button">
									<a href="<?php echo esc_url( $item['url'] ); ?>" class="button button-secondary">
										<?php if ( ! empty( $item['icon'] ) ) : ?>
											<span class="dashicons dashicons-<?php echo esc_attr( $item['icon'] ); ?>"></span>
										<?php endif; ?>
										<?php echo esc_html( $item['text'] ); ?>
									</a>
								</div>
								<?php
								break;

							case 'timeline_header':
								?>
								<div class="scd-insights-timeline-header">
									<span class="scd-timeline-days-badge"><?php echo esc_html( $item['days'] ); ?></span>
									<span><?php echo esc_html( $item['text'] ); ?></span>
								</div>
								<?php
								break;

							case 'timeline_section':
								?>
								<div class="scd-insights-timeline-section">
									<h5><?php echo esc_html( $item['heading'] ); ?></h5>
									<ul>
										<?php foreach ( $item['items'] as $timeline_item ) : ?>
											<li><?php echo esc_html( $timeline_item ); ?></li>
										<?php endforeach; ?>
									</ul>
								</div>
								<?php
								break;
						}
					endforeach;
					?>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
</div>
```

### 6. CSS Styles - Timeline Layout
**File**: `resources/assets/css/admin/dashboard/timeline-styles.css` (NEW FILE)

```css
/**
 * Weekly Campaign Timeline Styles
 *
 * @package SmartCycleDiscounts
 * @since   1.0.0
 */

/* ===========================
   Timeline Container
   =========================== */
.scd-campaign-timeline {
	margin-bottom: 32px;
}

.scd-timeline-header {
	margin-bottom: 24px;
}

.scd-timeline-header-content {
	display: flex;
	align-items: center;
	gap: 16px;
}

.scd-timeline-header-icon {
	flex-shrink: 0;
}

.scd-timeline-header-icon .dashicons {
	width: 48px;
	height: 48px;
	font-size: 48px;
	color: #3B82F6;
}

.scd-timeline-header-text h2 {
	margin: 0 0 4px;
	font-size: 24px;
	color: #1F2937;
}

.scd-timeline-header-text p {
	margin: 0;
	color: #6B7280;
	font-size: 14px;
}

/* ===========================
   Timeline Grid
   =========================== */
.scd-timeline-grid {
	display: grid;
	grid-template-columns: repeat(3, 1fr);
	gap: 24px;
	margin-bottom: 32px;
}

/* ===========================
   Timeline Cards
   =========================== */
.scd-timeline-card {
	background: #FFFFFF;
	border: 2px solid #E5E7EB;
	border-radius: 12px;
	padding: 24px;
	transition: all 0.3s ease;
	cursor: pointer;
	position: relative;
}

.scd-timeline-card:hover {
	transform: translateY(-4px);
	box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
}

/* Card States */
.scd-timeline-card--past {
	opacity: 0.7;
	background: linear-gradient(135deg, #F9FAFB 0%, #F3F4F6 100%);
	border-left: 4px solid #9CA3AF;
}

/* Past Major Event */
.scd-timeline-card--past.scd-timeline-card--major {
	opacity: 0.75;
	background: linear-gradient(135deg, #FEF3C7 0%, #FDE68A 100%);
	border-left: 4px solid #F59E0B;
}

.scd-timeline-card--active {
	background: linear-gradient(135deg, #3B82F6 0%, #1D4ED8 100%);
	color: #FFFFFF;
	border-left: 6px solid #FBBF24;
	box-shadow: 0 12px 32px rgba(59, 130, 246, 0.4);
	transform: scale(1.02);
}

/* Active Major Event - More prominent */
.scd-timeline-card--active.scd-timeline-card--major {
	background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%);
	border-left: 6px solid #B45309;
	box-shadow: 0 16px 40px rgba(245, 158, 11, 0.5);
}

.scd-timeline-card--active:hover {
	transform: scale(1.02) translateY(-4px);
}

.scd-timeline-card--future {
	background: linear-gradient(135deg, #DBEAFE 0%, #BFDBFE 100%);
	border: 2px dashed #60A5FA;
	border-left: 4px solid #3B82F6;
}

/* Future Major Event */
.scd-timeline-card--future.scd-timeline-card--major {
	background: linear-gradient(135deg, #FEF3C7 0%, #FDE68A 100%);
	border: 2px dashed #F59E0B;
	border-left: 4px solid #D97706;
}

/* Focused State */
.scd-timeline-card--focused {
	border: 3px solid #3B82F6;
	box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
}

.scd-timeline-card--active.scd-timeline-card--focused {
	border: 3px solid #FBBF24;
	box-shadow: 0 0 0 4px rgba(251, 191, 36, 0.2),
				0 12px 32px rgba(59, 130, 246, 0.4);
}

/* ===========================
   Card Components
   =========================== */
.scd-timeline-card-header {
	display: flex;
	justify-content: space-between;
	align-items: flex-start;
	margin-bottom: 16px;
	flex-wrap: wrap;
	gap: 8px;
}

.scd-timeline-card-title {
	flex: 1;
}

.scd-timeline-card-title h3 {
	margin: 0 0 4px;
	font-size: 18px;
	font-weight: 600;
	display: flex;
	align-items: center;
	gap: 8px;
}

.scd-timeline-card--active .scd-timeline-card-title h3 {
	color: #FFFFFF;
}

.scd-timeline-icon {
	font-size: 24px;
}

.scd-timeline-major-badge {
	display: inline-block;
	padding: 2px 8px;
	font-size: 11px;
	font-weight: 700;
	text-transform: uppercase;
	background: #F59E0B;
	color: #FFFFFF;
	border-radius: 4px;
	letter-spacing: 0.5px;
}

.scd-timeline-card--active .scd-timeline-major-badge {
	background: rgba(255, 255, 255, 0.3);
}

.scd-timeline-card-badge {
	display: inline-flex;
	align-items: center;
	gap: 4px;
	padding: 4px 12px;
	border-radius: 16px;
	font-size: 12px;
	font-weight: 600;
	text-transform: uppercase;
	flex-shrink: 0;
}

.scd-badge-past {
	background: #F3F4F6;
	color: #6B7280;
}

.scd-badge-active {
	background: #FEF3C7;
	color: #92400E;
}

.scd-badge-future {
	background: #FFFFFF;
	color: #1E40AF;
}

.scd-timeline-card--active .scd-badge-active {
	background: rgba(255, 255, 255, 0.2);
	color: #FFFFFF;
}

.scd-timeline-card-content {
	margin-bottom: 20px;
}

.scd-timeline-card-description {
	font-size: 14px;
	line-height: 1.6;
	margin-bottom: 12px;
	color: #4B5563;
}

.scd-timeline-card--active .scd-timeline-card-description {
	color: rgba(255, 255, 255, 0.95);
}

.scd-timeline-card-discount,
.scd-timeline-card-stat {
	display: flex;
	align-items: center;
	gap: 8px;
	font-size: 14px;
	font-weight: 600;
	color: #1F2937;
}

.scd-timeline-card--active .scd-timeline-card-discount,
.scd-timeline-card--active .scd-timeline-card-stat {
	color: #FFFFFF;
}

.scd-timeline-card-actions {
	display: flex;
	gap: 8px;
}

.scd-timeline-card-actions .button {
	flex: 1;
	justify-content: center;
}

.scd-timeline-create-cta {
	font-weight: 600;
}

/* ===========================
   Unified Insights Section
   =========================== */
.scd-timeline-insights {
	background: #FFFFFF;
	border: 2px solid #E5E7EB;
	border-radius: 12px;
	overflow: hidden;
}

.scd-insights-tabs {
	display: flex;
	border-bottom: 2px solid #E5E7EB;
	background: #F9FAFB;
}

.scd-insights-tab {
	flex: 1;
	display: flex;
	align-items: center;
	justify-content: center;
	gap: 8px;
	padding: 16px;
	background: transparent;
	border: none;
	border-bottom: 3px solid transparent;
	cursor: pointer;
	font-size: 14px;
	font-weight: 500;
	color: #6B7280;
	transition: all 0.2s ease;
}

.scd-insights-tab:hover {
	background: #F3F4F6;
	color: #1F2937;
}

.scd-insights-tab--active {
	background: #FFFFFF;
	color: #3B82F6;
	border-bottom-color: #3B82F6;
}

.scd-tab-major-indicator {
	font-size: 16px;
}

.scd-insights-content {
	padding: 24px;
	min-height: 300px;
	transition: opacity 0.2s ease;
}

.scd-insights-content.scd-insights-loading {
	opacity: 0.5;
	pointer-events: none;
}

.scd-insights-header {
	display: flex;
	align-items: center;
	gap: 12px;
	margin-bottom: 24px;
}

.scd-insights-header .dashicons {
	width: 32px;
	height: 32px;
	font-size: 32px;
	color: #3B82F6;
}

.scd-insights-header h3 {
	margin: 0;
	font-size: 20px;
	color: #1F2937;
}

/* ===========================
   Insights Sections
   =========================== */
.scd-insights-sections {
	display: flex;
	flex-direction: column;
	gap: 16px;
}

.scd-insights-section {
	border: 1px solid #E5E7EB;
	border-radius: 8px;
	overflow: hidden;
}

.scd-insights-section-toggle {
	width: 100%;
	display: flex;
	align-items: center;
	gap: 12px;
	padding: 16px;
	background: #F9FAFB;
	border: none;
	cursor: pointer;
	text-align: left;
	transition: background 0.2s ease;
}

.scd-insights-section-toggle:hover {
	background: #F3F4F6;
}

.scd-insights-section--open .scd-insights-section-toggle {
	background: #EFF6FF;
}

.scd-insights-section-heading {
	font-size: 15px;
	font-weight: 600;
	color: #1F2937;
}

.scd-insights-section-content {
	padding: 20px;
	background: #FFFFFF;
}

/* Insight Content Types */
.scd-insights-message,
.scd-insights-stat-text,
.scd-insights-tip {
	display: flex;
	align-items: flex-start;
	gap: 12px;
	margin-bottom: 12px;
	padding: 12px;
	background: #F9FAFB;
	border-radius: 6px;
}

.scd-insights-stat {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 12px;
	background: #EFF6FF;
	border-radius: 6px;
	margin-bottom: 12px;
}

.scd-insights-stat-label {
	font-weight: 600;
	color: #1F2937;
}

.scd-insights-stat-value {
	font-size: 18px;
	font-weight: 700;
	color: #3B82F6;
}

.scd-insights-checklist-item {
	display: flex;
	align-items: center;
	gap: 12px;
	padding: 10px;
	margin-bottom: 8px;
}

.scd-insights-checklist-item input[type="checkbox"] {
	margin: 0;
}

.scd-insights-cta {
	margin-top: 16px;
	padding-top: 16px;
	border-top: 1px solid #E5E7EB;
}

.scd-insights-subheading {
	font-size: 16px;
	font-weight: 600;
	margin: 16px 0 12px;
	color: #1F2937;
}

.scd-insights-text {
	line-height: 1.6;
	color: #4B5563;
	margin-bottom: 12px;
}

.scd-insights-button {
	margin-bottom: 8px;
}

.scd-insights-timeline-header {
	display: flex;
	align-items: center;
	gap: 12px;
	padding: 16px;
	background: #EFF6FF;
	border-radius: 8px;
	margin-bottom: 20px;
}

.scd-timeline-days-badge {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	min-width: 48px;
	height: 48px;
	background: #3B82F6;
	color: #FFFFFF;
	border-radius: 50%;
	font-size: 20px;
	font-weight: 700;
	padding: 0 8px;
}

.scd-insights-timeline-section {
	margin-bottom: 20px;
}

.scd-insights-timeline-section h5 {
	font-size: 15px;
	font-weight: 600;
	margin: 0 0 12px;
	color: #1F2937;
}

.scd-insights-timeline-section ul {
	margin: 0;
	padding-left: 24px;
}

.scd-insights-timeline-section li {
	margin-bottom: 8px;
	color: #4B5563;
}

/* ===========================
   Responsive Design
   =========================== */
@media (max-width: 1024px) {
	.scd-timeline-grid {
		gap: 16px;
	}
}

@media (max-width: 768px) {
	.scd-timeline-grid {
		grid-template-columns: 1fr;
	}

	.scd-timeline-card--active {
		transform: scale(1);
	}

	.scd-timeline-card--active:hover {
		transform: translateY(-4px);
	}

	.scd-insights-tabs {
		flex-direction: column;
	}

	.scd-insights-tab {
		border-bottom: 1px solid #E5E7EB;
		border-left: 3px solid transparent;
		justify-content: flex-start;
	}

	.scd-insights-tab--active {
		border-left-color: #3B82F6;
		border-bottom-color: #E5E7EB;
	}
}

/* ===========================
   Animations
   =========================== */
@keyframes subtle-pulse {
	0%, 100% {
		box-shadow: 0 12px 32px rgba(59, 130, 246, 0.4);
	}
	50% {
		box-shadow: 0 12px 40px rgba(59, 130, 246, 0.5);
	}
}

.scd-timeline-card--active {
	animation: subtle-pulse 3s ease-in-out infinite;
}

.scd-timeline-card--active.scd-timeline-card--major {
	animation: major-event-pulse 3s ease-in-out infinite;
}

@keyframes major-event-pulse {
	0%, 100% {
		box-shadow: 0 16px 40px rgba(245, 158, 11, 0.5);
	}
	50% {
		box-shadow: 0 16px 48px rgba(245, 158, 11, 0.6);
	}
}
```

### 7. Asset Registration - Enqueue New Assets
**File**: `includes/admin/assets/class-asset-localizer.php`

**Add timeline assets to the dashboard page enqueue logic:**

Find the section where dashboard assets are enqueued and add:

```php
// In the method that enqueues dashboard page assets:

// Enqueue timeline CSS
wp_enqueue_style(
	'scd-timeline-styles',
	SCD_ASSETS_URL . 'css/admin/dashboard/timeline-styles.css',
	array( 'scd-main-dashboard' ),
	SCD_VERSION,
	'all'
);

// Enqueue timeline JavaScript
wp_enqueue_script(
	'scd-timeline-interactions',
	SCD_ASSETS_URL . 'js/admin/timeline-interactions.js',
	array( 'jquery', 'scd-main-dashboard' ),
	SCD_VERSION,
	true
);
```

---

## 🎯 Implementation Phases

### Phase 1: Backend Foundation
**Tasks:**
1. Create `class-weekly-campaign-definitions.php` (weekly campaign templates)
2. Create `class-campaign-timeline-service.php` (timeline logic service)
3. Register Timeline Service in `class-service-definitions.php`
4. Update Dashboard Service constructor and add delegation method
5. Add AJAX handler to `class-ajax-router.php`
6. Test timeline data generation in isolation

**Architecture:**
- Follow the same service pattern as Campaign Suggestions/Display services
- Timeline Service gets injected with: Campaign Repository, Campaign Suggestions Service, Logger
- Dashboard Service delegates to Timeline Service
- All timeline logic lives in Timeline Service (900+ lines)

**Testing:**
- Verify weekly campaigns return correct data
- Test state detection logic for all days of week
- Confirm major event detection works
- Validate priority-based selection for each position
- Test edge cases (no major events, all major events, etc.)

### Phase 2: View Templates
**Tasks:**
1. Replace campaign suggestions section in `main-dashboard.php`
2. Create `partials/timeline-insights.php`
3. Test template rendering with sample data

**Testing:**
- Verify 3-card grid displays correctly
- Check state-based rendering (past/active/future)
- Verify major event vs weekly visual differentiation
- Validate insights section renders properly
- Test with different campaign combinations

### Phase 3: Styling
**Tasks:**
1. Create `timeline-styles.css`
2. Implement responsive design
3. Add animations and transitions
4. Test across browsers

**Testing:**
- Desktop layout (3 columns)
- Tablet layout (3 columns, narrower)
- Mobile layout (vertical stack)
- Major event styling stands out
- Past major events visible but muted
- Cross-browser compatibility

### Phase 4: JavaScript Interactions
**Tasks:**
1. Create `timeline-interactions.js`
2. Implement card click/focus switching
3. Add AJAX insights loading (with major event flag)
4. Implement collapsible sections
5. Register assets in asset localizer

**Testing:**
- Click card to focus
- Verify AJAX insights loading
- Test major event insights vs weekly insights
- Test collapsible toggles
- Confirm smooth transitions
- Test error handling

### Phase 5: Integration & Polish
**Tasks:**
1. Update dashboard page controller
2. Test complete user flow
3. Performance optimization
4. Accessibility review
5. Documentation

**Testing:**
- Full end-to-end testing
- Test all campaign combinations
- Performance benchmarks
- Accessibility audit (WCAG 2.1 AA)
- User acceptance testing

---

## ✅ Testing Checklist

### Functional Tests - Dynamic Selection
- [ ] Weekly campaigns display when no major events
- [ ] Major events replace weekly in PAST position
- [ ] Major events replace weekly in ACTIVE position
- [ ] Major events replace weekly in FUTURE position
- [ ] Priority sorting works (major > weekly)
- [ ] Recency sorting works for PAST
- [ ] Proximity sorting works for FUTURE
- [ ] State detection works for all campaign types
- [ ] AJAX insights adapt to campaign type

### Visual Tests - Campaign Types
- [ ] Weekly PAST card: muted gray
- [ ] Major PAST card: muted gold/amber
- [ ] Weekly ACTIVE card: blue gradient
- [ ] Major ACTIVE card: orange/gold gradient with pulse
- [ ] Weekly FUTURE card: soft blue dashed
- [ ] Major FUTURE card: soft amber dashed
- [ ] Major event badge visible
- [ ] Focused state works for all types

### Content Tests - Insights
- [ ] Weekly insights show quick setup
- [ ] Major event insights show industry data
- [ ] Past insights show performance or missed opportunity
- [ ] Future insights show prep timeline
- [ ] Major event prep has longer timeline
- [ ] Collapsible sections work

### Edge Cases
- [ ] No campaigns (shouldn't happen with weekly)
- [ ] All 3 positions are major events (holiday season)
- [ ] All 3 positions are weekly (summer lull)
- [ ] Mixed timeline (1 major, 2 weekly)
- [ ] Campaign ended >30 days ago (filtered out)
- [ ] Campaign starts >60 days away (filtered out)

### Performance
- [ ] Page load time acceptable (<2s)
- [ ] AJAX response time fast (<500ms)
- [ ] No JavaScript errors in console
- [ ] No CSS layout shifts
- [ ] Memory usage reasonable

### Accessibility
- [ ] Keyboard navigation works
- [ ] Screen reader announces campaign types
- [ ] ARIA attributes present
- [ ] Color contrast meets WCAG AA
- [ ] Focus indicators visible
- [ ] Major event indicators perceivable without color

---

## 📈 Success Metrics

**Track these metrics post-launch:**
1. Campaign creation rate from timeline (target: 30%+)
2. Major event campaign creation rate (target: 50%+)
3. Weekly campaign adoption rate (target: 15%+)
4. User engagement with past insights (click rate)
5. Time spent on dashboard page
6. Multi-campaign adoption (users running 2+ per week)

---

## 🔧 WordPress Standards Compliance

This implementation follows WordPress and plugin coding standards:

✅ **PHP Standards:**
- Yoda conditions used throughout
- `array()` syntax (not `[]`)
- Tab indentation
- Spaces inside parentheses
- Proper nonce verification in AJAX handlers
- Capability checks enforced
- All output escaped (`esc_html`, `esc_attr`, `esc_url`)
- All input sanitized (`sanitize_text_field`, etc.)

✅ **JavaScript Standards:**
- ES5 syntax (no arrow functions, const/let)
- jQuery wrapper pattern
- Single quotes for strings
- camelCase variable names

✅ **Architecture Standards:**
- Service layer for business logic
- Priority-based campaign selection
- View separation (MVC pattern)
- Asset management system integration
- Modular JavaScript with clear namespacing

✅ **Security Standards:**
- Nonce verification on all AJAX calls
- Capability checks before data access
- Input sanitization
- Output escaping
- No SQL injection vulnerabilities

---

## 🚀 Ready for Implementation

This plan provides complete specifications for:
- **2 new files** with full code
- **7 edited files** with exact changes
- **Dynamic campaign selection** based on priority
- **Major events can appear in ANY position** (past/active/future)
- **Complete code** for all changes
- **Testing procedures**
- **Phased implementation approach**

**Next Steps:** Choose which phase to implement first, and I'll guide you through the exact code changes needed.

---

**End of Implementation Plan**
