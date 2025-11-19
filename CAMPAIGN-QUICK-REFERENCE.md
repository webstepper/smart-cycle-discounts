# Campaign Management System - Quick Reference Guide

## File Locations

### Core Files
- **Campaign Model**: `/includes/core/campaigns/class-campaign.php` (703 lines)
- **Campaign Manager**: `/includes/core/campaigns/class-campaign-manager.php` (1000+ lines)
- **Campaign Repository**: `/includes/database/repositories/class-campaign-repository.php` (1582 lines)
- **Campaign List Table**: `/includes/admin/components/class-campaigns-list-table.php` (1556 lines)
- **Campaign Formatter**: `/includes/core/campaigns/class-campaign-formatter.php` (200+ lines)
- **Campaign Serializer**: `/includes/core/campaigns/class-campaign-serializer.php` (200+ lines)

### Related Files
- Campaign Edit Controller: `/includes/core/campaigns/class-campaign-edit-controller.php`
- Campaign List Controller: `/includes/core/campaigns/class-campaign-list-controller.php`
- Campaign Health Service: `/includes/core/services/class-campaign-health-service.php`
- Campaign Compiler Service: `/includes/core/campaigns/class-campaign-compiler-service.php`

---

## Key Classes & Methods

### SCD_Campaign (Data Model)

**Identification:**
- `get_id()` / `set_id(int)`
- `get_uuid()` / `set_uuid(string)`
- `get_name()` / `set_name(string)`
- `get_slug()` / `set_slug(string)`

**Status & Configuration:**
- `get_status()` / `set_status(string)` → draft, active, scheduled, paused, expired, archived
- `get_priority()` / `set_priority(int)` → 1-5 (higher = higher priority)
- `can_transition_to(string $status): bool`

**Discount:**
- `get_discount_type()` / `set_discount_type(string)` → percentage, fixed, tiered, bogo, spend_threshold
- `get_discount_value()` / `set_discount_value(float)`
- `get_discount_rules()` / `set_discount_rules(array)`

**Products:**
- `get_product_selection_type()` / `set_product_selection_type(string)` → all_products, specific_products, random_products, smart_selection
- `get_product_ids()` / `set_product_ids(array)`
- `get_category_ids()` / `set_category_ids(array)`
- `get_tag_ids()` / `set_tag_ids(array)`

**Scheduling:**
- `get_starts_at()` / `set_starts_at()` → DateTime (UTC)
- `get_ends_at()` / `set_ends_at()` → DateTime (UTC)
- `get_timezone()` / `set_timezone(string)` → For display

**Versioning & Tracking:**
- `get_version()` / `increment_version()` → Optimistic locking
- `get_created_by()` / `set_created_by(int)`
- `get_updated_by()` / `set_updated_by(int)`
- `get_created_at()` / `get_updated_at()` → DateTime (UTC)
- `get_deleted_at()` / `set_deleted_at()` → For soft delete

**Utility Methods:**
- `fill(array)` → Bulk load from array
- `to_array(): array` → Convert to array
- `validate(): array` → Get validation errors
- `is_valid(): bool` → Check if valid
- `get_performance_metrics(): array` → From metadata

---

### SCD_Campaign_Manager (Business Logic)

**Lifecycle Operations:**
- `create(array $data): SCD_Campaign|WP_Error` → Create new campaign
- `update(int $id, array $data): SCD_Campaign|WP_Error` → Update existing
- `delete(int $id): WP_Error|true` → Soft delete (trash)
- `activate(int $id): WP_Error|true` → Activate campaign
- `pause(int $id): WP_Error|true` → Pause campaign
- `duplicate(int $id): SCD_Campaign|WP_Error` → Clone campaign

**Retrieval & Querying:**
- `get_campaigns(array $args): array` → Get with filters
- `get_campaign(int $id): SCD_Campaign|null` → Get single
- `count_campaigns(array $criteria): int` → Count matching
- `get_status_counts(): array` → Count by status

