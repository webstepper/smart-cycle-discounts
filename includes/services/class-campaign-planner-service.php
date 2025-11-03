<?php
/**
 * Campaign Planner Service Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/services/class-campaign-planner-service.php
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

declare(strict_types=1);

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
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Campaign_Planner_Service {

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
	 * Initialize the planner service.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign_Repository          $campaign_repository    Campaign repository.
	 * @param    SCD_Campaign_Suggestions_Service $suggestions_service    Suggestions service.
	 * @param    SCD_Logger                       $logger                 Logger instance.
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
	 * @since  1.0.0
	 * @param  array $all_campaigns All campaign opportunities with calculated states.
	 * @return array Exactly 3 campaigns for timeline display.
	 */
	private function build_smart_timeline( array $all_campaigns ): array {
		$timeline = array();

		// SLOT 1: PAST - Most recently ended campaign.
		$past_campaign = $this->get_best_campaign_for_position( $all_campaigns, 'past' );
		$timeline[]    = $past_campaign;

		// SLOT 2: ACTIVE/NEXT - Current campaign OR next upcoming (gap filler).
		$active_campaign = $this->get_best_campaign_for_position( $all_campaigns, 'active' );

		if ( $active_campaign ) {
			// Campaign currently running - show as active.
			$timeline[] = $active_campaign;
		} else {
			// NO active campaign - fill gap with next upcoming campaign.
			$next_campaign = $this->get_next_upcoming_campaign( $all_campaigns );
			$timeline[]    = $next_campaign;
		}

		// SLOT 3: FUTURE - Next campaign after slot 2.
		$slot_2_id      = isset( $timeline[1]['id'] ) ? $timeline[1]['id'] : null;
		$future_campaign = $this->get_next_future_campaign( $all_campaigns, $slot_2_id );
		$timeline[]     = $future_campaign;

		return $timeline;
	}

	/**
	 * Get next upcoming campaign (for gap filling).
	 *
	 * Finds the soonest future campaign to fill the active slot when
	 * no campaign is currently running. Prioritizes PROXIMITY over priority
	 * to show what's coming NEXT.
	 *
	 * @since  1.0.0
	 * @param  array $campaigns All campaign opportunities.
	 * @return array|null Next upcoming campaign, or null if none found.
	 */
	private function get_next_upcoming_campaign( array $campaigns ): ?array {
		$future_campaigns = array_filter(
			$campaigns,
			function ( $campaign ) {
				return 'future' === $campaign['state'];
			}
		);

		if ( empty( $future_campaigns ) ) {
			return null;
		}

		// This ensures we show what's coming NEXT, not just what's most important.
		usort(
			$future_campaigns,
			function ( $a, $b ) {
				// Soonest to start (PROXIMITY FIRST for gap-filling).
				$time_diff = $a['start_timestamp'] - $b['start_timestamp'];
				if ( 0 !== $time_diff ) {
					return $time_diff;
				}
				// If starting at same time, prioritize major events.
				return $b['priority'] - $a['priority'];
			}
		);

		return reset( $future_campaigns );
	}

	/**
	 * Get next future campaign after a given campaign.
	 *
	 * Finds the next upcoming campaign that's NOT the same as the
	 * campaign in slot 2 (to avoid duplication).
	 *
	 * @since  1.0.0
	 * @param  array       $campaigns      All campaign opportunities.
	 * @param  string|null $exclude_id     Campaign ID to exclude (slot 2).
	 * @return array|null Next future campaign, or null if none found.
	 */
	private function get_next_future_campaign( array $campaigns, ?string $exclude_id ): ?array {
		$now = current_time( 'timestamp' );

		$future_campaigns = array_filter(
			$campaigns,
			function ( $campaign ) use ( $exclude_id ) {
				return 'future' === $campaign['state'] && $campaign['id'] !== $exclude_id;
			}
		);

		if ( empty( $future_campaigns ) ) {
			return null;
		}

		usort(
			$future_campaigns,
			function ( $a, $b ) {
				// Priority first (major events = 100, weekly = 10).
				if ( $a['priority'] !== $b['priority'] ) {
					return $b['priority'] - $a['priority'];
				}
				// Soonest to start.
				return $a['start_timestamp'] - $b['start_timestamp'];
			}
		);

		// Only show future campaigns within 60 days.
		$sixty_days_ahead = $now + ( 60 * DAY_IN_SECONDS );
		$future_campaigns = array_filter(
			$future_campaigns,
			function ( $campaign ) use ( $sixty_days_ahead ) {
				return $campaign['start_timestamp'] <= $sixty_days_ahead;
			}
		);

		return ! empty( $future_campaigns ) ? reset( $future_campaigns ) : null;
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

		require_once SCD_INCLUDES_DIR . 'core/campaigns/class-weekly-campaign-definitions.php';
		$weekly_campaigns = SCD_Weekly_Campaign_Definitions::get_definitions();
		$campaigns        = array_merge( $campaigns, $weekly_campaigns );

		require_once SCD_INCLUDES_DIR . 'core/campaigns/class-campaign-suggestions-registry.php';
		$major_events = SCD_Campaign_Suggestions_Registry::get_event_definitions();

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
					'priority'        => 100, // Major events have high priority.
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
	 *
	 * @since  1.0.0
	 * @param  array $campaign Campaign data.
	 * @return string State: 'past', 'active', or 'future'.
	 */
	private function calculate_campaign_state( array $campaign ): string {
		$now = current_time( 'timestamp' );

		if ( ! empty( $campaign['is_major_event'] ) ) {
			// Major event state based on creation window.
			$window = $campaign['creation_window'] ?? array();

			if ( empty( $window ) ) {
				return 'future';
			}

			$window_start = $window['window_start'] ?? 0;
			$window_end   = $window['window_end'] ?? 0;
			$campaign_end = $campaign['campaign_end'] ?? 0;

			// If campaign ended, it's past.
			if ( $campaign_end < $now ) {
				return 'past';
			}

			// If in creation window, it's active.
			if ( $now >= $window_start && $now <= $window_end ) {
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
				if ( $current_day === $start_day && $current_time_int < $start_time_int ) {
					return 'future';
				}
				if ( $current_day === $end_day && $current_time_int > $end_time_int ) {
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
		$candidates = array_filter(
			$campaigns,
			function ( $campaign ) use ( $position ) {
				return $campaign['state'] === $position;
			}
		);

		if ( empty( $candidates ) ) {
			return null;
		}

		// Apply position-specific sorting.
		$now = current_time( 'timestamp' );

		switch ( $position ) {
			case 'past':
				usort(
					$candidates,
					function ( $a, $b ) {
						// Priority first.
						if ( $a['priority'] !== $b['priority'] ) {
							return $b['priority'] - $a['priority'];
						}
						// Most recently ended.
						return $b['end_timestamp'] - $a['end_timestamp'];
					}
				);

				// Timeline planner: Always show the most recent past campaign (no date filter).
				// The sorting ensures we get the most recent one.
				break;

			case 'active':
				usort(
					$candidates,
					function ( $a, $b ) {
						// Priority first.
						if ( $a['priority'] !== $b['priority'] ) {
							return $b['priority'] - $a['priority'];
						}
						// If both major events, neither gets precedence.
						return 0;
					}
				);
				break;

			case 'future':
				usort(
					$candidates,
					function ( $a, $b ) {
						// Priority first.
						if ( $a['priority'] !== $b['priority'] ) {
							return $b['priority'] - $a['priority'];
						}
						// Soonest to start.
						return $a['start_timestamp'] - $b['start_timestamp'];
					}
				);

				// Only show future campaigns within 60 days.
				$sixty_days_ahead = $now + ( 60 * DAY_IN_SECONDS );
				$candidates       = array_filter(
					$candidates,
					function ( $campaign ) use ( $sixty_days_ahead ) {
						return $campaign['start_timestamp'] <= $sixty_days_ahead;
					}
				);
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
				if ( $current_day < $start_day || ( $current_day === $start_day && $current_time_int < $start_time_int ) ) {
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
	 * @since  1.0.0
	 * @param  array $campaign Campaign data.
	 * @return string Wizard URL.
	 */
	private function get_wizard_url_for_campaign( array $campaign ): string {
		$base_url = admin_url( 'admin.php?page=scd-campaigns&action=wizard&intent=new' );

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

		return $random_stat;
	}
}
