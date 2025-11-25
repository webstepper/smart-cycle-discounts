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

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Security: Verify user capabilities
if ( ! current_user_can( 'manage_woocommerce' ) ) {
	wp_die( esc_html__( 'You do not have permission to access this page.', 'smart-cycle-discounts' ) );
}

// Initialize variables using shared function
scd_wizard_init_step_vars( $step_data, $validation_errors );

// Set default launch option (default to 'active' - matches field definition)
$launch_option = isset( $step_data['launch_option'] ) ? $step_data['launch_option'] : 'active';

// Detect edit mode - check if we have a campaign ID in the wizard data
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display logic, nonce checked on form submission
$campaign_id_from_get = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
$is_edit_mode = $campaign_id_from_get > 0 || ( isset( $GLOBALS['scd_wizard_data']['campaign_id'] ) && $GLOBALS['scd_wizard_data']['campaign_id'] > 0 );

// Prepare content
ob_start();
?>

<?php scd_wizard_validation_notice( $validation_errors ); ?>

<!-- Health Check Loading State -->
<div id="scd-health-loading" class="scd-health-loading">
	<span class="spinner is-active"></span>
	<p><?php esc_html_e( 'Analyzing campaign configuration...', 'smart-cycle-discounts' ); ?></p>
</div>

<!-- Health Check Container -->
<div id="scd-health-container" class="scd-health-container" style="display: none;">

	<!-- Campaign Health Score -->
	<div class="scd-health-score-card">
		<div class="scd-health-score-header">
			<div class="scd-health-score-label">
				<h3 class="scd-form-section-title"><?php esc_html_e( 'Campaign Health Score', 'smart-cycle-discounts' ); ?></h3>
				<p class="scd-health-score-subtitle"></p>
			</div>
			<div class="scd-health-score-value">
				<span class="scd-score-number">0</span>
				<span class="scd-score-max">/100</span>
			</div>
		</div>
		<div class="scd-health-score-bar">
			<div class="scd-health-score-fill" style="width: 0%"></div>
		</div>
	</div>

	<!-- Health Factors Section (Critical Issues) -->
	<div id="scd-health-factors" class="scd-health-factors-container" style="display: none;">
		<div class="scd-health-factors-header">
			<?php echo SCD_Icon_Helper::get( 'admin-generic', array( 'size' => 16 ) ); ?>
			<h3 class="scd-form-section-title"><?php esc_html_e( 'Health Factors', 'smart-cycle-discounts' ); ?></h3>
			<p class="scd-health-factors-desc"><?php esc_html_e( 'Critical issues that must be fixed before launch', 'smart-cycle-discounts' ); ?></p>
		</div>

		<!-- Critical Issues Section -->
		<div id="scd-critical-issues" class="scd-issues-section scd-critical-issues" style="display: none;">
			<div class="scd-issues-header">
				<?php echo SCD_Icon_Helper::get( 'warning', array( 'size' => 16 ) ); ?>
				<h4><?php esc_html_e( 'Critical Issues', 'smart-cycle-discounts' ); ?></h4>
				<span class="scd-issues-count">0</span>
			</div>
			<div class="scd-issues-content">
				<!-- Critical issues will be inserted here via JavaScript -->
			</div>
		</div>
	</div>

	<!-- Recommendations Section -->
	<div id="scd-recommendations" class="scd-recommendations" style="display: none;">
		<div class="scd-section-header">
			<?php echo SCD_Icon_Helper::get( 'lightbulb', array( 'size' => 16 ) ); ?>
			<h3 class="scd-form-section-title"><?php esc_html_e( 'Recommendations', 'smart-cycle-discounts' ); ?></h3>
			<p class="scd-section-desc"><?php esc_html_e( 'Actions to improve your campaign health and performance', 'smart-cycle-discounts' ); ?></p>
		</div>
		<div class="scd-recommendations-categories">
			<!-- Categorized recommendations will be inserted here via JavaScript -->
		</div>
	</div>

	<!-- Conflict Preview -->
	<div id="scd-conflict-preview" class="scd-conflict-preview" style="display: none;">
		<div class="scd-section-header">
			<?php echo SCD_Icon_Helper::get( 'warning', array( 'size' => 16 ) ); ?>
			<h3 class="scd-form-section-title"><?php esc_html_e( 'Campaign Conflicts', 'smart-cycle-discounts' ); ?></h3>
			<p class="scd-section-desc"><?php esc_html_e( 'Other campaigns affecting the same products', 'smart-cycle-discounts' ); ?></p>
		</div>
		<div class="scd-conflicts-list">
			<!-- Conflicts will be inserted here via JavaScript -->
		</div>
	</div>

	<!-- Campaign Impact Analysis -->
	<div class="scd-impact-analysis">
		<div class="scd-section-header">
			<?php echo SCD_Icon_Helper::get( 'chart-bar', array( 'size' => 20 ) ); ?>
			<h3 class="scd-form-section-title"><?php esc_html_e( 'Campaign Impact Analysis', 'smart-cycle-discounts' ); ?></h3>
			<p class="scd-section-desc"><?php esc_html_e( 'Estimated reach and coverage of your campaign', 'smart-cycle-discounts' ); ?></p>
		</div>
		<div class="scd-impact-grid">
			<div class="scd-impact-item">
				<div class="scd-impact-label"><?php esc_html_e( 'Products Matched', 'smart-cycle-discounts' ); ?></div>
				<div class="scd-impact-value" data-metric="products_matched">--</div>
			</div>
			<div class="scd-impact-item">
				<div class="scd-impact-label"><?php esc_html_e( 'Actually Discounted', 'smart-cycle-discounts' ); ?></div>
				<div class="scd-impact-value" data-metric="products_discounted">--</div>
			</div>
			<div class="scd-impact-item">
				<div class="scd-impact-label"><?php esc_html_e( 'Coverage Rate', 'smart-cycle-discounts' ); ?></div>
				<div class="scd-impact-value" data-metric="coverage_percentage">--</div>
			</div>
		</div>
		<div id="scd-exclusions" class="scd-exclusions" style="display: none;">
			<h4><?php esc_html_e( 'Excluded Products', 'smart-cycle-discounts' ); ?></h4>
			<div class="scd-exclusions-list">
				<!-- Exclusions will be inserted here via JavaScript -->
			</div>
		</div>
	</div>

