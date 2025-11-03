<?php
/**
 * Campaign Change Tracker Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/wizard/class-campaign-change-tracker.php
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Campaign Change Tracker Class
 *
 * Solves the "session as database" anti-pattern by:
 * - Storing only changed fields in session
 * - Reading from database on demand
 * - Merging changes when compiling
 * - Clearing changes after save
 *
 * @since      1.0.0
 */
class SCD_Campaign_Change_Tracker {

	/**
	 * Campaign ID being edited.
	 *
	 * @since    1.0.0
	 * @var      int
	 */
	private $campaign_id;

	/**
	 * Changed fields by step.
	 *
	 * @since    1.0.0
	 * @var      array
	 */
	private $changes = array();

	/**
	 * Wizard state service.
	 *
	 * @since    1.0.0
	 * @var      SCD_Wizard_State_Service
	 */
	private $session;

	/**
	 * Campaign manager for loading campaign.
	 *
	 * @since    1.0.0
	 * @var      SCD_Campaign_Manager|null
	 */
	private $campaign_manager;

	/**
	 * Cached campaign entity.
	 *
	 * @since    1.0.0
	 * @var      SCD_Campaign|null
	 */
	private $campaign_cache = null;

	/**
	 * Constructor.
	 *
	 * @since    1.0.0
	 * @param    int                       $campaign_id        Campaign ID.
	 * @param    SCD_Wizard_State_Service  $session            Session service.
	 * @param    SCD_Campaign_Manager|null $campaign_manager   Campaign manager.
	 */
	public function __construct( $campaign_id, $session, $campaign_manager = null ) {
		$this->campaign_id      = $campaign_id;
		$this->session          = $session;
		$this->campaign_manager = $campaign_manager;

		// Load existing changes from session
		$this->changes = $session->get( 'changes', array() );
	}

	/**
	 * Track a field change.
	 *
	 * @since    1.0.0
	 * @param    string $step     Step name.
	 * @param    string $field    Field name.
	 * @param    mixed  $value    New value.
	 * @return   void
	 */
	public function track( $step, $field, $value ) {
		if ( ! isset( $this->changes[ $step ] ) ) {
			$this->changes[ $step ] = array();
		}

		$this->changes[ $step ][ $field ] = array(
			'value'     => $value,
			'timestamp' => time(),
		);

		// Persist to session
		$this->session->set( 'changes', $this->changes );
		$this->session->save();
	}

	/**
	 * Track multiple fields for a step.
	 *
	 * @since    1.0.0
	 * @param    string $step    Step name.
	 * @param    array  $data    Field => value pairs.
	 * @return   void
	 */
	public function track_step( $step, $data ) {
		if ( ! is_array( $data ) ) {
			return;
		}

		foreach ( $data as $field => $value ) {
			$this->track( $step, $field, $value );
		}
	}

	/**
	 * Get current value for a field.
	 *
	 * Checks changes first, falls back to database.
	 *
	 * @since    1.0.0
	 * @param    string $step      Step name.
	 * @param    string $field     Field name.
	 * @param    mixed  $default   Default value.
	 * @return   mixed                Current value.
	 */
	public function get( $step, $field, $default = null ) {
		// Check changes first
		if ( isset( $this->changes[ $step ][ $field ] ) ) {
			return $this->changes[ $step ][ $field ]['value'];
		}

		// Fall back to database
		return $this->get_from_database( $step, $field, $default );
	}

	/**
	 * Get all data for a step (merged changes + database).
	 *
	 * @since    1.0.0
	 * @param    string $step    Step name.
	 * @return   array              Merged step data.
	 */
	public function get_step_data( $step ) {
		// Load base data from database
		$campaign = $this->load_campaign();
		if ( ! $campaign ) {
			// No campaign - return changes only
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			}
			return $this->extract_changes_for_step( $step );
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		}

