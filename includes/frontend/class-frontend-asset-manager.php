<?php
/**
 * Frontend Asset Manager Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/frontend/class-frontend-asset-manager.php
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Frontend Asset Manager
 *
 * Handles loading of frontend CSS and JavaScript files.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/frontend
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Frontend_Asset_Manager {

	/**
	 * Plugin version for cache busting.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    Plugin version.
	 */
	private string $version;

	/**
	 * Plugin name for script handles.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    Plugin name.
	 */
	private string $plugin_name;

	/**
	 * Logger instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Logger|null    $logger    Logger instance.
	 */
	private ?SCD_Logger $logger = null;

	/**
	 * Script registry instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Frontend_Script_Registry|null    $script_registry    Script registry.
	 */
	private ?SCD_Frontend_Script_Registry $script_registry = null;

	/**
	 * Asset localizer instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Asset_Localizer|null    $asset_localizer    Asset localizer.
	 */
	private ?SCD_Asset_Localizer $asset_localizer = null;

	/**
	 * Initialize the asset manager.
	 *
	 * @since    1.0.0
	 * @param    string     $plugin_name    Plugin name.
	 * @param    string     $version        Plugin version.
	 * @param    SCD_Logger $logger         Logger instance.
	 */
	public function __construct( string $plugin_name, string $version, ?SCD_Logger $logger = null ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
		$this->logger      = $logger;

		// Initialize script registry
		if ( file_exists( SCD_PLUGIN_DIR . 'includes/frontend/assets/class-frontend-script-registry.php' ) ) {
			require_once SCD_PLUGIN_DIR . 'includes/frontend/assets/class-frontend-script-registry.php';
			$this->script_registry = new SCD_Frontend_Script_Registry( $version, SCD_PLUGIN_URL );
			$this->script_registry->init();
		}

		// Initialize asset localizer
		if ( file_exists( SCD_PLUGIN_DIR . 'includes/admin/assets/class-asset-localizer.php' ) ) {
			require_once SCD_PLUGIN_DIR . 'includes/admin/assets/class-asset-localizer.php';
			$this->asset_localizer = new SCD_Asset_Localizer();
			$this->asset_localizer->init();
		}
	}

	/**
	 * Initialize the asset manager.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function init(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		$this->log_debug( 'Frontend asset manager initialized' );
	}

	/**
	 * Enqueue frontend stylesheets.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function enqueue_styles(): void {
		// Only load on pages where we need frontend styles
		if ( ! $this->should_load_assets() ) {
			return;
		}

		// Main frontend stylesheet
		wp_enqueue_style(
			$this->plugin_name . '-frontend',
			SCD_PLUGIN_URL . 'resources/assets/css/frontend/frontend.css',
			array(),
			$this->version,
			'all'
		);

		$this->log_debug( 'Frontend styles enqueued' );
	}

	/**
	 * Enqueue frontend scripts.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function enqueue_scripts(): void {
		// Only load on pages where we need frontend scripts
		if ( ! $this->should_load_assets() ) {
			return;
		}

		if ( $this->script_registry ) {
			// Get scripts that should be loaded
			$scripts = $this->script_registry->get_scripts_to_load();

			foreach ( $scripts as $handle => $script ) {
				// Register and enqueue the script
				$src = $script['src'];
				if ( ! filter_var( $src, FILTER_VALIDATE_URL ) ) {
					$src = SCD_PLUGIN_URL . $src;
				}

				wp_register_script(
					$handle,
					$src,
					$script['deps'],
					$script['version'] ?? $this->version,
					$script['in_footer']
				);

				// Handle localization
				if ( ! empty( $script['localize'] ) && $this->asset_localizer ) {
					$this->localize_script( $handle, $script['localize'] );
				}

				wp_enqueue_script( $handle );
			}
		} else {
			// Fallback to basic localization
			if ( ! empty( $GLOBALS['wp_scripts']->registered['jquery'] ) ) {
				$this->localize_scripts();
			}
		}

		$this->log_debug( 'Frontend scripts enqueued' );
	}

	/**
	 * Localize a specific script.
	 *
	 * @since    1.0.0
	 * @param    string $handle         Script handle.
	 * @param    string $object_name    Object name for localization.
	 * @return   void
	 */
	private function localize_script( string $handle, string $object_name ): void {
		$data = array();

		switch ( $object_name ) {
			case 'scdFrontend':
				$data = $this->get_frontend_localization_data();
				break;

			case 'scdAnalyticsTracking':
				$data = $this->get_analytics_localization_data();
				break;

			default:
				$data = apply_filters( 'scd_frontend_localize_' . $object_name, array(), $handle );
				break;
		}

		if ( ! empty( $data ) ) {
			wp_localize_script( $handle, $object_name, $data );
		}
	}

	/**
	 * Get frontend localization data.
	 *
	 * @since    1.0.0
	 * @return   array    Localization data.
	 */
	private function get_frontend_localization_data(): array {
		return array(
			'ajax_url'           => admin_url( 'admin-ajax.php' ),
			'nonce'              => wp_create_nonce( 'scd_frontend_nonce' ),
			'rest_url'           => rest_url( 'scd/v1/' ),
			'rest_nonce'         => wp_create_nonce( 'wp_rest' ),
			'currency_symbol'    => get_woocommerce_currency_symbol(),
			'currency_position'  => get_option( 'woocommerce_currency_pos' ),
			'thousand_separator' => wc_get_price_thousand_separator(),
			'decimal_separator'  => wc_get_price_decimal_separator(),
			'price_decimals'     => wc_get_price_decimals(),
			'strings'            => array(
				'loading'          => __( 'Loading...', 'smart-cycle-discounts' ),
				'error'            => __( 'An error occurred. Please try again.', 'smart-cycle-discounts' ),
				'discount_applied' => __( 'Discount applied!', 'smart-cycle-discounts' ),
				'sale_ends_in'     => __( 'Sale ends in:', 'smart-cycle-discounts' ),
				'expired'          => __( 'Expired', 'smart-cycle-discounts' ),
				'days'             => __( 'days', 'smart-cycle-discounts' ),
				'hours'            => __( 'hours', 'smart-cycle-discounts' ),
				'minutes'          => __( 'minutes', 'smart-cycle-discounts' ),
				'seconds'          => __( 'seconds', 'smart-cycle-discounts' ),
			),
			'settings'           => array(
				'auto_update_prices'    => get_option( 'scd_auto_update_prices', true ),
				'show_countdown_timers' => get_option( 'scd_show_countdown_timers', true ),
				'animate_price_changes' => get_option( 'scd_animate_price_changes', true ),
				'debug_mode'            => SCD_DEBUG,
			),
		);
	}

	/**
	 * Get analytics localization data.
	 *
	 * @since    1.0.0
	 * @return   array    Localization data.
	 */
	private function get_analytics_localization_data(): array {
		// Load Ajax security class if needed
		if ( ! class_exists( 'SCD_Ajax_Security' ) ) {
			require_once SCD_PLUGIN_DIR . 'includes/admin/ajax/class-ajax-security.php';
		}

		return array(
			'ajax_url'       => admin_url( 'admin-ajax.php' ),
			'nonce'          => wp_create_nonce( 'scd_public_tracking_nonce' ),
			'tracking_token' => wp_hash( 'scd_tracking_' . date( 'Y-m-d' ) . '_' . SCD_Ajax_Security::get_client_ip() ),
		);
	}

	/**
	 * Localize scripts with necessary data.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function localize_scripts(): void {
		// Fallback localization when not using registry
		$localized_data = $this->get_frontend_localization_data();

		// Localize to jQuery for now until frontend script is created
		wp_localize_script(
			'jquery',
			'scdFrontend',
			$localized_data
		);

		$this->log_debug( 'Scripts localized with data' );
	}

	/**
	 * Check if assets should be loaded on current page.
	 *
	 * @since    1.0.0
	 * @return   bool    True if assets should be loaded.
	 */
	private function should_load_assets(): bool {
		// Always load on WooCommerce pages
		if ( function_exists( 'is_woocommerce' ) && is_woocommerce() ) {
			return true;
		}

		// Load on shop page
		if ( function_exists( 'is_shop' ) && is_shop() ) {
			return true;
		}

		// Load on product pages
		if ( function_exists( 'is_product' ) && is_product() ) {
			return true;
		}

		// Load on cart page
		if ( function_exists( 'is_cart' ) && is_cart() ) {
			return true;
		}

		// Load on checkout page
		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			return true;
		}

		// Load on account page
		if ( function_exists( 'is_account_page' ) && is_account_page() ) {
			return true;
		}

		// Load on pages with shortcodes
		if ( is_singular() && $this->has_plugin_shortcodes() ) {
			return true;
		}

		// Load on pages with blocks
		if ( is_singular() && $this->has_plugin_blocks() ) {
			return true;
		}

		// Allow filtering
		return apply_filters( 'scd_should_load_frontend_assets', false );
	}

	/**
	 * Check if current page has plugin shortcodes.
	 *
	 * @since    1.0.0
	 * @return   bool    True if page has plugin shortcodes.
	 */
	private function has_plugin_shortcodes(): bool {
		global $post;

		if ( ! $post || ! $post->post_content ) {
			return false;
		}

		$shortcodes = array(
			'scd_discount_badge',
			'scd_countdown_timer',
			'scd_featured_discounts',
			'scd_campaign_showcase',
		);

		foreach ( $shortcodes as $shortcode ) {
			if ( has_shortcode( $post->post_content, $shortcode ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if current page has plugin blocks.
	 *
	 * @since    1.0.0
	 * @return   bool    True if page has plugin blocks.
	 */
	private function has_plugin_blocks(): bool {
		global $post;

		if ( ! $post || ! $post->post_content ) {
			return false;
		}

		$blocks = array(
			'scd/discount-showcase',
			'scd/countdown-timer',
			'scd/featured-discounts',
		);

		foreach ( $blocks as $block ) {
			if ( has_block( $block, $post ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Enqueue specific asset for a component.
	 *
	 * @since    1.0.0
	 * @param    string $component    Component name.
	 * @param    array  $deps         Dependencies.
	 * @return   void
	 */
	public function enqueue_component_assets( string $component, array $deps = array() ): void {
		$component_slug = sanitize_title( $component );

		// Enqueue component CSS
		$css_path = SCD_ASSETS_URL . "dist/css/components/{$component_slug}.css";
		if ( file_exists( SCD_ASSETS_DIR . "dist/css/components/{$component_slug}.css" ) ) {
			wp_enqueue_style(
				$this->plugin_name . "-{$component_slug}",
				$css_path,
				array_merge( array( $this->plugin_name . '-frontend' ), $deps ),
				$this->version,
				'all'
			);
		}

		// Enqueue component JS
		$js_path = SCD_ASSETS_URL . "dist/js/components/{$component_slug}.js";
		if ( file_exists( SCD_ASSETS_DIR . "dist/js/components/{$component_slug}.js" ) ) {
			wp_enqueue_script(
				$this->plugin_name . "-{$component_slug}",
				$js_path,
				array_merge( array( 'jquery', $this->plugin_name . '-frontend' ), $deps ),
				$this->version,
				true
			);
		}

		$this->log_debug( "Component assets enqueued for: {$component}" );
	}

	/**
	 * Dequeue all plugin assets.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function dequeue_all_assets(): void {
		// Dequeue styles
		wp_dequeue_style( $this->plugin_name . '-frontend' );
		wp_dequeue_style( $this->plugin_name . '-discount-badge' );
		wp_dequeue_style( $this->plugin_name . '-countdown-timer' );

		// Dequeue scripts
		wp_dequeue_script( $this->plugin_name . '-frontend' );
		wp_dequeue_script( $this->plugin_name . '-discount-display' );
		wp_dequeue_script( $this->plugin_name . '-countdown-timer' );

		$this->log_debug( 'All frontend assets dequeued' );
	}

	/**
	 * Get asset URL with cache busting.
	 *
	 * @since    1.0.0
	 * @param    string $asset_path    Asset path relative to assets directory.
	 * @return   string                   Full asset URL with version.
	 */
	public function get_asset_url( string $asset_path ): string {
		$url = SCD_ASSETS_URL . ltrim( $asset_path, '/' );
		return add_query_arg( 'ver', $this->version, $url );
	}

	/**
	 * Check if asset file exists.
	 *
	 * @since    1.0.0
	 * @param    string $asset_path    Asset path relative to assets directory.
	 * @return   bool                     True if asset exists.
	 */
	public function asset_exists( string $asset_path ): bool {
		$file_path = SCD_ASSETS_DIR . ltrim( $asset_path, '/' );
		return file_exists( $file_path );
	}

	/**
	 * Get inline styles for critical CSS.
	 *
	 * @since    1.0.0
	 * @return   string    Critical CSS styles.
	 */
	public function get_critical_css(): string {
		$critical_css = '
        .scd-discount-badge {
            display: inline-block;
            background: #e74c3c;
            color: white;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            line-height: 1;
        }
        
        .scd-countdown-timer {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .scd-countdown-timer .time-unit {
            text-align: center;
            min-width: 40px;
        }
        
        .scd-loading {
            opacity: 0.6;
            pointer-events: none;
        }
        ';

		return apply_filters( 'scd_critical_css', $critical_css );
	}

	/**
	 * Add inline critical CSS to head.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function add_critical_css(): void {
		if ( ! $this->should_load_assets() ) {
			return;
		}

		$critical_css = $this->get_critical_css();
		if ( ! empty( $critical_css ) ) {
			echo "<style id='scd-critical-css'>" . wp_strip_all_tags( $critical_css ) . "</style>\n";
		}
	}

	/**
	 * Log debug message.
	 *
	 * @since    1.0.0
	 * @param    string $message    Debug message.
	 * @return   void
	 */
	private function log_debug( string $message ): void {
		if ( $this->logger && SCD_DEBUG ) {
			$this->logger->debug( $message, array( 'component' => 'frontend_asset_manager' ) );
		}
	}

	/**
	 * Get plugin version.
	 *
	 * @since    1.0.0
	 * @return   string    Plugin version.
	 */
	public function get_version(): string {
		return $this->version;
	}

	/**
	 * Get plugin name.
	 *
	 * @since    1.0.0
	 * @return   string    Plugin name.
	 */
	public function get_plugin_name(): string {
		return $this->plugin_name;
	}
}
