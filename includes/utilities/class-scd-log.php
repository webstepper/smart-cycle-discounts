<?php
/**
 * Scd Log Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/utilities/class-scd-log.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


// Ensure SCD_Logger is loaded
if ( ! class_exists( 'SCD_Logger' ) ) {
	require_once __DIR__ . '/class-logger.php';
}

/**
 * Static Logger Facade Class
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/utilities
 * @author     Webstepper <contact@webstepper.io>
 */
class SCD_Log {

	/**
	 * Debug logger instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Logger|null    $debug_instance    Debug logger instance.
	 */
	private static ?SCD_Logger $debug_instance = null;

	/**
	 * Get debug logger instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   SCD_Logger    Debug logger instance.
	 */
	private static function get_debug_instance(): SCD_Logger {
		if ( null === self::$debug_instance ) {
			self::$debug_instance = new SCD_Logger( 'debug' );
		}
		return self::$debug_instance;
	}

	/**
	 * Log debug message.
	 *
	 * @since    1.0.0
	 * @param    string $message    Message to log.
	 * @param    array  $context    Additional context data.
	 * @return   void
	 */
	public static function debug( string $message, array $context = array() ): void {
		self::get_debug_instance()->debug( $message, $context );
	}

	/**
	 * Log info message.
	 *
	 * @since    1.0.0
	 * @param    string $message    Message to log.
	 * @param    array  $context    Additional context data.
	 * @return   void
	 */
	public static function info( string $message, array $context = array() ): void {
		self::get_debug_instance()->info( $message, $context );
	}

	/**
	 * Log warning message.
	 *
	 * @since    1.0.0
	 * @param    string $message    Message to log.
	 * @param    array  $context    Additional context data.
	 * @return   void
	 */
	public static function warning( string $message, array $context = array() ): void {
		self::get_debug_instance()->warning( $message, $context );
	}

	/**
	 * Log error message.
	 *
	 * @since    1.0.0
	 * @param    string $message    Message to log.
	 * @param    array  $context    Additional context data.
	 * @return   void
	 */
	public static function error( string $message, array $context = array() ): void {
		self::get_debug_instance()->error( $message, $context );
	}

	/**
	 * Log exception.
	 *
	 * @since    1.0.0
	 * @param    Exception $exception    Exception to log.
	 * @param    string    $message      Additional message.
	 * @return   void
	 */
	public static function exception( Exception $exception, string $message = '' ): void {
		$context = array(
			'exception' => get_class( $exception ),
			'message'   => $exception->getMessage(),
			'file'      => $exception->getFile(),
			'line'      => $exception->getLine(),
			'trace'     => $exception->getTraceAsString(),
		);

		$log_message = $message ?: 'Exception occurred';
		self::get_debug_instance()->error( $log_message, $context );
	}
}
