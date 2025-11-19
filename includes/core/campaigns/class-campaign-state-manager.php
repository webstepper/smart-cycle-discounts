<?php
/**
 * Campaign State Manager Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/campaigns/class-campaign-state-manager.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}


/**
 * Campaign State Manager Class
 *
 * Manages campaign lifecycle and status transitions.
 *
 * STATUS DEFINITIONS:
 * - draft: Campaign being configured (not live)
 * - scheduled: Campaign waiting for start date (auto-activates)
 * - active: Campaign is live and applying discounts
 * - paused: Campaign temporarily stopped (manual action, can resume easily)
 * - expired: Campaign ended (time-based or manual, requires editing to reactivate)
 * - archived: Campaign stored for records (inactive, can be restored to draft)
 *
 * KEY DIFFERENCES:
 * - Paused vs Expired: Paused is manual/temporary, Expired is time-based/permanent
 * - Deactivate action → paused status (not expired)
 * - End date reached → expired status (automatic)
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/database/models
 */
class SCD_Campaign_State_Manager {

	/**
	 * Valid campaign states.
	 *
	 * @since    1.0.0
	 * @var      array
	 */
	private const VALID_STATES = array(
		'draft',
		'active',
		'paused',
		'scheduled',
		'expired',
		'archived',
	);

	/**
	 * State transition rules.
	 *
	 * Defines allowed status transitions for campaigns with business logic validation.
	 *
	 * TRANSITION RATIONALE:
	 * - draft → active/scheduled: New campaigns go live or get scheduled
	 * - active → paused: Temporarily stop campaign (can resume)
	 * - active → expired: Campaign reaches end date
	 * - paused → active: Resume paused campaign
	 * - paused → scheduled: Reschedule paused campaign for future activation
	 * - paused → draft: Edit paused campaign before resuming (valid: allows reconfiguration)
	 * - scheduled → active: Auto-activation when start time reached
	 * - scheduled → paused: Prevent activation before start (valid: user changed mind)
	 * - scheduled → draft: Cancel scheduling and return to editing (valid: allows changes)
	 * - expired → draft: Reconfigure expired campaign for relaunch (valid: common workflow)
	 * - archived → draft: Restore archived campaign for reuse
	 *
	 * All transitions are validated by validate_transition_conditions() which checks
	 * campaign configuration (name, discount value, product selection) before allowing transition.
	 *
	 * @since    1.0.0
	 * @var      array
	 */
	private const TRANSITION_RULES = array(
		'draft'     => array( 'active', 'scheduled', 'archived' ),
		'active'    => array( 'paused', 'scheduled', 'expired', 'draft', 'archived' ),
		'paused'    => array( 'active', 'scheduled', 'draft', 'expired', 'archived' ),
		'scheduled' => array( 'active', 'paused', 'draft', 'archived' ),
		'expired'   => array( 'draft', 'archived' ),
		'archived'  => array( 'draft' ),
	);

	/**
	 * Logger instance.
	 *
	 * @since    1.0.0
	 * @var      object|null
	 */
	private $logger;

	/**
	 * Event dispatcher.
	 *
	 * @since    1.0.0
	 * @var      object|null
	 */
	private $event_dispatcher;

	/**
	 * Constructor.
	 *
	 * @since    1.0.0
	 * @param    object|null $logger             Logger instance.
	 * @param    object|null $event_dispatcher   Event dispatcher.
	 */
	public function __construct( $logger = null, $event_dispatcher = null ) {
		$this->logger           = $logger;
		$this->event_dispatcher = $event_dispatcher;
	}

	/**
	 * Check if state transition is allowed.
	 *
	 * @since    1.0.0
	 * @param    string $from_state    Current state.
	 * @param    string $to_state      Target state.
	 * @return   bool                     True if allowed.
	 */
	public function can_transition( string $from_state, string $to_state ): bool {
		if ( ! $this->is_valid_state( $from_state ) || ! $this->is_valid_state( $to_state ) ) {
			return false;
		}

		if ( $from_state === $to_state ) {
			return true; // No actual transition.
		}

		$allowed_transitions = self::TRANSITION_RULES[ $from_state ] ?? array();
		return in_array( $to_state, $allowed_transitions, true );
	}

