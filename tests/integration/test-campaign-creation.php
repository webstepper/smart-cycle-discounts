<?php
/**
 * Class Test_Campaign_Creation
 *
 * Integration tests for campaign creation workflow.
 * These tests verify the complete end-to-end campaign creation process.
 *
 * @package    Smart_Cycle_Discounts
 * @subpackage Smart_Cycle_Discounts/Tests/Integration
 */

/**
 * Test Campaign Creation class
 *
 * Tests the complete campaign creation workflow from wizard steps to database storage.
 * These integration tests would have caught the schedule datetime bug and field name mismatches.
 */
class Test_Campaign_Creation extends WP_UnitTestCase {

	/**
	 * Container instance.
	 *
	 * @var SCD_Container
	 */
	private $container;

	/**
	 * Campaign manager instance.
	 *
	 * @var SCD_Campaign_Manager
	 */
	private $campaign_manager;

	/**
	 * Campaign repository instance.
	 *
	 * @var SCD_Campaign_Repository
	 */
	private $campaign_repository;

	/**
	 * Set up test environment before each test.
	 *
	 * @since 1.0.0
	 */
	public function setUp(): void {
		parent::setUp();

		// Initialize WordPress user with admin capabilities
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		// Get service container using test helper (provides multiple fallback strategies)
		$this->container = Test_Container_Helper::get_container();

		// Get campaign manager and repository
		$this->campaign_manager    = $this->container->get( 'campaign_manager' );
		$this->campaign_repository = $this->container->get( 'campaign_repository' );
	}

	/**
	 * Clean up after each test.
	 *
	 * @since 1.0.0
	 */
	public function tearDown(): void {
		// Clean up campaigns first (before users due to foreign key constraint)
		global $wpdb;
		$campaigns_table = $wpdb->prefix . 'scd_campaigns';
		$wpdb->query( "DELETE FROM {$campaigns_table} WHERE name LIKE 'Test Campaign%'" );

		parent::tearDown();
	}

	/**
	 * Clean up after all tests in this class.
	 *
	 * Ensures campaigns are deleted before users (foreign key constraint).
	 *
	 * @since 1.0.0
	 */
	public static function tearDownAfterClass(): void {
		// Clean up any remaining test campaigns before WordPress deletes users
		global $wpdb;
		$campaigns_table = $wpdb->prefix . 'scd_campaigns';
		$wpdb->query( "DELETE FROM {$campaigns_table} WHERE name LIKE 'Test Campaign%'" );

		parent::tearDownAfterClass();
	}

	/**
	 * Test creating a scheduled campaign with future datetime.
	 *
	 * This test verifies the datetime bug fix where scheduled campaigns
	 * were incorrectly rejected as "past dates" when they were in the future.
	 *
	 * @since 1.0.0
	 */
	public function test_create_scheduled_campaign() {
		// Setup: Create campaign data with future datetime
		$future_timestamp = current_time( 'timestamp' ) + ( 2 * HOUR_IN_SECONDS );
		$start_date       = gmdate( 'Y-m-d', $future_timestamp );
		$start_time       = gmdate( 'H:i', $future_timestamp );
		$end_timestamp    = $future_timestamp + ( 7 * DAY_IN_SECONDS );
		$end_date         = gmdate( 'Y-m-d', $end_timestamp );
		$end_time         = '23:59';

		$campaign_data = array(
			'name'                      => 'Test Campaign Scheduled',
			'description'               => 'Test scheduled campaign creation',
			'status'                    => 'draft',
			'discount_type'             => 'percentage',
			'discount_value'            => 25,
			'discount_value_percentage' => 25, // Type-specific field (post-refactoring)
			'apply_to'                  => 'cart',
			'product_selection_type'    => 'all_products',
			'start_date'                => $start_date,
			'start_time'                => $start_time,
			'end_date'                  => $end_date,
			'end_time'                  => $end_time,
			'start_type'                => 'scheduled', // Important: scheduled, not immediate
			'timezone'                  => 'UTC',
			'created_by'                => get_current_user_id(),
		);

		// Act: Create campaign
		$campaign = $this->campaign_manager->create( $campaign_data );

		// Assert: Campaign should be created successfully (returns Campaign object, not WP_Error)
		$this->assertNotInstanceOf(
			'WP_Error',
			$campaign,
			'Scheduled campaign with future datetime should be created successfully'
		);

		$this->assertInstanceOf(
			'SCD_Campaign',
			$campaign,
			'Campaign manager should return SCD_Campaign object'
		);

		$this->assertGreaterThan( 0, $campaign->get_id(), 'Campaign should have a valid ID' );

		// Verify campaign properties
		$this->assertEquals( 'Test Campaign Scheduled', $campaign->get_name() );
		$this->assertEquals( 'draft', $campaign->get_status(), 'Scheduled campaign should remain as draft until start time' );
		$this->assertEquals( 'percentage', $campaign->get_discount_type() );
		$this->assertEquals( 25.0, $campaign->get_discount_value() );

		// Verify campaign was persisted to database
		$saved_campaign = $this->campaign_repository->find( $campaign->get_id() );
		$this->assertNotNull( $saved_campaign, 'Campaign should be stored in database' );
		$this->assertEquals( 'Test Campaign Scheduled', $saved_campaign->get_name() );
	}

