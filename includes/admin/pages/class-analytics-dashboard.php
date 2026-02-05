<?php
/**
 * @fs_premium_only
 *
 * Analytics Dashboard Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/pages/class-analytics-dashboard.php
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
 * Analytics Dashboard Class
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/analytics
 * @author     Webstepper <contact@webstepper.io>
 */
class WSSCD_Analytics_Dashboard {

	/**
	 * Database manager instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Database_Manager    $database_manager    Database manager.
	 */
	private $database_manager;

	/**
	 * Cache manager instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Cache_Manager    $cache_manager    Cache manager.
	 */
	private $cache_manager;

	/**
	 * Logger instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Logger    $logger    Logger.
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
	 * @param    WSSCD_Database_Manager $database_manager    Database manager.
	 * @param    WSSCD_Cache_Manager    $cache_manager       Cache manager.
	 * @param    WSSCD_Logger           $logger              Logger.
	 */
	public function __construct( $database_manager, $cache_manager, $logger ) {
		$this->database_manager = $database_manager;
		$this->cache_manager    = $cache_manager;
		$this->logger           = $logger;

		global $wpdb;
		$this->analytics_table = $wpdb->prefix . 'wsscd_analytics';
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
		$cache_key = $this->cache_manager->analytics_key( 'dashboard_metrics_' . $date_range );

		if ( $use_cache ) {
			$cached = $this->cache_manager->get( $cache_key );
			if ( null !== $cached ) {
				return $cached;
			}
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching , PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- SHOW TABLES has no WP abstraction; ephemeral check.
		$table_exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $this->analytics_table )
		) === $this->analytics_table;

		if ( ! $table_exists ) {
			$this->logger->debug( 'Analytics table does not exist yet', array( 'table' => $this->analytics_table ) );
			return $this->get_empty_metrics();
		}

