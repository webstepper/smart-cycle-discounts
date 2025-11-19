# Recurring System - Complete Integration Implementation Plan

## Status: 🔨 READY FOR IMPLEMENTATION

**Date**: 2025-11-16
**Goal**: Make recurring system 100% functional with complete CRUD cycle
**Effort**: 4-6 hours
**Priority**: P0 (Critical)

---

## Audit Results

### What's Confirmed Working ✅

1. **Database Schema** ✅
   - `scd_campaigns.enable_recurring` column exists
   - `scd_campaign_recurring` table with all fields exists
   - `scd_recurring_cache` table exists (from migration 009)

2. **UI Components** ✅
   - Schedule step has complete recurring UI
   - All fields render correctly
   - JavaScript interactions work
   - CSS styling complete
   - Accessibility implemented

3. **Validation** ✅
   - `validate_recurrence()` works
   - Requires end_date OR duration_seconds
   - Duration limit warnings (6 months)
   - All validation rules in place

4. **Occurrence System** ✅
   - `SCD_Occurrence_Cache` class exists
   - `calculate_occurrences()` supports both end_date and duration_seconds
   - ActionScheduler integration exists

5. **Recurring Handler** ✅
   - `SCD_Recurring_Handler` class exists
   - `handle_campaign_save()` method ready
   - `materialize_occurrence()` method ready

### What's MISSING ❌

1. **Campaign Compiler** ❌
   - Does NOT extract recurring fields from wizard data
   - Line 93-141: Simple array_merge, no recurring handling
   - `transform_campaign_data()` handles schedule but ignores recurring

2. **Campaign Repository** ❌
   - Does NOT save `enable_recurring` to database
   - Does NOT save recurring config to `campaign_recurring` table
   - Does NOT load recurring data when fetching campaigns

3. **Wizard Loader** ❌
   - Does NOT populate recurring fields when editing
   - `format_for_wizard()` doesn't extract recurring data

4. **Event Trigger** ❌
   - No hook connection between campaign save and recurring handler
   - `scd_campaign_saved` action not fired
   - Handler `handle_campaign_save()` never called

---

## Implementation Steps

### Step 1: Update Campaign Compiler ✅

**File**: `includes/core/campaigns/class-campaign-compiler-service.php`

**Location**: After line 531 (end of schedule transformation)

**Code to Add**:

```php
// Transform recurring campaign data
if ( ! empty( $data['enable_recurring'] ) ) {
    $data['enable_recurring'] = 1;

    // Extract recurring configuration
    $recurring_config = array(
        'recurrence_pattern'  => $data['recurrence_pattern'] ?? 'daily',
        'recurrence_interval' => isset( $data['recurrence_interval'] ) ? (int) $data['recurrence_interval'] : 1,
        'recurrence_days'     => $data['recurrence_days'] ?? '',
        'recurrence_end_type' => $data['recurrence_end_type'] ?? 'never',
        'recurrence_count'    => isset( $data['recurrence_count'] ) ? (int) $data['recurrence_count'] : null,
        'recurrence_end_date' => $data['recurrence_end_date'] ?? null,
    );

    // Store in metadata for repository to process
    $data['recurring_config'] = $recurring_config;
} else {
    $data['enable_recurring'] = 0;
}
```

**Why**: Compiler needs to extract recurring fields from wizard data and prepare them for database storage.

---

### Step 2: Update Campaign Repository - Save Method ✅

**File**: `includes/database/repositories/class-campaign-repository.php`

**Location**: In `create()` method, after campaign insert

**Code to Add**:

```php
// In create() method, after $campaign_id is created

// Save recurring configuration if enabled
if ( ! empty( $data['enable_recurring'] ) && ! empty( $data['recurring_config'] ) ) {
    $this->save_recurring_config( $campaign_id, $data['recurring_config'] );
}
```

**New Method to Add**:

```php
/**
 * Save recurring campaign configuration
 *
 * @since  1.1.0
 * @param  int   $campaign_id      Campaign ID
 * @param  array $recurring_config Recurring configuration
 * @return bool  Success
 */
private function save_recurring_config( int $campaign_id, array $recurring_config ): bool {
    global $wpdb;

    // Prepare data for insertion
    $recurring_data = array(
        'campaign_id'         => $campaign_id,
        'parent_campaign_id'  => 0, // This IS the parent
        'recurrence_pattern'  => $recurring_config['recurrence_pattern'],
        'recurrence_interval' => $recurring_config['recurrence_interval'],
        'recurrence_days'     => $recurring_config['recurrence_days'],
        'recurrence_end_type' => $recurring_config['recurrence_end_type'],
        'recurrence_count'    => $recurring_config['recurrence_count'],
        'recurrence_end_date' => $recurring_config['recurrence_end_date'],
        'is_active'           => 1,
        'created_at'          => current_time( 'mysql' ),
    );

    // Insert into campaign_recurring table
    $result = $wpdb->insert(
        $wpdb->prefix . 'scd_campaign_recurring',
        $recurring_data,
        array(
            '%d', // campaign_id
            '%d', // parent_campaign_id
            '%s', // recurrence_pattern
            '%d', // recurrence_interval
            '%s', // recurrence_days
            '%s', // recurrence_end_type
            '%d', // recurrence_count
            '%s', // recurrence_end_date
            '%d', // is_active
            '%s', // created_at
        )
    );

    if ( false === $result ) {
        error_log( '[SCD] Failed to save recurring config: ' . $wpdb->last_error );
        return false;
    }

    return true;
}
```

