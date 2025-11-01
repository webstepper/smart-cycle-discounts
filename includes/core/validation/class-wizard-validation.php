<?php
/**
 * Wizard Validation Class
 *
 * Handles all validation specific to the campaign creation wizard.
 * Separates wizard-specific validation logic from general validation.
 *
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/validation
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wizard Validation Class
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/validation
 */
class SCD_Wizard_Validation {

	/**
	 * NOTE: Field definitions removed - now using SCD_Field_Definitions as single source of truth.
	 * This class delegates to SCD_Field_Definitions to avoid duplication and prevent bugs.
	 */

	/**
	 * Validate wizard step data.
	 *
	 * @since    1.0.0
	 * @param    array  $data    Data to validate.
	 * @param    string $step    Wizard step.
	 * @return   array|WP_Error      Sanitized data or error.
	 */
	public static function validate_step( array $data, $step ) {
		$step = sanitize_key( $step );

		if ( ! self::is_valid_step( $step ) ) {
			return self::create_unknown_step_error( $step );
		}

		self::ensure_field_definitions_loaded();

		return SCD_Field_Definitions::validate( $step, $data );
	}

	/**
	 * Check if step is valid.
	 *
	 * @since    1.0.0
	 * @param    string $step    Step name.
	 * @return   bool                Is valid.
	 */
	private static function is_valid_step( string $step ): bool {
		return in_array( $step, array( 'basic', 'products', 'discounts', 'schedule' ), true );
	}

	/**
	 * Create unknown step error.
	 *
	 * @since    1.0.0
	 * @param    string $step    Step name.
	 * @return   WP_Error           Error object.
	 */
	private static function create_unknown_step_error( string $step ): WP_Error {
		return new WP_Error(
			'unknown_step',
			sprintf( __( 'Unknown wizard step: %s', 'smart-cycle-discounts' ), $step )
		);
	}

	/**
	 * Ensure field definitions class is loaded.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private static function ensure_field_definitions_loaded(): void {
		if ( ! class_exists( 'SCD_Field_Definitions' ) ) {
			require_once SCD_PLUGIN_DIR . 'includes/core/validation/class-field-definitions.php';
		}
	}

	/**
	 * Sanitize wizard step data.
	 *
	 * Delegates to SCD_Field_Definitions as single source of truth.
	 *
	 * @since    1.0.0
	 * @param    array  $data    Data to sanitize.
	 * @param    string $step    Step name.
	 * @return   array|WP_Error     Sanitized data or error.
	 */
	public static function sanitize_step_data( array $data, $step ) {
		self::ensure_field_definitions_loaded();

		// Delegate to Field Definitions (single source of truth)
		return SCD_Field_Definitions::sanitize_only( $data, $step );
	}

	/**
	 * NOTE: Removed duplicate sanitization methods.
	 * All sanitization now delegated to SCD_Field_Definitions (single source of truth).
	 * Deleted methods:
	 * - sanitize_fields()
	 * - sanitize_by_type()
	 * - sanitize_int_array()
	 * - sanitize_category_array()
	 */

	/**
	 * Validate complete campaign data.
	 *
	 * @since    1.0.0
	 * @param    array $data    Complete campaign data.
	 * @return   array|WP_Error     Validated data or error.
	 */
	public static function validate_complete_campaign( array $data ) {
		$errors    = new WP_Error();
		$validated = array();

		$validated = self::validate_all_steps( $data, $errors );

		if ( ! $errors->has_errors() ) {
			$errors = self::validate_cross_step_dependencies( $validated, $errors );
		}

		return $errors->has_errors() ? $errors : $validated;
	}

	/**
	 * Validate all steps.
	 *
	 * @since    1.0.0
	 * @param    array    $data      Campaign data.
	 * @param    WP_Error $errors    Error object.
	 * @return   array                 Validated data.
	 */
	private static function validate_all_steps( array $data, WP_Error $errors ): array {
		$validated = array();
		$steps     = array( 'basic', 'products', 'discounts', 'schedule' );

		foreach ( $steps as $step ) {
			$result = self::validate_single_step( $data, $step, $errors );
			if ( null !== $result ) {
				$validated[ $step ] = $result;
			}
		}

		return $validated;
	}

