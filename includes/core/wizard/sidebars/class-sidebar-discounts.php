<?php
/**
 * Discounts Step Sidebar Class
 *
 * Provides contextual help and guidance for the discounts configuration step.
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
 * Discounts step sidebar class
 *
 * @since 1.0.0
 */
class SCD_Wizard_Sidebar_Discounts extends SCD_Wizard_Sidebar_Base {

	/**
	 * Get sidebar content
	 *
	 * @since  1.0.0
	 * @return string HTML content
	 */
	public function get_content() {
		return $this->render_wrapper(
			__( 'Discount Configuration', 'smart-cycle-discounts' ),
			__( 'Create effective discount strategies', 'smart-cycle-discounts' )
		);
	}

	/**
	 * Render sidebar sections
	 *
	 * @since  1.0.0
	 * @return void
	 */
	protected function render_sections() {
		// Discount Types section
		ob_start();
		?>
		<ul class="scd-icon-list">
			<li>
				<?php echo SCD_Icon_Helper::get( 'tag', array( 'size' => 16, 'class' => 'scd-list-icon' ) ); ?>
				<div class="scd-list-content">
					<strong><?php esc_html_e( 'Percentage', 'smart-cycle-discounts' ); ?></strong>
					<span class="scd-list-description"><?php esc_html_e( 'Broad campaigns (20% off)', 'smart-cycle-discounts' ); ?></span>
				</div>
			</li>
			<li>
				<?php echo SCD_Icon_Helper::get( 'money-alt', array( 'size' => 16, 'class' => 'scd-list-icon' ) ); ?>
				<div class="scd-list-content">
					<strong><?php esc_html_e( 'Fixed Amount', 'smart-cycle-discounts' ); ?></strong>
					<span class="scd-list-description"><?php esc_html_e( 'Specific value ($10 off)', 'smart-cycle-discounts' ); ?></span>
				</div>
			</li>
			<li>
				<?php echo SCD_Icon_Helper::get( 'money', array( 'size' => 16, 'class' => 'scd-list-icon' ) ); ?>
				<div class="scd-list-content">
					<strong><?php esc_html_e( 'Spend Threshold', 'smart-cycle-discounts' ); ?></strong>
					<span class="scd-list-description"><?php esc_html_e( 'Increase order value', 'smart-cycle-discounts' ); ?></span>
				</div>
			</li>
			<li>
				<?php echo SCD_Icon_Helper::get( 'products', array( 'size' => 16, 'class' => 'scd-list-icon' ) ); ?>
				<div class="scd-list-content">
					<strong><?php esc_html_e( 'BOGO', 'smart-cycle-discounts' ); ?></strong>
					<span class="scd-list-description"><?php esc_html_e( 'Drive volume (Buy 2 Get 1)', 'smart-cycle-discounts' ); ?></span>
				</div>
			</li>
			<li>
				<?php echo SCD_Icon_Helper::get( 'chart-line', array( 'size' => 16, 'class' => 'scd-list-icon' ) ); ?>
				<div class="scd-list-content">
					<strong><?php esc_html_e( 'Volume', 'smart-cycle-discounts' ); ?></strong>
					<span class="scd-list-description"><?php esc_html_e( 'Reward quantity (Buy 3+ save 10%)', 'smart-cycle-discounts' ); ?></span>
				</div>
			</li>
		</ul>
		<?php
		$this->render_section(
			__( 'Discount Types', 'smart-cycle-discounts' ),
			'tag',
			ob_get_clean(),
			'open'
		);

		// Best Practices section
		ob_start();
		?>
		<ul class="scd-icon-list">
			<li>
				<?php echo SCD_Icon_Helper::get( 'chart-line', array( 'size' => 16, 'class' => 'scd-list-icon' ) ); ?>
				<div class="scd-list-content">
					<strong><?php esc_html_e( 'Start Conservative', 'smart-cycle-discounts' ); ?></strong>
					<span class="scd-list-description"><?php esc_html_e( 'Begin with 10-20% and test response', 'smart-cycle-discounts' ); ?></span>
				</div>
			</li>
			<li>
				<?php echo SCD_Icon_Helper::get( 'money-alt', array( 'size' => 16, 'class' => 'scd-list-icon' ) ); ?>
				<div class="scd-list-content">
					<strong><?php esc_html_e( 'Know Your Margins', 'smart-cycle-discounts' ); ?></strong>
					<span class="scd-list-description"><?php esc_html_e( 'Never discount below profit threshold', 'smart-cycle-discounts' ); ?></span>
				</div>
			</li>
			<li>
				<?php echo SCD_Icon_Helper::get( 'shield', array( 'size' => 16, 'class' => 'scd-list-icon' ) ); ?>
				<div class="scd-list-content">
					<strong><?php esc_html_e( 'Set Clear Limits', 'smart-cycle-discounts' ); ?></strong>
					<span class="scd-list-description"><?php esc_html_e( 'Use minimum purchase and maximum caps', 'smart-cycle-discounts' ); ?></span>
				</div>
			</li>
			<li>
				<?php echo SCD_Icon_Helper::get( 'close', array( 'size' => 16, 'class' => 'scd-list-icon' ) ); ?>
				<div class="scd-list-content">
					<strong><?php esc_html_e( 'Exclude Sale Items', 'smart-cycle-discounts' ); ?></strong>
					<span class="scd-list-description"><?php esc_html_e( 'Prevent double-discounting', 'smart-cycle-discounts' ); ?></span>
				</div>
			</li>
			<li>
				<?php echo SCD_Icon_Helper::get( 'chart-area', array( 'size' => 16, 'class' => 'scd-list-icon' ) ); ?>
				<div class="scd-list-content">
					<strong><?php esc_html_e( 'Set Thresholds Strategically', 'smart-cycle-discounts' ); ?></strong>
					<span class="scd-list-description"><?php esc_html_e( '20-30% above average order value', 'smart-cycle-discounts' ); ?></span>
				</div>
			</li>
		</ul>
		<?php
		$this->render_section(
			__( 'Best Practices', 'smart-cycle-discounts' ),
			'thumbs-up',
			ob_get_clean(),
			'open'
		);

		// Common Mistakes section
		ob_start();
		?>
		<div class="scd-callouts">
			<div class="scd-callout scd-callout--danger">
				<div class="scd-callout-icon">
					<?php echo SCD_Icon_Helper::get( 'close', array( 'size' => 16 ) ); ?>
				</div>
				<div class="scd-callout-content">
					<h4><?php esc_html_e( 'Excessive Discounting', 'smart-cycle-discounts' ); ?></h4>
					<p><?php esc_html_e( 'Frequent 50%+ discounts train customers to wait for sales', 'smart-cycle-discounts' ); ?></p>
				</div>
			</div>
			<div class="scd-callout scd-callout--danger">
				<div class="scd-callout-icon">
					<?php echo SCD_Icon_Helper::get( 'close', array( 'size' => 16 ) ); ?>
				</div>
				<div class="scd-callout-content">
					<h4><?php esc_html_e( 'No Minimums', 'smart-cycle-discounts' ); ?></h4>
					<p><?php esc_html_e( 'Allows abuse on low-value items', 'smart-cycle-discounts' ); ?></p>
				</div>
			</div>
			<div class="scd-callout scd-callout--danger">
				<div class="scd-callout-icon">
					<?php echo SCD_Icon_Helper::get( 'close', array( 'size' => 16 ) ); ?>
				</div>
				<div class="scd-callout-content">
					<h4><?php esc_html_e( 'Ignoring Costs', 'smart-cycle-discounts' ); ?></h4>
					<p><?php esc_html_e( 'Can result in selling below cost', 'smart-cycle-discounts' ); ?></p>
				</div>
			</div>
			<div class="scd-callout scd-callout--danger">
				<div class="scd-callout-icon">
					<?php echo SCD_Icon_Helper::get( 'close', array( 'size' => 16 ) ); ?>
				</div>
				<div class="scd-callout-content">
					<h4><?php esc_html_e( 'Unlimited Stacking', 'smart-cycle-discounts' ); ?></h4>
					<p><?php esc_html_e( 'Multiple discounts combine unexpectedly', 'smart-cycle-discounts' ); ?></p>
				</div>
			</div>
		</div>
		<?php
		$this->render_section(
			__( 'Avoid These Mistakes', 'smart-cycle-discounts' ),
			'warning',
			ob_get_clean(),
			'collapsed'
		);
	}
}
