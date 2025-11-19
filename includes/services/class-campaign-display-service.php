<?php
/**
 * Campaign Display Service Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/services/class-campaign-display-service.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Campaign Display Service Class
 *
 * Responsible for fetching and preparing campaigns for display
 * with pre-computed fields like time remaining, urgency flags, and status formatting.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/services
 * @author     Webstepper <contact@webstepper.io>
 */
class SCD_Campaign_Display_Service {

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
	 * Initialize the campaign display service.
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
	 * Get recent campaigns with display data.
	 *
	 * Replaces direct DB query in view.
	 * Returns campaigns sorted by urgency with pre-computed display data.
	 *
	 * @since    1.0.0
	 * @param    int $limit    Number of campaigns to retrieve.
	 * @return   array            Recent campaigns prepared for display.
	 */
	public function get_recent_campaigns( int $limit = 5 ): array {
		// Use repository layer with find_by() for multiple statuses.
		$campaigns = $this->campaign_repository->find_by(
			array(
				'status' => array( 'active', 'scheduled', 'paused', 'draft' ),
			),
			array(
				'order_by'        => 'created_at',
				'order_direction' => 'DESC',
				'limit'           => $limit * 2, // Get more to allow urgency sorting.
			)
		);

		$this->logger->debug(
			'Campaign Display Service: get_recent_campaigns()',
			array(
				'campaigns_found' => count( $campaigns ),
				'limit_requested' => $limit,
			)
		);

		// Pre-compute all display data.
		$prepared = array();
		foreach ( $campaigns as $campaign ) {
			// Convert Campaign object to array.
			$campaign_array = is_object( $campaign ) ? $campaign->to_array() : $campaign;
			$prepared[]     = $this->prepare_campaign_for_display( $campaign_array );
		}

		// Sort by urgency and days remaining - avoid closure for serialization compatibility.
		usort(
			$prepared,
			array( $this, 'compare_campaigns_by_urgency_and_days' )
		);

		return array_slice( $prepared, 0, $limit );
	}

	/**
	 * Prepare campaign for display with all computed fields.
	 *
	 * Pre-computes all display data so view template can be "dumb".
	 *
	 * @since    1.0.0
	 * @param    array $campaign    Campaign data array.
	 * @return   array                Campaign with all display fields added.
	 */
	public function prepare_campaign_for_display( array $campaign ): array {
		$now = new DateTime( 'now', new DateTimeZone( 'UTC' ) );

		// Time remaining calculations.
		$time_data = $this->calculate_time_data( $campaign, $now );

		// Urgency checks.
		$urgency_data = $this->calculate_urgency_data( $campaign, $now );

		// Status formatting.
		$status_data = $this->format_status_data( $campaign );

		// Merge everything.
		return array_merge( $campaign, $time_data, $urgency_data, $status_data );
	}

	/**
	 * Calculate all time-related display data.
	 *
	 * @since    1.0.0
	 * @param    array    $campaign    Campaign data.
	 * @param    DateTime $now         Current datetime object.
	 * @return   array                    Time data array.
	 */
	private function calculate_time_data( array $campaign, DateTime $now ): array {
		$data = array(
			'time_remaining_text'   => '',
			'time_until_start_text' => '',
			'days_until_end'        => null,
			'days_until_start'      => null,
		);

		// Active campaign - calculate time until end.
		if ( 'active' === $campaign['status'] && ! empty( $campaign['ends_at'] ) ) {
			$end_date     = new DateTime( $campaign['ends_at'], new DateTimeZone( 'UTC' ) );
			$diff_seconds = $end_date->getTimestamp() - $now->getTimestamp();

			if ( $diff_seconds > 0 ) {
				$data['days_until_end']      = floor( $diff_seconds / DAY_IN_SECONDS );
				$data['time_remaining_text'] = $this->format_time_remaining( $diff_seconds );
			}
		}

		// Scheduled campaign - calculate time until start.
		if ( 'scheduled' === $campaign['status'] && ! empty( $campaign['starts_at'] ) ) {
			$start_date   = new DateTime( $campaign['starts_at'], new DateTimeZone( 'UTC' ) );
			$diff_seconds = $start_date->getTimestamp() - $now->getTimestamp();

			if ( $diff_seconds > 0 ) {
				$data['days_until_start']      = floor( $diff_seconds / DAY_IN_SECONDS );
				$data['time_until_start_text'] = $this->format_time_until_start( $diff_seconds );
			}
		}

		return $data;
	}

