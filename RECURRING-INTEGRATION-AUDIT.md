# Recurring System Integration Audit

## Status: 🔴 INCOMPLETE INTEGRATION

**Date**: 2025-11-16
**Issue**: Recurring feature UI exists but data is not saved/loaded/executed
**Severity**: Critical - Feature non-functional

---

## What Exists (✅)

### 1. Database Schema ✅
**Location**: `includes/database/migrations/001-initial-schema.php`

```sql
-- Campaigns table
enable_recurring tinyint(1) NOT NULL DEFAULT 0

-- Campaign_recurring table
recurrence_pattern varchar(20) NOT NULL DEFAULT 'daily'
recurrence_interval int(11) NOT NULL DEFAULT 1
recurrence_days text
recurrence_end_type varchar(20) NOT NULL DEFAULT 'never'
recurrence_count int(11) DEFAULT NULL
recurrence_end_date date DEFAULT NULL
```

### 2. UI Components ✅
- `resources/views/admin/wizard/step-schedule.php` (lines 393-607)
- `resources/assets/css/admin/step-schedule.css` (recurring styles)
- `resources/assets/js/steps/schedule/schedule-orchestrator.js` (recurring handlers)

### 3. Validation ✅
- `includes/core/validation/step-validators/class-schedule-step-validator.php`
- `validate_recurrence()` method exists and works

### 4. Occurrence Cache System ✅
- `includes/core/campaigns/class-occurrence-cache.php`
- `includes/database/migrations/009-recurring-refactor.php` (cache table)

### 5. Recurring Handler ✅
- `includes/class-recurring-handler.php`
- ActionScheduler integration exists

---

## What's Missing (❌)

### 1. ❌ Campaign Compiler Integration

**File**: `includes/core/campaigns/class-campaign-compiler-service.php`

**Problem**: Compiler doesn't extract recurring fields from wizard data

**Current**: Only compiles basic, products, discounts, schedule fields
**Missing**: No handling of `enable_recurring`, `recurrence_pattern`, etc.

**What needs to happen**:
```php
// In compile() method around line 150-200
if ( ! empty( $wizard_data['schedule']['enable_recurring'] ) ) {
    $campaign_data['enable_recurring'] = 1;

    $recurring_data = array(
        'recurrence_pattern'  => $wizard_data['schedule']['recurrence_pattern'],
        'recurrence_interval' => $wizard_data['schedule']['recurrence_interval'],
        'recurrence_days'     => $wizard_data['schedule']['recurrence_days'] ?? '',
        'recurrence_end_type' => $wizard_data['schedule']['recurrence_end_type'] ?? 'never',
        'recurrence_count'    => $wizard_data['schedule']['recurrence_count'] ?? null,
        'recurrence_end_date' => $wizard_data['schedule']['recurrence_end_date'] ?? null,
    );

    $campaign_data['recurring_config'] = $recurring_data;
} else {
    $campaign_data['enable_recurring'] = 0;
}
```

---

### 2. ❌ Campaign Repository Integration

**File**: `includes/database/repositories/class-campaign-repository.php`

**Problem**: Repository doesn't save or load recurring fields

**Missing in `create()` method**:
- No code to save `enable_recurring` to campaigns table
- No code to save recurring config to `campaign_recurring` table

**Missing in `find()` / `get()` methods**:
- No code to load recurring settings when editing campaign

**What needs to happen**:

