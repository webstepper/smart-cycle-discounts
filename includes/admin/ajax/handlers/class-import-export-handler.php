<?php
/**
 * Import Export Handler Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers/class-import-export-handler.php
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Import/Export Handler Class
 *
 * @since 1.0.0
 */
class SCD_Import_Export_Handler extends SCD_Abstract_Ajax_Handler {

	/**
	 * Container instance.
	 *
	 * @var object
	 */
	private $container;

	/**
	 * Feature gate instance.
	 *
	 * @var SCD_Feature_Gate
	 */
	private $feature_gate;

	/**
	 * Constructor.
	 *
	 * @param object           $container    Container instance.
	 * @param SCD_Logger       $logger       Logger instance.
	 * @param SCD_Feature_Gate $feature_gate Feature gate instance.
	 */
	public function __construct( $container, $logger, $feature_gate = null ) {
		parent::__construct( $logger );
		$this->container    = $container;
		$this->feature_gate = $feature_gate;
	}

	/**
	 * Get AJAX action name.
	 *
	 * @return string Action name.
	 */
	protected function get_action_name() {
		return 'scd_ajax';
	}

	/**
	 * Handle the AJAX request.
	 *
	 * @param array $request Request data.
	 * @return array Response data.
	 */
	protected function handle( $request ) {
		$start_time = microtime( true );

		$export_type = isset( $request['export_type'] ) ? sanitize_text_field( $request['export_type'] ) : '';

		// Log request start
		$this->logger->flow(
			'info',
			'AJAX START',
			'Processing export request',
			array(
				'export_type' => $export_type,
				'user_id'     => get_current_user_id(),
			)
		);

		if ( ! empty( $export_type ) ) {
			return $this->handle_export( $export_type, $start_time );
		} else {
			$this->logger->flow(
				'error',
				'AJAX ERROR',
				'Invalid export request',
				array(
					'_start_time' => $start_time,
				)
			);
			return $this->error( __( 'Invalid request', 'smart-cycle-discounts' ) );
		}
	}

	/**
	 * Handle export operation.
	 *
	 * @param string $type       Export type (campaigns or settings).
	 * @param float  $start_time Request start time.
	 * @return array Response data.
	 */
	private function handle_export( $type, $start_time ) {
		// Check if user can export data (premium feature)
		if ( $this->feature_gate && ! $this->feature_gate->can_export_data() ) {
			$this->logger->flow(
				'warning',
				'AJAX BLOCKED',
				'Export blocked - premium feature',
				array(
					'export_type' => $type,
					'user_id'     => get_current_user_id(),
					'_start_time' => $start_time,
				)
			);
			return $this->error( __( 'Export functionality is available in the Pro version. Please upgrade to access this feature.', 'smart-cycle-discounts' ) );
		}

		if ( 'campaigns' === $type ) {
			return $this->export_campaigns( $start_time );
		} elseif ( 'settings' === $type ) {
			return $this->export_settings( $start_time );
		} else {
			$this->logger->flow(
				'error',
				'AJAX ERROR',
				'Invalid export type',
				array(
					'export_type' => $type,
					'_start_time' => $start_time,
				)
			);
			return $this->error( __( 'Invalid export type', 'smart-cycle-discounts' ) );
		}
	}

