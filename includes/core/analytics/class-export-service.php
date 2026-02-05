<?php
/**
 * @fs_premium_only
 *
 * Export Service Class
 *
 * Generates standalone HTML/PDF export documents. These exports are viewed outside
 * WordPress (downloaded files, print dialogs) and have no access to wp_enqueue_style().
 * Inline CSS is REQUIRED for proper display of exported documents.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/analytics/class-export-service.php
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
 * Export Service Class
 *
 * @since      1.0.0
 */
class WSSCD_Export_Service {

	/**
	 * Metrics calculator instance.
	 *
	 * @since    1.0.0
	 * @var      WSSCD_Metrics_Calculator
	 */
	private $metrics_calculator;

	/**
	 * Logger instance.
	 *
	 * @since    1.0.0
	 * @var      WSSCD_Logger
	 */
	private $logger;

	/**
	 * Initialize the export service.
	 *
	 * @since    1.0.0
	 * @param    WSSCD_Metrics_Calculator $metrics_calculator    Metrics calculator.
	 * @param    WSSCD_Logger             $logger                Logger instance.
	 */
	public function __construct(
		WSSCD_Metrics_Calculator $metrics_calculator,
		WSSCD_Logger $logger
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
				'download_url' => $file_result['download_url'],
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
		$date_range  = isset( $options['date_range'] ) ? $options['date_range'] : '30days';
		$campaign_id = isset( $options['campaign_id'] ) ? $options['campaign_id'] : null;

		switch ( $export_type ) {
			case 'overview':
				$metrics = $this->metrics_calculator->calculate_overall_metrics( $date_range, false );

				// If campaign filter is active, get campaign-specific data
				if ( $campaign_id && 'all' !== $campaign_id ) {
					$campaign_metrics = $this->get_campaign_specific_metrics( (int) $campaign_id, $date_range );
					$metrics = array_merge( $metrics, $campaign_metrics );
				}

				// Wrap in array to create a single row for CSV export
				return array( $metrics );

			case 'campaigns':
				return $this->metrics_calculator->calculate_all_campaigns_metrics( $date_range, false );

			case 'product':
				$product_id = isset( $options['product_id'] ) ? (int) $options['product_id'] : 0;
				if ( $product_id > 0 ) {
					return $this->metrics_calculator->calculate_product_metrics( $product_id, $date_range, false );
				}
				return array();

			default:
				// Wrap in array to create a single row for CSV export
			return array( $this->metrics_calculator->calculate_overall_metrics( $date_range, false ) );
		}
	}

	/**
	 * Get campaign-specific metrics for export.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    int    $campaign_id    Campaign ID.
	 * @param    string $date_range     Date range.
	 * @return   array                     Campaign metrics.
	 */
	private function get_campaign_specific_metrics( int $campaign_id, string $date_range ): array {
		global $wpdb;

		$campaigns_table = $wpdb->prefix . 'wsscd_campaigns';
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Export requires fresh data. Table name is constructed with $wpdb->prefix, not user input.
		$campaign = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$campaigns_table} WHERE id = %d",
				$campaign_id
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls, PluginCheck.Security.DirectDB.UnescapedDBParameter

		if ( ! $campaign ) {
			return array();
		}

		return array(
			'filtered_campaign_id'   => $campaign_id,
			'filtered_campaign_name' => $campaign['name'],
			'filtered_campaign_status' => $campaign['status'],
		);
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
		$export_dir = $upload_dir['basedir'] . '/wsscd-exports';

		if ( ! file_exists( $export_dir ) ) {
			wp_mkdir_p( $export_dir );
		}

		// Generate filename with store name
		$timestamp  = current_time( 'Y-m-d-H-i-s' );
		$store_name = sanitize_file_name( get_bloginfo( 'name' ) );
		$store_name = strtolower( str_replace( ' ', '-', $store_name ) );
		$filename   = "{$store_name}-analytics-{$export_type}-{$timestamp}.{$format}";
		$filepath   = $export_dir . '/' . $filename;

