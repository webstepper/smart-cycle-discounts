<?php
/**
 * Campaign Planner Service Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/services/class-campaign-planner-service.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress/plugins/smart-cycle-discounts/
 * @since      1.0.0
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Campaign Planner Service Class
 *
 * Responsible for generating and managing the weekly campaign planner
 * with intelligent mixing of major events and weekly campaigns.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/services
 * @author     Webstepper <contact@webstepper.io>
 */
class WSSCD_Campaign_Planner_Service {

	/**
	 * Maximum days ahead to show future campaigns.
	 *
	 * @since    1.0.0
	 * @var      int
	 */
	const FUTURE_HORIZON_DAYS = 60;

	/**
	 * Campaign repository instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Campaign_Repository    $campaign_repository    Campaign repository.
	 */
	private WSSCD_Campaign_Repository $campaign_repository;

	/**
	 * Campaign suggestions service instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Campaign_Suggestions_Service    $suggestions_service    Suggestions service.
	 */
	private WSSCD_Campaign_Suggestions_Service $suggestions_service;

	/**
	 * Logger instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Logger    $logger    Logger instance.
	 */
	private WSSCD_Logger $logger;

	/**
	 * Initialize the planner service.
	 *
	 * @since    1.0.0
	 * @param    WSSCD_Campaign_Repository          $campaign_repository    Campaign repository.
	 * @param    WSSCD_Campaign_Suggestions_Service $suggestions_service    Suggestions service.
	 * @param    WSSCD_Logger                       $logger                 Logger instance.
	 */
	public function __construct(
		WSSCD_Campaign_Repository $campaign_repository,
		WSSCD_Campaign_Suggestions_Service $suggestions_service,
		WSSCD_Logger $logger
	) {
		$this->campaign_repository = $campaign_repository;
		$this->suggestions_service = $suggestions_service;
		$this->logger              = $logger;
	}

	/**
	 * Get weekly planner campaigns with smart 3-slot timeline.
	 *
	 * Always returns exactly 3 campaigns representing past, present, and future.
	 * Intelligently fills gaps when no campaign is currently active.
	 *
	 * Timeline Structure:
	 * - Slot 1 (PAST): Most recently ended campaign
	 * - Slot 2 (ACTIVE/NEXT): Current campaign OR next upcoming if no active campaign
	 * - Slot 3 (FUTURE): Next campaign after slot 2
	 *
	 * @since  1.0.0
	 * @return array Timeline data with exactly 3 campaigns.
	 */
	public function get_weekly_planner_campaigns(): array {
		$all_campaigns = $this->get_all_campaign_opportunities();

		foreach ( $all_campaigns as &$campaign ) {
			$campaign['state']           = $this->calculate_campaign_state( $campaign );
			$campaign['end_timestamp']   = $this->get_campaign_end_timestamp( $campaign );
			$campaign['start_timestamp'] = $this->get_campaign_start_timestamp( $campaign );
		}

		$timeline = $this->build_smart_timeline( $all_campaigns );

		foreach ( $timeline as &$campaign ) {
			if ( ! empty( $campaign ) ) {
				$campaign['wizard_url'] = $this->get_wizard_url_for_campaign( $campaign );
				// Replace description with random insight from pools on each load.
				$campaign['description'] = $this->get_random_insight( $campaign );
				$campaign['random_stat'] = $this->get_random_stat( $campaign );
				// Add formatted date info for cards.
				$campaign['date_range']    = $this->format_date_range( $campaign );
				$campaign['time_relative'] = $this->format_time_relative( $campaign );
				// Add formatted discount suggestion (matches prefilled value).
				$campaign['discount_suggestion'] = $this->format_discount_suggestion( $campaign );
			}
		}

		$this->logger->debug(
			'Campaign Planner Service: Smart 3-Slot Timeline',
			array(
				'total_opportunities' => count( $all_campaigns ),
				'slot_1_past'         => isset( $timeline[0] ) ? $timeline[0]['name'] . ' (' . $timeline[0]['state'] . ')' : 'empty',
				'slot_2_active_next'  => isset( $timeline[1] ) ? $timeline[1]['name'] . ' (' . $timeline[1]['state'] . ')' : 'empty',
				'slot_3_future'       => isset( $timeline[2] ) ? $timeline[2]['name'] . ' (' . $timeline[2]['state'] . ')' : 'empty',
			)
		);

		return array(
			'type'      => 'smart_timeline',
			'campaigns' => $timeline,
		);
	}

