# Bug Investigation: Volume Discounts (Tiered) and Spend Threshold Navigation Failure

## Status
**Current Status**: Fixed
**Severity**: High - Prevented users from completing wizard for these two discount types
**Fix Date**: 2025-11-10

## Bug Summary

### Symptom
- User selects "Volume Discounts" (tiered) or "Spend Threshold" as discount type
- User fills in tier/threshold information
- User clicks "Next" button
- **BUG**: Button shows "Processing..." but page redirects to itself or refreshes
- **EXPECTED**: Navigation should proceed to next wizard step (Schedule step)

### Affected Discount Types
- ✅ Percentage Off - Works correctly
- ✅ Fixed Amount Off - Works correctly
- ❌ Volume Discounts (tiered) - **FAILED** (now fixed)
- ✅ Buy One Get One (BOGO) - Works correctly
- ❌ Spend Threshold - **FAILED** (now fixed)

---

## Root Cause Analysis

### The Problem
**Race condition between complex field handler registration and data collection during wizard navigation**

### Technical Details

The bug occurs because:

1. **Complex fields require handlers**: Fields like `tiers` and `thresholds` are "complex" fields that store structured data (arrays of tier/threshold objects). These fields need special handlers with `getValue()` methods to collect their data.

2. **Handler registration is event-driven**: When a discount type instance is created, it triggers an event `scd:discount:type:instance:created` which the orchestrator listens for to register the handler.

3. **Race condition in edit mode**: When editing an existing campaign with tiered or spend_threshold discount:
   - Page loads
   - Type Registry initializes and immediately activates the current discount type
   - Instance is created and event is triggered
   - **BUG**: Event fires BEFORE orchestrator's event listener is fully set up
   - Handler is never registered
   - User clicks "Next" → collectData() tries to get handler → returns null → returns empty array → validation fails → navigation blocked

4. **Why only these two types**:
   - Percentage and Fixed don't use complex fields (simple number inputs)
   - BOGO was likely tested more thoroughly and the race condition didn't manifest
   - Tiered and Spend Threshold both use complex handlers and triggered the race condition consistently

### Execution Flow (BEFORE FIX)

```
EDIT MODE - Existing Campaign with Tiered Discount:

1. Page Load
2. Discounts Orchestrator initializes
   → initializeModules() called
   → Creates State module
   → Creates API module
   → Sets up event listener for 'scd:discount:type:instance:created'
   → Creates Type Registry  ← HERE IS THE PROBLEM
3. Type Registry init() called
   → Calls activateType('tiered')
   → Creates TieredDiscount instance
   → Triggers 'scd:discount:type:instance:created' event
   → ❌ Event listener NOT READY YET - event missed!
4. registerExistingHandlers() called
   → Tries to register from typeRegistry.instances
   → ❌ May not work if timing is off
5. Handler NOT registered in complexFieldHandlers

USER CLICKS "NEXT":

6. Navigation validates step
7. Calls collectData()
   → Loops through field definitions
   → Finds 'tiers' field (type: 'complex', handler: 'tieredDiscount')
   → Calls collectComplexField()
   → Calls getComplexFieldHandler('tieredDiscount')
   → ❌ Returns null - handler not registered!
   → Returns default value (empty array)
8. Validation runs
   → Checks 'tiers' field (required: true)
   → Value is empty array
   → ❌ Validation FAILS: "At least one tier is required"
9. Navigation blocked - saveStep() fails
10. Page redirects to current step (appears to "refresh")
```

---

## The Fix

### Changes Made

**File**: `/resources/assets/js/steps/discounts/discounts-orchestrator.js`

### Change 1: Event Listener Setup Timing (Lines 76-78)

**BEFORE**:
```javascript
// Type registry initialized FIRST
this.modules.typeRegistry = new SCD.Modules.Discounts.TypeRegistry( this.modules.state );
this.modules.typeRegistry.init();

// Event listener set up AFTER (too late!)
this.setupComplexFieldHandlerRegistration();
```

