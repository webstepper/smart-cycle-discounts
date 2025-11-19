# Recurring System - Critical Bugs Found

## Status: 🔴 CRITICAL ISSUES IDENTIFIED

**Date**: 2025-11-16
**Reporter**: User Analysis
**Severity**: Critical - Recurring campaigns silently fail

---

## Summary

The recurring campaign system has critical bugs that cause it to fail silently when the main campaign has no end date (indefinite duration). The system appears to work but creates NO recurring occurrences.

---

## Bug #1: Indefinite Campaigns Break Recurring (CRITICAL)

### Location
`includes/core/campaigns/class-occurrence-cache.php` lines 144-146

### The Bug
```php
private function calculate_occurrences( array $recurring, array $schedule ): array {
    // ...

    // Validate required fields
    if ( empty( $schedule['start_date'] ) || empty( $schedule['end_date'] ) ) {
        return array(); // ❌ FAILS SILENTLY - returns empty array!
    }

    // Calculate campaign duration
    $start_time = $schedule['start_time'] ?? '00:00';
    $end_time   = $schedule['end_time'] ?? '23:59';

    $start    = new DateTime( $schedule['start_date'] . ' ' . $start_time );
    $end      = new DateTime( $schedule['end_date'] . ' ' . $end_time ); // ❌ FATAL if end_date is null!
    $duration = $start->diff( $end ); // ❌ Can't calculate duration without end_date
```

### Reproduction Steps

1. Create a new campaign in the wizard
2. **Basic Step**: Name it "Test Recurring"
3. **Products Step**: Select some products
4. **Discounts Step**: Configure a discount
5. **Schedule Step**:
   - Start date: Tomorrow
   - Start time: 10:00
   - **End date: Leave EMPTY** (indefinite)
   - **Enable Recurring**: ON
   - Recurrence pattern: Daily
   - Repeat every: 1 Day(s)
   - Ends: Never
6. **Review & Save**

### What Happens
- ✅ Validation passes (no error shown to user)
- ✅ Campaign saves successfully
- ❌ `calculate_occurrences()` returns empty array (line 145)
- ❌ No occurrences are created in database
- ❌ Recurring schedule appears to work but **does nothing**
- ❌ No error logged or shown to user

### Expected Behavior
The system should either:
1. **Require end_date when recurring is enabled**, OR
2. **Use duration_seconds to calculate duration**, OR
3. **Show validation error**: "End date is required for recurring campaigns"

---

## Bug #2: No Validation for Recurring + Indefinite Campaign (CRITICAL)

### Location
`includes/core/validation/step-validators/class-schedule-step-validator.php` lines 119-125

### The Bug
```php
private static function validate_date_logic( array $data, WP_Error $errors ) {
    $start_date = isset( $data['start_date'] ) ? $data['start_date'] : '';
    $end_date   = isset( $data['end_date'] ) ? $data['end_date'] : '';

    if ( empty( $start_date ) || empty( $end_date ) ) {
        return; // ❌ RETURNS EARLY - doesn't validate recurring requirements!
    }
```

### The Problem
The validator has NO check for this scenario:
- `enable_recurring` = true
- `end_date` = empty (indefinite campaign)

This combination should be **REJECTED** with a validation error, but it passes through.

### Location of Missing Validation
`class-schedule-step-validator.php` lines 289-378 `validate_recurrence()` method

**What's there**:
- Line 296-312: Validates recurrence interval (min/max)
- Line 314-350: Validates recurrence interval vs campaign duration
- Line 352-377: Validates occurrence count

**What's MISSING**:
```php
// ❌ MISSING VALIDATION
if ( $is_recurring && empty( $data['end_date'] ) && empty( $data['duration_seconds'] ) ) {
    $errors->add(
        'recurring_requires_end_date',
        __( 'Recurring campaigns require an end date to determine occurrence duration.', 'smart-cycle-discounts' ),
        array( 'severity' => 'critical' )
    );
}
```

---

## Bug #3: Duration Calculation Doesn't Use duration_seconds (DESIGN FLAW)

### Location
`includes/core/campaigns/class-occurrence-cache.php` lines 150-156

### The Problem
```php
// Calculate campaign duration
$start_time = $schedule['start_time'] ?? '00:00';
$end_time   = $schedule['end_time'] ?? '23:59';

$start    = new DateTime( $schedule['start_date'] . ' ' . $start_time );
$end      = new DateTime( $schedule['end_date'] . ' ' . $end_time ); // ❌ Requires end_date
$duration = $start->diff( $end ); // ❌ Doesn't consider duration_seconds
```

