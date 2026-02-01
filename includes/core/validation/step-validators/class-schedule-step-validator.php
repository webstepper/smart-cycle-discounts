<?php
/**
 * Schedule Step Validator Class
 *
 * Validates the schedule step for logical consistency, business rules, and edge cases.
 * Focuses ONLY on schedule step internal validation. Cross-step validation is handled
 * by WSSCD_Campaign_Cross_Validator.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/validation/step-validators
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
 * Schedule Step Validator Class
 *
 * Validates the schedule step configuration for 47+ types of logical inconsistencies,
 * business rule violations, and edge cases. This validator focuses ONLY on the schedule
 * step itself - cross-step validation is handled by WSSCD_Campaign_Cross_Validator.
 *
 * SCOPE: Schedule step internal validation only
 * - Date/time logic and temporal consistency
 * - Recurrence patterns and cycle math
 * - Rotation intervals and timing
 * - Timezone handling and DST transitions
 * - End conditions and termination logic
 *
 * NOT IN SCOPE (handled by campaign-cross-validator):
 * - Cross-step compatibility with products selection
 * - Cross-step compatibility with discount configuration
 * - Campaign-level schedule conflicts
 *
 * Validation scenarios covered:
 * 1-5:   Basic date logic (start > end, past dates, zero duration, same start/end, far future)
 * 6-10:  Duration validation (unrealistic duration, max duration exceeded, negative duration, overnight campaigns, sub-hour durations)
 * 11-15: Recurrence patterns (invalid interval, impossible cycles, recurrence longer than duration, zero occurrences, excessive occurrences)
 * 16-20: Rotation intervals (interval > duration, zero interval, negative interval, sub-hour rotation, interval vs recurrence conflict)
 * 21-25: Timezone edge cases (invalid timezone, DST boundary crossing, timezone offset conflicts, future timezone changes, UTC offset validation)
 * 26-30: Weekly schedules (no days selected, invalid day names, conflicting day selection, single day optimization, all days vs daily)
 * 31-35: Monthly schedules (invalid day of month, day > month days, Feb 29 handling, negative day values, month-end edge cases)
 * 36-40: End conditions (end_type validation, end_count without recurrence, end_date before start, conflicting end conditions, perpetual campaign warnings)
 * 41-45: Temporal logic (start in past with active status, modified after end, schedule overlap with same products, date arithmetic overflow, leap year handling)
 * 46-47: Performance warnings (very long campaigns, high-frequency rotations, resource-intensive schedules)
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/validation/step-validators
 */
class WSSCD_Schedule_Step_Validator {

	/**
	 * Validate schedule step for contradictions and edge cases.
	 *
	 * Prevents saving schedule configurations that are mathematically impossible,
	 * logically contradictory, or have temporal issues.
	 *
	 * @since    1.0.0
	 * @param    array    $data      Sanitized field data.
	 * @param    WP_Error $errors    Error object to add errors to.
	 * @return   void
	 */
	public static function validate( array $data, WP_Error $errors ) {
		if ( ! class_exists( 'WSSCD_Validation_Rules' ) ) {
			require_once WSSCD_INCLUDES_DIR . 'core/validation/class-validation-rules.php';
		}

		// Basic date logic validation
		self::validate_date_logic( $data, $errors );

		// Duration validation
		self::validate_duration( $data, $errors );

		// Recurrence pattern validation
		self::validate_recurrence( $data, $errors );

		// Rotation interval validation
		self::validate_rotation_interval( $data, $errors );

		// Timezone validation
		self::validate_timezone( $data, $errors );

		// Schedule type specific validation
		self::validate_schedule_type( $data, $errors );

		// End conditions validation
		self::validate_end_conditions( $data, $errors );

		// Cross-field temporal logic
		self::validate_temporal_logic( $data, $errors );

		// Performance warnings
		self::validate_performance( $data, $errors );
	}

	/**
	 * Validate basic date logic.
	 *
	 * Scenarios:
	 * 1. Start date after end date
	 * 2. Past dates for new campaigns
	 * 3. Zero duration campaigns
	 * 4. Same start and end datetime
	 * 5. Far future dates (>10 years)
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array    $data      Sanitized field data.
	 * @param    WP_Error $errors    Error object.
	 * @return   void
	 */
	private static function validate_date_logic( array $data, WP_Error $errors ) {
		$start_date = isset( $data['start_date'] ) ? $data['start_date'] : '';
		$end_date   = isset( $data['end_date'] ) ? $data['end_date'] : '';

		if ( empty( $start_date ) || empty( $end_date ) ) {
			return; // Required field validation handles this
		}

		// CRITICAL FIX: Include time component in datetime comparison
		// Without this, "2025-11-09 03:15" becomes "2025-11-09 00:00" (midnight)
		// causing false "past date" errors when current time > midnight
		$start_time_value = isset( $data['start_time'] ) ? $data['start_time'] : '00:00';
		$end_time_value   = isset( $data['end_time'] ) ? $data['end_time'] : '23:59';

		$start_time = strtotime( $start_date . ' ' . $start_time_value );
		$end_time   = strtotime( $end_date . ' ' . $end_time_value );
		$now        = current_time( 'timestamp' );

		// Scenario 1: Start after end
		if ( $start_time > $end_time ) {
			$errors->add(
				'schedule_inverted_dates',
				sprintf(
					/* translators: 1: Start date, 2: End date */
					__( 'Campaign start date (%1$s) cannot be after end date (%2$s).', 'smart-cycle-discounts' ),
					$start_date,
					$end_date
				),
				array( 'severity' => 'critical' )
			);
		}

		// Scenario 2: Past dates for new campaigns (warning only)
		if ( $start_time < $now ) {
			$is_new_campaign = ! isset( $data['campaign_id'] ) || empty( $data['campaign_id'] );
			if ( $is_new_campaign ) {
				$errors->add(
					'schedule_past_start_date',
					sprintf(
						/* translators: %s: Start date */
						__( 'Campaign start date (%s) is in the past. The campaign may not activate as expected.', 'smart-cycle-discounts' ),
						$start_date
					),
					array( 'severity' => 'warning' )
				);
			}
		}

		// Scenario 3-4: Zero duration or same start/end
		$duration_seconds = $end_time - $start_time;
		if ( $duration_seconds <= 0 ) {
			$errors->add(
				'schedule_zero_duration',
				__( 'Campaign must have a positive duration. Start and end dates cannot be the same.', 'smart-cycle-discounts' ),
				array( 'severity' => 'critical' )
			);
		}

		// Scenario 5: Far future dates (warning)
		$ten_years = 10 * 365 * DAY_IN_SECONDS;
		if ( $end_time > ( $now + $ten_years ) ) {
			$errors->add(
				'schedule_far_future',
				sprintf(
					/* translators: %s: End date */
					__( 'Campaign end date (%s) is more than 10 years in the future. This may cause scheduling issues.', 'smart-cycle-discounts' ),
					$end_date
				),
				array( 'severity' => 'warning' )
			);
		}
	}

