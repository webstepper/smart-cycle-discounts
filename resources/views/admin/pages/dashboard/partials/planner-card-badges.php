<?php
/**
 * Campaign Planner Card Badges Partial
 *
 * Displays badges for campaign planner cards (major event + state badge).
 * This template should be used consistently across all card renderings.
 *
 * @var string $state          Campaign state: 'past', 'active', or 'future'
 * @var string $state_label    Localized state label for display
 * @var bool   $is_major_event Whether this is a major event campaign
 *
 * @package SmartCycleDiscounts
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Badge icons mapping
$badge_icons = array(
	'past'   => 'clock',
	'active' => 'star-filled',
	'future' => 'calendar',
);
$badge_icon = $badge_icons[ $state ] ?? 'info';
?>

<div class="scd-planner-card-badges">
	<?php if ( $is_major_event ) : ?>
		<span class="scd-planner-card-badge scd-badge-major"><?php esc_html_e( 'Major Event', 'smart-cycle-discounts' ); ?></span>
	<?php endif; ?>
	<span class="scd-planner-card-badge scd-badge-<?php echo esc_attr( $state ); ?>">
		<span class="dashicons dashicons-<?php echo esc_attr( $badge_icon ); ?>"></span>
		<?php echo esc_html( $state_label ); ?>
	</span>
</div>
