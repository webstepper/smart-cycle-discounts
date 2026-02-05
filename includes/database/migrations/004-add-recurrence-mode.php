<?php
/**
 * 004 Add Recurrence Mode
 *
 * Adds recurrence_mode column to campaign_recurring table for
 * supporting continuous vs instances mode for recurring campaigns.
 *
 * Continuous Mode: Campaign activates/deactivates based on schedule (no instances created)
 * Instances Mode: Creates separate campaign instances for each occurrence (existing behavior)
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/database/migrations
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress/plugins/smart-cycle-discounts/
 * @since      1.3.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Migration to add recurrence mode column
 *
 * Enables two operational modes for recurring campaigns:
 * - continuous: Same campaign toggles active/inactive based on time windows
 * - instances: Creates new campaign instance for each occurrence (default for backwards compatibility)
 *
 * @since 1.3.1
 */
class WSSCD_Migration_004_Add_Recurrence_Mode implements WSSCD_Migration_Interface {

	/**
	 * Database manager instance.
	 *
	 * @since  1.3.1
	 * @access private
	 * @var    WSSCD_Database_Manager $db Database manager.
	 */
	private WSSCD_Database_Manager $db;

	/**
	 * Initialize the migration.
	 *
	 * @since 1.3.1
	 * @param WSSCD_Database_Manager $db Database manager.
	 */
	public function __construct( WSSCD_Database_Manager $db ) {
		$this->db = $db;
	}

	/**
	 * Run the migration.
	 *
	 * Adds recurrence_mode column to campaign_recurring table.
	 *
	 * @since  1.3.1
	 * @return void
	 */
	public function up(): void {
		global $wpdb;

		$table_name = $this->db->get_table_name( 'campaign_recurring' );

		// Check if recurrence_mode column already exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Migration check; table name from trusted source.
		$column_exists = $wpdb->get_results(
			$wpdb->prepare(
				"SHOW COLUMNS FROM {$table_name} LIKE %s",
				'recurrence_mode'
			)
		);

		if ( empty( $column_exists ) ) {
			// Add recurrence_mode column.
			// Default to 'continuous' for new campaigns, but existing campaigns keep instances behavior.
			// Using VARCHAR for broader MySQL compatibility.
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Migration ALTER TABLE; table name from trusted source.
			$alter_ok = $wpdb->query(
				"ALTER TABLE {$table_name}
				ADD COLUMN recurrence_mode VARCHAR(20) NOT NULL DEFAULT 'continuous'
				COMMENT 'Recurrence mode: continuous (toggle active/inactive) or instances (create campaign copies)'
				AFTER recurrence_end_date"
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( false === $alter_ok && ! empty( $wpdb->last_error ) ) {
				throw new Exception( '004 add recurrence_mode: ' . $wpdb->last_error );
			}

			// Update existing recurring campaigns to use 'instances' mode for backwards compatibility.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Migration data update; table name from trusted source.
			$update_ok = $wpdb->query(
				"UPDATE {$table_name} SET recurrence_mode = 'instances' WHERE id > 0"
			);
			if ( false === $update_ok && ! empty( $wpdb->last_error ) ) {
				throw new Exception( '004 update recurrence_mode: ' . $wpdb->last_error );
			}
		}
	}

	/**
	 * Reverse the migration.
	 *
	 * Removes recurrence_mode column from campaign_recurring table.
	 *
	 * @since  1.3.1
	 * @return void
	 */
	public function down(): void {
		global $wpdb;

		$table_name = $this->db->get_table_name( 'campaign_recurring' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Migration ALTER TABLE; table name from trusted source.
		$wpdb->query(
			"ALTER TABLE {$table_name} DROP COLUMN IF EXISTS recurrence_mode"
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
}
