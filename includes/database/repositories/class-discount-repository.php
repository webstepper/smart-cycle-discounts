<?php
/**
 * Discount Repository Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/database/repositories/class-discount-repository.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress/plugins/smart-cycle-discounts/
 * @since      1.0.0
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


require_once WSSCD_INCLUDES_DIR . 'database/repositories/class-base-repository.php';

/**
 * Discount Repository Class
 *
 * Handles database operations for discount records using the base repository pattern.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/database
 * @author     Webstepper <contact@webstepper.io>
 */
class WSSCD_Discount_Repository extends WSSCD_Base_Repository {

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
		$this->table_name  = $wpdb->prefix . 'wsscd_active_discounts';
		$this->primary_key = 'id';
		$this->date_fields = array( 'created_at', 'updated_at', 'starts_at', 'ends_at', 'applied_at' );
		$this->json_fields = array( 'metadata', 'conditions' );
	}

	/**
	 * Find discounts by campaign ID.
	 *
	 * @since    1.0.0
	 * @param    int    $campaign_id    Campaign ID.
	 * @param    string $status         Optional status filter.
	 * @return   array                    Array of discount records.
	 */
	public function find_by_campaign( $campaign_id, $status = '' ) {
		$args = array(
			'campaign_id' => $campaign_id,
		);

		if ( ! empty( $status ) ) {
			$args['status'] = $status;
		}

		return $this->find_all( $args );
	}

	/**
	 * Find discounts by product ID.
	 *
	 * @since    1.0.0
	 * @param    int    $product_id    Product ID.
	 * @param    string $status        Optional status filter.
	 * @return   array                   Array of discount records.
	 */
	public function find_by_product( $product_id, $status = '' ) {
		$args = array(
			'product_id' => $product_id,
		);

		if ( ! empty( $status ) ) {
			$args['status'] = $status;
		}

		return $this->find_all( $args );
	}

	/**
	 * Find active discounts for a product.
	 *
	 * @since    1.0.0
	 * @param    int $product_id    Product ID.
	 * @return   array                 Array of active discount records.
	 */
	public function find_active_for_product( $product_id ) {
		$args = array(
			'product_id'  => $product_id,
			'active_only' => true,
		);

		return $this->find_all( $args );
	}

	/**
	 * Find expired discounts.
	 *
	 * @since    1.0.0
	 * @param    string $cutoff_date    Cutoff date for expiration.
	 * @return   array                     Array of expired discount records.
	 */
	public function find_expired( $cutoff_date ) {
		global $wpdb;

		// Validate date format using strict DateTime parsing
		$cutoff_date = sanitize_text_field( $cutoff_date );

		// Try parsing as full datetime first
		$datetime = DateTime::createFromFormat( 'Y-m-d H:i:s', $cutoff_date );

		// If that fails, try date-only format
		if ( ! $datetime || $datetime->format( 'Y-m-d H:i:s' ) !== $cutoff_date ) {
			$datetime = DateTime::createFromFormat( 'Y-m-d', $cutoff_date );

			// If date-only format succeeds, append time
			if ( $datetime && $datetime->format( 'Y-m-d' ) === $cutoff_date ) {
				$cutoff_date = $datetime->format( 'Y-m-d' ) . ' 00:00:00';
			} else {
				// Invalid date format - reject
				return array();
			}
		}

		// SECURITY: Use %i placeholder for table identifier (WordPress 6.2+).
		$sql = $wpdb->prepare(
			'SELECT * FROM %i
			 WHERE status = %s
			 AND ends_at < %s
			 ORDER BY ends_at ASC',
			$this->table_name,
			'active',
			$cutoff_date
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared , PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Query is prepared above with $wpdb->prepare().
		$results = $wpdb->get_results( $sql, ARRAY_A );

		if ( ! $results ) {
			return array();
		}

		return array_map( array( $this, 'prepare_item' ), $results );
	}

	/**
	 * Get discount statistics.
	 *
	 * @since    1.0.0
	 * @param    int $campaign_id    Optional campaign ID filter.
	 * @return   array                  Discount statistics.
	 */
	public function get_statistics( $campaign_id = 0 ) {
		global $wpdb;

		// SECURITY: Always use $wpdb->prepare() to satisfy WordPress.org review requirements.
		if ( $campaign_id > 0 ) {
			$sql = $wpdb->prepare(
				'SELECT
					status,
					COUNT(*) as count,
					SUM(discount_value) as total_discount_value,
					AVG(discount_value) as avg_discount_value,
					SUM(savings_amount) as total_savings
				FROM %i
				WHERE campaign_id = %d
				GROUP BY status',
				$this->table_name,
				$campaign_id
			);
		} else {
			// No campaign filter - get all statistics.
			$sql = $wpdb->prepare(
				'SELECT
					status,
					COUNT(*) as count,
					SUM(discount_value) as total_discount_value,
					AVG(discount_value) as avg_discount_value,
					SUM(savings_amount) as total_savings
				FROM %i
				GROUP BY status',
				$this->table_name
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query is prepared above with $wpdb->prepare().
		$results = $wpdb->get_results( $sql, ARRAY_A );

		$statistics = array(
			'total_discounts'      => 0,
			'active_discounts'     => 0,
			'expired_discounts'    => 0,
			'total_discount_value' => 0.0,
			'total_savings'        => 0.0,
			'average_discount'     => 0.0,
			'by_status'            => array(),
		);

		foreach ( $results as $row ) {
			$status               = $row['status'];
			$count                = intval( $row['count'] );
			$total_discount_value = floatval( $row['total_discount_value'] );
			$avg_discount_value   = floatval( $row['avg_discount_value'] );
			$total_savings        = floatval( $row['total_savings'] );

			$statistics['by_status'][ $status ] = array(
				'count'                => $count,
				'total_discount_value' => $total_discount_value,
				'avg_discount_value'   => $avg_discount_value,
				'total_savings'        => $total_savings,
			);

			$statistics['total_discounts']      += $count;
			$statistics['total_discount_value'] += $total_discount_value;
			$statistics['total_savings']        += $total_savings;

			if ( 'active' === $status ) {
				$statistics['active_discounts'] = $count;
			} elseif ( 'expired' === $status ) {
				$statistics['expired_discounts'] = $count;
			}
		}

		if ( $statistics['total_discounts'] > 0 ) {
			$statistics['average_discount'] = $statistics['total_discount_value'] / $statistics['total_discounts'];
		}

		return $statistics;
	}

	/**
	 * Bulk update discount status.
	 *
	 * @since    1.0.0
	 * @param    array  $discount_ids    Array of discount IDs.
	 * @param    string $status          New status.
	 * @return   int                        Number of updated discounts.
	 */
	public function bulk_update_status( array $discount_ids, $status ) {
		if ( empty( $discount_ids ) ) {
			return 0;
		}

		global $wpdb;

		$placeholders = implode( ',', array_fill( 0, count( $discount_ids ), '%d' ) );

		// SECURITY: Use %i placeholder for table identifier (WordPress 6.2+).
		$values = array_merge( array( $this->table_name, $status, current_time( 'mysql' ) ), $discount_ids );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter
		// Placeholders generated via array_fill for IN clause.
		$sql = $wpdb->prepare(
			'UPDATE %i
			 SET status = %s, updated_at = %s
			 WHERE id IN (' . $placeholders . ')',
			$values
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query is prepared above with $wpdb->prepare().
		$result = $wpdb->query( $sql );

		return false !== $result ? $result : 0;
	}

	/**
	 * Expire old discounts.
	 *
	 * @since    1.0.0
	 * @return   int    Number of expired discounts.
	 */
	public function expire_old_discounts() {
		global $wpdb;

		// SECURITY: Use %i placeholder for table identifier (WordPress 6.2+).
		$sql = $wpdb->prepare(
			'UPDATE %i
			 SET status = %s, updated_at = %s
			 WHERE status = %s
			 AND ends_at < %s',
			$this->table_name,
			'expired',
			current_time( 'mysql' ),
			'active',
			current_time( 'mysql' )
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Query is prepared above with $wpdb->prepare().
		$result = $wpdb->query( $sql );

		return false !== $result ? $result : 0;
	}

	/**
	 * Delete discounts by campaign ID.
	 *
	 * @since    1.0.0
	 * @param    int $campaign_id    Campaign ID.
	 * @return   int|WP_Error           Number of deleted discounts or error.
	 */
	public function delete_by_campaign( $campaign_id ) {
		// Capability check for authorization
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return new WP_Error(
				'insufficient_permissions',
				__( 'Insufficient permissions to delete discounts', 'smart-cycle-discounts' )
			);
		}

		$campaign_id = absint( $campaign_id );
		if ( $campaign_id < 1 ) {
			return new WP_Error(
				'invalid_campaign_id',
				__( 'Invalid campaign ID', 'smart-cycle-discounts' )
			);
		}

		// Verify campaign ownership before deletion
		try {
			$container       = WSSCD_Core_Container::get_instance();
			$campaign_repo   = $container->get( 'campaign_repository' );

			if ( $campaign_repo ) {
				$campaign = $campaign_repo->find( $campaign_id );

				// find() returns null if user doesn't own campaign and isn't admin
				if ( ! $campaign ) {
					return new WP_Error(
						'campaign_not_found',
						__( 'Campaign not found or you do not have permission to delete its discounts', 'smart-cycle-discounts' )
					);
				}
			}
		} catch ( Exception $e ) {
			// If we can't verify ownership, allow deletion for backward compatibility
			// The capability check above provides baseline security
			unset( $e );
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching , PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Discount cleanup; no caching on delete.
		$result = $wpdb->delete(
			$this->table_name,
			array( 'campaign_id' => $campaign_id ),
			array( '%d' )
		);

		return false !== $result ? $result : 0;
	}

	/**
	 * Apply custom WHERE conditions for discount queries.
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

		// Customer ID filter
		if ( ! empty( $args['customer_id'] ) ) {
			$query_builder->where( 'customer_id', '=', $args['customer_id'] );
		}

		// Order ID filter
		if ( ! empty( $args['order_id'] ) ) {
			$query_builder->where( 'order_id', '=', $args['order_id'] );
		}

		// Discount type filter
		if ( ! empty( $args['discount_type'] ) ) {
			$query_builder->where( 'discount_type', '=', $args['discount_type'] );
		}

		// Active discounts only
		if ( isset( $args['active_only'] ) && $args['active_only'] ) {
			$query_builder->where( 'status', '=', 'active' )
						->where( 'starts_at', '<=', current_time( 'mysql' ) )
						->where( 'ends_at', '>=', current_time( 'mysql' ) );
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
			'campaign_id',
			'product_id',
			'customer_id',
			'order_id',
			'discount_type',
			'discount_value',
			'original_price',
			'discounted_price',
			'savings_amount',
			'status',
			'starts_at',
			'ends_at',
			'applied_at',
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
			'customer_id',
			'order_id',
		);

		$float_fields = array(
			'discount_value',
			'original_price',
			'discounted_price',
			'savings_amount',
		);

		$data = $this->convert_to_int( $data, $numeric_fields );
		$data = $this->convert_to_float( $data, $float_fields );

		return $data;
	}

	/**
	 * Get entity name for error messages.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @return   string    Entity name.
	 */
	protected function get_entity_name() {
		return __( 'discount', 'smart-cycle-discounts' );
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
