<?php
/**
 * Campaign Overview Panel - Health Section
 *
 * Displays campaign health score, status, and issues/warnings/suggestions.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/views/admin/components/partials
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// Extract health data
$enabled     = isset( $data['enabled'] ) && $data['enabled'];
$score       = isset( $data['score'] ) ? absint( $data['score'] ) : 0;
$status      = isset( $data['status'] ) ? $data['status'] : 'unknown';
$issues      = isset( $data['issues'] ) ? $data['issues'] : array();
$warnings    = isset( $data['warnings'] ) ? $data['warnings'] : array();
$suggestions = isset( $data['suggestions'] ) ? $data['suggestions'] : array();

// Status configurations - colors use CSS custom properties via classes
// The 'color' values are fallbacks for SVG inline styles where CSS variables aren't available
$status_config = array(
	'excellent' => array(
		'label'    => __( 'Excellent', 'smart-cycle-discounts' ),
		'icon'     => 'yes-alt',
		'color'    => 'var(--scd-color-success)',
		'fallback' => '#00a32a', // --scd-color-success
	),
	'good'      => array(
		'label'    => __( 'Good', 'smart-cycle-discounts' ),
		'icon'     => 'yes',
		'color'    => 'var(--scd-color-success-light)',
		'fallback' => '#46b450', // --scd-color-success-light
	),
	'fair'      => array(
		'label'    => __( 'Fair', 'smart-cycle-discounts' ),
		'icon'     => 'warning',
		'color'    => 'var(--scd-color-warning)',
		'fallback' => '#dba617', // --scd-color-warning
	),
	'poor'      => array(
		'label'    => __( 'Poor', 'smart-cycle-discounts' ),
		'icon'     => 'dismiss',
		'color'    => 'var(--scd-color-danger)',
		'fallback' => '#d63638', // --scd-color-danger
	),
	'critical'  => array(
		'label'    => __( 'Critical', 'smart-cycle-discounts' ),
		'icon'     => 'no',
		'color'    => 'var(--scd-color-danger)',
		'fallback' => '#d63638', // --scd-color-danger
	),
);

$current_status = isset( $status_config[ $status ] ) ? $status_config[ $status ] : $status_config['fair'];

if ( ! $enabled ) :
	?>
	<div class="scd-health-unavailable">
		<?php echo SCD_Icon_Helper::get( 'info', array( 'size' => 20 ) ); ?>
		<p><?php esc_html_e( 'Health analysis is not available for this campaign.', 'smart-cycle-discounts' ); ?></p>
	</div>
	<?php
	return;
endif;
?>

<div class="scd-health-section">

	<!-- Health Score Display -->
	<div class="scd-health-score-card">
		<div class="scd-health-score-visual">
			<!-- Circular Progress -->
			<svg class="scd-health-circle scd-health-circle-<?php echo esc_attr( $status ); ?>" viewBox="0 0 120 120" width="120" height="120">
				<!-- Background circle -->
				<circle cx="60" cy="60" r="54" fill="none" stroke="var(--scd-color-surface-dark, #e5e7eb)" stroke-width="8"></circle>
				<!-- Progress circle - uses fallback color for SVG compatibility -->
				<circle
					class="scd-health-progress"
					cx="60"
					cy="60"
					r="54"
					fill="none"
					stroke="<?php echo esc_attr( isset( $current_status['fallback'] ) ? $current_status['fallback'] : '#00a32a' ); ?>"
					stroke-width="8"
					stroke-dasharray="339.292"
					stroke-dashoffset="<?php echo esc_attr( 339.292 - ( $score / 100 * 339.292 ) ); ?>"
					stroke-linecap="round"
					transform="rotate(-90 60 60)"
				></circle>
			</svg>
			<div class="scd-health-score-number">
				<span class="scd-health-score-value"><?php echo absint( $score ); ?></span>
				<span class="scd-health-score-max">/100</span>
			</div>
		</div>

		<div class="scd-health-score-details">
			<div class="scd-health-status-badge scd-status-<?php echo esc_attr( $status ); ?>">
				<?php echo SCD_Icon_Helper::get( $current_status['icon'], array( 'size' => 16 ) ); ?>
				<span><?php echo esc_html( $current_status['label'] ); ?></span>
			</div>
			<p class="scd-health-score-description">
				<?php
				if ( $score >= 90 ) {
					esc_html_e( 'Campaign is optimally configured with no critical issues.', 'smart-cycle-discounts' );
				} elseif ( $score >= 70 ) {
					esc_html_e( 'Campaign is well-configured with minor improvements available.', 'smart-cycle-discounts' );
				} elseif ( $score >= 50 ) {
					esc_html_e( 'Campaign has some issues that should be addressed.', 'smart-cycle-discounts' );
				} else {
					esc_html_e( 'Campaign has significant issues requiring immediate attention.', 'smart-cycle-discounts' );
				}
				?>
			</p>
		</div>
	</div>

	<!-- Critical Issues -->
	<?php if ( ! empty( $issues ) ) : ?>
		<div class="scd-health-alerts scd-health-issues">
			<div class="scd-health-alerts-header">
				<?php echo SCD_Icon_Helper::get( 'warning', array( 'size' => 16 ) ); ?>
				<h5><?php esc_html_e( 'Critical Issues', 'smart-cycle-discounts' ); ?></h5>
				<span class="scd-health-count"><?php echo count( $issues ); ?></span>
			</div>
			<ul class="scd-health-alerts-list">
				<?php foreach ( $issues as $issue ) : ?>
					<li class="scd-health-alert-item scd-alert-critical">
						<?php echo SCD_Icon_Helper::get( 'dismiss', array( 'size' => 14 ) ); ?>
						<span><?php echo esc_html( $issue ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<!-- Warnings -->
	<?php if ( ! empty( $warnings ) ) : ?>
		<div class="scd-health-alerts scd-health-warnings">
			<div class="scd-health-alerts-header">
				<?php echo SCD_Icon_Helper::get( 'info', array( 'size' => 16 ) ); ?>
				<h5><?php esc_html_e( 'Warnings', 'smart-cycle-discounts' ); ?></h5>
				<span class="scd-health-count"><?php echo count( $warnings ); ?></span>
			</div>
			<ul class="scd-health-alerts-list">
				<?php foreach ( $warnings as $warning ) : ?>
					<li class="scd-health-alert-item scd-alert-warning">
						<?php echo SCD_Icon_Helper::get( 'warning', array( 'size' => 14 ) ); ?>
						<span><?php echo esc_html( $warning ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<!-- Suggestions -->
	<?php if ( ! empty( $suggestions ) ) : ?>
		<div class="scd-health-alerts scd-health-suggestions">
			<div class="scd-health-alerts-header">
				<?php echo SCD_Icon_Helper::get( 'lightbulb', array( 'size' => 16 ) ); ?>
				<h5><?php esc_html_e( 'Suggestions', 'smart-cycle-discounts' ); ?></h5>
				<span class="scd-health-count"><?php echo count( $suggestions ); ?></span>
			</div>
			<ul class="scd-health-alerts-list">
				<?php foreach ( $suggestions as $suggestion ) : ?>
					<li class="scd-health-alert-item scd-alert-suggestion">
						<?php echo SCD_Icon_Helper::get( 'yes', array( 'size' => 14 ) ); ?>
						<span><?php echo esc_html( $suggestion ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<!-- Perfect Health Message -->
	<?php if ( empty( $issues ) && empty( $warnings ) && empty( $suggestions ) && $score >= 90 ) : ?>
		<div class="scd-health-perfect">
			<?php echo SCD_Icon_Helper::get( 'yes-alt', array( 'size' => 32 ) ); ?>
			<h4><?php esc_html_e( 'Perfect Health!', 'smart-cycle-discounts' ); ?></h4>
			<p><?php esc_html_e( 'Your campaign is optimally configured with no issues detected.', 'smart-cycle-discounts' ); ?></p>
		</div>
	<?php endif; ?>

</div>
