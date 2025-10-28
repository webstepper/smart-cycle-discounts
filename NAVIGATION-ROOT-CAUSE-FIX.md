# Navigation Root Cause Fix - Eliminate Duplicate Data Collection

**Date:** 2025-10-27
**Issue:** Duplicate `collectStepData()` calls causing idempotency cache hits
**Solution:** Collect data once, pass through navigation flow

---

## ROOT CAUSE

### Problem
`collectStepData()` is called **TWICE** in the same navigation flow:

1. **Line 255** - `validateCurrentStep()` collects data to check PRO features
2. **Line 338** - `performNavigation()` collects data again for save request

This causes:
- Duplicate console logs ("Collected step data" appears twice)
- Second save request hits idempotency cache
- Cache returns minimal `{success: true}` instead of full response
- Navigation fails because `redirectUrl` missing from cached response

### Band-Aid Solution (TO BE REMOVED)
Lines 443-453, 495-533: `_saveInProgress` flag prevents duplicate requests

**Problem with band-aid:** Treats symptom, not cause. Data still collected twice.

---

## PROPER SOLUTION

### Principle
**Collect data ONCE in validation, pass it through the entire navigation flow**

### Changes Required

#### 1. `validateCurrentStep()` - Return Data + Validation Result

**BEFORE (line 250-288):**
```javascript
validateCurrentStep: function( stepName ) {
    var formData = this.collectStepData( stepName );  // COLLECTED HERE
    var proCheck = this.checkProFeatures( stepName, formData );

    if ( proCheck.blocked ) {
        return $.Deferred().resolve( false ).promise();  // Returns boolean
    }

    // ...orchestrator validation...
    return stepOrchestrator.validateStep();  // Returns boolean
}
```

**AFTER:**
```javascript
validateCurrentStep: function( stepName ) {
    var formData = this.collectStepData( stepName );  // COLLECT ONCE
    var proCheck = this.checkProFeatures( stepName, formData );

    if ( proCheck.blocked ) {
        return $.Deferred().resolve({           // Returns object
            isValid: false,
            formData: formData
        }).promise();
    }

    // Wrap orchestrator validation to include data
    return stepOrchestrator.validateStep().then(function(isValid) {
        return { isValid: isValid, formData: formData };
    });
}
```

#### 2. `navigateNext()` - Accept New Format

**BEFORE (line 229-232):**
```javascript
this.validateCurrentStep( currentStep ).done( function( isValid ) {
    if ( isValid ) {
        self.navigateToStep( nextStep, currentStep );
    }
});
```

**AFTER:**
```javascript
this.validateCurrentStep( currentStep ).done( function( validationResult ) {
    if ( validationResult.isValid ) {
        self.navigateToStep( nextStep, currentStep, validationResult.formData );
    }
});
```

#### 3. `handleStepIndicatorClick()` - Accept New Format

**BEFORE (line 207-210):**
```javascript
this.validateCurrentStep( currentStep ).done( function( isValid ) {
    if ( isValid ) {
        self.navigateToStep( targetStep, currentStep );
    }
});
```

**AFTER:**
```javascript
this.validateCurrentStep( currentStep ).done( function( validationResult ) {
    if ( validationResult.isValid ) {
        self.navigateToStep( targetStep, currentStep, validationResult.formData );
    }
});
```

#### 4. `navigateToStep()` - Accept formData Parameter

**BEFORE (line 311-318):**
```javascript
navigateToStep: function( targetStep, fromStep ) {
    fromStep = fromStep || this.getCurrentStep();

    if ( targetStep === fromStep ) {
        return;
    }

    this.performNavigation( fromStep, targetStep );
}
```

**AFTER:**
```javascript
navigateToStep: function( targetStep, fromStep, formData ) {
    fromStep = fromStep || this.getCurrentStep();

    if ( targetStep === fromStep ) {
        return;
    }

    this.performNavigation( fromStep, targetStep, false, formData );
}
```

#### 5. `performNavigation()` - Accept formData, Remove Duplicate Collection

