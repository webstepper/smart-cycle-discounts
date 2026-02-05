<?php
/**
 * Draft Handler Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers/class-draft-handler.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress/plugins/smart-cycle-discounts/
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}


if ( ! class_exists( 'WSSCD_Ajax_Response' ) ) {
	require_once dirname( __DIR__ ) . '/class-wsscd-ajax-response.php';
}

/**
 * Draft Handler Class
 *
 * Handles all draft campaign operations.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers
 * @author     Webstepper <contact@webstepper.io>
 */
class WSSCD_Draft_Handler {

	/**
	 * State service instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Wizard_State_Service|null    $state_service    State service.
	 */
	private $state_service;

	/**
	 * Campaign manager instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Campaign_Manager|null    $campaign_manager    Campaign manager.
	 */
	private $campaign_manager;

	/**
	 * Campaign compiler instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Campaign_Compiler_Service|null    $compiler    Campaign compiler.
	 */
	private $compiler;

	/**
	 * Logger instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Logger    $logger    Logger.
	 */
	private $logger;

	/**
	 * Audit logger instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Audit_Logger|null    $audit_logger    Audit logger.
	 */
	private $audit_logger;

	/**
	 * Feature gate instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Feature_Gate|null    $feature_gate    Feature gate.
	 */
	private $feature_gate;

	/**
	 * Validated data from the request.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $validated_data    Validated request data.
	 */
	private $validated_data = array();

	/**
	 * Constructor.
	 *
	 * @since    1.0.0
	 * @param    WSSCD_Wizard_State_Service|null      $state_service       State service.
	 * @param    WSSCD_Campaign_Manager|null          $campaign_manager    Campaign manager.
	 * @param    WSSCD_Campaign_Compiler_Service|null $compiler           Campaign compiler.
	 * @param    WSSCD_Logger                         $logger             Logger.
	 * @param    WSSCD_Audit_Logger|null              $audit_logger       Audit logger.
	 * @param    WSSCD_Feature_Gate|null              $feature_gate       Feature gate.
	 */
	public function __construct( $state_service, $campaign_manager, $compiler, $logger, $audit_logger, $feature_gate = null ) {
		$this->state_service    = $state_service;
		$this->campaign_manager = $campaign_manager;
		$this->compiler         = $compiler;
		$this->logger           = $logger;
		$this->audit_logger     = $audit_logger;
		$this->feature_gate     = $feature_gate;
	}

	/**
	 * Handle the AJAX request.
	 *
	 * @since    1.0.0
	 * @param    array $request    Request data (with snake_case keys from router).
	 * @return   void
	 */
	public function handle( $request = array() ) {
		try {
			// NOTE: Draft operations are FREE (core freemium feature).
			// PRO features are protected at campaign creation level (via feature gate).

			// Request data is always provided by the AJAX router (already sanitized).
			// Router converts camelCase to snake_case and sanitizes all values.
			if ( empty( $request ) ) {
				WSSCD_Ajax_Response::error(
					__( 'Invalid request data', 'smart-cycle-discounts' ),
					'missing_request_data'
				);
				return;
			}

			$data = $request;

			$validation_result = WSSCD_Validation::validate( $data, 'ajax_action' );

			if ( is_wp_error( $validation_result ) ) {
				WSSCD_Ajax_Response::error(
					$validation_result->get_error_message(),
					'validation_failed',
					array( 'errors' => WSSCD_Validation::extract_error_codes( $validation_result ) )
				);
				return;
			}

			$this->validated_data = $validation_result;

			if ( isset( $this->validated_data['wsscd_action'] ) && 'clear_wizard_session' === $this->validated_data['wsscd_action'] ) {
				$this->handle_clear_session();
				return;
			}

			$sub_action = isset( $this->validated_data['draft_action'] ) ?
						$this->validated_data['draft_action'] : 'default';

			switch ( $sub_action ) {
				case 'save':
					$this->handle_save_draft();
					break;

				case 'delete':
					$this->handle_delete_draft();
					break;

				case 'list':
					$this->handle_list_drafts();
					break;

				case 'preview':
					$this->handle_preview_draft();
					break;

				case 'complete':
					// Explicitly handle complete action.
					$this->handle_complete_wizard();
					break;

				default:
					// Fallback to complete wizard for backward compatibility.
					$this->handle_complete_wizard();
			}
		} catch ( Exception $e ) {
			$this->logger->error(
				'Draft handler error',
				array(
					'error'   => $e->getMessage(),
					'user_id' => get_current_user_id(),
				)
			);

			WSSCD_Ajax_Response::error( __( 'An error occurred. Please try again.', 'smart-cycle-discounts' ) );
		}
	}

