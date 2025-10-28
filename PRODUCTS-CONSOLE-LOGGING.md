# Products Step Console Logging - Complete Guide

## Overview

Simple, comprehensive console.log statements have been added to the products step to show exactly what happens during:
- Initialization
- Module loading
- UI setup
- User interactions
- State changes
- Category/product selection
- Section visibility updates

---

## Changes Made

### Files Modified

1. **includes/admin/assets/class-script-registry.php**
   - Removed debug logger registration
   - Restored original products module registration

2. **resources/assets/js/steps/products/products-orchestrator.js**
   - Added console.log statements to key methods
   - All logs are ALWAYS active (no conditional checks)
   - Clear, descriptive logging at every important step

### Files Removed

- `resources/assets/js/steps/products/products-debug-logger.js`
- `PRODUCTS-DEBUG-LOGGER.md`
- `add-comprehensive-logging.js`
- `update-products-debug.py`

---

## Console Output Structure

### Initialization Logs

```javascript
[Products] ========== INIT START ==========
[Products] init() called with config: {...}
[Products] Has wizard? true
[Products] Calling initializeModules()...
[Products] --- initializeModules() START ---
[Products] Has wizard? true
[Products] Has stateManager? true
[Products] Creating State module...
[Products] State module created
[Products] Checking wizard state manager...
[Products]  - Has wizard? true
[Products]  - Has wizard.modules? true
[Products]  - Has wizard.modules.stateManager? true
[Products] All step data from wizard: {...}
[Products] Products step data extracted: {...}
[Products] Calling setState() with loaded data: {...}
[Products] State after setState(): {...}
[Products] Modules initialized: ["state", "api", "picker"]
[Products] Calling initializeUI()...
[Products] --- initializeUI() START ---
[Products] Has picker module? true
[Products] Initializing Picker module...
[Products] Picker module initialized successfully
[Products] --- initializeUI() COMPLETE ---
[Products] UI initialization complete
[Products] Setting initial state...
[Products] Orchestrator exposed globally as window.scdProductsOrchestrator
[Products] ========== INIT COMPLETE ==========
```

### User Interaction Logs

```javascript
// When user changes selection type
[Products] ##### USER ACTION: Selection type changed #####
[Products] New selection type: specific_products
[Products] Updating state with new selection type
[Products] >>> updateSectionVisibility() called <<<
[Products] Selection type: specific_products
[Products] Hiding all conditional sections...
[Products] Section to show: .scd-specific-products
[Products] Found section elements: 1
```

### Category Selection Logs

```javascript
[Products] *** handleCategoryChange() called ***
[Products] New categories: [1, 2, 3]
[Products] Old categories: ["all"]
[Products] Processed categories: [1, 2, 3]
[Products] State updated with new categories
[Products] Categories changed? true
[Products] Triggering scd:categories:changed event
[Products] *** handleCategoryChange() complete ***
```

---

## Key Methods with Logging

### 1. init()
- **When**: Orchestrator initialization starts
- **Logs**:
  - Init start marker
  - Config object
  - Wizard availability
  - Module initialization progress
  - UI initialization progress
  - Init complete marker

### 2. initializeModules()
- **When**: Loading State, API, and Picker modules
- **Logs**:
  - Wizard and stateManager availability
  - State module creation
  - Wizard state manager check
  - All step data from wizard
  - Products step data extraction
  - setState() call with data
  - Final state after setState()

### 3. initializeUI()
- **When**: Initializing Picker module (TomSelect)
- **Logs**:
  - Picker module availability
  - Picker initialization start
  - Picker initialization success/failure
  - UI initialization complete

### 4. updateSectionVisibility()
- **When**: Showing/hiding sections based on selection type
- **Logs**:
  - Current selection type
  - Container availability
  - Sections being hidden
  - Section to show
  - Number of elements found
  - Card option handling

### 5. handleCategoryChange()
- **When**: User selects/changes categories
- **Logs**:
  - New categories array
  - Old categories array
  - Processed categories
  - State update confirmation
  - Whether categories actually changed
  - Event trigger notification

### 6. bindEvents() - Selection Type Handler
- **When**: User changes product selection type
- **Logs**:
  - User action marker
  - New selection type value
  - State update confirmation

---

## How to Use

### Enable Browser Console

1. **Chrome/Edge**: Press `F12` or `Ctrl+Shift+I` (Windows) / `Cmd+Option+I` (Mac)
2. **Firefox**: Press `F12` or `Ctrl+Shift+K` (Windows) / `Cmd+Option+K` (Mac)
3. Click on the **Console** tab

### Filter Logs

In the browser console filter box, type:
```
[Products]
```

This will show only products step logs.

### Common Scenarios

#### Debugging Initialization Issues

Look for:
```
[Products] ========== INIT START ==========
```

And ensure it ends with:
```
[Products] ========== INIT COMPLETE ==========
```

If initialization fails, you'll see:
```
[Products] ========== INIT FAILED ==========
[Products] Initialization error: {...}
```

#### Debugging State Loading

Check if data is loaded from wizard:
```
[Products] All step data from wizard: {...}
[Products] Products step data extracted: {...}
```

If these are empty or null, no previous data exists.

#### Debugging User Interactions

Watch for:
```
[Products] ##### USER ACTION: Selection type changed #####
```

This indicates a user clicked/changed something.

#### Debugging Category Selection

Look for:
```
[Products] *** handleCategoryChange() called ***
[Products] New categories: [...]
[Products] Processed categories: [...]
```

#### Debugging Section Visibility

Check:
```
[Products] >>> updateSectionVisibility() called <<<
[Products] Selection type: specific_products
[Products] Section to show: .scd-specific-products
[Products] Found section elements: 1
```

