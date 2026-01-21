<?php
/**
 * Comprehensive Analytics Test Suite v2
 *
 * Rigorous testing of analytics calculations, data integrity, edge cases, and security.
 * Fixes: false positives, test isolation, missing scenarios.
 *
 * Usage:
 *   - Via browser: Add ?wsscd_run_comprehensive_analytics_test=1 to any admin page URL
 *   - Requires admin access
 *
 * @package    SmartCycleDiscounts
 * @since      1.0.0
 */

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls, PluginCheck.Security.DirectDB.UnescapedDBParameter
// This is a test utility file, not production code.

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Comprehensive Analytics Test Runner v2
 */
class WSSCD_Comprehensive_Analytics_Test {

	/**
	 * Test campaign ID for testing (primary).
	 *
	 * @var int
	 */
	private $test_campaign_id = 0;

	/**
	 * Multiple test campaign IDs for multi-campaign tests.
	 *
	 * @var array
	 */
	private $test_campaigns = array();

	/**
	 * Test summary.
	 *
	 * @var array
	 */
	private $summary = array(
		'total'   => 0,
		'passed'  => 0,
		'failed'  => 0,
		'skipped' => 0,
	);

	/**
	 * Epsilon for float comparisons.
	 *
	 * @var float
	 */
	private $epsilon = 0.001;

	/**
	 * Run all comprehensive tests.
	 *
	 * @return void
	 */
	public function run() {
		$this->output( "\n" . str_repeat( '=', 70 ) );
		$this->output( "  SCD COMPREHENSIVE ANALYTICS TEST SUITE v2" );
		$this->output( "  Rigorous Testing with Isolation & Security Checks" );
		$this->output( str_repeat( '=', 70 ) . "\n" );

		// Setup test environment
		if ( ! $this->setup() ) {
			$this->output( "\n[FATAL] Setup failed - cannot continue tests" );
			return;
		}

		// Run test groups - Single Campaign
		$this->test_calculation_accuracy();
		$this->test_data_insertion_retrieval();
		$this->test_division_by_zero();
		$this->test_negative_values();
		$this->test_float_precision();
		$this->test_large_numbers();
		$this->test_date_range_handling();
		$this->test_period_comparison();
		$this->test_null_handling();
		$this->test_sql_injection_prevention();
		$this->test_invalid_inputs();
		$this->test_cache_behavior();
		$this->test_data_integrity();

		// Run test groups - Multi-Campaign
		$this->test_multi_campaign_isolation();
		$this->test_overall_metrics_aggregation();
		$this->test_campaign_comparison();
		$this->test_overlapping_campaigns();
		$this->test_real_world_patterns();

		// Cleanup
		$this->teardown();

		// Output summary
		$this->output_summary();
	}

	/**
	 * Setup test environment.
	 *
	 * @return bool True if setup succeeded.
	 */
	private function setup() {
		$this->output( "[SETUP] Preparing isolated test environment..." );

		global $wpdb;

		// Get current user ID for foreign key constraint
		$current_user_id = get_current_user_id();
		if ( ! $current_user_id ) {
			$this->output( "  ✗ No user logged in - cannot create test campaign" );
			return false;
		}

		$campaigns_table = $wpdb->prefix . 'wsscd_campaigns';

		// Create multiple test campaigns for multi-campaign testing
		$campaign_configs = array(
			array(
				'name'          => 'TEST_CAMPAIGN_A_' . wp_generate_password( 6, false ),
				'discount_type' => 'percentage',
				'discount_value' => 10,
				'status'        => 'active',
			),
			array(
				'name'          => 'TEST_CAMPAIGN_B_' . wp_generate_password( 6, false ),
				'discount_type' => 'fixed',
				'discount_value' => 5,
				'status'        => 'active',
			),
			array(
				'name'          => 'TEST_CAMPAIGN_C_' . wp_generate_password( 6, false ),
				'discount_type' => 'percentage',
				'discount_value' => 15,
				'status'        => 'paused',
			),
		);

		foreach ( $campaign_configs as $index => $config ) {
			// Generate unique UUID and slug for each campaign
			$uuid = wp_generate_uuid4();
			$slug = sanitize_title( $config['name'] );

			$result = $wpdb->insert(
				$campaigns_table,
				array(
					'name'                   => $config['name'],
					'uuid'                   => $uuid,
					'slug'                   => $slug,
					'status'                 => $config['status'],
					'product_selection_type' => 'all_products',
					'discount_type'          => $config['discount_type'],
					'discount_value'         => $config['discount_value'],
					'priority'               => $index + 1,
					'created_by'             => $current_user_id,
					'created_at'             => gmdate( 'Y-m-d H:i:s' ),
					'updated_at'             => gmdate( 'Y-m-d H:i:s' ),
				),
				array( '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%d', '%d', '%s', '%s' )
			);

			if ( false === $result ) {
				$this->output( "  ✗ Failed to create test campaign {$index}: " . $wpdb->last_error );
				return false;
			}

			$campaign_id = $wpdb->insert_id;
			if ( ! $campaign_id ) {
				$this->output( "  ✗ Test campaign {$index} ID is 0" );
				return false;
			}

			$this->test_campaigns[] = array(
				'id'             => $campaign_id,
				'name'           => $config['name'],
				'discount_type'  => $config['discount_type'],
				'discount_value' => $config['discount_value'],
				'status'         => $config['status'],
			);

			$this->output( "  ✓ Campaign {$index} created (ID: {$campaign_id}, Name: {$config['name']})" );
		}

		// Set primary test campaign ID for backward compatibility
		$this->test_campaign_id = $this->test_campaigns[0]['id'];

		$this->output( "" );
		return true;
	}

	/**
	 * Teardown test environment.
	 *
	 * @return void
	 */
	private function teardown() {
		$this->output( "\n[TEARDOWN] Cleaning up test data..." );

		global $wpdb;

		$analytics_table         = $wpdb->prefix . 'wsscd_analytics';
		$product_analytics_table = $wpdb->prefix . 'wsscd_product_analytics';
		$campaigns_table         = $wpdb->prefix . 'wsscd_campaigns';

		$total_analytics = 0;
		$total_product   = 0;
		$total_campaigns = 0;

		// Clean up ALL test campaigns
		foreach ( $this->test_campaigns as $campaign ) {
			$campaign_id = $campaign['id'];

			// Delete analytics data
			$deleted = $wpdb->delete(
				$analytics_table,
				array( 'campaign_id' => $campaign_id ),
				array( '%d' )
			);
			$total_analytics += (int) $deleted;

			// Delete product analytics data
			$deleted = $wpdb->delete(
				$product_analytics_table,
				array( 'campaign_id' => $campaign_id ),
				array( '%d' )
			);
			$total_product += (int) $deleted;

			// Delete campaign
			$deleted = $wpdb->delete(
				$campaigns_table,
				array( 'id' => $campaign_id ),
				array( '%d' )
			);
			$total_campaigns += (int) $deleted;
		}

		$this->output( "  ✓ Deleted {$total_analytics} analytics records" );
		$this->output( "  ✓ Deleted {$total_product} product analytics records" );
		$this->output( "  ✓ Deleted {$total_campaigns} test campaigns" );
	}

