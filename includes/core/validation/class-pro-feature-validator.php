<?php
/**
 * PRO Feature Validator
 *
 * Validates that free users cannot use PRO features.
 * Provides server-side security to prevent bypassing UI restrictions.
 *
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/validation
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * PRO Feature Validator Class
 *
 * Server-side validation for PRO features to ensure free users
 * cannot bypass UI restrictions and use premium functionality.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/validation
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_PRO_Feature_Validator {

	/**
	 * Feature gate instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Feature_Gate    $feature_gate    Feature gate.
	 */
	private $feature_gate;

	/**
	 * Constructor.
	 *
	 * @since    1.0.0
	 * @param    SCD_Feature_Gate $feature_gate    Feature gate instance.
	 */
	public function __construct( $feature_gate ) {
		$this->feature_gate = $feature_gate;
	}

	/**
	 * Validate PRO features for a wizard step.
	 *
	 * @since    1.0.0
	 * @param    string $step    Step name.
	 * @param    array  $data    Step data.
	 * @return   true|WP_Error      True if valid, WP_Error if PRO feature detected.
	 */
	public function validate_step( $step, $data ) {
		switch ( $step ) {
			case 'discounts':
				return $this->validate_discounts( $data );
			case 'schedule':
				return $this->validate_schedule( $data );
			case 'products':
				return $this->validate_products( $data );
			default:
				return true;
		}
	}

	/**
	 * Validate PRO features in complete campaign data.
	 *
	 * This is the final validation before campaign creation.
	 *
	 * @since    1.0.0
	 * @param    array $campaign_data    Complete campaign data.
	 * @return   true|WP_Error              True if valid, WP_Error if PRO feature detected.
	 */
	public function validate_campaign( $campaign_data ) {
		// Validate discount type
		$discount_validation = $this->validate_discount_type( $campaign_data );
		if ( is_wp_error( $discount_validation ) ) {
			return $discount_validation;
		}

		// Validate recurring campaigns
		$recurring_validation = $this->validate_recurring( $campaign_data );
		if ( is_wp_error( $recurring_validation ) ) {
			return $recurring_validation;
		}

		// Validate advanced filters (if present)
		$filters_validation = $this->validate_advanced_filters( $campaign_data );
		if ( is_wp_error( $filters_validation ) ) {
			return $filters_validation;
		}

		return true;
	}

	/**
	 * Validate discounts step PRO features.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $data    Step data.
	 * @return   true|WP_Error     True if valid, WP_Error if PRO feature detected.
	 */
	private function validate_discounts( $data ) {
		if ( ! isset( $data['discount_type'] ) ) {
			return true;
		}

		return $this->validate_discount_type( $data );
	}

	/**
	 * Validate discount type is allowed for current user.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $data    Data containing discount_type.
	 * @return   true|WP_Error     True if valid, WP_Error if PRO feature detected.
	 */
	private function validate_discount_type( $data ) {
		if ( ! isset( $data['discount_type'] ) ) {
			return true;
		}

		$discount_type = $data['discount_type'];
		$pro_types     = array( 'tiered', 'bogo', 'spend_threshold' );

		// If PRO discount type selected, verify user has access
		if ( in_array( $discount_type, $pro_types, true ) ) {
			if ( ! $this->feature_gate->can_use_discount_type( $discount_type ) ) {
				return new WP_Error(
					'pro_feature_required',
					sprintf(
						/* translators: %s: discount type name */
						__( 'The "%s" discount type requires a PRO license.', 'smart-cycle-discounts' ),
						$discount_type
					),
					array(
						'status'      => 403,
						'feature'     => 'discount_type_' . $discount_type,
						'upgrade_url' => $this->feature_gate->get_upgrade_url(),
					)
				);
			}
		}

		return true;
	}

	/**
	 * Validate schedule step PRO features.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $data    Step data.
	 * @return   true|WP_Error     True if valid, WP_Error if PRO feature detected.
	 */
	private function validate_schedule( $data ) {
		// Server-side safeguard: Force disable recurring for free users
		// This prevents bypassing client-side restrictions via console/API
		if ( ! $this->feature_gate->can_use_recurring_campaigns() ) {
			$data['enable_recurring'] = false;
		}

		return $this->validate_recurring( $data );
	}

	/**
	 * Validate recurring campaigns feature.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $data    Data containing enable_recurring.
	 * @return   true|WP_Error     True if valid, WP_Error if PRO feature detected.
	 */
	private function validate_recurring( $data ) {
		// Check recurring campaigns
		if ( isset( $data['enable_recurring'] ) && $data['enable_recurring'] ) {
			if ( ! $this->feature_gate->can_use_recurring_campaigns() ) {
				return new WP_Error(
					'pro_feature_required',
					__( 'Recurring campaigns require a PRO license.', 'smart-cycle-discounts' ),
					array(
						'status'      => 403,
						'feature'     => 'campaigns_recurring',
						'upgrade_url' => $this->feature_gate->get_upgrade_url(),
					)
				);
			}
		}

		return true;
	}

	/**
	 * Validate products step PRO features.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $data    Step data.
	 * @return   true|WP_Error     True if valid, WP_Error if PRO feature detected.
	 */
	private function validate_products( $data ) {
		return $this->validate_advanced_filters( $data );
	}

	/**
	 * Validate advanced product filters feature.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $data    Data containing use_advanced_filters.
	 * @return   true|WP_Error     True if valid, WP_Error if PRO feature detected.
	 */
	private function validate_advanced_filters( $data ) {
		// Check advanced product filters
		if ( isset( $data['use_advanced_filters'] ) && $data['use_advanced_filters'] ) {
			if ( ! $this->feature_gate->can_use_advanced_product_filters() ) {
				return new WP_Error(
					'pro_feature_required',
					__( 'Advanced product filters require a PRO license.', 'smart-cycle-discounts' ),
					array(
						'status'      => 403,
						'feature'     => 'campaigns_advanced_product_filters',
						'upgrade_url' => $this->feature_gate->get_upgrade_url(),
					)
				);
			}
		}

		return true;
	}
}
