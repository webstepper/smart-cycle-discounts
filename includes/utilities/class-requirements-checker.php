<?php
/**
 * Requirements Checker Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/utilities/class-requirements-checker.php
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


if ( defined( 'SCD_PLUGIN_DIR' ) ) {
	require_once SCD_PLUGIN_DIR . 'includes/utilities/traits/trait-admin-notice.php';
} else {
	require_once __DIR__ . '/traits/trait-admin-notice.php';
}

/**
 * Requirements Checker
 *
 * Validates system requirements for the plugin.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Requirements_Checker {

	use SCD_Admin_Notice_Trait;

	/**
	 * Check results.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $results    Check results.
	 */
	private array $results = array();

	/**
	 * Constructor.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( is_admin() ) {
			$this->init_admin_notices();
		}
	}

	/**
	 * Check all system requirements.
	 *
	 * @since    1.0.0
	 * @return   bool    True if all requirements are met.
	 */
	public function check_requirements(): bool {
		$checks = array(
			$this->check_php_version(),
			$this->check_wordpress_version(),
			$this->check_woocommerce(),
			$this->check_php_extensions(),
			$this->check_memory_limit(),
			$this->check_file_permissions(),
		);

		// Skip detailed compatibility checks during early loading
		// These will be checked later after WooCommerce initializes
		if ( did_action( 'woocommerce_init' ) ) {
			$this->check_woocommerce_compatibility();
		}

		return ! in_array( false, $checks, true );
	}

	/**
	 * Check PHP version requirement.
	 *
	 * @since    1.0.0
	 * @return   bool    True if PHP version is sufficient.
	 */
	public function check_php_version(): bool {
		$result = version_compare( PHP_VERSION, SCD_MIN_PHP_VERSION, '>=' );

		if ( ! $result && is_admin() ) {
			if ( ! class_exists( 'SCD_Translation_Handler' ) ) {
				require_once __DIR__ . '/class-translation-handler.php';
			}

			$messages = SCD_Translation_Handler::get_requirements_messages();
			$this->show_error_notice(
				sprintf(
					$messages['php_version'],
					SCD_MIN_PHP_VERSION,
					PHP_VERSION
				),
				false
			);
		}

		return $result;
	}

	/**
	 * Check WordPress version requirement.
	 *
	 * @since    1.0.0
	 * @return   bool    True if WordPress version is sufficient.
	 */
	public function check_wordpress_version(): bool {
		$result = version_compare( get_bloginfo( 'version' ), SCD_MIN_WP_VERSION, '>=' );

		if ( ! $result && is_admin() ) {
			if ( ! class_exists( 'SCD_Translation_Handler' ) ) {
				require_once __DIR__ . '/class-translation-handler.php';
			}

			$messages = SCD_Translation_Handler::get_requirements_messages();
			$this->show_error_notice(
				sprintf(
					$messages['wp_version'],
					SCD_MIN_WP_VERSION,
					get_bloginfo( 'version' )
				),
				false
			);
		}

		return $result;
	}

	/**
	 * Check WooCommerce requirement.
	 *
	 * @since    1.0.0
	 * @return   bool    True if WooCommerce is available and compatible.
	 */
	public function check_woocommerce(): bool {
		// Use class_exists with false to prevent autoloading
		if ( ! class_exists( 'WooCommerce', false ) ) {
			if ( is_admin() && did_action( 'init' ) ) {
				if ( ! class_exists( 'SCD_Translation_Handler' ) ) {
					require_once __DIR__ . '/class-translation-handler.php';
				}

				$messages = SCD_Translation_Handler::get_requirements_messages();
				$this->show_error_notice(
					$messages['woocommerce_missing'],
					false
				);
			}
			return false;
		}

		// Only check version if WC_VERSION is already defined
		if ( ! defined( 'WC_VERSION' ) ) {
			return true; // Assume it's okay for now, will check later
		}

		$result = version_compare( WC_VERSION, SCD_MIN_WC_VERSION, '>=' );

		if ( ! $result && is_admin() && did_action( 'init' ) ) {
			if ( ! class_exists( 'SCD_Translation_Handler' ) ) {
				require_once __DIR__ . '/class-translation-handler.php';
			}

			$messages = SCD_Translation_Handler::get_requirements_messages();
			$this->show_error_notice(
				sprintf(
					$messages['woocommerce_version'],
					SCD_MIN_WC_VERSION,
					WC_VERSION
				),
				false
			);
		}

		return $result;
	}

	/**
	 * Check WooCommerce compatibility.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function check_woocommerce_compatibility(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			$this->add_error( 'WooCommerce is required but not installed or activated.' );
			return;
		}

		// Check WC version if available
		if ( function_exists( 'WC' ) && WC() && isset( WC()->version ) ) {
			$wc_version = WC()->version;
			if ( version_compare( $wc_version, SCD_MIN_WC_VERSION, '<' ) ) {
				$this->add_error(
					sprintf(
						'WooCommerce version %s or higher is required. Current version: %s',
						SCD_MIN_WC_VERSION,
						$wc_version
					)
				);
			}
		}

		// Check HPOS compatibility
		$this->check_hpos_compatibility();

		$this->add_success( 'WooCommerce compatibility check passed.' );
	}

	/**
	 * Check HPOS (High-Performance Order Storage) compatibility.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function check_hpos_compatibility(): void {
		if ( ! class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil', false ) ) {
			$this->add_warning( 'WooCommerce HPOS feature detection not available. Plugin may not be fully compatible with newer WooCommerce versions.' );
			return;
		}

		if ( class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' ) ) {
			try {
				$controller = wc_get_container()->get(
					\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class
				);

				if ( $controller->custom_orders_table_usage_is_enabled() ) {
					$this->add_success( 'WooCommerce HPOS (High-Performance Order Storage) is enabled and supported.' );
				} else {
					$this->add_info( 'WooCommerce HPOS is available but not enabled. Plugin supports both legacy and HPOS order storage.' );
				}
			} catch ( Exception $e ) {
				$this->add_warning( 'Could not determine HPOS status: ' . $e->getMessage() );
			}
		} else {
			$this->add_info( 'WooCommerce HPOS not available in this version. Plugin supports legacy order storage.' );
		}

		// Verify HPOS compatibility declaration
		try {
			$is_compatible = \Automattic\WooCommerce\Utilities\FeaturesUtil::get_compatible_features_for_plugin(
				SCD_PLUGIN_BASENAME,
				'custom_order_tables'
			);

			if ( $is_compatible ) {
				$this->add_success( 'HPOS compatibility properly declared.' );
			} else {
				$this->add_warning( 'HPOS compatibility declaration may be missing.' );
			}
		} catch ( Exception $e ) {
			$this->add_warning( 'Could not verify HPOS compatibility declaration: ' . $e->getMessage() );
		}
	}

	/**
	 * Check required PHP extensions.
	 *
	 * @since    1.0.0
	 * @return   bool    True if all required extensions are loaded.
	 */
	public function check_php_extensions(): bool {
		$required_extensions = array( 'json', 'mbstring', 'openssl' );

		foreach ( $required_extensions as $extension ) {
			if ( ! extension_loaded( $extension ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Check memory limit.
	 *
	 * @since    1.0.0
	 * @return   bool    True if memory limit is sufficient.
	 */
	public function check_memory_limit(): bool {
		$memory_limit    = wp_convert_hr_to_bytes( ini_get( 'memory_limit' ) );
		$required_memory = wp_convert_hr_to_bytes( '256M' );

		return $memory_limit >= $required_memory;
	}

	/**
	 * Check file permissions.
	 *
	 * @since    1.0.0
	 * @return   bool    True if file permissions are correct.
	 */
	public function check_file_permissions(): bool {
		$upload_dir = wp_upload_dir();
		return wp_is_writable( $upload_dir['basedir'] );
	}

	/**
	 * Add success result.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $message    Success message.
	 * @return   void
	 */
	private function add_success( string $message ): void {
		$this->results[] = array(
			'type'    => 'success',
			'message' => $message,
		);
	}

	/**
	 * Add error result.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $message    Error message.
	 * @return   void
	 */
	private function add_error( string $message ): void {
		$this->results[] = array(
			'type'    => 'error',
			'message' => $message,
		);
	}

	/**
	 * Add warning result.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $message    Warning message.
	 * @return   void
	 */
	private function add_warning( string $message ): void {
		$this->results[] = array(
			'type'    => 'warning',
			'message' => $message,
		);
	}

	/**
	 * Add info result.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $message    Info message.
	 * @return   void
	 */
	private function add_info( string $message ): void {
		$this->results[] = array(
			'type'    => 'info',
			'message' => $message,
		);
	}

	/**
	 * Get check results.
	 *
	 * @since    1.0.0
	 * @return   array    Check results.
	 */
	public function get_results(): array {
		return $this->results;
	}

	/**
	 * Get detailed requirements report.
	 *
	 * @since    1.0.0
	 * @return   array    Detailed requirements report.
	 */
	public function get_requirements_report(): array {
		return array(
			'php_version'        => array(
				'required' => SCD_MIN_PHP_VERSION,
				'current'  => PHP_VERSION,
				'status'   => $this->check_php_version(),
			),
			'wordpress_version'  => array(
				'required' => SCD_MIN_WP_VERSION,
				'current'  => get_bloginfo( 'version' ),
				'status'   => $this->check_wordpress_version(),
			),
			'woocommerce'        => array(
				'required' => SCD_MIN_WC_VERSION,
				'current'  => defined( 'WC_VERSION' ) ? WC_VERSION : 'Not installed',
				'status'   => $this->check_woocommerce(),
			),
			'php_extensions'     => array(
				'required' => array( 'json', 'mbstring', 'openssl' ),
				'status'   => $this->check_php_extensions(),
			),
			'memory_limit'       => array(
				'required' => '256M',
				'current'  => ini_get( 'memory_limit' ),
				'status'   => $this->check_memory_limit(),
			),
			'file_permissions'   => array(
				'status' => $this->check_file_permissions(),
			),
			'hpos_compatibility' => array(
				'status' => $this->is_hpos_compatible(),
			),
		);
	}

	/**
	 * Check if HPOS is compatible.
	 *
	 * @since    1.0.0
	 * @return   bool    True if HPOS is compatible.
	 */
	public function is_hpos_compatible(): bool {
		if ( ! class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil', false ) ) {
			return false;
		}

		try {
			return \Automattic\WooCommerce\Utilities\FeaturesUtil::get_compatible_features_for_plugin(
				SCD_PLUGIN_BASENAME,
				'custom_order_tables'
			);
		} catch ( Exception $e ) {
			return false;
		}
	}
}
