<?php
/**
 * Asset Loader Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/assets/class-asset-loader.php
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
 * Asset Loader Class
 *
 * @since 1.0.0
 */
class WSSCD_Asset_Loader {

	/**
	 * Script registry.
	 *
	 * @since 1.0.0
	 * @var WSSCD_Script_Registry
	 */
	private WSSCD_Script_Registry $script_registry;

	/**
	 * Style registry.
	 *
	 * @since 1.0.0
	 * @var WSSCD_Style_Registry
	 */
	private WSSCD_Style_Registry $style_registry;

	/**
	 * Plugin version.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private string $version;

	/**
	 * Plugin URL.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private string $plugin_url;

	/**
	 * Current page context.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private array $context = array();

	/**
	 * Loaded assets tracking.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private array $loaded = array(
		'scripts' => array(),
		'styles'  => array(),
	);

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param WSSCD_Script_Registry $script_registry Script registry.
	 * @param WSSCD_Style_Registry  $style_registry  Style registry.
	 * @param string              $version         Plugin version.
	 * @param string              $plugin_url      Plugin URL.
	 */
	public function __construct(
		WSSCD_Script_Registry $script_registry,
		WSSCD_Style_Registry $style_registry,
		string $version,
		string $plugin_url
	) {
		$this->script_registry = $script_registry;
		$this->style_registry  = $style_registry;
		$this->version         = $version;
		$this->plugin_url      = $plugin_url;
	}

	/**
	 * Initialize loader.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function init(): void {
		// Use earlier priority to ensure asset loading happens before other enqueues
		add_action( 'admin_enqueue_scripts', array( $this, 'load_assets' ), 5 );
		add_action( 'admin_print_scripts', array( $this, 'print_inline_scripts' ), 5 );
		add_action( 'admin_print_styles', array( $this, 'print_inline_styles' ), 5 );

		// AJAX lazy loading now handled by unified router
	}

	/**
	 * Load assets for current page.
	 *
	 * @since 1.0.0
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function load_assets( string $hook ): void {
		// Determine context
		$this->context = $this->determine_context( $hook );

		if ( ! $this->should_load_assets() ) {
			return;
		}

		$this->load_scripts();

		$this->load_styles();

		// Setup lazy loading if needed
		$this->setup_lazy_loading();
	}

	/**
	 * Determine current page context.
	 *
	 * SECURITY: This method ONLY reads URL parameters for asset loading context.
	 * No data processing occurs - values are used only for conditional asset loading.
	 * Capability is checked for defense in depth. Menu registration already enforces access.
	 *
	 * @since 1.0.0
	 * @param string $hook Admin page hook.
	 * @return array Context data.
	 */
	private function determine_context( string $hook ): array {
		// Defense in depth: verify user has admin capability.
		// Menu registration already enforces this, but we check again for safety.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return array(
				'hook'          => $hook,
				'page'          => '',
				'action'        => null,
				'step'          => null,
				'tab'           => null,
				'is_wsscd_page' => false,
			);
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Reading URL params for asset context only. Capability checked above. Values validated against whitelist in consumers.
		$context = array(
			'hook'        => $hook,
			'page'        => '',
			'action'      => isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : null,
			'step'        => isset( $_GET['step'] ) ? sanitize_key( wp_unslash( $_GET['step'] ) ) : null,
			'tab'         => isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : null,
			'is_wsscd_page' => false,
		);
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		// Map hooks to page identifiers
		// Note: WordPress may shorten menu slugs (smart-cycle-discounts → sc-discounts)
		$page_map = array(
			'toplevel_page_smart-cycle-discounts'        => 'wsscd-dashboard',
			'toplevel_page_sc-discounts'                 => 'wsscd-dashboard',
			'toplevel_page_wsscd-campaigns'                => 'wsscd-campaigns',
			'smart-cycle-discounts_page_wsscd-campaigns'   => 'wsscd-campaigns',
			'sc-discounts_page_wsscd-campaigns'            => 'wsscd-campaigns',
			'smart-cycle-discounts_page_wsscd-analytics'   => 'wsscd-analytics',
			'sc-discounts_page_wsscd-analytics'            => 'wsscd-analytics',
			'smart-cycle-discounts_page_wsscd-settings'    => 'wsscd-settings',
			'sc-discounts_page_wsscd-settings'             => 'wsscd-settings',
			'smart-cycle-discounts_page_wsscd-tools'       => 'wsscd-tools',
			'sc-discounts_page_wsscd-tools'                => 'wsscd-tools',
			'smart-cycle-discounts_page_wsscd-help'        => 'wsscd-help',
			'sc-discounts_page_wsscd-help'                 => 'wsscd-help',
			'smart-cycle-discounts_page_wsscd-notifications' => 'wsscd-notifications',
			'sc-discounts_page_wsscd-notifications'        => 'wsscd-notifications',
		);