```php
// In create() method
public function create( array $data ): int {
    // ... existing code ...

    // Save enable_recurring flag
    $campaign_data['enable_recurring'] = isset( $data['enable_recurring'] ) ? (int) $data['enable_recurring'] : 0;

    $campaign_id = $this->insert( 'scd_campaigns', $campaign_data );

    // If recurring enabled, save recurring config
    if ( $campaign_data['enable_recurring'] && ! empty( $data['recurring_config'] ) ) {
        $this->save_recurring_config( $campaign_id, $data['recurring_config'] );
    }

    return $campaign_id;
}

// New method needed
private function save_recurring_config( int $campaign_id, array $recurring_config ): void {
    global $wpdb;

    $wpdb->insert(
        $wpdb->prefix . 'scd_campaign_recurring',
        array(
            'campaign_id'         => $campaign_id,
            'recurrence_pattern'  => $recurring_config['recurrence_pattern'],
            'recurrence_interval' => $recurring_config['recurrence_interval'],
            'recurrence_days'     => $recurring_config['recurrence_days'],
            'recurrence_end_type' => $recurring_config['recurrence_end_type'],
            'recurrence_count'    => $recurring_config['recurrence_count'],
            'recurrence_end_date' => $recurring_config['recurrence_end_date'],
        )
    );
}

// In find() method - need to JOIN recurring table
public function find( int $id ): ?array {
    global $wpdb;

    $query = "
        SELECT c.*, r.*
        FROM {$wpdb->prefix}scd_campaigns c
        LEFT JOIN {$wpdb->prefix}scd_campaign_recurring r ON c.id = r.campaign_id
        WHERE c.id = %d
    ";

    $campaign = $wpdb->get_row( $wpdb->prepare( $query, $id ), ARRAY_A );

    // ... rest of method ...
}
```

---

### 3. ❌ Recurring Handler Trigger

**File**: `includes/class-recurring-handler.php`

**Problem**: Handler exists but is never triggered after campaign save

**Current**: Has `handle_campaign_save()` method but no hook connection
**Missing**: No `add_action( 'scd_campaign_saved', ... )` registration

**What needs to happen**:

```php
// In __construct() or register_hooks()
add_action( 'scd_campaign_saved', array( $this, 'handle_campaign_save' ), 10, 2 );
```

**Or in the save flow**:
```php
// After campaign is saved
if ( $campaign_data['enable_recurring'] ) {
    do_action( 'scd_campaign_saved', $campaign_id, $campaign_data );
}
```

---

### 4. ❌ Wizard Data Loader

**File**: `includes/admin/ajax/handlers/class-load-data-handler.php` (probably)

**Problem**: When editing a campaign, recurring fields are not populated in the wizard

**Current**: Loads basic, products, discounts, schedule data
**Missing**: Doesn't load recurring config into schedule step

**What needs to happen**:

```php
// In load_schedule_data() or similar method
private function load_schedule_data( $campaign_id ) {
    global $wpdb;

    // Load basic schedule fields
    $schedule_data = array( /* ... */ );

    // Load recurring config if enabled
    $recurring = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}scd_campaign_recurring WHERE campaign_id = %d",
            $campaign_id
        ),
        ARRAY_A
    );

    if ( $recurring ) {
        $schedule_data['enable_recurring']     = true;
        $schedule_data['recurrence_pattern']   = $recurring['recurrence_pattern'];
        $schedule_data['recurrence_interval']  = $recurring['recurrence_interval'];
        $schedule_data['recurrence_days']      = $recurring['recurrence_days'];
        $schedule_data['recurrence_end_type']  = $recurring['recurrence_end_type'];
        $schedule_data['recurrence_count']     = $recurring['recurrence_count'];
        $schedule_data['recurrence_end_date']  = $recurring['recurrence_end_date'];
    }

    return $schedule_data;
}
```

---

### 5. ❌ Service Container Registration

**File**: `includes/bootstrap/class-service-definitions.php`

**Problem**: Recurring handler may not be registered in service container

**Check needed**: Verify `SCD_Recurring_Handler` is registered and instantiated

---

## Data Flow Diagram

### Current (Broken) Flow

```
User fills wizard
    ↓
JavaScript collects data
    ↓
AJAX sends to save_step handler
    ↓
Validation runs ✅
    ↓
Campaign compiler runs ❌ (ignores recurring fields)
    ↓
Repository saves campaign ❌ (no recurring data)
    ↓
Campaign saved to database (enable_recurring = 0 always)
    ↓
Recurring handler NEVER TRIGGERED ❌
    ↓
No occurrences generated ❌
```

