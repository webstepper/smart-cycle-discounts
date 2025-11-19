# Campaign Management System - Architecture Diagram

## Data Flow Architecture

```
┌─────────────────────────────────────────────────────────────────────┐
│                       ADMIN INTERFACE LAYER                          │
├─────────────────────────────────────────────────────────────────────┤
│                                                                       │
│  Campaign Pages              List Table         Campaign Wizard      │
│  ├─ Create                 ├─ Display           ├─ Step 1: Basic    │
│  ├─ Edit                   ├─ Bulk Actions      ├─ Step 2: Products │
│  ├─ Delete                 ├─ Filter/Search     ├─ Step 3: Discounts│
│  └─ Activate/Pause         ├─ Columns           ├─ Step 4: Schedule │
│                            │  ├─ Name           └─ Step 5: Review   │
│  ↓                         │  ├─ Discount       │                   │
│  Campaign Formatters       │  ├─ Products       └─ Form Data → AJAX │
│  ├─ format()               │  ├─ Status         │                   │
│  ├─ format_discount()      │  ├─ Schedule       │                   │
│  ├─ format_status()        │  ├─ Priority       │                   │
│  ├─ format_schedule()      │  ├─ Health         │                   │
│  └─ format_priority()      │  ├─ Performance    │                   │
│                            │  └─ Created        │                   │
│                            │                    │                   │
│                            └─ Uses SCD_Badge    └─ Validation       │
│                               Helper for        │ ├─ Check name     │
│                               visual badges     │ ├─ Check products │
│                                                 │ ├─ Check discounts│
│                                                 │ ├─ Check schedule │
│                                                 │ └─ Validate form  │
│                                                 │                   │
└─────────────────────────────────────────────────────────────────────┘
                                    ↓
┌─────────────────────────────────────────────────────────────────────┐
│                    BUSINESS LOGIC LAYER                              │
├─────────────────────────────────────────────────────────────────────┤
│                                                                       │
│              SCD_Campaign_Manager                                    │
│              ├─ create()            - Create new campaign            │
│              ├─ update()            - Update existing                │
│              ├─ delete()            - Soft delete                    │
│              ├─ activate()          - Activate campaign              │
│              ├─ pause()             - Pause campaign                 │
│              ├─ duplicate()         - Clone campaign                 │
│              ├─ compile_campaign()  - Compile product selection      │
│              ├─ check_conflicts()   - Find overlapping campaigns     │
│              ├─ apply_product_conditions() - Update based on rules   │
│              └─ Hooks & Events      - Fire actions for extensions    │
│                  ├─ scd_campaign_created                            │
│                  ├─ scd_campaign_activated (triggers compilation)   │
│                  ├─ scd_campaign_updated                            │
│                  └─ scd_campaign_deleted                            │
│                                                                       │
│   Integration with:                                                  │
│   ├─ SCD_Campaign_Compiler_Service (for product selection)          │
│   ├─ SCD_Event_Scheduler (for start/end times)                      │
│   ├─ SCD_Validation (for data validation)                           │
│   ├─ SCD_Campaign_Health_Service (for health analysis)              │
│   └─ SCD_Logger (for logging operations)                            │
│                                                                       │
└─────────────────────────────────────────────────────────────────────┘
                                    ↓
┌─────────────────────────────────────────────────────────────────────┐
│                    DATA ABSTRACTION LAYER                            │
├─────────────────────────────────────────────────────────────────────┤
│                                                                       │
│              SCD_Campaign_Repository                                 │
│              ├─ Retrieval Methods                                    │
│              │  ├─ find(id) - Single campaign                       │
│              │  ├─ find_by_uuid/slug() - Unique lookups             │
│              │  ├─ find_by() - Batch queries with criteria          │
│              │  ├─ get_active() - Active campaigns                  │
│              │  ├─ get_scheduled() - Future campaigns               │
│              │  ├─ find_by_status() - Status filtered               │
│              │  ├─ find_by_metadata() - JSON queries                │
│              │  └─ search_campaigns() - Full-text search            │
│              │                                                       │
│              ├─ Mutation Methods                                     │
│              │  ├─ save() - Create/Update (with optimistic locking) │
│              │  ├─ delete() - Soft delete                           │
│              │  ├─ force_delete() - Hard delete                     │
│              │  ├─ restore() - Undelete                             │
│              │  └─ update_campaign_status() - Status change         │
│              │                                                       │
│              ├─ Counting & Aggregation                               │
│              │  ├─ count() - Count campaigns                        │
│              │  ├─ count_trashed() - Trash count                    │
│              │  └─ get_status_counts() - Per-status counts          │
│              │                                                       │
│              ├─ Cache Management                                     │
│              │  ├─ Remember()         - Retrieve with cache         │
│              │  ├─ clear_campaign_cache() - Clear related caches    │
│              │  └─ Invalidation on save/delete                      │
│              │                                                       │
│              ├─ Data Transformation                                  │
│              │  ├─ hydrate() - DB row → Campaign object             │
│              │  └─ dehydrate() - Campaign object → DB format        │
│              │                                                       │
│              └─ Dependency Injection                                 │
│                 ├─ SCD_Database_Manager (prepared statements)       │
│                 └─ SCD_Cache_Manager (caching layer)                │
│                                                                       │
└─────────────────────────────────────────────────────────────────────┘
                                    ↓
┌─────────────────────────────────────────────────────────────────────┐
│                    DATA MODEL LAYER                                  │
├─────────────────────────────────────────────────────────────────────┤
│                                                                       │
│                    SCD_Campaign (Entity)                             │
│                                                                       │
│  Identification:               Operations:                          │
│  ├─ id (int|null)              ├─ priority (1-5)                   │
│  ├─ uuid (string)              ├─ status (draft|active|...)        │
│  ├─ name (string)              ├─ version (int)                    │
│  ├─ slug (string)              ├─ created_by (int)                 │
│  ├─ description (string|null)  └─ updated_by (int|null)            │
│  │                                                                   │
│  Product Selection:            Timestamps (all UTC):                │
│  ├─ product_selection_type     ├─ created_at (DateTime)            │
│  │  (all|specific|random|smart)├─ updated_at (DateTime)            │
│  ├─ product_ids (array)        ├─ starts_at (DateTime|null)        │
│  ├─ category_ids (array)       ├─ ends_at (DateTime|null)          │
│  └─ tag_ids (array)            └─ deleted_at (DateTime|null)       │
│                                │                                    │
│  Discount Configuration:       Display & Storage:                   │
│  ├─ discount_type              ├─ timezone (string)                │
│  │  (percentage|fixed|...)     ├─ settings (array JSON)            │
│  ├─ discount_value (float)     ├─ metadata (array JSON)            │
│  └─ discount_rules (array)     └─ template_id (int|null)           │
│                                                                       │
│  Methods:                                                            │
│  ├─ get_*/set_* (accessors)                                        │
│  ├─ fill(array) - Bulk data load                                   │
│  ├─ to_array() - Convert to array                                  │
│  ├─ validate() - Validation                                        │
│  ├─ can_transition_to() - Status rules                             │
│  ├─ get_performance_metrics() - Stats (from metadata)              │
│  └─ increment_version() - Optimistic locking                       │
│                                                                       │
└─────────────────────────────────────────────────────────────────────┘
                                    ↓
┌─────────────────────────────────────────────────────────────────────┐
│                    DATABASE LAYER                                    │
├─────────────────────────────────────────────────────────────────────┤
│                                                                       │
│  wp_scd_campaigns Table:                                             │
│  ┌──────────────────────────────────────────────────────────────┐  │
│  │ id | uuid | name | slug | description | status | priority   │  │
│  ├──────────────────────────────────────────────────────────────┤  │
│  │ settings | metadata | template_id | created_by | updated_by │  │
│  ├──────────────────────────────────────────────────────────────┤  │
│  │ starts_at | ends_at | timezone | product_selection_type    │  │
│  ├──────────────────────────────────────────────────────────────┤  │
│  │ product_ids | category_ids | tag_ids (JSON columns)        │  │
│  ├──────────────────────────────────────────────────────────────┤  │
│  │ discount_type | discount_value | discount_rules            │  │
│  ├──────────────────────────────────────────────────────────────┤  │
│  │ created_at | updated_at | version | deleted_at             │  │
│  └──────────────────────────────────────────────────────────────┘  │
│                                                                       │
│  Indexes:                                                            │
│  ├─ PRIMARY KEY (id)                                                │
│  ├─ UNIQUE (uuid)                                                   │
│  ├─ UNIQUE (slug)                                                   │
│  ├─ INDEX (status)                                                  │
│  ├─ INDEX (created_by)                                              │
│  ├─ INDEX (created_at)                                              │
│  └─ INDEX (deleted_at)                                              │
│                                                                       │
│  JSON Columns:                                                       │
│  ├─ settings - Flexible key-value pairs                             │
│  ├─ metadata - Extended data for analytics/tracking                 │
│  ├─ product_ids - Array of selected product IDs                     │
│  ├─ category_ids - Array of category IDs                            │
│  ├─ tag_ids - Array of tag IDs                                      │
│  └─ discount_rules - Complex discount configuration                 │
│                                                                       │
└─────────────────────────────────────────────────────────────────────┘
                                    ↓
┌─────────────────────────────────────────────────────────────────────┐
│                    CACHING LAYER                                     │
├─────────────────────────────────────────────────────────────────────┤
│                                                                       │
│  Cache Manager (SCD_Cache_Manager):                                 │
│  ├─ Single Campaign Caches (1 hour TTL)                             │
│  │  ├─ campaign_{id}                                               │
│  │  ├─ campaign_uuid_{uuid}                                        │
│  │  └─ campaign_slug_{slug}                                        │
│  │                                                                  │
│  ├─ Collection Caches (30 min TTL)                                 │
│  │  ├─ active_campaigns                                            │
│  │  ├─ scheduled_campaigns                                         │
│  │  └─ paused_campaigns                                            │
│  │                                                                  │
│  ├─ Product-Specific Caches (30 min TTL)                           │
│  │  └─ campaigns_by_product_{product_id}                           │
│  │                                                                  │
│  └─ Invalidation: Automatic on save/delete/restore                 │
│                                                                       │
└─────────────────────────────────────────────────────────────────────┘
```

