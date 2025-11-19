# Testing Guide - Phase 4

**Centralized UI Architecture - Comprehensive Testing**

**Purpose:** Validate all Phase 1-4 implementations are production-ready

**Date:** 2025-11-11

---

## Pre-Testing Checklist

### Environment Setup
- [ ] WordPress version: 5.8+ (confirmed compatible)
- [ ] WooCommerce version: 5.0+ (confirmed compatible)
- [ ] PHP version: 7.4+ (confirmed compatible)
- [ ] Browser: Chrome/Firefox latest (recommended for console debugging)
- [ ] WordPress debug mode: Enabled for testing
- [ ] Browser console: Open to monitor for errors

### File Verification
- [ ] All modified files deployed
- [ ] JavaScript syntax validated (Node.js check)
- [ ] No merge conflicts
- [ ] Index.php files present in all directories

---

## Testing Scenarios

### Test Suite 1: Phase 2 - Basic Step (Module Registry & Auto Events)

#### Test 1.1: Create New Campaign - Basic Step
**Objective:** Verify Module Registry initializes correctly

**Steps:**
1. Navigate to WP Admin → Smart Cycle Discounts → Campaigns
2. Click "Add New Campaign"
3. Open browser console
4. Verify step loads without errors

**Expected Results:**
- ✅ No JavaScript errors in console
- ✅ Form fields render correctly
- ✅ All three fields visible: Name, Description, Priority
- ✅ Default priority value: 3
- ✅ Module initialization logs (if debug enabled): `[Module Registry] Initializing modules for basic step`

**Pass/Fail:** ___________

**Notes:** ___________

---

#### Test 1.2: Field Validation - Basic Step
**Objective:** Verify field definitions work with validation

**Steps:**
1. Leave "Campaign Name" empty
2. Click "Next" button
3. Observe validation error

**Expected Results:**
- ✅ Validation error appears: "Campaign name is required"
- ✅ Error styling applied (red border)
- ✅ Focus moved to Campaign Name field
- ✅ Step does not advance

**Pass/Fail:** ___________

---

#### Test 1.3: Data Persistence - Basic Step
**Objective:** Verify data persists when navigating between steps

**Steps:**
1. Fill in Campaign Name: "Test Campaign"
2. Fill in Description: "Test Description"
3. Set Priority: 5
4. Click "Next"
5. Click "Back" button
6. Verify fields still contain entered data

**Expected Results:**
- ✅ Campaign Name: "Test Campaign"
- ✅ Description: "Test Description"
- ✅ Priority: 5
- ✅ No data loss

**Pass/Fail:** ___________

---

### Test Suite 2: Phase 3 - Tiered Discount (Row Factory)

#### Test 2.1: Create Tiered Discount - Percentage Mode
**Objective:** Verify Row Factory generates tier rows correctly

**Steps:**
1. Complete Basic step
2. Complete Products step
3. On Discounts step, select "Tiered Discount"
4. Select "Percentage" mode
5. Click "Add Percentage Tier"
6. Verify row appears

**Expected Results:**
- ✅ Tier row renders with two fields:
  - Minimum Quantity (number input)
  - Discount Value (number input with % prefix)
- ✅ Remove button visible
- ✅ Fields have correct placeholders
- ✅ No JavaScript errors

**Pass/Fail:** ___________

---

#### Test 2.2: Add Multiple Tiers - Percentage Mode
**Objective:** Verify multiple rows render correctly

**Steps:**
1. Click "Add Percentage Tier" 3 times
2. Fill tier 1: Quantity 5, Discount 10%
3. Fill tier 2: Quantity 10, Discount 20%
4. Fill tier 3: Quantity 15, Discount 30%

**Expected Results:**
- ✅ All 3 rows render correctly
- ✅ Each row has unique index (data-index attribute)
- ✅ All remove buttons functional
- ✅ Preview text updates correctly
- ✅ Progression tip does NOT appear (discounts are ascending)

**Pass/Fail:** ___________

**Preview Text:** ___________

---

#### Test 2.3: Tier Progression Warning
**Objective:** Verify warning appears for non-ascending discounts

**Steps:**
1. Edit tier 2: Change discount to 5%
2. Tab out of field

**Expected Results:**
- ✅ Warning appears: "Tip: Higher quantity tiers usually have bigger discounts."
- ✅ Warning has dashicons warning icon
- ✅ Warning positioned after tiers list

**Pass/Fail:** ___________

---

#### Test 2.4: Remove Tier
**Objective:** Verify tier removal works

