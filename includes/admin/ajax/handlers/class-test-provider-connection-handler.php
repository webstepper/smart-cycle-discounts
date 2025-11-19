<?php
/**
 * Test Provider Connection AJAX Handler
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Test Provider Connection AJAX Handler
 *
 * Handles AJAX requests for testing email provider connections.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers
 * @author     Webstepper.io <contact@webstepper.io>
 */
class SCD_Test_Provider_Connection_Handler extends SCD_Abstract_Ajax_Handler {

	/**
	 * Logger instance.
	 *
	 * @var SCD_Logger
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @param SCD_Logger $logger Logger instance.
	 */
	public function __construct( SCD_Logger $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Get the action name for this handler.
	 *
	 * @return string Action name.
	 */
	public function get_action(): string {
		return 'test_provider_connection';
	}

	/**
	 * Get required capability for this action.
	 *
	 * @return string Required capability.
	 */
	public function get_capability(): string {
		return 'manage_options';
	}

	/**
	 * Handle the AJAX request.
	 *
	 * @param array $request_data Request data.
	 * @return array Response data.
	 */
	public function handle( array $request_data ): array {
		try {
			// Get current settings
			$settings = get_option( 'scd_settings', array() );

			if ( ! isset( $settings['notifications'] ) ) {
				return $this->error_response( 'Notification settings not found. Please save your settings first.' );
			}

			$notification_settings = $settings['notifications'];
			$provider_type         = $notification_settings['provider'] ?? 'wpmail';

			// Validate provider-specific requirements
			if ( 'sendgrid' === $provider_type ) {
				$api_key = $notification_settings['sendgrid_api_key'] ?? '';
				if ( empty( $api_key ) ) {
					return $this->error_response( 'SendGrid API key is required.' );
				}

				// Test SendGrid connection
				return $this->test_sendgrid_connection( $api_key );

			} elseif ( 'amazonses' === $provider_type ) {
				$access_key = $notification_settings['aws_access_key'] ?? '';
				$secret_key = $notification_settings['aws_secret_key'] ?? '';
				$region     = $notification_settings['aws_region'] ?? 'us-east-1';

				if ( empty( $access_key ) || empty( $secret_key ) ) {
					return $this->error_response( 'AWS credentials are required.' );
				}

				// Test AmazonSES connection
				return $this->test_amazonses_connection( $access_key, $secret_key, $region );

			} elseif ( 'wpmail' === $provider_type ) {
				return $this->success_response(
					array(
						'message' => 'WP Mail is always available. No connection test needed.',
						'status'  => 'connected',
					)
				);

			} else {
				return $this->error_response( 'Invalid email provider selected.' );
			}
		} catch ( Exception $e ) {
			$this->logger->error(
				'Test provider connection failed',
				array(
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString(),
				)
			);

			return $this->error_response( 'Connection test failed: ' . $e->getMessage() );
		}
	}

	/**
	 * Test SendGrid connection.
	 *
	 * @param string $api_key SendGrid API key.
	 * @return array Response data.
	 */
	private function test_sendgrid_connection( string $api_key ): array {
		// Test the API key by fetching account statistics
		$response = wp_remote_get(
			'https://api.sendgrid.com/v3/stats?start_date=' . gmdate( 'Y-m-d', strtotime( '-30 days' ) ),
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $this->error_response( 'SendGrid connection failed: ' . $response->get_error_message() );
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		if ( 401 === $status_code || 403 === $status_code ) {
			return $this->error_response( 'SendGrid API key is invalid or lacks permissions.' );
		}

		if ( 200 === $status_code ) {
			$body  = json_decode( wp_remote_retrieve_body( $response ), true );
			$stats = ! empty( $body ) ? $body : array();

			return $this->success_response(
				array(
					'message' => 'Successfully connected to SendGrid!',
					'status'  => 'connected',
					'stats'   => $stats,
				)
			);
		}

		return $this->error_response( 'Unexpected response from SendGrid API (Status: ' . $status_code . ')' );
	}

	/**
	 * Test AmazonSES connection.
	 *
	 * @param string $access_key AWS access key.
	 * @param string $secret_key AWS secret key.
	 * @param string $region     AWS region.
	 * @return array Response data.
	 */
	private function test_amazonses_connection( string $access_key, string $secret_key, string $region ): array {
		// For AmazonSES, we'll try to get account sending statistics
		// This requires implementing AWS Signature V4 authentication
		// For simplicity, we'll return a basic check

		if ( empty( $access_key ) || empty( $secret_key ) || empty( $region ) ) {
			return $this->error_response( 'Missing AWS credentials or region.' );
		}

		// Basic validation
		if ( strlen( $access_key ) < 16 || strlen( $secret_key ) < 30 ) {
			return $this->error_response( 'AWS credentials appear to be invalid (incorrect length).' );
		}

		// In a real implementation, you would make an actual AWS API call here
		// For now, we'll do basic validation
		return $this->success_response(
			array(
				'message' => 'AWS credentials format validated. Full connection test requires sending a test email.',
				'status'  => 'validated',
				'note'    => 'Use "Send Test Email" to fully verify the connection.',
			)
		);
	}

	/**
	 * Create success response.
	 *
	 * @param array $data Response data.
	 * @return array Success response.
	 */
	private function success_response( array $data ): array {
		return array(
			'success' => true,
			'data'    => $data,
		);
	}

	/**
	 * Create error response.
	 *
	 * @param string $message Error message.
	 * @return array Error response.
	 */
	private function error_response( string $message ): array {
		return array(
			'success' => false,
			'data'    => array(
				'message' => $message,
			),
		);
	}
}
