<?php
/**
 * Comprehensive Conditions System Tests
 *
 * Tests all aspects of the campaign conditions system including:
 * - Mode testing (include/exclude)
 * - Operator testing (all 14 operators)
 * - Multiple condition testing (AND/OR logic)
 * - Format compatibility (UI/Database/Engine)
 * - Edge cases and error handling
 *
 * @package    SmartCycleDiscounts
 * @subpackage Tests
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	// Load WordPress for standalone testing
	$wp_load_path = dirname( dirname( dirname( dirname( dirname( __DIR__ ) ) ) ) ) . '/public/wp-load.php';
	if ( ! file_exists( $wp_load_path ) ) {
		die( 'WordPress not found. Please run from WordPress environment.' );
	}
	require_once $wp_load_path;
}

/**
 * Comprehensive Conditions Test Runner
 */
class SCD_Conditions_Comprehensive_Test {

	/**
	 * Test results
	 *
	 * @var array
	 */
	private array $results = array();

	/**
	 * Condition engine instance
	 *
	 * @var SCD_Condition_Engine
	 */
	private $condition_engine;

	/**
	 * Product selector instance
	 *
	 * @var SCD_Product_Selector
	 */
	private $product_selector;

	/**
	 * Test product IDs
	 *
	 * @var array
	 */
	private array $test_products = array();

	/**
	 * Initialize test runner
	 */
	public function __construct() {
		$this->init_services();
		$this->create_test_products();
	}

	/**
	 * Initialize required services
	 */
	private function init_services(): void {
		$logger = new SCD_Logger( 'conditions-test' );
		$cache  = null; // Disable cache for testing

		$this->condition_engine = new SCD_Condition_Engine( $logger, $cache );

		$db                   = new SCD_Database_Manager( $logger );
		$this->product_selector = new SCD_Product_Selector( $db, $logger, $cache, $this->condition_engine );
	}

	/**
	 * Create test products with known properties
	 */
	private function create_test_products(): void {
		// Product 1: Price 50, Stock 10
		$product1 = new WC_Product_Simple();
		$product1->set_name( 'Test Product 50' );
		$product1->set_regular_price( '50.00' );
		$product1->set_price( '50.00' );
		$product1->set_stock_quantity( 10 );
		$product1->set_stock_status( 'instock' );
		$product1->set_sku( 'SKU-50' );
		$product1->save();
		$this->test_products['product_50'] = $product1->get_id();

		// Product 2: Price 100, Stock 20
		$product2 = new WC_Product_Simple();
		$product2->set_name( 'Test Product 100' );
		$product2->set_regular_price( '100.00' );
		$product2->set_price( '100.00' );
		$product2->set_stock_quantity( 20 );
		$product2->set_stock_status( 'instock' );
		$product2->set_sku( 'SKU-100' );
		$product2->save();
		$this->test_products['product_100'] = $product2->get_id();

		// Product 3: Price 25, Stock 5
		$product3 = new WC_Product_Simple();
		$product3->set_name( 'Test Product 25' );
		$product3->set_regular_price( '25.00' );
		$product3->set_price( '25.00' );
		$product3->set_stock_quantity( 5 );
		$product3->set_stock_status( 'instock' );
		$product3->set_sku( 'SKU-25' );
		$product3->save();
		$this->test_products['product_25'] = $product3->get_id();

		// Product 4: Price 75, Stock 15
		$product4 = new WC_Product_Simple();
		$product4->set_name( 'Awesome Product' );
		$product4->set_regular_price( '75.00' );
		$product4->set_price( '75.00' );
		$product4->set_stock_quantity( 15 );
		$product4->set_stock_status( 'instock' );
		$product4->set_sku( 'AWESOME-75' );
		$product4->save();
		$this->test_products['product_75'] = $product4->get_id();
	}

	/**
	 * Run all tests
	 */
	public function run_all_tests(): void {
		echo "\n\n";
		echo "========================================\n";
		echo "COMPREHENSIVE CONDITIONS SYSTEM TESTS\n";
		echo "========================================\n\n";

		// Test Categories
		$this->test_mode_functionality();
		$this->test_numeric_operators();
		$this->test_text_operators();
		$this->test_select_operators();
		$this->test_multiple_conditions_and_logic();
		$this->test_multiple_conditions_or_logic();
		$this->test_format_compatibility();
		$this->test_edge_cases();
		$this->test_error_handling();

		// Print summary
		$this->print_summary();
	}

