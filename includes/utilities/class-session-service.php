<?php
/**
 * Session Service Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/utilities/class-session-service.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


// Include required files
require_once __DIR__ . '/class-session-lock-service.php';

/**
 * Session Service Class
 *
 * Manages wizard data across steps using WordPress transients.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/services
 * @author     Webstepper <contact@webstepper.io>
 */
class SCD_Session_Service {

	/**
	 * Session ID.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string|null    $session_id    Session ID.
	 */
	private ?string $session_id = null;

	/**
	 * Session data.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $data    Session data.
	 */
	private array $data = array();

	/**
	 * Session expiration time (in seconds).
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      int    $expiration    Session expiration.
	 */
	private int $expiration = 3600; // 1 hour

	/**
	 * Transient prefix.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $prefix    Transient prefix.
	 */
	private string $prefix = 'scd_wizard_';

	/**
	 * Maximum data size in bytes (1MB).
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      int    $max_data_size    Maximum allowed data size.
	 */
	private int $max_data_size = 1048576; // 1MB

	/**
	 * Session lock instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Session_Lock    $lock    Session lock manager.
	 */
	private SCD_Session_Lock $lock;

	/**
	 * Initialize the session manager.
	 *
	 * @since    1.0.0
	 * @param    string|null $session_id    Existing session ID.
	 */
	public function __construct( ?string $session_id = null ) {
		$this->lock = new SCD_Session_Lock();

		$GLOBALS['scd_session_lock_instance'] = $this->lock;

		if ( $session_id ) {
			$this->load( $session_id );
		}
	}

	/**
	 * Destructor to ensure locks are released.
	 *
	 * @since    1.0.0
	 */
	public function __destruct() {
		if ( $this->lock ) {
			$this->lock->release_all();
		}
	}

	/**
	 * Create new session.
	 *
	 * @since    1.0.0
	 * @return   string    Session ID.
	 */
	public function create(): string {
		$this->session_id = $this->generate_session_id();
		$initial_data     = array(
			'session_id'    => $this->session_id,
			'created_at'    => current_time( 'mysql' ),
			'last_accessed' => current_time( 'mysql' ),
			'user_id'       => get_current_user_id(),
			'ip_address'    => $this->get_client_ip(),
			'version'       => 1, // Initialize version for optimistic locking
		);

		$this->data = $this->sanitize_data( $initial_data );

		$this->save();
		return $this->session_id;
	}

	/**
	 * Load existing session.
	 *
	 * @since    1.0.0
	 * @param    string $session_id    Session ID.
	 * @return   bool                     True if loaded successfully.
	 */
	public function load( string $session_id ): bool {
		// Acquire lock before loading
		if ( ! $this->lock->acquire( 'session_' . $session_id ) ) {
			// Use debug wrapper if available, fallback to direct logging
			if ( class_exists( 'SCD_Debug' ) ) {
				SCD_Debug::warn( 'Session: Failed to acquire lock for session load', array( 'session_id' => $session_id ) );
			} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				SCD_Log::warning( 'Session: Failed to acquire lock for session load', array( 'session_id' => $session_id ) );
			}
			return false;
		}

