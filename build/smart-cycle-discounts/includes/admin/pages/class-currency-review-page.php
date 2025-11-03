<?php
/**
 * Currency Review Page Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/pages/class-currency-review-page.php
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Currency Review Page Class
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/pages
 */
class SCD_Currency_Review_Page {

	/**
	 * Currency change service instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Currency_Change_Service    $currency_service    Currency service.
	 */
	private $currency_service;

	/**
	 * Constructor.
	 *
	 * @since    1.0.0
	 * @param    SCD_Currency_Change_Service $currency_service    Currency service instance.
	 */
	public function __construct( $currency_service = null ) {
		$this->currency_service = $currency_service;
	}

	/**
	 * Initialize page.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function init() {
		// Add admin menu item conditionally
		add_action( 'admin_menu', array( $this, 'add_currency_review_notice' ), 100 );

		// Register AJAX handlers
		add_action( 'wp_ajax_scd_currency_review_action', array( $this, 'handle_review_action' ) );
	}

	/**
	 * Add currency review menu notice.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function add_currency_review_notice() {
		// Load service if needed
		if ( ! $this->currency_service ) {
			require_once SCD_INCLUDES_DIR . 'core/services/class-currency-change-service.php';
			$this->currency_service = new SCD_Currency_Change_Service();
		}

		// Check if there are campaigns needing review
		$needing_review = $this->currency_service->get_campaigns_needing_review();

		// If campaigns need review, add menu badge via JavaScript
		if ( ! empty( $needing_review ) ) {
			add_action( 'admin_footer', array( $this, 'add_menu_badge_script' ) );
		}
	}

	/**
	 * Add menu badge script.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function add_menu_badge_script() {
		$needing_review = $this->currency_service->get_campaigns_needing_review();
		$count          = count( $needing_review );
		?>
		<script>
		jQuery(document).ready(function($) {
			// Add badge to campaigns menu item
			$('a[href*="page=scd-campaigns"]').first().append(' <span class="update-plugins"><span class="plugin-count"><?php echo esc_js( $count ); ?></span></span>');
		});
		</script>
		<?php
	}

	/**
	 * Render currency review page.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function render_page() {
		// Load service if needed
		if ( ! $this->currency_service ) {
			require_once SCD_INCLUDES_DIR . 'core/services/class-currency-change-service.php';
			$this->currency_service = new SCD_Currency_Change_Service();
		}

		// Get campaigns needing review
		$campaigns = $this->currency_service->get_campaigns_needing_review();

		if ( empty( $campaigns ) ) {
			$this->render_no_campaigns_message();
			return;
		}

		// Get current currency
		$current_currency = get_woocommerce_currency();
		$current_symbol   = get_woocommerce_currency_symbol();

		// Render page
		include SCD_PLUGIN_DIR . 'resources/views/admin/pages/currency-review.php';
	}

	/**
	 * Render no campaigns message.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function render_no_campaigns_message() {
		?>
		<div class="scd-currency-review-empty">
			<div class="notice notice-success inline">
				<p>
					<strong><?php esc_html_e( 'All Clear!', 'smart-cycle-discounts' ); ?></strong>
				</p>
				<p>
					<?php esc_html_e( 'No campaigns require currency review at this time.', 'smart-cycle-discounts' ); ?>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle AJAX review action.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function handle_review_action() {
		// Verify nonce
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'scd_currency_review' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed', 'smart-cycle-discounts' ) ) );
		}

		// Verify permissions
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'smart-cycle-discounts' ) ) );
		}

		// Get action and campaign ID
		$action      = isset( $_POST['review_action'] ) ? sanitize_text_field( $_POST['review_action'] ) : '';
		$campaign_id = isset( $_POST['campaign_id'] ) ? intval( $_POST['campaign_id'] ) : 0;

		if ( ! $campaign_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid campaign ID', 'smart-cycle-discounts' ) ) );
		}

		// Load service if needed
		if ( ! $this->currency_service ) {
			require_once SCD_INCLUDES_DIR . 'core/services/class-currency-change-service.php';
			$this->currency_service = new SCD_Currency_Change_Service();
		}

		// Handle action
		switch ( $action ) {
			case 'approve_and_resume':
				$result = $this->approve_and_resume_campaign( $campaign_id );
				break;

			case 'mark_reviewed':
				$result = $this->mark_campaign_reviewed( $campaign_id );
				break;

			case 'archive':
				$result = $this->archive_campaign( $campaign_id );
				break;

			default:
				wp_send_json_error( array( 'message' => __( 'Invalid action', 'smart-cycle-discounts' ) ) );
		}

		if ( $result ) {
			wp_send_json_success(
				array(
					'message'     => __( 'Campaign updated successfully', 'smart-cycle-discounts' ),
					'campaign_id' => $campaign_id,
				)
			);
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to update campaign', 'smart-cycle-discounts' ) ) );
		}
	}

	/**
	 * Approve and resume campaign.
	 *
	 * @since    1.0.0
	 * @param    int $campaign_id    Campaign ID.
	 * @return   bool                   True on success.
	 */
	private function approve_and_resume_campaign( $campaign_id ) {
		$success = $this->currency_service->restore_campaign_status( $campaign_id );

		if ( $success ) {
			do_action( 'scd_currency_review_approved', $campaign_id );
		}

		return $success;
	}

	/**
	 * Mark campaign as reviewed (keep paused).
	 *
	 * @since    1.0.0
	 * @param    int $campaign_id    Campaign ID.
	 * @return   bool                   True on success.
	 */
	private function mark_campaign_reviewed( $campaign_id ) {
		$success = $this->currency_service->clear_review_flag( $campaign_id );

		if ( $success ) {
			do_action( 'scd_currency_review_marked', $campaign_id );
		}

		return $success;
	}

	/**
	 * Archive campaign.
	 *
	 * @since    1.0.0
	 * @param    int $campaign_id    Campaign ID.
	 * @return   bool                   True on success.
	 */
	private function archive_campaign( $campaign_id ) {
		require_once SCD_INCLUDES_DIR . 'database/repositories/class-campaign-repository.php';
		$repository = new SCD_Campaign_Repository();

		try {
			$campaign = $repository->find_by_id( $campaign_id );

			if ( ! $campaign ) {
				return false;
			}

			// Clear review flag first
			$this->currency_service->clear_review_flag( $campaign_id );

			// Set to draft status (archives effectively)
			$campaign->set_status( 'draft' );
			$campaign->set_meta( 'archived_from_currency_review', true );

			$repository->update( $campaign );

			do_action( 'scd_currency_review_archived', $campaign_id );

			return true;
		} catch ( Exception $e ) {
			error_log(
				sprintf(
					'[SCD Currency Review] Failed to archive campaign #%d: %s',
					$campaign_id,
					$e->getMessage()
				)
			);
			return false;
		}
	}
}