## Object Lifecycle

```
┌─────────────────────────────────────────────────────────────────────┐
│                   CAMPAIGN LIFECYCLE                                 │
├─────────────────────────────────────────────────────────────────────┤
│                                                                       │
│                                                                       │
│   1. CREATION                                                        │
│   ┌─────────────────────────────────────────────────────┐           │
│   │ $manager->create($data)                             │           │
│   │ ├─ Validate data via SCD_Validation                │           │
│   │ ├─ Prepare data (dates, slugs, etc.)               │           │
│   │ ├─ Create SCD_Campaign object                      │           │
│   │ ├─ Save via Repository (transaction)               │           │
│   │ ├─ Fire 'scd_campaign_created' hook                │           │
│   │ ├─ Schedule campaign events (start/end)            │           │
│   │ └─ Return SCD_Campaign or WP_Error                 │           │
│   │                                                     │           │
│   │ Initial Status: DRAFT                              │           │
│   │ UUID Generated: Yes                                │           │
│   │ Version: 1                                         │           │
│   └─────────────────────────────────────────────────────┘           │
│                        ↓                                             │
│                                                                       │
│   2. CONFIGURATION                                                   │
│   ┌─────────────────────────────────────────────────────┐           │
│   │ $manager->update($id, $data)                        │           │
│   │ ├─ Load existing campaign                          │           │
│   │ ├─ Update with new data                            │           │
│   │ ├─ Save via Repository (optimistic locking)        │           │
│   │ │  └─ Version checked: prevent concurrent edits    │           │
│   │ ├─ If product selection changed: recompile         │           │
│   │ ├─ Fire 'scd_campaign_updated' hook                │           │
│   │ └─ Return SCD_Campaign or WP_Error                 │           │
│   │                                                     │           │
│   │ Status: DRAFT (editing) or any status               │           │
│   │ Version: Incremented on each save                  │           │
│   └─────────────────────────────────────────────────────┘           │
│                        ↓                                             │
│                                                                       │
│   3. ACTIVATION                                                      │
│   ┌─────────────────────────────────────────────────────┐           │
│   │ $manager->activate($id)                             │           │
│   │ ├─ Load campaign                                   │           │
│   │ ├─ Set status → ACTIVE                            │           │
│   │ ├─ Trigger 'scd_campaign_activated' hook           │           │
│   │ │  └─ For random/smart selection: compile now      │           │
│   │ ├─ Schedule end event if end_date set              │           │
│   │ ├─ Save via Repository                            │           │
│   │ └─ Clear caches                                   │           │
│   │                                                     │           │
│   │ Alternative Path: schedule() for future start      │           │
│   │ ├─ Set status → SCHEDULED                         │           │
│   │ ├─ Schedule start/end events                       │           │
│   └─────────────────────────────────────────────────────┘           │
│                        ↓                                             │
│                                                                       │
│   4. OPERATION                                                       │
│   ┌─────────────────────────────────────────────────────┐           │
│   │ Campaign applies discounts to products             │           │
│   │ ├─ Status: ACTIVE                                  │           │
│   │ ├─ Discounts applied on checkout                  │           │
│   │ ├─ Analytics tracked (usage, revenue, etc.)        │           │
│   │ └─ Continues until:                               │           │
│   │    ├─ End date reached (auto expires)              │           │
│   │    ├─ Manually paused                              │           │
│   │    └─ Manually deactivated                         │           │
│   │                                                     │           │
│   │ Alternative: PAUSE                                 │           │
│   │ ├─ $manager->pause($id)                           │           │
│   │ ├─ Set status → PAUSED                            │           │
│   │ ├─ Discounts no longer applied                    │           │
│   │ └─ Can resume later                               │           │
│   └─────────────────────────────────────────────────────┘           │
│                        ↓                                             │
│                                                                       │
│   5. COMPLETION                                                      │
│   ┌─────────────────────────────────────────────────────┐           │
│   │ Campaign ends (auto or manual)                     │           │
│   │ ├─ End date reached → Status: EXPIRED             │           │
│   │ ├─ Manually deleted → Status: deleted_at set      │           │
│   │ └─ Soft delete (trash)                            │           │
│   │    └─ Campaign still in DB, hidden from lists      │           │
│   │                                                     │           │
│   │ Can be ARCHIVED for historical tracking           │           │
│   │ ├─ $manager->archive($id)                         │           │
│   │ ├─ Status: ARCHIVED                               │           │
│   │ └─ Kept for reporting/audit                       │           │
│   └─────────────────────────────────────────────────────┘           │
│                        ↓                                             │
│                                                                       │
│   6. RESTORATION                                                     │
│   ┌─────────────────────────────────────────────────────┐           │
│   │ $repository->restore($id) [from trash]             │           │
│   │ ├─ Clear deleted_at timestamp                      │           │
│   │ ├─ Restore to original status                      │           │
│   │ └─ Clear caches                                   │           │
│   │                                                     │           │
│   │ Or DUPLICATE campaign for reuse                    │           │
│   │ ├─ $manager->duplicate($id)                        │           │
│   │ ├─ Clone all settings/products/discounts           │           │
│   │ ├─ Add "-copy" to name                             │           │
│   │ ├─ Clear dates and reset to draft                 │           │
│   │ └─ Return new SCD_Campaign                         │           │
│   └─────────────────────────────────────────────────────┘           │
│                        ↓                                             │
│                                                                       │
│   7. DELETION                                                        │
│   ┌─────────────────────────────────────────────────────┐           │
│   │ $repository->force_delete($id)                      │           │
│   │ ├─ Permanent removal from database                 │           │
│   │ ├─ Cannot be restored                              │           │
│   │ └─ Clear all related caches                        │           │
│   │                                                     │           │
│   │ Or EMPTY TRASH                                     │           │
│   │ ├─ Force delete all deleted_at IS NOT NULL         │           │
│   │ └─ Permanent cleanup                               │           │
│   └─────────────────────────────────────────────────────┘           │
│                                                                       │
└─────────────────────────────────────────────────────────────────────┘
```

