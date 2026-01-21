<?php
/**
 * Email Template: Campaign Ended
 *
 * Available variables:
 * - {campaign_name}         Campaign name
 * - {performance_summary}   Performance metrics summary
 * - {total_revenue}         Total revenue generated
 * - {total_orders}          Total number of orders
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
	<title><?php esc_html_e( 'Campaign Ended', 'smart-cycle-discounts' ); ?></title>
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
			background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
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
		.stats-grid {
			display: table;
			width: 100%;
			margin: 25px 0;
		}
		.stat-box {
			display: table-cell;
			width: 50%;
			padding: 20px;
			text-align: center;
			background-color: #f9f9f9;
			border: 1px solid #e5e5e5;
		}
		.stat-box:first-child {
			border-right: none;
		}
		.stat-value {
			display: block;
			font-size: 32px;
			font-weight: 700;
			color: #4facfe;
			margin-bottom: 5px;
		}
		.stat-label {
			display: block;
			font-size: 14px;
			color: #646970;
			text-transform: uppercase;
			letter-spacing: 0.5px;
		}
		.performance-details {
			background-color: #f0f9ff;
			border-radius: 8px;
			padding: 20px;
			margin: 25px 0;
		}
		.performance-details h3 {
			margin: 0 0 15px 0;
			color: #1d2327;
			font-size: 18px;
			font-weight: 600;
		}
		.performance-details p {
			margin: 5px 0;
			color: #3c434a;
			font-size: 15px;
		}
		.campaign-name {
			background-color: #f9f9f9;
			border-left: 4px solid #4facfe;
			padding: 20px;
			margin: 25px 0;
			font-size: 18px;
			font-weight: 600;
			color: #1d2327;
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
			color: #4facfe;
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
				border-right: none !important;
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
			<div class="icon">✅</div>
			<h1><?php esc_html_e( 'Campaign Completed', 'smart-cycle-discounts' ); ?></h1>
		</div>

		<div class="email-body">
			<h2><?php esc_html_e( 'Your campaign has ended', 'smart-cycle-discounts' ); ?></h2>

			<div class="campaign-name">
				{campaign_name}
			</div>

			<p><?php esc_html_e( 'Here\'s a summary of your campaign performance:', 'smart-cycle-discounts' ); ?></p>

			<div class="stats-grid">
				<div class="stat-box">
					<span class="stat-value">{total_revenue}</span>
					<span class="stat-label"><?php esc_html_e( 'Total Revenue', 'smart-cycle-discounts' ); ?></span>
				</div>
				<div class="stat-box">
					<span class="stat-value">{total_orders}</span>
					<span class="stat-label"><?php esc_html_e( 'Total Orders', 'smart-cycle-discounts' ); ?></span>
				</div>
			</div>

			<div class="performance-details">
				<h3><?php esc_html_e( 'Performance Summary', 'smart-cycle-discounts' ); ?></h3>
				<p>{performance_summary}</p>
			</div>

			<p><?php esc_html_e( 'Thank you for using Smart Cycle Discounts to manage your promotional campaigns. You can view detailed analytics in your dashboard.', 'smart-cycle-discounts' ); ?></p>
		</div>

		<div class="email-footer">
			<p><?php esc_html_e( 'This is an automated notification from Smart Cycle Discounts', 'smart-cycle-discounts' ); ?></p>
			<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=wsscd-notifications&tab=settings' ) ); ?>"><?php esc_html_e( 'Manage notification settings', 'smart-cycle-discounts' ); ?></a></p>
		</div>
	</div>
</body>
</html>