	/**
	 * Build smart 3-slot timeline (always 3 campaigns).
	 *
	 * Implements intelligent gap-filling logic to ensure continuous timeline:
	 * 1. PAST: Most recently ended campaign
	 * 2. ACTIVE/NEXT: Current OR next upcoming (fills gaps)
	 * 3. FUTURE: Next after active/next slot
	 *
	 * Special handling: When a major event is within 3 days, it overrides
	 * any active weekly campaign to keep focus on the important event.
	 *
	 * @since  1.0.0
	 * @param  array $all_campaigns All campaign opportunities with calculated states.
	 * @return array Exactly 3 campaigns for timeline display.
	 */
	private function build_smart_timeline( array $all_campaigns ): array {
		$timeline = array();

		// SLOT 1: PAST - Most recently ended campaign.
		$past_campaign = $this->get_best_campaign_for_position( $all_campaigns, 'past' );
		$timeline[]    = $past_campaign;

		// Check for imminent major events (within 3 days) that should override weekly campaigns.
		$imminent_major_event = $this->get_imminent_major_event( $all_campaigns );

		// SLOT 2: ACTIVE/NEXT - Current campaign OR next upcoming (gap filler).
		$active_campaign = $this->get_best_campaign_for_position( $all_campaigns, 'active' );

		if ( $active_campaign ) {
			// Check if active campaign is weekly and there's an imminent major event.
			$is_weekly_active = empty( $active_campaign['is_major_event'] );

			if ( $is_weekly_active && $imminent_major_event ) {
				// Override weekly campaign with imminent major event.
				$timeline[] = $imminent_major_event;
			} else {
				// Show the active campaign (either major event or no imminent override).
				$timeline[] = $active_campaign;
			}
		} else {
			// NO active campaign - fill gap with next upcoming campaign.
			$next_campaign = $this->get_next_upcoming_campaign( $all_campaigns );
			$timeline[]    = $next_campaign;
		}

		// SLOT 3: FUTURE - Next campaign after slot 2.
		$slot_2_id       = isset( $timeline[1]['id'] ) ? $timeline[1]['id'] : null;
		$future_campaign = $this->get_next_future_campaign( $all_campaigns, $slot_2_id );
		$timeline[]      = $future_campaign;

		return $timeline;
	}

	/**
	 * Get imminent major event within specified days.
	 *
	 * Finds a major event starting within the specified number of days.
	 * Used for both overriding weekly campaigns (3 days) and gap-filling (7 days).
	 *
	 * @since  1.0.0
	 * @param  array $campaigns All campaign opportunities.
	 * @param  int   $days      Number of days to look ahead (default: 3).
	 * @return array|null Imminent major event, or null if none found.
	 */
	private function get_imminent_major_event( array $campaigns, int $days = 3 ): ?array {
		$now       = current_time( 'timestamp' );
		$threshold = $now + ( $days * DAY_IN_SECONDS );

		$imminent_events = array();
		foreach ( $campaigns as $campaign ) {
			// Only check future major events.
			if ( empty( $campaign['is_major_event'] ) || 'future' !== $campaign['state'] ) {
				continue;
			}

			// Check if starting within threshold.
			if ( $campaign['start_timestamp'] <= $threshold ) {
				$imminent_events[] = $campaign;
			}
		}

		if ( empty( $imminent_events ) ) {
			return null;
		}

		// Sort by priority, then start time.
		usort(
			$imminent_events,
			array( $this, 'compare_campaigns_by_priority_then_start' )
		);

		return reset( $imminent_events );
	}

	/**
	 * Get next upcoming campaign (for gap filling).
	 *
	 * Finds the most relevant future campaign to fill the active slot when
	 * no campaign is currently running. Major events within 7 days take
	 * priority over weekly campaigns to ensure important events are visible.
	 *
	 * @since  1.0.0
	 * @param  array $campaigns All campaign opportunities.
	 * @return array|null Next upcoming campaign, or null if none found.
	 */
	private function get_next_upcoming_campaign( array $campaigns ): ?array {
		// Check for major events starting within 7 days - they take priority.
		$imminent_major_event = $this->get_imminent_major_event( $campaigns, 7 );
		if ( $imminent_major_event ) {
			return $imminent_major_event;
		}

		// No imminent major events - find soonest future campaign.
		$future_campaigns = array();
		foreach ( $campaigns as $campaign ) {
			if ( 'future' === $campaign['state'] ) {
				$future_campaigns[] = $campaign;
			}
		}

		if ( empty( $future_campaigns ) ) {
			return null;
		}

		// Sort by start timestamp, then priority.
		usort(
			$future_campaigns,
			array( $this, 'compare_campaigns_by_start_then_priority' )
		);

		return reset( $future_campaigns );
	}

