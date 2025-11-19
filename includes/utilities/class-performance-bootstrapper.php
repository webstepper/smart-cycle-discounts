<?php
/**
 * Performance Bootstrapper Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/utilities/class-performance-bootstrapper.php
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
 * Performance Bootstrapper class.
 *
 * Initializes performance optimization components.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/bootstrap
 * @author     Webstepper <contact@webstepper.io>
 */
class SCD_Performance_Bootstrapper {

	/**
	 * The loader instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Loader    $loader    The loader instance.
	 */
	private SCD_Loader $loader;

	/**
	 * The container instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      object    $container    The container instance.
	 */
	private object $container;

	/**
	 * Initialize the bootstrapper.
	 *
	 * @since    1.0.0
	 * @param    SCD_Loader $loader       The loader instance.
	 * @param    object     $container    The container instance.
	 */
	public function __construct( SCD_Loader $loader, object $container ) {
		$this->loader    = $loader;
		$this->container = $container;
	}

	/**
	 * Initialize performance components.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function init(): void {
		$this->load_dependencies();
		$this->register_services();
		$this->define_hooks();
	}

	/**
	 * Load performance dependencies.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function load_dependencies(): void {
		// Performance Optimizer
		require_once SCD_INCLUDES_DIR . 'utilities/class-performance-optimizer.php';

		// Performance Monitor
		require_once SCD_INCLUDES_DIR . 'utilities/class-performance-monitor.php';

		// Reference Data Cache
		require_once SCD_INCLUDES_DIR . 'cache/class-reference-data-cache.php';

		// Performance classes are loaded via autoloader
	}

	/**
	 * Register performance services.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function register_services(): void {
		// Register Reference Data Cache
		if ( ! $this->container->has( 'reference_data_cache' ) ) {
			$this->container->singleton(
				'reference_data_cache',
				function () {
					return new SCD_Reference_Data_Cache();
				}
			);
		}

		// WooCommerce integration now includes performance optimizations directly
	}

	/**
	 * Define performance hooks.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function define_hooks(): void {
		// Initialize Performance Optimizer early
		$this->loader->add_action( 'plugins_loaded', $this, 'init_performance_optimizer', 5 );

		if ( get_option( 'scd_debug', false ) ) {
			$this->loader->add_action( 'init', $this, 'start_performance_monitoring', 1 );
			$this->loader->add_action( 'shutdown', $this, 'log_performance_report', 999 );
			$this->loader->add_action( 'send_headers', $this, 'add_performance_headers' );
		}

		// Preload reference data
		$this->loader->add_action( 'init', $this, 'preload_reference_data', 20 );

		// AJAX handlers now handled by unified router

		// Validation performance scripts are handled by the Asset Management System
		// Scripts are conditionally loaded based on context through SCD_Asset_Loader
	}

	/**
	 * Initialize performance optimizer.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function init_performance_optimizer(): void {
		if ( class_exists( 'SCD_Performance_Optimizer' ) ) {
			add_action(
				'woocommerce_before_calculate_totals',
				function () {
					SCD_Performance_Optimizer::$is_calculating = true;
				},
				1
			);

			add_action(
				'woocommerce_after_calculate_totals',
				function () {
					SCD_Performance_Optimizer::$is_calculating = false;
				},
				999
			);
		}
	}

	/**
	 * Start performance monitoring.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function start_performance_monitoring(): void {
		if ( class_exists( 'SCD_Performance_Monitor' ) ) {
			SCD_Performance_Monitor::start_timer( 'page_load' );
			SCD_Performance_Monitor::track_memory( 'init' );
		}
	}

	/**
	 * Log performance report.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function log_performance_report(): void {
		if ( class_exists( 'SCD_Performance_Monitor' ) ) {
			SCD_Performance_Monitor::stop_timer( 'page_load' );
			SCD_Performance_Monitor::track_memory( 'shutdown' );
			SCD_Performance_Monitor::log_report( 'page_load' );
		}
	}

	/**
	 * Add performance headers.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function add_performance_headers(): void {
		if ( class_exists( 'SCD_Performance_Monitor' ) && ! headers_sent() ) {
			SCD_Performance_Monitor::add_performance_headers();
		}
	}

	/**
	 * Preload reference data.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function preload_reference_data(): void {
		if ( $this->container->has( 'reference_data_cache' ) ) {
			$cache = $this->container->get( 'reference_data_cache' );

			// Only preload on admin pages and checkout
			if ( is_admin() || is_checkout() ) {
				$cache->preload_common_data();
			}
		}
	}

	/**
	 * Handle validation rules batch request.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function handle_validation_rules_batch(): void {
		// Load AJAX security handler
		require_once SCD_PLUGIN_DIR . 'includes/admin/ajax/class-ajax-security.php';
		require_once SCD_PLUGIN_DIR . 'includes/admin/ajax/class-scd-ajax-response.php';

		// Verify AJAX request security
		$result = SCD_Ajax_Security::verify_ajax_request( 'scd_get_validation_rules_batch', $_POST );

		if ( is_wp_error( $result ) ) {
			SCD_AJAX_Response::wp_error( $result );
			return;
		}

		if ( $this->container->has( 'validation_rules_batch_handler' ) ) {
			$handler  = $this->container->get( 'validation_rules_batch_handler' );
			$response = $handler->handle( $_POST );

			if ( $response['success'] ) {
				SCD_AJAX_Response::success( $response['data'] );
			} else {
				SCD_AJAX_Response::error(
					'batch_processing_failed',
					isset( $response['data']['message'] ) ? $response['data']['message'] : __( 'Batch processing failed', 'smart-cycle-discounts' ),
					$response['data']
				);
			}
		} else {
			SCD_AJAX_Response::error(
				'handler_not_available',
				__( 'Batch handler not available', 'smart-cycle-discounts' )
			);
		}
	}


	/**
	 * Get asset version for cache busting.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $file    File path relative to plugin root.
	 * @return   string             Version string.
	 */
	private function get_asset_version( string $file ): string {
		$file_path = SCD_PLUGIN_DIR . $file;

		if ( file_exists( $file_path ) ) {
			return filemtime( $file_path ) ?: SCD_VERSION;
		}

		return SCD_VERSION;
	}
}
