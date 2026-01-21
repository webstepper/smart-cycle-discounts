<?php
/**
 * Ajax Validation Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/validation/class-ajax-validation.php
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
 * WSSCD_AJAX_Validation Class
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/validation
 * @author     Webstepper <contact@webstepper.io>
 */
class WSSCD_AJAX_Validation {

	/**
	 * Validate AJAX request data
	 *
	 * @since    1.0.0
	 * @param    array  $data       AJAX request data
	 * @param    string $context    Optional specific context
	 * @return   array|WP_Error       Validated data or error
	 */
	public static function validate( array $data, $context = null ) {
		// If specific context provided, use it
		if ( $context && method_exists( __CLASS__, 'validate_' . $context ) ) {
			$method = 'validate_' . $context;
			return self::$method( $data );
		}

		// Otherwise, determine context from action
		if ( isset( $data['wsscd_action'] ) ) {
			return self::validate_by_action( $data );
		}

		return new WP_Error( 'missing_action', __( 'AJAX action is required', 'smart-cycle-discounts' ) );
	}

	/**
	 * Validate based on action
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $data    AJAX data
	 * @return   array|WP_Error     Validated data or error
	 */
	private static function validate_by_action( array $data ) {
		$errors    = new WP_Error();
		$validated = array();

		$nonce_valid = false;
		if ( isset( $data['nonce'] ) ) {
			$nonce = sanitize_text_field( $data['nonce'] );
			// Try wizard nonce first (used by wizard pages)
			if ( wp_verify_nonce( $nonce, 'wsscd_wizard_nonce' ) ) {
				$nonce_valid = true;
			}
			// Fallback to ajax nonce (used by other AJAX requests)
			elseif ( wp_verify_nonce( $nonce, 'wsscd_ajax_nonce' ) ) {
				$nonce_valid = true;
			}
		}

		if ( ! $nonce_valid ) {
			$errors->add( 'invalid_nonce', __( 'Security check failed', 'smart-cycle-discounts' ) );
			return $errors;
		}

		$validated['wsscd_action'] = sanitize_key( $data['wsscd_action'] );

		if ( strlen( serialize( $data ) ) > WSSCD_Validation_Rules::MAX_REQUEST_SIZE ) {
			$errors->add( 'request_too_large', __( 'Request data is too large', 'smart-cycle-discounts' ) );
			return $errors;
		}

		// Route to specific validation based on action
		switch ( $validated['wsscd_action'] ) {
			case 'save_draft':
				return self::validate_save_draft( $data, $validated );

			case 'delete_draft':
				return self::validate_delete_draft( $data, $validated );

			case 'clear_wizard_session':
				// Simple action, just return validated nonce and action
				return $validated;

			default:
				if ( isset( $data['draft_action'] ) ) {
					return self::validate_draft_action( $data, $validated );
				}
				// For unknown actions, return basic validated data
				return $validated;
		}
	}

	/**
	 * Validate save draft action
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $data        Original data
	 * @param    array $validated   Already validated data
	 * @return   array|WP_Error        Validated data or error
	 */
	private static function validate_save_draft( array $data, array $validated ) {
		if ( isset( $data['save_as_draft'] ) ) {
			$validated['save_as_draft'] = rest_sanitize_boolean( $data['save_as_draft'] );
		}

		if ( isset( $data['campaign_id'] ) ) {
			$validated['campaign_id'] = absint( $data['campaign_id'] );
		}

		if ( isset( $data['step'] ) ) {
			$validated['step'] = sanitize_key( $data['step'] );
		}

		return $validated;
	}

	/**
	 * Validate delete draft action
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $data        Original data
	 * @param    array $validated   Already validated data
	 * @return   array|WP_Error        Validated data or error
	 */
	private static function validate_delete_draft( array $data, array $validated ) {
		if ( isset( $data['draft_id'] ) ) {
			$validated['draft_id'] = sanitize_text_field( $data['draft_id'] );
		}

		if ( isset( $data['draft_type'] ) ) {
			$type        = sanitize_key( $data['draft_type'] );
			$valid_types = array( 'session', 'database' );
			if ( in_array( $type, $valid_types, true ) ) {
				$validated['draft_type'] = $type;
			}
		}

		return $validated;
	}

	/**
	 * Validate product meta AJAX data
	 *
	 * @since    1.0.0
	 * @param    array $data    Product meta data
	 * @return   array|WP_Error     Validated data or error
	 */
	public static function validate_product_meta( array $data ) {
		$errors    = new WP_Error();
		$validated = array();

		$nonce = isset( $data['wsscd_product_meta_nonce'] ) ? sanitize_text_field( $data['wsscd_product_meta_nonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, 'wsscd_product_meta' ) ) {
			$errors->add( 'invalid_nonce', __( 'Security check failed', 'smart-cycle-discounts' ) );
			return $errors;
		}

		if ( isset( $data['wsscd_exclude_from_discounts'] ) ) {
			$validated['wsscd_exclude_from_discounts'] = rest_sanitize_boolean( $data['wsscd_exclude_from_discounts'] );
		}

		if ( isset( $data['wsscd_max_discount_percentage'] ) ) {
			$percentage = absint( $data['wsscd_max_discount_percentage'] );
			if ( $percentage > 0 && $percentage <= 100 ) {
				$validated['wsscd_max_discount_percentage'] = $percentage;
			} elseif ( $percentage > 100 ) {
				$errors->add( 'invalid_max_discount', __( 'Maximum discount cannot exceed 100%', 'smart-cycle-discounts' ) );
			}
		}

		if ( isset( $data['wsscd_custom_priority'] ) ) {
			$priority = absint( $data['wsscd_custom_priority'] );
			if ( $priority >= 1 && $priority <= 5 ) {
				$validated['wsscd_custom_priority'] = $priority;
			} else {
				$errors->add( 'invalid_priority', __( 'Priority must be between 1 and 5', 'smart-cycle-discounts' ) );
			}
		}

		return $errors->has_errors() ? $errors : $validated;
	}

	/**
	 * Validate draft action data
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $data        Original data
	 * @param    array $validated   Already validated data
	 * @return   array|WP_Error        Validated data or error
	 */
	private static function validate_draft_action( array $data, array $validated ) {
		if ( isset( $data['draft_action'] ) ) {
			$valid_actions = array( 'save', 'delete', 'list', 'preview', 'default', 'complete' );
			$action        = sanitize_key( $data['draft_action'] );
			if ( in_array( $action, $valid_actions, true ) ) {
				$validated['draft_action'] = $action;
			} else {
				// Log invalid draft_action for debugging
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional debug logging when WP_DEBUG is enabled.
					error_log( '[AJAX_Validation] Invalid draft_action: ' . $action . ' (expected one of: ' . implode( ', ', $valid_actions ) . ')' );
				}
			}
		}

		if ( isset( $data['draft_type'] ) ) {
			$valid_types = array( 'session', 'campaign', 'database' );
			$type        = sanitize_key( $data['draft_type'] );
			if ( in_array( $type, $valid_types, true ) ) {
				$validated['draft_type'] = $type;
			}
		}

		if ( isset( $data['draft_id'] ) ) {
			$validated['draft_id'] = sanitize_text_field( $data['draft_id'] );
		}

		if ( isset( $data['page'] ) ) {
			$validated['page'] = absint( $data['page'] );
		}

		if ( isset( $data['save_as_draft'] ) ) {
			$validated['save_as_draft'] = rest_sanitize_boolean( $data['save_as_draft'] );
		}

		return $validated;
	}
}
