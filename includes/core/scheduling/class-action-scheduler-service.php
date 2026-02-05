<?php
/**
 * Action Scheduler Service Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/scheduling/class-action-scheduler-service.php
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
 * ActionScheduler Service Class
 *
 * Manages scheduled actions using WooCommerce ActionScheduler.
 * Provides automatic retries, monitoring, and reliable execution even for missed events.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/scheduling
 * @author     Webstepper <contact@webstepper.io>
 */
class WSSCD_Action_Scheduler_Service {

	/**
	 * Logger instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Logger|null    $logger    Logger instance.
	 */
	private ?WSSCD_Logger $logger;

	/**
	 * Action group for all plugin actions.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $action_group    Action group identifier.
	 */
	private string $action_group = 'wsscd_actions';

	/**
	 * Initialize the service.
	 *
	 * @since    1.0.0
	 * @param    WSSCD_Logger|null $logger    Logger instance.
	 */
	public function __construct( ?WSSCD_Logger $logger = null ) {
		$this->logger = $logger;
	}

	/**
	 * Check if ActionScheduler is available.
	 *
	 * @since    1.0.0
	 * @return   bool    True if ActionScheduler is available.
	 */
	public function is_available(): bool {
		return function_exists( 'as_schedule_single_action' ) && function_exists( 'as_schedule_recurring_action' );
	}

	/**
	 * Schedule a single action to run at a specific time.
	 *
	 * @since    1.0.0
	 * @param    int    $timestamp    Unix timestamp when action should run.
	 * @param    string $hook         Action hook name.
	 * @param    array  $args         Arguments to pass to the action.
	 * @param    string $group        Optional. Action group (defaults to plugin group).
	 * @return   int|bool                Action ID on success, false on failure.
	 */
	public function schedule_single_action( int $timestamp, string $hook, array $args = array(), string $group = '' ) {
		if ( ! $this->is_available() ) {
			if ( $this->logger ) {
				$this->logger->error(
					'ActionScheduler not available',
					array(
						'hook' => $hook,
					)
				);
			}
			return false;
		}

		$group = ! empty( $group ) ? $group : $this->action_group;

		try {
			if ( $this->is_action_scheduled( $hook, $args, $group ) ) {
				if ( $this->logger ) {
					$this->logger->debug(
						'Action already scheduled',
						array(
							'hook'      => $hook,
							'timestamp' => gmdate( 'Y-m-d H:i:s', $timestamp ),
						)
					);
				}
				return false;
			}

			$action_id = as_schedule_single_action( $timestamp, $hook, $args, $group );

			if ( $this->logger ) {
				$this->logger->info(
					'Scheduled single action',
					array(
						'action_id' => $action_id,
						'hook'      => $hook,
						'timestamp' => gmdate( 'Y-m-d H:i:s', $timestamp ),
						'args'      => $args,
					)
				);
			}

			return $action_id;

		} catch ( Exception $e ) {
			if ( $this->logger ) {
				$this->logger->error(
					'Failed to schedule action',
					array(
						'hook'  => $hook,
						'error' => $e->getMessage(),
					)
				);
			}
			return false;
		}
	}

	/**
	 * Schedule a recurring action.
	 *
	 * @since    1.0.0
	 * @param    int    $timestamp    Unix timestamp for first occurrence.
	 * @param    int    $interval     Interval in seconds between occurrences.
	 * @param    string $hook         Action hook name.
	 * @param    array  $args         Arguments to pass to the action.
	 * @param    string $group        Optional. Action group (defaults to plugin group).
	 * @return   int|bool                Action ID on success, false on failure.
	 */
	public function schedule_recurring_action( int $timestamp, int $interval, string $hook, array $args = array(), string $group = '' ) {
		if ( ! $this->is_available() ) {
			if ( $this->logger ) {
				$this->logger->error(
					'ActionScheduler not available',
					array(
						'hook' => $hook,
					)
				);
			}
			return false;
		}

		$group = ! empty( $group ) ? $group : $this->action_group;

		try {
			if ( $this->is_action_scheduled( $hook, $args, $group ) ) {
				// Already scheduled - no need to log (expected behavior)
				return false;
			}

			$action_id = as_schedule_recurring_action( $timestamp, $interval, $hook, $args, $group );

			if ( $this->logger ) {
				$this->logger->info(
					'Scheduled recurring action',
					array(
						'action_id' => $action_id,
						'hook'      => $hook,
						'timestamp' => gmdate( 'Y-m-d H:i:s', $timestamp ),
						'interval'  => $interval,
						'args'      => $args,
					)
				);
			}

			return $action_id;

		} catch ( Exception $e ) {
			if ( $this->logger ) {
				$this->logger->error(
					'Failed to schedule recurring action',
					array(
						'hook'  => $hook,
						'error' => $e->getMessage(),
					)
				);
			}
			return false;
		}
	}

