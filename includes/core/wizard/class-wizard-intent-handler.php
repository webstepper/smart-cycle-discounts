<?php
/**
 * Wizard Intent Handler Class
 *
 * Handles wizard intent processing early (before headers are sent) to ensure
 * session cookies can be properly set. This is necessary because setcookie()
 * must be called before any output.
 *
 * ## Why Early Processing?
 *
 * NEW and EDIT intents require:
 * 1. Clearing existing session (if any)
 * 2. Creating new session with cookies (setcookie before output)
 * 3. Redirecting to wizard WITHOUT intent param (prevents re-processing on refresh)
 *
 * ## Flow
 *
 * ```
 * 1. User clicks "New Campaign" → URL: ?action=wizard&intent=new
 * 2. admin_init hook fires (priority 5)
 * 3. This handler: validates intent, creates session, sets cookie
 * 4. Redirects to: ?action=wizard&step=basic (intent stripped)
 * 5. Session flag is_fresh=true signals JavaScript to clear browser storage
 * ```
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/wizard
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Load Intent Constants for single source of truth.
require_once WSSCD_PLUGIN_DIR . 'includes/constants/class-wsscd-intent-constants.php';

/**
 * Wizard Intent Handler Class
 *
 * Processes wizard intents (new, edit) on admin_init hook before headers are sent.
 * This ensures session cookies are properly set and avoids the issue where
 * setcookie() fails silently when called after output has started.
 *
 * @since      1.0.0
 */
class WSSCD_Wizard_Intent_Handler {

	/**
	 * Service container.
	 *
	 * @since    1.0.0
	 * @var      WSSCD_Container
	 */
	private $container;

	/**
	 * Initialize the handler.
	 *
	 * @since    1.0.0
	 * @param    WSSCD_Container $container    Service container.
	 */
	public function __construct( $container ) {
		$this->container = $container;
	}

	/**
	 * Initialize hooks.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function init(): void {
		// Hook early in admin_init to process intents before any output.
		// Priority 5 ensures this runs before most other admin_init callbacks.
		add_action( 'admin_init', array( $this, 'process_wizard_intent' ), 5 );
	}

	/**
	 * Process wizard intent early.
	 *
	 * This method checks if we're on the wizard page with an intent that requires
	 * session initialization (new, edit). If so, it processes the intent,
	 * sets the session cookie, and redirects to remove the intent parameter.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function process_wizard_intent(): void {
		// Only process on admin pages.
		if ( ! is_admin() ) {
			return;
		}

		// Check if we're on the campaigns page with wizard action.
		if ( ! $this->is_wizard_page() ) {
			return;
		}

		// Use centralized intent retrieval.
		$intent = WSSCD_Intent_Constants::get_from_request();

		// Only process intents that require early handling.
		if ( ! WSSCD_Intent_Constants::requires_early_processing( $intent ) ) {
			return;
		}

		// Verify user capability.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		// Get wizard state service.
		$state_service = $this->get_state_service();
		if ( ! $state_service ) {
			return;
		}

		// Get additional parameters.
		$suggestion_id = $this->get_suggestion_id();
		$schedule_mode = $this->is_schedule_mode();

		// Process the intent - this clears old session and creates new one with cookie.
		// For NEW intent: sets is_fresh=true flag (consumed by Asset Localizer).
		// For EDIT intent: sets is_edit_mode=true and campaign_id.
		$state_service->initialize_with_intent( $intent, $suggestion_id, $schedule_mode );

		// Determine redirect target.
		$redirect_url = $this->build_redirect_url( $intent, $state_service );

		// Redirect to wizard without intent parameter.
		// The intent is stripped to prevent re-processing on page refresh.
		// Session flags (is_fresh, is_edit_mode) persist in PHP transient.
		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Check if current page is the wizard page.
	 *
	 * @since    1.0.0
	 * @return   bool    True if on wizard page.
	 */
	private function is_wizard_page(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- URL param for page routing only.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- URL param for action routing only.
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';

		return 'wsscd-campaigns' === $page && 'wizard' === $action;
	}

	/**
	 * Get suggestion ID from request.
	 *
	 * @since    1.0.0
	 * @return   string|null    Suggestion ID or null.
	 */
	private function get_suggestion_id(): ?string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- URL param for suggestion pre-fill.
		$suggestion = isset( $_GET['suggestion'] ) ? sanitize_key( wp_unslash( $_GET['suggestion'] ) ) : '';

		// Validate format (alphanumeric with underscores).
		if ( '' !== $suggestion && preg_match( '/^[a-z0-9_]+$/', $suggestion ) ) {
			return $suggestion;
		}

		return null;
	}

	/**
	 * Check if schedule mode is enabled.
	 *
	 * @since    1.0.0
	 * @return   bool    True if schedule mode.
	 */
	private function is_schedule_mode(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- URL param for schedule mode.
		return isset( $_GET['schedule'] ) && '1' === sanitize_key( wp_unslash( $_GET['schedule'] ) );
	}

	/**
	 * Get wizard state service from container.
	 *
	 * @since    1.0.0
	 * @return   WSSCD_Wizard_State_Service|null    State service or null on failure.
	 */
	private function get_state_service(): ?object {
		try {
			return $this->container->get( 'wizard_state_service' );
		} catch ( Exception $e ) {
			// Log error if logger is available.
			try {
				$logger = $this->container->get( 'logger' );
				$logger->error(
					'Failed to get wizard state service in intent handler',
					array( 'error' => $e->getMessage() )
				);
			} catch ( Exception $log_error ) {
				// Silently fail if logger unavailable.
				unset( $log_error );
			}
			return null;
		}
	}

	/**
	 * Build redirect URL based on intent.
	 *
	 * @since    1.0.0
	 * @param    string                      $intent         The intent being processed.
	 * @param    WSSCD_Wizard_State_Service $state_service  The state service.
	 * @return   string                                      Redirect URL.
	 */
	private function build_redirect_url( string $intent, $state_service ): string {
		// Determine target step.
		$step = 'basic';

		if ( WSSCD_Intent_Constants::NEW === $intent ) {
			// Check if pre-filled from suggestion - go to review step.
			$session_data = $state_service->get_all_data();
			if ( ! empty( $session_data['prefilled_from_suggestion'] ) ) {
				$step = 'review';
			}
		}

		// Build URL arguments.
		$args = array(
			'page'   => 'wsscd-campaigns',
			'action' => 'wizard',
			'step'   => $step,
		);

		// For edit intent, include campaign ID.
		if ( WSSCD_Intent_Constants::EDIT === $intent ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- URL param for campaign ID.
			$campaign_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
			if ( $campaign_id ) {
				$args['id'] = $campaign_id;
			}
		}

		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}
}
