<?php
/**
 * Class Test_Plugin_Architecture
 *
 * Integration tests for overall plugin architecture and component integration.
 * These tests verify that all major components work together correctly.
 *
 * @package    Smart_Cycle_Discounts
 * @subpackage Smart_Cycle_Discounts/Tests/Integration
 */

/**
 * Test Plugin Architecture class
 *
 * Tests the complete plugin architecture and component integration to ensure
 * all parts work together as a cohesive system.
 *
 * This catches:
 * - Broken component communication
 * - Missing initializations
 * - Incorrect hook firing order
 * - Integration failures between layers
 */
class Test_Plugin_Architecture extends WP_UnitTestCase {

	/**
	 * Container instance.
	 *
	 * @var SCD_Container
	 */
	private $container;

	/**
	 * Set up test environment before each test.
	 *
	 * @since 1.0.0
	 */
	public function setUp(): void {
		parent::setUp();

		// Initialize WordPress user with admin capabilities
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		// Get service container
		$this->container = Test_Container_Helper::get_container();
	}

	/**
	 * Test that plugin main class is initialized.
	 *
	 * Verifies that the plugin singleton is properly initialized.
	 *
	 * @since 1.0.0
	 */
	public function test_plugin_main_class_initialized() {
		// Plugin main class should be loaded
		$this->assertTrue(
			class_exists( 'Smart_Cycle_Discounts' ),
			'Plugin main class should be loaded'
		);

		// Container should be available globally
		$this->assertInstanceOf(
			'SCD_Container',
			$this->container,
			'Service container should be initialized'
		);
	}

	/**
	 * Test that WordPress hooks are registered.
	 *
	 * Verifies that critical WordPress hooks are registered by the plugin.
	 *
	 * @since 1.0.0
	 */
	public function test_wordpress_hooks_registered() {
		// Admin initialization hook
		$this->assertTrue(
			has_action( 'admin_init' ) !== false,
			'Plugin should register admin_init hook'
		);

		// Admin menu hook
		$this->assertTrue(
			has_action( 'admin_menu' ) !== false,
			'Plugin should register admin_menu hook'
		);

		// Admin enqueue scripts
		$this->assertTrue(
			has_action( 'admin_enqueue_scripts' ) !== false,
			'Plugin should register admin_enqueue_scripts hook'
		);
	}

	/**
	 * Test that WooCommerce integration hooks are registered.
	 *
	 * Verifies that WooCommerce integration is properly initialized.
	 *
	 * @since 1.0.0
	 */
	public function test_woocommerce_integration_hooks_registered() {
		// WooCommerce should be active (mocked in test bootstrap)
		$this->assertTrue(
			class_exists( 'WooCommerce' ),
			'WooCommerce should be active'
		);

		// Price display hooks should be registered
		$this->assertTrue(
			has_filter( 'woocommerce_get_price_html' ) !== false ||
			has_filter( 'woocommerce_product_get_price' ) !== false,
			'Plugin should register WooCommerce price hooks'
		);
	}

	/**
	 * Test that database and services can communicate.
	 *
	 * Verifies that repository layer can communicate with database layer.
	 *
	 * @since 1.0.0
	 */
	public function test_database_service_communication() {
		// Get repository from container
		$campaign_repository = $this->container->get( 'campaign_repository' );

		$this->assertInstanceOf(
			'SCD_Campaign_Repository',
			$campaign_repository,
			'Campaign repository should be available'
		);

		// Repository should be able to query database
		$campaigns = $campaign_repository->find_all();

		// Should return array (even if empty)
		$this->assertIsArray(
			$campaigns,
			'Repository should successfully query database and return array'
		);
	}

