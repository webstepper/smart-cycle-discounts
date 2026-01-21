<?php
/**
 * Sale Items Filter Handler Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers/class-sale-items-filter-handler.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

require_once WSSCD_INCLUDES_DIR . 'admin/ajax/trait-wizard-helpers.php';

/**
 * Sale Items Filter Handler Class
 *
 * Analyzes how many products are on sale and calculates coverage impact.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers
 * @author     Webstepper <contact@webstepper.io>
 */
class WSSCD_Sale_Items_Filter_Handler extends WSSCD_Abstract_Ajax_Handler {

	use WSSCD_Wizard_Helpers;

	/**
	 * Constructor.
	 *
	 * @since    1.0.0
	 * @param    WSSCD_Logger $logger    Logger instance (optional).
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
		return 'wsscd_sale_items_filter';
	}

	/**
	 * Handle the sale items filter request.
	 *
	 * @since    1.0.0
	 * @param    array $request    Request data.
	 * @return   array               Response data.
	 */
	protected function handle( $request ) {
		$state_service = $this->_get_state_service();
		if ( ! $state_service ) {
			return $this->error(
				__( 'Could not load wizard data', 'smart-cycle-discounts' ),
				'state_service_unavailable',
				500
			);
		}

		$products_data  = $state_service->get_step_data( 'products' );
		$discounts_data = $state_service->get_step_data( 'discounts' );

		if ( empty( $products_data ) ) {
			return $this->success(
				array(
					'has_data' => false,
					'message'  => __( 'Select products to see sale items impact', 'smart-cycle-discounts' ),
				)
			);
		}

		$product_ids = $this->_get_product_ids( $products_data );

		if ( empty( $product_ids ) ) {
			return $this->success(
				array(
					'has_data' => false,
					'message'  => __( 'No products selected', 'smart-cycle-discounts' ),
				)
			);
		}

		$apply_to_sale_items = isset( $discounts_data['apply_to_sale_items'] ) ? (bool) $discounts_data['apply_to_sale_items'] : false;

		// Count sale items
		$sale_items = $this->_count_sale_items( $product_ids );

		$total_products     = count( $product_ids );
		$sale_count         = count( $sale_items );
		$effective_coverage = $apply_to_sale_items ? $total_products : ( $total_products - $sale_count );

		return $this->success(
			array(
				'has_data'            => true,
				'apply_to_sale_items' => $apply_to_sale_items,
				'total_products'      => $total_products,
				'sale_items_count'    => $sale_count,
				'excluded_count'      => $apply_to_sale_items ? 0 : $sale_count,
				'effective_coverage'  => $effective_coverage,
				'coverage_percentage' => $total_products > 0 ? round( ( $effective_coverage / $total_products ) * 100 ) : 0,
				'has_sale_items'      => $sale_count > 0,
			)
		);
	}

	/**
	 * Count products that are on sale.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $product_ids    Product IDs to check.
	 * @return   array                    Array of product IDs on sale.
	 */
	private function _count_sale_items( $product_ids ) {
		$sale_items = array();

		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( $product && $product->is_on_sale() ) {
				$sale_items[] = $product_id;
			}
		}

		return $sale_items;
	}
}