## Status Transition Matrix

```
┌─────────────┬───────┬────────┬──────────┬────────┬──────────┬──────────┐
│ From Status │ Draft │ Active │ Paused   │Schedule│ Expired  │ Archived │
├─────────────┼───────┼────────┼──────────┼────────┼──────────┼──────────┤
│ Draft       │   -   │   ✓    │    ✗     │   ✓    │    ✗     │    ✓     │
├─────────────┼───────┼────────┼──────────┼────────┼──────────┼──────────┤
│ Active      │   ✗   │   -    │    ✓     │   ✗    │    ✓     │    ✓     │
├─────────────┼───────┼────────┼──────────┼────────┼──────────┼──────────┤
│ Paused      │   ✓   │   ✓    │    -     │   ✓    │    ✓     │    ✓     │
├─────────────┼───────┼────────┼──────────┼────────┼──────────┼──────────┤
│ Scheduled   │   ✓   │   ✓    │    ✓     │   -    │    ✗     │    ✓     │
├─────────────┼───────┼────────┼──────────┼────────┼──────────┼──────────┤
│ Expired     │   ✓   │   ✗    │    ✗     │   ✗    │    -     │    ✓     │
├─────────────┼───────┼────────┼──────────┼────────┼──────────┼──────────┤
│ Archived    │   ✓   │   ✗    │    ✗     │   ✗    │    ✗     │    -     │
└─────────────┴───────┴────────┴──────────┴────────┴──────────┴──────────┘

Legend: ✓ = Allowed  ✗ = Not Allowed  - = Same Status

Key Rules:
- Draft is flexible: can go to most statuses
- Active can only transition to Paused, Expired, or Archived
- Expired and Archived are end states (limited options to reset)
- Cannot go backwards except to reset to Draft
- Soft delete (trash) is separate: deleted_at timestamp
```