### Why It's a Problem
The campaign compiler service (`class-campaign-compiler-service.php` lines 438-466) shows that campaigns can have:
1. **Explicit end_date + end_time**, OR
2. **duration_seconds** (for timeline presets like "3 Day Weekend", "Flash Sale - 6 Hours")

But the occurrence cache ONLY supports #1, not #2.

### What Should Happen
```php
// Calculate campaign duration
if ( ! empty( $schedule['end_date'] ) ) {
    // Option 1: Explicit end date
    $start    = new DateTime( $schedule['start_date'] . ' ' . $start_time );
    $end      = new DateTime( $schedule['end_date'] . ' ' . $end_time );
    $duration = $start->diff( $end );
} elseif ( ! empty( $schedule['duration_seconds'] ) ) {
    // Option 2: Duration-based (timeline presets)
    $duration = new DateInterval( 'PT' . $schedule['duration_seconds'] . 'S' );
} else {
    // Option 3: Default to 7 days for indefinite campaigns
    $duration = new DateInterval( 'P7D' ); // 7 days
    $this->logger->warning( 'Using default 7-day duration for indefinite recurring campaign', array( 'parent_id' => $parent_id ) );
}
```

---

## Bug #4: Field Definitions Don't Mark end_date as Required When Recurring (VALIDATION GAP)

### Location
`includes/core/validation/class-field-definitions.php` schedule step fields

### The Problem
The `end_date` field is not conditionally required based on `enable_recurring`:

```php
'end_date' => array(
    'type'       => 'date',
    'label'      => __( 'End Date', 'smart-cycle-discounts' ),
    'required'   => false, // ❌ Should be true when enable_recurring is true
    'default'    => '',
    // ...
),
```

### What Should Happen
Either:
1. Make `end_date` conditionally required when `enable_recurring` is true
2. Add custom validation in `validate_recurrence()` method
3. Document that indefinite + recurring is not supported

---

## Impact Assessment

### Severity: 🔴 CRITICAL

**Why Critical:**
1. **Silent Failure**: No error shown to user, campaign appears to save successfully
2. **Data Loss**: User expects recurring occurrences, gets nothing
3. **User Confusion**: "Why isn't my recurring campaign working?"
4. **Support Burden**: Users will report "recurring campaigns don't work"

### Affected Scenarios

| Scenario | Campaign End Date | Recurring Enabled | Result | Should Be |
|----------|------------------|-------------------|---------|-----------|
| 1 | Set | No | ✅ Works | ✅ Works |
| 2 | Set | Yes | ✅ Works | ✅ Works |
| 3 | **Empty** | No | ✅ Works | ✅ Works |
| 4 | **Empty** | Yes | 🔴 **FAILS SILENTLY** | ❌ Validation Error |
| 5 | Empty, duration_seconds set | Yes | 🔴 **FAILS** | ✅ Should Work |

**Scenario 4** is the critical bug - it appears to work but doesn't.
**Scenario 5** is a design flaw - should use duration_seconds but doesn't.

---

## Recommended Fixes

### Fix Priority: IMMEDIATE (P0)

### Option A: Require End Date for Recurring (SIMPLEST)

**Pros:**
- Simplest fix
- Clear user requirement
- No risk of edge cases

**Cons:**
- Doesn't support indefinite recurring campaigns
- Less flexible

**Implementation:**
1. Add validation in `validate_recurrence()`:
```php
// After line 294 in class-schedule-step-validator.php
if ( $is_recurring ) {
    // Recurring campaigns MUST have end date to determine occurrence duration
    if ( empty( $data['end_date'] ) && empty( $data['duration_seconds'] ) ) {
        $errors->add(
            'recurring_requires_end_date',
            __( 'Recurring campaigns require an end date to determine the duration for each occurrence.', 'smart-cycle-discounts' ),
            array( 'severity' => 'critical' )
        );
        return; // Stop validation early
    }
}
```

2. Update UI to show this requirement clearly in step-schedule.php

### Option B: Support duration_seconds for Recurring (BETTER)

**Pros:**
- More flexible
- Supports all existing campaign types
- Matches compiler service design

**Cons:**
- More complex implementation
- Need to test thoroughly

**Implementation:**
1. Update `calculate_occurrences()` to support duration_seconds:
```php
// Calculate campaign duration
$duration = null;
if ( ! empty( $schedule['end_date'] ) ) {
    // Option 1: Explicit end date
    $start    = new DateTime( $schedule['start_date'] . ' ' . $start_time );
    $end      = new DateTime( $schedule['end_date'] . ' ' . $end_time );
    $duration = $start->diff( $end );
} elseif ( ! empty( $schedule['duration_seconds'] ) ) {
    // Option 2: Duration-based
    $duration = new DateInterval( 'PT' . $schedule['duration_seconds'] . 'S' );
} else {
    // Invalid: no way to determine duration
    $this->logger->error( 'Cannot calculate recurring occurrences: no end_date or duration_seconds' );
    return array();
}
```

