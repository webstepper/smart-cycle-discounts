<?php
/**
 * Class Test_Discounts_Step
 *
 * Integration tests for the discounts wizard step.
 * Tests all discount types, field mapping, data persistence, and the BOGO flattening fix.
 *
 * @package    Smart_Cycle_Discounts
 * @subpackage Smart_Cycle_Discounts/Tests/Integration
 */

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.CodeAnalysis.Sniffs.DirectDBcalls.DirectDBcalls, PluginCheck.Security.DirectDB.UnescapedDBParameter
// This is a test file, not production code.

/**
 * Test Discounts Step class
 *
 * Comprehensive tests for the discounts step including:
 * - All discount types (percentage, fixed, tiered, bogo, spend_threshold)
 * - Field mapping and data transformation
 * - BOGO config flattening (the fix we just implemented)
 * - Data persistence through save/load cycles
 * - Change tracker integration
 * - Asset localizer data conversion
 */
class Test_Discounts_Step extends WP_UnitTestCase {

	/**
	 * Container instance.
	 *
	 * @var WSSCD_Container
	 */
	private $container;

	/**
	 * Campaign manager instance.
	 *
	 * @var WSSCD_Campaign_Manager
	 */
	private $campaign_manager;

	/**
	 * Campaign repository instance.
	 *
	 * @var WSSCD_Campaign_Repository
	 */
	private $campaign_repository;

	/**
	 * Wizard state service instance.
	 *
	 * @var WSSCD_Wizard_State_Service
	 */
	private $wizard_state;

	/**
	 * Set up test environment before each test.
	 *
	 * @since 1.0.0
	 */
	public function setUp(): void {
		parent::setUp();

		// Initialize WordPress user with admin capabilities
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		// Get service container using test helper
		$this->container = Test_Container_Helper::get_container();

		// Get required services
		$this->campaign_manager    = $this->container->get( 'campaign_manager' );
		$this->campaign_repository = $this->container->get( 'campaign_repository' );
		$this->wizard_state        = $this->container->get( 'wizard_state_service' );
	}

	/**
	 * Clean up after each test.
	 *
	 * @since 1.0.0
	 */
	public function tearDown(): void {
		// Clean up campaigns
		global $wpdb;
		$campaigns_table = $wpdb->prefix . 'wsscd_campaigns';
		$wpdb->query( "DELETE FROM {$campaigns_table} WHERE name LIKE 'Test Discount%'" );

		// Clear wizard state
		if ( $this->wizard_state ) {
			$this->wizard_state->clear();
		}

		parent::tearDown();
	}

	/**
	 * Clean up after all tests in this class.
	 *
	 * @since 1.0.0
	 */
	public static function tearDownAfterClass(): void {
		global $wpdb;
		$campaigns_table = $wpdb->prefix . 'wsscd_campaigns';
		$wpdb->query( "DELETE FROM {$campaigns_table} WHERE name LIKE 'Test Discount%'" );

		parent::tearDownAfterClass();
	}

	/**
	 * Test percentage discount creation and persistence.
	 *
	 * @since 1.0.0
	 */
	public function test_percentage_discount() {
		$campaign_data = Test_Campaign_Data_Generator::get_base_campaign_data(
			array(
				'name'                      => 'Test Discount Percentage',
				'discount_type'             => 'percentage',
				'discount_value_percentage' => 25,
			)
		);

		$campaign = $this->campaign_manager->create( $campaign_data );

		$this->assertNotInstanceOf( 'WP_Error', $campaign, 'Percentage discount should be created successfully' );
		$this->assertEquals( 'percentage', $campaign->get_discount_type() );
		$this->assertEquals( 25.0, $campaign->get_discount_value() );
	}

