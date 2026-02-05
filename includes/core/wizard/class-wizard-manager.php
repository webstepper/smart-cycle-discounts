<?php
/**
 * Wizard Manager Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/wizard/class-wizard-manager.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress/plugins/smart-cycle-discounts/
 * @since      1.0.0
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Wizard Manager Class
 *
 * Orchestrates multi-step wizard flow for campaign creation.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/components
 * @author     Webstepper <contact@webstepper.io>
 */
class WSSCD_Wizard_Manager {

	/**
	 * State service manager.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Wizard_State_Service    $state_service    State service manager.
	 */
	private WSSCD_Wizard_State_Service $state_service;

	/**
	 * Security manager.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      object|null    $security    Security manager.
	 */
	private ?object $security;

	/**
	 * Campaign repository.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      object|null    $campaign_repository    Campaign repository.
	 */
	private ?object $campaign_repository;

	/**
	 * Campaign compiler instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Campaign_Compiler_Service|null    $compiler    Compiler instance.
	 */
	private ?WSSCD_Campaign_Compiler_Service $compiler = null;

	/**
	 * Wizard steps configuration.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $steps    Wizard steps.
	 */
	private array $steps = array(
		'basic'     => array(
			'title'            => 'Basic Information',
			'description'      => 'Set up campaign name, description, and basic settings',
			'template'         => 'step-basic.php',
			'required_fields'  => array( 'name' ),  // Only name is required
			'validation_rules' => array(
				'name'        => 'required|min:3|max:100',  // Use centralized min:3
				'description' => 'max:1000',  // Optional field
			),
		),
		'products'  => array(
			'title'            => 'Product Selection',
			'description'      => 'Choose which products to include in your campaign',
			'template'         => 'step-products.php',
			'required_fields'  => array( 'product_selection_type' ),
			'validation_rules' => array(
				'product_selection_type' => 'required|in:all_products,random_products,specific_products,smart_selection',
			),
		),
		'discounts' => array(
			'title'            => 'Discount Configuration',
			'description'      => 'Configure discount types, values, and rules',
			'template'         => 'step-discounts.php',
			'required_fields'  => array( 'discount_type' ),
			'validation_rules' => array(
				'discount_type' => 'required|in:percentage,fixed,bogo,tiered,bundle',
			),
		),
		'schedule'  => array(
			'title'            => 'Schedule & Rotation',
			'description'      => 'Configure campaign schedule, timing, and rotation options',
			'template'         => 'step-schedule.php',
			'required_fields'  => array(),
			'validation_rules' => array(
				// Include rotation validation rules
				'rotation_interval'       => 'numeric|min:1|max:168',
				'max_concurrent_products' => 'numeric|min:1|max:100',
			),
		),
		'review'    => array(
			'title'            => 'Review & Launch',
			'description'      => 'Review your campaign settings and launch',
			'template'         => 'step-review.php',
			'required_fields'  => array(),
			'validation_rules' => array(),
		),
	);

	/**
	 * Current step.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $current_step    Current step key.
	 */
	private string $current_step = 'basic';

	/**
	 * Navigation instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Wizard_Navigation|null    $navigation    Navigation instance.
	 */
	private ?WSSCD_Wizard_Navigation $navigation = null;

	/**
	 * Initialize the wizard manager.
	 *
	 * @since    1.0.0
	 * @param    WSSCD_Wizard_State_Service|null $state_service         State service manager.
	 * @param    object|null                   $security              Security manager.
	 * @param    object|null                   $campaign_repository   Campaign repository.
	 */
	public function __construct(
		?WSSCD_Wizard_State_Service $state_service = null,
		?object $security = null,
		?object $campaign_repository = null
	) {
		// Debug: Log wizard manager construction
		if ( function_exists( 'wsscd_debug_wizard' ) ) {
			wsscd_debug_wizard(
				'constructor',
				array(
					'state_service_provided'       => null !== $state_service,
					'security_provided'            => null !== $security,
					'campaign_repository_provided' => null !== $campaign_repository,
				)
			);
		}

		// Use provided state service or create new one
		if ( null === $state_service ) {
			require_once WSSCD_INCLUDES_DIR . 'core/wizard/class-wizard-state-service.php';
			$state_service = new WSSCD_Wizard_State_Service();
		}
		$this->state_service = $state_service;

		$this->security = $security;

		$this->load_sidebar_manager();

		// Use provided repository or create default
		if ( null === $campaign_repository ) {
			// Try to create default repository
			if ( class_exists( 'WSSCD_Campaign_Repository' ) ) {
				$db_manager          = new WSSCD_Database_Manager();
				$cache_manager       = new WSSCD_Cache_Manager();
				$campaign_repository = new WSSCD_Campaign_Repository( $db_manager, $cache_manager );
			} else {
				// Use a dummy repository
				$campaign_repository = new stdClass();
			}
		}
		$this->campaign_repository = $campaign_repository;
	}

