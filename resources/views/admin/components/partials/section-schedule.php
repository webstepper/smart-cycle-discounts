<?php
/**
 * Campaign Overview Panel - Schedule Section
 *
 * Displays campaign schedule with visual timeline.
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

$starts_at       = isset( $data['starts_at'] ) ? $data['starts_at'] : null;
$ends_at         = isset( $data['ends_at'] ) ? $data['ends_at'] : null;
$timezone        = isset( $data['timezone'] ) ? $data['timezone'] : '';
$duration        = isset( $data['duration'] ) ? $data['duration'] : '';
$duration_detail = isset( $data['duration_detail'] ) ? $data['duration_detail'] : '';

// Calculate progress percentage for timeline bar
$progress_percentage = 0;
$is_active           = false;
$is_upcoming         = false;
$is_expired          = false;

if ( $starts_at instanceof DateTime && $ends_at instanceof DateTime ) {
	$now        = new DateTime( 'now', new DateTimeZone( 'UTC' ) );
	$start_time = $starts_at->getTimestamp();
	$end_time   = $ends_at->getTimestamp();
	$now_time   = $now->getTimestamp();

	if ( $now_time < $start_time ) {
		// Campaign hasn't started yet
		$is_upcoming = true;
	} elseif ( $now_time > $end_time ) {
		// Campaign has ended
		$is_expired          = true;
		$progress_percentage = 100;
	} else {
		// Campaign is active
		$is_active           = true;
		$total_duration      = $end_time - $start_time;
		$elapsed             = $now_time - $start_time;
		$progress_percentage = min( 100, max( 0, ( $elapsed / $total_duration ) * 100 ) );
	}
}
?>

<div class="scd-overview-subsection">
	<div class="scd-subsection-header">
		<?php echo SCD_Icon_Helper::get( 'calendar', array( 'size' => 16 ) ); ?>
		<h5><?php esc_html_e( 'Schedule', 'smart-cycle-discounts' ); ?></h5>
	</div>

	<!-- Date Range -->
	<div class="scd-schedule-dates">
		<?php if ( $starts_at instanceof DateTime ) : ?>
			<div class="scd-schedule-date scd-schedule-start">
				<div class="scd-schedule-date-label">
					<?php echo SCD_Icon_Helper::get( 'play', array( 'size' => 14 ) ); ?>
					<?php esc_html_e( 'Start', 'smart-cycle-discounts' ); ?>
				</div>
				<div class="scd-schedule-date-value">
					<?php echo esc_html( $starts_at->format( 'M j, Y g:i A' ) ); ?>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( $ends_at instanceof DateTime ) : ?>
			<div class="scd-schedule-date scd-schedule-end">
				<div class="scd-schedule-date-label">
					<?php echo SCD_Icon_Helper::get( 'pause', array( 'size' => 14 ) ); ?>
					<?php esc_html_e( 'End', 'smart-cycle-discounts' ); ?>
				</div>
				<div class="scd-schedule-date-value">
					<?php echo esc_html( $ends_at->format( 'M j, Y g:i A' ) ); ?>
				</div>
			</div>
		<?php endif; ?>
	</div>

	<!-- Visual Timeline -->
	<?php if ( $starts_at instanceof DateTime && $ends_at instanceof DateTime ) : ?>
		<div class="scd-schedule-timeline">
			<div class="scd-timeline-track">
				<div class="scd-timeline-progress" style="width: <?php echo esc_attr( number_format( $progress_percentage, 2 ) ); ?>%;"></div>
				<div class="scd-timeline-marker scd-timeline-marker-start" aria-label="<?php esc_attr_e( 'Campaign start', 'smart-cycle-discounts' ); ?>"></div>
				<?php if ( $is_active ) : ?>
					<div class="scd-timeline-marker scd-timeline-marker-current" style="left: <?php echo esc_attr( number_format( $progress_percentage, 2 ) ); ?>%;" aria-label="<?php esc_attr_e( 'Current time', 'smart-cycle-discounts' ); ?>"></div>
				<?php endif; ?>
				<div class="scd-timeline-marker scd-timeline-marker-end" aria-label="<?php esc_attr_e( 'Campaign end', 'smart-cycle-discounts' ); ?>"></div>
			</div>
			<div class="scd-timeline-labels">
				<span class="scd-timeline-label-start"><?php esc_html_e( 'Start', 'smart-cycle-discounts' ); ?></span>
				<span class="scd-timeline-label-center">
					<?php
					if ( ! empty( $duration_detail ) ) {
						echo esc_html( $duration_detail );
					} elseif ( ! empty( $duration ) ) {
						echo esc_html( $duration );
					}
					?>
				</span>
				<span class="scd-timeline-label-end"><?php esc_html_e( 'End', 'smart-cycle-discounts' ); ?></span>
			</div>
		</div>
	<?php endif; ?>

	<!-- Duration Info -->
	<?php if ( ! empty( $duration ) ) : ?>
		<div class="scd-schedule-duration">
			<?php echo SCD_Icon_Helper::get( 'clock', array( 'size' => 14 ) ); ?>
			<span class="scd-duration-label"><?php esc_html_e( 'Duration:', 'smart-cycle-discounts' ); ?></span>
			<span class="scd-duration-value"><?php echo esc_html( $duration ); ?></span>
			<?php if ( ! empty( $duration_detail ) && $duration !== $duration_detail ) : ?>
				<span class="scd-duration-detail">(<?php echo esc_html( $duration_detail ); ?>)</span>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<!-- Timezone -->
	<?php if ( ! empty( $timezone ) ) : ?>
		<div class="scd-schedule-timezone">
			<?php echo SCD_Icon_Helper::get( 'admin-site', array( 'size' => 14 ) ); ?>
			<span class="scd-timezone-label"><?php esc_html_e( 'Timezone:', 'smart-cycle-discounts' ); ?></span>
			<span class="scd-timezone-value"><?php echo esc_html( $timezone ); ?></span>
		</div>
	<?php endif; ?>
</div>
