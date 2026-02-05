<?php
/**
 * WooCommerce Free Shipping Handler
 *
 * Handles free shipping functionality for campaigns.
 * Modifies WooCommerce shipping rates when cart qualifies for free shipping.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/integrations/woocommerce
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress/plugins/smart-cycle-discounts/
 * @since      1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Free Shipping Handler Class
 *
 * Filters WooCommerce shipping rates to apply free shipping when
 * cart contains products from campaigns with free shipping enabled.
 *
 * @since 1.2.0
 */
class WSSCD_WC_Free_Shipping_Handler {

	/**
	 * Discount query service.
	 *
	 * @since  1.2.0
	 * @access private
	 * @var    WSSCD_WC_Discount_Query_Service $discount_query Discount query service.
	 */
	private WSSCD_WC_Discount_Query_Service $discount_query;

	/**
	 * Campaign repository.
	 *
	 * @since  1.2.0
	 * @access private
	 * @var    WSSCD_Campaign_Repository $campaign_repository Campaign repository.
	 */
	private WSSCD_Campaign_Repository $campaign_repository;

	/**
	 * Logger instance.
	 *
	 * @since  1.2.0
	 * @access private
	 * @var    WSSCD_Logger $logger Logger.
	 */
	private WSSCD_Logger $logger;

	/**
	 * Cache for free shipping qualification checks.
	 *
	 * @since  1.2.0
	 * @access private
	 * @var    array $qualification_cache Cache array.
	 */
	private array $qualification_cache = array();

	/**
	 * Initialize the handler.
	 *
	 * @since 1.2.0
	 * @param WSSCD_WC_Discount_Query_Service $discount_query      Discount query service.
	 * @param WSSCD_Campaign_Repository       $campaign_repository Campaign repository.
	 * @param WSSCD_Logger                    $logger              Logger instance.
	 */
	public function __construct(
		WSSCD_WC_Discount_Query_Service $discount_query,
		WSSCD_Campaign_Repository $campaign_repository,
		WSSCD_Logger $logger
	) {
		$this->discount_query      = $discount_query;
		$this->campaign_repository = $campaign_repository;
		$this->logger              = $logger;
	}

	/**
	 * Register hooks.
	 *
	 * @since  1.2.0
	 * @return void
	 */
	public function register_hooks(): void {
		// Filter shipping rates - high priority to run after other shipping calculations.
		add_filter( 'woocommerce_package_rates', array( $this, 'apply_free_shipping' ), 100, 2 );

		// Clear cache when cart changes.
		add_action( 'woocommerce_cart_loaded_from_session', array( $this, 'clear_cache' ) );
		add_action( 'woocommerce_add_to_cart', array( $this, 'clear_cache' ) );
		add_action( 'woocommerce_cart_item_removed', array( $this, 'clear_cache' ) );
		add_action( 'woocommerce_cart_item_restored', array( $this, 'clear_cache' ) );
		add_action( 'woocommerce_after_cart_item_quantity_update', array( $this, 'clear_cache' ) );
	}

	/**
	 * Apply free shipping to qualifying shipping rates.
	 *
	 * @since  1.2.0
	 * @param  array $rates   Shipping rates.
	 * @param  array $package Package data.
	 * @return array Modified shipping rates.
	 */
	public function apply_free_shipping( array $rates, array $package ): array {
		// Skip if no rates.
		if ( empty( $rates ) ) {
			return $rates;
		}

		// Get free shipping configuration for the current cart.
		$free_shipping_config = $this->get_free_shipping_config_for_cart( $package );

		if ( empty( $free_shipping_config ) || ! $free_shipping_config['enabled'] ) {
			return $rates;
		}

		$methods = $free_shipping_config['methods'];

		foreach ( $rates as $rate_id => $rate ) {
			// Skip local pickup - it's already free.
			if ( 'local_pickup' === $rate->method_id ) {
				continue;
			}

			// Check if this method should be free.
			if ( $this->should_make_method_free( $rate_id, $rate, $methods ) ) {
				$rates[ $rate_id ]->cost  = 0;
				$rates[ $rate_id ]->taxes = array();

				// Update label to indicate free shipping.
				$label = $rate->label;
				if ( false === strpos( $label, __( 'Free', 'smart-cycle-discounts' ) ) ) {
					$rates[ $rate_id ]->label = $label . ' ' . __( '(Free!)', 'smart-cycle-discounts' );
				}
			}
		}

		return $rates;
	}

