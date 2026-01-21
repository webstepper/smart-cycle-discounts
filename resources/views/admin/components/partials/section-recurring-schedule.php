<?php
/**
 * Campaign Overview Panel - Recurring Schedule Section
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

// Show message if recurring is not enabled
if ( empty( $data['enabled'] ) ) :
	?>
	<div class="wsscd-no-data">
		<p><?php esc_html_e( 'This campaign does not use recurring scheduling.', 'smart-cycle-discounts' ); ?></p>
	</div>
	<?php
	return;
endif;

// Determine if this is a parent or child campaign
$is_parent = isset( $data['is_parent'] ) && $data['is_parent'];

if ( $is_parent ) :
	// Parent campaign - show recurring schedule details
	?>

	<!-- Status -->
	<div class="wsscd-field-row">
		<div class="wsscd-field-label"><?php esc_html_e( 'Status', 'smart-cycle-discounts' ); ?></div>
		<div class="wsscd-field-value">
			<?php
			if ( ! empty( $data['is_active'] ) ) {
								echo wp_kses_post( WSSCD_Badge_Helper::status_badge( 'active', __( 'Active', 'smart-cycle-discounts' ) ) );
			} else {
								echo wp_kses_post( WSSCD_Badge_Helper::status_badge( 'paused', __( 'Stopped', 'smart-cycle-discounts' ) ) );
			}
			?>
		</div>
	</div>

	<!-- Pattern -->
	<?php if ( ! empty( $data['pattern_label'] ) ) : ?>
	<div class="wsscd-field-row">
		<div class="wsscd-field-label"><?php esc_html_e( 'Recurrence Pattern', 'smart-cycle-discounts' ); ?></div>
		<div class="wsscd-field-value"><?php echo esc_html( $data['pattern_label'] ); ?></div>
	</div>
	<?php endif; ?>

	<!-- Interval (if applicable) -->
	<?php if ( ! empty( $data['interval'] ) && $data['interval'] > 1 ) : ?>
	<div class="wsscd-field-row">
		<div class="wsscd-field-label"><?php esc_html_e( 'Interval', 'smart-cycle-discounts' ); ?></div>
		<div class="wsscd-field-value">
			<?php
			/* translators: %d: interval number */
			echo esc_html( sprintf( _n( 'Every %d day', 'Every %d days', $data['interval'], 'smart-cycle-discounts' ), $data['interval'] ) );
			?>
		</div>
	</div>
	<?php endif; ?>

	<!-- Next Occurrence -->
	<?php if ( ! empty( $data['next_occurrence_formatted'] ) ) : ?>
	<div class="wsscd-field-row">
		<div class="wsscd-field-label"><?php esc_html_e( 'Next Occurrence', 'smart-cycle-discounts' ); ?></div>
		<div class="wsscd-field-value">
			<strong><?php echo esc_html( $data['next_occurrence_formatted'] ); ?></strong>
			<?php if ( ! empty( $data['next_occurrence_relative'] ) ) : ?>
				<br>
				<span class="description">
					<?php
					/* translators: %s: relative time (e.g., "in 2 days") */
					echo esc_html( sprintf( __( 'In %s', 'smart-cycle-discounts' ), $data['next_occurrence_relative'] ) );
					?>
				</span>
			<?php endif; ?>
		</div>
	</div>
	<?php elseif ( ! empty( $data['is_active'] ) ) : ?>
	<div class="wsscd-field-row">
		<div class="wsscd-field-label"><?php esc_html_e( 'Next Occurrence', 'smart-cycle-discounts' ); ?></div>
		<div class="wsscd-field-value">
			<span style="color: #d63638;"><?php esc_html_e( 'None scheduled', 'smart-cycle-discounts' ); ?></span>
		</div>
	</div>
	<?php endif; ?>

	<!-- Recurrence End Date -->
	<?php if ( ! empty( $data['recurrence_end_formatted'] ) ) : ?>
	<div class="wsscd-field-row">
		<div class="wsscd-field-label"><?php esc_html_e( 'End Date', 'smart-cycle-discounts' ); ?></div>
		<div class="wsscd-field-value"><?php echo esc_html( $data['recurrence_end_formatted'] ); ?></div>
	</div>
	<?php endif; ?>

	<!-- Occurrence Count Limit -->
	<?php if ( ! empty( $data['recurrence_count'] ) ) : ?>
	<div class="wsscd-field-row">
		<div class="wsscd-field-label"><?php esc_html_e( 'Max Occurrences', 'smart-cycle-discounts' ); ?></div>
		<div class="wsscd-field-value">
			<?php
			$current    = isset( $data['occurrence_number'] ) ? intval( $data['occurrence_number'] ) : 0;
			$max        = intval( $data['recurrence_count'] );
			/* translators: 1: current occurrence number, 2: maximum occurrence number */
			echo esc_html( sprintf( __( '%1$d of %2$d', 'smart-cycle-discounts' ), $current, $max ) );
			?>
		</div>
	</div>
	<?php endif; ?>

	<!-- Child Campaigns Count -->
	<?php if ( isset( $data['child_campaigns_count'] ) && $data['child_campaigns_count'] > 0 ) : ?>
	<div class="wsscd-field-row">
		<div class="wsscd-field-label"><?php esc_html_e( 'Child Campaigns', 'smart-cycle-discounts' ); ?></div>
		<div class="wsscd-field-value">
			<?php
			/* translators: %d: number of child campaigns */
			echo esc_html( sprintf( _n( '%d campaign created', '%d campaigns created', $data['child_campaigns_count'], 'smart-cycle-discounts' ), $data['child_campaigns_count'] ) );
			?>
		</div>
	</div>
	<?php endif; ?>

	<!-- Last Error (if any) -->
	<?php if ( ! empty( $data['last_error'] ) ) : ?>
	<div class="wsscd-field-row">
		<div class="wsscd-field-label"><?php esc_html_e( 'Last Error', 'smart-cycle-discounts' ); ?></div>
		<div class="wsscd-field-value">
			<span style="color: #d63638;"><?php echo esc_html( $data['last_error'] ); ?></span>
		</div>
	</div>
	<?php endif; ?>

	<?php