	/**
	 * Export campaigns.
	 *
	 * SECURITY: Only exports campaigns owned by current user (unless super admin).
	 * Limits export size to prevent DOS attacks.
	 *
	 * @param float $start_time Request start time.
	 * @return array Response data.
	 */
	private function export_campaigns( $start_time ) {
		global $wpdb;

		$campaigns_table = $wpdb->prefix . 'scd_campaigns';
		$current_user_id = get_current_user_id();

		// SECURITY: Build query with explicit columns (no SELECT *)
		// This prevents schema disclosure
		$query = "
			SELECT
				id,
				name,
				status,
				campaign_type,
				priority,
				schedule_start,
				schedule_end,
				product_selection_type,
				product_selection_data,
				discount_type,
				discount_settings,
				usage_limit_per_user,
				usage_limit_total,
				created_at,
				updated_at,
				created_by
			FROM {$campaigns_table}
		";

		// SECURITY: Ownership filtering - only export own campaigns unless super admin
		// Super admins (manage_options) can export all campaigns
		if ( ! current_user_can( 'manage_options' ) ) {
			$query .= $wpdb->prepare( ' WHERE created_by = %d', $current_user_id );
		}

		// SECURITY: Add ORDER BY and LIMIT to prevent DOS
		// Maximum 1000 campaigns per export
		$query .= ' ORDER BY created_at DESC LIMIT 1000';

		$campaigns = $wpdb->get_results( $query, ARRAY_A );

		// Check for database errors
		if ( null === $campaigns && ! empty( $wpdb->last_error ) ) {
			$this->logger->flow(
				'error',
				'DB ERROR',
				'Failed to export campaigns',
				array(
					'error'       => $wpdb->last_error,
					'query'       => $wpdb->last_query,
					'user_id'     => $current_user_id,
					'_start_time' => $start_time,
				)
			);
			return $this->error( __( 'Database error occurred', 'smart-cycle-discounts' ) );
		}

		// SECURITY: Sanitize campaign data before export
		$sanitized_campaigns = array();
		foreach ( $campaigns as $campaign ) {
			$sanitized_campaigns[] = array(
				'id'                     => intval( $campaign['id'] ),
				'name'                   => sanitize_text_field( $campaign['name'] ),
				'status'                 => sanitize_text_field( $campaign['status'] ),
				'campaign_type'          => sanitize_text_field( $campaign['campaign_type'] ),
				'priority'               => intval( $campaign['priority'] ),
				'schedule_start'         => sanitize_text_field( $campaign['schedule_start'] ),
				'schedule_end'           => sanitize_text_field( $campaign['schedule_end'] ),
				'product_selection_type' => sanitize_text_field( $campaign['product_selection_type'] ),
				'product_selection_data' => $campaign['product_selection_data'], // JSON data
				'discount_type'          => sanitize_text_field( $campaign['discount_type'] ),
				'discount_settings'      => $campaign['discount_settings'], // JSON data
				'usage_limit_per_user'   => intval( $campaign['usage_limit_per_user'] ),
				'usage_limit_total'      => intval( $campaign['usage_limit_total'] ),
				'created_at'             => sanitize_text_field( $campaign['created_at'] ),
				'updated_at'             => sanitize_text_field( $campaign['updated_at'] ),
				'created_by'             => intval( $campaign['created_by'] ),
			);
		}

		$is_filtered = ! current_user_can( 'manage_options' );

		$export_data = array(
			'version'            => SCD_VERSION,
			'type'               => 'campaigns',
			'date'               => current_time( 'mysql' ),
			'count'              => count( $sanitized_campaigns ),
			'ownership_filtered' => $is_filtered,
			'data'               => $sanitized_campaigns,
		);

		$filename = 'scd-campaigns-export-' . gmdate( 'Y-m-d-H-i-s' ) . '.json';
		$content  = wp_json_encode( $export_data, JSON_PRETTY_PRINT );

		// Log successful export
		$this->logger->flow(
			'notice',
			'AJAX SUCCESS',
			'Campaigns exported successfully',
			array(
				'count'              => count( $sanitized_campaigns ),
				'ownership_filtered' => $is_filtered,
				'filename'           => $filename,
				'size'               => strlen( $content ),
				'user_id'            => $current_user_id,
				'_start_time'        => $start_time,
				'_include_memory'    => true,
			)
		);

		return $this->success(
			array(
				'content'  => $content,
				'filename' => $filename,
			)
		);
	}

	/**
	 * Export settings.
	 *
	 * @param float $start_time Request start time.
	 * @return array Response data.
	 */
	private function export_settings( $start_time ) {
		$settings = get_option( 'scd_settings', array() );

		$export_data = array(
			'version' => SCD_VERSION,
			'type'    => 'settings',
			'date'    => current_time( 'mysql' ),
			'data'    => $settings,
		);

		$filename = 'scd-settings-export-' . gmdate( 'Y-m-d-H-i-s' ) . '.json';
		$content  = wp_json_encode( $export_data, JSON_PRETTY_PRINT );

		// Log successful export
		$this->logger->flow(
			'notice',
			'AJAX SUCCESS',
			'Settings exported successfully',
			array(
				'settings_count'  => count( $settings ),
				'filename'        => $filename,
				'size'            => strlen( $content ),
				'user_id'         => get_current_user_id(),
				'_start_time'     => $start_time,
				'_include_memory' => true,
			)
		);

		return $this->success(
			array(
				'content'  => $content,
				'filename' => $filename,
			)
		);
	}
}
