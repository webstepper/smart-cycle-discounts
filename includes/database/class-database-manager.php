<?php
/**
 * Database Manager Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/database/class-database-manager.php
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
 * Database Manager
 *
 * Manages database operations for the plugin.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/database
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Database_Manager {

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
			'campaigns'          => $this->wpdb->prefix . 'scd_campaigns',
			'active_discounts'   => $this->wpdb->prefix . 'scd_active_discounts',
			'analytics'          => $this->wpdb->prefix . 'scd_analytics',
			'customer_usage'     => $this->wpdb->prefix . 'scd_customer_usage',
			'campaign_recurring' => $this->wpdb->prefix . 'scd_campaign_recurring',
			'migrations'         => $this->wpdb->prefix . 'scd_migrations',
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

		$result = $this->wpdb->get_var(
			$this->wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name )
		);

		return $result === $table_name;
	}

	/**
	 * Execute a query.
	 *
	 * @since    1.0.0
	 * @param    string $query    SQL query.
	 * @return   int|false           Number of rows affected or false on error.
	 */
	public function query( string $query ): int|false {
		$start_time = microtime( true );
		$result     = $this->wpdb->query( $query );
		$duration   = microtime( true ) - $start_time;

		// Debug: Log database query
		if ( function_exists( 'scd_debug_database' ) ) {
			scd_debug_database( 'query', 'custom', array( 'query' => $query ), $result, $duration );
		}

		// wpdb->query can return bool(true) for some queries like CREATE TABLE
		// Convert to int for consistency with return type
		if ( $result === true ) {
			return 0; // Return 0 for successful queries with no rows affected
		}

		return $result;
	}

	/**
	 * Get a single variable.
	 *
	 * @since    1.0.0
	 * @param    string $query    SQL query.
	 * @return   string|null         Variable value or null.
	 */
	public function get_var( string $query ): ?string {
		$result = $this->wpdb->get_var( $query );
		return $result !== null ? (string) $result : null;
	}

	/**
	 * Get a single row.
	 *
	 * @since    1.0.0
	 * @param    string $query      SQL query.
	 * @param    string $output     Output type (OBJECT, ARRAY_A, ARRAY_N).
	 * @return   mixed                 Row data or null.
	 */
	public function get_row( string $query, string $output = OBJECT ): mixed {
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
		$results    = $this->wpdb->get_results( $query, $output );
		$duration   = microtime( true ) - $start_time;

		// Debug: Log select operation
		if ( function_exists( 'scd_debug_database' ) ) {
			$row_count = is_array( $results ) ? count( $results ) : 0;
			scd_debug_database( 'select', 'custom', array( 'query' => $query ), $row_count, $duration );
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
	public function insert( string $table, array $data, array $format = array() ): int|false {
		$table_name = $this->get_table_name( $table );
		if ( empty( $table_name ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[SCD_Database] Insert failed: Invalid table name for ' . $table );
			}
			return false;
		}

		$start_time = microtime( true );
		$result     = $this->wpdb->insert( $table_name, $data, $format );
		$duration   = microtime( true ) - $start_time;

		if ( $result === false ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[SCD_Database] Insert failed: ' . $this->wpdb->last_error );
			}

			// Debug: Log failed insert
			if ( function_exists( 'scd_debug_database' ) ) {
				scd_debug_database( 'insert', $table, $data, false, $duration );
			}

			return false;
		}

		// Debug: Log successful insert
		if ( function_exists( 'scd_debug_database' ) ) {
			scd_debug_database( 'insert', $table, $data, $this->wpdb->insert_id, $duration );
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
	public function update( string $table, array $data, array $where, array $format = array(), array $where_format = array() ): int|false {
		$table_name = $this->get_table_name( $table );
		if ( empty( $table_name ) ) {
			return false;
		}

		$start_time = microtime( true );
		$result     = $this->wpdb->update( $table_name, $data, $where, $format, $where_format );
		$duration   = microtime( true ) - $start_time;

		// Debug: Log update operation
		if ( function_exists( 'scd_debug_database' ) ) {
			$update_data = array_merge( $data, array( 'where' => $where ) );
			scd_debug_database( 'update', $table, $update_data, $result, $duration );
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
	public function delete( string $table, array $where, array $where_format = array() ): int|false {
		$table_name = $this->get_table_name( $table );
		if ( empty( $table_name ) ) {
			return false;
		}

		$start_time = microtime( true );
		$result     = $this->wpdb->delete( $table_name, $where, $where_format );
		$duration   = microtime( true ) - $start_time;

		// Debug: Log delete operation
		if ( function_exists( 'scd_debug_database' ) ) {
			scd_debug_database( 'delete', $table, array( 'where' => $where ), $result, $duration );
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
		return $this->wpdb->prepare( $query, ...$args );
	}

	/**
	 * Start a database transaction.
	 *
	 * @since    1.0.0
	 * @return   bool    True on success, false on failure.
	 */
	public function start_transaction(): bool {
		return $this->wpdb->query( 'START TRANSACTION' ) !== false;
	}

	/**
	 * Commit a database transaction.
	 *
	 * @since    1.0.0
	 * @return   bool    True on success, false on failure.
	 */
	public function commit(): bool {
		return $this->wpdb->query( 'COMMIT' ) !== false;
	}

	/**
	 * Rollback a database transaction.
	 *
	 * @since    1.0.0
	 * @return   bool    True on success, false on failure.
	 */
	public function rollback(): bool {
		return $this->wpdb->query( 'ROLLBACK' ) !== false;
	}

	/**
	 * Execute a callback within a transaction.
	 *
	 * @since    1.0.0
	 * @param    callable $callback    Callback to execute.
	 * @return   mixed                    Callback result or false on error.
	 */
	public function transaction( callable $callback ): mixed {
		if ( ! $this->start_transaction() ) {
			return false;
		}

		try {
			$result = $callback( $this );

			if ( $result === false ) {
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
		$result = $this->wpdb->get_row(
			$this->wpdb->prepare(
				'SELECT (data_length + index_length) as size 
                 FROM information_schema.TABLES 
                 WHERE table_schema = %s AND table_name = %s',
				DB_NAME,
				$table_name
			)
		);

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
		// Validate table name to prevent SQL injection
		if ( ! preg_match( '/^[a-zA-Z0-9_]+$/', $table_name ) ) {
			return 0;
		}

		// Table names cannot be prepared with placeholders
		// We ensure safety by validating the table name above
		$query  = "SELECT COUNT(*) FROM `{$table_name}`";
		$result = $this->wpdb->get_var( $query );

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
				// Validate table name
				if ( preg_match( '/^[a-zA-Z0-9_]+$/', $table_name ) ) {
					$result          = $this->wpdb->query( "OPTIMIZE TABLE `{$table_name}`" );
					$results[ $key ] = $result !== false;
				} else {
					$results[ $key ] = false;
				}
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
				// Validate table name
				if ( preg_match( '/^[a-zA-Z0-9_]+$/', $table_name ) ) {
					$result          = $this->wpdb->query( "REPAIR TABLE `{$table_name}`" );
					$results[ $key ] = $result !== false;
				} else {
					$results[ $key ] = false;
				}
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