**BEFORE (line 329-345):**
```javascript
performNavigation: function( fromStep, targetStep, isRetry ) {
    var self = this;

    if ( SCD.Wizard && SCD.Wizard.Orchestrator ) {
        SCD.Wizard.Orchestrator.isInternalNavigation = true;
    }

    this.setNavigationState( true );

    var formData = this.collectStepData( fromStep );  // DUPLICATE COLLECTION!

    // Note: PRO feature check now happens in validateCurrentStep() before this point

    this.sendNavigationRequest( fromStep, targetStep, formData )
    // ...
}
```

**AFTER:**
```javascript
performNavigation: function( fromStep, targetStep, isRetry, formData ) {
    var self = this;

    if ( SCD.Wizard && SCD.Wizard.Orchestrator ) {
        SCD.Wizard.Orchestrator.isInternalNavigation = true;
    }

    this.setNavigationState( true );

    // Use formData passed from validation - NO duplicate collection

    // Note: PRO feature check now happens in validateCurrentStep() before this point

    this.sendNavigationRequest( fromStep, targetStep, formData )
    // ...
}
```

#### 6. Remove Band-Aid Code

**Remove from `sendNavigationRequest()` (lines 443-453):**
```javascript
// Prevent duplicate save requests (debounce)
if ( this._saveInProgress ) {
    console.warn( '[SCD Navigation] Save already in progress, ignoring duplicate request' );
    return this._savePromise || $.Deferred().reject({
        success: false,
        data: {
            message: 'Save already in progress',
            code: 'save_in_progress'
        }
    }).promise();
}
```

**Remove flag management (lines 496, 503-504, 532-533):**
```javascript
this._saveInProgress = true;   // Line 496 - REMOVE
self._saveInProgress = false;  // Lines 503, 532 - REMOVE
self._savePromise = null;      // Lines 504, 533 - REMOVE
```

#### 7. Remove Diagnostic Logging

Remove all console.log statements added for debugging:
- Line 443: Save in progress warning
- Line 493: Saving step log
- Line 500: Step saved successfully log
- Line 514: Building redirect URL log
- Line 529: Step save failed log
- Line 738: Redirecting to log
- Line 739: Current URL before redirect log
- Line 745: Executing redirect now log
- Line 753: No redirectUrl warning log
- Line 945: Collected step data log

---

## FILES MODIFIED

### wizard-navigation.js
- `validateCurrentStep()` - Returns `{isValid, formData}` instead of boolean
- `navigateNext()` - Handles new return format
- `handleStepIndicatorClick()` - Handles new return format
- `navigateToStep()` - Accepts formData parameter
- `performNavigation()` - Accepts formData parameter, removes duplicate collection
- `sendNavigationRequest()` - Removes band-aid deduplication code
- All methods - Removes diagnostic console.log statements

---

## TESTING CHECKLIST

After applying fix:

1. **Basic Navigation**
   - [ ] Fill Basic step → Click Next → Products step loads
   - [ ] Console shows "Collected step data" ONCE (not twice)
   - [ ] No "_saveInProgress" warnings

2. **Specific Products Selection**
   - [ ] Select "Specific Products" option
   - [ ] Fill form → Click Next
   - [ ] Should redirect to Discounts step
   - [ ] Console shows full response with redirectUrl

3. **All Products Selection**
   - [ ] Select "All Products" option
   - [ ] Fill form → Click Next
   - [ ] Should redirect to Discounts step
   - [ ] Same behavior as specific products

4. **Step Indicator Click**
   - [ ] Click step 3 from step 1
   - [ ] Validation runs
   - [ ] Navigation works
   - [ ] Data collected once

5. **Browser Back/Forward**
   - [ ] Navigate through wizard
   - [ ] Click browser back
   - [ ] Data persists
   - [ ] Click browser forward
   - [ ] Navigation works

6. **Console Cleanliness**
   - [ ] No diagnostic logging
   - [ ] No duplicate collection logs
   - [ ] No band-aid warnings
   - [ ] Clean, minimal console output

---

## SUCCESS CRITERIA

✅ **Fix Complete When:**
1. "Collected step data" appears ONCE per navigation
2. No `_saveInProgress` band-aid code
3. No diagnostic console.log statements
4. Navigation works 100% reliably
5. Both "all_products" and "specific_products" work identically
6. Console output is clean and minimal

---

**Status:** 🔧 READY TO APPLY

All changes identified. Ready to implement proper root cause fix.
