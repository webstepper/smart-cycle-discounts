<?php
/**
 * Profit Margin Warning Handler Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers/class-profit-margin-warning-handler.php
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// Load wizard helpers trait
require_once SCD_INCLUDES_DIR . 'admin/ajax/trait-wizard-helpers.php';

/**
 * Profit Margin Warning Handler Class
 *
 * Analyzes product cost vs discounted price to warn about selling below cost.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Profit_Margin_Warning_Handler extends SCD_Abstract_Ajax_Handler {

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
		return 'scd_profit_margin_warning';
	}

	/**
	 * Handle the profit margin warning request.
	 *
	 * @since    1.0.0
	 * @param    array $request    Request data.
	 * @return   array               Response data.
	 */
	protected function handle( $request ) {
		// Get validated wizard state using trait helper
		$wizard_state = $this->_get_validated_wizard_state( __( 'Configure discount to see margin analysis', 'smart-cycle-discounts' ) );

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
					'message'  => __( 'Select products to see margin analysis', 'smart-cycle-discounts' ),
				)
			);
		}

		// Analyze margins
		$analysis = $this->_analyze_margins( $product_ids, $discounts_data );

		return $this->success( $analysis );
	}

	/**
	 * Analyze profit margins.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $product_ids      Product IDs.
	 * @param    array $discounts_data   Discount configuration.
	 * @return   array                      Margin analysis data.
	 */
	private function _analyze_margins( $product_ids, $discounts_data ) {
		$discount_type  = $discounts_data['discount_type'];
		$discount_value = isset( $discounts_data['discount_value'] ) ? floatval( $discounts_data['discount_value'] ) : 0;

		$below_cost_products   = array();
		$total_estimated_loss  = 0;
		$products_with_cost    = 0;
		$products_without_cost = 0;

		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				continue;
			}

			// Get cost (WooCommerce stores cost in _wc_cog_cost meta or similar)
			$cost = $this->_get_product_cost( $product );

			// Get regular price
			$regular_price = floatval( $product->get_regular_price() );
			if ( $regular_price <= 0 ) {
				$regular_price = floatval( $product->get_price() );
			}

			if ( $regular_price <= 0 ) {
				continue;
			}

			// Track products with/without cost
			if ( $cost > 0 ) {
				++$products_with_cost;
			} else {
				++$products_without_cost;
				continue; // Skip products without cost data
			}

			// Calculate discounted price
			$discounted_price = $this->_apply_discount( $regular_price, $discount_type, $discount_value );

			// Check if below cost
			if ( $discounted_price < $cost ) {
				$loss_per_unit         = $cost - $discounted_price;
				$below_cost_products[] = array(
					'id'               => $product_id,
					'name'             => $product->get_name(),
					'cost'             => $cost,
					'regular_price'    => $regular_price,
					'discounted_price' => $discounted_price,
					'loss_per_unit'    => $loss_per_unit,
				);
				$total_estimated_loss += $loss_per_unit;
			}
		}

		$has_warnings = ! empty( $below_cost_products );

		return array(
			'has_data'              => true,
			'has_warnings'          => $has_warnings,
			'products_with_cost'    => $products_with_cost,
			'products_without_cost' => $products_without_cost,
			'below_cost_count'      => count( $below_cost_products ),
			'below_cost_products'   => array_slice( $below_cost_products, 0, 5 ), // Sample first 5
			'total_estimated_loss'  => $total_estimated_loss,
			'warning_message'       => $has_warnings ? $this->_get_warning_message( count( $below_cost_products ), $total_estimated_loss ) : null,
		);
	}

	/**
	 * Get product cost.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    WC_Product $product    Product object.
	 * @return   float                     Product cost.
	 */
	private function _get_product_cost( $product ) {
		// Try WooCommerce Cost of Goods plugin meta
		$cost = get_post_meta( $product->get_id(), '_wc_cog_cost', true );

		if ( ! empty( $cost ) && is_numeric( $cost ) ) {
			return floatval( $cost );
		}

		// Try common cost meta keys
		$cost_meta_keys = array(
			'_cost',
			'_product_cost',
			'cost',
			'product_cost',
		);

		foreach ( $cost_meta_keys as $meta_key ) {
			$cost = get_post_meta( $product->get_id(), $meta_key, true );
			if ( ! empty( $cost ) && is_numeric( $cost ) ) {
				return floatval( $cost );
			}
		}

		return 0;
	}

	/**
	 * Get warning message.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    int   $count    Count of products below cost.
	 * @param    float $loss     Total estimated loss.
	 * @return   string              Warning message.
	 */
	private function _get_warning_message( $count, $loss ) {
		if ( $loss > 1000 ) {
			/* translators: 1: number of products, 2: estimated loss amount */
			return sprintf(
				__( 'Critical: %1$d products will sell below cost, resulting in estimated loss of %2$s per sale!', 'smart-cycle-discounts' ),
				$count,
				wc_price( $loss )
			);
		} elseif ( $loss > 100 ) {
			/* translators: 1: number of products */
			return sprintf(
				__( 'Warning: %d products will sell below cost. Review profit margins carefully.', 'smart-cycle-discounts' ),
				$count
			);
		} else {
			/* translators: 1: number of products */
			return sprintf(
				__( 'Note: %d products will have minimal profit margin. Consider excluding low-margin items.', 'smart-cycle-discounts' ),
				$count
			);
		}
	}
}
