<?php
/**
 * Campaign Manager Service
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/campaigns
 * @since      1.0.0
 */

declare(strict_types=1);


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


if ( ! class_exists( 'SCD_Campaign' ) ) {
	require_once __DIR__ . '/class-campaign.php';
}

if ( ! class_exists( 'SCD_Validation' ) ) {
	require_once dirname( __DIR__ ) . '/validation/class-validation.php';
}

/**
 * Campaign Manager
 *
 * Handles campaign business logic and operations.
 *
 * PRIORITY SYSTEM:
 * ===============
 * Lower numbers = Higher priority (1 = highest, 10 = lowest)
 * - Priority 1: Always wins conflicts (highest)
 * - Priority 5: Default/normal
 * - Priority 10: Gets blocked by everything (lowest)
 *
 * When multiple campaigns apply to same product:
 * 1. Campaigns sorted ascending (1, 2, 3... 10)
 * 2. First campaign wins and applies discount
 * 3. Other campaigns blocked for that product
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/campaigns
 */
class SCD_Campaign_Manager {

	/**
	 * Campaign repository.
	 *
	 * @since    1.0.0
	 * @var      object|null
	 */
	private ?object $repository;

	/**
	 * Logger instance.
	 *
	 * @since    1.0.0
	 * @var      object|null
	 */
	private ?object $logger;

	/**
	 * Cache manager.
	 *
	 * @since    1.0.0
	 * @var      object|null
	 */
	private ?object $cache;

	/**
	 * Service container.
	 *
	 * @since    1.0.0
	 * @var      object|null
	 */
	private ?object $container;

	/**
	 * Constructor.
	 *
	 * @since    1.0.0
	 * @param    object|null $repository    Campaign repository.
	 * @param    object|null $logger        Logger instance.
	 * @param    object|null $cache         Cache manager.
	 * @param    object|null $container     Service container.
	 */
	public function __construct(
		?object $repository = null,
		?object $logger = null,
		?object $cache = null,
		?object $container = null
	) {
		$this->repository = $repository;
		$this->logger     = $logger;
		$this->cache      = $cache;
		$this->container  = $container;

		// Listen to campaign activation hook to trigger compilation
		add_action( 'scd_campaign_activated', array( $this, 'on_campaign_activated' ), 5, 1 );
	}

	/**
	 * Get campaign repository instance.
	 *
	 * @since    1.0.0
	 * @return   object|null    Repository instance.
	 */
	public function get_repository(): ?object {
		return $this->repository;
	}

	/**
	 * Log message.
	 *
	 * @since    1.0.0
	 * @param    string $level      Log level.
	 * @param    string $message    Message.
	 * @param    array  $context    Context data.
	 * @return   void
	 */
	private function log( string $level, string $message, array $context = array() ): void {
		if ( $this->logger && method_exists( $this->logger, $level ) ) {
			$this->logger->$level( $message, $context );
		}
	}

	/**
	 * Create campaign.
	 *
	 * @since    1.0.0
	 * @param    array $data    Campaign data.
	 * @return   SCD_Campaign|WP_Error    Created campaign or error.
	 */
	public function create( array $data ): SCD_Campaign|WP_Error {
		try {
			$validation_result = $this->validate_data( $data );
			if ( is_wp_error( $validation_result ) ) {
				return $validation_result;
			}

			$data = $this->prepare_data_for_creation( $data );

			$campaign = new SCD_Campaign( $data );

			$errors = $campaign->validate();
			if ( ! empty( $errors ) ) {
				return new WP_Error( 'validation_failed', 'Campaign validation failed.', $errors );
			}

			if ( ! $this->repository->save( $campaign ) ) {
				$this->log_save_failure( $data );
				return new WP_Error( 'save_failed', 'Failed to save campaign.' );
			}

			$this->log_campaign_created( $campaign );
			do_action( 'scd_campaign_created', $campaign );

			// If campaign created as active and needs compilation, trigger activation hook
			if ( 'active' === $campaign->get_status() ) {
				$selection_type = $campaign->get_product_selection_type();
				$metadata       = $campaign->get_metadata();
				$has_conditions = ! empty( $metadata['product_conditions'] );

				// Trigger activation hook if campaign has dynamic product selection or conditions
				if ( in_array( $selection_type, array( 'random_products', 'smart_selection' ), true ) || $has_conditions ) {
					do_action( 'scd_campaign_activated', $campaign );
				}
			}

			// Schedule one-time activation/deactivation events for the campaign
			// Wrapped in try-catch to ensure campaign creation succeeds even if scheduling fails
			// The 15-minute safety fallback will handle any missed schedules
			// CRITICAL: Must include 'active' status for immediate-start campaigns
			if ( in_array( $campaign->get_status(), array( 'scheduled', 'draft', 'active' ), true ) ) {
				try {
					$scheduler = $this->get_scheduler_service();
					if ( $scheduler ) {
						$this->log(
							'debug',
							'About to schedule campaign events',
							array(
								'campaign_id' => $campaign->get_id(),
							)
						);

						$success = $scheduler->schedule_campaign_events( $campaign->get_id() );
						if ( ! $success ) {
							$this->log(
								'warning',
								'Campaign created but event scheduling failed',
								array(
									'campaign_id' => $campaign->get_id(),
								)
							);
						} else {
							$this->log(
								'info',
								'Campaign events scheduled successfully',
								array(
									'campaign_id' => $campaign->get_id(),
								)
							);
						}

						// EDGE CASE FIX: Immediately check if scheduled time has already passed
						// This handles the case where user schedules for 18:45, stays on review for 20 minutes,
						// then creates campaign at 19:00. Without this, campaign would be "dead" until next cron run.
						// IMPORTANT: This runs REGARDLESS of whether scheduling succeeded or failed
						$start_dt = $campaign->get_starts_at();
						$this->log(
							'debug',
							'Edge case check starting',
							array(
								'campaign_id'  => $campaign->get_id(),
								'has_start_dt' => ! empty( $start_dt ),
							)
						);

						if ( $start_dt ) {
							try {
								$campaign_tz = new DateTimeZone( $campaign->get_timezone() ?: wp_timezone_string() );
								$now_dt      = new DateTimeImmutable( 'now', $campaign_tz );

								$this->log(
									'debug',
									'Comparing times for edge case',
									array(
										'campaign_id'     => $campaign->get_id(),
										'start_timestamp' => $start_dt->getTimestamp(),
										'now_timestamp'   => $now_dt->getTimestamp(),
										'start_formatted' => $start_dt->format( 'Y-m-d H:i:s T' ),
										'now_formatted'   => $now_dt->format( 'Y-m-d H:i:s T' ),
										'diff_seconds'    => $start_dt->getTimestamp() - $now_dt->getTimestamp(),
									)
								);

								// If scheduled time has already passed, activate immediately
								if ( $start_dt <= $now_dt ) {
									$this->log(
										'info',
										'Scheduled time has passed - activating campaign immediately',
										array(
											'campaign_id'  => $campaign->get_id(),
											'scheduled_time' => $start_dt->format( 'Y-m-d H:i:s' ),
											'current_time' => $now_dt->format( 'Y-m-d H:i:s' ),
										)
									);

									// Reload campaign to get fresh status before activation
									// RACE CONDITION FIX: Check status again to prevent duplicate activation
									$reloaded_campaign = $this->repository->find( $campaign->get_id() );
									if ( $reloaded_campaign && 'scheduled' === $reloaded_campaign->get_status() ) {
										$activation_result = $this->activate( $campaign->get_id() );

										// Handle both success and "already active" gracefully
										if ( ! is_wp_error( $activation_result ) ) {
											$this->log(
												'info',
												'Campaign activated immediately after creation',
												array(
													'campaign_id' => $campaign->get_id(),
												)
											);

											// Reload again to get updated status for return
											$campaign = $this->repository->find( $campaign->get_id() );
										} elseif ( 'cannot_activate' === $activation_result->get_error_code() ) {
											// Campaign already activated by another process - this is OK
											$this->log(
												'debug',
												'Campaign already activated by another process',
												array(
													'campaign_id' => $campaign->get_id(),
												)
											);

											// Reload to get current status
											$campaign = $this->repository->find( $campaign->get_id() );
										} else {
											// Actual error occurred
											$this->log(
												'warning',
												'Failed to activate campaign immediately',
												array(
													'campaign_id' => $campaign->get_id(),
													'error' => $activation_result->get_error_message(),
												)
											);
										}
									} elseif ( $reloaded_campaign && 'active' === $reloaded_campaign->get_status() ) {
										// Campaign already active - another process beat us to it
										$this->log(
											'debug',
											'Campaign already active - skipping activation',
											array(
												'campaign_id' => $campaign->get_id(),
											)
										);
										$campaign = $reloaded_campaign;
									}
								}
							} catch ( Exception $e ) {
								$this->log(
									'warning',
									'Failed to check if scheduled time passed',
									array(
										'campaign_id' => $campaign->get_id(),
										'error'       => $e->getMessage(),
									)
								);
							}
						}
					}
				} catch ( Exception $e ) {
					// Event scheduling failed, but campaign was created successfully
					// The 15-minute safety fallback will catch this campaign
					$this->log(
						'error',
						'Event scheduling threw exception - campaign created but scheduling failed',
						array(
							'campaign_id' => $campaign->get_id(),
							'error'       => $e->getMessage(),
							'file'        => $e->getFile(),
							'line'        => $e->getLine(),
						)
					);
				}
			}

			return $campaign;

		} catch ( Exception $e ) {
			$this->handle_creation_error( $e, $data );
			return new WP_Error( 'creation_failed', $e->getMessage() );
		}
	}

	/**
	 * Validate campaign data.
	 *
	 * Uses explicit validation context to determine which validation rules to apply.
	 * This is more secure and testable than the old validation bypass flag approach.
	 *
	 * @since    1.0.0
	 * @param    array $data    Data to validate.
	 * @return   true|WP_Error     True or error.
	 */
	private function validate_data( array &$data ): bool|WP_Error {
		// Determine validation context based on data structure
		$validation_context = $this->determine_validation_context( $data );

		// Remove validation context marker from data
		unset( $data['_validation_context'] );

		// Apply context-specific validation
		if ( class_exists( 'SCD_Validation' ) ) {
			$result = SCD_Validation::validate( $data, $validation_context );
			if ( is_wp_error( $result ) ) {
				$this->log_validation_error( $result, $data );
				return $result;
			}
		}

		return $this->validate_campaign_data( $data );
	}

