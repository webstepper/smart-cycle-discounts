<?php
/**
 * Wpmail Provider Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/integrations/email/providers/class-wpmail-provider.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WordPress Mail Provider Class
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/integrations/email/providers
 */
class SCD_WPMail_Provider implements SCD_Email_Provider {

	/**
	 * Logger instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Logger    $logger    Logger instance.
	 */
	private SCD_Logger $logger;

	/**
	 * From email address.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $from_email    From email address.
	 */
	private string $from_email;

	/**
	 * From name.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $from_name    From name.
	 */
	private string $from_name;

	/**
	 * Initialize provider.
	 *
	 * @since    1.0.0
	 * @param    SCD_Logger $logger        Logger instance.
	 * @param    string     $from_email    From email address.
	 * @param    string     $from_name     From name.
	 */
	public function __construct( SCD_Logger $logger, string $from_email = '', string $from_name = '' ) {
		$this->logger     = $logger;
		$this->from_email = ! empty( $from_email ) ? $from_email : get_option( 'admin_email' );
		$this->from_name  = ! empty( $from_name ) ? $from_name : get_bloginfo( 'name' );
	}

	/**
	 * Send email.
	 *
	 * @since    1.0.0
	 * @param    string $to         Recipient email address.
	 * @param    string $subject    Email subject.
	 * @param    string $content    Email content (HTML).
	 * @param    array  $headers    Optional. Email headers.
	 * @return   bool                  True on success, false on failure.
	 */
	public function send( string $to, string $subject, string $content, array $headers = array() ): bool {
		if ( ! is_email( $to ) ) {
			$this->logger->error( 'Invalid email address', array( 'to' => $to ) );
			return false;
		}

		$default_headers = array(
			'Content-Type: text/html; charset=UTF-8',
			sprintf( 'From: %s <%s>', $this->from_name, $this->from_email ),
		);

		$headers = array_merge( $default_headers, $headers );

		// Development mode check - skip actual sending in local environments without mail server
		if ( $this->is_local_development_without_mail() ) {
			$this->logger->info(
				'Development mode: Email simulated (not actually sent)',
				array(
					'to'        => $to,
					'subject'   => $subject,
					'from'      => $this->from_email,
					'from_name' => $this->from_name,
				)
			);
			return true;
		}

		// Send email
		$result = wp_mail( $to, $subject, $content, $headers );

		if ( $result ) {
			$this->logger->debug(
				'Email sent via wp_mail',
				array(
					'to'      => $to,
					'subject' => $subject,
				)
			);
		} else {
			$this->logger->error(
				'Failed to send email via wp_mail',
				array(
					'to'      => $to,
					'subject' => $subject,
				)
			);
		}

		return $result;
	}

	/**
	 * Send batch of emails.
	 *
	 * @since    1.0.0
	 * @param    array $emails    Array of email data.
	 * @return   array               Results array with success/failure status.
	 */
	public function send_batch( array $emails ): array {
		$results = array(
			'success' => 0,
			'failed'  => 0,
			'total'   => count( $emails ),
			'errors'  => array(),
		);

		foreach ( $emails as $index => $email ) {
			$to      = $email['to'] ?? '';
			$subject = $email['subject'] ?? '';
			$content = $email['content'] ?? '';
			$headers = $email['headers'] ?? array();

			$sent = $this->send( $to, $subject, $content, $headers );

			if ( $sent ) {
				++$results['success'];
			} else {
				++$results['failed'];
				$results['errors'][] = array(
					'index' => $index,
					'to'    => $to,
					'error' => 'Failed to send via wp_mail',
				);
			}
		}

		return $results;
	}

	/**
	 * Get provider name.
	 *
	 * @since    1.0.0
	 * @return   string    Provider name.
	 */
	public function get_name(): string {
		return 'WordPress Mail';
	}

	/**
	 * Check if provider is configured and available.
	 *
	 * @since    1.0.0
	 * @return   bool    True if available.
	 */
	public function is_available(): bool {
		return function_exists( 'wp_mail' );
	}

	/**
	 * Validate provider configuration.
	 *
	 * @since    1.0.0
	 * @return   bool    True if valid configuration.
	 */
	public function validate_config(): bool {
		// WordPress mail is always valid if wp_mail exists
		return $this->is_available() && is_email( $this->from_email );
	}

	/**
	 * Get sending statistics.
	 *
	 * @since    1.0.0
	 * @return   array    Empty array (wp_mail doesn't provide stats).
	 */
	public function get_stats(): array {
		// WordPress wp_mail() doesn't provide statistics
		return array(
			'provider'        => $this->get_name(),
			'stats_available' => false,
			'note'            => __( 'WordPress mail does not provide delivery statistics. For analytics, use SendGrid or Amazon SES.', 'smart-cycle-discounts' ),
		);
	}

	/**
	 * Set from email address.
	 *
	 * @since    1.0.0
	 * @param    string $from_email    From email address.
	 * @return   void
	 */
	public function set_from_email( string $from_email ): void {
		if ( is_email( $from_email ) ) {
			$this->from_email = $from_email;
		}
	}

	/**
	 * Set from name.
	 *
	 * @since    1.0.0
	 * @param    string $from_name    From name.
	 * @return   void
	 */
	public function set_from_name( string $from_name ): void {
		$this->from_name = sanitize_text_field( $from_name );
	}

	/**
	 * Get from email address.
	 *
	 * @since    1.0.0
	 * @return   string    From email address.
	 */
	public function get_from_email(): string {
		return $this->from_email;
	}

	/**
	 * Get from name.
	 *
	 * @since    1.0.0
	 * @return   string    From name.
	 */
	public function get_from_name(): string {
		return $this->from_name;
	}

	/**
	 * Check if running in local development without mail server.
	 *
	 * Detects Local by Flywheel, XAMPP, MAMP, etc. environments that don't have
	 * mail servers configured, which would cause wp_mail() to hang.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   bool    True if local development without mail.
	 */
	private function is_local_development_without_mail(): bool {
		if ( defined( 'WP_LOCAL_DEV' ) && WP_LOCAL_DEV ) {
			return true;
		}

		$server_name = isset( $_SERVER['SERVER_NAME'] ) ? $_SERVER['SERVER_NAME'] : '';
		$local_hosts = array( '.local', 'localhost', '127.0.0.1', '.test', '.dev' );

		foreach ( $local_hosts as $host ) {
			if ( strpos( $server_name, $host ) !== false ) {
				return true;
			}
		}

		if ( function_exists( 'wp_get_environment_type' ) ) {
			$env_type = wp_get_environment_type();
			if ( in_array( $env_type, array( 'local', 'development' ), true ) ) {
				return true;
			}
		}

		return false;
	}
}
