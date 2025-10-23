<?php
/**
 * Email Template: Campaign Ending Soon
 *
 * Available variables:
 * - {campaign_name}         Campaign name
 * - {campaign_url}          Link to edit campaign
 * - {end_date}              Campaign end date
 * - {time_remaining}        Time remaining until end
 * - {performance_summary}   Performance metrics summary
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
	<title><?php esc_html_e( 'Campaign Ending Soon', 'smart-cycle-discounts' ); ?></title>
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
			background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
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
			background-color: #fff3cd;
			border-left: 4px solid #f5576c;
			padding: 20px;
			margin: 25px 0;
		}
		.alert-box .alert-title {
			font-size: 18px;
			font-weight: 600;
			color: #856404;
			margin: 0 0 10px 0;
		}
		.alert-box p {
			margin: 0;
			color: #856404;
		}
		.campaign-details {
			background-color: #f9f9f9;
			border-left: 4px solid #f5576c;
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
		.performance-summary {
			background-color: #f0f9ff;
			border-radius: 8px;
			padding: 20px;
			margin: 25px 0;
		}
		.performance-summary h3 {
			margin: 0 0 15px 0;
			color: #1d2327;
			font-size: 18px;
			font-weight: 600;
		}
		.performance-summary p {
			margin: 5px 0;
			color: #3c434a;
			font-size: 15px;
		}
		.button-container {
			text-align: center;
			margin: 30px 0;
		}
		.button {
			display: inline-block;
			padding: 14px 30px;
			background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
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
			color: #f5576c;
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
			.campaign-details, .alert-box, .performance-summary {
				padding: 15px;
			}
		}
	</style>
</head>
<body>
	<div class="email-container">
		<div class="email-header">
			<div class="icon">⏰</div>
			<h1><?php esc_html_e( 'Campaign Ending Soon', 'smart-cycle-discounts' ); ?></h1>
		</div>

		<div class="email-body">
			<h2><?php esc_html_e( 'Your campaign will end soon', 'smart-cycle-discounts' ); ?></h2>

			<div class="alert-box">
				<div class="alert-title"><?php esc_html_e( 'Time Remaining: {time_remaining}', 'smart-cycle-discounts' ); ?></div>
				<p><?php esc_html_e( 'This is a reminder that your campaign is approaching its end date.', 'smart-cycle-discounts' ); ?></p>
			</div>

			<div class="campaign-details">
				<table>
					<tr>
						<td><?php esc_html_e( 'Campaign Name:', 'smart-cycle-discounts' ); ?></td>
						<td><strong>{campaign_name}</strong></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'End Date:', 'smart-cycle-discounts' ); ?></td>
						<td>{end_date}</td>
					</tr>
				</table>
			</div>

			<div class="performance-summary">
				<h3><?php esc_html_e( 'Current Performance', 'smart-cycle-discounts' ); ?></h3>
				<p>{performance_summary}</p>
			</div>

			<p><?php esc_html_e( 'If you want to extend the campaign or make any adjustments, you can do so from your dashboard.', 'smart-cycle-discounts' ); ?></p>

			<div class="button-container">
				<a href="{campaign_url}" class="button"><?php esc_html_e( 'Manage Campaign', 'smart-cycle-discounts' ); ?></a>
			</div>
		</div>

		<div class="email-footer">
			<p><?php esc_html_e( 'This is an automated notification from Smart Cycle Discounts', 'smart-cycle-discounts' ); ?></p>
			<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=scd-notifications&tab=settings' ) ); ?>"><?php esc_html_e( 'Manage notification settings', 'smart-cycle-discounts' ); ?></a></p>
		</div>
	</div>
</body>
</html>
