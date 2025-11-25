<?php
/**
 * Campaign Overview Panel Template
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/views/admin/components
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>

<!-- Backdrop -->
<div class="scd-overview-panel-backdrop"></div>

<div id="scd-campaign-overview-panel"
     class="scd-overview-panel"
     role="dialog"
     aria-modal="true"
     aria-labelledby="scd-panel-title"
     aria-describedby="scd-panel-description">

	<!-- Panel Container -->
	<div class="scd-overview-panel-container">

		<!-- Header -->
		<div class="scd-overview-panel-header">
			<div class="scd-overview-panel-header-content">
				<h2 id="scd-panel-title" class="scd-overview-panel-title">
					<span class="scd-panel-title-text"><?php esc_html_e( 'Campaign Overview', 'smart-cycle-discounts' ); ?></span>
				</h2>
				<div id="scd-panel-description" class="screen-reader-text">
					<?php esc_html_e( 'Detailed information about the selected campaign', 'smart-cycle-discounts' ); ?>
				</div>
			</div>
			<button type="button"
			        class="scd-overview-panel-close"
			        aria-label="<?php esc_attr_e( 'Close panel', 'smart-cycle-discounts' ); ?>">
				<?php echo SCD_Icon_Helper::get( 'no-alt', array( 'size' => 16 ) ); ?>
			</button>
		</div>

		<!-- Content -->
		<div class="scd-overview-panel-content">

			<!-- Loading State -->
			<?php
			if ( class_exists( 'SCD_Loader_Helper' ) ) {
				SCD_Loader_Helper::render_container( 'scd-overview-loading', __( 'Loading campaign details...', 'smart-cycle-discounts' ), false );
			}
			?>

			<!-- Error State -->
			<div id="scd-overview-error" class="scd-overview-error">
				<?php echo SCD_Icon_Helper::get( 'warning', array( 'size' => 16 ) ); ?>
				<p class="scd-overview-error-message"></p>
				<button type="button" id="scd-overview-retry" class="button scd-retry-load">
					<?php esc_html_e( 'Retry', 'smart-cycle-discounts' ); ?>
				</button>
			</div>

			<!-- Sections Container -->
			<div id="scd-overview-sections" class="scd-overview-sections">

				<!-- Basic Info Section -->
				<div class="scd-overview-section scd-section-basic">
					<div class="scd-form-section-header">
						<h3 class="scd-form-section-title">
							<?php esc_html_e( 'Basic Information', 'smart-cycle-discounts' ); ?>
						</h3>
					</div>
					<div id="scd-section-basic" class="scd-overview-section-content" data-section="basic">
						<!-- Populated via AJAX -->
					</div>
				</div>

				<!-- Health Status Section -->
				<div class="scd-overview-section scd-section-health">
					<div class="scd-form-section-header">
						<h3 class="scd-form-section-title">
							<?php echo SCD_Icon_Helper::get( 'heart', array( 'size' => 20 ) ); ?>
							<?php esc_html_e( 'Campaign Health', 'smart-cycle-discounts' ); ?>
						</h3>
					</div>
					<div id="scd-section-health" class="scd-overview-section-content" data-section="health">
						<!-- Populated via AJAX -->
					</div>
				</div>

				<!-- Schedule Section -->
				<div class="scd-overview-section scd-section-schedule">
					<div class="scd-form-section-header">
						<h3 class="scd-form-section-title">
							<?php esc_html_e( 'Schedule', 'smart-cycle-discounts' ); ?>
						</h3>
					</div>
					<div id="scd-section-schedule" class="scd-overview-section-content" data-section="schedule">
						<!-- Populated via AJAX -->
					</div>
				</div>

				<!-- Recurring Schedule Section -->
				<div class="scd-overview-section scd-section-recurring-schedule">
					<div class="scd-overview-subsection">
						<div class="scd-subsection-header">
							<?php echo SCD_Icon_Helper::get( 'backup', array( 'size' => 16 ) ); ?>
							<h5><?php esc_html_e( 'Recurring Schedule', 'smart-cycle-discounts' ); ?></h5>
						</div>
						<div id="scd-section-recurring-schedule" class="scd-overview-section-content" data-section="recurring_schedule">
							<!-- Populated via AJAX -->
						</div>
					</div>
				</div>


				<!-- Products Section -->
				<div class="scd-overview-section scd-section-products">
					<div class="scd-form-section-header">
						<h3 class="scd-form-section-title">
							<?php esc_html_e( 'Products', 'smart-cycle-discounts' ); ?>
						</h3>
					</div>
					<div id="scd-section-products" class="scd-overview-section-content" data-section="products">
						<!-- Populated via AJAX -->
					</div>
				</div>

				<!-- Discounts Section -->
				<div class="scd-overview-section scd-section-discounts">
					<div class="scd-form-section-header">
						<h3 class="scd-form-section-title">
							<?php esc_html_e( 'Discount Configuration', 'smart-cycle-discounts' ); ?>
						</h3>
					</div>
					<div id="scd-section-discounts" class="scd-overview-section-content" data-section="discounts">
						<!-- Populated via AJAX -->
					</div>
				</div>

				<!-- Performance Section -->
				<div class="scd-overview-section scd-section-performance">
					<div class="scd-form-section-header">
						<h3 class="scd-form-section-title">
							<?php esc_html_e( 'Performance', 'smart-cycle-discounts' ); ?>
						</h3>
					</div>
					<div id="scd-section-performance" class="scd-overview-section-content" data-section="performance">
						<!-- Populated via AJAX -->
					</div>
				</div>

			</div>

		</div>

		<!-- Footer -->
		<div class="scd-overview-panel-footer">
			<button type="button" id="scd-overview-edit-button" class="button button-primary scd-edit-campaign" data-campaign-id="">
				<?php esc_html_e( 'Edit Campaign', 'smart-cycle-discounts' ); ?>
			</button>
			<button type="button" id="scd-overview-close-button" class="button scd-close-panel">
				<?php esc_html_e( 'Close', 'smart-cycle-discounts' ); ?>
			</button>
		</div>

	</div>

</div>
