<?php
/**
 * Analytics Repository Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/database/repositories/class-analytics-repository.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


require_once WSSCD_INCLUDES_DIR . 'database/repositories/class-base-repository.php';

/**
 * Analytics Repository Class
 *
 * Handles database operations for analytics data using the base repository pattern.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/database
 * @author     Webstepper <contact@webstepper.io>
 */
class WSSCD_Analytics_Repository extends WSSCD_Base_Repository {

	/**
	 * Initialize the repository.
	 *
	 * @since    1.0.0
	 * @param    WSSCD_Database_Manager $database_manager    Database manager (for DI compatibility, not used).
	 */
	public function __construct( WSSCD_Database_Manager $database_manager ) {
		global $wpdb;
		// Note: database_manager parameter is kept for DI container compatibility
		// but not used. Base repository uses global $wpdb directly.
		$this->table_name  = $wpdb->prefix . 'wsscd_analytics';
		$this->primary_key = 'id';
		$this->date_fields = array( 'event_timestamp', 'created_at', 'updated_at' );
		$this->json_fields = array( 'event_data', 'metadata', 'additional_data' );
	}

	/**
	 * Find analytics by campaign ID.
	 *
	 * @since    1.0.0
	 * @param    int    $campaign_id    Campaign ID.
	 * @param    string $event_type     Optional event type filter.
	 * @return   array                    Array of analytics records.
	 */
	public function find_by_campaign( $campaign_id, $event_type = '' ) {
		$args = array(
			'campaign_id' => $campaign_id,
		);

		if ( ! empty( $event_type ) ) {
			$args['event_type'] = $event_type;
		}

		return $this->find_all( $args );
	}

	/**
	 * Find analytics by product ID.
	 *
	 * @since    1.0.0
	 * @param    int    $product_id    Product ID.
	 * @param    string $event_type    Optional event type filter.
	 * @return   array                   Array of analytics records.
	 */
	public function find_by_product( $product_id, $event_type = '' ) {
		$args = array(
			'product_id' => $product_id,
		);

		if ( ! empty( $event_type ) ) {
			$args['event_type'] = $event_type;
		}

		return $this->find_all( $args );
	}

	/**
	 * Find analytics by event type.
	 *
	 * @since    1.0.0
	 * @param    string $event_type    Event type.
	 * @param    array  $filters       Additional filters.
	 * @return   array                    Array of analytics records.
	 */
	public function find_by_event_type( $event_type, $filters = array() ) {
		$args = array_merge(
			$filters,
			array(
				'event_type' => $event_type,
			)
		);

		return $this->find_all( $args );
	}

	/**
	 * Find analytics by date range.
	 *
	 * @since    1.0.0
	 * @param    string $start_date    Start date.
	 * @param    string $end_date      End date.
	 * @param    array  $filters       Additional filters.
	 * @return   array                    Array of analytics records.
	 */
	public function find_by_date_range( $start_date, $end_date, $filters = array() ) {
		$args = array_merge(
			$filters,
			array(
				'date_from' => $start_date,
				'date_to'   => $end_date,
			)
		);

		return $this->find_all( $args );
	}

