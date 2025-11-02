<?php
/**
 * Campaign Planner Service
 *
 * Handles weekly campaign planner logic including:
 * - Combining weekly campaigns with major events
 * - Calculating campaign states (past/active/future)
 * - Selecting best campaign for each planner position
 * - Applying priority-based selection rules
 *
 * Follows Single Responsibility Principle - focuses only on planner logic.
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
	 * Get weekly planner campaigns with dynamic selection.
	 *
	 * Intelligently mixes major events and weekly campaigns based on priority.
	 * Each position (past/active/future) shows the most relevant campaign.
	 *
	 * @since  1.0.0
	 * @return array Timeline data with 3 selected campaigns.
	 */
	public function get_weekly_planner_campaigns(): array {
		// Get all potential campaigns (weekly + major events).
		$all_campaigns = $this->get_all_campaign_opportunities();

		// Calculate state and priority for each campaign.
		foreach ( $all_campaigns as &$campaign ) {
			$campaign['state']           = $this->calculate_campaign_state( $campaign );
			$campaign['end_timestamp']   = $this->get_campaign_end_timestamp( $campaign );
			$campaign['start_timestamp'] = $this->get_campaign_start_timestamp( $campaign );
		}

		// Select best campaign for each position.
		$planner_positions = array(
			'past'   => $this->get_best_campaign_for_position( $all_campaigns, 'past' ),
			'active' => $this->get_best_campaign_for_position( $all_campaigns, 'active' ),
			'future' => $this->get_best_campaign_for_position( $all_campaigns, 'future' ),
		);

		// Remove empty positions.
		$planner_positions = array_filter( $planner_positions );

		// Add wizard URLs.
		foreach ( $planner_positions as $position => &$campaign ) {
			if ( ! empty( $campaign ) ) {
				$campaign['wizard_url'] = $this->get_wizard_url_for_campaign( $campaign );
			}
		}

		$this->logger->debug(
			'Campaign Planner Service: get_weekly_planner_campaigns()',
			array(
				'total_opportunities' => count( $all_campaigns ),
				'past_count'          => ! empty( $planner_positions['past'] ) ? 1 : 0,
				'active_count'        => ! empty( $planner_positions['active'] ) ? 1 : 0,
				'future_count'        => ! empty( $planner_positions['future'] ) ? 1 : 0,
			)
		);

		return array(
			'type'      => 'dynamic',
			'campaigns' => array_values( $planner_positions ), // Re-index array.
		);
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

		// Add weekly campaigns.
		require_once SCD_INCLUDES_DIR . 'core/campaigns/class-weekly-campaign-definitions.php';
		$weekly_campaigns = SCD_Weekly_Campaign_Definitions::get_definitions();
		$campaigns        = array_merge( $campaigns, $weekly_campaigns );

		// Add major events.
		require_once SCD_INCLUDES_DIR . 'core/campaigns/class-campaign-suggestions-registry.php';
		$major_events = SCD_Campaign_Suggestions_Registry::get_event_definitions();

		$current_year = intval( wp_date( 'Y' ) );
		$now          = current_time( 'timestamp' );

		foreach ( $major_events as $event ) {
			// Calculate event dates.
			$event_date = $this->suggestions_service->calculate_event_date( $event, $current_year );

			// If event passed this year, check next year.
			if ( $event_date < $now ) {
				$event_date = $this->suggestions_service->calculate_event_date( $event, $current_year + 1 );
			}

			// Calculate campaign start/end based on event.
			$start_offset = isset( $event['start_offset'] ) ? $event['start_offset'] : 0;
			$duration     = isset( $event['duration_days'] ) ? $event['duration_days'] : 7;

			$campaign_start = strtotime( $start_offset . ' days', $event_date );
			$campaign_end   = strtotime( '+' . $duration . ' days', $campaign_start );

			// Calculate creation window.
			$window = $this->suggestions_service->calculate_suggestion_window( $event, $event_date );

			// Add major event as campaign opportunity.
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

			// Convert times to comparable integers.
			$current_time_int = intval( str_replace( ':', '', $current_time ) );
			$start_time_int   = intval( str_replace( ':', '', $start_time ) );
			$end_time_int     = intval( str_replace( ':', '', $end_time ) );

			// Check if currently active.
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

			// Before start day.
			if ( $current_day < $start_day ) {
				return 'future';
			}

			// After end day.
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
		// Filter campaigns by state.
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
				// Sort by: priority (high first), then recency (most recent first).
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

				// Only show past campaigns from last 30 days.
				$thirty_days_ago = $now - ( 30 * DAY_IN_SECONDS );
				$candidates      = array_filter(
					$candidates,
					function ( $campaign ) use ( $thirty_days_ago ) {
						return $campaign['end_timestamp'] >= $thirty_days_ago;
					}
				);
				break;

			case 'active':
				// Sort by: priority (high first), then in-window check.
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
				// Sort by: priority (high first), then proximity (soonest first).
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

		// Return first (best) candidate.
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
	 * @since  1.0.0
	 * @param  array $campaign Campaign data.
	 * @return int Timestamp.
	 */
	private function get_campaign_start_timestamp( array $campaign ): int {
		if ( ! empty( $campaign['is_major_event'] ) ) {
			return $campaign['campaign_start'];
		} else {
			// Weekly campaigns - calculate start time for current week.
			$current_week_start = strtotime( 'this week Monday 00:00' );
			$schedule           = $campaign['schedule'];
			$start_day          = $schedule['start_day'];
			$start_time         = $schedule['start_time'];

			$days_offset = $start_day - 1; // Monday = 0 offset.
			return strtotime( "+{$days_offset} days {$start_time}", $current_week_start );
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

		// Add campaign suggestion parameter for wizard prefill.
		$campaign_id = $campaign['id'];
		return add_query_arg( 'suggestion', $campaign_id, $base_url );
	}
}
