<?php
/**
 * Wizard Helpers Trait
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/trait-wizard-helpers.php
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
 * Wizard Helpers Trait
 *
 * @since      1.0.0
 */
trait WSSCD_Wizard_Helpers {

	/**
	 * Get wizard state service instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   WSSCD_Wizard_State_Service|null    State service instance or null on failure.
	 */
	private function _get_state_service() {
		if ( ! class_exists( 'WSSCD_Wizard_State_Service' ) ) {
			require_once WSSCD_INCLUDES_DIR . 'core/wizard/class-wizard-state-service.php';
		}

		try {
			$state_service = new WSSCD_Wizard_State_Service();
			return $state_service;
		} catch ( Exception $e ) {
			return null;
		}
	}

	/**
	 * Get all product IDs using chunked/batched loading.
	 *
	 * Loads products in batches to avoid memory exhaustion with large catalogs.
	 * Uses WooCommerce API with caching for optimal performance and compatibility.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   array    Array of product IDs.
	 */
	private function _get_all_product_ids() {
		// Get cache manager
		$cache_manager = null;
		if ( class_exists( 'Smart_Cycle_Discounts' ) ) {
			$plugin    = Smart_Cycle_Discounts::get_instance();
			$container = $plugin->get_container();
			if ( $container && $container->has( 'cache_manager' ) ) {
				$cache_manager = $container->get( 'cache_manager' );
			}
		}

		// Try cache first (15 minute cache for product IDs)
		$cache_key = 'products_all_ids';
		if ( $cache_manager ) {
			$cached = $cache_manager->get( $cache_key );
			if ( false !== $cached && is_array( $cached ) ) {
				return $cached;
			}
		}

		// Use chunked loading to avoid memory exhaustion
		$all_product_ids = array();
		$page            = 1;
		$per_page        = 100; // Process 100 products at a time

		if ( function_exists( 'wc_get_products' ) ) {
			// Use WooCommerce API - respects visibility rules, HPOS compatible
			while ( true ) {
				$batch = wc_get_products(
					array(
						'limit'  => $per_page,
						'page'   => $page,
						'status' => 'publish',
						'return' => 'ids',
					)
				);

				if ( empty( $batch ) ) {
					break; // No more products
				}

				$all_product_ids = array_merge( $all_product_ids, $batch );
				$page++;

				// Safety: Prevent infinite loops (max 10,000 products)
				if ( $page > 100 ) {
					break;
				}
			}
		} else {
			// Fallback using get_posts with chunking
			while ( true ) {
				$args = array(
					'post_type'      => 'product',
					'posts_per_page' => $per_page,
					'post_status'    => 'publish',
					'fields'         => 'ids',
					'paged'          => $page,
				);

				$batch = get_posts( $args );

				if ( empty( $batch ) ) {
					break;
				}

				$all_product_ids = array_merge( $all_product_ids, $batch );
				$page++;

				// Safety: Prevent infinite loops
				if ( $page > 100 ) {
					break;
				}
			}
		}

		// Cache for 15 minutes
		if ( $cache_manager ) {
			$cache_manager->set( $cache_key, $all_product_ids, 900 );
		}

		return $all_product_ids;
	}

	/**
	 * Get total published product count.
	 *
	 * Optimized method for getting product count without loading all IDs.
	 * Much more memory efficient than loading and counting all products.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   int    Total number of published products.
	 */
	private function _get_total_product_count() {
		// Get cache manager
		$cache_manager = null;
		if ( class_exists( 'Smart_Cycle_Discounts' ) ) {
			$plugin    = Smart_Cycle_Discounts::get_instance();
			$container = $plugin->get_container();
			if ( $container && $container->has( 'cache_manager' ) ) {
				$cache_manager = $container->get( 'cache_manager' );
			}
		}

		// Try cache first
		$cache_key = 'products_total_count';
		if ( $cache_manager ) {
			$cached = $cache_manager->get( $cache_key );
			if ( false !== $cached ) {
				return intval( $cached );
			}
		}

		// Use wp_count_posts() - very efficient, just queries post count
		$counts = wp_count_posts( 'product' );
		$total  = isset( $counts->publish ) ? $counts->publish : 0;

		// Cache for 15 minutes
		if ( $cache_manager ) {
			$cache_manager->set( $cache_key, $total, 900 );
		}

		return intval( $total );
	}

