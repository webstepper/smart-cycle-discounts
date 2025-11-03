<?php
/**
 * Wc Discount Query Service Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/integrations/woocommerce/class-wc-discount-query-service.php
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
 * WooCommerce Discount Query Service class.
 *
 * Responsible for:
 * - Looking up active campaigns for products
 * - Selecting winning campaign when multiple apply
 * - Building discount configurations
 * - Calculating discounted prices
 * - Managing request-level cache
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/integrations/woocommerce
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_WC_Discount_Query_Service {

	/**
	 * Campaign manager instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Campaign_Manager    $campaign_manager    Campaign manager.
	 */
	private SCD_Campaign_Manager $campaign_manager;

	/**
	 * Discount engine instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Discount_Engine    $discount_engine    Discount engine.
	 */
	private SCD_Discount_Engine $discount_engine;

	/**
	 * Logger instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      object|null    $logger    Logger instance.
	 */
	private ?object $logger;

	/**
	 * Request-level cache for discount lookups.
	 *
	 * Prevents redundant database queries and calculations within a single request.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $cache    Cached discount information.
	 */
	private array $cache = array();

	/**
	 * Initialize the discount query service.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign_Manager $campaign_manager    Campaign manager instance.
	 * @param    SCD_Discount_Engine  $discount_engine     Discount engine instance.
	 * @param    object|null          $logger              Logger instance.
	 */
	public function __construct(
		SCD_Campaign_Manager $campaign_manager,
		SCD_Discount_Engine $discount_engine,
		?object $logger = null
	) {
		$this->campaign_manager = $campaign_manager;
		$this->discount_engine  = $discount_engine;
		$this->logger           = $logger;
	}

	/**
	 * Check if product has active discount.
	 *
	 * Uses request-level caching to avoid redundant lookups.
	 *
	 * @since    1.0.0
	 * @param    int $product_id    Product ID.
	 * @return   bool                  True if has active discount.
	 */
	public function has_active_discount( int $product_id ): bool {
		$cache_key = 'has_discount_' . $product_id;

		if ( isset( $this->cache[ $cache_key ] ) ) {
			return $this->cache[ $cache_key ];
		}

		$result                    = null !== $this->get_discount_info( $product_id );
		$this->cache[ $cache_key ] = $result;

		return $result;
	}

	/**
	 * Get discount information for a product.
	 *
	 * Orchestrates the entire discount lookup process:
	 * 1. Get product
	 * 2. Find applicable campaigns
	 * 3. Select winning campaign (priority + conflict detection)
	 * 4. Build discount configuration
	 * 5. Calculate discount
	 * 6. Return formatted data
	 *
	 * @since    1.0.0
	 * @param    int   $product_id    Product ID.
	 * @param    array $context       Context data (quantity, cart_item, etc).
	 * @return   array|null               Discount info or null if no discount.
	 */
	public function get_discount_info( int $product_id, array $context = array() ): ?array {
		$cache_key = 'discount_info_' . $product_id . '_' . md5( serialize( $context ) );

		if ( isset( $this->cache[ $cache_key ] ) ) {
			return $this->cache[ $cache_key ];
		}

		try {
			// Step 1: Get product
			$product = $this->get_product( $product_id );
			if ( ! $product ) {
				return null;
			}

			// Step 2: Get applicable campaigns
			$campaigns = $this->get_applicable_campaigns( $product );
			if ( empty( $campaigns ) ) {
				return null;
			}

			// Step 3: Select winning campaign (with conflict detection)
			$campaign = $this->select_winning_campaign( $campaigns, $product_id );

			// Step 4: Build discount configuration
			$discount_config = $this->build_discount_config( $campaign );

			// Step 5: Calculate discount
			$original_price   = floatval( $product->get_regular_price() );
			$discount_context = $this->build_discount_context( $product, $product_id, $context );

			$result = $this->calculate_discount( $original_price, $discount_config, $discount_context, $campaign );
			if ( ! $result ) {
				return null;
			}

			// Step 6: Build response data
			$discount_data = $this->build_discount_data( $discount_config, $campaign, $result );

			$this->cache[ $cache_key ] = $discount_data;

			return $discount_data;

		} catch ( Exception $e ) {
			$this->log(
				'error',
				'Failed to get discount info',
				array(
					'product_id' => $product_id,
					'error'      => $e->getMessage(),
					'trace'      => $e->getTraceAsString(),
				)
			);
			return null;
		}
	}

	/**
	 * Get product object.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    int $product_id    Product ID.
	 * @return   WC_Product|null       Product object or null.
	 */
	private function get_product( int $product_id ): ?WC_Product {
		$product = wc_get_product( $product_id );

		if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
			return null;
		}

		return $product;
	}

	/**
	 * Get applicable campaigns for product.
	 *
	 * For variations, checks both variation and parent product for campaigns.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    WC_Product $product    Product object.
	 * @return   array                     Array of campaign objects.
	 */
	private function get_applicable_campaigns( WC_Product $product ): array {
		$product_id           = $product->get_id();
		$product_ids_to_check = array( $product_id );

		// For variations, check parent product too
		if ( $product->is_type( 'variation' ) ) {
			$parent_id = $product->get_parent_id();
			if ( $parent_id ) {
				$product_ids_to_check[] = $parent_id;
			}
		}

		$campaigns = array();

		// Gather campaigns from all checked product IDs
		foreach ( $product_ids_to_check as $check_id ) {
			$product_campaigns = $this->campaign_manager->get_active_campaigns_for_product( $check_id );
			if ( ! empty( $product_campaigns ) ) {
				$campaigns = array_merge( $campaigns, $product_campaigns );
			}
		}

		return $campaigns;
	}

	/**
	 * Select winning campaign from multiple applicable campaigns.
	 *
	 * Priority Resolution:
	 * 1. Highest priority value wins (5 beats 3, 3 beats 1)
	 * 2. If same priority, oldest campaign wins (lower ID = created first)
	 * 3. Logs warning if multiple campaigns have same priority
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $campaigns     Array of campaign objects.
	 * @param    int   $product_id    Product ID (for logging).
	 * @return   SCD_Campaign             Winning campaign.
	 */
	private function select_winning_campaign( array $campaigns, int $product_id ): SCD_Campaign {
		usort(
			$campaigns,
			function ( $a, $b ) {
				// First compare priority (higher priority wins)
				$priority_diff = $b->get_priority() <=> $a->get_priority();
				if ( 0 !== $priority_diff ) {
					return $priority_diff;
				}

				// If same priority, older campaign wins (lower ID = created first)
				return $a->get_id() <=> $b->get_id();
			}
		);

		// Detect and log priority conflicts
		if ( count( $campaigns ) > 1 ) {
			$this->detect_priority_conflicts( $campaigns, $product_id );
		}

		return reset( $campaigns );
	}

	/**
	 * Detect and log campaign priority conflicts.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $campaigns     Sorted campaigns array.
	 * @param    int   $product_id    Product ID.
	 * @return   void
	 */
	private function detect_priority_conflicts( array $campaigns, int $product_id ): void {
		$highest_priority = $campaigns[0]->get_priority();

		$same_priority = array_filter(
			$campaigns,
			function ( $c ) use ( $highest_priority ) {
				return $c->get_priority() === $highest_priority;
			}
		);

		if ( count( $same_priority ) > 1 ) {
			$this->log(
				'warning',
				'Multiple campaigns with same priority for product',
				array(
					'product_id'        => $product_id,
					'priority'          => $highest_priority,
					'campaigns'         => array_map(
						function ( $c ) {
							return array(
								'id'       => $c->get_id(),
								'name'     => $c->get_name(),
								'priority' => $c->get_priority(),
							);
						},
						$same_priority
					),
					'selected_campaign' => array(
						'id'   => $campaigns[0]->get_id(),
						'name' => $campaigns[0]->get_name(),
					),
				)
			);
		}
	}

	/**
	 * Build discount configuration from campaign.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    SCD_Campaign $campaign    Campaign object.
	 * @return   array                        Discount configuration array.
	 */
	private function build_discount_config( SCD_Campaign $campaign ): array {
		$discount_type   = $campaign->get_discount_type();
		$discount_config = array(
			'type'  => $discount_type,
			'value' => $campaign->get_discount_value(),
		);

		// For complex discount types, include full discount_rules
		if ( in_array( $discount_type, array( 'tiered', 'bogo', 'spend_threshold' ), true ) ) {
			$discount_rules = $campaign->get_discount_rules();
			if ( ! empty( $discount_rules ) ) {
				$discount_config = array_merge( $discount_config, $discount_rules );
			}
		}

		// Map value to percentage for percentage strategy
		if ( 'percentage' === $discount_config['type'] && isset( $discount_config['value'] ) ) {
			$discount_config['percentage'] = $discount_config['value'];
		}

		// Map value to amount for fixed strategy
		if ( 'fixed' === $discount_config['type'] && isset( $discount_config['value'] ) ) {
			$discount_config['amount'] = $discount_config['value'];
		}

		return $discount_config;
	}

	/**
	 * Build discount context from product and request context.
	 *
	 * CRITICAL: For variations, always use the variation's price, not the parent's.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    WC_Product $product       Product object.
	 * @param    int        $product_id    Product ID.
	 * @param    array      $context       Request context (quantity, cart_item, etc).
	 * @return   array                        Discount context array.
	 */
	private function build_discount_context( WC_Product $product, int $product_id, array $context ): array {
		$discount_context = array_merge(
			array(
				'product'      => $product,
				'product_id'   => $product_id,
				'product_type' => $product->get_type(),
				'quantity'     => 1, // Default to 1 for product pages
			),
			$context // Override with passed context (e.g., cart quantity)
		);

		return $discount_context;
	}

	/**
	 * Calculate discount using discount engine.
	 *
	 * Wraps discount engine call in try-catch to prevent crashes.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    float        $original_price      Original price.
	 * @param    array        $discount_config     Discount configuration.
	 * @param    array        $discount_context    Discount context.
	 * @param    SCD_Campaign $campaign            Campaign object (for logging).
	 * @return   object|null                           Discount result or null on error.
	 */
	private function calculate_discount(
		float $original_price,
		array $discount_config,
		array $discount_context,
		SCD_Campaign $campaign
	): ?object {
		try {
			$result = $this->discount_engine->calculate_discount(
				$original_price,
				$discount_config,
				$discount_context
			);

			if ( ! $result || ! method_exists( $result, 'is_applied' ) || ! $result->is_applied() ) {
				return null;
			}

			return $result;

		} catch ( Exception $e ) {
			$this->log(
				'error',
				'Discount calculation exception',
				array(
					'campaign_id'   => $campaign->get_id(),
					'discount_type' => $discount_config['type'] ?? 'unknown',
					'error'         => $e->getMessage(),
					'trace'         => $e->getTraceAsString(),
				)
			);

			return null;
		}
	}

	/**
	 * Build discount data array from calculation result.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array        $discount_config    Discount configuration.
	 * @param    SCD_Campaign $campaign           Campaign object.
	 * @param    object       $result             Discount calculation result.
	 * @return   array                               Formatted discount data.
	 */
	private function build_discount_data( array $discount_config, SCD_Campaign $campaign, object $result ): array {
		$discount_data = array(
			'type'             => $discount_config['type'] ?? 'percentage',
			'value'            => $discount_config['value'] ?? 0,
			'campaign_id'      => $campaign->get_id(),
			'discount_amount'  => $result->get_discount_amount(),
			'discounted_price' => $result->get_discounted_price(),
			'percentage'       => $result->get_discount_percentage(),
			'campaign_data'    => array(
				'max_uses_per_customer' => $campaign->get_setting( 'usage_limit', 0 ),
			),
		);

		if ( method_exists( $result, 'get_metadata' ) ) {
			$metadata = $result->get_metadata();
			if ( ! empty( $metadata ) ) {
				$discount_data['metadata'] = $metadata;
			}
		}

		return $discount_data;
	}

	/**
	 * Clear request-level cache.
	 *
	 * Useful for testing or when product/campaign data changes mid-request.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function clear_cache(): void {
		$this->cache = array();
	}

	/**
	 * Helper method for logging.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $level      Log level.
	 * @param    string $message    Log message.
	 * @param    array  $context    Context data.
	 * @return   void
	 */
	private function log( string $level, string $message, array $context = array() ): void {
		if ( $this->logger && method_exists( $this->logger, $level ) ) {
			$this->logger->$level( '[WC_Discount_Query] ' . $message, $context );
		}
	}
}