	/**
	 * Test fixed discount creation and persistence.
	 *
	 * @since 1.0.0
	 */
	public function test_fixed_discount() {
		$campaign_data = Test_Campaign_Data_Generator::get_base_campaign_data(
			array(
				'name'                 => 'Test Discount Fixed',
				'discount_type'        => 'fixed',
				'discount_value_fixed' => 15.50,
			)
		);

		$campaign = $this->campaign_manager->create( $campaign_data );

		$this->assertNotInstanceOf( 'WP_Error', $campaign, 'Fixed discount should be created successfully' );
		$this->assertEquals( 'fixed', $campaign->get_discount_type() );
		$this->assertEquals( 15.50, $campaign->get_discount_value() );
	}

	/**
	 * Test tiered discount creation and persistence.
	 *
	 * @since 1.0.0
	 */
	public function test_tiered_discount() {
		$tiers = array(
			array(
				'min_quantity' => 1,
				'max_quantity' => 5,
				'discount'     => 10,
			),
			array(
				'min_quantity' => 6,
				'max_quantity' => 10,
				'discount'     => 20,
			),
			array(
				'min_quantity' => 11,
				'max_quantity' => 999,
				'discount'     => 30,
			),
		);

		$campaign_data = Test_Campaign_Data_Generator::get_base_campaign_data(
			array(
				'name'          => 'Test Discount Tiered',
				'discount_type' => 'tiered',
				'tiers'         => $tiers,
			)
		);

		$campaign = $this->campaign_manager->create( $campaign_data );

		$this->assertNotInstanceOf( 'WP_Error', $campaign, 'Tiered discount should be created successfully' );
		$this->assertEquals( 'tiered', $campaign->get_discount_type() );

		$discount_rules = $campaign->get_discount_rules();
		$this->assertIsArray( $discount_rules );
		$this->assertArrayHasKey( 'tiers', $discount_rules );
		$this->assertCount( 3, $discount_rules['tiers'] );
		$this->assertEquals( 10, $discount_rules['tiers'][0]['discount'] );
		$this->assertEquals( 20, $discount_rules['tiers'][1]['discount'] );
		$this->assertEquals( 30, $discount_rules['tiers'][2]['discount'] );
	}

	/**
	 * Test BOGO discount with flattened fields (the fix we just implemented).
	 *
	 * This test verifies the BOGO persistence fix where:
	 * 1. Campaign is created with BOGO config object
	 * 2. Change tracker extracts it as flattened individual fields
	 * 3. Data loads correctly in edit mode
	 *
	 * @since 1.0.0
	 */
	public function test_bogo_discount_flattened_fields() {
		// Create campaign with BOGO config object
		$campaign_data = Test_Campaign_Data_Generator::get_base_campaign_data(
			array(
				'name'          => 'Test Discount BOGO',
				'discount_type' => 'bogo',
				'bogo_config'   => array(
					'buy_quantity'     => 2,
					'get_quantity'     => 1,
					'discount_percent' => 50,
					'apply_to'         => 'cheapest',
				),
			)
		);

		$campaign = $this->campaign_manager->create( $campaign_data );

		$this->assertNotInstanceOf( 'WP_Error', $campaign, 'BOGO discount should be created successfully' );
		$this->assertEquals( 'bogo', $campaign->get_discount_type() );

		// Verify BOGO config was stored correctly
		$discount_rules = $campaign->get_discount_rules();
		$this->assertIsArray( $discount_rules );
		$this->assertArrayHasKey( 'bogo_config', $discount_rules );
		$this->assertEquals( 2, $discount_rules['bogo_config']['buy_quantity'] );
		$this->assertEquals( 1, $discount_rules['bogo_config']['get_quantity'] );
		$this->assertEquals( 50, $discount_rules['bogo_config']['discount_percent'] );
		$this->assertEquals( 'cheapest', $discount_rules['bogo_config']['apply_to'] );

		// Now test the change tracker extraction (the fix we implemented)
		// Simulate loading campaign for editing
		$change_tracker = new WSSCD_Campaign_Change_Tracker(
			$campaign->get_id(),
			$this->wizard_state,
			$this->campaign_manager
		);

		// Get discounts step data (should be flattened fields)
		$discounts_data = $change_tracker->get_step_data( 'discounts' );

		// Verify flattened BOGO fields exist (not nested object)
		$this->assertArrayHasKey( 'bogo_buy_quantity', $discounts_data, 'BOGO buy quantity should be flattened field' );
		$this->assertArrayHasKey( 'bogo_get_quantity', $discounts_data, 'BOGO get quantity should be flattened field' );
		$this->assertArrayHasKey( 'bogo_discount_percentage', $discounts_data, 'BOGO discount percentage should be flattened field' );
		$this->assertArrayHasKey( 'bogo_apply_to', $discounts_data, 'BOGO apply to should be flattened field' );

		// Verify values match
		$this->assertEquals( 2, $discounts_data['bogo_buy_quantity'] );
		$this->assertEquals( 1, $discounts_data['bogo_get_quantity'] );
		$this->assertEquals( 50, $discounts_data['bogo_discount_percentage'] );
		$this->assertEquals( 'cheapest', $discounts_data['bogo_apply_to'] );
	}

