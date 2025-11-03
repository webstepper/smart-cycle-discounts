<?php
/**
 * Process Queue Handler Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers/class-process-queue-handler.php
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
 * Process Queue Handler Class
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Process_Queue_Handler extends SCD_Abstract_Ajax_Handler {

	/**
	 * Get AJAX action name.
	 *
	 * @since    1.0.0
	 * @return   string    Action name.
	 */
	protected function get_action_name() {
		return 'scd_process_queue';
	}

	/**
	 * Handle the request.
	 *
	 * @since    1.0.0
	 * @param    array $request    Request data.
	 * @return   array                Response data.
	 */
	protected function handle( $request ) {
		try {
			// Verify user has permission
			if ( ! current_user_can( 'manage_options' ) ) {
				throw new Exception( __( 'You do not have permission to perform this action', 'smart-cycle-discounts' ) );
			}

			$container = isset( $GLOBALS['scd_container'] ) ? $GLOBALS['scd_container'] : null;
			if ( ! $container ) {
				throw new Exception( __( 'Service container not initialized', 'smart-cycle-discounts' ) );
			}

			$action_scheduler = $container->get( 'action_scheduler' );
			if ( ! $action_scheduler ) {
				throw new Exception( __( 'Action scheduler service not available', 'smart-cycle-discounts' ) );
			}

			$logger = $container->get( 'logger' );

			$processed = 0;
			$failed    = 0;

			$pending_actions = $action_scheduler->get_actions(
				array(
					'hook'     => 'scd_process_email_queue',
					'status'   => ActionScheduler_Store::STATUS_PENDING,
					'per_page' => 50,
				)
			);

			foreach ( $pending_actions as $action_id ) {
				try {
					// Run the action immediately
					do_action( 'scd_process_email_queue' );
					++$processed;
				} catch ( Exception $e ) {
					++$failed;
					$logger->error(
						'Failed to process queue action',
						array(
							'action_id' => $action_id,
							'error'     => $e->getMessage(),
						)
					);
				}
			}

			$logger->info(
				'Queue processed manually',
				array(
					'processed' => $processed,
					'failed'    => $failed,
				)
			);

			return $this->success(
				array(
					'message'   => sprintf(
					/* translators: %1$d: processed count, %2$d: failed count */
						__( 'Processed %1$d email(s), %2$d failed', 'smart-cycle-discounts' ),
						$processed,
						$failed
					),
					'processed' => $processed,
					'failed'    => $failed,
				)
			);

		} catch ( Exception $e ) {
			if ( isset( $logger ) ) {
				$logger->error(
					'Failed to process queue',
					array(
						'error' => $e->getMessage(),
					)
				);
			}

			return $this->error(
				$e->getMessage(),
				'process_queue_failed',
				400
			);
		}
	}
}
