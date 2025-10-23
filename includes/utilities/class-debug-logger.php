<?php
/**
 * Comprehensive Debug Logger for Smart Cycle Discounts
 *
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/utilities
 */

declare(strict_types=1);


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Debug Logger
 *
 * Provides comprehensive debugging capabilities with detailed logging
 * of all wizard actions, AJAX requests, validation, and persistence operations.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/utilities
 */
class SCD_Debug_Logger extends SCD_Logger {

	/**
	 * Debug log file path.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $debug_log_file    Debug log file path.
	 */
	private string $debug_log_file;

	/**
	 * Request ID for tracking.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $request_id    Unique request identifier.
	 */
	private string $request_id;

	/**
	 * Start time for performance tracking.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      float    $start_time    Request start time.
	 */
	private float $start_time;

	/**
	 * Initialize the debug logger.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		parent::__construct( 'debug' );

		$upload_dir = wp_upload_dir();
		$log_dir = $upload_dir['basedir'] . '/smart-cycle-discounts/logs';
		$this->debug_log_file = $log_dir . '/plugin.log';

		// Generate unique request ID
		$this->request_id = uniqid( 'scd_', true );
		$this->start_time = microtime( true );

		// Add debug file handler (parent handles error_log based on SCD_LOG_TO_DEBUG_LOG)
		$this->add_handler( 'debug_file', array( $this, 'handle_debug_file_log' ) );

		// Log initialization only if debug level logging is enabled
		if ( defined( 'SCD_DEBUG' ) && SCD_DEBUG ) {
			$this->log_request_start();
		}
	}

	/**
	 * Log request start with environment details.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function log_request_start(): void {
		$context = array(
			'request_id' => $this->request_id,
			'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
			'uri' => $_SERVER['REQUEST_URI'] ?? 'N/A',
			'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'N/A',
			'ip' => $this->get_client_ip(),
			'user_id' => get_current_user_id(),
			'session_id' => session_id() ?: 'no_session',
			'ajax' => wp_doing_ajax() ? 'yes' : 'no',
			'cron' => wp_doing_cron() ? 'yes' : 'no',
			'memory_usage' => memory_get_usage( true ),
			'peak_memory' => memory_get_peak_usage( true ),
		);

		$this->info( '=== REQUEST START ===', $context );
	}

	/**
	 * Log wizard initialization.
	 *
	 * @since    1.0.0
	 * @param    string    $step         Current step.
	 * @param    array     $init_data    Initialization data.
	 * @return   void
	 */
	public function log_wizard_init( string $step, array $init_data = array() ): void {
		$context = array_merge(
			array(
				'request_id' => $this->request_id,
				'action' => 'wizard_init',
				'step' => $step,
				'timestamp' => microtime( true ),
			),
			$init_data
		);

		$this->info( "Wizard initialized on step: {$step}", $context );
	}

	/**
	 * Log wizard navigation.
	 *
	 * @since    1.0.0
	 * @param    string    $from_step    Source step.
	 * @param    string    $to_step      Target step.
	 * @param    string    $action       Navigation action.
	 * @param    array     $context      Additional context.
	 * @return   void
	 */
	public function log_navigation( string $from_step, string $to_step, string $action, array $context = array() ): void {
		$log_context = array_merge(
			array(
				'request_id' => $this->request_id,
				'action' => 'navigation',
				'nav_action' => $action,
				'from_step' => $from_step,
				'to_step' => $to_step,
				'timestamp' => microtime( true ),
			),
			$context
		);

		$this->info( "Navigation: {$from_step} -> {$to_step} via {$action}", $log_context );
	}

	/**
	 * Log AJAX request.
	 *
	 * @since    1.0.0
	 * @param    string    $action       AJAX action.
	 * @param    array     $params       Request parameters.
	 * @param    string    $nonce        Nonce value.
	 * @return   void
	 */
	public function log_ajax_request( string $action, array $params = array(), string $nonce = '' ): void {
		// Sanitize sensitive data
		$safe_params = $this->sanitize_log_data( $params );

		$context = array(
			'request_id' => $this->request_id,
			'type' => 'ajax_request',
			'ajax_action' => $action,
			'params' => $safe_params,
			'nonce' => substr( $nonce, 0, 10 ) . '...',
			'timestamp' => microtime( true ),
		);

		$this->info( "AJAX Request: {$action}", $context );
	}

