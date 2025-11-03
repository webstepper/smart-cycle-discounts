<?php
/**
 * Campaign Suggestions Service Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/services/class-campaign-suggestions-service.php
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
 * Campaign Suggestions Service Class
 *
 * Generates intelligent campaign suggestions based on seasonal events
 * with optimized timing windows and lead time calculations.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/services
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Campaign_Suggestions_Service {

	/**
	 * Campaign repository instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Campaign_Repository    $campaign_repository    Campaign repository.
	 */
	private SCD_Campaign_Repository $campaign_repository;

	/**
	 * Logger instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Logger    $logger    Logger instance.
	 */
	private SCD_Logger $logger;

	/**
	 * Initialize the campaign suggestions service.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign_Repository $campaign_repository    Campaign repository.
	 * @param    SCD_Logger              $logger                 Logger instance.
	 */
	public function __construct(
		SCD_Campaign_Repository $campaign_repository,
		SCD_Logger $logger
	) {
		$this->campaign_repository = $campaign_repository;
		$this->logger              = $logger;
	}

	/**
	 * Get campaign suggestions based on upcoming seasonal events.
	 *
	 * Uses intelligent lead time calculations to show suggestions at optimal creation times.
	 *
	 * @since    1.0.0
	 * @return   array    Array of campaign suggestions.
	 */
	public function get_suggestions(): array {
		require_once SCD_INCLUDES_DIR . 'core/campaigns/class-campaign-suggestions-registry.php';
		$all_events        = SCD_Campaign_Suggestions_Registry::get_event_definitions();
		$qualifying_events = array();

		// Use WordPress timezone for user-facing date calculations.
		$current_year = intval( wp_date( 'Y' ) );
		$now          = current_time( 'timestamp' );

		foreach ( $all_events as $event ) {
			// Calculate actual event date for this year.
			$event_date = $this->calculate_event_date( $event, $current_year );

			// If event already passed this year, check next year.
			if ( $event_date < $now ) {
				$event_date = $this->calculate_event_date( $event, $current_year + 1 );
			}

			// Calculate optimal creation window.
			$window = $this->calculate_suggestion_window( $event, $event_date );

			// Check if we're currently in the optimal creation window.
			if ( $window['in_window'] ) {
				$event['event_date'] = $event_date;
				$event['window']     = $window;
				$qualifying_events[] = $event;
			}
		}

		// Smart filtering: Remove weekend_sale if major events are nearby.
		$qualifying_events = $this->filter_weekend_sale_by_major_events( $qualifying_events, $all_events, $current_year );

		// No qualifying events.
		if ( empty( $qualifying_events ) ) {
			return array();
		}

		// Sort by priority (higher first), then by days until optimal.
		usort(
			$qualifying_events,
			function ( $a, $b ) {
				if ( $a['priority'] !== $b['priority'] ) {
					return $b['priority'] - $a['priority'];
				}
				return $a['window']['days_until_optimal'] - $b['window']['days_until_optimal'];
			}
		);

		// Smart display logic: 1 suggestion preferred, multiple only if windows overlap.
		$suggestions = $this->select_suggestions_by_overlap( $qualifying_events );

		// Enrich suggestions with campaign data (if campaigns were created from suggestions).
		$suggestions = $this->enrich_suggestions_with_campaigns( $suggestions );

		// Filter out suggestions where events have already started.
		$suggestions = $this->filter_started_events( $suggestions );

		$this->logger->debug(
			'Campaign suggestions generated',
			array(
				'total_qualifying_events' => count( $qualifying_events ),
				'suggestions_returned'    => count( $suggestions ),
			)
		);

		return $suggestions;
	}

	/**
	 * Get single event definition by ID.
	 *
	 * @since    1.0.0
	 * @param    string $event_id    Event ID (e.g., 'valentines', 'black_friday').
	 * @return   array|null              Event definition or null if not found.
	 */
	public function get_event_by_id( string $event_id ): ?array {
		require_once SCD_INCLUDES_DIR . 'core/campaigns/class-campaign-suggestions-registry.php';
		return SCD_Campaign_Suggestions_Registry::get_event_by_id( $event_id );
	}

	/**
	 * Calculate actual event date for a given year.
	 *
	 * Handles both fixed dates and dynamic date calculations (e.g., "4th Thursday of November").
	 *
	 * @since    1.0.0
	 * @param    array $event Event definition.
	 * @param    int   $year  Year to calculate for.
	 * @return   int          Event timestamp.
	 */
	public function calculate_event_date( array $event, int $year ): int {
		// Check if this is a dynamic date calculation.
		if ( isset( $event['calculate_date'] ) && is_callable( $event['calculate_date'] ) ) {
			return call_user_func( $event['calculate_date'], $year );
		}

		// Default: fixed date calculation.
		return mktime( 0, 0, 0, $event['month'], $event['day'], $year );
	}

	/**
	 * Calculate optimal creation window for an event.
	 *
	 * Determines the ideal timeframe for creating a campaign based on lead time requirements.
	 *
	 * @since    1.0.0
	 * @param    array $event      Event definition.
	 * @param    int   $event_date Event timestamp.
	 * @return   array             Window data with timestamps and status.
	 */
	public function calculate_suggestion_window( array $event, int $event_date ): array {
		$lead_time = $event['lead_time'];

		// Calculate total lead time (base prep + inventory + marketing).
		$total_lead_days  = $lead_time['base_prep'] + $lead_time['inventory'] + $lead_time['marketing'];
		$flexibility_days = $lead_time['flexibility'];

		// Optimal date is when campaign should ideally be created.
		$optimal_date = strtotime( "-{$total_lead_days} days", $event_date );

		// Window is optimal date ± flexibility.
		$window_start = strtotime( "-{$flexibility_days} days", $optimal_date );
		$window_end   = strtotime( "+{$flexibility_days} days", $optimal_date );

		// Use WordPress timezone for user-facing calculations.
		$now                 = current_time( 'timestamp' );
		$in_window           = ( $now >= $window_start && $now <= $window_end );
		$days_until_optimal  = ceil( ( $optimal_date - $now ) / DAY_IN_SECONDS );
		$days_until_event    = ceil( ( $event_date - $now ) / DAY_IN_SECONDS );
		$days_left_in_window = $in_window ? ceil( ( $window_end - $now ) / DAY_IN_SECONDS ) : 0;

		return array(
			'optimal_date'        => $optimal_date,
			'window_start'        => $window_start,
			'window_end'          => $window_end,
			'in_window'           => $in_window,
			'days_until_optimal'  => abs( $days_until_optimal ),
			'days_until_event'    => $days_until_event,
			'days_left_in_window' => $days_left_in_window,
			'total_lead_days'     => $total_lead_days,
		);
	}

	/**
	 * Format event data for display.
	 *
	 * Transforms internal event data structure into user-friendly format.
	 *
	 * @since    1.0.0
	 * @param    array $event Event with window data.
	 * @return   array        Formatted suggestion.
	 */
	public function format_suggestion( array $event ): array {
		$start_date = strtotime( $event['start_offset'] . ' days', $event['event_date'] );
		$end_date   = strtotime( '+' . $event['duration_days'] . ' days', $start_date );

		// Format discount range.
		$discount       = $event['suggested_discount'];
		$discount_range = $discount['min'] . '-' . $discount['max'] . '%';

		$formatted = array(
			'id'                  => $event['id'],
			'name'                => $event['name'],
			'icon'                => $event['icon'],
			'category'            => $event['category'],
			'start_date'          => wp_date( 'Y-m-d', $start_date ),
			'end_date'            => wp_date( 'Y-m-d', $end_date ),
			'days_until'          => $event['window']['days_until_event'],
			'days_left_in_window' => $event['window']['days_left_in_window'],
			'suggested_discount'  => $discount_range,
			'optimal_discount'    => $discount['optimal'],
			'description'         => $event['description'],
			'timing_message'      => $this->get_timing_message( $event['window'] ),
		);

		// Add recommendations if available.
		if ( isset( $event['recommendations'] ) && ! empty( $event['recommendations'] ) ) {
			$formatted['recommendations'] = $event['recommendations'];
		}

		// Add rich data if available - randomly select 1 from each.
		if ( isset( $event['statistics'] ) && ! empty( $event['statistics'] ) ) {
			$stat_keys                     = array_keys( $event['statistics'] );
			$random_stat_key               = $stat_keys[ array_rand( $stat_keys ) ];
			$formatted['random_statistic'] = array(
				'label' => $random_stat_key,
				'value' => $event['statistics'][ $random_stat_key ],
			);
			$formatted['statistics']       = $event['statistics'];
		}

		if ( isset( $event['tips'] ) && ! empty( $event['tips'] ) ) {
			$formatted['random_tip'] = $event['tips'][ array_rand( $event['tips'] ) ];
			$formatted['tips']       = $event['tips'];
		}

		if ( isset( $event['best_practices'] ) && ! empty( $event['best_practices'] ) ) {
			$formatted['random_best_practice'] = $event['best_practices'][ array_rand( $event['best_practices'] ) ];
			$formatted['best_practices']       = $event['best_practices'];
		}

		return $formatted;
	}

	/**
	 * Enrich suggestions with campaign data.
	 *
	 * Checks if campaigns were created from suggestions and adds campaign data to suggestions.
	 *
	 * @since    1.0.0
	 * @param    array $suggestions    Suggestions array.
	 * @return   array                    Enriched suggestions with campaign data.
	 */
	private function enrich_suggestions_with_campaigns( array $suggestions ): array {
		if ( empty( $suggestions ) ) {
			return $suggestions;
		}

		foreach ( $suggestions as &$suggestion ) {
			// Query campaigns created from this suggestion (most recent first).
			$campaigns = $this->campaign_repository->find_by_metadata(
				'from_suggestion',
				$suggestion['id'],
				array(
					'order_by' => 'created_at',
					'order'    => 'DESC',
					'limit'    => 1,
				)
			);

			if ( ! empty( $campaigns ) && ! is_wp_error( $campaigns ) && is_array( $campaigns ) ) {
				// Get the most recent campaign.
				$campaign = $campaigns[0];

				// Verify it's a valid campaign object.
				if ( $campaign && is_object( $campaign ) && method_exists( $campaign, 'get_id' ) ) {
					// Add campaign data to suggestion.
					$suggestion['has_campaign'] = true;
					$suggestion['campaign']     = array(
						'id'     => $campaign->get_id(),
						'name'   => $campaign->get_name(),
						'status' => $campaign->get_status(),
						'url'    => admin_url( 'admin.php?page=scd-campaigns&action=edit&id=' . $campaign->get_id() ),
					);
				} else {
					$suggestion['has_campaign'] = false;
				}
			} else {
				$suggestion['has_campaign'] = false;
			}
		}

		return $suggestions;
	}

	/**
	 * Filter out suggestions where events have already started.
	 *
	 * Once an event starts, the suggestion is no longer actionable.
	 *
	 * @since    1.0.0
	 * @param    array $suggestions    Suggestions array.
	 * @return   array                    Filtered suggestions.
	 */
	private function filter_started_events( array $suggestions ): array {
		if ( empty( $suggestions ) ) {
			return $suggestions;
		}

		$now = current_time( 'timestamp' );

		$filtered = array_filter(
			$suggestions,
			function ( $suggestion ) use ( $now ) {
				// Keep suggestions where event hasn't started yet.
				if ( isset( $suggestion['start_date'] ) ) {
					$event_start = strtotime( $suggestion['start_date'] );
					return $event_start > $now;
				}
				// Keep suggestions without start_date (shouldn't happen, but safe).
				return true;
			}
		);

		// Re-index array to avoid gaps in keys.
		return array_values( $filtered );
	}

	/**
	 * Check if two event windows overlap.
	 *
	 * @since    1.0.0
	 * @param    array $window1 First window data.
	 * @param    array $window2 Second window data.
	 * @return   bool           True if windows overlap.
	 */
	private function windows_overlap( array $window1, array $window2 ): bool {
		return ! ( $window1['window_end'] < $window2['window_start'] || $window2['window_end'] < $window1['window_start'] );
	}

	/**
	 * Select suggestions based on window overlap logic.
	 *
	 * Shows 1 suggestion by default, multiple only if their creation windows overlap.
	 *
	 * @since    1.0.0
	 * @param    array $qualifying_events Events that are in their creation windows.
	 * @return   array                    Selected suggestions to display.
	 */
	private function select_suggestions_by_overlap( array $qualifying_events ): array {
		if ( empty( $qualifying_events ) ) {
			return array();
		}

		// If only one event qualifies, show it.
		if ( 1 === count( $qualifying_events ) ) {
			return array( $this->format_suggestion( $qualifying_events[0] ) );
		}

		// Multiple events qualify - check for overlaps.
		$suggestions = array( $qualifying_events[0] );

		foreach ( array_slice( $qualifying_events, 1 ) as $event ) {
			// Check if this event's window overlaps with any already selected.
			$has_overlap = false;
			foreach ( $suggestions as $existing ) {
				if ( $this->windows_overlap( $event['window'], $existing['window'] ) ) {
					$has_overlap = true;
					break;
				}
			}

			// Only add if windows overlap.
			if ( $has_overlap ) {
				$suggestions[] = $event;

				// Maximum 3 suggestions.
				if ( count( $suggestions ) >= 3 ) {
					break;
				}
			}
		}

		// Format all selected suggestions.
		return array_map( array( $this, 'format_suggestion' ), $suggestions );
	}

	/**
	 * Get timing explanation message.
	 *
	 * Generates human-readable urgency message based on days remaining.
	 *
	 * @since    1.0.0
	 * @param    array $window Window data.
	 * @return   string        Human-readable timing message.
	 */
	private function get_timing_message( array $window ): string {
		$days_left        = $window['days_left_in_window'];
		$days_until_event = $window['days_until_event'];

		// Show urgency based on days left.
		if ( $days_left <= 3 ) {
			return sprintf(
				/* translators: %d: days left in window */
				__( 'Urgent: Only %d days left in optimal creation window!', 'smart-cycle-discounts' ),
				$days_left
			);
		} elseif ( $days_left <= 7 ) {
			return sprintf(
				/* translators: %d: days left in window */
				__( 'Create soon: %d days left in optimal window', 'smart-cycle-discounts' ),
				$days_left
			);
		} else {
			return sprintf(
				/* translators: 1: days until event, 2: days left to create */
				__( 'Perfect timing: %1$d days until event, %2$d days left to create', 'smart-cycle-discounts' ),
				$days_until_event,
				$days_left
			);
		}
	}

	/**
	 * Filter out weekend_sale if major events are within 2 weeks.
	 *
	 * Prevents showing weekend sale suggestions when major retail events are nearby.
	 *
	 * @since    1.0.0
	 * @param    array $qualifying_events Events that qualified for display.
	 * @param    array $all_events        All event definitions.
	 * @param    int   $current_year      Current year for date calculations.
	 * @return   array                    Filtered qualifying events.
	 */
	private function filter_weekend_sale_by_major_events( array $qualifying_events, array $all_events, int $current_year ): array {
		// Check if weekend_sale is in qualifying events.
		$has_weekend_sale   = false;
		$weekend_sale_index = -1;
		$weekend_sale_date  = 0;

		foreach ( $qualifying_events as $index => $event ) {
			if ( 'weekend_sale' === $event['id'] ) {
				$has_weekend_sale   = true;
				$weekend_sale_index = $index;
				$weekend_sale_date  = $event['event_date'];
				break;
			}
		}

		// No weekend sale in qualifying events - nothing to filter.
		if ( ! $has_weekend_sale ) {
			return $qualifying_events;
		}

		// Check all events for major events within 2 weeks of the weekend.
		$two_weeks_seconds = 14 * DAY_IN_SECONDS;
		$now               = current_time( 'timestamp' );

		foreach ( $all_events as $event ) {
			// Skip weekend_sale itself.
			if ( 'weekend_sale' === $event['id'] ) {
				continue;
			}

			// Only check major events.
			if ( 'major' !== $event['category'] ) {
				continue;
			}

			// Calculate event date.
			$event_date = $this->calculate_event_date( $event, $current_year );

			// If event already passed this year, check next year.
			if ( $event_date < $now ) {
				$event_date = $this->calculate_event_date( $event, $current_year + 1 );
			}

			// Calculate time difference between weekend and major event.
			$time_diff = abs( $weekend_sale_date - $event_date );

			// If major event is within 2 weeks, remove weekend_sale.
			if ( $time_diff <= $two_weeks_seconds ) {
				unset( $qualifying_events[ $weekend_sale_index ] );
				return array_values( $qualifying_events );
			}
		}

		// No major events nearby - weekend sale is safe to show.
		return $qualifying_events;
	}
}
