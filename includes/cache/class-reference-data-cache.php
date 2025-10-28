<?php
/**
 * Reference Data Cache
 *
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/cache
 */

declare(strict_types=1);


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Reference Data Cache class.
 *
 * Manages caching of stable reference data using WordPress transients
 * and object cache when available.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/cache
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Reference_Data_Cache {

	/**
	 * Cache group for object cache.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $cache_group    Cache group name.
	 */
	private string $cache_group = 'scd_reference';

	/**
	 * Default cache durations.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $cache_durations    Cache durations by type.
	 */
	private array $cache_durations = array(
		'categories'       => 3600,        // 1 hour
		'tags'             => 3600,              // 1 hour
		'attributes'       => 3600,        // 1 hour
		'tax_rates'        => 7200,         // 2 hours
		'currencies'       => 86400,       // 24 hours
		'countries'        => 86400,        // 24 hours
		'states'           => 86400,           // 24 hours
		'payment_methods'  => 3600,   // 1 hour
		'shipping_methods' => 3600,  // 1 hour
		'customer_groups'  => 1800,   // 30 minutes
		'active_campaigns' => 300,   // 5 minutes
		'discount_rules'   => 600,     // 10 minutes
		'validation_rules' => 600,    // 10 minutes
	);

	/**
	 * Get cached reference data.
	 *
	 * @since    1.0.0
	 * @param    string   $type        Data type.
	 * @param    callable $generator   Function to generate data if not cached.
	 * @param    int|null $duration    Optional custom cache duration.
	 * @return   mixed                  Cached or generated data.
	 */
	public function get( string $type, callable $generator, ?int $duration = null ): mixed {
		// Determine cache duration
		$cache_duration = $duration ?? $this->cache_durations[ $type ] ?? 3600;

		// Try object cache first (if available)
		if ( wp_using_ext_object_cache() ) {
			$cached = wp_cache_get( $type, $this->cache_group );
			if ( $cached !== false ) {
				return $cached;
			}
		}

		// Try transient
		$transient_key = $this->get_transient_key( $type );
		$cached        = get_transient( $transient_key );

		if ( $cached !== false ) {
			// Store in object cache for this request
			if ( wp_using_ext_object_cache() ) {
				wp_cache_set( $type, $cached, $this->cache_group, $cache_duration );
			}
			return $cached;
		}

		// Generate fresh data
		$data = $this->generate_with_lock( $type, $generator );

		// Cache the data
		$this->set( $type, $data, $cache_duration );

		return $data;
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
		// Store in object cache
		if ( wp_using_ext_object_cache() ) {
			wp_cache_set( $type, $data, $this->cache_group, $duration );
		}

		// Store in transient
		$transient_key = $this->get_transient_key( $type );
		return set_transient( $transient_key, $data, $duration );
	}

	/**
	 * Delete cached reference data.
	 *
	 * @since    1.0.0
	 * @param    string $type    Data type.
	 * @return   bool               True on success.
	 */
	public function delete( string $type ): bool {
		// Delete from object cache
		if ( wp_using_ext_object_cache() ) {
			wp_cache_delete( $type, $this->cache_group );
		}

		// Delete transient
		$transient_key = $this->get_transient_key( $type );
		return delete_transient( $transient_key );
	}

	/**
	 * Clear all cached reference data.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function clear_all(): void {
		// Clear specific types
		foreach ( array_keys( $this->cache_durations ) as $type ) {
			$this->delete( $type );
		}

		// Clear any custom types with prefix
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} 
                WHERE option_name LIKE %s 
                OR option_name LIKE %s",
				'_transient_scd_ref_%',
				'_transient_timeout_scd_ref_%'
			)
		);

		// Clear object cache group
		if ( wp_using_ext_object_cache() ) {
			wp_cache_flush_group( $this->cache_group );
		}
	}

	/**
	 * Generate data with lock to prevent stampede.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string   $type        Data type.
	 * @param    callable $generator   Generator function.
	 * @return   mixed                  Generated data.
	 */
	private function generate_with_lock( string $type, callable $generator ): mixed {
		$lock_key      = "scd_ref_lock_{$type}";
		$lock_duration = 30; // 30 seconds max generation time

		// Try to acquire lock
		$lock_acquired = set_transient( $lock_key, 1, $lock_duration );

		if ( ! $lock_acquired ) {
			// Another process is generating, wait and retry
			$retries = 10;
			while ( $retries > 0 && get_transient( $lock_key ) !== false ) {
				usleep( 100000 ); // 100ms
				--$retries;
			}

			// Try to get cached value again
			$cached = get_transient( $this->get_transient_key( $type ) );
			if ( $cached !== false ) {
				return $cached;
			}
		}

		try {
			// Generate data
			$data = call_user_func( $generator );

			// Release lock
			delete_transient( $lock_key );

			return $data;

		} catch ( Exception $e ) {
			// Release lock on error
			delete_transient( $lock_key );
			throw $e;
		}
	}

	/**
	 * Get transient key for type.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $type    Data type.
	 * @return   string             Transient key.
	 */
	private function get_transient_key( string $type ): string {
		return 'scd_ref_' . $type;
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
			// Check if already cached
			if ( get_transient( $this->get_transient_key( $type ) ) !== false ) {
				continue;
			}

			// Get appropriate generator
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
					return SCD_Performance_Optimizer::get_reference_data( 'active_campaigns' );
				};

			case 'discount_rules':
				return function () {
					return SCD_Performance_Optimizer::get_reference_data( 'discount_rules' );
				};

			case 'validation_rules':
				return function () {
					return SCD_Performance_Optimizer::get_reference_data( 'validation_rules' );
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