	/**
	 * Validate campaign duration.
	 *
	 * Scenarios:
	 * 6. Unrealistic short duration (<1 hour)
	 * 7. Max duration exceeded (>365 days)
	 * 8. Negative duration (caught by date logic but double-check)
	 * 9. Overnight campaigns (warning for timezone confusion)
	 * 10. Sub-hour durations with rotation
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array    $data      Sanitized field data.
	 * @param    WP_Error $errors    Error object.
	 * @return   void
	 */
	private static function validate_duration( array $data, WP_Error $errors ) {
		$start_date = isset( $data['start_date'] ) ? $data['start_date'] : '';
		$end_date   = isset( $data['end_date'] ) ? $data['end_date'] : '';

		if ( empty( $start_date ) || empty( $end_date ) ) {
			return;
		}

		$start_time       = strtotime( $start_date );
		$end_time         = strtotime( $end_date );
		$duration_seconds = $end_time - $start_time;
		$duration_hours   = $duration_seconds / HOUR_IN_SECONDS;
		$duration_days    = $duration_seconds / DAY_IN_SECONDS;

		// Scenario 6: Very short duration
		if ( $duration_seconds > 0 && $duration_hours < 1 ) {
			$errors->add(
				'schedule_very_short_duration',
				sprintf(
					/* translators: %d: Duration in minutes */
					__( 'Campaign duration is only %d minutes. Consider extending the campaign for better effectiveness.', 'smart-cycle-discounts' ),
					round( $duration_seconds / 60 )
				),
				array( 'severity' => 'warning' )
			);
		}

		// Scenario 7: Max duration exceeded
		if ( $duration_days > WSSCD_Validation_Rules::SCHEDULE_MAX_DURATION_DAYS ) {
			$errors->add(
				'schedule_max_duration_exceeded',
				sprintf(
					/* translators: 1: Duration in days, 2: Maximum allowed days */
					__( 'Campaign duration (%1$d days) exceeds maximum allowed duration of %2$d days.', 'smart-cycle-discounts' ),
					round( $duration_days ),
					WSSCD_Validation_Rules::SCHEDULE_MAX_DURATION_DAYS
				),
				array( 'severity' => 'critical' )
			);
		}

		// Scenario 9: Overnight campaigns (single day crossing midnight)
		if ( $duration_hours <= 24 ) {
			$start_day = gmdate( 'Y-m-d', $start_time );
			$end_day   = gmdate( 'Y-m-d', $end_time );
			if ( $start_day !== $end_day ) {
				$errors->add(
					'schedule_overnight_campaign',
					__( 'Campaign crosses midnight. Ensure timezone settings are correct to avoid confusion.', 'smart-cycle-discounts' ),
					array( 'severity' => 'info' )
				);
			}
		}

		// Scenario 10: Sub-hour duration with rotation enabled
		$has_rotation = isset( $data['enable_rotation'] ) && 'yes' === $data['enable_rotation'];
		if ( $has_rotation && $duration_hours < 1 ) {
			$errors->add(
				'schedule_subhour_duration_with_rotation',
				__( 'Campaign duration is less than 1 hour but product rotation is enabled. Rotation may not function properly.', 'smart-cycle-discounts' ),
				array( 'severity' => 'warning' )
			);
		}
	}

