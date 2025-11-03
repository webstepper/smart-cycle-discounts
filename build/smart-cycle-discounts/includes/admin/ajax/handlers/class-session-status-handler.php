<?php
/**
 * Session Status Handler Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers/class-session-status-handler.php
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
 * Session Status Handler Class
 *
 * Handles session status and expiration info requests.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Session_Status_Handler extends SCD_Abstract_Ajax_Handler {

	/**
	 * Session service instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Session_Service    $session_service    Session service instance.
	 */
	private $session_service;

	/**
	 * Constructor.
	 *
	 * @since    1.0.0
	 * @param    SCD_Session_Service $session_service    Session service instance (optional).
	 * @param    SCD_Logger          $logger             Logger instance (optional).
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
		return 'scd_session_status';
	}

	/**
	 * Handle the session status request.
	 *
	 * @since    1.0.0
	 * @param    array $request    Request data.
	 * @return   array                Response data.
	 */
	protected function handle( $request ) {
		// Get session service
		$session_service = $this->get_session_service();

		if ( ! $session_service ) {
			return array(
				'success' => false,
				'message' => __( 'Session service not available', 'smart-cycle-discounts' ),
			);
		}

		// Get expiration info
		$expiration_info = $session_service->get_expiration_info();

		if ( ! $expiration_info ) {
			return array(
				'success'        => false,
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