## Caching Strategy

```
┌─────────────────────────────────────────────────────────┐
│  REQUEST                                                │
├─────────────────────────────────────────────────────────┤
│  GET /admin/campaigns/1                                 │
└─────────────────────────────────────────────────────────┘
         ↓
┌─────────────────────────────────────────────────────────┐
│  CHECK L1 CACHE: campaign_1                             │
├─────────────────────────────────────────────────────────┤
│  ├─ Hit? → Return cached SCD_Campaign object           │
│  └─ Miss? → Continue to L2                             │
└─────────────────────────────────────────────────────────┘
         ↓
┌─────────────────────────────────────────────────────────┐
│  CHECK L2 CACHE: active_campaigns                       │
├─────────────────────────────────────────────────────────┤
│  ├─ Hit? → Return cached list                          │
│  └─ Miss? → Continue to L3                             │
└─────────────────────────────────────────────────────────┘
         ↓
┌─────────────────────────────────────────────────────────┐
│  DATABASE QUERY                                         │
├─────────────────────────────────────────────────────────┤
│  SELECT * FROM wp_scd_campaigns WHERE id = 1            │
│        AND deleted_at IS NULL                           │
└─────────────────────────────────────────────────────────┘
         ↓
┌─────────────────────────────────────────────────────────┐
│  HYDRATE: DB Row → SCD_Campaign Object                  │
├─────────────────────────────────────────────────────────┤
│  ├─ Decode JSON fields                                 │
│  ├─ Create DateTime objects                            │
│  └─ Fill properties                                    │
└─────────────────────────────────────────────────────────┘
         ↓
┌─────────────────────────────────────────────────────────┐
│  STORE IN CACHE (TTL: 3600s = 1 hour)                   │
├─────────────────────────────────────────────────────────┤
│  cache['campaign_1'] = $campaign_object                │
└─────────────────────────────────────────────────────────┘
         ↓
┌─────────────────────────────────────────────────────────┐
│  RETURN TO CALLER                                       │
├─────────────────────────────────────────────────────────┤
│  Display in list table, render in admin                │
└─────────────────────────────────────────────────────────┘

INVALIDATION (On Save):
  ├─ Clear: campaign_1
  ├─ Clear: campaign_uuid_{uuid}
  ├─ Clear: campaign_slug_{slug}
  ├─ Clear: active_campaigns
  ├─ Clear: scheduled_campaigns
  ├─ Clear: paused_campaigns
  └─ Clear: campaigns_by_product_* (for affected products)
```

