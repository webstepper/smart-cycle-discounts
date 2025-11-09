<?php
/**
 * Class Test_Field_Collection
 *
 * Tests for field data collection and sanitization.
 * These tests verify PHP-side field handling and dependency validation.
 *
 * @package    Smart_Cycle_Discounts
 * @subpackage Smart_Cycle_Discounts/Tests/Unit
 */

use PHPUnit\Framework\TestCase;

/**
 * Test Field Collection class
 *
 * Tests critical field validation and sanitization logic that ensures
 * field data is properly processed before database storage.
 */
class Test_Field_Collection extends TestCase {

	/**
	 * Test that SCD_Field_Definitions class is available.
	 *
	 * Verifies the field definitions system is loaded and accessible.
	 * This is the PHP equivalent of checking SCD.Utils.Fields in JavaScript.
	 *
	 * @since 1.0.0
	 */
	public function test_field_definitions_available() {
		// Assert: Field definitions class should be loaded
		$this->assertTrue(
			class_exists( 'SCD_Field_Definitions' ),
			'SCD_Field_Definitions class must be available for validation'
		);

		// Verify the class has the required validate method
		$this->assertTrue(
			method_exists( 'SCD_Field_Definitions', 'validate' ),
			'SCD_Field_Definitions must have validate() method'
		);

		// Verify the class has the required validate method
		$this->assertTrue(
			method_exists( 'SCD_Field_Definitions', 'validate' ),
			'SCD_Field_Definitions must have validate() method'
		);
	}

	/**
	 * Test that validation fails loudly when required data is missing.
	 *
	 * Validates that the system doesn't silently ignore missing critical fields.
	 * This prevents the "silent fallback" antipattern.
	 *
	 * @since 1.0.0
	 */
	public function test_fails_loudly_on_missing_required_field() {
		// Setup: Campaign data WITHOUT required campaign_name field
		$data = array(
			'discount_type'  => 'percentage',
			'discount_value' => 50,
		);

		// Act: Attempt to validate basic step without campaign_name
		$result = SCD_Field_Definitions::validate( 'basic', $data );

		// Assert: Should return WP_Error (not silently succeed)
		$this->assertInstanceOf(
			'WP_Error',
			$result,
			'Validation should return WP_Error for missing required fields (fail loudly)'
		);

		// Verify error contains campaign_name
		$error_codes = $result->get_error_codes();
		$this->assertNotEmpty(
			$error_codes,
			'Should have error codes for missing required field'
		);

		// Find error related to campaign_name
		$has_name_error = false;
		foreach ( $error_codes as $code ) {
			$message = $result->get_error_message( $code );
			if ( false !== strpos( $message, 'campaign_name' ) || false !== strpos( $code, 'campaign_name' ) ) {
				$has_name_error = true;
				break;
			}
		}

		$this->assertTrue(
			$has_name_error,
			'Error should explicitly mention campaign_name field'
		);
	}

	/**
	 * Test that field-level validation works correctly.
	 *
	 * Validates individual field validation rules are enforced.
	 * This ensures field definitions are properly integrated.
	 *
	 * @since 1.0.0
	 */
	public function test_field_level_validation_enforced() {
		// Setup: Invalid discount value (negative number)
		$data = array(
			'discount_type'            => 'percentage',
			'discount_value_percentage' => -50, // Invalid: negative
		);

		// Act: Validate discounts step
		$result = SCD_Field_Definitions::validate( 'discounts', $data );

		// Assert: Should return WP_Error for invalid value
		$this->assertInstanceOf(
			'WP_Error',
			$result,
			'Validation should reject negative discount values'
		);

		// Verify error is critical (blocks submission)
		$error_codes = $result->get_error_codes();
		$this->assertNotEmpty( $error_codes, 'Should have error codes' );

		// Check severity of first error
		$first_code = $error_codes[0];
		$error_data = $result->get_error_data( $first_code );

		if ( is_array( $error_data ) && isset( $error_data['severity'] ) ) {
			$this->assertEquals(
				'critical',
				$error_data['severity'],
				'Invalid discount value should be critical error'
			);
		}
	}

	/**
	 * Test that valid data passes validation.
	 *
	 * Ensures the validation system allows valid data through.
	 * This prevents false positives that would block legitimate campaigns.
	 *
	 * @since 1.0.0
	 */
	public function test_valid_data_passes_validation() {
		// Setup: Valid discount data
		$data = array(
			'discount_type'             => 'percentage',
			'discount_value_percentage' => 50,
			'apply_to'                  => 'cart',
		);

		// Act: Validate discounts step
		$result = SCD_Field_Definitions::validate( 'discounts', $data );

		// Assert: Should return sanitized array (not WP_Error)
		if ( is_wp_error( $result ) ) {
			// If there are errors, they should only be warnings/info (not critical)
			$error_codes = $result->get_error_codes();
			foreach ( $error_codes as $code ) {
				$error_data = $result->get_error_data( $code );
				if ( is_array( $error_data ) && isset( $error_data['severity'] ) ) {
					$this->assertContains(
						$error_data['severity'],
						array( 'warning', 'info' ),
						"Valid data should not have critical errors. Error code: {$code}"
					);
				}
			}
		} else {
			// No errors - perfect!
			$this->assertIsArray(
				$result,
				'Valid data should return sanitized array (not WP_Error)'
			);

			// Verify discount_value_percentage was preserved
			$this->assertArrayHasKey(
				'discount_value_percentage',
				$result,
				'Sanitized data should preserve valid fields'
			);

			$this->assertEquals(
				50,
				$result['discount_value_percentage'],
				'Sanitized data should preserve field values'
			);
		}
	}
}