</div><!-- #scd-health-container -->

<!-- Launch Options -->
<?php
ob_start();

// Get current campaign status if editing
$current_status = '';
if ( $is_edit_mode && isset( $GLOBALS['scd_wizard_data']['campaign']['status'] ) ) {
	$current_status = $GLOBALS['scd_wizard_data']['campaign']['status'];
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
<div class="scd-launch-container">
	<div class="scd-launch-options">
		<label class="scd-launch-option" data-option="active">
			<input type="radio" name="launch_option" value="active" <?php checked( $launch_option, 'active' ); ?>>
			<div class="scd-launch-option-card">
				<div class="scd-launch-option-icon">
					<?php echo SCD_Icon_Helper::get( 'check', array( 'size' => 16 ) ); ?>
				</div>
				<div class="scd-launch-option-body">
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
				<div class="scd-launch-option-check">
					<?php echo SCD_Icon_Helper::get( 'saved', array( 'size' => 16 ) ); ?>
				</div>
			</div>
		</label>

		<label class="scd-launch-option" data-option="draft">
			<input type="radio" name="launch_option" value="draft" <?php checked( $launch_option, 'draft' ); ?>>
			<div class="scd-launch-option-card">
				<div class="scd-launch-option-icon">
					<?php echo SCD_Icon_Helper::get( 'edit', array( 'size' => 16 ) ); ?>
				</div>
				<div class="scd-launch-option-body">
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
				<div class="scd-launch-option-check">
					<?php echo SCD_Icon_Helper::get( 'saved', array( 'size' => 16 ) ); ?>
				</div>
			</div>
		</label>
	</div>

	<div class="scd-launch-info">
		<?php echo SCD_Icon_Helper::get( 'info', array( 'size' => 16 ) ); ?>
		<span class="scd-launch-info-text" data-active="<?php esc_attr_e( 'Campaign will be activated and customers can use the discount based on your schedule.', 'smart-cycle-discounts' ); ?>" data-draft="<?php esc_attr_e( 'Campaign will be saved as draft. No discounts will apply until you activate it.', 'smart-cycle-discounts' ); ?>">
			<?php esc_html_e( 'Campaign will be activated and customers can use the discount based on your schedule.', 'smart-cycle-discounts' ); ?>
		</span>
	</div>
</div>
<?php
$launch_content = ob_get_clean();

scd_wizard_card(
	array(
		'title'    => $is_edit_mode ? __( 'Update Your Campaign', 'smart-cycle-discounts' ) : __( 'Launch Your Campaign', 'smart-cycle-discounts' ),
		'subtitle' => __( 'Choose how you want to proceed', 'smart-cycle-discounts' ),
		'icon'     => 'controls-play',
		'content'  => $launch_content,
		'class'    => 'scd-launch-section',
		'help_topic' => 'card-launch-options',
	)
);
?>

<?php
// Get all content
$content = ob_get_clean();

// Render using template wrapper
scd_wizard_render_step(
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
scd_wizard_state_script(
	'review',
	array(
		'launch_option' => $launch_option,
		'all_data'      => $step_data,
	)
);
?>

<style>
/* Health Check Styles */
.scd-health-loading {
	text-align: center;
	padding: 60px 20px;
}

.scd-health-score-card {
	background: #fff;
	border: 1px solid #dcdcde;
	border-left: 4px solid #2271b1;
	border-radius: 4px;
	padding: 24px;
	margin-bottom: 24px;
	transition: border-left-color 0.3s ease;
}

.scd-health-score-card.excellent {
	border-left-color: #00a32a;
}

.scd-health-score-card.good {
	border-left-color: #2271b1;
}

.scd-health-score-card.fair {
	border-left-color: #dba617;
}

.scd-health-score-card.poor {
	border-left-color: #d63638;
}

.scd-health-score-card.critical {
	border-left-color: #d63638;
}

.scd-health-score-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 16px;
}

.scd-health-score-label h3 {
	margin: 0 0 4px 0;
	font-size: 18px;
}

.scd-health-score-subtitle {
	margin: 0;
	color: #646970;
	font-size: 13px;
	font-weight: 500;
	transition: color 0.3s ease;
}

.scd-health-score-subtitle.critical {
	color: #d63638;
	font-weight: 600;
}

.scd-health-score-subtitle.excellent {
	color: #00a32a;
}

.scd-health-score-subtitle.good {
	color: #2271b1;
}

.scd-health-score-value {
	font-size: 48px;
	font-weight: 600;
	line-height: 1;
}

.scd-score-number {
	color: #2271b1;
	transition: color 0.3s ease;
}

.scd-score-number.excellent {
	color: #00a32a;
}

.scd-score-number.good {
	color: #2271b1;
}

.scd-score-number.fair {
	color: #dba617;
}

.scd-score-number.poor {
	color: #d63638;
}

.scd-score-number.critical {
	color: #d63638;
}

.scd-score-max {
	color: #646970;
	font-size: 24px;
}

.scd-health-score-bar {
	height: 8px;
	background: #f0f0f1;
	border-radius: 4px;
	overflow: hidden;
}

.scd-health-score-fill {
	height: 100%;
	background: #2271b1;
	transition: width 0.3s ease;
}

.scd-health-score-fill.excellent {
	background: #00a32a;
}

.scd-health-score-fill.good {
	background: #2271b1;
}

.scd-health-score-fill.fair {
	background: #dba617;
}

.scd-health-score-fill.poor {
	background: #d63638;
}

.scd-health-factors-container {
	background: #fff;
	border: 1px solid #dcdcde;
	border-radius: 4px;
	padding: 24px;
	margin-bottom: 24px;
	position: relative;
}

.scd-health-factors-container:before {
	content: "";
	position: absolute;
	top: -12px;
	left: 50%;
	transform: translateX( -50% );
	width: 0;
	height: 0;
	border-left: 12px solid transparent;
	border-right: 12px solid transparent;
	border-bottom: 12px solid #dcdcde;
}

.scd-health-factors-container:after {
	content: "";
	position: absolute;
	top: -11px;
	left: 50%;
	transform: translateX( -50% );
	width: 0;
	height: 0;
	border-left: 11px solid transparent;
	border-right: 11px solid transparent;
	border-bottom: 11px solid #fff;
}

.scd-health-factors-header {
	text-align: center;
	margin-bottom: 24px;
	padding-bottom: 16px;
	border-bottom: 2px solid #f0f0f1;
}

.scd-health-factors-header .dashicons {
	font-size: 24px;
	width: 24px;
	height: 24px;
	color: #2271b1;
}

.scd-health-factors-header h3 {
	margin: 8px 0 4px 0;
	font-size: 18px;
}

.scd-health-factors-desc {
	margin: 0;
	color: #646970;
	font-size: 13px;
}

.scd-issues-section {
	background: #f9f9f9;
	border: 1px solid #dcdcde;
	border-radius: 4px;
	padding: 20px;
	margin-bottom: 16px;
}

.scd-issues-section:last-child {
	margin-bottom: 0;
}

.scd-critical-issues {
	border-left: 4px solid #d63638;
}

.scd-issues-header {
	display: flex;
	align-items: center;
	gap: 8px;
	margin-bottom: 16px;
}

.scd-issues-header h4 {
	margin: 0;
	flex: 1;
	font-size: 15px;
}

.scd-issues-count {
	background: #646970;
	color: #fff;
	border-radius: 12px;
	padding: 2px 8px;
	font-size: 12px;
	font-weight: 600;
}

.scd-critical-issues .scd-issues-count {
	background: #d63638;
}

.scd-issue-item {
	padding: 16px;
	background: #f6f7f7;
	border-radius: 4px;
	margin-bottom: 12px;
	border-bottom: 1px solid #dcdcde;
}

.scd-issue-item:last-child {
	margin-bottom: 0;
	border-bottom: none;
}

.scd-issue-title {
	font-weight: 600;
	margin-bottom: 4px;
}

.scd-issue-message {
	color: #646970;
	margin-bottom: 12px;
}

.scd-issue-actions {
	display: flex;
	gap: 8px;
}

.scd-issue-action {
	display: inline-block;
	padding: 6px 12px;
	background: #2271b1;
	color: #fff;
	text-decoration: none;
	border-radius: 3px;
	font-size: 13px;
	cursor: pointer;
	border: none;
}

.scd-issue-action:hover {
	background: #135e96;
	color: #fff;
}

.scd-impact-analysis {
	background: #fff;
	border: 1px solid #dcdcde;
	border-radius: 4px;
	padding: 24px;
	margin-bottom: 24px;
}

.scd-section-header {
	display: flex;
	align-items: flex-start;
	gap: 8px;
	margin-bottom: 16px;
	flex-wrap: wrap;
}

.scd-section-header h3 {
	margin: 0;
	font-size: 16px;
	flex: 1;
}

.scd-section-desc {
	flex-basis: 100%;
	margin: 4px 0 0 32px;
	color: #646970;
	font-size: 13px;
}

.scd-impact-grid {
	display: grid;
	grid-template-columns: repeat( auto-fit, minmax( 200px, 1fr ) );
	gap: 16px;
	margin-bottom: 16px;
}

.scd-impact-item {
	text-align: center;
	padding: 16px;
	background: #f6f7f7;
	border-radius: 4px;
}

.scd-impact-label {
	font-size: 13px;
	color: #646970;
	margin-bottom: 8px;
}

.scd-impact-value {
	font-size: 32px;
	font-weight: 600;
	color: #1d2327;
}

.scd-exclusions h4 {
	margin: 16px 0 8px 0;
	font-size: 14px;
}

.scd-exclusions-list {
	list-style: none;
	margin: 0;
	padding: 0;
}

.scd-exclusion-item {
	padding: 8px 12px;
	background: #f6f7f7;
	border-radius: 3px;
	margin-bottom: 4px;
	display: flex;
	justify-content: space-between;
}

.scd-recommendations {
	background: #fff;
	border: 1px solid #dcdcde;
	border-left: 4px solid #2271b1;
	border-radius: 4px;
	padding: 24px;
	margin-bottom: 24px;
}

.scd-recommendations-list {
	list-style: none;
	margin: 0;
	padding: 0;
}

.scd-recommendations-list li {
	padding: 8px 0;
	padding-left: 24px;
	position: relative;
}

.scd-recommendations-list li:before {
	content: "✓";
	position: absolute;
	left: 0;
	color: #2271b1;
	font-weight: 600;
}

/* Severity Badges */
.scd-severity-badge {
	display: inline-block;
	padding: 2px 8px;
	border-radius: 3px;
	font-size: 11px;
	font-weight: 600;
	text-transform: uppercase;
	margin-left: 8px;
}

.scd-severity-badge.critical {
	background: #d63638;
	color: #fff;
}

.scd-severity-badge.high {
	background: #e65100;
	color: #fff;
}

.scd-severity-badge.medium {
	background: #f0a000;
	color: #fff;
}

.scd-severity-badge.low {
	background: #72aee6;
	color: #fff;
}

/* Priority Badges */
.scd-badge.scd-priority-badge {
	display: inline-flex;
	align-items: center;
	gap: 4px;
	padding: 4px 10px;
	border-radius: 4px;
	font-size: 11px;
	font-weight: 600;
	text-transform: uppercase;
	letter-spacing: 0.3px;
	flex-shrink: 0;
}

.scd-priority-badge.critical {
	background: #d63638;
	color: #fff;
	animation: scd-pulse 2s ease-in-out infinite;
}

@keyframes scd-pulse {
	0%, 100% { opacity: 1; }
	50% { opacity: 0.85; }
}

/* Recommendations Categories */
.scd-recommendations-categories {
	display: flex;
	flex-direction: column;
	gap: 16px;
}

.scd-recommendation-category {
	/* Category borders removed - individual recommendations now have colored borders */
}

.scd-recommendation-category-title {
	font-weight: 600;
	margin-bottom: 8px;
	display: flex;
	align-items: center;
	gap: 8px;
}

.scd-recommendation-category-title .dashicons {
	font-size: 18px;
	width: 18px;
	height: 18px;
}

.scd-recommendation-items {
	list-style: none;
	margin: 0;
	padding: 0;
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.scd-recommendation-item {
	padding: 16px;
	background: #fff;
	border: 1px solid #dcdcde;
	border-left: 4px solid #dcdcde;
	border-radius: 4px;
	display: block;
	position: relative;
	transition: box-shadow 0.2s, border-left-color 0.2s, background-color 0.2s;
}

.scd-recommendation-item:hover {
	box-shadow: 0 2px 4px rgba( 0, 0, 0, 0.05 );
}

.scd-recommendation-item.priority-critical {
	border-left-color: #d63638;
	background: #fff5f5;
}

.scd-recommendation-item.priority-high {
	border-left-color: #d63638;
}

.scd-recommendation-item.priority-medium {
	border-left-color: #dba617;
}

.scd-recommendation-item.priority-low {
	border-left-color: #2271b1;
}

.scd-recommendation-item:before {
	display: none;
}

.scd-recommendation-header {
	display: flex;
	align-items: flex-start;
	gap: 12px;
	margin-bottom: 12px;
}

.scd-recommendation-message {
	flex: 1;
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.scd-recommendation-text {
	color: #1d2327;
	line-height: 1.6;
	font-size: 14px;
}

.priority-critical .scd-recommendation-text {
	color: #2c1517;
	font-weight: 500;
}

.scd-recommendation-impact {
	display: inline-flex;
	align-items: center;
	gap: 4px;
	font-size: 13px;
	color: #2271b1;
	background: #f0f6fc;
	padding: 6px 10px;
	border-radius: 4px;
	font-weight: 500;
	align-self: flex-start;
}

.priority-critical .scd-recommendation-impact {
	color: #d63638;
	background: #ffe6e6;
}

.priority-high .scd-recommendation-impact {
	color: #d63638;
	background: #fff0f0;
}

.priority-medium .scd-recommendation-impact {
	color: #996800;
	background: #fff9e6;
}

.priority-low .scd-recommendation-impact {
	color: #2271b1;
	background: #f0f6fc;
}

/* Conflict Preview */
.scd-conflict-preview {
	background: #fff;
	border: 1px solid #dcdcde;
	border-left: 4px solid #dba617;
	border-radius: 4px;
	padding: 24px;
	margin-bottom: 24px;
}

.scd-conflicts-list {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.scd-conflict-item {
	padding: 12px;
	background: #fff9e6;
	border-radius: 4px;
	border-left: 3px solid #dba617;
}

.scd-conflict-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 4px;
}

.scd-conflict-name {
	font-weight: 600;
	color: #1d2327;
}

.scd-conflict-priority {
	display: inline-block;
	padding: 2px 8px;
	background: #646970;
	color: #fff;
	border-radius: 3px;
	font-size: 11px;
	font-weight: 600;
}

.scd-conflict-details {
	font-size: 13px;
	color: #646970;
}

/* Visual Progress Bars */
.scd-progress-bar-container {
	width: 100%;
	height: 8px;
	background: #f0f0f1;
	border-radius: 4px;
	overflow: hidden;
	margin-top: 8px;
}

.scd-progress-bar {
	height: 100%;
	background: #2271b1;
	border-radius: 4px;
	transition: width 0.3s ease;
}

.scd-progress-bar.excellent {
	background: #00a32a;
}

.scd-progress-bar.good {
	background: #2271b1;
}

.scd-progress-bar.fair {
	background: #dba617;
}

.scd-progress-bar.poor {
	background: #d63638;
}

/* Expandable Details */
.scd-expandable-item {
	margin-bottom: 12px;
}

.scd-expandable-header {
	cursor: pointer;
	padding: 12px;
	background: #f6f7f7;
	border-radius: 4px;
	display: flex;
	justify-content: space-between;
	align-items: center;
}

.scd-expandable-header:hover {
	background: #ebebeb;
}

.scd-expandable-toggle {
	transition: transform 0.2s;
}

.scd-expandable-item.expanded .scd-expandable-toggle {
	transform: rotate( 180deg );
}

.scd-expandable-content {
	display: none;
	padding: 12px;
	background: #fafafa;
	border: 1px solid #dcdcde;
	border-top: none;
	border-radius: 0 0 4px 4px;
}

.scd-expandable-item.expanded .scd-expandable-content {
	display: block;
}

/* Enhanced Recommendations - New Capabilities */

/* Recommendation Counter */
.scd-recommendation-counter {
	display: flex;
	gap: 10px;
	padding: 16px;
	background: #f6f7f7;
	border: 1px solid #dcdcde;
	border-radius: 4px;
	margin-bottom: 20px;
	flex-wrap: wrap;
	align-items: center;
}

.scd-counter-active,
.scd-counter-applied,
.scd-counter-dismissed {
	padding: 6px 12px;
	border-radius: 16px;
	font-size: 12px;
	font-weight: 600;
	display: inline-flex;
	align-items: center;
	gap: 4px;
}

.scd-counter-active {
	background: #2271b1;
	color: #fff;
}

.scd-counter-applied {
	background: #00a32a;
	color: #fff;
}

.scd-counter-dismissed {
	background: #dba617;
	color: #fff;
	cursor: pointer;
	transition: background 0.2s, transform 0.1s;
}

.scd-counter-dismissed:hover {
	background: #c29400;
	transform: translateY( -1px );
}

/* Explanation Toggle */
.scd-explanation-toggle {
	display: inline-flex;
	align-items: center;
	margin-top: 0;
	margin-bottom: 12px;
	padding: 6px 12px;
	background: transparent;
	border: 1px solid #dcdcde;
	border-radius: 4px;
	cursor: pointer;
	font-size: 13px;
	color: #2271b1;
	transition: background 0.2s, border-color 0.2s;
	font-weight: 500;
}

.scd-explanation-toggle:hover {
	background: #f0f6fc;
	border-color: #2271b1;
}

.scd-explanation-content {
	padding: 16px;
	background: #f6f7f7;
	border-left: 3px solid #2271b1;
	border-radius: 4px;
	margin-bottom: 12px;
	font-size: 13px;
	line-height: 1.7;
	color: #2c3338;
}

/* Action Buttons */
.scd-recommendation-actions {
	display: flex;
	gap: 10px;
	align-items: center;
	margin-top: 0;
	padding-top: 12px;
	border-top: 1px solid #f0f0f1;
	flex-wrap: wrap;
}

.scd-apply-btn {
	background: #2271b1;
	color: #fff;
	border-color: #2271b1;
	transition: background 0.2s;
	font-weight: 500;
	height: 32px;
	line-height: 30px;
	padding: 0 16px;
}

.scd-apply-btn:hover {
	background: #135e96;
	border-color: #135e96;
	color: #fff;
}

.scd-apply-btn:disabled {
	background: #a7aaad;
	border-color: #a7aaad;
	cursor: not-allowed;
}

.scd-dismiss-btn {
	background: #fff;
	color: #2c3338;
	border-color: #dcdcde;
	transition: background 0.2s, border-color 0.2s;
	height: 32px;
	line-height: 30px;
	padding: 0 16px;
}

.scd-dismiss-btn:hover {
	background: #f6f7f7;
	border-color: #8c8f94;
}

.scd-step-link {
	color: #2271b1;
	text-decoration: none;
	font-weight: 500;
	padding: 6px 12px;
	border-radius: 4px;
	transition: color 0.2s, background 0.2s;
	display: inline-flex;
	align-items: center;
	gap: 4px;
}

.scd-step-link:hover {
	color: #135e96;
	background: #f0f6fc;
	text-decoration: none;
}

/* Wizard Notices */
.scd-wizard-notices {
	margin-bottom: 16px;
}

.scd-wizard-notices .notice {
	margin: 0 0 8px 0;
}

/* Impact Analysis Enhancements */
.scd-impact-detail {
	font-size: 12px;
	color: #646970;
	margin-top: 4px;
	text-align: center;
}

/* Exclusion Items with Icons */
.scd-exclusion-item {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 12px;
	background: #f6f7f7;
	border-radius: 4px;
	border-left: 3px solid #dcdcde;
	margin-bottom: 8px;
}

.scd-exclusion-label {
	display: flex;
	align-items: center;
	gap: 8px;
	flex: 1;
	font-weight: 500;
}

.scd-exclusion-label .dashicons {
	color: #646970;
	font-size: 18px;
	width: 18px;
	height: 18px;
}

.scd-exclusion-count {
	font-size: 13px;
	color: #646970;
	background: #fff;
	padding: 4px 8px;
	border-radius: 3px;
	font-weight: 600;
}

/* Stock Risk Section */
.scd-stock-risk-section {
	background: #fff;
	border: 1px solid #dcdcde;
	border-left: 4px solid #d63638;
	border-radius: 4px;
	padding: 24px;
	margin-bottom: 24px;
}

.scd-stock-risk-summary {
	display: flex;
	gap: 16px;
	margin-bottom: 20px;
}

.scd-risk-stat {
	flex: 1;
	text-align: center;
	padding: 16px;
	background: #f6f7f7;
	border-radius: 4px;
	border-left: 4px solid #dcdcde;
}

.scd-risk-stat.high {
	border-left-color: #d63638;
}

.scd-risk-stat.medium {
	border-left-color: #dba617;
}

.scd-risk-count {
	display: block;
	font-size: 32px;
	font-weight: 600;
	color: #1d2327;
	margin-bottom: 4px;
}

.scd-risk-label {
	display: block;
	font-size: 13px;
	color: #646970;
	font-weight: 500;
	text-transform: uppercase;
	letter-spacing: 0.5px;
}

.scd-stock-risk-products {
	display: flex;
	flex-direction: column;
	gap: 12px;
	margin-bottom: 16px;
}

.scd-stock-risk-item {
	padding: 12px;
	background: #fff9e6;
	border-radius: 4px;
	border-left: 3px solid #dba617;
}

.scd-stock-risk-item.scd-risk-high {
	background: #ffe6e6;
	border-left-color: #d63638;
}

.scd-risk-product-name {
	display: flex;
	align-items: center;
	gap: 8px;
	font-weight: 600;
	margin-bottom: 8px;
	color: #1d2327;
}

.scd-risk-product-name .dashicons {
	font-size: 18px;
	width: 18px;
	height: 18px;
}

.scd-stock-risk-item.scd-risk-high .scd-risk-product-name .dashicons {
	color: #d63638;
}

.scd-stock-risk-item.scd-risk-medium .scd-risk-product-name .dashicons {
	color: #dba617;
}

.scd-risk-details {
	display: flex;
	gap: 16px;
	font-size: 13px;
	color: #646970;
}

.scd-risk-stock,
.scd-risk-demand {
	padding: 4px 8px;
	background: rgba( 255, 255, 255, 0.5 );
	border-radius: 3px;
}

.scd-stock-risk-note {
	display: flex;
	align-items: flex-start;
	gap: 8px;
	padding: 12px;
	background: #f0f6fc;
	border-radius: 4px;
	border-left: 3px solid #2271b1;
}

.scd-stock-risk-note .dashicons {
	color: #2271b1;
	font-size: 18px;
	width: 18px;
	height: 18px;
	margin-top: 2px;
}

.scd-stock-risk-note p {
	margin: 0;
	font-size: 13px;
	color: #2c3338;
	line-height: 1.5;
}
</style>
