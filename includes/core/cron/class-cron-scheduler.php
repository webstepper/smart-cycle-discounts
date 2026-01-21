<?php
/**
 * Cron Scheduler Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/cron/class-cron-scheduler.php
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
 * Cron Scheduler
 *
 * Manages scheduled tasks for the plugin using WooCommerce ActionScheduler.
 * ActionScheduler provides reliable task execution with automatic retries and monitoring.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/cron
 * @author     Webstepper <contact@webstepper.io>
 */
class WSSCD_Cron_Scheduler {

	/**
	 * Customer usage manager.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Customer_Usage_Manager|null    $customer_usage_manager    Customer usage manager.
	 */
	private ?WSSCD_Customer_Usage_Manager $customer_usage_manager = null;

	/**
	 * Logger instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Logger    $logger    Logger instance.
	 */
	private WSSCD_Logger $logger;

	/**
	 * ActionScheduler service.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Action_Scheduler_Service    $scheduler    ActionScheduler service.
	 */
	private WSSCD_Action_Scheduler_Service $scheduler;

	/**
	 * Initialize the scheduler.
	 *
	 * @since    1.0.0
	 * @param    WSSCD_Logger                      $logger                    Logger instance.
	 * @param    WSSCD_Action_Scheduler_Service    $scheduler                 ActionScheduler service.
	 * @param    WSSCD_Customer_Usage_Manager|null $customer_usage_manager    Customer usage manager.
	 */
	public function __construct( WSSCD_Logger $logger, WSSCD_Action_Scheduler_Service $scheduler, ?WSSCD_Customer_Usage_Manager $customer_usage_manager = null ) {
		$this->logger                 = $logger;
		$this->scheduler              = $scheduler;
		$this->customer_usage_manager = $customer_usage_manager;
	}