	/**
	 * Test campaign status logic for scheduled campaigns.
	 *
	 * Verifies that campaign status is set correctly based on start_type.
	 * This prevents the bug where scheduled campaigns activated immediately.
	 *
	 * @since 1.0.0
	 */
	public function test_campaign_status_correct() {
		// Setup: Create TWO campaigns - one immediate, one scheduled
		$future_timestamp = current_time( 'timestamp' ) + ( 2 * HOUR_IN_SECONDS );
		$start_date       = gmdate( 'Y-m-d', $future_timestamp );
		$start_time       = gmdate( 'H:i', $future_timestamp );
		$end_date         = gmdate( 'Y-m-d', $future_timestamp + ( 7 * DAY_IN_SECONDS ) );
		$end_time         = '23:59';

		// Campaign 1: Immediate activation
		$immediate_data = array(
			'name'                      => 'Test Campaign Immediate',
			'description'               => 'Test immediate campaign',
			'status'                    => 'draft',
			'discount_type'             => 'percentage',
			'discount_value'            => 20,
			'discount_value_percentage' => 20,
			'apply_to'                  => 'cart',
			'product_selection_type'    => 'all_products',
			'start_date'                => gmdate( 'Y-m-d' ),
			'start_time'                => gmdate( 'H:i' ),
			'end_date'                  => $end_date,
			'end_time'                  => $end_time,
			'start_type'                => 'immediate', // Activate now
			'timezone'                  => 'UTC',
			'created_by'                => get_current_user_id(),
		);

		// Campaign 2: Scheduled activation
		$scheduled_data = array(
			'name'                      => 'Test Campaign Scheduled Status',
			'description'               => 'Test scheduled campaign status',
			'status'                    => 'draft',
			'discount_type'             => 'percentage',
			'discount_value'            => 15,
			'discount_value_percentage' => 15,
			'apply_to'                  => 'cart',
			'product_selection_type'    => 'all_products',
			'start_date'                => $start_date,
			'start_time'                => $start_time,
			'end_date'                  => $end_date,
			'end_time'                  => $end_time,
			'start_type'                => 'scheduled', // Schedule for future
			'timezone'                  => 'UTC',
			'created_by'                => get_current_user_id(),
		);

		// Act: Create both campaigns
		$immediate_campaign = $this->campaign_manager->create( $immediate_data );
		$scheduled_campaign = $this->campaign_manager->create( $scheduled_data );

		// Assert: Both should be created successfully (Campaign objects, not WP_Error)
		$this->assertInstanceOf(
			'SCD_Campaign',
			$immediate_campaign,
			'Immediate campaign should be created'
		);

		$this->assertInstanceOf(
			'SCD_Campaign',
			$scheduled_campaign,
			'Scheduled campaign should be created'
		);

		// Verify scheduled campaign is NOT active yet (should be draft)
		$this->assertEquals(
			'draft',
			$scheduled_campaign->get_status(),
			'Scheduled campaign should be draft (not active) before start time'
		);

		// Verify campaigns have different properties
		$this->assertEquals( 'Test Campaign Immediate', $immediate_campaign->get_name() );
		$this->assertEquals( 'Test Campaign Scheduled Status', $scheduled_campaign->get_name() );
		$this->assertEquals( 20.0, $immediate_campaign->get_discount_value() );
		$this->assertEquals( 15.0, $scheduled_campaign->get_discount_value() );
	}

