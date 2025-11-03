<?php
/**
 * Wizard Sidebar Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/wizard/class-wizard-sidebar.php
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Wizard Sidebar Manager Class
 *
 * Manages sidebar content for wizard steps following WordPress patterns.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/wizard
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Wizard_Sidebar {

	/**
	 * Sidebar instances cache.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $instances    Cached sidebar instances.
	 */
	private static array $instances = array();

	/**
	 * Sidebar class mapping.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $sidebar_map    Step to class mapping.
	 */
	private static array $sidebar_map = array(
		'basic'     => 'SCD_Wizard_Sidebar_Basic',
		'products'  => 'SCD_Wizard_Sidebar_Products',
		'discounts' => 'SCD_Wizard_Sidebar_Discounts',
		'schedule'  => 'SCD_Wizard_Sidebar_Schedule',
		'review'    => 'SCD_Wizard_Sidebar_Review',
	);

	/**
	 * Sidebar files mapping.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $files_map    Step to file mapping.
	 */
	private static array $files_map = array(
		'basic'     => 'sidebar-basic.php',
		'products'  => 'sidebar-products.php',
		'discounts' => 'sidebar-discounts.php',
		'schedule'  => 'sidebar-schedule.php',
		'review'    => 'sidebar-review.php',
	);

	/**
	 * Get sidebar content for a wizard step.
	 *
	 * @since    1.0.0
	 * @param    string $step    Step name.
	 * @return   string            Sidebar HTML content.
	 */
	public static function get_sidebar( string $step ): string {
		if ( empty( $step ) ) {
			return '';
		}

		if ( ! isset( self::$sidebar_map[ $step ] ) ) {
			return '';
		}

		$sidebar_instance = self::get_sidebar_instance( $step );
		if ( null === $sidebar_instance ) {
			return '';
		}

		return $sidebar_instance->get_content();
	}

	/**
	 * Get sidebar instance for a step.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $step    Step name.
	 * @return   object|null       Sidebar instance or null.
	 */
	private static function get_sidebar_instance( string $step ): ?object {
		if ( isset( self::$instances[ $step ] ) ) {
			return self::$instances[ $step ];
		}

		$class_name = self::$sidebar_map[ $step ];

		if ( ! class_exists( $class_name ) ) {
			self::load_sidebar_file( $step );
		}

		if ( ! class_exists( $class_name ) ) {
			return null;
		}

		try {
			self::$instances[ $step ] = new $class_name();
			return self::$instances[ $step ];
		} catch ( Exception $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'SCD Wizard Sidebar Error: ' . $e->getMessage() );
			}
			return null;
		}
	}

	/**
	 * Load sidebar file for a step.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $step    Step name.
	 * @return   void
	 */
	private static function load_sidebar_file( string $step ): void {
		if ( ! isset( self::$files_map[ $step ] ) ) {
			return;
		}

		$sidebar_base_file = SCD_INCLUDES_DIR . 'core/wizard/class-sidebar-base.php';
		if ( file_exists( $sidebar_base_file ) ) {
			require_once $sidebar_base_file;
		}

		$sidebar_file = SCD_PLUGIN_DIR . 'resources/views/admin/wizard/' . self::$files_map[ $step ];
		if ( file_exists( $sidebar_file ) ) {
			require_once $sidebar_file;
		}
	}

	/**
	 * Check if step has sidebar.
	 *
	 * @since    1.0.0
	 * @param    string $step    Step name.
	 * @return   bool              True if step has sidebar.
	 */
	public static function has_sidebar( string $step ): bool {
		return isset( self::$sidebar_map[ $step ] );
	}

	/**
	 * Get available sidebar steps.
	 *
	 * @since    1.0.0
	 * @return   array    Available step names.
	 */
	public static function get_available_steps(): array {
		return array_keys( self::$sidebar_map );
	}

	/**
	 * Clear sidebar instances cache.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public static function clear_cache(): void {
		self::$instances = array();
	}
}