	/**
	 * Initialize cron hooks.
	 *
	 * Registers action handlers for scheduled tasks.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function init(): void {
		add_action( 'wsscd_cleanup_expired_sessions', array( $this, 'cleanup_expired_sessions' ) );
		add_action( 'wsscd_cleanup_old_analytics', array( $this, 'cleanup_old_analytics' ) );
		add_action( 'wsscd_auto_purge_trash', array( $this, 'auto_purge_trash' ) );

		// Schedule events on activation
		$this->schedule_events();
	}

	/**
	 * Schedule events.
	 *
	 * Sets up all recurring scheduled actions using ActionScheduler.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function schedule_events(): void {
		if ( ! $this->scheduler->is_available() ) {
			$this->logger->error( 'ActionScheduler not available - cannot schedule tasks' );
			return;
		}

		// Schedule campaign status safety check - every 15 minutes
		// Catches campaigns that should have activated/deactivated but missed their event
		if ( ! $this->scheduler->is_action_scheduled( 'wsscd_update_campaign_status' ) ) {
			$this->scheduler->schedule_recurring_action(
				time(),
				15 * MINUTE_IN_SECONDS,
				'wsscd_update_campaign_status',
				array()
			);
			$this->logger->info( 'Scheduled campaign status safety check (every 15 minutes)' );
		}

		// Schedule session cleanup - runs daily
		if ( ! $this->scheduler->is_action_scheduled( 'wsscd_cleanup_expired_sessions' ) ) {
			$this->scheduler->schedule_recurring_action(
				time(),
				DAY_IN_SECONDS,
				'wsscd_cleanup_expired_sessions',
				array()
			);
			$this->logger->info( 'Scheduled expired sessions cleanup (daily)' );
		}

		// Schedule wizard session cleanup - runs daily
		if ( ! $this->scheduler->is_action_scheduled( 'wsscd_cleanup_wizard_sessions' ) ) {
			$this->scheduler->schedule_recurring_action(
				time(),
				DAY_IN_SECONDS,
				'wsscd_cleanup_wizard_sessions',
				array()
			);
			$this->logger->info( 'Scheduled wizard sessions cleanup (daily)' );
		}

		// Schedule audit log cleanup - runs daily
		if ( ! $this->scheduler->is_action_scheduled( 'wsscd_cleanup_audit_logs' ) ) {
			$this->scheduler->schedule_recurring_action(
				time(),
				DAY_IN_SECONDS,
				'wsscd_cleanup_audit_logs',
				array()
			);
			$this->logger->info( 'Scheduled audit logs cleanup (daily)' );
		}

		// Schedule analytics cleanup - runs weekly
		if ( ! $this->scheduler->is_action_scheduled( 'wsscd_cleanup_old_analytics' ) ) {
			$this->scheduler->schedule_recurring_action(
				time(),
				WEEK_IN_SECONDS,
				'wsscd_cleanup_old_analytics',
				array()
			);
			$this->logger->info( 'Scheduled old analytics cleanup (weekly)' );
		}

		// Schedule trash auto-purge - runs daily if enabled in settings
		$settings = get_option( 'wsscd_settings', array() );
		if ( isset( $settings['general']['trash_auto_purge'] ) && $settings['general']['trash_auto_purge'] ) {
			if ( ! $this->scheduler->is_action_scheduled( 'wsscd_auto_purge_trash' ) ) {
				$this->scheduler->schedule_recurring_action(
					time(),
					DAY_IN_SECONDS,
					'wsscd_auto_purge_trash',
					array()
				);
				$this->logger->info( 'Scheduled trash auto-purge (daily)' );
			}
		} else {
			// Unschedule if disabled
			$this->scheduler->unschedule_all_actions( 'wsscd_auto_purge_trash' );
		}
	}

	/**
	 * Unschedule events.
	 *
	 * Removes all scheduled recurring actions.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function unschedule_events(): void {
		if ( ! $this->scheduler->is_available() ) {
			return;
		}

		$this->scheduler->unschedule_all_actions( 'wsscd_update_campaign_status' );
		$this->scheduler->unschedule_all_actions( 'wsscd_cleanup_expired_sessions' );
		$this->scheduler->unschedule_all_actions( 'wsscd_cleanup_wizard_sessions' );
		$this->scheduler->unschedule_all_actions( 'wsscd_cleanup_audit_logs' );
		$this->scheduler->unschedule_all_actions( 'wsscd_cleanup_old_analytics' );
		$this->scheduler->unschedule_all_actions( 'wsscd_auto_purge_trash' );
		$this->scheduler->unschedule_all_actions( 'wsscd_analytics_hourly_aggregation' );
		$this->scheduler->unschedule_all_actions( 'wsscd_analytics_daily_aggregation' );

		$this->logger->info( 'Unscheduled all recurring actions' );
	}

	/**
	 * Clean up expired sessions.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function cleanup_expired_sessions(): void {
		try {
			$this->logger->info( 'Starting expired sessions cleanup' );

			$cleaned = 0;

			// Clean up customer usage sessions
			if ( $this->customer_usage_manager ) {
				$cleaned += $this->customer_usage_manager->cleanup_expired_sessions();
			}

			// Clean up wizard sessions
			if ( class_exists( 'WSSCD_Session_Service' ) ) {
				$session_service = new WSSCD_Session_Service();
				$session_service->cleanup_expired_sessions();
			}

			$this->logger->info(
				'Expired sessions cleanup completed',
				array(
					'records_cleaned' => $cleaned,
				)
			);

		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to cleanup expired sessions',
				array(
					'error' => $e->getMessage(),
				)
			);
		}
	}

	/**
	 * Clean up old analytics data.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function cleanup_old_analytics(): void {
		try {
			$this->logger->info( 'Starting old analytics cleanup' );

			$retention_days = intval( get_option( 'wsscd_analytics_retention_days', 90 ) );

			if ( $retention_days <= 0 ) {
				return; // Retention disabled
			}

			// Clean up old analytics data
			global $wpdb;
			$table_name  = $wpdb->prefix . 'wsscd_analytics';
			$cutoff_date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$retention_days} days" ) );

			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls, PluginCheck.Security.DirectDB.UnescapedDBParameter
			// Table name constructed with $wpdb->prefix. Query IS prepared. Scheduled analytics cleanup.
			$deleted = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$table_name} WHERE created_at < %s",
					$cutoff_date
				)
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls, PluginCheck.Security.DirectDB.UnescapedDBParameter

			$this->logger->info(
				'Old analytics cleanup completed',
				array(
					'records_deleted' => $deleted,
					'retention_days'  => $retention_days,
				)
			);

		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to cleanup old analytics',
				array(
					'error' => $e->getMessage(),
				)
			);
		}
	}

	/**
	 * Run manual cleanup.
	 *
	 * @since    1.0.0
	 * @return   array    Cleanup results.
	 */
	public function run_manual_cleanup(): array {
		$results = array(
			'sessions_cleaned'  => 0,
			'analytics_cleaned' => 0,
			'errors'            => array(),
		);

		// Run session cleanup
		try {
			if ( $this->customer_usage_manager ) {
				$results['sessions_cleaned'] = $this->customer_usage_manager->cleanup_expired_sessions();
			}
		} catch ( Exception $e ) {
			$results['errors'][] = 'Session cleanup: ' . $e->getMessage();
		}

		// Run analytics cleanup
		try {
			$this->cleanup_old_analytics();
			global $wpdb;
			$results['analytics_cleaned'] = $wpdb->rows_affected;
		} catch ( Exception $e ) {
			$results['errors'][] = 'Analytics cleanup: ' . $e->getMessage();
		}

		return $results;
	}

