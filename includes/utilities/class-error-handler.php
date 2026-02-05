<?php
/**
 * Error Handler Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/utilities/class-error-handler.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress/plugins/smart-cycle-discounts/
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Error Handler Class
 *
 * Handles errors and exceptions throughout the plugin.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core
 * @author     Webstepper <contact@webstepper.io>
 */
class WSSCD_Error_Handler {

	/**
	 * Logger instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Logger|null    $logger    Logger instance.
	 */
	private ?WSSCD_Logger $logger = null;

	/**
	 * Error types mapping.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $error_types    Error types.
	 */
	private array $error_types = array(
		E_ERROR             => 'Fatal Error',
		E_WARNING           => 'Warning',
		E_PARSE             => 'Parse Error',
		E_NOTICE            => 'Notice',
		E_CORE_ERROR        => 'Core Error',
		E_CORE_WARNING      => 'Core Warning',
		E_COMPILE_ERROR     => 'Compile Error',
		E_COMPILE_WARNING   => 'Compile Warning',
		E_USER_ERROR        => 'User Error',
		E_USER_WARNING      => 'User Warning',
		E_USER_NOTICE       => 'User Notice',
		E_STRICT            => 'Strict Notice',
		E_RECOVERABLE_ERROR => 'Recoverable Error',
		E_DEPRECATED        => 'Deprecated',
		E_USER_DEPRECATED   => 'User Deprecated',
	);

	/**
	 * Initialize the error handler.
	 *
	 * @since    1.0.0
	 * @param    WSSCD_Logger|null $logger    Logger instance.
	 */
	public function __construct( ?WSSCD_Logger $logger = null ) {
		$this->logger = $logger;
	}

	/**
	 * Handle an error.
	 *
	 * @since    1.0.0
	 * @param    int    $errno       Error number.
	 * @param    string $errstr      Error message.
	 * @param    string $errfile     Error file.
	 * @param    int    $errline     Error line.
	 * @param    array  $errcontext  Error context.
	 * @return   bool                   True if error was handled.
	 */
	public function handle_error( int $errno, string $errstr, string $errfile = '', int $errline = 0, array $errcontext = array() ): bool {
		// Only handle errors when WordPress debug mode is enabled
		// This respects WordPress's standard debugging configuration.
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return false;
		}

		$error_type = $this->error_types[ $errno ] ?? 'Unknown Error';

		$safe_file = $this->sanitize_file_path( $errfile );

		$error_data = array(
			'type'    => $error_type,
			'message' => $errstr,
			'file'    => $safe_file,
			'line'    => $errline,
		);

