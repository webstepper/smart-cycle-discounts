<?php
/**
 * Nonce Manager Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/security/class-nonce-manager.php
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
 * Nonce Manager
 *
 * Handles WordPress nonce creation and verification.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/security
 * @author     Webstepper <contact@webstepper.io>
 */
class SCD_Nonce_Manager {

	/**
	 * Create a nonce.
	 *
	 * @since    1.0.0
	 * @param    string $action    Action name.
	 * @return   string              Nonce value.
	 */
	public function create( string $action ): string {
		return wp_create_nonce( $action );
	}

	/**
	 * Verify a nonce.
	 *
	 * @since    1.0.0
	 * @param    string $nonce     Nonce value.
	 * @param    string $action    Action name.
	 * @return   bool                 True if valid.
	 */
	public function verify( string $nonce, string $action ): bool {
		return wp_verify_nonce( $nonce, $action ) !== false;
	}

	/**
	 * Create nonce field.
	 *
	 * @since    1.0.0
	 * @param    string $action    Action name.
	 * @param    string $name      Field name.
	 * @param    bool   $referer   Include referer field.
	 * @param    bool   $echo      Echo or return.
	 * @return   string              Nonce field HTML.
	 */
	public function field( string $action, string $name = '_wpnonce', bool $referer = true, bool $echo = true ): string {
		return wp_nonce_field( $action, $name, $referer, $echo );
	}

	/**
	 * Create nonce URL.
	 *
	 * @since    1.0.0
	 * @param    string $actionurl    URL to add nonce to.
	 * @param    string $action       Action name.
	 * @param    string $name         Nonce name.
	 * @return   string                  URL with nonce.
	 */
	public function url( string $actionurl, string $action = '-1', string $name = '_wpnonce' ): string {
		return wp_nonce_url( $actionurl, $action, $name );
	}
}
