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
class WSSCD_Cache_Manager {

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
		'settings',
		'wsscd_dashboard',
	);

	/**
	 * Cache prefix for all plugin cache keys.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $cache_prefix    Cache prefix.
	 */
	private string $cache_prefix = 'wsscd_';

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
		$this->register_hooks();
	}

	/**
	 * Register cache invalidation hooks.
	 *
	 * Automatically invalidates caches when campaigns change status.
	 * This ensures expired campaigns stop applying discounts immediately.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function register_hooks(): void {
		// Invalidate cache when campaigns expire (CRITICAL for stopping discounts)
		add_action( 'wsscd_campaign_expired', array( $this, 'on_campaign_status_changed' ), 10, 1 );

		// Invalidate cache when campaigns are activated
		add_action( 'wsscd_campaign_activated', array( $this, 'on_campaign_status_changed' ), 10, 1 );

		// Invalidate cache when campaign status changes (covers deactivation, pause, resume, etc.)
		add_action( 'wsscd_campaign_status_changed', array( $this, 'on_campaign_status_changed' ), 10, 1 );

		// Invalidate cache when campaigns are created/updated/deleted
		add_action( 'wsscd_campaign_created', array( $this, 'on_campaign_status_changed' ), 10, 1 );
		add_action( 'wsscd_campaign_updated', array( $this, 'on_campaign_status_changed' ), 10, 1 );
		add_action( 'wsscd_campaign_deleted', array( $this, 'on_campaign_status_changed' ), 10, 1 );

		// WooCommerce settings changes - invalidate all caches when critical settings change
		add_action( 'update_option_woocommerce_currency', array( $this, 'on_woocommerce_settings_changed' ), 10, 0 );
		add_action( 'update_option_woocommerce_price_thousand_sep', array( $this, 'on_woocommerce_settings_changed' ), 10, 0 );
		add_action( 'update_option_woocommerce_price_decimal_sep', array( $this, 'on_woocommerce_settings_changed' ), 10, 0 );
		add_action( 'update_option_woocommerce_price_num_decimals', array( $this, 'on_woocommerce_settings_changed' ), 10, 0 );
		add_action( 'update_option_woocommerce_tax_display_shop', array( $this, 'on_woocommerce_settings_changed' ), 10, 0 );
		add_action( 'update_option_woocommerce_tax_display_cart', array( $this, 'on_woocommerce_settings_changed' ), 10, 0 );
		add_action( 'update_option_woocommerce_prices_include_tax', array( $this, 'on_woocommerce_settings_changed' ), 10, 0 );
		add_action( 'update_option_woocommerce_calc_taxes', array( $this, 'on_woocommerce_settings_changed' ), 10, 0 );

		// Plugin-specific settings changes
		add_action( 'wsscd_settings_updated', array( $this, 'on_plugin_settings_changed' ), 10, 0 );
		add_action( 'wsscd_license_activated', array( $this, 'on_plugin_settings_changed' ), 10, 0 );
		add_action( 'wsscd_license_deactivated', array( $this, 'on_plugin_settings_changed' ), 10, 0 );

		// Currency change event from currency change service
		add_action( 'wsscd_currency_changed', array( $this, 'on_currency_changed' ), 10, 3 );

		// Bulk campaign operations - comprehensive cache invalidation
		add_action( 'wsscd_campaigns_bulk_activated', array( $this, 'on_bulk_campaign_operation' ), 10, 1 );
		add_action( 'wsscd_campaigns_bulk_paused', array( $this, 'on_bulk_campaign_operation' ), 10, 1 );
		add_action( 'wsscd_campaigns_bulk_deleted', array( $this, 'on_bulk_campaign_operation' ), 10, 1 );

		// Wizard session cleanup - clear product caches when wizard completes
		add_action( 'wsscd_campaign_created_from_wizard', array( $this, 'on_wizard_session_complete' ), 10, 1 );
		add_action( 'wsscd_campaign_updated_from_wizard', array( $this, 'on_wizard_session_complete' ), 10, 1 );
		add_action( 'wsscd_wizard_session_cancelled', array( $this, 'on_wizard_session_cancelled' ), 10, 1 );
		add_action( 'wsscd_wizard_session_expired', array( $this, 'on_wizard_session_cancelled' ), 10, 1 );
	}

	/**
	 * Handle bulk campaign operations.
	 *
	 * Performs comprehensive cache invalidation after bulk activate/pause/delete.
	 *
	 * @since    1.0.0
	 * @param    array $campaign_ids    Array of affected campaign IDs.
	 * @return   void
	 */
	public function on_bulk_campaign_operation( array $campaign_ids ): void {
		// Full invalidation for bulk operations
		$this->invalidate_campaign();
		$this->invalidate_analytics();

		// Clear specific caches for each affected campaign
		foreach ( $campaign_ids as $campaign_id ) {
			$this->delete( $this->campaigns_key( 'stats_' . $campaign_id ) );
			$this->delete( $this->campaigns_key( 'exists_' . $campaign_id ) );
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional debug logging when WP_DEBUG is enabled.
			error_log( sprintf(
				'[WSSCD Cache] Bulk campaign operation - invalidated caches for %d campaigns',
				count( $campaign_ids )
			) );
		}
	}

	/**
	 * Handle wizard session completion.
	 *
	 * Clears wizard-specific product caches (all product IDs, product lookups)
	 * that were populated during the wizard session.
	 *
	 * @since    1.0.0
	 * @param    int $campaign_id    The created/updated campaign ID.
	 * @return   void
	 */
	public function on_wizard_session_complete( int $campaign_id ): void {
		// Clear wizard-specific product caches
		$this->delete( $this->products_key( 'all_ids' ) );
		$this->delete( $this->products_key( 'all_products' ) );

		// Clear reference data used during wizard
		$this->delete_group( 'reference' );

		// Invalidate the campaign to ensure fresh data
		$this->invalidate_campaign( $campaign_id );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional debug logging when WP_DEBUG is enabled.
			error_log( sprintf(
				'[WSSCD Cache] Wizard session complete for campaign %d - cleared wizard caches',
				$campaign_id
			) );
		}
	}

	/**
	 * Handle wizard session cancellation or expiration.
	 *
	 * Clears any cached data from abandoned wizard sessions to prevent
	 * stale product lists from affecting future sessions.
	 *
	 * @since    1.0.0
	 * @param    string|int $session_id    The wizard session ID or campaign ID.
	 * @return   void
	 */
	public function on_wizard_session_cancelled( $session_id ): void {
		// Clear wizard-specific product caches
		$this->delete( $this->products_key( 'all_ids' ) );
		$this->delete( $this->products_key( 'all_products' ) );

		// Clear reference data that might be stale
		$this->delete_group( 'reference' );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional debug logging when WP_DEBUG is enabled.
			error_log( sprintf(
				'[WSSCD Cache] Wizard session cancelled/expired (session: %s) - cleared wizard caches',
				$session_id
			) );
		}
	}

	/**
	 * Handle WooCommerce settings changes.
	 *
	 * Invalidates all caches when critical WooCommerce settings change
	 * (currency, tax, price formatting) as these affect discount calculations.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function on_woocommerce_settings_changed(): void {
		$this->invalidate_all();

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional debug logging when WP_DEBUG is enabled.
			error_log( '[WSSCD Cache] WooCommerce settings changed - all caches invalidated' );
		}
	}

	/**
	 * Handle plugin settings changes.
	 *
	 * Invalidates all caches when plugin settings or license status changes.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function on_plugin_settings_changed(): void {
		$this->invalidate_all();

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional debug logging when WP_DEBUG is enabled.
			error_log( '[WSSCD Cache] Plugin settings changed - all caches invalidated' );
		}
	}

	/**
	 * Handle currency change event.
	 *
	 * Invalidates all campaign and analytics caches when store currency changes.
	 *
	 * @since    1.0.0
	 * @param    string $old_currency   Old currency code.
	 * @param    string $new_currency   New currency code.
	 * @param    int    $paused_count   Number of campaigns paused.
	 * @return   void
	 */
	public function on_currency_changed( string $old_currency, string $new_currency, int $paused_count ): void {
		// Full cache invalidation since all monetary values are affected
		$this->invalidate_all();

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional debug logging when WP_DEBUG is enabled.
			error_log( sprintf(
				'[WSSCD Cache] Currency changed from %s to %s - all caches invalidated',
				$old_currency,
				$new_currency
			) );
		}
	}

	/**
	 * Handle campaign status change by invalidating ALL relevant caches.
	 *
	 * This is called for any campaign lifecycle event (create, update, delete,
	 * activate, deactivate, expire). It performs comprehensive cache invalidation
	 * to ensure no stale data remains.
	 *
	 * @since    1.0.0
	 * @param    WSSCD_Campaign|int $campaign    Campaign object or ID.
	 * @return   void
	 */
	public function on_campaign_status_changed( $campaign ): void {
		$campaign_id = is_object( $campaign ) ? $campaign->get_id() : (int) $campaign;

		// Comprehensive invalidation - clear all related caches
		$this->invalidate_campaign( $campaign_id );

		// Also invalidate analytics since campaign changes affect reporting
		$this->invalidate_analytics();

		// Clear specific campaign caches that use direct keys (not groups)
		if ( $campaign_id ) {
			$this->delete( $this->campaigns_key( 'stats_' . $campaign_id ) );
			$this->delete( $this->campaigns_key( 'exists_' . $campaign_id ) );
		}
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
		$duration                 = apply_filters( 'wsscd_cache_duration', 3600 );
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
	 * Build a properly-formatted cache key for settings group.
	 *
	 * @since    1.0.0
	 * @param    string $suffix    Key suffix.
	 * @return   string               Properly-formatted cache key.
	 */
	public function settings_key( string $suffix ): string {
		return 'settings_' . $suffix;
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
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional debug logging when WP_DEBUG is enabled.
			error_log(
				sprintf(
					'[WSSCD Cache] WARNING: Invalid cache key "%s" - must start with one of: %s',
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
	public function get( string $key, $default = null ) {
		if ( ! $this->enabled ) {
			return $default;
		}

		$cache_key = $this->get_cache_key( $key );

		// Try object cache first (if available)
		if ( wp_using_ext_object_cache() ) {
			$value = wp_cache_get( $cache_key, 'wsscd' );
			if ( false !== $value ) {
				return $value;
			}
		}

		// Try transient cache (primary storage)
		$value = get_transient( $cache_key );
		if ( false !== $value ) {
			// Populate object cache for next request (if available)
			if ( wp_using_ext_object_cache() ) {
				wp_cache_set( $cache_key, $value, 'wsscd', $this->default_expiration );
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
	public function set( string $key, $value, int $expiration = 0 ): bool {
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
			wp_cache_set( $cache_key, $value, 'wsscd', $expiration );
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

		wp_cache_delete( $cache_key, 'wsscd' );

		return delete_transient( $cache_key );
	}

	/**
	 * Delete cached values by pattern.
	 *
	 * Uses SQL LIKE pattern matching to delete multiple cache keys.
	 * Pattern should use * as wildcard (converted to % for SQL).
	 *
	 * @since    1.0.0
	 * @param    string $pattern    Cache key pattern (e.g., 'analytics_metrics_*').
	 * @return   int                   Number of keys deleted.
	 */
	public function delete_by_pattern( string $pattern ): int {
		global $wpdb;

		// Convert wildcard pattern to SQL LIKE pattern
		$sql_pattern = str_replace( '*', '%', $pattern );

		// Build full cache key pattern (same format as get_cache_key)
		// Format: prefix + version + _ + key
		$full_pattern = $this->cache_prefix . $this->cache_version . '_' . $sql_pattern;

		// Flush object cache group (no pattern support, but helps)
		wp_cache_flush_group( 'wsscd' );

		// Query for matching transient names first
		// We need to use delete_transient() to properly clear WordPress's in-memory cache
		$transient_prefix = '_transient_';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching , PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Pattern-based transient lookup has no WP abstraction.
		$transient_names  = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options}
				WHERE option_name LIKE %s",
				$transient_prefix . $full_pattern
			)
		);

		$deleted = 0;

		// Delete each transient properly (clears both DB and object cache)
		foreach ( $transient_names as $option_name ) {
			// Extract transient name by removing '_transient_' prefix
			$transient_name = substr( $option_name, strlen( $transient_prefix ) );
			if ( delete_transient( $transient_name ) ) {
				++$deleted;
			}
		}

		return $deleted;
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
	public function remember( string $key, callable $callback, int $expiration = 0 ) {
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

		wp_cache_flush_group( 'wsscd' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching , PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Bulk transient deletion for cache flush; no WP abstraction for pattern-based delete.
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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching , PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Admin stats query; transient count lookup has no WP abstraction.
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
		$settings = get_option( 'wsscd_settings', array() );
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
	public function increment( string $key, int $offset = 1 ) {
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
	public function decrement( string $key, int $offset = 1 ) {
		return $this->increment( $key, -$offset );
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
			wp_cache_flush_group( 'wsscd_' . $group );
		}

		// Clear transients matching group pattern
		$pattern = $this->cache_prefix . $this->cache_version . '_' . $group . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching , PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Bulk transient deletion for cache group; no WP abstraction for pattern-based delete.
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
	 * Delete all cached items matching a key prefix.
	 *
	 * @since    1.0.0
	 * @param    string $prefix    Cache key prefix to match.
	 * @return   bool                 True on success.
	 */
	public function delete_by_prefix( string $prefix ): bool {
		global $wpdb;

		// Build the pattern for transient deletion
		$pattern = $this->cache_prefix . $this->cache_version . '_' . $prefix . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Bulk transient deletion; no WP abstraction for pattern-based delete.
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
		$version = get_option( 'wsscd_cache_version', '' );

		if ( empty( $version ) ) {
			$version = 'v1';
			update_option( 'wsscd_cache_version', $version, false );
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
		$result      = update_option( 'wsscd_cache_version', $new_version, false );

		if ( $result ) {
			$this->cache_version = $new_version;

			// Clear object cache immediately
			if ( wp_using_ext_object_cache() ) {
				wp_cache_flush_group( 'wsscd' );
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

		// Always clear products group - any campaign change can affect product discounts
		// (products may have been added/removed, conditions changed, discount values updated)
		$this->delete_group( 'products' );

		// Clear reference data cache since campaign changes may affect category/product counts
		$this->delete_group( 'reference' );

		// Clear dashboard cache - Dashboard Service is lazy-loaded so its own
		// invalidation hooks may not be registered during non-dashboard AJAX requests
		$this->delete_group( 'wsscd_dashboard' );

		// Log invalidation for debugging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional debug logging when WP_DEBUG is enabled.
			error_log(
				sprintf(
					'[WSSCD Cache] Invalidated campaign cache%s',
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
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional debug logging when WP_DEBUG is enabled.
				error_log( "[WSSCD Cache] Invalidated cache for product {$product_id}" );
			}
		} else {
			// Clear entire products group
			$this->delete_group( 'products' );

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional debug logging when WP_DEBUG is enabled.
				error_log( '[WSSCD Cache] Invalidated all product caches' );
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
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional debug logging when WP_DEBUG is enabled.
			error_log( '[WSSCD Cache] Invalidated analytics cache' );
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
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional debug logging when WP_DEBUG is enabled.
			error_log( '[WSSCD Cache] Flushed all plugin caches' );
		}
	}
}
