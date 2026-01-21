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
$wsscd_enabled     = isset( $data['enabled'] ) && $data['enabled'];
$wsscd_score       = isset( $data['score'] ) ? absint( $data['score'] ) : 0;
$wsscd_status      = isset( $data['status'] ) ? $data['status'] : 'unknown';
$wsscd_issues      = isset( $data['issues'] ) ? $data['issues'] : array();
$wsscd_warnings    = isset( $data['warnings'] ) ? $data['warnings'] : array();
$wsscd_suggestions = isset( $data['suggestions'] ) ? $data['suggestions'] : array();

// Status configurations - colors use CSS custom properties via classes
// The 'color' values are fallbacks for SVG inline styles where CSS variables aren't available
$wsscd_status_config = array(
	'excellent' => array(
		'label'    => __( 'Excellent', 'smart-cycle-discounts' ),
		'icon'     => 'yes-alt',
		'color'    => 'var(--wsscd-color-success)',
		'fallback' => '#00a32a', // --wsscd-color-success
	),
	'good'      => array(
		'label'    => __( 'Good', 'smart-cycle-discounts' ),
		'icon'     => 'yes',
		'color'    => 'var(--wsscd-color-success-light)',
		'fallback' => '#46b450', // --wsscd-color-success-light
	),
	'fair'      => array(
		'label'    => __( 'Fair', 'smart-cycle-discounts' ),
		'icon'     => 'warning',
		'color'    => 'var(--wsscd-color-warning)',
		'fallback' => '#dba617', // --wsscd-color-warning
	),
	'poor'      => array(
		'label'    => __( 'Poor', 'smart-cycle-discounts' ),
		'icon'     => 'dismiss',
		'color'    => 'var(--wsscd-color-danger)',
		'fallback' => '#d63638', // --wsscd-color-danger
	),
	'critical'  => array(
		'label'    => __( 'Critical', 'smart-cycle-discounts' ),
		'icon'     => 'no',
		'color'    => 'var(--wsscd-color-danger)',
		'fallback' => '#d63638', // --wsscd-color-danger
	),
);

$wsscd_current_status = isset( $wsscd_status_config[ $wsscd_status ] ) ? $wsscd_status_config[ $wsscd_status ] : $wsscd_status_config['fair'];

if ( ! $wsscd_enabled ) :
	?>
	<div class="wsscd-health-unavailable">
		<?php WSSCD_Icon_Helper::render( 'info', array( 'size' => 20 ) ); ?>
		<p><?php esc_html_e( 'Health analysis is not available for this campaign.', 'smart-cycle-discounts' ); ?></p>
	</div>
	<?php
	return;
endif;
?>

