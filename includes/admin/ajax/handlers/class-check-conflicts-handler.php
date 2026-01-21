<?php
/**
 * Check Conflicts Handler Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers/class-check-conflicts-handler.php
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
 * Check Conflicts Handler Class
 *
 * Analyzes campaign data to detect conflicts with existing active campaigns.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers
 * @author     Webstepper <contact@webstepper.io>
 */
class WSSCD_Check_Conflicts_Handler extends WSSCD_Abstract_Ajax_Handler {

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
		return 'wsscd_check_conflicts';
	}

	/**
	 * Handle the check conflicts request.
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

		$basic_data    = $state_service->get_step_data( 'basic' );
		$products_data = $state_service->get_step_data( 'products' );

		if ( empty( $basic_data ) || empty( $products_data ) ) {
			// No data to check yet
			return $this->success(
				array(
					'has_conflicts'   => false,
					'conflicts'       => array(),
					'total_conflicts' => 0,
				)
			);
		}

		$priority = isset( $basic_data['priority'] ) ? intval( $basic_data['priority'] ) : 3;

		$selection_type = isset( $products_data['product_selection_type'] ) ? $products_data['product_selection_type'] : 'all_products';
		$product_ids    = isset( $products_data['product_ids'] ) ? $products_data['product_ids'] : array();
		$category_ids   = isset( $products_data['category_ids'] ) ? $products_data['category_ids'] : array();

		// Get campaign ID if editing (to exclude from conflicts)
		$editing_campaign_id = $state_service->get( 'campaign_id', 0 );

		// Get cache manager for conflict caching
		$cache_manager = null;
		if ( class_exists( 'Smart_Cycle_Discounts' ) ) {
			$plugin    = Smart_Cycle_Discounts::get_instance();
			$container = $plugin->get_container();
			if ( $container && $container->has( 'cache_manager' ) ) {
				$cache_manager = $container->get( 'cache_manager' );
			}
		}

		// Generate cache key based on input parameters
		$cache_key_params = array(
			'priority'       => $priority,
			'selection_type' => $selection_type,
			'product_ids'    => $product_ids,
			'category_ids'   => $category_ids,
			'editing_id'     => $editing_campaign_id,
		);
		$cache_key = 'campaigns_conflicts_' . md5( wp_json_encode( $cache_key_params ) );

		// Try to get cached conflicts first
		$cached_result = null;
		if ( $cache_manager ) {
			$cached_result = $cache_manager->get( $cache_key );
		}

		if ( $cached_result && is_array( $cached_result ) ) {
			// Use cached data
			return $this->success( $cached_result );
		}

		// Cache miss - find conflicts
		$conflicts = $this->_find_conflicts( $priority, $selection_type, $product_ids, $category_ids, $editing_campaign_id );

		$result = array(
			'has_conflicts'          => ! empty( $conflicts ),
			'conflicts'              => $conflicts,
			'total_conflicts'        => count( $conflicts ),
			'total_products_blocked' => $this->_count_blocked_products( $conflicts ),
		);

		// Cache for 30 seconds (short TTL since wizard data changes frequently)
		if ( $cache_manager ) {
			$cache_manager->set( $cache_key, $result, 30 );
		}

		return $this->success( $result );
	}

	/**
	 * Find conflicting campaigns.
	 *
	 * Optimized to avoid N+1 queries by pre-calculating new campaign's products
	 * once and reusing for all comparisons.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    int    $priority              Campaign priority.
	 * @param    string $selection_type        Product selection type.
	 * @param    array  $product_ids           Selected product IDs.
	 * @param    array  $category_ids          Selected category IDs.
	 * @param    int    $editing_campaign_id   ID of campaign being edited (0 if new).
	 * @return   array                            Array of conflicts.
	 */
	private function _find_conflicts( $priority, $selection_type, $product_ids, $category_ids, $editing_campaign_id = 0 ) {
		$active_campaigns = $this->_get_active_campaigns();

		if ( empty( $active_campaigns ) ) {
			return array();
		}

		// Pre-calculate new campaign's products ONCE (eliminates N+1 query).
		$new_campaign_products = $this->_get_selection_products( $selection_type, $product_ids, $category_ids );

		// Early exit if new campaign has no products.
		if ( empty( $new_campaign_products ) ) {
			return array();
		}

		$conflicts = array();

		foreach ( $active_campaigns as $campaign ) {
			// Skip the campaign being edited (can't conflict with itself).
			if ( $editing_campaign_id && $campaign->get_id() === $editing_campaign_id ) {
				continue;
			}

			$campaign_priority = $campaign->get_priority();

			// Use shared method for consistent priority logic across handlers
			if ( ! $this->_would_block_new_campaign( $campaign_priority, $priority ) ) {
				continue;
			}

			// Pass pre-calculated products to avoid repeated queries.
			$overlap_count = $this->_count_product_overlap_optimized( $campaign, $new_campaign_products );

			if ( $overlap_count > 0 ) {
				$conflicts[] = array(
					'id'            => $campaign->get_id(),
					'name'          => $campaign->get_name(),
					'priority'      => $campaign_priority,
					'overlap_count' => $overlap_count,
				);
			}
		}

		return $conflicts;
	}

	/**
	 * Count product overlap between new campaign and existing campaign (optimized).
	 *
	 * Takes pre-calculated new campaign products to avoid repeated queries.
	 * Uses early-exit heuristics to avoid expensive product array comparisons.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    object $campaign               Existing campaign.
	 * @param    array  $new_campaign_products  Pre-calculated new campaign product IDs.
	 * @return   int                               Number of overlapping products.
	 */
	private function _count_product_overlap_optimized( $campaign, array $new_campaign_products ) {
		$existing_products = $this->_get_campaign_products( $campaign );

		if ( empty( $existing_products ) ) {
			return 0;
		}

		// Memory optimization: For very large sets, sample to estimate overlap.
		$existing_count = count( $existing_products );
		$new_count      = count( $new_campaign_products );

		// If both sets are large (> 1000), use sampling for estimation.
		if ( $existing_count > 1000 && $new_count > 1000 ) {
			return $this->_estimate_overlap_by_sampling( $existing_products, $new_campaign_products );
		}

		// Use array_flip + isset for O(n) instead of O(n*m) with array_intersect.
		$new_products_map = array_flip( $new_campaign_products );
		$overlap_count    = 0;

		foreach ( $existing_products as $product_id ) {
			if ( isset( $new_products_map[ $product_id ] ) ) {
				$overlap_count++;
			}
		}

		return $overlap_count;
	}

	/**
	 * Estimate overlap by sampling for very large product sets.
	 *
	 * Uses random sampling to estimate overlap without loading full arrays.
	 * More memory efficient for stores with thousands of products.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $set_a    First product ID set.
	 * @param    array $set_b    Second product ID set.
	 * @return   int                Estimated overlap count.
	 */
	private function _estimate_overlap_by_sampling( array $set_a, array $set_b ) {
		$sample_size = 200; // Sample 200 items for estimation.
		$set_a_count = count( $set_a );
		$set_b_count = count( $set_b );

		// Use smaller set for sampling.
		if ( $set_a_count <= $set_b_count ) {
			$sample_set  = $set_a;
			$lookup_set  = array_flip( $set_b );
			$total_count = $set_a_count;
		} else {
			$sample_set  = $set_b;
			$lookup_set  = array_flip( $set_a );
			$total_count = $set_b_count;
		}

		// Take random sample.
		$sample_keys = array_rand( $sample_set, min( $sample_size, count( $sample_set ) ) );
		if ( ! is_array( $sample_keys ) ) {
			$sample_keys = array( $sample_keys );
		}

		$matches = 0;
		foreach ( $sample_keys as $key ) {
			if ( isset( $lookup_set[ $sample_set[ $key ] ] ) ) {
				$matches++;
			}
		}

		// Extrapolate overlap estimate.
		$sample_ratio    = count( $sample_keys ) / $total_count;
		$estimated_total = intval( $matches / $sample_ratio );

		return $estimated_total;
	}

	/**
	 * Get product IDs for selection criteria.
	 *
	 * Product Selection Model:
	 * - selection_type: HOW to select (all_products, specific_products, random_products, smart_selection)
	 * - category_ids: Optional FILTER for pool-based selections
	 *
	 * Pool-based selections (all_products, random_products, smart_selection) select from
	 * all store products OR products filtered by categories when category_ids is set.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $selection_type   Selection type.
	 * @param    array  $product_ids      Product IDs (for specific_products only).
	 * @param    array  $category_ids     Category filter (for pool-based selections).
	 * @return   array                       Array of product IDs.
	 */
	private function _get_selection_products( $selection_type, $product_ids, $category_ids ) {
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

		// Unknown selection type - default to all products.
		return $this->_get_all_product_ids();
	}

	/**
	 * Count total blocked products across all conflicts.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $conflicts    Array of conflicts.
	 * @return   int                    Total blocked products.
	 */
	private function _count_blocked_products( $conflicts ) {
		$total = 0;

		foreach ( $conflicts as $conflict ) {
			$total += isset( $conflict['overlap_count'] ) ? intval( $conflict['overlap_count'] ) : 0;
		}

		return $total;
	}
}
