<?php
/**
 * 006 Add Foreign Keys Indexes
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/database/migrations/006-add-foreign-keys-indexes.php
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

/**
 * Migration 006: Add Foreign Keys and Performance Indexes
 *
 * @since 1.0.0
 */
class SCD_Migration_006_Add_Foreign_Keys_Indexes implements SCD_Migration_Interface {

	/**
	 * Database manager instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Database_Manager    $db    Database manager.
	 */
	private SCD_Database_Manager $db;

	/**
	 * Initialize the migration.
	 *
	 * @since    1.0.0
	 * @param    SCD_Database_Manager $db    Database manager.
	 */
	public function __construct( SCD_Database_Manager $db ) {
		$this->db = $db;
	}

	/**
	 * Run the migration.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function up(): void {
		global $wpdb;

		// Add indexes first (required before foreign keys)
		$this->add_performance_indexes( $wpdb );

		// Add foreign key constraints
		// Note: InnoDB engine required for FK constraints
		$this->add_foreign_key_constraints( $wpdb );
	}

	/**
	 * Add performance indexes on frequently queried columns.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    object $wpdb    WordPress database object.
	 * @return   void
	 */
	private function add_performance_indexes( $wpdb ): void {
		$prefix = $wpdb->prefix;

		// Index on analytics.date_recorded for time-range queries
		$index_name = 'idx_date_recorded';
		if ( ! $this->index_exists( $wpdb, "{$prefix}scd_analytics", $index_name ) ) {
			$wpdb->query(
				"ALTER TABLE {$prefix}scd_analytics
				ADD INDEX {$index_name} (date_recorded)"
			);
		}

		// Index on customer_usage.campaign_id for FK and queries
		$index_name = 'idx_campaign_id';
		if ( ! $this->index_exists( $wpdb, "{$prefix}scd_customer_usage", $index_name ) ) {
			$wpdb->query(
				"ALTER TABLE {$prefix}scd_customer_usage
				ADD INDEX {$index_name} (campaign_id)"
			);
		}

		// Index on customer_usage.customer_id for queries
		$index_name = 'idx_customer_id';
		if ( ! $this->index_exists( $wpdb, "{$prefix}scd_customer_usage", $index_name ) ) {
			$wpdb->query(
				"ALTER TABLE {$prefix}scd_customer_usage
				ADD INDEX {$index_name} (customer_id)"
			);
		}

		// Index on active_discounts.campaign_id for FK and queries
		$index_name = 'idx_campaign_id';
		if ( ! $this->index_exists( $wpdb, "{$prefix}scd_active_discounts", $index_name ) ) {
			$wpdb->query(
				"ALTER TABLE {$prefix}scd_active_discounts
				ADD INDEX {$index_name} (campaign_id)"
			);
		}

		// Index on active_discounts.product_id for product queries
		$index_name = 'idx_product_id';
		if ( ! $this->index_exists( $wpdb, "{$prefix}scd_active_discounts", $index_name ) ) {
			$wpdb->query(
				"ALTER TABLE {$prefix}scd_active_discounts
				ADD INDEX {$index_name} (product_id)"
			);
		}

		// Index on active_discounts.valid_from and valid_until for time-based queries
		$index_name = 'idx_validity_period';
		if ( ! $this->index_exists( $wpdb, "{$prefix}scd_active_discounts", $index_name ) ) {
			$wpdb->query(
				"ALTER TABLE {$prefix}scd_active_discounts
				ADD INDEX {$index_name} (valid_from, valid_until)"
			);
		}

		// Index on analytics.campaign_id for FK and queries
		$index_name = 'idx_campaign_id';
		if ( ! $this->index_exists( $wpdb, "{$prefix}scd_analytics", $index_name ) ) {
			$wpdb->query(
				"ALTER TABLE {$prefix}scd_analytics
				ADD INDEX {$index_name} (campaign_id)"
			);
		}

		// Index on campaign_recurring.campaign_id for FK
		$index_name = 'idx_campaign_id';
		if ( ! $this->index_exists( $wpdb, "{$prefix}scd_campaign_recurring", $index_name ) ) {
			$wpdb->query(
				"ALTER TABLE {$prefix}scd_campaign_recurring
				ADD INDEX {$index_name} (campaign_id)"
			);
		}

		// Index on campaigns.status for status-based queries
		$index_name = 'idx_status';
		if ( ! $this->index_exists( $wpdb, "{$prefix}scd_campaigns", $index_name ) ) {
			$wpdb->query(
				"ALTER TABLE {$prefix}scd_campaigns
				ADD INDEX {$index_name} (status)"
			);
		}

		// Index on campaigns schedule columns for time-based queries
		$index_name = 'idx_schedule';
		if ( ! $this->index_exists( $wpdb, "{$prefix}scd_campaigns", $index_name ) ) {
			$wpdb->query(
				"ALTER TABLE {$prefix}scd_campaigns
				ADD INDEX {$index_name} (schedule_start, schedule_end)"
			);
		}
	}