	/**
	 * Determine validation context from data structure.
	 *
	 * @since    1.0.0
	 * @param    array $data    Campaign data.
	 * @return   string            Validation context.
	 */
	private function determine_validation_context( array $data ): string {
		// Check for explicit context marker (set by trusted services)
		if ( isset( $data['_validation_context'] ) ) {
			$context          = sanitize_key( $data['_validation_context'] );
			$allowed_contexts = array( 'campaign_compiled', 'campaign_update', 'campaign_complete' );
			if ( in_array( $context, $allowed_contexts, true ) ) {
				return $context;
			}
		}

		// Auto-detect context from data structure
		// Compiled data has flat structure (name, discount_type, product_selection_type, etc.)
		// Step-based data has nested structure (basic => [name], discounts => [discount_type], etc.)
		$has_step_structure = isset( $data['basic'] ) || isset( $data['products'] ) || isset( $data['discounts'] ) || isset( $data['schedule'] );

		if ( $has_step_structure ) {
			return 'campaign_complete'; // Step-based validation
		}

		// Default to compiled validation for flat structure
		return 'campaign_compiled';
	}

	/**
	 * Prepare data for creation.
	 *
	 * @since    1.0.0
	 * @param    array $data    Raw data.
	 * @return   array              Prepared data.
	 */
	private function prepare_data_for_creation( array $data ): array {
		$data = $this->set_default_values( $data );

		if ( ! empty( $data['slug'] ) ) {
			$data['slug'] = $this->repository->get_unique_slug( $data['slug'] );
		}

		return $data;
	}

	/**
	 * Log validation error.
	 *
	 * @since    1.0.0
	 * @param    WP_Error $error    Validation error.
	 * @param    array    $data     Campaign data.
	 * @return   void
	 */
	private function log_validation_error( WP_Error $error, array $data ): void {
		$this->log(
			'error',
			'Campaign validation failed',
			array(
				'errors' => $error->get_error_messages(),
				'data'   => $data,
			)
		);
	}

	/**
	 * Log save failure.
	 *
	 * @since    1.0.0
	 * @param    array $data    Campaign data.
	 * @return   void
	 */
	private function log_save_failure( array $data ): void {
		$this->log(
			'error',
			'Campaign save failed',
			array(
				'campaign_data' => $data,
			)
		);
	}

	/**
	 * Log campaign created.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign    Created campaign.
	 * @return   void
	 */
	private function log_campaign_created( SCD_Campaign $campaign ): void {
		$this->log(
			'info',
			'Campaign created',
			array(
				'campaign_id'   => $campaign->get_id(),
				'campaign_name' => $campaign->get_name(),
				'status'        => $campaign->get_status(),
				'created_by'    => $campaign->get_created_by(),
			)
		);
	}

	/**
	 * Handle creation error.
	 *
	 * @since    1.0.0
	 * @param    Exception $exception    Exception object.
	 * @param    array     $data         Campaign data.
	 * @return   void
	 */
	private function handle_creation_error( Exception $exception, array $data ): void {
		$this->log(
			'error',
			'Campaign creation failed',
			array(
				'error' => $exception->getMessage(),
				'data'  => $data,
			)
		);
	}

	/**
	 * Update campaign.
	 *
	 * @since    1.0.0
	 * @param    int   $id      Campaign ID.
	 * @param    array $data    Updated data.
	 * @return   SCD_Campaign|WP_Error    Updated campaign or error.
	 */
	public function update( int $id, array $data ): SCD_Campaign|WP_Error {
		try {
			$campaign = $this->get_campaign_for_update( $id );
			if ( is_wp_error( $campaign ) ) {
				return $campaign;
			}

			$original_status = $campaign->get_status();

			$validation_result = $this->validate_update_data( $data, $id );
			if ( is_wp_error( $validation_result ) ) {
				return $validation_result;
			}

			$data = $this->prepare_data_for_update( $data, $campaign );
			$campaign->fill( $data );

			$errors = $campaign->validate();
			if ( ! empty( $errors ) ) {
				return new WP_Error( 'validation_failed', 'Campaign validation failed.', $errors );
			}

			if ( ! $this->validate_status_transition( $campaign, $original_status, $data ) ) {
				return $this->create_transition_error( $original_status, $data['status'] );
			}

			if ( ! $this->repository->save( $campaign ) ) {
				return new WP_Error( 'save_failed', 'Failed to update campaign.' );
			}

			$this->log_campaign_updated( $campaign, $original_status );
			$this->trigger_update_hooks( $campaign, $original_status );

			// Reschedule events for draft, scheduled, active, or paused campaigns
			// Active campaigns MUST have deactivation events to expire properly
			$new_status = $campaign->get_status();
			if ( in_array( $new_status, array( 'draft', 'scheduled', 'active', 'paused' ), true ) ) {
				$scheduler = $this->get_scheduler_service();
				if ( $scheduler ) {
					$scheduler->schedule_campaign_events( $campaign->get_id() );
				}
			} elseif ( in_array( $new_status, array( 'expired', 'archived' ), true ) ) {
				// Clear all events for expired/archived campaigns
				$scheduler = $this->get_scheduler_service();
				if ( $scheduler ) {
					$scheduler->clear_campaign_events( $campaign->get_id() );
				}
			}

			return $campaign;

		} catch ( Exception $e ) {
			$this->handle_update_error( $e, $id, $data );
			return new WP_Error( 'update_failed', $e->getMessage() );
		}
	}

	/**
	 * Get campaign for update.
	 *
	 * @since    1.0.0
	 * @param    int $id    Campaign ID.
	 * @return   SCD_Campaign|WP_Error    Campaign or error.
	 */
	private function get_campaign_for_update( int $id ): SCD_Campaign|WP_Error {
		$campaign = $this->repository->find( $id );
		if ( ! $campaign ) {
			return new WP_Error( 'not_found', 'Campaign not found.' );
		}
		return $campaign;
	}

	/**
	 * Validate update data.
	 *
	 * @since    1.0.0
	 * @param    array $data    Update data.
	 * @param    int   $id      Campaign ID.
	 * @return   true|WP_Error     True or error.
	 */
	private function validate_update_data( array $data, int $id ): bool|WP_Error {
		if ( class_exists( 'SCD_Validation' ) ) {
			$result = SCD_Validation::validate( $data, 'campaign_complete' );
			if ( is_wp_error( $result ) ) {
				$this->log_update_validation_error( $result, $id, $data );
				return $result;
			}
		}

		return $this->validate_campaign_data( $data, $id );
	}

	/**
	 * Prepare data for update.
	 *
	 * @since    1.0.0
	 * @param    array        $data       Raw data.
	 * @param    SCD_Campaign $campaign   Campaign object.
	 * @return   array                       Prepared data.
	 */
	private function prepare_data_for_update( array $data, SCD_Campaign $campaign ): array {
		if ( ! empty( $data['slug'] ) && $data['slug'] !== $campaign->get_slug() ) {
			$data['slug'] = $this->repository->get_unique_slug( $data['slug'], $campaign->get_id() );
		}

		// Handle timezone conversion for schedule updates
		// If update includes separate date/time/timezone fields, combine and convert to UTC
		if ( isset( $data['start_date'] ) && isset( $data['start_time'] ) && isset( $data['timezone'] ) ) {
			$start_datetime    = $data['start_date'] . ' ' . $data['start_time'];
			$data['starts_at'] = $this->convert_datetime_to_utc( $start_datetime, $data['timezone'] );
		}

		if ( isset( $data['end_date'] ) && isset( $data['end_time'] ) && isset( $data['timezone'] ) ) {
			$end_datetime    = $data['end_date'] . ' ' . $data['end_time'];
			$data['ends_at'] = $this->convert_datetime_to_utc( $end_datetime, $data['timezone'] );
		}

		$data['updated_by'] = get_current_user_id();
		return $data;
	}

	/**
	 * Convert datetime from campaign timezone to UTC.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $datetime_str    Datetime string in campaign timezone.
	 * @param    string $timezone        Campaign timezone.
	 * @return   string                     Datetime string in UTC format (Y-m-d H:i:s).
	 */
	private function convert_datetime_to_utc( string $datetime_str, string $timezone ): string {
		try {
			// Parse datetime in campaign timezone
			$dt = new DateTime( $datetime_str, new DateTimeZone( $timezone ) );

			// Convert to UTC
			$dt->setTimezone( new DateTimeZone( 'UTC' ) );

			// Return as string in MySQL format
			return $dt->format( 'Y-m-d H:i:s' );
		} catch ( Exception $e ) {
			// If conversion fails, log and return original string
			// (will be parsed as UTC by Campaign entity)
			if ( $this->logger ) {
				$this->logger->warning(
					'Timezone conversion failed',
					array(
						'datetime' => $datetime_str,
						'timezone' => $timezone,
						'error'    => $e->getMessage(),
					)
				);
			}
			return $datetime_str;
		}
	}

	/**
	 * Validate status transition.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign          Campaign object.
	 * @param    string       $original_status   Original status.
	 * @param    array        $data              Update data.
	 * @return   bool                               Is valid transition.
	 */
	private function validate_status_transition( SCD_Campaign $campaign, string $original_status, array $data ): bool {
		if ( ! isset( $data['status'] ) || $data['status'] === $original_status ) {
			return true;
		}

		return $campaign->can_transition_to( $data['status'] );
	}

	/**
	 * Create transition error.
	 *
	 * @since    1.0.0
	 * @param    string $from    From status.
	 * @param    string $to      To status.
	 * @return   WP_Error           Error object.
	 */
	private function create_transition_error( string $from, string $to ): WP_Error {
		return new WP_Error(
			'invalid_transition',
			sprintf( 'Cannot transition from %s to %s.', $from, $to )
		);
	}

	/**
	 * Log update validation error.
	 *
	 * @since    1.0.0
	 * @param    WP_Error $error    Validation error.
	 * @param    int      $id       Campaign ID.
	 * @param    array    $data     Update data.
	 * @return   void
	 */
	private function log_update_validation_error( WP_Error $error, int $id, array $data ): void {
		$this->log(
			'error',
			'Campaign update validation failed',
			array(
				'campaign_id' => $id,
				'errors'      => $error->get_error_messages(),
				'data'        => $data,
			)
		);
	}

