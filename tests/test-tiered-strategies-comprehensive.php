<?php
/**
 * Comprehensive Tests for Tiered Discount Strategies
 *
 * Tests both WSSCD_Tiered_Strategy and WSSCD_Spend_Threshold_Strategy
 * to verify correct field name usage, calculation logic, and edge cases.
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/tests
 * @since      1.0.0
 */

class WSSCD_Test_Tiered_Strategies extends WP_UnitTestCase {

	/**
	 * Tiered strategy instance.
	 *
	 * @var WSSCD_Tiered_Strategy
	 */
	private $tiered_strategy;

	/**
	 * Spend threshold strategy instance.
	 *
	 * @var WSSCD_Spend_Threshold_Strategy
	 */
	private $spend_strategy;

	/**
	 * Set up test fixtures.
	 */
	public function setUp() {
		parent::setUp();
		$this->tiered_strategy = new WSSCD_Tiered_Strategy();
		$this->spend_strategy  = new WSSCD_Spend_Threshold_Strategy();
	}

	// ==========================================
	// PART 1: TIERED STRATEGY TESTS
	// ==========================================

	/**
	 * Test: Tiered strategy correctly reads field names from tier data
	 */
	public function test_tiered_strategy_field_name_reading() {
		$config = array(
			'type'     => 'tiered',
			'apply_to' => 'per_item',
			'tiers'    => array(
				array(
					'min_quantity'   => 5,
					'discount_type'  => 'percentage',
					'discount_value' => 10.0,
				),
			),
		);

		$result = $this->tiered_strategy->calculate_discount(
			100.00,
			$config,
			array( 'quantity' => 5 )
		);

		$metadata = $result->get_metadata();

		// Verify correct field names were read
		$this->assertEquals( 5, $metadata['tier_min_quantity'], 'Should read min_quantity field' );
		$this->assertEquals( 'percentage', $metadata['tier_discount_type'], 'Should read discount_type field' );
		$this->assertEquals( 10.0, $metadata['tier_discount_value'], 'Should read discount_value field' );
		$this->assertTrue( $result->is_applied(), 'Discount should be applied' );
	}

	/**
	 * Test: Percentage discount calculations (10%, 20%, 50%)
	 */
	public function test_tiered_percentage_discounts() {
		$test_cases = array(
			array(
				'price'             => 100.00,
				'discount_percent'  => 10.0,
				'expected_discount' => 10.00,
				'expected_final'    => 90.00,
			),
			array(
				'price'             => 100.00,
				'discount_percent'  => 20.0,
				'expected_discount' => 20.00,
				'expected_final'    => 80.00,
			),
			array(
				'price'             => 100.00,
				'discount_percent'  => 50.0,
				'expected_discount' => 50.00,
				'expected_final'    => 50.00,
			),
			array(
				'price'             => 49.99,
				'discount_percent'  => 15.0,
				'expected_discount' => 7.50,
				'expected_final'    => 42.49,
			),
		);

		foreach ( $test_cases as $i => $case ) {
			$config = array(
				'type'     => 'tiered',
				'apply_to' => 'per_item',
				'tiers'    => array(
					array(
						'min_quantity'   => 1,
						'discount_type'  => 'percentage',
						'discount_value' => $case['discount_percent'],
					),
				),
			);

			$result = $this->tiered_strategy->calculate_discount(
				$case['price'],
				$config,
				array( 'quantity' => 1 )
			);

			$this->assertEquals(
				$case['expected_discount'],
				$result->get_discount_amount(),
				"Case {$i}: Discount amount should be {$case['expected_discount']}"
			);

			$this->assertEquals(
				$case['expected_final'],
				$result->get_discounted_price(),
				"Case {$i}: Final price should be {$case['expected_final']}"
			);
		}
	}

