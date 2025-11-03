<?php
/**
 * Upgrade Prompt Manager Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/licensing/class-upgrade-prompt-manager.php
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Upgrade Prompt Manager Class
 *
 * Controls when and how upgrade prompts are displayed to free users.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/licensing
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Upgrade_Prompt_Manager {

	/**
	 * Feature Gate instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Feature_Gate    $feature_gate    Feature gate service.
	 */
	private $feature_gate;

	/**
	 * Maximum prompts per day.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      int    $max_prompts_per_day    Maximum prompts per day.
	 */
	private $max_prompts_per_day = 2;

	/**
	 * Transient key prefix for tracking prompts.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $transient_prefix    Transient key prefix.
	 */
	private $transient_prefix = 'scd_upgrade_prompts_';

	/**
	 * Initialize the manager.
	 *
	 * @since    1.0.0
	 * @param    SCD_Feature_Gate $feature_gate    Feature gate service.
	 */
	public function __construct( SCD_Feature_Gate $feature_gate ) {
		$this->feature_gate = $feature_gate;

		// Register AJAX handler for dismissing banners
		add_action( 'wp_ajax_scd_dismiss_upgrade_banner', array( $this, 'handle_dismiss_banner' ) );
	}

	/**
	 * Get promotional settings.
	 *
	 * Use filter 'scd_upgrade_promotion_active' to enable/disable promotions.
	 * Use filter 'scd_upgrade_promotion_settings' to customize promotion details.
	 *
	 * @since    1.0.0
	 * @return   array    Promotional settings array.
	 */
	public function get_promotion_settings() {
		// Check if promotion is active (can be controlled via filter)
		// Default: true (enabled) - set to false to disable promotions
		$is_active = apply_filters( 'scd_upgrade_promotion_active', true );

		if ( ! $is_active ) {
			return array( 'promotion' => false );
		}

		// Default promotional settings
		$defaults = array(
			'promotion'     => true,
			'discount_text' => __( 'Save 30%', 'smart-cycle-discounts' ),
			'urgency_text'  => __( 'Limited Time Offer - Ends Soon!', 'smart-cycle-discounts' ),
		);

		// Allow customization via filter
		return apply_filters( 'scd_upgrade_promotion_settings', $defaults );
	}

	/**
	 * Check if a banner has been dismissed by the user.
	 *
	 * @since    1.0.0
	 * @param    string $banner_id    Banner identifier.
	 * @return   bool                    True if dismissed.
	 */
	public function is_banner_dismissed( $banner_id = 'dashboard_analytics' ) {
		$dismissed = get_user_meta( get_current_user_id(), 'scd_dismissed_upgrade_banner_' . $banner_id, true );

		// If dismissed, check if enough time has passed to show again (30 days)
		if ( $dismissed ) {
			$dismissed_time = absint( $dismissed );
			$time_elapsed   = time() - $dismissed_time;
			$reshow_after   = 30 * DAY_IN_SECONDS; // 30 days

			// If 30 days have passed, reset the dismissal
			if ( $time_elapsed >= $reshow_after ) {
				delete_user_meta( get_current_user_id(), 'scd_dismissed_upgrade_banner_' . $banner_id );
				return false;
			}

			return true;
		}

		return false;
	}

	/**
	 * Handle AJAX request to dismiss upgrade banner.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function handle_dismiss_banner() {
		// Security check - dies if nonce verification fails
		check_ajax_referer( 'scd_dismiss_upgrade_banner', 'nonce' );

		// Capability check
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Insufficient permissions', 'smart-cycle-discounts' ),
				)
			);
		}

		// Sanitize banner ID
		$banner_id = isset( $_POST['banner_id'] ) ? sanitize_text_field( wp_unslash( $_POST['banner_id'] ) ) : 'dashboard_analytics';

		// Store dismissal with timestamp
		update_user_meta( get_current_user_id(), 'scd_dismissed_upgrade_banner_' . $banner_id, time() );

		wp_send_json_success(
			array(
				'message' => __( 'Banner dismissed successfully', 'smart-cycle-discounts' ),
			)
		);
	}

	/**
	 * Check if upgrade prompts should be shown.
	 *
	 * @since    1.0.0
	 * @return   bool    True if prompts should be shown.
	 */
	public function should_show_prompts() {
		// Never show prompts to premium users
		if ( $this->feature_gate->is_premium() ) {
			return false;
		}

		// Check daily limit
		$prompt_count = $this->get_prompt_count();

		return $prompt_count < $this->max_prompts_per_day;
	}

	/**
	 * Get current prompt count for today.
	 *
	 * @since    1.0.0
	 * @return   int    Number of prompts shown today.
	 */
	private function get_prompt_count() {
		$transient_key = $this->transient_prefix . get_current_user_id();
		$count         = get_transient( $transient_key );

		return $count ? absint( $count ) : 0;
	}

	/**
	 * Increment prompt count.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function increment_prompt_count() {
		$transient_key = $this->transient_prefix . get_current_user_id();
		$current       = $this->get_prompt_count();

		// Set transient for 24 hours
		set_transient( $transient_key, $current + 1, DAY_IN_SECONDS );
	}

	/**
	 * Record that a prompt was shown.
	 *
	 * @since    1.0.0
	 * @param    string $context    Context where prompt was shown.
	 * @return   void
	 */
	public function record_prompt_shown( $context = '' ) {
		$this->increment_prompt_count();

		// Log the event for analytics
		if ( function_exists( 'scd_log_info' ) ) {
			scd_log_info(
				'Upgrade prompt shown',
				array(
					'context'     => $context,
					'daily_count' => $this->get_prompt_count(),
				)
			);
		}
	}

	/**
	 * Get upgrade prompt HTML for a specific context.
	 *
	 * @since    1.0.0
	 * @param    string $feature_name    Feature name to upgrade for.
	 * @param    string $context         Context ('inline', 'banner', 'modal', 'overlay').
	 * @param    array  $args            Additional arguments.
	 * @return   string                     HTML for upgrade prompt.
	 */
	public function get_upgrade_prompt( $feature_name, $context = 'inline', $args = array() ) {
		// Check if this prompt should bypass the daily limit
		// Dashboard banners and permanent UI elements should bypass the limit
		$bypass_limit = isset( $args['bypass_limit'] ) && true === $args['bypass_limit'];

		// Don't show if prompts are disabled for this session (unless bypassing)
		if ( ! $bypass_limit && ! $this->should_show_prompts() ) {
			return '';
		}

		// Build prompt based on context
		$prompt_html = '';

		switch ( $context ) {
			case 'banner':
				$prompt_html = $this->get_banner_prompt( $feature_name, $args );
				break;

			case 'inline':
				$prompt_html = $this->get_inline_prompt( $feature_name, $args );
				break;

			case 'modal':
				$prompt_html = $this->get_modal_prompt( $feature_name, $args );
				break;

			case 'overlay':
				$prompt_html = $this->get_overlay_prompt( $feature_name, $args );
				break;

			default:
				$prompt_html = $this->get_inline_prompt( $feature_name, $args );
				break;
		}

		// Record that prompt was shown (only if not bypassing limit)
		if ( ! $bypass_limit && ! empty( $prompt_html ) ) {
			$this->record_prompt_shown( $context . ':' . $feature_name );
		}

		return $prompt_html;
	}

	/**
	 * Get banner-style upgrade prompt (compact notification style).
	 *
	 * @since    1.0.0
	 * @param    string $feature_name    Feature name.
	 * @param    array  $args            Additional arguments.
	 * @return   string                     Banner HTML.
	 */
	private function get_banner_prompt( $feature_name, $args = array() ) {
		// Check if banner is dismissed
		$banner_id = isset( $args['banner_id'] ) ? $args['banner_id'] : 'dashboard_analytics';
		if ( $this->is_banner_dismissed( $banner_id ) ) {
			return '';
		}

		$upgrade_url = $this->feature_gate->get_upgrade_url();
		$trial_url   = $this->feature_gate->get_trial_url();

		$title = isset( $args['title'] ) ? esc_html( $args['title'] ) : sprintf(
			/* translators: %s: feature name */
			esc_html__( 'Upgrade to unlock %s', 'smart-cycle-discounts' ),
			esc_html( $feature_name )
		);

		$message = isset( $args['message'] ) ? esc_html( $args['message'] ) : esc_html__( 'Get custom date ranges, trend analysis, campaign comparisons, and exportable reports.', 'smart-cycle-discounts' );

		// Promotional settings (can be enabled via filter)
		$has_promotion = isset( $args['promotion'] ) && true === $args['promotion'];
		$discount_text = isset( $args['discount_text'] ) ? esc_html( $args['discount_text'] ) : esc_html__( 'Save 30%', 'smart-cycle-discounts' );

		// Generate nonce for dismiss action
		$dismiss_nonce = wp_create_nonce( 'scd_dismiss_upgrade_banner' );

		ob_start();
		?>
		<div class="scd-upgrade-banner scd-upgrade-banner-inline <?php echo esc_attr( $has_promotion ? 'scd-has-promotion' : '' ); ?>" data-banner-id="<?php echo esc_attr( $banner_id ); ?>" data-dismiss-nonce="<?php echo esc_attr( $dismiss_nonce ); ?>">
			<button type="button" class="scd-banner-dismiss" aria-label="<?php esc_attr_e( 'Dismiss this notice', 'smart-cycle-discounts' ); ?>">
				<span class="dashicons dashicons-no-alt"></span>
			</button>
			<div class="scd-upgrade-banner-content">
				<div class="scd-upgrade-banner-left">
					<span class="dashicons dashicons-info-outline scd-upgrade-icon"></span>
					<div class="scd-upgrade-text">
						<strong><?php echo esc_html( $title ); ?></strong>
						<?php if ( true === $has_promotion ) : ?>
							<span class="scd-promotion-badge"><?php echo esc_html( $discount_text ); ?></span>
						<?php endif; ?>
						<span class="scd-upgrade-message"><?php echo esc_html( $message ); ?></span>
					</div>
				</div>
				<div class="scd-upgrade-banner-actions">
					<a href="<?php echo esc_url( $upgrade_url ); ?>" class="button button-primary scd-upgrade-btn">
						<?php
						if ( true === $has_promotion ) {
							esc_html_e( 'Claim Discount', 'smart-cycle-discounts' );
						} else {
							esc_html_e( 'Upgrade to Pro', 'smart-cycle-discounts' );
						}
						?>
						<span class="dashicons dashicons-arrow-right-alt2"></span>
					</a>
					<a href="<?php echo esc_url( $trial_url ); ?>" class="button scd-trial-btn">
						<?php esc_html_e( 'Start Trial', 'smart-cycle-discounts' ); ?>
					</a>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get inline upgrade prompt.
	 *
	 * @since    1.0.0
	 * @param    string $feature_name    Feature name.
	 * @param    array  $args            Additional arguments.
	 * @return   string                     Inline prompt HTML.
	 */
	private function get_inline_prompt( $feature_name, $args = array() ) {
		$upgrade_url = $this->feature_gate->get_upgrade_url();

		$message = isset( $args['message'] ) ? esc_html( $args['message'] ) : sprintf(
			/* translators: %s: feature name */
			esc_html__( '%s is a Pro feature.', 'smart-cycle-discounts' ),
			esc_html( $feature_name )
		);

		ob_start();
		?>
		<div class="scd-upgrade-prompt-inline">
			<span class="dashicons dashicons-lock"></span>
			<span><?php echo $message; ?></span>
			<a href="<?php echo esc_url( $upgrade_url ); ?>" class="button button-small">
				<?php esc_html_e( 'Upgrade', 'smart-cycle-discounts' ); ?>
			</a>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get modal upgrade prompt.
	 *
	 * @since    1.0.0
	 * @param    string $feature_name    Feature name.
	 * @param    array  $args            Additional arguments.
	 * @return   string                     Modal HTML.
	 */
	private function get_modal_prompt( $feature_name, $args = array() ) {
		$upgrade_url = $this->feature_gate->get_upgrade_url();
		$trial_url   = $this->feature_gate->get_trial_url();

		$title = isset( $args['title'] ) ? esc_html( $args['title'] ) : sprintf(
			/* translators: %s: feature name */
			esc_html__( 'Unlock %s with Pro', 'smart-cycle-discounts' ),
			esc_html( $feature_name )
		);

		$benefits = isset( $args['benefits'] ) ? $args['benefits'] : array(
			__( 'Advanced analytics and insights', 'smart-cycle-discounts' ),
			__( 'Unlimited campaigns', 'smart-cycle-discounts' ),
			__( 'Priority support', 'smart-cycle-discounts' ),
			__( 'Custom date ranges and exports', 'smart-cycle-discounts' ),
		);

		ob_start();
		?>
		<div id="scd-upgrade-modal" class="scd-modal" style="display: none;">
			<div class="scd-modal-content">
				<span class="scd-modal-close">&times;</span>
				<h2><?php echo $title; ?></h2>
				<ul class="scd-feature-list">
					<?php foreach ( $benefits as $benefit ) : ?>
						<li><span class="dashicons dashicons-yes"></span> <?php echo esc_html( $benefit ); ?></li>
					<?php endforeach; ?>
				</ul>
				<div class="scd-modal-actions">
					<a href="<?php echo esc_url( $upgrade_url ); ?>" class="button button-primary button-hero">
						<?php esc_html_e( 'Upgrade Now', 'smart-cycle-discounts' ); ?>
					</a>
					<a href="<?php echo esc_url( $trial_url ); ?>" class="button button-secondary button-hero">
						<?php esc_html_e( 'Start Free Trial', 'smart-cycle-discounts' ); ?>
					</a>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get overlay upgrade prompt (for blurred content).
	 *
	 * @since    1.0.0
	 * @param    string $feature_name    Feature name.
	 * @param    array  $args            Additional arguments.
	 * @return   string                     Overlay HTML.
	 */
	private function get_overlay_prompt( $feature_name, $args = array() ) {
		$upgrade_url = $this->feature_gate->get_upgrade_url();

		$message = isset( $args['message'] ) ? esc_html( $args['message'] ) : sprintf(
			/* translators: %s: feature name */
			esc_html__( 'Upgrade to unlock %s', 'smart-cycle-discounts' ),
			esc_html( $feature_name )
		);

		ob_start();
		?>
		<div class="scd-upgrade-overlay">
			<div class="scd-upgrade-overlay-content">
				<span class="dashicons dashicons-lock"></span>
				<h3><?php echo $message; ?></h3>
				<a href="<?php echo esc_url( $upgrade_url ); ?>" class="button button-primary">
					<?php esc_html_e( 'Upgrade to Pro', 'smart-cycle-discounts' ); ?>
				</a>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get pro badge HTML.
	 *
	 * @since    1.0.0
	 * @return   string    Pro badge HTML.
	 */
	public function get_pro_badge() {
		return '<span class="scd-pro-badge">' . esc_html__( 'PRO', 'smart-cycle-discounts' ) . '</span>';
	}

	/**
	 * Reset prompt count for current user.
	 *
	 * Useful for testing or manual reset.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function reset_prompt_count() {
		$transient_key = $this->transient_prefix . get_current_user_id();
		delete_transient( $transient_key );
	}
}
