<?php
/**
 * Case Converter Utility
 *
 * Converts array keys between snake_case (PHP/WordPress) and camelCase (JavaScript)
 * at the boundary between backend and frontend.
 *
 * This is the single source of truth for all case conversions in the plugin:
 * - AJAX requests: JavaScript camelCase → PHP snake_case
 * - AJAX responses: PHP snake_case → JavaScript camelCase
 * - Page load data: PHP snake_case → JavaScript camelCase
 *
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/utilities
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Case Converter Utility Class
 *
 * Provides bidirectional conversion between PHP snake_case and JavaScript camelCase.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/utilities
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Case_Converter {

	/**
	 * Convert camelCase keys to snake_case recursively
	 *
	 * Used for: JavaScript → PHP (AJAX requests, form submissions)
	 *
	 * Examples:
	 *   productSelectionType → product_selection_type
	 *   categoryIds → category_ids
	 *   campaignName → campaign_name
	 *
	 * @since  1.0.0
	 * @param  mixed $data Data to convert (array, object, or primitive)
	 * @return mixed       Converted data with snake_case keys
	 */
	public static function camel_to_snake( $data ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}

		// Check if this is a numeric array (list) - preserve as-is but recurse into values
		$is_list = array_keys( $data ) === range( 0, count( $data ) - 1 );
		if ( $is_list ) {
			$result = array();
			foreach ( $data as $value ) {
				$result[] = is_array( $value ) ? self::camel_to_snake( $value ) : $value;
			}
			return $result;
		}

		// Associative array - convert keys
		$result = array();
		foreach ( $data as $key => $value ) {
			// Convert camelCase key to snake_case
			// Uses negative lookbehind to avoid converting first character
			$snake_key = strtolower( preg_replace( '/(?<!^)[A-Z]/', '_$0', $key ) );

			// Recursively convert nested arrays
			$result[ $snake_key ] = is_array( $value ) ? self::camel_to_snake( $value ) : $value;
		}

		return $result;
	}

	/**
	 * Convert snake_case keys to camelCase recursively
	 *
	 * Used for: PHP → JavaScript (AJAX responses, page load data via wp_localize_script)
	 *
	 * Examples:
	 *   product_selection_type → productSelectionType
	 *   category_ids → categoryIds
	 *   campaign_name → campaignName
	 *
	 * @since  1.0.0
	 * @param  mixed $data Data to convert (array, object, or primitive)
	 * @return mixed       Converted data with camelCase keys
	 */
	public static function snake_to_camel( $data ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}

		// Check if this is a numeric array (list) - preserve as-is but recurse into values
		$is_list = array_keys( $data ) === range( 0, count( $data ) - 1 );
		if ( $is_list ) {
			$result = array();
			foreach ( $data as $value ) {
				$result[] = is_array( $value ) ? self::snake_to_camel( $value ) : $value;
			}
			return $result;
		}

		// Associative array - convert keys
		$result = array();
		foreach ( $data as $key => $value ) {
			// Convert snake_case key to camelCase
			// Uses ucwords with underscore delimiter, then removes underscores and lowercases first char
			$camel_key = lcfirst( str_replace( '_', '', ucwords( $key, '_' ) ) );

			// Recursively convert nested arrays
			$result[ $camel_key ] = is_array( $value ) ? self::snake_to_camel( $value ) : $value;
		}

		return $result;
	}
}
