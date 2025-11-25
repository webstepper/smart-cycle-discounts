<?php
/**
 * Sidebar Base Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/wizard/class-sidebar-base.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract class SCD_Wizard_Sidebar_Base
 *
 * Provides common functionality for all sidebar implementations.
 * Now supports both legacy static sidebars and new contextual sidebar system.
 */
abstract class SCD_Wizard_Sidebar_Base {

	/**
	 * Step identifier
	 *
	 * @var string
	 */
	protected $step = '';

	/**
	 * Step data for contextual rendering
	 *
	 * @var array
	 */
	protected $step_data = array();

	/**
	 * Use contextual sidebar system
	 *
	 * @var bool
	 */
	protected $use_contextual = true;

	/**
	 * Get the sidebar content
	 *
	 * @return string HTML content
	 */
	public function get_content() {

		// Use new contextual sidebar system if enabled
		if ( $this->use_contextual && class_exists( 'SCD_Sidebar_Renderer' ) ) {
			return $this->get_contextual_sidebar();
		}

		// Fallback to legacy sidebar rendering
		return $this->get_legacy_content();
	}

	/**
	 * Get legacy static sidebar content
	 * Override in child classes for legacy mode
	 *
	 * @return string HTML content
	 */
	protected function get_legacy_content() {
		return $this->render_wrapper(
			'',
			''
		);
	}

	/**
	 * Get contextual sidebar using new system
	 *
	 * @return string HTML content
	 */
	protected function get_contextual_sidebar() {

		if ( ! class_exists( 'SCD_Sidebar_Renderer' ) ) {
			return '';
		}

		$html = SCD_Sidebar_Renderer::render_complete_sidebar(
			$this->step,
			$this->step_data
		);


		return $html;
	}

	/**
	 * Set step identifier
	 *
	 * @param string $step Step identifier.
	 * @return void
	 */
	public function set_step( $step ) {
		$this->step = $step;
	}

	/**
	 * Set step data for contextual rendering
	 *
	 * @param array $data Step data.
	 * @return void
	 */
	public function set_step_data( $data ) {
		$this->step_data = is_array( $data ) ? $data : array();
	}

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
							<?php echo SCD_Icon_Helper::get( 'info', array( 'size' => 16 ) ); ?>
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
				<?php echo SCD_Icon_Helper::get( 'book', array( 'size' => 16 ) ); ?>
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
				<?php echo SCD_Icon_Helper::get( $icon, array( 'size' => 16, 'class' => 'scd-sidebar-section-icon' ) ); ?>
				<span class="scd-sidebar-section-title"><?php echo esc_html( $title ); ?></span>
			</h4>
			<div id="<?php echo esc_attr( $section_id ); ?>-content" class="scd-sidebar-section-content">
				<?php echo wp_kses_post( $content ); ?>
			</div>
		</div>
		<?php
	}
}
