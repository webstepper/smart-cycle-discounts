<?php
/**
 * Campaign Wizard Controller
 *
 * Handles the campaign creation wizard.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/pages/campaigns
 * @since      1.0.0
 */

declare(strict_types=1);


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Campaign Wizard Controller Class
 *
 * @since      1.0.0
 */
class SCD_Campaign_Wizard_Controller extends SCD_Abstract_Campaign_Controller {

	/**
	 * Wizard session.
	 *
	 * @since    1.0.0
	 * @var      SCD_Wizard_State_Service
	 */
	private SCD_Wizard_State_Service $session;

	/**
	 * Feature gate service.
	 *
	 * @since    1.0.0
	 * @var      SCD_Feature_Gate
	 */
	private SCD_Feature_Gate $feature_gate;

	/**
	 * Available wizard steps (delegated to Step Registry).
	 *
	 * @since    1.0.0
	 * @deprecated Use SCD_Wizard_Step_Registry::get_steps() instead
	 * @var      array
	 */
	private array $steps;

	/**
	 * Initialize the controller.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign_Manager         $campaign_manager     Campaign manager.
	 * @param    SCD_Admin_Capability_Manager $capability_manager   Capability manager.
	 * @param    SCD_Logger                   $logger               Logger instance.
	 * @param    SCD_Wizard_State_Service     $session              Wizard session.
	 * @param    SCD_Feature_Gate             $feature_gate         Feature gate service.
	 */
	public function __construct(
		SCD_Campaign_Manager $campaign_manager,
		SCD_Admin_Capability_Manager $capability_manager,
		SCD_Logger $logger,
		SCD_Wizard_State_Service $session,
		SCD_Feature_Gate $feature_gate
	) {
		parent::__construct( $campaign_manager, $capability_manager, $logger );
		$this->session      = $session;
		$this->feature_gate = $feature_gate;

		// Use Step Registry for step definitions
		$this->steps = SCD_Wizard_Step_Registry::get_steps();

		$this->init_wizard_components();
	}

	/**
	 * Initialize wizard components.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function init_wizard_components(): void {
		require_once SCD_INCLUDES_DIR . 'core/wizard/class-wizard-navigation.php';
		$navigation = new SCD_Wizard_Navigation();
		$navigation->init();
	}

	/**
	 * Handle wizard display.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function handle(): void {
		if ( ! $this->check_capability( 'scd_create_campaigns' ) ) {
			wp_die( __( 'You do not have permission to create campaigns.', 'smart-cycle-discounts' ) );
		}

		// Check campaign limit for new campaigns (not for editing existing ones)
		$intent = $this->get_intent();
		if ( 'new' === $intent || 'continue' === $intent ) {
			$session_data    = $this->session->get_all_data();
			$is_new_campaign = empty( $session_data ) || ! isset( $session_data['campaign_id'] );

			if ( $is_new_campaign && ! $this->can_create_campaign() ) {
				$this->render_campaign_limit_prompt();
				return;
			}
		}

		$this->session->initialize_with_intent( $intent );

		if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
			$this->handle_step_submission();
			return;
		}

		// Initial redirect for intent=new: drop the intent parameter after session is initialized
		// The session already knows it's a new campaign from initialize_with_intent() above
		// Keeping intent=new in URL causes redirect loops and function issues
		if ( 'new' === $intent ) {
			$this->redirect_to_wizard( 'basic' );
			return;
		}

		$this->handle_get_request();
	}

	/**
	 * Get intent from request.
	 *
	 * @since    1.0.0
	 * @return   string    Intent value.
	 */
	private function get_intent(): string {
		return isset( $_GET['intent'] ) ? sanitize_text_field( $_GET['intent'] ) : 'continue';
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

		// Validate step access - prevent users from accessing steps they haven't completed
		$requested_step = $this->get_current_step();
		$allowed_step   = $this->get_allowed_step( $requested_step );

		// DEBUG: Log access control check
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$completed = $this->session->get( 'completed_steps', array() );
			error_log(
				sprintf(
					'[SCD Wizard Access Control] Requested: %s | Allowed: %s | Completed steps: %s',
					$requested_step,
					$allowed_step,
					implode( ', ', $completed )
				)
			);
		}

