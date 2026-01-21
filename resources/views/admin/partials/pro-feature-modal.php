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

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template partial included into function scope; variables are local, not global.

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get upgrade URL from feature gate if available
$upgrade_url = isset( $feature_gate ) && $feature_gate ?
	$feature_gate->get_upgrade_url() :
	admin_url( 'admin.php?page=smart-cycle-discounts-pricing' );
?>

<div id="wsscd-pro-required-modal" class="wsscd-modal wsscd-hidden" role="dialog" aria-labelledby="wsscd-pro-modal-title" aria-modal="true" aria-hidden="true">
	<div class="wsscd-modal-overlay" role="presentation"></div>

	<div class="wsscd-modal-dialog" role="document">
		<div class="wsscd-modal-content">
			<!-- Modal Header -->
			<div class="wsscd-modal-header">
				<div class="wsscd-modal-icon" aria-hidden="true">
					<?php WSSCD_Icon_Helper::render( 'lock', array( 'size' => 16 ) ); ?>
				</div>
				<h2 id="wsscd-pro-modal-title">
					<span id="wsscd-pro-feature-name"></span>
					<?php esc_html_e( 'Requires PRO', 'smart-cycle-discounts' ); ?>
				</h2>
				<button type="button" class="wsscd-modal-close" aria-label="<?php esc_attr_e( 'Close dialog', 'smart-cycle-discounts' ); ?>">
					<?php WSSCD_Icon_Helper::render( 'no-alt', array( 'size' => 16 ) ); ?>
				</button>
			</div>

			<!-- Modal Body -->
			<div class="wsscd-modal-body">
				<div class="wsscd-modal-columns">
					<!-- Left Column: PRO Features -->
					<div class="wsscd-modal-column-left">
						<h3 class="wsscd-form-section-title">
							<?php esc_html_e( 'PRO Features', 'smart-cycle-discounts' ); ?>
						</h3>

						<ul class="wsscd-features-list">
							<li>
								<?php WSSCD_Icon_Helper::render( 'check', array( 'size' => 16 ) ); ?>
								<?php esc_html_e( 'Tiered Discounts', 'smart-cycle-discounts' ); ?>
							</li>
							<li>
								<?php WSSCD_Icon_Helper::render( 'check', array( 'size' => 16 ) ); ?>
								<?php esc_html_e( 'BOGO Deals', 'smart-cycle-discounts' ); ?>
							</li>
							<li>
								<?php WSSCD_Icon_Helper::render( 'check', array( 'size' => 16 ) ); ?>
								<?php esc_html_e( 'Spend Thresholds', 'smart-cycle-discounts' ); ?>
							</li>
							<li>
								<?php WSSCD_Icon_Helper::render( 'check', array( 'size' => 16 ) ); ?>
								<?php esc_html_e( 'Recurring Campaigns', 'smart-cycle-discounts' ); ?>
							</li>
							<li>
								<?php WSSCD_Icon_Helper::render( 'check', array( 'size' => 16 ) ); ?>
								<?php esc_html_e( 'Advanced Filters', 'smart-cycle-discounts' ); ?>
							</li>
							<li>
								<?php WSSCD_Icon_Helper::render( 'check', array( 'size' => 16 ) ); ?>
								<?php esc_html_e( 'Priority Support', 'smart-cycle-discounts' ); ?>
							</li>
							<li>
								<?php WSSCD_Icon_Helper::render( 'check', array( 'size' => 16 ) ); ?>
								<?php esc_html_e( 'Analytics & Reports', 'smart-cycle-discounts' ); ?>
							</li>
							<li>
								<?php WSSCD_Icon_Helper::render( 'check', array( 'size' => 16 ) ); ?>
								<?php esc_html_e( 'Role-Based Access', 'smart-cycle-discounts' ); ?>
							</li>
						</ul>
					</div>

					<!-- Right Column: Why Upgrade -->
					<div class="wsscd-modal-column-right">
						<h3 class="wsscd-form-section-title">
							<?php esc_html_e( 'Why Upgrade?', 'smart-cycle-discounts' ); ?>
						</h3>

						<div class="wsscd-benefits-compact">
							<div class="wsscd-benefit-compact">
								<?php WSSCD_Icon_Helper::render( 'chart-line', array( 'size' => 16 ) ); ?>
								<strong><?php esc_html_e( 'Increase AOV by 40%', 'smart-cycle-discounts' ); ?></strong>
							</div>
							<div class="wsscd-benefit-compact">
								<?php WSSCD_Icon_Helper::render( 'groups', array( 'size' => 16 ) ); ?>
								<strong><?php esc_html_e( 'Boost Retention', 'smart-cycle-discounts' ); ?></strong>
							</div>
							<div class="wsscd-benefit-compact">
								<?php WSSCD_Icon_Helper::render( 'clock', array( 'size' => 16 ) ); ?>
								<strong><?php esc_html_e( 'Save 10+ Hours/Month', 'smart-cycle-discounts' ); ?></strong>
							</div>
							<div class="wsscd-benefit-compact">
								<?php WSSCD_Icon_Helper::render( 'controls-repeat', array( 'size' => 16 ) ); ?>
								<strong><?php esc_html_e( 'Recurring Schedules', 'smart-cycle-discounts' ); ?></strong>
							</div>
						</div>

						<div class="wsscd-upgrade-box">
							<div class="wsscd-price-wrapper">
								<div class="wsscd-price-original">
									<span class="wsscd-price-was"><?php esc_html_e( 'Was', 'smart-cycle-discounts' ); ?></span>
									<span class="wsscd-price-strike"><?php esc_html_e( '$107.88', 'smart-cycle-discounts' ); ?></span>
									<span class="wsscd-savings-badge"><?php esc_html_e( 'Save 35%', 'smart-cycle-discounts' ); ?></span>
								</div>
								<div class="wsscd-price-tag">
									<span class="wsscd-price-label"><?php esc_html_e( 'Now only', 'smart-cycle-discounts' ); ?></span>
									<span class="wsscd-price-amount"><?php esc_html_e( '$69.99', 'smart-cycle-discounts' ); ?></span>
									<span class="wsscd-price-period"><?php esc_html_e( '/year', 'smart-cycle-discounts' ); ?></span>
								</div>
							</div>
							<?php
							WSSCD_Button_Helper::primary(
								__( 'Upgrade Now', 'smart-cycle-discounts' ),
								array(
									'size'       => 'large',
									'icon'       => 'star-filled',
									'classes'    => array( 'wsscd-modal-upgrade' ),
									'attributes' => array( 'data-upgrade-url' => esc_url( $upgrade_url ) ),
								)
							);
							?>
							<div class="wsscd-trust-badges">
								<div class="wsscd-trust-badge">
									<?php WSSCD_Icon_Helper::render( 'shield-alt', array( 'size' => 16 ) ); ?>
									<span><?php esc_html_e( '14-Day Guarantee', 'smart-cycle-discounts' ); ?></span>
								</div>
								<div class="wsscd-trust-badge">
									<?php WSSCD_Icon_Helper::render( 'unlock', array( 'size' => 16 ) ); ?>
									<span><?php esc_html_e( 'Instant Access', 'smart-cycle-discounts' ); ?></span>
								</div>
								<div class="wsscd-trust-badge">
									<?php WSSCD_Icon_Helper::render( 'update', array( 'size' => 16 ) ); ?>
									<span><?php esc_html_e( 'Free Updates', 'smart-cycle-discounts' ); ?></span>
								</div>
								<div class="wsscd-trust-badge">
									<?php WSSCD_Icon_Helper::render( 'admin-network', array( 'size' => 16 ) ); ?>
									<span><?php esc_html_e( 'Secure Payment', 'smart-cycle-discounts' ); ?></span>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>

			<!-- Modal Footer -->
			<div class="wsscd-modal-footer">
			<?php
			WSSCD_Button_Helper::secondary(
				__( 'Change Discount Type', 'smart-cycle-discounts' ),
				array(
					'classes' => array( 'wsscd-modal-change' ),
				)
			);

			WSSCD_Button_Helper::link(
				__( 'Save as Draft', 'smart-cycle-discounts' ),
				'#',
				array(
					'classes' => array( 'wsscd-modal-save-draft' ),
				)
			);
			?>
			</div>
		</div>
	</div>
</div>
