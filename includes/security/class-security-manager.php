<?php
/**
 * Security Manager Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/security/class-security-manager.php
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
 * Security Manager
 *
 * Coordinates all security-related functionality for the plugin.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/security
 * @author     Webstepper <contact@webstepper.io>
 */
class WSSCD_Security_Manager {

	/**
	 * Container instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Container    $container    Container instance.
	 */
	private WSSCD_Container $container;

	// Removed: Input validator - using consolidated WSSCD_Validation class instead

	// Removed: Sanitizer - using consolidated WSSCD_Validation class instead

	/**
	 * Nonce manager instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      object|null    $nonce_manager    Nonce manager.
	 */
	private ?object $nonce_manager = null;

	/**
	 * Rate limiter instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      object|null    $rate_limiter    Rate limiter.
	 */
	private ?object $rate_limiter = null;

	/**
	 * Initialize the security manager.
	 *
	 * @since    1.0.0
	 * @param    WSSCD_Container $container    Container instance.
	 */
	public function __construct( WSSCD_Container $container ) {
		$this->container = $container;
	}

	/**
	 * Initialize security components.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function init(): void {
		// Removed: sanitizer - using consolidated WSSCD_Validation class instead
		$this->nonce_manager = $this->container->get( 'nonce_manager' );
		$this->rate_limiter  = $this->container->get( 'rate_limiter' );
	}

	/**
	 * Validate request security.
	 *
	 * @since    1.0.0
	 * @param    array $request    Request data.
	 * @return   bool                 True if valid.
	 */
	public function validate_request( array $request ): bool {
		if ( ! $this->nonce_manager || ! $this->rate_limiter ) {
			$this->init();
		}

		// For wizard requests, use the correct nonce action
		$nonce_action = null;
		if ( isset( $request['action'] ) && strpos( $request['action'], 'wsscd_wizard' ) === 0 ) {
			$nonce_action = 'wsscd_wizard_nonce';
		}

		if ( ! $this->nonce_manager->verify_request( $request, 'nonce', $nonce_action ) ) {
			return false;
		}

		if ( ! $this->rate_limiter->check_rate_limit() ) {
			return false;
		}

		// Actual validation happens in individual handlers using consolidated WSSCD_Validation class
		return true;
	}



	/**
	 * Get nonce manager.
	 *
	 * @since    1.0.0
	 * @return   object    Nonce manager.
	 */
	public function get_nonce_manager(): object {
		if ( ! $this->nonce_manager ) {
			$this->init();
		}
		return $this->nonce_manager;
	}

	/**
	 * Get rate limiter.
	 *
	 * @since    1.0.0
	 * @return   object    Rate limiter.
	 */
	public function get_rate_limiter(): object {
		if ( ! $this->rate_limiter ) {
			$this->init();
		}
		return $this->rate_limiter;
	}
}
