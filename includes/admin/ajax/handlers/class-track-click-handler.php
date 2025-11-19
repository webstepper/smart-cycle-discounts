<?php
/**
 * Track Click Handler Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers
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
 * Track Click Handler Class
 *
 * Handles discount click tracking via AJAX.
 * Uses lightweight aggregated storage for high-volume frontend tracking.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers
 * @author     Webstepper <contact@webstepper.io>
 */
class SCD_Track_Click_Handler extends SCD_Abstract_Ajax_Handler {

	/**
	 * Constructor.
	 *
	 * @since    1.0.0
	 * @param    SCD_Logger $logger    Logger instance (optional).
	 */
	public function __construct( $logger = null ) {
		parent::__construct( $logger );
	}

	/**
	 * Get AJAX action name.
	 *
	 * @since    1.0.0
	 * @return   string    Action name.
	 */
	protected function get_action_name(): string {
		return 'scd_track_click';
	}

	/**
	 * Check if authentication is required.
	 *
	 * @since    1.0.0
	 * @return   bool    False - clicks can be tracked by non-logged-in users.
	 */
	protected function requires_authentication(): bool {
		return false;
	}

	/**
	 * Handle track click request.
	 *
	 * @since    1.0.0
	 * @param    array $request    Request data.
	 * @return   array                Response data.
	 */
	protected function handle( array $request ): array {
		$container = SCD_Container::get_instance();

		if ( ! $container->has( 'analytics_collector' ) ) {
			return $this->error(
				__( 'Analytics service not available', 'smart-cycle-discounts' ),
				'analytics_not_available',
				503
			);
		}

		$analytics_collector = $container->get( 'analytics_collector' );

		$campaign_id_raw = isset( $request['campaign_id'] ) ? absint( $request['campaign_id'] ) : 0;

		if ( $campaign_id_raw <= 0 ) {
			return $this->error(
				__( 'Campaign ID is required', 'smart-cycle-discounts' ),
				'missing_campaign_id'
			);
		}

		// Optional parameters
		$product_id    = isset( $request['product_id'] ) ? absint( $request['product_id'] ) : 0;
		$click_source  = isset( $request['source'] ) ? sanitize_text_field( $request['source'] ) : '';

		// Track click using optimized method
		if ( method_exists( $analytics_collector, 'track_click' ) ) {
			$result = $analytics_collector->track_click( $campaign_id_raw, $product_id, $click_source );

			if ( $result ) {
				return $this->success(
					array(
						'message'     => __( 'Click tracked', 'smart-cycle-discounts' ),
						'campaign_id' => $campaign_id_raw,
					)
				);
			} else {
				return $this->error(
					__( 'Failed to track click', 'smart-cycle-discounts' ),
					'tracking_failed'
				);
			}
		}

		return $this->error(
			__( 'Track click method not found', 'smart-cycle-discounts' ),
			'method_not_found',
			501
		);
	}
}
