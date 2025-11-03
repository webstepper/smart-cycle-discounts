<?php
/**
 * Campaign Creator Service Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/services/class-campaign-creator-service.php
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
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
		$is_update = false;

		try {
			if ( ! current_user_can( 'scd_create_campaigns' ) && ! current_user_can( 'manage_options' ) ) {
				return $this->error_response(
					__( 'You do not have permission to create campaigns.', 'smart-cycle-discounts' ),
					403
				);
			}

			$session_data    = $state_service->get_all_data();
			$campaign_id     = isset( $session_data['campaign_id'] ) ? absint( $session_data['campaign_id'] ) : 0;
			$is_new_campaign = ( 0 === $campaign_id );
			$is_update       = ! $is_new_campaign;

			$progress = $state_service->get_progress();

			if ( false === $save_as_draft && ! $progress['can_complete'] ) {
				return $this->error_response(
					__( 'Please complete all required steps before creating the campaign.', 'smart-cycle-discounts' ),
					400
				);
			}

			// For draft saves, ensure we have at least basic info.
			if ( true === $save_as_draft ) {
				$basic_data = $state_service->get_step_data( 'basic' );
				if ( empty( $basic_data['name'] ) ) {
					return $this->error_response(
						__( 'Campaign name is required to save as draft.', 'smart-cycle-discounts' ),
						400
					);
				}
			}

			// Compile campaign data from session.
			$steps_data = isset( $session_data['steps'] ) ? $session_data['steps'] : array();

			if ( empty( $steps_data ) ) {
				return $this->error_response(
					__( 'No campaign data found in session.', 'smart-cycle-discounts' ),
					400
				);
			}

			$campaign_data = $this->compiler->compile( $steps_data, $campaign_id );
			if ( ! $campaign_data || empty( $campaign_data ) ) {
				return $this->error_response(
					__( 'Failed to compile campaign data.', 'smart-cycle-discounts' ),
					500
				);
			}

			if ( ! empty( $session_data['from_suggestion'] ) ) {
				if ( ! isset( $campaign_data['metadata'] ) ) {
					$campaign_data['metadata'] = array();
				}
				$campaign_data['metadata']['from_suggestion'] = sanitize_text_field( $session_data['from_suggestion'] );
			}

			// Determine campaign status based on user intent and business rules.
			if ( true === $save_as_draft ) {
				// WordPress-style draft: Always allow draft from any status for editing.
				// This follows WordPress/WooCommerce pattern where draft is a safe editing state.
				if ( $campaign_id > 0 ) {
					// EDIT MODE: Validate transition is allowed before proceeding.
					$existing_campaign = $this->campaign_manager->find( $campaign_id );
					$current_status    = $existing_campaign ? $existing_campaign->get_status() : 'draft';

					// Only validate if not already draft.
					if ( 'draft' !== $current_status ) {
						$state_manager = $this->get_state_manager();

						if ( ! $state_manager->can_transition( $current_status, 'draft' ) ) {
							return $this->error_response(
								sprintf(
									/* translators: %s: Current campaign status */
									__( 'Cannot save %s campaign as draft. This status transition is not allowed.', 'smart-cycle-discounts' ),
									$current_status
								),
								400
							);
						}
					}
				}

				$campaign_data['status'] = 'draft';
			} elseif ( ! isset( $campaign_data['status'] ) || empty( $campaign_data['status'] ) ) {
				// Status not set by compiler - calculate based on campaign mode and dates.
				if ( $campaign_id > 0 ) {
					// EDITING existing campaign - apply status transition rules.
					$existing_campaign = $this->campaign_manager->find( $campaign_id );
					$current_status    = $existing_campaign ? $existing_campaign->get_status() : null;

					$campaign_data['status'] = $this->calculate_status_for_update( $current_status, $campaign_data );
				} else {
					// NEW campaign - calculate initial status from dates.
					$campaign_data['status'] = $this->calculate_initial_status( $campaign_data );
				}
			}
			// else: Status already set by compiler based on user's launch option - respect it.

			$campaign_data['created_by'] = absint( get_current_user_id() );

			// This tells Campaign_Manager to use 'campaign_compiled' validation.
			// instead of 'campaign_complete' (step-based) validation.
			$campaign_data['_validation_context'] = 'campaign_compiled';

			// CRITICAL: Validate PRO features in final campaign data (security layer).
			// This is the last line of defense before campaign creation.
			$pro_validation = $this->validate_pro_features( $campaign_data );
			if ( is_wp_error( $pro_validation ) ) {
				return $this->error_response(
					$pro_validation->get_error_message(),
					403
				);
			}

			if ( true === $is_update ) {
				// Handle special case: active → scheduled requires intermediate paused state.
				$existing_campaign    = $this->campaign_manager->find( $campaign_id );
				$current_status       = $existing_campaign ? $existing_campaign->get_status() : null;
				$needs_pause_workflow = ( 'active' === $current_status && 'paused' === $campaign_data['status'] );

				$campaign = $this->update_with_retry( $campaign_id, $campaign_data );

				if ( is_wp_error( $campaign ) ) {
					return $this->error_response(
						$campaign->get_error_message(),
						500
					);
				}

				if ( false === $campaign || ! $campaign ) {
					return $this->error_response(
						__( 'Failed to update campaign. Please try again.', 'smart-cycle-discounts' ),
						500
					);
				}

				// If we paused an active campaign to reschedule, now transition to scheduled.
				if ( true === $needs_pause_workflow ) {
					$desired_status = $this->calculate_initial_status( $campaign_data );
					if ( 'scheduled' === $desired_status ) {
						// Reload fresh campaign after first update to get current state.
						$fresh_campaign = $this->campaign_manager->find( $campaign_id );
						if ( ! $fresh_campaign ) {
							return $this->error_response(
								__( 'Campaign not found after first update.', 'smart-cycle-discounts' ),
								500
							);
						}

						// Use State Manager to transition directly (bypasses full campaign validation).
						$state_manager = new SCD_Campaign_State_Manager( $this->logger, null );
						$transition    = $state_manager->transition( $fresh_campaign, 'scheduled' );

						if ( is_wp_error( $transition ) ) {
							// ROLLBACK: Restore original status to maintain data integrity.
							$this->logger->warning(
								'Reschedule workflow failed, attempting rollback',
								array(
									'campaign_id'    => $campaign_id,
									'current_status' => $current_status,
									'error'          => $transition->get_error_message(),
								)
							);

							$rollback = $state_manager->transition( $fresh_campaign, $current_status );
							if ( ! is_wp_error( $rollback ) ) {
								$repository = $this->campaign_manager->get_repository();
								if ( $repository ) {
									$repository->save( $fresh_campaign );
								}
							}

							return $this->error_response(
								sprintf(
									/* translators: 1: current status, 2: error message */
									__( 'Failed to reschedule campaign. Status remains: %1$s. Error: %2$s', 'smart-cycle-discounts' ),
									$current_status,
									$transition->get_error_message()
								),
								500
							);
						}

						$repository = $this->campaign_manager->get_repository();
						if ( ! $repository ) {
							return $this->error_response(
								__( 'Database error: Repository unavailable.', 'smart-cycle-discounts' ),
								500
							);
						}

						$campaign = $repository->save( $fresh_campaign );

						if ( is_wp_error( $campaign ) ) {
							return $this->error_response(
								$campaign->get_error_message(),
								500
							);
						}

						if ( false === $campaign || ! $campaign ) {
							return $this->error_response(
								__( 'Failed to save campaign state transition.', 'smart-cycle-discounts' ),
								500
							);
						}
					}
				}
			} else {
				$campaign = $this->campaign_manager->create( $campaign_data );

				if ( is_wp_error( $campaign ) ) {
					return $this->error_response(
						$campaign->get_error_message(),
						500
					);
				}

				// PHASE 1.1: Verify campaign limit AFTER creation (prevents race condition).
				// Draft campaigns count toward limits following WordPress pattern.
				if ( true === $is_new_campaign && $this->feature_gate ) {
					if ( ! $this->feature_gate->is_premium() ) {
						$repository = $this->campaign_manager->get_repository();
						if ( $repository ) {
							$final_count = $repository->count(
								array(
									'status__not' => 'deleted',
								)
							);

							$campaign_limit = $this->feature_gate->get_campaign_limit();

							if ( $final_count > $campaign_limit ) {
								// Exceeded limit - rollback by deleting the just-created campaign.
								$repository->delete( $campaign->get_id() );

								$this->logger->warning(
									'Campaign limit exceeded after creation, rolled back',
									array(
										'campaign_id' => $campaign->get_id(),
										'final_count' => $final_count,
										'limit'       => $campaign_limit,
										'user_id'     => absint( get_current_user_id() ),
									)
								);

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
			}

			try {
				$state_service->clear_session();
			} catch ( Exception $e ) {
				// Log but don't fail - session will expire anyway.
				$this->logger->warning(
					'Failed to clear wizard session after campaign ' . ( true === $is_update ? 'update' : 'creation' ),
					array(
						'error' => $e->getMessage(),
					)
				);
			}

			// Log success.
			if ( true === $is_update ) {
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

			// Trigger action for extensions.
			if ( true === $is_update ) {
				do_action( 'scd_campaign_updated_from_wizard', $campaign->get_id(), $campaign_data );
			} else {
				do_action( 'scd_campaign_created_from_wizard', $campaign->get_id(), $campaign_data );
			}

			return $this->success_response( $campaign, $save_as_draft );

		} catch ( Exception $e ) {
			$this->logger->error(
				'Campaign ' . ( true === $is_update ? 'update' : 'creation' ) . ' from wizard failed',
				array(
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString(),
				)
			);

			return $this->error_response(
				__( 'Campaign operation failed. Please try again.', 'smart-cycle-discounts' ),
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
			if ( ! current_user_can( 'scd_create_campaigns' ) && ! current_user_can( 'manage_options' ) ) {
				return $this->error_response(
					__( 'You do not have permission to create campaigns.', 'smart-cycle-discounts' ),
					403
				);
			}

			// Determine campaign type.
			$is_new_campaign = empty( $data['id'] );
			$is_draft        = isset( $data['status'] ) && 'draft' === $data['status'];

			// Always set created_by to current user (never trust input).
			$data['created_by'] = absint( get_current_user_id() );

			$campaign = $this->campaign_manager->create( $data );

			if ( is_wp_error( $campaign ) ) {
				return $this->error_response(
					$campaign->get_error_message(),
					500
				);
			}

			// PHASE 1.1: Verify campaign limit AFTER creation (prevents race condition).
			// Draft campaigns count toward limits following WordPress pattern.
			if ( true === $is_new_campaign && $this->feature_gate ) {
				if ( ! $this->feature_gate->is_premium() ) {
					$repository = $this->campaign_manager->get_repository();
					if ( $repository ) {
						$final_count = $repository->count(
							array(
								'status__not' => 'deleted',
							)
						);

						$campaign_limit = $this->feature_gate->get_campaign_limit();

						if ( $final_count > $campaign_limit ) {
							// Exceeded limit - rollback by deleting the just-created campaign.
							$repository->delete( $campaign->get_id() );

							$this->logger->warning(
								'Campaign limit exceeded after creation, rolled back',
								array(
									'campaign_id' => $campaign->get_id(),
									'final_count' => $final_count,
									'limit'       => $campaign_limit,
									'user_id'     => absint( get_current_user_id() ),
								)
							);

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

			// Log success.
			$this->log_campaign_created( $campaign, 'direct' );

			// Trigger action for extensions.
			do_action( 'scd_campaign_created_from_data', $campaign->get_id(), $data );

			return $this->success_response( $campaign );

		} catch ( Exception $e ) {
			$this->logger->error(
				'Campaign creation from data failed',
				array(
					'error'   => $e->getMessage(),
					'user_id' => absint( get_current_user_id() ),
				)
			);

			return $this->error_response(
				__( 'Campaign creation failed. Please try again.', 'smart-cycle-discounts' ),
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
			if ( ! current_user_can( 'scd_create_campaigns' ) && ! current_user_can( 'manage_options' ) ) {
				return $this->error_response(
					__( 'You do not have permission to duplicate campaigns.', 'smart-cycle-discounts' ),
					403
				);
			}

			$original = $this->campaign_manager->find( $campaign_id );
			if ( ! $original ) {
				return $this->error_response(
					__( 'Original campaign not found.', 'smart-cycle-discounts' ),
					404
				);
			}

			$duplicate_data = $original->to_array();
			unset( $duplicate_data['id'] );
			unset( $duplicate_data['uuid'] );
			unset( $duplicate_data['created_at'] );
			unset( $duplicate_data['updated_at'] );

			/* translators: %s: original campaign name */
			$duplicate_data['name']       = sprintf( __( '%s (Copy)', 'smart-cycle-discounts' ), $original->get_name() );
			$duplicate_data['status']     = 'draft';
			$duplicate_data['created_by'] = absint( get_current_user_id() );

			$duplicate = $this->campaign_manager->create( $duplicate_data );

			if ( is_wp_error( $duplicate ) ) {
				return $this->error_response(
					$duplicate->get_error_message(),
					500
				);
			}

			// PHASE 1.1: Verify campaign limit AFTER creation (prevents race condition).
			if ( $this->feature_gate && ! $this->feature_gate->is_premium() ) {
				$repository = $this->campaign_manager->get_repository();
				if ( $repository ) {
					$final_count = $repository->count(
						array(
							'status__not' => 'deleted',
						)
					);

					$campaign_limit = $this->feature_gate->get_campaign_limit();

					if ( $final_count > $campaign_limit ) {
						// Exceeded limit - rollback by deleting the just-created campaign.
						$repository->delete( $duplicate->get_id() );

						$this->logger->warning(
							'Campaign limit exceeded after duplication, rolled back',
							array(
								'duplicate_id' => $duplicate->get_id(),
								'original_id'  => $campaign_id,
								'final_count'  => $final_count,
								'limit'        => $campaign_limit,
								'user_id'      => absint( get_current_user_id() ),
							)
						);

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

			// Log success.
			$this->log_campaign_created( $duplicate, 'duplicate', false, $campaign_id );

			// Trigger action for extensions.
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
				__( 'Campaign duplication failed. Please try again.', 'smart-cycle-discounts' ),
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
		$message = true === $is_draft
			? __( 'Campaign saved as draft successfully.', 'smart-cycle-discounts' )
			: __( 'Campaign created successfully.', 'smart-cycle-discounts' );

		$redirect_url = true === $is_draft
			? add_query_arg(
				array(
					'page'    => 'scd-campaigns',
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
			'user_id'       => absint( get_current_user_id() ),
		);

		if ( null !== $original_id ) {
			$log_data['original_campaign_id'] = $original_id;
		}

		$action = true === $is_draft ? 'Draft saved' : 'Campaign created';
		$this->logger->info( $action, $log_data );

		if ( $this->audit_logger ) {
			$event = true === $is_draft ? 'draft_saved' : 'campaign_created';
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
		// If feature gate not available, allow (fail open for safety).
		if ( ! $this->feature_gate ) {
			return true;
		}

		// Load PRO feature validator class.
		$validator_path = SCD_PLUGIN_DIR . 'includes/core/validation/class-pro-feature-validator.php';
		if ( ! file_exists( $validator_path ) ) {
			// Validator file missing - log error but allow (fail open).
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
			// Validator class missing - log error but allow (fail open).
			$this->logger->warning( 'PRO feature validator class not found' );
			return true;
		}

		$validator = new SCD_PRO_Feature_Validator( $this->feature_gate );
		$result    = $validator->validate_campaign( $campaign_data );

		// Log validation failures for security auditing.
		if ( is_wp_error( $result ) && $this->audit_logger ) {
			$error_data    = $result->get_error_data();
			$feature_name  = isset( $error_data['feature'] ) ? $error_data['feature'] : 'unknown';
			$campaign_name = isset( $campaign_data['name'] ) ? $campaign_data['name'] : 'unknown';

			$this->audit_logger->log_security_event(
				'pro_feature_violation',
				'Free user attempted to use PRO feature',
				array(
					'user_id'       => absint( get_current_user_id() ),
					'error'         => $result->get_error_message(),
					'feature'       => $feature_name,
					'campaign_name' => $campaign_name,
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
	private function update_with_retry( int $campaign_id, array $campaign_data ) {
		try {
			// First attempt.
			return $this->campaign_manager->update( $campaign_id, $campaign_data );

		} catch ( SCD_Concurrent_Modification_Exception $e ) {
			// Version conflict detected - attempt auto-recovery.
			$this->logger->info(
				'Optimistic locking conflict detected, attempting auto-retry',
				array(
					'campaign_id'      => $campaign_id,
					'expected_version' => $e->get_expected_version(),
					'current_version'  => $e->get_current_version(),
				)
			);

			// Reload fresh campaign from database to get latest version.
			$fresh_campaign = $this->campaign_manager->find( $campaign_id );
			if ( ! $fresh_campaign ) {
				return new WP_Error(
					'campaign_not_found',
					__( 'Campaign not found. It may have been deleted.', 'smart-cycle-discounts' )
				);
			}

			// Protected fields that must NEVER be overwritten by user input.
			$protected_fields = array( 'id', 'uuid', 'created_at', 'created_by', 'version' );

			$fresh_data = $fresh_campaign->to_array();

			// Merge user's changes over fresh data.
			$merged_data = array_merge( $fresh_data, $campaign_data );

			// Restore protected fields from fresh data (prevents tampering).
			foreach ( $protected_fields as $field ) {
				if ( isset( $fresh_data[ $field ] ) ) {
					$merged_data[ $field ] = $fresh_data[ $field ];
				}
			}

			// Retry update with fresh version.
			try {
				$result = $this->campaign_manager->update( $campaign_id, $merged_data );

				if ( ! is_wp_error( $result ) ) {
					$this->logger->info(
						'Auto-retry succeeded after version conflict',
						array(
							'campaign_id' => $campaign_id,
							'new_version' => $result->get_version(),
						)
					);
				}

				return $result;

			} catch ( SCD_Concurrent_Modification_Exception $retry_exception ) {
				// Second failure - genuine concurrent edit conflict.
				$this->logger->warning(
					'Auto-retry failed with another version conflict',
					array(
						'campaign_id' => $campaign_id,
					)
				);

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

	/**
	 * Calculate status for campaign update based on current status and state transition rules.
	 *
	 * Business Rules:
	 * - active with future date: Pause then schedule (workflow: active → paused → scheduled)
	 * - active with past/current date: Keep active
	 * - paused: Recalculate from dates (can become scheduled or stay paused if manually set)
	 * - scheduled: Recalculate from dates (can become active if start date passed)
	 * - draft/expired/archived: Recalculate from dates
	 *
	 * @since    1.0.0
	 * @param    string|null $current_status    Current campaign status.
	 * @param    array       $campaign_data     Campaign data with dates.
	 * @return   string                         Calculated status.
	 */
	private function calculate_status_for_update( ?string $current_status, array $campaign_data ): string {
		if ( null === $current_status || '' === $current_status ) {
			return $this->calculate_initial_status( $campaign_data );
		}

		$desired_status = $this->calculate_initial_status( $campaign_data );

		switch ( $current_status ) {
			case 'active':
				// If user sets future start date, they want to reschedule.
				// Since active → scheduled is invalid, use intermediate paused state.
				if ( 'scheduled' === $desired_status ) {
					// Return 'paused' which will then be transitioned to 'scheduled'.
					// This happens in two steps via Campaign Manager.
					return 'paused';
				}
				// If start date is past/current, keep active.
				return 'active';

			case 'paused':
				// Recalculate from dates - paused can become scheduled or active.
				return $desired_status;

			case 'scheduled':
				// Scheduled is system-managed - recalculate from dates.
				// If start date moved to past, activate (valid transition: scheduled → active).
				return $desired_status;

			case 'draft':
			case 'expired':
			case 'archived':
			default:
				// These statuses can freely transition to scheduled/active based on dates.
				return $desired_status;
		}
	}

	/**
	 * Get State Manager instance.
	 *
	 * @since    1.0.0
	 * @return   SCD_Campaign_State_Manager    State Manager instance.
	 */
	private function get_state_manager(): SCD_Campaign_State_Manager {
		return new SCD_Campaign_State_Manager( $this->logger, null );
	}

	/**
	 * Calculate initial status for new campaign based on start date.
	 *
	 * @since    1.0.0
	 * @param    array $campaign_data    Campaign data with dates.
	 * @return   string                   Initial status (scheduled or active).
	 */
	private function calculate_initial_status( array $campaign_data ): string {
		$is_future_campaign = false;

		if ( isset( $campaign_data['starts_at'] ) && ! empty( $campaign_data['starts_at'] ) ) {
			$start_timestamp = strtotime( $campaign_data['starts_at'] );
			$now_timestamp   = time();
			// Add 5-second buffer to prevent edge case timing issues.
			$buffer_seconds     = 5;
			$is_future_campaign = ( $start_timestamp > ( $now_timestamp + $buffer_seconds ) );
		}

		return true === $is_future_campaign ? 'scheduled' : 'active';
	}
}
