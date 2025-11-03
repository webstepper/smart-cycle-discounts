<?php
/**
 * License Emergency Fix Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/pages/class-license-emergency-fix.php
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
 * Emergency License Fix Class
 *
 * @since 1.0.0
 */
class SCD_License_Emergency_Fix {

	/**
	 * Render the emergency fix page.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions', 'smart-cycle-discounts' ) );
		}

		// Handle form submission
		if ( isset( $_POST['scd_clear_license_cache'] ) && check_admin_referer( 'scd_clear_license_cache' ) ) {
			self::clear_license_cache();
		}

		$freemius_loaded        = function_exists( 'scd_fs' ) && is_object( scd_fs() );
		$freemius_is_premium    = false;
		$freemius_is_trial      = false;
		$freemius_is_registered = false;

		if ( $freemius_loaded ) {
			$freemius               = scd_fs();
			$freemius_is_premium    = $freemius->is_premium();
			$freemius_is_trial      = $freemius->is_trial();
			$freemius_is_registered = $freemius->is_registered();
		}

		// Get Feature Gate status
		$container               = Smart_Cycle_Discounts::get_instance();
		$feature_gate            = null;
		$feature_gate_is_premium = false;

		if ( $container ) {
			try {
				$feature_gate = Smart_Cycle_Discounts::get_service( 'feature_gate' );
				if ( $feature_gate ) {
					$feature_gate_is_premium = $feature_gate->is_premium();
				}
			} catch ( Exception $e ) {
				// Ignore
			}
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Emergency License Fix', 'smart-cycle-discounts' ); ?></h1>

			<div class="notice notice-info">
				<p>
					<strong><?php esc_html_e( 'This is a temporary diagnostic page.', 'smart-cycle-discounts' ); ?></strong><br>
					<?php esc_html_e( 'Use this page to manually clear the license cache and sync your PRO status.', 'smart-cycle-discounts' ); ?>
				</p>
			</div>

			<h2><?php esc_html_e( 'Current License Status', 'smart-cycle-discounts' ); ?></h2>

			<table class="form-table">
				<tr>
					<th><?php esc_html_e( 'Freemius Loaded:', 'smart-cycle-discounts' ); ?></th>
					<td>
						<?php if ( $freemius_loaded ) : ?>
							<span class="dashicons dashicons-yes" style="color: #46b450;"></span>
							<strong style="color: #46b450;"><?php esc_html_e( 'YES', 'smart-cycle-discounts' ); ?></strong>
						<?php else : ?>
							<span class="dashicons dashicons-no" style="color: #dc3232;"></span>
							<strong style="color: #dc3232;"><?php esc_html_e( 'NO', 'smart-cycle-discounts' ); ?></strong>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Freemius Registered:', 'smart-cycle-discounts' ); ?></th>
					<td>
						<?php if ( $freemius_is_registered ) : ?>
							<span class="dashicons dashicons-yes" style="color: #46b450;"></span>
							<strong style="color: #46b450;"><?php esc_html_e( 'YES', 'smart-cycle-discounts' ); ?></strong>
						<?php else : ?>
							<span class="dashicons dashicons-no" style="color: #dc3232;"></span>
							<strong style="color: #dc3232;"><?php esc_html_e( 'NO', 'smart-cycle-discounts' ); ?></strong>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Freemius is_premium():', 'smart-cycle-discounts' ); ?></th>
					<td>
						<?php if ( $freemius_is_premium ) : ?>
							<span class="dashicons dashicons-yes" style="color: #46b450;"></span>
							<strong style="color: #46b450;"><?php esc_html_e( 'TRUE', 'smart-cycle-discounts' ); ?></strong>
						<?php else : ?>
							<span class="dashicons dashicons-no" style="color: #dc3232;"></span>
							<strong style="color: #dc3232;"><?php esc_html_e( 'FALSE', 'smart-cycle-discounts' ); ?></strong>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Freemius is_trial():', 'smart-cycle-discounts' ); ?></th>
					<td>
						<?php if ( $freemius_is_trial ) : ?>
							<span class="dashicons dashicons-yes" style="color: #46b450;"></span>
							<strong style="color: #46b450;"><?php esc_html_e( 'TRUE', 'smart-cycle-discounts' ); ?></strong>
						<?php else : ?>
							<span class="dashicons dashicons-no" style="color: #999;"></span>
							<strong style="color: #999;"><?php esc_html_e( 'FALSE', 'smart-cycle-discounts' ); ?></strong>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Feature Gate is_premium():', 'smart-cycle-discounts' ); ?></th>
					<td>
						<?php if ( $feature_gate_is_premium ) : ?>
							<span class="dashicons dashicons-yes" style="color: #46b450;"></span>
							<strong style="color: #46b450;"><?php esc_html_e( 'TRUE', 'smart-cycle-discounts' ); ?></strong>
						<?php else : ?>
							<span class="dashicons dashicons-no" style="color: #dc3232;"></span>
							<strong style="color: #dc3232;"><?php esc_html_e( 'FALSE', 'smart-cycle-discounts' ); ?></strong>
						<?php endif; ?>
					</td>
				</tr>
			</table>

			<?php if ( $freemius_is_premium && ! $feature_gate_is_premium ) : ?>
				<div class="notice notice-warning">
					<p>
						<strong><?php esc_html_e( '⚠️ MISMATCH DETECTED!', 'smart-cycle-discounts' ); ?></strong><br>
						<?php esc_html_e( 'Freemius shows you have PRO, but Feature Gate shows FREE.', 'smart-cycle-discounts' ); ?><br>
						<?php esc_html_e( 'Click the button below to clear the cache and fix this.', 'smart-cycle-discounts' ); ?>
					</p>
				</div>
			<?php elseif ( $freemius_is_premium && $feature_gate_is_premium ) : ?>
				<div class="notice notice-success">
					<p>
						<strong><?php esc_html_e( '✅ License Status: PRO', 'smart-cycle-discounts' ); ?></strong><br>
						<?php esc_html_e( 'Both Freemius and Feature Gate show PRO status. Your PRO features should be accessible.', 'smart-cycle-discounts' ); ?>
					</p>
				</div>
			<?php else : ?>
				<div class="notice notice-info">
					<p>
						<strong><?php esc_html_e( 'License Status: FREE', 'smart-cycle-discounts' ); ?></strong><br>
						<?php esc_html_e( 'To access PRO features, please upgrade your license.', 'smart-cycle-discounts' ); ?>
					</p>
				</div>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Clear License Cache', 'smart-cycle-discounts' ); ?></h2>
			<p><?php esc_html_e( 'If you just upgraded to PRO, clearing the cache will force the plugin to re-check your license status.', 'smart-cycle-discounts' ); ?></p>

			<form method="post">
				<?php wp_nonce_field( 'scd_clear_license_cache' ); ?>
				<p>
					<button type="submit" name="scd_clear_license_cache" class="button button-primary button-large">
						<span class="dashicons dashicons-update"></span>
						<?php esc_html_e( 'Clear License Cache Now', 'smart-cycle-discounts' ); ?>
					</button>
				</p>
			</form>

		</div>
		<?php
	}

	/**
	 * Clear license cache.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private static function clear_license_cache() {
		global $wpdb;

		$results = array();

		// 1. Clear Feature Gate cache
		try {
			$container    = Smart_Cycle_Discounts::get_instance();
			$feature_gate = Smart_Cycle_Discounts::get_service( 'feature_gate' );

			if ( $feature_gate && method_exists( $feature_gate, 'clear_cache' ) ) {
				$feature_gate->clear_cache();
				$results[] = 'Feature Gate cache cleared';
			}
		} catch ( Exception $e ) {
			$results[] = 'Error clearing Feature Gate cache: ' . $e->getMessage();
		}

		// 2. Clear WordPress object cache
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
			$results[] = 'WordPress object cache flushed';
		}

		// 3. Clear Freemius transients
		$transients_cleared = $wpdb->query(
			"DELETE FROM {$wpdb->options}
			WHERE option_name LIKE '_transient_fs_%'
			OR option_name LIKE '_transient_timeout_fs_%'"
		);
		$results[]          = 'Freemius transients cleared: ' . $transients_cleared;

		// 4. Trigger Freemius license sync if available
		if ( function_exists( 'scd_fs' ) && is_object( scd_fs() ) ) {
			$freemius = scd_fs();

			// Force refresh account data
			if ( method_exists( $freemius, '_get_account_option' ) ) {
				delete_option( 'fs_accounts' );
				delete_option( 'fs_active_plugins' );
				$results[] = 'Freemius account options cleared';
			}
		}

		// Show success message
		add_settings_error(
			'scd_license_cache',
			'cache_cleared',
			'<strong>Cache Cleared Successfully!</strong><br>' . implode( '<br>', $results ) . '<br><br>Please refresh this page to see updated status.',
			'success'
		);

		// Force page reload after 2 seconds
		echo '<script>setTimeout(function(){ location.reload(); }, 2000);</script>';
	}
}
