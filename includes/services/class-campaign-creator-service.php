<?php
/**
 * Campaign Creator Service
 *
 * Centralized service for campaign creation from various sources.
 *
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/services
 */

declare(strict_types=1);


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Campaign Creator Service Class
 *
 * Single source of truth for campaign creation logic.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/services
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Campaign_Creator_Service {

	/**
	 * Campaign manager.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Campaign_Manager    $campaign_manager    Campaign manager.
	 */
	private SCD_Campaign_Manager $campaign_manager;

	/**
	 * Campaign compiler.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Campaign_Compiler_Service    $compiler    Campaign compiler.
	 */
	private SCD_Campaign_Compiler_Service $compiler;

	/**
	 * Logger instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Logger    $logger    Logger.
	 */
	private SCD_Logger $logger;

	/**
	 * Audit logger instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Audit_Logger|null    $audit_logger    Audit logger.
	 */
	private ?SCD_Audit_Logger $audit_logger;

	/**
	 * Feature gate instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Feature_Gate|null    $feature_gate    Feature gate.
	 */
	private ?SCD_Feature_Gate $feature_gate;

	/**
	 * Constructor.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign_Manager          $campaign_manager    Campaign manager.
	 * @param    SCD_Campaign_Compiler_Service $compiler           Campaign compiler.
	 * @param    SCD_Logger                    $logger             Logger.
	 * @param    SCD_Audit_Logger|null         $audit_logger       Audit logger.
	 * @param    SCD_Feature_Gate|null         $feature_gate       Feature gate.
	 */
	public function __construct(
		SCD_Campaign_Manager $campaign_manager,
		SCD_Campaign_Compiler_Service $compiler,
		SCD_Logger $logger,
		?SCD_Audit_Logger $audit_logger = null,
		?SCD_Feature_Gate $feature_gate = null
	) {
		$this->campaign_manager = $campaign_manager;
		$this->compiler         = $compiler;
		$this->logger           = $logger;
		$this->audit_logger     = $audit_logger;
		$this->feature_gate     = $feature_gate;
	}

	/**
	 * Create campaign from wizard session.
	 *
	 * @since    1.0.0
	 * @param    SCD_Wizard_State_Service $state_service    Wizard state service.
	 * @param    bool                     $save_as_draft    Whether to save as draft.
	 * @return   array                                        Result array with success status and data.
	 */
	public function create_from_wizard( SCD_Wizard_State_Service $state_service, bool $save_as_draft = false ): array {
		try {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[Campaign_Creator_Service] create_from_wizard() called with save_as_draft=' . ( $save_as_draft ? 'true' : 'false' ) );
			}

			// Validate capability
			if ( ! current_user_can( 'scd_create_campaigns' ) && ! current_user_can( 'manage_options' ) ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( '[Campaign_Creator_Service] ERROR: User lacks capability' );
				}
				return $this->error_response(
					__( 'You do not have permission to create campaigns.', 'smart-cycle-discounts' ),
					403
				);
			}

			// Check campaign limit for new campaigns (not when editing existing)
			$session_data    = $state_service->get_all_data();
			$is_new_campaign = empty( $session_data['campaign_id'] );

			if ( $is_new_campaign && ! $save_as_draft && $this->feature_gate ) {
				if ( ! $this->feature_gate->is_premium() ) {
					$repository = $this->campaign_manager->get_repository();
					if ( $repository ) {
						$current_count = $repository->count(
							array(
								'status__not' => 'deleted',
							)
						);

						if ( ! $this->feature_gate->can_create_campaign( $current_count ) ) {
							$campaign_limit = $this->feature_gate->get_campaign_limit();
							return $this->error_response(
								sprintf(
									/* translators: %d: campaign limit */
									__( 'Campaign limit reached. Free plan is limited to %d campaigns. Please upgrade to Pro for unlimited campaigns.', 'smart-cycle-discounts' ),
									$campaign_limit
								),
								403
							);
						}
					}
				}
			}

			// Validate wizard completion
			$progress = $state_service->get_progress();
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[Campaign_Creator_Service] Progress can_complete: ' . ( $progress['can_complete'] ? 'true' : 'false' ) );
			}

			if ( ! $save_as_draft && ! $progress['can_complete'] ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( '[Campaign_Creator_Service] ERROR: Wizard not complete' );
				}
				return $this->error_response(
					__( 'Please complete all required steps before creating the campaign.', 'smart-cycle-discounts' ),
					400
				);
			}

			// For draft saves, ensure we have at least basic info
			if ( $save_as_draft ) {
				$basic_data = $state_service->get_step_data( 'basic' );
				if ( empty( $basic_data['name'] ) ) {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( '[Campaign_Creator_Service] ERROR: Campaign name missing for draft' );
					}
					return $this->error_response(
						__( 'Campaign name is required to save as draft.', 'smart-cycle-discounts' ),
						400
					);
				}
			}

			// Compile campaign data from session
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[Campaign_Creator_Service] Compiling campaign data from session' );
			}

			$session_data = $state_service->get_all_data();
			$steps_data   = isset( $session_data['steps'] ) ? $session_data['steps'] : array();

			if ( empty( $steps_data ) ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( '[Campaign_Creator_Service] ERROR: No steps data in session' );
				}
				return $this->error_response(
					__( 'No campaign data found in session.', 'smart-cycle-discounts' ),
					400
				);
			}

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[Campaign_Creator_Service] Steps data keys: ' . implode( ', ', array_keys( $steps_data ) ) );
			}

			$campaign_data = $this->compiler->compile( $steps_data );
			if ( ! $campaign_data ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( '[Campaign_Creator_Service] ERROR: Compiler returned empty/false' );
				}
				return $this->error_response(
					__( 'Failed to compile campaign data.', 'smart-cycle-discounts' ),
					500
				);
			}

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[Campaign_Creator_Service] Campaign data compiled successfully' );
			}

			// CRITICAL FIX: Override status based on save_as_draft parameter
			// The compiler expects launch_option in review step data, but review step doesn't save its state
			// So we need to set the status here based on the save_as_draft parameter from the AJAX request
			if ( $save_as_draft ) {
				$campaign_data['status'] = 'draft';
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( '[Campaign_Creator_Service] Status set to DRAFT (save_as_draft=true)' );
				}
			} else {
				// User selected "Launch" - determine status based on start time
				// Check if campaign start time is in the future
				$is_future_campaign = false;
				if ( isset( $campaign_data['starts_at'] ) && ! empty( $campaign_data['starts_at'] ) ) {
					$start_timestamp    = strtotime( $campaign_data['starts_at'] );
					$now_timestamp      = time();
					$is_future_campaign = ( $start_timestamp > $now_timestamp );
				}

				// Future campaigns are 'scheduled', immediate/past campaigns are 'active'
				$campaign_data['status'] = $is_future_campaign ? 'scheduled' : 'active';

				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( '[Campaign_Creator_Service] Status set to ' . strtoupper( $campaign_data['status'] ) . ' (save_as_draft=false, is_future=' . ( $is_future_campaign ? 'true' : 'false' ) . ')' );
				}
			}

			// Set created_by metadata
			$campaign_data['created_by'] = get_current_user_id();

			// Set validation context for compiled campaign data
			// This tells Campaign_Manager to use 'campaign_compiled' validation
			// instead of 'campaign_complete' (step-based) validation
			$campaign_data['_validation_context'] = 'campaign_compiled';

			// CRITICAL: Validate PRO features in final campaign data (security layer)
			// This is the last line of defense before campaign creation
			$pro_validation = $this->validate_pro_features( $campaign_data );
			if ( is_wp_error( $pro_validation ) ) {
				return $this->error_response(
					$pro_validation->get_error_message(),
					403
				);
			}

			// Create or update the campaign
			$campaign_id = isset( $session_data['campaign_id'] ) ? absint( $session_data['campaign_id'] ) : 0;
			$is_update   = $campaign_id > 0;

			if ( $is_update ) {
				// Update existing campaign
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( '[Campaign_Creator_Service] Calling campaign_manager->update() for campaign ID: ' . $campaign_id );
				}

				$campaign = $this->update_with_retry( $campaign_id, $campaign_data );

				if ( is_wp_error( $campaign ) ) {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( '[Campaign_Creator_Service] ERROR: update() returned WP_Error: ' . $campaign->get_error_message() );
					}
					return $this->error_response(
						$campaign->get_error_message(),
						500
					);
				}

				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( '[Campaign_Creator_Service] Campaign updated successfully with ID: ' . $campaign->get_id() );
				}
			} else {
				// Create new campaign
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( '[Campaign_Creator_Service] Calling campaign_manager->create()' );
				}

				$campaign = $this->campaign_manager->create( $campaign_data );

				if ( is_wp_error( $campaign ) ) {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( '[Campaign_Creator_Service] ERROR: create() returned WP_Error: ' . $campaign->get_error_message() );
					}
					return $this->error_response(
						$campaign->get_error_message(),
						500
					);
				}

				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( '[Campaign_Creator_Service] Campaign created successfully with ID: ' . $campaign->get_id() );
				}
			}

			// Clear wizard session
			try {
				$state_service->clear_session();
			} catch ( Exception $e ) {
				// Log but don't fail - session will expire anyway
				$this->logger->warning(
					'Failed to clear wizard session after campaign ' . ( $is_update ? 'update' : 'creation' ),
					array(
						'error' => $e->getMessage(),
					)
				);
			}

			// Log success
			if ( $is_update ) {
				$this->logger->info(
					'Campaign updated from wizard',
					array(
						'campaign_id' => $campaign->get_id(),
						'method'      => 'wizard',
						'save_draft'  => $save_as_draft,
					)
				);
			} else {
				$this->log_campaign_created( $campaign, 'wizard', $save_as_draft );
			}

			// Trigger action for extensions
			if ( $is_update ) {
				do_action( 'scd_campaign_updated_from_wizard', $campaign->get_id(), $campaign_data );
			} else {
				do_action( 'scd_campaign_created_from_wizard', $campaign->get_id(), $campaign_data );
			}

			return $this->success_response( $campaign, $save_as_draft );

		} catch ( Exception $e ) {
			$this->logger->error(
				'Campaign ' . ( isset( $is_update ) && $is_update ? 'update' : 'creation' ) . ' from wizard failed',
				array(
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString(),
				)
			);

			return $this->error_response(
				sprintf( __( 'Campaign %1$s failed: %2$s', 'smart-cycle-discounts' ), ( isset( $is_update ) && $is_update ? 'update' : 'creation' ), $e->getMessage() ),
				500
			);
		}
	}

	/**
	 * Create campaign from data array.
	 *
	 * @since    1.0.0
	 * @param    array $data    Campaign data.
	 * @return   array             Result array with success status and data.
	 */
	public function create_from_data( array $data ): array {
		try {
			// Validate capability
			if ( ! current_user_can( 'scd_create_campaigns' ) && ! current_user_can( 'manage_options' ) ) {
				return $this->error_response(
					__( 'You do not have permission to create campaigns.', 'smart-cycle-discounts' ),
					403
				);
			}

			// Check campaign limit for new campaigns
			$is_new_campaign = empty( $data['id'] );
			$is_draft        = isset( $data['status'] ) && 'draft' === $data['status'];

			if ( $is_new_campaign && ! $is_draft && $this->feature_gate ) {
				if ( ! $this->feature_gate->is_premium() ) {
					$repository = $this->campaign_manager->get_repository();
					if ( $repository ) {
						$current_count = $repository->count(
							array(
								'status__not' => 'deleted',
							)
						);

						if ( ! $this->feature_gate->can_create_campaign( $current_count ) ) {
							$campaign_limit = $this->feature_gate->get_campaign_limit();
							return $this->error_response(
								sprintf(
									/* translators: %d: campaign limit */
									__( 'Campaign limit reached. Free plan is limited to %d campaigns. Please upgrade to Pro for unlimited campaigns.', 'smart-cycle-discounts' ),
									$campaign_limit
								),
								403
							);
						}
					}
				}
			}

			// Ensure created_by is set
			if ( ! isset( $data['created_by'] ) || empty( $data['created_by'] ) ) {
				$data['created_by'] = get_current_user_id();
			}

			// Create the campaign
			$campaign = $this->campaign_manager->create( $data );

			if ( is_wp_error( $campaign ) ) {
				return $this->error_response(
					$campaign->get_error_message(),
					500
				);
			}

			// Log success
			$this->log_campaign_created( $campaign, 'direct' );

			// Trigger action for extensions
			do_action( 'scd_campaign_created_from_data', $campaign->get_id(), $data );

			return $this->success_response( $campaign );

		} catch ( Exception $e ) {
			$this->logger->error(
				'Campaign creation from data failed',
				array(
					'error' => $e->getMessage(),
					'data'  => $data,
				)
			);

			return $this->error_response(
				sprintf( __( 'Campaign creation failed: %s', 'smart-cycle-discounts' ), $e->getMessage() ),
				500
			);
		}
	}

	/**
	 * Duplicate an existing campaign.
	 *
	 * @since    1.0.0
	 * @param    int $campaign_id    Campaign ID to duplicate.
	 * @return   array                 Result array with success status and data.
	 */
	public function duplicate_campaign( int $campaign_id ): array {
		try {
			// Validate capability
			if ( ! current_user_can( 'scd_create_campaigns' ) && ! current_user_can( 'manage_options' ) ) {
				return $this->error_response(
					__( 'You do not have permission to duplicate campaigns.', 'smart-cycle-discounts' ),
					403
				);
			}

			// Check campaign limit (duplicates count as new campaigns)
			if ( $this->feature_gate && ! $this->feature_gate->is_premium() ) {
				$repository = $this->campaign_manager->get_repository();
				if ( $repository ) {
					$current_count = $repository->count(
						array(
							'status__not' => 'deleted',
						)
					);

					if ( ! $this->feature_gate->can_create_campaign( $current_count ) ) {
						$campaign_limit = $this->feature_gate->get_campaign_limit();
						return $this->error_response(
							sprintf(
								/* translators: %d: campaign limit */
								__( 'Campaign limit reached. Free plan is limited to %d campaigns. Please upgrade to Pro for unlimited campaigns.', 'smart-cycle-discounts' ),
								$campaign_limit
							),
							403
						);
					}
				}
			}

			// Get original campaign
			$original = $this->campaign_manager->find( $campaign_id );
			if ( ! $original ) {
				return $this->error_response(
					__( 'Original campaign not found.', 'smart-cycle-discounts' ),
					404
				);
			}

			// Prepare duplicate data
			$duplicate_data = $original->to_array();
			unset( $duplicate_data['id'], $duplicate_data['uuid'], $duplicate_data['created_at'], $duplicate_data['updated_at'] );

			// Update name and status
			$duplicate_data['name']       = sprintf( __( '%s (Copy)', 'smart-cycle-discounts' ), $original->get_name() );
			$duplicate_data['status']     = 'draft';
			$duplicate_data['created_by'] = get_current_user_id();

			// Create the duplicate
			$duplicate = $this->campaign_manager->create( $duplicate_data );

			if ( is_wp_error( $duplicate ) ) {
				return $this->error_response(
					$duplicate->get_error_message(),
					500
				);
			}

			// Log success
			$this->log_campaign_created( $duplicate, 'duplicate', false, $campaign_id );

			// Trigger action for extensions
			do_action( 'scd_campaign_duplicated', $duplicate->get_id(), $campaign_id );

			return $this->success_response( $duplicate );

		} catch ( Exception $e ) {
			$this->logger->error(
				'Campaign duplication failed',
				array(
					'original_id' => $campaign_id,
					'error'       => $e->getMessage(),
				)
			);

			return $this->error_response(
				sprintf( __( 'Campaign duplication failed: %s', 'smart-cycle-discounts' ), $e->getMessage() ),
				500
			);
		}
	}

	/**
	 * Build success response.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign         Created campaign.
	 * @param    bool         $is_draft        Whether campaign is draft.
	 * @return   array                            Response array.
	 */
	private function success_response( SCD_Campaign $campaign, bool $is_draft = false ): array {
		$message = $is_draft
			? __( 'Campaign saved as draft successfully.', 'smart-cycle-discounts' )
			: __( 'Campaign created successfully.', 'smart-cycle-discounts' );

		$redirect_url = $is_draft
			? add_query_arg(
				array(
					'page'    => 'scd-campaigns',
					'status'  => 'draft',
					'message' => 'draft_saved',
				),
				admin_url( 'admin.php' )
			)
			: add_query_arg(
				array(
					'page'    => 'scd-campaigns',
					'action'  => 'view',
					'id'      => $campaign->get_id(),
					'message' => 'created',
				),
				admin_url( 'admin.php' )
			);

		return array(
			'success'       => true,
			'message'       => $message,
			'campaign_id'   => $campaign->get_id(),
			'campaign_uuid' => $campaign->get_uuid(),
			'status'        => $campaign->get_status(),
			'redirect_url'  => $redirect_url,
			'campaign'      => array(
				'id'     => $campaign->get_id(),
				'uuid'   => $campaign->get_uuid(),
				'name'   => $campaign->get_name(),
				'status' => $campaign->get_status(),
			),
		);
	}

	/**
	 * Build error response.
	 *
	 * @since    1.0.0
	 * @param    string $message    Error message.
	 * @param    int    $code       Error code.
	 * @return   array                 Response array.
	 */
	private function error_response( string $message, int $code = 500 ): array {
		return array(
			'success' => false,
			'error'   => $message,
			'code'    => $code,
		);
	}

	/**
	 * Log campaign creation.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign       Created campaign.
	 * @param    string       $source         Creation source.
	 * @param    bool         $is_draft       Whether saved as draft.
	 * @param    int|null     $original_id    Original campaign ID if duplicated.
	 * @return   void
	 */
	private function log_campaign_created(
		SCD_Campaign $campaign,
		string $source,
		bool $is_draft = false,
		?int $original_id = null
	): void {
		$log_data = array(
			'campaign_id'   => $campaign->get_id(),
			'campaign_name' => $campaign->get_name(),
			'status'        => $campaign->get_status(),
			'source'        => $source,
			'user_id'       => get_current_user_id(),
		);

		if ( $original_id ) {
			$log_data['original_campaign_id'] = $original_id;
		}

		$action = $is_draft ? 'Draft saved' : 'Campaign created';
		$this->logger->info( $action, $log_data );

		if ( $this->audit_logger ) {
			$event = $is_draft ? 'draft_saved' : 'campaign_created';
			$this->audit_logger->log_security_event( $event, $action, $log_data );
		}
	}

	/**
	 * Validate PRO features in campaign data.
	 *
	 * Final security check before campaign creation to ensure
	 * free users cannot use PRO features.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $campaign_data    Complete campaign data.
	 * @return   true|WP_Error              True if valid, WP_Error if PRO feature detected.
	 */
	private function validate_pro_features( array $campaign_data ) {
		// If feature gate not available, allow (fail open for safety)
		if ( ! $this->feature_gate ) {
			return true;
		}

		// Load PRO feature validator class
		$validator_path = SCD_PLUGIN_DIR . 'includes/core/validation/class-pro-feature-validator.php';
		if ( ! file_exists( $validator_path ) ) {
			// Validator file missing - log error but allow (fail open)
			$this->logger->warning(
				'PRO feature validator file not found',
				array(
					'path' => $validator_path,
				)
			);
			return true;
		}

		require_once $validator_path;

		if ( ! class_exists( 'SCD_PRO_Feature_Validator' ) ) {
			// Validator class missing - log error but allow (fail open)
			$this->logger->warning( 'PRO feature validator class not found' );
			return true;
		}

		// Create validator and validate complete campaign
		$validator = new SCD_PRO_Feature_Validator( $this->feature_gate );
		$result    = $validator->validate_campaign( $campaign_data );

		// Log validation failures for security auditing
		if ( is_wp_error( $result ) && $this->audit_logger ) {
			$this->audit_logger->log_security_event(
				'pro_feature_violation',
				'Free user attempted to use PRO feature',
				array(
					'user_id'       => get_current_user_id(),
					'error'         => $result->get_error_message(),
					'feature'       => $result->get_error_data()['feature'] ?? 'unknown',
					'campaign_name' => $campaign_data['name'] ?? 'unknown',
				)
			);
		}

		return $result;
	}

	/**
	 * Update campaign with automatic retry on version conflict.
	 *
	 * Implements optimistic locking retry logic:
	 * 1. Attempt update with user's changes
	 * 2. If version conflict detected, reload fresh campaign
	 * 3. Retry update ONCE with fresh version
	 * 4. If still fails, return error to user (genuine conflict)
	 *
	 * @since    1.0.0
	 * @param    int   $campaign_id      Campaign ID.
	 * @param    array $campaign_data    Campaign data to update.
	 * @return   SCD_Campaign|WP_Error      Updated campaign or error.
	 */
	private function update_with_retry( $campaign_id, $campaign_data ) {
		try {
			// First attempt
			return $this->campaign_manager->update( $campaign_id, $campaign_data );

		} catch ( SCD_Concurrent_Modification_Exception $e ) {
			// Version conflict detected - attempt auto-recovery
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[Campaign_Creator_Service] Version conflict detected (expected: ' . $e->get_expected_version() . ', current: ' . $e->get_current_version() . ')' );
				error_log( '[Campaign_Creator_Service] Attempting auto-retry with fresh version...' );
			}

			// Reload fresh campaign from database to get latest version
			$fresh_campaign = $this->campaign_manager->find( $campaign_id );
			if ( ! $fresh_campaign ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( '[Campaign_Creator_Service] Auto-retry failed: Campaign not found' );
				}
				return new WP_Error(
					'campaign_not_found',
					__( 'Campaign not found. It may have been deleted.', 'smart-cycle-discounts' )
				);
			}

			// Merge user's changes with fresh campaign data
			// The fresh campaign has the correct version number from database
			$fresh_data = $fresh_campaign->to_array();

			// Apply user's changes on top of fresh data
			// This preserves any concurrent changes to fields the user didn't modify
			$merged_data = array_merge( $fresh_data, $campaign_data );

			// Preserve the fresh version number (critical for optimistic locking)
			unset( $merged_data['version'] );

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[Campaign_Creator_Service] Retrying update with fresh version ' . $fresh_campaign->get_version() );
			}

			// Retry update with fresh version
			try {
				$result = $this->campaign_manager->update( $campaign_id, $merged_data );

				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					if ( is_wp_error( $result ) ) {
						error_log( '[Campaign_Creator_Service] Auto-retry failed: ' . $result->get_error_message() );
					} else {
						error_log( '[Campaign_Creator_Service] Auto-retry succeeded! Campaign updated with version ' . $result->get_version() );
					}
				}

				return $result;

			} catch ( SCD_Concurrent_Modification_Exception $retry_exception ) {
				// Second failure - genuine concurrent edit conflict
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( '[Campaign_Creator_Service] Auto-retry failed with another version conflict' );
				}

				// Return user-friendly error
				return new WP_Error(
					'concurrent_modification',
					sprintf(
						/* translators: %d: campaign ID */
						__( 'Campaign %d was modified by another user while you were editing. Please refresh the page and try again.', 'smart-cycle-discounts' ),
						$campaign_id
					)
				);
			}
		}
	}
}
