<?php
/**
 * Wizard Sidebar Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/wizard/class-wizard-sidebar.php
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
 * Wizard Sidebar Manager Class
 *
 * Manages sidebar content for wizard steps following WordPress patterns.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/wizard
 * @author     Webstepper <contact@webstepper.io>
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
		'basic'     => 'class-sidebar-basic.php',
		'products'  => 'class-sidebar-products.php',
		'discounts' => 'class-sidebar-discounts.php',
		'schedule'  => 'class-sidebar-schedule.php',
		'review'    => 'class-sidebar-review.php',
	);

	/**
	 * Dependency instances for injection.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $dependencies    Dependency instances by step.
	 */
	private static array $dependencies = array();

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

		// Allow filtering of sidebar map for extensibility
		$sidebar_map = apply_filters( 'scd_wizard_sidebar_map', self::$sidebar_map );

		if ( ! isset( $sidebar_map[ $step ] ) ) {
			return '';
		}

		$sidebar_instance = self::get_sidebar_instance( $step, $sidebar_map );
		if ( null === $sidebar_instance ) {
			return '';
		}

		$content = $sidebar_instance->get_content();

		// Allow filtering of final sidebar content
		return apply_filters( 'scd_wizard_sidebar_content', $content, $step, $sidebar_instance );
	}

	/**
	 * Set dependency for a specific sidebar step.
	 *
	 * Allows dependency injection for sidebars that need external services.
	 *
	 * @since    1.0.0
	 * @param    string $step       Step name.
	 * @param    object $dependency Dependency instance to inject.
	 * @return   void
	 */
	public static function set_dependency( string $step, $dependency ): void {
		self::$dependencies[ $step ] = $dependency;
	}

	/**
	 * Get sidebar instance for a step.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $step        Step name.
	 * @param    array  $sidebar_map Sidebar class map.
	 * @return   object|null           Sidebar instance or null.
	 */
	private static function get_sidebar_instance( string $step, array $sidebar_map ): ?object {
		if ( isset( self::$instances[ $step ] ) ) {
			return self::$instances[ $step ];
		}

		$class_name = $sidebar_map[ $step ];

		// Ensure class is loaded
		if ( ! class_exists( $class_name ) ) {
			self::load_sidebar_file( $step );
		}

		if ( ! class_exists( $class_name ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( sprintf( 'SCD Wizard Sidebar: Class %s not found for step %s', $class_name, $step ) );
			}
			return null;
		}

		try {
			if ( isset( self::$dependencies[ $step ] ) ) {
				self::$instances[ $step ] = new $class_name( self::$dependencies[ $step ] );
			} else {
				self::$instances[ $step ] = new $class_name();
			}

			return self::$instances[ $step ];
		} catch ( Exception $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( sprintf( 'SCD Wizard Sidebar Error: Failed to instantiate %s - %s', $class_name, $e->getMessage() ) );
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
		// Allow filtering of files map for extensibility
		$files_map = apply_filters( 'scd_wizard_sidebar_files_map', self::$files_map );

		if ( ! isset( $files_map[ $step ] ) ) {
			return;
		}

		$sidebar_base_file = SCD_INCLUDES_DIR . 'core/wizard/class-sidebar-base.php';
		if ( file_exists( $sidebar_base_file ) ) {
			require_once $sidebar_base_file;
		}

		$sidebar_file = SCD_INCLUDES_DIR . 'core/wizard/sidebars/' . $files_map[ $step ];
		if ( file_exists( $sidebar_file ) ) {
			require_once $sidebar_file;
		} else {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( sprintf( 'SCD Wizard Sidebar: File not found - %s', $sidebar_file ) );
			}
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
		$sidebar_map = apply_filters( 'scd_wizard_sidebar_map', self::$sidebar_map );
		return isset( $sidebar_map[ $step ] );
	}

	/**
	 * Get available sidebar steps.
	 *
	 * @since    1.0.0
	 * @return   array    Available step names.
	 */
	public static function get_available_steps(): array {
		$sidebar_map = apply_filters( 'scd_wizard_sidebar_map', self::$sidebar_map );
		return array_keys( $sidebar_map );
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
