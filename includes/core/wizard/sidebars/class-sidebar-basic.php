<?php
/**
 * Basic Step Sidebar Class
 *
 * Provides contextual help and guidance for the basic information step.
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
 * Basic step sidebar class
 *
 * @since 1.0.0
 */
class SCD_Wizard_Sidebar_Basic extends SCD_Wizard_Sidebar_Base {

	/**
	 * Get sidebar content
	 *
	 * @since  1.0.0
	 * @return string HTML content
	 */
	public function get_content() {
		return $this->render_wrapper(
			__( 'Campaign Setup Guide', 'smart-cycle-discounts' ),
			__( 'Best practices for creating effective campaigns', 'smart-cycle-discounts' )
		);
	}

	/**
	 * Render sidebar sections
	 *
	 * @since  1.0.0
	 * @return void
	 */
	protected function render_sections() {
		// Naming Best Practices section
		ob_start();
		?>
		<div class="scd-naming-tips">
			<p class="scd-naming-intro">
				<?php esc_html_e( 'Use descriptive names to easily identify and manage your campaigns.', 'smart-cycle-discounts' ); ?>
			</p>

			<div class="scd-do-dont-grid">
				<div class="scd-do-card">
					<div class="scd-card-header">
						<?php echo SCD_Icon_Helper::get( 'check', array( 'size' => 16 ) ); ?>
						<strong><?php esc_html_e( 'Do', 'smart-cycle-discounts' ); ?></strong>
					</div>
					<div class="scd-card-content">
						<p class="scd-example-item">
							<?php echo SCD_Icon_Helper::get( 'tag', array( 'size' => 16, 'class' => 'scd-example-icon' ) ); ?>
							<?php esc_html_e( 'Summer Sale 2025 - 20% Off', 'smart-cycle-discounts' ); ?>
						</p>
						<p class="scd-example-item">
							<?php echo SCD_Icon_Helper::get( 'products', array( 'size' => 16, 'class' => 'scd-example-icon' ) ); ?>
							<?php esc_html_e( 'BOGO Women\'s Shoes', 'smart-cycle-discounts' ); ?>
						</p>
						<p class="scd-example-item">
							<?php echo SCD_Icon_Helper::get( 'superhero', array( 'size' => 16, 'class' => 'scd-example-icon' ) ); ?>
							<?php esc_html_e( 'Flash Sale - Electronics 50%', 'smart-cycle-discounts' ); ?>
						</p>
					</div>
				</div>

				<div class="scd-dont-card">
					<div class="scd-card-header">
						<?php echo SCD_Icon_Helper::get( 'close', array( 'size' => 16 ) ); ?>
						<strong><?php esc_html_e( 'Don\'t', 'smart-cycle-discounts' ); ?></strong>
					</div>
					<div class="scd-card-content">
						<p class="scd-example-item">
							<?php echo SCD_Icon_Helper::get( 'warning', array( 'size' => 16, 'class' => 'scd-example-icon' ) ); ?>
							<?php esc_html_e( 'Generic: "Sale1", "Campaign"', 'smart-cycle-discounts' ); ?>
						</p>
						<p class="scd-example-item">
							<?php echo SCD_Icon_Helper::get( 'warning', array( 'size' => 16, 'class' => 'scd-example-icon' ) ); ?>
							<?php esc_html_e( 'Special characters: "@#$%"', 'smart-cycle-discounts' ); ?>
						</p>
						<p class="scd-example-item">
							<?php echo SCD_Icon_Helper::get( 'warning', array( 'size' => 16, 'class' => 'scd-example-icon' ) ); ?>
							<?php esc_html_e( 'Vague: "Discount", "Promo"', 'smart-cycle-discounts' ); ?>
						</p>
					</div>
				</div>
			</div>

			<div class="scd-naming-tip">
				<?php echo SCD_Icon_Helper::get( 'lightbulb', array( 'size' => 16 ) ); ?>
				<p><?php esc_html_e( 'Include discount type and amount for quick reference', 'smart-cycle-discounts' ); ?></p>
			</div>
		</div>
		<?php
		$this->render_section(
			__( 'Naming Best Practices', 'smart-cycle-discounts' ),
			'edit',
			ob_get_clean(),
			'open'
		);

		// Priority System section
		ob_start();
		?>
		<div class="scd-priority-guide">
			<p class="scd-priority-description">
				<?php esc_html_e( 'When products match multiple campaigns, highest priority wins. If equal, first created wins.', 'smart-cycle-discounts' ); ?>
			</p>

			<div class="scd-priority-levels">
				<div class="scd-priority-level scd-priority-high">
					<div class="scd-priority-range">5</div>
					<div class="scd-priority-label"><?php esc_html_e( 'Critical', 'smart-cycle-discounts' ); ?></div>
					<div class="scd-priority-use"><?php esc_html_e( 'VIP sales, exclusive offers', 'smart-cycle-discounts' ); ?></div>
				</div>

				<div class="scd-priority-level scd-priority-medium-high">
					<div class="scd-priority-range">4</div>
					<div class="scd-priority-label"><?php esc_html_e( 'High', 'smart-cycle-discounts' ); ?></div>
					<div class="scd-priority-use"><?php esc_html_e( 'Flash sales, time-sensitive', 'smart-cycle-discounts' ); ?></div>
				</div>

				<div class="scd-priority-level scd-priority-medium">
					<div class="scd-priority-range">3</div>
					<div class="scd-priority-label"><?php esc_html_e( 'Normal', 'smart-cycle-discounts' ); ?></div>
					<div class="scd-priority-use"><?php esc_html_e( 'Regular campaigns (default)', 'smart-cycle-discounts' ); ?></div>
				</div>

				<div class="scd-priority-level scd-priority-low">
					<div class="scd-priority-range">2</div>
					<div class="scd-priority-label"><?php esc_html_e( 'Low', 'smart-cycle-discounts' ); ?></div>
					<div class="scd-priority-use"><?php esc_html_e( 'Category sales, ongoing', 'smart-cycle-discounts' ); ?></div>
				</div>

				<div class="scd-priority-level scd-priority-low">
					<div class="scd-priority-range">1</div>
					<div class="scd-priority-label"><?php esc_html_e( 'Fallback', 'smart-cycle-discounts' ); ?></div>
					<div class="scd-priority-use"><?php esc_html_e( 'Always-on background', 'smart-cycle-discounts' ); ?></div>
				</div>
			</div>
		</div>
		<?php
		$this->render_section(
			__( 'Priority System', 'smart-cycle-discounts' ),
			'sort',
			ob_get_clean(),
			'open'
		);
	}
}
