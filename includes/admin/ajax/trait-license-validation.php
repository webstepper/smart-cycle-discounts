<?php
/**
 * License Validation Trait
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/trait-license-validation.php
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * License Validation Trait
 *
 * Provides three tiers of license validation for AJAX handlers:
 * - UI Level: Basic cached check (fastest)
 * - Logic Level: Standard server-validated check
 * - Critical Level: Fresh API validation (most secure)
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
trait SCD_License_Validation_Trait {

	/**
	 * Validate license for UI-level features.
	 *
	 * Uses Feature Gate's cached check for fast validation.
	 * Suitable for: UI elements, convenience features, non-critical operations.
	 *
	 * @since    1.0.0
	 * @param    string $feature_key    Optional feature key to check.
	 * @return   true|WP_Error              True if valid, WP_Error if not.
	 */
	protected function validate_license_ui( $feature_key = '' ) {
		// Get Feature Gate instance
		if ( function_exists( 'scd_get_instance' ) ) {
			$container = scd_get_instance()->get_container();
			if ( $container && $container->has( 'feature_gate' ) ) {
				$feature_gate = $container->get( 'feature_gate' );

				// Check specific feature if provided
				if ( ! empty( $feature_key ) ) {
					if ( ! $feature_gate->can_use_feature( $feature_key ) ) {
						return new WP_Error(
							'feature_locked',
							sprintf(
								/* translators: %s: Feature name */
								__( 'This feature requires a Pro license. %s', 'smart-cycle-discounts' ),
								'<a href="' . esc_url( $feature_gate->get_upgrade_url() ) . '">' . __( 'Upgrade Now', 'smart-cycle-discounts' ) . '</a>'
							)
						);
					}
				} else {
					// General premium check
					if ( ! $feature_gate->is_premium() ) {
						return new WP_Error(
							'license_required',
							sprintf(
								/* translators: Upgrade prompt */
								__( 'This feature requires a Pro license. %s', 'smart-cycle-discounts' ),
								'<a href="' . esc_url( $feature_gate->get_upgrade_url() ) . '">' . __( 'Upgrade Now', 'smart-cycle-discounts' ) . '</a>'
							)
						);
					}
				}
			}
		}

		return true;
	}

	/**
	 * Validate license for logic-level features.
	 *
	 * Uses License Manager's server-validated check (with caching).
	 * Suitable for: Business logic, calculations, data processing.
	 *
	 * @since    1.0.0
	 * @return   true|WP_Error    True if valid, WP_Error if not.
	 */
	protected function validate_license_logic() {
		// Use server-validated check
		if ( function_exists( 'scd_is_license_valid' ) ) {
			if ( ! scd_is_license_valid() ) {
				return new WP_Error(
					'license_invalid',
					sprintf(
						/* translators: Upgrade prompt */
						__( 'A valid Pro license is required for this operation. %s', 'smart-cycle-discounts' ),
						'<a href="' . esc_url( scd_get_upgrade_url() ) . '">' . __( 'Upgrade Now', 'smart-cycle-discounts' ) . '</a>'
					)
				);
			}
		} else {
			// Fallback if License Manager not available
			return $this->validate_license_ui();
		}

		return true;
	}

	/**
	 * Validate license for critical-level features.
	 *
	 * Forces fresh API validation, bypassing cache.
	 * Suitable for: Sensitive operations, exports, imports, critical data.
	 *
	 * @since    1.0.0
	 * @return   true|WP_Error    True if valid, WP_Error if not.
	 */
	protected function validate_license_critical() {
		// Force fresh validation
		if ( function_exists( 'scd_force_license_validation' ) ) {
			if ( ! scd_force_license_validation() ) {
				return new WP_Error(
					'license_verification_failed',
					sprintf(
						/* translators: Support message */
						__( 'License verification failed. Please ensure you have an active Pro license. %s', 'smart-cycle-discounts' ),
						'<a href="' . esc_url( admin_url( 'admin.php?page=smart-cycle-discounts-account' ) ) . '">' . __( 'Check License', 'smart-cycle-discounts' ) . '</a>'
					)
				);
			}
		} else {
			// Fallback to logic-level validation
			return $this->validate_license_logic();
		}

		return true;
	}

	/**
	 * Validate license with automatic tier selection.
	 *
	 * Selects validation tier based on operation type.
	 *
	 * @since    1.0.0
	 * @param    string $tier           Validation tier: 'ui', 'logic', 'critical'.
	 * @param    string $feature_key    Optional feature key for UI-level check.
	 * @return   true|WP_Error              True if valid, WP_Error if not.
	 */
	protected function validate_license( $tier = 'logic', $feature_key = '' ) {
		switch ( $tier ) {
			case 'ui':
				return $this->validate_license_ui( $feature_key );

			case 'critical':
				return $this->validate_license_critical();

			case 'logic':
			default:
				return $this->validate_license_logic();
		}
	}

	/**
	 * Check if license validation failed.
	 *
	 * Helper method to check if validation result is an error.
	 *
	 * @since    1.0.0
	 * @param    mixed $validation_result    Result from validate_license_*() method.
	 * @return   bool                           True if validation failed.
	 */
	protected function license_validation_failed( $validation_result ) {
		return is_wp_error( $validation_result );
	}

	/**
	 * Get license validation error response.
	 *
	 * Converts WP_Error to AJAX error response.
	 *
	 * @since    1.0.0
	 * @param    WP_Error $error    Validation error.
	 * @return   array                 Error response array.
	 */
	protected function license_error_response( $error ) {
		if ( ! is_wp_error( $error ) ) {
			$error = new WP_Error(
				'license_error',
				__( 'License validation failed', 'smart-cycle-discounts' )
			);
		}

		return array(
			'success' => false,
			'data'    => array(
				'message' => $error->get_error_message(),
				'code'    => $error->get_error_code(),
			),
		);
	}
}
