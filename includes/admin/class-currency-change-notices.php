<?php
/**
 * Currency Change Notices
 *
 * Handles display of admin notices for currency changes.
 *
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin
 */

declare(strict_types=1);


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Currency Change Notices Class
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin
 */
class SCD_Currency_Change_Notices {

	/**
	 * Initialize notices.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function init() {
		add_action( 'admin_notices', array( $this, 'display_currency_change_notice' ) );
		add_action( 'wp_ajax_scd_dismiss_currency_notice', array( $this, 'handle_dismiss_notice' ) );
	}

	/**
	 * Display currency change notice.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function display_currency_change_notice() {
		// Only show on SCD pages
		$screen = get_current_screen();
		if ( ! $screen || false === strpos( $screen->id, 'scd' ) ) {
			return;
		}

		// Check for notice data
		$notice_data = get_transient( 'scd_currency_change_notice' );

		if ( ! $notice_data ) {
			return;
		}

		// Extract notice data
		$paused_count = $notice_data['paused_count'] ?? 0;
		$old_currency = $notice_data['old_currency'] ?? '';
		$new_currency = $notice_data['new_currency'] ?? '';

		if ( 0 === $paused_count ) {
			// No campaigns affected
			$this->display_safe_currency_change_notice( $old_currency, $new_currency );
		} else {
			// Campaigns were paused
			$this->display_campaigns_paused_notice( $paused_count, $old_currency, $new_currency );
		}
	}

	/**
	 * Display notice for safe currency change (no campaigns affected).
	 *
	 * @since    1.0.0
	 * @param    string $old_currency    Old currency code.
	 * @param    string $new_currency    New currency code.
	 * @return   void
	 */
	private function display_safe_currency_change_notice( $old_currency, $new_currency ) {
		?>
		<div class="notice notice-info is-dismissible scd-currency-notice" data-notice-id="scd_currency_change">
			<p>
				<strong><?php esc_html_e( 'Currency Changed', 'smart-cycle-discounts' ); ?></strong>
			</p>
			<p>
				<?php
				echo esc_html(
					sprintf(
						__( 'Your store currency has been changed from %1$s to %2$s. No active campaigns were affected because you only have percentage-based or BOGO discounts running.', 'smart-cycle-discounts' ),
						$old_currency,
						$new_currency
					)
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Display notice for campaigns paused due to currency change.
	 *
	 * @since    1.0.0
	 * @param    int    $paused_count    Number of campaigns paused.
	 * @param    string $old_currency    Old currency code.
	 * @param    string $new_currency    New currency code.
	 * @return   void
	 */
	private function display_campaigns_paused_notice( $paused_count, $old_currency, $new_currency ) {
		$review_url = admin_url( 'admin.php?page=scd-campaigns&action=currency-review' );
		?>
		<div class="notice notice-warning scd-currency-notice scd-currency-notice--paused">
			<p>
				<strong><?php esc_html_e( 'Currency Changed - Campaigns Paused for Review', 'smart-cycle-discounts' ); ?></strong>
			</p>
			<p>
				<?php
				echo esc_html(
					sprintf(
						_n(
							'Your store currency has been changed from %1$s to %2$s. %3$d campaign with fixed discount amounts has been automatically paused for your review.',
							'Your store currency has been changed from %1$s to %2$s. %3$d campaigns with fixed discount amounts have been automatically paused for your review.',
							$paused_count,
							'smart-cycle-discounts'
						),
						$old_currency,
						$new_currency,
						$paused_count
					)
				);
				?>
			</p>
			<p>
				<?php esc_html_e( 'Percentage-based and BOGO campaigns remain active as they work with any currency.', 'smart-cycle-discounts' ); ?>
			</p>
			<p>
				<a href="<?php echo esc_url( $review_url ); ?>" class="button button-primary">
					<?php esc_html_e( 'Review Campaigns', 'smart-cycle-discounts' ); ?>
				</a>
				<button type="button" class="button" data-dismiss-notice="scd_currency_change">
					<?php esc_html_e( 'Dismiss', 'smart-cycle-discounts' ); ?>
				</button>
			</p>
		</div>
		<style>
		.scd-currency-notice {
			border-left-color: #ff9800;
		}
		.scd-currency-notice p {
			margin: 0.5em 0;
		}
		.scd-currency-notice p:first-of-type {
			margin-top: 0;
		}
		.scd-currency-notice p:last-of-type {
			margin-bottom: 0;
		}
		.scd-currency-notice--paused {
			border-left-width: 4px;
		}
		</style>
		<script>
		jQuery(document).ready(function($) {
			$('[data-dismiss-notice]').on('click', function() {
				var noticeId = $(this).data('dismiss-notice');
				$.post(ajaxurl, {
					action: 'scd_dismiss_currency_notice',
					notice_id: noticeId,
					_wpnonce: '<?php echo esc_js( wp_create_nonce( 'scd_dismiss_notice' ) ); ?>'
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
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'scd_dismiss_notice' ) ) {
			wp_die( 'Security check failed' );
		}

		// Verify permissions
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'Permission denied' );
		}

		// Delete transient
		delete_transient( 'scd_currency_change_notice' );

		wp_send_json_success();
	}
}
