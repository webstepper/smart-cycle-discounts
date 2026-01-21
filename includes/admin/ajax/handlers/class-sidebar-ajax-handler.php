<?php
/**
 * Sidebar AJAX Handler
 *
 * Handles AJAX requests for contextual sidebar system.
 * Provides help topics, progress updates, and template loading.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sidebar AJAX Handler
 *
 * @since 1.0.0
 */
class WSSCD_Sidebar_Ajax_Handler {

	/**
	 * Handle AJAX request
	 *
	 * @since  1.0.0
	 * @param  array $request_data Request data.
	 * @return array               Response data
	 */
	public function handle( array $request_data ) {

		// Get action from request data (set by AJAX router)
		$action = isset( $request_data['action'] ) ? $request_data['action'] : '';

		// Only handle help topic requests
		if ( 'get_help_topic' === $action ) {
			return $this->get_help_topic( $request_data );
		}

		return array(
			'success' => false,
			'message' => __( 'Invalid action', 'smart-cycle-discounts' ),
		);
	}

	/**
	 * Get help topic content
	 *
	 * @since  1.0.0
	 * @param  array $request_data Request data.
	 * @return array               Response data
	 */
	private function get_help_topic( $request_data ) {

		$topic_id = isset( $request_data['topic_id'] ) ? sanitize_key( $request_data['topic_id'] ) : '';

		if ( empty( $topic_id ) ) {
			return array(
				'success' => false,
				'message' => __( 'Topic ID required', 'smart-cycle-discounts' ),
			);
		}

		$topic = WSSCD_Sidebar_Help_Topics::get_topic( $topic_id );

		if ( ! $topic ) {
			return array(
				'success' => false,
				'message' => __( 'Topic not found', 'smart-cycle-discounts' ),
			);
		}

		$html = WSSCD_Sidebar_Renderer::render_help_topic( $topic );

		return array(
			'success' => true,
			'data'    => array(
				'html' => $html,
			),
		);
	}

}
