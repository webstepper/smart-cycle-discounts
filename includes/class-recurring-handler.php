<?php
/**
 * Recurring Handler Class (Refactored)
 *
 * Clean implementation using ActionScheduler and occurrence cache
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes
 * @author     Webstepper <contact@webstepper.io>
 * @since      1.1.0
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Recurring Campaign Handler
 *
 * @since 1.1.0
 */
class WSSCD_Recurring_Handler {

	/**
	 * Service container
	 *
	 * @var WSSCD_Container
	 */
	private $container;

	/**
	 * Database instance
	 *
	 * @var wpdb
	 */
	private $wpdb;

	/**
	 * Logger instance
	 *
	 * @var WSSCD_Logger
	 */
	private $logger;

	/**
	 * Occurrence cache
	 *
	 * @var WSSCD_Occurrence_Cache
	 */
	private $cache;

	/**
	 * ActionScheduler service
	 *
	 * @var WSSCD_Action_Scheduler_Service
	 */
	private $scheduler;

	/**
	 * Campaign repository
	 *
	 * @var WSSCD_Campaign_Repository
	 */
	private $campaign_repo;

	/**
	 * Campaign manager
	 *
	 * @var WSSCD_Campaign_Manager
	 */
	private $campaign_manager;

	/**
	 * Recurring table name
	 *
	 * @var string
	 */
	private $recurring_table;

	/**
	 * Max retry attempts for failed materialization
	 *
	 * @var int
	 */
	private $max_retries = 3;

	/**
	 * Initialize recurring handler
	 *
	 * @since 1.1.0
	 * @param WSSCD_Container $container Service container.
	 */
	public function __construct( WSSCD_Container $container ) {
		global $wpdb;

		$this->container        = $container;
		$this->wpdb             = $wpdb;
		$this->logger           = $container->get( 'logger' );
		$this->cache            = $container->get( 'occurrence_cache' );
		$this->scheduler        = $container->get( 'action_scheduler' );
		$this->campaign_repo    = $container->get( 'campaign_repository' );
		$this->campaign_manager = $container->get( 'campaign_manager' );
		$this->recurring_table  = $wpdb->prefix . 'wsscd_campaign_recurring';

		$this->register_hooks();
	}

	/**
	 * Register action hooks
	 *
	 * @since 1.1.0
	 */
	private function register_hooks(): void {
		add_action( 'wsscd_campaign_saved', array( $this, 'handle_campaign_save' ), 10, 2 );
		add_action( 'wsscd_materialize_occurrence', array( $this, 'materialize_occurrence' ), 10, 2 );
		add_action( 'wsscd_cleanup_old_occurrences', array( $this, 'cleanup_old_occurrences' ) );
		add_action( 'wsscd_check_due_occurrences', array( $this, 'check_due_occurrences' ) );

		// Schedule recurring jobs after ActionScheduler is initialized
		add_action( 'init', array( $this, 'schedule_recurring_jobs' ), 20 );
	}

	/**
	 * Schedule recurring jobs using ActionScheduler
	 *
	 * Deferred to init hook to ensure ActionScheduler is initialized.
	 *
	 * @since 1.1.0
	 */
	public function schedule_recurring_jobs(): void {
		// Ensure ActionScheduler is available
		if ( ! function_exists( 'as_has_scheduled_action' ) ) {
			return;
		}
		// Daily check for due occurrences (midnight)
		if ( ! as_has_scheduled_action( 'wsscd_check_due_occurrences' ) ) {
			as_schedule_recurring_action(
				strtotime( 'tomorrow midnight' ),
				DAY_IN_SECONDS,
				'wsscd_check_due_occurrences',
				array(),
				'recurring_campaigns'
			);
		}

		// Daily cleanup (3am)
		if ( ! as_has_scheduled_action( 'wsscd_cleanup_old_occurrences' ) ) {
			as_schedule_recurring_action(
				strtotime( 'tomorrow 3am' ),
				DAY_IN_SECONDS,
				'wsscd_cleanup_old_occurrences',
				array(),
				'recurring_campaigns'
			);
		}
	}

