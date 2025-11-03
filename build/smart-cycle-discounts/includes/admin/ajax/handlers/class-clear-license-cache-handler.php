<?php
/**
 * Clear License Cache Handler Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers/class-clear-license-cache-handler.php
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Clear License Cache Handler Class
 *
 * @since 1.0.0
 */
class SCD_Clear_License_Cache_Handler extends SCD_Abstract_Ajax_Handler {

	/**
	 * Container instance.
	 *
	 * @var object
	 */
	private $container;

	/**
	 * Logger instance.
	 *
	 * @var SCD_Logger
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @param object     $container Container instance.
	 * @param SCD_Logger $logger    Logger instance.
	 */
	public function __construct( $container, $logger ) {
		parent::__construct( $logger );
		$this->container = $container;
		$this->logger    = $logger;
	}

	/**
	 * Get AJAX action name.
	 *
	 * @return string Action name.
	 */
	protected function get_action_name() {
		return 'scd_clear_license_cache';
	}

	/**
	 * Handle the AJAX request.
	 *
	 * @param array $request Request data.
	 * @return array Response data.
	 */
	protected function handle( $request ) {
		// Only admins can clear license cache
		if ( ! current_user_can( 'manage_options' ) ) {
			return $this->error( __( 'Insufficient permissions', 'smart-cycle-discounts' ) );
		}

		$results = array();

		// 1. Clear Feature Gate cache
		try {
			$feature_gate = $this->container::get_service( 'feature_gate' );
			if ( $feature_gate && method_exists( $feature_gate, 'clear_cache' ) ) {
				$feature_gate->clear_cache();
				$results['feature_gate_cache_cleared'] = true;

				// Get fresh status after clearing
				$results['feature_gate_status_after_clear'] = array(
					'is_premium' => $feature_gate->is_premium(),
					'is_trial'   => $feature_gate->is_trial(),
				);
			} else {
				$results['feature_gate_cache_cleared'] = false;
				$results['feature_gate_error']         = 'Feature Gate service not available or clear_cache method missing';
			}
		} catch ( Exception $e ) {
			$results['feature_gate_error'] = $e->getMessage();
		}

		// 2. Clear WordPress object cache for Freemius
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
			$results['wp_cache_flushed'] = true;
		}

		// 3. Clear any Freemius-specific transients
		global $wpdb;
		$transients_cleared                     = $wpdb->query(
			"DELETE FROM {$wpdb->options}
			WHERE option_name LIKE '_transient_fs_%'
			OR option_name LIKE '_transient_timeout_fs_%'"
		);
		$results['freemius_transients_cleared'] = $transients_cleared;

		// 4. Get fresh Freemius status
		if ( function_exists( 'scd_fs' ) && is_object( scd_fs() ) ) {
			$freemius                               = scd_fs();
			$results['freemius_status_after_clear'] = array(
				'is_premium'    => $freemius->is_premium(),
				'is_trial'      => $freemius->is_trial(),
				'is_registered' => $freemius->is_registered(),
			);

			// Try to sync license with Freemius servers
			if ( method_exists( $freemius, 'get_site' ) ) {
				$site = $freemius->get_site();
				if ( $site && method_exists( $freemius, 'sync_license' ) ) {
					try {
						$freemius->sync_license();
						$results['license_synced'] = true;
					} catch ( Exception $e ) {
						$results['license_sync_error'] = $e->getMessage();
					}
				}
			}
		}

		// Log cache clear
		$this->logger->flow(
			'notice',
			'LICENSE CACHE CLEARED',
			'License cache manually cleared',
			array(
				'user_id' => get_current_user_id(),
				'results' => $results,
			)
		);

		return $this->success(
			array(
				'message'    => __( 'License cache cleared successfully', 'smart-cycle-discounts' ),
				'results'    => $results,
				'timestamp'  => current_time( 'mysql' ),
				'next_steps' => array(
					'1. Refresh the page to see if PRO features are now accessible',
					'2. If still not working, check the debug info to verify license status',
					'3. Contact Freemius support if license shows as inactive',
				),
			)
		);
	}
}
