<?php
/**
 * Main Dashboard Data Handler
 *
 * Handles AJAX requests for main dashboard data (Free tier).
 * Returns hero stats, campaign status, top campaigns, and recent activity.
 *
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers
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
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Main_Dashboard_Data_Handler extends SCD_Abstract_Ajax_Handler {

	/**
	 * Main dashboard page instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Main_Dashboard_Page    $dashboard_page    Dashboard page instance.
	 */
	private $dashboard_page;

	/**
	 * Initialize the handler.
	 *
	 * @since    1.0.0
	 * @param    SCD_Main_Dashboard_Page    $dashboard_page    Dashboard page instance.
	 * @param    SCD_Logger                 $logger            Logger instance.
	 */
	public function __construct( $dashboard_page, $logger = null ) {
		parent::__construct( $logger );
		$this->dashboard_page = $dashboard_page;
	}

	/**
	 * Get AJAX action name.
	 *
	 * @since    1.0.0
	 * @return   string    Action name.
	 */
	protected function get_action_name() {
		return 'scd_ajax';
	}

	/**
	 * Get required capability.
	 *
	 * @since    1.0.0
	 * @return   string    Required capability.
	 */
	protected function get_required_capability() {
		return 'scd_view_analytics';
	}

	/**
	 * Handle the request.
	 *
	 * @since    1.0.0
	 * @param    array    $request    Request data.
	 * @return   array                Response data.
	 */
	public function handle( $request ) {
		try {
			// Get dashboard data using reflection to access private method
			// This is necessary because get_dashboard_data is private
			$reflection = new ReflectionClass( $this->dashboard_page );
			$method = $reflection->getMethod( 'get_dashboard_data' );
			$method->setAccessible( true );

			// Fixed 7-day range for free tier
			$data = $method->invoke( $this->dashboard_page, '7days' );

			// Return formatted response
			return array(
				'success' => true,
				'data' => array(
					'metrics' => isset( $data['metrics'] ) ? $data['metrics'] : array(),
					'campaign_stats' => isset( $data['campaign_stats'] ) ? $data['campaign_stats'] : array(),
					'top_campaigns' => isset( $data['top_campaigns'] ) ? $data['top_campaigns'] : array(),
					'is_premium' => isset( $data['is_premium'] ) ? $data['is_premium'] : false,
					'campaign_limit' => isset( $data['campaign_limit'] ) ? $data['campaign_limit'] : 3,
				),
			);

		} catch ( ReflectionException $e ) {
			$this->logger->error( 'Dashboard data reflection failed', array(
				'error' => $e->getMessage(),
			) );

			return array(
				'success' => false,
				'data' => array(
					'message' => __( 'Failed to load dashboard data (reflection error)', 'smart-cycle-discounts' ),
				),
			);

		} catch ( Exception $e ) {
			$this->logger->error( 'Get dashboard data failed', array(
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString(),
			) );

			return array(
				'success' => false,
				'data' => array(
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
	 * @param    array    $request    Request data.
	 * @return   bool|WP_Error         True if valid, WP_Error if invalid.
	 */
	protected function verify_request( $request ) {
		// Verify nonce
		if ( ! isset( $request['nonce'] ) || ! wp_verify_nonce( $request['nonce'], 'scd_main_dashboard' ) ) {
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