	/**
	 * Get next campaign for slot 3 (future position).
	 *
	 * Finds the next upcoming campaign that's NOT the same as the
	 * campaign in slot 2 (to avoid duplication). Considers both "future"
	 * and "active" campaigns since multiple major events can be active
	 * simultaneously (e.g., Black Friday and Christmas both within 7-day window).
	 *
	 * Prioritizes major events over weekly campaigns to keep focus on
	 * important business planning during major event seasons.
	 *
	 * @since  1.0.0
	 * @param  array       $campaigns      All campaign opportunities.
	 * @param  string|null $exclude_id     Campaign ID to exclude (slot 2).
	 * @return array|null Next campaign for slot 3, or null if none found.
	 */
	private function get_next_future_campaign( array $campaigns, ?string $exclude_id ): ?array {
		$now = current_time( 'timestamp' );

		// Filter campaigns that are not in slot 2 and are either future or active.
		// This handles the case where multiple major events have overlapping promotion windows.
		$candidate_campaigns = array();
		foreach ( $campaigns as $campaign ) {
			if ( $exclude_id === $campaign['id'] ) {
				continue;
			}
			// Include both future campaigns and active campaigns (for overlapping major events).
			if ( 'future' === $campaign['state'] || 'active' === $campaign['state'] ) {
				$candidate_campaigns[] = $campaign;
			}
		}

		if ( empty( $candidate_campaigns ) ) {
			return null;
		}

		// Sort by priority first (major events before weekly), then start timestamp.
		// This ensures Christmas shows after Black Friday, not Wednesday Wins.
		usort(
			$candidate_campaigns,
			array( $this, 'compare_campaigns_by_priority_then_start' )
		);

		// Only show campaigns within the future horizon.
		$future_horizon = $now + ( self::FUTURE_HORIZON_DAYS * DAY_IN_SECONDS );
		$filtered         = array();
		foreach ( $candidate_campaigns as $campaign ) {
			if ( $campaign['start_timestamp'] <= $future_horizon ) {
				$filtered[] = $campaign;
			}
		}

		return ! empty( $filtered ) ? reset( $filtered ) : null;
	}

	/**
	 * Get all campaign opportunities (weekly + major events).
	 *
	 * Combines weekly campaign definitions with major event definitions
	 * to create a unified pool of campaign opportunities.
	 *
	 * @since  1.0.0
	 * @return array Combined array of all campaign opportunities.
	 */
	private function get_all_campaign_opportunities(): array {
		$campaigns = array();

		require_once WSSCD_INCLUDES_DIR . 'core/campaigns/class-weekly-campaign-definitions.php';
		$weekly_campaigns = WSSCD_Weekly_Campaign_Definitions::get_definitions();
		$campaigns        = array_merge( $campaigns, $weekly_campaigns );

		require_once WSSCD_INCLUDES_DIR . 'core/campaigns/class-campaign-suggestions-registry.php';
		$major_events = WSSCD_Campaign_Suggestions_Registry::get_event_definitions();

		$current_year = intval( wp_date( 'Y' ) );
		$now          = current_time( 'timestamp' );

		foreach ( $major_events as $event ) {
			$event_date = $this->suggestions_service->calculate_event_date( $event, $current_year );

			// If event passed this year, check next year.
			if ( $event_date < $now ) {
				$event_date = $this->suggestions_service->calculate_event_date( $event, $current_year + 1 );
			}

			$start_offset = isset( $event['start_offset'] ) ? $event['start_offset'] : 0;
			$duration     = isset( $event['duration_days'] ) ? $event['duration_days'] : 7;

			$campaign_start = strtotime( $start_offset . ' days', $event_date );
			$campaign_end   = strtotime( '+' . $duration . ' days', $campaign_start );

			$window = $this->suggestions_service->calculate_suggestion_window( $event, $event_date );

			$campaigns[] = array_merge(
				$event,
				array(
					'is_major_event'  => true,
					'priority'        => isset( $event['priority'] ) ? $event['priority'] : 100,
					'event_date'      => $event_date,
					'campaign_start'  => $campaign_start,
					'campaign_end'    => $campaign_end,
					'creation_window' => $window,
				)
			);
		}

		return $campaigns;
	}

