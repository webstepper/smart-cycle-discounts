<?php
/**
 * Apply Recommendation Handler Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers/class-apply-recommendation-handler.php
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Apply Recommendation Handler Class
 *
 * Applies recommendations to wizard campaign data.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Apply_Recommendation_Handler extends SCD_Abstract_Ajax_Handler {

	/**
	 * State service instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Wizard_State_Service    $state_service    State service.
	 */
	private $state_service;

	/**
	 * Constructor.
	 *
	 * @since    1.0.0
	 * @param    SCD_Logger $logger    Logger instance (optional).
	 */
	public function __construct( $logger = null ) {
		parent::__construct( $logger );
		$this->state_service = null;
	}

	/**
	 * Get AJAX action name.
	 *
	 * @since    1.0.0
	 * @return   string    Action name.
	 */
	protected function get_action_name() {
		return 'scd_apply_recommendation';
	}

	/**
	 * Handle the apply recommendation request.
	 *
	 * @since    1.0.0
	 * @param    array $request    Request data.
	 * @return   array               Response data.
	 */
	protected function handle( $request ) {
		// Validate request data
		if ( ! is_array( $request ) ) {
			$request = array();
		}

		// Get required fields
		$recommendation_id = isset( $request['recommendation_id'] ) ? sanitize_text_field( $request['recommendation_id'] ) : '';
		$action_type       = isset( $request['action_type'] ) ? sanitize_text_field( $request['action_type'] ) : '';
		$action_data       = isset( $request['action_data'] ) ? $request['action_data'] : array();

		if ( empty( $recommendation_id ) || empty( $action_type ) ) {
			return $this->error(
				__( 'Missing required parameters', 'smart-cycle-discounts' ),
				'missing_parameters'
			);
		}

		// Ensure state service is initialized
		if ( ! $this->state_service ) {
			if ( ! class_exists( 'SCD_Wizard_State_Service' ) ) {
				require_once SCD_INCLUDES_DIR . 'core/wizard/class-wizard-state-service.php';
			}
			$this->state_service = new SCD_Wizard_State_Service();
			$this->state_service->initialize_with_intent( 'continue' );
		}

		// Apply the recommendation based on action type
		try {
			$result = $this->apply_action( $action_type, $action_data );

			if ( is_wp_error( $result ) ) {
				return $this->handle_wp_error( $result );
			}

			return $this->success(
				array(
					'message'           => __( 'Recommendation applied successfully', 'smart-cycle-discounts' ),
					'recommendation_id' => $recommendation_id,
					'action_type'       => $action_type,
					'applied_changes'   => $result,
				)
			);

		} catch ( Exception $e ) {
			return $this->error(
				$e->getMessage(),
				'application_error'
			);
		}
	}

	/**
	 * Apply recommendation action to campaign data.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $action_type    Action type.
	 * @param    array  $action_data    Action data.
	 * @return   array|WP_Error            Applied changes or error.
	 */
	private function apply_action( $action_type, $action_data ) {
		$changes = array();

		switch ( $action_type ) {
			case 'change_discount_type':
				$changes = $this->change_discount_type( $action_data );
				break;

			case 'update_discount_value':
				$changes = $this->update_discount_value( $action_data );
				break;

			case 'set_end_date':
				$changes = $this->set_end_date( $action_data );
				break;

			case 'change_selection_type':
				$changes = $this->change_selection_type( $action_data );
				break;

			default:
				return $this->error(
					sprintf( __( 'Invalid action type: %s', 'smart-cycle-discounts' ), $action_type ),
					'invalid_action_type'
				);
		}

		return $changes;
	}

	/**
	 * Change discount type.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $data    Action data.
	 * @return   array             Applied changes.
	 */
	private function change_discount_type( $data ) {
		$discount_type = isset( $data['discount_type'] ) ? sanitize_text_field( $data['discount_type'] ) : '';

		if ( empty( $discount_type ) ) {
			return $this->error(
				__( 'Discount type is required', 'smart-cycle-discounts' ),
				'missing_discount_type'
			);
		}

		// Get existing discount data to merge
		$existing_data = $this->state_service->get_step_data( 'discount' );
		if ( ! is_array( $existing_data ) ) {
			$existing_data = array();
		}

		$discount_data = array_merge(
			$existing_data,
			array(
				'discount_type' => $discount_type,
			)
		);

		// Add discount value if provided (for percentage or fixed)
		if ( isset( $data['discount_value'] ) && $data['discount_value'] > 0 ) {
			$discount_value = floatval( $data['discount_value'] );

			// Save with appropriate field name based on type
			if ( 'percentage' === $discount_type ) {
				$discount_data['discount_value_percentage'] = $discount_value;
			} elseif ( 'fixed' === $discount_type ) {
				$discount_data['discount_value_fixed'] = $discount_value;
			}
		}

		// Add tiers if provided
		if ( 'tiered' === $discount_type && isset( $data['tiers'] ) && is_array( $data['tiers'] ) ) {
			// Sanitize tiers array
			$sanitized_tiers = array();
			foreach ( $data['tiers'] as $tier ) {
				if ( is_array( $tier ) ) {
					$sanitized_tiers[] = array(
						'min_quantity'   => isset( $tier['quantity'] ) ? absint( $tier['quantity'] ) : ( isset( $tier['min_quantity'] ) ? absint( $tier['min_quantity'] ) : 0 ),
						'discount_value' => isset( $tier['discount'] ) ? floatval( $tier['discount'] ) : ( isset( $tier['discount_value'] ) ? floatval( $tier['discount_value'] ) : 0 ),
						'discount_type'  => isset( $tier['discount_type'] ) ? sanitize_text_field( $tier['discount_type'] ) : 'percentage',
					);
				}
			}
			$discount_data['tiers'] = $sanitized_tiers;
		}

		// Add BOGO data if provided
		if ( 'bogo' === $discount_type ) {
			if ( isset( $data['buy_quantity'] ) ) {
				$discount_data['buy_quantity'] = absint( $data['buy_quantity'] );
			}
			if ( isset( $data['get_quantity'] ) ) {
				$discount_data['get_quantity'] = absint( $data['get_quantity'] );
			}
		}

		// Save to discount step
		$this->state_service->save_step_data( 'discount', $discount_data );

		return array(
			'step'           => 'discount',
			'discount_type'  => $discount_type,
			'discount_value' => isset( $discount_data['discount_value_percentage'] ) ? $discount_data['discount_value_percentage'] : ( isset( $discount_data['discount_value_fixed'] ) ? $discount_data['discount_value_fixed'] : null ),
			'tiers'          => isset( $discount_data['tiers'] ) ? $discount_data['tiers'] : null,
		);
	}

	/**
	 * Update discount value.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $data    Action data.
	 * @return   array             Applied changes.
	 */
	private function update_discount_value( $data ) {
		$discount_value = isset( $data['discount_value'] ) ? floatval( $data['discount_value'] ) : 0;

		if ( $discount_value <= 0 ) {
			return $this->error(
				__( 'Discount value must be greater than 0', 'smart-cycle-discounts' ),
				'invalid_discount_value'
			);
		}

		// Get current discount data
		$discount_data = $this->state_service->get_step_data( 'discount' );

		if ( ! is_array( $discount_data ) ) {
			$discount_data = array();
		}

		$discount_data['discount_value'] = $discount_value;

		// Save to discount step
		$this->state_service->save_step_data( 'discount', $discount_data );

		return array(
			'step'           => 'discount',
			'discount_value' => $discount_value,
		);
	}

	/**
	 * Set end date.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $data    Action data.
	 * @return   array             Applied changes.
	 */
	private function set_end_date( $data ) {
		$end_date = isset( $data['end_date'] ) ? sanitize_text_field( $data['end_date'] ) : '';

		if ( empty( $end_date ) ) {
			return $this->error(
				__( 'End date is required', 'smart-cycle-discounts' ),
				'missing_end_date'
			);
		}

		// Get current schedule data
		$schedule_data = $this->state_service->get_step_data( 'schedule' );

		if ( ! is_array( $schedule_data ) ) {
			$schedule_data = array();
		}

		$schedule_data['end_date'] = $end_date;

		// Save to schedule step
		$this->state_service->save_step_data( 'schedule', $schedule_data );

		return array(
			'step'     => 'schedule',
			'end_date' => $end_date,
		);
	}

	/**
	 * Change product selection type.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $data    Action data.
	 * @return   array             Applied changes.
	 */
	private function change_selection_type( $data ) {
		$selection_type = isset( $data['product_selection_type'] ) ? sanitize_text_field( $data['product_selection_type'] ) : '';

		if ( empty( $selection_type ) ) {
			return $this->error(
				__( 'Selection type is required', 'smart-cycle-discounts' ),
				'missing_selection_type'
			);
		}

		// Get current products data
		$products_data = $this->state_service->get_step_data( 'products' );

		if ( ! is_array( $products_data ) ) {
			$products_data = array();
		}

		$products_data['product_selection_type'] = $selection_type;

		// Save to products step
		$this->state_service->save_step_data( 'products', $products_data );

		return array(
			'step'                   => 'products',
			'product_selection_type' => $selection_type,
		);
	}
}
