<?php
/**
 * Email Template: Low Stock Alert
 *
 * Available variables:
 * - {campaign_name}         Campaign name
 * - {products_list}         List of low stock products
 * - {total_low_stock}       Number of products running low
 * - {recommended_actions}   Suggested actions
 * - {dashboard_url}         URL to campaign dashboard
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/templates/emails
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php esc_html_e( 'Low Stock Alert', 'smart-cycle-discounts' ); ?></title>
	<style type="text/css">
		body {
			margin: 0;
			padding: 0;
			font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen-Sans, Ubuntu, Cantarell, 'Helvetica Neue', sans-serif;
			background-color: #f5f5f5;
			color: #333333;
			line-height: 1.6;
		}
		.email-container {
			max-width: 600px;
			margin: 0 auto;
			background-color: #ffffff;
		}
		.email-header {
			background: linear-gradient(135deg, #d63638 0%, #b52124 100%);
			padding: 40px 30px;
			text-align: center;
		}
		.email-header h1 {
			margin: 0;
			color: #ffffff;
			font-size: 28px;
			font-weight: 600;
		}
		.email-header .icon {
			display: inline-block;
			width: 60px;
			height: 60px;
			background-color: rgba(255, 255, 255, 0.2);
			border-radius: 50%;
			line-height: 60px;
			font-size: 30px;
			margin-bottom: 15px;
		}
		.email-body {
			padding: 40px 30px;
		}
		.email-body h2 {
			margin: 0 0 20px 0;
			color: #1d2327;
			font-size: 24px;
			font-weight: 600;
		}
		.email-body p {
			margin: 0 0 15px 0;
			color: #3c434a;
			font-size: 16px;
		}
		.alert-box {
			background-color: #fef2f2;
			border-left: 4px solid #d63638;
			padding: 20px;
			margin: 25px 0;
			border-radius: 4px;
		}
		.alert-box h3 {
			margin: 0 0 10px 0;
			color: #d63638;
			font-size: 18px;
			font-weight: 600;
		}
		.alert-box p {
			margin: 0;
			color: #3c434a;
			font-size: 15px;
		}
		.products-table {
			width: 100%;
			border-collapse: collapse;
			margin: 25px 0;
			background-color: #ffffff;
			border: 1px solid #e5e5e5;
		}
		.products-table th {
			background-color: #f9f9f9;
			padding: 12px;
			text-align: left;
			font-weight: 600;
			color: #1d2327;
			border-bottom: 2px solid #e5e5e5;
		}
		.products-table td {
			padding: 12px;
			border-bottom: 1px solid #e5e5e5;
			color: #3c434a;
		}
		.products-table tr:last-child td {
			border-bottom: none;
		}
		.stock-level {
			display: inline-block;
			padding: 4px 10px;
			border-radius: 12px;
			font-size: 12px;
			font-weight: 600;
		}
		.stock-critical {
			background-color: #fef2f2;
			color: #d63638;
		}
		.stock-low {
			background-color: #fff3cd;
			color: #f0ad4e;
		}
		.recommended-actions {
			background-color: #f0f9ff;
			border-radius: 8px;
			padding: 20px;
			margin: 25px 0;
		}
		.recommended-actions h3 {
			margin: 0 0 15px 0;
			color: #1d2327;
			font-size: 18px;
			font-weight: 600;
		}
		.recommended-actions ul {
			margin: 10px 0;
			padding-left: 25px;
		}
		.recommended-actions li {
			color: #3c434a;
			font-size: 15px;
			margin-bottom: 8px;
		}
		.campaign-name {
			background-color: #f9f9f9;
			border-left: 4px solid #d63638;
			padding: 20px;
			margin: 25px 0;
			font-size: 18px;
			font-weight: 600;
			color: #1d2327;
		}
		.cta-button {
			display: inline-block;
			padding: 15px 30px;
			background-color: #2271b1;
			color: #ffffff;
			text-decoration: none;
			border-radius: 4px;
			font-weight: 600;
			margin: 20px 0;
		}
		.pro-badge {
			display: inline-block;
			background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
			color: #ffffff;
			padding: 6px 12px;
			border-radius: 12px;
			font-size: 12px;
			font-weight: 600;
			text-transform: uppercase;
			letter-spacing: 0.5px;
			margin-left: 10px;
		}
		.email-footer {
			background-color: #f5f5f5;
			padding: 30px;
			text-align: center;
			border-top: 1px solid #e5e5e5;
		}
		.email-footer p {
			margin: 5px 0;
			color: #646970;
			font-size: 14px;
		}
		.email-footer a {
			color: #2271b1;
			text-decoration: none;
		}
		@media only screen and (max-width: 600px) {
			.email-header {
				padding: 30px 20px;
			}
			.email-header h1 {
				font-size: 24px;
			}
			.email-body {
				padding: 30px 20px;
			}
			.products-table {
				font-size: 14px;
			}
			.products-table th,
			.products-table td {
				padding: 8px;
			}
		}
	</style>
</head>
<body>
	<div class="email-container">
		<div class="email-header">
			<div class="icon">📦</div>
			<h1><?php esc_html_e( 'Low Stock Alert', 'smart-cycle-discounts' ); ?> <span class="pro-badge">PRO</span></h1>
		</div>

		<div class="email-body">
			<h2><?php esc_html_e( 'Action Required: Products Running Low', 'smart-cycle-discounts' ); ?></h2>

			<div class="campaign-name">
				{campaign_name}
			</div>

			<div class="alert-box">
				<h3><?php esc_html_e( 'Stock Alert', 'smart-cycle-discounts' ); ?></h3>
				<?php /* translators: %s: number of products with low stock */ ?>
				<p><?php echo sprintf( esc_html__( '%s products in your campaign are running low on stock. Immediate action may be needed to avoid stockouts.', 'smart-cycle-discounts' ), '{total_low_stock}' ); ?></p>
			</div>

			<p><?php esc_html_e( 'Here are the products that need attention:', 'smart-cycle-discounts' ); ?></p>

			{products_list}

			<div class="recommended-actions">
				<h3><?php esc_html_e( 'Recommended Actions', 'smart-cycle-discounts' ); ?></h3>
				{recommended_actions}
			</div>

			<p style="text-align: center;">
				<a href="{dashboard_url}" class="cta-button"><?php esc_html_e( 'View Campaign Dashboard', 'smart-cycle-discounts' ); ?></a>
			</p>

			<p style="font-size: 14px; color: #646970; margin-top: 30px;">
				<?php esc_html_e( 'Low stock alerts help you avoid stockouts and missed sales opportunities. This is a PRO feature that monitors product inventory for your active campaigns.', 'smart-cycle-discounts' ); ?>
			</p>
		</div>

		<div class="email-footer">
			<p><?php esc_html_e( 'This is an automated notification from Smart Cycle Discounts', 'smart-cycle-discounts' ); ?></p>
			<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=wsscd-notifications&tab=settings' ) ); ?>"><?php esc_html_e( 'Manage notification settings', 'smart-cycle-discounts' ); ?></a></p>
		</div>
	</div>
</body>
</html>