	/**
	 * Log AJAX response.
	 *
	 * @since    1.0.0
	 * @param    string    $action       AJAX action.
	 * @param    mixed     $response     Response data.
	 * @param    bool      $success      Success status.
	 * @param    float     $duration     Request duration.
	 * @return   void
	 */
	public function log_ajax_response( string $action, $response, bool $success, float $duration ): void {
		$safe_response = $this->sanitize_log_data( $response );

		$context = array(
			'request_id' => $this->request_id,
			'type' => 'ajax_response',
			'ajax_action' => $action,
			'success' => $success ? 'yes' : 'no',
			'duration_ms' => round( $duration * 1000, 2 ),
			'response_type' => gettype( $response ),
			'response_preview' => $this->get_response_preview( $safe_response ),
			'timestamp' => microtime( true ),
		);

		$level = $success ? 'info' : 'warning';
		$this->$level( "AJAX Response: {$action} - " . ( $success ? 'SUCCESS' : 'FAILED' ), $context );
	}

	/**
	 * Log validation process.
	 *
	 * @since    1.0.0
	 * @param    string    $step         Step being validated.
	 * @param    array     $data         Data being validated.
	 * @param    array     $rules        Validation rules applied.
	 * @param    bool      $valid        Validation result.
	 * @param    array     $errors       Validation errors.
	 * @return   void
	 */
	public function log_validation( string $step, array $data, array $rules, bool $valid, array $errors = array() ): void {
		$safe_data = $this->sanitize_log_data( $data );

		$context = array(
			'request_id' => $this->request_id,
			'action' => 'validation',
			'step' => $step,
			'data_fields' => array_keys( $safe_data ),
			'rules_applied' => $rules,
			'valid' => $valid ? 'yes' : 'no',
			'error_count' => count( $errors ),
			'errors' => $errors,
			'timestamp' => microtime( true ),
		);

		if ( $valid ) {
			$this->info( "Validation PASSED for step: {$step}", $context );
		} else {
			$this->warning( "Validation FAILED for step: {$step}", $context );
		}
	}

	/**
	 * Log sanitization process.
	 *
	 * @since    1.0.0
	 * @param    string    $field        Field being sanitized.
	 * @param    mixed     $raw_value    Raw value.
	 * @param    mixed     $clean_value  Sanitized value.
	 * @param    string    $method       Sanitization method.
	 * @return   void
	 */
	public function log_sanitization( string $field, $raw_value, $clean_value, string $method ): void {
		$context = array(
			'request_id' => $this->request_id,
			'action' => 'sanitization',
			'field' => $field,
			'method' => $method,
			'raw_type' => gettype( $raw_value ),
			'clean_type' => gettype( $clean_value ),
			'value_changed' => $raw_value !== $clean_value ? 'yes' : 'no',
			'timestamp' => microtime( true ),
		);

		$this->debug( "Sanitized field '{$field}' using {$method}", $context );
	}

	/**
	 * Log database operation.
	 *
	 * @since    1.0.0
	 * @param    string    $operation    Operation type (insert, update, delete, select).
	 * @param    string    $table        Table name.
	 * @param    array     $data         Operation data.
	 * @param    mixed     $result       Operation result.
	 * @param    float     $duration     Operation duration.
	 * @return   void
	 */
	public function log_database_operation( string $operation, string $table, array $data, $result, float $duration ): void {
		$safe_data = $this->sanitize_log_data( $data );

		$context = array(
			'request_id' => $this->request_id,
			'action' => 'database',
			'operation' => $operation,
			'table' => $table,
			'fields' => array_keys( $safe_data ),
			'success' => false !== $result ? 'yes' : 'no',
			'affected_rows' => is_numeric( $result ) ? $result : 0,
			'duration_ms' => round( $duration * 1000, 2 ),
			'timestamp' => microtime( true ),
		);

		$level = false !== $result ? 'info' : 'error';
		$this->$level( "Database {$operation} on {$table}", $context );
	}

	/**
	 * Log persistence operation.
	 *
	 * @since    1.0.0
	 * @param    string    $action       Persistence action.
	 * @param    string    $step         Step name.
	 * @param    array     $data         Data being persisted.
	 * @param    bool      $success      Success status.
	 * @param    string    $message      Result message.
	 * @return   void
	 */
	public function log_persistence( string $action, string $step, array $data, bool $success, string $message = '' ): void {
		$safe_data = $this->sanitize_log_data( $data );

		$context = array(
			'request_id' => $this->request_id,
			'action' => 'persistence',
			'persist_action' => $action,
			'step' => $step,
			'data_size' => strlen( serialize( $safe_data ) ),
			'field_count' => count( $safe_data ),
			'success' => $success ? 'yes' : 'no',
			'message' => $message,
			'timestamp' => microtime( true ),
		);

		$level = $success ? 'info' : 'error';
		$this->$level( "Persistence {$action} for step: {$step}", $context );
	}

