<?php
/**
 * SendGrid Email Provider
 *
 * Email provider using SendGrid Web API v3.
 * Provides excellent deliverability, analytics, and tracking.
 *
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/integrations/email/providers
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SendGrid Provider Class
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/integrations/email/providers
 */
class SCD_SendGrid_Provider implements SCD_Email_Provider {

	/**
	 * Logger instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Logger    $logger    Logger instance.
	 */
	private SCD_Logger $logger;

	/**
	 * SendGrid API key.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $api_key    SendGrid API key.
	 */
	private string $api_key;

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
	 * SendGrid API endpoint.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $api_endpoint    API endpoint.
	 */
	private string $api_endpoint = 'https://api.sendgrid.com/v3/mail/send';

	/**
	 * Initialize provider.
	 *
	 * @since    1.0.0
	 * @param    SCD_Logger $logger        Logger instance.
	 * @param    string     $api_key       SendGrid API key.
	 * @param    string     $from_email    From email address.
	 * @param    string     $from_name     From name.
	 */
	public function __construct( SCD_Logger $logger, string $api_key = '', string $from_email = '', string $from_name = '' ) {
		$this->logger     = $logger;
		$this->api_key    = $api_key;
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
		if ( ! $this->is_available() ) {
			$this->logger->error( 'SendGrid provider not configured' );
			return false;
		}

		if ( ! is_email( $to ) ) {
			$this->logger->error( 'Invalid email address', array( 'to' => $to ) );
			return false;
		}

		// Build SendGrid API payload
		$payload = array(
			'personalizations' => array(
				array(
					'to'      => array(
						array(
							'email' => $to,
						),
					),
					'subject' => $subject,
				),
			),
			'from'             => array(
				'email' => $this->from_email,
				'name'  => $this->from_name,
			),
			'content'          => array(
				array(
					'type'  => 'text/html',
					'value' => $content,
				),
			),
		);

		// Make API request
		$response = wp_remote_post(
			$this->api_endpoint,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $payload ),
				'timeout' => 30,
			)
		);

		// Handle response
		if ( is_wp_error( $response ) ) {
			$this->logger->error(
				'SendGrid API request failed',
				array(
					'error' => $response->get_error_message(),
					'to'    => $to,
				)
			);
			return false;
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		if ( 202 === $status_code ) {
			$this->logger->debug(
				'Email sent via SendGrid',
				array(
					'to'      => $to,
					'subject' => $subject,
				)
			);
			return true;
		}

		$body = wp_remote_retrieve_body( $response );
		$this->logger->error(
			'SendGrid API error',
			array(
				'status_code' => $status_code,
				'response'    => $body,
				'to'          => $to,
			)
		);

		return false;
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

		// SendGrid allows batch sending, but for simplicity we'll send individually
		// In production, you could optimize this to use SendGrid's batch API
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
					'error' => 'Failed to send via SendGrid',
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
		return 'SendGrid';
	}

	/**
	 * Check if provider is configured and available.
	 *
	 * @since    1.0.0
	 * @return   bool    True if available.
	 */
	public function is_available(): bool {
		return ! empty( $this->api_key ) && is_email( $this->from_email );
	}

	/**
	 * Validate provider configuration.
	 *
	 * @since    1.0.0
	 * @return   bool    True if valid configuration.
	 */
	public function validate_config(): bool {
		if ( ! $this->is_available() ) {
			return false;
		}

		// Test API key by making a validation request
		$response = wp_remote_get(
			'https://api.sendgrid.com/v3/scopes',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->api_key,
				),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->logger->error(
				'SendGrid config validation failed',
				array(
					'error' => $response->get_error_message(),
				)
			);
			return false;
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		return 200 === $status_code;
	}

	/**
	 * Get sending statistics.
	 *
	 * @since    1.0.0
	 * @return   array    Statistics array.
	 */
	public function get_stats(): array {
		if ( ! $this->is_available() ) {
			return array();
		}

		// Get stats for last 30 days
		$end_date   = gmdate( 'Y-m-d' );
		$start_date = gmdate( 'Y-m-d', strtotime( '-30 days' ) );

		$response = wp_remote_get(
			"https://api.sendgrid.com/v3/stats?start_date={$start_date}&end_date={$end_date}",
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->api_key,
				),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->logger->error(
				'Failed to fetch SendGrid stats',
				array(
					'error' => $response->get_error_message(),
				)
			);
			return array();
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		if ( 200 !== $status_code ) {
			return array();
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) || empty( $data ) ) {
			return array();
		}

		// Aggregate stats from all days
		$stats = array(
			'provider'     => $this->get_name(),
			'period'       => 'Last 30 days',
			'requests'     => 0,
			'delivered'    => 0,
			'opens'        => 0,
			'clicks'       => 0,
			'bounces'      => 0,
			'spam_reports' => 0,
		);

		foreach ( $data as $day ) {
			if ( ! isset( $day['stats'] ) || ! is_array( $day['stats'] ) ) {
				continue;
			}

			foreach ( $day['stats'] as $metric ) {
				$stats['requests']     += $metric['metrics']['requests'] ?? 0;
				$stats['delivered']    += $metric['metrics']['delivered'] ?? 0;
				$stats['opens']        += $metric['metrics']['unique_opens'] ?? 0;
				$stats['clicks']       += $metric['metrics']['unique_clicks'] ?? 0;
				$stats['bounces']      += $metric['metrics']['bounces'] ?? 0;
				$stats['spam_reports'] += $metric['metrics']['spam_reports'] ?? 0;
			}
		}

		return $stats;
	}

	/**
	 * Set API key.
	 *
	 * @since    1.0.0
	 * @param    string $api_key    SendGrid API key.
	 * @return   void
	 */
	public function set_api_key( string $api_key ): void {
		$this->api_key = sanitize_text_field( $api_key );
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
}
