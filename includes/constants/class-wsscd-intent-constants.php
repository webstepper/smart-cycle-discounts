<?php
/**
 * Intent Constants Class
 *
 * Single source of truth for wizard intent values and session flags.
 * All intent validation should use these constants.
 *
 * ## Intent Flow Overview
 *
 * ```
 * User Action          Intent Param      PHP Processing           Session Flag
 * ─────────────────────────────────────────────────────────────────────────────
 * Click "New Campaign" → intent=new   → Intent Handler (early) → is_fresh=true
 * Click "Edit"         → intent=edit  → Intent Handler (early) → is_edit_mode=true
 * Click "Continue"     → intent=cont  → Wizard Controller      → (uses existing)
 * Click "Duplicate"    → intent=dup   → Wizard Controller      → is_fresh=true
 * ```
 *
 * ## Early Processing (admin_init, priority 5)
 *
 * NEW and EDIT intents require early processing because they:
 * 1. Clear existing session (if any)
 * 2. Create new session with cookies (setcookie before output)
 * 3. Redirect to wizard WITHOUT intent param (prevents re-processing on refresh)
 *
 * ## Session Flags
 *
 * - `is_fresh`: Set when NEW intent creates session. Signals JavaScript to clear
 *               browser sessionStorage. Consumed (cleared) after first read.
 * - `is_edit_mode`: Set when EDIT intent creates session. Enables Change Tracker.
 * - `campaign_id`: Set when editing existing campaign.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/constants
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Intent Constants Class
 *
 * Centralizes all wizard intent definitions to avoid duplication
 * and ensure consistent validation across the codebase.
 *
 * @since 1.0.0
 */
class WSSCD_Intent_Constants {

	// =========================================================================
	// INTENT VALUES
	// =========================================================================

	/**
	 * Intent: Create new campaign.
	 *
	 * Clears existing session, creates fresh session with is_fresh=true.
	 * Processed early (admin_init) to set cookies before output.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const NEW = 'new';

	/**
	 * Intent: Continue existing draft.
	 *
	 * Uses existing session if present, creates new if not.
	 * Does NOT require early processing.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const CONTINUE = 'continue';

	/**
	 * Intent: Edit existing campaign.
	 *
	 * Clears existing session, creates edit session with campaign_id.
	 * Processed early (admin_init) to set cookies before output.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const EDIT = 'edit';

	/**
	 * Intent: Duplicate existing campaign.
	 *
	 * Creates fresh session pre-filled with campaign data.
	 * Does NOT require early processing.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const DUPLICATE = 'duplicate';

	/**
	 * Default intent when none specified.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const DEFAULT_INTENT = self::CONTINUE;

	// =========================================================================
	// SESSION FLAGS (for PHP → JavaScript communication)
	// =========================================================================

	/**
	 * Session flag: Fresh session indicator.
	 *
	 * Set by: start_fresh_session() when processing NEW intent.
	 * Read by: Asset Localizer (passes to JavaScript as isFresh).
	 * Consumed: After first read (one-time signal).
	 * Purpose: Signals JavaScript to clear browser sessionStorage.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const FLAG_IS_FRESH = 'is_fresh';

	/**
	 * Session flag: Edit mode indicator.
	 *
	 * Set by: start_edit_session() when processing EDIT intent.
	 * Used by: State Service to enable Change Tracker.
	 * Persists: For duration of edit session.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const FLAG_IS_EDIT_MODE = 'is_edit_mode';

	/**
	 * Session flag: Campaign ID for edit mode.
	 *
	 * Set by: start_edit_session() when processing EDIT intent.
	 * Used by: Change Tracker to load/compare campaign data.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const FLAG_CAMPAIGN_ID = 'campaign_id';

	// =========================================================================
	// INTENT GROUPINGS
	// =========================================================================

	/**
	 * All allowed intent values.
	 *
	 * @since 1.0.0
	 * @var   array
	 */
	const ALL_INTENTS = array(
		self::NEW,
		self::CONTINUE,
		self::EDIT,
		self::DUPLICATE,
	);

	/**
	 * Intents that require early processing (before headers sent).
	 *
	 * These intents set session cookies and must be processed on admin_init
	 * before any output occurs. After processing, the URL is redirected
	 * to remove the intent parameter.
	 *
	 * @since 1.0.0
	 * @var   array
	 */
	const EARLY_PROCESS_INTENTS = array(
		self::NEW,
		self::EDIT,
	);

	/**
	 * Intents that create fresh sessions (clear existing data).
	 *
	 * @since 1.0.0
	 * @var   array
	 */
	const FRESH_SESSION_INTENTS = array(
		self::NEW,
		self::DUPLICATE,
	);

	// =========================================================================
	// VALIDATION METHODS
	// =========================================================================

	/**
	 * Check if an intent is valid.
	 *
	 * @since  1.0.0
	 * @param  string $intent Intent to validate.
	 * @return bool           True if valid.
	 */
	public static function is_valid( string $intent ): bool {
		return in_array( $intent, self::ALL_INTENTS, true );
	}

	/**
	 * Check if intent requires early processing.
	 *
	 * Early processing intents must be handled on admin_init hook
	 * before any output, as they need to set session cookies.
	 *
	 * @since  1.0.0
	 * @param  string $intent Intent to check.
	 * @return bool           True if requires early processing.
	 */
	public static function requires_early_processing( string $intent ): bool {
		return in_array( $intent, self::EARLY_PROCESS_INTENTS, true );
	}

	/**
	 * Check if intent creates a fresh session.
	 *
	 * Fresh session intents clear existing session data and start clean.
	 *
	 * @since  1.0.0
	 * @param  string $intent Intent to check.
	 * @return bool           True if creates fresh session.
	 */
	public static function creates_fresh_session( string $intent ): bool {
		return in_array( $intent, self::FRESH_SESSION_INTENTS, true );
	}

	/**
	 * Sanitize and validate an intent value.
	 *
	 * @since  1.0.0
	 * @param  string $intent   Raw intent value.
	 * @param  string $fallback Fallback value if invalid.
	 * @return string           Validated intent or fallback.
	 */
	public static function sanitize( string $intent, string $fallback = self::DEFAULT_INTENT ): string {
		$intent = sanitize_key( $intent );
		return self::is_valid( $intent ) ? $intent : $fallback;
	}

	// =========================================================================
	// HELPER METHODS
	// =========================================================================

	/**
	 * Get intent from URL parameters.
	 *
	 * Centralized intent retrieval to avoid duplicate code.
	 * Use this instead of directly accessing $_GET['intent'].
	 *
	 * @since  1.0.0
	 * @return string Validated intent or empty string.
	 */
	public static function get_from_request(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Intent is for routing only, validated by is_valid().
		$intent = isset( $_GET['intent'] ) ? sanitize_key( wp_unslash( $_GET['intent'] ) ) : '';

		return self::is_valid( $intent ) ? $intent : '';
	}

	/**
	 * Check if current request has a specific intent.
	 *
	 * @since  1.0.0
	 * @param  string $intent Intent to check for.
	 * @return bool           True if request has this intent.
	 */
	public static function request_has_intent( string $intent ): bool {
		return self::get_from_request() === $intent;
	}

	/**
	 * Check if current request needs early processing.
	 *
	 * Use this in admin_init hooks to determine if intent handling is needed.
	 *
	 * @since  1.0.0
	 * @return bool True if current request has an early-process intent.
	 */
	public static function request_needs_early_processing(): bool {
		$intent = self::get_from_request();
		return '' !== $intent && self::requires_early_processing( $intent );
	}
}
