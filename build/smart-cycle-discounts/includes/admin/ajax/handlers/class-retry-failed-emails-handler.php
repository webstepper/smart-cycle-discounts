<?php
/**
 * Retry Failed Emails Handler Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers/class-retry-failed-emails-handler.php
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
 * Retry Failed Emails Handler Class
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Retry_Failed_Emails_Handler extends SCD_Abstract_Ajax_Handler {

	/**
	 * Get AJAX action name.
	 *
	 * @since    1.0.0
	 * @return   string    Action name.
	 */
	protected function get_action_name() {
		return 'scd_retry_failed_emails';
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

			// Get container and action scheduler
			$container = isset( $GLOBALS['scd_container'] ) ? $GLOBALS['scd_container'] : null;
			if ( ! $container ) {
				throw new Exception( __( 'Service container not initialized', 'smart-cycle-discounts' ) );
			}

			$action_scheduler = $container->get( 'action_scheduler' );
			if ( ! $action_scheduler ) {
				throw new Exception( __( 'Action scheduler service not available', 'smart-cycle-discounts' ) );
			}

			// Get logger
			$logger = $container->get( 'logger' );

			// Get all failed actions from Action Scheduler
			$failed_actions = $action_scheduler->get_actions(
				array(
					'hook'     => 'scd_process_email_queue',
					'status'   => ActionScheduler_Store::STATUS_FAILED,
					'per_page' => 100,
				)
			);

			$retried = 0;

			foreach ( $failed_actions as $action_id ) {
				try {
					// Reschedule the failed action
					$action_scheduler->schedule_single(
						array(
							'hook' => 'scd_process_email_queue',
							'args' => array(),
							'when' => time(),
						)
					);

					// Mark the old action as complete to prevent retry loops
					// This is handled by Action Scheduler automatically

					++$retried;
				} catch ( Exception $e ) {
					$logger->error(
						'Failed to retry email action',
						array(
							'action_id' => $action_id,
							'error'     => $e->getMessage(),
						)
					);
				}
			}

			$logger->info(
				'Failed emails retried',
				array(
					'retried' => $retried,
				)
			);

			return $this->success(
				array(
					'message' => sprintf(
					/* translators: %d: retry count */
						__( 'Retried %d failed email(s)', 'smart-cycle-discounts' ),
						$retried
					),
					'retried' => $retried,
				)
			);

		} catch ( Exception $e ) {
			if ( isset( $logger ) ) {
				$logger->error(
					'Failed to retry emails',
					array(
						'error' => $e->getMessage(),
					)
				);
			}

			return $this->error(
				$e->getMessage(),
				'retry_failed_emails_failed',
				400
			);
		}
	}
}
