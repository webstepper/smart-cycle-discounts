<?php
/**
 * Products Step Sidebar
 *
 * Static sidebar content for the products selection step
 *
 * @package SmartCycleDiscounts
 * @since   1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Products step sidebar class
 */
class SCD_Wizard_Sidebar_Products extends SCD_Wizard_Sidebar_Base {
	
	/**
	 * Get sidebar content
	 *
	 * @return string HTML content
	 */
	public function get_content() {
		return $this->render_wrapper(
			__( 'Product Selection Guide', 'smart-cycle-discounts' ),
			__( 'Learn how to choose the right products for your campaign', 'smart-cycle-discounts' )
		);
	}
	
	/**
	 * Render sidebar sections
	 */
	protected function render_sections() {
		// How to Select Products section
		ob_start();
		?>
		<ul class="scd-sidebar-list">
			<li><?php esc_html_e( 'Select individual products for targeted discounts', 'smart-cycle-discounts' ); ?></li>
			<li><?php esc_html_e( 'Choose entire categories to discount all products within', 'smart-cycle-discounts' ); ?></li>
			<li><?php esc_html_e( 'Combine both methods for maximum flexibility', 'smart-cycle-discounts' ); ?></li>
		</ul>
		<?php
		$this->render_section(
			__( 'Selection Methods', 'smart-cycle-discounts' ),
			'products',
			ob_get_clean(),
			'open' // Important selection guidance - keep open
		);
		
		// Search Tips section
		ob_start();
		?>
		<div class="scd-search-tips">
			<p><strong><?php esc_html_e( 'Search by:', 'smart-cycle-discounts' ); ?></strong></p>
			<ul>
				<li><?php esc_html_e( 'Product name or SKU', 'smart-cycle-discounts' ); ?></li>
				<li><?php esc_html_e( 'Category name', 'smart-cycle-discounts' ); ?></li>
				<li><?php esc_html_e( 'Product ID', 'smart-cycle-discounts' ); ?></li>
			</ul>
		</div>
		<?php
		$this->render_section(
			__( 'Search Tips', 'smart-cycle-discounts' ),
			'search',
			ob_get_clean(),
			'collapsed' // Reference material - collapse by default
		);

		// Best Practices section
		ob_start();
		?>
		<ul class="scd-sidebar-tips">
			<li><?php esc_html_e( 'Use categories for broad discounts (e.g., "Summer Sale")', 'smart-cycle-discounts' ); ?></li>
			<li><?php esc_html_e( 'Select specific products for targeted promotions', 'smart-cycle-discounts' ); ?></li>
			<li><?php esc_html_e( 'Review your selections before proceeding', 'smart-cycle-discounts' ); ?></li>
		</ul>
		<?php
		$this->render_section(
			__( 'Best Practices', 'smart-cycle-discounts' ),
			'yes-alt',
			ob_get_clean(),
			'collapsed' // Reference material - collapse by default
		);
	}
}