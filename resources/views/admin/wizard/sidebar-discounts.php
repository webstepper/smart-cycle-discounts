<?php
/**
 * Discounts Step Sidebar
 *
 * Static help content for the discounts configuration step.
 * Provides guidance on discount types, strategies, and best practices.
 *
 * @package SmartCycleDiscounts
 * @since   1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Discounts step sidebar class
 */
class SCD_Wizard_Sidebar_Discounts extends SCD_Wizard_Sidebar_Base {

	/**
	 * Get sidebar content
	 *
	 * @return string HTML content
	 */
	public function get_content() {
		return $this->render_wrapper(
			__( 'Discount Configuration', 'smart-cycle-discounts' ),
			__( 'Tips and best practices for creating effective discount campaigns', 'smart-cycle-discounts' )
		);
	}

	/**
	 * Render sidebar sections
	 */
	protected function render_sections() {
		// Choosing Discount Type section
		ob_start();
		?>
		<div class="scd-help-content">
			<p><?php esc_html_e( 'Select the discount type that best matches your campaign goals:', 'smart-cycle-discounts' ); ?></p>
			<ul class="scd-sidebar-list">
				<li>
					<strong><?php esc_html_e( 'Percentage:', 'smart-cycle-discounts' ); ?></strong>
					<?php esc_html_e( 'Best for broad campaigns. Example: 20% off all items.', 'smart-cycle-discounts' ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( 'Fixed Amount:', 'smart-cycle-discounts' ); ?></strong>
					<?php esc_html_e( 'Good for specific price ranges. Example: $10 off orders over $50.', 'smart-cycle-discounts' ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( 'Spend Threshold:', 'smart-cycle-discounts' ); ?></strong>
					<?php esc_html_e( 'Increases average order value. Example: Spend $100, get 10% off.', 'smart-cycle-discounts' ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( 'BOGO:', 'smart-cycle-discounts' ); ?></strong>
					<?php esc_html_e( 'Drives volume. Example: Buy 2 get 1 free.', 'smart-cycle-discounts' ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( 'Volume Discounts:', 'smart-cycle-discounts' ); ?></strong>
					<?php esc_html_e( 'Rewards higher quantities. Example: Buy 3+ get 10% off each.', 'smart-cycle-discounts' ); ?>
				</li>
			</ul>
		</div>
		<?php
		$this->render_section(
			__( 'Choosing a Discount Type', 'smart-cycle-discounts' ),
			'tag',
			ob_get_clean(),
			'open'
		);

		// Best Practices section
		ob_start();
		?>
		<div class="scd-help-content">
			<ul class="scd-sidebar-list">
				<li>
					<strong><?php esc_html_e( 'Start Conservative:', 'smart-cycle-discounts' ); ?></strong>
					<?php esc_html_e( 'Begin with 10-20% discounts and test customer response before increasing.', 'smart-cycle-discounts' ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( 'Know Your Margins:', 'smart-cycle-discounts' ); ?></strong>
					<?php esc_html_e( 'Never discount below your profit threshold. Use the review step to verify margins.', 'smart-cycle-discounts' ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( 'Set Clear Limits:', 'smart-cycle-discounts' ); ?></strong>
					<?php esc_html_e( 'Use minimum purchase requirements and maximum discount caps to protect profitability.', 'smart-cycle-discounts' ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( 'Exclude Sale Items:', 'smart-cycle-discounts' ); ?></strong>
					<?php esc_html_e( 'Prevent double-discounting by excluding products already on sale.', 'smart-cycle-discounts' ); ?>
				</li>
			</ul>
		</div>
		<?php
		$this->render_section(
			__( 'Best Practices', 'smart-cycle-discounts' ),
			'thumbs-up',
			ob_get_clean(),
			'open'
		);

		// Common Discount Strategies section
		ob_start();
		?>
		<div class="scd-help-content">
			<h4><?php esc_html_e( 'Clearance Sale:', 'smart-cycle-discounts' ); ?></h4>
			<p><?php esc_html_e( '30-50% off slow-moving inventory. Higher discounts drive urgency.', 'smart-cycle-discounts' ); ?></p>

			<h4><?php esc_html_e( 'New Customer Acquisition:', 'smart-cycle-discounts' ); ?></h4>
			<p><?php esc_html_e( '15-20% off first purchase. Lower discounts maintain margins while attracting new customers.', 'smart-cycle-discounts' ); ?></p>

			<h4><?php esc_html_e( 'Seasonal Promotion:', 'smart-cycle-discounts' ); ?></h4>
			<p><?php esc_html_e( '20-30% off seasonal items. Medium discounts balance volume and profit.', 'smart-cycle-discounts' ); ?></p>

			<h4><?php esc_html_e( 'Flash Sale:', 'smart-cycle-discounts' ); ?></h4>
			<p><?php esc_html_e( '25-40% off for 24-48 hours. High discounts + scarcity = urgency.', 'smart-cycle-discounts' ); ?></p>
		</div>
		<?php
		$this->render_section(
			__( 'Common Discount Strategies', 'smart-cycle-discounts' ),
			'lightbulb',
			ob_get_clean(),
			'collapsed'
		);

		// Spend Threshold Best Practices section
		ob_start();
		?>
		<div class="scd-help-content">
			<ul class="scd-sidebar-list">
				<li>
					<strong><?php esc_html_e( 'Set Thresholds 20-30% Above Your AOV:', 'smart-cycle-discounts' ); ?></strong>
					<?php esc_html_e( 'Encourages customers to add more items to reach the discount.', 'smart-cycle-discounts' ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( 'Use Percentage for Scalability:', 'smart-cycle-discounts' ); ?></strong>
					<?php esc_html_e( 'Works across all price points and scales with order value.', 'smart-cycle-discounts' ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( 'Use Fixed for Specific Goals:', 'smart-cycle-discounts' ); ?></strong>
					<?php esc_html_e( 'Best for covering shipping costs or high-value items where percentage seems small.', 'smart-cycle-discounts' ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( 'Create Multiple Tiers:', 'smart-cycle-discounts' ); ?></strong>
					<?php esc_html_e( 'Gives customers incremental targets to reach (e.g., Spend $50 → 5% off, $100 → 10% off, $200 → 15% off).', 'smart-cycle-discounts' ); ?>
				</li>
			</ul>
		</div>
		<?php
		$this->render_section(
			__( 'Spend Threshold Best Practices', 'smart-cycle-discounts' ),
			'money',
			ob_get_clean(),
			'collapsed'
		);

		// Industry Benchmarks section
		ob_start();
		?>
		<div class="scd-help-content">
			<table class="scd-benchmark-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Industry', 'smart-cycle-discounts' ); ?></th>
						<th><?php esc_html_e( 'Typical Range', 'smart-cycle-discounts' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><?php esc_html_e( 'Fashion & Apparel', 'smart-cycle-discounts' ); ?></td>
						<td>20-40%</td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Electronics', 'smart-cycle-discounts' ); ?></td>
						<td>10-25%</td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Home & Garden', 'smart-cycle-discounts' ); ?></td>
						<td>15-30%</td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Groceries & Food', 'smart-cycle-discounts' ); ?></td>
						<td>5-15%</td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Software & Digital', 'smart-cycle-discounts' ); ?></td>
						<td>25-50%</td>
					</tr>
				</tbody>
			</table>
			<p class="scd-benchmark-note">
				<em><?php esc_html_e( 'Note: These are general guidelines. Adjust based on your specific costs and margins.', 'smart-cycle-discounts' ); ?></em>
			</p>
		</div>
		<?php
		$this->render_section(
			__( 'Industry Benchmarks', 'smart-cycle-discounts' ),
			'chart-line',
			ob_get_clean(),
			'collapsed'
		);

		// Common Mistakes section
		ob_start();
		?>
		<div class="scd-help-content">
			<ul class="scd-sidebar-list scd-warning-list">
				<li>
					<span class="dashicons dashicons-no-alt"></span>
					<strong><?php esc_html_e( 'Excessive Discounting:', 'smart-cycle-discounts' ); ?></strong>
					<?php esc_html_e( 'Frequent 50%+ discounts train customers to wait for sales.', 'smart-cycle-discounts' ); ?>
				</li>
				<li>
					<span class="dashicons dashicons-no-alt"></span>
					<strong><?php esc_html_e( 'No Minimum Purchase:', 'smart-cycle-discounts' ); ?></strong>
					<?php esc_html_e( 'Allows discount abuse on single low-value items.', 'smart-cycle-discounts' ); ?>
				</li>
				<li>
					<span class="dashicons dashicons-no-alt"></span>
					<strong><?php esc_html_e( 'Ignoring Product Costs:', 'smart-cycle-discounts' ); ?></strong>
					<?php esc_html_e( 'Can result in selling below cost. Always verify margins in the review step.', 'smart-cycle-discounts' ); ?>
				</li>
				<li>
					<span class="dashicons dashicons-no-alt"></span>
					<strong><?php esc_html_e( 'Unlimited Stacking:', 'smart-cycle-discounts' ); ?></strong>
					<?php esc_html_e( 'Multiple discounts can combine unexpectedly. Set clear combination rules.', 'smart-cycle-discounts' ); ?>
				</li>
			</ul>
		</div>
		<?php
		$this->render_section(
			__( 'Common Mistakes to Avoid', 'smart-cycle-discounts' ),
			'warning',
			ob_get_clean(),
			'collapsed'
		);

		// Configuration Tips section
		ob_start();
		?>
		<div class="scd-help-content">
			<ul class="scd-sidebar-list">
				<li><?php esc_html_e( 'Test discount amounts before launching to verify margins remain healthy', 'smart-cycle-discounts' ); ?></li>
				<li><?php esc_html_e( 'Use the review step to see profit margin analysis and revenue impact', 'smart-cycle-discounts' ); ?></li>
				<li><?php esc_html_e( 'Consider seasonal factors when setting discount levels', 'smart-cycle-discounts' ); ?></li>
				<li><?php esc_html_e( 'Set usage limits to control campaign costs', 'smart-cycle-discounts' ); ?></li>
				<li><?php esc_html_e( 'Document your discount strategy for future campaign reference', 'smart-cycle-discounts' ); ?></li>
			</ul>
		</div>
		<?php
		$this->render_section(
			__( 'Configuration Tips', 'smart-cycle-discounts' ),
			'admin-settings',
			ob_get_clean(),
			'collapsed'
		);
	}
}
