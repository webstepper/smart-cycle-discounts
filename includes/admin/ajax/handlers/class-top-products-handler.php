<?php
/**
 * Top Products Handler Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers/class-top-products-handler.php
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Top Products Handler Class
 *
 * @since      1.0.0
 */
class SCD_Top_Products_Handler extends SCD_Abstract_Analytics_Handler {
	use SCD_License_Validation_Trait;

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
	 * @param    SCD_Metrics_Calculator  $metrics_calculator     Metrics calculator.
	 * @param    SCD_Logger              $logger                 Logger instance.
	 * @param    SCD_Analytics_Collector $analytics_collector    Analytics collector.
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
		$verification = $this->verify_request( $request, 'scd_analytics_top_products' );
		if ( is_wp_error( $verification ) ) {
			return $this->error(
				$verification->get_error_message(),
				$verification->get_error_code()
			);
		}

		// Sanitize inputs
		$date_range = sanitize_text_field( isset( $request['date_range'] ) ? $request['date_range'] : '30days' );
		$limit      = isset( $request['limit'] ) ? absint( $request['limit'] ) : 10;

		try {
			// Get top products data from analytics collector
			$products_data = $this->analytics_collector->get_top_products_by_revenue(
				$date_range,
				$limit
			);

			return $this->success(
				array(
					'products'     => $products_data['products'],
					'period'       => $products_data['period'],
					'generated_at' => isset( $products_data['generated_at'] ) ? $products_data['generated_at'] : current_time( 'timestamp' ),
				)
			);

		} catch ( Exception $e ) {
			$this->logger->error(
				'Get top products failed',
				array(
					'error' => $e->getMessage(),
				)
			);

			return $this->error(
				sprintf( __( 'Failed to retrieve top products: %s', 'smart-cycle-discounts' ), $e->getMessage() ),
				'top_products_failed'
			);
		}
	}
}
