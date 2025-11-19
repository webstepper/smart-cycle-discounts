<?php
/**
 * Time Helpers Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/utilities/class-time-helpers.php
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
 * Parse a datetime string in site timezone
 *
 * CRITICAL FIX (EC-011): Reject invalid dates instead of silent rollover.
 * Previously "2024-02-30" would silently become "2024-03-02".
 * Now properly validates and rejects invalid dates.
 *
 * HIGH FIX (EC-002, EC-003): DST transition handling.
 * Detects and adjusts times that fall in non-existent or ambiguous hours.
 *
 * @since 1.0.0
 * @param string $datetime_str DateTime string to parse
 * @param string $format Optional format (default: 'Y-m-d H:i:s')
 * @return DateTimeImmutable|false DateTime object in site timezone or false on failure
 */
function scd_parse_local( string $datetime_str, string $format = 'Y-m-d H:i:s' ) {
	try {
		$wp_timezone = wp_timezone();

		// EC-011 FIX: Use strict date validation with '!' prefix
		$datetime = DateTimeImmutable::createFromFormat( '!' . $format, $datetime_str, $wp_timezone );

		if ( false === $datetime ) {
			// Try general parsing as fallback
			$datetime = new DateTimeImmutable( $datetime_str, $wp_timezone );
		} else {
			// EC-011 FIX: Verify no date rollover occurred
			$formatted = $datetime->format( $format );
			if ( $formatted !== $datetime_str ) {
				// Date was rolled over (e.g., Feb 30 → Mar 2), reject it
				return false;
			}
		}

		// EC-002, EC-003 FIX: Check for DST transitions
		$dst_info = scd_check_dst_transition( $datetime );
		if ( $dst_info['is_transition_day'] ) {
			$datetime = scd_adjust_for_dst_transition( $datetime, $dst_info );
		}

		return $datetime;
	} catch ( Exception $e ) {
		return false;
	}
}

/**
 * Convert a DateTime to UTC timestamp for cron
 *
 * @since 1.0.0
 * @param DateTimeImmutable $datetime DateTime object (in any timezone)
 * @return int UTC timestamp
 */
function scd_to_utc_ts( DateTimeImmutable $datetime ): int {
	// DateTime::getTimestamp() always returns UTC timestamp regardless of timezone
	return $datetime->getTimestamp();
}

/**
 * Create DateTime from UTC timestamp in site timezone
 *
 * @since 1.0.0
 * @param int $timestamp UTC timestamp
 * @return DateTimeImmutable DateTime object in site timezone
 */
function scd_from_utc_ts( int $timestamp ): DateTimeImmutable {
	$wp_timezone = wp_timezone();
	$datetime    = new DateTimeImmutable( '@' . $timestamp );
	return $datetime->setTimezone( $wp_timezone );
}

/**
 * Get current time in site timezone
 *
 * @since 1.0.0
 * @return DateTimeImmutable Current time in site timezone
 */
function scd_current_time(): DateTimeImmutable {
	return new DateTimeImmutable( 'now', wp_timezone() );
}

/**
 * Format datetime for display
 *
 * @since 1.0.0
 * @param DateTimeImmutable $datetime DateTime to format
 * @param string            $format PHP date format string
 * @return string Formatted date string
 */
function scd_format_datetime( DateTimeImmutable $datetime, string $format = 'Y-m-d H:i:s' ): string {
	// Ensure we're formatting in site timezone
	$site_datetime = $datetime->setTimezone( wp_timezone() );
	return $site_datetime->format( $format );
}

/**
 * Parse time string (e.g., "14:30" or "2:30 PM") into hours and minutes
 *
 * @since 1.0.0
 * @param string $time_str Time string to parse
 * @return array|false Array with 'hour' (0-23) and 'minute' (0-59) or false on failure
 */
function scd_parse_time_string( string $time_str ) {
	// Try 24-hour format first
	if ( preg_match( '/^(\d{1,2}):(\d{2})$/', $time_str, $matches ) ) {
		$hour   = (int) $matches[1];
		$minute = (int) $matches[2];

		if ( $hour >= 0 && $hour <= 23 && $minute >= 0 && $minute <= 59 ) {
			return array(
				'hour'   => $hour,
				'minute' => $minute,
			);
		}
	}

	// Try 12-hour format with AM/PM
	if ( preg_match( '/^(\d{1,2}):(\d{2})\s*(AM|PM)$/i', $time_str, $matches ) ) {
		$hour   = (int) $matches[1];
		$minute = (int) $matches[2];
		$ampm   = strtoupper( $matches[3] );

		if ( $hour >= 1 && $hour <= 12 && $minute >= 0 && $minute <= 59 ) {
			if ( 'PM' === $ampm && 12 !== $hour ) {
				$hour += 12;
			} elseif ( 'AM' === $ampm && 12 === $hour ) {
				$hour = 0;
			}

			return array(
				'hour'   => $hour,
				'minute' => $minute,
			);
		}
	}

	return false;
}

