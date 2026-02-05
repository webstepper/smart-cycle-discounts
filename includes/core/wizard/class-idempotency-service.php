<?php
/**
 * Idempotency Service Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/wizard/class-idempotency-service.php
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
 * Idempotency Service Class
 *
 * Provides idempotency guarantees for wizard save operations:
 * - Prevents duplicate request execution
 * - Caches responses for identical requests
 * - Atomic request claiming to avoid race conditions
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/wizard
 */
class WSSCD_Idempotency_Service {

	/**
	 * Cache duration for idempotent responses (10 minutes).
	 *
	 * @since    1.0.0
	 * @var      int
	 */
	const CACHE_TTL = 600;

	/**
	 * Lock duration for request processing (30 seconds).
	 *
	 * @since    1.0.0
	 * @var      int
	 */
	const LOCK_TTL = 30;

	/**
	 * State service instance for session access.
	 *
	 * @since    1.0.0
	 * @var      WSSCD_Wizard_State_Service|null
	 */
	private $state_service;

	/**
	 * Cache manager instance.
	 *
	 * @since    1.0.0
	 * @var      WSSCD_Cache_Manager
	 */
	private WSSCD_Cache_Manager $cache;

	/**
	 * Constructor.
	 *
	 * @since    1.0.0
	 * @param    WSSCD_Wizard_State_Service|null $state_service    State service instance.
	 */
	public function __construct( WSSCD_Cache_Manager $cache, $state_service = null ) {
		$this->cache         = $cache;
		$this->state_service = $state_service;
	}

	/**
	 * Generate idempotency key for request.
	 *
	 * Creates cryptographically secure key from request parameters.
	 * Supports both client-provided keys and auto-generation.
	 *
	 * @since    1.0.0
	 * @param    string $step        Step name.
	 * @param    array  $data        Step data.
	 * @param    int    $user_id     User ID.
	 * @return   string                 Idempotency key.
	 */
	public function generate_key( $step, $data, $user_id ) {
		$client_key = isset( $_SERVER['HTTP_X_IDEMPOTENCY_KEY'] )
			? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_IDEMPOTENCY_KEY'] ) )
			: null;

		if ( $client_key && preg_match( '/^[a-zA-Z0-9_-]{32,64}$/', $client_key ) ) {
			return $client_key;
		}

		// Generate key from request parameters
		return $this->generate_key_from_params( $step, $data, $user_id );
	}

	/**
	 * Generate idempotency key from parameters.
	 *
	 * @since    1.0.0
	 * @param    string $step        Step name.
	 * @param    array  $data        Step data.
	 * @param    int    $user_id     User ID.
	 * @return   string                 Generated key.
	 */
	private function generate_key_from_params( $step, $data, $user_id ) {
		$session_id = $this->get_session_id();

		// Use HMAC with WordPress salt for cryptographic security
		$secret    = wp_salt( 'nonce' );
		$data_json = wp_json_encode( $data );
		$data_hash = hash_hmac( 'sha256', $data_json, $secret );

		// Use 1-minute buckets to prevent indefinite caching
		$timestamp = floor( time() / 60 );

		return sprintf(
			'wsscd_save_%s_%s_%d_%s_%d',
			$step,
			substr( $data_hash, 0, 16 ),
			$user_id,
			substr( $session_id, 0, 8 ),
			$timestamp
		);
	}

	/**
	 * Get session ID from state service.
	 *
	 * @since    1.0.0
	 * @return   string    Session ID or random string if unavailable.
	 */
	private function get_session_id() {
		if ( $this->state_service && $this->state_service->get_session_id() ) {
			return $this->state_service->get_session_id();
		}

		// Fallback: generate random session ID
		return wp_generate_password( 32, false );
	}

	/**
	 * Get cached response for idempotency key.
	 *
	 * @since    1.0.0
	 * @param    string $key    Idempotency key.
	 * @return   array|null        Cached response or null.
	 */
	public function get_cached_response( $key ) {
		if ( empty( $key ) ) {
			return null;
		}

		$cache_key = $this->get_cache_key( $key );
		$cached    = $this->cache->get( $cache_key );

		// Validate cached response has required fields for save_step responses.
		// This prevents returning incomplete/corrupted cached responses.
		if ( $cached && is_array( $cached ) ) {
			// A valid save_step response must have message, step, and next_step.
			if ( ! isset( $cached['message'] ) || ! isset( $cached['step'] ) ) {
				// Invalid/incomplete cached response - delete and return null.
				$this->cache->delete( $cache_key );
				return null;
			}
		}

		return $cached ? $cached : null;
	}

	/**
	 * Cache response for idempotency key.
	 *
	 * @since    1.0.0
	 * @param    string $key         Idempotency key.
	 * @param    array  $response    Response to cache.
	 * @param    int    $ttl         Time to live in seconds.
	 * @return   bool                   Success status.
	 */
	public function cache_response( $key, $response, $ttl = null ) {
		if ( empty( $key ) ) {
			return false;
		}

		if ( null === $ttl ) {
			$ttl = self::CACHE_TTL;
		}

		$cache_key = $this->get_cache_key( $key );
		return $this->cache->set( $cache_key, $response, $ttl );
	}

	/**
	 * Claim request atomically to prevent concurrent execution.
	 *
	 * Uses wp_cache_add for atomic operation - only succeeds if key doesn't exist.
	 *
	 * @since    1.0.0
	 * @param    string $key    Idempotency key.
	 * @return   true|WP_Error|array    True if claimed, WP_Error if duplicate, array if completed.
	 */
	public function claim_request( $key ) {
		if ( empty( $key ) ) {
			return true;
		}

		// Processing lock key (used with wp_cache_add for atomic operations)
		// Note: This bypasses Cache Manager for atomic locking, so uses wsscd_ prefix directly
		$processing_key = 'wsscd_idem_proc_' . md5( $key );

		// Try to claim request atomically
		// wp_cache_add only succeeds if key does not exist
		$claimed = wp_cache_add( $processing_key, time(), '', self::LOCK_TTL );

		if ( $claimed ) {
			// Successfully claimed - this request will process
			return true;
		}

		// Another request is already processing - check if result exists
		$result = $this->get_cached_response( $key );
		if ( $result ) {
			// Another request completed successfully - return cached result
			return $result;
		}

		// Request is still processing - return 409 Conflict
		// Client will retry with exponential backoff (handled by AJAX service)
		return new WP_Error(
			'duplicate_request',
			__( 'Duplicate request detected. Please wait.', 'smart-cycle-discounts' ),
			array(
				'status'      => 409,
				'retry_after' => 2, // Suggest retry after 2 seconds
			)
		);
	}

	/**
	 * Clear cached response for key.
	 *
	 * @since    1.0.0
	 * @param    string $key    Idempotency key.
	 * @return   bool              Success status.
	 */
	public function clear_cached_response( $key ) {
		if ( empty( $key ) ) {
			return false;
		}

		$cache_key = $this->get_cache_key( $key );
		return $this->cache->delete( $cache_key );
	}

	/**
	 * Get cache key for idempotency key.
	 *
	 * Uses reference_ prefix for Cache Manager compatibility.
	 *
	 * @since    1.0.0
	 * @param    string $key    Idempotency key.
	 * @return   string            Cache key.
	 */
	private function get_cache_key( $key ) {
		// Use reference_ prefix for proper cache key validation
		return 'reference_idem_' . md5( $key );
	}
}
