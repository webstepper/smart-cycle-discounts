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
trait SCD_Wizard_Helpers {

	/**
	 * Get wizard state service instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   SCD_Wizard_State_Service|null    State service instance or null on failure.
	 */
	private function _get_state_service() {
		if ( ! class_exists( 'SCD_Wizard_State_Service' ) ) {
			require_once SCD_INCLUDES_DIR . 'core/wizard/class-wizard-state-service.php';
		}

		try {
			$state_service = new SCD_Wizard_State_Service();
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

		// Filter out 'all' marker and non-numeric IDs
		$category_ids = array_filter(
			array_map( 'intval', $category_ids ),
			function( $id ) {
				return 0 < $id;
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
			// Use WooCommerce API - respects visibility rules, HPOS compatible
			while ( true ) {
				$batch = wc_get_products(
					array(
						'limit'    => $per_page,
						'page'     => $page,
						'status'   => 'publish',
						'category' => $category_ids,
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
		} else {
			// Fallback using get_posts with chunking
			while ( true ) {
				$args = array(
					'post_type'      => 'product',
					'posts_per_page' => $per_page,
					'post_status'    => 'publish',
					'fields'         => 'ids',
					'paged'          => $page,
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
	 * @since    1.0.0
	 * @access   private
	 * @param    array $products_data    Products data from wizard.
	 * @return   array                      Array of product IDs.
	 */
	private function _get_product_ids( $products_data ) {
		if ( empty( $products_data ) || ! is_array( $products_data ) ) {
			return array();
		}

		// Handle different data formats
		if ( isset( $products_data['product_selection_type'] ) ) {
			$selection_type = $products_data['product_selection_type'];

			if ( 'all_products' === $selection_type ) {
				return $this->_get_all_product_ids();
			}

			if ( 'specific_categories' === $selection_type && ! empty( $products_data['category_ids'] ) ) {
				return $this->_get_products_in_categories( $products_data['category_ids'] );
			}

			if ( 'specific_products' === $selection_type && ! empty( $products_data['product_ids'] ) ) {
				return is_array( $products_data['product_ids'] ) ? array_map( 'intval', $products_data['product_ids'] ) : array();
			}
		}

		// Fallback: assume it's an array of product IDs
		return array_map( 'intval', $products_data );
	}

	/**
	 * Get campaign products based on campaign data.
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

		// Handle campaign object
		if ( is_object( $campaign ) && method_exists( $campaign, 'get_product_ids' ) ) {
			return $campaign->get_product_ids();
		}

		// Handle campaign array
		if ( is_array( $campaign ) ) {
			$selection_type = isset( $campaign['product_selection_type'] ) ? $campaign['product_selection_type'] : '';

			if ( 'all_products' === $selection_type ) {
				return $this->_get_all_product_ids();
			}

			if ( 'specific_categories' === $selection_type && ! empty( $campaign['category_ids'] ) ) {
				return $this->_get_products_in_categories( $campaign['category_ids'] );
			}

			if ( 'specific_products' === $selection_type && ! empty( $campaign['product_ids'] ) ) {
				return is_array( $campaign['product_ids'] ) ? array_map( 'intval', $campaign['product_ids'] ) : array();
			}
		}

		return array();
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

			default:
				return $price;
		}
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