	/**
	 * Test 1: Mode Testing (Include/Exclude)
	 */
	private function test_mode_functionality(): void {
		echo "TEST 1: Mode Functionality (Include/Exclude)\n";
		echo "---------------------------------------------\n";

		$all_ids = array_values( $this->test_products );

		// Test 1.1: Include mode with price = 50
		$condition_include = array(
			array(
				'property' => 'price',
				'operator' => 'equals',
				'value'    => '50',
				'mode'     => 'include',
			),
		);

		$result_include = $this->condition_engine->apply_conditions( $all_ids, $condition_include, 'all' );
		$expected       = array( $this->test_products['product_50'] );
		$this->assert_test(
			'Include mode: price = 50',
			$expected,
			$result_include,
			'Should match only product with price 50'
		);

		// Test 1.2: Exclude mode with price = 50
		$condition_exclude = array(
			array(
				'property' => 'price',
				'operator' => 'equals',
				'value'    => '50',
				'mode'     => 'exclude',
			),
		);

		$result_exclude = $this->condition_engine->apply_conditions( $all_ids, $condition_exclude, 'all' );
		$expected       = array(
			$this->test_products['product_100'],
			$this->test_products['product_25'],
			$this->test_products['product_75'],
		);
		$this->assert_test(
			'Exclude mode: price = 50',
			$expected,
			$result_exclude,
			'Should match everything except product with price 50'
		);

		echo "\n";
	}

	/**
	 * Test 2: Numeric Operators
	 */
	private function test_numeric_operators(): void {
		echo "TEST 2: Numeric Operators (8 operators)\n";
		echo "----------------------------------------\n";

		$all_ids = array_values( $this->test_products );

		// Test 2.1: Equals (=)
		$this->test_operator(
			'Equals (=): price = 50',
			$all_ids,
			array(
				array(
					'property' => 'price',
					'operator' => 'equals',
					'value'    => '50',
				),
			),
			array( $this->test_products['product_50'] )
		);

		// Test 2.2: Not Equals (!=)
		$this->test_operator(
			'Not Equals (!=): price != 50',
			$all_ids,
			array(
				array(
					'property' => 'price',
					'operator' => 'not_equals',
					'value'    => '50',
				),
			),
			array(
				$this->test_products['product_100'],
				$this->test_products['product_25'],
				$this->test_products['product_75'],
			)
		);

		// Test 2.3: Greater Than (>)
		$this->test_operator(
			'Greater Than (>): price > 50',
			$all_ids,
			array(
				array(
					'property' => 'price',
					'operator' => 'greater_than',
					'value'    => '50',
				),
			),
			array(
				$this->test_products['product_100'],
				$this->test_products['product_75'],
			)
		);

		// Test 2.4: Greater Than or Equal (>=)
		$this->test_operator(
			'Greater Than or Equal (>=): price >= 50',
			$all_ids,
			array(
				array(
					'property' => 'price',
					'operator' => 'greater_than_equal',
					'value'    => '50',
				),
			),
			array(
				$this->test_products['product_50'],
				$this->test_products['product_100'],
				$this->test_products['product_75'],
			)
		);

		// Test 2.5: Less Than (<)
		$this->test_operator(
			'Less Than (<): price < 50',
			$all_ids,
			array(
				array(
					'property' => 'price',
					'operator' => 'less_than',
					'value'    => '50',
				),
			),
			array( $this->test_products['product_25'] )
		);

		// Test 2.6: Less Than or Equal (<=)
		$this->test_operator(
			'Less Than or Equal (<=): price <= 50',
			$all_ids,
			array(
				array(
					'property' => 'price',
					'operator' => 'less_than_equal',
					'value'    => '50',
				),
			),
			array(
				$this->test_products['product_50'],
				$this->test_products['product_25'],
			)
		);

		// Test 2.7: Between
		$this->test_operator(
			'Between: price BETWEEN 40 AND 80',
			$all_ids,
			array(
				array(
					'property' => 'price',
					'operator' => 'between',
					'value'    => '40',
					'value2'   => '80',
				),
			),
			array(
				$this->test_products['product_50'],
				$this->test_products['product_75'],
			)
		);

		// Test 2.8: Not Between
		$this->test_operator(
			'Not Between: price NOT BETWEEN 40 AND 80',
			$all_ids,
			array(
				array(
					'property' => 'price',
					'operator' => 'not_between',
					'value'    => '40',
					'value2'   => '80',
				),
			),
			array(
				$this->test_products['product_100'],
				$this->test_products['product_25'],
			)
		);

		echo "\n";
	}

