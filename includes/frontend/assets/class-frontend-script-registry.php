<?php
/**
 * Frontend Script Registry Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/frontend/assets/class-frontend-script-registry.php
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
 * Frontend Script Registry Class
 *
 * @since 1.0.0
 */
class SCD_Frontend_Script_Registry {

	/**
	 * Registered scripts.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private array $scripts = array();

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
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param string $version    Plugin version.
	 * @param string $plugin_url Plugin URL.
	 */
	public function __construct( string $version, string $plugin_url ) {
		$this->version    = $version;
		$this->plugin_url = $plugin_url;
	}

	/**
	 * Initialize script registry.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function init(): void {
		$this->register_analytics_scripts();
		$this->register_discount_display_scripts();
		$this->register_component_scripts();

		// Allow extensions
		do_action( 'scd_frontend_script_registry_init', $this );
	}

	/**
	 * Register analytics scripts.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function register_analytics_scripts(): void {
		// Analytics tracking script
		$this->add_script(
			'scd-analytics-tracking',
			array(
				'src'        => 'resources/assets/js/analytics/scd-analytics-tracking.js',
				'deps'       => array( 'jquery' ),
				'localize'   => 'scdAnalyticsTracking',
				'in_footer'  => true,
				'conditions' => array(
					array(
						'function' => 'is_woocommerce',
						'result'   => true,
					),
				),
			)
		);
	}

	/**
	 * Register discount display scripts.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function register_discount_display_scripts(): void {
		// Main frontend script
		$this->add_script(
			'scd-frontend',
			array(
				'src'        => 'resources/assets/js/frontend/main.js',
				'deps'       => array( 'jquery' ),
				'localize'   => 'scdFrontend',
				'in_footer'  => true,
				'conditions' => array(
					array(
						'function' => 'is_woocommerce',
						'result'   => true,
					),
				),
			)
		);
	}

	/**
	 * Register component scripts.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function register_component_scripts(): void {
		// Component scripts are loaded on-demand
	}

	/**
	 * Add a script definition.
	 *
	 * @since 1.0.0
	 * @param string $handle Script handle.
	 * @param array  $config Script configuration.
	 * @return void
	 */
	public function add_script( string $handle, array $config ): void {
		$this->scripts[ $handle ] = wp_parse_args(
			$config,
			array(
				'src'        => '',
				'deps'       => array(),
				'localize'   => null,
				'in_footer'  => true,
				'conditions' => array(),
				'version'    => $this->version,
			)
		);
	}

	/**
	 * Get script configuration.
	 *
	 * @since 1.0.0
	 * @param string $handle Script handle.
	 * @return array|null Script config or null if not found.
	 */
	public function get_script( string $handle ): ?array {
		return $this->scripts[ $handle ] ?? null;
	}

	/**
	 * Get all scripts.
	 *
	 * @since 1.0.0
	 * @return array All registered scripts.
	 */
	public function get_all_scripts(): array {
		return $this->scripts;
	}

	/**
	 * Get scripts that should be loaded.
	 *
	 * @since 1.0.0
	 * @return array Scripts to load.
	 */
	public function get_scripts_to_load(): array {
		$scripts_to_load = array();

		foreach ( $this->scripts as $handle => $script ) {
			if ( $this->should_load_script( $script ) ) {
				$scripts_to_load[ $handle ] = $script;
			}
		}

		return $scripts_to_load;
	}

	/**
	 * Check if script should be loaded.
	 *
	 * @since 1.0.0
	 * @param array $script Script configuration.
	 * @return bool True if should load.
	 */
	private function should_load_script( array $script ): bool {
		if ( empty( $script['conditions'] ) ) {
			return true;
		}

		foreach ( $script['conditions'] as $condition ) {
			// Handle array-based conditions
			if ( is_array( $condition ) ) {
				if ( isset( $condition['function'] ) ) {
					if ( function_exists( $condition['function'] ) ) {
						$result = call_user_func( $condition['function'] );
						if ( $result !== $condition['result'] ) {
							return false;
						}
					}
				} elseif ( isset( $condition['custom'] ) ) {
					$method = 'check_' . $condition['custom'];
					if ( method_exists( $this, $method ) ) {
						if ( ! $this->$method() ) {
							return false;
						}
					}
				}
			} else {
				// Handle simple key => value conditions (backward compatibility)
				if ( $condition === 'custom' ) {
					continue; // Skip, handled in array format
				}
			}
		}

		return true;
	}

	/**
	 * Check if page has countdown timer.
	 *
	 * @since 1.0.0
	 * @return bool True if has countdown timer.
	 */
	private function check_has_countdown_timer(): bool {
		global $post;

		if ( ! $post || ! $post->post_content ) {
			return false;
		}

		return has_shortcode( $post->post_content, 'scd_countdown_timer' ) ||
				has_block( 'scd/countdown-timer', $post );
	}
}