If "Found section elements: 0", the DOM element doesn't exist.

---

## Troubleshooting

### No Logs Appearing

1. **Check Browser Console is Open**: Press F12
2. **Check Console Tab**: Click "Console" tab
3. **Check Filter**: Remove any filters
4. **Check Log Level**: Ensure "Info" and "Log" are enabled (not just "Errors")
5. **Refresh Page**: Hard refresh with `Ctrl+Shift+R` (Windows) / `Cmd+Shift+R` (Mac)

### Logs But No Products Step

1. **Verify You're on Products Step**: Check wizard URL contains `?action=wizard`
2. **Check Step is Active**: Products step tab should be highlighted
3. **Check JavaScript Errors**: Look for red error messages before `[Products]` logs

### Initialization Errors

If you see:
```
[Products] ========== INIT FAILED ==========
```

1. **Check the error message** - it will show what failed
2. **Common causes**:
   - Missing modules (State, API, Picker not loaded)
   - jQuery not loaded
   - DOM not ready
   - JavaScript syntax errors

---

## Log Patterns

### Successful Flow

```
[Products] ========== INIT START ==========
  ↓
[Products] --- initializeModules() START ---
  ↓
[Products] State module created
  ↓
[Products] All step data from wizard: {...}
  ↓
[Products] Calling setState() with loaded data: {...}
  ↓
[Products] --- initializeUI() START ---
  ↓
[Products] Picker module initialized successfully
  ↓
[Products] ========== INIT COMPLETE ==========
```

### User Changes Selection Type

```
[Products] ##### USER ACTION: Selection type changed #####
  ↓
[Products] New selection type: specific_products
  ↓
[Products] >>> updateSectionVisibility() called <<<
  ↓
[Products] Section to show: .scd-specific-products
  ↓
[Products] Found section elements: 1
  ↓
[Products] Updating state with new selection type
```

### User Selects Categories

```
[Products] *** handleCategoryChange() called ***
  ↓
[Products] New categories: [1, 2, 3]
  ↓
[Products] Processed categories: [1, 2, 3]
  ↓
[Products] State updated with new categories
  ↓
[Products] Categories changed? true
  ↓
[Products] Triggering scd:categories:changed event
```

---

## Example Console Output

### Complete Initialization Sequence

```
[Products] ========== INIT START ==========
[Products] init() called with config: Object { stepName: "products" }
[Products] Has wizard? true
[Products] Calling initializeModules()...
[Products] --- initializeModules() START ---
[Products] Has wizard? true
[Products] Has stateManager? true
[Products] Creating State module...
[Products] State module created
[Products] Checking wizard state manager...
[Products]  - Has wizard? true
[Products]  - Has wizard.modules? true
[Products]  - Has wizard.modules.stateManager? true
[Products] All step data from wizard: Object { basic: {…}, products: {…}, discounts: null, schedule: null, review: null }
[Products] Products step data extracted: Object { product_selection_type: "all_products", category_ids: ["all"], product_ids: [], … }
[Products] Calling setState() with loaded data: Object { product_selection_type: "all_products", category_ids: ["all"], … }
[Products] State after setState(): Object { productSelectionType: "all_products", categoryIds: ["all"], productIds: [], … }
[Products] Modules initialized: Array(3) [ "state", "api", "picker" ]
[Products] Calling initializeUI()...
[Products] --- initializeUI() START ---
[Products] Has picker module? true
[Products] Initializing Picker module...
[Products] Picker module initialized successfully
[Products] --- initializeUI() COMPLETE ---
[Products] UI initialization complete
[Products] Setting initial state...
[Products] Orchestrator exposed globally as window.scdProductsOrchestrator
[Products] ========== INIT COMPLETE ==========
```

---

## Benefits

### For Debugging

✅ **See Exact Execution Flow**: Know exactly what code is running and when
✅ **Track State Changes**: See state before and after updates
✅ **Monitor User Actions**: Know when users interact with the interface
✅ **Identify Missing Data**: Spot null/undefined values immediately
✅ **Find DOM Issues**: See if elements are found or missing

### For Development

✅ **No Setup Required**: Works immediately without configuration
✅ **Always Active**: No need to enable debug mode
✅ **Simple to Read**: Clear, descriptive messages
✅ **Easy to Extend**: Just add more console.log statements
✅ **No Performance Impact**: Browser optimizes console.log when DevTools closed

---

## Differences from Debug Logger

| Feature | Debug Logger (Removed) | Console.log (Current) |
|---------|------------------------|----------------------|
| Complexity | 500+ lines of code | Simple console.log calls |
| Setup | Requires registration, configuration | No setup needed |
| Activation | Conditional (debug flag) | Always active |
| Output | Formatted, colored, categorized | Simple console messages |
| History | Stored, exportable | Browser console only |
| Performance | Minimal overhead when enabled | Negligible overhead |
| Maintenance | Complex logger to maintain | Easy to add/remove logs |

**Why Simple is Better**:
- Faster to implement
- Easier to understand
- Less code to maintain
- Always works (no activation required)
- Familiar console.log syntax

---

## Summary

✅ **Comprehensive Logging Added**: Key methods throughout products orchestrator
✅ **Always Active**: No need to enable debug mode
✅ **Clear Markers**: Init start/end, user actions, method calls
✅ **State Tracking**: See state before/after changes
✅ **User Actions**: Track clicks and selections
✅ **Easy Debugging**: Open console and see exactly what's happening

**Result**: You can now open the browser console and see complete, real-time logging of everything that happens in the products step!

---

**Date**: 2025-10-27
**Status**: ✅ Complete
**Files Modified**: 2 files (orchestrator + script registry)
**Files Removed**: 4 files (debug logger infrastructure)
