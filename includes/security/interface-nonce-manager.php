<?php
/**
 * Nonce Manager Interface
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
 * Nonce Manager Interface
 *
 * Defines the contract for nonce management implementations.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/security
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
interface SCD_Nonce_Manager_Interface {
    
    /**
     * Verify a nonce.
     *
     * @since    1.0.0
     * @param    string    $nonce     The nonce to verify.
     * @param    string    $action    The action associated with the nonce.
     * @return   bool                 True if valid, false otherwise.
     */
    public function verify(string $nonce, string $action): bool;
    
    /**
     * Create a nonce.
     *
     * @since    1.0.0
     * @param    string    $action    The action for the nonce.
     * @return   string               The generated nonce.
     */
    public function create(string $action): string;
    
    /**
     * Verify a nonce from request data.
     *
     * @since    1.0.0
     * @param    array     $request    Request data containing nonce and action.
     * @param    string    $nonce_key  Key for nonce in request (default: 'nonce').
     * @param    string    $action     Action to verify against (optional).
     * @return   bool                  True if valid, false otherwise.
     */
    public function verify_request(array $request, string $nonce_key = 'nonce', ?string $action = null): bool;
}