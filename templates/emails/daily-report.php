<?php
/**
 * Email Template: Daily Performance Report
 *
 * Available variables:
 * - {date}               Report date
 * - {active}   Active campaigns count
 * - {total_revenue}      Total revenue for the day
 * - {total_orders}       Total orders for the day
 * - {top_products}       Top performing products
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
	<title><?php esc_html_e( 'Daily Performance Report', 'smart-cycle-discounts' ); ?></title>
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
		.email-header .date {
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
		.stats-grid {
			display: table;
			width: 100%;
			margin: 25px 0;
		}
		.stat-box {
			display: table-cell;
			width: 33.33%;
			padding: 20px;
			text-align: center;
			background-color: #f9f9f9;
			border: 1px solid #e5e5e5;
		}
		.stat-box + .stat-box {
			border-left: none;
		}
		.stat-value {
			display: block;
			font-size: 28px;
			font-weight: 700;
			color: #667eea;
			margin-bottom: 5px;
		}
		.stat-label {
			display: block;
			font-size: 13px;
			color: #646970;
			text-transform: uppercase;
			letter-spacing: 0.5px;
		}
		.top-products {
			background-color: #f9f9f9;
			border-radius: 8px;
			padding: 20px;
			margin: 25px 0;
		}
		.top-products h3 {
			margin: 0 0 15px 0;
			color: #1d2327;
			font-size: 18px;
			font-weight: 600;
		}
		.top-products p {
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
			.stats-grid {
				display: block;
			}
			.stat-box {
				display: block;
				width: 100%;
				border-left: none !important;
				border-bottom: none;
			}
			.stat-box:last-child {
				border-top: none;
			}
		}
	</style>
</head>
<body>
	<div class="email-container">
		<div class="email-header">
			<div class="icon">📊</div>
			<h1><?php esc_html_e( 'Daily Performance Report', 'smart-cycle-discounts' ); ?></h1>
			<div class="date">{date}</div>
		</div>

		<div class="email-body">
			<h2><?php esc_html_e( 'Today\'s Performance Summary', 'smart-cycle-discounts' ); ?></h2>

			<p><?php esc_html_e( 'Here\'s how your discount campaigns performed today:', 'smart-cycle-discounts' ); ?></p>

			<div class="stats-grid">
				<div class="stat-box">
					<span class="stat-value">{active}</span>
					<span class="stat-label"><?php esc_html_e( 'Active Campaigns', 'smart-cycle-discounts' ); ?></span>
				</div>
				<div class="stat-box">
					<span class="stat-value">{total_revenue}</span>
					<span class="stat-label"><?php esc_html_e( 'Revenue', 'smart-cycle-discounts' ); ?></span>
				</div>
				<div class="stat-box">
					<span class="stat-value">{total_orders}</span>
					<span class="stat-label"><?php esc_html_e( 'Orders', 'smart-cycle-discounts' ); ?></span>
				</div>
			</div>

			<div class="top-products">
				<h3><?php esc_html_e( 'Top Performing Products', 'smart-cycle-discounts' ); ?></h3>
				<p>{top_products}</p>
			</div>

			<div class="button-container">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=scd-campaigns' ) ); ?>" class="button"><?php esc_html_e( 'View Full Analytics', 'smart-cycle-discounts' ); ?></a>
			</div>
		</div>

		<div class="email-footer">
			<p><?php esc_html_e( 'This is an automated daily report from Smart Cycle Discounts', 'smart-cycle-discounts' ); ?></p>
			<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=scd-notifications&tab=settings' ) ); ?>"><?php esc_html_e( 'Manage notification settings', 'smart-cycle-discounts' ); ?></a></p>
		</div>
	</div>
</body>
</html>
