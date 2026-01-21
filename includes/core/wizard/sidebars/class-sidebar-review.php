<?php
/**
 * Review Step Sidebar Class
 *
 * Provides a static campaign summary sidebar for the review step.
 * Unlike other steps, this does NOT use the contextual help system.
 * Instead, it displays a complete summary of all campaign data.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/wizard/sidebars
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Review step sidebar class
 *
 * Displays a static campaign summary instead of contextual help.
 *
 * @since 1.0.0
 */
class WSSCD_Wizard_Sidebar_Review extends WSSCD_Wizard_Sidebar_Base {

	/**
	 * Wizard State Service instance
	 *
	 * @since 1.0.0
	 * @var   WSSCD_Wizard_State_Service
	 */
	private $state_service;

	/**
	 * Campaign Summary Service instance
	 *
	 * @since 1.0.0
	 * @var   WSSCD_Campaign_Summary_Service
	 */
	private $summary_service;

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 * @param WSSCD_Wizard_State_Service $state_service State service instance (optional).
	 */
	public function __construct( WSSCD_Wizard_State_Service $state_service = null ) {
		$this->step           = 'review';
		$this->use_contextual = false; // Review step uses static sidebar

		if ( $state_service ) {
			$this->state_service   = $state_service;
			$this->summary_service = new WSSCD_Campaign_Summary_Service();
		}
	}

	/**
	 * Get the sidebar content
	 *
	 * Overrides parent to always return static campaign summary.
	 *
	 * @since  1.0.0
	 * @return string HTML content
	 */
	public function get_content() {
		return $this->render_campaign_summary();
	}