	/**
	 * Handle completing wizard (with optional save as draft).
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function handle_complete_wizard() {
		// Verify services are available.
		if ( ! $this->state_service ) {
			WSSCD_Ajax_Response::error( __( 'Service unavailable. Please try again.', 'smart-cycle-discounts' ) );
			return;
		}

		// CRITICAL: If campaign_data is provided in request, save it to session first.
		// This handles edit mode where sessionStorage was cleared but we have fresh data.
		if ( isset( $this->validated_data['campaign_data'] ) && is_array( $this->validated_data['campaign_data'] ) ) {
			$campaign_data = $this->validated_data['campaign_data'];
			$steps         = array( 'basic', 'products', 'discounts', 'schedule', 'review' );

			foreach ( $steps as $step ) {
				if ( isset( $campaign_data[ $step ] ) && is_array( $campaign_data[ $step ] ) ) {
					$this->state_service->set_step_data( $step, $campaign_data[ $step ] );
				}
			}
			$this->state_service->save();
		}

		// Before creating or updating a campaign, run a final cross-step validation
		// using the campaign_complete context. This ensures that campaigns cannot
		// be launched or saved as draft with incomplete or invalid wizard data,
		// even when the client sends a full campaign_data payload.
		$session_data      = $this->state_service->get_all_data();
		$data_for_validate = isset( $session_data['steps'] ) && is_array( $session_data['steps'] ) ? $session_data['steps'] : $session_data;
		$validation_result = WSSCD_Validation::validate( $data_for_validate, 'campaign_complete' );
		if ( is_wp_error( $validation_result ) ) {
			WSSCD_Ajax_Response::error(
				$validation_result->get_error_message(),
				'validation_failed',
				array( 'errors' => WSSCD_Validation::extract_error_codes( $validation_result ) )
			);
			return;
		}

		if ( ! $this->campaign_manager ) {
			WSSCD_Ajax_Response::error( __( 'Campaign manager service unavailable. Please try again.', 'smart-cycle-discounts' ) );
			return;
		}

		// Explicitly convert to boolean to ensure proper type (even though validation should handle this).
		$save_as_draft = isset( $this->validated_data['save_as_draft'] ) ?
						(bool) $this->validated_data['save_as_draft'] : false;

		// Ensure review step has launch_option for compiler (status calculation).
		// When campaign_data is not sent (e.g. Cycle AI prefilled session), session may lack review step.
		$review_data   = $this->state_service->get_step_data( 'review' );
		$launch_option = $save_as_draft ? 'draft' : 'active';
		$review_data   = is_array( $review_data ) ? $review_data : array();
		$review_data['launch_option'] = $launch_option;
		$this->state_service->set_step_data( 'review', $review_data );
		$this->state_service->save();

		// CRITICAL: Ensure compiler is initialized before creating Campaign_Creator_Service.
		// Campaign_Creator_Service uses strict types and requires non-null compiler.
		// Ensure compiler is initialized using centralized method.
		if ( ! $this->ensure_compiler_initialized() ) {
			WSSCD_Ajax_Response::error( __( 'Failed to initialize campaign compiler. Please try again.', 'smart-cycle-discounts' ) );
			return;
		}

		// Use the centralized campaign creator service.
		if ( ! class_exists( 'WSSCD_Campaign_Creator_Service' ) ) {
			require_once WSSCD_INCLUDES_DIR . 'services/class-campaign-creator-service.php';
		}

		try {
			$creator = new WSSCD_Campaign_Creator_Service(
				$this->campaign_manager,
				$this->compiler,
				$this->logger,
				$this->audit_logger,
				$this->feature_gate
			);

			$result = $creator->create_from_wizard( $this->state_service, $save_as_draft );

			if ( $result['success'] ) {
				WSSCD_Ajax_Response::success( $result );
			} else {
				WSSCD_Ajax_Response::error( $result['error'], isset( $result['code'] ) ? $result['code'] : 500 );
			}
		} catch ( Exception $e ) {
			$this->logger->error(
				'Exception in handle_complete_wizard',
				array(
					'error'   => $e->getMessage(),
					'trace'   => $e->getTraceAsString(),
					'user_id' => get_current_user_id(),
				)
			);
			$message = __( 'Campaign creation failed. Please check the error log.', 'smart-cycle-discounts' );
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && $e->getMessage() ) {
				$message = $e->getMessage();
			}
			WSSCD_Ajax_Response::error( $message, 500 );
		}
	}

	/**
	 * Handle saving wizard session as draft campaign.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function handle_save_draft() {
		if ( ! $this->state_service ) {
			WSSCD_Ajax_Response::error( __( 'Service unavailable. Please try again.', 'smart-cycle-discounts' ) );
			return;
		}

		if ( ! $this->campaign_manager ) {
			WSSCD_Ajax_Response::error( __( 'Campaign manager service unavailable. Please try again.', 'smart-cycle-discounts' ) );
			return;
		}

		// CRITICAL: Ensure compiler is initialized before creating Campaign_Creator_Service.
		// Campaign_Creator_Service uses strict types and requires non-null compiler.
		// Ensure compiler is initialized using centralized method.
		if ( ! $this->ensure_compiler_initialized() ) {
			WSSCD_Ajax_Response::error( __( 'Failed to initialize campaign compiler. Please try again.', 'smart-cycle-discounts' ) );
			return;
		}

		// Use the centralized campaign creator service.
		if ( ! class_exists( 'WSSCD_Campaign_Creator_Service' ) ) {
			require_once WSSCD_INCLUDES_DIR . 'services/class-campaign-creator-service.php';
		}

		$creator = new WSSCD_Campaign_Creator_Service(
			$this->campaign_manager,
			$this->compiler,
			$this->logger,
			$this->audit_logger,
			$this->feature_gate
		);

		$result = $creator->create_from_wizard( $this->state_service, true );

		if ( $result['success'] ) {
			WSSCD_Ajax_Response::success( $result );
		} else {
			WSSCD_Ajax_Response::error( $result['error'], isset( $result['code'] ) ? $result['code'] : 500 );
		}
	}

	/**
	 * Handle deleting a draft.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function handle_delete_draft() {
		$draft_type = isset( $this->validated_data['draft_type'] ) ?
					$this->validated_data['draft_type'] : 'campaign';
		$draft_id   = isset( $this->validated_data['draft_id'] ) ?
					$this->validated_data['draft_id'] : '';

		if ( empty( $draft_id ) ) {
			WSSCD_Ajax_Response::error( __( 'No draft specified.', 'smart-cycle-discounts' ) );
			return;
		}

		// Handle based on type.
		if ( 'session' === $draft_type ) {
			$this->delete_session_draft();
		} else {
			$this->delete_campaign_draft( intval( $draft_id ) );
		}
	}

	/**
	 * Handle clearing wizard session.
	 * Used when user chooses "Save Draft & Create New" in the modal.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function handle_clear_session() {
		if ( ! $this->state_service ) {
			WSSCD_Ajax_Response::error( __( 'Service unavailable.', 'smart-cycle-discounts' ) );
			return;
		}

		try {
			$this->state_service->clear_session();

			// Log the action.
			$this->logger->info(
				'Wizard session cleared for new campaign',
				array(
					'user_id' => get_current_user_id(),
				)
			);

			WSSCD_Ajax_Response::success(
				array(
					'message' => __( 'Session cleared successfully.', 'smart-cycle-discounts' ),
					'cleared' => true,
				)
			);

		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to clear wizard session',
				array(
					'error'   => $e->getMessage(),
					'user_id' => get_current_user_id(),
				)
			);

			WSSCD_Ajax_Response::error( __( 'Failed to clear session.', 'smart-cycle-discounts' ) );
		}
	}

	/**
	 * Handle listing drafts.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function handle_list_drafts() {
		if ( ! $this->campaign_manager ) {
			WSSCD_Ajax_Response::error( __( 'Service unavailable.', 'smart-cycle-discounts' ) );
			return;
		}

		$args = array(
			'status'   => 'draft',
			'orderby'  => 'modified',
			'order'    => 'DESC',
			'per_page' => 20,
			'page'     => isset( $this->validated_data['page'] ) ? $this->validated_data['page'] : 1,
		);

		$drafts = $this->campaign_manager->get_campaigns( $args );

		$session_draft = null;
		if ( $this->state_service ) {
			$draft_info = $this->state_service->get_draft_info();
			if ( $draft_info && empty( $draft_info['is_expired'] ) ) {
				$session_draft = array(
					'id'           => 'session',
					'type'         => 'session',
					'name'         => $draft_info['campaign_name'],
					'last_updated' => $draft_info['last_updated'],
					'progress'     => $this->state_service->get_progress(),
				);
			}
		}

		$response = array(
			'drafts'        => $this->format_drafts( $drafts ),
			'session_draft' => $session_draft,
			'total'         => $this->campaign_manager->count_campaigns( array( 'status' => 'draft' ) ),
			'per_page'      => 20,
			'current_page'  => $args['page'],
		);

		WSSCD_Ajax_Response::success( $response );
	}

	/**
	 * Handle draft preview.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function handle_preview_draft() {
		$draft_id = isset( $this->validated_data['draft_id'] ) ? $this->validated_data['draft_id'] : '';

		if ( empty( $draft_id ) ) {
			WSSCD_Ajax_Response::error( __( 'Invalid draft ID.', 'smart-cycle-discounts' ) );
			return;
		}

		// Handle session draft preview.
		if ( 'session' === $draft_id && $this->state_service ) {
			$data     = $this->state_service->get_all_data();
			$progress = $this->state_service->get_progress();

			WSSCD_Ajax_Response::success(
				array(
					'draft_type' => 'session',
					'data'       => $data,
					'progress'   => $progress,
				)
			);
			return;
		}

		// Handle database draft preview.
		if ( $this->campaign_manager ) {
			$campaign = $this->campaign_manager->find( intval( $draft_id ) );

			if ( ! $campaign || $campaign->get_status() !== 'draft' ) {
				WSSCD_Ajax_Response::error( __( 'Draft not found.', 'smart-cycle-discounts' ) );
				return;
			}

			WSSCD_Ajax_Response::success(
				array(
					'draft_type' => 'campaign',
					'data'       => $campaign->to_array(),
				)
			);
		}
	}

	/**
	 * Delete session draft.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function delete_session_draft() {
		if ( ! $this->state_service ) {
			WSSCD_Ajax_Response::error( __( 'Session service unavailable.', 'smart-cycle-discounts' ) );
			return;
		}

		$draft_info    = $this->state_service->get_draft_info();
		$campaign_name = isset( $draft_info['campaign_name'] ) ? $draft_info['campaign_name'] : __( 'Unnamed Draft', 'smart-cycle-discounts' );

		// Fire cancellation hook before clearing session data.
		$session_id = $this->state_service->get_session_id();
		if ( $session_id ) {
			/** This action is documented in includes/core/campaigns/class-campaign-list-controller.php */
			do_action( 'wsscd_wizard_session_cancelled', $session_id );
		}

		$this->state_service->clear_session();

		// Log the action.
		$this->logger->info(
			'Session draft discarded',
			array(
				'campaign_name' => $campaign_name,
				'user_id'       => get_current_user_id(),
			)
		);

		if ( $this->audit_logger ) {
			$this->audit_logger->log_event(
				'draft_session_discarded',
				array(
					'campaign_name' => $campaign_name,
					'user_id'       => get_current_user_id(),
				)
			);
		}

		WSSCD_Ajax_Response::success(
			array(
				'message' =>
					sprintf(
						/* translators: %s: name of the discarded draft campaign */
						__( 'Draft "%s" has been discarded.', 'smart-cycle-discounts' ),
						esc_html( $campaign_name )
					),
			)
		);
	}

	/**
	 * Delete campaign draft.
	 *
	 * @since    1.0.0
	 * @param    int $campaign_id    Campaign ID.
	 * @return   void
	 */
	private function delete_campaign_draft( $campaign_id ) {
		if ( ! $this->campaign_manager ) {
			WSSCD_Ajax_Response::error( __( 'Campaign service unavailable.', 'smart-cycle-discounts' ) );
			return;
		}

		if ( ! $campaign_id ) {
			WSSCD_Ajax_Response::error( __( 'Invalid campaign ID.', 'smart-cycle-discounts' ) );
			return;
		}

		$campaign = $this->campaign_manager->find( $campaign_id );

		if ( ! $campaign ) {
			WSSCD_Ajax_Response::error( __( 'Draft campaign not found.', 'smart-cycle-discounts' ) );
			return;
		}

		// Verify it is a draft.
		if ( $campaign->get_status() !== 'draft' ) {
			WSSCD_Ajax_Response::error( __( 'Only draft campaigns can be deleted this way.', 'smart-cycle-discounts' ) );
			return;
		}

		$campaign_name = $campaign->get_name();

		if ( ! $this->campaign_manager->delete_campaign( $campaign_id ) ) {
			WSSCD_Ajax_Response::error( __( 'Failed to delete draft campaign.', 'smart-cycle-discounts' ) );
			return;
		}

		// Log the action.
		$this->logger->info(
			'Draft campaign deleted',
			array(
				'campaign_id'   => $campaign_id,
				'campaign_name' => $campaign_name,
				'user_id'       => get_current_user_id(),
			)
		);

		if ( $this->audit_logger ) {
			$this->audit_logger->log_event(
				'draft_campaign_deleted',
				array(
					'campaign_id'   => $campaign_id,
					'campaign_name' => $campaign_name,
					'user_id'       => get_current_user_id(),
				)
			);
		}

		WSSCD_Ajax_Response::success(
			array(
				'message' =>
					sprintf(
						/* translators: %s: name of the deleted draft campaign */
						__( 'Draft campaign "%s" has been deleted.', 'smart-cycle-discounts' ),
						esc_html( $campaign_name )
					),
			)
		);
	}


	/**
	 * Format drafts for response.
	 *
	 * @since    1.0.0
	 * @param    array $drafts    Draft campaigns.
	 * @return   array              Formatted drafts.
	 */
	private function format_drafts( $drafts ) {
		$formatted = array();

		foreach ( $drafts as $draft ) {
			// Skip null drafts.
			if ( ! $draft ) {
				continue;
			}

			$formatted[] = array(
				'id'             => $draft->get_id(),
				'type'           => 'database',
				'name'           => $draft->get_name(),
				'description'    => $draft->get_description(),
				'last_updated'   => $draft->get_updated_at()->format( 'Y-m-d H:i:s' ),
				'created_date'   => $draft->get_created_at()->format( 'Y-m-d H:i:s' ),
				'campaign_type'  => 'standard',
				'discount_type'  => $draft->get_discount_type(),
				'discount_value' => $draft->get_discount_value(),
				'product_count'  => count( $draft->get_product_ids() ),
				'is_complete'    => $this->check_draft_complete( $draft ),
				'edit_url'       => add_query_arg(
					array(
						'page'        => 'wsscd-campaigns',
						'action'      => 'edit',
						'campaign_id' => $draft->get_id(),
					),
					admin_url( 'admin.php' )
				),
			);
		}

		return $formatted;
	}

	/**
	 * Check if draft campaign is complete.
	 *
	 * @since    1.0.0
	 * @param    WSSCD_Campaign $draft    Draft campaign.
	 * @return   bool                      True if complete.
	 */
	private function check_draft_complete( $draft ) {
		// A draft is considered complete if it has.
		// 1. Name.
		// 2. Product selection (either all products or specific products/categories).
		// 3. Discount type and value.
		// 4. Schedule dates (optional for drafts).

		if ( empty( $draft->get_name() ) ) {
			return false;
		}

		if ( empty( $draft->get_discount_type() ) || $draft->get_discount_value() <= 0 ) {
			return false;
		}

		$selection_type = $draft->get_product_selection_type();

		// For specific_products, must have at least one product selected.
		if ( WSSCD_Campaign::SELECTION_TYPE_SPECIFIC_PRODUCTS === $selection_type ) {
			if ( count( $draft->get_product_ids() ) === 0 ) {
				return false;
			}
		}
		// Pool-based selections are always valid (categories are optional filter).

		return true;
	}

	/**
	 * Ensure campaign compiler is initialized.
	 *
	 * Lazy initialization of the compiler service to avoid loading it
	 * when not needed. This method consolidates the duplicate initialization
	 * logic that was previously in handle_complete_wizard() and handle_save_draft().
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   bool True if compiler is available, false on failure.
	 */
	private function ensure_compiler_initialized(): bool {
		if ( $this->compiler ) {
			return true;
		}

		if ( ! class_exists( 'WSSCD_Campaign_Compiler_Service' ) ) {
			require_once WSSCD_INCLUDES_DIR . 'core/campaigns/class-campaign-compiler-service.php';
		}

		try {
			$repository     = $this->get_campaign_repository();
			$this->compiler = new WSSCD_Campaign_Compiler_Service( $repository );
			return true;
		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to initialize campaign compiler',
				array( 'error' => $e->getMessage() )
			);
			return false;
		}
	}

	/**
	 * Get campaign repository instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   object    Campaign repository instance.
	 * @throws   Exception If required classes not available.
	 */
	private function get_campaign_repository(): object {
		// Load Database Manager.
		if ( ! class_exists( 'WSSCD_Database_Manager' ) ) {
			$db_path = WSSCD_INCLUDES_DIR . 'database/class-database-manager.php';
			if ( file_exists( $db_path ) ) {
				require_once $db_path;
			} else {
				throw new Exception( 'Database Manager class file not found' );
			}
		}

		// Load Cache Manager.
		if ( ! class_exists( 'WSSCD_Cache_Manager' ) ) {
			$cache_path = WSSCD_INCLUDES_DIR . 'cache/class-cache-manager.php';
			if ( file_exists( $cache_path ) ) {
				require_once $cache_path;
			} else {
				throw new Exception( 'Cache Manager class file not found' );
			}
		}

		// Load Campaign Repository.
		if ( ! class_exists( 'WSSCD_Campaign_Repository' ) ) {
			$repo_path = WSSCD_INCLUDES_DIR . 'database/repositories/class-campaign-repository.php';
			if ( file_exists( $repo_path ) ) {
				require_once $repo_path;
			} else {
				throw new Exception( 'Campaign Repository class file not found' );
			}
		}

		$db_manager    = new WSSCD_Database_Manager();
		$cache_manager = new WSSCD_Cache_Manager();
		return new WSSCD_Campaign_Repository( $db_manager, $cache_manager );
	}
}