	/**
	 * Transition campaign to new state.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign     Campaign object.
	 * @param    string       $new_state    New state.
	 * @param    array        $context      Transition context.
	 * @return   bool|WP_Error                 True on success, WP_Error on failure.
	 */
	public function transition( SCD_Campaign $campaign, string $new_state, array $context = array() ) {
		$current_state = $campaign->get_status();

		if ( ! $this->can_transition( $current_state, $new_state ) ) {
			return new WP_Error(
				'invalid_transition',
				sprintf(
					/* translators: 1: Current campaign status, 2: Target campaign status */
					__( 'Cannot transition from %1$s to %2$s', 'smart-cycle-discounts' ),
					$current_state,
					$new_state
				)
			);
		}

		// No-op if same state.
		if ( $current_state === $new_state ) {
			return true;
		}

		$validation = $this->validate_transition_conditions( $campaign, $new_state );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		// Execute pre-transition hooks.
		$this->before_transition( $campaign, $current_state, $new_state, $context );

		$campaign->set_status( $new_state );
		$campaign->set_updated_at( new DateTime( 'now', new DateTimeZone( 'UTC' ) ) );

		// System actions (auto_expired, auto_scheduled) set to NULL.
		// User actions set to current user ID.
		$is_system_action = isset( $context['reason'] ) && in_array(
			$context['reason'],
			array( 'auto_expired', 'auto_scheduled' ),
			true
		);

		if ( $is_system_action ) {
			$campaign->set_updated_by( null ); // System action.
		} else {
			$campaign->set_updated_by( get_current_user_id() ); // User action.
		}

		// Execute post-transition hooks.
		$this->after_transition( $campaign, $current_state, $new_state, $context );

		// Log transition.
		$this->log_transition( $campaign, $current_state, $new_state, $context );

		// Dispatch event.
		$this->dispatch_transition_event( $campaign, $current_state, $new_state, $context );

		return true;
	}

	/**
	 * Validate transition conditions.
	 *
	 * State Manager only validates STATUS TRANSITION RULES, not campaign data.
	 * Campaign data validation is handled by:
	 * - Wizard orchestrators (step-level validation)
	 * - Campaign Compiler (compilation validation)
	 * - Campaign Manager (final validation before save)
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign     Campaign object.
	 * @param    string       $new_state    Target state.
	 * @return   bool|WP_Error                 True if valid, WP_Error otherwise.
	 */
	private function validate_transition_conditions( SCD_Campaign $campaign, string $new_state ) {
		switch ( $new_state ) {
			case 'scheduled':
				// Only validate scheduling-specific requirements (date in future).
				return $this->validate_scheduling_date( $campaign );

			case 'expired':
				// Only validate expiration logic (no future end date).
				return $this->validate_expiration( $campaign );

			default:
				// All other transitions are allowed if the transition rule permits it.
				return true;
		}
	}

	/**
	 * Validate campaign can be scheduled (date check only).
	 *
	 * State Manager ONLY validates that the start date is in the future.
	 * Campaign data (products, discounts, etc.) is validated elsewhere.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign    Campaign object.
	 * @return   bool|WP_Error                True if valid, WP_Error otherwise.
	 */
	private function validate_scheduling_date( SCD_Campaign $campaign ) {
		$starts_at = $campaign->get_starts_at();

		if ( ! $starts_at ) {
			return new WP_Error( 'no_start_date', __( 'Start date is required for scheduling', 'smart-cycle-discounts' ) );
		}

		// Use UTC timezone to match campaign dates (which are stored in UTC).
		if ( $starts_at <= new DateTime( 'now', new DateTimeZone( 'UTC' ) ) ) {
			return new WP_Error( 'past_start_date', __( 'Start date must be in the future', 'smart-cycle-discounts' ) );
		}

		return true;
	}

	/**
	 * Validate campaign can be marked as expired.
	 *
	 * When manually marking a campaign as expired, we should verify that:
	 * 1. The campaign has an end date set, OR
	 * 2. It's a reasonable action (active/paused campaigns without end dates can be expired manually)
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign    Campaign object.
	 * @return   bool|WP_Error                True if valid, WP_Error otherwise.
	 */
	private function validate_expiration( SCD_Campaign $campaign ) {
		$ends_at = $campaign->get_ends_at();
		$now     = new DateTime( 'now', new DateTimeZone( 'UTC' ) );

		// If campaign has an end date in the future, warn user.
		if ( $ends_at && $ends_at > $now ) {
			return new WP_Error(
				'future_end_date',
				__( 'Cannot manually expire campaign with future end date. The campaign will expire automatically when the end date is reached.', 'smart-cycle-discounts' )
			);
		}

		// Allow expiring campaigns without end dates (manual expiration).
		// Allow expiring campaigns with past end dates.
		return true;
	}

