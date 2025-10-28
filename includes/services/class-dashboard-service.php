<?php
/**
 * Dashboard Service
 *
 * Centralized service for dashboard data operations.
 * Provides single source of truth for dashboard business logic,
 * eliminating the need for reflection API hacks.
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
 * Handles all dashboard data operations and business logic.
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
	 * Initialize the dashboard service.
	 *
	 * @since    1.0.0
	 * @param    SCD_Analytics_Dashboard     $analytics_dashboard    Analytics dashboard.
	 * @param    SCD_Campaign_Repository     $campaign_repository    Campaign repository.
	 * @param    SCD_Campaign_Health_Service $health_service         Campaign health service.
	 * @param    SCD_Feature_Gate            $feature_gate           Feature gate.
	 * @param    SCD_Logger                  $logger                 Logger instance.
	 */
	public function __construct(
		SCD_Analytics_Dashboard $analytics_dashboard,
		SCD_Campaign_Repository $campaign_repository,
		SCD_Campaign_Health_Service $health_service,
		SCD_Feature_Gate $feature_gate,
		SCD_Logger $logger
	) {
		$this->analytics_dashboard = $analytics_dashboard;
		$this->campaign_repository = $campaign_repository;
		$this->health_service      = $health_service;
		$this->feature_gate        = $feature_gate;
		$this->logger              = $logger;

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

		// Get timeline campaigns with positioning data (replaces view query)
		$timeline_campaigns = $this->get_timeline_campaigns( 30 );

		return array(
			'metrics'            => $metrics,
			'campaign_stats'     => $campaign_stats,
			'top_campaigns'      => $top_campaigns,
			'recent_activity'    => $recent_activity,
			'campaign_health'    => $campaign_health,
			'all_campaigns'      => $all_campaigns,
			'timeline_campaigns' => $timeline_campaigns,
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
	 * Replaces direct DB query in view (main-dashboard.php lines 655-673).
	 * Returns campaigns sorted by urgency with pre-computed display data.
	 *
	 * @since    1.0.0
	 * @param    int $limit    Number of campaigns to retrieve.
	 * @return   array            Recent campaigns prepared for display.
	 */
	public function get_recent_campaigns( int $limit = 5 ): array {
		// Use repository layer with find_by() for multiple statuses
		$campaigns = $this->campaign_repository->find_by(
			array(
				'status' => array( 'active', 'scheduled', 'paused', 'draft' ),
			),
			array(
				'order_by'        => 'created_at',
				'order_direction' => 'DESC',
				'limit'           => $limit * 2, // Get more to allow urgency sorting
			)
		);

		// Debug: Log what we found
		$this->logger->debug(
			'Dashboard Service: get_recent_campaigns()',
			array(
				'campaigns_found' => count( $campaigns ),
				'limit_requested' => $limit,
			)
		);

		// Pre-compute all display data
		$prepared = array();
		foreach ( $campaigns as $campaign ) {
			// Convert Campaign object to array
			$campaign_array = is_object( $campaign ) ? $campaign->to_array() : $campaign;
			$prepared[]     = $this->prepare_campaign_for_display( $campaign_array );
		}

		// Sort by urgency (ending soon first)
		usort(
			$prepared,
			function ( $a, $b ) {
				// Urgent campaigns first
				if ( $a['is_urgent'] !== $b['is_urgent'] ) {
					return $b['is_urgent'] <=> $a['is_urgent'];
				}

				// Then by days remaining (ascending)
				if ( isset( $a['days_until_end'], $b['days_until_end'] ) ) {
					return $a['days_until_end'] <=> $b['days_until_end'];
				}

				// Fall back to created date
				return 0;
			}
		);

		// Return only requested limit after sorting
		return array_slice( $prepared, 0, $limit );
	}

	/**
	 * Get timeline campaigns with positioning data.
	 *
	 * Replaces direct DB query in view (main-dashboard.php lines 861-874).
	 * Returns campaigns with pre-calculated timeline positioning.
	 *
	 * @since    1.0.0
	 * @param    int $days    Timeline range in days.
	 * @return   array           Timeline campaigns with position data.
	 */
	public function get_timeline_campaigns( int $days = 30 ): array {
		// Use repository layer with find_by() for multiple statuses
		$campaigns = $this->campaign_repository->find_by(
			array(
				'status' => array( 'active', 'scheduled' ),
			),
			array(
				'order_by'        => 'starts_at',
				'order_direction' => 'ASC',
				'limit'           => 10,
			)
		);

		// Pre-calculate timeline positioning
		$now            = current_time( 'timestamp' );
		$timeline_start = $now;
		$timeline_end   = $now + ( $days * DAY_IN_SECONDS );

		$prepared = array();
		foreach ( $campaigns as $campaign ) {
			// Convert Campaign object to array
			$campaign_array                      = is_object( $campaign ) ? $campaign->to_array() : $campaign;
			$campaign_array['timeline_position'] = $this->calculate_timeline_position(
				$campaign_array,
				$timeline_start,
				$timeline_end
			);
			$prepared[]                          = $campaign_array;
		}

		return $prepared;
	}

	/**
	 * Calculate timeline bar position and width.
	 *
	 * @since    1.0.0
	 * @param    array $campaign         Campaign data.
	 * @param    int   $timeline_start   Timeline start timestamp.
	 * @param    int   $timeline_end     Timeline end timestamp.
	 * @return   array                      Position data (left, width, formatted dates).
	 */
	private function calculate_timeline_position( array $campaign, int $timeline_start, int $timeline_end ): array {
		$start_time = ! empty( $campaign['starts_at'] ) ? strtotime( $campaign['starts_at'] ) : $timeline_start;
		$end_time   = ! empty( $campaign['ends_at'] ) ? strtotime( $campaign['ends_at'] ) : $timeline_end;

		// Calculate left position (%)
		$left_pos = 0;
		if ( $start_time > $timeline_start ) {
			$left_pos = ( ( $start_time - $timeline_start ) / ( $timeline_end - $timeline_start ) ) * 100;
		}

		// Calculate width (%)
		$width = 100;
		if ( $end_time < $timeline_end ) {
			$right_pos = ( ( $end_time - $timeline_start ) / ( $timeline_end - $timeline_start ) ) * 100;
			$width     = $right_pos - $left_pos;
		} else {
			$width = 100 - $left_pos;
		}

		// Minimum visible width
		$width = max( 2, $width );

		return array(
			'left'                 => round( $left_pos, 2 ),
			'width'                => round( $width, 2 ),
			'start_date_formatted' => wp_date( 'M j', $start_time ),
			'end_date_formatted'   => wp_date( 'M j', $end_time ),
			'date_range'           => wp_date( 'M j', $start_time ) . ' - ' . wp_date( 'M j', $end_time ),
		);
	}

	/**
	 * Prepare campaign for display with all computed fields.
	 *
	 * Pre-computes all display data so view template can be "dumb".
	 *
	 * @since    1.0.0
	 * @param    array $campaign    Campaign data array.
	 * @return   array                Campaign with all display fields added.
	 */
	private function prepare_campaign_for_display( array $campaign ): array {
		$now = new DateTime( 'now', new DateTimeZone( 'UTC' ) );

		// Time remaining calculations
		$time_data = $this->calculate_time_data( $campaign, $now );

		// Urgency checks
		$urgency_data = $this->calculate_urgency_data( $campaign, $now );

		// Status formatting
		$status_data = $this->format_status_data( $campaign );

		// Merge everything
		return array_merge( $campaign, $time_data, $urgency_data, $status_data );
	}

	/**
	 * Calculate all time-related display data.
	 *
	 * @since    1.0.0
	 * @param    array    $campaign    Campaign data.
	 * @param    DateTime $now         Current datetime object.
	 * @return   array                    Time data array.
	 */
	private function calculate_time_data( array $campaign, DateTime $now ): array {
		$data = array(
			'time_remaining_text'   => '',
			'time_until_start_text' => '',
			'days_until_end'        => null,
			'days_until_start'      => null,
		);

		// Active campaign - calculate time until end
		if ( 'active' === $campaign['status'] && ! empty( $campaign['ends_at'] ) ) {
			$end_date     = new DateTime( $campaign['ends_at'], new DateTimeZone( 'UTC' ) );
			$diff_seconds = $end_date->getTimestamp() - $now->getTimestamp();

			if ( $diff_seconds > 0 ) {
				$data['days_until_end']      = floor( $diff_seconds / DAY_IN_SECONDS );
				$data['time_remaining_text'] = $this->format_time_remaining( $diff_seconds );
			}
		}

		// Scheduled campaign - calculate time until start
		if ( 'scheduled' === $campaign['status'] && ! empty( $campaign['starts_at'] ) ) {
			$start_date   = new DateTime( $campaign['starts_at'], new DateTimeZone( 'UTC' ) );
			$diff_seconds = $start_date->getTimestamp() - $now->getTimestamp();

			if ( $diff_seconds > 0 ) {
				$data['days_until_start']      = floor( $diff_seconds / DAY_IN_SECONDS );
				$data['time_until_start_text'] = $this->format_time_until_start( $diff_seconds );
			}
		}

		return $data;
	}

	/**
	 * Format time remaining in human-readable format.
	 *
	 * @since    1.0.0
	 * @param    int $seconds    Seconds remaining.
	 * @return   string             Formatted time string.
	 */
	private function format_time_remaining( int $seconds ): string {
		if ( $seconds < DAY_IN_SECONDS ) {
			// Less than 1 day - show hours and minutes
			$hours   = floor( $seconds / HOUR_IN_SECONDS );
			$minutes = floor( ( $seconds % HOUR_IN_SECONDS ) / MINUTE_IN_SECONDS );

			if ( $hours > 0 ) {
				return sprintf(
					/* translators: 1: hours, 2: minutes */
					_n( 'Ends in %1$d hour %2$d min', 'Ends in %1$d hours %2$d min', $hours, 'smart-cycle-discounts' ),
					$hours,
					$minutes
				);
			} else {
				return sprintf(
					/* translators: %d: minutes */
					_n( 'Ends in %d minute', 'Ends in %d minutes', $minutes, 'smart-cycle-discounts' ),
					$minutes
				);
			}
		} else {
			// Show days
			$days = floor( $seconds / DAY_IN_SECONDS );
			return sprintf(
				/* translators: %d: days */
				_n( 'Ends in %d day', 'Ends in %d days', $days, 'smart-cycle-discounts' ),
				$days
			);
		}
	}

	/**
	 * Format time until start in human-readable format.
	 *
	 * @since    1.0.0
	 * @param    int $seconds    Seconds until start.
	 * @return   string             Formatted time string.
	 */
	private function format_time_until_start( int $seconds ): string {
		if ( $seconds < DAY_IN_SECONDS ) {
			// Less than 1 day - show hours
			$hours = floor( $seconds / HOUR_IN_SECONDS );
			return sprintf(
				/* translators: %d: hours */
				_n( 'Starts in %d hour', 'Starts in %d hours', $hours, 'smart-cycle-discounts' ),
				$hours
			);
		} else {
			// Show days
			$days = floor( $seconds / DAY_IN_SECONDS );
			return sprintf(
				/* translators: %d: days */
				_n( 'Starts in %d day', 'Starts in %d days', $days, 'smart-cycle-discounts' ),
				$days
			);
		}
	}

	/**
	 * Calculate urgency flags.
	 *
	 * @since    1.0.0
	 * @param    array    $campaign    Campaign data.
	 * @param    DateTime $now         Current datetime object.
	 * @return   array                    Urgency data array.
	 */
	private function calculate_urgency_data( array $campaign, DateTime $now ): array {
		$is_ending_soon   = false;
		$is_starting_soon = false;

		// Check if ending soon (within 7 days)
		if ( 'active' === $campaign['status'] && ! empty( $campaign['ends_at'] ) ) {
			$end_date       = new DateTime( $campaign['ends_at'], new DateTimeZone( 'UTC' ) );
			$diff_days      = ( $end_date->getTimestamp() - $now->getTimestamp() ) / DAY_IN_SECONDS;
			$is_ending_soon = $diff_days >= 0 && $diff_days <= 7;
		}

		// Check if starting soon (within 7 days)
		if ( 'scheduled' === $campaign['status'] && ! empty( $campaign['starts_at'] ) ) {
			$start_date       = new DateTime( $campaign['starts_at'], new DateTimeZone( 'UTC' ) );
			$diff_days        = ( $start_date->getTimestamp() - $now->getTimestamp() ) / DAY_IN_SECONDS;
			$is_starting_soon = $diff_days >= 0 && $diff_days <= 7;
		}

		return array(
			'is_ending_soon'   => $is_ending_soon,
			'is_starting_soon' => $is_starting_soon,
			'is_urgent'        => $is_ending_soon || $is_starting_soon,
		);
	}

	/**
	 * Format status data for display.
	 *
	 * @since    1.0.0
	 * @param    array $campaign    Campaign data.
	 * @return   array                 Status data array.
	 */
	private function format_status_data( array $campaign ): array {
		$status = $campaign['status'];

		return array(
			'status_badge_class' => 'scd-status-' . $status,
			'status_label'       => ucfirst( $status ),
			'status_icon'        => $this->get_status_icon( $status ),
		);
	}

	/**
	 * Get dashicon for status.
	 *
	 * @since    1.0.0
	 * @param    string $status    Campaign status.
	 * @return   string               Dashicon name.
	 */
	private function get_status_icon( string $status ): string {
		$icons = array(
			'active'    => 'yes-alt',
			'scheduled' => 'calendar-alt',
			'paused'    => 'controls-pause',
			'draft'     => 'edit',
			'expired'   => 'clock',
		);

		return $icons[ $status ] ?? 'admin-generic';
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
}