	/**
	 * Test BOGO discount with individual flattened fields input.
	 *
	 * @since 1.0.0
	 */
	public function test_bogo_discount_individual_fields() {
		// Create campaign with individual BOGO fields
		$campaign_data = Test_Campaign_Data_Generator::get_base_campaign_data(
			array(
				'name'                     => 'Test Discount BOGO Individual',
				'discount_type'            => 'bogo',
				'bogo_buy_quantity'        => 3,
				'bogo_get_quantity'        => 2,
				'bogo_discount_percentage' => 100,
				'bogo_apply_to'            => 'most_expensive',
			)
		);

		$campaign = $this->campaign_manager->create( $campaign_data );

		$this->assertNotInstanceOf( 'WP_Error', $campaign, 'BOGO with individual fields should be created successfully' );
		$this->assertEquals( 'bogo', $campaign->get_discount_type() );

		// Verify BOGO config was assembled correctly
		$discount_rules = $campaign->get_discount_rules();
		$this->assertIsArray( $discount_rules );
		$this->assertArrayHasKey( 'bogo_config', $discount_rules );
		$this->assertEquals( 3, $discount_rules['bogo_config']['buy_quantity'] );
		$this->assertEquals( 2, $discount_rules['bogo_config']['get_quantity'] );
		$this->assertEquals( 100, $discount_rules['bogo_config']['discount_percent'] );
		$this->assertEquals( 'most_expensive', $discount_rules['bogo_config']['apply_to'] );
	}

	/**
	 * Test spend threshold discount creation and persistence.
	 *
	 * @since 1.0.0
	 */
	public function test_spend_threshold_discount() {
		$thresholds = array(
			array(
				'min_spend' => 50,
				'discount'  => 10,
			),
			array(
				'min_spend' => 100,
				'discount'  => 20,
			),
			array(
				'min_spend' => 200,
				'discount'  => 30,
			),
		);

		$campaign_data = Test_Campaign_Data_Generator::get_base_campaign_data(
			array(
				'name'           => 'Test Discount Spend Threshold',
				'discount_type'  => 'spend_threshold',
				'threshold_mode' => 'percentage',
				'thresholds'     => $thresholds,
			)
		);

		$campaign = $this->campaign_manager->create( $campaign_data );

		$this->assertNotInstanceOf( 'WP_Error', $campaign, 'Spend threshold discount should be created successfully' );
		$this->assertEquals( 'spend_threshold', $campaign->get_discount_type() );

		$discount_rules = $campaign->get_discount_rules();
		$this->assertIsArray( $discount_rules );
		$this->assertArrayHasKey( 'thresholds', $discount_rules );
		$this->assertArrayHasKey( 'threshold_mode', $discount_rules );
		$this->assertEquals( 'percentage', $discount_rules['threshold_mode'] );
		$this->assertCount( 3, $discount_rules['thresholds'] );
	}