		$db_data = $this->extract_step_data_from_campaign( $campaign, $step );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		}

		// Merge changes on top
		$step_changes = $this->extract_changes_for_step( $step );
		return array_merge( $db_data, $step_changes );
	}

	/**
	 * Compile all changes into campaign data.
	 *
	 * @since    1.0.0
	 * @return   array    Complete campaign data with changes applied.
	 */
	public function compile() {
		$campaign = $this->load_campaign();
		if ( ! $campaign ) {
			// No base campaign - return changes only
			$compiled = array();
			foreach ( $this->changes as $step => $fields ) {
				foreach ( $fields as $field => $change ) {
					$compiled[ $field ] = $change['value'];
				}
			}
			return $compiled;
		}

		// Start with campaign data
		$campaign_data = $campaign->to_array();

		// Apply all changes
		foreach ( $this->changes as $step => $fields ) {
			foreach ( $fields as $field => $change ) {
				$campaign_data[ $field ] = $change['value'];
			}
		}

		return $campaign_data;
	}

	/**
	 * Check if there are unsaved changes.
	 *
	 * @since    1.0.0
	 * @return   bool    True if changes exist.
	 */
	public function has_changes() {
		return ! empty( $this->changes );
	}

	/**
	 * Get change summary.
	 *
	 * @since    1.0.0
	 * @return   array    Step => change count.
	 */
	public function get_summary() {
		$summary = array();
		foreach ( $this->changes as $step => $fields ) {
			$summary[ $step ] = count( $fields );
		}
		return $summary;
	}

	/**
	 * Clear all changes.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function clear() {
		$this->changes = array();
		$this->session->set( 'changes', array() );
		$this->session->save();
	}

	/**
	 * Load campaign from database (with caching).
	 *
	 * @since    1.0.0
	 * @return   SCD_Campaign|null    Campaign or null.
	 */
	private function load_campaign() {
		if ( null !== $this->campaign_cache ) {
			return $this->campaign_cache;
		}

		if ( ! $this->campaign_manager ) {
			// Try to get from container
			$container              = Smart_Cycle_Discounts::get_instance();
			$this->campaign_manager = $container::get_service( 'campaign_manager' );
		}

		if ( ! $this->campaign_manager ) {
			return null;
		}

		$this->campaign_cache = $this->campaign_manager->find( $this->campaign_id );
		return $this->campaign_cache;
	}

	/**
	 * Get value from database.
	 *
	 * @since    1.0.0
	 * @param    string $step      Step name.
	 * @param    string $field     Field name.
	 * @param    mixed  $default   Default value.
	 * @return   mixed                Database value or default.
	 */
	private function get_from_database( $step, $field, $default = null ) {
		$campaign = $this->load_campaign();
		if ( ! $campaign ) {
			return $default;
		}

		$getter = 'get_' . $field;
		if ( method_exists( $campaign, $getter ) ) {
			return $campaign->$getter();
		}

		// Try to_array
		$data = $campaign->to_array();
		return isset( $data[ $field ] ) ? $data[ $field ] : $default;
	}

	/**
	 * Extract step data from campaign entity.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign    Campaign entity.
	 * @param    string       $step        Step name.
	 * @return   array                        Step data.
	 */
	private function extract_step_data_from_campaign( $campaign, $step ) {
		switch ( $step ) {
			case 'basic':
				return array(
					'name'        => $campaign->get_name(),
					'description' => $campaign->get_description(),
					'priority'    => $campaign->get_priority(),
				);

			case 'products':
				$metadata    = $campaign->get_metadata();
				$product_ids = $campaign->get_product_ids() ?: array();

				// CRITICAL FIX: Filter out deleted products
				$valid_product_ids = $this->filter_valid_products( $product_ids );
				if ( count( $product_ids ) !== count( $valid_product_ids ) ) {
					$removed_count = count( $product_ids ) - count( $valid_product_ids );
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log(
							sprintf(
								'[Change Tracker] Filtered %d deleted product(s) from campaign %d',
								$removed_count,
								$campaign->get_id()
							)
						);
					}
				}

				return array(
					'product_selection_type' => $campaign->get_product_selection_type(),
					'product_ids'            => $valid_product_ids,
					'category_ids'           => $campaign->get_category_ids() ?: array(),
					'random_count'           => $metadata['random_count'] ?? 10,
					'smart_criteria'         => $metadata['smart_criteria'] ?? '',
					'conditions'             => $metadata['product_conditions'] ?? array(),
					'conditions_logic'       => $metadata['product_conditions_logic'] ?? 'all',
				);

			case 'discounts':
				$discount_rules = $campaign->get_discount_rules() ?: array();
				$discount_type  = $campaign->get_discount_type();
				$discount_value = $campaign->get_discount_value();
				$settings       = $campaign->get_settings() ?: array();

				$data = array(
					// Core discount fields
					'discount_type'             => $discount_type,
					'discount_value_percentage' => 'percentage' === $discount_type ? $discount_value : 10,
					'discount_value_fixed'      => 'fixed' === $discount_type ? $discount_value : 5,
					'tiers'                     => $discount_rules['tiers'] ?? array(),
					'bogo_config'               => $discount_rules['bogo_config'] ?? array(
						'buy_quantity'     => 1,
						'get_quantity'     => 1,
						'discount_percent' => 100,
					),
					// Spend threshold fields
					'threshold_mode'            => $discount_rules['threshold_mode'] ?? 'percentage',
					'thresholds'                => $discount_rules['thresholds'] ?? array(),
					// Usage limits
					'usage_limit_per_customer'  => $discount_rules['usage_limit_per_customer'] ?? '',
					'total_usage_limit'         => $discount_rules['total_usage_limit'] ?? '',
					'lifetime_usage_cap'        => $discount_rules['lifetime_usage_cap'] ?? '',
					// Discount rules
					'apply_to'                  => $discount_rules['apply_to'] ?? 'per_item',
					'max_discount_amount'       => $discount_rules['max_discount_amount'] ?? '',
					'minimum_quantity'          => $discount_rules['minimum_quantity'] ?? '',
					'minimum_order_amount'      => $discount_rules['minimum_order_amount'] ?? '',
					'stack_with_others'         => $settings['stack_with_others'] ?? false,
					'allow_coupons'             => $settings['allow_coupons'] ?? true,
					'apply_to_sale_items'       => $settings['apply_to_sale_items'] ?? true,
					// Badge settings
					'badge_enabled'             => $settings['badge_enabled'] ?? false,
					'badge_text'                => $settings['badge_text'] ?? '',
					'badge_bg_color'            => $settings['badge_bg_color'] ?? '#e74c3c',
					'badge_text_color'          => $settings['badge_text_color'] ?? '#ffffff',
					'badge_position'            => $settings['badge_position'] ?? 'top-right',
				);

				return $data;

			case 'schedule':
				$starts_at = $campaign->get_starts_at();
				$ends_at   = $campaign->get_ends_at();
				$timezone  = $campaign->get_timezone();
				$metadata  = $campaign->get_metadata();

				$start_split = $starts_at ? SCD_DateTime_Splitter::for_editing( $starts_at, $timezone ) : array();
				$end_split   = $ends_at ? SCD_DateTime_Splitter::for_editing( $ends_at, $timezone ) : array();

				// Debug logging for schedule data extraction
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				}

				// Extract exact date/time values from database (no fallbacks for edit mode)
				$end_time = '';
				if ( ! empty( $end_split['date'] ) ) {
					$end_time = $end_split['time'] ?? '';
				}

				return array(
					// Date/Time fields
					'start_date'          => $start_split['date'] ?? '',
					'start_time'          => $start_split['time'] ?? '',
					'end_date'            => $end_split['date'] ?? '',
					'end_time'            => $end_time,
					'timezone'            => $timezone,
					// Start type fields
					'start_type'          => $starts_at ? 'scheduled' : 'immediate',
					'duration_seconds'    => $metadata['duration_seconds'] ?? 3600,
					// Recurring fields
					'enable_recurring'    => $metadata['enable_recurring'] ?? false,
					'recurrence_pattern'  => $metadata['recurrence_pattern'] ?? 'daily',
					'recurrence_interval' => $metadata['recurrence_interval'] ?? 1,
					'recurrence_days'     => $metadata['recurrence_days'] ?? array(),
					'recurrence_end_type' => $metadata['recurrence_end_type'] ?? 'never',
					'recurrence_count'    => $metadata['recurrence_count'] ?? 10,
					'recurrence_end_date' => $metadata['recurrence_end_date'] ?? '',
					// Rotation fields
					'rotation_enabled'    => $metadata['rotation_enabled'] ?? false,
					'rotation_interval'   => $metadata['rotation_interval'] ?? 24,
				);

			case 'review':
				// Map campaign status to launch_option for wizard
				$status        = $campaign->get_status();
				$launch_option = ( 'active' === $status ) ? 'active' : 'draft';

				return array(
					'launch_option' => $launch_option,
				);

			default:
				return array();
		}
	}

	/**
	 * Extract changes for a specific step.
	 *
	 * @since    1.0.0
	 * @param    string $step    Step name.
	 * @return   array              Field => value pairs.
	 */
	public function extract_changes_for_step( $step ) {
		if ( ! isset( $this->changes[ $step ] ) ) {
			return array();
		}

		$step_data = array();
		foreach ( $this->changes[ $step ] as $field => $change ) {
			$step_data[ $field ] = $change['value'];
		}

		return $step_data;
	}

	/**
	 * Filter valid products (remove deleted products).
	 *
	 * @since    1.0.0
	 * @param    array $product_ids    Product IDs to validate.
	 * @return   array                    Valid product IDs.
	 */
	private function filter_valid_products( $product_ids ) {
		if ( ! is_array( $product_ids ) || empty( $product_ids ) ) {
			return array();
		}

		$valid_ids = array();
		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( $product && $product->exists() ) {
				$valid_ids[] = (int) $product_id;
			}
		}

		return $valid_ids;
	}
}
