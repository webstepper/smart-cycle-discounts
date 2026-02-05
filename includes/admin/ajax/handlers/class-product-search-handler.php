<?php
/**
 * Product Search Handler Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers/class-product-search-handler.php
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
 * Product Search Handler Class
 *
 * Orchestrates product search requests by delegating to the Product Service.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers
 * @author     Webstepper <contact@webstepper.io>
 */
class WSSCD_Product_Search_Handler extends WSSCD_Abstract_Ajax_Handler {

	/**
	 * Product service instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Product_Service|null    $product_service    Product service.
	 */
	private $product_service = null;

	/**
	 * Initialize the handler.
	 *
	 * @since    1.0.0
	 * @param    WSSCD_Product_Service $product_service    Product service (optional).
	 * @param    WSSCD_Logger          $logger             Logger instance (optional).
	 */
	public function __construct( $product_service = null, $logger = null ) {
		parent::__construct( $logger );
		$this->product_service = $product_service;
	}

	/**
	 * Get AJAX action name.
	 *
	 * @since    1.0.0
	 * @return   string    Action name.
	 */
	protected function get_action_name() {
		return 'wsscd_product_search';
	}

	/**
	 * Handle the request.
	 *
	 * @since    1.0.0
	 * @param    array $request    Request data.
	 * @return   array                Response data.
	 * @throws   Exception            If request fails.
	 */
	protected function handle( $request ) {
		// NOTE: Product browsing/searching is FREE - allows users to explore what's available
		// License protection happens at campaign SAVE level (in save-step-handler)

		try {
			// Determine which action to perform with whitelist validation
			$action = isset( $request['wizard_action'] ) ? sanitize_text_field( $request['wizard_action'] ) : '';

			// Security: Whitelist of allowed actions
			$allowed_actions = array(
				'get_products_by_ids',
				'get_product_categories',
				'get_product_tags',
				'', // Empty string for default search action
			);

			if ( ! in_array( $action, $allowed_actions, true ) ) {
				throw new Exception(
					'Invalid action requested',
					400
				);
			}

			if ( 'get_products_by_ids' === $action ) {
				return $this->handle_get_by_ids( $request );
			}

			if ( 'get_product_categories' === $action ) {
				return $this->handle_get_categories( $request );
			}

			if ( 'get_product_tags' === $action ) {
				return $this->handle_get_tags( $request );
			}

			return $this->handle_search( $request );
		} catch ( Exception $e ) {
			// Log the error using proper logger
			$this->log_error( 'Product search error: ' . $e->getMessage() );

			return array(
				'success' => false,
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is sanitized before JSON output.
				'message' => esc_html( $e->getMessage() ),
				'code'    => 'product_search_error',
			);
		}
	}

	/**
	 * Handle product search.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $request    Request data.
	 * @return   array               Response data.
	 */
	private function handle_search( $request ) {
		$search   = sanitize_text_field( isset( $request['search'] ) ? $request['search'] : '' );
		$page     = max( 1, intval( isset( $request['page'] ) ? $request['page'] : 1 ) );
		$per_page = max( WSSCD_Validation_Rules::SEARCH_PER_PAGE_MIN, min( WSSCD_Validation_Rules::SEARCH_PER_PAGE_MAX, intval( isset( $request['per_page'] ) ? $request['per_page'] : WSSCD_Validation_Rules::SEARCH_PER_PAGE_DEFAULT ) ) );

		$categories = $this->process_categories( isset( $request['categories'] ) ? $request['categories'] : array() );

		$selected = array_map( 'intval', (array) ( isset( $request['selected'] ) ? $request['selected'] : array() ) );

		// Delegate to service
		try {
			$service = $this->get_product_service();

			if ( ! $service ) {
				throw new Exception( 'Product Service not available' );
			}

			$results = $service->search_products( $search, $categories, $page, $per_page, $selected );
		} catch ( Exception $e ) {
			throw $e;
		}

		return array(
			'products'   => $results['products'],
			'pagination' => array(
				'page'        => $results['current_page'],
				'total_pages' => $results['pages'],
				'total_items' => $results['total'],
				'per_page'    => $results['per_page'],
			),
			'debug'      => defined( 'WP_DEBUG' ) && WP_DEBUG ? array(
				'search_term' => $search,
				'categories'  => $categories,
			) : null,
		);
	}

