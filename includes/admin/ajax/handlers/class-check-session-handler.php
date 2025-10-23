<?php
/**
 * Check Session AJAX Handler
 *
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers
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
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Check_Session_Handler extends SCD_Abstract_Ajax_Handler {

	/**
	 * Constructor.
	 *
	 * @since    1.0.0
	 * @param    SCD_Logger    $logger    Logger instance (optional).
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
	 * @param    array    $request    Request data.
	 * @return   array               Response data.
	 */
	protected function handle( $request ) {
		try {
			// Get container
			$container = isset( $GLOBALS['scd_container'] ) ? $GLOBALS['scd_container'] : null;
			if ( ! $container ) {
				throw new Exception( __( 'Service container not initialized', 'smart-cycle-discounts' ) );
			}
			
			// Get wizard session service - Phase 2: Cookie-based session management
			$session_service = $container->get( 'wizard_session' );
			
			// Get session data (service handles cookie-based session ID internally)
			$session_data = $session_service->get_all_data();

			if ( ! $session_data ) {
				return $this->error(
					__( 'Session expired', 'smart-cycle-discounts' ),
					'session_expired',
					401,
					array( 'expired' => true )
				);
			}

			return $this->success( array(
				'valid' => true,
				// Step tracked via URL only - no current_step in session
				'has_data' => ! empty( $session_data )
			) );

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