## Optimistic Locking (Concurrency Control)

```
┌──────────────────────────────────────────┐
│  INITIAL STATE                           │
├──────────────────────────────────────────┤
│  Database: id=1, version=1               │
│  Memory:   User1 loads campaign          │
│            version=1 stored locally      │
└──────────────────────────────────────────┘

SCENARIO: Two Users Edit Concurrently
         ↙                    ↘
┌────────────────────┐  ┌────────────────────┐
│  USER 1            │  │  USER 2            │
├────────────────────┤  ├────────────────────┤
│ 1. Load campaign   │  │ 1. Load campaign   │
│    version=1       │  │    version=1       │
│                    │  │                    │
│ 2. Edit discount   │  │ 2. Edit name       │
│    value to 25     │  │    to "Summer Sale"│
│                    │  │                    │
│ 3. Save:           │  │ 3. Save:           │
│    UPDATE campaigns│  │    UPDATE campaigns│
│    SET            │  │    SET             │
│    discount_value  │  │    name='Summer...'│
│    WHERE id=1      │  │    WHERE id=1      │
│    AND version=1   │  │    AND version=1   │
│                    │  │    ✓ SUCCESS       │
│    ✓ SUCCESS       │  │    version→2       │
│    version→2       │  │                    │
│    campaign saved  │  │ DB now has:        │
│                    │  │ version=2          │
│    DB now has:     │  │ name='Summer Sale' │
│    version=2       │  │ discount=20 (old)  │
│    discount=25     │  │                    │
│    name=null (old) │  │                    │
│                    │  │                    │
└────────────────────┘  └────────────────────┘
         ↓                       ↓
  RESULT: Race condition handled safely!
  
  If User 1 tried to save again:
  - Would query: WHERE id=1 AND version=1
  - Would find NO ROWS (version is now 2)
  - Would throw: ConcurrentModificationException
  - User would see: "Campaign was updated by another user"
  - User must reload to get latest version
```

