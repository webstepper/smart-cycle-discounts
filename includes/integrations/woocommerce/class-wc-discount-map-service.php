<?php
/**
 * WooCommerce Discount Map Service
 *
 * Provides in-memory caching of product-to-campaign lookups for shop/archive pages.
 * Prevents repeated database queries by caching results for the request duration.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/integrations/woocommerce
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
 * WooCommerce Discount Map Service
 *
 * Provides in-memory caching layer for product-to-campaign lookups.
 * Prevents repeated calls to Campaign Manager by caching results per request.
 * Critical for performance on shop/archive pages with many products.
 *
 * @since      1.0.0
 */
class SCD_WC_Discount_Map_Service {

	/**
	 * Campaign manager instance.
	 *
	 * @since    1.0.0
	 * @var      SCD_Campaign_Manager
	 */
	private SCD_Campaign_Manager $campaign_manager;

	/**
	 * Cache manager instance.
	 *
	 * @since    1.0.0
	 * @var      SCD_Cache_Manager|null
	 */
	private ?object $cache;

	/**
	 * Logger instance.
	 *
	 * @since    1.0.0
	 * @var      object|null
	 */
	private ?object $logger;

	/**
	 * Active campaigns loaded for current request.
	 *
	 * @since    1.0.0
	 * @var      array|null
	 */
	private $active_campaigns = null;

	/**
	 * Product to campaigns map.
	 * Format: array( product_id => array of campaign objects )
	 *
	 * @since    1.0.0
	 * @var      array
	 */
	private $product_campaign_map = array();

	/**
	 * Whether the map has been built for current request.
	 *
	 * @since    1.0.0
	 * @var      bool
	 */
	private $map_initialized = false;

	/**
	 * Initialize the service.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign_Manager $campaign_manager    Campaign manager instance.
	 * @param    object|null          $cache               Cache manager instance.
	 * @param    object|null          $logger              Logger instance (optional).
	 */
	public function __construct( SCD_Campaign_Manager $campaign_manager, ?object $cache = null, ?object $logger = null ) {
		$this->campaign_manager = $campaign_manager;
		$this->cache            = $cache;
		$this->logger           = $logger;
	}

	/**
	 * Initialize the caching service for current page request.
	 *
	 * Prepares the service for lookups by loading active campaigns.
	 * Should be called early in request lifecycle (e.g., template_redirect).
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function init_map(): void {
		// Only initialize once per request
		if ( $this->map_initialized ) {
			return;
		}

		// Load all active campaigns (cached)
		$this->active_campaigns = $this->campaign_manager->get_active_campaigns();
		$this->map_initialized  = true;
	}

	/**
	 * Get applicable campaigns for a product.
	 *
	 * Uses in-memory cache to avoid repeated lookups during the same request.
	 * The optimization comes from caching results, not from pre-loading campaigns.
	 *
	 * @since    1.0.0
	 * @param    int $product_id    Product ID.
	 * @return   array                 Array of applicable campaign objects.
	 */
	public function get_campaigns_for_product( int $product_id ): array {
		// Check if we've already looked up this product
		if ( isset( $this->product_campaign_map[ $product_id ] ) ) {
			return $this->product_campaign_map[ $product_id ];
		}

		// Use Campaign Manager's public API to get applicable campaigns
		$applicable_campaigns = $this->campaign_manager->get_active_campaigns_for_product( $product_id );

		// Cache the result in memory for this request
		$this->product_campaign_map[ $product_id ] = $applicable_campaigns;

		return $applicable_campaigns;
	}

	/**
	 * Check if a product has any active discounts.
	 *
	 * Efficient check without loading full campaign details.
	 *
	 * @since    1.0.0
	 * @param    int $product_id    Product ID.
	 * @return   bool                  True if product has active discounts.
	 */
	public function has_discount( int $product_id ): bool {
		$campaigns = $this->get_campaigns_for_product( $product_id );
		return ! empty( $campaigns );
	}

	/**
	 * Warm up the map for multiple products.
	 *
	 * Pre-loads campaign mappings for a batch of products.
	 * Useful for shop/archive pages to avoid per-product overhead.
	 *
	 * @since    1.0.0
	 * @param    array $product_ids    Array of product IDs.
	 * @return   void
	 */
	public function warm_map( array $product_ids ): void {
		if ( empty( $product_ids ) || ! is_array( $product_ids ) ) {
			return;
		}

		// Ensure map is initialized
		if ( ! $this->map_initialized ) {
			$this->init_map();
		}

		// Pre-load mappings for all products
		foreach ( $product_ids as $product_id ) {
			// Skip if already mapped
			if ( isset( $this->product_campaign_map[ $product_id ] ) ) {
				continue;
			}

			// Trigger mapping
			$this->get_campaigns_for_product( $product_id );
		}
	}

	/**
	 * Reset the map.
	 *
	 * Clears in-memory cache. Should be called if campaigns are modified during request.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function reset_map() {
		$this->active_campaigns     = null;
		$this->product_campaign_map = array();
		$this->map_initialized      = false;
	}

	/**
	 * Check if map is initialized.
	 *
	 * @since    1.0.0
	 * @return   bool    True if map is initialized.
	 */
	public function is_initialized(): bool {
		return $this->map_initialized;
	}

	/**
	 * Get all active campaigns loaded for current request.
	 *
	 * @since    1.0.0
	 * @return   array    Array of campaign objects.
	 */
	public function get_active_campaigns(): array {
		if ( ! $this->map_initialized ) {
			$this->init_map();
		}

		return $this->active_campaigns ?? array();
	}
}
