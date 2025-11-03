<?php
/**
 * Currency Review Page Template
 *
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/views/admin/pages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

?>

<div class="wrap scd-currency-review-page">
	<h1><?php esc_html_e( 'Currency Review', 'smart-cycle-discounts' ); ?></h1>

	<div class="scd-currency-review-intro">
		<div class="notice notice-warning inline">
			<p>
				<strong><?php esc_html_e( 'Store Currency Changed', 'smart-cycle-discounts' ); ?></strong>
			</p>
			<p>
				<?php esc_html_e( 'The following campaigns have been paused because they contain fixed discount amounts that may need adjustment for the new currency.', 'smart-cycle-discounts' ); ?>
			</p>
			<p>
				<?php esc_html_e( 'Review each campaign and choose an action:', 'smart-cycle-discounts' ); ?>
			</p>
			<ul style="list-style: disc; margin-left: 2em;">
				<li><?php esc_html_e( 'Approve & Resume - Resume the campaign with current settings', 'smart-cycle-discounts' ); ?></li>
				<li><?php esc_html_e( 'Edit - Modify discount amounts for the new currency', 'smart-cycle-discounts' ); ?></li>
				<li><?php esc_html_e( 'Archive - Disable the campaign permanently', 'smart-cycle-discounts' ); ?></li>
			</ul>
		</div>
	</div>

	<div class="scd-currency-review-list">
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Campaign', 'smart-cycle-discounts' ); ?></th>
					<th><?php esc_html_e( 'Discount Type', 'smart-cycle-discounts' ); ?></th>
					<th><?php esc_html_e( 'Original Value', 'smart-cycle-discounts' ); ?></th>
					<th><?php esc_html_e( 'Currency Change', 'smart-cycle-discounts' ); ?></th>
					<th><?php esc_html_e( 'Status', 'smart-cycle-discounts' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'smart-cycle-discounts' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $campaigns as $campaign ) : ?>
					<?php
					$old_currency = $campaign->get_meta( 'old_currency' );
					$new_currency = $campaign->get_meta( 'new_currency' );
					$original_value = $campaign->get_meta( 'original_discount_value' );
					$discount_type = $campaign->get_discount_type();
					$change_date = $campaign->get_meta( 'currency_change_date' );
					?>
					<tr data-campaign-id="<?php echo esc_attr( $campaign->get_id() ); ?>">
						<td>
							<strong><?php echo esc_html( $campaign->get_name() ); ?></strong>
							<div class="row-actions">
								<span class="view">
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=scd-campaigns&action=view&id=' . $campaign->get_id() ) ); ?>">
										<?php esc_html_e( 'View', 'smart-cycle-discounts' ); ?>
									</a>
								</span>
								<span class="edit">
									| <a href="<?php echo esc_url( admin_url( 'admin.php?page=scd-campaigns&action=edit&id=' . $campaign->get_id() ) ); ?>">
										<?php esc_html_e( 'Edit', 'smart-cycle-discounts' ); ?>
									</a>
								</span>
							</div>
						</td>
						<td>
							<?php
							$type_labels = array(
								'fixed' => __( 'Fixed Discount', 'smart-cycle-discounts' ),
								'percentage' => __( 'Percentage', 'smart-cycle-discounts' ),
								'tiered' => __( 'Tiered', 'smart-cycle-discounts' ),
								'spend_threshold' => __( 'Spend Threshold', 'smart-cycle-discounts' ),
								'bulk' => __( 'Bulk Discount', 'smart-cycle-discounts' ),
								'bogo' => __( 'BOGO', 'smart-cycle-discounts' ),
							);
							echo esc_html( $type_labels[ $discount_type ] ?? ucfirst( $discount_type ) );
							?>
						</td>
						<td>
							<?php
							if ( 'fixed' === $discount_type || 'spend_threshold' === $discount_type ) {
								echo esc_html( get_woocommerce_currency_symbol( $old_currency ) . number_format( $original_value, 2 ) );
							} elseif ( 'percentage' === $discount_type ) {
								echo esc_html( $original_value . '%' );
							} else {
								echo esc_html( __( 'Variable', 'smart-cycle-discounts' ) );
							}
							?>
						</td>
						<td>
							<span class="scd-currency-change-badge">
								<?php echo esc_html( sprintf( '%s → %s', $old_currency, $new_currency ) ); ?>
							</span>
							<br>
							<small>
								<?php
								echo esc_html( sprintf(
									__( 'Changed on %s', 'smart-cycle-discounts' ),
									wp_date( get_option( 'date_format' ), strtotime( $change_date ) )
								) );
								?>
							</small>
						</td>
						<td>
							<span class="scd-status-badge scd-status-paused">
								<?php esc_html_e( 'Paused for Review', 'smart-cycle-discounts' ); ?>
							</span>
						</td>
						<td class="scd-review-actions">
							<button type="button"
								class="button button-primary scd-review-approve"
								data-campaign-id="<?php echo esc_attr( $campaign->get_id() ); ?>"
								data-action="approve_and_resume">
								<?php esc_html_e( 'Approve & Resume', 'smart-cycle-discounts' ); ?>
							</button>
							<button type="button"
								class="button scd-review-archive"
								data-campaign-id="<?php echo esc_attr( $campaign->get_id() ); ?>"
								data-action="archive">
								<?php esc_html_e( 'Archive', 'smart-cycle-discounts' ); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>

<style>
.scd-currency-review-page {
	max-width: 1200px;
}

.scd-currency-review-intro {
	margin: 20px 0;
}

.scd-currency-review-list {
	margin-top: 30px;
}

.scd-currency-change-badge {
	display: inline-block;
	padding: 3px 8px;
	background: #fff3cd;
	border: 1px solid #ffc107;
	border-radius: 3px;
	font-weight: 600;
	font-size: 12px;
}

.scd-status-badge {
	display: inline-block;
	padding: 3px 8px;
	border-radius: 3px;
	font-size: 12px;
	font-weight: 600;
}

.scd-status-paused {
	background: #ff9800;
	color: #fff;
}

.scd-review-actions {
	white-space: nowrap;
}

.scd-review-actions .button {
	margin-right: 5px;
	margin-bottom: 5px;
}

.scd-review-approve:disabled,
.scd-review-archive:disabled {
	opacity: 0.5;
	cursor: not-allowed;
}
</style>

<script>
jQuery(document).ready(function($) {
	'use strict';

	// Handle review actions
	$('.scd-review-approve, .scd-review-archive').on('click', function() {
		var $button = $(this);
		var campaignId = $button.data('campaign-id');
		var action = $button.data('action');
		var $row = $button.closest('tr');

		// Confirm archive
		if ( 'archive' === action ) {
			if ( ! confirm( <?php echo wp_json_encode( __( 'Are you sure you want to archive this campaign? This cannot be undone.', 'smart-cycle-discounts' ) ); ?> ) ) {
				return;
			}
		}

		// Disable button
		$button.prop('disabled', true).text( <?php echo wp_json_encode( __( 'Processing...', 'smart-cycle-discounts' ) ); ?> );

		// Send AJAX request
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'scd_currency_review_action',
				review_action: action,
				campaign_id: campaignId,
				_wpnonce: <?php echo wp_json_encode( wp_create_nonce( 'scd_currency_review' ) ); ?>
			},
			success: function(response) {
				if ( response.success ) {
					// Fade out row
					$row.fadeOut(400, function() {
						$(this).remove();

						// Check if table is empty
						if ( 0 === $('table tbody tr').length ) {
							location.reload();
						}
					});
				} else {
					alert( response.data.message || <?php echo wp_json_encode( __( 'Failed to update campaign', 'smart-cycle-discounts' ) ); ?> );
					$button.prop('disabled', false).text( $button.data('original-text') );
				}
			},
			error: function() {
				alert( <?php echo wp_json_encode( __( 'An error occurred. Please try again.', 'smart-cycle-discounts' ) ); ?> );
				$button.prop('disabled', false).text( $button.data('original-text') );
			}
		});
	});

	// Store original button text
	$('.scd-review-approve, .scd-review-archive').each(function() {
		$(this).data('original-text', $(this).text());
	});
});
</script>
