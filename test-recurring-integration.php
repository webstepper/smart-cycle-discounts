<?php
/**
 * Recurring Integration Test Script
 *
 * Tests complete CRUD cycle for recurring campaigns
 *
 * Usage: Run via WP-CLI or direct inclusion in WordPress admin
 * wp eval-file test-recurring-integration.php
 *
 * @package SmartCycleDiscounts
 */

if ( ! defined( 'ABSPATH' ) ) {
	// Load WordPress if running standalone
	require_once dirname( dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) ) . '/wp-load.php';
}

class SCD_Recurring_Integration_Test {

	private $test_results = array();
	private $campaign_id  = null;

	/**
	 * Run all tests
	 */
	public function run() {
		echo "\n" . str_repeat( '=', 80 ) . "\n";
		echo "RECURRING CAMPAIGN INTEGRATION TEST\n";
		echo str_repeat( '=', 80 ) . "\n\n";

		$this->test_create_recurring_campaign();
		$this->test_load_recurring_campaign();
		$this->test_edit_recurring_campaign();
		$this->test_disable_recurring();
		$this->test_cleanup();

		$this->print_results();
	}

	/**
	 * Test 1: Create Recurring Campaign
	 */
	private function test_create_recurring_campaign() {
		echo "Test 1: Create Recurring Campaign\n";
		echo str_repeat( '-', 80 ) . "\n";

		global $wpdb;

		try {
			$container = SCD_Container::get_instance();
			$compiler  = $container->get( 'SCD_Campaign_Compiler_Service' );
			$repository = $container->get( 'SCD_Campaign_Repository' );

			// Simulate wizard data with recurring settings
			$wizard_data = array(
				'name'                   => 'Test Recurring Campaign ' . time(),
				'description'            => 'Integration test for recurring system',
				'status'                 => 'active',
				'product_selection_type' => 'all_products',
				'discount_type'          => 'percentage',
				'discount_value'         => 15,
				'starts_at'              => gmdate( 'Y-m-d H:i:s', strtotime( '+1 day' ) ),
				'ends_at'                => gmdate( 'Y-m-d H:i:s', strtotime( '+8 days' ) ),
				'timezone'               => 'America/New_York',
				'enable_recurring'       => true,
				'recurrence_pattern'     => 'daily',
				'recurrence_interval'    => 1,
				'recurrence_days'        => '',
				'recurrence_end_type'    => 'never',
				'recurrence_count'       => null,
				'recurrence_end_date'    => null,
			);

			// Compile wizard data
			$campaign_data = $compiler->compile( $wizard_data );

			// Verify compiler extracted recurring fields
			$this->assert_true(
				isset( $campaign_data['enable_recurring'] ),
				'Compiler should set enable_recurring'
			);
			$this->assert_true(
				isset( $campaign_data['recurring_config'] ),
				'Compiler should create recurring_config'
			);
			$this->assert_equals(
				'daily',
				$campaign_data['recurring_config']['recurrence_pattern'] ?? '',
				'Recurring pattern should be daily'
			);

			// Save campaign
			$campaign = new SCD_Campaign( $campaign_data );
			$result   = $repository->save( $campaign );

			$this->assert_true( $result, 'Campaign save should succeed' );

			$this->campaign_id = $campaign->get_id();
			$this->assert_true( $this->campaign_id > 0, 'Campaign ID should be generated' );

			// Check database: campaigns table
			$campaign_row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT id, name, enable_recurring FROM {$wpdb->prefix}scd_campaigns WHERE id = %d",
					$this->campaign_id
				)
			);

			$this->assert_true( ! empty( $campaign_row ), 'Campaign should exist in database' );
			$this->assert_equals(
				1,
				(int) $campaign_row->enable_recurring,
				'enable_recurring should be 1 in database'
			);

