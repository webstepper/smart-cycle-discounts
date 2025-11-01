<?php
/**
 * Email Provider Factory
 *
 * Centralized factory for creating email provider instances.
 * Eliminates code duplication across AJAX handlers.
 *
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/integrations/email
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Email Provider Factory Class
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/integrations/email
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Email_Provider_Factory {

	/**
	 * Create provider instance based on settings.
	 *
	 * @since    1.0.0
	 * @param    string     $provider     Provider type (wpmail, sendgrid, amazonses).
	 * @param    array      $settings     Provider settings.
	 * @param    SCD_Logger $logger       Logger instance.
	 * @param    string     $from_email   From email address.
	 * @param    string     $from_name    From name.
	 * @return   object                      Provider instance.
	 * @throws   Exception                   If provider cannot be created.
	 */
	public static function create( $provider, $settings, $logger, $from_email, $from_name ) {
		// Load provider interface
		require_once SCD_INCLUDES_DIR . 'integrations/email/interface-email-provider.php';

		// Validate provider type
		$valid_providers = array( 'wpmail', 'sendgrid', 'amazonses' );
		if ( ! in_array( $provider, $valid_providers, true ) ) {
			throw new Exception(
				sprintf(
					/* translators: %s: provider name */
					__( 'Invalid email provider: %s', 'smart-cycle-discounts' ),
					$provider
				)
			);
		}

		// Create provider instance based on type
		switch ( $provider ) {
			case 'sendgrid':
				return self::create_sendgrid_provider( $settings, $logger, $from_email, $from_name );

			case 'amazonses':
				return self::create_amazonses_provider( $settings, $logger, $from_email, $from_name );

			case 'wpmail':
			default:
				return self::create_wpmail_provider( $logger, $from_email, $from_name );
		}
	}

	/**
	 * Create SendGrid provider instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array      $settings     Provider settings.
	 * @param    SCD_Logger $logger       Logger instance.
	 * @param    string     $from_email   From email address.
	 * @param    string     $from_name    From name.
	 * @return   SCD_SendGrid_Provider       SendGrid provider instance.
	 * @throws   Exception                   If API key is missing.
	 */
	private static function create_sendgrid_provider( $settings, $logger, $from_email, $from_name ) {
		require_once SCD_INCLUDES_DIR . 'integrations/email/providers/class-sendgrid-provider.php';

		$api_key = isset( $settings['sendgrid_api_key'] ) ? sanitize_text_field( $settings['sendgrid_api_key'] ) : '';

		if ( empty( $api_key ) ) {
			throw new Exception( __( 'SendGrid API key is required', 'smart-cycle-discounts' ) );
		}

		return new SCD_SendGrid_Provider( $logger, $api_key, $from_email, $from_name );
	}

	/**
	 * Create Amazon SES provider instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array      $settings     Provider settings.
	 * @param    SCD_Logger $logger       Logger instance.
	 * @param    string     $from_email   From email address.
	 * @param    string     $from_name    From name.
	 * @return   SCD_AmazonSES_Provider      Amazon SES provider instance.
	 * @throws   Exception                   If credentials are missing.
	 */
	private static function create_amazonses_provider( $settings, $logger, $from_email, $from_name ) {
		require_once SCD_INCLUDES_DIR . 'integrations/email/providers/class-amazonses-provider.php';

		$access_key = isset( $settings['amazonses_access_key'] ) ? sanitize_text_field( $settings['amazonses_access_key'] ) : '';
		$secret_key = isset( $settings['amazonses_secret_key'] ) ? sanitize_text_field( $settings['amazonses_secret_key'] ) : '';
		$region     = isset( $settings['amazonses_region'] ) ? sanitize_text_field( $settings['amazonses_region'] ) : 'us-east-1';

		if ( empty( $access_key ) || empty( $secret_key ) ) {
			throw new Exception( __( 'Amazon SES access and secret keys are required', 'smart-cycle-discounts' ) );
		}

		return new SCD_AmazonSES_Provider( $logger, $access_key, $secret_key, $region, $from_email, $from_name );
	}

	/**
	 * Create WordPress Mail provider instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    SCD_Logger $logger       Logger instance.
	 * @param    string     $from_email   From email address.
	 * @param    string     $from_name    From name.
	 * @return   SCD_WPMail_Provider         WordPress Mail provider instance.
	 */
	private static function create_wpmail_provider( $logger, $from_email, $from_name ) {
		require_once SCD_INCLUDES_DIR . 'integrations/email/providers/class-wpmail-provider.php';

		return new SCD_WPMail_Provider( $logger, $from_email, $from_name );
	}
}
