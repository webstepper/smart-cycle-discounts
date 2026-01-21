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

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template partial included into function scope; variables are local, not global.

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

<div class="wsscd-overview-subsection">
	<div class="wsscd-subsection-header">
		<?php WSSCD_Icon_Helper::render( 'calendar', array( 'size' => 16 ) ); ?>
		<h5><?php esc_html_e( 'Schedule', 'smart-cycle-discounts' ); ?></h5>
	</div>

	<!-- Date Range -->
	<div class="wsscd-schedule-dates">
		<?php if ( $starts_at instanceof DateTime ) : ?>
			<div class="wsscd-schedule-date wsscd-schedule-start">
				<div class="wsscd-schedule-date-label">
					<?php WSSCD_Icon_Helper::render( 'play', array( 'size' => 14 ) ); ?>
					<?php esc_html_e( 'Start', 'smart-cycle-discounts' ); ?>
					<?php if ( $is_upcoming ) : ?>
						<span class="wsscd-schedule-date-relative">
							<?php
							$time_until = human_time_diff( current_time( 'timestamp' ), $starts_at->getTimestamp() );
							printf(
								/* translators: %s: human-readable time difference */
								esc_html__( 'in %s', 'smart-cycle-discounts' ),
								esc_html( $time_until )
							);
							?>
						</span>
					<?php endif; ?>
				</div>
				<div class="wsscd-schedule-date-value">
					<?php echo esc_html( $starts_at->format( 'M j, Y g:i A' ) ); ?>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( $ends_at instanceof DateTime ) : ?>
			<div class="wsscd-schedule-date wsscd-schedule-end">
				<div class="wsscd-schedule-date-label">
					<?php WSSCD_Icon_Helper::render( 'pause', array( 'size' => 14 ) ); ?>
					<?php esc_html_e( 'End', 'smart-cycle-discounts' ); ?>
					<?php if ( $is_active ) : ?>
						<span class="wsscd-schedule-date-relative wsscd-time-remaining">
							<?php
							$time_remaining = human_time_diff( current_time( 'timestamp' ), $ends_at->getTimestamp() );
							printf(
								/* translators: %s: human-readable time difference */
								esc_html__( 'in %s', 'smart-cycle-discounts' ),
								esc_html( $time_remaining )
							);
							?>
						</span>
					<?php elseif ( $is_expired ) : ?>
						<span class="wsscd-schedule-date-relative wsscd-time-expired">
							<?php
							$time_since = human_time_diff( $ends_at->getTimestamp(), current_time( 'timestamp' ) );
							printf(
								/* translators: %s: human-readable time difference */
								esc_html__( '%s ago', 'smart-cycle-discounts' ),
								esc_html( $time_since )
							);
							?>
						</span>
					<?php endif; ?>
				</div>
				<div class="wsscd-schedule-date-value">
					<?php echo esc_html( $ends_at->format( 'M j, Y g:i A' ) ); ?>
				</div>
			</div>
		<?php endif; ?>
	</div>

	<!-- Visual Timeline -->
	<?php if ( $starts_at instanceof DateTime && $ends_at instanceof DateTime ) : ?>
		<div class="wsscd-schedule-timeline">
			<div class="wsscd-timeline-track">
				<div class="wsscd-timeline-progress" style="width: <?php echo esc_attr( number_format( $progress_percentage, 2 ) ); ?>%;"></div>
				<div class="wsscd-timeline-marker wsscd-timeline-marker-start" aria-label="<?php esc_attr_e( 'Campaign start', 'smart-cycle-discounts' ); ?>"></div>
				<?php if ( $is_active ) : ?>
					<div class="wsscd-timeline-marker wsscd-timeline-marker-current" style="left: <?php echo esc_attr( number_format( $progress_percentage, 2 ) ); ?>%;" aria-label="<?php esc_attr_e( 'Current time', 'smart-cycle-discounts' ); ?>"></div>
				<?php endif; ?>
				<div class="wsscd-timeline-marker wsscd-timeline-marker-end" aria-label="<?php esc_attr_e( 'Campaign end', 'smart-cycle-discounts' ); ?>"></div>
			</div>
			<div class="wsscd-timeline-labels">
				<span class="wsscd-timeline-label-start"><?php esc_html_e( 'Start', 'smart-cycle-discounts' ); ?></span>
				<span class="wsscd-timeline-label-center">
					<?php
					// Show progress percentage for active campaigns, nothing for others
					// Duration is shown in dedicated Duration Info section below
					if ( $is_active && $progress_percentage > 0 ) {
						printf(
							/* translators: %s: progress percentage */
							esc_html__( '%s%% complete', 'smart-cycle-discounts' ),
							esc_html( number_format( $progress_percentage, 0 ) )
						);
					}
					?>
				</span>
				<span class="wsscd-timeline-label-end"><?php esc_html_e( 'End', 'smart-cycle-discounts' ); ?></span>
			</div>
		</div>
	<?php endif; ?>

	<!-- Duration Info -->
	<?php if ( ! empty( $duration ) ) : ?>
		<div class="wsscd-schedule-duration">
			<?php WSSCD_Icon_Helper::render( 'clock', array( 'size' => 14 ) ); ?>
			<span class="wsscd-duration-label"><?php esc_html_e( 'Duration:', 'smart-cycle-discounts' ); ?></span>
			<span class="wsscd-duration-value">
				<?php
				// Show the most detailed duration available
				if ( ! empty( $duration_detail ) ) {
					echo esc_html( $duration_detail );
				} else {
					echo esc_html( $duration );
				}
				?>
			</span>
		</div>
	<?php endif; ?>

	<!-- Timezone -->
	<?php if ( ! empty( $timezone ) ) : ?>
		<div class="wsscd-schedule-timezone">
			<?php WSSCD_Icon_Helper::render( 'admin-site', array( 'size' => 14 ) ); ?>
			<span class="wsscd-timezone-label"><?php esc_html_e( 'Timezone:', 'smart-cycle-discounts' ); ?></span>
			<span class="wsscd-timezone-value"><?php echo esc_html( $timezone ); ?></span>
		</div>
	<?php endif; ?>
</div>
