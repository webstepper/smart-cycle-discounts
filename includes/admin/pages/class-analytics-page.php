<?php
/**
 * Analytics Page Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/pages/class-analytics-page.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress/plugins/smart-cycle-discounts/
 * @since      1.0.0
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

require_once WSSCD_PLUGIN_DIR . 'includes/admin/ajax/class-wsscd-ajax-response.php';

// Load AJAX security handler
require_once WSSCD_PLUGIN_DIR . 'includes/admin/ajax/class-ajax-security.php';

/**
 * Analytics Page Controller
 *
 * Handles the analytics dashboard page in the WordPress admin.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/pages
 * @author     Webstepper <contact@webstepper.io>
 */
class WSSCD_Analytics_Page {

	/**
	 * Analytics collector instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Analytics_Collector    $analytics_collector    Analytics collector.
	 */
	private WSSCD_Analytics_Collector $analytics_collector;

	/**
	 * Metrics calculator instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Metrics_Calculator    $metrics_calculator    Metrics calculator.
	 */
	private WSSCD_Metrics_Calculator $metrics_calculator;

	/**
	 * Chart renderer instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Chart_Renderer    $chart_renderer    Chart renderer.
	 */
	private WSSCD_Chart_Renderer $chart_renderer;

	/**
	 * Logger instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Logger    $logger    Logger instance.
	 */
	private WSSCD_Logger $logger;

	/**
	 * Campaign Overview Panel instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Campaign_Overview_Panel    $overview_panel    Campaign Overview Panel.
	 */
	private WSSCD_Campaign_Overview_Panel $overview_panel;

	/**
	 * Initialize the analytics page.
	 *
	 * @since    1.0.0
	 * @param    WSSCD_Analytics_Collector       $analytics_collector    Analytics collector.
	 * @param    WSSCD_Metrics_Calculator        $metrics_calculator     Metrics calculator.
	 * @param    WSSCD_Chart_Renderer            $chart_renderer         Chart renderer.
	 * @param    WSSCD_Logger                    $logger                 Logger instance.
	 * @param    WSSCD_Campaign_Overview_Panel   $overview_panel         Campaign Overview Panel.
	 */
	public function __construct(
		WSSCD_Analytics_Collector $analytics_collector,
		WSSCD_Metrics_Calculator $metrics_calculator,
		WSSCD_Chart_Renderer $chart_renderer,
		WSSCD_Logger $logger,
		WSSCD_Campaign_Overview_Panel $overview_panel
	) {
		$this->analytics_collector = $analytics_collector;
		$this->metrics_calculator  = $metrics_calculator;
		$this->chart_renderer      = $chart_renderer;
		$this->logger              = $logger;
		$this->overview_panel      = $overview_panel;
	}

	/**
	 * Render the analytics page.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function render(): void {
		if ( ! current_user_can( 'wsscd_view_analytics' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'smart-cycle-discounts' ) );
		}

		$is_premium = function_exists( 'wsscd_is_premium' ) && wsscd_is_premium();

		// Show upgrade prompt for free users
		if ( ! $is_premium ) {
			$this->render_upgrade_prompt();
			return;
		}

		try {
			$date_range = $this->get_current_date_range();

			// Check if user requested cache refresh.
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading URL param for cache refresh only. Capability checked at method start. Value validated against expected '1'.
			$refresh = isset( $_GET['refresh'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['refresh'] ) );

			// Calculate metrics once (use cache unless refresh requested)
			$full_metrics = $this->metrics_calculator->calculate_overall_metrics( $date_range, ! $refresh );

			// Build overview metrics from full metrics
			$overview_metrics = $this->build_overview_metrics( $full_metrics );

			$campaigns_data = $this->get_campaigns_data( $date_range );

			// Assets are handled by the centralized asset management system

			$this->render_dashboard( $overview_metrics, $campaigns_data, $date_range, $full_metrics );

		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to render analytics page',
				array(
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString(),
				)
			);

			wp_die( esc_html__( 'Failed to load analytics dashboard.', 'smart-cycle-discounts' ) );
		}
	}

	/**
	 * Get current date range.
	 *
	 * SECURITY: Called only from render() which enforces capability check.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   string    Date range.
	 */
	private function get_current_date_range(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading URL param for date range only. Capability checked in caller (render()). Value validated against whitelist below.
		$date_range = isset( $_GET['date_range'] ) ? sanitize_text_field( wp_unslash( $_GET['date_range'] ) ) : '30days';

		$valid_ranges = array( '24hours', '7days', '30days', '90days', 'custom' );

		if ( ! in_array( $date_range, $valid_ranges, true ) ) {
			$date_range = '30days';
		}

		return $date_range;
	}

