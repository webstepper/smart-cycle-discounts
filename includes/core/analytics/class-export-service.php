<?php
/**
 * Export Service
 *
 * Generates exports of analytics data in various formats.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/analytics
 * @since      1.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Export Service Class
 *
 * @since      1.0.0
 */
class SCD_Export_Service {

	/**
	 * Metrics calculator instance.
	 *
	 * @since    1.0.0
	 * @var      SCD_Metrics_Calculator
	 */
	private SCD_Metrics_Calculator $metrics_calculator;

	/**
	 * Logger instance.
	 *
	 * @since    1.0.0
	 * @var      SCD_Logger
	 */
	private SCD_Logger $logger;

	/**
	 * Initialize the export service.
	 *
	 * @since    1.0.0
	 * @param    SCD_Metrics_Calculator $metrics_calculator    Metrics calculator.
	 * @param    SCD_Logger             $logger                Logger instance.
	 */
	public function __construct(
		SCD_Metrics_Calculator $metrics_calculator,
		SCD_Logger $logger
	) {
		$this->metrics_calculator = $metrics_calculator;
		$this->logger             = $logger;
	}

	/**
	 * Generate export.
	 *
	 * @since    1.0.0
	 * @param    string $export_type    Type of export (overview, campaign, product).
	 * @param    string $format         Export format (csv, json, pdf).
	 * @param    array  $options        Export options.
	 * @return   array                     Export result with download URL.
	 */
	public function generate_export( string $export_type, string $format, array $options = array() ): array {
		try {
			// Get data based on export type
			$data = $this->get_export_data( $export_type, $options );

			// Generate export file
			$file_result = $this->create_export_file( $data, $format, $export_type );

			$this->logger->info(
				'Export generated successfully',
				array(
					'export_type' => $export_type,
					'format'      => $format,
					'file'        => $file_result['filename'],
				)
			);

			return array(
				'success'      => true,
				'download_url' => $file_result['url'],
				'filename'     => $file_result['filename'],
				'size'         => $file_result['size'],
				'format'       => $format,
				'generated_at' => current_time( 'timestamp' ),
			);

		} catch ( Exception $e ) {
			$this->logger->error(
				'Export generation failed',
				array(
					'export_type' => $export_type,
					'format'      => $format,
					'error'       => $e->getMessage(),
				)
			);

			throw $e;
		}
	}

	/**
	 * Get export data.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $export_type    Export type.
	 * @param    array  $options        Export options.
	 * @return   array                     Export data.
	 */
	private function get_export_data( string $export_type, array $options ): array {
		$date_range = isset( $options['date_range'] ) ? $options['date_range'] : '30days';

		switch ( $export_type ) {
			case 'overview':
				return $this->metrics_calculator->calculate_overall_metrics( $date_range, false );

			case 'campaigns':
				return $this->metrics_calculator->calculate_all_campaigns_metrics( $date_range, false );

			case 'product':
				$product_id = isset( $options['product_id'] ) ? (int) $options['product_id'] : 0;
				if ( $product_id > 0 ) {
					return $this->metrics_calculator->calculate_product_metrics( $product_id, $date_range, false );
				}
				return array();

			default:
				return $this->metrics_calculator->calculate_overall_metrics( $date_range, false );
		}
	}

	/**
	 * Create export file.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array  $data           Export data.
	 * @param    string $format         Export format.
	 * @param    string $export_type    Export type.
	 * @return   array                     File information.
	 */
	private function create_export_file( array $data, string $format, string $export_type ): array {
		$upload_dir = wp_upload_dir();
		$export_dir = $upload_dir['basedir'] . '/scd-exports';

		// Create export directory if it doesn't exist
		if ( ! file_exists( $export_dir ) ) {
			wp_mkdir_p( $export_dir );
		}

		// Generate filename
		$timestamp = current_time( 'Y-m-d-H-i-s' );
		$filename  = "scd-export-{$export_type}-{$timestamp}.{$format}";
		$filepath  = $export_dir . '/' . $filename;

		// Generate content based on format
		switch ( $format ) {
			case 'csv':
				$content = $this->generate_csv( $data );
				break;

			case 'json':
				$content = wp_json_encode( $data, JSON_PRETTY_PRINT );
				break;

			case 'pdf':
				// For now, fallback to JSON for PDF
				// TODO: Implement PDF generation
				$content = wp_json_encode( $data, JSON_PRETTY_PRINT );
				break;

			default:
				$content = wp_json_encode( $data, JSON_PRETTY_PRINT );
		}

		// Write file
		file_put_contents( $filepath, $content );

		// Generate download URL
		$url = $upload_dir['baseurl'] . '/scd-exports/' . $filename;

		return array(
			'filename' => $filename,
			'filepath' => $filepath,
			'url'      => $url,
			'size'     => filesize( $filepath ),
		);
	}

	/**
	 * Generate CSV content.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $data    Export data.
	 * @return   string            CSV content.
	 */
	private function generate_csv( array $data ): string {
		$csv = '';

		// Convert data to CSV format
		if ( ! empty( $data ) ) {
			// Get headers from first row
			$first_row = is_array( $data ) ? reset( $data ) : $data;
			$headers   = array();

			if ( is_array( $first_row ) ) {
				$headers = array_keys( $first_row );
			} elseif ( is_object( $first_row ) ) {
				$headers = array_keys( get_object_vars( $first_row ) );
			} else {
				$headers = array_keys( $data );
			}

			// Add headers
			$csv .= implode( ',', array_map( array( $this, 'escape_csv_value' ), $headers ) ) . "\n";

			// Add data rows
			foreach ( $data as $row ) {
				if ( is_array( $row ) ) {
					$csv .= implode( ',', array_map( array( $this, 'escape_csv_value' ), array_values( $row ) ) ) . "\n";
				} elseif ( is_object( $row ) ) {
					$csv .= implode( ',', array_map( array( $this, 'escape_csv_value' ), array_values( get_object_vars( $row ) ) ) ) . "\n";
				}
			}
		}

		return $csv;
	}

	/**
	 * Escape CSV value.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    mixed $value    Value to escape.
	 * @return   string             Escaped value.
	 */
	private function escape_csv_value( $value ): string {
		if ( is_array( $value ) || is_object( $value ) ) {
			$value = wp_json_encode( $value );
		}

		$value = (string) $value;

		// Escape quotes and wrap in quotes if needed
		if ( false !== strpos( $value, ',' ) || false !== strpos( $value, '"' ) || false !== strpos( $value, "\n" ) ) {
			$value = '"' . str_replace( '"', '""', $value ) . '"';
		}

		return $value;
	}
}