**Advanced Operations:**
- `check_conflicts(SCD_Campaign $campaign): array` → Find overlapping
- `compile_campaign(int $id): WP_Error|true` → Compile product selection
- `apply_product_conditions(int $id): array` → Update by conditions

**Dependency Injection:**
- Constructor accepts: `$repository`, `$logger`, `$cache`, `$container`

**Hooks Fired:**
- `scd_campaign_created` → After creation
- `scd_campaign_activated` → On activation (triggers compilation)
- `scd_campaign_updated` → After update
- `scd_campaign_deleted` → After deletion

---

### SCD_Campaign_Repository (Data Access)

**Retrieval Methods:**
- `find(int $id, bool $include_trashed): ?SCD_Campaign`
- `find_by_uuid(string $uuid): ?SCD_Campaign`
- `find_by_slug(string $slug): ?SCD_Campaign`
- `find_for_user(int $id, int $user_id): ?SCD_Campaign` → With ownership check
- `find_by(array $criteria, array $options): array` → Batch query
- `find_all(array $args): array` → Flexible find with search
- `find_by_status(string $status, array $options): array`

**Status-Specific:**
- `get_active(): array` → Active campaigns within schedule
- `get_scheduled(): array` → Future campaigns
- `get_paused(): array` → Paused campaigns
- `get_expired(): array` → Expired campaigns

**Mutation Methods:**
- `save(SCD_Campaign $campaign): bool` → Create/Update with optimistic locking
- `delete(int $id): bool` → Soft delete
- `force_delete(int $id): bool` → Hard delete
- `restore(int $id): bool` → Restore from trash
- `update_campaign_status(int $id, string $status): bool`

**Counting & Aggregation:**
- `count(array $criteria): int`
- `count_trashed(): int`
- `get_status_counts(): array`

**Search & Advanced:**
- `search_campaigns(array $args): array`
- `find_by_metadata(string $key, string $value): array`
- `get_conflicting_campaigns(SCD_Campaign $campaign): array`
- `get_campaigns_by_product(int $product_id): array`
- `slug_exists(string $slug, ?int $exclude_id): bool`
- `get_unique_slug(string $slug, ?int $exclude_id): string`

**Trash Management:**
- `find_trashed(array $options): array`
- `count_trashed(): int`

**Cache Keys:**
- Single: `campaign_{id}`, `campaign_uuid_{uuid}`, `campaign_slug_{slug}`
- Collections: `active_campaigns`, `scheduled_campaigns`, `paused_campaigns`
- Products: `campaigns_by_product_{product_id}`

---

### SCD_Campaigns_List_Table (Admin Display)

**Column Renderers:**
- `column_name($item)` → Name + actions + recurring badge
- `column_status($item)` → Status badge
- `column_discount($item)` → Discount display
- `column_products($item)` → Product selection summary
- `column_schedule($item)` → Start/end dates
- `column_priority($item)` → Priority badge
- `column_health($item)` → Health score
- `column_performance($item)` → Performance metrics
- `column_created($item)` → Creation info
- `column_cb($item)` → Checkbox

**Bulk Actions:**
- activate, deactivate, delete, restore, delete_permanently, stop_recurring

**Sorting Support:**
- name, status, created (by created_at), schedule (by start_date), priority

**Filtering:**
- Status dropdown: All, Active, Scheduled, Paused, Expired, Draft, Trash
- Search: Campaign name and description
- Views: Status count links

**Capabilities Integration:**
- `scd_create_campaigns` → Create/duplicate
- `scd_edit_campaigns` → Edit/quick edit
- `scd_activate_campaigns` → Activate/deactivate
- `scd_delete_campaigns` → Delete/trash
- Ownership checks for user-created campaigns

---

## Data Structures

### Campaign Array Format (for creation)

