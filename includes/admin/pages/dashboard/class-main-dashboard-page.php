<?php
/**
 * Main Dashboard Page Controller
 *
 * Handles the main dashboard page (Free tier) at page=smart-cycle-discounts.
 * Provides overview metrics, campaign status, and upgrade prompts for Pro features.
 *
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/pages/dashboard
 */

declare(strict_types=1);


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Main Dashboard Page Controller
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/pages/dashboard
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Main_Dashboard_Page {

	/**
	 * Analytics dashboard instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Analytics_Dashboard    $analytics_dashboard    Analytics dashboard.
	 */
	private SCD_Analytics_Dashboard $analytics_dashboard;

	/**
	 * Campaign repository instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Campaign_Repository    $campaign_repository    Campaign repository.
	 */
	private SCD_Campaign_Repository $campaign_repository;

	/**
	 * Feature gate instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Feature_Gate    $feature_gate    Feature gate.
	 */
	private SCD_Feature_Gate $feature_gate;

	/**
	 * Upgrade prompt manager instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Upgrade_Prompt_Manager    $upgrade_prompt_manager    Upgrade prompt manager.
	 */
	private SCD_Upgrade_Prompt_Manager $upgrade_prompt_manager;

	/**
	 * Logger instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Logger    $logger    Logger instance.
	 */
	private SCD_Logger $logger;

	/**
	 * Campaign health service instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Campaign_Health_Service    $health_service    Campaign health service.
	 */
	private SCD_Campaign_Health_Service $health_service;

	/**
	 * Dashboard service instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Dashboard_Service    $dashboard_service    Dashboard service.
	 */
	private SCD_Dashboard_Service $dashboard_service;

	/**
	 * Initialize the main dashboard page.
	 *
	 * @since    1.0.0
	 * @param    SCD_Analytics_Dashboard     $analytics_dashboard      Analytics dashboard.
	 * @param    SCD_Campaign_Repository     $campaign_repository      Campaign repository.
	 * @param    SCD_Feature_Gate            $feature_gate             Feature gate.
	 * @param    SCD_Upgrade_Prompt_Manager  $upgrade_prompt_manager   Upgrade prompt manager.
	 * @param    SCD_Logger                  $logger                   Logger instance.
	 * @param    SCD_Campaign_Health_Service $health_service           Campaign health service.
	 * @param    SCD_Dashboard_Service       $dashboard_service        Dashboard service.
	 */
	public function __construct(
		SCD_Analytics_Dashboard $analytics_dashboard,
		SCD_Campaign_Repository $campaign_repository,
		SCD_Feature_Gate $feature_gate,
		SCD_Upgrade_Prompt_Manager $upgrade_prompt_manager,
		SCD_Logger $logger,
		SCD_Campaign_Health_Service $health_service,
		SCD_Dashboard_Service $dashboard_service
	) {
		$this->analytics_dashboard    = $analytics_dashboard;
		$this->campaign_repository    = $campaign_repository;
		$this->feature_gate           = $feature_gate;
		$this->upgrade_prompt_manager = $upgrade_prompt_manager;
		$this->logger                 = $logger;
		$this->health_service         = $health_service;
		$this->dashboard_service      = $dashboard_service;
	}

	/**
	 * Render the main dashboard page.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function render(): void {
		// Check user capabilities
		if ( ! current_user_can( 'scd_view_analytics' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'smart-cycle-discounts' ) );
		}

		try {
			// Get dashboard data (free tier: 7 days, premium: 30 days)
			$date_range     = $this->feature_gate->is_premium() ? '30days' : '7days';
			$dashboard_data = $this->get_dashboard_data( $date_range );

			// Load the view template
			$this->render_view( $dashboard_data );

		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to render main dashboard page',
				array(
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString(),
				)
			);

			wp_die( esc_html__( 'Failed to load dashboard.', 'smart-cycle-discounts' ) );
		}
	}

	/**
	 * Get dashboard data.
	 *
	 * Uses Dashboard Service (PHASE 1 integration).
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $date_range    Date range.
	 * @return   array                    Dashboard data.
	 */
	private function get_dashboard_data( $date_range ): array {
		// Get dashboard data from service (includes caching from PHASE 3)
		$dashboard_data = $this->dashboard_service->get_dashboard_data(
			array(
				'date_range' => $date_range,
			)
		);

		// Add campaign suggestions from Dashboard Service
		$dashboard_data['campaign_suggestions'] = $this->dashboard_service->get_campaign_suggestions();

		return $dashboard_data;
	}

	/**
	 * Get campaign status breakdown.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   array    Campaign status counts.
	 */
	private function get_campaign_stats(): array {
		global $wpdb;

		$table_name = $wpdb->prefix . 'scd_campaigns';

		$stats = $wpdb->get_results(
			"SELECT status, COUNT(*) as count
			FROM {$table_name}
			WHERE deleted_at IS NULL
			GROUP BY status",
			ARRAY_A
		);

		$result = array(
			'active'    => 0,
			'scheduled' => 0,
			'paused'    => 0,
			'expired'   => 0,
			'draft'     => 0,
		);

		foreach ( $stats as $stat ) {
			$status = $stat['status'];
			$count  = absint( $stat['count'] );

			if ( isset( $result[ $status ] ) ) {
				$result[ $status ] = $count;
			}
		}

		$result['total'] = array_sum( $result );

		return $result;
	}

	/**
	 * Get top campaigns by revenue.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    int    $limit         Number of campaigns to retrieve.
	 * @param    string $date_range    Date range.
	 * @return   array                    Top campaigns.
	 */
	private function get_top_campaigns( $limit, $date_range ): array {
		// Get all active campaigns
		$campaigns = $this->campaign_repository->find_all(
			array(
				'status'  => 'active',
				'orderby' => 'created_at',
				'order'   => 'DESC',
				'limit'   => $limit,
			)
		);

		if ( empty( $campaigns ) ) {
			return array();
		}

		// Convert campaign objects to arrays and get IDs
		$campaign_data = array();
		$campaign_ids  = array();
		foreach ( $campaigns as $campaign ) {
			$campaign_ids[]  = $campaign->get_id();
			$campaign_data[] = array(
				'id'     => $campaign->get_id(),
				'name'   => $campaign->get_name(),
				'status' => $campaign->get_status(),
			);
		}

		// Get batch metrics (this might fail if analytics table doesn't exist yet)
		try {
			$metrics = $this->analytics_dashboard->get_batch_campaign_metrics( $campaign_ids, $date_range );
		} catch ( Exception $e ) {
			// Analytics table doesn't exist yet - use empty metrics
			$this->logger->debug( 'Analytics metrics unavailable', array( 'error' => $e->getMessage() ) );
			$metrics = array();
		}

		// Merge campaign data with metrics
		$result = array();

		foreach ( $campaign_data as $campaign ) {
			$campaign_id = $campaign['id'];

			$result[] = array(
				'id'          => $campaign_id,
				'name'        => $campaign['name'],
				'status'      => $campaign['status'],
				'revenue'     => isset( $metrics[ $campaign_id ]['revenue'] ) ? $metrics[ $campaign_id ]['revenue'] : 0,
				'conversions' => isset( $metrics[ $campaign_id ]['conversions'] ) ? $metrics[ $campaign_id ]['conversions'] : 0,
				'impressions' => isset( $metrics[ $campaign_id ]['impressions'] ) ? $metrics[ $campaign_id ]['impressions'] : 0,
			);
		}

		return $result;
	}

	/**
	 * Get recent activity events.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    int $limit    Number of events to retrieve.
	 * @return   array            Recent activity events.
	 */
	private function get_recent_activity( $limit ): array {
		global $wpdb;

		$table_name = $wpdb->prefix . 'scd_activity_log';

		// Check if table exists
		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) === $table_name;

		if ( ! $table_exists ) {
			return array();
		}

		$events = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_name}
				ORDER BY created_at DESC
				LIMIT %d",
				$limit
			),
			ARRAY_A
		);

		return $events ? $events : array();
	}

	/**
	 * Get campaign health checks.
	 *
	 * Uses unified Campaign Health Service for consistent health analysis.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   array    Campaign health data.
	 */
	private function get_campaign_health(): array {
		global $wpdb;

		$table_name = $wpdb->prefix . 'scd_campaigns';

		// Get all active, scheduled, and paused campaigns
		$campaigns = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_name}
				WHERE deleted_at IS NULL
				AND status IN (%s, %s, %s)
				ORDER BY priority DESC",
				'active',
				'scheduled',
				'paused'
			),
			ARRAY_A
		);

		if ( empty( $campaigns ) ) {
			return $this->get_empty_health_structure();
		}

		// Use unified health service to analyze all campaigns
		$aggregate_health = $this->health_service->analyze_campaigns( $campaigns, 'standard' );

		// Transform to dashboard format
		$health = array(
			'status'           => $this->map_status_to_dashboard( $aggregate_health['overall_status'] ),
			'issues'           => array(),
			'warnings'         => array(),
			'success_messages' => array(),
			'categories'       => array(
				'configuration' => array(
					'status' => 'healthy',
					'count'  => 0,
				),
				'coverage'      => array(
					'status' => 'healthy',
					'count'  => 0,
				),
				'schedule'      => array(
					'status' => 'healthy',
					'count'  => 0,
				),
				'discount'      => array(
					'status' => 'healthy',
					'count'  => 0,
				),
				'stock'         => array(
					'status' => 'healthy',
					'count'  => 0,
				),
				'conflicts'     => array(
					'status' => 'healthy',
					'count'  => 0,
				),
			),
			'quick_stats'      => array(
				'total_analyzed' => $aggregate_health['total_campaigns_analyzed'],
				'issues_count'   => count( $aggregate_health['critical_issues'] ),
				'warnings_count' => count( $aggregate_health['warnings'] ),
			),
		);

		// Map critical issues
		foreach ( $aggregate_health['critical_issues'] as $issue ) {
			$health['issues'][] = array(
				'type'    => isset( $issue['category'] ) ? $issue['category'] : 'general',
				'message' => $issue['message'],
			);
		}

		// Map warnings
		foreach ( $aggregate_health['warnings'] as $warning ) {
			$health['warnings'][] = array(
				'type'    => isset( $warning['category'] ) ? $warning['category'] : 'general',
				'message' => $warning['message'],
			);
		}

		// Map category statuses
		if ( isset( $aggregate_health['categories_data'] ) ) {
			$categories_data = $aggregate_health['categories_data'];
			if ( isset( $categories_data['configuration'] ) ) {
				$health['categories']['configuration'] = $this->map_category_status( $categories_data['configuration'] );
			}
			if ( isset( $categories_data['coverage'] ) ) {
				$health['categories']['coverage'] = $this->map_category_status( $categories_data['coverage'] );
			}
			if ( isset( $categories_data['schedule'] ) ) {
				$health['categories']['schedule'] = $this->map_category_status( $categories_data['schedule'] );
			}
			if ( isset( $categories_data['discount'] ) ) {
				$health['categories']['discount'] = $this->map_category_status( $categories_data['discount'] );
			}
			if ( isset( $categories_data['stock'] ) ) {
				$health['categories']['stock'] = $this->map_category_status( $categories_data['stock'] );
			}
			if ( isset( $categories_data['conflicts'] ) ) {
				$health['categories']['conflicts'] = $this->map_category_status( $categories_data['conflicts'] );
			}
		}

		// Check campaign limit
		$active_campaigns = array_filter(
			$campaigns,
			function ( $c ) {
				return 'active' === $c['status'];
			}
		);
		$active_count     = count( $active_campaigns );
		$campaign_limit   = $this->feature_gate->get_campaign_limit();

		if ( 0 !== $campaign_limit && $active_count >= $campaign_limit ) {
			$health['issues'][] = array(
				'type'    => 'limit_reached',
				'message' => sprintf(
					/* translators: %d: campaign limit */
					__( 'Campaign limit reached (%d active campaigns)', 'smart-cycle-discounts' ),
					$campaign_limit
				),
			);
			$health['status']   = 'critical';
		} elseif ( 0 !== $campaign_limit && $active_count >= ( $campaign_limit * 0.67 ) ) {
			$health['warnings'][] = array(
				'type'    => 'approaching_limit',
				'message' => sprintf(
					/* translators: 1: active campaigns, 2: campaign limit */
					__( 'Using %1$d of %2$d campaigns - approaching limit', 'smart-cycle-discounts' ),
					$active_count,
					$campaign_limit
				),
			);
			if ( 'success' === $health['status'] ) {
				$health['status'] = 'warning';
			}
		}

		// Recalculate quick stats after campaign limit checks
		$health['quick_stats']['issues_count']   = count( $health['issues'] );
		$health['quick_stats']['warnings_count'] = count( $health['warnings'] );

		// Add success messages if everything is healthy
		if ( 'success' === $health['status'] && empty( $health['warnings'] ) && ! empty( $campaigns ) ) {
			$health['success_messages'][] = sprintf(
				/* translators: %d: number of campaigns */
				_n( '%d campaign analyzed and healthy', '%d campaigns analyzed and healthy', count( $campaigns ), 'smart-cycle-discounts' ),
				count( $campaigns )
			);
			$health['success_messages'][] = __( 'No critical issues detected', 'smart-cycle-discounts' );
		} elseif ( ! empty( $health['warnings'] ) && empty( $health['issues'] ) ) {
			// If warnings but no critical issues
			$health['success_messages'][] = __( 'Minor issues detected - review warnings below', 'smart-cycle-discounts' );
		}

		return $health;
	}

	/**
	 * Get empty health structure.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   array    Empty health data structure.
	 */
	private function get_empty_health_structure(): array {
		return array(
			'status'           => 'success',
			'issues'           => array(),
			'warnings'         => array(),
			'success_messages' => array(),
			'categories'       => array(
				'configuration' => array(
					'status' => 'healthy',
					'count'  => 0,
				),
				'schedule'      => array(
					'status' => 'healthy',
					'count'  => 0,
				),
				'conflicts'     => array(
					'status' => 'healthy',
					'count'  => 0,
				),
			),
			'quick_stats'      => array(
				'total_analyzed' => 0,
				'issues_count'   => 0,
				'warnings_count' => 0,
			),
		);
	}

	/**
	 * Map service status to dashboard status.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $service_status    Status from health service.
	 * @return   string                       Dashboard status.
	 */
	private function map_status_to_dashboard( $service_status ): string {
		$status_map = array(
			'excellent' => 'success',
			'good'      => 'success',
			'fair'      => 'warning',
			'poor'      => 'critical',
		);

		return isset( $status_map[ $service_status ] ) ? $status_map[ $service_status ] : 'success';
	}

	/**
	 * Map category data to dashboard category format.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $category_data    Category data from service.
	 * @return   array                      Dashboard category format.
	 */
	private function map_category_status( $category_data ): array {
		$critical_count = isset( $category_data['critical'] ) ? $category_data['critical'] : 0;
		$warning_count  = isset( $category_data['warning'] ) ? $category_data['warning'] : 0;

		$status = 'healthy';
		if ( $critical_count > 0 ) {
			$status = 'critical';
		} elseif ( $warning_count > 0 ) {
			$status = 'warning';
		}

		return array(
			'status' => $status,
			'count'  => $critical_count + $warning_count,
		);
	}


	/**
	 * Render the dashboard view.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $data    Dashboard data.
	 * @return   void
	 */
	private function render_view( $data ): void {
		// Extract data for view
		$metrics              = $data['metrics'];
		$campaign_stats       = $data['campaign_stats'];
		$top_campaigns        = $data['top_campaigns'];
		$recent_activity      = $data['recent_activity'];
		$campaign_health      = $data['campaign_health'];
		$campaign_suggestions = $data['campaign_suggestions'];
		$all_campaigns        = $data['all_campaigns'] ?? array(); // PHASE 2: Pre-computed campaign display data
		$timeline_campaigns   = $data['timeline_campaigns'] ?? array(); // PHASE 2: Timeline positioning data
		$is_premium           = $data['is_premium'];
		$campaign_limit       = $data['campaign_limit'];

		// Pass feature gate and upgrade prompt manager to view
		$feature_gate           = $this->feature_gate;
		$upgrade_prompt_manager = $this->upgrade_prompt_manager;

		// Load view template
		$view_file = SCD_PLUGIN_DIR . 'resources/views/admin/pages/dashboard/main-dashboard.php';

		if ( file_exists( $view_file ) ) {
			include $view_file;
		} else {
			echo '<div class="wrap">';
			echo '<h1>' . esc_html__( 'Smart Cycle Discounts Dashboard', 'smart-cycle-discounts' ) . '</h1>';
			echo '<p>' . esc_html__( 'Dashboard template not found.', 'smart-cycle-discounts' ) . '</p>';
			echo '</div>';
		}
	}
}