	/**
	 * Calculate campaign state (past/active/future).
	 *
	 * Determines whether a campaign has ended, is currently active, or is upcoming.
	 * State reflects the ACTUAL campaign period, not the promotion window.
	 *
	 * @since  1.0.0
	 * @param  array $campaign Campaign data.
	 * @return string State: 'past', 'active', or 'future'.
	 */
	private function calculate_campaign_state( array $campaign ): string {
		$now = current_time( 'timestamp' );

		if ( ! empty( $campaign['is_major_event'] ) ) {
			$campaign_start = $campaign['campaign_start'] ?? 0;
			$campaign_end   = $campaign['campaign_end'] ?? 0;

			// If campaign has ended, it's past.
			if ( $campaign_end < $now ) {
				return 'past';
			}

			// If campaign is currently running, it's active.
			if ( $now >= $campaign_start && $now <= $campaign_end ) {
				return 'active';
			}

			// Otherwise, it's future.
			return 'future';
		} else {
			// Weekly campaign state based on day/time.
			$current_day  = intval( current_time( 'N' ) ); // 1=Monday, 7=Sunday.
			$current_time = current_time( 'H:i' );

			$schedule   = $campaign['schedule'];
			$start_day  = $schedule['start_day'];
			$end_day    = $schedule['end_day'];
			$start_time = $schedule['start_time'];
			$end_time   = $schedule['end_time'];

			$current_time_int = intval( str_replace( ':', '', $current_time ) );
			$start_time_int   = intval( str_replace( ':', '', $start_time ) );
			$end_time_int     = intval( str_replace( ':', '', $end_time ) );

			if ( $current_day >= $start_day && $current_day <= $end_day ) {
				// Same day - check time.
				if ( $start_day === $current_day && $current_time_int < $start_time_int ) {
					return 'future';
				}
				if ( $end_day === $current_day && $current_time_int > $end_time_int ) {
					return 'past';
				}
				return 'active';
			}

			// Handle week wraparound (e.g., Monday checking Weekend campaign that ended Sunday).
			// If current day is early in week (Mon-Wed) and campaign STARTS late in week (Thu-Sun),
			// the campaign is PAST (ended last week), not FUTURE.
			if ( $current_day < $start_day ) {
				// Early week (Mon-Wed) vs late week campaign start (Thu-Sun) = past week.
				if ( $current_day <= 3 && $start_day >= 4 ) {
					return 'past';
				}
				// Otherwise, campaign is in future (starts later this week).
				return 'future';
			}

			// After end day (within same week).
			return 'past';
		}
	}

	/**
	 * Get best campaign for a specific position.
	 *
	 * Applies priority rules to select the most relevant campaign.
	 *
	 * @since  1.0.0
	 * @param  array  $campaigns All campaign opportunities.
	 * @param  string $position  Position: 'past', 'active', or 'future'.
	 * @return array|null Best campaign for position, or null if none.
	 */
	private function get_best_campaign_for_position( array $campaigns, string $position ): ?array {
		// Filter by position - avoid closure for serialization compatibility.
		$candidates = array();
		foreach ( $campaigns as $campaign ) {
			if ( $position === $campaign['state'] ) {
				$candidates[] = $campaign;
			}
		}

		if ( empty( $candidates ) ) {
			return null;
		}

		// Apply position-specific sorting - avoid closures for serialization compatibility.
		$now = current_time( 'timestamp' );

		switch ( $position ) {
			case 'past':
				usort(
					$candidates,
					array( $this, 'compare_campaigns_by_priority_then_end' )
				);

				// Timeline planner: Always show the most recent past campaign (no date filter).
				// The sorting ensures we get the most recent one.
				break;

			case 'active':
				usort(
					$candidates,
					array( $this, 'compare_campaigns_by_priority_then_start' )
				);
				break;

			case 'future':
				usort(
					$candidates,
					array( $this, 'compare_campaigns_by_priority_then_start' )
				);

				// Only show future campaigns within the future horizon.
				$future_horizon = $now + ( self::FUTURE_HORIZON_DAYS * DAY_IN_SECONDS );
				$filtered       = array();
				foreach ( $candidates as $campaign ) {
					if ( $campaign['start_timestamp'] <= $future_horizon ) {
						$filtered[] = $campaign;
					}
				}
				$candidates = $filtered;
				break;
		}

		return ! empty( $candidates ) ? reset( $candidates ) : null;
	}

