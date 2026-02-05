<?php
/**
 * Campaign Overview Panel - Performance Section
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/views/admin/components/partials
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress/plugins/smart-cycle-discounts/
 * @since      1.0.0
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template partial included into function scope; variables are local, not global.

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// Check if we have any data
$has_data = ! empty( $data['revenue'] ) || ! empty( $data['conversions'] ) || ! empty( $data['impressions'] );

if ( $has_data ) :
	?>
	<!-- Date Range Indicator -->
	<div class="wsscd-performance-date-range">
		<?php WSSCD_Icon_Helper::render( 'calendar', array( 'size' => 14 ) ); ?>
		<?php esc_html_e( 'Last 30 days', 'smart-cycle-discounts' ); ?>
	</div>

	<!-- Performance metrics grid -->
	<div class="wsscd-metrics-grid">

		<!-- Revenue -->
		<div class="wsscd-metric-card">
			<div class="wsscd-metric-icon">
				<?php WSSCD_Icon_Helper::render( 'money-alt', array( 'size' => 20 ) ); ?>
			</div>
			<span class="wsscd-metric-value"><?php echo wp_kses_post( wc_price( $data['revenue'] ?? 0 ) ); ?></span>
			<span class="wsscd-metric-label"><?php esc_html_e( 'Revenue', 'smart-cycle-discounts' ); ?></span>
		</div>

		<!-- Conversions -->
		<div class="wsscd-metric-card">
			<div class="wsscd-metric-icon">
				<?php WSSCD_Icon_Helper::render( 'cart', array( 'size' => 20 ) ); ?>
			</div>
			<span class="wsscd-metric-value"><?php echo absint( $data['conversions'] ?? 0 ); ?></span>
			<span class="wsscd-metric-label"><?php esc_html_e( 'Orders', 'smart-cycle-discounts' ); ?></span>
		</div>

		<!-- Impressions -->
		<div class="wsscd-metric-card">
			<div class="wsscd-metric-icon">
				<?php WSSCD_Icon_Helper::render( 'visibility', array( 'size' => 20 ) ); ?>
			</div>
			<span class="wsscd-metric-value"><?php echo esc_html( number_format_i18n( $data['impressions'] ?? 0 ) ); ?></span>
			<span class="wsscd-metric-label"><?php esc_html_e( 'Views', 'smart-cycle-discounts' ); ?></span>
		</div>

		<!-- CTR -->
		<div class="wsscd-metric-card">
			<div class="wsscd-metric-icon">
				<?php WSSCD_Icon_Helper::render( 'chart-area', array( 'size' => 20 ) ); ?>
			</div>
			<span class="wsscd-metric-value"><?php echo esc_html( number_format_i18n( $data['ctr'] ?? 0, 2 ) ); ?>%</span>
			<span class="wsscd-metric-label"><?php esc_html_e( 'CTR', 'smart-cycle-discounts' ); ?></span>
		</div>

	</div>

	<?php
	// Average order value (if available)
	if ( ! empty( $data['avg_order'] ) ) :
		?>
		<div class="wsscd-performance-avg-order">
			<span class="wsscd-avg-order-label">
				<?php esc_html_e( 'Average Order Value', 'smart-cycle-discounts' ); ?>
			</span>
			<span class="wsscd-avg-order-value">
				<?php echo wp_kses_post( wc_price( $data['avg_order'] ) ); ?>
			</span>
		</div>
		<?php
	endif;
else :
	?>
	<div class="wsscd-no-data wsscd-no-data-enhanced">
		<div class="wsscd-no-data-icon">
			<?php WSSCD_Icon_Helper::render( 'chart-line', array( 'size' => 48 ) ); ?>
		</div>
		<h4 class="wsscd-no-data-title"><?php esc_html_e( 'No Performance Data Yet', 'smart-cycle-discounts' ); ?></h4>
		<p class="wsscd-no-data-message">
			<?php esc_html_e( 'Performance metrics will be available once the campaign starts running and generating activity.', 'smart-cycle-discounts' ); ?>
		</p>
		<div class="wsscd-no-data-info">
			<?php WSSCD_Icon_Helper::render( 'info', array( 'size' => 16 ) ); ?>
			<span><?php esc_html_e( 'Check back after the campaign has been active for a while', 'smart-cycle-discounts' ); ?></span>
		</div>
	</div>
	<?php
endif;
