<?php
/**
 * Rate Limiter Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/security/class-rate-limiter.php
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
 * Rate Limiter
 *
 * Handles rate limiting for the plugin.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/security
 * @author     Webstepper <contact@webstepper.io>
 */
class WSSCD_Rate_Limiter {

	/**
	 * Cache manager instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Cache_Manager    $cache_manager    Cache manager.
	 */
	private WSSCD_Cache_Manager $cache_manager;

	/**
	 * Initialize the rate limiter.
	 *
	 * @since    1.0.0
	 * @param    WSSCD_Cache_Manager $cache_manager    Cache manager.
	 */
	public function __construct( WSSCD_Cache_Manager $cache_manager ) {
		$this->cache_manager = $cache_manager;
	}

	/**
	 * Check if action is rate limited.
	 *
	 * @since    1.0.0
	 * @param    string $key       Rate limit key.
	 * @param    int    $limit     Request limit.
	 * @param    int    $window    Time window in seconds.
	 * @return   bool                 True if rate limited.
	 */
	public function is_limited( string $key, int $limit = 60, int $window = 3600 ): bool {
		$cache_key     = 'rate_limit_' . md5( $key );
		$current_count = $this->cache_manager->get( $cache_key, 0 );

		if ( $current_count >= $limit ) {
			return true;
		}

		$this->cache_manager->set( $cache_key, $current_count + 1, $window );
		return false;
	}

	/**
	 * Get remaining requests.
	 *
	 * @since    1.0.0
	 * @param    string $key       Rate limit key.
	 * @param    int    $limit     Request limit.
	 * @return   int                  Remaining requests.
	 */
	public function get_remaining( string $key, int $limit = 60 ): int {
		$cache_key     = 'rate_limit_' . md5( $key );
		$current_count = $this->cache_manager->get( $cache_key, 0 );

		return max( 0, $limit - $current_count );
	}

	/**
	 * Reset rate limit for key.
	 *
	 * @since    1.0.0
	 * @param    string $key    Rate limit key.
	 * @return   void
	 */
	public function reset( string $key ): void {
		$cache_key = 'rate_limit_' . md5( $key );
		$this->cache_manager->delete( $cache_key );
	}
}
