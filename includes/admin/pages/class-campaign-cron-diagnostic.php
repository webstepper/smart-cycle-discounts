<?php
/**
 * Campaign Cron Diagnostic Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/pages/class-campaign-cron-diagnostic.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress/plugins/smart-cycle-discounts/
 * @since      1.0.0
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Campaign Cron Diagnostic Page
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/pages
 */
class WSSCD_Campaign_Cron_Diagnostic {

	/**
	 * Initialize the diagnostic page.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'add_diagnostic_page' ), 99 );
		add_action( 'admin_post_wsscd_test_cron', array( $this, 'handle_test_cron' ) );
	}

	/**
	 * Add diagnostic page to WordPress admin.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function add_diagnostic_page(): void {
		add_submenu_page(
			'tools.php',
			'Campaign Cron Diagnostic',
			'Campaign Cron',
			'manage_options',
			'wsscd-cron-diagnostic',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render the diagnostic page.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Access denied' );
		}

		$campaigns_with_events = $this->get_campaigns_with_events();
		$hook_status           = $this->check_hook_registration();
		$next_cron_run         = $this->get_next_safety_check();

		?>
		<div class="wrap">
			<h1>📊 Campaign Cron Diagnostic</h1>

			<div class="notice notice-info">
				<p>
					<strong>How It Works:</strong> This page helps you verify that campaign
					activation/deactivation events are properly scheduled and will execute when their time arrives.
				</p>
			</div>

			<!-- Hook Registration Status -->
			<div class="card">
				<h2>✅ Hook Registration Status</h2>
				<table class="widefat">
					<tr>
						<th>Universal Event Listener:</th>
						<td>
							<?php if ( $hook_status['universal_listener'] ) : ?>
								<span style="color: green;">✅ Registered</span>
								<p class="description">
									The 'all' hook is catching campaign events. This means activation/deactivation
									will work automatically for any campaign.
								</p>
							<?php else : ?>
								<span style="color: red;">❌ Not Registered</span>
								<p class="description">
									ERROR: The universal listener is not registered. Campaign events will not fire!
								</p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th>Next Safety Check:</th>
						<td>
							<?php if ( $next_cron_run ) : ?>
								<?php echo esc_html( human_time_diff( $next_cron_run ) ); ?> from now
								<br><small><?php echo esc_html( gmdate( 'Y-m-d H:i:s', $next_cron_run ) ); ?></small>
							<?php else : ?>
								<span style="color: orange;">⚠️ Not scheduled</span>
							<?php endif; ?>
						</td>
					</tr>
				</table>
			</div>

			<!-- Scheduled Campaign Events -->
			<div class="card">
				<h2>📅 Scheduled Campaign Events</h2>
				<?php if ( empty( $campaigns_with_events ) ) : ?>
					<p>No campaigns have scheduled activation/deactivation events.</p>
				<?php else : ?>
					<table class="widefat striped">
						<thead>
							<tr>
								<th>Campaign ID</th>
								<th>Event Type</th>
								<th>Scheduled Time</th>
								<th>Status</th>
								<th>Actions</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $campaigns_with_events as $event ) : ?>
								<tr>
									<td><?php echo esc_html( $event['campaign_id'] ); ?></td>
									<td>
										<?php if ( 'activate' === $event['type'] ) : ?>
											<span style="color: green;">▶️ Activation</span>
										<?php else : ?>
											<span style="color: red;">⏹️ Deactivation</span>
										<?php endif; ?>
									</td>
									<td>
										<?php echo esc_html( wp_date( 'Y-m-d H:i:s', $event['timestamp'] ) ); ?>
										<br>
										<small>
											<?php
											if ( $event['timestamp'] > time() ) {
												echo 'in ' . esc_html( human_time_diff( $event['timestamp'] ) );
											} else {
												echo esc_html( human_time_diff( $event['timestamp'] ) ) . ' ago';
											}
											?>
										</small>
									</td>
									<td>
										<?php if ( $event['timestamp'] > time() ) : ?>
											<span style="color: blue;">⏳ Pending</span>
										<?php else : ?>
											<span style="color: orange;">⚠️ Overdue</span>
										<?php endif; ?>
									</td>
									<td>
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: inline;">
											<?php wp_nonce_field( 'wsscd_test_cron' ); ?>
											<input type="hidden" name="action" value="wsscd_test_cron">
											<input type="hidden" name="campaign_id" value="<?php echo esc_attr( $event['campaign_id'] ); ?>">
											<input type="hidden" name="event_type" value="<?php echo esc_attr( $event['type'] ); ?>">
											<button type="submit" class="button button-small">
												Test Now
											</button>
										</form>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>

			<!-- Manual Testing -->
			<div class="card">
				<h2>🧪 Manual Testing</h2>
				<p>Use these tools to manually trigger campaign events for testing:</p>

				<h3>Trigger Safety Check</h3>
				<p>This runs the same process that the 15-minute cron job runs:</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'wsscd_test_cron' ); ?>
					<input type="hidden" name="action" value="wsscd_test_cron">
					<input type="hidden" name="trigger_safety_check" value="1">
					<button type="submit" class="button button-primary">
						Run Safety Check Now
					</button>
				</form>
			</div>

			<!-- WP-Cron Status -->
			<div class="card">
				<h2>⚙️ WordPress Cron Configuration</h2>
				<table class="widefat">
					<tr>
						<th>DISABLE_WP_CRON:</th>
						<td>
							<?php if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) : ?>
								<span style="color: green;">✅ Disabled (using real cron)</span>
							<?php else : ?>
								<span style="color: blue;">ℹ️ Enabled (using WP-Cron)</span>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th>ALTERNATE_WP_CRON:</th>
						<td>
							<?php if ( defined( 'ALTERNATE_WP_CRON' ) && ALTERNATE_WP_CRON ) : ?>
								<span style="color: green;">✅ Enabled</span>
							<?php else : ?>
								<span>ℹ️ Disabled</span>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th>Environment:</th>
						<td><?php echo esc_html( $this->get_environment_type() ); ?></td>
					</tr>
				</table>

				<?php if ( ! defined( 'DISABLE_WP_CRON' ) || ! DISABLE_WP_CRON ) : ?>
					<?php if ( $this->is_production_environment() ) : ?>
						<div class="notice notice-warning inline">
							<p>
								<strong>⚠️ Recommendation for Production:</strong>
								For best reliability, consider using real cron instead of WP-Cron.
								<a href="https://developer.wordpress.org/plugins/cron/hooking-wp-cron-into-the-system-task-scheduler/" target="_blank">
									Learn more
								</a>
							</p>
						</div>
					<?php endif; ?>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Get all campaigns with scheduled events.
	 *
	 * @since    1.0.0
	 * @return   array    Campaign events.
	 */
	private function get_campaigns_with_events(): array {
		$crons  = _get_cron_array();
		$events = array();

		if ( ! $crons ) {
			return $events;
		}

		foreach ( $crons as $timestamp => $cron ) {
			foreach ( $cron as $hook => $details ) {
				// Match activation hooks
				if ( preg_match( '/^wsscd_activate_campaign_(\d+)$/', $hook, $matches ) ) {
					$events[] = array(
						'campaign_id' => (int) $matches[1],
						'type'        => 'activate',
						'timestamp'   => $timestamp,
						'hook'        => $hook,
					);
				}

				// Match deactivation hooks
				if ( preg_match( '/^wsscd_deactivate_campaign_(\d+)$/', $hook, $matches ) ) {
					$events[] = array(
						'campaign_id' => (int) $matches[1],
						'type'        => 'deactivate',
						'timestamp'   => $timestamp,
						'hook'        => $hook,
					);
				}
			}
		}

		usort(
			$events,
			function ( $a, $b ) {
				return $a['timestamp'] - $b['timestamp'];
			}
		);

		return $events;
	}

