<?php
/**
 * Campaign Overview Panel - Metrics Row
 *
 * Displays key performance indicators at a glance.
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

// Extract performance data
$revenue     = isset( $data['revenue'] ) ? floatval( $data['revenue'] ) : 0;
$conversions = isset( $data['conversions'] ) ? absint( $data['conversions'] ) : 0;
$impressions = isset( $data['impressions'] ) ? absint( $data['impressions'] ) : 0;
$ctr         = isset( $data['ctr'] ) ? floatval( $data['ctr'] ) : 0;

// Format values
$formatted_revenue     = wc_price( $revenue );
$formatted_conversions = number_format_i18n( $conversions );
$formatted_impressions = number_format_i18n( $impressions );
$formatted_ctr         = number_format_i18n( $ctr, 2 ) . '%';
?>

<div class="wsscd-overview-subsection">
	<div class="wsscd-subsection-header">
		<?php WSSCD_Icon_Helper::render( 'chart-bar', array( 'size' => 16 ) ); ?>
		<h5><?php esc_html_e( 'Performance Overview', 'smart-cycle-discounts' ); ?></h5>
		<span class="wsscd-subsection-period"><?php esc_html_e( '(Last 30 Days)', 'smart-cycle-discounts' ); ?></span>
	</div>

	<div class="wsscd-metrics-row">
		<!-- Revenue -->
		<div class="wsscd-metric-card">
			<div class="wsscd-metric-icon">
				<?php WSSCD_Icon_Helper::render( 'money-alt', array( 'size' => 20 ) ); ?>
			</div>
			<div class="wsscd-metric-content">
				<div class="wsscd-metric-value"><?php echo wp_kses_post( $formatted_revenue ); ?></div>
				<div class="wsscd-metric-label"><?php esc_html_e( 'Revenue', 'smart-cycle-discounts' ); ?></div>
			</div>
		</div>

		<!-- Conversions -->
		<div class="wsscd-metric-card">
			<div class="wsscd-metric-icon">
				<?php WSSCD_Icon_Helper::render( 'yes', array( 'size' => 20 ) ); ?>
			</div>
			<div class="wsscd-metric-content">
				<div class="wsscd-metric-value"><?php echo esc_html( $formatted_conversions ); ?></div>
				<div class="wsscd-metric-label"><?php esc_html_e( 'Orders', 'smart-cycle-discounts' ); ?></div>
			</div>
		</div>

		<!-- Impressions -->
		<div class="wsscd-metric-card">
			<div class="wsscd-metric-icon">
				<?php WSSCD_Icon_Helper::render( 'visibility', array( 'size' => 20 ) ); ?>
			</div>
			<div class="wsscd-metric-content">
				<div class="wsscd-metric-value"><?php echo esc_html( $formatted_impressions ); ?></div>
				<div class="wsscd-metric-label"><?php esc_html_e( 'Views', 'smart-cycle-discounts' ); ?></div>
			</div>
		</div>

		<!-- CTR -->
		<div class="wsscd-metric-card">
			<div class="wsscd-metric-icon">
				<?php WSSCD_Icon_Helper::render( 'chart-line', array( 'size' => 20 ) ); ?>
			</div>
			<div class="wsscd-metric-content">
				<div class="wsscd-metric-value"><?php echo esc_html( $formatted_ctr ); ?></div>
				<div class="wsscd-metric-label"><?php esc_html_e( 'CTR', 'smart-cycle-discounts' ); ?></div>
			</div>
		</div>
	</div>
</div>
