<?php
/**
 * Sidebar Progress Checklist
 *
 * Tracks and displays completion status for wizard steps.
 * Shows users what's done, what's required, and what's next.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/wizard
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sidebar Progress Checklist
 *
 * @since 1.0.0
 */
class SCD_Sidebar_Progress {

	/**
	 * Get step checklist configuration
	 *
	 * @since  1.0.0
	 * @param  string $step Step identifier (basic, products, discounts, schedule, review).
	 * @param  array  $data Step data.
	 * @return array        Checklist with completion status
	 */
	public static function get_step_checklist( $step, $data = array() ) {
		$checklist = self::get_checklist_definition( $step );

		if ( empty( $checklist ) ) {
			return array();
		}

		return self::check_completion( $checklist, $data );
	}

	/**
	 * Get checklist definition for step
	 *
	 * @since  1.0.0
	 * @param  string $step Step identifier.
	 * @return array        Checklist items configuration
	 */
	private static function get_checklist_definition( $step ) {
		$checklists = array(
			'basic'     => array(
				array(
					'field'    => 'name',
					'label'    => __( 'Name your campaign', 'smart-cycle-discounts' ),
					'required' => true,
				),
				array(
					'field'    => 'description',
					'label'    => __( 'Add description', 'smart-cycle-discounts' ),
					'required' => false,
				),
				array(
					'field'    => 'priority',
					'label'    => __( 'Set priority level', 'smart-cycle-discounts' ),
					'required' => true,
				),
			),

			'products'  => array(
				array(
					'field'    => 'product_selection_type',
					'label'    => __( 'Choose selection method', 'smart-cycle-discounts' ),
					'required' => true,
				),
				array(
					'field'     => 'category_ids',
					'label'     => __( 'Select categories', 'smart-cycle-discounts' ),
					'required'  => true,
					'condition' => array(
						'field' => 'product_selection_type',
						'value' => 'all_products',
					),
				),
				array(
					'field'     => 'product_ids',
					'label'     => __( 'Select products', 'smart-cycle-discounts' ),
					'required'  => true,
					'condition' => array(
						'field' => 'product_selection_type',
						'value' => 'specific_products',
					),
				),
				array(
					'field'     => 'random_count',
					'label'     => __( 'Set random count', 'smart-cycle-discounts' ),
					'required'  => true,
					'condition' => array(
						'field' => 'product_selection_type',
						'value' => 'random_products',
					),
				),
				array(
					'field'     => 'conditions',
					'label'     => __( 'Configure conditions', 'smart-cycle-discounts' ),
					'required'  => true,
					'condition' => array(
						'field' => 'product_selection_type',
						'value' => 'smart_selection',
					),
				),
			),

			'discounts' => array(
				array(
					'field'    => 'discount_type',
					'label'    => __( 'Choose discount type', 'smart-cycle-discounts' ),
					'required' => true,
				),
				array(
					'field'     => 'discount_value',
					'label'     => __( 'Set discount value', 'smart-cycle-discounts' ),
					'required'  => true,
					'condition' => array(
						'field'  => 'discount_type',
						'values' => array( 'percentage', 'fixed' ),
					),
				),
				array(
					'field'     => 'tiers',
					'label'     => __( 'Configure tiers', 'smart-cycle-discounts' ),
					'required'  => true,
					'condition' => array(
						'field' => 'discount_type',
						'value' => 'tiered',
					),
				),
				array(
					'field'     => 'bogo_config',
					'label'     => __( 'Configure BOGO', 'smart-cycle-discounts' ),
					'required'  => true,
					'condition' => array(
						'field' => 'discount_type',
						'value' => 'bogo',
					),
				),
				array(
					'field'     => 'spend_amount',
					'label'     => __( 'Set spend threshold', 'smart-cycle-discounts' ),
					'required'  => true,
					'condition' => array(
						'field' => 'discount_type',
						'value' => 'spend_threshold',
					),
				),
			),

			'schedule'  => array(
				array(
					'field'    => 'start_date',
					'label'    => __( 'Set start date (or leave empty)', 'smart-cycle-discounts' ),
					'required' => false,
				),
				array(
					'field'    => 'end_date',
					'label'    => __( 'Set end date (or leave empty)', 'smart-cycle-discounts' ),
					'required' => false,
				),
				array(
					'field'    => 'recurring_type',
					'label'    => __( 'Configure recurring (optional)', 'smart-cycle-discounts' ),
					'required' => false,
				),
			),

			'review'    => array(
				array(
					'field'    => 'all_steps',
					'label'    => __( 'Complete all required fields', 'smart-cycle-discounts' ),
					'required' => true,
				),
				array(
					'field'    => 'no_errors',
					'label'    => __( 'Resolve all validation errors', 'smart-cycle-discounts' ),
					'required' => true,
				),
				array(
					'field'    => 'review_settings',
					'label'    => __( 'Review campaign settings', 'smart-cycle-discounts' ),
					'required' => true,
				),
			),
		);

		return apply_filters( "scd_sidebar_checklist_{$step}", isset( $checklists[ $step ] ) ? $checklists[ $step ] : array(), $step );
	}