	/**
	 * Get products in categories using chunked/batched loading.
	 *
	 * Loads products in batches to avoid memory exhaustion with large catalogs.
	 * Uses WooCommerce API with caching for optimal performance and compatibility.
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

		// Convert to integers and filter out invalid IDs.
		$category_ids = array_filter(
			array_map( 'intval', $category_ids ),
			function( $id ) {
				return $id > 0;
			}
		);

		if ( empty( $category_ids ) ) {
			return array();
		}

		// Get cache manager
		$cache_manager = null;
		if ( class_exists( 'Smart_Cycle_Discounts' ) ) {
			$plugin    = Smart_Cycle_Discounts::get_instance();
			$container = $plugin->get_container();
			if ( $container && $container->has( 'cache_manager' ) ) {
				$cache_manager = $container->get( 'cache_manager' );
			}
		}

		// Generate cache key from category IDs
		sort( $category_ids ); // Normalize order for consistent cache key
		$cache_key = 'products_in_cats_' . md5( wp_json_encode( $category_ids ) );

		// Try cache first (15 minute cache)
		if ( $cache_manager ) {
			$cached = $cache_manager->get( $cache_key );
			if ( false !== $cached && is_array( $cached ) ) {
				return $cached;
			}
		}

		// Use chunked loading to avoid memory exhaustion
		$all_product_ids = array();
		$page            = 1;
		$per_page        = 100; // Process 100 products at a time

		if ( function_exists( 'wc_get_products' ) ) {
			// Convert category IDs to slugs (wc_get_products 'category' param expects slugs, not IDs)
			$terms = get_terms(
				array(
					'taxonomy'   => 'product_cat',
					'include'    => $category_ids,
					'hide_empty' => false,
					'fields'     => 'id=>slug',
				)
			);

			$category_slugs = array();
			if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
				$category_slugs = array_values( $terms );
			}

			// If valid slugs found, use WooCommerce API
			if ( ! empty( $category_slugs ) ) {
				while ( true ) {
					$batch = wc_get_products(
						array(
							'limit'    => $per_page,
							'page'     => $page,
							'status'   => 'publish',
							'category' => $category_slugs,
							'return'   => 'ids',
						)
					);

					if ( empty( $batch ) ) {
						break; // No more products
					}

					$all_product_ids = array_merge( $all_product_ids, $batch );
					$page++;

					// Safety: Prevent infinite loops (max 10,000 products)
					if ( $page > 100 ) {
						break;
					}
				}
			}
		}

		// Fallback: use get_posts with tax_query if WC API unavailable or no slugs found
		if ( empty( $all_product_ids ) ) {
			$page = 1; // Reset pagination for fallback
			// Fallback using get_posts with chunking
			while ( true ) {
				$args = array(
					'post_type'      => 'product',
					'posts_per_page' => $per_page,
					'post_status'    => 'publish',
					'fields'         => 'ids',
					'paged'          => $page,
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Required for category-based product filtering.
					'tax_query'      => array(
						array(
							'taxonomy' => 'product_cat',
							'field'    => 'term_id',
							'terms'    => $category_ids,
						),
					),
				);

				$batch = get_posts( $args );

				if ( empty( $batch ) ) {
					break;
				}

				$all_product_ids = array_merge( $all_product_ids, $batch );
				$page++;

				// Safety: Prevent infinite loops
				if ( $page > 100 ) {
					break;
				}
			}
		}

		// Cache for 15 minutes
		if ( $cache_manager ) {
			$cache_manager->set( $cache_key, $all_product_ids, 900 );
		}

		return $all_product_ids;
	}

	/**
	 * Get product IDs from wizard data.
	 *
	 * Product Selection Model:
	 * - selection_type: HOW to select (all_products, specific_products, random_products, smart_selection)
	 * - category_ids: Optional FILTER for pool-based selections
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $products_data    Products data from wizard.
	 * @return   array                      Array of product IDs.
	 */
	private function _get_product_ids( $products_data ) {
		if ( empty( $products_data ) || ! is_array( $products_data ) ) {
			return array();
		}

		if ( ! isset( $products_data['product_selection_type'] ) ) {
			// Fallback: assume it's an array of product IDs.
			return array_map( 'intval', $products_data );
		}

		$selection_type = $products_data['product_selection_type'];
		$category_ids   = isset( $products_data['category_ids'] ) ? $products_data['category_ids'] : array();
		$product_ids    = isset( $products_data['product_ids'] ) ? $products_data['product_ids'] : array();

		// Specific products - use explicit product IDs, ignore category filter.
		if ( WSSCD_Campaign::SELECTION_TYPE_SPECIFIC_PRODUCTS === $selection_type ) {
			return is_array( $product_ids ) ? array_map( 'intval', $product_ids ) : array();
		}

		// Pool-based selections - apply category filter if set.
		if ( WSSCD_Campaign::is_pool_based_selection( $selection_type ) ) {
			if ( ! empty( $category_ids ) && is_array( $category_ids ) ) {
				return $this->_get_products_in_categories( array_map( 'intval', $category_ids ) );
			}
			return $this->_get_all_product_ids();
		}

		// Unknown type - default to all products.
		return $this->_get_all_product_ids();
	}

