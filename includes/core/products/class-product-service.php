<?php
/**
 * Product Service Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/products/class-product-service.php
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
 * Product Service Class
 *
 * Handles product-related business logic.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/services
 * @author     Webstepper <contact@webstepper.io>
 */
class SCD_Product_Service {

	/**
	 * Results per page.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      int    $per_page    Results per page.
	 */
	private $per_page = 50;

	/**
	 * Search products.
	 *
	 * @since    1.0.0
	 * @param    string $search       Search term.
	 * @param    array  $categories   Category IDs.
	 * @param    int    $page         Page number.
	 * @param    int    $per_page     Results per page.
	 * @param    array  $selected     Selected product IDs.
	 * @return   array                  Search results.
	 * @throws   Exception              If WooCommerce is not active.
	 */
	public function search_products(
		$search = '',
		$categories = array(),
		$page = 1,
		$per_page = 50,
		$selected = array()
	) {
		$search     = sanitize_text_field( $search );
		$page       = absint( $page );
		$per_page   = absint( $per_page );
		$categories = array_map( 'absint', (array) $categories );
		$selected   = array_map( 'absint', (array) $selected );

		if ( $page < 1 ) {
			$page = 1;
		}
		if ( $per_page < 1 || $per_page > 100 ) {
			$per_page = 50;
		}

		// Ensure WooCommerce is loaded in AJAX context
		$this->ensure_woocommerce_loaded();

		// Security: Limit search term length (200 chars max)
		if ( strlen( $search ) > 200 ) {
			$search = substr( $search, 0, 200 );
		}

		$args = $this->build_search_query_args( $search, $categories, $page, $per_page );

		// Debug logging only when enabled
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
		}

		// Execute query
		$query    = new WP_Query( $args );
		$products = array();

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$product_id = get_the_ID();
				$product    = wc_get_product( $product_id );

				if ( ! $product || ! $product->is_visible() ) {
					continue;
				}

