<?php
/**
 * Feature Gate Service
 *
 * Manages feature access based on licensing (Free vs Pro).
 * Integrates with Freemius SDK for premium feature checks.
 *
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/licensing
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
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Feature_Gate {

	/**
	 * Feature definitions (Free vs Pro).
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $features    Feature access definitions.
	 */
	private $features = array(
		// Dashboard features
		'dashboard_advanced_stats'           => 'pro',
		'dashboard_custom_date_range'        => 'pro',
		'dashboard_export'                   => 'pro',

		// Analytics features
		'analytics_page'                     => 'pro',
		'analytics_detailed_metrics'         => 'pro',
		'analytics_traffic_breakdown'        => 'pro',
		'analytics_device_breakdown'         => 'pro',
		'analytics_geographic_data'          => 'pro',
		'analytics_funnel_analysis'          => 'pro',

		// Campaign features
		'campaigns_unlimited'                => 'pro',
		'campaigns_advanced_rotation'        => 'pro',
		'campaigns_geographic_restrictions'  => 'pro',
		'campaigns_customer_segments'        => 'pro',
		'campaigns_advanced_product_filters' => 'pro',
		'campaigns_recurring'                => 'pro',

		// Discount types
		'discount_type_tiered'               => 'pro',
		'discount_type_bogo'                 => 'pro',
		'discount_type_spend_threshold'      => 'pro',

		// Email notification types (FREE = reactive, PRO = proactive)
		'notification_campaign_started'      => 'free',      // Reactive: after start
		'notification_campaign_ending'       => 'pro',        // Proactive: 24h warning
		'notification_campaign_ended'        => 'free',        // Reactive: after end
		'notification_daily_report'          => 'pro',           // Proactive: daily insights
		'notification_weekly_report'         => 'pro',          // Proactive: weekly insights
		'notification_performance_alert'     => 'pro',      // Proactive: smart alerts
		'notification_low_stock_alert'       => 'pro',        // Proactive: stock warnings
		'notification_milestone_alert'       => 'pro',        // Proactive: achievement notifications

		// Email providers (all FREE)
		'email_provider_wpmail'              => 'free',
		'email_provider_sendgrid'            => 'free',
		'email_provider_amazonses'           => 'free',

		// Export features
		'export_csv'                         => 'pro',
		'export_json'                        => 'pro',
		'export_scheduled_reports'           => 'pro',

		// Advanced features
		'api_access'                         => 'pro',
		'priority_support'                   => 'pro',
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
		return function_exists( 'scd_fs' ) && is_object( scd_fs() );
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
		// Return cached result if available
		if ( null !== $this->is_premium_cached ) {
			return $this->is_premium_cached;
		}

		// Use License Manager for server-validated check (Phase 2)
		if ( function_exists( 'scd_is_license_valid' ) ) {
			$this->is_premium_cached = scd_is_license_valid();
			return $this->is_premium_cached;
		}

		// Fallback: check Freemius directly if License Manager unavailable
		if ( ! $this->is_freemius_loaded() ) {
			$this->is_premium_cached = false;
			return false;
		}

		// Last resort: direct Freemius check
		$this->is_premium_cached = scd_fs()->is_premium() || scd_fs()->is_trial();

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

		return scd_fs()->is_trial();
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
	 * Check if user can access analytics page.
	 *
	 * @since    1.0.0
	 * @return   bool    True if user can access analytics.
	 */
	public function can_access_analytics() {
		return $this->can_use_feature( 'analytics_page' );
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
	 * Check if user can use custom date ranges.
	 *
	 * @since    1.0.0
	 * @return   bool    True if user can use custom date ranges.
	 */
	public function can_use_custom_date_ranges() {
		return $this->can_use_feature( 'dashboard_custom_date_range' );
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
	 * @since    1.0.0
	 * @return   int    Campaign limit (0 = unlimited).
	 */
	public function get_campaign_limit() {
		if ( $this->is_premium() ) {
			return 0; // Unlimited
		}

		return 3; // Free tier limit
	}

	/**
	 * Check if user can create more campaigns.
	 *
	 * @since    1.0.0
	 * @param    int $current_count    Current campaign count.
	 * @return   bool                     True if user can create more campaigns.
	 */
	public function can_create_campaign( $current_count ) {
		$limit = $this->get_campaign_limit();

		// Unlimited campaigns
		if ( 0 === $limit ) {
			return true;
		}

		return $current_count < $limit;
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

		return scd_fs()->get_upgrade_url();
	}

	/**
	 * Get trial URL.
	 *
	 * @since    1.0.0
	 * @return   string    Trial URL.
	 */
	public function get_trial_url() {
		if ( ! $this->is_freemius_loaded() ) {
			return admin_url( 'admin.php?page=smart-cycle-discounts-pricing' );
		}

		return scd_fs()->get_trial_url();
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
}
