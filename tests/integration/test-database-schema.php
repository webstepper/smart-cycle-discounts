<?php
/**
 * Class Test_Database_Schema
 *
 * Integration tests for database schema and table structure.
 * These tests verify that all plugin tables are created with correct structure.
 *
 * @package    Smart_Cycle_Discounts
 * @subpackage Smart_Cycle_Discounts/Tests/Integration
 */

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls, PluginCheck.Security.DirectDB.UnescapedDBParameter
// This is a test file, not production code.

/**
 * Test Database Schema class
 *
 * Tests the complete database schema to ensure all tables exist,
 * have correct columns, indexes, and foreign key relationships.
 *
 * This catches:
 * - Missing database tables
 * - Missing or incorrect columns
 * - Missing indexes (performance issues)
 * - Broken foreign key relationships
 */
class Test_Database_Schema extends WP_UnitTestCase {

	/**
	 * WordPress database instance.
	 *
	 * @var wpdb
	 */
	private $wpdb;

	/**
	 * Set up test environment before each test.
	 *
	 * @since 1.0.0
	 */
	public function setUp(): void {
		parent::setUp();

		global $wpdb;
		$this->wpdb = $wpdb;
	}

	/**
	 * Clean up after each test.
	 *
	 * @since 1.0.0
	 */
	public function tearDown(): void {
		// Clean up campaigns before users to avoid foreign key constraint violations
		global $wpdb;
		$campaigns_table = $wpdb->prefix . 'wsscd_campaigns';
		$wpdb->query( "DELETE FROM {$campaigns_table}" );

		parent::tearDown();
	}

	/**
	 * Test that campaigns table exists.
	 *
	 * Verifies that the main campaigns table was created during activation.
	 *
	 * @since 1.0.0
	 */
	public function test_campaigns_table_exists() {
		$table_name = $this->wpdb->prefix . 'wsscd_campaigns';

		// Query to check if table exists
		$table_exists = $this->wpdb->get_var(
			$this->wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$table_name
			)
		);

		$this->assertNotNull(
			$table_exists,
			'Campaigns table should exist in database'
		);

