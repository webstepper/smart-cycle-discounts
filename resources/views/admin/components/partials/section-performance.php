<?php
/**
 * Campaign Overview Panel - Performance Section
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

// Check if we have any data
$has_data = ! empty( $data['revenue'] ) || ! empty( $data['conversions'] ) || ! empty( $data['impressions'] );

if ( $has_data ) :
	?>
	<!-- Date Range Indicator -->
	<div style="margin-bottom: 16px; padding: 8px 12px; background: #f7f7f7; border-radius: 4px; font-size: 12px; color: #646970;">
		<?php echo SCD_Icon_Helper::get( 'calendar', array( 'size' => 14 ) ); ?>
		<?php esc_html_e( 'Last 30 days', 'smart-cycle-discounts' ); ?>
	</div>

	<!-- Performance metrics grid -->
	<div class="scd-metrics-grid">

		<!-- Revenue -->
		<div class="scd-metric-card">
			<div style="display: flex; align-items: center; justify-content: center; margin-bottom: 8px;">
				<?php echo SCD_Icon_Helper::get( 'money-alt', array( 'size' => 20 ) ); ?>
			</div>
			<span class="scd-metric-value"><?php echo wp_kses_post( wc_price( $data['revenue'] ?? 0 ) ); ?></span>
			<span class="scd-metric-label"><?php esc_html_e( 'Revenue', 'smart-cycle-discounts' ); ?></span>
		</div>

		<!-- Conversions -->
		<div class="scd-metric-card">
			<div style="display: flex; align-items: center; justify-content: center; margin-bottom: 8px;">
				<?php echo SCD_Icon_Helper::get( 'cart', array( 'size' => 20 ) ); ?>
			</div>
			<span class="scd-metric-value"><?php echo absint( $data['conversions'] ?? 0 ); ?></span>
			<span class="scd-metric-label"><?php esc_html_e( 'Orders', 'smart-cycle-discounts' ); ?></span>
		</div>

		<!-- Impressions -->
		<div class="scd-metric-card">
			<div style="display: flex; align-items: center; justify-content: center; margin-bottom: 8px;">
				<?php echo SCD_Icon_Helper::get( 'visibility', array( 'size' => 20 ) ); ?>
			</div>
			<span class="scd-metric-value"><?php echo number_format_i18n( $data['impressions'] ?? 0 ); ?></span>
			<span class="scd-metric-label"><?php esc_html_e( 'Views', 'smart-cycle-discounts' ); ?></span>
		</div>

		<!-- CTR -->
		<div class="scd-metric-card">
			<div style="display: flex; align-items: center; justify-content: center; margin-bottom: 8px;">
				<?php echo SCD_Icon_Helper::get( 'chart-area', array( 'size' => 20 ) ); ?>
			</div>
			<span class="scd-metric-value"><?php echo number_format_i18n( $data['ctr'] ?? 0, 2 ); ?>%</span>
			<span class="scd-metric-label"><?php esc_html_e( 'CTR', 'smart-cycle-discounts' ); ?></span>
		</div>

	</div>

	<?php
	// Average order value (if available)
	if ( ! empty( $data['avg_order'] ) ) :
		?>
		<div style="margin-top: 16px; padding: 12px; background: #f7f7f7; border-radius: 4px; text-align: center;">
			<span style="display: block; font-size: 12px; color: #646970; margin-bottom: 4px;">
				<?php esc_html_e( 'Average Order Value', 'smart-cycle-discounts' ); ?>
			</span>
			<span style="display: block; font-size: 16px; font-weight: 600; color: #1d2327;">
				<?php echo wp_kses_post( wc_price( $data['avg_order'] ) ); ?>
			</span>
		</div>
		<?php
	endif;
else :
	?>
	<p class="scd-no-data">
		<?php echo SCD_Icon_Helper::get( 'chart-line', array( 'size' => 48 ) ); ?>
		<br>
		<?php esc_html_e( 'No performance data available yet.', 'smart-cycle-discounts' ); ?>
	</p>
	<?php
endif;