	/**
	 * Test: Fixed discount calculations ($5, $10, $20)
	 */
	public function test_tiered_fixed_discounts() {
		$test_cases = array(
			array(
				'price'             => 100.00,
				'discount_fixed'    => 5.00,
				'expected_discount' => 5.00,
				'expected_final'    => 95.00,
			),
			array(
				'price'             => 100.00,
				'discount_fixed'    => 10.00,
				'expected_discount' => 10.00,
				'expected_final'    => 90.00,
			),
			array(
				'price'             => 100.00,
				'discount_fixed'    => 20.00,
				'expected_discount' => 20.00,
				'expected_final'    => 80.00,
			),
			array(
				'price'             => 25.00,
				'discount_fixed'    => 15.00,
				'expected_discount' => 15.00,
				'expected_final'    => 10.00,
			),
		);

		foreach ( $test_cases as $i => $case ) {
			$config = array(
				'type'     => 'tiered',
				'apply_to' => 'per_item',
				'tiers'    => array(
					array(
						'min_quantity'   => 1,
						'discount_type'  => 'fixed',
						'discount_value' => $case['discount_fixed'],
					),
				),
			);

			$result = $this->tiered_strategy->calculate_discount(
				$case['price'],
				$config,
				array( 'quantity' => 1 )
			);

			$this->assertEquals(
				$case['expected_discount'],
				$result->get_discount_amount(),
				"Case {$i}: Discount amount should be {$case['expected_discount']}"
			);

			$this->assertEquals(
				$case['expected_final'],
				$result->get_discounted_price(),
				"Case {$i}: Final price should be {$case['expected_final']}"
			);
		}
	}

	/**
	 * Test: Tier selection logic (correct tier chosen based on quantity)
	 */
	public function test_tiered_tier_selection_logic() {
		$config = array(
			'type'     => 'tiered',
			'apply_to' => 'per_item',
			'tiers'    => array(
				array(
					'min_quantity'   => 5,
					'discount_type'  => 'percentage',
					'discount_value' => 10.0,
				),
				array(
					'min_quantity'   => 10,
					'discount_type'  => 'percentage',
					'discount_value' => 20.0,
				),
				array(
					'min_quantity'   => 20,
					'discount_type'  => 'percentage',
					'discount_value' => 30.0,
				),
			),
		);

		// Test quantity = 5 (should get 10% discount)
		$result = $this->tiered_strategy->calculate_discount(
			100.00,
			$config,
			array( 'quantity' => 5 )
		);
		$this->assertEquals( 90.00, $result->get_discounted_price(), 'Qty 5 should get 10% discount' );
		$this->assertEquals( 5, $result->get_metadata()['tier_min_quantity'], 'Should select tier 1' );

		// Test quantity = 10 (should get 20% discount)
		$result = $this->tiered_strategy->calculate_discount(
			100.00,
			$config,
			array( 'quantity' => 10 )
		);
		$this->assertEquals( 80.00, $result->get_discounted_price(), 'Qty 10 should get 20% discount' );
		$this->assertEquals( 10, $result->get_metadata()['tier_min_quantity'], 'Should select tier 2' );

		// Test quantity = 25 (should get 30% discount - highest tier)
		$result = $this->tiered_strategy->calculate_discount(
			100.00,
			$config,
			array( 'quantity' => 25 )
		);
		$this->assertEquals( 70.00, $result->get_discounted_price(), 'Qty 25 should get 30% discount' );
		$this->assertEquals( 20, $result->get_metadata()['tier_min_quantity'], 'Should select tier 3' );
	}

	/**
	 * Test: Edge case - quantity = 0
	 */
	public function test_tiered_edge_case_zero_quantity() {
		$config = array(
			'type'     => 'tiered',
			'apply_to' => 'per_item',
			'tiers'    => array(
				array(
					'min_quantity'   => 5,
					'discount_type'  => 'percentage',
					'discount_value' => 10.0,
				),
			),
		);

		$result = $this->tiered_strategy->calculate_discount(
			100.00,
			$config,
			array( 'quantity' => 0 )
		);

		// Quantity 0 should be treated as 1, and since 1 < 5, no tier applies
		$this->assertFalse( $result->is_applied(), 'No discount should apply for quantity below minimum' );
		$this->assertEquals( 100.00, $result->get_discounted_price(), 'Price should remain unchanged' );
	}

	/**
	 * Test: Edge case - quantity < minimum tier
	 */
	public function test_tiered_edge_case_below_minimum() {
		$config = array(
			'type'     => 'tiered',
			'apply_to' => 'per_item',
			'tiers'    => array(
				array(
					'min_quantity'   => 10,
					'discount_type'  => 'percentage',
					'discount_value' => 15.0,
				),
			),
		);

		// Test quantity = 5 (below minimum of 10)
		$result = $this->tiered_strategy->calculate_discount(
			100.00,
			$config,
			array( 'quantity' => 5 )
		);

		$this->assertFalse( $result->is_applied(), 'No discount should apply when below minimum quantity' );
		$this->assertEquals( 100.00, $result->get_discounted_price(), 'Price should remain unchanged' );
	}

