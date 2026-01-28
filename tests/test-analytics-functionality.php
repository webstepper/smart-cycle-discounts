<?php
/**
 * Analytics Functionality Test Script
 *
 * Run this script to verify analytics module is working correctly.
 *
 * Usage:
 *   - Via WP-CLI: wp eval-file wp-content/plugins/smart-cycle-discounts/tests/test-analytics-functionality.php
 *   - Via browser: Add ?wsscd_run_analytics_test=1 to any admin page URL (requires admin access)
 *
 * @package    SmartCycleDiscounts
 * @since      1.0.0
 */

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls, PluginCheck.Security.DirectDB.UnescapedDBParameter
// This is a test utility file, not production code.

// Prevent direct access - must be run through WordPress.
if ( ! defined( 'ABSPATH' ) ) {
	// Check if running via WP-CLI.
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		// WP-CLI will define ABSPATH.
	} else {
		exit( 'This script must be run through WordPress (WP-CLI or admin request).' );
	}
}

/**
 * Analytics Test Runner
 */
class WSSCD_Analytics_Test_Runner {

	/**
	 * Test results.
	 *
	 * @var array
	 */
	private $results = array();

	/**
	 * Total tests.
	 *
	 * @var int
	 */
	private $total = 0;

	/**
	 * Passed tests.
	 *
	 * @var int
	 */
	private $passed = 0;

	/**
	 * Failed tests.
	 *
	 * @var int
	 */
	private $failed = 0;

	/**
	 * Run all tests.
	 *
	 * @return void
	 */
	public function run() {
		$this->output( "\n========================================" );
		$this->output( "  SCD Analytics Module Test Suite" );
		$this->output( "========================================\n" );

		// Run test groups.
		$this->test_database_tables();
		$this->test_class_loading();
		$this->test_metrics_calculator();
		$this->test_analytics_collector();
		$this->test_ajax_handlers();

		// Output summary.
		$this->output_summary();
	}

	/**
	 * Test database tables exist.
	 *
	 * @return void
	 */
	private function test_database_tables() {
		$this->output( "\n[1] Database Tables" );
		$this->output( str_repeat( '-', 40 ) );

		global $wpdb;

		$tables = array(
			'wsscd_analytics'         => 'Analytics table',
			'wsscd_product_analytics' => 'Product analytics table',
			'wsscd_campaigns'         => 'Campaigns table',
			'wsscd_customer_usage'    => 'Customer usage table',
		);

		foreach ( $tables as $table => $description ) {
			$table_name = $wpdb->prefix . $table;
			$exists     = $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) === $table_name;

			$this->assert( $exists, "{$description} ({$table_name})" );
		}

		// Check analytics table has required columns.
		$analytics_table = $wpdb->prefix . 'wsscd_analytics';
		$columns         = $wpdb->get_col( "DESCRIBE {$analytics_table}", 0 );

		$required_columns = array( 'campaign_id', 'date_recorded', 'impressions', 'clicks', 'conversions', 'revenue', 'discount_given' );

