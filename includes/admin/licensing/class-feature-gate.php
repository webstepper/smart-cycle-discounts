<?php
/**
 * Feature Gate Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/licensing/class-feature-gate.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress/plugins/smart-cycle-discounts/
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Feature Gate Service Class
 *
 * Provides centralized feature access control based on subscription level.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/licensing
 * @author     Webstepper <contact@webstepper.io>
 */
class WSSCD_Feature_Gate {

	/**
	 * Feature definitions (Free vs Pro).
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $features    Feature access definitions.
	 */
	private $features = array(
		// Campaign features (USED)
		'campaigns_advanced_product_filters' => 'pro',
		'campaigns_recurring'                => 'free',

		// Cycle AI features (PRO-only)
		'cycle_ai_campaign_suggestions'      => 'pro',

		// Discount configurations (USED - usage limits, application rules, combination policy)
		'discount_configurations'            => 'pro',

		// Discount types (USED via can_use_discount_type)
		'discount_type_tiered'               => 'pro',
		'discount_type_bogo'                 => 'pro',
		'discount_type_spend_threshold'      => 'pro',

		// Subscription controls (PRO-only)
		'subscription_controls'              => 'pro',

		// Email notification types (USED via can_send_notification)
		// FREE = reactive (after event), PRO = proactive (before event / insights)
		'notification_campaign_started'      => 'free',
		'notification_campaign_ending'       => 'pro',
		'notification_campaign_ended'        => 'free',
		'notification_daily_report'          => 'pro',
		'notification_weekly_report'         => 'pro',
		'notification_performance_alert'     => 'pro',
		'notification_low_stock_alert'       => 'pro',
		'notification_milestone_alert'       => 'pro',

		// Export features (USED via can_export_data)
		'export_csv'                         => 'pro',
	);

	/**
	 * Cache for premium status.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      bool|null    $is_premium_cached    Cached premium status.
	 */
	private $is_premium_cached = null;

	/**
	 * Check if Freemius is loaded and available.
	 *
	 * @since    1.0.0
	 * @return   bool    True if Freemius is available.
	 */
	private function is_freemius_loaded() {
		return function_exists( 'wsscd_fs' ) && is_object( wsscd_fs() );
	}

	/**
	 * Check if user has premium access.
	 *
	 * Uses server-validated license check via License Manager for security.
	 * Falls back to Freemius direct check if License Manager unavailable.
	 *
	 * @since    1.0.0
	 * @return   bool    True if user has premium or trial access.
	 */
	public function is_premium() {
		if ( null !== $this->is_premium_cached ) {
			return $this->is_premium_cached;
		}

		// Use License Manager for server-validated check (Phase 2)
		if ( function_exists( 'wsscd_is_license_valid' ) ) {
			$this->is_premium_cached = wsscd_is_license_valid();
			return $this->is_premium_cached;
		}

		// Fallback: check Freemius directly if License Manager unavailable
		if ( ! $this->is_freemius_loaded() ) {
			$this->is_premium_cached = false;
			return false;
		}

		// Last resort: direct Freemius check
		$this->is_premium_cached = wsscd_fs()->is_premium() || wsscd_fs()->is_trial();

		return $this->is_premium_cached;
	}

	/**
	 * Check if user is on trial.
	 *
	 * @since    1.0.0
	 * @return   bool    True if user is on trial.
	 */
	public function is_trial() {
		if ( ! $this->is_freemius_loaded() ) {
			return false;
		}

		return wsscd_fs()->is_trial();
	}

	/**
	 * Check if a feature is available.
	 *
	 * @since    1.0.0
	 * @param    string $feature_key    Feature identifier.
	 * @return   bool                      True if feature is available.
	 */
	public function can_use_feature( $feature_key ) {
		// Unknown features are considered free
		if ( ! isset( $this->features[ $feature_key ] ) ) {
			return true;
		}

		$required_level = $this->features[ $feature_key ];

		// Free features are always available
		if ( 'free' === $required_level ) {
			return true;
		}

		// Pro features require premium or trial access
		if ( 'pro' === $required_level ) {
			return $this->is_premium();
		}

		// Default to not allowed
		return false;
	}

	/**
	 * Get the required level for a feature.
	 *
	 * @since    1.0.0
	 * @param    string $feature_key    Feature identifier.
	 * @return   string                    Required level ('free' or 'pro').
	 */
	public function get_feature_level( $feature_key ) {
		return isset( $this->features[ $feature_key ] ) ? $this->features[ $feature_key ] : 'free';
	}

	/**
	 * Check if user can export data.
	 *
	 * @since    1.0.0
	 * @return   bool    True if user can export data.
	 */
	public function can_export_data() {
		return $this->can_use_feature( 'export_csv' );
	}

	/**
	 * Check if user can use advanced product filters.
	 *
	 * @since    1.0.0
	 * @return   bool    True if user can use advanced product filters.
	 */
	public function can_use_advanced_product_filters() {
		return $this->can_use_feature( 'campaigns_advanced_product_filters' );
	}

	/**
	 * Check if user can use recurring campaigns.
	 *
	 * @since    1.0.0
	 * @return   bool    True if user can use recurring campaigns.
	 */
	public function can_use_recurring_campaigns() {
		return $this->can_use_feature( 'campaigns_recurring' );
	}

	/**
	 * Check if user can use discount configurations.
	 *
	 * Discount configurations include: usage limits, application rules,
	 * and combination policy settings.
	 *
	 * @since    1.0.0
	 * @return   bool    True if user can use discount configurations.
	 */
	public function can_use_discount_configurations() {
		return $this->can_use_feature( 'discount_configurations' );
	}

