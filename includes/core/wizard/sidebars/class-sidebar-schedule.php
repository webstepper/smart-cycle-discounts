<?php
/**
 * Schedule Step Sidebar Class
 *
 * Provides contextual help and guidance for the schedule configuration step.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/wizard/sidebars
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Schedule step sidebar class
 *
 * @since 1.0.0
 */
class SCD_Wizard_Sidebar_Schedule extends SCD_Wizard_Sidebar_Base {

	/**
	 * Get sidebar content
	 *
	 * @since  1.0.0
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
	 *
	 * @since  1.0.0
	 * @return void
	 */
	protected function render_sections() {
		// Scheduling Basics section
		ob_start();
		?>
		<ul class="scd-sidebar-list">
			<li><?php esc_html_e( 'Set start/end dates or leave empty for ongoing', 'smart-cycle-discounts' ); ?></li>
			<li><?php esc_html_e( 'Configure recurring schedules for regular sales', 'smart-cycle-discounts' ); ?></li>
			<li><?php
				/* translators: %s: timezone setting */
				printf(
					esc_html__( 'All times use your timezone: %s', 'smart-cycle-discounts' ),
					'<strong>' . esc_html( wp_timezone_string() ) . '</strong>'
				);
			?></li>
			<li><?php esc_html_e( 'Campaigns run from midnight to 11:59 PM', 'smart-cycle-discounts' ); ?></li>
		</ul>
		<?php
		$this->render_section(
			__( 'Scheduling Basics', 'smart-cycle-discounts' ),
			'calendar-alt',
			ob_get_clean(),
			'open'
		);

		// Timing Best Practices section
		ob_start();
		?>
		<ul class="scd-sidebar-list">
			<li>
				<strong><?php esc_html_e( 'Launch Timing', 'smart-cycle-discounts' ); ?></strong> -
				<?php esc_html_e( 'Start 1-2 hours before peak traffic', 'smart-cycle-discounts' ); ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'Peak Hours', 'smart-cycle-discounts' ); ?></strong> -
				<?php esc_html_e( '3-7 PM for flash sales and high visibility', 'smart-cycle-discounts' ); ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'Best Days', 'smart-cycle-discounts' ); ?></strong> -
				<?php esc_html_e( 'Monday-Thursday for regular, Friday for weekend kickoff', 'smart-cycle-discounts' ); ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'Testing', 'smart-cycle-discounts' ); ?></strong> -
				<?php esc_html_e( 'Launch 9-11 AM with short duration first', 'smart-cycle-discounts' ); ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'Holidays', 'smart-cycle-discounts' ); ?></strong> -
				<?php esc_html_e( 'Schedule well in advance for preparation', 'smart-cycle-discounts' ); ?>
			</li>
		</ul>
		<?php
		$this->render_section(
			__( 'Timing Best Practices', 'smart-cycle-discounts' ),
			'clock',
			ob_get_clean(),
			'open'
		);

		// Campaign Duration Tips section
		ob_start();
		?>
		<ul class="scd-sidebar-list">
			<li><?php esc_html_e( 'Flash Sales: 24-48 hours for urgency', 'smart-cycle-discounts' ); ?></li>
			<li><?php esc_html_e( 'Seasonal: 2-4 weeks for major events', 'smart-cycle-discounts' ); ?></li>
			<li><?php esc_html_e( 'Clearance: Open-ended until stock depletes', 'smart-cycle-discounts' ); ?></li>
			<li><?php esc_html_e( 'Weekly: Recurring for consistent promotions', 'smart-cycle-discounts' ); ?></li>
		</ul>
		<?php
		$this->render_section(
			__( 'Duration Guidelines', 'smart-cycle-discounts' ),
			'chart-line',
			ob_get_clean(),
			'collapsed'
		);
	}
}
