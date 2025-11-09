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
	 * @return SCD_Container Container instance.
	 */
	public static function get_container() {
		// Try to get existing instance from global
		if ( isset( $GLOBALS['scd_container'] ) && $GLOBALS['scd_container'] instanceof SCD_Container ) {
			return $GLOBALS['scd_container'];
		}

		// Try to get from main plugin class
		if ( isset( $GLOBALS['smart_cycle_discounts'] ) ) {
			$plugin = $GLOBALS['smart_cycle_discounts'];
			if ( method_exists( $plugin, 'get_container' ) ) {
				return $plugin->get_container();
			}
		}

		// Try static method if available
		if ( class_exists( 'SCD_Container' ) && method_exists( 'SCD_Container', 'get_instance' ) ) {
			return SCD_Container::get_instance();
		}

		// Last resort: create new instance
		if ( class_exists( 'SCD_Container' ) ) {
			$container = new SCD_Container( false );
			$GLOBALS['scd_container'] = $container;
			return $container;
		}

		throw new RuntimeException( 'Could not initialize SCD_Container for tests' );
	}
}
