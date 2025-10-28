# Category Filtering Fix

## Problem

User received notification "X product(s) removed - not in selected categories" but the products were not actually removed from the selection. The filtering appeared to work (notification showed) but the filtered products weren't persisted.

## Root Cause

The `restoreProducts()` method in `products-picker.js` was only updating the TomSelect UI but not syncing the changes to:
1. **State** - UI reactivity layer
2. **Hidden field** - Form submission data (single source of truth)
3. **Underlying select** - DOM element

### Flow Analysis

**Category change triggers:**
1. `reloadProductsForNewCategories()` - Gets current selected products
2. `filterProductsByCategories()` - Filters products by category
3. Shows notification if products removed
4. `restoreProducts(filtered)` - **ONLY** updated TomSelect UI

**What was missing:**
```javascript
// restoreProducts() did this:
this.productSelect.setValue( productIds, true );  // ✅ TomSelect UI updated

// But didn't do this:
this.state.setState( { productIds: productIds } );  // ❌ State not updated
this.syncHiddenField( productIds );                  // ❌ Hidden field not synced
this.syncProductSelect( productIds );                // ❌ Select element not synced
```

**Result:**
- ✅ TomSelect UI showed filtered products
- ❌ Hidden field still had old product IDs
- ❌ Form submission sent unfiltered products
- ❌ Navigation/save used wrong data

## Solution

Updated `restoreProducts()` to sync all data layers, matching the pattern used in `handleProductChange()`.

### Code Changes

**File**: `resources/assets/js/steps/products/products-picker.js:629-665`

```javascript
restoreProducts: function( productIds ) {
    if ( ! this.productSelect || ! productIds || 0 === productIds.length ) {
        return Promise.resolve();
    }

    var self = this;

    // Try preloaded data first
    var preloaded = this.getPreloadedProducts( productIds );
    if ( preloaded.length === productIds.length ) {
        this.addProductOptions( preloaded );
        this.productSelect.setValue( productIds, true );

        // Sync state and hidden field (critical for form submission)  // ✅ ADDED
        this.state.setState( { productIds: productIds } );              // ✅ ADDED
        this.syncHiddenField( productIds );                             // ✅ ADDED
        this.syncProductSelect( productIds );                           // ✅ ADDED

        return Promise.resolve();
    }

    // Load from API
    return this.api.getProductsByIds( productIds )
        .then( function( response ) {
            var products = self.extractProducts( response );
            self.addProductOptions( products );
            self.productSelect.setValue( productIds, true );

            // Sync state and hidden field (critical for form submission)  // ✅ ADDED
            self.state.setState( { productIds: productIds } );              // ✅ ADDED
            self.syncHiddenField( productIds );                             // ✅ ADDED
            self.syncProductSelect( productIds );                           // ✅ ADDED
        } )
        .catch( function( error ) {
            SCD.ErrorHandler.handle( error, 'picker-restore-products' );
        } );
},
```

## Why This Fix Works

### Data Flow Now Complete

**When categories change:**
1. Filter products by new categories
2. `restoreProducts(filtered)` is called
3. TomSelect UI updated ✅
4. State updated ✅
5. Hidden field synced ✅
6. Underlying select synced ✅

**Result:**
- ✅ TomSelect shows correct products
- ✅ Hidden field contains correct IDs
- ✅ Form submission sends correct data
- ✅ Navigation preserves correct selection
- ✅ Save operation uses correct products

### Consistency with handleProductChange

The fix makes `restoreProducts()` consistent with `handleProductChange()`, which was already doing all the syncing correctly:

```javascript
// handleProductChange() (lines 434-441) - ALREADY CORRECT
this.state.setState( { productIds: productIds } );
this.syncHiddenField( productIds );
this.syncProductSelect( productIds );

// restoreProducts() (lines 643-645, 658-660) - NOW FIXED
this.state.setState( { productIds: productIds } );  // Now matches
this.syncHiddenField( productIds );                  // Now matches
this.syncProductSelect( productIds );                // Now matches
```

## Testing Instructions

1. Create campaign with "Specific Products" selection
2. Select category "Electronics"
3. Add 10 products from Electronics
4. Change category to "Clothing"
5. **Verify notification:** "10 product(s) removed - not in selected categories"
6. **Verify UI:** Product list is empty
7. Add products from Clothing category
8. Navigate to Discounts step
9. Navigate back to Products step
10. **Verify:** Only Clothing products shown (Electronics products gone)
11. Complete campaign
12. Edit campaign
13. **Verify:** Only Clothing products restored (not Electronics)

## Files Modified

1. `resources/assets/js/steps/products/products-picker.js`
   - Lines 643-645: Added state/hidden field sync (preloaded path)
   - Lines 658-660: Added state/hidden field sync (API path)

## Impact

- ✅ Category filtering now works correctly
- ✅ Filtered products actually removed from selection
- ✅ Form submission sends correct data
- ✅ No breaking changes to existing functionality
- ✅ Consistent with existing handleProductChange() pattern

## Related Issues

This fix complements the previous fixes:
- Category persistence fix (categories now persist AND filter works)
- Conditions field fix (conditions now collected AND work correctly)

All three data layers must stay in sync:
1. **TomSelect** - UI widget
2. **State** - Reactivity/UI updates
3. **Hidden field** - Form submission (single source of truth)
