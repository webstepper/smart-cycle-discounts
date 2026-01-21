<?php
/**
 * Send Test Email Handler Class
 *
 * Contains test email HTML template with inline CSS. Email clients (Gmail, Outlook,
 * Apple Mail, etc.) do NOT support external stylesheets. Inline CSS is REQUIRED
 * for proper email rendering per industry standards.
 *
 * @see https://www.campaignmonitor.com/css/
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers/class-send-test-email-handler.php
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
 * Send Test Email Handler Class
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers
 * @author     Webstepper <contact@webstepper.io>
 */
class WSSCD_Send_Test_Email_Handler extends WSSCD_Abstract_Ajax_Handler {

	/**
	 * Get AJAX action name.
	 *
	 * @since    1.0.0
	 * @return   string    Action name.
	 */
	protected function get_action_name() {
		return 'wsscd_send_test_email';
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

			$settings = isset( $request['settings'] ) ? $request['settings'] : array();

			$provider        = isset( $settings['email_provider'] ) ? $settings['email_provider'] : 'wpmail';
			$valid_providers = array( 'wpmail', 'sendgrid', 'amazonses' );

			if ( ! in_array( $provider, $valid_providers, true ) ) {
				throw new Exception( __( 'Invalid email provider', 'smart-cycle-discounts' ) );
			}

			$from_email = isset( $settings['from_email'] ) ? sanitize_email( $settings['from_email'] ) : '';
			if ( empty( $from_email ) ) {
				$from_email = get_option( 'admin_email' );
			}

			if ( ! is_email( $from_email ) ) {
				throw new Exception( __( 'Invalid from email address', 'smart-cycle-discounts' ) );
			}

			$from_name = isset( $settings['from_name'] ) ? sanitize_text_field( $settings['from_name'] ) : '';
			if ( empty( $from_name ) ) {
				$from_name = get_bloginfo( 'name' );
			}

			$container = isset( $GLOBALS['wsscd_container'] ) ? $GLOBALS['wsscd_container'] : null;
			if ( ! $container ) {
				throw new Exception( __( 'Service container not initialized', 'smart-cycle-discounts' ) );
			}

			$logger = $container->get( 'logger' );

			$provider_instance = $this->create_provider( $provider, $settings, $logger, $from_email, $from_name );

			if ( ! $provider_instance->validate_config() ) {
				throw new Exception( __( 'Email provider configuration is invalid. Please check your settings.', 'smart-cycle-discounts' ) );
			}

			// Send test email
			$recipient = get_option( 'admin_email' );
			$subject   = __( 'Test Email from Smart Cycle Discounts', 'smart-cycle-discounts' );
			$content   = $this->get_test_email_content( $provider, $from_email, $from_name );

			$result = $provider_instance->send( $recipient, $subject, $content );

			if ( ! $result ) {
				throw new Exception( __( 'Failed to send test email. Please check your email provider settings and logs.', 'smart-cycle-discounts' ) );
			}

			$logger->info(
				'Test email sent successfully',
				array(
					'provider'  => $provider,
					'recipient' => $recipient,
				)
			);

			return $this->success(
				array(
					'message' => sprintf(
					/* translators: %s: email address */
						__( 'Test email sent successfully to %s', 'smart-cycle-discounts' ),
						$recipient
					),
				)
			);

		} catch ( Exception $e ) {
			if ( isset( $logger ) ) {
				$logger->error(
					'Failed to send test email',
					array(
						'error' => $e->getMessage(),
					)
				);
			}

			return $this->error(
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is sanitized before JSON output.
				esc_html( $e->getMessage() ),
				'test_email_failed',
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
	 * @param    WSSCD_Logger $logger       Logger instance.
	 * @param    string     $from_email   From email address.
	 * @param    string     $from_name    From name.
	 * @return   WSSCD_Email_Provider           Provider instance.
	 * @throws   Exception                    If provider cannot be created.
	 */
	private function create_provider( $provider, $settings, $logger, $from_email, $from_name ) {
		require_once WSSCD_INCLUDES_DIR . 'integrations/email/interface-email-provider.php';

		switch ( $provider ) {
			case 'sendgrid':
				require_once WSSCD_INCLUDES_DIR . 'integrations/email/providers/class-sendgrid-provider.php';
				$api_key = isset( $settings['sendgrid_api_key'] ) ? sanitize_text_field( $settings['sendgrid_api_key'] ) : '';

				if ( empty( $api_key ) ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages are for logging/debugging, not direct output.
					throw new Exception( esc_html__( 'SendGrid API key is required', 'smart-cycle-discounts' ) );
				}

				return new WSSCD_SendGrid_Provider( $logger, $api_key, $from_email, $from_name );

			case 'amazonses':
				require_once WSSCD_INCLUDES_DIR . 'integrations/email/providers/class-amazonses-provider.php';
				$access_key = isset( $settings['amazonses_access_key'] ) ? sanitize_text_field( $settings['amazonses_access_key'] ) : '';
				$secret_key = isset( $settings['amazonses_secret_key'] ) ? sanitize_text_field( $settings['amazonses_secret_key'] ) : '';
				$region     = isset( $settings['amazonses_region'] ) ? sanitize_text_field( $settings['amazonses_region'] ) : 'us-east-1';

				if ( empty( $access_key ) || empty( $secret_key ) ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages are for logging/debugging, not direct output.
					throw new Exception( esc_html__( 'Amazon SES access and secret keys are required', 'smart-cycle-discounts' ) );
				}

				return new WSSCD_AmazonSES_Provider( $logger, $access_key, $secret_key, $region, $from_email, $from_name );

			case 'wpmail':
			default:
				require_once WSSCD_INCLUDES_DIR . 'integrations/email/providers/class-wpmail-provider.php';
				return new WSSCD_WPMail_Provider( $logger, $from_email, $from_name );
		}
	}

	/**
	 * Get test email HTML content.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $provider     Provider name.
	 * @param    string $from_email   From email address.
	 * @param    string $from_name    From name.
	 * @return   string                  HTML content.
	 */
	private function get_test_email_content( $provider, $from_email, $from_name ) {
		$provider_names = array(
			'wpmail'    => __( 'WordPress Mail', 'smart-cycle-discounts' ),
			'sendgrid'  => __( 'SendGrid', 'smart-cycle-discounts' ),
			'amazonses' => __( 'Amazon SES', 'smart-cycle-discounts' ),
		);

		$provider_label = isset( $provider_names[ $provider ] ) ? $provider_names[ $provider ] : $provider;

		ob_start();
		?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php esc_html_e( 'Test Email', 'smart-cycle-discounts' ); ?></title>
	<?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- Email template. Email clients do not support external stylesheets. ?>
	<style type="text/css">
		body {
			margin: 0;
			padding: 0;
			font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen-Sans, Ubuntu, Cantarell, 'Helvetica Neue', sans-serif;
			background-color: #f5f5f5;
			color: #333333;
			line-height: 1.6;
		}
		.email-container {
			max-width: 600px;
			margin: 0 auto;
			background-color: #ffffff;
		}
		.email-header {
			background: linear-gradient( 135deg, #667eea 0%, #764ba2 100% );
			padding: 40px 30px;
			text-align: center;
		}
		.email-header h1 {
			margin: 0;
			color: #ffffff;
			font-size: 28px;
			font-weight: 600;
		}
		.email-header .icon {
			display: inline-block;
			width: 60px;
			height: 60px;
			background-color: rgba( 255, 255, 255, 0.2 );
			border-radius: 50%;
			line-height: 60px;
			font-size: 30px;
			margin-bottom: 15px;
		}
		.email-body {
			padding: 40px 30px;
		}
		.email-body h2 {
			margin: 0 0 20px 0;
			color: #1d2327;
			font-size: 20px;
			font-weight: 600;
		}
		.email-body p {
			margin: 0 0 15px 0;
			color: #3c434a;
			font-size: 16px;
		}
		.info-box {
			background-color: #f0f9ff;
			border-left: 4px solid #667eea;
			padding: 20px;
			margin: 25px 0;
		}
		.info-box table {
			width: 100%;
			border-collapse: collapse;
		}
		.info-box td {
			padding: 8px 0;
			font-size: 15px;
		}
		.info-box td:first-child {
			font-weight: 600;
			color: #1d2327;
			width: 40%;
		}
		.info-box td:last-child {
			color: #3c434a;
		}
		.success-icon {
			display: inline-block;
			width: 50px;
			height: 50px;
			background-color: #00a32a;
			color: white;
			border-radius: 50%;
			line-height: 50px;
			text-align: center;
			font-size: 30px;
			margin: 20px auto;
		}
		.email-footer {
			background-color: #f5f5f5;
			padding: 30px;
			text-align: center;
			border-top: 1px solid #e5e5e5;
		}
		.email-footer p {
			margin: 5px 0;
			color: #646970;
			font-size: 14px;
		}
	</style>
</head>
<body>
	<div class="email-container">
		<div class="email-header">
			<div class="icon">✉️</div>
			<h1><?php esc_html_e( 'Test Email', 'smart-cycle-discounts' ); ?></h1>
		</div>

		<div class="email-body">
			<div style="text-align: center;">
				<div class="success-icon">✓</div>
			</div>

			<h2><?php esc_html_e( 'Email delivery is working!', 'smart-cycle-discounts' ); ?></h2>

			<p><?php esc_html_e( 'This is a test email from Smart Cycle Discounts. If you received this message, your email configuration is working correctly.', 'smart-cycle-discounts' ); ?></p>

			<div class="info-box">
				<table>
					<tr>
						<td><?php esc_html_e( 'Email Provider:', 'smart-cycle-discounts' ); ?></td>
						<td><?php echo esc_html( $provider_label ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'From Email:', 'smart-cycle-discounts' ); ?></td>
						<td><?php echo esc_html( $from_email ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'From Name:', 'smart-cycle-discounts' ); ?></td>
						<td><?php echo esc_html( $from_name ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Sent At:', 'smart-cycle-discounts' ); ?></td>
						<td><?php echo esc_html( current_time( 'mysql' ) ); ?></td>
					</tr>
				</table>
			</div>

			<p><?php esc_html_e( 'You can now confidently use this email provider for campaign notifications and performance reports.', 'smart-cycle-discounts' ); ?></p>
		</div>

		<div class="email-footer">
			<p><?php esc_html_e( 'This is a test email from Smart Cycle Discounts', 'smart-cycle-discounts' ); ?></p>
			<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=wsscd-notifications&tab=settings' ) ); ?>"><?php esc_html_e( 'Manage notification settings', 'smart-cycle-discounts' ); ?></a></p>
		</div>
	</div>
</body>
</html>
		<?php
		return ob_get_clean();
	}
}
