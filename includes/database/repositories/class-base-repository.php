<?php
/**
 * Base Repository Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/database/repositories/class-base-repository.php
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
 * Base Repository Abstract Class
 *
 * Implements Template Method pattern for customizable CRUD operations.
 * Provides hooks for repository-specific logic while maintaining
 * consistent database interaction patterns.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/database
 * @author     Webstepper <contact@webstepper.io>
 */
abstract class WSSCD_Base_Repository {

	/**
	 * Table name.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $table_name    Database table name.
	 */
	protected $table_name;

	/**
	 * Primary key field.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $primary_key    Primary key field name.
	 */
	protected $primary_key = 'id';

	/**
	 * Date fields for automatic timestamp management.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      array    $date_fields    Date field names.
	 */
	protected $date_fields = array( 'created_at', 'updated_at' );

	/**
	 * JSON fields for automatic encoding/decoding.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      array    $json_fields    JSON field names.
	 */
	protected $json_fields = array();

	/**
	 * Template method for finding single record.
	 *
	 * @since    1.0.0
	 * @param    int $id    Record ID.
	 * @return   array|null    Record data or null if not found.
	 */
	public function find( $id ) {
		global $wpdb;

		// SECURITY: Use %i placeholder for table identifier (WordPress 6.2+).
		// Note: primary_key is always 'id' - a controlled internal value, not user input.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Primary key column name is internal class property.
		$sql = $wpdb->prepare(
			'SELECT * FROM %i WHERE ' . $this->primary_key . ' = %d',
			$this->table_name,
			$id
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query is prepared above with $wpdb->prepare().
		$result = $wpdb->get_row( $sql, ARRAY_A );

		if ( null === $result ) {
			return null;
		}

		return $this->prepare_item( $result );
	}

	/**
	 * Template method for finding all records with modular query building.
	 *
	 * @since    1.0.0
	 * @param    array $args    Query arguments.
	 * @return   array             Array of records.
	 */
	public function find_all( array $args = array() ) {
		$defaults = $this->get_default_query_args();
		$args     = wp_parse_args( $args, $defaults );

		$query_builder = $this->create_query_builder();
		$query_builder->select( '*' )
					->from( $this->get_table_name_without_prefix() );

		// Apply WHERE conditions.
		$this->apply_where_conditions( $query_builder, $args );

		// Apply ORDER BY.
		$this->apply_order_by( $query_builder, $args );

		// Apply LIMIT.
		$this->apply_limit( $query_builder, $args );

		// Execute via Query Builder - uses $wpdb->prepare() internally for all WHERE values.
		$results = $query_builder->get();

		return array_map( array( $this, 'prepare_item' ), $results );
	}

	/**
	 * Get default query arguments.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @return   array    Default arguments.
	 */
	protected function get_default_query_args() {
		return array(
			'orderby'   => 'id',
			'order'     => 'DESC',
			'limit'     => 100,
			'offset'    => 0,
			'status'    => '',
			'date_from' => '',
			'date_to'   => '',
		);
	}

	/**
	 * Modular query builder for complex queries.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @return   WSSCD_Query_Builder    Query builder instance.
	 */
	protected function create_query_builder() {
		require_once WSSCD_INCLUDES_DIR . 'database/class-query-builder.php';
		return new WSSCD_Query_Builder();
	}

	/**
	 * Hook method for repository-specific WHERE conditions.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @param    WSSCD_Query_Builder $query_builder    Query builder instance.
	 * @param    array             $args             Query arguments.
	 * @return   void
	 */
	protected function apply_where_conditions( $query_builder, $args ) {
		// Base implementation for common fields
		if ( ! empty( $args['status'] ) ) {
			$query_builder->where( 'status', '=', $args['status'] );
		}

		if ( ! empty( $args['date_from'] ) ) {
			$query_builder->where( 'created_at', '>=', $args['date_from'] );
		}

		if ( ! empty( $args['date_to'] ) ) {
			$query_builder->where( 'created_at', '<=', $args['date_to'] );
		}

		// Call repository-specific conditions
		$this->apply_custom_where_conditions( $query_builder, $args );
	}

	/**
	 * Apply ORDER BY clause.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @param    WSSCD_Query_Builder $query_builder    Query builder instance.
	 * @param    array             $args             Query arguments.
	 * @return   void
	 */
	protected function apply_order_by( $query_builder, $args ) {
		$orderby = sanitize_sql_orderby( $args['orderby'] );
		$order   = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

		if ( $orderby ) {
			$query_builder->order_by( $orderby, $order );
		}
	}

	/**
	 * Apply LIMIT clause.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @param    WSSCD_Query_Builder $query_builder    Query builder instance.
	 * @param    array             $args             Query arguments.
	 * @return   void
	 */
	protected function apply_limit( $query_builder, $args ) {
		if ( $args['limit'] > 0 ) {
			$query_builder->limit( (int) $args['limit'] );

			if ( $args['offset'] > 0 ) {
				$query_builder->offset( (int) $args['offset'] );
			}
		}
	}

	/**
	 * Hook for repository-specific WHERE conditions.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @param    WSSCD_Query_Builder $query_builder    Query builder instance.
	 * @param    array             $args             Query arguments.
	 * @return   void
	 */
	abstract protected function apply_custom_where_conditions( $query_builder, $args );

	/**
	 * Template method for creating records.
	 *
	 * @since    1.0.0
	 * @param    array $data    Data to insert.
	 * @return   int|WP_Error       Insert ID or error.
	 */
	public function create( array $data ) {
		global $wpdb;

		$data = $this->prepare_data_for_database( $data );

		if ( in_array( 'created_at', $this->date_fields, true ) ) {
			$data['created_at'] = current_time( 'mysql' );
		}

		if ( in_array( 'updated_at', $this->date_fields, true ) ) {
			$data['updated_at'] = current_time( 'mysql' );
		}

		$formats = $this->get_data_formats( $data );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Base repository INSERT; no caching on write.
		$result = $wpdb->insert(
			$this->table_name,
			$data,
			$formats
		);

		if ( false === $result ) {
			return new WP_Error(
				'db_insert_error',
				sprintf(
					/* translators: %1$s: entity name, %2$s: database error message */
					__( 'Failed to create %1$s record: %2$s', 'smart-cycle-discounts' ),
					$this->get_entity_name(),
					$wpdb->last_error
				)
			);
		}

		return $wpdb->insert_id;
	}

	/**
	 * Template method for updating records.
	 *
	 * @since    1.0.0
	 * @param    int   $id     Record ID.
	 * @param    array $data   Data to update.
	 * @return   bool|WP_Error    True on success, WP_Error on failure.
	 */
	public function update( $id, array $data ) {
		global $wpdb;

		$data = $this->prepare_data_for_database( $data );

		if ( in_array( 'updated_at', $this->date_fields, true ) ) {
			$data['updated_at'] = current_time( 'mysql' );
		}

		$formats = $this->get_data_formats( $data );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Base repository UPDATE; no caching on write.
		$result = $wpdb->update(
			$this->table_name,
			$data,
			array( $this->primary_key => $id ),
			$formats,
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'db_update_error',
				sprintf(
					/* translators: %1$s: entity name, %2$s: database error message */
					__( 'Failed to update %1$s record: %2$s', 'smart-cycle-discounts' ),
					$this->get_entity_name(),
					$wpdb->last_error
				)
			);
		}

		return true;
	}