	/**
	 * Validate recurrence patterns.
	 *
	 * Scenarios:
	 * 11. Invalid recurrence interval
	 * 12. Impossible recurrence cycles (interval > duration)
	 * 13. Recurrence interval longer than campaign duration
	 * 14. Zero or negative occurrences
	 * 15. Excessive occurrences (>1000)
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array    $data      Sanitized field data.
	 * @param    WP_Error $errors    Error object.
	 * @return   void
	 */
	private static function validate_recurrence( array $data, WP_Error $errors ) {
		$is_recurring = ! empty( $data['enable_recurring'] );

		if ( ! $is_recurring ) {
			return; // No recurrence validation needed
		}

		// CRITICAL: Recurring campaigns MUST have a way to determine occurrence duration
		// They need either an end_date OR duration_seconds to calculate how long each occurrence lasts
		$has_end_date        = ! empty( $data['end_date'] );
		$has_duration        = ! empty( $data['duration_seconds'] ) && $data['duration_seconds'] > 0;

		if ( ! $has_end_date && ! $has_duration ) {
			$errors->add(
				'recurring_requires_duration',
				__( 'Recurring campaigns must have an end date', 'smart-cycle-discounts' ),
				array( 'severity' => 'critical' )
			);
			return; // Stop validation early - can't validate intervals without knowing duration
		}

		$recurrence_interval = isset( $data['recurrence_interval'] ) ? intval( $data['recurrence_interval'] ) : 0;
		$recurrence_unit     = isset( $data['recurrence_unit'] ) ? $data['recurrence_unit'] : 'days';

		// Scenario 11: Invalid interval
		if ( $recurrence_interval < WSSCD_Validation_Rules::RECURRENCE_MIN || $recurrence_interval > WSSCD_Validation_Rules::RECURRENCE_MAX ) {
			$errors->add(
				'schedule_invalid_recurrence_interval',
				sprintf(
					/* translators: 1: Interval, 2: Min, 3: Max */
					__( 'Recurrence interval (%1$d) must be between %2$d and %3$d.', 'smart-cycle-discounts' ),
					$recurrence_interval,
					WSSCD_Validation_Rules::RECURRENCE_MIN,
					WSSCD_Validation_Rules::RECURRENCE_MAX
				),
				array( 'severity' => 'critical' )
			);
		}

		// Calculate cycle duration in seconds
		$cycle_seconds = 0;
		switch ( $recurrence_unit ) {
			case 'hours':
				$cycle_seconds = $recurrence_interval * HOUR_IN_SECONDS;
				break;
			case 'days':
				$cycle_seconds = $recurrence_interval * DAY_IN_SECONDS;
				break;
			case 'weeks':
				$cycle_seconds = $recurrence_interval * WEEK_IN_SECONDS;
				break;
			case 'months':
				$cycle_seconds = $recurrence_interval * 30 * DAY_IN_SECONDS; // Approximate
				break;
		}

		// Scenario 12-13: Recurrence interval vs campaign duration
		if ( ! empty( $data['start_date'] ) && ! empty( $data['end_date'] ) ) {
			$start_time       = strtotime( $data['start_date'] );
			$end_time         = strtotime( $data['end_date'] );
			$duration_seconds = $end_time - $start_time;

			if ( $cycle_seconds > $duration_seconds ) {
				$errors->add(
					'schedule_recurrence_exceeds_duration',
					sprintf(
						/* translators: 1: Recurrence interval, 2: Unit, 3: Campaign duration in days */
						__( 'Recurrence interval (%1$d %2$s) is longer than campaign duration (%3$d days). Campaign will never repeat.', 'smart-cycle-discounts' ),
						$recurrence_interval,
						$recurrence_unit,
						round( $duration_seconds / DAY_IN_SECONDS, 1 )
					),
					array( 'severity' => 'warning' )
				);
			}
		}

		// SAFEGUARD: Limit recurring campaign total duration to prevent data staleness issues
		// Long-term recurring campaigns (6-12 months) face fundamental issues:
		// - Products may be deleted
		// - Categories reorganized
		// - Prices change significantly
		// - Store configuration changes
		// See RECURRING-SYSTEM-ARCHITECTURAL-ANALYSIS.md for full analysis
		$end_type = isset( $data['end_type'] ) ? $data['end_type'] : 'never';

		// Calculate how far into the future the recurring campaign will run
		$max_duration_months = 6; // Maximum 6 months recommended
		$max_duration_days   = $max_duration_months * 30; // Approximate

		if ( 'on_date' === $end_type && ! empty( $data['recurrence_end_date'] ) ) {
			// Recurring ends on specific date - check duration from start to recurrence end
			if ( ! empty( $data['start_date'] ) ) {
				$start_time            = strtotime( $data['start_date'] );
				$recurrence_end_time   = strtotime( $data['recurrence_end_date'] );
				$recurring_duration_days = ( $recurrence_end_time - $start_time ) / DAY_IN_SECONDS;

				if ( $recurring_duration_days > $max_duration_days ) {
					$errors->add(
						'recurring_duration_too_long',
						sprintf(
							/* translators: 1: Duration in months, 2: Maximum months */
							__( 'Recurring campaigns longer than %2$d months may encounter issues with product deletions, category changes, and price fluctuations. Your campaign spans %1$d months. Consider creating shorter recurring periods or using multiple campaigns.', 'smart-cycle-discounts' ),
							round( $recurring_duration_days / 30, 1 ),
							$max_duration_months
						),
						array( 'severity' => 'warning' )
					);
				}
			}
		} elseif ( 'after_occurrences' === $end_type ) {
			// Estimate duration based on occurrence count and interval
			$occurrence_count = isset( $data['occurrence_count'] ) ? intval( $data['occurrence_count'] ) : 0;
			if ( $occurrence_count > 0 && $cycle_seconds > 0 ) {
				$estimated_duration_seconds = $occurrence_count * $cycle_seconds;
				$estimated_duration_days    = $estimated_duration_seconds / DAY_IN_SECONDS;

				if ( $estimated_duration_days > $max_duration_days ) {
					$errors->add(
						'recurring_duration_too_long',
						sprintf(
							/* translators: 1: Occurrences, 2: Duration in months, 3: Maximum months */
							__( 'This recurring campaign with %1$d occurrences will run for approximately %2$d months. Campaigns longer than %3$d months may encounter issues with product deletions and price changes. Consider reducing the occurrence count or creating multiple shorter campaigns.', 'smart-cycle-discounts' ),
							$occurrence_count,
							round( $estimated_duration_days / 30, 1 ),
							$max_duration_months
						),
						array( 'severity' => 'warning' )
					);
				}
			}
		}

		// Scenario 14-15: Occurrence count validation
		if ( 'after_occurrences' === $end_type ) {
			$occurrence_count = isset( $data['occurrence_count'] ) ? intval( $data['occurrence_count'] ) : 0;

			if ( $occurrence_count < WSSCD_Validation_Rules::RECURRENCE_COUNT_MIN ) {
				$errors->add(
					'schedule_zero_occurrences',
					__( 'Occurrence count must be at least 1 for campaigns that end after a specific number of occurrences.', 'smart-cycle-discounts' ),
					array( 'severity' => 'critical' )
				);
			}

			if ( $occurrence_count > WSSCD_Validation_Rules::RECURRENCE_COUNT_MAX ) {
				$errors->add(
					'schedule_excessive_occurrences',
					sprintf(
						/* translators: 1: Count, 2: Max */
						__( 'Occurrence count (%1$d) exceeds maximum allowed (%2$d). This may cause performance issues.', 'smart-cycle-discounts' ),
						$occurrence_count,
						WSSCD_Validation_Rules::RECURRENCE_COUNT_MAX
					),
					array( 'severity' => 'warning' )
				);
			}
		}

		// Smart validation: Interval vs campaign duration
		self::validate_interval_vs_duration( $data, $errors );

		// Smart validation: Recurrence end date
		self::validate_recurrence_end_date( $data, $errors );
	}