	/**
	 * Log user interaction.
	 *
	 * @since    1.0.0
	 * @param    string    $interaction  Interaction type (click, change, submit, etc.).
	 * @param    string    $element      Element identifier.
	 * @param    mixed     $value        Interaction value.
	 * @param    array     $context      Additional context.
	 * @return   void
	 */
	public function log_user_interaction( string $interaction, string $element, $value = null, array $context = array() ): void {
		$log_context = array_merge(
			array(
				'request_id' => $this->request_id,
				'action' => 'user_interaction',
				'interaction' => $interaction,
				'element' => $element,
				'value' => $this->sanitize_value( $value ),
				'timestamp' => microtime( true ),
			),
			$context
		);

		$this->debug( "User {$interaction} on {$element}", $log_context );
	}

	/**
	 * Log error with full context.
	 *
	 * @since    1.0.0
	 * @param    string         $message      Error message.
	 * @param    \Throwable     $exception    Exception object.
	 * @param    array          $context      Additional context.
	 * @return   void
	 */
	public function log_error_with_trace( string $message, \Throwable $exception, array $context = array() ): void {
		$error_context = array_merge(
			array(
				'request_id' => $this->request_id,
				'action' => 'error',
				'exception_class' => get_class( $exception ),
				'error_code' => $exception->getCode(),
				'file' => $exception->getFile(),
				'line' => $exception->getLine(),
				'trace' => $this->format_trace( $exception->getTrace() ),
				'timestamp' => microtime( true ),
			),
			$context
		);

		$this->error( $message . ': ' . $exception->getMessage(), $error_context );
	}

	/**
	 * Log performance metrics.
	 *
	 * @since    1.0.0
	 * @param    string    $operation    Operation name.
	 * @param    float     $duration     Duration in seconds.
	 * @param    array     $metrics      Additional metrics.
	 * @return   void
	 */
	public function log_performance( string $operation, float $duration, array $metrics = array() ): void {
		$context = array_merge(
			array(
				'request_id' => $this->request_id,
				'action' => 'performance',
				'operation' => $operation,
				'duration_ms' => round( $duration * 1000, 2 ),
				'memory_used' => memory_get_usage( true ),
				'peak_memory' => memory_get_peak_usage( true ),
				'timestamp' => microtime( true ),
			),
			$metrics
		);

		$this->info( "Performance: {$operation} completed", $context );
	}

	/**
	 * Log request end with summary.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function log_request_end(): void {
		$duration = microtime( true ) - $this->start_time;

		$context = array(
			'request_id' => $this->request_id,
			'total_duration_ms' => round( $duration * 1000, 2 ),
			'memory_used' => memory_get_usage( true ),
			'peak_memory' => memory_get_peak_usage( true ),
			'db_queries' => get_num_queries(),
		);

		$this->info( '=== REQUEST END ===', $context );
	}

	/**
	 * Handle debug file logging.
	 *
	 * @since    1.0.0
	 * @param    string    $level      Log level.
	 * @param    string    $message    Log message.
	 * @param    array     $context    Log context.
	 * @return   void
	 */
	protected function handle_debug_file_log( string $level, string $message, array $context ): void {
		$formatted_message = $this->format_debug_message( $level, $message, $context );
		$this->write_to_debug_file( $formatted_message );
	}

	/**
	 * Format debug message with enhanced details.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string    $level      Log level.
	 * @param    string    $message    Log message.
	 * @param    array     $context    Log context.
	 * @return   string                Formatted message.
	 */
	protected function format_debug_message( string $level, string $message, array $context ): string {
		$timestamp = current_time( 'Y-m-d H:i:s' ) . '.' . substr( (string) microtime( true ), -3 );
		$level = strtoupper( $level );
		$request_id = $context['request_id'] ?? $this->request_id;

		// Remove request_id from context to avoid duplication
		unset( $context['request_id'], $context['logger_context'] );

		$formatted = "[{$timestamp}] [{$request_id}] SCD.DEBUG.{$level}: {$message}";

		if ( ! empty( $context ) ) {
			$formatted .= ' | ' . wp_json_encode( $context, JSON_UNESCAPED_SLASHES );
		}

		return $formatted . PHP_EOL;
	}

	/**
	 * Write message to debug log file.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string    $message    Formatted message.
	 * @return   void
	 */
	protected function write_to_debug_file( string $message ): void {
		// Use 5x the normal log size for debug logs (default 50MB)
		$max_debug_size = defined( 'SCD_LOG_MAX_SIZE' ) ? SCD_LOG_MAX_SIZE * 5 : 52428800;

		// Rotate if needed
		if ( file_exists( $this->debug_log_file ) && filesize( $this->debug_log_file ) > $max_debug_size ) {
			$this->rotate_debug_log();
		}

		// Write to debug log
		file_put_contents( $this->debug_log_file, $message, FILE_APPEND | LOCK_EX );
	}

