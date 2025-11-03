<?php
/**
 * 004 Add Activity Log Table
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/database/migrations/004-add-activity-log-table.php
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
 * Migration 004: Activity Log Table
 *
 * @since 1.0.0
 */
class SCD_Migration_004_Add_Activity_Log_Table implements SCD_Migration_Interface {

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

		$table_name      = $wpdb->prefix . 'scd_activity_log';
		$charset_collate = $wpdb->get_charset_collate();

		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) === $table_name;

		if ( $table_exists ) {
			// Log that table already exists
			if ( function_exists( 'scd_log_info' ) ) {
				scd_log_info(
					'Migration 004: Activity log table already exists',
					array(
						'table' => $table_name,
					)
				);
			}
			return;
		}

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			event_type varchar(50) NOT NULL,
			event_data longtext,
			campaign_id bigint(20) UNSIGNED DEFAULT NULL,
			user_id bigint(20) UNSIGNED DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY event_type (event_type),
			KEY campaign_id (campaign_id),
			KEY user_id (user_id),
			KEY created_at (created_at),
			KEY event_type_created (event_type, created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Verify table was created
		$table_created = $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) === $table_name;

		if ( $table_created ) {
			// Log successful creation
			if ( function_exists( 'scd_log_info' ) ) {
				scd_log_info(
					'Migration 004: Activity log table created successfully',
					array(
						'table' => $table_name,
					)
				);
			}
		} else {
			// Log error
			if ( function_exists( 'scd_log_error' ) ) {
				scd_log_error(
					'Migration 004: Failed to create activity log table',
					array(
						'table' => $table_name,
						'error' => $wpdb->last_error,
					)
				);
			}
		}
	}

	/**
	 * Reverse the migration.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function down(): void {
		global $wpdb;

		$table_name = $wpdb->prefix . 'scd_activity_log';

		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) === $table_name;

		if ( $table_exists ) {
			$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );

			// Log the drop
			if ( function_exists( 'scd_log_info' ) ) {
				scd_log_info(
					'Migration 004 rollback: Activity log table dropped',
					array(
						'table' => $table_name,
					)
				);
			}
		}
	}
}
