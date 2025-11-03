<?php
/**
 * Analytics Page Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/pages/class-analytics-page.php
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


// Load standardized response handler
require_once SCD_PLUGIN_DIR . 'includes/admin/ajax/class-scd-ajax-response.php';

// Load AJAX security handler
require_once SCD_PLUGIN_DIR . 'includes/admin/ajax/class-ajax-security.php';

/**
 * Analytics Page Controller
 *
 * Handles the analytics dashboard page in the WordPress admin.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/pages
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Analytics_Page {

	/**
	 * Analytics collector instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Analytics_Collector    $analytics_collector    Analytics collector.
	 */
	private SCD_Analytics_Collector $analytics_collector;

	/**
	 * Metrics calculator instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Metrics_Calculator    $metrics_calculator    Metrics calculator.
	 */
	private SCD_Metrics_Calculator $metrics_calculator;

	/**
	 * Chart renderer instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Chart_Renderer    $chart_renderer    Chart renderer.
	 */
	private SCD_Chart_Renderer $chart_renderer;

	/**
	 * Logger instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Logger    $logger    Logger instance.
	 */
	private SCD_Logger $logger;

	/**
	 * Initialize the analytics page.
	 *
	 * @since    1.0.0
	 * @param    SCD_Analytics_Collector $analytics_collector    Analytics collector.
	 * @param    SCD_Metrics_Calculator  $metrics_calculator     Metrics calculator.
	 * @param    SCD_Chart_Renderer      $chart_renderer         Chart renderer.
	 * @param    SCD_Logger              $logger                 Logger instance.
	 */
	public function __construct(
		SCD_Analytics_Collector $analytics_collector,
		SCD_Metrics_Calculator $metrics_calculator,
		SCD_Chart_Renderer $chart_renderer,
		SCD_Logger $logger
	) {
		$this->analytics_collector = $analytics_collector;
		$this->metrics_calculator  = $metrics_calculator;
		$this->chart_renderer      = $chart_renderer;
		$this->logger              = $logger;
	}

	/**
	 * Render the analytics page.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function render(): void {
		// Check user capabilities
		if ( ! current_user_can( 'scd_view_analytics' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'smart-cycle-discounts' ) );
		}

		// Check if user has premium access
		$is_premium = function_exists( 'scd_is_premium' ) && scd_is_premium();

		// Show upgrade prompt for free users
		if ( ! $is_premium ) {
			$this->render_upgrade_prompt();
			return;
		}

		try {
			// Get current date range
			$date_range = $this->get_current_date_range();

			// Get overview metrics
			$overview_metrics = $this->get_overview_metrics( $date_range );

			// Get campaigns data
			$campaigns_data = $this->get_campaigns_data( $date_range );

			// Assets are handled by the centralized asset management system

			// Render the dashboard
			$this->render_dashboard( $overview_metrics, $campaigns_data, $date_range );

		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to render analytics page',
				array(
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString(),
				)
			);

			wp_die( __( 'Failed to load analytics dashboard.', 'smart-cycle-discounts' ) );
		}
	}

	/**
	 * Get current date range.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   string    Date range.
	 */
	private function get_current_date_range(): string {
		$date_range = sanitize_text_field( $_GET['date_range'] ?? '7days' );

		$valid_ranges = array( '24hours', '7days', '30days', '90days', 'custom' );

		if ( ! in_array( $date_range, $valid_ranges ) ) {
			$date_range = '7days';
		}

		return $date_range;
	}

	/**
	 * Get overview metrics.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $date_range    Date range.
	 * @return   array                    Overview metrics.
	 */
	private function get_overview_metrics( string $date_range ): array {
		try {
			$metrics = $this->metrics_calculator->calculate_overall_metrics( $date_range );

			return array(
				array(
					'title'       => __( 'Total Revenue', 'smart-cycle-discounts' ),
					'value'       => $metrics['total_revenue'] ?? 0,
					'change'      => $metrics['revenue_change'] ?? 0,
					'change_type' => $this->get_change_type( $metrics['revenue_change'] ?? 0 ),
					'icon'        => 'dashicons-money-alt',
					'format'      => 'currency',
					'description' => __( 'Revenue generated from discount campaigns', 'smart-cycle-discounts' ),
				),
				array(
					'title'       => __( 'Conversions', 'smart-cycle-discounts' ),
					'value'       => $metrics['total_conversions'] ?? 0,
					'change'      => $metrics['conversions_change'] ?? 0,
					'change_type' => $this->get_change_type( $metrics['conversions_change'] ?? 0 ),
					'icon'        => 'dashicons-cart',
					'format'      => 'number',
					'description' => __( 'Number of successful purchases', 'smart-cycle-discounts' ),
				),
				array(
					'title'       => __( 'Avg Order Value', 'smart-cycle-discounts' ),
					'value'       => $metrics['avg_order_value'] ?? 0,
					'change'      => $metrics['aov_change'] ?? 0,
					'change_type' => $this->get_change_type( $metrics['aov_change'] ?? 0 ),
					'icon'        => 'dashicons-chart-line',
					'format'      => 'currency',
					'description' => __( 'Average value per order with discount applied', 'smart-cycle-discounts' ),
				),
				array(
					'title'       => __( 'Click-through Rate', 'smart-cycle-discounts' ),
					'value'       => $metrics['avg_ctr'] ?? 0,
					'change'      => $metrics['ctr_change'] ?? 0,
					'change_type' => $this->get_change_type( $metrics['ctr_change'] ?? 0 ),
					'icon'        => 'dashicons-performance',
					'format'      => 'percentage',
					'description' => __( 'Percentage of clicks that led to purchases', 'smart-cycle-discounts' ),
				),
				array(
					'title'       => __( 'Active Campaigns', 'smart-cycle-discounts' ),
					'value'       => $metrics['active_campaigns'] ?? 0,
					'change'      => $metrics['campaigns_change'] ?? 0,
					'change_type' => $this->get_change_type( $metrics['campaigns_change'] ?? 0 ),
					'icon'        => 'dashicons-megaphone',
					'format'      => 'number',
					'description' => __( 'Currently running discount campaigns', 'smart-cycle-discounts' ),
				),
			);

		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to get overview metrics',
				array(
					'error'      => $e->getMessage(),
					'date_range' => $date_range,
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
	 * @return   void
	 */
	private function render_dashboard( array $overview_metrics, array $campaigns_data, string $current_period ): void {
		// Pass chart renderer to template
		$chart_renderer = $this->chart_renderer;

		// Include the dashboard template
		include SCD_PLUGIN_DIR . 'resources/views/admin/pages/dashboard.php';
	}

	/**
	 * Render upgrade prompt for free users.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function render_upgrade_prompt(): void {
		$upgrade_url = function_exists( 'scd_get_upgrade_url' ) ? scd_get_upgrade_url() : admin_url( 'admin.php?page=smart-cycle-discounts-pricing' );
		$trial_url   = function_exists( 'scd_get_trial_url' ) ? scd_get_trial_url() : $upgrade_url;

		?>
		<div class="wrap scd-analytics-locked">
			<h1><?php esc_html_e( 'Analytics', 'smart-cycle-discounts' ); ?></h1>

			<div class="scd-upgrade-container">
				<div class="scd-upgrade-content">
					<span class="dashicons dashicons-chart-area scd-upgrade-icon"></span>
					<h2><?php esc_html_e( 'Unlock Advanced Analytics with Pro', 'smart-cycle-discounts' ); ?></h2>

					<p class="scd-upgrade-description">
						<?php esc_html_e( 'Get access to powerful analytics and insights to optimize your discount campaigns:', 'smart-cycle-discounts' ); ?>
					</p>

					<ul class="scd-feature-list">
						<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Custom date ranges and flexible reporting', 'smart-cycle-discounts' ); ?></li>
						<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Export data to CSV and JSON formats', 'smart-cycle-discounts' ); ?></li>
						<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Advanced metrics and performance charts', 'smart-cycle-discounts' ); ?></li>
						<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Geographic sales breakdown', 'smart-cycle-discounts' ); ?></li>
						<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Traffic source analysis', 'smart-cycle-discounts' ); ?></li>
						<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Customer lifetime value calculations', 'smart-cycle-discounts' ); ?></li>
						<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Conversion funnel analysis', 'smart-cycle-discounts' ); ?></li>
						<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Priority support', 'smart-cycle-discounts' ); ?></li>
					</ul>

					<div class="scd-upgrade-actions">
						<a href="<?php echo esc_url( $upgrade_url ); ?>" class="button button-primary button-hero">
							<?php esc_html_e( 'Upgrade to Pro', 'smart-cycle-discounts' ); ?>
						</a>
						<a href="<?php echo esc_url( $trial_url ); ?>" class="button button-secondary button-hero">
							<?php esc_html_e( 'Start 14-Day Trial', 'smart-cycle-discounts' ); ?>
						</a>
					</div>

					<p class="scd-upgrade-note">
						<?php esc_html_e( '14-day money-back guarantee. No risk, cancel anytime.', 'smart-cycle-discounts' ); ?>
					</p>
				</div>

				<div class="scd-analytics-preview">
					<div class="scd-preview-placeholder">
						<div class="scd-preview-header">
							<div class="scd-preview-title"></div>
							<div class="scd-preview-actions"></div>
						</div>
						<div class="scd-preview-metrics">
							<div class="scd-preview-metric"></div>
							<div class="scd-preview-metric"></div>
							<div class="scd-preview-metric"></div>
							<div class="scd-preview-metric"></div>
						</div>
						<div class="scd-preview-chart"></div>
						<div class="scd-preview-table">
							<div class="scd-preview-row"></div>
							<div class="scd-preview-row"></div>
							<div class="scd-preview-row"></div>
						</div>
					</div>
					<div class="scd-preview-overlay">
						<span class="dashicons dashicons-lock"></span>
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
		// AJAX handlers moved to SCD_Analytics_Ajax class for better organization
		// This method kept for modern implementation but no longer registers handlers
	}
}