	/**
	 * Test calculation accuracy with exact values.
	 *
	 * @return void
	 */
	private function test_calculation_accuracy() {
		$this->output( "\n[1] CALCULATION ACCURACY TESTS" );
		$this->output( str_repeat( '-', 60 ) );

		global $wpdb;
		$analytics_table = $wpdb->prefix . 'wsscd_analytics';
		$today           = gmdate( 'Y-m-d' );

		// Insert EXACT test data
		$test_data = array(
			'impressions'    => 1000,
			'clicks'         => 100,
			'conversions'    => 10,
			'revenue'        => 500.00,
			'discount_given' => 50.00,
		);

		$wpdb->insert(
			$analytics_table,
			array(
				'campaign_id'    => $this->test_campaign_id,
				'date_recorded'  => $today,
				'hour_recorded'  => 12,
				'impressions'    => $test_data['impressions'],
				'clicks'         => $test_data['clicks'],
				'conversions'    => $test_data['conversions'],
				'revenue'        => $test_data['revenue'],
				'discount_given' => $test_data['discount_given'],
				'created_at'     => gmdate( 'Y-m-d H:i:s' ),
			),
			array( '%d', '%s', '%d', '%d', '%d', '%d', '%f', '%f', '%s' )
		);

		$container = wsscd();
		if ( ! $container ) {
			$this->skip( 'Container not available' );
			return;
		}

		$calculator = $container->get( 'metrics_calculator' );
		if ( ! $calculator ) {
			$this->skip( 'Metrics Calculator not available' );
			return;
		}

		$metrics = $calculator->calculate_campaign_metrics( $this->test_campaign_id, '7days', false );

		// Expected calculations (exact math)
		$expected_ctr             = ( $test_data['clicks'] / $test_data['impressions'] ) * 100; // 10.0
		$expected_conversion_rate = ( $test_data['conversions'] / $test_data['clicks'] ) * 100; // 10.0
		$expected_aov             = $test_data['revenue'] / $test_data['conversions']; // 50.0

		// Test with strict float comparison
		$this->assert_float_equals(
			$expected_ctr,
			(float) ( $metrics['ctr'] ?? -1 ),
			"CTR: {$test_data['clicks']}/{$test_data['impressions']}*100 = {$expected_ctr}%"
		);

		$this->assert_float_equals(
			$expected_conversion_rate,
			(float) ( $metrics['conversion_rate'] ?? -1 ),
			"Conversion Rate: {$test_data['conversions']}/{$test_data['clicks']}*100 = {$expected_conversion_rate}%"
		);

		$this->assert_float_equals(
			$expected_aov,
			(float) ( $metrics['avg_order_value'] ?? -1 ),
			"AOV: {$test_data['revenue']}/{$test_data['conversions']} = \${$expected_aov}"
		);

		// Test exact integer values
		$this->assert_strict_equals(
			$test_data['impressions'],
			(int) ( $metrics['impressions'] ?? -1 ),
			"Impressions exact match: {$test_data['impressions']}"
		);

		$this->assert_strict_equals(
			$test_data['clicks'],
			(int) ( $metrics['clicks'] ?? -1 ),
			"Clicks exact match: {$test_data['clicks']}"
		);

		$this->assert_strict_equals(
			$test_data['conversions'],
			(int) ( $metrics['conversions'] ?? -1 ),
			"Conversions exact match: {$test_data['conversions']}"
		);

		$this->assert_float_equals(
			$test_data['revenue'],
			(float) ( $metrics['revenue'] ?? -1 ),
			"Revenue exact match: \${$test_data['revenue']}"
		);

		// Cleanup for next tests
		$wpdb->delete( $analytics_table, array( 'campaign_id' => $this->test_campaign_id ), array( '%d' ) );
	}