		if ( isset( $page_map[ $hook ] ) ) {
			$context['page']        = $page_map[ $hook ];
			$context['is_wsscd_page'] = true;
		} elseif ( strpos( $hook, '_page_wsscd-' ) !== false ) {
			// Fallback for any SCD page
			$context['is_wsscd_page'] = true;
			if ( preg_match( '/_page_wsscd-([a-z]+)/', $hook, $matches ) ) {
				$context['page'] = 'wsscd-' . $matches[1];
			}
		}

		// Set default tab for pages that have tabs when no tab parameter is specified
		if ( null === $context['tab'] && $context['is_wsscd_page'] ) {
			$default_tabs = array(
				'wsscd-settings'      => 'general',
				'wsscd-notifications' => 'settings',
			);
			if ( isset( $default_tabs[ $context['page'] ] ) ) {
				$context['tab'] = $default_tabs[ $context['page'] ];
			}
		}

		return apply_filters( 'wsscd_asset_loader_context', $context, $hook );
	}

	/**
	 * Check if assets should be loaded.
	 *
	 * @since 1.0.0
	 * @return bool True if should load.
	 */
	private function should_load_assets(): bool {
		if ( ! $this->context['is_wsscd_page'] ) {
			return false;
		}

		return apply_filters( 'wsscd_should_load_assets', true, $this->context );
	}

	/**
	 * Load scripts for current context.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function load_scripts(): void {
		$scripts = $this->script_registry->get_scripts_for_page( $this->context['page'] );

		foreach ( $scripts as $handle => $script ) {
			if ( $this->should_load_script( $handle, $script ) ) {
				$this->enqueue_script( $handle, $script );
				$this->loaded['scripts'][] = $handle;

				if ( defined( 'WSSCD_DEBUG' ) && WSSCD_DEBUG ) {
				}
			} elseif ( defined( 'WSSCD_DEBUG' ) && WSSCD_DEBUG ) {

			}
		}
	}

	/**
	 * Load styles for current context.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function load_styles(): void {
		$styles = $this->style_registry->get_styles_for_page( $this->context['page'] );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {

			// Debug specific wizard step CSS files
			foreach ( $styles as $handle => $style ) {
				if ( strpos( $handle, 'wizard-step' ) !== false ) {
				}
			}
		}

		foreach ( $styles as $handle => $style ) {
			if ( $this->should_load_style( $handle, $style ) ) {
				$this->enqueue_style( $handle, $style );
				$this->loaded['styles'][] = $handle;

				if ( defined( 'WP_DEBUG' ) && WP_DEBUG && strpos( $handle, 'step-basic' ) !== false ) {
				}
			} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG && strpos( $handle, 'step-basic' ) !== false ) {

			}
		}
	}

	/**
	 * Check if script should be loaded.
	 *
	 * @since 1.0.0
	 * @param string $handle Script handle.
	 * @param array  $script Script config.
	 * @return bool True if should load.
	 */
	private function should_load_script( string $handle, array $script ): bool {
		// Skip lazy scripts in initial load
		if ( $script['lazy'] ) {
			return false;
		}

		if ( ! empty( $script['condition'] ) ) {
			foreach ( $script['condition'] as $key => $value ) {
				// Special handling for null values in conditions (means the key should not be set)
				if ( null === $value && isset( $this->context[ $key ] ) ) {
					return false;
				} elseif ( null !== $value && ( ! isset( $this->context[ $key ] ) || $this->context[ $key ] !== $value ) ) {
					return false;
				}
			}
		}

		return apply_filters( 'wsscd_should_load_script', true, $handle, $script, $this->context );
	}

	/**
	 * Check if style should be loaded.
	 *
	 * @since 1.0.0
	 * @param string $handle Style handle.
	 * @param array  $style  Style config.
	 * @return bool True if should load.
	 */
	private function should_load_style( string $handle, array $style ): bool {
		// Skip lazy styles in initial load
		if ( $style['lazy'] ) {
			return false;
		}

		if ( ! empty( $style['condition'] ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && strpos( $handle, 'step-basic' ) !== false ) {
			}

			foreach ( $style['condition'] as $key => $value ) {
				// Special handling for null values in conditions (means the key should not be set)
				if ( null === $value && isset( $this->context[ $key ] ) ) {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG && strpos( $handle, 'step-basic' ) !== false ) {
					}
					return false;
				} elseif ( null !== $value && ( ! isset( $this->context[ $key ] ) || $this->context[ $key ] !== $value ) ) {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG && strpos( $handle, 'step-basic' ) !== false ) {
					}
					return false;
				}
			}

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && strpos( $handle, 'step-basic' ) !== false ) {
			}
		}

		return apply_filters( 'wsscd_should_load_style', true, $handle, $style, $this->context );
	}

	/**
	 * Enqueue a script.
	 *
	 * @since 1.0.0
	 * @param string $handle Script handle.
	 * @param array  $script Script config.
	 * @return void
	 */
	private function enqueue_script( string $handle, array $script ): void {
		// Handle external scripts differently
		if ( ! empty( $script['external'] ) ) {
			$src = $script['src'];
			$ver = null; // External scripts should not use plugin version
		} else {
			// Ensure proper URL construction without double slashes
			$src = rtrim( $this->plugin_url, '/' ) . '/' . ltrim( $script['src'], '/' );
			// Use timestamp for development to bypass caching
			$ver = defined( 'WP_DEBUG' ) && WP_DEBUG ? time() : $this->version;
		}

		$deps      = $script['deps'];
		$in_footer = $script['in_footer'];

		wp_enqueue_script( $handle, $src, $deps, $ver, $in_footer );

		if ( ! empty( $script['async'] ) ) {
			wp_script_add_data( $handle, 'async', true );
		}
		if ( ! empty( $script['defer'] ) ) {
			wp_script_add_data( $handle, 'defer', true );
		}

		// Handle localization
		if ( ! empty( $script['localize'] ) ) {
			$this->handle_script_localization( $handle, $script['localize'] );
		}
	}

	/**
	 * Enqueue a style.
	 *
	 * @since 1.0.0
	 * @param string $handle Style handle.
	 * @param array  $style  Style config.
	 * @return void
	 */
	private function enqueue_style( string $handle, array $style ): void {
		// Allow dynamic src via callback (for theme files that depend on settings)
		if ( is_callable( $style['src'] ) ) {
			$style['src'] = call_user_func( $style['src'] );
		}

		// Handle external styles differently
		if ( ! empty( $style['external'] ) ) {
			$src = $style['src'];
			$ver = null; // External styles should not use plugin version
		} else {
			// Ensure proper URL construction without double slashes
			$src = rtrim( $this->plugin_url, '/' ) . '/' . ltrim( $style['src'], '/' );
			// Use timestamp for development to bypass caching
			$ver = defined( 'WP_DEBUG' ) && WP_DEBUG ? time() : $this->version;
		}

		$deps  = $style['deps'];
		$media = $style['media'];

		wp_enqueue_style( $handle, $src, $deps, $ver, $media );
	}

	/**
	 * Handle script localization.
	 *
	 * @since 1.0.0
	 * @param string $handle Script handle.
	 * @param mixed  $localize Localization config.
	 * @return void
	 */
	private function handle_script_localization( string $handle, $localize ): void {
		// Delegate to localizer service
		do_action( 'wsscd_localize_script', $handle, $localize, $this->context );
	}

	/**
	 * Setup lazy loading.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function setup_lazy_loading(): void {
		$lazy_scripts = array();
		$lazy_styles  = array();

		$all_scripts = $this->script_registry->get_scripts_for_page( $this->context['page'] );
		foreach ( $all_scripts as $handle => $script ) {
			if ( $script['lazy'] && $this->matches_conditions( $script['condition'] ) ) {
				$lazy_scripts[ $handle ] = $script['src'];
			}
		}

		$all_styles = $this->style_registry->get_styles_for_page( $this->context['page'] );
		foreach ( $all_styles as $handle => $style ) {
			if ( $style['lazy'] && $this->matches_conditions( $style['condition'] ) ) {
				$lazy_styles[ $handle ] = $style['src'];
			}
		}

		if ( ! empty( $lazy_scripts ) || ! empty( $lazy_styles ) ) {
			wp_localize_script(
				'wsscd-admin-main',
				'wsscdLazyAssets',
				array(
					'scripts'  => $lazy_scripts,
					'styles'   => $lazy_styles,
					'nonce'    => wp_create_nonce( 'wsscd_lazy_assets' ),
					'ajax_url' => admin_url( 'admin-ajax.php' ),
				)
			);
		}
	}

	/**
	 * Check if conditions match current context.
	 *
	 * @since 1.0.0
	 * @param array $conditions Conditions to check.
	 * @return bool True if matches.
	 */
	private function matches_conditions( array $conditions ): bool {
		if ( empty( $conditions ) ) {
			return true;
		}

		foreach ( $conditions as $key => $value ) {
			if ( ! isset( $this->context[ $key ] ) || $this->context[ $key ] !== $value ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Handle lazy load AJAX request.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function handle_lazy_load_request(): void {
		check_ajax_referer( 'wsscd_lazy_assets', 'nonce' );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Array sanitized via array_map below.
		$handles = isset( $_POST['handles'] ) ? wp_unslash( $_POST['handles'] ) : array();
		$type    = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : 'script';

		if ( ! is_array( $handles ) ) {
			WSSCD_AJAX_Response::error( 'Invalid handles', 'invalid_parameter' );
		}

		$sanitized_handles = array_map( 'sanitize_key', $handles );
		$loaded            = array();

		foreach ( $sanitized_handles as $handle ) {
			if ( 'script' === $type ) {
				$script = $this->script_registry->get_script( $handle );
				if ( $script ) {
					$loaded[ $handle ] = array(
						'src'  => rtrim( $this->plugin_url, '/' ) . '/' . ltrim( $script['src'], '/' ),
						'deps' => $script['deps'],
					);
				}
			} else {
				$style = $this->style_registry->get_style( $handle );
				if ( $style ) {
					$loaded[ $handle ] = array(
						'src'  => rtrim( $this->plugin_url, '/' ) . '/' . ltrim( $style['src'], '/' ),
						'deps' => $style['deps'],
					);
				}
			}
		}

		WSSCD_AJAX_Response::success( $loaded );
	}

	/**
	 * Print inline scripts.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function print_inline_scripts(): void {
		if ( ! $this->context['is_wsscd_page'] ) {
			return;
		}

		// Print configuration object using wp_add_inline_script
		$inline_script = 'window.wsscdAssetLoader = ' . wp_json_encode(
			array(
				'loaded'  => $this->loaded,
				'context' => $this->context,
				'version' => $this->version,
			)
		) . ';';

		wp_add_inline_script( 'wsscd-admin', $inline_script, 'before' );
	}

	/**
	 * Print inline styles.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function print_inline_styles(): void {
		$inline_styles = $this->style_registry->get_inline_styles();

		foreach ( $inline_styles as $handle => $style ) {
			if ( in_array( $handle, $this->loaded['styles'], true ) ) {
				do_action( 'wsscd_print_inline_style', $handle, $style );
			}
		}
	}

	/**
	 * Get loaded assets.
	 *
	 * @since 1.0.0
	 * @return array Loaded assets.
	 */
	public function get_loaded_assets(): array {
		return $this->loaded;
	}

	/**
	 * Check if asset is loaded.
	 *
	 * @since 1.0.0
	 * @param string $handle Asset handle.
	 * @param string $type   Asset type (script|style).
	 * @return bool True if loaded.
	 */
	public function is_loaded( string $handle, string $type = 'script' ): bool {
		$key = 'script' === $type ? 'scripts' : 'styles';
		return in_array( $handle, $this->loaded[ $key ], true );
	}
}
