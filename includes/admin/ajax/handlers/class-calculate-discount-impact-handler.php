<?php
/**
 * Calculate Discount Impact Handler Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers/class-calculate-discount-impact-handler.php
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// Load wizard helpers trait
require_once SCD_INCLUDES_DIR . 'admin/ajax/trait-wizard-helpers.php';

/**
 * Calculate Discount Impact Handler Class
 *
 * Calculates example price conversions and revenue impact estimates.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Calculate_Discount_Impact_Handler extends SCD_Abstract_Ajax_Handler {
	use SCD_Wizard_Helpers;

	/**
	 * Constructor.
	 *
	 * @since    1.0.0
	 * @param    SCD_Logger $logger    Logger instance (optional).
	 */
	public function __construct( $logger = null ) {
		parent::__construct( $logger );
	}

	/**
	 * Get AJAX action name.
	 *
	 * @since    1.0.0
	 * @return   string    Action name.
	 */
	protected function get_action_name() {
		return 'scd_calculate_discount_impact';
	}

	/**
	 * Handle the calculate discount impact request.
	 *
	 * @since    1.0.0
	 * @param    array $request    Request data.
	 * @return   array               Response data.
	 */
	protected function handle( $request ) {
		// NOTE: Discount impact preview is FREE - shows what discount WOULD do (teaser feature)
		// License protection happens at campaign SAVE level (in save-step-handler)

		// Get validated wizard state using trait helper
		$wizard_state = $this->_get_validated_wizard_state( __( 'Configure discount to see impact', 'smart-cycle-discounts' ) );

		if ( is_wp_error( $wizard_state ) ) {
			// Handle specific error codes
			if ( 'no_discount_configured' === $wizard_state->get_error_code() ) {
				return $this->success(
					array(
						'has_data' => false,
						'message'  => $wizard_state->get_error_message(),
					)
				);
			}
			return $this->error(
				$wizard_state->get_error_message(),
				$wizard_state->get_error_code(),
				500
			);
		}

		// Extract validated data
		$product_ids    = $wizard_state['product_ids'];
		$discounts_data = $wizard_state['discounts_data'];

		if ( empty( $product_ids ) ) {
			return $this->success(
				array(
					'has_data' => false,
					'message'  => __( 'Select products to see impact', 'smart-cycle-discounts' ),
				)
			);
		}

		// Calculate impact
		$impact = $this->_calculate_impact( $product_ids, $discounts_data );

		return $this->success( $impact );
	}

	/**
	 * Calculate discount impact.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $product_ids      Product IDs.
	 * @param    array $discounts_data   Discount configuration.
	 * @return   array                      Impact data.
	 */
	private function _calculate_impact( $product_ids, $discounts_data ) {
		$discount_type  = $discounts_data['discount_type'];
		$discount_value = isset( $discounts_data['discount_value'] ) ? floatval( $discounts_data['discount_value'] ) : 0;

		// Sample products for examples (max 5)
		$sample_ids = array_slice( $product_ids, 0, 5 );
		$examples   = array();

		// Calculate total revenue impact
		$total_original   = 0;
		$total_discounted = 0;

		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				continue;
			}

			$original_price = floatval( $product->get_regular_price() );
			if ( $original_price <= 0 ) {
				$original_price = floatval( $product->get_price() );
			}

			if ( $original_price <= 0 ) {
				continue;
			}

			$discounted_price = $this->_apply_discount( $original_price, $discount_type, $discount_value );

			$total_original   += $original_price;
			$total_discounted += $discounted_price;

			// Add to examples if in sample
			if ( in_array( $product_id, $sample_ids, true ) ) {
				$examples[] = array(
					'name'       => $product->get_name(),
					'original'   => $original_price,
					'discounted' => $discounted_price,
					'savings'    => $original_price - $discounted_price,
				);
			}
		}

		$revenue_difference = $total_discounted - $total_original;
		$percentage_impact  = $total_original > 0 ? round( ( $revenue_difference / $total_original ) * 100 ) : 0;

		return array(
			'has_data'       => true,
			'discount_type'  => $discount_type,
			'discount_value' => $discount_value,
			'product_count'  => count( $product_ids ),
			'examples'       => $examples,
			'revenue'        => array(
				'original'   => $total_original,
				'discounted' => $total_discounted,
				'difference' => $revenue_difference,
				'percentage' => $percentage_impact,
			),
			'warning'        => $this->_get_warning( $discount_type, $discount_value, $percentage_impact ),
		);
	}

	/**
	 * Get warning message based on discount impact.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $discount_type        Discount type.
	 * @param    float  $discount_value       Discount value.
	 * @param    int    $percentage_impact    Revenue impact percentage.
	 * @return   string|null                     Warning message or null.
	 */
	private function _get_warning( $discount_type, $discount_value, $percentage_impact ) {
		if ( 'percentage' === $discount_type && $discount_value >= 70 ) {
			return __( 'High discount! Review profit margins carefully', 'smart-cycle-discounts' );
		}

		if ( $percentage_impact <= -50 ) {
			return __( 'Significant revenue impact - verify this is intentional', 'smart-cycle-discounts' );
		}

		return null;
	}
}
