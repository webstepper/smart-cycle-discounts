<?php
/**
 * Session Lock Manager
 *
 * Implements atomic locking for session operations to prevent
 * race conditions and data corruption from concurrent requests.
 *
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/services
 */

declare(strict_types=1);


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Session Lock Class
 *
 * Provides distributed locking mechanism using WordPress transients
 * with atomic operations for preventing concurrent access issues.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/services
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Session_Lock {

    /**
     * Lock timeout in seconds.
     *
     * @since    1.0.0
     * @access   private
     * @var      int    $lock_timeout    Maximum time a lock can be held.
     */
    private int $lock_timeout = 30;

    /**
     * Maximum wait time for acquiring lock in seconds.
     *
     * @since    1.0.0
     * @access   private
     * @var      int    $max_wait_time    Maximum time to wait for lock.
     */
    private int $max_wait_time = 5;

    /**
     * Lock check interval in microseconds.
     *
     * @since    1.0.0
     * @access   private
     * @var      int    $check_interval    How often to check for lock availability.
     */
    private int $check_interval = 50000; // 50ms

    /**
     * Lock prefix.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $lock_prefix    Prefix for lock transients.
     */
    private string $lock_prefix = 'scd_lock_';

    /**
     * Currently held locks.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $held_locks    Array of locks held by current request.
     */
    private array $held_locks = array();

    /**
     * Acquire a lock for the given resource.
     *
     * @since    1.0.0
     * @param    string    $resource_id    Resource identifier to lock.
     * @param    int       $timeout        Custom timeout for this lock (optional).
     * @return   bool                      True if lock acquired, false otherwise.
     */
    public function acquire(string $resource_id, int $timeout = 0): bool {
        $lock_key = $this->get_lock_key($resource_id);
        $lock_value = $this->generate_lock_value();
        $timeout = $timeout > 0 ? $timeout : $this->lock_timeout;
        
        $start_time = microtime(true);
        
        while ((microtime(true) - $start_time) < $this->max_wait_time) {
            // Try to acquire lock atomically
            if ($this->try_acquire_lock($lock_key, $lock_value, $timeout)) {
                $this->held_locks[$resource_id] = array(
                    'key' => $lock_key,
                    'value' => $lock_value,
                    'acquired_at' => microtime(true)
                );
                return true;
            }
            
            // Check if existing lock is expired
            $existing_lock = get_transient($lock_key);
            if ($existing_lock && $this->is_lock_expired($existing_lock)) {
                // Try to clean up expired lock and acquire
                $this->release_expired_lock($lock_key, $existing_lock);
                continue;
            }
            
            // Wait before retrying
            usleep($this->check_interval);
        }
        
        // Log failed lock acquisition

        return false;
    }

    /**
     * Release a lock.
     *
     * @since    1.0.0
     * @param    string    $resource_id    Resource identifier to unlock.
     * @return   bool                      True if lock released, false otherwise.
     */
    public function release(string $resource_id): bool {
        if (!isset($this->held_locks[$resource_id])) {
            return false;
        }
        
        $lock_info = $this->held_locks[$resource_id];
        $lock_key = $lock_info['key'];
        $lock_value = $lock_info['value'];
        
        // Only release if we still own the lock
        $current_lock = get_transient($lock_key);
        if ($current_lock && $this->parse_lock_value($current_lock)['token'] === $this->parse_lock_value($lock_value)['token']) {
            delete_transient($lock_key);
            unset($this->held_locks[$resource_id]);
            return true;
        }
        
        // Lock was already released or taken by someone else
        unset($this->held_locks[$resource_id]);
        return false;
    }

    /**
     * Release all locks held by current request.
     *
     * @since    1.0.0
     * @return   void
     */
    public function release_all(): void {
        foreach (array_keys($this->held_locks) as $resource_id) {
            $this->release($resource_id);
        }
    }

    /**
     * Check if a resource is locked.
     *
     * @since    1.0.0
     * @param    string    $resource_id    Resource identifier to check.
     * @return   bool                      True if locked, false otherwise.
     */
    public function is_locked(string $resource_id): bool {
        $lock_key = $this->get_lock_key($resource_id);
        $lock_value = get_transient($lock_key);
        
        if (!$lock_value) {
            return false;
        }
        
        // Check if lock is expired
        if ($this->is_lock_expired($lock_value)) {
            $this->release_expired_lock($lock_key, $lock_value);
            return false;
        }
        
        return true;
    }

    /**
     * Get lock info for a resource.
     *
     * @since    1.0.0
     * @param    string    $resource_id    Resource identifier.
     * @return   array|null                Lock info or null if not locked.
     */
    public function get_lock_info(string $resource_id): ?array {
        $lock_key = $this->get_lock_key($resource_id);
        $lock_value = get_transient($lock_key);
        
        if (!$lock_value) {
            return null;
        }
        
        $lock_data = $this->parse_lock_value($lock_value);
        
        if ($this->is_lock_expired($lock_value)) {
            return array_merge($lock_data, array('expired' => true));
        }
        
        return array_merge($lock_data, array('expired' => false));
    }

    /**
     * Try to acquire lock atomically.
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $lock_key     Lock transient key.
     * @param    string    $lock_value   Lock value with metadata.
     * @param    int       $timeout      Lock timeout.
     * @return   bool                    True if acquired, false otherwise.
     */
    private function try_acquire_lock(string $lock_key, string $lock_value, int $timeout): bool {
        // Use add_transient for atomic operation
        // This will only succeed if the transient doesn't exist
        global $wpdb;
        
        $option_name = '_transient_' . $lock_key;
        $option_value = $lock_value;
        $autoload = 'no';
        
        // Direct database insert for true atomicity
        $result = $wpdb->query(
            $wpdb->prepare(
                "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, %s)",
                $option_name,
                $option_value,
                $autoload
            )
        );
        
        if ($result) {
            // Set expiration
            set_transient($lock_key, $lock_value, $timeout);
            return true;
        }
        
        return false;
    }

    /**
     * Generate lock value with metadata.
     *
     * @since    1.0.0
     * @access   private
     * @return   string    Lock value.
     */
    private function generate_lock_value(): string {
        $data = array(
            'token' => wp_generate_uuid4(),
            'user_id' => get_current_user_id(),
            'time' => microtime(true),
            'pid' => getmypid() ?: 0,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        );
        
        return json_encode($data);
    }

    /**
     * Parse lock value.
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $lock_value    Lock value to parse.
     * @return   array                    Parsed lock data.
     */
    private function parse_lock_value(string $lock_value): array {
        $data = json_decode($lock_value, true);
        
        if (!is_array($data)) {
            return array(
                'token' => 'unknown',
                'user_id' => 0,
                'time' => 0,
                'pid' => 0,
                'ip' => 'unknown'
            );
        }
        
        return $data;
    }

    /**
     * Check if lock is expired.
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $lock_value    Lock value to check.
     * @return   bool                     True if expired, false otherwise.
     */
    private function is_lock_expired(string $lock_value): bool {
        $lock_data = $this->parse_lock_value($lock_value);
        $lock_time = $lock_data['time'] ?? 0;
        
        // Check if lock is older than timeout
        return (microtime(true) - $lock_time) > $this->lock_timeout;
    }

    /**
     * Release an expired lock.
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $lock_key      Lock transient key.
     * @param    string    $lock_value    Current lock value.
     * @return   void
     */
    private function release_expired_lock(string $lock_key, string $lock_value): void {
        // Log expired lock
        $lock_data = $this->parse_lock_value($lock_value);

        delete_transient($lock_key);
    }

    /**
     * Get lock key for resource.
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $resource_id    Resource identifier.
     * @return   string                    Lock transient key.
     */
    private function get_lock_key(string $resource_id): string {
        return $this->lock_prefix . md5($resource_id);
    }

    /**
     * Clean up all expired locks.
     *
     * @since    1.0.0
     * @static
     * @return   int    Number of expired locks cleaned.
     */
    public static function cleanup_expired_locks(): int {
        global $wpdb;
        
        $prefix = 'scd_lock_';
        $cleaned = 0;
        
        // Get all lock transients
        $locks = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT option_name, option_value FROM {$wpdb->options} 
                 WHERE option_name LIKE %s",
                $wpdb->esc_like('_transient_' . $prefix) . '%'
            )
        );
        
        foreach ($locks as $lock) {
            $lock_key = str_replace('_transient_', '', $lock->option_name);
            
            // Parse and check if expired
            $lock_data = json_decode($lock->option_value, true);
            if (is_array($lock_data) && isset($lock_data['time'])) {
                $lock_time = $lock_data['time'];
                if ((microtime(true) - $lock_time) > 30) { // 30 seconds default timeout
                    delete_transient($lock_key);
                    $cleaned++;
                }
            }
        }
        
        return $cleaned;
    }

    /**
     * Register cleanup hook.
     *
     * @since    1.0.0
     * @static
     * @return   void
     */
    public static function register_cleanup_hook(): void {
        // Clean up on shutdown to release any locks
        register_shutdown_function([__CLASS__, 'shutdown_cleanup']);
    }

    /**
     * Shutdown cleanup handler.
     *
     * @since    1.0.0
     * @static
     * @return   void
     */
    public static function shutdown_cleanup(): void {
        // Release any locks that weren't properly released
        // This is a safety mechanism for unexpected termination
        if (isset($GLOBALS['scd_session_lock_instance'])) {
            $GLOBALS['scd_session_lock_instance']->release_all();
        }
    }
}

// Register cleanup hook
SCD_Session_Lock::register_cleanup_hook();
