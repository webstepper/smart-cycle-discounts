<?php
/**
 * 002 Add enable_recurring Column Migration
 *
 * Adds the enable_recurring column to wp_scd_campaigns table if it doesn't exist.
 * This migration is idempotent - it checks if the column exists before adding it.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/database/migrations/002-add-enable-recurring-column.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Add enable_recurring Column Migration
 *
 * Ensures the enable_recurring column exists in the campaigns table.
 * This fixes database schema inconsistencies for existing installations.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/database/migrations
 * @author     Webstepper <contact@webstepper.io>
 */
class SCD_Migration_002_Add_Enable_Recurring_Column implements SCD_Migration_Interface {

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
	 * Adds enable_recurring column to campaigns table if it doesn't exist.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function up(): void {
		global $wpdb;

		$table_name = $this->db->get_table_name( 'campaigns' );

		// Check if column exists.
		$column_exists = $wpdb->get_results(
			$wpdb->prepare(
				'SHOW COLUMNS FROM %i LIKE %s',
				$table_name,
				'enable_recurring'
			)
		);

		// Only add if column doesn't exist.
		if ( empty( $column_exists ) ) {
			// Add enable_recurring column after status column.
			$sql = $wpdb->prepare(
				'ALTER TABLE %i ADD COLUMN enable_recurring tinyint(1) NOT NULL DEFAULT 0 AFTER status',
				$table_name
			);

			$wpdb->query( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared

			// Verify the column was added.
			$verify = $wpdb->get_results(
				$wpdb->prepare(
					'SHOW COLUMNS FROM %i LIKE %s',
					$table_name,
					'enable_recurring'
				)
			);

			if ( ! empty( $verify ) ) {
				error_log( '[SCD Migration 002] Successfully added enable_recurring column to campaigns table' );
			} else {
				error_log( '[SCD Migration 002] Failed to add enable_recurring column to campaigns table' );
			}
		} else {
			error_log( '[SCD Migration 002] enable_recurring column already exists, skipping migration' );
		}
	}

	/**
	 * Reverse the migration.
	 *
	 * Removes the enable_recurring column if it exists.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function down(): void {
		global $wpdb;

		$table_name = $this->db->get_table_name( 'campaigns' );

		// Check if column exists.
		$column_exists = $wpdb->get_results(
			$wpdb->prepare(
				'SHOW COLUMNS FROM %i LIKE %s',
				$table_name,
				'enable_recurring'
			)
		);

		// Only remove if column exists.
		if ( ! empty( $column_exists ) ) {
			$sql = $wpdb->prepare(
				'ALTER TABLE %i DROP COLUMN enable_recurring',
				$table_name
			);

			$wpdb->query( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared

			error_log( '[SCD Migration 002] Removed enable_recurring column from campaigns table' );
		}
	}
}