	/**
	 * Test: Edge case - quantity exactly matches tier
	 */
	public function test_tiered_edge_case_exact_match() {
		$config = array(
			'type'     => 'tiered',
			'apply_to' => 'per_item',
			'tiers'    => array(
				array(
					'min_quantity'   => 10,
					'discount_type'  => 'percentage',
					'discount_value' => 25.0,
				),
			),
		);

		// Test quantity = 10 (exactly matches minimum)
		$result = $this->tiered_strategy->calculate_discount(
			100.00,
			$config,
			array( 'quantity' => 10 )
		);

		$this->assertTrue( $result->is_applied(), 'Discount should apply when quantity exactly matches minimum' );
		$this->assertEquals( 75.00, $result->get_discounted_price(), 'Should get 25% discount' );
		$this->assertEquals( 10, $result->get_metadata()['tier_min_quantity'], 'Should match tier exactly' );
	}

	/**
	 * Test: Float precision handling (no rounding errors)
	 */
	public function test_tiered_float_precision() {
		$config = array(
			'type'     => 'tiered',
			'apply_to' => 'per_item',
			'tiers'    => array(
				array(
					'min_quantity'   => 1,
					'discount_type'  => 'percentage',
					'discount_value' => 15.0,
				),
			),
		);

		// Test price that could cause precision errors
		$result = $this->tiered_strategy->calculate_discount(
			99.99,
			$config,
			array( 'quantity' => 1 )
		);

		// 99.99 * 15% = 14.9985, should round to 15.00
		$this->assertEquals( 15.00, $result->get_discount_amount(), 'Should handle float precision correctly' );
		$this->assertEquals( 84.99, $result->get_discounted_price(), 'Final price should be precise' );

		// Test another precision case
		$result = $this->tiered_strategy->calculate_discount(
			19.99,
			$config,
			array( 'quantity' => 1 )
		);

		// 19.99 * 15% = 2.9985, should round to 3.00
		$this->assertEquals( 3.00, $result->get_discount_amount(), 'Should round discount amount correctly' );
		$this->assertEquals( 16.99, $result->get_discounted_price(), 'Final price should be precise' );
	}

	/**
	 * Test: Negative price prevention
	 */
	public function test_tiered_negative_price_prevention() {
		$config = array(
			'type'     => 'tiered',
			'apply_to' => 'per_item',
			'tiers'    => array(
				array(
					'min_quantity'   => 1,
					'discount_type'  => 'fixed',
					'discount_value' => 150.00, // More than the price
				),
			),
		);

		$result = $this->tiered_strategy->calculate_discount(
			100.00,
			$config,
			array( 'quantity' => 1 )
		);

		$this->assertEquals( 0.00, $result->get_discounted_price(), 'Price should not go below zero' );
		$this->assertEquals( 100.00, $result->get_discount_amount(), 'Discount capped at original price' );
	}

	/**
	 * Test: Order total mode (promotional discount)
	 */
	public function test_tiered_order_total_mode() {
		$config = array(
			'type'     => 'tiered',
			'apply_to' => 'order_total', // Promotional mode
			'tiers'    => array(
				array(
					'min_quantity'   => 3,
					'discount_type'  => 'fixed',
					'discount_value' => 10.00, // $10 off entire order
				),
			),
		);

		// Buy 3 items at $20 each = $60 total, get $10 off = $50 total
		$result = $this->tiered_strategy->calculate_discount(
			20.00,
			$config,
			array( 'quantity' => 3 )
		);

		$metadata = $result->get_metadata();

		$this->assertEquals( 'order_total', $metadata['apply_to'], 'Should use order_total mode' );
		$this->assertEquals( 60.00, $metadata['order_subtotal'], 'Order subtotal should be $60' );
		$this->assertEquals( 10.00, $metadata['total_discount'], 'Total discount should be $10' );

		// Final price per item: $50 / 3 = $16.67
		$this->assertEquals( 16.67, $result->get_discounted_price(), 'Per-item price should be discounted proportionally' );
	}

	// ==========================================
	// PART 2: SPEND THRESHOLD STRATEGY TESTS
	// ==========================================