	/**
	 * Check completion status for checklist items
	 *
	 * @since  1.0.0
	 * @param  array $checklist Checklist items.
	 * @param  array $data      Step data.
	 * @return array            Checklist with completion status
	 */
	private static function check_completion( $checklist, $data ) {
		$completed_count = 0;
		$required_count  = 0;
		$total_count     = count( $checklist );

		foreach ( $checklist as $index => $item ) {
			// Check if item should be shown based on condition
			if ( isset( $item['condition'] ) && ! self::check_condition( $item['condition'], $data ) ) {
				$checklist[ $index ]['visible'] = false;
				continue;
			}

			$checklist[ $index ]['visible'] = true;

			// Check if field is completed
			$checklist[ $index ]['completed'] = self::is_field_completed( $item['field'], $data );

			if ( $checklist[ $index ]['completed'] ) {
				$completed_count++;
			}

			if ( $item['required'] ) {
				$required_count++;
			}
		}

		// Calculate progress
		$visible_items    = array_filter( $checklist, function( $item ) {
			return isset( $item['visible'] ) && $item['visible'];
		} );
		$visible_count    = count( $visible_items );
		$progress_percent = $visible_count > 0 ? round( ( $completed_count / $visible_count ) * 100 ) : 0;

		return array(
			'items'            => $checklist,
			'completed_count'  => $completed_count,
			'required_count'   => $required_count,
			'total_count'      => $total_count,
			'visible_count'    => $visible_count,
			'progress_percent' => $progress_percent,
			'all_required_done' => $completed_count >= $required_count,
		);
	}

	/**
	 * Check if condition is met
	 *
	 * @since  1.0.0
	 * @param  array $condition Condition configuration.
	 * @param  array $data      Step data.
	 * @return bool             Whether condition is met
	 */
	private static function check_condition( $condition, $data ) {
		if ( ! isset( $condition['field'] ) ) {
			return true;
		}

		$field_value = isset( $data[ $condition['field'] ] ) ? $data[ $condition['field'] ] : '';

		// Single value condition
		if ( isset( $condition['value'] ) ) {
			return $condition['value'] === $field_value;
		}

		// Multiple values condition
		if ( isset( $condition['values'] ) && is_array( $condition['values'] ) ) {
			return in_array( $field_value, $condition['values'], true );
		}

		return true;
	}

	/**
	 * Check if field is completed
	 *
	 * @since  1.0.0
	 * @param  string $field Field name.
	 * @param  array  $data  Step data.
	 * @return bool          Whether field is completed
	 */
	private static function is_field_completed( $field, $data ) {
		if ( ! isset( $data[ $field ] ) ) {
			return false;
		}

		$value = $data[ $field ];

		// Empty check
		if ( is_string( $value ) && '' === trim( $value ) ) {
			return false;
		}

		// Array check
		if ( is_array( $value ) ) {
			// Empty array
			if ( empty( $value ) ) {
				return false;
			}

			// Array with only 'all' is not considered complete for specific selections
			if ( 1 === count( $value ) && in_array( 'all', $value, true ) ) {
				// For category_ids, 'all' is valid
				if ( 'category_ids' === $field ) {
					return true;
				}
				return false;
			}

			return true;
		}

		// Non-empty value
		return true;
	}

	/**
	 * Get progress summary
	 *
	 * @since  1.0.0
	 * @param  array $checklist_data Checklist data from get_step_checklist().
	 * @return string                 Human-readable progress summary
	 */
	public static function get_progress_summary( $checklist_data ) {
		if ( empty( $checklist_data ) ) {
			return __( 'No progress data available', 'smart-cycle-discounts' );
		}

		$completed = $checklist_data['completed_count'];
		$required  = $checklist_data['required_count'];
		$total     = $checklist_data['visible_count'];

		if ( $checklist_data['all_required_done'] ) {
			return __( 'All required fields complete!', 'smart-cycle-discounts' );
		}

		return sprintf(
			/* translators: 1: completed count, 2: required count */
			__( '%1$d of %2$d required complete', 'smart-cycle-discounts' ),
			$completed,
			$required
		);
	}
}
