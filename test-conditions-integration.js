/**
 * WordPress Integration Test for Conditions Validator Fix
 *
 * USAGE:
 * 1. Navigate to: WP Admin > Smart Cycle Discounts > Create/Edit Campaign > Products Step
 * 2. Open browser console (F12)
 * 3. Copy and paste this entire file into the console
 * 4. Press Enter
 * 5. Results will display in the console
 *
 * @package SmartCycleDiscounts
 * @since 1.0.0
 */

(function($) {
    'use strict';

    console.clear();
    console.log('%c🧪 SCD Conditions Validator - Integration Test', 'background: #2271b1; color: white; padding: 5px 10px; font-size: 16px; font-weight: bold;');
    console.log('%cVerifying conditionType field name fix...', 'color: #666; font-style: italic;');
    console.log('');

    // Test results container
    const results = {
        passed: 0,
        failed: 0,
        tests: []
    };

    /**
     * Helper: Log test result
     */
    function logTest(name, passed, details) {
        results.tests.push({ name, passed, details });
        if (passed) {
            results.passed++;
            console.log('%c✓ PASS', 'color: #00a32a; font-weight: bold;', name);
            console.log('  ', details);
        } else {
            results.failed++;
            console.error('%c✗ FAIL', 'color: #d63638; font-weight: bold;', name);
            console.error('  ', details);
        }
        console.log('');
    }

    /**
     * Test 1: Check if validator exists
     */
    function test1_validatorExists() {
        const exists = window.SCD &&
                      window.SCD.Modules &&
                      window.SCD.Modules.Products &&
                      window.SCD.Modules.Products.ConditionsValidator;

        logTest(
            'Validator module exists',
            exists,
            exists
                ? 'SCD.Modules.Products.ConditionsValidator is available'
                : 'Module not found. Make sure you are on the Products step page.'
        );

        return exists;
    }

    /**
     * Test 2: Check if getConditionFromRow method exists
     */
    function test2_getConditionFromRowExists() {
        const exists = window.SCD &&
                      window.SCD.Modules &&
                      window.SCD.Modules.Products &&
                      window.SCD.Modules.Products.ConditionsValidator &&
                      window.SCD.Modules.Products.ConditionsValidator.prototype &&
                      typeof window.SCD.Modules.Products.ConditionsValidator.prototype.getConditionFromRow === 'function';

        logTest(
            'getConditionFromRow() method exists',
            exists,
            exists
                ? 'Method found on validator prototype'
                : 'Method not found. Validator may not be loaded.'
        );

        return exists;
    }

    /**
     * Test 3: Test getConditionFromRow returns conditionType
     */
    function test3_getConditionFromRowReturnsConditionType() {
        if (!test2_getConditionFromRowExists()) {
            logTest(
                'getConditionFromRow() returns conditionType',
                false,
                'Skipped: Method not available'
            );
            return false;
        }

        // Create a mock row
        const $mockRow = $('<div class="scd-condition-row"></div>');
        $mockRow.append('<select class="scd-condition-type"><option value="price" selected>Price</option></select>');
        $mockRow.append('<select class="scd-condition-operator"><option value=">=" selected>>=</option></select>');
        $mockRow.append('<input class="scd-condition-value" value="50">');
        $mockRow.append('<input class="scd-condition-value-between" value="">');
        $mockRow.append('<select class="scd-condition-mode"><option value="include" selected>Include</option></select>');

        // Call the actual method
        const mockValidator = {
            getConditionFromRow: window.SCD.Modules.Products.ConditionsValidator.prototype.getConditionFromRow
        };

        const condition = mockValidator.getConditionFromRow($mockRow);

        const hasConditionType = condition && condition.hasOwnProperty('conditionType');
        const hasType = condition && condition.hasOwnProperty('type');

        logTest(
            'getConditionFromRow() returns conditionType (not type)',
            hasConditionType && !hasType,
            hasConditionType && !hasType
                ? `✓ Returned object has "conditionType": "${condition.conditionType}" (no "type" property)`
                : `✗ Object structure: ${JSON.stringify(Object.keys(condition || {}))}`
        );

        return hasConditionType && !hasType;
    }

    /**
     * Test 4: Check if real condition rows exist
     */
    function test4_realConditionRowsExist() {
        const $rows = $('.scd-condition-row');
        const exists = $rows.length > 0;

        logTest(
            'Real condition rows exist on page',
            true, // Always pass, just informational
            exists
                ? `Found ${$rows.length} condition row(s) on the page`
                : 'No condition rows found. Add some conditions to test with real data.'
        );

        return exists;
    }

    /**
     * Test 5: Test with real condition row (if exists)
     */
    function test5_testRealConditionRow() {
        const $firstRow = $('.scd-condition-row').first();

        if ($firstRow.length === 0) {
            logTest(
                'Test with real condition row',
                true,
                'Skipped: No real conditions on page. This is OK - add conditions to test further.'
            );
            return true;
        }

        const mockValidator = {
            getConditionFromRow: window.SCD.Modules.Products.ConditionsValidator.prototype.getConditionFromRow
        };

        const condition = mockValidator.getConditionFromRow($firstRow);

        const hasConditionType = condition && condition.hasOwnProperty('conditionType');
        const conditionTypeValue = condition && condition.conditionType;

        logTest(
            'Real condition row returns valid conditionType',
            hasConditionType && conditionTypeValue !== '',
            hasConditionType
                ? `✓ Real condition has conditionType: "${conditionTypeValue}"`
                : '✗ Real condition missing conditionType or has empty value'
        );

        return hasConditionType;
    }

    /**
     * Test 6: Verify validator instance can be created
     */
    function test6_canCreateValidatorInstance() {
        try {
            const mockState = {};
            const validator = new window.SCD.Modules.Products.ConditionsValidator(mockState);
            const created = validator !== null && validator !== undefined;

            logTest(
                'Validator instance can be created',
                created,
                created
                    ? 'Successfully created validator instance'
                    : 'Failed to create validator instance'
            );

            return created;
        } catch (error) {
            logTest(
                'Validator instance can be created',
                false,
                `Error creating instance: ${error.message}`
            );
            return false;
        }
    }

    /**
     * Test 7: Check validation method signatures
     */
    function test7_validationMethodsExist() {
        const proto = window.SCD.Modules.Products.ConditionsValidator.prototype;
        const methods = [
            'checkBetweenInverted',
            'checkSamePropertyContradiction',
            'checkNumericRangeContradiction',
            'checkBooleanContradiction',
            'checkTextPatternConflict'
        ];

        const missing = [];
        methods.forEach(method => {
            if (typeof proto[method] !== 'function') {
                missing.push(method);
            }
        });

        const allExist = missing.length === 0;

        logTest(
            'Core validation methods exist',
            allExist,
            allExist
                ? `✓ All ${methods.length} core validation methods found`
                : `✗ Missing methods: ${missing.join(', ')}`
        );

        return allExist;
    }

    /**
     * Run all tests
     */
    function runAllTests() {
        console.log('%c━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━', 'color: #ddd;');
        console.log('%cRunning Tests...', 'color: #2271b1; font-weight: bold; font-size: 14px;');
        console.log('');

        test1_validatorExists();
        test2_getConditionFromRowExists();
        test3_getConditionFromRowReturnsConditionType();
        test4_realConditionRowsExist();
        test5_testRealConditionRow();
        test6_canCreateValidatorInstance();
        test7_validationMethodsExist();

        console.log('%c━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━', 'color: #ddd;');
        console.log('%c📊 Test Summary', 'background: #f0f6fc; color: #0073aa; padding: 5px 10px; font-size: 14px; font-weight: bold;');
        console.log('');
        console.log(`  Total Tests: ${results.tests.length}`);
        console.log(`  %cPassed: ${results.passed}`, 'color: #00a32a; font-weight: bold;');
        console.log(`  %cFailed: ${results.failed}`, results.failed > 0 ? 'color: #d63638; font-weight: bold;' : 'color: #666;');
        console.log('');

        if (results.failed === 0) {
            console.log('%c✅ All tests passed! The conditionType fix is working correctly.', 'background: #d5f4e6; color: #00551a; padding: 10px; font-weight: bold; font-size: 14px;');
        } else {
            console.log('%c❌ Some tests failed. Please review the implementation.', 'background: #fcf0f1; color: #8a0000; padding: 10px; font-weight: bold; font-size: 14px;');
        }

        console.log('');
        console.log('%c━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━', 'color: #ddd;');
        console.log('%c💡 Next Steps:', 'color: #2271b1; font-weight: bold;');
        console.log('');
        console.log('1. Add a condition with: Price >= 50');
        console.log('2. Add another condition with: Price <= 100');
        console.log('3. Verify no errors appear (valid range)');
        console.log('');
        console.log('4. Add a condition with: Price = 50');
        console.log('5. Add another condition with: Price = 100');
        console.log('6. Should show ERROR: "price cannot equal \'50\' AND \'100\' simultaneously"');
        console.log('');
        console.log('%c━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━', 'color: #ddd;');
    }

    // Execute tests
    runAllTests();

})(jQuery);
