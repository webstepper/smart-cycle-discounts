<?php
/**
 * Class Test_Service_Container
 *
 * Integration tests for service container registration and dependency injection.
 * These tests verify that all plugin services are properly registered and resolvable.
 *
 * @package    Smart_Cycle_Discounts
 * @subpackage Smart_Cycle_Discounts/Tests/Integration
 */

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls, PluginCheck.Security.DirectDB.UnescapedDBParameter
// This is a test file, not production code.

/**
 * Test Service Container class
 *
 * Tests the complete dependency injection container to ensure all services
 * are registered, resolvable, and properly initialized with their dependencies.
 *
 * This catches:
 * - Missing service registrations
 * - Circular dependency issues
 * - Class instantiation failures
 * - Incorrect dependency wiring
 */
class Test_Service_Container extends WP_UnitTestCase {

	/**
	 * Container instance.
	 *
	 * @var WSSCD_Container
	 */
	private $container;

	/**
	 * Set up test environment before each test.
	 *
	 * @since 1.0.0
	 */
	public function setUp(): void {
		parent::setUp();

		// Get service container using test helper
		$this->container = Test_Container_Helper::get_container();
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
	 * Test that all core services are registered.
	 *
	 * Verifies that critical services required for plugin operation
	 * are registered in the service container.
	 *
	 * @since 1.0.0
	 */
	public function test_core_services_registered() {
		// Logger (foundation service)
		$this->assertTrue(
			$this->container->has( 'logger' ),
			'Logger service must be registered'
		);

		// Database services
		$this->assertTrue(
			$this->container->has( 'database_manager' ),
			'Database manager service must be registered'
		);

		// Cache services
		$this->assertTrue(
			$this->container->has( 'cache_manager' ),
			'Cache manager service must be registered'
		);

		$this->assertTrue(
			$this->container->has( 'cache' ),
			'Cache service must be registered (backward compatibility wrapper for cache_manager)'
		);

		// Security services
		$this->assertTrue(
			$this->container->has( 'security_manager' ),
			'Security manager service must be registered'
		);

		// Error handling
		$this->assertTrue(
			$this->container->has( 'error_handler' ),
			'Error handler service must be registered'
		);
	}

	/**
	 * Test that all campaign services are registered.
	 *
	 * Verifies that campaign-related services are properly registered.
	 *
	 * @since 1.0.0
	 */
	public function test_campaign_services_registered() {
		// Campaign management
		$this->assertTrue(
			$this->container->has( 'campaign_manager' ),
			'Campaign manager service must be registered'
		);

		$this->assertTrue(
			$this->container->has( 'campaign_repository' ),
			'Campaign repository service must be registered'
		);

		// Campaign support services
		$this->assertTrue(
			$this->container->has( 'campaign.formatter' ),
			'Campaign formatter service must be registered'
		);
	}

	/**
	 * Test that all repository services are registered.
	 *
	 * Verifies that data repository services are properly registered.
	 *
	 * @since 1.0.0
	 */
	public function test_repository_services_registered() {
		// Campaign repository
		$this->assertTrue(
			$this->container->has( 'campaign_repository' ),
			'Campaign repository must be registered'
		);

		// Discount repository
		$this->assertTrue(
			$this->container->has( 'discount_repository' ),
			'Discount repository must be registered'
		);

		// Analytics repository
		$this->assertTrue(
			$this->container->has( 'analytics_repository' ),
			'Analytics repository must be registered'
		);

		// Customer usage repository
		$this->assertTrue(
			$this->container->has( 'customer_usage_repository' ),
			'Customer usage repository must be registered'
		);
	}

	/**
	 * Test that services resolve without errors.
	 *
	 * Verifies that registered services can be instantiated and their
	 * dependencies are correctly wired.
	 *
	 * @since 1.0.0
	 */
	public function test_services_resolve_successfully() {
		// Test logger resolves
		$logger = $this->container->get( 'logger' );
		$this->assertInstanceOf(
			'WSSCD_Logger',
			$logger,
			'Logger service should resolve to WSSCD_Logger instance'
		);

		// Test campaign manager resolves
		$campaign_manager = $this->container->get( 'campaign_manager' );
		$this->assertInstanceOf(
			'WSSCD_Campaign_Manager',
			$campaign_manager,
			'Campaign manager should resolve to WSSCD_Campaign_Manager instance'
		);

		// Test campaign repository resolves
		$campaign_repository = $this->container->get( 'campaign_repository' );
		$this->assertInstanceOf(
			'WSSCD_Campaign_Repository',
			$campaign_repository,
			'Campaign repository should resolve to WSSCD_Campaign_Repository instance'
		);

		// Test discount engine resolves
		$discount_engine = $this->container->get( 'discount_engine' );
		$this->assertInstanceOf(
			'WSSCD_Discount_Engine',
			$discount_engine,
			'Discount engine should resolve to WSSCD_Discount_Engine instance'
		);

		// Test product selector resolves
		$product_selector = $this->container->get( 'product_selector' );
		$this->assertInstanceOf(
			'WSSCD_Product_Selector',
			$product_selector,
			'Product selector should resolve to WSSCD_Product_Selector instance'
		);
	}

	/**
	 * Test that singleton services return same instance.
	 *
	 * Verifies that services marked as singleton always return
	 * the same instance when resolved multiple times.
	 *
	 * @since 1.0.0
	 */
	public function test_singleton_services_return_same_instance() {
		// Logger should be singleton
		$logger1 = $this->container->get( 'logger' );
		$logger2 = $this->container->get( 'logger' );

		$this->assertSame(
			$logger1,
			$logger2,
			'Logger service should return same instance (singleton)'
		);

		// Campaign manager should be singleton
		$manager1 = $this->container->get( 'campaign_manager' );
		$manager2 = $this->container->get( 'campaign_manager' );

		$this->assertSame(
			$manager1,
			$manager2,
			'Campaign manager should return same instance (singleton)'
		);

		// Cache manager should be singleton
		$cache1 = $this->container->get( 'cache_manager' );
		$cache2 = $this->container->get( 'cache_manager' );

		$this->assertSame(
			$cache1,
			$cache2,
			'Cache manager should return same instance (singleton)'
		);
	}

	/**
	 * Test that service dependencies are correctly injected.
	 *
	 * Verifies that services receive their required dependencies
	 * when instantiated by the container.
	 *
	 * @since 1.0.0
	 */
	public function test_service_dependencies_injected() {
		// Campaign manager depends on multiple services
		$campaign_manager = $this->container->get( 'campaign_manager' );

		// Verify campaign manager was created (dependencies resolved)
		$this->assertInstanceOf(
			'WSSCD_Campaign_Manager',
			$campaign_manager,
			'Campaign manager should be instantiated with all dependencies'
		);

		// If we got here without exceptions, dependencies were resolved correctly
		$this->assertTrue(
			true,
			'Service dependency injection working correctly'
		);
	}

	/**
	 * Test that container can resolve dependencies.
	 *
	 * Verifies that the container can successfully resolve service dependencies.
	 *
	 * @since 1.0.0
	 */
	public function test_container_dependency_resolution() {
		// Test that container can resolve a service with dependencies
		$campaign_manager = $this->container->get( 'campaign_manager' );

		$this->assertInstanceOf(
			'WSSCD_Campaign_Manager',
			$campaign_manager,
			'Container should successfully resolve campaign_manager with all dependencies'
		);

		// Test that container can resolve deeply nested dependencies
		$discount_applicator = $this->container->get( 'discount_applicator' );

		$this->assertInstanceOf(
			'WSSCD_Discount_Applicator',
			$discount_applicator,
			'Container should successfully resolve discount_applicator with nested dependencies'
		);
	}

	/**
	 * Test that backward compatibility services work correctly.
	 *
	 * Verifies that backward compatibility services (like 'cache' wrapping 'cache_manager')
	 * resolve to the same instance as the primary service.
	 *
	 * @since 1.0.0
	 */
	public function test_backward_compatibility_services_work() {
		// 'cache' is a backward compatibility wrapper for 'cache_manager'
		// Both should return the same singleton instance
		$cache_manager = $this->container->get( 'cache_manager' );
		$cache         = $this->container->get( 'cache' );

		$this->assertSame(
			$cache_manager,
			$cache,
			'Backward compatibility service "cache" should resolve to same instance as "cache_manager"'
		);
	}
}