**Steps:**
1. Click "Remove" button on tier 2
2. Verify tier removed

**Expected Results:**
- ✅ Tier 2 row disappears
- ✅ Remaining tiers: tier 1 and tier 3
- ✅ Indexes updated correctly
- ✅ Preview text updates
- ✅ No errors in console

**Pass/Fail:** ___________

---

#### Test 2.5: Fixed Amount Mode
**Objective:** Verify fixed mode works

**Steps:**
1. Select "Fixed Amount" mode radio button
2. Click "Add Fixed Amount Tier"
3. Fill tier: Quantity 5, Discount 10

**Expected Results:**
- ✅ Percentage tiers hidden
- ✅ Fixed tiers visible
- ✅ Currency prefix ($) on discount field
- ✅ Preview updates correctly

**Pass/Fail:** ___________

**Preview Text:** ___________

---

#### Test 2.6: Save Campaign with Tiers
**Objective:** Verify tier data persists

**Steps:**
1. Add 2 percentage tiers:
   - Tier 1: Qty 5, Discount 10%
   - Tier 2: Qty 10, Discount 20%
2. Complete remaining steps
3. Save campaign
4. Edit campaign
5. Navigate to Discounts step
6. Verify tiers loaded

**Expected Results:**
- ✅ Both tiers render correctly
- ✅ Values match saved data
- ✅ Mode (percentage) selected correctly
- ✅ No data loss

**Pass/Fail:** ___________

---

### Test Suite 3: Phase 3 - Spend Threshold (Row Factory)

#### Test 3.1: Create Spend Threshold - Percentage Mode
**Objective:** Verify Row Factory for spend thresholds

**Steps:**
1. On Discounts step, select "Spend Threshold"
2. Select "Percentage" mode
3. Click "Add Threshold"

**Expected Results:**
- ✅ Threshold row renders with two fields:
  - Spend Amount (number input with $ prefix)
  - Discount Value (number input with % prefix)
- ✅ Remove button visible
- ✅ Placeholders: "100.00" and "10"
- ✅ Max value on discount: 100

**Pass/Fail:** ___________

---

#### Test 3.2: Add Multiple Thresholds
**Objective:** Verify multiple threshold rows

**Steps:**
1. Add 3 thresholds:
   - Threshold 1: Spend $50, Discount 5%
   - Threshold 2: Spend $100, Discount 10%
   - Threshold 3: Spend $200, Discount 15%

**Expected Results:**
- ✅ All 3 rows render
- ✅ Rows sorted by spend amount (ascending)
- ✅ Preview text updates correctly
- ✅ No progression warning (discounts ascending)

**Pass/Fail:** ___________

**Preview Text:** ___________

---

#### Test 3.3: Threshold Progression Warning
**Objective:** Verify warning for non-ascending discounts

**Steps:**
1. Edit threshold 2: Change discount to 3%
2. Tab out

**Expected Results:**
- ✅ Warning appears: "Tip: Higher spending thresholds usually have bigger discounts."
- ✅ Warning positioned correctly

**Pass/Fail:** ___________

---

#### Test 3.4: Remove Threshold
**Objective:** Verify threshold removal

**Steps:**
1. Click "Remove" on threshold 2
2. Verify removed

**Expected Results:**
- ✅ Threshold 2 disappears
- ✅ Remaining thresholds intact
- ✅ Preview updates
- ✅ No errors

**Pass/Fail:** ___________

---

#### Test 3.5: Fixed Amount Mode
**Objective:** Verify fixed mode for thresholds

**Steps:**
1. Select "Fixed Amount" mode
2. Add threshold: Spend $50, Discount $5

**Expected Results:**
- ✅ Percentage thresholds hidden
- ✅ Fixed thresholds visible
- ✅ Currency prefix ($) on discount field
- ✅ No max value on discount

**Pass/Fail:** ___________

---

#### Test 3.6: Save Campaign with Thresholds
**Objective:** Verify threshold data persists

**Steps:**
1. Add 2 percentage thresholds
2. Save campaign
3. Edit campaign
4. Navigate to Discounts step
5. Verify thresholds loaded

**Expected Results:**
- ✅ Both thresholds render correctly
- ✅ Values match saved data
- ✅ Mode selected correctly

**Pass/Fail:** ___________

---

### Test Suite 4: Edge Cases & Error Handling

#### Test 4.1: Zero Values
**Objective:** Verify handling of 0 values

**Steps:**
1. Create tiered discount
2. Try entering 0 for quantity
3. Try entering 0 for discount