2. Add validation to ensure one of the two is set
3. Update UI messaging

### Option C: Default Duration for Indefinite (RISKY)

**Pros:**
- Most flexible
- Allows indefinite recurring

**Cons:**
- Assumes duration (could be wrong)
- Users may not realize what duration is being used
- Could lead to confusion

**Not Recommended** - too much implicit behavior.

---

## Recommended Solution

**Use Option B**: Support both end_date and duration_seconds

This matches the existing campaign compiler design and provides maximum flexibility while being explicit about duration.

### Implementation Steps

1. **Add Validation** (10 min)
   - Update `validate_recurrence()` in schedule validator
   - Require either end_date OR duration_seconds when recurring enabled

2. **Update Occurrence Cache** (20 min)
   - Modify `calculate_occurrences()` to support duration_seconds
   - Add proper error logging

3. **Add UI Help Text** (5 min)
   - Update step-schedule.php to explain requirement
   - Add tooltip: "Recurring campaigns need an end date to determine how long each occurrence should last"

4. **Test All Scenarios** (30 min)
   - Test with end_date set
   - Test with duration_seconds set
   - Test with neither (should fail validation)
   - Test with both (end_date takes precedence)

5. **Update Documentation** (10 min)
   - Update user docs
   - Add to troubleshooting guide

**Total Effort**: ~75 minutes

---

## Testing Checklist

After fix is implemented, test these scenarios:

### Scenario 1: Recurring with End Date (Should Work)
- [  ] Campaign: Start 2025-11-20, End 2025-11-27
- [  ] Recurring: Daily, Never ends
- [  ] Expected: 7-day occurrences generated starting 2025-11-27
- [  ] Verify: Check `wp_scd_recurring_cache` table

### Scenario 2: Recurring with Duration Preset (Should Work After Fix)
- [  ] Campaign: Flash Sale (6 hours duration_seconds)
- [  ] Start: 2025-11-20 10:00
- [  ] Recurring: Daily, After 7 occurrences
- [  ] Expected: 6-hour occurrences for 7 days
- [  ] Verify: Each occurrence is 6 hours long

### Scenario 3: Recurring with No End Date, No Duration (Should Fail Validation)
- [  ] Campaign: Start 2025-11-20, No end
- [  ] Recurring: Weekly, Never ends
- [  ] Expected: Validation error shown
- [  ] Error: "Recurring campaigns require an end date or duration"

### Scenario 4: Weekly Pattern with Days Selected
- [  ] Campaign: Start Mon 2025-11-17, End Sun 2025-11-23
- [  ] Recurring: Weekly on Mon/Wed/Fri, After 4 occurrences
- [  ] Expected: Only Mon/Wed/Fri occurrences generated
- [  ] Verify: No occurrences on Tue/Thu/Sat/Sun

### Scenario 5: Monthly Pattern (Edge Case)
- [  ] Campaign: Start 2025-01-31, End 2025-02-02
- [  ] Recurring: Monthly, After 12 occurrences
- [  ] Expected: Feb occurrence skipped (no Feb 31)
- [  ] Verify: Logs warning about skipped month

---

## Additional Edge Cases to Consider

### Timezone Handling
- What if user changes WordPress timezone after occurrences are cached?
- Do occurrences update or remain in original timezone?

### Daylight Saving Time
- What happens when occurrence spans DST transition?
- Does duration account for hour gained/lost?

### Far Future Occurrences
- Recurring "never" with daily pattern = unlimited occurrences
- Current safety limit: 100 occurrences per cache generation
- Current cache horizon: 90 days
- Is this sufficient?

### Occurrence Numbering
- If cache is regenerated, does occurrence_number restart?
- Could lead to duplicate occurrence numbers if occurrences already materialized

---

## Conclusion

The user's concern is **100% valid**. The recurring system has critical bugs that cause silent failures when campaigns have no end date. This must be fixed before recurring campaigns can be considered production-ready.

**Recommended Action**: Implement Option B (support duration_seconds) immediately.

---

**Identified By**: User Analysis + Code Review
**Date**: 2025-11-16
**Priority**: P0 (Critical)
**Status**: 🔴 BUGS CONFIRMED, FIX NEEDED