	/**
	 * Check if user can use subscription discount controls.
	 *
	 * Subscription controls include: discount target selection
	 * (recurring/sign-up fee/both) and renewal limits.
	 *
	 * @since    1.6.0
	 * @return   bool    True if user can use subscription controls.
	 */
	public function can_use_subscription_controls() {
		return $this->can_use_feature( 'subscription_controls' );
	}

	/**
	 * Check if user can use a specific discount type.
	 *
	 * @since    1.0.0
	 * @param    string $discount_type    Discount type (tiered, bogo, spend_threshold).
	 * @return   bool                        True if user can use this discount type.
	 */
	public function can_use_discount_type( $discount_type ) {
		// Percentage and fixed are always available
		$free_types = array( 'percentage', 'fixed' );
		if ( in_array( $discount_type, $free_types, true ) ) {
			return true;
		}

		// Premium types require Pro
		$feature_key = 'discount_type_' . $discount_type;
		return $this->can_use_feature( $feature_key );
	}

	/**
	 * Get list of available discount types based on subscription level.
	 *
	 * @since    1.0.0
	 * @return   array    Array of discount type keys that are available.
	 */
	public function get_available_discount_types() {
		$all_types = array( 'percentage', 'fixed', 'tiered', 'bogo', 'spend_threshold' );
		$available = array();

		foreach ( $all_types as $type ) {
			if ( $this->can_use_discount_type( $type ) ) {
				$available[] = $type;
			}
		}

		return $available;
	}

	/**
	 * Get campaign limit based on subscription level.
	 *
	 * NOTE: Campaigns are now unlimited for all users (free and pro).
	 * This method is kept for backward compatibility but always returns 0 (unlimited).
	 *
	 * @since    1.0.0
	 * @return   int    Always returns 0 (unlimited).
	 */
	public function get_campaign_limit() {
		return 0; // Unlimited for all users
	}

	/**
	 * Check if user can create more campaigns.
	 *
	 * NOTE: Campaigns are now unlimited for all users (free and pro).
	 * This method is kept for backward compatibility but always returns true.
	 *
	 * @since    1.0.0
	 * @param    int $current_count    Current campaign count (ignored).
	 * @return   bool                     Always returns true.
	 */
	public function can_create_campaign( $current_count ) {
		return true; // Unlimited campaigns for all users
	}

	/**
	 * Get all feature definitions.
	 *
	 * @since    1.0.0
	 * @return   array    Feature definitions.
	 */
	public function get_all_features() {
		return $this->features;
	}

	/**
	 * Get pro features only.
	 *
	 * @since    1.0.0
	 * @return   array    Pro feature keys.
	 */
	public function get_pro_features() {
		return array_keys(
			array_filter(
				$this->features,
				function ( $level ) {
					return 'pro' === $level;
				}
			)
		);
	}

	/**
	 * Clear premium status cache.
	 *
	 * Useful when subscription status changes.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function clear_cache() {
		$this->is_premium_cached = null;
	}

	/**
	 * Get upgrade URL.
	 *
	 * @since    1.0.0
	 * @return   string    Upgrade URL.
	 */
	public function get_upgrade_url() {
		if ( ! $this->is_freemius_loaded() ) {
			return admin_url( 'admin.php?page=smart-cycle-discounts-pricing' );
		}

		return wsscd_fs()->get_upgrade_url();
	}

	/**
	 * Check if notification type can be sent.
	 *
	 * @since    1.0.0
	 * @param    string $notification_type    Notification type (campaign_started, campaign_ending, etc.).
	 * @return   bool                            True if notification can be sent.
	 */
	public function can_send_notification( $notification_type ) {
		$feature_key = 'notification_' . $notification_type;
		return $this->can_use_feature( $feature_key );
	}

	/**
	 * Get all notification types and their access levels.
	 *
	 * @since    1.0.0
	 * @return   array    Notification types with their levels.
	 */
	public function get_notification_types() {
		$notifications = array();
		foreach ( $this->features as $feature_key => $level ) {
			if ( strpos( $feature_key, 'notification_' ) === 0 ) {
				$notification_type                   = str_replace( 'notification_', '', $feature_key );
				$notifications[ $notification_type ] = $level;
			}
		}
		return $notifications;
	}

	/**
	 * Get free notification types.
	 *
	 * @since    1.0.0
	 * @return   array    Free notification type keys.
	 */
	public function get_free_notifications() {
		$free = array();
		foreach ( $this->get_notification_types() as $type => $level ) {
			if ( 'free' === $level ) {
				$free[] = $type;
			}
		}
		return $free;
	}

	/**
	 * Get pro notification types.
	 *
	 * @since    1.0.0
	 * @return   array    Pro notification type keys.
	 */
	public function get_pro_notifications() {
		$pro = array();
		foreach ( $this->get_notification_types() as $type => $level ) {
			if ( 'pro' === $level ) {
				$pro[] = $type;
			}
		}
		return $pro;
	}

	/**
	 * Check if a notification type is PRO tier (regardless of user access).
	 *
	 * @since    1.0.0
	 * @param    string $notification_type    Notification type.
	 * @return   bool                         True if PRO tier, false if free.
	 */
	public function is_notification_pro_tier( $notification_type ) {
		return in_array( $notification_type, $this->get_pro_notifications(), true );
	}

}