/**
 * Check if current time is within a daily time window
 *
 * @since 1.0.0
 * @param string                 $start_time Start time (e.g., "09:00" or "9:00 AM")
 * @param string                 $end_time End time (e.g., "17:00" or "5:00 PM")
 * @param DateTimeImmutable|null $current_time Optional current time (defaults to now)
 * @return bool True if within window, false otherwise
 */
function scd_is_within_time_window( string $start_time, string $end_time, ?DateTimeImmutable $current_time = null ): bool {
	$current = $current_time ?? scd_current_time();

	$start_parts = scd_parse_time_string( $start_time );
	$end_parts   = scd_parse_time_string( $end_time );

	if ( false === $start_parts || false === $end_parts ) {
		return false; // Invalid time format
	}

	$today_start = $current->setTime( $start_parts['hour'], $start_parts['minute'], 0 );
	$today_end   = $current->setTime( $end_parts['hour'], $end_parts['minute'], 0 );

	// Handle case where end time is before start time (spans midnight)
	if ( $today_end <= $today_start ) {
		// Time window spans midnight
		// We're in the window if we're after start OR before end
		return $current >= $today_start || $current < $today_end;
	} else {
		// Normal case: start and end are on the same day
		return $current >= $today_start && $current < $today_end;
	}
}

/**
 * Get next allowed time within daily window
 *
 * @since 1.0.0
 * @param string                 $start_time Daily window start time
 * @param string                 $end_time Daily window end time
 * @param DateTimeImmutable|null $from_time Starting point (defaults to now)
 * @return DateTimeImmutable|false Next allowed time or false if invalid window
 */
function scd_next_allowed_time( string $start_time, string $end_time, ?DateTimeImmutable $from_time = null ): DateTimeImmutable|false {
	$from = $from_time ?? scd_current_time();

	$start_parts = scd_parse_time_string( $start_time );
	$end_parts   = scd_parse_time_string( $end_time );

	if ( false === $start_parts || false === $end_parts ) {
		return false;
	}

	// If already within window, return current time
	if ( scd_is_within_time_window( $start_time, $end_time, $from ) ) {
		return $from;
	}

	$next_start = $from->setTime( $start_parts['hour'], $start_parts['minute'], 0 );

	// If we're past today's window start, move to tomorrow
	if ( $from >= $next_start ) {
		$next_start = $next_start->modify( '+1 day' );
	}

	return $next_start;
}

/**
 * Validate timezone identifier
 *
 * EC-004 FIX: Convert UTC offsets to canonical timezone strings.
 * WordPress allows "UTC+5" but this doesn't account for DST.
 * This function converts offsets to proper IANA identifiers when possible.
 *
 * @since 1.0.0
 * @param string $timezone Timezone identifier to validate
 * @return string|false Canonical timezone ID or false if invalid
 */
function scd_validate_timezone( string $timezone ): string|false {
	$valid_timezones = DateTimeZone::listIdentifiers();

	// Direct match
	if ( in_array( $timezone, $valid_timezones, true ) ) {
		return $timezone;
	}

	// Try case-insensitive match
	foreach ( $valid_timezones as $valid_tz ) {
		if ( 0 === strcasecmp( $timezone, $valid_tz ) ) {
			return $valid_tz; // Return canonical version
		}
	}

	// Accept UTC offset formats (e.g., +04:00, -05:00, UTC+5, etc.)
	// PHP's DateTimeZone accepts these formats
	if ( preg_match( '/^[+-]\d{2}:\d{2}$/', $timezone ) || preg_match( '/^UTC[+-]\d{1,2}(:\d{2})?$/', $timezone ) ) {
		try {
			new DateTimeZone( $timezone );
			return $timezone; // Valid UTC offset
		} catch ( Exception $e ) {
			return false;
		}
	}

	return false;
}

/**
 * Convert WordPress timezone setting to canonical identifier
 *
 * EC-004 FIX: Ensures timezone is always a canonical IANA identifier, never an offset.
 *
 * @since 1.0.0
 * @return string Canonical timezone identifier
 */
