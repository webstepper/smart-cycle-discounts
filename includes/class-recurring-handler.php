<?php
/**
 * Recurring Handler Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/class-recurring-handler.php
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles recurring campaign functionality
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Recurring_Handler {

	/**
	 * Service container
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Container    $container    Service container
	 */
	private $container;

	/**
	 * Database manager
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Database_Manager    $db    Database manager
	 */
	private $db;

	/**
	 * Initialize the handler
	 *
	 * @since    1.0.0
	 * @param    SCD_Container $container    Service container
	 */
	public function __construct( SCD_Container $container ) {
		$this->container = $container;
		$this->db        = $container->get( 'database_manager' );

		// Hook into campaign save process
		add_action( 'scd_campaign_saved', array( $this, 'handle_recurring_setup' ), 10, 2 );

		add_action( 'scd_check_recurring_campaigns', array( $this, 'check_recurring_campaigns' ) );
		add_action( 'scd_create_recurring_campaign', array( $this, 'create_recurring_campaign' ) );

		// Schedule daily check if not already scheduled
		if ( ! wp_next_scheduled( 'scd_check_recurring_campaigns' ) ) {
			wp_schedule_event( time(), 'daily', 'scd_check_recurring_campaigns' );
		}
	}

	/**
	 * Handle recurring campaign setup after save
	 *
	 * @since    1.0.0
	 * @param    int   $campaign_id      Campaign ID
	 * @param    array $campaign_data    Campaign data
	 * @return   void
	 */
	public function handle_recurring_setup( $campaign_id, $campaign_data ) {
		if ( ! isset( $campaign_data['schedule']['enable_recurring'] ) ||
			! $campaign_data['schedule']['enable_recurring'] ) {
			$this->remove_recurring_settings( $campaign_id );
			return;
		}

		// Use Field Definitions for schedule data sanitization
		$sanitized_schedule = SCD_Validation::sanitize_step_data( $campaign_data['schedule'] ?? array(), 'schedule' );

		$recurring_data = array(
			'campaign_id'          => $campaign_id,
			'parent_campaign_id'   => 0, // This is the parent
			'recurrence_pattern'   => $sanitized_schedule['recurrence_pattern'] ?? 'daily',
			'recurrence_interval'  => $sanitized_schedule['recurrence_interval'] ?? 1,
			'recurrence_days'      => wp_json_encode( $sanitized_schedule['recurrence_days'] ?? array() ),
			'recurrence_end_type'  => $sanitized_schedule['recurrence_end_type'] ?? 'never',
			'recurrence_count'     => null,
			'recurrence_end_date'  => null,
			'occurrence_number'    => 1,
			'next_occurrence_date' => null,
			'is_active'            => 1,
		);

		// Handle end type specific fields
		if ( 'after' === $recurring_data['recurrence_end_type'] ) {
			$recurring_data['recurrence_count'] = $sanitized_schedule['recurrence_count'] ?? 10;
		} elseif ( 'on' === $recurring_data['recurrence_end_type'] ) {
			if ( ! empty( $sanitized_schedule['recurrence_end_date'] ) ) {
				$recurring_data['recurrence_end_date'] = $sanitized_schedule['recurrence_end_date'];
			}
		}

		$recurring_data['next_occurrence_date'] = $this->calculate_next_occurrence(
			$campaign_data['schedule'],
			$recurring_data
		);

		$this->save_recurring_settings( $campaign_id, $recurring_data );
	}

	/**
	 * Save recurring settings
	 *
	 * @since    1.0.0
	 * @param    int   $campaign_id        Campaign ID
	 * @param    array $recurring_data     Recurring data
	 * @return   bool                         Success status
	 */
	private function save_recurring_settings( $campaign_id, $recurring_data ) {
		$table_name = $this->db->get_table_name( 'campaign_recurring' );

		$existing = $this->db->get_row(
			$this->db->prepare(
				"SELECT id FROM $table_name WHERE campaign_id = %d",
				$campaign_id
			)
		);

		if ( $existing ) {
			unset( $recurring_data['campaign_id'] ); // Don't update campaign_id
			return $this->db->update(
				'campaign_recurring',
				$recurring_data,
				array( 'campaign_id' => $campaign_id )
			) !== false;
		} else {
			// Insert new settings
			return $this->db->insert( 'campaign_recurring', $recurring_data ) !== false;
		}
	}

	/**
	 * Remove recurring settings
	 *
	 * @since    1.0.0
	 * @param    int $campaign_id    Campaign ID
	 * @return   bool                   Success status
	 */
	private function remove_recurring_settings( $campaign_id ) {
		return $this->db->delete(
			'campaign_recurring',
			array( 'campaign_id' => $campaign_id )
		) !== false;
	}

	/**
	 * Sanitize recurrence days array
	 *
	 * @since    1.0.0
	 * @param    array $days    Days array
	 * @return   string            JSON encoded sanitized days
	 */
	private function sanitize_recurrence_days( $days ) {
		if ( ! is_array( $days ) ) {
			return wp_json_encode( array() );
		}

		$valid_days = array( 'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun' );
		$sanitized  = array();

		foreach ( $days as $day ) {
			$day = sanitize_text_field( $day );
			if ( in_array( $day, $valid_days, true ) ) {
				array_push( $sanitized, $day );
			}
		}

		return wp_json_encode( array_unique( $sanitized ) );
	}

	/**
	 * Calculate next occurrence date
	 *
	 * @since    1.0.0
	 * @param    array $schedule          Schedule data
	 * @param    array $recurring_data    Recurring data
	 * @return   string                      Next occurrence datetime
	 */
	private function calculate_next_occurrence( $schedule, $recurring_data ) {
		$end_date = $schedule['end_date'] ?? '';
		$end_time = $schedule['end_time'] ?? '23:59';

		if ( empty( $end_date ) ) {
			// If no end date, use start date + 7 days as default
			$start_date = $schedule['start_date'] ?? scd_current_time()->format( 'Y-m-d' );

			// Use DateTime for date calculation
			$start_datetime = new DateTime( $start_date, wp_timezone() );
			$start_datetime->modify( '+7 days' );
			$end_date = $start_datetime->format( 'Y-m-d' );
		}

		// Create DateTime object for end of current campaign (in WordPress timezone)
		$current_end = scd_combine_date_time( $end_date, $end_time, wp_timezone_string() );

		// Handle invalid datetime
		if ( ! $current_end ) {
			// Fallback to simple date parsing
			$current_end = new DateTime( $end_date . ' ' . $end_time, wp_timezone() );
		}

		$next_start = clone $current_end;

		switch ( $recurring_data['recurrence_pattern'] ) {
			case 'daily':
				$next_start->modify( '+' . $recurring_data['recurrence_interval'] . ' days' );
				break;

			case 'weekly':
				$next_start->modify( '+' . ( $recurring_data['recurrence_interval'] * 7 ) . ' days' );
				break;

			case 'monthly':
				$next_start->modify( '+' . $recurring_data['recurrence_interval'] . ' months' );
				break;
		}

		$next_start->setTime( 0, 0, 0 );

		return $next_start->format( 'Y-m-d H:i:s' );
	}

	/**
	 * Get recurring settings for a campaign
	 *
	 * @since    1.0.0
	 * @param    int $campaign_id    Campaign ID
	 * @return   array|null            Recurring settings or null
	 */
	public function get_recurring_settings( $campaign_id ) {
		$table_name = $this->db->get_table_name( 'campaign_recurring' );

		$settings = $this->db->get_row(
			$this->db->prepare(
				"SELECT * FROM $table_name WHERE campaign_id = %d",
				$campaign_id
			),
			ARRAY_A
		);

		if ( $settings && ! empty( $settings['recurrence_days'] ) ) {
			$settings['recurrence_days'] = json_decode( $settings['recurrence_days'], true );
		}

		return $settings;
	}

	/**
	 * Check if campaign has recurring settings
	 *
	 * @since    1.0.0
	 * @param    int $campaign_id    Campaign ID
	 * @return   bool                   Has recurring settings
	 */
	public function has_recurring_settings( $campaign_id ) {
		$settings = $this->get_recurring_settings( $campaign_id );
		return ! empty( $settings );
	}

	/**
	 * Get all campaigns with recurring settings
	 *
	 * @since    1.0.0
	 * @return   array    Array of campaign IDs
	 */
	public function get_recurring_campaigns() {
		$table_name = $this->db->get_table_name( 'campaign_recurring' );

		$results = $this->db->get_results(
			"SELECT campaign_id FROM $table_name WHERE is_active = 1",
			ARRAY_A
		);

		return array_column( $results, 'campaign_id' );
	}

	/**
	 * Check for recurring campaigns that need to be created
	 * Called by daily cron job
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function check_recurring_campaigns() {
		$table_name = $this->db->get_table_name( 'campaign_recurring' );
		$now        = current_time( 'mysql' );

		$results = $this->db->get_results(
			$this->db->prepare(
				"SELECT * FROM $table_name 
				WHERE is_active = 1 
				AND next_occurrence_date IS NOT NULL 
				AND next_occurrence_date <= %s",
				$now
			),
			ARRAY_A
		);

		if ( ! empty( $results ) ) {
			foreach ( $results as $recurring ) {
				// Schedule immediate creation of this campaign
				wp_schedule_single_event( time(), 'scd_create_recurring_campaign', array( (int) $recurring['campaign_id'] ) );
			}
		}
	}

	/**
	 * Create the next occurrence of a recurring campaign
	 *
	 * @since    1.0.0
	 * @param    int $parent_campaign_id    Parent campaign ID
	 * @return   void
	 */
	public function create_recurring_campaign( $parent_campaign_id ) {
		$recurring = $this->get_recurring_settings( $parent_campaign_id );
		if ( ! $recurring || ! $recurring['is_active'] ) {
			return;
		}

		if ( $this->should_stop_recurring( $recurring ) ) {
			// Deactivate recurring
			$this->db->update(
				'campaign_recurring',
				array( 'is_active' => 0 ),
				array( 'campaign_id' => $parent_campaign_id )
			);
			return;
		}

		$campaign_repo   = $this->container->get( 'campaign_repository' );
		$parent_campaign = $campaign_repo->find( $parent_campaign_id );

		if ( ! $parent_campaign ) {
			return;
		}

		$new_dates = $this->calculate_new_campaign_dates( $parent_campaign, $recurring );

		$new_campaign = $parent_campaign;
		unset( $new_campaign['id'] ); // Remove ID so it creates new

		$new_campaign['schedule']['start_date'] = $new_dates['start_date'];
		$new_campaign['schedule']['end_date']   = $new_dates['end_date'];
		$new_campaign['schedule']['start_type'] = 'scheduled';

		$occurrence_num       = $recurring['occurrence_number'] + 1;
		$new_campaign['name'] = $parent_campaign['name'] . ' (Occurrence ' . $occurrence_num . ')';

		$new_campaign['schedule']['enable_recurring'] = false;

		$campaign_manager = $this->container->get( 'campaign_manager' );
		$new_campaign_id  = $campaign_manager->create_campaign( $new_campaign );

		if ( $new_campaign_id ) {
			$next_occurrence = $this->calculate_next_occurrence(
				array(
					'end_date' => $new_dates['end_date'],
					'end_time' => $new_campaign['schedule']['end_time'],
				),
				$recurring
			);

			$this->db->update(
				'campaign_recurring',
				array(
					'occurrence_number'    => $occurrence_num,
					'next_occurrence_date' => $next_occurrence,
				),
				array( 'campaign_id' => $parent_campaign_id )
			);

			$this->db->insert(
				'campaign_recurring',
				array(
					'campaign_id'        => $new_campaign_id,
					'parent_campaign_id' => $parent_campaign_id,
					'occurrence_number'  => $occurrence_num,
					'is_active'          => 0, // Child campaigns don't recurse
				)
			);
		}
	}

	/**
	 * Check if recurring should stop
	 *
	 * @since    1.0.0
	 * @param    array $recurring    Recurring settings
	 * @return   bool                   Should stop
	 */
	private function should_stop_recurring( $recurring ) {
		if ( 'never' === $recurring['recurrence_end_type'] ) {
			return false;
		}

		if ( 'after' === $recurring['recurrence_end_type'] ) {
			return $recurring['occurrence_number'] >= $recurring['recurrence_count'];
		}

		if ( 'on' === $recurring['recurrence_end_type'] && ! empty( $recurring['recurrence_end_date'] ) ) {
			$end_date = new DateTime( $recurring['recurrence_end_date'] );
			// Use UTC timezone to match campaign dates (which are stored in UTC)
			$today = new DateTime( 'now', new DateTimeZone( 'UTC' ) );
			return $today >= $end_date;
		}

		return false;
	}

	/**
	 * Calculate dates for new campaign
	 *
	 * @since    1.0.0
	 * @param    array $parent_campaign    Parent campaign data
	 * @param    array $recurring          Recurring settings
	 * @return   array                        New start and end dates
	 */
	private function calculate_new_campaign_dates( $parent_campaign, $recurring ) {
		$start_date = new DateTime( $recurring['next_occurrence_date'] );
		$start_date->setTime( 0, 0, 0 ); // Start at beginning of day

		$parent_start = new DateTime( $parent_campaign['schedule']['start_date'] );
		$parent_end   = new DateTime( $parent_campaign['schedule']['end_date'] );
		$duration     = $parent_start->diff( $parent_end );

		// Apply duration to get end date
		$end_date = clone $start_date;
		$end_date->add( $duration );

		return array(
			'start_date' => $start_date->format( 'Y-m-d' ),
			'end_date'   => $end_date->format( 'Y-m-d' ),
		);
	}

	/**
	 * Cleanup method for deactivation
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'scd_check_recurring_campaigns' );

		$cron = _get_cron_array();
		foreach ( $cron as $timestamp => $hooks ) {
			if ( isset( $hooks['scd_create_recurring_campaign'] ) ) {
				unset( $cron[ $timestamp ]['scd_create_recurring_campaign'] );
				if ( empty( $cron[ $timestamp ] ) ) {
					unset( $cron[ $timestamp ] );
				}
			}
		}
		_set_cron_array( $cron );
	}
}
