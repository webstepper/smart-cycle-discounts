<?php
/**
 * WooCommerce Blocks Integration
 *
 * Integrates Smart Cycle Discounts with WooCommerce block-based cart and checkout.
 * Exposes discount data via Store API and registers frontend assets for display.
 *
 * @package    WSSCD_Plugin
 * @subpackage WSSCD_Plugin/integrations/woocommerce
 * @since      1.5.70
 */

use Automattic\WooCommerce\StoreApi\Schemas\V1\CartItemSchema;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * WooCommerce Blocks Integration Class
 *
 * Implements IntegrationInterface to register assets and extends Store API
 * to expose discount metadata for block-based cart/checkout rendering.
 *
 * @since 1.5.70
 */
class WSSCD_WC_Blocks_Integration implements Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface {

	/**
	 * Logger instance
	 *
	 * @var object|null
	 */
	private $logger;

	/**
	 * Constructor
	 *
	 * @since 1.5.70
	 * @param object|null $logger Logger instance.
	 */
	public function __construct( $logger = null ) {
		$this->logger = $logger;
	}

	/**
	 * Register integration hooks
	 *
	 * @since 1.5.70
	 * @return void
	 */
	public function register_hooks() {
		// Register with WooCommerce Blocks.
		add_action( 'woocommerce_blocks_cart_block_registration', array( $this, 'register_cart_integration' ) );
		add_action( 'woocommerce_blocks_checkout_block_registration', array( $this, 'register_checkout_integration' ) );

		// Register Store API endpoint data.
		add_action( 'woocommerce_blocks_loaded', array( $this, 'register_store_api_extension' ) );
	}

	/**
	 * Get integration name
	 *
	 * @since 1.5.70
	 * @return string
	 */
	public function get_name() {
		return 'smart-cycle-discounts';
	}

	/**
	 * Initialize integration
	 *
	 * Called when WooCommerce Blocks are loaded.
	 *
	 * @since 1.5.70
	 * @return void
	 */
	public function initialize() {
		// Nothing to initialize - assets registered via get_script_handles().
		$this->log( 'debug', 'WooCommerce Blocks integration initialized' );
	}

	/**
	 * Get script handles to enqueue
	 *
	 * @since 1.5.70
	 * @return array
	 */
	public function get_script_handles() {
		$script_path = '/resources/assets/js/frontend/wsscd-blocks-checkout.js';
		$script_url  = plugins_url( $script_path, WSSCD_PLUGIN_FILE );
		$script_file = WSSCD_PLUGIN_DIR . $script_path;

		$script_asset = array(
			'dependencies' => array(),
			'version'      => WSSCD_VERSION,
		);

		// Try to load asset file if it exists (for built assets).
		$asset_file = WSSCD_PLUGIN_DIR . '/resources/assets/js/frontend/wsscd-blocks-checkout.asset.php';
		if ( file_exists( $asset_file ) ) {
			$script_asset = require $asset_file;
		}

		wp_register_script(
			'wsscd-blocks-checkout',
			$script_url,
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);

		// Localize currency settings for JS formatting.
		wp_localize_script(
			'wsscd-blocks-checkout',
			'wsscdBlocksData',
			array(
				'currency' => array(
					'code'   => get_woocommerce_currency(),
					'symbol' => get_woocommerce_currency_symbol(),
					'decimals' => wc_get_price_decimals(),
					'decimal_separator' => wc_get_price_decimal_separator(),
					'thousand_separator' => wc_get_price_thousand_separator(),
					'price_format' => get_woocommerce_price_format(),
				),
				'locale' => array(
					'userLocale' => get_user_locale(),
				),
			)
		);

		// Register and enqueue CSS.
		$style_path = '/resources/assets/css/frontend/wsscd-blocks-checkout.css';
		$style_url  = plugins_url( $style_path, WSSCD_PLUGIN_FILE );

		wp_register_style(
			'wsscd-blocks-checkout',
			$style_url,
			array(),
			WSSCD_VERSION
		);

		wp_enqueue_style( 'wsscd-blocks-checkout' );

		return array( 'wsscd-blocks-checkout' );
	}

	/**
	 * Get editor script handles
	 *
	 * @since 1.5.70
	 * @return array
	 */
	public function get_editor_script_handles() {
		// No editor-specific scripts needed.
		return array();
	}

	/**
	 * Get script data for inline script
	 *
	 * @since 1.5.70
	 * @return array
	 */
	public function get_script_data() {
		return array(
			'enabled' => true,
		);
	}

