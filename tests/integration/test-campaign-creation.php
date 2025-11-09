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
}
