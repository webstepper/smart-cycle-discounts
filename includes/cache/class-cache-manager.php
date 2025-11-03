<?php
/**
 * Cache Manager Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/cache/class-cache-manager.php
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 */

declare(strict_types=1);

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
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Cache_Manager {

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
	private int $default_expiration = 3600; // 1 hour

	/**
	 * Whether caching is enabled.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      bool    $enabled    Cache enabled status.
	 */
	private bool $enabled = true;

	/**
	 * Initialize the cache manager.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		$this->enabled = $this->is_cache_enabled();
		$this->load_settings();
	}

	/**
	 * Load cache settings from database.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function load_settings(): void {
		$settings = get_option( 'scd_settings', array() );

		if ( isset( $settings['performance']['campaign_cache_duration'] ) ) {
			$this->default_expiration = (int) $settings['performance']['campaign_cache_duration'];
		}
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

		// Try object cache first
		$value = wp_cache_get( $cache_key, 'scd' );
		if ( $value !== false ) {
			return $value;
		}

		// Try transient cache
		$value = get_transient( $cache_key );
		if ( $value !== false ) {
			// Store in object cache for faster access
			wp_cache_set( $cache_key, $value, 'scd', $this->default_expiration );
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

		if ( $expiration === 0 ) {
			$expiration = $this->default_expiration;
		}

		$cache_key = $this->get_cache_key( $key );

		// Set in object cache
		wp_cache_set( $cache_key, $value, 'scd', $expiration );

		// Set in transient cache for persistence
		return set_transient( $cache_key, $value, $expiration );
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

		// Delete from object cache
		wp_cache_delete( $cache_key, 'scd' );

		// Delete from transient cache
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
		$value = $this->get( $key );

		if ( $value !== null ) {
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

		// Clear object cache group
		wp_cache_flush_group( 'scd' );

		// Clear transients
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
	 * @since    1.0.0
	 * @return   void
	 */
	public function warm_cache(): void {
		if ( ! $this->enabled ) {
			return;
		}

		// Check if cache warming is enabled
		$settings = get_option( 'scd_settings', array() );
		if ( ! isset( $settings['performance']['enable_cache_warming'] ) || ! $settings['performance']['enable_cache_warming'] ) {
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
				"SELECT * FROM $campaigns_table WHERE status = 'active' AND deleted_at IS NULL LIMIT 10"
			);

			if ( $active_campaigns ) {
				// Use campaign cache duration from settings
				$settings = get_option( 'scd_settings', array() );
				$duration = isset( $settings['performance']['campaign_cache_duration'] )
					? (int) $settings['performance']['campaign_cache_duration']
					: 3600;
				$this->set( 'active_campaigns', $active_campaigns, $duration );
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
			$this->set( 'plugin_settings', $settings, 7200 ); // 2 hours
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
			// Use product cache duration from settings
			$settings = get_option( 'scd_settings', array() );
			$duration = isset( $settings['performance']['product_cache_duration'] )
				? (int) $settings['performance']['product_cache_duration']
				: 3600;
			$this->set( 'featured_products', $featured_products, $duration );
		}
	}

	/**
	 * Get cache key with prefix.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $key    Original key.
	 * @return   string            Prefixed key.
	 */
	private function get_cache_key( string $key ): string {
		return $this->cache_prefix . $key;
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
}
