<?php
/**
 * Base Sidebar Class
 *
 * Abstract base class for all wizard sidebars
 *
 * @package SmartCycleDiscounts
 * @since   1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract class SCD_Wizard_Sidebar_Base
 *
 * Provides common functionality for all sidebar implementations
 */
abstract class SCD_Wizard_Sidebar_Base {

	/**
	 * Get the sidebar content
	 *
	 * @return string HTML content
	 */
	abstract public function get_content();

	/**
	 * Render the sidebar wrapper
	 *
	 * @param string $title       The sidebar title
	 * @param string $description Optional sidebar description
	 * @return string HTML content
	 */
	protected function render_wrapper( $title = '', $description = '' ) {
		ob_start();
		?>
		<div class="scd-sidebar-panel">
			<div class="scd-sidebar-content">
				<?php if ( ! empty( $title ) ) : ?>
					<div class="scd-sidebar-header">
						<h3 class="scd-sidebar-header-title">
							<span class="dashicons dashicons-info-outline"></span>
							<?php echo esc_html( $title ); ?>
						</h3>
						<?php if ( ! empty( $description ) ) : ?>
							<p class="scd-sidebar-header-description"><?php echo esc_html( $description ); ?></p>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<?php $this->render_sections(); ?>

				<?php $this->render_footer(); ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render sidebar sections
	 * Override in child classes to add specific sections
	 */
	protected function render_sections() {
		// To be implemented by child classes
	}

	/**
	 * Render the help footer
	 */
	protected function render_footer() {
		// Only show if documentation page exists
		if ( ! $this->has_documentation_page() ) {
			return;
		}
		?>
		<div class="scd-sidebar-footer">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=scd-documentation' ) ); ?>"
				class="scd-sidebar-link"
				target="_blank">
				<span class="dashicons dashicons-book"></span>
				<?php esc_html_e( 'View Full Documentation', 'smart-cycle-discounts' ); ?>
			</a>
		</div>
		<?php
	}

	/**
	 * Check if documentation page exists
	 *
	 * @return bool
	 */
	private function has_documentation_page() {
		// Check if documentation page is registered
		global $submenu;

		if ( ! isset( $submenu['scd-campaigns'] ) ) {
			return false;
		}

		foreach ( $submenu['scd-campaigns'] as $item ) {
			if ( isset( $item[2] ) && $item[2] === 'scd-documentation' ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Render a help section
	 *
	 * @param string $title         Section title
	 * @param string $icon          Dashicon name (without 'dashicons-' prefix)
	 * @param string $content       Section content HTML
	 * @param string $default_state Default state: 'open' or 'collapsed' (default: 'open')
	 */
	protected function render_section( $title, $icon, $content, $default_state = 'open' ) {
		// Generate unique section ID from title
		$section_id = 'scd-sidebar-' . sanitize_title_with_dashes( $title );

		?>
		<div id="<?php echo esc_attr( $section_id ); ?>"
			class="scd-sidebar-section"
			data-default-state="<?php echo esc_attr( $default_state ); ?>">
			<h4 class="scd-sidebar-section-header"
				tabindex="0"
				role="button"
				aria-expanded="true"
				aria-controls="<?php echo esc_attr( $section_id ); ?>-content">
				<button type="button"
						class="scd-sidebar-section-toggle"
						tabindex="-1"
						aria-hidden="true">
					<span class="dashicons dashicons-arrow-down"></span>
				</button>
				<span class="scd-sidebar-section-icon dashicons dashicons-<?php echo esc_attr( $icon ); ?>"></span>
				<span class="scd-sidebar-section-title"><?php echo esc_html( $title ); ?></span>
			</h4>
			<div id="<?php echo esc_attr( $section_id ); ?>-content" class="scd-sidebar-section-content">
				<?php echo wp_kses_post( $content ); ?>
			</div>
		</div>
		<?php
	}
}