**AFTER**:
```javascript
// Setup event-driven handler registration BEFORE initializing type registry
// This ensures we catch the instance creation event
this.setupComplexFieldHandlerRegistration();

// Type registry for discount type modules
this.modules.typeRegistry = new SCD.Modules.Discounts.TypeRegistry( this.modules.state );
this.modules.typeRegistry.init();
```

**Why This Helps**: Event listener is now in place BEFORE type registry creates instances, so events won't be missed.

### Change 2: Deferred Handler Registration (Lines 90-96)

**ADDED**:
```javascript
// CRITICAL FIX: Register handlers for instances that were created during typeRegistry.init()
// Use requestAnimationFrame to ensure registration happens after event loop completes
// This catches instances that were created before our event listener was fully set up
var self = this;
requestAnimationFrame( function() {
    self.registerExistingHandlers();
} );
```

**Why This Helps**: `requestAnimationFrame` ensures that `registerExistingHandlers()` runs AFTER the current event loop completes, giving the type registry's init() method time to finish creating instances. This is a safety net for the race condition.

### Change 3: Enhanced registerExistingHandlers() (Lines 144-167)

**BEFORE**:
```javascript
registerExistingHandlers: function() {
    if ( !this.modules.typeRegistry || !this.modules.typeRegistry.instances ) {
        return;
    }

    var self = this;
    var instances = this.modules.typeRegistry.instances;
    Object.keys( instances ).forEach( function( typeId ) {
        if ( instances[typeId] ) {
            self.registerHandlerForType( typeId, instances[typeId] );
        }
    } );
},
```

**AFTER**:
```javascript
registerExistingHandlers: function() {
    if ( !this.modules.typeRegistry ) {
        console.warn( '[DiscountsOrchestrator] Cannot register existing handlers - typeRegistry not initialized' );
        return;
    }

    var instances = this.modules.typeRegistry.instances;
    if ( !instances || 0 === Object.keys( instances ).length ) {
        // No instances exist yet - this is normal on initial page load
        // Handlers will be registered via event when instances are created
        return;
    }

    var self = this;
    Object.keys( instances ).forEach( function( typeId ) {
        if ( instances[typeId] ) {
            // Check if handler is already registered to avoid duplicates
            var handlerName = self.getHandlerNameForType( typeId );
            if ( handlerName && ! self.complexFieldHandlers[handlerName] ) {
                self.registerHandlerForType( typeId, instances[typeId] );
            }
        }
    } );
},
```

**Why This Helps**:
- Better error checking and logging
- Prevents duplicate registrations
- Gracefully handles case where no instances exist yet (normal for new campaigns)

### Change 4: Extract Handler Name Mapping (Lines 169-186)

**ADDED NEW METHOD**:
```javascript
/**
 * Get handler name for a discount type ID
 * Extracted from registerHandlerForType for reusability
 * @param {string} typeId - Type identifier (tiered, bogo, spend_threshold)
 * @returns {string|null} Handler name or null
 */
getHandlerNameForType: function( typeId ) {
    switch ( typeId ) {
        case 'tiered':
            return 'tieredDiscount';
        case 'bogo':
            return 'bogoDiscount';
        case 'spend_threshold':
            return 'spendThreshold';
        default:
            return null;
    }
},
```

**Why This Helps**: Reduces code duplication, makes mapping reusable, easier to maintain.

### Change 5: Simplified registerHandlerForType() (Lines 118-126)

**BEFORE**:
```javascript
registerHandlerForType: function( typeId, instance ) {
    var handlerName = null;

    switch ( typeId ) {
        case 'tiered':
            handlerName = 'tieredDiscount';
            break;
        case 'bogo':
            handlerName = 'bogoDiscount';
            break;
        case 'spend_threshold':
            handlerName = 'spendThreshold';
            break;
    }

    if ( handlerName && instance ) {
        this.registerComplexFieldHandler( handlerName, instance );
    } else {
        console.warn( '[DiscountsOrchestrator] Cannot register handler - missing handlerName or instance:', { typeId: typeId, handlerName: handlerName, instance: instance } );
    }
},
```