	/**
	 * Render complete campaign summary sidebar
	 *
	 * @since  1.0.0
	 * @return string HTML content
	 */
	private function render_campaign_summary() {
		$basic_data     = $this->get_step_data_safe( 'basic' );
		$products_data  = $this->get_step_data_safe( 'products' );
		$discounts_data = $this->get_step_data_safe( 'discounts' );
		$schedule_data  = $this->get_step_data_safe( 'schedule' );

		ob_start();
		?>
		<div class="wsscd-sidebar-contextual wsscd-review-sidebar" data-step="review">
			<div class="wsscd-sidebar-breadcrumb">
				<span class="wsscd-breadcrumb-step"><?php esc_html_e( 'Review', 'smart-cycle-discounts' ); ?></span>
				<span class="wsscd-breadcrumb-separator">›</span>
				<span class="wsscd-breadcrumb-topic" id="wsscd-breadcrumb-topic"><?php esc_html_e( 'Campaign Summary', 'smart-cycle-discounts' ); ?></span>
			</div>

			<div class="wsscd-sidebar-help-area">
				<div id="wsscd-sidebar-help-content">
					<?php $this->render_overview_section( $basic_data, $products_data, $discounts_data, $schedule_data ); ?>
					<?php $this->render_basic_details_section( $basic_data ); ?>
					<?php $this->render_product_details_section( $products_data ); ?>
					<?php $this->render_discount_details_section( $discounts_data ); ?>
					<?php $this->render_badge_details_section( $discounts_data ); ?>
					<?php $this->render_schedule_details_section( $schedule_data ); ?>
					<?php $this->render_checklist_section(); ?>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render campaign overview section
	 *
	 * @since  1.0.0
	 * @param  array $basic_data     Basic step data.
	 * @param  array $products_data  Products step data.
	 * @param  array $discounts_data Discounts step data.
	 * @param  array $schedule_data  Schedule step data.
	 * @return void
	 */
	private function render_overview_section( $basic_data, $products_data, $discounts_data, $schedule_data ) {
		?>
		<div class="wsscd-sidebar-help-topic">
			<div class="wsscd-sidebar-topic-header">
				<?php WSSCD_Icon_Helper::render( 'dashboard', array( 'size' => 20, 'class' => 'wsscd-topic-icon' ) ); ?>
				<h3 class="wsscd-topic-title"><?php esc_html_e( 'Campaign Overview', 'smart-cycle-discounts' ); ?></h3>
			</div>

			<div class="wsscd-sidebar-topic-content">
				<div class="wsscd-review-summary-grid">
					<div class="wsscd-review-summary-item">
						<span class="wsscd-review-label"><?php esc_html_e( 'Name', 'smart-cycle-discounts' ); ?></span>
						<span class="wsscd-review-value wsscd-review-value--primary">
							<?php echo esc_html( $this->summary_service ? $this->summary_service->get_campaign_name( $basic_data ) : __( 'Not set', 'smart-cycle-discounts' ) ); ?>
						</span>
					</div>

					<div class="wsscd-review-summary-item">
						<span class="wsscd-review-label"><?php esc_html_e( 'Priority', 'smart-cycle-discounts' ); ?></span>
						<span class="wsscd-review-value">
							<?php
							if ( $this->summary_service ) {
																echo wp_kses_post( WSSCD_Badge_Helper::priority_badge( $this->summary_service->get_priority( $basic_data ) ) );
							} else {
								echo esc_html__( 'Not set', 'smart-cycle-discounts' );
							}
							?>
						</span>
					</div>

					<div class="wsscd-review-summary-item">
						<span class="wsscd-review-label"><?php esc_html_e( 'Discount', 'smart-cycle-discounts' ); ?></span>
						<span class="wsscd-review-value wsscd-review-value--highlight">
							<?php echo esc_html( $this->summary_service ? $this->summary_service->get_discount_display( $discounts_data ) : __( 'Not set', 'smart-cycle-discounts' ) ); ?>
						</span>
					</div>

					<div class="wsscd-review-summary-item">
						<span class="wsscd-review-label"><?php esc_html_e( 'Products', 'smart-cycle-discounts' ); ?></span>
						<span class="wsscd-review-value">
							<?php echo esc_html( $this->summary_service ? $this->summary_service->get_product_count( $products_data ) : __( 'Not set', 'smart-cycle-discounts' ) ); ?>
						</span>
					</div>

					<div class="wsscd-review-summary-item">
						<span class="wsscd-review-label"><?php esc_html_e( 'Duration', 'smart-cycle-discounts' ); ?></span>
						<span class="wsscd-review-value">
							<?php echo esc_html( $this->summary_service ? $this->summary_service->get_schedule_duration( $schedule_data ) : __( 'Not set', 'smart-cycle-discounts' ) ); ?>
						</span>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render basic details section
	 *
	 * @since  1.0.0
	 * @param  array $basic_data Basic step data.
	 * @return void
	 */
	private function render_basic_details_section( $basic_data ) {
		$description = isset( $basic_data['description'] ) ? $basic_data['description'] : '';
		?>
		<div class="wsscd-help-section wsscd-help-how-works">
			<div class="wsscd-help-header">
				<?php WSSCD_Icon_Helper::render( 'edit', array( 'size' => 14 ) ); ?>
				<?php esc_html_e( 'Basic Info', 'smart-cycle-discounts' ); ?>
			</div>
			<div class="wsscd-help-body">
				<?php if ( ! empty( $description ) ) : ?>
					<p class="wsscd-review-description"><?php echo esc_html( $description ); ?></p>
				<?php else : ?>
					<p class="wsscd-review-empty"><?php esc_html_e( 'No description provided', 'smart-cycle-discounts' ); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render product details section
	 *
	 * @since  1.0.0
	 * @param  array $products_data Products step data.
	 * @return void
	 */
	private function render_product_details_section( $products_data ) {
		$details = $this->summary_service ? $this->summary_service->get_product_details( $products_data ) : array();
		?>
		<div class="wsscd-help-section wsscd-help-use-cases">
			<div class="wsscd-help-header">
				<?php WSSCD_Icon_Helper::render( 'products', array( 'size' => 14 ) ); ?>
				<?php esc_html_e( 'Product Selection', 'smart-cycle-discounts' ); ?>
			</div>
			<div class="wsscd-help-body">
				<?php if ( empty( $details ) ) : ?>
					<p class="wsscd-review-empty"><?php esc_html_e( 'No products configured', 'smart-cycle-discounts' ); ?></p>
				<?php else : ?>
					<ul class="wsscd-help-list wsscd-checklist">
						<?php foreach ( $details as $detail ) : ?>
							<li>
								<strong><?php echo esc_html( $detail['label'] ); ?>:</strong>
								<?php echo esc_html( $detail['value'] ); ?>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render discount details section
	 *
	 * @since  1.0.0
	 * @param  array $discounts_data Discounts step data.
	 * @return void
	 */
	private function render_discount_details_section( $discounts_data ) {
		$details = $this->summary_service ? $this->summary_service->get_discount_details( $discounts_data ) : array();
		?>
		<div class="wsscd-help-section wsscd-help-watch-out">
			<div class="wsscd-help-header">
				<?php WSSCD_Icon_Helper::render( 'tag', array( 'size' => 14 ) ); ?>
				<?php esc_html_e( 'Discount Rules', 'smart-cycle-discounts' ); ?>
			</div>
			<div class="wsscd-help-body">
				<?php if ( empty( $details ) ) : ?>
					<p class="wsscd-review-empty"><?php esc_html_e( 'No discount rules configured', 'smart-cycle-discounts' ); ?></p>
				<?php else : ?>
					<ul class="wsscd-help-list">
						<?php foreach ( $details as $detail ) : ?>
							<li>
								<strong><?php echo esc_html( $detail['label'] ); ?>:</strong>
								<?php echo esc_html( $detail['value'] ); ?>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render badge details section
	 *
	 * @since  1.0.0
	 * @param  array $discounts_data Discounts step data.
	 * @return void
	 */
	private function render_badge_details_section( $discounts_data ) {
		$details = $this->summary_service ? $this->summary_service->get_badge_details( $discounts_data ) : array();
		?>
		<div class="wsscd-help-section wsscd-help-badge-settings">
			<div class="wsscd-help-header">
				<?php WSSCD_Icon_Helper::render( 'awards', array( 'size' => 14 ) ); ?>
				<?php esc_html_e( 'Badge Settings', 'smart-cycle-discounts' ); ?>
			</div>
			<div class="wsscd-help-body">
				<?php if ( empty( $details ) ) : ?>
					<p class="wsscd-review-empty"><?php esc_html_e( 'Default badge settings', 'smart-cycle-discounts' ); ?></p>
				<?php else : ?>
					<ul class="wsscd-help-list">
						<?php foreach ( $details as $detail ) : ?>
							<li>
								<strong><?php echo esc_html( $detail['label'] ); ?>:</strong>
								<?php echo esc_html( $detail['value'] ); ?>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render schedule details section
	 *
	 * @since  1.0.0
	 * @param  array $schedule_data Schedule step data.
	 * @return void
	 */
	private function render_schedule_details_section( $schedule_data ) {
		$details = $this->summary_service ? $this->summary_service->get_schedule_details( $schedule_data ) : array();
		?>
		<div class="wsscd-help-section wsscd-help-setup-tips">
			<div class="wsscd-help-header">
				<?php WSSCD_Icon_Helper::render( 'calendar-alt', array( 'size' => 14 ) ); ?>
				<?php esc_html_e( 'Schedule', 'smart-cycle-discounts' ); ?>
			</div>
			<div class="wsscd-help-body">
				<?php if ( empty( $details ) ) : ?>
					<p class="wsscd-review-empty"><?php esc_html_e( 'No schedule configured', 'smart-cycle-discounts' ); ?></p>
				<?php else : ?>
					<ul class="wsscd-help-list">
						<?php foreach ( $details as $detail ) : ?>
							<li>
								<strong><?php echo esc_html( $detail['label'] ); ?>:</strong>
								<?php echo esc_html( $detail['value'] ); ?>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render pre-launch checklist section
	 *
	 * @since  1.0.0
	 * @return void
	 */
	private function render_checklist_section() {
		?>
		<div class="wsscd-common-mistakes">
			<div class="wsscd-mistakes-header">
				<?php WSSCD_Icon_Helper::render( 'yes-alt', array( 'size' => 14 ) ); ?>
				<?php esc_html_e( 'Pre-Launch Checklist', 'smart-cycle-discounts' ); ?>
			</div>
			<ul class="wsscd-mistakes-list wsscd-checklist-items">
				<li class="wsscd-checklist-item">
					<?php esc_html_e( 'Review all settings above', 'smart-cycle-discounts' ); ?>
				</li>
				<li class="wsscd-checklist-item">
					<?php esc_html_e( 'Check for overlapping campaigns', 'smart-cycle-discounts' ); ?>
				</li>
				<li class="wsscd-checklist-item">
					<?php esc_html_e( 'Verify discount doesn\'t exceed margins', 'smart-cycle-discounts' ); ?>
				</li>
				<li class="wsscd-checklist-item">
					<?php esc_html_e( 'Test on storefront before publishing', 'smart-cycle-discounts' ); ?>
				</li>
			</ul>
		</div>
		<?php
	}

	/**
	 * Get step data safely with error handling
	 *
	 * @since  1.0.0
	 * @param  string $step Step name.
	 * @return array        Step data or empty array
	 */
	private function get_step_data_safe( $step ) {
		if ( ! $this->state_service ) {
			return array();
		}

		try {
			$data = $this->state_service->get_step_data( $step );
			return is_array( $data ) ? $data : array();
		} catch ( Exception $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'WSSCD Review Sidebar: Failed to get step data for ' . $step . ': ' . $e->getMessage() );
			}
			return array();
		}
	}
}