	/**
	 * Handle get products by IDs.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $request    Request data.
	 * @return   array               Response data.
	 * @throws   Exception           If too many products requested.
	 */
	private function handle_get_by_ids( $request ) {
		$product_ids = isset( $request['product_ids'] ) ? $request['product_ids'] : array();
		if ( ! is_array( $product_ids ) ) {
			$product_ids = array( $product_ids );
		}

		// Limit number of products for security
		if ( count( $product_ids ) > 100 ) {
			throw new Exception(
				'Too many products requested. Maximum 100 allowed',
				400
			);
		}

		$product_ids = array_map( 'intval', $product_ids );
		$product_ids = array_filter( $product_ids, array( $this, 'filter_valid_product_ids' ) );

		if ( empty( $product_ids ) ) {
			return array(
				'products' => array(),
			);
		}

		// Delegate to service
		$service  = $this->get_product_service();
		$products = $service->get_products_by_ids( $product_ids );

		return array(
			'products'  => $products,
			'requested' => count( $product_ids ),
			'found'     => count( $products ),
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
		return $id > 0 && $id <= PHP_INT_MAX;
	}

	/**
	 * Process categories parameter.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    mixed $categories    Raw categories.
	 * @return   array                   Processed categories.
	 */
	private function process_categories( $categories ) {
		if ( ! is_array( $categories ) ) {
			$categories = array( $categories );
		}

		// Limit number of categories for performance
		if ( count( $categories ) > WSSCD_Validation_Rules::SEARCH_CATEGORIES_MAX ) {
			$categories = array_slice( $categories, 0, WSSCD_Validation_Rules::SEARCH_CATEGORIES_MAX );
		}

		// Handle 'all' category selection
		if ( in_array( 'all', $categories, true ) ) {
			return array();
		}

		$categories = array_map( 'intval', $categories );
		$filtered   = array_filter( $categories, array( $this, 'filter_valid_category_ids' ) );

		return $filtered;
	}

	/**
	 * Filter valid category IDs.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    int $id    Category ID to validate.
	 * @return   bool          True if valid category ID.
	 */
	private function filter_valid_category_ids( $id ) {
		return $id > 0 && $id <= PHP_INT_MAX;
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
			// WSSCD_Product_Service is registered in the autoloader (class-autoloader.php)
			// No manual require_once needed - class will be loaded automatically on first use
			if ( ! class_exists( 'WSSCD_Product_Service' ) ) {
				throw new Exception( 'Product Service class not found. Autoloader may not be initialized.' );
			}

			try {
				$this->product_service = new WSSCD_Product_Service();
			} catch ( Exception $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging intentional.
					error_log( '[WSSCD Product Search Handler] Failed to instantiate Product Service: ' . $e->getMessage() );
				}
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages are for logging/debugging, not direct output.
				throw new Exception( 'Failed to create Product Service instance: ' . esc_html( $e->getMessage() ) );
			}
		}