	/**
	 * Get campaign end timestamp.
	 *
	 * @since  1.0.0
	 * @param  array $campaign Campaign data.
	 * @return int Timestamp.
	 */
	private function get_campaign_end_timestamp( array $campaign ): int {
		if ( ! empty( $campaign['is_major_event'] ) ) {
			return $campaign['campaign_end'];
		} else {
			// Weekly campaigns - calculate end time for current week.
			$current_week_start = strtotime( 'this week Monday 00:00' );
			$schedule           = $campaign['schedule'];
			$end_day            = $schedule['end_day'];
			$end_time           = $schedule['end_time'];

			$days_offset = $end_day - 1; // Monday = 0 offset.
			return strtotime( "+{$days_offset} days {$end_time}", $current_week_start );
		}
	}

	/**
	 * Get campaign start timestamp.
	 *
	 * For weekly campaigns, calculates next occurrence (this week or next week).
	 *
	 * @since  1.0.0
	 * @param  array $campaign Campaign data.
	 * @return int Timestamp.
	 */
	private function get_campaign_start_timestamp( array $campaign ): int {
		if ( ! empty( $campaign['is_major_event'] ) ) {
			return $campaign['campaign_start'];
		} else {
			// Weekly campaigns - calculate next occurrence.
			$schedule   = $campaign['schedule'];
			$start_day  = $schedule['start_day'];
			$start_time = $schedule['start_time'];

			// If campaign is in 'future' state, use next occurrence.
			// If campaign is in 'past' or 'active' state, use current/recent occurrence.
			if ( 'future' === $campaign['state'] ) {
				$current_day  = intval( current_time( 'N' ) );
				$current_time = current_time( 'H:i' );

				$current_time_int = intval( str_replace( ':', '', $current_time ) );
				$start_time_int   = intval( str_replace( ':', '', $start_time ) );

				// Determine week offset.
				if ( $current_day < $start_day || ( $start_day === $current_day && $current_time_int < $start_time_int ) ) {
					// Campaign is later this week.
					$week_offset = 0;
				} else {
					// Campaign has passed this week, use next week.
					$week_offset = 1;
				}

				$base_monday = strtotime( 'this week Monday 00:00' );
				$target_week = strtotime( "+{$week_offset} weeks", $base_monday );
				$days_offset = $start_day - 1; // Monday = 0 offset.

				return strtotime( "+{$days_offset} days {$start_time}", $target_week );
			} else {
				// For past/active campaigns, use current week occurrence.
				$current_week_start = strtotime( 'this week Monday 00:00' );
				$days_offset        = $start_day - 1;
				return strtotime( "+{$days_offset} days {$start_time}", $current_week_start );
			}
		}
	}

	/**
	 * Get wizard URL for campaign creation.
	 *
	 * The wizard pre-fills schedule dates from the suggestion ID automatically
	 * via prefill_from_suggestion() in the wizard state service.
	 *
	 * @since  1.0.0
	 * @param  array $campaign Campaign data.
	 * @return string Wizard URL.
	 */
	private function get_wizard_url_for_campaign( array $campaign ): string {
		$base_url = admin_url( 'admin.php?page=wsscd-campaigns&action=wizard&intent=new' );

		$campaign_id = $campaign['id'];
		return add_query_arg( 'suggestion', $campaign_id, $base_url );
	}