	/**
	 * Format time remaining in human-readable format.
	 *
	 * @since    1.0.0
	 * @param    int $seconds    Seconds remaining.
	 * @return   string             Formatted time string.
	 */
	private function format_time_remaining( int $seconds ): string {
		if ( $seconds < DAY_IN_SECONDS ) {
			// Less than 1 day - show hours and minutes.
			$hours   = floor( $seconds / HOUR_IN_SECONDS );
			$minutes = floor( ( $seconds % HOUR_IN_SECONDS ) / MINUTE_IN_SECONDS );

			if ( $hours > 0 ) {
				return sprintf(
					/* translators: 1: hours, 2: minutes */
					_n( 'Ends in %1$d hour %2$d min', 'Ends in %1$d hours %2$d min', $hours, 'smart-cycle-discounts' ),
					$hours,
					$minutes
				);
			} else {
				return sprintf(
					/* translators: %d: minutes */
					_n( 'Ends in %d minute', 'Ends in %d minutes', $minutes, 'smart-cycle-discounts' ),
					$minutes
				);
			}
		} else {
			// Show days.
			$days = floor( $seconds / DAY_IN_SECONDS );
			return sprintf(
				/* translators: %d: days */
				_n( 'Ends in %d day', 'Ends in %d days', $days, 'smart-cycle-discounts' ),
				$days
			);
		}
	}

	/**
	 * Format time until start in human-readable format.
	 *
	 * @since    1.0.0
	 * @param    int $seconds    Seconds until start.
	 * @return   string             Formatted time string.
	 */
	private function format_time_until_start( int $seconds ): string {
		if ( $seconds < DAY_IN_SECONDS ) {
			// Less than 1 day - show hours.
			$hours = floor( $seconds / HOUR_IN_SECONDS );
			return sprintf(
				/* translators: %d: hours */
				_n( 'Starts in %d hour', 'Starts in %d hours', $hours, 'smart-cycle-discounts' ),
				$hours
			);
		} else {
			// Show days.
			$days = floor( $seconds / DAY_IN_SECONDS );
			return sprintf(
				/* translators: %d: days */
				_n( 'Starts in %d day', 'Starts in %d days', $days, 'smart-cycle-discounts' ),
				$days
			);
		}
	}

	/**
	 * Calculate urgency flags.
	 *
	 * Checks if campaigns are ending or starting soon (within 7 days).
	 *
	 * @since    1.0.0
	 * @param    array    $campaign    Campaign data.
	 * @param    DateTime $now         Current datetime object.
	 * @return   array                    Urgency data array.
	 */
	private function calculate_urgency_data( array $campaign, DateTime $now ): array {
		$is_ending_soon   = false;
		$is_starting_soon = false;

		if ( 'active' === $campaign['status'] && ! empty( $campaign['ends_at'] ) ) {
			$end_date       = new DateTime( $campaign['ends_at'], new DateTimeZone( 'UTC' ) );
			$diff_days      = ( $end_date->getTimestamp() - $now->getTimestamp() ) / DAY_IN_SECONDS;
			$is_ending_soon = $diff_days >= 0 && $diff_days <= 7;
		}

		if ( 'scheduled' === $campaign['status'] && ! empty( $campaign['starts_at'] ) ) {
			$start_date       = new DateTime( $campaign['starts_at'], new DateTimeZone( 'UTC' ) );
			$diff_days        = ( $start_date->getTimestamp() - $now->getTimestamp() ) / DAY_IN_SECONDS;
			$is_starting_soon = $diff_days >= 0 && $diff_days <= 7;
		}

		return array(
			'is_ending_soon'   => $is_ending_soon,
			'is_starting_soon' => $is_starting_soon,
			'is_urgent'        => $is_ending_soon || $is_starting_soon,
		);
	}

	/**
	 * Format status data for display.
	 *
	 * Generates CSS classes, labels, and icons for campaign status.
	 *
	 * @since    1.0.0
	 * @param    array $campaign    Campaign data.
	 * @return   array                 Status data array.
	 */
	private function format_status_data( array $campaign ): array {
		$status = $campaign['status'];

		return array(
			'status_badge_class' => 'scd-status-' . $status,
			'status_label'       => ucfirst( $status ),
			'status_icon'        => $this->get_status_icon( $status ),
		);
	}

	/**
	 * Get dashicon for status.
	 *
	 * Maps campaign status to WordPress dashicon.
	 *
	 * @since    1.0.0
	 * @param    string $status    Campaign status.
	 * @return   string               Dashicon name.
	 */
	private function get_status_icon( string $status ): string {
		$icons = array(
			'active'    => 'yes-alt',
			'scheduled' => 'calendar-alt',
			'paused'    => 'controls-pause',
			'draft'     => 'edit',
			'expired'   => 'clock',
		);

		return $icons[ $status ] ?? 'admin-generic';
	}

	/**
	 * Compare campaigns by urgency and days remaining.
	 * Used instead of closure for serialization compatibility.
	 *
	 * @since  1.0.0
	 * @param  array $a First campaign.
	 * @param  array $b Second campaign.
	 * @return int Comparison result.
	 */
	private function compare_campaigns_by_urgency_and_days( array $a, array $b ): int {
		// Urgent campaigns first.
		if ( $a['is_urgent'] !== $b['is_urgent'] ) {
			return $b['is_urgent'] <=> $a['is_urgent'];
		}

		// Then by days remaining (ascending).
		if ( isset( $a['days_until_end'], $b['days_until_end'] ) ) {
			return $a['days_until_end'] <=> $b['days_until_end'];
		}

		// Fall back to created date.
		return 0;
	}
}
