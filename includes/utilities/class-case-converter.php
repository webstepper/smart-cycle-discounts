<?php
/**
 * Case Converter Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/utilities/class-case-converter.php
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
 * Case Converter Utility Class
 *
 * Provides bidirectional conversion between PHP snake_case and JavaScript camelCase.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/utilities
 * @author     Webstepper <contact@webstepper.io>
 */
class WSSCD_Case_Converter {

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
			// Uses ucwords with underscore delimiter, then removes underscores and lowercases first char
			$camel_key = lcfirst( str_replace( '_', '', ucwords( $key, '_' ) ) );

			// Recursively convert nested arrays
			$result[ $camel_key ] = is_array( $value ) ? self::snake_to_camel( $value ) : $value;
		}

		return $result;
	}

	/**
	 * Recursively sanitize request data.
	 *
	 * Applies appropriate sanitization based on value type:
	 * - Arrays: recursive processing with key sanitization
	 * - Strings: sanitize_text_field() or wp_kses_post() for HTML
	 * - Integers/Floats: type casting
	 * - Booleans: preserved as-is
	 *
	 * Used for: Sanitizing $_POST data before processing
	 *
	 * @since  1.0.0
	 * @param  mixed $data Data to sanitize.
	 * @return mixed       Sanitized data.
	 */
	public static function sanitize_request( $data ) {
		if ( is_array( $data ) ) {
			$sanitized = array();
			foreach ( $data as $key => $value ) {
				// Sanitize key (only allow alphanumeric, underscore, hyphen).
				$clean_key               = preg_replace( '/[^a-zA-Z0-9_\-]/', '', $key );
				$sanitized[ $clean_key ] = self::sanitize_request( $value );
			}
			return $sanitized;
		}

		if ( is_string( $data ) ) {
			// Check if it looks like HTML content (for WYSIWYG fields).
			if ( preg_match( '/<[^>]+>/', $data ) ) {
				return wp_kses_post( $data );
			}
			return sanitize_text_field( $data );
		}

		if ( is_int( $data ) ) {
			return intval( $data );
		}

		if ( is_float( $data ) ) {
			return floatval( $data );
		}

		if ( is_bool( $data ) ) {
			return (bool) $data;
		}

		// For null or other types, return as-is.
		return $data;
	}

	/**
	 * Extract and sanitize only specified fields from request data.
	 *
	 * This method addresses WordPress.org plugin review requirements by:
	 * 1. Only processing specified fields (not the entire $_POST array)
	 * 2. Applying proper sanitization to each extracted field
	 * 3. Improving performance by avoiding unnecessary iteration
	 *
	 * @since  1.0.0
	 * @param  array $allowed_fields List of field names to extract.
	 * @param  array $source         Source array (typically $_POST).
	 * @return array                 Extracted and sanitized fields.
	 */
	public static function extract_and_sanitize( array $allowed_fields, array $source ): array {
		$extracted = array();

		foreach ( $allowed_fields as $field ) {
			if ( isset( $source[ $field ] ) ) {
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Value is sanitized via sanitize_request() below.
				$extracted[ $field ] = self::sanitize_request( wp_unslash( $source[ $field ] ) );
			}
		}

		return $extracted;
	}

	/**
	 * Get all allowed AJAX request fields.
	 *
	 * Returns a comprehensive list of fields used across all AJAX handlers.
	 * This addresses WordPress.org requirements to not process the entire $_POST array.
	 *
	 * @since  1.0.0
	 * @return array List of allowed AJAX field names.
	 */
	public static function get_allowed_ajax_fields(): array {
		return array(
			// Security fields.
			'nonce',
			'_wpnonce',
			'_signature',
			// Action fields.
			'action',
			'wsscdAction',
			'wsscd_action',
			'wizardAction',
			'wizard_action',
			'action_type',
			'action_data',
			// Common identifiers.
			'campaign_id',
			'campaignId',
			'step',
			'id',
			'wizard_navigation',
			'wsscd_wizard_nonce',
			// Calculator import.
			'code',
			// Campaign data fields.
			'campaign_name',
			'campaign_description',
			'priority',
			'status',
			'state',
			// Product selection fields.
			'product_selection_type',
			'product_ids',
			'category_ids',
			'tag_ids',
			'conditions',
			'conditions_logic',
			'exclude_sale_items',
			'exclude_id',
			'random_count',
			'smart_criteria',
			// Discount fields.
			'discount_type',
			'discount_value',
			'discount_rules',
			'discount_config',
			'tiers',
			'bogo_config',
			'spend_threshold_config',
			'max_discount_amount',
			'usage_limit_per_campaign',
			'usage_limit_per_customer',
			'rules',
			'tier_type',
			'threshold_mode',
			'min_quantity',
			'min_amount',
			'buy_quantity',
			'get_quantity',
			'discount_percent',
			// Schedule fields.
			'start_type',
			'start_date',
			'start_time',
			'end_type',
			'end_date',
			'end_time',
			'enable_recurring',
			'recurring',
			'recurrence_pattern',
			'recurrence_interval',
			'recurrence_days',
			'recurrence_end_type',
			'recurrence_end_date',
			'recurrence_count',
			'schedule',
			'timezone',
			'launch_option',
			// Analytics fields.
			'date_range',
			'start',
			'end',
			'period',
			'format',
			'report_type',
			'metric',
			'granularity',
			'is_major_event',
			// Search/filter fields.
			'search',
			'term',
			'query',
			'page',
			'per_page',
			'limit',
			'offset',
			'orderby',
			'order',
			'filter',
			'type',
			'parent',
			'ids',
			'includeProductCounts',
			'load_all',
			// Dashboard/tools fields.
			'panel',
			'data',
			'config',
			'settings',
			'provider',
			'email',
			'test_email',
			'clear_type',
			'log_type',
			'logAction',
			'log_action',
			'lines',
			'logs',
			'operation',
			'position',
			'source',
			'refresh',
			// Recommendation fields.
			'recommendation_id',
			// Import/export fields.
			'file',
			'importData',
			'import_data',
			'export_format',
			'exportType',
			'export_type',
			// Notification fields.
			'notification_id',
			'dismiss',
			'banner_id',
			// Draft/session fields.
			'draft_id',
			'session_id',
			'confirm_save',
			'use_draft',
			'step_data',
			'recovery_data',
			'campaign_data',
			'campaignData',
			'save_as_draft',
			'saveAsDraft',
			'draft_action',
			'draftAction',
			// Product search specific.
			'product_id',
			'include',
			'exclude',
			'topic_id',
			'categories',
			'selected',
		);
	}

	/**
	 * Get wizard step fields.
	 *
	 * Returns fields needed for wizard step submission.
	 *
	 * @since  1.0.0
	 * @param  string $step Step name.
	 * @return array        List of field names for the step.
	 */
	public static function get_wizard_step_fields( string $step ): array {
		// Common wizard fields.
		$common = array(
			'wsscd_wizard_nonce',
			'wizard_navigation',
			'campaign_id',
			'step',
		);

		// Step-specific fields.
		$step_fields = array(
			'basic'     => array(
				'campaign_name',
				'campaign_description',
				'priority',
				'status',
			),
			'products'  => array(
				'product_selection_type',
				'product_ids',
				'category_ids',
				'tag_ids',
				'conditions',
				'conditions_logic',
				'exclude_sale_items',
			),
			'discounts' => array(
				'discount_type',
				'discount_value',
				'discount_rules',
				'tiers',
				'bogo_config',
				'spend_threshold_config',
				'max_discount_amount',
				'usage_limit_per_campaign',
				'usage_limit_per_customer',
			),
			'schedule'  => array(
				'start_type',
				'start_date',
				'start_time',
				'end_type',
				'end_date',
				'end_time',
				'enable_recurring',
				'recurrence_pattern',
				'recurrence_interval',
				'recurrence_days',
				'recurrence_end_type',
				'recurrence_end_date',
				'recurrence_count',
			),
			'review'    => array(
				'confirm_save',
			),
		);

		$fields = isset( $step_fields[ $step ] ) ? $step_fields[ $step ] : array();

		return array_merge( $common, $fields );
	}

	/**
	 * Get campaign edit fields.
	 *
	 * Returns fields needed for campaign editing.
	 *
	 * @since  1.0.0
	 * @return array List of field names for campaign editing.
	 */
	public static function get_campaign_edit_fields(): array {
		return array(
			// Security.
			'wsscd_campaign_nonce',
			// Identifier.
			'campaign_id',
			// Basic.
			'campaign_name',
			'campaign_description',
			'priority',
			'status',
			// Products.
			'product_selection_type',
			'product_ids',
			'category_ids',
			'tag_ids',
			'conditions',
			'conditions_logic',
			'exclude_sale_items',
			// Discounts.
			'discount_type',
			'discount_value',
			'discount_rules',
			'tiers',
			'bogo_config',
			'spend_threshold_config',
			'max_discount_amount',
			'usage_limit_per_campaign',
			'usage_limit_per_customer',
			// Schedule.
			'start_type',
			'start_date',
			'start_time',
			'end_type',
			'end_date',
			'end_time',
			'enable_recurring',
			'recurrence_pattern',
			'recurrence_interval',
			'recurrence_days',
			'recurrence_end_type',
			'recurrence_end_date',
			'recurrence_count',
		);
	}
}
