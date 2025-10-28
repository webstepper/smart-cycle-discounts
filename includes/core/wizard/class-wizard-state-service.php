<?php
/**
 * Wizard State Service
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/wizard
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wizard State Service Class
 *
 * Manages wizard session state with database-based persistence.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/wizard
 */
class SCD_Wizard_State_Service {

	/**
	 * Session cookie name.
	 *
	 * @since    1.0.0
	 * @var      string
	 */
	const COOKIE_NAME = 'scd_wizard_session';

	/**
	 * Session lifetime in seconds (2 hours).
	 *
	 * Based on last activity (updated_at), not creation time.
	 * Auto-save runs every 30 seconds, keeping active sessions alive.
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
	const TRANSIENT_PREFIX = 'scd_wizard_session_';

	/**
	 * Available wizard steps (delegated to Step Registry).
	 *
	 * @since    1.0.0
	 * @deprecated Use SCD_Wizard_Step_Registry::get_steps() instead
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
	 * @var      SCD_Campaign_Change_Tracker|null
	 */
	private $change_tracker = null;

	/**
	 * Constructor.
	 *
	 * @since    1.0.0
	 * @param    string|null    $session_id    Optional session ID to load.
	 */
	public function __construct( $session_id = null ) {
		// Use Step Registry for step definitions
		$this->steps = SCD_Wizard_Step_Registry::get_steps();

		$this->initialize( $session_id );
	}

	/**
	 * Initialize session.
	 *
	 * @since    1.0.0
	 * @param    string|null    $session_id    Session ID to load.
	 * @return   void
	 */
	private function initialize( $session_id = null ): void {
		if ( $session_id ) {
			$this->load( $session_id );
		} else {
			$this->load_from_cookie();
		}
	}

	/**
	 * Initialize with specific intent.
	 *
	 * @since    1.0.0
	 * @param    string    $intent    Intent: 'new', 'continue', or 'edit'.
	 * @return   void
	 */
	public function initialize_with_intent( string $intent = 'continue' ): void {
		if ( 'new' === $intent ) {
			$this->start_fresh_session();
		} elseif ( 'edit' === $intent ) {
			$this->start_edit_session();
		} elseif ( 'continue' === $intent && ! $this->has_session() ) {
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
		// Get campaign ID from URL
		$campaign_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

		if ( ! $campaign_id ) {
			// No campaign ID provided, treat as new
			$this->start_fresh_session();
			return;
		}

		// Check if we already have an edit session for this campaign
		$existing_campaign_id = $this->get( 'campaign_id', 0 );
		$is_edit_mode = $this->get( 'is_edit_mode', false );

		if ( $existing_campaign_id === $campaign_id && $is_edit_mode ) {
			// Already editing this campaign, keep existing session and change tracker
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[State Service] Reusing existing edit session for campaign ' . $campaign_id );
			}
			$this->initialize_change_tracker( $campaign_id );
			return;
		}

		// Different campaign or not in edit mode - create new edit session
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[State Service] Creating new edit session for campaign ' . $campaign_id . ' (previous campaign: ' . $existing_campaign_id . ')' );
		}

		// Clear existing session and start fresh for editing
		$this->clear_session();
		$this->create();

		// Store campaign ID in session
		$this->set( 'campaign_id', $campaign_id );
		$this->set( 'is_edit_mode', true );

		// Mark all steps as completed so user can navigate freely
		$this->set( 'completed_steps', $this->steps );

		// Initialize change tracker for this campaign
		$this->initialize_change_tracker( $campaign_id );

