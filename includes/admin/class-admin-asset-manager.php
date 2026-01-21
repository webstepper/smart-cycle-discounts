<?php
/**
 * Admin Asset Manager Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/class-admin-asset-manager.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


require_once WSSCD_PLUGIN_DIR . 'includes/admin/assets/class-script-registry.php';
require_once WSSCD_PLUGIN_DIR . 'includes/admin/assets/class-style-registry.php';
require_once WSSCD_PLUGIN_DIR . 'includes/admin/assets/class-asset-loader.php';
require_once WSSCD_PLUGIN_DIR . 'includes/admin/assets/class-asset-localizer.php';
require_once WSSCD_PLUGIN_DIR . 'includes/admin/assets/class-theme-color-inline-styles.php';

/**
 * Admin Asset Manager Class
 *
 * @since      1.0.0
 */
class WSSCD_Admin_Asset_Manager {

	/**
	 * Logger instance.
	 *
	 * @since    1.0.0
	 * @var      WSSCD_Logger
	 */
	private WSSCD_Logger $logger;

	/**
	 * Plugin version.
	 *
	 * @since    1.0.0
	 * @var      string
	 */
	private string $version;

	/**
	 * Plugin URL.
	 *
	 * @since    1.0.0
	 * @var      string
	 */
	private string $plugin_url;

	/**
	 * Asset loader instance.
	 *
	 * @since    1.0.0
	 * @var      WSSCD_Asset_Loader|null
	 */
	private ?WSSCD_Asset_Loader $asset_loader = null;

	/**
	 * Asset localizer instance.
	 *
	 * @since    1.0.0
	 * @var      WSSCD_Asset_Localizer|null
	 */
	private ?WSSCD_Asset_Localizer $asset_localizer = null;

	/**
	 * Script registry instance.
	 *
	 * @since    1.0.0
	 * @var      WSSCD_Script_Registry|null
	 */
	private ?WSSCD_Script_Registry $script_registry = null;

	/**
	 * Style registry instance.
	 *
	 * @since    1.0.0
	 * @var      WSSCD_Style_Registry|null
	 */
	private ?WSSCD_Style_Registry $style_registry = null;

	/**
	 * Constructor.
	 *
	 * @since    1.0.0
	 * @param    WSSCD_Logger $logger        Logger instance.
	 * @param    string     $version       Plugin version.
	 * @param    string     $plugin_url    Plugin URL.
	 */
	public function __construct(
		WSSCD_Logger $logger,
		string $version,
		string $plugin_url
	) {
		$this->logger     = $logger;
		$this->version    = $version;
		$this->plugin_url = $plugin_url;
	}

	/**
	 * Initialize the asset manager.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function init(): void {
		$this->init_asset_system();
	}

	/**
	 * Initialize the asset loading system.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	private function init_asset_system(): void {
		$this->script_registry = new WSSCD_Script_Registry( $this->version, $this->plugin_url );
		$this->style_registry  = new WSSCD_Style_Registry( $this->version, $this->plugin_url );

		$this->script_registry->init();
		$this->style_registry->init();

		$this->asset_loader = new WSSCD_Asset_Loader(
			$this->script_registry,
			$this->style_registry,
			$this->version,
			$this->plugin_url
		);

		$settings              = array(
			'version'    => $this->version,
			'plugin_url' => $this->plugin_url,
		);
		$this->asset_localizer = new WSSCD_Asset_Localizer( $settings );

		$this->asset_loader->init();
		$this->asset_localizer->init();

		add_filter( 'wsscd_localize_wsscdDraftManager', array( $this, 'get_draft_manager_data' ) );

		$theme_color_styles = new WSSCD_Theme_Color_Inline_Styles();
		$theme_color_styles->init();
	}

	/**
	 * Get the asset loader instance.
	 *
	 * @since    1.0.0
	 * @return   WSSCD_Asset_Loader|null    Asset loader instance.
	 */
	public function get_asset_loader(): ?WSSCD_Asset_Loader {
		return $this->asset_loader;
	}