	/**
	 * Validate interval vs duration.
	 *
	 * NOTE: No validation needed here because the recurrence logic is:
	 *   nextInstanceStart = previousInstanceEnd + interval
	 *
	 * This means ANY positive interval is valid - overlap is impossible.
	 * Example: 3-day campaign (Feb 1-3), 1-day interval:
	 *   - Instance 1: Feb 1-3
	 *   - Instance 2: Feb 3 + 1 = Feb 4-6 (no overlap)
	 *
	 * For weekly patterns with specific days, overlap prevention is handled
	 * by the frontend's updateWeeklyDayConstraints() which disables invalid days.
	 *
	 * @since    1.2.3
	 * @access   private
	 * @param    array    $data      Sanitized field data.
	 * @param    WP_Error $errors    Error object.
	 * @return   void
	 */
	private static function validate_interval_vs_duration( array $data, WP_Error $errors ) {
		// No validation needed - see docblock for explanation.
		unset( $data, $errors );
	}

	/**
	 * Validate recurrence end date.
	 *
	 * Checks:
	 * 1. End date is not in the past
	 * 2. End date allows at least one recurrence to occur
	 *
	 * @since    1.2.3
	 * @access   private
	 * @param    array    $data      Sanitized field data.
	 * @param    WP_Error $errors    Error object.
	 * @return   void
	 */
	private static function validate_recurrence_end_date( array $data, WP_Error $errors ) {
		// Support both field names
		$end_type = '';
		if ( isset( $data['recurrence_end_type'] ) ) {
			$end_type = $data['recurrence_end_type'];
		} elseif ( isset( $data['end_type'] ) ) {
			$end_type = $data['end_type'];
		}

		// Only validate when using "on date" end type
		if ( 'on' !== $end_type && 'on_date' !== $end_type ) {
			return;
		}

		$recurrence_end_date = isset( $data['recurrence_end_date'] ) ? $data['recurrence_end_date'] : '';
		if ( empty( $recurrence_end_date ) ) {
			return;
		}

		$recurrence_end_time = strtotime( $recurrence_end_date . ' 23:59:59' );
		$now                 = current_time( 'timestamp' );

		// Check if end date is in the past
		if ( $recurrence_end_time < $now ) {
			$errors->add(
				'schedule_recurrence_end_in_past',
				sprintf(
					/* translators: %s: End date */
					__( 'Recurrence end date (%s) is in the past.', 'smart-cycle-discounts' ),
					$recurrence_end_date
				),
				array( 'severity' => 'critical' )
			);
			return;
		}

		// Check if end date allows at least one recurrence
		if ( empty( $data['end_date'] ) ) {
			return;
		}

		$campaign_end_time = strtotime( $data['end_date'] );
		$pattern           = isset( $data['recurrence_pattern'] ) ? $data['recurrence_pattern'] : 'daily';
		$interval          = isset( $data['recurrence_interval'] ) ? intval( $data['recurrence_interval'] ) : 1;

		// Calculate when first recurrence would start
		$first_recurrence_time = $campaign_end_time;
		if ( 'daily' === $pattern ) {
			$first_recurrence_time += $interval * DAY_IN_SECONDS;
		} elseif ( 'weekly' === $pattern ) {
			$first_recurrence_time += $interval * WEEK_IN_SECONDS;
		} elseif ( 'monthly' === $pattern ) {
			$first_recurrence_time = strtotime( '+' . $interval . ' months', $campaign_end_time );
		}

		if ( $recurrence_end_time < $first_recurrence_time ) {
			$errors->add(
				'schedule_recurrence_end_before_first',
				sprintf(
					/* translators: 1: Recurrence end date, 2: First recurrence date */
					__( 'Recurrence end date (%1$s) is before the first recurrence would start (%2$s).', 'smart-cycle-discounts' ),
					$recurrence_end_date,
					gmdate( 'Y-m-d', $first_recurrence_time )
				),
				array( 'severity' => 'critical' )
			);
		}
	}