	/**
	 * Test: Spend threshold strategy correctly reads field names
	 */
	public function test_spend_threshold_field_name_reading() {
		$config = array(
			'type'       => 'spend_threshold',
			'thresholds' => array(
				array(
					'spend_amount'   => 50.00,
					'discount_type'  => 'percentage',
					'discount_value' => 10.0,
				),
			),
		);

		$result = $this->spend_strategy->calculate_discount(
			100.00,
			$config,
			array( 'cart_total' => 100.00 )
		);

		$metadata = $result->get_metadata();

		// Verify correct field names were read
		$this->assertEquals( 50.00, $metadata['threshold_amount'], 'Should read spend_amount field' );
		$this->assertEquals( 'percentage', $metadata['threshold_discount_type'], 'Should read discount_type field' );
		$this->assertEquals( 10.0, $metadata['threshold_discount_value'], 'Should read discount_value field' );
		$this->assertTrue( $result->is_applied(), 'Discount should be applied' );
	}

	/**
	 * Test: Spend threshold percentage discounts (5%, 10%, 15%)
	 */
	public function test_spend_threshold_percentage_discounts() {
		$test_cases = array(
			array(
				'price'             => 100.00,
				'cart_total'        => 100.00,
				'threshold'         => 50.00,
				'discount_percent'  => 5.0,
				'expected_discount' => 5.00,
				'expected_final'    => 95.00,
			),
			array(
				'price'             => 100.00,
				'cart_total'        => 100.00,
				'threshold'         => 50.00,
				'discount_percent'  => 10.0,
				'expected_discount' => 10.00,
				'expected_final'    => 90.00,
			),
			array(
				'price'             => 100.00,
				'cart_total'        => 100.00,
				'threshold'         => 50.00,
				'discount_percent'  => 15.0,
				'expected_discount' => 15.00,
				'expected_final'    => 85.00,
			),
		);

		foreach ( $test_cases as $i => $case ) {
			$config = array(
				'type'       => 'spend_threshold',
				'thresholds' => array(
					array(
						'spend_amount'   => $case['threshold'],
						'discount_type'  => 'percentage',
						'discount_value' => $case['discount_percent'],
					),
				),
			);

			$result = $this->spend_strategy->calculate_discount(
				$case['price'],
				$config,
				array( 'cart_total' => $case['cart_total'] )
			);

			$this->assertEquals(
				$case['expected_discount'],
				$result->get_discount_amount(),
				"Case {$i}: Discount amount should be {$case['expected_discount']}"
			);

			$this->assertEquals(
				$case['expected_final'],
				$result->get_discounted_price(),
				"Case {$i}: Final price should be {$case['expected_final']}"
			);
		}
	}

	/**
	 * Test: Spend threshold fixed discounts ($5, $15, $25)
	 */
	public function test_spend_threshold_fixed_discounts() {
		$test_cases = array(
			array(
				'price'             => 100.00,
				'cart_total'        => 100.00,
				'threshold'         => 50.00,
				'discount_fixed'    => 5.00,
				'expected_discount' => 5.00,
				'expected_final'    => 95.00,
			),
			array(
				'price'             => 100.00,
				'cart_total'        => 100.00,
				'threshold'         => 50.00,
				'discount_fixed'    => 15.00,
				'expected_discount' => 15.00,
				'expected_final'    => 85.00,
			),
			array(
				'price'             => 100.00,
				'cart_total'        => 100.00,
				'threshold'         => 50.00,
				'discount_fixed'    => 25.00,
				'expected_discount' => 25.00,
				'expected_final'    => 75.00,
			),
		);

		foreach ( $test_cases as $i => $case ) {
			$config = array(
				'type'       => 'spend_threshold',
				'thresholds' => array(
					array(
						'spend_amount'   => $case['threshold'],
						'discount_type'  => 'fixed',
						'discount_value' => $case['discount_fixed'],
					),
				),
			);

			$result = $this->spend_strategy->calculate_discount(
				$case['price'],
				$config,
				array( 'cart_total' => $case['cart_total'] )
			);

			$this->assertEquals(
				$case['expected_discount'],
				$result->get_discount_amount(),
				"Case {$i}: Discount amount should be {$case['expected_discount']}"
			);

			$this->assertEquals(
				$case['expected_final'],
				$result->get_discounted_price(),
				"Case {$i}: Final price should be {$case['expected_final']}"
			);
		}
	}