	/**
	 * Test 3: Text Operators
	 */
	private function test_text_operators(): void {
		echo "TEST 3: Text Operators (4 operators)\n";
		echo "-------------------------------------\n";

		$all_ids = array_values( $this->test_products );

		// Test 3.1: Contains
		$this->test_operator(
			'Contains: SKU contains "SKU"',
			$all_ids,
			array(
				array(
					'property' => 'sku',
					'operator' => 'contains',
					'value'    => 'SKU',
				),
			),
			array(
				$this->test_products['product_50'],
				$this->test_products['product_100'],
				$this->test_products['product_25'],
			)
		);

		// Test 3.2: Not Contains
		$this->test_operator(
			'Not Contains: SKU not contains "AWESOME"',
			$all_ids,
			array(
				array(
					'property' => 'sku',
					'operator' => 'not_contains',
					'value'    => 'AWESOME',
				),
			),
			array(
				$this->test_products['product_50'],
				$this->test_products['product_100'],
				$this->test_products['product_25'],
			)
		);

		// Test 3.3: Starts With
		$this->test_operator(
			'Starts With: SKU starts with "SKU"',
			$all_ids,
			array(
				array(
					'property' => 'sku',
					'operator' => 'starts_with',
					'value'    => 'SKU',
				),
			),
			array(
				$this->test_products['product_50'],
				$this->test_products['product_100'],
				$this->test_products['product_25'],
			)
		);

		// Test 3.4: Ends With
		$this->test_operator(
			'Ends With: SKU ends with "75"',
			$all_ids,
			array(
				array(
					'property' => 'sku',
					'operator' => 'ends_with',
					'value'    => '75',
				),
			),
			array( $this->test_products['product_75'] )
		);

		echo "\n";
	}

	/**
	 * Test 4: Select Operators
	 */
	private function test_select_operators(): void {
		echo "TEST 4: Select Operators (2 operators)\n";
		echo "---------------------------------------\n";

		$all_ids = array_values( $this->test_products );

		// Test 4.1: In
		$this->test_operator(
			'In: stock_status IN (instock)',
			$all_ids,
			array(
				array(
					'property' => 'stock_status',
					'operator' => 'in',
					'value'    => 'instock',
				),
			),
			$all_ids // All test products are in stock
		);

		// Test 4.2: Not In
		$this->test_operator(
			'Not In: stock_status NOT IN (outofstock)',
			$all_ids,
			array(
				array(
					'property' => 'stock_status',
					'operator' => 'not_in',
					'value'    => 'outofstock',
				),
			),
			$all_ids // All test products are not out of stock
		);

		echo "\n";
	}

	/**
	 * Test 5: Multiple Conditions with AND Logic
	 */
	private function test_multiple_conditions_and_logic(): void {
		echo "TEST 5: Multiple Conditions - AND Logic\n";
		echo "----------------------------------------\n";

		$all_ids = array_values( $this->test_products );

		// Test 5.1: price > 40 AND stock > 8
		$conditions = array(
			array(
				'property' => 'price',
				'operator' => 'greater_than',
				'value'    => '40',
			),
			array(
				'property' => 'stock_quantity',
				'operator' => 'greater_than',
				'value'    => '8',
			),
		);

		$result = $this->condition_engine->apply_conditions( $all_ids, $conditions, 'all' );
		$expected = array(
			$this->test_products['product_50'],   // Price 50, Stock 10
			$this->test_products['product_100'],  // Price 100, Stock 20
			$this->test_products['product_75'],   // Price 75, Stock 15
		);
		$this->assert_test(
			'AND Logic: price > 40 AND stock > 8',
			$expected,
			$result,
			'Both conditions must match'
		);

		// Test 5.2: price BETWEEN 40-80 AND stock < 12
		$conditions2 = array(
			array(
				'property' => 'price',
				'operator' => 'between',
				'value'    => '40',
				'value2'   => '80',
			),
			array(
				'property' => 'stock_quantity',
				'operator' => 'less_than',
				'value'    => '12',
			),
		);

		$result2 = $this->condition_engine->apply_conditions( $all_ids, $conditions2, 'all' );
		$expected2 = array( $this->test_products['product_50'] ); // Price 50, Stock 10
		$this->assert_test(
			'AND Logic: price BETWEEN 40-80 AND stock < 12',
			$expected2,
			$result2,
			'Both conditions must match'
		);

		echo "\n";
	}