	/**
	 * Test that field name refactoring is handled correctly.
	 *
	 * Verifies that both old and new field names work for discount values.
	 * This tests the backward compatibility fallback logic.
	 *
	 * @since 1.0.0
	 */
	public function test_field_name_backward_compatibility() {
		// Setup: Create campaign using OLD field name (discount_value)
		$future_timestamp = current_time( 'timestamp' ) + HOUR_IN_SECONDS;
		$campaign_data    = array(
			'name'                   => 'Test Campaign Old Field Name',
			'description'            => 'Test backward compatibility',
			'status'                 => 'draft',
			'discount_type'          => 'percentage',
			'discount_value'         => 30, // Old field name (pre-refactoring)
			'apply_to'               => 'cart',
			'product_selection_type' => 'all_products',
			'start_date'             => gmdate( 'Y-m-d', $future_timestamp ),
			'start_time'             => gmdate( 'H:i', $future_timestamp ),
			'end_date'               => gmdate( 'Y-m-d', $future_timestamp + DAY_IN_SECONDS ),
			'end_time'               => '23:59',
			'start_type'             => 'scheduled',
			'timezone'               => 'UTC',
			'created_by'             => get_current_user_id(),
		);

		// Act: Create campaign
		$campaign = $this->campaign_manager->create( $campaign_data );

		// Assert: Should work even with old field name (Campaign object, not WP_Error)
		$this->assertNotInstanceOf(
			'WP_Error',
			$campaign,
			'Campaign should be created with old field name (backward compatibility)'
		);

		$this->assertInstanceOf(
			'SCD_Campaign',
			$campaign,
			'Should return Campaign object'
		);

		$this->assertGreaterThan( 0, $campaign->get_id(), 'Campaign should have valid ID' );

		// Verify discount value was stored correctly
		$this->assertEquals( 30.0, $campaign->get_discount_value(), 'Discount value should be stored correctly' );
		$this->assertEquals( 'Test Campaign Old Field Name', $campaign->get_name() );
		$this->assertEquals( 'percentage', $campaign->get_discount_type() );
	}

	/**
	 * Data provider for all discount types.
	 *
	 * @return array Discount type configurations for parameterized testing.
	 */
	public function discount_types_provider() {
		return array(
			'percentage'      => array( 'percentage', array( 'discount_value_percentage' => 20 ) ),
			'fixed'           => array( 'fixed', array( 'discount_value_fixed' => 15 ) ),
			'tiered'          => array(
				'tiered',
				array(
					'tiers' => array(
						array( 'min_quantity' => 1, 'max_quantity' => 5, 'discount' => 10 ),
						array( 'min_quantity' => 6, 'max_quantity' => 999, 'discount' => 20 ),
					),
				),
			),
			'bogo'            => array(
				'bogo',
				array(
					'bogo_config' => array(
						'buy_quantity'     => 1,
						'get_quantity'     => 1,
						'discount_percent' => 100,
					),
				),
			),
			'spend_threshold' => array(
				'spend_threshold',
				array(
					'threshold_mode' => 'percentage',
					'thresholds'     => array(
						array( 'min_spend' => 50, 'discount' => 10 ),
						array( 'min_spend' => 100, 'discount' => 20 ),
					),
				),
			),
		);
	}

