<?php
/**
 * Wizard State Service Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/wizard/class-wizard-state-service.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress/plugins/smart-cycle-discounts/
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load Intent Constants for single source of truth.
require_once WSSCD_PLUGIN_DIR . 'includes/constants/class-wsscd-intent-constants.php';

/**
 * Wizard State Service Class
 *
 * Manages wizard session state with database-based persistence.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/wizard
 */
class WSSCD_Wizard_State_Service {

	/**
	 * Session cookie name.
	 *
	 * @since    1.0.0
	 * @var      string
	 */
	const COOKIE_NAME = 'wsscd_wizard_session';

	/**
	 * Session lifetime in seconds (2 hours).
	 *
	 * Based on last activity (updated_at), not creation time.
	 * Manual saves update session activity, keeping active sessions alive.
	 * Should be longer than JavaScript sessionTimeout (1 hour).
	 *
	 * @since    1.0.0
	 * @var      int
	 */
	const SESSION_LIFETIME = 7200;

	/**
	 * Transient key prefix.
	 *
	 * @since    1.0.0
	 * @var      string
	 */
	const TRANSIENT_PREFIX = 'wsscd_wizard_session_';

	/**
	 * Available wizard steps (delegated to Step Registry).
	 *
	 * @since    1.0.0
	 * @deprecated Use WSSCD_Wizard_Step_Registry::get_steps() instead
	 * @var      array
	 */
	private $steps;

	/**
	 * Session ID.
	 *
	 * @since    1.0.0
	 * @var      string|null
	 */
	private $session_id = null;

	/**
	 * Session data.
	 *
	 * @since    1.0.0
	 * @var      array
	 */
	private $data = array();

	/**
	 * Dirty flag.
	 *
	 * @since    1.0.0
	 * @var      bool
	 */
	private $dirty = false;

	/**
	 * Change tracker for edit mode.
	 *
	 * @since    1.0.0
	 * @var      WSSCD_Campaign_Change_Tracker|null
	 */
	private $change_tracker = null;

	/**
	 * Constructor.
	 *
	 * @since    1.0.0
	 * @param    string|null $session_id    Optional session ID to load.
	 */
	public function __construct( $session_id = null ) {
		// Use Step Registry for step definitions
		$this->steps = WSSCD_Wizard_Step_Registry::get_steps();

		$this->initialize( $session_id );
	}

	/**
	 * Initialize session.
	 *
	 * @since    1.0.0
	 * @param    string|null $session_id    Session ID to load.
	 * @return   void
	 */
	private function initialize( $session_id = null ): void {
		if ( $session_id ) {
			$this->load( $session_id );
		} else {
			$this->load_from_cookie();
		}

		// CRITICAL FIX: Auto-initialize Change Tracker for edit mode sessions
		// This ensures AJAX handlers have access to Change Tracker without needing to call initialize_with_intent()
		// The session data already contains is_edit_mode and campaign_id from the initial page load
		if ( $this->is_edit_mode() && ! $this->change_tracker ) {
			$campaign_id = $this->get( 'campaign_id', 0 );
			if ( $campaign_id ) {
				$this->initialize_change_tracker( $campaign_id );
			}
		}
	}

	/**
	 * Initialize with specific intent.
	 *
	 * Uses WSSCD_Intent_Constants for intent values (single source of truth).
	 *
	 * @since    1.0.0
	 * @param    string      $intent         Intent: 'new', 'continue', 'edit', or 'duplicate'.
	 * @param    string|null $suggestion_id  Optional suggestion ID for pre-fill.
	 * @param    bool        $schedule_mode  Whether to use future dates (true) or current dates (false).
	 * @return   void
	 */
	public function initialize_with_intent( string $intent = WSSCD_Intent_Constants::DEFAULT_INTENT, ?string $suggestion_id = null, bool $schedule_mode = false ): void {
		if ( WSSCD_Intent_Constants::NEW === $intent ) {
			$this->start_fresh_session( $suggestion_id, $schedule_mode );
		} elseif ( WSSCD_Intent_Constants::EDIT === $intent ) {
			$this->start_edit_session();
		} elseif ( WSSCD_Intent_Constants::CONTINUE === $intent && ! $this->has_session() ) {
			$this->create();
		} else {
			$this->clear_fresh_flag();
		}
	}

	/**
	 * Start edit session for existing campaign.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function start_edit_session(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading URL parameter for campaign ID, not form processing.
		$campaign_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

		// CRITICAL FIX: If no campaign ID in URL, check session (for AJAX requests)
		if ( ! $campaign_id ) {
			$campaign_id = $this->get( 'campaign_id', 0 );
		}

		if ( ! $campaign_id ) {
			// No campaign ID in URL or session, treat as new
			$this->start_fresh_session();
			return;
		}

		$existing_campaign_id = $this->get( 'campaign_id', 0 );
		$is_edit_mode         = $this->get( 'is_edit_mode', false );

		if ( $existing_campaign_id === $campaign_id && $is_edit_mode ) {
			// Already editing this campaign, keep existing session and change tracker
			$this->initialize_change_tracker( $campaign_id );
			return;
		}

		$this->clear_session();
		$this->create();

		$this->set( 'campaign_id', $campaign_id );
		$this->set( 'is_edit_mode', true );

		// Mark all steps as completed so user can navigate freely
		$this->set( 'completed_steps', $this->steps );

		$this->initialize_change_tracker( $campaign_id );

		$this->save();
	}

	/**
	 * Start fresh session.
	 *
	 * @since    1.0.0
	 * @param    string|null $suggestion_id  Optional suggestion ID for pre-fill.
	 * @param    bool        $schedule_mode  Whether to use future dates (true) or current dates (false).
	 * @return   void
	 */
	private function start_fresh_session( ?string $suggestion_id = null, bool $schedule_mode = false ): void {
		$this->clear_session();
		$this->create();
		$this->mark_as_fresh();

		// Pre-fill wizard data from campaign suggestion.
		if ( $suggestion_id ) {
			$this->prefill_from_suggestion( $suggestion_id, $schedule_mode );
		}
	}