	/**
	 * Get campaign products based on campaign data.
	 *
	 * Resolves product IDs for a campaign based on its selection type.
	 * Pool-based selections dynamically resolve products since they're not stored.
	 *
	 * Product Selection Model:
	 * - selection_type: HOW to select products
	 *   - all_products: All products in store
	 *   - specific_products: Manually selected product IDs
	 *   - random_products: Random subset from pool
	 *   - smart_selection: Algorithm-selected from pool
	 * - category_ids: Optional FILTER for pool-based selections
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    object|array $campaign    Campaign object or array.
	 * @return   array                        Array of product IDs.
	 */
	private function _get_campaign_products( $campaign ) {
		if ( empty( $campaign ) ) {
			return array();
		}

		// Handle campaign object.
		if ( is_object( $campaign ) ) {
			return $this->_get_campaign_products_from_object( $campaign );
		}

		// Handle campaign array.
		if ( is_array( $campaign ) ) {
			return $this->_get_campaign_products_from_array( $campaign );
		}

		return array();
	}

	/**
	 * Get campaign products from campaign object.
	 *
	 * Product Selection Model:
	 * - selection_type: HOW to select (all_products, specific_products, random_products, smart_selection)
	 * - category_ids: Optional FILTER for pool-based selections
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    object $campaign    Campaign object.
	 * @return   array                  Array of product IDs.
	 */
	private function _get_campaign_products_from_object( $campaign ) {
		$selection_type = method_exists( $campaign, 'get_product_selection_type' )
			? $campaign->get_product_selection_type()
			: WSSCD_Campaign::SELECTION_TYPE_ALL_PRODUCTS;

		// Specific products - return stored product IDs, ignore category filter.
		if ( WSSCD_Campaign::SELECTION_TYPE_SPECIFIC_PRODUCTS === $selection_type ) {
			return method_exists( $campaign, 'get_product_ids' )
				? $campaign->get_product_ids()
				: array();
		}

		// Pool-based selections - apply category filter if set.
		if ( WSSCD_Campaign::is_pool_based_selection( $selection_type ) ) {
			$category_ids = method_exists( $campaign, 'get_category_ids' )
				? $campaign->get_category_ids()
				: array();

			if ( ! empty( $category_ids ) ) {
				return $this->_get_products_in_categories( $category_ids );
			}
			return $this->_get_all_product_ids();
		}

		// Unknown type - default to all products.
		return $this->_get_all_product_ids();
	}