	/**
	 * Log campaign updated.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign          Updated campaign.
	 * @param    string       $original_status   Original status.
	 * @return   void
	 */
	private function log_campaign_updated( SCD_Campaign $campaign, string $original_status ): void {
		$this->log(
			'info',
			'Campaign updated',
			array(
				'campaign_id'    => $campaign->get_id(),
				'campaign_name'  => $campaign->get_name(),
				'updated_by'     => $campaign->get_updated_by(),
				'status_changed' => $original_status !== $campaign->get_status(),
			)
		);
	}

	/**
	 * Trigger update hooks.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign          Updated campaign.
	 * @param    string       $original_status   Original status.
	 * @return   void
	 */
	private function trigger_update_hooks( SCD_Campaign $campaign, string $original_status ): void {
		do_action( 'scd_campaign_updated', $campaign, $original_status );

		if ( $original_status !== $campaign->get_status() ) {
			do_action( 'scd_campaign_status_changed', $campaign, $original_status, $campaign->get_status() );
		}
	}

	/**
	 * Handle update error.
	 *
	 * @since    1.0.0
	 * @param    Exception $exception    Exception object.
	 * @param    int       $id           Campaign ID.
	 * @param    array     $data         Update data.
	 * @return   void
	 */
	private function handle_update_error( Exception $exception, int $id, array $data ): void {
		$this->log(
			'error',
			'Campaign update failed',
			array(
				'campaign_id' => $id,
				'error'       => $exception->getMessage(),
				'data'        => $data,
			)
		);
	}

	/**
	 * Delete campaign.
	 *
	 * @since    1.0.0
	 * @param    int $id    Campaign ID.
	 * @return   bool|WP_Error    True or error.
	 */
	public function delete( int $id ): bool|WP_Error {
		try {
			$campaign = $this->get_campaign_for_deletion( $id );
			if ( is_wp_error( $campaign ) ) {
				return $campaign;
			}

			if ( ! $this->can_delete_campaign( $campaign ) ) {
				return new WP_Error( 'cannot_delete', 'Cannot delete active campaign.' );
			}

			// If campaign is active, deactivate it first
			if ( 'active' === $campaign->get_status() ) {
				$campaign->set_status( 'paused' );
				$this->repository->save( $campaign );
				$this->log(
					'info',
					'Campaign deactivated before deletion',
					array(
						'campaign_id' => $id,
					)
				);
			}

			if ( ! $this->repository->delete( $id ) ) {
				return new WP_Error( 'delete_failed', 'Failed to delete campaign.' );
			}

			// Clear any scheduled events for this campaign
			$scheduler = $this->get_scheduler_service();
			if ( $scheduler ) {
				$scheduler->clear_campaign_events( $id );
			}

			$this->log_campaign_deleted( $campaign );
			do_action( 'scd_campaign_deleted', $campaign );

			return true;

		} catch ( Exception $e ) {
			$this->handle_deletion_error( $e, $id );
			return new WP_Error( 'deletion_failed', $e->getMessage() );
		}
	}

	/**
	 * Get campaign for deletion.
	 *
	 * @since    1.0.0
	 * @param    int $id    Campaign ID.
	 * @return   SCD_Campaign|WP_Error    Campaign or error.
	 */
	private function get_campaign_for_deletion( int $id ): SCD_Campaign|WP_Error {
		$campaign = $this->repository->find( $id );
		if ( ! $campaign ) {
			return new WP_Error( 'not_found', 'Campaign not found.' );
		}
		return $campaign;
	}

	/**
	 * Check if campaign can be deleted.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign    Campaign object.
	 * @return   bool                         Can delete.
	 */
	private function can_delete_campaign( SCD_Campaign $campaign ): bool {
		// Allow deleting campaigns in any status
		// If active, they will be deactivated automatically during deletion
		return true;
	}

	/**
	 * Log campaign deleted.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign    Deleted campaign.
	 * @return   void
	 */
	private function log_campaign_deleted( SCD_Campaign $campaign ): void {
		$this->log(
			'info',
			'Campaign deleted',
			array(
				'campaign_id'   => $campaign->get_id(),
				'campaign_name' => $campaign->get_name(),
				'deleted_by'    => get_current_user_id(),
			)
		);
	}

	/**
	 * Handle deletion error.
	 *
	 * @since    1.0.0
	 * @param    Exception $exception    Exception object.
	 * @param    int       $id           Campaign ID.
	 * @return   void
	 */
	private function handle_deletion_error( Exception $exception, int $id ): void {
		$this->log(
			'error',
			'Campaign deletion failed',
			array(
				'campaign_id' => $id,
				'error'       => $exception->getMessage(),
			)
		);
	}

	/**
	 * Activate campaign.
	 *
	 * @since    1.0.0
	 * @param    int $id    Campaign ID.
	 * @return   bool|WP_Error    True or error.
	 */
	public function activate( int $id ): bool|WP_Error {
		try {
			$campaign = $this->get_campaign_for_status_change( $id );
			if ( is_wp_error( $campaign ) ) {
				return $campaign;
			}

			$validation = $this->validate_activation( $campaign );
			if ( is_wp_error( $validation ) ) {
				return $validation;
			}

			$original_status = $campaign->get_status();
			$update_result   = $this->update_campaign_status( $campaign, 'active' );
			if ( is_wp_error( $update_result ) ) {
				return $update_result;
			}

			// Schedule deactivation event for active campaign
			$scheduler = $this->get_scheduler_service();
			if ( $scheduler ) {
				$scheduler->schedule_campaign_events( $campaign->get_id() );
			}

			$this->log_campaign_activated( $campaign );
			$this->trigger_activation_hooks( $campaign, $original_status );

			return true;

		} catch ( Exception $e ) {
			$this->handle_activation_error( $e, $id );
			return new WP_Error( 'activation_failed', $e->getMessage() );
		}
	}

	/**
	 * Get campaign for status change.
	 *
	 * @since    1.0.0
	 * @param    int $id    Campaign ID.
	 * @return   SCD_Campaign|WP_Error    Campaign or error.
	 */
	private function get_campaign_for_status_change( int $id ): SCD_Campaign|WP_Error {
		$campaign = $this->repository->find( $id );
		if ( ! $campaign ) {
			return new WP_Error( 'not_found', 'Campaign not found.' );
		}
		return $campaign;
	}

	/**
	 * Validate activation.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign    Campaign to validate.
	 * @return   true|WP_Error               True or error.
	 */
	private function validate_activation( SCD_Campaign $campaign ): bool|WP_Error {
		if ( ! $campaign->can_transition_to( 'active' ) ) {
			return new WP_Error(
				'cannot_activate',
				sprintf( 'Cannot activate campaign with status: %s', $campaign->get_status() )
			);
		}

		return $this->validate_for_activation( $campaign );
	}

	/**
	 * Update campaign status.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign    Campaign object.
	 * @param    string       $status      New status.
	 * @return   true|WP_Error               True or error.
	 */
	private function update_campaign_status( SCD_Campaign $campaign, string $status ): bool|WP_Error {
		$campaign->set_status( $status );
		$campaign->set_updated_by( get_current_user_id() );

		if ( ! $this->repository->save( $campaign ) ) {
			return new WP_Error( 'save_failed', sprintf( 'Failed to %s campaign.', $status ) );
		}

		return true;
	}

	/**
	 * Log campaign activated.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign    Activated campaign.
	 * @return   void
	 */
	private function log_campaign_activated( SCD_Campaign $campaign ): void {
		$this->log(
			'info',
			'Campaign activated',
			array(
				'campaign_id'   => $campaign->get_id(),
				'campaign_name' => $campaign->get_name(),
				'activated_by'  => get_current_user_id(),
			)
		);
	}

	/**
	 * Trigger activation hooks.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign    $campaign          Activated campaign.
	 * @param    string          $original_status   Original status.
	 * @return   void
	 */
	/**
	 * Handle campaign activation event.
	 *
	 * Called when scd_campaign_activated hook fires.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign    Activated campaign.
	 * @return   void
	 */
	public function on_campaign_activated( SCD_Campaign $campaign ): void {
		// Compile product selection for random_products and smart_selection
		$this->compile_product_selection( $campaign );

		// Clear campaign-related caches
		if ( $this->cache ) {
			$this->cache->delete( 'active_campaigns' );
			$this->cache->delete( 'campaigns_list' );
			// Also clear the specific campaign cache
			$this->cache->delete( 'active_campaigns_product_' . $campaign->get_id() );
		}
	}

	/**
	 * Trigger activation hooks.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign          Activated campaign.
	 * @param    string       $original_status   Original status.
	 * @return   void
	 */
	private function trigger_activation_hooks( SCD_Campaign $campaign, string $original_status ): void {
		do_action( 'scd_campaign_activated', $campaign );
		do_action( 'scd_campaign_status_changed', $campaign, $original_status, 'active' );
	}

	/**
	 * Handle activation error.
	 *
	 * @since    1.0.0
	 * @param    Exception $exception    Exception object.
	 * @param    int       $id           Campaign ID.
	 * @return   void
	 */
	private function handle_activation_error( Exception $exception, int $id ): void {
		$this->log(
			'error',
			'Campaign activation failed',
			array(
				'campaign_id' => $id,
				'error'       => $exception->getMessage(),
			)
		);
	}

	/**
	 * Pause campaign.
	 *
	 * @since    1.0.0
	 * @param    int $id    Campaign ID.
	 * @return   bool|WP_Error    True or error.
	 */
	public function pause( int $id ): bool|WP_Error {
		return $this->change_campaign_status( $id, 'paused' );
	}

	/**
	 * Archive campaign.
	 *
	 * @since    1.0.0
	 * @param    int $id    Campaign ID.
	 * @return   bool|WP_Error    True or error.
	 */
	public function archive( int $id ): bool|WP_Error {
		return $this->change_campaign_status( $id, 'archived' );
	}

