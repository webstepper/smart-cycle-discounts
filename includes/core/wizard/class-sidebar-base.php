<?php
/**
 * Sidebar Base Class
 *
 * Abstract base class for wizard step sidebars.
 * Supports the contextual sidebar system for focus-aware help.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/wizard
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress/plugins/smart-cycle-discounts/
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract class WSSCD_Wizard_Sidebar_Base
 *
 * Provides common functionality for all sidebar implementations.
 * Uses the contextual sidebar system by default.
 *
 * @since 1.0.0
 */
abstract class WSSCD_Wizard_Sidebar_Base {

	/**
	 * Step identifier
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	protected $step = '';

	/**
	 * Step data for contextual rendering
	 *
	 * @since 1.0.0
	 * @var   array
	 */
	protected $step_data = array();

	/**
	 * Use contextual sidebar system
	 *
	 * @since 1.0.0
	 * @var   bool
	 */
	protected $use_contextual = true;

	/**
	 * Get the sidebar content
	 *
	 * @since  1.0.0
	 * @return string HTML content
	 */
	public function get_content() {
		if ( $this->use_contextual && class_exists( 'WSSCD_Sidebar_Renderer' ) ) {
			return $this->get_contextual_sidebar();
		}

		return '';
	}

	/**
	 * Get contextual sidebar using the contextual system
	 *
	 * @since  1.0.0
	 * @return string HTML content
	 */
	protected function get_contextual_sidebar() {
		if ( ! class_exists( 'WSSCD_Sidebar_Renderer' ) ) {
			return '';
		}

		return WSSCD_Sidebar_Renderer::render_complete_sidebar(
			$this->step,
			$this->step_data
		);
	}

	/**
	 * Set step identifier
	 *
	 * @since  1.0.0
	 * @param  string $step Step identifier.
	 * @return void
	 */
	public function set_step( $step ) {
		$this->step = $step;
	}

	/**
	 * Set step data for contextual rendering
	 *
	 * @since  1.0.0
	 * @param  array $data Step data.
	 * @return void
	 */
	public function set_step_data( $data ) {
		$this->step_data = is_array( $data ) ? $data : array();
	}
}
