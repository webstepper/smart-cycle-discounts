<?php
/**
 * Datetime Builder Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/utilities/class-datetime-builder.php
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * DateTime Builder Class
 *
 * Value Object for safely combining date and time components into a DateTime object.
 * Handles validation, timezone conversion, and DST edge cases.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/utilities
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_DateTime_Builder {

	/**
	 * Date string (Y-m-d format).
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $date    Date string.
	 */
	private string $date;

	/**
	 * Time string (H:i or H:i:s format).
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $time    Time string.
	 */
	private string $time;

	/**
	 * Timezone identifier.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $timezone    Timezone identifier.
	 */
	private string $timezone;

	/**
	 * Validation errors.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $errors    Validation errors.
	 */
	private array $errors = array();

	/**
	 * Private constructor (use static factory methods).
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function __construct() {
		// Private - use static factory methods
	}

	/**
	 * Create from user input.
	 *
	 * @since    1.0.0
	 * @param    string $date        Date string (Y-m-d).
	 * @param    string $time        Time string (H:i or H:i:s).
	 * @param    string $timezone    Timezone identifier.
	 * @return   self                   Builder instance.
	 */
	public static function from_user_input( string $date, string $time, string $timezone ): self {
		$builder           = new self();
		$builder->date     = $date;
		$builder->time     = $time;
		$builder->timezone = $timezone;
		return $builder;
	}

	/**
	 * Validate all components.
	 *
	 * @since    1.0.0
	 * @return   bool    True if valid, false otherwise.
	 */
	public function validate(): bool {
		$this->errors = array();

		// Validate date
		if ( ! $this->validate_date( $this->date ) ) {
			$this->errors['date'] = __( 'Invalid date format. Expected YYYY-MM-DD.', 'smart-cycle-discounts' );
		}

		// Validate time
		if ( ! $this->validate_time( $this->time ) ) {
			$this->errors['time'] = __( 'Invalid time format. Expected HH:MM.', 'smart-cycle-discounts' );
		}

		// Validate timezone
		$canonical_tz = scd_validate_timezone( $this->timezone );
		if ( false === $canonical_tz ) {
			$this->errors['timezone'] = __( 'Invalid timezone identifier.', 'smart-cycle-discounts' );
		} else {
			$this->timezone = $canonical_tz; // Use canonical version
		}

		return empty( $this->errors );
	}

	/**
	 * Build DateTimeImmutable object.
	 *
	 * @since    1.0.0
	 * @return   DateTimeImmutable             DateTimeImmutable object in specified timezone.
	 * @throws   InvalidArgumentException      If validation fails.
	 */
	public function build(): DateTimeImmutable {
		if ( ! $this->validate() ) {
			throw new InvalidArgumentException(
				'Cannot build datetime: ' . implode( ', ', $this->errors )
			);
		}

		// Use helper function with DST handling
		$datetime = scd_combine_date_time(
			$this->date,
			$this->time,
			$this->timezone
		);

		if ( false === $datetime ) {
			throw new InvalidArgumentException(
				__( 'Failed to create datetime. The date/time may fall in a DST gap or be otherwise invalid.', 'smart-cycle-discounts' )
			);
		}

		return $datetime;
	}

	/**
	 * Build and convert to UTC.
	 *
	 * @since    1.0.0
	 * @return   DateTimeImmutable    DateTimeImmutable object in UTC timezone.
	 * @throws   InvalidArgumentException    If validation fails.
	 */
	public function build_utc(): DateTimeImmutable {
		$datetime = $this->build();
		return $datetime->setTimezone( new DateTimeZone( 'UTC' ) );
	}

	/**
	 * Get datetime formatted for MySQL storage.
	 *
	 * @since    1.0.0
	 * @return   string                      MySQL datetime string (UTC).
	 * @throws   InvalidArgumentException    If validation fails.
	 */
	public function to_mysql(): string {
		return $this->build_utc()->format( 'Y-m-d H:i:s' );
	}

	/**
	 * Get validation errors.
	 *
	 * @since    1.0.0
	 * @return   array    Validation errors array.
	 */
	public function get_errors(): array {
		return $this->errors;
	}

	/**
	 * Validate date format.
	 *
	 * Ensures date is in Y-m-d format and is a valid date.
	 * Rejects invalid dates like 2024-02-30.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $date    Date string to validate.
	 * @return   bool               True if valid, false otherwise.
	 */
	private function validate_date( string $date ): bool {
		// Parse with strict validation
		$dt = DateTime::createFromFormat( '!Y-m-d', $date );

		// Check format matches exactly (prevents date rollover)
		if ( ! $dt || $dt->format( 'Y-m-d' ) !== $date ) {
			return false;
		}

		return true;
	}

	/**
	 * Validate time format.
	 *
	 * Accepts HH:MM or HH:MM:SS format.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $time    Time string to validate.
	 * @return   bool               True if valid, false otherwise.
	 */
	private function validate_time( string $time ): bool {
		// Use existing helper function
		return false !== scd_parse_time_string( $time );
	}
}
