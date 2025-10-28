<?php
/**
 * DateTime Splitter
 *
 * Converts stored datetime back to separate date/time components for editing.
 * Implements the Value Object pattern for datetime decomposition.
 *
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/utilities
 */

declare(strict_types=1);


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * DateTime Splitter Class
 *
 * Value Object for splitting DateTime objects into separate date and time components.
 * Used when loading campaigns for editing or displaying datetime in separate fields.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/utilities
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_DateTime_Splitter {

	/**
	 * Split UTC datetime for editing form.
	 *
	 * Converts a UTC datetime to the target timezone and returns
	 * separate date and time components for form population.
	 *
	 * @since    1.0.0
	 * @param    DateTime|null $utc_datetime       DateTime object in UTC.
	 * @param    string        $target_timezone    Target timezone identifier.
	 * @return   array                                Array with 'date' and 'time' keys.
	 */
	public static function for_editing( ?DateTime $utc_datetime, string $target_timezone ): array {
		if ( null === $utc_datetime ) {
			return array(
				'date' => null,
				'time' => null,
			);
		}

		// Convert from UTC to target timezone
		$local_datetime = clone $utc_datetime;
		$local_datetime->setTimezone( new DateTimeZone( $target_timezone ) );

		return array(
			'date' => $local_datetime->format( 'Y-m-d' ),
			'time' => $local_datetime->format( 'H:i' ),
		);
	}

	/**
	 * Get datetime parts for display.
	 *
	 * Converts a UTC datetime to the target timezone and returns
	 * formatted strings for display purposes.
	 *
	 * @since    1.0.0
	 * @param    DateTime|null $utc_datetime       DateTime object in UTC.
	 * @param    string        $target_timezone    Target timezone identifier.
	 * @param    string        $date_format        PHP date format for date (default: 'F j, Y').
	 * @param    string        $time_format        PHP date format for time (default: 'g:i A').
	 * @return   array                                Array with 'date', 'time', and 'datetime' keys.
	 */
	public static function for_display(
		?DateTime $utc_datetime,
		string $target_timezone,
		string $date_format = 'F j, Y',
		string $time_format = 'g:i A'
	): array {
		if ( null === $utc_datetime ) {
			return array(
				'date'     => '',
				'time'     => '',
				'datetime' => '',
			);
		}

		$local_datetime = clone $utc_datetime;
		$local_datetime->setTimezone( new DateTimeZone( $target_timezone ) );

		// Use wp_date for localization
		$timestamp = $local_datetime->getTimestamp();

		return array(
			'date'     => wp_date( $date_format, $timestamp, new DateTimeZone( $target_timezone ) ),
			'time'     => wp_date( $time_format, $timestamp, new DateTimeZone( $target_timezone ) ),
			'datetime' => wp_date( $date_format . ' ' . $time_format, $timestamp, new DateTimeZone( $target_timezone ) ),
		);
	}

	/**
	 * Split for JSON API response.
	 *
	 * Returns both ISO 8601 format and separate date/time components
	 * for API consumers who may prefer separate values.
	 *
	 * @since    1.0.0
	 * @param    DateTime|null $utc_datetime       DateTime object in UTC.
	 * @param    string        $target_timezone    Target timezone identifier.
	 * @return   array                                Array with ISO and split values.
	 */
	public static function for_api( ?DateTime $utc_datetime, string $target_timezone ): array {
		if ( null === $utc_datetime ) {
			return array(
				'iso'       => null,
				'date'      => null,
				'time'      => null,
				'datetime'  => null,
				'timestamp' => null,
			);
		}

		$local_datetime = clone $utc_datetime;
		$local_datetime->setTimezone( new DateTimeZone( $target_timezone ) );

		return array(
			'iso'       => $utc_datetime->format( 'c' ),  // ISO 8601 in UTC
			'date'      => $local_datetime->format( 'Y-m-d' ),
			'time'      => $local_datetime->format( 'H:i' ),
			'datetime'  => $local_datetime->format( 'Y-m-d H:i:s' ),
			'timestamp' => $utc_datetime->getTimestamp(),
		);
	}
}
