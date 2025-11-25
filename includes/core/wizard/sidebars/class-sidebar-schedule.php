<?php
/**
 * Schedule Step Sidebar Class
 *
 * Provides contextual help and guidance for the schedule configuration step.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/wizard/sidebars
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
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
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->step           = 'schedule';
		$this->use_contextual = true;
	}

	/**
	 * Get legacy sidebar content (fallback)
	 *
	 * @since  1.0.0
	 * @return string HTML content
	 */
	protected function get_legacy_content() {
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
		<ul class="scd-icon-list">
			<li>
				<?php echo SCD_Icon_Helper::get( 'clock', array( 'size' => 16, 'class' => 'scd-list-icon' ) ); ?>
				<div class="scd-list-content">
					<strong><?php esc_html_e( 'Launch Timing', 'smart-cycle-discounts' ); ?></strong>
					<span class="scd-list-description"><?php esc_html_e( 'Start 1-2 hours before peak traffic', 'smart-cycle-discounts' ); ?></span>
				</div>
			</li>
			<li>
				<?php echo SCD_Icon_Helper::get( 'chart-line', array( 'size' => 16, 'class' => 'scd-list-icon' ) ); ?>
				<div class="scd-list-content">
					<strong><?php esc_html_e( 'Peak Hours', 'smart-cycle-discounts' ); ?></strong>
					<span class="scd-list-description"><?php esc_html_e( '3-7 PM for flash sales and high visibility', 'smart-cycle-discounts' ); ?></span>
				</div>
			</li>
			<li>
				<?php echo SCD_Icon_Helper::get( 'calendar', array( 'size' => 16, 'class' => 'scd-list-icon' ) ); ?>
				<div class="scd-list-content">
					<strong><?php esc_html_e( 'Best Days', 'smart-cycle-discounts' ); ?></strong>
					<span class="scd-list-description"><?php esc_html_e( 'Monday-Thursday for regular, Friday for weekend kickoff', 'smart-cycle-discounts' ); ?></span>
				</div>
			</li>
			<li>
				<?php echo SCD_Icon_Helper::get( 'welcome-learn-more', array( 'size' => 16, 'class' => 'scd-list-icon' ) ); ?>
				<div class="scd-list-content">
					<strong><?php esc_html_e( 'Testing', 'smart-cycle-discounts' ); ?></strong>
					<span class="scd-list-description"><?php esc_html_e( 'Launch 9-11 AM with short duration first', 'smart-cycle-discounts' ); ?></span>
				</div>
			</li>
			<li>
				<?php echo SCD_Icon_Helper::get( 'admin-site-alt3', array( 'size' => 16, 'class' => 'scd-list-icon' ) ); ?>
				<div class="scd-list-content">
					<strong><?php esc_html_e( 'Holidays', 'smart-cycle-discounts' ); ?></strong>
					<span class="scd-list-description"><?php esc_html_e( 'Schedule well in advance for preparation', 'smart-cycle-discounts' ); ?></span>
				</div>
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
		<table class="scd-comparison-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Campaign', 'smart-cycle-discounts' ); ?></th>
					<th><?php esc_html_e( 'Purpose', 'smart-cycle-discounts' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td class="scd-comparison-type">
						<strong><?php esc_html_e( 'Flash Sales', 'smart-cycle-discounts' ); ?></strong>
					</td>
					<td class="scd-comparison-benefit"><?php esc_html_e( 'Creates urgency', 'smart-cycle-discounts' ); ?></td>
				</tr>
				<tr>
					<td class="scd-comparison-type">
						<strong><?php esc_html_e( 'Seasonal', 'smart-cycle-discounts' ); ?></strong>
					</td>
					<td class="scd-comparison-benefit"><?php esc_html_e( 'Major events coverage', 'smart-cycle-discounts' ); ?></td>
				</tr>
				<tr>
					<td class="scd-comparison-type">
						<strong><?php esc_html_e( 'Clearance', 'smart-cycle-discounts' ); ?></strong>
					</td>
					<td class="scd-comparison-benefit"><?php esc_html_e( 'Until stock depletes', 'smart-cycle-discounts' ); ?></td>
				</tr>
				<tr>
					<td class="scd-comparison-type">
						<strong><?php esc_html_e( 'Weekly', 'smart-cycle-discounts' ); ?></strong>
					</td>
					<td class="scd-comparison-benefit"><?php esc_html_e( 'Consistent promotions', 'smart-cycle-discounts' ); ?></td>
				</tr>
			</tbody>
		</table>
		<?php
		$this->render_section(
			__( 'Duration Guidelines', 'smart-cycle-discounts' ),
			'chart-line',
			ob_get_clean(),
			'collapsed'
		);
	}
}