	/**
	 * Expire campaign.
	 *
	 * Marks a campaign as expired. This is typically called automatically when
	 * a campaign reaches its end date, but can also be called manually.
	 *
	 * @since    1.0.0
	 * @param    int $id    Campaign ID.
	 * @return   bool|WP_Error    True or error.
	 */
	public function expire( int $id ): bool|WP_Error {
		return $this->change_campaign_status( $id, 'expired' );
	}

	/**
	 * Change campaign status.
	 *
	 * @since    1.0.0
	 * @param    int    $id        Campaign ID.
	 * @param    string $status    New status.
	 * @return   bool|WP_Error         True or error.
	 */
	private function change_campaign_status( int $id, string $status ): bool|WP_Error {
		try {
			$campaign = $this->get_campaign_for_status_change( $id );
			if ( is_wp_error( $campaign ) ) {
				return $campaign;
			}

			if ( ! $campaign->can_transition_to( $status ) ) {
				return $this->create_status_transition_error( $campaign->get_status(), $status );
			}

			$original_status = $campaign->get_status();
			$update_result   = $this->update_campaign_status( $campaign, $status );
			if ( is_wp_error( $update_result ) ) {
				return $update_result;
			}

			$this->log_status_change( $campaign, $original_status, $status );
			$this->trigger_status_change_hooks( $campaign, $original_status, $status );

			return true;

		} catch ( Exception $e ) {
			$this->handle_status_change_error( $e, $id, $status );
			return new WP_Error( $status . '_failed', $e->getMessage() );
		}
	}

	/**
	 * Create status transition error.
	 *
	 * @since    1.0.0
	 * @param    string $from    From status.
	 * @param    string $to      To status.
	 * @return   WP_Error           Error object.
	 */
	private function create_status_transition_error( string $from, string $to ): WP_Error {
		return new WP_Error(
			'cannot_' . $to,
			sprintf( 'Cannot %s campaign with status: %s', $to, $from )
		);
	}

	/**
	 * Log status change.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign    Campaign object.
	 * @param    string       $from        Original status.
	 * @param    string       $to          New status.
	 * @return   void
	 */
	private function log_status_change( SCD_Campaign $campaign, string $from, string $to ): void {
		$this->log(
			'info',
			sprintf( 'Campaign %s', $to ),
			array(
				'campaign_id'   => $campaign->get_id(),
				'campaign_name' => $campaign->get_name(),
				$to . '_by'     => get_current_user_id(),
				'from_status'   => $from,
				'to_status'     => $to,
			)
		);
	}

	/**
	 * Trigger status change hooks.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign    Campaign object.
	 * @param    string       $from        Original status.
	 * @param    string       $to          New status.
	 * @return   void
	 */
	private function trigger_status_change_hooks( SCD_Campaign $campaign, string $from, string $to ): void {
		do_action( 'scd_campaign_' . $to, $campaign );
		do_action( 'scd_campaign_status_changed', $campaign, $from, $to );
	}

	/**
	 * Handle status change error.
	 *
	 * @since    1.0.0
	 * @param    Exception $exception    Exception object.
	 * @param    int       $id           Campaign ID.
	 * @param    string    $status       Target status.
	 * @return   void
	 */
	private function handle_status_change_error( Exception $exception, int $id, string $status ): void {
		$this->log(
			'error',
			sprintf( 'Campaign %s failed', $status ),
			array(
				'campaign_id' => $id,
				'error'       => $exception->getMessage(),
			)
		);
	}

	/**
	 * Duplicate campaign.
	 *
	 * @since    1.0.0
	 * @param    int   $id      Campaign ID.
	 * @param    array $data    Override data.
	 * @return   SCD_Campaign|WP_Error    Duplicated campaign or error.
	 */
	public function duplicate( int $id, array $data = array() ): SCD_Campaign|WP_Error {
		try {
			$original = $this->get_campaign_for_duplication( $id );
			if ( is_wp_error( $original ) ) {
				return $original;
			}

			$duplicate_data = $this->prepare_duplication_data( $original, $data );

			return $this->create( $duplicate_data );

		} catch ( Exception $e ) {
			$this->handle_duplication_error( $e, $id );
			return new WP_Error( 'duplication_failed', $e->getMessage() );
		}
	}

	/**
	 * Get campaign for duplication.
	 *
	 * @since    1.0.0
	 * @param    int $id    Campaign ID.
	 * @return   SCD_Campaign|WP_Error    Campaign or error.
	 */
	private function get_campaign_for_duplication( int $id ): SCD_Campaign|WP_Error {
		$campaign = $this->repository->find( $id );
		if ( ! $campaign ) {
			return new WP_Error( 'not_found', 'Campaign not found.' );
		}
		return $campaign;
	}

	/**
	 * Prepare duplication data.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $original    Original campaign.
	 * @param    array        $override    Override data.
	 * @return   array                        Duplication data.
	 */
	private function prepare_duplication_data( SCD_Campaign $original, array $override ): array {
		$duplicate_data = $original->to_array();

		$this->remove_unique_fields( $duplicate_data );
		$this->reset_performance_metrics( $duplicate_data );
		$this->set_duplication_defaults( $duplicate_data, $original, $override );

		return array_merge( $duplicate_data, $override );
	}

	/**
	 * Remove unique fields from data.
	 *
	 * @since    1.0.0
	 * @param    array &$data    Campaign data.
	 * @return   void
	 */
	private function remove_unique_fields( array &$data ): void {
		unset(
			$data['id'],
			$data['uuid'],
			$data['version'],
			$data['created_at'],
			$data['updated_at'],
			$data['deleted_at']
		);
	}

	/**
	 * Reset performance metrics.
	 *
	 * @since    1.0.0
	 * @param    array &$data    Campaign data.
	 * @return   void
	 */
	private function reset_performance_metrics( array &$data ): void {
		$metrics = array(
			'products_count'    => 0,
			'revenue_generated' => 0.0,
			'orders_count'      => 0,
			'impressions_count' => 0,
			'clicks_count'      => 0,
			'conversion_rate'   => 0.0,
		);

		foreach ( $metrics as $key => $value ) {
			$data[ $key ] = $value;
		}
	}

	/**
	 * Set duplication defaults.
	 *
	 * @since    1.0.0
	 * @param    array        &$data       Campaign data.
	 * @param    SCD_Campaign $original    Original campaign.
	 * @param    array        $override    Override data.
	 * @return   void
	 */
	private function set_duplication_defaults( array &$data, SCD_Campaign $original, array $override ): void {
		$data['status']     = 'draft';
		$data['name']       = $override['name'] ?? $original->get_name() . ' (Copy)';
		$data['slug']       = $this->repository->get_unique_slug(
			$override['slug'] ?? sanitize_title( $data['name'] )
		);
		$data['created_by'] = get_current_user_id();
		$data['updated_by'] = null;

		// Clear schedule dates when duplicating
		// This forces user to set new dates and prevents accidental activation with expired dates
		$data['starts_at'] = null;
		$data['ends_at']   = null;
	}

	/**
	 * Handle duplication error.
	 *
	 * @since    1.0.0
	 * @param    Exception $exception    Exception object.
	 * @param    int       $id           Original ID.
	 * @return   void
	 */
	private function handle_duplication_error( Exception $exception, int $id ): void {
		$this->log(
			'error',
			'Campaign duplication failed',
			array(
				'original_id' => $id,
				'error'       => $exception->getMessage(),
			)
		);
	}

	/**
	 * Get active campaigns.
	 *
	 * @since    1.0.0
	 * @param    array $options    Query options.
	 * @return   array               Active campaigns.
	 */
	public function get_active_campaigns( array $options = array() ): array {
		return $this->repository->get_active( $options );
	}

	/**
	 * Get scheduled campaigns.
	 *
	 * @since    1.0.0
	 * @return   array    Scheduled campaigns.
	 */
	public function get_scheduled_campaigns(): array {
		return $this->repository->get_scheduled();
	}

	/**
	 * Get expired campaigns.
	 *
	 * @since    1.0.0
	 * @return   array    Expired campaigns.
	 */
	public function get_expired_campaigns(): array {
		return $this->repository->get_expired();
	}

	/**
	 * Process scheduled campaigns.
	 *
	 * Uses distributed locking via WordPress transients to prevent race conditions
	 * when multiple processes (cron jobs, admin requests) run simultaneously.
	 *
	 * @since    1.0.0
	 * @return   array    Processing results.
	 */
	public function process_scheduled_campaigns(): array {
		$results = $this->initialize_processing_results();

		// CRITICAL: Distributed lock to prevent race conditions
		// If two cron jobs run simultaneously, only one should process campaigns
		$lock_key     = 'scd_process_campaigns_lock';
		$lock_value   = time();
		$lock_timeout = 60; // 60 seconds - enough for processing but not too long if process crashes

		// Try to acquire lock
		if ( false !== get_transient( $lock_key ) ) {
			// Another process is already running
			$results['skipped'] = 'locked';
			$this->log(
				'debug',
				'Campaign processing skipped - another process is running',
				array(
					'lock_key' => $lock_key,
				)
			);
			return $results;
		}

		// Acquire lock
		set_transient( $lock_key, $lock_value, $lock_timeout );

		try {
			// Use UTC timezone to match campaign dates (which are stored in UTC)
			$now     = new DateTime( 'now', new DateTimeZone( 'UTC' ) );
			$results = $this->activate_scheduled_campaigns( $results, $now );
			$results = $this->expire_active_campaigns( $results, $now );

		} catch ( Exception $e ) {
			$this->handle_processing_error( $e, $results );
		} finally {
			// ALWAYS release lock, even if exception occurs
			delete_transient( $lock_key );
		}

		return $results;
	}

	/**
	 * Initialize processing results.
	 *
	 * @since    1.0.0
	 * @return   array    Initial results structure.
	 */
	private function initialize_processing_results(): array {
		return array(
			'activated' => 0,
			'expired'   => 0,
			'errors'    => array(),
		);
	}