	/**
	 * Validate single step.
	 *
	 * @since    1.0.0
	 * @param    array    $data      Campaign data.
	 * @param    string   $step      Step name.
	 * @param    WP_Error $errors    Error object.
	 * @return   array|null            Validated data or null.
	 */
	private static function validate_single_step( array $data, string $step, WP_Error $errors ): ?array {
		if ( ! isset( $data[ $step ] ) ) {
			$errors->add(
				'missing_step',
				sprintf( __( 'Missing required step data: %s', 'smart-cycle-discounts' ), $step )
			);
			return null;
		}

		$step_result = self::validate_step( $data[ $step ], $step );

		if ( is_wp_error( $step_result ) ) {
			self::add_step_errors( $errors, $step_result, $step );
			return null;
		}

		return $step_result;
	}

	/**
	 * Add step errors to main error object.
	 *
	 * @since    1.0.0
	 * @param    WP_Error $errors         Main error object.
	 * @param    WP_Error $step_errors    Step errors.
	 * @param    string   $step           Step name.
	 * @return   void
	 */
	private static function add_step_errors( WP_Error $errors, WP_Error $step_errors, string $step ): void {
		foreach ( $step_errors->get_error_codes() as $code ) {
			$errors->add(
				$step . '_' . $code,
				$step_errors->get_error_message( $code ),
				$step_errors->get_error_data( $code )
			);
		}
	}

	/**
	 * Validate AJAX navigation data.
	 *
	 * @since    1.0.0
	 * @param    array $data    Navigation data.
	 * @return   array|WP_Error     Validated data or error.
	 */
	public static function validate_navigation( array $data ) {
		$errors    = new WP_Error();
		$validated = array();

		self::validate_navigation_fields( $data, $validated, $errors );
		self::validate_step_data_field( $data, $validated );
		self::validate_request_size( $data, $errors );

		return $errors->has_errors() ? $errors : $validated;
	}

	/**
	 * Validate navigation fields.
	 *
	 * @since    1.0.0
	 * @param    array    $data         Navigation data.
	 * @param    array    &$validated   Validated data.
	 * @param    WP_Error $errors       Error object.
	 * @return   void
	 */
	private static function validate_navigation_fields( array $data, array &$validated, WP_Error $errors ): void {
		$nav_fields = array(
			'navigation_action' => array(
				'type'    => 'choice',
				'choices' => array( 'next', 'previous', 'complete' ),
				'error'   => 'invalid_navigation_action',
			),
			'current_step'      => array(
				'type' => 'key',
			),
			'target_step'       => array(
				'type' => 'key',
			),
			'launch_status'     => array(
				'type'    => 'choice',
				'choices' => array( 'active', 'draft' ),
				'error'   => 'invalid_launch_status',
			),
		);

		foreach ( $nav_fields as $field => $config ) {
			if ( ! isset( $data[ $field ] ) ) {
				continue;
			}

			$value = sanitize_key( $data[ $field ] );

			if ( 'choice' === $config['type'] && ! in_array( $value, $config['choices'], true ) ) {
				$errors->add( $config['error'], __( 'Invalid value', 'smart-cycle-discounts' ) );
			} else {
				$validated[ $field ] = $value;
			}
		}
	}

	/**
	 * Validate step data field.
	 *
	 * @since    1.0.0
	 * @param    array $data         Navigation data.
	 * @param    array &$validated   Validated data.
	 * @return   void
	 */
	private static function validate_step_data_field( array $data, array &$validated ): void {
		if ( ! isset( $data['step_data'] ) || ! is_array( $data['step_data'] ) ) {
			return;
		}

		$step = $validated['current_step'] ?? '';

		if ( $step && self::is_valid_step( $step ) ) {
			$validated['step_data'] = self::sanitize_step_data( $data['step_data'], $step );
		} else {
			// MEDIUM: Reject unknown steps instead of weak fallback sanitization
			// Previously used array_map('sanitize_text_field') which doesn't handle nested data
			$validated['step_data'] = array();
		}
	}

	/**
	 * Validate request size.
	 *
	 * @since    1.0.0
	 * @param    array    $data      Request data.
	 * @param    WP_Error $errors    Error object.
	 * @return   void
	 */
	private static function validate_request_size( array $data, WP_Error $errors ): void {
		if ( strlen( serialize( $data ) ) > SCD_Validation_Rules::MAX_REQUEST_SIZE ) {
			$errors->add( 'request_too_large', __( 'Request data is too large', 'smart-cycle-discounts' ) );
		}
	}


