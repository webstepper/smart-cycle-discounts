<?php
/**
 * Revenue Trend Handler
 *
 * Handles AJAX requests for revenue trend data.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/analytics
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Revenue Trend Handler Class
 *
 * @since      1.0.0
 */
class SCD_Revenue_Trend_Handler extends SCD_Abstract_Analytics_Handler {

    /**
     * Analytics collector instance.
     *
     * @since    1.0.0
     * @var      SCD_Analytics_Collector
     */
    private $analytics_collector;

    /**
     * Initialize the handler.
     *
     * @since    1.0.0
     * @param    SCD_Metrics_Calculator     $metrics_calculator     Metrics calculator.
     * @param    SCD_Logger                 $logger                 Logger instance.
     * @param    SCD_Analytics_Collector    $analytics_collector    Analytics collector.
     */
    public function __construct( $metrics_calculator, $logger, $analytics_collector ) {
        parent::__construct( $metrics_calculator, $logger );
        $this->analytics_collector = $analytics_collector;
    }

    /**
     * Get required capability.
     *
     * @since    1.0.0
     * @return   string    Required capability.
     */
    protected function get_required_capability() {
        return 'scd_view_analytics';
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
        $verification = $this->verify_request( $request, 'scd_analytics_revenue_trend' );
        if ( is_wp_error( $verification ) ) {
            return $this->error(
                $verification->get_error_message(),
                $verification->get_error_code()
            );
        }

        // Sanitize inputs
        $date_range = sanitize_text_field( isset( $request['date_range'] ) ? $request['date_range'] : '30days' );
        $granularity = sanitize_text_field( isset( $request['granularity'] ) ? $request['granularity'] : 'daily' );
        $campaign_id = isset( $request['campaign_id'] ) ? absint( $request['campaign_id'] ) : 0;

        try {
            // Get trend data from analytics collector
            $trend_data = $this->analytics_collector->get_revenue_trend(
                $date_range,
                $granularity,
                $campaign_id
            );

            // Return data in format expected by frontend Chart.js
            return $this->success( array(
                'labels' => $trend_data['labels'],
                'values' => $trend_data['values'],
                'metadata' => isset( $trend_data['metadata'] ) ? $trend_data['metadata'] : array(),
                'date_range' => $date_range,
                'granularity' => $granularity,
                'campaign_id' => $campaign_id,
                'generated_at' => current_time( 'timestamp' )
            ) );

        } catch (Exception $e) {
            $this->logger->error('Get revenue trend failed', array(
                'error' => $e->getMessage()
            ));

            return $this->error(
                sprintf(__('Failed to generate revenue trend: %s', 'smart-cycle-discounts'), $e->getMessage()),
                'revenue_trend_failed'
            );
        }
    }
}