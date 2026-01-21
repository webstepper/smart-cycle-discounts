<?php
/**
 * Session Status Handler Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers/class-session-status-handler.php
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
 * Session Status Handler Class
 *
 * Handles session status and expiration info requests.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers
 * @author     Webstepper <contact@webstepper.io>
 */
class WSSCD_Session_Status_Handler extends WSSCD_Abstract_Ajax_Handler {

	/**
	 * Session service instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Session_Service    $session_service    Session service instance.
	 */
	private $session_service;

	/**
	 * Constructor.
	 *
	 * @since    1.0.0
	 * @param    WSSCD_Session_Service $session_service    Session service instance (optional).
	 * @param    WSSCD_Logger          $logger             Logger instance (optional).
	 */
	public function __construct( $session_service = null, $logger = null ) {
		parent::__construct( $logger );
		$this->session_service = $session_service;
	}

	/**
	 * Get AJAX action name.
	 *
	 * @since    1.0.0
	 * @return   string    Action name.
	 */
	protected function get_action_name() {
		return 'wsscd_session_status';
	}

	/**
	 * Handle the session status request.
	 *
	 * @since    1.0.0
	 * @param    array $request    Request data.
	 * @return   array                Response data.
	 */
	protected function handle( $request ) {
		$session_service = $this->get_session_service();

		if ( ! $session_service ) {
			// Service not available - this is an actual error
			return array(
				'success'        => false,
				'session_exists' => false,
				'message'        => __( 'Session service not available', 'smart-cycle-discounts' ),
			);
		}

		$expiration_info = $session_service->get_expiration_info();

		if ( ! $expiration_info ) {
			// No session - this is a valid state (e.g., fresh wizard load)
			return array(
				'success'        => true,
				'session_exists' => false,
				'message'        => __( 'No active session', 'smart-cycle-discounts' ),
			);
		}

		return array(
			'success'         => true,
			'session_exists'  => true,
			'expiration_info' => $expiration_info,
			'message'         => __( 'Session status retrieved', 'smart-cycle-discounts' ),
		);
	}

	/**
	 * Get session service instance
	 *
	 * @since    1.0.0
	 * @return   object|null    Session service instance
	 */
	private function get_session_service() {
		// Use injected instance if available
		if ( $this->session_service ) {
			return $this->session_service;
		}

		// Fallback to service locator
		return Smart_Cycle_Discounts::get_service( 'session_service' );
	}
}
