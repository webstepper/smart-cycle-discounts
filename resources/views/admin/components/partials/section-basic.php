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
$name        = isset( $data['name'] ) ? $data['name'] : '';
$description = isset( $data['description'] ) ? $data['description'] : '';
$status      = isset( $data['status'] ) ? $data['status'] : 'draft';
$priority    = isset( $data['priority'] ) ? absint( $data['priority'] ) : 3;
$created_by  = isset( $data['created_by'] ) ? absint( $data['created_by'] ) : 0;
$created_at  = isset( $data['created_at'] ) ? $data['created_at'] : null;
$updated_at  = isset( $data['updated_at'] ) ? $data['updated_at'] : null;

// Get user display name
$user_name = __( 'Unknown', 'smart-cycle-discounts' );
if ( $created_by > 0 ) {
	$user = get_userdata( $created_by );
	if ( $user ) {
		$user_name = $user->display_name;
	}
}

// Format timestamps
$created_time = '';
$updated_time = '';
if ( $created_at instanceof DateTime ) {
	$created_time = sprintf(
		/* translators: %s: human-readable time difference */
		__( '%s ago', 'smart-cycle-discounts' ),
		human_time_diff( $created_at->getTimestamp(), current_time( 'timestamp' ) )
	);
}
if ( $updated_at instanceof DateTime ) {
	$updated_time = sprintf(
		/* translators: %s: human-readable time difference */
		__( '%s ago', 'smart-cycle-discounts' ),
		human_time_diff( $updated_at->getTimestamp(), current_time( 'timestamp' ) )
	);
}
?>

<div class="scd-campaign-summary-card">
	<!-- Header: Name + Status Badge -->
	<div class="scd-campaign-summary-header">
		<h3 class="scd-campaign-summary-name"><?php echo esc_html( $name ); ?></h3>
		<div class="scd-campaign-summary-status">
			<?php echo SCD_Badge_Helper::status_badge( $status ); ?>
		</div>
	</div>

	<!-- Description (if provided) -->
	<?php if ( ! empty( $description ) ) : ?>
		<div class="scd-campaign-summary-description">
			<?php echo esc_html( $description ); ?>
		</div>
	<?php endif; ?>

	<!-- Metadata Row: Priority | Created By | Last Updated -->
	<div class="scd-campaign-summary-meta-row">
		<!-- Priority -->
		<div class="scd-meta-item scd-meta-priority">
			<div class="scd-meta-label">
				<?php echo SCD_Icon_Helper::get( 'star-filled', array( 'size' => 14 ) ); ?>
				<?php esc_html_e( 'Priority', 'smart-cycle-discounts' ); ?>
			</div>
			<div class="scd-meta-value">
				<span class="scd-priority-stars" aria-label="<?php echo esc_attr( sprintf( __( 'Priority: %d out of 5', 'smart-cycle-discounts' ), $priority ) ); ?>">
					<?php
					for ( $i = 1; $i <= 5; $i++ ) {
						echo SCD_Icon_Helper::get( 'star-filled', array( 'size' => 16, 'class' => $i <= $priority ? 'scd-star-active' : 'scd-star-inactive' ) );
					}
					?>
				</span>
				<span class="scd-priority-number">(<?php echo absint( $priority ); ?>/5)</span>
			</div>
		</div>

		<!-- Created By -->
		<div class="scd-meta-item scd-meta-user">
			<div class="scd-meta-label">
				<?php echo SCD_Icon_Helper::get( 'admin-users', array( 'size' => 14 ) ); ?>
				<?php esc_html_e( 'Created by', 'smart-cycle-discounts' ); ?>
			</div>
			<div class="scd-meta-value">
				<?php echo esc_html( $user_name ); ?>
				<?php if ( ! empty( $created_time ) ) : ?>
					<span class="scd-meta-timestamp"><?php echo esc_html( $created_time ); ?></span>
				<?php endif; ?>
			</div>
		</div>

		<!-- Last Updated -->
		<?php if ( ! empty( $updated_time ) ) : ?>
			<div class="scd-meta-item scd-meta-updated">
				<div class="scd-meta-label">
					<?php echo SCD_Icon_Helper::get( 'update', array( 'size' => 14 ) ); ?>
					<?php esc_html_e( 'Last updated', 'smart-cycle-discounts' ); ?>
				</div>
				<div class="scd-meta-value">
					<?php echo esc_html( $updated_time ); ?>
				</div>
			</div>
		<?php endif; ?>
	</div>
</div>
