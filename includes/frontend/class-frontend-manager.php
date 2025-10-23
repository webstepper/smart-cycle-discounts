<?php
/**
 * Frontend manager
 *
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/frontend
 */

declare(strict_types=1);


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
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Frontend_Manager {

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
     * @var      SCD_Frontend_Asset_Manager|null    $asset_manager    Frontend asset manager.
     */
    private ?SCD_Frontend_Asset_Manager $asset_manager = null;

    /**
     * Initialize the frontend manager.
     *
     * @since    1.0.0
     * @param    string    $plugin_name    The name of the plugin.
     * @param    string    $version        The version of this plugin.
     * @param    object    $container      Container instance.
     */
    public function __construct(string $plugin_name, string $version, object $container) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->container = $container;
    }

    /**
     * Initialize frontend functionality.
     *
     * @since    1.0.0
     * @return   void
     */
    public function init(): void {
        // Initialize frontend asset manager if available
        if ( $this->container->has( 'frontend_asset_manager' ) ) {
            $this->asset_manager = $this->container->get( 'frontend_asset_manager' );
            if ( $this->asset_manager && method_exists( $this->asset_manager, 'init' ) ) {
                $this->asset_manager->init();
            }
        }
        
        // Frontend initialization is now handled by this consolidated manager
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
            SCD_ASSETS_URL . $css_file, array(),
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
        if (file_exists(SCD_PLUGIN_DIR . 'resources/assets/' . $js_file)) {
            wp_enqueue_script(
                $this->plugin_name . '-frontend',
                SCD_ASSETS_URL . $js_file,
                ['jquery'],
                $this->version,
                true
            );
        }
    }

    /**
     * Modify product price.
     *
     * @since    1.0.0
     * @param    mixed    $price      The product price.
     * @param    mixed    $product    The product object.
     * @return   mixed                Modified price.
     */
    public function modify_product_price($price, $product) {
        if ($this->container->has('discount_engine')) {
            $discount_engine = $this->container->get('discount_engine');
            if (method_exists($discount_engine, 'apply_discount')) {
                return $discount_engine->apply_discount($price, $product);
            }
        }
        return $price;
    }

    /**
     * Modify sale price.
     *
     * @since    1.0.0
     * @param    mixed    $price      The sale price.
     * @param    mixed    $product    The product object.
     * @return   mixed                Modified sale price.
     */
    public function modify_sale_price($price, $product) {
        if ($this->container->has('discount_engine')) {
            $discount_engine = $this->container->get('discount_engine');
            if (method_exists($discount_engine, 'apply_discount')) {
                return $discount_engine->apply_discount($price, $product);
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
        if ($this->container->has('shortcodes')) {
            $shortcodes = $this->container->get('shortcodes');
            if (method_exists($shortcodes, 'register_all')) {
                $shortcodes->register_all();
            }
        }
    }

    /**
     * Display discount badge on single product page.
     *
     * @since    1.0.0
     * @return   void
     */
    public function display_discount_badge(): void {
        if ($this->container->has('discount_display')) {
            $discount_display = $this->container->get('discount_display');
            if (method_exists($discount_display, 'render_single_product_badge')) {
                $discount_display->render_single_product_badge();
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
        if ($this->container->has('discount_display')) {
            $discount_display = $this->container->get('discount_display');
            if (method_exists($discount_display, 'render_shop_badge')) {
                $discount_display->render_shop_badge();
            }
        }
    }
}
