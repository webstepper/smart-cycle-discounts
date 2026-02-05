<?php
/**
 * Discounts Step Sidebar Class
 *
 * Provides contextual help for the discounts configuration step.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/wizard/sidebars
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress/plugins/smart-cycle-discounts/
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Discounts step sidebar class
 *
 * Uses the contextual sidebar system to show focus-aware help.
 *
 * @since 1.0.0
 */
class WSSCD_Wizard_Sidebar_Discounts extends WSSCD_Wizard_Sidebar_Base {

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->step           = 'discounts';
		$this->use_contextual = true;
	}
}