	/**
	 * Activate scheduled campaigns.
	 *
	 * Processes scheduled campaigns by checking if they should be:
	 * 1. Expired (if end date already passed) - prevents wasteful activation
	 * 2. Activated (if start date reached and end date still in future)
	 *
	 * This handles edge cases where cron was delayed and campaigns missed
	 * their activation window. Instead of activating then immediately expiring,
	 * we expire them directly for efficiency.
	 *
	 * @since    1.0.0
	 * @param    array    $results    Results array.
	 * @param    DateTime $now        Current time.
	 * @return   array                   Updated results.
	 */
	private function activate_scheduled_campaigns( array $results, DateTime $now ): array {
		$scheduled = $this->repository->get_scheduled();

		foreach ( $scheduled as $campaign ) {
			// Priority 1: Check if campaign already passed its end date
			// (This handles campaigns that missed their activation window due to cron delays)
			if ( $this->should_expire_campaign( $campaign, $now ) ) {
				$results = $this->process_campaign_expiration( $campaign, $results );
			}
			// Priority 2: Activate if due and not expired
			elseif ( $this->should_activate_campaign( $campaign, $now ) ) {
				$results = $this->process_campaign_activation( $campaign, $results );
			}
		}

		return $results;
	}

	/**
	 * Check if campaign should be activated.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign    Campaign object.
	 * @param    DateTime     $now         Current time.
	 * @return   bool                         Should activate.
	 */
	private function should_activate_campaign( SCD_Campaign $campaign, DateTime $now ): bool {
		$starts_at = $campaign->get_starts_at();
		return $starts_at && $starts_at <= $now;
	}

	/**
	 * Process campaign activation.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign    Campaign object.
	 * @param    array        $results     Results array.
	 * @return   array                        Updated results.
	 */
	private function process_campaign_activation( SCD_Campaign $campaign, array $results ): array {
		$result = $this->activate( $campaign->get_id() );

		if ( is_wp_error( $result ) ) {
			$results['errors'][] = array(
				'campaign_id' => $campaign->get_id(),
				'error'       => $result->get_error_message(),
			);
		} else {
			++$results['activated'];
		}

		return $results;
	}

	/**
	 * Expire active and paused campaigns.
	 *
	 * Checks both active and paused campaigns for expiration. A campaign's end date
	 * is an absolute deadline regardless of whether it was paused by the user.
	 *
	 * @since    1.0.0
	 * @param    array    $results    Results array.
	 * @param    DateTime $now        Current time.
	 * @return   array                   Updated results.
	 */
	private function expire_active_campaigns( array $results, DateTime $now ): array {
		// Get both active and paused campaigns for expiration check
		$active             = $this->repository->get_active();
		$paused             = $this->repository->get_paused();
		$campaigns_to_check = array_merge( $active, $paused );

		foreach ( $campaigns_to_check as $campaign ) {
			if ( $this->should_expire_campaign( $campaign, $now ) ) {
				$results = $this->process_campaign_expiration( $campaign, $results );
			}
		}

		return $results;
	}

	/**
	 * Check if campaign should be expired.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign    Campaign object.
	 * @param    DateTime     $now         Current time.
	 * @return   bool                         Should expire.
	 */
	private function should_expire_campaign( SCD_Campaign $campaign, DateTime $now ): bool {
		$ends_at = $campaign->get_ends_at();
		return $ends_at && $ends_at <= $now;
	}

	/**
	 * Process campaign expiration.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign    Campaign object.
	 * @param    array        $results     Results array.
	 * @return   array                        Updated results.
	 */
	private function process_campaign_expiration( SCD_Campaign $campaign, array $results ): array {
		$campaign->set_status( 'expired' );
		$campaign->set_updated_by( null ); // System action (auto-expiration)

		if ( $this->repository->save( $campaign ) ) {
			++$results['expired'];

			// Store expired campaign info for admin notice
			$expired_campaigns = get_transient( 'scd_recently_expired_campaigns' );
			if ( false === $expired_campaigns || ! is_array( $expired_campaigns ) ) {
				$expired_campaigns = array();
			}

			$expired_campaigns[] = array(
				'id'   => $campaign->get_id(),
				'name' => $campaign->get_name(),
				'time' => time(),
			);

			// Limit to most recent 50 campaigns to prevent transient bloat
			// This handles edge cases where many campaigns expire simultaneously
			if ( count( $expired_campaigns ) > 50 ) {
				$expired_campaigns = array_slice( $expired_campaigns, -50 );
			}

			// Store for 24 hours
			set_transient( 'scd_recently_expired_campaigns', $expired_campaigns, DAY_IN_SECONDS );

			do_action( 'scd_campaign_expired', $campaign );
		} else {
			$results['errors'][] = array(
				'campaign_id' => $campaign->get_id(),
				'error'       => 'Failed to expire campaign',
			);
		}

		return $results;
	}

	/**
	 * Handle processing error.
	 *
	 * @since    1.0.0
	 * @param    Exception $exception    Exception object.
	 * @param    array     &$results     Results array.
	 * @return   void
	 */
	private function handle_processing_error( Exception $exception, array &$results ): void {
		$this->log(
			'error',
			'Scheduled campaign processing failed',
			array(
				'error' => $exception->getMessage(),
			)
		);

		$results['errors'][] = array(
			'error' => $exception->getMessage(),
		);
	}

	/**
	 * Get campaigns.
	 *
	 * @since    1.0.0
	 * @param    array $args    Query arguments.
	 * @return   array             Campaigns.
	 */
	public function get_campaigns( array $args = array() ): array {
		return $this->repository->find_all( $args );
	}

	/**
	 * Count campaigns.
	 *
	 * @since    1.0.0
	 * @param    array $args    Query arguments.
	 * @return   int               Campaign count.
	 */
	public function count_campaigns( array $args = array() ): int {
		return $this->repository->count( $args );
	}


	/**
	 * Get status counts.
	 *
	 * @since    1.0.0
	 * @return   array    Status counts.
	 */
	public function get_status_counts(): array {
		if ( $this->repository ) {
			return $this->repository->get_status_counts();
		}

		return array(
			'active'    => 0,
			'scheduled' => 0,
			'expired'   => 0,
			'draft'     => 0,
			'paused'    => 0,
			'total'     => 0,
		);
	}

	/**
	 * Get campaign statistics.
	 *
	 * @since    1.0.0
	 * @return   array    Campaign statistics.
	 */
	public function get_campaign_statistics(): array {
		try {
			$cache_key = 'scd_campaign_statistics';

			if ( $this->cache ) {
				$stats = $this->cache->get( $cache_key );
				if ( false !== $stats && is_array( $stats ) ) {
					return $stats;
				}
			}

			$stats = $this->calculate_statistics();

			if ( $this->cache ) {
				$this->cache->set( $cache_key, $stats, 300 ); // 5 minutes
			}

			return $stats;

		} catch ( Exception $e ) {
			$this->handle_statistics_error( $e );
			return $this->get_default_statistics();
		}
	}

	/**
	 * Calculate statistics.
	 *
	 * @since    1.0.0
	 * @return   array    Statistics data.
	 */
	private function calculate_statistics(): array {
		$stats = $this->get_default_statistics();

		if ( $this->repository ) {
			$this->add_count_statistics( $stats );
			$this->add_performance_statistics( $stats );
		}

		return $stats;
	}

	/**
	 * Get default statistics.
	 *
	 * @since    1.0.0
	 * @return   array    Default statistics.
	 */
	private function get_default_statistics(): array {
		return array(
			'total'                   => 0,
			'active'                  => 0,
			'scheduled'               => 0,
			'paused'                  => 0,
			'total_campaigns'         => 0,
			'active_campaigns'        => 0,
			'scheduled_campaigns'     => 0,
			'paused_campaigns'        => 0,
			'total_revenue'           => 0.0,
			'total_savings'           => 0.0,
			'total_orders'            => 0,
			'average_conversion_rate' => 0.0,
		);
	}

	/**
	 * Add count statistics.
	 *
	 * @since    1.0.0
	 * @param    array &$stats    Statistics array.
	 * @return   void
	 */
	private function add_count_statistics( array &$stats ): void {
		$stats['total']     = $this->repository->count();
		$stats['active']    = $this->repository->count( array( 'status' => 'active' ) );
		$stats['scheduled'] = $this->repository->count( array( 'status' => 'scheduled' ) );
		$stats['paused']    = $this->repository->count( array( 'status' => 'paused' ) );
	}

	/**
	 * Add performance statistics.
	 *
	 * @since    1.0.0
	 * @param    array &$stats    Statistics array.
	 * @return   void
	 */
	private function add_performance_statistics( array &$stats ): void {
		$performance_data = $this->repository->get_performance_summary();
		if ( $performance_data && is_array( $performance_data ) ) {
			$stats['total_revenue']           = floatval( $performance_data['total_revenue'] ?? 0 );
			$stats['total_savings']           = floatval( $performance_data['total_savings'] ?? 0 );
			$stats['total_orders']            = intval( $performance_data['total_orders'] ?? 0 );
			$stats['average_conversion_rate'] = floatval( $performance_data['average_conversion_rate'] ?? 0 );
		}
	}

	/**
	 * Handle statistics error.
	 *
	 * @since    1.0.0
	 * @param    Exception $exception    Exception object.
	 * @return   void
	 */
	private function handle_statistics_error( Exception $exception ): void {
		$this->log(
			'error',
			'Failed to get campaign statistics',
			array(
				'error' => $exception->getMessage(),
			)
		);
	}

	/**
	 * Find campaign by ID.
	 *
	 * @since    1.0.0
	 * @param    int $id    Campaign ID.
	 * @return   SCD_Campaign|null    Campaign or null.
	 */
	public function find( int $id, bool $include_trashed = false ): ?SCD_Campaign {
		return $this->repository->find( $id, $include_trashed );
	}

	/**
	 * Find campaign by slug.
	 *
	 * @since    1.0.0
	 * @param    string $slug    Campaign slug.
	 * @return   SCD_Campaign|null    Campaign or null.
	 */
	public function find_by_slug( string $slug ): ?SCD_Campaign {
		return $this->repository->find_by_slug( $slug );
	}

	/**
	 * Activate campaign alias.
	 *
	 * @since    1.0.0
	 * @param    int $id    Campaign ID.
	 * @return   bool|WP_Error    True or error.
	 */
	public function activate_campaign( int $id ): bool|WP_Error {
		return $this->activate( $id );
	}

	/**
	 * Deactivate campaign alias.
	 *
	 * @since    1.0.0
	 * @param    int $id    Campaign ID.
	 * @return   bool|WP_Error    True or error.
	 */
	public function deactivate_campaign( int $id ): bool|WP_Error {
		return $this->pause( $id );
	}

