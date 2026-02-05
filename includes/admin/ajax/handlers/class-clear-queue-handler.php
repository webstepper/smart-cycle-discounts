<?php
/**
 * Clear Queue Handler Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers/class-clear-queue-handler.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress/plugins/smart-cycle-discounts/
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Clear Queue Handler Class
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers
 * @author     Webstepper <contact@webstepper.io>
 */
class WSSCD_Clear_Queue_Handler extends WSSCD_Abstract_Ajax_Handler {

	/**
	 * Get AJAX action name.
	 *
	 * @since    1.0.0
	 * @return   string    Action name.
	 */
	protected function get_action_name() {
		return 'wsscd_clear_queue';
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

			$container = isset( $GLOBALS['wsscd_container'] ) ? $GLOBALS['wsscd_container'] : null;
			if ( ! $container ) {
				throw new Exception( __( 'Service container not initialized', 'smart-cycle-discounts' ) );
			}

			$action_scheduler = $container->get( 'action_scheduler' );
			if ( ! $action_scheduler ) {
				throw new Exception( __( 'Action scheduler service not available', 'smart-cycle-discounts' ) );
			}

			$logger = $container->get( 'logger' );

			$thirty_days_ago = strtotime( '-30 days' );

			$completed_actions = $action_scheduler->get_actions(
				array(
					'hook'     => 'wsscd_process_email_queue',
					'status'   => ActionScheduler_Store::STATUS_COMPLETE,
					'date'     => $thirty_days_ago,
					'per_page' => 1000,
				)
			);

			$cleared = 0;

			foreach ( $completed_actions as $action_id ) {
				try {
					ActionScheduler::store()->delete_action( $action_id );
					++$cleared;
				} catch ( Exception $e ) {
					$logger->error(
						'Failed to clear queue action',
						array(
							'action_id' => $action_id,
							'error'     => $e->getMessage(),
						)
					);
				}
			}

			$logger->info(
				'Queue cleared',
				array(
					'cleared' => $cleared,
				)
			);

			return $this->success(
				array(
					'message' => sprintf(
					/* translators: %d: cleared count */
						__( 'Cleared %d old queue item(s)', 'smart-cycle-discounts' ),
						$cleared
					),
					'cleared' => $cleared,
				)
			);

		} catch ( Exception $e ) {
			if ( isset( $logger ) ) {
				$logger->error(
					'Failed to clear queue',
					array(
						'error' => $e->getMessage(),
					)
				);
			}

			return $this->error(
				$e->getMessage(),
				'clear_queue_failed',
				400
			);
		}
	}
}
