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
class SCD_Cron_Scheduler {

	/**
	 * Customer usage manager.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Customer_Usage_Manager|null    $customer_usage_manager    Customer usage manager.
	 */
	private ?SCD_Customer_Usage_Manager $customer_usage_manager = null;

	/**
	 * Logger instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Logger    $logger    Logger instance.
	 */
	private SCD_Logger $logger;

	/**
	 * ActionScheduler service.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Action_Scheduler_Service    $scheduler    ActionScheduler service.
	 */
	private SCD_Action_Scheduler_Service $scheduler;

	/**
	 * Initialize the scheduler.
	 *
	 * @since    1.0.0
	 * @param    SCD_Logger                      $logger                    Logger instance.
	 * @param    SCD_Action_Scheduler_Service    $scheduler                 ActionScheduler service.
	 * @param    SCD_Customer_Usage_Manager|null $customer_usage_manager    Customer usage manager.
	 */
	public function __construct( SCD_Logger $logger, SCD_Action_Scheduler_Service $scheduler, ?SCD_Customer_Usage_Manager $customer_usage_manager = null ) {
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
		add_action( 'scd_cleanup_expired_sessions', array( $this, 'cleanup_expired_sessions' ) );
		add_action( 'scd_cleanup_old_analytics', array( $this, 'cleanup_old_analytics' ) );
		add_action( 'scd_warm_cache', array( $this, 'warm_cache_task' ) );

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
		if ( ! $this->scheduler->is_action_scheduled( 'scd_update_campaign_status' ) ) {
			$this->scheduler->schedule_recurring_action(
				time(),
				15 * MINUTE_IN_SECONDS,
				'scd_update_campaign_status',
				array()
			);
			$this->logger->info( 'Scheduled campaign status safety check (every 15 minutes)' );
		}

		// Schedule session cleanup - runs daily
		if ( ! $this->scheduler->is_action_scheduled( 'scd_cleanup_expired_sessions' ) ) {
			$this->scheduler->schedule_recurring_action(
				time(),
				DAY_IN_SECONDS,
				'scd_cleanup_expired_sessions',
				array()
			);
			$this->logger->info( 'Scheduled expired sessions cleanup (daily)' );
		}

		// Schedule wizard session cleanup - runs daily
		if ( ! $this->scheduler->is_action_scheduled( 'scd_cleanup_wizard_sessions' ) ) {
			$this->scheduler->schedule_recurring_action(
				time(),
				DAY_IN_SECONDS,
				'scd_cleanup_wizard_sessions',
				array()
			);
			$this->logger->info( 'Scheduled wizard sessions cleanup (daily)' );
		}

		// Schedule audit log cleanup - runs daily
		if ( ! $this->scheduler->is_action_scheduled( 'scd_cleanup_audit_logs' ) ) {
			$this->scheduler->schedule_recurring_action(
				time(),
				DAY_IN_SECONDS,
				'scd_cleanup_audit_logs',
				array()
			);
			$this->logger->info( 'Scheduled audit logs cleanup (daily)' );
		}

		// Schedule analytics cleanup - runs weekly
		if ( ! $this->scheduler->is_action_scheduled( 'scd_cleanup_old_analytics' ) ) {
			$this->scheduler->schedule_recurring_action(
				time(),
				WEEK_IN_SECONDS,
				'scd_cleanup_old_analytics',
				array()
			);
			$this->logger->info( 'Scheduled old analytics cleanup (weekly)' );
		}

		// Schedule cache warming - runs hourly if enabled in settings
		$settings = get_option( 'scd_settings', array() );
		if ( isset( $settings['performance']['enable_cache_warming'] ) && $settings['performance']['enable_cache_warming'] ) {
			if ( ! $this->scheduler->is_action_scheduled( 'scd_warm_cache' ) ) {
				$this->scheduler->schedule_recurring_action(
					time(),
					HOUR_IN_SECONDS,
					'scd_warm_cache',
					array()
				);
				$this->logger->info( 'Scheduled cache warming (hourly)' );
			}
		}

		// Analytics aggregation disabled - main analytics table is already pre-aggregated
		// No need for additional hourly/daily aggregation layers
		// @see includes/database/migrations/001-initial-schema.php for aggregated analytics table structure
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

		$this->scheduler->unschedule_all_actions( 'scd_update_campaign_status' );
		$this->scheduler->unschedule_all_actions( 'scd_cleanup_expired_sessions' );
		$this->scheduler->unschedule_all_actions( 'scd_cleanup_wizard_sessions' );
		$this->scheduler->unschedule_all_actions( 'scd_cleanup_audit_logs' );
		$this->scheduler->unschedule_all_actions( 'scd_cleanup_old_analytics' );
		$this->scheduler->unschedule_all_actions( 'scd_warm_cache' );
		$this->scheduler->unschedule_all_actions( 'scd_analytics_hourly_aggregation' );
		$this->scheduler->unschedule_all_actions( 'scd_analytics_daily_aggregation' );

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
			if ( class_exists( 'SCD_Session_Service' ) ) {
				$session_service = new SCD_Session_Service();
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
	 * Warm cache task.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function warm_cache_task(): void {
		try {
			$this->logger->info( 'Starting cache warming' );

			// Get cache manager from container
			$container = Smart_Cycle_Discounts::get_instance();
			if ( ! $container || ! method_exists( $container, 'get_service' ) ) {
				$this->logger->error( 'Container not available for cache warming' );
				return;
			}

			$cache_manager = $container::get_service( 'cache_manager' );

			if ( ! $cache_manager || ! method_exists( $cache_manager, 'warm_cache' ) ) {
				$this->logger->error( 'Cache manager not available for cache warming' );
				return;
			}

			// Execute cache warming
			$cache_manager->warm_cache();

			$this->logger->info( 'Cache warming completed successfully' );

		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to warm cache',
				array(
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString(),
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

			$retention_days = intval( get_option( 'scd_analytics_retention_days', 90 ) );

			if ( $retention_days <= 0 ) {
				return; // Retention disabled
			}

			// Clean up old analytics data
			global $wpdb;
			$table_name  = $wpdb->prefix . 'scd_analytics';
			$cutoff_date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$retention_days} days" ) );

			$deleted = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$table_name} WHERE created_at < %s",
					$cutoff_date
				)
			);

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
}
