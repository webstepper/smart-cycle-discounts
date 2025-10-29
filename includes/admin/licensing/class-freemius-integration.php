<?php
/**
 * Freemius Integration
 *
 * Handles Freemius SDK initialization and configuration.
 * Provides licensing, upgrades, and account management.
 *
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/licensing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Freemius Integration Class
 *
 * Initializes and configures Freemius SDK for licensing.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/licensing
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Freemius_Integration {

	/**
	 * Freemius instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      Freemius    $freemius    Freemius SDK instance.
	 */
	private static $freemius = null;

	/**
	 * Initialize Freemius SDK.
	 *
	 * @since    1.0.0
	 * @return   Freemius|null    Freemius SDK instance or null if not available.
	 */
	public static function init() {
		global $scd_fs;

		if ( isset( $scd_fs ) && is_object( $scd_fs ) ) {
			return $scd_fs;
		}

		if ( ! file_exists( SCD_PLUGIN_DIR . 'includes/freemius/wordpress-sdk-master/start.php' ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'freemius_missing_notice' ) );
			return null;
		}

		// Disable Freemius debug/dev mode BEFORE loading SDK
		if ( ! defined( 'WP_FS__DEV_MODE' ) ) {
			define( 'WP_FS__DEV_MODE', false );
		}
		if ( ! defined( 'WP_FS__DEBUG_SDK' ) ) {
			define( 'WP_FS__DEBUG_SDK', false );
		}
		if ( ! defined( 'WP_FS__ECHO_DEBUG_SDK' ) ) {
			define( 'WP_FS__ECHO_DEBUG_SDK', false );
		}

		require_once SCD_PLUGIN_DIR . 'includes/freemius/wordpress-sdk-master/start.php';

		$scd_fs = fs_dynamic_init(
			array(
				'id'             => '21492',
				'slug'           => 'smart-cycle-discounts',
				'type'           => 'plugin',
				'public_key'     => 'pk_4adf9836495f54c692369525c1000',
				'is_premium'     => false,
				'has_addons'     => false,
				'has_paid_plans' => false,
				'menu'           => array(
					'slug'       => 'smart-cycle-discounts',
					'first-path' => 'admin.php?page=smart-cycle-discounts',
					'support'    => false,
				),
			)
		);

		self::$freemius = $scd_fs;
		self::setup_hooks();

		return $scd_fs;
	}

	/**
	 * Setup Freemius hooks and filters.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private static function setup_hooks() {
		// Disable Freemius debug mode
		add_filter( 'fs_is_dev_mode', '__return_false' );
		add_filter( 'fs_is_debug_mode', '__return_false' );

		add_filter( 'fs_show_trial_as_pricing_option', '__return_false' );

		// Account and license change hooks
		self::$freemius->add_action( 'after_account_connection', array( __CLASS__, 'after_activation' ) );
		self::$freemius->add_action( 'after_premium_subscription_change', array( __CLASS__, 'after_plan_change' ) );
		self::$freemius->add_action( 'after_trial_started', array( __CLASS__, 'after_trial_started' ) );
		self::$freemius->add_action( 'after_trial_cancelled', array( __CLASS__, 'after_trial_cancelled' ) );
		self::$freemius->add_action( 'after_account_plan_change', array( __CLASS__, 'after_plan_change' ) );

		// License sync hooks for security
		self::$freemius->add_filter( 'after_account_connection', array( __CLASS__, 'sync_license_on_connect' ) );
		self::$freemius->add_filter( 'license_key_maxed', array( __CLASS__, 'handle_license_maxed' ), 10, 2 );

		// Admin notice filters
		add_filter( 'fs_show_admin_notice', array( __CLASS__, 'filter_admin_notices' ), 10, 2 );
		add_action( 'admin_notices', array( __CLASS__, 'upgrade_notices' ) );

		// Customize pricing table display
		self::$freemius->add_filter( 'templates/pricing.php', array( __CLASS__, 'remove_inherited_features_text' ) );
		add_action( 'admin_head', array( __CLASS__, 'hide_inherited_features_css' ) );
	}

	/**
	 * Handle actions after Freemius activation.
	 *
	 * @since    1.0.0
	 * @param    WP_User $user    WordPress user object.
	 * @return   void
	 */
	public static function after_activation( $user ) {
		if ( function_exists( 'scd_log_info' ) ) {
			scd_log_info(
				'Freemius account connected',
				array(
					'user_id'    => $user->ID,
					'user_email' => $user->user_email,
				)
			);
		}

		self::clear_feature_gate_cache();
	}

	/**
	 * Handle actions after premium plan change.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public static function after_plan_change() {
		self::clear_feature_gate_cache();

		if ( function_exists( 'scd_log_info' ) ) {
			scd_log_info(
				'Freemius plan changed',
				array(
					'user_id' => get_current_user_id(),
				)
			);
		}

		// Force immediate validation after plan change
		if ( class_exists( 'SCD_License_Manager' ) ) {
			$license_manager = SCD_License_Manager::instance();
			if ( method_exists( $license_manager, 'force_validation' ) ) {
				$license_manager->force_validation();
			}
		}
	}

	/**
	 * Handle actions after trial started.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public static function after_trial_started() {
		self::clear_feature_gate_cache();

		if ( function_exists( 'scd_log_info' ) ) {
			scd_log_info(
				'Freemius trial started',
				array(
					'user_id' => get_current_user_id(),
				)
			);
		}

		// Force immediate validation
		if ( class_exists( 'SCD_License_Manager' ) ) {
			$license_manager = SCD_License_Manager::instance();
			if ( method_exists( $license_manager, 'force_validation' ) ) {
				$license_manager->force_validation();
			}
		}

		add_action(
			'admin_notices',
			function () {
				?>
			<div class="notice notice-success is-dismissible">
				<p>
					<strong><?php esc_html_e( 'Smart Cycle Discounts Pro Trial Activated!', 'smart-cycle-discounts' ); ?></strong><br>
					<?php esc_html_e( 'You now have access to all Pro features for 14 days. Explore advanced analytics, unlimited campaigns, and more!', 'smart-cycle-discounts' ); ?>
				</p>
			</div>
				<?php
			}
		);
	}

	/**
	 * Handle actions after trial cancelled.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public static function after_trial_cancelled() {
		self::clear_feature_gate_cache();

		if ( function_exists( 'scd_log_info' ) ) {
			scd_log_info(
				'Freemius trial cancelled',
				array(
					'user_id' => get_current_user_id(),
				)
			);
		}

		// Force immediate validation
		if ( class_exists( 'SCD_License_Manager' ) ) {
			$license_manager = SCD_License_Manager::instance();
			if ( method_exists( $license_manager, 'force_validation' ) ) {
				$license_manager->force_validation();
			}
		}
	}

	/**
	 * Clear Feature Gate and License Manager caches.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private static function clear_feature_gate_cache() {
		// Clear Feature Gate cache
		if ( class_exists( 'SCD_Feature_Gate' ) && function_exists( 'scd_get_instance' ) ) {
			try {
				$container = scd_get_instance()->get_container();
				if ( $container && $container->has( 'feature_gate' ) ) {
					$feature_gate = $container->get( 'feature_gate' );
					if ( method_exists( $feature_gate, 'clear_cache' ) ) {
						$feature_gate->clear_cache();
					}
				}
			} catch ( Exception $e ) {
				// Silently fail - cache clearing is not critical
			}
		}

		// Clear License Manager cache
		if ( class_exists( 'SCD_License_Manager' ) ) {
			$license_manager = SCD_License_Manager::instance();
			if ( method_exists( $license_manager, 'clear_validation_cache' ) ) {
				$license_manager->clear_validation_cache();
			}
		}
	}

	/**
	 * Sync license information on account connection.
	 *
	 * @since    1.0.0
	 * @param    WP_User $user    WordPress user object.
	 * @return   WP_User             Unchanged user object.
	 */
	public static function sync_license_on_connect( $user ) {
		// Trigger immediate license validation
		if ( class_exists( 'SCD_License_Manager' ) ) {
			$license_manager = SCD_License_Manager::instance();
			if ( method_exists( $license_manager, 'force_validation' ) ) {
				$license_manager->force_validation();
			}
		}

		return $user;
	}

	/**
	 * Handle license activation limit reached.
	 *
	 * @since    1.0.0
	 * @param    bool   $is_maxed    Whether license reached activation limit.
	 * @param    object $license     License object.
	 * @return   bool                 Unchanged value.
	 */
	public static function handle_license_maxed( $is_maxed, $license ) {
		if ( $is_maxed && function_exists( 'scd_log_warning' ) ) {
			scd_log_warning(
				'License activation limit reached',
				array(
					'license_id' => isset( $license->id ) ? $license->id : 'unknown',
				)
			);
		}

		return $is_maxed;
	}

	/**
	 * Filter Freemius admin notices.
	 *
	 * @since    1.0.0
	 * @param    bool   $show    Whether to show notice.
	 * @param    string $type    Notice type.
	 * @return   bool               Filtered value.
	 */
	public static function filter_admin_notices( $show, $type ) {
		$hidden_notices = array(
			'trial_promotion',
			'promotion',
		);

		if ( in_array( $type, $hidden_notices, true ) ) {
			return false;
		}

		return $show;
	}

	/**
	 * Show custom upgrade notices for free users.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public static function upgrade_notices() {
		$screen = get_current_screen();
		if ( ! $screen || false === strpos( $screen->id, 'smart-cycle-discounts' ) ) {
			return;
		}

		if ( self::is_premium() ) {
			return;
		}

		$page = isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : '';

		if ( 'smart-cycle-discounts-account' === $page || 'smart-cycle-discounts-pricing' === $page ) {
			return;
		}

		$notice_shown = get_transient( 'scd_upgrade_notice_shown_' . get_current_user_id() );

		if ( ! $notice_shown ) {
			?>
			<div class="notice notice-info is-dismissible scd-upgrade-notice">
				<p>
					<strong><?php esc_html_e( 'Upgrade to Smart Cycle Discounts Pro', 'smart-cycle-discounts' ); ?></strong><br>
					<?php esc_html_e( 'Unlock advanced analytics, unlimited campaigns, and priority support.', 'smart-cycle-discounts' ); ?>
					<a href="<?php echo esc_url( self::$freemius->get_upgrade_url() ); ?>" class="button button-primary" style="margin-left: 10px;">
						<?php esc_html_e( 'Upgrade Now', 'smart-cycle-discounts' ); ?>
					</a>
					<a href="<?php echo esc_url( self::$freemius->get_trial_url() ); ?>" class="button button-secondary">
						<?php esc_html_e( 'Start Free Trial', 'smart-cycle-discounts' ); ?>
					</a>
				</p>
			</div>
			<?php
			set_transient( 'scd_upgrade_notice_shown_' . get_current_user_id(), true, DAY_IN_SECONDS );
		}
	}

	/**
	 * Show Freemius missing notice.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public static function freemius_missing_notice() {
		?>
		<div class="notice notice-warning">
			<p>
				<strong><?php esc_html_e( 'Smart Cycle Discounts', 'smart-cycle-discounts' ); ?>:</strong>
				<?php esc_html_e( 'Freemius SDK is not installed. Please download and install it to enable Pro features and licensing.', 'smart-cycle-discounts' ); ?>
				<a href="https://github.com/Freemius/wordpress-sdk/archive/refs/heads/master.zip" target="_blank">
					<?php esc_html_e( 'Download Freemius SDK', 'smart-cycle-discounts' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Check if user has premium access.
	 *
	 * Handles localhost development licenses which Freemius marks as "free"
	 * even when linked to paid plans. This override ensures proper premium
	 * detection in all environments.
	 *
	 * @since    1.0.0
	 * @return   bool    True if premium or trial.
	 */
	public static function is_premium() {
		if ( ! self::$freemius || ! is_object( self::$freemius ) ) {
			return false;
		}

		// Check native Freemius premium status
		if ( self::$freemius->is_premium() || self::$freemius->is_trial() ) {
			return true;
		}

		// Handle localhost licenses with paid plans
		// Freemius provides free localhost licenses for development that aren't
		// flagged as "premium" even when linked to a paid plan. We check manually.
		if ( self::$freemius->is_registered() ) {
			$license = self::$freemius->_get_license();

			if ( $license && $license->is_active() ) {
				if ( isset( $license->is_free_localhost ) && $license->is_free_localhost ) {
					// Verify paid plan assignment via plan_id and pricing_id
					if ( isset( $license->plan_id ) && ! empty( $license->plan_id ) &&
						isset( $license->pricing_id ) && ! empty( $license->pricing_id ) ) {
						return true;
					}
				}
			}
		}

		return false;
	}

	/**
	 * Check if user is on trial.
	 *
	 * @since    1.0.0
	 * @return   bool    True if on trial.
	 */
	public static function is_trial() {
		if ( ! self::$freemius || ! is_object( self::$freemius ) ) {
			return false;
		}

		return self::$freemius->is_trial();
	}

	/**
	 * Get Freemius instance.
	 *
	 * @since    1.0.0
	 * @return   Freemius|null    Freemius instance or null.
	 */
	public static function get_instance() {
		return self::$freemius;
	}

	/**
	 * Remove "All X Features" text from Freemius pricing table.
	 *
	 * When features are inherited from lower tiers, Freemius shows
	 * "All STARTER Features" or "All PROFESSIONAL Features" text.
	 * This filter removes that text to display the full feature list
	 * on all plans without the inheritance message.
	 *
	 * @since    1.0.0
	 * @param    string $html    Pricing table HTML.
	 * @return   string          Modified HTML without inheritance text.
	 */
	public static function remove_inherited_features_text( $html ) {
		if ( empty( $html ) ) {
			return $html;
		}

		// Remove "All [PLAN NAME] Features" text patterns
		// Matches: "All STARTER Features", "All PROFESSIONAL Features", "All BUSINESS Features", etc.
		$html = preg_replace(
			'/<[^>]*class="[^"]*fs-inherited-features[^"]*"[^>]*>.*?All\s+[A-Z]+\s+Features.*?<\/[^>]+>/is',
			'',
			$html
		);

		// Fallback: Remove any remaining "All X Features" text regardless of wrapper
		$html = preg_replace(
			'/All\s+[A-Z]+\s+Features/i',
			'',
			$html
		);

		return $html;
	}

	/**
	 * Hide "All X Features" text with CSS (backup method).
	 *
	 * This CSS approach ensures the text is hidden even if the HTML
	 * filter doesn't catch it. Works as a reliable backup.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public static function hide_inherited_features_css() {
		// Only load on Freemius pages
		if ( ! function_exists( 'scd_fs' ) || ! scd_fs()->is_page_visible() ) {
			return;
		}

		?>
		<script type="text/javascript">
			jQuery(document).ready(function($) {
				// Function to remove "All X Features" text from pricing table
				function removeInheritedText() {
					// Target the exact structure: ul.fs-plan-features > li > span.fs-feature-title > strong
					$('ul.fs-plan-features > li').each(function() {
						var $li = $(this);
						var $featureTitle = $li.find('span.fs-feature-title strong');

						if ($featureTitle.length > 0) {
							var text = $featureTitle.text().trim();
							// Check if it matches "All STARTER Features", "All PROFESSIONAL Features", etc.
							if (/^All\s+(STARTER|PROFESSIONAL|BUSINESS|PRO)\s+Features$/i.test(text)) {
								// Remove the entire <li> element
								$li.remove();
							}
						}
					});
				}

				// Run immediately
				removeInheritedText();

				// Run again after delays (in case Freemius JS loads content)
				setTimeout(removeInheritedText, 100);
				setTimeout(removeInheritedText, 500);
				setTimeout(removeInheritedText, 1000);

				// Watch for DOM changes (when pricing table loads dynamically)
				if (window.MutationObserver) {
					var observer = new MutationObserver(function(mutations) {
						removeInheritedText();
					});

					// Observe the body for any changes
					observer.observe(document.body, {
						childList: true,
						subtree: true
					});
				}
			});
		</script>
		<?php
	}
}
