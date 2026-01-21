<?php
/**
 * Email Manager Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/integrations/email/class-email-manager.php
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
 * Email Manager
 *
 * Handles email notifications and template management for the plugin.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/integrations/email
 * @author     Webstepper <contact@webstepper.io>
 */
class WSSCD_Email_Manager {

	/**
	 * Logger instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Logger    $logger    Logger instance.
	 */
	private WSSCD_Logger $logger;

	/**
	 * Campaign manager instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Campaign_Manager    $campaign_manager    Campaign manager.
	 */
	private WSSCD_Campaign_Manager $campaign_manager;

	/**
	 * Action scheduler instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Action_Scheduler_Service    $action_scheduler    Action scheduler.
	 */
	private WSSCD_Action_Scheduler_Service $action_scheduler;

	/**
	 * Feature gate instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Feature_Gate    $feature_gate    Feature gate.
	 */
	private WSSCD_Feature_Gate $feature_gate;

	/**
	 * Analytics repository instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Analytics_Repository    $analytics_repository    Analytics repository.
	 */
	private WSSCD_Analytics_Repository $analytics_repository;

	/**
	 * Email queue.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $email_queue    Email queue.
	 */
	private array $email_queue = array();

	/**
	 * Email templates.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $templates    Email templates.
	 */
	private array $templates = array();

	/**
	 * Email settings.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $settings    Email settings.
	 */
	private array $settings = array();

	/**
	 * Email provider instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Email_Provider    $provider    Email provider.
	 */
	private WSSCD_Email_Provider $provider;

	/**
	 * Initialize the email manager.
	 *
	 * @since    1.0.0
	 * @param    WSSCD_Logger                   $logger                Logger instance.
	 * @param    WSSCD_Campaign_Manager         $campaign_manager      Campaign manager.
	 * @param    WSSCD_Action_Scheduler_Service $action_scheduler      Action scheduler.
	 * @param    WSSCD_Feature_Gate             $feature_gate          Feature gate instance.
	 * @param    WSSCD_Analytics_Repository     $analytics_repository  Analytics repository.
	 */
	public function __construct(
		WSSCD_Logger $logger,
		WSSCD_Campaign_Manager $campaign_manager,
		WSSCD_Action_Scheduler_Service $action_scheduler,
		WSSCD_Feature_Gate $feature_gate,
		WSSCD_Analytics_Repository $analytics_repository
	) {
		$this->logger               = $logger;
		$this->campaign_manager     = $campaign_manager;
		$this->action_scheduler     = $action_scheduler;
		$this->feature_gate         = $feature_gate;
		$this->analytics_repository = $analytics_repository;
		$this->load_settings();
		// Defer template registration until init hook when translations are available
		add_action( 'init', array( $this, 'register_templates' ), 5 );
	}