	/**
	 * Mark session as fresh.
	 *
	 * Sets the is_fresh flag which signals JavaScript to clear browser sessionStorage.
	 * This flag is consumed (cleared) after first read by Asset Localizer.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function mark_as_fresh(): void {
		$this->data[ WSSCD_Intent_Constants::FLAG_IS_FRESH ] = true;
		$this->dirty = true;
		$this->save();
	}

	/**
	 * Request-level cache for is_fresh flag.
	 *
	 * This ensures multiple components (Asset Localizer, Wizard Controller) can
	 * all read the same value during a single request, even after flag is consumed.
	 *
	 * @since    1.0.0
	 * @var      bool|null
	 */
	private static $is_fresh_cache = null;

	/**
	 * Consume the fresh flag (read and clear).
	 *
	 * This implements the one-time signal pattern:
	 * - Returns true if session is fresh (first read after NEW intent)
	 * - Clears the flag from transient so subsequent REQUESTS return false
	 * - Caches value for current request so multiple reads work correctly
	 * - Prevents data loss on page refresh
	 *
	 * Called by Asset Localizer to pass isFresh to JavaScript.
	 *
	 * @since    1.0.0
	 * @return   bool True if session was fresh (first read only).
	 */
	public function consume_fresh_flag(): bool {
		// Return cached value if already determined this request.
		if ( null !== self::$is_fresh_cache ) {
			return self::$is_fresh_cache;
		}

		$is_fresh = $this->get( WSSCD_Intent_Constants::FLAG_IS_FRESH, false );

		// Cache the value for this request.
		self::$is_fresh_cache = (bool) $is_fresh;

		if ( $is_fresh ) {
			// Clear the flag from transient - next REQUEST won't see it.
			unset( $this->data[ WSSCD_Intent_Constants::FLAG_IS_FRESH ] );
			$this->dirty = true;
			$this->save();
		}

		return self::$is_fresh_cache;
	}

	/**
	 * Check if session is fresh (non-consuming read).
	 *
	 * Uses request-level cache to ensure consistent value across all components.
	 * This should be used by Wizard Controller to check fresh status.
	 *
	 * @since    1.0.0
	 * @return   bool True if session is fresh.
	 */
	public function is_fresh(): bool {
		// If cache is set, use it (flag was already consumed this request).
		if ( null !== self::$is_fresh_cache ) {
			return self::$is_fresh_cache;
		}

		// Otherwise read from session data (not consuming).
		return (bool) $this->get( WSSCD_Intent_Constants::FLAG_IS_FRESH, false );
	}