		// Only include minimal context in development mode
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			$safe_context = $this->sanitize_error_context( $errcontext );
			if ( ! empty( $safe_context ) ) {
				$error_data['context_keys'] = array_keys( $safe_context );
			}
		}

		// Log the error
		if ( $this->logger ) {
			switch ( $errno ) {
				case E_ERROR:
				case E_PARSE:
				case E_CORE_ERROR:
				case E_COMPILE_ERROR:
				case E_USER_ERROR:
					$this->logger->error( $errstr, $error_data );
					break;

				case E_WARNING:
				case E_CORE_WARNING:
				case E_COMPILE_WARNING:
				case E_USER_WARNING:
					$this->logger->warning( $errstr, $error_data );
					break;

				case E_NOTICE:
				case E_USER_NOTICE:
				case E_STRICT:
				case E_DEPRECATED:
				case E_USER_DEPRECATED:
					$this->logger->notice( $errstr, $error_data );
					break;

				default:
					$this->logger->debug( $errstr, $error_data );
					break;
			}
		}

		// Don't execute PHP internal error handler
		return true;
	}

	/**
	 * Handle an exception.
	 *
	 * @since    1.0.0
	 * @param    Throwable $exception    Exception to handle.
	 * @return   void
	 */
	public function handle_exception( Throwable $exception ): void {
		$safe_file = $this->sanitize_file_path( $exception->getFile() );

		$error_data = array(
			'message' => $exception->getMessage(),
			'file'    => $safe_file,
			'line'    => $exception->getLine(),
			'type'    => get_class( $exception ),
		);

		// Only include stack trace in development mode
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			$error_data['trace'] = $this->sanitize_stack_trace( $exception->getTrace() );
		}

		// Log the exception
		if ( $this->logger ) {
			$this->logger->error( 'Uncaught exception: ' . $exception->getMessage(), $error_data );
		}

		// In production, show a user-friendly error
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			wp_die(
				esc_html__( 'An error occurred. Please try again later.', 'smart-cycle-discounts' ),
				esc_html__( 'Error', 'smart-cycle-discounts' ),
				array( 'response' => 500 )
			);
		}
	}


	/**
	 * Log and format error.
	 *
	 * @since    1.0.0
	 * @param    string $message    Error message.
	 * @param    array  $context    Error context.
	 * @param    string $level      Error level.
	 * @return   void
	 */
	public function log_error( string $message, array $context = array(), string $level = 'error' ): void {
		if ( ! $this->logger ) {
			return;
		}

		switch ( $level ) {
			case 'emergency':
				$this->logger->emergency( $message, $context );
				break;
			case 'alert':
				$this->logger->alert( $message, $context );
				break;
			case 'critical':
				$this->logger->critical( $message, $context );
				break;
			case 'error':
				$this->logger->error( $message, $context );
				break;
			case 'warning':
				$this->logger->warning( $message, $context );
				break;
			case 'notice':
				$this->logger->notice( $message, $context );
				break;
			case 'info':
				$this->logger->info( $message, $context );
				break;
			case 'debug':
				$this->logger->debug( $message, $context );
				break;
		}
	}

	/**
	 * Handle shutdown errors.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function handle_shutdown(): void {
		$error = error_get_last();

		if ( null !== $error && in_array( $error['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) {
			$this->handle_error(
				$error['type'],
				$error['message'],
				$error['file'],
				$error['line']
			);
		}
	}

	/**
	 * Register error handlers.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function register_handlers(): void {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Core functionality of error handler class
		set_error_handler( array( $this, 'handle_error' ) );
		set_exception_handler( array( $this, 'handle_exception' ) );
		register_shutdown_function( array( $this, 'handle_shutdown' ) );
	}

	/**
	 * Restore default error handlers.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function restore_handlers(): void {
		restore_error_handler();
		restore_exception_handler();
	}

	/**
	 * Check if error should be reported.
	 *
	 * Uses WordPress debug constants to determine if errors should be reported.
	 * This respects the site's WP_DEBUG and WP_DEBUG_LOG configuration.
	 *
	 * @since    1.0.0
	 * @param    int $errno    Error number.
	 * @return   bool             True if error should be reported.
	 */
	public function should_report_error( int $errno ): bool {
		// Only report errors when WordPress debug mode is enabled.
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return false;
		}

		// Fatal errors should always be reported when debugging is on.
		$fatal_errors = array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR );
		if ( in_array( $errno, $fatal_errors, true ) ) {
			return true;
		}

		// For non-fatal errors, also check WP_DEBUG_LOG.
		return defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG;
	}

	/**
	 * Get error type name.
	 *
	 * @since    1.0.0
	 * @param    int $errno    Error number.
	 * @return   string           Error type name.
	 */
	public function get_error_type_name( int $errno ): string {
		return $this->error_types[ $errno ] ?? 'Unknown Error';
	}

	/**
	 * Sanitize file path to remove sensitive information.
	 *
	 * @since    1.0.0
	 * @param    string $file_path    File path to sanitize.
	 * @return   string                  Sanitized file path.
	 */
	private function sanitize_file_path( string $file_path ): string {
		if ( defined( 'WSSCD_PLUGIN_DIR' ) ) {
			$file_path = str_replace( WSSCD_PLUGIN_DIR, 'WSSCD/', $file_path );
		}

		// Remove WordPress root path from log output for security (prevents path disclosure).
		// Note: This is sanitization, not location determination - we need ABSPATH value to redact it.
		// phpcs:ignore WordPress.PHP.ConstantConditions.FoundConstant -- ABSPATH check for sanitization, not location determination.
		if ( defined( 'ABSPATH' ) ) {
			$file_path = str_replace( constant( 'ABSPATH' ), 'WP/', $file_path );
		}

		$file_path = preg_replace( '/^.*\/plugins\//', 'plugins/', $file_path );

		return $file_path;
	}

	/**
	 * Sanitize error context to remove sensitive data.
	 *
	 * @since    1.0.0
	 * @param    array $context    Error context.
	 * @return   array                Sanitized context.
	 */
	private function sanitize_error_context( array $context ): array {
		$sensitive_keys = array(
			'password',
			'pass',
			'pwd',
			'secret',
			'token',
			'key',
			'auth',
			'api_key',
			'apikey',
			'access_token',
			'private',
			'credential',
			'db_password',
			'db_host',
			'database',
		);

		$safe_context = array();

		foreach ( $context as $key => $value ) {
			$lower_key = strtolower( $key );

			// Skip sensitive keys
			$is_sensitive = false;
			foreach ( $sensitive_keys as $sensitive ) {
				if ( strpos( $lower_key, $sensitive ) !== false ) {
					$is_sensitive = true;
					break;
				}
			}

			if ( ! $is_sensitive ) {
				// Only include basic type information, not actual values
				$safe_context[ $key ] = gettype( $value );
			}
		}

		return $safe_context;
	}

	/**
	 * Sanitize stack trace to remove sensitive data.
	 *
	 * @since    1.0.0
	 * @param    array $trace    Stack trace.
	 * @return   array              Sanitized trace.
	 */
	private function sanitize_stack_trace( array $trace ): array {
		$safe_trace = array();

		foreach ( $trace as $i => $frame ) {
			$safe_frame = array(
				'file'     => isset( $frame['file'] ) ? $this->sanitize_file_path( $frame['file'] ) : 'unknown',
				'line'     => isset( $frame['line'] ) ? $frame['line'] : 0,
				'function' => isset( $frame['function'] ) ? $frame['function'] : 'unknown',
			);

			if ( isset( $frame['class'] ) ) {
				$safe_frame['class'] = $frame['class'];
			}

			if ( isset( $frame['type'] ) ) {
				$safe_frame['type'] = $frame['type'];
			}

			// Don't include args as they may contain sensitive data
			$safe_trace[] = $safe_frame;

			// Limit trace depth
			if ( $i >= 10 ) {
				break;
			}
		}

		return $safe_trace;
	}
}
