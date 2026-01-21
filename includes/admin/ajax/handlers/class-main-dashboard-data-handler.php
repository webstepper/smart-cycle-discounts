<?php
/**
 * Main Dashboard Data Handler Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers/class-main-dashboard-data-handler.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Main Dashboard Data Handler Class
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers
 * @author     Webstepper <contact@webstepper.io>
 */
class WSSCD_Main_Dashboard_Data_Handler extends WSSCD_Abstract_Ajax_Handler {

	/**
	 * Dashboard service instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Dashboard_Service    $dashboard_service    Dashboard service instance.
	 */
	private $dashboard_service;

	/**
	 * Initialize the handler.
	 *
	 * @since    1.0.0
	 * @param    WSSCD_Dashboard_Service $dashboard_service    Dashboard service instance.
	 * @param    WSSCD_Logger            $logger               Logger instance.
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
		return 'wsscd_ajax';
	}

	/**
	 * Get required capability.
	 *
	 * @since    1.0.0
	 * @return   string    Required capability.
	 */
	protected function get_required_capability() {
		return 'wsscd_view_analytics';
	}

	/**
	 * Handle the request.
	 *
	 * @since    1.0.0
	 * @param    array $request    Request data.
	 * @return   array                Response data.
	 */
	public function handle( $request ) {
		try {
			// Fixed 7-day range for free tier
			$data = $this->dashboard_service->get_dashboard_data(
				array(
					'date_range' => '7days',
				)
			);

			return array(
				'success' => true,
				'data'    => array(
					'metrics'        => isset( $data['metrics'] ) ? $data['metrics'] : array(),
					'campaign_stats' => isset( $data['campaign_stats'] ) ? $data['campaign_stats'] : array(),
					'top_campaigns'  => isset( $data['top_campaigns'] ) ? $data['top_campaigns'] : array(),
					'is_premium'     => isset( $data['is_premium'] ) ? $data['is_premium'] : false,
				),
			);

		} catch ( Exception $e ) {
			$this->logger->error(
				'Get dashboard data failed',
				array(
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString(),
				)
			);

			return array(
				'success' => false,
				'data'    => array(
					'message' => sprintf(
						/* translators: %s: error message */
						__( 'Failed to load dashboard data: %s', 'smart-cycle-discounts' ),
						$e->getMessage()
					),
				),
			);
		}
	}

	/**
	 * Verify the AJAX request.
	 *
	 * @since    1.0.0
	 * @param    array $request    Request data.
	 * @return   bool|WP_Error         True if valid, WP_Error if invalid.
	 */
	protected function verify_request( $request ) {
		// Verify nonce
		$nonce = isset( $request['nonce'] ) ? sanitize_text_field( $request['nonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, 'wsscd_main_dashboard' ) ) {
			return new WP_Error(
				'invalid_nonce',
				__( 'Security verification failed', 'smart-cycle-discounts' )
			);
		}

		// Verify capability
		$capability = $this->get_required_capability();
		if ( ! current_user_can( $capability ) ) {
			return new WP_Error(
				'insufficient_permissions',
				__( 'You do not have permission to access this data', 'smart-cycle-discounts' )
			);
		}

		return true;
	}
}
