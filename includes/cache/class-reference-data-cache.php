<?php
/**
 * Reference Data Cache Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/cache/class-reference-data-cache.php
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
 * Reference Data Cache class.
 *
 * Thin wrapper around WSSCD_Cache_Manager for reference data caching.
 * Uses cache manager as single source of truth with reference-specific defaults.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/cache
 * @author     Webstepper <contact@webstepper.io>
 */
class WSSCD_Reference_Data_Cache {

	/**
	 * Cache manager instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Cache_Manager    $cache    Cache manager.
	 */
	private WSSCD_Cache_Manager $cache;

	/**
	 * Cache group for reference data.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $cache_group    Cache group name.
	 */
	private string $cache_group = 'reference';

	/**
	 * Default cache durations.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $cache_durations    Cache durations by type.
	 */
	private array $cache_durations = array(
		'categories'       => 1800, // 30 minutes
		'tags'             => 1800, // 30 minutes
		'attributes'       => 1800, // 30 minutes
		'tax_rates'        => 3600, // 1 hour (changes rarely)
		'currencies'       => 3600, // 1 hour (stable data)
		'countries'        => 3600, // 1 hour (stable data)
		'states'           => 3600, // 1 hour (stable data)
		'payment_methods'  => 1800, // 30 minutes
		'shipping_methods' => 1800, // 30 minutes
		'customer_groups'  => 900,  // 15 minutes
		'active_campaigns' => 1800, // 30 minutes
		'discount_rules'   => 1800, // 30 minutes
		'validation_rules' => 1800, // 30 minutes
	);

	/**
	 * Initialize the reference data cache.
	 *
	 * @since    1.0.0
	 * @param    WSSCD_Cache_Manager $cache    Cache manager instance.
	 */
	public function __construct( WSSCD_Cache_Manager $cache ) {
		$this->cache = $cache;
		$this->load_cache_durations_from_settings();
	}

	/**
	 * Load cache durations.
	 *
	 * Uses sensible defaults with filter for developers who need customization.
	 * Default: 3600 seconds (1 hour) - good balance between freshness and performance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function load_cache_durations_from_settings(): void {
		/**
		 * Filter the reference data cache duration.
		 *
		 * @since 1.0.0
		 * @param int $duration Cache duration in seconds. Default 3600 (1 hour).
		 */
		$duration = apply_filters( 'wsscd_reference_cache_duration', 3600 );
		$duration = max( 900, (int) $duration ); // Minimum 15 minutes

