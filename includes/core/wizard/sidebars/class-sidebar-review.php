<?php
/**
 * Review Step Sidebar Class
 *
 * Provides contextual help and dynamic campaign summary for the review step.
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
	exit; // Exit if accessed directly
}

/**
 * Review step sidebar class
 *
 * @since 1.0.0
 */
class SCD_Wizard_Sidebar_Review extends SCD_Wizard_Sidebar_Base {

	/**
	 * Wizard State Service instance
	 *
	 * @since  1.0.0
	 * @var    SCD_Wizard_State_Service
	 */
	private $state_service;

	/**
	 * Campaign Summary Service instance
	 *
	 * @since  1.0.0
	 * @var    SCD_Campaign_Summary_Service
	 */
	private $summary_service;

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 * @param SCD_Wizard_State_Service $state_service State service instance (optional for contextual mode).
	 */
	public function __construct( SCD_Wizard_State_Service $state_service = null ) {
		$this->step           = 'review';
		$this->use_contextual = true;

		if ( $state_service ) {
			$this->state_service   = $state_service;
			$this->summary_service = new SCD_Campaign_Summary_Service();
		}
	}

	/**
	 * Get legacy sidebar content (fallback)
	 *
	 * @since  1.0.0
	 * @return string HTML content
	 */
	protected function get_legacy_content() {
		return $this->render_wrapper(
			__( 'Campaign Summary', 'smart-cycle-discounts' ),
			__( 'Review your campaign settings before launching', 'smart-cycle-discounts' )
		);
	}

	/**
	 * Render sidebar sections
	 *
	 * @since  1.0.0
	 * @return void
	 */
	protected function render_sections() {
		$basic_data     = $this->get_step_data_safe( 'basic' );
		$products_data  = $this->get_step_data_safe( 'products' );
		$discounts_data = $this->get_step_data_safe( 'discounts' );
		$schedule_data  = $this->get_step_data_safe( 'schedule' );

		// Campaign Overview section
		$this->render_overview_section( $basic_data, $products_data, $discounts_data, $schedule_data );

		// Discount Details section
		$this->render_discount_details_section( $discounts_data );

		// Product Details section
		$this->render_product_details_section( $products_data );

		// Schedule Details section
		$this->render_schedule_details_section( $schedule_data );

		// Pre-Launch Checklist section
		$this->render_checklist_section();
	}

	/**
	 * Render campaign overview section
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  array $basic_data     Basic step data.
	 * @param  array $products_data  Products step data.
	 * @param  array $discounts_data Discounts step data.
	 * @param  array $schedule_data  Schedule step data.
	 * @return void
	 */
	private function render_overview_section( $basic_data, $products_data, $discounts_data, $schedule_data ) {
		ob_start();
		?>
		<div class="scd-sidebar-summary">
			<div class="scd-summary-item">
				<div class="scd-summary-label"><?php esc_html_e( 'Name', 'smart-cycle-discounts' ); ?></div>
				<div class="scd-summary-value"><?php echo esc_html( $this->summary_service->get_campaign_name( $basic_data ) ); ?></div>
			</div>

			<div class="scd-summary-item">
				<div class="scd-summary-label"><?php esc_html_e( 'Priority', 'smart-cycle-discounts' ); ?></div>
				<div class="scd-summary-value">
					<?php
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Badge helper handles escaping
					echo SCD_Badge_Helper::priority_badge( $this->summary_service->get_priority( $basic_data ) );
					?>
				</div>
			</div>

			<div class="scd-summary-item">
				<div class="scd-summary-label"><?php esc_html_e( 'Discount', 'smart-cycle-discounts' ); ?></div>
				<div class="scd-summary-value scd-summary-highlight">
					<?php echo esc_html( $this->summary_service->get_discount_display( $discounts_data ) ); ?>
				</div>
			</div>

			<div class="scd-summary-item">
				<div class="scd-summary-label"><?php esc_html_e( 'Products', 'smart-cycle-discounts' ); ?></div>
				<div class="scd-summary-value"><?php echo esc_html( $this->summary_service->get_product_count( $products_data ) ); ?></div>
			</div>

			<div class="scd-summary-item">
				<div class="scd-summary-label"><?php esc_html_e( 'Duration', 'smart-cycle-discounts' ); ?></div>
				<div class="scd-summary-value"><?php echo esc_html( $this->summary_service->get_schedule_duration( $schedule_data ) ); ?></div>
			</div>
		</div>
		<?php
		$this->render_section(
			__( 'Overview', 'smart-cycle-discounts' ),
			'admin-settings',
			ob_get_clean(),
			'open'
		);
	}

	/**
	 * Render discount details section
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  array $discounts_data Discounts step data.
	 * @return void
	 */
	private function render_discount_details_section( $discounts_data ) {
		$details = $this->summary_service->get_discount_details( $discounts_data );

		ob_start();
		if ( empty( $details ) ) {
			?>
			<p><?php esc_html_e( 'No discount information available', 'smart-cycle-discounts' ); ?></p>
			<?php
		} else {
			?>
			<ul class="scd-details-list">
				<?php foreach ( $details as $detail ) : ?>
					<li>
						<strong><?php echo esc_html( $detail['label'] ); ?>:</strong>
						<?php echo esc_html( $detail['value'] ); ?>
					</li>
				<?php endforeach; ?>
			</ul>
			<?php
		}

		$this->render_section(
			__( 'Discount Details', 'smart-cycle-discounts' ),
			'tag',
			ob_get_clean(),
			'collapsed'
		);
	}

