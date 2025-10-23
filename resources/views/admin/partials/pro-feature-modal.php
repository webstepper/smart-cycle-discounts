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

<div id="scd-pro-required-modal" class="scd-modal" style="display: none;" role="dialog" aria-labelledby="scd-pro-modal-title" aria-modal="true" aria-hidden="true">
	<div class="scd-modal-overlay" role="presentation"></div>

	<div class="scd-modal-dialog" role="document">
		<div class="scd-modal-content">
			<!-- Modal Header -->
			<div class="scd-modal-header">
				<div class="scd-modal-icon" aria-hidden="true">
					<span class="dashicons dashicons-lock"></span>
				</div>
				<h2 id="scd-pro-modal-title">
					<span id="scd-pro-feature-name"></span>
					<?php esc_html_e( 'Requires PRO', 'smart-cycle-discounts' ); ?>
				</h2>
				<button type="button" class="scd-modal-close" aria-label="<?php esc_attr_e( 'Close dialog', 'smart-cycle-discounts' ); ?>">
					<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
				</button>
			</div>

			<!-- Modal Body -->
			<div class="scd-modal-body">
				<div class="scd-modal-columns">
					<!-- Left Column: PRO Features -->
					<div class="scd-modal-column-left">
						<h3 class="scd-section-title">
							<?php esc_html_e( 'PRO Features', 'smart-cycle-discounts' ); ?>
						</h3>

						<ul class="scd-features-list">
							<li>
								<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
								<?php esc_html_e( 'Tiered Discounts', 'smart-cycle-discounts' ); ?>
							</li>
							<li>
								<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
								<?php esc_html_e( 'BOGO Deals', 'smart-cycle-discounts' ); ?>
							</li>
							<li>
								<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
								<?php esc_html_e( 'Spend Thresholds', 'smart-cycle-discounts' ); ?>
							</li>
							<li>
								<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
								<?php esc_html_e( 'Recurring Campaigns', 'smart-cycle-discounts' ); ?>
							</li>
							<li>
								<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
								<?php esc_html_e( 'Advanced Filters', 'smart-cycle-discounts' ); ?>
							</li>
							<li>
								<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
								<?php esc_html_e( 'Priority Support', 'smart-cycle-discounts' ); ?>
							</li>
							<li>
								<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
								<?php esc_html_e( 'Analytics & Reports', 'smart-cycle-discounts' ); ?>
							</li>
							<li>
								<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
								<?php esc_html_e( 'Role-Based Access', 'smart-cycle-discounts' ); ?>
							</li>
						</ul>
					</div>

					<!-- Right Column: Why Upgrade -->
					<div class="scd-modal-column-right">
						<h3 class="scd-section-title">
							<?php esc_html_e( 'Why Upgrade?', 'smart-cycle-discounts' ); ?>
						</h3>

						<div class="scd-benefits-compact">
							<div class="scd-benefit-compact">
								<span class="dashicons dashicons-chart-line" aria-hidden="true"></span>
								<strong><?php esc_html_e( 'Increase AOV by 40%', 'smart-cycle-discounts' ); ?></strong>
							</div>
							<div class="scd-benefit-compact">
								<span class="dashicons dashicons-groups" aria-hidden="true"></span>
								<strong><?php esc_html_e( 'Boost Retention', 'smart-cycle-discounts' ); ?></strong>
							</div>
							<div class="scd-benefit-compact">
								<span class="dashicons dashicons-clock" aria-hidden="true"></span>
								<strong><?php esc_html_e( 'Save 10+ Hours/Month', 'smart-cycle-discounts' ); ?></strong>
							</div>
							<div class="scd-benefit-compact">
								<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
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
							<button type="button" class="button button-primary button-large scd-modal-upgrade" data-upgrade-url="<?php echo esc_url( $upgrade_url ); ?>">
								<span class="dashicons dashicons-star-filled" aria-hidden="true"></span>
								<?php esc_html_e( 'Upgrade Now', 'smart-cycle-discounts' ); ?>
							</button>
							<div class="scd-trust-badges">
								<div class="scd-trust-badge">
									<span class="dashicons dashicons-shield-alt" aria-hidden="true"></span>
									<span><?php esc_html_e( '14-Day Guarantee', 'smart-cycle-discounts' ); ?></span>
								</div>
								<div class="scd-trust-badge">
									<span class="dashicons dashicons-unlock" aria-hidden="true"></span>
									<span><?php esc_html_e( 'Instant Access', 'smart-cycle-discounts' ); ?></span>
								</div>
								<div class="scd-trust-badge">
									<span class="dashicons dashicons-update" aria-hidden="true"></span>
									<span><?php esc_html_e( 'Free Updates', 'smart-cycle-discounts' ); ?></span>
								</div>
								<div class="scd-trust-badge">
									<span class="dashicons dashicons-admin-network" aria-hidden="true"></span>
									<span><?php esc_html_e( 'Secure Payment', 'smart-cycle-discounts' ); ?></span>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>

			<!-- Modal Footer -->
			<div class="scd-modal-footer">
				<button type="button" class="button button-secondary scd-modal-change">
					<?php esc_html_e( 'Change Discount Type', 'smart-cycle-discounts' ); ?>
				</button>

				<button type="button" class="button button-link scd-modal-save-draft">
					<?php esc_html_e( 'Save as Draft', 'smart-cycle-discounts' ); ?>
				</button>
			</div>
		</div>
	</div>
</div>
