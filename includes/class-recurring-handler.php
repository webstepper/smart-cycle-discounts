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

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Recurring Campaign Handler
 *
 * @since 1.1.0
 */
class SCD_Recurring_Handler {

	/**
	 * Service container
	 *
	 * @var SCD_Container
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
	 * @var SCD_Logger
	 */
	private $logger;

	/**
	 * Occurrence cache
	 *
	 * @var SCD_Occurrence_Cache
	 */
	private $cache;

	/**
	 * ActionScheduler service
	 *
	 * @var SCD_Action_Scheduler_Service
	 */
	private $scheduler;

	/**
	 * Campaign repository
	 *
	 * @var SCD_Campaign_Repository
	 */
	private $campaign_repo;

	/**
	 * Campaign manager
	 *
	 * @var SCD_Campaign_Manager
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
	 * @param SCD_Container $container Service container.
	 */
	public function __construct( SCD_Container $container ) {
		global $wpdb;

		$this->container        = $container;
		$this->wpdb             = $wpdb;
		$this->logger           = $container->get( 'logger' );
		$this->cache            = $container->get( 'occurrence_cache' );
		$this->scheduler        = $container->get( 'action_scheduler' );
		$this->campaign_repo    = $container->get( 'campaign_repository' );
		$this->campaign_manager = $container->get( 'campaign_manager' );
		$this->recurring_table  = $wpdb->prefix . 'scd_campaign_recurring';

		$this->register_hooks();
	}