	/**
	 * Get campaign performance statistics.
	 *
	 * Queries aggregated metrics from the analytics table.
	 * The analytics table stores pre-aggregated data by campaign/date/hour.
	 *
	 * @since    1.0.0
	 * @param    int    $campaign_id    Campaign ID.
	 * @param    string $start_date     Optional start date (Y-m-d format).
	 * @param    string $end_date       Optional end date (Y-m-d format).
	 * @return   array                     Performance statistics.
	 */
	public function get_campaign_performance( $campaign_id, $start_date = '', $end_date = '' ) {
		global $wpdb;

		$where_conditions = array( 'campaign_id = %d' );
		$where_values     = array( $campaign_id );

		if ( ! empty( $start_date ) ) {
			$where_conditions[] = 'date_recorded >= %s';
			$where_values[]     = $start_date;
		}

		if ( ! empty( $end_date ) ) {
			$where_conditions[] = 'date_recorded <= %s';
			$where_values[]     = $end_date;
		}

		$where_sql = implode( ' AND ', $where_conditions );

		// Query aggregated metrics directly from columns.
		// SECURITY: Use %i placeholder for table identifier (WordPress 6.2+).
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Dynamic WHERE clause with placeholders in $where_sql match $where_values count.
		$sql = $wpdb->prepare(
			'SELECT
				SUM(impressions) as total_impressions,
				SUM(clicks) as total_clicks,
				SUM(conversions) as total_conversions,
				SUM(revenue) as total_revenue,
				SUM(discount_given) as total_discount,
				SUM(unique_customers) as total_customers
			 FROM %i
			 WHERE ' . $where_sql,
			array_merge( array( $this->table_name ), $where_values )
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query is prepared above with $wpdb->prepare(). $where_sql contains prepared conditions.
		$result = $wpdb->get_row( $sql, ARRAY_A );

		if ( ! $result ) {
			return array(
				'impressions'        => 0,
				'clicks'             => 0,
				'conversions'        => 0,
				'revenue'            => 0.0,
				'discount_given'     => 0.0,
				'avg_order_value'    => 0.0,
				'ctr'                => 0.0,
				'conversion_rate'    => 0.0,
				'roi'                => 0.0,
			);
		}

		$impressions  = (int) ( $result['total_impressions'] ?? 0 );
		$clicks       = (int) ( $result['total_clicks'] ?? 0 );
		$conversions  = (int) ( $result['total_conversions'] ?? 0 );
		$revenue      = (float) ( $result['total_revenue'] ?? 0.0 );
		$discount     = (float) ( $result['total_discount'] ?? 0.0 );

		// Calculate derived metrics
		$ctr             = $impressions > 0 ? ( $clicks / $impressions ) * 100 : 0.0;
		$conversion_rate = $clicks > 0 ? ( $conversions / $clicks ) * 100 : 0.0;
		$avg_order       = $conversions > 0 ? $revenue / $conversions : 0.0;
		$roi             = $discount > 0 ? ( ( $revenue - $discount ) / $discount ) * 100 : 0.0;

		return array(
			'impressions'     => $impressions,
			'clicks'          => $clicks,
			'conversions'     => $conversions,
			'revenue'         => round( $revenue, 2 ),
			'discount_given'  => round( $discount, 2 ),
			'avg_order_value' => round( $avg_order, 2 ),
			'ctr'             => round( $ctr, 2 ),
			'conversion_rate' => round( $conversion_rate, 2 ),
			'roi'             => round( $roi, 2 ),
		);
	}

	/**
	 * Get campaign performance with pagination support.
	 *
	 * @since    1.0.0
	 * @param    int   $campaign_id    Campaign ID.
	 * @param    array $date_range     Date range array.
	 * @param    int   $page           Page number (1-based).
	 * @param    int   $per_page       Records per page.
	 * @return   array                    Performance data with pagination info.
	 */
	public function get_campaign_performance_paginated( $campaign_id, $date_range = array(), $page = 1, $per_page = 100 ) {
		global $wpdb;

		$where_conditions = array( '1=1' );
		$where_values     = array();

		if ( $campaign_id > 0 ) {
			$where_conditions[] = 'campaign_id = %d';
			$where_values[]     = $campaign_id;
		}

		if ( ! empty( $date_range['start'] ) ) {
			$where_conditions[] = 'event_timestamp >= %s';
			$where_values[]     = $date_range['start'] . ' 00:00:00';
		}

		if ( ! empty( $date_range['end'] ) ) {
			$where_conditions[] = 'event_timestamp <= %s';
			$where_values[]     = $date_range['end'] . ' 23:59:59';
		}

		$where_sql = implode( ' AND ', $where_conditions );

		$offset = ( $page - 1 ) * $per_page;

		// SECURITY: Use %i placeholder for table identifier (WordPress 6.2+).
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Dynamic WHERE clause built from validated column names. Placeholder count matches $where_values array.
		$count_sql = $wpdb->prepare(
			'SELECT COUNT(DISTINCT event_type) FROM %i WHERE ' . $where_sql,
			array_merge( array( $this->table_name ), $where_values )
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query is prepared above.
		$total_count = (int) $wpdb->get_var( $count_sql );

		// SECURITY: Use %i placeholder for table identifier (WordPress 6.2+).
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Dynamic WHERE clause built from validated column names. Placeholder count matches $where_values array.
		$sql = $wpdb->prepare(
			'SELECT
				event_type,
				COUNT(*) as count,
				SUM(CASE WHEN event_type = \'discount_applied\' THEN CAST(JSON_EXTRACT(event_data, \'$.savings_amount\') AS DECIMAL(10,2)) ELSE 0 END) as total_savings,
				AVG(CASE WHEN event_type = \'discount_applied\' THEN CAST(JSON_EXTRACT(event_data, \'$.discount_value\') AS DECIMAL(5,2)) ELSE NULL END) as avg_discount_value
			 FROM %i
			 WHERE ' . $where_sql . '
			 GROUP BY event_type
			 LIMIT %d OFFSET %d',
			array_merge( array( $this->table_name ), $where_values, array( $per_page, $offset ) )
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query is prepared above.
		$results = $wpdb->get_results( $sql, ARRAY_A );

		$performance = array(
			'impressions'        => 0,
			'clicks'             => 0,
			'conversions'        => 0,
			'revenue'            => 0.0,
			'total_savings'      => 0.0,
			'avg_discount_value' => 0.0,
			'click_through_rate' => 0.0,
			'conversion_rate'    => 0.0,
		);

		foreach ( $results as $row ) {
			$event_type = $row['event_type'];
			$count      = (int) $row['count'];

			switch ( $event_type ) {
				case 'campaign_displayed':
					$performance['impressions'] = $count;
					break;
				case 'discount_applied':
					$performance['clicks']             = $count;
					$performance['total_savings']      = (float) $row['total_savings'];
					$performance['avg_discount_value'] = (float) $row['avg_discount_value'];
					break;
				case 'purchase_completed':
					$performance['conversions'] = $count;
					break;
			}
		}

		if ( $performance['impressions'] > 0 ) {
			$performance['click_through_rate'] = ( $performance['clicks'] / $performance['impressions'] ) * 100;
		}

		if ( $performance['clicks'] > 0 ) {
			$performance['conversion_rate'] = ( $performance['conversions'] / $performance['clicks'] ) * 100;
		}

		$total_pages = ceil( $total_count / $per_page );

		return array(
			'data'       => $performance,
			'pagination' => array(
				'current_page' => $page,
				'per_page'     => $per_page,
				'total_items'  => $total_count,
				'total_pages'  => $total_pages,
			),
		);
	}

	/**
	 * Get event counts by type.
	 *
	 * @since    1.0.0
	 * @param    array $filters    Optional filters.
	 * @return   array                Event counts by type.
	 */
	public function get_event_counts( $filters = array() ) {
		global $wpdb;

		$where_conditions = array();
		$where_values     = array();

		if ( ! empty( $filters['campaign_id'] ) ) {
			$where_conditions[] = 'campaign_id = %d';
			$where_values[]     = $filters['campaign_id'];
		}

		if ( ! empty( $filters['product_id'] ) ) {
			$where_conditions[] = 'product_id = %d';
			$where_values[]     = $filters['product_id'];
		}

		if ( ! empty( $filters['date_from'] ) ) {
			$where_conditions[] = 'event_timestamp >= %s';
			$where_values[]     = $filters['date_from'];
		}

		if ( ! empty( $filters['date_to'] ) ) {
			$where_conditions[] = 'event_timestamp <= %s';
			$where_values[]     = $filters['date_to'];
		}

		// SECURITY: Always use $wpdb->prepare() to satisfy WordPress.org review requirements.
		// Build WHERE clause - can be empty when no filters.
		if ( ! empty( $where_conditions ) ) {
			$where_sql = 'WHERE ' . implode( ' AND ', $where_conditions );
		} else {
			$where_sql = '';
		}

		// SECURITY: Use %i placeholder for table identifier (WordPress 6.2+).
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic WHERE clause built from validated column names.
		if ( ! empty( $where_values ) ) {
			$sql = $wpdb->prepare(
				'SELECT
					event_type,
					COUNT(*) as count
				FROM %i
				' . $where_sql . '
				GROUP BY event_type
				ORDER BY count DESC',
				array_merge( array( $this->table_name ), $where_values )
			);
		} else {
			// No where values - just prepare with table name.
			$sql = $wpdb->prepare(
				'SELECT
					event_type,
					COUNT(*) as count
				FROM %i
				GROUP BY event_type
				ORDER BY count DESC',
				$this->table_name
			);
		}
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query is prepared above with $wpdb->prepare().
		$results = $wpdb->get_results( $sql, ARRAY_A );

		$counts = array();
		foreach ( $results as $row ) {
			$counts[ $row['event_type'] ] = intval( $row['count'] );
		}

		return $counts;
	}

	/**
	 * Record analytics event.
	 *
	 * @since    1.0.0
	 * @param    array $event_data    Event data.
	 * @return   int|false               Insert ID or false on failure.
	 */
	public function record_event( array $event_data ) {
		$required_fields = array( 'event_type', 'campaign_id' );

		foreach ( $required_fields as $field ) {
			if ( empty( $event_data[ $field ] ) ) {
				return false;
			}
		}

		$data = array(
			'event_type'      => $event_data['event_type'],
			'campaign_id'     => $event_data['campaign_id'],
			'product_id'      => isset( $event_data['product_id'] ) ? $event_data['product_id'] : null,
			'user_id'         => isset( $event_data['user_id'] ) ? $event_data['user_id'] : get_current_user_id(),
			'session_id'      => isset( $event_data['session_id'] ) ? $event_data['session_id'] : '',
			'event_timestamp' => current_time( 'mysql' ),
			'event_data'      => isset( $event_data['data'] ) ? $event_data['data'] : array(),
			'metadata'        => isset( $event_data['metadata'] ) ? $event_data['metadata'] : array(),
		);

		return $this->create( $data );
	}

	/**
	 * Cleanup old analytics data.
	 *
	 * @since    1.0.0
	 * @param    int $days_old    Number of days to keep.
	 * @return   int                  Number of deleted records.
	 */
	public function cleanup_old_data( $days_old = 90 ) {
		global $wpdb;

		$cutoff_date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days_old} days" ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching , PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Analytics cleanup; no caching needed for delete operation.
		$result = $wpdb->delete(
			$this->table_name,
			array(
				'event_timestamp' => array( '<', $cutoff_date ),
			),
			array( '%s' )
		);

		return false !== $result ? $result : 0;
	}

	/**
	 * Apply custom WHERE conditions for analytics queries.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @param    WSSCD_Query_Builder $query_builder    Query builder instance.
	 * @param    array             $args             Query arguments.
	 * @return   void
	 */
	protected function apply_custom_where_conditions( $query_builder, $args ) {
		// Campaign ID filter
		if ( ! empty( $args['campaign_id'] ) ) {
			$query_builder->where( 'campaign_id', '=', $args['campaign_id'] );
		}

		// Product ID filter
		if ( ! empty( $args['product_id'] ) ) {
			$query_builder->where( 'product_id', '=', $args['product_id'] );
		}

		// User ID filter
		if ( ! empty( $args['user_id'] ) ) {
			$query_builder->where( 'user_id', '=', $args['user_id'] );
		}

		// Event type filter
		if ( ! empty( $args['event_type'] ) ) {
			$query_builder->where( 'event_type', '=', $args['event_type'] );
		}

		// Session ID filter
		if ( ! empty( $args['session_id'] ) ) {
			$query_builder->where( 'session_id', '=', $args['session_id'] );
		}

		// Date range filters
		if ( ! empty( $args['date_from'] ) ) {
			$query_builder->where( 'event_timestamp', '>=', $args['date_from'] );
		}

		if ( ! empty( $args['date_to'] ) ) {
			$query_builder->where( 'event_timestamp', '<=', $args['date_to'] );
		}
	}

	/**
	 * Prepare data for database insertion/update.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @param    array $data    Data to prepare.
	 * @return   array             Prepared data.
	 */
	protected function prepare_data_for_database( array $data ) {
		$prepared = array();

		// Direct mappings
		$direct_fields = array(
			'event_type',
			'campaign_id',
			'product_id',
			'user_id',
			'session_id',
			'event_timestamp',
		);

		foreach ( $direct_fields as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$prepared[ $field ] = $data[ $field ];
			}
		}

		// Encode JSON fields using base class helper
		$prepared = $this->encode_json_fields( $prepared );

		return $prepared;
	}

	/**
	 * Prepare item output from database.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @param    array $data    Raw database data.
	 * @return   array             Prepared data.
	 */
	protected function prepare_item_output( array $data ) {
		$numeric_fields = array(
			'id',
			'campaign_id',
			'product_id',
			'user_id',
		);

		return $this->convert_to_int( $data, $numeric_fields );
	}

	/**
	 * Get entity name for error messages.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @return   string    Entity name.
	 */
	protected function get_entity_name() {
		return __( 'analytics record', 'smart-cycle-discounts' );
	}

	/**
	 * Get table name.
	 *
	 * @since    1.0.0
	 * @return   string    Table name.
	 */
	public function get_table_name() {
		return $this->table_name;
	}

	/**
	 * Get product-level performance analytics.
	 *
	 * @since    1.0.0
	 * @param    int    $campaign_id    Campaign ID.
	 * @param    string $start_date     Start date (optional).
	 * @param    string $end_date       End date (optional).
	 * @param    int    $limit          Limit results (default 10).
	 * @return   array                   Product performance data.
	 */
	public function get_product_performance( $campaign_id, $start_date = '', $end_date = '', $limit = 10 ) {
		global $wpdb;
		$product_analytics_table = $wpdb->prefix . 'wsscd_product_analytics';

		$where_sql    = array();
		$where_values = array();

		// Only filter by campaign if a specific campaign is requested (non-zero)
		if ( $campaign_id > 0 ) {
			$where_sql[]    = 'campaign_id = %d';
			$where_values[] = $campaign_id;
		}

		if ( ! empty( $start_date ) ) {
			$where_sql[]    = 'date_recorded >= %s';
			$where_values[] = $start_date;
		}

		if ( ! empty( $end_date ) ) {
			$where_sql[]    = 'date_recorded <= %s';
			$where_values[] = $end_date;
		}

		$where_clause = ! empty( $where_sql ) ? 'WHERE ' . implode( ' AND ', $where_sql ) : '';

		// SECURITY: Use %i placeholder for table identifier (WordPress 6.2+).
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic WHERE clause built from validated column names. Placeholder count matches $where_values array.
		$sql = $wpdb->prepare(
			'SELECT
				product_id,
				SUM(impressions) as total_impressions,
				SUM(clicks) as total_clicks,
				SUM(conversions) as total_conversions,
				SUM(revenue) as total_revenue,
				SUM(discount_given) as total_discount,
				SUM(product_cost) as total_cost,
				SUM(profit) as total_profit,
				SUM(quantity_sold) as total_quantity,
				SUM(unique_customers) as total_customers,
				CASE
					WHEN SUM(impressions) > 0 THEN (SUM(clicks) / SUM(impressions)) * 100
					ELSE 0
				END as ctr,
				CASE
					WHEN SUM(clicks) > 0 THEN (SUM(conversions) / SUM(clicks)) * 100
					ELSE 0
				END as conversion_rate,
				CASE
					WHEN SUM(product_cost) > 0 THEN ((SUM(profit) / SUM(product_cost)) * 100)
					ELSE 0
				END as profit_margin_pct
			FROM %i
			' . $where_clause . '
			GROUP BY product_id
			ORDER BY total_revenue DESC
			LIMIT %d',
			array_merge( array( $product_analytics_table ), $where_values, array( $limit ) )
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query is prepared above.
		$results = $wpdb->get_results( $sql, ARRAY_A );

		// Format results with product names
		$formatted = array();
		foreach ( $results as $row ) {
			$product_id = (int) $row['product_id'];

			// Get product name from WooCommerce
			$product_name = 'Unknown Product';
			if ( function_exists( 'wc_get_product' ) ) {
				$product = wc_get_product( $product_id );
				if ( $product ) {
					$product_name = $product->get_name();
				}
			}

			// Calculate average discount percentage
			$total_revenue       = (float) $row['total_revenue'];
			$total_discount      = (float) $row['total_discount'];
			$avg_discount_percent = $total_revenue > 0 ? ( $total_discount / $total_revenue ) * 100 : 0;

			$formatted[] = array(
				'product_id'           => $product_id,
				'name'                 => $product_name,
				'impressions'          => (int) $row['total_impressions'],
				'clicks'               => (int) $row['total_clicks'],
				'conversions'          => (int) $row['total_conversions'],
				'order_count'          => (int) $row['total_conversions'], // Alias for JavaScript compatibility
				'revenue'              => round( $total_revenue, 2 ),
				'discount_given'       => round( $total_discount, 2 ),
				'avg_discount_percent' => round( $avg_discount_percent, 2 ),
				'cost'                 => round( (float) $row['total_cost'], 2 ),
				'profit'               => round( (float) $row['total_profit'], 2 ),
				'quantity_sold'        => (int) $row['total_quantity'],
				'unique_customers'     => (int) $row['total_customers'],
				'ctr'                  => round( (float) $row['ctr'], 2 ),
				'conversion_rate'      => round( (float) $row['conversion_rate'], 2 ),
				'profit_margin_pct'    => round( (float) $row['profit_margin_pct'], 2 ),
				'trend'                => 'neutral', // TODO: Calculate actual trend based on historical data
			);
		}

		return $formatted;
	}

	/**
	 * Get customer retention rate for a campaign.
	 *
	 * @since    1.0.0
	 * @param    int $campaign_id    Campaign ID.
	 * @param    int $days           Days to check for repeat purchase (default 30).
	 * @return   array                Retention metrics.
	 */
	public function get_customer_retention_rate( $campaign_id, $days = 30 ) {
		global $wpdb;
		$customer_usage_table = $wpdb->prefix . 'wsscd_customer_usage';
		$cutoff_date          = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		// Get customers who used the campaign.
		// SECURITY: Use %i placeholder for table identifier (WordPress 6.2+).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Required for aggregate query on customer usage.
		$total_customers = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(DISTINCT customer_id)
				FROM %i
				WHERE campaign_id = %d
				AND customer_id > 0
				AND first_used_at >= %s',
				$customer_usage_table,
				$campaign_id,
				$cutoff_date
			)
		);

		// Get customers who made repeat purchases.
		// SECURITY: Use %i placeholder for table identifier (WordPress 6.2+).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Required for aggregate query on customer usage.
		$repeat_customers = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(DISTINCT customer_id)
				FROM %i
				WHERE campaign_id = %d
				AND customer_id > 0
				AND usage_count > 1
				AND first_used_at >= %s',
				$customer_usage_table,
				$campaign_id,
				$cutoff_date
			)
		);

		$retention_rate = $total_customers > 0 ? ( $repeat_customers / $total_customers ) * 100 : 0;

		return array(
			'total_customers'  => (int) $total_customers,
			'repeat_customers' => (int) $repeat_customers,
			'retention_rate'   => round( $retention_rate, 2 ),
			'period_days'      => $days,
		);
	}

