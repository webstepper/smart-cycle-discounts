<?php
/**
 * Test Container Helper
 *
 * Ensures Container class is properly initialized for tests.
 *
 * @package Smart_Cycle_Discounts\Tests
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Test Container Helper Class
 *
 * Provides a reliable way to get Container instance in tests.
 */
class Test_Container_Helper {

	/**
	 * Get or create Container instance for tests.
	 *
	 * @return WSSCD_Container Container instance.
	 */
	public static function get_container() {
		// Try to get existing instance from global
		if ( isset( $GLOBALS['wsscd_container'] ) && $GLOBALS['wsscd_container'] instanceof WSSCD_Container ) {
			return $GLOBALS['wsscd_container'];
		}

		// Try to get from main plugin class
		if ( isset( $GLOBALS['wsscd_plugin'] ) ) {
			$plugin = $GLOBALS['wsscd_plugin'];
			if ( method_exists( $plugin, 'get_container' ) ) {
				return $plugin->get_container();
			}
		}

		// Try static method if available
		if ( class_exists( 'WSSCD_Container' ) && method_exists( 'WSSCD_Container', 'get_instance' ) ) {
			return WSSCD_Container::get_instance();
		}

		// Last resort: create new instance with service definitions
		if ( class_exists( 'WSSCD_Container' ) ) {
			$container = new WSSCD_Container( false );

			// Load service definitions if Service Registry is available
			if ( class_exists( 'WSSCD_Service_Registry' ) ) {
				$registry = new WSSCD_Service_Registry( $container );
				$registry->register_all_services();
			}

			$GLOBALS['wsscd_container'] = $container;
			return $container;
		}

		throw new RuntimeException( 'Could not initialize WSSCD_Container for tests' );
	}
}
