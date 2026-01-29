<?php
/**
 * Get Shipping Methods Handler Class
 *
 * Returns available WooCommerce shipping methods for the free shipping configuration UI.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Get Shipping Methods Handler
 *
 * Retrieves all available shipping methods from WooCommerce shipping zones
 * for use in the campaign wizard free shipping configuration.
 *
 * @since      1.2.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax
 * @author     Webstepper <contact@webstepper.io>
 */
class WSSCD_Get_Shipping_Methods_Handler extends WSSCD_Abstract_Ajax_Handler {

	/**
	 * Constructor.
	 *
	 * @since    1.2.0
	 * @param    WSSCD_Logger $logger    Logger instance (optional).
	 */
	public function __construct( $logger = null ) {
		parent::__construct( $logger );
	}

	/**
	 * Get AJAX action name.
	 *
	 * @since    1.2.0
	 * @return   string    Action name.
	 */
	protected function get_action_name() {
		return 'wsscd_get_shipping_methods';
	}

	/**
	 * Handle the request to get shipping methods.
	 *
	 * @since    1.2.0
	 * @param    array $request    Request data.
	 * @return   array             Response with shipping methods.
	 */
	protected function handle( $request ) {
		// Use the static method from the Free Shipping Handler.
		$methods = WSSCD_WC_Free_Shipping_Handler::get_available_shipping_methods();

		return $this->success(
			array(
				'methods' => $methods,
			)
		);
	}
}