```php
[
    'campaign_name'          => 'Summer Sale',
    'campaign_description'   => 'Optional description',
    'product_selection_type' => 'specific_products', // all_products, specific_products, random_products, smart_selection
    'selected_products'      => [1, 2, 3],
    'selected_categories'    => [10, 11],
    'selected_tags'          => [5, 6],
    'discount_type'          => 'percentage', // percentage, fixed, tiered, bogo, spend_threshold
    'discount_value'         => 20.0,
    'discount_rules'         => [...], // Complex for tiered/BOGO/threshold
    'priority'               => 3, // 1-5
    'start_date'             => '2025-06-01',
    'start_time'             => '00:00:00',
    'end_date'               => '2025-06-30',
    'end_time'               => '23:59:59',
    'timezone'               => 'America/New_York', // Optional
    'settings'               => [...], // Optional custom settings
]
```

### Query Options Format

```php
[
    'limit'             => 20,
    'offset'            => 0,
    'order_by'          => 'created_at',
    'order_direction'   => 'DESC',
    'search'            => 'search term',  // Optional
    'status'            => 'active',        // Optional
]
```

### Campaign Status Values

```
'draft'     - Not yet activated
'active'    - Currently running
'scheduled' - Will run in future
'paused'    - Temporarily inactive
'expired'   - Past end date
'archived'  - For historical tracking
```

### Discount Type Values

```
'percentage'      - X% off
'fixed'          - Fixed amount off
'tiered'         - Volume discounts (multiple tiers)
'bogo'           - Buy X Get Y
'spend_threshold' - Discount based on cart total
```

### Product Selection Type Values

```
'all_products'      - Apply to all products
'specific_products' - Select individual products
'random_products'   - Random selection (requires compilation)
'smart_selection'   - AI-based selection (requires compilation)
```

---

## Common Operations

### Create Campaign
```php
$manager = $container->get('campaign_manager');
$result = $manager->create([
    'campaign_name' => 'Summer Sale',
    'discount_type' => 'percentage',
    'discount_value' => 20,
    // ... more fields
]);

if (is_wp_error($result)) {
    echo $result->get_error_message();
} else {
    $campaign_id = $result->get_id();
}
```

### Get Campaigns
```php
$repository = $manager->get_repository();

// Single campaign
$campaign = $repository->find(123);

// Active campaigns
$campaigns = $repository->get_active();

// With criteria
$campaigns = $repository->find_by_status('draft', [
    'limit' => 10,
    'offset' => 0,
]);

// Search
$campaigns = $manager->get_campaigns(['search' => 'sale']);
```

### Update Campaign
```php
$result = $manager->update($campaign_id, [
    'discount_value' => 25,
    'priority' => 4,
]);
```

### Activate/Pause
```php
$manager->activate($campaign_id);   // Set to active
$manager->pause($campaign_id);      // Set to paused
```

### Delete
```php
// Soft delete (trash)
$manager->delete($campaign_id);

// Hard delete
$repository->force_delete($campaign_id);

// Restore from trash
$repository->restore($campaign_id);
```

### Format for Display
```php
$formatter = new SCD_Campaign_Formatter();
$formatted = $formatter->format($campaign, 'list');
// Returns: ['name', 'status', 'discount', 'schedule', 'priority']
```

### Serialize for API
```php
$serializer = new SCD_Campaign_Serializer($logger);
$json = $serializer->serialize($campaign, [
    'include_meta' => true,
    'include_stats' => true,
    'include_split_datetime' => true,
]);
```

---

## Database Schema Essentials

### wp_scd_campaigns Table

**Primary Columns:**
- `id` INT PRIMARY KEY AUTO_INCREMENT
- `uuid` VARCHAR(36) UNIQUE
- `name` VARCHAR(255) NOT NULL
- `slug` VARCHAR(255) UNIQUE
- `status` VARCHAR(50) DEFAULT 'draft'
- `priority` INT DEFAULT 3
- `version` INT DEFAULT 1
- `deleted_at` DATETIME NULL (soft delete)