				$products[] = $this->format_product_for_response( $product );
			}
			wp_reset_postdata();
		}

		// Debug logging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {

			$total_products = wp_count_posts( 'product' );
		}

		// Ensure selected products are included if they match criteria
		if ( ! empty( $selected ) ) {
			$existing_ids = array_column( $products, 'id' );
			$missing_ids  = array_diff( $selected, $existing_ids );

			if ( ! empty( $missing_ids ) ) {
				foreach ( $missing_ids as $product_id ) {
					$product = wc_get_product( $product_id );
					if ( $product && $this->product_matches_criteria( $product, $search, $categories ) ) {
						$products[] = $this->format_product_for_response( $product );
					}
				}
			}
		}

		return array(
			'products'     => $products,
			'total'        => $query->found_posts,
			'pages'        => ceil( $query->found_posts / $per_page ),
			'current_page' => $page,
			'per_page'     => $per_page,
		);
	}

	/**
	 * Get products by IDs.
	 *
	 * @since    1.0.0
	 * @param    array $product_ids    Product IDs.
	 * @return   array                    Products data.
	 */
	public function get_products_by_ids( $product_ids ) {
		// Ensure WooCommerce is loaded in AJAX context
		$this->ensure_woocommerce_loaded();

		$products = array();

		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( $product ) {
				$products[] = $this->format_product_for_response( $product );
			}
		}

		return $products;
	}

	/**
	 * Get product stats for wizard summary.
	 *
	 * @since    1.0.0
	 * @param    array $product_ids    Product IDs.
	 * @return   array                    Product stats.
	 */
	public function get_product_stats( $product_ids ) {
		// Ensure WooCommerce is loaded in AJAX context
		$this->ensure_woocommerce_loaded();

		$stats = array(
			'total_products' => count( $product_ids ),
			'categories'     => array(),
			'price_range'    => array(
				'min' => PHP_FLOAT_MAX,
				'max' => 0,
			),
			'stock_status'   => array(
				'in_stock'     => 0,
				'out_of_stock' => 0,
			),
			'types'          => array(),
			'average_price'  => 0,
			'total_value'    => 0,
		);

		if ( empty( $product_ids ) ) {
			return $stats;
		}

		$total_price     = 0;
		$category_counts = array();
		$type_counts     = array();

		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( absint( $product_id ) );
			if ( ! $product ) {
				continue;
			}

			// Price stats
			$price = $product->get_price();
			if ( '' !== $price ) {
				$price_float                 = floatval( $price );
				$total_price                += $price_float;
				$stats['price_range']['min'] = min( $stats['price_range']['min'], $price_float );
				$stats['price_range']['max'] = max( $stats['price_range']['max'], $price_float );
			}

			// Stock status
			if ( $product->is_in_stock() ) {
				++$stats['stock_status']['in_stock'];
			} else {
				++$stats['stock_status']['out_of_stock'];
			}

			// Categories
			$categories = $product->get_category_ids();
			foreach ( $categories as $cat_id ) {
				if ( ! isset( $category_counts[ $cat_id ] ) ) {
					$category_counts[ $cat_id ] = 0;
				}
				++$category_counts[ $cat_id ];
			}

			// Product type
			$type = $product->get_type();
			if ( ! isset( $type_counts[ $type ] ) ) {
				$type_counts[ $type ] = 0;
			}
			++$type_counts[ $type ];

			// Total inventory value
			$stock_quantity = $product->get_stock_quantity();
			if ( $stock_quantity && '' !== $price ) {
				$stats['total_value'] += floatval( $price ) * $stock_quantity;
			}
		}

		foreach ( $category_counts as $cat_id => $count ) {
			$term = get_term( $cat_id, 'product_cat' );
			if ( $term && ! is_wp_error( $term ) ) {
				$stats['categories'][] = array(
					'id'    => $cat_id,
					'name'  => $term->name,
					'count' => $count,
				);
			}
		}

		usort(
			$stats['categories'],
			function ( $a, $b ) {
				return $b['count'] - $a['count'];
			}
		);

		foreach ( $type_counts as $type => $count ) {
			$stats['types'][] = array(
				'type'  => $type,
				'label' => ucfirst( $type ),
				'count' => $count,
			);
		}

		if ( $stats['total_products'] > 0 && $total_price > 0 ) {
			$stats['average_price'] = $total_price / $stats['total_products'];
		}

		// Fix price range if no valid prices found
		if ( PHP_FLOAT_MAX === $stats['price_range']['min'] ) {
			$stats['price_range']['min'] = 0;
		}

		return $stats;
	}

	/**
	 * Build search query args.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $search       Search term.
	 * @param    array  $categories   Category IDs.
	 * @param    int    $page         Page number.
	 * @param    int    $per_page     Results per page.
	 * @return   array                  Query args.
	 */
	private function build_search_query_args(
		$search,
		$categories,
		$page,
		$per_page
	) {
		$args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'menu_order title',
			'order'          => 'ASC',
		);

		// In WooCommerce 3.0+, visibility is handled via taxonomy, not meta
		$args['tax_query'] = array();

		if ( ! empty( $search ) ) {
			$args['s'] = $search;
			$args['orderby'] = 'relevance';
		}

		if ( ! empty( $categories ) ) {
			// Ensure categories are integers
			$categories = array_map( 'intval', $categories );


			// The IN operator already provides OR logic - products in ANY of the categories
			$args['tax_query'][] = array(
				'taxonomy'         => 'product_cat',
				'field'            => 'term_id',
				'terms'            => $categories,
				'include_children' => true,
				'operator'         => 'IN',
			);
		}

		// Note: We don't exclude variable products as they might be needed

		return $args;
	}

	/**
	 * Format product for response.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    WC_Product $product    Product object.
	 * @return   array                     Formatted product data.
	 */
	private function format_product_for_response( $product ) {
		$image_id  = $product->get_image_id();
		$image_url = '';

		if ( $image_id ) {
			$image_data = wp_get_attachment_image_src( $image_id, 'thumbnail' );
			if ( $image_data ) {
				$image_url = $image_data[0];
			}
		}

		$category_ids = $product->get_category_ids();

		// Get primary category name
		$primary_category = '';
		if ( ! empty( $category_ids ) ) {
			$term = get_term( $category_ids[0], 'product_cat' );
			if ( $term && ! is_wp_error( $term ) ) {
				$primary_category = $term->name;
			}
		}

		// Get variation count for variable products
		$variation_count = 0;
		if ( 'variable' === $product->get_type() ) {
			$variation_count = count( $product->get_children() );
		}

		// Calculate discount percentage if on sale
		$discount_percent = 0;
		if ( $product->is_on_sale() ) {
			$regular = floatval( $product->get_regular_price() );
			$sale    = floatval( $product->get_sale_price() );
			if ( $regular > 0 && $sale > 0 && $sale < $regular ) {
				$discount_percent = round( ( ( $regular - $sale ) / $regular ) * 100 );
			}
		}

		return array(
			'id'               => $product->get_id(),
			'name'             => $product->get_name(),
			'price'            => $product->get_price_html(),
			'regular_price'    => $product->get_regular_price(),
			'sale_price'       => $product->get_sale_price(),
			'image'            => $image_url,
			'sku'              => $product->get_sku(),
			'stock_status'     => $product->get_stock_status(),
			'category_ids'     => $category_ids,
			'primary_category' => $primary_category,
			'type'             => $product->get_type(),
			'on_sale'          => $product->is_on_sale(),
			'variation_count'  => $variation_count,
			'discount_percent' => $discount_percent,
		);
	}

	/**
	 * Check if product matches search criteria.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    WC_Product $product       Product object.
	 * @param    string     $search        Search term.
	 * @param    array      $categories    Category IDs.
	 * @return   bool                         True if matches.
	 */
	private function product_matches_criteria(
		$product,
		$search,
		$categories
	) {
		if ( ! empty( $search ) ) {
			$searchable = strtolower( $product->get_name() . ' ' . $product->get_sku() );
			if ( strpos( $searchable, strtolower( $search ) ) === false ) {
				return false;
			}
		}

		if ( ! empty( $categories ) ) {
			$product_cats = $product->get_category_ids();
			if ( ! array_intersect( $categories, $product_cats ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Ensure WooCommerce is properly loaded in AJAX context.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @throws   Exception    If WooCommerce cannot be loaded.
	 */
	private function ensure_woocommerce_loaded() {
		try {
			if ( ! function_exists( 'wc_get_product' ) ) {
				// Try loading WooCommerce includes
				if ( defined( 'WC_ABSPATH' ) ) {
					// Load WooCommerce includes
					include_once WC_ABSPATH . 'includes/wc-core-functions.php';
					include_once WC_ABSPATH . 'includes/wc-product-functions.php';
				}

				if ( ! function_exists( 'wc_get_product' ) ) {
					// Debug logging
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					}

					throw new Exception(
						'WooCommerce is required for product operations',
						400
					);
				}
			}

			// Ensure WooCommerce is initialized in AJAX context
			if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
				// Make sure the WC() instance exists
				if ( ! function_exists( 'WC' ) || ! WC() ) {
					if ( class_exists( 'WooCommerce' ) ) {
						// Try to get the main instance
						$wc = WooCommerce::instance();
						if ( ! did_action( 'woocommerce_init' ) ) {
							// Initialize WooCommerce if needed
							do_action( 'woocommerce_init' );
						}
					}
				}

				// Ensure product factory is available
				if ( function_exists( 'WC' ) && method_exists( WC(), '__get' ) ) {
					$wc = WC();
					if ( ! isset( $wc->product_factory ) || ! $wc->product_factory ) {
						if ( class_exists( 'WC_Product_Factory' ) ) {
							$wc->product_factory = new WC_Product_Factory();
						}
					}
				}
			}
		} catch ( Exception $e ) {
			// Log the detailed error
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[SCD Product Service] Failed to ensure WooCommerce loaded: ' . $e->getMessage() );
				error_log( '[SCD Product Service] Stack trace: ' . $e->getTraceAsString() );
			}
			throw $e;
		}
	}
}