	/**
	 * Test: Threshold selection logic (correct threshold chosen based on cart total)
	 */
	public function test_spend_threshold_selection_logic() {
		$config = array(
			'type'       => 'spend_threshold',
			'thresholds' => array(
				array(
					'spend_amount'   => 50.00,
					'discount_type'  => 'percentage',
					'discount_value' => 5.0,
				),
				array(
					'spend_amount'   => 100.00,
					'discount_type'  => 'percentage',
					'discount_value' => 10.0,
				),
				array(
					'spend_amount'   => 200.00,
					'discount_type'  => 'percentage',
					'discount_value' => 15.0,
				),
			),
		);

		// Test cart_total = $50 (should get 5% discount)
		$result = $this->spend_strategy->calculate_discount(
			100.00,
			$config,
			array( 'cart_total' => 50.00 )
		);
		$this->assertEquals( 95.00, $result->get_discounted_price(), 'Cart $50 should get 5% discount' );
		$this->assertEquals( 50.00, $result->get_metadata()['threshold_amount'], 'Should select threshold 1' );

		// Test cart_total = $100 (should get 10% discount)
		$result = $this->spend_strategy->calculate_discount(
			100.00,
			$config,
			array( 'cart_total' => 100.00 )
		);
		$this->assertEquals( 90.00, $result->get_discounted_price(), 'Cart $100 should get 10% discount' );
		$this->assertEquals( 100.00, $result->get_metadata()['threshold_amount'], 'Should select threshold 2' );

		// Test cart_total = $250 (should get 15% discount - highest threshold)
		$result = $this->spend_strategy->calculate_discount(
			100.00,
			$config,
			array( 'cart_total' => 250.00 )
		);
		$this->assertEquals( 85.00, $result->get_discounted_price(), 'Cart $250 should get 15% discount' );
		$this->assertEquals( 200.00, $result->get_metadata()['threshold_amount'], 'Should select threshold 3' );
	}

	/**
	 * Test: Edge case - cart_total = 0
	 */
	public function test_spend_threshold_edge_case_zero_total() {
		$config = array(
			'type'       => 'spend_threshold',
			'thresholds' => array(
				array(
					'spend_amount'   => 50.00,
					'discount_type'  => 'percentage',
					'discount_value' => 10.0,
				),
			),
		);

		$result = $this->spend_strategy->calculate_discount(
			100.00,
			$config,
			array( 'cart_total' => 0.00 )
		);

		$this->assertFalse( $result->is_applied(), 'No discount should apply for zero cart total' );
		$this->assertEquals( 100.00, $result->get_discounted_price(), 'Price should remain unchanged' );
	}

	/**
	 * Test: Edge case - cart_total < minimum threshold
	 */
	public function test_spend_threshold_edge_case_below_minimum() {
		$config = array(
			'type'       => 'spend_threshold',
			'thresholds' => array(
				array(
					'spend_amount'   => 100.00,
					'discount_type'  => 'percentage',
					'discount_value' => 15.0,
				),
			),
		);

		// Test cart_total = $50 (below minimum of $100)
		$result = $this->spend_strategy->calculate_discount(
			100.00,
			$config,
			array( 'cart_total' => 50.00 )
		);

		$this->assertFalse( $result->is_applied(), 'No discount should apply when below minimum threshold' );
		$this->assertEquals( 100.00, $result->get_discounted_price(), 'Price should remain unchanged' );
	}

	/**
	 * Test: Edge case - cart_total exactly matches threshold
	 */
	public function test_spend_threshold_edge_case_exact_match() {
		$config = array(
			'type'       => 'spend_threshold',
			'thresholds' => array(
				array(
					'spend_amount'   => 100.00,
					'discount_type'  => 'percentage',
					'discount_value' => 20.0,
				),
			),
		);

		// Test cart_total = $100 (exactly matches threshold)
		$result = $this->spend_strategy->calculate_discount(
			100.00,
			$config,
			array( 'cart_total' => 100.00 )
		);

		$this->assertTrue( $result->is_applied(), 'Discount should apply when cart total exactly matches threshold' );
		$this->assertEquals( 80.00, $result->get_discounted_price(), 'Should get 20% discount' );
		$this->assertEquals( 100.00, $result->get_metadata()['threshold_amount'], 'Should match threshold exactly' );
	}

