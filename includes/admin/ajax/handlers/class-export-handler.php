<?php
/**
 * Export Handler Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers/class-export-handler.php
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
 * Export Handler Class
 *
 * @since      1.0.0
 */
class WSSCD_Export_Handler extends WSSCD_Abstract_Analytics_Handler {

	use WSSCD_License_Validation_Trait;

	/**
	 * Export service instance.
	 *
	 * @since    1.0.0
	 * @var      WSSCD_Export_Service
	 */
	private $export_service;

	/**
	 * Initialize the handler.
	 *
	 * @since    1.0.0
	 * @param    WSSCD_Metrics_Calculator $metrics_calculator    Metrics calculator.
	 * @param    WSSCD_Logger             $logger                Logger instance.
	 * @param    WSSCD_Export_Service     $export_service        Export service.
	 */
	public function __construct( $metrics_calculator, $logger, $export_service ) {
		parent::__construct( $metrics_calculator, $logger );
		$this->export_service = $export_service;
	}

	/**
	 * Get required capability.
	 *
	 * @since    1.0.0
	 * @return   string    Required capability.
	 */
	protected function get_required_capability(): string {
		return 'wsscd_export_analytics';
	}

	/**
	 * Handle the request.
	 *
	 * @since    1.0.0
	 * @param    array $request    Request data.
	 * @return   array                Response data.
	 */
	public function handle( array $request ): array {
		// Verify request
		$verification = $this->verify_request( $request, 'wsscd_analytics_export' );
		if ( is_wp_error( $verification ) ) {
			return $this->error(
				$verification->get_error_message(),
				$verification->get_error_code()
			);
		}

		$license_check = $this->validate_license( 'critical' );
		if ( $this->license_validation_failed( $license_check ) ) {
			return $this->license_error_response( $license_check );
		}

		$export_type = sanitize_text_field( isset( $request['export_type'] ) ? $request['export_type'] : 'overview' );
		$format      = sanitize_text_field( isset( $request['format'] ) ? $request['format'] : 'csv' );
		$date_range  = sanitize_text_field( isset( $request['date_range'] ) ? $request['date_range'] : '30days' );
		$campaign_id = isset( $request['campaign_id'] ) ? sanitize_text_field( $request['campaign_id'] ) : 'all';

		try {
			// Build export options
			$options = array(
				'date_range' => $date_range,
				'user_id'    => get_current_user_id(),
			);

			// Add campaign filter if provided
			if ( $campaign_id && 'all' !== $campaign_id ) {
				$options['campaign_id'] = $campaign_id;
			}

			// Generate export using service
			$export_result = $this->export_service->generate_export(
				$export_type,
				$format,
				$options
			);

			$this->logger->info(
				'Export generated',
				array(
					'export_type' => $export_type,
					'format'      => $format,
				)
			);

			return $this->success( $export_result );

		} catch ( Exception $e ) {
			$this->logger->error(
				'Export generation failed',
				array(
					'error' => $e->getMessage(),
				)
			);

			return $this->error(
				/* translators: %s: error message */
				sprintf( __( 'Failed to generate export: %s', 'smart-cycle-discounts' ), $e->getMessage() ),
				'export_generation_failed'
			);
		}
	}
}