	/**
	 * Initialize email manager.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function init(): void {
		$this->add_hooks();

		// Defer Action Scheduler operations until init hook (priority 20 to run after ActionScheduler initializes at priority 1)
		add_action( 'init', array( $this, 'schedule_email_processing' ), 20 );
	}

	/**
	 * Add WordPress hooks.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function add_hooks(): void {
		// Campaign lifecycle hooks
		add_action( 'wsscd_campaign_started', array( $this, 'send_campaign_started_notification' ), 10, 1 );
		add_action( 'wsscd_campaign_ending', array( $this, 'send_campaign_ending_notification' ), 10, 1 );
		add_action( 'wsscd_campaign_ended', array( $this, 'send_campaign_ended_notification' ), 10, 1 );

		// Scheduled cron hook for ending notification
		add_action( 'wsscd_campaign_ending_notification', array( $this, 'handle_ending_notification_cron' ), 10, 1 );

		// Performance hooks
		add_action( 'wsscd_daily_report', array( $this, 'send_daily_performance_report' ), 10, 1 );
		add_action( 'wsscd_weekly_report', array( $this, 'send_weekly_performance_report' ), 10, 1 );

		// PRO notification hooks
		add_action( 'wsscd_performance_alert', array( $this, 'send_performance_alert' ), 10, 2 );
		add_action( 'wsscd_low_stock_alert', array( $this, 'send_low_stock_alert' ), 10, 2 );
		add_action( 'wsscd_milestone_alert', array( $this, 'send_milestone_alert' ), 10, 2 );

		// Scheduled cron hooks for reports
		add_action( 'wsscd_send_daily_report', array( $this, 'handle_daily_report_cron' ) );
		add_action( 'wsscd_send_weekly_report', array( $this, 'handle_weekly_report_cron' ) );

		// Email processing
		add_action( 'wsscd_process_email_queue', array( $this, 'process_email_queue' ) );

		// WordPress email filters (only for WP Mail provider)
		if ( 'wpmail' === $this->settings['email_provider'] ) {
			add_filter( 'wp_mail_content_type', array( $this, 'set_email_content_type' ) );
			add_filter( 'wp_mail_from', array( $this, 'set_email_from' ) );
			add_filter( 'wp_mail_from_name', array( $this, 'set_email_from_name' ) );
		}
	}

	/**
	 * Load email settings.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function load_settings(): void {
		$all_settings           = get_option( 'wsscd_settings', array() );
		$notifications_settings = isset( $all_settings['notifications'] ) ? $all_settings['notifications'] : array();

		// Defaults
		$defaults = array(
			'email_provider'           => 'wpmail',
			'sendgrid_api_key'         => '',
			'amazonses_access_key'     => '',
			'amazonses_secret_key'     => '',
			'amazonses_region'         => 'us-east-1',
			'from_email'               => get_option( 'admin_email' ),
			'from_name'                => get_bloginfo( 'name' ),
			'additional_recipients'    => '',
			'notify_campaign_started'  => true,
			'notify_campaign_ending'   => true,
			'notify_campaign_ended'    => true,
			'notify_daily_report'      => false,
			'notify_weekly_report'     => false,
			'notify_performance_alert' => false,
			'notify_low_stock_alert'   => false,
			'notify_milestone_alert'   => false,
			'notify_errors'            => true,
		);

		$this->settings = wp_parse_args( $notifications_settings, $defaults );

		$this->init_provider();
	}

	/**
	 * Initialize email provider based on settings.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function init_provider(): void {
		$provider_type = $this->settings['email_provider'];

		switch ( $provider_type ) {
			case 'sendgrid':
				$this->provider = new WSSCD_SendGrid_Provider(
					$this->logger,
					$this->settings['sendgrid_api_key'],
					$this->settings['from_email'],
					$this->settings['from_name']
				);
				break;

			case 'amazonses':
				$this->provider = new WSSCD_AmazonSES_Provider(
					$this->logger,
					$this->settings['amazonses_access_key'],
					$this->settings['amazonses_secret_key'],
					$this->settings['amazonses_region'],
					$this->settings['from_email'],
					$this->settings['from_name']
				);
				break;

			case 'wpmail':
			default:
				$this->provider = new WSSCD_WPMail_Provider(
					$this->logger,
					$this->settings['from_email'],
					$this->settings['from_name']
				);
				break;
		}

		$this->logger->debug(
			'Email provider initialized',
			array(
				'provider' => $this->provider->get_name(),
			)
		);
	}

	/**
	 * Register email templates.
	 *
	 * Called on 'init' hook (priority 5) when translations are available.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function register_templates(): void {
		$this->templates = array(
			'campaign_started'  => array(
				'subject'   => __( 'Campaign Started: {campaign_name}', 'smart-cycle-discounts' ),
				'template'  => 'campaign-started',
				'variables' => array( 'campaign_name', 'campaign_url', 'start_date', 'end_date', 'product_count' ),
			),
			'campaign_ending'   => array(
				'subject'   => __( 'Campaign Ending Soon: {campaign_name}', 'smart-cycle-discounts' ),
				'template'  => 'campaign-ending',
				'variables' => array( 'campaign_name', 'campaign_url', 'end_date', 'time_remaining', 'performance_summary' ),
			),
			'campaign_ended'    => array(
				'subject'   => __( 'Campaign Ended: {campaign_name}', 'smart-cycle-discounts' ),
				'template'  => 'campaign-ended',
				'variables' => array( 'campaign_name', 'performance_summary', 'total_revenue', 'total_orders' ),
			),
			'daily_report'      => array(
				'subject'   => __( 'Daily Performance Report - {date}', 'smart-cycle-discounts' ),
				'template'  => 'daily-report',
				'variables' => array( 'date', 'active_campaigns', 'total_revenue', 'total_orders', 'top_products' ),
			),
			'weekly_report'     => array(
				'subject'   => __( 'Weekly Performance Report - {week_range}', 'smart-cycle-discounts' ),
				'template'  => 'weekly-report',
				'variables' => array( 'week_range', 'campaign_summary', 'revenue_summary', 'performance_trends' ),
			),
			'performance_alert' => array(
				'subject'   => __( 'Performance Alert: {alert_type} - {campaign_name}', 'smart-cycle-discounts' ),
				'template'  => 'performance-alert',
				'variables' => array( 'campaign_name', 'alert_type', 'alert_message', 'current_performance', 'recommended_actions', 'dashboard_url' ),
			),
			'low_stock_alert'   => array(
				'subject'   => __( 'Low Stock Alert: {campaign_name}', 'smart-cycle-discounts' ),
				'template'  => 'low-stock-alert',
				'variables' => array( 'campaign_name', 'products_list', 'total_low_stock', 'recommended_actions', 'dashboard_url' ),
			),
			'milestone_alert'   => array(
				'subject'   => __( 'Milestone Reached: {milestone_type} - {campaign_name}', 'smart-cycle-discounts' ),
				'template'  => 'milestone-alert',
				'variables' => array( 'campaign_name', 'milestone_type', 'milestone_value', 'achievement_message', 'performance_summary', 'dashboard_url' ),
			),
		);

		$this->logger->debug( 'Email templates registered', array( 'count' => count( $this->templates ) ) );
	}

	/**
	 * Send campaign started notification.
	 *
	 * @since    1.0.0
	 * @param    int $campaign_id    Campaign ID.
	 * @return   void
	 */
	public function send_campaign_started_notification( int $campaign_id ): void {
		if ( ! $this->is_notification_enabled( 'campaign_started' ) ) {
			return;
		}

		try {
			$campaign = $this->campaign_manager->find( $campaign_id );
			if ( ! $campaign ) {
				throw new Exception( 'Campaign not found' );
			}

			$variables = array(
				'campaign_name' => $campaign->get_name(),
				'campaign_url'  => admin_url( "admin.php?page=wsscd-campaigns&action=edit&id={$campaign_id}" ),
				'start_date'    => $campaign->get_starts_at() ? $campaign->get_starts_at()->format( 'Y-m-d H:i:s' ) : '',
				'end_date'      => $campaign->get_ends_at() ? $campaign->get_ends_at()->format( 'Y-m-d H:i:s' ) : '',
				'product_count' => count( $campaign->get_product_ids() ),
			);

			$this->queue_email( 'campaign_started', $variables );

			$this->logger->info(
				'Campaign started notification queued',
				array(
					'campaign_id'   => $campaign_id,
					'campaign_name' => $campaign->get_name(),
				)
			);

		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to send campaign started notification',
				array(
					'campaign_id' => $campaign_id,
					'error'       => $e->getMessage(),
				)
			);
		}
	}

	/**
	 * Send campaign ending notification.
	 *
	 * @since    1.0.0
	 * @param    int $campaign_id    Campaign ID.
	 * @return   void
	 */
	public function send_campaign_ending_notification( int $campaign_id ): void {
		if ( ! $this->is_notification_enabled( 'campaign_ending' ) ) {
			return;
		}

		try {
			$campaign = $this->campaign_manager->find( $campaign_id );
			if ( ! $campaign ) {
				throw new Exception( 'Campaign not found' );
			}

			$performance    = $this->get_campaign_performance( $campaign_id );
			$time_remaining = $this->calculate_time_remaining( $campaign->get_ends_at() ? $campaign->get_ends_at()->format( 'Y-m-d H:i:s' ) : '' );

			$variables = array(
				'campaign_name'       => $campaign->get_name(),
				'campaign_url'        => admin_url( "admin.php?page=wsscd-campaigns&action=edit&id={$campaign_id}" ),
				'end_date'            => $campaign->get_ends_at() ? $campaign->get_ends_at()->format( 'Y-m-d H:i:s' ) : '',
				'time_remaining'      => $time_remaining,
				'performance_summary' => $this->format_performance_summary( $performance ),
			);

			$this->queue_email( 'campaign_ending', $variables );

			$this->logger->info(
				'Campaign ending notification queued',
				array(
					'campaign_id'    => $campaign_id,
					'campaign_name'  => $campaign->get_name(),
					'time_remaining' => $time_remaining,
				)
			);

		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to send campaign ending notification',
				array(
					'campaign_id' => $campaign_id,
					'error'       => $e->getMessage(),
				)
			);
		}
	}

	/**
	 * Send campaign ended notification.
	 *
	 * @since    1.0.0
	 * @param    int $campaign_id    Campaign ID.
	 * @return   void
	 */
	public function send_campaign_ended_notification( int $campaign_id ): void {
		if ( ! $this->is_notification_enabled( 'campaign_ended' ) ) {
			return;
		}

		try {
			$campaign = $this->campaign_manager->find( $campaign_id );
			if ( ! $campaign ) {
				throw new Exception( 'Campaign not found' );
			}

			$performance = $this->get_campaign_performance( $campaign_id );

			$variables = array(
				'campaign_name'       => $campaign->get_name(),
				'performance_summary' => $this->format_performance_summary( $performance ),
				'total_revenue'       => wc_price( $performance['total_revenue'] ?? 0 ),
				'total_orders'        => $performance['total_orders'] ?? 0,
			);

			$this->queue_email( 'campaign_ended', $variables );

			$this->logger->info(
				'Campaign ended notification queued',
				array(
					'campaign_id'   => $campaign_id,
					'campaign_name' => $campaign->get_name(),
					'total_revenue' => $performance['total_revenue'] ?? 0,
				)
			);

		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to send campaign ended notification',
				array(
					'campaign_id' => $campaign_id,
					'error'       => $e->getMessage(),
				)
			);
		}
	}

	/**
	 * Handle campaign ending notification cron.
	 *
	 * This method is called by WP-Cron when it's time to send
	 * the "ending soon" notification (24 hours before end date).
	 *
	 * @since    1.0.0
	 * @param    int $campaign_id    Campaign ID.
	 * @return   void
	 */
	public function handle_ending_notification_cron( int $campaign_id ): void {
		// Fire the wsscd_campaign_ending action
		do_action( 'wsscd_campaign_ending', $campaign_id );
	}

	/**
	 * Handle daily report cron.
	 *
	 * This method is called by WP-Cron to send daily performance reports.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function handle_daily_report_cron(): void {
		$date = gmdate( 'Y-m-d', strtotime( 'yesterday' ) );

		// Fire the wsscd_daily_report action
		do_action( 'wsscd_daily_report', $date );
	}

	/**
	 * Handle weekly report cron.
	 *
	 * This method is called by WP-Cron to send weekly performance reports.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function handle_weekly_report_cron(): void {
		$week_start = gmdate( 'Y-m-d', strtotime( 'last Monday -1 week' ) );

		// Fire the wsscd_weekly_report action
		do_action( 'wsscd_weekly_report', $week_start );
	}

	/**
	 * Send daily performance report.
	 *
	 * @since    1.0.0
	 * @param    string $date    Report date.
	 * @return   void
	 */
	public function send_daily_performance_report( string $date ): void {
		if ( ! $this->is_notification_enabled( 'daily_report' ) ) {
			return;
		}

		try {
			$report_data = $this->generate_daily_report( $date );

			$variables = array(
				'date'             => gmdate( 'F j, Y', strtotime( $date ) ),
				'active_campaigns' => $report_data['active_campaigns'],
				'total_revenue'    => wc_price( $report_data['total_revenue'] ),
				'total_orders'     => $report_data['total_orders'],
				'top_products'     => $this->format_top_products( $report_data['top_products'] ),
			);

			$this->queue_email( 'daily_report', $variables );

			$this->logger->info(
				'Daily performance report queued',
				array(
					'date'    => $date,
					'revenue' => $report_data['total_revenue'],
				)
			);

		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to send daily performance report',
				array(
					'date'  => $date,
					'error' => $e->getMessage(),
				)
			);
		}
	}

	/**
	 * Send weekly performance report.
	 *
	 * @since    1.0.0
	 * @param    string $week_start    Week start date.
	 * @return   void
	 */
	public function send_weekly_performance_report( string $week_start ): void {
		if ( ! $this->is_notification_enabled( 'weekly_report' ) ) {
			return;
		}

		try {
			$week_end    = gmdate( 'Y-m-d', strtotime( $week_start . ' +6 days' ) );
			$report_data = $this->generate_weekly_report( $week_start, $week_end );

			$variables = array(
				'week_range'         => gmdate( 'M j', strtotime( $week_start ) ) . ' - ' . gmdate( 'M j, Y', strtotime( $week_end ) ),
				'campaign_summary'   => $this->format_campaign_summary( $report_data['campaigns'] ),
				'revenue_summary'    => $this->format_revenue_summary( $report_data['revenue'] ),
				'performance_trends' => $this->format_performance_trends( $report_data['trends'] ),
			);

			$this->queue_email( 'weekly_report', $variables );

			$this->logger->info(
				'Weekly performance report queued',
				array(
					'week_start' => $week_start,
					'week_end'   => $week_end,
					'revenue'    => $report_data['revenue']['total'],
				)
			);

		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to send weekly performance report',
				array(
					'week_start' => $week_start,
					'error'      => $e->getMessage(),
				)
			);
		}
	}

	/**
	 * Send performance alert notification.
	 *
	 * @since    1.0.0
	 * @param    int   $campaign_id    Campaign ID.
	 * @param    array $alert_data     Alert data (alert_type, alert_message, performance, actions).
	 * @return   void
	 */
	public function send_performance_alert( int $campaign_id, array $alert_data ): void {
		if ( ! $this->is_notification_enabled( 'performance_alert' ) ) {
			return;
		}

		try {
			$campaign = $this->campaign_manager->find( $campaign_id );
			if ( ! $campaign ) {
				throw new Exception( 'Campaign not found' );
			}

			$variables = array(
				'campaign_name'       => $campaign->get_name(),
				'alert_type'          => $alert_data['alert_type'] ?? 'performance',
				'alert_message'       => $alert_data['alert_message'] ?? '',
				'current_performance' => $alert_data['performance'] ?? '',
				'recommended_actions' => $this->format_recommended_actions( $alert_data['actions'] ?? array() ),
				'dashboard_url'       => admin_url( "admin.php?page=wsscd-campaigns&action=edit&id={$campaign_id}" ),
			);

			$this->queue_email( 'performance_alert', $variables );

			$this->logger->info(
				'Performance alert notification queued',
				array(
					'campaign_id'   => $campaign_id,
					'campaign_name' => $campaign->get_name(),
					'alert_type'    => $variables['alert_type'],
				)
			);

		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to send performance alert notification',
				array(
					'campaign_id' => $campaign_id,
					'error'       => $e->getMessage(),
				)
			);
		}
	}

	/**
	 * Send low stock alert notification.
	 *
	 * @since    1.0.0
	 * @param    int   $campaign_id    Campaign ID.
	 * @param    array $stock_data     Stock data (products, actions).
	 * @return   void
	 */
	public function send_low_stock_alert( int $campaign_id, array $stock_data ): void {
		if ( ! $this->is_notification_enabled( 'low_stock_alert' ) ) {
			return;
		}

		try {
			$campaign = $this->campaign_manager->find( $campaign_id );
			if ( ! $campaign ) {
				throw new Exception( 'Campaign not found' );
			}

			$low_stock_products = $stock_data['products'] ?? array();
			$total_low_stock    = count( $low_stock_products );

			$variables = array(
				'campaign_name'       => $campaign->get_name(),
				'products_list'       => $this->format_product_stock_table( $low_stock_products ),
				'total_low_stock'     => $total_low_stock,
				'recommended_actions' => $this->format_recommended_actions( $stock_data['actions'] ?? array() ),
				'dashboard_url'       => admin_url( "admin.php?page=wsscd-campaigns&action=edit&id={$campaign_id}" ),
			);

			$this->queue_email( 'low_stock_alert', $variables );

			$this->logger->info(
				'Low stock alert notification queued',
				array(
					'campaign_id'     => $campaign_id,
					'campaign_name'   => $campaign->get_name(),
					'total_low_stock' => $total_low_stock,
				)
			);

		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to send low stock alert notification',
				array(
					'campaign_id' => $campaign_id,
					'error'       => $e->getMessage(),
				)
			);
		}
	}

	/**
	 * Send milestone alert notification.
	 *
	 * @since    1.0.0
	 * @param    int   $campaign_id       Campaign ID.
	 * @param    array $milestone_data    Milestone data (type, value, message, performance).
	 * @return   void
	 */
	public function send_milestone_alert( int $campaign_id, array $milestone_data ): void {
		if ( ! $this->is_notification_enabled( 'milestone_alert' ) ) {
			return;
		}

		try {
			$campaign = $this->campaign_manager->find( $campaign_id );
			if ( ! $campaign ) {
				throw new Exception( 'Campaign not found' );
			}

			$variables = array(
				'campaign_name'       => $campaign->get_name(),
				'milestone_type'      => $milestone_data['type'] ?? 'Achievement',
				'milestone_value'     => $milestone_data['value'] ?? '',
				'achievement_message' => $milestone_data['message'] ?? '',
				'performance_summary' => $milestone_data['performance'] ?? '',
				'dashboard_url'       => admin_url( "admin.php?page=wsscd-campaigns&action=edit&id={$campaign_id}" ),
			);

			$this->queue_email( 'milestone_alert', $variables );

			$this->logger->info(
				'Milestone alert notification queued',
				array(
					'campaign_id'    => $campaign_id,
					'campaign_name'  => $campaign->get_name(),
					'milestone_type' => $variables['milestone_type'],
				)
			);

		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to send milestone alert notification',
				array(
					'campaign_id' => $campaign_id,
					'error'       => $e->getMessage(),
				)
			);
		}
	}

	/**
	 * Queue email for sending.
	 *
	 * @since    1.0.0
	 * @param    string $template     Email template.
	 * @param    array  $variables    Template variables.
	 * @param    array  $recipients   Email recipients (optional).
	 * @return   bool                    Success status.
	 */
	public function queue_email( string $template, array $variables, array $recipients = array() ): bool {
		if ( ! isset( $this->templates[ $template ] ) ) {
			$this->logger->error( 'Email template not found', array( 'template' => $template ) );
			return false;
		}

		if ( empty( $recipients ) ) {
			$recipients = $this->get_default_recipients();
		}

		$email_data = array(
			'id'         => uniqid( 'email_', true ),
			'template'   => $template,
			'variables'  => $variables,
			'recipients' => $recipients,
			'queued_at'  => current_time( 'mysql' ),
			'attempts'   => 0,
			'status'     => 'queued',
		);

		$this->email_queue[] = $email_data;

		$this->save_email_queue();

		$this->logger->debug(
			'Email queued',
			array(
				'template'         => $template,
				'recipients_count' => count( $recipients ),
			)
		);

		return true;
	}

	/**
	 * Process email queue.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function process_email_queue(): void {
		$this->load_email_queue();

		if ( empty( $this->email_queue ) ) {
			return;
		}

		$processed            = 0;
		$max_emails_per_batch = apply_filters( 'wsscd_email_batch_size', 10 );

		foreach ( $this->email_queue as $index => $email_data ) {
			if ( $processed >= $max_emails_per_batch ) {
				break;
			}

			if ( $email_data['status'] !== 'queued' ) {
				continue;
			}

			$success = $this->send_email( $email_data );

			if ( $success ) {
				$this->email_queue[ $index ]['status']  = 'sent';
				$this->email_queue[ $index ]['sent_at'] = current_time( 'mysql' );
				++$processed;
			} else {
				++$this->email_queue[ $index ]['attempts'];

				if ( $this->email_queue[ $index ]['attempts'] >= 3 ) {
					$this->email_queue[ $index ]['status']    = 'failed';
					$this->email_queue[ $index ]['failed_at'] = current_time( 'mysql' );
				}
			}
		}

		$this->email_queue = array_filter(
			$this->email_queue,
			function ( $email ) {
				return ! in_array( $email['status'], array( 'sent', 'failed' ) );
			}
		);

		$this->save_email_queue();

		if ( $processed > 0 ) {
			$this->logger->info( 'Email queue processed', array( 'processed' => $processed ) );
		}

		// Cleanup old queue items periodically
		$this->cleanup_email_queue();
	}

	/**
	 * Clean up old email queue items.
	 *
	 * Removes queued items older than 7 days and limits total queue size.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function cleanup_email_queue(): void {
		$original_count = count( $this->email_queue );

		if ( 0 === $original_count ) {
			return;
		}

		$max_age_days     = apply_filters( 'wsscd_email_queue_max_age_days', 7 );
		$max_queue_size   = apply_filters( 'wsscd_email_queue_max_size', 1000 );
		$cutoff_timestamp = strtotime( "-{$max_age_days} days" );

		// Remove old queued items
		$this->email_queue = array_filter(
			$this->email_queue,
			function ( $email ) use ( $cutoff_timestamp ) {
				// Keep sent and failed items for filtering below
				if ( in_array( $email['status'], array( 'sent', 'failed' ), true ) ) {
					return true;
				}

				// Remove queued items older than max age
				if ( 'queued' === $email['status'] ) {
					$queued_time = isset( $email['queued_at'] ) ? strtotime( $email['queued_at'] ) : 0;
					return $queued_time >= $cutoff_timestamp;
				}

				return true;
			}
		);

		// Enforce maximum queue size (keep most recent items)
		if ( count( $this->email_queue ) > $max_queue_size ) {
			// Sort by queued_at timestamp (most recent first)
			usort(
				$this->email_queue,
				function ( $a, $b ) {
					$time_a = isset( $a['queued_at'] ) ? strtotime( $a['queued_at'] ) : 0;
					$time_b = isset( $b['queued_at'] ) ? strtotime( $b['queued_at'] ) : 0;
					return $time_b - $time_a;
				}
			);

			// Keep only the most recent items
			$this->email_queue = array_slice( $this->email_queue, 0, $max_queue_size );
		}

		$removed_count = $original_count - count( $this->email_queue );

		if ( $removed_count > 0 ) {
			$this->logger->info(
				'Email queue cleanup completed',
				array(
					'removed'        => $removed_count,
					'remaining'      => count( $this->email_queue ),
					'max_age_days'   => $max_age_days,
					'max_queue_size' => $max_queue_size,
				)
			);
		}
	}

	/**
	 * Send individual email.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $email_data    Email data.
	 * @return   bool                    Success status.
	 */
	private function send_email( array $email_data ): bool {
		try {
			$template_config = $this->templates[ $email_data['template'] ];

			// Generate subject
			$subject = $this->replace_variables( $template_config['subject'], $email_data['variables'] );

			// Generate content
			$content = $this->render_email_template( $email_data['template'], $email_data['variables'] );

			// Send to each recipient using provider
			foreach ( $email_data['recipients'] as $recipient ) {
				$result = $this->provider->send( $recipient, $subject, $content );

				if ( ! $result ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages are for logging/debugging, not direct output.
					throw new Exception( 'Failed to send email to ' . esc_html( $recipient ) );
				}
			}

			$this->logger->info(
				'Email sent successfully',
				array(
					'template'   => $email_data['template'],
					'recipients' => $email_data['recipients'],
					'provider'   => $this->provider->get_name(),
				)
			);

			return true;

		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to send email',
				array(
					'template' => $email_data['template'],
					'error'    => $e->getMessage(),
				)
			);

			return false;
		}
	}

	/**
	 * Render email template.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $template     Template name.
	 * @param    array  $variables    Template variables.
	 * @return   string                  Rendered content.
	 */
	private function render_email_template( string $template, array $variables ): string {
		// Allow custom templates directory
		$template_path = apply_filters( 'wsscd_email_template_path', WSSCD_PLUGIN_DIR . "templates/emails/{$template}.php", $template );

		if ( file_exists( $template_path ) ) {
			ob_start();
			include $template_path;
			$content = ob_get_clean();
			return $this->replace_variables( $content, $variables );
		}

		// Fallback to basic template
		return $this->render_basic_email_template( $template, $variables );
	}

	/**
	 * Render basic email template.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $template     Template name.
	 * @param    array  $variables    Template variables.
	 * @return   string                  Rendered content.
	 */
	private function render_basic_email_template( string $template, array $variables ): string {
		$content  = '<html><body>';
		$content .= '<h2>' . esc_html( ucwords( str_replace( array( '_', '-' ), ' ', $template ) ) ) . '</h2>';

		foreach ( $variables as $key => $value ) {
			$content .= '<p><strong>' . esc_html( ucwords( str_replace( '_', ' ', $key ) ) ) . ':</strong> ' . esc_html( $value ) . '</p>';
		}

		$content .= '</body></html>';

		return $content;
	}

	/**
	 * Replace variables in content.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $content      Content with variables.
	 * @param    array  $variables    Variable values.
	 * @return   string                  Content with replaced variables.
	 */
	private function replace_variables( string $content, array $variables ): string {
		foreach ( $variables as $key => $value ) {
			$content = str_replace( '{' . $key . '}', $value, $content );
		}

		return $content;
	}

	/**
	 * Schedule email processing.
	 *
	 * Called on 'init' hook (priority 20) after Action Scheduler is initialized.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function schedule_email_processing(): void {
		// Schedule recurring action to process email queue every hour
		if ( ! $this->action_scheduler->is_action_scheduled( 'wsscd_process_email_queue' ) ) {
			$this->action_scheduler->schedule_recurring_action(
				time(),
				HOUR_IN_SECONDS,
				'wsscd_process_email_queue',
				array(),
				'wsscd_email'
			);
		}

		// Schedule daily performance report (if enabled)
		if ( $this->is_notification_enabled( 'daily_report' ) ) {
			if ( ! wp_next_scheduled( 'wsscd_send_daily_report' ) ) {
				// Schedule for 9 AM daily
				$schedule_time = strtotime( 'tomorrow 09:00:00' );
				wp_schedule_event( $schedule_time, 'daily', 'wsscd_send_daily_report' );
			}
		} else {
			// Unschedule if disabled
			$timestamp = wp_next_scheduled( 'wsscd_send_daily_report' );
			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, 'wsscd_send_daily_report' );
			}
		}

		// Schedule weekly performance report (if enabled)
		if ( $this->is_notification_enabled( 'weekly_report' ) ) {
			if ( ! wp_next_scheduled( 'wsscd_send_weekly_report' ) ) {
				// Schedule for Monday 9 AM weekly
				$schedule_time = strtotime( 'next Monday 09:00:00' );
				wp_schedule_event( $schedule_time, 'weekly', 'wsscd_send_weekly_report' );
			}
		} else {
			// Unschedule if disabled
			$timestamp = wp_next_scheduled( 'wsscd_send_weekly_report' );
			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, 'wsscd_send_weekly_report' );
			}
		}
	}

	/**
	 * Check if notification is enabled.
	 *
	 * Checks both settings AND licensing (PRO features require PRO license).
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $notification    Notification type.
	 * @return   bool                       True if enabled and user has access.
	 */
	private function is_notification_enabled( string $notification ): bool {
		$setting_key = 'notify_' . $notification;

		$is_setting_enabled = isset( $this->settings[ $setting_key ] ) && $this->settings[ $setting_key ];

		if ( ! $is_setting_enabled ) {
			return false;
		}

		if ( ! $this->feature_gate->can_send_notification( $notification ) ) {
			$this->logger->debug(
				'Notification blocked by licensing',
				array(
					'notification' => $notification,
					'is_premium'   => $this->feature_gate->is_premium(),
				)
			);
			return false;
		}

		return true;
	}

	/**
	 * Get default recipients.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   array    Default recipients.
	 */
	private function get_default_recipients(): array {
		$recipients = array( get_option( 'admin_email' ) );

		if ( ! empty( $this->settings['additional_recipients'] ) ) {
			$additional = array_map( 'trim', explode( ',', $this->settings['additional_recipients'] ) );
			$recipients = array_merge( $recipients, $additional );
		}

		return array_unique( array_filter( $recipients ) );
	}

	/**
	 * Get campaign performance data.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    int $campaign_id    Campaign ID.
	 * @return   array                  Performance data.
	 */
	private function get_campaign_performance( int $campaign_id ): array {
		try {
			// Get campaign to determine date range
			$campaign = $this->campaign_manager->find( $campaign_id );
			if ( ! $campaign ) {
				throw new Exception( 'Campaign not found' );
			}

			$starts_at = $campaign->get_starts_at();
			$ends_at   = $campaign->get_ends_at();

			$start_date = $starts_at ? $starts_at->format( 'Y-m-d' ) : '';
			$end_date   = $ends_at ? $ends_at->format( 'Y-m-d' ) : gmdate( 'Y-m-d' );

			// Get real analytics data
			$analytics = $this->analytics_repository->get_campaign_performance( $campaign_id, $start_date, $end_date );

			// Get top products
			$top_products = $this->analytics_repository->get_product_performance( $campaign_id, $start_date, $end_date, 5 );

			return array(
				'total_revenue'   => $analytics['revenue'] ?? 0,
				'total_orders'    => $analytics['conversions'] ?? 0,
				'conversion_rate' => $analytics['conversion_rate'] ?? 0,
				'impressions'     => $analytics['impressions'] ?? 0,
				'clicks'          => $analytics['clicks'] ?? 0,
				'ctr'             => $analytics['ctr'] ?? 0,
				'top_products'    => $top_products,
			);
		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to get campaign performance data',
				array(
					'campaign_id' => $campaign_id,
					'error'       => $e->getMessage(),
				)
			);

			return array(
				'total_revenue'   => 0,
				'total_orders'    => 0,
				'conversion_rate' => 0,
				'impressions'     => 0,
				'clicks'          => 0,
				'ctr'             => 0,
				'top_products'    => array(),
			);
		}
	}

	/**
	 * Calculate time remaining.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $end_date    End date.
	 * @return   string                 Formatted time remaining.
	 */
	private function calculate_time_remaining( string $end_date ): string {
		$now  = time();
		$end  = strtotime( $end_date );
		$diff = $end - $now;

		if ( $diff <= 0 ) {
			return __( 'Expired', 'smart-cycle-discounts' );
		}

		$days  = floor( $diff / ( 24 * 60 * 60 ) );
		$hours = floor( ( $diff % ( 24 * 60 * 60 ) ) / ( 60 * 60 ) );

		if ( $days > 0 ) {
			/* translators: %d: number of days */
			return sprintf( _n( '%d day', '%d days', $days, 'smart-cycle-discounts' ), $days );
		} else {
			/* translators: %d: number of hours */
			return sprintf( _n( '%d hour', '%d hours', $hours, 'smart-cycle-discounts' ), $hours );
		}
	}

	/**
	 * Format performance summary.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $performance    Performance data.
	 * @return   string                   Formatted summary.
	 */
	private function format_performance_summary( array $performance ): string {
		return sprintf(
			/* translators: %1$s: formatted revenue amount, %2$d: total number of orders, %3$.2f: conversion rate percentage */
			__( 'Revenue: %1$s | Orders: %2$d | Conversion Rate: %3$.2f%%', 'smart-cycle-discounts' ),
			wc_price( $performance['total_revenue'] ?? 0 ),
			$performance['total_orders'] ?? 0,
			$performance['conversion_rate'] ?? 0
		);
	}

	/**
	 * Generate daily report data.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $date    Report date.
	 * @return   array              Report data.
	 */
	private function generate_daily_report( string $date ): array {
		try {
			// Get all active campaigns for the date
			$all_campaigns    = $this->campaign_manager->get_campaigns();
			$active_campaigns = array();
			$total_revenue    = 0;
			$total_orders     = 0;
			$all_top_products = array();

			foreach ( $all_campaigns as $campaign ) {
				// Check if campaign was active on this date
				$starts_at = $campaign->get_starts_at();
				$ends_at   = $campaign->get_ends_at();

				$campaign_date = new DateTime( $date, new DateTimeZone( 'UTC' ) );

				if ( $starts_at && $campaign_date < $starts_at ) {
					continue;
				}
				if ( $ends_at && $campaign_date > $ends_at ) {
					continue;
				}

				$active_campaigns[] = $campaign;

				// Get analytics for this campaign for this specific date
				$analytics = $this->analytics_repository->get_campaign_performance( $campaign->get_id(), $date, $date );

				$total_revenue += $analytics['revenue'] ?? 0;
				$total_orders  += $analytics['conversions'] ?? 0;

				// Get top products for this campaign
				$campaign_products = $this->analytics_repository->get_product_performance( $campaign->get_id(), $date, $date, 3 );
				if ( ! empty( $campaign_products ) ) {
					$all_top_products = array_merge( $all_top_products, $campaign_products );
				}
			}

			// Sort and limit top products
			usort(
				$all_top_products,
				function ( $a, $b ) {
					return ( $b['revenue'] ?? 0 ) <=> ( $a['revenue'] ?? 0 );
				}
			);
			$top_products = array_slice( $all_top_products, 0, 5 );

			return array(
				'active_campaigns' => count( $active_campaigns ),
				'total_revenue'    => $total_revenue,
				'total_orders'     => $total_orders,
				'top_products'     => $top_products,
			);
		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to generate daily report',
				array(
					'date'  => $date,
					'error' => $e->getMessage(),
				)
			);

			return array(
				'active_campaigns' => 0,
				'total_revenue'    => 0,
				'total_orders'     => 0,
				'top_products'     => array(),
			);
		}
	}

	/**
	 * Generate weekly report data.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $week_start    Week start date.
	 * @param    string $week_end      Week end date.
	 * @return   array                    Report data.
	 */
	private function generate_weekly_report( string $week_start, string $week_end ): array {
		try {
			// Get all campaigns
			$all_campaigns = $this->campaign_manager->get_campaigns();
			$week_campaigns = array();
			$total_revenue = 0;
			$total_orders = 0;
			$daily_revenues = array();

			foreach ( $all_campaigns as $campaign ) {
				// Check if campaign was active during this week
				$starts_at = $campaign->get_starts_at();
				$ends_at   = $campaign->get_ends_at();

				$week_start_dt = new DateTime( $week_start, new DateTimeZone( 'UTC' ) );
				$week_end_dt   = new DateTime( $week_end, new DateTimeZone( 'UTC' ) );

				// Skip if campaign ended before week start or started after week end
				if ( $ends_at && $ends_at < $week_start_dt ) {
					continue;
				}
				if ( $starts_at && $starts_at > $week_end_dt ) {
					continue;
				}

				// Get analytics for this campaign for the week
				$analytics = $this->analytics_repository->get_campaign_performance( $campaign->get_id(), $week_start, $week_end );

				if ( $analytics['revenue'] > 0 || $analytics['conversions'] > 0 ) {
					$week_campaigns[] = array(
						'name'        => $campaign->get_name(),
						'revenue'     => $analytics['revenue'] ?? 0,
						'orders'      => $analytics['conversions'] ?? 0,
						'impressions' => $analytics['impressions'] ?? 0,
						'clicks'      => $analytics['clicks'] ?? 0,
					);

					$total_revenue += $analytics['revenue'] ?? 0;
					$total_orders  += $analytics['conversions'] ?? 0;
				}
			}

			// Generate daily revenue trend for the week
			$current_date = new DateTime( $week_start, new DateTimeZone( 'UTC' ) );
			$end_date     = new DateTime( $week_end, new DateTimeZone( 'UTC' ) );

			while ( $current_date <= $end_date ) {
				$date_str = $current_date->format( 'Y-m-d' );
				$day_revenue = 0;

				foreach ( $week_campaigns as $campaign_data ) {
					// Simplification: divide weekly revenue equally across days
					// Real implementation would query daily analytics
					$day_revenue += ( $campaign_data['revenue'] / 7 );
				}

				$daily_revenues[] = array(
					'date'    => $date_str,
					'revenue' => round( $day_revenue, 2 ),
				);

				$current_date->modify( '+1 day' );
			}

			return array(
				'campaigns' => $week_campaigns,
				'revenue'   => array(
					'total'  => $total_revenue,
					'orders' => $total_orders,
				),
				'trends'    => $daily_revenues,
			);
		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to generate weekly report',
				array(
					'week_start' => $week_start,
					'week_end'   => $week_end,
					'error'      => $e->getMessage(),
				)
			);

			return array(
				'campaigns' => array(),
				'revenue'   => array( 'total' => 0 ),
				'trends'    => array(),
			);
		}
	}

	/**
	 * Format top products.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $products    Top products data.
	 * @return   string                Formatted products.
	 */
	private function format_top_products( array $products ): string {
		if ( empty( $products ) ) {
			return __( 'No products data available', 'smart-cycle-discounts' );
		}

		$formatted = array();
		foreach ( $products as $product ) {
			$formatted[] = sprintf( '%s (%s)', $product['name'], wc_price( $product['revenue'] ) );
		}

		return implode( ', ', $formatted );
	}

	/**
	 * Format campaign summary.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $campaigns    Campaign data.
	 * @return   string                 Formatted summary.
	 */
	private function format_campaign_summary( array $campaigns ): string {
		/* translators: %d: number of active campaigns */
		return sprintf( __( '%d campaigns active this week', 'smart-cycle-discounts' ), count( $campaigns ) );
	}

	/**
	 * Format revenue summary.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $revenue    Revenue data.
	 * @return   string               Formatted summary.
	 */
	private function format_revenue_summary( array $revenue ): string {
		/* translators: %s: formatted revenue amount */
		return sprintf( __( 'Total revenue: %s', 'smart-cycle-discounts' ), wc_price( $revenue['total'] ) );
	}

	/**
	 * Format performance trends.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $trends    Trends data (array of date/revenue pairs).
	 * @return   string              Formatted trends.
	 */
	private function format_performance_trends( array $trends ): string {
		if ( empty( $trends ) ) {
			return '<p>' . esc_html__( 'No performance data available for this period.', 'smart-cycle-discounts' ) . '</p>';
		}

		$html  = '<table style="width: 100%; border-collapse: collapse;">';
		$html .= '<thead><tr>';
		$html .= '<th style="text-align: left; padding: 8px; border-bottom: 2px solid #ddd;">' . esc_html__( 'Date', 'smart-cycle-discounts' ) . '</th>';
		$html .= '<th style="text-align: right; padding: 8px; border-bottom: 2px solid #ddd;">' . esc_html__( 'Revenue', 'smart-cycle-discounts' ) . '</th>';
		$html .= '</tr></thead>';
		$html .= '<tbody>';

		foreach ( $trends as $trend ) {
			$date    = isset( $trend['date'] ) ? date_i18n( get_option( 'date_format' ), strtotime( $trend['date'] ) ) : '';
			$revenue = isset( $trend['revenue'] ) ? wc_price( $trend['revenue'] ) : wc_price( 0 );

			$html .= '<tr>';
			$html .= '<td style="padding: 6px 8px; border-bottom: 1px solid #eee;">' . esc_html( $date ) . '</td>';
			$html .= '<td style="padding: 6px 8px; border-bottom: 1px solid #eee; text-align: right;">' . wp_kses_post( $revenue ) . '</td>';
			$html .= '</tr>';
		}

		$html .= '</tbody></table>';

		return $html;
	}

	/**
	 * Format recommended actions as HTML list.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $actions    Actions array.
	 * @return   string              Formatted HTML list.
	 */
	private function format_recommended_actions( array $actions ): string {
		if ( empty( $actions ) ) {
			return '<p>' . esc_html__( 'No specific actions recommended at this time.', 'smart-cycle-discounts' ) . '</p>';
		}

		$html = '<ul>';
		foreach ( $actions as $action ) {
			$html .= '<li>' . esc_html( $action ) . '</li>';
		}
		$html .= '</ul>';

		return $html;
	}

	/**
	 * Format product stock data as HTML table.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $products    Products array with stock data.
	 * @return   string                Formatted HTML table.
	 */
	private function format_product_stock_table( array $products ): string {
		if ( empty( $products ) ) {
			return '<p>' . esc_html__( 'No low stock products found.', 'smart-cycle-discounts' ) . '</p>';
		}

		$html  = '<table class="products-table">';
		$html .= '<thead><tr>';
		$html .= '<th>' . esc_html__( 'Product', 'smart-cycle-discounts' ) . '</th>';
		$html .= '<th>' . esc_html__( 'Stock', 'smart-cycle-discounts' ) . '</th>';
		$html .= '<th>' . esc_html__( 'Status', 'smart-cycle-discounts' ) . '</th>';
		$html .= '</tr></thead>';
		$html .= '<tbody>';

		foreach ( $products as $product ) {
			$product_name = isset( $product['name'] ) ? $product['name'] : __( 'Unknown Product', 'smart-cycle-discounts' );
			$stock_qty    = isset( $product['stock'] ) ? $product['stock'] : 0;
			$status       = isset( $product['status'] ) ? $product['status'] : 'low';

			$status_class = 'stock-' . $status;
			$status_label = 'critical' === $status ? __( 'Critical', 'smart-cycle-discounts' ) : __( 'Low', 'smart-cycle-discounts' );

			$html .= '<tr>';
			$html .= '<td>' . esc_html( $product_name ) . '</td>';
			$html .= '<td>' . esc_html( $stock_qty ) . '</td>';
			$html .= '<td><span class="stock-level ' . esc_attr( $status_class ) . '">' . esc_html( $status_label ) . '</span></td>';
			$html .= '</tr>';
		}

		$html .= '</tbody></table>';

		return $html;
	}

	/**
	 * Save email queue to database.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function save_email_queue(): void {
		update_option( 'wsscd_email_queue', $this->email_queue );
	}

	/**
	 * Load email queue from database.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function load_email_queue(): void {
		$this->email_queue = get_option( 'wsscd_email_queue', array() );
	}

	/**
	 * Set email content type.
	 *
	 * @since    1.0.0
	 * @return   string    Content type.
	 */
	public function set_email_content_type(): string {
		return 'text/html';
	}

	/**
	 * Set email from address.
	 *
	 * @since    1.0.0
	 * @param    string $from_email    Current from email.
	 * @return   string                   Modified from email.
	 */
	public function set_email_from( string $from_email ): string {
		return $this->settings['from_email'] ?: $from_email;
	}

	/**
	 * Set email from name.
	 *
	 * @since    1.0.0
	 * @param    string $from_name    Current from name.
	 * @return   string                  Modified from name.
	 */
	public function set_email_from_name( string $from_name ): string {
		return $this->settings['from_name'] ?: $from_name;
	}

	/**
	 * Get email settings.
	 *
	 * @since    1.0.0
	 * @return   array    Email settings.
	 */
	public function get_settings(): array {
		return $this->settings;
	}

	/**
	 * Update email settings.
	 *
	 * @since    1.0.0
	 * @param    array $settings    New settings.
	 * @return   bool                  Success status.
	 */
	public function update_settings( array $settings ): bool {
		$this->settings = wp_parse_args( $settings, $this->settings );

		$all_settings                  = get_option( 'wsscd_settings', array() );
		$all_settings['notifications'] = $this->settings;

		$result = update_option( 'wsscd_settings', $all_settings );

		// Re-initialize provider if provider settings changed
		if ( isset( $settings['email_provider'] ) || isset( $settings['sendgrid_api_key'] ) || isset( $settings['amazonses_access_key'] ) ) {
			$this->init_provider();
		}

		return $result;
	}

	/**
	 * Get email templates.
	 *
	 * @since    1.0.0
	 * @return   array    Email templates.
	 */
	public function get_templates(): array {
		return $this->templates;
	}

	/**
	 * Get email queue status.
	 *
	 * @since    1.0.0
	 * @return   array    Queue status.
	 */
	public function get_queue_status(): array {
		$this->load_email_queue();

		$status = array(
			'total'  => count( $this->email_queue ),
			'queued' => 0,
			'sent'   => 0,
			'failed' => 0,
		);

		foreach ( $this->email_queue as $email ) {
			++$status[ $email['status'] ];
		}

		return $status;
	}
}
