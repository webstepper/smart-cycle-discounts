<?php
/**
 * Campaign Expiration Notices Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/class-campaign-expiration-notices.php
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
 * Campaign Expiration Notices Class
 *
 * Displays admin notices when campaigns auto-expire due to their end date.
 * Uses WordPress native admin notices with Asset Management System.
 *
 * Dismiss functionality handled by admin-notices-dismiss.js (registered in Script_Registry).
 * Nonces localized via Asset_Localizer (wsscdAdminNotices.nonces.expiration).
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin
 */
class WSSCD_Campaign_Expiration_Notices {

	/**
	 * Transient key for expired campaigns data.
	 *
	 * @since    1.0.0
	 * @var      string
	 */
	const TRANSIENT_KEY = 'wsscd_recently_expired_campaigns';

	/**
	 * Time window for "recent" expiration (24 hours).
	 *
	 * @since    1.0.0
	 * @var      int
	 */
	const RECENT_WINDOW = DAY_IN_SECONDS;

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
		add_action( 'admin_notices', array( $this, 'display_expiration_notice' ) );
		add_action( 'wp_ajax_wsscd_dismiss_expiration_notice', array( $this, 'handle_dismiss_notice' ) );
	}

	/**
	 * Display campaign expiration notice.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function display_expiration_notice() {
		// Only show on SCD pages.
		if ( ! $this->should_show_notice() ) {
			return;
		}

		$expired_campaigns = get_transient( self::TRANSIENT_KEY );

		if ( ! $expired_campaigns || ! is_array( $expired_campaigns ) ) {
			return;
		}

		$recent_expired = array_filter( $expired_campaigns, array( $this, 'is_recent_expiration' ) );

		if ( empty( $recent_expired ) ) {
			// All campaigns are old, clear the transient.
			delete_transient( self::TRANSIENT_KEY );
			return;
		}

		// Enqueue minimal CSS (border color only).
		$this->enqueue_notice_styles();

		// Display notice.
		$this->render_notice( $recent_expired );
	}

	/**
	 * Check if notice should be displayed on current screen.
	 *
	 * Only shows on pages where users can take action:
	 * - Dashboard (overview)
	 * - Campaign list page (can view/edit expired campaigns)
	 *
	 * Does NOT show on:
	 * - Wizard (focused workflow - don't interrupt)
	 * - Edit pages (user is already focused on one campaign)
	 * - Settings/Analytics/Tools (irrelevant to expiration)
	 *
	 * @since    1.0.0
	 * @return   bool    True if notice should show.
	 */
	private function should_show_notice() {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return false;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Safe: read-only display context check, no data modification.
		// Show on main dashboard (overview page).
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		if ( false !== strpos( $screen->id, 'smart-cycle-discounts' ) && empty( $page ) ) {
			return true;
		}

		// Show on campaigns list page ONLY (not wizard, not edit).
		if ( false !== strpos( $screen->id, 'wsscd-campaigns' ) ) {
			// Only on list view (no action parameter).
			$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';
			return empty( $action );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return false;
	}

	/**
	 * Check if campaign expiration is recent (within 24 hours).
	 *
	 * @since    1.0.0
	 * @param    array $campaign    Campaign data.
	 * @return   bool               True if recent.
	 */
	public function is_recent_expiration( $campaign ) {
		if ( ! isset( $campaign['time'] ) ) {
			return false;
		}

		$time_diff = time() - $campaign['time'];
		return $time_diff < self::RECENT_WINDOW;
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
			'.notice.wsscd-expiration-notice { border-left-color: #2196F3; border-left-width: 4px; }
			.notice.wsscd-expiration-notice ul { list-style: disc; margin-left: 20px; margin-top: 8px; margin-bottom: 8px; }'
		);
	}

	/**
	 * Render the expiration notice.
	 *
	 * @since    1.0.0
	 * @param    array $expired_campaigns    Array of expired campaign data.
	 * @return   void
	 */
	private function render_notice( $expired_campaigns ) {
		$count       = count( $expired_campaigns );
		$expired_url = admin_url( 'admin.php?page=wsscd-campaigns&status=expired' );
		?>
		<div class="notice notice-info wsscd-expiration-notice">
			<p>
				<strong><?php esc_html_e( 'Campaign Expiration Notice', 'smart-cycle-discounts' ); ?></strong>
			</p>
			<p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: number of campaigns that have expired */
						_n(
							'%d campaign has automatically expired after reaching its end date.',
							'%d campaigns have automatically expired after reaching their end dates.',
							$count,
							'smart-cycle-discounts'
						),
						$count
					)
				);
				?>
			</p>
			<?php if ( $count <= 5 ) : ?>
				<ul>
					<?php foreach ( $expired_campaigns as $campaign ) : ?>
						<li>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=wsscd-campaigns&action=wizard&intent=edit&id=' . $campaign['id'] ) ); ?>">
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
				<button
					type="button"
					class="button wsscd-dismiss-notice"
					data-action="wsscd_dismiss_expiration_notice"
					data-type="expiration">
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
		check_ajax_referer( 'wsscd_dismiss_expiration_notice', '_wpnonce' );

		// Verify permissions.
		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'smart-cycle-discounts' ) ) );
		}

		// Delete the transient.
		delete_transient( self::TRANSIENT_KEY );

		wp_send_json_success( array( 'message' => __( 'Notice dismissed', 'smart-cycle-discounts' ) ) );
	}
}
