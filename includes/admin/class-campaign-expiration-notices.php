<?php
/**
 * Campaign Expiration Notices
 *
 * Handles display of admin notices for campaigns that have auto-expired.
 *
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin
 */

// Removed strict types for PHP compatibility

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Campaign Expiration Notices Class
 *
 * Displays admin notices when campaigns auto-expire due to their end date.
 * This helps users stay informed when paused or active campaigns expire
 * automatically based on their configured end date.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin
 */
class SCD_Campaign_Expiration_Notices {

	/**
	 * Initialize notices.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function init() {
		add_action( 'admin_notices', array( $this, 'display_expiration_notice' ) );
		add_action( 'wp_ajax_scd_dismiss_expiration_notice', array( $this, 'handle_dismiss_notice' ) );
	}

	/**
	 * Display campaign expiration notice.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function display_expiration_notice() {
		// Only show on SCD pages
		$screen = get_current_screen();
		if ( ! $screen || false === strpos( $screen->id, 'scd' ) ) {
			return;
		}

		// Check for expired campaigns
		$expired_campaigns = get_transient( 'scd_recently_expired_campaigns' );

		if ( ! $expired_campaigns || ! is_array( $expired_campaigns ) ) {
			return;
		}

		// Filter campaigns expired in the last 24 hours
		$recent_expired = array_filter( $expired_campaigns, array( $this, 'is_recent_expiration' ) );

		if ( empty( $recent_expired ) ) {
			// All campaigns are old, clear the transient
			delete_transient( 'scd_recently_expired_campaigns' );
			return;
		}

		// Display notice
		$this->display_campaigns_expired_notice( $recent_expired );
	}

	/**
	 * Check if campaign expiration is recent (within 24 hours).
	 *
	 * @since    1.0.0
	 * @param    array    $campaign    Campaign data.
	 * @return   bool                  True if recent.
	 */
	private function is_recent_expiration( $campaign ) {
		if ( ! isset( $campaign['time'] ) ) {
			return false;
		}

		$time_diff = time() - $campaign['time'];
		return $time_diff < DAY_IN_SECONDS;
	}

	/**
	 * Display notice for expired campaigns.
	 *
	 * @since    1.0.0
	 * @param    array    $expired_campaigns    Array of expired campaign data.
	 * @return   void
	 */
	private function display_campaigns_expired_notice( $expired_campaigns ) {
		$count = count( $expired_campaigns );
		$expired_url = admin_url( 'admin.php?page=scd-campaigns&status=expired' );
		?>
		<div class="notice notice-info scd-expiration-notice">
			<p>
				<strong><?php esc_html_e( 'Campaign Expiration Notice', 'smart-cycle-discounts' ); ?></strong>
			</p>
			<p>
				<?php
				echo esc_html( sprintf(
					_n(
						'%d campaign has automatically expired after reaching its end date.',
						'%d campaigns have automatically expired after reaching their end dates.',
						$count,
						'smart-cycle-discounts'
					),
					$count
				) );
				?>
			</p>
			<?php if ( $count <= 5 ) : ?>
				<ul style="list-style: disc; margin-left: 20px; margin-top: 8px;">
					<?php foreach ( $expired_campaigns as $campaign ) : ?>
						<li>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=scd-campaigns&action=edit&id=' . $campaign['id'] ) ); ?>">
								<?php echo esc_html( $campaign['name'] ); ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<p>
				<a href="<?php echo esc_url( $expired_url ); ?>" class="button button-primary">
					<?php esc_html_e( 'View Expired Campaigns', 'smart-cycle-discounts' ); ?>
				</a>
				<button type="button" class="button scd-dismiss-expiration-notice">
					<?php esc_html_e( 'Dismiss', 'smart-cycle-discounts' ); ?>
				</button>
			</p>
		</div>
		<style>
		.scd-expiration-notice {
			border-left-color: #2196F3;
			border-left-width: 4px;
		}
		.scd-expiration-notice p {
			margin: 0.5em 0;
		}
		.scd-expiration-notice p:first-of-type {
			margin-top: 0;
		}
		.scd-expiration-notice p:last-of-type {
			margin-bottom: 0;
		}
		.scd-expiration-notice ul {
			margin-bottom: 8px;
		}
		</style>
		<script>
		jQuery(document).ready(function($) {
			$('.scd-dismiss-expiration-notice').on('click', function() {
				$.post(ajaxurl, {
					action: 'scd_dismiss_expiration_notice',
					_wpnonce: '<?php echo esc_js( wp_create_nonce( 'scd_dismiss_expiration_notice' ) ); ?>'
				});
				$(this).closest('.notice').fadeOut();
			});
		});
		</script>
		<?php
	}

	/**
	 * Handle AJAX request to dismiss notice.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function handle_dismiss_notice() {
		// Verify nonce
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'scd_dismiss_expiration_notice' ) ) {
			wp_die( 'Security check failed' );
		}

		// Verify permissions
		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Permission denied' );
		}

		// Delete transient
		delete_transient( 'scd_recently_expired_campaigns' );

		wp_send_json_success();
	}
}
