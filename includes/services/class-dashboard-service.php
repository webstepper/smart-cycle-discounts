<?php
/**
 * Dashboard Service Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/services/class-dashboard-service.php
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
 * Dashboard Service Class
 *
 * Orchestrator for dashboard data operations. Delegates to specialized services:
 * - Campaign Suggestions Service: Event-based suggestions with timing windows
 * - Campaign Display Service: Campaign preparation for display
 * - Campaign Planner Service: Weekly planner with dynamic major event integration
 * - Campaign Health Service: Health monitoring and analysis
 *
 * Responsibilities:
 * - Dashboard data assembly and orchestration
 * - Campaign statistics and metrics
 * - Caching layer (5-minute TTL with automatic invalidation)
 * - Cache invalidation hooks
 *
 * Used by both Page Controller and AJAX Handler.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/services
 * @author     Webstepper <contact@webstepper.io>
 */
class WSSCD_Dashboard_Service {

	/**
	 * Cache group name for dashboard transients.
	 *
	 * @since    1.0.0
	 */
	const CACHE_GROUP = 'wsscd_dashboard';

	/**
	 * Cache TTL (Time To Live) in seconds.
	 * 5 minutes = 300 seconds.
	 *
	 * @since    1.0.0
	 */
	const CACHE_TTL = 300;


	/**
	 * Cache manager instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Cache_Manager    $cache    Cache manager.
	 */
	private WSSCD_Cache_Manager $cache;


	/**
	 * Analytics dashboard instance (Pro-only, may be null in free version).
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Analytics_Dashboard|null    $analytics_dashboard    Analytics dashboard.
	 */
	private ?WSSCD_Analytics_Dashboard $analytics_dashboard;

	/**
	 * Campaign repository instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Campaign_Repository    $campaign_repository    Campaign repository.
	 */
	private WSSCD_Campaign_Repository $campaign_repository;

	/**
	 * Campaign health service instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Campaign_Health_Service    $health_service    Campaign health service.
	 */
	private WSSCD_Campaign_Health_Service $health_service;

	/**
	 * Feature gate instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Feature_Gate    $feature_gate    Feature gate.
	 */
	private WSSCD_Feature_Gate $feature_gate;


	/**
	 * Logger instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Logger    $logger    Logger instance.
	 */
	private WSSCD_Logger $logger;

	/**
	 * Campaign suggestions service instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Campaign_Suggestions_Service    $suggestions_service    Campaign suggestions service.
	 */
	private WSSCD_Campaign_Suggestions_Service $suggestions_service;

	/**
	 * Campaign display service instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Campaign_Display_Service    $display_service    Campaign display service.
	 */
	private WSSCD_Campaign_Display_Service $display_service;

	/**
	 * Campaign planner service instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Campaign_Planner_Service    $planner_service    Campaign planner service.
	 */
	private WSSCD_Campaign_Planner_Service $planner_service;

	/**
	 * Insights builder instance (lazy-loaded).
	 *
	 * @since    1.3.0
	 * @access   private
	 * @var      WSSCD_Insights_Builder|null    $insights_builder    Insights builder.
	 */
	private ?WSSCD_Insights_Builder $insights_builder = null;

