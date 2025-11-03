<?php
/**
 * Check Campaign Name Handler Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers/class-check-campaign-name-handler.php
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
 * Check Campaign Name Handler Class
 *
 * Handles campaign name uniqueness validation.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Check_Campaign_Name_Handler extends SCD_Abstract_Ajax_Handler {

	/**
	 * Campaign manager instance.
	 *
	 * @since    1.0.0
	 * @var      SCD_Campaign_Manager    $campaign_manager    Campaign manager instance.
	 */
	private $campaign_manager;

	/**
	 * Constructor.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign_Manager $campaign_manager    Campaign manager instance.
	 * @param    SCD_Logger           $logger              Logger instance.
	 */
	public function __construct( $campaign_manager = null, $logger = null ) {
		parent::__construct( $logger );
		$this->campaign_manager = $campaign_manager;
	}

	/**
	 * Get AJAX action name.
	 *
	 * @since    1.0.0
	 * @return   string    Action name.
	 */
	protected function get_action_name() {
		return 'scd_check_campaign_name';
	}

	/**
	 * Handle the check campaign name request.
	 *
	 * @since    1.0.0
	 * @param    array $request    Request data.
	 * @return   array                Response data.
	 */
	protected function handle( $request ) {
		$name       = $this->sanitize_text( $this->get_param( $request, 'name' ) );
		$exclude_id = $this->sanitize_int( $this->get_param( $request, 'exclude_id' ) );

		if ( empty( $name ) ) {
			return $this->success(
				array(
					'exists'      => true,
					'unique'      => false,
					'suggestions' => array(),
					'message'     => __( 'Campaign name is required', 'smart-cycle-discounts' ),
				)
			);
		}

		$campaign_manager = $this->get_campaign_manager();

		$exists = false;
		if ( $campaign_manager && method_exists( $campaign_manager, 'campaign_name_exists' ) ) {
			$exists = $campaign_manager->campaign_name_exists( $name, $exclude_id );
		}

		// Generate suggestions if name exists
		$suggestions = array();
		if ( $exists ) {
			$suggestions = $this->generate_suggestions( $name, $exclude_id, $campaign_manager );
		}

		return $this->success(
			array(
				'exists'      => $exists,
				'unique'      => ! $exists,
				'suggestions' => $suggestions,
				'message'     => $exists
					? __( 'A campaign with this name already exists', 'smart-cycle-discounts' )
					: __( 'Campaign name is available', 'smart-cycle-discounts' ),
			)
		);
	}

	/**
	 * Generate name suggestions.
	 *
	 * @since    1.0.0
	 * @param    string $base_name         Base campaign name.
	 * @param    int    $exclude_id        ID to exclude.
	 * @param    mixed  $campaign_manager  Campaign manager instance.
	 * @return   array                        Array of suggestions.
	 */
	private function generate_suggestions( $base_name, $exclude_id, $campaign_manager ) {
		$suggestions = array();

		if ( ! $campaign_manager ) {
			// Simple fallback suggestions
			for ( $i = 2; $i <= 5; $i++ ) {
				$suggestions[] = $base_name . ' ' . $i;
			}
			$suggestions[] = $base_name . ' - ' . date_i18n( 'M j' );
			return $suggestions;
		}

		// Generate numbered suggestions
		for ( $i = 2; $i <= 10; $i++ ) {
			$suggestion = $base_name . ' ' . $i;
			if ( ! $campaign_manager->campaign_name_exists( $suggestion, $exclude_id ) ) {
				$suggestions[] = $suggestion;
				if ( count( $suggestions ) >= 3 ) {
					break;
				}
			}
		}

		if ( count( $suggestions ) < 3 ) {
			$date_suggestion = $base_name . ' - ' . date_i18n( 'M j' );
			if ( ! $campaign_manager->campaign_name_exists( $date_suggestion, $exclude_id ) ) {
				$suggestions[] = $date_suggestion;
			}
		}

		return array_slice( $suggestions, 0, 5 );
	}

	/**
	 * Get campaign manager instance.
	 *
	 * @since    1.0.0
	 * @return   mixed    Campaign manager instance or null.
	 */
	private function get_campaign_manager() {
		// Use injected instance if available
		if ( $this->campaign_manager ) {
			return $this->campaign_manager;
		}

		// Fallback to service locator for modern implementation
		if ( class_exists( 'Smart_Cycle_Discounts' ) && method_exists( 'Smart_Cycle_Discounts', 'get_service' ) ) {
			return Smart_Cycle_Discounts::get_service( 'campaign_manager' );
		}

		return null;
	}
}
