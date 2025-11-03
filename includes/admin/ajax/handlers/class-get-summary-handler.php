<?php
/**
 * Get Summary Handler Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers/class-get-summary-handler.php
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
 * Get Summary Handler Class
 *
 * Handles retrieving wizard summary data for review step.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Get_Summary_Handler extends SCD_Abstract_Ajax_Handler {

	/**
	 * State service instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Wizard_State_Service    $state_service    State service.
	 */
	private $state_service;

	/**
	 * Initialize the handler.
	 *
	 * @since    1.0.0
	 * @param    SCD_Wizard_State_Service $state_service    State service.
	 * @param    SCD_Logger               $logger           Logger instance (optional).
	 */
	public function __construct( $state_service, $logger = null ) {
		parent::__construct( $logger );
		$this->state_service = $state_service;
	}

	/**
	 * Get AJAX action name.
	 *
	 * @since    1.0.0
	 * @return   string    Action name.
	 */
	protected function get_action_name() {
		return 'scd_get_summary';
	}

	/**
	 * Handle the get summary request.
	 *
	 * @since    1.0.0
	 * @param    array $request    Request data.
	 * @return   array               Response data.
	 */
	protected function handle( $request ) {
		$steps = array(
			'basic'     => $this->state_service->get_step_data( 'basic' ),
			'products'  => $this->state_service->get_step_data( 'products' ),
			'discounts' => $this->state_service->get_step_data( 'discounts' ),
			'schedule'  => $this->state_service->get_step_data( 'schedule' ),
			'review'    => $this->state_service->get_step_data( 'review' ),
		);

		$has_data = false;
		foreach ( $steps as $step_data ) {
			if ( ! empty( $step_data ) ) {
				$has_data = true;
				break;
			}
		}

		if ( ! $has_data ) {
			return $this->error(
				__( 'No campaign data found', 'smart-cycle-discounts' ),
				'no_campaign_data'
			);
		}

		$summary = $this->build_summary( $steps );

		$progress = $this->state_service->get_progress();

		return $this->success(
			array(
				'summary'      => $summary,
				'progress'     => $progress,
				'can_complete' => $progress['can_complete'],
				'message'      => __( 'Summary loaded successfully', 'smart-cycle-discounts' ),
			)
		);
	}

	/**
	 * Build summary from step data.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $steps    Step data.
	 * @return   array              Summary data.
	 */
	private function build_summary( $steps ) {
		$summary = array();

		// Basic information
		if ( ! empty( $steps['basic'] ) ) {
			$summary['basic'] = array(
				'campaign_name' => isset( $steps['basic']['campaign_name'] ) ? $steps['basic']['campaign_name'] : __( 'Untitled Campaign', 'smart-cycle-discounts' ),
				'description'   => isset( $steps['basic']['description'] ) ? $steps['basic']['description'] : '',
				'status'        => isset( $steps['basic']['status'] ) ? $steps['basic']['status'] : 'draft',
				'priority'      => isset( $steps['basic']['priority'] ) ? $steps['basic']['priority'] : 3,
			);
		}

		// Products information
		if ( ! empty( $steps['products'] ) ) {
			$product_ids  = isset( $steps['products']['product_ids'] ) ? $steps['products']['product_ids'] : array();
			$category_ids = isset( $steps['products']['category_ids'] ) ? $steps['products']['category_ids'] : array();

			$summary['products'] = array(
				'product_selection_type' => isset( $steps['products']['product_selection_type'] ) ? $steps['products']['product_selection_type'] : 'manual',
				'product_count'          => count( $product_ids ),
				'product_ids'            => $product_ids,
				'category_count'         => count( $category_ids ),
				'category_ids'           => $category_ids,
			);

			if ( ! empty( $summary['products']['product_ids'] ) && function_exists( 'wc_get_product' ) ) {
				$summary['products']['product_names'] = $this->get_product_names( $summary['products']['product_ids'] );
			}
		}

		// Discount information
		if ( ! empty( $steps['discounts'] ) ) {
			$discount_type = isset( $steps['discounts']['discount_type'] ) ? $steps['discounts']['discount_type'] : 'percentage';

			$discount_value = 0;
			if ( 'percentage' === $discount_type && isset( $steps['discounts']['discount_value_percentage'] ) ) {
				$discount_value = $steps['discounts']['discount_value_percentage'];
			} elseif ( 'fixed' === $discount_type && isset( $steps['discounts']['discount_value_fixed'] ) ) {
				$discount_value = $steps['discounts']['discount_value_fixed'];
			} elseif ( isset( $steps['discounts']['discount_value'] ) ) {
				// Fallback to combined field if present
				$discount_value = $steps['discounts']['discount_value'];
			}

			$summary['discounts'] = array(
				'discount_type'  => $discount_type,
				'discount_value' => $discount_value,
				'enable_tiered'  => ! empty( $steps['discounts']['enable_tiered'] ),
				'tiers'          => isset( $steps['discounts']['tiers'] ) ? $steps['discounts']['tiers'] : array(),
				'badge_enabled'  => ! empty( $steps['discounts']['badge_enabled'] ),
				'badge_text'     => isset( $steps['discounts']['badge_text'] ) ? $steps['discounts']['badge_text'] : '',
			);
		}

		// Schedule information
		if ( ! empty( $steps['schedule'] ) ) {
			$summary['schedule'] = array(
				'start_date'        => isset( $steps['schedule']['start_date'] ) ? $steps['schedule']['start_date'] : '',
				'end_date'          => isset( $steps['schedule']['end_date'] ) ? $steps['schedule']['end_date'] : '',
				'enable_time_limit' => ! empty( $steps['schedule']['enable_time_limit'] ),
				'recurring'         => ! empty( $steps['schedule']['recurring'] ),
				'recurring_pattern' => isset( $steps['schedule']['recurring_pattern'] ) ? $steps['schedule']['recurring_pattern'] : '',
			);

			if ( ! empty( $summary['schedule']['start_date'] ) ) {
				$summary['schedule']['start_date_formatted'] = date_i18n(
					get_option( 'date_format' ),
					strtotime( $summary['schedule']['start_date'] )
				);
			}
			if ( ! empty( $summary['schedule']['end_date'] ) ) {
				$summary['schedule']['end_date_formatted'] = date_i18n(
					get_option( 'date_format' ),
					strtotime( $summary['schedule']['end_date'] )
				);
			}
		}

		return $summary;
	}

	/**
	 * Get product names from IDs.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $product_ids    Product IDs.
	 * @return   array                    Product names.
	 */
	private function get_product_names( $product_ids ) {
		$names = array();

		foreach ( array_slice( $product_ids, 0, 10 ) as $id ) { // Limit to 10 for performance
			$product = wc_get_product( $id );
			if ( $product ) {
				$names[] = $product->get_name();
			}
		}

		if ( count( $product_ids ) > 10 ) {
			$names[] = sprintf(
				__( 'and %d more...', 'smart-cycle-discounts' ),
				count( $product_ids ) - 10
			);
		}

		return $names;
	}
}