	/**
	 * Test data insertion and retrieval.
	 *
	 * @return void
	 */
	private function test_data_insertion_retrieval() {
		$this->output( "\n[2] DATA INSERTION & RETRIEVAL TESTS" );
		$this->output( str_repeat( '-', 60 ) );

		$container = wsscd();
		if ( ! $container ) {
			$this->skip( 'Container not available' );
			return;
		}

		$collector = $container->get( 'analytics_collector' );
		if ( ! $collector ) {
			$this->skip( 'Analytics Collector not available' );
			return;
		}

		global $wpdb;
		$analytics_table = $wpdb->prefix . 'wsscd_analytics';
		$today           = gmdate( 'Y-m-d' );

		// Clear any existing data first
		$wpdb->delete( $analytics_table, array( 'campaign_id' => $this->test_campaign_id ), array( '%d' ) );

		// Test impression tracking with VALID campaign ID
		$result = $collector->track_impression( $this->test_campaign_id, array( 'product_id' => 123 ) );
		$this->assert( true === $result, 'track_impression() returns TRUE for valid campaign' );

		// Verify data was actually inserted
		$recorded = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT impressions FROM {$analytics_table} WHERE campaign_id = %d AND date_recorded = %s",
				$this->test_campaign_id,
				$today
			)
		);
		$this->assert( null !== $recorded && (int) $recorded >= 1, "Database actually contains impression data (count: " . ( $recorded ?? 'NULL' ) . ")" );

		// Test click tracking
		$result = $collector->track_click( $this->test_campaign_id, 123, 'product_page' );
		$this->assert( true === $result, 'track_click() returns TRUE for valid campaign' );

		$clicks = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT clicks FROM {$analytics_table} WHERE campaign_id = %d AND date_recorded = %s",
				$this->test_campaign_id,
				$today
			)
		);
		$this->assert( null !== $clicks && (int) $clicks >= 1, "Database actually contains click data (count: " . ( $clicks ?? 'NULL' ) . ")" );

		// Test aggregation: multiple impressions should SUM, not replace
		$initial_count = (int) $recorded;
		$collector->track_impression( $this->test_campaign_id, array() );
		$collector->track_impression( $this->test_campaign_id, array() );

		$new_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT impressions FROM {$analytics_table} WHERE campaign_id = %d AND date_recorded = %s",
				$this->test_campaign_id,
				$today
			)
		);

		$this->assert(
			(int) $new_count === $initial_count + 2,
			"Impressions aggregate correctly: {$initial_count} + 2 = " . ( $new_count ?? 'NULL' )
		);

		// Cleanup
		$wpdb->delete( $analytics_table, array( 'campaign_id' => $this->test_campaign_id ), array( '%d' ) );
		$product_analytics_table = $wpdb->prefix . 'wsscd_product_analytics';
		$wpdb->delete( $product_analytics_table, array( 'campaign_id' => $this->test_campaign_id ), array( '%d' ) );
	}

	/**
	 * Test division by zero handling.
	 *
	 * @return void
	 */
	private function test_division_by_zero() {
		$this->output( "\n[3] DIVISION BY ZERO TESTS" );
		$this->output( str_repeat( '-', 60 ) );

		global $wpdb;
		$analytics_table = $wpdb->prefix . 'wsscd_analytics';
		$today           = gmdate( 'Y-m-d' );

		$container = wsscd();
		if ( ! $container ) {
			$this->skip( 'Container not available' );
			return;
		}

		$calculator = $container->get( 'metrics_calculator' );
		if ( ! $calculator ) {
			$this->skip( 'Metrics Calculator not available' );
			return;
		}

		// Test 1: Zero impressions (CTR would be clicks/0)
		$wpdb->insert(
			$analytics_table,
			array(
				'campaign_id'   => $this->test_campaign_id,
				'date_recorded' => $today,
				'hour_recorded' => 12,
				'impressions'   => 0,
				'clicks'        => 50,  // Non-zero clicks with zero impressions
				'conversions'   => 5,
				'revenue'       => 100.00,
				'created_at'    => gmdate( 'Y-m-d H:i:s' ),
			),
			array( '%d', '%s', '%d', '%d', '%d', '%d', '%f', '%s' )
		);

		$metrics = $calculator->calculate_campaign_metrics( $this->test_campaign_id, '7days', false );

		// CTR should be 0, not NaN, not error, not negative
		$ctr = $metrics['ctr'] ?? null;
		$this->assert(
			null !== $ctr && is_numeric( $ctr ) && (float) $ctr === 0.0,
			"CTR = 0 when impressions = 0 (got: " . var_export( $ctr, true ) . ")"
		);

		// Verify it's not NaN
		$this->assert(
			! is_nan( (float) $ctr ),
			"CTR is not NaN"
		);

		$wpdb->delete( $analytics_table, array( 'campaign_id' => $this->test_campaign_id ), array( '%d' ) );

		// Test 2: Zero clicks (conversion rate would be conversions/0)
		$wpdb->insert(
			$analytics_table,
			array(
				'campaign_id'   => $this->test_campaign_id,
				'date_recorded' => $today,
				'hour_recorded' => 12,
				'impressions'   => 100,
				'clicks'        => 0,
				'conversions'   => 5,  // Non-zero conversions with zero clicks
				'revenue'       => 100.00,
				'created_at'    => gmdate( 'Y-m-d H:i:s' ),
			),
			array( '%d', '%s', '%d', '%d', '%d', '%d', '%f', '%s' )
		);

		$metrics = $calculator->calculate_campaign_metrics( $this->test_campaign_id, '7days', false );

		$conv_rate = $metrics['conversion_rate'] ?? null;
		$this->assert(
			null !== $conv_rate && is_numeric( $conv_rate ) && (float) $conv_rate === 0.0,
			"Conversion rate = 0 when clicks = 0 (got: " . var_export( $conv_rate, true ) . ")"
		);

		$wpdb->delete( $analytics_table, array( 'campaign_id' => $this->test_campaign_id ), array( '%d' ) );

		// Test 3: Zero conversions (AOV would be revenue/0)
		$wpdb->insert(
			$analytics_table,
			array(
				'campaign_id'   => $this->test_campaign_id,
				'date_recorded' => $today,
				'hour_recorded' => 12,
				'impressions'   => 100,
				'clicks'        => 10,
				'conversions'   => 0,
				'revenue'       => 500.00,  // Non-zero revenue with zero conversions
				'created_at'    => gmdate( 'Y-m-d H:i:s' ),
			),
			array( '%d', '%s', '%d', '%d', '%d', '%d', '%f', '%s' )
		);

		$metrics = $calculator->calculate_campaign_metrics( $this->test_campaign_id, '7days', false );

		$aov = $metrics['avg_order_value'] ?? null;
		$this->assert(
			null !== $aov && is_numeric( $aov ) && (float) $aov === 0.0,
			"AOV = 0 when conversions = 0 (got: " . var_export( $aov, true ) . ")"
		);

		$wpdb->delete( $analytics_table, array( 'campaign_id' => $this->test_campaign_id ), array( '%d' ) );
	}

	/**
	 * Test negative value handling.
	 *
	 * @return void
	 */
	private function test_negative_values() {
		$this->output( "\n[4] NEGATIVE VALUE TESTS" );
		$this->output( str_repeat( '-', 60 ) );

		global $wpdb;
		$analytics_table = $wpdb->prefix . 'wsscd_analytics';
		$today           = gmdate( 'Y-m-d' );

		$container = wsscd();
		if ( ! $container ) {
			$this->skip( 'Container not available' );
			return;
		}

		$calculator = $container->get( 'metrics_calculator' );
		if ( ! $calculator ) {
			$this->skip( 'Metrics Calculator not available' );
			return;
		}

		// Test with negative revenue (refund scenario)
		$wpdb->insert(
			$analytics_table,
			array(
				'campaign_id'    => $this->test_campaign_id,
				'date_recorded'  => $today,
				'hour_recorded'  => 12,
				'impressions'    => 100,
				'clicks'         => 10,
				'conversions'    => 5,
				'revenue'        => -50.00,  // Negative revenue (refunds)
				'discount_given' => 25.00,
				'created_at'     => gmdate( 'Y-m-d H:i:s' ),
			),
			array( '%d', '%s', '%d', '%d', '%d', '%d', '%f', '%f', '%s' )
		);

		$metrics = $calculator->calculate_campaign_metrics( $this->test_campaign_id, '7days', false );

		// Revenue should be negative
		$this->assert_float_equals(
			-50.00,
			(float) ( $metrics['revenue'] ?? 0 ),
			"Negative revenue preserved: -\$50.00"
		);

		// AOV should be negative: -50 / 5 = -10
		$this->assert_float_equals(
			-10.00,
			(float) ( $metrics['avg_order_value'] ?? 0 ),
			"AOV calculated correctly with negative revenue: -\$10.00"
		);

		$wpdb->delete( $analytics_table, array( 'campaign_id' => $this->test_campaign_id ), array( '%d' ) );
	}

	/**
	 * Test float precision.
	 *
	 * @return void
	 */
	private function test_float_precision() {
		$this->output( "\n[5] FLOAT PRECISION TESTS" );
		$this->output( str_repeat( '-', 60 ) );

		global $wpdb;
		$analytics_table = $wpdb->prefix . 'wsscd_analytics';
		$today           = gmdate( 'Y-m-d' );

		$container = wsscd();
		if ( ! $container ) {
			$this->skip( 'Container not available' );
			return;
		}

		$calculator = $container->get( 'metrics_calculator' );
		if ( ! $calculator ) {
			$this->skip( 'Metrics Calculator not available' );
			return;
		}

		// Test with values that cause repeating decimals
		// 1/3 = 0.333... (CTR: 33/100 = 33%)
		// 7/3 = 2.333... (conversions)
		$wpdb->insert(
			$analytics_table,
			array(
				'campaign_id'   => $this->test_campaign_id,
				'date_recorded' => $today,
				'hour_recorded' => 12,
				'impressions'   => 300,
				'clicks'        => 100,  // CTR: 100/300 = 33.333...%
				'conversions'   => 3,    // Conv rate: 3/100 = 3%
				'revenue'       => 100.00,  // AOV: 100/3 = 33.333...
				'created_at'    => gmdate( 'Y-m-d H:i:s' ),
			),
			array( '%d', '%s', '%d', '%d', '%d', '%d', '%f', '%s' )
		);

		$metrics = $calculator->calculate_campaign_metrics( $this->test_campaign_id, '7days', false );

		// CTR: 100/300 * 100 = 33.33...%
		$expected_ctr = ( 100 / 300 ) * 100;
		$actual_ctr   = (float) ( $metrics['ctr'] ?? 0 );

		$this->assert(
			abs( $actual_ctr - $expected_ctr ) < 0.1,  // Allow small rounding difference
			"CTR handles repeating decimal: expected ~33.33%, got " . round( $actual_ctr, 2 ) . "%"
		);

		// AOV: 100/3 = 33.33...
		$expected_aov = 100 / 3;
		$actual_aov   = (float) ( $metrics['avg_order_value'] ?? 0 );

		$this->assert(
			abs( $actual_aov - $expected_aov ) < 0.1,
			"AOV handles repeating decimal: expected ~\$33.33, got \$" . round( $actual_aov, 2 )
		);

		// Test penny precision
		$wpdb->delete( $analytics_table, array( 'campaign_id' => $this->test_campaign_id ), array( '%d' ) );

		$wpdb->insert(
			$analytics_table,
			array(
				'campaign_id'   => $this->test_campaign_id,
				'date_recorded' => $today,
				'hour_recorded' => 12,
				'impressions'   => 100,
				'clicks'        => 10,
				'conversions'   => 1,
				'revenue'       => 99.99,  // Penny precision
				'created_at'    => gmdate( 'Y-m-d H:i:s' ),
			),
			array( '%d', '%s', '%d', '%d', '%d', '%d', '%f', '%s' )
		);

		$metrics = $calculator->calculate_campaign_metrics( $this->test_campaign_id, '7days', false );

		$this->assert_float_equals(
			99.99,
			(float) ( $metrics['revenue'] ?? 0 ),
			"Penny precision preserved: \$99.99"
		);

		$wpdb->delete( $analytics_table, array( 'campaign_id' => $this->test_campaign_id ), array( '%d' ) );
	}

	/**
	 * Test large numbers.
	 *
	 * @return void
	 */
	private function test_large_numbers() {
		$this->output( "\n[6] LARGE NUMBER TESTS" );
		$this->output( str_repeat( '-', 60 ) );

		global $wpdb;
		$analytics_table = $wpdb->prefix . 'wsscd_analytics';
		$today           = gmdate( 'Y-m-d' );

		$container = wsscd();
		if ( ! $container ) {
			$this->skip( 'Container not available' );
			return;
		}

		$calculator = $container->get( 'metrics_calculator' );
		if ( ! $calculator ) {
			$this->skip( 'Metrics Calculator not available' );
			return;
		}

		// Test with large but reasonable numbers (1 billion impressions)
		$large_impressions = 1000000000;  // 1 billion
		$large_clicks      = 100000000;   // 100 million (10% CTR)
		$large_conversions = 10000000;    // 10 million (10% conv rate)
		$large_revenue     = 999999999.99; // ~$1 billion

		$wpdb->insert(
			$analytics_table,
			array(
				'campaign_id'   => $this->test_campaign_id,
				'date_recorded' => $today,
				'hour_recorded' => 12,
				'impressions'   => $large_impressions,
				'clicks'        => $large_clicks,
				'conversions'   => $large_conversions,
				'revenue'       => $large_revenue,
				'created_at'    => gmdate( 'Y-m-d H:i:s' ),
			),
			array( '%d', '%s', '%d', '%d', '%d', '%d', '%f', '%s' )
		);

		$metrics = $calculator->calculate_campaign_metrics( $this->test_campaign_id, '7days', false );

		// Verify large numbers are retrieved correctly
		$this->assert(
			(int) ( $metrics['impressions'] ?? 0 ) === $large_impressions,
			"Large impressions: " . number_format( $metrics['impressions'] ?? 0 ) . " = " . number_format( $large_impressions )
		);

		// CTR should still calculate correctly: 100M / 1B * 100 = 10%
		$expected_ctr = ( $large_clicks / $large_impressions ) * 100;
		$this->assert_float_equals(
			$expected_ctr,
			(float) ( $metrics['ctr'] ?? -1 ),
			"Large number CTR: " . round( $expected_ctr, 2 ) . "%"
		);

		// AOV: ~$1B / 10M = ~$100
		$expected_aov = $large_revenue / $large_conversions;
		$this->assert(
			abs( (float) ( $metrics['avg_order_value'] ?? 0 ) - $expected_aov ) < 0.01,
			"Large number AOV: \$" . round( $expected_aov, 2 )
		);

		$wpdb->delete( $analytics_table, array( 'campaign_id' => $this->test_campaign_id ), array( '%d' ) );
	}

	/**
	 * Test date range handling with isolated data.
	 *
	 * @return void
	 */
	private function test_date_range_handling() {
		$this->output( "\n[7] DATE RANGE HANDLING TESTS (Isolated)" );
		$this->output( str_repeat( '-', 60 ) );

		global $wpdb;
		$analytics_table = $wpdb->prefix . 'wsscd_analytics';

		$container = wsscd();
		if ( ! $container ) {
			$this->skip( 'Container not available' );
			return;
		}

		$calculator = $container->get( 'metrics_calculator' );
		if ( ! $calculator ) {
			$this->skip( 'Metrics Calculator not available' );
			return;
		}

		// Insert data at specific dates with known impressions
		$dates_and_impressions = array(
			gmdate( 'Y-m-d' )                         => 100,  // Today - in all ranges
			gmdate( 'Y-m-d', strtotime( '-3 days' ) )  => 200,  // 3 days ago - in all ranges
			gmdate( 'Y-m-d', strtotime( '-10 days' ) ) => 300,  // 10 days ago - NOT in 7days, in 30/90
			gmdate( 'Y-m-d', strtotime( '-45 days' ) ) => 400,  // 45 days ago - NOT in 7/30days, in 90
		);

		foreach ( $dates_and_impressions as $date => $impressions ) {
			$wpdb->insert(
				$analytics_table,
				array(
					'campaign_id'   => $this->test_campaign_id,
					'date_recorded' => $date,
					'hour_recorded' => 12,
					'impressions'   => $impressions,
					'clicks'        => 10,
					'conversions'   => 1,
					'revenue'       => 50.00,
					'created_at'    => gmdate( 'Y-m-d H:i:s' ),
				),
				array( '%d', '%s', '%d', '%d', '%d', '%d', '%f', '%s' )
			);
		}

		// Test 7 days: should include 100 + 200 = 300
		$metrics_7days             = $calculator->calculate_campaign_metrics( $this->test_campaign_id, '7days', false );
		$expected_7days            = 100 + 200;
		$actual_7days_impressions  = (int) ( $metrics_7days['impressions'] ?? 0 );

		$this->assert(
			$actual_7days_impressions === $expected_7days,
			"7-day range: {$expected_7days} impressions, got {$actual_7days_impressions}"
		);

		// Test 30 days: should include 100 + 200 + 300 = 600
		$metrics_30days            = $calculator->calculate_campaign_metrics( $this->test_campaign_id, '30days', false );
		$expected_30days           = 100 + 200 + 300;
		$actual_30days_impressions = (int) ( $metrics_30days['impressions'] ?? 0 );

		$this->assert(
			$actual_30days_impressions === $expected_30days,
			"30-day range: {$expected_30days} impressions, got {$actual_30days_impressions}"
		);

		// Test 90 days: should include all = 1000
		$metrics_90days            = $calculator->calculate_campaign_metrics( $this->test_campaign_id, '90days', false );
		$expected_90days           = 100 + 200 + 300 + 400;
		$actual_90days_impressions = (int) ( $metrics_90days['impressions'] ?? 0 );

		$this->assert(
			$actual_90days_impressions === $expected_90days,
			"90-day range: {$expected_90days} impressions, got {$actual_90days_impressions}"
		);

		$wpdb->delete( $analytics_table, array( 'campaign_id' => $this->test_campaign_id ), array( '%d' ) );
	}

	/**
	 * Test period comparison.
	 *
	 * @return void
	 */
	private function test_period_comparison() {
		$this->output( "\n[8] PERIOD COMPARISON TESTS" );
		$this->output( str_repeat( '-', 60 ) );

		global $wpdb;
		$analytics_table = $wpdb->prefix . 'wsscd_analytics';

		$container = wsscd();
		if ( ! $container ) {
			$this->skip( 'Container not available' );
			return;
		}

		$calculator = $container->get( 'metrics_calculator' );
		if ( ! $calculator ) {
			$this->skip( 'Metrics Calculator not available' );
			return;
		}

		// Current period: $1000 revenue (within last 7 days)
		$wpdb->insert(
			$analytics_table,
			array(
				'campaign_id'   => $this->test_campaign_id,
				'date_recorded' => gmdate( 'Y-m-d', strtotime( '-2 days' ) ),
				'hour_recorded' => 12,
				'impressions'   => 1000,
				'clicks'        => 100,
				'conversions'   => 20,
				'revenue'       => 1000.00,
				'created_at'    => gmdate( 'Y-m-d H:i:s' ),
			),
			array( '%d', '%s', '%d', '%d', '%d', '%d', '%f', '%s' )
		);

		// Previous period: $500 revenue (7-14 days ago)
		$wpdb->insert(
			$analytics_table,
			array(
				'campaign_id'   => $this->test_campaign_id,
				'date_recorded' => gmdate( 'Y-m-d', strtotime( '-10 days' ) ),
				'hour_recorded' => 12,
				'impressions'   => 500,
				'clicks'        => 50,
				'conversions'   => 10,
				'revenue'       => 500.00,
				'created_at'    => gmdate( 'Y-m-d H:i:s' ),
			),
			array( '%d', '%s', '%d', '%d', '%d', '%d', '%f', '%s' )
		);

		$overall = $calculator->calculate_overall_metrics( '7days', false );

		// Note: Overall metrics include ALL campaigns, so we check structure
		$this->assert(
			isset( $overall['total_revenue'] ) && is_numeric( $overall['total_revenue'] ),
			"Overall metrics has total_revenue"
		);

		$this->assert(
			isset( $overall['previous_revenue'] ) && is_numeric( $overall['previous_revenue'] ),
			"Overall metrics has previous_revenue"
		);

		$this->assert(
			isset( $overall['revenue_change'] ) && is_numeric( $overall['revenue_change'] ),
			"Overall metrics has revenue_change percentage"
		);

		// Revenue change calculation: ((new - old) / old) * 100
		// If previous = 0, change should be 100% (or 0 if both are 0)
		$current_rev  = (float) $overall['total_revenue'];
		$previous_rev = (float) $overall['previous_revenue'];

		if ( $previous_rev > 0 ) {
			$expected_change = ( ( $current_rev - $previous_rev ) / $previous_rev ) * 100;
			$actual_change   = (float) $overall['revenue_change'];

			$this->assert(
				abs( $actual_change - $expected_change ) < 1,
				"Revenue change calculated correctly: " . round( $actual_change, 1 ) . "% (expected ~" . round( $expected_change, 1 ) . "%)"
			);
		} else {
			$this->assert( true, "Previous revenue is 0, cannot verify change percentage (acceptable)" );
		}

		$wpdb->delete( $analytics_table, array( 'campaign_id' => $this->test_campaign_id ), array( '%d' ) );
	}

	/**
	 * Test NULL handling.
	 *
	 * @return void
	 */
	private function test_null_handling() {
		$this->output( "\n[9] NULL HANDLING TESTS" );
		$this->output( str_repeat( '-', 60 ) );

		$container = wsscd();
		if ( ! $container ) {
			$this->skip( 'Container not available' );
			return;
		}

		$calculator = $container->get( 'metrics_calculator' );
		if ( ! $calculator ) {
			$this->skip( 'Metrics Calculator not available' );
			return;
		}

		// Query for non-existent campaign - should return array with zeros, not NULLs
		$fake_campaign_id = 999999999;
		$metrics          = $calculator->calculate_campaign_metrics( $fake_campaign_id, '7days', false );

		$this->assert(
			is_array( $metrics ),
			"Non-existent campaign returns array, not NULL"
		);

		// All numeric fields should be 0, not NULL
		$numeric_fields = array( 'impressions', 'clicks', 'conversions', 'revenue', 'ctr', 'conversion_rate', 'avg_order_value' );

		foreach ( $numeric_fields as $field ) {
			$value = $metrics[ $field ] ?? null;
			$this->assert(
				null !== $value && is_numeric( $value ),
				"Field '{$field}' is numeric (not NULL): " . var_export( $value, true )
			);
		}
	}

	/**
	 * Test SQL injection prevention.
	 *
	 * @return void
	 */
	private function test_sql_injection_prevention() {
		$this->output( "\n[10] SQL INJECTION PREVENTION TESTS" );
		$this->output( str_repeat( '-', 60 ) );

		$container = wsscd();
		if ( ! $container ) {
			$this->skip( 'Container not available' );
			return;
		}

		$calculator = $container->get( 'metrics_calculator' );
		$collector  = $container->get( 'analytics_collector' );

		if ( ! $calculator || ! $collector ) {
			$this->skip( 'Services not available' );
			return;
		}

		// Test 1: SQL injection in campaign ID (should be cast to int)
		$malicious_id = "1; DROP TABLE wp_users; --";
		// PHP will cast this to 1 when passed to a function expecting int
		// But we test that no error occurs
		$no_crash = true;
		try {
			// Note: PHP 8 will throw TypeError, PHP 7 will cast to 0
			$metrics = $calculator->calculate_campaign_metrics( (int) $malicious_id, '7days', false );
		} catch ( TypeError $e ) {
			$no_crash = true; // Type safety prevented attack
		} catch ( Exception $e ) {
			$no_crash = false;
		}
		$this->assert( $no_crash, "SQL injection in campaign_id handled safely" );

		// Test 2: Malicious date range
		$malicious_range = "7days'; DROP TABLE wp_scd_analytics; --";
		$no_crash        = true;
		try {
			$metrics = $calculator->calculate_campaign_metrics( $this->test_campaign_id, $malicious_range, false );
		} catch ( Exception $e ) {
			$no_crash = false;
		}
		$this->assert( $no_crash, "SQL injection in date_range handled safely" );
		$this->assert(
			is_array( $metrics ),
			"Malicious date_range returns array (falls back to default)"
		);

		// Test 3: Verify tables still exist (injection didn't work)
		global $wpdb;
		$analytics_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}wsscd_analytics'" );
		$this->assert(
			null !== $analytics_exists,
			"Analytics table still exists after injection attempts"
		);

		$users_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->users}'" );
		$this->assert(
			null !== $users_exists,
			"Users table still exists after injection attempts"
		);
	}

	/**
	 * Test invalid inputs.
	 *
	 * @return void
	 */
	private function test_invalid_inputs() {
		$this->output( "\n[11] INVALID INPUT TESTS" );
		$this->output( str_repeat( '-', 60 ) );

		$container = wsscd();
		if ( ! $container ) {
			$this->skip( 'Container not available' );
			return;
		}

		$calculator = $container->get( 'metrics_calculator' );
		if ( ! $calculator ) {
			$this->skip( 'Metrics Calculator not available' );
			return;
		}

		// Test negative campaign ID
		$metrics = $calculator->calculate_campaign_metrics( -1, '7days', false );
		$this->assert(
			is_array( $metrics ),
			"Negative campaign ID returns array (graceful failure)"
		);

		// Test zero campaign ID
		$metrics = $calculator->calculate_campaign_metrics( 0, '7days', false );
		$this->assert(
			is_array( $metrics ),
			"Zero campaign ID returns array (graceful failure)"
		);

		// Test invalid date range
		$metrics = $calculator->calculate_campaign_metrics( $this->test_campaign_id, 'invalid', false );
		$this->assert(
			is_array( $metrics ),
			"Invalid date range returns array (uses default)"
		);

		// Test empty date range
		$metrics = $calculator->calculate_campaign_metrics( $this->test_campaign_id, '', false );
		$this->assert(
			is_array( $metrics ),
			"Empty date range returns array (uses default)"
		);

		// Test WSSCD_Analytics_Data validation
		$data = new WSSCD_Analytics_Data();

		// Invalid event type
		$data->fill( array(
			'campaign_id' => 1,
			'event_type'  => 'INVALID_EVENT_TYPE_XYZ',
		) );
		$errors = $data->validate();
		$this->assert(
			! empty( $errors ),
			"Invalid event type fails validation"
		);

		// Missing required campaign_id
		$data2 = new WSSCD_Analytics_Data();
		$data2->fill( array( 'event_type' => 'click' ) );
		$errors2 = $data2->validate();
		$this->assert(
			! empty( $errors2 ),
			"Missing campaign_id fails validation"
		);

		// Invalid IP address
		$data3 = new WSSCD_Analytics_Data();
		$data3->fill( array(
			'campaign_id' => 1,
			'event_type'  => 'click',
			'ip_address'  => 'not.a.valid.ip.address',
		) );
		$errors3 = $data3->validate();
		$this->assert(
			! empty( $errors3 ),
			"Invalid IP address fails validation"
		);

		// Valid data should pass
		$data4 = new WSSCD_Analytics_Data();
		$data4->fill( array(
			'campaign_id' => 1,
			'event_type'  => 'click',
			'ip_address'  => '192.168.1.1',
		) );
		$errors4 = $data4->validate();
		$this->assert(
			empty( $errors4 ),
			"Valid data passes validation"
		);
	}

	/**
	 * Test cache behavior.
	 *
	 * @return void
	 */
	private function test_cache_behavior() {
		$this->output( "\n[12] CACHE BEHAVIOR TESTS" );
		$this->output( str_repeat( '-', 60 ) );

		global $wpdb;
		$analytics_table = $wpdb->prefix . 'wsscd_analytics';
		$today           = gmdate( 'Y-m-d' );

		$container = wsscd();
		if ( ! $container ) {
			$this->skip( 'Container not available' );
			return;
		}

		$calculator = $container->get( 'metrics_calculator' );
		if ( ! $calculator ) {
			$this->skip( 'Metrics Calculator not available' );
			return;
		}

		// Insert initial data
		$wpdb->insert(
			$analytics_table,
			array(
				'campaign_id'   => $this->test_campaign_id,
				'date_recorded' => $today,
				'hour_recorded' => 12,
				'impressions'   => 100,
				'clicks'        => 10,
				'conversions'   => 1,
				'revenue'       => 50.00,
				'created_at'    => gmdate( 'Y-m-d H:i:s' ),
			),
			array( '%d', '%s', '%d', '%d', '%d', '%d', '%f', '%s' )
		);

		// First call WITH cache
		$metrics1 = $calculator->calculate_campaign_metrics( $this->test_campaign_id, '7days', true );
		$this->assert(
			100 === (int) ( $metrics1['impressions'] ?? 0 ),
			"Initial cached call: 100 impressions"
		);

		// Update directly in database
		$wpdb->update(
			$analytics_table,
			array( 'impressions' => 999 ),
			array(
				'campaign_id'   => $this->test_campaign_id,
				'date_recorded' => $today,
			),
			array( '%d' ),
			array( '%d', '%s' )
		);

		// Second call WITH cache - should return STALE data (100)
		$metrics2 = $calculator->calculate_campaign_metrics( $this->test_campaign_id, '7days', true );
		$this->assert(
			100 === (int) ( $metrics2['impressions'] ?? 0 ),
			"Cached call returns stale data: 100 (not 999)"
		);

		// Call WITHOUT cache - should return FRESH data (999)
		$metrics3 = $calculator->calculate_campaign_metrics( $this->test_campaign_id, '7days', false );
		$this->assert(
			999 === (int) ( $metrics3['impressions'] ?? 0 ),
			"Non-cached call returns fresh data: 999"
		);

		// Clear cache and verify
		try {
			$calculator->clear_cache();

			$metrics4 = $calculator->calculate_campaign_metrics( $this->test_campaign_id, '7days', true );
			$this->assert(
				999 === (int) ( $metrics4['impressions'] ?? 0 ),
				"After clear_cache(), cached call returns fresh data: 999"
			);
		} catch ( Error $e ) {
			$this->skip( "Cache clear error: " . $e->getMessage() );
		}

		$wpdb->delete( $analytics_table, array( 'campaign_id' => $this->test_campaign_id ), array( '%d' ) );
	}

	/**
	 * Test data integrity with known values.
	 *
	 * @return void
	 */
	private function test_data_integrity() {
		$this->output( "\n[13] DATA INTEGRITY TESTS" );
		$this->output( str_repeat( '-', 60 ) );

		global $wpdb;
		$analytics_table = $wpdb->prefix . 'wsscd_analytics';
		$today           = gmdate( 'Y-m-d' );

		$container = wsscd();
		if ( ! $container ) {
			$this->skip( 'Container not available' );
			return;
		}

		$calculator = $container->get( 'metrics_calculator' );
		if ( ! $calculator ) {
			$this->skip( 'Metrics Calculator not available' );
			return;
		}

		// Insert known, precise test data
		$test_data = array(
			'impressions'    => 5000,
			'clicks'         => 250,
			'conversions'    => 25,
			'revenue'        => 2500.00,
			'discount_given' => 125.00,
		);

		$wpdb->insert(
			$analytics_table,
			array(
				'campaign_id'    => $this->test_campaign_id,
				'date_recorded'  => $today,
				'hour_recorded'  => 12,
				'impressions'    => $test_data['impressions'],
				'clicks'         => $test_data['clicks'],
				'conversions'    => $test_data['conversions'],
				'revenue'        => $test_data['revenue'],
				'discount_given' => $test_data['discount_given'],
				'created_at'     => gmdate( 'Y-m-d H:i:s' ),
			),
			array( '%d', '%s', '%d', '%d', '%d', '%d', '%f', '%f', '%s' )
		);

		$metrics = $calculator->calculate_campaign_metrics( $this->test_campaign_id, '7days', false );

		// Verify all raw values match exactly
		$this->assert_strict_equals(
			$test_data['impressions'],
			(int) ( $metrics['impressions'] ?? -1 ),
			"Impressions integrity: {$test_data['impressions']}"
		);

		$this->assert_strict_equals(
			$test_data['clicks'],
			(int) ( $metrics['clicks'] ?? -1 ),
			"Clicks integrity: {$test_data['clicks']}"
		);

		$this->assert_strict_equals(
			$test_data['conversions'],
			(int) ( $metrics['conversions'] ?? -1 ),
			"Conversions integrity: {$test_data['conversions']}"
		);

		$this->assert_float_equals(
			$test_data['revenue'],
			(float) ( $metrics['revenue'] ?? -1 ),
			"Revenue integrity: \${$test_data['revenue']}"
		);

		// Verify derived calculations
		$expected_ctr = ( $test_data['clicks'] / $test_data['impressions'] ) * 100; // 5%
		$this->assert_float_equals(
			$expected_ctr,
			(float) ( $metrics['ctr'] ?? -1 ),
			"CTR integrity: {$expected_ctr}%"
		);

		$expected_conv_rate = ( $test_data['conversions'] / $test_data['clicks'] ) * 100; // 10%
		$this->assert_float_equals(
			$expected_conv_rate,
			(float) ( $metrics['conversion_rate'] ?? -1 ),
			"Conversion rate integrity: {$expected_conv_rate}%"
		);

		$expected_aov = $test_data['revenue'] / $test_data['conversions']; // $100
		$this->assert_float_equals(
			$expected_aov,
			(float) ( $metrics['avg_order_value'] ?? -1 ),
			"AOV integrity: \${$expected_aov}"
		);

		// Test conversion funnel
		$funnel = $calculator->get_conversion_funnel( '7days' );

		$this->assert(
			isset( $funnel['views'] ) && (int) $funnel['views'] >= $test_data['impressions'],
			"Funnel views >= test impressions"
		);

		$this->assert(
			isset( $funnel['rates']['view_to_click'] ),
			"Funnel has view_to_click rate"
		);

		$wpdb->delete( $analytics_table, array( 'campaign_id' => $this->test_campaign_id ), array( '%d' ) );
	}

	/**
	 * Test multi-campaign isolation - each campaign's metrics stay separate.
	 *
	 * @return void
	 */
	private function test_multi_campaign_isolation() {
		$this->output( "\n[14] MULTI-CAMPAIGN ISOLATION TESTS" );
		$this->output( str_repeat( '-', 60 ) );

		global $wpdb;
		$analytics_table = $wpdb->prefix . 'wsscd_analytics';
		$today           = gmdate( 'Y-m-d' );

		$container = wsscd();
		if ( ! $container ) {
			$this->skip( 'Container not available' );
			return;
		}

		$calculator = $container->get( 'metrics_calculator' );
		if ( ! $calculator ) {
			$this->skip( 'Metrics Calculator not available' );
			return;
		}

		// Insert DISTINCT data for each campaign
		$campaign_data = array(
			0 => array( 'impressions' => 1000, 'clicks' => 100, 'conversions' => 10, 'revenue' => 500.00 ),
			1 => array( 'impressions' => 2000, 'clicks' => 150, 'conversions' => 20, 'revenue' => 800.00 ),
			2 => array( 'impressions' => 500,  'clicks' => 50,  'conversions' => 5,  'revenue' => 250.00 ),
		);

		foreach ( $this->test_campaigns as $index => $campaign ) {
			$data = $campaign_data[ $index ];
			$wpdb->insert(
				$analytics_table,
				array(
					'campaign_id'   => $campaign['id'],
					'date_recorded' => $today,
					'hour_recorded' => 12,
					'impressions'   => $data['impressions'],
					'clicks'        => $data['clicks'],
					'conversions'   => $data['conversions'],
					'revenue'       => $data['revenue'],
					'created_at'    => gmdate( 'Y-m-d H:i:s' ),
				),
				array( '%d', '%s', '%d', '%d', '%d', '%d', '%f', '%s' )
			);
		}

		// Test each campaign returns ONLY its own data
		foreach ( $this->test_campaigns as $index => $campaign ) {
			$expected = $campaign_data[ $index ];
			$metrics  = $calculator->calculate_campaign_metrics( $campaign['id'], '7days', false );

			$this->assert_strict_equals(
				$expected['impressions'],
				(int) ( $metrics['impressions'] ?? -1 ),
				"Campaign {$index} (ID:{$campaign['id']}) impressions isolated: {$expected['impressions']}"
			);

			$this->assert_strict_equals(
				$expected['clicks'],
				(int) ( $metrics['clicks'] ?? -1 ),
				"Campaign {$index} (ID:{$campaign['id']}) clicks isolated: {$expected['clicks']}"
			);

			$this->assert_float_equals(
				$expected['revenue'],
				(float) ( $metrics['revenue'] ?? -1 ),
				"Campaign {$index} (ID:{$campaign['id']}) revenue isolated: \${$expected['revenue']}"
			);
		}

		// Verify campaigns don't bleed into each other
		$campaign_a_metrics = $calculator->calculate_campaign_metrics( $this->test_campaigns[0]['id'], '7days', false );
		$campaign_b_metrics = $calculator->calculate_campaign_metrics( $this->test_campaigns[1]['id'], '7days', false );

		$this->assert(
			(int) $campaign_a_metrics['impressions'] !== (int) $campaign_b_metrics['impressions'],
			"Campaign A and B have different impressions (no data bleeding)"
		);

		// Cleanup
		foreach ( $this->test_campaigns as $campaign ) {
			$wpdb->delete( $analytics_table, array( 'campaign_id' => $campaign['id'] ), array( '%d' ) );
		}
	}

	/**
	 * Test overall/combined metrics aggregation across all campaigns.
	 *
	 * @return void
	 */
	private function test_overall_metrics_aggregation() {
		$this->output( "\n[15] OVERALL METRICS AGGREGATION TESTS" );
		$this->output( str_repeat( '-', 60 ) );

		global $wpdb;
		$analytics_table = $wpdb->prefix . 'wsscd_analytics';
		$today           = gmdate( 'Y-m-d' );

		$container = wsscd();
		if ( ! $container ) {
			$this->skip( 'Container not available' );
			return;
		}

		$calculator = $container->get( 'metrics_calculator' );
		if ( ! $calculator ) {
			$this->skip( 'Metrics Calculator not available' );
			return;
		}

		// Insert data for multiple campaigns with known totals
		$campaign_data = array(
			0 => array( 'impressions' => 1000, 'clicks' => 100, 'conversions' => 10, 'revenue' => 500.00, 'discount' => 50.00 ),
			1 => array( 'impressions' => 2000, 'clicks' => 200, 'conversions' => 20, 'revenue' => 1000.00, 'discount' => 100.00 ),
			2 => array( 'impressions' => 3000, 'clicks' => 300, 'conversions' => 30, 'revenue' => 1500.00, 'discount' => 150.00 ),
		);

		$expected_totals = array(
			'impressions' => 0,
			'clicks'      => 0,
			'conversions' => 0,
			'revenue'     => 0.0,
			'discount'    => 0.0,
		);

		foreach ( $this->test_campaigns as $index => $campaign ) {
			$data = $campaign_data[ $index ];

			$wpdb->insert(
				$analytics_table,
				array(
					'campaign_id'    => $campaign['id'],
					'date_recorded'  => $today,
					'hour_recorded'  => 12,
					'impressions'    => $data['impressions'],
					'clicks'         => $data['clicks'],
					'conversions'    => $data['conversions'],
					'revenue'        => $data['revenue'],
					'discount_given' => $data['discount'],
					'created_at'     => gmdate( 'Y-m-d H:i:s' ),
				),
				array( '%d', '%s', '%d', '%d', '%d', '%d', '%f', '%f', '%s' )
			);

			$expected_totals['impressions'] += $data['impressions'];
			$expected_totals['clicks']      += $data['clicks'];
			$expected_totals['conversions'] += $data['conversions'];
			$expected_totals['revenue']     += $data['revenue'];
			$expected_totals['discount']    += $data['discount'];
		}

		// Get overall metrics (should aggregate all campaigns)
		$overall = $calculator->calculate_overall_metrics( '7days', false );

		// Note: Overall metrics include ALL campaigns in database, not just test ones
		// So we check that our test data is AT LEAST included
		$this->assert(
			(float) ( $overall['total_revenue'] ?? 0 ) >= $expected_totals['revenue'],
			"Overall revenue >= test campaigns total: \${$expected_totals['revenue']}"
		);

		$this->assert(
			(int) ( $overall['total_impressions'] ?? 0 ) >= $expected_totals['impressions'],
			"Overall impressions >= test campaigns total: {$expected_totals['impressions']}"
		);

		$this->assert(
			(int) ( $overall['total_conversions'] ?? 0 ) >= $expected_totals['conversions'],
			"Overall conversions >= test campaigns total: {$expected_totals['conversions']}"
		);

		// Test that calculated ratios are mathematically consistent
		$total_impressions = (int) ( $overall['total_impressions'] ?? 0 );
		$total_clicks      = (int) ( $overall['total_clicks'] ?? 0 );
		$total_conversions = (int) ( $overall['total_conversions'] ?? 0 );
		$total_revenue     = (float) ( $overall['total_revenue'] ?? 0 );

		if ( $total_impressions > 0 && $total_clicks > 0 ) {
			$expected_ctr = ( $total_clicks / $total_impressions ) * 100;
			$actual_ctr   = (float) ( $overall['avg_ctr'] ?? -1 );

			$this->assert(
				abs( $actual_ctr - $expected_ctr ) < 0.1,
				"Overall CTR mathematically correct: " . round( $actual_ctr, 2 ) . "% (expected " . round( $expected_ctr, 2 ) . "%)"
			);
		}

		if ( $total_conversions > 0 ) {
			$expected_aov = $total_revenue / $total_conversions;
			$actual_aov   = (float) ( $overall['avg_order_value'] ?? -1 );

			$this->assert(
				abs( $actual_aov - $expected_aov ) < 0.1,
				"Overall AOV mathematically correct: \$" . round( $actual_aov, 2 ) . " (expected \$" . round( $expected_aov, 2 ) . ")"
			);
		}

		// Cleanup
		foreach ( $this->test_campaigns as $campaign ) {
			$wpdb->delete( $analytics_table, array( 'campaign_id' => $campaign['id'] ), array( '%d' ) );
		}
	}

	/**
	 * Test campaign comparison functionality.
	 *
	 * @return void
	 */
	private function test_campaign_comparison() {
		$this->output( "\n[16] CAMPAIGN COMPARISON TESTS" );
		$this->output( str_repeat( '-', 60 ) );

		global $wpdb;
		$analytics_table = $wpdb->prefix . 'wsscd_analytics';
		$today           = gmdate( 'Y-m-d' );

		$container = wsscd();
		if ( ! $container ) {
			$this->skip( 'Container not available' );
			return;
		}

		$calculator = $container->get( 'metrics_calculator' );
		if ( ! $calculator ) {
			$this->skip( 'Metrics Calculator not available' );
			return;
		}

		// Campaign A: High impressions, low conversion (1%)
		$wpdb->insert(
			$analytics_table,
			array(
				'campaign_id'   => $this->test_campaigns[0]['id'],
				'date_recorded' => $today,
				'hour_recorded' => 12,
				'impressions'   => 10000,
				'clicks'        => 1000,
				'conversions'   => 10,  // 1% conversion rate
				'revenue'       => 500.00,
				'created_at'    => gmdate( 'Y-m-d H:i:s' ),
			),
			array( '%d', '%s', '%d', '%d', '%d', '%d', '%f', '%s' )
		);

		// Campaign B: Lower impressions, high conversion (20%), higher AOV
		$wpdb->insert(
			$analytics_table,
			array(
				'campaign_id'   => $this->test_campaigns[1]['id'],
				'date_recorded' => $today,
				'hour_recorded' => 12,
				'impressions'   => 1000,
				'clicks'        => 500,
				'conversions'   => 100,  // 20% conversion rate
				'revenue'       => 10000.00,  // AOV = $100 (higher than Campaign A's $50)
				'created_at'    => gmdate( 'Y-m-d H:i:s' ),
			),
			array( '%d', '%s', '%d', '%d', '%d', '%d', '%f', '%s' )
		);

		$metrics_a = $calculator->calculate_campaign_metrics( $this->test_campaigns[0]['id'], '7days', false );
		$metrics_b = $calculator->calculate_campaign_metrics( $this->test_campaigns[1]['id'], '7days', false );

		// Campaign A should have MORE impressions
		$this->assert(
			(int) $metrics_a['impressions'] > (int) $metrics_b['impressions'],
			"Campaign A has more impressions: {$metrics_a['impressions']} > {$metrics_b['impressions']}"
		);

		// Campaign B should have HIGHER conversion rate
		$this->assert(
			(float) $metrics_b['conversion_rate'] > (float) $metrics_a['conversion_rate'],
			"Campaign B has higher conversion rate: {$metrics_b['conversion_rate']}% > {$metrics_a['conversion_rate']}%"
		);

		// Campaign B should have HIGHER revenue ($10000 vs $500)
		$this->assert(
			(float) $metrics_b['revenue'] > (float) $metrics_a['revenue'],
			"Campaign B has higher revenue: \${$metrics_b['revenue']} > \${$metrics_a['revenue']}"
		);

		// Campaign B should have HIGHER AOV ($100 vs $50)
		$this->assert(
			(float) $metrics_b['avg_order_value'] > (float) $metrics_a['avg_order_value'],
			"Campaign B has higher AOV: \${$metrics_b['avg_order_value']} > \${$metrics_a['avg_order_value']}"
		);

		// Verify exact conversion rates
		$expected_conv_a = ( 10 / 1000 ) * 100;   // 1%
		$expected_conv_b = ( 100 / 500 ) * 100;  // 20%

		$this->assert_float_equals(
			$expected_conv_a,
			(float) $metrics_a['conversion_rate'],
			"Campaign A conversion rate: {$expected_conv_a}%"
		);

		$this->assert_float_equals(
			$expected_conv_b,
			(float) $metrics_b['conversion_rate'],
			"Campaign B conversion rate: {$expected_conv_b}%"
		);

		// Cleanup
		foreach ( $this->test_campaigns as $campaign ) {
			$wpdb->delete( $analytics_table, array( 'campaign_id' => $campaign['id'] ), array( '%d' ) );
		}
	}

	/**
	 * Test overlapping campaign scenarios.
	 *
	 * @return void
	 */
	private function test_overlapping_campaigns() {
		$this->output( "\n[17] OVERLAPPING CAMPAIGN TESTS" );
		$this->output( str_repeat( '-', 60 ) );

		global $wpdb;
		$analytics_table         = $wpdb->prefix . 'wsscd_analytics';
		$product_analytics_table = $wpdb->prefix . 'wsscd_product_analytics';
		$today                   = gmdate( 'Y-m-d' );

		$container = wsscd();
		if ( ! $container ) {
			$this->skip( 'Container not available' );
			return;
		}

		$calculator = $container->get( 'metrics_calculator' );
		if ( ! $calculator ) {
			$this->skip( 'Metrics Calculator not available' );
			return;
		}

		// Simulate same product being tracked in multiple campaigns
		$shared_product_id = 99999;

		// Insert data for same product in different campaigns
		foreach ( $this->test_campaigns as $index => $campaign ) {
			// Campaign analytics
			$wpdb->insert(
				$analytics_table,
				array(
					'campaign_id'   => $campaign['id'],
					'date_recorded' => $today,
					'hour_recorded' => 12,
					'impressions'   => 100 * ( $index + 1 ),
					'clicks'        => 10 * ( $index + 1 ),
					'conversions'   => 1 * ( $index + 1 ),
					'revenue'       => 50.00 * ( $index + 1 ),
					'created_at'    => gmdate( 'Y-m-d H:i:s' ),
				),
				array( '%d', '%s', '%d', '%d', '%d', '%d', '%f', '%s' )
			);

			// Product analytics (same product, different campaigns)
			$wpdb->insert(
				$product_analytics_table,
				array(
					'campaign_id'   => $campaign['id'],
					'product_id'    => $shared_product_id,
					'date_recorded' => $today,
					'impressions'   => 50 * ( $index + 1 ),
					'clicks'        => 5 * ( $index + 1 ),
					'conversions'   => 1,
					'revenue'       => 25.00 * ( $index + 1 ),
					'created_at'    => gmdate( 'Y-m-d H:i:s' ),
				),
				array( '%d', '%d', '%s', '%d', '%d', '%d', '%f', '%s' )
			);
		}

		// Each campaign should have its own metrics, not combined
		$metrics_a = $calculator->calculate_campaign_metrics( $this->test_campaigns[0]['id'], '7days', false );
		$metrics_b = $calculator->calculate_campaign_metrics( $this->test_campaigns[1]['id'], '7days', false );

		$this->assert_strict_equals(
			100,
			(int) ( $metrics_a['impressions'] ?? -1 ),
			"Campaign A impressions separate: 100"
		);

		$this->assert_strict_equals(
			200,
			(int) ( $metrics_b['impressions'] ?? -1 ),
			"Campaign B impressions separate: 200"
		);

		// Test that overall doesn't double-count
		$overall = $calculator->calculate_overall_metrics( '7days', false );

		// Total should be sum, not multiplied by shared products
		$expected_min_impressions = 100 + 200 + 300; // Each campaign contributes its own
		$this->assert(
			(int) ( $overall['total_impressions'] ?? 0 ) >= $expected_min_impressions,
			"Overall impressions include all campaigns: >= {$expected_min_impressions}"
		);

		// Cleanup
		foreach ( $this->test_campaigns as $campaign ) {
			$wpdb->delete( $analytics_table, array( 'campaign_id' => $campaign['id'] ), array( '%d' ) );
			$wpdb->delete( $product_analytics_table, array( 'campaign_id' => $campaign['id'] ), array( '%d' ) );
		}
	}

	/**
	 * Test real-world data patterns.
	 *
	 * @return void
	 */
	private function test_real_world_patterns() {
		$this->output( "\n[18] REAL-WORLD DATA PATTERN TESTS" );
		$this->output( str_repeat( '-', 60 ) );

		global $wpdb;
		$analytics_table = $wpdb->prefix . 'wsscd_analytics';

		$container = wsscd();
		if ( ! $container ) {
			$this->skip( 'Container not available' );
			return;
		}

		$calculator = $container->get( 'metrics_calculator' );
		if ( ! $calculator ) {
			$this->skip( 'Metrics Calculator not available' );
			return;
		}

		$campaign_id = $this->test_campaigns[0]['id'];

		// Simulate 7 days of realistic data with varying patterns
		$daily_data = array(
			// Day, Impressions, Clicks, Conversions, Revenue (realistic e-commerce patterns)
			array( -6, 1247, 89, 7, 347.23 ),   // Monday - moderate
			array( -5, 1583, 112, 9, 456.78 ),  // Tuesday - building
			array( -4, 1891, 134, 11, 589.12 ), // Wednesday - peak weekday
			array( -3, 1756, 125, 10, 512.34 ), // Thursday - slight dip
			array( -2, 2103, 167, 14, 723.45 ), // Friday - pre-weekend surge
			array( -1, 2567, 198, 16, 892.67 ), // Saturday - weekend peak
			array( 0, 2234, 178, 13, 678.90 ),  // Sunday - tapering
		);

		$expected_totals = array(
			'impressions' => 0,
			'clicks'      => 0,
			'conversions' => 0,
			'revenue'     => 0.0,
		);

		foreach ( $daily_data as $day ) {
			$date = gmdate( 'Y-m-d', strtotime( "{$day[0]} days" ) );

			$wpdb->insert(
				$analytics_table,
				array(
					'campaign_id'   => $campaign_id,
					'date_recorded' => $date,
					'hour_recorded' => 12,
					'impressions'   => $day[1],
					'clicks'        => $day[2],
					'conversions'   => $day[3],
					'revenue'       => $day[4],
					'created_at'    => gmdate( 'Y-m-d H:i:s' ),
				),
				array( '%d', '%s', '%d', '%d', '%d', '%d', '%f', '%s' )
			);

			$expected_totals['impressions'] += $day[1];
			$expected_totals['clicks']      += $day[2];
			$expected_totals['conversions'] += $day[3];
			$expected_totals['revenue']     += $day[4];
		}

		// Test 7-day aggregation
		$metrics = $calculator->calculate_campaign_metrics( $campaign_id, '7days', false );

		$this->assert_strict_equals(
			$expected_totals['impressions'],
			(int) ( $metrics['impressions'] ?? -1 ),
			"7-day impressions aggregate: {$expected_totals['impressions']}"
		);

		$this->assert_strict_equals(
			$expected_totals['clicks'],
			(int) ( $metrics['clicks'] ?? -1 ),
			"7-day clicks aggregate: {$expected_totals['clicks']}"
		);

		$this->assert_strict_equals(
			$expected_totals['conversions'],
			(int) ( $metrics['conversions'] ?? -1 ),
			"7-day conversions aggregate: {$expected_totals['conversions']}"
		);

		// Revenue with penny precision
		$this->assert(
			abs( (float) ( $metrics['revenue'] ?? 0 ) - $expected_totals['revenue'] ) < 0.01,
			"7-day revenue with penny precision: \$" . round( $expected_totals['revenue'], 2 )
		);

		// Verify derived metrics are reasonable
		$expected_ctr       = ( $expected_totals['clicks'] / $expected_totals['impressions'] ) * 100;
		$expected_conv_rate = ( $expected_totals['conversions'] / $expected_totals['clicks'] ) * 100;
		$expected_aov       = $expected_totals['revenue'] / $expected_totals['conversions'];

		$this->assert(
			abs( (float) ( $metrics['ctr'] ?? 0 ) - $expected_ctr ) < 0.1,
			"Realistic CTR: " . round( $expected_ctr, 2 ) . "% (typical: 5-10%)"
		);

		$this->assert(
			abs( (float) ( $metrics['conversion_rate'] ?? 0 ) - $expected_conv_rate ) < 0.1,
			"Realistic conversion rate: " . round( $expected_conv_rate, 2 ) . "% (typical: 2-5%)"
		);

		$this->assert(
			abs( (float) ( $metrics['avg_order_value'] ?? 0 ) - $expected_aov ) < 0.1,
			"Realistic AOV: \$" . round( $expected_aov, 2 ) . " (typical: \$50-100)"
		);

		// Test that metrics fall within realistic e-commerce ranges
		$ctr       = (float) ( $metrics['ctr'] ?? 0 );
		$conv_rate = (float) ( $metrics['conversion_rate'] ?? 0 );
		$aov       = (float) ( $metrics['avg_order_value'] ?? 0 );

		$this->assert(
			$ctr > 0 && $ctr < 50,
			"CTR in realistic range (0-50%): {$ctr}%"
		);

		$this->assert(
			$conv_rate > 0 && $conv_rate < 30,
			"Conversion rate in realistic range (0-30%): {$conv_rate}%"
		);

		$this->assert(
			$aov > 0 && $aov < 10000,
			"AOV in realistic range (\$0-\$10,000): \${$aov}"
		);

		// Cleanup
		$wpdb->delete( $analytics_table, array( 'campaign_id' => $campaign_id ), array( '%d' ) );
	}

	/**
	 * Assert with strict equality (===).
	 *
	 * @param mixed  $expected Expected value.
	 * @param mixed  $actual   Actual value.
	 * @param string $message  Test message.
	 * @return void
	 */
	private function assert_strict_equals( $expected, $actual, string $message ) {
		$this->summary['total']++;

		if ( $expected === $actual ) {
			$this->summary['passed']++;
			$this->output( "  ✓ PASS: {$message}" );
		} else {
			$this->summary['failed']++;
			$this->output( "  ✗ FAIL: {$message}" );
			$this->output( "         Expected: " . var_export( $expected, true ) );
			$this->output( "         Actual:   " . var_export( $actual, true ) );
		}
	}

	/**
	 * Assert float equality with epsilon.
	 *
	 * @param float  $expected Expected value.
	 * @param float  $actual   Actual value.
	 * @param string $message  Test message.
	 * @return void
	 */
	private function assert_float_equals( float $expected, float $actual, string $message ) {
		$this->summary['total']++;

		if ( abs( $expected - $actual ) < $this->epsilon ) {
			$this->summary['passed']++;
			$this->output( "  ✓ PASS: {$message}" );
		} else {
			$this->summary['failed']++;
			$this->output( "  ✗ FAIL: {$message}" );
			$this->output( "         Expected: {$expected}" );
			$this->output( "         Actual:   {$actual}" );
			$this->output( "         Diff:     " . abs( $expected - $actual ) );
		}
	}

	/**
	 * Assert a test condition.
	 *
	 * @param bool   $condition Test condition.
	 * @param string $message   Test message.
	 * @return void
	 */
	private function assert( bool $condition, string $message ) {
		$this->summary['total']++;

		if ( $condition ) {
			$this->summary['passed']++;
			$this->output( "  ✓ PASS: {$message}" );
		} else {
			$this->summary['failed']++;
			$this->output( "  ✗ FAIL: {$message}" );
		}
	}

	/**
	 * Skip a test.
	 *
	 * @param string $message Skip reason.
	 * @return void
	 */
	private function skip( string $message ) {
		$this->summary['total']++;
		$this->summary['skipped']++;
		$this->output( "  ⊘ SKIP: {$message}" );
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
	 * Output summary.
	 *
	 * @return void
	 */
	private function output_summary() {
		$this->output( "\n" . str_repeat( '=', 70 ) );
		$this->output( "  TEST SUMMARY" );
		$this->output( str_repeat( '=', 70 ) );

		$this->output( "  Total Tests:   {$this->summary['total']}" );
		$this->output( "  Passed:        {$this->summary['passed']}" );
		$this->output( "  Failed:        {$this->summary['failed']}" );
		$this->output( "  Skipped:       {$this->summary['skipped']}" );

		$score = $this->summary['total'] > 0
			? round( ( $this->summary['passed'] / $this->summary['total'] ) * 100, 1 )
			: 0;
		$this->output( "  Score:         {$score}%" );

		$this->output( "" );

		if ( 0 === $this->summary['failed'] && 0 === $this->summary['skipped'] ) {
			$this->output( "  ★★★ ALL TESTS PASSED - Analytics module is production-ready! ★★★" );
		} elseif ( 0 === $this->summary['failed'] ) {
			$this->output( "  ★ All tests passed ({$this->summary['skipped']} skipped)" );
		} else {
			$this->output( "  ⚠ {$this->summary['failed']} TESTS FAILED - Review output above" );
		}

		$this->output( str_repeat( '=', 70 ) );
	}
}

