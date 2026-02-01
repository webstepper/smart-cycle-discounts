<?php
/**
 * Welcome Notice Class
 *
 * Displays a friendly welcome notice for first-time users
 * guiding them to create their first discount campaign.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.2.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Welcome Notice Class
 *
 * Shows a welcome message to new users until they:
 * - Create their first campaign, OR
 * - Dismiss the notice
 *
 * @since      1.2.2
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin
 */
class WSSCD_Welcome_Notice {

	/**
	 * User meta key for dismissed state.
	 *
	 * @since    1.2.2
	 * @var      string
	 */
	const DISMISSED_META = 'wsscd_welcome_notice_dismissed';

	/**
	 * Container instance.
	 *
	 * @since    1.2.2
	 * @access   private
	 * @var      object|null
	 */
	private $container = null;

	/**
	 * Constructor.
	 *
	 * @since    1.2.2
	 * @param    object|null $container    Optional container instance.
	 */
	public function __construct( $container = null ) {
		$this->container = $container;
	}

	/**
	 * Initialize the welcome notice.
	 *
	 * @since    1.2.2
	 * @return   void
	 */
	public function init() {
		add_action( 'admin_notices', array( $this, 'maybe_display_notice' ) );
		add_action( 'wp_ajax_wsscd_dismiss_welcome_notice', array( $this, 'handle_dismiss' ) );
		add_action( 'admin_footer', array( $this, 'output_dismiss_script' ) );
	}

	/**
	 * Check if notice should be displayed and display it.
	 *
	 * @since    1.2.2
	 * @return   void
	 */
	public function maybe_display_notice() {
		// Only show on SCD admin pages.
		if ( ! $this->is_scd_admin_page() ) {
			return;
		}

		// Check if should show.
		if ( ! $this->should_show_notice() ) {
			return;
		}

		$this->render_notice();
	}

	/**
	 * Check if welcome notice should be shown.
	 *
	 * @since    1.2.2
	 * @return   bool
	 */
	private function should_show_notice() {
		// Check user capability.
		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return false;
		}

		// Check if already dismissed.
		if ( get_user_meta( $user_id, self::DISMISSED_META, true ) ) {
			return false;
		}

		// Check if user has any campaigns (auto-dismiss after first campaign).
		$campaign_count = $this->get_campaign_count();
		if ( $campaign_count > 0 ) {
			// Auto-dismiss - user has created a campaign.
			update_user_meta( $user_id, self::DISMISSED_META, time() );
			return false;
		}

		return true;
	}

	/**
	 * Check if current page is an SCD admin page.
	 *
	 * @since    1.2.2
	 * @return   bool
	 */
	private function is_scd_admin_page() {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return false;
		}

		return strpos( $screen->id, 'smart-cycle-discounts' ) !== false ||
			   strpos( $screen->id, 'wsscd' ) !== false;
	}

	/**
	 * Get the total number of campaigns.
	 *
	 * @since    1.2.2
	 * @return   int
	 */
	private function get_campaign_count() {
		// Try container first.
		if ( $this->container && $this->container->has( 'campaign_repository' ) ) {
			try {
				$repo = $this->container->get( 'campaign_repository' );
				if ( method_exists( $repo, 'count' ) ) {
					return (int) $repo->count();
				}
			} catch ( Exception $e ) {
				// Fall through to direct query.
			}
		}

		// Fallback: direct database query.
		global $wpdb;
		$table = $wpdb->prefix . 'wsscd_campaigns';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time check for welcome notice.
		$table_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$table
			)
		);

		if ( ! $table_exists ) {
			return 0;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- One-time count; table name from trusted $wpdb->prefix.
		$count = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table} WHERE deleted_at IS NULL"
		);

		return (int) $count;
	}

	/**
	 * Render the welcome notice.
	 *
	 * @since    1.2.2
	 * @return   void
	 */
	private function render_notice() {
		$create_url = admin_url( 'admin.php?page=wsscd-campaigns&action=wizard&intent=new' );
		$nonce      = wp_create_nonce( 'wsscd_welcome_notice' );
		?>
		<div class="notice notice-info is-dismissible" id="wsscd-welcome-notice" style="padding: 12px 12px 12px 16px;">
			<p style="margin: 0 0 10px 0; font-size: 14px;">
				<strong><?php esc_html_e( '👋 Welcome to Smart Cycle Discounts!', 'smart-cycle-discounts' ); ?></strong><br>
				<?php esc_html_e( "Great to have you here! Let's get started — create your first discount campaign and watch your sales grow.", 'smart-cycle-discounts' ); ?>
			</p>
			<p style="margin: 0;">
				<a href="<?php echo esc_url( $create_url ); ?>" class="button button-primary"><?php esc_html_e( 'Create Campaign', 'smart-cycle-discounts' ); ?></a>
			</p>
			<button type="button" class="notice-dismiss" id="wsscd-welcome-dismiss" data-nonce="<?php echo esc_attr( $nonce ); ?>">
				<span class="screen-reader-text"><?php esc_html_e( 'Dismiss this notice.', 'smart-cycle-discounts' ); ?></span>
			</button>
		</div>
		<?php
	}

	/**
	 * Output the dismiss script.
	 *
	 * @since    1.2.2
	 * @return   void
	 */
	public function output_dismiss_script() {
		if ( ! $this->is_scd_admin_page() || ! $this->should_show_notice() ) {
			return;
		}
		?>
		<script>
		(function() {
			'use strict';
			var notice = document.getElementById('wsscd-welcome-notice');
			var dismiss = document.getElementById('wsscd-welcome-dismiss');

			if (notice && dismiss) {
				dismiss.addEventListener('click', function(e) {
					e.preventDefault();
					var xhr = new XMLHttpRequest();
					xhr.open('POST', ajaxurl, true);
					xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
					xhr.send('action=wsscd_dismiss_welcome_notice&_wpnonce=' + this.dataset.nonce);
					notice.parentNode.removeChild(notice);
				});
			}
		})();
		</script>
		<?php
	}

	/**
	 * Handle dismiss AJAX request.
	 *
	 * @since    1.2.2
	 * @return   void
	 */
	public function handle_dismiss() {
		check_ajax_referer( 'wsscd_welcome_notice', '_wpnonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'smart-cycle-discounts' ) ) );
		}

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid user.', 'smart-cycle-discounts' ) ) );
		}

		update_user_meta( $user_id, self::DISMISSED_META, time() );

		wp_send_json_success( array( 'message' => __( 'Notice dismissed.', 'smart-cycle-discounts' ) ) );
	}

	/**
	 * Clean up on plugin uninstall.
	 *
	 * @since    1.2.2
	 * @return   void
	 */
	public static function on_uninstall() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cleanup during uninstall.
		$wpdb->delete(
			$wpdb->usermeta,
			array( 'meta_key' => self::DISMISSED_META ),
			array( '%s' )
		);
	}
}