else :
	// Child campaign - show parent relationship
	?>

	<!-- Parent Campaign -->
	<div class="wsscd-field-row">
		<div class="wsscd-field-label"><?php esc_html_e( 'Parent Campaign', 'smart-cycle-discounts' ); ?></div>
		<div class="wsscd-field-value">
			<?php if ( ! empty( $data['parent_campaign_name'] ) ) : ?>
				<strong><?php echo esc_html( $data['parent_campaign_name'] ); ?></strong>
				<?php if ( ! empty( $data['parent_campaign_id'] ) ) : ?>
					<br>
					<span class="description">
						<?php
						/* translators: %d: parent campaign ID */
						echo esc_html( sprintf( __( 'ID: %d', 'smart-cycle-discounts' ), $data['parent_campaign_id'] ) );
						?>
					</span>
				<?php endif; ?>
			<?php elseif ( ! empty( $data['parent_campaign_id'] ) ) : ?>
				<?php
				/* translators: %d: parent campaign ID */
				echo esc_html( sprintf( __( 'Campaign #%d', 'smart-cycle-discounts' ), $data['parent_campaign_id'] ) );
				?>
			<?php endif; ?>
		</div>
	</div>

	<!-- Occurrence Number -->
	<?php if ( ! empty( $data['occurrence_number'] ) ) : ?>
	<div class="wsscd-field-row">
		<div class="wsscd-field-label"><?php esc_html_e( 'Occurrence', 'smart-cycle-discounts' ); ?></div>
		<div class="wsscd-field-value">
			<?php
			/* translators: %d: occurrence number */
			echo esc_html( sprintf( __( '#%d', 'smart-cycle-discounts' ), $data['occurrence_number'] ) );
			?>
		</div>
	</div>
	<?php endif; ?>

	<!-- Type Badge -->
	<div class="wsscd-field-row">
		<div class="wsscd-field-label"><?php esc_html_e( 'Type', 'smart-cycle-discounts' ); ?></div>
		<div class="wsscd-field-value">
			<?php
						echo wp_kses_post( WSSCD_Badge_Helper::info_badge( __( 'Recurring Child', 'smart-cycle-discounts' ) ) );
			?>
		</div>
	</div>

	<?php
endif;
