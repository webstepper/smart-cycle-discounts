<?php
/**
 * Campaign Health Handler Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers/class-campaign-health-handler.php
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

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
	 * @param    SCD_Logger $logger    Logger instance (optional).
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
	 * REFACTORED: Now uses unified SCD_Campaign_Health_Service instead of wizard-specific calculator.
	 * Maintains backward compatibility with wizard frontend expectations.
	 *
	 * @since    1.0.0
	 * @param    array $request    Request data.
	 * @return   array               Response data.
	 */
	protected function handle( $request ) {
		$state_service = $this->_get_state_service();
		if ( ! $state_service ) {
			return $this->error(
				__( 'Could not load wizard data', 'smart-cycle-discounts' ),
				'state_service_unavailable',
				500
			);
		}

		$coverage_handler  = new SCD_Preview_Coverage_Handler();
		$coverage_response = $coverage_handler->handle( $request );

		if ( is_wp_error( $coverage_response ) ) {
			$coverage_data = array();
		} else {
			$coverage_data = isset( $coverage_response['data'] ) ? $coverage_response['data'] : array();
		}

		$conflicts_handler  = new SCD_Check_Conflicts_Handler();
		$conflicts_response = $conflicts_handler->handle( $request );

		if ( is_wp_error( $conflicts_response ) ) {
			$conflicts_data = array();
		} else {
			$conflicts_data = isset( $conflicts_response['data'] ) ? $conflicts_response['data'] : array();
		}

		// Use unified health service with wizard context
		$health_service = $this->_get_health_service();
		$campaign_data  = $this->_prepare_campaign_data_for_service( $state_service );

		$context = array(
			'coverage_data'  => $coverage_data,
			'conflicts_data' => $conflicts_data,
			'view_context'   => 'review', // Wizard review step
		);

		$health_analysis = $health_service->analyze_health( $campaign_data, 'comprehensive', $context );

		// Enhance critical issues with wizard-required properties (severity, step)
		$critical_issues = $this->_enhance_critical_issues(
			isset( $health_analysis['critical_issues'] ) ? $health_analysis['critical_issues'] : array()
		);

		$stock_risk = isset( $health_analysis['stock_risk'] ) ? $health_analysis['stock_risk'] : array(
			'has_risk'          => false,
			'high_risk_count'   => 0,
			'medium_risk_count' => 0,
			'products'          => array(),
		);

		$response = array(
			'score'            => isset( $health_analysis['score'] ) ? $health_analysis['score'] : 100,
			'percentage'       => min( 100, round( isset( $health_analysis['score'] ) ? $health_analysis['score'] : 100 ) ),
			'status'           => isset( $health_analysis['status'] ) ? $health_analysis['status'] : 'excellent',
			'is_ready'         => isset( $health_analysis['is_ready'] ) ? $health_analysis['is_ready'] : true,
			'critical_issues'  => $critical_issues,
			'recommendations'  => isset( $health_analysis['recommendations'] ) ? $health_analysis['recommendations'] : array(),
			'conflict_preview' => $this->_build_conflict_preview( $conflicts_data ),
			'coverage'         => $coverage_data,
			'stock_risk'       => $stock_risk, // IMPROVED: Now uses real data from health service
		);

		return $this->success( $response );
	}

	/**
	 * Get unified health service from container.
	 *
	 * REFACTORED: Now returns SCD_Campaign_Health_Service instead of wizard calculator.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   SCD_Campaign_Health_Service    Health service instance.
	 */
	private function _get_health_service() {
		if ( class_exists( 'SmartCycleDiscounts' ) ) {
			$plugin    = SmartCycleDiscounts::get_instance();
			$container = $plugin->get_container();
			if ( $container && $container->has( 'campaign_health_service' ) ) {
				return $container->get( 'campaign_health_service' );
			}
		}

		// Fallback: create instance manually
		if ( ! class_exists( 'SCD_Campaign_Health_Service' ) ) {
			require_once SCD_INCLUDES_DIR . 'core/services/class-campaign-health-service.php';
		}

		return new SCD_Campaign_Health_Service( $this->logger );
	}

	/**
	 * Prepare campaign data from wizard state for health service.
	 *
	 * Transforms wizard state format to campaign data array expected by health service.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    SCD_Wizard_State_Service $state_service    State service.
	 * @return   array                                         Campaign data array.
	 */
	private function _prepare_campaign_data_for_service( $state_service ) {
		$basic     = $state_service->get_step_data( 'basic' );
		$products  = $state_service->get_step_data( 'products' );
		$discounts = $state_service->get_step_data( 'discounts' );
		$schedule  = $state_service->get_step_data( 'schedule' );

		$discount_type  = isset( $discounts['discount_type'] ) ? $discounts['discount_type'] : 'percentage';
		$discount_value = 0;

		if ( 'percentage' === $discount_type ) {
			$discount_value = isset( $discounts['discount_value_percentage'] ) ? floatval( $discounts['discount_value_percentage'] ) : 0;
		} elseif ( 'fixed' === $discount_type ) {
			$discount_value = isset( $discounts['discount_value_fixed'] ) ? floatval( $discounts['discount_value_fixed'] ) : 0;
		}

		return array(
			'name'                   => isset( $basic['name'] ) ? $basic['name'] : '',
			'description'            => isset( $basic['description'] ) ? $basic['description'] : '',
			'priority'               => isset( $basic['priority'] ) ? $basic['priority'] : 3,
			'discount_type'          => $discount_type,
			'discount_value'         => $discount_value,
			'product_selection_type' => isset( $products['product_selection_type'] ) ? $products['product_selection_type'] : 'all_products',
			'selected_product_ids'   => isset( $products['selected_product_ids'] ) ? $products['selected_product_ids'] : array(),
			'start_date'             => isset( $schedule['start_date'] ) ? $schedule['start_date'] : null,
			'end_date'               => isset( $schedule['end_date'] ) ? $schedule['end_date'] : null,
			'start_time'             => isset( $schedule['start_time'] ) ? $schedule['start_time'] : '00:00',
			'end_time'               => isset( $schedule['end_time'] ) ? $schedule['end_time'] : '23:59',
			'start_type'             => isset( $schedule['start_type'] ) ? $schedule['start_type'] : 'scheduled',
			'status'                 => 'draft', // Wizard campaigns are drafts
		);
	}

	/**
	 * Enhance critical issues with wizard-required properties.
	 *
	 * Adds 'severity' and 'step' properties that the wizard JavaScript expects.
	 * Maps category to appropriate wizard step.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $critical_issues    Critical issues from health service.
	 * @return   array                        Enhanced critical issues.
	 */
	private function _enhance_critical_issues( $critical_issues ) {
		$category_to_step = array(
			'products' => 'products',
			'discount' => 'discounts',
			'timing'   => 'schedule',
			'schedule' => 'schedule',
			'basic'    => 'basic',
		);

		$enhanced = array();
		foreach ( $critical_issues as $issue ) {
			if ( ! isset( $issue['severity'] ) ) {
				$issue['severity'] = 'critical';
			}

			if ( ! isset( $issue['step'] ) && isset( $issue['category'] ) ) {
				$category      = $issue['category'];
				$issue['step'] = isset( $category_to_step[ $category ] ) ? $category_to_step[ $category ] : 'basic';
			}

			$enhanced[] = $issue;
		}

		return $enhanced;
	}

	/**
	 * Build conflict preview from conflicts data.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $conflicts_data    Conflicts data.
	 * @return   array                       Conflict preview.
	 */
	private function _build_conflict_preview( $conflicts_data ) {
		$has_conflicts   = isset( $conflicts_data['has_conflicts'] ) ? $conflicts_data['has_conflicts'] : false;
		$total_conflicts = isset( $conflicts_data['total_products_blocked'] ) ? $conflicts_data['total_products_blocked'] : 0;
		$conflicts       = array();

		if ( ! empty( $conflicts_data['conflicts'] ) ) {
			foreach ( $conflicts_data['conflicts'] as $conflict ) {
				$conflicts[] = array(
					'campaign_name'     => isset( $conflict['name'] ) ? $conflict['name'] : '',
					'priority'          => isset( $conflict['priority'] ) ? $conflict['priority'] : 0,
					'affected_products' => isset( $conflict['product_count'] ) ? $conflict['product_count'] : 0,
				);
			}
		}

		return array(
			'has_conflicts'   => $has_conflicts,
			'total_conflicts' => $total_conflicts,
			'conflicts'       => $conflicts,
		);
	}
}
