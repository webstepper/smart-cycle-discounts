<?php
/**
 * Discount API Handler
 *
 * Handles all discount-related AJAX operations with proper separation of concerns
 *
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers
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
class SCD_Discount_API_Handler implements SCD_Ajax_Handler {

	// Removed: validation_service - using consolidated SCD_Validation class instead

	/**
	 * Discount engine
	 *
	 * @var SCD_Discount_Engine
	 */
	private $discount_engine;

	/**
	 * State service
	 *
	 * @var SCD_Wizard_State_Service
	 */
	private $state_service;

	/**
	 * Constructor
	 */
	public function __construct() {
		$container = SCD_Core_Container::get_instance();
		
		// Get services
		// Removed: validation_service - using consolidated SCD_Validation class instead
		$this->state_service = $container->get( 'wizard.state_service' );
		
		// Initialize discount engine if needed
		if ( ! class_exists( 'SCD_Discount_Engine' ) ) {
			require_once SCD_INCLUDES_DIR . 'core/discounts/class-discount-engine.php';
		}
		$this->discount_engine = new SCD_Discount_Engine();
	}

	/**
	 * Handle the request based on sub-action
	 *
	 * @param array $request Request data
	 * @return array Response data
	 */
	public function handle( array $request ) {
		// HIGH: Add nonce verification for CSRF protection
		if ( ! isset( $request['nonce'] ) || ! wp_verify_nonce( $request['nonce'], 'scd_discount_api' ) ) {
			return array(
				'success' => false,
				'message' => __( 'Security check failed', 'smart-cycle-discounts' )
			);
		}

		// Check permissions
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return array(
				'success' => false,
				'message' => __( 'Insufficient permissions', 'smart-cycle-discounts' )
			);
		}

		// Extract sub-action and validate with whitelist
		$action = isset( $request['action'] ) ? sanitize_key( $request['action'] ) : '';
		$action = str_replace( 'scd_', '', $action );

		// Security: Whitelist of allowed actions
		$allowed_actions = array( 'validate_discount_rules', 'get_discount_preview', 'calculate_discount_impact' );

		if ( ! in_array( $action, $allowed_actions, true ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid discount action', 'smart-cycle-discounts' )
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
		
		// Use consolidated SCD_Validation class for validation
		$validation_context = 'wizard_discounts';
		$validation_result = SCD_Validation::validate( array( 'rules' => $rules ), $validation_context );
		
		if ( is_wp_error( $validation_result ) ) {
			return array(
				'success' => false,
				'valid' => false,
				'errors' => $validation_result->get_error_messages(),
				'message' => __( 'Validation failed', 'smart-cycle-discounts' )
			);
		}

		return array(
			'success' => true,
			'valid' => true,
			'errors' => array(),
			'warnings' => $this->get_warnings_for_rules( $rules ),
			'message' => __( 'Validation passed', 'smart-cycle-discounts' )
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
				'message' => __( 'Discount type is required', 'smart-cycle-discounts' )
			);
		}
		
		// Check discount engine availability
		if ( ! $this->discount_engine ) {
			return array(
				'success' => false,
				'message' => __( 'Discount engine not available', 'smart-cycle-discounts' )
			);
		}

		// Get sample product for preview
		$sample_product = $this->get_sample_product();
		
		if ( ! $sample_product ) {
			return array(
				'success' => false,
				'message' => __( 'No sample product available for preview', 'smart-cycle-discounts' )
			);
		}
		
		// Use discount engine for calculation
		$preview = $this->discount_engine->calculate_discount( 
			$sample_product, 
			$config,
			1 // quantity
		);

		// Format preview data
		$preview_data = $this->format_preview_data( $preview, $config );

		return array(
			'success' => true,
			'preview' => $preview_data,
			'html' => $this->render_preview_html( $preview_data ),
			'message' => __( 'Preview generated', 'smart-cycle-discounts' )
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
		$config = isset( $request['discount_config'] ) && is_array( $request['discount_config'] ) ? $request['discount_config'] : array();
		
		// Get products from state if not provided
		if ( empty( $product_ids ) && $this->state_service ) {
			$products_data = $this->state_service->get_step_data( 'products' );
			$product_ids = isset( $products_data['product_ids'] ) && is_array( $products_data['product_ids'] ) ? $products_data['product_ids'] : array();
		}

		if ( empty( $config['discount_type'] ) ) {
			return array(
				'success' => false,
				'message' => __( 'Discount configuration required', 'smart-cycle-discounts' )
			);
		}

		// Calculate impact across all products
		$impact = $this->discount_engine->calculate_campaign_impact( $product_ids, $config );

		return array(
			'success' => true,
			'impact' => array(
				'total_discount' => $impact['total_discount'],
				'products_affected' => $impact['products_affected'],
				'average_discount' => $impact['average_discount'],
				'formatted_total' => wc_price( $impact['total_discount'] ),
				'formatted_average' => wc_price( $impact['average_discount'] )
			),
			'chart_data' => $this->prepare_chart_data( $impact ),
			'message' => sprintf( 
				__( '%d products affected, total discount: %s', 'smart-cycle-discounts' ),
				$impact['products_affected'],
				wc_price( $impact['total_discount'] )
			)
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
		
		// Check for high discounts with safe array access
		if ( isset( $rules['discount_type'] ) && 'percentage' === $rules['discount_type'] && 
		     isset( $rules['discount_value'] ) && $rules['discount_value'] > 50 ) {
			$warnings[] = __( 'High discount percentage may impact margins', 'smart-cycle-discounts' );
		}
		
		// Check for complex conditions
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
		$products = wc_get_products( array( 'limit' => 1, 'status' => 'publish' ) );
		
		if ( ! empty( $products ) ) {
			return $products[0];
		}

		// Return mock product
		return (object) array(
			'ID' => 0,
			'name' => __( 'Sample Product', 'smart-cycle-discounts' ),
			'regular_price' => 100,
			'price' => 100
		);
	}

	/**
	 * Format preview data for display
	 *
	 * @param array $calculation Discount calculation result
	 * @param array $config Discount configuration
	 * @return array Formatted preview data
	 */
	private function format_preview_data( $calculation, $config ) {
		return array(
			'type' => $config['discount_type'],
			'original_price' => wc_price( $calculation['original_price'] ),
			'discount_amount' => wc_price( $calculation['discount_amount'] ),
			'final_price' => wc_price( $calculation['final_price'] ),
			'savings_percent' => $calculation['savings_percent'],
			'savings_text' => $this->get_savings_text( $calculation, $config ),
			'badge_text' => $this->get_badge_text( $calculation, $config )
		);
	}

	/**
	 * Get savings text
	 */
	private function get_savings_text( $calculation, $config ) {
		switch ( $config['discount_type'] ) {
			case 'percentage':
				return sprintf( __( 'Save %s%%', 'smart-cycle-discounts' ), $calculation['savings_percent'] );
			case 'fixed':
				return sprintf( __( 'Save %s', 'smart-cycle-discounts' ), wc_price( $calculation['discount_amount'] ) );
			default:
				return sprintf( __( '%s off', 'smart-cycle-discounts' ), wc_price( $calculation['discount_amount'] ) );
		}
	}

	/**
	 * Get badge text
	 */
	private function get_badge_text( $calculation, $config ) {
		switch ( $config['discount_type'] ) {
			case 'percentage':
				return sprintf( __( '%s%% OFF', 'smart-cycle-discounts' ), $calculation['savings_percent'] );
			case 'fixed':
				return sprintf( __( 'SAVE %s', 'smart-cycle-discounts' ), strip_tags( wc_price( $calculation['discount_amount'] ) ) );
			case 'bogo':
				return __( 'BOGO DEAL', 'smart-cycle-discounts' );
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
		<div class="scd-discount-preview">
			<div class="scd-preview-badge">
				<?php echo esc_html( $preview_data['badge_text'] ); ?>
			</div>
			
			<div class="scd-preview-pricing">
				<div class="scd-original-price">
					<span class="label"><?php esc_html_e( 'Regular:', 'smart-cycle-discounts' ); ?></span>
					<span class="price strikethrough"><?php echo wp_kses_post( $preview_data['original_price'] ); ?></span>
				</div>
				
				<div class="scd-sale-price">
					<span class="label"><?php esc_html_e( 'Sale:', 'smart-cycle-discounts' ); ?></span>
					<span class="price highlight"><?php echo wp_kses_post( $preview_data['final_price'] ); ?></span>
				</div>
				
				<div class="scd-savings">
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
				__( 'Total Discount', 'smart-cycle-discounts' )
			),
			'data' => array(
				$impact['regular_total'],
				$impact['discounted_total'],
				$impact['total_discount']
			)
		);
	}
}