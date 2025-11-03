<?php
/**
 * Analytics Repository Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/database/repositories/class-analytics-repository.php
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


require_once SCD_INCLUDES_DIR . 'database/repositories/class-base-repository.php';

/**
 * Analytics Repository Class
 *
 * Handles database operations for analytics data using the base repository pattern.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/database
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Analytics_Repository extends SCD_Base_Repository {

	/**
	 * Initialize the repository.
	 *
	 * @since    1.0.0
	 * @param    SCD_Database_Manager $database_manager    Database manager (for DI compatibility, not used).
	 */
	public function __construct( SCD_Database_Manager $database_manager ) {
		global $wpdb;
		// Note: database_manager parameter is kept for DI container compatibility
		// but not used. Base repository uses global $wpdb directly.
		$this->table_name  = $wpdb->prefix . 'scd_analytics';
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
	 * @since    1.0.0
	 * @param    int    $campaign_id    Campaign ID.
	 * @param    string $start_date     Optional start date.
	 * @param    string $end_date       Optional end date.
	 * @return   array                     Performance statistics.
	 */
	public function get_campaign_performance( $campaign_id, $start_date = '', $end_date = '' ) {
		global $wpdb;

		$where_conditions = array( 'campaign_id = %d' );
		$where_values     = array( $campaign_id );

		if ( ! empty( $start_date ) ) {
			$where_conditions[] = 'event_timestamp >= %s';
			$where_values[]     = $start_date;
		}

		if ( ! empty( $end_date ) ) {
			$where_conditions[] = 'event_timestamp <= %s';
			$where_values[]     = $end_date;
		}

		$where_sql = implode( ' AND ', $where_conditions );

		$sql = $wpdb->prepare(
			"SELECT 
				event_type,
				COUNT(*) as count,
				SUM(CASE WHEN event_type = 'discount_applied' THEN CAST(JSON_EXTRACT(event_data, '$.savings_amount') AS DECIMAL(10,2)) ELSE 0 END) as total_savings,
				AVG(CASE WHEN event_type = 'discount_applied' THEN CAST(JSON_EXTRACT(event_data, '$.discount_value') AS DECIMAL(5,2)) ELSE NULL END) as avg_discount_value
			 FROM {$this->table_name} 
			 WHERE {$where_sql}
			 GROUP BY event_type",
			$where_values
		);

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
			$count      = intval( $row['count'] );

			switch ( $event_type ) {
				case 'impression':
					$performance['impressions'] = $count;
					break;
				case 'click':
					$performance['clicks'] = $count;
					break;
				case 'discount_applied':
					$performance['conversions']        = $count;
					$performance['total_savings']      = floatval( $row['total_savings'] );
					$performance['avg_discount_value'] = floatval( $row['avg_discount_value'] );
					break;
			}
		}

		if ( $performance['impressions'] > 0 ) {
			$performance['click_through_rate'] = ( $performance['clicks'] / $performance['impressions'] ) * 100;
		}

		if ( $performance['clicks'] > 0 ) {
			$performance['conversion_rate'] = ( $performance['conversions'] / $performance['clicks'] ) * 100;
		}

		return $performance;
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

		$count_sql   = $wpdb->prepare(
			"SELECT COUNT(DISTINCT event_type) FROM {$this->table_name} WHERE {$where_sql}",
			$where_values
		);
		$total_count = (int) $wpdb->get_var( $count_sql );

		$sql = $wpdb->prepare(
			"SELECT 
				event_type,
				COUNT(*) as count,
				SUM(CASE WHEN event_type = 'discount_applied' THEN CAST(JSON_EXTRACT(event_data, '$.savings_amount') AS DECIMAL(10,2)) ELSE 0 END) as total_savings,
				AVG(CASE WHEN event_type = 'discount_applied' THEN CAST(JSON_EXTRACT(event_data, '$.discount_value') AS DECIMAL(5,2)) ELSE NULL END) as avg_discount_value
			 FROM {$this->table_name} 
			 WHERE {$where_sql}
			 GROUP BY event_type
			 LIMIT %d OFFSET %d",
			array_merge( $where_values, array( $per_page, $offset ) )
		);

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
	 * Get product performance statistics.
	 *
	 * @since    1.0.0
	 * @param    int    $product_id    Product ID.
	 * @param    string $start_date    Optional start date.
	 * @param    string $end_date      Optional end date.
	 * @return   array                    Product performance statistics.
	 */
	public function get_product_performance( $product_id, $start_date = '', $end_date = '' ) {
		global $wpdb;

		$where_conditions = array( 'product_id = %d' );
		$where_values     = array( $product_id );

		if ( ! empty( $start_date ) ) {
			$where_conditions[] = 'event_timestamp >= %s';
			$where_values[]     = $start_date;
		}

		if ( ! empty( $end_date ) ) {
			$where_conditions[] = 'event_timestamp <= %s';
			$where_values[]     = $end_date;
		}

		$where_sql = implode( ' AND ', $where_conditions );

		$sql = $wpdb->prepare(
			"SELECT 
				event_type,
				COUNT(*) as count,
				AVG(CASE WHEN event_type = 'discount_applied' THEN CAST(JSON_EXTRACT(event_data, '$.discount_value') AS DECIMAL(5,2)) ELSE NULL END) as avg_discount
			 FROM {$this->table_name} 
			 WHERE {$where_sql}
			 GROUP BY event_type",
			$where_values
		);

		$results = $wpdb->get_results( $sql, ARRAY_A );

		$performance = array(
			'total_views'           => 0,
			'discount_applications' => 0,
			'avg_discount_value'    => 0.0,
		);

		foreach ( $results as $row ) {
			$event_type = $row['event_type'];
			$count      = intval( $row['count'] );

			if ( 'product_view' === $event_type ) {
				$performance['total_views'] = $count;
			} elseif ( 'discount_applied' === $event_type ) {
				$performance['discount_applications'] = $count;
				$performance['avg_discount_value']    = floatval( $row['avg_discount'] );
			}
		}

		return $performance;
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

		$where_sql = '';
		if ( ! empty( $where_conditions ) ) {
			$where_sql = 'WHERE ' . implode( ' AND ', $where_conditions );
		}

		$sql = "SELECT 
					event_type,
					COUNT(*) as count
				FROM {$this->table_name} 
				{$where_sql}
				GROUP BY event_type
				ORDER BY count DESC";

		if ( ! empty( $where_values ) ) {
			$sql = $wpdb->prepare( $sql, $where_values );
		}

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

		$cutoff_date = date( 'Y-m-d H:i:s', strtotime( "-{$days_old} days" ) );

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
	 * @param    SCD_Query_Builder $query_builder    Query builder instance.
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
}