<div class="wsscd-health-section">

	<!-- Health Score Display -->
	<div class="wsscd-health-score-card">
		<div class="wsscd-health-score-visual">
			<!-- Circular Progress -->
			<svg class="wsscd-health-circle wsscd-health-circle-<?php echo esc_attr( $wsscd_status ); ?>" viewBox="0 0 120 120" width="120" height="120">
				<!-- Background circle -->
				<circle cx="60" cy="60" r="54" fill="none" stroke="var(--wsscd-color-surface-dark, #e5e7eb)" stroke-width="8"></circle>
				<!-- Progress circle - uses fallback color for SVG compatibility -->
				<circle
					class="wsscd-health-progress"
					cx="60"
					cy="60"
					r="54"
					fill="none"
					stroke="<?php echo esc_attr( isset( $wsscd_current_status['fallback'] ) ? $wsscd_current_status['fallback'] : '#00a32a' ); ?>"
					stroke-width="8"
					stroke-dasharray="339.292"
					stroke-dashoffset="<?php echo esc_attr( 339.292 - ( $wsscd_score / 100 * 339.292 ) ); ?>"
					stroke-linecap="round"
					transform="rotate(-90 60 60)"
				></circle>
			</svg>
			<div class="wsscd-health-score-number">
				<span class="wsscd-health-score-value"><?php echo absint( $wsscd_score ); ?></span>
				<span class="wsscd-health-score-max">/100</span>
			</div>
		</div>

		<div class="wsscd-health-score-details">
			<div class="wsscd-health-status-badge wsscd-status-<?php echo esc_attr( $wsscd_status ); ?>">
				<?php WSSCD_Icon_Helper::render( $wsscd_current_status['icon'], array( 'size' => 16 ) ); ?>
				<span><?php echo esc_html( $wsscd_current_status['label'] ); ?></span>
			</div>
			<p class="wsscd-health-score-description">
				<?php
				if ( $wsscd_score >= 90 ) {
					esc_html_e( 'Campaign is optimally configured with no critical issues.', 'smart-cycle-discounts' );
				} elseif ( $wsscd_score >= 70 ) {
					esc_html_e( 'Campaign is well-configured with minor improvements available.', 'smart-cycle-discounts' );
				} elseif ( $wsscd_score >= 50 ) {
					esc_html_e( 'Campaign has some issues that should be addressed.', 'smart-cycle-discounts' );
				} else {
					esc_html_e( 'Campaign has significant issues requiring immediate attention.', 'smart-cycle-discounts' );
				}
				?>
			</p>
		</div>
	</div>

	<!-- Critical Issues -->
	<?php if ( ! empty( $wsscd_issues ) ) : ?>
		<div class="wsscd-health-alerts wsscd-health-issues">
			<div class="wsscd-health-alerts-header">
				<?php WSSCD_Icon_Helper::render( 'warning', array( 'size' => 16 ) ); ?>
				<h5><?php esc_html_e( 'Critical Issues', 'smart-cycle-discounts' ); ?></h5>
				<span class="wsscd-health-count"><?php echo esc_html( count( $wsscd_issues ) ); ?></span>
			</div>
			<ul class="wsscd-health-alerts-list">
				<?php foreach ( $wsscd_issues as $wsscd_issue ) : ?>
					<li class="wsscd-health-alert-item wsscd-alert-critical">
						<?php WSSCD_Icon_Helper::render( 'dismiss', array( 'size' => 14 ) ); ?>
						<span><?php echo esc_html( $wsscd_issue ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<!-- Warnings -->
	<?php if ( ! empty( $wsscd_warnings ) ) : ?>
		<div class="wsscd-health-alerts wsscd-health-warnings">
			<div class="wsscd-health-alerts-header">
				<?php WSSCD_Icon_Helper::render( 'info', array( 'size' => 16 ) ); ?>
				<h5><?php esc_html_e( 'Warnings', 'smart-cycle-discounts' ); ?></h5>
				<span class="wsscd-health-count"><?php echo esc_html( count( $wsscd_warnings ) ); ?></span>
			</div>
			<ul class="wsscd-health-alerts-list">
				<?php foreach ( $wsscd_warnings as $wsscd_warning ) : ?>
					<li class="wsscd-health-alert-item wsscd-alert-warning">
						<?php WSSCD_Icon_Helper::render( 'warning', array( 'size' => 14 ) ); ?>
						<span><?php echo esc_html( $wsscd_warning ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<!-- Suggestions -->
	<?php if ( ! empty( $wsscd_suggestions ) ) : ?>
		<div class="wsscd-health-alerts wsscd-health-suggestions">
			<div class="wsscd-health-alerts-header">
				<?php WSSCD_Icon_Helper::render( 'lightbulb', array( 'size' => 16 ) ); ?>
				<h5><?php esc_html_e( 'Suggestions', 'smart-cycle-discounts' ); ?></h5>
				<span class="wsscd-health-count"><?php echo esc_html( count( $wsscd_suggestions ) ); ?></span>
			</div>
			<ul class="wsscd-health-alerts-list">
				<?php foreach ( $wsscd_suggestions as $wsscd_suggestion ) : ?>
					<li class="wsscd-health-alert-item wsscd-alert-suggestion">
						<?php WSSCD_Icon_Helper::render( 'yes', array( 'size' => 14 ) ); ?>
						<span><?php echo esc_html( $wsscd_suggestion ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<!-- Perfect Health Message -->
	<?php if ( empty( $wsscd_issues ) && empty( $wsscd_warnings ) && empty( $wsscd_suggestions ) && $wsscd_score >= 90 ) : ?>
		<div class="wsscd-health-perfect">
			<?php WSSCD_Icon_Helper::render( 'yes-alt', array( 'size' => 32 ) ); ?>
			<h4><?php esc_html_e( 'Perfect Health!', 'smart-cycle-discounts' ); ?></h4>
			<p><?php esc_html_e( 'Your campaign is optimally configured with no issues detected.', 'smart-cycle-discounts' ); ?></p>
		</div>
	<?php endif; ?>

</div>