	/**
	 * NOTE: Removed duplicate complex field sanitization methods.
	 * All complex field sanitization now handled by SCD_Field_Definitions.
	 * Deleted methods:
	 * - sanitize_tiers() (delegates to Field_Definitions)
	 * - sanitize_thresholds() (delegates to Field_Definitions)
	 * - sanitize_time_slots() (delegates to Field_Definitions)
	 */

	/**
	 * Validate cross-step dependencies.
	 *
	 * @since    1.0.0
	 * @param    array    $validated    Validated data.
	 * @param    WP_Error $errors       Error object.
	 * @return   WP_Error                  Updated errors.
	 */
	private static function validate_cross_step_dependencies( array $validated, WP_Error $errors ): WP_Error {
		self::validate_date_range( $validated, $errors );
		self::validate_product_selection( $validated, $errors );

		return $errors;
	}

	/**
	 * Validate date range.
	 *
	 * TIMEZONE HANDLING:
	 * All date calculations use wp_timezone() for consistency with WordPress settings.
	 * This ensures campaigns respect the site's configured timezone and handle
	 * daylight saving time transitions correctly.
	 *
	 * @since    1.0.0
	 * @param    array    $validated    Validated data.
	 * @param    WP_Error $errors       Error object.
	 * @return   void
	 */
	private static function validate_date_range( array $validated, WP_Error $errors ): void {
		// Skip date validation for immediate campaigns - server sets the start time
		if ( isset( $validated['schedule']['start_type'] ) && 'immediate' === $validated['schedule']['start_type'] ) {
			// Only validate end_date if provided
			if ( ! empty( $validated['schedule']['end_date'] ) ) {
				try {
					// CRITICAL: Use wp_timezone() for consistent timezone handling across the plugin
					$timezone = wp_timezone();
					$end_date = new DateTime( $validated['schedule']['end_date'], $timezone );
					$now      = new DateTime( 'now', $timezone );

					// Validate end_date is in the future
					if ( $end_date < $now ) {
						$errors->add( 'end_date_past', __( 'End date must be in the future', 'smart-cycle-discounts' ) );
					}
				} catch ( Exception $e ) {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( 'SCD End date validation error: ' . $e->getMessage() );
					}
					$errors->add( 'invalid_end_date_format', __( 'Invalid end date format', 'smart-cycle-discounts' ) );
				}
			}
			return;
		}

		// For scheduled campaigns, validate both start and end dates
		if ( ! isset( $validated['schedule']['start_date'], $validated['schedule']['end_date'] ) ) {
			return;
		}

		// MEDIUM: Skip if end_date is empty (indefinite campaign)
		if ( empty( $validated['schedule']['end_date'] ) ) {
			return;
		}