	/**
	 * Get the asset localizer instance.
	 *
	 * @since    1.0.0
	 * @return   WSSCD_Asset_Localizer|null    Asset localizer instance.
	 */
	public function get_asset_localizer(): ?WSSCD_Asset_Localizer {
		return $this->asset_localizer;
	}

	/**
	 * Get the script registry instance.
	 *
	 * @since    1.0.0
	 * @return   WSSCD_Script_Registry|null    Script registry instance.
	 */
	public function get_script_registry(): ?WSSCD_Script_Registry {
		return $this->script_registry;
	}

	/**
	 * Get the style registry instance.
	 *
	 * @since    1.0.0
	 * @return   WSSCD_Style_Registry|null    Style registry instance.
	 */
	public function get_style_registry(): ?WSSCD_Style_Registry {
		return $this->style_registry;
	}

	/**
	 * Register a script dynamically.
	 *
	 * @since    1.0.0
	 * @param    string $handle    Script handle.
	 * @param    array  $config    Script configuration.
	 * @return   void
	 */
	public function register_script( string $handle, array $config ): void {
		if ( $this->script_registry ) {
			$this->script_registry->add_script( $handle, $config );
		}
	}

	/**
	 * Register a style dynamically.
	 *
	 * @since    1.0.0
	 * @param    string $handle    Style handle.
	 * @param    array  $config    Style configuration.
	 * @return   void
	 */
	public function register_style( string $handle, array $config ): void {
		if ( $this->style_registry ) {
			$this->style_registry->add_style( $handle, $config );
		}
	}

	/**
	 * Enqueue a script immediately.
	 *
	 * @since    1.0.0
	 * @param    string $handle    Script handle.
	 * @return   void
	 */
	public function enqueue_script( string $handle ): void {
		if ( $this->asset_loader && $this->script_registry ) {
			$script = $this->script_registry->get_script( $handle );
			if ( $script ) {
				wp_enqueue_script(
					$handle,
					rtrim( $this->plugin_url, '/' ) . '/' . ltrim( $script['src'], '/' ),
					$script['deps'],
					$this->version,
					$script['in_footer']
				);
			}
		}
	}

	/**
	 * Enqueue a style immediately.
	 *
	 * @since    1.0.0
	 * @param    string $handle    Style handle.
	 * @return   void
	 */
	public function enqueue_style( string $handle ): void {
		if ( $this->asset_loader && $this->style_registry ) {
			$style = $this->style_registry->get_style( $handle );
			if ( $style ) {
				wp_enqueue_style(
					$handle,
					rtrim( $this->plugin_url, '/' ) . '/' . ltrim( $style['src'], '/' ),
					$style['deps'],
					$this->version,
					$style['media']
				);
			}
		}
	}

	/**
	 * Get draft manager localization data.
	 *
	 * @since    1.0.0
	 * @param    array $data    Default data.
	 * @return   array             Draft manager data.
	 */
	public function get_draft_manager_data( array $data ): array {
		return array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'wsscd_draft_manager' ),
			'i18n'     => array(
				/* translators: %s: name of the existing draft campaign */
				'warning_message' => __( 'You have an existing draft campaign "%s". Starting a new campaign will discard your current draft. Do you want to continue?', 'smart-cycle-discounts' ),
				'load_error'      => __( 'Unable to load draft information.', 'smart-cycle-discounts' ),
				'save_error'      => __( 'Unable to save draft. Please try again.', 'smart-cycle-discounts' ),
				'confirm_discard' => __( 'Are you sure you want to discard this draft? This action cannot be undone.', 'smart-cycle-discounts' ),
				'progress'        => __( 'Progress', 'smart-cycle-discounts' ),
				'steps_completed' => __( 'steps completed', 'smart-cycle-discounts' ),
				'type'            => __( 'Type', 'smart-cycle-discounts' ),
				'products'        => __( 'Products', 'smart-cycle-discounts' ),
				'selected'        => __( 'selected', 'smart-cycle-discounts' ),
				'discount'        => __( 'Discount', 'smart-cycle-discounts' ),
				'schedule'        => __( 'Schedule', 'smart-cycle-discounts' ),
				'to'              => __( 'to', 'smart-cycle-discounts' ),
			),
		);
	}
}