	/**
	 * Template method for deleting records.
	 *
	 * @since    1.0.0
	 * @param    int $id    Record ID.
	 * @return   bool|WP_Error   True on success, WP_Error on failure.
	 */
	public function delete( $id ) {
		global $wpdb;

		if ( in_array( 'deleted_at', $this->date_fields, true ) ) {
			return $this->soft_delete( $id );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Base repository DELETE; no caching on write.
		$result = $wpdb->delete(
			$this->table_name,
			array( $this->primary_key => $id ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'db_delete_error',
				sprintf(
					/* translators: %1$s: entity name, %2$s: database error message */
					__( 'Failed to delete %1$s record: %2$s', 'smart-cycle-discounts' ),
					$this->get_entity_name(),
					$wpdb->last_error
				)
			);
		}

		return true;
	}

	/**
	 * Soft delete a record.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @param    int $id    Record ID.
	 * @return   bool|WP_Error   True on success, WP_Error on failure.
	 */
	protected function soft_delete( $id ) {
		return $this->update( $id, array( 'deleted_at' => current_time( 'mysql' ) ) );
	}

	/**
	 * Get data formats for wpdb operations.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @param    array $data    Data array.
	 * @return   array              Format specifications.
	 */
	protected function get_data_formats( array $data ) {
		$formats = array();

		foreach ( $data as $field => $value ) {
			if ( is_int( $value ) ) {
				$formats[] = '%d';
			} elseif ( is_float( $value ) ) {
				$formats[] = '%f';
			} else {
				$formats[] = '%s';
			}
		}

		return $formats;
	}

	/**
	 * Count records matching criteria.
	 *
	 * @since    1.0.0
	 * @param    array $args    Query arguments.
	 * @return   int                 Record count.
	 */
	public function count( array $args = array() ) {
		$query_builder = $this->create_query_builder();
		$query_builder->from( $this->get_table_name_without_prefix() );

		// Apply WHERE conditions.
		$this->apply_where_conditions( $query_builder, $args );

		// Execute via Query Builder's count() method - uses $wpdb->prepare() internally.
		return $query_builder->count();
	}

	/**
	 * Check if record exists.
	 *
	 * @since    1.0.0
	 * @param    int $id    Record ID.
	 * @return   bool           True if exists.
	 */
	public function exists( $id ) {
		global $wpdb;

		// SECURITY: Use %i placeholder for table identifier (WordPress 6.2+).
		// Note: primary_key is always 'id' - a controlled internal value, not user input.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Primary key column name is internal class property.
		$sql = $wpdb->prepare(
			'SELECT EXISTS(SELECT 1 FROM %i WHERE ' . $this->primary_key . ' = %d)',
			$this->table_name,
			$id
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query is prepared above with $wpdb->prepare().
		return (bool) $wpdb->get_var( $sql );
	}

	/**
	 * Prepare item for output.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @param    array $data    Raw database data.
	 * @return   array             Prepared data.
	 */
	protected function prepare_item( array $data ) {
		// Decode JSON fields
		foreach ( $this->json_fields as $field ) {
			if ( isset( $data[ $field ] ) && ! empty( $data[ $field ] ) ) {
				$data[ $field ] = json_decode( $data[ $field ], true );
			}
		}

		// Call repository-specific preparation
		return $this->prepare_item_output( $data );
	}

	/**
	 * Hook method for entity-specific data preparation.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @param    array $data    Data to prepare.
	 * @return   array             Prepared data.
	 */
	abstract protected function prepare_data_for_database( array $data );

	/**
	 * Hook method for entity-specific data transformation.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @param    array $data    Raw data.
	 * @return   array             Transformed data.
	 */
	abstract protected function prepare_item_output( array $data );

	/**
	 * Get entity name for error messages.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @return   string    Entity name.
	 */
	abstract protected function get_entity_name();

	/**
	 * Begin database transaction.
	 *
	 * @since    1.0.0
	 * @return   bool    True on success.
	 */
	public function begin_transaction() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Transaction management; no abstraction available.
		return $wpdb->query( 'START TRANSACTION' );
	}

	/**
	 * Commit database transaction.
	 *
	 * @since    1.0.0
	 * @return   bool    True on success.
	 */
	public function commit() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Transaction management; no abstraction available.
		return $wpdb->query( 'COMMIT' );
	}

