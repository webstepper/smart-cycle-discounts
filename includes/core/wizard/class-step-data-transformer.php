<?php
/**
 * Step Data Transformer Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/wizard/class-step-data-transformer.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Step Data Transformer Class
 *
 * Handles transformation of step data:
 * - Products: Convert product IDs format (CSV string → array)
 * - Ensures consistent data structures across application
 *
 * Note: Conditions now use UI format throughout the entire system.
 * No transformation needed - engine/processor works directly with UI format.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/wizard
 */
class SCD_Step_Data_Transformer {

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
	 * - Conditions now use UI format throughout (no transformation needed)
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
		} elseif ( isset( $data['product_ids'] ) && is_array( $data['product_ids'] ) ) {
			// Re-index array to ensure sequential keys
			$data['product_ids'] = array_values( $data['product_ids'] );
		}

		// Conditions use standardized database format throughout - no transformation needed
		// All systems work directly with database format (condition_type, value, value2)

		return $data;
	}

	/**
	 * Transform discounts step data.
	 *
	 * Conditions use standardized database format throughout (no transformation needed).
	 * Can be extended for other discount-specific transformations if needed.
	 *
	 * @since    1.0.0
	 * @param    array $data    Raw discounts data.
	 * @return   array             Transformed data.
	 */
	private function transform_discounts_data( $data ) {
		// Conditions use standardized database format throughout - no transformation needed
		// All systems work directly with database format (condition_type, value, value2)

		return $data;
	}
}