		try {
			// CRITICAL: Use wp_timezone() for consistent timezone handling
			$timezone   = wp_timezone();
			$start_date = new DateTime( $validated['schedule']['start_date'], $timezone );
			$end_date   = new DateTime( $validated['schedule']['end_date'], $timezone );
			$now        = new DateTime( 'now', $timezone );

			// MEDIUM: Validate end after start
			if ( $start_date >= $end_date ) {
				$errors->add( 'invalid_date_range', __( 'End date must be after start date', 'smart-cycle-discounts' ) );
			}

			// MEDIUM: Validate start is not in the past (with 5-minute grace period)
			// Grace period allows for slight clock differences and processing time
			$grace_period = new DateInterval( 'PT5M' );
			$now->sub( $grace_period );
			if ( 'scheduled' === $validated['schedule']['start_type'] && $start_date < $now ) {
				$errors->add( 'start_date_past', __( 'Start date cannot be in the past', 'smart-cycle-discounts' ) );
			}

			// MEDIUM: Validate maximum duration
			$duration_days = $start_date->diff( $end_date )->days;
			if ( $duration_days > SCD_Validation_Rules::SCHEDULE_MAX_DURATION_DAYS ) {
				$errors->add(
					'duration_too_long',
					sprintf(
						__( 'Campaign duration cannot exceed %d days', 'smart-cycle-discounts' ),
						SCD_Validation_Rules::SCHEDULE_MAX_DURATION_DAYS
					)
				);
			}
		} catch ( Exception $e ) {
			// MEDIUM: Log the error for debugging
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'SCD Date validation error: ' . $e->getMessage() );
			}
			$errors->add( 'invalid_date_format', __( 'Invalid date format', 'smart-cycle-discounts' ) );
		}
	}

	/**
	 * Validate product selection.
	 *
	 * @since    1.0.0
	 * @param    array    $validated    Validated data.
	 * @param    WP_Error $errors       Error object.
	 * @return   void
	 */
	private static function validate_product_selection( array $validated, WP_Error $errors ): void {
		if ( ! isset( $validated['products']['product_selection_type'] ) ) {
			return;
		}

		$selection_type = $validated['products']['product_selection_type'];
		$requirements   = array(
			'specific_products' => array(
				'field'   => 'product_ids',
				'message' => __( 'Please select at least one product', 'smart-cycle-discounts' ),
				'error'   => 'missing_product_ids',
			),
			'random_products'   => array(
				'field'   => 'random_count',
				'message' => __( 'Please specify number of random products', 'smart-cycle-discounts' ),
				'error'   => 'missing_random_count',
			),
		);

		if ( isset( $requirements[ $selection_type ] ) ) {
			$req = $requirements[ $selection_type ];
			if ( empty( $validated['products'][ $req['field'] ] ) ) {
				$errors->add( $req['error'], $req['message'] );
			}
		}
	}

	/**
	 * Get wizard constants.
	 *
	 * @since    1.0.0
	 * @return   array    Constants for JavaScript.
	 */
	public static function get_wizard_constants(): array {
		return array(
			'steps'            => array( 'basic', 'products', 'discounts', 'schedule', 'review' ),
			'discount_types'   => SCD_Validation_Rules::DISCOUNT_TYPES,
			'selection_types'  => SCD_Validation_Rules::SELECTION_TYPES,
			'schedule_types'   => SCD_Validation_Rules::SCHEDULE_TYPES,
			'max_request_size' => SCD_Validation_Rules::MAX_REQUEST_SIZE,
		);
	}

	/**
	 * Validate compiled campaign data.
	 *
	 * This context is for already-compiled campaign data from the Campaign Creator Service.
	 * The data has already been transformed from step-based structure to flat campaign structure,
	 * so we skip step-by-step validation and validate the compiled fields directly.
	 *
	 * @since    1.0.0
	 * @param    array $data    Compiled campaign data (flat structure).
	 * @return   array|WP_Error     Validated data or error.
	 */
	public static function validate_compiled_campaign( array $data ) {
		$errors    = new WP_Error();
		$validated = array();

		// Validate required campaign fields
		self::validate_required_campaign_fields( $data, $validated, $errors );

		// Validate discount configuration
		self::validate_discount_config( $data, $validated, $errors );

		// Validate product configuration
		self::validate_product_config( $data, $validated, $errors );

		// Validate schedule configuration
		self::validate_schedule_config( $data, $validated, $errors );

		return $errors->has_errors() ? $errors : $validated;
	}

	/**
	 * Validate campaign update data.
	 *
	 * This context is for updating existing campaigns.
	 * Some fields may be optional (not all fields need to be present for updates).
	 *
	 * @since    1.0.0
	 * @param    array $data    Update data.
	 * @return   array|WP_Error     Validated data or error.
	 */
	public static function validate_campaign_update( array $data ) {
		$errors    = new WP_Error();
		$validated = array();

		// Only validate fields that are present (partial updates allowed)
		if ( isset( $data['name'] ) ) {
			$validated['name'] = sanitize_text_field( $data['name'] );
			if ( empty( $validated['name'] ) ) {
				$errors->add( 'invalid_name', __( 'Campaign name cannot be empty', 'smart-cycle-discounts' ) );
			}
		}

		if ( isset( $data['status'] ) ) {
			$allowed_statuses = array( 'draft', 'scheduled', 'active', 'paused', 'expired', 'archived' );
			$status           = sanitize_key( $data['status'] );
			if ( ! in_array( $status, $allowed_statuses, true ) ) {
				$errors->add( 'invalid_status', __( 'Invalid campaign status', 'smart-cycle-discounts' ) );
			} else {
				$validated['status'] = $status;
			}
		}

		// Validate other update fields as needed
		if ( isset( $data['priority'] ) ) {
			$priority = absint( $data['priority'] );
			if ( $priority < 1 || $priority > 5 ) {
				$errors->add( 'invalid_priority', __( 'Priority must be between 1 and 5', 'smart-cycle-discounts' ) );
			} else {
				$validated['priority'] = $priority;
			}
		}

		return $errors->has_errors() ? $errors : $validated;
	}

	/**
	 * Validate required campaign fields.
	 *
	 * @since    1.0.0
	 * @param    array    $data         Campaign data.
	 * @param    array    &$validated   Validated data.
	 * @param    WP_Error $errors       Error object.
	 * @return   void
	 */
	private static function validate_required_campaign_fields( array $data, array &$validated, WP_Error $errors ): void {
		// Campaign name
		if ( empty( $data['name'] ) ) {
			$errors->add( 'missing_name', __( 'Campaign name is required', 'smart-cycle-discounts' ) );
		} else {
			$validated['name'] = sanitize_text_field( $data['name'] );
		}

		// Priority
		if ( isset( $data['priority'] ) ) {
			$validated['priority'] = absint( $data['priority'] );
		} else {
			$validated['priority'] = 5; // Default priority
		}

		// Status
		if ( isset( $data['status'] ) ) {
			$validated['status'] = sanitize_key( $data['status'] );
		}
	}

	/**
	 * Validate discount configuration.
	 *
	 * @since    1.0.0
	 * @param    array    $data         Campaign data.
	 * @param    array    &$validated   Validated data.
	 * @param    WP_Error $errors       Error object.
	 * @return   void
	 */
	private static function validate_discount_config( array $data, array &$validated, WP_Error $errors ): void {
		// Discount type
		if ( empty( $data['discount_type'] ) ) {
			$errors->add( 'missing_discount_type', __( 'Discount type is required', 'smart-cycle-discounts' ) );
			return;
		}

		$validated['discount_type'] = sanitize_key( $data['discount_type'] );

		// Discount value (type-specific validation)
		if ( 'percentage' === $validated['discount_type'] && isset( $data['discount_value'] ) ) {
			$value = floatval( $data['discount_value'] );
			if ( $value <= 0 || $value > 100 ) {
				$errors->add( 'invalid_percentage', __( 'Percentage must be between 0 and 100', 'smart-cycle-discounts' ) );
			} else {
				$validated['discount_value'] = $value;
			}
		} elseif ( 'fixed' === $validated['discount_type'] && isset( $data['discount_value'] ) ) {
			$value = floatval( $data['discount_value'] );
			if ( $value <= 0 ) {
				$errors->add( 'invalid_fixed_amount', __( 'Fixed amount must be greater than 0', 'smart-cycle-discounts' ) );
			} else {
				$validated['discount_value'] = $value;
			}
		}
	}

	/**
	 * Validate product configuration.
	 *
	 * @since    1.0.0
	 * @param    array    $data         Campaign data.
	 * @param    array    &$validated   Validated data.
	 * @param    WP_Error $errors       Error object.
	 * @return   void
	 */
	private static function validate_product_config( array $data, array &$validated, WP_Error $errors ): void {
		// Product selection type
		if ( empty( $data['product_selection_type'] ) ) {
			$errors->add( 'missing_selection_type', __( 'Product selection type is required', 'smart-cycle-discounts' ) );
			return;
		}

		$validated['product_selection_type'] = sanitize_key( $data['product_selection_type'] );

		// Validate based on selection type
		if ( 'specific_products' === $validated['product_selection_type'] ) {
			if ( empty( $data['product_ids'] ) || ! is_array( $data['product_ids'] ) ) {
				$errors->add( 'missing_product_ids', __( 'Product IDs are required for specific products selection', 'smart-cycle-discounts' ) );
			} else {
				$validated['product_ids'] = array_map( 'absint', $data['product_ids'] );
			}
		}
	}

	/**
	 * Validate schedule configuration.
	 *
	 * @since    1.0.0
	 * @param    array    $data         Campaign data.
	 * @param    array    &$validated   Validated data.
	 * @param    WP_Error $errors       Error object.
	 * @return   void
	 */
	private static function validate_schedule_config( array $data, array &$validated, WP_Error $errors ): void {
		// Start date
		if ( isset( $data['starts_at'] ) && ! empty( $data['starts_at'] ) ) {
			$validated['starts_at'] = sanitize_text_field( $data['starts_at'] );
		}

		// End date
		if ( isset( $data['ends_at'] ) && ! empty( $data['ends_at'] ) ) {
			$validated['ends_at'] = sanitize_text_field( $data['ends_at'] );
		}

		// Timezone
		if ( isset( $data['timezone'] ) ) {
			$validated['timezone'] = sanitize_text_field( $data['timezone'] );
		}
	}
}
