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

<div class="scd-overview-subsection">
	<div class="scd-subsection-header">
		<?php echo SCD_Icon_Helper::get( 'chart-bar', array( 'size' => 16 ) ); ?>
		<h5><?php esc_html_e( 'Performance Overview', 'smart-cycle-discounts' ); ?></h5>
		<span class="scd-subsection-period"><?php esc_html_e( '(Last 30 Days)', 'smart-cycle-discounts' ); ?></span>
	</div>

	<div class="scd-metrics-row">
		<!-- Revenue -->
		<div class="scd-metric-card">
			<div class="scd-metric-icon">
				<?php echo SCD_Icon_Helper::get( 'money-alt', array( 'size' => 20 ) ); ?>
			</div>
			<div class="scd-metric-content">
				<div class="scd-metric-value"><?php echo wp_kses_post( $formatted_revenue ); ?></div>
				<div class="scd-metric-label"><?php esc_html_e( 'Revenue', 'smart-cycle-discounts' ); ?></div>
			</div>
		</div>

		<!-- Conversions -->
		<div class="scd-metric-card">
			<div class="scd-metric-icon">
				<?php echo SCD_Icon_Helper::get( 'yes', array( 'size' => 20 ) ); ?>
			</div>
			<div class="scd-metric-content">
				<div class="scd-metric-value"><?php echo esc_html( $formatted_conversions ); ?></div>
				<div class="scd-metric-label"><?php esc_html_e( 'Orders', 'smart-cycle-discounts' ); ?></div>
			</div>
		</div>

		<!-- Impressions -->
		<div class="scd-metric-card">
			<div class="scd-metric-icon">
				<?php echo SCD_Icon_Helper::get( 'visibility', array( 'size' => 20 ) ); ?>
			</div>
			<div class="scd-metric-content">
				<div class="scd-metric-value"><?php echo esc_html( $formatted_impressions ); ?></div>
				<div class="scd-metric-label"><?php esc_html_e( 'Views', 'smart-cycle-discounts' ); ?></div>
			</div>
		</div>

		<!-- CTR -->
		<div class="scd-metric-card">
			<div class="scd-metric-icon">
				<?php echo SCD_Icon_Helper::get( 'chart-line', array( 'size' => 20 ) ); ?>
			</div>
			<div class="scd-metric-content">
				<div class="scd-metric-value"><?php echo esc_html( $formatted_ctr ); ?></div>
				<div class="scd-metric-label"><?php esc_html_e( 'CTR', 'smart-cycle-discounts' ); ?></div>
			</div>
		</div>
	</div>
</div>
