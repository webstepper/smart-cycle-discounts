<?php
/**
 * Database Manager Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/database/class-database-manager.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress/plugins/smart-cycle-discounts/
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}


/**
 * Database Manager
 *
 * Manages database operations for the plugin.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/database
 * @author     Webstepper <contact@webstepper.io>
 */
class WSSCD_Database_Manager {

	/**
	 * WordPress database instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      wpdb    $wpdb    WordPress database instance.
	 */
	private wpdb $wpdb;

	/**
	 * Database table names.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $tables    Database table names.
	 */
	private array $tables;

	/**
	 * Database charset collate.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $charset_collate    Database charset collate.
	 */
	private string $charset_collate;

	/**
	 * Initialize the database manager.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		global $wpdb;

		$this->wpdb            = $wpdb;
		$this->charset_collate = $wpdb->get_charset_collate();

		$this->setup_table_names();
	}

	/**
	 * Setup database table names.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function setup_table_names(): void {
		$this->tables = array(
			'campaigns'           => $this->wpdb->prefix . 'wsscd_campaigns',
			'campaign_conditions' => $this->wpdb->prefix . 'wsscd_campaign_conditions',
			'active_discounts'    => $this->wpdb->prefix . 'wsscd_active_discounts',
			'analytics'           => $this->wpdb->prefix . 'wsscd_analytics',
			'customer_usage'      => $this->wpdb->prefix . 'wsscd_customer_usage',
			'campaign_recurring'  => $this->wpdb->prefix . 'wsscd_campaign_recurring',
			'activity_log'        => $this->wpdb->prefix . 'wsscd_activity_log',
			'product_analytics'   => $this->wpdb->prefix . 'wsscd_product_analytics',
			'recurring_cache'     => $this->wpdb->prefix . 'wsscd_recurring_cache',
			'migrations'          => $this->wpdb->prefix . 'wsscd_migrations',
		);
	}

	/**
	 * Get table name.
	 *
	 * @since    1.0.0
	 * @param    string|null $table    Table identifier.
	 * @return   string                   Full table name.
	 */
	public function get_table_name( ?string $table ): string {
		if ( null === $table || empty( $table ) ) {
			return '';
		}
		return $this->tables[ $table ] ?? '';
	}

	/**
	 * Get all table names.
	 *
	 * @since    1.0.0
	 * @return   array    All table names.
	 */
	public function get_all_tables(): array {
		return $this->tables;
	}

