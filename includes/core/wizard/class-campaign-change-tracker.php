<?php
/**
 * Campaign Change Tracker
 *
 * Tracks changes to campaigns being edited in wizard.
 * Stores only deltas in session, not full campaign data.
 * Database remains source of truth.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/wizard
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
				$metadata = $campaign->get_metadata();
				return array(
					'product_selection_type' => $campaign->get_product_selection_type(),
					'product_ids'            => $campaign->get_product_ids() ?: array(),
					'category_ids'           => $campaign->get_category_ids() ?: array(),
					'random_count'           => $metadata['random_count'] ?? 10,
					'conditions'             => $metadata['product_conditions'] ?? array(),
					'conditions_logic'       => $metadata['product_conditions_logic'] ?? 'all',
				);

			case 'discounts':
				$discount_rules = $campaign->get_discount_rules() ?: array();
				$discount_type  = $campaign->get_discount_type();
				$discount_value = $campaign->get_discount_value();

				return array(
					'discount_type'             => $discount_type,
					'discount_value_percentage' => 'percentage' === $discount_type ? $discount_value : '',
					'discount_value_fixed'      => 'fixed' === $discount_type ? $discount_value : '',
					'conditions'                => $discount_rules['conditions'] ?? array(),
					'conditions_logic'          => $discount_rules['conditions_logic'] ?? 'all',
					'usage_limit_per_customer'  => $discount_rules['usage_limit_per_customer'] ?? '',
					'total_usage_limit'         => $discount_rules['total_usage_limit'] ?? '',
					'apply_to'                  => $discount_rules['apply_to'] ?? 'per_item',
					'max_discount_amount'       => $discount_rules['max_discount_amount'] ?? '',
					'minimum_quantity'          => $discount_rules['minimum_quantity'] ?? '',
					'minimum_order_amount'      => $discount_rules['minimum_order_amount'] ?? '',
					'stack_with_others'         => $discount_rules['stack_with_others'] ?? false,
					'allow_coupons'             => $discount_rules['allow_coupons'] ?? false,
					'apply_to_sale_items'       => $discount_rules['apply_to_sale_items'] ?? false,
				);

			case 'schedule':
				$starts_at = $campaign->get_starts_at();
				$ends_at   = $campaign->get_ends_at();
				$timezone  = $campaign->get_timezone();

				$start_split = $starts_at ? SCD_DateTime_Splitter::for_editing( $starts_at, $timezone ) : array();
				$end_split   = $ends_at ? SCD_DateTime_Splitter::for_editing( $ends_at, $timezone ) : array();

				return array(
					'start_type' => $starts_at ? 'scheduled' : 'immediate',
					'start_date' => $start_split['date'] ?? '',
					'start_time' => $start_split['time'] ?? '00:00',
					'end_date'   => $end_split['date'] ?? '',
					'end_time'   => $end_split['time'] ?? '23:59',
					'timezone'   => $timezone,
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
	private function extract_changes_for_step( $step ) {
		if ( ! isset( $this->changes[ $step ] ) ) {
			return array();
		}

		$step_data = array();
		foreach ( $this->changes[ $step ] as $field => $change ) {
			$step_data[ $field ] = $change['value'];
		}

		return $step_data;
	}
}
