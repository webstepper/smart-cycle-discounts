<?php
/**
 * Amazonses Provider Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/integrations/email/providers/class-amazonses-provider.php
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Amazon SES Provider Class
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/integrations/email/providers
 */
class SCD_AmazonSES_Provider implements SCD_Email_Provider {

	/**
	 * Logger instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Logger    $logger    Logger instance.
	 */
	private SCD_Logger $logger;

	/**
	 * AWS Access Key ID.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $access_key    AWS Access Key ID.
	 */
	private string $access_key;

	/**
	 * AWS Secret Access Key.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $secret_key    AWS Secret Access Key.
	 */
	private string $secret_key;

	/**
	 * AWS Region.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $region    AWS Region.
	 */
	private string $region;

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
	 * @param    string     $access_key    AWS Access Key ID.
	 * @param    string     $secret_key    AWS Secret Access Key.
	 * @param    string     $region        AWS Region.
	 * @param    string     $from_email    From email address.
	 * @param    string     $from_name     From name.
	 */
	public function __construct(
		SCD_Logger $logger,
		string $access_key = '',
		string $secret_key = '',
		string $region = 'us-east-1',
		string $from_email = '',
		string $from_name = ''
	) {
		$this->logger     = $logger;
		$this->access_key = $access_key;
		$this->secret_key = $secret_key;
		$this->region     = $region;
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
			$this->logger->error( 'Amazon SES provider not configured' );
			return false;
		}

		if ( ! is_email( $to ) ) {
			$this->logger->error( 'Invalid email address', array( 'to' => $to ) );
			return false;
		}

		// Build SES API request parameters
		$params = array(
			'Action'                           => 'SendEmail',
			'Source'                           => sprintf( '%s <%s>', $this->from_name, $this->from_email ),
			'Destination.ToAddresses.member.1' => $to,
			'Message.Subject.Data'             => $subject,
			'Message.Subject.Charset'          => 'UTF-8',
			'Message.Body.Html.Data'           => $content,
			'Message.Body.Html.Charset'        => 'UTF-8',
		);

		// Make signed API request
		$response = $this->make_ses_request( $params );

		if ( is_wp_error( $response ) ) {
			$this->logger->error(
				'Amazon SES API request failed',
				array(
					'error' => $response->get_error_message(),
					'to'    => $to,
				)
			);
			return false;
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		if ( 200 === $status_code ) {
			$this->logger->debug(
				'Email sent via Amazon SES',
				array(
					'to'      => $to,
					'subject' => $subject,
				)
			);
			return true;
		}

		$body = wp_remote_retrieve_body( $response );
		$this->logger->error(
			'Amazon SES API error',
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
					'error' => 'Failed to send via Amazon SES',
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
		return 'Amazon SES';
	}

	/**
	 * Check if provider is configured and available.
	 *
	 * @since    1.0.0
	 * @return   bool    True if available.
	 */
	public function is_available(): bool {
		return ! empty( $this->access_key )
			&& ! empty( $this->secret_key )
			&& ! empty( $this->region )
			&& is_email( $this->from_email );
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

		// Test configuration by getting sending quota
		$params = array(
			'Action' => 'GetSendQuota',
		);

		$response = $this->make_ses_request( $params );

		if ( is_wp_error( $response ) ) {
			$this->logger->error(
				'Amazon SES config validation failed',
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

		// Get send quota
		$params = array(
			'Action' => 'GetSendQuota',
		);

		$response = $this->make_ses_request( $params );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return array();
		}

		$body = wp_remote_retrieve_body( $response );
		$xml  = simplexml_load_string( $body );

		if ( false === $xml ) {
			return array();
		}

		$result = $xml->GetSendQuotaResult;

		return array(
			'provider'           => $this->get_name(),
			'max_24_hour_send'   => (string) $result->Max24HourSend,
			'max_send_rate'      => (string) $result->MaxSendRate,
			'sent_last_24_hours' => (string) $result->SentLast24Hours,
		);
	}

	/**
	 * Make signed SES API request.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $params    Request parameters.
	 * @return   array|WP_Error      Response or error.
	 */
	private function make_ses_request( array $params ) {
		$endpoint = "https://email.{$this->region}.amazonaws.com/";
		$host     = "email.{$this->region}.amazonaws.com";
		$service  = 'ses';

		// Prepare request
		$date              = gmdate( 'Ymd\THis\Z' );
		$params['Version'] = '2010-12-01';

		// Build query string
		ksort( $params );
		$query_string = http_build_query( $params, '', '&', PHP_QUERY_RFC3986 );

		// Create signature
		$signature = $this->create_aws_signature( $query_string, $host, $date, $service );

		// Make request
		return wp_remote_post(
			$endpoint,
			array(
				'body'    => $query_string,
				'headers' => array(
					'Content-Type'  => 'application/x-www-form-urlencoded',
					'X-Amz-Date'    => $date,
					'Authorization' => $signature,
				),
				'timeout' => 30,
			)
		);
	}

	/**
	 * Create AWS Signature Version 4.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $query_string    Query string.
	 * @param    string $host            Host.
	 * @param    string $date            Date.
	 * @param    string $service         Service name.
	 * @return   string                     Authorization header.
	 */
	private function create_aws_signature( string $query_string, string $host, string $date, string $service ): string {
		$algorithm        = 'AWS4-HMAC-SHA256';
		$date_stamp       = substr( $date, 0, 8 );
		$credential_scope = "{$date_stamp}/{$this->region}/{$service}/aws4_request";

		// Create canonical request
		$canonical_request = "POST\n/\n\ncontent-type:application/x-www-form-urlencoded\nhost:{$host}\nx-amz-date:{$date}\n\ncontent-type;host;x-amz-date\n" . hash( 'sha256', $query_string );

		// Create string to sign
		$string_to_sign = "{$algorithm}\n{$date}\n{$credential_scope}\n" . hash( 'sha256', $canonical_request );

		// Calculate signing key
		$k_date    = hash_hmac( 'sha256', $date_stamp, 'AWS4' . $this->secret_key, true );
		$k_region  = hash_hmac( 'sha256', $this->region, $k_date, true );
		$k_service = hash_hmac( 'sha256', $service, $k_region, true );
		$k_signing = hash_hmac( 'sha256', 'aws4_request', $k_service, true );

		// Sign string
		$signature = hash_hmac( 'sha256', $string_to_sign, $k_signing );

		// Build authorization header
		return "{$algorithm} Credential={$this->access_key}/{$credential_scope}, SignedHeaders=content-type;host;x-amz-date, Signature={$signature}";
	}

	/**
	 * Set AWS credentials.
	 *
	 * @since    1.0.0
	 * @param    string $access_key    AWS Access Key ID.
	 * @param    string $secret_key    AWS Secret Access Key.
	 * @return   void
	 */
	public function set_credentials( string $access_key, string $secret_key ): void {
		$this->access_key = sanitize_text_field( $access_key );
		$this->secret_key = sanitize_text_field( $secret_key );
	}

	/**
	 * Set AWS region.
	 *
	 * @since    1.0.0
	 * @param    string $region    AWS Region.
	 * @return   void
	 */
	public function set_region( string $region ): void {
		$this->region = sanitize_text_field( $region );
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
