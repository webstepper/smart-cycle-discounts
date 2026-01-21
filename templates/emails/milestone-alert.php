<?php
/**
 * Email Template: Milestone Alert
 *
 * Available variables:
 * - {campaign_name}         Campaign name
 * - {milestone_type}        Type of milestone reached
 * - {milestone_value}       The milestone value (e.g., $1000, 100 orders)
 * - {achievement_message}   Achievement description
 * - {performance_summary}   Current performance summary
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
	<title><?php esc_html_e( 'Milestone Reached', 'smart-cycle-discounts' ); ?></title>
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
			width: 80px;
			height: 80px;
			background-color: rgba(255, 255, 255, 0.2);
			border-radius: 50%;
			line-height: 80px;
			font-size: 40px;
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
			text-align: center;
		}
		.email-body p {
			margin: 0 0 15px 0;
			color: #3c434a;
			font-size: 16px;
		}
		.milestone-box {
			background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
			border: 2px solid #667eea;
			border-radius: 12px;
			padding: 30px;
			margin: 30px 0;
			text-align: center;
		}
		.milestone-icon {
			font-size: 60px;
			margin-bottom: 15px;
		}
		.milestone-value {
			display: block;
			font-size: 48px;
			font-weight: 700;
			color: #667eea;
			margin-bottom: 10px;
		}
		.milestone-type {
			display: block;
			font-size: 20px;
			color: #1d2327;
			font-weight: 600;
			text-transform: uppercase;
			letter-spacing: 1px;
		}
		.achievement-message {
			background-color: #f0f9ff;
			border-radius: 8px;
			padding: 20px;
			margin: 25px 0;
			text-align: center;
		}
		.achievement-message p {
			margin: 0;
			color: #1d2327;
			font-size: 18px;
			font-weight: 500;
			line-height: 1.8;
		}
		.performance-summary {
			background-color: #f9f9f9;
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
		.campaign-name {
			background-color: #f9f9f9;
			border-left: 4px solid #667eea;
			padding: 20px;
			margin: 25px 0;
			font-size: 18px;
			font-weight: 600;
			color: #1d2327;
		}
		.celebration-box {
			background: linear-gradient(135deg, #fff9e6 0%, #ffedd5 100%);
			border-radius: 8px;
			padding: 20px;
			margin: 25px 0;
			text-align: center;
		}
		.celebration-box p {
			margin: 10px 0;
			font-size: 16px;
			color: #1d2327;
		}
		.cta-button {
			display: inline-block;
			padding: 15px 30px;
			background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
			.email-header .icon {
				width: 60px;
				height: 60px;
				line-height: 60px;
				font-size: 30px;
			}
			.email-body {
				padding: 30px 20px;
			}
			.milestone-value {
				font-size: 36px;
			}
			.milestone-type {
				font-size: 16px;
			}
		}
	</style>
</head>
<body>
	<div class="email-container">
		<div class="email-header">
			<div class="icon">🏆</div>
			<h1><?php esc_html_e( 'Milestone Reached!', 'smart-cycle-discounts' ); ?> <span class="pro-badge">PRO</span></h1>
		</div>

		<div class="email-body">
			<h2><?php esc_html_e( 'Congratulations! 🎉', 'smart-cycle-discounts' ); ?></h2>

			<div class="campaign-name">
				{campaign_name}
			</div>

			<div class="milestone-box">
				<div class="milestone-icon">🎯</div>
				<span class="milestone-value">{milestone_value}</span>
				<span class="milestone-type">{milestone_type}</span>
			</div>

			<div class="achievement-message">
				<p>{achievement_message}</p>
			</div>

			<div class="celebration-box">
				<p><?php esc_html_e( '🌟 This is a significant achievement! Your campaign is performing exceptionally well.', 'smart-cycle-discounts' ); ?></p>
			</div>

			<div class="performance-summary">
				<h3><?php esc_html_e( 'Performance Summary', 'smart-cycle-discounts' ); ?></h3>
				<p>{performance_summary}</p>
			</div>

			<p style="text-align: center;">
				<a href="{dashboard_url}" class="cta-button"><?php esc_html_e( 'View Full Campaign Analytics', 'smart-cycle-discounts' ); ?></a>
			</p>

			<p style="font-size: 14px; color: #646970; margin-top: 30px; text-align: center;">
				<?php esc_html_e( 'Milestone alerts celebrate your success and help you track campaign achievements. This is a PRO feature that recognizes important performance milestones.', 'smart-cycle-discounts' ); ?>
			</p>
		</div>

		<div class="email-footer">
			<p><?php esc_html_e( 'This is an automated notification from Smart Cycle Discounts', 'smart-cycle-discounts' ); ?></p>
			<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=wsscd-notifications&tab=settings' ) ); ?>"><?php esc_html_e( 'Manage notification settings', 'smart-cycle-discounts' ); ?></a></p>
		</div>
	</div>
</body>
</html>
