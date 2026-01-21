<?php
/**
 * Pro Feature Validator Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/validation/class-pro-feature-validator.php
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
 * PRO Feature Validator Class
 *
 * Server-side validation for PRO features to ensure free users
 * cannot bypass UI restrictions and use premium functionality.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/validation
 * @author     Webstepper <contact@webstepper.io>
 */
class WSSCD_PRO_Feature_Validator {

	/**
	 * Feature gate instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Feature_Gate    $feature_gate    Feature gate.
	 */
	private $feature_gate;

	/**
	 * Constructor.
	 *
	 * @since    1.0.0
	 * @param    WSSCD_Feature_Gate $feature_gate    Feature gate instance.
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
		$discount_validation = $this->validate_discount_type( $campaign_data );
		if ( is_wp_error( $discount_validation ) ) {
			return $discount_validation;
		}

		$config_validation = $this->validate_discount_configurations( $campaign_data );
		if ( is_wp_error( $config_validation ) ) {
			return $config_validation;
		}

		// Note: Recurring campaigns are now FREE - no validation needed
		// The validate_recurring() check would pass anyway since can_use_recurring_campaigns()
		// returns true for all users now that campaigns_recurring is set to 'free'

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
		// Validate discount type
		$type_validation = $this->validate_discount_type( $data );
		if ( is_wp_error( $type_validation ) ) {
			return $type_validation;
		}

		// Validate discount configurations (usage limits, application rules, etc.)
		$config_validation = $this->validate_discount_configurations( $data );
		if ( is_wp_error( $config_validation ) ) {
			return $config_validation;
		}

		return true;
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

	/**
	 * Validate discount configurations feature.
	 *
	 * Checks if free users are trying to use PRO discount configuration options
	 * like usage limits, application rules, or combination policies.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $data    Data containing discount configurations.
	 * @return   true|WP_Error     True if valid, WP_Error if PRO feature detected.
	 */
	private function validate_discount_configurations( $data ) {
		// If user has PRO access, allow all configurations
		if ( $this->feature_gate->can_use_discount_configurations() ) {
			return true;
		}

		// PRO configuration fields that free users cannot use
		$pro_config_fields = array(
			'usage_limit_per_customer' => __( 'per-customer usage limits', 'smart-cycle-discounts' ),
			'total_usage_limit'        => __( 'total usage limits', 'smart-cycle-discounts' ),
			'lifetime_usage_cap'       => __( 'lifetime usage caps', 'smart-cycle-discounts' ),
			'max_discount_amount'      => __( 'maximum discount amount', 'smart-cycle-discounts' ),
			'minimum_quantity'         => __( 'minimum quantity requirements', 'smart-cycle-discounts' ),
			'minimum_order_amount'     => __( 'minimum order requirements', 'smart-cycle-discounts' ),
		);

		// Check if any PRO config field has a non-empty/non-default value
		foreach ( $pro_config_fields as $field => $label ) {
			if ( isset( $data[ $field ] ) && ! empty( $data[ $field ] ) && 0 !== (int) $data[ $field ] ) {
				return new WP_Error(
					'pro_feature_required',
					sprintf(
						/* translators: %s: configuration feature name */
						__( 'Setting %s requires a PRO license.', 'smart-cycle-discounts' ),
						$label
					),
					array(
						'status'      => 403,
						'feature'     => 'discount_configurations',
						'upgrade_url' => $this->feature_gate->get_upgrade_url(),
					)
				);
			}
		}

		// Check boolean/select configurations that differ from free defaults
		// stack_with_others: default is false (can't stack) - if true, requires PRO
		if ( isset( $data['stack_with_others'] ) && $data['stack_with_others'] ) {
			return new WP_Error(
				'pro_feature_required',
				__( 'Campaign stacking policy requires a PRO license.', 'smart-cycle-discounts' ),
				array(
					'status'      => 403,
					'feature'     => 'discount_configurations',
					'upgrade_url' => $this->feature_gate->get_upgrade_url(),
				)
			);
		}

		// allow_coupons: default is true - if false (blocking coupons), requires PRO
		if ( isset( $data['allow_coupons'] ) && ! $data['allow_coupons'] ) {
			return new WP_Error(
				'pro_feature_required',
				__( 'Coupon policy settings require a PRO license.', 'smart-cycle-discounts' ),
				array(
					'status'      => 403,
					'feature'     => 'discount_configurations',
					'upgrade_url' => $this->feature_gate->get_upgrade_url(),
				)
			);
		}

		// apply_to_sale_items: default is true - if false (excluding sale items), requires PRO
		if ( isset( $data['apply_to_sale_items'] ) && ! $data['apply_to_sale_items'] ) {
			return new WP_Error(
				'pro_feature_required',
				__( 'Sale item exclusion settings require a PRO license.', 'smart-cycle-discounts' ),
				array(
					'status'      => 403,
					'feature'     => 'discount_configurations',
					'upgrade_url' => $this->feature_gate->get_upgrade_url(),
				)
			);
		}

		return true;
	}
}
