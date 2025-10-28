<?php
/**
 * Security Manager
 *
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/security
 */

declare(strict_types=1);


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
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Security_Manager {

	/**
	 * Container instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Container    $container    Container instance.
	 */
	private SCD_Container $container;

	// Removed: Input validator - using consolidated SCD_Validation class instead

	// Removed: Sanitizer - using consolidated SCD_Validation class instead

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
	 * @param    SCD_Container $container    Container instance.
	 */
	public function __construct( SCD_Container $container ) {
		$this->container = $container;
	}

	/**
	 * Initialize security components.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function init(): void {
		// Get services from container - they're always available
		// Removed: sanitizer - using consolidated SCD_Validation class instead
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
		// Initialize components if not already done
		if ( ! $this->nonce_manager || ! $this->rate_limiter ) {
			$this->init();
		}

		// Validate nonce using standardized method
		// For wizard requests, use the correct nonce action
		$nonce_action = null;
		if ( isset( $request['action'] ) && strpos( $request['action'], 'scd_wizard' ) === 0 ) {
			$nonce_action = 'scd_wizard_nonce';
		}

		if ( ! $this->nonce_manager->verify_request( $request, 'nonce', $nonce_action ) ) {
			return false;
		}

		// Check rate limiting
		if ( ! $this->rate_limiter->check_rate_limit() ) {
			return false;
		}

		// Actual validation happens in individual handlers using consolidated SCD_Validation class
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
