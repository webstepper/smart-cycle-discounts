<?php
/**
 * Clear Cache Handler Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers/class-clear-cache-handler.php
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
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
class SCD_Clear_Cache_Handler {

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
	 * @var      SCD_Logger
	 */
	private $logger;

	/**
	 * Initialize the handler
	 *
	 * @since    1.0.0
	 * @param    object     $container    Container instance.
	 * @param    SCD_Logger $logger       Logger instance.
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
		// Debug logging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[SCD Clear Cache] Handler called' );
			error_log( '[SCD Clear Cache] Container class: ' . get_class( $this->container ) );
			error_log( '[SCD Clear Cache] method_exists check: ' . ( method_exists( $this->container, 'get_service' ) ? 'true' : 'false' ) );
		}

		// Get cache manager via static service accessor
		$cache_manager = null;
		if ( method_exists( $this->container, 'get_service' ) ) {
			$cache_manager = $this->container::get_service( 'cache_manager' );

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[SCD Clear Cache] cache_manager retrieved: ' . ( $cache_manager ? get_class( $cache_manager ) : 'NULL' ) );
			}
		}

		if ( ! $cache_manager ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[SCD Clear Cache] Cache manager not available - returning error' );
			}

			return array(
				'success' => false,
				'data'    => array(
					'message' => __( 'Cache manager not available', 'smart-cycle-discounts' ),
				),
			);
		}

		// Clear the cache
		try {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[SCD Clear Cache] Calling flush() method' );
			}

			$result = $cache_manager->flush();

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[SCD Clear Cache] flush() returned: ' . ( $result ? 'true' : 'false' ) );
			}

			if ( $result ) {
				// Log the cache clear action
				$this->logger->info(
					'Cache cleared manually from Performance Settings',
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
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( '[SCD Clear Cache] flush() returned false - this should not happen!' );
				}

				return array(
					'success' => false,
					'data'    => array(
						'message' => __( 'Failed to clear cache', 'smart-cycle-discounts' ),
					),
				);
			}
		} catch ( Exception $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[SCD Clear Cache] Exception caught: ' . $e->getMessage() );
			}

			$this->logger->error(
				'Error clearing cache',
				array(
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString(),
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
