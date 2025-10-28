<?php
/**
 * Step Data Transformer
 *
 * Transforms wizard step data between formats (UI <-> Engine).
 * Centralizes data transformation logic for consistency.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/wizard
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Step Data Transformer Class
 *
 * Handles transformation of step data:
 * - Products: Convert product IDs format, transform conditions
 * - Discounts: Transform conditions from UI to engine format
 * - Ensures consistent data structures across application
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/wizard
 */
class SCD_Step_Data_Transformer {

	/**
	 * Condition validation errors and warnings.
	 *
	 * @since    1.0.0
	 * @var      array
	 */
	private $condition_errors = array();

	/**
	 * Transform step data based on step type.
	 *
	 * @since    1.0.0
	 * @param    string $step    Step name.
	 * @param    array  $data    Raw data.
	 * @return   array              Transformed data.
	 */
	public function transform( $step, $data ) {
		switch ( $step ) {
			case 'products':
				return $this->transform_products_data( $data );

			case 'discounts':
				return $this->transform_discounts_data( $data );

			default:
				return $data;
		}
	}

	/**
	 * Transform products step data.
	 *
	 * Handles:
	 * - Product IDs format (string -> array)
	 * - Product conditions (UI format -> engine format)
	 *
	 * @since    1.0.0
	 * @param    array $data    Raw products data.
	 * @return   array             Transformed data.
	 */
	private function transform_products_data( $data ) {
		// Transform product_ids from string to array
		if ( isset( $data['product_ids'] ) && is_string( $data['product_ids'] ) ) {
			$product_ids         = explode( ',', $data['product_ids'] );
			$data['product_ids'] = array_values(
				array_filter(
					$product_ids,
					function ( $id ) {
						return '' !== $id && null !== $id && false !== $id;
					}
				)
			);

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log(
					sprintf(
						'[SCD Transformer] Converted product_ids from string to array - Count: %d',
						count( $data['product_ids'] )
					)
				);
			}
		} elseif ( isset( $data['product_ids'] ) && is_array( $data['product_ids'] ) ) {
			// Re-index array to ensure sequential keys
			$data['product_ids'] = array_values( $data['product_ids'] );
		}

		// Transform conditions from UI format to engine format
		if ( isset( $data['conditions'] ) && is_array( $data['conditions'] ) ) {
			$data['conditions'] = $this->transform_conditions_for_engine( $data['conditions'] );
		}

		return $data;
	}

	/**
	 * Transform discounts step data.
	 *
	 * Currently handles conditions transformation.
	 * Can be extended for other discount-specific transformations.
	 *
	 * @since    1.0.0
	 * @param    array $data    Raw discounts data.
	 * @return   array             Transformed data.
	 */
	private function transform_discounts_data( $data ) {
		// Transform conditions from UI format to engine format
		if ( isset( $data['conditions'] ) && is_array( $data['conditions'] ) ) {
			$data['conditions'] = $this->transform_conditions_for_engine( $data['conditions'] );
		}

		return $data;
	}

	/**
	 * Transform conditions from UI format to engine format.
	 *
	 * UI format:   {type, operator, value, value2, mode}
	 * Engine format: {property, operator, values[], mode}
	 *
	 * @since    1.0.0
	 * @param    array $ui_conditions    Conditions from UI.
	 * @return   array                      Conditions for engine.
	 */
	private function transform_conditions_for_engine( $ui_conditions ) {
		$engine_conditions      = array();
		$this->condition_errors = array(
			'invalid'  => array(),
			'warnings' => array(),
		);

		foreach ( $ui_conditions as $index => $condition ) {
			// Skip if not an array
			if ( ! is_array( $condition ) ) {
				$this->condition_errors['invalid'][] = sprintf(
					/* translators: %d: condition number */
					__( 'Condition #%d is not valid (expected array)', 'smart-cycle-discounts' ),
					$index + 1
				);
				continue;
			}

			// Skip if already in engine format (has 'property' field)
			if ( isset( $condition['property'] ) ) {
				$engine_conditions[] = $condition;
				continue;
			}

			// Skip if missing required UI fields
			if ( ! isset( $condition['type'], $condition['operator'] ) ) {
				$this->condition_errors['invalid'][] = sprintf(
					/* translators: %d: condition number */
					__( 'Condition #%d is missing required fields (type and operator)', 'smart-cycle-discounts' ),
					$index + 1
				);

				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( '[SCD Transformer] Skipping invalid condition - missing type or operator' );
				}
				continue;
			}

			// Build values array from value and value2
			$values = $this->extract_condition_values( $condition );

			// Warn if no values provided
			if ( empty( $values ) ) {
				$this->condition_errors['warnings'][] = sprintf(
					/* translators: %d: condition number */
					__( 'Condition #%d has no values specified', 'smart-cycle-discounts' ),
					$index + 1
				);
			}

			// Transform to engine format
			$engine_condition = array(
				'property' => $condition['type'],
				'operator' => $condition['operator'],
				'values'   => $values,
				'mode'     => isset( $condition['mode'] ) ? $condition['mode'] : 'include',
			);

			$engine_conditions[] = $engine_condition;

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log(
					sprintf(
						'[SCD Transformer] Transformed condition - Property: %s, Operator: %s, Values: %s',
						$engine_condition['property'],
						$engine_condition['operator'],
						implode( ', ', $engine_condition['values'] )
					)
				);
			}
		}

		return $engine_conditions;
	}

	/**
	 * Extract values from condition.
	 *
	 * Builds values array from 'value' and 'value2' fields.
	 *
	 * @since    1.0.0
	 * @param    array $condition    Condition data.
	 * @return   array                  Values array.
	 */
	private function extract_condition_values( $condition ) {
		$values = array();

		if ( isset( $condition['value'] ) && '' !== $condition['value'] ) {
			$values[] = $condition['value'];
		}

		if ( isset( $condition['value2'] ) && '' !== $condition['value2'] ) {
			$values[] = $condition['value2'];
		}

		return $values;
	}

	/**
	 * Get condition transformation errors.
	 *
	 * @since    1.0.0
	 * @return   array    Array with 'invalid' and 'warnings' keys.
	 */
	public function get_condition_errors() {
		return $this->condition_errors;
	}

	/**
	 * Check if there are condition errors.
	 *
	 * @since    1.0.0
	 * @return   bool    True if errors exist.
	 */
	public function has_condition_errors() {
		return ! empty( $this->condition_errors['invalid'] ) || ! empty( $this->condition_errors['warnings'] );
	}
}