			// Check database: campaign_recurring table
			$recurring_row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}scd_campaign_recurring WHERE campaign_id = %d AND parent_campaign_id = 0",
					$this->campaign_id
				)
			);

			$this->assert_true( ! empty( $recurring_row ), 'Recurring config should exist in database' );
			$this->assert_equals(
				'daily',
				$recurring_row->recurrence_pattern ?? '',
				'Recurrence pattern should be daily'
			);
			$this->assert_equals(
				1,
				(int) $recurring_row->recurrence_interval,
				'Recurrence interval should be 1'
			);

			// Check database: recurring_cache table (should have occurrences)
			$cache_count = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}scd_recurring_cache WHERE parent_campaign_id = %d",
					$this->campaign_id
				)
			);

			// Note: Cache generation depends on recurring handler being triggered
			// This may be 0 if handler hasn't run yet (async via ActionScheduler)
			echo "  Cache occurrences generated: {$cache_count}\n";

			echo "✅ Test 1 PASSED\n\n";

		} catch ( Exception $e ) {
			echo "❌ Test 1 FAILED: {$e->getMessage()}\n\n";
			$this->test_results['create'] = false;
			return;
		}

		$this->test_results['create'] = true;
	}

	/**
	 * Test 2: Load Recurring Campaign
	 */
	private function test_load_recurring_campaign() {
		echo "Test 2: Load Recurring Campaign\n";
		echo str_repeat( '-', 80 ) . "\n";

		if ( ! $this->campaign_id ) {
			echo "⏭️  Skipped (no campaign ID)\n\n";
			return;
		}

		try {
			$container  = SCD_Container::get_instance();
			$repository = $container->get( 'SCD_Campaign_Repository' );
			$compiler   = $container->get( 'SCD_Campaign_Compiler_Service' );

			// Load campaign
			$campaign = $repository->find( $this->campaign_id );

			$this->assert_true( ! empty( $campaign ), 'Campaign should load from database' );
			$this->assert_true( $campaign->get_enable_recurring(), 'enable_recurring should be true' );
			$this->assert_true(
				! empty( $campaign->get_recurring_config() ),
				'recurring_config should be loaded'
			);

			$recurring_config = $campaign->get_recurring_config();
			$this->assert_equals(
				'daily',
				$recurring_config['recurrence_pattern'] ?? '',
				'Recurrence pattern should be daily'
			);

			// Format for wizard
			$wizard_data = $compiler->format_for_wizard( $campaign->to_array() );

			$this->assert_true(
				! empty( $wizard_data['enable_recurring'] ),
				'Wizard data should have enable_recurring'
			);
			$this->assert_equals(
				'daily',
				$wizard_data['recurrence_pattern'] ?? '',
				'Wizard should have recurrence_pattern'
			);
			$this->assert_equals(
				1,
				$wizard_data['recurrence_interval'] ?? 0,
				'Wizard should have recurrence_interval'
			);

			echo "✅ Test 2 PASSED\n\n";

		} catch ( Exception $e ) {
			echo "❌ Test 2 FAILED: {$e->getMessage()}\n\n";
			$this->test_results['load'] = false;
			return;
		}

		$this->test_results['load'] = true;
	}

	/**
	 * Test 3: Edit Recurring Campaign
	 */
	private function test_edit_recurring_campaign() {
		echo "Test 3: Edit Recurring Campaign\n";
		echo str_repeat( '-', 80 ) . "\n";

		if ( ! $this->campaign_id ) {
			echo "⏭️  Skipped (no campaign ID)\n\n";
			return;
		}

		try {
			global $wpdb;

			$container  = SCD_Container::get_instance();
			$repository = $container->get( 'SCD_Campaign_Repository' );

			// Load campaign
			$campaign = $repository->find( $this->campaign_id );

			// Modify recurring config
			$campaign->set_recurring_config(
				array(
					'recurrence_pattern'  => 'weekly',
					'recurrence_interval' => 2,
					'recurrence_days'     => 'mon,wed,fri',
					'recurrence_end_type' => 'never',
					'recurrence_count'    => null,
					'recurrence_end_date' => null,
				)
			);

			// Save
			$result = $repository->save( $campaign );
			$this->assert_true( $result, 'Campaign update should succeed' );

			// Check database
			$recurring_row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}scd_campaign_recurring WHERE campaign_id = %d AND parent_campaign_id = 0",
					$this->campaign_id
				)
			);

			$this->assert_equals(
				'weekly',
				$recurring_row->recurrence_pattern ?? '',
				'Recurrence pattern should be updated to weekly'
			);
			$this->assert_equals(
				2,
				(int) $recurring_row->recurrence_interval,
				'Recurrence interval should be updated to 2'
			);

			echo "✅ Test 3 PASSED\n\n";

		} catch ( Exception $e ) {
			echo "❌ Test 3 FAILED: {$e->getMessage()}\n\n";
			$this->test_results['edit'] = false;
			return;
		}

		$this->test_results['edit'] = true;
	}

	/**
	 * Test 4: Disable Recurring
	 */
	private function test_disable_recurring() {
		echo "Test 4: Disable Recurring\n";
		echo str_repeat( '-', 80 ) . "\n";

		if ( ! $this->campaign_id ) {
			echo "⏭️  Skipped (no campaign ID)\n\n";
			return;
		}

		try {
			global $wpdb;

			$container  = SCD_Container::get_instance();
			$repository = $container->get( 'SCD_Campaign_Repository' );

			// Load campaign
			$campaign = $repository->find( $this->campaign_id );

			// Disable recurring
			$campaign->set_enable_recurring( false );
			$campaign->set_recurring_config( array() );

			// Save
			$result = $repository->save( $campaign );
			$this->assert_true( $result, 'Campaign update should succeed' );

			// Check database: enable_recurring should be 0
			$campaign_row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT enable_recurring FROM {$wpdb->prefix}scd_campaigns WHERE id = %d",
					$this->campaign_id
				)
			);

			$this->assert_equals(
				0,
				(int) $campaign_row->enable_recurring,
				'enable_recurring should be 0'
			);

			// Note: Cleanup of recurring_config table and cache happens in save_recurring_config()
			// when enable_recurring is false - implementation may vary

			echo "✅ Test 4 PASSED\n\n";

		} catch ( Exception $e ) {
			echo "❌ Test 4 FAILED: {$e->getMessage()}\n\n";
			$this->test_results['disable'] = false;
			return;
		}

		$this->test_results['disable'] = true;
	}

	/**
	 * Test Cleanup
	 */
	private function test_cleanup() {
		echo "Cleanup: Deleting test campaign\n";
		echo str_repeat( '-', 80 ) . "\n";

		if ( ! $this->campaign_id ) {
			echo "⏭️  Skipped (no campaign ID)\n\n";
			return;
		}

		try {
			global $wpdb;

			// Delete campaign
			$wpdb->delete(
				$wpdb->prefix . 'scd_campaigns',
				array( 'id' => $this->campaign_id ),
				array( '%d' )
			);

			// Delete recurring config
			$wpdb->delete(
				$wpdb->prefix . 'scd_campaign_recurring',
				array( 'campaign_id' => $this->campaign_id ),
				array( '%d' )
			);

			// Delete occurrence cache
			$wpdb->delete(
				$wpdb->prefix . 'scd_recurring_cache',
				array( 'parent_campaign_id' => $this->campaign_id ),
				array( '%d' )
			);

			echo "✅ Cleanup complete\n\n";

		} catch ( Exception $e ) {
			echo "⚠️  Cleanup failed: {$e->getMessage()}\n\n";
		}
	}

	/**
	 * Print test results summary
	 */
	private function print_results() {
		echo str_repeat( '=', 80 ) . "\n";
		echo "TEST RESULTS SUMMARY\n";
		echo str_repeat( '=', 80 ) . "\n";

		$total  = count( $this->test_results );
		$passed = count( array_filter( $this->test_results ) );

		foreach ( $this->test_results as $test => $result ) {
			$status = $result ? '✅ PASSED' : '❌ FAILED';
			echo sprintf( "%-20s %s\n", ucwords( $test ), $status );
		}

		echo "\nTotal: {$passed}/{$total} tests passed\n";

		if ( $passed === $total ) {
			echo "\n🎉 ALL TESTS PASSED - Recurring integration is working!\n";
		} else {
			echo "\n⚠️  SOME TESTS FAILED - Review implementation\n";
		}

		echo str_repeat( '=', 80 ) . "\n";
	}

	/**
	 * Assert true
	 */
	private function assert_true( $condition, $message ) {
		if ( ! $condition ) {
			throw new Exception( "Assertion failed: {$message}" );
		}
		echo "  ✓ {$message}\n";
	}

	/**
	 * Assert equals
	 */
	private function assert_equals( $expected, $actual, $message ) {
		if ( $expected !== $actual ) {
			throw new Exception(
				sprintf(
					"Assertion failed: %s\nExpected: %s\nActual: %s",
					$message,
					var_export( $expected, true ),
					var_export( $actual, true )
				)
			);
		}
		echo "  ✓ {$message}\n";
	}
}

// Run tests
$test = new SCD_Recurring_Integration_Test();
$test->run();
