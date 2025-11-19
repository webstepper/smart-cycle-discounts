<?php
/**
 * Check Session Handler Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers/class-check-session-handler.php
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
 * Check Session Handler Class
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers
 * @author     Webstepper <contact@webstepper.io>
 */
class SCD_Check_Session_Handler extends SCD_Abstract_Ajax_Handler {

	/**
	 * Constructor.
	 *
	 * @since    1.0.0
	 * @param    SCD_Logger $logger    Logger instance (optional).
	 */
	public function __construct( $logger = null ) {
		parent::__construct( $logger );
	}

	/**
	 * Get AJAX action name.
	 *
	 * @since    1.0.0
	 * @return   string    Action name.
	 */
	protected function get_action_name() {
		return 'scd_check_session';
	}

	/**
	 * Handle the request
	 *
	 * @since    1.0.0
	 * @param    array $request    Request data.
	 * @return   array               Response data.
	 */
	protected function handle( $request ) {
		try {
			$container = isset( $GLOBALS['scd_container'] ) ? $GLOBALS['scd_container'] : null;
			if ( ! $container ) {
				throw new Exception( __( 'Service container not initialized', 'smart-cycle-discounts' ) );
			}

			$session_service = $container->get( 'wizard_session' );

			$session_data = $session_service->get_all_data();

			if ( ! $session_data ) {
				return $this->error(
					__( 'Session expired', 'smart-cycle-discounts' ),
					'session_expired',
					401,
					array( 'expired' => true )
				);
			}

			return $this->success(
				array(
					'valid'    => true,
					// Step tracked via URL only - no current_step in session
					'has_data' => ! empty( $session_data ),
				)
			);

		} catch ( Exception $e ) {
			return $this->error(
				$e->getMessage(),
				'session_error',
				500,
				array( 'expired' => true )
			);
		}
	}
}
