<?php
/**
 * Main Dashboard Page Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/pages/dashboard/class-main-dashboard-page.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress/plugins/smart-cycle-discounts/
 * @since      1.0.0
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Main Dashboard Page Controller
 *
 * Thin controller that delegates data fetching to Dashboard Service.
 * All health data calculation and caching is handled by the service layer.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/pages/dashboard
 * @author     Webstepper <contact@webstepper.io>
 */
class WSSCD_Main_Dashboard_Page {

	/**
	 * Feature gate instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Feature_Gate    $feature_gate    Feature gate.
	 */
	private WSSCD_Feature_Gate $feature_gate;

	/**
	 * Upgrade prompt manager instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Upgrade_Prompt_Manager    $upgrade_prompt_manager    Upgrade prompt manager.
	 */
	private WSSCD_Upgrade_Prompt_Manager $upgrade_prompt_manager;

	/**
	 * Logger instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Logger    $logger    Logger instance.
	 */
	private WSSCD_Logger $logger;

	/**
	 * Dashboard service instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Dashboard_Service    $dashboard_service    Dashboard service.
	 */
	private WSSCD_Dashboard_Service $dashboard_service;

	/**
	 * Initialize the main dashboard page.
	 *
	 * @since    1.0.0
	 * @param    WSSCD_Analytics_Dashboard|null $analytics_dashboard      Analytics dashboard (Pro-only, null in free).
	 * @param    WSSCD_Campaign_Repository      $campaign_repository      Campaign repository (unused, kept for BC).
	 * @param    WSSCD_Feature_Gate             $feature_gate             Feature gate.
	 * @param    WSSCD_Upgrade_Prompt_Manager   $upgrade_prompt_manager   Upgrade prompt manager.
	 * @param    WSSCD_Logger                   $logger                   Logger instance.
	 * @param    WSSCD_Campaign_Health_Service  $health_service           Campaign health service (unused, kept for BC).
	 * @param    WSSCD_Dashboard_Service        $dashboard_service        Dashboard service.
	 */
	public function __construct(
		?WSSCD_Analytics_Dashboard $analytics_dashboard,
		WSSCD_Campaign_Repository $campaign_repository,
		WSSCD_Feature_Gate $feature_gate,
		WSSCD_Upgrade_Prompt_Manager $upgrade_prompt_manager,
		WSSCD_Logger $logger,
		WSSCD_Campaign_Health_Service $health_service,
		WSSCD_Dashboard_Service $dashboard_service
	) {
		$this->feature_gate           = $feature_gate;
		$this->upgrade_prompt_manager = $upgrade_prompt_manager;
		$this->logger                 = $logger;
		$this->dashboard_service      = $dashboard_service;
	}

	/**
	 * Render the main dashboard page.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function render(): void {
		if ( ! current_user_can( 'wsscd_view_analytics' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'smart-cycle-discounts' ) );
		}

		try {
			$date_range     = $this->feature_gate->is_premium() ? '30days' : '7days';
			$dashboard_data = $this->get_dashboard_data( $date_range );

			$this->render_view( $dashboard_data );

		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to render main dashboard page',
				array(
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString(),
				)
			);

			wp_die( esc_html__( 'Failed to load dashboard.', 'smart-cycle-discounts' ) );
		}
	}

	/**
	 * Get dashboard data.
	 *
	 * Delegates to Dashboard Service which handles caching and data calculation.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $date_range    Date range.
	 * @return   array                    Dashboard data.
	 */
	private function get_dashboard_data( $date_range ): array {
		$dashboard_data = $this->dashboard_service->get_dashboard_data(
			array(
				'date_range' => $date_range,
			)
		);

		$dashboard_data['campaign_suggestions'] = $this->dashboard_service->get_campaign_suggestions();

		$dashboard_data['planner_data'] = $this->dashboard_service->get_weekly_planner_campaigns();

		return $dashboard_data;
	}

	/**
	 * Render the dashboard view.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $data    Dashboard data.
	 * @return   void
	 */
	private function render_view( $data ): void {
		$metrics              = $data['metrics'];
		$campaign_stats       = $data['campaign_stats'];
		$top_campaigns        = $data['top_campaigns'];
		$recent_activity      = $data['recent_activity'];
		$campaign_health      = $data['campaign_health'];
		$campaign_suggestions = $data['campaign_suggestions'];
		$planner_data         = $data['planner_data'] ?? array();
		$all_campaigns        = $data['all_campaigns'] ?? array();
		$is_premium           = $data['is_premium'];

		// Pass feature gate, upgrade prompt manager, and dashboard service to view.
		$feature_gate           = $this->feature_gate;
		$upgrade_prompt_manager = $this->upgrade_prompt_manager;
		$dashboard_service      = $this->dashboard_service;

		$view_file = WSSCD_PLUGIN_DIR . 'resources/views/admin/pages/dashboard/main-dashboard.php';

		if ( file_exists( $view_file ) ) {
			include $view_file;
		} else {
			echo '<div class="wrap">';
			echo '<h1>' . esc_html__( 'Smart Cycle Discounts Dashboard', 'smart-cycle-discounts' ) . '</h1>';
			echo '<p>' . esc_html__( 'Dashboard template not found.', 'smart-cycle-discounts' ) . '</p>';
			echo '</div>';
		}
	}
}