### Required (Working) Flow

```
User fills wizard
    ↓
JavaScript collects data (including recurring fields)
    ↓
AJAX sends to save_step handler
    ↓
Validation runs ✅
    ↓
Campaign compiler extracts recurring fields ✅ NEED TO ADD
    ↓
Repository saves campaign ✅ NEED TO ADD
    ├─ Saves enable_recurring to campaigns table
    └─ Saves recurring config to campaign_recurring table
    ↓
Fire 'scd_campaign_saved' action ✅ NEED TO ADD
    ↓
Recurring handler triggered ✅ EXISTS
    ├─ Generates occurrence cache
    ├─ Schedules ActionScheduler jobs
    └─ Returns success
    ↓
Campaign saved with recurring data ✅
```

---

## Testing Checklist (All Currently Fail)

### Create Test
- [ ] Fill wizard with recurring settings
- [ ] Save campaign
- [ ] **Expected**: `enable_recurring = 1` in campaigns table
- [ ] **Expected**: Row in `campaign_recurring` table
- [ ] **Expected**: Rows in `scd_recurring_cache` table
- [ ] **Current**: ❌ All fail - no data saved

### Edit Test
- [ ] Create recurring campaign
- [ ] Edit campaign
- [ ] **Expected**: Recurring fields populated in wizard
- [ ] **Current**: ❌ Fields empty

### Execution Test
- [ ] Create recurring campaign
- [ ] Wait for occurrence time
- [ ] **Expected**: Instance campaign created
- [ ] **Current**: ❌ Nothing happens (no occurrences exist)

---

## Implementation Priority

### Phase 1: Basic CRUD (Critical - P0)
1. ✅ Update campaign compiler to extract recurring fields
2. ✅ Update campaign repository to save recurring data
3. ✅ Add recurring data to wizard load flow
4. ✅ Trigger recurring handler on save
5. ✅ Test create/edit/load cycle

**Effort**: 4-6 hours
**Blocks**: Everything else

### Phase 2: Execution (High - P1)
1. ✅ Verify occurrence cache generation
2. ✅ Verify ActionScheduler integration
3. ✅ Test materialization flow
4. ✅ Test complete end-to-end

**Effort**: 2-3 hours
**Depends**: Phase 1

### Phase 3: Edge Cases (Medium - P2)
1. Update existing campaigns
2. Delete campaign with occurrences
3. Pause/resume recurring campaigns
4. Handle failures gracefully

**Effort**: 3-4 hours
**Depends**: Phase 1-2

---

## Files That Need Changes

### Must Change (Phase 1)
1. `includes/core/campaigns/class-campaign-compiler-service.php` - Add recurring extraction
2. `includes/database/repositories/class-campaign-repository.php` - Add save/load
3. `includes/admin/ajax/handlers/class-load-data-handler.php` - Add wizard load
4. Hook connection between save and recurring handler

### May Need Changes
1. `includes/bootstrap/class-service-definitions.php` - Verify registration
2. `includes/core/campaigns/class-campaign-manager.php` - May need updates

---

## Conclusion

The recurring system has all the pieces but they're not connected:
- ✅ UI works
- ✅ Validation works
- ✅ Database schema exists
- ✅ Occurrence cache system exists
- ✅ Recurring handler exists
- ❌ **Data never reaches the database**
- ❌ **Handler never triggered**
- ❌ **Edit flow broken**

**This is a complete integration issue, not a design flaw.**

Once integrated properly, the system will work as designed. The architectural issues (snapshot-based, no change detection) are separate concerns that can be addressed later with Phases 2-5 from the architectural analysis.

---

**Next Step**: Implement Phase 1 integration (4-6 hours) to make recurring fully functional.

**Created By**: Claude Code
**Date**: 2025-11-16
**Status**: 🔴 READY FOR IMPLEMENTATION
