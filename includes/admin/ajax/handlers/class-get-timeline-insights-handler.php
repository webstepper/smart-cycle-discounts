<?php
/**
 * Get Campaign Planner Insights Handler
 *
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Get Campaign Planner Insights Handler Class
 *
 * Handles retrieving insights for a specific Campaign Planner campaign.
 * Returns rendered HTML for the insights panel (Why/How/When tabs).
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Get_Timeline_Insights_Handler extends SCD_Abstract_Ajax_Handler {

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
		return 'scd_get_timeline_insights';
	}

	/**
	 * Handle the get Campaign Planner insights request.
	 *
	 * Returns rendered HTML for campaign insights panel (Why/How/When tabs).
	 *
	 * @since    1.0.0
	 * @param    array $request    Request data.
	 * @return   array               Response data.
	 */
	protected function handle( $request ) {
		// Sanitize inputs.
		$campaign_id    = isset( $request['campaign_id'] ) ? sanitize_text_field( $request['campaign_id'] ) : '';
		$state          = isset( $request['state'] ) ? sanitize_text_field( $request['state'] ) : 'active';
		$is_major_event = isset( $request['is_major_event'] ) && '1' === $request['is_major_event'];

		// Validate campaign ID.
		if ( empty( $campaign_id ) ) {
			return array(
				'success' => false,
				'message' => __( 'Campaign ID is required', 'smart-cycle-discounts' ),
			);
		}

		// Validate state.
		if ( ! in_array( $state, array( 'past', 'active', 'future' ), true ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid campaign state', 'smart-cycle-discounts' ),
			);
		}

		// Log request.
		if ( $this->logger ) {
			$this->logger->debug(
				'Timeline Insights Handler: Loading insights',
				array(
					'campaign_id'    => $campaign_id,
					'state'          => $state,
					'is_major_event' => $is_major_event,
				)
			);
		}

		// Get insights from service.
		$insights_data = $this->dashboard_service->get_unified_insights( $campaign_id, $state, $is_major_event );

		if ( empty( $insights_data ) ) {
			return array(
				'success' => false,
				'message' => __( 'Campaign not found', 'smart-cycle-discounts' ),
			);
		}

		// Render insights HTML.
		$view_file = SCD_VIEWS_DIR . 'admin/pages/dashboard/partials/timeline-insights.php';

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

			// Return rendered HTML.
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
