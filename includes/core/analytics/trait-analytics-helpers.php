<?php
/**
 * Analytics Helpers Trait
 *
 * Provides shared helper methods for analytics classes.
 *
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/analytics
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
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
trait SCD_Analytics_Helpers {

	/**
	 * Get date range conditions for analytics queries.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $date_range    Date range identifier (24hours, 7days, 30days, 90days).
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
			default:
				$start_date = date( 'Y-m-d H:i:s', strtotime( '-7 days' ) );
		}

		return array(
			'start_date' => $start_date,
			'end_date'   => $end_date,
		);
	}
}