**AFTER**:
```javascript
registerHandlerForType: function( typeId, instance ) {
    var handlerName = this.getHandlerNameForType( typeId );

    if ( handlerName && instance ) {
        this.registerComplexFieldHandler( handlerName, instance );
    } else {
        console.warn( '[DiscountsOrchestrator] Cannot register handler - missing handlerName or instance:', { typeId: typeId, handlerName: handlerName, instance: instance } );
    }
},
```

**Why This Helps**: DRY principle - uses extracted method instead of duplicating switch statement.

### Change 6: Enhanced Logging (Lines 181-187)

**BEFORE**:
```javascript
registerComplexFieldHandler: function( name, handler ) {
    if ( handler && 'function' === typeof handler.getValue ) {
        this.complexFieldHandlers[name] = handler;
    } else {
        console.warn( '[DiscountsOrchestrator] Failed to register handler - missing getValue:', name );
    }
},
```

**AFTER**:
```javascript
registerComplexFieldHandler: function( name, handler ) {
    if ( handler && 'function' === typeof handler.getValue ) {
        this.complexFieldHandlers[name] = handler;
        console.log( '[DiscountsOrchestrator] Successfully registered complex field handler:', name );
    } else {
        console.warn( '[DiscountsOrchestrator] Failed to register handler - missing getValue:', name );
    }
},
```

**Why This Helps**: Success logging confirms handlers are registered correctly, aids debugging.

---

## Execution Flow (AFTER FIX)

```
EDIT MODE - Existing Campaign with Tiered Discount:

1. Page Load
2. Discounts Orchestrator initializes
   → initializeModules() called
   → Creates State module
   → Creates API module
   → ✅ Sets up event listener for 'scd:discount:type:instance:created' FIRST
   → Creates Type Registry
3. Type Registry init() called
   → Calls activateType('tiered')
   → Creates TieredDiscount instance
   → Triggers 'scd:discount:type:instance:created' event
   → ✅ Event listener IS READY - event caught!
   → ✅ Handler registered via event
4. requestAnimationFrame(() => registerExistingHandlers())
   → Runs after event loop completes
   → ✅ Safety net: Re-registers if somehow missed
   → Checks for duplicates before registering
5. ✅ Handler IS registered in complexFieldHandlers

USER CLICKS "NEXT":

6. Navigation validates step
7. Calls collectData()
   → Loops through field definitions
   → Finds 'tiers' field (type: 'complex', handler: 'tieredDiscount')
   → Calls collectComplexField()
   → Calls getComplexFieldHandler('tieredDiscount')
   → ✅ Returns handler instance
   → Calls handler.getValue()
   → ✅ Returns properly formatted tier data
8. Validation runs
   → Checks 'tiers' field (required: true)
   → Value is array with tier objects
   → ✅ Validation PASSES
9. ✅ Navigation proceeds - saveStep() succeeds
10. ✅ User advances to next step (Schedule)
```

---

## Testing Results

### Test Case 1: Create New Campaign with Tiered Discount
- ✅ Select "Volume Discounts" type
- ✅ Add tier: 5 items = 10% off
- ✅ Add tier: 10 items = 20% off
- ✅ Click "Next"
- ✅ Successfully navigates to Schedule step
- ✅ Console shows: "Successfully registered complex field handler: tieredDiscount"

### Test Case 2: Create New Campaign with Spend Threshold
- ✅ Select "Spend Threshold" type
- ✅ Add threshold: Spend $50 = 5% off
- ✅ Add threshold: Spend $100 = 10% off
- ✅ Click "Next"
- ✅ Successfully navigates to Schedule step
- ✅ Console shows: "Successfully registered complex field handler: spendThreshold"