	/**
	 * Check hook registration status.
	 *
	 * @since    1.0.0
	 * @return   array    Hook status.
	 */
	private function check_hook_registration(): array {
		global $wp_filter;

		$universal_listener = false;

		if ( isset( $wp_filter['all'] ) ) {
			foreach ( $wp_filter['all']->callbacks as $priority => $callbacks ) {
				foreach ( $callbacks as $callback ) {
					if ( isset( $callback['function'] ) && is_array( $callback['function'] ) ) {
						if ( isset( $callback['function'][1] ) && 'intercept_campaign_events' === $callback['function'][1] ) {
							$universal_listener = true;
							break 2;
						}
					}
				}
			}
		}

		return array(
			'universal_listener' => $universal_listener,
		);
	}

	/**
	 * Get next safety check run time.
	 *
	 * @since    1.0.0
	 * @return   int|false    Timestamp or false.
	 */
	private function get_next_safety_check() {
		return wp_next_scheduled( 'wsscd_update_campaign_status' );
	}

	/**
	 * Handle test cron form submission.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function handle_test_cron(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Access denied' );
		}

		check_admin_referer( 'wsscd_test_cron' );

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified above via check_admin_referer().
		if ( isset( $_POST['trigger_safety_check'] ) ) {
			// Trigger the safety check
			do_action( 'wsscd_update_campaign_status' );

			wp_safe_redirect(
				add_query_arg(
					array(
						'page'    => 'wsscd-cron-diagnostic',
						'message' => 'safety_check_run',
					),
					admin_url( 'tools.php' )
				)
			);
			exit;
		}

		if ( isset( $_POST['campaign_id'] ) && isset( $_POST['event_type'] ) ) {
			$campaign_id = absint( $_POST['campaign_id'] );
			$event_type  = sanitize_text_field( wp_unslash( $_POST['event_type'] ) );

			// Trigger the event
			if ( 'activate' === $event_type ) {
				do_action( 'wsscd_activate_campaign_' . $campaign_id, $campaign_id );
			} elseif ( 'deactivate' === $event_type ) {
				do_action( 'wsscd_deactivate_campaign_' . $campaign_id, $campaign_id );
			}

			wp_safe_redirect(
				add_query_arg(
					array(
						'page'        => 'wsscd-cron-diagnostic',
						'message'     => 'event_triggered',
						'campaign_id' => $campaign_id,
						'event_type'  => $event_type,
					),
					admin_url( 'tools.php' )
				)
			);
			// phpcs:enable WordPress.Security.NonceVerification.Missing
			exit;
		}

		wp_safe_redirect( admin_url( 'tools.php?page=wsscd-cron-diagnostic' ) );
		exit;
	}

	/**
	 * Get environment type.
	 *
	 * @since    1.0.0
	 * @return   string    Environment type.
	 */
	private function get_environment_type(): string {
		$host = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';

		if ( strpos( $host, 'localhost' ) !== false || strpos( $host, '.local' ) !== false ) {
			return '💻 Local Development';
		}

		if ( function_exists( 'wp_get_environment_type' ) ) {
			$env = wp_get_environment_type();
			if ( 'production' === $env ) {
				return '🌍 Production';
			} elseif ( 'staging' === $env ) {
				return '🚀 Staging';
			}
		}

		return '❓ Unknown';
	}

	/**
	 * Check if this is a production environment.
	 *
	 * @since    1.0.0
	 * @return   bool    True if production.
	 */
	private function is_production_environment(): bool {
		$host = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';

		// Not production if localhost or .local
		if ( strpos( $host, 'localhost' ) !== false || strpos( $host, '.local' ) !== false ) {
			return false;
		}

		// Check WordPress environment type
		if ( function_exists( 'wp_get_environment_type' ) ) {
			return 'production' === wp_get_environment_type();
		}

		// Assume production if we can't determine
		return true;
	}
}
