<?php
/**
 * Analytics Controller Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/analytics/class-analytics-controller.php
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Analytics API Controller
 *
 * Handles all REST API operations for analytics data.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/api/controllers
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Analytics_Controller {

	/**
	 * API namespace.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $namespace    API namespace.
	 */
	private string $namespace;

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
	 * Permissions manager instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_API_Permissions    $permissions_manager    Permissions manager.
	 */
	private SCD_API_Permissions $permissions_manager;

	/**
	 * Logger instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Logger    $logger    Logger instance.
	 */
	private SCD_Logger $logger;

	/**
	 * Initialize the analytics endpoint.
	 *
	 * @since    1.0.0
	 * @param    string                  $namespace             API namespace.
	 * @param    SCD_Analytics_Collector $analytics_collector   Analytics collector.
	 * @param    SCD_Metrics_Calculator  $metrics_calculator    Metrics calculator.
	 * @param    SCD_API_Permissions     $permissions_manager   Permissions manager.
	 * @param    SCD_Logger              $logger                Logger instance.
	 */
	public function __construct(
		string $namespace,
		SCD_Analytics_Collector $analytics_collector,
		SCD_Metrics_Calculator $metrics_calculator,
		SCD_API_Permissions $permissions_manager,
		SCD_Logger $logger
	) {
		$this->namespace           = $namespace;
		$this->analytics_collector = $analytics_collector;
		$this->metrics_calculator  = $metrics_calculator;
		$this->permissions_manager = $permissions_manager;
		$this->logger              = $logger;
	}

	/**
	 * Register API routes.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function register_routes(): void {
		// Overview analytics
		register_rest_route(
			$this->namespace,
			'/analytics/overview',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_overview' ),
				'permission_callback' => array( $this->permissions_manager, 'check_analytics_permissions' ),
				'args'                => $this->get_date_range_params(),
			)
		);

		// Campaign analytics
		register_rest_route(
			$this->namespace,
			'/analytics/campaigns',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_campaigns_analytics' ),
				'permission_callback' => array( $this->permissions_manager, 'check_analytics_permissions' ),
				'args'                => array_merge(
					$this->get_date_range_params(),
					array(
						'campaign_id' => array(
							'description'       => __( 'Specific campaign ID to analyze.', 'smart-cycle-discounts' ),
							'type'              => 'integer',
							'validate_callback' => function ( $param ) {
								return is_numeric( $param ) && $param > 0;
							},
						),
					)
				),
			)
		);

		// Product analytics
		register_rest_route(
			$this->namespace,
			'/analytics/products',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_products_analytics' ),
				'permission_callback' => array( $this->permissions_manager, 'check_analytics_permissions' ),
				'args'                => array_merge(
					$this->get_date_range_params(),
					array(
						'product_id' => array(
							'description'       => __( 'Specific product ID to analyze.', 'smart-cycle-discounts' ),
							'type'              => 'integer',
							'validate_callback' => function ( $param ) {
								return is_numeric( $param ) && $param > 0;
							},
						),
					)
				),
			)
		);

		// Real-time metrics
		register_rest_route(
			$this->namespace,
			'/analytics/realtime',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_realtime_metrics' ),
				'permission_callback' => array( $this->permissions_manager, 'check_analytics_permissions' ),
			)
		);

		// Chart data
		register_rest_route(
			$this->namespace,
			'/analytics/charts/(?P<type>revenue|conversions|clicks|views)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_chart_data' ),
				'permission_callback' => array( $this->permissions_manager, 'check_analytics_permissions' ),
				'args'                => array_merge(
					$this->get_date_range_params(),
					array(
						'type'        => array(
							'description' => __( 'Chart data type.', 'smart-cycle-discounts' ),
							'type'        => 'string',
							'enum'        => array( 'revenue', 'conversions', 'clicks', 'views' ),
							'required'    => true,
						),
						'granularity' => array(
							'description' => __( 'Data granularity.', 'smart-cycle-discounts' ),
							'type'        => 'string',
							'enum'        => array( 'hourly', 'daily', 'weekly', 'monthly' ),
							'default'     => 'daily',
						),
					)
				),
			)
		);

		// Top performers
		register_rest_route(
			$this->namespace,
			'/analytics/top/(?P<entity>campaigns|products)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_top_performers' ),
				'permission_callback' => array( $this->permissions_manager, 'check_analytics_permissions' ),
				'args'                => array_merge(
					$this->get_date_range_params(),
					array(
						'entity' => array(
							'description' => __( 'Entity type for top performers.', 'smart-cycle-discounts' ),
							'type'        => 'string',
							'enum'        => array( 'campaigns', 'products' ),
							'required'    => true,
						),
						'metric' => array(
							'description' => __( 'Metric to rank by.', 'smart-cycle-discounts' ),
							'type'        => 'string',
							'enum'        => array( 'revenue', 'conversions', 'clicks', 'views' ),
							'default'     => 'revenue',
						),
						'limit'  => array(
							'description' => __( 'Number of top performers to return.', 'smart-cycle-discounts' ),
							'type'        => 'integer',
							'default'     => 10,
							'minimum'     => 1,
							'maximum'     => 50,
						),
					)
				),
			)
		);

		// Export data
		register_rest_route(
			$this->namespace,
			'/analytics/export',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'export_analytics' ),
				'permission_callback' => array( $this->permissions_manager, 'check_analytics_permissions' ),
				'args'                => array_merge(
					$this->get_date_range_params(),
					array(
						'format'    => array(
							'description' => __( 'Export format.', 'smart-cycle-discounts' ),
							'type'        => 'string',
							'enum'        => array( 'csv', 'json' ),
							'default'     => 'csv',
						),
						'data_type' => array(
							'description' => __( 'Type of data to export.', 'smart-cycle-discounts' ),
							'type'        => 'string',
							'enum'        => array( 'overview', 'campaigns', 'products', 'events' ),
							'default'     => 'overview',
						),
					)
				),
			)
		);

		$this->logger->debug( 'Analytics API routes registered' );
	}

	/**
	 * Get overview analytics.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request $request    Request object.
	 * @return   WP_REST_Response               Response object.
	 */
	public function get_overview( WP_REST_Request $request ): WP_REST_Response {
		try {
			$date_range = $this->prepare_date_range( $request );
			$metrics    = $this->metrics_calculator->calculate_overall_metrics( $date_range );

			$data = array(
				'period'  => $date_range,
				'metrics' => $metrics,
				'summary' => array(
					'total_revenue'          => $metrics['total_revenue'] ?? 0,
					'total_conversions'      => $metrics['total_conversions'] ?? 0,
					'total_clicks'           => $metrics['total_clicks'] ?? 0,
					'total_views'            => $metrics['total_views'] ?? 0,
					'avg_conversion_rate'    => $metrics['avg_conversion_rate'] ?? 0,
					'avg_click_through_rate' => $metrics['avg_click_through_rate'] ?? 0,
				),
				'trends'  => $this->calculate_trends( $metrics, $date_range ),
			);

			$this->logger->debug(
				'Overview analytics retrieved via API',
				array(
					'date_range' => $date_range,
					'revenue'    => $data['summary']['total_revenue'],
				)
			);

			return new WP_REST_Response( $data, 200 );

		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to get overview analytics via API',
				array(
					'error'  => $e->getMessage(),
					'params' => $request->get_params(),
				)
			);

			return new WP_REST_Response(
				array(
					'code'    => 'analytics_overview_error',
					'message' => __( 'Failed to retrieve overview analytics.', 'smart-cycle-discounts' ),
					'data'    => array( 'status' => 500 ),
				),
				500
			);
		}
	}

	/**
	 * Get campaigns analytics.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request $request    Request object.
	 * @return   WP_REST_Response               Response object.
	 */
	public function get_campaigns_analytics( WP_REST_Request $request ): WP_REST_Response {
		try {
			$date_range  = $this->prepare_date_range( $request );
			$campaign_id = $request->get_param( 'campaign_id' );

			if ( $campaign_id ) {
				$metrics = $this->metrics_calculator->calculate_campaign_metrics( $campaign_id, $date_range );
				$data    = array(
					'campaign_id' => $campaign_id,
					'period'      => $date_range,
					'metrics'     => $metrics,
				);
			} else {
				$metrics = $this->metrics_calculator->calculate_all_campaigns_metrics( $date_range );
				$data    = array(
					'period'    => $date_range,
					'campaigns' => $metrics,
					'summary'   => $this->summarize_campaigns_metrics( $metrics ),
				);
			}

			$this->logger->debug(
				'Campaigns analytics retrieved via API',
				array(
					'campaign_id'     => $campaign_id,
					'date_range'      => $date_range,
					'campaigns_count' => $campaign_id ? 1 : count( $metrics ),
				)
			);

			return new WP_REST_Response( $data, 200 );

		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to get campaigns analytics via API',
				array(
					'error'       => $e->getMessage(),
					'campaign_id' => $campaign_id ?? null,
					'params'      => $request->get_params(),
				)
			);

			return new WP_REST_Response(
				array(
					'code'    => 'analytics_campaigns_error',
					'message' => __( 'Failed to retrieve campaigns analytics.', 'smart-cycle-discounts' ),
					'data'    => array( 'status' => 500 ),
				),
				500
			);
		}
	}

	/**
	 * Get products analytics.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request $request    Request object.
	 * @return   WP_REST_Response               Response object.
	 */
	public function get_products_analytics( WP_REST_Request $request ): WP_REST_Response {
		try {
			$date_range = $this->prepare_date_range( $request );
			$product_id = $request->get_param( 'product_id' );

			if ( $product_id ) {
				$metrics = $this->metrics_calculator->calculate_product_metrics( $product_id, $date_range );
				$data    = array(
					'product_id' => $product_id,
					'period'     => $date_range,
					'metrics'    => $metrics,
				);
			} else {
				$metrics = $this->metrics_calculator->calculate_all_products_metrics( $date_range );
				$data    = array(
					'period'   => $date_range,
					'products' => $metrics,
					'summary'  => $this->summarize_products_metrics( $metrics ),
				);
			}

			$this->logger->debug(
				'Products analytics retrieved via API',
				array(
					'product_id'     => $product_id,
					'date_range'     => $date_range,
					'products_count' => $product_id ? 1 : count( $metrics ),
				)
			);

			return new WP_REST_Response( $data, 200 );

		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to get products analytics via API',
				array(
					'error'      => $e->getMessage(),
					'product_id' => $product_id ?? null,
					'params'     => $request->get_params(),
				)
			);

			return new WP_REST_Response(
				array(
					'code'    => 'analytics_products_error',
					'message' => __( 'Failed to retrieve products analytics.', 'smart-cycle-discounts' ),
					'data'    => array( 'status' => 500 ),
				),
				500
			);
		}
	}

	/**
	 * Get real-time metrics.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request $request    Request object.
	 * @return   WP_REST_Response               Response object.
	 */
	public function get_realtime_metrics( WP_REST_Request $request ): WP_REST_Response {
		try {
			$metrics = $this->metrics_calculator->calculate_realtime_metrics();

			$data = array(
				'timestamp'          => current_time( 'timestamp' ),
				'metrics'            => $metrics,
				'active_campaigns'   => $this->get_active_campaigns_count(),
				'current_hour_stats' => $this->get_current_hour_stats(),
			);

			$this->logger->debug( 'Real-time metrics retrieved via API' );

			return new WP_REST_Response( $data, 200 );

		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to get real-time metrics via API',
				array(
					'error' => $e->getMessage(),
				)
			);

			return new WP_REST_Response(
				array(
					'code'    => 'analytics_realtime_error',
					'message' => __( 'Failed to retrieve real-time metrics.', 'smart-cycle-discounts' ),
					'data'    => array( 'status' => 500 ),
				),
				500
			);
		}
	}

	/**
	 * Get chart data.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request $request    Request object.
	 * @return   WP_REST_Response               Response object.
	 */
	public function get_chart_data( WP_REST_Request $request ): WP_REST_Response {
		try {
			$type        = $request['type'];
			$granularity = $request->get_param( 'granularity' ) ?: 'daily';
			$date_range  = $this->prepare_date_range( $request );

			$chart_data = $this->metrics_calculator->get_chart_data( $type, $granularity, $date_range );

			$data = array(
				'type'        => $type,
				'granularity' => $granularity,
				'period'      => $date_range,
				'data'        => $chart_data,
				'labels'      => $this->generate_chart_labels( $granularity, $date_range ),
				'metadata'    => array(
					'total_points' => count( $chart_data ),
					'max_value'    => max( $chart_data ),
					'min_value'    => min( $chart_data ),
					'average'      => array_sum( $chart_data ) / count( $chart_data ),
				),
			);

			$this->logger->debug(
				'Chart data retrieved via API',
				array(
					'type'        => $type,
					'granularity' => $granularity,
					'points'      => count( $chart_data ),
				)
			);

			return new WP_REST_Response( $data, 200 );

		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to get chart data via API',
				array(
					'error'  => $e->getMessage(),
					'type'   => $type ?? 'unknown',
					'params' => $request->get_params(),
				)
			);

			return new WP_REST_Response(
				array(
					'code'    => 'analytics_chart_error',
					'message' => __( 'Failed to retrieve chart data.', 'smart-cycle-discounts' ),
					'data'    => array( 'status' => 500 ),
				),
				500
			);
		}
	}

	/**
	 * Get top performers.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request $request    Request object.
	 * @return   WP_REST_Response               Response object.
	 */
	public function get_top_performers( WP_REST_Request $request ): WP_REST_Response {
		try {
			$entity     = $request['entity'];
			$metric     = $request->get_param( 'metric' ) ?: 'revenue';
			$limit      = (int) ( $request->get_param( 'limit' ) ?: 10 );
			$date_range = $this->prepare_date_range( $request );

			$performers = $this->metrics_calculator->get_top_performers( $entity, $metric, $limit, $date_range );

			$data = array(
				'entity'      => $entity,
				'metric'      => $metric,
				'limit'       => $limit,
				'period'      => $date_range,
				'performers'  => $performers,
				'total_found' => count( $performers ),
			);

			$this->logger->debug(
				'Top performers retrieved via API',
				array(
					'entity' => $entity,
					'metric' => $metric,
					'count'  => count( $performers ),
				)
			);

			return new WP_REST_Response( $data, 200 );

		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to get top performers via API',
				array(
					'error'  => $e->getMessage(),
					'entity' => $entity ?? 'unknown',
					'params' => $request->get_params(),
				)
			);

			return new WP_REST_Response(
				array(
					'code'    => 'analytics_top_performers_error',
					'message' => __( 'Failed to retrieve top performers.', 'smart-cycle-discounts' ),
					'data'    => array( 'status' => 500 ),
				),
				500
			);
		}
	}

	/**
	 * Export analytics data.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request $request    Request object.
	 * @return   WP_REST_Response               Response object.
	 */
	public function export_analytics( WP_REST_Request $request ): WP_REST_Response {
		try {
			$format     = $request->get_param( 'format' ) ?: 'csv';
			$data_type  = $request->get_param( 'data_type' ) ?: 'overview';
			$date_range = $this->prepare_date_range( $request );

			// Get data based on type
			$export_data = match ( $data_type ) {
				'overview' => $this->metrics_calculator->calculate_overall_metrics( $date_range ),
				'campaigns' => $this->metrics_calculator->calculate_all_campaigns_metrics( $date_range ),
				'products' => $this->metrics_calculator->calculate_all_products_metrics( $date_range ),
				'events' => $this->analytics_collector->get_events( $date_range ),
				default => array()
			};

			// Generate export file
			$export_result = $this->generate_export_file( $export_data, $format, $data_type, $date_range );

			if ( ! $export_result['success'] ) {
				return new WP_REST_Response(
					array(
						'code'    => 'export_generation_failed',
						'message' => $export_result['message'],
						'data'    => array( 'status' => 500 ),
					),
					500
				);
			}

			$data = array(
				'format'        => $format,
				'data_type'     => $data_type,
				'period'        => $date_range,
				'file_url'      => $export_result['file_url'],
				'file_size'     => $export_result['file_size'],
				'records_count' => $export_result['records_count'],
				'generated_at'  => current_time( 'mysql' ),
			);

			$this->logger->info(
				'Analytics data exported via API',
				array(
					'format'    => $format,
					'data_type' => $data_type,
					'records'   => $export_result['records_count'],
				)
			);

			return new WP_REST_Response( $data, 200 );

		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to export analytics via API',
				array(
					'error'  => $e->getMessage(),
					'params' => $request->get_params(),
				)
			);

			return new WP_REST_Response(
				array(
					'code'    => 'analytics_export_error',
					'message' => __( 'Failed to export analytics data.', 'smart-cycle-discounts' ),
					'data'    => array( 'status' => 500 ),
				),
				500
			);
		}
	}

	/**
	 * Prepare date range from request.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    WP_REST_Request $request    Request object.
	 * @return   string                         Date range.
	 */
	private function prepare_date_range( WP_REST_Request $request ): string {
		$date_range = $request->get_param( 'date_range' );

		if ( $date_range && in_array( $date_range, array( '24hours', '7days', '30days', '90days' ) ) ) {
			return $date_range;
		}

		// Custom date range
		$start_date = $request->get_param( 'start_date' );
		$end_date   = $request->get_param( 'end_date' );

		if ( $start_date && $end_date ) {
			return "custom:{$start_date}:{$end_date}";
		}

		return '7days'; // Default
	}

	/**
	 * Calculate trends for metrics.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array  $metrics      Current metrics.
	 * @param    string $date_range   Date range.
	 * @return   array                   Trend data.
	 */
	private function calculate_trends( array $metrics, string $date_range ): array {
		// This would calculate percentage changes compared to previous period
		// For now, return placeholder data
		return array(
			'revenue_change'     => '+12.5%',
			'conversions_change' => '+8.3%',
			'clicks_change'      => '+15.7%',
			'views_change'       => '+5.2%',
		);
	}

	/**
	 * Summarize campaigns metrics.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $campaigns_metrics    Campaigns metrics.
	 * @return   array                          Summary data.
	 */
	private function summarize_campaigns_metrics( array $campaigns_metrics ): array {
		$total_revenue     = 0;
		$total_conversions = 0;
		$total_clicks      = 0;
		$total_views       = 0;

		foreach ( $campaigns_metrics as $metrics ) {
			$total_revenue     += $metrics['revenue'] ?? 0;
			$total_conversions += $metrics['conversions'] ?? 0;
			$total_clicks      += $metrics['clicks'] ?? 0;
			$total_views       += $metrics['views'] ?? 0;
		}

		return array(
			'total_campaigns'          => count( $campaigns_metrics ),
			'total_revenue'            => $total_revenue,
			'total_conversions'        => $total_conversions,
			'total_clicks'             => $total_clicks,
			'total_views'              => $total_views,
			'avg_revenue_per_campaign' => count( $campaigns_metrics ) > 0 ? $total_revenue / count( $campaigns_metrics ) : 0,
		);
	}

	/**
	 * Summarize products metrics.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $products_metrics    Products metrics.
	 * @return   array                         Summary data.
	 */
	private function summarize_products_metrics( array $products_metrics ): array {
		$total_revenue     = 0;
		$total_conversions = 0;

		foreach ( $products_metrics as $metrics ) {
			$total_revenue     += $metrics['revenue'] ?? 0;
			$total_conversions += $metrics['conversions'] ?? 0;
		}

		return array(
			'total_products'          => count( $products_metrics ),
			'total_revenue'           => $total_revenue,
			'total_conversions'       => $total_conversions,
			'avg_revenue_per_product' => count( $products_metrics ) > 0 ? $total_revenue / count( $products_metrics ) : 0,
		);
	}

	/**
	 * Get active campaigns count.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   int    Active campaigns count.
	 */
	private function get_active_campaigns_count(): int {
		// This would query the database for active campaigns
		return 0; // Placeholder
	}

	/**
	 * Get current hour statistics.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   array    Current hour stats.
	 */
	private function get_current_hour_stats(): array {
		// This would get statistics for the current hour
		return array(
			'views'       => 0,
			'clicks'      => 0,
			'conversions' => 0,
			'revenue'     => 0.0,
		);
	}

	/**
	 * Generate chart labels.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $granularity    Chart granularity.
	 * @param    string $date_range     Date range.
	 * @return   array                     Chart labels.
	 */
	private function generate_chart_labels( string $granularity, string $date_range ): array {
		// This would generate appropriate labels based on granularity and date range
		return array(); // Placeholder
	}

	/**
	 * Generate export file.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array  $data          Export data.
	 * @param    string $format        Export format.
	 * @param    string $data_type     Data type.
	 * @param    string $date_range    Date range.
	 * @return   array                    Export result.
	 */
	private function generate_export_file( array $data, string $format, string $data_type, string $date_range ): array {
		// This would generate the actual export file
		return array(
			'success'       => true,
			'file_url'      => '',
			'file_size'     => 0,
			'records_count' => count( $data ),
			'message'       => '',
		);
	}

	/**
	 * Get date range parameters schema.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   array    Date range parameters.
	 */
	private function get_date_range_params(): array {
		return array(
			'date_range' => array(
				'description' => __( 'Predefined date range.', 'smart-cycle-discounts' ),
				'type'        => 'string',
				'enum'        => array( '24hours', '7days', '30days', '90days' ),
				'default'     => '7days',
			),
			'start_date' => array(
				'description' => __( 'Custom start date (YYYY-MM-DD).', 'smart-cycle-discounts' ),
				'type'        => 'string',
				'format'      => 'date',
			),
			'end_date'   => array(
				'description' => __( 'Custom end date (YYYY-MM-DD).', 'smart-cycle-discounts' ),
				'type'        => 'string',
				'format'      => 'date',
			),
		);
	}

	/**
	 * Get endpoint information.
	 *
	 * @since    1.0.0
	 * @return   array    Endpoint information.
	 */
	public function get_endpoint_info(): array {
		return array(
			'name'         => 'Analytics',
			'description'  => __( 'Access analytics and reporting data', 'smart-cycle-discounts' ),
			'routes'       => array(
				'GET /analytics/overview'      => __( 'Get overview analytics', 'smart-cycle-discounts' ),
				'GET /analytics/campaigns'     => __( 'Get campaigns analytics', 'smart-cycle-discounts' ),
				'GET /analytics/products'      => __( 'Get products analytics', 'smart-cycle-discounts' ),
				'GET /analytics/realtime'      => __( 'Get real-time metrics', 'smart-cycle-discounts' ),
				'GET /analytics/charts/{type}' => __( 'Get chart data', 'smart-cycle-discounts' ),
				'GET /analytics/top/{entity}'  => __( 'Get top performers', 'smart-cycle-discounts' ),
				'POST /analytics/export'       => __( 'Export analytics data', 'smart-cycle-discounts' ),
			),
			'capabilities' => array(
				'view_analytics',
				'manage_analytics',
			),
		);
	}
}