	/**
	 * Test: Float precision handling for spend thresholds
	 */
	public function test_spend_threshold_float_precision() {
		$config = array(
			'type'       => 'spend_threshold',
			'thresholds' => array(
				array(
					'spend_amount'   => 50.00,
					'discount_type'  => 'percentage',
					'discount_value' => 15.0,
				),
			),
		);

		// Test price that could cause precision errors
		$result = $this->spend_strategy->calculate_discount(
			99.99,
			$config,
			array( 'cart_total' => 100.00 )
		);

		// 99.99 * 15% = 14.9985, should round to 15.00
		$this->assertEquals( 15.00, $result->get_discount_amount(), 'Should handle float precision correctly' );
		$this->assertEquals( 84.99, $result->get_discounted_price(), 'Final price should be precise' );
	}

	/**
	 * Test: Negative price prevention for spend thresholds
	 */
	public function test_spend_threshold_negative_price_prevention() {
		$config = array(
			'type'       => 'spend_threshold',
			'thresholds' => array(
				array(
					'spend_amount'   => 50.00,
					'discount_type'  => 'fixed',
					'discount_value' => 150.00, // More than the price
				),
			),
		);

		$result = $this->spend_strategy->calculate_discount(
			100.00,
			$config,
			array( 'cart_total' => 100.00 )
		);

		$this->assertEquals( 0.00, $result->get_discounted_price(), 'Price should not go below zero' );
		$this->assertEquals( 100.00, $result->get_discount_amount(), 'Discount capped at original price' );
	}

	// ==========================================
	// PART 3: INTEGRATION & BUSINESS LOGIC TESTS
	// ==========================================

	/**
	 * Test: Higher tiers give better discounts
	 */
	public function test_higher_tiers_give_better_discounts() {
		$config = array(
			'type'     => 'tiered',
			'apply_to' => 'per_item',
			'tiers'    => array(
				array(
					'min_quantity'   => 5,
					'discount_type'  => 'percentage',
					'discount_value' => 10.0,
				),
				array(
					'min_quantity'   => 10,
					'discount_type'  => 'percentage',
					'discount_value' => 20.0,
				),
				array(
					'min_quantity'   => 20,
					'discount_type'  => 'percentage',
					'discount_value' => 30.0,
				),
			),
		);

		$result_tier1 = $this->tiered_strategy->calculate_discount(
			100.00,
			$config,
			array( 'quantity' => 5 )
		);

		$result_tier2 = $this->tiered_strategy->calculate_discount(
			100.00,
			$config,
			array( 'quantity' => 10 )
		);

		$result_tier3 = $this->tiered_strategy->calculate_discount(
			100.00,
			$config,
			array( 'quantity' => 20 )
		);

		// Verify increasing discount amounts
		$this->assertGreaterThan(
			$result_tier1->get_discount_amount(),
			$result_tier2->get_discount_amount(),
			'Tier 2 should give more discount than Tier 1'
		);

		$this->assertGreaterThan(
			$result_tier2->get_discount_amount(),
			$result_tier3->get_discount_amount(),
			'Tier 3 should give more discount than Tier 2'
		);
	}

	/**
	 * Test: Higher thresholds give better discounts
	 */
	public function test_higher_thresholds_give_better_discounts() {
		$config = array(
			'type'       => 'spend_threshold',
			'thresholds' => array(
				array(
					'spend_amount'   => 50.00,
					'discount_type'  => 'percentage',
					'discount_value' => 5.0,
				),
				array(
					'spend_amount'   => 100.00,
					'discount_type'  => 'percentage',
					'discount_value' => 10.0,
				),
				array(
					'spend_amount'   => 200.00,
					'discount_type'  => 'percentage',
					'discount_value' => 15.0,
				),
			),
		);

		$result_threshold1 = $this->spend_strategy->calculate_discount(
			100.00,
			$config,
			array( 'cart_total' => 50.00 )
		);

		$result_threshold2 = $this->spend_strategy->calculate_discount(
			100.00,
			$config,
			array( 'cart_total' => 100.00 )
		);

		$result_threshold3 = $this->spend_strategy->calculate_discount(
			100.00,
			$config,
			array( 'cart_total' => 200.00 )
		);

		// Verify increasing discount amounts
		$this->assertGreaterThan(
			$result_threshold1->get_discount_amount(),
			$result_threshold2->get_discount_amount(),
			'Threshold 2 should give more discount than Threshold 1'
		);

		$this->assertGreaterThan(
			$result_threshold2->get_discount_amount(),
			$result_threshold3->get_discount_amount(),
			'Threshold 3 should give more discount than Threshold 2'
		);
	}