	/**
	 * Rollback database transaction.
	 *
	 * @since    1.0.0
	 * @return   bool    True on success.
	 */
	public function rollback() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Transaction management; no abstraction available.
		return $wpdb->query( 'ROLLBACK' );
	}

	/**
	 * Get last insert ID.
	 *
	 * @since    1.0.0
	 * @return   int    Last insert ID.
	 */
	public function get_last_insert_id() {
		global $wpdb;
		return $wpdb->insert_id;
	}

	/**
	 * Get table name with prefix.
	 *
	 * @since    1.0.0
	 * @return   string    Full table name.
	 */
	public function get_table_name() {
		return $this->table_name;
	}

	/**
	 * Get table name without prefix for Query Builder.
	 *
	 * The Query Builder's from() method adds the prefix automatically,
	 * so we need to strip it when passing from repositories.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @return   string    Table name without prefix.
	 */
	protected function get_table_name_without_prefix(): string {
		global $wpdb;
		$prefix = $wpdb->prefix;

		if ( strpos( $this->table_name, $prefix ) === 0 ) {
			return substr( $this->table_name, strlen( $prefix ) );
		}

		return $this->table_name;
	}

	/**
	 * Encode JSON fields for database storage.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @param    array $data    Data array to process.
	 * @return   array             Data with JSON fields encoded.
	 */
	protected function encode_json_fields( array $data ): array {
		foreach ( $this->json_fields as $field ) {
			if ( isset( $data[ $field ] ) && is_array( $data[ $field ] ) ) {
				$data[ $field ] = wp_json_encode( $data[ $field ] );
			}
		}
		return $data;
	}

	/**
	 * Convert specified fields to integers.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @param    array $data            Data array to process.
	 * @param    array $numeric_fields  Fields to convert to integers.
	 * @return   array                     Data with converted fields.
	 */
	protected function convert_to_int( array $data, array $numeric_fields ): array {
		foreach ( $numeric_fields as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$data[ $field ] = (int) $data[ $field ];
			}
		}
		return $data;
	}

	/**
	 * Convert specified fields to floats.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @param    array $data          Data array to process.
	 * @param    array $float_fields  Fields to convert to floats.
	 * @return   array                   Data with converted fields.
	 */
	protected function convert_to_float( array $data, array $float_fields ): array {
		foreach ( $float_fields as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$data[ $field ] = (float) $data[ $field ];
			}
		}
		return $data;
	}
}
