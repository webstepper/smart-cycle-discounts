<?php
/**
 * Test Provider Connection AJAX Handler
 *
 * Handles testing email provider configuration without sending actual emails.
 * Validates API credentials and connection settings.
 *
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Test Provider Connection Handler Class
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Test_Provider_Connection_Handler extends SCD_Abstract_Ajax_Handler {

	/**
	 * Get AJAX action name.
	 *
	 * @since    1.0.0
	 * @return   string    Action name.
	 */
	protected function get_action_name() {
		return 'scd_test_provider_connection';
	}

	/**
	 * Handle the request.
	 *
	 * @since    1.0.0
	 * @param    array $request    Request data.
	 * @return   array                Response data.
	 */
	protected function handle( $request ) {
		try {
			// Verify user has permission
			if ( ! current_user_can( 'manage_options' ) ) {
				throw new Exception( __( 'You do not have permission to perform this action', 'smart-cycle-discounts' ) );
			}

			// Get provider type
			$provider        = isset( $request['provider'] ) ? sanitize_text_field( $request['provider'] ) : '';
			$valid_providers = array( 'wpmail', 'sendgrid', 'amazonses' );

			if ( ! in_array( $provider, $valid_providers, true ) ) {
				throw new Exception( __( 'Invalid email provider', 'smart-cycle-discounts' ) );
			}

			// Get settings from request
			$settings = isset( $request['settings'] ) ? $request['settings'] : array();

			// Get container
			$container = isset( $GLOBALS['scd_container'] ) ? $GLOBALS['scd_container'] : null;
			if ( ! $container ) {
				throw new Exception( __( 'Service container not initialized', 'smart-cycle-discounts' ) );
			}

			// Get logger
			$logger = $container->get( 'logger' );

			// Get from email and name with fallbacks
			$from_email = isset( $settings['from_email'] ) ? sanitize_email( $settings['from_email'] ) : get_option( 'admin_email' );
			$from_name  = isset( $settings['from_name'] ) ? sanitize_text_field( $settings['from_name'] ) : get_bloginfo( 'name' );

			// Create provider instance
			$provider_instance = $this->create_provider( $provider, $settings, $logger, $from_email, $from_name );

			// Test provider configuration
			$is_valid = $provider_instance->validate_config();

			if ( ! $is_valid ) {
				throw new Exception( $this->get_provider_error_message( $provider ) );
			}

			// Get provider stats if available
			$stats = array();
			if ( method_exists( $provider_instance, 'get_stats' ) ) {
				$stats = $provider_instance->get_stats();
			}

			$logger->info(
				'Provider connection test successful',
				array(
					'provider' => $provider,
				)
			);

			return $this->success(
				array(
					'message'  => $this->get_success_message( $provider ),
					'provider' => $provider,
					'stats'    => $stats,
				)
			);

		} catch ( Exception $e ) {
			if ( isset( $logger ) ) {
				$logger->error(
					'Provider connection test failed',
					array(
						'provider' => isset( $provider ) ? $provider : 'unknown',
						'error'    => $e->getMessage(),
					)
				);
			}

			return $this->error(
				$e->getMessage(),
				'connection_test_failed',
				400
			);
		}
	}

	/**
	 * Create provider instance based on settings.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string     $provider     Provider type.
	 * @param    array      $settings     Provider settings.
	 * @param    SCD_Logger $logger       Logger instance.
	 * @param    string     $from_email   From email address.
	 * @param    string     $from_name    From name.
	 * @return   SCD_Email_Provider           Provider instance.
	 * @throws   Exception                    If provider cannot be created.
	 */
	private function create_provider( $provider, $settings, $logger, $from_email, $from_name ) {
		// Load provider classes
		require_once SCD_INCLUDES_DIR . 'integrations/email/interface-email-provider.php';

		switch ( $provider ) {
			case 'sendgrid':
				require_once SCD_INCLUDES_DIR . 'integrations/email/providers/class-sendgrid-provider.php';
				$api_key = isset( $settings['sendgrid_api_key'] ) ? sanitize_text_field( $settings['sendgrid_api_key'] ) : '';

				if ( empty( $api_key ) ) {
					throw new Exception( __( 'SendGrid API key is required', 'smart-cycle-discounts' ) );
				}

				return new SCD_SendGrid_Provider( $logger, $api_key, $from_email, $from_name );

			case 'amazonses':
				require_once SCD_INCLUDES_DIR . 'integrations/email/providers/class-amazonses-provider.php';
				$access_key = isset( $settings['amazonses_access_key'] ) ? sanitize_text_field( $settings['amazonses_access_key'] ) : '';
				$secret_key = isset( $settings['amazonses_secret_key'] ) ? sanitize_text_field( $settings['amazonses_secret_key'] ) : '';
				$region     = isset( $settings['amazonses_region'] ) ? sanitize_text_field( $settings['amazonses_region'] ) : 'us-east-1';

				if ( empty( $access_key ) || empty( $secret_key ) ) {
					throw new Exception( __( 'Amazon SES access and secret keys are required', 'smart-cycle-discounts' ) );
				}

				return new SCD_AmazonSES_Provider( $logger, $access_key, $secret_key, $region, $from_email, $from_name );

			case 'wpmail':
			default:
				require_once SCD_INCLUDES_DIR . 'integrations/email/providers/class-wpmail-provider.php';
				return new SCD_WPMail_Provider( $logger, $from_email, $from_name );
		}
	}

	/**
	 * Get success message for provider.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $provider    Provider type.
	 * @return   string                 Success message.
	 */
	private function get_success_message( $provider ) {
		$messages = array(
			'wpmail'    => __( 'WordPress Mail is configured correctly and ready to use.', 'smart-cycle-discounts' ),
			'sendgrid'  => __( 'SendGrid connection successful! API key is valid and ready to send emails.', 'smart-cycle-discounts' ),
			'amazonses' => __( 'Amazon SES connection successful! Your AWS credentials are valid and SES is ready.', 'smart-cycle-discounts' ),
		);

		return isset( $messages[ $provider ] ) ? $messages[ $provider ] : __( 'Connection successful!', 'smart-cycle-discounts' );
	}

	/**
	 * Get error message for provider.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $provider    Provider type.
	 * @return   string                 Error message.
	 */
	private function get_provider_error_message( $provider ) {
		$messages = array(
			'wpmail'    => __( 'WordPress Mail configuration check failed. Please verify your SMTP settings.', 'smart-cycle-discounts' ),
			'sendgrid'  => __( 'SendGrid connection failed. Please verify your API key is correct and has "Mail Send" permission.', 'smart-cycle-discounts' ),
			'amazonses' => __( 'Amazon SES connection failed. Please verify your AWS credentials, region, and that your SES account is configured correctly.', 'smart-cycle-discounts' ),
		);

		return isset( $messages[ $provider ] ) ? $messages[ $provider ] : __( 'Connection test failed.', 'smart-cycle-discounts' );
	}
}