	/**
	 * Get random insight from campaign's insight pools.
	 *
	 * Randomly selects one insight from the campaign's rich data pools
	 * (best_for, statistics, recommendations, psychology) to display
	 * as the card description on each page load.
	 *
	 * @since  1.0.0
	 * @param  array $campaign Campaign data with insight pools.
	 * @return string Random insight text.
	 */
	private function get_random_insight( array $campaign ): string {
		$available_pools = array();

		// Collect available insight pools.
		if ( ! empty( $campaign['best_for'] ) && is_array( $campaign['best_for'] ) ) {
			$available_pools['best_for'] = $campaign['best_for'];
		}

		if ( ! empty( $campaign['statistics'] ) && is_array( $campaign['statistics'] ) ) {
			$available_pools['statistics'] = array_values( $campaign['statistics'] );
		}

		if ( ! empty( $campaign['recommendations'] ) && is_array( $campaign['recommendations'] ) ) {
			$available_pools['recommendations'] = $campaign['recommendations'];
		}

		if ( ! empty( $campaign['psychology'] ) && is_string( $campaign['psychology'] ) ) {
			$available_pools['psychology'] = array( $campaign['psychology'] );
		}

		// If no insights available, return original description.
		if ( empty( $available_pools ) ) {
			return isset( $campaign['description'] ) ? $campaign['description'] : '';
		}

		// Randomly select a pool.
		$pool_keys    = array_keys( $available_pools );
		$random_pool  = $pool_keys[ array_rand( $pool_keys ) ];
		$selected_pool = $available_pools[ $random_pool ];

		// Randomly select an item from the pool.
		$random_insight = $selected_pool[ array_rand( $selected_pool ) ];

		// Strip PRO marker for PRO users (show badge only to FREE users for promotional purposes).
		if ( wsscd_is_license_valid() ) {
			$random_insight = str_replace( ' [PRO]', '', $random_insight );
		}

		return $random_insight;
	}

	/**
	 * Get random statistic from campaign's statistics pool.
	 *
	 * Randomly selects one statistic from the campaign's statistics array
	 * to display as the card stat on each page load.
	 *
	 * @since  1.0.0
	 * @param  array $campaign Campaign data with statistics pool.
	 * @return string Random statistic text.
	 */
	private function get_random_stat( array $campaign ): string {
		if ( empty( $campaign['statistics'] ) || ! is_array( $campaign['statistics'] ) ) {
			return '';
		}

		$stats = array_values( $campaign['statistics'] );

		// Randomly select one stat.
		$random_stat = $stats[ array_rand( $stats ) ];

		// Strip PRO marker for PRO users (show badge only to FREE users for promotional purposes).
		if ( wsscd_is_license_valid() ) {
			$random_stat = str_replace( ' [PRO]', '', $random_stat );
		}

		return $random_stat;
	}

	/**
	 * Compare campaigns by start timestamp, then priority.
	 * Used instead of closure for serialization compatibility.
	 *
	 * @since  1.0.0
	 * @param  array $a First campaign.
	 * @param  array $b Second campaign.
	 * @return int Comparison result.
	 */
	private function compare_campaigns_by_start_then_priority( array $a, array $b ): int {
		// Soonest to start (PROXIMITY FIRST for gap-filling).
		$time_diff = $a['start_timestamp'] - $b['start_timestamp'];
		if ( 0 !== $time_diff ) {
			return $time_diff;
		}
		// If starting at same time, prioritize major events.
		return $b['priority'] - $a['priority'];
	}

	/**
	 * Compare campaigns by priority, then start timestamp.
	 * Used instead of closure for serialization compatibility.
	 *
	 * @since  1.0.0
	 * @param  array $a First campaign.
	 * @param  array $b Second campaign.
	 * @return int Comparison result.
	 */
	private function compare_campaigns_by_priority_then_start( array $a, array $b ): int {
		// Priority first (major events = 100, weekly = 10).
		if ( $a['priority'] !== $b['priority'] ) {
			return $b['priority'] - $a['priority'];
		}
		// Soonest to start.
		return $a['start_timestamp'] - $b['start_timestamp'];
	}

	/**
	 * Compare campaigns by priority, then end timestamp.
	 * Used instead of closure for serialization compatibility.
	 *
	 * @since  1.0.0
	 * @param  array $a First campaign.
	 * @param  array $b Second campaign.
	 * @return int Comparison result.
	 */
	private function compare_campaigns_by_priority_then_end( array $a, array $b ): int {
		// Priority first.
		if ( $a['priority'] !== $b['priority'] ) {
			return $b['priority'] - $a['priority'];
		}
		// Most recently ended.
		return $b['end_timestamp'] - $a['end_timestamp'];
	}