	/**
	 * Test 6: Multiple Conditions with OR Logic
	 */
	private function test_multiple_conditions_or_logic(): void {
		echo "TEST 6: Multiple Conditions - OR Logic\n";
		echo "---------------------------------------\n";

		$all_ids = array_values( $this->test_products );

		// Test 6.1: price = 50 OR price = 100
		$conditions = array(
			array(
				'property' => 'price',
				'operator' => 'equals',
				'value'    => '50',
			),
			array(
				'property' => 'price',
				'operator' => 'equals',
				'value'    => '100',
			),
		);

		$result = $this->condition_engine->apply_conditions( $all_ids, $conditions, 'any' );
		$expected = array(
			$this->test_products['product_50'],
			$this->test_products['product_100'],
		);
		$this->assert_test(
			'OR Logic: price = 50 OR price = 100',
			$expected,
			$result,
			'Either condition can match'
		);

		// Test 6.2: stock < 8 OR price > 90
		$conditions2 = array(
			array(
				'property' => 'stock_quantity',
				'operator' => 'less_than',
				'value'    => '8',
			),
			array(
				'property' => 'price',
				'operator' => 'greater_than',
				'value'    => '90',
			),
		);

		$result2 = $this->condition_engine->apply_conditions( $all_ids, $conditions2, 'any' );
		$expected2 = array(
			$this->test_products['product_25'],  // Stock 5
			$this->test_products['product_100'], // Price 100
		);
		$this->assert_test(
			'OR Logic: stock < 8 OR price > 90',
			$expected2,
			$result2,
			'Either condition can match'
		);

		echo "\n";
	}

	/**
	 * Test 7: Format Compatibility
	 */
	private function test_format_compatibility(): void {
		echo "TEST 7: Format Compatibility\n";
		echo "----------------------------\n";

		$all_ids = array_values( $this->test_products );

		// Test 7.1: UI Format {type, operator, value, mode}
		$ui_format = array(
			array(
				'type'     => 'price',
				'operator' => 'equals',
				'value'    => '50',
				'mode'     => 'include',
			),
		);

		$result = $this->condition_engine->apply_conditions( $all_ids, $ui_format, 'all' );
		$expected = array( $this->test_products['product_50'] );
		$this->assert_test(
			'UI Format: {type, operator, value}',
			$expected,
			$result,
			'Should work with "type" field'
		);

		// Test 7.2: Database Format {condition_type, operator, value, mode}
		$db_format = array(
			array(
				'condition_type' => 'price',
				'operator'       => 'equals',
				'value'          => '100',
				'mode'           => 'include',
			),
		);

		$result2 = $this->condition_engine->apply_conditions( $all_ids, $db_format, 'all' );
		$expected2 = array( $this->test_products['product_100'] );
		$this->assert_test(
			'Database Format: {condition_type, operator, value}',
			$expected2,
			$result2,
			'Should work with "condition_type" field'
		);

		// Test 7.3: Engine Format {property, operator, values[], mode}
		$engine_format = array(
			array(
				'property' => 'price',
				'operator' => 'between',
				'values'   => array( '40', '80' ),
				'mode'     => 'include',
			),
		);

		$result3 = $this->condition_engine->apply_conditions( $all_ids, $engine_format, 'all' );
		$expected3 = array(
			$this->test_products['product_50'],
			$this->test_products['product_75'],
		);
		$this->assert_test(
			'Engine Format: {property, operator, values[]}',
			$expected3,
			$result3,
			'Should work with "property" and "values[]" fields'
		);

		// Test 7.4: HTML-encoded operators
		$html_encoded = array(
			array(
				'property' => 'price',
				'operator' => '&lt;', // HTML-encoded <
				'value'    => '50',
			),
		);

		$result4 = $this->condition_engine->apply_conditions( $all_ids, $html_encoded, 'all' );
		$expected4 = array( $this->test_products['product_25'] );
		$this->assert_test(
			'HTML-encoded operator: &lt; decoded to <',
			$expected4,
			$result4,
			'Should decode HTML entities in operators'
		);

		echo "\n";
	}