	/**
	 * Delete campaign alias.
	 *
	 * @since    1.0.0
	 * @param    int $id    Campaign ID.
	 * @return   bool|WP_Error    True or error.
	 */
	public function delete_campaign( int $id ): bool|WP_Error {
		return $this->delete( $id );
	}

	/**
	 * Check if campaign name exists.
	 *
	 * @since    1.0.0
	 * @param    string   $name          Campaign name.
	 * @param    int|null $exclude_id    Exclude ID.
	 * @return   bool                      Name exists.
	 */
	public function campaign_name_exists( string $name, ?int $exclude_id = null ): bool {
		$cache_key = $this->get_name_cache_key( $name, $exclude_id );

		if ( $this->cache ) {
			$cached = $this->cache->get( $cache_key );
			if ( false !== $cached ) {
				return (bool) $cached;
			}
		}

		$exists = $this->query_name_exists( $name, $exclude_id );

		if ( $this->cache ) {
			$this->cache->set( $cache_key, $exists, 60 ); // 1 minute
		}

		return $exists;
	}

	/**
	 * Get name cache key.
	 *
	 * @since    1.0.0
	 * @param    string   $name          Campaign name.
	 * @param    int|null $exclude_id    Exclude ID.
	 * @return   string                    Cache key.
	 */
	private function get_name_cache_key( string $name, ?int $exclude_id ): string {
		return 'campaign_name_' . md5( $name . '_' . ( $exclude_id ?? 0 ) );
	}

	/**
	 * Query if name exists.
	 *
	 * @since    1.0.0
	 * @param    string   $name          Campaign name.
	 * @param    int|null $exclude_id    Exclude ID.
	 * @return   bool                      Exists.
	 */
	private function query_name_exists( string $name, ?int $exclude_id ): bool {
		global $wpdb;
		$table_name = $wpdb->prefix . 'scd_campaigns';

		$sql    = "SELECT 1 FROM {$table_name} WHERE name = %s AND deleted_at IS NULL";
		$params = array( $name );

		if ( $exclude_id ) {
			$sql     .= ' AND id != %d';
			$params[] = $exclude_id;
		}

		$sql .= ' LIMIT 1';

		$wpdb->suppress_errors();
		$exists = (bool) $wpdb->get_var( $wpdb->prepare( $sql, ...$params ) );
		$wpdb->suppress_errors( false );

		return $exists;
	}

	/**
	 * Validate campaign data.
	 *
	 * @since    1.0.0
	 * @param    array    $data    Campaign data.
	 * @param    int|null $id      Campaign ID.
	 * @return   true|WP_Error        True or error.
	 */
	private function validate_campaign_data( array $data, ?int $id = null ): bool|WP_Error {
		$errors = array();

		$this->validate_required_fields( $data, $id, $errors );
		$this->validate_date_range( $data, $id, $errors );
		$this->validate_priority( $data, $errors );
		$this->validate_status( $data, $errors );
		$this->validate_related_data( $data, $errors );

		if ( ! empty( $errors ) ) {
			return new WP_Error( 'validation_failed', 'Validation failed.', $errors );
		}

		return true;
	}

	/**
	 * Validate required fields.
	 *
	 * @since    1.0.0
	 * @param    array    $data       Campaign data.
	 * @param    int|null $id         Campaign ID.
	 * @param    array    &$errors    Errors array.
	 * @return   void
	 */
	private function validate_required_fields( array $data, ?int $id, array &$errors ): void {
		if ( ! $id ) {
			if ( empty( $data['name'] ) ) {
				$errors['name'] = 'Campaign name is required.';
			}

			if ( empty( $data['created_by'] ) ) {
				$errors['created_by'] = 'Created by user is required.';
			}
		}
	}

	/**
	 * Validate date range.
	 *
	 * Validates that:
	 * 1. End date is after start date
	 * 2. End date is not in the past (for active/scheduled campaigns)
	 * 3. Start date is not in the past (for scheduled campaigns)
	 *
	 * @since    1.0.0
	 * @param    array    $data       Campaign data.
	 * @param    int|null $id         Campaign ID (null for new campaigns).
	 * @param    array    &$errors    Errors array.
	 * @return   void
	 */
	private function validate_date_range( array $data, ?int $id, array &$errors ): void {
		if ( empty( $data['starts_at'] ) && empty( $data['ends_at'] ) ) {
			return;
		}

		// Get current time in UTC
		$now = new DateTime( 'now', new DateTimeZone( 'UTC' ) );

		// Parse dates in UTC timezone (matches database storage)
		$starts_at = null;
		$ends_at   = null;

		if ( ! empty( $data['starts_at'] ) ) {
			$starts_at = is_string( $data['starts_at'] ) ? new DateTime( $data['starts_at'], new DateTimeZone( 'UTC' ) ) : $data['starts_at'];
		}

		if ( ! empty( $data['ends_at'] ) ) {
			$ends_at = is_string( $data['ends_at'] ) ? new DateTime( $data['ends_at'], new DateTimeZone( 'UTC' ) ) : $data['ends_at'];
		}

		// Validation 1: End date must be after start date
		if ( $starts_at && $ends_at && $starts_at >= $ends_at ) {
			$errors['dates'] = __( 'End date must be after start date.', 'smart-cycle-discounts' );
			return; // Stop further validation if this basic check fails
		}

		// Get the target status (from data or existing campaign)
		$target_status = null;
		if ( ! empty( $data['status'] ) ) {
			$target_status = $data['status'];
		} elseif ( $id ) {
			// Get existing campaign status
			$existing_campaign = $this->find( $id );
			if ( $existing_campaign ) {
				$target_status = $existing_campaign->get_status();
			}
		}

		// Validation 2: Prevent past end dates for active/scheduled/draft campaigns
		if ( $ends_at && $target_status && in_array( $target_status, array( 'draft', 'active', 'scheduled' ), true ) ) {
			if ( $ends_at <= $now ) {
				$errors['ends_at'] = sprintf(
					__( 'End date must be in the future. The date you selected (%s) has already passed.', 'smart-cycle-discounts' ),
					wp_date( 'F j, Y g:i A', $ends_at->getTimestamp() )
				);
			}
		}

		// Validation 3: Prevent past start dates for scheduled campaigns
		if ( $starts_at && 'scheduled' === $target_status ) {
			if ( $starts_at <= $now ) {
				$errors['starts_at'] = sprintf(
					__( 'Start date must be in the future for scheduled campaigns. The date you selected (%s) has already passed.', 'smart-cycle-discounts' ),
					wp_date( 'F j, Y g:i A', $starts_at->getTimestamp() )
				);
			}
		}
	}

	/**
	 * Validate priority.
	 *
	 * @since    1.0.0
	 * @param    array $data       Campaign data.
	 * @param    array &$errors    Errors array.
	 * @return   void
	 */
	private function validate_priority( array $data, array &$errors ): void {
		if ( isset( $data['priority'] ) && ( $data['priority'] < 1 || $data['priority'] > 10 ) ) {
			$errors['priority'] = 'Priority must be between 1 and 10.';
		}
	}

	/**
	 * Validate status.
	 *
	 * @since    1.0.0
	 * @param    array $data       Campaign data.
	 * @param    array &$errors    Errors array.
	 * @return   void
	 */
	private function validate_status( array $data, array &$errors ): void {
		$valid_statuses = array( 'draft', 'scheduled', 'active', 'paused', 'expired', 'archived' );
		if ( isset( $data['status'] ) && ! in_array( $data['status'], $valid_statuses, true ) ) {
			$errors['status'] = 'Invalid campaign status.';
		}
	}

	/**
	 * Validate related data (products, categories).
	 *
	 * Ensures that referenced products and categories actually exist.
	 * Prevents runtime errors when campaigns reference deleted/invalid data.
	 *
	 * @since    1.0.0
	 * @param    array $data       Campaign data.
	 * @param    array &$errors    Errors array.
	 * @return   void
	 */
	private function validate_related_data( array $data, array &$errors ): void {
		// Validate product IDs if present
		if ( ! empty( $data['product_ids'] ) && is_array( $data['product_ids'] ) ) {
			// Check if WooCommerce is active
			if ( ! function_exists( 'wc_get_product' ) ) {
				// WooCommerce not active, skip validation
				return;
			}

			// Separate numeric and non-numeric IDs
			$numeric_ids     = array();
			$non_numeric_ids = array();

			foreach ( $data['product_ids'] as $product_id ) {
				if ( ! is_numeric( $product_id ) ) {
					$non_numeric_ids[] = $product_id;
				} else {
					$numeric_ids[] = absint( $product_id );
				}
			}

			// PERFORMANCE FIX: Batch query instead of N+1
			// Query all product IDs at once using WordPress $wpdb
			$invalid_products = $non_numeric_ids; // Start with non-numeric IDs

			if ( ! empty( $numeric_ids ) ) {
				global $wpdb;

				// Build safe query with proper placeholders
				$placeholders = implode( ',', array_fill( 0, count( $numeric_ids ), '%d' ) );
				$query        = $wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} WHERE ID IN ($placeholders) AND post_type IN ('product', 'product_variation') AND post_status != 'trash'",
					...$numeric_ids
				);

				// Get all existing product IDs
				$existing_ids = $wpdb->get_col( $query );

				// Find IDs that don't exist
				$missing_ids      = array_diff( $numeric_ids, array_map( 'intval', $existing_ids ) );
				$invalid_products = array_merge( $invalid_products, $missing_ids );
			}