	/**
	 * Check if a shipping method should be made free.
	 *
	 * @since  1.2.0
	 * @param  string            $rate_id Rate ID.
	 * @param  WC_Shipping_Rate  $rate    Rate object.
	 * @param  string|array      $methods Methods configuration ('all' or array of method IDs).
	 * @return bool True if method should be free.
	 */
	private function should_make_method_free( string $rate_id, WC_Shipping_Rate $rate, $methods ): bool {
		// If 'all' methods are free, return true.
		if ( 'all' === $methods ) {
			return true;
		}

		// Check if rate ID is in selected methods.
		if ( is_array( $methods ) ) {
			// Check exact match first.
			if ( in_array( $rate_id, $methods, true ) ) {
				return true;
			}

			// Check method ID match (without instance).
			$method_id = $rate->method_id;
			foreach ( $methods as $selected_method ) {
				// Handle formats like 'flat_rate:1' or just 'flat_rate'.
				$selected_method_base = explode( ':', $selected_method )[0];
				if ( $method_id === $selected_method_base || $rate_id === $selected_method ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Get free shipping configuration for the current cart.
	 *
	 * Checks all cart items against active campaigns and returns
	 * the free shipping config if cart qualifies.
	 *
	 * @since  1.2.0
	 * @param  array $package Package data.
	 * @return array|null Free shipping config or null if not qualifying.
	 */
	private function get_free_shipping_config_for_cart( array $package ): ?array {
		// Check cache.
		$cache_key = $this->get_cache_key( $package );
		if ( isset( $this->qualification_cache[ $cache_key ] ) ) {
			return $this->qualification_cache[ $cache_key ];
		}

		$cart_contents = $package['contents'] ?? array();
		if ( empty( $cart_contents ) ) {
			$this->qualification_cache[ $cache_key ] = null;
			return null;
		}

		// Collect campaigns with free shipping enabled.
		$free_shipping_campaigns = array();

		foreach ( $cart_contents as $cart_item_key => $cart_item ) {
			$product_id = $cart_item['product_id'];

			// Get discount info for this product.
			$discount_info = $this->discount_query->get_discount_info( $product_id );

			if ( empty( $discount_info ) || empty( $discount_info['campaign_id'] ) ) {
				continue;
			}

			$campaign_id = $discount_info['campaign_id'];

			// Skip if already checked.
			if ( isset( $free_shipping_campaigns[ $campaign_id ] ) ) {
				continue;
			}

			// Get campaign to check free shipping config.
			try {
				$campaign = $this->campaign_repository->find( $campaign_id );

				if ( ! $campaign ) {
					continue;
				}

				$config = $campaign->get_free_shipping_config();

				if ( empty( $config['enabled'] ) ) {
					continue;
				}

				// For spend threshold, check if threshold is met.
				if ( 'spend_threshold' === $campaign->get_discount_type() ) {
					if ( ! $this->cart_meets_spend_threshold( $campaign, $package ) ) {
						continue;
					}
				}

				// Cart qualifies for free shipping from this campaign.
				$free_shipping_campaigns[ $campaign_id ] = $config;

			} catch ( Exception $e ) {
				$this->logger->error(
					'Error checking free shipping for campaign',
					array(
						'campaign_id' => $campaign_id,
						'error'       => $e->getMessage(),
					)
				);
				continue;
			}
		}

		// If any campaign grants free shipping, use the most permissive config.
		if ( empty( $free_shipping_campaigns ) ) {
			$this->qualification_cache[ $cache_key ] = null;
			return null;
		}

		// Merge configs - 'all' methods takes precedence.
		$result = array(
			'enabled' => true,
			'methods' => array(),
		);

		foreach ( $free_shipping_campaigns as $config ) {
			if ( 'all' === $config['methods'] ) {
				$result['methods'] = 'all';
				break; // 'all' is most permissive, no need to check more.
			}

			if ( is_array( $config['methods'] ) ) {
				$result['methods'] = array_unique(
					array_merge(
						is_array( $result['methods'] ) ? $result['methods'] : array(),
						$config['methods']
					)
				);
			}
		}

		$this->qualification_cache[ $cache_key ] = $result;
		return $result;
	}

	/**
	 * Check if cart meets spend threshold for a campaign.
	 *
	 * @since  1.2.0
	 * @param  WSSCD_Campaign $campaign Campaign object.
	 * @param  array          $package  Package data.
	 * @return bool True if threshold is met.
	 */
	private function cart_meets_spend_threshold( WSSCD_Campaign $campaign, array $package ): bool {
		$discount_rules = $campaign->get_discount_rules();
		$thresholds     = $discount_rules['thresholds'] ?? array();

		if ( empty( $thresholds ) ) {
			return false;
		}

		// Get cart total for campaign products.
		$campaign_products_total = $this->get_campaign_products_total( $campaign, $package );

		// Get minimum threshold (lowest spend_amount from thresholds array).
		$min_threshold = PHP_FLOAT_MAX;
		foreach ( $thresholds as $threshold ) {
			$threshold_amount = floatval( $threshold['spend_amount'] ?? 0 );
			if ( $threshold_amount > 0 && $threshold_amount < $min_threshold ) {
				$min_threshold = $threshold_amount;
			}
		}

		// If no valid threshold found, return false.
		if ( PHP_FLOAT_MAX === $min_threshold ) {
			return false;
		}

		return $campaign_products_total >= $min_threshold;
	}

	/**
	 * Get total value of campaign products in cart.
	 *
	 * @since  1.2.0
	 * @param  WSSCD_Campaign $campaign Campaign object.
	 * @param  array          $package  Package data.
	 * @return float Total value.
	 */
	private function get_campaign_products_total( WSSCD_Campaign $campaign, array $package ): float {
		$total         = 0.0;
		$cart_contents = $package['contents'] ?? array();

		foreach ( $cart_contents as $cart_item ) {
			$product_id   = $cart_item['product_id'];
			$discount_info = $this->discount_query->get_discount_info( $product_id );

			if ( ! empty( $discount_info['campaign_id'] ) && (int) $discount_info['campaign_id'] === $campaign->get_id() ) {
				$quantity = $cart_item['quantity'] ?? 1;
				$price    = $cart_item['data']->get_price();
				$total   += $price * $quantity;
			}
		}

		return $total;
	}

	/**
	 * Generate cache key for package.
	 *
	 * @since  1.2.0
	 * @param  array $package Package data.
	 * @return string Cache key.
	 */
	private function get_cache_key( array $package ): string {
		$contents = $package['contents'] ?? array();
		$key_data = array();

		foreach ( $contents as $cart_item ) {
			$key_data[] = $cart_item['product_id'] . ':' . ( $cart_item['quantity'] ?? 1 );
		}

		return md5( implode( '|', $key_data ) );
	}

	/**
	 * Clear the qualification cache.
	 *
	 * @since  1.2.0
	 * @return void
	 */
	public function clear_cache(): void {
		$this->qualification_cache = array();
	}

	/**
	 * Get available shipping methods for the store.
	 *
	 * Returns all shipping methods configured in WooCommerce shipping zones.
	 *
	 * @since  1.2.0
	 * @return array Array of shipping methods with id and title.
	 */
	public static function get_available_shipping_methods(): array {
		$methods = array();

		try {
			// Ensure WooCommerce is loaded.
			if ( ! function_exists( 'WC' ) || ! class_exists( 'WC_Shipping_Zones' ) ) {
				return $methods;
			}

			// Get WooCommerce instance safely.
			$wc = WC();
			if ( null === $wc ) {
				return $methods;
			}

			// Ensure WooCommerce shipping is initialized.
			if ( isset( $wc->shipping ) && null !== $wc->shipping ) {
				$wc->shipping->load_shipping_methods();
			}

			// Get all shipping zones.
			$zones = WC_Shipping_Zones::get_zones();

			foreach ( $zones as $zone_data ) {
				$zone_name = isset( $zone_data['zone_name'] ) ? $zone_data['zone_name'] : '';

				// Get shipping methods for this zone.
				$zone_methods = isset( $zone_data['shipping_methods'] ) ? $zone_data['shipping_methods'] : array();

				foreach ( $zone_methods as $method ) {
					if ( ! is_object( $method ) || ! method_exists( $method, 'get_rate_id' ) ) {
						continue;
					}

					// Only include enabled methods.
					if ( ! isset( $method->enabled ) || 'yes' !== $method->enabled ) {
						continue;
					}

					$rate_id = $method->get_rate_id();
					$title   = $method->get_title();

					$methods[] = array(
						'id'    => $rate_id,
						'title' => $zone_name . ': ' . $title,
					);
				}
			}

			// Add methods from the default zone (Rest of World / Locations not covered).
			$default_zone = WC_Shipping_Zones::get_zone( 0 );
			if ( $default_zone ) {
				$zone_name = $default_zone->get_zone_name();
				if ( empty( $zone_name ) ) {
					$zone_name = __( 'Locations not covered by your other zones', 'smart-cycle-discounts' );
				}

				foreach ( $default_zone->get_shipping_methods() as $method ) {
					if ( ! is_object( $method ) || ! method_exists( $method, 'get_rate_id' ) ) {
						continue;
					}

					// Only include enabled methods.
					if ( ! isset( $method->enabled ) || 'yes' !== $method->enabled ) {
						continue;
					}

					$rate_id = $method->get_rate_id();
					$title   = $method->get_title();

					$methods[] = array(
						'id'    => $rate_id,
						'title' => $zone_name . ': ' . $title,
					);
				}
			}
		} catch ( Exception $e ) {
			// Return empty array on any error.
			return array();
		}

		return $methods;
	}

	/**
	 * Check if any active campaign has free shipping enabled.
	 *
	 * Useful for determining whether to show free shipping badges.
	 *
	 * @since  1.2.0
	 * @param  int $product_id Product ID.
	 * @return bool True if product is in a campaign with free shipping.
	 */
	public function product_has_free_shipping( int $product_id ): bool {
		$discount_info = $this->discount_query->get_discount_info( $product_id );

		if ( empty( $discount_info['campaign_id'] ) ) {
			return false;
		}

		try {
			$campaign = $this->campaign_repository->find( $discount_info['campaign_id'] );
			return $campaign && $campaign->is_free_shipping_enabled();
		} catch ( Exception $e ) {
			return false;
		}
	}
}
