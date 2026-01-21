<?php
/**
 * Case Converter Test Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/tests
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Test Class for WSSCD_Case_Converter
 *
 * Tests bidirectional case conversion between camelCase and snake_case
 *
 * @since      1.0.0
 */
class Test_WSSCD_Case_Converter extends WP_UnitTestCase {

	/**
	 * Test setup
	 */
	public function setUp() {
		parent::setUp();

		// Load case converter
		require_once WSSCD_PLUGIN_DIR . 'includes/utilities/class-case-converter.php';
	}

	/**
	 * Test: camelCase to snake_case conversion - simple fields
	 */
	public function test_camel_to_snake_simple_fields() {
		$input = array(
			'campaignName'     => 'Test Campaign',
			'discountType'     => 'percentage',
			'discountValue'    => 15.5,
			'productIds'       => array( 1, 2, 3 ),
			'categoryIds'      => array( 10, 20 ),
			'enableRecurring'  => true,
		);

		$expected = array(
			'campaign_name'    => 'Test Campaign',
			'discount_type'    => 'percentage',
			'discount_value'   => 15.5,
			'product_ids'      => array( 1, 2, 3 ),
			'category_ids'     => array( 10, 20 ),
			'enable_recurring' => true,
		);

		$result = WSSCD_Case_Converter::camel_to_snake( $input );

		$this->assertEquals( $expected, $result, 'Simple field case conversion failed' );
	}

	/**
	 * Test: camelCase to snake_case - nested objects
	 */
	public function test_camel_to_snake_nested_objects() {
		$input = array(
			'discountType' => 'bogo',
			'bogoConfig'   => array(
				'buyQuantity'        => 2,
				'getQuantity'        => 1,
				'discountPercentage' => 100,
			),
		);

		$expected = array(
			'discount_type' => 'bogo',
			'bogo_config'   => array(
				'buy_quantity'        => 2,
				'get_quantity'        => 1,
				'discount_percentage' => 100,
			),
		);

		$result = WSSCD_Case_Converter::camel_to_snake( $input );

		$this->assertEquals( $expected, $result, 'Nested object case conversion failed' );
	}

	/**
	 * Test: camelCase to snake_case - arrays of objects (tiers)
	 */
	public function test_camel_to_snake_array_of_objects() {
		$input = array(
			'discountType' => 'tiered',
			'tiers'        => array(
				array(
					'minQuantity'   => 5,
					'discountValue' => 10,
					'discountType'  => 'percentage',
				),
				array(
					'minQuantity'   => 10,
					'discountValue' => 15,
					'discountType'  => 'percentage',
				),
			),
		);

		$expected = array(
			'discount_type' => 'tiered',
			'tiers'         => array(
				array(
					'min_quantity'   => 5,
					'discount_value' => 10,
					'discount_type'  => 'percentage',
				),
				array(
					'min_quantity'   => 10,
					'discount_value' => 15,
					'discount_type'  => 'percentage',
				),
			),
		);

		$result = WSSCD_Case_Converter::camel_to_snake( $input );

		$this->assertEquals( $expected, $result, 'Array of objects case conversion failed' );
	}

	/**
	 * Test: snake_case to camelCase - simple fields
	 */
	public function test_snake_to_camel_simple_fields() {
		$input = array(
			'campaign_name'    => 'Test Campaign',
			'discount_type'    => 'percentage',
			'discount_value'   => 15.5,
			'product_ids'      => array( 1, 2, 3 ),
			'category_ids'     => array( 10, 20 ),
			'enable_recurring' => true,
		);

		$expected = array(
			'campaignName'     => 'Test Campaign',
			'discountType'     => 'percentage',
			'discountValue'    => 15.5,
			'productIds'       => array( 1, 2, 3 ),
			'categoryIds'      => array( 10, 20 ),
			'enableRecurring'  => true,
		);

		$result = WSSCD_Case_Converter::snake_to_camel( $input );

		$this->assertEquals( $expected, $result, 'Simple field reverse conversion failed' );
	}

	/**
	 * Test: snake_case to camelCase - nested objects
	 */
	public function test_snake_to_camel_nested_objects() {
		$input = array(
			'discount_type' => 'bogo',
			'bogo_config'   => array(
				'buy_quantity'        => 2,
				'get_quantity'        => 1,
				'discount_percentage' => 100,
			),
		);

		$expected = array(
			'discountType' => 'bogo',
			'bogoConfig'   => array(
				'buyQuantity'        => 2,
				'getQuantity'        => 1,
				'discountPercentage' => 100,
			),
		);

		$result = WSSCD_Case_Converter::snake_to_camel( $input );

		$this->assertEquals( $expected, $result, 'Nested object reverse conversion failed' );
	}