			if ( ! empty( $invalid_products ) ) {
				$errors['product_ids'] = sprintf(
					/* translators: %s: comma-separated list of invalid product IDs */
					__( 'The following product IDs do not exist: %s', 'smart-cycle-discounts' ),
					implode( ', ', $invalid_products )
				);
			}
		}

		// Validate category IDs if present
		if ( ! empty( $data['category_ids'] ) && is_array( $data['category_ids'] ) ) {
			// Separate numeric and non-numeric IDs
			$numeric_ids     = array();
			$non_numeric_ids = array();

			foreach ( $data['category_ids'] as $category_id ) {
				if ( ! is_numeric( $category_id ) ) {
					$non_numeric_ids[] = $category_id;
				} else {
					$numeric_ids[] = absint( $category_id );
				}
			}

			// PERFORMANCE FIX: Batch query instead of N+1
			// Query all category IDs at once using WordPress $wpdb
			$invalid_categories = $non_numeric_ids; // Start with non-numeric IDs

			if ( ! empty( $numeric_ids ) ) {
				global $wpdb;

				// Build safe query with proper placeholders
				$placeholders = implode( ',', array_fill( 0, count( $numeric_ids ), '%d' ) );
				$query        = $wpdb->prepare(
					"SELECT term_id FROM {$wpdb->term_taxonomy} WHERE term_id IN ($placeholders) AND taxonomy = 'product_cat'",
					...$numeric_ids
				);

				// Get all existing category IDs
				$existing_ids = $wpdb->get_col( $query );

				// Find IDs that don't exist
				$missing_ids        = array_diff( $numeric_ids, array_map( 'intval', $existing_ids ) );
				$invalid_categories = array_merge( $invalid_categories, $missing_ids );
			}

			if ( ! empty( $invalid_categories ) ) {
				$errors['category_ids'] = sprintf(
					/* translators: %s: comma-separated list of invalid category IDs */
					__( 'The following category IDs do not exist: %s', 'smart-cycle-discounts' ),
					implode( ', ', $invalid_categories )
				);
			}
		}
	}

	/**
	 * Set default values.
	 *
	 * @since    1.0.0
	 * @param    array $data    Campaign data.
	 * @return   array             Data with defaults.
	 */
	private function set_default_values( array $data ): array {
		$defaults = array(
			'status'      => 'draft',
			'priority'    => 5,
			'settings'    => array(),
			'metadata'    => array(),
			'color_theme' => '#2271b1',
			'icon'        => 'dashicons-tag',
			'timezone'    => wp_timezone_string(),
			'created_by'  => get_current_user_id(),
		);

		return array_merge( $defaults, $data );
	}


	/**
	 * Sort campaigns by priority.
	 *
	 * PRIORITY LOGIC: Lower numbers = higher priority (1 = highest, 10 = lowest)
	 * When multiple campaigns apply to same product, campaign with priority 1 wins over priority 10.
	 *
	 * @since    1.0.0
	 * @param    array $campaigns    Campaigns to sort.
	 * @return   array                  Sorted campaigns.
	 */
	private function sort_campaigns_by_priority( array $campaigns ): array {
		usort(
			$campaigns,
			function ( $a, $b ) {
				// FIXED: Ascending order - lower numbers first (1 = highest priority)
				return $a->get_priority() <=> $b->get_priority();
			}
		);

		return $campaigns;
	}

	/**
	 * Compile product selection for campaign.
	 *
	 * For campaigns with random_products, smart_selection, or product_conditions,
	 * this method resolves the actual product IDs and stores them in product_ids.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign    Campaign object.
	 * @return   void
	 */
	private function compile_product_selection( SCD_Campaign $campaign ): void {
		$selection_type = $campaign->get_product_selection_type();
		$metadata       = $campaign->get_metadata();
		$has_conditions = ! empty( $metadata['product_conditions'] );

		// Determine if compilation is needed
		$needs_random_selection = in_array( $selection_type, array( 'random_products', 'smart_selection' ), true );

		if ( ! $needs_random_selection && ! $has_conditions ) {
			return; // No compilation needed
		}

		// Skip if products are already compiled (don't re-select on reactivation)
		// BUT always recompile if conditions changed
		$existing_products = $campaign->get_product_ids();
		if ( ! empty( $existing_products ) && ! $has_conditions ) {
			$this->log(
				'info',
				'Products already compiled, skipping recompilation',
				array(
					'campaign_id'   => $campaign->get_id(),
					'product_count' => count( $existing_products ),
				)
			);
			return;
		}

		// Check if product_selector is available
		if ( ! $this->container || ! $this->container->has( 'product_selector' ) ) {
			$this->log(
				'warning',
				'Product selector not available for compilation',
				array(
					'campaign_id'    => $campaign->get_id(),
					'selection_type' => $selection_type,
				)
			);
			return;
		}

		try {
			$product_selector = $this->container->get( 'product_selector' );
			$category_ids     = $campaign->get_category_ids();
			$conditions       = $metadata['product_conditions'] ?? array();
			$conditions_logic = $metadata['conditions_logic'] ?? 'all';
			$selected_ids     = array();

			// Case 1: Handle campaigns with conditions (filter-based selection)
			if ( $has_conditions && ! in_array( $selection_type, array( 'random_products', 'smart_selection' ), true ) ) {
				// Clean category IDs (remove 'all' placeholder)
				$clean_categories = array_filter(
					$category_ids,
					function ( $id ) {
						return 'all' !== $id && ! empty( $id );
					}
				);

				// Build criteria for Product Selector (use correct key names)
				$criteria = array(
					'categories'       => ! empty( $clean_categories ) ? $clean_categories : array(),
					'conditions'       => $conditions,
					'conditions_logic' => $conditions_logic,
				);

				// Select products using conditions
				$selected_ids = $product_selector->select_products( $criteria );

				$this->log(
					'info',
					'Products compiled from conditions',
					array(
						'campaign_id'      => $campaign->get_id(),
						'selection_type'   => $selection_type,
						'conditions_count' => count( $conditions ),
						'product_count'    => count( $selected_ids ),
						'conditions'       => $conditions,
						'selected_ids'     => $selected_ids,
					)
				);

			} elseif ( 'random_products' === $selection_type ) {
				// Get random_count from metadata or settings
				$random_count = $metadata['random_count'] ?? $campaign->get_settings()['random_count'] ?? 5;

				// Select random products
				$selected_ids = $product_selector->select_random_by_categories(
					$category_ids,
					intval( $random_count ),
					$conditions
				);

				$this->log(
					'info',
					'Random products compiled',
					array(
						'campaign_id' => $campaign->get_id(),
						'count'       => count( $selected_ids ),
					)
				);

			} elseif ( 'smart_selection' === $selection_type ) {
				// Get smart criteria
				$smart_criteria = $metadata['smart_criteria'] ?? array();

				// Select products based on smart criteria
				$selected_ids = $product_selector->select_by_smart_criteria(
					is_array( $smart_criteria ) ? $smart_criteria : array( $smart_criteria ),
					$category_ids,
					$conditions
				);

				$this->log(
					'info',
					'Smart selection compiled',
					array(
						'campaign_id' => $campaign->get_id(),
						'count'       => count( $selected_ids ),
						'criteria'    => $smart_criteria,
					)
				);
			}

			// Update campaign with selected product IDs
			if ( ! empty( $selected_ids ) ) {
				$campaign->set_product_ids( $selected_ids );

				// Save the campaign
				$save_result = $this->repository->save( $campaign );
				if ( ! $save_result ) {
					$this->log(
						'error',
						'Failed to save compiled product selection',
						array(
							'campaign_id' => $campaign->get_id(),
						)
					);

					// Debug output if WP_DEBUG is on
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					}
				} else {
					// Success - log it
					$this->log(
						'info',
						'Successfully compiled and saved product selection',
						array(
							'campaign_id'   => $campaign->get_id(),
							'product_count' => count( $selected_ids ),
							'product_ids'   => $selected_ids,
						)
					);

					// Debug output if WP_DEBUG is on
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					}
				}
			} else {
				$this->log(
					'warning',
					'No products selected during compilation',
					array(
						'campaign_id'    => $campaign->get_id(),
						'selection_type' => $selection_type,
						'category_ids'   => $category_ids,
					)
				);

			}
		} catch ( Exception $e ) {
			$this->log(
				'error',
				'Product selection compilation failed',
				array(
					'campaign_id' => $campaign->get_id(),
					'error'       => $e->getMessage(),
				)
			);
		}
	}

	/**
	 * Get active campaigns for specific product.
	 *
	 * @since    1.0.0
	 * @param    int $product_id    Product ID.
	 * @return   array                 Active campaigns.
	 */
	public function get_active_campaigns_for_product( int $product_id ): array {
		$cache_key = 'active_campaigns_product_' . $product_id;

		return $this->cache->remember(
			$cache_key,
			function () use ( $product_id ) {
				$active_campaigns = $this->get_active_campaigns();
				if ( empty( $active_campaigns ) ) {
					return array();
				}

				$product_terms = $this->fetch_product_terms( $product_id );
				$applicable    = array();

				foreach ( $active_campaigns as $campaign ) {
					if ( $this->evaluate_campaign_for_product( $campaign, $product_id, $product_terms ) ) {
						$applicable[] = $campaign;
					}
				}

				return $this->sort_campaigns_by_priority( $applicable );
			},
			300
		); // Cache for 5 minutes
	}

	/**
	 * Fetch product terms.
	 *
	 * @since    1.0.0
	 * @param    int $product_id    Product ID.
	 * @return   array                 Product terms.
	 */
	private function fetch_product_terms( int $product_id ): array {
		$terms = array(
			'categories' => $this->get_product_term_ids( $product_id, 'product_cat' ),
			'tags'       => $this->get_product_term_ids( $product_id, 'product_tag' ),
		);

		return $terms;
	}

	/**
	 * Get product term IDs.
	 *
	 * @since    1.0.0
	 * @param    int    $product_id    Product ID.
	 * @param    string $taxonomy      Taxonomy name.
	 * @return   array                    Term IDs.
	 */
	private function get_product_term_ids( int $product_id, string $taxonomy ): array {
		$terms = wp_get_post_terms( $product_id, $taxonomy, array( 'fields' => 'ids' ) );
		return is_wp_error( $terms ) ? array() : $terms;
	}

	/**
	 * Evaluate campaign for product.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign         Campaign object.
	 * @param    int          $product_id       Product ID.
	 * @param    array        $product_terms    Product terms.
	 * @return   bool                              Matches product.
	 */
	private function evaluate_campaign_for_product( SCD_Campaign $campaign, int $product_id, array $product_terms ): bool {
		$selection_type      = $campaign->get_product_selection_type();
		$campaign_categories = $this->clean_category_ids( $campaign->get_category_ids() );

		switch ( $selection_type ) {
			case 'all_products':
				// Check if product_ids are populated (from condition-based compilation)
				$product_ids = $campaign->get_product_ids();
				if ( ! empty( $product_ids ) ) {
					// Treat as specific_products if compiled with conditions
					return in_array( $product_id, $product_ids, true );
				}
				// Fallback to category filter if no conditions
				return $this->matches_category_filter( $campaign_categories, $product_terms['categories'] );

			case 'specific_products':
				$product_ids = $campaign->get_product_ids();
				return in_array( $product_id, $product_ids, true );

			case 'random_products':
			case 'smart_selection':
				// After compilation, these types have product_ids set
				// Check if product_ids are populated (meaning compilation has run)
				$product_ids = $campaign->get_product_ids();
				if ( ! empty( $product_ids ) ) {
					// Treat as specific_products if compiled
					return in_array( $product_id, $product_ids, true );
				}
				// Fallback to category filter if not yet compiled (shouldn't happen for active campaigns)
				return $this->matches_category_filter( $campaign_categories, $product_terms['categories'] );

			default:
				return false;
		}
	}

	/**
	 * Clean category IDs.
	 *
	 * @since    1.0.0
	 * @param    array $category_ids    Category IDs.
	 * @return   array                     Cleaned IDs.
	 */
	private function clean_category_ids( array $category_ids ): array {
		return array_filter(
			$category_ids,
			function ( $id ) {
				return 'all' !== $id;
			}
		);
	}

	/**
	 * Check category filter match.
	 *
	 * @since    1.0.0
	 * @param    array $campaign_categories    Campaign categories.
	 * @param    array $product_categories     Product categories.
	 * @return   bool                             Matches filter.
	 */
	private function matches_category_filter( array $campaign_categories, array $product_categories ): bool {
		if ( empty( $campaign_categories ) ) {
			return true; // No filter
		}

		return ! empty( array_intersect( $campaign_categories, $product_categories ) );
	}

	/**
	 * Validate for activation.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign    Campaign to validate.
	 * @return   true|WP_Error               True or error.
	 */
	private function validate_for_activation( SCD_Campaign $campaign ): bool|WP_Error {
		$errors = array();

		$this->log_activation_validation( $campaign );
		$this->validate_discount_configuration( $campaign, $errors );
		$this->validate_product_selection( $campaign, $errors );

		if ( ! empty( $errors ) ) {
			$this->log_activation_failure( $campaign, $errors );
			return new WP_Error( 'activation_validation_failed', 'Campaign is not ready for activation.', $errors );
		}

		return true;
	}

	/**
	 * Log activation validation.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign    Campaign object.
	 * @return   void
	 */
	private function log_activation_validation( SCD_Campaign $campaign ): void {
		$this->log(
			'debug',
			'Campaign activation validation',
			array(
				'campaign_id'            => $campaign->get_id(),
				'campaign_name'          => $campaign->get_name(),
				'settings'               => $campaign->get_settings(),
				'discount_type'          => $campaign->get_discount_type(),
				'discount_value'         => $campaign->get_discount_value(),
				'product_selection_type' => $campaign->get_product_selection_type(),
			)
		);
	}

	/**
	 * Validate discount configuration.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign    Campaign object.
	 * @param    array        &$errors     Errors array.
	 * @return   void
	 */
	private function validate_discount_configuration( SCD_Campaign $campaign, array &$errors ): void {
		$settings       = $campaign->get_settings();
		$discount_type  = $settings['discount_type'] ?? $campaign->get_discount_type();
		$discount_value = $settings['discount_value'] ?? $campaign->get_discount_value();

		if ( empty( $discount_type ) ) {
			$errors['discount_type'] = 'Discount type is required for activation.';
		}

		if ( empty( $discount_value ) && 0 !== $discount_value ) {
			$errors['discount_value'] = 'Discount value is required for activation.';
		}
	}

	/**
	 * Validate product selection.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign    Campaign object.
	 * @param    array        &$errors     Errors array.
	 * @return   void
	 */
	private function validate_product_selection( SCD_Campaign $campaign, array &$errors ): void {
		if ( ! $this->has_valid_product_selection( $campaign ) ) {
			$errors['products'] = 'At least one product, category, tag must be selected, or campaign must apply to all products.';

			$this->log(
				'warning',
				'Campaign activation failed - no product selection',
				array(
					'campaign_id'            => $campaign->get_id(),
					'product_selection_type' => $campaign->get_product_selection_type(),
					'settings'               => $campaign->get_settings(),
				)
			);
		}
	}

	/**
	 * Check valid product selection.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign    Campaign object.
	 * @return   bool                         Has selection.
	 */
	private function has_valid_product_selection( SCD_Campaign $campaign ): bool {
		$settings       = $campaign->get_settings();
		$selection_type = $campaign->get_product_selection_type();

		if ( 'all' === $selection_type || ! empty( $settings['applies_to_all'] ) ) {
			return true;
		}

		if ( ! empty( $settings['product_ids'] ) || ! empty( $campaign->get_product_ids() ) ) {
			return true;
		}

		if ( ! empty( $settings['categories'] ) || ! empty( $campaign->get_category_ids() ) ) {
			return true;
		}

		if ( ! empty( $settings['tags'] ) || ! empty( $campaign->get_tag_ids() ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Log activation failure.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign    Campaign object.
	 * @param    array        $errors      Errors array.
	 * @return   void
	 */
	private function log_activation_failure( SCD_Campaign $campaign, array $errors ): void {
		$this->log(
			'error',
			'Campaign activation validation failed',
			array(
				'campaign_id' => $campaign->get_id(),
				'errors'      => $errors,
			)
		);
	}

	/**
	 * Get event scheduler service from container.
	 *
	 * @since    1.0.0
	 * @return   SCD_Campaign_Event_Scheduler|null    Event scheduler or null if not available.
	 */
	private function get_scheduler_service(): ?SCD_Campaign_Event_Scheduler {
		if ( $this->container && method_exists( $this->container, 'get' ) ) {
			try {
				return $this->container->get( 'campaign_event_scheduler' );
			} catch ( Exception $e ) {
				$this->log(
					'warning',
					'Could not get event scheduler service',
					array(
						'error' => $e->getMessage(),
					)
				);
			}
		}
		return null;
	}

	/**
	 * Bulk activate campaigns.
	 *
	 * Activates multiple campaigns efficiently by batching database operations
	 * and clearing caches only once at the end. Prevents timeout issues when
	 * activating many campaigns.
	 *
	 * @since    1.0.0
	 * @param    array $campaign_ids    Array of campaign IDs to activate.
	 * @return   array                     Results with 'success' and 'failed' arrays.
	 */
	public function bulk_activate( array $campaign_ids ): array {
		$results = array(
			'success' => array(),
			'failed'  => array(),
		);

		// Temporarily disable certain hooks to prevent duplicate processing
		$remove_hooks = array(
			'scd_campaign_activated' => array(),
		);

		// Store existing hook callbacks to restore later
		foreach ( $remove_hooks as $hook_name => $callbacks ) {
			global $wp_filter;
			if ( isset( $wp_filter[ $hook_name ] ) ) {
				$remove_hooks[ $hook_name ] = $wp_filter[ $hook_name ];
				remove_all_actions( $hook_name );
			}
		}

		try {
			foreach ( $campaign_ids as $id ) {
				$result = $this->activate( $id );
				if ( is_wp_error( $result ) ) {
					$results['failed'][ $id ] = $result->get_error_message();
				} else {
					$results['success'][] = $id;
				}
			}
		} finally {
			// Restore hooks
			foreach ( $remove_hooks as $hook_name => $callbacks ) {
				if ( ! empty( $callbacks ) ) {
					global $wp_filter;
					$wp_filter[ $hook_name ] = $callbacks;
				}
			}

			// Clear all campaign caches once at the end (more efficient than per-campaign)
			wp_cache_delete( 'active_campaigns', 'scd' );
			wp_cache_delete( 'scheduled_campaigns', 'scd' );
			delete_transient( '_transient_scd_active_campaigns' );
		}

		// Fire bulk activation hook
		if ( ! empty( $results['success'] ) ) {
			do_action( 'scd_campaigns_bulk_activated', $results['success'] );
		}

		$this->log(
			'info',
			'Bulk campaign activation completed',
			array(
				'success_count' => count( $results['success'] ),
				'failed_count'  => count( $results['failed'] ),
			)
		);

		return $results;
	}

	/**
	 * Bulk pause campaigns.
	 *
	 * @since    1.0.0
	 * @param    array $campaign_ids    Array of campaign IDs to pause.
	 * @return   array                     Results with 'success' and 'failed' arrays.
	 */
	public function bulk_pause( array $campaign_ids ): array {
		$results = array(
			'success' => array(),
			'failed'  => array(),
		);

		foreach ( $campaign_ids as $id ) {
			$result = $this->pause( $id );
			if ( is_wp_error( $result ) ) {
				$results['failed'][ $id ] = $result->get_error_message();
			} else {
				$results['success'][] = $id;
			}
		}

		// Clear caches once
		wp_cache_delete( 'active_campaigns', 'scd' );
		wp_cache_delete( 'paused_campaigns', 'scd' );

		if ( ! empty( $results['success'] ) ) {
			do_action( 'scd_campaigns_bulk_paused', $results['success'] );
		}

		$this->log(
			'info',
			'Bulk campaign pause completed',
			array(
				'success_count' => count( $results['success'] ),
				'failed_count'  => count( $results['failed'] ),
			)
		);

		return $results;
	}

	/**
	 * Bulk delete campaigns.
	 *
	 * @since    1.0.0
	 * @param    array $campaign_ids    Array of campaign IDs to delete.
	 * @return   array                     Results with 'success' and 'failed' arrays.
	 */
	public function bulk_delete( array $campaign_ids ): array {
		$results = array(
			'success' => array(),
			'failed'  => array(),
		);

		foreach ( $campaign_ids as $id ) {
			$result = $this->delete( $id );
			if ( is_wp_error( $result ) ) {
				$results['failed'][ $id ] = $result->get_error_message();
			} else {
				$results['success'][] = $id;
			}
		}

		if ( ! empty( $results['success'] ) ) {
			do_action( 'scd_campaigns_bulk_deleted', $results['success'] );
		}

		$this->log(
			'info',
			'Bulk campaign deletion completed',
			array(
				'success_count' => count( $results['success'] ),
				'failed_count'  => count( $results['failed'] ),
			)
		);

		return $results;
	}
}
