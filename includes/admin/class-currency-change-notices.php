<?php
/**
 * Currency Change Notices Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/class-currency-change-notices.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Currency Change Notices Class
 *
 * Displays admin notices when store currency changes, alerting users to
 * campaigns that were automatically paused due to fixed discount amounts.
 * Uses WordPress native admin notices with Asset Management System.
 *
 * Dismiss functionality handled by admin-notices-dismiss.js (registered in Script_Registry).
 * Nonces localized via Asset_Localizer (wsscdAdminNotices.nonces.currency).
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin
 */
class WSSCD_Currency_Change_Notices {

	/**
	 * Transient key for currency change data.
	 *
	 * @since    1.0.0
	 * @var      string
	 */
	const TRANSIENT_KEY = 'wsscd_currency_change_notice';

	/**
	 * Initialize notices.
	 *
	 * Registers admin_notices hook and AJAX handler for dismiss functionality.
	 * Dismiss JavaScript handled by admin-notices-dismiss.js via Asset Management System.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function init() {
		add_action( 'admin_notices', array( $this, 'display_currency_change_notice' ) );
		add_action( 'wp_ajax_wsscd_dismiss_currency_notice', array( $this, 'handle_dismiss_notice' ) );
	}

	/**
	 * Display currency change notice.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function display_currency_change_notice() {
		// Only show on SCD pages.
		if ( ! $this->should_show_notice() ) {
			return;
		}

		$notice_data = get_transient( self::TRANSIENT_KEY );

		if ( ! $notice_data ) {
			return;
		}

		$paused_count = $notice_data['paused_count'] ?? 0;
		$old_currency = $notice_data['old_currency'] ?? '';
		$new_currency = $notice_data['new_currency'] ?? '';

		// Enqueue minimal CSS (border color only).
		$this->enqueue_notice_styles();

		if ( 0 === $paused_count ) {
			// No campaigns affected.
			$this->render_safe_change_notice( $old_currency, $new_currency );
		} else {
			// Campaigns were paused.
			$this->render_paused_campaigns_notice( $paused_count, $old_currency, $new_currency );
		}
	}

	/**
	 * Check if notice should be displayed on current screen.
	 *
	 * Only shows on pages where users can take action:
	 * - Dashboard (overview)
	 * - Campaign list page (can view/edit affected campaigns)
	 *
	 * Does NOT show on:
	 * - Wizard (focused workflow - don't interrupt)
	 * - Edit pages (user is already focused on one campaign)
	 * - Settings/Analytics/Tools (irrelevant to currency changes)
	 *
	 * @since    1.0.0
	 * @return   bool    True if notice should show.
	 */
	private function should_show_notice() {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return false;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Checking URL params for display context only.
		// Show on main dashboard (overview page).
		if ( false !== strpos( $screen->id, 'smart-cycle-discounts' ) && ! isset( $_GET['page'] ) ) {
			return true;
		}

		// Show on campaigns list page ONLY (not wizard, not edit).
		if ( false !== strpos( $screen->id, 'wsscd-campaigns' ) ) {
			// Only on list view (no action parameter).
			return ! isset( $_GET['action'] );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return false;
	}

	/**
	 * Enqueue notice styles.
	 *
	 * Only CSS for border color (minimal, inline is appropriate).
	 * JavaScript handled by admin-notices-dismiss.js via Script_Registry.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function enqueue_notice_styles() {
		wp_add_inline_style(
			'wp-admin',
			'.notice.wsscd-currency-notice { border-left-color: #ff9800; }
			.notice.wsscd-currency-notice--paused { border-left-width: 4px; }'
		);
	}

	/**
	 * Render notice for safe currency change (no campaigns affected).
	 *
	 * @since    1.0.0
	 * @param    string $old_currency    Old currency code.
	 * @param    string $new_currency    New currency code.
	 * @return   void
	 */
	private function render_safe_change_notice( $old_currency, $new_currency ) {
		?>
		<div class="notice notice-info is-dismissible wsscd-currency-notice">
			<p>
				<strong><?php esc_html_e( 'Currency Changed', 'smart-cycle-discounts' ); ?></strong>
			</p>
			<p>
				<?php
				echo esc_html(
					sprintf(
						// translators: %1$s: old currency code, %2$s: new currency code.
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
	 * Render notice for campaigns paused due to currency change.
	 *
	 * @since    1.0.0
	 * @param    int    $paused_count    Number of campaigns paused.
	 * @param    string $old_currency    Old currency code.
	 * @param    string $new_currency    New currency code.
	 * @return   void
	 */
	private function render_paused_campaigns_notice( $paused_count, $old_currency, $new_currency ) {
		$review_url = admin_url( 'admin.php?page=wsscd-campaigns&action=currency-review' );
		?>
		<div class="notice notice-warning wsscd-currency-notice wsscd-currency-notice--paused">
			<p>
				<strong><?php esc_html_e( 'Currency Changed - Campaigns Paused for Review', 'smart-cycle-discounts' ); ?></strong>
			</p>
			<p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %1$s: old currency code, %2$s: new currency code, %3$d: number of campaigns */
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
				<button
					type="button"
					class="button wsscd-dismiss-notice"
					data-action="wsscd_dismiss_currency_notice"
					data-type="currency">
					<?php esc_html_e( 'Dismiss', 'smart-cycle-discounts' ); ?>
				</button>
			</p>
		</div>
		<?php
	}

	/**
	 * Handle AJAX request to dismiss notice.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function handle_dismiss_notice() {
		// Verify nonce.
		check_ajax_referer( 'wsscd_dismiss_currency_notice', '_wpnonce' );

		// Verify permissions.
		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'smart-cycle-discounts' ) ) );
		}

		// Delete the transient.
		delete_transient( self::TRANSIENT_KEY );

		wp_send_json_success( array( 'message' => __( 'Notice dismissed', 'smart-cycle-discounts' ) ) );
	}
}
