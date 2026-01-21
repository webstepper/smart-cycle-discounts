<?php
/**
 * Email Template: Campaign Started
 *
 * Available variables:
 * - {campaign_name}    Campaign name
 * - {campaign_url}     Link to edit campaign
 * - {start_date}       Campaign start date
 * - {end_date}         Campaign end date
 * - {product_count}    Number of products in campaign
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
	<title><?php esc_html_e( 'Campaign Started', 'smart-cycle-discounts' ); ?></title>
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
			background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
		.campaign-details {
			background-color: #f9f9f9;
			border-left: 4px solid #667eea;
			padding: 20px;
			margin: 25px 0;
		}
		.campaign-details table {
			width: 100%;
			border-collapse: collapse;
		}
		.campaign-details td {
			padding: 8px 0;
			font-size: 15px;
		}
		.campaign-details td:first-child {
			font-weight: 600;
			color: #1d2327;
			width: 40%;
		}
		.campaign-details td:last-child {
			color: #3c434a;
		}
		.button-container {
			text-align: center;
			margin: 30px 0;
		}
		.button {
			display: inline-block;
			padding: 14px 30px;
			background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
			color: #ffffff !important;
			text-decoration: none;
			border-radius: 6px;
			font-size: 16px;
			font-weight: 600;
			transition: opacity 0.3s;
		}
		.button:hover {
			opacity: 0.9;
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
			color: #667eea;
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
			.campaign-details {
				padding: 15px;
			}
		}
	</style>
</head>
<body>
	<div class="email-container">
		<div class="email-header">
			<div class="icon">🚀</div>
			<h1><?php esc_html_e( 'Campaign Started', 'smart-cycle-discounts' ); ?></h1>
		</div>

		<div class="email-body">
			<h2><?php esc_html_e( 'Your campaign is now live!', 'smart-cycle-discounts' ); ?></h2>

			<p><?php esc_html_e( 'Great news! Your discount campaign has successfully started and is now active on your store.', 'smart-cycle-discounts' ); ?></p>

			<div class="campaign-details">
				<table>
					<tr>
						<td><?php esc_html_e( 'Campaign Name:', 'smart-cycle-discounts' ); ?></td>
						<td><strong>{campaign_name}</strong></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Start Date:', 'smart-cycle-discounts' ); ?></td>
						<td>{start_date}</td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'End Date:', 'smart-cycle-discounts' ); ?></td>
						<td>{end_date}</td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Products:', 'smart-cycle-discounts' ); ?></td>
						<td>{product_count} <?php esc_html_e( 'products', 'smart-cycle-discounts' ); ?></td>
					</tr>
				</table>
			</div>

			<p><?php esc_html_e( 'Customers can now see and use the discounts on applicable products. Monitor your campaign performance in the dashboard.', 'smart-cycle-discounts' ); ?></p>

			<div class="button-container">
				<a href="{campaign_url}" class="button"><?php esc_html_e( 'View Campaign Details', 'smart-cycle-discounts' ); ?></a>
			</div>
		</div>

		<div class="email-footer">
			<p><?php esc_html_e( 'This is an automated notification from Smart Cycle Discounts', 'smart-cycle-discounts' ); ?></p>
			<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=wsscd-notifications&tab=settings' ) ); ?>"><?php esc_html_e( 'Manage notification settings', 'smart-cycle-discounts' ); ?></a></p>
		</div>
	</div>
</body>
</html>