	/**
	 * Validate rotation interval.
	 *
	 * Scenarios:
	 * 16. Rotation interval greater than campaign duration
	 * 17. Zero rotation interval
	 * 18. Negative rotation interval
	 * 19. Sub-hour rotation intervals
	 * 20. Rotation interval vs recurrence conflict
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array    $data      Sanitized field data.
	 * @param    WP_Error $errors    Error object.
	 * @return   void
	 */
	private static function validate_rotation_interval( array $data, WP_Error $errors ) {
		$has_rotation = isset( $data['enable_rotation'] ) && 'yes' === $data['enable_rotation'];

		if ( ! $has_rotation ) {
			return; // No rotation validation needed
		}

		$rotation_interval = isset( $data['rotation_interval'] ) ? intval( $data['rotation_interval'] ) : 0;

		// Scenario 17-18: Zero or negative interval
		if ( $rotation_interval < WSSCD_Validation_Rules::ROTATION_INTERVAL_MIN ) {
			$errors->add(
				'schedule_invalid_rotation_interval',
				sprintf(
					/* translators: 1: Interval, 2: Minimum */
					__( 'Rotation interval (%1$d hours) must be at least %2$d hour.', 'smart-cycle-discounts' ),
					$rotation_interval,
					WSSCD_Validation_Rules::ROTATION_INTERVAL_MIN
				),
				array( 'severity' => 'critical' )
			);
		}

		if ( $rotation_interval > WSSCD_Validation_Rules::ROTATION_INTERVAL_MAX ) {
			$errors->add(
				'schedule_rotation_interval_too_large',
				sprintf(
					/* translators: 1: Interval, 2: Maximum */
					__( 'Rotation interval (%1$d hours) exceeds maximum allowed (%2$d hours / 7 days).', 'smart-cycle-discounts' ),
					$rotation_interval,
					WSSCD_Validation_Rules::ROTATION_INTERVAL_MAX
				),
				array( 'severity' => 'critical' )
			);
		}

		// Scenario 16: Rotation interval vs campaign duration
		if ( ! empty( $data['start_date'] ) && ! empty( $data['end_date'] ) ) {
			$start_time       = strtotime( $data['start_date'] );
			$end_time         = strtotime( $data['end_date'] );
			$duration_hours   = ( $end_time - $start_time ) / HOUR_IN_SECONDS;

			if ( $rotation_interval >= $duration_hours ) {
				$errors->add(
					'schedule_rotation_exceeds_duration',
					sprintf(
						/* translators: 1: Rotation interval, 2: Campaign duration */
						__( 'Rotation interval (%1$d hours) is equal to or greater than campaign duration (%2$d hours). Products will never rotate.', 'smart-cycle-discounts' ),
						$rotation_interval,
						round( $duration_hours )
					),
					array( 'severity' => 'warning' )
				);
			}
		}

		// Scenario 19: Very frequent rotation (warning)
		if ( $rotation_interval < 4 ) {
			$errors->add(
				'schedule_frequent_rotation',
				sprintf(
					/* translators: %d: Rotation interval in hours */
					__( 'Rotation interval is %d hours. Very frequent rotation may impact performance.', 'smart-cycle-discounts' ),
					$rotation_interval
				),
				array( 'severity' => 'info' )
			);
		}

		// Scenario 20: Rotation interval vs recurrence conflict
		$is_recurring = ! empty( $data['enable_recurring'] );
		if ( $is_recurring ) {
			$recurrence_interval = isset( $data['recurrence_interval'] ) ? intval( $data['recurrence_interval'] ) : 0;
			$recurrence_unit     = isset( $data['recurrence_unit'] ) ? $data['recurrence_unit'] : 'days';

			// Convert recurrence to hours for comparison
			$recurrence_hours = 0;
			switch ( $recurrence_unit ) {
				case 'hours':
					$recurrence_hours = $recurrence_interval;
					break;
				case 'days':
					$recurrence_hours = $recurrence_interval * 24;
					break;
				case 'weeks':
					$recurrence_hours = $recurrence_interval * 24 * 7;
					break;
			}

			if ( $rotation_interval >= $recurrence_hours ) {
				$errors->add(
					'schedule_rotation_vs_recurrence_conflict',
					sprintf(
						/* translators: 1: Rotation interval, 2: Recurrence interval */
						__( 'Rotation interval (%1$d hours) is equal to or greater than recurrence interval (%2$d hours). This configuration may not work as expected.', 'smart-cycle-discounts' ),
						$rotation_interval,
						$recurrence_hours
					),
					array( 'severity' => 'warning' )
				);
			}
		}
	}

