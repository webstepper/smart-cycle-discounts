<?php
/**
 * Email Template: Performance Alert
 *
 * Available variables:
 * - {campaign_name}         Campaign name
 * - {alert_type}            Type of alert (low_performance, high_performance)
 * - {alert_message}         Alert message
 * - {current_performance}   Current performance metrics
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
	<title><?php esc_html_e( 'Performance Alert', 'smart-cycle-discounts' ); ?></title>
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
			background: linear-gradient(135deg, #f0ad4e 0%, #ed9c28 100%);
			padding: 40px 30px;
			text-align: center;
		}
		.email-header.high-performance {
			background: linear-gradient(135deg, #00a32a 0%, #008a20 100%);
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
			border-left: 4px solid #f0ad4e;
			padding: 20px;
			margin: 25px 0;
			border-radius: 4px;
		}
		.alert-box.high-performance {
			background-color: #d4edda;
			border-left-color: #00a32a;
		}
		.alert-box h3 {
			margin: 0 0 10px 0;
			color: #1d2327;
			font-size: 18px;
			font-weight: 600;
		}
		.alert-box p {
			margin: 0;
			color: #3c434a;
			font-size: 15px;
		}
		.performance-metrics {
			background-color: #f0f9ff;
			border-radius: 8px;
			padding: 20px;
			margin: 25px 0;
		}
		.performance-metrics h3 {
			margin: 0 0 15px 0;
			color: #1d2327;
			font-size: 18px;
			font-weight: 600;
		}
		.performance-metrics p {
			margin: 5px 0;
			color: #3c434a;
			font-size: 15px;
		}
		.recommended-actions {
			background-color: #f9f9f9;
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
			border-left: 4px solid #f0ad4e;
			padding: 20px;
			margin: 25px 0;
			font-size: 18px;
			font-weight: 600;
			color: #1d2327;
		}
		.campaign-name.high-performance {
			border-left-color: #00a32a;
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
		}
	</style>
</head>
<body>
	<div class="email-container">
		<div class="email-header {alert_type}">
			<div class="icon">⚡</div>
			<h1><?php esc_html_e( 'Performance Alert', 'smart-cycle-discounts' ); ?> <span class="pro-badge">PRO</span></h1>
		</div>

		<div class="email-body">
			<h2><?php esc_html_e( 'Campaign Performance Update', 'smart-cycle-discounts' ); ?></h2>

			<div class="campaign-name {alert_type}">
				{campaign_name}
			</div>

			<div class="alert-box {alert_type}">
				<h3><?php esc_html_e( 'Alert Details', 'smart-cycle-discounts' ); ?></h3>
				<p>{alert_message}</p>
			</div>

			<div class="performance-metrics">
				<h3><?php esc_html_e( 'Current Performance', 'smart-cycle-discounts' ); ?></h3>
				<p>{current_performance}</p>
			</div>

			<div class="recommended-actions">
				<h3><?php esc_html_e( 'Recommended Actions', 'smart-cycle-discounts' ); ?></h3>
				{recommended_actions}
			</div>

			<p style="text-align: center;">
				<a href="{dashboard_url}" class="cta-button"><?php esc_html_e( 'View Campaign Dashboard', 'smart-cycle-discounts' ); ?></a>
			</p>

			<p style="font-size: 14px; color: #646970; margin-top: 30px;">
				<?php esc_html_e( 'Performance alerts help you optimize your campaigns in real-time. This is a PRO feature that monitors your campaigns and notifies you when action is needed.', 'smart-cycle-discounts' ); ?>
			</p>
		</div>

		<div class="email-footer">
			<p><?php esc_html_e( 'This is an automated notification from Smart Cycle Discounts', 'smart-cycle-discounts' ); ?></p>
			<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=wsscd-notifications&tab=settings' ) ); ?>"><?php esc_html_e( 'Manage notification settings', 'smart-cycle-discounts' ); ?></a></p>
		</div>
	</div>
</body>
</html>
