<?php
/**
 * Class Test_AJAX_Routing
 *
 * Integration tests for AJAX handler registration and routing.
 * These tests verify that all AJAX handlers are properly registered with WordPress.
 *
 * @package    Smart_Cycle_Discounts
 * @subpackage Smart_Cycle_Discounts/Tests/Integration
 */

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls, PluginCheck.Security.DirectDB.UnescapedDBParameter
// This is a test file, not production code.

/**
 * Test AJAX Routing class
 *
 * Tests the AJAX routing system to ensure all handlers are registered,
 * accessible, and properly wired to WordPress AJAX hooks.
 *
 * This catches:
 * - Missing AJAX handler registrations
 * - Broken routing configuration
 * - Missing WordPress hook registrations
 * - Handler class loading failures
 */
class Test_AJAX_Routing extends WP_UnitTestCase {

	/**
	 * AJAX router instance.
	 *
	 * @var WSSCD_Ajax_Router
	 */
	private $router;

	/**
	 * Set up test environment before each test.
	 *
	 * @since 1.0.0
	 */
	public function setUp(): void {
		parent::setUp();

		// Initialize WordPress user with admin capabilities
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		// Get AJAX router instance
		// The router should be registered as a service
		$container   = Test_Container_Helper::get_container();
		$this->router = $container->get( 'ajax_router' );
	}

	/**
	 * Clean up after each test.
	 *
	 * @since 1.0.0
	 */
	public function tearDown(): void {
		// Clean up campaigns before users to avoid foreign key constraint violations
		global $wpdb;
		$campaigns_table = $wpdb->prefix . 'wsscd_campaigns';
		$wpdb->query( "DELETE FROM {$campaigns_table}" );

		parent::tearDown();
	}

	/**
	 * Test that AJAX router is properly initialized.
	 *
	 * Verifies that the AJAX router service is registered and accessible.
	 *
	 * @since 1.0.0
	 */
	public function test_ajax_router_initialized() {
		$this->assertInstanceOf(
			'WSSCD_Ajax_Router',
			$this->router,
			'AJAX router should be initialized'
		);
	}

	/**
	 * Test that critical AJAX handlers are registered.
	 *
	 * Verifies that essential AJAX actions have WordPress hooks registered.
	 *
	 * @since 1.0.0
	 */
	public function test_critical_ajax_handlers_registered() {
		// Campaign management handlers
		$this->assertTrue(
			has_action( 'wp_ajax_wsscd_save_step' ) !== false,
			'AJAX handler for wsscd_save_step must be registered'
		);

		$this->assertTrue(
			has_action( 'wp_ajax_wsscd_load_data' ) !== false,
			'AJAX handler for wsscd_load_data must be registered'
		);

		// Product search handler
		$this->assertTrue(
			has_action( 'wp_ajax_wsscd_product_search' ) !== false,
			'AJAX handler for wsscd_product_search must be registered'
		);

		// Health check handler
		$this->assertTrue(
			has_action( 'wp_ajax_wsscd_health_check' ) !== false,
			'AJAX handler for wsscd_health_check must be registered'
		);
	}

	/**
	 * Test that dashboard AJAX handlers are registered.
	 *
	 * Verifies that dashboard-related AJAX actions are registered.
	 *
	 * @since 1.0.0
	 */
	public function test_dashboard_ajax_handlers_registered() {
		// Main dashboard data
		$this->assertTrue(
			has_action( 'wp_ajax_wsscd_main_dashboard_data' ) !== false,
			'AJAX handler for wsscd_main_dashboard_data must be registered'
		);

		// Campaign health
		$this->assertTrue(
			has_action( 'wp_ajax_wsscd_campaign_health' ) !== false,
			'AJAX handler for wsscd_campaign_health must be registered'
		);

		// Planner insights
		$this->assertTrue(
			has_action( 'wp_ajax_wsscd_get_planner_insights' ) !== false,
			'AJAX handler for wsscd_get_planner_insights must be registered'
		);
	}

