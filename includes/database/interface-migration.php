<?php
/**
 * Migration Interface
 *
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/database
 */

declare(strict_types=1);


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Migration Interface.
 *
 * Interface for all database migrations.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/database
 */
interface SCD_Migration_Interface {

    /**
     * Run the migration.
     *
     * @since    1.0.0
     * @return   void
     */
    public function up(): void;

    /**
     * Reverse the migration.
     *
     * @since    1.0.0
     * @return   void
     */
    public function down(): void;
}