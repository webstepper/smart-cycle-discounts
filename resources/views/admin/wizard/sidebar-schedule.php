<?php
/**
 * Schedule Step Sidebar
 *
 * Static sidebar content for the schedule configuration step
 *
 * @package SmartCycleDiscounts
 * @since   1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Schedule step sidebar class
 */
class SCD_Wizard_Sidebar_Schedule extends SCD_Wizard_Sidebar_Base {
	
	/**
	 * Get sidebar content
	 *
	 * @return string HTML content
	 */
	public function get_content() {
		return $this->render_wrapper(
			__( 'Schedule Your Campaign', 'smart-cycle-discounts' ),
			__( 'Configure when your campaign should run and optimize timing', 'smart-cycle-discounts' )
		);
	}
	
	/**
	 * Render sidebar sections
	 */
	protected function render_sections() {
		// Scheduling Options section
		ob_start();
		?>
		<ul class="scd-sidebar-list">
			<li><?php esc_html_e( 'Set start and end dates for your campaign', 'smart-cycle-discounts' ); ?></li>
			<li><?php esc_html_e( 'Leave end date empty for ongoing campaigns', 'smart-cycle-discounts' ); ?></li>
			<li><?php esc_html_e( 'Configure recurring schedules for regular promotions', 'smart-cycle-discounts' ); ?></li>
		</ul>
		<?php
		$this->render_section(
			__( 'Scheduling Options', 'smart-cycle-discounts' ),
			'calendar-alt',
			ob_get_clean(),
			'open' // Critical scheduling info - keep open
		);

		// Time Zone Notice section
		ob_start();
		?>
		<div class="scd-timezone-notice">
			<p><?php
				/* translators: %s: timezone setting */
				printf(
					esc_html__( 'All times are based on your store timezone: %s', 'smart-cycle-discounts' ),
					'<strong>' . esc_html( wp_timezone_string() ) . '</strong>'
				);
			?></p>
			<p><?php esc_html_e( 'Campaigns start at midnight and end at 11:59 PM on selected dates.', 'smart-cycle-discounts' ); ?></p>
		</div>
		<?php
		$this->render_section(
			__( 'Time Zone', 'smart-cycle-discounts' ),
			'clock',
			ob_get_clean(),
			'open' // Important timezone context - keep open
		);

		// Timing Optimizer section
		ob_start();
		?>
		<div class="scd-timing-optimizer">
			<p class="scd-timing-intro">
				<?php esc_html_e( 'Maximize campaign effectiveness by launching at optimal times.', 'smart-cycle-discounts' ); ?>
			</p>

			<div class="scd-timing-recommendations">
				<div class="scd-timing-rec">
					<span class="scd-timing-icon scd-timing-peak">🔴</span>
					<div class="scd-timing-content">
						<strong><?php esc_html_e( 'Peak Hours', 'smart-cycle-discounts' ); ?></strong>
						<p><?php esc_html_e( '3-7 PM: Launch flash sales during peak traffic for maximum visibility', 'smart-cycle-discounts' ); ?></p>
					</div>
				</div>

				<div class="scd-timing-rec">
					<span class="scd-timing-icon scd-timing-moderate">🟠</span>
					<div class="scd-timing-content">
						<strong><?php esc_html_e( 'Moderate Hours', 'smart-cycle-discounts' ); ?></strong>
						<p><?php esc_html_e( '12-2 PM: Good for lunch-break shoppers and email campaigns', 'smart-cycle-discounts' ); ?></p>
					</div>
				</div>

				<div class="scd-timing-rec">
					<span class="scd-timing-icon scd-timing-quiet">🟡</span>
					<div class="scd-timing-content">
						<strong><?php esc_html_e( 'Quiet Hours', 'smart-cycle-discounts' ); ?></strong>
						<p><?php esc_html_e( '9-11 AM: Ideal for testing campaigns before peak hours', 'smart-cycle-discounts' ); ?></p>
					</div>
				</div>
			</div>

			<div class="scd-timing-best-practices">
				<p class="scd-timing-label">
					<span class="dashicons dashicons-calendar-alt"></span>
					<?php esc_html_e( 'Best Days to Launch:', 'smart-cycle-discounts' ); ?>
				</p>
				<ul class="scd-timing-days">
					<li><?php esc_html_e( 'Monday-Thursday: Regular promotions', 'smart-cycle-discounts' ); ?></li>
					<li><?php esc_html_e( 'Friday: Weekend sales kickoff', 'smart-cycle-discounts' ); ?></li>
					<li><?php esc_html_e( 'Sunday: Week-ahead planning campaigns', 'smart-cycle-discounts' ); ?></li>
				</ul>
			</div>

			<div class="scd-timing-tip">
				<span class="dashicons dashicons-lightbulb"></span>
				<p><?php esc_html_e( 'Schedule campaigns to start 1-2 hours before your peak traffic for better preparation', 'smart-cycle-discounts' ); ?></p>
			</div>
		</div>

		<style>
		.scd-timing-optimizer {
			font-size: 13px;
		}

		.scd-timing-intro {
			margin: 0 0 16px 0;
			color: #646970;
			line-height: 1.6;
		}

		.scd-timing-recommendations {
			margin-bottom: 16px;
		}

		.scd-timing-rec {
			display: flex;
			align-items: flex-start;
			gap: 10px;
			padding: 10px;
			margin-bottom: 8px;
			background: #f6f7f7;
			border-radius: 4px;
		}

		.scd-timing-rec:last-child {
			margin-bottom: 0;
		}

		.scd-timing-icon {
			font-size: 18px;
			line-height: 1;
			flex-shrink: 0;
		}

		.scd-timing-content {
			flex: 1;
		}

		.scd-timing-content strong {
			display: block;
			margin-bottom: 4px;
			color: #1d2327;
			font-size: 12px;
		}

		.scd-timing-content p {
			margin: 0;
			color: #646970;
			font-size: 11px;
			line-height: 1.5;
		}

		.scd-timing-best-practices {
			margin-bottom: 16px;
		}

		.scd-timing-label {
			display: flex;
			align-items: center;
			gap: 6px;
			margin: 0 0 8px 0;
			font-weight: 600;
			font-size: 12px;
			color: #1d2327;
		}

		.scd-timing-label .dashicons {
			color: #2271b1;
			font-size: 16px;
			width: 16px;
			height: 16px;
		}

		.scd-timing-days {
			margin: 0;
			padding-left: 20px;
			list-style: disc;
		}

		.scd-timing-days li {
			margin-bottom: 4px;
			color: #646970;
			font-size: 12px;
			line-height: 1.5;
		}

		.scd-timing-tip {
			display: flex;
			align-items: flex-start;
			gap: 8px;
			padding: 10px;
			background: #f0f6fc;
			border-left: 3px solid #2271b1;
			border-radius: 3px;
		}

		.scd-timing-tip .dashicons {
			color: #2271b1;
			font-size: 16px;
			width: 16px;
			height: 16px;
			flex-shrink: 0;
			margin-top: 2px;
		}

		.scd-timing-tip p {
			margin: 0;
			font-size: 12px;
			color: #1d2327;
			line-height: 1.5;
		}
		</style>
		<?php
		$this->render_section(
			__( 'Timing Optimizer', 'smart-cycle-discounts' ),
			'chart-line',
			ob_get_clean(),
			'open' // Helpful timing guidance - keep open
		);

		// Pro Tips section
		ob_start();
		?>
		<ul class="scd-sidebar-tips">
			<li><?php esc_html_e( 'Schedule campaigns in advance to prepare for holidays', 'smart-cycle-discounts' ); ?></li>
			<li><?php esc_html_e( 'Use recurring schedules for weekly or monthly sales', 'smart-cycle-discounts' ); ?></li>
			<li><?php esc_html_e( 'Test campaigns with short durations first', 'smart-cycle-discounts' ); ?></li>
		</ul>
		<?php
		$this->render_section(
			__( 'Scheduling Tips', 'smart-cycle-discounts' ),
			'lightbulb',
			ob_get_clean(),
			'collapsed' // Reference material - collapse by default
		);
	}
}