	/**
	 * Get navigation instance.
	 *
	 * @since    1.0.0
	 * @return   WSSCD_Wizard_Navigation    Navigation instance.
	 */
	public function get_navigation(): WSSCD_Wizard_Navigation {
		if ( $this->navigation === null ) {
			require_once WSSCD_PLUGIN_DIR . 'includes/core/wizard/class-wizard-navigation.php';
			$this->navigation = new WSSCD_Wizard_Navigation( $this );
			$this->navigation->init();
		}
		return $this->navigation;
	}

	/**
	 * Load sidebar system.
	 *
	 * @since    1.0.0
	 */
	private function load_sidebar_manager(): void {
		require_once WSSCD_PLUGIN_DIR . 'includes/core/wizard/class-wizard-sidebar.php';

		WSSCD_Wizard_Sidebar::set_dependency( 'review', $this->state_service );
	}

	/**
	 * Get state service instance.
	 *
	 * @since    1.0.0
	 * @return   WSSCD_Wizard_State_Service    State service instance.
	 */
	public function get_state_service(): WSSCD_Wizard_State_Service {
		return $this->state_service;
	}

	/**
	 * Set state service instance.
	 *
	 * @since    1.0.0
	 * @param    WSSCD_Wizard_State_Service $state_service    State service instance.
	 * @return   void
	 */
	public function set_state_service( WSSCD_Wizard_State_Service $state_service ): void {
		$this->state_service = $state_service;
	}

	/**
	 * Initialize wizard.
	 *
	 * @since    1.0.0
	 * @param    array $options    Wizard options.
	 * @return   string              Wizard session ID.
	 */
	public function initialize( array $options = array() ): string {
		// Debug: Log detailed wizard initialization
		if ( function_exists( 'wsscd_debug_wizard' ) ) {
			wsscd_debug_wizard(
				'initialize',
				'init',
				array(
					'options'              => $options,
					'has_existing_session' => $this->state_service->has_session(),
					'current_user_id'      => get_current_user_id(),
					'current_step'         => $this->get_current_step(),
					'available_steps'      => array_keys( $this->steps ),
					'request_uri'          => isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '',
					'ajax_request'         => wp_doing_ajax(),
				)
			);
		}

		// Phase 3: State service handles session creation via secure cookies

		if ( $this->state_service->has_session() ) {
			try {
				$this->state_service->clear_session();

				// Debug: Log session cleared
				if ( function_exists( 'wsscd_debug_wizard' ) ) {
					wsscd_debug_wizard( 'session_cleared', array() );
				}
			} catch ( Exception $e ) {
				WSSCD_Log::warning(
					'Failed to clear existing wizard session during initialization',
					array(
						'error' => $e->getMessage(),
						'trace' => $e->getTraceAsString(),
					)
				);

				// Debug: Log session clear failure
				if ( function_exists( 'wsscd_debug_error' ) ) {
					wsscd_debug_error(
						'Failed to clear wizard session',
						$e,
						array(
							'action' => 'initialize',
						)
					);
				}

				// Continue initialization despite session clear failure
			}
		}

		$session_id = $this->state_service->create();

		// Debug: Log session created
		if ( function_exists( 'wsscd_debug_wizard' ) ) {
			wsscd_debug_wizard(
				'session_created',
				array(
					'session_id' => $session_id,
				)
			);
		}

		$initial_data = array(
			'wizard_id'  => $session_id,
			'created_at' => current_time( 'mysql' ),
			'options'    => $options,
		);

		try {
			$this->state_service->save_step_data( '_meta', $initial_data );

			// Debug: Log metadata saved
			if ( function_exists( 'wsscd_debug_persistence' ) ) {
				wsscd_debug_persistence( 'save_metadata', '_meta', $initial_data, true, 'Initial wizard metadata saved' );
			}
		} catch ( Exception $e ) {
			WSSCD_Log::error(
				'Failed to save initial wizard metadata',
				array(
					'error'        => $e->getMessage(),
					'initial_data' => $initial_data,
					'session_id'   => $session_id,
				)
			);

			// Debug: Log metadata save failure
			if ( function_exists( 'wsscd_debug_error' ) ) {
				wsscd_debug_error(
					'Failed to save wizard metadata',
					$e,
					array(
						'action'     => 'initialize',
						'session_id' => $session_id,
					)
				);
			}

			// Continue despite metadata save failure - wizard can still function
		}

		// Debug: Log wizard initialization complete
		if ( function_exists( 'wsscd_debug_wizard' ) ) {
			wsscd_debug_wizard(
				'initialize',
				'complete',
				array(
					'session_id'      => $session_id,
					'initial_data'    => $initial_data,
					'wizard_mode'     => $options['mode'] ?? 'new',
					'starting_step'   => $this->get_current_step(),
					'completed_steps' => $this->state_service->get_completed_steps(),
				)
			);
		}

		return $session_id;
	}