	/**
	 * Clear fresh flag (internal use).
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function clear_fresh_flag(): void {
		if ( isset( $this->data[ WSSCD_Intent_Constants::FLAG_IS_FRESH ] ) ) {
			unset( $this->data[ WSSCD_Intent_Constants::FLAG_IS_FRESH ] );
			$this->dirty = true;
			$this->save();
		}
	}

	/**
	 * Pre-fill wizard data from campaign suggestion.
	 *
	 * Supports both major events (from Campaign Suggestions Registry)
	 * and weekly campaigns (from Weekly Campaign Definitions).
	 *
	 * @since    1.0.0
	 * @param    string $suggestion_id  Suggestion event ID.
	 * @param    bool   $schedule_mode  Whether to use future dates (true) or current/immediate dates (false).
	 * @return   void
	 */
	private function prefill_from_suggestion( string $suggestion_id, bool $schedule_mode = false ): void {
		$event = null;

		// First, try to get from Campaign Suggestions Registry (major events).
		require_once WSSCD_INCLUDES_DIR . 'core/campaigns/class-campaign-suggestions-registry.php';
		$event = WSSCD_Campaign_Suggestions_Registry::get_event_by_id( $suggestion_id );

		// If not found, try Weekly Campaign Definitions.
		if ( ! $event ) {
			require_once WSSCD_INCLUDES_DIR . 'core/campaigns/class-weekly-campaign-definitions.php';
			$event = WSSCD_Weekly_Campaign_Definitions::get_by_id( $suggestion_id );
		}

		if ( ! $event ) {
			return;
		}

		$this->set( 'from_suggestion', $suggestion_id );

		// Pre-fill basic step data.
		if ( ! isset( $this->data['steps'] ) ) {
			$this->data['steps'] = array();
		}

		if ( ! isset( $this->data['steps']['basic'] ) ) {
			$this->data['steps']['basic'] = array();
		}

		// Determine if this is a weekly campaign or major event.
		$is_weekly_campaign = isset( $event['schedule'] ) && isset( $event['schedule']['start_day'] );

		if ( $is_weekly_campaign ) {
			// Weekly campaign - use simple name (no date range needed as it's recurring).
			$this->data['steps']['basic']['name'] = $event['name'];

			// Weekly campaigns use recurring_weekly category, map to priority 3 (normal).
			$this->data['steps']['basic']['priority'] = 3;
		} else {
			// Major event - include specific date range.
			$start_month = wp_date( 'M', $event['calculated_start_date'] );
			$start_day   = wp_date( 'j', $event['calculated_start_date'] );
			$end_month   = wp_date( 'M', $event['calculated_end_date'] );
			$end_day     = wp_date( 'j', $event['calculated_end_date'] );
			$year        = wp_date( 'Y', $event['calculated_start_date'] );

			// Format: "Weekend Sale Nov 1-3, 2025" or "Valentine's Day Feb 7-14, 2025".
			if ( $start_month === $end_month ) {
				$date_range = sprintf( '%s %d-%d, %s', $start_month, $start_day, $end_day, $year );
			} else {
				$date_range = sprintf( '%s %d - %s %d, %s', $start_month, $start_day, $end_month, $end_day, $year );
			}

			$this->data['steps']['basic']['name'] = $event['name'] . ' ' . $date_range;

			// Priority based on event category.
			$priority_map                             = array(
				'major'    => 4,
				'seasonal' => 3,
				'ongoing'  => 3,
				'flexible' => 2,
			);
			$this->data['steps']['basic']['priority'] = (int) ( $priority_map[ $event['category'] ] ?? 3 );
		}

		// Pre-fill schedule step data.
		if ( ! isset( $this->data['steps']['schedule'] ) ) {
			$this->data['steps']['schedule'] = array();
		}

		if ( $is_weekly_campaign ) {
			$this->prefill_weekly_campaign_schedule( $event, $schedule_mode );
		} else {
			// Major event - use pre-calculated dates.
			$this->data['steps']['schedule']['start_type'] = 'scheduled';
			$this->data['steps']['schedule']['start_date'] = wp_date( 'Y-m-d', $event['calculated_start_date'] );
			$this->data['steps']['schedule']['end_date']   = wp_date( 'Y-m-d', $event['calculated_end_date'] );
			$this->data['steps']['schedule']['start_time'] = '00:00';
			$this->data['steps']['schedule']['end_time']   = '23:59';
		}

		// Pre-fill products step data with default (all products).
		// This allows the Review step to display properly.
		if ( ! isset( $this->data['steps']['products'] ) ) {
			$this->data['steps']['products'] = array();
		}
		$this->data['steps']['products']['product_selection_type'] = 'all_products';
		$this->data['steps']['products']['selected_product_ids']   = array();

		// Pre-fill discounts step data if available.
		if ( isset( $event['suggested_discount']['optimal'] ) ) {
			if ( ! isset( $this->data['steps']['discounts'] ) ) {
				$this->data['steps']['discounts'] = array();
			}

			$this->data['steps']['discounts']['discount_type'] = 'percentage';
			// Cast to float for proper type - Campaign class requires float for discount_value.
			$this->data['steps']['discounts']['discount_value_percentage'] = (float) $event['suggested_discount']['optimal'];
		}

		// Mark steps as completed so user can navigate directly to Review.
		// This allows skipping to the final step since everything is pre-filled.
		$completed_steps = array( 'basic', 'schedule', 'products', 'discounts' );
		$this->set( 'completed_steps', $completed_steps );

		$this->set( 'prefilled_from_suggestion', true );

		$this->dirty = true;
		$this->save();
	}

	/**
	 * Pre-fill schedule data for weekly campaigns.
	 *
	 * Handles two modes:
	 * - Create mode (schedule_mode=false): Use current week dates, even if start time passed.
	 *   This is for "Create Campaign" when a weekly campaign is currently active.
	 * - Schedule mode (schedule_mode=true): Use next occurrence dates.
	 *   This is for "Schedule Campaign" or "Plan Next/Ahead" buttons.
	 *
	 * @since    1.0.0
	 * @param    array $event         Weekly campaign event data.
	 * @param    bool  $schedule_mode Whether to use future dates.
	 * @return   void
	 */
	private function prefill_weekly_campaign_schedule( array $event, bool $schedule_mode ): void {
		$schedule           = $event['schedule'];
		$current_week_start = strtotime( 'this week Monday 00:00' );
		$now                = current_time( 'timestamp' );

		$start_day_offset = $schedule['start_day'] - 1; // Monday = 0.
		$end_day_offset   = $schedule['end_day'] - 1;

		// Calculate this week's timestamps.
		$start_timestamp = strtotime( "+{$start_day_offset} days {$schedule['start_time']}", $current_week_start );
		$end_timestamp   = strtotime( "+{$end_day_offset} days {$schedule['end_time']}", $current_week_start );

		if ( $schedule_mode ) {
			// Schedule mode: Always use next occurrence if start has passed.
			if ( $start_timestamp < $now ) {
				$next_week_start = strtotime( 'next week Monday 00:00' );
				$start_timestamp = strtotime( "+{$start_day_offset} days {$schedule['start_time']}", $next_week_start );
				$end_timestamp   = strtotime( "+{$end_day_offset} days {$schedule['end_time']}", $next_week_start );
			}
		} else {
			// Create mode: Use current week dates if campaign is still running (end not passed).
			// This allows creating a campaign for an active weekly event.
			if ( $end_timestamp < $now ) {
				// Campaign has completely ended, use next week.
				$next_week_start = strtotime( 'next week Monday 00:00' );
				$start_timestamp = strtotime( "+{$start_day_offset} days {$schedule['start_time']}", $next_week_start );
				$end_timestamp   = strtotime( "+{$end_day_offset} days {$schedule['end_time']}", $next_week_start );
			}
			// If campaign is still running (end > now), keep this week's dates.
		}

		$this->data['steps']['schedule']['start_type'] = 'scheduled';
		$this->data['steps']['schedule']['start_date'] = wp_date( 'Y-m-d', $start_timestamp );
		$this->data['steps']['schedule']['end_date']   = wp_date( 'Y-m-d', $end_timestamp );
		$this->data['steps']['schedule']['start_time'] = $schedule['start_time'];
		$this->data['steps']['schedule']['end_time']   = $schedule['end_time'];
	}