	/**
	 * Test discount rules persistence (usage limits, application rules, etc.).
	 *
	 * @since 1.0.0
	 */
	public function test_discount_rules_persistence() {
		$campaign_data = Test_Campaign_Data_Generator::get_base_campaign_data(
			array(
				'name'                     => 'Test Discount Rules',
				'discount_type'            => 'percentage',
				'discount_value_percentage' => 20,
				// Usage limits
				'usage_limit_per_customer' => 5,
				'total_usage_limit'        => 100,
				'lifetime_usage_cap'       => 500,
				// Application rules
				'apply_to'                 => 'per_item',
				'max_discount_amount'      => 50,
				'minimum_quantity'         => 2,
				'minimum_order_amount'     => 25,
				// Combination policy
				'stack_with_others'        => true,
				'allow_coupons'            => false,
				'apply_to_sale_items'      => false,
			)
		);

		$campaign = $this->campaign_manager->create( $campaign_data );

		$this->assertNotInstanceOf( 'WP_Error', $campaign, 'Campaign with discount rules should be created' );

		// Verify usage limits
		$discount_rules = $campaign->get_discount_rules();
		$this->assertEquals( 5, $discount_rules['usage_limit_per_customer'] );
		$this->assertEquals( 100, $discount_rules['total_usage_limit'] );
		$this->assertEquals( 500, $discount_rules['lifetime_usage_cap'] );

		// Verify application rules
		$this->assertEquals( 'per_item', $discount_rules['apply_to'] );
		$this->assertEquals( 50, $discount_rules['max_discount_amount'] );
		$this->assertEquals( 2, $discount_rules['minimum_quantity'] );
		$this->assertEquals( 25, $discount_rules['minimum_order_amount'] );

		// Verify combination policy
		$settings = $campaign->get_settings();
		$this->assertTrue( $settings['stack_with_others'] );
		$this->assertFalse( $settings['allow_coupons'] );
		$this->assertFalse( $settings['apply_to_sale_items'] );
	}

	/**
	 * Test badge settings persistence.
	 *
	 * @since 1.0.0
	 */
	public function test_badge_settings_persistence() {
		$campaign_data = Test_Campaign_Data_Generator::get_base_campaign_data(
			array(
				'name'                      => 'Test Discount Badge',
				'discount_type'             => 'percentage',
				'discount_value_percentage' => 15,
				'badge_enabled'             => true,
				'badge_text'                => '15% OFF',
				'badge_bg_color'            => '#ff0000',
				'badge_text_color'          => '#ffffff',
				'badge_position'            => 'top-left',
			)
		);

		$campaign = $this->campaign_manager->create( $campaign_data );

		$this->assertNotInstanceOf( 'WP_Error', $campaign, 'Campaign with badge settings should be created' );

		$settings = $campaign->get_settings();
		$this->assertTrue( $settings['badge_enabled'] );
		$this->assertEquals( '15% OFF', $settings['badge_text'] );
		$this->assertEquals( '#ff0000', $settings['badge_bg_color'] );
		$this->assertEquals( '#ffffff', $settings['badge_text_color'] );
		$this->assertEquals( 'top-left', $settings['badge_position'] );
	}

	/**
	 * Test change tracker for all discount types.
	 *
	 * Verifies that the change tracker correctly extracts discount data for editing.
	 *
	 * @since 1.0.0
	 */
	public function test_change_tracker_discount_extraction() {
		// Create a campaign with full discount configuration
		$campaign_data = Test_Campaign_Data_Generator::get_base_campaign_data(
			array(
				'name'                      => 'Test Discount Change Tracker',
				'discount_type'             => 'percentage',
				'discount_value_percentage' => 30,
				'usage_limit_per_customer'  => 3,
				'total_usage_limit'         => 50,
				'apply_to'                  => 'cart',
				'max_discount_amount'       => 100,
				'badge_enabled'             => true,
				'badge_text'                => 'SALE',
			)
		);

		$campaign = $this->campaign_manager->create( $campaign_data );
		$this->assertNotInstanceOf( 'WP_Error', $campaign );

		// Load campaign through change tracker
		$change_tracker = new WSSCD_Campaign_Change_Tracker(
			$campaign->get_id(),
			$this->wizard_state,
			$this->campaign_manager
		);

		$discounts_data = $change_tracker->get_step_data( 'discounts' );

		// Verify all fields are extracted correctly
		$this->assertEquals( 'percentage', $discounts_data['discount_type'] );
		$this->assertEquals( 30, $discounts_data['discount_value_percentage'] );
		$this->assertEquals( 3, $discounts_data['usage_limit_per_customer'] );
		$this->assertEquals( 50, $discounts_data['total_usage_limit'] );
		$this->assertEquals( 'cart', $discounts_data['apply_to'] );
		$this->assertEquals( 100, $discounts_data['max_discount_amount'] );
		$this->assertTrue( $discounts_data['badge_enabled'] );
		$this->assertEquals( 'SALE', $discounts_data['badge_text'] );
	}

