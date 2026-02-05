<?php
/**
 * License Notices Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/licensing/class-license-notices.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress/plugins/smart-cycle-discounts/
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * License Notices Class
 *
 * Handles displaying license-related admin notices.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/licensing
 * @author     Webstepper <contact@webstepper.io>
 */
class WSSCD_License_Notices {

	/**
	 * Initialize the notices.
	 *
	 * @since    1.0.0
	 */
	public function init() {
		add_action( 'admin_notices', array( $this, 'show_license_notices' ) );
		add_action( 'wp_ajax_wsscd_dismiss_expired_notice', array( $this, 'handle_dismiss_expired_notice' ) );
	}

	/**
	 * Show license-related admin notices.
	 *
	 * IMPORTANT: This class handles ONLY critical license warnings for Pro users.
	 * Free user upgrade prompts are handled by WSSCD_Upgrade_Prompt_Manager.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function show_license_notices() {
		// Only show on plugin pages
		$screen = get_current_screen();
		if ( ! $screen || false === strpos( $screen->id, 'smart-cycle-discounts' ) && false === strpos( $screen->id, 'wsscd-' ) ) {
			return;
		}

		// Don't show admin notices on wizard pages - they break the layout
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display context check.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display context check.
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		if ( 'wsscd-campaigns' === $page && 'wizard' === $action ) {
			return;
		}

		// Only show to users who can manage options
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Show ONLY critical license warnings for pro users with expired licenses
		// Free user upgrade prompts are handled by Upgrade Prompt Manager (inline banners)
		if ( function_exists( 'wsscd_is_license_expired' ) && wsscd_is_license_expired() ) {
			// Pro user with expired license - show critical notice (3-day cycle)
			$this->show_expired_license_notice();
		}
	}

	/**
	 * Show critical notice for pro users with expired license.
	 *
	 * This is a critical admin notice that appears when a paying customer's
	 * license has expired. It shows every 3 days after dismissal.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function show_expired_license_notice() {
		$user_id   = get_current_user_id();
		$dismissed = get_user_meta( $user_id, 'wsscd_dismissed_expired_notice', true );

		if ( $dismissed && $dismissed > time() ) {
			return;
		}

		$account_url = admin_url( 'admin.php?page=smart-cycle-discounts-account' );
		$upgrade_url = function_exists( 'wsscd_get_upgrade_url' ) ? wsscd_get_upgrade_url() : admin_url( 'admin.php?page=smart-cycle-discounts-pricing' );

		?>
		<div class="notice notice-error is-dismissible wsscd-expired-notice" data-notice-id="license_expired">
			<p>
				<strong><?php esc_html_e( 'Smart Cycle Discounts - License Expired', 'smart-cycle-discounts' ); ?>:</strong>
				<?php esc_html_e( 'Your Pro license has expired. Please renew to continue using premium features.', 'smart-cycle-discounts' ); ?>
			</p>
			<p>
				<a href="<?php echo esc_url( $account_url ); ?>" class="button button-primary">
					<?php esc_html_e( 'Renew License', 'smart-cycle-discounts' ); ?>
				</a>
				<a href="<?php echo esc_url( $upgrade_url ); ?>" class="button button-secondary">
					<?php esc_html_e( 'View Pricing', 'smart-cycle-discounts' ); ?>
				</a>
			</p>
		</div>
		<?php
		// Use wp_add_inline_script for WordPress.org compliance
		$nonce  = wp_create_nonce( 'wsscd_dismiss_expired_notice' );
		$script = 'jQuery(document).ready(function($) {' .
			'$(".wsscd-expired-notice").on("click", ".notice-dismiss", function() {' .
			'$.post(ajaxurl, { action: "wsscd_dismiss_expired_notice", nonce: "' . esc_js( $nonce ) . '" });' .
			'});' .
			'});';
		wp_add_inline_script( 'jquery-core', $script );
	}

	/**
	 * Handle dismissing expired license notice.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function handle_dismiss_expired_notice() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wsscd_dismiss_expired_notice' ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		// Dismiss for 3 days (critical license notice)
		$user_id = get_current_user_id();
		update_user_meta( $user_id, 'wsscd_dismissed_expired_notice', time() + ( 3 * DAY_IN_SECONDS ) );

		wp_send_json_success();
	}
}
