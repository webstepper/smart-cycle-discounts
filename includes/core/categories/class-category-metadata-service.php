<?php
/**
 * Category Metadata Service Class
 *
 * Provides enhanced category metadata with performance-optimized caching.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/categories
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress/plugins/smart-cycle-discounts/
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Category Metadata Service Class
 *
 * Calculates and caches category metadata for enhanced dropdown display.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/categories
 * @author     Webstepper <contact@webstepper.io>
 */
class WSSCD_Category_Metadata_Service {

	/**
	 * Cache duration in seconds (1 hour)
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      int    $cache_duration    Cache duration.
	 */
	private $cache_duration = 3600;

	/**
	 * Get metadata for a category
	 *
	 * @since    1.0.0
	 * @param    int $category_id    Category ID.
	 * @return   array                  Category metadata.
	 */
	public function get_category_metadata( $category_id ) {
		$category_id = absint( $category_id );

		if ( $category_id <= 0 ) {
			return $this->get_empty_metadata();
		}

		// Try to get from cache first
		$cache_key = 'wsscd_cat_meta_' . $category_id;
		$metadata  = get_transient( $cache_key );

		if ( false !== $metadata && is_array( $metadata ) ) {
			return $metadata;
		}

		// Calculate metadata
		$metadata = $this->calculate_category_metadata( $category_id );

		// Cache for 1 hour
		set_transient( $cache_key, $metadata, $this->cache_duration );

		return $metadata;
	}

	/**
	 * Calculate category metadata
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    int $category_id    Category ID.
	 * @return   array                  Category metadata.
	 */
	private function calculate_category_metadata( $category_id ) {
		// Get products in this category
		$args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Required for category product count; results are cached.
			'tax_query'      => array(
				array(
					'taxonomy' => 'product_cat',
					'field'    => 'term_id',
					'terms'    => $category_id,
				),
			),
		);

		$query       = new WP_Query( $args );
		$product_ids = $query->posts;

		wp_reset_postdata();

		$total_products = count( $product_ids );

		if ( 0 === $total_products ) {
			return $this->get_empty_metadata();
		}

		// Calculate stock statistics
		$in_stock_count = 0;
		$on_sale_count  = 0;
		$prices         = array();

		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );

			if ( ! $product ) {
				continue;
			}

			// Stock status
			if ( $product->is_in_stock() ) {
				++$in_stock_count;
			}

			// Sale status
			if ( $product->is_on_sale() ) {
				++$on_sale_count;
			}

			// Price range
			$price = $product->get_price();
			if ( '' !== $price ) {
				$prices[] = floatval( $price );
			}
		}

		// Calculate stock percentage
		$stock_percent = $total_products > 0
			? round( ( $in_stock_count / $total_products ) * 100 )
			: 0;

		// Calculate price range
		$price_min = ! empty( $prices ) ? min( $prices ) : 0;
		$price_max = ! empty( $prices ) ? max( $prices ) : 0;

		return array(
			'total_products' => $total_products,
			'in_stock_count' => $in_stock_count,
			'stock_percent'  => $stock_percent,
			'on_sale_count'  => $on_sale_count,
			'price_min'      => $price_min,
			'price_max'      => $price_max,
		);
	}

	/**
	 * Get empty metadata structure
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   array    Empty metadata.
	 */
	private function get_empty_metadata() {
		return array(
			'total_products' => 0,
			'in_stock_count' => 0,
			'stock_percent'  => 0,
			'on_sale_count'  => 0,
			'price_min'      => 0,
			'price_max'      => 0,
		);
	}

	/**
	 * Invalidate cache for a category
	 *
	 * @since    1.0.0
	 * @param    int $category_id    Category ID.
	 * @return   bool                   True on success.
	 */
	public function invalidate_cache( $category_id ) {
		$category_id = absint( $category_id );
		$cache_key   = 'wsscd_cat_meta_' . $category_id;

		return delete_transient( $cache_key );
	}

	/**
	 * Invalidate cache for all categories containing a product
	 *
	 * @since    1.0.0
	 * @param    int $product_id    Product ID.
	 * @return   void
	 */
	public function invalidate_product_categories( $product_id ) {
		$product_id = absint( $product_id );

		// Get all categories for this product
		$terms = wp_get_post_terms(
			$product_id,
			'product_cat',
			array( 'fields' => 'ids' )
		);

		if ( is_array( $terms ) ) {
			foreach ( $terms as $term_id ) {
				$this->invalidate_cache( $term_id );
			}
		}
	}

	/**
	 * Get stock status class
	 *
	 * @since    1.0.0
	 * @param    int $stock_percent    Stock percentage.
	 * @return   string                   Status class.
	 */
	public function get_stock_status( $stock_percent ) {
		$stock_percent = absint( $stock_percent );

		if ( 0 === $stock_percent ) {
			return 'empty';
		} elseif ( $stock_percent < 25 ) {
			return 'critical';
		} elseif ( $stock_percent < 75 ) {
			return 'warning';
		} else {
			return 'healthy';
		}
	}
}