	/**
	 * Test that analytics AJAX handlers are registered.
	 *
	 * Verifies that analytics-related AJAX actions are registered.
	 *
	 * @since 1.0.0
	 */
	public function test_analytics_ajax_handlers_registered() {
		// Analytics overview
		$this->assertTrue(
			has_action( 'wp_ajax_wsscd_analytics_overview' ) !== false,
			'AJAX handler for wsscd_analytics_overview must be registered'
		);

		// Campaign performance (note: analytics_ prefix)
		$this->assertTrue(
			has_action( 'wp_ajax_wsscd_analytics_campaign_performance' ) !== false,
			'AJAX handler for wsscd_analytics_campaign_performance must be registered'
		);

		// Revenue trend (note: analytics_ prefix)
		$this->assertTrue(
			has_action( 'wp_ajax_wsscd_analytics_revenue_trend' ) !== false,
			'AJAX handler for wsscd_analytics_revenue_trend must be registered'
		);

		// Top products (note: analytics_ prefix)
		$this->assertTrue(
			has_action( 'wp_ajax_wsscd_analytics_top_products' ) !== false,
			'AJAX handler for wsscd_analytics_top_products must be registered'
		);

		// Activity feed (note: analytics_ prefix)
		$this->assertTrue(
			has_action( 'wp_ajax_wsscd_analytics_activity_feed' ) !== false,
			'AJAX handler for wsscd_analytics_activity_feed must be registered'
		);
	}

	/**
	 * Test that utility AJAX handlers are registered.
	 *
	 * Verifies that utility AJAX actions are registered.
	 *
	 * @since 1.0.0
	 */
	public function test_utility_ajax_handlers_registered() {
		// Session management
		$this->assertTrue(
			has_action( 'wp_ajax_wsscd_check_session' ) !== false,
			'AJAX handler for wsscd_check_session must be registered'
		);

		$this->assertTrue(
			has_action( 'wp_ajax_wsscd_session_status' ) !== false,
			'AJAX handler for wsscd_session_status must be registered'
		);

		// Cache management
		$this->assertTrue(
			has_action( 'wp_ajax_wsscd_clear_cache' ) !== false,
			'AJAX handler for wsscd_clear_cache must be registered'
		);
	}

	/**
	 * Test that AJAX handlers require authentication.
	 *
	 * Verifies that AJAX handlers are NOT registered for non-logged-in users
	 * (wp_ajax_nopriv_ hooks should not be registered for admin actions).
	 *
	 * @since 1.0.0
	 */
	public function test_ajax_handlers_require_authentication() {
		// Admin actions should NOT be available to non-logged-in users
		$this->assertFalse(
			has_action( 'wp_ajax_nopriv_wsscd_save_step' ) !== false,
			'Admin AJAX handlers should not be available to non-logged-in users'
		);

		$this->assertFalse(
			has_action( 'wp_ajax_nopriv_wsscd_load_data' ) !== false,
			'Admin AJAX handlers should not be available to non-logged-in users'
		);

		$this->assertFalse(
			has_action( 'wp_ajax_nopriv_wsscd_main_dashboard_data' ) !== false,
			'Admin AJAX handlers should not be available to non-logged-in users'
		);
	}

	/**
	 * Test that AJAX router has handler registration method.
	 *
	 * Verifies that the AJAX router provides methods for handler management.
	 *
	 * @since 1.0.0
	 */
	public function test_ajax_router_has_handler_methods() {
		// Router should have method to get handler instance
		$this->assertTrue(
			method_exists( $this->router, 'get_handler_instance' ),
			'AJAX router should have get_handler_instance method'
		);

		// Router should have route_request method
		$this->assertTrue(
			method_exists( $this->router, 'route_request' ),
			'AJAX router should have route_request method'
		);

		// Router should have register_handlers method
		$this->assertTrue(
			method_exists( $this->router, 'register_handlers' ),
			'AJAX router should have register_handlers method'
		);
	}
}
