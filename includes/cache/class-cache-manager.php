<?php
/**
 * Cache Manager Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/cache/class-cache-manager.php
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
 * Cache Manager
 *
 * Manages caching for the plugin with multiple cache layers.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/cache
 * @author     Webstepper <contact@webstepper.io>
 */
class SCD_Cache_Manager {

	/**
	 * Valid cache groups for key validation.
	 *
	 * All cache keys MUST start with one of these group names.
	 * This ensures delete_group() works correctly.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $valid_groups    Valid cache groups.
	 */
	private array $valid_groups = array(
		'campaigns',
		'products',
		'analytics',
		'reference',
	);

	/**
	 * Cache prefix for all plugin cache keys.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $cache_prefix    Cache prefix.
	 */
	private string $cache_prefix = 'scd_';

	/**
	 * Default cache expiration time in seconds.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      int    $default_expiration    Default expiration.
	 */
	private int $default_expiration = 1800; // 30 minutes

	/**
	 * Whether caching is enabled.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      bool    $enabled    Cache enabled status.
	 */
	private bool $enabled = true;

	/**
	 * Cache version for invalidation.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $cache_version    Cache version.
	 */
	private string $cache_version = '';

	/**
	 * Initialize the cache manager.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		$this->enabled = $this->is_cache_enabled();
		$this->load_settings();
		$this->cache_version = $this->get_cache_version();
	}

	/**
	 * Load cache settings.
	 *
	 * Uses sensible defaults with filter for developers who need customization.
	 * Default: 3600 seconds (1 hour) - good balance between freshness and performance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function load_settings(): void {
		/**
		 * Filter the default cache duration.
		 *
		 * @since 1.0.0
		 * @param int $duration Cache duration in seconds. Default 3600 (1 hour).
		 */
		$duration                 = apply_filters( 'scd_cache_duration', 3600 );
		$this->default_expiration = max( 900, (int) $duration ); // Minimum 15 minutes
	}

	/**
	 * Build a properly-formatted cache key for campaigns group.
	 *
	 * @since    1.0.0
	 * @param    string $suffix    Key suffix (e.g., 'campaign_97', 'all_active').
	 * @return   string               Properly-formatted cache key.
	 */
	public function campaigns_key( string $suffix ): string {
		return 'campaigns_' . $suffix;
	}

	/**
	 * Build a properly-formatted cache key for products group.
	 *
	 * @since    1.0.0
	 * @param    string $suffix    Key suffix (e.g., 'active_campaigns_84').
	 * @return   string               Properly-formatted cache key.
	 */
	public function products_key( string $suffix ): string {
		return 'products_' . $suffix;
	}

	/**
	 * Build a properly-formatted cache key for analytics group.
	 *
	 * @since    1.0.0
	 * @param    string $suffix    Key suffix.
	 * @return   string               Properly-formatted cache key.
	 */
	public function analytics_key( string $suffix ): string {
		return 'analytics_' . $suffix;
	}

	/**
	 * Build a properly-formatted cache key for reference group.
	 *
	 * @since    1.0.0
	 * @param    string $suffix    Key suffix.
	 * @return   string               Properly-formatted cache key.
	 */
	public function reference_key( string $suffix ): string {
		return 'reference_' . $suffix;
	}

	/**
	 * Validate cache key follows naming convention.
	 *
	 * Ensures cache key starts with a valid group prefix.
	 * Logs warning in debug mode if invalid.
	 *
	 * @since    1.0.0
	 * @param    string $key    Cache key to validate.
	 * @return   bool              True if valid, false otherwise.
	 */
	private function validate_key( string $key ): bool {
		foreach ( $this->valid_groups as $group ) {
			if ( 0 === strpos( $key, $group . '_' ) ) {
				return true;
			}
		}

		// Log warning in debug mode
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$valid_prefixes = array_map( function( $g ) { return $g . '_'; }, $this->valid_groups );
			error_log(
				sprintf(
					'[SCD Cache] WARNING: Invalid cache key "%s" - must start with one of: %s',
					$key,
					implode( ', ', $valid_prefixes )
				)
			);
		}

		return false;
	}

	/**
	 * Get cache key group.
	 *
	 * Extracts the group name from a cache key.
	 *
	 * @since    1.0.0
	 * @param    string $key    Cache key.
	 * @return   string|null       Group name or null if invalid.
	 */
	private function get_key_group( string $key ): ?string {
		$parts = explode( '_', $key, 2 );
		$group = isset( $parts[0] ) ? $parts[0] : null;

		// Validate it's a real group
		if ( $group && in_array( $group, $this->valid_groups, true ) ) {
			return $group;
		}

		return null;
	}

	/**
	 * Get a cached value.
	 *
	 * @since    1.0.0
	 * @param    string $key        Cache key.
	 * @param    mixed  $default    Default value if not found.
	 * @return   mixed                 Cached value or default.
	 */
	public function get( string $key, mixed $default = null ): mixed {
		if ( ! $this->enabled ) {
			return $default;
		}

		$cache_key = $this->get_cache_key( $key );

		// Try object cache first (if available)
		if ( wp_using_ext_object_cache() ) {
			$value = wp_cache_get( $cache_key, 'scd' );
			if ( false !== $value ) {
				return $value;
			}
		}

		// Try transient cache (primary storage)
		$value = get_transient( $cache_key );
		if ( false !== $value ) {
			// Populate object cache for next request (if available)
			if ( wp_using_ext_object_cache() ) {
				wp_cache_set( $cache_key, $value, 'scd', $this->default_expiration );
			}
			return $value;
		}

		return $default;
	}

	/**
	 * Set a cached value.
	 *
	 * @since    1.0.0
	 * @param    string $key          Cache key.
	 * @param    mixed  $value        Value to cache.
	 * @param    int    $expiration   Expiration time in seconds.
	 * @return   bool                    True on success, false on failure.
	 */
	public function set( string $key, mixed $value, int $expiration = 0 ): bool {
		if ( ! $this->enabled ) {
			return false;
		}

		// Validate key follows naming convention
		$this->validate_key( $key );

		if ( 0 === $expiration ) {
			$expiration = $this->default_expiration;
		}

		$cache_key = $this->get_cache_key( $key );

		// Set in transient (primary storage)
		$result = set_transient( $cache_key, $value, $expiration );

		// Also set in object cache if available (for fast access)
		if ( wp_using_ext_object_cache() ) {
			wp_cache_set( $cache_key, $value, 'scd', $expiration );
		}

		return $result;
	}

	/**
	 * Delete a cached value.
	 *
	 * @since    1.0.0
	 * @param    string $key    Cache key.
	 * @return   bool              True on success, false on failure.
	 */
	public function delete( string $key ): bool {
		$cache_key = $this->get_cache_key( $key );

		wp_cache_delete( $cache_key, 'scd' );

		return delete_transient( $cache_key );
	}

	/**
	 * Remember a value in cache using a callback.
	 *
	 * @since    1.0.0
	 * @param    string   $key          Cache key.
	 * @param    callable $callback     Callback to generate value.
	 * @param    int      $expiration   Expiration time in seconds.
	 * @return   mixed                     Cached or generated value.
	 */
	public function remember( string $key, callable $callback, int $expiration = 0 ): mixed {
		// Validate key follows naming convention
		$this->validate_key( $key );

		$value = $this->get( $key );

		if ( null !== $value ) {
			return $value;
		}

		$value = $callback();
		$this->set( $key, $value, $expiration );

		return $value;
	}

	/**
	 * Flush all plugin cache.
	 *
	 * @since    1.0.0
	 * @return   bool    True on success, false on failure.
	 */
	public function flush(): bool {
		global $wpdb;

		wp_cache_flush_group( 'scd' );

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				'_transient_' . $this->cache_prefix . '%',
				'_transient_timeout_' . $this->cache_prefix . '%'
			)
		);

		return true;
	}

	/**
	 * Get cache statistics.
	 *
	 * @since    1.0.0
	 * @return   array    Cache statistics.
	 */
	public function get_stats(): array {
		global $wpdb;

		$transient_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
				'_transient_' . $this->cache_prefix . '%'
			)
		);

		return array(
			'enabled'                => $this->enabled,
			'prefix'                 => $this->cache_prefix,
			'default_expiration'     => $this->default_expiration,
			'transient_count'        => (int) $transient_count,
			'object_cache_available' => wp_using_ext_object_cache(),
		);
	}

	/**
	 * Warm cache with commonly used data.
	 *
	 * Called on-demand from Tools page to pre-populate cache.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function warm_cache(): void {
		if ( ! $this->enabled ) {
			return;
		}

		// Warm campaign cache
		$this->warm_campaign_cache();

		// Warm settings cache
		$this->warm_settings_cache();

		// Warm product cache
		$this->warm_product_cache();
	}

	/**
	 * Warm campaign cache.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function warm_campaign_cache(): void {
		global $wpdb;

		$campaigns_table = $wpdb->prefix . 'scd_campaigns';

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $campaigns_table ) ) === $campaigns_table ) {
			$active_campaigns = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$campaigns_table} WHERE status = %s AND deleted_at IS NULL LIMIT %d",
					'active',
					10
				)
			);

			if ( $active_campaigns ) {
				$this->set( 'campaigns_active_campaigns', $active_campaigns, 1800 ); // 30 minutes
			}
		}
	}

	/**
	 * Warm settings cache.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function warm_settings_cache(): void {
		$settings = get_option( 'scd_settings', array() );
		if ( ! empty( $settings ) ) {
			$this->set( 'settings_plugin_settings', $settings, 3600 ); // 1 hour (settings rarely change)
		}
	}

	/**
	 * Warm product cache.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function warm_product_cache(): void {
		if ( ! function_exists( 'wc_get_products' ) ) {
			return;
		}

		$featured_products = wc_get_products(
			array(
				'status'   => 'publish',
				'featured' => true,
				'limit'    => 20,
			)
		);

		if ( $featured_products ) {
			$this->set( 'products_featured_products', $featured_products, 900 ); // 15 minutes
		}
	}

	/**
	 * Get cache key with prefix and version.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $key    Original key.
	 * @return   string            Prefixed and versioned key.
	 */
	private function get_cache_key( string $key ): string {
		return $this->cache_prefix . $this->cache_version . '_' . $key;
	}

	/**
	 * Check if caching is enabled.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   bool    True if caching is enabled.
	 */
	private function is_cache_enabled(): bool {
		$settings = get_option( 'scd_settings', array() );
		return $settings['general']['cache_enabled'] ?? true;
	}

	/**
	 * Set cache prefix.
	 *
	 * @since    1.0.0
	 * @param    string $prefix    Cache prefix.
	 * @return   void
	 */
	public function set_cache_prefix( string $prefix ): void {
		$this->cache_prefix = $prefix;
	}

	/**
	 * Set default expiration time.
	 *
	 * @since    1.0.0
	 * @param    int $expiration    Expiration time in seconds.
	 * @return   void
	 */
	public function set_default_expiration( int $expiration ): void {
		$this->default_expiration = $expiration;
	}

	/**
	 * Enable or disable caching.
	 *
	 * @since    1.0.0
	 * @param    bool $enabled    Whether to enable caching.
	 * @return   void
	 */
	public function set_enabled( bool $enabled ): void {
		$this->enabled = $enabled;
	}

	/**
	 * Check if a cache key exists.
	 *
	 * @since    1.0.0
	 * @param    string $key    Cache key.
	 * @return   bool              True if key exists.
	 */
	public function has( string $key ): bool {
		return $this->get( $key ) !== null;
	}

	/**
	 * Increment a cached numeric value.
	 *
	 * @since    1.0.0
	 * @param    string $key      Cache key.
	 * @param    int    $offset   Increment offset.
	 * @return   int|false           New value or false on failure.
	 */
	public function increment( string $key, int $offset = 1 ): int|false {
		$value = $this->get( $key, 0 );

		if ( ! is_numeric( $value ) ) {
			return false;
		}

		$new_value = (int) $value + $offset;

		if ( $this->set( $key, $new_value ) ) {
			return $new_value;
		}

		return false;
	}

	/**
	 * Decrement a cached numeric value.
	 *
	 * @since    1.0.0
	 * @param    string $key      Cache key.
	 * @param    int    $offset   Decrement offset.
	 * @return   int|false           New value or false on failure.
	 */
	public function decrement( string $key, int $offset = 1 ): int|false {
		return $this->increment( $key, -$offset );
	}

	/**
	 * Warm up cache with multiple keys.
	 *
	 * @since    1.0.0
	 * @param    array  $cache_keys    Array of cache keys and their callbacks.
	 * @param    string $group         Optional cache group for logging.
	 * @return   int                      Number of keys warmed.
	 */
	public function warm_up( array $cache_keys, string $group = '' ): int {
		if ( ! $this->enabled ) {
			return 0;
		}

		$warmed = 0;

		foreach ( $cache_keys as $key => $callback ) {
			if ( ! is_callable( $callback ) ) {
				continue;
			}

			try {
				$this->remember( $key, $callback );
				++$warmed;
			} catch ( Exception $e ) {
				// Log error but continue warming other keys
				continue;
			}
		}

		return $warmed;
	}

	/**
	 * Delete all cache entries in a group.
	 *
	 * @since    1.0.0
	 * @param    string $group    Cache group name.
	 * @return   bool                True on success.
	 */
	public function delete_group( string $group ): bool {
		global $wpdb;

		// Clear object cache group
		if ( wp_using_ext_object_cache() ) {
			wp_cache_flush_group( 'scd_' . $group );
		}

		// Clear transients matching group pattern
		$pattern = $this->cache_prefix . $this->cache_version . '_' . $group . '%';

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options}
				WHERE option_name LIKE %s
				OR option_name LIKE %s",
				'_transient_' . $pattern,
				'_transient_timeout_' . $pattern
			)
		);

		return true;
	}

	/**
	 * Get current cache version.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   string    Cache version.
	 */
	private function get_cache_version(): string {
		$version = get_option( 'scd_cache_version', '' );

		if ( empty( $version ) ) {
			$version = 'v1';
			update_option( 'scd_cache_version', $version, false );
		}

		return $version;
	}

	/**
	 * Bump cache version to invalidate all caches.
	 *
	 * @since    1.0.0
	 * @return   bool    True on success.
	 */
	public function bump_cache_version(): bool {
		$new_version = 'v' . time();
		$result      = update_option( 'scd_cache_version', $new_version, false );

		if ( $result ) {
			$this->cache_version = $new_version;

			// Clear object cache immediately
			if ( wp_using_ext_object_cache() ) {
				wp_cache_flush_group( 'scd' );
			}
		}

		return $result;
	}

	/**
	 * Invalidate campaign-related caches.
	 *
	 * Call this when:
	 * - Campaign is created/updated/deleted
	 * - Campaign status changes
	 * - Campaign is compiled
	 *
	 * @since    1.0.0
	 * @param    int|null $campaign_id    Specific campaign ID (optional).
	 * @return   void
	 */
	public function invalidate_campaign( ?int $campaign_id = null ): void {
		// Always clear the campaigns group
		$this->delete_group( 'campaigns' );

		// If specific campaign, also clear product lookups
		// (products may have been added/removed from this campaign)
		if ( $campaign_id ) {
			$this->delete_group( 'products' );
		}

		// Log invalidation for debugging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log(
				sprintf(
					'[SCD Cache] Invalidated campaign cache%s',
					$campaign_id ? " for campaign {$campaign_id}" : ''
				)
			);
		}
	}

	/**
	 * Invalidate product-related caches.
	 *
	 * Call this when:
	 * - Product is updated
	 * - Product price changes
	 * - Product categories/tags change
	 *
	 * @since    1.0.0
	 * @param    int|null $product_id    Specific product ID (optional).
	 * @return   void
	 */
	public function invalidate_product( ?int $product_id = null ): void {
		if ( $product_id ) {
			// Clear specific product discount lookups
			$this->delete( $this->products_key( 'active_campaigns_' . $product_id ) );
			$this->delete( $this->products_key( 'discount_info_' . $product_id ) );

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( "[SCD Cache] Invalidated cache for product {$product_id}" );
			}
		} else {
			// Clear entire products group
			$this->delete_group( 'products' );

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[SCD Cache] Invalidated all product caches' );
			}
		}
	}

	/**
	 * Invalidate analytics-related caches.
	 *
	 * Call this when:
	 * - Analytics data is updated
	 * - Reports are regenerated
	 * - Activity logs change
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function invalidate_analytics(): void {
		$this->delete_group( 'analytics' );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[SCD Cache] Invalidated analytics cache' );
		}
	}

	/**
	 * Invalidate all plugin caches.
	 *
	 * Call this when:
	 * - Running migrations
	 * - Major plugin updates
	 * - Manual cache flush requested
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function invalidate_all(): void {
		$this->flush();

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[SCD Cache] Flushed all plugin caches' );
		}
	}
}
