<?php
/**
 * Campaign Wizard - Review & Launch Step (Health Check Version)
 *
 * Displays campaign health check, validation, and impact analysis before launch.
 *
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/views/admin/wizard
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template partial included into function scope; variables are local, not global.

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Security: Verify user capabilities
if ( ! current_user_can( 'manage_woocommerce' ) ) {
	wp_die( esc_html__( 'You do not have permission to access this page.', 'smart-cycle-discounts' ) );
}

// Initialize variables using shared function
wsscd_wizard_init_step_vars( $step_data, $validation_errors );

// Set default launch option (default to 'active' - matches field definition)
$launch_option = isset( $step_data['launch_option'] ) ? $step_data['launch_option'] : 'active';

// Detect edit mode - check if we have a campaign ID in the wizard data
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display logic, nonce checked on form submission
$campaign_id_from_get = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
$is_edit_mode = $campaign_id_from_get > 0 || ( isset( $GLOBALS['wsscd_wizard_data']['campaign_id'] ) && $GLOBALS['wsscd_wizard_data']['campaign_id'] > 0 );

// Prepare content
ob_start();
?>

<?php wsscd_wizard_validation_notice( $validation_errors ); ?>

<!-- Health Check Loading State -->
<div id="wsscd-health-loading" class="wsscd-health-loading">
	<span class="spinner is-active"></span>
	<p><?php esc_html_e( 'Analyzing campaign configuration...', 'smart-cycle-discounts' ); ?></p>
</div>

<!-- Health Check Container -->
<div id="wsscd-health-container" class="wsscd-health-container" style="display: none;">

<!-- Campaign Health Score Card (headerless) -->
<div class="wsscd-card wsscd-wizard-card wsscd-health-score-card" data-help-topic="card-health-score">
	<div class="wsscd-card__content">
		<div class="wsscd-health-score-wrapper">
			<div class="wsscd-health-score-content">
				<h3 class="wsscd-health-score-title"><?php esc_html_e( 'Campaign Health Score', 'smart-cycle-discounts' ); ?></h3>
				<p class="wsscd-health-score-subtitle"></p>
			</div>
			<div class="wsscd-health-score-value">
				<span class="wsscd-score-number">0</span>
				<span class="wsscd-score-max">/100</span>
			</div>
		</div>
	</div>
</div>
<?php

// Health Factors Card (Critical Issues)
ob_start();
?>
<div id="wsscd-critical-issues" class="wsscd-issues-section wsscd-critical-issues" style="display: none;">
	<div class="wsscd-issues-header">
		<?php WSSCD_Icon_Helper::render( 'warning', array( 'size' => 16 ) ); ?>
		<h4><?php esc_html_e( 'Critical Issues', 'smart-cycle-discounts' ); ?></h4>
		<span class="wsscd-issues-count">0</span>
	</div>
	<div class="wsscd-issues-content">
		<!-- Critical issues will be inserted here via JavaScript -->
	</div>
</div>
<?php
$health_factors_content = ob_get_clean();

wsscd_wizard_card(
	array(
		'title'      => __( 'Health Factors', 'smart-cycle-discounts' ),
		'subtitle'   => __( 'Critical issues that must be fixed before launch', 'smart-cycle-discounts' ),
		'icon'       => 'admin-generic',
		'content'    => $health_factors_content,
		'class'      => 'wsscd-health-factors-card',
		'id'         => 'wsscd-health-factors',
		'help_topic' => 'card-health-factors',
	)
);

// Recommendations Card
ob_start();
?>
<div class="wsscd-recommendations-categories">
	<!-- Categorized recommendations will be inserted here via JavaScript -->
</div>
<?php
$recommendations_content = ob_get_clean();

wsscd_wizard_card(
	array(
		'title'      => __( 'Recommendations', 'smart-cycle-discounts' ),
		'subtitle'   => __( 'Actions to improve your campaign health and performance', 'smart-cycle-discounts' ),
		'icon'       => 'lightbulb',
		'content'    => $recommendations_content,
		'class'      => 'wsscd-recommendations-card',
		'id'         => 'wsscd-recommendations',
		'help_topic' => 'card-recommendations',
	)
);

// Campaign Conflicts Card
ob_start();
?>
<div class="wsscd-conflicts-list">
	<!-- Conflicts will be inserted here via JavaScript -->
</div>
<?php
$conflicts_content = ob_get_clean();

wsscd_wizard_card(
	array(
		'title'      => __( 'Campaign Conflicts', 'smart-cycle-discounts' ),
		'subtitle'   => __( 'Other campaigns affecting the same products', 'smart-cycle-discounts' ),
		'icon'       => 'warning',
		'content'    => $conflicts_content,
		'class'      => 'wsscd-conflict-preview-card',
		'id'         => 'wsscd-conflict-preview',
		'help_topic' => 'card-conflicts',
	)
);

// Campaign Impact Analysis Card
ob_start();
?>
<div class="wsscd-impact-grid">
	<div class="wsscd-impact-item">
		<div class="wsscd-impact-label"><?php esc_html_e( 'Products Matched', 'smart-cycle-discounts' ); ?></div>
		<div class="wsscd-impact-value" data-metric="products_matched">--</div>
	</div>
	<div class="wsscd-impact-item">
		<div class="wsscd-impact-label"><?php esc_html_e( 'Actually Discounted', 'smart-cycle-discounts' ); ?></div>
		<div class="wsscd-impact-value" data-metric="products_discounted">--</div>
	</div>
	<div class="wsscd-impact-item">
		<div class="wsscd-impact-label"><?php esc_html_e( 'Coverage Rate', 'smart-cycle-discounts' ); ?></div>
		<div class="wsscd-impact-value" data-metric="coverage_percentage">--</div>
	</div>
</div>
<div id="wsscd-exclusions" class="wsscd-exclusions" style="display: none;">
	<h4><?php esc_html_e( 'Excluded Products', 'smart-cycle-discounts' ); ?></h4>
	<div class="wsscd-exclusions-list">
		<!-- Exclusions will be inserted here via JavaScript -->
	</div>
</div>
<?php
$impact_content = ob_get_clean();

wsscd_wizard_card(
	array(
		'title'      => __( 'Campaign Impact Analysis', 'smart-cycle-discounts' ),
		'subtitle'   => __( 'Estimated reach and coverage of your campaign', 'smart-cycle-discounts' ),
		'icon'       => 'chart-bar',
		'content'    => $impact_content,
		'class'      => 'wsscd-impact-analysis-card',
		'help_topic' => 'card-impact-analysis',
	)
);
?>

</div><!-- #wsscd-health-container -->

<!-- Launch Options -->
<?php
ob_start();

// Get current campaign status if editing
$current_status = '';
if ( $is_edit_mode && isset( $GLOBALS['wsscd_wizard_data']['campaign']['status'] ) ) {
	$current_status = $GLOBALS['wsscd_wizard_data']['campaign']['status'];
}

// Detect if campaign has future start date
$has_future_start = false;
if ( isset( $step_data['start_type'] ) && 'scheduled' === $step_data['start_type'] ) {
	if ( ! empty( $step_data['start_date'] ) ) {
		$start_date       = $step_data['start_date'];
		$has_future_start = ( strtotime( $start_date ) > time() );
	}
}
?>
<div class="wsscd-launch-container">
	<div class="wsscd-launch-options">
		<label class="wsscd-launch-option" data-option="active">
			<input type="radio" name="launch_option" value="active" <?php checked( $launch_option, 'active' ); ?>>
			<div class="wsscd-launch-option-card">
				<div class="wsscd-launch-option-icon">
					<?php WSSCD_Icon_Helper::render( 'check', array( 'size' => 16 ) ); ?>
				</div>
				<div class="wsscd-launch-option-body">
					<?php if ( $is_edit_mode ) : ?>
						<h4><?php esc_html_e( 'Activate Campaign', 'smart-cycle-discounts' ); ?></h4>
						<?php if ( 'draft' === $current_status ) : ?>
							<p><?php esc_html_e( 'Save changes and activate this campaign to make it live', 'smart-cycle-discounts' ); ?></p>
						<?php elseif ( 'scheduled' === $current_status ) : ?>
							<p><?php esc_html_e( 'Save changes and keep campaign scheduled for future activation', 'smart-cycle-discounts' ); ?></p>
						<?php else : ?>
							<p><?php esc_html_e( 'Save changes and keep campaign active', 'smart-cycle-discounts' ); ?></p>
						<?php endif; ?>
					<?php else : ?>
						<h4><?php esc_html_e( 'Launch Campaign', 'smart-cycle-discounts' ); ?></h4>
						<?php if ( $has_future_start ) : ?>
							<p><?php esc_html_e( 'Schedule campaign to activate automatically at the set start time', 'smart-cycle-discounts' ); ?></p>
						<?php else : ?>
							<p><?php esc_html_e( 'Activate immediately and make the discount available to customers now', 'smart-cycle-discounts' ); ?></p>
						<?php endif; ?>
					<?php endif; ?>
				</div>
				<div class="wsscd-launch-option-check">
					<?php WSSCD_Icon_Helper::render( 'saved', array( 'size' => 16 ) ); ?>
				</div>
			</div>
		</label>

		<label class="wsscd-launch-option" data-option="draft">
			<input type="radio" name="launch_option" value="draft" <?php checked( $launch_option, 'draft' ); ?>>
			<div class="wsscd-launch-option-card">
				<div class="wsscd-launch-option-icon">
					<?php WSSCD_Icon_Helper::render( 'edit', array( 'size' => 16 ) ); ?>
				</div>
				<div class="wsscd-launch-option-body">
					<?php if ( $is_edit_mode ) : ?>
						<h4><?php esc_html_e( 'Save as Draft', 'smart-cycle-discounts' ); ?></h4>
						<?php if ( 'active' === $current_status || 'scheduled' === $current_status ) : ?>
							<p><?php esc_html_e( 'Save changes and deactivate campaign (will stop running immediately)', 'smart-cycle-discounts' ); ?></p>
						<?php else : ?>
							<p><?php esc_html_e( 'Save changes without activating (campaign stays inactive)', 'smart-cycle-discounts' ); ?></p>
						<?php endif; ?>
					<?php else : ?>
						<h4><?php esc_html_e( 'Save as Draft', 'smart-cycle-discounts' ); ?></h4>
						<p><?php esc_html_e( 'Save for review without launching. You can activate it later from the campaigns list', 'smart-cycle-discounts' ); ?></p>
					<?php endif; ?>
				</div>
				<div class="wsscd-launch-option-check">
					<?php WSSCD_Icon_Helper::render( 'saved', array( 'size' => 16 ) ); ?>
				</div>
			</div>
		</label>
	</div>

	<div class="wsscd-launch-info">
		<?php WSSCD_Icon_Helper::render( 'info', array( 'size' => 16 ) ); ?>
		<span class="wsscd-launch-info-text" data-active="<?php esc_attr_e( 'Campaign will be activated and customers can use the discount based on your schedule.', 'smart-cycle-discounts' ); ?>" data-draft="<?php esc_attr_e( 'Campaign will be saved as draft. No discounts will apply until you activate it.', 'smart-cycle-discounts' ); ?>">
			<?php esc_html_e( 'Campaign will be activated and customers can use the discount based on your schedule.', 'smart-cycle-discounts' ); ?>
		</span>
	</div>
</div>
<?php
$launch_content = ob_get_clean();

wsscd_wizard_card(
	array(
		'title'    => $is_edit_mode ? __( 'Update Your Campaign', 'smart-cycle-discounts' ) : __( 'Launch Your Campaign', 'smart-cycle-discounts' ),
		'subtitle' => __( 'Choose how you want to proceed', 'smart-cycle-discounts' ),
		'icon'     => 'controls-play',
		'content'  => $launch_content,
		'class'    => 'wsscd-launch-section',
		'help_topic' => 'card-launch-options',
	)
);
?>

<?php
// Get all content
$content = ob_get_clean();

// Render using template wrapper
wsscd_wizard_render_step(
	array(
		'title'       => __( 'Review & Launch', 'smart-cycle-discounts' ),
		'description' => __( 'Campaign health check and validation', 'smart-cycle-discounts' ),
		'content'     => $content,
		'step'        => 'review',
	)
);
?>

<!-- Initialize state data for Review step -->
<?php
wsscd_wizard_state_script(
	'review',
	array(
		'launch_option' => $launch_option,
		'all_data'      => $step_data,
	)
);
?>