		try {
			$transient_key = $this->get_transient_key( $session_id );
			$data          = get_transient( $transient_key );

			if ( false === $data ) {
				return false;
			}

			if ( ! $this->validate_session_ownership( $data ) ) {
				$this->cleanup_session( $session_id );
				return false;
			}

			$this->session_id = $session_id;
			$this->data = $this->sanitize_data( $data );

			if ( ! isset( $this->data['version'] ) ) {
				$this->data['version'] = 1;
			}

			$this->data['last_accessed'] = current_time( 'mysql' );
			$this->save_internal(); // Use internal save to avoid double locking

			return true;
		} finally {
			// Always release lock
			$this->lock->release( 'session_' . $session_id );
		}
	}

	/**
	 * Save session data.
	 *
	 * @since    1.0.0
	 * @return   bool|WP_Error    True if saved successfully, WP_Error on failure.
	 */
	public function save(): bool|WP_Error {
		if ( ! $this->session_id ) {
			return false;
		}

		// Acquire lock before saving
		if ( ! $this->lock->acquire( 'session_' . $this->session_id ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				SCD_Log::warning( 'Session: Failed to acquire lock for session save', array( 'session_id' => $this->session_id ) );
			}
			return false;
		}

		try {
			return $this->save_internal();
		} finally {
			// Always release lock
			$this->lock->release( 'session_' . $this->session_id );
		}
	}

	/**
	 * Internal save without locking (for use when lock is already held).
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   bool|WP_Error    True if saved successfully, WP_Error on failure.
	 */
	private function save_internal(): bool|WP_Error {
		if ( ! $this->session_id ) {
			return false;
		}

		$this->data = $this->sanitize_data( $this->data );

		$data_size = strlen( serialize( $this->data ) );
		if ( $data_size > $this->max_data_size ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				SCD_Log::warning(
					'Session: Data too large',
					array(
						'data_size'  => $data_size,
						'max_size'   => $this->max_data_size,
						'session_id' => $this->session_id,
					)
				);
			}
			return new WP_Error(
				'session_data_too_large',
				sprintf(
					__( 'The data you are trying to save is too large (%1$s). Maximum allowed size is %2$s. Please reduce the amount of data or contact support.', 'smart-cycle-discounts' ),
					size_format( $data_size ),
					size_format( $this->max_data_size )
				),
				array(
					'current_size' => $data_size,
					'max_size'     => $this->max_data_size,
					'status'       => 413,
				)
			);
		}

		$transient_key = $this->get_transient_key( $this->session_id );

		// Optimistic locking: check current version before saving
		$current_data = get_transient( $transient_key );
		if ( false !== $current_data ) {
			$current_version = $current_data['version'] ?? 0;
			$our_version     = $this->data['version'] ?? 1;

			if ( $current_version > $our_version ) {
				return new WP_Error(
					'session_version_conflict',
					__( 'The session has been modified by another process. Please reload and try again.', 'smart-cycle-discounts' ),
					array(
						'current_version' => $current_version,
						'our_version'     => $our_version,
						'status'          => 409, // Conflict
					)
				);
			}
		}

		// Increment version for this save
		$this->data['version'] = ( $this->data['version'] ?? 1 ) + 1;

		$result = set_transient( $transient_key, $this->data, $this->expiration );

		if ( ! $result ) {
			return new WP_Error(
				'session_save_failed',
				__( 'Failed to save session data. Please try again or contact support if the problem persists.', 'smart-cycle-discounts' ),
				array( 'status' => 500 )
			);
		}

		return $result;
	}

	/**
	 * Get session data value.
	 *
	 * @since    1.0.0
	 * @param    string $key        Data key.
	 * @param    mixed  $default    Default value.
	 * @return   mixed                 Data value.
	 */
	public function get( string $key, mixed $default = null ): mixed {
		return $this->data[ $key ] ?? $default;
	}

	/**
	 * Set session data value.
	 *
	 * @since    1.0.0
	 * @param    string $key      Data key.
	 * @param    mixed  $value    Data value.
	 * @return   bool|WP_Error     True on success, WP_Error on failure.
	 */
	public function set( string $key, mixed $value ): bool|WP_Error {
		if ( ! $this->session_id ) {
			return false;
		}

		// Acquire lock for atomic update
		if ( ! $this->lock->acquire( 'session_' . $this->session_id ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				SCD_Log::warning( 'Session: Failed to acquire lock for set operation', array( 'key' => $key ) );
			}
			return new WP_Error(
				'session_lock_failed',
				__( 'Unable to save data due to a temporary lock. Please try again.', 'smart-cycle-discounts' ),
				array( 'status' => 409 )
			);
		}

		try {
			// Reload data to ensure we have latest version
			$transient_key = $this->get_transient_key( $this->session_id );
			$current_data  = get_transient( $transient_key );
			if ( false !== $current_data ) {
				$this->data = $this->sanitize_data( $current_data );
			}

			$sanitized = $this->sanitize_data( array( $key => $value ) );

			$test_data         = $this->data;
			$test_data[ $key ] = $sanitized[ $key ] ?? $value;
			$data_size         = strlen( serialize( $test_data ) );

			if ( $data_size > $this->max_data_size ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					SCD_Log::warning(
						'Session: Key too large',
						array(
							'key'            => $key,
							'max_size'       => $this->max_data_size,
							'projected_size' => $data_size,
						)
					);
				}
				return new WP_Error(
					'session_data_too_large',
					sprintf(
						__( 'The data for "%1$s" is too large (%2$s). Maximum total size is %3$s. Please reduce the amount of data.', 'smart-cycle-discounts' ),
						$key,
						size_format( $data_size ),
						size_format( $this->max_data_size )
					),
					array(
						'field'        => $key,
						'current_size' => $data_size,
						'max_size'     => $this->max_data_size,
						'status'       => 413,
					)
				);
			}

			$this->data[ $key ] = $sanitized[ $key ] ?? $value;
			$save_result        = $this->save_internal();

			// If version conflict, retry once with fresh data
			if ( is_wp_error( $save_result ) && 'session_version_conflict' === $save_result->get_error_code() ) {
				// Reload latest data
				$fresh_data = get_transient( $transient_key );
				if ( false !== $fresh_data ) {
					$this->data         = $this->sanitize_data( $fresh_data );
					$this->data[ $key ] = $sanitized[ $key ] ?? $value;
					$save_result        = $this->save_internal();
				}
			}

			return $save_result;
		} finally {
			$this->lock->release( 'session_' . $this->session_id );
		}
	}

	/**
	 * Set multiple data values.
	 *
	 * @since    1.0.0
	 * @param    array $data    Data array.
	 * @return   bool|WP_Error     True on success, WP_Error on failure.
	 */
	public function set_data( array $data ): bool|WP_Error {
		if ( ! $this->session_id ) {
			return false;
		}

		// Acquire lock for atomic update
		if ( ! $this->lock->acquire( 'session_' . $this->session_id ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				SCD_Log::warning( 'Session: Failed to acquire lock for set_data operation' );
			}
			return new WP_Error(
				'session_lock_failed',
				__( 'Unable to save data due to a temporary lock. Please try again.', 'smart-cycle-discounts' ),
				array( 'status' => 409 )
			);
		}

		try {
			// Reload data to ensure we have latest version
			$transient_key = $this->get_transient_key( $this->session_id );
			$current_data  = get_transient( $transient_key );
			if ( false !== $current_data ) {
				$this->data = $this->sanitize_data( $current_data );
			}

			$sanitized_data = $this->sanitize_data( $data );

			$test_data = array_merge( $this->data, $sanitized_data );
			$data_size = strlen( serialize( $test_data ) );

			if ( $data_size > $this->max_data_size ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					SCD_Log::warning(
						'Session: Data too large for set_data',
						array(
							'max_size'       => $this->max_data_size,
							'projected_size' => $data_size,
						)
					);
				}
				return new WP_Error(
					'session_data_too_large',
					sprintf(
						__( 'The total data size (%1$s) exceeds the maximum allowed (%2$s). Please reduce the amount of data being saved.', 'smart-cycle-discounts' ),
						size_format( $data_size ),
						size_format( $this->max_data_size )
					),
					array(
						'current_size' => $data_size,
						'max_size'     => $this->max_data_size,
						'status'       => 413,
					)
				);
			}

			$this->data = $test_data;
			return $this->save_internal();
		} finally {
			$this->lock->release( 'session_' . $this->session_id );
		}
	}

	/**
	 * Get all session data.
	 *
	 * @since    1.0.0
	 * @return   array    Session data.
	 */
	public function get_all_data(): array {
		return $this->data;
	}

	/**
	 * Check if session exists.
	 *
	 * @since    1.0.0
	 * @param    string $session_id    Session ID.
	 * @return   bool                     True if exists.
	 */
	public function exists( string $session_id ): bool {
		$transient_key = $this->get_transient_key( $session_id );
		return get_transient( $transient_key ) !== false;
	}

	/**
	 * Delete session data key.
	 *
	 * @since    1.0.0
	 * @param    string $key    Data key.
	 * @return   void
	 */
	public function delete( string $key ): void {
		unset( $this->data[ $key ] );
		$this->save();
	}

	/**
	 * Clear all session data.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function clear(): void {
		$reset_data = array(
			'session_id'    => $this->session_id,
			'created_at'    => $this->data['created_at'] ?? current_time( 'mysql' ),
			'last_accessed' => current_time( 'mysql' ),
			'user_id'       => get_current_user_id(),
			'ip_address'    => $this->get_client_ip(),
		);

		$this->data = $this->sanitize_data( $reset_data );
		$this->save();
	}

	/**
	 * Cleanup session.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function cleanup(): void {
		if ( $this->session_id ) {
			$this->cleanup_session( $this->session_id );
			$this->session_id = null;
			$this->data       = array();
		}
	}

	/**
	 * Get session ID.
	 *
	 * @since    1.0.0
	 * @return   string|null    Session ID.
	 */
	public function get_session_id(): ?string {
		return $this->session_id;
	}

	/**
	 * Get session expiration info.
	 *
	 * @since    1.0.0
	 * @return   array|null    Expiration info array or null if no session.
	 */
	public function get_expiration_info(): ?array {
		if ( ! $this->session_id ) {
			return null;
		}

		// Get transient timeout to determine actual expiration time
		$transient_key     = $this->get_transient_key( $this->session_id );
		$timeout_option    = '_transient_timeout_' . $transient_key;
		$transient_timeout = get_option( $timeout_option );

		if ( false === $transient_timeout ) {
			// Transient doesn't exist or expired
			return null;
		}

		$current_time   = time();
		$expires_at     = (int) $transient_timeout;
		$time_remaining = $expires_at - $current_time;

		// Get created_at for metadata (convert MySQL datetime to timestamp)
		$created_at_str = $this->get( 'created_at' );
		$created_at     = $created_at_str ? strtotime( $created_at_str ) : $current_time;

		return array(
			'created_at'       => $created_at,
			'expires_at'       => $expires_at,
			'expiration'       => $this->expiration,
			'time_remaining'   => max( 0, $time_remaining ),
			'is_expiring_soon' => $time_remaining < 300, // Less than 5 minutes
			'is_expired'       => $time_remaining <= 0,
		);
	}

	/**
	 * Check if session is valid.
	 *
	 * @since    1.0.0
	 * @return   bool    True if valid.
	 */
	public function is_valid(): bool {
		if ( ! $this->session_id ) {
			return false;
		}

		if ( ! $this->exists( $this->session_id ) ) {
			return false;
		}

		$created_at = $this->get( 'created_at' );
		if ( $created_at ) {
			$created_time = strtotime( $created_at );
			$max_age      = 24 * 3600; // 24 hours

			if ( time() - $created_time > $max_age ) {
				$this->cleanup();
				return false;
			}
		}

		return true;
	}

	/**
	 * Extend session expiration.
	 *
	 * @since    1.0.0
	 * @param    int $seconds    Additional seconds.
	 * @return   void
	 */
	public function extend( int $seconds ): void {
		$this->expiration += $seconds;
		$this->save();
	}

	/**
	 * Get session metadata.
	 *
	 * @since    1.0.0
	 * @return   array    Session metadata.
	 */
	public function get_metadata(): array {
		return array(
			'session_id'         => $this->session_id,
			'created_at'         => $this->get( 'created_at' ),
			'last_accessed'      => $this->get( 'last_accessed' ),
			'user_id'            => $this->get( 'user_id' ),
			'ip_address'         => $this->get( 'ip_address' ),
			'expiration'         => $this->expiration,
			'data_size'          => $this->get_data_size(),
			'max_data_size'      => $this->max_data_size,
			'data_usage_percent' => round( ( $this->get_data_size() / $this->max_data_size ) * 100, 2 ),
		);
	}

	/**
	 * Get current data size in bytes.
	 *
	 * @since    1.0.0
	 * @return   int    Data size in bytes.
	 */
	public function get_data_size(): int {
		return strlen( serialize( $this->data ) );
	}

	/**
	 * Schedule automatic session cleanup.
	 *
	 * @since    1.0.0
	 * @static
	 * @return   void
	 */
	public static function schedule_cleanup(): void {
		if ( ! wp_next_scheduled( 'scd_cleanup_wizard_sessions' ) ) {
			wp_schedule_event( time(), 'daily', 'scd_cleanup_wizard_sessions' );
		}
	}

	/**
	 * Unschedule automatic session cleanup.
	 *
	 * @since    1.0.0
	 * @static
	 * @return   void
	 */
	public static function unschedule_cleanup(): void {
		$timestamp = wp_next_scheduled( 'scd_cleanup_wizard_sessions' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'scd_cleanup_wizard_sessions' );
		}
	}

	/**
	 * Cleanup expired sessions.
	 *
	 * @since    1.0.0
	 * @static
	 * @return   int    Number of cleaned sessions.
	 */
	public static function cleanup_expired_sessions(): int {
		global $wpdb;

		$prefix        = 'scd_wizard_';
		$expired_count = 0;

		// Properly escape the prefix for LIKE queries
		$like_pattern     = $wpdb->esc_like( '_transient_' . $prefix ) . '%';
		$not_like_pattern = $wpdb->esc_like( '_transient_timeout_' . $prefix ) . '%';

		$transients = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} 
                 WHERE option_name LIKE %s 
                 AND option_name NOT LIKE %s",
				$like_pattern,
				$not_like_pattern
			)
		);

		foreach ( $transients as $transient ) {
			$key = str_replace( '_transient_', '', $transient->option_name );

			if ( get_transient( $key ) === false ) {
				delete_transient( $key );
				++$expired_count;
			}
		}

		return $expired_count;
	}

	/**
	 * Get active sessions count.
	 *
	 * @since    1.0.0
	 * @static
	 * @return   int    Active sessions count.
	 */
	public static function get_active_sessions_count(): int {
		global $wpdb;

		$prefix = 'scd_wizard_';

		// Properly escape the prefix for LIKE queries
		$like_pattern     = $wpdb->esc_like( '_transient_' . $prefix ) . '%';
		$not_like_pattern = $wpdb->esc_like( '_transient_timeout_' . $prefix ) . '%';

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->options} 
                 WHERE option_name LIKE %s 
                 AND option_name NOT LIKE %s",
				$like_pattern,
				$not_like_pattern
			)
		);

		return (int) $count;
	}

	/**
	 * Generate session ID.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   string    Session ID.
	 */
	private function generate_session_id(): string {
		return 'wizard_' . wp_generate_uuid4() . '_' . time();
	}

	/**
	 * Get transient key.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $session_id    Session ID.
	 * @return   string                   Transient key.
	 */
	private function get_transient_key( string $session_id ): string {
		return $this->prefix . $session_id;
	}

	/**
	 * Validate session ownership.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $data    Session data.
	 * @return   bool             True if valid ownership.
	 */
	private function validate_session_ownership( array $data ): bool {
		$current_user_id = get_current_user_id();
		$session_user_id = $data['user_id'] ?? 0;

		// Must be same user
		if ( $current_user_id !== $session_user_id ) {
			return false;
		}

		// Validate IP address if stored and validation is enabled
		$validate_ip = apply_filters( 'scd_wizard_validate_session_ip', true );

		if ( $validate_ip && isset( $data['ip_address'] ) && ! empty( $data['ip_address'] ) ) {
			$current_ip = $this->get_client_ip();
			if ( $current_ip !== $data['ip_address'] ) {
				// Log suspicious activity
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					SCD_Log::warning(
						'Session: IP mismatch detected',
						array(
							'session_ip' => $data['ip_address'],
							'current_ip' => $current_ip,
							'user_id'    => $current_user_id,
						)
					);
				}
				// Don't fail for IP changes (VPN, mobile networks, carrier-grade NAT)
				// Just log for monitoring
			}
		}

		if ( isset( $data['created_at'] ) ) {
			$created_time = strtotime( $data['created_at'] );
			$session_age  = time() - $created_time;

			// Sessions older than 24 hours are invalid
			if ( $session_age > 86400 ) {
				return false;
			}
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Get client IP address.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   string    Client IP address.
	 */
	private function get_client_ip(): string {
		$ip_keys = array(
			'HTTP_CF_CONNECTING_IP',     // Cloudflare
			'HTTP_CLIENT_IP',            // Proxy
			'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
			'HTTP_X_FORWARDED',          // Proxy
			'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
			'HTTP_FORWARDED_FOR',        // Proxy
			'HTTP_FORWARDED',            // Proxy
			'REMOTE_ADDR',                // Standard
		);

		foreach ( $ip_keys as $key ) {
			if ( array_key_exists( $key, $_SERVER ) && ! empty( $_SERVER[ $key ] ) ) {
				$ip = $_SERVER[ $key ];

				// Handle comma-separated IPs
				if ( strpos( $ip, ',' ) !== false ) {
					$ip = trim( explode( ',', $ip )[0] );
				}

				// Validate IP - allow private ranges for mobile/corporate users
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}

		return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
	}

	/**
	 * Cleanup specific session.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $session_id    Session ID.
	 * @return   void
	 */
	private function cleanup_session( string $session_id ): void {
		$transient_key = $this->get_transient_key( $session_id );
		delete_transient( $transient_key );
	}

	/**
	 * Validate session data integrity.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $data    Session data.
	 * @return   bool             True if data is valid.
	 */
	private function validate_data_integrity( array $data ): bool {
		$required_fields = array( 'session_id', 'created_at', 'user_id' );

		foreach ( $required_fields as $field ) {
			if ( ! isset( $data[ $field ] ) ) {
				return false;
			}
		}

		if ( ! is_string( $data['session_id'] ) || empty( $data['session_id'] ) ) {
			return false;
		}

		if ( ! is_numeric( $data['user_id'] ) || $data['user_id'] < 0 ) {
			return false;
		}

		if ( ! strtotime( $data['created_at'] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Sanitize session data.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $data    Raw data.
	 * @return   array            Sanitized data.
	 */
	private function sanitize_data( array $data ): array {
		$sanitized = array();

		foreach ( $data as $key => $value ) {
			$clean_key = sanitize_key( $key );

			switch ( $clean_key ) {
				case 'session_id':
				case 'created_at':
				case 'last_accessed':
					$sanitized[ $clean_key ] = sanitize_text_field( $value );
					break;

				case 'ip_address':
					$ip                      = filter_var( $value, FILTER_VALIDATE_IP );
					$sanitized[ $clean_key ] = false !== $ip ? $ip : '0.0.0.0';
					break;

				case 'user_id':
					$sanitized[ $clean_key ] = absint( $value );
					break;

				case 'campaign_data':
				case 'completed_steps':
				case 'options':
				case 'step_data':
					// Recursively sanitize nested arrays
					$sanitized[ $clean_key ] = $this->sanitize_nested_data( $value );
					break;

				default:
					// For other fields, apply appropriate sanitization
					$sanitized[ $clean_key ] = $this->sanitize_value( $value );
					break;
			}
		}

		return $sanitized;
	}

	/**
	 * Sanitize nested data recursively.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    mixed $data    Data to sanitize.
	 * @return   mixed            Sanitized data.
	 */
	private function sanitize_nested_data( $data ) {
		if ( is_array( $data ) ) {
			$sanitized = array();
			foreach ( $data as $key => $value ) {
				$clean_key               = sanitize_key( $key );
				$sanitized[ $clean_key ] = $this->sanitize_nested_data( $value );
			}
			return $sanitized;
		}

		return $this->sanitize_value( $data );
	}

	/**
	 * Sanitize a single value based on its type.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    mixed $value    Value to sanitize.
	 * @return   mixed             Sanitized value.
	 */
	private function sanitize_value( $value ) {
		if ( is_string( $value ) ) {
			if ( filter_var( $value, FILTER_VALIDATE_URL ) ) {
				return esc_url_raw( $value );
			}
			if ( is_email( $value ) ) {
				return sanitize_email( $value );
			}
			if ( $value !== strip_tags( $value ) ) {
				// Allow basic formatting tags
				return wp_kses(
					$value,
					array(
						'strong' => array(),
						'em'     => array(),
						'b'      => array(),
						'i'      => array(),
						'br'     => array(),
						'p'      => array(),
						'span'   => array( 'class' => array() ),
					)
				);
			}
			// Default string sanitization
			return sanitize_text_field( $value );
		} elseif ( is_numeric( $value ) ) {
			// Preserve numeric types
			if ( is_int( $value ) ) {
				return (int) $value;
			} elseif ( is_float( $value ) ) {
				return (float) $value;
			}
			return $value;
		} elseif ( is_bool( $value ) ) {
			return (bool) $value;
		} elseif ( is_null( $value ) ) {
			return null;
		}

		// For other types, return as-is
		return $value;
	}
}