	/**
	 * Handle campaign save event
	 *
	 * @since  1.1.0
	 * @param  int   $campaign_id Campaign ID.
	 * @param  array $data        Campaign data.
	 * @return void
	 */
	public function handle_campaign_save( int $campaign_id, array $data ): void {
		// Normalize data structure
		$schedule = $this->extract_schedule_data( $data );

		// Check if recurring is enabled
		if ( empty( $schedule[ WSSCD_Schedule_Field_Names::ENABLE_RECURRING ] ) ) {
			// Recurring disabled: remove any existing recurring row so one-time campaigns don't show recurring UI.
			$this->delete_recurring_row_for_campaign( $campaign_id );
			return;
		}

		// Extract recurring settings
		$recurring = $this->extract_recurring_data( $schedule );

		// Validate recurring data
		if ( ! $this->validate_recurring_data( $recurring ) ) {
			$this->logger->warning( 'Invalid recurring data', array( 'campaign_id' => $campaign_id ) );
			return;
		}

		// Save recurring settings to database
		$this->save_recurring_settings( $campaign_id, $recurring, $schedule );

		// Update campaign type based on mode
		$recurrence_mode = $recurring['recurrence_mode'] ?? 'continuous';
		$campaign_type   = 'continuous' === $recurrence_mode ? 'recurring_continuous' : 'recurring_parent';

		$this->wpdb->update(
			$this->wpdb->prefix . 'wsscd_campaigns',
			array( 'campaign_type' => $campaign_type ),
			array( 'id' => $campaign_id ),
			array( '%s' ),
			array( '%d' )
		);

		// For continuous mode, skip instance creation - the discount engine handles time-based activation
		if ( 'continuous' === $recurrence_mode ) {
			$this->logger->info(
				'Configured continuous recurring campaign',
				array(
					'campaign_id'      => $campaign_id,
					'recurrence_mode'  => 'continuous',
					'pattern'          => $recurring['recurrence_pattern'],
				)
			);
			return;
		}

		// For instances mode, generate occurrence cache and schedule materialization
		$count = $this->cache->regenerate( $campaign_id, $recurring, $schedule );

		// Schedule materialization events
		$this->schedule_occurrences( $campaign_id );

		$this->logger->info(
			'Configured recurring campaign with instances',
			array(
				'campaign_id'         => $campaign_id,
				'recurrence_mode'     => 'instances',
				'occurrences_cached'  => $count,
			)
		);
	}

	/**
	 * Extract schedule data from campaign data
	 *
	 * @since  1.1.0
	 * @param  array $data Campaign data.
	 * @return array       Schedule data.
	 */
	private function extract_schedule_data( array $data ): array {
		// Check if data is in step-based format
		if ( isset( $data['schedule'] ) && is_array( $data['schedule'] ) ) {
			return $data['schedule'];
		}

		// Extract schedule fields from flattened format
		$schedule_fields = array_merge(
			WSSCD_Schedule_Field_Names::get_fields(),
			array( 'start_date', 'start_time', 'end_date', 'end_time', 'timezone' )
		);

		$schedule = array();
		foreach ( $schedule_fields as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$schedule[ $field ] = $data[ $field ];
			}
		}

