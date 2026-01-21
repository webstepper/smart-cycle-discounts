<?php
/**
 * Email Provider Factory Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/integrations/email/class-email-provider-factory.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
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
 * @author     Webstepper <contact@webstepper.io>
 */
class WSSCD_Email_Provider_Factory {

	/**
	 * Create provider instance based on settings.
	 *
	 * @since    1.0.0
	 * @param    string     $provider     Provider type (wpmail, sendgrid, amazonses).
	 * @param    array      $settings     Provider settings.
	 * @param    WSSCD_Logger $logger       Logger instance.
	 * @param    string     $from_email   From email address.
	 * @param    string     $from_name    From name.
	 * @return   object                      Provider instance.
	 * @throws   Exception                   If provider cannot be created.
	 */
	public static function create( $provider, $settings, $logger, $from_email, $from_name ) {
		require_once WSSCD_INCLUDES_DIR . 'integrations/email/interface-email-provider.php';

		$valid_providers = array( 'wpmail', 'sendgrid', 'amazonses' );
		if ( ! in_array( $provider, $valid_providers, true ) ) {
			// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages are for logging/debugging, not direct output.
			throw new Exception(
				sprintf(
					/* translators: %s: provider name */
					__( 'Invalid email provider: %s', 'smart-cycle-discounts' ),
					esc_html( $provider )
				)
			);
			// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

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
	 * @param    WSSCD_Logger $logger       Logger instance.
	 * @param    string     $from_email   From email address.
	 * @param    string     $from_name    From name.
	 * @return   WSSCD_SendGrid_Provider       SendGrid provider instance.
	 * @throws   Exception                   If API key is missing.
	 */
	private static function create_sendgrid_provider( $settings, $logger, $from_email, $from_name ) {
		require_once WSSCD_INCLUDES_DIR . 'integrations/email/providers/class-sendgrid-provider.php';

		$api_key = isset( $settings['sendgrid_api_key'] ) ? sanitize_text_field( $settings['sendgrid_api_key'] ) : '';

		if ( empty( $api_key ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages are for logging/debugging, not direct output.
			throw new Exception( __( 'SendGrid API key is required', 'smart-cycle-discounts' ) );
		}

		return new WSSCD_SendGrid_Provider( $logger, $api_key, $from_email, $from_name );
	}

	/**
	 * Create Amazon SES provider instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array      $settings     Provider settings.
	 * @param    WSSCD_Logger $logger       Logger instance.
	 * @param    string     $from_email   From email address.
	 * @param    string     $from_name    From name.
	 * @return   WSSCD_AmazonSES_Provider      Amazon SES provider instance.
	 * @throws   Exception                   If credentials are missing.
	 */
	private static function create_amazonses_provider( $settings, $logger, $from_email, $from_name ) {
		require_once WSSCD_INCLUDES_DIR . 'integrations/email/providers/class-amazonses-provider.php';

		$access_key = isset( $settings['amazonses_access_key'] ) ? sanitize_text_field( $settings['amazonses_access_key'] ) : '';
		$secret_key = isset( $settings['amazonses_secret_key'] ) ? sanitize_text_field( $settings['amazonses_secret_key'] ) : '';
		$region     = isset( $settings['amazonses_region'] ) ? sanitize_text_field( $settings['amazonses_region'] ) : 'us-east-1';

		if ( empty( $access_key ) || empty( $secret_key ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages are for logging/debugging, not direct output.
			throw new Exception( __( 'Amazon SES access and secret keys are required', 'smart-cycle-discounts' ) );
		}

		return new WSSCD_AmazonSES_Provider( $logger, $access_key, $secret_key, $region, $from_email, $from_name );
	}

	/**
	 * Create WordPress Mail provider instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    WSSCD_Logger $logger       Logger instance.
	 * @param    string     $from_email   From email address.
	 * @param    string     $from_name    From name.
	 * @return   WSSCD_WPMail_Provider         WordPress Mail provider instance.
	 */
	private static function create_wpmail_provider( $logger, $from_email, $from_name ) {
		require_once WSSCD_INCLUDES_DIR . 'integrations/email/providers/class-wpmail-provider.php';

		return new WSSCD_WPMail_Provider( $logger, $from_email, $from_name );
	}
}
