<?php
/**
 * Logger Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/utilities/class-logger.php
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
 * Logger
 *
 * Central logging system for the plugin with configurable handlers.
 * Supports file logging, error_log, database logging, and custom handlers.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Logger {

	/**
	 * Log levels.
	 */
	public const EMERGENCY = 'emergency';
	public const ALERT     = 'alert';
	public const CRITICAL  = 'critical';
	public const ERROR     = 'error';
	public const WARNING   = 'warning';
	public const NOTICE    = 'notice';
	public const INFO      = 'info';
	public const DEBUG     = 'debug';

	/**
	 * Log handlers.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $handlers    Array of log handlers.
	 */
	private array $handlers = array();

	/**
	 * Context for all log messages.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $context    Log context (e.g., 'general', 'security', 'debug', 'wizard').
	 */
	private string $context;

	/**
	 * Log file path.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $log_file    Log file path.
	 */
	private string $log_file;

	/**
	 * Maximum log file size in bytes.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      int    $max_file_size    Maximum file size.
	 */
	private int $max_file_size;

	/**
	 * Request ID for tracking.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $request_id    Unique request identifier.
	 */
	private string $request_id;

	/**
	 * Log level priority mapping.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $level_priority    Log level priorities.
	 */
	private array $level_priority = array(
		'none'      => 0,
		'emergency' => 1,
		'alert'     => 2,
		'critical'  => 3,
		'error'     => 4,
		'warning'   => 5,
		'notice'    => 6,
		'info'      => 7,
		'debug'     => 8,
	);

	/**
	 * Minimum log level to record.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $min_log_level    Minimum log level.
	 */
	private string $min_log_level;

	/**
	 * Initialize the logger.
	 *
	 * @since    1.0.0
	 * @param    string $context    Logger context (e.g., 'general', 'security', 'debug', 'wizard').
	 */
	public function __construct( string $context = 'general' ) {
		$this->context = $context;

		// Generate unique request ID for session tracking
		$this->request_id = uniqid( 'scd_', true );

		$this->max_file_size = defined( 'SCD_LOG_MAX_SIZE' ) ? SCD_LOG_MAX_SIZE : 10485760;

		$this->min_log_level = $this->determine_log_level();

		$upload_dir = wp_upload_dir();
		$log_dir    = $upload_dir['basedir'] . '/smart-cycle-discounts/logs';

		if ( ! file_exists( $log_dir ) ) {
			wp_mkdir_p( $log_dir );
		}

		$this->log_file = $log_dir . '/plugin.log';

		$this->initialize_handlers();
	}

	/**
	 * Determine log level from multiple sources.
	 *
	 * Priority: constant > database > auto-detect
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   string    Log level.
	 */
	private function determine_log_level(): string {
		// 1. Check constant (highest priority - can't be overridden)
		if ( defined( 'SCD_LOG_LEVEL' ) ) {
			$level = SCD_LOG_LEVEL;
			if ( isset( $this->level_priority[ $level ] ) ) {
				return $level;
			}
		}

		// 2. Check database settings from admin UI
		$settings = get_option( 'scd_settings', array() );
		if ( isset( $settings['advanced']['log_level'] ) ) {
			$level = $settings['advanced']['log_level'];
			if ( isset( $this->level_priority[ $level ] ) ) {
				return $level;
			}
		}

		// 3. Check if debug mode is enabled via toggle
		if ( isset( $settings['advanced']['enable_debug_mode'] ) && $settings['advanced']['enable_debug_mode'] ) {
			$debug_enabled_at = isset( $settings['advanced']['debug_mode_enabled_at'] ) ? $settings['advanced']['debug_mode_enabled_at'] : 0;
			if ( $debug_enabled_at > 0 ) {
				$hours_elapsed = ( time() - $debug_enabled_at ) / HOUR_IN_SECONDS;
				if ( $hours_elapsed < 24 ) {
					return 'debug';
				} else {
					// Auto-disable after 24 hours
					$this->auto_disable_debug_mode();
				}
			}
		}

		// 4. Auto-detect based on environment (fallback)
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			return 'warning'; // Moderate logging for development
		}

		// 5. Default for production
		return 'error';
	}

	/**
	 * Auto-disable debug mode after 24 hours.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function auto_disable_debug_mode(): void {
		$settings = get_option( 'scd_settings', array() );
		if ( isset( $settings['advanced'] ) ) {
			$settings['advanced']['enable_debug_mode']     = false;
			$settings['advanced']['debug_mode_enabled_at'] = 0;
			update_option( 'scd_settings', $settings );
		}
	}

	/**
	 * Initialize default handlers based on context.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function initialize_handlers(): void {
		// Always add file handler for non-debug contexts
		if ( 'debug' !== $this->context ) {
			$this->add_handler( 'file', array( $this, 'handle_file_log' ) );
		}

		// Only add error_log handler if explicitly enabled via SCD_LOG_TO_DEBUG_LOG constant
		if ( defined( 'SCD_LOG_TO_DEBUG_LOG' ) && SCD_LOG_TO_DEBUG_LOG ) {
			$this->add_handler( 'error_log', array( $this, 'handle_error_log' ) );
		}

		if ( 'wizard' === $this->context ) {
			$this->add_handler( 'database', array( $this, 'handle_database_log' ) );
		}
	}

	/**
	 * Add a log handler.
	 *
	 * @since    1.0.0
	 * @param    string   $name       Handler name.
	 * @param    callable $handler    Handler callback.
	 * @return   void
	 */
	public function add_handler( string $name, callable $handler ): void {
		$this->handlers[ $name ] = $handler;
	}

	/**
	 * Remove a log handler.
	 *
	 * @since    1.0.0
	 * @param    string $name    Handler name.
	 * @return   void
	 */
	public function remove_handler( string $name ): void {
		unset( $this->handlers[ $name ] );
	}

	/**
	 * Log an emergency message.
	 *
	 * @since    1.0.0
	 * @param    string $message    Log message.
	 * @param    array  $context    Log context.
	 * @return   void
	 */
	public function emergency( string $message, array $context = array() ): void {
		$this->log( self::EMERGENCY, $message, $context );
	}

	/**
	 * Log an alert message.
	 *
	 * @since    1.0.0
	 * @param    string $message    Log message.
	 * @param    array  $context    Log context.
	 * @return   void
	 */
	public function alert( string $message, array $context = array() ): void {
		$this->log( self::ALERT, $message, $context );
	}

	/**
	 * Log a critical message.
	 *
	 * @since    1.0.0
	 * @param    string $message    Log message.
	 * @param    array  $context    Log context.
	 * @return   void
	 */
	public function critical( string $message, array $context = array() ): void {
		$this->log( self::CRITICAL, $message, $context );
	}

	/**
	 * Log an error message.
	 *
	 * @since    1.0.0
	 * @param    string $message    Log message.
	 * @param    array  $context    Log context.
	 * @return   void
	 */
	public function error( string $message, array $context = array() ): void {
		$this->log( self::ERROR, $message, $context );
	}

	/**
	 * Log a warning message.
	 *
	 * @since    1.0.0
	 * @param    string $message    Log message.
	 * @param    array  $context    Log context.
	 * @return   void
	 */
	public function warning( string $message, array $context = array() ): void {
		$this->log( self::WARNING, $message, $context );
	}

	/**
	 * Log a notice message.
	 *
	 * @since    1.0.0
	 * @param    string $message    Log message.
	 * @param    array  $context    Log context.
	 * @return   void
	 */
	public function notice( string $message, array $context = array() ): void {
		$this->log( self::NOTICE, $message, $context );
	}

	/**
	 * Log an info message.
	 *
	 * @since    1.0.0
	 * @param    string $message    Log message.
	 * @param    array  $context    Log context.
	 * @return   void
	 */
	public function info( string $message, array $context = array() ): void {
		$this->log( self::INFO, $message, $context );
	}

	/**
	 * Log a debug message.
	 *
	 * @since    1.0.0
	 * @param    string $message    Log message.
	 * @param    array  $context    Log context.
	 * @return   void
	 */
	public function debug( string $message, array $context = array() ): void {
		$this->log( self::DEBUG, $message, $context );
	}

	/**
	 * Log a message with arbitrary level.
	 *
	 * @since    1.0.0
	 * @param    string $level      Log level.
	 * @param    string $message    Log message.
	 * @param    array  $context    Log context.
	 * @return   void
	 */
	public function log( string $level, string $message, array $context = array() ): void {
		if ( ! $this->should_log( $level ) ) {
			return;
		}

		$context['logger_context'] = $this->context;

		foreach ( $this->handlers as $handler ) {
			call_user_func( $handler, $level, $message, $context );
		}
	}

	/**
	 * Handle file logging.
	 *
	 * @since    1.0.0
	 * @param    string $level      Log level.
	 * @param    string $message    Log message.
	 * @param    array  $context    Log context.
	 * @return   void
	 */
	private function handle_file_log( string $level, string $message, array $context ): void {
		$formatted_message = $this->format_message( $level, $message, $context );
		$this->write_to_file( $formatted_message );
	}

	/**
	 * Handle error_log logging.
	 *
	 * @since    1.0.0
	 * @param    string $level      Log level.
	 * @param    string $message    Log message.
	 * @param    array  $context    Log context.
	 * @return   void
	 */
	private function handle_error_log( string $level, string $message, array $context ): void {
		$formatted_message = $this->format_message( $level, $message, $context );
		error_log( $formatted_message );
	}

	/**
	 * Handle database logging.
	 *
	 * @since    1.0.0
	 * @param    string $level      Log level.
	 * @param    string $message    Log message.
	 * @param    array  $context    Log context.
	 * @return   void
	 */
	private function handle_database_log( string $level, string $message, array $context ): void {
		global $wpdb;

		$table_name = $wpdb->prefix . 'scd_audit_logs';

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) !== $table_name ) {
			return;
		}

		$wpdb->insert(
			$table_name,
			array(
				'timestamp'  => current_time( 'mysql' ),
				'level'      => $level,
				'message'    => $message,
				'context'    => wp_json_encode( $context ),
				'user_id'    => get_current_user_id(),
				'session_id' => $context['session_id'] ?? '',
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s' )
		);
	}

	/**
	 * Check if we should log at this level.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $level    Log level.
	 * @return   bool                True if should log.
	 */
	private function should_log( string $level ): bool {
		// If log level is 'none', don't log anything
		if ( 'none' === $this->min_log_level ) {
			return false;
		}

		// For debug context, only log when SCD_DEBUG is enabled
		if ( 'debug' === $this->context && ! ( defined( 'SCD_DEBUG' ) && SCD_DEBUG ) ) {
			return false;
		}

		$message_priority = isset( $this->level_priority[ $level ] ) ? $this->level_priority[ $level ] : 0;
		$min_priority     = isset( $this->level_priority[ $this->min_log_level ] ) ? $this->level_priority[ $this->min_log_level ] : 4;

		// Only log if message priority is equal to or higher than minimum (lower number = higher priority)
		return $message_priority <= $min_priority;
	}

	/**
	 * Format log message.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $level      Log level.
	 * @param    string $message    Log message.
	 * @param    array  $context    Log context.
	 * @return   string                Formatted message.
	 */
	private function format_message( string $level, string $message, array $context ): string {
		// Enhanced format with millisecond timestamps and session tracking
		$microtime    = microtime( true );
		$milliseconds = sprintf( '%03d', ( $microtime - floor( $microtime ) ) * 1000 );
		$timestamp    = current_time( 'Y-m-d H:i:s' ) . '.' . $milliseconds;

		$level          = strtoupper( $level );
		$context_prefix = strtoupper( $this->context );

		$formatted = "[{$timestamp}] [{$this->request_id}] SCD.{$context_prefix}.{$level}: {$message}";

		if ( ! empty( $context ) ) {
			unset( $context['logger_context'], $context['request_id'] );
			if ( ! empty( $context ) ) {
				$formatted .= ' | ' . wp_json_encode( $context, JSON_UNESCAPED_SLASHES );
			}
		}

		return $formatted . PHP_EOL;
	}

	/**
	 * Write message to log file.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $message    Formatted message.
	 * @return   void
	 */
	private function write_to_file( string $message ): void {
		if ( file_exists( $this->log_file ) && filesize( $this->log_file ) > $this->max_file_size ) {
			$this->rotate_log();
		}

		// Write to log file
		file_put_contents( $this->log_file, $message, FILE_APPEND | LOCK_EX );
	}

	/**
	 * Rotate log file when it gets too large.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function rotate_log(): void {
		$timestamp   = current_time( 'Ymd-His' );
		$backup_file = $this->log_file . '.' . $timestamp;
		rename( $this->log_file, $backup_file );

		// Clean up old log files
		$this->cleanup_old_logs();
	}

	/**
	 * Clean up old log files based on age and count.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function cleanup_old_logs(): void {
		$log_dir     = dirname( $this->log_file );
		$log_pattern = basename( $this->log_file ) . '.*';
		$old_logs    = glob( $log_dir . '/' . $log_pattern );

		if ( empty( $old_logs ) ) {
			return;
		}

		usort(
			$old_logs,
			function ( $a, $b ) {
				return filemtime( $a ) - filemtime( $b );
			}
		);

		$max_age_days = defined( 'SCD_LOG_MAX_AGE_DAYS' ) ? SCD_LOG_MAX_AGE_DAYS : 7;
		$current_time = time();

		foreach ( $old_logs as $log_file ) {
			$file_age_days = ( $current_time - filemtime( $log_file ) ) / DAY_IN_SECONDS;

			if ( $max_age_days > 0 && $file_age_days > $max_age_days ) {
				unlink( $log_file );
			}
		}

		// Re-check remaining logs and keep only last 5
		$remaining_logs = glob( $log_dir . '/' . $log_pattern );
		if ( count( $remaining_logs ) > 5 ) {
			usort(
				$remaining_logs,
				function ( $a, $b ) {
					return filemtime( $a ) - filemtime( $b );
				}
			);
			$to_delete = array_slice( $remaining_logs, 0, count( $remaining_logs ) - 5 );
			foreach ( $to_delete as $file ) {
				if ( file_exists( $file ) ) {
					unlink( $file );
				}
			}
		}
	}

	/**
	 * Get log file contents.
	 *
	 * @since    1.0.0
	 * @param    int $lines    Number of lines to retrieve (0 for all).
	 * @return   string           Log file contents.
	 */
	public function get_logs( int $lines = 100 ): string {
		if ( ! file_exists( $this->log_file ) ) {
			return '';
		}

		if ( $lines === 0 ) {
			return file_get_contents( $this->log_file );
		}

		$file = new SplFileObject( $this->log_file );
		$file->seek( PHP_INT_MAX );
		$total_lines = $file->key();

		$start_line = max( 0, $total_lines - $lines );
		$file->seek( $start_line );

		$content = '';
		while ( ! $file->eof() ) {
			$content .= $file->current();
			$file->next();
		}

		return $content;
	}

	/**
	 * Clear log file.
	 *
	 * @since    1.0.0
	 * @return   bool    True on success, false on failure.
	 */
	public function clear_logs(): bool {
		if ( file_exists( $this->log_file ) ) {
			return unlink( $this->log_file );
		}

		return true;
	}

	/**
	 * Get log file size.
	 *
	 * @since    1.0.0
	 * @return   int    File size in bytes.
	 */
	public function get_log_size(): int {
		if ( file_exists( $this->log_file ) ) {
			return filesize( $this->log_file );
		}

		return 0;
	}

	/**
	 * Get log statistics.
	 *
	 * @since    1.0.0
	 * @return   array    Log statistics.
	 */
	public function get_log_stats(): array {
		$stats = array(
			'file_exists'   => file_exists( $this->log_file ),
			'file_size'     => $this->get_log_size(),
			'file_path'     => $this->log_file,
			'max_file_size' => $this->max_file_size,
			'writable'      => is_writable( dirname( $this->log_file ) ),
		);

		if ( $stats['file_exists'] ) {
			$stats['last_modified'] = filemtime( $this->log_file );
			$stats['line_count']    = $this->count_lines();
		}

		return $stats;
	}

	/**
	 * Count lines in log file.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   int    Number of lines.
	 */
	private function count_lines(): int {
		if ( ! file_exists( $this->log_file ) ) {
			return 0;
		}

		$file = new SplFileObject( $this->log_file );
		$file->seek( PHP_INT_MAX );
		return $file->key() + 1;
	}

	/**
	 * Set maximum log file size.
	 *
	 * @since    1.0.0
	 * @param    int $size    Maximum size in bytes.
	 * @return   void
	 */
	public function set_max_file_size( int $size ): void {
		$this->max_file_size = $size;
	}

	/**
	 * Flush any buffered log entries.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function flush(): void {
		// This method can be extended if buffering is implemented
	}


	/**
	 * Create a logger instance with specific context.
	 *
	 * @since    1.0.0
	 * @param    string $context    Logger context.
	 * @return   SCD_Logger              Logger instance.
	 */
	public static function with_context( string $context ): SCD_Logger {
		return new self( $context );
	}

	/**
	 * Log with flow prefix for standardized logging.
	 *
	 * This method provides a standardized way to log events in specific flows
	 * (e.g., AJAX, Campaign, Discount, etc.) with consistent formatting.
	 *
	 * Automatically includes:
	 * - Request ID for session tracking
	 * - Millisecond timestamps
	 * - Performance metrics (duration, memory) if _start_time or _include_memory flags provided
	 * - Timestamp for chronological ordering
	 *
	 * @since    1.0.0
	 * @param    string $level      Log level (error, warning, info, debug, etc.).
	 * @param    string $flow       Flow identifier (e.g., 'AJAX START', 'CAMPAIGN CREATE').
	 * @param    string $message    Log message.
	 * @param    array  $data       Additional data to log.
	 * @return   void
	 *
	 * @example
	 * $logger->flow('info', 'AJAX START', 'Processing save_step request', array(
	 *     'action' => 'save_step',
	 *     'user_id' => get_current_user_id(),
	 *     '_start_time' => microtime(true),  // Auto-calculates duration
	 *     '_include_memory' => true           // Auto-adds memory usage
	 * ));
	 */
	public function flow( string $level, string $flow, string $message, array $data = array() ): void {
		$formatted_message = '[' . strtoupper( $flow ) . '] ' . $message;

		$data['request_id'] = $this->request_id;

		$data['timestamp'] = microtime( true );

		if ( isset( $data['_start_time'] ) ) {
			$duration            = microtime( true ) - $data['_start_time'];
			$data['duration_ms'] = round( $duration * 1000, 2 );
			unset( $data['_start_time'] );
		}

		if ( isset( $data['_include_memory'] ) && $data['_include_memory'] ) {
			$data['memory_used'] = memory_get_usage( true );
			$data['peak_memory'] = memory_get_peak_usage( true );
			unset( $data['_include_memory'] );
		}

		$this->log( $level, $formatted_message, $data );
	}
}
