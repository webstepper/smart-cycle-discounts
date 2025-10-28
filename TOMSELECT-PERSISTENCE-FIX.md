# TomSelect Persistence Fix - Root Cause Analysis & Solution

## Problem Summary
When editing a campaign (intent=edit), the Products step's TomSelect components (Category Filter and Product Search) were not being populated with saved data, despite the data being available in PHP session and loaded into JavaScript state.

## Root Cause

**Execution Order Race Condition in products-orchestrator.js**

The `onInit()` hook (lines 219-234) was attempting to populate TomSelect components immediately after initialization, but this was happening TOO EARLY in the lifecycle:

```
PROBLEMATIC FLOW:
1. orchestrator.init() called by wizard
   ├─> initializeModules() [line 55]
   │   ├─> Creates state
   │   ├─> Loads data: state.setState(stepData) [line 104]
   │   └─> Registers complex field handlers [lines 113, 146, 159, 170]
   │
   ├─> initializeUI() [line 58] - Returns Promise
   │   ├─> categoryFilter.initializeCategorySelect() [line 188]
   │   └─> tomSelect.initializeProductSearch() [line 200]
   │   └─> TomSelect instances initializing ASYNCHRONOUSLY
   │
   └─> onInit() [line 219] ← BUG WAS HERE
       ├─> categoryFilter.setValue(categoryIds) ← FAILS: TomSelect not ready
       └─> tomSelect.setValue(productIds) ← FAILS: Tom Select not ready

2. wizard.populateStepFields() [wizard line 725] ← TOO LATE
   └─> But this is the CORRECT way to populate!
```

### Why onInit() Population Failed

1. **TomSelect Initialization is Asynchronous**: Although `initializeUI()` returns a Promise that resolves when `tomSelect.init()` completes, the TomSelect instance has its own internal async initialization flow.

2. **setValue() Called on Partially-Initialized Instance**: When `onInit()` calls `setValue()`, the TomSelect instance exists but may not be fully ready to accept values.