	/**
	 * Render product details section
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  array $products_data Products step data.
	 * @return void
	 */
	private function render_product_details_section( $products_data ) {
		$details = $this->summary_service->get_product_details( $products_data );

		ob_start();
		if ( empty( $details ) ) {
			?>
			<p><?php esc_html_e( 'No product information available', 'smart-cycle-discounts' ); ?></p>
			<?php
		} else {
			?>
			<ul class="scd-details-list">
				<?php foreach ( $details as $detail ) : ?>
					<li>
						<strong><?php echo esc_html( $detail['label'] ); ?>:</strong>
						<?php echo esc_html( $detail['value'] ); ?>
					</li>
				<?php endforeach; ?>
			</ul>
			<?php
		}

		$this->render_section(
			__( 'Product Details', 'smart-cycle-discounts' ),
			'products',
			ob_get_clean(),
			'collapsed'
		);
	}

	/**
	 * Render schedule details section
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  array $schedule_data Schedule step data.
	 * @return void
	 */
	private function render_schedule_details_section( $schedule_data ) {
		$details = $this->summary_service->get_schedule_details( $schedule_data );

		ob_start();
		if ( empty( $details ) ) {
			?>
			<p><?php esc_html_e( 'No schedule information available', 'smart-cycle-discounts' ); ?></p>
			<?php
		} else {
			?>
			<ul class="scd-details-list">
				<?php foreach ( $details as $detail ) : ?>
					<li>
						<strong><?php echo esc_html( $detail['label'] ); ?>:</strong>
						<?php echo esc_html( $detail['value'] ); ?>
					</li>
				<?php endforeach; ?>
			</ul>
			<?php
		}

		$this->render_section(
			__( 'Schedule Details', 'smart-cycle-discounts' ),
			'calendar-alt',
			ob_get_clean(),
			'collapsed'
		);
	}

	/**
	 * Render pre-launch checklist section
	 *
	 * @since  1.0.0
	 * @access private
	 * @return void
	 */
	private function render_checklist_section() {
		ob_start();
		?>
		<div class="scd-checklist-cards">
			<div class="scd-checklist-card">
				<div class="scd-checklist-icon">
					<?php echo SCD_Icon_Helper::get( 'warning', array( 'size' => 16 ) ); ?>
				</div>
				<div class="scd-checklist-content">
					<h4><?php esc_html_e( 'Resolve Warnings', 'smart-cycle-discounts' ); ?></h4>
					<p><?php esc_html_e( 'Fix all validation errors shown above', 'smart-cycle-discounts' ); ?></p>
				</div>
			</div>
			<div class="scd-checklist-card">
				<div class="scd-checklist-icon">
					<?php echo SCD_Icon_Helper::get( 'admin-links', array( 'size' => 16 ) ); ?>
				</div>
				<div class="scd-checklist-content">
					<h4><?php esc_html_e( 'Check Conflicts', 'smart-cycle-discounts' ); ?></h4>
					<p><?php esc_html_e( 'Ensure no overlapping campaigns on same products', 'smart-cycle-discounts' ); ?></p>
				</div>
			</div>
			<div class="scd-checklist-card">
				<div class="scd-checklist-icon">
					<?php echo SCD_Icon_Helper::get( 'money-alt', array( 'size' => 16 ) ); ?>
				</div>
				<div class="scd-checklist-content">
					<h4><?php esc_html_e( 'Verify Margins', 'smart-cycle-discounts' ); ?></h4>
					<p><?php esc_html_e( 'Confirm discount doesn\'t exceed profit threshold', 'smart-cycle-discounts' ); ?></p>
				</div>
			</div>
			<div class="scd-checklist-card">
				<div class="scd-checklist-icon">
					<?php echo SCD_Icon_Helper::get( 'visibility', array( 'size' => 16 ) ); ?>
				</div>
				<div class="scd-checklist-content">
					<h4><?php esc_html_e( 'Test First', 'smart-cycle-discounts' ); ?></h4>
					<p><?php esc_html_e( 'Save as draft and verify on storefront before publishing', 'smart-cycle-discounts' ); ?></p>
				</div>
			</div>
			<div class="scd-checklist-card">
				<div class="scd-checklist-icon">
					<?php echo SCD_Icon_Helper::get( 'shield', array( 'size' => 16 ) ); ?>
				</div>
				<div class="scd-checklist-content">
					<h4><?php esc_html_e( 'Set Limits', 'smart-cycle-discounts' ); ?></h4>
					<p><?php esc_html_e( 'Consider usage caps to prevent budget overruns', 'smart-cycle-discounts' ); ?></p>
				</div>
			</div>
		</div>
		<?php
		$this->render_section(
			__( 'Pre-Launch Checklist', 'smart-cycle-discounts' ),
			'yes-alt',
			ob_get_clean(),
			'open'
		);
	}

	/**
	 * Get step data safely with error handling
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  string $step Step name.
	 * @return array         Step data or empty array
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
				error_log( 'SCD Review Sidebar: Failed to get step data for ' . $step . ': ' . $e->getMessage() );
			}
			return array();
		}
	}
}
