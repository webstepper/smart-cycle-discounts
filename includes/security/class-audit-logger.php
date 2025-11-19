<?php
/**
 * Audit Logger Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/security/class-audit-logger.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Audit Logger
 *
 * Handles security audit logging for the plugin.
 * This class now acts as a facade for the unified logger with security context.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/security
 * @author     Webstepper <contact@webstepper.io>
 */
class SCD_Audit_Logger {

	/**
	 * Logger instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Logger    $logger    Logger instance.
	 */
	private SCD_Logger $logger;

	/**
	 * Initialize the audit logger.
	 *
	 * @since    1.0.0
	 * @param    SCD_Logger|null $logger    Logger instance (optional, creates security context logger if null).
	 */
	public function __construct( ?SCD_Logger $logger = null ) {
		$this->logger = $logger ?? SCD_Logger::with_context( 'security' );
	}

	/**
	 * Log security event.
	 *
	 * @since    1.0.0
	 * @param    string $event      Event type.
	 * @param    string $message    Event message.
	 * @param    array  $context    Additional context.
	 * @return   void
	 */
	public function log_security_event( string $event, string $message, array $context = array() ): void {
		$context['event_type'] = $event;
		$context['user_id']    = get_current_user_id();
		$context['ip_address'] = $this->get_client_ip();
		$context['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
		$context['timestamp']  = current_time( 'mysql' );

		$this->logger->warning( $message, $context );
	}

	/**
	 * Log authentication event.
	 *
	 * @since    1.0.0
	 * @param    string $event      Event type.
	 * @param    string $username   Username.
	 * @param    bool   $success    Success status.
	 * @return   void
	 */
	public function log_auth_event( string $event, string $username, bool $success ): void {
		$message = sprintf(
			'Authentication %s: %s for user %s',
			$success ? 'success' : 'failure',
			$event,
			$username
		);

		$this->log_security_event(
			'authentication',
			$message,
			array(
				'username' => $username,
				'success'  => $success,
			)
		);
	}

	/**
	 * Log access event.
	 *
	 * @since    1.0.0
	 * @param    string $resource    Resource accessed.
	 * @param    string $action      Action performed.
	 * @param    bool   $allowed     Access allowed.
	 * @return   void
	 */
	public function log_access_event( string $resource, string $action, bool $allowed ): void {
		$message = sprintf(
			'Access %s: %s on %s',
			$allowed ? 'granted' : 'denied',
			$action,
			$resource
		);

		$this->log_security_event(
			'access_control',
			$message,
			array(
				'resource' => $resource,
				'action'   => $action,
				'allowed'  => $allowed,
			)
		);
	}

	/**
	 * Get client IP address.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   string    Client IP address.
	 */
	private function get_client_ip(): string {
		$ip_keys = array( 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR' );

		foreach ( $ip_keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = $_SERVER[ $key ];
				if ( strpos( $ip, ',' ) !== false ) {
					$ip = trim( explode( ',', $ip )[0] );
				}
				if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
					return $ip;
				}
			}
		}

		return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
	}
}