	/**
	 * Test: Round-trip conversion (camel -> snake -> camel)
	 */
	public function test_round_trip_conversion() {
		$original = array(
			'campaignName'  => 'Test',
			'discountType'  => 'tiered',
			'tiers'         => array(
				array(
					'minQuantity'   => 5,
					'discountValue' => 10,
				),
			),
			'bogoConfig'    => array(
				'buyQuantity' => 2,
			),
		);

		// Round trip: camel -> snake -> camel
		$snake_case = WSSCD_Case_Converter::camel_to_snake( $original );
		$back_to_camel = WSSCD_Case_Converter::snake_to_camel( $snake_case );

		$this->assertEquals( $original, $back_to_camel, 'Round-trip conversion lost data' );
	}

	/**
	 * Test: Deep nesting (3+ levels)
	 */
	public function test_deep_nesting() {
		$input = array(
			'discountRules' => array(
				'tieredConfig' => array(
					'tierSettings' => array(
						'minQuantity' => 5,
					),
				),
			),
		);

		$expected = array(
			'discount_rules' => array(
				'tiered_config' => array(
					'tier_settings' => array(
						'min_quantity' => 5,
					),
				),
			),
		);

		$result = WSSCD_Case_Converter::camel_to_snake( $input );

		$this->assertEquals( $expected, $result, 'Deep nesting conversion failed' );
	}

	/**
	 * Test: Idempotency - already in target case
	 */
	public function test_idempotency_snake_case() {
		$input = array(
			'campaign_name' => 'Test',
			'discount_type' => 'percentage',
		);

		// Converting already snake_case data to snake_case should not change it
		$result = WSSCD_Case_Converter::camel_to_snake( $input );

		$this->assertEquals( $input, $result, 'Idempotency test failed for snake_case' );
	}

	/**
	 * Test: Preserve numeric array indices
	 */
	public function test_preserve_numeric_indices() {
		$input = array(
			'productIds' => array( 1, 2, 3, 4, 5 ),
			'tiers'      => array(
				array( 'minQuantity' => 5 ),
				array( 'minQuantity' => 10 ),
			),
		);

		$result = WSSCD_Case_Converter::camel_to_snake( $input );

		// Verify arrays are preserved (not converted to objects)
		$this->assertIsArray( $result['product_ids'], 'product_ids should be array' );
		$this->assertIsArray( $result['tiers'], 'tiers should be array' );
		$this->assertEquals( 5, count( $result['product_ids'] ), 'product_ids count mismatch' );
		$this->assertEquals( 2, count( $result['tiers'] ), 'tiers count mismatch' );
	}

	/**
	 * Test: Type preservation
	 */
	public function test_type_preservation() {
		$input = array(
			'discountValue'  => 15.5,        // float
			'buyQuantity'    => 2,           // int
			'enableFeature'  => true,        // bool
			'campaignName'   => 'Test',      // string
			'nullField'      => null,        // null
		);

		$result = WSSCD_Case_Converter::camel_to_snake( $input );

		$this->assertIsFloat( $result['discount_value'], 'Float type not preserved' );
		$this->assertIsInt( $result['buy_quantity'], 'Integer type not preserved' );
		$this->assertIsBool( $result['enable_feature'], 'Boolean type not preserved' );
		$this->assertIsString( $result['campaign_name'], 'String type not preserved' );
		$this->assertNull( $result['null_field'], 'Null type not preserved' );
	}

	/**
	 * Test: Empty arrays handling
	 */
	public function test_empty_arrays() {
		$input = array(
			'tiers'      => array(),
			'productIds' => array(),
		);

		$expected = array(
			'tiers'       => array(),
			'product_ids' => array(),
		);

		$result = WSSCD_Case_Converter::camel_to_snake( $input );

		$this->assertEquals( $expected, $result, 'Empty arrays not handled correctly' );
	}

	/**
	 * Test: Special characters in values (should not be affected)
	 */
	public function test_special_characters_in_values() {
		$input = array(
			'campaignName' => 'Test & Campaign with "Quotes"',
			'description'  => 'Special chars: < > \' " &',
		);

		$result = WSSCD_Case_Converter::camel_to_snake( $input );

		// Values should remain unchanged
		$this->assertEquals( 'Test & Campaign with "Quotes"', $result['campaign_name'] );
		$this->assertEquals( 'Special chars: < > \' " &', $result['description'] );
	}
}