**Timestamps (all UTC):**
- `created_at` DATETIME
- `updated_at` DATETIME
- `starts_at` DATETIME NULL
- `ends_at` DATETIME NULL

**JSON Fields:**
- `settings` JSON
- `metadata` JSON
- `product_ids` JSON
- `category_ids` JSON
- `tag_ids` JSON
- `discount_rules` JSON

**Discount:**
- `discount_type` VARCHAR(50)
- `discount_value` DECIMAL(10,2)

**User Tracking:**
- `created_by` INT
- `updated_by` INT NULL

**Indexes:**
- PRIMARY KEY (id)
- UNIQUE (uuid)
- UNIQUE (slug)
- INDEX (status)
- INDEX (created_by)
- INDEX (created_at)
- INDEX (deleted_at)

---

## Caching Summary

| Cache Key Pattern | TTL | Invalidation |
|------------------|-----|--------------|
| `campaign_{id}` | 3600s | On save/delete |
| `campaign_uuid_{uuid}` | 3600s | On save/delete |
| `campaign_slug_{slug}` | 3600s | On save/delete |
| `active_campaigns` | 1800s | On save/delete |
| `scheduled_campaigns` | 1800s | On save/delete |
| `paused_campaigns` | 1800s | On save/delete |
| `campaigns_by_product_{id}` | 1800s | On save/delete |

---

## Security Checklist

- [x] All database queries use prepared statements
- [x] Input sanitization (sanitize_text_field, intval, etc.)
- [x] Output escaping (esc_html, esc_attr, wp_kses_post)
- [x] Nonce verification for form actions
- [x] Capability checks for all operations
- [x] Ownership verification for user-created items
- [x] Soft delete with audit trail (deleted_at timestamp)
- [x] Optimistic locking (version field) for concurrent edits

---

## Performance Tips

1. **Caching**: Repository caches single campaigns for 1 hour, collections for 30 minutes
2. **Batch Queries**: Use `find_by()` with criteria for efficient bulk retrieval
3. **Product-Specific**: Cache `campaigns_by_product_{id}` to avoid repeated filtering
4. **Indexed Queries**: Status, created_by, created_at, deleted_at all indexed
5. **Prepared Statements**: All queries parameterized, preventing SQL injection

---

## Extension Points (Hooks)

```php
// Fired after campaign creation
do_action('scd_campaign_created', SCD_Campaign $campaign);

// Fired on activation (use to trigger compilation)
do_action('scd_campaign_activated', SCD_Campaign $campaign);

// Fired after campaign update
do_action('scd_campaign_updated', SCD_Campaign $campaign);

// Fired after campaign deletion
do_action('scd_campaign_deleted', int $campaign_id);
```

---

## Debugging Tips

### Check Campaign Status
```php
$campaign = $repository->find(123);
echo $campaign->get_status();  // draft, active, scheduled, paused, expired, archived
echo $campaign->get_version(); // Current version (incremented on save)
```

### Verify Status Transition
```php
if ($campaign->can_transition_to('active')) {
    // Status transition allowed
}
```

### Clear Caches
```php
$cache = $container->get('cache_manager');
$cache->delete('campaign_123');
$cache->delete('active_campaigns');
wp_cache_flush(); // Full flush if needed
```

### Check for Conflicts
```php
$conflicts = $repository->get_conflicting_campaigns($campaign);
// Returns array of campaigns with overlapping schedules
```

### Validate Campaign
```php
$errors = $campaign->validate();
if (!empty($errors)) {
    // Handle validation errors
}
```

---

## Related Documentation

See also:
- `CAMPAIGN-ANALYSIS.md` - Comprehensive system analysis
- `CAMPAIGN-ARCHITECTURE.md` - Visual architecture diagrams
- Plugin source files in `/includes/core/campaigns/`
- Database migrations in `/includes/database/migrations/`

