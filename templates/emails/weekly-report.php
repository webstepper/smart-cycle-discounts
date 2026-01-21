<?php
/**
 * Email Template: Weekly Performance Report
 *
 * Available variables:
 * - {week_range}          Week date range
 * - {campaign_summary}    Campaign summary statistics
 * - {revenue_summary}     Revenue summary
 * - {performance_trends}  Performance trends data
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
	<title><?php esc_html_e( 'Weekly Performance Report', 'smart-cycle-discounts' ); ?></title>
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
			background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
			padding: 40px 30px;
			text-align: center;
		}
		.email-header h1 {
			margin: 0;
			color: #ffffff;
			font-size: 28px;
			font-weight: 600;
		}
		.email-header .date-range {
			color: rgba(255, 255, 255, 0.9);
			font-size: 16px;
			margin-top: 10px;
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
			font-size: 20px;
			font-weight: 600;
		}
		.email-body p {
			margin: 0 0 15px 0;
			color: #3c434a;
			font-size: 16px;
		}
		.section {
			background-color: #f9f9f9;
			border-radius: 8px;
			padding: 20px;
			margin: 25px 0;
		}
		.section h3 {
			margin: 0 0 15px 0;
			color: #1d2327;
			font-size: 18px;
			font-weight: 600;
		}
		.section p {
			margin: 5px 0;
			color: #3c434a;
			font-size: 15px;
		}
		.campaign-summary {
			border-left: 4px solid #43e97b;
		}
		.revenue-summary {
			border-left: 4px solid #38f9d7;
		}
		.performance-trends {
			border-left: 4px solid #667eea;
		}
		.highlight {
			background-color: #fff;
			border: 2px solid #43e97b;
			border-radius: 8px;
			padding: 20px;
			margin: 25px 0;
			text-align: center;
		}
		.highlight .big-number {
			font-size: 36px;
			font-weight: 700;
			color: #43e97b;
			display: block;
			margin-bottom: 10px;
		}
		.highlight .label {
			font-size: 16px;
			color: #646970;
			display: block;
		}
		.button-container {
			text-align: center;
			margin: 30px 0;
		}
		.button {
			display: inline-block;
			padding: 14px 30px;
			background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
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
			color: #43e97b;
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
			.section, .highlight {
				padding: 15px;
			}
		}
	</style>
</head>
<body>
	<div class="email-container">
		<div class="email-header">
			<div class="icon">📈</div>
			<h1><?php esc_html_e( 'Weekly Performance Report', 'smart-cycle-discounts' ); ?></h1>
			<div class="date-range">{week_range}</div>
		</div>

		<div class="email-body">
			<h2><?php esc_html_e( 'This Week\'s Performance Overview', 'smart-cycle-discounts' ); ?></h2>

			<p><?php esc_html_e( 'Here\'s a comprehensive summary of your discount campaign performance over the past week:', 'smart-cycle-discounts' ); ?></p>

			<div class="section campaign-summary">
				<h3><?php esc_html_e( 'Campaign Summary', 'smart-cycle-discounts' ); ?></h3>
				<p>{campaign_summary}</p>
			</div>

			<div class="section revenue-summary">
				<h3><?php esc_html_e( 'Revenue Summary', 'smart-cycle-discounts' ); ?></h3>
				<p>{revenue_summary}</p>
			</div>

			<div class="section performance-trends">
				<h3><?php esc_html_e( 'Performance Trends', 'smart-cycle-discounts' ); ?></h3>
				<p>{performance_trends}</p>
			</div>

			<p><?php esc_html_e( 'Keep up the great work! Review detailed analytics to optimize your campaigns for even better results.', 'smart-cycle-discounts' ); ?></p>

			<div class="button-container">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wsscd-campaigns' ) ); ?>" class="button"><?php esc_html_e( 'View Detailed Analytics', 'smart-cycle-discounts' ); ?></a>
			</div>
		</div>

		<div class="email-footer">
			<p><?php esc_html_e( 'This is an automated weekly report from Smart Cycle Discounts', 'smart-cycle-discounts' ); ?></p>
			<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=wsscd-notifications&tab=settings' ) ); ?>"><?php esc_html_e( 'Manage notification settings', 'smart-cycle-discounts' ); ?></a></p>
		</div>
	</div>
</body>
</html>