	/**
	 * Initialize the dashboard service.
	 *
	 * @since    1.0.0
	 * @param    WSSCD_Cache_Manager                $cache                  Cache manager.
	 * @param    WSSCD_Analytics_Dashboard|null     $analytics_dashboard    Analytics dashboard (Pro-only, null in free).
	 * @param    WSSCD_Campaign_Repository          $campaign_repository    Campaign repository.
	 * @param    WSSCD_Campaign_Health_Service      $health_service         Campaign health service.
	 * @param    WSSCD_Feature_Gate                 $feature_gate           Feature gate.
	 * @param    WSSCD_Logger                       $logger                 Logger instance.
	 * @param    WSSCD_Campaign_Suggestions_Service $suggestions_service    Campaign suggestions service.
	 * @param    WSSCD_Campaign_Display_Service     $display_service        Campaign display service.
	 * @param    WSSCD_Campaign_Planner_Service     $planner_service        Campaign planner service.
	 */
	public function __construct(
		WSSCD_Cache_Manager $cache,
		?WSSCD_Analytics_Dashboard $analytics_dashboard,
		WSSCD_Campaign_Repository $campaign_repository,
		WSSCD_Campaign_Health_Service $health_service,
		WSSCD_Feature_Gate $feature_gate,
		WSSCD_Logger $logger,
		WSSCD_Campaign_Suggestions_Service $suggestions_service,
		WSSCD_Campaign_Display_Service $display_service,
		WSSCD_Campaign_Planner_Service $planner_service
	) {
		$this->cache = $cache;
		$this->analytics_dashboard = $analytics_dashboard;
		$this->campaign_repository = $campaign_repository;
		$this->health_service      = $health_service;
		$this->feature_gate        = $feature_gate;
		$this->logger              = $logger;
		$this->suggestions_service = $suggestions_service;
		$this->display_service     = $display_service;
		$this->planner_service     = $planner_service;

		$this->register_cache_hooks();
		$this->maybe_run_cache_migration();
	}

	/**
	 * Get the insights builder instance (lazy-loaded).
	 *
	 * @since    1.3.0
	 * @return   WSSCD_Insights_Builder    Insights builder instance.
	 */
	private function get_insights_builder(): WSSCD_Insights_Builder {
		if ( null === $this->insights_builder ) {
			require_once WSSCD_INCLUDES_DIR . 'core/campaigns/interface-campaign-data.php';
			require_once WSSCD_INCLUDES_DIR . 'core/campaigns/class-major-event-data.php';
			require_once WSSCD_INCLUDES_DIR . 'core/campaigns/class-weekly-campaign-data.php';
			require_once WSSCD_INCLUDES_DIR . 'core/campaigns/class-campaign-data-factory.php';
			require_once WSSCD_INCLUDES_DIR . 'core/campaigns/class-campaign-suggestions-registry.php';
			require_once WSSCD_INCLUDES_DIR . 'core/campaigns/class-weekly-campaign-definitions.php';
			require_once WSSCD_INCLUDES_DIR . 'services/class-insights-builder.php';

			$this->insights_builder = new WSSCD_Insights_Builder();
		}

		return $this->insights_builder;
	}

	/**
	 * Run one-time cache migration to clear stale dashboard caches.
	 *
	 * This ensures old cache formats are cleared when the cache key structure changes.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function maybe_run_cache_migration(): void {
		$migration_version = 3; // Increment this to force cache clear.
		$current_version   = get_option( 'wsscd_dashboard_cache_migration', 0 );

		if ( (int) $current_version < $migration_version ) {
			$this->invalidate_all_caches();
			update_option( 'wsscd_dashboard_cache_migration', $migration_version, false );
			$this->logger->info( 'Dashboard cache migration completed', array( 'version' => $migration_version ) );
		}
	}

	/**
	 * Get complete dashboard data with caching.
	 *
	 * Single method used by both Page Controller and AJAX Handler.
	 * Implements 5-minute cache with automatic invalidation.
	 *
	 * @since    1.0.0
	 * @param    array $options         Dashboard options {
	 *     @type string $date_range            Date range ('7days', '30days'). Default '30days'.
	 *     @type bool   $include_suggestions   Include campaign suggestions. Default true.
	 *     @type bool   $include_health        Include campaign health. Default true.
	 *     @type bool   $include_activity      Include recent activity. Default true.
	 * }
	 * @param    bool  $force_refresh   Force cache refresh. Default false.
	 * @return   array                     Dashboard data.
	 */
	public function get_dashboard_data( array $options = array(), bool $force_refresh = false ): array {
		$defaults = array(
			'date_range'          => '30days',
			'include_suggestions' => true,
			'include_health'      => true,
			'include_activity'    => true,
		);

		$options = array_merge( $defaults, $options );

		// Generate cache key based on user and options
		$cache_key = $this->get_cache_key( $options );

		// Try cache first (unless force refresh)
		if ( ! $force_refresh ) {
			$cached = $this->get_from_cache( $cache_key );
			if ( null !== $cached ) {
				$this->logger->debug(
					'Dashboard data served from cache',
					array(
						'cache_key' => $cache_key,
					)
				);
				return $cached;
			}
		}

		$this->logger->debug(
			'Dashboard cache miss, calculating fresh data',
			array(
				'cache_key'     => $cache_key,
				'force_refresh' => $force_refresh,
			)
		);

		$data = $this->calculate_dashboard_data( $options );

		$this->store_in_cache( $cache_key, $data );

		return $data;
	}

