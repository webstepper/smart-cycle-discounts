<?php
/**
 * 003 Float To Decimal
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/database/migrations/003-float-to-decimal.php
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
 * Migration 003: FLOAT to DECIMAL Conversion
 *
 * @since 1.0.0
 */
class SCD_Migration_003_Float_To_Decimal implements SCD_Migration_Interface {

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

		$columns_converted = 0;

		// Define tables and their currency columns that must be DECIMAL
		$tables_to_check = array(
			'scd_campaigns'        => array(
				'discount_value'    => 'DECIMAL(10,4)',
				'revenue_generated' => 'DECIMAL(15,4)',
				'conversion_rate'   => 'DECIMAL(5,2)',
			),
			'scd_active_discounts' => array(
				'original_price'      => 'DECIMAL(15,4)',
				'discounted_price'    => 'DECIMAL(15,4)',
				'discount_amount'     => 'DECIMAL(15,4)',
				'discount_percentage' => 'DECIMAL(5,2)',
				'revenue_generated'   => 'DECIMAL(15,4)',
			),
			'scd_analytics'        => array(
				'revenue'         => 'DECIMAL(15,4)',
				'discount_amount' => 'DECIMAL(15,4)',
				'cart_total'      => 'DECIMAL(15,4)',
			),
			'scd_customer_usage'   => array(
				'total_discount_amount' => 'DECIMAL(15,4)',
				'total_order_value'     => 'DECIMAL(15,4)',
			),
		);

		foreach ( $tables_to_check as $table_short_name => $columns ) {
			$table_name = $wpdb->prefix . $table_short_name;

			$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) === $table_name;

			if ( ! $table_exists ) {
				continue; // Skip if table doesn't exist
			}

			$table_columns = $wpdb->get_results( "SHOW COLUMNS FROM {$table_name}", ARRAY_A );

			foreach ( $columns as $column_name => $desired_type ) {
				// Find column in table
				$column_info = null;
				foreach ( $table_columns as $col ) {
					if ( strtolower( $col['Field'] ) === strtolower( $column_name ) ) {
						$column_info = $col;
						break;
					}
				}

				if ( ! $column_info ) {
					continue; // Column doesn't exist, skip
				}

				$current_type = strtoupper( $column_info['Type'] );

				// CRITICAL: Check if column is FLOAT or DOUBLE (both cause precision errors)
				if ( strpos( $current_type, 'FLOAT' ) !== false || strpos( $current_type, 'DOUBLE' ) !== false ) {
					// Convert FLOAT/DOUBLE to DECIMAL
					$null_clause    = ( $column_info['Null'] === 'YES' ) ? 'NULL' : 'NOT NULL';
					$default_clause = '';

					if ( $column_info['Default'] !== null ) {
						$default_clause = "DEFAULT {$column_info['Default']}";
					} elseif ( $column_info['Null'] === 'YES' ) {
						$default_clause = 'DEFAULT NULL';
					}

					$alter_sql = "ALTER TABLE {$table_name}
						MODIFY COLUMN {$column_name} {$desired_type} {$null_clause} {$default_clause}";

					$converted = $wpdb->query( $alter_sql );

					if ( false !== $converted ) {
						++$columns_converted;

						// Log the conversion
						if ( function_exists( 'scd_log_info' ) ) {
							scd_log_info(
								'Migration 003: Converted FLOAT to DECIMAL',
								array(
									'table'     => $table_short_name,
									'column'    => $column_name,
									'from_type' => $current_type,
									'to_type'   => $desired_type,
								)
							);
						}
					}
				}
			}
		}

		// Log summary
		if ( function_exists( 'scd_log_info' ) ) {
			scd_log_info(
				'Migration 003 completed',
				array(
					'columns_converted' => $columns_converted,
				)
			);
		}
	}

	/**
	 * Reverse the migration.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function down(): void {
		// Cannot reverse this migration - would lose precision
		// Just log that rollback was requested
		if ( function_exists( 'scd_log_info' ) ) {
			scd_log_info( 'Migration 003 rollback skipped - DECIMAL to FLOAT conversion not supported (would lose precision)' );
		}
	}
}