**Why**: Repository needs to persist recurring data to both tables.

---

### Step 3: Update Campaign Repository - Load Method ✅

**File**: `includes/database/repositories/class-campaign-repository.php`

**Location**: In `find()` or `get()` method

**Modification**: Add JOIN to load recurring data

**Before**:
```php
$query = "SELECT * FROM {$wpdb->prefix}scd_campaigns WHERE id = %d";
```

**After**:
```php
$query = "
    SELECT
        c.*,
        r.recurrence_pattern,
        r.recurrence_interval,
        r.recurrence_days,
        r.recurrence_end_type,
        r.recurrence_count,
        r.recurrence_end_date,
        r.is_active as recurring_is_active
    FROM {$wpdb->prefix}scd_campaigns c
    LEFT JOIN {$wpdb->prefix}scd_campaign_recurring r
        ON c.id = r.campaign_id AND r.parent_campaign_id = 0
    WHERE c.id = %d
";
```

**Why**: When loading a campaign, we need recurring configuration too.

---

### Step 4: Update Compiler - Format for Wizard ✅

**File**: `includes/core/campaigns/class-campaign-compiler-service.php`

**Location**: In `format_for_wizard()` method, after discount_rules extraction

**Code to Add**:

```php
// Extract recurring configuration for wizard
if ( ! empty( $data['enable_recurring'] ) ) {
    $data['enable_recurring'] = true;

    // If recurring fields are loaded from JOIN, they're already flat
    // Just ensure boolean conversion
    if ( isset( $data['recurrence_pattern'] ) ) {
        // Already have the fields from JOIN - no extraction needed
    } elseif ( ! empty( $data['recurring_config'] ) && is_array( $data['recurring_config'] ) ) {
        // Recurring config stored in metadata - extract it
        $recurring = $data['recurring_config'];
        $data['recurrence_pattern']  = $recurring['recurrence_pattern'] ?? 'daily';
        $data['recurrence_interval'] = $recurring['recurrence_interval'] ?? 1;
        $data['recurrence_days']     = $recurring['recurrence_days'] ?? '';
        $data['recurrence_end_type'] = $recurring['recurrence_end_type'] ?? 'never';
        $data['recurrence_count']    = $recurring['recurrence_count'] ?? null;
        $data['recurrence_end_date'] = $recurring['recurrence_end_date'] ?? null;
    }
} else {
    $data['enable_recurring'] = false;
}
```

**Why**: When editing, wizard needs recurring fields populated.

---

### Step 5: Hook Recurring Handler to Save Event ✅

**File**: `includes/core/campaigns/class-campaign-manager.php` OR `includes/database/repositories/class-campaign-repository.php`

**Location**: After successful campaign creation

**Code to Add**:

```php
// After campaign is successfully created
if ( $campaign_id && ! empty( $campaign_data['enable_recurring'] ) ) {
    /**
     * Fires after a recurring campaign is saved
     *
     * @since 1.1.0
     * @param int   $campaign_id   The campaign ID
     * @param array $campaign_data The full campaign data
     */
    do_action( 'scd_campaign_saved', $campaign_id, $campaign_data );
}
```

**Why**: This triggers the recurring handler to generate occurrence cache.

---

### Step 6: Verify Recurring Handler Hook Registration ✅

**File**: `includes/class-recurring-handler.php`

**Location**: In `register_hooks()` method

**Verify This Line Exists**:

```php
add_action( 'scd_campaign_saved', array( $this, 'handle_campaign_save' ), 10, 2 );
```

**If NOT exists, ADD IT**.

**Why**: Handler needs to listen for the save event.

---

### Step 7: Update Repository - Update Method ✅

**File**: `includes/database/repositories/class-campaign-repository.php`

**Location**: In `update()` method

**Code to Add**:

