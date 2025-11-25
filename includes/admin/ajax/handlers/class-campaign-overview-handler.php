<?php
/**
 * Campaign Overview AJAX Handler
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers
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
 * Campaign Overview AJAX Handler
 *
 * Handles AJAX requests for loading campaign data into the overview panel.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers
 * @author     Webstepper <contact@webstepper.io>
 */
class SCD_Campaign_Overview_Handler extends SCD_Abstract_Ajax_Handler {

	/**
	 * Campaign repository.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Campaign_Repository    $campaign_repository    Campaign repository.
	 */
	private $campaign_repository;

	/**
	 * Overview panel component.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Campaign_Overview_Panel    $panel    Panel component.
	 */
	private $panel;

	/**
	 * Initialize the handler.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign_Repository      $campaign_repository    Campaign repository.
	 * @param    SCD_Campaign_Overview_Panel  $panel                  Panel component.
	 * @param    SCD_Logger|null              $logger                 Logger instance.
	 */
	public function __construct(
		$campaign_repository,
		$panel,
		$logger = null
	) {
		parent::__construct( $logger );
		$this->campaign_repository = $campaign_repository;
		$this->panel               = $panel;
	}

	/**
	 * Get AJAX action name.
	 *
	 * @since    1.0.0
	 * @return   string    Action name.
	 */
	protected function get_action_name() {
		return 'scd_campaign_overview';
	}

	/**
	 * Handle the AJAX request.
	 *
	 * @since    1.0.0
	 * @param    array $request    Request data.
	 * @return   array               Response array.
	 */
	protected function handle( $request ) {

		// Validate campaign ID
		if ( ! isset( $request['campaign_id'] ) ) {
			return array(
				'success' => false,
				'message' => __( 'Campaign ID is required', 'smart-cycle-discounts' ),
			);
		}

		$campaign_id = absint( $request['campaign_id'] );

		if ( $campaign_id <= 0 ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid campaign ID', 'smart-cycle-discounts' ),
			);
		}

		// Load campaign
		try {
			$campaign = $this->campaign_repository->find( $campaign_id );

			if ( ! $campaign ) {
				return array(
					'success' => false,
					'message' => __( 'Campaign not found', 'smart-cycle-discounts' ),
				);
			}

			// Prepare data for panel
			$data = $this->panel->prepare_campaign_data( $campaign );

			// Render HTML sections
			$html = array(
				'basic'              => $this->render_section( 'basic', $data['basic'] ),
				'health'             => $this->render_section( 'health', $data['health'] ),
				'metrics'            => $this->render_section( 'metrics', $data['performance'] ),
				'schedule'           => $this->render_section( 'schedule', $data['schedule'] ),
				'recurring_schedule' => $this->render_section( 'recurring_schedule', $data['recurring_schedule'] ),
				'products'           => $this->render_section( 'products', $data['products'] ),
				'discounts'          => $this->render_section( 'discounts', $data['discounts'] ),
				'performance'        => $this->render_section( 'performance', $data['performance'] ),
			);

			return array(
				'success' => true,
				'data'    => array(
					'campaign_id' => $campaign_id,
					'campaign'    => $data,
					'sections'    => $html,
				),
			);

		} catch ( Exception $e ) {

			SCD_Log::error(
				'Failed to load campaign for overview panel',
				array(
					'campaign_id' => $campaign_id,
					'error'       => $e->getMessage(),
					'trace'       => $e->getTraceAsString(),
				)
			);

			return array(
				'success' => false,
				'message' => __( 'Failed to load campaign details. Please try again.', 'smart-cycle-discounts' ) . ' Error: ' . $e->getMessage(),
			);
		}
	}

	/**
	 * Render a section and return HTML.
	 *
	 * @since    1.0.0
	 * @param    string $section    Section name.
	 * @param    array  $data       Section data.
	 * @return   string               Rendered HTML.
	 */
	private function render_section( $section, $data ) {
		ob_start();

		switch ( $section ) {
			case 'basic':
				$this->panel->render_basic_section( $data );
				break;
			case 'health':
				$this->panel->render_health_section( $data );
				break;
			case 'metrics':
				$this->panel->render_metrics_section( $data );
				break;
			case 'schedule':
				$this->panel->render_schedule_section( $data );
				break;
			case 'recurring_schedule':
				$this->panel->render_recurring_schedule_section( $data );
				break;
			case 'products':
				$this->panel->render_products_section( $data );
				break;
			case 'discounts':
				$this->panel->render_discounts_section( $data );
				break;
			case 'performance':
				$this->panel->render_performance_section( $data );
				break;
		}

		return ob_get_clean();
	}
}