	/**
	 * Build overview metrics from calculated data.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $metrics    Calculated metrics data.
	 * @return   array                Overview metrics.
	 */
	private function build_overview_metrics( array $metrics ): array {
		try {
			return array(
				array(
					'title'       => __( 'Total Revenue', 'smart-cycle-discounts' ),
					'value'       => $metrics['total_revenue'] ?? 0,
					'change'      => $metrics['revenue_change'] ?? 0,
					'change_type' => $this->get_change_type( $metrics['revenue_change'] ?? 0 ),
					'icon'        => 'receipt',
					'format'      => 'currency',
					'description' => __( 'Revenue generated from discount campaigns', 'smart-cycle-discounts' ),
					'help_text'   => __( 'Total revenue from orders where discounts were applied. Calculated as sum of order totals after discounts.', 'smart-cycle-discounts' ),
				),
				array(
					'title'       => __( 'Conversions', 'smart-cycle-discounts' ),
					'value'       => $metrics['total_conversions'] ?? 0,
					'change'      => $metrics['conversions_change'] ?? 0,
					'change_type' => $this->get_change_type( $metrics['conversions_change'] ?? 0 ),
					'icon'        => 'cart',
					'format'      => 'number',
					'description' => __( 'Number of successful purchases', 'smart-cycle-discounts' ),
					'help_text'   => __( 'Total number of completed orders that used discount campaigns. Includes all order statuses except failed, cancelled, and pending.', 'smart-cycle-discounts' ),
				),
				array(
					'title'       => __( 'Avg Order Value', 'smart-cycle-discounts' ),
					'value'       => $metrics['avg_order_value'] ?? 0,
					'change'      => $metrics['aov_change'] ?? 0,
					'change_type' => $this->get_change_type( $metrics['aov_change'] ?? 0 ),
					'icon'        => 'chart-line',
					'format'      => 'currency',
					'description' => __( 'Average value per order with discount applied', 'smart-cycle-discounts' ),
					'help_text'   => __( 'Average revenue per order calculated as Total Revenue divided by Conversions. Higher values indicate customers are purchasing more per transaction.', 'smart-cycle-discounts' ),
				),
				array(
					'title'       => __( 'Click-through Rate', 'smart-cycle-discounts' ),
					'value'       => $metrics['avg_ctr'] ?? 0,
					'change'      => $metrics['ctr_change'] ?? 0,
					'change_type' => $this->get_change_type( $metrics['ctr_change'] ?? 0 ),
					'icon'        => 'performance',
					'format'      => 'percentage',
					'description' => __( 'Percentage of clicks that led to purchases', 'smart-cycle-discounts' ),
					'help_text'   => __( 'Percentage of discount impressions that resulted in completed orders. Calculated as (Conversions / Impressions) × 100. Higher rates indicate more effective campaigns.', 'smart-cycle-discounts' ),
				),
				array(
					'title'       => __( 'Active Campaigns', 'smart-cycle-discounts' ),
					'value'       => $metrics['active_campaigns'] ?? 0,
					'change'      => $metrics['campaigns_change'] ?? 0,
					'change_type' => $this->get_change_type( $metrics['campaigns_change'] ?? 0 ),
					'icon'        => 'megaphone',
					'format'      => 'number',
					'description' => __( 'Currently running discount campaigns', 'smart-cycle-discounts' ),
					'help_text'   => __( 'Number of campaigns currently active and available to customers. This count includes campaigns that have started and not yet ended.', 'smart-cycle-discounts' ),
				),
			);

		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to build overview metrics',
				array(
					'error' => $e->getMessage(),
				)
			);

			return array();
		}
	}

	/**
	 * Get campaigns data.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $date_range    Date range.
	 * @return   array                    Campaigns data.
	 */
	private function get_campaigns_data( string $date_range ): array {
		try {
			return $this->metrics_calculator->calculate_all_campaigns_metrics( $date_range );
		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to get campaigns data',
				array(
					'error'      => $e->getMessage(),
					'date_range' => $date_range,
				)
			);

			return array();
		}
	}

	/**
	 * Get change type based on value.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    float $change    Change value.
	 * @return   string             Change type.
	 */
	private function get_change_type( float $change ): string {
		if ( $change > 0 ) {
			return 'positive';
		} elseif ( $change < 0 ) {
			return 'negative';
		}

		return 'neutral';
	}


	/**
	 * Render the dashboard.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array  $overview_metrics    Overview metrics.
	 * @param    array  $campaigns_data      Campaigns data.
	 * @param    string $current_period      Current period.
	 * @param    array  $full_metrics        Full metrics including discount and ROI data.
	 * @return   void
	 */
	private function render_dashboard( array $overview_metrics, array $campaigns_data, string $current_period, array $full_metrics = array() ): void {
		// Pass chart renderer and metrics to template
		$chart_renderer = $this->chart_renderer;
		$overview_panel = $this->overview_panel;

		// Include the dashboard template
		include WSSCD_PLUGIN_DIR . 'resources/views/admin/pages/dashboard.php';
	}

	/**
	 * Render upgrade prompt for free users.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function render_upgrade_prompt(): void {
		$upgrade_url = function_exists( 'wsscd_get_upgrade_url' ) ? wsscd_get_upgrade_url() : admin_url( 'admin.php?page=smart-cycle-discounts-pricing' );

		?>
		<div class="wrap wsscd-analytics-locked">
			<h1><?php esc_html_e( 'Analytics', 'smart-cycle-discounts' ); ?></h1>

			<div class="wsscd-upgrade-container">
				<div class="wsscd-upgrade-content">
					<?php
					WSSCD_Icon_Helper::render( 'chart-area', array( 'size' => 16, 'class' => 'wsscd-upgrade-icon' ) );
					?>
					<h2><?php esc_html_e( 'Unlock Advanced Analytics with Pro', 'smart-cycle-discounts' ); ?></h2>

					<p class="wsscd-upgrade-description">
						<?php esc_html_e( 'Get access to powerful analytics and insights to optimize your discount campaigns:', 'smart-cycle-discounts' ); ?>
					</p>

					<?php
										?>
					<ul class="wsscd-feature-list">
						<li><?php WSSCD_Icon_Helper::render( 'check', array( 'size' => 16 ) ); ?> <?php esc_html_e( 'Custom date ranges and flexible reporting', 'smart-cycle-discounts' ); ?></li>
						<li><?php WSSCD_Icon_Helper::render( 'check', array( 'size' => 16 ) ); ?> <?php esc_html_e( 'Export data to CSV and JSON formats', 'smart-cycle-discounts' ); ?></li>
						<li><?php WSSCD_Icon_Helper::render( 'check', array( 'size' => 16 ) ); ?> <?php esc_html_e( 'Advanced metrics and performance charts', 'smart-cycle-discounts' ); ?></li>
						<li><?php WSSCD_Icon_Helper::render( 'check', array( 'size' => 16 ) ); ?> <?php esc_html_e( 'Geographic sales breakdown', 'smart-cycle-discounts' ); ?></li>
						<li><?php WSSCD_Icon_Helper::render( 'check', array( 'size' => 16 ) ); ?> <?php esc_html_e( 'Traffic source analysis', 'smart-cycle-discounts' ); ?></li>
						<li><?php WSSCD_Icon_Helper::render( 'check', array( 'size' => 16 ) ); ?> <?php esc_html_e( 'Customer lifetime value calculations', 'smart-cycle-discounts' ); ?></li>
						<li><?php WSSCD_Icon_Helper::render( 'check', array( 'size' => 16 ) ); ?> <?php esc_html_e( 'Conversion funnel analysis', 'smart-cycle-discounts' ); ?></li>
						<li><?php WSSCD_Icon_Helper::render( 'check', array( 'size' => 16 ) ); ?> <?php esc_html_e( 'Priority support', 'smart-cycle-discounts' ); ?></li>
					</ul>
					<?php  ?>

					<div class="wsscd-upgrade-actions">
						<?php
						WSSCD_Button_Helper::primary(
							__( 'Upgrade to Pro', 'smart-cycle-discounts' ),
							array(
								'size' => 'hero',
								'href' => esc_url( $upgrade_url ),
							)
						);
						?>
					</div>

					<p class="wsscd-upgrade-note">
						<?php esc_html_e( '14-day money-back guarantee. No risk, cancel anytime.', 'smart-cycle-discounts' ); ?>
					</p>
				</div>

				<div class="wsscd-analytics-preview">
					<div class="wsscd-preview-placeholder">
						<div class="wsscd-preview-header">
							<div class="wsscd-preview-title"></div>
							<div class="wsscd-preview-actions"></div>
						</div>
						<div class="wsscd-preview-metrics">
							<div class="wsscd-preview-metric"></div>
							<div class="wsscd-preview-metric"></div>
							<div class="wsscd-preview-metric"></div>
							<div class="wsscd-preview-metric"></div>
						</div>
						<div class="wsscd-preview-chart"></div>
						<div class="wsscd-preview-table">
							<div class="wsscd-preview-row"></div>
							<div class="wsscd-preview-row"></div>
							<div class="wsscd-preview-row"></div>
						</div>
					</div>
					<div class="wsscd-preview-overlay">
						<?php
						WSSCD_Icon_Helper::render( 'lock', array( 'size' => 16 ) );
						?>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle AJAX requests for dashboard data.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function handle_ajax_requests(): void {
		// AJAX handlers moved to WSSCD_Analytics_Ajax class for better organization
		// This method kept for modern implementation but no longer registers handlers
	}
}