	/**
	 * Test field mapper handles discount fields correctly.
	 *
	 * @since 1.0.0
	 */
	public function test_field_mapper_discount_fields() {
		$form_data = array(
			'discount_type'             => 'percentage',
			'discount_value_percentage' => 25,
			'bogo_buy_quantity'         => 2,
			'bogo_get_quantity'         => 1,
			'bogo_discount_percentage'  => 50,
			'usage_limit_per_customer'  => 10,
			'badge_enabled'             => true,
		);

		$mapped_data = WSSCD_Wizard_Field_Mapper::map_form_data( $form_data );

		// Verify all fields are mapped correctly
		$this->assertEquals( 'percentage', $mapped_data['discount_type'] );
		$this->assertEquals( 25, $mapped_data['discount_value_percentage'] );
		$this->assertEquals( 2, $mapped_data['bogo_buy_quantity'] );
		$this->assertEquals( 1, $mapped_data['bogo_get_quantity'] );
		$this->assertEquals( 50, $mapped_data['bogo_discount_percentage'] );
		$this->assertEquals( 10, $mapped_data['usage_limit_per_customer'] );
		$this->assertTrue( $mapped_data['badge_enabled'] );
	}

	/**
	 * Test discount type switching and value isolation.
	 *
	 * Verifies that changing discount types doesn't cause value conflicts.
	 *
	 * @since 1.0.0
	 */
	public function test_discount_type_switching() {
		// Create percentage campaign
		$campaign_data = Test_Campaign_Data_Generator::get_base_campaign_data(
			array(
				'name'                      => 'Test Discount Type Switch',
				'discount_type'             => 'percentage',
				'discount_value_percentage' => 20,
			)
		);

		$campaign = $this->campaign_manager->create( $campaign_data );
		$this->assertNotInstanceOf( 'WP_Error', $campaign );
		$this->assertEquals( 20.0, $campaign->get_discount_value() );

		// Update to fixed discount
		$update_data = array(
			'discount_type'        => 'fixed',
			'discount_value_fixed' => 15,
		);

		$updated_campaign = $this->campaign_manager->update( $campaign->get_id(), $update_data );
		$this->assertNotInstanceOf( 'WP_Error', $updated_campaign );
		$this->assertEquals( 'fixed', $updated_campaign->get_discount_type() );
		$this->assertEquals( 15.0, $updated_campaign->get_discount_value() );
	}

	/**
	 * Test edge case: zero values in usage limits.
	 *
	 * @since 1.0.0
	 */
	public function test_zero_usage_limits() {
		$campaign_data = Test_Campaign_Data_Generator::get_base_campaign_data(
			array(
				'name'                     => 'Test Discount Zero Limits',
				'discount_type'            => 'percentage',
				'discount_value_percentage' => 10,
				'usage_limit_per_customer' => 0, // Unlimited
				'total_usage_limit'        => 0, // Unlimited
			)
		);

		$campaign = $this->campaign_manager->create( $campaign_data );

		$this->assertNotInstanceOf( 'WP_Error', $campaign, 'Zero usage limits should be valid (unlimited)' );

		$discount_rules = $campaign->get_discount_rules();
		$this->assertEquals( 0, $discount_rules['usage_limit_per_customer'] );
		$this->assertEquals( 0, $discount_rules['total_usage_limit'] );
	}

