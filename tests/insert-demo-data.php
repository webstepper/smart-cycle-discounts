<?php
/**
 * Demo Data Insertion Script
 *
 * Creates 10 demo campaigns with creative names and realistic analytics data.
 * Used for screenshots and demos.
 *
 * Usage: Add ?wsscd_insert_demo_data=1 to any admin page URL
 *
 * @package    SmartCycleDiscounts
 * @since      1.0.0
 */

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls, PluginCheck.Security.DirectDB.UnescapedDBParameter
// This is a development/demo utility file, not production code.

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Insert demo data for screenshots.
 *
 * @return void
 */
function wsscd_insert_demo_data_on_admin_init() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! isset( $_GET['wsscd_insert_demo_data'] ) ) {
		return;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'You do not have permission to run this.', 'Permission Denied', array( 'response' => 403 ) );
	}

	$inserter = new WSSCD_Demo_Data_Inserter();
	$inserter->run();
}
add_action( 'admin_init', 'wsscd_insert_demo_data_on_admin_init', 1 );

/**
 * Demo Data Inserter Class
 */
class WSSCD_Demo_Data_Inserter {

	/**
	 * Created campaign IDs.
	 *
	 * @var array
	 */
	private $campaign_ids = array();

	/**
	 * Demo campaign names for identification.
	 *
	 * @var array
	 */
	private $demo_campaign_names = array(
		'Black Friday Mega Deals',
		'Cyber Week Specials',
		'VIP Members Exclusive',
		'Holiday Gift Shop',
		'Christmas Countdown',
		'New Year New Deals',
		'Winter Clearance',
		'Buy More Save More',
		'Halloween Spooktacular',
		'Fall Favorites Sale',
	);

	/**
	 * Run the demo data insertion.
	 *
	 * @return void
	 */
	public function run() {
		echo '<!DOCTYPE html><html><head><title>SCD Demo Data Insertion</title></head><body>';
		echo '<pre style="background:#1d2327;color:#f0f0f1;padding:20px;font-family:monospace;font-size:13px;line-height:1.6;border-radius:4px;max-width:900px;margin:40px auto;">';

		$this->output( "======================================================================" );
		$this->output( "  SCD DEMO DATA INSERTION" );
		$this->output( "  Creating campaigns and analytics for screenshots" );
		$this->output( "======================================================================\n" );

		// Clean up existing demo data first
		$this->cleanup_existing_demo_data();

		// Create campaigns
		$this->create_demo_campaigns();

		// Insert analytics data
		$this->insert_analytics_data();

		// Summary
		$this->output( "\n======================================================================" );
		$this->output( "  COMPLETE!" );
		$this->output( "======================================================================" );
		$this->output( "  Created: " . count( $this->campaign_ids ) . " campaigns" );
		$this->output( "  Analytics: 30 days of data per campaign" );
		$this->output( "\n  You can now take screenshots of:" );
		$this->output( "  - Campaigns list page" );
		$this->output( "  - Analytics dashboard" );
		$this->output( "======================================================================" );

		echo '</pre>';
		echo '<p style="text-align:center;margin-top:20px;">';
		echo '<a href="' . esc_url( admin_url( 'admin.php?page=wsscd-campaigns' ) ) . '" style="color:#2271b1;margin-right:20px;">View Campaigns</a>';
		echo '<a href="' . esc_url( admin_url( 'admin.php?page=wsscd-analytics' ) ) . '" style="color:#2271b1;">View Analytics</a>';
		echo '</p>';
		echo '</body></html>';
		exit;
	}