	/**
	 * Schedule a cron action (recurring with cron expression support).
	 *
	 * @since    1.0.0
	 * @param    int    $timestamp    Unix timestamp for first occurrence.
	 * @param    string $schedule     Cron schedule name (hourly, daily, etc.).
	 * @param    string $hook         Action hook name.
	 * @param    array  $args         Arguments to pass to the action.
	 * @param    string $group        Optional. Action group (defaults to plugin group).
	 * @return   int|bool                Action ID on success, false on failure.
	 */
	public function schedule_cron_action( int $timestamp, string $schedule, string $hook, array $args = array(), string $group = '' ) {
		if ( ! $this->is_available() ) {
			return false;
		}

		$group = ! empty( $group ) ? $group : $this->action_group;

		try {
			if ( $this->is_action_scheduled( $hook, $args, $group ) ) {
				return false;
			}

			$action_id = as_schedule_cron_action( $timestamp, $schedule, $hook, $args, $group );

			if ( $this->logger ) {
				$this->logger->info(
					'Scheduled cron action',
					array(
						'action_id' => $action_id,
						'hook'      => $hook,
						'schedule'  => $schedule,
						'args'      => $args,
					)
				);
			}

			return $action_id;

		} catch ( Exception $e ) {
			if ( $this->logger ) {
				$this->logger->error(
					'Failed to schedule cron action',
					array(
						'hook'  => $hook,
						'error' => $e->getMessage(),
					)
				);
			}
			return false;
		}
	}

	/**
	 * Check if an action is scheduled.
	 *
	 * @since    1.0.0
	 * @param    string $hook     Action hook name.
	 * @param    array  $args     Arguments to check.
	 * @param    string $group    Optional. Action group.
	 * @return   bool                True if action is scheduled.
	 */
	public function is_action_scheduled( string $hook, array $args = array(), string $group = '' ): bool {
		if ( ! $this->is_available() ) {
			return false;
		}

		$group = ! empty( $group ) ? $group : $this->action_group;

		return false !== as_next_scheduled_action( $hook, $args, $group );
	}

	/**
	 * Get next scheduled time for an action.
	 *
	 * @since    1.0.0
	 * @param    string $hook     Action hook name.
	 * @param    array  $args     Arguments to check.
	 * @param    string $group    Optional. Action group.
	 * @return   int|bool            Unix timestamp or false if not scheduled.
	 */
	public function get_next_scheduled_action( string $hook, array $args = array(), string $group = '' ) {
		if ( ! $this->is_available() ) {
			return false;
		}

		$group = ! empty( $group ) ? $group : $this->action_group;

		$next_scheduled = as_next_scheduled_action( $hook, $args, $group );

		// as_next_scheduled_action() can return null in some cases
		if ( null === $next_scheduled ) {
			return false;
		}

		return $next_scheduled;
	}

