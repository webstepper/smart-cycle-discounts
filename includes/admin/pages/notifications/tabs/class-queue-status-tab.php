<?php
/**
 * Queue Status Tab Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/pages/notifications/tabs/class-queue-status-tab.php
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
 * Queue Status Tab Class
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/pages/notifications/tabs
 */
class WSSCD_Queue_Status_Tab extends WSSCD_Notifications_Tab_Base {

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
	 * @param    WSSCD_Notifications_Page $page       Page instance.
	 * @param    WSSCD_Logger             $logger     Logger instance.
	 * @param    object                 $container  Container instance.
	 */
	public function __construct( string $tab_slug, WSSCD_Notifications_Page $page, WSSCD_Logger $logger, object $container ) {
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
	 * @return   WSSCD_Email_Manager|null    Email manager instance or null.
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
		<div class="wsscd-queue-status">
			<h2><?php esc_html_e( 'Email Queue Status', 'smart-cycle-discounts' ); ?></h2>

			<div class="wsscd-queue-stats">
				<div class="wsscd-stat-box">
					<?php
					WSSCD_Icon_Helper::render( 'clock', array( 'size' => 16 ) );
					?>
					<div class="wsscd-stat-content">
						<span class="wsscd-stat-value"><?php echo absint( $queue_status['queued'] ); ?></span>
						<span class="wsscd-stat-label"><?php esc_html_e( 'Queued', 'smart-cycle-discounts' ); ?></span>
					</div>
				</div>

				<div class="wsscd-stat-box wsscd-stat-success">
					<?php
					WSSCD_Icon_Helper::render( 'check', array( 'size' => 16 ) );
					?>
					<div class="wsscd-stat-content">
						<span class="wsscd-stat-value"><?php echo absint( $queue_status['sent'] ); ?></span>
						<span class="wsscd-stat-label"><?php esc_html_e( 'Sent', 'smart-cycle-discounts' ); ?></span>
					</div>
				</div>

				<div class="wsscd-stat-box wsscd-stat-error">
					<?php
					WSSCD_Icon_Helper::render( 'close', array( 'size' => 16 ) );
					?>
					<div class="wsscd-stat-content">
						<span class="wsscd-stat-value"><?php echo absint( $queue_status['failed'] ); ?></span>
						<span class="wsscd-stat-label"><?php esc_html_e( 'Failed', 'smart-cycle-discounts' ); ?></span>
					</div>
				</div>

				<div class="wsscd-stat-box">
					<?php
					WSSCD_Icon_Helper::render( 'email', array( 'size' => 16 ) );
					?>
					<div class="wsscd-stat-content">
						<span class="wsscd-stat-value"><?php echo absint( $queue_status['total'] ); ?></span>
						<span class="wsscd-stat-label"><?php esc_html_e( 'Total', 'smart-cycle-discounts' ); ?></span>
					</div>
				</div>
			</div>

			<div class="wsscd-queue-info">
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

			<div class="wsscd-provider-info">
				<h3><?php esc_html_e( 'Current Email Provider', 'smart-cycle-discounts' ); ?></h3>
				<p>
					<?php
					WSSCD_Icon_Helper::render( 'email', array( 'size' => 16 ) );
					?>
					<strong><?php echo esc_html( $provider_names[ $current_provider ] ?? $current_provider ); ?></strong>
				</p>
				<p class="description">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wsscd-notifications&tab=settings' ) ); ?>">
						<?php esc_html_e( 'Change email provider settings', 'smart-cycle-discounts' ); ?>
					</a>
				</p>
			</div>

			<div class="wsscd-queue-actions">
				<h3><?php esc_html_e( 'Queue Management', 'smart-cycle-discounts' ); ?></h3>
				<p class="description">
					<?php esc_html_e( 'Use these tools to manually manage the email queue.', 'smart-cycle-discounts' ); ?>
				</p>

				<button type="button" id="wsscd-process-queue" class="button button-primary" <?php disabled( $queue_status['queued'], 0 ); ?>>
					<?php
					WSSCD_Icon_Helper::render( 'play', array( 'size' => 16 ) );
					?>
					<?php esc_html_e( 'Process Queue Now', 'smart-cycle-discounts' ); ?>
				</button>

				<button type="button" id="wsscd-retry-failed" class="button button-secondary" <?php disabled( $queue_status['failed'], 0 ); ?>>
					<?php
					WSSCD_Icon_Helper::render( 'update', array( 'size' => 16 ) );
					?>
					<?php esc_html_e( 'Retry Failed Emails', 'smart-cycle-discounts' ); ?>
				</button>

				<button type="button" id="wsscd-clear-queue" class="button button-secondary">
					<?php
					WSSCD_Icon_Helper::render( 'delete', array( 'size' => 16 ) );
					?>
					<?php esc_html_e( 'Clear Old Queue Items', 'smart-cycle-discounts' ); ?>
				</button>

				<div id="wsscd-queue-result" class="notice" style="display: none; margin-top: 15px;"></div>
			</div>
		</div>
		<?php
		/* Styles moved to:
		 * - shared/_components.css (stat box, stat content, stat value, stat label, variants)
		 * - admin/notifications-page.css (queue-stats margin, queue-info, provider-info, queue-actions)
		 */
	}
}
