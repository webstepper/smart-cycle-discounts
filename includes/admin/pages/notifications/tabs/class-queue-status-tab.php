<?php
/**
 * Queue Status Tab Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/pages/notifications/tabs/class-queue-status-tab.php
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
 * Queue Status Tab Class
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/pages/notifications/tabs
 */
class SCD_Queue_Status_Tab extends SCD_Notifications_Tab_Base {

	/**
	 * Container instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      object    $container    Container instance.
	 */
	private object $container;

	/**
	 * Initialize tab.
	 *
	 * @since    1.0.0
	 * @param    string                 $tab_slug   Tab slug.
	 * @param    SCD_Notifications_Page $page       Page instance.
	 * @param    SCD_Logger             $logger     Logger instance.
	 * @param    object                 $container  Container instance.
	 */
	public function __construct( string $tab_slug, SCD_Notifications_Page $page, SCD_Logger $logger, object $container ) {
		$this->container = $container;
		parent::__construct( $tab_slug, $page, $logger );
	}

	/**
	 * Register sections and fields for this tab.
	 *
	 * @since    1.0.0
	 * @param    string $current_tab    Current active tab.
	 * @return   void
	 */
	public function register_sections( string $current_tab ): void {
		// Queue status tab doesn't use WordPress Settings API
		// Content is rendered directly
	}

	/**
	 * Get email manager instance from container.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   SCD_Email_Manager|null    Email manager instance or null.
	 */
	private function get_email_manager(): ?object {
		if ( ! $this->container->has( 'email_manager' ) ) {
			return null;
		}

		return $this->container->get( 'email_manager' );
	}

