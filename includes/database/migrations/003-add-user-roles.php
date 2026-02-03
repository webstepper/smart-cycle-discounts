<?php
/**
 * 003 Add User Role Targeting
 *
 * Adds user_roles and user_roles_mode columns to campaigns table for
 * targeting discounts to specific WordPress user roles.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/database/migrations
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Migration to add user role targeting columns
 *
 * Stores user role restrictions for campaigns:
 * - user_roles: JSON array of role slugs (e.g., ["wholesaler", "subscriber"])
 * - user_roles_mode: How to apply roles ("all", "include", "exclude")
 *
 * Example: user_roles=["wholesaler","distributor"], user_roles_mode="include"
 * Result: Only wholesalers and distributors see the discount
 *
 * @since 1.3.0
 */
class WSSCD_Migration_003_Add_User_Roles implements WSSCD_Migration_Interface {

	/**
	 * Database manager instance.
	 *
	 * @since  1.3.0
	 * @access private
	 * @var    WSSCD_Database_Manager $db Database manager.
	 */
	private WSSCD_Database_Manager $db;

	/**
	 * Initialize the migration.
	 *
	 * @since 1.3.0
	 * @param WSSCD_Database_Manager $db Database manager.
	 */
	public function __construct( WSSCD_Database_Manager $db ) {
		$this->db = $db;
	}

	/**
	 * Run the migration.
	 *
	 * Adds user_roles and user_roles_mode columns to campaigns table.
	 *
	 * @since  1.3.0
	 * @return void
	 */
	public function up(): void {
		global $wpdb;

		$table_name = $this->db->get_table_name( 'campaigns' );

		// Check if user_roles column already exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Migration check; table name from trusted source.
		$user_roles_exists = $wpdb->get_results(
			$wpdb->prepare(
				"SHOW COLUMNS FROM {$table_name} LIKE %s",
				'user_roles'
			)
		);

		if ( empty( $user_roles_exists ) ) {
			// Add user_roles column - stores JSON array of role slugs.
			// Using LONGTEXT for broader MySQL compatibility (JSON type requires MySQL 5.7.8+).
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Migration ALTER TABLE; table name from trusted source.
			$alter_ok = $wpdb->query(
				"ALTER TABLE {$table_name}
				ADD COLUMN user_roles LONGTEXT DEFAULT NULL
				COMMENT 'JSON array of role slugs for targeting'
				AFTER free_shipping_config"
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( false === $alter_ok && ! empty( $wpdb->last_error ) ) {
				throw new Exception( '003 add user_roles: ' . $wpdb->last_error );
			}
		}

		// Check if user_roles_mode column already exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Migration check; table name from trusted source.
		$user_roles_mode_exists = $wpdb->get_results(
			$wpdb->prepare(
				"SHOW COLUMNS FROM {$table_name} LIKE %s",
				'user_roles_mode'
			)
		);

		if ( empty( $user_roles_mode_exists ) ) {
			// Add user_roles_mode column - determines how roles are applied.
			// Using VARCHAR for broader compatibility instead of ENUM.
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Migration ALTER TABLE; table name from trusted source.
			$alter_ok = $wpdb->query(
				"ALTER TABLE {$table_name}
				ADD COLUMN user_roles_mode VARCHAR(10) DEFAULT 'all'
				COMMENT 'How to apply roles: all, include, exclude'
				AFTER user_roles"
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( false === $alter_ok && ! empty( $wpdb->last_error ) ) {
				throw new Exception( '003 add user_roles_mode: ' . $wpdb->last_error );
			}
		}
	}

	/**
	 * Reverse the migration.
	 *
	 * Removes user_roles and user_roles_mode columns from campaigns table.
	 *
	 * @since  1.3.0
	 * @return void
	 */
	public function down(): void {
		global $wpdb;

		$table_name = $this->db->get_table_name( 'campaigns' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Migration ALTER TABLE; table name from trusted source.
		$wpdb->query(
			"ALTER TABLE {$table_name} DROP COLUMN IF EXISTS user_roles"
		);
		$wpdb->query(
			"ALTER TABLE {$table_name} DROP COLUMN IF EXISTS user_roles_mode"
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
}
