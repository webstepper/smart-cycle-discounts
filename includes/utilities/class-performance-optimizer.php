<?php
/**
 * Performance Optimizer Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/utilities/class-performance-optimizer.php
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
 * Performance Optimizer class.
 *
 * Provides memoization and caching utilities for hot paths.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/performance
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Performance_Optimizer {

	/**
	 * Request-level memoization cache.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $memoized    Memoized values for current request.
	 */
	private static array $memoized = array();

	/**
	 * Maximum number of items to keep in memoization cache.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      int    $max_cache_size    Maximum cache size.
	 */
	private static int $max_cache_size = 100;

	/**
	 * Track cache access for LRU eviction.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $cache_access    Access timestamps for cache keys.
	 */
	private static array $cache_access = array();

	/**
	 * Track if cart calculation is in progress.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      bool    $calculating    Whether calculation is in progress.
	 */
	private static bool $calculating = false;

	/**
	 * Memoize a function result within the current request.
	 *
	 * @since    1.0.0
	 * @param    string   $key         Cache key.
	 * @param    callable $callback    Function to memoize.
	 * @param    array    $args        Arguments for the function.
	 * @param    array    $cache_data  Optional. Data to use for cache key (defaults to $args).
	 * @return   mixed                 Cached or computed result.
	 */
	public static function memoize( string $key, callable $callback, array $args = array(), array $cache_data = null ) {
		// Use cache_data for key if provided, otherwise use args
		// This allows separating the cache key from the actual function arguments
		$key_data = $cache_data !== null ? $cache_data : $args;

		// Generate unique cache key
		$cache_key = $key . '_' . md5( serialize( $key_data ) );

		if ( isset( self::$memoized[ $cache_key ] ) ) {
			self::$cache_access[ $cache_key ] = microtime( true );
			return self::$memoized[ $cache_key ];
		}

		if ( count( self::$memoized ) >= self::$max_cache_size ) {
			self::evict_lru_entry();
		}

		// Compute and cache the result
		$result                           = call_user_func_array( $callback, $args );
		self::$memoized[ $cache_key ]     = $result;
		self::$cache_access[ $cache_key ] = microtime( true );

		return $result;
	}

	/**
	 * Clear memoization cache.
	 *
	 * @since    1.0.0
	 * @param    string|null $key    Optional specific key to clear.
	 * @return   void
	 */
	public static function clear_memoized( ?string $key = null ): void {
		if ( $key === null ) {
			self::$memoized     = array();
			self::$cache_access = array();
		} else {
			foreach ( array_keys( self::$memoized ) as $cache_key ) {
				if ( strpos( $cache_key, $key . '_' ) === 0 ) {
					unset( self::$memoized[ $cache_key ] );
					unset( self::$cache_access[ $cache_key ] );
				}
			}
		}
	}

	/**
	 * Evict least recently used cache entry.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private static function evict_lru_entry(): void {
		if ( empty( self::$cache_access ) ) {
			return;
		}

		// Find the least recently used key
		$lru_key = array_search( min( self::$cache_access ), self::$cache_access );

		if ( $lru_key !== false ) {
			unset( self::$memoized[ $lru_key ] );
			unset( self::$cache_access[ $lru_key ] );
		}
	}

	/**
	 * Get or set transient with proper error handling.
	 *
	 * @since    1.0.0
	 * @param    string   $key        Transient key.
	 * @param    callable $callback   Function to generate value.
	 * @param    int      $expiration Expiration in seconds.
	 * @return   mixed                Cached or computed value.
	 */
	public static function get_or_set_transient( string $key, callable $callback, int $expiration = 3600 ) {
		// Try to get from transient
		$value = get_transient( $key );

		if ( $value !== false ) {
			return $value;
		}

		// Generate new value
		$value = call_user_func( $callback );

		set_transient( $key, $value, $expiration );

		return $value;
	}

	/**
	 * Check if cart calculation is in progress (prevent recursion).
	 *
	 * @since    1.0.0
	 * @return   bool    True if calculating, false otherwise.
	 */
	public static function is_calculating(): bool {
		return self::$calculating;
	}

	/**
	 * Set calculation status.
	 *
	 * @since    1.0.0
	 * @param    bool $status    Calculation status.
	 * @return   void
	 */
	public static function set_calculating( bool $status ): void {
		self::$calculating = $status;
	}

	/**
	 * Batch load product discounts to avoid N+1 queries.
	 *
	 * @since    1.0.0
	 * @param    array $product_ids    Product IDs to load.
	 * @return   array                    Product ID => discount info map.
	 */
	public static function batch_load_product_discounts( array $product_ids ): array {
		if ( empty( $product_ids ) ) {
			return array();
		}

		return self::memoize(
			'batch_product_discounts',
			function () use ( $product_ids ) {
				global $wpdb;

				$placeholders = implode( ',', array_fill( 0, count( $product_ids ), '%d' ) );
				$table_name   = $wpdb->prefix . 'scd_active_discounts';

				// Query all active discounts for these products in one go
				$results = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT product_id, discount_type, discount_amount, discount_percentage
                     FROM {$table_name}
                     WHERE product_id IN ({$placeholders})
                     AND status = 'active'
                     AND (valid_from <= NOW() OR valid_from IS NULL)
                     AND (valid_until >= NOW() OR valid_until IS NULL)",
						...$product_ids
					),
					ARRAY_A
				);

				$discount_map = array();
				foreach ( $results as $row ) {
					$discount_map[ $row['product_id'] ] = array(
						'type'       => $row['discount_type'],
						'amount'     => $row['discount_amount'],
						'percentage' => $row['discount_percentage'],
					);
				}

				return $discount_map;
			},
			array( $product_ids )
		);
	}

	/**
	 * Get stable reference data with caching.
	 *
	 * @since    1.0.0
	 * @param    string $type    Data type (e.g., 'active_campaigns', 'discount_rules').
	 * @return   array               Cached reference data.
	 */
	public static function get_reference_data( string $type ): array {
		$cache_key      = 'scd_ref_data_' . $type;
		$cache_duration = 300; // 5 minutes

		return self::get_or_set_transient(
			$cache_key,
			function () use ( $type ) {
				switch ( $type ) {
					case 'active_campaigns':
						return self::load_active_campaigns();

					case 'discount_rules':
						return self::load_discount_rules();

					case 'validation_rules':
						return self::load_validation_rules();

					default:
						return array();
				}
			},
			$cache_duration
		);
	}

	/**
	 * Load active campaigns.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   array    Active campaigns.
	 */
	private static function load_active_campaigns(): array {
		global $wpdb;

		$table_name = $wpdb->prefix . 'scd_campaigns';

		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id, name, priority, discount_type
                 FROM %i
                 WHERE status = %s
                 AND deleted_at IS NULL
                 ORDER BY priority DESC, created_at DESC
                 LIMIT %d',
				$table_name,
				'active',
				50
			),
			ARRAY_A
		) ?: array();
	}

	/**
	 * Load discount rules.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   array    Discount rules.
	 */
	private static function load_discount_rules(): array {
		if ( class_exists( 'SCD_Validation' ) ) {
			return array(
				'discount_types' => SCD_Validation_Rules::DISCOUNT_TYPES,
				'percentage_min' => SCD_Validation_Rules::PERCENTAGE_MIN,
				'percentage_max' => SCD_Validation_Rules::PERCENTAGE_MAX,
				'fixed_min'      => SCD_Validation_Rules::FIXED_MIN,
				'fixed_max'      => SCD_Validation_Rules::FIXED_MAX,
			);
		}

		return array();
	}

	/**
	 * Load validation rules.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   array    Validation rules.
	 */
	private static function load_validation_rules(): array {
		// Use the consolidated validation class to get all rules
		if ( class_exists( 'SCD_Validation' ) ) {
			return SCD_Validation::get_js_data();
		}

		return array();
	}

	/**
	 * Set maximum cache size.
	 *
	 * @since    1.0.0
	 * @param    int $size    Maximum number of cache entries.
	 * @return   void
	 */
	public static function set_max_cache_size( int $size ): void {
		self::$max_cache_size = max( 10, min( 1000, $size ) );

		// Trim cache if needed
		while ( count( self::$memoized ) > self::$max_cache_size ) {
			self::evict_lru_entry();
		}
	}

	/**
	 * Get current cache stats.
	 *
	 * @since    1.0.0
	 * @return   array    Cache statistics.
	 */
	public static function get_cache_stats(): array {
		return array(
			'size'         => count( self::$memoized ),
			'max_size'     => self::$max_cache_size,
			'memory_usage' => strlen( serialize( self::$memoized ) ),
		);
	}

	/**
	 * Register cleanup hooks for long-running processes.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public static function register_cleanup_hooks(): void {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			// For WP-CLI commands, clear after each command
			add_action( 'cli_init', array( __CLASS__, 'clear_memoized' ) );
		}

		register_shutdown_function( array( __CLASS__, 'clear_memoized' ) );

		// Hook into WordPress cron to clear before scheduled tasks
		add_action( 'pre_schedule_event', array( __CLASS__, 'clear_memoized' ) );
	}

	/**
	 * Optimize cart calculation hook.
	 *
	 * @since    1.0.0
	 * @param    callable|array $callback    Original callback (closure, array, or string).
	 * @return   callable                       Optimized callback.
	 */
	public static function optimize_cart_calculation( $callback ): callable {
		return function ( $cart ) use ( $callback ) {
			// Prevent recursive calculations
			if ( self::is_calculating() ) {
				return;
			}

			self::set_calculating( true );

			try {
				// Pre-load all product discounts for cart items
				$product_ids = array();
				foreach ( $cart->get_cart() as $cart_item ) {
					$product_ids[] = $cart_item['data']->get_id();
				}

				// Batch load to avoid N+1 queries
				$discounts = self::batch_load_product_discounts( $product_ids );

				foreach ( $discounts as $product_id => $discount ) {
					self::$memoized[ 'product_discount_' . $product_id ] = $discount;
				}

				// Call original callback
				call_user_func( $callback, $cart );

			} finally {
				self::set_calculating( false );
				self::clear_memoized( 'product_discount' );
			}
		};
	}
}
