<?php
/**
 * Cycle AI Handler Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers/class-cycle-ai-handler.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2026 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Cycle AI Handler Class
 *
 * PRO-only AJAX handler that delegates to the Cycle AI service to fetch
 * campaign suggestions. This handler:
 * - Verifies capability and premium access.
 * - Enforces a defensive rate limit.
 * - Returns a normalized suggestions array for the wizard UI.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers
 * @author     Webstepper <contact@webstepper.io>
 */
class WSSCD_Cycle_AI_Handler extends WSSCD_Abstract_Ajax_Handler {

	/**
	 * Feature key used for gating Cycle AI access.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	const FEATURE_KEY = 'cycle_ai_campaign_suggestions';

	/**
	 * Rate limit: maximum AI calls per user per hour.
	 *
	 * @since 1.0.0
	 *
	 * @var int
	 */
	const RATE_LIMIT_PER_HOUR = 30;

	/**
	 * Cycle AI service instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Cycle_AI_Service $cycle_ai_service Cycle AI service.
	 */
	private $cycle_ai_service;

	/**
	 * Feature gate instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Feature_Gate $feature_gate Feature gate.
	 */
	private $feature_gate;

	/**
	 * Rate limiter instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Rate_Limiter $rate_limiter Rate limiter.
	 */
	private $rate_limiter;

	/**
	 * Initialize the handler.
	 *
	 * @since 1.0.0
	 *
	 * @param WSSCD_Cycle_AI_Service $cycle_ai_service Cycle AI service.
	 * @param WSSCD_Feature_Gate     $feature_gate     Feature gate.
	 * @param WSSCD_Rate_Limiter     $rate_limiter     Rate limiter.
	 * @param WSSCD_Logger|null      $logger           Logger instance (optional).
	 */
	public function __construct( $cycle_ai_service, $feature_gate, $rate_limiter, $logger = null ) {
		parent::__construct( $logger );

		$this->cycle_ai_service = $cycle_ai_service;
		$this->feature_gate     = $feature_gate;
		$this->rate_limiter     = $rate_limiter;
	}

	/**
	 * Get AJAX action name.
	 *
	 * @since 1.0.0
	 *
	 * @return string Action name.
	 */
	protected function get_action_name() {
		return 'wsscd_cycle_ai_suggest_campaigns';
	}

	/**
	 * Handle the Cycle AI suggestions request.
	 *
	 * @since 1.0.0
	 *
	 * @param array $request Request data.
	 * @return array Response data.
	 */
	protected function handle( $request ) {
		// Capability check (defense in depth; Ajax_Security also enforces this).
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return $this->error(
				__( 'You do not have permission to use Cycle AI.', 'smart-cycle-discounts' ),
				'forbidden',
				403
			);
		}

		// PRO feature gating.
		if ( ! $this->feature_gate || ! $this->feature_gate->can_use_feature( self::FEATURE_KEY ) ) {
			return $this->error(
				__( 'Cycle AI campaign suggestions are available in the Pro version.', 'smart-cycle-discounts' ),
				'feature_not_available',
				403
			);
		}

		// Defensive rate limiting per user.
		$user_id = get_current_user_id();
		$key     = 'cycle_ai_suggest_campaigns_user_' . ( $user_id ? $user_id : 'guest' );

		if ( $this->rate_limiter && $this->rate_limiter->is_limited( $key, self::RATE_LIMIT_PER_HOUR, HOUR_IN_SECONDS ) ) {
			if ( current_user_can( 'manage_options' ) ) {
				$this->rate_limiter->reset( $key );
			} else {
				return $this->error(
					__( 'You have reached the Cycle AI usage limit. Please try again later.', 'smart-cycle-discounts' ),
					'rate_limited',
					429
				);
			}
		}

		// Delegate to service.
		if ( ! $this->cycle_ai_service || ! method_exists( $this->cycle_ai_service, 'get_campaign_suggestions' ) ) {
			return $this->error(
				__( 'Cycle AI service is not available.', 'smart-cycle-discounts' ),
				'service_unavailable',
				500
			);
		}

		$suggestions = $this->cycle_ai_service->get_campaign_suggestions();

		if ( is_wp_error( $suggestions ) ) {
			return $this->handle_wp_error( $suggestions );
		}

		// Normalize suggestions structure for the wizard UI.
		$normalized = $this->normalize_suggestions( $suggestions );

		// Consume rate limit only on success.
		if ( $this->rate_limiter ) {
			$this->rate_limiter->record_hit( $key, self::RATE_LIMIT_PER_HOUR, HOUR_IN_SECONDS );
		}

		return $this->success(
			array(
				'suggestions' => $normalized,
			)
		);
	}

	/**
	 * Normalize raw AI suggestions into a predictable structure.
	 *
	 * @since 1.0.0
	 *
	 * @param array $suggestions Raw suggestions array from AI.
	 * @return array Normalized suggestions.
	 */
	private function normalize_suggestions( array $suggestions ): array {
		$normalized = array();

		foreach ( $suggestions as $index => $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$name        = isset( $item['name'] ) ? sanitize_text_field( $item['name'] ) : '';
			$description = isset( $item['description'] ) ? sanitize_textarea_field( $item['description'] ) : '';
			$type        = isset( $item['discount_type'] ) ? sanitize_key( $item['discount_type'] ) : '';
			$value       = isset( $item['discount_value'] ) ? floatval( $item['discount_value'] ) : 0;
			$target      = isset( $item['target'] ) ? sanitize_text_field( $item['target'] ) : '';
			$duration    = isset( $item['duration_days'] ) ? absint( $item['duration_days'] ) : 0;
			$notes       = isset( $item['notes'] ) ? sanitize_textarea_field( $item['notes'] ) : '';

			if ( '' === $name ) {
				continue;
			}

			$normalized[] = array(
				'id'            => 'cycle_ai_' . $index,
				'name'          => $name,
				'description'   => $description,
				'discount_type' => $type,
				'discount_value'=> $value,
				'target'        => $target,
				'duration_days' => $duration,
				'notes'         => $notes,
			);
		}

		return $normalized;
	}

	/**
	 * Get required capability.
	 *
	 * This is used indirectly by security helpers and kept for parity
	 * with other handlers, although verify_request() already checks capability.
	 *
	 * @since 1.0.0
	 *
	 * @return string Required capability.
	 */
	protected function get_required_capability() {
		return 'manage_woocommerce';
	}
}

