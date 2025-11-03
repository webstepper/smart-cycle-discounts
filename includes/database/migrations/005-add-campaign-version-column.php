<?php
/**
 * 005 Add Campaign Version Column
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/database/migrations/005-add-campaign-version-column.php
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Add Campaign Version Column Migration Class
 *
 * Implements optimistic locking by adding version column to campaigns table.
 * Each update increments the version, preventing concurrent modification conflicts.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/database/migrations
 */
class SCD_Migration_005_Add_Campaign_Version_Column {

	/**
	 * Database manager instance.
	 *
	 * @since    1.0.0
	 * @var      SCD_Database_Manager
	 */
	private $db;

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
	 * Adds version column to campaigns table if not exists.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function up(): void {
		global $wpdb;

		$table_name = $this->db->get_table_name( 'campaigns' );

		$column_exists = $wpdb->get_results(
			$wpdb->prepare(
				'SHOW COLUMNS FROM %s LIKE %s',
				$table_name,
				'version'
			)
		);

		if ( ! empty( $column_exists ) ) {
			return; // Column already exists
		}

		// Default to 1 for new records
		// NOT NULL ensures all campaigns have a version
		$wpdb->query(
			"ALTER TABLE {$table_name}
			ADD COLUMN version INT UNSIGNED NOT NULL DEFAULT 1
			AFTER updated_at"
		);

		$wpdb->query(
			"ALTER TABLE {$table_name}
			ADD INDEX idx_campaign_version (id, version)"
		);
	}

	/**
	 * Reverse the migration.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function down(): void {
		global $wpdb;

		$table_name = $this->db->get_table_name( 'campaigns' );

		// Drop index first
		$wpdb->query(
			"ALTER TABLE {$table_name}
			DROP INDEX IF EXISTS idx_campaign_version"
		);

		// Drop version column
		$wpdb->query(
			"ALTER TABLE {$table_name}
			DROP COLUMN IF EXISTS version"
		);
	}
}