### Test Case 3: Edit Existing Campaign with Tiered Discount
- ✅ Open existing campaign with tiered discount
- ✅ Modify tier values
- ✅ Click "Next"
- ✅ Successfully navigates to Schedule step
- ✅ Handler registered during initialization

### Test Case 4: Edit Existing Campaign with Spend Threshold
- ✅ Open existing campaign with spend threshold
- ✅ Modify threshold values
- ✅ Click "Next"
- ✅ Successfully navigates to Schedule step
- ✅ Handler registered during initialization

### Test Case 5: Regression - Other Discount Types Still Work
- ✅ Percentage Off - Works correctly
- ✅ Fixed Amount Off - Works correctly
- ✅ Buy One Get One (BOGO) - Works correctly

---

## Related Code Locations

### Primary Fix Location
- `/resources/assets/js/steps/discounts/discounts-orchestrator.js` (lines 54-187)

### Complex Field Handler System
- `/resources/assets/js/shared/mixins/step-persistence.js` (lines 602-674)
  - `registerComplexFieldHandler()` - Registers handlers
  - `getComplexFieldHandler()` - Retrieves handlers
  - `collectComplexField()` - Collects data using handler.getValue()
  - `isComplexFieldReady()` - Checks if handler is ready

### Type Registry (Instance Creation)
- `/resources/assets/js/steps/discounts/discounts-type-registry.js` (lines 245-279)
  - `getInstance()` - Creates discount type instances
  - Line 269: Triggers `scd:discount:type:instance:created` event

### Discount Type Handlers (with getValue() methods)
- `/resources/assets/js/steps/discounts/tiered-discount.js` (lines 907-952)
- `/resources/assets/js/steps/discounts/spend-threshold.js` (lines 814-885)
- `/resources/assets/js/steps/discounts/bogo-discount.js` (similar pattern)

### Field Definitions
- `/includes/core/validation/class-field-definitions.php`
  - Lines ~450-470: `tiers` field definition (handler: 'tieredDiscount')
  - Lines ~490-510: `thresholds` field definition (handler: 'spendThreshold')

### Wizard Navigation (Triggers collectData)
- `/resources/assets/js/wizard/wizard-navigation.js` (lines 510-619)
  - `sendNavigationRequest()` - Calls stepOrchestrator.saveStep()

---

## Prevention Measures

### What Was Added
1. **Event Listener Priority**: Set up event listeners BEFORE creating instances that trigger events
2. **Safety Net**: Use requestAnimationFrame to defer handler registration, ensuring all instances are created
3. **Duplicate Prevention**: Check if handler already registered before re-registering
4. **Enhanced Logging**: Success and failure logs for handler registration
5. **Better Error Handling**: Graceful handling of missing instances vs missing handlers

### Future Safeguards
1. **Documentation**: This file documents the race condition and fix
2. **Code Comments**: Inline comments explain the timing-sensitive code
3. **Test Cases**: Explicit test cases for both create and edit modes
4. **Logging**: Console logs help diagnose handler registration issues

---

## Summary

**Bug**: Race condition prevented handler registration for tiered and spend_threshold discount types, causing navigation to fail when clicking "Next" in wizard.

**Root Cause**: Event listener for handler registration was set up AFTER type registry created instances, so events were missed.

**Fix**:
1. Set up event listener BEFORE creating type registry
2. Use requestAnimationFrame to defer handler registration as safety net
3. Enhanced duplicate prevention and error handling
4. Added logging for verification

**Result**: Both tiered and spend_threshold discount types now navigate correctly in wizard, in both CREATE and EDIT modes.

**Files Modified**:
- `/resources/assets/js/steps/discounts/discounts-orchestrator.js`

**Lines Changed**: ~40 lines modified/added across methods:
- initializeModules() - Event listener timing fix
- registerExistingHandlers() - Enhanced with better checks
- getHandlerNameForType() - New extracted method
- registerHandlerForType() - Simplified using extracted method
- registerComplexFieldHandler() - Added success logging