	/**
	 * Pre-fill wizard from calculator preset code.
	 *
	 * Decodes the preset code from the external Profit Calculator tool
	 * and populates wizard steps with the discount configuration.
	 *
	 * @since    1.0.0
	 * @param    string $code    Calculator preset code (e.g., "SCD:t|5:10,10:15").
	 * @return   bool|WP_Error   True on success, WP_Error on failure.
	 */
	public function prefill_from_calculator( string $code ) {
		require_once WSSCD_INCLUDES_DIR . 'utilities/class-calculator-preset-decoder.php';

		$decoded = WSSCD_Calculator_Preset_Decoder::decode( $code );

		if ( is_wp_error( $decoded ) ) {
			return $decoded;
		}

		$this->set( 'from_calculator', $code );

		// Initialize steps if needed.
		if ( ! isset( $this->data['steps'] ) ) {
			$this->data['steps'] = array();
		}

		// Pre-fill basic step with generated name.
		if ( ! isset( $this->data['steps']['basic'] ) ) {
			$this->data['steps']['basic'] = array();
		}
		$this->data['steps']['basic']['name']     = $decoded['name'];
		$this->data['steps']['basic']['priority'] = 3; // Normal priority.

		// Pre-fill products step with default (all products).
		if ( ! isset( $this->data['steps']['products'] ) ) {
			$this->data['steps']['products'] = array();
		}
		$this->data['steps']['products']['product_selection_type'] = 'all_products';
		$this->data['steps']['products']['selected_product_ids']   = array();

		// Pre-fill discounts step with decoded data.
		if ( ! isset( $this->data['steps']['discounts'] ) ) {
			$this->data['steps']['discounts'] = array();
		}
		$this->data['steps']['discounts'] = array_merge(
			$this->data['steps']['discounts'],
			$decoded['discounts']
		);

		// Pre-fill schedule step with immediate start.
		if ( ! isset( $this->data['steps']['schedule'] ) ) {
			$this->data['steps']['schedule'] = array();
		}
		$this->data['steps']['schedule']['start_type'] = 'immediate';

		// Mark steps as completed so user can navigate directly to Review.
		$completed_steps = array( 'basic', 'products', 'discounts', 'schedule' );
		$this->set( 'completed_steps', $completed_steps );

		$this->set( 'prefilled_from_calculator', true );

		// Mark session as fresh so JavaScript clears sessionStorage.
		// This prevents old cached data from overwriting prefilled values.
		$this->mark_as_fresh();

		$this->dirty = true;
		$this->save();

		return true;
	}

	/**
	 * Create new session.
	 *
	 * @since    1.0.0
	 * @return   string    Session ID.
	 */
	public function create(): string {
		$this->session_id = $this->generate_session_id();
		$this->data       = $this->get_default_session_data();

		$this->set_session_cookie();
		$this->save();

		return $this->session_id;
	}

	/**
	 * Generate session ID.
	 *
	 * @since    1.0.0
	 * @return   string    Session ID.
	 */
	private function generate_session_id(): string {
		return wp_generate_password( 32, false );
	}

	/**
	 * Get default session data.
	 *
	 * @since    1.0.0
	 * @return   array    Default data.
	 */
	private function get_default_session_data(): array {
		return array(
			'session_id'      => $this->session_id,
			'user_id'         => get_current_user_id(), // Bind session to user
			'created_at'      => time(),
			'updated_at'      => time(),
			'steps'           => array(),
			'completed_steps' => array(),
			'campaign_id'     => 0,
		);
	}

	/**
	 * Load session.
	 *
	 * @since    1.0.0
	 * @param    string $session_id    Session ID.
	 * @return   bool                     Success status.
	 */
	public function load( string $session_id ): bool {
		$this->session_id = sanitize_text_field( $session_id );
		$data             = $this->fetch_session_data();

		if ( ! $this->is_valid_session_data( $data ) ) {
			return false;
		}

		// SECURITY: Validate session belongs to current user
		$session_user_id = isset( $data['user_id'] ) ? (int) $data['user_id'] : 0;
		$current_user_id = get_current_user_id();

		if ( $session_user_id !== $current_user_id ) {
			// Session hijacking attempt - reject the session
			return false;
		}

		$this->data  = $data;
		$this->dirty = false;
		$this->extend_session();

		return true;
	}

	/**
	 * Load from cookie.
	 *
	 * @since    1.0.0
	 * @return   bool    Success status.
	 */
	private function load_from_cookie(): bool {
		$session_id = $this->get_session_id_from_cookie();

		if ( ! $session_id ) {
			return false;
		}

		return $this->load( $session_id );
	}

