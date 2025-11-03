<?php
/**
 * Products Step Sidebar Class
 *
 * Provides contextual help and guidance for the products selection step.
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
			__( 'Product Selection', 'smart-cycle-discounts' ),
			__( 'Choose which products to include in your campaign', 'smart-cycle-discounts' )
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
		<ul class="scd-sidebar-list">
			<li><?php esc_html_e( 'All Products - Apply to entire catalog', 'smart-cycle-discounts' ); ?></li>
			<li><?php esc_html_e( 'Categories - Target product groups', 'smart-cycle-discounts' ); ?></li>
			<li><?php esc_html_e( 'Specific Products - Precise control', 'smart-cycle-discounts' ); ?></li>
			<li><?php esc_html_e( 'Smart Filters - Use conditions for dynamic selection', 'smart-cycle-discounts' ); ?></li>
		</ul>
		<?php
		$this->render_section(
			__( 'Selection Methods', 'smart-cycle-discounts' ),
			'products',
			ob_get_clean(),
			'open'
		);

		// Smart Tips section
		ob_start();
		?>
		<ul class="scd-sidebar-tips">
			<li><?php esc_html_e( 'Categories - Best for broad seasonal sales', 'smart-cycle-discounts' ); ?></li>
			<li><?php esc_html_e( 'Specific Products - Ideal for clearance or featured items', 'smart-cycle-discounts' ); ?></li>
			<li><?php esc_html_e( 'Use conditions to exclude sale items or low-margin products', 'smart-cycle-discounts' ); ?></li>
			<li><?php esc_html_e( 'Combine methods for maximum flexibility', 'smart-cycle-discounts' ); ?></li>
		</ul>
		<?php
		$this->render_section(
			__( 'Selection Tips', 'smart-cycle-discounts' ),
			'lightbulb',
			ob_get_clean(),
			'open'
		);

		// Search Tips section
		ob_start();
		?>
		<div class="scd-search-tips">
			<p><strong><?php esc_html_e( 'Quick Search by:', 'smart-cycle-discounts' ); ?></strong></p>
			<ul>
				<li><?php esc_html_e( 'Product name or SKU', 'smart-cycle-discounts' ); ?></li>
				<li><?php esc_html_e( 'Category name', 'smart-cycle-discounts' ); ?></li>
				<li><?php esc_html_e( 'Product ID (exact match)', 'smart-cycle-discounts' ); ?></li>
			</ul>
		</div>
		<?php
		$this->render_section(
			__( 'Search', 'smart-cycle-discounts' ),
			'search',
			ob_get_clean(),
			'collapsed'
		);
	}
}
