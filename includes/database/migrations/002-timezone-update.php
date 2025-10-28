<?php
/**
 * Database Migration 002: Timezone Update
 *
 * EC-034 FIX: Update existing campaigns to have proper timezone values.
 * EC-001, EC-028 FIX: Ensure campaigns remember their creation timezone.
 *
 * This migration populates the timezone column for campaigns that have NULL values,
 * using the WordPress site timezone at the time this migration runs.
 *
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/database/migrations
 */

declare(strict_types=1);


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Migration 002: Timezone Update
 *
 * @since 1.0.0
 */
class SCD_Migration_002_Timezone_Update implements SCD_Migration_Interface {

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

		$table_name = $this->db->get_table_name( 'campaigns' );

		// EC-034 FIX: Get WordPress timezone as default for existing campaigns
		$site_timezone = wp_timezone_string();

		// Validate timezone
		require_once SCD_INCLUDES_DIR . 'utilities/class-time-helpers.php';
		$canonical_timezone = scd_get_canonical_timezone();

		// Count campaigns with NULL timezone
		$null_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE timezone IS NULL OR timezone = %s",
				''
			)
		);

		if ( $null_count > 0 ) {
			// Update campaigns with NULL or empty timezone
			$updated = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table_name}
					SET timezone = %s,
					    updated_at = %s
					WHERE timezone IS NULL OR timezone = %s",
					$canonical_timezone,
					current_time( 'mysql' ),
					''
				)
			);

			if ( false !== $updated ) {
				// Log the migration
				if ( function_exists( 'scd_log_info' ) ) {
					scd_log_info(
						'Migration 002: Updated campaign timezones',
						array(
							'campaigns_updated' => $updated,
							'timezone'          => $canonical_timezone,
						)
					);
				}
			} else {
				throw new Exception( 'Failed to update campaign timezones' );
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
		// This migration cannot be reversed as we don't know the original values
		// Just log that rollback was requested
		if ( function_exists( 'scd_log_info' ) ) {
			scd_log_info( 'Migration 002 rollback skipped - timezone data cannot be restored' );
		}
	}
}
