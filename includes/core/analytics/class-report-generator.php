<?php
/**
 * Report Generator Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/analytics/class-report-generator.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Report Generator Class
 *
 * Handles generation of various analytics reports in multiple formats.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/analytics
 * @author     Webstepper <contact@webstepper.io>
 */
class SCD_Report_Generator {

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
	 * Campaign manager instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Campaign_Manager    $campaign_manager    Campaign manager.
	 */
	private SCD_Campaign_Manager $campaign_manager;

	/**
	 * Cache manager instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Cache_Manager    $cache_manager    Cache manager.
	 */
	private SCD_Cache_Manager $cache_manager;

	/**
	 * Logger instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Logger    $logger    Logger instance.
	 */
	private SCD_Logger $logger;

	/**
	 * Supported export formats.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $supported_formats    Supported formats.
	 */
	private array $supported_formats = array( 'csv', 'json', 'pdf', 'excel' );

	/**
	 * Initialize the report generator.
	 *
	 * @since    1.0.0
	 * @param    SCD_Analytics_Collector $analytics_collector    Analytics collector.
	 * @param    SCD_Metrics_Calculator  $metrics_calculator     Metrics calculator.
	 * @param    SCD_Campaign_Manager    $campaign_manager       Campaign manager.
	 * @param    SCD_Cache_Manager       $cache_manager          Cache manager.
	 * @param    SCD_Logger              $logger                 Logger instance.
	 */
	public function __construct(
		SCD_Analytics_Collector $analytics_collector,
		SCD_Metrics_Calculator $metrics_calculator,
		SCD_Campaign_Manager $campaign_manager,
		SCD_Cache_Manager $cache_manager,
		SCD_Logger $logger
	) {
		$this->analytics_collector = $analytics_collector;
		$this->metrics_calculator  = $metrics_calculator;
		$this->campaign_manager    = $campaign_manager;
		$this->cache_manager       = $cache_manager;
		$this->logger              = $logger;
	}

	/**
	 * Generate a report.
	 *
	 * @since    1.0.0
	 * @param    string $report_type    Type of report to generate.
	 * @param    array  $options        Report options.
	 * @return   array                     Report generation result.
	 */
	public function generate_report( string $report_type, array $options = array() ): array {
		try {
			$this->logger->info(
				'Starting report generation',
				array(
					'report_type' => $report_type,
					'options'     => $options,
				)
			);

			if ( ! $this->is_valid_report_type( $report_type ) ) {
				throw new InvalidArgumentException( "Invalid report type: {$report_type}" );
			}

			$options = $this->sanitize_report_options( $options );

			$cache_key     = $this->get_cache_key( $report_type, $options );
			$cached_result = $this->cache_manager->get( $cache_key );

			if ( null !== $cached_result && ! ( $options['force_refresh'] ?? false ) ) {
				$this->logger->debug( 'Returning cached report', array( 'cache_key' => $cache_key ) );
				return $cached_result;
			}

			// Generate report data
			$report_data = $this->collect_report_data( $report_type, $options );

			// Generate report in requested format
			$format      = $options['format'] ?? 'json';
			$file_result = $this->generate_report_file( $report_data, $format, $options );

			$result = array(
				'success'       => true,
				'report_type'   => $report_type,
				'format'        => $format,
				'file_path'     => $file_result['file_path'],
				'file_url'      => $file_result['file_url'],
				'file_size'     => $file_result['file_size'],
				'records_count' => count( $report_data['data'] ?? array() ),
				'generated_at'  => current_time( 'mysql' ),
				'expires_at'    => date( 'Y-m-d H:i:s', time() + ( 24 * HOUR_IN_SECONDS ) ),
				'metadata'      => $report_data['metadata'] ?? array(),
			);

			$this->cache_manager->set( $cache_key, $result, HOUR_IN_SECONDS );

			$this->logger->info(
				'Report generated successfully',
				array(
					'report_type'   => $report_type,
					'format'        => $format,
					'records_count' => $result['records_count'],
				)
			);

			return $result;

		} catch ( Exception $e ) {
			$this->logger->error(
				'Report generation failed',
				array(
					'report_type' => $report_type,
					'error'       => $e->getMessage(),
					'trace'       => $e->getTraceAsString(),
				)
			);

			return array(
				'success'     => false,
				'error'       => $e->getMessage(),
				'report_type' => $report_type,
			);
		}
	}