	/**
	 * Calculate dashboard data (expensive operation).
	 *
	 * This runs on cache miss. All expensive queries and calculations happen here.
	 *
	 * @since    1.0.0
	 * @param    array $options    Dashboard options (already merged with defaults).
	 * @return   array                Dashboard data.
	 */
	private function calculate_dashboard_data( array $options ): array {
		// Pro-only: Get analytics metrics (returns empty array in free version)
		$metrics = null !== $this->analytics_dashboard
			? $this->analytics_dashboard->get_dashboard_metrics( $options['date_range'], true )
			: $this->get_empty_metrics();

		$campaign_stats = $this->get_campaign_stats();

		$top_campaigns = $this->get_top_campaigns( 3, $options['date_range'] );

		$recent_activity = $options['include_activity'] ? $this->get_recent_activity( 5 ) : array();

		$campaign_health = $options['include_health'] ? $this->get_campaign_health() : $this->get_empty_health_structure();

		$all_campaigns = $this->get_recent_campaigns( 5 );

		$planner_data = $this->get_weekly_planner_campaigns();

		return array(
			'metrics'         => $metrics,
			'campaign_stats'  => $campaign_stats,
			'top_campaigns'   => $top_campaigns,
			'recent_activity' => $recent_activity,
			'campaign_health' => $campaign_health,
			'all_campaigns'   => $all_campaigns,
			'planner_data'    => $planner_data,
			'is_premium'      => $this->feature_gate->is_premium(),
		);
	}