	/**
	 * Execute before transition hooks.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign        Campaign object.
	 * @param    string       $from_state      Current state.
	 * @param    string       $to_state        Target state.
	 * @param    array        $context         Transition context.
	 * @return   void
	 */
	private function before_transition( SCD_Campaign $campaign, string $from_state, string $to_state, array $context ): void {
		// Generic hook.
		do_action( 'scd_before_campaign_transition', $campaign, $from_state, $to_state, $context );

		// Specific hook.
		do_action( "scd_before_campaign_{$from_state}_to_{$to_state}", $campaign, $context );
	}

	/**
	 * Execute after transition hooks.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign        Campaign object.
	 * @param    string       $from_state      Previous state.
	 * @param    string       $to_state        New state.
	 * @param    array        $context         Transition context.
	 * @return   void
	 */
	private function after_transition( SCD_Campaign $campaign, string $from_state, string $to_state, array $context ): void {
		// Note: Cache invalidation handled by Repository layer on save().

		// State-specific actions.
		switch ( $to_state ) {
			case 'active':
				// Schedule expiration check.
				$this->schedule_expiration_check( $campaign );

				// Schedule ending soon notification.
				$this->schedule_ending_notification( $campaign );

				// Fire campaign started notification hook.
				do_action( 'scd_campaign_started', $campaign->get_id() );
				break;

			case 'expired':
				// Fire campaign ended notification hook.
				do_action( 'scd_campaign_ended', $campaign->get_id() );
				break;

			case 'archived':
				// Clean up scheduled tasks.
				$this->cleanup_scheduled_tasks( $campaign );
				break;
		}

		// Generic hook.
		do_action( 'scd_after_campaign_transition', $campaign, $from_state, $to_state, $context );

		// Specific hook.
		do_action( "scd_after_campaign_{$from_state}_to_{$to_state}", $campaign, $context );
	}

	/**
	 * Log state transition.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign        Campaign object.
	 * @param    string       $from_state      Previous state.
	 * @param    string       $to_state        New state.
	 * @param    array        $context         Transition context.
	 * @return   void
	 */
	private function log_transition( SCD_Campaign $campaign, string $from_state, string $to_state, array $context ): void {
		if ( ! $this->logger ) {
			return;
		}

		$this->logger->info(
			'Campaign state transition',
			array(
				'campaign_id'   => $campaign->get_id(),
				'campaign_name' => $campaign->get_name(),
				'from_state'    => $from_state,
				'to_state'      => $to_state,
				'user_id'       => get_current_user_id(),
				'context'       => $context,
			)
		);
	}

	/**
	 * Dispatch transition event.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign        Campaign object.
	 * @param    string       $from_state      Previous state.
	 * @param    string       $to_state        New state.
	 * @param    array        $context         Transition context.
	 * @return   void
	 */
	private function dispatch_transition_event( SCD_Campaign $campaign, string $from_state, string $to_state, array $context ): void {
		if ( ! $this->event_dispatcher ) {
			return;
		}

		$event = array(
			'type'        => 'campaign.state_changed',
			'campaign_id' => $campaign->get_id(),
			'from_state'  => $from_state,
			'to_state'    => $to_state,
			'timestamp'   => time(),
			'context'     => $context,
		);

		$this->event_dispatcher->dispatch( $event );
	}

	/**
	 * Check if state is valid.
	 *
	 * @since    1.0.0
	 * @param    string $state    State to check.
	 * @return   bool                True if valid.
	 */
	public function is_valid_state( string $state ): bool {
		return in_array( $state, self::VALID_STATES, true );
	}

	/**
	 * Get all valid states.
	 *
	 * @since    1.0.0
	 * @return   array    Valid states.
	 */
	public function get_valid_states(): array {
		return self::VALID_STATES;
	}

	/**
	 * Get allowed transitions for a state.
	 *
	 * @since    1.0.0
	 * @param    string $state    Current state.
	 * @return   array               Allowed target states.
	 */
	public function get_allowed_transitions( string $state ): array {
		return self::TRANSITION_RULES[ $state ] ?? array();
	}

