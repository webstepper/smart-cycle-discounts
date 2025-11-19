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

require_once SCD_INCLUDES_DIR . 'admin/ajax/trait-wizard-helpers.php';

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
class SCD_Check_Conflicts_Handler extends SCD_Abstract_Ajax_Handler {

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
		return 'scd_check_conflicts';
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

		// Cache for 5 minutes
		if ( $cache_manager ) {
			$cache_manager->set( $cache_key, $result, 300 );
		}

		return $this->success( $result );
	}

	/**
	 * Find conflicting campaigns.
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

		$conflicts = array();

		foreach ( $active_campaigns as $campaign ) {
			// Skip the campaign being edited (can't conflict with itself)
			if ( $editing_campaign_id && $campaign->get_id() === $editing_campaign_id ) {
				continue;
			}

			$campaign_priority = $campaign->get_priority();

			// Check if new campaign would be blocked:
			// 1. Existing campaign has HIGHER priority (bigger number) → new loses
			// 2. Equal priorities → conflict (older campaign wins, but we're creating new so warn user)
			$would_lose = ( $campaign_priority >= $priority );

			if ( ! $would_lose ) {
				continue;
			}

			$overlap_count = $this->_count_product_overlap( $campaign, $selection_type, $product_ids, $category_ids );

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
	 * Count product overlap between new campaign and existing campaign.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    object $campaign         Existing campaign.
	 * @param    string $selection_type   New campaign selection type.
	 * @param    array  $product_ids      New campaign product IDs.
	 * @param    array  $category_ids     New campaign category IDs.
	 * @return   int                         Number of overlapping products.
	 */
	private function _count_product_overlap( $campaign, $selection_type, $product_ids, $category_ids ) {
		$existing_products = $this->_get_campaign_products( $campaign );

		$new_products = $this->_get_selection_products( $selection_type, $product_ids, $category_ids );

		// Find intersection
		$overlap = array_intersect( $existing_products, $new_products );

		return count( $overlap );
	}

	/**
	 * Get product IDs for selection criteria.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $selection_type   Selection type.
	 * @param    array  $product_ids      Product IDs.
	 * @param    array  $category_ids     Category IDs.
	 * @return   array                       Array of product IDs.
	 */
	private function _get_selection_products( $selection_type, $product_ids, $category_ids ) {
		// When 'all_products' or 'random_products', use category_ids to get products
		if ( 'all_products' === $selection_type || 'random_products' === $selection_type ) {
			// If categories are specified, get products from those categories
			if ( ! empty( $category_ids ) && is_array( $category_ids ) ) {
				// Filter out 'all' marker if present
				$category_ids = array_filter( $category_ids, function( $id ) {
					return 'all' !== $id;
				} );

				if ( ! empty( $category_ids ) ) {
					return $this->_get_products_in_categories( $category_ids );
				}
			}

			// No specific categories = all products in store
			return $this->_get_all_product_ids();
		} elseif ( 'specific_products' === $selection_type ) {
			return is_array( $product_ids ) ? array_map( 'intval', $product_ids ) : array();
		} elseif ( 'smart_selection' === $selection_type ) {
			// Smart selection uses category_ids as base
			if ( ! empty( $category_ids ) && is_array( $category_ids ) ) {
				$category_ids = array_filter( $category_ids, function( $id ) {
					return 'all' !== $id;
				} );

				if ( ! empty( $category_ids ) ) {
					return $this->_get_products_in_categories( $category_ids );
				}
			}

			return $this->_get_all_product_ids();
		}

		return array();
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
