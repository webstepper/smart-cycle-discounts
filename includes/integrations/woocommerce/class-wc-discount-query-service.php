<?php
/**
 * Wc Discount Query Service Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/integrations/woocommerce/class-wc-discount-query-service.php
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
 * @author     Webstepper <contact@webstepper.io>
 */
class WSSCD_WC_Discount_Query_Service {

	/**
	 * Campaign manager instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Campaign_Manager    $campaign_manager    Campaign manager.
	 */
	private WSSCD_Campaign_Manager $campaign_manager;

	/**
	 * Discount engine instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Discount_Engine    $discount_engine    Discount engine.
	 */
	private WSSCD_Discount_Engine $discount_engine;

	/**
	 * Logger instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      object|null    $logger    Logger instance.
	 */
	private ?object $logger;

	/**
	 * Discount rules enforcer.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Discount_Rules_Enforcer|null    $rules_enforcer    Rules enforcer.
	 */
	private ?WSSCD_Discount_Rules_Enforcer $rules_enforcer;

	/**
	 * Discount map service for efficient bulk lookups.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_WC_Discount_Map_Service|null    $map_service    Discount map service.
	 */
	private ?WSSCD_WC_Discount_Map_Service $map_service;

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
	 * @param    WSSCD_Campaign_Manager              $campaign_manager    Campaign manager instance.
	 * @param    WSSCD_Discount_Engine               $discount_engine     Discount engine instance.
	 * @param    object|null                       $logger              Logger instance.
	 * @param    WSSCD_Discount_Rules_Enforcer|null  $rules_enforcer      Rules enforcer instance.
	 * @param    WSSCD_WC_Discount_Map_Service|null  $map_service         Discount map service instance.
	 */
	public function __construct(
		WSSCD_Campaign_Manager $campaign_manager,
		WSSCD_Discount_Engine $discount_engine,
		?object $logger = null,
		?WSSCD_Discount_Rules_Enforcer $rules_enforcer = null,
		?WSSCD_WC_Discount_Map_Service $map_service = null
	) {
		$this->campaign_manager = $campaign_manager;
		$this->discount_engine  = $discount_engine;
		$this->logger           = $logger;
		$this->rules_enforcer   = $rules_enforcer;
		$this->map_service      = $map_service;
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
	 * Check if product has SCD badge enabled.
	 *
	 * Returns true only if the product has an active discount AND
	 * the campaign has badge display enabled. Used to determine
	 * whether to hide theme sale badges.
	 *
	 * @since    1.0.0
	 * @param    int $product_id    Product ID.
	 * @return   bool               True if WSSCD badge is enabled for this product.
	 */
	public function has_wsscd_badge_enabled( int $product_id ): bool {
		$cache_key = 'has_wsscd_badge_' . $product_id;

		if ( isset( $this->cache[ $cache_key ] ) ) {
			return $this->cache[ $cache_key ];
		}

		$badge_info = $this->get_campaign_badge_info( $product_id );
		$result     = $badge_info && ! empty( $badge_info['badge_enabled'] );

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
		$cache_key = 'discount_info_' . $product_id . '_' . md5( wp_json_encode( $context ) );

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

			// Step 4.5: Check discount rules eligibility
			if ( $this->rules_enforcer ) {
				$enforcement_context = $this->build_discount_context( $product, $product_id, $context );
				$enforcement_check   = $this->rules_enforcer->can_apply_discount(
					$discount_config,
					$enforcement_context,
					$campaign->get_id()
				);

				if ( ! $enforcement_check['allowed'] ) {
					$this->log(
						'debug',
						'Discount blocked by rules enforcer',
						array(
							'product_id'  => $product_id,
							'campaign_id' => $campaign->get_id(),
							'reason'      => $enforcement_check['reason'] ?? 'Unknown',
						)
					);
					return null;
				}
			}

			// Step 5: Calculate discount (always from regular price; never stack on WC sale price).
			$original_price   = floatval( $product->get_regular_price() );
			$discount_context = $this->build_discount_context( $product, $product_id, $context );

			$result = $this->calculate_discount( $original_price, $discount_config, $discount_context, $campaign );
			if ( ! $result ) {
				return null;
			}

			// Step 5.5: Apply maximum discount cap
			if ( $this->rules_enforcer && method_exists( $result, 'get_discount_amount' ) ) {
				$original_discount = $result->get_discount_amount();
				$capped_discount   = $this->rules_enforcer->apply_max_discount_cap( $original_discount, $discount_config );

				if ( $capped_discount < $original_discount ) {
					$capped_price = $original_price - $capped_discount;
					$result       = new WSSCD_Discount_Result(
						$original_price,
						$capped_price,
						$result->get_strategy_id(),
						true,
						array_merge(
							$result->get_metadata(),
							array(
								'discount_capped'    => true,
								'original_discount'  => $original_discount,
								'capped_discount'    => $capped_discount,
								'max_discount_limit' => $discount_config['max_discount_amount'] ?? 0,
							)
						)
					);

					$this->log(
						'debug',
						'Discount capped at maximum',
						array(
							'product_id'        => $product_id,
							'original_discount' => $original_discount,
							'capped_discount'   => $capped_discount,
						)
					);
				}
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

		// Use map service if available (efficient for shop/archive pages)
		if ( $this->map_service && $this->map_service->is_initialized() ) {
			foreach ( $product_ids_to_check as $check_id ) {
				$product_campaigns = $this->map_service->get_campaigns_for_product( $check_id );
				if ( ! empty( $product_campaigns ) ) {
					$campaigns = array_merge( $campaigns, $product_campaigns );
				}
			}
		} else {
			// Fallback to standard method (single product pages, cart, etc.)
			foreach ( $product_ids_to_check as $check_id ) {
				$product_campaigns = $this->campaign_manager->get_active_campaigns_for_product( $check_id );
				if ( ! empty( $product_campaigns ) ) {
					$campaigns = array_merge( $campaigns, $product_campaigns );
				}
			}
		}

		// Filter campaigns based on stacking rules
		$campaigns = $this->filter_campaigns_by_stacking( $campaigns );

		return $campaigns;
	}

	/**
	 * Filter campaigns based on stack_with_others rules.
	 *
	 * If a non-stackable campaign is already applied in cart, filter it out.
	 * If the highest priority campaign doesn't allow stacking, only return that one.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $campaigns    Array of campaign objects.
	 * @return   array                  Filtered campaigns.
	 */
	private function filter_campaigns_by_stacking( array $campaigns ): array {
		if ( empty( $campaigns ) || 1 === count( $campaigns ) ) {
			return $campaigns;
		}

		// Sort by priority to check highest priority campaign
		usort(
			$campaigns,
			function ( $a, $b ) {
				$priority_diff = $b->get_priority() <=> $a->get_priority();
				if ( 0 !== $priority_diff ) {
					return $priority_diff;
				}
				return $a->get_id() <=> $b->get_id();
			}
		);

		$highest_priority_campaign = reset( $campaigns );
		$discount_rules            = $highest_priority_campaign->get_discount_rules();
		$allows_stacking           = isset( $discount_rules['stack_with_others'] ) ?
			(bool) $discount_rules['stack_with_others'] : true;

		// If highest priority campaign doesn't allow stacking, only return it
		if ( ! $allows_stacking ) {
			$this->log(
				'debug',
				'Campaign stacking blocked',
				array(
					'campaign_id'   => $highest_priority_campaign->get_id(),
					'campaign_name' => $highest_priority_campaign->get_name(),
				)
			);
			return array( $highest_priority_campaign );
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
	 * @return   WSSCD_Campaign             Winning campaign.
	 */
	private function select_winning_campaign( array $campaigns, int $product_id ): WSSCD_Campaign {
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
	 * @param    WSSCD_Campaign $campaign    Campaign object.
	 * @return   array                        Discount configuration array.
	 */
	private function build_discount_config( WSSCD_Campaign $campaign ): array {
		$discount_type   = $campaign->get_discount_type();
		$discount_config = array(
			'type'  => $discount_type,
			'value' => $campaign->get_discount_value(),
		);

		// For complex discount types, include full discount_rules
		if ( in_array( $discount_type, array( 'tiered', 'bogo', 'spend_threshold' ), true ) ) {
			$discount_rules = $campaign->get_discount_rules();
			if ( ! empty( $discount_rules ) ) {
				// BOGO requires special handling - flatten bogo_config structure
				if ( 'bogo' === $discount_type && isset( $discount_rules['bogo_config'] ) ) {
					$bogo_config = $discount_rules['bogo_config'];
					$discount_config = array_merge(
						$discount_config,
						array(
							'buy_quantity'            => $bogo_config['buy_quantity'] ?? 1,
							'get_quantity'            => $bogo_config['get_quantity'] ?? 1,
							'get_discount_percentage' => $bogo_config['discount_percentage'] ?? $bogo_config['discount_percent'] ?? 100,  // Support both field names for backward compatibility
							'apply_to'                => $bogo_config['apply_to'] ?? 'cheapest',
						)
					);
				} else {
				// All other discount types - merge rules directly (already in snake_case from AJAX Router)
					$discount_config = array_merge( $discount_config, $discount_rules );
				}
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
	 * @param    WSSCD_Campaign $campaign            Campaign object (for logging).
	 * @return   object|null                           Discount result or null on error.
	 */
	private function calculate_discount(
		float $original_price,
		array $discount_config,
		array $discount_context,
		WSSCD_Campaign $campaign
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
	 * @param    WSSCD_Campaign $campaign           Campaign object.
	 * @param    object       $result             Discount calculation result.
	 * @return   array                               Formatted discount data.
	 */
	private function build_discount_data( array $discount_config, WSSCD_Campaign $campaign, object $result ): array {
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
	 * Get campaign badge information for product.
	 *
	 * Unlike get_discount_info(), this returns campaign data even when
	 * the discount doesn't apply at quantity=1 (e.g., BOGO campaigns).
	 * Used for displaying promotional badges on product pages.
	 *
	 * @since    1.0.0
	 * @param    int $product_id    Product ID.
	 * @return   array|null            Badge info or null if no campaigns.
	 */
	public function get_campaign_badge_info( int $product_id ): ?array {
		$cache_key = 'badge_info_' . $product_id;

		if ( isset( $this->cache[ $cache_key ] ) ) {
			return $this->cache[ $cache_key ];
		}

		try {
			// Get product
			$product = $this->get_product( $product_id );
			if ( ! $product ) {
				return null;
			}

			// Get applicable campaigns
			$campaigns = $this->get_applicable_campaigns( $product );
			if ( empty( $campaigns ) ) {
				return null;
			}

			// Select winning campaign
			$campaign = $this->select_winning_campaign( $campaigns, $product_id );

			// Build discount configuration
			$discount_config = $this->build_discount_config( $campaign );

			// Build badge data
			$badge_data = array(
				'type'          => $discount_config['type'] ?? 'percentage',
				'value'         => $discount_config['value'] ?? 0,
				'campaign_id'   => $campaign->get_id(),
				'campaign_name' => $campaign->get_name(),
				// Badge enabled status
				'badge_enabled'    => $campaign->is_badge_enabled(),
				// Badge styling settings from campaign
				'badge_text'       => $campaign->get_badge_text() ?: 'auto',
				'badge_bg_color'   => $campaign->get_badge_bg_color() ?: '#ff0000',
				'badge_text_color' => $campaign->get_badge_text_color() ?: '#ffffff',
				'badge_position'   => $campaign->get_badge_position() ?: 'top-right',
			);

			// Add type-specific data
			switch ( $badge_data['type'] ) {
				case 'bogo':
					$badge_data['buy_quantity']            = $discount_config['buy_quantity'] ?? 1;
					$badge_data['get_quantity']            = $discount_config['get_quantity'] ?? 1;
					$badge_data['get_discount_percentage'] = $discount_config['get_discount_percentage'] ?? 100;
					break;
				case 'tiered':
					$badge_data['tiers']    = $discount_config['tiers'] ?? array();
					$badge_data['apply_to'] = $discount_config['apply_to'] ?? 'per_item';
					break;
				case 'spend_threshold':
					$badge_data['thresholds'] = $discount_config['thresholds'] ?? array();
					break;
			}

			$this->cache[ $cache_key ] = $badge_data;
			return $badge_data;

		} catch ( Exception $e ) {
			$this->log(
				'error',
				'Failed to get campaign badge info',
				array(
					'product_id' => $product_id,
					'error'      => $e->getMessage(),
				)
			);
			return null;
		}
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