	/**
	 * Get session ID from cookie.
	 *
	 * @since    1.0.0
	 * @return   string|null    Session ID or null.
	 */
	private function get_session_id_from_cookie(): ?string {
		if ( ! isset( $_COOKIE[ self::COOKIE_NAME ] ) ) {
			return null;
		}

		return sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) );
	}

	/**
	 * Fetch session data from database.
	 *
	 * @since    1.0.0
	 * @return   mixed    Session data or false.
	 */
	private function fetch_session_data() {
		return get_transient( self::TRANSIENT_PREFIX . $this->session_id );
	}

	/**
	 * Check if session data is valid.
	 *
	 * @since    1.0.0
	 * @param    mixed $data    Data to check.
	 * @return   bool              Is valid.
	 */
	private function is_valid_session_data( $data ): bool {
		return false !== $data && is_array( $data );
	}


	/**
	 * Deferred save queue.
	 *
	 * @since    1.0.0
	 * @var      array
	 */
	private $deferred_saves = array();

	/**
	 * Save session with locking to prevent race conditions.
	 *
	 * Uses transient-based locking to ensure only one save operation
	 * runs at a time, preventing data loss from concurrent saves.
	 *
	 * Graceful degradation: If lock cannot be acquired, queues the save
	 * for a later attempt instead of silently failing.
	 *
	 * @since    1.0.0
	 * @param    bool $force    Force save without waiting for lock.
	 * @return   bool              Success status.
	 */
	public function save( bool $force = false ): bool {
		if ( ! $this->session_id ) {
			return false;
		}

		// Acquire lock using transient
		$lock_key      = 'wsscd_state_lock_' . $this->session_id;
		$lock_acquired = false;

		if ( $force ) {
			// Force save bypasses lock (used for critical operations like wizard completion)
			$lock_acquired = true;
		} else {
			$lock_acquired = set_transient( $lock_key, time(), 5 ); // 5 second lock
		}

		if ( ! $lock_acquired ) {
			// Lock not acquired - queue for deferred save
			return $this->queue_deferred_save();
		}

		try {
			$this->update_timestamp();
			$success = $this->persist_session_data();

			if ( $success ) {
				$this->dirty = false;
				$this->process_deferred_saves();
			}

			return $success;

		} finally {
			// Always release lock (unless forced)
			if ( ! $force ) {
				delete_transient( $lock_key );
			}
		}
	}

	/**
	 * Queue deferred save.
	 *
	 * When lock cannot be acquired, queue the save for later processing
	 * instead of silently failing. This prevents data loss from concurrent saves.
	 *
	 * @since    1.0.0
	 * @return   bool    Always returns false to indicate immediate save failed.
	 */
	private function queue_deferred_save(): bool {
		$this->deferred_saves[] = time();

		// Schedule a single deferred save attempt using WordPress shutdown hook
		// This runs after the current request completes
		if ( ! has_action( 'shutdown', array( $this, 'attempt_deferred_save' ) ) ) {
			add_action( 'shutdown', array( $this, 'attempt_deferred_save' ) );
		}

		return false; // Indicate immediate save failed
	}

	/**
	 * Attempt deferred save on shutdown.
	 *
	 * Tries to save queued data after the main request completes.
	 * Uses exponential backoff if multiple attempts fail.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function attempt_deferred_save(): void {
		if ( empty( $this->deferred_saves ) ) {
			return;
		}

		$queue_size           = count( $this->deferred_saves );
		$this->deferred_saves = array();

		// Try to save without lock contention
		// Most concurrent saves should be complete by shutdown
		$this->save( false );
	}

	/**
	 * Process deferred saves after successful save.
	 *
	 * If there were deferred saves queued while waiting for lock,
	 * clear them since we just successfully saved.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function process_deferred_saves(): void {
		if ( ! empty( $this->deferred_saves ) ) {
			$this->deferred_saves = array();
		}
	}

	/**
	 * Update timestamp.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function update_timestamp(): void {
		$this->data['updated_at'] = time();
	}

	/**
	 * Persist session data to database.
	 *
	 * @since    1.0.0
	 * @return   bool    Success status.
	 */
	private function persist_session_data(): bool {
		return set_transient(
			self::TRANSIENT_PREFIX . $this->session_id,
			$this->data,
			self::SESSION_LIFETIME
		);
	}

	/**
	 * Save step data.
	 *
	 * In edit mode: Uses Change Tracker to store only deltas.
	 * In create mode: Stores full data in session.
	 *
	 * @since    1.0.0
	 * @param    string $step    Step name.
	 * @param    array  $data    Step data to save.
	 * @return   bool               Success status.
	 */
	public function save_step_data( string $step, array $data ): bool {
		$step = sanitize_key( $step );

		if ( ! $this->is_valid_step( $step ) ) {
			return false;
		}

		$this->prepare_step_data( $step, $data );

		// Invalidate conflicts cache when relevant step data changes
		if ( in_array( $step, array( 'products', 'basic' ), true ) ) {
			$this->invalidate_conflicts_cache();
		}

		// Edit mode: use Change Tracker (stores only deltas)
		$campaign_id = $this->get( 'campaign_id' );
		$is_edit     = $this->is_edit_mode() || $campaign_id;

		if ( $is_edit && $this->change_tracker ) {
			// Track changes via Change Tracker (session only).
			// Database will be updated when user completes wizard via create_from_wizard().
			$this->change_tracker->track_step( $step, $data );
			return true;
		}

		$existing_data = $this->get_step_data( $step );
		$merged_data   = array_merge( $existing_data, $data );
		$this->set_step_data( $step, $merged_data );

		return $this->save();
	}

	/**
	 * Check if step is valid.
	 *
	 * @since    1.0.0
	 * @param    string $step    Step name.
	 * @return   bool               Is valid.
	 */
	private function is_valid_step( string $step ): bool {
		return in_array( $step, $this->steps, true );
	}

	/**
	 * Invalidate conflicts cache when step data changes.
	 *
	 * Clears cached conflict data to ensure fresh conflict detection
	 * after product selection or priority changes.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function invalidate_conflicts_cache(): void {
		if ( class_exists( 'Smart_Cycle_Discounts' ) ) {
			$plugin    = Smart_Cycle_Discounts::get_instance();
			$container = $plugin->get_container();
			if ( $container && $container->has( 'cache_manager' ) ) {
				$cache_manager = $container->get( 'cache_manager' );
				// Clear all conflict-related caches
				$cache_manager->delete_by_prefix( 'campaigns_conflicts_' );
			}
		}
	}

	/**
	 * Prepare step data.
	 *
	 * @since    1.0.0
	 * @param    string $step    Step name.
	 * @param    array  $data    Step data.
	 * @return   void
	 */
	private function prepare_step_data( string $step, array &$data ): void {
		if ( 'products' === $step && isset( $data['product_ids'] ) ) {
			$data['product_ids'] = $this->ensure_array( $data['product_ids'] );
		}
	}

	/**
	 * Ensure value is array.
	 *
	 * @since    1.0.0
	 * @param    mixed $value    Value to check.
	 * @return   array              Array value.
	 */
	private function ensure_array( $value ): array {
		if ( is_array( $value ) ) {
			return $value;
		}

		if ( is_string( $value ) ) {
			return array_filter( explode( ',', $value ) );
		}

		return array();
	}

	/**
	 * Set step data.
	 *
	 * @since    1.0.0
	 * @param    string $step    Step name.
	 * @param    array  $data    Step data.
	 * @return   void
	 */
	public function set_step_data( string $step, array $data ): void {
		if ( ! isset( $this->data['steps'] ) ) {
			$this->data['steps'] = array();
		}

		$this->data['steps'][ $step ] = $data;
		$this->dirty                  = true;
	}

	/**
	 * Get step data.
	 *
	 * In edit mode: Uses Change Tracker to merge DB + deltas.
	 * In create mode: Returns data from session.
	 *
	 * @since    1.0.0
	 * @param    string $step    Step name.
	 * @return   array              Step data.
	 */
	public function get_step_data( string $step ): array {
		$step = sanitize_key( $step );

		// Edit mode: use Change Tracker (DB + deltas)
		if ( $this->is_edit_mode() && $this->change_tracker ) {
			return $this->change_tracker->get_step_data( $step );
		}

		if ( ! isset( $this->data['steps'][ $step ] ) ) {
			return array();
		}

		return $this->data['steps'][ $step ];
	}

	/**
	 * Mark step complete.
	 *
	 * @since    1.0.0
	 * @param    string $step    Step name.
	 * @return   bool               Success status.
	 */
	public function mark_step_complete( string $step ): bool {
		$step = sanitize_key( $step );

		if ( ! $this->is_valid_step( $step ) ) {
			return false;
		}

		$this->ensure_completed_steps_array();

		if ( $this->is_step_complete( $step ) ) {
			return true;
		}

		$this->add_completed_step( $step );

		// Force immediate save for navigation - bypass lock to prevent deferred save.
		// Critical: Must persist before client redirects to next step.
		return $this->save( true );
	}

	/**
	 * Ensure completed steps array exists.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function ensure_completed_steps_array(): void {
		if ( ! isset( $this->data['completed_steps'] ) ) {
			$this->data['completed_steps'] = array();
		}
	}

	/**
	 * Check if step is complete.
	 *
	 * @since    1.0.0
	 * @param    string $step    Step name.
	 * @return   bool               Is complete.
	 */
	private function is_step_complete( string $step ): bool {
		return in_array( $step, $this->data['completed_steps'], true );
	}

	/**
	 * Add completed step.
	 *
	 * @since    1.0.0
	 * @param    string $step    Step name.
	 * @return   void
	 */
	private function add_completed_step( string $step ): void {
		$this->data['completed_steps'][] = $step;
		$this->dirty                     = true;
	}

	/**
	 * Get value.
	 *
	 * @since    1.0.0
	 * @param    string $key       Data key.
	 * @param    mixed  $default   Default value.
	 * @return   mixed                Value or default.
	 */
	public function get( string $key, $default = null ) {
		return $this->data[ $key ] ?? $default;
	}

	/**
	 * Set value.
	 *
	 * @since    1.0.0
	 * @param    string $key      Data key.
	 * @param    mixed  $value    Data value.
	 * @return   void
	 */
	public function set( string $key, $value ): void {
		$this->data[ $key ] = $value;
		$this->dirty        = true;
	}

	/**
	 * Get all data.
	 *
	 * @since    1.0.0
	 * @return   array    Session data.
	 */
	public function get_all_data(): array {
		// CRITICAL FIX: In edit mode, compile data from Change Tracker
		// The session $this->data doesn't contain steps - they're in Change Tracker
		if ( $this->is_edit_mode() && $this->change_tracker ) {
			$steps_data = array();
			foreach ( $this->steps as $step ) {
				$step_data = $this->change_tracker->get_step_data( $step );
				// CRITICAL: Include step even if empty - some steps might have optional fields
				$steps_data[ $step ] = $step_data;
			}

			return array_merge(
				$this->data,
				array( 'steps' => $steps_data )
			);
		}

		return $this->data;
	}

	/**
	 * Get session ID.
	 *
	 * @since    1.0.0
	 * @return   string|null    Session ID or null.
	 */
	public function get_session_id(): ?string {
		return $this->session_id;
	}

	/**
	 * Check if has session.
	 *
	 * @since    1.0.0
	 * @return   bool    Has session.
	 */
	public function has_session(): bool {
		return ! empty( $this->session_id ) && ! empty( $this->data );
	}

	/**
	 * Check if has draft.
	 *
	 * @since    1.0.0
	 * @return   bool    Has draft.
	 */
	public function has_draft(): bool {
		return ! empty( $this->data['steps'] );
	}


	/**
	 * Get draft info.
	 *
	 * @since    1.0.0
	 * @return   array|null    Draft info or null.
	 */
	public function get_draft_info(): ?array {
		if ( ! $this->has_draft() ) {
			return null;
		}

		// Exclude edit mode sessions - they're not drafts
		$is_edit_mode = $this->get( 'is_edit_mode', false );
		if ( $is_edit_mode ) {
			return null;
		}

		return array(
			'campaign_name' => $this->get_campaign_name(),
			'session_id'    => $this->session_id,
			'created_at'    => $this->data['created_at'] ?? time(),
			'updated_at'    => $this->data['updated_at'] ?? time(),
			'progress'      => $this->get_progress(),
			'is_expired'    => $this->is_expired(),
			'last_updated'  => $this->data['updated_at'] ?? time(),
		);
	}

	/**
	 * Get campaign name.
	 *
	 * @since    1.0.0
	 * @return   string    Campaign name.
	 */
	private function get_campaign_name(): string {
		$basic_data = $this->get_step_data( 'basic' );
		return $basic_data['name'] ?? '';
	}

	/**
	 * Get progress.
	 *
	 * @since    1.0.0
	 * @return   array    Progress data.
	 */
	public function get_progress(): array {
		$completed_steps = $this->data['completed_steps'] ?? array();
		$total_steps     = count( $this->steps );
		$completed_count = count( $completed_steps );

		$required_steps = array_diff( $this->steps, array( 'review' ) );
		$can_complete   = count( array_intersect( $required_steps, $completed_steps ) ) === count( $required_steps );

		return array(
			'completed_steps' => $completed_steps,
			'total_steps'     => $total_steps,
			'current_step'    => $completed_count,
			'percentage'      => $this->calculate_percentage( $completed_count, $total_steps ),
			'can_complete'    => $can_complete,
		);
	}

	/**
	 * Calculate percentage.
	 *
	 * @since    1.0.0
	 * @param    int $completed    Completed count.
	 * @param    int $total        Total count.
	 * @return   float                Percentage.
	 */
	private function calculate_percentage( int $completed, int $total ): float {
		if ( 0 === $total ) {
			return 0.0;
		}

		return ( $completed / $total ) * 100;
	}

	/**
	 * Compile campaign data.
	 *
	 * In edit mode: Uses Change Tracker to compile DB + changes.
	 * In create mode: Compiles data from session steps.
	 *
	 * @since    1.0.0
	 * @return   array    Compiled data.
	 */
	public function compile_campaign_data(): array {
		// Edit mode: use Change Tracker
		if ( $this->is_edit_mode() && $this->change_tracker ) {
			return $this->change_tracker->compile();
		}

		if ( ! isset( $this->data['steps'] ) ) {
			return array();
		}

		$compiler = new WSSCD_Campaign_Data_Compiler( $this->data['steps'] );
		return $compiler->compile();
	}

	/**
	 * Check if in edit mode.
	 *
	 * @since    1.0.0
	 * @return   bool    Is edit mode.
	 */
	private function is_edit_mode(): bool {
		return $this->get( 'is_edit_mode', false );
	}

	/**
	 * Initialize change tracker.
	 *
	 * @since    1.0.0
	 * @param    int $campaign_id    Campaign ID.
	 * @return   void
	 */
	private function initialize_change_tracker( int $campaign_id ): void {
		if ( ! $campaign_id ) {
			return;
		}

		$this->change_tracker = new WSSCD_Campaign_Change_Tracker(
			$campaign_id,
			$this,
			null  // Campaign manager will be lazy-loaded
		);
	}

	/**
	 * Get change tracker.
	 *
	 * @since    1.0.0
	 * @return   WSSCD_Campaign_Change_Tracker|null    Change tracker or null.
	 */
	public function get_change_tracker(): ?WSSCD_Campaign_Change_Tracker {
		return $this->change_tracker;
	}

	/**
	 * Clear changes in change tracker.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function clear_changes(): void {
		if ( $this->change_tracker ) {
			$this->change_tracker->clear();
		}
	}

	/**
	 * Clear session.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function clear_session(): void {
		$this->delete_session_data();
		$this->clear_session_cookie();
		$this->reset_instance_data();
	}

	/**
	 * Delete session data.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function delete_session_data(): void {
		if ( $this->session_id ) {
			delete_transient( self::TRANSIENT_PREFIX . $this->session_id );
		}
	}

	/**
	 * Reset instance data.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function reset_instance_data(): void {
		$this->session_id = null;
		$this->data       = array();
		$this->dirty      = false;
	}


	/**
	 * Check if expired.
	 *
	 * Checks expiration based on last activity (updated_at) not creation time.
	 * This ensures active sessions with saves don't expire.
	 *
	 * @since    1.0.0
	 * @return   bool    Is expired.
	 */
	private function is_expired(): bool {
		// Use updated_at for expiration check to respect user activity
		// Falls back to created_at for backwards compatibility
		$last_activity = isset( $this->data['updated_at'] ) ? $this->data['updated_at'] : $this->data['created_at'];

		if ( ! $last_activity ) {
			return false;
		}

		$age = time() - $last_activity;
		return $age > self::SESSION_LIFETIME;
	}

	/**
	 * Extend session.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function extend_session(): void {
		if ( ! $this->should_extend_session() ) {
			return;
		}

		$this->persist_session_data();
		$this->set_session_cookie();
	}

	/**
	 * Check if should extend session.
	 *
	 * @since    1.0.0
	 * @return   bool    Should extend.
	 */
	private function should_extend_session(): bool {
		return $this->session_id && ! $this->is_expired();
	}

	/**
	 * Set session cookie.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function set_session_cookie(): void {
		if ( ! $this->session_id ) {
			return;
		}

		setcookie(
			self::COOKIE_NAME,
			$this->session_id,
			$this->get_cookie_expiration(),
			COOKIEPATH,
			COOKIE_DOMAIN,
			is_ssl(),
			true
		);
	}

	/**
	 * Get cookie expiration.
	 *
	 * @since    1.0.0
	 * @return   int    Expiration timestamp.
	 */
	private function get_cookie_expiration(): int {
		return time() + self::SESSION_LIFETIME;
	}

	/**
	 * Clear session cookie.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function clear_session_cookie(): void {
		setcookie(
			self::COOKIE_NAME,
			'',
			time() - 3600,
			COOKIEPATH,
			COOKIE_DOMAIN,
			is_ssl(),
			true
		);

		unset( $_COOKIE[ self::COOKIE_NAME ] );
	}

	/**
	 * Destructor.
	 *
	 * @since    1.0.0
	 */
	public function __destruct() {
		if ( $this->dirty ) {
			$this->save();
		}
	}
}