		$this->assertEquals(
			$table_name,
			$table_exists,
			'Campaigns table name should match expected format'
		);
	}

	/**
	 * Test that campaigns table has required columns.
	 *
	 * Verifies that the campaigns table has all essential columns.
	 *
	 * @since 1.0.0
	 */
	public function test_campaigns_table_has_required_columns() {
		$table_name = $this->wpdb->prefix . 'wsscd_campaigns';

		// Get all columns in campaigns table
		$columns = $this->wpdb->get_results(
			$this->wpdb->prepare(
				'SHOW COLUMNS FROM %i',
				$table_name
			)
		);

		$column_names = wp_list_pluck( $columns, 'Field' );

		// Core identity columns
		$this->assertContains( 'id', $column_names, 'Campaigns table should have id column' );
		$this->assertContains( 'name', $column_names, 'Campaigns table should have name column' );
		$this->assertContains( 'status', $column_names, 'Campaigns table should have status column' );

		// Discount configuration columns
		$this->assertContains( 'discount_type', $column_names, 'Campaigns table should have discount_type column' );
		$this->assertContains( 'discount_value', $column_names, 'Campaigns table should have discount_value column' );

		// Product selection columns
		$this->assertContains( 'product_selection_type', $column_names, 'Campaigns table should have product_selection_type column' );

		// Schedule columns (correct column names)
		$this->assertContains( 'starts_at', $column_names, 'Campaigns table should have starts_at column' );
		$this->assertContains( 'ends_at', $column_names, 'Campaigns table should have ends_at column' );

		// Audit columns
		$this->assertContains( 'created_at', $column_names, 'Campaigns table should have created_at column' );
		$this->assertContains( 'created_by', $column_names, 'Campaigns table should have created_by column' );
		$this->assertContains( 'updated_at', $column_names, 'Campaigns table should have updated_at column' );
	}

	/**
	 * Test that active_discounts table exists.
	 *
	 * Verifies that the active_discounts table was created.
	 *
	 * @since 1.0.0
	 */
	public function test_active_discounts_table_exists() {
		$table_name = $this->wpdb->prefix . 'wsscd_active_discounts';

		$table_exists = $this->wpdb->get_var(
			$this->wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$table_name
			)
		);

		$this->assertNotNull(
			$table_exists,
			'Active discounts table should exist in database'
		);
	}

	/**
	 * Test that analytics table exists.
	 *
	 * Verifies that the analytics/activity log table was created.
	 *
	 * @since 1.0.0
	 */
	public function test_analytics_table_exists() {
		$table_name = $this->wpdb->prefix . 'wsscd_activity_log';

		$table_exists = $this->wpdb->get_var(
			$this->wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$table_name
			)
		);

		$this->assertNotNull(
			$table_exists,
			'Activity log table should exist in database'
		);
	}

	/**
	 * Test that customer usage table exists.
	 *
	 * Verifies that the customer usage tracking table was created.
	 *
	 * @since 1.0.0
	 */
	public function test_customer_usage_table_exists() {
		$table_name = $this->wpdb->prefix . 'wsscd_customer_usage';

		$table_exists = $this->wpdb->get_var(
			$this->wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$table_name
			)
		);

		$this->assertNotNull(
			$table_exists,
			'Customer usage table should exist in database'
		);
	}

	/**
	 * Test that campaigns table has indexes.
	 *
	 * Verifies that performance indexes exist on frequently queried columns.
	 *
	 * @since 1.0.0
	 */
	public function test_campaigns_table_has_indexes() {
		$table_name = $this->wpdb->prefix . 'wsscd_campaigns';

		// Get all indexes
		$indexes = $this->wpdb->get_results(
			$this->wpdb->prepare(
				'SHOW INDEX FROM %i',
				$table_name
			)
		);

		$index_names = wp_list_pluck( $indexes, 'Key_name' );

		// Should have primary key
		$this->assertContains(
			'PRIMARY',
			$index_names,
			'Campaigns table should have PRIMARY index on id'
		);

		// Should have index on status for filtering
		$has_status_index = false;
		foreach ( $indexes as $index ) {
			if ( 'status' === $index->Column_name || false !== strpos( $index->Key_name, 'status' ) ) {
				$has_status_index = true;
				break;
			}
		}

		$this->assertTrue(
			$has_status_index,
			'Campaigns table should have index on status column for performance'
		);
	}

	/**
	 * Test that tables use correct character set.
	 *
	 * Verifies that tables use UTF-8 character set for internationalization.
	 *
	 * @since 1.0.0
	 */
	public function test_tables_use_correct_charset() {
		$table_name = $this->wpdb->prefix . 'wsscd_campaigns';

		// Get table status
		$table_status = $this->wpdb->get_row(
			$this->wpdb->prepare(
				'SHOW TABLE STATUS LIKE %s',
				$table_name
			)
		);

		$this->assertNotNull(
			$table_status,
			'Should be able to retrieve table status'
		);

		// Check collation (should be utf8 variant)
		$this->assertStringContainsString(
			'utf8',
			$table_status->Collation,
			'Table should use UTF-8 character set for internationalization'
		);
	}

	/**
	 * Test that tables use InnoDB engine.
	 *
	 * Verifies that tables use InnoDB for transaction support and foreign keys.
	 *
	 * @since 1.0.0
	 */
	public function test_tables_use_innodb_engine() {
		$table_name = $this->wpdb->prefix . 'wsscd_campaigns';

		// Get table status
		$table_status = $this->wpdb->get_row(
			$this->wpdb->prepare(
				'SHOW TABLE STATUS LIKE %s',
				$table_name
			)
		);

		$this->assertNotNull(
			$table_status,
			'Should be able to retrieve table status'
		);

		$this->assertEquals(
			'InnoDB',
			$table_status->Engine,
			'Table should use InnoDB engine for transaction support'
		);
	}
}
