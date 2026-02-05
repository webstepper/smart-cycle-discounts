<?php
/**
 * Cycle AI Create Full Campaign Handler Class
 *
 * PRO-only AJAX handler that generates one full campaign via Cycle AI,
 * creates a wizard session with all steps prefilled, and returns a redirect URL
 * so the user can review and save from the wizard.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2026 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cycle AI Create Full Campaign Handler
 *
 * @since 1.0.0
 */
class WSSCD_Cycle_AI_Create_Full_Handler extends WSSCD_Abstract_Ajax_Handler {

	const FEATURE_KEY         = 'cycle_ai_campaign_suggestions';
	const RATE_LIMIT_PER_HOUR  = 10;

	/** @var WSSCD_Cycle_AI_Service */
	private $cycle_ai_service;

	/** @var WSSCD_Feature_Gate */
	private $feature_gate;

	/** @var WSSCD_Rate_Limiter */
	private $rate_limiter;

	/** @var WSSCD_Wizard_State_Service */
	private $state_service;

	/** @var WSSCD_Campaign_Manager */
	private $campaign_manager;

	/**
	 * Constructor.
	 *
	 * @param WSSCD_Cycle_AI_Service     $cycle_ai_service Cycle AI service.
	 * @param WSSCD_Feature_Gate         $feature_gate     Feature gate.
	 * @param WSSCD_Rate_Limiter         $rate_limiter     Rate limiter.
	 * @param WSSCD_Wizard_State_Service $state_service    Wizard state service.
	 * @param WSSCD_Campaign_Manager     $campaign_manager Campaign manager.
	 * @param WSSCD_Logger|null          $logger           Logger.
	 */
	public function __construct(
		$cycle_ai_service,
		$feature_gate,
		$rate_limiter,
		$state_service,
		$campaign_manager,
		$logger = null
	) {
		parent::__construct( $logger );
		$this->cycle_ai_service = $cycle_ai_service;
		$this->feature_gate     = $feature_gate;
		$this->rate_limiter     = $rate_limiter;
		$this->state_service    = $state_service;
		$this->campaign_manager = $campaign_manager;
	}

	/**
	 * Get AJAX action name.
	 *
	 * @return string
	 */
	public function get_action_name(): string {
		return 'wsscd_cycle_ai_create_full_campaign';
	}

	/**
	 * Handle the request: build context, get full campaign from AI, create session, return redirect.
	 *
	 * @param array $request Request data.
	 * @return array Success with redirect_url or error array.
	 */
	protected function handle( $request ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return $this->error( __( 'You do not have permission to use Cycle AI.', 'smart-cycle-discounts' ), 'forbidden', 403 );
		}

		// Allow up to 90s for AI API call (client timeout is 90s; wp_remote_post uses 45s).
		if ( function_exists( 'set_time_limit' ) && false === strpos( ini_get( 'disable_functions' ), 'set_time_limit' ) ) {
			set_time_limit( 90 );
		}

		if ( ! $this->feature_gate || ! $this->feature_gate->can_use_feature( self::FEATURE_KEY ) ) {
			return $this->error( __( 'Cycle AI full campaign creation is available in the Pro version.', 'smart-cycle-discounts' ), 'feature_not_available', 403 );
		}

		$user_id = get_current_user_id();
		$key     = 'cycle_ai_create_full_user_' . ( $user_id ? $user_id : 'guest' );
		if ( $this->rate_limiter && $this->rate_limiter->is_limited( $key, self::RATE_LIMIT_PER_HOUR, HOUR_IN_SECONDS ) ) {
			// Allow admins to bypass once by resetting their limit (recovers from old "increment on every request" or testing).
			if ( current_user_can( 'manage_options' ) ) {
				$this->rate_limiter->reset( $key );
			} else {
				return $this->error( __( 'You have reached the Cycle AI usage limit. Please try again later.', 'smart-cycle-discounts' ), 'rate_limited', 429 );
			}
		}

		if ( ! $this->cycle_ai_service || ! method_exists( $this->cycle_ai_service, 'get_full_campaign_suggestion' ) ) {
			return $this->error( __( 'Cycle AI service is not available.', 'smart-cycle-discounts' ), 'service_unavailable', 500 );
		}

		if ( ! $this->state_service ) {
			return $this->error( __( 'Wizard session could not be created.', 'smart-cycle-discounts' ), 'session_error', 500 );
		}

		$existing_campaigns_text = $this->build_existing_campaigns_context();
		$context_overrides       = array( 'existing_campaigns' => $existing_campaigns_text );

		$user_brief = isset( $request['user_brief'] ) && is_string( $request['user_brief'] )
			? sanitize_text_field( wp_unslash( $request['user_brief'] ) )
			: '';
		if ( '' !== $user_brief ) {
			$context_overrides['user_brief'] = substr( $user_brief, 0, 500 );
		}

		$steps = $this->cycle_ai_service->get_full_campaign_suggestion( $context_overrides );

		if ( is_wp_error( $steps ) ) {
			return $this->handle_wp_error( $steps );
		}

		$this->state_service->clear_session();
		$this->state_service->create();

		$this->state_service->set( 'prefilled_from_cycle_ai', true );
		$this->state_service->set( 'is_fresh', true );
		if ( '' !== $user_brief ) {
			$this->state_service->set( 'cycle_ai_user_brief', substr( $user_brief, 0, 500 ) );
		}

		foreach ( array( 'basic', 'products', 'discounts', 'schedule' ) as $step_name ) {
			if ( isset( $steps[ $step_name ] ) && is_array( $steps[ $step_name ] ) ) {
				$this->state_service->save_step_data( $step_name, $steps[ $step_name ] );
			}
		}

		$this->state_service->set( 'completed_steps', array( 'basic', 'products', 'discounts', 'schedule' ) );
		$this->state_service->save( true );

		// Consume rate limit only on success so failed attempts do not burn quota.
		if ( $this->rate_limiter ) {
			$this->rate_limiter->record_hit( $key, self::RATE_LIMIT_PER_HOUR, HOUR_IN_SECONDS );
		}

		$redirect_url = add_query_arg(
			array(
				'page'   => 'wsscd-campaigns',
				'action' => 'wizard',
				'step'   => 'review',
			),
			admin_url( 'admin.php' )
		);

		return $this->success( array( 'redirect_url' => $redirect_url ) );
	}

	/**
	 * Build a short summary of existing campaigns for AI context (names and types only).
	 *
	 * @return string
	 */
	private function build_existing_campaigns_context(): string {
		if ( ! $this->campaign_manager ) {
			return '';
		}

		$campaigns = $this->campaign_manager->get_campaigns(
			array(
				'status'   => array( 'active', 'scheduled', 'draft', 'paused' ),
				'per_page' => 30,
				'orderby'  => 'id',
				'order'    => 'DESC',
			)
		);

		if ( empty( $campaigns ) ) {
			return __( 'No existing campaigns.', 'smart-cycle-discounts' );
		}

		$lines = array();
		foreach ( $campaigns as $campaign ) {
			if ( ! is_object( $campaign ) || ! method_exists( $campaign, 'get_name' ) ) {
				continue;
			}
			$name   = $campaign->get_name();
			$status = method_exists( $campaign, 'get_status' ) ? $campaign->get_status() : '';
			$type   = method_exists( $campaign, 'get_discount_type' ) ? $campaign->get_discount_type() : '';
			$lines[] = '- ' . $name . ' (' . $status . ', ' . $type . ')';
		}

		return implode( "\n", $lines );
	}
}
