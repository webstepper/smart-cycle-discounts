<?php
/**
 * Campaign Wizard Controller Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/campaigns/class-campaign-wizard-controller.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// Load Intent Constants for single source of truth.
require_once WSSCD_PLUGIN_DIR . 'includes/constants/class-wsscd-intent-constants.php';

/**
 * Campaign Wizard Controller Class
 *
 * @since      1.0.0
 */
class WSSCD_Campaign_Wizard_Controller extends WSSCD_Abstract_Campaign_Controller {

	/**
	 * Wizard session.
	 *
	 * @since    1.0.0
	 * @var      WSSCD_Wizard_State_Service
	 */
	private WSSCD_Wizard_State_Service $session;

	/**
	 * Feature gate service.
	 *
	 * @since    1.0.0
	 * @var      WSSCD_Feature_Gate
	 */
	private WSSCD_Feature_Gate $feature_gate;

	/**
	 * Available wizard steps (delegated to Step Registry).
	 *
	 * @since    1.0.0
	 * @deprecated Use WSSCD_Wizard_Step_Registry::get_steps() instead
	 * @var      array
	 */
	private array $steps;

	/**
	 * Initialize the controller.
	 *
	 * @since    1.0.0
	 * @param    WSSCD_Campaign_Manager         $campaign_manager     Campaign manager.
	 * @param    WSSCD_Admin_Capability_Manager $capability_manager   Capability manager.
	 * @param    WSSCD_Logger                   $logger               Logger instance.
	 * @param    WSSCD_Wizard_State_Service     $session              Wizard session.
	 * @param    WSSCD_Feature_Gate             $feature_gate         Feature gate service.
	 */
	public function __construct(
		WSSCD_Campaign_Manager $campaign_manager,
		WSSCD_Admin_Capability_Manager $capability_manager,
		WSSCD_Logger $logger,
		WSSCD_Wizard_State_Service $session,
		WSSCD_Feature_Gate $feature_gate
	) {
		parent::__construct( $campaign_manager, $capability_manager, $logger );
		$this->session      = $session;
		$this->feature_gate = $feature_gate;

		// Use Step Registry for step definitions
		$this->steps = WSSCD_Wizard_Step_Registry::get_steps();

		$this->init_wizard_components();
	}

