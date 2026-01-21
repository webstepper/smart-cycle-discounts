<?php
/**
 * Dashboard Service Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/services/class-dashboard-service.php
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
	 * Run one-time cache migration to clear stale dashboard caches.
	 *
	 * This ensures old cache formats are cleared when the cache key structure changes.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function maybe_run_cache_migration(): void {
		$migration_version = 2; // Increment this to force cache clear.
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
				'total_analyzed' => 0,
				'issues_count'   => 0,
				'warnings_count' => 0,
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

		// If major event, get rich event data from Registry.
		if ( $is_major_event ) {
			$event = WSSCD_Campaign_Suggestions_Registry::get_event_by_id( $campaign_id );

			if ( $event ) {
				return $this->build_event_insights( $event, $position, $state );
			}
		}

		// For weekly campaigns, get rich data from Weekly Definitions.
		require_once WSSCD_INCLUDES_DIR . 'core/campaigns/class-weekly-campaign-definitions.php';
		$weekly = WSSCD_Weekly_Campaign_Definitions::get_by_id( $campaign_id );

		if ( $weekly ) {
			return $this->build_weekly_event_insights( $weekly, $position, $state );
		}

		// Fallback to basic structure if no data found.
		return $this->build_weekly_insights( $campaign_id, $position );
	}

	/**
	 * Build comprehensive insights for major events with 3-column layout.
	 *
	 * @since  1.0.0
	 * @param  array  $event     Event definition from Campaign Suggestions Registry.
	 * @param  string $position  Timeline position (past/active/future).
	 * @param  string $state     Campaign state (past/active/future).
	 * @return array             Insights data structure.
	 */
	private function build_event_insights( array $event, string $position, string $state ): array {
		$tabs = array(
			$this->build_opportunity_column( $event, $position ),
			$this->build_strategy_column( $event, $position, $state, true ),
			$this->build_timeline_column( $event, $position ),
		);

		// Build title with emoji icon.
		$emoji = isset( $event['icon'] ) ? $event['icon'] . ' ' : '';

		return array(
			'title' => $emoji . $event['name'],
			'icon'  => 'calendar-alt',
			'tabs'  => $tabs,
		);
	}

	/**
	 * Build comprehensive insights for weekly campaigns with 3-column layout.
	 *
	 * Uses rich data from Weekly Campaign Definitions to build the same comprehensive
	 * column structure as major events.
	 *
	 * @since  1.0.0
	 * @param  array  $weekly    Weekly campaign definition from Weekly Campaign Definitions.
	 * @param  string $position  Timeline position (past/active/future).
	 * @param  string $state     Campaign state (past/active/future).
	 * @return array             Insights data structure.
	 */
	private function build_weekly_event_insights( array $weekly, string $position, string $state ): array {
		$tabs = array(
			$this->build_weekly_opportunity_column( $weekly, $position ),
			$this->build_weekly_strategy_column( $weekly, $position, $state ),
			$this->build_weekly_timeline_column( $weekly, $position ),
		);

		// Build title with emoji icon.
		$emoji = isset( $weekly['icon'] ) ? $weekly['icon'] . ' ' : '';

		return array(
			'title' => $emoji . $weekly['name'],
			'icon'  => 'calendar-alt',
			'tabs'  => $tabs,
		);
	}

	/**
	 * Build "Opportunity" column - Market Intelligence.
	 * Returns 3 randomly selected insights from available pool.
	 *
	 * @since  1.0.0
	 * @param  array  $event  Event definition.
	 * @param  string $position  Timeline position.
	 * @return array          Column data structure.
	 */
	private function build_opportunity_column( array $event, string $position ): array {
		$content_pool = array();

		$content_pool[] = array(
			'type'   => 'info',
			'icon'   => 'info',
			'text'   => $event['description'],
			'weight' => 2,
		);

		if ( ! empty( $event['statistics'] ) ) {
			foreach ( $event['statistics'] as $label => $value ) {
				$formatted_label = ucwords( str_replace( '_', ' ', $label ) );
				$content_pool[]  = array(
					'type'   => 'info',
					'icon'   => $this->get_stat_icon( $label ),
					'text'   => $formatted_label . ': ' . $value,
					'weight' => 1,
				);
			}
		}

		// Randomly select 3 items from pool with weighted selection.
		$selected_content = $this->weighted_random_select( $content_pool, 3 );

		return array(
			'id'      => 'opportunity',
			'label'   => __( 'Opportunity', 'smart-cycle-discounts' ),
			'icon'    => 'trending-up',
			'content' => $selected_content,
		);
	}

	/**
	 * Build "Strategy" column - Execution Guide.
	 * Returns 3 randomly selected strategy insights + CTA button.
	 *
	 * @since  1.0.0
	 * @param  array  $event          Event definition.
	 * @param  string $position       Timeline position.
	 * @param  string $state          Campaign state.
	 * @param  bool   $is_major_event Whether this is a major event.
	 * @return array                  Column data structure.
	 */
	private function build_strategy_column( array $event, string $position, string $state, bool $is_major_event ): array {
		$content_pool = array();

		if ( ! empty( $event['suggested_discount'] ) ) {
			$discount       = $event['suggested_discount'];
			$content_pool[] = array(
				'type'   => 'info',
				'icon'   => 'percent',
				'text'   => sprintf(
					/* translators: %d: optimal discount percentage */
					__( 'Optimal Discount: %d%%', 'smart-cycle-discounts' ),
					$discount['optimal']
				),
				'weight' => 3,
			);
			$content_pool[] = array(
				'type'   => 'info',
				'icon'   => 'chart-bar',
				'text'   => sprintf(
					/* translators: %1$d: minimum discount, %2$d: maximum discount */
					__( 'Range: %1$d%% - %2$d%% based on industry performance', 'smart-cycle-discounts' ),
					$discount['min'],
					$discount['max']
				),
				'weight' => 2,
			);
		}

		if ( ! empty( $event['recommendations'] ) ) {
			foreach ( $event['recommendations'] as $recommendation ) {
				$content_pool[] = array(
					'type'   => 'info',
					'icon'   => 'check-circle',
					'text'   => $recommendation,
					'weight' => 2,
				);
			}
		}

		if ( ! empty( $event['tips'] ) ) {
			foreach ( $event['tips'] as $tip ) {
				$content_pool[] = array(
					'type'   => 'info',
					'icon'   => 'lightbulb',
					'text'   => $tip,
					'weight' => 1,
				);
			}
		}

		// Randomly select 3 items from pool with weighted selection.
		$selected_content = $this->weighted_random_select( $content_pool, 3 );

		// Add CTA button with position and state-aware URL and text.
		$cta = $this->build_position_aware_cta( $event['id'], $event['name'], $position, $state, $is_major_event );
		if ( $cta ) {
			$selected_content[] = $cta;
		}

		return array(
			'id'      => 'strategy',
			'label'   => __( 'Strategy', 'smart-cycle-discounts' ),
			'icon'    => 'target',
			'content' => $selected_content,
		);
	}

	/**
	 * Build "Timeline" column - Launch Timeline.
	 * Returns 3 randomly selected timing insights from available pool.
	 *
	 * @since  1.0.0
	 * @param  array  $event  Event definition.
	 * @param  string $position  Timeline position.
	 * @return array          Column data structure.
	 */
	private function build_timeline_column( array $event, string $position ): array {
		$content_pool = array();

		if ( ! empty( $event['event_date'] ) ) {
			$event_date     = wp_date( 'F j, Y', $event['event_date'] );
			$content_pool[] = array(
				'type'   => 'info',
				'icon'   => 'calendar',
				'text'   => sprintf(
					/* translators: %s: event date */
					__( 'Event Date: %s', 'smart-cycle-discounts' ),
					$event_date
				),
				'weight' => 3,
			);
		}

		if ( ! empty( $event['calculated_start_date'] ) ) {
			$start_date     = wp_date( 'F j, Y', $event['calculated_start_date'] );
			$content_pool[] = array(
				'type'   => 'info',
				'icon'   => 'play-circle',
				'text'   => sprintf(
					/* translators: %s: start date */
					__( 'Campaign Starts: %s', 'smart-cycle-discounts' ),
					$start_date
				),
				'weight' => 2,
			);
		}

		if ( ! empty( $event['calculated_end_date'] ) ) {
			$end_date       = wp_date( 'F j, Y', $event['calculated_end_date'] );
			$content_pool[] = array(
				'type'   => 'info',
				'icon'   => 'stop-circle',
				'text'   => sprintf(
					/* translators: %s: end date */
					__( 'Campaign Ends: %s', 'smart-cycle-discounts' ),
					$end_date
				),
				'weight' => 2,
			);
		}

		if ( ! empty( $event['duration_days'] ) ) {
			$content_pool[] = array(
				'type'   => 'info',
				'icon'   => 'clock',
				'text'   => sprintf(
					/* translators: %d: number of days */
					__( 'Duration: %d days', 'smart-cycle-discounts' ),
					$event['duration_days']
				),
				'weight' => 1,
			);
		}

		if ( ! empty( $event['lead_time'] ) ) {
			$lead_time = $event['lead_time'];

			if ( ! empty( $lead_time['marketing'] ) ) {
				$content_pool[] = array(
					'type'   => 'info',
					'icon'   => 'bullhorn',
					'text'   => sprintf(
						/* translators: %d: number of weeks */
						__( 'Start marketing %d weeks before', 'smart-cycle-discounts' ),
						$lead_time['marketing']
					),
					'weight' => 1,
				);
			}

			if ( ! empty( $lead_time['inventory'] ) ) {
				$content_pool[] = array(
					'type'   => 'info',
					'icon'   => 'box',
					'text'   => sprintf(
						/* translators: %d: number of weeks */
						__( 'Order inventory %d weeks ahead', 'smart-cycle-discounts' ),
						$lead_time['inventory']
					),
					'weight' => 1,
				);
			}
		}

		if ( ! empty( $event['best_practices'] ) ) {
			foreach ( $event['best_practices'] as $practice ) {
				$content_pool[] = array(
					'type'   => 'info',
					'icon'   => 'star',
					'text'   => $practice,
					'weight' => 1,
				);
			}
		}

		// Randomly select 3 items from pool with weighted selection.
		$selected_content = $this->weighted_random_select( $content_pool, 3 );

		return array(
			'id'      => 'timeline',
			'label'   => __( 'Timeline', 'smart-cycle-discounts' ),
			'icon'    => 'calendar',
			'content' => $selected_content,
		);
	}

	/**
	 * Build "Opportunity" column for weekly campaigns.
	 * Returns 3 randomly selected insights from available pool.
	 *
	 * @since  1.0.0
	 * @param  array  $weekly  Weekly campaign definition.
	 * @param  string $position   Timeline position.
	 * @return array           Column data structure.
	 */
	private function build_weekly_opportunity_column( array $weekly, string $position ): array {
		$content_pool = array();

		$content_pool[] = array(
			'type'   => 'info',
			'icon'   => 'calendar-alt',
			'text'   => $weekly['description'],
			'weight' => 2,
		);

		if ( ! empty( $weekly['psychology'] ) ) {
			$content_pool[] = array(
				'type'   => 'info',
				'icon'   => 'admin-users',
				'text'   => __( 'Psychology: ', 'smart-cycle-discounts' ) . $weekly['psychology'],
				'weight' => 2,
			);
		}

		if ( ! empty( $weekly['statistics'] ) ) {
			foreach ( $weekly['statistics'] as $label => $value ) {
				$formatted_label = ucwords( str_replace( '_', ' ', $label ) );
				$content_pool[]  = array(
					'type'   => 'info',
					'icon'   => $this->get_stat_icon( $label ),
					'text'   => $formatted_label . ': ' . $value,
					'weight' => 1,
				);
			}
		}

		// Add "best for" items.
		if ( ! empty( $weekly['best_for'] ) ) {
			foreach ( $weekly['best_for'] as $item ) {
				$content_pool[] = array(
					'type'   => 'info',
					'icon'   => 'yes',
					'text'   => $item,
					'weight' => 1,
				);
			}
		}

		// Randomly select 3 items from pool with weighted selection.
		$selected_content = $this->weighted_random_select( $content_pool, 3 );

		return array(
			'id'      => 'opportunity',
			'label'   => __( 'Opportunity', 'smart-cycle-discounts' ),
			'icon'    => 'lightbulb',
			'content' => $selected_content,
		);
	}

	/**
	 * Build "Strategy" column for weekly campaigns.
	 * Returns 3 randomly selected strategy insights + CTA button.
	 *
	 * @since  1.0.0
	 * @param  array  $weekly    Weekly campaign definition.
	 * @param  string $position  Timeline position.
	 * @param  string $state     Campaign state.
	 * @return array             Column data structure.
	 */
	private function build_weekly_strategy_column( array $weekly, string $position, string $state ): array {
		$content_pool = array();

		if ( ! empty( $weekly['suggested_discount'] ) ) {
			$discount       = $weekly['suggested_discount'];
			$content_pool[] = array(
				'type'   => 'info',
				'icon'   => 'tag',
				'text'   => sprintf(
					/* translators: %d: optimal discount percentage */
					__( 'Optimal Discount: %d%%', 'smart-cycle-discounts' ),
					$discount['optimal']
				),
				'weight' => 3,
			);
			$content_pool[] = array(
				'type'   => 'info',
				'icon'   => 'chart-line',
				'text'   => sprintf(
					/* translators: %1$d: minimum discount, %2$d: maximum discount */
					__( 'Range: %1$d%% - %2$d%% based on weekly performance', 'smart-cycle-discounts' ),
					$discount['min'],
					$discount['max']
				),
				'weight' => 2,
			);
		}

		if ( ! empty( $weekly['recommendations'] ) ) {
			foreach ( $weekly['recommendations'] as $recommendation ) {
				$content_pool[] = array(
					'type'   => 'info',
					'icon'   => 'yes-alt',
					'text'   => $recommendation,
					'weight' => 2,
				);
			}
		}

		// Randomly select 3 items from pool with weighted selection.
		$selected_content = $this->weighted_random_select( $content_pool, 3 );

		// Add CTA button with position and state-aware URL and text.
		$cta = $this->build_position_aware_cta( $weekly['id'], $weekly['name'], $position, $state, false );
		if ( $cta ) {
			$selected_content[] = $cta;
		}

		return array(
			'id'      => 'strategy',
			'label'   => __( 'Strategy', 'smart-cycle-discounts' ),
			'icon'    => 'admin-tools',
			'content' => $selected_content,
		);
	}

	/**
	 * Build "Timeline" column for weekly campaigns.
	 * Uses weighted random selection to display 3 items from content pool.
	 *
	 * @since  1.0.0
	 * @param  array  $weekly  Weekly campaign definition.
	 * @param  string $position   Timeline position.
	 * @return array           Column data structure.
	 */
	private function build_weekly_timeline_column( array $weekly, string $position ): array {
		$content_pool = array();

		if ( ! empty( $weekly['schedule'] ) ) {
			$schedule = $weekly['schedule'];
			$days     = array(
				1 => __( 'Monday', 'smart-cycle-discounts' ),
				2 => __( 'Tuesday', 'smart-cycle-discounts' ),
				3 => __( 'Wednesday', 'smart-cycle-discounts' ),
				4 => __( 'Thursday', 'smart-cycle-discounts' ),
				5 => __( 'Friday', 'smart-cycle-discounts' ),
				6 => __( 'Saturday', 'smart-cycle-discounts' ),
				7 => __( 'Sunday', 'smart-cycle-discounts' ),
			);

			$start_day = $days[ $schedule['start_day'] ];
			$end_day   = $days[ $schedule['end_day'] ];

			$content_pool[] = array(
				'type'   => 'info',
				'icon'   => 'calendar',
				'text'   => sprintf(
					/* translators: %1$s: start day, %2$s: start time, %3$s: end day, %4$s: end time */
					__( 'Runs every week from %1$s %2$s to %3$s %4$s', 'smart-cycle-discounts' ),
					$start_day,
					$schedule['start_time'],
					$end_day,
					$schedule['end_time']
				),
				'weight' => 3,
			);

			$content_pool[] = array(
				'type'   => 'info',
				'icon'   => 'flag',
				'text'   => sprintf(
					/* translators: %1$s: start day, %2$s: start time */
					__( 'Campaign starts: %1$s at %2$s', 'smart-cycle-discounts' ),
					$start_day,
					$schedule['start_time']
				),
				'weight' => 2,
			);

			$content_pool[] = array(
				'type'   => 'info',
				'icon'   => 'flag',
				'text'   => sprintf(
					/* translators: %1$s: end day, %2$s: end time */
					__( 'Campaign ends: %1$s at %2$s', 'smart-cycle-discounts' ),
					$end_day,
					$schedule['end_time']
				),
				'weight' => 2,
			);

			$duration_days = ( $schedule['end_day'] - $schedule['start_day'] );
			if ( $duration_days < 0 ) {
				$duration_days += 7; // Wrap around week.
			}
			if ( $duration_days > 0 ) {
				$content_pool[] = array(
					'type'   => 'info',
					'icon'   => 'clock',
					'text'   => sprintf(
						/* translators: %d: number of days */
						_n( 'Runs for %d day each week', 'Runs for %d days each week', $duration_days, 'smart-cycle-discounts' ),
						$duration_days
					),
					'weight' => 1,
				);
			}
		}

		if ( isset( $weekly['prep_time'] ) ) {
			$prep_time      = absint( $weekly['prep_time'] );
			$content_pool[] = array(
				'type'   => 'info',
				'icon'   => 'admin-settings',
				'text'   => 0 === $prep_time
					? __( 'Can be created day-of', 'smart-cycle-discounts' )
					: sprintf(
						/* translators: %d: number of days */
						_n( 'Prepare %d day in advance', 'Prepare %d days in advance', $prep_time, 'smart-cycle-discounts' ),
						$prep_time
					),
				'weight' => 1,
			);
		}

		if ( ! empty( $weekly['psychology'] ) ) {
			$content_pool[] = array(
				'type'   => 'info',
				'icon'   => 'lightbulb',
				'text'   => $weekly['psychology'],
				'weight' => 1,
			);
		}

		$content_pool[] = array(
			'type'   => 'info',
			'icon'   => 'update',
			'text'   => __( 'Repeats automatically every week', 'smart-cycle-discounts' ),
			'weight' => 1,
		);

		if ( ! empty( $weekly['position'] ) ) {
			$position_texts = array(
				'first'  => __( 'Best launched early in the week', 'smart-cycle-discounts' ),
				'middle' => __( 'Peak engagement mid-week', 'smart-cycle-discounts' ),
				'last'   => __( 'Capitalize on weekend browsing behavior', 'smart-cycle-discounts' ),
			);

			if ( isset( $position_texts[ $weekly['position'] ] ) ) {
				$content_pool[] = array(
					'type'   => 'info',
					'icon'   => 'chart-area',
					'text'   => $position_texts[ $weekly['position'] ],
					'weight' => 1,
				);
			}
		}

		// Randomly select 3 items from pool with weighted selection.
		$selected_content = $this->weighted_random_select( $content_pool, 3 );

		return array(
			'id'      => 'timeline',
			'label'   => __( 'Timeline', 'smart-cycle-discounts' ),
			'icon'    => 'calendar',
			'content' => $selected_content,
		);
	}

	/**
	 * Build basic insights for weekly campaigns.
	 *
	 * @since  1.0.0
	 * @param  string $campaign_id  Campaign ID.
	 * @param  string $position        Timeline position.
	 * @return array                Insights data structure.
	 */
	private function build_weekly_insights( string $campaign_id, string $position ): array {
		$title = '';
		$icon  = 'info';

		// Position-specific title and icon.
		switch ( $position ) {
			case 'past':
				$title = __( 'Campaign Results', 'smart-cycle-discounts' );
				$icon  = 'chart-line';
				break;
			case 'active':
				$title = __( 'Ready to Launch', 'smart-cycle-discounts' );
				$icon  = 'star-filled';
				break;
			case 'future':
				$title = __( 'Planning Ahead', 'smart-cycle-discounts' );
				$icon  = 'calendar';
				break;
		}

		// Single tab for weekly campaigns.
		$tabs = array(
			array(
				'id'       => 'overview',
				'label'    => __( 'Overview', 'smart-cycle-discounts' ),
				'icon'     => 'admin-generic',
				'sections' => array(
					array(
						'heading'      => __( 'This Week\'s Opportunity', 'smart-cycle-discounts' ),
						'icon'         => 'calendar',
						'default_open' => true,
						'content'      => array(
							array(
								'type' => 'message',
								'icon' => 'calendar',
								'text' => __( 'Create a targeted campaign for this week\'s sales opportunity', 'smart-cycle-discounts' ),
							),
							array(
								'type' => 'tip',
								'icon' => 'yes',
								'text' => __( 'Weekend sales typically perform best with 10-15% discounts', 'smart-cycle-discounts' ),
							),
						),
					),
				),
			),
		);

		return array(
			'title' => $title,
			'icon'  => $icon,
			'tabs'  => $tabs,
		);
	}

	/**
	 * Get appropriate dashicon for a statistic based on its label.
	 *
	 * @since  1.0.0
	 * @param  string $label Statistic label (snake_case).
	 * @return string        Dashicon name (without 'dashicons-' prefix).
	 */
	private function get_stat_icon( string $label ): string {
		// Map common statistic types to appropriate icons.
		$icon_map = array(
			'conversion_lift'  => 'chart-line',
			'peak_time'        => 'clock',
			'avg_order'        => 'money-alt',
			'average_order'    => 'money-alt',
			'order_value'      => 'money-alt',
			'discount'         => 'tag',
			'optimal_discount' => 'tag',
			'conversion'       => 'chart-area',
			'conversion_rate'  => 'chart-area',
			'revenue'          => 'chart-bar',
			'sales'            => 'cart',
			'customers'        => 'groups',
			'orders'           => 'clipboard',
			'engagement'       => 'heart',
			'traffic'          => 'admin-site',
			'bounce_rate'      => 'undo',
			'time_on_site'     => 'backup',
			'repeat_purchase'  => 'update',
			'margin'           => 'performance',
		);

		return $icon_map[ $label ] ?? 'chart-line';
	}

	/**
	 * Weighted random selection from content pool.
	 *
	 * Selects N items from the pool with weighted probability.
	 * Items with higher weights are more likely to be selected.
	 *
	 * @since  1.0.0
	 * @param  array $pool  Array of items with 'weight' property.
	 * @param  int   $count Number of items to select.
	 * @return array        Selected items (without weight property).
	 */
	private function weighted_random_select( array $pool, int $count ): array {
		if ( empty( $pool ) ) {
			return array();
		}

		// If pool is smaller than requested count, return all items.
		if ( count( $pool ) <= $count ) {
			// Remove weight property - avoid closure for serialization compatibility.
			$result = array();
			foreach ( $pool as $item ) {
				unset( $item['weight'] );
				$result[] = $item;
			}
			return $result;
		}

		$selected       = array();
		$remaining_pool = $pool;

		for ( $i = 0; $i < $count; $i++ ) {
			if ( empty( $remaining_pool ) ) {
				break;
			}

			$total_weight = array_sum( array_column( $remaining_pool, 'weight' ) );

			// Generate random number between 0 and total weight.
			$random = wp_rand( 0, $total_weight );

			// Select item based on weighted probability.
			$cumulative_weight = 0;
			$selected_index    = 0;

			foreach ( $remaining_pool as $index => $item ) {
				$cumulative_weight += $item['weight'];
				if ( $random <= $cumulative_weight ) {
					$selected_index = $index;
					break;
				}
			}

			$selected_item = $remaining_pool[ $selected_index ];
			unset( $selected_item['weight'] );
			$selected[] = $selected_item;

			array_splice( $remaining_pool, $selected_index, 1 );
			$remaining_pool = array_values( $remaining_pool ); // Re-index array.
		}

		return $selected;
	}

	/**
	 * Build position-aware CTA button for insights.
	 *
	 * Generates CTA button that matches the card button logic:
	 * - Active position + active state: "Create Campaign"
	 * - Active position + future state: "Schedule Campaign" (with schedule=1)
	 * - Past position (weekly only): "Plan Next"
	 * - Future position (weekly only): "Plan Ahead"
	 * - Major events in past/future: No CTA (informational only)
	 *
	 * @since  1.0.0
	 * @param  string $campaign_id    Campaign/event ID.
	 * @param  string $campaign_name  Campaign/event name.
	 * @param  string $position       Timeline position (past/active/future).
	 * @param  string $state          Campaign state (past/active/future).
	 * @param  bool   $is_major_event Whether this is a major event.
	 * @return array|null             CTA array or null if no CTA should be shown.
	 */
	private function build_position_aware_cta( string $campaign_id, string $campaign_name, string $position, string $state, bool $is_major_event ): ?array {
		$base_url = admin_url( 'admin.php?page=wsscd-campaigns&action=wizard&intent=new&suggestion=' . $campaign_id );

		// Active position (focus slot): Always show CTA.
		if ( 'active' === $position ) {
			// Check state to determine Create vs Schedule.
			if ( 'active' === $state ) {
				// Campaign is currently running - create now.
				return array(
					'type' => 'cta',
					'url'  => $base_url,
					'text' => $is_major_event
						? sprintf(
							/* translators: %s: event name */
							__( '✨ Create %s Campaign', 'smart-cycle-discounts' ),
							$campaign_name
						)
						: sprintf(
							/* translators: %s: campaign name */
							__( '⚡ Create %s Campaign', 'smart-cycle-discounts' ),
							$campaign_name
						),
				);
			} else {
				// Future campaign in focus slot - schedule ahead.
				$schedule_url = add_query_arg( 'schedule', '1', $base_url );
				return array(
					'type' => 'cta',
					'url'  => $schedule_url,
					'text' => $is_major_event
						? sprintf(
							/* translators: %s: event name */
							__( '📅 Schedule %s Campaign', 'smart-cycle-discounts' ),
							$campaign_name
						)
						: sprintf(
							/* translators: %s: campaign name */
							__( '📅 Schedule %s Campaign', 'smart-cycle-discounts' ),
							$campaign_name
						),
				);
			}
		}

		// Major events in past/future positions: No CTA (informational only).
		if ( $is_major_event ) {
			return null;
		}

		// Weekly campaigns in past/future positions get planning CTAs.
		if ( 'past' === $position ) {
			return array(
				'type' => 'cta',
				'url'  => $base_url,
				'text' => __( 'Plan Next Occurrence', 'smart-cycle-discounts' ),
			);
		}

		if ( 'future' === $position ) {
			return array(
				'type' => 'cta',
				'url'  => $base_url,
				'text' => __( 'Plan Ahead', 'smart-cycle-discounts' ),
			);
		}

		return null;
	}
}