		try {
			$date_ranges = $this->get_date_ranges_for_period( $date_range );

			// Query aggregated analytics table (uses impressions, clicks, conversions columns).
			// Current period metrics.
			// SECURITY: Use %i placeholder for table identifier (WordPress 6.2+).
			$current_query = $wpdb->prepare(
				'SELECT
					SUM(impressions) as total_impressions,
					SUM(clicks) as total_clicks,
					SUM(conversions) as total_conversions,
					SUM(revenue) as total_revenue,
					SUM(unique_customers) as unique_users,
					COUNT(DISTINCT campaign_id) as active_campaigns
				FROM %i
				WHERE date_recorded >= %s AND date_recorded <= %s',
				$this->analytics_table,
				$date_ranges['current_start'],
				$date_ranges['current_end']
			);

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Query on plugin's custom analytics table.
			$current_metrics = $wpdb->get_row( $current_query, ARRAY_A );

			// Previous period metrics for comparison.
			// SECURITY: Use %i placeholder for table identifier (WordPress 6.2+).
			$previous_query = $wpdb->prepare(
				'SELECT
					SUM(conversions) as previous_conversions,
					SUM(revenue) as previous_revenue,
					SUM(clicks) as previous_clicks,
					SUM(impressions) as previous_impressions
				FROM %i
				WHERE date_recorded >= %s AND date_recorded <= %s',
				$this->analytics_table,
				$date_ranges['previous_start'],
				$date_ranges['previous_end']
			);

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Query on plugin's custom analytics table.
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

			$metrics['avg_order_value'] = $metrics['conversions'] > 0
				? ( $metrics['revenue'] / $metrics['conversions'] )
				: 0;

			if ( ! $metrics ) {
				return $this->get_empty_metrics();
			}

			$metrics['conversion_rate'] = $metrics['impressions'] > 0
				? ( $metrics['conversions'] / $metrics['impressions'] * 100 )
				: 0;

			$metrics['click_through_rate'] = $metrics['impressions'] > 0
				? ( $metrics['clicks'] / $metrics['impressions'] * 100 )
				: 0;

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

			$metrics['top_campaigns'] = $this->get_top_campaigns_inline( $date_ranges, 5 );

			$this->cache_manager->set( $cache_key, $metrics, self::CACHE_TTL );

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
		switch ( $date_range ) {
			case '7days':
			case 'last_7_days':
			case 'previous_7days':
				$days = 7;
				break;
			case '30days':
			case 'last_30_days':
			case 'previous_30days':
				$days = 30;
				break;
			case '90days':
			case 'last_90_days':
				$days = 90;
				break;
			case 'today':
				$days = 1;
				break;
			default:
				$days = 7;
		}

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

		// SECURITY: Use %i placeholder for table identifier (WordPress 6.2+).
		$prepare_args = array_merge( array( $this->analytics_table ), $campaign_ids, array( $date_ranges['current_start'], $date_ranges['current_end'] ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Dynamic placeholders generated via array_fill match prepare_args count.
		$query = $wpdb->prepare(
			'SELECT
				campaign_id,
				SUM(impressions) as views,
				SUM(clicks) as clicks,
				SUM(conversions) as conversions,
				SUM(revenue) as revenue,
				AVG(average_order_value) as avg_order_value,
				SUM(unique_customers) as unique_users,
				COUNT(DISTINCT date_recorded) as active_days
			FROM %i
			WHERE campaign_id IN (' . $placeholders . ')
			AND date_recorded >= %s AND date_recorded <= %s
			GROUP BY campaign_id',
			$prepare_args
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query on plugin's custom analytics table; results cached via WSSCD_Cache_Manager.
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

		switch ( $metric ) {
			case 'revenue':
				$metric_sql = 'SUM(revenue)';
				break;
			case 'conversions':
				$metric_sql = 'SUM(conversions)';
				break;
			case 'views':
				$metric_sql = 'SUM(impressions)';
				break;
			case 'clicks':
				$metric_sql = 'SUM(clicks)';
				break;
			case 'users':
				$metric_sql = 'SUM(unique_customers)';
				break;
			default:
				$metric_sql = 'COUNT(*)';
		}

		// SECURITY: Use %i placeholder for table identifier (WordPress 6.2+).
		// $group_by and $metric_sql are whitelisted SQL expressions from switch statements.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- group_by and metric_sql are whitelisted values from switch statements.
		if ( $campaign_id ) {
			$query = $wpdb->prepare(
				'SELECT
					' . $group_by . ' as period,
					' . $metric_sql . ' as value
				FROM %i
				WHERE date_recorded >= %s AND date_recorded <= %s
				AND campaign_id = %d
				GROUP BY period
				ORDER BY period ASC',
				$this->analytics_table,
				$date_ranges['current_start'],
				$date_ranges['current_end'],
				$campaign_id
			);
		} else {
			$query = $wpdb->prepare(
				'SELECT
					' . $group_by . ' as period,
					' . $metric_sql . ' as value
				FROM %i
				WHERE date_recorded >= %s AND date_recorded <= %s
				GROUP BY period
				ORDER BY period ASC',
				$this->analytics_table,
				$date_ranges['current_start'],
				$date_ranges['current_end']
			);
		}
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query on plugin's custom analytics table; results cached via WSSCD_Cache_Manager.
		$results = $wpdb->get_results( $query );

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
		switch ( $granularity ) {
			case 'hour':
				return "DATE_FORMAT(date_recorded, '%Y-%m-%d %H:00:00')";
			case 'day':
				return 'date_recorded';
			case 'week':
				return "DATE_FORMAT(date_recorded, '%Y-%u')";
			case 'month':
				return "DATE_FORMAT(date_recorded, '%Y-%m')";
			default:
				return 'date_recorded';
		}
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
		switch ( $granularity ) {
			case 'hour':
				return wp_date( 'M j, g:i A', strtotime( $period ) );
			case 'day':
				return wp_date( 'M j', strtotime( $period ) );
			case 'week':
				/* translators: %s: week number or date range */
				return sprintf( __( 'Week %s', 'smart-cycle-discounts' ), $period );
			case 'month':
				return wp_date( 'M Y', strtotime( $period . '-01' ) );
			default:
				return $period;
		}
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

		// SECURITY: Use %i placeholder for table identifier (WordPress 6.2+).
		$query = $wpdb->prepare(
			'SELECT
				campaign_id,
				SUM(conversions) as conversions,
				SUM(revenue) as revenue
			FROM %i
			WHERE date_recorded >= %s AND date_recorded <= %s
			AND campaign_id IS NOT NULL
			GROUP BY campaign_id
			ORDER BY revenue DESC
			LIMIT %d',
			$this->analytics_table,
			$date_ranges['current_start'],
			$date_ranges['current_end'],
			$limit
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Query on plugin's custom analytics table.
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
			$cache_key = $this->cache_manager->analytics_key( 'dashboard_metrics_' . $date_range );
			$this->cache_manager->delete( $cache_key );
		} else {
			// Clear entire analytics group
			$this->cache_manager->delete_group( 'analytics' );
		}
	}
}