		// Update all reference data cache durations
		$this->cache_durations = array(
			'categories'       => $duration,
			'tags'             => $duration,
			'attributes'       => $duration,
			'tax_rates'        => $duration,
			'currencies'       => $duration,
			'countries'        => $duration,
			'states'           => $duration,
			'payment_methods'  => $duration,
			'shipping_methods' => $duration,
			'customer_groups'  => $duration,
			'active_campaigns' => $duration,
			'discount_rules'   => $duration,
			'validation_rules' => $duration,
		);
	}

	/**
	 * Get cached reference data.
	 *
	 * @since    1.0.0
	 * @param    string   $type        Data type.
	 * @param    callable $generator   Function to generate data if not cached.
	 * @param    int|null $duration    Optional custom cache duration.
	 * @return   mixed                  Cached or generated data.
	 */
	public function get( string $type, callable $generator, ?int $duration = null ) {
		$cache_duration = $duration ?? $this->cache_durations[ $type ] ?? 3600;
		$cache_key      = $this->get_cache_key( $type );

		return $this->cache->remember( $cache_key, $generator, $cache_duration );
	}

	/**
	 * Set cached reference data.
	 *
	 * @since    1.0.0
	 * @param    string $type        Data type.
	 * @param    mixed  $data        Data to cache.
	 * @param    int    $duration    Cache duration in seconds.
	 * @return   bool                   True on success.
	 */
	public function set( string $type, $data, int $duration ): bool {
		$cache_key = $this->get_cache_key( $type );
		return $this->cache->set( $cache_key, $data, $duration );
	}

	/**
	 * Delete cached reference data.
	 *
	 * @since    1.0.0
	 * @param    string $type    Data type.
	 * @return   bool               True on success.
	 */
	public function delete( string $type ): bool {
		$cache_key = $this->get_cache_key( $type );
		return $this->cache->delete( $cache_key );
	}

	/**
	 * Clear all cached reference data.
	 *
	 * @since    1.0.0
	 * @return   bool    True on success.
	 */
	public function clear_all(): bool {
		return $this->cache->delete_group( $this->cache_group );
	}

	/**
	 * Get cache key for type.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $type    Data type.
	 * @return   string             Cache key.
	 */
	private function get_cache_key( string $type ): string {
		return $this->cache->reference_key( $type );
	}

	/**
	 * Preload common reference data.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function preload_common_data(): void {
		$preload_types = array(
			'categories',
			'active_campaigns',
			'discount_rules',
			'validation_rules',
		);

		foreach ( $preload_types as $type ) {
			$generator = $this->get_data_generator( $type );
			if ( $generator ) {
				try {
					$this->get( $type, $generator );
				} catch ( Exception $e ) {
					// Log error but continue preloading
				}
			}
		}
	}

	/**
	 * Get data generator for type.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $type    Data type.
	 * @return   callable|null      Generator function or null.
	 */
	private function get_data_generator( string $type ): ?callable {
		switch ( $type ) {
			case 'categories':
				return function () {
					return get_terms(
						array(
							'taxonomy'   => 'product_cat',
							'hide_empty' => false,
							'fields'     => 'id=>name',
						)
					);
				};

			case 'tags':
				return function () {
					return get_terms(
						array(
							'taxonomy'   => 'product_tag',
							'hide_empty' => false,
							'fields'     => 'id=>name',
						)
					);
				};

			case 'active_campaigns':
				return function () {
					return WSSCD_Performance_Optimizer::get_reference_data( 'active_campaigns' );
				};

			case 'discount_rules':
				return function () {
					return WSSCD_Performance_Optimizer::get_reference_data( 'discount_rules' );
				};

			case 'validation_rules':
				return function () {
					return WSSCD_Performance_Optimizer::get_reference_data( 'validation_rules' );
				};

			case 'tax_rates':
				return function () {
					if ( class_exists( 'WC_Tax' ) ) {
						return WC_Tax::get_rates();
					}
					return array();
				};

			case 'currencies':
				return function () {
					if ( function_exists( 'get_woocommerce_currencies' ) ) {
						return get_woocommerce_currencies();
					}
					return array();
				};

			case 'countries':
				return function () {
					if ( class_exists( 'WC_Countries' ) ) {
						$countries = new WC_Countries();
						return $countries->get_countries();
					}
					return array();
				};

			case 'payment_methods':
				return function () {
					if ( function_exists( 'WC' ) ) {
						return WC()->payment_gateways()->get_available_payment_gateways();
					}
					return array();
				};

			case 'shipping_methods':
				return function () {
					if ( function_exists( 'WC' ) ) {
						return WC()->shipping()->get_shipping_methods();
					}
					return array();
				};

			default:
				return null;
		}
	}

	/**
	 * Warm cache for specific types.
	 *
	 * @since    1.0.0
	 * @param    array $types    Data types to warm.
	 * @return   array              Results by type.
	 */
	public function warm_cache( array $types ): array {
		$results = array();

		foreach ( $types as $type ) {
			$generator = $this->get_data_generator( $type );
			if ( $generator ) {
				try {
					$this->get( $type, $generator );
					$results[ $type ] = true;
				} catch ( Exception $e ) {
					$results[ $type ] = false;
				}
			}
		}

		return $results;
	}
}
