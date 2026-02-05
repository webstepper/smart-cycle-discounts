<?php
/**
 * Campaign Change Tracker Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/wizard/class-campaign-change-tracker.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress/plugins/smart-cycle-discounts/
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
class WSSCD_Campaign_Change_Tracker {

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
	 * @var      WSSCD_Wizard_State_Service
	 */
	private $session;

	/**
	 * Campaign manager for loading campaign.
	 *
	 * @since    1.0.0
	 * @var      WSSCD_Campaign_Manager|null
	 */
	private $campaign_manager;

	/**
	 * Cached campaign entity.
	 *
	 * @since    1.0.0
	 * @var      WSSCD_Campaign|null
	 */
	private $campaign_cache = null;

	/**
	 * Cached recurring settings.
	 *
	 * @since    1.0.0
	 * @var      array|null
	 */
	private $recurring_settings_cache = null;

	/**
	 * Constructor.
	 *
	 * @since    1.0.0
	 * @param    int                       $campaign_id        Campaign ID.
	 * @param    WSSCD_Wizard_State_Service  $session            Session service.
	 * @param    WSSCD_Campaign_Manager|null $campaign_manager   Campaign manager.
	 */
	public function __construct( $campaign_id, $session, $campaign_manager = null ) {
		$this->campaign_id      = $campaign_id;
		$this->session          = $session;
		$this->campaign_manager = $campaign_manager;

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
		$campaign = $this->load_campaign();
		if ( ! $campaign ) {
			// No campaign - return changes only
			return $this->extract_changes_for_step( $step );
		}

		$db_data = $this->extract_step_data_from_campaign( $campaign, $step );

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
	 * @return   WSSCD_Campaign|null    Campaign or null.
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
	 * Load recurring settings from database (with caching).
	 *
	 * @since    1.0.0
	 * @return   array    Recurring settings or empty array.
	 */
	private function load_recurring_settings() {
		if ( null !== $this->recurring_settings_cache ) {
			return $this->recurring_settings_cache;
		}

		// Try to get recurring handler from container
		$recurring_handler = null;
		if ( class_exists( 'Smart_Cycle_Discounts' ) ) {
			$container         = Smart_Cycle_Discounts::get_instance();
			$recurring_handler = $container::get_service( 'recurring_handler' );
		}

		if ( ! $recurring_handler || ! method_exists( $recurring_handler, 'get_recurring_settings' ) ) {
			$this->recurring_settings_cache = array();
			return $this->recurring_settings_cache;
		}

		$settings = $recurring_handler->get_recurring_settings( $this->campaign_id );

		$this->recurring_settings_cache = is_array( $settings ) ? $settings : array();
		return $this->recurring_settings_cache;
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
	 * @param    WSSCD_Campaign $campaign    Campaign entity.
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
						// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional debug logging when WP_DEBUG is enabled.
						error_log(
							sprintf(
								'[Change Tracker] Filtered %d deleted product(s) from campaign %d',
								$removed_count,
								$campaign->get_id()
							)
						);
					}
				}

				// Load conditions from repository (new architecture)
				$conditions = array();
				if ( class_exists( 'Smart_Cycle_Discounts' ) ) {
					try {
						$conditions_repo = Smart_Cycle_Discounts::get_service( 'campaign_conditions_repository' );
						if ( $conditions_repo ) {
							$conditions = $conditions_repo->get_conditions_for_campaign( $campaign->get_id() );
						}
					} catch ( Exception $e ) {
						// Fallback to empty array
					}
				}

				return array(
					'product_selection_type' => $campaign->get_product_selection_type(),
					'product_ids'            => $valid_product_ids,
					'category_ids'           => $campaign->get_category_ids() ?: array(),
					'random_product_count'   => $campaign->get_random_product_count(),
					'smart_criteria'         => $metadata['smart_criteria'] ?? '',
					'conditions'             => $conditions,
					'conditions_logic'       => $campaign->get_conditions_logic(),
				);

			case 'discounts':
				$discount_rules = $campaign->get_discount_rules() ?: array();
				$discount_type  = $campaign->get_discount_type();
				$discount_value = $campaign->get_discount_value();
				$settings       = $campaign->get_settings() ?: array();

				// Extract BOGO config as grouped object to match frontend field definition
				$bogo_config = $discount_rules['bogo_config'] ?? array();

				// Ensure bogo_config has default values if empty
				if ( empty( $bogo_config ) ) {
					$bogo_config = array(
						'buy_quantity'     => 1,
						'get_quantity'     => 1,
						'discount_percent' => 100,
						'apply_to'         => 'cheapest',
					);
				}

				$data = array(
					// Core discount fields
					'discount_type'             => $discount_type,
					'discount_value_percentage' => 'percentage' === $discount_type ? $discount_value : 10,
					'discount_value_fixed'      => 'fixed' === $discount_type ? $discount_value : 5,
					'tiers'                     => $discount_rules['tiers'] ?? array(),
					// BOGO config - keep as grouped object to match frontend complex field
					'bogo_config'               => $bogo_config,
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
					'badge_enabled'             => $settings['badge_enabled'] ?? true,
					'badge_text'                => $settings['badge_text'] ?? 'auto',
					'badge_bg_color'            => $settings['badge_bg_color'] ?? '#ff0000',
					'badge_text_color'          => $settings['badge_text_color'] ?? '#ffffff',
					'badge_position'            => $settings['badge_position'] ?? 'top-right',
				);

				return $data;

			case 'schedule':
				$starts_at = $campaign->get_starts_at();
				$ends_at   = $campaign->get_ends_at();
				$timezone  = $campaign->get_timezone();
				$metadata  = $campaign->get_metadata();

				$start_split = $starts_at ? WSSCD_DateTime_Splitter::for_editing( $starts_at, $timezone ) : array();
				$end_split   = $ends_at ? WSSCD_DateTime_Splitter::for_editing( $ends_at, $timezone ) : array();


				$end_time = '';
				if ( ! empty( $end_split['date'] ) ) {
					$end_time = $end_split['time'] ?? '';
				}

				// Load recurring settings from recurring table for recurrence_mode
				$recurring_settings = $this->load_recurring_settings();
				$recurrence_mode    = $recurring_settings['recurrence_mode'] ?? 'continuous';

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
					'recurrence_mode'     => $recurrence_mode,
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