		// Generate content based on format
		switch ( $format ) {
			case 'csv':
				$content = $this->generate_csv( $data );
				break;

			case 'json':
				$content = wp_json_encode( $data, JSON_PRETTY_PRINT );
				break;

			case 'pdf':
				$content = $this->generate_pdf_html( $data, $export_type );
				$filename = str_replace( '.pdf', '.html', $filename );
				$filepath = str_replace( '.pdf', '.html', $filepath );
				break;

			default:
				$content = wp_json_encode( $data, JSON_PRETTY_PRINT );
		}

		// Write file
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Writing export file to plugin's uploads directory.
		file_put_contents( $filepath, $content );

		// Generate download URL
		$download_url = $upload_dir['baseurl'] . '/wsscd-exports/' . $filename;

		return array(
			'filename'     => $filename,
			'filepath'     => $filepath,
			'download_url' => $download_url,
			'size'         => filesize( $filepath ),
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

		// Add metadata header
		$csv .= $this->generate_csv_metadata();

		if ( ! empty( $data ) ) {
			$first_row = is_array( $data ) ? reset( $data ) : $data;
			$headers   = array();

			if ( is_array( $first_row ) ) {
				$headers = array_keys( $first_row );
			} elseif ( is_object( $first_row ) ) {
				$headers = array_keys( get_object_vars( $first_row ) );
			} else {
				$headers = array_keys( $data );
			}

			// Convert headers to human-readable format
			$readable_headers = array_map( array( $this, 'format_header' ), $headers );

			// Add column headers
			$csv .= implode( ',', array_map( array( $this, 'escape_csv_value' ), $readable_headers ) ) . "\n";

			// Add data rows
			foreach ( $data as $row ) {
				if ( is_array( $row ) ) {
					$formatted_row = array();
					foreach ( $row as $key => $value ) {
						$formatted_row[] = $this->format_csv_value( $key, $value );
					}
					$csv .= implode( ',', array_map( array( $this, 'escape_csv_value' ), $formatted_row ) ) . "\n";
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

		if ( false !== strpos( $value, ',' ) || false !== strpos( $value, '"' ) || false !== strpos( $value, "\n" ) ) {
			$value = '"' . str_replace( '"', '""', $value ) . '"';
		}

		return $value;
	}

	/**
	 * Generate CSV metadata header.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   string    Metadata header rows.
	 */
	private function generate_csv_metadata(): string {
		$csv = '';

		// Report title
		$csv .= 'Report,Smart Cycle Discounts - Analytics Export' . "\n";

		// Export date and time
		$csv .= 'Generated,' . current_time( 'Y-m-d H:i:s' ) . "\n";

		// Currency
		$csv .= 'Currency,' . get_woocommerce_currency() . "\n";

		// Blank separator row
		$csv .= "\n";

		return $csv;
	}

	/**
	 * Format header to human-readable.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $header    Snake_case header.
	 * @return   string               Title Case header.
	 */
	private function format_header( string $header ): string {
		// Special cases for better readability
		$special_cases = array(
			'avg_ctr'             => 'Avg CTR',
			'avg_conversion_rate' => 'Avg Conversion Rate',
			'avg_order_value'     => 'Avg Order Value',
			'avg_roi'             => 'Avg ROI',
			'ctr_change'          => 'CTR Change',
			'aov_change'          => 'AOV Change',
		);

		if ( isset( $special_cases[ $header ] ) ) {
			return $special_cases[ $header ];
		}

		// Convert snake_case to Title Case
		return ucwords( str_replace( '_', ' ', $header ) );
	}

	/**
	 * Format CSV value based on field type.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $key      Field key.
	 * @param    mixed  $value    Field value.
	 * @return   mixed               Formatted value.
	 */
	private function format_csv_value( string $key, $value ) {
		// Handle period field (convert JSON to readable dates)
		if ( 'period' === $key && is_array( $value ) ) {
			if ( isset( $value['start_date'] ) && isset( $value['end_date'] ) ) {
				return $value['start_date'] . ' to ' . $value['end_date'];
			}
		}

		// Decode JSON if it's still encoded
		if ( is_string( $value ) && $this->is_json( $value ) ) {
			$decoded = json_decode( $value, true );
			if ( isset( $decoded['start_date'] ) && isset( $decoded['end_date'] ) ) {
				return $decoded['start_date'] . ' to ' . $decoded['end_date'];
			}
		}

		// Keep numbers unformatted for Excel compatibility
		return $value;
	}

	/**
	 * Check if string is JSON.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $string    String to check.
	 * @return   bool                 True if JSON.
	 */
	private function is_json( string $string ): bool {
		json_decode( $string );
		return json_last_error() === JSON_ERROR_NONE;
	}

	/**
	 * Generate PDF-ready HTML content.
	 *
	 * Creates a print-optimized HTML document that can be printed to PDF
	 * directly from the browser without external dependencies.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array  $data           Export data.
	 * @param    string $export_type    Export type.
	 * @return   string                    HTML content.
	 */
	private function generate_pdf_html( array $data, string $export_type ): string {
		$site_name    = get_bloginfo( 'name' );
		$generated_at = current_time( 'Y-m-d H:i:s' );
		$export_title = ucwords( str_replace( '_', ' ', $export_type ) );

		// Start HTML
		$html  = '<!DOCTYPE html>';
		$html .= '<html lang="' . esc_attr( get_locale() ) . '">';
		$html .= '<head>';
		$html .= '<meta charset="' . esc_attr( get_bloginfo( 'charset' ) ) . '">';
		$html .= '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
		$html .= '<title>' . esc_html( $export_title . ' Export - ' . $site_name ) . '</title>';
		$html .= $this->get_pdf_styles();
		$html .= '</head>';
		$html .= '<body>';

		// Header
		$html .= '<div class="header">';
		$html .= '<h1>' . esc_html( $export_title . ' Export' ) . '</h1>';
		$html .= '<p class="metadata">';
		$html .= '<strong>' . esc_html( $site_name ) . '</strong><br>';
		$html .= 'Generated: ' . esc_html( $generated_at ) . '';
		$html .= '</p>';
		$html .= '</div>';

		// Content
		$html .= '<div class="content">';
		$html .= $this->format_data_as_html( $data );
		$html .= '</div>';

		// Footer
		$html .= '<div class="footer">';
		$html .= '<p>Smart Cycle Discounts &copy; ' . gmdate( 'Y' ) . '</p>';
		$html .= '<button class="print-button" onclick="window.print()">Print to PDF</button>';
		$html .= '</div>';

		$html .= '</body>';
		$html .= '</html>';

		return $html;
	}

	/**
	 * Get PDF export styles.
	 *
	 * Returns inline CSS for standalone HTML/PDF exports. These documents are viewed
	 * outside WordPress (downloaded files, browser print dialogs) and cannot use
	 * wp_enqueue_style(). Inline CSS is required for proper display.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   string    CSS styles.
	 */
	private function get_pdf_styles(): string {
		// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- Standalone HTML/PDF export. Viewed outside WordPress; wp_enqueue_style() not available.
		return '
		<style>
			* { margin: 0; padding: 0; box-sizing: border-box; }
			body {
				font-family: Arial, sans-serif;
				font-size: 12px;
				line-height: 1.6;
				color: #333;
				padding: 20px;
				background: #f5f5f5;
			}
			.header {
				background: #fff;
				padding: 20px;
				margin-bottom: 20px;
				border-bottom: 3px solid #2271b1;
			}
			.header h1 {
				color: #2271b1;
				font-size: 24px;
				margin-bottom: 10px;
			}
			.metadata {
				color: #666;
				font-size: 11px;
			}
			.content {
				background: #fff;
				padding: 20px;
				margin-bottom: 20px;
			}
			table {
				width: 100%;
				border-collapse: collapse;
				margin: 20px 0;
			}
			th, td {
				padding: 10px;
				text-align: left;
				border-bottom: 1px solid #ddd;
			}
			th {
				background: #2271b1;
				color: #fff;
				font-weight: bold;
			}
			tr:nth-child(even) {
				background: #f9f9f9;
			}
			.footer {
				text-align: center;
				color: #666;
				font-size: 10px;
				padding: 20px;
			}
			.print-button {
				background: #2271b1;
				color: #fff;
				border: none;
				padding: 10px 20px;
				border-radius: 4px;
				cursor: pointer;
				margin-top: 10px;
			}
			.print-button:hover {
				background: #135e96;
			}
			@media print {
				body { background: #fff; padding: 0; }
				.print-button { display: none; }
				.header { border-bottom: 2px solid #000; }
			}
		</style>';
	}

	/**
	 * Format data as HTML table.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $data    Export data.
	 * @return   string            HTML table.
	 */
	private function format_data_as_html( array $data ): string {
		if ( empty( $data ) ) {
			return '<p>No data available.</p>';
		}

		$first_item = is_array( $data ) ? reset( $data ) : $data;

		if ( ! is_array( $first_item ) && ! is_object( $first_item ) ) {
			// Simple key-value pairs
			return $this->format_key_value_html( $data );
		}

		// Table format for list of items
		$html  = '<table>';
		$html .= '<thead><tr>';

		$headers = array();
		if ( is_array( $first_item ) ) {
			$headers = array_keys( $first_item );
		} elseif ( is_object( $first_item ) ) {
			$headers = array_keys( get_object_vars( $first_item ) );
		}

		foreach ( $headers as $header ) {
			$html .= '<th>' . esc_html( ucwords( str_replace( '_', ' ', $header ) ) ) . '</th>';
		}

		$html .= '</tr></thead>';
		$html .= '<tbody>';

		foreach ( $data as $row ) {
			$html .= '<tr>';

			if ( is_array( $row ) ) {
				foreach ( $row as $value ) {
					$html .= '<td>' . esc_html( $this->format_value( $value ) ) . '</td>';
				}
			} elseif ( is_object( $row ) ) {
				foreach ( get_object_vars( $row ) as $value ) {
					$html .= '<td>' . esc_html( $this->format_value( $value ) ) . '</td>';
				}
			}

			$html .= '</tr>';
		}

		$html .= '</tbody>';
		$html .= '</table>';

		return $html;
	}

	/**
	 * Format key-value pairs as HTML.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $data    Data array.
	 * @return   string            HTML output.
	 */
	private function format_key_value_html( array $data ): string {
		$html  = '<table>';
		$html .= '<thead><tr>';
		$html .= '<th>Property</th>';
		$html .= '<th>Value</th>';
		$html .= '</tr></thead>';
		$html .= '<tbody>';

		foreach ( $data as $key => $value ) {
			$html .= '<tr>';
			$html .= '<td><strong>' . esc_html( ucwords( str_replace( '_', ' ', $key ) ) ) . '</strong></td>';
			$html .= '<td>' . esc_html( $this->format_value( $value ) ) . '</td>';
			$html .= '</tr>';
		}

		$html .= '</tbody>';
		$html .= '</table>';

		return $html;
	}

	/**
	 * Format a value for display.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    mixed $value    Value to format.
	 * @return   string             Formatted value.
	 */
	private function format_value( $value ): string {
		if ( is_array( $value ) || is_object( $value ) ) {
			return wp_json_encode( $value );
		}

		if ( is_bool( $value ) ) {
			return $value ? 'Yes' : 'No';
		}

		return (string) $value;
	}
}