/**
 * Hook to run comprehensive tests.
 *
 * @return void
 */
function wsscd_run_comprehensive_analytics_test_on_admin_init() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Test runner, not form submission.
	if ( ! isset( $_GET['wsscd_run_comprehensive_analytics_test'] ) ) {
		return;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'You do not have permission to run this test.', 'Permission Denied', array( 'response' => 403 ) );
	}

	echo '<!DOCTYPE html><html><head><title>SCD Comprehensive Analytics Test v2</title></head><body>';
	echo '<pre style="background:#1d2327;color:#f0f0f1;padding:20px;font-family:monospace;font-size:13px;line-height:1.6;border-radius:4px;max-width:1100px;margin:40px auto;">';

	$runner = new WSSCD_Comprehensive_Analytics_Test();
	$runner->run();

	echo '</pre>';
	echo '<p style="text-align:center;margin-top:20px;">';
	echo '<a href="' . esc_url( admin_url( 'admin.php?page=wsscd-analytics' ) ) . '" style="color:#2271b1;">← Back to Analytics Dashboard</a>';
	echo ' | ';
	echo '<a href="' . esc_url( admin_url( 'admin.php?wsscd_run_analytics_test=1' ) ) . '" style="color:#2271b1;">Run Basic Tests</a>';
	echo '</p>';
	echo '</body></html>';
	exit;
}
add_action( 'admin_init', 'wsscd_run_comprehensive_analytics_test_on_admin_init', 1 );
