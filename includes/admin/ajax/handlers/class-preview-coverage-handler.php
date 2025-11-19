<?php
/**
 * Preview Coverage Handler Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers/class-preview-coverage-handler.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

require_once SCD_INCLUDES_DIR . 'admin/ajax/trait-wizard-helpers.php';

/**
 * Preview Coverage Handler Class
 *
 * Calculates effective product coverage considering all exclusions and conflicts.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers
 * @author     Webstepper <contact@webstepper.io>
 */
class SCD_Preview_Coverage_Handler extends SCD_Abstract_Ajax_Handler {
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
		return 'scd_preview_coverage';
	}

	/**
	 * Handle the preview coverage request.
	 *
	 * @since    1.0.0
	 * @param    array $request    Request data.
	 * @return   array               Response data.
	 */
	protected function handle( $request ) {
		// NOTE: Coverage preview is FREE - shows what WOULD be covered (exploration feature)
		// License protection happens at campaign SAVE level (in save-step-handler)

		$state_service = $this->_get_state_service();
		if ( ! $state_service ) {
			return $this->error(
				__( 'Could not load wizard data', 'smart-cycle-discounts' ),
				'state_service_unavailable',
				500
			);
		}

		$basic_data     = $state_service->get_step_data( 'basic' );
		$products_data  = $state_service->get_step_data( 'products' );
		$discounts_data = $state_service->get_step_data( 'discounts' );

		if ( empty( $products_data ) ) {
			return $this->success(
				array(
					'products_matched'    => 0,
					'products_discounted' => 0,
					'coverage_percentage' => 0,
					'exclusions'          => array(),
				)
			);
		}

		// Get cache manager for coverage caching
		$cache_manager = null;
		if ( class_exists( 'Smart_Cycle_Discounts' ) ) {
			$plugin    = Smart_Cycle_Discounts::get_instance();
			$container = $plugin->get_container();
			if ( $container && $container->has( 'cache_manager' ) ) {
				$cache_manager = $container->get( 'cache_manager' );
			}
		}

		// Generate cache key based on input parameters to avoid expensive recalculation
		$cache_key_params = array(
			'selection_type' => isset( $products_data['product_selection_type'] ) ? $products_data['product_selection_type'] : 'all_products',
			'product_ids'    => isset( $products_data['product_ids'] ) ? $products_data['product_ids'] : array(),
			'category_ids'   => isset( $products_data['category_ids'] ) ? $products_data['category_ids'] : array(),
			'priority'       => isset( $basic_data['priority'] ) ? $basic_data['priority'] : 3,
			'apply_sale'     => isset( $discounts_data['apply_to_sale_items'] ) ? $discounts_data['apply_to_sale_items'] : false,
			'random_count'   => isset( $products_data['random_count'] ) ? $products_data['random_count'] : 0,
		);
		$cache_key = 'campaigns_coverage_' . md5( wp_json_encode( $cache_key_params ) );

		// Try to get cached coverage first
		$cached_coverage = null;
		if ( $cache_manager ) {
			$cached_coverage = $cache_manager->get( $cache_key );
		}

		if ( $cached_coverage && is_array( $cached_coverage ) ) {
			// Use cached data - no expensive WooCommerce queries needed
			return $this->success( $cached_coverage );
		}

		// Cache miss - calculate coverage (expensive)
		$coverage = $this->_calculate_coverage( $basic_data, $products_data, $discounts_data );

		// Cache for 5 minutes (wizard data can change, but caching reduces repeated calculations)
		if ( $cache_manager ) {
			$cache_manager->set( $cache_key, $coverage, 300 );
		}

		return $this->success( $coverage );
	}

	/**
	 * Calculate campaign coverage.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $basic_data       Basic campaign data.
	 * @param    array $products_data    Products selection data.
	 * @param    array $discounts_data   Discount configuration data.
	 * @return   array                      Coverage data.
	 */
	private function _calculate_coverage( $basic_data, $products_data, $discounts_data ) {
		$selection_type = isset( $products_data['product_selection_type'] ) ? $products_data['product_selection_type'] : 'all_products';
		$product_ids    = isset( $products_data['product_ids'] ) ? $products_data['product_ids'] : array();
		$category_ids   = isset( $products_data['category_ids'] ) ? $products_data['category_ids'] : array();

		$matched_products = $this->_get_matched_products( $selection_type, $product_ids, $category_ids );
		$total_matched    = count( $matched_products );

		// Track exclusions
		$exclusions        = array();
		$excluded_products = array();

		$apply_to_sale_items = isset( $discounts_data['apply_to_sale_items'] ) ? (bool) $discounts_data['apply_to_sale_items'] : false;

		if ( ! $apply_to_sale_items ) {
			$sale_products = $this->_filter_sale_products( $matched_products );
			if ( ! empty( $sale_products ) ) {
				$excluded_products = array_merge( $excluded_products, $sale_products );
				$exclusions[]      = array(
					'reason' => 'on_sale',
					'label'  => __( 'Products on sale', 'smart-cycle-discounts' ),
					'count'  => count( $sale_products ),
				);
			}
		}

		$priority            = isset( $basic_data['priority'] ) ? intval( $basic_data['priority'] ) : 3;
		$conflicted_products = $this->_get_conflicted_products( $priority, $selection_type, $product_ids, $category_ids );

		if ( ! empty( $conflicted_products ) ) {
			$excluded_products = array_merge( $excluded_products, $conflicted_products );
			$exclusions[]      = array(
				'reason' => 'conflicts',
				'label'  => __( 'Campaign conflicts', 'smart-cycle-discounts' ),
				'count'  => count( $conflicted_products ),
			);
		}

		$excluded_products = array_unique( $excluded_products );

		$products_discounted = $total_matched - count( $excluded_products );

		// For random products, the "actually discounted" is limited by random_count
		if ( 'random_products' === $selection_type && isset( $products_data['random_count'] ) ) {
			$random_count = intval( $products_data['random_count'] );
			// The actual discounted will be the minimum of random_count and available products
			$products_discounted = min( $random_count, $products_discounted );
		}

		$coverage_percentage = 0 < $total_matched ? round( ( $products_discounted / $total_matched ) * 100 ) : 0;

		// Use optimized count method instead of loading all product IDs
		$total_store_products = $this->_get_total_product_count();

		return array(
			'products_matched'     => $total_matched,
			'products_discounted'  => $products_discounted,
			'products_excluded'    => count( $excluded_products ),
			'coverage_percentage'  => $coverage_percentage,
			'total_store_products' => $total_store_products,
			'exclusions'           => $exclusions,
			'product_ids'          => $matched_products,
			'selection_type'       => $selection_type,
			'category_ids'         => $category_ids,
		);
	}

	/**
	 * Get matched products for selection.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $selection_type   Selection type.
	 * @param    array  $product_ids      Product IDs.
	 * @param    array  $category_ids     Category IDs.
	 * @return   array                       Array of product IDs.
	 */
	private function _get_matched_products( $selection_type, $product_ids, $category_ids ) {
		if ( 'all_products' === $selection_type ) {
			// If categories are specified, get all products from those categories
			// Otherwise get all products in store
			if ( ! empty( $category_ids ) && is_array( $category_ids ) && ! in_array( 'all', $category_ids, true ) ) {
				return $this->_get_products_in_categories( $category_ids );
			}
			return $this->_get_all_product_ids();
		} elseif ( 'specific_products' === $selection_type ) {
			return is_array( $product_ids ) ? array_map( 'intval', $product_ids ) : array();
		} elseif ( 'random_products' === $selection_type ) {
			// For random products, get all products from selected categories
			if ( ! empty( $category_ids ) && is_array( $category_ids ) ) {
				return $this->_get_products_in_categories( $category_ids );
			}
			return $this->_get_all_product_ids();
		} elseif ( 'smart_selection' === $selection_type ) {
			// For smart selection, use categories as base if specified
			if ( ! empty( $category_ids ) && is_array( $category_ids ) ) {
				return $this->_get_products_in_categories( $category_ids );
			}
			return $this->_get_all_product_ids();
		}

		return array();
	}

	/**
	 * Get products in categories.
	 *
	 * NOTE: This method overrides the trait version to exclude child categories
	 * and filter out 'all' values, which is specific to coverage calculation logic.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $category_ids    Category IDs.
	 * @return   array                     Array of product IDs.
	 */
	private function _get_products_in_categories( $category_ids ) {
		if ( empty( $category_ids ) || ! is_array( $category_ids ) ) {
			return array();
		}

		$category_ids = array_filter( $category_ids, array( $this, 'filter_valid_category_id' ) );

		if ( empty( $category_ids ) ) {
			return array();
		}

		$args = array(
			'post_type'      => 'product',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'fields'         => 'ids',
			'tax_query'      => array(
				array(
					'taxonomy'         => 'product_cat',
					'field'            => 'term_id',
					'terms'            => array_map( 'intval', $category_ids ),
					'operator'         => 'IN',
					'include_children' => false, // Don't include subcategories
				),
			),
		);

		return get_posts( $args );
	}

	/**
	 * Filter products on sale.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $product_ids    Product IDs to check.
	 * @return   array                    Product IDs on sale.
	 */
	private function _filter_sale_products( $product_ids ) {
		$sale_products = array();

		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( $product && $product->is_on_sale() ) {
				$sale_products[] = $product_id;
			}
		}

		return $sale_products;
	}

	/**
	 * Get products that would be conflicted by higher-priority campaigns.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    int    $priority         Campaign priority.
	 * @param    string $selection_type   Selection type.
	 * @param    array  $product_ids      Product IDs.
	 * @param    array  $category_ids     Category IDs.
	 * @return   array                       Array of conflicted product IDs.
	 */
	private function _get_conflicted_products( $priority, $selection_type, $product_ids, $category_ids ) {
		$active_campaigns = $this->_get_active_campaigns();

		if ( empty( $active_campaigns ) ) {
			return array();
		}

		$conflicted = array();

		$new_campaign_products = $this->_get_matched_products( $selection_type, $product_ids, $category_ids );

		foreach ( $active_campaigns as $campaign ) {
			$campaign_priority = $campaign->get_priority();

			// Check campaigns with higher OR equal priority (both block new campaign's products)
			// Higher value = higher priority (5 beats 3)
			// Equal priority = older campaign wins (but we're creating new, so it will be blocked)
			if ( $priority > $campaign_priority ) {
				continue;
			}

			$existing_products = $this->_get_campaign_products( $campaign );

			// Find intersection
			$overlap = array_intersect( $existing_products, $new_campaign_products );

			if ( ! empty( $overlap ) ) {
				$conflicted = array_merge( $conflicted, $overlap );
			}
		}

		return array_unique( $conflicted );
	}

	/**
	 * Filter valid category IDs (exclude 'all' and empty values).
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    mixed $id    Category ID to check.
	 * @return   bool            True if valid category ID.
	 */
	private function filter_valid_category_id( $id ) {
		return 'all' !== $id && ! empty( $id );
	}
}