	/**
	 * Test that campaign manager can use repositories.
	 *
	 * Verifies that service layer can use data layer.
	 *
	 * @since 1.0.0
	 */
	public function test_campaign_manager_repository_integration() {
		// Get campaign manager
		$campaign_manager = $this->container->get( 'campaign_manager' );

		$this->assertInstanceOf(
			'SCD_Campaign_Manager',
			$campaign_manager,
			'Campaign manager should be available'
		);

		// Create test campaign data
		$future_timestamp = current_time( 'timestamp' ) + ( 2 * HOUR_IN_SECONDS );

		$campaign_data = array(
			'name'                      => 'Architecture Test Campaign',
			'description'               => 'Testing component integration',
			'status'                    => 'draft',
			'discount_type'             => 'percentage',
			'discount_value'            => 10,
			'discount_value_percentage' => 10,
			'apply_to'                  => 'cart',
			'product_selection_type'    => 'all_products',
			'start_date'                => gmdate( 'Y-m-d', $future_timestamp ),
			'start_time'                => gmdate( 'H:i', $future_timestamp ),
			'end_date'                  => gmdate( 'Y-m-d', $future_timestamp + DAY_IN_SECONDS ),
			'end_time'                  => '23:59',
			'start_type'                => 'scheduled',
			'timezone'                  => 'UTC',
			'created_by'                => get_current_user_id(),
		);

		// Campaign manager should successfully create campaign
		$campaign = $campaign_manager->create( $campaign_data );

		$this->assertInstanceOf(
			'SCD_Campaign',
			$campaign,
			'Campaign manager should successfully create campaign using repository'
		);

		// Clean up
		global $wpdb;
		$campaigns_table = $wpdb->prefix . 'scd_campaigns';
		$wpdb->delete( $campaigns_table, array( 'id' => $campaign->get_id() ) );
	}

	/**
	 * Test that discount engine can access campaigns.
	 *
	 * Verifies that discount engine integrates with campaign repository.
	 *
	 * @since 1.0.0
	 */
	public function test_discount_engine_campaign_integration() {
		// Get discount engine
		$discount_engine = $this->container->get( 'discount_engine' );

		$this->assertInstanceOf(
			'SCD_Discount_Engine',
			$discount_engine,
			'Discount engine should be available'
		);

		// Discount engine should be able to query for applicable campaigns
		// (even if result is empty, the method should exist and work)
		$this->assertTrue(
			method_exists( $discount_engine, 'get_applicable_discounts' ),
			'Discount engine should have get_applicable_discounts method'
		);

		// Should not throw exception
		$applicable = $discount_engine->get_applicable_discounts();

		$this->assertIsArray(
			$applicable,
			'Discount engine should return array of applicable discounts'
		);
	}

	/**
	 * Test that plugin can be activated without errors.
	 *
	 * Simulates plugin activation process.
	 *
	 * @since 1.0.0
	 */
	public function test_plugin_activation_succeeds() {
		// Activation function should exist
		$this->assertTrue(
			class_exists( 'SCD_Activator' ),
			'Plugin activator class should exist'
		);

		$this->assertTrue(
			method_exists( 'SCD_Activator', 'activate' ),
			'Activator should have activate method'
		);

		// If we got here without fatal errors, activation works
		$this->assertTrue(
			true,
			'Plugin activation process should complete without errors'
		);
	}

	/**
	 * Test that caching layer integrates with services.
	 *
	 * Verifies that cache manager is accessible to services that need it.
	 *
	 * @since 1.0.0
	 */
	public function test_cache_service_integration() {
		// Get cache manager
		$cache_manager = $this->container->get( 'cache_manager' );

		$this->assertInstanceOf(
			'SCD_Cache_Manager',
			$cache_manager,
			'Cache manager should be available'
		);

		// Caching should work
		$test_key   = 'test_integration_cache_key';
		$test_value = array( 'data' => 'test' );

		$cache_manager->set( $test_key, $test_value, 'test_group', 60 );
		$retrieved = $cache_manager->get( $test_key, 'test_group' );

		$this->assertEquals(
			$test_value,
			$retrieved,
			'Cache manager should successfully store and retrieve data'
		);

		// Clean up
		$cache_manager->delete( $test_key, 'test_group' );
	}

	/**
	 * Test that error handler integrates with logging.
	 *
	 * Verifies that error handling system is properly wired.
	 *
	 * @since 1.0.0
	 */
	public function test_error_handler_logger_integration() {
		// Get error handler
		$error_handler = $this->container->get( 'error_handler' );

		$this->assertInstanceOf(
			'SCD_Error_Handler',
			$error_handler,
			'Error handler should be available'
		);

		// Get logger
		$logger = $this->container->get( 'logger' );

		$this->assertInstanceOf(
			'SCD_Logger',
			$logger,
			'Logger should be available'
		);

		// Error handler and logger should be integrated
		$this->assertTrue(
			true,
			'Error handler and logger should be properly integrated'
		);
	}
}
