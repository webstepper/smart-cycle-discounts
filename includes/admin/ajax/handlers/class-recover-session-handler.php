<?php
/**
 * Recover Session Handler Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers/class-recover-session-handler.php
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
 * Recover Session Handler Class
 *
 * Handles session recovery from local storage backup.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Recover_Session_Handler extends SCD_Abstract_Ajax_Handler {

	/**
	 * State service instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Wizard_State_Service    $state_service    State service.
	 */
	private $state_service;

	/**
	 * Constructor.
	 *
	 * @since    1.0.0
	 * @param    SCD_Wizard_State_Service $state_service    State service instance.
	 * @param    SCD_Logger               $logger           Logger instance (optional).
	 */
	public function __construct( $state_service, $logger = null ) {
		parent::__construct( $logger );
		$this->state_service = $state_service;
	}

	/**
	 * Get AJAX action name.
	 *
	 * @since    1.0.0
	 * @return   string    Action name.
	 */
	protected function get_action_name() {
		return 'scd_recover_session';
	}

	/**
	 * Handle the request.
	 *
	 * @since    1.0.0
	 * @param    array $request    Request data.
	 * @return   array               Response data.
	 */
	protected function handle( $request ) {
		$recovery_data = isset( $request['recovery_data'] ) ? $request['recovery_data'] : null;

		if ( ! $recovery_data || ! is_array( $recovery_data ) ) {
			return $this->error(
				__( 'Invalid recovery data provided', 'smart-cycle-discounts' ),
				'invalid_recovery_data',
				400
			);
		}

		if ( ! isset( $recovery_data['steps'] ) || ! is_array( $recovery_data['steps'] ) ) {
			return $this->error(
				__( 'Recovery data is missing required information', 'smart-cycle-discounts' ),
				'invalid_recovery_structure',
				400
			);
		}

		// Phase 3: Create new session using secure cookie system
		$new_state_service = new SCD_Wizard_State_Service();
		$new_session_id    = $new_state_service->create(); // This sets the secure cookie

		// Restore each step's data
		$restored_steps = array();
		$errors         = array();

		foreach ( $recovery_data['steps'] as $step => $step_data ) {
			if ( ! in_array( $step, array( 'basic', 'products', 'discounts', 'schedule', 'review' ), true ) ) {
				continue;
			}

			try {
				$save_result = $new_state_service->save_step_data( $step, $step_data );

				if ( $save_result ) {
					$restored_steps[] = $step;

					// Mark step as complete if it was complete before
					if ( isset( $recovery_data['completed_steps'] ) &&
						in_array( $step, $recovery_data['completed_steps'], true ) ) {
						$new_state_service->mark_step_complete( $step );
					}
				} else {
					$errors[] = sprintf(
						__( 'Failed to restore %s step data', 'smart-cycle-discounts' ),
						$step
					);
				}
			} catch ( Exception $e ) {
				$errors[] = sprintf(
					__( 'Error restoring %1$s step: %2$s', 'smart-cycle-discounts' ),
					$step,
					$e->getMessage()
				);
			}
		}

		if ( empty( $restored_steps ) ) {
			return $this->error(
				__( 'Failed to restore any session data', 'smart-cycle-discounts' ),
				'recovery_failed',
				500
			);
		}

		$current_step = isset( $recovery_data['current_step'] ) ? $recovery_data['current_step'] : 'basic';
		if ( ! in_array( $current_step, array( 'basic', 'products', 'discounts', 'schedule', 'review' ), true ) ) {
			$current_step = 'basic';
		}

		$redirect_url = add_query_arg(
			array(
				'page'      => 'scd-campaigns',
				'action'    => 'wizard',
				'step'      => $current_step,
				'recovered' => '1',
			),
			admin_url( 'admin.php' )
		);

		// Log recovery
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$this->log_info(
				'Session recovered',
				array(
					'restored_steps' => $restored_steps,
					'errors'         => $errors,
				)
			);

			if ( ! empty( $errors ) ) {
				$this->log_warning( 'Session recovery had errors', array( 'errors' => $errors ) );
			}
		}

		return $this->success(
			array(
				// Phase 2: Session ID handled via secure cookies, not returned to client
				'redirect_url'   => $redirect_url,
				'restored_steps' => $restored_steps,
				'errors'         => $errors,
				'message'        => sprintf(
					__( 'Successfully recovered %1$d of %2$d steps', 'smart-cycle-discounts' ),
					count( $restored_steps ),
					count( $recovery_data['steps'] )
				),
			)
		);
	}

	/**
	 * Generate new session ID.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   string    New session ID.
	 */
	// Phase 3: generate_session_id removed - session management handled by SCD_Wizard_State_Service via secure cookies
}
