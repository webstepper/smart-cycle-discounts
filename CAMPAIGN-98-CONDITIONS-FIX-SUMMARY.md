# Campaign 98 Conditions Fix - Complete Summary

## Problem Description

Campaign 98 was created with:
- Product Selection: "All Products" from "All Categories"
- Conditions:
  - Regular price > €10 AND
  - Regular price < €100

**Issues Found**:
1. Conditions disappeared from UI after launching campaign
2. Discounts applied to ALL products, ignoring the price conditions
3. Products with prices like €150 and €120 were incorrectly included

## Root Causes Identified

### 1. ✅ Campaign Not Loading Conditions (FIXED)
**Cause**: Campaign class had no `conditions` property and repository wasn't loading them.

**Fix**:
- Added `private array $conditions` property to `class-campaign.php`
- Added `get_conditions()` and `set_conditions()` methods
- Updated `hydrate()` in `class-campaign-repository.php` to load conditions from database

**File**: `includes/core/campaigns/class-campaign.php` (lines 225-234, 572-578)
**File**: `includes/database/repositories/class-campaign-repository.php` (lines 1213-1219)

---

### 2. ✅ Field Name Mismatch (FIXED)
**Cause**: Database stores `condition_type` but code was checking for `type`.

**Fix**: Updated `transform_conditions_for_engine()` to support both formats:
```php
$condition_type = $condition['type'] ?? $condition['condition_type'] ?? null;
```

**File**: `includes/core/products/class-product-selector.php` (lines 1567-1568)

---

### 3. ✅ Operator Symbol vs Name Mismatch (FIXED)
**Cause**: Database stores operator symbols (`>`, `<`) but condition engine's `$supported_operators` array is keyed by names (`greater_than`, `less_than`).

**Fix**: Created `normalize_operator()` method to map symbols to names:
```php
private function normalize_operator( string $operator ): string {
    $symbol_map = array(
        '='  => 'equals',
        '>'  => 'greater_than',
        '<'  => 'less_than',
        '>=' => 'greater_than_equal',
        '<=' => 'less_than_equal',
        // etc...
    );
    return $symbol_map[$operator] ?? $operator;
}
```

Applied normalization in both `validate_condition()` and `apply_single_condition()`.

**File**: `includes/core/products/class-condition-engine.php` (lines 762-793, 426, 782)

---

### 4. ✅ Values Array vs String Mismatch (FIXED)
**Cause**: Transformed conditions use `values` (array) but validation was checking `value` (string).

**Fix**: Updated validation to support both formats:
```php
if ( isset( $condition['values'] ) && is_array( $condition['values'] ) ) {
    $value = $condition['values'][0] ?? '';
} else {
    $value = $condition['value'] ?? '';
}
```

**File**: `includes/core/products/class-condition-engine.php` (lines 830-838)

---

### 5. ✅ CACHE KEY TOO GENERIC - CRITICAL BUG (FIXED)

**Cause**: The cache key in `select_products()` only checked IF conditions exist, not WHAT the conditions are:

```php
// OLD CODE (WRONG):
$cache_key = sprintf(
    'products_selection_%s_%s',
    $selection_type,
    $has_conditions ? 'conditional' : 'simple'
);
```

This created the SAME cache key for ALL campaigns with conditions:
- Campaign with "price > 10 AND price < 100" → `products_selection_all_conditional`
- Campaign with "price > 50 AND price < 200" → `products_selection_all_conditional` ← SAME KEY!

Result: All campaigns with conditions returned the SAME cached products, regardless of what the actual conditions were!

**Even worse**: The cache was populated BEFORE the previous bugs were fixed, so it contained invalid results (all products instead of filtered products).

**Fix**: Include the actual condition values in cache key:
```php
// NEW CODE (CORRECT):
if ( $has_conditions ) {
    $conditions_hash = md5( serialize( $criteria['conditions'] ) . ( $criteria['conditions_logic'] ?? 'all' ) );
    $cache_suffix    = 'cond_' . $conditions_hash;
} else {
    $cache_suffix = 'simple';
}

$cache_key = sprintf(
    'products_selection_%s_%s',
    $selection_type,
    $cache_suffix
);
```

