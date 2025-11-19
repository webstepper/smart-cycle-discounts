# Architecture Refactoring Progress

## ✅ Completed Tasks

### 1. Migration 007 Created
**File:** `includes/database/migrations/007-refactor-campaign-structure.php`

**What it does:**
- Adds `conditions_logic` column (VARCHAR)
- Adds `random_product_count` column (INT)
- Adds `compiled_at` column (DATETIME)
- Adds `compilation_method` column (VARCHAR)
- Creates `wp_scd_campaign_conditions` table
- Migrates existing data from metadata JSON to new structure
- Data migration is automatic - no data loss

### 2. Campaign Conditions Repository Created
**File:** `includes/database/repositories/class-campaign-conditions-repository.php`

**Provides:**
- `get_conditions_for_campaign($campaign_id)` - Get all conditions
- `save_conditions($campaign_id, $conditions)` - Save/replace conditions
- `delete_conditions($campaign_id)` - Delete all conditions
- `has_conditions($campaign_id)` - Check if has conditions
- `count_conditions($campaign_id)` - Count conditions
- `get_campaigns_with_condition_type($type)` - Query by condition type

### 3. Campaign Class Updated
**File:** `includes/core/campaigns/class-campaign.php`

**New Properties:**
```php
private string $conditions_logic = 'all';
private int $random_product_count = 5;
private ?DateTime $compiled_at = null;
private ?string $compilation_method = null;
```

**New Methods:**
```php
get_conditions_logic() / set_conditions_logic()
get_random_product_count() / set_random_product_count()
get_compiled_at() / set_compiled_at()
get_compilation_method() / set_compilation_method()
needs_recompilation() - Check if needs recompile
mark_compiled($method) - Set compilation tracking
```

**Updated:**
- `to_array()` includes new fields

### 4. Campaign Repository Updated
**File:** `includes/database/repositories/class-campaign-repository.php`

**Updated Methods:**
- `hydrate()` - Loads new fields from database
- `get_data_format()` - Handles `random_product_count` as INT

---

## 🚧 In Progress

### 5. Campaign Manager Compilation Logic
**File:** `includes/core/campaigns/class-campaign-manager.php`
**Method:** `compile_product_selection()`

**Needs to:**
- Load conditions from conditions repository (not metadata)
- Use campaign's `conditions_logic` property
- Use campaign's `random_product_count` property
- Call `campaign->mark_compiled($method)` after successful compilation
- Remove all metadata references for conditions/logic/count

---

## 📋 Remaining Tasks

### 6. Register Conditions Repository in Service Container
**File:** `includes/bootstrap/class-service-definitions.php`

**Add:**
```php
'campaign_conditions_repository' => array(
    'class' => 'SCD_Campaign_Conditions_Repository',
    'dependencies' => array( 'database_manager' ),
),
```

### 7. Update Services Using Metadata/Conditions

**Files to update:**
- Product Selector - Read conditions from repository
- Wizard handlers - Save conditions to repository
- Any service reading `metadata['product_conditions']`

### 8. Update Wizard Handlers

**Files:**
- `includes/admin/ajax/handlers/class-save-step-handler.php`
- Save conditions to repository when saving products step

### 9. Run Migration

```bash
# Access WordPress admin
# The migration will run automatically via Migration Manager
# OR manually trigger via WP-CLI:
wp scd migrate
```

### 10. Test With Existing Campaigns

- Campaign 95 (category filtered)
- Campaign 96 (random products)
- Verify products compile correctly
- Verify discounts apply

### 11. Clean Up

- Remove deprecated metadata references
- Update code comments
- Remove old compilation logic
- Add inline documentation

---

## 🎯 Architecture Benefits

**Before:**
```php
// ❌ Data scattered in JSON
$metadata['product_conditions']  // JSON blob
$metadata['conditions_logic']    // JSON blob
$metadata['random_count']        // JSON blob
// Can't query, no type safety, unclear schema
```

**After:**
```php
// ✅ Clean, queryable, type-safe
$campaign->get_conditions_logic()      // Dedicated column
$campaign->get_random_product_count()  // Dedicated column
$campaign->get_compiled_at()           // Audit trail
$conditions_repo->get_conditions()     // Separate table, queryable
```

**Query Example:**
```sql
-- Find all campaigns with price conditions
SELECT DISTINCT c.*
FROM wp_scd_campaigns c
JOIN wp_scd_campaign_conditions cc ON c.id = cc.campaign_id
WHERE cc.condition_type = 'price'
AND c.status = 'active';
```

---

## 🔍 Testing Checklist

- [ ] Migration runs without errors
- [ ] Existing campaigns load correctly
- [ ] Conditions visible in new table
- [ ] Product compilation works
- [ ] Discounts apply correctly
- [ ] Wizard saves to new structure
- [ ] No PHP errors in logs
- [ ] No JavaScript errors in console

---

## 📝 Next Steps

1. Complete Campaign Manager refactor
2. Register conditions repository
3. Update wizard handlers
4. Run migration
5. Test thoroughly
6. Clean up old code
7. Document new architecture