	/**
	 * Format event date for campaign card display.
	 *
	 * Shows the actual event date (e.g., "Nov 29" for Black Friday),
	 * not the campaign/promotion period.
	 *
	 * @since  1.0.0
	 * @param  array $campaign Campaign data.
	 * @return string Formatted event date (e.g., "Nov 29").
	 */
	private function format_date_range( array $campaign ): string {
		// For major events, use the actual event date.
		if ( ! empty( $campaign['is_major_event'] ) && ! empty( $campaign['event_date'] ) ) {
			return wp_date( 'M j', $campaign['event_date'] );
		}

		// For weekly campaigns, show the day range (e.g., "Wed - Thu").
		if ( ! empty( $campaign['schedule'] ) ) {
			$days = array(
				1 => __( 'Mon', 'smart-cycle-discounts' ),
				2 => __( 'Tue', 'smart-cycle-discounts' ),
				3 => __( 'Wed', 'smart-cycle-discounts' ),
				4 => __( 'Thu', 'smart-cycle-discounts' ),
				5 => __( 'Fri', 'smart-cycle-discounts' ),
				6 => __( 'Sat', 'smart-cycle-discounts' ),
				7 => __( 'Sun', 'smart-cycle-discounts' ),
			);

			$start_day = $campaign['schedule']['start_day'] ?? 1;
			$end_day   = $campaign['schedule']['end_day'] ?? 1;

			if ( $start_day === $end_day ) {
				return $days[ $start_day ] ?? '';
			}

			return sprintf( '%s - %s', $days[ $start_day ] ?? '', $days[ $end_day ] ?? '' );
		}

		return '';
	}

	/**
	 * Format relative time for campaign card display.
	 *
	 * Shows time relative to the actual event date, not the campaign period.
	 *
	 * @since  1.0.0
	 * @param  array $campaign Campaign data.
	 * @return string Relative time (e.g., "In 3 days", "5 days ago").
	 */
	private function format_time_relative( array $campaign ): string {
		$now   = current_time( 'timestamp' );
		$state = $campaign['state'] ?? '';

		// For major events, use the actual event date.
		if ( ! empty( $campaign['is_major_event'] ) && ! empty( $campaign['event_date'] ) ) {
			$event_date = $campaign['event_date'];
			$days_diff  = (int) round( ( $event_date - $now ) / DAY_IN_SECONDS );

			if ( $days_diff < 0 ) {
				// Past.
				$days_ago = abs( $days_diff );
				if ( 0 === $days_ago ) {
					return __( 'Today', 'smart-cycle-discounts' );
				} elseif ( 1 === $days_ago ) {
					return __( 'Yesterday', 'smart-cycle-discounts' );
				} else {
					return sprintf(
						/* translators: %d: number of days */
						__( '%d days ago', 'smart-cycle-discounts' ),
						$days_ago
					);
				}
			} elseif ( 0 === $days_diff ) {
				return __( 'Today', 'smart-cycle-discounts' );
			} elseif ( 1 === $days_diff ) {
				return __( 'Tomorrow', 'smart-cycle-discounts' );
			} else {
				return sprintf(
					/* translators: %d: number of days */
					__( 'In %d days', 'smart-cycle-discounts' ),
					$days_diff
				);
			}
		}

		// For weekly campaigns, use relative to campaign period.
		switch ( $state ) {
			case 'past':
				return __( 'Last week', 'smart-cycle-discounts' );

			case 'active':
				return __( 'This week', 'smart-cycle-discounts' );

			case 'future':
				return __( 'Next week', 'smart-cycle-discounts' );

			default:
				return '';
		}
	}

	/**
	 * Format discount suggestion for campaign card display.
	 *
	 * Shows the optimal discount value that will be prefilled in the wizard.
	 *
	 * @since  1.0.0
	 * @param  array $campaign Campaign data.
	 * @return string Formatted discount (e.g., "20% off").
	 */
	private function format_discount_suggestion( array $campaign ): string {
		if ( empty( $campaign['suggested_discount'] ) ) {
			return '';
		}

		$discount = $campaign['suggested_discount'];

		// Use optimal value (same as wizard prefill).
		if ( isset( $discount['optimal'] ) ) {
			return sprintf(
				/* translators: %d: discount percentage */
				__( '%d%% off', 'smart-cycle-discounts' ),
				(int) $discount['optimal']
			);
		}

		return '';
	}

}