		foreach ( $required_columns as $column ) {
			$has_column = in_array( $column, $columns, true );
			$this->assert( $has_column, "Analytics table has '{$column}' column" );
		}
	}

	/**
	 * Test class loading.
	 *
	 * @return void
	 */
	private function test_class_loading() {
		$this->output( "\n[2] Class Loading" );
		$this->output( str_repeat( '-', 40 ) );

		$classes = array(
			'WSSCD_Metrics_Calculator'    => 'Metrics Calculator',
			'WSSCD_Analytics_Collector'   => 'Analytics Collector',
			'WSSCD_Report_Generator'      => 'Report Generator',
			'WSSCD_Export_Service'        => 'Export Service',
			'WSSCD_Analytics_Helpers'     => 'Analytics Helpers Trait',
		);

		foreach ( $classes as $class => $description ) {
			// For traits, use trait_exists.
			if ( false !== strpos( $class, 'Trait' ) || false !== strpos( $class, 'Helpers' ) ) {
				$exists = trait_exists( $class );
			} else {
				$exists = class_exists( $class );
			}

			$this->assert( $exists, "{$description} ({$class})" );
		}
	}

	/**
	 * Test Metrics Calculator.
	 *
	 * @return void
	 */
	private function test_metrics_calculator() {
		$this->output( "\n[3] Metrics Calculator" );
		$this->output( str_repeat( '-', 40 ) );

		// Get container instance.
		if ( ! function_exists( 'wsscd' ) ) {
			$this->assert( false, 'WSSCD container function exists' );
			return;
		}

		try {
			$container = wsscd();
			$this->assert( true, 'SCD container accessible' );

			// Try to get metrics calculator.
			if ( method_exists( $container, 'get' ) ) {
				$calculator = $container->get( 'metrics_calculator' );
				$this->assert( null !== $calculator, 'Metrics Calculator instantiated' );

				if ( $calculator ) {
					// Test method exists.
					$methods = array(
						'calculate_campaign_metrics',
						'calculate_overall_metrics',
						'calculate_realtime_metrics',
						'get_top_campaigns',
						'get_conversion_funnel',
						'get_revenue_trend',
						'clear_cache',
					);

					foreach ( $methods as $method ) {
						$has_method = method_exists( $calculator, $method );
						$this->assert( $has_method, "Has method: {$method}()" );
					}

					// Test calculate_overall_metrics.
					$metrics = $calculator->calculate_overall_metrics( '7days', false );
					$this->assert( is_array( $metrics ), 'calculate_overall_metrics() returns array' );

					// Check metrics has expected keys.
					$expected_keys = array( 'total_impressions', 'total_clicks', 'total_conversions', 'total_revenue' );
					foreach ( $expected_keys as $key ) {
						$has_key = isset( $metrics[ $key ] ) || array_key_exists( $key, $metrics );
						$this->assert( $has_key, "Metrics contains '{$key}'" );
					}

					// Test get_top_campaigns.
					$top_campaigns = $calculator->get_top_campaigns( '30days', 5 );
					$this->assert( is_array( $top_campaigns ), 'get_top_campaigns() returns array' );

					// Test get_conversion_funnel.
					$funnel = $calculator->get_conversion_funnel( '30days' );
					$this->assert( is_array( $funnel ), 'get_conversion_funnel() returns array' );
					$this->assert( isset( $funnel['rates'] ), 'Funnel contains rates' );
				}
			} else {
				$this->assert( false, 'Container has get() method' );
			}
		} catch ( Exception $e ) {
			$this->assert( false, 'Metrics Calculator tests: ' . $e->getMessage() );
		}
	}

	/**
	 * Test Analytics Collector.
	 *
	 * @return void
	 */
	private function test_analytics_collector() {
		$this->output( "\n[4] Analytics Collector" );
		$this->output( str_repeat( '-', 40 ) );

		try {
			$container = wsscd();

			if ( method_exists( $container, 'get' ) ) {
				$collector = $container->get( 'analytics_collector' );
				$this->assert( null !== $collector, 'Analytics Collector instantiated' );

				if ( $collector ) {
					// Test methods exist.
					$methods = array(
						'track_impression',
						'track_click',
						'track_purchase_complete',
						'get_revenue_trend',
						'get_daily_metrics',
						'get_campaign_performance',
					);

					foreach ( $methods as $method ) {
						$has_method = method_exists( $collector, $method );
						$this->assert( $has_method, "Has method: {$method}()" );
					}

					// Test get_revenue_trend.
					$trend = $collector->get_revenue_trend( '7days', 'daily', 0 );
					$this->assert( is_array( $trend ), 'get_revenue_trend() returns array' );
					$this->assert( isset( $trend['labels'] ), 'Revenue trend has labels' );
					$this->assert( isset( $trend['values'] ), 'Revenue trend has values' );

					// Test get_daily_metrics.
					$daily = $collector->get_daily_metrics( '7days' );
					$this->assert( is_array( $daily ), 'get_daily_metrics() returns array' );
				}
			}
		} catch ( Exception $e ) {
			$this->assert( false, 'Analytics Collector tests: ' . $e->getMessage() );
		}
	}

	/**
	 * Test AJAX handlers exist.
	 *
	 * @return void
	 */
	private function test_ajax_handlers() {
		$this->output( "\n[6] AJAX Handlers" );
		$this->output( str_repeat( '-', 40 ) );

		$handlers = array(
			'WSSCD_Overview_Handler'            => 'Overview Handler',
			'WSSCD_Revenue_Trend_Handler'       => 'Revenue Trend Handler',
			'WSSCD_Campaign_Performance_Handler' => 'Campaign Performance Handler',
			'WSSCD_Top_Products_Handler'        => 'Top Products Handler',
			'WSSCD_Activity_Feed_Handler'       => 'Activity Feed Handler',
			'WSSCD_Export_Handler'              => 'Export Handler',
		);

		foreach ( $handlers as $class => $description ) {
			$exists = class_exists( $class );
			$this->assert( $exists, "{$description} ({$class})" );

			if ( $exists ) {
				// Check extends abstract handler.
				$reflection = new ReflectionClass( $class );
				$parent     = $reflection->getParentClass();

				if ( $parent ) {
					$extends_abstract = 'WSSCD_Abstract_Analytics_Handler' === $parent->getName();
					$this->assert( $extends_abstract, "{$class} extends Abstract Handler" );
				}

				// Check has handle method.
				$has_handle = method_exists( $class, 'handle' );
				$this->assert( $has_handle, "{$class} has handle() method" );
			}
		}
	}

	/**
	 * Assert a condition.
	 *
	 * @param bool   $condition Test condition.
	 * @param string $message   Test description.
	 * @return void
	 */
	private function assert( $condition, $message ) {
		$this->total++;

		if ( $condition ) {
			$this->passed++;
			$this->output( "  ✓ PASS: {$message}" );
		} else {
			$this->failed++;
			$this->output( "  ✗ FAIL: {$message}" );
		}

		$this->results[] = array(
			'message' => $message,
			'passed'  => $condition,
		);
	}

	/**
	 * Output message.
	 *
	 * @param string $message Message to output.
	 * @return void
	 */
	private function output( $message ) {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::log( $message );
		} else {
			echo esc_html( $message ) . "\n";
		}
	}

	/**
	 * Output test summary.
	 *
	 * @return void
	 */
	private function output_summary() {
		$this->output( "\n========================================" );
		$this->output( "  Test Summary" );
		$this->output( "========================================" );
		$this->output( "  Total:  {$this->total}" );
		$this->output( "  Passed: {$this->passed}" );
		$this->output( "  Failed: {$this->failed}" );

		$percentage = $this->total > 0 ? round( ( $this->passed / $this->total ) * 100, 1 ) : 0;
		$this->output( "  Score:  {$percentage}%" );

		if ( 0 === $this->failed ) {
			$this->output( "\n  ★ All tests passed! Analytics module is functional." );
		} else {
			$this->output( "\n  ⚠ Some tests failed. Review the output above." );
		}

		$this->output( "========================================\n" );
	}

	/**
	 * Get results.
	 *
	 * @return array
	 */
	public function get_results() {
		return array(
			'total'   => $this->total,
			'passed'  => $this->passed,
			'failed'  => $this->failed,
			'results' => $this->results,
		);
	}
}

