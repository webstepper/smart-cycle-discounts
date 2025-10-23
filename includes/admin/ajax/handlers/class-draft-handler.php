<?php
/**
 * Draft Handler
 *
 * Consolidated handler for all draft-related AJAX operations
 *
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


// Load required AJAX response class
if ( ! class_exists( 'SCD_Ajax_Response' ) ) {
	require_once dirname( dirname( __FILE__ ) ) . '/class-scd-ajax-response.php';
}

/**
 * Draft Handler Class
 *
 * Handles all draft campaign operations.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Draft_Handler {

	/**
	 * State service instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Wizard_State_Service|null    $state_service    State service.
	 */
	private $state_service;

	/**
	 * Campaign manager instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Campaign_Manager|null    $campaign_manager    Campaign manager.
	 */
	private $campaign_manager;

	/**
	 * Campaign compiler instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Campaign_Compiler_Service|null    $compiler    Campaign compiler.
	 */
	private $compiler;

	/**
	 * Logger instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Logger    $logger    Logger.
	 */
	private $logger;

	/**
	 * Audit logger instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Audit_Logger|null    $audit_logger    Audit logger.
	 */
	private $audit_logger;

	/**
	 * Feature gate instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Feature_Gate|null    $feature_gate    Feature gate.
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
	 * @param    SCD_Wizard_State_Service|null           $state_service       State service.
	 * @param    SCD_Campaign_Manager|null               $campaign_manager    Campaign manager.
	 * @param    SCD_Campaign_Compiler_Service|null      $compiler           Campaign compiler.
	 * @param    SCD_Logger                              $logger             Logger.
	 * @param    SCD_Audit_Logger|null                   $audit_logger       Audit logger.
	 * @param    SCD_Feature_Gate|null                   $feature_gate       Feature gate.
	 */
	public function __construct( $state_service, $campaign_manager, $compiler, $logger, $audit_logger, $feature_gate = null ) {
		$this->state_service = $state_service;
		$this->campaign_manager = $campaign_manager;
		$this->compiler = $compiler;
		$this->logger = $logger;
		$this->audit_logger = $audit_logger;
		$this->feature_gate = $feature_gate;
	}

	/**
	 * Handle the AJAX request.
	 *
	 * @since    1.0.0
	 * @param    array    $request    Request data (with snake_case keys from router).
	 * @return   void
	 */
	public function handle( $request = array() ) {
		try {
			// Use request data from router if available, otherwise fallback to $_POST
			// Router converts camelCase to snake_case, so we need to use that data
			$data = ! empty( $request ) ? $request : $_POST;

			// Debug logging
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[Draft_Handler] handle() called with request data: ' . print_r( $data, true ) );
			}

			// Validate the request using centralized validation
			$validation_result = SCD_Validation::validate( $data, 'ajax_action' );
			
			if ( is_wp_error( $validation_result ) ) {
				SCD_Ajax_Response::error( 
					$validation_result->get_error_message(),
					'validation_failed',
					array( 'errors' => SCD_Validation::extract_error_codes( $validation_result ) )
				);
				return;
			}
			
			// Store validated data for use in other methods
			$this->validated_data = $validation_result;
			
			// Check if this is a clear session request (from modal)
			if ( isset( $this->validated_data['scd_action'] ) && 'clear_wizard_session' === $this->validated_data['scd_action'] ) {
				$this->handle_clear_session();
				return;
			}
			
			// Get sub-action
			$sub_action = isset( $this->validated_data['draft_action'] ) ? 
						 $this->validated_data['draft_action'] : 'default';

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[Draft_Handler] sub_action: ' . $sub_action );
			}

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
					// Explicitly handle complete action
					$this->handle_complete_wizard();
					break;

				default:
					// Fallback to complete wizard for backward compatibility
					$this->handle_complete_wizard();
			}

		} catch ( Exception $e ) {
			$this->logger->error( 'Draft handler error', array(
				'error' => $e->getMessage(),
				'user_id' => get_current_user_id()
			) );
			
			SCD_Ajax_Response::error( __( 'An error occurred. Please try again.', 'smart-cycle-discounts' ) );
		}
	}

	/**
	 * Handle completing wizard (with optional save as draft).
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function handle_complete_wizard() {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[Draft_Handler] handle_complete_wizard() called' );
		}

		// Verify services are available
		if ( ! $this->state_service ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[Draft_Handler] ERROR: state_service is null' );
			}
			SCD_Ajax_Response::error( __( 'Service unavailable. Please try again.', 'smart-cycle-discounts' ) );
			return;
		}

		if ( ! $this->campaign_manager ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[Draft_Handler] ERROR: campaign_manager is null' );
			}
			SCD_Ajax_Response::error( __( 'Campaign manager service unavailable. Please try again.', 'smart-cycle-discounts' ) );
			return;
		}

		// Check for save as draft option from validated data
		// Explicitly convert to boolean to ensure proper type (even though validation should handle this)
		$save_as_draft = isset( $this->validated_data['save_as_draft'] ) ?
						(bool) $this->validated_data['save_as_draft'] : false;

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[Draft_Handler] save_as_draft: ' . ( $save_as_draft ? 'true' : 'false' ) . ' (type: ' . gettype( $save_as_draft ) . ')' );
		}

		// CRITICAL: Ensure compiler is initialized before creating Campaign_Creator_Service
		// Campaign_Creator_Service uses strict types and requires non-null compiler
		if ( ! $this->compiler ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[Draft_Handler] Initializing compiler' );
			}

			if ( ! class_exists( 'SCD_Campaign_Compiler_Service' ) ) {
				require_once SCD_INCLUDES_DIR . 'core/campaigns/class-campaign-compiler-service.php';
			}

			try {
				$repository = $this->get_campaign_repository();
				$this->compiler = new SCD_Campaign_Compiler_Service( $repository );
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( '[Draft_Handler] Compiler initialized successfully' );
				}
			} catch ( Exception $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( '[Draft_Handler] Failed to create compiler: ' . $e->getMessage() );
					error_log( '[Draft_Handler] Compiler exception trace: ' . $e->getTraceAsString() );
				}
				SCD_Ajax_Response::error( __( 'Failed to initialize campaign compiler. Please try again.', 'smart-cycle-discounts' ) );
				return;
			}
		}

		// Use the centralized campaign creator service
		if ( ! class_exists( 'SCD_Campaign_Creator_Service' ) ) {
			require_once SCD_INCLUDES_DIR . 'services/class-campaign-creator-service.php';
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[Draft_Handler] Creating Campaign_Creator_Service' );
		}

		try {
			$creator = new SCD_Campaign_Creator_Service(
				$this->campaign_manager,
				$this->compiler,
				$this->logger,
				$this->audit_logger,
				$this->feature_gate
			);

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[Draft_Handler] Calling create_from_wizard()' );
			}

			$result = $creator->create_from_wizard( $this->state_service, $save_as_draft );

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[Draft_Handler] create_from_wizard() returned: ' . print_r( $result, true ) );
			}

			if ( $result['success'] ) {
				SCD_Ajax_Response::success( $result );
			} else {
				SCD_Ajax_Response::error( $result['error'], isset( $result['code'] ) ? $result['code'] : 500 );
			}
		} catch ( Exception $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[Draft_Handler] EXCEPTION in handle_complete_wizard: ' . $e->getMessage() );
				error_log( '[Draft_Handler] Exception trace: ' . $e->getTraceAsString() );
			}
			$this->logger->error( 'Exception in handle_complete_wizard', array(
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString(),
				'user_id' => get_current_user_id()
			) );
			SCD_Ajax_Response::error( __( 'Campaign creation failed. Please check the error log.', 'smart-cycle-discounts' ) );
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
			SCD_Ajax_Response::error( __( 'Service unavailable. Please try again.', 'smart-cycle-discounts' ) );
			return;
		}

		if ( ! $this->campaign_manager ) {
			SCD_Ajax_Response::error( __( 'Campaign manager service unavailable. Please try again.', 'smart-cycle-discounts' ) );
			return;
		}

		// CRITICAL: Ensure compiler is initialized before creating Campaign_Creator_Service
		// Campaign_Creator_Service uses strict types and requires non-null compiler
		if ( ! $this->compiler ) {
			if ( ! class_exists( 'SCD_Campaign_Compiler_Service' ) ) {
				require_once SCD_INCLUDES_DIR . 'core/campaigns/class-campaign-compiler-service.php';
			}

			try {
				$repository = $this->get_campaign_repository();
				$this->compiler = new SCD_Campaign_Compiler_Service( $repository );
			} catch ( Exception $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( '[Draft_Handler] Failed to create compiler: ' . $e->getMessage() );
				}
				SCD_Ajax_Response::error( __( 'Failed to initialize campaign compiler. Please try again.', 'smart-cycle-discounts' ) );
				return;
			}
		}

		// Use the centralized campaign creator service
		if ( ! class_exists( 'SCD_Campaign_Creator_Service' ) ) {
			require_once SCD_INCLUDES_DIR . 'services/class-campaign-creator-service.php';
		}

		$creator = new SCD_Campaign_Creator_Service(
			$this->campaign_manager,
			$this->compiler,
			$this->logger,
			$this->audit_logger,
			$this->feature_gate
		);

		$result = $creator->create_from_wizard( $this->state_service, true );

		if ( $result['success'] ) {
			SCD_Ajax_Response::success( $result );
		} else {
			SCD_Ajax_Response::error( $result['error'], isset( $result['code'] ) ? $result['code'] : 500 );
		}
	}

	/**
	 * Handle deleting a draft.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function handle_delete_draft() {
		// Get draft type and ID from validated data
		$draft_type = isset( $this->validated_data['draft_type'] ) ? 
					 $this->validated_data['draft_type'] : 'campaign';
		$draft_id = isset( $this->validated_data['draft_id'] ) ? 
				   $this->validated_data['draft_id'] : '';

		if ( empty( $draft_id ) ) {
			SCD_Ajax_Response::error( __( 'No draft specified.', 'smart-cycle-discounts' ) );
			return;
		}

		// Handle based on type
		if ( $draft_type === 'session' ) {
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
			SCD_Ajax_Response::error( __( 'Service unavailable.', 'smart-cycle-discounts' ) );
			return;
		}

		try {
			// Clear the wizard session
			$this->state_service->clear_session();
			
			// Log the action
			$this->logger->info( 'Wizard session cleared for new campaign', array(
				'user_id' => get_current_user_id()
			) );
			
			SCD_Ajax_Response::success( array(
				'message' => __( 'Session cleared successfully.', 'smart-cycle-discounts' ),
				'cleared' => true
			) );
			
		} catch ( Exception $e ) {
			$this->logger->error( 'Failed to clear wizard session', array(
				'error' => $e->getMessage(),
				'user_id' => get_current_user_id()
			) );
			
			SCD_Ajax_Response::error( __( 'Failed to clear session.', 'smart-cycle-discounts' ) );
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
			SCD_Ajax_Response::error( __( 'Service unavailable.', 'smart-cycle-discounts' ) );
			return;
		}

		// Get draft campaigns from database
		$args = array(
			'status' => 'draft',
			'orderby' => 'modified',
			'order' => 'DESC',
			'per_page' => 20,
			'page' => isset( $this->validated_data['page'] ) ? $this->validated_data['page'] : 1
		);

		$drafts = $this->campaign_manager->get_campaigns( $args );
		
		// Get current wizard session draft info
		$session_draft = null;
		if ( $this->state_service ) {
			$draft_info = $this->state_service->get_draft_info();
			if ( $draft_info && empty( $draft_info['is_expired'] ) ) {
				$session_draft = array(
					'id' => 'session',
					'type' => 'session',
					'name' => $draft_info['campaign_name'],
					'last_updated' => $draft_info['last_updated'],
					'progress' => $this->state_service->get_progress()
				);
			}
		}

		// Format response
		$response = array(
			'drafts' => $this->format_drafts( $drafts ),
			'session_draft' => $session_draft,
			'total' => $this->campaign_manager->get_campaigns_count( array( 'status' => 'draft' ) ),
			'per_page' => 20,
			'current_page' => $args['page']
		);

		SCD_Ajax_Response::success( $response );
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
			SCD_Ajax_Response::error( __( 'Invalid draft ID.', 'smart-cycle-discounts' ) );
			return;
		}

		// Handle session draft preview
		if ( $draft_id === 'session' && $this->state_service ) {
			$data = $this->state_service->get_all_data();
			$progress = $this->state_service->get_progress();
			
			SCD_Ajax_Response::success( array(
				'draft_type' => 'session',
				'data' => $data,
				'progress' => $progress
			) );
			return;
		}

		// Handle database draft preview
		if ( $this->campaign_manager ) {
			$campaign = $this->campaign_manager->find( intval( $draft_id ) );
			
			if ( ! $campaign || $campaign->get_status() !== 'draft' ) {
				SCD_Ajax_Response::error( __( 'Draft not found.', 'smart-cycle-discounts' ) );
				return;
			}

			SCD_Ajax_Response::success( array(
				'draft_type' => 'campaign',
				'data' => $campaign->to_array()
			) );
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
			SCD_Ajax_Response::error( __( 'Session service unavailable.', 'smart-cycle-discounts' ) );
			return;
		}

		// Get draft info before deletion
		$draft_info = $this->state_service->get_draft_info();
		$campaign_name = isset( $draft_info['campaign_name'] ) ? $draft_info['campaign_name'] : __( 'Unnamed Draft', 'smart-cycle-discounts' );

		// Clear the session
		$this->state_service->clear_session();

		// Log the action
		$this->logger->info( 'Session draft discarded', array(
			'campaign_name' => $campaign_name,
			'user_id' => get_current_user_id()
		) );

		if ( $this->audit_logger ) {
			$this->audit_logger->log_event( 'draft_session_discarded', array(
				'campaign_name' => $campaign_name,
				'user_id' => get_current_user_id()
			) );
		}

		SCD_Ajax_Response::success( array(
			'message' => sprintf(
				__( 'Draft "%s" has been discarded.', 'smart-cycle-discounts' ),
				esc_html( $campaign_name )
			)
		) );
	}

	/**
	 * Delete campaign draft.
	 *
	 * @since    1.0.0
	 * @param    int    $campaign_id    Campaign ID.
	 * @return   void
	 */
	private function delete_campaign_draft( $campaign_id ) {
		if ( ! $this->campaign_manager ) {
			SCD_Ajax_Response::error( __( 'Campaign service unavailable.', 'smart-cycle-discounts' ) );
			return;
		}

		if ( ! $campaign_id ) {
			SCD_Ajax_Response::error( __( 'Invalid campaign ID.', 'smart-cycle-discounts' ) );
			return;
		}

		// Get the campaign
		$campaign = $this->campaign_manager->find( $campaign_id );
		
		if ( ! $campaign ) {
			SCD_Ajax_Response::error( __( 'Draft campaign not found.', 'smart-cycle-discounts' ) );
			return;
		}

		// Verify it's a draft
		if ( $campaign->get_status() !== 'draft' ) {
			SCD_Ajax_Response::error( __( 'Only draft campaigns can be deleted this way.', 'smart-cycle-discounts' ) );
			return;
		}

		// Store campaign name for message
		$campaign_name = $campaign->get_name();

		// Delete the campaign
		if ( ! $this->campaign_manager->delete_campaign( $campaign_id ) ) {
			SCD_Ajax_Response::error( __( 'Failed to delete draft campaign.', 'smart-cycle-discounts' ) );
			return;
		}

		// Log the action
		$this->logger->info( 'Draft campaign deleted', array(
			'campaign_id' => $campaign_id,
			'campaign_name' => $campaign_name,
			'user_id' => get_current_user_id()
		) );

		if ( $this->audit_logger ) {
			$this->audit_logger->log_event( 'draft_campaign_deleted', array(
				'campaign_id' => $campaign_id,
				'campaign_name' => $campaign_name,
				'user_id' => get_current_user_id()
			) );
		}

		SCD_Ajax_Response::success( array(
			'message' => sprintf(
				__( 'Draft campaign "%s" has been deleted.', 'smart-cycle-discounts' ),
				esc_html( $campaign_name )
			)
		) );
	}


	/**
	 * Format drafts for response.
	 *
	 * @since    1.0.0
	 * @param    array    $drafts    Draft campaigns.
	 * @return   array              Formatted drafts.
	 */
	private function format_drafts( $drafts ) {
		$formatted = array();

		foreach ( $drafts as $draft ) {
			// Skip null drafts
			if ( ! $draft ) {
				continue;
			}
			
			$formatted[] = array(
				'id' => $draft->get_id(),
				'type' => 'database',
				'name' => $draft->get_name(),
				'description' => $draft->get_description(),
				'last_updated' => $draft->get_updated_at()->format('Y-m-d H:i:s'),
				'created_date' => $draft->get_created_at()->format('Y-m-d H:i:s'),
				'campaign_type' => 'standard',
				'discount_type' => $draft->get_discount_type(),
				'discount_value' => $draft->get_discount_value(),
				'product_count' => count( $draft->get_product_ids() ),
				'is_complete' => $this->check_draft_complete( $draft ),
				'edit_url' => add_query_arg( array(
					'page' => 'scd-campaigns',
					'action' => 'edit',
					'campaign_id' => $draft->get_id()
				), admin_url( 'admin.php' ) )
			);
		}

		return $formatted;
	}
	
	/**
	 * Check if draft campaign is complete.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign    $draft    Draft campaign.
	 * @return   bool                      True if complete.
	 */
	private function check_draft_complete( $draft ) {
		// A draft is considered complete if it has:
		// 1. Name
		// 2. Product selection (either all products or specific products/categories)
		// 3. Discount type and value
		// 4. Schedule dates (optional for drafts)
		
		if ( empty( $draft->get_name() ) ) {
			return false;
		}
		
		if ( empty( $draft->get_discount_type() ) || $draft->get_discount_value() <= 0 ) {
			return false;
		}
		
		// Check product selection
		$selection_type = $draft->get_product_selection_type();
		if ( $selection_type === 'specific' ) {
			$has_products = count( $draft->get_product_ids() ) > 0;
			$has_categories = count( $draft->get_category_ids() ) > 0;
			$has_tags = count( $draft->get_tag_ids() ) > 0;
			
			if ( ! $has_products && ! $has_categories && ! $has_tags ) {
				return false;
			}
		}
		
		return true;
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
		// Load Database Manager
		if ( ! class_exists( 'SCD_Database_Manager' ) ) {
			$db_path = SCD_INCLUDES_DIR . 'database/class-database-manager.php';
			if ( file_exists( $db_path ) ) {
				require_once $db_path;
			} else {
				throw new Exception( 'Database Manager class file not found' );
			}
		}

		// Load Cache Manager
		if ( ! class_exists( 'SCD_Cache_Manager' ) ) {
			$cache_path = SCD_INCLUDES_DIR . 'cache/class-cache-manager.php';
			if ( file_exists( $cache_path ) ) {
				require_once $cache_path;
			} else {
				throw new Exception( 'Cache Manager class file not found' );
			}
		}

		// Load Campaign Repository
		if ( ! class_exists( 'SCD_Campaign_Repository' ) ) {
			$repo_path = SCD_INCLUDES_DIR . 'database/repositories/class-campaign-repository.php';
			if ( file_exists( $repo_path ) ) {
				require_once $repo_path;
			} else {
				throw new Exception( 'Campaign Repository class file not found' );
			}
		}

		$db_manager = new SCD_Database_Manager();
		$cache_manager = new SCD_Cache_Manager();
		return new SCD_Campaign_Repository( $db_manager, $cache_manager );
	}
}