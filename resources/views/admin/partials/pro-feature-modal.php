<?php
/**
 * PRO Feature Required Modal
 *
 * Modal dialog displayed when free users attempt to use PRO features.
 * Provides clear upgrade path and option to change selection.
 *
 * @package SmartCycleDiscounts
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get upgrade URL from feature gate if available
$upgrade_url = isset( $feature_gate ) && $feature_gate ?
	$feature_gate->get_upgrade_url() :
	admin_url( 'admin.php?page=smart-cycle-discounts-pricing' );
?>

<div id="scd-pro-required-modal" class="scd-modal scd-hidden" role="dialog" aria-labelledby="scd-pro-modal-title" aria-modal="true" aria-hidden="true">
	<div class="scd-modal-overlay" role="presentation"></div>

	<div class="scd-modal-dialog" role="document">
		<div class="scd-modal-content">
			<!-- Modal Header -->
			<div class="scd-modal-header">
				<div class="scd-modal-icon" aria-hidden="true">
					<?php echo SCD_Icon_Helper::get( 'lock', array( 'size' => 16 ) ); ?>
				</div>
				<h2 id="scd-pro-modal-title">
					<span id="scd-pro-feature-name"></span>
					<?php esc_html_e( 'Requires PRO', 'smart-cycle-discounts' ); ?>
				</h2>
				<button type="button" class="scd-modal-close" aria-label="<?php esc_attr_e( 'Close dialog', 'smart-cycle-discounts' ); ?>">
					<?php echo SCD_Icon_Helper::get( 'no-alt', array( 'size' => 16 ) ); ?>
				</button>
			</div>

			<!-- Modal Body -->
			<div class="scd-modal-body">
				<div class="scd-modal-columns">
					<!-- Left Column: PRO Features -->
					<div class="scd-modal-column-left">
						<h3 class="scd-form-section-title">
							<?php esc_html_e( 'PRO Features', 'smart-cycle-discounts' ); ?>
						</h3>

						<ul class="scd-features-list">
							<li>
								<?php echo SCD_Icon_Helper::get( 'check', array( 'size' => 16 ) ); ?>
								<?php esc_html_e( 'Tiered Discounts', 'smart-cycle-discounts' ); ?>
							</li>
							<li>
								<?php echo SCD_Icon_Helper::get( 'check', array( 'size' => 16 ) ); ?>
								<?php esc_html_e( 'BOGO Deals', 'smart-cycle-discounts' ); ?>
							</li>
							<li>
								<?php echo SCD_Icon_Helper::get( 'check', array( 'size' => 16 ) ); ?>
								<?php esc_html_e( 'Spend Thresholds', 'smart-cycle-discounts' ); ?>
							</li>
							<li>
								<?php echo SCD_Icon_Helper::get( 'check', array( 'size' => 16 ) ); ?>
								<?php esc_html_e( 'Recurring Campaigns', 'smart-cycle-discounts' ); ?>
							</li>
							<li>
								<?php echo SCD_Icon_Helper::get( 'check', array( 'size' => 16 ) ); ?>
								<?php esc_html_e( 'Advanced Filters', 'smart-cycle-discounts' ); ?>
							</li>
							<li>
								<?php echo SCD_Icon_Helper::get( 'check', array( 'size' => 16 ) ); ?>
								<?php esc_html_e( 'Priority Support', 'smart-cycle-discounts' ); ?>
							</li>
							<li>
								<?php echo SCD_Icon_Helper::get( 'check', array( 'size' => 16 ) ); ?>
								<?php esc_html_e( 'Analytics & Reports', 'smart-cycle-discounts' ); ?>
							</li>
							<li>
								<?php echo SCD_Icon_Helper::get( 'check', array( 'size' => 16 ) ); ?>
								<?php esc_html_e( 'Role-Based Access', 'smart-cycle-discounts' ); ?>
							</li>
						</ul>
					</div>

					<!-- Right Column: Why Upgrade -->
					<div class="scd-modal-column-right">
						<h3 class="scd-form-section-title">
							<?php esc_html_e( 'Why Upgrade?', 'smart-cycle-discounts' ); ?>
						</h3>

						<div class="scd-benefits-compact">
							<div class="scd-benefit-compact">
								<?php echo SCD_Icon_Helper::get( 'chart-line', array( 'size' => 16 ) ); ?>
								<strong><?php esc_html_e( 'Increase AOV by 40%', 'smart-cycle-discounts' ); ?></strong>
							</div>
							<div class="scd-benefit-compact">
								<?php echo SCD_Icon_Helper::get( 'groups', array( 'size' => 16 ) ); ?>
								<strong><?php esc_html_e( 'Boost Retention', 'smart-cycle-discounts' ); ?></strong>
							</div>
							<div class="scd-benefit-compact">
								<?php echo SCD_Icon_Helper::get( 'clock', array( 'size' => 16 ) ); ?>
								<strong><?php esc_html_e( 'Save 10+ Hours/Month', 'smart-cycle-discounts' ); ?></strong>
							</div>
							<div class="scd-benefit-compact">
								<?php echo SCD_Icon_Helper::get( 'check', array( 'size' => 16 ) ); ?>
								<strong><?php esc_html_e( 'Unlimited Campaigns', 'smart-cycle-discounts' ); ?></strong>
							</div>
						</div>

						<div class="scd-upgrade-box">
							<div class="scd-price-wrapper">
								<div class="scd-price-original">
									<span class="scd-price-was"><?php esc_html_e( 'Was', 'smart-cycle-discounts' ); ?></span>
									<span class="scd-price-strike"><?php esc_html_e( '$107.88', 'smart-cycle-discounts' ); ?></span>
									<span class="scd-savings-badge"><?php esc_html_e( 'Save 35%', 'smart-cycle-discounts' ); ?></span>
								</div>
								<div class="scd-price-tag">
									<span class="scd-price-label"><?php esc_html_e( 'Now only', 'smart-cycle-discounts' ); ?></span>
									<span class="scd-price-amount"><?php esc_html_e( '$69.99', 'smart-cycle-discounts' ); ?></span>
									<span class="scd-price-period"><?php esc_html_e( '/year', 'smart-cycle-discounts' ); ?></span>
								</div>
							</div>
							<?php
							SCD_Button_Helper::primary(
								__( 'Upgrade Now', 'smart-cycle-discounts' ),
								array(
									'size'       => 'large',
									'icon'       => 'star-filled',
									'classes'    => array( 'scd-modal-upgrade' ),
									'attributes' => array( 'data-upgrade-url' => esc_url( $upgrade_url ) ),
								)
							);
							?>
							<div class="scd-trust-badges">
								<div class="scd-trust-badge">
									<?php echo SCD_Icon_Helper::get( 'shield-alt', array( 'size' => 16 ) ); ?>
									<span><?php esc_html_e( '14-Day Guarantee', 'smart-cycle-discounts' ); ?></span>
								</div>
								<div class="scd-trust-badge">
									<?php echo SCD_Icon_Helper::get( 'unlock', array( 'size' => 16 ) ); ?>
									<span><?php esc_html_e( 'Instant Access', 'smart-cycle-discounts' ); ?></span>
								</div>
								<div class="scd-trust-badge">
									<?php echo SCD_Icon_Helper::get( 'update', array( 'size' => 16 ) ); ?>
									<span><?php esc_html_e( 'Free Updates', 'smart-cycle-discounts' ); ?></span>
								</div>
								<div class="scd-trust-badge">
									<?php echo SCD_Icon_Helper::get( 'admin-network', array( 'size' => 16 ) ); ?>
									<span><?php esc_html_e( 'Secure Payment', 'smart-cycle-discounts' ); ?></span>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>

			<!-- Modal Footer -->
			<div class="scd-modal-footer">
			<?php
			SCD_Button_Helper::secondary(
				__( 'Change Discount Type', 'smart-cycle-discounts' ),
				array(
					'classes' => array( 'scd-modal-change' ),
				)
			);

			SCD_Button_Helper::link(
				__( 'Save as Draft', 'smart-cycle-discounts' ),
				'#',
				array(
					'classes' => array( 'scd-modal-save-draft' ),
				)
			);
			?>
			</div>
		</div>
	</div>
</div>