	/**
	 * Add foreign key constraints for referential integrity.
	 *
	 * Note: WordPress default table engine is InnoDB which supports FK constraints.
	 * However, we add them carefully to avoid breaking existing data.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    object $wpdb    WordPress database object.
	 * @return   void
	 */
	private function add_foreign_key_constraints( $wpdb ): void {
		$prefix = $wpdb->prefix;

		// FK: active_discounts.campaign_id → campaigns.id
		$fk_name = 'fk_active_discounts_campaign';
		if ( ! $this->foreign_key_exists( $wpdb, "{$prefix}scd_active_discounts", $fk_name ) ) {
			// Clean up orphaned records first
			$wpdb->query(
				"DELETE FROM {$prefix}scd_active_discounts
				WHERE campaign_id NOT IN (SELECT id FROM {$prefix}scd_campaigns)"
			);

			// Add FK constraint with CASCADE delete
			$wpdb->query(
				"ALTER TABLE {$prefix}scd_active_discounts
				ADD CONSTRAINT {$fk_name}
				FOREIGN KEY (campaign_id)
				REFERENCES {$prefix}scd_campaigns(id)
				ON DELETE CASCADE
				ON UPDATE CASCADE"
			);
		}

		// FK: analytics.campaign_id → campaigns.id
		$fk_name = 'fk_analytics_campaign';
		if ( ! $this->foreign_key_exists( $wpdb, "{$prefix}scd_analytics", $fk_name ) ) {
			// Clean up orphaned records first
			$wpdb->query(
				"DELETE FROM {$prefix}scd_analytics
				WHERE campaign_id NOT IN (SELECT id FROM {$prefix}scd_campaigns)"
			);

			// Add FK constraint with CASCADE delete
			$wpdb->query(
				"ALTER TABLE {$prefix}scd_analytics
				ADD CONSTRAINT {$fk_name}
				FOREIGN KEY (campaign_id)
				REFERENCES {$prefix}scd_campaigns(id)
				ON DELETE CASCADE
				ON UPDATE CASCADE"
			);
		}

		// FK: customer_usage.campaign_id → campaigns.id
		$fk_name = 'fk_customer_usage_campaign';
		if ( ! $this->foreign_key_exists( $wpdb, "{$prefix}scd_customer_usage", $fk_name ) ) {
			// Clean up orphaned records first
			$wpdb->query(
				"DELETE FROM {$prefix}scd_customer_usage
				WHERE campaign_id NOT IN (SELECT id FROM {$prefix}scd_campaigns)"
			);

			// Add FK constraint with CASCADE delete
			$wpdb->query(
				"ALTER TABLE {$prefix}scd_customer_usage
				ADD CONSTRAINT {$fk_name}
				FOREIGN KEY (campaign_id)
				REFERENCES {$prefix}scd_campaigns(id)
				ON DELETE CASCADE
				ON UPDATE CASCADE"
			);
		}

		// FK: campaign_recurring.campaign_id → campaigns.id
		$fk_name = 'fk_campaign_recurring_campaign';
		if ( ! $this->foreign_key_exists( $wpdb, "{$prefix}scd_campaign_recurring", $fk_name ) ) {
			// Clean up orphaned records first
			$wpdb->query(
				"DELETE FROM {$prefix}scd_campaign_recurring
				WHERE campaign_id NOT IN (SELECT id FROM {$prefix}scd_campaigns)"
			);

			// Add FK constraint with CASCADE delete
			$wpdb->query(
				"ALTER TABLE {$prefix}scd_campaign_recurring
				ADD CONSTRAINT {$fk_name}
				FOREIGN KEY (campaign_id)
				REFERENCES {$prefix}scd_campaigns(id)
				ON DELETE CASCADE
				ON UPDATE CASCADE"
			);
		}
	}