	/**
	 * Test 8: Edge Cases
	 */
	private function test_edge_cases(): void {
		echo "TEST 8: Edge Cases\n";
		echo "------------------\n";

		$all_ids = array_values( $this->test_products );

		// Test 8.1: Empty conditions array
		$result1 = $this->condition_engine->apply_conditions( $all_ids, array(), 'all' );
		$this->assert_test(
			'Empty conditions array',
			$all_ids,
			$result1,
			'Should return all products when no conditions'
		);

		// Test 8.2: Empty product IDs array
		$conditions = array(
			array(
				'property' => 'price',
				'operator' => 'equals',
				'value'    => '50',
			),
		);
		$result2 = $this->condition_engine->apply_conditions( array(), $conditions, 'all' );
		$this->assert_test(
			'Empty product IDs array',
			array(),
			$result2,
			'Should return empty array when no products'
		);

		// Test 8.3: Between with missing value2
		$invalid_between = array(
			array(
				'property' => 'price',
				'operator' => 'between',
				'value'    => '40',
				// missing value2
			),
		);
		$result3 = $this->condition_engine->apply_conditions( $all_ids, $invalid_between, 'all' );
		$this->assert_test(
			'Between with missing value2',
			$all_ids,
			$result3,
			'Should skip invalid condition and return all products'
		);

		// Test 8.4: Non-existent property
		$invalid_property = array(
			array(
				'property' => 'non_existent_field',
				'operator' => 'equals',
				'value'    => '50',
			),
		);
		$result4 = $this->condition_engine->apply_conditions( $all_ids, $invalid_property, 'all' );
		$this->assert_test(
			'Non-existent property',
			$all_ids,
			$result4,
			'Should skip invalid condition and return all products'
		);

		// Test 8.5: Invalid operator
		$invalid_operator = array(
			array(
				'property' => 'price',
				'operator' => 'invalid_op',
				'value'    => '50',
			),
		);
		$result5 = $this->condition_engine->apply_conditions( $all_ids, $invalid_operator, 'all' );
		$this->assert_test(
			'Invalid operator',
			$all_ids,
			$result5,
			'Should skip invalid condition and return all products'
		);

		// Test 8.6: Float comparison tolerance
		$float_condition = array(
			array(
				'property' => 'price',
				'operator' => 'equals',
				'value'    => '50.00',
			),
		);
		$result6 = $this->condition_engine->apply_conditions( $all_ids, $float_condition, 'all' );
		$expected6 = array( $this->test_products['product_50'] );
		$this->assert_test(
			'Float comparison tolerance',
			$expected6,
			$result6,
			'Should handle float equality with tolerance'
		);

		echo "\n";
	}

	/**
	 * Test 9: Error Handling
	 */
	private function test_error_handling(): void {
		echo "TEST 9: Error Handling\n";
		echo "----------------------\n";

		$all_ids = array_values( $this->test_products );

		// Test 9.1: Malformed condition (not an array)
		$malformed1 = array( 'not_an_array' );
		$result1    = $this->condition_engine->apply_conditions( $all_ids, $malformed1, 'all' );
		$this->assert_test(
			'Malformed condition (string instead of array)',
			$all_ids,
			$result1,
			'Should skip invalid condition gracefully'
		);

		// Test 9.2: Condition missing required fields
		$missing_fields = array(
			array(
				'value' => '50',
				// missing property and operator
			),
		);
		$result2 = $this->condition_engine->apply_conditions( $all_ids, $missing_fields, 'all' );
		$this->assert_test(
			'Condition missing required fields',
			$all_ids,
			$result2,
			'Should skip incomplete condition'
		);

		// Test 9.3: Non-numeric value for numeric field
		$non_numeric = array(
			array(
				'property' => 'price',
				'operator' => 'equals',
				'value'    => 'not_a_number',
			),
		);
		$validation = $this->condition_engine->validate_condition( $non_numeric[0] );
		$this->assert_boolean(
			'Non-numeric value for numeric field',
			false,
			$validation,
			'Should fail validation'
		);

		// Test 9.4: Validate condition method
		$valid_condition = array(
			'property' => 'price',
			'operator' => 'greater_than',
			'value'    => '50',
		);
		$validation2 = $this->condition_engine->validate_condition( $valid_condition );
		$this->assert_boolean(
			'Valid condition validation',
			true,
			$validation2,
			'Should pass validation'
		);

		echo "\n";
	}