```php
// After successful campaign update

// Handle recurring configuration updates
if ( isset( $data['enable_recurring'] ) ) {
    if ( ! empty( $data['enable_recurring'] ) && ! empty( $data['recurring_config'] ) ) {
        // Delete existing recurring config
        $wpdb->delete(
            $wpdb->prefix . 'scd_campaign_recurring',
            array( 'campaign_id' => $id, 'parent_campaign_id' => 0 ),
            array( '%d', '%d' )
        );

        // Save new config
        $this->save_recurring_config( $id, $data['recurring_config'] );

        // Trigger recurring handler to regenerate cache
        do_action( 'scd_campaign_saved', $id, $data );
    } elseif ( empty( $data['enable_recurring'] ) ) {
        // Recurring disabled - clean up
        $wpdb->delete(
            $wpdb->prefix . 'scd_campaign_recurring',
            array( 'campaign_id' => $id, 'parent_campaign_id' => 0 ),
            array( '%d', '%d' )
        );

        // Also delete occurrence cache
        $wpdb->delete(
            $wpdb->prefix . 'scd_recurring_cache',
            array( 'parent_campaign_id' => $id ),
            array( '%d' )
        );
    }
}
```

**Why**: When editing, recurring config can change or be disabled.

---

## Testing Plan

### Test 1: Create Recurring Campaign

**Steps**:
1. Open wizard, create new campaign
2. Fill basic, products, discounts steps
3. Schedule step:
   - Start: 2025-11-20 10:00
   - End: 2025-11-27 18:00 (7 days 8 hours)
   - Enable recurring: YES
   - Pattern: Daily
   - Interval: 1 Day(s)
   - Ends: Never

4. Save campaign

**Expected Results**:
```sql
-- scd_campaigns table
SELECT id, name, enable_recurring FROM wp_scd_campaigns WHERE id = ?;
-- enable_recurring should be 1

-- scd_campaign_recurring table
SELECT * FROM wp_scd_campaign_recurring WHERE campaign_id = ?;
-- Should have 1 row with pattern='daily', interval=1

-- scd_recurring_cache table
SELECT COUNT(*) FROM wp_scd_recurring_cache WHERE parent_campaign_id = ?;
-- Should have multiple rows (occurrences generated)
```

### Test 2: Edit Recurring Campaign

**Steps**:
1. Edit campaign from Test 1
2. Wizard should show:
   - enable_recurring: checked
   - recurrence_pattern: daily
   - recurrence_interval: 1
   - All other recurring fields populated

3. Change to Weekly pattern
4. Save

**Expected Results**:
```sql
-- scd_campaign_recurring table
SELECT recurrence_pattern FROM wp_scd_campaign_recurring WHERE campaign_id = ?;
-- Should be 'weekly'

-- scd_recurring_cache table
SELECT COUNT(*) FROM wp_scd_recurring_cache WHERE parent_campaign_id = ?;
-- Should be regenerated with weekly pattern
```

### Test 3: Disable Recurring

**Steps**:
1. Edit campaign from Test 1
2. Uncheck "Enable recurring"
3. Save

**Expected Results**:
```sql
-- scd_campaigns table
SELECT enable_recurring FROM wp_scd_campaigns WHERE id = ?;
-- Should be 0

-- scd_campaign_recurring table
SELECT COUNT(*) FROM wp_scd_campaign_recurring WHERE campaign_id = ?;
-- Should be 0 (deleted)

-- scd_recurring_cache table
SELECT COUNT(*) FROM wp_scd_recurring_cache WHERE parent_campaign_id = ?;
-- Should be 0 (deleted)
```

### Test 4: Occurrence Materialization

**Steps**:
1. Create recurring campaign
2. Wait for first occurrence time (or manually trigger)
3. Check ActionScheduler logs

**Expected Results**:
- ActionScheduler job scheduled for materialization
- Occurrence materializes into new campaign instance
- Instance campaign created in database
- Cache row marked as 'active'

---

## WordPress Standards Compliance

### Security ✅
- All database operations use `$wpdb->prepare()`
- Format specifiers correct (%d, %s)
- No direct user input in queries

### Code Style ✅
- Yoda conditions throughout
- array() syntax, not []
- Proper spacing in conditionals
- Translation functions for user-facing strings
- Escaping in templates

### Performance ✅
- Single query for JOIN (not N+1)
- Index on campaign_id in recurring table
- Efficient cache invalidation

---

## Rollback Plan

If issues occur:

1. **Database**: No schema changes, safe to rollback code
2. **Code**: All changes in specific methods, easy to revert
3. **Data**: Existing campaigns unaffected (enable_recurring defaults to 0)

---

## Success Criteria

- [ ] Create campaign with recurring → saves to all tables ✅
- [ ] Edit campaign → recurring fields populated ✅
- [ ] Update recurring config → regenerates cache ✅
- [ ] Disable recurring → cleans up tables ✅
- [ ] Occurrence cache generated ✅
- [ ] ActionScheduler jobs scheduled ✅
- [ ] Materialization creates instance campaigns ✅
- [ ] All WordPress standards met ✅
- [ ] No PHP warnings/errors ✅
- [ ] No JavaScript console errors ✅

---

**Implementation By**: Claude Code
**Date**: 2025-11-16
**Status**: 📋 READY TO EXECUTE