	/**
	 * Test: Percentage discounts capped at 100%
	 */
	public function test_percentage_discount_capped_at_100() {
		// Test 100% discount (maximum allowed)
		$config = array(
			'type'     => 'tiered',
			'apply_to' => 'per_item',
			'tiers'    => array(
				array(
					'min_quantity'   => 1,
					'discount_type'  => 'percentage',
					'discount_value' => 100.0,
				),
			),
		);

		$result = $this->tiered_strategy->calculate_discount(
			100.00,
			$config,
			array( 'quantity' => 1 )
		);

		$this->assertEquals( 0.00, $result->get_discounted_price(), '100% discount should result in $0 price' );
		$this->assertEquals( 100.00, $result->get_discount_amount(), 'Discount amount should equal original price' );
	}

	/**
	 * Test: Verify metadata structure for tiered discounts
	 */
	public function test_tiered_metadata_structure() {
		$config = array(
			'type'     => 'tiered',
			'apply_to' => 'per_item',
			'tiers'    => array(
				array(
					'min_quantity'   => 5,
					'discount_type'  => 'percentage',
					'discount_value' => 15.0,
				),
			),
		);

		$result   = $this->tiered_strategy->calculate_discount(
			100.00,
			$config,
			array( 'quantity' => 5 )
		);
		$metadata = $result->get_metadata();

		// Verify all required metadata fields exist
		$this->assertArrayHasKey( 'apply_to', $metadata, 'Metadata should contain apply_to' );
		$this->assertArrayHasKey( 'quantity', $metadata, 'Metadata should contain quantity' );
		$this->assertArrayHasKey( 'applicable_tier', $metadata, 'Metadata should contain applicable_tier' );
		$this->assertArrayHasKey( 'tier_min_quantity', $metadata, 'Metadata should contain tier_min_quantity' );
		$this->assertArrayHasKey( 'tier_discount_type', $metadata, 'Metadata should contain tier_discount_type' );
		$this->assertArrayHasKey( 'tier_discount_value', $metadata, 'Metadata should contain tier_discount_value' );

		// Verify metadata values
		$this->assertEquals( 'per_item', $metadata['apply_to'] );
		$this->assertEquals( 5, $metadata['quantity'] );
		$this->assertEquals( 5, $metadata['tier_min_quantity'] );
		$this->assertEquals( 'percentage', $metadata['tier_discount_type'] );
		$this->assertEquals( 15.0, $metadata['tier_discount_value'] );
	}

	/**
	 * Test: Verify metadata structure for spend threshold discounts
	 */
	public function test_spend_threshold_metadata_structure() {
		$config = array(
			'type'       => 'spend_threshold',
			'thresholds' => array(
				array(
					'spend_amount'   => 50.00,
					'discount_type'  => 'percentage',
					'discount_value' => 10.0,
				),
			),
		);

		$result   = $this->spend_strategy->calculate_discount(
			100.00,
			$config,
			array( 'cart_total' => 75.00 )
		);
		$metadata = $result->get_metadata();

		// Verify all required metadata fields exist
		$this->assertArrayHasKey( 'cart_total', $metadata, 'Metadata should contain cart_total' );
		$this->assertArrayHasKey( 'applicable_threshold', $metadata, 'Metadata should contain applicable_threshold' );
		$this->assertArrayHasKey( 'threshold_amount', $metadata, 'Metadata should contain threshold_amount' );
		$this->assertArrayHasKey( 'threshold_discount_type', $metadata, 'Metadata should contain threshold_discount_type' );
		$this->assertArrayHasKey( 'threshold_discount_value', $metadata, 'Metadata should contain threshold_discount_value' );

		// Verify metadata values
		$this->assertEquals( 75.00, $metadata['cart_total'] );
		$this->assertEquals( 50.00, $metadata['threshold_amount'] );
		$this->assertEquals( 'percentage', $metadata['threshold_discount_type'] );
		$this->assertEquals( 10.0, $metadata['threshold_discount_value'] );
	}

