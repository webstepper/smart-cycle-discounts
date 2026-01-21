<?php
/**
 * Get Product Stats Handler Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers/class-get-product-stats-handler.php
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
 * Get Product Stats Handler Class
 *
 * Orchestrates product statistics requests by delegating to the Product Service.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers
 * @author     Webstepper <contact@webstepper.io>
 */
class WSSCD_Get_Product_Stats_Handler extends WSSCD_Abstract_Ajax_Handler {

	/**
	 * Product service instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Product_Service|null    $product_service    Product service.
	 */
	private $product_service = null;

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
		return 'wsscd_get_product_stats';
	}

	/**
	 * Handle the get product stats request.
	 *
	 * @since    1.0.0
	 * @param    array $request    Request data.
	 * @return   array               Response data.
	 */
	protected function handle( $request ) {
		// NOTE: Product stats is FREE - helps users during campaign setup (exploration feature)
		// License protection happens at campaign SAVE level (in save-step-handler)

		$product_ids = isset( $request['product_ids'] ) ? $request['product_ids'] : array();

		// Security: Validate that product_ids is an array or can be converted to one
		if ( ! is_array( $product_ids ) ) {
			// If it's a string, try to decode as JSON
			if ( is_string( $product_ids ) ) {
				$decoded = json_decode( $product_ids, true );
				if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
					$product_ids = $decoded;
				} else {
					// If not JSON, treat as single ID
					$product_ids = array( $product_ids );
				}
			} else {
				// For other types, convert to array
				$product_ids = array( $product_ids );
			}
		}

		// If no product IDs provided, check if we need stats for a campaign
		if ( empty( $product_ids ) ) {
			$campaign_id = isset( $request['campaign_id'] ) ? intval( $request['campaign_id'] ) : 0;
			if ( $campaign_id > 0 ) {
				$product_ids = $this->get_campaign_product_ids( $campaign_id );
			}
		}

		$product_ids = array_map( 'intval', $product_ids );
		$product_ids = array_filter( $product_ids, array( $this, 'filter_valid_product_ids' ) );

		// Security: Limit number of products to prevent DoS
		if ( count( $product_ids ) > 1000 ) {
			$product_ids = array_slice( $product_ids, 0, 1000 );
		}

		// If still no product IDs, return empty stats
		if ( empty( $product_ids ) ) {
			return $this->success(
				array(
					'stats'     => array(
						'total_products' => 0,
						'categories'     => array(),
						'price_range'    => array(
							'min' => 0,
							'max' => 0,
						),
						'stock_status'   => array(
							'in_stock'     => 0,
							'out_of_stock' => 0,
						),
						'types'          => array(),
						'average_price'  => 0,
						'total_value'    => 0,
					),
					'timestamp' => current_time( 'timestamp' ),
				)
			);
		}

		// Delegate to service
		$service = $this->get_product_service();
		$stats   = $service->get_product_stats( $product_ids );

		return $this->success(
			array(
				'stats'       => $stats,
				'product_ids' => $product_ids,
				'timestamp'   => current_time( 'timestamp' ),
			)
		);
	}

	/**
	 * Filter valid product IDs.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    int $id    Product ID to validate.
	 * @return   bool          True if valid product ID.
	 */
	private function filter_valid_product_ids( $id ) {
		// Validate ID is positive integer and within safe range
		return $id > 0 && $id <= PHP_INT_MAX;
	}

	/**
	 * Get product IDs for a campaign.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    int $campaign_id    Campaign ID.
	 * @return   array                  Product IDs.
	 */
	private function get_campaign_product_ids( $campaign_id ) {
		// Try to use campaign repository to load campaign
		if ( class_exists( 'WSSCD_Campaign_Repository' ) && class_exists( 'WSSCD_Database_Manager' ) && class_exists( 'WSSCD_Cache_Manager' ) ) {
			$db_manager    = new WSSCD_Database_Manager();
			$cache_manager = new WSSCD_Cache_Manager();
			$repository    = new WSSCD_Campaign_Repository( $db_manager, $cache_manager );

			$campaign = $repository->get_by_id( $campaign_id );
			if ( $campaign ) {
				// For specific products, return the product IDs
				if ( 'specific_products' === $campaign->get_product_selection_type() ) {
					return $campaign->get_product_ids();
				}
				// For other types, we can't get all product IDs efficiently
				return array();
			}
		}

		// Fallback if campaign model not available
		return array();
	}

	/**
	 * Get product service instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   WSSCD_Product_Service    Product service.
	 */
	private function get_product_service() {
		if ( null === $this->product_service ) {
			require_once WSSCD_PLUGIN_DIR . 'includes/core/products/class-product-service.php';
			$this->product_service = new WSSCD_Product_Service();
		}

		return $this->product_service;
	}
}