		if ( $requested_step !== $allowed_step ) {
			// Redirect to the correct step
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[SCD Wizard Access Control] BLOCKING access - redirecting to: ' . $allowed_step );
			}

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
				admin_url( 'admin.php?page=scd-campaigns' ),
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
			'page'   => 'scd-campaigns',
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
			wp_die( __( 'Security check failed.', 'smart-cycle-discounts' ) );
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
		return wp_verify_nonce( $_POST['scd_wizard_nonce'] ?? '', 'scd_wizard_nonce' );
	}

	/**
	 * Get current step from URL.
	 *
	 * @since    1.0.0
	 * @return   string    Current step.
	 */
	private function get_current_step(): string {
		$step = isset( $_GET['step'] ) ? sanitize_key( $_GET['step'] ) : 'basic';
		return in_array( $step, $this->steps, true ) ? $step : 'basic';
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

		// Check if previous step is completed
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
	 * @since    1.0.0
	 * @return   string    Navigation direction.
	 */
	private function get_navigation_direction(): string {
		return $_POST['wizard_navigation'] ?? 'next';
	}

	/**
	 * Validate step data.
	 *
	 * @since    1.0.0
	 * @param    string $step    Current step.
	 * @return   bool               Is valid.
	 */
	private function validate_step_data( string $step ): bool {
		$validation_context = 'wizard_' . $step;
		$validation_result  = SCD_Validation::validate( $_POST, $validation_context );

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
	 * @since    1.0.0
	 * @param    string $step    Current step.
	 * @return   void
	 */
	private function save_step_data( string $step ): void {
		$sanitized_data = $this->sanitize_step_data( $_POST, $step );
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
	 * @return   SCD_Campaign_Creator_Service    Campaign creator.
	 */
	private function get_campaign_creator(): SCD_Campaign_Creator_Service {
		require_once SCD_INCLUDES_DIR . 'services/class-campaign-creator-service.php';
		require_once SCD_INCLUDES_DIR . 'core/campaigns/class-campaign-compiler-service.php';

		$repository = $this->get_campaign_repository();
		$compiler   = new SCD_Campaign_Compiler_Service( $repository );

		return new SCD_Campaign_Creator_Service(
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
		if ( $this->is_fresh_session( $session ) ) {
			return array();
		}

		return $session['steps'][ $current_step ] ?? array();
	}

	/**
	 * Check if session is fresh.
	 *
	 * @since    1.0.0
	 * @param    array $session    Session data.
	 * @return   bool                 Is fresh session.
	 */
	private function is_fresh_session( array $session ): bool {
		return empty( $session['steps'] ) ||
				empty( $session['session_id'] ) ||
				! empty( $session['is_fresh'] );
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
		<div class="wrap scd-wizard-wrap scd-wizard-page">
			<?php $this->render_wizard_header( $session ); ?>
			<?php $this->render_progress_bar( $current_step ); ?>
			<?php $this->render_errors( $errors ); ?>
			
			<form method="post" class="scd-wizard-form" autocomplete="off">
				<?php wp_nonce_field( 'scd_wizard_nonce', 'scd_wizard_nonce' ); ?>
				
				<div class="scd-wizard-content scd-wizard-layout" data-step="<?php echo esc_attr( $current_step ); ?>">
					<?php $this->render_step( $current_step, $step_data ); ?>
				</div>
				
				<?php $this->render_wizard_navigation( $current_step ); ?>
				<?php $this->render_session_info_script( $session ); ?>
			</form>

			<?php $this->render_pro_feature_modal(); ?>
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
		<div class="scd-wizard-header">
			<?php if ( $is_edit_mode ) : ?>
				<h1>
					<?php esc_html_e( 'Edit Campaign', 'smart-cycle-discounts' ); ?>
					<?php if ( $campaign_name ) : ?>
						<span class="scd-campaign-name">: <?php echo esc_html( $campaign_name ); ?></span>
					<?php endif; ?>
				</h1>
			<?php else : ?>
				<h1><?php esc_html_e( 'Create New Campaign', 'smart-cycle-discounts' ); ?></h1>
			<?php endif; ?>

			<?php if ( $is_edit_mode ) : ?>
				<span class="scd-status-badge scd-status-edit">
					<span class="dashicons dashicons-edit"></span>
					<?php esc_html_e( 'Editing Mode', 'smart-cycle-discounts' ); ?>
				</span>
			<?php endif; ?>
			
			<?php if ( isset( $_GET['saved'] ) && '1' === $_GET['saved'] ) : ?>
				<span class="scd-status-badge scd-status-saved">
					<span class="dashicons dashicons-yes"></span>
					<?php esc_html_e( 'Saved', 'smart-cycle-discounts' ); ?>
				</span>
			<?php endif; ?>
			
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=scd-campaigns' ) ); ?>" 
				class="button scd-exit-wizard">
				<span class="dashicons dashicons-no-alt"></span>
				<?php esc_html_e( 'Exit Wizard', 'smart-cycle-discounts' ); ?>
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

		?>
		<style>
			.scd-wizard-navigation {
				--progress: <?php echo esc_attr( $progress ); ?>%;
			}
		</style>
		<?php

		do_action( 'scd_wizard_render_navigation', $current_step );
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

		?>
		<script>
			window.scdWizardSessionInfo = {
				isFresh: <?php echo $this->is_fresh_session( $session ) ? 'true' : 'false'; ?>,
				hasSteps: <?php echo ! empty( $session['steps'] ) ? 'true' : 'false'; ?>,
				sessionId: '<?php echo esc_js( substr( $session['session_id'] ?? '', 0, 8 ) ); ?>...',
				intent: '<?php echo esc_js( $_GET['intent'] ?? 'continue' ); ?>'
			};

			// Configuration for client-side feature gating
			window.scdWizardConfig = {
				is_premium: <?php echo $is_premium ? 'true' : 'false'; ?>,
				upgrade_url: '<?php echo esc_js( $upgrade_url ); ?>'
			};
			
			// Clear client storage for fresh sessions
			if ( window.scdWizardSessionInfo.isFresh ) {
				( function( $ ) {
					'use strict';
					
					// Clear all SCD-related storage
					['sessionStorage', 'localStorage'].forEach( function( storageType ) {
						if ( window[storageType] ) {
							var keysToRemove = [];
							var i;
							
							for ( i = 0; i < window[storageType].length; i++ ) {
								var key = window[storageType].key( i );
								if ( key && ( key.indexOf( 'scd_' ) === 0 || key.indexOf( 'wizard' ) !== -1 ) ) {
									keysToRemove.push( key );
								}
							}
							
							keysToRemove.forEach( function( key ) {
								window[storageType].removeItem( key );
							} );
						}
					} );
				} )( jQuery );
			}
		</script>
		<?php
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
		include SCD_PLUGIN_DIR . 'resources/views/admin/partials/pro-feature-modal.php';
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

		?>
		<div class="scd-wizard-progress">
			<ul class="scd-wizard-steps">
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

		// Check if step is marked as complete in the state service
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
	 * @return   array    Step labels.
	 */
	private function get_step_labels(): array {
		// Delegate to Step Registry
		$labels = SCD_Wizard_Step_Registry::get_step_labels();

		// Translate labels
		return array_map(
			function ( $label ) {
				return __( $label, 'smart-cycle-discounts' );
			},
			$labels
		);
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
		$wrapper_file = SCD_PLUGIN_DIR . 'resources/views/admin/wizard/template-wrapper.php';
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
		$view_file         = SCD_PLUGIN_DIR . 'resources/views/admin/wizard/step-' . $step . '.php';
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
		unset( $data['scd_wizard_nonce'], $data['wizard_navigation'] );
		return SCD_Validation::sanitize_step_data( $data, $step );
	}

	/**
	 * Get campaign repository instance.
	 *
	 * @since    1.0.0
	 * @return   SCD_Campaign_Repository    Campaign repository.
	 */
	private function get_campaign_repository(): SCD_Campaign_Repository {
		if ( ! class_exists( 'SCD_Campaign_Repository' ) ) {
			throw new Exception( 'Campaign repository not available' );
		}

		$db_manager    = new SCD_Database_Manager();
		$cache_manager = new SCD_Cache_Manager();

		return new SCD_Campaign_Repository( $db_manager, $cache_manager );
	}

	/**
	 * Check if user can create more campaigns.
	 *
	 * @since    1.0.0
	 * @return   bool    True if user can create campaigns.
	 */
	private function can_create_campaign(): bool {
		// Premium users can create unlimited campaigns
		if ( $this->feature_gate->is_premium() ) {
			return true;
		}

		// Get current campaign count (exclude deleted campaigns)
		$repository = $this->campaign_manager->get_repository();
		if ( ! $repository ) {
			// If repository not available, allow creation (fail open)
			return true;
		}

		$current_count = $repository->count(
			array(
				'status__not' => 'deleted',
			)
		);

		// Check if user can create more campaigns
		return $this->feature_gate->can_create_campaign( $current_count );
	}

	/**
	 * Render campaign limit reached prompt.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function render_campaign_limit_prompt(): void {
		$campaign_limit = $this->feature_gate->get_campaign_limit();
		$upgrade_url    = function_exists( 'scd_get_upgrade_url' ) ? scd_get_upgrade_url() : admin_url( 'admin.php?page=smart-cycle-discounts-pricing' );
		$trial_url      = function_exists( 'scd_get_trial_url' ) ? scd_get_trial_url() : $upgrade_url;

		?>
		<div class="wrap scd-campaign-limit-reached">
			<h1><?php esc_html_e( 'Campaign Limit Reached', 'smart-cycle-discounts' ); ?></h1>

			<div class="scd-upgrade-notice">
				<div class="scd-upgrade-icon">
					<span class="dashicons dashicons-info"></span>
				</div>
				<div class="scd-upgrade-content">
					<h2><?php echo esc_html( sprintf( __( 'You\'ve reached the %d campaign limit', 'smart-cycle-discounts' ), $campaign_limit ) ); ?></h2>
					<p><?php esc_html_e( 'Upgrade to Pro to create unlimited campaigns and unlock advanced features:', 'smart-cycle-discounts' ); ?></p>

					<ul class="scd-feature-list">
						<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Unlimited active campaigns', 'smart-cycle-discounts' ); ?></li>
						<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Advanced analytics and reporting', 'smart-cycle-discounts' ); ?></li>
						<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Export campaign data to CSV/JSON', 'smart-cycle-discounts' ); ?></li>
						<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Customer segmentation targeting', 'smart-cycle-discounts' ); ?></li>
						<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Priority email support', 'smart-cycle-discounts' ); ?></li>
					</ul>

					<div class="scd-upgrade-actions">
						<a href="<?php echo esc_url( $trial_url ); ?>" class="button button-primary button-hero">
							<?php esc_html_e( 'Start 14-Day Free Trial', 'smart-cycle-discounts' ); ?>
						</a>
						<a href="<?php echo esc_url( $upgrade_url ); ?>" class="button button-secondary button-hero">
							<?php esc_html_e( 'View Pricing', 'smart-cycle-discounts' ); ?>
						</a>
					</div>

					<p class="scd-upgrade-alternative">
						<?php
						printf(
							/* translators: %s: campaigns page URL */
							esc_html__( 'Or %s to free up space for a new campaign.', 'smart-cycle-discounts' ),
							'<a href="' . esc_url( admin_url( 'admin.php?page=scd-campaigns' ) ) . '">' . esc_html__( 'delete an existing campaign', 'smart-cycle-discounts' ) . '</a>'
						);
						?>
					</p>
				</div>
			</div>

			<style>
				.scd-campaign-limit-reached {
					max-width: 800px;
					margin: 40px auto;
				}
				.scd-upgrade-notice {
					background: #fff;
					border: 1px solid #c3c4c7;
					border-left: 4px solid #2271b1;
					box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04);
					padding: 30px;
					margin: 30px 0;
					display: flex;
					gap: 20px;
				}
				.scd-upgrade-icon {
					font-size: 48px;
					color: #2271b1;
					flex-shrink: 0;
				}
				.scd-upgrade-icon .dashicons {
					width: 48px;
					height: 48px;
					font-size: 48px;
				}
				.scd-upgrade-content h2 {
					margin-top: 0;
					font-size: 20px;
					font-weight: 600;
				}
				.scd-feature-list {
					list-style: none;
					padding: 0;
					margin: 20px 0;
				}
				.scd-feature-list li {
					padding: 8px 0;
					display: flex;
					align-items: center;
					gap: 8px;
				}
				.scd-feature-list .dashicons {
					color: #00a32a;
					flex-shrink: 0;
				}
				.scd-upgrade-actions {
					margin: 25px 0 15px;
					display: flex;
					gap: 10px;
					flex-wrap: wrap;
				}
				.scd-upgrade-alternative {
					font-size: 13px;
					color: #646970;
					margin-top: 15px;
				}
			</style>
		</div>
		<?php
	}
}