	/**
	 * Register action hooks
	 *
	 * @since 1.1.0
	 */
	private function register_hooks(): void {
		add_action( 'scd_campaign_saved', array( $this, 'handle_campaign_save' ), 10, 2 );
		add_action( 'scd_materialize_occurrence', array( $this, 'materialize_occurrence' ), 10, 2 );
		add_action( 'scd_cleanup_old_occurrences', array( $this, 'cleanup_old_occurrences' ) );
		add_action( 'scd_check_due_occurrences', array( $this, 'check_due_occurrences' ) );

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
		if ( ! as_has_scheduled_action( 'scd_check_due_occurrences' ) ) {
			as_schedule_recurring_action(
				strtotime( 'tomorrow midnight' ),
				DAY_IN_SECONDS,
				'scd_check_due_occurrences',
				array(),
				'recurring_campaigns'
			);
		}

		// Daily cleanup (3am)
		if ( ! as_has_scheduled_action( 'scd_cleanup_old_occurrences' ) ) {
			as_schedule_recurring_action(
				strtotime( 'tomorrow 3am' ),
				DAY_IN_SECONDS,
				'scd_cleanup_old_occurrences',
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
		if ( empty( $schedule[ SCD_Schedule_Field_Names::ENABLE_RECURRING ] ) ) {
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

		// Update campaign type
		$this->wpdb->update(
			$this->wpdb->prefix . 'scd_campaigns',
			array( 'campaign_type' => 'recurring_parent' ),
			array( 'id' => $campaign_id ),
			array( '%s' ),
			array( '%d' )
		);

		// Generate occurrence cache
		$count = $this->cache->regenerate( $campaign_id, $recurring, $schedule );

		// Schedule materialization events
		$this->schedule_occurrences( $campaign_id );

		$this->logger->info(
			'Configured recurring campaign',
			array(
				'campaign_id'         => $campaign_id,
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
			SCD_Schedule_Field_Names::get_fields(),
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
		$defaults = SCD_Schedule_Field_Names::get_defaults();

		return array(
			'recurrence_pattern'   => $schedule[ SCD_Schedule_Field_Names::RECURRENCE_PATTERN ] ?? $defaults[ SCD_Schedule_Field_Names::RECURRENCE_PATTERN ],
			'recurrence_interval'  => $schedule[ SCD_Schedule_Field_Names::RECURRENCE_INTERVAL ] ?? $defaults[ SCD_Schedule_Field_Names::RECURRENCE_INTERVAL ],
			'recurrence_days'      => $schedule[ SCD_Schedule_Field_Names::RECURRENCE_DAYS ] ?? $defaults[ SCD_Schedule_Field_Names::RECURRENCE_DAYS ],
			'recurrence_end_type'  => $schedule[ SCD_Schedule_Field_Names::RECURRENCE_END_TYPE ] ?? $defaults[ SCD_Schedule_Field_Names::RECURRENCE_END_TYPE ],
			'recurrence_count'     => $schedule[ SCD_Schedule_Field_Names::RECURRENCE_COUNT ] ?? $defaults[ SCD_Schedule_Field_Names::RECURRENCE_COUNT ],
			'recurrence_end_date'  => $schedule[ SCD_Schedule_Field_Names::RECURRENCE_END_DATE ] ?? $defaults[ SCD_Schedule_Field_Names::RECURRENCE_END_DATE ],
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

		// Check if record exists
		$exists = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->recurring_table} WHERE campaign_id = %d",
				$campaign_id
			)
		);

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
					'next_occurrence_date' => $next_occurrence,
					'is_active'            => 1,
					'updated_at'           => current_time( 'mysql' ),
				),
				array( 'campaign_id' => $campaign_id ),
				array( '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%d', '%s' ),
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
					'occurrence_number'    => 1,
					'next_occurrence_date' => $next_occurrence,
					'is_active'            => 1,
					'created_at'           => current_time( 'mysql' ),
					'updated_at'           => current_time( 'mysql' ),
				),
				array( '%d', '%d', '%s', '%d', '%s', '%s', '%d', '%s', '%d', '%s', '%d', '%s', '%s' )
			);
		}
	}

	/**
	 * Calculate first occurrence date
	 *
	 * @since  1.1.0
	 * @param  array $schedule Schedule data.
	 * @return string          Next occurrence datetime.
	 */
	private function calculate_first_occurrence( array $schedule ): string {
		$end_date = $schedule['end_date'] ?? '';
		$end_time = $schedule['end_time'] ?? '23:59';

		try {
			$end_datetime = new DateTime( $end_date . ' ' . $end_time, new DateTimeZone( wp_timezone_string() ) );
			return $end_datetime->format( 'Y-m-d H:i:s' );
		} catch ( Exception $e ) {
			return current_time( 'mysql' );
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
				'scd_materialize_occurrence',
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
			if ( function_exists( 'as_has_scheduled_action' ) && as_has_scheduled_action( 'scd_materialize_occurrence', array( 'parent_id' => $parent_id, 'occurrence_number' => $occurrence_number ), $hook ) ) {
				continue;
			}

			// Schedule materialization
			$schedule_time = strtotime( $occurrence['occurrence_start'] ) - ( 5 * MINUTE_IN_SECONDS );

			$this->scheduler->schedule_single(
				'scd_materialize_occurrence',
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
				throw new Exception( 'Parent campaign not found: ' . $parent_id );
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
			delete_transient( 'scd_retry_' . $parent_id . '_' . $occurrence_number );

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
	 * @param  SCD_Campaign $parent     Parent campaign.
	 * @param  array        $occurrence Occurrence data.
	 * @return array                    Instance data.
	 */
	private function prepare_instance_data( SCD_Campaign $parent, array $occurrence ): array {
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
		$campaign = new SCD_Campaign( $instance_data );

		if ( ! $campaign->is_valid() ) {
			throw new Exception( 'Invalid campaign instance data' );
		}

		if ( ! $this->campaign_repo->save( $campaign ) ) {
			throw new Exception( 'Failed to save campaign instance' );
		}

		$instance_id = $campaign->get_id();

		// Update campaign type
		$this->wpdb->update(
			$this->wpdb->prefix . 'scd_campaigns',
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
		$retry_key = 'scd_retry_' . $parent_id . '_' . $occurrence_number;
		$retries   = (int) get_transient( $retry_key );

		if ( $retries < $this->max_retries ) {
			// Schedule retry
			set_transient( $retry_key, $retries + 1, HOUR_IN_SECONDS );

			$this->scheduler->schedule_single(
				'scd_materialize_occurrence',
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
		$instances = $this->wpdb->get_col(
			$this->wpdb->prepare(
				"SELECT id FROM {$this->wpdb->prefix}scd_campaigns
				 WHERE campaign_type = 'recurring_instance'
				 AND status = 'expired'
				 AND ends_at < %s
				 LIMIT 100",
				$cutoff
			)
		);

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
	 * Get recurring settings for multiple campaigns in one query
	 *
	 * Optimized batch method to avoid N+1 queries in list views.
	 *
	 * @since  1.1.0
	 * @param  array $campaign_ids Array of campaign IDs.
	 * @return array               Array of recurring settings keyed by campaign_id.
	 */
	public function get_batch_recurring_settings( array $campaign_ids ): array {
		if ( empty( $campaign_ids ) ) {
			return array();
		}

		// Sanitize campaign IDs
		$campaign_ids = array_map( 'intval', $campaign_ids );
		$placeholders = implode( ',', array_fill( 0, count( $campaign_ids ), '%d' ) );

		// Fetch all parent settings in one query
		$parent_settings = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->recurring_table} WHERE campaign_id IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$campaign_ids
			),
			ARRAY_A
		);

		// Index by campaign_id
		$settings_map = array();
		foreach ( $parent_settings as $settings ) {
			$campaign_id = (int) $settings['campaign_id'];

			// Decode JSON days if present
			if ( isset( $settings['recurrence_days'] ) && is_string( $settings['recurrence_days'] ) ) {
				$settings['recurrence_days'] = json_decode( $settings['recurrence_days'], true );
			}

			$settings_map[ $campaign_id ] = $settings;
		}

		// Check for instances (campaigns not found in parent settings)
		$cache_table = $this->wpdb->prefix . 'scd_recurring_cache';

		// Verify cache table exists
		$table_exists = $this->wpdb->get_var(
			$this->wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$cache_table
			)
		);

		if ( ! $table_exists ) {
			return $settings_map;
		}

		$missing_ids = array_diff( $campaign_ids, array_keys( $settings_map ) );

		if ( ! empty( $missing_ids ) ) {
			$missing_placeholders = implode( ',', array_fill( 0, count( $missing_ids ), '%d' ) );

			// Fetch occurrence data for instances
			$occurrences = $this->wpdb->get_results(
				$this->wpdb->prepare(
					"SELECT instance_id, parent_campaign_id, occurrence_number FROM {$cache_table} WHERE instance_id IN ({$missing_placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$missing_ids
				),
				ARRAY_A
			);

			if ( ! empty( $occurrences ) ) {
				// Get unique parent IDs
				$parent_ids = array_unique( wp_list_pluck( $occurrences, 'parent_campaign_id' ) );
				$parent_placeholders = implode( ',', array_fill( 0, count( $parent_ids ), '%d' ) );

				// Fetch parent settings
				$parent_data = $this->wpdb->get_results(
					$this->wpdb->prepare(
						"SELECT * FROM {$this->recurring_table} WHERE campaign_id IN ({$parent_placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$parent_ids
					),
					ARRAY_A
				);

				// Index parent data by campaign_id
				$parent_data_map = array();
				foreach ( $parent_data as $parent ) {
					$parent_data_map[ (int) $parent['campaign_id'] ] = $parent;
				}

				// Add instance settings to map
				foreach ( $occurrences as $occurrence ) {
					$instance_id = (int) $occurrence['instance_id'];
					$parent_id   = (int) $occurrence['parent_campaign_id'];

					if ( isset( $parent_data_map[ $parent_id ] ) ) {
						$instance_settings = $parent_data_map[ $parent_id ];

						// Decode JSON days if present
						if ( isset( $instance_settings['recurrence_days'] ) && is_string( $instance_settings['recurrence_days'] ) ) {
							$instance_settings['recurrence_days'] = json_decode( $instance_settings['recurrence_days'], true );
						}

						// Mark as instance
						$instance_settings['parent_campaign_id'] = $parent_id;
						$instance_settings['occurrence_number']  = (int) $occurrence['occurrence_number'];
						$instance_settings['is_instance']        = true;

						$settings_map[ $instance_id ] = $instance_settings;
					}
				}
			}
		}

		return $settings_map;
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
		$settings = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->recurring_table} WHERE campaign_id = %d",
				$campaign_id
			),
			ARRAY_A
		);

		if ( $settings ) {
			// Decode JSON days if present
			if ( isset( $settings['recurrence_days'] ) && is_string( $settings['recurrence_days'] ) ) {
				$settings['recurrence_days'] = json_decode( $settings['recurrence_days'], true );
			}
			return $settings;
		}

		// Not a parent - check if it's an instance (child campaign)
		// First verify the cache table exists to avoid database errors
		$cache_table = $this->wpdb->prefix . 'scd_recurring_cache';

		// Check if table exists
		$table_exists = $this->wpdb->get_var(
			$this->wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$cache_table
			)
		);

		if ( ! $table_exists ) {
			// Table doesn't exist yet - not a recurring campaign
			return null;
		}

		$occurrence = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT parent_campaign_id, occurrence_number FROM {$cache_table} WHERE instance_id = %d",
				$campaign_id
			),
			ARRAY_A
		);

		if ( ! $occurrence ) {
			// Not a recurring campaign at all
			return null;
		}

		// Get parent settings
		$parent_settings = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->recurring_table} WHERE campaign_id = %d",
				$occurrence['parent_campaign_id']
			),
			ARRAY_A
		);

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
		$instances = $this->wpdb->get_col(
			$this->wpdb->prepare(
				"SELECT instance_id FROM {$this->wpdb->prefix}scd_recurring_cache
				 WHERE parent_campaign_id = %d
				 AND instance_id IS NOT NULL",
				$parent_id
			)
		);

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
			as_unschedule_all_actions( 'scd_materialize_occurrence', array( 'parent_id' => $parent_id ), 'recurring_campaigns' );
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
}
