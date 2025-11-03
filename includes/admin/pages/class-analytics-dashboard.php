<?php
/**
 * Analytics Dashboard Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/pages/class-analytics-dashboard.php
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Analytics Dashboard Class
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/analytics
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Analytics_Dashboard {

	/**
	 * Database manager instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Database_Manager    $database_manager    Database manager.
	 */
	private $database_manager;

	/**
	 * Cache manager instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Cache_Manager    $cache_manager    Cache manager.
	 */
	private $cache_manager;

	/**
	 * Logger instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Logger    $logger    Logger.
	 */
	private $logger;

	/**
	 * Analytics table name.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $analytics_table    Analytics table name.
	 */
	private $analytics_table;

	/**
	 * Cache duration for analytics data (15 minutes).
	 */
	const CACHE_TTL = 900;

	/**
	 * Initialize the analytics dashboard.
	 *
	 * @since    1.0.0
	 * @param    SCD_Database_Manager $database_manager    Database manager.
	 * @param    SCD_Cache_Manager    $cache_manager       Cache manager.
	 * @param    SCD_Logger           $logger              Logger.
	 */
	public function __construct( $database_manager, $cache_manager, $logger ) {
		$this->database_manager = $database_manager;
		$this->cache_manager    = $cache_manager;
		$this->logger           = $logger;

		// Get analytics table name from database manager
		global $wpdb;
		$this->analytics_table = $wpdb->prefix . 'scd_analytics';
	}

	/**
	 * Get dashboard metrics in a single optimized query.
	 *
	 * @since    1.0.0
	 * @param    string $date_range    Date range (e.g., '7days', '30days').
	 * @param    bool   $use_cache     Whether to use cache.
	 * @return   array                    Dashboard metrics.
	 */
	public function get_dashboard_metrics( string $date_range, bool $use_cache = true ): array {
		$cache_key = 'scd_dashboard_metrics_' . $date_range;

		if ( $use_cache ) {
			$cached = get_transient( $cache_key );
			if ( $cached !== false ) {
				return $cached;
			}
		}

		// Check if analytics table exists
		global $wpdb;
		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$this->analytics_table}'" ) === $this->analytics_table;

		if ( ! $table_exists ) {
			// Return empty metrics if table doesn't exist
			$this->logger->debug( 'Analytics table does not exist yet', array( 'table' => $this->analytics_table ) );
			return $this->get_empty_metrics();
		}

		try {
			// Get date ranges for current and previous periods
			$date_ranges = $this->get_date_ranges_for_period( $date_range );

			// Query aggregated analytics table (uses impressions, clicks, conversions columns)
			// Current period metrics
			$current_query = $wpdb->prepare(
				"SELECT
                    SUM(impressions) as total_impressions,
                    SUM(clicks) as total_clicks,
                    SUM(conversions) as total_conversions,
                    SUM(revenue) as total_revenue,
                    SUM(unique_customers) as unique_users,
                    COUNT(DISTINCT campaign_id) as active_campaigns
                FROM {$this->analytics_table}
                WHERE date_recorded >= %s AND date_recorded <= %s",
				$date_ranges['current_start'],
				$date_ranges['current_end']
			);

			$current_metrics = $wpdb->get_row( $current_query, ARRAY_A );

			// Previous period metrics for comparison
			$previous_query = $wpdb->prepare(
				"SELECT
                    SUM(conversions) as previous_conversions,
                    SUM(revenue) as previous_revenue,
                    SUM(clicks) as previous_clicks,
                    SUM(impressions) as previous_impressions
                FROM {$this->analytics_table}
                WHERE date_recorded >= %s AND date_recorded <= %s",
				$date_ranges['previous_start'],
				$date_ranges['previous_end']
			);

			$previous_metrics = $wpdb->get_row( $previous_query, ARRAY_A );

			// Merge results (using standard key names expected by templates)
			$metrics = array(
				'impressions'          => intval( $current_metrics['total_impressions'] ?? 0 ),
				'clicks'               => intval( $current_metrics['total_clicks'] ?? 0 ),
				'conversions'          => intval( $current_metrics['total_conversions'] ?? 0 ),
				'revenue'              => floatval( $current_metrics['total_revenue'] ?? 0 ),
				'unique_users'         => intval( $current_metrics['unique_users'] ?? 0 ),
				'active_campaigns'     => intval( $current_metrics['active_campaigns'] ?? 0 ),
				'previous_conversions' => intval( $previous_metrics['previous_conversions'] ?? 0 ),
				'previous_revenue'     => floatval( $previous_metrics['previous_revenue'] ?? 0 ),
				'previous_clicks'      => intval( $previous_metrics['previous_clicks'] ?? 0 ),
				'previous_impressions' => intval( $previous_metrics['previous_impressions'] ?? 0 ),
			);

			// Calculate average order value
			$metrics['avg_order_value'] = $metrics['conversions'] > 0
				? ( $metrics['revenue'] / $metrics['conversions'] )
				: 0;

			if ( ! $metrics ) {
				return $this->get_empty_metrics();
			}

			// Calculate derived metrics
			$metrics['conversion_rate'] = $metrics['impressions'] > 0
				? ( $metrics['conversions'] / $metrics['impressions'] * 100 )
				: 0;

			$metrics['click_through_rate'] = $metrics['impressions'] > 0
				? ( $metrics['clicks'] / $metrics['impressions'] * 100 )
				: 0;

			// Calculate changes
			$metrics['revenue_change'] = $metrics['previous_revenue'] > 0
				? ( ( $metrics['revenue'] - $metrics['previous_revenue'] ) / $metrics['previous_revenue'] * 100 )
				: 0;

			$metrics['conversions_change'] = $metrics['previous_conversions'] > 0
				? ( ( $metrics['conversions'] - $metrics['previous_conversions'] ) / $metrics['previous_conversions'] * 100 )
				: 0;

			// Calculate CTR change
			$previous_ctr = $metrics['previous_impressions'] > 0
				? ( $metrics['previous_clicks'] / $metrics['previous_impressions'] * 100 )
				: 0;

			$metrics['ctr_change'] = $previous_ctr > 0
				? ( ( $metrics['click_through_rate'] - $previous_ctr ) / $previous_ctr * 100 )
				: 0;

			// Get top performing campaigns in the same request
			$metrics['top_campaigns'] = $this->get_top_campaigns_inline( $date_ranges, 5 );

			// Cache the results
			set_transient( $cache_key, $metrics, self::CACHE_TTL );

			return $metrics;

		} catch ( Exception $e ) {
			$this->logger->error( 'Failed to get dashboard metrics', array( 'error' => $e->getMessage() ) );
			return $this->get_empty_metrics();
		}
	}

	/**
	 * Get date ranges for current and previous periods.
	 *
	 * @since    1.0.0
	 * @param    string $date_range    Date range identifier (e.g., '7days', '30days').
	 * @return   array                    Array with current_start, current_end, previous_start, previous_end.
	 */
	private function get_date_ranges_for_period( string $date_range ): array {
		$days = match ( $date_range ) {
			'7days', 'last_7_days', 'previous_7days' => 7,
			'30days', 'last_30_days', 'previous_30days' => 30,
			'90days', 'last_90_days' => 90,
			'today' => 1,
			default => 7,
		};

		$current_end   = gmdate( 'Y-m-d' );
		$current_start = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );

		$previous_end   = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );
		$previous_start = gmdate( 'Y-m-d', strtotime( '-' . ( $days * 2 ) . ' days' ) );

		return array(
			'current_start'  => $current_start,
			'current_end'    => $current_end,
			'previous_start' => $previous_start,
			'previous_end'   => $previous_end,
		);
	}

	/**
	 * Get empty metrics array.
	 *
	 * @since    1.0.0
	 * @return   array    Empty metrics.
	 */
	private function get_empty_metrics(): array {
		return array(
			'impressions'          => 0,
			'clicks'               => 0,
			'conversions'          => 0,
			'active_campaigns'     => 0,
			'unique_users'         => 0,
			'revenue'              => 0,
			'avg_order_value'      => 0,
			'previous_conversions' => 0,
			'previous_revenue'     => 0,
			'previous_clicks'      => 0,
			'previous_impressions' => 0,
			'conversion_rate'      => 0,
			'click_through_rate'   => 0,
			'revenue_change'       => 0,
			'conversions_change'   => 0,
			'ctr_change'           => 0,
			'top_campaigns'        => array(),
		);
	}

	/**
	 * Get campaign performance metrics in batch.
	 *
	 * @since    1.0.0
	 * @param    array  $campaign_ids    Array of campaign IDs.
	 * @param    string $date_range      Date range.
	 * @return   array                      Campaign metrics indexed by ID.
	 */
	public function get_batch_campaign_metrics( array $campaign_ids, string $date_range ): array {
		if ( empty( $campaign_ids ) ) {
			return array();
		}

		global $wpdb;

		$date_ranges  = $this->get_date_ranges_for_period( $date_range );
		$placeholders = implode( ',', array_fill( 0, count( $campaign_ids ), '%d' ) );

		// Get all metrics for all campaigns in one query (using aggregated columns)
		$prepare_args = array_merge( $campaign_ids, array( $date_ranges['current_start'], $date_ranges['current_end'] ) );

		$query = $wpdb->prepare(
			"
            SELECT
                campaign_id,
                SUM(impressions) as views,
                SUM(clicks) as clicks,
                SUM(conversions) as conversions,
                SUM(revenue) as revenue,
                AVG(average_order_value) as avg_order_value,
                SUM(unique_customers) as unique_users,
                COUNT(DISTINCT date_recorded) as active_days
            FROM {$this->analytics_table}
            WHERE campaign_id IN ($placeholders)
            AND date_recorded >= %s AND date_recorded <= %s
            GROUP BY campaign_id
        ",
			$prepare_args
		);

		$results = $wpdb->get_results( $query, ARRAY_A );

		// Index by campaign ID and calculate derived metrics
		$metrics = array();
		foreach ( $results as $row ) {
			$campaign_id = $row['campaign_id'];

			$row['conversion_rate'] = $row['views'] > 0
				? ( $row['conversions'] / $row['views'] * 100 )
				: 0;

			$row['click_through_rate'] = $row['views'] > 0
				? ( $row['clicks'] / $row['views'] * 100 )
				: 0;

			$row['revenue_per_conversion'] = $row['conversions'] > 0
				? $row['revenue'] / $row['conversions']
				: 0;

			$metrics[ $campaign_id ] = $row;
		}

		// Fill in zeros for campaigns with no data
		foreach ( $campaign_ids as $id ) {
			if ( ! isset( $metrics[ $id ] ) ) {
				$metrics[ $id ] = $this->get_empty_campaign_metrics();
			}
		}

		return $metrics;
	}

	/**
	 * Get time series data with efficient grouping.
	 *
	 * @since    1.0.0
	 * @param    string   $metric        Metric to retrieve.
	 * @param    string   $date_range    Date range.
	 * @param    string   $granularity   Grouping (hour, day, week, month).
	 * @param    int|null $campaign_id   Optional campaign filter.
	 * @return   array                    Time series data.
	 */
	public function get_time_series_data(
		string $metric,
		string $date_range,
		string $granularity = 'day',
		?int $campaign_id = null
	): array {
		global $wpdb;

		$date_ranges        = $this->get_date_ranges_for_period( $date_range );
		$group_by           = $this->get_group_by_clause( $granularity );
		$campaign_condition = $campaign_id ? $wpdb->prepare( 'AND campaign_id = %d', $campaign_id ) : '';

		// Build metric selection based on type (using aggregated columns)
		$metric_sql = match ( $metric ) {
			'revenue' => 'SUM(revenue)',
			'conversions' => 'SUM(conversions)',
			'views' => 'SUM(impressions)',
			'clicks' => 'SUM(clicks)',
			'users' => 'SUM(unique_customers)',
			default => 'COUNT(*)'
		};

		// Build query with proper date filtering
		if ( $campaign_id ) {
			$query = $wpdb->prepare(
				"SELECT
                    {$group_by} as period,
                    {$metric_sql} as value
                FROM {$this->analytics_table}
                WHERE date_recorded >= %s AND date_recorded <= %s
                AND campaign_id = %d
                GROUP BY period
                ORDER BY period ASC",
				$date_ranges['current_start'],
				$date_ranges['current_end'],
				$campaign_id
			);
		} else {
			$query = $wpdb->prepare(
				"SELECT
                    {$group_by} as period,
                    {$metric_sql} as value
                FROM {$this->analytics_table}
                WHERE date_recorded >= %s AND date_recorded <= %s
                GROUP BY period
                ORDER BY period ASC",
				$date_ranges['current_start'],
				$date_ranges['current_end']
			);
		}

		$results = $wpdb->get_results( $query );

		// Format for chart display
		$labels = array();
		$values = array();

		foreach ( $results as $row ) {
			$labels[] = $this->format_period_label( $row->period, $granularity );
			$values[] = floatval( $row->value );
		}

		return array(
			'labels' => $labels,
			'values' => $values,
		);
	}

	/**
	 * Get GROUP BY clause based on granularity.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $granularity    Granularity level.
	 * @return   string                    SQL GROUP BY clause.
	 */
	private function get_group_by_clause( string $granularity ): string {
		return match ( $granularity ) {
			'hour' => "DATE_FORMAT(date_recorded, '%Y-%m-%d %H:00:00')",
			'day' => 'date_recorded',
			'week' => "DATE_FORMAT(date_recorded, '%Y-%u')",
			'month' => "DATE_FORMAT(date_recorded, '%Y-%m')",
			default => 'date_recorded'
		};
	}

	/**
	 * Format period label for display.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $period         Period value.
	 * @param    string $granularity    Granularity level.
	 * @return   string                    Formatted label.
	 */
	private function format_period_label( string $period, string $granularity ): string {
		return match ( $granularity ) {
			'hour' => date( 'M j, g:i A', strtotime( $period ) ),
			'day' => date( 'M j', strtotime( $period ) ),
			'week' => sprintf( __( 'Week %s', 'smart-cycle-discounts' ), $period ),
			'month' => date( 'M Y', strtotime( $period . '-01' ) ),
			default => $period
		};
	}

	/**
	 * Get top campaigns inline for dashboard.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $date_ranges    Date ranges array.
	 * @param    int   $limit          Number of campaigns.
	 * @return   array                    Top campaigns data.
	 */
	private function get_top_campaigns_inline( array $date_ranges, int $limit ): array {
		global $wpdb;

		$query = $wpdb->prepare(
			"
            SELECT
                campaign_id,
                SUM(conversions) as conversions,
                SUM(revenue) as revenue
            FROM {$this->analytics_table}
            WHERE date_recorded >= %s AND date_recorded <= %s
            AND campaign_id IS NOT NULL
            GROUP BY campaign_id
            ORDER BY revenue DESC
            LIMIT %d
        ",
			$date_ranges['current_start'],
			$date_ranges['current_end'],
			$limit
		);

		return $wpdb->get_results( $query, ARRAY_A );
	}

	/**
	 * Get empty campaign metrics structure.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   array    Empty metrics array.
	 */
	private function get_empty_campaign_metrics(): array {
		return array(
			'views'                  => 0,
			'clicks'                 => 0,
			'conversions'            => 0,
			'revenue'                => 0,
			'avg_order_value'        => 0,
			'unique_users'           => 0,
			'active_days'            => 0,
			'conversion_rate'        => 0,
			'click_through_rate'     => 0,
			'revenue_per_conversion' => 0,
		);
	}

	/**
	 * Clear analytics cache.
	 *
	 * @since    1.0.0
	 * @param    string|null $date_range    Optional specific date range.
	 * @return   void
	 */
	public function clear_cache( ?string $date_range = null ): void {
		if ( $date_range ) {
			delete_transient( 'scd_dashboard_metrics_' . $date_range );
		} else {
			global $wpdb;
			$wpdb->query(
				"DELETE FROM {$wpdb->options} 
                WHERE option_name LIKE '_transient_scd_dashboard_metrics_%'"
			);
		}
	}
}