/**
 * Campaign Data Compiler
 *
 * Handles compilation of campaign data from steps.
 *
 * @since      1.0.0
 */
class WSSCD_Campaign_Data_Compiler {

	/**
	 * Steps data.
	 *
	 * @since    1.0.0
	 * @var      array
	 */
	private $steps_data;

	/**
	 * Array and complex fields that should be merged, not overwritten.
	 *
	 * @since    1.0.0
	 * @var      array
	 */
	private $array_fields = array(
		'product_ids',
		'category_ids',
		'excluded_products',
		'product_categories',
		'tag_ids',
		'conditions',
		'tiers',
		'bogo_config',
		'thresholds',
		'recurrence_days',
		'discount_rules',
	);

	/**
	 * Constructor.
	 *
	 * @since    1.0.0
	 * @param    array $steps_data    Steps data.
	 */
	public function __construct( array $steps_data ) {
		$this->steps_data = $steps_data;
	}

	/**
	 * Compile data.
	 *
	 * @since    1.0.0
	 * @return   array    Compiled data.
	 */
	public function compile(): array {
		$compiled = array();

		foreach ( $this->steps_data as $step_name => $step_data ) {
			if ( ! is_array( $step_data ) ) {
				continue;
			}

			$this->merge_step_data( $compiled, $step_data );
		}

		return $compiled;
	}

