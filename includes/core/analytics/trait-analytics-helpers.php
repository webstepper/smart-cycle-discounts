<?php
/**
 * Analytics Helpers Trait
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/analytics/trait-analytics-helpers.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Analytics Helpers Trait
 *
 * Shared methods for date range calculations and analytics utilities.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/analytics
 * @author     Webstepper <contact@webstepper.io>
 */
trait SCD_Analytics_Helpers {

	/**
	 * Get date range conditions for analytics queries.
	 *
	 * Supports current and previous period ranges for trend analysis.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $date_range    Date range identifier (24hours, 7days, 30days, 90days, previous_*).
	 * @return   array                    Array with start_date and end_date.
	 */
	private function get_date_range_conditions( $date_range ) {
		$end_date = current_time( 'mysql' );

		switch ( $date_range ) {
			case '24hours':
				$start_date = date( 'Y-m-d H:i:s', strtotime( '-24 hours' ) );
				break;
			case '7days':
				$start_date = date( 'Y-m-d H:i:s', strtotime( '-7 days' ) );
				break;
			case '30days':
				$start_date = date( 'Y-m-d H:i:s', strtotime( '-30 days' ) );
				break;
			case '90days':
				$start_date = date( 'Y-m-d H:i:s', strtotime( '-90 days' ) );
				break;

			// Previous periods for trend comparison
			case 'previous_24hours':
				$end_date   = date( 'Y-m-d H:i:s', strtotime( '-24 hours' ) );
				$start_date = date( 'Y-m-d H:i:s', strtotime( '-48 hours' ) );
				break;
			case 'previous_7days':
				$end_date   = date( 'Y-m-d H:i:s', strtotime( '-7 days' ) );
				$start_date = date( 'Y-m-d H:i:s', strtotime( '-14 days' ) );
				break;
			case 'previous_30days':
				$end_date   = date( 'Y-m-d H:i:s', strtotime( '-30 days' ) );
				$start_date = date( 'Y-m-d H:i:s', strtotime( '-60 days' ) );
				break;
			case 'previous_90days':
				$end_date   = date( 'Y-m-d H:i:s', strtotime( '-90 days' ) );
				$start_date = date( 'Y-m-d H:i:s', strtotime( '-180 days' ) );
				break;

			default:
				$start_date = date( 'Y-m-d H:i:s', strtotime( '-7 days' ) );
		}

		return array(
			'start_date' => $start_date,
			'end_date'   => $end_date,
		);
	}
}
