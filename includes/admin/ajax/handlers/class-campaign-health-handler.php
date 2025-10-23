<?php
/**
 * Campaign Health Handler
 *
 * Handles AJAX requests for calculating campaign health score.
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

// Load wizard helpers trait
require_once SCD_INCLUDES_DIR . 'admin/ajax/trait-wizard-helpers.php';

/**
 * Campaign Health Handler Class
 *
 * Coordinates health score calculation and analysis.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Campaign_Health_Handler extends SCD_Abstract_Ajax_Handler {

	use SCD_Wizard_Helpers;

	/**
	 * Constructor.
	 *
	 * @since    1.0.0
	 * @param    SCD_Logger    $logger    Logger instance (optional).
	 */
	public function __construct( $logger = null ) {
		parent::__construct( $logger );
	}

	/**
	 * Get AJAX action name.
	 *
	 * @since    1.0.0
	 * @return   string    Action name.
	 */
	protected function get_action_name() {
		return 'scd_campaign_health';
	}

	/**
	 * Handle the campaign health request.
	 *
	 * @since    1.0.0
	 * @param    array    $request    Request data.
	 * @return   array               Response data.
	 */
	protected function handle( $request ) {
		// Get state service
		$state_service = $this->_get_state_service();
		if ( ! $state_service ) {
			return $this->error(
				__( 'Could not load wizard data', 'smart-cycle-discounts' ),
				'state_service_unavailable',
				500
			);
		}

		// Get coverage data
		$coverage_handler = new SCD_Preview_Coverage_Handler();
		$coverage_response = $coverage_handler->handle( $request );

		if ( is_wp_error( $coverage_response ) ) {
			$coverage_data = array();
		} else {
			// Extract data from wrapped response
			$coverage_data = isset( $coverage_response['data'] ) ? $coverage_response['data'] : array();
		}

		// Get conflicts data
		$conflicts_handler = new SCD_Check_Conflicts_Handler();
		$conflicts_response = $conflicts_handler->handle( $request );

		if ( is_wp_error( $conflicts_response ) ) {
			$conflicts_data = array();
		} else {
			// Extract data from wrapped response
			$conflicts_data = isset( $conflicts_response['data'] ) ? $conflicts_response['data'] : array();
		}

		// Calculate health score
		$calculator = $this->_get_health_calculator( $state_service );
		$health = $calculator->calculate( $coverage_data, $conflicts_data );

		// Add coverage and conflicts to response
		$health['coverage'] = $coverage_data;
		$health['conflicts'] = $conflicts_data;

		return $this->success( $health );
	}

	/**
	 * Get health calculator.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    SCD_Wizard_State_Service    $state_service    State service.
	 * @return   SCD_Campaign_Health_Calculator               Health calculator.
	 */
	private function _get_health_calculator( $state_service ) {
		if ( ! class_exists( 'SCD_Campaign_Health_Calculator' ) ) {
			require_once SCD_INCLUDES_DIR . 'core/wizard/class-campaign-health-calculator.php';
		}

		// Get health service from container if available
		$health_service = null;
		if ( class_exists( 'SmartCycleDiscounts' ) ) {
			$plugin = SmartCycleDiscounts::get_instance();
			$container = $plugin->get_container();
			if ( $container && $container->has( 'campaign_health_service' ) ) {
				$health_service = $container->get( 'campaign_health_service' );
			}
		}

		return new SCD_Campaign_Health_Calculator( $state_service, $health_service );
	}
}
