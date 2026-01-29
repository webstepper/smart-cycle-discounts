<?php
/**
 * 002 Add Free Shipping Configuration
 *
 * Adds free_shipping_config column to campaigns table for storing
 * free shipping settings per campaign.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/database/migrations
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Migration to add free shipping configuration column
 *
 * Stores JSON configuration for free shipping:
 * - enabled: boolean - Whether free shipping is enabled for this campaign
 * - methods: string|array - "all" or array of specific shipping method IDs
 *
 * Example: {"enabled": true, "methods": "all"}
 * Example: {"enabled": true, "methods": ["flat_rate:1", "flat_rate:2"]}
 *
 * @since 1.2.0
 */
class WSSCD_Migration_002_Add_Free_Shipping implements WSSCD_Migration_Interface {

	/**
	 * Database manager instance.
	 *
	 * @since  1.2.0
	 * @access private
	 * @var    WSSCD_Database_Manager $db Database manager.
	 */
	private WSSCD_Database_Manager $db;

	/**
	 * Initialize the migration.
	 *
	 * @since 1.2.0
	 * @param WSSCD_Database_Manager $db Database manager.
	 */
	public function __construct( WSSCD_Database_Manager $db ) {
		$this->db = $db;
	}

	/**
	 * Run the migration.
	 *
	 * Adds free_shipping_config column to campaigns table.
	 *
	 * @since  1.2.0
	 * @return void
	 */
	public function up(): void {
		global $wpdb;

		$table_name = $this->db->get_table_name( 'campaigns' );

		// Check if column already exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Migration check, not cached.
		$column_exists = $wpdb->get_results(
			$wpdb->prepare(
				"SHOW COLUMNS FROM {$table_name} LIKE %s",
				'free_shipping_config'
			)
		);

		if ( empty( $column_exists ) ) {
			// Add the column after discount_rules for logical grouping.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Migration ALTER TABLE.
			$wpdb->query(
				"ALTER TABLE {$table_name}
				ADD COLUMN free_shipping_config LONGTEXT DEFAULT NULL
				COMMENT 'JSON configuration for free shipping: {enabled: bool, methods: \"all\"|array}'
				AFTER discount_rules"
			);
		}
	}

	/**
	 * Reverse the migration.
	 *
	 * Removes free_shipping_config column from campaigns table.
	 *
	 * @since  1.2.0
	 * @return void
	 */
	public function down(): void {
		global $wpdb;

		$table_name = $this->db->get_table_name( 'campaigns' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Migration ALTER TABLE.
		$wpdb->query(
			"ALTER TABLE {$table_name} DROP COLUMN IF EXISTS free_shipping_config"
		);
	}
}
