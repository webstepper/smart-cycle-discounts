# Testing Guide - Category System Implementation

## Quick Test Checklist

Use this guide to verify all category system features are working correctly in your browser.

---

## Test 1: Real-Time Dropdown Filtering ⚡ (NEW!)

**This is the most important test - it verifies the AJAX real-time filtering implementation.**

### Steps:
1. Navigate to Products step
2. Select "Specific Products" as selection type
3. Select "Electronics" category
4. Open the product dropdown (click it - don't type anything)
5. **✅ VERIFY:** Dropdown shows ONLY electronics products
6. Change to "Clothing" category
7. **✅ VERIFY:** Dropdown IMMEDIATELY updates to show ONLY clothing products (no typing needed)
8. Select "Electronics" + "Clothing" (both)
9. **✅ VERIFY:** Dropdown shows products from BOTH categories
10. Remove "Electronics"
11. **✅ VERIFY:** Dropdown IMMEDIATELY shows ONLY clothing products

### Expected Behavior:
- ✅ Dropdown updates INSTANTLY when categories change
- ✅ No need to type to trigger update
- ✅ Shows ONLY products from selected categories
- ✅ AJAX request sent automatically
- ✅ No errors in console

### What to Look For in Browser Console:
```javascript
// You should see AJAX request when categories change:
POST /wp-admin/admin-ajax.php
action: scd_search_products
categories: [5, 8]  // Selected category IDs
```

---

## Test 2: Category Persistence

### Steps:
1. Select "Electronics" + "Clothing" categories
2. Navigate to Discounts step
3. Navigate back to Products step
4. **✅ VERIFY:** Both categories still selected (not reset to "All")
5. Complete the wizard
6. Edit the campaign
7. **✅ VERIFY:** Categories properly restored

### Expected Behavior:
- ✅ Categories persist during navigation
- ✅ Categories restored when editing
- ✅ TomSelect UI matches hidden field value

---

## Test 3: All Products Filtered Out

### Steps:
1. Select "Electronics" category
2. Add 5 electronics products to selection
3. Change to "Clothing" category ONLY (remove Electronics)
4. **✅ VERIFY:** Notification shows "5 product(s) removed - not in selected categories"
5. **✅ VERIFY:** Product selection list is now EMPTY
6. Open browser console and check hidden field value:
   ```javascript
   jQuery('[name="product_ids"]').val()  // Should return empty string
   ```
7. Navigate to next step and back
8. **✅ VERIFY:** Product list is still empty (not restored)

### Expected Behavior:
- ✅ Clear notification displayed
- ✅ All products actually removed
- ✅ Hidden field cleared
- ✅ State cleared
- ✅ TomSelect UI cleared

---

## Test 4: Some Products Filtered Out

### Steps:
1. Select "Electronics" + "Clothing" categories
2. Add 3 electronics products
3. Add 2 clothing products
4. Remove "Electronics" category (keep only Clothing)
5. **✅ VERIFY:** Notification shows "3 product(s) removed - not in selected categories"
6. **✅ VERIFY:** Only 2 clothing products remain in selection
7. Check hidden field:
   ```javascript
   jQuery('[name="product_ids"]').val()  // Should only have 2 clothing product IDs
   ```

### Expected Behavior:
- ✅ Correct count in notification
- ✅ Only valid products remain
- ✅ Hidden field has correct IDs
- ✅ Can add more products from selected category

---

## Test 5: Rapid Category Changes

### Steps:
1. Rapidly click between different categories:
   - Click "Electronics"
   - Immediately click "Clothing"
   - Immediately click "Books"
   - Immediately click "Electronics"
2. **✅ VERIFY:** No errors in console
3. **✅ VERIFY:** Dropdown shows products from FINAL category (Electronics)
4. Open Network tab in browser DevTools
5. **✅ VERIFY:** Only ONE AJAX request sent (debounced)

### Expected Behavior:
- ✅ No errors or crashes
- ✅ Only last category selection processed
- ✅ Debouncing prevents multiple AJAX calls
- ✅ UI updates correctly

---

## Test 6: Empty Category

### Steps:
1. Create a test category with NO products (or select one that's empty)
2. Select that empty category
3. Open product dropdown
4. **✅ VERIFY:** Shows "No products found in selected categories" or similar
5. Change to a category with products
6. **✅ VERIFY:** Dropdown IMMEDIATELY shows products

### Expected Behavior:
- ✅ No errors when category is empty
- ✅ Clear feedback to user
- ✅ Immediately updates when switching to non-empty category

---

## Test 7: "All Categories" Selection

### Steps:
1. Select "Electronics" category
2. Add some products
3. Change to "All Categories"
4. **✅ VERIFY:** Selected products remain selected
5. Open product dropdown
6. **✅ VERIFY:** Dropdown shows products from ALL categories
7. Search for a product
8. **✅ VERIFY:** Search works across all categories

### Expected Behavior:
- ✅ No filtering applied
- ✅ All products available
- ✅ Selected products not removed

---

## Browser Console Debugging Commands

Use these in browser console to verify data layer sync:

```javascript
// Check TomSelect instance
var picker = SCD.Modules.Products.Picker;
picker.productSelect.getValue();  // Should match hidden field

// Check hidden field
jQuery('[name="product_ids"]').val();  // Comma-separated IDs

// Check state
picker.state.getState().productIds;  // Should match hidden field

// Check categories
picker.categorySelect.getValue();  // Selected category IDs

// Verify all three layers match
var tsValue = picker.productSelect.getValue();
var hiddenValue = jQuery('[name="product_ids"]').val().split(',').filter(Boolean);
var stateValue = picker.state.getState().productIds;
console.log('TomSelect:', tsValue);
console.log('Hidden Field:', hiddenValue);
console.log('State:', stateValue);
console.log('All Match:', JSON.stringify(tsValue) === JSON.stringify(hiddenValue) && JSON.stringify(hiddenValue) === JSON.stringify(stateValue));
```

---

## Common Issues and Solutions

### Issue: Dropdown doesn't update when categories change
**Solution:** Check browser console for errors. Verify AJAX request is being sent.

### Issue: Products not actually removed
**Solution:** Verify hidden field value matches TomSelect UI. All three data layers should match.

### Issue: Multiple AJAX requests sent
**Solution:** This is normal during rapid changes - debouncing ensures only last request completes.

### Issue: Categories don't persist
**Solution:** Check that `category_ids` field type is `complex` with correct handler.

---

## Expected Console Output (No Errors)

When everything works correctly, you should see:
- ✅ No JavaScript errors
- ✅ AJAX requests complete successfully
- ✅ Network tab shows `scd_search_products` requests with correct category filters
- ✅ All three data layers in sync (use console commands above to verify)

---

## Success Criteria

All tests pass when:
- ✅ Real-time dropdown filtering works instantly
- ✅ Categories persist during navigation
- ✅ All edge cases handled (all filtered, some filtered, none selected)
- ✅ No JavaScript errors in console
- ✅ All three data layers stay in sync
- ✅ Clean user notifications
- ✅ Debouncing prevents excessive AJAX calls

---

**After completing these tests, the category system is verified to be 100% bulletproof!**
