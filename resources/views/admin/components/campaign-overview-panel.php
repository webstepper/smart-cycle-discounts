<?php
/**
 * Campaign Overview Panel Template
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/views/admin/components
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress/plugins/smart-cycle-discounts/
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>

<!-- Backdrop -->
<div class="wsscd-overview-panel-backdrop"></div>

<div id="wsscd-campaign-overview-panel"
     class="wsscd-overview-panel"
     role="dialog"
     aria-modal="true"
     aria-labelledby="wsscd-panel-title"
     aria-describedby="wsscd-panel-description">

	<!-- Panel Container -->
	<div class="wsscd-overview-panel-container">

		<!-- Header -->
		<div class="wsscd-overview-panel-header">
			<div class="wsscd-overview-panel-header-content">
				<h2 id="wsscd-panel-title" class="wsscd-overview-panel-title">
					<span class="wsscd-panel-title-text"><?php esc_html_e( 'Campaign Overview', 'smart-cycle-discounts' ); ?></span>
				</h2>
				<div id="wsscd-panel-description" class="screen-reader-text">
					<?php esc_html_e( 'Detailed information about the selected campaign', 'smart-cycle-discounts' ); ?>
				</div>
			</div>
			<button type="button"
			        class="wsscd-overview-panel-close"
			        aria-label="<?php esc_attr_e( 'Close panel', 'smart-cycle-discounts' ); ?>">
				<?php WSSCD_Icon_Helper::render( 'no-alt', array( 'size' => 16 ) ); ?>
			</button>
		</div>

		<!-- Content -->
		<div class="wsscd-overview-panel-content">

			<!-- Loading State -->
			<?php
			if ( class_exists( 'WSSCD_Loader_Helper' ) ) {
				WSSCD_Loader_Helper::render_container( 'wsscd-overview-loading', __( 'Loading campaign details...', 'smart-cycle-discounts' ), false );
			}
			?>

			<!-- Error State -->
			<div id="wsscd-overview-error" class="wsscd-overview-error">
				<?php WSSCD_Icon_Helper::render( 'warning', array( 'size' => 16 ) ); ?>
				<p class="wsscd-overview-error-message"></p>
				<button type="button" id="wsscd-overview-retry" class="button wsscd-retry-load">
					<?php esc_html_e( 'Retry', 'smart-cycle-discounts' ); ?>
				</button>
			</div>

			<!-- Sections Container -->
			<div id="wsscd-overview-sections" class="wsscd-overview-sections">

				<!-- Basic Info Section -->
				<div class="wsscd-overview-section wsscd-section-basic">
					<div class="wsscd-form-section-header">
						<h3 class="wsscd-form-section-title">
							<?php esc_html_e( 'Basic Information', 'smart-cycle-discounts' ); ?>
						</h3>
					</div>
					<div id="wsscd-section-basic" class="wsscd-overview-section-content" data-section="basic">
						<!-- Populated via AJAX -->
					</div>
				</div>

				<!-- Health Status Section -->
				<div class="wsscd-overview-section wsscd-section-health">
					<div class="wsscd-form-section-header">
						<h3 class="wsscd-form-section-title">
							<?php WSSCD_Icon_Helper::render( 'heart', array( 'size' => 20 ) ); ?>
							<?php esc_html_e( 'Campaign Health', 'smart-cycle-discounts' ); ?>
						</h3>
					</div>
					<div id="wsscd-section-health" class="wsscd-overview-section-content" data-section="health">
						<!-- Populated via AJAX -->
					</div>
				</div>

				<!-- Schedule Section -->
				<div class="wsscd-overview-section wsscd-section-schedule">
					<div class="wsscd-form-section-header">
						<h3 class="wsscd-form-section-title">
							<?php esc_html_e( 'Schedule', 'smart-cycle-discounts' ); ?>
						</h3>
					</div>
					<div id="wsscd-section-schedule" class="wsscd-overview-section-content" data-section="schedule">
						<!-- Populated via AJAX -->
					</div>
				</div>

				<!-- Recurring Schedule Section -->
				<div class="wsscd-overview-section wsscd-section-recurring-schedule">
					<div class="wsscd-overview-subsection">
						<div class="wsscd-subsection-header">
							<?php WSSCD_Icon_Helper::render( 'backup', array( 'size' => 16 ) ); ?>
							<h5><?php esc_html_e( 'Recurring Schedule', 'smart-cycle-discounts' ); ?></h5>
						</div>
						<div id="wsscd-section-recurring-schedule" class="wsscd-overview-section-content" data-section="recurring_schedule">
							<!-- Populated via AJAX -->
						</div>
					</div>
				</div>


				<!-- Products Section -->
				<div class="wsscd-overview-section wsscd-section-products">
					<div class="wsscd-form-section-header">
						<h3 class="wsscd-form-section-title">
							<?php esc_html_e( 'Products', 'smart-cycle-discounts' ); ?>
						</h3>
					</div>
					<div id="wsscd-section-products" class="wsscd-overview-section-content" data-section="products">
						<!-- Populated via AJAX -->
					</div>
				</div>

				<!-- Discounts Section -->
				<div class="wsscd-overview-section wsscd-section-discounts">
					<div class="wsscd-form-section-header">
						<h3 class="wsscd-form-section-title">
							<?php esc_html_e( 'Discount Configuration', 'smart-cycle-discounts' ); ?>
						</h3>
					</div>
					<div id="wsscd-section-discounts" class="wsscd-overview-section-content" data-section="discounts">
						<!-- Populated via AJAX -->
					</div>
				</div>

				<!-- Performance Section -->
				<div class="wsscd-overview-section wsscd-section-performance">
					<div class="wsscd-form-section-header">
						<h3 class="wsscd-form-section-title">
							<?php esc_html_e( 'Performance', 'smart-cycle-discounts' ); ?>
						</h3>
					</div>
					<div id="wsscd-section-performance" class="wsscd-overview-section-content" data-section="performance">
						<!-- Populated via AJAX -->
					</div>
				</div>

			</div>

		</div>

		<!-- Footer -->
		<div class="wsscd-overview-panel-footer">
			<button type="button" id="wsscd-overview-edit-button" class="button button-primary wsscd-edit-campaign" data-campaign-id="">
				<?php esc_html_e( 'Edit Campaign', 'smart-cycle-discounts' ); ?>
			</button>
			<button type="button" id="wsscd-overview-close-button" class="button wsscd-close-panel">
				<?php esc_html_e( 'Close', 'smart-cycle-discounts' ); ?>
			</button>
		</div>

	</div>

</div>
