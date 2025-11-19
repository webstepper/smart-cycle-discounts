<?php
/**
 * Product Selector Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/products/class-product-selector.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Product Selector
 *
 * Handles product selection logic for campaigns.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/products
 * @author     Webstepper <contact@webstepper.io>
 */
class SCD_Product_Selector {

	/**
	 * Database manager instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Database_Manager    $db    Database manager.
	 */
	private SCD_Database_Manager $db;

	/**
	 * Logger instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Logger    $logger    Logger instance.
	 */
	private SCD_Logger $logger;

	/**
	 * Cache manager instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Cache_Manager|null    $cache    Cache manager.
	 */
	private ?SCD_Cache_Manager $cache = null;

	/**
	 * Condition engine instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Condition_Engine|null    $condition_engine    Condition engine.
	 */
	private ?SCD_Condition_Engine $condition_engine = null;

	/**
	 * Initialize the product selector.
	 *
	 * @since    1.0.0
	 * @param    SCD_Database_Manager $db                 Database manager.
	 * @param    SCD_Logger           $logger             Logger instance.
	 * @param    SCD_Cache_Manager    $cache              Cache manager.
	 * @param    SCD_Condition_Engine $condition_engine   Condition engine.
	 */
	public function __construct(
		SCD_Database_Manager $db,
		SCD_Logger $logger,
		?SCD_Cache_Manager $cache = null,
		?SCD_Condition_Engine $condition_engine = null
	) {
		$this->db               = $db;
		$this->logger           = $logger;
		$this->cache            = $cache;
		$this->condition_engine = $condition_engine;

		// Invalidate cache when products are deleted or updated
		add_action( 'before_delete_post', array( $this, 'handle_product_deletion' ), 10, 1 );
		add_action( 'woocommerce_delete_product', array( $this, 'handle_product_deletion' ), 10, 1 );
		add_action( 'save_post_product', array( $this, 'handle_product_update' ), 10, 1 );
		add_action( 'woocommerce_update_product', array( $this, 'handle_product_update' ), 10, 1 );

		// Invalidate cache when categories/tags are modified
		add_action( 'created_product_cat', array( $this, 'handle_taxonomy_change' ), 10, 1 );
		add_action( 'edited_product_cat', array( $this, 'handle_taxonomy_change' ), 10, 1 );
		add_action( 'delete_product_cat', array( $this, 'handle_taxonomy_change' ), 10, 1 );
		add_action( 'created_product_tag', array( $this, 'handle_taxonomy_change' ), 10, 1 );
		add_action( 'edited_product_tag', array( $this, 'handle_taxonomy_change' ), 10, 1 );
		add_action( 'delete_product_tag', array( $this, 'handle_taxonomy_change' ), 10, 1 );
	}

	/**
	 * Select products based on criteria.
	 *
	 * @since    1.0.0
	 * @param    array $criteria    Selection criteria.
	 * @return   array                 Selected product IDs.
	 */
	public function select_products( array $criteria ): array {
		// Generate cache key that includes ALL criteria that affect results
		// Must include: conditions, categories, tags, price range, stock, featured, include/exclude IDs, operators, etc.
		$cache_parts = array(
			'type'              => $criteria['product_selection_type'] ?? 'all',
			'categories'        => $criteria['categories'] ?? array(),
			'category_operator' => $criteria['category_operator'] ?? 'IN',
			'tags'              => $criteria['tags'] ?? array(),
			'tag_operator'      => $criteria['tag_operator'] ?? 'IN',
			'stock_status'      => $criteria['stock_status'] ?? '',
			'featured'          => $criteria['featured'] ?? null,
			'price_min'         => $criteria['price_min'] ?? null,
			'price_max'         => $criteria['price_max'] ?? null,
			'exclude_ids'       => $criteria['exclude_ids'] ?? array(),
			'include_ids'       => $criteria['include_ids'] ?? array(),
			'conditions'        => $criteria['conditions'] ?? array(),
			'conditions_logic'  => $criteria['conditions_logic'] ?? 'all',
			'limit'             => $criteria['limit'] ?? 0,
			'orderby'           => $criteria['orderby'] ?? '',
			'order'             => $criteria['order'] ?? 'ASC',
		);

		// Generate unique hash for this exact combination of criteria
		$cache_key = 'products_selection_' . md5( serialize( $cache_parts ) );

		// Try to get from cache first
		if ( $this->cache ) {
			$cached_result = $this->cache->get( $cache_key );
			if ( false !== $cached_result && is_array( $cached_result ) ) {
				$this->logger->debug( 'Product selection retrieved from cache', array( 'criteria' => $criteria ) );
				return $cached_result;
			}
		}

		$product_ids = array();

		try {
			$query_args = array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => 5000,
				'fields'         => 'ids',
				'meta_query'     => array(),
				'tax_query'      => array(),
			);

			// Apply criteria filters
			$query_args = $this->apply_criteria_to_query( $query_args, $criteria );

			// Apply conditions to query if condition engine is available
			if ( ! empty( $criteria['conditions'] ) && $this->condition_engine ) {
				$conditions_logic = $criteria['conditions_logic'] ?? 'all';
				$meta_query       = $this->condition_engine->build_meta_query( $criteria['conditions'], $conditions_logic );
				if ( ! empty( $meta_query ) && count( $meta_query ) > 1 ) {
					$query_args['meta_query'] = array_merge( $query_args['meta_query'], $meta_query );
				}
			}

			// Execute query
			$query       = new WP_Query( $query_args );
			$product_ids = $query->posts;

			// Apply additional filters that can't be done in WP_Query
			$product_ids = $this->apply_post_query_filters( $product_ids, $criteria );

			// Apply post-query conditions if condition engine is available
			if ( ! empty( $criteria['conditions'] ) && $this->condition_engine ) {
				$conditions_logic = $criteria['conditions_logic'] ?? 'all';
				$product_ids      = $this->condition_engine->apply_conditions( $product_ids, $criteria['conditions'], $conditions_logic );
			}

			// Cache for 15 minutes - cleared when campaigns change
			if ( $this->cache ) {
				$this->cache->set( $cache_key, $product_ids, 900 );
			}

			$this->logger->info(
				'Products selected successfully',
				array(
					'criteria' => $criteria,
					'count'    => count( $product_ids ),
				)
			);

		} catch ( Exception $e ) {
			$this->logger->error(
				'Product selection failed',
				array(
					'criteria' => $criteria,
					'error'    => $e->getMessage(),
				)
			);
		}