	/**
	 * Create demo campaigns with creative names.
	 *
	 * @return void
	 */
	private function create_demo_campaigns() {
		$this->output( "[CAMPAIGNS] Creating 10 demo campaigns...\n" );

		global $wpdb;
		$campaigns_table = $wpdb->prefix . 'wsscd_campaigns';
		$current_user_id = get_current_user_id();
		$now             = current_time( 'mysql' );

		// Campaign configurations with creative e-commerce names
		// Dates are relative to current date (late November 2025)
		$campaigns = array(
			// ACTIVE campaigns (4) - Currently running holiday/winter sales
			array(
				'name'           => 'Black Friday Mega Deals',
				'status'         => 'active',
				'discount_type'  => 'percentage',
				'discount_value' => 40,
				'starts_at'      => gmdate( 'Y-m-d H:i:s', strtotime( '-3 days' ) ),
				'ends_at'        => gmdate( 'Y-m-d H:i:s', strtotime( '+4 days' ) ),
				'description'    => 'Biggest discounts of the year',
			),
			array(
				'name'           => 'Cyber Week Specials',
				'status'         => 'active',
				'discount_type'  => 'percentage',
				'discount_value' => 35,
				'starts_at'      => gmdate( 'Y-m-d H:i:s', strtotime( '-1 days' ) ),
				'ends_at'        => gmdate( 'Y-m-d H:i:s', strtotime( '+6 days' ) ),
				'description'    => 'Online-only cyber week savings',
			),
			array(
				'name'           => 'VIP Members Exclusive',
				'status'         => 'active',
				'discount_type'  => 'fixed',
				'discount_value' => 15,
				'starts_at'      => gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) ),
				'ends_at'        => null,
				'description'    => 'Special pricing for our loyal VIP members',
			),
			array(
				'name'           => 'Holiday Gift Shop',
				'status'         => 'active',
				'discount_type'  => 'percentage',
				'discount_value' => 25,
				'starts_at'      => gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) ),
				'ends_at'        => gmdate( 'Y-m-d H:i:s', strtotime( '+30 days' ) ),
				'description'    => 'Perfect gifts at perfect prices',
			),

			// SCHEDULED campaigns (2) - Upcoming winter/new year sales
			array(
				'name'           => 'Christmas Countdown',
				'status'         => 'scheduled',
				'discount_type'  => 'percentage',
				'discount_value' => 30,
				'starts_at'      => gmdate( 'Y-m-d H:i:s', strtotime( '+17 days' ) ),
				'ends_at'        => gmdate( 'Y-m-d H:i:s', strtotime( '+25 days' ) ),
				'description'    => 'Last-minute holiday savings',
			),
			array(
				'name'           => 'New Year New Deals',
				'status'         => 'scheduled',
				'discount_type'  => 'percentage',
				'discount_value' => 50,
				'starts_at'      => gmdate( 'Y-m-d H:i:s', strtotime( '+33 days' ) ),
				'ends_at'        => gmdate( 'Y-m-d H:i:s', strtotime( '+40 days' ) ),
				'description'    => 'Ring in the new year with huge savings',
			),

			// PAUSED campaigns (2) - Temporarily paused promotions
			array(
				'name'           => 'Winter Clearance',
				'status'         => 'paused',
				'discount_type'  => 'percentage',
				'discount_value' => 60,
				'starts_at'      => gmdate( 'Y-m-d H:i:s', strtotime( '-10 days' ) ),
				'ends_at'        => gmdate( 'Y-m-d H:i:s', strtotime( '+20 days' ) ),
				'description'    => 'Clearance sale - paused for Black Friday',
			),
			array(
				'name'           => 'Buy More Save More',
				'status'         => 'paused',
				'discount_type'  => 'tiered',
				'discount_value' => 10,
				'starts_at'      => gmdate( 'Y-m-d H:i:s', strtotime( '-14 days' ) ),
				'ends_at'        => null,
				'description'    => 'Tiered discounts - resuming after holidays',
			),

			// EXPIRED campaigns (2) - Past fall promotions
			array(
				'name'           => 'Halloween Spooktacular',
				'status'         => 'expired',
				'discount_type'  => 'percentage',
				'discount_value' => 31,
				'starts_at'      => gmdate( 'Y-m-d H:i:s', strtotime( '-35 days' ) ),
				'ends_at'        => gmdate( 'Y-m-d H:i:s', strtotime( '-28 days' ) ),
				'description'    => 'Spooky savings on everything',
			),
			array(
				'name'           => 'Fall Favorites Sale',
				'status'         => 'expired',
				'discount_type'  => 'fixed',
				'discount_value' => 20,
				'starts_at'      => gmdate( 'Y-m-d H:i:s', strtotime( '-60 days' ) ),
				'ends_at'        => gmdate( 'Y-m-d H:i:s', strtotime( '-40 days' ) ),
				'description'    => 'Autumn collection discounts',
			),
		);

		foreach ( $campaigns as $index => $campaign ) {
			$uuid = wp_generate_uuid4();
			$slug = sanitize_title( $campaign['name'] );

			$result = $wpdb->insert(
				$campaigns_table,
				array(
					'name'                   => $campaign['name'],
					'uuid'                   => $uuid,
					'slug'                   => $slug,
					'status'                 => $campaign['status'],
					'product_selection_type' => 'all_products',
					'discount_type'          => $campaign['discount_type'],
					'discount_value'         => $campaign['discount_value'],
					'priority'               => $index + 1,
					'starts_at'              => $campaign['starts_at'],
					'ends_at'                => $campaign['ends_at'],
					'created_by'             => $current_user_id,
					'created_at'             => $now,
					'updated_at'             => $now,
				),
				array( '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%d', '%s', '%s', '%d', '%s', '%s' )
			);

			if ( false === $result ) {
				$this->output( "  [!] Failed: {$campaign['name']} - " . $wpdb->last_error );
				continue;
			}

			$campaign_id = $wpdb->insert_id;
			$this->campaign_ids[ $campaign_id ] = $campaign;

			$status_icon = $this->get_status_icon( $campaign['status'] );
			$this->output( "  {$status_icon} {$campaign['name']}" );
			$this->output( "     Status: {$campaign['status']} | Discount: {$campaign['discount_value']}% {$campaign['discount_type']}" );
		}

		$this->output( "\n  Created " . count( $this->campaign_ids ) . " campaigns" );
	}

	/**
	 * Insert analytics data for all campaigns.
	 *
	 * @return void
	 */
	private function insert_analytics_data() {
		$this->output( "\n[ANALYTICS] Inserting 30 days of metrics data...\n" );

		global $wpdb;
		$analytics_table = $wpdb->prefix . 'wsscd_analytics';

		// Performance profiles for different campaign types
		$profiles = array(
			'high_performer'   => array( 'base_impressions' => 2500, 'ctr' => 0.08, 'conv_rate' => 0.12, 'aov' => 75 ),
			'medium_performer' => array( 'base_impressions' => 1500, 'ctr' => 0.06, 'conv_rate' => 0.08, 'aov' => 55 ),
			'low_performer'    => array( 'base_impressions' => 800,  'ctr' => 0.04, 'conv_rate' => 0.05, 'aov' => 40 ),
			'viral'            => array( 'base_impressions' => 5000, 'ctr' => 0.12, 'conv_rate' => 0.15, 'aov' => 90 ),
		);

		// Assign profiles to campaigns
		$campaign_profiles = array(
			'Black Friday Mega Deals' => 'viral',
			'Cyber Week Specials'     => 'high_performer',
			'VIP Members Exclusive'   => 'medium_performer',
			'Holiday Gift Shop'       => 'high_performer',
			'Christmas Countdown'     => 'medium_performer',  // scheduled, less data
			'New Year New Deals'      => 'low_performer',     // scheduled, less data
			'Winter Clearance'        => 'medium_performer',
			'Buy More Save More'      => 'low_performer',
			'Halloween Spooktacular'  => 'high_performer',
			'Fall Favorites Sale'     => 'medium_performer',
		);

		$total_records = 0;

		foreach ( $this->campaign_ids as $campaign_id => $campaign ) {
			$profile_name = $campaign_profiles[ $campaign['name'] ] ?? 'medium_performer';
			$profile      = $profiles[ $profile_name ];

			// Determine date range based on status
			$days_of_data = 30;
			if ( 'scheduled' === $campaign['status'] ) {
				$days_of_data = 0; // No data for scheduled campaigns
			} elseif ( 'expired' === $campaign['status'] ) {
				$days_of_data = 30; // Historical data
			}

			if ( 0 === $days_of_data ) {
				$this->output( "  [SKIP] {$campaign['name']} (scheduled - no data yet)" );
				continue;
			}

			$records = 0;

			for ( $day = $days_of_data; $day >= 0; $day-- ) {
				$date = gmdate( 'Y-m-d', strtotime( "-{$day} days" ) );

				// Add daily variation (weekends higher, random noise)
				$day_of_week  = (int) gmdate( 'N', strtotime( $date ) );
				$weekend_mult = ( $day_of_week >= 6 ) ? 1.4 : 1.0;
				$random_mult  = 0.7 + ( mt_rand( 0, 60 ) / 100 ); // 0.7 to 1.3

				// Add growth trend (newer = more traffic)
				$growth_mult = 0.8 + ( ( $days_of_data - $day ) / $days_of_data ) * 0.4;

				$impressions = (int) ( $profile['base_impressions'] * $weekend_mult * $random_mult * $growth_mult );
				$clicks      = (int) ( $impressions * $profile['ctr'] * ( 0.8 + mt_rand( 0, 40 ) / 100 ) );
				$conversions = (int) ( $clicks * $profile['conv_rate'] * ( 0.8 + mt_rand( 0, 40 ) / 100 ) );
				$revenue     = round( $conversions * $profile['aov'] * ( 0.9 + mt_rand( 0, 20 ) / 100 ), 2 );
				$discount    = round( $revenue * 0.15, 2 ); // ~15% of revenue as discount

				$wpdb->insert(
					$analytics_table,
					array(
						'campaign_id'    => $campaign_id,
						'date_recorded'  => $date,
						'hour_recorded'  => 12,
						'impressions'    => $impressions,
						'clicks'         => $clicks,
						'conversions'    => $conversions,
						'revenue'        => $revenue,
						'discount_given' => $discount,
						'created_at'     => gmdate( 'Y-m-d H:i:s' ),
					),
					array( '%d', '%s', '%d', '%d', '%d', '%d', '%f', '%f', '%s' )
				);

				++$records;
			}

			$total_records += $records;
			$this->output( "  [OK] {$campaign['name']}: {$records} days of data ({$profile_name})" );
		}

		$this->output( "\n  Total: {$total_records} analytics records inserted" );
	}

	/**
	 * Get status icon.
	 *
	 * @param string $status Campaign status.
	 * @return string Icon character.
	 */
	private function get_status_icon( string $status ): string {
		$icons = array(
			'active'    => '[ACTIVE]',
			'paused'    => '[PAUSED]',
			'scheduled' => '[SCHEDULED]',
			'expired'   => '[EXPIRED]',
		);
		return $icons[ $status ] ?? '[?]';
	}

	/**
	 * Output text.
	 *
	 * @param string $text Text to output.
	 * @return void
	 */
	private function output( string $text ) {
		echo esc_html( $text ) . "\n";
	}

	/**
	 * Clean up existing demo data before inserting fresh data.
	 *
	 * @return void
	 */
	private function cleanup_existing_demo_data() {
		global $wpdb;

		$this->output( "[CLEANUP] Removing existing demo campaigns...\n" );

		$campaigns_table = $wpdb->prefix . 'wsscd_campaigns';
		$analytics_table = $wpdb->prefix . 'wsscd_analytics';

		// Find existing demo campaigns by name
		$placeholders = implode( ', ', array_fill( 0, count( $this->demo_campaign_names ), '%s' ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$existing_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM {$campaigns_table} WHERE name IN ({$placeholders})",
				$this->demo_campaign_names
			)
		);

		if ( empty( $existing_ids ) ) {
			$this->output( "  No existing demo campaigns found.\n" );
			return;
		}

		$this->output( "  Found " . count( $existing_ids ) . " existing demo campaigns to remove." );

		// Delete analytics data for these campaigns
		$id_placeholders = implode( ', ', array_fill( 0, count( $existing_ids ), '%d' ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$analytics_deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$analytics_table} WHERE campaign_id IN ({$id_placeholders})",
				$existing_ids
			)
		);

		$this->output( "  Deleted {$analytics_deleted} analytics records." );

		// Delete the campaigns
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$campaigns_deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$campaigns_table} WHERE id IN ({$id_placeholders})",
				$existing_ids
			)
		);

		$this->output( "  Deleted {$campaigns_deleted} campaigns.\n" );
	}
}