	/**
	 * Get baseline comparison for a campaign.
	 *
	 * Compares performance during campaign vs pre-campaign baseline period.
	 *
	 * @since    1.0.0
	 * @param    int $campaign_id    Campaign ID.
	 * @return   array                Baseline comparison data.
	 */
	public function get_baseline_comparison( $campaign_id ) {
		global $wpdb;
		$campaigns_table = $wpdb->prefix . 'wsscd_campaigns';

		// Get campaign dates.
		// SECURITY: Use %i placeholder for table identifier (WordPress 6.2+).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Required for campaign date lookup.
		$campaign = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT
					starts_at,
					ends_at,
					baseline_revenue,
					baseline_orders,
					baseline_customers
				FROM %i
				WHERE id = %d',
				$campaigns_table,
				$campaign_id
			),
			ARRAY_A
		);

		if ( ! $campaign || ! $campaign['starts_at'] ) {
			return array(
				'has_baseline'  => false,
				'message'       => __( 'Campaign dates not set', 'smart-cycle-discounts' ),
			);
		}

		$campaign_start = $campaign['starts_at'];
		$campaign_end   = $campaign['ends_at'] ? $campaign['ends_at'] : gmdate( 'Y-m-d H:i:s' );

		// Calculate campaign duration in days
		$duration_days = max( 1, (int) ( ( strtotime( $campaign_end ) - strtotime( $campaign_start ) ) / DAY_IN_SECONDS ) );

		// Get baseline period (same duration before campaign start)
		$baseline_end   = gmdate( 'Y-m-d H:i:s', strtotime( $campaign_start ) - 1 );
		$baseline_start = gmdate( 'Y-m-d H:i:s', strtotime( $campaign_start ) - ( $duration_days * DAY_IN_SECONDS ) );

		// Get campaign performance
		$campaign_perf = $this->get_campaign_performance( $campaign_id, $campaign_start, $campaign_end );

		// Get baseline from stored values or calculate
		$baseline_revenue   = (float) ( $campaign['baseline_revenue'] ?? 0 );
		$baseline_orders    = (int) ( $campaign['baseline_orders'] ?? 0 );
		$baseline_customers = (int) ( $campaign['baseline_customers'] ?? 0 );

		// Calculate incremental impact
		$incremental_revenue   = $campaign_perf['revenue'] - $baseline_revenue;
		$incremental_orders    = $campaign_perf['conversions'] - $baseline_orders;
		$incremental_customers = $campaign_perf['unique_customers'] - $baseline_customers;

		// Calculate lift percentages
		$revenue_lift   = $baseline_revenue > 0 ? ( ( $incremental_revenue / $baseline_revenue ) * 100 ) : 0;
		$orders_lift    = $baseline_orders > 0 ? ( ( $incremental_orders / $baseline_orders ) * 100 ) : 0;
		$customers_lift = $baseline_customers > 0 ? ( ( $incremental_customers / $baseline_customers ) * 100 ) : 0;

		return array(
			'has_baseline'          => true,
			'baseline_period'       => array(
				'start' => $baseline_start,
				'end'   => $baseline_end,
				'days'  => $duration_days,
			),
			'campaign_period'       => array(
				'start' => $campaign_start,
				'end'   => $campaign_end,
				'days'  => $duration_days,
			),
			'baseline_metrics'      => array(
				'revenue'   => round( $baseline_revenue, 2 ),
				'orders'    => $baseline_orders,
				'customers' => $baseline_customers,
			),
			'campaign_metrics'      => array(
				'revenue'   => round( $campaign_perf['revenue'], 2 ),
				'orders'    => $campaign_perf['conversions'],
				'customers' => $campaign_perf['unique_customers'],
			),
			'incremental_impact'    => array(
				'revenue'   => round( $incremental_revenue, 2 ),
				'orders'    => $incremental_orders,
				'customers' => $incremental_customers,
			),
			'lift_percentages'      => array(
				'revenue'   => round( $revenue_lift, 2 ),
				'orders'    => round( $orders_lift, 2 ),
				'customers' => round( $customers_lift, 2 ),
			),
		);
	}
}