	/**
	 * Auto-purge old trashed campaigns.
	 *
	 * Permanently deletes campaigns that have been in trash
	 * longer than the configured retention period.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function auto_purge_trash(): void {
		try {
			$this->logger->info( 'Starting trash auto-purge' );

			// Get settings
			$settings = get_option( 'wsscd_settings', array() );

			// Check if auto-purge is enabled
			if ( ! isset( $settings['general']['trash_auto_purge'] ) || ! $settings['general']['trash_auto_purge'] ) {
				$this->logger->info( 'Trash auto-purge is disabled, skipping' );
				return;
			}

			// Get retention period (default 30 days)
			$retention_days = isset( $settings['general']['trash_retention_days'] )
				? absint( $settings['general']['trash_retention_days'] )
				: 30;

			if ( $retention_days < 1 ) {
				$retention_days = 30;
			}

			// Get campaign repository
			$container = Smart_Cycle_Discounts::get_instance();
			if ( ! $container || ! method_exists( $container, 'get_service' ) ) {
				$this->logger->error( 'Container not available for trash auto-purge' );
				return;
			}

			$repository = $container::get_service( 'campaign_repository' );
			if ( ! $repository ) {
				$this->logger->error( 'Campaign repository not available for trash auto-purge' );
				return;
			}

			// Find campaigns older than retention period
			$old_campaign_ids = $repository->find_trashed_older_than( $retention_days );

			if ( empty( $old_campaign_ids ) ) {
				$this->logger->info( 'No campaigns to purge from trash' );
				return;
			}

			$deleted_count = 0;
			$errors        = array();

			foreach ( $old_campaign_ids as $campaign_id ) {
				$result = $repository->force_delete( $campaign_id );

				if ( $result ) {
					++$deleted_count;
				} else {
					$errors[] = $campaign_id;
				}
			}

			$this->logger->info(
				'Trash auto-purge completed',
				array(
					'retention_days'  => $retention_days,
					'campaigns_found' => count( $old_campaign_ids ),
					'deleted_count'   => $deleted_count,
					'errors'          => $errors,
				)
			);

		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to auto-purge trash',
				array(
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString(),
				)
			);
		}
	}
}
