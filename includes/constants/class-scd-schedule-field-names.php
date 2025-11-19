<?php
/**
 * Schedule Field Names Constants
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/constants/class-scd-schedule-field-names.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Schedule Field Names Class
 *
 * Centralized constants for schedule step field names to prevent inconsistencies
 * between frontend and backend. Single source of truth for all recurring campaign
 * field references.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/constants
 * @author     Webstepper <contact@webstepper.io>
 */
class SCD_Schedule_Field_Names {

	/**
	 * Recurring schedule field name constants
	 *
	 * @since 1.0.0
	 */
	const ENABLE_RECURRING    = 'enable_recurring';
	const RECURRENCE_PATTERN  = 'recurrence_pattern';
	const RECURRENCE_INTERVAL = 'recurrence_interval';
	const RECURRENCE_DAYS     = 'recurrence_days';
	const RECURRENCE_END_TYPE = 'recurrence_end_type';
	const RECURRENCE_COUNT    = 'recurrence_count';
	const RECURRENCE_END_DATE = 'recurrence_end_date';

	/**
	 * Get all recurring field names
	 *
	 * @since  1.0.0
	 * @return array Array of field names
	 */
	public static function get_fields(): array {
		return array(
			self::ENABLE_RECURRING,
			self::RECURRENCE_PATTERN,
			self::RECURRENCE_INTERVAL,
			self::RECURRENCE_DAYS,
			self::RECURRENCE_END_TYPE,
			self::RECURRENCE_COUNT,
			self::RECURRENCE_END_DATE,
		);
	}

	/**
	 * Check if a field name is valid recurring field
	 *
	 * @since  1.0.0
	 * @param  string $field_name Field name to validate.
	 * @return bool True if valid recurring field, false otherwise
	 */
	public static function is_valid( string $field_name ): bool {
		return in_array( $field_name, self::get_fields(), true );
	}

	/**
	 * Get recurring fields with default values
	 *
	 * @since  1.0.0
	 * @return array Array of field names with their default values
	 */
	public static function get_defaults(): array {
		return array(
			self::ENABLE_RECURRING    => 0,
			self::RECURRENCE_PATTERN  => 'daily',
			self::RECURRENCE_INTERVAL => 1,
			self::RECURRENCE_DAYS     => array(),
			self::RECURRENCE_END_TYPE => 'never',
			self::RECURRENCE_COUNT    => 10,
			self::RECURRENCE_END_DATE => '',
		);
	}
}
