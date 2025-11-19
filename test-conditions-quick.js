/**
 * Quick WordPress Integration Test - Copy this ENTIRE file into browser console
 * Navigate to: WP Admin > Smart Cycle Discounts > Create/Edit Campaign > Products Step
 */

(function($) {
    console.clear();
    console.log('=== SCD Conditions Validator Quick Test ===');

    var passed = 0;
    var failed = 0;

    // Test 1: Check validator exists
    if (window.SCD && window.SCD.Modules && window.SCD.Modules.Products && window.SCD.Modules.Products.ConditionsValidator) {
        console.log('%c✓ PASS', 'color: green; font-weight: bold', 'Validator module exists');
        passed++;
    } else {
        console.error('%c✗ FAIL', 'color: red; font-weight: bold', 'Validator module not found');
        failed++;
    }

    // Test 2: Check method exists
    var proto = window.SCD.Modules.Products.ConditionsValidator.prototype;
    if (proto && typeof proto.getConditionFromRow === 'function') {
        console.log('%c✓ PASS', 'color: green; font-weight: bold', 'getConditionFromRow() method exists');
        passed++;
    } else {
        console.error('%c✗ FAIL', 'color: red; font-weight: bold', 'getConditionFromRow() method not found');
        failed++;
        console.log('=== SUMMARY: ' + passed + ' passed, ' + failed + ' failed ===');
        return;
    }

    // Test 3: Test returns conditionType
    var $mockRow = $('<div class="scd-condition-row"></div>');
    $mockRow.append('<select class="scd-condition-type"><option value="price" selected>Price</option></select>');
    $mockRow.append('<select class="scd-condition-operator"><option value=">=" selected>>=</option></select>');
    $mockRow.append('<input class="scd-condition-value" value="50">');
    $mockRow.append('<input class="scd-condition-value-between" value="">');
    $mockRow.append('<select class="scd-condition-mode"><option value="include" selected>Include</option></select>');

    var mockValidator = { getConditionFromRow: proto.getConditionFromRow };
    var condition = mockValidator.getConditionFromRow($mockRow);

    if (condition && condition.hasOwnProperty('conditionType') && !condition.hasOwnProperty('type')) {
        console.log('%c✓ PASS', 'color: green; font-weight: bold', 'Returns conditionType (not type): ' + condition.conditionType);
        passed++;
    } else {
        console.error('%c✗ FAIL', 'color: red; font-weight: bold', 'Wrong property structure: ' + JSON.stringify(Object.keys(condition || {})));
        failed++;
    }

    // Test 4: Test with real condition row
    var $realRow = $('.scd-condition-row').first();
    if ($realRow.length > 0) {
        var realCondition = mockValidator.getConditionFromRow($realRow);
        if (realCondition && realCondition.conditionType) {
            console.log('%c✓ PASS', 'color: green; font-weight: bold', 'Real condition has conditionType: ' + realCondition.conditionType);
            passed++;
        } else {
            console.error('%c✗ FAIL', 'color: red; font-weight: bold', 'Real condition missing conditionType');
            failed++;
        }
    } else {
        console.log('%cℹ INFO', 'color: blue', 'No real conditions on page to test (this is OK)');
        passed++;
    }

    // Summary
    console.log('');
    console.log('=== SUMMARY ===');
    console.log('Total: ' + (passed + failed) + ' tests');
    console.log('%cPassed: ' + passed, 'color: green; font-weight: bold');
    console.log('%cFailed: ' + failed, failed > 0 ? 'color: red; font-weight: bold' : 'color: gray');

    if (failed === 0) {
        console.log('%c✅ ALL TESTS PASSED! The fix is working correctly.', 'background: #d5f4e6; color: #00551a; padding: 10px; font-weight: bold');
    } else {
        console.log('%c❌ Some tests failed. Please review the implementation.', 'background: #fcf0f1; color: #8a0000; padding: 10px; font-weight: bold');
    }

})(jQuery);