	/**
	 * Generate campaign performance report.
	 *
	 * @since    1.0.0
	 * @param    array $options    Report options.
	 * @return   array               Report data.
	 */
	public function generate_campaign_performance_report( array $options = array() ): array {
		$date_range   = $options['date_range'] ?? '30days';
		$campaign_ids = $options['campaign_ids'] ?? array();

		$campaigns = empty( $campaign_ids )
			? $this->campaign_manager->get_campaigns( array( 'status' => 'active' ) )
			: $this->campaign_manager->get_campaigns_by_ids( $campaign_ids );

		$report_data       = array();
		$total_revenue     = 0;
		$total_conversions = 0;
		$total_clicks      = 0;
		$total_views       = 0;

		foreach ( $campaigns as $campaign ) {
			$metrics = $this->metrics_calculator->calculate_campaign_metrics( $campaign->get_id(), $date_range );

			$campaign_data = array(
				'campaign_id'         => $campaign->get_id(),
				'campaign_name'       => $campaign->get_name(),
				'status'              => $campaign->get_status(),
				'start_date'          => $campaign->get_starts_at(),
				'end_date'            => $campaign->get_ends_at(),
				'revenue'             => $metrics['revenue'] ?? 0,
				'conversions'         => $metrics['conversions'] ?? 0,
				'clicks'              => $metrics['clicks'] ?? 0,
				'views'               => $metrics['views'] ?? 0,
				'click_through_rate'  => $metrics['click_through_rate'] ?? 0,
				'conversion_rate'     => $metrics['conversion_rate'] ?? 0,
				'average_order_value' => $metrics['average_order_value'] ?? 0,
				'roi'                 => $metrics['roi'] ?? 0,
				'discount_given'      => $metrics['discount_given'] ?? 0,
				'profit_margin'       => $metrics['profit_margin'] ?? 0,
			);

			$report_data[] = $campaign_data;

			// Accumulate totals
			$total_revenue     += $campaign_data['revenue'];
			$total_conversions += $campaign_data['conversions'];
			$total_clicks      += $campaign_data['clicks'];
			$total_views       += $campaign_data['views'];
		}

		return array(
			'data'     => $report_data,
			'metadata' => array(
				'report_type'     => 'campaign_performance',
				'date_range'      => $date_range,
				'total_campaigns' => count( $campaigns ),
				'summary'         => array(
					'total_revenue'           => $total_revenue,
					'total_conversions'       => $total_conversions,
					'total_clicks'            => $total_clicks,
					'total_views'             => $total_views,
					'overall_ctr'             => $total_views > 0 ? ( $total_clicks / $total_views ) * 100 : 0,
					'overall_conversion_rate' => $total_clicks > 0 ? ( $total_conversions / $total_clicks ) * 100 : 0,
					'average_aov'             => $total_conversions > 0 ? $total_revenue / $total_conversions : 0,
				),
			),
		);
	}

	/**
	 * Generate analytics overview report.
	 *
	 * @since    1.0.0
	 * @param    array $options    Report options.
	 * @return   array               Report data.
	 */
	public function generate_analytics_overview_report( array $options = array() ): array {
		$date_range = $options['date_range'] ?? '30days';

		$overall_metrics = $this->metrics_calculator->calculate_overall_metrics( $date_range );

		$daily_metrics = $this->analytics_collector->get_daily_metrics( $date_range );

		$top_campaigns = $this->metrics_calculator->get_top_campaigns( $date_range, 10 );

		$conversion_funnel = $this->metrics_calculator->get_conversion_funnel( $date_range );

		return array(
			'data'     => array(
				'overall_metrics'   => $overall_metrics,
				'daily_breakdown'   => $daily_metrics,
				'top_campaigns'     => $top_campaigns,
				'conversion_funnel' => $conversion_funnel,
			),
			'metadata' => array(
				'report_type'          => 'analytics_overview',
				'date_range'           => $date_range,
				'generated_for_period' => $this->get_date_range_description( $date_range ),
			),
		);
	}

	/**
	 * Generate revenue trend report.
	 *
	 * @since    1.0.0
	 * @param    array $options    Report options.
	 * @return   array               Report data.
	 */
	public function generate_revenue_trend_report( array $options = array() ): array {
		$date_range  = $options['date_range'] ?? '30days';
		$granularity = $options['granularity'] ?? 'daily'; // daily, weekly, monthly

		$trend_data = $this->metrics_calculator->get_revenue_trend( $date_range, $granularity );

		return array(
			'data'     => $trend_data,
			'metadata' => array(
				'report_type' => 'revenue_trend',
				'date_range'  => $date_range,
				'granularity' => $granularity,
				'data_points' => count( $trend_data['values'] ?? array() ),
			),
		);
	}

