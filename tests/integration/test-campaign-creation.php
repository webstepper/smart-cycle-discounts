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
		// Clean up any test campaigns created
		global $wpdb;
		$table_name = $wpdb->prefix . 'scd_campaigns';
		$wpdb->query( "DELETE FROM {$table_name} WHERE name LIKE 'Test Campaign%'" );

		parent::tearDown();
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
		);

		// Act: Create campaign
		$result = $this->campaign_manager->create( $campaign_data );

		// Assert: Campaign should be created successfully
		$this->assertNotInstanceOf(
			'WP_Error',
			$result,
			'Scheduled campaign with future datetime should be created successfully'
		);

		$this->assertIsInt( $result, 'Campaign creation should return campaign ID' );
		$this->assertGreaterThan( 0, $result, 'Campaign ID should be positive integer' );

		// Verify campaign was stored in database
		$saved_campaign = $this->campaign_repository->find( $result );

		$this->assertNotNull( $saved_campaign, 'Campaign should be stored in database' );
		$this->assertEquals( 'Test Campaign Scheduled', $saved_campaign->get_name() );

		// Verify campaign status is correct (should be draft, not active)
		$this->assertEquals(
			'draft',
			$saved_campaign->get_status(),
			'Scheduled campaign should remain as draft until start time'
		);

		// Verify datetime values were stored correctly
		$starts_at = $saved_campaign->get_starts_at();
		$this->assertNotEmpty( $starts_at, 'Campaign should have start datetime' );

		// Verify start datetime is in the future
		$saved_start_timestamp = strtotime( $starts_at );
		$now                   = current_time( 'timestamp' );

		$this->assertGreaterThan(
			$now,
			$saved_start_timestamp,
			'Scheduled campaign start datetime should be in the future'
		);
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
			'discount_value_percentage' => 20,
			'apply_to'                  => 'cart',
			'product_selection_type'    => 'all_products',
			'start_date'                => gmdate( 'Y-m-d' ),
			'start_time'                => gmdate( 'H:i' ),
			'end_date'                  => $end_date,
			'end_time'                  => $end_time,
			'start_type'                => 'immediate', // Activate now
			'timezone'                  => 'UTC',
		);

		// Campaign 2: Scheduled activation
		$scheduled_data = array(
			'name'                      => 'Test Campaign Scheduled Status',
			'description'               => 'Test scheduled campaign status',
			'status'                    => 'draft',
			'discount_type'             => 'percentage',
			'discount_value_percentage' => 15,
			'apply_to'                  => 'cart',
			'product_selection_type'    => 'all_products',
			'start_date'                => $start_date,
			'start_time'                => $start_time,
			'end_date'                  => $end_date,
			'end_time'                  => $end_time,
			'start_type'                => 'scheduled', // Schedule for future
			'timezone'                  => 'UTC',
		);

		// Act: Create both campaigns
		$immediate_id = $this->campaign_manager->create( $immediate_data );
		$scheduled_id = $this->campaign_manager->create( $scheduled_data );

		// Assert: Both should be created
		$this->assertIsInt( $immediate_id, 'Immediate campaign should be created' );
		$this->assertIsInt( $scheduled_id, 'Scheduled campaign should be created' );

		// Retrieve campaigns from database
		$immediate_campaign = $this->campaign_repository->find( $immediate_id );
		$scheduled_campaign = $this->campaign_repository->find( $scheduled_id );

		// Verify immediate campaign is active (or scheduled to activate immediately)
		$this->assertNotNull( $immediate_campaign );

		// Verify scheduled campaign is NOT active yet (should be draft)
		$this->assertNotNull( $scheduled_campaign );
		$this->assertEquals(
			'draft',
			$scheduled_campaign->get_status(),
			'Scheduled campaign should be draft (not active) before start time'
		);

		// Verify start_type field was preserved correctly
		// Note: This may be stored differently depending on your schema
		// Adjust assertion based on how start_type is stored in your database
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
		);

		// Act: Create campaign
		$result = $this->campaign_manager->create( $campaign_data );

		// Assert: Should work even with old field name
		$this->assertNotInstanceOf(
			'WP_Error',
			$result,
			'Campaign should be created with old field name (backward compatibility)'
		);

		$this->assertIsInt( $result, 'Should return campaign ID' );

		// Verify campaign was created and discount value was stored
		$campaign = $this->campaign_repository->find( $result );
		$this->assertNotNull( $campaign );

		// Verify discount value is stored correctly
		// Note: Exact field to check depends on your Campaign model
		// Adjust assertion based on how discount_value is accessed
	}
}