// Run tests if called directly or via WP-CLI.
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	$runner = new WSSCD_Analytics_Test_Runner();
	$runner->run();
}

/**
 * Hook into admin_init to run tests when parameter is present.
 */
function wsscd_run_analytics_test_on_admin_init() {
	if ( ! isset( $_GET['wsscd_run_analytics_test'] ) ) {
		return;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'You do not have permission to run this test.', 'Permission Denied', array( 'response' => 403 ) );
	}

	// Verify nonce for security (optional but recommended).
	// For simplicity, we just check capability.

	echo '<!DOCTYPE html><html><head><title>SCD Analytics Test</title></head><body>';
	echo '<pre style="background:#1d2327;color:#f0f0f1;padding:20px;font-family:monospace;font-size:13px;line-height:1.6;border-radius:4px;max-width:900px;margin:40px auto;">';

	$runner = new WSSCD_Analytics_Test_Runner();
	$runner->run();

	echo '</pre>';
	echo '<p style="text-align:center;margin-top:20px;"><a href="' . esc_url( admin_url( 'admin.php?page=wsscd-analytics' ) ) . '" style="color:#2271b1;">← Back to Analytics Dashboard</a></p>';
	echo '</body></html>';
	exit;
}
add_action( 'admin_init', 'wsscd_run_analytics_test_on_admin_init', 1 );