	/**
	 * Initialize wizard components.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function init_wizard_components(): void {
		require_once WSSCD_INCLUDES_DIR . 'core/wizard/class-wizard-navigation.php';
		$navigation = new WSSCD_Wizard_Navigation();
		$navigation->init();
	}

	/**
	 * Handle wizard display.
	 *
	 * Note: The 'new' and 'edit' intents are processed early by WSSCD_Wizard_Intent_Handler
	 * on admin_init hook (before headers are sent) to ensure session cookies are properly set.
	 * By the time this method is called, those intents have already been processed and
	 * the URL has been redirected to remove the intent parameter.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function handle(): void {
		if ( ! $this->check_capability( 'wsscd_create_campaigns' ) ) {
			wp_die( esc_html__( 'You do not have permission to create campaigns.', 'smart-cycle-discounts' ) );
		}

		// Initialize session based on intent.
		//
		// Intent Processing Flow:
		// ─────────────────────────────────────────────────────────────────────
		// NEW/EDIT: Already processed by WSSCD_Wizard_Intent_Handler on admin_init.
		//           URL was redirected, intent param stripped. Session already exists.
		//           This call will get empty intent → defaults to CONTINUE.
		//
		// CONTINUE: Uses existing session (created by Intent Handler or previous visit).
		//           Creates new session only if none exists.
		//
		// DUPLICATE: Handled here (not early-processed). Creates fresh session
		//            pre-filled with source campaign data.
		// ─────────────────────────────────────────────────────────────────────
		$intent = WSSCD_Intent_Constants::get_from_request();
		if ( '' === $intent ) {
			$intent = WSSCD_Intent_Constants::DEFAULT_INTENT;
		}

		$suggestion_id = $this->get_suggestion_id();
		$schedule_mode = $this->is_schedule_mode();

		$this->session->initialize_with_intent( $intent, $suggestion_id, $schedule_mode );

		if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
			$this->handle_step_submission();
			return;
		}

		$this->handle_get_request();
	}

	/**
	 * Get suggestion ID from request.
	 *
	 * SECURITY: This method is only called from handle() after capability check.
	 * The suggestion ID is a predefined template identifier (e.g., 'clearance_sale'),
	 * not user-submitted data. It's validated against a strict alphanumeric pattern.
	 *
	 * @since    1.0.0
	 * @return   string|null    Suggestion ID or null.
	 */
	private function get_suggestion_id(): ?string {
		// Capability check - this method should only be accessible to authorized users.
		// This is also checked in handle() but we add it here for defense in depth.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return null;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only URL parameter for loading predefined suggestion templates. Validated with strict regex. Capability checked above.
		if ( ! isset( $_GET['suggestion'] ) ) {
			return null;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only URL parameter for loading predefined suggestion templates. Validated with strict regex. Capability checked above.
		$suggestion_id = sanitize_key( wp_unslash( $_GET['suggestion'] ) );

		// Strict validation: only allow lowercase alphanumeric and underscores.
		// This prevents any injection attempts as suggestion IDs are predefined constants.
		if ( ! preg_match( '/^[a-z0-9_]+$/', $suggestion_id ) ) {
			return null;
		}

		return $suggestion_id;
	}

	/**
	 * Check if this is a scheduled campaign request.
	 *
	 * SECURITY: This method is ONLY called from handle() after capability check at line 98-100.
	 * When schedule=1 is in the URL, the campaign should use future dates.
	 * Without it, the campaign uses current/immediate dates.
	 *
	 * @since    1.0.0
	 * @return   bool    True if scheduling for future, false for immediate.
	 */
	private function is_schedule_mode(): bool {
		// Defense in depth: capability check (also verified in handle() before this is called).
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only URL parameter for mode detection. Capability checked above. Value compared against literal '1'.
		return isset( $_GET['schedule'] ) && '1' === sanitize_key( wp_unslash( $_GET['schedule'] ) );
	}

	/**
	 * Handle GET request.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function handle_get_request(): void {
		$session = $this->session->get_all_data();

		if ( ! $this->is_valid_session( $session ) ) {
			$this->create_new_session();
			return;
		}

		// Edit mode data loading is now handled by Change Tracker
		// Data is loaded on-demand from database, not decomposed into session

		$requested_step = $this->get_current_step();
		$allowed_step   = $this->get_allowed_step( $requested_step );

		if ( $requested_step !== $allowed_step ) {
			// Redirect to the correct step

			// Preserve intent parameter if present
			$args   = array();
			$intent = $this->get_intent();
			if ( 'new' === $intent || 'edit' === $intent ) {
				$args['intent'] = $intent;
			}

			$this->redirect_to_wizard( $allowed_step, $args );
			return;
		}

		$this->render( $session );
	}

	/**
	 * Check if session is valid.
	 *
	 * @since    1.0.0
	 * @param    array $session    Session data.
	 * @return   bool                 Is valid.
	 */
	private function is_valid_session( array $session ): bool {
		return ! empty( $session ) && ! empty( $session['session_id'] );
	}

	/**
	 * Create new session and redirect.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function create_new_session(): void {
		try {
			$this->session->create();

			// Preserve intent parameter if present
			$args   = array();
			$intent = $this->get_intent();
			if ( 'new' === $intent || 'edit' === $intent ) {
				$args['intent'] = $intent;
			}

			$this->redirect_to_wizard( 'basic', $args );
		} catch ( Throwable $e ) {
			$this->logger->error(
				'Failed to create wizard session',
				array(
					'error' => $e->getMessage(),
				)
			);

			$this->redirect_with_message(
				admin_url( 'admin.php?page=wsscd-campaigns' ),
				__( 'Unable to start wizard session.', 'smart-cycle-discounts' ),
				'error'
			);
		}
	}

	/**
	 * Redirect to wizard step.
	 *
	 * @since    1.0.0
	 * @param    string $step      Step to redirect to.
	 * @param    array  $args      Additional query arguments.
	 * @return   void
	 */
	private function redirect_to_wizard( string $step, array $args = array() ): void {
		$defaults = array(
			'page'   => 'wsscd-campaigns',
			'action' => 'wizard',
			'step'   => $step,
		);

		$url = add_query_arg(
			array_merge( $defaults, $args ),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Handle wizard step submission.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function handle_step_submission(): void {
		if ( ! $this->verify_nonce() ) {
			wp_die( esc_html__( 'Security check failed.', 'smart-cycle-discounts' ) );
		}

		$current_step = $this->get_current_step();
		$navigation   = $this->get_navigation_direction();

		try {
			if ( 'next' === $navigation && ! $this->validate_step_data( $current_step ) ) {
				$this->handle_validation_error( $current_step );
				return;
			}

			$this->save_step_data( $current_step );

			if ( 'next' === $navigation ) {
				$this->session->mark_step_complete( $current_step );
			}

			$next_step = $this->get_next_step( $current_step, $navigation );

			if ( 'complete' === $next_step ) {
				$this->complete_wizard();
				return;
			}

			// Preserve intent parameter if present
			$args   = array( 'saved' => 1 );
			$intent = $this->get_intent();
			if ( 'new' === $intent || 'edit' === $intent ) {
				$args['intent'] = $intent;
			}

			$this->redirect_to_wizard( $next_step, $args );

		} catch ( Exception $e ) {
			$this->handle_submission_error( $e, $current_step );
		}
	}

	/**
	 * Verify nonce.
	 *
	 * @since    1.0.0
	 * @return   bool    Is valid nonce.
	 */
	private function verify_nonce(): bool {
		return wp_verify_nonce( isset( $_POST['wsscd_wizard_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['wsscd_wizard_nonce'] ) ) : '', 'wsscd_wizard_nonce' );
	}

	/**
	 * Get current step from URL.
	 *
	 * SECURITY: This method is called from handle_get_request() and render() after
	 * capability is checked in handle() at line 98-100.
	 *
	 * @since    1.0.0
	 * @return   string    Current step.
	 */
	private function get_current_step(): string {
		// Defense in depth: capability check (also verified in handle() before this is called).
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return 'basic';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only URL parameter for navigation. Capability checked above. Validated against whitelist.
		$step = isset( $_GET['step'] ) ? sanitize_key( wp_unslash( $_GET['step'] ) ) : 'basic';
		return in_array( $step, $this->steps, true ) ? $step : 'basic';
	}

	/**
	 * Get current intent from URL.
	 *
	 * Note: For NEW and EDIT intents, this will typically return empty string
	 * because the Intent Handler processes them early and redirects with the
	 * intent parameter stripped. This method is primarily useful for CONTINUE
	 * and DUPLICATE intents which are not early-processed.
	 *
	 * @since    1.0.0
	 * @return   string    Current intent or empty string.
	 */
	private function get_intent(): string {
		return WSSCD_Intent_Constants::get_from_request();
	}

	/**
	 * Get the step user is allowed to access.
	 *
	 * Users can access:
	 * 1. Any previously completed step (to go back and edit)
	 * 2. The next step after the last completed step
	 * 3. Cannot skip ahead to future steps
	 *
	 * @since    1.0.0
	 * @param    string $requested_step    Requested step.
	 * @return   string                       Allowed step.
	 */
	private function get_allowed_step( string $requested_step ): string {
		$completed_steps = $this->session->get( 'completed_steps', array() );

		// Always allow first step
		if ( 'basic' === $requested_step ) {
			return $requested_step;
		}

		// Allow if step is already completed (going back)
		if ( in_array( $requested_step, $completed_steps, true ) ) {
			return $requested_step;
		}

		$requested_index = array_search( $requested_step, $this->steps, true );
		if ( false === $requested_index || 0 === $requested_index ) {
			return 'basic';
		}

		$previous_step = $this->steps[ $requested_index - 1 ];
		if ( in_array( $previous_step, $completed_steps, true ) ) {
			// Previous step complete, allow access to this step
			return $requested_step;
		}

		// User trying to skip ahead - find the furthest allowed step
		return $this->get_furthest_allowed_step( $completed_steps );
	}

	/**
	 * Get the furthest step user can access.
	 *
	 * @since    1.0.0
	 * @param    array $completed_steps    Completed steps.
	 * @return   string                       Furthest allowed step.
	 */
	private function get_furthest_allowed_step( array $completed_steps ): string {
		if ( empty( $completed_steps ) ) {
			return 'basic';
		}

		// Find the last completed step
		$last_completed_index = -1;
		foreach ( $this->steps as $index => $step ) {
			if ( in_array( $step, $completed_steps, true ) ) {
				$last_completed_index = $index;
			}
		}

		// User can access one step beyond last completed
		$next_index = $last_completed_index + 1;
		if ( $next_index < count( $this->steps ) ) {
			return $this->steps[ $next_index ];
		}

		// All steps complete, allow review
		return 'review';
	}

	/**
	 * Get navigation direction.
	 *
	 * SECURITY: This method is ONLY called from handle_step_submission() at line 308,
	 * which verifies nonce via verify_nonce() at line 303-305 before ANY processing.
	 *
	 * @since    1.0.0
	 * @return   string    Navigation direction.
	 */
	private function get_navigation_direction(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_step_submission() line 327 via verify_nonce() before this method is called.
		return isset( $_POST['wizard_navigation'] ) ? sanitize_key( wp_unslash( $_POST['wizard_navigation'] ) ) : 'next';
	}

	/**
	 * Validate step data.
	 *
	 * SECURITY: This method is ONLY called from handle_step_submission() at line 311,
	 * which verifies nonce via verify_nonce() at line 303-305 before ANY processing.
	 *
	 * @since    1.0.0
	 * @param    string $step    Current step.
	 * @return   bool               Is valid.
	 */
	private function validate_step_data( string $step ): bool {
		$validation_context = 'wizard_' . $step;
		// Extract and sanitize only step-specific fields - not the entire $_POST array.
		// This addresses WordPress.org requirements to process only required fields.
		$step_fields       = WSSCD_Case_Converter::get_wizard_step_fields( $step );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_step_submission() line 327 via verify_nonce() before this method is called.
		$extracted_data    = WSSCD_Case_Converter::extract_and_sanitize( $step_fields, $_POST );
		$sanitized_data    = $this->sanitize_step_data( $extracted_data, $step );
		$validation_result = WSSCD_Validation::validate( $sanitized_data, $validation_context );

		if ( is_wp_error( $validation_result ) ) {
			$this->store_validation_errors( $validation_result );
			return false;
		}

		return true;
	}

	/**
	 * Store validation errors in session.
	 *
	 * @since    1.0.0
	 * @param    WP_Error $validation_result    Validation errors.
	 * @return   void
	 */
	private function store_validation_errors( WP_Error $validation_result ): void {
		$errors = array();
		foreach ( $validation_result->get_error_codes() as $code ) {
			$errors[ $code ] = $validation_result->get_error_messages( $code );
		}

		$this->session->set( 'errors', $errors );
		$this->session->save();
	}

	/**
	 * Handle validation error.
	 *
	 * @since    1.0.0
	 * @param    string $step    Current step.
	 * @return   void
	 */
	private function handle_validation_error( string $step ): void {
		// Preserve intent parameter if present
		$args   = array( 'errors' => 1 );
		$intent = $this->get_intent();
		if ( 'new' === $intent || 'edit' === $intent ) {
			$args['intent'] = $intent;
		}

		$this->redirect_to_wizard( $step, $args );
	}

	/**
	 * Save step data.
	 *
	 * SECURITY: This method is ONLY called from handle_step_submission() at line 316,
	 * which verifies nonce via verify_nonce() at line 303-305 before ANY processing.
	 * The call chain is: handle() -> handle_step_submission() [nonce verified] -> save_step_data().
	 *
	 * @since    1.0.0
	 * @param    string $step    Current step.
	 * @return   void
	 */
	private function save_step_data( string $step ): void {
		// Extract and sanitize only step-specific fields - not the entire $_POST array.
		// This addresses WordPress.org requirements to process only required fields.
		$step_fields    = WSSCD_Case_Converter::get_wizard_step_fields( $step );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_step_submission() line 327 via verify_nonce() before this method is called.
		$extracted_data = WSSCD_Case_Converter::extract_and_sanitize( $step_fields, $_POST );
		$sanitized_data = $this->sanitize_step_data( $extracted_data, $step );
		$this->session->save_step_data( $step, $sanitized_data );
		$this->session->set_draft_status( true );
		$this->session->save();
	}

	/**
	 * Handle submission error.
	 *
	 * @since    1.0.0
	 * @param    Exception $exception    Exception object.
	 * @param    string    $step         Current step.
	 * @return   void
	 */
	private function handle_submission_error( Exception $exception, string $step ): void {
		$this->logger->error(
			'Wizard step submission failed',
			array(
				'step'  => $step,
				'error' => $exception->getMessage(),
			)
		);

		$this->add_notice(
			__( 'Failed to save data. Please try again.', 'smart-cycle-discounts' ),
			'error'
		);

		$session = $this->session->get_all_data();
		$this->render( $session );
	}

	/**
	 * Complete the wizard.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function complete_wizard(): void {
		try {
			$save_as_draft = $this->should_save_as_draft();
			$creator       = $this->get_campaign_creator();
			$result        = $creator->create_from_wizard( $this->session, $save_as_draft );

			if ( ! $result['success'] ) {
				throw new Exception( $result['error'] );
			}

			$this->session->clear_session();
			wp_safe_redirect( $result['redirect_url'] );
			exit;

		} catch ( Exception $e ) {
			$this->handle_completion_error( $e );
		}
	}

	/**
	 * Check if campaign should be saved as draft.
	 *
	 * @since    1.0.0
	 * @return   bool    Save as draft.
	 */
	private function should_save_as_draft(): bool {
		$review_data = $this->session->get_step_data( 'review' );
		return isset( $review_data['launch_option'] ) && 'draft' === $review_data['launch_option'];
	}

	/**
	 * Get campaign creator service.
	 *
	 * @since    1.0.0
	 * @return   WSSCD_Campaign_Creator_Service    Campaign creator.
	 */
	private function get_campaign_creator(): WSSCD_Campaign_Creator_Service {
		require_once WSSCD_INCLUDES_DIR . 'services/class-campaign-creator-service.php';
		require_once WSSCD_INCLUDES_DIR . 'core/campaigns/class-campaign-compiler-service.php';

		$repository = $this->get_campaign_repository();
		$compiler   = new WSSCD_Campaign_Compiler_Service( $repository );

		return new WSSCD_Campaign_Creator_Service(
			$this->campaign_manager,
			$compiler,
			$this->logger,
			null,
			$this->feature_gate
		);
	}

	/**
	 * Handle completion error.
	 *
	 * @since    1.0.0
	 * @param    Exception $exception    Exception object.
	 * @return   void
	 */
	private function handle_completion_error( Exception $exception ): void {
		$this->logger->error(
			'Wizard completion failed',
			array(
				'error' => $exception->getMessage(),
			)
		);

		$this->add_notice( $exception->getMessage(), 'error' );
		$session = $this->session->get_all_data();
		$this->render( $session );
	}

	/**
	 * Get next step.
	 *
	 * @since    1.0.0
	 * @param    string $current_step    Current step.
	 * @param    string $navigation      Navigation direction.
	 * @return   string                     Next step.
	 */
	private function get_next_step( string $current_step, string $navigation ): string {
		$current_index = array_search( $current_step, $this->steps, true );

		if ( false === $current_index ) {
			return 'basic';
		}

		if ( 'prev' === $navigation && $current_index > 0 ) {
			return $this->steps[ $current_index - 1 ];
		}

		if ( 'next' === $navigation ) {
			if ( 'review' === $current_step ) {
				return 'complete';
			}

			$next_index = $current_index + 1;
			if ( $next_index < count( $this->steps ) ) {
				return $this->steps[ $next_index ];
			}
		}

		return $current_step;
	}

	/**
	 * Render wizard step.
	 *
	 * @since    1.0.0
	 * @param    array $session    Session data.
	 * @return   void
	 */
	private function render( array $session ): void {
		$current_step = $this->get_current_step();
		$step_data    = $this->get_step_data( $session, $current_step );
		$errors       = $this->session->get( 'errors', array() );

		$this->render_wizard_wrapper( $session, $current_step, $step_data, $errors );
	}

	/**
	 * Get step data from session.
	 *
	 * @since    1.0.0
	 * @param    array  $session        Session data.
	 * @param    string $current_step   Current step.
	 * @return   array                     Step data.
	 */
	private function get_step_data( array $session, string $current_step ): array {
		// Always return step data if it exists, even for fresh sessions.
		// Fresh sessions from calculator/suggestion imports have prefilled data
		// that should be displayed. The is_fresh flag is for JS sessionStorage
		// clearing, not for ignoring PHP-side prefilled data.
		$step_data = $session['steps'][ $current_step ] ?? array();

		return $step_data;
	}

	/**
	 * Check if session is fresh.
	 *
	 * Checks multiple indicators:
	 * 1. Session has no steps (empty session)
	 * 2. Session has no ID (invalid session)
	 * 3. is_fresh flag in session data
	 * 4. State Service's is_fresh() (uses request-level cache for race condition safety)
	 *
	 * @since    1.0.0
	 * @param    array $session    Session data.
	 * @return   bool                 Is fresh session.
	 */
	private function is_fresh_session( array $session ): bool {
		// Check session data directly.
		if ( empty( $session['steps'] ) || empty( $session['session_id'] ) || ! empty( $session['is_fresh'] ) ) {
			return true;
		}

		// Also check State Service's cached value (handles race condition with Asset Localizer).
		return $this->session->is_fresh();
	}

	/**
	 * Render wizard wrapper.
	 *
	 * @since    1.0.0
	 * @param    array  $session        Session data.
	 * @param    string $current_step   Current step.
	 * @param    array  $step_data      Step data.
	 * @param    array  $errors         Validation errors.
	 * @return   void
	 */
	private function render_wizard_wrapper( array $session, string $current_step, array $step_data, array $errors ): void {
		?>
		<div class="wrap wsscd-wizard-wrap wsscd-wizard-page">
			<?php $this->render_progress_bar( $current_step ); ?>
			<?php $this->render_errors( $errors ); ?>
			
			<form method="post" class="wsscd-wizard-form" autocomplete="off">
				<?php wp_nonce_field( 'wsscd_wizard_nonce', 'wsscd_wizard_nonce' ); ?>
				
				<div class="wsscd-wizard-content wsscd-wizard-layout" data-step="<?php echo esc_attr( $current_step ); ?>">
					<?php $this->render_step( $current_step, $step_data ); ?>
				</div>
				
				<?php $this->render_wizard_navigation( $current_step ); ?>
				<?php $this->render_session_info_script( $session ); ?>
			</form>

			<?php $this->render_pro_feature_modal(); ?>

			<?php
			// Completion loading overlay (shown before completion modal)
			if ( class_exists( 'WSSCD_Loader_Helper' ) ) {
				$is_edit_mode = ! empty( $session['campaign_id'] );
				$loading_text = $is_edit_mode ? __( 'Updating campaign...', 'smart-cycle-discounts' ) : __( 'Creating campaign...', 'smart-cycle-discounts' );
				WSSCD_Loader_Helper::render_fullscreen( 'wsscd-wizard-completion-loading', $loading_text, false );
			}
			?>
		</div>
		<?php
	}

	/**
	 * Render wizard header.
	 *
	 * @since    1.0.0
	 * @param    array $session    Session data.
	 * @return   void
	 */
	private function render_wizard_header( array $session ): void {
		$is_edit_mode  = ! empty( $session['campaign_id'] );
		$campaign_name = ! empty( $session['basic']['campaign_name'] ) ? $session['basic']['campaign_name'] : '';

		?>
		<div class="wsscd-wizard-header">
			<?php if ( $is_edit_mode ) : ?>
				<h1>
					<?php esc_html_e( 'Edit Campaign', 'smart-cycle-discounts' ); ?>
					<?php if ( $campaign_name ) : ?>
						<span class="wsscd-campaign-name">: <?php echo esc_html( $campaign_name ); ?></span>
					<?php endif; ?>
				</h1>
			<?php else : ?>
				<h1><?php esc_html_e( 'Create New Campaign', 'smart-cycle-discounts' ); ?></h1>
			<?php endif; ?>

			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wsscd-campaigns' ) ); ?>"
				class="button wsscd-exit-wizard">
				<?php
				WSSCD_Icon_Helper::render( 'close', array( 'size' => 16 ) );
				?>
				<span><?php esc_html_e( 'Exit Wizard', 'smart-cycle-discounts' ); ?></span>
			</a>
		</div>
		<?php
	}

	/**
	 * Render errors.
	 *
	 * @since    1.0.0
	 * @param    array $errors    Validation errors.
	 * @return   void
	 */
	private function render_errors( array $errors ): void {
		if ( empty( $errors ) ) {
			return;
		}

		?>
		<div class="notice notice-error">
			<ul>
				<?php foreach ( $errors as $field => $messages ) : ?>
					<?php foreach ( $messages as $message ) : ?>
						<li><?php echo esc_html( $message ); ?></li>
					<?php endforeach; ?>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
	}

	/**
	 * Render wizard navigation.
	 *
	 * @since    1.0.0
	 * @param    string $current_step    Current step.
	 * @return   void
	 */
	private function render_wizard_navigation( string $current_step ): void {
		$current_index = array_search( $current_step, $this->steps, true );
		$progress      = ( ( $current_index + 1 ) / count( $this->steps ) ) * 100;

		// Use wp_add_inline_style for WordPress.org compliance
		$css = '.wsscd-wizard-navigation { --progress: ' . esc_attr( $progress ) . '%; }';
		wp_add_inline_style( 'wsscd-wizard-navigation', $css );

		do_action( 'wsscd_wizard_render_navigation', $current_step );
	}

	/**
	 * Render session info script.
	 *
	 * @since    1.0.0
	 * @param    array $session    Session data.
	 * @return   void
	 */
	private function render_session_info_script( array $session ): void {
		// Get PRO status for client-side feature gating
		$is_premium  = $this->feature_gate ? $this->feature_gate->is_premium() : false;
		$upgrade_url = $this->feature_gate ? $this->feature_gate->get_upgrade_url() : admin_url( 'admin.php?page=smart-cycle-discounts-pricing' );

		// Build session info data
		$session_info = array(
			'isFresh'   => $this->is_fresh_session( $session ),
			'hasSteps'  => ! empty( $session['steps'] ),
			'sessionId' => substr( $session['session_id'] ?? '', 0, 8 ) . '...',
			'intent'    => $this->get_intent(),
		);

		$config = array(
			'is_premium'  => $is_premium,
			'upgrade_url' => $upgrade_url,
		);

		// Build the script
		$script = 'window.wsscdWizardSessionInfo = ' . wp_json_encode( $session_info ) . ';' .
			'window.wsscdWizardConfig = ' . wp_json_encode( $config ) . ';';

		// Add storage cleanup script for fresh sessions
		if ( $this->is_fresh_session( $session ) ) {
			$script .= '(function($) {' .
				'"use strict";' .
				'["sessionStorage", "localStorage"].forEach(function(storageType) {' .
				'if (window[storageType]) {' .
				'var keysToRemove = [];' .
				'for (var i = 0; i < window[storageType].length; i++) {' .
				'var key = window[storageType].key(i);' .
				'if (key && (key.indexOf("wsscd_") === 0 || key.indexOf("wizard") !== -1)) {' .
				'keysToRemove.push(key);' .
				'}' .
				'}' .
				'keysToRemove.forEach(function(key) { window[storageType].removeItem(key); });' .
				'}' .
				'});' .
				'})(jQuery);';
		}

		// Use wp_add_inline_script for WordPress.org compliance
		wp_add_inline_script( 'jquery-core', $script );
	}

	/**
	 * Render PRO feature modal.
	 *
	 * Displays modal dialog when free users attempt to use PRO features.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function render_pro_feature_modal(): void {
		$feature_gate = $this->feature_gate;
		include WSSCD_PLUGIN_DIR . 'resources/views/admin/partials/pro-feature-modal.php';
	}

	/**
	 * Render progress bar.
	 *
	 * @since    1.0.0
	 * @param    string $current_step    Current step.
	 * @return   void
	 */
	private function render_progress_bar( string $current_step ): void {
		$step_labels   = $this->get_step_labels();
		$current_index = array_search( $current_step, $this->steps, true );

		$session_data  = $this->session->get_all_data();
		$is_edit_mode  = ! empty( $session_data['campaign_id'] );
		$campaign_name = ! empty( $session_data['basic']['campaign_name'] ) ? $session_data['basic']['campaign_name'] : '';

		?>
		<div class="wsscd-wizard-progress">
			<!-- Wizard Header Content -->
			<div class="wsscd-wizard-progress-header">
				<div class="wsscd-wizard-progress-title">
					<?php if ( $is_edit_mode ) : ?>
						<h1>
							<?php esc_html_e( 'Edit Campaign', 'smart-cycle-discounts' ); ?>
							<?php if ( $campaign_name ) : ?>
								<span class="wsscd-campaign-name">: <?php echo esc_html( $campaign_name ); ?></span>
							<?php endif; ?>
						</h1>
					<?php else : ?>
						<h1><?php esc_html_e( 'Create New Campaign', 'smart-cycle-discounts' ); ?></h1>
					<?php endif; ?>

					<?php
					// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only URL parameter for display. Capability verified in handle() before render. Value compared to literal '1'.
					$is_saved = isset( $_GET['saved'] ) && '1' === sanitize_key( wp_unslash( $_GET['saved'] ) );
					if ( $is_saved ) :
						?>
						<?php
												echo wp_kses_post( WSSCD_Badge_Helper::health_badge( 'healthy', __( 'Saved', 'smart-cycle-discounts' ) ) );
						?>
					<?php endif; ?>
				</div>

				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wsscd-campaigns' ) ); ?>"
					class="button wsscd-exit-wizard">
					<?php
					WSSCD_Icon_Helper::render( 'close', array( 'size' => 16 ) );
					?>
					<span><?php esc_html_e( 'Exit Wizard', 'smart-cycle-discounts' ); ?></span>
				</a>
			</div>

			<!-- Step Progress Indicators -->
			<ul class="wsscd-wizard-steps">
				<?php $this->render_progress_steps( $step_labels, $current_step, $current_index ); ?>
			</ul>
		</div>
		<?php
	}

	/**
	 * Render progress steps.
	 *
	 * @since    1.0.0
	 * @param    array  $step_labels     Step labels.
	 * @param    string $current_step    Current step.
	 * @param    int    $current_index   Current step index.
	 * @return   void
	 */
	private function render_progress_steps( array $step_labels, string $current_step, int $current_index ): void {
		$step_number = 1;

		foreach ( $step_labels as $step => $label ) :
			$classes = $this->get_step_classes( $step, $current_step, $current_index );
			?>
			<li class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
				data-step="<?php echo esc_attr( $step_number ); ?>"
				data-step-name="<?php echo esc_attr( $step ); ?>"
				style="cursor: pointer;">
				<span class="step-label"><?php echo esc_html( $label ); ?></span>
			</li>
			<?php
			++$step_number;
		endforeach;
	}

	/**
	 * Get step classes.
	 *
	 * @since    1.0.0
	 * @param    string $step            Step identifier.
	 * @param    string $current_step    Current step.
	 * @param    int    $current_index   Current step index.
	 * @return   array                      Step classes.
	 */
	private function get_step_classes( string $step, string $current_step, int $current_index ): array {
		$classes = array();

		if ( $step === $current_step ) {
			$classes[] = 'active';
		}

		$completed_steps = $this->session->get( 'completed_steps', array() );
		if ( in_array( $step, $completed_steps, true ) ) {
			$classes[] = 'completed';
		}

		return $classes;
	}

	/**
	 * Get step labels.
	 *
	 * @since    1.0.0
	 * @return   array    Step labels (already translated).
	 */
	private function get_step_labels(): array {
		// Delegate to Step Registry (returns translated labels)
		return WSSCD_Wizard_Step_Registry::get_step_labels();
	}

	/**
	 * Render wizard step.
	 *
	 * @since    1.0.0
	 * @param    string $step         Current step.
	 * @param    array  $step_data    Step data.
	 * @return   void
	 */
	private function render_step( string $step, array $step_data ): void {
		$this->load_template_wrapper();

		if ( 'review' === $step ) {
			$step_data = $this->get_review_step_data();
		}

		$this->include_step_template( $step, $step_data );
	}

	/**
	 * Load template wrapper.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function load_template_wrapper(): void {
		$wrapper_file = WSSCD_PLUGIN_DIR . 'resources/views/admin/wizard/template-wrapper.php';
		if ( file_exists( $wrapper_file ) ) {
			require_once $wrapper_file;
		}
	}

	/**
	 * Get review step data.
	 *
	 * @since    1.0.0
	 * @return   array    Review step data.
	 */
	private function get_review_step_data(): array {
		$all_session_data = $this->session->get_all_data();
		return $all_session_data['steps'] ?? array();
	}

	/**
	 * Include step template.
	 *
	 * @since    1.0.0
	 * @param    string $step           Current step.
	 * @param    array  $step_data      Step data.
	 * @return   void
	 */
	private function include_step_template( string $step, array $step_data ): void {
		$view_file         = WSSCD_PLUGIN_DIR . 'resources/views/admin/wizard/step-' . $step . '.php';
		$validation_errors = $this->session->get( 'errors', array() );
		$feature_gate      = $this->feature_gate;

		if ( file_exists( $view_file ) ) {
			include $view_file;
		} else {
			echo '<p>' . esc_html__( 'Step template not found.', 'smart-cycle-discounts' ) . '</p>';
		}
	}

	/**
	 * Sanitize step data.
	 *
	 * @since    1.0.0
	 * @param    array  $data    Raw POST data.
	 * @param    string $step    Current step.
	 * @return   array              Sanitized data.
	 */
	private function sanitize_step_data( array $data, string $step ): array {
		unset( $data['wsscd_wizard_nonce'], $data['wizard_navigation'] );
		return WSSCD_Validation::sanitize_step_data( $data, $step );
	}

	/**
	 * Get campaign repository instance.
	 *
	 * @since    1.0.0
	 * @return   WSSCD_Campaign_Repository    Campaign repository.
	 */
	private function get_campaign_repository(): WSSCD_Campaign_Repository {
		if ( ! class_exists( 'WSSCD_Campaign_Repository' ) ) {
			throw new Exception( 'Campaign repository not available' );
		}

		$db_manager    = new WSSCD_Database_Manager();
		$cache_manager = new WSSCD_Cache_Manager();

		return new WSSCD_Campaign_Repository( $db_manager, $cache_manager );
	}
}