	/**
	 * Get campaign products from campaign array.
	 *
	 * Product Selection Model:
	 * - selection_type: HOW to select (all_products, specific_products, random_products, smart_selection)
	 * - category_ids: Optional FILTER for pool-based selections
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $campaign    Campaign data array.
	 * @return   array                 Array of product IDs.
	 */
	private function _get_campaign_products_from_array( array $campaign ) {
		$selection_type = isset( $campaign['product_selection_type'] )
			? $campaign['product_selection_type']
			: WSSCD_Campaign::SELECTION_TYPE_ALL_PRODUCTS;

		$category_ids = isset( $campaign['category_ids'] ) ? $campaign['category_ids'] : array();
		$product_ids  = isset( $campaign['product_ids'] ) ? $campaign['product_ids'] : array();

		// Specific products - use explicit product IDs, ignore category filter.
		if ( WSSCD_Campaign::SELECTION_TYPE_SPECIFIC_PRODUCTS === $selection_type ) {
			return is_array( $product_ids ) ? array_map( 'intval', $product_ids ) : array();
		}

		// Pool-based selections - apply category filter if set.
		if ( WSSCD_Campaign::is_pool_based_selection( $selection_type ) ) {
			if ( ! empty( $category_ids ) && is_array( $category_ids ) ) {
				return $this->_get_products_in_categories( array_map( 'intval', $category_ids ) );
			}
			return $this->_get_all_product_ids();
		}

		// Unknown type - default to all products.
		return $this->_get_all_product_ids();
	}

	/**
	 * Get active campaigns from repository.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   array    Array of campaign objects.
	 */
	private function _get_active_campaigns() {
		if ( ! class_exists( 'Smart_Cycle_Discounts' ) ) {
			return array();
		}

		$campaign_repository = Smart_Cycle_Discounts::get_service( 'campaign_repository' );
		if ( ! $campaign_repository ) {
			return array();
		}

		$campaigns = $campaign_repository->get_active_campaigns();

		return is_array( $campaigns ) ? $campaigns : array();
	}

	/**
	 * Apply discount to price.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    float  $price             Original price.
	 * @param    string $discount_type     Discount type (percentage or fixed).
	 * @param    float  $discount_value    Discount value.
	 * @return   float                        Discounted price.
	 */
	private function _apply_discount( $price, $discount_type, $discount_value ) {
		switch ( $discount_type ) {
			case 'percentage':
				$discount_amount = ( $price * $discount_value ) / 100;
				return max( 0, $price - $discount_amount );

			case 'fixed':
				return max( 0, $price - $discount_value );

			case 'spend_threshold':
				// Threshold discounts depend on cart total, not individual product price.
				return $price;

			default:
				return $price;
		}
	}

	/**
	 * Check if an existing campaign would block a new campaign.
	 *
	 * Priority Logic:
	 * - Higher number = higher priority (e.g., 5 beats 3)
	 * - Equal priority = older campaign wins (existing blocks new)
	 * - New campaign is blocked if existing has higher OR equal priority
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    int $existing_priority    Priority of existing campaign.
	 * @param    int $new_priority         Priority of new campaign being created.
	 * @return   bool                         True if existing campaign would block new one.
	 */
	private function _would_block_new_campaign( $existing_priority, $new_priority ) {
		return intval( $existing_priority ) >= intval( $new_priority );
	}

	/**
	 * Get wizard step data with validation.
	 *
	 * Retrieves state service, gets products and discounts data, and validates
	 * that discount configuration exists. Returns error array or data array.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $no_discount_message    Message to show if no discount configured.
	 * @return   array|WP_Error                   Error array or data array with state_service, products_data, discounts_data, product_ids.
	 */
	private function _get_validated_wizard_state( $no_discount_message = '' ) {
		$state_service = $this->_get_state_service();
		if ( ! $state_service ) {
			return new WP_Error(
				'state_service_unavailable',
				__( 'Could not load wizard data', 'smart-cycle-discounts' )
			);
		}

		$products_data  = $state_service->get_step_data( 'products' );
		$discounts_data = $state_service->get_step_data( 'discounts' );

		if ( empty( $discounts_data ) || empty( $discounts_data['discount_type'] ) ) {
			// No discount configured yet
			if ( empty( $no_discount_message ) ) {
				$no_discount_message = __( 'Configure discount first', 'smart-cycle-discounts' );
			}
			return new WP_Error( 'no_discount_configured', $no_discount_message );
		}

		$product_ids = $this->_get_product_ids( $products_data );

		return array(
			'state_service'  => $state_service,
			'products_data'  => $products_data,
			'discounts_data' => $discounts_data,
			'product_ids'    => $product_ids,
		);
	}
}