	/**
	 * Get campaign status breakdown.
	 *
	 * @since    1.0.0
	 * @return   array    Campaign status counts.
	 */
	private function get_campaign_stats(): array {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wsscd_campaigns';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching , PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Dashboard stats query; results are cached via WSSCD_Cache_Manager at higher level.
		$stats = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT status, COUNT(*) as count
				FROM %i
				WHERE deleted_at IS NULL
				GROUP BY status",
				$table_name
			),
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
	 * @param    int    $limit         Number of campaigns to retrieve.
	 * @param    string $date_range    Date range.
	 * @return   array                    Top campaigns.
	 */
	private function get_top_campaigns( int $limit, string $date_range ): array {
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

		try {
			// Pro-only: Get batch campaign metrics (empty in free version)
			$metrics = null !== $this->analytics_dashboard
				? $this->analytics_dashboard->get_batch_campaign_metrics( $campaign_ids, $date_range )
				: array();
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
	 * @param    int $limit    Number of events to retrieve.
	 * @return   array            Recent activity events.
	 */
	private function get_recent_activity( int $limit ): array {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wsscd_activity_log';

		$check_table_sql = $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls, PluginCheck.Security.DirectDB.UnescapedDBParameter -- SHOW TABLES has no WP abstraction; query prepared above.
		$table_exists = $wpdb->get_var( $check_table_sql ) === $table_name;

		if ( ! $table_exists ) {
			return array();
		}

		// Dashboard activity query. Table name constructed with $wpdb->prefix. Query IS prepared.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Table name from $wpdb->prefix is safe.
		$events_sql = $wpdb->prepare(
			"SELECT * FROM {$table_name}
			ORDER BY created_at DESC
			LIMIT %d",
			$limit
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query prepared above; table name from $wpdb->prefix.
		$events = $wpdb->get_results( $events_sql, ARRAY_A );

		return $events ? $events : array();
	}

	/**
	 * Get campaign health checks.
	 *
	 * Uses unified Campaign Health Service for consistent health analysis.
	 *
	 * @since    1.0.0
	 * @return   array    Campaign health data.
	 */
	private function get_campaign_health(): array {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wsscd_campaigns';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls, PluginCheck.Security.DirectDB.UnescapedDBParameter
		// Dashboard health query. Table name constructed with $wpdb->prefix. Query IS prepared.
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
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls, PluginCheck.Security.DirectDB.UnescapedDBParameter

		if ( empty( $campaigns ) ) {
			return $this->get_empty_health_structure();
		}

		// Count campaigns by status for quick stats breakdown.
		$status_counts = array(
			'active'    => 0,
			'scheduled' => 0,
			'paused'    => 0,
		);
		foreach ( $campaigns as $campaign ) {
			$status = $campaign['status'] ?? '';
			if ( isset( $status_counts[ $status ] ) ) {
				++$status_counts[ $status ];
			}
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
				'total_analyzed'   => $aggregate_health['total_campaigns_analyzed'],
				'issues_count'     => count( $aggregate_health['critical_issues'] ),
				'warnings_count'   => count( $aggregate_health['warnings'] ),
				'active_count'     => $status_counts['active'],
				'scheduled_count'  => $status_counts['scheduled'],
				'paused_count'     => $status_counts['paused'],
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

		// Calculate quick stats
		$health['quick_stats']['issues_count']   = count( $health['issues'] );
		$health['quick_stats']['warnings_count'] = count( $health['warnings'] );

		if ( in_array( $health['status'], array( 'excellent', 'good' ), true ) && empty( $health['warnings'] ) && ! empty( $campaigns ) ) {
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
	 * @return   array    Empty health data structure.
	 */
	private function get_empty_health_structure(): array {
		return array(
			'status'           => 'excellent',
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
				'total_analyzed'  => 0,
				'issues_count'    => 0,
				'warnings_count'  => 0,
				'active_count'    => 0,
				'scheduled_count' => 0,
				'paused_count'    => 0,
			),
		);
	}

	/**
	 * Get empty metrics structure for free version.
	 *
	 * Returns the expected metrics structure with zeroed values when
	 * analytics dashboard is not available (Pro-only feature).
	 *
	 * @since    1.0.0
	 * @return   array    Empty metrics data structure.
	 */
	private function get_empty_metrics(): array {
		return array(
			'revenue'          => 0,
			'orders'           => 0,
			'conversions'      => 0,
			'impressions'      => 0,
			'average_discount' => 0,
			'conversion_rate'  => 0,
			'revenue_trend'    => array(),
			'top_products'     => array(),
		);
	}

	/**
	 * Map service status to dashboard status.
	 *
	 * @since    1.0.0
	 * @param    string $service_status    Status from health service.
	 * @return   string                       Dashboard status.
	 */
	private function map_status_to_dashboard( string $service_status ): string {
		// Pass through the 5 statuses directly for consistency with other features
		// (Campaign List, Overview Panel, Wizard Review all use these same 5 statuses)
		$valid_statuses = array( 'excellent', 'good', 'fair', 'poor', 'critical' );

		if ( in_array( $service_status, $valid_statuses, true ) ) {
			return $service_status;
		}

		// Default to 'fair' for unknown statuses (middle ground)
		return 'fair';
	}

	/**
	 * Map category data to dashboard category format.
	 *
	 * @since    1.0.0
	 * @param    array $category_data    Category data from service.
	 * @return   array                      Dashboard category format.
	 */
	private function map_category_status( array $category_data ): array {
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
	 * Get recent campaigns with display data.
	 *
	 * Delegates to Campaign Display Service.
	 *
	 * @since    1.0.0
	 * @param    int $limit    Number of campaigns to retrieve.
	 * @return   array            Recent campaigns prepared for display.
	 */
	public function get_recent_campaigns( int $limit = 5 ): array {
		return $this->display_service->get_recent_campaigns( $limit );
	}

	/**
	 * Generate cache key for dashboard data.
	 *
	 * @since    1.0.0
	 * @param    array $options    Dashboard options.
	 * @return   string               Cache key.
	 */
	private function get_cache_key( array $options ): string {
		$user_id      = get_current_user_id();
		$options_hash = md5( wp_json_encode( $options ) );

		return sprintf( 'dashboard_%d_%s', $user_id, $options_hash );
	}

	/**
	 * Get data from cache.
	 *
	 * @since    1.0.0
	 * @param    string $key    Cache key.
	 * @return   mixed             Cached data or false if not found.
	 */
	private function get_from_cache( string $key ) {
		return $this->cache->get( self::CACHE_GROUP . '_' . $key );
	}

	/**
	 * Store data in cache.
	 *
	 * @since    1.0.0
	 * @param    string $key     Cache key.
	 * @param    array  $data    Data to cache.
	 * @return   void
	 */
	private function store_in_cache( string $key, array $data ): void {
		// Store dashboard data in transient cache.
		$this->cache->set( self::CACHE_GROUP . '_' . $key, $data, self::CACHE_TTL );
	}

	/**
	 * Invalidate dashboard cache for specific user.
	 *
	 * Called when campaign data changes that affects this user's dashboard.
	 *
	 * @since    1.0.0
	 * @param    int|null $user_id    User ID. Null for current user.
	 * @return   void
	 */
	public function invalidate_cache( ?int $user_id = null ): void {
		if ( null === $user_id ) {
			$user_id = get_current_user_id();
		}

		// For now, invalidate all dashboard caches.
		// Per-user invalidation would require tracking individual cache keys.
		$this->cache->delete_group( self::CACHE_GROUP );

		$this->logger->debug(
			'Invalidated dashboard cache for user',
			array(
				'user_id' => $user_id,
			)
		);
	}

	/**
	 * Invalidate all dashboard caches (all users).
	 *
	 * Called when plugin settings change that affect all dashboards.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function invalidate_all_caches(): void {
		global $wpdb;

		// Use Cache Manager's delete_group() for proper key handling.
		// This accounts for the cache prefix and version automatically.
		$this->cache->delete_group( self::CACHE_GROUP );

		// Also clear any legacy transients that might be using old key formats.
		// This ensures a clean slate when cache format changes.
		$pattern = $wpdb->esc_like( 'wsscd_dashboard' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching , PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Bulk legacy transient cleanup; no WP abstraction for pattern-based delete.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options}
				WHERE option_name LIKE %s
				OR option_name LIKE %s",
				'%' . $pattern . '%',
				'%scd%dashboard%'
			)
		);

		$this->logger->info( 'Invalidated all dashboard caches via cache manager' );
	}

	/**
	 * Register cache invalidation hooks.
	 *
	 * Automatically invalidates cache when campaigns or settings change.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function register_cache_hooks(): void {
		// Invalidate when campaigns are created/updated/deleted
		add_action( 'wsscd_campaign_created', array( $this, 'on_campaign_changed' ), 10, 1 );
		add_action( 'wsscd_campaign_updated', array( $this, 'on_campaign_changed' ), 10, 1 );
		add_action( 'wsscd_campaign_deleted', array( $this, 'on_campaign_changed' ), 10, 1 );
		add_action( 'wsscd_campaign_status_changed', array( $this, 'on_campaign_changed' ), 10, 1 );

		// Invalidate for wizard-created/updated campaigns (passes campaign ID, not object)
		add_action( 'wsscd_campaign_created_from_wizard', array( $this, 'on_campaign_changed_by_id' ), 10, 1 );
		add_action( 'wsscd_campaign_updated_from_wizard', array( $this, 'on_campaign_changed_by_id' ), 10, 1 );
		add_action( 'wsscd_campaign_created_from_data', array( $this, 'on_campaign_changed_by_id' ), 10, 1 );

		// Invalidate when settings change
		add_action( 'wsscd_settings_updated', array( $this, 'invalidate_all_caches' ) );

		// Invalidate when license status changes
		add_action( 'wsscd_license_activated', array( $this, 'invalidate_all_caches' ) );
		add_action( 'wsscd_license_deactivated', array( $this, 'invalidate_all_caches' ) );
	}

	/**
	 * Handle campaign change event.
	 *
	 * Invalidates cache for campaign owner and all admins.
	 *
	 * @since    1.0.0
	 * @param    WSSCD_Campaign|int $campaign    Campaign object or ID.
	 * @return   void
	 */
	public function on_campaign_changed( $campaign ): void {
		if ( is_object( $campaign ) && method_exists( $campaign, 'get_created_by' ) ) {
			$owner_id = $campaign->get_created_by();
			if ( $owner_id ) {
				$this->invalidate_cache( $owner_id );
			}
		}

		// Invalidate for all admins (they can see all campaigns)
		$admins = get_users(
			array(
				'role'   => 'administrator',
				'fields' => 'ID',
			)
		);

		foreach ( $admins as $admin_id ) {
			$this->invalidate_cache( $admin_id );
		}

		$this->logger->debug(
			'Invalidated dashboard cache for campaign change',
			array(
				'campaign_id'     => is_object( $campaign ) && method_exists( $campaign, 'get_id' ) ? $campaign->get_id() : $campaign,
				'admins_affected' => count( $admins ),
			)
		);
	}

	/**
	 * Handle campaign change event (when passed campaign ID).
	 *
	 * Used by wizard hooks that pass campaign ID instead of object.
	 *
	 * @since    1.0.0
	 * @param    int $campaign_id    Campaign ID.
	 * @return   void
	 */
	public function on_campaign_changed_by_id( int $campaign_id ): void {
		// Invalidate for all admins (they can see all campaigns)
		$admins = get_users(
			array(
				'role'   => 'administrator',
				'fields' => 'ID',
			)
		);

		foreach ( $admins as $admin_id ) {
			$this->invalidate_cache( $admin_id );
		}

		$this->logger->debug(
			'Invalidated dashboard cache for campaign change (by ID)',
			array(
				'campaign_id'     => $campaign_id,
				'admins_affected' => count( $admins ),
			)
		);
	}

	/**
	 * Get campaign suggestions based on upcoming seasonal events.
	 *
	 * Delegates to Campaign Suggestions Service.
	 *
	 * @since    1.0.0
	 * @return   array    Array of campaign suggestions.
	 */
	public function get_campaign_suggestions(): array {
		return $this->suggestions_service->get_suggestions();
	}

	/**
	 * Get weekly planner campaigns with dynamic selection.
	 *
	 * Delegates to Campaign Planner Service.
	 * Intelligently mixes major events and weekly campaigns based on priority.
	 * Each position (past/active/future) shows the most relevant campaign.
	 *
	 * @since  1.0.0
	 * @return array Planner data with 3 selected campaigns.
	 */
	public function get_weekly_planner_campaigns(): array {
		return $this->planner_service->get_weekly_planner_campaigns();
	}

	/**
	 * Get single event definition by ID.
	 *
	 * Delegates to Campaign Suggestions Service.
	 *
	 * @since    1.0.0
	 * @param    string $event_id    Event ID (e.g., 'valentines', 'black_friday').
	 * @return   array|null              Event definition or null if not found.
	 */
	public function get_event_by_id( string $event_id ): ?array {
		return $this->suggestions_service->get_event_by_id( $event_id );
	}

	/**
	 * Get unified insights for a planner campaign.
	 *
	 * Returns structured insights data for displaying in planner insights panel.
	 * Creates comprehensive 3-column structure (Opportunity/Strategy/Timeline) with rich data for BOTH
	 * major events (from Campaign Suggestions Registry) AND weekly campaigns
	 * (from Weekly Campaign Definitions).
	 *
	 * @since  1.0.0
	 * @param  string $campaign_id     Campaign ID.
	 * @param  string $position        Timeline position (past/active/future) - where campaign is displayed.
	 * @param  bool   $is_major_event  Whether this is a major event.
	 * @param  string $state           Campaign state (past/active/future) - actual campaign timing.
	 * @return array                   Insights data structure with 'tabs' key (3 columns).
	 */
	public function get_unified_insights( string $campaign_id, string $position, bool $is_major_event, string $state = '' ): array {
		// Default state to position if not provided (backwards compatibility).
		if ( empty( $state ) ) {
			$state = $position;
		}

		// Use unified factory and builder (Phase 1 refactoring).
		$builder  = $this->get_insights_builder();
		$campaign = WSSCD_Campaign_Data_Factory::create( $campaign_id, $is_major_event );

		if ( $campaign ) {
			return $builder->build( $campaign, $position, $state );
		}

		// Fallback to generic insights if campaign not found.
		return $builder->build_fallback( $position );
	}
}