3. **Pending Restoration Mechanism Not Triggered**: The `setValue()` method checks `if (!this.tomSelect)` and sets `pendingRestoration`, but if `this.tomSelect` exists (but isn't ready), the pending restoration is never queued.

4. **Step Persistence System Bypassed**: The proper restoration flow through `step-persistence.js::populateComplexField()` was running AFTER the failed `onInit()` attempt, but by then the damage was done.

## Solution

**Remove Premature Population Logic from onInit()**

The fix eliminates the race condition by removing all TomSelect population logic from `onInit()` and relying entirely on the step persistence system, which has proper retry logic built-in.

### File Modified
- `/resources/assets/js/steps/products/products-orchestrator.js` (lines 219-234)

### Change Made
```javascript
// BEFORE (BUGGY):
onInit: function() {
    // Populate UI from state if we have existing data (edit mode)
    if ( this.modules.state ) {
        var state = this.modules.state.getState();

        // Populate category filter if we have category data
        if ( state && state.categoryIds && this.modules.categoryFilter && 'function' === typeof this.modules.categoryFilter.setValue ) {
            this.modules.categoryFilter.setValue( state.categoryIds );  // ← RACE CONDITION
        }

        // Populate product TomSelect if we have product IDs
        if ( state && state.productIds && state.productIds.length > 0 && this.modules.tomSelect && 'function' === typeof this.modules.tomSelect.setValue ) {
            this.modules.tomSelect.setValue( state.productIds );  // ← RACE CONDITION
        }
    }
},

// AFTER (FIXED):
onInit: function() {
    // Reserved for future orchestrator-level initialization
    // Do NOT add TomSelect population logic here
    // TomSelect restoration is handled by step-persistence.js via populateComplexField()
},
```

### Why This Fix Works

The step persistence system (`step-persistence.js`) already handles complex field restoration correctly:

```
CORRECT FLOW (After Fix):
1. orchestrator.init() called by wizard
   ├─> initializeModules() [line 55]
   │   ├─> Creates state
   │   ├─> Registers complex field handlers [lines 113, 146, 159, 170]
   │   └─> READY for step-persistence to call handlers
   │
   ├─> initializeUI() [line 58]
   │   └─> TomSelect instances initializing asynchronously
   │
   └─> onInit() [line 219]
       └─> Does nothing (no premature population) ✓

2. wizard.populateStepFields() [wizard line 725]
   └─> step-persistence.js::populateFields()
       └─> populateComplexField() [line 351]
           └─> Queues restoration [line 752]
               └─> _processComplexFieldQueue() [line 759]
                   └─> Retries up to 50 times × 100ms = 5 seconds
                       └─> Calls setValue() when handlers are FULLY ready ✓
```

## Field Definitions Confirm Complex Handler Pattern

From `class-field-definitions.php`:

```php
'category_ids' => array(
    'type'     => 'complex',
    'handler'  => 'SCD.Modules.Products.CategoryFilter',
    'methods'  => array(
        'collect'  => 'getValue',
        'populate' => 'setValue',
    ),
),
'product_ids' => array(
    'type'     => 'complex',
    'handler'  => 'SCD.Modules.Products.TomSelect',
    'methods'  => array(
        'collect'  => 'getValue',
        'populate' => 'setValue',
    ),
    'conditional' => array(
        'field' => 'product_selection_type',
        'value' => 'specific_products',
    ),
),
```

This confirms that:
1. Both fields are marked as `'type' => 'complex'`
2. They have registered handlers (CategoryFilter and TomSelect)
3. Step persistence will automatically call `setValue()` via `populateComplexField()`
4. The queue/retry mechanism ensures handlers are ready before population

## Testing & Verification

**Test Case 1: Edit Campaign with Categories**
1. Edit campaign (intent=edit, id=20)
2. Navigate to Products step
3. **Expected**: Category Filter TomSelect shows previously selected categories
4. **Actual** (After Fix): Categories are populated correctly ✓

**Test Case 2: Edit Campaign with Specific Products**
1. Edit campaign with product_selection_type='specific_products'
2. Navigate to Products step
3. **Expected**: Product TomSelect shows previously selected products
4. **Actual** (After Fix): Products are populated correctly ✓

**Test Case 3: New Campaign**
1. Create new campaign (intent=new)
2. Navigate to Products step
3. **Expected**: TomSelect components show defaults (["all"] for categories, [] for products)
4. **Actual** (After Fix): Defaults work correctly ✓

## Additional Notes

### Data Flow Verification

1. **PHP → JavaScript**: Data is loaded via `wp_localize_script()` into `window.scdWizardData.current_campaign.products`
2. **Wizard StateManager**: Loads data from window into `stateManager.stepData.products`
3. **Products State**: Populated via `state.setState()` in `initializeModules()` (line 104)
4. **Step Persistence**: Calls `populateComplexField()` which delegates to handlers
5. **TomSelect UI**: Updated via `setValue()` when handlers are fully ready

### Complex Field Handler Registration

The handlers are properly registered in `initializeModules()`:
- Line 113: `registerComplexFieldHandler('products.state', this.modules.state)`
- Line 146: `registerComplexFieldHandler('SCD.Modules.Products.Filter', this.modules.filter)`
- Line 159: `registerComplexFieldHandler('SCD.Modules.Products.CategoryFilter', this.modules.categoryFilter)`
- Line 170: `registerComplexFieldHandler('SCD.Modules.Products.TomSelect', this.modules.tomSelect)`

These registrations happen BEFORE `populateFields()` is called, ensuring handlers are available when needed.

### Retry Mechanism

The step-persistence retry mechanism (`_processComplexFieldQueue()` at line 759) will:
- Check if handler is ready via `isReady()` method
- Retry every 100ms for up to 50 attempts (5 seconds total)
- Clear queue and log error if handler never becomes ready
- Call `setValue()` method once handler is confirmed ready

## Conclusion

The fix resolves the TomSelect persistence issue by:
1. **Removing the race condition**: No more premature `setValue()` calls in `onInit()`
2. **Trusting the persistence system**: Let step-persistence handle complex field restoration
3. **Proper async handling**: Retry mechanism waits until TomSelect is truly ready

This approach is more robust, maintainable, and follows the established architectural pattern for complex field handling in the wizard system.
