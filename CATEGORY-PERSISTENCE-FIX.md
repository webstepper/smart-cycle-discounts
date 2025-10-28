# Category Persistence Fix

## Problem

After changing `category_ids` from `complex` to `array` type, category selections were not persisting during step navigation.

## Root Cause

The `category_ids` field is managed by a TomSelect widget through the `Picker` module. When the field type was changed from `complex` to `array`:

1. **Collection worked** - Standard array field collection read from `<select name="category_ids[]">`
2. **Population failed** - Standard `setFieldValue()` set values on the underlying `<select>` element but TomSelect UI wasn't updated

**Why?**
- TomSelect creates its own UI on top of the `<select>` element
- Setting `.val()` on the select doesn't automatically update TomSelect's UI
- TomSelect needs explicit `.setValue()` call on its instance
- The Picker module has the proper `setValue()` logic but it wasn't being called

## Solution

Revert `category_ids` to `type: 'complex'` with the **CORRECT handler** (`SCD.Modules.Products.Picker`) and add field-specific collection/population methods.

### Changes Made

#### 1. Added Specific Methods to Picker
**File**: `resources/assets/js/steps/products/products-picker.js:1070-1090`

```javascript
/**
 * Get category IDs only (for category_ids field collection)
 */
getCategoryIds: function() {
    return this.categorySelect ? this.categorySelect.getValue() : [ 'all' ];
},

/**
 * Set category IDs only (for category_ids field population)
 */
setCategoryIds: function( value ) {
    if ( ! this.categorySelect ) {
        this.pendingCategories = value;
        return Promise.resolve();
    }

    return this.ensureCategoryOptionsLoaded( value ).then( function() {
        this.setCategoriesOnInstance( value );
    }.bind( this ) );
},
```

#### 2. Updated Field Definition
**File**: `includes/core/validation/class-field-definitions.php:126-143`

```php
'category_ids' => array(
    'type'         => 'complex',  // Reverted to complex
    'handler'      => 'SCD.Modules.Products.Picker',  // CORRECT handler
    'methods'      => array(
        'collect'  => 'getCategoryIds',   // Field-specific method
        'populate' => 'setCategoryIds',   // Field-specific method
    ),
    // ... rest of config
),
```

## Why This Works

### Collection Flow
1. `collectData()` finds `category_ids` with `type: 'complex'`
2. Calls `collectComplexField(fieldDef)`
3. Gets handler: `SCD.Modules.Products.Picker`
4. Calls method: `Picker.getCategoryIds()`
5. Returns: Array of selected category IDs from TomSelect
6. ✅ Categories properly collected

### Population Flow
1. `populateFields()` finds `category_ids` with `type: 'complex'`
2. Calls `populateComplexField(fieldDef, value)`
3. Gets handler: `SCD.Modules.Products.Picker`
4. Calls method: `Picker.setCategoryIds(value)`
5. `setCategoryIds()`:
   - Loads category options if needed
   - Calls `setCategoriesOnInstance(value)`
   - Updates TomSelect UI directly
6. ✅ Categories properly restored in UI

## Key Insights

1. **TomSelect-backed fields should use complex type** - They need special handling
2. **Handler must exist and be registered** - `Picker` is registered in orchestrator
3. **Field-specific methods are better** - Clearer than generic `getValue()`/`setValue()`
4. **Standard field types don't work with widgets** - Widgets need explicit API calls

## Files Modified

1. `resources/assets/js/steps/products/products-picker.js`
   - Added `getCategoryIds()` method (line 1070)
   - Added `setCategoryIds()` method (line 1081)

2. `includes/core/validation/class-field-definitions.php`
   - Reverted `category_ids` to `type: 'complex'` (line 127)
   - Updated handler to `'SCD.Modules.Products.Picker'` (line 133)
   - Updated methods to use field-specific collect/populate (lines 135-136)

## Testing

1. Select specific categories in Products step
2. Navigate to Discounts step
3. Navigate back to Products step
4. **Verify**: Categories are still selected (not reset to "All")
5. Complete wizard
6. Edit campaign
7. **Verify**: Categories are properly restored

## Result

✅ Categories now persist correctly during navigation
✅ Categories properly restored when editing campaigns
✅ Clean, maintainable solution using existing Picker infrastructure
✅ No conflicts between widget and standard field systems
