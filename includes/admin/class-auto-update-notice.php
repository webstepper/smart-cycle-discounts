<?php
/**
 * Auto-Update Notice Class
 *
 * Displays a dismissible admin notice encouraging users to enable
 * automatic updates for the plugin.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress/plugins/smart-cycle-discounts/
 * @since      1.1.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Auto-Update Notice Class
 *
 * Shows a notice on plugin pages suggesting users enable auto-updates.
 * - Dismissible (stores in user meta)
 * - One-click enable button
 * - Only shows if auto-updates are not already enabled
 *
 * @since      1.1.5
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin
 */
class WSSCD_Auto_Update_Notice {

	/**
	 * User meta key for notice dismissed state.
	 *
	 * @since    1.1.5
	 * @var      string
	 */
	const DISMISSED_META = 'wsscd_auto_update_notice_dismissed';

	/**
	 * Plugin basename for auto-update checks.
	 *
	 * @since    1.1.5
	 * @var      string
	 */
	const PLUGIN_BASENAME = 'smart-cycle-discounts/smart-cycle-discounts.php';

	/**
	 * Whether notice should be shown (cached for current request).
	 *
	 * @since    1.1.5
	 * @access   private
	 * @var      bool|null
	 */
	private $should_show_notice = null;

	/**
	 * Initialize the auto-update notice.
	 *
	 * @since    1.1.5
	 * @return   void
	 */
	public function init() {
		// Register admin notice hook.
		add_action( 'admin_notices', array( $this, 'maybe_display_notice' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_wsscd_dismiss_auto_update_notice', array( $this, 'handle_dismiss' ) );
		add_action( 'wp_ajax_wsscd_enable_auto_updates', array( $this, 'handle_enable_auto_updates' ) );

		// Enqueue inline script for AJAX handling.
		add_action( 'admin_footer', array( $this, 'output_notice_script' ) );
	}

	/**
	 * Check if notice should be displayed and display it.
	 *
	 * @since    1.1.5
	 * @return   void
	 */
	public function maybe_display_notice() {
		// Only show on SCD admin pages or plugins page.
		if ( ! $this->is_relevant_admin_page() ) {
			return;
		}

		// Check if should show.
		$this->should_show_notice = $this->should_show();

		if ( ! $this->should_show_notice ) {
			return;
		}

		$this->render_notice();
	}

	/**
	 * Check if notice should be shown.
	 *
	 * @since    1.1.5
	 * @return   bool
	 */
	private function should_show() {
		// Check user capability.
		if ( ! current_user_can( 'update_plugins' ) ) {
			return false;
		}

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return false;
		}

		// Check if already dismissed.
		if ( get_user_meta( $user_id, self::DISMISSED_META, true ) ) {
			return false;
		}

		// Check if auto-updates already enabled for this plugin.
		if ( $this->is_auto_update_enabled() ) {
			return false;
		}

		// Check if WordPress auto-updates are disabled entirely.
		if ( ! $this->are_auto_updates_available() ) {
			return false;
		}

		return true;
	}

	/**
	 * Check if auto-updates are enabled for this plugin.
	 *
	 * @since    1.1.5
	 * @return   bool
	 */
	private function is_auto_update_enabled() {
		$auto_updates = get_option( 'auto_update_plugins', array() );

		if ( ! is_array( $auto_updates ) ) {
			return false;
		}

		return in_array( self::PLUGIN_BASENAME, $auto_updates, true );
	}

	/**
	 * Check if WordPress auto-updates are available.
	 *
	 * @since    1.1.5
	 * @return   bool
	 */
	private function are_auto_updates_available() {
		// Check if auto-updates are disabled via constant.
		if ( defined( 'AUTOMATIC_UPDATER_DISABLED' ) && AUTOMATIC_UPDATER_DISABLED ) {
			return false;
		}

		// Check if plugin auto-updates are disabled via filter.
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- This is a WordPress core filter, not a custom hook.
		if ( ! apply_filters( 'plugins_auto_update_enabled', true ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Check if current page is relevant for showing the notice.
	 *
	 * @since    1.1.5
	 * @return   bool
	 */
	private function is_relevant_admin_page() {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return false;
		}

		// Show on plugins page.
		if ( 'plugins' === $screen->id ) {
			return true;
		}

		// Show on SCD admin pages.
		if ( strpos( $screen->id, 'smart-cycle-discounts' ) !== false ) {
			return true;
		}

		if ( strpos( $screen->id, 'wsscd' ) !== false ) {
			return true;
		}

		return false;
	}

	/**
	 * Render the notice.
	 *
	 * @since    1.1.5
	 * @return   void
	 */
	private function render_notice() {
		$nonce       = wp_create_nonce( 'wsscd_auto_update_notice' );
		$plugins_url = admin_url( 'plugins.php' );
		?>
		<style>
			.wsscd-auto-update-notice {
				border-left-color: var(--wsscd-color-primary, var(--wp-admin-theme-color, #2271b1));
				padding: 12px 15px;
				background: #fff;
			}
			.wsscd-auto-update-notice-content {
				display: flex;
				align-items: flex-start;
				gap: 15px;
			}
			.wsscd-auto-update-notice-icon {
				font-size: 24px;
				line-height: 1;
				flex-shrink: 0;
			}
			.wsscd-auto-update-notice-text {
				flex: 1;
			}
			.wsscd-auto-update-notice-text p {
				margin: 0 0 10px 0;
				color: #1d2327;
			}
			.wsscd-auto-update-notice-text p:last-of-type {
				margin-bottom: 12px;
			}
			.wsscd-auto-update-notice-actions {
				display: flex;
				gap: 10px;
				flex-wrap: wrap;
				align-items: center;
			}
			.wsscd-auto-update-notice-actions .button {
				display: inline-flex;
				align-items: center;
				gap: 5px;
			}
		</style>
		<div class="notice notice-info wsscd-auto-update-notice is-dismissible" id="wsscd-auto-update-notice" data-nonce="<?php echo esc_attr( $nonce ); ?>">
			<div class="wsscd-auto-update-notice-content">
				<div class="wsscd-auto-update-notice-icon">🔄</div>
				<div class="wsscd-auto-update-notice-text">
					<p>
						<strong><?php esc_html_e( 'Keep Smart Cycle Discounts up to date automatically!', 'smart-cycle-discounts' ); ?></strong>
					</p>
					<p>
						<?php esc_html_e( 'Enable auto-updates to receive the latest features, security fixes, and improvements without lifting a finger.', 'smart-cycle-discounts' ); ?>
					</p>
					<div class="wsscd-auto-update-notice-actions">
						<button type="button" class="button button-primary" id="wsscd-enable-auto-updates">
							<span class="dashicons dashicons-update" style="margin-top: 3px;"></span>
							<?php esc_html_e( 'Enable Auto-Updates', 'smart-cycle-discounts' ); ?>
						</button>
						<a href="<?php echo esc_url( $plugins_url ); ?>" class="button button-secondary">
							<?php esc_html_e( 'Go to Plugins Page', 'smart-cycle-discounts' ); ?>
						</a>
						<button type="button" class="button button-link" id="wsscd-dismiss-auto-update-notice">
							<?php esc_html_e( "Don't show again", 'smart-cycle-discounts' ); ?>
						</button>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Output the notice script in admin footer.
	 *
	 * @since    1.1.5
	 * @return   void
	 */
	public function output_notice_script() {
		// Only output if notice is shown.
		if ( ! $this->should_show_notice ) {
			return;
		}
		?>
		<script>
		(function() {
			'use strict';

			var notice = document.getElementById( 'wsscd-auto-update-notice' );
			if ( ! notice ) {
				return;
			}

			var nonce = notice.dataset.nonce;
			var enableBtn = document.getElementById( 'wsscd-enable-auto-updates' );
			var dismissBtn = document.getElementById( 'wsscd-dismiss-auto-update-notice' );

			/**
			 * Hide the notice with fade animation.
			 */
			function hideNotice() {
				notice.style.transition = 'opacity 0.3s ease';
				notice.style.opacity = '0';
				setTimeout( function() {
					notice.style.display = 'none';
				}, 300 );
			}

			/**
			 * Send AJAX request.
			 */
			function sendAjax( action, callback ) {
				var xhr = new XMLHttpRequest();
				xhr.open( 'POST', ajaxurl, true );
				xhr.setRequestHeader( 'Content-Type', 'application/x-www-form-urlencoded' );
				xhr.onreadystatechange = function() {
					if ( xhr.readyState === 4 && callback ) {
						callback( xhr.status === 200, xhr.responseText );
					}
				};
				xhr.send( 'action=' + action + '&_wpnonce=' + nonce );
			}

			// Handle enable auto-updates button.
			if ( enableBtn ) {
				enableBtn.addEventListener( 'click', function() {
					enableBtn.disabled = true;
					enableBtn.textContent = '<?php echo esc_js( __( 'Enabling...', 'smart-cycle-discounts' ) ); ?>';

					sendAjax( 'wsscd_enable_auto_updates', function( success, response ) {
						if ( success ) {
							try {
								var data = JSON.parse( response );
								if ( data.success ) {
									enableBtn.textContent = '<?php echo esc_js( __( 'Enabled!', 'smart-cycle-discounts' ) ); ?>';
									setTimeout( hideNotice, 1000 );
								} else {
									enableBtn.textContent = '<?php echo esc_js( __( 'Failed - Try Plugins Page', 'smart-cycle-discounts' ) ); ?>';
									enableBtn.disabled = false;
								}
							} catch ( e ) {
								enableBtn.textContent = '<?php echo esc_js( __( 'Error', 'smart-cycle-discounts' ) ); ?>';
								enableBtn.disabled = false;
							}
						} else {
							enableBtn.textContent = '<?php echo esc_js( __( 'Error', 'smart-cycle-discounts' ) ); ?>';
							enableBtn.disabled = false;
						}
					} );
				} );
			}

			// Handle dismiss button.
			if ( dismissBtn ) {
				dismissBtn.addEventListener( 'click', function() {
					sendAjax( 'wsscd_dismiss_auto_update_notice' );
					hideNotice();
				} );
			}

			// Handle WordPress native dismiss button (X).
			notice.addEventListener( 'click', function( e ) {
				if ( e.target.classList.contains( 'notice-dismiss' ) ) {
					sendAjax( 'wsscd_dismiss_auto_update_notice' );
				}
			} );
		})();
		</script>
		<?php
	}

	/**
	 * Handle dismiss AJAX request.
	 *
	 * @since    1.1.5
	 * @return   void
	 */
	public function handle_dismiss() {
		check_ajax_referer( 'wsscd_auto_update_notice', '_wpnonce' );

		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'smart-cycle-discounts' ) ) );
		}

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid user.', 'smart-cycle-discounts' ) ) );
		}

		update_user_meta( $user_id, self::DISMISSED_META, time() );

		wp_send_json_success( array( 'message' => __( 'Notice dismissed.', 'smart-cycle-discounts' ) ) );
	}

	/**
	 * Handle enable auto-updates AJAX request.
	 *
	 * @since    1.1.5
	 * @return   void
	 */
	public function handle_enable_auto_updates() {
		check_ajax_referer( 'wsscd_auto_update_notice', '_wpnonce' );

		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'smart-cycle-discounts' ) ) );
		}

		// Get current auto-update plugins list.
		$auto_updates = get_option( 'auto_update_plugins', array() );

		if ( ! is_array( $auto_updates ) ) {
			$auto_updates = array();
		}

		// Add our plugin if not already in the list.
		if ( ! in_array( self::PLUGIN_BASENAME, $auto_updates, true ) ) {
			$auto_updates[] = self::PLUGIN_BASENAME;
			update_option( 'auto_update_plugins', $auto_updates );
		}

		// Also dismiss the notice.
		$user_id = get_current_user_id();
		if ( $user_id ) {
			update_user_meta( $user_id, self::DISMISSED_META, time() );
		}

		wp_send_json_success(
			array(
				'message' => __( 'Auto-updates enabled for Smart Cycle Discounts.', 'smart-cycle-discounts' ),
			)
		);
	}

	/**
	 * Static method to clean up on plugin uninstall.
	 *
	 * @since    1.1.5
	 * @return   void
	 */
	public static function on_uninstall() {
		global $wpdb;

		// Remove user meta for all users.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cleanup during uninstall.
		$wpdb->delete(
			$wpdb->usermeta,
			array( 'meta_key' => self::DISMISSED_META ),
			array( '%s' )
		);
	}
}