	/**
	 * Register cart block integration
	 *
	 * @since 1.5.70
	 * @param object $integration_registry Integration registry.
	 * @return void
	 */
	public function register_cart_integration( $integration_registry ) {
		$integration_registry->register( $this );
	}

	/**
	 * Register checkout block integration
	 *
	 * @since 1.5.70
	 * @param object $integration_registry Integration registry.
	 * @return void
	 */
	public function register_checkout_integration( $integration_registry ) {
		$integration_registry->register( $this );
	}

	/**
	 * Register Store API extension
	 *
	 * Extends cart item schema to include discount metadata.
	 *
	 * @since 1.5.70
	 * @return void
	 */
	public function register_store_api_extension() {
		// Check if Store API extension function exists.
		if ( ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
			$this->log( 'warning', 'Store API extension function not available' );
			return;
		}

		woocommerce_store_api_register_endpoint_data(
			array(
				'endpoint'        => CartItemSchema::IDENTIFIER,
				'namespace'       => 'wsscd',
				'data_callback'   => array( $this, 'extend_cart_item_data' ),
				'schema_callback' => array( $this, 'extend_cart_item_schema' ),
			)
		);

		$this->log( 'debug', 'Store API extension registered' );
	}

	/**
	 * Extend cart item data with discount information
	 *
	 * Reads discount metadata already stored by WSSCD_WC_Price_Integration
	 * and exposes it to the Store API for block-based cart/checkout.
	 *
	 * @since 1.5.70
	 * @param array $cart_item Cart item data.
	 * @return array Discount data for Store API.
	 */
	public function extend_cart_item_data( $cart_item ) {
		// Default response - no discount.
		$discount_data = array(
			'has_discount' => false,
		);

		// Check if discount metadata exists (set by WSSCD_WC_Price_Integration).
		if ( ! isset( $cart_item['wsscd_discount'] ) || ! is_array( $cart_item['wsscd_discount'] ) ) {
			return $discount_data;
		}

		$discount = $cart_item['wsscd_discount'];

		// Validate required fields.
		if ( ! isset( $discount['original_price'], $discount['discounted_price'] ) ) {
			$this->log(
				'warning',
				'Incomplete discount data in cart item',
				array(
					'cart_item_key' => isset( $cart_item['key'] ) ? $cart_item['key'] : 'unknown',
					'discount_data' => $discount,
				)
			);
			return $discount_data;
		}

		// Expose raw numbers - JS will handle formatting.
		$discount_data = array(
			'has_discount'     => true,
			'original_price'   => (float) $discount['original_price'],
			'discounted_price' => (float) $discount['discounted_price'],
			'discount_amount'  => (float) $discount['discount_amount'],
			'campaign_id'      => isset( $discount['campaign_id'] ) ? (int) $discount['campaign_id'] : 0,
		);

		$this->log(
			'debug',
			'Cart item discount data exposed to Store API',
			array(
				'product_id'    => isset( $cart_item['product_id'] ) ? $cart_item['product_id'] : 0,
				'discount_data' => $discount_data,
			)
		);

		return $discount_data;
	}

	/**
	 * Define schema for discount data
	 *
	 * @since 1.5.70
	 * @return array Schema definition.
	 */
	public function extend_cart_item_schema() {
		return array(
			'has_discount'     => array(
				'description' => __( 'Whether the item has an active discount', 'smart-cycle-discounts' ),
				'type'        => 'boolean',
				'readonly'    => true,
			),
			'original_price'   => array(
				'description' => __( 'Original price before discount', 'smart-cycle-discounts' ),
				'type'        => 'number',
				'readonly'    => true,
			),
			'discounted_price' => array(
				'description' => __( 'Price after discount', 'smart-cycle-discounts' ),
				'type'        => 'number',
				'readonly'    => true,
			),
			'discount_amount'  => array(
				'description' => __( 'Amount saved', 'smart-cycle-discounts' ),
				'type'        => 'number',
				'readonly'    => true,
			),
			'campaign_id'      => array(
				'description' => __( 'Active campaign ID', 'smart-cycle-discounts' ),
				'type'        => 'integer',
				'readonly'    => true,
			),
		);
	}

	/**
	 * Log message
	 *
	 * @since 1.5.70
	 * @param string $level   Log level.
	 * @param string $message Message.
	 * @param array  $context Context.
	 * @return void
	 */
	private function log( $level, $message, $context = array() ) {
		if ( $this->logger && method_exists( $this->logger, $level ) ) {
			$this->logger->$level( '[WC_Blocks] ' . $message, $context );
		}
	}
}