	/**
	 * Check if table exists.
	 *
	 * @since    1.0.0
	 * @param    string $table    Table identifier.
	 * @return   bool                True if table exists.
	 */
	public function table_exists( string $table ): bool {
		$table_name = $this->get_table_name( $table );
		if ( empty( $table_name ) ) {
			return false;
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- SHOW TABLES has no WP abstraction; ephemeral check, caching not appropriate. Query IS prepared.
		$result = $this->wpdb->get_var(
			$this->wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name )
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls

		return $table_name === $result;
	}

	/**
	 * Get columns for a table.
	 *
	 * @since    1.0.0
	 * @param    string $table_name    Full table name (with prefix).
	 * @return   array                    Array of column names.
	 */
	public function get_columns( string $table_name ): array {
		// Validate table name against whitelist to prevent SQL injection.
		if ( ! in_array( $table_name, $this->tables, true ) ) {
			return array();
		}

		$columns = array();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- INFORMATION_SCHEMA query has no WP abstraction; schema inspection, caching not appropriate. Query IS prepared.
		$results = $this->wpdb->get_results(
			$this->wpdb->prepare(
				'SELECT COLUMN_NAME
				FROM INFORMATION_SCHEMA.COLUMNS
				WHERE TABLE_SCHEMA = %s
				AND TABLE_NAME = %s
				ORDER BY ORDINAL_POSITION',
				DB_NAME,
				$table_name
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls

		if ( ! empty( $results ) ) {
			foreach ( $results as $column ) {
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- MySQL INFORMATION_SCHEMA column name.
				$columns[] = $column->COLUMN_NAME;
			}
		}

		return $columns;
	}

	/**
	 * Execute a query.
	 *
	 * @since    1.0.0
	 * @param    string $query    SQL query.
	 * @return   int|false           Number of rows affected or false on error.
	 */
	public function query( string $query ) {
		$start_time = microtime( true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Generic query method; receives pre-prepared queries from calling code; caching handled via WSSCD_Cache_Manager.
		$result   = $this->wpdb->query( $query );
		$duration = microtime( true ) - $start_time;

		// Log database query for debugging.
		if ( function_exists( 'wsscd_debug_database' ) ) {
			wsscd_debug_database( 'query', 'custom', array( 'query' => $query ), $result, $duration );
		}

		// Handle wpdb->query return types correctly.
		// Returns: int (rows affected), false (error), or true (DDL success).
		if ( false === $result ) {
			return false; // Error.
		}

		if ( true === $result ) {
			return 1; // DDL success (CREATE, ALTER, DROP) - return 1, not 0.
		}

		return (int) $result; // Affected rows.
	}

	/**
	 * Get a single variable.
	 *
	 * @since    1.0.0
	 * @param    string $query    SQL query.
	 * @return   string|null         Variable value or null.
	 */
	public function get_var( string $query ): ?string {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Generic query method; receives pre-prepared queries from calling code; caching handled via WSSCD_Cache_Manager.
		$result = $this->wpdb->get_var( $query );
		return null !== $result ? (string) $result : null;
	}

	/**
	 * Get a single row.
	 *
	 * @since    1.0.0
	 * @param    string $query      SQL query.
	 * @param    string $output     Output type (OBJECT, ARRAY_A, ARRAY_N).
	 * @return   mixed                 Row data or null.
	 */
	public function get_row( string $query, string $output = OBJECT ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Generic query method; receives pre-prepared queries from calling code; caching handled via WSSCD_Cache_Manager.
		return $this->wpdb->get_row( $query, $output );
	}

	/**
	 * Get multiple rows.
	 *
	 * @since    1.0.0
	 * @param    string $query      SQL query.
	 * @param    string $output     Output type (OBJECT, ARRAY_A, ARRAY_N).
	 * @return   array                 Array of rows.
	 */
	public function get_results( string $query, string $output = OBJECT ): array {
		$start_time = microtime( true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Generic query method; receives pre-prepared queries from calling code; caching handled via WSSCD_Cache_Manager.
		$results  = $this->wpdb->get_results( $query, $output );
		$duration = microtime( true ) - $start_time;

		// Log select operation for debugging.
		if ( function_exists( 'wsscd_debug_database' ) ) {
			$row_count = is_array( $results ) ? count( $results ) : 0;
			wsscd_debug_database( 'select', 'custom', array( 'query' => $query ), $row_count, $duration );
		}

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Insert data into table.
	 *
	 * @since    1.0.0
	 * @param    string $table    Table identifier.
	 * @param    array  $data     Data to insert.
	 * @param    array  $format   Data format.
	 * @return   int|false           Insert ID or false on error.
	 */
	public function insert( string $table, array $data, array $format = array() ) {
		$table_name = $this->get_table_name( $table );
		if ( empty( $table_name ) ) {
			return false;
		}

		$start_time = microtime( true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Insert into plugin's custom tables; cache invalidated after operation.
		$result   = $this->wpdb->insert( $table_name, $data, $format );
		$duration = microtime( true ) - $start_time;

		if ( false === $result ) {
			// Log failed insert for debugging.
			if ( function_exists( 'wsscd_debug_database' ) ) {
				wsscd_debug_database( 'insert', $table, $data, false, $duration );
			}

			return false;
		}

		// Log successful insert for debugging.
		if ( function_exists( 'wsscd_debug_database' ) ) {
			wsscd_debug_database( 'insert', $table, $data, $this->wpdb->insert_id, $duration );
		}

		return $this->wpdb->insert_id;
	}

	/**
	 * Update data in table.
	 *
	 * @since    1.0.0
	 * @param    string $table        Table identifier.
	 * @param    array  $data         Data to update.
	 * @param    array  $where        WHERE conditions.
	 * @param    array  $format       Data format.
	 * @param    array  $where_format WHERE format.
	 * @return   int|false               Number of rows updated or false on error.
	 */
	public function update( string $table, array $data, array $where, array $format = array(), array $where_format = array() ) {
		$table_name = $this->get_table_name( $table );
		if ( empty( $table_name ) ) {
			return false;
		}

		$start_time = microtime( true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Update plugin's custom tables; cache invalidated after operation via WSSCD_Cache_Manager.
		$result   = $this->wpdb->update( $table_name, $data, $where, $format, $where_format );
		$duration = microtime( true ) - $start_time;

		// Log update operation for debugging.
		if ( function_exists( 'wsscd_debug_database' ) ) {
			$update_data = array_merge( $data, array( 'where' => $where ) );
			wsscd_debug_database( 'update', $table, $update_data, $result, $duration );
		}

		return $result;
	}

	/**
	 * Delete data from table.
	 *
	 * @since    1.0.0
	 * @param    string $table        Table identifier.
	 * @param    array  $where        WHERE conditions.
	 * @param    array  $where_format WHERE format.
	 * @return   int|false               Number of rows deleted or false on error.
	 */
	public function delete( string $table, array $where, array $where_format = array() ) {
		$table_name = $this->get_table_name( $table );
		if ( empty( $table_name ) ) {
			return false;
		}

		$start_time = microtime( true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Delete from plugin's custom tables; cache invalidated after operation via WSSCD_Cache_Manager.
		$result   = $this->wpdb->delete( $table_name, $where, $where_format );
		$duration = microtime( true ) - $start_time;

		// Log delete operation for debugging.
		if ( function_exists( 'wsscd_debug_database' ) ) {
			wsscd_debug_database( 'delete', $table, array( 'where' => $where ), $result, $duration );
		}

		return $result;
	}

	/**
	 * Prepare a SQL query.
	 *
	 * @since    1.0.0
	 * @param    string $query    SQL query with placeholders.
	 * @param    mixed  ...$args  Arguments for placeholders.
	 * @return   string              Prepared query.
	 */
	public function prepare( string $query, ...$args ): string {
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- This is a wrapper method; $query contains placeholders, $args contains values.
		return $this->wpdb->prepare( $query, ...$args );
	}

	/**
	 * Start a database transaction.
	 *
	 * @since    1.0.0
	 * @return   bool    True on success, false on failure.
	 */
	public function start_transaction(): bool {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Transaction control has no WP abstraction.
		return false !== $this->wpdb->query( 'START TRANSACTION' );
	}

	/**
	 * Commit a database transaction.
	 *
	 * @since    1.0.0
	 * @return   bool    True on success, false on failure.
	 */
	public function commit(): bool {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Transaction control has no WP abstraction.
		return false !== $this->wpdb->query( 'COMMIT' );
	}

	/**
	 * Rollback a database transaction.
	 *
	 * @since    1.0.0
	 * @return   bool    True on success, false on failure.
	 */
	public function rollback(): bool {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- Transaction control has no WP abstraction.
		return false !== $this->wpdb->query( 'ROLLBACK' );
	}

	/**
	 * Execute a callback within a transaction.
	 *
	 * @since    1.0.0
	 * @param    callable $callback    Callback to execute.
	 * @return   mixed                    Callback result or false on error.
	 * @throws   Exception                 Re-throws any exception from the callback.
	 */
	public function transaction( callable $callback ) {
		if ( ! $this->start_transaction() ) {
			return false;
		}

		try {
			$result = $callback( $this );

			if ( false === $result ) {
				$this->rollback();
				return false;
			}

			if ( ! $this->commit() ) {
				$this->rollback();
				return false;
			}

			return $result;
		} catch ( Exception $e ) {
			$this->rollback();
			throw $e;
		}
	}

	/**
	 * Get the last error.
	 *
	 * @since    1.0.0
	 * @return   string    Last error message.
	 */
	public function get_last_error(): string {
		return $this->wpdb->last_error;
	}

	/**
	 * Get the last query.
	 *
	 * @since    1.0.0
	 * @return   string    Last executed query.
	 */
	public function get_last_query(): string {
		return $this->wpdb->last_query;
	}

	/**
	 * Get database statistics.
	 *
	 * @since    1.0.0
	 * @return   array    Database statistics.
	 */
	public function get_stats(): array {
		$stats = array(
			'tables'     => array(),
			'total_size' => 0,
			'charset'    => $this->wpdb->charset,
			'collate'    => $this->wpdb->collate,
		);

		foreach ( $this->tables as $key => $table_name ) {
			if ( $this->table_exists( $key ) ) {
				$size  = $this->get_table_size( $table_name );
				$count = $this->get_table_count( $table_name );

				$stats['tables'][ $key ] = array(
					'name'   => $table_name,
					'size'   => $size,
					'count'  => $count,
					'exists' => true,
				);

				$stats['total_size'] += $size;
			} else {
				$stats['tables'][ $key ] = array(
					'name'   => $table_name,
					'size'   => 0,
					'count'  => 0,
					'exists' => false,
				);
			}
		}

		return $stats;
	}

	/**
	 * Get table size in bytes.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $table_name    Table name.
	 * @return   int                      Table size in bytes.
	 */
	private function get_table_size( string $table_name ): int {
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls -- INFORMATION_SCHEMA query has no WP abstraction; admin stats only, caching not appropriate. Query IS prepared.
		$result = $this->wpdb->get_row(
			$this->wpdb->prepare(
				'SELECT (data_length + index_length) as size
				FROM information_schema.TABLES
				WHERE table_schema = %s AND table_name = %s',
				DB_NAME,
				$table_name
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls

		return $result ? (int) $result->size : 0;
	}

	/**
	 * Get table row count.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $table_name    Table name.
	 * @return   int                      Row count.
	 */
	private function get_table_count( string $table_name ): int {
		// SECURITY: Use %i placeholder for table identifier (WordPress 6.2+).
		$sql = $this->wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table_name );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query is prepared above with $wpdb->prepare().
		$result = $this->wpdb->get_var( $sql );

		return $result ? (int) $result : 0;
	}

	/**
	 * Optimize database tables.
	 *
	 * @since    1.0.0
	 * @return   array    Optimization results.
	 */
	public function optimize_tables(): array {
		$results = array();

		foreach ( $this->tables as $key => $table_name ) {
			if ( $this->table_exists( $key ) ) {
				// SECURITY: Use %i placeholder for table identifier (WordPress 6.2+).
				$sql = $this->wpdb->prepare( 'OPTIMIZE TABLE %i', $table_name );

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query is prepared above; OPTIMIZE TABLE on plugin's custom table.
				$result          = $this->wpdb->query( $sql );
				$results[ $key ] = false !== $result;
			}
		}

		return $results;
	}

	/**
	 * Repair database tables.
	 *
	 * @since    1.0.0
	 * @return   array    Repair results.
	 */
	public function repair_tables(): array {
		$results = array();

		foreach ( $this->tables as $key => $table_name ) {
			if ( $this->table_exists( $key ) ) {
				// SECURITY: Use %i placeholder for table identifier (WordPress 6.2+).
				$sql = $this->wpdb->prepare( 'REPAIR TABLE %i', $table_name );

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query is prepared above; REPAIR TABLE on plugin's custom table.
				$result          = $this->wpdb->query( $sql );
				$results[ $key ] = false !== $result;
			}
		}

		return $results;
	}

	/**
	 * Get WordPress database instance.
	 *
	 * @since    1.0.0
	 * @return   wpdb    WordPress database instance.
	 */
	public function get_wpdb(): wpdb {
		return $this->wpdb;
	}

	/**
	 * Get charset collate.
	 *
	 * @since    1.0.0
	 * @return   string    Charset collate.
	 */
	public function get_charset_collate(): string {
		return $this->charset_collate;
	}
}