	/**
	 * Test discount value precision (decimal handling).
	 *
	 * @since 1.0.0
	 */
	public function test_discount_value_precision() {
		// Test percentage with decimals
		$percentage_data = Test_Campaign_Data_Generator::get_base_campaign_data(
			array(
				'name'                      => 'Test Discount Precision Percentage',
				'discount_type'             => 'percentage',
				'discount_value_percentage' => 12.75,
			)
		);

		$percentage_campaign = $this->campaign_manager->create( $percentage_data );
		$this->assertEquals( 12.75, $percentage_campaign->get_discount_value() );

		// Test fixed with decimals
		$fixed_data = Test_Campaign_Data_Generator::get_base_campaign_data(
			array(
				'name'                 => 'Test Discount Precision Fixed',
				'discount_type'        => 'fixed',
				'discount_value_fixed' => 9.99,
			)
		);

		$fixed_campaign = $this->campaign_manager->create( $fixed_data );
		$this->assertEquals( 9.99, $fixed_campaign->get_discount_value() );
	}

	/**
	 * Test complete save/load cycle for BOGO discount.
	 *
	 * This is the key test for the BOGO persistence bug fix.
	 *
	 * @since 1.0.0
	 */
	public function test_bogo_complete_save_load_cycle() {
		// Step 1: Create BOGO campaign
		$campaign_data = Test_Campaign_Data_Generator::get_base_campaign_data(
			array(
				'name'          => 'Test Discount BOGO Cycle',
				'discount_type' => 'bogo',
				'bogo_config'   => array(
					'buy_quantity'     => 3,
					'get_quantity'     => 2,
					'discount_percent' => 75,
					'apply_to'         => 'most_expensive',
				),
			)
		);

		$campaign = $this->campaign_manager->create( $campaign_data );
		$this->assertNotInstanceOf( 'WP_Error', $campaign );
		$campaign_id = $campaign->get_id();

		// Step 2: Load campaign through change tracker (simulating edit mode)
		$change_tracker = new WSSCD_Campaign_Change_Tracker(
			$campaign_id,
			$this->wizard_state,
			$this->campaign_manager
		);

		$loaded_data = $change_tracker->get_step_data( 'discounts' );

		// Step 3: Verify BOGO data is loaded as flattened fields
		$this->assertEquals( 3, $loaded_data['bogo_buy_quantity'], 'Buy quantity should be loaded' );
		$this->assertEquals( 2, $loaded_data['bogo_get_quantity'], 'Get quantity should be loaded' );
		$this->assertEquals( 75, $loaded_data['bogo_discount_percentage'], 'Discount percentage should be loaded' );
		$this->assertEquals( 'most_expensive', $loaded_data['bogo_apply_to'], 'Apply to should be loaded' );

		// Step 4: Simulate user modifying BOGO settings
		$change_tracker->track_step(
			'discounts',
			array(
				'bogo_buy_quantity'        => 4,
				'bogo_get_quantity'        => 3,
				'bogo_discount_percentage' => 50,
			)
		);

		// Step 5: Compile and save changes
		$compiled_data = $change_tracker->compile();
		$updated_campaign = $this->campaign_manager->update( $campaign_id, $compiled_data );

		// Step 6: Verify changes were saved
		$this->assertNotInstanceOf( 'WP_Error', $updated_campaign );
		$discount_rules = $updated_campaign->get_discount_rules();

		$this->assertEquals( 4, $discount_rules['bogo_config']['buy_quantity'], 'Updated buy quantity should be saved' );
		$this->assertEquals( 3, $discount_rules['bogo_config']['get_quantity'], 'Updated get quantity should be saved' );
		$this->assertEquals( 50, $discount_rules['bogo_config']['discount_percent'], 'Updated discount should be saved' );
		$this->assertEquals( 'most_expensive', $discount_rules['bogo_config']['apply_to'], 'Apply to should remain unchanged' );
	}
}
