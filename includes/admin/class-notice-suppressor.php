<?php
/**
 * Notice Suppressor Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/class-notice-suppressor.php
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
 * Notice Suppressor Class
 *
 * Removes third-party admin notices from plugin pages while preserving
 * native WordPress notices and Smart Cycle Discounts notices.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Notice_Suppressor {

	/**
	 * Initialize notice suppression.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function init() {
		// Run with high priority to execute before other plugins' notices
		add_action( 'admin_head', array( $this, 'suppress_third_party_notices' ), 1 );
	}

	/**
	 * Suppress third-party admin notices on plugin pages.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function suppress_third_party_notices() {
		// Only suppress on our plugin pages
		if ( ! $this->is_plugin_page() ) {
			return;
		}

		$this->remove_third_party_notice_hooks();
	}

	/**
	 * Check if current page is a plugin admin page.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   bool    True if on plugin page.
	 */
	private function is_plugin_page() {
		$screen = get_current_screen();

		if ( ! $screen ) {
			return false;
		}

		if ( false !== strpos( $screen->id, 'smart-cycle-discounts' ) || false !== strpos( $screen->id, 'scd-' ) ) {
			return true;
		}

		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

		if ( false !== strpos( $page, 'smart-cycle-discounts' ) || false !== strpos( $page, 'scd-' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Remove third-party admin_notices hooks while preserving core and plugin notices.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function remove_third_party_notice_hooks() {
		global $wp_filter;

		if ( ! isset( $wp_filter['admin_notices'] ) ) {
			return;
		}

		$notice_hooks = $wp_filter['admin_notices'];

		// Whitelist: These are allowed to show notices
		$allowed_prefixes = array(
			'SCD_',              // Our plugin classes
			'scd_',              // Our plugin functions
			'_',                 // WordPress core (internal functions)
			'WP_',               // WordPress core classes
			'do_settings_errors', // WordPress settings errors
			'settings_errors',   // WordPress settings errors
		);

		// Whitelist for Freemius (our licensing provider)
		$allowed_freemius = array(
			'Freemius',
		);

		foreach ( $notice_hooks as $priority => $hooks ) {
			foreach ( $hooks as $hook_name => $hook_data ) {
				$should_remove = true;

				$callback = $hook_data['function'];

				// Determine callback identifier
				if ( is_array( $callback ) ) {
					// Class method callback
					if ( is_object( $callback[0] ) ) {
						$class_name = get_class( $callback[0] );
					} elseif ( is_string( $callback[0] ) ) {
						$class_name = $callback[0];
					} else {
						$class_name = '';
					}

					foreach ( $allowed_prefixes as $prefix ) {
						if ( 0 === strpos( $class_name, $prefix ) ) {
							$should_remove = false;
							break;
						}
					}

					// Check Freemius separately
					if ( $should_remove ) {
						foreach ( $allowed_freemius as $freemius_class ) {
							if ( false !== strpos( $class_name, $freemius_class ) ) {
								$should_remove = false;
								break;
							}
						}
					}
				} elseif ( is_string( $callback ) ) {
					// Function callback
					foreach ( $allowed_prefixes as $prefix ) {
						if ( 0 === strpos( $callback, $prefix ) ) {
							$should_remove = false;
							break;
						}
					}
				}

				if ( $should_remove ) {
					remove_action( 'admin_notices', $callback, $priority );
				}
			}
		}
	}
}
