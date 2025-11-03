<?php
/**
 * Discounts Controller Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/api/endpoints/class-discounts-controller.php
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Discounts API Controller
 *
 * Handles all REST API operations for discount management.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/api/controllers
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Discounts_Controller {

	/**
	 * API namespace.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $namespace    API namespace.
	 */
	private string $namespace;

	/**
	 * Discount engine instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Discount_Engine    $discount_engine    Discount engine.
	 */
	private SCD_Discount_Engine $discount_engine;

	/**
	 * Campaign manager instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Campaign_Manager    $campaign_manager    Campaign manager.
	 */
	private SCD_Campaign_Manager $campaign_manager;

	/**
	 * Permissions manager instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_API_Permissions    $permissions_manager    Permissions manager.
	 */
	private SCD_API_Permissions $permissions_manager;

	/**
	 * Logger instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Logger    $logger    Logger instance.
	 */
	private SCD_Logger $logger;

	/**
	 * Initialize the discounts endpoint.
	 *
	 * @since    1.0.0
	 * @param    string               $namespace             API namespace.
	 * @param    SCD_Discount_Engine  $discount_engine       Discount engine.
	 * @param    SCD_Campaign_Manager $campaign_manager      Campaign manager.
	 * @param    SCD_API_Permissions  $permissions_manager   Permissions manager.
	 * @param    SCD_Logger           $logger                Logger instance.
	 */
	public function __construct(
		string $namespace,
		SCD_Discount_Engine $discount_engine,
		SCD_Campaign_Manager $campaign_manager,
		SCD_API_Permissions $permissions_manager,
		SCD_Logger $logger
	) {
		$this->namespace           = $namespace;
		$this->discount_engine     = $discount_engine;
		$this->campaign_manager    = $campaign_manager;
		$this->permissions_manager = $permissions_manager;
		$this->logger              = $logger;
	}

	/**
	 * Register API routes.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function register_routes(): void {
		// Active discounts collection
		register_rest_route(
			$this->namespace,
			'/discounts',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_active_discounts' ),
				'permission_callback' => array( $this->permissions_manager, 'check_permissions' ),
				'args'                => $this->get_collection_params(),
			)
		);

		// Product discount calculation
		register_rest_route(
			$this->namespace,
			'/discounts/calculate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'calculate_discount' ),
				'permission_callback' => array( $this->permissions_manager, 'check_permissions' ),
				'args'                => array(
					'product_id'  => array(
						'description'       => __( 'Product ID to calculate discount for.', 'smart-cycle-discounts' ),
						'type'              => 'integer',
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param ) && $param > 0;
						},
					),
					'quantity'    => array(
						'description' => __( 'Product quantity.', 'smart-cycle-discounts' ),
						'type'        => 'integer',
						'default'     => 1,
						'minimum'     => 1,
					),
					'campaign_id' => array(
						'description'       => __( 'Specific campaign ID to use.', 'smart-cycle-discounts' ),
						'type'              => 'integer',
						'validate_callback' => function ( $param ) {
							return is_numeric( $param ) && $param > 0;
						},
					),
				),
			)
		);

		// Bulk discount calculation
		register_rest_route(
			$this->namespace,
			'/discounts/calculate/bulk',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'calculate_bulk_discounts' ),
				'permission_callback' => array( $this->permissions_manager, 'check_permissions' ),
				'args'                => array(
					'products' => array(
						'description'       => __( 'Array of products to calculate discounts for.', 'smart-cycle-discounts' ),
						'type'              => 'array',
						'required'          => true,
						'items'             => array(
							'type'       => 'object',
							'properties' => array(
								'product_id' => array( 'type' => 'integer' ),
								'quantity'   => array(
									'type'    => 'integer',
									'default' => 1,
								),
							),
						),
						'validate_callback' => function ( $param ) {
							return is_array( $param ) && ! empty( $param );
						},
					),
				),
			)
		);

		// Discount preview
		register_rest_route(
			$this->namespace,
			'/discounts/preview',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'preview_discount' ),
				'permission_callback' => array( $this->permissions_manager, 'check_permissions' ),
				'args'                => array(
					'discount_type'  => array(
						'description' => __( 'Discount type.', 'smart-cycle-discounts' ),
						'type'        => 'string',
						'enum'        => array( 'percentage', 'fixed' ),
						'required'    => true,
					),
					'discount_value' => array(
						'description' => __( 'Discount value.', 'smart-cycle-discounts' ),
						'type'        => 'number',
						'required'    => true,
						'minimum'     => 0,
					),
					'original_price' => array(
						'description' => __( 'Original product price.', 'smart-cycle-discounts' ),
						'type'        => 'number',
						'required'    => true,
						'minimum'     => 0,
					),
					'quantity'       => array(
						'description' => __( 'Product quantity.', 'smart-cycle-discounts' ),
						'type'        => 'integer',
						'default'     => 1,
						'minimum'     => 1,
					),
				),
			)
		);

		// Product discounts by campaign
		register_rest_route(
			$this->namespace,
			'/discounts/campaign/(?P<campaign_id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_campaign_discounts' ),
				'permission_callback' => array( $this->permissions_manager, 'check_permissions' ),
				'args'                => array(
					'campaign_id' => array(
						'description'       => __( 'Campaign ID.', 'smart-cycle-discounts' ),
						'type'              => 'integer',
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param ) && $param > 0;
						},
					),
				),
			)
		);

		// Best discount for product
		register_rest_route(
			$this->namespace,
			'/discounts/best/(?P<product_id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_best_discount' ),
				'permission_callback' => '__return_true', // Public endpoint
				'args'                => array(
					'product_id' => array(
						'description'       => __( 'Product ID.', 'smart-cycle-discounts' ),
						'type'              => 'integer',
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param ) && $param > 0;
						},
					),
					'quantity'   => array(
						'description' => __( 'Product quantity.', 'smart-cycle-discounts' ),
						'type'        => 'integer',
						'default'     => 1,
						'minimum'     => 1,
					),
				),
			)
		);

		// Discount validation
		register_rest_route(
			$this->namespace,
			'/discounts/validate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'validate_discount' ),
				'permission_callback' => array( $this->permissions_manager, 'check_permissions' ),
				'args'                => array(
					'discount_type'  => array(
						'description' => __( 'Discount type.', 'smart-cycle-discounts' ),
						'type'        => 'string',
						'enum'        => array( 'percentage', 'fixed', 'tiered', 'bogo', 'bundle' ),
						'required'    => true,
					),
					'discount_value' => array(
						'description' => __( 'Discount value.', 'smart-cycle-discounts' ),
						'type'        => 'number',
						'required'    => true,
					),
					'product_price'  => array(
						'description' => __( 'Product price for validation.', 'smart-cycle-discounts' ),
						'type'        => 'number',
						'required'    => true,
						'minimum'     => 0,
					),
					'tiers'          => array(
						'description' => __( 'Tier configuration for tiered discounts.', 'smart-cycle-discounts' ),
						'type'        => 'array',
						'items'       => array(
							'type'       => 'object',
							'properties' => array(
								'threshold'      => array( 'type' => 'number' ),
								'discount_type'  => array(
									'type' => 'string',
									'enum' => array( 'percentage', 'fixed' ),
								),
								'discount_value' => array( 'type' => 'number' ),
							),
						),
					),
					'tier_type'      => array(
						'description' => __( 'Tier comparison type.', 'smart-cycle-discounts' ),
						'type'        => 'string',
						'enum'        => array( 'quantity', 'amount' ),
						'default'     => 'quantity',
					),
					'bogo_config'    => array(
						'description' => __( 'BOGO configuration.', 'smart-cycle-discounts' ),
						'type'        => 'object',
						'properties'  => array(
							'buy_quantity'        => array(
								'type'    => 'integer',
								'minimum' => 1,
							),
							'get_quantity'        => array(
								'type'    => 'integer',
								'minimum' => 1,
							),
							'discount_percentage' => array(
								'type'    => 'number',
								'minimum' => 0,
								'maximum' => 100,
							),
						),
					),
					'bundle_config'  => array(
						'description' => __( 'Bundle configuration.', 'smart-cycle-discounts' ),
						'type'        => 'object',
						'properties'  => array(
							'bundle_size'  => array(
								'type'    => 'integer',
								'minimum' => 2,
							),
							'bundle_price' => array(
								'type'    => 'number',
								'minimum' => 0.01,
							),
						),
					),
				),
			)
		);

		$this->logger->debug( 'Discounts API routes registered' );
	}

	/**
	 * Get active discounts.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request $request    Request object.
	 * @return   WP_REST_Response               Response object.
	 */
	public function get_active_discounts( WP_REST_Request $request ): WP_REST_Response {
		try {
			$params           = $this->prepare_collection_params( $request );
			$active_discounts = $this->discount_engine->get_active_discounts( $params );

			$data = array();
			foreach ( $active_discounts as $discount ) {
				$data[] = $this->prepare_discount_for_response( $discount, $request );
			}

			$response = new WP_REST_Response( $data, 200 );

			// Add total count header
			$response->header( 'X-WP-Total', (string) count( $data ) );

			$this->logger->debug(
				'Active discounts retrieved via API',
				array(
					'count'  => count( $data ),
					'params' => $params,
				)
			);

			return $response;

		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to get active discounts via API',
				array(
					'error'  => $e->getMessage(),
					'params' => $request->get_params(),
				)
			);

			return new WP_REST_Response(
				array(
					'code'    => 'discounts_fetch_error',
					'message' => __( 'Failed to retrieve active discounts.', 'smart-cycle-discounts' ),
					'data'    => array( 'status' => 500 ),
				),
				500
			);
		}
	}

	/**
	 * Calculate discount for product.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request $request    Request object.
	 * @return   WP_REST_Response               Response object.
	 */
	public function calculate_discount( WP_REST_Request $request ): WP_REST_Response {
		try {
			$product_id  = (int) $request['product_id'];
			$quantity    = (int) ( $request->get_param( 'quantity' ) ?: 1 );
			$campaign_id = $request->get_param( 'campaign_id' ) ? (int) $request->get_param( 'campaign_id' ) : null;

			// Get product
			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				return new WP_REST_Response(
					array(
						'code'    => 'product_not_found',
						'message' => __( 'Product not found.', 'smart-cycle-discounts' ),
						'data'    => array( 'status' => 404 ),
					),
					404
				);
			}

			// Calculate discount
			if ( $campaign_id ) {
				$discount_result = $this->discount_engine->calculate_campaign_discount( $product, $campaign_id, $quantity );
			} else {
				$discount_result = $this->discount_engine->calculate_best_discount( $product, $quantity );
			}

			$data = array(
				'product_id'      => $product_id,
				'quantity'        => $quantity,
				'campaign_id'     => $campaign_id,
				'original_price'  => (float) $product->get_price(),
				'discount_result' => $this->prepare_discount_result_for_response( $discount_result ),
				'calculated_at'   => current_time( 'mysql' ),
			);

			$this->logger->debug(
				'Discount calculated via API',
				array(
					'product_id'      => $product_id,
					'campaign_id'     => $campaign_id,
					'discount_amount' => $discount_result->get_discount_amount(),
				)
			);

			return new WP_REST_Response( $data, 200 );

		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to calculate discount via API',
				array(
					'error'      => $e->getMessage(),
					'product_id' => $product_id ?? null,
					'params'     => $request->get_params(),
				)
			);

			return new WP_REST_Response(
				array(
					'code'    => 'discount_calculation_error',
					'message' => __( 'Failed to calculate discount.', 'smart-cycle-discounts' ),
					'data'    => array( 'status' => 500 ),
				),
				500
			);
		}
	}

	/**
	 * Calculate bulk discounts.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request $request    Request object.
	 * @return   WP_REST_Response               Response object.
	 */
	public function calculate_bulk_discounts( WP_REST_Request $request ): WP_REST_Response {
		try {
			$products = $request['products'];
			$results  = array();
			$errors   = array();

			foreach ( $products as $product_data ) {
				try {
					$product_id = (int) $product_data['product_id'];
					$quantity   = (int) ( $product_data['quantity'] ?? 1 );

					$product = wc_get_product( $product_id );
					if ( ! $product ) {
						$errors[] = array(
							'product_id' => $product_id,
							'message'    => __( 'Product not found.', 'smart-cycle-discounts' ),
						);
						continue;
					}

					$discount_result = $this->discount_engine->calculate_best_discount( $product, $quantity );

					$results[] = array(
						'product_id'      => $product_id,
						'quantity'        => $quantity,
						'original_price'  => (float) $product->get_price(),
						'discount_result' => $this->prepare_discount_result_for_response( $discount_result ),
					);

				} catch ( Exception $e ) {
					$errors[] = array(
						'product_id' => $product_data['product_id'] ?? 'unknown',
						'message'    => $e->getMessage(),
					);
				}
			}

			$data = array(
				'results'         => $results,
				'errors'          => $errors,
				'total_processed' => count( $products ),
				'success_count'   => count( $results ),
				'error_count'     => count( $errors ),
				'calculated_at'   => current_time( 'mysql' ),
			);

			$this->logger->debug(
				'Bulk discounts calculated via API',
				array(
					'total'   => count( $products ),
					'success' => count( $results ),
					'errors'  => count( $errors ),
				)
			);

			return new WP_REST_Response( $data, 200 );

		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to calculate bulk discounts via API',
				array(
					'error'  => $e->getMessage(),
					'params' => $request->get_params(),
				)
			);

			return new WP_REST_Response(
				array(
					'code'    => 'bulk_discount_calculation_error',
					'message' => __( 'Failed to calculate bulk discounts.', 'smart-cycle-discounts' ),
					'data'    => array( 'status' => 500 ),
				),
				500
			);
		}
	}

	/**
	 * Preview discount calculation.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request $request    Request object.
	 * @return   WP_REST_Response               Response object.
	 */
	public function preview_discount( WP_REST_Request $request ): WP_REST_Response {
		try {
			$discount_type  = $request['discount_type'];
			$discount_value = (float) $request['discount_value'];
			$original_price = (float) $request['original_price'];
			$quantity       = (int) ( $request->get_param( 'quantity' ) ?: 1 );

			// Create preview discount configuration
			$discount_config = array(
				'type'  => $discount_type,
				'value' => $discount_value,
			);

			// Calculate preview
			$preview_result = $this->discount_engine->preview_discount( $discount_config, $original_price, $quantity );

			$data = array(
				'discount_type'  => $discount_type,
				'discount_value' => $discount_value,
				'original_price' => $original_price,
				'quantity'       => $quantity,
				'preview'        => array(
					'discount_amount'  => $preview_result['discount_amount'],
					'discounted_price' => $preview_result['discounted_price'],
					'total_savings'    => $preview_result['total_savings'],
					'percentage_saved' => $preview_result['percentage_saved'],
				),
				'validation'     => array(
					'is_valid' => $preview_result['is_valid'],
					'warnings' => $preview_result['warnings'] ?? array(),
				),
			);

			$this->logger->debug(
				'Discount preview calculated via API',
				array(
					'type'    => $discount_type,
					'value'   => $discount_value,
					'savings' => $preview_result['total_savings'],
				)
			);

			return new WP_REST_Response( $data, 200 );

		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to preview discount via API',
				array(
					'error'  => $e->getMessage(),
					'params' => $request->get_params(),
				)
			);

			return new WP_REST_Response(
				array(
					'code'    => 'discount_preview_error',
					'message' => __( 'Failed to preview discount.', 'smart-cycle-discounts' ),
					'data'    => array( 'status' => 500 ),
				),
				500
			);
		}
	}

	/**
	 * Get campaign discounts.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request $request    Request object.
	 * @return   WP_REST_Response               Response object.
	 */
	public function get_campaign_discounts( WP_REST_Request $request ): WP_REST_Response {
		try {
			$campaign_id = (int) $request['campaign_id'];

			// Verify campaign exists
			$campaign = $this->campaign_manager->find( $campaign_id );
			if ( ! $campaign ) {
				return new WP_REST_Response(
					array(
						'code'    => 'campaign_not_found',
						'message' => __( 'Campaign not found.', 'smart-cycle-discounts' ),
						'data'    => array( 'status' => 404 ),
					),
					404
				);
			}

			// Get campaign discounts
			$discounts = $this->discount_engine->get_campaign_discounts( $campaign_id );

			$data = array(
				'campaign_id'      => $campaign_id,
				'campaign_name'    => $campaign->get_name(),
				'campaign_status'  => $campaign->get_status(),
				'discount_type'    => $campaign->get_discount_type(),
				'discount_value'   => $campaign->get_discount_value(),
				'active_discounts' => array_map(
					function ( $discount ) use ( $request ) {
						return $this->prepare_discount_for_response( $discount, $request );
					},
					$discounts
				),
				'total_discounts'  => count( $discounts ),
			);

			$this->logger->debug(
				'Campaign discounts retrieved via API',
				array(
					'campaign_id'     => $campaign_id,
					'discounts_count' => count( $discounts ),
				)
			);

			return new WP_REST_Response( $data, 200 );

		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to get campaign discounts via API',
				array(
					'error'       => $e->getMessage(),
					'campaign_id' => $campaign_id ?? null,
				)
			);

			return new WP_REST_Response(
				array(
					'code'    => 'campaign_discounts_error',
					'message' => __( 'Failed to retrieve campaign discounts.', 'smart-cycle-discounts' ),
					'data'    => array( 'status' => 500 ),
				),
				500
			);
		}
	}

	/**
	 * Get best discount for product.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request $request    Request object.
	 * @return   WP_REST_Response               Response object.
	 */
	public function get_best_discount( WP_REST_Request $request ): WP_REST_Response {
		try {
			$product_id = (int) $request['product_id'];
			$quantity   = (int) ( $request->get_param( 'quantity' ) ?: 1 );

			// Get product
			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				return new WP_REST_Response(
					array(
						'code'    => 'product_not_found',
						'message' => __( 'Product not found.', 'smart-cycle-discounts' ),
						'data'    => array( 'status' => 404 ),
					),
					404
				);
			}

			// Find best discount
			$best_discount = $this->discount_engine->calculate_best_discount( $product, $quantity );

			$data = array(
				'product_id'     => $product_id,
				'product_name'   => $product->get_name(),
				'quantity'       => $quantity,
				'original_price' => (float) $product->get_price(),
				'best_discount'  => $this->prepare_discount_result_for_response( $best_discount ),
				'has_discount'   => $best_discount->has_discount(),
				'calculated_at'  => current_time( 'mysql' ),
			);

			// Add cache headers for public endpoint
			$response = new WP_REST_Response( $data, 200 );
			$response->header( 'Cache-Control', 'public, max-age=300' ); // 5 minutes
			$response->header( 'ETag', md5( serialize( $data ) ) );

			$this->logger->debug(
				'Best discount retrieved via API',
				array(
					'product_id'   => $product_id,
					'has_discount' => $best_discount->has_discount(),
				)
			);

			return $response;

		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to get best discount via API',
				array(
					'error'      => $e->getMessage(),
					'product_id' => $product_id ?? null,
				)
			);

			return new WP_REST_Response(
				array(
					'code'    => 'best_discount_error',
					'message' => __( 'Failed to retrieve best discount.', 'smart-cycle-discounts' ),
					'data'    => array( 'status' => 500 ),
				),
				500
			);
		}
	}

	/**
	 * Validate discount configuration.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request $request    Request object.
	 * @return   WP_REST_Response               Response object.
	 */
	public function validate_discount( WP_REST_Request $request ): WP_REST_Response {
		try {
			$discount_type  = $request['discount_type'];
			$discount_value = (float) $request['discount_value'];
			$product_price  = (float) $request['product_price'];

			// Build complete discount configuration for validation
			$discount_config = array(
				'type'  => $discount_type,
				'value' => $discount_value,
			);

			// Add type-specific configuration from request
			if ( $discount_type === 'tiered' && $request->has_param( 'tiers' ) ) {
				$discount_config['tiers']     = $request['tiers'];
				$discount_config['tier_type'] = $request['tier_type'] ?? 'quantity';
			} elseif ( $discount_type === 'bogo' && $request->has_param( 'bogo_config' ) ) {
				$discount_config = array_merge( $discount_config, $request['bogo_config'] );
			} elseif ( $discount_type === 'bundle' && $request->has_param( 'bundle_config' ) ) {
				$discount_config = array_merge( $discount_config, $request['bundle_config'] );
			}

			// Validate discount configuration
			$validation_errors = $this->discount_engine->validate_discount_config( $discount_config );
			$is_valid          = empty( $validation_errors );

			// Additional validation for price limits
			$warnings = array();
			if ( $discount_type === 'fixed' && $discount_value > $product_price ) {
				$warnings[] = __( 'Fixed discount exceeds product price', 'smart-cycle-discounts' );
			}
			if ( $discount_type === 'percentage' && $discount_value > 50 ) {
				$warnings[] = __( 'Large discount percentage - please verify', 'smart-cycle-discounts' );
			}

			$data = array(
				'discount_type'  => $discount_type,
				'discount_value' => $discount_value,
				'product_price'  => $product_price,
				'validation'     => array(
					'is_valid'    => $is_valid,
					'errors'      => $validation_errors,
					'warnings'    => $warnings,
					'suggestions' => array(),
				),
				'validated_at'   => current_time( 'mysql' ),
			);

			$this->logger->debug(
				'Discount validation performed via API',
				array(
					'type'     => $discount_type,
					'value'    => $discount_value,
					'is_valid' => $validation_result['is_valid'],
				)
			);

			return new WP_REST_Response( $data, 200 );

		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to validate discount via API',
				array(
					'error'  => $e->getMessage(),
					'params' => $request->get_params(),
				)
			);

			return new WP_REST_Response(
				array(
					'code'    => 'discount_validation_error',
					'message' => __( 'Failed to validate discount.', 'smart-cycle-discounts' ),
					'data'    => array( 'status' => 500 ),
				),
				500
			);
		}
	}

	/**
	 * Prepare discount for API response.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array           $discount    Discount data.
	 * @param    WP_REST_Request $request     Request object.
	 * @return   array                           Prepared discount data.
	 */
	private function prepare_discount_for_response( array $discount, WP_REST_Request $request ): array {
		return array(
			'id'               => $discount['id'] ?? null,
			'campaign_id'      => $discount['campaign_id'] ?? null,
			'campaign_name'    => $discount['campaign_name'] ?? '',
			'product_id'       => $discount['product_id'] ?? null,
			'product_name'     => $discount['product_name'] ?? '',
			'discount_type'    => $discount['discount_type'] ?? '',
			'discount_value'   => $discount['discount_value'] ?? 0,
			'original_price'   => $discount['original_price'] ?? 0,
			'discounted_price' => $discount['discounted_price'] ?? 0,
			'discount_amount'  => $discount['discount_amount'] ?? 0,
			'start_date'       => $discount['start_date'] ?? null,
			'end_date'         => $discount['end_date'] ?? null,
			'is_active'        => $discount['is_active'] ?? false,
		);
	}

	/**
	 * Prepare discount result for API response.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    SCD_Discount_Result $result    Discount result.
	 * @return   array                             Prepared result data.
	 */
	private function prepare_discount_result_for_response( SCD_Discount_Result $result ): array {
		return array(
			'has_discount'       => $result->has_discount(),
			'discount_amount'    => $result->get_discount_amount(),
			'discounted_price'   => $result->get_discounted_price(),
			'original_price'     => $result->get_original_price(),
			'savings_percentage' => $result->get_savings_percentage(),
			'campaign_id'        => $result->get_campaign_id(),
			'campaign_name'      => $result->get_campaign_name(),
			'discount_type'      => $result->get_discount_type(),
			'discount_value'     => $result->get_discount_value(),
			'metadata'           => $result->get_metadata(),
		);
	}

	/**
	 * Prepare collection parameters.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    WP_REST_Request $request    Request object.
	 * @return   array                          Prepared parameters.
	 */
	private function prepare_collection_params( WP_REST_Request $request ): array {
		$params = array();

		// Pagination
		$params['page']     = max( 1, (int) $request->get_param( 'page' ) );
		$params['per_page'] = min( 100, max( 1, (int) $request->get_param( 'per_page' ) ) );

		// Filtering
		if ( $request->has_param( 'campaign_id' ) ) {
			$params['campaign_id'] = (int) $request->get_param( 'campaign_id' );
		}

		if ( $request->has_param( 'product_id' ) ) {
			$params['product_id'] = (int) $request->get_param( 'product_id' );
		}

		if ( $request->has_param( 'discount_type' ) ) {
			$params['discount_type'] = $request->get_param( 'discount_type' );
		}

		return $params;
	}

	/**
	 * Get collection parameters schema.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   array    Collection parameters.
	 */
	private function get_collection_params(): array {
		return array(
			'page'          => array(
				'description' => __( 'Current page of the collection.', 'smart-cycle-discounts' ),
				'type'        => 'integer',
				'default'     => 1,
				'minimum'     => 1,
			),
			'per_page'      => array(
				'description' => __( 'Maximum number of items to return.', 'smart-cycle-discounts' ),
				'type'        => 'integer',
				'default'     => 10,
				'minimum'     => 1,
				'maximum'     => 100,
			),
			'campaign_id'   => array(
				'description' => __( 'Filter by campaign ID.', 'smart-cycle-discounts' ),
				'type'        => 'integer',
			),
			'product_id'    => array(
				'description' => __( 'Filter by product ID.', 'smart-cycle-discounts' ),
				'type'        => 'integer',
			),
			'discount_type' => array(
				'description' => __( 'Filter by discount type.', 'smart-cycle-discounts' ),
				'type'        => 'string',
				'enum'        => array( 'percentage', 'fixed' ),
			),
		);
	}

	/**
	 * Get endpoint information.
	 *
	 * @since    1.0.0
	 * @return   array    Endpoint information.
	 */
	public function get_endpoint_info(): array {
		return array(
			'name'         => 'Discounts',
			'description'  => __( 'Manage and calculate discounts', 'smart-cycle-discounts' ),
			'routes'       => array(
				'GET /discounts'                   => __( 'List active discounts', 'smart-cycle-discounts' ),
				'POST /discounts/calculate'        => __( 'Calculate product discount', 'smart-cycle-discounts' ),
				'POST /discounts/calculate/bulk'   => __( 'Calculate bulk discounts', 'smart-cycle-discounts' ),
				'POST /discounts/preview'          => __( 'Preview discount calculation', 'smart-cycle-discounts' ),
				'GET /discounts/campaign/{id}'     => __( 'Get campaign discounts', 'smart-cycle-discounts' ),
				'GET /discounts/best/{product_id}' => __( 'Get best discount for product', 'smart-cycle-discounts' ),
				'POST /discounts/validate'         => __( 'Validate discount configuration', 'smart-cycle-discounts' ),
			),
			'capabilities' => array(
				'view_discounts',
				'manage_discounts',
			),
		);
	}
}
