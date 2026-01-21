<?php
/**
 * Campaign Overview Panel - Campaign Summary Card
 *
 * Displays campaign identity, status, and metadata in a clean hero section.
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

// Extract data
$wsscd_name        = isset( $data['name'] ) ? $data['name'] : '';
$wsscd_description = isset( $data['description'] ) ? $data['description'] : '';
$wsscd_status      = isset( $data['status'] ) ? $data['status'] : 'draft';
$wsscd_priority    = isset( $data['priority'] ) ? absint( $data['priority'] ) : 3;
$wsscd_created_by  = isset( $data['created_by'] ) ? absint( $data['created_by'] ) : 0;
$wsscd_created_at  = isset( $data['created_at'] ) ? $data['created_at'] : null;
$wsscd_updated_at  = isset( $data['updated_at'] ) ? $data['updated_at'] : null;

// Get user display name
$wsscd_user_name = __( 'Unknown', 'smart-cycle-discounts' );
if ( $wsscd_created_by > 0 ) {
	$wsscd_user = get_userdata( $wsscd_created_by );
	if ( $wsscd_user ) {
		$wsscd_user_name = $wsscd_user->display_name;
	}
}

// Format timestamps
$wsscd_created_time = '';
$wsscd_updated_time = '';
if ( $wsscd_created_at instanceof DateTime ) {
	$wsscd_created_time = sprintf(
		/* translators: %s: human-readable time difference */
		__( '%s ago', 'smart-cycle-discounts' ),
		human_time_diff( $wsscd_created_at->getTimestamp(), current_time( 'timestamp' ) )
	);
}
if ( $wsscd_updated_at instanceof DateTime ) {
	$wsscd_updated_time = sprintf(
		/* translators: %s: human-readable time difference */
		__( '%s ago', 'smart-cycle-discounts' ),
		human_time_diff( $wsscd_updated_at->getTimestamp(), current_time( 'timestamp' ) )
	);
}
?>

<div class="wsscd-campaign-summary-card">
	<!-- Header: Name + Status Badge -->
	<div class="wsscd-campaign-summary-header">
		<h3 class="wsscd-campaign-summary-name"><?php echo esc_html( $wsscd_name ); ?></h3>
		<div class="wsscd-campaign-summary-status">
			<?php echo wp_kses_post( WSSCD_Badge_Helper::status_badge( $wsscd_status ) ); ?>
		</div>
	</div>

	<!-- Description (if provided) -->
	<?php if ( ! empty( $wsscd_description ) ) : ?>
		<div class="wsscd-campaign-summary-description">
			<?php echo esc_html( $wsscd_description ); ?>
		</div>
	<?php endif; ?>

	<!-- Metadata Row: Priority | Created By | Last Updated -->
	<div class="wsscd-campaign-summary-meta-row">
		<!-- Priority -->
		<div class="wsscd-meta-item wsscd-meta-priority">
			<div class="wsscd-meta-label">
				<?php WSSCD_Icon_Helper::render( 'star-filled', array( 'size' => 14 ) ); ?>
				<?php esc_html_e( 'Priority', 'smart-cycle-discounts' ); ?>
			</div>
			<div class="wsscd-meta-value">
				<?php /* translators: %d: priority level (1-5) */ ?>
				<span class="wsscd-priority-stars" aria-label="<?php echo esc_attr( sprintf( __( 'Priority: %d out of 5', 'smart-cycle-discounts' ), $wsscd_priority ) ); ?>">
					<?php
					for ( $wsscd_i = 1; $wsscd_i <= 5; $wsscd_i++ ) {
						WSSCD_Icon_Helper::render( 'star-filled', array( 'size' => 16, 'class' => $wsscd_i <= $wsscd_priority ? 'wsscd-star-active' : 'wsscd-star-inactive' ) );
					}
					?>
				</span>
				<span class="wsscd-priority-number">(<?php echo absint( $wsscd_priority ); ?>/5)</span>
			</div>
		</div>

		<!-- Created By -->
		<div class="wsscd-meta-item wsscd-meta-user">
			<div class="wsscd-meta-label">
				<?php WSSCD_Icon_Helper::render( 'admin-users', array( 'size' => 14 ) ); ?>
				<?php esc_html_e( 'Created by', 'smart-cycle-discounts' ); ?>
			</div>
			<div class="wsscd-meta-value">
				<?php echo esc_html( $wsscd_user_name ); ?>
				<?php if ( ! empty( $wsscd_created_time ) ) : ?>
					<span class="wsscd-meta-timestamp"><?php echo esc_html( $wsscd_created_time ); ?></span>
				<?php endif; ?>
			</div>
		</div>

		<!-- Last Updated -->
		<?php if ( ! empty( $wsscd_updated_time ) ) : ?>
			<div class="wsscd-meta-item wsscd-meta-updated">
				<div class="wsscd-meta-label">
					<?php WSSCD_Icon_Helper::render( 'update', array( 'size' => 14 ) ); ?>
					<?php esc_html_e( 'Last updated', 'smart-cycle-discounts' ); ?>
				</div>
				<div class="wsscd-meta-value">
					<?php echo esc_html( $wsscd_updated_time ); ?>
				</div>
			</div>
		<?php endif; ?>
	</div>
</div>