		return $product_ids;
	}

	/**
	 * Get eligible products for campaigns.
	 *
	 * @since    1.0.0
	 * @param    array $exclude_ids    Product IDs to exclude.
	 * @return   array                    Eligible product data.
	 */
	public function get_eligible_products( array $exclude_ids = array() ): array {
		$cache_key = 'scd_eligible_products_' . md5( serialize( $exclude_ids ) );

		if ( $this->cache ) {
			$cached_result = $this->cache->get( $cache_key );
			if ( $cached_result !== false && is_array( $cached_result ) ) {
				return $cached_result;
			}
		}

		$products = array();

		try {
			$query_args = array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => 1000, // Reasonable limit for stock products
				'meta_query'     => array(
					array(
						'key'     => '_stock_status',
						'value'   => 'instock',
						'compare' => '=',
					),
					array(
						'key'     => '_visibility',
						'value'   => array( 'hidden', 'search' ),
						'compare' => 'NOT IN',
					),
				),
			);

			if ( ! empty( $exclude_ids ) ) {
				$query_args['post__not_in'] = $exclude_ids;
			}

			$query = new WP_Query( $query_args );

			foreach ( $query->posts as $product_id ) {
				$product = wc_get_product( $product_id );
				if ( ! $product ) {
					continue;
				}

				$products[] = array(
					'id'             => $product_id,
					'name'           => $product->get_name(),
					'sku'            => $product->get_sku(),
					'price'          => $product->get_price(),
					'regular_price'  => $product->get_regular_price(),
					'sale_price'     => $product->get_sale_price(),
					'stock_quantity' => $product->get_stock_quantity(),
					'categories'     => wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) ),
					'tags'           => wp_get_post_terms( $product_id, 'product_tag', array( 'fields' => 'ids' ) ),
					'type'           => $product->get_type(),
					'featured'       => $product->is_featured(),
					'on_sale'        => $product->is_on_sale(),
				);
			}

			if ( $this->cache ) {
				$this->cache->set( $cache_key, $products, 1800 ); // Cache for 30 minutes
			}

			$this->logger->debug( 'Eligible products retrieved', array( 'count' => count( $products ) ) );

		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to get eligible products',
				array(
					'error'       => $e->getMessage(),
					'exclude_ids' => $exclude_ids,
				)
			);
		}

		return $products;
	}

	/**
	 * Filter products by criteria.
	 *
	 * @since    1.0.0
	 * @param    array $product_ids    Product IDs to filter.
	 * @param    array $criteria       Filter criteria.
	 * @return   array                    Filtered product IDs.
	 */
	public function filter_by_criteria( array $product_ids, array $criteria ): array {
		if ( empty( $product_ids ) || empty( $criteria ) ) {
			return $product_ids;
		}

		$filtered_ids = $product_ids;

		if ( isset( $criteria['price_min'] ) || isset( $criteria['price_max'] ) ) {
			$filtered_ids = $this->filter_by_price_range( $filtered_ids, $criteria );
		}

		if ( ! empty( $criteria['categories'] ) ) {
			$filtered_ids = $this->filter_by_categories( $filtered_ids, $criteria['categories'] );
		}

		if ( ! empty( $criteria['tags'] ) ) {
			$filtered_ids = $this->filter_by_tags( $filtered_ids, $criteria['tags'] );
		}

		if ( ! empty( $criteria['stock_status'] ) ) {
			$filtered_ids = $this->filter_by_stock_status( $filtered_ids, $criteria['stock_status'] );
		}

		if ( ! empty( $criteria['product_type'] ) ) {
			$filtered_ids = $this->filter_by_product_type( $filtered_ids, $criteria['product_type'] );
		}

		if ( isset( $criteria['featured'] ) ) {
			$filtered_ids = $this->filter_by_featured_status( $filtered_ids, $criteria['featured'] );
		}

		if ( isset( $criteria['on_sale'] ) ) {
			$filtered_ids = $this->filter_by_sale_status( $filtered_ids, $criteria['on_sale'] );
		}

		$this->logger->debug(
			'Products filtered by criteria',
			array(
				'original_count' => count( $product_ids ),
				'filtered_count' => count( $filtered_ids ),
				'criteria'       => $criteria,
			)
		);

		return $filtered_ids;
	}

	/**
	 * Apply criteria to WP_Query arguments.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $query_args    Query arguments.
	 * @param    array $criteria      Selection criteria.
	 * @return   array                   Modified query arguments.
	 */
	private function apply_criteria_to_query( array $query_args, array $criteria ): array {
		// Category filter
		if ( ! empty( $criteria['categories'] ) ) {
			$query_args['tax_query'][] = array(
				'taxonomy' => 'product_cat',
				'field'    => 'term_id',
				'terms'    => $criteria['categories'],
				'operator' => isset( $criteria['category_operator'] ) ? $criteria['category_operator'] : 'IN',
			);
		}

		// Tag filter
		if ( ! empty( $criteria['tags'] ) ) {
			$query_args['tax_query'][] = array(
				'taxonomy' => 'product_tag',
				'field'    => 'term_id',
				'terms'    => $criteria['tags'],
				'operator' => isset( $criteria['tag_operator'] ) ? $criteria['tag_operator'] : 'IN',
			);
		}

		// Stock status filter
		if ( ! empty( $criteria['stock_status'] ) ) {
			$query_args['meta_query'][] = array(
				'key'     => '_stock_status',
				'value'   => $criteria['stock_status'],
				'compare' => '=',
			);
		}

		// Featured products filter
		if ( isset( $criteria['featured'] ) ) {
			$query_args['meta_query'][] = array(
				'key'     => '_featured',
				'value'   => $criteria['featured'] ? 'yes' : 'no',
				'compare' => '=',
			);
		}

		// Price range filter
		if ( isset( $criteria['price_min'] ) || isset( $criteria['price_max'] ) ) {
			$price_meta_query = array( 'relation' => 'AND' );

			if ( isset( $criteria['price_min'] ) ) {
				$price_meta_query[] = array(
					'key'     => '_price',
					'value'   => floatval( $criteria['price_min'] ),
					'type'    => 'NUMERIC',
					'compare' => '>=',
				);
			}

			if ( isset( $criteria['price_max'] ) ) {
				$price_meta_query[] = array(
					'key'     => '_price',
					'value'   => floatval( $criteria['price_max'] ),
					'type'    => 'NUMERIC',
					'compare' => '<=',
				);
			}

			$query_args['meta_query'][] = $price_meta_query;
		}

		// Exclude specific products
		if ( ! empty( $criteria['exclude_ids'] ) ) {
			$query_args['post__not_in'] = $criteria['exclude_ids'];
		}

		// Include specific products
		if ( ! empty( $criteria['include_ids'] ) ) {
			$query_args['post__in'] = $criteria['include_ids'];
		}

		// Limit results
		if ( isset( $criteria['limit'] ) && $criteria['limit'] > 0 ) {
			$query_args['posts_per_page'] = intval( $criteria['limit'] );
		}

		// Ordering
		if ( ! empty( $criteria['orderby'] ) ) {
			$query_args['orderby'] = $criteria['orderby'];
			$query_args['order']   = $criteria['order'] ?? 'ASC';
		}

		return $query_args;
	}

	/**
	 * Apply filters that can't be done in WP_Query.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $product_ids    Product IDs.
	 * @param    array $criteria       Filter criteria.
	 * @return   array                    Filtered product IDs.
	 */
	private function apply_post_query_filters( array $product_ids, array $criteria ): array {
		$filtered_ids = $product_ids;

		// Handle random selection if specified
		if ( ! empty( $criteria['selection_type'] ) && $criteria['selection_type'] === 'random' && ! empty( $criteria['random_count'] ) ) {
			$filtered_ids = $this->get_random_products( $filtered_ids, intval( $criteria['random_count'] ) );
		}

		if ( ! empty( $criteria['product_type'] ) ) {
			$filtered_ids = array_filter(
				$filtered_ids,
				function ( $product_id ) use ( $criteria ) {
					$product = wc_get_product( $product_id );
					return $product && $product->get_type() === $criteria['product_type'];
				}
			);
		}

		if ( isset( $criteria['on_sale'] ) ) {
			$filtered_ids = array_filter(
				$filtered_ids,
				function ( $product_id ) use ( $criteria ) {
					$product = wc_get_product( $product_id );
					return $product && $product->is_on_sale() === $criteria['on_sale'];
				}
			);
		}

		if ( ! empty( $criteria['attributes'] ) ) {
			$filtered_ids = $this->filter_by_attributes( $filtered_ids, $criteria['attributes'] );
		}

		return array_values( $filtered_ids );
	}

	/**
	 * Filter products by price range.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $product_ids    Product IDs.
	 * @param    array $criteria       Price criteria.
	 * @return   array                    Filtered product IDs.
	 */
	private function filter_by_price_range( array $product_ids, array $criteria ): array {
		$min_price = isset( $criteria['price_min'] ) ? floatval( $criteria['price_min'] ) : 0;
		$max_price = isset( $criteria['price_max'] ) ? floatval( $criteria['price_max'] ) : PHP_FLOAT_MAX;

		return array_filter(
			$product_ids,
			function ( $product_id ) use ( $min_price, $max_price ) {
				$product = wc_get_product( $product_id );
				if ( ! $product ) {
					return false;
				}

				$price = floatval( $product->get_price() );
				return $price >= $min_price && $price <= $max_price;
			}
		);
	}

	/**
	 * Filter products by categories.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $product_ids    Product IDs.
	 * @param    array $category_ids   Category IDs.
	 * @return   array                    Filtered product IDs.
	 */
	private function filter_by_categories( array $product_ids, array $category_ids ): array {
		return array_filter(
			$product_ids,
			function ( $product_id ) use ( $category_ids ) {
				$product_categories = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );
				return ! empty( array_intersect( $product_categories, $category_ids ) );
			}
		);
	}

	/**
	 * Filter products by tags.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $product_ids    Product IDs.
	 * @param    array $tag_ids        Tag IDs.
	 * @return   array                    Filtered product IDs.
	 */
	private function filter_by_tags( array $product_ids, array $tag_ids ): array {
		return array_filter(
			$product_ids,
			function ( $product_id ) use ( $tag_ids ) {
				$product_tags = wp_get_post_terms( $product_id, 'product_tag', array( 'fields' => 'ids' ) );
				return ! empty( array_intersect( $product_tags, $tag_ids ) );
			}
		);
	}

	/**
	 * Filter products by stock status.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array  $product_ids     Product IDs.
	 * @param    string $stock_status    Stock status.
	 * @return   array                      Filtered product IDs.
	 */
	private function filter_by_stock_status( array $product_ids, string $stock_status ): array {
		return array_filter(
			$product_ids,
			function ( $product_id ) use ( $stock_status ) {
				$product = wc_get_product( $product_id );
				return $product && $product->get_stock_status() === $stock_status;
			}
		);
	}

	/**
	 * Filter products by product type.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array  $product_ids     Product IDs.
	 * @param    string $product_type    Product type.
	 * @return   array                      Filtered product IDs.
	 */
	private function filter_by_product_type( array $product_ids, string $product_type ): array {
		return array_filter(
			$product_ids,
			function ( $product_id ) use ( $product_type ) {
				$product = wc_get_product( $product_id );
				return $product && $product->get_type() === $product_type;
			}
		);
	}

	/**
	 * Filter products by featured status.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $product_ids    Product IDs.
	 * @param    bool  $featured       Featured status.
	 * @return   array                    Filtered product IDs.
	 */
	private function filter_by_featured_status( array $product_ids, bool $featured ): array {
		return array_filter(
			$product_ids,
			function ( $product_id ) use ( $featured ) {
				$product = wc_get_product( $product_id );
				return $product && $product->is_featured() === $featured;
			}
		);
	}

	/**
	 * Filter products by sale status.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $product_ids    Product IDs.
	 * @param    bool  $on_sale        Sale status.
	 * @return   array                    Filtered product IDs.
	 */
	private function filter_by_sale_status( array $product_ids, bool $on_sale ): array {
		return array_filter(
			$product_ids,
			function ( $product_id ) use ( $on_sale ) {
				$product = wc_get_product( $product_id );
				return $product && $product->is_on_sale() === $on_sale;
			}
		);
	}

	/**
	 * Filter products by custom attributes.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $product_ids    Product IDs.
	 * @param    array $attributes     Attribute filters.
	 * @return   array                    Filtered product IDs.
	 */
	private function filter_by_attributes( array $product_ids, array $attributes ): array {
		return array_filter(
			$product_ids,
			function ( $product_id ) use ( $attributes ) {
				$product = wc_get_product( $product_id );
				if ( ! $product ) {
					return false;
				}

				foreach ( $attributes as $attribute_name => $attribute_values ) {
					$product_attribute = $product->get_attribute( $attribute_name );
					if ( empty( $product_attribute ) ) {
						return false;
					}

					$product_values = array_map( 'trim', explode( ',', $product_attribute ) );
					if ( empty( array_intersect( $product_values, $attribute_values ) ) ) {
						return false;
					}
				}

				return true;
			}
		);
	}

	/**
	 * Get random products from selection.
	 *
	 * Note: array_rand() returns different types based on count parameter.
	 * - count > 1: Returns array of keys
	 * - count = 1: Returns single integer key (NOT an array)
	 *
	 * This caused crashes when count=1 because array_flip() expected an array.
	 *
	 * @since    1.0.0
	 * @param    array $product_ids    Product IDs.
	 * @param    int   $count          Number of products to select.
	 * @return   array                    Random product IDs.
	 */
	public function get_random_products( array $product_ids, int $count ): array {
		if ( $count <= 0 ) {
			$this->logger->warning(
				'Random product count must be positive',
				array(
					'count' => $count,
				)
			);
			return array();
		}

		// If requesting all or more than available, return all
		if ( count( $product_ids ) <= $count ) {
			return $product_ids;
		}

		// Handle single selection: array_rand() returns int for count=1
		if ( 1 === $count ) {
			$random_key = array_rand( $product_ids, 1 );
			return array( $product_ids[ $random_key ] );
		}

		// For count > 1, array_rand returns array of keys
		$random_keys = array_rand( $product_ids, $count );
		return array_intersect_key( $product_ids, array_flip( $random_keys ) );
	}

	/**
	 * Clear product selection cache.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function clear_cache(): void {
		if ( $this->cache ) {
			// Or delete specific keys if we know them
			$this->cache->flush();
			$this->logger->debug( 'Product selector cache cleared' );
		}
	}

	/**
	 * Validate and filter product IDs to ensure they exist.
	 *
	 * Prevents NULL products from deleted/invalid IDs.
	 * Filters out:
	 * - Non-existent product IDs
	 * - Zero or negative IDs
	 * - Products that are trashed
	 * - Invalid data types
	 *
	 * @since    1.0.0
	 * @param    array $product_ids    Product IDs to validate.
	 * @return   array                    Valid product IDs only.
	 */
	public function validate_and_filter_product_ids( array $product_ids ): array {
		if ( empty( $product_ids ) ) {
			return array();
		}

		$valid_ids = array();

		foreach ( $product_ids as $product_id ) {
			// Skip non-numeric values
			if ( ! is_numeric( $product_id ) ) {
				$this->logger->warning(
					'Invalid product ID type (not numeric)',
					array(
						'product_id' => $product_id,
						'type'       => gettype( $product_id ),
					)
				);
				continue;
			}

			$product_id = intval( $product_id );

			// Skip zero or negative IDs
			if ( $product_id <= 0 ) {
				$this->logger->warning(
					'Invalid product ID (zero or negative)',
					array(
						'product_id' => $product_id,
					)
				);
				continue;
			}

			$post_status = get_post_status( $product_id );

			if ( false === $post_status ) {
				// Product doesn't exist
				$this->logger->info(
					'Product ID does not exist (may have been deleted)',
					array(
						'product_id' => $product_id,
					)
				);
				continue;
			}

			if ( 'publish' !== $post_status ) {
				// Product exists but is not published (draft, trash, etc.)
				$this->logger->info(
					'Product exists but not published',
					array(
						'product_id' => $product_id,
						'status'     => $post_status,
					)
				);
				continue;
			}

			// Verify it's actually a product post type
			$post_type = get_post_type( $product_id );
			if ( 'product' !== $post_type ) {
				$this->logger->warning(
					'ID is not a product',
					array(
						'product_id' => $product_id,
						'post_type'  => $post_type,
					)
				);
				continue;
			}

			// All checks passed
			$valid_ids[] = $product_id;
		}

		return $valid_ids;
	}

	/**
	 * Get product selection statistics.
	 *
	 * @since    1.0.0
	 * @return   array    Selection statistics.
	 */
	public function get_selection_statistics(): array {
		$stats = array(
			'total_products'    => 0,
			'in_stock_products' => 0,
			'featured_products' => 0,
			'on_sale_products'  => 0,
			'categories_count'  => 0,
			'tags_count'        => 0,
		);

		try {
			$stats['total_products'] = wp_count_posts( 'product' )->publish;

			$in_stock_query             = new WP_Query(
				array(
					'post_type'      => 'product',
					'post_status'    => 'publish',
					'posts_per_page' => 1, // Only need count, not results
					'fields'         => 'ids',
					'meta_query'     => array(
						array(
							'key'     => '_stock_status',
							'value'   => 'instock',
							'compare' => '=',
						),
					),
				)
			);
			$stats['in_stock_products'] = $in_stock_query->found_posts;

			$featured_query             = new WP_Query(
				array(
					'post_type'      => 'product',
					'post_status'    => 'publish',
					'posts_per_page' => 1, // Only need count, not results
					'fields'         => 'ids',
					'meta_query'     => array(
						array(
							'key'     => '_featured',
							'value'   => 'yes',
							'compare' => '=',
						),
					),
				)
			);
			$stats['featured_products'] = $featured_query->found_posts;

			$stats['categories_count'] = wp_count_terms( 'product_cat' );
			$stats['tags_count']       = wp_count_terms( 'product_tag' );

		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to get selection statistics',
				array(
					'error' => $e->getMessage(),
				)
			);
		}

		return $stats;
	}

	/**
	 * Select products by categories with enhanced logic.
	 *
	 * @since    1.0.0
	 * @param    array  $category_ids      Category IDs ('all' for all categories).
	 * @param    array  $conditions        Optional conditions to apply.
	 * @param    int    $limit             Optional limit for results.
	 * @param    string $conditions_logic  Optional logic for conditions ('all' or 'any'). Default 'all'.
	 * @return   array                     Selected product IDs.
	 */
	public function select_by_categories( array $category_ids, array $conditions = array(), int $limit = 0, string $conditions_logic = 'all' ): array {
		$cache_key = 'scd_category_selection_' . md5( serialize( $category_ids ) . serialize( $conditions ) . $limit . $conditions_logic );

		if ( $this->cache ) {
			$cached_result = $this->cache->get( $cache_key );
			if ( $cached_result !== false && is_array( $cached_result ) ) {
				return $cached_result;
			}
		}

		try {
			$query_args = $this->build_product_query_args( $category_ids, $conditions, $limit );

			$query       = new WP_Query( $query_args );
			$product_ids = $query->posts;

			// Apply post-query conditions if condition engine is available
			if ( ! empty( $conditions ) && $this->condition_engine ) {
				$product_ids = $this->condition_engine->apply_conditions( $product_ids, $conditions, $conditions_logic );
			}

			if ( $this->cache ) {
				$this->cache->set( $cache_key, $product_ids, 1800 );
			}

			$this->logger->info(
				'Products selected by categories',
				array(
					'categories' => $category_ids,
					'conditions' => count( $conditions ),
					'count'      => count( $product_ids ),
				)
			);

			return $product_ids;

		} catch ( Exception $e ) {
			$this->logger->error(
				'Category-based product selection failed',
				array(
					'categories' => $category_ids,
					'error'      => $e->getMessage(),
				)
			);
			return array();
		}
	}

	/**
	 * Select products by criteria.
	 *
	 * @since    1.0.0
	 * @param    array $criteria    Criteria array with categories, conditions, limit, order, conditions_logic.
	 * @return   array                 Selected product IDs.
	 */
	private function select_by_criteria( array $criteria ): array {
		$categories       = $criteria['categories'] ?? array( 'all' );
		$conditions       = $criteria['conditions'] ?? array();
		$limit            = $criteria['limit'] ?? 0;
		$conditions_logic = $criteria['conditions_logic'] ?? 'all';

		return $this->select_by_categories( $categories, $conditions, $limit, $conditions_logic );
	}

	/**
	 * Select random products from categories.
	 *
	 * @since    1.0.0
	 * @param    array  $category_ids      Category IDs ('all' for all categories).
	 * @param    int    $count             Number of products to select.
	 * @param    array  $conditions        Optional conditions to apply.
	 * @param    string $conditions_logic  Optional logic for conditions ('all' or 'any'). Default 'all'.
	 * @return   array                     Random product IDs.
	 */
	public function select_random_by_categories( array $category_ids, int $count, array $conditions = array(), string $conditions_logic = 'all' ): array {
		if ( $count <= 0 ) {
			return array();
		}

		$all_products = $this->select_by_categories( $category_ids, $conditions, 0, $conditions_logic );

		if ( empty( $all_products ) ) {
			return array();
		}

		return $this->get_random_products( $all_products, $count );
	}

	/**
	 * Get product count by categories.
	 *
	 * @since    1.0.0
	 * @param    array  $category_ids      Category IDs ('all' for all categories).
	 * @param    array  $conditions        Optional conditions to apply.
	 * @param    string $conditions_logic  Optional logic for conditions ('all' or 'any'). Default 'all'.
	 * @return   int                       Product count.
	 */
	public function get_product_count_by_categories( array $category_ids, array $conditions = array(), string $conditions_logic = 'all' ): int {
		$cache_key = 'scd_category_count_' . md5( serialize( $category_ids ) . serialize( $conditions ) . $conditions_logic );

		if ( $this->cache ) {
			$cached_result = $this->cache->get( $cache_key );
			if ( $cached_result !== false && is_numeric( $cached_result ) ) {
				return (int) $cached_result;
			}
		}

		try {
			$query_args = $this->build_product_query_args( $category_ids, $conditions, 1000 );

			$query       = new WP_Query( $query_args );
			$product_ids = $query->posts;

			// Apply post-query conditions if condition engine is available
			if ( ! empty( $conditions ) && $this->condition_engine ) {
				$product_ids = $this->condition_engine->apply_conditions( $product_ids, $conditions, $conditions_logic );
			}

			$count = count( $product_ids );

			if ( $this->cache ) {
				$this->cache->set( $cache_key, $count, 1800 );
			}

			return $count;

		} catch ( Exception $e ) {
			$this->logger->error(
				'Product count by categories failed',
				array(
					'categories' => $category_ids,
					'error'      => $e->getMessage(),
				)
			);
			return 0;
		}
	}

	/**
	 * Build product query arguments with categories and conditions.
	 *
	 * @since    1.0.0
	 * @param    array $category_ids    Category IDs ('all' for all categories).
	 * @param    array $conditions      Optional conditions to apply.
	 * @param    int   $limit           Optional limit for results.
	 * @return   array                     WP_Query arguments.
	 */
	private function build_product_query_args( array $category_ids, array $conditions = array(), int $limit = 0 ): array {
		$query_args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => $limit > 0 ? $limit : -1,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'     => '_stock_status',
					'value'   => 'instock',
					'compare' => '=',
				),
			),
		);

		if ( ! in_array( 'all', $category_ids, true ) && ! empty( $category_ids ) ) {
			$query_args['tax_query'] = array(
				array(
					'taxonomy' => 'product_cat',
					'field'    => 'term_id',
					'terms'    => $category_ids,
					'operator' => 'IN',
				),
			);
		}

		// Apply conditions to query if condition engine is available
		if ( ! empty( $conditions ) && $this->condition_engine ) {
			$meta_query = $this->condition_engine->build_meta_query( $conditions );
			if ( ! empty( $meta_query ) && count( $meta_query ) > 1 ) {
				$query_args['meta_query'] = array_merge( $query_args['meta_query'], $meta_query );
			}
		}

		return $query_args;
	}

	/**
	 * Search products with autocomplete support.
	 *
	 * @since    1.0.0
	 * @param    string $search_term     Search term.
	 * @param    array  $category_ids    Optional category filter.
	 * @param    int    $limit           Results limit.
	 * @return   array                     Product data for autocomplete.
	 */
	public function search_products_autocomplete( string $search_term, array $category_ids = array(), int $limit = 20 ): array {
		if ( strlen( $search_term ) < SCD_Validation_Rules::SEARCH_TERM_MIN ) {
			return array();
		}

		$cache_key = 'scd_product_search_' . md5( $search_term . serialize( $category_ids ) . $limit );

		if ( $this->cache ) {
			$cached_result = $this->cache->get( $cache_key );
			if ( $cached_result !== false && is_array( $cached_result ) ) {
				return $cached_result;
			}
		}

		try {
			$query_args = array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				's'              => $search_term,
				'meta_query'     => array(
					array(
						'key'     => '_stock_status',
						'value'   => 'instock',
						'compare' => '=',
					),
				),
			);

			if ( ! empty( $category_ids ) && ! in_array( 'all', $category_ids ) ) {
				$query_args['tax_query'] = array(
					array(
						'taxonomy' => 'product_cat',
						'field'    => 'term_id',
						'terms'    => $category_ids,
						'operator' => 'IN',
					),
				);
			}

			// Also search by SKU
			$sku_query_args = $query_args;
			unset( $sku_query_args['s'] );
			$sku_query_args['meta_query'][] = array(
				'key'     => '_sku',
				'value'   => $search_term,
				'compare' => 'LIKE',
			);

			$search_query = new WP_Query( $query_args );
			$sku_query    = new WP_Query( $sku_query_args );

			// Combine and deduplicate results
			$product_ids = array_unique( array_merge( $search_query->posts, $sku_query->posts ) );
			$product_ids = array_slice( $product_ids, 0, $limit );

			$products = array();
			foreach ( $product_ids as $product_id ) {
				$product = wc_get_product( $product_id );
				if ( ! $product ) {
					continue;
				}

				$products[] = array(
					'id'           => $product_id,
					'name'         => $product->get_name(),
					'sku'          => $product->get_sku(),
					'price'        => $product->get_price(),
					'price_html'   => $product->get_price_html(),
					'image'        => wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' ),
					'permalink'    => $product->get_permalink(),
					'stock_status' => $product->get_stock_status(),
					'categories'   => wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'names' ) ),
				);
			}

			if ( $this->cache ) {
				$this->cache->set( $cache_key, $products, 900 ); // Cache for 15 minutes
			}

			return $products;

		} catch ( Exception $e ) {
			$this->logger->error(
				'Product search failed',
				array(
					'search_term' => $search_term,
					'error'       => $e->getMessage(),
				)
			);
			return array();
		}
	}

	/**
	 * Get products by specific IDs with full data.
	 *
	 * @since    1.0.0
	 * @param    array $product_ids    Product IDs.
	 * @return   array                    Product data.
	 */
	public function get_products_by_ids( array $product_ids ): array {
		if ( empty( $product_ids ) ) {
			return array();
		}

		$cache_key = 'scd_products_by_ids_' . md5( serialize( $product_ids ) );

		if ( $this->cache ) {
			$cached_result = $this->cache->get( $cache_key );
			if ( $cached_result !== false && is_array( $cached_result ) ) {
				return $cached_result;
			}
		}

		$products = array();

		try {
			// Pre-fetch all term relationships to avoid N+1 queries
			update_object_term_cache( $product_ids, 'product' );

			// Pre-fetch all post meta to avoid N+1 queries
			update_meta_cache( 'post', $product_ids );

			foreach ( $product_ids as $product_id ) {
				$product = wc_get_product( $product_id );
				if ( ! $product ) {
					continue;
				}

				$products[] = array(
					'id'             => $product_id,
					'name'           => $product->get_name(),
					'sku'            => $product->get_sku(),
					'price'          => $product->get_price(),
					'regular_price'  => $product->get_regular_price(),
					'sale_price'     => $product->get_sale_price(),
					'price_html'     => $product->get_price_html(),
					'image'          => wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' ),
					'permalink'      => $product->get_permalink(),
					'stock_status'   => $product->get_stock_status(),
					'stock_quantity' => $product->get_stock_quantity(),
					'categories'     => wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'names' ) ),
					'tags'           => wp_get_post_terms( $product_id, 'product_tag', array( 'fields' => 'names' ) ),
					'type'           => $product->get_type(),
					'featured'       => $product->is_featured(),
					'on_sale'        => $product->is_on_sale(),
					'rating'         => $product->get_average_rating(),
					'review_count'   => $product->get_review_count(),
					'total_sales'    => get_post_meta( $product_id, 'total_sales', true ) ?: 0,
				);
			}

			if ( $this->cache ) {
				$this->cache->set( $cache_key, $products, 1800 );
			}
		} catch ( Exception $e ) {
			$this->logger->error(
				'Get products by IDs failed',
				array(
					'product_ids' => $product_ids,
					'error'       => $e->getMessage(),
				)
			);
		}

		return $products;
	}

	/**
	 * Get all product categories with hierarchy.
	 *
	 * @since    1.0.0
	 * @param    bool $include_empty    Include empty categories.
	 * @return   array                     Category data with hierarchy.
	 */
	public function get_product_categories_hierarchy( bool $include_empty = false ): array {
		$cache_key = 'scd_categories_hierarchy_' . ( $include_empty ? 'with_empty' : 'no_empty' );

		if ( $this->cache ) {
			$cached_result = $this->cache->get( $cache_key );
			if ( $cached_result !== false && is_array( $cached_result ) ) {
				return $cached_result;
			}
		}

		try {
			$categories = get_terms(
				array(
					'taxonomy'   => 'product_cat',
					'hide_empty' => ! $include_empty,
					'orderby'    => 'name',
					'order'      => 'ASC',
				)
			);

			if ( is_wp_error( $categories ) || ! is_array( $categories ) ) {
				$this->logger->warning(
					'Failed to get product categories',
					array(
						'include_empty' => $include_empty,
						'error'         => is_wp_error( $categories ) ? $categories->get_error_message() : 'Invalid categories data',
					)
				);
				return array();
			}

			$hierarchy    = array();
			$category_map = array();

			// First pass: create category map
			foreach ( $categories as $category ) {
				if ( ! is_object( $category ) || ! isset( $category->term_id ) ) {
					continue;
				}

				$category_map[ $category->term_id ] = array(
					'id'       => $category->term_id,
					'name'     => $category->name ?? '',
					'slug'     => $category->slug ?? '',
					'parent'   => $category->parent ?? 0,
					'count'    => $category->count ?? 0,
					'children' => array(),
				);
			}

			// Second pass: build hierarchy
			foreach ( $category_map as $category ) {
				if ( 0 === $category['parent'] ) {
					$hierarchy[] = $category;
				} elseif ( isset( $category_map[ $category['parent'] ] ) ) {
						$category_map[ $category['parent'] ]['children'][] = $category;
				}
			}

			if ( $this->cache ) {
				$this->cache->set( $cache_key, $hierarchy, 3600 );
			}

			return $hierarchy;

		} catch ( Exception $e ) {
			$this->logger->error(
				'Get categories hierarchy failed',
				array(
					'error'         => $e->getMessage(),
					'include_empty' => $include_empty,
				)
			);
			return array();
		}
	}

	/**
	 * Validate product selection configuration.
	 *
	 * @since    1.0.0
	 * @param    array $config    Selection configuration.
	 * @return   array              Validation result with errors.
	 */
	public function validate_selection_config( array $config ): array {
		$errors = array();

		if ( ! isset( $config['product_selection_type'] ) ) {
			$errors[] = __( 'Product selection type is required.', 'smart-cycle-discounts' );
		} elseif ( ! in_array( $config['product_selection_type'], array( 'all_products', 'random_products', 'specific_products', 'smart_selection' ) ) ) {
			$errors[] = __( 'Invalid selection type.', 'smart-cycle-discounts' );
		}

		if ( ! isset( $config['categories'] ) || ! is_array( $config['categories'] ) ) {
			$errors[] = __( 'Categories selection is required.', 'smart-cycle-discounts' );
		}

		switch ( $config['product_selection_type'] ?? '' ) {
			case 'random_products':
				if ( ! isset( $config['random_count'] ) || ! is_numeric( $config['random_count'] ) || $config['random_count'] <= 0 ) {
					$errors[] = __( 'Random product count must be a positive number.', 'smart-cycle-discounts' );
				}
				break;

			case 'specific_products':
				if ( ! isset( $config['product_ids'] ) || ! is_array( $config['product_ids'] ) || empty( $config['product_ids'] ) ) {
					$errors[] = __( 'At least one specific product must be selected.', 'smart-cycle-discounts' );
				}
				break;
		}

		if ( ! empty( $config['conditions'] ) && $this->condition_engine ) {
			foreach ( $config['conditions'] as $condition ) {
				if ( ! $this->condition_engine->validate_condition( $condition ) ) {
					$errors[] = __( 'Invalid filter condition detected.', 'smart-cycle-discounts' );
					break;
				}
			}
		}

		return array(
			'valid'  => empty( $errors ),
			'errors' => $errors,
		);
	}

	/**
	 * Process product selection configuration.
	 *
	 * @since    1.0.0
	 * @param    array $config    Selection configuration.
	 * @return   array              Processing result with product IDs.
	 */
	public function process_selection_config( array $config ): array {
		$validation = $this->validate_selection_config( $config );
		if ( ! $validation['valid'] ) {
			return array(
				'success'     => false,
				'errors'      => $validation['errors'],
				'product_ids' => array(),
			);
		}

		try {
			$product_ids      = array();
			$conditions       = $config['conditions'] ?? array();
			$conditions_logic = $config['conditions_logic'] ?? 'all';

			$criteria = array(
				'conditions'       => $conditions,
				'conditions_logic' => $conditions_logic,
				'categories'       => $config['categories'] ?? array( 'all' ),
			);

			switch ( $config['product_selection_type'] ) {
				case 'all_products':
					$product_ids = $this->select_by_criteria( $criteria );
					break;

				case 'random_products':
					$criteria['limit'] = intval( $config['random_count'] );
					$criteria['order'] = 'rand';
					$product_ids       = $this->select_by_criteria( $criteria );
					break;

				case 'specific_products':
				// Validate product existence to prevent null values
					$raw_product_ids = $config['product_ids'];
					$product_ids     = $this->validate_and_filter_product_ids( $raw_product_ids );

					// Log if products were filtered out
					if ( count( $product_ids ) < count( $raw_product_ids ) ) {
						$removed_count = count( $raw_product_ids ) - count( $product_ids );
						$this->logger->warning(
							'Some product IDs filtered out (deleted or invalid)',
							array(
								'original_count' => count( $raw_product_ids ),
								'filtered_count' => count( $product_ids ),
								'removed_count'  => $removed_count,
							)
						);
					}

					// Apply conditions to specific products if any
					if ( ! empty( $conditions ) && $this->condition_engine ) {
						$product_ids = $this->condition_engine->apply_conditions( $product_ids, $conditions, $conditions_logic );
					}
					break;

				case 'smart_selection':
					$smart_criterion = $config['smart_criteria'] ?? '';
					$criteria_array = $smart_criterion ? array( $smart_criterion ) : array();
					$product_ids    = $this->select_by_smart_criteria( $criteria_array, $config['categories'] ?? array( 'all' ), $conditions );
					break;
			}

			return array(
				'success'     => true,
				'errors'      => array(),
				'product_ids' => $product_ids,
				'count'       => count( $product_ids ),
			);

		} catch ( Exception $e ) {
			$this->logger->error(
				'Product selection processing failed',
				array(
					'config' => $config,
					'error'  => $e->getMessage(),
				)
			);

			return array(
				'success'     => false,
				'errors'      => array( __( 'Product selection processing failed.', 'smart-cycle-discounts' ) ),
				'product_ids' => array(),
			);
		}
	}

	/**
	 * Select products by smart criteria
	 *
	 * @since    1.0.0
	 * @param    array $smart_criteria    Smart selection criteria
	 * @param    array $category_ids      Category IDs to filter by
	 * @param    array $conditions        Additional conditions
	 * @return   array                       Product IDs
	 */
	public function select_by_smart_criteria( array $smart_criteria, array $category_ids = array( 'all' ), array $conditions = array() ): array {
		// Transform smart criteria to conditions
		$criteria_conditions = $this->transform_smart_criteria_to_conditions( $smart_criteria );

		// Merge with existing conditions
		$all_conditions = array_merge( $criteria_conditions, $conditions );

		// Use existing category selection with combined conditions
		return $this->select_by_categories( $category_ids, $all_conditions );
	}

	/**
	 * Transform smart criteria to condition engine conditions
	 *
	 * @since    1.0.0
	 * @param    array $smart_criteria    Smart selection criteria
	 * @return   array                       Condition engine conditions
	 */
	private function transform_smart_criteria_to_conditions( array $smart_criteria ): array {
		$conditions = array();

		foreach ( $smart_criteria as $criterion ) {
			switch ( $criterion ) {
				case 'best_sellers':
					$conditions[] = array(
						'property' => 'meta',
						'operator' => 'greater_than',
						'value'    => '0',
						'meta_key' => 'total_sales',
					);
					break;

				case 'featured':
					$conditions[] = array(
						'property' => 'meta',
						'operator' => 'equals',
						'value'    => 'yes',
						'meta_key' => '_featured',
					);
					break;

				case 'low_stock':
					// Products with stock quantity 10 or less
					$conditions[] = array(
						'property' => 'meta',
						'operator' => 'less_than_equal',
						'value'    => '10',
						'meta_key' => '_stock',
					);
					break;

				case 'new_arrivals':
					// Products created in the last 30 days
					$date_30_days_ago = date( 'Y-m-d', strtotime( '-30 days' ) );
					$conditions[]     = array(
						'property' => 'date',
						'operator' => 'greater_than',
						'value'    => $date_30_days_ago,
					);
					break;
			}
		}

		return $conditions;
	}

	/**
	 * Handle product deletion.
	 *
	 * Clean up campaigns when products are deleted to prevent orphaned product IDs.
	 *
	 * @since    1.0.0
	 * @param    int $product_id    Product ID being deleted.
	 * @return   void
	 */
	public function handle_product_deletion( int $product_id ): void {
		// Only handle product post type
		if ( 'product' !== get_post_type( $product_id ) ) {
			return;
		}

		$this->logger->info(
			'Product being deleted, invalidating caches',
			array(
				'product_id' => $product_id,
			)
		);

		if ( $this->cache ) {
			$this->cache->flush();
		}

		// Note: Campaigns are not automatically updated to remove this product ID.
		// The validate_and_filter_product_ids method handles cleanup on-demand
		// when campaigns are loaded. This approach prevents unnecessary database
		// writes and allows for bulk cleanup operations.

		/**
		 * Fires after product deletion is handled.
		 *
		 * @since 1.0.0
		 * @param int $product_id The product ID that was deleted.
		 */
		do_action( 'scd_product_deleted', $product_id );
	}

	/**
	 * Handle product update.
	 *
	 *
	 * Invalidate cache when products are updated.
	 * Stock status, price, and other attributes may have changed.
	 *
	 * @since    1.0.0
	 * @param    int $product_id    Product ID being updated.
	 * @return   void
	 */
	public function handle_product_update( int $product_id ): void {
		// Only handle product post type
		if ( 'product' !== get_post_type( $product_id ) ) {
			return;
		}

		$this->logger->debug(
			'Product updated, invalidating caches',
			array(
				'product_id' => $product_id,
			)
		);

		if ( $this->cache ) {
			$this->cache->flush();
		}

		/**
		 * Fires after product update is handled.
		 *
		 * @since 1.0.0
		 * @param int $product_id The product ID that was updated.
		 */
		do_action( 'scd_product_updated', $product_id );
	}

	/**
	 * Handle taxonomy (category/tag) changes.
	 *
	 * Invalidate cache when product categories or tags are created, edited, or deleted.
	 *
	 * @since    1.0.0
	 * @param    int $term_id    Term ID.
	 * @return   void
	 */
	public function handle_taxonomy_change( int $term_id ): void {
		$this->logger->info(
			'Product taxonomy changed, invalidating caches',
			array(
				'term_id' => $term_id,
			)
		);

		if ( $this->cache ) {
			$this->cache->invalidate_product();

			// Clear reference data cache for categories/tags
			if ( class_exists( 'SCD_Reference_Data_Cache' ) ) {
				$ref_cache = new SCD_Reference_Data_Cache();
				$ref_cache->delete( 'categories' );
				$ref_cache->delete( 'tags' );
			}
		}

		/**
		 * Fires after taxonomy change is handled.
		 *
		 * @since 1.0.0
		 * @param int $term_id The term ID that was changed.
		 */
		do_action( 'scd_taxonomy_changed', $term_id );
	}
}