	/**
	 * Test creating campaigns with all discount types.
	 *
	 * Parameterized test that verifies each discount type can be created successfully.
	 *
	 * @dataProvider discount_types_provider
	 * @param string $discount_type Discount type identifier.
	 * @param array  $discount_config Type-specific configuration.
	 */
	public function test_create_campaign_with_all_discount_types( $discount_type, $discount_config ) {
		// Use data generator to get base campaign data
		$campaign_data = Test_Campaign_Data_Generator::get_base_campaign_data(
			array_merge(
				array(
					'name'           => "Test {$discount_type} Campaign",
					'discount_type'  => $discount_type,
					'discount_value' => 0, // Will be set by type-specific config
				),
				$discount_config
			)
		);

		// Create campaign
		$campaign = $this->campaign_manager->create( $campaign_data );

		// Assert campaign created successfully
		$this->assertNotInstanceOf(
			'WP_Error',
			$campaign,
			"{$discount_type} campaign should be created without errors"
		);

		$this->assertInstanceOf(
			'SCD_Campaign',
			$campaign,
			"{$discount_type} campaign should return Campaign object"
		);

		$this->assertGreaterThan( 0, $campaign->get_id(), "{$discount_type} campaign should have valid ID" );
		$this->assertEquals( $discount_type, $campaign->get_discount_type(), 'Discount type should match' );
	}

	/**
	 * Data provider for all product selection types.
	 *
	 * @return array Product selection configurations.
	 */
	public function product_selection_types_provider() {
		return array(
			'all_products'      => array( 'all_products', array() ),
			'specific_products' => array( 'specific_products', array( 'product_ids' => array( 1, 2, 3 ) ) ),
			'random_products'   => array( 'random_products', array( 'random_count' => 10 ) ),
			'smart_selection'   => array( 'smart_selection', array( 'smart_criteria' => 'best_sellers' ) ),
		);
	}

	/**
	 * Test creating campaigns with all product selection types.
	 *
	 * @dataProvider product_selection_types_provider
	 * @param string $selection_type Product selection type.
	 * @param array  $selection_config Selection-specific configuration.
	 */
	public function test_create_campaign_with_all_product_selections( $selection_type, $selection_config ) {
		$campaign_data = Test_Campaign_Data_Generator::get_percentage_discount_data(
			array_merge(
				array(
					'name'                   => "Test {$selection_type} Campaign",
					'product_selection_type' => $selection_type,
				),
				$selection_config
			)
		);

		$campaign = $this->campaign_manager->create( $campaign_data );

		$this->assertNotInstanceOf(
			'WP_Error',
			$campaign,
			"{$selection_type} selection should work without errors"
		);

		$this->assertInstanceOf( 'SCD_Campaign', $campaign );
		$this->assertEquals( $selection_type, $campaign->get_product_selection_type() );
	}

	/**
	 * Test edge case: minimum percentage discount (0.01%).
	 */
	public function test_minimum_percentage_discount() {
		$campaign_data = Test_Campaign_Data_Generator::get_minimum_percentage_discount();
		$campaign      = $this->campaign_manager->create( $campaign_data );

		$this->assertNotInstanceOf( 'WP_Error', $campaign, 'Minimum percentage (0.01%) should be valid' );
		$this->assertEquals( 0.01, $campaign->get_discount_value() );
	}

	/**
	 * Test edge case: maximum percentage discount (100%).
	 */
	public function test_maximum_percentage_discount() {
		$campaign_data = Test_Campaign_Data_Generator::get_maximum_percentage_discount();
		$campaign      = $this->campaign_manager->create( $campaign_data );

		$this->assertNotInstanceOf( 'WP_Error', $campaign, 'Maximum percentage (100%) should be valid' );
		$this->assertEquals( 100.0, $campaign->get_discount_value() );
	}

