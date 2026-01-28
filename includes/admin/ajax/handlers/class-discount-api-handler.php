<?php
/**
 * Discount Api Handler Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers/class-discount-api-handler.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Discount API Handler Class
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers
 */
class WSSCD_Discount_API_Handler implements WSSCD_Ajax_Handler {

	// Removed: validation_service - using consolidated WSSCD_Validation class instead

	/**
	 * Discount engine
	 *
	 * @var WSSCD_Discount_Engine
	 */
	private $discount_engine;

	/**
	 * State service
	 *
	 * @var WSSCD_Wizard_State_Service
	 */
	private $state_service;

	/**
	 * Constructor
	 */
	public function __construct() {
		$container = WSSCD_Core_Container::get_instance();

		// Removed: validation_service - using consolidated WSSCD_Validation class instead
		$this->state_service = $container->get( 'wizard.state_service' );

		if ( ! class_exists( 'WSSCD_Discount_Engine' ) ) {
			require_once WSSCD_INCLUDES_DIR . 'core/discounts/class-discount-engine.php';
		}
		$this->discount_engine = new WSSCD_Discount_Engine();
	}

	/**
	 * Handle the request based on sub-action
	 *
	 * @param array $request Request data
	 * @return array Response data
	 */
	public function handle( array $request ) {
		// Nonce verification is handled by AJAX Router (class-ajax-router.php:170)
		// No need for redundant check here - follows DRY principle

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return array(
				'success' => false,
				'message' => __( 'Insufficient permissions', 'smart-cycle-discounts' ),
			);
		}

		$action = isset( $request['action'] ) ? sanitize_key( $request['action'] ) : '';
		$action = str_replace( 'wsscd_', '', $action );

		// Security: Whitelist of allowed actions
		$allowed_actions = array( 'validate_discount_rules', 'get_discount_preview', 'calculate_discount_impact' );

		if ( ! in_array( $action, $allowed_actions, true ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid discount action', 'smart-cycle-discounts' ),
			);
		}

