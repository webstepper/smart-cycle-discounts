<?php
/**
 * Concurrent Modification Exception
 *
 * Thrown when optimistic locking detects concurrent modifications.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/exceptions
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Concurrent Modification Exception Class
 *
 * Indicates that a campaign was modified by another user or process
 * after being loaded for editing. Requires user to refresh and retry.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/exceptions
 */
class SCD_Concurrent_Modification_Exception extends Exception {

	/**
	 * Campaign ID that was modified.
	 *
	 * @since    1.0.0
	 * @var      int
	 */
	private $campaign_id;

	/**
	 * Expected version number.
	 *
	 * @since    1.0.0
	 * @var      int
	 */
	private $expected_version;

	/**
	 * Actual current version number.
	 *
	 * @since    1.0.0
	 * @var      int
	 */
	private $current_version;

	/**
	 * Constructor.
	 *
	 * @since    1.0.0
	 * @param    int         $campaign_id        Campaign ID.
	 * @param    int         $expected_version   Expected version.
	 * @param    int         $current_version    Current version.
	 * @param    string      $message            Exception message.
	 * @param    int         $code               Exception code.
	 * @param    Exception   $previous           Previous exception.
	 */
	public function __construct(
		$campaign_id,
		$expected_version,
		$current_version,
		$message = '',
		$code = 0,
		Exception $previous = null
	) {
		$this->campaign_id = $campaign_id;
		$this->expected_version = $expected_version;
		$this->current_version = $current_version;

		if ( empty( $message ) ) {
			$message = sprintf(
				/* translators: 1: campaign ID, 2: expected version, 3: current version */
				__( 'Campaign %1$d was modified by another user (expected version %2$d, current version %3$d). Please refresh and try again.', 'smart-cycle-discounts' ),
				$campaign_id,
				$expected_version,
				$current_version
			);
		}

		parent::__construct( $message, $code, $previous );
	}

	/**
	 * Get campaign ID.
	 *
	 * @since    1.0.0
	 * @return   int    Campaign ID.
	 */
	public function get_campaign_id() {
		return $this->campaign_id;
	}

	/**
	 * Get expected version.
	 *
	 * @since    1.0.0
	 * @return   int    Expected version.
	 */
	public function get_expected_version() {
		return $this->expected_version;
	}

	/**
	 * Get current version.
	 *
	 * @since    1.0.0
	 * @return   int    Current version.
	 */
	public function get_current_version() {
		return $this->current_version;
	}
}
