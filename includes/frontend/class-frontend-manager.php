<?php
/**
 * Frontend Manager Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/frontend/class-frontend-manager.php
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
 * Frontend Manager
 *
 * Handles all frontend functionality including assets, WooCommerce integration,
 * shortcodes, and discount display.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/frontend
 * @author     Webstepper <contact@webstepper.io>
 */
class WSSCD_Frontend_Manager {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private string $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private string $version;

	/**
	 * Container instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      object    $container    Container instance.
	 */
	private object $container;

	/**
	 * Frontend asset manager instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Frontend_Asset_Manager|null    $asset_manager    Frontend asset manager.
	 */
	private ?WSSCD_Frontend_Asset_Manager $asset_manager = null;

	/**
	 * Frontend AJAX handler instance.
	 *
	 * @since    1.5.2
	 * @access   private
	 * @var      WSSCD_Frontend_Ajax_Handler|null    $ajax_handler    Frontend AJAX handler.
	 */
	private ?WSSCD_Frontend_Ajax_Handler $ajax_handler = null;

	/**
	 * Initialize the frontend manager.
	 *
	 * @since    1.0.0
	 * @param    string $plugin_name    The name of the plugin.
	 * @param    string $version        The version of this plugin.
	 * @param    object $container      Container instance.
	 */
	public function __construct( string $plugin_name, string $version, object $container ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
		$this->container   = $container;
	}

	/**
	 * Initialize frontend functionality.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function init(): void {
		if ( $this->container->has( 'frontend_asset_manager' ) ) {
			$this->asset_manager = $this->container->get( 'frontend_asset_manager' );
			if ( $this->asset_manager && method_exists( $this->asset_manager, 'init' ) ) {
				$this->asset_manager->init();
			}
		}

		// Initialize frontend AJAX handler.
		$this->init_ajax_handler();
	}

	/**
	 * Initialize frontend AJAX handler.
	 *
	 * Only loads for premium users as it's primarily used for
	 * spend threshold progress which is a Pro feature.
	 *
	 * @since    1.5.2
	 * @return   void
	 */
	private function init_ajax_handler(): void {
		// Only load for premium users - spend threshold is a Pro feature.
		if ( ! function_exists( 'wsscd_is_premium' ) || ! wsscd_is_premium() ) {
			return;
		}

		// Load AJAX handler class.
		if ( ! class_exists( 'WSSCD_Frontend_Ajax_Handler' ) ) {
			require_once WSSCD_PLUGIN_DIR . 'includes/frontend/class-frontend-ajax-handler.php';
		}

		$this->ajax_handler = new WSSCD_Frontend_Ajax_Handler();
		$this->ajax_handler->init();
	}

	/**
	 * Setup frontend hooks.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function setup_hooks(): void {
		// Setup frontend hooks
	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function enqueue_styles(): void {
		// If asset manager is available, it will handle style enqueuing
		if ( $this->asset_manager ) {
			// Asset manager handles styles through its own enqueue hooks
			return;
		}

		// Fallback: Use non-minified version directly
		$css_file = 'css/frontend/frontend.css';

		// Basic frontend styles
		wp_enqueue_style(
			$this->plugin_name . '-frontend',
			WSSCD_ASSETS_URL . $css_file,
			array(),
			$this->version,
			'all'
		);
	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function enqueue_scripts(): void {
		// If asset manager is available, it will handle script enqueuing
		if ( $this->asset_manager ) {
			// Asset manager handles scripts through its own enqueue hooks
			return;
		}

		// Fallback: Check if frontend script exists before enqueuing
		$js_file = 'js/frontend/frontend.js';
		if ( file_exists( WSSCD_PLUGIN_DIR . 'resources/assets/' . $js_file ) ) {
			wp_enqueue_script(
				$this->plugin_name . '-frontend',
				WSSCD_ASSETS_URL . $js_file,
				array( 'jquery' ),
				$this->version,
				true
			);
		}
	}

	/**
	 * Modify product price.
	 *
	 * @since    1.0.0
	 * @param    mixed $price      The product price.
	 * @param    mixed $product    The product object.
	 * @return   mixed                Modified price.
	 */
	public function modify_product_price( $price, $product ) {
		if ( $this->container->has( 'discount_engine' ) ) {
			$discount_engine = $this->container->get( 'discount_engine' );
			if ( method_exists( $discount_engine, 'apply_discount' ) ) {
				return $discount_engine->apply_discount( $price, $product );
			}
		}
		return $price;
	}

	/**
	 * Modify sale price.
	 *
	 * @since    1.0.0
	 * @param    mixed $price      The sale price.
	 * @param    mixed $product    The product object.
	 * @return   mixed                Modified sale price.
	 */
	public function modify_sale_price( $price, $product ) {
		if ( $this->container->has( 'discount_engine' ) ) {
			$discount_engine = $this->container->get( 'discount_engine' );
			if ( method_exists( $discount_engine, 'apply_discount' ) ) {
				return $discount_engine->apply_discount( $price, $product );
			}
		}
		return $price;
	}

	/**
	 * Register shortcodes.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function register_shortcodes(): void {
		if ( $this->container->has( 'shortcodes' ) ) {
			$shortcodes = $this->container->get( 'shortcodes' );
			if ( method_exists( $shortcodes, 'register_all' ) ) {
				$shortcodes->register_all();
			}
		}
	}

	/**
	 * Display discount badge on shop page.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function display_shop_discount_badge(): void {
		if ( $this->container->has( 'discount_display' ) ) {
			$discount_display = $this->container->get( 'discount_display' );
			if ( method_exists( $discount_display, 'render_shop_badge' ) ) {
				$discount_display->render_shop_badge();
			}
		}
	}
}