	/**
	 * Check if an index exists on a table.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    object $wpdb         WordPress database object.
	 * @param    string $table_name   Table name (with prefix).
	 * @param    string $index_name   Index name.
	 * @return   bool      True if index exists, false otherwise.
	 */
	private function index_exists( $wpdb, string $table_name, string $index_name ): bool {
		$query = $wpdb->prepare(
			"SHOW INDEX FROM {$table_name} WHERE Key_name = %s",
			$index_name
		);

		$result = $wpdb->get_results( $query );

		return ! empty( $result );
	}

	/**
	 * Check if a foreign key exists on a table.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    object $wpdb         WordPress database object.
	 * @param    string $table_name   Table name (with prefix).
	 * @param    string $fk_name      Foreign key constraint name.
	 * @return   bool      True if FK exists, false otherwise.
	 */
	private function foreign_key_exists( $wpdb, string $table_name, string $fk_name ): bool {
		$query = $wpdb->prepare(
			"SELECT CONSTRAINT_NAME
			FROM information_schema.TABLE_CONSTRAINTS
			WHERE TABLE_SCHEMA = %s
			AND TABLE_NAME = %s
			AND CONSTRAINT_NAME = %s
			AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
			DB_NAME,
			$table_name,
			$fk_name
		);

		$result = $wpdb->get_results( $query );

		return ! empty( $result );
	}

	/**
	 * Rollback the migration.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function down(): void {
		global $wpdb;
		$prefix = $wpdb->prefix;

		// Drop foreign key constraints
		$this->drop_fk_if_exists( $wpdb, "{$prefix}scd_active_discounts", 'fk_active_discounts_campaign' );
		$this->drop_fk_if_exists( $wpdb, "{$prefix}scd_analytics", 'fk_analytics_campaign' );
		$this->drop_fk_if_exists( $wpdb, "{$prefix}scd_customer_usage", 'fk_customer_usage_campaign' );
		$this->drop_fk_if_exists( $wpdb, "{$prefix}scd_campaign_recurring", 'fk_campaign_recurring_campaign' );

		// Drop indexes
		$this->drop_index_if_exists( $wpdb, "{$prefix}scd_analytics", 'idx_recorded_at' );
		$this->drop_index_if_exists( $wpdb, "{$prefix}scd_analytics", 'idx_campaign_id' );
		$this->drop_index_if_exists( $wpdb, "{$prefix}scd_customer_usage", 'idx_campaign_id' );
		$this->drop_index_if_exists( $wpdb, "{$prefix}scd_customer_usage", 'idx_customer_id' );
		$this->drop_index_if_exists( $wpdb, "{$prefix}scd_active_discounts", 'idx_campaign_id' );
		$this->drop_index_if_exists( $wpdb, "{$prefix}scd_active_discounts", 'idx_product_id' );
		$this->drop_index_if_exists( $wpdb, "{$prefix}scd_active_discounts", 'idx_validity_period' );
		$this->drop_index_if_exists( $wpdb, "{$prefix}scd_campaign_recurring", 'idx_campaign_id' );
		$this->drop_index_if_exists( $wpdb, "{$prefix}scd_campaigns", 'idx_status' );
		$this->drop_index_if_exists( $wpdb, "{$prefix}scd_campaigns", 'idx_schedule' );
	}

	/**
	 * Drop foreign key if it exists.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    object $wpdb         WordPress database object.
	 * @param    string $table_name   Table name (with prefix).
	 * @param    string $fk_name      Foreign key name.
	 * @return   void
	 */
	private function drop_fk_if_exists( $wpdb, string $table_name, string $fk_name ): void {
		if ( $this->foreign_key_exists( $wpdb, $table_name, $fk_name ) ) {
			$wpdb->query( "ALTER TABLE {$table_name} DROP FOREIGN KEY {$fk_name}" );
		}
	}

	/**
	 * Drop index if it exists.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    object $wpdb         WordPress database object.
	 * @param    string $table_name   Table name (with prefix).
	 * @param    string $index_name   Index name.
	 * @return   void
	 */
	private function drop_index_if_exists( $wpdb, string $table_name, string $index_name ): void {
		if ( $this->index_exists( $wpdb, $table_name, $index_name ) ) {
			$wpdb->query( "ALTER TABLE {$table_name} DROP INDEX {$index_name}" );
		}
	}

	/**
	 * Get migration version.
	 *
	 * @since    1.0.0
	 * @return   string    Migration version.
	 */
	public function get_version(): string {
		return '006';
	}

	/**
	 * Get migration description.
	 *
	 * @since    1.0.0
	 * @return   string    Migration description.
	 */
	public function get_description(): string {
		return 'Add foreign key constraints and performance indexes';
	}
}