	/**
	 * Rotate debug log file.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	protected function rotate_debug_log(): void {
		$timestamp = current_time( 'Ymd-His' );
		$backup_file = $this->debug_log_file . '.' . $timestamp;
		rename( $this->debug_log_file, $backup_file );

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
		$log_dir = dirname( $this->debug_log_file );
		$old_logs = glob( $log_dir . '/plugin.log.*' );

		if ( empty( $old_logs ) ) {
			return;
		}

		// Sort by modification time (oldest first)
		usort( $old_logs, function( $a, $b ) {
			return filemtime( $a ) - filemtime( $b );
		} );

		$max_age_days = defined( 'SCD_LOG_MAX_AGE_DAYS' ) ? SCD_LOG_MAX_AGE_DAYS : 7;
		$current_time = time();

		foreach ( $old_logs as $log_file ) {
			$file_age_days = ( $current_time - filemtime( $log_file ) ) / DAY_IN_SECONDS;

			// Delete if older than max age (and max age is not 0)
			if ( $max_age_days > 0 && $file_age_days > $max_age_days ) {
				unlink( $log_file );
			}
		}

		// Re-check remaining logs and keep only last 5
		$remaining_logs = glob( $log_dir . '/plugin.log.*' );
		if ( count( $remaining_logs ) > 5 ) {
			usort( $remaining_logs, function( $a, $b ) {
				return filemtime( $a ) - filemtime( $b );
			} );
			$to_delete = array_slice( $remaining_logs, 0, count( $remaining_logs ) - 5 );
			foreach ( $to_delete as $file ) {
				if ( file_exists( $file ) ) {
					unlink( $file );
				}
			}
		}
	}

	/**
	 * Sanitize data for logging.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    mixed    $data    Data to sanitize.
	 * @return   mixed             Sanitized data.
	 */
	private function sanitize_log_data( $data ) {
		if ( is_array( $data ) || is_object( $data ) ) {
			$sanitized = array();
			foreach ( (array) $data as $key => $value ) {
				// Convert key to string for sensitive field check
				if ( $this->is_sensitive_field( (string) $key ) ) {
					$sanitized[ $key ] = '[REDACTED]';
				} else {
					$sanitized[ $key ] = $this->sanitize_log_data( $value );
				}
			}
			return $sanitized;
		}
		return $data;
	}

	/**
	 * Sanitize single value.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    mixed    $value    Value to sanitize.
	 * @return   mixed              Sanitized value.
	 */
	private function sanitize_value( $value ) {
		if ( is_string( $value ) && strlen( $value ) > 100 ) {
			return substr( $value, 0, 100 ) . '... [truncated]';
		}
		return $value;
	}

	/**
	 * Check if field is sensitive.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string    $field    Field name.
	 * @return   bool                True if sensitive.
	 */
	private function is_sensitive_field( string $field ): bool {
		$sensitive_patterns = array(
			'password',
			'pass',
			'pwd',
			'secret',
			'key',
			'token',
			'auth',
			'credit',
			'card',
			'cvv',
			'ssn',
		);

		$field_lower = strtolower( $field );
		foreach ( $sensitive_patterns as $pattern ) {
			if ( false !== strpos( $field_lower, $pattern ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get response preview.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    mixed    $response    Response data.
	 * @return   string                Response preview.
	 */
	private function get_response_preview( $response ): string {
		if ( is_array( $response ) ) {
			return 'Array(' . count( $response ) . ' items)';
		} elseif ( is_object( $response ) ) {
			return 'Object(' . get_class( $response ) . ')';
		} elseif ( is_string( $response ) ) {
			return strlen( $response ) > 50 ? substr( $response, 0, 50 ) . '...' : $response;
		} else {
			return (string) $response;
		}
	}

	/**
	 * Format stack trace.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array    $trace    Stack trace.
	 * @return   array              Formatted trace.
	 */
	private function format_trace( array $trace ): array {
		$formatted = array();
		$limit = min( 5, count( $trace ) );

		for ( $i = 0; $i < $limit; $i++ ) {
			$item = $trace[ $i ];
			$formatted[] = sprintf(
				'%s:%d %s%s%s()',
				$item['file'] ?? 'unknown',
				$item['line'] ?? 0,
				$item['class'] ?? '',
				$item['type'] ?? '',
				$item['function'] ?? 'unknown'
			);
		}

		if ( count( $trace ) > $limit ) {
			$formatted[] = '... and ' . ( count( $trace ) - $limit ) . ' more';
		}

		return $formatted;
	}

	/**
	 * Get client IP address.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   string    IP address.
	 */
	private function get_client_ip(): string {
		$ip_keys = array( 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR' );

		foreach ( $ip_keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
				// Handle comma-separated IPs
				if ( strpos( $ip, ',' ) !== false ) {
					$ip = trim( explode( ',', $ip )[0] );
				}
				return $ip;
			}
		}

		return 'unknown';
	}
}