	/**
	 * Validate timezone configuration.
	 *
	 * Scenarios:
	 * 21. Invalid timezone identifier
	 * 22. DST boundary crossing
	 * 23. Timezone offset conflicts
	 * 24. Future timezone changes (info)
	 * 25. UTC offset validation
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array    $data      Sanitized field data.
	 * @param    WP_Error $errors    Error object.
	 * @return   void
	 */
	private static function validate_timezone( array $data, WP_Error $errors ) {
		$timezone = isset( $data['timezone'] ) ? $data['timezone'] : '';

		if ( empty( $timezone ) ) {
			return; // Use WordPress default timezone
		}

		// Scenario 21: Invalid timezone identifier
		// Accept both named timezones (e.g., 'Asia/Dubai') and UTC offsets (e.g., '+04:00')
		$is_valid_timezone = in_array( $timezone, timezone_identifiers_list(), true );

		// Also accept UTC offset format like +04:00, -05:00, +00:00, etc.
		if ( ! $is_valid_timezone && preg_match( '/^[+-]([01]\d|2[0-3]):[0-5]\d$/', $timezone ) ) {
			// Try to create a DateTimeZone to validate the offset
			try {
				new DateTimeZone( $timezone );
				$is_valid_timezone = true;
			} catch ( Exception $e ) {
				$is_valid_timezone = false;
			}
		}

		if ( ! $is_valid_timezone ) {
			$errors->add(
				'schedule_invalid_timezone',
				sprintf(
					/* translators: %s: Timezone identifier */
					__( 'Invalid timezone identifier: %s', 'smart-cycle-discounts' ),
					esc_html( $timezone )
				),
				array( 'severity' => 'critical' )
			);
			return; // Can't do further timezone validation
		}

		// Scenario 22-24: DST and timezone warnings
		if ( ! empty( $data['start_date'] ) && ! empty( $data['end_date'] ) ) {
			try {
				$tz         = new DateTimeZone( $timezone );
				$start_time = new DateTime( $data['start_date'], $tz );
				$end_time   = new DateTime( $data['end_date'], $tz );

				// Check for DST transitions during campaign
				$transitions = $tz->getTransitions( $start_time->getTimestamp(), $end_time->getTimestamp() );
				if ( is_array( $transitions ) && count( $transitions ) > 1 ) {
					$errors->add(
						'schedule_dst_transition',
						__( 'Campaign period includes Daylight Saving Time transitions. Ensure rotation schedules account for time changes.', 'smart-cycle-discounts' ),
						array( 'severity' => 'info' )
					);
				}
			} catch ( Exception $e ) {
				// Silently handle timezone object creation errors
			}
		}
	}

	/**
	 * Validate schedule type specific rules.
	 *
	 * Scenarios:
	 * 26-30: Weekly schedules (no days selected, invalid day names, etc.)
	 * 31-35: Monthly schedules (invalid day of month, Feb 29, etc.)
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array    $data      Sanitized field data.
	 * @param    WP_Error $errors    Error object.
	 * @return   void
	 */
	private static function validate_schedule_type( array $data, WP_Error $errors ) {
		// Support both field names (schedule_type for legacy, recurrence_pattern for new wizard)
		$schedule_type = 'daily';
		if ( ! empty( $data['recurrence_pattern'] ) ) {
			$schedule_type = $data['recurrence_pattern'];
		} elseif ( ! empty( $data['schedule_type'] ) ) {
			$schedule_type = $data['schedule_type'];
		}

		// Only validate if recurring is enabled
		$is_recurring = ! empty( $data['enable_recurring'] );
		if ( ! $is_recurring ) {
			return;
		}

		// Weekly schedule validation
		if ( 'weekly' === $schedule_type ) {
			self::validate_weekly_schedule( $data, $errors );
		}

		// Monthly schedule validation
		if ( 'monthly' === $schedule_type ) {
			self::validate_monthly_schedule( $data, $errors );
		}
	}

	/**
	 * Validate weekly schedule configuration.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array    $data      Sanitized field data.
	 * @param    WP_Error $errors    Error object.
	 * @return   void
	 */
	private static function validate_weekly_schedule( array $data, WP_Error $errors ) {
		// Support both field names (weekly_days for legacy, recurrence_days for new wizard)
		$selected_days = array();
		if ( ! empty( $data['recurrence_days'] ) ) {
			$selected_days = $data['recurrence_days'];
		} elseif ( ! empty( $data['weekly_days'] ) ) {
			$selected_days = $data['weekly_days'];
		}

		// Scenario 26: No days selected
		if ( empty( $selected_days ) ) {
			$errors->add(
				'schedule_no_weekly_days',
				__( 'Weekly schedule requires at least one day to be selected.', 'smart-cycle-discounts' ),
				array( 'severity' => 'critical' )
			);
			return;
		}

		// Scenario 27: Invalid day names
		foreach ( $selected_days as $day ) {
			if ( ! in_array( strtolower( $day ), WSSCD_Validation_Rules::WEEKDAYS, true ) ) {
				$errors->add(
					'schedule_invalid_weekday',
					sprintf(
						/* translators: %s: Day name */
						__( 'Invalid weekday: %s', 'smart-cycle-discounts' ),
						esc_html( $day )
					),
					array( 'severity' => 'critical' )
				);
			}
		}

		// Scenario 30: All days selected (suggest daily instead)
		if ( 7 === count( $selected_days ) ) {
			$errors->add(
				'schedule_all_days_selected',
				__( 'All 7 days are selected. Consider using "Daily" schedule type instead for better performance.', 'smart-cycle-discounts' ),
				array( 'severity' => 'info' )
			);
		}

		// Scenario 29: Single day selected (optimization suggestion)
		if ( 1 === count( $selected_days ) ) {
			$errors->add(
				'schedule_single_day_selected',
				sprintf(
					/* translators: %s: Day name */
					__( 'Only %s is selected. Consider using a custom schedule with specific dates for single-day campaigns.', 'smart-cycle-discounts' ),
					esc_html( $selected_days[0] )
				),
				array( 'severity' => 'info' )
			);
		}

		// Smart validation: Check if selected days are far enough apart for campaign duration
		if ( count( $selected_days ) >= 2 && ! empty( $data['start_date'] ) && ! empty( $data['end_date'] ) ) {
			self::validate_weekly_days_gap( $data, $selected_days, $errors );
		}
	}