	/**
	 * Get current step.
	 *
	 * @since    1.0.0
	 * @return   string    Current step key.
	 */
	public function get_current_step(): string {
		$progress = $this->state_service->get_progress();
		return $progress['current_step'] ?? 'basic';
	}

	/**
	 * Set current step.
	 *
	 * @since    1.0.0
	 * @param    string $step    Step key.
	 * @return   bool              True on success.
	 */
	public function set_current_step( string $step ): bool {
		if ( ! $this->is_valid_step( $step ) ) {
			return false;
		}

		$this->current_step = $step;
		// State service manages current step internally through completed steps

		return true;
	}

	/**
	 * Process step data.
	 *
	 * @since    1.0.0
	 * @param    string $step    Step key.
	 * @param    array  $data    Step data.
	 * @return   array             Processing result.
	 */
	public function process_step( string $step, array $data ): array {
		if ( ! $this->is_valid_step( $step ) ) {
			return $this->error_response( 'Invalid step' );
		}

		// Always perform security validation
		if ( ! $this->validate_security( $data ) ) {
			return $this->error_response( 'Security validation failed' );
		}

		$step_data = $data;
		unset( $step_data['nonce'], $step_data['action'] );

		$validation_context = 'wizard_' . $step;
		$validation_result  = WSSCD_Validation::validate( $step_data, $validation_context );
		if ( is_wp_error( $validation_result ) ) {
			$errors = array();
			foreach ( $validation_result->get_error_codes() as $code ) {
				$data             = $validation_result->get_error_data( $code );
				$field            = $data['field'] ?? 'general';
				$errors[ $field ] = $validation_result->get_error_message( $code );
			}
			return $this->error_response( 'Validation failed', $errors );
		}

		$step_data = $validation_result;

		$this->save_step_data( $step, $step_data );

		// Mark step as completed
		$this->mark_step_completed( $step );

		// Determine next step
		$next_step = $this->get_next_step( $step );

		return $this->success_response(
			array(
				'step'        => $step,
				'next_step'   => $next_step,
				'progress'    => $this->get_progress(),
				'can_proceed' => $this->can_proceed_to( $next_step ),
			)
		);
	}

	/**
	 * Navigate to step.
	 *
	 * @since    1.0.0
	 * @param    string $step    Target step.
	 * @return   array             Navigation result.
	 */
	public function navigate_to_step( string $step ): array {
		if ( ! $this->is_valid_step( $step ) ) {
			return $this->error_response( 'Invalid step' );
		}

		if ( ! $this->can_navigate_to( $step ) ) {
			return $this->error_response( 'Cannot navigate to this step' );
		}

		$this->set_current_step( $step );

		return $this->success_response(
			array(
				'step'      => $step,
				'progress'  => $this->get_progress(),
				'step_data' => $this->get_step_data( $step ),
			)
		);
	}

	/**
	 * Get step data.
	 *
	 * @since    1.0.0
	 * @param    string $step    Step key.
	 * @return   array             Step data.
	 */
	public function get_step_data( string $step ): array {
		return $this->state_service->get_step_data( $step );
	}

	/**
	 * Save step data.
	 *
	 * @since    1.0.0
	 * @param    string $step    Step key.
	 * @param    array  $data    Step data.
	 * @return   void
	 */
	public function save_step_data( string $step, array $data ): void {
		try {
			$this->state_service->save_step_data( $step, $data );
		} catch ( Exception $e ) {
			WSSCD_Log::error(
				'Failed to save wizard step data',
				array(
					'step'      => $step,
					'error'     => $e->getMessage(),
					'data_keys' => array_keys( $data ),
					'trace'     => $e->getTraceAsString(),
				)
			);
			// Don't throw - allow wizard to continue functioning with degraded state persistence
		}
	}

	/**
	 * Get all campaign data.
	 *
	 * @since    1.0.0
	 * @return   array    All campaign data.
	 */
	public function get_all_campaign_data(): array {
		$all_data = array();
		$steps    = array( 'basic', 'products', 'discounts', 'schedule' );

		foreach ( $steps as $step ) {
			$step_data = $this->state_service->get_step_data( $step );
			if ( ! empty( $step_data ) ) {
				$all_data[ $step ] = $step_data;
			}
		}

		return $all_data;
	}

	/**
	 * Get wizard progress.
	 *
	 * @since    1.0.0
	 * @return   array    Progress information.
	 */
	public function get_progress(): array {
		$progress = $this->state_service->get_progress();

		$progress['progress_percentage'] = $progress['percentage'] ?? 0;
		$progress['steps_info']          = $this->get_steps_info();

		return $progress;
	}