		return $this->product_service;
	}

	/**
	 * Handle get product categories.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $request    Request data.
	 * @return   array               Response data.
	 */
	private function handle_get_categories( $request ) {
		$search = sanitize_text_field( isset( $request['search'] ) ? $request['search'] : '' );
		$parent = max( 0, intval( isset( $request['parent'] ) ? $request['parent'] : 0 ) );
		$ids    = isset( $request['ids'] ) && is_array( $request['ids'] ) ? array_map( 'sanitize_text_field', $request['ids'] ) : array();

		$args = array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => false,
			'search'     => $search,
			'number'     => 100, // Limit results
			'orderby'    => 'menu_order',
			'order'      => 'ASC',
		);

		// If specific IDs requested, only fetch those
		if ( ! empty( $ids ) ) {
			$numeric_ids = array_filter( $ids, array( $this, 'filter_numeric_category_ids' ) );

			if ( ! empty( $numeric_ids ) ) {
				$args['include'] = array_map( 'intval', $numeric_ids );
			}
		}

		$terms      = get_terms( $args );
		$categories = array();

		if ( ! is_wp_error( $terms ) ) {
			// Add "All Categories" option if no parent and no search, OR if 'all' is specifically requested in IDs
			if ( ( 0 === $parent && empty( $search ) ) || in_array( 'all', $ids, true ) ) {
				$categories[] = array(
					'value' => 'all',
					'text'  => 'All Categories',
					'count' => 0,
					'level' => 0,
				);
			}

			// Always build hierarchical structure for categories (no search, no specific IDs)
			if ( empty( $search ) && empty( $ids ) ) {
				$categories = array_merge( $categories, $this->build_category_hierarchy( $terms ) );
			} else {
				// For search or specific IDs, return flat list
				foreach ( $terms as $term ) {
					$thumbnail_id = get_term_meta( $term->term_id, 'thumbnail_id', true );
					$image_url    = $thumbnail_id ? wp_get_attachment_image_url( $thumbnail_id, 'thumbnail' ) : '';

					$categories[] = array(
						'value'  => $term->term_id,
						'text'   => $term->name,
						'count'  => $term->count,
						'parent' => $term->parent,
						'slug'   => $term->slug,
						'level'  => 0, // Default level for non-hierarchical display
						'image'  => $image_url,
					);
				}
			}
		}

		return array(
			'categories' => $categories,
			'total'      => count( $categories ),
			'search'     => $search,
			'parent'     => $parent,
		);
	}

	/**
	 * Filter numeric category IDs (excludes 'all').
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    mixed $id    Category ID to validate.
	 * @return   bool            True if numeric and not 'all'.
	 */
	private function filter_numeric_category_ids( $id ) {
		return 'all' !== $id && is_numeric( $id );
	}

	/**
	 * Build hierarchical category structure.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $categories    Flat array of categories.
	 * @param    int   $parent_id     Parent ID to start from.
	 * @param    int   $level         Current nesting level.
	 * @return   array                   Hierarchical array of categories.
	 */
	private function build_category_hierarchy( $categories, $parent_id = 0, $level = 0 ) {
		$hierarchy = array();

		// Get category metadata service
		$metadata_service = $this->get_category_metadata_service();

		foreach ( $categories as $category ) {
			if ( $category->parent === $parent_id ) {
				$has_children      = false;
				$subcategory_count = 0;

				foreach ( $categories as $check_cat ) {
					if ( $check_cat->parent === $category->term_id ) {
						$has_children = true;
						++$subcategory_count;
					}
				}

				// Get category thumbnail (WooCommerce feature)
				$thumbnail_id = get_term_meta( $category->term_id, 'thumbnail_id', true );
				$image_url    = $thumbnail_id ? wp_get_attachment_image_url( $thumbnail_id, 'thumbnail' ) : '';

				// Get enhanced metadata (cached)
				$metadata = $metadata_service ? $metadata_service->get_category_metadata( $category->term_id ) : array();

				$hierarchy[] = array(
					'value'             => $category->term_id,
					'text'              => $category->name,
					'count'             => $category->count,
					'parent'            => $category->parent,
					'slug'              => $category->slug,
					'level'             => $level,
					'has_children'      => $has_children,
					'image'             => $image_url,
					'subcategory_count' => $subcategory_count,
					'stock_percent'     => isset( $metadata['stock_percent'] ) ? $metadata['stock_percent'] : 0,
					'on_sale_count'     => isset( $metadata['on_sale_count'] ) ? $metadata['on_sale_count'] : 0,
					'price_min'         => isset( $metadata['price_min'] ) ? $metadata['price_min'] : 0,
					'price_max'         => isset( $metadata['price_max'] ) ? $metadata['price_max'] : 0,
				);

				// Recursively get children
				$children  = $this->build_category_hierarchy( $categories, $category->term_id, $level + 1 );
				$hierarchy = array_merge( $hierarchy, $children );
			}
		}

		return $hierarchy;
	}

	/**
	 * Get category metadata service.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   WSSCD_Category_Metadata_Service|null    Service instance or null.
	 */
	private function get_category_metadata_service() {
		if ( class_exists( 'WSSCD_Category_Metadata_Service' ) ) {
			return new WSSCD_Category_Metadata_Service();
		}
		return null;
	}

	/**
	 * Handle get product tags.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $request    Request data.
	 * @return   array               Response data.
	 */
	private function handle_get_tags( $request ) {
		$search = sanitize_text_field( isset( $request['search'] ) ? $request['search'] : '' );

		$args = array(
			'taxonomy'   => 'product_tag',
			'hide_empty' => false,
			'search'     => $search,
			'number'     => 100, // Limit results
			'orderby'    => 'name',
			'order'      => 'ASC',
		);

		$terms = get_terms( $args );
		$tags  = array();

		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$tags[] = array(
					'value' => $term->term_id,
					'text'  => $term->name,
					'count' => $term->count,
					'slug'  => $term->slug,
				);
			}
		}

		return array(
			'tags'   => $tags,
			'total'  => count( $tags ),
			'search' => $search,
		);
	}
}