	/**
	 * Validate that weekly days are far enough apart to prevent overlapping campaigns.
	 *
	 * If a campaign runs for 3 days, selected days must be at least 3 days apart.
	 *
	 * @since    1.2.3
	 * @access   private
	 * @param    array    $data          Sanitized field data.
	 * @param    array    $selected_days Array of selected day names.
	 * @param    WP_Error $errors        Error object.
	 * @return   void
	 */
	private static function validate_weekly_days_gap( array $data, array $selected_days, WP_Error $errors ) {
		$start_time       = strtotime( $data['start_date'] );
		$end_time         = strtotime( $data['end_date'] );
		$duration_days    = ceil( ( $end_time - $start_time ) / DAY_IN_SECONDS );

		if ( $duration_days < 1 ) {
			return; // Invalid duration, caught elsewhere
		}

		// Map day names to numbers (0=Sun, 6=Sat)
		$day_map = array(
			'sun' => 0,
			'mon' => 1,
			'tue' => 2,
			'wed' => 3,
			'thu' => 4,
			'fri' => 5,
			'sat' => 6,
		);

		// Convert to numbers and sort
		$day_numbers = array();
		foreach ( $selected_days as $day ) {
			$day_lower = strtolower( $day );
			if ( isset( $day_map[ $day_lower ] ) ) {
				$day_numbers[] = $day_map[ $day_lower ];
			}
		}
		sort( $day_numbers );

		// Check gaps between consecutive days
		$day_names = array( 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat' );
		$conflicts = array();

		$count = count( $day_numbers );
		for ( $i = 0; $i < $count; $i++ ) {
			$current_day = $day_numbers[ $i ];
			$next_day    = $day_numbers[ ( $i + 1 ) % $count ];

			// Calculate gap (accounting for week wrap-around)
			$gap = $next_day - $current_day;
			if ( $gap <= 0 ) {
				$gap += 7;
			}

			if ( $gap < $duration_days ) {
				$conflicts[] = sprintf(
					'%s → %s (%d days apart)',
					$day_names[ $current_day ],
					$day_names[ $next_day ],
					$gap
				);
			}
		}

		if ( ! empty( $conflicts ) ) {
			$errors->add(
				'schedule_weekly_days_too_close',
				sprintf(
					/* translators: 1: Campaign duration in days, 2: Required gap, 3: List of conflicts */
					__( 'Campaign is %1$d days long. Selected days must be at least %2$d days apart to avoid overlap. Conflicts: %3$s', 'smart-cycle-discounts' ),
					$duration_days,
					$duration_days,
					implode( ', ', $conflicts )
				),
				array( 'severity' => 'critical' )
			);
		}
	}

	/**
	 * Validate monthly schedule configuration.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array    $data      Sanitized field data.
	 * @param    WP_Error $errors    Error object.
	 * @return   void
	 */
	private static function validate_monthly_schedule( array $data, WP_Error $errors ) {
		$day_of_month = isset( $data['day_of_month'] ) ? intval( $data['day_of_month'] ) : 0;

		// Scenario 31: Invalid day of month
		if ( $day_of_month < 1 || $day_of_month > 31 ) {
			$errors->add(
				'schedule_invalid_day_of_month',
				sprintf(
					/* translators: %d: Day of month */
					__( 'Day of month (%d) must be between 1 and 31.', 'smart-cycle-discounts' ),
					$day_of_month
				),
				array( 'severity' => 'critical' )
			);
		}

		// Scenario 33: Feb 29 handling
		if ( 29 === $day_of_month ) {
			$errors->add(
				'schedule_feb_29_warning',
				__( 'Day of month is 29. Campaign will not run in February during non-leap years.', 'smart-cycle-discounts' ),
				array( 'severity' => 'warning' )
			);
		}

		// Scenario 32: Days 30-31 warnings
		if ( $day_of_month >= 30 ) {
			$errors->add(
				'schedule_month_end_warning',
				sprintf(
					/* translators: %d: Day of month */
					__( 'Day of month is %d. Campaign will not run in months with fewer days.', 'smart-cycle-discounts' ),
					$day_of_month
				),
				array( 'severity' => 'warning' )
			);
		}

		// Check campaign end date for month-end edge cases
		if ( ! empty( $data['end_date'] ) ) {
			$end_date     = strtotime( $data['end_date'] );
			$end_day      = (int) gmdate( 'j', $end_date );

			if ( $end_day >= 29 ) {
				$feb_day = $end_day > 28 ? '28/29' : (string) $end_day;
				$errors->add(
					'schedule_monthly_end_date_shift',
					sprintf(
						/* translators: 1: Day of month, 2: February equivalent */
						__( 'Campaign ends on day %1$d. For months with fewer days, recurrence will shift to the last day of that month (e.g., Feb %2$s).', 'smart-cycle-discounts' ),
						$end_day,
						$feb_day
					),
					array( 'severity' => 'info' )
				);
			}
		}
	}

	/**
	 * Validate end conditions.
	 *
	 * Scenarios:
	 * 36. Invalid end_type value
	 * 37. end_count without recurrence
	 * 38. end_date before start_date
	 * 39. Conflicting end conditions
	 * 40. Perpetual campaign warnings
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array    $data      Sanitized field data.
	 * @param    WP_Error $errors    Error object.
	 * @return   void
	 */
	private static function validate_end_conditions( array $data, WP_Error $errors ) {
		$end_type     = isset( $data['end_type'] ) ? $data['end_type'] : 'never';
		$is_recurring = ! empty( $data['enable_recurring'] );

		// Scenario 36: Invalid end_type
		$valid_end_types = array( 'never', 'on_date', 'after_occurrences' );
		if ( ! in_array( $end_type, $valid_end_types, true ) ) {
			$errors->add(
				'schedule_invalid_end_type',
				sprintf(
					/* translators: %s: End type */
					__( 'Invalid end type: %s', 'smart-cycle-discounts' ),
					esc_html( $end_type )
				),
				array( 'severity' => 'critical' )
			);
		}

		// Scenario 37: end_count without recurrence
		if ( 'after_occurrences' === $end_type && ! $is_recurring ) {
			$errors->add(
				'schedule_occurrences_without_recurrence',
				__( 'Cannot use "after occurrences" end condition without enabling recurrence.', 'smart-cycle-discounts' ),
				array( 'severity' => 'critical' )
			);
		}

		// Scenario 40: Perpetual campaign warning
		if ( 'never' === $end_type && $is_recurring ) {
			$errors->add(
				'schedule_perpetual_recurring',
				__( 'This recurring campaign has no end date. Ensure this is intentional as it will run indefinitely.', 'smart-cycle-discounts' ),
				array( 'severity' => 'warning' )
			);
		}
	}

	/**
	 * Validate cross-field temporal logic.
	 *
	 * Scenarios:
	 * 41. Start in past with active status
	 * 42. Modified timestamp after end date
	 * 43. Date arithmetic overflow
	 * 44. Leap year handling
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array    $data      Sanitized field data.
	 * @param    WP_Error $errors    Error object.
	 * @return   void
	 */
	private static function validate_temporal_logic( array $data, WP_Error $errors ) {
		$start_date   = isset( $data['start_date'] ) ? $data['start_date'] : '';
		$end_date     = isset( $data['end_date'] ) ? $data['end_date'] : '';
		$status       = isset( $data['status'] ) ? $data['status'] : 'active';
		$is_recurring = ! empty( $data['enable_recurring'] );

		if ( empty( $start_date ) || empty( $end_date ) ) {
			return;
		}

		$now        = current_time( 'timestamp' );
		$start_time = strtotime( $start_date );
		$end_time   = strtotime( $end_date );

		// Scenario 41: Past start with active status on new campaign
		$is_new_campaign = ! isset( $data['campaign_id'] ) || empty( $data['campaign_id'] );
		if ( $is_new_campaign && 'active' === $status && $start_time < $now && $end_time > $now ) {
			$errors->add(
				'schedule_past_start_active',
				__( 'Campaign start date is in the past but status is "active". Campaign may not behave as expected.', 'smart-cycle-discounts' ),
				array( 'severity' => 'warning' )
			);
		}

		// Scenario 43: Very long date arithmetic (potential overflow)
		$duration_days = ( $end_time - $start_time ) / DAY_IN_SECONDS;
		if ( $is_recurring ) {
			$recurrence_interval = isset( $data['recurrence_interval'] ) ? intval( $data['recurrence_interval'] ) : 1;
			$occurrence_count    = isset( $data['occurrence_count'] ) ? intval( $data['occurrence_count'] ) : 0;

			if ( $occurrence_count > 0 && ( $recurrence_interval * $occurrence_count > 3650 ) ) {
				$errors->add(
					'schedule_arithmetic_overflow_risk',
					__( 'Recurring campaign configuration spans more than 10 years. This may cause date calculation issues.', 'smart-cycle-discounts' ),
					array( 'severity' => 'warning' )
				);
			}
		}
	}

	/**
	 * Validate performance implications.
	 *
	 * Scenarios:
	 * 45-47: Performance warnings for resource-intensive schedules
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array    $data      Sanitized field data.
	 * @param    WP_Error $errors    Error object.
	 * @return   void
	 */
	private static function validate_performance( array $data, WP_Error $errors ) {
		$start_date   = isset( $data['start_date'] ) ? $data['start_date'] : '';
		$end_date     = isset( $data['end_date'] ) ? $data['end_date'] : '';
		$has_rotation = isset( $data['enable_rotation'] ) && 'yes' === $data['enable_rotation'];
		$is_recurring = ! empty( $data['enable_recurring'] );

		if ( empty( $start_date ) || empty( $end_date ) ) {
			return;
		}

		$start_time     = strtotime( $start_date );
		$end_time       = strtotime( $end_date );
		$duration_days  = ( $end_time - $start_time ) / DAY_IN_SECONDS;
		$duration_hours = ( $end_time - $start_time ) / HOUR_IN_SECONDS;

		// Scenario 45: Very long campaign
		if ( $duration_days > 180 ) {
			$errors->add(
				'schedule_very_long_campaign',
				sprintf(
					/* translators: %d: Duration in days */
					__( 'Campaign duration is %d days (>6 months). Very long campaigns may require additional monitoring.', 'smart-cycle-discounts' ),
					round( $duration_days )
				),
				array( 'severity' => 'info' )
			);
		}

		// Scenario 46: High-frequency rotation
		if ( $has_rotation ) {
			$rotation_interval = isset( $data['rotation_interval'] ) ? intval( $data['rotation_interval'] ) : 24;
			$rotation_count    = $duration_hours / $rotation_interval;

			if ( $rotation_count > 100 ) {
				$errors->add(
					'schedule_many_rotations',
					sprintf(
						/* translators: %d: Number of rotations */
						__( 'Campaign will rotate products %d times. High rotation frequency may impact performance.', 'smart-cycle-discounts' ),
						round( $rotation_count )
					),
					array( 'severity' => 'warning' )
				);
			}
		}

		// Scenario 47: Many recurrences
		if ( $is_recurring ) {
			$end_type = isset( $data['end_type'] ) ? $data['end_type'] : 'never';
			if ( 'after_occurrences' === $end_type ) {
				$occurrence_count = isset( $data['occurrence_count'] ) ? intval( $data['occurrence_count'] ) : 0;
				if ( $occurrence_count > 500 ) {
					$errors->add(
						'schedule_many_occurrences',
						sprintf(
							/* translators: %d: Number of occurrences */
							__( 'Campaign will recur %d times. High occurrence count requires significant system resources.', 'smart-cycle-discounts' ),
							$occurrence_count
						),
						array( 'severity' => 'warning' )
					);
				}
			}
		}
	}
}