## Security Boundaries

```
┌──────────────────────────────────────────────────────────┐
│  ADMIN REQUEST                                           │
├──────────────────────────────────────────────────────────┤
│  $_GET['id'] = 123 (UNTRUSTED)                           │
└──────────────────────────────────────────────────────────┘
         ↓
┌──────────────────────────────────────────────────────────┐
│  SANITIZATION                                            │
├──────────────────────────────────────────────────────────┤
│  $id = intval($_GET['id']) = 123                         │
│  (ensures integer)                                       │
└──────────────────────────────────────────────────────────┘
         ↓
┌──────────────────────────────────────────────────────────┐
│  SECURITY CHECKS                                         │
├──────────────────────────────────────────────────────────┤
│  ✓ Check nonce: wp_verify_nonce()                       │
│  ✓ Check capability: current_user_can('edit_campaigns') │
│  ✓ Check ownership: $campaign->get_created_by() == uid  │
│                     OR current_user_can('manage_options')│
└──────────────────────────────────────────────────────────┘
         ↓
┌──────────────────────────────────────────────────────────┐
│  PREPARED STATEMENT                                      │
├──────────────────────────────────────────────────────────┤
│  $wpdb->prepare(                                         │
│    "SELECT * FROM campaigns WHERE id = %d AND ...",     │
│    $id  // Parameterized, not concatenated              │
│  )                                                       │
└──────────────────────────────────────────────────────────┘
         ↓
┌──────────────────────────────────────────────────────────┐
│  OUTPUT ESCAPING                                         │
├──────────────────────────────────────────────────────────┤
│  echo esc_html($campaign->get_name())                    │
│  ✓ HTML tags are escaped                                │
│  ✓ XSS protection                                       │
└──────────────────────────────────────────────────────────┘

Defense Layers:
1. Input: Sanitize (sanitize_text_field, intval, etc.)
2. Validation: Validate data structure (SCD_Validation)
3. Authorization: Check permissions (capability_manager)
4. Query: Prepared statements (wpdb->prepare)
5. Output: Escape display (esc_html, esc_attr, etc.)
```

