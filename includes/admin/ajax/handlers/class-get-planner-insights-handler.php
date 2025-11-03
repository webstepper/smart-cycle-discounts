<?php
/**
 * Get Planner Insights Handler Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers/class-get-planner-insights-handler.php
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Get Campaign Planner Insights Handler Class
 *
 * Handles retrieving insights for a specific Campaign Planner campaign.
 * Returns rendered HTML for the insights panel (3-column layout: Opportunity/Strategy/Timeline).
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Get_Planner_Insights_Handler extends SCD_Abstract_Ajax_Handler {

	/**
	 * Dashboard service instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Dashboard_Service    $dashboard_service    Dashboard service.
	 */
	private $dashboard_service;

	/**
	 * Initialize the handler.
	 *
	 * @since    1.0.0
	 * @param    SCD_Dashboard_Service $dashboard_service    Dashboard service.
	 * @param    SCD_Logger            $logger                Logger instance (optional).
	 */
	public function __construct( $dashboard_service, $logger = null ) {
		parent::__construct( $logger );
		$this->dashboard_service = $dashboard_service;
	}

	/**
	 * Get AJAX action name.
	 *
	 * @since    1.0.0
	 * @return   string    Action name.
	 */
	protected function get_action_name() {
		return 'scd_get_planner_insights';
	}

	/**
	 * Handle the get Campaign Planner insights request.
	 *
	 * Returns rendered HTML for campaign insights panel (3-column layout).
	 *
	 * @since    1.0.0
	 * @param    array $request    Request data.
	 * @return   array               Response data.
	 */
	protected function handle( $request ) {
		$campaign_id    = isset( $request['campaign_id'] ) ? sanitize_text_field( $request['campaign_id'] ) : '';
		$state          = isset( $request['state'] ) ? sanitize_text_field( $request['state'] ) : 'active';
		$position       = isset( $request['position'] ) ? sanitize_text_field( $request['position'] ) : $state;
		$is_major_event = isset( $request['is_major_event'] ) && '1' === $request['is_major_event'];

		if ( empty( $campaign_id ) ) {
			return array(
				'success' => false,
				'message' => __( 'Campaign ID is required', 'smart-cycle-discounts' ),
			);
		}

		if ( ! in_array( $state, array( 'past', 'active', 'future' ), true ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid campaign state', 'smart-cycle-discounts' ),
			);
		}

		if ( ! in_array( $position, array( 'past', 'active', 'future' ), true ) ) {
			$position = $state; // Fallback to state if invalid.
		}

		// Log request.
		if ( $this->logger ) {
			$this->logger->debug(
				'Timeline Insights Handler: Loading insights',
				array(
					'campaign_id'    => $campaign_id,
					'state'          => $state,
					'position'       => $position,
					'is_major_event' => $is_major_event,
				)
			);
		}

		// Position = where campaign is displayed (past/active/future slots).
		// State = actual campaign state (past/active/future).
		// For gap-filled campaigns, position may differ from state.
		$insights_data = $this->dashboard_service->get_unified_insights( $campaign_id, $position, $is_major_event );

		if ( empty( $insights_data ) ) {
			return array(
				'success' => false,
				'message' => __( 'Campaign not found', 'smart-cycle-discounts' ),
			);
		}

		$view_file = SCD_VIEWS_DIR . 'admin/pages/dashboard/partials/planner-insights.php';

		if ( ! file_exists( $view_file ) ) {
			return array(
				'success' => false,
				'message' => __( 'View template not found', 'smart-cycle-discounts' ),
			);
		}

		try {
			ob_start();
			require $view_file;
			$html = ob_get_clean();

			return array(
				'success' => true,
				'html'    => $html,
			);
		} catch ( Exception $e ) {
			// Clean up output buffer if still active
			if ( ob_get_level() > 0 ) {
				ob_end_clean();
			}

			return array(
				'success' => false,
				'message' => __( 'Failed to render insights template', 'smart-cycle-discounts' ),
			);
		}
	}

	/**
	 * Get required capability.
	 *
	 * @since    1.0.0
	 * @return   string    Required capability.
	 */
	protected function get_required_capability() {
		return 'manage_woocommerce';
	}
}