	/**
	 * Test edge case: minimum fixed discount ($0.01).
	 */
	public function test_minimum_fixed_discount() {
		$campaign_data = Test_Campaign_Data_Generator::get_minimum_fixed_discount();
		$campaign      = $this->campaign_manager->create( $campaign_data );

		$this->assertNotInstanceOf( 'WP_Error', $campaign, 'Minimum fixed amount ($0.01) should be valid' );
		$this->assertEquals( 0.01, $campaign->get_discount_value() );
	}

	/**
	 * Test campaign with all usage limits set.
	 */
	public function test_campaign_with_usage_limits() {
		$campaign_data = Test_Campaign_Data_Generator::get_campaign_with_usage_limits();
		$campaign      = $this->campaign_manager->create( $campaign_data );

		$this->assertNotInstanceOf( 'WP_Error', $campaign, 'Campaign with usage limits should be created' );
		$this->assertInstanceOf( 'SCD_Campaign', $campaign );
	}

	/**
	 * Test campaign with minimum order requirements.
	 */
	public function test_campaign_with_minimums() {
		$campaign_data = Test_Campaign_Data_Generator::get_campaign_with_minimums();
		$campaign      = $this->campaign_manager->create( $campaign_data );

		$this->assertNotInstanceOf( 'WP_Error', $campaign, 'Campaign with minimum requirements should be created' );
		$this->assertInstanceOf( 'SCD_Campaign', $campaign );
	}

	/**
	 * Test campaign with badge enabled.
	 */
	public function test_campaign_with_badge() {
		$campaign_data = Test_Campaign_Data_Generator::get_campaign_with_badge();
		$campaign      = $this->campaign_manager->create( $campaign_data );

		$this->assertNotInstanceOf( 'WP_Error', $campaign, 'Campaign with badge should be created' );
		$this->assertInstanceOf( 'SCD_Campaign', $campaign );
	}

	/**
	 * Test stackable campaign.
	 */
	public function test_stackable_campaign() {
		$campaign_data = Test_Campaign_Data_Generator::get_stackable_campaign();
		$campaign      = $this->campaign_manager->create( $campaign_data );

		$this->assertNotInstanceOf( 'WP_Error', $campaign, 'Stackable campaign should be created' );
		$this->assertInstanceOf( 'SCD_Campaign', $campaign );
	}

	/**
	 * Test campaign excluding sale items.
	 */
	public function test_campaign_excluding_sale_items() {
		$campaign_data = Test_Campaign_Data_Generator::get_campaign_excluding_sale_items();
		$campaign      = $this->campaign_manager->create( $campaign_data );

		$this->assertNotInstanceOf( 'WP_Error', $campaign, 'Campaign excluding sale items should be created' );
		$this->assertInstanceOf( 'SCD_Campaign', $campaign );
	}

	/**
	 * Test campaign with max discount amount cap.
	 */
	public function test_campaign_with_max_discount_cap() {
		$campaign_data = Test_Campaign_Data_Generator::get_campaign_with_max_discount_cap();
		$campaign      = $this->campaign_manager->create( $campaign_data );

		$this->assertNotInstanceOf( 'WP_Error', $campaign, 'Campaign with max discount cap should be created' );
		$this->assertInstanceOf( 'SCD_Campaign', $campaign );
	}

	/**
	 * Test that past date campaigns fail validation.
	 */
	public function test_past_date_campaign_fails() {
		$campaign_data = Test_Campaign_Data_Generator::get_past_date_campaign();
		$campaign      = $this->campaign_manager->create( $campaign_data );

		// This should return WP_Error because start date is in the past
		$this->assertInstanceOf(
			'WP_Error',
			$campaign,
			'Campaign with past start date should fail validation'
		);
	}

	/**
	 * Test that invalid date ranges fail validation.
	 */
	public function test_invalid_date_range_fails() {
		$campaign_data = Test_Campaign_Data_Generator::get_invalid_date_range_campaign();
		$campaign      = $this->campaign_manager->create( $campaign_data );

		// This should return WP_Error because end date is before start date
		$this->assertInstanceOf(
			'WP_Error',
			$campaign,
			'Campaign with end date before start date should fail validation'
		);
	}
}