	/**
	 * Test: Complex multi-tier scenario
	 */
	public function test_complex_multi_tier_scenario() {
		$config = array(
			'type'     => 'tiered',
			'apply_to' => 'per_item',
			'tiers'    => array(
				array(
					'min_quantity'   => 3,
					'discount_type'  => 'percentage',
					'discount_value' => 5.0,
				),
				array(
					'min_quantity'   => 6,
					'discount_type'  => 'percentage',
					'discount_value' => 10.0,
				),
				array(
					'min_quantity'   => 12,
					'discount_type'  => 'percentage',
					'discount_value' => 15.0,
				),
				array(
					'min_quantity'   => 24,
					'discount_type'  => 'percentage',
					'discount_value' => 20.0,
				),
			),
		);

		// Test various quantities to ensure correct tier selection
		$test_quantities = array(
			array( 'qty' => 2, 'should_apply' => false, 'expected_price' => 50.00 ),
			array( 'qty' => 3, 'should_apply' => true, 'expected_price' => 47.50 ), // 5% off
			array( 'qty' => 6, 'should_apply' => true, 'expected_price' => 45.00 ), // 10% off
			array( 'qty' => 12, 'should_apply' => true, 'expected_price' => 42.50 ), // 15% off
			array( 'qty' => 24, 'should_apply' => true, 'expected_price' => 40.00 ), // 20% off
			array( 'qty' => 50, 'should_apply' => true, 'expected_price' => 40.00 ), // Still 20% off (highest tier)
		);

		foreach ( $test_quantities as $test ) {
			$result = $this->tiered_strategy->calculate_discount(
				50.00,
				$config,
				array( 'quantity' => $test['qty'] )
			);

			$this->assertEquals(
				$test['should_apply'],
				$result->is_applied(),
				"Quantity {$test['qty']}: Discount applied status should be " . ( $test['should_apply'] ? 'true' : 'false' )
			);

			$this->assertEquals(
				$test['expected_price'],
				$result->get_discounted_price(),
				"Quantity {$test['qty']}: Final price should be {$test['expected_price']}"
			);
		}
	}

	/**
	 * Test: Complex multi-threshold scenario
	 */
	public function test_complex_multi_threshold_scenario() {
		$config = array(
			'type'       => 'spend_threshold',
			'thresholds' => array(
				array(
					'spend_amount'   => 30.00,
					'discount_type'  => 'fixed',
					'discount_value' => 3.00,
				),
				array(
					'spend_amount'   => 50.00,
					'discount_type'  => 'fixed',
					'discount_value' => 5.00,
				),
				array(
					'spend_amount'   => 100.00,
					'discount_type'  => 'fixed',
					'discount_value' => 12.00,
				),
				array(
					'spend_amount'   => 200.00,
					'discount_type'  => 'fixed',
					'discount_value' => 30.00,
				),
			),
		);

		// Test various cart totals to ensure correct threshold selection
		$test_totals = array(
			array( 'total' => 25.00, 'should_apply' => false, 'expected_price' => 50.00 ),
			array( 'total' => 30.00, 'should_apply' => true, 'expected_price' => 47.00 ), // $3 off
			array( 'total' => 50.00, 'should_apply' => true, 'expected_price' => 45.00 ), // $5 off
			array( 'total' => 100.00, 'should_apply' => true, 'expected_price' => 38.00 ), // $12 off
			array( 'total' => 200.00, 'should_apply' => true, 'expected_price' => 20.00 ), // $30 off
			array( 'total' => 500.00, 'should_apply' => true, 'expected_price' => 20.00 ), // Still $30 off (highest threshold)
		);

		foreach ( $test_totals as $test ) {
			$result = $this->spend_strategy->calculate_discount(
				50.00,
				$config,
				array( 'cart_total' => $test['total'] )
			);

			$this->assertEquals(
				$test['should_apply'],
				$result->is_applied(),
				"Cart total \${$test['total']}: Discount applied status should be " . ( $test['should_apply'] ? 'true' : 'false' )
			);

			$this->assertEquals(
				$test['expected_price'],
				$result->get_discounted_price(),
				"Cart total \${$test['total']}: Final price should be {$test['expected_price']}"
			);
		}
	}
}
