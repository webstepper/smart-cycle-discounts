<?php
/**
 * Dashboard Service
 *
 * Orchestrator service for dashboard data operations.
 * Coordinates dashboard data assembly by delegating to specialized sub-services:
 * - Campaign Suggestions Service (event-based suggestions)
 * - Campaign Display Service (display preparation)
 * - Campaign Timeline Service (weekly timeline with major events)
 * - Campaign Health Service (health monitoring)
 *
 * Provides caching layer and single source of truth for dashboard business logic.
 *
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/services
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Dashboard Service Class
 *
 * Orchestrator for dashboard data operations. Delegates to specialized services:
 * - Campaign Suggestions Service: Event-based suggestions with timing windows
 * - Campaign Display Service: Campaign preparation for display
 * - Campaign Timeline Service: Weekly timeline with dynamic major event integration
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
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Dashboard_Service {

	/**
	 * Cache group name for dashboard transients.
	 *
	 * @since    1.0.0
	 */
	const CACHE_GROUP = 'scd_dashboard';

	/**
	 * Cache TTL (Time To Live) in seconds.
	 * 5 minutes = 300 seconds.
	 *
	 * @since    1.0.0
	 */
	const CACHE_TTL = 300;

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
	 * Campaign health service instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Campaign_Health_Service    $health_service    Campaign health service.
	 */
	private SCD_Campaign_Health_Service $health_service;

	/**
	 * Feature gate instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Feature_Gate    $feature_gate    Feature gate.
	 */
	private SCD_Feature_Gate $feature_gate;


	/**
	 * Logger instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Logger    $logger    Logger instance.
	 */
	private SCD_Logger $logger;

	/**
	 * Campaign suggestions service instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Campaign_Suggestions_Service    $suggestions_service    Campaign suggestions service.
	 */
	private SCD_Campaign_Suggestions_Service $suggestions_service;

	/**
	 * Campaign display service instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Campaign_Display_Service    $display_service    Campaign display service.
	 */
	private SCD_Campaign_Display_Service $display_service;

	/**
	 * Campaign timeline service instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Campaign_Timeline_Service    $timeline_service    Campaign timeline service.
	 */
	private SCD_Campaign_Timeline_Service $timeline_service;

	/**
	 * Initialize the dashboard service.
	 *
	 * @since    1.0.0
	 * @param    SCD_Analytics_Dashboard          $analytics_dashboard    Analytics dashboard.
	 * @param    SCD_Campaign_Repository          $campaign_repository    Campaign repository.
	 * @param    SCD_Campaign_Health_Service      $health_service         Campaign health service.
	 * @param    SCD_Feature_Gate                 $feature_gate           Feature gate.
	 * @param    SCD_Logger                       $logger                 Logger instance.
	 * @param    SCD_Campaign_Suggestions_Service $suggestions_service    Campaign suggestions service.
	 * @param    SCD_Campaign_Display_Service     $display_service        Campaign display service.
	 * @param    SCD_Campaign_Timeline_Service    $timeline_service       Campaign timeline service.
	 */
	public function __construct(
		SCD_Analytics_Dashboard $analytics_dashboard,
		SCD_Campaign_Repository $campaign_repository,
		SCD_Campaign_Health_Service $health_service,
		SCD_Feature_Gate $feature_gate,
		SCD_Logger $logger,
		SCD_Campaign_Suggestions_Service $suggestions_service,
		SCD_Campaign_Display_Service $display_service,
		SCD_Campaign_Timeline_Service $timeline_service
	) {
		$this->analytics_dashboard = $analytics_dashboard;
		$this->campaign_repository = $campaign_repository;
		$this->health_service      = $health_service;
		$this->feature_gate        = $feature_gate;
		$this->logger              = $logger;
		$this->suggestions_service = $suggestions_service;
		$this->display_service     = $display_service;
		$this->timeline_service    = $timeline_service;

		// Register cache invalidation hooks
		$this->register_cache_hooks();
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
		// Set defaults
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
			if ( false !== $cached ) {
				$this->logger->debug(
					'Dashboard data served from cache',
					array(
						'cache_key' => $cache_key,
					)
				);
				return $cached;
			}
		}

		// Cache miss - calculate fresh data
		$this->logger->debug(
			'Dashboard cache miss, calculating fresh data',
			array(
				'cache_key'     => $cache_key,
				'force_refresh' => $force_refresh,
			)
		);

		$data = $this->calculate_dashboard_data( $options );

		// Store in cache
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
		// Get overview metrics from analytics dashboard (includes pre-calculated trends)
		$metrics = $this->analytics_dashboard->get_dashboard_metrics( $options['date_range'], true );

		// Get campaign status breakdown
		$campaign_stats = $this->get_campaign_stats();

		// Get top 3 campaigns (free tier limit)
		$top_campaigns = $this->get_top_campaigns( 3, $options['date_range'] );

		// Get recent activity (if requested)
		$recent_activity = $options['include_activity'] ? $this->get_recent_activity( 5 ) : array();

		// Get campaign health checks (if requested)
		$campaign_health = $options['include_health'] ? $this->get_campaign_health() : $this->get_empty_health_structure();

		// Get recent campaigns with pre-computed display data (replaces view query)
		$all_campaigns = $this->get_recent_campaigns( 5 );

		// Get weekly timeline data (dynamic 3-card selection: past/active/future)
		$timeline_data = $this->get_weekly_timeline_campaigns();

		return array(
			'metrics'            => $metrics,
			'campaign_stats'     => $campaign_stats,
			'top_campaigns'      => $top_campaigns,
			'recent_activity'    => $recent_activity,
			'campaign_health'    => $campaign_health,
			'all_campaigns'      => $all_campaigns,
			'timeline_data'      => $timeline_data,
			'is_premium'         => $this->feature_gate->is_premium(),
			'campaign_limit'     => $this->feature_gate->get_campaign_limit(),
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
	 * @param    int    $limit         Number of campaigns to retrieve.
	 * @param    string $date_range    Date range.
	 * @return   array                    Top campaigns.
	 */
	private function get_top_campaigns( int $limit, string $date_range ): array {
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
	 * @param    int $limit    Number of events to retrieve.
	 * @return   array            Recent activity events.
	 */
	private function get_recent_activity( int $limit ): array {
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
	 * @param    string $service_status    Status from health service.
	 * @return   string                       Dashboard status.
	 */
	private function map_status_to_dashboard( string $service_status ): string {
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
		return get_transient( self::CACHE_GROUP . '_' . $key );
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
		set_transient( self::CACHE_GROUP . '_' . $key, $data, self::CACHE_TTL );
	}

	/**
	 * Invalidate dashboard cache for specific user.
	 *
	 * Called when campaign data changes that affects this user's dashboard.
	 *
	 * @since    1.0.0
	 * @param    int $user_id    User ID. Null for current user.
	 * @return   void
	 */
	public function invalidate_cache( int $user_id = null ): void {
		if ( null === $user_id ) {
			$user_id = get_current_user_id();
		}

		global $wpdb;

		// Delete all dashboard transients for this user
		$pattern = $wpdb->esc_like( '_transient_' . self::CACHE_GROUP . '_dashboard_' . $user_id . '_' ) . '%';

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options}
				WHERE option_name LIKE %s",
				$pattern
			)
		);

		// Also delete timeout options
		$timeout_pattern = $wpdb->esc_like( '_transient_timeout_' . self::CACHE_GROUP . '_dashboard_' . $user_id . '_' ) . '%';

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options}
				WHERE option_name LIKE %s",
				$timeout_pattern
			)
		);

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

		// Delete all dashboard transients
		$pattern = $wpdb->esc_like( '_transient_' . self::CACHE_GROUP . '_' ) . '%';

		$count = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options}
				WHERE option_name LIKE %s",
				$pattern
			)
		);

		// Also delete timeout options
		$timeout_pattern = $wpdb->esc_like( '_transient_timeout_' . self::CACHE_GROUP . '_' ) . '%';

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options}
				WHERE option_name LIKE %s",
				$timeout_pattern
			)
		);

		$this->logger->info(
			'Invalidated all dashboard caches',
			array(
				'transients_deleted' => $count,
			)
		);
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
		add_action( 'scd_campaign_created', array( $this, 'on_campaign_changed' ), 10, 1 );
		add_action( 'scd_campaign_updated', array( $this, 'on_campaign_changed' ), 10, 1 );
		add_action( 'scd_campaign_deleted', array( $this, 'on_campaign_changed' ), 10, 1 );
		add_action( 'scd_campaign_status_changed', array( $this, 'on_campaign_changed' ), 10, 1 );

		// Invalidate for wizard-created/updated campaigns (passes campaign ID, not object)
		add_action( 'scd_campaign_created_from_wizard', array( $this, 'on_campaign_changed_by_id' ), 10, 1 );
		add_action( 'scd_campaign_updated_from_wizard', array( $this, 'on_campaign_changed_by_id' ), 10, 1 );
		add_action( 'scd_campaign_created_from_data', array( $this, 'on_campaign_changed_by_id' ), 10, 1 );

		// Invalidate when settings change
		add_action( 'scd_settings_updated', array( $this, 'invalidate_all_caches' ) );

		// Invalidate when license status changes
		add_action( 'scd_license_activated', array( $this, 'invalidate_all_caches' ) );
		add_action( 'scd_license_deactivated', array( $this, 'invalidate_all_caches' ) );
	}

	/**
	 * Handle campaign change event.
	 *
	 * Invalidates cache for campaign owner and all admins.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign|int $campaign    Campaign object or ID.
	 * @return   void
	 */
	public function on_campaign_changed( $campaign ): void {
		// Get campaign owner if we have a campaign object
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
	 * Get weekly timeline campaigns with dynamic selection.
	 *
	 * Delegates to Campaign Timeline Service.
	 * Intelligently mixes major events and weekly campaigns based on priority.
	 * Each position (past/active/future) shows the most relevant campaign.
	 *
	 * @since  1.0.0
	 * @return array Timeline data with 3 selected campaigns.
	 */
	public function get_weekly_timeline_campaigns(): array {
		return $this->timeline_service->get_weekly_timeline_campaigns();
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
	 * Get unified insights for a timeline campaign.
	 *
	 * Returns structured insights data for displaying in timeline insights panel.
	 * Creates comprehensive Why/How/When tab structure with rich data for BOTH
	 * major events (from Campaign Suggestions Registry) AND weekly campaigns
	 * (from Weekly Campaign Definitions).
	 *
	 * @since  1.0.0
	 * @param  string $campaign_id     Campaign ID.
	 * @param  string $state           Campaign state (past/active/future).
	 * @param  bool   $is_major_event  Whether this is a major event.
	 * @return array                   Insights data structure with 'tabs' key.
	 */
	public function get_unified_insights( string $campaign_id, string $state, bool $is_major_event ): array {
		// If major event, get rich event data from Registry.
		if ( $is_major_event ) {
			$event = SCD_Campaign_Suggestions_Registry::get_event_by_id( $campaign_id );

			if ( $event ) {
				return $this->build_event_insights( $event, $state );
			}
		}

		// For weekly campaigns, get rich data from Weekly Definitions.
		require_once SCD_INCLUDES_DIR . 'core/campaigns/class-weekly-campaign-definitions.php';
		$weekly = SCD_Weekly_Campaign_Definitions::get_by_id( $campaign_id );

		if ( $weekly ) {
			return $this->build_weekly_event_insights( $weekly, $state );
		}

		// Fallback to basic structure if no data found.
		return $this->build_weekly_insights( $campaign_id, $state );
	}

	/**
	 * Build comprehensive insights for major events with Why/How/When tabs.
	 *
	 * @since  1.0.0
	 * @param  array  $event  Event definition from Campaign Suggestions Registry.
	 * @param  string $state  Campaign state (past/active/future).
	 * @return array          Insights data structure.
	 */
	private function build_event_insights( array $event, string $state ): array {
		$tabs = array(
			$this->build_why_tab( $event, $state ),
			$this->build_how_tab( $event, $state ),
			$this->build_when_tab( $event, $state ),
		);

		return array(
			'title' => $event['name'],
			'icon'  => 'calendar-alt',
			'tabs'  => $tabs,
		);
	}

	/**
	 * Build comprehensive insights for weekly campaigns with Why/How/When tabs.
	 *
	 * Uses rich data from Weekly Campaign Definitions to build the same comprehensive
	 * tab structure as major events.
	 *
	 * @since  1.0.0
	 * @param  array  $weekly  Weekly campaign definition from Weekly Campaign Definitions.
	 * @param  string $state   Campaign state (past/active/future).
	 * @return array           Insights data structure.
	 */
	private function build_weekly_event_insights( array $weekly, string $state ): array {
		$tabs = array(
			$this->build_weekly_why_tab( $weekly, $state ),
			$this->build_weekly_how_tab( $weekly, $state ),
			$this->build_weekly_when_tab( $weekly, $state ),
		);

		return array(
			'title' => $weekly['name'],
			'icon'  => 'calendar-alt',
			'tabs'  => $tabs,
		);
	}

	/**
	 * Build "Why This Opportunity?" tab - Market Intelligence.
	 *
	 * @since  1.0.0
	 * @param  array  $event  Event definition.
	 * @param  string $state  Campaign state.
	 * @return array          Tab data structure.
	 */
	private function build_why_tab( array $event, string $state ): array {
		$sections = array();

		// Section 1: Event Description & Statistics.
		$content = array(
			array(
				'type' => 'message',
				'icon' => 'calendar-alt',
				'text' => $event['description'],
			),
		);

		if ( ! empty( $event['statistics'] ) ) {
			foreach ( $event['statistics'] as $label => $value ) {
				$content[] = array(
					'type'  => 'stat',
					'label' => ucwords( str_replace( '_', ' ', $label ) ),
					'value' => $value,
				);
			}
		}

		$sections[] = array(
			'heading'      => __( 'Market Opportunity', 'smart-cycle-discounts' ),
			'icon'         => 'chart-bar',
			'default_open' => true,
			'content'      => $content,
		);

		return array(
			'id'       => 'why',
			'label'    => __( 'Why?', 'smart-cycle-discounts' ),
			'icon'     => 'lightbulb',
			'sections' => $sections,
		);
	}

	/**
	 * Build "How to Execute?" tab - Execution Guide.
	 *
	 * @since  1.0.0
	 * @param  array  $event  Event definition.
	 * @param  string $state  Campaign state.
	 * @return array          Tab data structure.
	 */
	private function build_how_tab( array $event, string $state ): array {
		$sections = array();

		// Section 1: Suggested Discount Range.
		if ( ! empty( $event['suggested_discount'] ) ) {
			$discount = $event['suggested_discount'];
			$sections[] = array(
				'heading'      => __( 'Suggested Discount Range', 'smart-cycle-discounts' ),
				'icon'         => 'tag',
				'default_open' => true,
				'content'      => array(
					array(
						'type'  => 'stat',
						'label' => __( 'Optimal Discount', 'smart-cycle-discounts' ),
						'value' => $discount['optimal'] . '%',
					),
					array(
						'type' => 'text',
						'text' => sprintf(
							/* translators: %1$d: minimum discount, %2$d: maximum discount */
							__( 'Range: %1$d%% - %2$d%% based on industry performance', 'smart-cycle-discounts' ),
							$discount['min'],
							$discount['max']
						),
					),
				),
			);
		}

		// Section 2: Preparation Checklist.
		if ( ! empty( $event['recommendations'] ) && ( 'active' === $state || 'future' === $state ) ) {
			$checklist_items = array();
			foreach ( $event['recommendations'] as $recommendation ) {
				$checklist_items[] = array(
					'type' => 'checklist_item',
					'text' => $recommendation,
				);
			}

			$sections[] = array(
				'heading'      => __( 'Preparation Checklist', 'smart-cycle-discounts' ),
				'icon'         => 'yes-alt',
				'default_open' => false,
				'content'      => $checklist_items,
			);
		}

		// Section 3: Marketing Tips.
		if ( ! empty( $event['tips'] ) ) {
			$tip_items = array();
			foreach ( $event['tips'] as $tip ) {
				$tip_items[] = array(
					'type' => 'tip',
					'icon' => 'megaphone',
					'text' => $tip,
				);
			}

			$sections[] = array(
				'heading'      => __( 'Marketing Tips', 'smart-cycle-discounts' ),
				'icon'         => 'megaphone',
				'default_open' => false,
				'content'      => $tip_items,
			);
		}

		// Section 4: CTA for active campaigns.
		if ( 'active' === $state ) {
			$sections[] = array(
				'heading'      => __( 'Ready to Start?', 'smart-cycle-discounts' ),
				'icon'         => 'admin-generic',
				'default_open' => true,
				'content'      => array(
					array(
						'type' => 'cta',
						'url'  => admin_url( 'admin.php?page=scd-campaigns&action=wizard&intent=new&suggestion=' . $event['id'] ),
						'text' => sprintf(
							/* translators: %s: event name */
							__( 'Create %s Campaign', 'smart-cycle-discounts' ),
							$event['name']
						),
					),
				),
			);
		}

		return array(
			'id'       => 'how',
			'label'    => __( 'How?', 'smart-cycle-discounts' ),
			'icon'     => 'admin-tools',
			'sections' => $sections,
		);
	}

	/**
	 * Build "When to Launch?" tab - Launch Timeline.
	 *
	 * @since  1.0.0
	 * @param  array  $event  Event definition.
	 * @param  string $state  Campaign state.
	 * @return array          Tab data structure.
	 */
	private function build_when_tab( array $event, string $state ): array {
		$sections = array();

		// Section 1: Best Practices.
		if ( ! empty( $event['best_practices'] ) ) {
			$practice_items = array();
			foreach ( $event['best_practices'] as $practice ) {
				$practice_items[] = array(
					'type' => 'stat_text',
					'icon' => 'star-filled',
					'text' => $practice,
				);
			}

			$sections[] = array(
				'heading'      => __( 'Best Practices', 'smart-cycle-discounts' ),
				'icon'         => 'star-filled',
				'default_open' => true,
				'content'      => $practice_items,
			);
		}

		// Section 2: Timing Guidance.
		if ( ! empty( $event['event_date'] ) ) {
			$event_date = wp_date( 'F j, Y', $event['event_date'] );
			$sections[] = array(
				'heading'      => __( 'Launch Timeline', 'smart-cycle-discounts' ),
				'icon'         => 'clock',
				'default_open' => true,
				'content'      => array(
					array(
						'type' => 'message',
						'icon' => 'calendar',
						'text' => sprintf(
							/* translators: %s: event date */
							__( 'Event Date: %s', 'smart-cycle-discounts' ),
							$event_date
						),
					),
					array(
						'type' => 'text',
						'text' => sprintf(
							/* translators: %d: number of days */
							__( 'Campaign Duration: %d days', 'smart-cycle-discounts' ),
							$event['duration_days']
						),
					),
				),
			);
		}

		return array(
			'id'       => 'when',
			'label'    => __( 'When?', 'smart-cycle-discounts' ),
			'icon'     => 'calendar',
			'sections' => $sections,
		);
	}

	/**
	 * Build "Why This Opportunity?" tab for weekly campaigns.
	 *
	 * @since  1.0.0
	 * @param  array  $weekly  Weekly campaign definition.
	 * @param  string $state   Campaign state.
	 * @return array           Tab data structure.
	 */
	private function build_weekly_why_tab( array $weekly, string $state ): array {
		$sections = array();

		// Section 1: Campaign Description & Psychology.
		$content = array(
			array(
				'type' => 'message',
				'icon' => 'calendar-alt',
				'text' => $weekly['description'],
			),
		);

		if ( ! empty( $weekly['psychology'] ) ) {
			$content[] = array(
				'type' => 'tip',
				'icon' => 'admin-users',
				'text' => __( 'Psychology: ', 'smart-cycle-discounts' ) . $weekly['psychology'],
			);
		}

		$sections[] = array(
			'heading'      => __( 'Market Opportunity', 'smart-cycle-discounts' ),
			'icon'         => 'chart-bar',
			'default_open' => true,
			'content'      => $content,
		);

		// Section 2: Statistics.
		if ( ! empty( $weekly['statistics'] ) ) {
			$stat_content = array();
			foreach ( $weekly['statistics'] as $label => $value ) {
				$stat_content[] = array(
					'type'  => 'stat',
					'label' => ucwords( str_replace( '_', ' ', $label ) ),
					'value' => $value,
				);
			}

			$sections[] = array(
				'heading'      => __( 'Performance Data', 'smart-cycle-discounts' ),
				'icon'         => 'chart-line',
				'default_open' => true,
				'content'      => $stat_content,
			);
		}

		// Section 3: Best For.
		if ( ! empty( $weekly['best_for'] ) ) {
			$best_for_content = array();
			foreach ( $weekly['best_for'] as $item ) {
				$best_for_content[] = array(
					'type' => 'stat_text',
					'icon' => 'yes',
					'text' => $item,
				);
			}

			$sections[] = array(
				'heading'      => __( 'Best For', 'smart-cycle-discounts' ),
				'icon'         => 'star-filled',
				'default_open' => false,
				'content'      => $best_for_content,
			);
		}

		return array(
			'id'       => 'why',
			'label'    => __( 'Why?', 'smart-cycle-discounts' ),
			'icon'     => 'lightbulb',
			'sections' => $sections,
		);
	}

	/**
	 * Build "How to Execute?" tab for weekly campaigns.
	 *
	 * @since  1.0.0
	 * @param  array  $weekly  Weekly campaign definition.
	 * @param  string $state   Campaign state.
	 * @return array           Tab data structure.
	 */
	private function build_weekly_how_tab( array $weekly, string $state ): array {
		$sections = array();

		// Section 1: Suggested Discount Range.
		if ( ! empty( $weekly['suggested_discount'] ) ) {
			$discount = $weekly['suggested_discount'];
			$sections[] = array(
				'heading'      => __( 'Suggested Discount Range', 'smart-cycle-discounts' ),
				'icon'         => 'tag',
				'default_open' => true,
				'content'      => array(
					array(
						'type'  => 'stat',
						'label' => __( 'Optimal Discount', 'smart-cycle-discounts' ),
						'value' => $discount['optimal'] . '%',
					),
					array(
						'type' => 'text',
						'text' => sprintf(
							/* translators: %1$d: minimum discount, %2$d: maximum discount */
							__( 'Range: %1$d%% - %2$d%% based on weekly performance', 'smart-cycle-discounts' ),
							$discount['min'],
							$discount['max']
						),
					),
				),
			);
		}

		// Section 2: Recommendations Checklist.
		if ( ! empty( $weekly['recommendations'] ) && ( 'active' === $state || 'future' === $state ) ) {
			$checklist_items = array();
			foreach ( $weekly['recommendations'] as $recommendation ) {
				$checklist_items[] = array(
					'type' => 'checklist_item',
					'text' => $recommendation,
				);
			}

			$sections[] = array(
				'heading'      => __( 'Action Checklist', 'smart-cycle-discounts' ),
				'icon'         => 'yes-alt',
				'default_open' => true,
				'content'      => $checklist_items,
			);
		}

		// Section 3: CTA for active campaigns.
		if ( 'active' === $state ) {
			$sections[] = array(
				'heading'      => __( 'Ready to Start?', 'smart-cycle-discounts' ),
				'icon'         => 'admin-generic',
				'default_open' => true,
				'content'      => array(
					array(
						'type' => 'cta',
						'url'  => admin_url( 'admin.php?page=scd-campaigns&action=wizard&intent=new&suggestion=' . $weekly['id'] ),
						'text' => sprintf(
							/* translators: %s: campaign name */
							__( 'Create %s Campaign', 'smart-cycle-discounts' ),
							$weekly['name']
						),
					),
				),
			);
		}

		return array(
			'id'       => 'how',
			'label'    => __( 'How?', 'smart-cycle-discounts' ),
			'icon'     => 'admin-tools',
			'sections' => $sections,
		);
	}

	/**
	 * Build "When to Launch?" tab for weekly campaigns.
	 *
	 * @since  1.0.0
	 * @param  array  $weekly  Weekly campaign definition.
	 * @param  string $state   Campaign state.
	 * @return array           Tab data structure.
	 */
	private function build_weekly_when_tab( array $weekly, string $state ): array {
		$sections = array();

		// Section 1: Weekly Schedule.
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

			$sections[] = array(
				'heading'      => __( 'Weekly Schedule', 'smart-cycle-discounts' ),
				'icon'         => 'clock',
				'default_open' => true,
				'content'      => array(
					array(
						'type' => 'message',
						'icon' => 'calendar',
						'text' => sprintf(
							/* translators: %1$s: start day, %2$s: start time, %3$s: end day, %4$s: end time */
							__( 'Runs every week from %1$s %2$s to %3$s %4$s', 'smart-cycle-discounts' ),
							$start_day,
							$schedule['start_time'],
							$end_day,
							$schedule['end_time']
						),
					),
				),
			);
		}

		// Section 2: Preparation Time.
		if ( isset( $weekly['prep_time'] ) ) {
			$prep_time = absint( $weekly['prep_time'] );
			$sections[] = array(
				'heading'      => __( 'Preparation', 'smart-cycle-discounts' ),
				'icon'         => 'admin-tools',
				'default_open' => true,
				'content'      => array(
					array(
						'type' => 'stat_text',
						'icon' => 'clock',
						'text' => 0 === $prep_time
							? __( 'Can be created day-of', 'smart-cycle-discounts' )
							: sprintf(
								/* translators: %d: number of days */
								_n( 'Prepare %d day in advance', 'Prepare %d days in advance', $prep_time, 'smart-cycle-discounts' ),
								$prep_time
							),
					),
				),
			);
		}

		return array(
			'id'       => 'when',
			'label'    => __( 'When?', 'smart-cycle-discounts' ),
			'icon'     => 'calendar',
			'sections' => $sections,
		);
	}

	/**
	 * Build basic insights for weekly campaigns.
	 *
	 * @since  1.0.0
	 * @param  string $campaign_id  Campaign ID.
	 * @param  string $state        Campaign state.
	 * @return array                Insights data structure.
	 */
	private function build_weekly_insights( string $campaign_id, string $state ): array {
		$title = '';
		$icon  = 'info';

		// State-specific title and icon.
		switch ( $state ) {
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
}