		return $schedule;
	}

	/**
	 * Extract recurring data from schedule
	 *
	 * @since  1.1.0
	 * @param  array $schedule Schedule data.
	 * @return array           Recurring data.
	 */
	private function extract_recurring_data( array $schedule ): array {
		$defaults = WSSCD_Schedule_Field_Names::get_defaults();

		return array(
			'recurrence_pattern'   => $schedule[ WSSCD_Schedule_Field_Names::RECURRENCE_PATTERN ] ?? $defaults[ WSSCD_Schedule_Field_Names::RECURRENCE_PATTERN ],
			'recurrence_interval'  => $schedule[ WSSCD_Schedule_Field_Names::RECURRENCE_INTERVAL ] ?? $defaults[ WSSCD_Schedule_Field_Names::RECURRENCE_INTERVAL ],
			'recurrence_days'      => $schedule[ WSSCD_Schedule_Field_Names::RECURRENCE_DAYS ] ?? $defaults[ WSSCD_Schedule_Field_Names::RECURRENCE_DAYS ],
			'recurrence_end_type'  => $schedule[ WSSCD_Schedule_Field_Names::RECURRENCE_END_TYPE ] ?? $defaults[ WSSCD_Schedule_Field_Names::RECURRENCE_END_TYPE ],
			'recurrence_count'     => $schedule[ WSSCD_Schedule_Field_Names::RECURRENCE_COUNT ] ?? $defaults[ WSSCD_Schedule_Field_Names::RECURRENCE_COUNT ],
			'recurrence_end_date'  => $schedule[ WSSCD_Schedule_Field_Names::RECURRENCE_END_DATE ] ?? $defaults[ WSSCD_Schedule_Field_Names::RECURRENCE_END_DATE ],
			'recurrence_mode'      => $schedule[ WSSCD_Schedule_Field_Names::RECURRENCE_MODE ] ?? $defaults[ WSSCD_Schedule_Field_Names::RECURRENCE_MODE ],
		);
	}

	/**
	 * Validate recurring data
	 *
	 * @since  1.1.0
	 * @param  array $recurring Recurring data.
	 * @return bool             Valid status.
	 */
	private function validate_recurring_data( array $recurring ): bool {
		// Validate pattern
		$valid_patterns = array( 'daily', 'weekly', 'monthly' );
		if ( ! in_array( $recurring['recurrence_pattern'], $valid_patterns, true ) ) {
			return false;
		}

		// Validate interval
		$interval = (int) $recurring['recurrence_interval'];
		if ( $interval < 1 || $interval > 365 ) {
			return false;
		}

		// Validate end type
		$valid_end_types = array( 'never', 'after', 'on' );
		if ( ! in_array( $recurring['recurrence_end_type'], $valid_end_types, true ) ) {
			return false;
		}

		// Validate recurrence mode
		$valid_modes = array( 'continuous', 'instances' );
		if ( isset( $recurring['recurrence_mode'] ) && ! in_array( $recurring['recurrence_mode'], $valid_modes, true ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Save recurring settings to database
	 *
	 * @since  1.1.0
	 * @param  int   $campaign_id Campaign ID.
	 * @param  array $recurring   Recurring data.
	 * @param  array $schedule    Schedule data.
	 * @return void
	 */
	private function save_recurring_settings( int $campaign_id, array $recurring, array $schedule ): void {
		// Prepare days data
		$days_json = is_array( $recurring['recurrence_days'] )
			? wp_json_encode( $recurring['recurrence_days'] )
			: $recurring['recurrence_days'];

		// Calculate first occurrence date
		$next_occurrence = $this->calculate_first_occurrence( $schedule );

		// Get recurrence mode (default to 'continuous' for new, 'instances' for backwards compatibility)
		$recurrence_mode = $recurring['recurrence_mode'] ?? 'continuous';

		// Check if record exists
		// SECURITY: Use %i placeholder for table identifier (WordPress 6.2+).
		$sql = $this->wpdb->prepare(
			'SELECT COUNT(*) FROM %i WHERE campaign_id = %d',
			$this->recurring_table,
			$campaign_id
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query is prepared above with $wpdb->prepare().
		$exists = $this->wpdb->get_var( $sql );

		if ( $exists ) {
			// Update existing record
			$this->wpdb->update(
				$this->recurring_table,
				array(
					'recurrence_pattern'   => $recurring['recurrence_pattern'],
					'recurrence_interval'  => $recurring['recurrence_interval'],
					'recurrence_days'      => $days_json,
					'recurrence_end_type'  => $recurring['recurrence_end_type'],
					'recurrence_count'     => $recurring['recurrence_count'],
					'recurrence_end_date'  => $recurring['recurrence_end_date'],
					'recurrence_mode'      => $recurrence_mode,
					'next_occurrence_date' => $next_occurrence,
					'is_active'            => 1,
					'updated_at'           => current_time( 'mysql' ),
				),
				array( 'campaign_id' => $campaign_id ),
				array( '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%s' ),
				array( '%d' )
			);
		} else {
			// Insert new record
			$this->wpdb->insert(
				$this->recurring_table,
				array(
					'campaign_id'          => $campaign_id,
					'parent_campaign_id'   => 0,
					'recurrence_pattern'   => $recurring['recurrence_pattern'],
					'recurrence_interval'  => $recurring['recurrence_interval'],
					'recurrence_days'      => $days_json,
					'recurrence_end_type'  => $recurring['recurrence_end_type'],
					'recurrence_count'     => $recurring['recurrence_count'],
					'recurrence_end_date'  => $recurring['recurrence_end_date'],
					'recurrence_mode'      => $recurrence_mode,
					'occurrence_number'    => 1,
					'next_occurrence_date' => $next_occurrence,
					'is_active'            => 1,
					'created_at'           => current_time( 'mysql' ),
					'updated_at'           => current_time( 'mysql' ),
				),
				array( '%d', '%d', '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%d', '%s', '%s' )
			);
		}
	}

	/**
	 * Delete recurring row for a campaign (when recurring is disabled).
	 * Prevents one-time campaigns from showing recurring schedule UI due to stale data.
	 *
	 * @since  1.1.0
	 * @param  int $campaign_id Campaign ID.
	 * @return void
	 */
	private function delete_recurring_row_for_campaign( int $campaign_id ): void {
		if ( $campaign_id <= 0 ) {
			return;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Intentional delete; table name from trusted prefix.
		$this->wpdb->delete(
			$this->recurring_table,
			array( 'campaign_id' => $campaign_id ),
			array( '%d' )
		);
	}

	/**
	 * Calculate first occurrence date
	 *
	 * @since  1.1.0
	 * @param  array $schedule Schedule data.
	 * @return string          Next occurrence datetime.
	 */
	private function calculate_first_occurrence( array $schedule ): string {
		$end_date   = $schedule['end_date'] ?? '';
		$end_time   = $schedule['end_time'] ?? '23:59';
		$tz_string  = isset( $schedule['timezone'] ) && is_string( $schedule['timezone'] ) && '' !== trim( $schedule['timezone'] ) ? $schedule['timezone'] : wp_timezone_string();

		try {
			$tz = new DateTimeZone( $tz_string );
			$end_datetime = new DateTime( $end_date . ' ' . $end_time, $tz );
			$end_datetime->setTimezone( new DateTimeZone( 'UTC' ) );
			return $end_datetime->format( 'Y-m-d H:i:s' );
		} catch ( Exception $e ) {
			return gmdate( 'Y-m-d H:i:s' );
		}
	}

	/**
	 * Schedule materialization events for pending occurrences
	 *
	 * @since  1.1.0
	 * @param  int $parent_id Parent campaign ID.
	 * @return int            Number of events scheduled.
	 */
	private function schedule_occurrences( int $parent_id ): int {
		$occurrences = $this->cache->get_preview( $parent_id, 100 );
		$scheduled   = 0;

		foreach ( $occurrences as $occurrence ) {
			if ( 'pending' !== $occurrence['status'] ) {
				continue;
			}

			// Schedule 5 minutes before occurrence start
			$schedule_time = strtotime( $occurrence['occurrence_start'] ) - ( 5 * MINUTE_IN_SECONDS );

			// Skip if in the past
			if ( $schedule_time < time() ) {
				$schedule_time = time() + MINUTE_IN_SECONDS; // Schedule ASAP.
			}

			$this->scheduler->schedule_single(
				'wsscd_materialize_occurrence',
				$schedule_time,
				array(
					'parent_id'         => $parent_id,
					'occurrence_number' => $occurrence['occurrence_number'],
				),
				'recurring_' . $parent_id . '_' . $occurrence['occurrence_number']
			);

			++$scheduled;
		}

		return $scheduled;
	}

	/**
	 * Check for due occurrences and schedule materialization
	 *
	 * @since 1.1.0
	 */
	public function check_due_occurrences(): void {
		$due_occurrences = $this->cache->get_due_occurrences( 1440 ); // 24 hours ahead.

		foreach ( $due_occurrences as $occurrence ) {
			$parent_id         = (int) $occurrence['parent_campaign_id'];
			$occurrence_number = (int) $occurrence['occurrence_number'];

			// Check if already scheduled
			$hook = 'recurring_' . $parent_id . '_' . $occurrence_number;
			if ( function_exists( 'as_has_scheduled_action' ) && as_has_scheduled_action( 'wsscd_materialize_occurrence', array( 'parent_id' => $parent_id, 'occurrence_number' => $occurrence_number ), $hook ) ) {
				continue;
			}

			// Schedule materialization
			$schedule_time = strtotime( $occurrence['occurrence_start'] ) - ( 5 * MINUTE_IN_SECONDS );

			$this->scheduler->schedule_single(
				'wsscd_materialize_occurrence',
				$schedule_time,
				array(
					'parent_id'         => $parent_id,
					'occurrence_number' => $occurrence_number,
				),
				$hook
			);
		}

		$this->logger->info(
			'Checked due occurrences',
			array( 'found' => count( $due_occurrences ) )
		);
	}

	/**
	 * Materialize a pending occurrence (create campaign instance)
	 *
	 * @since  1.1.0
	 * @param  int $parent_id         Parent campaign ID.
	 * @param  int $occurrence_number Occurrence number.
	 * @return void
	 */
	public function materialize_occurrence( int $parent_id, int $occurrence_number ): void {
		try {
			// Get occurrence from cache
			$occurrence = $this->cache->get_occurrence( $parent_id, $occurrence_number );

			if ( ! $occurrence ) {
				throw new Exception( 'Occurrence not found in cache' );
			}

			if ( 'pending' !== $occurrence['status'] ) {
				$this->logger->warning(
					'Occurrence already materialized',
					array(
						'parent_id'         => $parent_id,
						'occurrence_number' => $occurrence_number,
						'status'            => $occurrence['status'],
					)
				);
				return;
			}

			// Load parent campaign
			$parent = $this->campaign_repo->find( $parent_id );

			if ( ! $parent ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages are for logging/debugging, not direct output.
				throw new Exception( 'Parent campaign not found: ' . absint( $parent_id ) );
			}

			// Create instance
			$instance_data = $this->prepare_instance_data( $parent, $occurrence );
			$instance_id   = $this->create_campaign_instance( $instance_data );

			if ( ! $instance_id ) {
				throw new Exception( 'Failed to create campaign instance' );
			}

			// Update cache
			$this->cache->mark_materialized( (int) $occurrence['id'], $instance_id );

			// Clear retry counter
			delete_transient( 'wsscd_retry_' . $parent_id . '_' . $occurrence_number );

			$this->logger->info(
				'Materialized occurrence',
				array(
					'parent_id'         => $parent_id,
					'occurrence_number' => $occurrence_number,
					'instance_id'       => $instance_id,
				)
			);

		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to materialize occurrence',
				array(
					'parent_id'         => $parent_id,
					'occurrence_number' => $occurrence_number,
					'error'             => $e->getMessage(),
				)
			);

			$this->handle_materialization_failure( $parent_id, $occurrence_number, $occurrence, $e->getMessage() );
		}
	}

	/**
	 * Prepare instance data from parent and occurrence
	 *
	 * @since  1.1.0
	 * @param  WSSCD_Campaign $parent     Parent campaign.
	 * @param  array        $occurrence Occurrence data.
	 * @return array                    Instance data.
	 */
	private function prepare_instance_data( WSSCD_Campaign $parent, array $occurrence ): array {
		$data = $parent->to_array();

		// Remove parent-specific fields
		unset(
			$data['id'],
			$data['uuid'],
			$data['slug'],
			$data['name'],
			$data['created_at'],
			$data['updated_at'],
			$data['enable_recurring']
		);

		// Set instance-specific fields
		$occurrence_num             = (int) $occurrence['occurrence_number'];
		$data['name']               = $parent->get_name() . ' #' . $occurrence_num;
		$data['slug']               = $parent->get_slug() . '-occ-' . $occurrence_num;
		$data['starts_at']          = $occurrence['occurrence_start'];
		$data['ends_at']            = $occurrence['occurrence_end'];
		$data['status']             = 'scheduled';
		$data['enable_recurring']   = false;

		return $data;
	}

	/**
	 * Create campaign instance
	 *
	 * @since  1.1.0
	 * @param  array $instance_data Instance data.
	 * @return int                  Instance campaign ID.
	 */
	private function create_campaign_instance( array $instance_data ): int {
		$campaign = new WSSCD_Campaign( $instance_data );

		if ( ! $campaign->is_valid() ) {
			throw new Exception( 'Invalid campaign instance data' );
		}

		if ( ! $this->campaign_repo->save( $campaign ) ) {
			throw new Exception( 'Failed to save campaign instance' );
		}

		$instance_id = $campaign->get_id();

		// Update campaign type
		$this->wpdb->update(
			$this->wpdb->prefix . 'wsscd_campaigns',
			array( 'campaign_type' => 'recurring_instance' ),
			array( 'id' => $instance_id ),
			array( '%s' ),
			array( '%d' )
		);

		return $instance_id;
	}

	/**
	 * Handle materialization failure with retry logic
	 *
	 * @since  1.1.0
	 * @param  int    $parent_id         Parent campaign ID.
	 * @param  int    $occurrence_number Occurrence number.
	 * @param  array  $occurrence        Occurrence data.
	 * @param  string $error_message     Error message.
	 * @return void
	 */
	private function handle_materialization_failure( int $parent_id, int $occurrence_number, array $occurrence, string $error_message ): void {
		$retry_key = 'wsscd_retry_' . $parent_id . '_' . $occurrence_number;
		$retries   = (int) get_transient( $retry_key );

		if ( $retries < $this->max_retries ) {
			// Schedule retry
			set_transient( $retry_key, $retries + 1, HOUR_IN_SECONDS );

			$this->scheduler->schedule_single(
				'wsscd_materialize_occurrence',
				time() + HOUR_IN_SECONDS,
				array(
					'parent_id'         => $parent_id,
					'occurrence_number' => $occurrence_number,
				),
				'retry_' . $retry_key
			);

			$this->logger->info(
				'Scheduled retry for failed occurrence',
				array(
					'parent_id' => $parent_id,
					'attempt'   => $retries + 1,
				)
			);
		} else {
			// Max retries reached - mark as failed
			if ( isset( $occurrence['id'] ) ) {
				$this->cache->mark_failed( (int) $occurrence['id'], $error_message );
			}

			delete_transient( $retry_key );

			// Send admin notification
			$this->send_failure_notification( $parent_id, $occurrence_number, $error_message );
		}
	}

	/**
	 * Send admin notification for failed occurrence
	 *
	 * @since  1.1.0
	 * @param  int    $parent_id         Parent campaign ID.
	 * @param  int    $occurrence_number Occurrence number.
	 * @param  string $error             Error message.
	 * @return void
	 */
	private function send_failure_notification( int $parent_id, int $occurrence_number, string $error ): void {
		$admin_email = get_option( 'admin_email' );

		$subject = sprintf(
			/* translators: %d: Campaign ID */
			__( '[Smart Cycle Discounts] Recurring Campaign Failed (ID: %d)', 'smart-cycle-discounts' ),
			$parent_id
		);

		$message = sprintf(
			/* translators: 1: Occurrence number, 2: Campaign ID, 3: Error message */
			__( "Failed to create occurrence #%1\$d for campaign #%2\$d after %3\$d attempts.\n\nError: %4\$s\n\nPlease review the campaign settings in the admin panel.", 'smart-cycle-discounts' ),
			$occurrence_number,
			$parent_id,
			$this->max_retries,
			$error
		);

		wp_mail( $admin_email, $subject, $message );

		$this->logger->error(
			'Sent failure notification to admin',
			array(
				'parent_id'         => $parent_id,
				'occurrence_number' => $occurrence_number,
			)
		);
	}

	/**
	 * Cleanup old expired occurrences
	 *
	 * @since  1.1.0
	 * @param  int $retention_days Number of days to keep expired campaigns.
	 * @return int                 Number of deleted campaigns.
	 */
	public function cleanup_old_occurrences( int $retention_days = 30 ): int {
		$cutoff = gmdate( 'Y-m-d H:i:s', strtotime( '-' . $retention_days . ' days' ) );

		// Find expired instances
		// SECURITY: Use %i placeholder for table identifier (WordPress 6.2+).
		$campaigns_table = $this->wpdb->prefix . 'wsscd_campaigns';

		// SECURITY: Use %i placeholder for table identifier (WordPress 6.2+).
		$sql = $this->wpdb->prepare(
			'SELECT id FROM %i
			 WHERE campaign_type = %s
			 AND status = %s
			 AND ends_at < %s
			 LIMIT 100',
			$campaigns_table,
			'recurring_instance',
			'expired',
			$cutoff
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query is prepared above with $wpdb->prepare().
		$instances = $this->wpdb->get_col( $sql );

		if ( empty( $instances ) ) {
			return 0;
		}

		// Delete instances
		$deleted = 0;
		foreach ( $instances as $instance_id ) {
			if ( $this->campaign_repo->delete( (int) $instance_id ) ) {
				++$deleted;
			}
		}

		$this->logger->info(
			'Cleaned up old recurring occurrences',
			array( 'deleted_count' => $deleted )
		);

		return $deleted;
	}

	/**
	 * Get recurring settings for a campaign
	 *
	 * Returns recurring settings for both parent campaigns and instances.
	 * For instances, it finds the parent and returns its settings with occurrence info.
	 *
	 * @since  1.1.0
	 * @param  int        $campaign_id Campaign ID.
	 * @return array|null              Recurring settings or null if not found.
	 */
	public function get_recurring_settings( int $campaign_id ): ?array {
		// First, check if this is a parent campaign
		// SECURITY: Use %i placeholder for table identifier (WordPress 6.2+).
		$sql = $this->wpdb->prepare(
			'SELECT * FROM %i WHERE campaign_id = %d',
			$this->recurring_table,
			$campaign_id
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query is prepared above with $wpdb->prepare().
		$settings = $this->wpdb->get_row( $sql, ARRAY_A );

		if ( $settings ) {
			// Decode JSON days if present
			if ( isset( $settings['recurrence_days'] ) && is_string( $settings['recurrence_days'] ) ) {
				$settings['recurrence_days'] = json_decode( $settings['recurrence_days'], true );
			}
			return $settings;
		}

		// Not a parent - check if it's an instance (child campaign)
		// First verify the cache table exists to avoid database errors
		$cache_table = $this->wpdb->prefix . 'wsscd_recurring_cache';

		// Check if table exists
		// SHOW TABLES has no WP abstraction. Query IS prepared.
		$show_tables_sql = $this->wpdb->prepare( 'SHOW TABLES LIKE %s', $cache_table );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query is prepared above with $wpdb->prepare().
		$table_exists = $this->wpdb->get_var( $show_tables_sql );

		if ( ! $table_exists ) {
			// Table doesn't exist yet - not a recurring campaign
			return null;
		}

		// SECURITY: Use %i placeholder for table identifier (WordPress 6.2+).
		$occurrence_sql = $this->wpdb->prepare(
			'SELECT parent_campaign_id, occurrence_number FROM %i WHERE instance_id = %d',
			$cache_table,
			$campaign_id
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query is prepared above with $wpdb->prepare().
		$occurrence = $this->wpdb->get_row( $occurrence_sql, ARRAY_A );

		if ( ! $occurrence ) {
			// Not a recurring campaign at all
			return null;
		}

		// Get parent settings
		// SECURITY: Use %i placeholder for table identifier (WordPress 6.2+).
		$parent_sql = $this->wpdb->prepare(
			'SELECT * FROM %i WHERE campaign_id = %d',
			$this->recurring_table,
			$occurrence['parent_campaign_id']
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query is prepared above with $wpdb->prepare().
		$parent_settings = $this->wpdb->get_row( $parent_sql, ARRAY_A );

		if ( ! $parent_settings ) {
			return null;
		}

		// Decode JSON days if present
		if ( isset( $parent_settings['recurrence_days'] ) && is_string( $parent_settings['recurrence_days'] ) ) {
			$parent_settings['recurrence_days'] = json_decode( $parent_settings['recurrence_days'], true );
		}

		// Mark this as a child by setting parent_campaign_id
		$parent_settings['parent_campaign_id']  = $occurrence['parent_campaign_id'];
		$parent_settings['occurrence_number']   = $occurrence['occurrence_number'];
		$parent_settings['is_instance']         = true;

		return $parent_settings;
	}

	/**
	 * Delete all occurrences for a parent campaign
	 *
	 * @since  1.1.0
	 * @param  int $parent_id Parent campaign ID.
	 * @return int            Number of deleted instances.
	 */
	public function delete_parent_occurrences( int $parent_id ): int {
		// Get all instance IDs
		// SECURITY: Use %i placeholder for table identifier (WordPress 6.2+).
		$cache_table = $this->wpdb->prefix . 'wsscd_recurring_cache';

		$sql = $this->wpdb->prepare(
			'SELECT instance_id FROM %i
			 WHERE parent_campaign_id = %d
			 AND instance_id IS NOT NULL',
			$cache_table,
			$parent_id
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query is prepared above with $wpdb->prepare().
		$instances = $this->wpdb->get_col( $sql );

		// Delete occurrence cache
		$this->cache->delete_by_parent( $parent_id );

		// Delete recurring settings
		$this->wpdb->delete(
			$this->recurring_table,
			array( 'campaign_id' => $parent_id ),
			array( '%d' )
		);

		// Cancel scheduled events
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( 'wsscd_materialize_occurrence', array( 'parent_id' => $parent_id ), 'recurring_campaigns' );
		}

		// Delete campaign instances
		$deleted = 0;
		foreach ( $instances as $instance_id ) {
			if ( $instance_id && $this->campaign_repo->delete( (int) $instance_id ) ) {
				++$deleted;
			}
		}

		return $deleted;
	}

	/**
	 * Check if a continuous recurring campaign is currently in an active time window
	 *
	 * Used by the discount engine to determine if a continuous mode campaign
	 * should be applying discounts at the current time.
	 *
	 * @since  1.3.1
	 * @param  int         $campaign_id Campaign ID.
	 * @param  string|null $check_time  Optional. Time to check against (default: current time).
	 * @return bool                     True if campaign is in active window.
	 */
	public function is_in_active_window( int $campaign_id, ?string $check_time = null ): bool {
		// Get recurring settings
		$settings = $this->get_recurring_settings( $campaign_id );

		if ( ! $settings ) {
			return false;
		}

		// Only applies to continuous mode
		$recurrence_mode = $settings['recurrence_mode'] ?? 'continuous';
		if ( 'continuous' !== $recurrence_mode ) {
			return false;
		}

		// Check if recurring is active
		if ( empty( $settings['is_active'] ) ) {
			return false;
		}

		// Get campaign schedule (start/end times define the daily window)
		$campaign = $this->campaign_repo->find( $campaign_id );
		if ( ! $campaign ) {
			return false;
		}

		// Use campaign timezone so "current time" and daily window (start/end time) are in the same zone.
		$tz_string = $campaign->get_timezone();
		if ( empty( $tz_string ) || ! is_string( $tz_string ) ) {
			$tz_string = wp_timezone_string();
		}
		try {
			$timezone = new DateTimeZone( $tz_string );
		} catch ( Exception $e ) {
			$timezone = new DateTimeZone( wp_timezone_string() );
		}
		try {
			$now = $check_time ? new DateTime( $check_time, $timezone ) : new DateTime( 'now', $timezone );
		} catch ( Exception $e ) {
			return false;
		}

		// Check if we're within the recurring end conditions
		if ( ! $this->is_within_recurrence_bounds( $settings, $now ) ) {
			return false;
		}

		// Check if current day matches the recurrence pattern
		if ( ! $this->matches_recurrence_pattern( $settings, $now ) ) {
			return false;
		}

		// Check if current time is within the daily time window
		return $this->is_within_time_window( $campaign, $now );
	}

	/**
	 * Check if date is within recurrence bounds (end type constraints)
	 *
	 * @since  1.3.1
	 * @param  array    $settings Recurring settings.
	 * @param  DateTime $now      Current datetime.
	 * @return bool               True if within bounds.
	 */
	private function is_within_recurrence_bounds( array $settings, DateTime $now ): bool {
		$end_type = $settings['recurrence_end_type'] ?? 'never';

		switch ( $end_type ) {
			case 'never':
				return true;

			case 'on':
				$end_date = $settings['recurrence_end_date'] ?? '';
				if ( empty( $end_date ) ) {
					return true;
				}
				try {
					$end = new DateTime( $end_date . ' 23:59:59', $now->getTimezone() );
					return $now <= $end;
				} catch ( Exception $e ) {
					return true;
				}

			case 'after':
				// For 'after' X occurrences, we track via occurrence_number
				// Since continuous mode doesn't create instances, we calculate based on dates
				$count = (int) ( $settings['recurrence_count'] ?? 0 );
				if ( 0 === $count ) {
					return true;
				}
				// This would need more complex date calculation based on pattern
				// For now, allow if count is set (implementation can be refined)
				return true;

			default:
				return true;
		}
	}

	/**
	 * Check if current datetime matches the recurrence pattern
	 *
	 * @since  1.3.1
	 * @param  array    $settings Recurring settings.
	 * @param  DateTime $now      Current datetime.
	 * @return bool               True if matches pattern.
	 */
	private function matches_recurrence_pattern( array $settings, DateTime $now ): bool {
		$pattern  = $settings['recurrence_pattern'] ?? 'daily';
		$interval = (int) ( $settings['recurrence_interval'] ?? 1 );
		$days     = $settings['recurrence_days'] ?? array();

		// Ensure days is an array
		if ( is_string( $days ) ) {
			$days = json_decode( $days, true ) ?: array();
		}

		switch ( $pattern ) {
			case 'daily':
				// Daily pattern always matches (interval is for instances mode)
				return true;

			case 'weekly':
				// Check if today's day of week is in the selected days.
				// Form stores short names (mon, tue, ...); support both short (D) and full (l).
				$current_short = strtolower( $now->format( 'D' ) );
				$current_full  = strtolower( $now->format( 'l' ) );
				if ( empty( $days ) ) {
					return true; // No days specified = all days.
				}
				$days = array_map( 'strtolower', array_map( 'strval', (array) $days ) );
				return in_array( $current_short, $days, true ) || in_array( $current_full, $days, true );

			case 'monthly':
				// Check if today's date matches the selected days
				$current_date = (int) $now->format( 'j' );
				if ( empty( $days ) ) {
					return true; // No days specified = all days.
				}
				return in_array( $current_date, array_map( 'intval', $days ), true );

			default:
				return true;
		}
	}

	/**
	 * Check if current time is within the campaign's daily time window
	 *
	 * @since  1.3.1
	 * @param  WSSCD_Campaign $campaign Campaign object.
	 * @param  DateTime       $now      Current datetime.
	 * @return bool                     True if within time window.
	 */
	private function is_within_time_window( WSSCD_Campaign $campaign, DateTime $now ): bool {
		$starts_at = $campaign->get_starts_at();
		$ends_at   = $campaign->get_ends_at();

		if ( empty( $starts_at ) || empty( $ends_at ) ) {
			return true; // No time restriction.
		}

		try {
			$timezone = $now->getTimezone();

			// Extract time portion only (ignore date). Campaign may return DateTime or string.
			if ( $starts_at instanceof DateTime ) {
				$start_dt = clone $starts_at;
				$start_dt->setTimezone( $timezone );
			} else {
				$start_dt = new DateTime( $starts_at, $timezone );
			}
			if ( $ends_at instanceof DateTime ) {
				$end_dt = clone $ends_at;
				$end_dt->setTimezone( $timezone );
			} else {
				$end_dt = new DateTime( $ends_at, $timezone );
			}

			$start_time = $start_dt->format( 'H:i:s' );
			$end_time   = $end_dt->format( 'H:i:s' );
			$current_time = $now->format( 'H:i:s' );

			// Handle overnight time windows (e.g., 22:00 - 06:00)
			if ( $end_time < $start_time ) {
				// Overnight window: active if current time is after start OR before end
				return $current_time >= $start_time || $current_time <= $end_time;
			}

			// Normal window: active if current time is between start and end
			return $current_time >= $start_time && $current_time <= $end_time;

		} catch ( Exception $e ) {
			return true;
		}
	}

	/**
	 * Check if a campaign is a continuous recurring type
	 *
	 * @since  1.3.1
	 * @param  int $campaign_id Campaign ID.
	 * @return bool             True if continuous recurring.
	 */
	public function is_continuous_recurring( int $campaign_id ): bool {
		$settings = $this->get_recurring_settings( $campaign_id );

		if ( ! $settings ) {
			return false;
		}

		return 'continuous' === ( $settings['recurrence_mode'] ?? 'continuous' );
	}
}
