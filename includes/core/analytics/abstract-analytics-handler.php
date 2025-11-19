<?php
/**
 * Abstract Analytics Handler
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/analytics/abstract-analytics-handler.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Abstract Analytics Handler
 *
 * @since      1.0.0
 */
abstract class SCD_Abstract_Analytics_Handler {

	/**
	 * Metrics calculator instance.
	 *
	 * @since    1.0.0
	 * @var      SCD_Metrics_Calculator
	 */
	protected SCD_Metrics_Calculator $metrics_calculator;

	/**
	 * Logger instance.
	 *
	 * @since    1.0.0
	 * @var      SCD_Logger
	 */
	protected SCD_Logger $logger;

	/**
	 * Initialize the handler.
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
	 * Handle the AJAX request.
	 *
	 * @since    1.0.0
	 * @param    array $request    Request data.
	 * @return   array                Response data.
	 */
	abstract public function handle( array $request ): array;

	/**
	 * Get required capability.
	 *
	 * @since    1.0.0
	 * @return   string    Required capability.
	 */
	abstract protected function get_required_capability(): string;

	/**
	 * Verify the request.
	 *
	 * @since    1.0.0
	 * @param    array  $request    Request data.
	 * @param    string $action     Action name.
	 * @return   true|WP_Error          True on success, WP_Error on failure.
	 */
	protected function verify_request( array $request, string $action ): bool|WP_Error {
		// Security check
		$result = SCD_Ajax_Security::verify_ajax_request( $action, $request );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Capability check
		if ( ! current_user_can( $this->get_required_capability() ) ) {
			return new WP_Error(
				'insufficient_permissions',
				__( 'You do not have permission to perform this action.', 'smart-cycle-discounts' )
			);
		}

		return true;
	}

	/**
	 * Return success data.
	 *
	 * @since    1.0.0
	 * @param    array $data    Response data.
	 * @return   array             Success response array.
	 */
	protected function success( array $data ): array {
		return array(
			'success' => true,
			'data'    => $data,
		);
	}

	/**
	 * Return error response.
	 *
	 * @since    1.0.0
	 * @param    string $message    Error message.
	 * @param    string $code       Error code.
	 * @return   array                Error response array.
	 */
	protected function error( string $message, string $code = 'error' ): array {
		return array(
			'success' => false,
			'error'   => array(
				'code'    => $code,
				'message' => $message,
			),
		);
	}
}