**Expected Results:**
- ✅ Validation error for quantity: "Tier threshold must be greater than 0"
- ✅ Validation error for discount: "Discount value must be greater than 0"

**Pass/Fail:** ___________

---

#### Test 4.2: Very Large Numbers
**Objective:** Verify handling of large values

**Steps:**
1. Create tiered discount
2. Enter quantity: 999999
3. Enter discount: 99.99%

**Expected Results:**
- ✅ Values accepted
- ✅ No JavaScript errors
- ✅ Values display correctly
- ✅ Save succeeds

**Pass/Fail:** ___________

---

#### Test 4.3: Special Characters in Text Fields
**Objective:** Verify XSS protection

**Steps:**
1. On Basic step, enter name: `<script>alert('XSS')</script>`
2. Save and reload

**Expected Results:**
- ✅ Script does NOT execute
- ✅ HTML escaped in display
- ✅ Value saved as plain text

**Pass/Fail:** ___________

---

#### Test 4.4: Maximum Tiers/Thresholds
**Objective:** Verify max limit enforcement

**Steps:**
1. Create tiered discount
2. Add 5 percentage tiers (max)
3. Try adding 6th tier

**Expected Results:**
- ✅ "Add Tier" button disabled at 5 tiers
- ✅ Button text: "Maximum tiers reached"
- ✅ Cannot add more tiers

**Pass/Fail:** ___________

---

### Test Suite 5: Cross-Step Integration

#### Test 5.1: Complete Campaign Creation
**Objective:** Verify all steps work together

**Steps:**
1. Basic: Fill all fields
2. Products: Select products
3. Discounts: Add tiered discount with 2 tiers
4. Schedule: Set dates
5. Review: Verify all data
6. Save campaign

**Expected Results:**
- ✅ All steps complete without errors
- ✅ Data persists across navigation
- ✅ Review step shows all entered data
- ✅ Campaign saves successfully

**Pass/Fail:** ___________

**Campaign ID:** ___________

---

#### Test 5.2: Edit Saved Campaign
**Objective:** Verify editing works

**Steps:**
1. Edit the campaign created in 5.1
2. Navigate to Discounts step
3. Modify tier values
4. Save campaign
5. Reload campaign

**Expected Results:**
- ✅ Campaign loads with all data
- ✅ Tier modifications persist
- ✅ No data corruption

**Pass/Fail:** ___________

---

### Test Suite 6: Browser Compatibility

#### Test 6.1: Chrome
**Browser Version:** ___________
**Overall Status:** ___________
**Notes:** ___________

---

#### Test 6.2: Firefox
**Browser Version:** ___________
**Overall Status:** ___________
**Notes:** ___________

---

#### Test 6.3: Safari (if applicable)
**Browser Version:** ___________
**Overall Status:** ___________
**Notes:** ___________

---

## Console Error Monitoring

**Instructions:** Check browser console after each test suite

### Expected Console Messages (Normal)
- ✅ Module initialization logs (if debug enabled)
- ✅ State change logs (if debug enabled)
- ✅ Row Factory creation logs (if debug enabled)

### Errors to Watch For
- ❌ "Row Factory not available"
- ❌ "Undefined is not a function"
- ❌ "Cannot read property of null"
- ❌ Any XSS-related warnings
- ❌ CORS errors

**Console Errors Found:** ___________

---

## Test Summary

### Overall Statistics
- **Total Test Scenarios:** 24
- **Passed:** ___________
- **Failed:** ___________
- **Blocked:** ___________
- **Pass Rate:** ___________

### Critical Issues
_(List any blocking issues found)_

___________

### Non-Critical Issues
_(List any minor issues found)_

___________

### Recommendations
_(List any improvements or fixes needed)_

___________

---

## Sign-Off

**Tested By:** ___________
**Date:** ___________
**Environment:** ___________
**Overall Status:** ✅ Pass / ❌ Fail / ⚠️ Conditional Pass

**Notes:**
___________

---

## Next Steps

### If All Tests Pass ✅
1. Mark Phase 4 as complete
2. Prepare for production deployment
3. Create release notes
4. Update documentation

### If Tests Fail ❌
1. Document all failures
2. Create bug tickets
3. Prioritize fixes
4. Re-test after fixes

### If Conditional Pass ⚠️
1. Document known issues
2. Assess risk level
3. Create mitigation plan
4. Proceed with caution

---

**Document Version:** 1.0.0
**Last Updated:** 2025-11-11