	/**
	 * Render tab content.
	 *
	 * @since    1.0.0
	 * @param    string $current_tab    Current active tab.
	 * @return   void
	 */
	public function render_tab_content( string $current_tab ): void {
		if ( $current_tab !== $this->tab_slug ) {
			return;
		}

		$email_manager = $this->get_email_manager();
		if ( ! $email_manager ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Email manager not available.', 'smart-cycle-discounts' ) . '</p></div>';
			return;
		}

		$queue_status = $email_manager->get_queue_status();

		?>
		<div class="scd-queue-status">
			<h2><?php esc_html_e( 'Email Queue Status', 'smart-cycle-discounts' ); ?></h2>

			<div class="scd-queue-stats">
				<div class="scd-stat-box">
					<span class="dashicons dashicons-clock"></span>
					<div class="scd-stat-content">
						<span class="scd-stat-value"><?php echo absint( $queue_status['queued'] ); ?></span>
						<span class="scd-stat-label"><?php esc_html_e( 'Queued', 'smart-cycle-discounts' ); ?></span>
					</div>
				</div>

				<div class="scd-stat-box scd-stat-success">
					<span class="dashicons dashicons-yes-alt"></span>
					<div class="scd-stat-content">
						<span class="scd-stat-value"><?php echo absint( $queue_status['sent'] ); ?></span>
						<span class="scd-stat-label"><?php esc_html_e( 'Sent', 'smart-cycle-discounts' ); ?></span>
					</div>
				</div>

				<div class="scd-stat-box scd-stat-error">
					<span class="dashicons dashicons-dismiss"></span>
					<div class="scd-stat-content">
						<span class="scd-stat-value"><?php echo absint( $queue_status['failed'] ); ?></span>
						<span class="scd-stat-label"><?php esc_html_e( 'Failed', 'smart-cycle-discounts' ); ?></span>
					</div>
				</div>

				<div class="scd-stat-box">
					<span class="dashicons dashicons-email"></span>
					<div class="scd-stat-content">
						<span class="scd-stat-value"><?php echo absint( $queue_status['total'] ); ?></span>
						<span class="scd-stat-label"><?php esc_html_e( 'Total', 'smart-cycle-discounts' ); ?></span>
					</div>
				</div>
			</div>

			<div class="scd-queue-info">
				<p class="description">
					<?php esc_html_e( 'The email queue is processed automatically every hour using Action Scheduler. Failed emails are retried up to 3 times before being marked as failed.', 'smart-cycle-discounts' ); ?>
				</p>

				<?php if ( $queue_status['queued'] > 0 ) : ?>
					<p class="notice notice-info inline">
						<strong><?php esc_html_e( 'Queued Emails:', 'smart-cycle-discounts' ); ?></strong>
						<?php
						printf(
							/* translators: %d: number of queued emails */
							esc_html( _n( '%d email is waiting to be sent.', '%d emails are waiting to be sent.', $queue_status['queued'], 'smart-cycle-discounts' ) ),
							absint( $queue_status['queued'] )
						);
						?>
					</p>
				<?php endif; ?>

				<?php if ( $queue_status['failed'] > 0 ) : ?>
					<p class="notice notice-error inline">
						<strong><?php esc_html_e( 'Failed Emails:', 'smart-cycle-discounts' ); ?></strong>
						<?php
						printf(
							/* translators: %d: number of failed emails */
							esc_html( _n( '%d email failed to send after multiple attempts.', '%d emails failed to send after multiple attempts.', $queue_status['failed'], 'smart-cycle-discounts' ) ),
							absint( $queue_status['failed'] )
						);
						?>
						<?php esc_html_e( 'Check your email provider settings and error logs.', 'smart-cycle-discounts' ); ?>
					</p>
				<?php endif; ?>
			</div>

			<?php
			// Show email provider info
			$email_settings   = $email_manager->get_settings();
			$provider_names   = array(
				'wpmail'    => __( 'WordPress Mail', 'smart-cycle-discounts' ),
				'sendgrid'  => __( 'SendGrid', 'smart-cycle-discounts' ),
				'amazonses' => __( 'Amazon SES', 'smart-cycle-discounts' ),
			);
			$current_provider = isset( $email_settings['email_provider'] ) ? $email_settings['email_provider'] : 'wpmail';
			?>

			<div class="scd-provider-info">
				<h3><?php esc_html_e( 'Current Email Provider', 'smart-cycle-discounts' ); ?></h3>
				<p>
					<span class="dashicons dashicons-email-alt"></span>
					<strong><?php echo esc_html( $provider_names[ $current_provider ] ?? $current_provider ); ?></strong>
				</p>
				<p class="description">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=scd-notifications&tab=settings' ) ); ?>">
						<?php esc_html_e( 'Change email provider settings', 'smart-cycle-discounts' ); ?>
					</a>
				</p>
			</div>

			<div class="scd-queue-actions">
				<h3><?php esc_html_e( 'Queue Management', 'smart-cycle-discounts' ); ?></h3>
				<p class="description">
					<?php esc_html_e( 'Use these tools to manually manage the email queue.', 'smart-cycle-discounts' ); ?>
				</p>

				<button type="button" id="scd-process-queue" class="button button-primary" <?php disabled( $queue_status['queued'], 0 ); ?>>
					<span class="dashicons dashicons-controls-play"></span>
					<?php esc_html_e( 'Process Queue Now', 'smart-cycle-discounts' ); ?>
				</button>

				<button type="button" id="scd-retry-failed" class="button button-secondary" <?php disabled( $queue_status['failed'], 0 ); ?>>
					<span class="dashicons dashicons-update"></span>
					<?php esc_html_e( 'Retry Failed Emails', 'smart-cycle-discounts' ); ?>
				</button>

				<button type="button" id="scd-clear-queue" class="button button-secondary">
					<span class="dashicons dashicons-trash"></span>
					<?php esc_html_e( 'Clear Old Queue Items', 'smart-cycle-discounts' ); ?>
				</button>

				<div id="scd-queue-result" class="notice" style="display: none; margin-top: 15px;"></div>
			</div>
		</div>

		<style>
			.scd-queue-stats {
				display: grid;
				grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
				gap: 20px;
				margin: 20px 0;
			}
			.scd-stat-box {
				background: #fff;
				border: 1px solid #ccd0d4;
				border-radius: 4px;
				padding: 20px;
				display: flex;
				align-items: center;
				gap: 15px;
			}
			.scd-stat-box .dashicons {
				font-size: 40px;
				width: 40px;
				height: 40px;
				color: #646970;
			}
			.scd-stat-box.scd-stat-success .dashicons {
				color: #00a32a;
			}
			.scd-stat-box.scd-stat-error .dashicons {
				color: #d63638;
			}
			.scd-stat-content {
				display: flex;
				flex-direction: column;
			}
			.scd-stat-value {
				font-size: 32px;
				font-weight: 600;
				line-height: 1;
				color: #1d2327;
			}
			.scd-stat-label {
				font-size: 13px;
				color: #646970;
				margin-top: 4px;
			}
			.scd-queue-info {
				margin: 30px 0;
			}
			.scd-provider-info,
			.scd-queue-actions {
				background: #fff;
				border: 1px solid #ccd0d4;
				border-radius: 4px;
				padding: 20px;
				margin: 20px 0;
			}
			.scd-provider-info h3,
			.scd-queue-actions h3 {
				margin-top: 0;
			}
		</style>
		<?php
	}
}