	/**
	 * Auto-transition campaigns based on schedule.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign    Campaign object.
	 * @return   bool                         True if transitioned.
	 */
	public function auto_transition( SCD_Campaign $campaign ): bool {
		$current_state = $campaign->get_status();
		// Use UTC timezone to match campaign dates (which are stored in UTC).
		$now = new DateTime( 'now', new DateTimeZone( 'UTC' ) );

		if ( 'scheduled' === $current_state ) {
			$starts_at = $campaign->get_starts_at();
			if ( $starts_at && $starts_at <= $now ) {
				$result = $this->transition( $campaign, 'active', array( 'reason' => 'auto_scheduled' ) );
				return ! is_wp_error( $result );
			}
		}

		if ( in_array( $current_state, array( 'active', 'paused' ), true ) ) {
			$ends_at = $campaign->get_ends_at();
			if ( $ends_at && $ends_at <= $now ) {
				$result = $this->transition( $campaign, 'expired', array( 'reason' => 'auto_expired' ) );
				return ! is_wp_error( $result );
			}
		}

		return false;
	}

	/**
	 * Schedule expiration check for campaign.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign    Campaign object.
	 * @return   void
	 */
	private function schedule_expiration_check( SCD_Campaign $campaign ): void {
		$ends_at = $campaign->get_ends_at();
		if ( ! $ends_at ) {
			return;
		}

		$hook = 'scd_check_campaign_expiration';
		$args = array( $campaign->get_id() );

		wp_clear_scheduled_hook( $hook, $args );

		// Schedule new check.
		wp_schedule_single_event( $ends_at->getTimestamp(), $hook, $args );
	}

	/**
	 * Schedule ending soon notification for campaign.
	 *
	 * Schedules a notification to be sent 24 hours before campaign ends.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign    Campaign object.
	 * @return   void
	 */
	private function schedule_ending_notification( SCD_Campaign $campaign ): void {
		$ends_at = $campaign->get_ends_at();
		if ( ! $ends_at ) {
			return;
		}

		$notification_time = clone $ends_at;
		$notification_time->modify( '-24 hours' );

		// Only schedule if notification time is in the future.
		$now = new DateTime( 'now', new DateTimeZone( 'UTC' ) );
		if ( $notification_time <= $now ) {
			return;
		}

		$hook = 'scd_campaign_ending_notification';
		$args = array( $campaign->get_id() );

		wp_clear_scheduled_hook( $hook, $args );

		// Schedule notification.
		wp_schedule_single_event( $notification_time->getTimestamp(), $hook, $args );
	}

	/**
	 * Clean up scheduled tasks for campaign.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign    Campaign object.
	 * @return   void
	 */
	private function cleanup_scheduled_tasks( SCD_Campaign $campaign ): void {
		$campaign_id = $campaign->get_id();
		if ( ! $campaign_id ) {
			return;
		}

		wp_clear_scheduled_hook( 'scd_check_campaign_expiration', array( $campaign_id ) );

		wp_clear_scheduled_hook( 'scd_campaign_ending_notification', array( $campaign_id ) );

		wp_clear_scheduled_hook( 'scd_rotate_campaign_products', array( $campaign_id ) );
	}

	/**
	 * Get state metadata.
	 *
	 * @since    1.0.0
	 * @param    string $state    State name.
	 * @return   array               State metadata.
	 */
	public function get_state_metadata( string $state ): array {
		$metadata = array(
			'draft'     => array(
				'label'       => __( 'Draft', 'smart-cycle-discounts' ),
				'description' => __( 'Campaign is being edited', 'smart-cycle-discounts' ),
				'icon'        => 'edit',
				'color'       => '#999999',
			),
			'active'    => array(
				'label'       => __( 'Active', 'smart-cycle-discounts' ),
				'description' => __( 'Campaign is running', 'smart-cycle-discounts' ),
				'icon'        => 'check',
				'color'       => '#46b450',
			),
			'paused'    => array(
				'label'       => __( 'Paused', 'smart-cycle-discounts' ),
				'description' => __( 'Campaign is temporarily stopped', 'smart-cycle-discounts' ),
				'icon'        => 'pause',
				'color'       => '#f0b849',
			),
			'scheduled' => array(
				'label'       => __( 'Scheduled', 'smart-cycle-discounts' ),
				'description' => __( 'Campaign will start automatically', 'smart-cycle-discounts' ),
				'icon'        => 'schedule',
				'color'       => '#00a0d2',
			),
			'expired'   => array(
				'label'       => __( 'Expired', 'smart-cycle-discounts' ),
				'description' => __( 'Campaign has ended', 'smart-cycle-discounts' ),
				'icon'        => 'close',
				'color'       => '#dc3232',
			),
			'archived'  => array(
				'label'       => __( 'Archived', 'smart-cycle-discounts' ),
				'description' => __( 'Campaign is archived', 'smart-cycle-discounts' ),
				'icon'        => 'archive',
				'color'       => '#666666',
			),
		);

		return $metadata[ $state ] ?? array();
	}
}