	/**
	 * Generate custom report.
	 *
	 * @since    1.0.0
	 * @param    array $options    Report options.
	 * @return   array               Report data.
	 */
	public function generate_custom_report( array $options = array() ): array {
		$metrics    = $options['metrics'] ?? array( 'revenue', 'conversions' );
		$date_range = $options['date_range'] ?? '30days';
		$filters    = $options['filters'] ?? array();

		$report_data = array();

		// Apply filters and collect data based on selected metrics
		foreach ( $metrics as $metric ) {
			$data                   = $this->collect_metric_data( $metric, $date_range, $filters );
			$report_data[ $metric ] = $data;
		}

		return array(
			'data'     => $report_data,
			'metadata' => array(
				'report_type' => 'custom',
				'metrics'     => $metrics,
				'date_range'  => $date_range,
				'filters'     => $filters,
			),
		);
	}

	/**
	 * Schedule a report for automatic generation.
	 *
	 * @since    1.0.0
	 * @param    string $report_type    Type of report.
	 * @param    array  $options        Report options.
	 * @param    string $schedule       Schedule frequency.
	 * @param    array  $recipients     Email recipients.
	 * @return   bool                      Success status.
	 */
	public function schedule_report( string $report_type, array $options, string $schedule, array $recipients ): bool {
		try {
			$scheduled_report = array(
				'report_type' => $report_type,
				'options'     => $options,
				'schedule'    => $schedule,
				'recipients'  => $recipients,
				'created_at'  => current_time( 'mysql' ),
				'next_run'    => $this->calculate_next_run_time( $schedule ),
				'status'      => 'active',
			);

			$schedule_id = 'scd_scheduled_report_' . uniqid();
			$this->cache_manager->set( $schedule_id, $scheduled_report, YEAR_IN_SECONDS );

			// Schedule the first run
			wp_schedule_single_event(
				strtotime( $scheduled_report['next_run'] ),
				'scd_generate_scheduled_report',
				array( $schedule_id )
			);

			$this->logger->info(
				'Report scheduled successfully',
				array(
					'schedule_id' => $schedule_id,
					'report_type' => $report_type,
					'schedule'    => $schedule,
				)
			);

			return true;

		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to schedule report',
				array(
					'report_type' => $report_type,
					'error'       => $e->getMessage(),
				)
			);

			return false;
		}
	}

	/**
	 * Collect report data based on type.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $report_type    Report type.
	 * @param    array  $options        Options.
	 * @return   array                     Report data.
	 */
	private function collect_report_data( string $report_type, array $options ): array {
		switch ( $report_type ) {
			case 'campaign_performance':
				return $this->generate_campaign_performance_report( $options );

			case 'analytics_overview':
				return $this->generate_analytics_overview_report( $options );

			case 'revenue_trend':
				return $this->generate_revenue_trend_report( $options );

			case 'custom':
				return $this->generate_custom_report( $options );

			default:
				throw new InvalidArgumentException( "Unsupported report type: {$report_type}" );
		}
	}

	/**
	 * Generate report file in specified format.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array  $report_data    Report data.
	 * @param    string $format         Export format.
	 * @param    array  $options        Options.
	 * @return   array                     File generation result.
	 */
	private function generate_report_file( array $report_data, string $format, array $options ): array {
		$upload_dir  = wp_upload_dir();
		$reports_dir = $upload_dir['basedir'] . '/scd-reports';

		if ( ! file_exists( $reports_dir ) ) {
			wp_mkdir_p( $reports_dir );
		}

		$filename  = $this->generate_filename( $report_data['metadata']['report_type'] ?? 'report', $format );
		$file_path = $reports_dir . DIRECTORY_SEPARATOR . $filename;
		$file_url  = $upload_dir['baseurl'] . '/scd-reports/' . $filename;

		switch ( $format ) {
			case 'csv':
				$this->generate_csv_file( $report_data, $file_path );
				break;

			case 'json':
				$this->generate_json_file( $report_data, $file_path );
				break;

			case 'pdf':
				$this->generate_pdf_file( $report_data, $file_path, $options );
				break;

			case 'excel':
				$this->generate_excel_file( $report_data, $file_path );
				break;

			default:
				throw new InvalidArgumentException( "Unsupported format: {$format}" );
		}

		return array(
			'file_path' => $file_path,
			'file_url'  => $file_url,
			'file_size' => $this->format_file_size( filesize( $file_path ) ),
		);
	}

	/**
	 * Generate CSV file.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array  $report_data    Report data.
	 * @param    string $file_path      File path.
	 * @return   void
	 */
	private function generate_csv_file( array $report_data, string $file_path ): void {
		$handle = fopen( $file_path, 'w' );

		if ( false === $handle ) {
			throw new RuntimeException( "Cannot create CSV file: {$file_path}" );
		}

		$data = $report_data['data'];

		// Handle different data structures
		if ( isset( $data[0] ) && is_array( $data[0] ) ) {
			// Array of records
			if ( ! empty( $data ) ) {
				// Write headers
				fputcsv( $handle, array_keys( $data[0] ) );

				// Write data rows
				foreach ( $data as $row ) {
					fputcsv( $handle, $row );
				}
			}
		} else {
			// Complex data structure - flatten it
			$flattened = $this->flatten_array( $data );
			fputcsv( $handle, array( 'Key', 'Value' ) );

			foreach ( $flattened as $key => $value ) {
				fputcsv( $handle, array( $key, $value ) );
			}
		}

		fclose( $handle );
	}

	/**
	 * Generate JSON file.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array  $report_data    Report data.
	 * @param    string $file_path      File path.
	 * @return   void
	 */
	private function generate_json_file( array $report_data, string $file_path ): void {
		$json_data = wp_json_encode( $report_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );

		if ( false === $json_data ) {
			throw new RuntimeException( 'Failed to encode report data as JSON' );
		}

		if ( false === file_put_contents( $file_path, $json_data ) ) {
			throw new RuntimeException( "Cannot write JSON file: {$file_path}" );
		}
	}

	/**
	 * Generate PDF file.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array  $report_data    Report data.
	 * @param    string $file_path      File path.
	 * @param    array  $options        Options.
	 * @return   void
	 */
	private function generate_pdf_file( array $report_data, string $file_path, array $options ): void {
		// For now, generate HTML and convert to PDF using a simple approach
		// In production, you might want to use a library like TCPDF or DOMPDF

		$html_content = $this->generate_html_report( $report_data, $options );

		// Simple HTML to PDF conversion (placeholder)
		// This would need a proper PDF library in production
		$pdf_content = "PDF Report\n\n" . strip_tags( $html_content );

		if ( file_put_contents( $file_path, $pdf_content ) === false ) {
			throw new RuntimeException( "Cannot write PDF file: {$file_path}" );
		}
	}

	/**
	 * Generate Excel file.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array  $report_data    Report data.
	 * @param    string $file_path      File path.
	 * @return   void
	 */
	private function generate_excel_file( array $report_data, string $file_path ): void {
		// For now, generate CSV with .xlsx extension
		// In production, you might want to use PhpSpreadsheet
		$this->generate_csv_file( $report_data, $file_path );
	}

	/**
	 * Generate HTML report.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $report_data    Report data.
	 * @param    array $options        Options.
	 * @return   string                   HTML content.
	 */
	private function generate_html_report( array $report_data, array $options ): string {
		$template_path = SCD_PLUGIN_DIR . 'resources/views/admin/pages/campaign-performance.php';

		if ( file_exists( $template_path ) ) {
			ob_start();
			include $template_path;
			return ob_get_clean();
		}

		// Fallback HTML generation
		$html  = '<html><head><title>Analytics Report</title></head><body>';
		$html .= '<h1>' . esc_html( $report_data['metadata']['report_type'] ?? 'Report' ) . '</h1>';
		$html .= '<pre>' . esc_html( wp_json_encode( $report_data, JSON_PRETTY_PRINT ) ) . '</pre>';
		$html .= '</body></html>';

		return $html;
	}

	/**
	 * Validate report type.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $report_type    Report type.
	 * @return   bool                      Is valid.
	 */
	private function is_valid_report_type( string $report_type ): bool {
		$valid_types = array(
			'campaign_performance',
			'analytics_overview',
			'revenue_trend',
			'custom',
		);

		return in_array( $report_type, $valid_types, true );
	}

	/**
	 * Sanitize report options.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $options    Options to sanitize.
	 * @return   array               Sanitized options.
	 */
	private function sanitize_report_options( array $options ): array {
		$sanitized = array();

		$sanitized['format'] = in_array( $options['format'] ?? 'json', $this->supported_formats, true )
			? $options['format']
			: 'json';

		$valid_ranges            = array( '24hours', '7days', '30days', '90days', 'custom' );
		$sanitized['date_range'] = in_array( $options['date_range'] ?? '30days', $valid_ranges, true )
			? $options['date_range']
			: '30days';

		if ( isset( $options['campaign_ids'] ) && is_array( $options['campaign_ids'] ) ) {
			$sanitized['campaign_ids'] = array_map( 'absint', $options['campaign_ids'] );
		}

		$sanitized['force_refresh']    = ! empty( $options['force_refresh'] );
		$sanitized['include_metadata'] = $options['include_metadata'] ?? true;

		return $sanitized;
	}

	/**
	 * Get cache key for report.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $report_type    Report type.
	 * @param    array  $options        Options.
	 * @return   string                    Cache key.
	 */
	private function get_cache_key( string $report_type, array $options ): string {
		$key_data = array(
			'report_type' => $report_type,
			'options'     => $options,
		);

		return 'scd_report_' . md5( serialize( $key_data ) );
	}

	/**
	 * Generate filename for report.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $report_type    Report type.
	 * @param    string $format         Format.
	 * @return   string                    Filename.
	 */
	private function generate_filename( string $report_type, string $format ): string {
		$timestamp = date( 'Y-m-d_H-i-s' );
		return "scd_{$report_type}_{$timestamp}.{$format}";
	}

	/**
	 * Format file size.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    int $size    File size in bytes.
	 * @return   string            Formatted size.
	 */
	private function format_file_size( int $size ): string {
		$units      = array( 'B', 'KB', 'MB', 'GB' );
		$unit_index = 0;

		while ( $size >= 1024 && $unit_index < count( $units ) - 1 ) {
			$size /= 1024;
			++$unit_index;
		}

		return round( $size, 2 ) . ' ' . $units[ $unit_index ];
	}

	/**
	 * Flatten array for CSV export.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array  $array     Array to flatten.
	 * @param    string $prefix    Key prefix.
	 * @return   array                Flattened array.
	 */
	private function flatten_array( array $array, string $prefix = '' ): array {
		$result = array();

		foreach ( $array as $key => $value ) {
			$new_key = $prefix ? $prefix . '.' . $key : $key;

			if ( is_array( $value ) ) {
				$result = array_merge( $result, $this->flatten_array( $value, $new_key ) );
			} else {
				$result[ $new_key ] = $value;
			}
		}

		return $result;
	}

	/**
	 * Get date range description.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $date_range    Date range.
	 * @return   string                   Description.
	 */
	private function get_date_range_description( string $date_range ): string {
		switch ( $date_range ) {
			case '24hours':
				return 'Last 24 Hours';
			case '7days':
				return 'Last 7 Days';
			case '30days':
				return 'Last 30 Days';
			case '90days':
				return 'Last 90 Days';
			default:
				return 'Custom Range';
		}
	}

	/**
	 * Calculate next run time for scheduled report.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $schedule    Schedule frequency.
	 * @return   string                Next run time.
	 */
	private function calculate_next_run_time( string $schedule ): string {
		switch ( $schedule ) {
			case 'daily':
				return date( 'Y-m-d H:i:s', strtotime( '+1 day' ) );
			case 'weekly':
				return date( 'Y-m-d H:i:s', strtotime( '+1 week' ) );
			case 'monthly':
				return date( 'Y-m-d H:i:s', strtotime( '+1 month' ) );
			default:
				return date( 'Y-m-d H:i:s', strtotime( '+1 day' ) );
		}
	}

	/**
	 * Collect metric data.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $metric       Metric name.
	 * @param    string $date_range   Date range.
	 * @param    array  $filters      Filters.
	 * @return   array                   Metric data.
	 */
	private function collect_metric_data( string $metric, string $date_range, array $filters ): array {
		// This would collect specific metric data based on the metric type
		// For now, return placeholder data
		return array(
			'metric'      => $metric,
			'date_range'  => $date_range,
			'value'       => 0,
			'data_points' => array(),
		);
	}
}
