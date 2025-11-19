<?php
/**
 * Load Data Handler Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers/class-load-data-handler.php
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
 * Load Data Handler Class
 *
 * Handles loading saved wizard data.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers
 * @author     Webstepper <contact@webstepper.io>
 */
class SCD_Load_Data_Handler extends SCD_Abstract_Ajax_Handler {

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
		return 'scd_load_data';
	}

	/**
	 * Handle the load data request.
	 *
	 * @since    1.0.0
	 * @param    array $request    Request data.
	 * @return   array               Response data.
	 */
	protected function handle( $request ) {
		$step     = sanitize_key( isset( $request['step'] ) ? $request['step'] : '' );
		$load_all = ! empty( $request['load_all'] );

		// Debug: Log load request
		if ( function_exists( 'scd_debug_persistence' ) ) {
			scd_debug_persistence(
				'load_request',
				$step ? $step : 'all',
				array(
					'load_all'    => $load_all,
					'has_session' => $this->state_service->has_session(),
				),
				true,
				'Data load requested'
			);
		}

		if ( ! $this->state_service->has_session() ) {
			// Debug: Log no session found
			if ( function_exists( 'scd_debug_persistence' ) ) {
				scd_debug_persistence( 'load_error', $step ? $step : 'all', array(), false, 'No saved session found' );
			}

			return $this->error(
				__( 'No saved session found', 'smart-cycle-discounts' ),
				'no_session_found'
			);
		}

		if ( $load_all ) {
			$data = $this->state_service->get_all_data();
		} elseif ( ! empty( $step ) ) {
			$data = $this->state_service->get_step_data( $step );
		} else {
			$data = $this->state_service->get_all_data();
		}

		// Enrich data with additional information needed by UI components
		if ( ! empty( $step ) ) {
			$data = $this->enrich_step_data( $step, $data );
		} elseif ( $load_all && isset( $data['steps'] ) ) {
			// Enrich all steps if loading all data
			foreach ( $data['steps'] as $step_name => $step_data ) {
				$data['steps'][ $step_name ] = $this->enrich_step_data( $step_name, $step_data );
			}
		}

		// Debug: Log loaded data
		if ( function_exists( 'scd_debug_persistence' ) ) {
			scd_debug_persistence( 'load_success', $step ? $step : 'all', $data, true, 'Data loaded from session' );
		}

		$progress = $this->state_service->get_progress();

		return $this->success(
			array(
				'data'        => $data,
				'progress'    => $progress,
				'session_age' => $this->state_service->get_session_age(),
				'message'     => __( 'Data loaded successfully', 'smart-cycle-discounts' ),
			)
		);
	}

	/**
	 * Enrich step data with additional information needed by UI components.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $step    Step name.
	 * @param    array  $data    Step data.
	 * @return   array              Enriched step data.
	 */
	private function enrich_step_data( $step, $data ) {
		if ( 'products' === $step ) {
			return $this->enrich_products_data( $data );
		}

		return $data;
	}

	/**
	 * Enrich products step data with full category objects.
	 * This ensures category names and counts are available for TomSelect restoration.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $data    Products step data.
	 * @return   array              Enriched products data.
	 */
	private function enrich_products_data( $data ) {
		// Enrich category_ids with full category data
		if ( ! empty( $data['category_ids'] ) && is_array( $data['category_ids'] ) ) {
			$category_data = array();

			foreach ( $data['category_ids'] as $cat_id ) {
				// Skip 'all' - it's a special value, not a real category ID
				if ( 'all' === $cat_id ) {
					continue;
				}

				$category = get_term( (int) $cat_id, 'product_cat' );
				if ( $category && ! is_wp_error( $category ) ) {
					$category_data[] = array(
						'id'    => $category->term_id,
						'name'  => $category->name,
						'count' => $category->count,
						'level' => 0,
					);
				}
			}

			if ( ! empty( $category_data ) ) {
				$data['category_data'] = $category_data;
			}
		}

		return $data;
	}
}
