<?php
/**
 * Campaign Schedule Validator Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/utilities/class-campaign-schedule-validator.php
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Campaign Schedule Validator Class
 *
 * Provides schedule overlap checking for campaign validation.
 *
 * @since 1.0.0
 */
class SCD_Campaign_Schedule_Validator {

	/**
	 * Check for schedule overlap with active campaigns.
	 *
	 * @since  1.0.0
	 * @param  int  $start_timestamp    Start timestamp to check.
	 * @param  int  $end_timestamp      End timestamp to check.
	 * @param  bool $include_details    Whether to include campaign details in results.
	 * @return array                     Array of overlapping campaigns.
	 */
	public static function check_schedule_overlap( $start_timestamp, $end_timestamp, $include_details = true ) {
		if ( ! class_exists( 'Smart_Cycle_Discounts' ) ) {
			return array();
		}

		$campaign_repository = Smart_Cycle_Discounts::get_service( 'campaign_repository' );
		if ( ! $campaign_repository ) {
			return array();
		}

		$active_campaigns = $campaign_repository->get_active_campaigns();
		if ( empty( $active_campaigns ) ) {
			return array();
		}

		$overlapping = array();

		foreach ( $active_campaigns as $campaign ) {
			$campaign_starts_at = $campaign->get_starts_at();
			$campaign_start     = $campaign_starts_at ? $campaign_starts_at->getTimestamp() : 0;
			$campaign_ends_at   = $campaign->get_ends_at();
			$campaign_end       = $campaign_ends_at ? $campaign_ends_at->getTimestamp() : 0;

			$overlaps = self::check_date_overlap(
				$start_timestamp,
				$end_timestamp,
				$campaign_start,
				$campaign_end
			);

			if ( $overlaps ) {
				if ( $include_details ) {
					$overlapping[] = array(
						'id'    => $campaign->get_id(),
						'name'  => $campaign->get_name(),
						'start' => $campaign_starts_at ? $campaign_starts_at->format( 'Y-m-d H:i:s' ) : '',
						'end'   => $campaign_ends_at ? $campaign_ends_at->format( 'Y-m-d H:i:s' ) : __( 'No end date', 'smart-cycle-discounts' ),
					);
				} else {
					$overlapping[] = array(
						'id'   => $campaign->get_id(),
						'name' => $campaign->get_name(),
					);
				}
			}
		}

		return $overlapping;
	}

	/**
	 * Check if two date ranges overlap.
	 *
	 * @since  1.0.0
	 * @param  int $start1    Start timestamp of first range.
	 * @param  int $end1      End timestamp of first range.
	 * @param  int $start2    Start timestamp of second range.
	 * @param  int $end2      End timestamp of second range.
	 * @return bool           True if ranges overlap.
	 */
	public static function check_date_overlap( $start1, $end1, $start2, $end2 ) {
		// If either range has no end date, treat as indefinite.
		if ( 0 === $end1 ) {
			$end1 = PHP_INT_MAX;
		}
		if ( 0 === $end2 ) {
			$end2 = PHP_INT_MAX;
		}

		// Ranges overlap if start1 is before end2 AND start2 is before end1.
		return ( $start1 < $end2 ) && ( $start2 < $end1 );
	}
}
