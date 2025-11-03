<?php
/**
 * Product Filter Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/products/class-product-filter.php
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Product Filter
 *
 * Advanced product filtering capabilities for campaigns.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/products
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Product_Filter {

	/**
	 * Product selector instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Product_Selector    $product_selector    Product selector.
	 */
	private SCD_Product_Selector $product_selector;

	/**
	 * Logger instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Logger    $logger    Logger instance.
	 */
	private SCD_Logger $logger;

	/**
	 * Initialize the product filter.
	 *
	 * @since    1.0.0
	 * @param    SCD_Product_Selector $product_selector    Product selector.
	 * @param    SCD_Logger           $logger              Logger instance.
	 */
	public function __construct( SCD_Product_Selector $product_selector, SCD_Logger $logger ) {
		$this->product_selector = $product_selector;
		$this->logger           = $logger;
	}

	/**
	 * Apply filters to product selection.
	 *
	 * @since    1.0.0
	 * @param    array $product_ids    Product IDs to filter.
	 * @param    array $filters        Filter configuration.
	 * @return   array                    Filtered product IDs.
	 */
	public function apply_filters( array $product_ids, array $filters ): array {
		if ( empty( $product_ids ) || empty( $filters ) ) {
			return $product_ids;
		}

		$filtered_ids = $product_ids;

		foreach ( $filters as $filter_type => $filter_config ) {
			switch ( $filter_type ) {
				case 'category':
					$filtered_ids = $this->filter_by_category( $filtered_ids, $filter_config );
					break;

				case 'price_range':
					$filtered_ids = $this->filter_by_price_range( $filtered_ids, $filter_config );
					break;

				case 'stock':
					$filtered_ids = $this->filter_by_stock( $filtered_ids, $filter_config );
					break;

				case 'attributes':
					$filtered_ids = $this->filter_by_attributes( $filtered_ids, $filter_config );
					break;

				case 'tags':
					$filtered_ids = $this->filter_by_tags( $filtered_ids, $filter_config );
					break;

				case 'rating':
					$filtered_ids = $this->filter_by_rating( $filtered_ids, $filter_config );
					break;

				case 'sales_performance':
					$filtered_ids = $this->filter_by_sales_performance( $filtered_ids, $filter_config );
					break;

				case 'date_created':
					$filtered_ids = $this->filter_by_date_created( $filtered_ids, $filter_config );
					break;

				case 'custom':
					$filtered_ids = $this->apply_custom_filter( $filtered_ids, $filter_config );
					break;
			}

			// If no products remain, break early
			if ( empty( $filtered_ids ) ) {
				break;
			}
		}

		$this->logger->debug(
			'Filters applied to products',
			array(
				'original_count' => count( $product_ids ),
				'filtered_count' => count( $filtered_ids ),
				'filters'        => array_keys( $filters ),
			)
		);

		return $filtered_ids;
	}

	/**
	 * Filter products by category.
	 *
	 * @since    1.0.0
	 * @param    array $product_ids    Product IDs.
	 * @param    array $config         Category filter config.
	 * @return   array                    Filtered product IDs.
	 */
	public function filter_by_category( array $product_ids, array $config ): array {
		$categories = $config['categories'] ?? array();

		// Security: Whitelist validation for operator
		$allowed_operators = array( 'IN', 'NOT IN', 'AND' );
		$operator          = isset( $config['operator'] ) ? $config['operator'] : 'IN';
		if ( ! in_array( $operator, $allowed_operators, true ) ) {
			$this->logger->warning( 'Invalid category operator provided', array( 'operator' => $operator ) );
			$operator = 'IN'; // Fallback to safe default
		}

		$include_children = $config['include_children'] ?? true;

		if ( empty( $categories ) ) {
			return $product_ids;
		}

		// Security: Limit number of categories to prevent performance issues
		if ( count( $categories ) > 100 ) {
			$this->logger->warning(
				'Too many categories provided, limiting to 100',
				array(
					'count' => count( $categories ),
				)
			);
			$categories = array_slice( $categories, 0, 100 );
		}

		$category_ids = $categories;
		if ( $include_children ) {
			foreach ( $categories as $category_id ) {
				$children = get_term_children( $category_id, 'product_cat' );
				if ( ! is_wp_error( $children ) ) {
					$category_ids = array_merge( $category_ids, $children );
				}
			}
			$category_ids = array_unique( $category_ids );
		}

		return array_filter(
			$product_ids,
			function ( $product_id ) use ( $category_ids, $operator ) {
				$product_categories = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );

				switch ( $operator ) {
					case 'NOT IN':
						return empty( array_intersect( $product_categories, $category_ids ) );

					case 'AND':
						return count( array_intersect( $product_categories, $category_ids ) ) === count( $category_ids );

					case 'IN':
					default:
						return ! empty( array_intersect( $product_categories, $category_ids ) );
				}
			}
		);
	}

	/**
	 * Filter products by price range.
	 *
	 * @since    1.0.0
	 * @param    array $product_ids    Product IDs.
	 * @param    array $config         Price filter config.
	 * @return   array                    Filtered product IDs.
	 */
	public function filter_by_price_range( array $product_ids, array $config ): array {
		$min_price = isset( $config['min'] ) ? floatval( $config['min'] ) : 0;
		$max_price = isset( $config['max'] ) ? floatval( $config['max'] ) : PHP_FLOAT_MAX;

		// Security: Whitelist validation for price_type
		$allowed_price_types = array( 'regular', 'sale', 'current' );
		$price_type          = isset( $config['price_type'] ) ? $config['price_type'] : 'regular';
		if ( ! in_array( $price_type, $allowed_price_types, true ) ) {
			$this->logger->warning( 'Invalid price_type provided', array( 'price_type' => $price_type ) );
			$price_type = 'regular'; // Fallback to safe default
		}

		return array_filter(
			$product_ids,
			function ( $product_id ) use ( $min_price, $max_price, $price_type ) {
				$product = wc_get_product( $product_id );
				if ( ! $product ) {
					return false;
				}

				switch ( $price_type ) {
					case 'sale':
						$price = floatval( $product->get_sale_price() );
						break;
					case 'regular':
						$price = floatval( $product->get_regular_price() );
						break;
					case 'current':
					default:
						$price = floatval( $product->get_price() );
						break;
				}

				return $price >= $min_price && $price <= $max_price;
			}
		);
	}

	/**
	 * Filter products by stock status and quantity.
	 *
	 * @since    1.0.0
	 * @param    array $product_ids    Product IDs.
	 * @param    array $config         Stock filter config.
	 * @return   array                    Filtered product IDs.
	 */
	public function filter_by_stock( array $product_ids, array $config ): array {
		// Security: Whitelist validation for stock_status
		$allowed_stock_statuses = array( 'instock', 'outofstock', 'onbackorder' );
		$stock_status           = isset( $config['status'] ) ? $config['status'] : 'instock';
		if ( ! in_array( $stock_status, $allowed_stock_statuses, true ) ) {
			$this->logger->warning( 'Invalid stock_status provided', array( 'status' => $stock_status ) );
			$stock_status = 'instock'; // Fallback to safe default
		}

		$min_quantity = isset( $config['min_quantity'] ) ? intval( $config['min_quantity'] ) : null;
		$max_quantity = isset( $config['max_quantity'] ) ? intval( $config['max_quantity'] ) : null;

		return array_filter(
			$product_ids,
			function ( $product_id ) use ( $stock_status, $min_quantity, $max_quantity ) {
				$product = wc_get_product( $product_id );
				if ( ! $product ) {
					return false;
				}

				if ( $product->get_stock_status() !== $stock_status ) {
					return false;
				}

				if ( $min_quantity !== null || $max_quantity !== null ) {
					$quantity = $product->get_stock_quantity();

					if ( $quantity === null ) {
						// Product doesn't track quantity
						return $stock_status === 'instock';
					}

					if ( $min_quantity !== null && $quantity < $min_quantity ) {
						return false;
					}

					if ( $max_quantity !== null && $quantity > $max_quantity ) {
						return false;
					}
				}

				return true;
			}
		);
	}

	/**
	 * Filter products by attributes.
	 *
	 * @since    1.0.0
	 * @param    array $product_ids    Product IDs.
	 * @param    array $config         Attributes filter config.
	 * @return   array                    Filtered product IDs.
	 */
	public function filter_by_attributes( array $product_ids, array $config ): array {
		$attributes = $config['attributes'] ?? array();

		// Security: Whitelist validation for operator
		$allowed_operators = array( 'AND', 'OR' );
		$operator          = isset( $config['operator'] ) ? $config['operator'] : 'AND';
		if ( ! in_array( $operator, $allowed_operators, true ) ) {
			$this->logger->warning( 'Invalid attribute operator provided', array( 'operator' => $operator ) );
			$operator = 'AND'; // Fallback to safe default
		}

		if ( empty( $attributes ) ) {
			return $product_ids;
		}

		return array_filter(
			$product_ids,
			function ( $product_id ) use ( $attributes, $operator ) {
				$product = wc_get_product( $product_id );
				if ( ! $product ) {
					return false;
				}

				$matches          = 0;
				$total_attributes = count( $attributes );

				foreach ( $attributes as $attribute_name => $attribute_values ) {
					$product_attribute = $product->get_attribute( $attribute_name );

					if ( empty( $product_attribute ) ) {
						continue;
					}

					$product_values = array_map( 'trim', explode( ',', $product_attribute ) );

					if ( ! empty( array_intersect( $product_values, $attribute_values ) ) ) {
						$matches++;
					}
				}

				return $operator === 'OR' ? $matches > 0 : $matches === $total_attributes;
			}
		);
	}

	/**
	 * Filter products by tags.
	 *
	 * @since    1.0.0
	 * @param    array $product_ids    Product IDs.
	 * @param    array $config         Tags filter config.
	 * @return   array                    Filtered product IDs.
	 */
	public function filter_by_tags( array $product_ids, array $config ): array {
		$tags = $config['tags'] ?? array();

		// Security: Whitelist validation for operator
		$allowed_operators = array( 'IN', 'NOT IN', 'AND' );
		$operator          = isset( $config['operator'] ) ? $config['operator'] : 'IN';
		if ( ! in_array( $operator, $allowed_operators, true ) ) {
			$this->logger->warning( 'Invalid tag operator provided', array( 'operator' => $operator ) );
			$operator = 'IN'; // Fallback to safe default
		}

		if ( empty( $tags ) ) {
			return $product_ids;
		}

		// Security: Limit number of tags to prevent performance issues
		if ( count( $tags ) > 100 ) {
			$this->logger->warning(
				'Too many tags provided, limiting to 100',
				array(
					'count' => count( $tags ),
				)
			);
			$tags = array_slice( $tags, 0, 100 );
		}

		return array_filter(
			$product_ids,
			function ( $product_id ) use ( $tags, $operator ) {
				$product_tags = wp_get_post_terms( $product_id, 'product_tag', array( 'fields' => 'ids' ) );

				switch ( $operator ) {
					case 'NOT IN':
						return empty( array_intersect( $product_tags, $tags ) );

					case 'AND':
						return count( array_intersect( $product_tags, $tags ) ) === count( $tags );

					case 'IN':
					default:
						return ! empty( array_intersect( $product_tags, $tags ) );
				}
			}
		);
	}

	/**
	 * Filter products by rating.
	 *
	 * @since    1.0.0
	 * @param    array $product_ids    Product IDs.
	 * @param    array $config         Rating filter config.
	 * @return   array                    Filtered product IDs.
	 */
	public function filter_by_rating( array $product_ids, array $config ): array {
		$min_rating  = isset( $config['min'] ) ? floatval( $config['min'] ) : 0;
		$max_rating  = isset( $config['max'] ) ? floatval( $config['max'] ) : 5;
		$min_reviews = isset( $config['min_reviews'] ) ? intval( $config['min_reviews'] ) : 0;

		return array_filter(
			$product_ids,
			function ( $product_id ) use ( $min_rating, $max_rating, $min_reviews ) {
				$product = wc_get_product( $product_id );
				if ( ! $product ) {
					return false;
				}

				$average_rating = floatval( $product->get_average_rating() );
				$review_count   = intval( $product->get_review_count() );

				return $average_rating >= $min_rating
				&& $average_rating <= $max_rating
				&& $review_count >= $min_reviews;
			}
		);
	}

	/**
	 * Filter products by sales performance.
	 *
	 * @since    1.0.0
	 * @param    array $product_ids    Product IDs.
	 * @param    array $config         Sales performance filter config.
	 * @return   array                    Filtered product IDs.
	 */
	public function filter_by_sales_performance( array $product_ids, array $config ): array {
		// Security: Validate and limit period to prevent excessive database queries
		$period = isset( $config['period'] ) ? intval( $config['period'] ) : 30;
		if ( $period < 1 || $period > 365 ) {
			$this->logger->warning( 'Invalid sales period provided', array( 'period' => $period ) );
			$period = 30; // Fallback to safe default
		}

		$min_sales = isset( $config['min_sales'] ) ? intval( $config['min_sales'] ) : 0;
		$max_sales = isset( $config['max_sales'] ) ? intval( $config['max_sales'] ) : PHP_INT_MAX;

		if ( $min_sales < 0 ) {
			$min_sales = 0;
		}
		if ( $max_sales < $min_sales ) {
			$max_sales = PHP_INT_MAX;
		}

		$start_date = date( 'Y-m-d', strtotime( "-{$period} days" ) );
		$end_date   = date( 'Y-m-d' );

		return array_filter(
			$product_ids,
			function ( $product_id ) use ( $start_date, $end_date, $min_sales, $max_sales ) {
				$sales_count = $this->get_product_sales_count( $product_id, $start_date, $end_date );
				return $sales_count >= $min_sales && $sales_count <= $max_sales;
			}
		);
	}

	/**
	 * Filter products by date created.
	 *
	 * @since    1.0.0
	 * @param    array $product_ids    Product IDs.
	 * @param    array $config         Date filter config.
	 * @return   array                    Filtered product IDs.
	 */
	public function filter_by_date_created( array $product_ids, array $config ): array {
		$start_date = $config['start_date'] ?? null;
		$end_date   = $config['end_date'] ?? null;

		if ( ! $start_date && ! $end_date ) {
			return $product_ids;
		}

		return array_filter(
			$product_ids,
			function ( $product_id ) use ( $start_date, $end_date ) {
				$post = get_post( $product_id );
				if ( ! $post ) {
					return false;
				}

				$created_date = $post->post_date;

				if ( $start_date && $created_date < $start_date ) {
					return false;
				}

				if ( $end_date && $created_date > $end_date ) {
					return false;
				}

				return true;
			}
		);
	}

	/**
	 * Apply custom filter.
	 *
	 * @since    1.0.0
	 * @param    array $product_ids    Product IDs.
	 * @param    array $config         Custom filter config.
	 * @return   array                    Filtered product IDs.
	 */
	public function apply_custom_filter( array $product_ids, array $config ): array {
		$callback = $config['callback'] ?? null;
		$args     = $config['args'] ?? array();

		if ( ! $callback || ! is_callable( $callback ) ) {
			return $product_ids;
		}

		try {
			return array_filter(
				$product_ids,
				function ( $product_id ) use ( $callback, $args ) {
					return call_user_func( $callback, $product_id, $args );
				}
			);
		} catch ( Exception $e ) {
			$this->logger->error(
				'Custom filter failed',
				array(
					'error'  => $e->getMessage(),
					'config' => $config,
				)
			);
			return $product_ids;
		}
	}

	/**
	 * Get available filter options for products.
	 *
	 * @since    1.0.0
	 * @param    array $product_ids    Product IDs to analyze.
	 * @return   array                    Available filter options.
	 */
	public function get_available_filters( array $product_ids = array() ): array {
		$filters = array(
			'categories'   => $this->get_available_categories( $product_ids ),
			'tags'         => $this->get_available_tags( $product_ids ),
			'attributes'   => $this->get_available_attributes( $product_ids ),
			'price_range'  => $this->get_price_range( $product_ids ),
			'stock_levels' => $this->get_stock_levels( $product_ids ),
			'ratings'      => $this->get_rating_distribution( $product_ids ),
		);

		return apply_filters( 'scd_available_product_filters', $filters, $product_ids );
	}

	/**
	 * Get available categories for filtering.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $product_ids    Product IDs.
	 * @return   array                    Available categories.
	 */
	private function get_available_categories( array $product_ids ): array {
		if ( empty( $product_ids ) ) {
			$terms = get_terms(
				array(
					'taxonomy'   => 'product_cat',
					'hide_empty' => true,
					'fields'     => 'id=>name',
				)
			);

			if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
				return array();
			}

			return $terms;
		}

		$category_ids = array();
		foreach ( $product_ids as $product_id ) {
			$categories   = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );
			$category_ids = array_merge( $category_ids, $categories );
		}

		$category_ids = array_unique( $category_ids );

		$categories = array();
		foreach ( $category_ids as $category_id ) {
			$term = get_term( $category_id );
			if ( $term && ! is_wp_error( $term ) ) {
				$categories[ $category_id ] = $term->name;
			}
		}

		return $categories;
	}

	/**
	 * Get available tags for filtering.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $product_ids    Product IDs.
	 * @return   array                    Available tags.
	 */
	private function get_available_tags( array $product_ids ): array {
		if ( empty( $product_ids ) ) {
			$terms = get_terms(
				array(
					'taxonomy'   => 'product_tag',
					'hide_empty' => true,
					'fields'     => 'id=>name',
				)
			);

			if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
				return array();
			}

			return $terms;
		}

		$tag_ids = array();
		foreach ( $product_ids as $product_id ) {
			$tags    = wp_get_post_terms( $product_id, 'product_tag', array( 'fields' => 'ids' ) );
			$tag_ids = array_merge( $tag_ids, $tags );
		}

		$tag_ids = array_unique( $tag_ids );

		$tags = array();
		foreach ( $tag_ids as $tag_id ) {
			$term = get_term( $tag_id );
			if ( $term && ! is_wp_error( $term ) ) {
				$tags[ $tag_id ] = $term->name;
			}
		}

		return $tags;
	}

	/**
	 * Get available attributes for filtering.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $product_ids    Product IDs.
	 * @return   array                    Available attributes.
	 */
	private function get_available_attributes( array $product_ids ): array {
		$attributes = array();

		$global_attributes = wc_get_attribute_taxonomies();
		foreach ( $global_attributes as $attribute ) {
			$taxonomy = wc_attribute_taxonomy_name( $attribute->attribute_name );
			$terms    = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => true,
				)
			);

			if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
				$attributes[ $attribute->attribute_label ] = wp_list_pluck( $terms, 'name', 'term_id' );
			}
		}

		return $attributes;
	}

	/**
	 * Get price range for products.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $product_ids    Product IDs.
	 * @return   array                    Price range data.
	 */
	private function get_price_range( array $product_ids ): array {
		$prices = array();

		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( $product ) {
				$price = floatval( $product->get_price() );
				if ( $price > 0 ) {
					$prices[] = $price;
				}
			}
		}

		if ( empty( $prices ) ) {
			return array(
				'min' => 0,
				'max' => 0,
			);
		}

		return array(
			'min'     => min( $prices ),
			'max'     => max( $prices ),
			'average' => array_sum( $prices ) / count( $prices ),
		);
	}

	/**
	 * Get stock levels distribution.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $product_ids    Product IDs.
	 * @return   array                    Stock levels data.
	 */
	private function get_stock_levels( array $product_ids ): array {
		$stock_data = array(
			'instock'     => 0,
			'outofstock'  => 0,
			'onbackorder' => 0,
		);

		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( $product ) {
				$status = $product->get_stock_status();
				if ( isset( $stock_data[ $status ] ) ) {
					++$stock_data[ $status ];
				}
			}
		}

		return $stock_data;
	}

	/**
	 * Get rating distribution.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $product_ids    Product IDs.
	 * @return   array                    Rating distribution data.
	 */
	private function get_rating_distribution( array $product_ids ): array {
		$ratings = array();

		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( $product ) {
				$rating = floatval( $product->get_average_rating() );
				if ( $rating > 0 ) {
					$ratings[] = $rating;
				}
			}
		}

		if ( empty( $ratings ) ) {
			return array(
				'min'     => 0,
				'max'     => 0,
				'average' => 0,
			);
		}

		return array(
			'min'     => min( $ratings ),
			'max'     => max( $ratings ),
			'average' => array_sum( $ratings ) / count( $ratings ),
		);
	}

	/**
	 * Get product sales count for a period.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    int    $product_id    Product ID.
	 * @param    string $start_date    Start date.
	 * @param    string $end_date      End date.
	 * @return   int                      Sales count.
	 */
	private function get_product_sales_count( int $product_id, string $start_date, string $end_date ): int {
		global $wpdb;

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $start_date ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $end_date ) ) {
			$this->logger->error(
				'Invalid date format in sales count query',
				array(
					'start_date' => $start_date,
					'end_date'   => $end_date,
				)
			);
			return 0;
		}

		// Additional validation: ensure dates are valid
		$start_timestamp = strtotime( $start_date );
		$end_timestamp   = strtotime( $end_date );

		if ( false === $start_timestamp || false === $end_timestamp ) {
			$this->logger->error(
				'Invalid date values in sales count query',
				array(
					'start_date' => $start_date,
					'end_date'   => $end_date,
				)
			);
			return 0;
		}

		$query = $wpdb->prepare(
			"
            SELECT SUM(order_item_meta.meta_value) as total_sales
            FROM {$wpdb->prefix}woocommerce_order_items as order_items
            LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta as order_item_meta
                ON order_items.order_item_id = order_item_meta.order_item_id
            LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta as order_item_meta_product
                ON order_items.order_item_id = order_item_meta_product.order_item_id
            LEFT JOIN {$wpdb->posts} as posts
                ON order_items.order_id = posts.ID
            WHERE posts.post_type = 'shop_order'
                AND posts.post_status IN ('wc-completed', 'wc-processing')
                AND posts.post_date >= %s
                AND posts.post_date <= %s
                AND order_item_meta.meta_key = '_qty'
                AND order_item_meta_product.meta_key = '_product_id'
                AND order_item_meta_product.meta_value = %d
        ",
			$start_date,
			$end_date,
			$product_id
		);

		$result = $wpdb->get_var( $query );
		return intval( $result );
	}

	/**
	 * Validate filter configuration.
	 *
	 * @since    1.0.0
	 * @param    array $filters    Filter configuration.
	 * @return   bool                 True if valid.
	 */
	public function validate_filters( array $filters ): bool {
		$valid_filter_types = array(
			'category',
			'price_range',
			'stock',
			'attributes',
			'tags',
			'rating',
			'sales_performance',
			'date_created',
			'custom',
		);

		foreach ( $filters as $filter_type => $config ) {
			if ( ! in_array( $filter_type, $valid_filter_types ) ) {
				$this->logger->warning( 'Invalid filter type', array( 'type' => $filter_type ) );
				return false;
			}

			if ( ! is_array( $config ) ) {
				$this->logger->warning( 'Invalid filter config', array( 'type' => $filter_type ) );
				return false;
			}
		}

		return true;
	}
}