	/**
	 * Get steps information.
	 *
	 * @since    1.0.0
	 * @return   array    Steps information.
	 */
	public function get_steps_info(): array {
		$progress        = $this->state_service->get_progress();
		$completed_steps = $progress['completed_steps'] ?? array();
		$current_step    = $progress['current_step'] ?? 'basic';
		$steps_info      = array();

		foreach ( $this->steps as $key => $step ) {
			$steps_info[ $key ] = array(
				'key'          => $key,
				'title'        => $step['title'],
				'description'  => $step['description'],
				'is_current'   => $key === $current_step,
				'is_completed' => in_array( $key, $completed_steps ),
				'can_navigate' => $this->can_navigate_to( $key ),
			);
		}

		return $steps_info;
	}

	/**
	 * Get compiler instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   WSSCD_Campaign_Compiler_Service    Compiler instance.
	 */
	private function get_compiler(): WSSCD_Campaign_Compiler_Service {
		if ( $this->compiler === null ) {
			require_once WSSCD_INCLUDES_DIR . 'core/campaigns/class-campaign-compiler-service.php';
			$this->compiler = new WSSCD_Campaign_Compiler_Service( $this->campaign_repository );
		}
		return $this->compiler;
	}

	/**
	 * Mark step as completed.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $step    Step key.
	 * @return   void
	 */
	private function mark_step_completed( string $step ): void {
		try {
			$this->state_service->mark_step_complete( $step );
		} catch ( Exception $e ) {
			WSSCD_Log::warning(
				'Failed to mark wizard step as completed',
				array(
					'step'  => $step,
					'error' => $e->getMessage(),
				)
			);
			// Step completion tracking failure doesn't prevent wizard progression
		}
	}

	/**
	 * Get next step.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $current_step    Current step.
	 * @return   string|null               Next step or null if last.
	 */
	private function get_next_step( string $current_step ): ?string {
		$step_keys     = array_keys( $this->steps );
		$current_index = array_search( $current_step, $step_keys );

		if ( false !== $current_index && $current_index < count( $step_keys ) - 1 ) {
			return $step_keys[ $current_index + 1 ];
		}

		return null;
	}

	/**
	 * Check if can proceed to step.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $step    Target step.
	 * @return   bool              True if can proceed.
	 */
	private function can_proceed_to( string $step ): bool {
		return $this->can_navigate_to( $step );
	}

	/**
	 * Check if can navigate to step.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $step    Target step.
	 * @return   bool              True if can navigate.
	 */
	private function can_navigate_to( string $step ): bool {
		if ( ! $this->is_valid_step( $step ) ) {
			return false;
		}

		$step_keys       = array_keys( $this->steps );
		$target_index    = array_search( $step, $step_keys );
		$progress        = $this->state_service->get_progress();
		$completed_steps = $progress['completed_steps'] ?? array();
		$current_step    = $progress['current_step'] ?? 'basic';
		$current_index   = array_search( $current_step, $step_keys );

		// Can always go to first step
		if ( $target_index === 0 ) {
			return true;
		}

		// Can go back to any completed step
		if ( in_array( $step, $completed_steps ) ) {
			return true;
		}

		// Can only move forward one step at a time
		// and only if the current step is completed
		if ( $target_index === $current_index + 1 ) {
			return in_array( $current_step, $completed_steps );
		}

		// Cannot skip steps
		return false;
	}

	/**
	 * Check if step is valid.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $step    Step key.
	 * @return   bool              True if valid.
	 */
	private function is_valid_step( string $step ): bool {
		return isset( $this->steps[ $step ] );
	}

	/**
	 * Check if all steps are completed.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   bool    True if all completed.
	 */
	private function all_steps_completed(): bool {
		$progress = $this->state_service->get_progress();
		return $progress['can_complete'] ?? false;
	}

	/**
	 * Create success response.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $data    Response data.
	 * @return   array            Success response.
	 */
	private function success_response( array $data = array() ): array {
		return array(
			'success' => true,
			'data'    => $data,
		);
	}

	/**
	 * Validate security for request.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $data    Request data.
	 * @return   bool              True if valid.
	 */
	private function validate_security( array $data ): bool {
		$nonce = isset( $data['nonce'] ) ? sanitize_text_field( $data['nonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, 'wsscd_wizard_nonce' ) ) {
			return false;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return false;
		}

		// Use security manager if available for additional checks
		if ( $this->security && method_exists( $this->security, 'validate_request' ) ) {
			return $this->security->validate_request( $data );
		}

		return true;
	}

	/**
	 * Create error response.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $message    Error message.
	 * @param    array  $errors     Validation errors.
	 * @return   array                 Error response.
	 */
	private function error_response( string $message, array $errors = array() ): array {
		return array(
			'success' => false,
			'message' => $message,
			'errors'  => $errors,
		);
	}
}
