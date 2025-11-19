<?php
/**
 * Products Step Sidebar Class
 *
 * Provides contextual help and guidance for the products selection step.
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
 * Products step sidebar class
 *
 * @since 1.0.0
 */
class SCD_Wizard_Sidebar_Products extends SCD_Wizard_Sidebar_Base {

	/**
	 * Get sidebar content
	 *
	 * @since  1.0.0
	 * @return string HTML content
	 */
	public function get_content() {
		return $this->render_wrapper(
			__( 'Product Selection Guide', 'smart-cycle-discounts' ),
			__( 'Choose the right products and maximize your campaign effectiveness', 'smart-cycle-discounts' )
		);
	}

	/**
	 * Render sidebar sections
	 *
	 * @since  1.0.0
	 * @return void
	 */
	protected function render_sections() {
		// Selection Methods section
		ob_start();
		?>
		<ul class="scd-icon-list">
			<li>
				<?php echo SCD_Icon_Helper::get( 'cart', array( 'size' => 16, 'class' => 'scd-list-icon' ) ); ?>
				<div class="scd-list-content">
					<strong><?php esc_html_e( 'All Products', 'smart-cycle-discounts' ); ?></strong>
					<span class="scd-list-description"><?php esc_html_e( 'Apply discount to entire catalog or specific categories', 'smart-cycle-discounts' ); ?></span>
				</div>
			</li>
			<li>
				<?php echo SCD_Icon_Helper::get( 'marker', array( 'size' => 16, 'class' => 'scd-list-icon' ) ); ?>
				<div class="scd-list-content">
					<strong><?php esc_html_e( 'Specific Products', 'smart-cycle-discounts' ); ?></strong>
					<span class="scd-list-description"><?php esc_html_e( 'Hand-pick products for precise control', 'smart-cycle-discounts' ); ?></span>
				</div>
			</li>
			<li>
				<?php echo SCD_Icon_Helper::get( 'randomize', array( 'size' => 16, 'class' => 'scd-list-icon' ) ); ?>
				<div class="scd-list-content">
					<strong><?php esc_html_e( 'Random Products', 'smart-cycle-discounts' ); ?></strong>
					<span class="scd-list-description"><?php esc_html_e( 'Randomly select X products from your catalog', 'smart-cycle-discounts' ); ?></span>
				</div>
			</li>
			<li>
				<?php echo SCD_Icon_Helper::get( 'filter', array( 'size' => 16, 'class' => 'scd-list-icon' ) ); ?>
				<div class="scd-list-content">
					<strong><?php esc_html_e( 'Smart Selection', 'smart-cycle-discounts' ); ?></strong>
					<span class="scd-list-description"><?php esc_html_e( 'Use conditions to automatically filter products', 'smart-cycle-discounts' ); ?></span>
				</div>
			</li>
		</ul>
		<?php
		$this->render_section(
			__( 'Selection Methods', 'smart-cycle-discounts' ),
			'products',
			ob_get_clean(),
			'open'
		);

		// When to Use Each Method section
		ob_start();
		?>
		<ul class="scd-sidebar-list">
			<li>
				<strong><?php esc_html_e( 'All Products', 'smart-cycle-discounts' ); ?></strong> -
				<?php esc_html_e( 'Site-wide sales, seasonal promotions, customer appreciation events', 'smart-cycle-discounts' ); ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'Specific Products', 'smart-cycle-discounts' ); ?></strong> -
				<?php esc_html_e( 'Clearance items, featured products, new arrivals, flash sales on specific items', 'smart-cycle-discounts' ); ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'Random Products', 'smart-cycle-discounts' ); ?></strong> -
				<?php esc_html_e( 'Daily deals, surprise sales, gamification (creates urgency and discovery)', 'smart-cycle-discounts' ); ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'Smart Selection', 'smart-cycle-discounts' ); ?></strong> -
				<?php esc_html_e( 'Automatic inventory management, margin-based pricing, stock clearance, price range targeting', 'smart-cycle-discounts' ); ?>
			</li>
		</ul>
		<?php
		$this->render_section(
			__( 'When to Use Each Method', 'smart-cycle-discounts' ),
			'lightbulb',
			ob_get_clean(),
			'open'
		);

		// Understanding Conditions section
		ob_start();
		?>
		<div class="scd-conditions-guide">
			<p class="scd-conditions-intro">
				<?php esc_html_e( 'Conditions let you filter products automatically based on their properties. Combine multiple conditions for powerful targeting.', 'smart-cycle-discounts' ); ?>
			</p>

			<div class="scd-condition-examples">
				<div class="scd-condition-example">
					<strong><?php esc_html_e( 'Example: Exclude Low-Margin Products', 'smart-cycle-discounts' ); ?></strong>
					<ul>
						<li><?php esc_html_e( 'Condition: Regular Price is greater than $50', 'smart-cycle-discounts' ); ?></li>
						<li><?php esc_html_e( 'Protects profit margins on expensive items', 'smart-cycle-discounts' ); ?></li>
					</ul>
				</div>

				<div class="scd-condition-example">
					<strong><?php esc_html_e( 'Example: Target Overstock', 'smart-cycle-discounts' ); ?></strong>
					<ul>
						<li><?php esc_html_e( 'Condition: Stock Quantity is greater than 50', 'smart-cycle-discounts' ); ?></li>
						<li><?php esc_html_e( 'Automatically discounts high-inventory items', 'smart-cycle-discounts' ); ?></li>
					</ul>
				</div>

				<div class="scd-condition-example">
					<strong><?php esc_html_e( 'Example: Exclude Sale Items', 'smart-cycle-discounts' ); ?></strong>
					<ul>
						<li><?php esc_html_e( 'Condition: On Sale is not equal to Yes', 'smart-cycle-discounts' ); ?></li>
						<li><?php esc_html_e( 'Prevents double-discounting', 'smart-cycle-discounts' ); ?></li>
					</ul>
				</div>
			</div>

			<div class="scd-conditions-tip">
				<?php echo SCD_Icon_Helper::get( 'warning', array( 'size' => 16 ) ); ?>
				<p><strong><?php esc_html_e( 'Pro Tip:', 'smart-cycle-discounts' ); ?></strong> <?php esc_html_e( 'Use "AND" logic to require all conditions, or "OR" to match any condition. This gives you surgical control over product targeting.', 'smart-cycle-discounts' ); ?></p>
			</div>
		</div>
		<?php
		$this->render_section(
			__( 'Understanding Conditions', 'smart-cycle-discounts' ),
			'filter',
			ob_get_clean(),
			'open'
		);

		// Category Selection Strategies section
		ob_start();
		?>
		<ul class="scd-sidebar-list">
			<li>
				<strong><?php esc_html_e( 'Seasonal Focus', 'smart-cycle-discounts' ); ?></strong> -
				<?php esc_html_e( 'Select "Winter Clothing" or "Holiday Decor" for timely promotions', 'smart-cycle-discounts' ); ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'Cross-Sell Strategy', 'smart-cycle-discounts' ); ?></strong> -
				<?php esc_html_e( 'Discount complementary categories together (e.g., "Cameras" + "Camera Accessories")', 'smart-cycle-discounts' ); ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'Margin-Based', 'smart-cycle-discounts' ); ?></strong> -
				<?php esc_html_e( 'Discount high-margin categories more aggressively than low-margin ones', 'smart-cycle-discounts' ); ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'Inventory Turnover', 'smart-cycle-discounts' ); ?></strong> -
				<?php esc_html_e( 'Target slow-moving categories to free up warehouse space', 'smart-cycle-discounts' ); ?>
			</li>
		</ul>
		<?php
		$this->render_section(
			__( 'Category Selection Strategies', 'smart-cycle-discounts' ),
			'category',
			ob_get_clean(),
			'collapsed'
		);

		// Common Mistakes to Avoid section
		ob_start();
		?>
		<div class="scd-callouts">
			<div class="scd-callout scd-callout--warning">
				<div class="scd-callout-icon">
					<?php echo SCD_Icon_Helper::get( 'close', array( 'size' => 16 ) ); ?>
				</div>
				<div class="scd-callout-content">
					<h4><?php esc_html_e( 'Over-Broad Selection', 'smart-cycle-discounts' ); ?></h4>
					<p><?php esc_html_e( 'Discounting entire catalog too often trains customers to wait for sales', 'smart-cycle-discounts' ); ?></p>
				</div>
			</div>
			<div class="scd-callout scd-callout--warning">
				<div class="scd-callout-icon">
					<?php echo SCD_Icon_Helper::get( 'warning', array( 'size' => 16 ) ); ?>
				</div>
				<div class="scd-callout-content">
					<h4><?php esc_html_e( 'Ignoring Margins', 'smart-cycle-discounts' ); ?></h4>
					<p><?php esc_html_e( 'Always exclude low-margin products using conditions to protect profitability', 'smart-cycle-discounts' ); ?></p>
				</div>
			</div>
			<div class="scd-callout scd-callout--warning">
				<div class="scd-callout-icon">
					<?php echo SCD_Icon_Helper::get( 'close', array( 'size' => 16 ) ); ?>
				</div>
				<div class="scd-callout-content">
					<h4><?php esc_html_e( 'Double-Discounting', 'smart-cycle-discounts' ); ?></h4>
					<p><?php esc_html_e( 'Add condition to exclude products already on sale to prevent margin erosion', 'smart-cycle-discounts' ); ?></p>
				</div>
			</div>
			<div class="scd-callout scd-callout--warning">
				<div class="scd-callout-icon">
					<?php echo SCD_Icon_Helper::get( 'admin-generic', array( 'size' => 16 ) ); ?>
				</div>
				<div class="scd-callout-content">
					<h4><?php esc_html_e( 'Too Many Conditions', 'smart-cycle-discounts' ); ?></h4>
					<p><?php esc_html_e( 'Start simple. Complex conditions can result in zero products matching', 'smart-cycle-discounts' ); ?></p>
				</div>
			</div>
		</div>
		<?php
		$this->render_section(
			__( 'Common Mistakes', 'smart-cycle-discounts' ),
			'warning',
			ob_get_clean(),
			'collapsed'
		);

		// Quick Search Tips section
		ob_start();
		?>
		<div class="scd-search-tips">
			<p><strong><?php esc_html_e( 'Quick Search by:', 'smart-cycle-discounts' ); ?></strong></p>
			<ul>
				<li><?php esc_html_e( 'Product name (searches titles)', 'smart-cycle-discounts' ); ?></li>
				<li><?php esc_html_e( 'SKU (exact or partial match)', 'smart-cycle-discounts' ); ?></li>
				<li><?php esc_html_e( 'Category name (searches all categories)', 'smart-cycle-discounts' ); ?></li>
				<li><?php esc_html_e( 'Product ID for exact matching', 'smart-cycle-discounts' ); ?></li>
			</ul>
			<p class="scd-search-tip">
				<?php echo SCD_Icon_Helper::get( 'info', array( 'size' => 16 ) ); ?>
				<?php esc_html_e( 'Tip: Use wildcards (*) in SKU search for pattern matching', 'smart-cycle-discounts' ); ?>
			</p>
		</div>
		<?php
		$this->render_section(
			__( 'Search Tips', 'smart-cycle-discounts' ),
			'search',
			ob_get_clean(),
			'collapsed'
		);
	}
}
