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
	 * Initialize the main dashboard page.
	 *
	 * @since    1.0.0
	 * @param    SCD_Analytics_Dashboard          $analytics_dashboard      Analytics dashboard.
	 * @param    SCD_Campaign_Repository          $campaign_repository      Campaign repository.
	 * @param    SCD_Feature_Gate                 $feature_gate             Feature gate.
	 * @param    SCD_Upgrade_Prompt_Manager       $upgrade_prompt_manager   Upgrade prompt manager.
	 * @param    SCD_Logger                       $logger                   Logger instance.
	 * @param    SCD_Campaign_Health_Service      $health_service           Campaign health service.
	 */
	public function __construct(
		SCD_Analytics_Dashboard $analytics_dashboard,
		SCD_Campaign_Repository $campaign_repository,
		SCD_Feature_Gate $feature_gate,
		SCD_Upgrade_Prompt_Manager $upgrade_prompt_manager,
		SCD_Logger $logger,
		SCD_Campaign_Health_Service $health_service
	) {
		$this->analytics_dashboard = $analytics_dashboard;
		$this->campaign_repository = $campaign_repository;
		$this->feature_gate = $feature_gate;
		$this->upgrade_prompt_manager = $upgrade_prompt_manager;
		$this->logger = $logger;
		$this->health_service = $health_service;
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
			$date_range = $this->feature_gate->is_premium() ? '30days' : '7days';
			$dashboard_data = $this->get_dashboard_data( $date_range );

			// Load the view template
			$this->render_view( $dashboard_data );

		} catch ( Exception $e ) {
			$this->logger->error( 'Failed to render main dashboard page', array(
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString(),
			) );

			wp_die( esc_html__( 'Failed to load dashboard.', 'smart-cycle-discounts' ) );
		}
	}

	/**
	 * Get dashboard data.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string    $date_range    Date range.
	 * @return   array                    Dashboard data.
	 */
	private function get_dashboard_data( $date_range ): array {
		// Get overview metrics from analytics dashboard (includes pre-calculated trends)
		$metrics = $this->analytics_dashboard->get_dashboard_metrics( $date_range, true );

		// Get campaign status breakdown
		$campaign_stats = $this->get_campaign_stats();

		// Get top 3 campaigns (free tier limit)
		$top_campaigns = $this->get_top_campaigns( 3, $date_range );

		// Get recent activity (last 5 events for free tier)
		$recent_activity = $this->get_recent_activity( 5 );

		// Get campaign health checks
		$campaign_health = $this->get_campaign_health();

		// Get smart campaign suggestions
		$campaign_suggestions = $this->get_campaign_suggestions();

		return array(
			'metrics' => $metrics,
			'campaign_stats' => $campaign_stats,
			'top_campaigns' => $top_campaigns,
			'recent_activity' => $recent_activity,
			'campaign_health' => $campaign_health,
			'campaign_suggestions' => $campaign_suggestions,
			'is_premium' => $this->feature_gate->is_premium(),
			'campaign_limit' => $this->feature_gate->get_campaign_limit(),
		);
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
			'active' => 0,
			'scheduled' => 0,
			'paused' => 0,
			'expired' => 0,
			'draft' => 0,
		);

		foreach ( $stats as $stat ) {
			$status = $stat['status'];
			$count = absint( $stat['count'] );

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
	 * @param    int       $limit         Number of campaigns to retrieve.
	 * @param    string    $date_range    Date range.
	 * @return   array                    Top campaigns.
	 */
	private function get_top_campaigns( $limit, $date_range ): array {
		// Get all active campaigns
		$campaigns = $this->campaign_repository->find_all( array(
			'status' => 'active',
			'orderby' => 'created_at',
			'order' => 'DESC',
			'limit' => $limit,
		) );

		if ( empty( $campaigns ) ) {
			return array();
		}

		// Convert campaign objects to arrays and get IDs
		$campaign_data = array();
		$campaign_ids = array();
		foreach ( $campaigns as $campaign ) {
			$campaign_ids[] = $campaign->get_id();
			$campaign_data[] = array(
				'id' => $campaign->get_id(),
				'name' => $campaign->get_name(),
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
				'id' => $campaign_id,
				'name' => $campaign['name'],
				'status' => $campaign['status'],
				'revenue' => isset( $metrics[ $campaign_id ]['revenue'] ) ? $metrics[ $campaign_id ]['revenue'] : 0,
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
	 * @param    int    $limit    Number of events to retrieve.
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
			'status' => $this->map_status_to_dashboard( $aggregate_health['overall_status'] ),
			'issues' => array(),
			'warnings' => array(),
			'success_messages' => array(),
			'categories' => array(
				'configuration' => array( 'status' => 'healthy', 'count' => 0 ),
				'coverage' => array( 'status' => 'healthy', 'count' => 0 ),
				'schedule' => array( 'status' => 'healthy', 'count' => 0 ),
				'discount' => array( 'status' => 'healthy', 'count' => 0 ),
				'stock' => array( 'status' => 'healthy', 'count' => 0 ),
				'conflicts' => array( 'status' => 'healthy', 'count' => 0 ),
			),
			'quick_stats' => array(
				'total_analyzed' => $aggregate_health['total_campaigns_analyzed'],
				'issues_count' => count( $aggregate_health['critical_issues'] ),
				'warnings_count' => count( $aggregate_health['warnings'] ),
			),
		);

		// Map critical issues
		foreach ( $aggregate_health['critical_issues'] as $issue ) {
			$health['issues'][] = array(
				'type' => isset( $issue['category'] ) ? $issue['category'] : 'general',
				'message' => $issue['message'],
			);
		}

		// Map warnings
		foreach ( $aggregate_health['warnings'] as $warning ) {
			$health['warnings'][] = array(
				'type' => isset( $warning['category'] ) ? $warning['category'] : 'general',
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
		$active_campaigns = array_filter( $campaigns, function( $c ) {
			return 'active' === $c['status'];
		} );
		$active_count = count( $active_campaigns );
		$campaign_limit = $this->feature_gate->get_campaign_limit();

		if ( 0 !== $campaign_limit && $active_count >= $campaign_limit ) {
			$health['issues'][] = array(
				'type' => 'limit_reached',
				'message' => sprintf(
					/* translators: %d: campaign limit */
					__( 'Campaign limit reached (%d active campaigns)', 'smart-cycle-discounts' ),
					$campaign_limit
				),
			);
			$health['status'] = 'critical';
		} elseif ( 0 !== $campaign_limit && $active_count >= ( $campaign_limit * 0.67 ) ) {
			$health['warnings'][] = array(
				'type' => 'approaching_limit',
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
		$health['quick_stats']['issues_count'] = count( $health['issues'] );
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
			'status' => 'success',
			'issues' => array(),
			'warnings' => array(),
			'success_messages' => array(),
			'categories' => array(
				'configuration' => array( 'status' => 'healthy', 'count' => 0 ),
				'schedule' => array( 'status' => 'healthy', 'count' => 0 ),
				'conflicts' => array( 'status' => 'healthy', 'count' => 0 ),
			),
			'quick_stats' => array(
				'total_analyzed' => 0,
				'issues_count' => 0,
				'warnings_count' => 0,
			),
		);
	}

	/**
	 * Map service status to dashboard status.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string    $service_status    Status from health service.
	 * @return   string                       Dashboard status.
	 */
	private function map_status_to_dashboard( $service_status ): string {
		$status_map = array(
			'excellent' => 'success',
			'good' => 'success',
			'fair' => 'warning',
			'poor' => 'critical',
		);

		return isset( $status_map[ $service_status ] ) ? $status_map[ $service_status ] : 'success';
	}

	/**
	 * Map category data to dashboard category format.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array    $category_data    Category data from service.
	 * @return   array                      Dashboard category format.
	 */
	private function map_category_status( $category_data ): array {
		$critical_count = isset( $category_data['critical'] ) ? $category_data['critical'] : 0;
		$warning_count = isset( $category_data['warning'] ) ? $category_data['warning'] : 0;

		$status = 'healthy';
		if ( $critical_count > 0 ) {
			$status = 'critical';
		} elseif ( $warning_count > 0 ) {
			$status = 'warning';
		}

		return array(
			'status' => $status,
			'count' => $critical_count + $warning_count,
		);
	}

	/**
	 * Get campaign suggestions based on upcoming seasonal events with smart timing.
	 *
	 * Uses intelligent lead time calculations to show suggestions at optimal creation times.
	 * Prefers showing 1 suggestion at a time, but will show multiple if their optimal
	 * creation windows overlap.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   array    Array of campaign suggestions.
	 */
	private function get_campaign_suggestions(): array {
		$all_events = $this->get_event_definitions();
		$qualifying_events = array();

		// Use WordPress timezone for user-facing date calculations
		$current_year = intval( wp_date( 'Y' ) );
		$now = current_time( 'timestamp' );

		foreach ( $all_events as $event ) {
			// Calculate actual event date for this year
			$event_date = $this->calculate_event_date( $event, $current_year );

			// If event already passed this year, check next year
			if ( $event_date < $now ) {
				$event_date = $this->calculate_event_date( $event, $current_year + 1 );
			}

			// Calculate optimal creation window
			$window = $this->calculate_suggestion_window( $event, $event_date );

			// Check if we're currently in the optimal creation window
			if ( $window['in_window'] ) {
				$event['event_date'] = $event_date;
				$event['window'] = $window;
				$qualifying_events[] = $event;
			}
		}

		// Smart filtering: Remove weekend_sale if major events are nearby
		$qualifying_events = $this->filter_weekend_sale_by_major_events( $qualifying_events, $all_events, $current_year );

		// No qualifying events
		if ( empty( $qualifying_events ) ) {
			return array();
		}

		// Sort by priority (higher first), then by days until optimal
		usort( $qualifying_events, function( $a, $b ) {
			if ( $a['priority'] !== $b['priority'] ) {
				return $b['priority'] - $a['priority'];
			}
			return $a['window']['days_until_optimal'] - $b['window']['days_until_optimal'];
		} );

		// Smart display logic: 1 suggestion preferred, multiple only if windows overlap
		return $this->select_suggestions_by_overlap( $qualifying_events );
	}

	/**
	 * Get event definitions with lead time intelligence.
	 *
	 * Each event includes timing parameters that determine when to show the suggestion.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   array    Array of event definitions.
	 */
	private function get_event_definitions(): array {
		return array(
			array(
				'id' => 'valentines',
			'name' => __( 'Valentine\047s Day', 'smart-cycle-discounts' ),
			'category' => 'major',
			'icon' => '❤️',
			'month' => 2,
			'day' => 14,
			'duration_days' => 7,
			'start_offset' => -7,
			'lead_time' => array(
				'base_prep' => 2,
				'inventory' => 3,
				'marketing' => 9,
				'flexibility' => 5,
				),
				'recommendations' => array(
					__( 'Check inventory levels for popular gift items', 'smart-cycle-discounts' ),
					__( 'Plan your Valentine\'s Day product selection', 'smart-cycle-discounts' ),
					__( 'Review last year\'s performance data', 'smart-cycle-discounts' ),
					__( 'Prepare gift wrapping or romantic packaging options', 'smart-cycle-discounts' ),
					__( 'Set up gift guides for different budgets', 'smart-cycle-discounts' ),
				),
				'suggested_discount' => array(
					'min' => 15,
					'max' => 25,
					'optimal' => 20,
				),
				'description' => __( 'Perfect time for gift promotions and special offers', 'smart-cycle-discounts' ),
				'statistics' => array(
					'total_spending' => __( '$25.8 billion Valentine\'s spending (2023)', 'smart-cycle-discounts' ),
					'avg_per_person' => __( '$192 average spending per person', 'smart-cycle-discounts' ),
					'peak_category' => __( 'Jewelry: 34% of purchases', 'smart-cycle-discounts' ),
				),
				'tips' => array(
					__( 'Start promoting 1 week before Valentine\'s Day', 'smart-cycle-discounts' ),
					__( 'Create "Gifts for Him" and "Gifts for Her" categories', 'smart-cycle-discounts' ),
					__( 'Offer free gift wrapping or romantic packaging', 'smart-cycle-discounts' ),
					__( 'Highlight same-day or express shipping options', 'smart-cycle-discounts' ),
				),
				'best_practices' => array(
					__( 'Launch 7-10 days before Valentine\'s Day', 'smart-cycle-discounts' ),
					__( 'Create last-minute gift section 2 days before', 'smart-cycle-discounts' ),
					__( 'Promote express/same-day shipping prominently', 'smart-cycle-discounts' ),
					__( 'Bundle complementary gifts (flowers + chocolate)', 'smart-cycle-discounts' ),
				),
				'priority' => 70,
			),
			array(
				'id' => 'easter',
				'name' => __( 'Easter', 'smart-cycle-discounts' ),
				'category' => 'major',
				'icon' => '🐰',
				'duration_days' => 7,
				'start_offset' => -7,
				'calculate_date' => function( $year ) {
					// Easter calculation using Computus algorithm
					// Easter is the first Sunday after the first full moon after March 21
					$a = $year % 19;
					$b = intval( $year / 100 );
					$c = $year % 100;
					$d = intval( $b / 4 );
					$e = $b % 4;
					$f = intval( ( $b + 8 ) / 25 );
					$g = intval( ( $b - $f + 1 ) / 3 );
					$h = ( 19 * $a + $b - $d - $g + 15 ) % 30;
					$i = intval( $c / 4 );
					$k = $c % 4;
					$l = ( 32 + 2 * $e + 2 * $i - $h - $k ) % 7;
					$m = intval( ( $a + 11 * $h + 22 * $l ) / 451 );
					$month = intval( ( $h + $l - 7 * $m + 114 ) / 31 );
					$day = ( ( $h + $l - 7 * $m + 114 ) % 31 ) + 1;

					return mktime( 0, 0, 0, $month, $day, $year );
				},
				'lead_time' => array(
					'base_prep' => 2,
					'inventory' => 3,
					'marketing' => 9,
					'flexibility' => 5,
				),
				'recommendations' => array(
					__( 'Stock spring-themed gift baskets and candy', 'smart-cycle-discounts' ),
					__( 'Feature pastel colors and spring imagery', 'smart-cycle-discounts' ),
					__( 'Create bundles for Easter egg hunts and decorations', 'smart-cycle-discounts' ),
					__( 'Offer last-minute gift wrapping services', 'smart-cycle-discounts' ),
				),
				'suggested_discount' => array(
					'min' => 15,
					'max' => 25,
					'optimal' => 20,
				),
				'description' => __( 'Spring celebration with family-focused shopping', 'smart-cycle-discounts' ),
				'statistics' => array(
					'spending' => __( '$20 billion in Easter spending (2023)', 'smart-cycle-discounts' ),
					'avg_spend' => __( '$192 per household average', 'smart-cycle-discounts' ),
					'categories' => __( 'Candy (90%), gifts (50%), clothing (38%)', 'smart-cycle-discounts' ),
				),
				'tips' => array(
					__( 'Start promoting 2 weeks before Easter Sunday', 'smart-cycle-discounts' ),
					__( 'Bundle candy with toys and gifts', 'smart-cycle-discounts' ),
					__( 'Highlight spring fashion and outdoor items', 'smart-cycle-discounts' ),
				),
				'priority' => 65,
			),
			array(
				'id' => 'spring_sale',
			'name' => __( 'Spring Sale', 'smart-cycle-discounts' ),
			'category' => 'seasonal',
			'icon' => '🌸',
			'month' => 3,
			'day' => 20,
			'duration_days' => 14,
			'start_offset' => 0,
			'lead_time' => array(
				'base_prep' => 2,
				'inventory' => 3,
				'marketing' => 5,
				'flexibility' => 3,
				),
				'recommendations' => array(
					__( 'Review winter inventory for clearance opportunities', 'smart-cycle-discounts' ),
					__( 'Identify spring products to feature', 'smart-cycle-discounts' ),
					__( 'Plan seasonal category updates', 'smart-cycle-discounts' ),
					__( 'Update homepage banners with spring themes', 'smart-cycle-discounts' ),
				),
				'suggested_discount' => array(
					'min' => 20,
					'max' => 30,
					'optimal' => 25,
				),
				'description' => __( 'Welcome spring with fresh discounts and clearance', 'smart-cycle-discounts' ),
				'statistics' => array(
					'seasonal_spending' => __( '8 billion spring shopping spending', 'smart-cycle-discounts' ),
					'clearance_demand' => __( '65% of shoppers seek spring clearance deals', 'smart-cycle-discounts' ),
					'refresh_mindset' => __( 'Spring cleaning drives 40% purchase decisions', 'smart-cycle-discounts' ),
				),
				'tips' => array(
					__( 'Promote winter clearance alongside new spring arrivals', 'smart-cycle-discounts' ),
					__( 'Create "Spring Refresh" themed bundles', 'smart-cycle-discounts' ),
					__( 'Target outdoor and garden products', 'smart-cycle-discounts' ),
					__( 'Use fresh, bright imagery in marketing', 'smart-cycle-discounts' ),
				),
				'best_practices' => array(
					__( 'Start clearance early to make room for spring inventory', 'smart-cycle-discounts' ),
					__( 'Bundle winter clearance with spring must-haves', 'smart-cycle-discounts' ),
					__( 'Update site colors and themes for seasonal appeal', 'smart-cycle-discounts' ),
					__( 'Promote renewal and fresh start messaging', 'smart-cycle-discounts' ),
				),
				'priority' => 40,
			),
			array(
				'id' => 'mothers_day',
			'name' => __( 'Mother\047s Day', 'smart-cycle-discounts' ),
			'category' => 'major',
			'icon' => '👩',
			'duration_days' => 7,
			'start_offset' => -7,
			'calculate_date' => function( $year ) {
				// Mother's Day is 2nd Sunday in May
				$first_day_of_may = mktime( 0, 0, 0, 5, 1, $year );
				$first_day_of_week = intval( gmdate( 'w', $first_day_of_may ) );

				// Calculate days until first Sunday
				$days_until_first_sunday = ( 0 === $first_day_of_week ) ? 0 : ( 7 - $first_day_of_week );

				// 2nd Sunday is first Sunday + 7 days
				$second_sunday_day = 1 + $days_until_first_sunday + 7;

				return mktime( 0, 0, 0, 5, $second_sunday_day, $year );
			},
			'lead_time' => array(
				'base_prep' => 2,
				'inventory' => 3,
				'marketing' => 9,
				'flexibility' => 5,
				),
				'recommendations' => array(
					__( 'Check stock levels for popular Mother\'s Day gifts', 'smart-cycle-discounts' ),
					__( 'Plan gift sets and curated collections', 'smart-cycle-discounts' ),
					__( 'Review shipping deadlines for timely delivery', 'smart-cycle-discounts' ),
					__( 'Prepare personalization options if available', 'smart-cycle-discounts' ),
					__( 'Create Mother\'s Day gift guides for different budgets', 'smart-cycle-discounts' ),
				),
				'suggested_discount' => array(
					'min' => 15,
					'max' => 25,
					'optimal' => 20,
				),
				'description' => __( 'Help customers find perfect gifts for Mom', 'smart-cycle-discounts' ),
				'statistics' => array(
					'total_spending' => __( '$35.7 billion Mother\'s Day spending (2023)', 'smart-cycle-discounts' ),
					'celebrating' => __( '84% of Americans celebrate', 'smart-cycle-discounts' ),
					'top_gifts' => __( 'Flowers, jewelry, and gift cards', 'smart-cycle-discounts' ),
				),
				'tips' => array(
					__( 'Promote gift sets and curated collections', 'smart-cycle-discounts' ),
					__( 'Offer personalization options if possible', 'smart-cycle-discounts' ),
					__( 'Create Mother\'s Day gift guide', 'smart-cycle-discounts' ),
					__( 'Emphasize shipping deadlines for timely delivery', 'smart-cycle-discounts' ),
				),
				'best_practices' => array(
					__( 'Start campaign 7-10 days before Mother\'s Day', 'smart-cycle-discounts' ),
					__( 'Offer gift wrapping and personalization', 'smart-cycle-discounts' ),
					__( 'Create tiered gift guides by budget', 'smart-cycle-discounts' ),
					__( 'Promote last-minute digital gift cards', 'smart-cycle-discounts' ),
				),
				'priority' => 70,
			),
			array(
				'id' => 'fathers_day',
				'name' => __( 'Father\047s Day', 'smart-cycle-discounts' ),
				'category' => 'major',
				'icon' => '👨',
				'duration_days' => 7,
				'start_offset' => -7,
				'calculate_date' => function( $year ) {
					// Father's Day is 3rd Sunday in June
					$first_day_of_june = mktime( 0, 0, 0, 6, 1, $year );
					$first_day_of_week = intval( gmdate( 'w', $first_day_of_june ) );

					// Calculate days until first Sunday
					$days_until_first_sunday = ( 0 === $first_day_of_week ) ? 0 : ( 7 - $first_day_of_week );

					// 3rd Sunday is first Sunday + 14 days (2 weeks)
					$third_sunday_day = 1 + $days_until_first_sunday + 14;

					return mktime( 0, 0, 0, 6, $third_sunday_day, $year );
				},
				'lead_time' => array(
					'base_prep' => 2,
					'inventory' => 3,
					'marketing' => 9,
					'flexibility' => 5,
				),
				'recommendations' => array(
					__( 'Feature tech, tools, and outdoor gear', 'smart-cycle-discounts' ),
					__( 'Create gift bundles for different interests', 'smart-cycle-discounts' ),
					__( 'Offer gift wrapping and greeting cards', 'smart-cycle-discounts' ),
					__( 'Promote last-minute digital gift options', 'smart-cycle-discounts' ),
				),
				'suggested_discount' => array(
					'min' => 15,
					'max' => 25,
					'optimal' => 20,
				),
				'description' => __( 'Celebrate dads with targeted gift promotions', 'smart-cycle-discounts' ),
				'statistics' => array(
					'spending' => __( '$20 billion in Father\'s Day spending (2023)', 'smart-cycle-discounts' ),
					'avg_spend' => __( '$171 per household average', 'smart-cycle-discounts' ),
					'top_gifts' => __( 'Clothing, gift cards, electronics', 'smart-cycle-discounts' ),
				),
				'tips' => array(
					__( 'Start promoting 2 weeks before Father\'s Day', 'smart-cycle-discounts' ),
					__( 'Create curated gift guides by interest', 'smart-cycle-discounts' ),
					__( 'Bundle complementary products (grill + accessories)', 'smart-cycle-discounts' ),
				),
				'priority' => 70,
			),
			array(
				'id' => 'summer_sale',
			'name' => __( 'Summer Sale', 'smart-cycle-discounts' ),
			'category' => 'seasonal',
			'icon' => '☀️',
			'month' => 6,
			'day' => 21,
			'duration_days' => 21,
			'start_offset' => 0,
			'lead_time' => array(
				'base_prep' => 2,
				'inventory' => 3,
				'marketing' => 5,
				'flexibility' => 3,
				),
				'recommendations' => array(
					__( 'Review spring inventory for clearance opportunities', 'smart-cycle-discounts' ),
					__( 'Identify summer products to promote', 'smart-cycle-discounts' ),
					__( 'Plan seasonal promotions and bundles', 'smart-cycle-discounts' ),
					__( 'Update homepage with summer themes', 'smart-cycle-discounts' ),
				),
				'suggested_discount' => array(
					'min' => 25,
					'max' => 35,
					'optimal' => 30,
				),
				'description' => __( 'Kick off summer with hot deals', 'smart-cycle-discounts' ),
				'statistics' => array(
					'summer_spending' => __( '20 billion summer retail spending', 'smart-cycle-discounts' ),
					'travel_impact' => __( '60% plan summer vacations, boosting outdoor gear', 'smart-cycle-discounts' ),
					'clearance_timing' => __( 'Mid-summer clearance drives 35% more sales', 'smart-cycle-discounts' ),
				),
				'tips' => array(
					__( 'Promote outdoor, travel, and vacation products', 'smart-cycle-discounts' ),
					__( 'Create "Summer Essentials" bundles', 'smart-cycle-discounts' ),
					__( 'Offer early clearance on spring items', 'smart-cycle-discounts' ),
					__( 'Use bright, sunny imagery in promotions', 'smart-cycle-discounts' ),
				),
				'best_practices' => array(
					__( 'Launch sale at summer solstice for maximum impact', 'smart-cycle-discounts' ),
					__( 'Bundle seasonal items with evergreen products', 'smart-cycle-discounts' ),
					__( 'Target Memorial Day to July 4th corridor', 'smart-cycle-discounts' ),
					__( 'Promote free shipping for summer convenience', 'smart-cycle-discounts' ),
				),
				'priority' => 40,
			),
			array(
				'id' => 'july_4th',
				'name' => __( 'Independence Day Sale', 'smart-cycle-discounts' ),
				'category' => 'major',
				'icon' => '🎆',
				'month' => 7,
				'day' => 4,
				'duration_days' => 3,
				'start_offset' => -1,
				'lead_time' => array(
					'base_prep' => 2,
					'inventory' => 3,
					'marketing' => 7,
					'flexibility' => 5,
				),
				'recommendations' => array(
					__( 'Feature red, white, and blue themed products', 'smart-cycle-discounts' ),
					__( 'Promote outdoor and BBQ-related items', 'smart-cycle-discounts' ),
					__( 'Create party supply bundles', 'smart-cycle-discounts' ),
					__( 'Offer special patriotic gift sets', 'smart-cycle-discounts' ),
				),
				'suggested_discount' => array(
					'min' => 20,
					'max' => 40,
					'optimal' => 30,
				),
				'description' => __( 'Patriotic celebration with summer shopping peak', 'smart-cycle-discounts' ),
				'statistics' => array(
					'spending' => __( '$7.5 billion in July 4th spending (2023)', 'smart-cycle-discounts' ),
					'celebrations' => __( '87% of Americans celebrate', 'smart-cycle-discounts' ),
					'categories' => __( 'Food (68%), apparel (14%), decorations (12%)', 'smart-cycle-discounts' ),
				),
				'tips' => array(
					__( 'Start promoting 1-2 weeks before holiday', 'smart-cycle-discounts' ),
					__( 'Bundle summer and patriotic items together', 'smart-cycle-discounts' ),
					__( 'Extend sale through the full holiday weekend', 'smart-cycle-discounts' ),
				),
				'priority' => 60,
			),
			array(
				'id' => 'back_to_school',
				'name' => __( 'Back to School Sale', 'smart-cycle-discounts' ),
				'category' => 'ongoing',
				'icon' => '🎒',
				'month' => 8,
				'day' => 15,
				'duration_days' => 21,
				'start_offset' => 0,
				'lead_time' => array(
					'base_prep' => 3,
					'inventory' => 3,
					'marketing' => 7,
					'flexibility' => 3,
				),
				'recommendations' => array(
					__( 'Review school and office supply inventory', 'smart-cycle-discounts' ),
					__( 'Identify products popular with students and parents', 'smart-cycle-discounts' ),
					__( 'Plan bundle deals for back-to-school needs', 'smart-cycle-discounts' ),
					__( 'Promote early-bird discounts', 'smart-cycle-discounts' ),
				),
				'suggested_discount' => array(
					'min' => 15,
					'max' => 25,
					'optimal' => 20,
				),
				'description' => __( 'Help students and parents gear up for the new school year', 'smart-cycle-discounts' ),
				'statistics' => array(
					'school_spending' => __( '7 billion back-to-school spending (2023)', 'smart-cycle-discounts' ),
					'avg_per_family' => __( '90 average per family spending', 'smart-cycle-discounts' ),
					'peak_shopping' => __( 'July-August: 85% of shopping occurs', 'smart-cycle-discounts' ),
				),
				'tips' => array(
					__( 'Start promotions in late July for early shoppers', 'smart-cycle-discounts' ),
					__( 'Create grade-specific product bundles', 'smart-cycle-discounts' ),
					__( 'Offer bulk discounts for multiple children', 'smart-cycle-discounts' ),
					__( 'Promote tax-free shopping days where applicable', 'smart-cycle-discounts' ),
				),
				'best_practices' => array(
					__( 'Run campaign for full 3 weeks in August', 'smart-cycle-discounts' ),
					__( 'Target parents with age-appropriate bundles', 'smart-cycle-discounts' ),
					__( 'Increase inventory 2x for popular school items', 'smart-cycle-discounts' ),
					__( 'Clear seasonal inventory before September', 'smart-cycle-discounts' ),
				),
				'priority' => 60,
			),
			array(
				'id' => 'labor_day',
				'name' => __( 'Labor Day Sale', 'smart-cycle-discounts' ),
				'category' => 'major',
				'icon' => '💼',
				'duration_days' => 3,
				'start_offset' => -1,
				'calculate_date' => function( $year ) {
					// Labor Day is the 1st Monday in September
					$first_day_of_september = mktime( 0, 0, 0, 9, 1, $year );
					$first_day_of_week = intval( gmdate( 'w', $first_day_of_september ) );

					// Calculate days until first Monday (Monday = 1)
					$days_until_first_monday = ( 1 === $first_day_of_week ) ? 0 : ( ( 1 - $first_day_of_week + 7 ) % 7 );

					$first_monday_day = 1 + $days_until_first_monday;

					return mktime( 0, 0, 0, 9, $first_monday_day, $year );
				},
				'lead_time' => array(
					'base_prep' => 2,
					'inventory' => 3,
					'marketing' => 7,
					'flexibility' => 5,
				),
				'recommendations' => array(
					__( 'Feature end-of-summer clearance items', 'smart-cycle-discounts' ),
					__( 'Promote furniture, appliances, and home goods', 'smart-cycle-discounts' ),
					__( 'Bundle back-to-work and fall transition items', 'smart-cycle-discounts' ),
					__( 'Offer extended weekend sale (Fri-Mon)', 'smart-cycle-discounts' ),
				),
				'suggested_discount' => array(
					'min' => 25,
					'max' => 40,
					'optimal' => 35,
				),
				'description' => __( 'End of summer with major shopping weekend', 'smart-cycle-discounts' ),
				'statistics' => array(
					'spending' => __( '$4.6 billion in Labor Day weekend spending (2023)', 'smart-cycle-discounts' ),
					'shoppers' => __( '42% of Americans shop Labor Day sales', 'smart-cycle-discounts' ),
					'categories' => __( 'Furniture (33%), appliances (28%), clothing (25%)', 'smart-cycle-discounts' ),
				),
				'tips' => array(
					__( 'Start promoting 1-2 weeks before the holiday', 'smart-cycle-discounts' ),
					__( 'Clear summer inventory with deep discounts', 'smart-cycle-discounts' ),
					__( 'Extend sale through the full 3-day weekend', 'smart-cycle-discounts' ),
				),
				'priority' => 65,
			),
			array(
				'id' => 'halloween',
			'name' => __( 'Halloween', 'smart-cycle-discounts' ),
			'category' => 'major',
			'icon' => '🎃',
			'month' => 10,
			'day' => 31,
			'duration_days' => 7,
			'start_offset' => -7,
			'lead_time' => array(
				'base_prep' => 2,
				'inventory' => 3,
				'marketing' => 7,
				'flexibility' => 5,
				),
				'recommendations' => array(
					__( 'Check inventory for Halloween-themed products', 'smart-cycle-discounts' ),
					__( 'Plan themed bundles and party packages', 'smart-cycle-discounts' ),
					__( 'Review last year\'s Halloween performance', 'smart-cycle-discounts' ),
					__( 'Prepare costume and decor item highlights', 'smart-cycle-discounts' ),
					__( 'Schedule clearance pricing for Nov 1st', 'smart-cycle-discounts' ),
				),
				'suggested_discount' => array(
					'min' => 20,
					'max' => 30,
					'optimal' => 25,
				),
				'description' => __( 'Spooky savings for Halloween shoppers', 'smart-cycle-discounts' ),
				'statistics' => array(
					'total_spending' => __( '$12.2 billion Halloween spending (2023)', 'smart-cycle-discounts' ),
					'participation' => __( '73% of Americans plan to celebrate', 'smart-cycle-discounts' ),
					'peak_shopping' => __( 'Oct 15-25: peak shopping period', 'smart-cycle-discounts' ),
				),
				'tips' => array(
					__( 'Start promotions 10-14 days before Halloween', 'smart-cycle-discounts' ),
					__( 'Create themed bundles and party packages', 'smart-cycle-discounts' ),
					__( 'Highlight last-minute costume and decor items', 'smart-cycle-discounts' ),
					__( 'Clear inventory with deeper discounts after Oct 25', 'smart-cycle-discounts' ),
				),
				'best_practices' => array(
					__( 'Launch sale 10-14 days before Halloween', 'smart-cycle-discounts' ),
					__( 'Create themed product bundles for parties', 'smart-cycle-discounts' ),
					__( 'Offer express shipping in final 3 days', 'smart-cycle-discounts' ),
					__( 'Start 50% off clearance on November 1st', 'smart-cycle-discounts' ),
				),
				'priority' => 70,
			),
			array(
				'id' => 'black_friday',
				'name' => __( 'Black Friday / Cyber Monday', 'smart-cycle-discounts' ),
				'category' => 'major',
				'icon' => '🛍️',
				'duration_days' => 4,
				'start_offset' => 0,
				'calculate_date' => function( $year ) {
					// Black Friday is the Friday after Thanksgiving
					// Thanksgiving is the 4th Thursday in November
					$first_day_of_november = mktime( 0, 0, 0, 11, 1, $year );
					$first_day_of_week = intval( gmdate( 'w', $first_day_of_november ) );

					// Calculate days until first Thursday (Thursday = 4)
					$days_until_first_thursday = ( 4 - $first_day_of_week + 7 ) % 7;
					if ( 0 === $days_until_first_thursday && 4 !== $first_day_of_week ) {
						$days_until_first_thursday = 7;
					}

					// 4th Thursday is first Thursday + 21 days (3 weeks)
					$fourth_thursday_day = 1 + $days_until_first_thursday + 21;

					// Black Friday is the day after (Friday)
					$black_friday_day = $fourth_thursday_day + 1;

					return mktime( 0, 0, 0, 11, $black_friday_day, $year );
				},
				'lead_time' => array(
					'base_prep' => 2,
					'inventory' => 3,
					'marketing' => 7,
					'flexibility' => 5,
				),
				'recommendations' => array(
					__( 'Order inventory NOW - suppliers get overwhelmed', 'smart-cycle-discounts' ),
					__( 'Stock 2-3x normal levels for best sellers', 'smart-cycle-discounts' ),
					__( 'Test checkout flow and payment processing capacity', 'smart-cycle-discounts' ),
					__( 'Schedule extra customer support staff', 'smart-cycle-discounts' ),
					__( 'Prepare email sequences for multi-day promotion', 'smart-cycle-discounts' ),
					__( 'Set up abandoned cart recovery for high traffic', 'smart-cycle-discounts' ),
				),
				'suggested_discount' => array(
					'min' => 30,
					'max' => 50,
					'optimal' => 40,
				),
				'description' => __( 'Biggest shopping event of the year - maximize sales!', 'smart-cycle-discounts' ),
				'statistics' => array(
					'global_revenue' => __( '$9.8 billion in online sales (2023)', 'smart-cycle-discounts' ),
					'avg_discount' => __( '37% average discount across retailers', 'smart-cycle-discounts' ),
					'conversion_lift' => __( '3.2x normal conversion rate', 'smart-cycle-discounts' ),
					'mobile_share' => __( '54% of sales from mobile devices', 'smart-cycle-discounts' ),
				),
				'tips' => array(
					__( 'Start promoting 2-3 weeks early to build anticipation', 'smart-cycle-discounts' ),
					__( 'Use countdown timers to create urgency', 'smart-cycle-discounts' ),
					__( 'Bundle products for higher average order value', 'smart-cycle-discounts' ),
					__( 'Extend through Cyber Monday for maximum reach', 'smart-cycle-discounts' ),
					__( 'Prepare 2-3x normal inventory levels', 'smart-cycle-discounts' ),
				),
				'best_practices' => array(
					__( 'Launch at midnight EST for early bird shoppers', 'smart-cycle-discounts' ),
					__( 'Offer tiered discounts (spend more, save more)', 'smart-cycle-discounts' ),
					__( 'Send VIP early access emails 24 hours before', 'smart-cycle-discounts' ),
					__( 'Ensure mobile checkout is optimized', 'smart-cycle-discounts' ),
					__( 'Monitor inventory in real-time to prevent stockouts', 'smart-cycle-discounts' ),
				),
				'priority' => 100,
			),
			array(
				'id' => 'small_business_saturday',
				'name' => __( 'Small Business Saturday', 'smart-cycle-discounts' ),
				'category' => 'major',
				'icon' => '🏪',
				'duration_days' => 1,
				'start_offset' => 0,
				'calculate_date' => function( $year ) {
					// Small Business Saturday is the Saturday after Thanksgiving
					// Thanksgiving is the 4th Thursday in November
					$first_day_of_november = mktime( 0, 0, 0, 11, 1, $year );
					$first_day_of_week = intval( gmdate( 'w', $first_day_of_november ) );

					// Calculate days until first Thursday (Thursday = 4)
					$days_until_first_thursday = ( 4 - $first_day_of_week + 7 ) % 7;
					if ( 0 === $days_until_first_thursday && 4 !== $first_day_of_week ) {
						$days_until_first_thursday = 7;
					}

					// 4th Thursday is first Thursday + 21 days (3 weeks)
					$fourth_thursday_day = 1 + $days_until_first_thursday + 21;

					// Small Business Saturday is 2 days after Thanksgiving (Saturday)
					$small_business_saturday_day = $fourth_thursday_day + 2;

					return mktime( 0, 0, 0, 11, $small_business_saturday_day, $year );
				},
				'lead_time' => array(
					'base_prep' => 2,
					'inventory' => 3,
					'marketing' => 5,
					'flexibility' => 5,
				),
				'recommendations' => array(
					__( 'Highlight your local/small business story', 'smart-cycle-discounts' ),
					__( 'Offer exclusive in-store or online-only deals', 'smart-cycle-discounts' ),
					__( 'Partner with other small businesses for bundles', 'smart-cycle-discounts' ),
					__( 'Promote same-day pickup or local delivery', 'smart-cycle-discounts' ),
				),
				'suggested_discount' => array(
					'min' => 15,
					'max' => 30,
					'optimal' => 20,
				),
				'description' => __( 'Support local businesses with community-focused shopping', 'smart-cycle-discounts' ),
				'statistics' => array(
					'spending' => __( '$17.9 billion in Small Business Saturday spending (2023)', 'smart-cycle-discounts' ),
					'shoppers' => __( '122 million shoppers participate', 'smart-cycle-discounts' ),
					'awareness' => __( '77% of Americans aware of the day', 'smart-cycle-discounts' ),
				),
				'tips' => array(
					__( 'Emphasize your small business identity', 'smart-cycle-discounts' ),
					__( 'Share your story and community impact', 'smart-cycle-discounts' ),
					__( 'Offer personalized service and experiences', 'smart-cycle-discounts' ),
				),
				'priority' => 85,
			),
			array(
				'id' => 'christmas',
			'name' => __( 'Christmas Season', 'smart-cycle-discounts' ),
			'category' => 'major',
			'icon' => '🎄',
			'month' => 12,
			'day' => 25,
			'duration_days' => 21,
			'start_offset' => -21,
			'lead_time' => array(
				'base_prep' => 3,
				'inventory' => 5,
				'marketing' => 10,
				'flexibility' => 7,
				),
				'recommendations' => array(
					__( 'Finalize holiday inventory - suppliers have long lead times', 'smart-cycle-discounts' ),
					__( 'Stock gift-friendly items and wrapping supplies', 'smart-cycle-discounts' ),
					__( 'Create gift guides for different recipient types', 'smart-cycle-discounts' ),
					__( 'Set up holiday shipping deadline banners', 'smart-cycle-discounts' ),
					__( 'Plan extended customer service hours', 'smart-cycle-discounts' ),
					__( 'Test gift card and gift message features', 'smart-cycle-discounts' ),
				),
				'suggested_discount' => array(
					'min' => 20,
					'max' => 40,
					'optimal' => 30,
				),
				'description' => __( 'Holiday shopping season - peak sales opportunity', 'smart-cycle-discounts' ),
				'statistics' => array(
					'season_revenue' => __( '$936 billion holiday season spending (2023)', 'smart-cycle-discounts' ),
					'online_growth' => __( '14% year-over-year online growth', 'smart-cycle-discounts' ),
					'peak_days' => __( 'Dec 17-23: highest shopping days', 'smart-cycle-discounts' ),
					'gift_purchases' => __( '76% of purchases are gifts', 'smart-cycle-discounts' ),
				),
				'tips' => array(
					__( 'Start promotions December 1st at the latest', 'smart-cycle-discounts' ),
					__( 'Offer gift wrapping or gift messages', 'smart-cycle-discounts' ),
					__( 'Highlight last-minute shipping deadlines', 'smart-cycle-discounts' ),
					__( 'Create gift guides for different budgets', 'smart-cycle-discounts' ),
					__( 'Extend returns policy through January', 'smart-cycle-discounts' ),
				),
				'best_practices' => array(
					__( 'Run campaign for full 3 weeks before Christmas', 'smart-cycle-discounts' ),
					__( 'Increase discounts in final week (Dec 18-24)', 'smart-cycle-discounts' ),
					__( 'Promote gift cards heavily in last 3 days', 'smart-cycle-discounts' ),
					__( 'Prepare customer service for high volume', 'smart-cycle-discounts' ),
					__( 'Stock popular items 2x normal levels', 'smart-cycle-discounts' ),
				),
				'priority' => 100,
			),
			array(
				'id' => 'new_year',
				'name' => __( 'New Year Sale', 'smart-cycle-discounts' ),
				'category' => 'ongoing',
				'icon' => '🎊',
				'month' => 1,
				'day' => 1,
				'duration_days' => 14,
				'start_offset' => 0,
				'lead_time' => array(
					'base_prep' => 3,
					'inventory' => 3,
					'marketing' => 7,
					'flexibility' => 3,
				),
				'recommendations' => array(
					__( 'Review holiday season inventory and plan clearance', 'smart-cycle-discounts' ),
					__( 'Identify products for New Year fresh start themes', 'smart-cycle-discounts' ),
					__( 'Plan New Year, New You marketing campaigns', 'smart-cycle-discounts' ),
					__( 'Promote fitness, wellness, and organization products', 'smart-cycle-discounts' ),
				),
				'suggested_discount' => array(
					'min' => 25,
					'max' => 35,
					'optimal' => 30,
				),
				'description' => __( 'Start the year fresh with clearance and new beginnings', 'smart-cycle-discounts' ),
				'statistics' => array(
					'resolution_market' => __( '$10 billion spent on New Year resolutions', 'smart-cycle-discounts' ),
					'fitness_surge' => __( '12% of annual gym memberships sold in January', 'smart-cycle-discounts' ),
					'organization' => __( '80% of resolutions involve self-improvement', 'smart-cycle-discounts' ),
				),
				'tips' => array(
					__( 'Promote fitness, wellness, and organization products', 'smart-cycle-discounts' ),
					__( 'Create "New Year, New You" themed bundles', 'smart-cycle-discounts' ),
					__( 'Offer discounts on goal-setting and productivity items', 'smart-cycle-discounts' ),
					__( 'Clear holiday inventory with extended clearance pricing', 'smart-cycle-discounts' ),
				),
				'best_practices' => array(
					__( 'Launch New Year sale on January 1st', 'smart-cycle-discounts' ),
					__( 'Target health, fitness, and self-improvement products', 'smart-cycle-discounts' ),
					__( 'Use fresh start and new beginnings messaging', 'smart-cycle-discounts' ),
					__( 'Combine with post-holiday clearance strategy', 'smart-cycle-discounts' ),
				),
				'priority' => 50,
			),
			array(
				'id' => 'weekend_sale',
				'name' => __( 'Weekend Sale', 'smart-cycle-discounts' ),
				'category' => 'flexible',
				'icon' => '🎉',
				'duration_days' => 3,
				'start_offset' => 0,
				'calculate_date' => function( $year ) {
					// Calculate next upcoming Friday
					$now = current_time( 'timestamp' );
					$current_day_of_week = intval( wp_date( 'w', $now ) ); // 0 = Sunday, 5 = Friday

					// Days until next Friday (5)
					if ( 5 === $current_day_of_week ) {
						// Today is Friday - return next Friday
						$days_until_friday = 7;
					} elseif ( $current_day_of_week < 5 ) {
						// Before Friday this week
						$days_until_friday = 5 - $current_day_of_week;
					} else {
						// Saturday (6) or Sunday (0) - next Friday
						$days_until_friday = ( 7 - $current_day_of_week ) + 5;
					}

					return strtotime( "+{$days_until_friday} days", $now );
				},
				'lead_time' => array(
					'base_prep' => 3,
					'inventory' => 0,
					'marketing' => 2,
					'flexibility' => 2,
				),
				'recommendations' => array(
					__( 'Create urgency with countdown timers', 'smart-cycle-discounts' ),
					__( 'Bundle slow-moving inventory with bestsellers', 'smart-cycle-discounts' ),
					__( 'Offer free shipping threshold to increase order value', 'smart-cycle-discounts' ),
					__( 'Promote on social media Thursday evening for maximum reach', 'smart-cycle-discounts' ),
					__( 'Highlight weekend-only deals prominently', 'smart-cycle-discounts' ),
				),
				'suggested_discount' => array(
					'min' => 10,
					'max' => 20,
					'optimal' => 15,
				),
				'description' => __( 'Quick weekend flash sale - perfect for filling quiet calendar gaps', 'smart-cycle-discounts' ),
				'statistics' => array(
					'weekend_traffic' => __( 'Weekend traffic averages 20-30% higher than weekdays', 'smart-cycle-discounts' ),
					'flash_sales' => __( 'Flash sales increase conversion rates by 35%', 'smart-cycle-discounts' ),
					'urgency' => __( '70% of purchases happen in first 24 hours of limited-time offers', 'smart-cycle-discounts' ),
				),
				'tips' => array(
					__( 'Launch Friday morning to catch early weekend shoppers', 'smart-cycle-discounts' ),
					__( 'Use "Weekend Only" messaging to create urgency', 'smart-cycle-discounts' ),
					__( 'Feature products that benefit from quick decision-making', 'smart-cycle-discounts' ),
					__( 'Keep discounts modest (10-20%) for sustainable profitability', 'smart-cycle-discounts' ),
				),
				'best_practices' => array(
					__( 'Promote Thursday evening on social media', 'smart-cycle-discounts' ),
					__( 'Send email campaign Friday morning', 'smart-cycle-discounts' ),
					__( 'Add countdown timer to create urgency', 'smart-cycle-discounts' ),
					__( 'Feature fast-selling or impulse-buy products', 'smart-cycle-discounts' ),
				),
				'priority' => 15,
			),
		);
	}

	/**
	 * Calculate actual event date for a given year.
	 *
	 * Handles both fixed dates (like Christmas) and dynamic dates (like Mother's Day).
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $event Event definition.
	 * @param    int   $year  Year to calculate for.
	 * @return   int          Event timestamp.
	 */
	private function calculate_event_date( array $event, int $year ): int {
		// Check if this is a dynamic date calculation
		if ( isset( $event['calculate_date'] ) && is_callable( $event['calculate_date'] ) ) {
			return call_user_func( $event['calculate_date'], $year );
		}

		// Default: fixed date calculation
		return mktime( 0, 0, 0, $event['month'], $event['day'], $year );
	}

	/**
	 * Calculate optimal creation window for an event.
	 *
	 * This determines when merchants should see the suggestion based on lead time requirements.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $event      Event definition.
	 * @param    int   $event_date Event timestamp.
	 * @return   array             Window data with timestamps and status.
	 */
	private function calculate_suggestion_window( array $event, int $event_date ): array {
		$lead_time = $event['lead_time'];

		// Calculate total lead time (base prep + inventory + marketing)
		$total_lead_days = $lead_time['base_prep'] + $lead_time['inventory'] + $lead_time['marketing'];
		$flexibility_days = $lead_time['flexibility'];

		// Optimal date is when campaign should ideally be created
		$optimal_date = strtotime( "-{$total_lead_days} days", $event_date );

		// Window is optimal date ± flexibility
		$window_start = strtotime( "-{$flexibility_days} days", $optimal_date );
		$window_end = strtotime( "+{$flexibility_days} days", $optimal_date );

		// Use WordPress timezone for user-facing calculations
		$now = current_time( 'timestamp' );
		$in_window = ( $now >= $window_start && $now <= $window_end );
		$days_until_optimal = ceil( ( $optimal_date - $now ) / DAY_IN_SECONDS );
		$days_until_event = ceil( ( $event_date - $now ) / DAY_IN_SECONDS );
		$days_left_in_window = $in_window ? ceil( ( $window_end - $now ) / DAY_IN_SECONDS ) : 0;

		return array(
			'optimal_date' => $optimal_date,
			'window_start' => $window_start,
			'window_end' => $window_end,
			'in_window' => $in_window,
			'days_until_optimal' => abs( $days_until_optimal ),
			'days_until_event' => $days_until_event,
			'days_left_in_window' => $days_left_in_window,
			'total_lead_days' => $total_lead_days,
		);
	}

	/**
	 * Check if two event windows overlap.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $window1 First window data.
	 * @param    array $window2 Second window data.
	 * @return   bool           True if windows overlap.
	 */
	private function windows_overlap( array $window1, array $window2 ): bool {
		return ! ( $window1['window_end'] < $window2['window_start'] || $window2['window_end'] < $window1['window_start'] );
	}

	/**
	 * Select suggestions based on window overlap logic.
	 *
	 * Prefers showing 1 suggestion at a time. Shows multiple (up to 3) only if their
	 * optimal creation windows overlap, indicating events are temporally close.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $qualifying_events Events that are in their creation windows.
	 * @return   array                    Selected suggestions to display.
	 */
	private function select_suggestions_by_overlap( array $qualifying_events ): array {
		if ( empty( $qualifying_events ) ) {
			return array();
		}

		// If only one event qualifies, show it (preferred case)
		if ( 1 === count( $qualifying_events ) ) {
			return array( $this->format_suggestion( $qualifying_events[0] ) );
		}

		// Multiple events qualify - check for overlaps
		$suggestions = array( $qualifying_events[0] );

		foreach ( array_slice( $qualifying_events, 1 ) as $event ) {
			// Check if this event's window overlaps with any already selected
			$has_overlap = false;
			foreach ( $suggestions as $existing ) {
				if ( $this->windows_overlap( $event['window'], $existing['window'] ) ) {
					$has_overlap = true;
					break;
				}
			}

			// Only add if windows overlap
			if ( $has_overlap ) {
				$suggestions[] = $event;

				// Maximum 3 suggestions
				if ( count( $suggestions ) >= 3 ) {
					break;
				}
			}
		}

		// Format all selected suggestions
		return array_map( array( $this, 'format_suggestion' ), $suggestions );
	}

	/**
	 * Format event data for display.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $event Event with window data.
	 * @return   array        Formatted suggestion.
	 */
	private function format_suggestion( array $event ): array {
		$start_date = strtotime( $event['start_offset'] . ' days', $event['event_date'] );
		$end_date = strtotime( '+' . $event['duration_days'] . ' days', $start_date );

		// Format discount range
		$discount = $event['suggested_discount'];
		$discount_range = $discount['min'] . '-' . $discount['max'] . '%';

		$formatted = array(
			'id' => $event['id'],
			'name' => $event['name'],
			'icon' => $event['icon'],
			'category' => $event['category'],
			'start_date' => wp_date( 'Y-m-d', $start_date ),
			'end_date' => wp_date( 'Y-m-d', $end_date ),
			'days_until' => $event['window']['days_until_event'],
			'days_left_in_window' => $event['window']['days_left_in_window'],
			'suggested_discount' => $discount_range,
			'optimal_discount' => $discount['optimal'],
			'description' => $event['description'],
			'timing_message' => $this->get_timing_message( $event['window'] ),
		);

		// Add recommendations if available
		if ( isset( $event['recommendations'] ) && ! empty( $event['recommendations'] ) ) {
			$formatted['recommendations'] = $event['recommendations'];
		}

		// Add rich data if available - randomly select 1 from each
		if ( isset( $event['statistics'] ) && ! empty( $event['statistics'] ) ) {
			$stat_keys = array_keys( $event['statistics'] );
			$random_stat_key = $stat_keys[ array_rand( $stat_keys ) ];
			$formatted['random_statistic'] = array(
				'label' => $random_stat_key,
				'value' => $event['statistics'][ $random_stat_key ],
			);
			$formatted['statistics'] = $event['statistics']; // Keep all for reference
		}

		if ( isset( $event['tips'] ) && ! empty( $event['tips'] ) ) {
			$formatted['random_tip'] = $event['tips'][ array_rand( $event['tips'] ) ];
			$formatted['tips'] = $event['tips']; // Keep all for reference
		}

		if ( isset( $event['best_practices'] ) && ! empty( $event['best_practices'] ) ) {
			$formatted['random_best_practice'] = $event['best_practices'][ array_rand( $event['best_practices'] ) ];
			$formatted['best_practices'] = $event['best_practices']; // Keep all for reference
		}

		return $formatted;
	}

	/**
	 * Get timing explanation message.
	 *
	 * Shows urgency messaging based on days left in optimal creation window.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $window Window data.
	 * @return   string        Human-readable timing message.
	 */
	private function get_timing_message( array $window ): string {
		$days_left = $window['days_left_in_window'];
		$days_until_event = $window['days_until_event'];

		// Show urgency based on days left
		if ( $days_left <= 3 ) {
			/* translators: %d: Number of days left in optimal window */
			return sprintf(
				__( 'Urgent: Only %d days left in optimal creation window!', 'smart-cycle-discounts' ),
				$days_left
			);
		} elseif ( $days_left <= 7 ) {
			/* translators: %d: Number of days left in optimal window */
			return sprintf(
				__( 'Create soon: %d days left in optimal window', 'smart-cycle-discounts' ),
				$days_left
			);
		} else {
			/* translators: 1: Days until event, 2: Days left in optimal window */
			return sprintf(
				__( 'Perfect timing: %1$d days until event, %2$d days left to create', 'smart-cycle-discounts' ),
				$days_until_event,
				$days_left
			);
		}
	}

	/**
	 * Filter out weekend_sale if major events are within 2 weeks.
	 *
	 * Smart detection logic: Weekend Sale suggestions only appear during quiet
	 * calendar periods. If any major event is within 2 weeks before or after
	 * the weekend, hide the weekend sale to avoid competing with more important
	 * promotional opportunities.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $qualifying_events Events that qualified for display.
	 * @param    array $all_events        All event definitions.
	 * @param    int   $current_year      Current year for date calculations.
	 * @return   array                    Filtered qualifying events.
	 */
	private function filter_weekend_sale_by_major_events( array $qualifying_events, array $all_events, int $current_year ): array {
		// Check if weekend_sale is in qualifying events
		$has_weekend_sale = false;
		$weekend_sale_index = -1;
		$weekend_sale_date = 0;

		foreach ( $qualifying_events as $index => $event ) {
			if ( 'weekend_sale' === $event['id'] ) {
				$has_weekend_sale = true;
				$weekend_sale_index = $index;
				$weekend_sale_date = $event['event_date'];
				break;
			}
		}

		// No weekend sale in qualifying events - nothing to filter
		if ( ! $has_weekend_sale ) {
			return $qualifying_events;
		}

		// Check all events for major events within 2 weeks of the weekend
		$two_weeks_seconds = 14 * DAY_IN_SECONDS;
		$now = current_time( 'timestamp' );

		foreach ( $all_events as $event ) {
			// Skip weekend_sale itself
			if ( 'weekend_sale' === $event['id'] ) {
				continue;
			}

			// Only check major events
			if ( 'major' !== $event['category'] ) {
				continue;
			}

			// Calculate event date
			$event_date = $this->calculate_event_date( $event, $current_year );

			// If event already passed this year, check next year
			if ( $event_date < $now ) {
				$event_date = $this->calculate_event_date( $event, $current_year + 1 );
			}

			// Calculate time difference between weekend and major event
			$time_diff = abs( $weekend_sale_date - $event_date );

			// If major event is within 2 weeks, remove weekend_sale
			if ( $time_diff <= $two_weeks_seconds ) {
				unset( $qualifying_events[ $weekend_sale_index ] );
				// Re-index array to maintain sequential keys
				return array_values( $qualifying_events );
			}
		}

		// No major events nearby - weekend sale is safe to show
		return $qualifying_events;
	}

	/**
	 * Render the dashboard view.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array    $data    Dashboard data.
	 * @return   void
	 */
	private function render_view( $data ): void {
		// Extract data for view
		$metrics = $data['metrics'];
		$campaign_stats = $data['campaign_stats'];
		$top_campaigns = $data['top_campaigns'];
		$recent_activity = $data['recent_activity'];
		$campaign_health = $data['campaign_health'];
		$campaign_suggestions = $data['campaign_suggestions'];
		$is_premium = $data['is_premium'];
		$campaign_limit = $data['campaign_limit'];

		// Pass feature gate and upgrade prompt manager to view
		$feature_gate = $this->feature_gate;
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
