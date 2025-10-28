# Server-Side Access Control Diagnosis Needed

**Date:** 2025-10-27
**Status:** 🔍 INVESTIGATING - Need Debug Logs
**Issue:** Navigation reaches Discounts but redirects back to Products

---

## 🎯 WHAT WE KNOW SO FAR

### JavaScript Side: ✅ WORKING
The JavaScript navigation is now working correctly after fixing line 276:

```javascript
// wizard-navigation.js:276
return { isValid: validationResult, formData: formData };  // ✅ Fixed
```

**Evidence from Console:**
```
[DIAGNOSTIC] PASSED - formData: {productSelectionType: 'specific_products', ...}
[SCD Navigation] Collected step data for save: {productSelectionType: 'specific_products', ...}
[SCD Navigation] Building redirect URL for target: discounts
[SCD Navigation] Executing redirect now...
```

The save succeeds, the redirect happens, and you briefly see the progress bar reach step 3 (Discounts), but then the page loads showing Products step again.

---

## 🚨 THE ISSUE

**Server-side access control is blocking Discounts access**

The wizard controller checks if the previous step ("products") is in the `completed_steps` array before allowing access to "discounts".

**File:** `includes/core/campaigns/class-campaign-wizard-controller.php`

**Lines 340-367 - Access Control Logic:**
```php
private function get_allowed_step( string $requested_step ): string {
    $completed_steps = $this->session->get( 'completed_steps', array() );

    // Always allow first step
    if ( 'basic' === $requested_step ) {
        return $requested_step;
    }

    // Allow if step is already completed (going back)
    if ( in_array( $requested_step, $completed_steps, true ) ) {
        return $requested_step;
    }

    // Check if previous step is completed
    $requested_index = array_search( $requested_step, $this->steps, true );
    if ( false === $requested_index || 0 === $requested_index ) {
        return 'basic';
    }

    $previous_step = $this->steps[ $requested_index - 1 ];
    if ( in_array( $previous_step, $completed_steps, true ) ) {
        // Previous step complete, allow access to this step
        return $requested_step;  // ← Should allow 'discounts' if 'products' is in array
    }

    // User trying to skip ahead - find the furthest allowed step
    return $this->get_furthest_allowed_step( $completed_steps );  // ← Redirects back
}
```

**The Question:** Is "products" actually in the `$completed_steps` array when you try to access Discounts?

---

## 🔍 WHAT HAPPENS

### Save Flow (AJAX):
1. **Products step save** → `SCD_Save_Step_Handler::handle()`
2. **Validation passes** → Data is sanitized and processed
3. **Line 457:** `$this->state_service->mark_step_complete( 'products' );`
4. **Wizard_State_Service** adds "products" to `$this->data['completed_steps']`
5. **Saves to transient:** `set_transient('scd_wizard_session_{id}', $data, 7200)`
6. **Returns response** with `completed_steps: ['basic', 'products']`

### Navigation Flow (JavaScript):
1. **JavaScript receives response** with `completed_steps: ['basic', 'products']`
2. **Builds redirect URL:** `?page=scd-wizard&step=discounts`
3. **Browser redirects** to Discounts page

### Page Load (PHP):
1. **NEW PHP request** for `?step=discounts`
2. **Wizard Controller created** with singleton `Wizard_State_Service` instance
3. **Session loads from transient:** Should have `completed_steps: ['basic', 'products']`
4. **Access control checks:** Line 165 gets `completed_steps` array
5. **Line 360:** Checks if "products" is in array
6. **IF NOT FOUND:** Redirects back to Products step ← **THIS IS HAPPENING**

---

## 🧪 DEBUGGING REQUIRED

There's **built-in debug logging** in the wizard controller (lines 164-177) that will tell us EXACTLY what's in the `completed_steps` array:

```php
// DEBUG: Log access control check
if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
    $completed = $this->session->get( 'completed_steps', array() );
    error_log( sprintf(
        '[SCD Wizard Access Control] Requested: %s | Allowed: %s | Completed steps: %s',
        $requested_step,
        $allowed_step,
        implode( ', ', $completed )
    ) );
}

if ( $requested_step !== $allowed_step ) {
    // Redirect to the correct step
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( '[SCD Wizard Access Control] BLOCKING access - redirecting to: ' . $allowed_step );
    }
    // ... redirect logic
}
```

---

## 📋 ACTION NEEDED

### 1. Enable WordPress Debug Logging

**File:** `wp-config.php` (in your WordPress root directory)

Add or verify these lines:
```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
```

### 2. Clear the Debug Log

**File:** `wp-content/debug.log`

Either delete it or clear its contents to start fresh.

### 3. Reproduce the Issue

1. **Clear browser cache** (Ctrl+Shift+Delete)
2. **Hard refresh** (Ctrl+Shift+R)
3. **Navigate to Products step**
4. **Select "Specific Products"**
5. **Add 2-3 products with TomSelect**
6. **Click "Next"**
7. **Observe:** Progress bar reaches step 3, then page shows Products again

### 4. Check the Debug Log

**File:** `wp-content/debug.log`

**Look for these lines:**
```
[SCD Wizard Access Control] Requested: discounts | Allowed: products | Completed steps: basic
[SCD Wizard Access Control] BLOCKING access - redirecting to: products
```

**What to Share:**
1. **The complete log entry** for the access control check
2. **What's in "Completed steps:"** - Is "products" in the list?
3. **Any errors or warnings** related to the wizard or session

---

## 🔬 POSSIBLE ROOT CAUSES

### Theory 1: Save Not Completing
The `mark_step_complete()` call might be failing silently.

**Check for:** Errors before the "[BLOCKING access]" log entry

### Theory 2: Transient Not Persisting
The transient might not be saving to the database correctly.

**Check for:** Transient or database errors in the log

### Theory 3: Session Load Failure
The wizard controller might be loading a different session or stale data.

**Check for:** Session ID mismatches or load failures

### Theory 4: Timing Issue
The transient might not be written before the redirect happens.

**Check for:** If completed steps shows only "basic" (missing "products")

### Theory 5: Array Merge Issue
The completed_steps array might be getting reset somewhere.

**Check for:** If completed steps is empty `[]`

---

## 🎯 EXPECTED LOG OUTPUT

### If Working Correctly:
```
[SCD Wizard Access Control] Requested: discounts | Allowed: discounts | Completed steps: basic, products
```
No blocking message - navigation succeeds.

### If Broken (most likely):
```
[SCD Wizard Access Control] Requested: discounts | Allowed: products | Completed steps: basic
[SCD Wizard Access Control] BLOCKING access - redirecting to: products
```
"products" is missing from completed steps.

---

## 📝 WHAT TO DO NEXT

1. **Enable debug logging** in `wp-config.php`
2. **Clear debug log** (`wp-content/debug.log`)
3. **Test navigation** from Products → Discounts
4. **Copy the access control log entries** from `debug.log`
5. **Share them** so we can see what's actually in the completed_steps array

With this information, we'll know EXACTLY why the access control is blocking and can fix the actual root cause.

---

**Status:** 🔍 AWAITING DEBUG LOG OUTPUT

The JavaScript side is working perfectly. We need to see what's happening on the server side when the Discounts page loads.
