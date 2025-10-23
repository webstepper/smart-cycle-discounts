<?php
/**
 * Analytics Export Handler
 *
 * Handles AJAX requests for exporting analytics data.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/analytics
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
class SCD_Export_Handler extends SCD_Abstract_Analytics_Handler {

    /**
     * Export service instance.
     *
     * @since    1.0.0
     * @var      SCD_Export_Service
     */
    private $export_service;

    /**
     * Feature gate instance.
     *
     * @since    1.0.0
     * @var      SCD_Feature_Gate
     */
    private $feature_gate;

    /**
     * Initialize the handler.
     *
     * @since    1.0.0
     * @param    SCD_Metrics_Calculator    $metrics_calculator    Metrics calculator.
     * @param    SCD_Logger                $logger                Logger instance.
     * @param    SCD_Export_Service        $export_service        Export service.
     * @param    SCD_Feature_Gate          $feature_gate          Feature gate.
     */
    public function __construct( $metrics_calculator, $logger, $export_service, $feature_gate = null ) {
        parent::__construct( $metrics_calculator, $logger );
        $this->export_service = $export_service;
        $this->feature_gate = $feature_gate;
    }

    /**
     * Get required capability.
     *
     * @since    1.0.0
     * @return   string    Required capability.
     */
    protected function get_required_capability() {
        return 'scd_export_analytics';
    }

    /**
     * Handle the request.
     *
     * @since    1.0.0
     * @param    array    $request    Request data.
     * @return   array                Response data.
     */
    public function handle( $request ) {
        // Verify request
        $verification = $this->verify_request($request, 'scd_analytics_export');
        if ( is_wp_error( $verification ) ) {
            return $this->error(
                $verification->get_error_message(),
                $verification->get_error_code()
            );
        }

        // Check if user can export data (premium feature)
        if ( $this->feature_gate && ! $this->feature_gate->can_export_data() ) {
            return $this->error(
                __( 'Export functionality is available in the Pro version. Please upgrade to access this feature.', 'smart-cycle-discounts' ),
                'feature_locked'
            );
        }

        // Sanitize inputs
        $export_type = sanitize_text_field( isset( $request['export_type'] ) ? $request['export_type'] : 'overview' );
        $format = sanitize_text_field( isset( $request['format'] ) ? $request['format'] : 'csv' );
        $date_range = sanitize_text_field( isset( $request['date_range'] ) ? $request['date_range'] : '30days' );

        try {
            // Generate export using service
            $export_result = $this->export_service->generate_export(
                $export_type,
                $format,
                array(
                    'date_range' => $date_range,
                    'user_id' => get_current_user_id()
                )
            );

            $this->logger->info('Export generated', array(
                'export_type' => $export_type,
                'format' => $format
            ));

            return $this->success($export_result);

        } catch (Exception $e) {
            $this->logger->error('Export generation failed', array(
                'error' => $e->getMessage()
            ));

            return $this->error(
                sprintf(__('Failed to generate export: %s', 'smart-cycle-discounts'), $e->getMessage()),
                'export_generation_failed'
            );
        }
    }
}