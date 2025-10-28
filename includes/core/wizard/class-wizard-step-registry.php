<?php
/**
 * Wizard Step Registry
 *
 * Centralized registry for wizard step definitions and navigation.
 * Provides single source of truth for step configuration.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/wizard
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Wizard Step Registry Class
 *
 * Manages wizard step definitions, validation, and navigation logic.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/wizard
 */
class SCD_Wizard_Step_Registry {

	/**
	 * Available wizard steps in order.
	 *
	 * @since    1.0.0
	 * @var      array
	 */
	private static $steps = array( 'basic', 'products', 'discounts', 'schedule', 'review' );

	/**
	 * Step labels for display.
	 *
	 * @since    1.0.0
	 * @var      array
	 */
	private static $step_labels = array(
		'basic'     => 'Basic Info',
		'products'  => 'Products',
		'discounts' => 'Discounts',
		'schedule'  => 'Schedule',
		'review'    => 'Review',
	);

	/**
	 * Get all wizard steps.
	 *
	 * @since    1.0.0
	 * @return   array    Ordered array of step identifiers.
	 */
	public static function get_steps() {
		return self::$steps;
	}

	/**
	 * Get step labels.
	 *
	 * @since    1.0.0
	 * @return   array    Associative array of step => label.
	 */
	public static function get_step_labels() {
		return self::$step_labels;
	}

	/**
	 * Get label for specific step.
	 *
	 * @since    1.0.0
	 * @param    string $step    Step identifier.
	 * @return   string             Step label or empty string.
	 */
	public static function get_step_label( $step ) {
		return isset( self::$step_labels[ $step ] ) ? self::$step_labels[ $step ] : '';
	}

	/**
	 * Check if step is valid.
	 *
	 * @since    1.0.0
	 * @param    string $step    Step identifier to validate.
	 * @return   bool               True if valid step.
	 */
	public static function is_valid_step( $step ) {
		return in_array( $step, self::$steps, true );
	}

	/**
	 * Get next step.
	 *
	 * @since    1.0.0
	 * @param    string $current_step    Current step identifier.
	 * @return   string                     Next step or 'complete' if at end.
	 */
	public static function get_next_step( $current_step ) {
		$current_index = array_search( $current_step, self::$steps, true );

		if ( false === $current_index ) {
			return 'basic';
		}

		// Review is last step - next is completion
		if ( 'review' === $current_step ) {
			return 'complete';
		}

		$next_index = $current_index + 1;
		if ( $next_index < count( self::$steps ) ) {
			return self::$steps[ $next_index ];
		}

		return 'complete';
	}

	/**
	 * Get previous step.
	 *
	 * @since    1.0.0
	 * @param    string $current_step    Current step identifier.
	 * @return   string|null                Previous step or null if at start.
	 */
	public static function get_previous_step( $current_step ) {
		$current_index = array_search( $current_step, self::$steps, true );

		if ( false === $current_index || 0 === $current_index ) {
			return null;
		}

		return self::$steps[ $current_index - 1 ];
	}

	/**
	 * Get step index.
	 *
	 * @since    1.0.0
	 * @param    string $step    Step identifier.
	 * @return   int|false          Zero-based index or false if not found.
	 */
	public static function get_step_index( $step ) {
		return array_search( $step, self::$steps, true );
	}

	/**
	 * Get total number of steps.
	 *
	 * @since    1.0.0
	 * @return   int    Total step count.
	 */
	public static function get_step_count() {
		return count( self::$steps );
	}

	/**
	 * Get first step.
	 *
	 * @since    1.0.0
	 * @return   string    First step identifier.
	 */
	public static function get_first_step() {
		return self::$steps[0];
	}

	/**
	 * Get last step.
	 *
	 * @since    1.0.0
	 * @return   string    Last step identifier.
	 */
	public static function get_last_step() {
		return self::$steps[ count( self::$steps ) - 1 ];
	}

	/**
	 * Check if step is first step.
	 *
	 * @since    1.0.0
	 * @param    string $step    Step identifier.
	 * @return   bool               True if first step.
	 */
	public static function is_first_step( $step ) {
		return $step === self::get_first_step();
	}

	/**
	 * Check if step is last step.
	 *
	 * @since    1.0.0
	 * @param    string $step    Step identifier.
	 * @return   bool               True if last step.
	 */
	public static function is_last_step( $step ) {
		return $step === self::get_last_step();
	}

	/**
	 * Get progress percentage for a step.
	 *
	 * @since    1.0.0
	 * @param    string $step    Current step identifier.
	 * @return   float              Progress percentage (0-100).
	 */
	public static function get_progress_percentage( $step ) {
		$index = self::get_step_index( $step );

		if ( false === $index ) {
			return 0.0;
		}

		$total = self::get_step_count();
		return ( ( $index + 1 ) / $total ) * 100;
	}
}