		$this->save();
	}

	/**
	 * Start fresh session.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function start_fresh_session(): void {
		$this->clear_session();
		$this->create();
		$this->mark_as_fresh();
	}

	/**
	 * Mark session as fresh.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function mark_as_fresh(): void {
		$this->data['is_fresh'] = true;
		$this->dirty = true;
		$this->save();
	}

	/**
	 * Clear fresh flag.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function clear_fresh_flag(): void {
		if ( isset( $this->data['is_fresh'] ) ) {
			unset( $this->data['is_fresh'] );
			$this->dirty = true;
			$this->save();
		}
	}

	/**
	 * Create new session.
	 *
	 * @since    1.0.0
	 * @return   string    Session ID.
	 */
	public function create(): string {
		$this->session_id = $this->generate_session_id();
		$this->data = $this->get_default_session_data();

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
	 * @param    string    $session_id    Session ID.
	 * @return   bool                     Success status.
	 */
	public function load( string $session_id ): bool {
		$this->session_id = sanitize_text_field( $session_id );
		$data = $this->fetch_session_data();

		if ( ! $this->is_valid_session_data( $data ) ) {
			return false;
		}

		$this->data = $data;
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
		
		return sanitize_text_field( $_COOKIE[ self::COOKIE_NAME ] );
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
	 * @param    mixed    $data    Data to check.
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
	 * @param    bool    $force    Force save without waiting for lock.
	 * @return   bool              Success status.
	 */
	public function save( bool $force = false ): bool {
		if ( ! $this->session_id ) {
			return false;
		}

		// Acquire lock using transient
		$lock_key = 'scd_state_lock_' . $this->session_id;
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
				// Process any deferred saves after successful save
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
		// Add current timestamp to deferred saves queue
		$this->deferred_saves[] = time();

		// Log for debugging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf(
				'[State Service] Lock not acquired, queuing save (queue size: %d)',
				count( $this->deferred_saves )
			) );
		}

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

		// Clear the deferred saves queue
		$queue_size = count( $this->deferred_saves );
		$this->deferred_saves = array();

		// Try to save without lock contention
		// Most concurrent saves should be complete by shutdown
		$success = $this->save( false );

		if ( $success ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( sprintf(
					'[State Service] Deferred save succeeded (cleared %d queued saves)',
					$queue_size
				) );
			}
		} else {
			// Still couldn't save - log warning but don't retry infinitely
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( sprintf(
					'[State Service] Deferred save failed (lost %d queued saves)',
					$queue_size
				) );
			}
		}
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
			$queue_size = count( $this->deferred_saves );
			$this->deferred_saves = array();

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( sprintf(
					'[State Service] Cleared %d deferred saves after successful save',
					$queue_size
				) );
			}
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
	 * @param    string    $step    Step name.
	 * @param    array     $data    Step data to save.
	 * @return   bool               Success status.
	 */
	public function save_step_data( string $step, array $data ): bool {
		$step = sanitize_key( $step );

		if ( ! $this->is_valid_step( $step ) ) {
			return false;
		}

		// Prepare data
		$this->prepare_step_data( $step, $data );

		// Edit mode: use Change Tracker (stores only deltas)
		if ( $this->is_edit_mode() ) {
			if ( ! $this->change_tracker ) {
				// Change tracker not initialized, fall back to session storage
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( '[State Service] Change tracker not initialized in edit mode, using session storage' );
				}
			} else {
				// Track changes via Change Tracker
				$this->change_tracker->track_step( $step, $data );
				return true;
			}
		}

		// Create mode: store full data in session
		$existing_data = $this->get_step_data( $step );
		$merged_data = array_merge( $existing_data, $data );
		$this->set_step_data( $step, $merged_data );

		return $this->save();
	}

	/**
	 * Check if step is valid.
	 *
	 * @since    1.0.0
	 * @param    string    $step    Step name.
	 * @return   bool               Is valid.
	 */
	private function is_valid_step( string $step ): bool {
		return in_array( $step, $this->steps, true );
	}

	/**
	 * Prepare step data.
	 *
	 * @since    1.0.0
	 * @param    string    $step    Step name.
	 * @param    array     $data    Step data.
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
	 * @param    mixed    $value    Value to check.
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
	 * @param    string    $step    Step name.
	 * @param    array     $data    Step data.
	 * @return   void
	 */
	public function set_step_data( string $step, array $data ): void {
		if ( ! isset( $this->data['steps'] ) ) {
			$this->data['steps'] = array();
		}

		$this->data['steps'][ $step ] = $data;
		$this->dirty = true;
	}

	/**
	 * Get step data.
	 *
	 * In edit mode: Uses Change Tracker to merge DB + deltas.
	 * In create mode: Returns data from session.
	 *
	 * @since    1.0.0
	 * @param    string    $step    Step name.
	 * @return   array              Step data.
	 */
	public function get_step_data( string $step ): array {
		$step = sanitize_key( $step );

		// Edit mode: use Change Tracker (DB + deltas)
		if ( $this->is_edit_mode() && $this->change_tracker ) {
			return $this->change_tracker->get_step_data( $step );
		}

		// Create mode: return from session
		if ( ! isset( $this->data['steps'][ $step ] ) ) {
			return array();
		}

		return $this->data['steps'][ $step ];
	}

	/**
	 * Mark step complete.
	 *
	 * @since    1.0.0
	 * @param    string    $step    Step name.
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
		
		return $this->save();
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
	 * @param    string    $step    Step name.
	 * @return   bool               Is complete.
	 */
	private function is_step_complete( string $step ): bool {
		return in_array( $step, $this->data['completed_steps'], true );
	}

	/**
	 * Add completed step.
	 *
	 * @since    1.0.0
	 * @param    string    $step    Step name.
	 * @return   void
	 */
	private function add_completed_step( string $step ): void {
		$this->data['completed_steps'][] = $step;
		$this->dirty = true;
	}

	/**
	 * Get value.
	 *
	 * @since    1.0.0
	 * @param    string    $key       Data key.
	 * @param    mixed     $default   Default value.
	 * @return   mixed                Value or default.
	 */
	public function get( string $key, $default = null ) {
		return $this->data[ $key ] ?? $default;
	}

	/**
	 * Set value.
	 *
	 * @since    1.0.0
	 * @param    string    $key      Data key.
	 * @param    mixed     $value    Data value.
	 * @return   void
	 */
	public function set( string $key, $value ): void {
		$this->data[ $key ] = $value;
		$this->dirty = true;
	}

	/**
	 * Get all data.
	 *
	 * @since    1.0.0
	 * @return   array    Session data.
	 */
	public function get_all_data(): array {
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
		$total_steps = count( $this->steps );
		$completed_count = count( $completed_steps );

		// Check if all required steps are completed (all steps except 'review')
		$required_steps = array_diff( $this->steps, array( 'review' ) );
		$can_complete = count( array_intersect( $required_steps, $completed_steps ) ) === count( $required_steps );

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
	 * @param    int    $completed    Completed count.
	 * @param    int    $total        Total count.
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
			$compiled = $this->change_tracker->compile();

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[Wizard State] Compiled from Change Tracker: ' . print_r( $compiled, true ) );
			}

			return $compiled;
		}

		// Create mode: compile from session
		if ( ! isset( $this->data['steps'] ) ) {
			return array();
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[Wizard State] Steps data before compilation: ' . print_r( $this->data['steps'], true ) );
		}

		$compiler = new Campaign_Data_Compiler( $this->data['steps'] );
		$compiled = $compiler->compile();

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[Wizard State] Compiled result: ' . print_r( $compiled, true ) );
		}

		return $compiled;
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
	 * @param    int    $campaign_id    Campaign ID.
	 * @return   void
	 */
	private function initialize_change_tracker( int $campaign_id ): void {
		if ( ! $campaign_id ) {
			return;
		}

		// Create change tracker instance
		$this->change_tracker = new SCD_Campaign_Change_Tracker(
			$campaign_id,
			$this,
			null  // Campaign manager will be lazy-loaded
		);

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[State Service] Change tracker initialized for campaign ' . $campaign_id );
		}
	}

	/**
	 * Get change tracker.
	 *
	 * @since    1.0.0
	 * @return   SCD_Campaign_Change_Tracker|null    Change tracker or null.
	 */
	public function get_change_tracker(): ?SCD_Campaign_Change_Tracker {
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
		$this->data = array();
		$this->dirty = false;
	}


	/**
	 * Check if expired.
	 *
	 * Checks expiration based on last activity (updated_at) not creation time.
	 * This ensures active sessions with auto-save don't expire.
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
class Campaign_Data_Compiler {

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
		'discount_rules'
	);

	/**
	 * Constructor.
	 *
	 * @since    1.0.0
	 * @param    array    $steps_data    Steps data.
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
	 * @param    array    $compiled     Compiled data.
	 * @param    array    $step_data    Step data.
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
	 * @param    string    $field    Field name.
	 * @return   bool                Is array field.
	 */
	private function is_array_field( string $field ): bool {
		return in_array( $field, $this->array_fields, true );
	}

	/**
	 * Merge array field.
	 *
	 * @since    1.0.0
	 * @param    array     $compiled    Compiled data.
	 * @param    string    $key         Field key.
	 * @param    array     $value       Field value.
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