	/**
	 * Unschedule a specific action.
	 *
	 * @since    1.0.0
	 * @param    string $hook     Action hook name.
	 * @param    array  $args     Arguments to match.
	 * @param    string $group    Optional. Action group.
	 * @return   int|bool            Number of actions unscheduled, or false on failure.
	 */
	public function unschedule_action( string $hook, array $args = array(), string $group = '' ) {
		if ( ! $this->is_available() ) {
			return false;
		}

		$group = ! empty( $group ) ? $group : $this->action_group;

		try {
			$unscheduled = as_unschedule_action( $hook, $args, $group );

			// as_unschedule_action() can return null when no actions found
			if ( null === $unscheduled ) {
				$unscheduled = 0;
			}

			if ( $this->logger && $unscheduled ) {
				$this->logger->info(
					'Unscheduled action',
					array(
						'hook'        => $hook,
						'args'        => $args,
						'unscheduled' => $unscheduled,
					)
				);
			}

			return $unscheduled;

		} catch ( Exception $e ) {
			if ( $this->logger ) {
				$this->logger->error(
					'Failed to unschedule action',
					array(
						'hook'  => $hook,
						'error' => $e->getMessage(),
					)
				);
			}
			return false;
		}
	}

	/**
	 * Unschedule all actions for a hook.
	 *
	 * @since    1.0.0
	 * @param    string $hook     Action hook name.
	 * @param    string $group    Optional. Action group.
	 * @return   int|bool            Number of actions unscheduled, or false on failure.
	 */
	public function unschedule_all_actions( string $hook, string $group = '' ) {
		if ( ! $this->is_available() ) {
			return false;
		}

		$group = ! empty( $group ) ? $group : $this->action_group;

		try {
			$unscheduled = as_unschedule_all_actions( $hook, array(), $group );

			// as_unschedule_all_actions() can return null when no actions found
			if ( null === $unscheduled ) {
				$unscheduled = 0;
			}

			if ( $this->logger ) {
				$this->logger->info(
					'Unscheduled all actions for hook',
					array(
						'hook'        => $hook,
						'unscheduled' => $unscheduled,
					)
				);
			}

			return $unscheduled;

		} catch ( Exception $e ) {
			if ( $this->logger ) {
				$this->logger->error(
					'Failed to unschedule all actions',
					array(
						'hook'  => $hook,
						'error' => $e->getMessage(),
					)
				);
			}
			return false;
		}
	}

	/**
	 * Get all pending actions for a hook.
	 *
	 * @since    1.0.0
	 * @param    string $hook     Action hook name.
	 * @param    string $group    Optional. Action group.
	 * @return   array               Array of action IDs.
	 */
	public function get_pending_actions( string $hook, string $group = '' ): array {
		if ( ! $this->is_available() ) {
			return array();
		}

		$group = ! empty( $group ) ? $group : $this->action_group;

		$actions = as_get_scheduled_actions(
			array(
				'hook'   => $hook,
				'group'  => $group,
				'status' => 'pending',
			),
			'ids'
		);

		return is_array( $actions ) ? $actions : array();
	}

	/**
	 * Cancel all actions in the plugin's action group.
	 *
	 * Useful for plugin deactivation.
	 *
	 * @since    1.0.0
	 * @return   int    Number of actions cancelled.
	 */
	public function cancel_all_plugin_actions(): int {
		if ( ! $this->is_available() ) {
			return 0;
		}

		try {
			$actions = as_get_scheduled_actions(
				array(
					'group'  => $this->action_group,
					'status' => 'pending',
				),
				'ids'
			);

			$cancelled = 0;

			if ( is_array( $actions ) && ! empty( $actions ) ) {
				foreach ( $actions as $action_id ) {
					as_unschedule_action( '', array(), $this->action_group );
					++$cancelled;
				}
			}

			if ( $this->logger ) {
				$this->logger->info(
					'Cancelled all plugin actions',
					array(
						'cancelled' => $cancelled,
					)
				);
			}

			return $cancelled;

		} catch ( Exception $e ) {
			if ( $this->logger ) {
				$this->logger->error(
					'Failed to cancel plugin actions',
					array(
						'error' => $e->getMessage(),
					)
				);
			}
			return 0;
		}
	}

	/**
	 * Get the action group identifier.
	 *
	 * @since    1.0.0
	 * @return   string    Action group name.
	 */
	public function get_action_group(): string {
		return $this->action_group;
	}
}
