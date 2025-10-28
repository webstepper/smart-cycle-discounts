# Notification Timeout Fix

## Bug Report

**User:** "also this notification is not disappear. It should after 3 sec: 5 product(s) removed - not in selected categories"

## Root Cause Analysis

### The Bug

The notification was not disappearing because of incorrect API usage.

**Original Code:**
```javascript
SCD.Shared.NotificationService.info(
    removedCount + ' product(s) removed - not in selected categories',
    { timeout: 4000 }  // ❌ WRONG: This is treated as 'options' parameter
);
```

### Why It Didn't Work

The `info()` shorthand method has this signature:
```javascript
info: function( message, options ) {
    return this.show( message, 'info', null, options );
                                      // ↑ Passes NULL as duration!
}
```

Flow:
1. `info(message, { timeout: 4000 })` is called
2. `{ timeout: 4000 }` goes into `options` parameter
3. Shorthand calls `show(message, 'info', null, options)`
4. **`null` is passed as duration!**
5. In `show()` method (line 99):
   ```javascript
   duration = 'undefined' !== typeof duration ? duration : this.config.defaultDuration;
   // null !== 'undefined' → true
   // So duration = null (not defaultDuration!)
   ```
6. Line 125:
   ```javascript
   if ( 0 < duration ) {  // 0 < null → false!
       this.scheduleHide( notification, duration );  // ❌ Never called!
   }
   ```

**Result:** Notification never schedules auto-hide, stays visible forever!

### NotificationService API

The correct API signature is:
```javascript
show( message, type, duration, options )
```

**Shorthand methods** (`success()`, `error()`, `warning()`, `info()`):
```javascript
shorthand( message, options )
```

These shortcuts always pass `null` as duration, which breaks the auto-hide logic.

## The Fix ✅

Use `show()` directly with explicit duration parameter:

```javascript
SCD.Shared.NotificationService.show(
    removedCount + ' product(s) removed - not in selected categories',
    'info',
    3000  // 3 seconds - explicit duration parameter
);
```

This correctly passes:
- `message` → 1st parameter
- `'info'` → 2nd parameter (type)
- `3000` → 3rd parameter (duration in milliseconds)
- `undefined` → 4th parameter (options, not needed)

## Code Changes

**File:** `products-picker.js:469-479`

**Before (BROKEN):**
```javascript
if ( filtered.length < selected.length ) {
    var removedCount = selected.length - filtered.length;
    if ( SCD.Shared && SCD.Shared.NotificationService ) {
        SCD.Shared.NotificationService.info(
            removedCount + ' product(s) removed - not in selected categories',
            { timeout: 4000 }  // ❌ Wrong parameter
        );
    }
}
```

**After (FIXED):**
```javascript
if ( filtered.length < selected.length ) {
    var removedCount = selected.length - filtered.length;
    if ( SCD.Shared && SCD.Shared.NotificationService ) {
        SCD.Shared.NotificationService.show(
            removedCount + ' product(s) removed - not in selected categories',
            'info',
            3000  // ✅ Correct: 3 seconds as requested
        );
    }
}
```

## Why This Happens

This is a **design flaw in NotificationService** shorthand methods:

```javascript
// Shorthand methods pass NULL as duration
info: function( message, options ) {
    return this.show( message, 'info', null, options );
                                      // ↑ Should use undefined or options.duration
}
```

The shorthand methods should either:
1. Pass `undefined` instead of `null` (to trigger default duration), OR
2. Check `options.duration` and use it if provided

**Current behavior:**
- Shorthand methods create **permanent notifications** (never hide)
- This is probably not intended behavior

**However**, fixing the shorthand methods is outside the scope of this issue. The correct solution for our use case is to use `show()` directly.

## Alternative Solutions Considered

### Option 1: Fix NotificationService Shorthand Methods
```javascript
info: function( message, options ) {
    options = options || {};
    var duration = options.duration || options.timeout || undefined;
    return this.show( message, 'info', duration, options );
}
```

**Not used:** Would require changing shared service, affecting other code.

### Option 2: Pass Duration in Options
```javascript
SCD.Shared.NotificationService.info(
    message,
    { duration: 3000 }  // Instead of timeout
);
```

**Not used:** Doesn't work with current implementation.

### Option 3: Use show() Directly ✅
```javascript
SCD.Shared.NotificationService.show( message, 'info', 3000 );
```

**Used:** Clean, explicit, works with current API.

## Testing

### Test 1: Notification Disappears
1. Select Category A
2. Add 5 products
3. Change to Category B
4. **VERIFY:** Notification appears: "5 product(s) removed - not in selected categories"
5. **VERIFY:** Notification disappears after exactly 3 seconds ✅

### Test 2: Notification Can Be Closed Manually
1. Trigger notification
2. Click the X button
3. **VERIFY:** Notification closes immediately ✅

### Test 3: Hover Pauses Timer
1. Trigger notification
2. Hover over notification within 3 seconds
3. **VERIFY:** Notification stays visible while hovered
4. Move mouse away
5. **VERIFY:** Notification disappears after remaining time ✅

## Result

✅ **Notification now disappears after 3 seconds as requested!**

- ✅ Correct API usage
- ✅ Explicit duration parameter
- ✅ Works as expected
- ✅ Clean and maintainable

---

**Implementation Date:** 2025-10-28
**Status:** ✅ FIXED
**Notification Duration:** 3 seconds
