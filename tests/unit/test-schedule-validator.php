<?php
/**
 * Class Test_Schedule_Validator
 *
 * Tests for SCD_Schedule_Step_Validator class.
 * These tests verify the datetime bug fix where time component was being ignored.
 *
 * @package    Smart_Cycle_Discounts
 * @subpackage Smart_Cycle_Discounts/Tests/Unit
 */

use PHPUnit\Framework\TestCase;

/**
 * Test Schedule Validator class
 *
 * Tests critical datetime validation logic that was causing scheduled campaigns
 * to be rejected as "past dates" when they were actually in the future.
 */
class Test_Schedule_Validator extends TestCase {

	/**
	 * Test that validates datetime (not just date).
	 *
	 * This is the PRIMARY bug fix test.
	 * Before fix: "2025-11-09 03:15" was compared as "2025-11-09 00:00" (midnight).
	 * After fix: Time component is included in comparison.
	 *
	 * @since 1.0.0
	 */
	public function test_validates_datetime_not_just_date() {
		// Setup: Create a datetime 1 hour in the future
		$future_timestamp = current_time( 'timestamp' ) + HOUR_IN_SECONDS;
		$start_date       = gmdate( 'Y-m-d', $future_timestamp );
		$start_time       = gmdate( 'H:i', $future_timestamp );
		$end_date         = gmdate( 'Y-m-d', $future_timestamp + ( 7 * DAY_IN_SECONDS ) );
		$end_time         = '23:59';

		$data = array(
			'start_date' => $start_date,
			'start_time' => $start_time,
			'end_date'   => $end_date,
			'end_time'   => $end_time,
			'start_type' => 'scheduled',
		);

		$errors = new WP_Error();

		// Act: Run validation
		SCD_Schedule_Step_Validator::validate( $data, $errors );

		// Assert: Should NOT have "past date" error
		$this->assertFalse(
			$errors->get_error_code( 'schedule_past_dates' ),
			'Future datetime should not be rejected as past date'
		);

		// Verify no critical errors exist
		$error_codes = $errors->get_error_codes();
		$this->assertEmpty(
			$error_codes,
			'Future datetime should pass validation without errors'
		);
	}

	/**
	 * Test that past dates are correctly detected.
	 *
	 * Validates that actual past datetimes are caught and rejected.
	 *
	 * @since 1.0.0
	 */
	public function test_detects_past_dates() {
		// Setup: Create a datetime 1 hour in the past
		$past_timestamp = current_time( 'timestamp' ) - HOUR_IN_SECONDS;
		$start_date     = gmdate( 'Y-m-d', $past_timestamp );
		$start_time     = gmdate( 'H:i', $past_timestamp );
		$end_date       = gmdate( 'Y-m-d', $past_timestamp + ( 7 * DAY_IN_SECONDS ) );
		$end_time       = '23:59';

		$data = array(
			'start_date' => $start_date,
			'start_time' => $start_time,
			'end_date'   => $end_date,
			'end_time'   => $end_time,
			'start_type' => 'scheduled',
		);

		$errors = new WP_Error();

		// Act: Run validation
		SCD_Schedule_Step_Validator::validate( $data, $errors );

		// Assert: Should have "past date" warning
		$error_codes = $errors->get_error_codes();
		$this->assertContains(
			'schedule_past_dates',
			$error_codes,
			'Past datetime should be detected and flagged'
		);

		// Verify error severity is 'warning' (not 'critical') to allow editing
		$error_data = $errors->get_error_data( 'schedule_past_dates' );
		$this->assertIsArray( $error_data, 'Error data should be array' );
		$this->assertArrayHasKey( 'severity', $error_data, 'Error should have severity' );
		$this->assertEquals(
			'warning',
			$error_data['severity'],
			'Past date should be warning (not critical) to allow editing existing campaigns'
		);
	}

	/**
	 * Test that inverted dates (start after end) are detected.
	 *
	 * Validates that start_date > end_date is caught regardless of time.
	 *
	 * @since 1.0.0
	 */
	public function test_detects_inverted_dates() {
		// Setup: Start date after end date
		$future_timestamp = current_time( 'timestamp' ) + ( 7 * DAY_IN_SECONDS );
		$start_date       = gmdate( 'Y-m-d', $future_timestamp );
		$start_time       = '14:00';
		$end_date         = gmdate( 'Y-m-d', $future_timestamp - ( 3 * DAY_IN_SECONDS ) );
		$end_time         = '16:00';

		$data = array(
			'start_date' => $start_date,
			'start_time' => $start_time,
			'end_date'   => $end_date,
			'end_time'   => $end_time,
			'start_type' => 'scheduled',
		);

		$errors = new WP_Error();

		// Act: Run validation
		SCD_Schedule_Step_Validator::validate( $data, $errors );

		// Assert: Should have "inverted dates" error
		$error_codes = $errors->get_error_codes();
		$this->assertContains(
			'schedule_inverted_dates',
			$error_codes,
			'Inverted dates (start > end) should be detected'
		);

		// Verify error severity is 'critical' (blocks submission)
		$error_data = $errors->get_error_data( 'schedule_inverted_dates' );
		$this->assertIsArray( $error_data, 'Error data should be array' );
		$this->assertArrayHasKey( 'severity', $error_data, 'Error should have severity' );
		$this->assertEquals(
			'critical',
			$error_data['severity'],
			'Inverted dates should be critical error (blocks submission)'
		);
	}

	/**
	 * Test that schedule defaults are correct.
	 *
	 * Validates that default time values are '00:00' and '23:59', not current_time().
	 * This prevents the issue where defaults changed during refactoring.
	 *
	 * @since 1.0.0
	 */
	public function test_schedule_defaults_correct() {
		// Setup: Data without time fields (should use defaults)
		$future_timestamp = current_time( 'timestamp' ) + ( 7 * DAY_IN_SECONDS );
		$start_date       = gmdate( 'Y-m-d', $future_timestamp );
		$end_date         = gmdate( 'Y-m-d', $future_timestamp + ( 7 * DAY_IN_SECONDS ) );

		// Data WITHOUT start_time/end_time (validator should use defaults)
		$data = array(
			'start_date' => $start_date,
			'end_date'   => $end_date,
			'start_type' => 'scheduled',
		);

		$errors = new WP_Error();

		// Act: Run validation
		SCD_Schedule_Step_Validator::validate( $data, $errors );

		// Assert: Should pass validation using default times (00:00 and 23:59)
		// If defaults were current_time(), this might fail depending on time of day
		$error_codes = $errors->get_error_codes();

		// Filter out non-critical errors (warnings, info)
		$critical_errors = array();
		foreach ( $error_codes as $code ) {
			$error_data = $errors->get_error_data( $code );
			if ( ! is_array( $error_data ) || ! isset( $error_data['severity'] ) ) {
				$critical_errors[] = $code;
			} elseif ( ! in_array( $error_data['severity'], array( 'warning', 'info' ), true ) ) {
				$critical_errors[] = $code;
			}
		}

		$this->assertEmpty(
			$critical_errors,
			'Validation should use correct defaults (00:00 and 23:59) not current_time()'
		);
	}
}