	/**
	 * Merge step data.
	 *
	 * @since    1.0.0
	 * @param    array $compiled     Compiled data.
	 * @param    array $step_data    Step data.
	 * @return   void
	 */
	private function merge_step_data( array &$compiled, array $step_data ): void {
		foreach ( $step_data as $key => $value ) {
			if ( $this->is_array_field( $key ) && is_array( $value ) ) {
				$this->merge_array_field( $compiled, $key, $value );
			} else {
				$compiled[ $key ] = $value;
			}
		}
	}

	/**
	 * Check if field is array field.
	 *
	 * @since    1.0.0
	 * @param    string $field    Field name.
	 * @return   bool                Is array field.
	 */
	private function is_array_field( string $field ): bool {
		return in_array( $field, $this->array_fields, true );
	}

	/**
	 * Merge array field.
	 *
	 * @since    1.0.0
	 * @param    array  $compiled    Compiled data.
	 * @param    string $key         Field key.
	 * @param    array  $value       Field value.
	 * @return   void
	 */
	private function merge_array_field( array &$compiled, string $key, array $value ): void {
		if ( ! isset( $compiled[ $key ] ) || ! is_array( $compiled[ $key ] ) ) {
			$compiled[ $key ] = array();
		}

		$compiled[ $key ] = array_values(
			array_unique(
				array_merge( $compiled[ $key ], $value )
			)
		);
	}
}
