<?php
/**
 * Analytics Overview Handler
 *
 * Handles AJAX requests for analytics overview data.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/analytics
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Overview Handler Class
 *
 * @since      1.0.0
 */
class SCD_Overview_Handler extends SCD_Abstract_Analytics_Handler {
	use SCD_License_Validation_Trait;

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
	 * @param    array $request    Request data.
	 * @return   array                Response data.
	 */
	public function handle( $request ) {
		// Check license (logic tier - analytics data is premium feature)
		$license_check = $this->validate_license( 'logic' );
		if ( $this->license_validation_failed( $license_check ) ) {
			return $this->license_error_response( $license_check );
		}

		// Verify request
		$verification = $this->verify_request( $request, 'scd_analytics_overview' );
		if ( is_wp_error( $verification ) ) {
			return $this->error(
				$verification->get_error_message(),
				$verification->get_error_code()
			);
		}

		// Sanitize inputs
		$date_range = sanitize_text_field( isset( $request['date_range'] ) ? $request['date_range'] : '7days' );
		$refresh    = filter_var( isset( $request['refresh'] ) ? $request['refresh'] : false, FILTER_VALIDATE_BOOLEAN );

		try {
			// Get metrics from calculator
			$metrics = $this->metrics_calculator->calculate_overall_metrics( $date_range, ! $refresh );

			$this->logger->debug(
				'Overview data retrieved',
				array(
					'date_range' => $date_range,
					'refresh'    => $refresh,
				)
			);

			return $this->success(
				array(
					'overview'     => $metrics,
					'date_range'   => $date_range,
					'generated_at' => current_time( 'timestamp' ),
					'cache_status' => $refresh ? 'refreshed' : 'cached',
				)
			);

		} catch ( Exception $e ) {
			$this->logger->error(
				'Get analytics overview failed',
				array(
					'date_range' => $date_range,
					'error'      => $e->getMessage(),
				)
			);

			return $this->error(
				sprintf( __( 'Failed to load analytics overview: %s', 'smart-cycle-discounts' ), $e->getMessage() ),
				'analytics_overview_failed'
			);
		}
	}
}