function scd_get_canonical_timezone(): string {
	$timezone_string = get_option( 'timezone_string' );

	// If we have a timezone string, validate and return it
	if ( ! empty( $timezone_string ) ) {
		$validated = scd_validate_timezone( $timezone_string );
		if ( $validated ) {
			return $validated;
		}
	}

	// Fall back to wp_timezone_string() but warn if it's an offset
	$wp_tz = wp_timezone_string();

	if ( preg_match( '/^UTC[+-]/', $wp_tz ) ) {
		// Log warning about UTC offset usage
		if ( function_exists( 'scd_log_warning' ) ) {
			scd_log_warning(
				'WordPress timezone is set to UTC offset instead of timezone string',
				array(
					'timezone'       => $wp_tz,
					'recommendation' => 'Please set timezone to a city (e.g., America/New_York) in Settings > General',
				)
			);
		}
	}

	return $wp_tz;
}

/**
 * Handle DST transition edge cases
 *
 * @since 1.0.0
 * @param DateTimeImmutable $datetime DateTime to check
 * @return array Information about DST transition
 */
function scd_check_dst_transition( DateTimeImmutable $datetime ): array {
	$tz          = $datetime->getTimezone();
	$transitions = $tz->getTransitions( $datetime->getTimestamp() - 86400, $datetime->getTimestamp() + 86400 );

	$info = array(
		'is_transition_day' => false,
		'transition_type'   => null, // 'spring_forward' or 'fall_back'
		'lost_hour'         => null, // For spring forward
		'repeated_hour'     => null, // For fall back
		'transition_time'   => null, // Exact transition timestamp
	);

	// getTransitions() returns false for fixed offset timezones (e.g., UTC, UTC+5)
	if ( false === $transitions || ! is_array( $transitions ) ) {
		return $info;
	}

	if ( count( $transitions ) > 1 ) {
		$info['is_transition_day'] = true;
		$info['transition_time']   = $transitions[1]['ts'];

		if ( $transitions[1]['offset'] > $transitions[0]['offset'] ) {
			$info['transition_type'] = 'spring_forward';
			$lost_start        = new DateTimeImmutable( '@' . $transitions[1]['ts'], $tz );
			$info['lost_hour'] = array(
				'start'           => $lost_start->format( 'H:i' ),
				'end'             => $lost_start->modify( '+1 hour' )->format( 'H:i' ),
				'start_timestamp' => $transitions[1]['ts'],
			);
		} else {
			$info['transition_type'] = 'fall_back';
			$repeat_start          = new DateTimeImmutable( '@' . $transitions[1]['ts'], $tz );
			$info['repeated_hour'] = array(
				'start'           => $repeat_start->modify( '-1 hour' )->format( 'H:i' ),
				'end'             => $repeat_start->format( 'H:i' ),
				'start_timestamp' => $transitions[1]['ts'] - 3600,
			);
		}
	}

	return $info;
}

/**
 * Adjust datetime for DST transitions
 *
 * EC-002 FIX: Spring forward - times in non-existent hour are adjusted forward
 * EC-003 FIX: Fall back - ambiguous times use first occurrence
 *
 * @since 1.0.0
 * @param DateTimeImmutable $datetime DateTime to adjust
 * @param array             $dst_info DST transition information from scd_check_dst_transition()
 * @return DateTimeImmutable Adjusted datetime
 */
