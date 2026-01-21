<?php
/**
 * Clear Cache Handler Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers/class-clear-cache-handler.php
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
 * Clear Cache AJAX Handler
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers
 */
class WSSCD_Clear_Cache_Handler {

	/**
	 * Container instance
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      object
	 */
	private $container;

	/**
	 * Logger instance
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Logger
	 */
	private $logger;

	/**
	 * Initialize the handler
	 *
	 * @since    1.0.0
	 * @param    object     $container    Container instance.
	 * @param    WSSCD_Logger $logger       Logger instance.
	 */
	public function __construct( $container, $logger ) {
		$this->container = $container;
		$this->logger    = $logger;
	}

	/**
	 * Handle the request
	 *
	 * @since    1.0.0
	 * @param    array $data    Request data.
	 * @return   array             Response data.
	 */
	public function handle( $data ) {
		// Get cache manager from service container
		$cache_manager = Smart_Cycle_Discounts::get_service( 'cache_manager' );

		if ( ! $cache_manager ) {
			$this->logger->warning( 'Cache clear failed: cache manager not available' );

			return array(
				'success' => false,
				'data'    => array(
					'message' => __( 'Cache manager not available', 'smart-cycle-discounts' ),
				),
			);
		}

		try {
			$result = $cache_manager->flush();

			if ( $result ) {
				// Log the cache clear action
				$this->logger->info(
					'Cache cleared manually from Tools page',
					array(
						'user_id' => get_current_user_id(),
					)
				);

				return array(
					'success' => true,
					'data'    => array(
						'message' => __( 'Cache cleared successfully!', 'smart-cycle-discounts' ),
					),
				);
			} else {
				$this->logger->warning( 'Cache flush returned false' );

				return array(
					'success' => false,
					'data'    => array(
						'message' => __( 'Failed to clear cache', 'smart-cycle-discounts' ),
					),
				);
			}
		} catch ( Exception $e ) {
			$this->logger->error(
				'Error clearing cache',
				array(
					'error' => $e->getMessage(),
				)
			);

			return array(
				'success' => false,
				'data'    => array(
					/* translators: %s: error message */
					'message' => sprintf( __( 'Error clearing cache: %s', 'smart-cycle-discounts' ), esc_html( $e->getMessage() ) ),
				),
			);
		}
	}
}