	/**
	 * Helper: Test operator
	 */
	private function test_operator( string $name, array $product_ids, array $conditions, array $expected ): void {
		$result = $this->condition_engine->apply_conditions( $product_ids, $conditions, 'all' );
		$this->assert_test( $name, $expected, $result );
	}

	/**
	 * Helper: Assert test result
	 */
	private function assert_test( string $name, array $expected, array $actual, string $description = '' ): void {
		sort( $expected );
		sort( $actual );

		$passed = $expected === $actual;

		$this->results[] = array(
			'name'        => $name,
			'passed'      => $passed,
			'expected'    => $expected,
			'actual'      => $actual,
			'description' => $description,
		);

		$status = $passed ? '✅ PASS' : '❌ FAIL';
		echo sprintf( "%s: %s\n", $status, $name );

		if ( ! $passed ) {
			echo "  Expected: " . json_encode( $expected ) . "\n";
			echo "  Actual:   " . json_encode( $actual ) . "\n";
		}

		if ( $description ) {
			echo "  → " . $description . "\n";
		}
	}

	/**
	 * Helper: Assert boolean result
	 */
	private function assert_boolean( string $name, bool $expected, bool $actual, string $description = '' ): void {
		$passed = $expected === $actual;

		$this->results[] = array(
			'name'        => $name,
			'passed'      => $passed,
			'expected'    => $expected ? 'true' : 'false',
			'actual'      => $actual ? 'true' : 'false',
			'description' => $description,
		);

		$status = $passed ? '✅ PASS' : '❌ FAIL';
		echo sprintf( "%s: %s\n", $status, $name );

		if ( ! $passed ) {
			echo "  Expected: " . ( $expected ? 'true' : 'false' ) . "\n";
			echo "  Actual:   " . ( $actual ? 'true' : 'false' ) . "\n";
		}

		if ( $description ) {
			echo "  → " . $description . "\n";
		}
	}

	/**
	 * Print test summary
	 */
	private function print_summary(): void {
		$total  = count( $this->results );
		$passed = count( array_filter( $this->results, fn( $r ) => $r['passed'] ) );
		$failed = $total - $passed;

		$pass_rate = $total > 0 ? round( ( $passed / $total ) * 100, 2 ) : 0;

		echo "\n";
		echo "========================================\n";
		echo "TEST SUMMARY\n";
		echo "========================================\n";
		echo sprintf( "Total Tests:  %d\n", $total );
		echo sprintf( "Passed:       %d\n", $passed );
		echo sprintf( "Failed:       %d\n", $failed );
		echo sprintf( "Pass Rate:    %.2f%%\n", $pass_rate );
		echo "========================================\n\n";

		if ( $failed > 0 ) {
			echo "FAILED TESTS:\n";
			echo "-------------\n";
			foreach ( $this->results as $result ) {
				if ( ! $result['passed'] ) {
					echo "❌ " . $result['name'] . "\n";
					echo "   Expected: " . json_encode( $result['expected'] ) . "\n";
					echo "   Actual:   " . json_encode( $result['actual'] ) . "\n\n";
				}
			}
		}

		// Cleanup test products
		$this->cleanup_test_products();
	}

	/**
	 * Cleanup test products
	 */
	private function cleanup_test_products(): void {
		foreach ( $this->test_products as $product_id ) {
			wp_delete_post( $product_id, true );
		}
	}
}

// Run tests if accessed directly
if ( basename( $_SERVER['SCRIPT_FILENAME'] ) === basename( __FILE__ ) ) {
	$test_runner = new SCD_Conditions_Comprehensive_Test();
	$test_runner->run_all_tests();
}