Now each unique set of conditions gets its own cache entry:
- "price > 10 AND price < 100" → `products_selection_all_cond_abc123def456...`
- "price > 50 AND price < 200" → `products_selection_all_cond_xyz789ghi012...`

**File**: `includes/core/products/class-product-selector.php` (lines 111-128)

---

## Product Selection Flow (After Fixes)

Here's how product selection now works correctly:

1. **Build WP_Query** with meta_query from conditions (lines 146-153)
2. **Execute query** to get initial product set (lines 156-157)
3. **Apply post-query filters** for random selection, product type, etc. (line 160)
4. **Apply condition engine filtering** to enforce all conditions (lines 163-167)
5. **Cache results** with unique key that includes condition values (lines 170-172)

The condition engine is called on line 166:
```php
$product_ids = $this->condition_engine->apply_conditions(
    $product_ids,
    $transformed_conditions,
    $conditions_logic
);
```

---

## Testing the Fix

### Test Script Created
Run this URL in your browser:
```
http://vvmdov.local/wp-content/plugins/smart-cycle-discounts/verify-complete-fix.php
```

This script will:
1. ✓ Clear all caches
2. ✓ Load campaign 98 and verify conditions are loaded
3. ✓ Validate both conditions
4. ✓ Test condition engine directly (should return 3 products: 127, 126, 125)
5. ✓ Test product selector with conditions (should return same filtered set)
6. ✓ Recompile campaign and verify all products meet €10-€100 criteria

### Expected Results
- **Before fix**: 44+ products including €150 and €120 items
- **After fix**: 3-5 products, ALL between €10-€100
- Products 129 (€150) and 128 (€120) should be EXCLUDED

---

## Why This Bug Was Hard to Find

The bug was subtle because:
1. ✅ Conditions were saved to database correctly
2. ✅ Conditions were loaded from database correctly (after first fix)
3. ✅ Conditions validated correctly (after normalization fix)
4. ✅ Condition engine filtered correctly when tested directly
5. ❌ **But cached results from BEFORE the fixes were being returned**
6. ❌ **AND different campaigns shared the same cache key**

The cache system was working as designed - the problem was that the cache key didn't differentiate between different condition values.

---

## Files Modified

1. **includes/core/campaigns/class-campaign.php**
   - Added conditions property and methods

2. **includes/database/repositories/class-campaign-repository.php**
   - Load conditions during hydration

3. **includes/core/products/class-product-selector.php**
   - Support both field name formats
   - **Fixed cache key to include condition values**

4. **includes/core/products/class-condition-engine.php**
   - Added operator normalization
   - Support both value formats

---

## Cleanup Required

After confirming the fix works, delete these temporary diagnostic scripts:
- `check-campaign-98-conditions.php`
- `check-conditions-loaded.php`
- `check-migration-status.php`
- `clear-cache-and-recompile.php`
- `debug-campaign-data.php`
- `debug-condition-flow.php`
- `debug-operator-issue.php`
- `debug-product-selector-call.php`
- `debug-validation-detail.php`
- `test-condition-engine-directly.php`
- `test-cache-fix.php`
- `verify-complete-fix.php`

---

## Next Steps

1. **Test the fix**: Visit `verify-complete-fix.php` in browser
2. **Verify campaign 98**: Should now show 3-5 products (€10-€100 range only)
3. **Test on frontend**: Verify discounts apply correctly to filtered products only
4. **Clear diagnostic scripts**: Delete all temporary test files
5. **Test with new campaigns**: Create campaigns with different conditions to verify unique caching

---

## Lessons Learned

1. **Cache keys must be specific**: Include all criteria that affect results, not just presence of criteria
2. **Always test with fresh cache**: Old cached data can hide bugs
3. **Validate at every layer**: We found bugs in transformation, validation, and caching
4. **Operator normalization is critical**: UI symbols need to map to engine names
5. **Support multiple data formats**: Database format may differ from UI and engine formats