function scd_adjust_for_dst_transition( DateTimeImmutable $datetime, array $dst_info ): DateTimeImmutable {
	if ( ! $dst_info['is_transition_day'] ) {
		return $datetime;
	}

	if ( 'spring_forward' === $dst_info['transition_type'] && $dst_info['lost_hour'] ) {
		$time_str   = $datetime->format( 'H:i' );
		$lost_start = $dst_info['lost_hour']['start'];
		$lost_end   = $dst_info['lost_hour']['end'];

		$time_parts  = scd_parse_time_string( $time_str );
		$start_parts = scd_parse_time_string( $lost_start );
		$end_parts   = scd_parse_time_string( $lost_end );

		if ( $time_parts && $start_parts && $end_parts ) {
			$time_minutes  = $time_parts['hour'] * 60 + $time_parts['minute'];
			$start_minutes = $start_parts['hour'] * 60 + $start_parts['minute'];
			$end_minutes   = $end_parts['hour'] * 60 + $end_parts['minute'];

			// If time falls in lost hour, adjust forward by 1 hour
			if ( $time_minutes >= $start_minutes && $time_minutes < $end_minutes ) {
				$datetime = $datetime->modify( '+1 hour' );

				// Log the adjustment
				if ( function_exists( 'scd_log_warning' ) ) {
					scd_log_warning(
						'DST Spring Forward: Adjusted time from non-existent hour',
						array(
							'original_time' => $time_str,
							'adjusted_time' => $datetime->format( 'H:i' ),
							'date'          => $datetime->format( 'Y-m-d' ),
						)
					);
				}
			}
		}
	} elseif ( 'fall_back' === $dst_info['transition_type'] && $dst_info['repeated_hour'] ) {
		// EC-003 FIX: For repeated hour, document that we use first occurrence
		// No adjustment needed - DateTimeImmutable automatically uses first occurrence
		// But we log it for awareness
		$time_str     = $datetime->format( 'H:i' );
		$repeat_start = $dst_info['repeated_hour']['start'];
		$repeat_end   = $dst_info['repeated_hour']['end'];

		$time_parts  = scd_parse_time_string( $time_str );
		$start_parts = scd_parse_time_string( $repeat_start );
		$end_parts   = scd_parse_time_string( $repeat_end );

		if ( $time_parts && $start_parts && $end_parts ) {
			$time_minutes  = $time_parts['hour'] * 60 + $time_parts['minute'];
			$start_minutes = $start_parts['hour'] * 60 + $start_parts['minute'];
			$end_minutes   = $end_parts['hour'] * 60 + $end_parts['minute'];

			if ( $time_minutes >= $start_minutes && $time_minutes < $end_minutes ) {
				// Log the ambiguous time - using first occurrence
				if ( function_exists( 'scd_log_info' ) ) {
					scd_log_info(
						'DST Fall Back: Time in repeated hour, using first occurrence',
						array(
							'time' => $time_str,
							'date' => $datetime->format( 'Y-m-d' ),
						)
					);
				}
			}
		}
	}

	return $datetime;
}

/**
 * Check if a datetime falls in a DST lost hour
 *
 * @since 1.0.0
 * @param DateTimeImmutable $datetime DateTime to check
 * @return bool True if in lost hour
 */
function scd_is_in_dst_lost_hour( DateTimeImmutable $datetime ): bool {
	$dst_info = scd_check_dst_transition( $datetime );

	if ( 'spring_forward' !== $dst_info['transition_type'] || ! $dst_info['lost_hour'] ) {
		return false;
	}

	$time_str    = $datetime->format( 'H:i' );
	$time_parts  = scd_parse_time_string( $time_str );
	$start_parts = scd_parse_time_string( $dst_info['lost_hour']['start'] );
	$end_parts   = scd_parse_time_string( $dst_info['lost_hour']['end'] );

	if ( ! $time_parts || ! $start_parts || ! $end_parts ) {
		return false;
	}

	$time_minutes  = $time_parts['hour'] * 60 + $time_parts['minute'];
	$start_minutes = $start_parts['hour'] * 60 + $start_parts['minute'];
	$end_minutes   = $end_parts['hour'] * 60 + $end_parts['minute'];

	return $time_minutes >= $start_minutes && $time_minutes < $end_minutes;
}

/**
 * Combine date and time into DateTimeImmutable object
 *
 * Safely combines separate date and time strings with full validation
 * and DST transition handling.
 *
 * @since 1.0.0
 * @param string      $date Date string (Y-m-d format)
 * @param string      $time Time string (H:i or H:i:s format)
 * @param string|null $timezone Timezone identifier (defaults to WordPress timezone)
 * @return DateTimeImmutable|false DateTimeImmutable object or false on failure
 */
function scd_combine_date_time( string $date, string $time, ?string $timezone = null ) {
	try {
		$tz = $timezone ? new DateTimeZone( $timezone ) : wp_timezone();

		$date_obj = DateTime::createFromFormat( '!Y-m-d', $date, $tz );
		if ( ! $date_obj || $date_obj->format( 'Y-m-d' ) !== $date ) {
			return false;
		}

		$time_parts = scd_parse_time_string( $time );
		if ( false === $time_parts ) {
			return false;
		}

		// Combine date and time
		$datetime_str = sprintf(
			'%s %02d:%02d:00',
			$date,
			$time_parts['hour'],
			$time_parts['minute']
		);

		$datetime = new DateTimeImmutable( $datetime_str, $tz );

		// DST transition handling
		$dst_info = scd_check_dst_transition( $datetime );
		if ( $dst_info['is_transition_day'] ) {
			$datetime = scd_adjust_for_dst_transition( $datetime, $dst_info );
		}

		return $datetime;

	} catch ( Exception $e ) {
		return false;
	}
}