		switch ( $action ) {
			case 'validate_discount_rules':
				return $this->validate_rules( $request );

			case 'get_discount_preview':
				return $this->get_preview( $request );

			case 'calculate_discount_impact':
				return $this->calculate_impact( $request );
		}
	}

	/**
	 * Validate discount rules
	 *
	 * @param array $request Request data
	 * @return array Validation results
	 */
	private function validate_rules( array $request ) {
		// Defensive programming: ensure rules is an array
		$rules = isset( $request['rules'] ) && is_array( $request['rules'] ) ? $request['rules'] : array();

		// Use consolidated WSSCD_Validation class for validation
		$validation_context = 'wizard_discounts';
		$validation_result  = WSSCD_Validation::validate( array( 'rules' => $rules ), $validation_context );

		if ( is_wp_error( $validation_result ) ) {
			return array(
				'success' => false,
				'valid'   => false,
				'errors'  => $validation_result->get_error_messages(),
				'message' => __( 'Validation failed', 'smart-cycle-discounts' ),
			);
		}

		return array(
			'success'  => true,
			'valid'    => true,
			'errors'   => array(),
			'warnings' => $this->get_warnings_for_rules( $rules ),
			'message'  => __( 'Validation passed', 'smart-cycle-discounts' ),
		);
	}

	/**
	 * Generate discount preview
	 *
	 * @param array $request Request data
	 * @return array Preview data
	 */
	private function get_preview( array $request ) {
		// Defensive programming: ensure config is an array
		$config = isset( $request['config'] ) && is_array( $request['config'] ) ? $request['config'] : array();

		if ( empty( $config['discount_type'] ) ) {
			return array(
				'success' => false,
				'message' => __( 'Discount type is required', 'smart-cycle-discounts' ),
			);
		}

		if ( ! $this->discount_engine ) {
			return array(
				'success' => false,
				'message' => __( 'Discount engine not available', 'smart-cycle-discounts' ),
			);
		}

		$sample_product = $this->get_sample_product();

		if ( ! $sample_product ) {
			return array(
				'success' => false,
				'message' => __( 'No sample product available for preview', 'smart-cycle-discounts' ),
			);
		}

		// Get price from product - handle both WC_Product objects and mock objects
		$sample_price = 0.0;
		if ( method_exists( $sample_product, 'get_price' ) ) {
			$sample_price = floatval( $sample_product->get_price() );
		} elseif ( isset( $sample_product->price ) ) {
			$sample_price = floatval( $sample_product->price );
		}

		if ( 0 >= $sample_price ) {
			$sample_price = 100.0; // Default sample price for preview
		}

		// Use discount engine for calculation
		$preview = $this->discount_engine->calculate_discount(
			$sample_price,
			$config,
			array( 'quantity' => 1 )
		);

		$preview_data = $this->format_preview_data( $preview, $config );

		return array(
			'success' => true,
			'preview' => $preview_data,
			'html'    => $this->render_preview_html( $preview_data ),
			'message' => __( 'Preview generated', 'smart-cycle-discounts' ),
		);
	}

	/**
	 * Calculate discount impact
	 *
	 * @param array $request Request data
	 * @return array Impact calculation
	 */
	private function calculate_impact( array $request ) {
		// Defensive programming: ensure arrays
		$product_ids = isset( $request['product_ids'] ) && is_array( $request['product_ids'] ) ? $request['product_ids'] : array();
		$config      = isset( $request['discount_config'] ) && is_array( $request['discount_config'] ) ? $request['discount_config'] : array();

		if ( empty( $product_ids ) && $this->state_service ) {
			$products_data = $this->state_service->get_step_data( 'products' );
			$product_ids   = isset( $products_data['product_ids'] ) && is_array( $products_data['product_ids'] ) ? $products_data['product_ids'] : array();
		}

		if ( empty( $config['discount_type'] ) ) {
			return array(
				'success' => false,
				'message' => __( 'Discount configuration required', 'smart-cycle-discounts' ),
			);
		}

		$impact = $this->discount_engine->calculate_campaign_impact( $product_ids, $config );

		return array(
			'success'    => true,
			'impact'     => array(
				'total_discount'    => $impact['total_discount'],
				'products_affected' => $impact['products_affected'],
				'average_discount'  => $impact['average_discount'],
				'formatted_total'   => wc_price( $impact['total_discount'] ),
				'formatted_average' => wc_price( $impact['average_discount'] ),
			),
			'chart_data' => $this->prepare_chart_data( $impact ),
			'message'    => sprintf(
				/* translators: %1$d: number of products affected, %2$s: formatted total discount amount */
				__( '%1$d products affected, total discount: %2$s', 'smart-cycle-discounts' ),
					$impact['products_affected'],
					wc_price( $impact['total_discount'] )
				),
		);
	}

	/**
	 * Get warnings for discount rules
	 *
	 * @param array $rules Discount rules to check
	 * @return array List of warning messages
	 */
	private function get_warnings_for_rules( $rules ) {
		$warnings = array();

		if ( isset( $rules['discount_type'] ) && 'percentage' === $rules['discount_type'] &&
			isset( $rules['discount_value'] ) && $rules['discount_value'] > 50 ) {
			$warnings[] = __( 'High discount percentage may impact margins', 'smart-cycle-discounts' );
		}

		if ( ! empty( $rules['conditions'] ) && count( $rules['conditions'] ) > 3 ) {
			$warnings[] = __( 'Complex conditions may confuse customers', 'smart-cycle-discounts' );
		}

		return $warnings;
	}

	/**
	 * Get sample product for preview
	 *
	 * Attempts to get a real published product first, falls back to mock product
	 *
	 * @return object|WC_Product Sample product object
	 */
	private function get_sample_product() {
		// Try to get a real product first
		$products = wc_get_products(
			array(
				'limit'  => 1,
				'status' => 'publish',
			)
		);

		if ( ! empty( $products ) ) {
			return $products[0];
		}

		return (object) array(
			'ID'            => 0,
			'name'          => __( 'Sample Product', 'smart-cycle-discounts' ),
			'regular_price' => 100,
			'price'         => 100,
		);
	}

	/**
	 * Format preview data for display
	 *
	 * @param WSSCD_Discount_Result $calculation Discount calculation result.
	 * @param array                 $config      Discount configuration.
	 * @return array Formatted preview data.
	 */
	private function format_preview_data( WSSCD_Discount_Result $calculation, $config ) {
		return array(
			'type'            => $config['discount_type'],
			'original_price'  => wc_price( $calculation->get_original_price() ),
			'discount_amount' => wc_price( $calculation->get_discount_amount() ),
			'final_price'     => wc_price( $calculation->get_discounted_price() ),
			'savings_percent' => round( $calculation->get_discount_percentage(), 1 ),
			'savings_text'    => $this->get_savings_text( $calculation, $config ),
			'badge_text'      => $this->get_badge_text( $calculation, $config ),
		);
	}

	/**
	 * Get savings text.
	 *
	 * @param WSSCD_Discount_Result $calculation Discount calculation result.
	 * @param array                 $config      Discount configuration.
	 * @return string Savings text.
	 */
	private function get_savings_text( WSSCD_Discount_Result $calculation, $config ) {
		switch ( $config['discount_type'] ) {
			case 'percentage':
				return /* translators: %s: savings percentage */
				sprintf( __( 'Save %s%%', 'smart-cycle-discounts' ), round( $calculation->get_discount_percentage(), 1 ) );
			case 'fixed':
				return /* translators: %s: formatted price amount */
				sprintf( __( 'Save %s', 'smart-cycle-discounts' ), wc_price( $calculation->get_discount_amount() ) );
			case 'spend_threshold':
				return /* translators: %s: formatted price amount */
				sprintf( __( 'Save %s when you spend more', 'smart-cycle-discounts' ), wc_price( $calculation->get_discount_amount() ) );
			default:
				return /* translators: %s: formatted price amount */
				sprintf( __( '%s off', 'smart-cycle-discounts' ), wc_price( $calculation->get_discount_amount() ) );
		}
	}

	/**
	 * Get badge text.
	 *
	 * @param WSSCD_Discount_Result $calculation Discount calculation result.
	 * @param array                 $config      Discount configuration.
	 * @return string Badge text.
	 */
	private function get_badge_text( WSSCD_Discount_Result $calculation, $config ) {
		switch ( $config['discount_type'] ) {
			case 'percentage':
				return /* translators: %s: discount percentage */
				sprintf( __( '%s%% OFF', 'smart-cycle-discounts' ), round( $calculation->get_discount_percentage(), 1 ) );
			case 'fixed':
				return /* translators: %s: formatted price amount */
				sprintf( __( 'SAVE %s', 'smart-cycle-discounts' ), wp_strip_all_tags( wc_price( $calculation->get_discount_amount() ) ) );
			case 'bogo':
				return __( 'BOGO DEAL', 'smart-cycle-discounts' );
			case 'spend_threshold':
				return __( 'SPEND & SAVE', 'smart-cycle-discounts' );
			default:
				return __( 'SPECIAL OFFER', 'smart-cycle-discounts' );
		}
	}

	/**
	 * Render preview HTML
	 */
	private function render_preview_html( $preview_data ) {
		ob_start();
		?>
		<div class="wsscd-discount-preview">
			<div class="wsscd-preview-badge">
				<?php echo esc_html( $preview_data['badge_text'] ); ?>
			</div>
			
			<div class="wsscd-preview-pricing">
				<div class="wsscd-original-price">
					<span class="label"><?php esc_html_e( 'Regular:', 'smart-cycle-discounts' ); ?></span>
					<span class="price strikethrough"><?php echo wp_kses_post( $preview_data['original_price'] ); ?></span>
				</div>
				
				<div class="wsscd-sale-price">
					<span class="label"><?php esc_html_e( 'Sale:', 'smart-cycle-discounts' ); ?></span>
					<span class="price highlight"><?php echo wp_kses_post( $preview_data['final_price'] ); ?></span>
				</div>
				
				<div class="wsscd-savings">
					<?php echo esc_html( $preview_data['savings_text'] ); ?>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Prepare chart data for impact visualization
	 */
	private function prepare_chart_data( $impact ) {
		return array(
			'labels' => array(
				__( 'Regular Revenue', 'smart-cycle-discounts' ),
				__( 'Discounted Revenue', 'smart-cycle-discounts' ),
				__( 'Total Discount', 'smart-cycle-discounts' ),
			),
			'data'   => array(
				$impact['regular_total'],
				$impact['discounted_total'],
				$impact['total_discount'],
			),
		);
	}
}