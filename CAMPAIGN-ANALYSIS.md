# Campaign Management System Analysis

## Overview
The Smart Cycle Discounts plugin has a well-structured, object-oriented campaign management system following the Model-Repository-Controller pattern. Campaigns are the core entity representing discount campaigns with comprehensive data models, database abstraction, and admin UI.

---

## 1. CAMPAIGN DATA MODEL (SCD_Campaign)

### Location
`/includes/core/campaigns/class-campaign.php`

### Core Properties

#### Identification
- **id**: `?int` - Primary database key (auto-increment)
- **uuid**: `string` - Universally unique identifier (generated on creation)
- **name**: `string` - Campaign name (required)
- **slug**: `string` - URL-friendly slug (auto-generated from name)

#### Description & Metadata
- **description**: `?string` - Optional campaign description
- **settings**: `array` - Flexible key-value settings storage
- **metadata**: `array` - Extended metadata for additional data
- **template_id**: `?int` - Reference to template if campaign created from template

#### Status & Versioning
- **status**: `string` (default: 'draft')
  - Possible values: `draft`, `active`, `scheduled`, `paused`, `expired`, `archived`
  - State transitions controlled by `can_transition_to()` method
- **version**: `int` (default: 1) - Optimistic locking counter (prevents concurrent modification conflicts)
- **deleted_at**: `?DateTime` - Soft delete timestamp (null = not deleted)

#### Campaign Operations
- **priority**: `int` (1-5, default: 3) - Determines discount application order
  - Higher number = higher priority (5 = highest)
  - Used when multiple campaigns apply to same product
- **created_by**: `int` - User ID of campaign creator
- **updated_by**: `?int` - User ID of last updater
- **created_at**: `DateTime` - Created timestamp (always UTC)
- **updated_at**: `DateTime` - Last updated timestamp (always UTC)

#### Product Selection
- **product_selection_type**: `string` (default: 'all_products')
  - Types: `all_products`, `specific_products`, `random_products`, `smart_selection`
- **product_ids**: `array` - IDs of specific products (when applicable)
- **category_ids**: `array` - IDs of product categories
- **tag_ids**: `array` - IDs of product tags

#### Discount Configuration
- **discount_type**: `string` (default: 'percentage')
  - Types: `percentage`, `fixed`, `tiered`, `bogo`, `spend_threshold`
- **discount_value**: `float` - Primary discount value
- **discount_rules**: `array` - Complex discount configuration (tiers, BOGO rules, thresholds, etc.)

#### Scheduling
- **starts_at**: `?DateTime` - Campaign start time (UTC)
- **ends_at**: `?DateTime` - Campaign end time (UTC)
- **timezone**: `string` - Campaign timezone (for display/UI)

### Data Type Handling

#### DateTime Management
```php
// All dates stored in UTC in database
// DateTime objects set to UTC timezone
$this->starts_at = new DateTime($starts_at, new DateTimeZone('UTC'));

// Campaign has separate timezone property for display
$this->timezone = wp_timezone_string(); // For UI purposes

// Conversion methods handle string/DateTime inputs
$starts_at->setTimezone(new DateTimeZone('UTC')); // Always UTC for storage
```

#### JSON Fields
- Settings, metadata, product IDs, category IDs, tag IDs, discount rules are stored as JSON
- Automatically serialized/deserialized by hydrate/dehydrate methods

### Key Methods

#### Getters/Setters
All properties have corresponding `get_*()` and `set_*()` methods with proper type hints.

#### Fill Method
```php
public function fill(array $data): void
```
- Bulk-loads data from array (used during hydration from database)
- Automatically calls appropriate setter methods
- Handles slug specially (sets before other properties)

#### Array Conversion
```php
public function to_array(): array
```
- Converts campaign object to array for database storage
- Formats DateTime objects as 'Y-m-d H:i:s' strings
- Returns all properties including timestamps

#### Status Transitions
```php
public function can_transition_to(string $to_status): bool
```
- Validates status transitions based on rules:
  - draft → active, scheduled, archived
  - active → paused, expired, archived
  - paused → active, scheduled, draft, expired, archived
  - scheduled → active, paused, draft, archived
  - expired → draft, archived
  - archived → draft

#### Validation
```php
public function validate(): array    // Returns array of errors
public function is_valid(): bool     // Returns true if valid
```
- Validates campaign data structure
- Note: Campaign validation is mostly done by Campaign Manager before creation

#### Performance Metrics
```php
public function get_performance_metrics(): array
```
- Returns performance data from metadata (if stored)
- Performance tracking is managed by Analytics service, not Campaign entity

#### Version Management
```php
public function increment_version(): int
```
- Increments version for optimistic locking
- Prevents concurrent modification conflicts

---

## 2. CAMPAIGN REPOSITORY (SCD_Campaign_Repository)

### Location
`/includes/database/repositories/class-campaign-repository.php`

### Database Abstraction
- Extends `SCD_Base_Repository`
- Uses `SCD_Database_Manager` for queries
- Uses `SCD_Cache_Manager` for caching

### Key Retrieval Methods

#### Single Record Retrieval
```php
public function find($id, $include_trashed = false): ?SCD_Campaign
public function find_by_uuid(string $uuid): ?SCD_Campaign
public function find_by_slug(string $slug): ?SCD_Campaign
public function find_for_user($id, $user_id): ?SCD_Campaign
```

#### Batch Retrieval
```php
public function find_by(array $criteria, array $options): array
  // criteria: status, created_by, discount_type, id, uuid, slug, name
  // options: limit, offset, order_by, order_direction

public function find_all(array $args): array
  // High-level find with search support

public function find_by_status(string $status, array $options): array

public function search_campaigns(array $args): array
  // Searches name and description fields
```

#### Status-Specific Queries
```php
public function get_active(): array              // Active campaigns within schedule
public function get_scheduled(): array           // Future campaigns or scheduled status
public function get_paused(): array              // Paused campaigns
public function get_expired(): array             // Expired campaigns
public function get_campaigns_by_product($product_id): array
```

#### Advanced Queries
```php
public function find_by_metadata(string $meta_key, string $meta_value): array
public function get_conflicting_campaigns(SCD_Campaign $campaign): array
  // Finds campaigns with overlapping schedules
public function find_trashed(array $options): array
public function find_expired(string $cutoff_date): array
  // For auto-archiving old campaigns
```

### Counting
```php
public function count(array $criteria = array()): int
public function count_trashed(): int
public function get_status_counts(): array
  // Returns: ['active' => N, 'scheduled' => N, ...]
```

### Saving & Updating

#### Create/Update
```php
public function save(SCD_Campaign $campaign): bool
```
- Creates new campaign if no ID
- Updates existing if ID present
- Implements optimistic locking (version check)
- Wraps in transaction
- Clears cache on success
- Ownership/capability verification

#### Status Management
```php
public function update_campaign_status(int $id, string $status): bool
public function increment_campaign_usage(int $id): bool
```

### Deletion

#### Soft Delete
```php
public function delete(int $id): bool
```
- Sets `deleted_at` timestamp
- WordPress-style trash system
- Verifies ownership before deletion

#### Hard Delete
```php
public function force_delete(int $id): bool
```
- Permanent removal from database

#### Restore
```php
public function restore(int $id): bool
```
- Clears `deleted_at` timestamp

### Cache Management
- Cache key patterns:
  - Single: `campaign_{id}`, `campaign_uuid_{uuid}`, `campaign_slug_{slug}`
  - Collections: `active_campaigns`, `scheduled_campaigns`, `paused_campaigns`
  - Product-specific: `campaigns_by_product_{product_id}`
- Cache TTL: 1800 seconds (30 minutes) for active/scheduled/paused
- Clears: Automatically on save/delete operations

### Performance
- Uses caching to reduce database hits
- JSON_EXTRACT for metadata queries (MySQL 5.7+)
- Prepared statements for SQL security
- Transaction support for data integrity

### Data Hydration/Dehydration

#### Hydrate (Database → Object)
```php
private function hydrate(object $data): SCD_Campaign
```
- Converts database row to Campaign object
- Decodes JSON fields
- Handles timezone conversion
- Creates Campaign instance with all properties

#### Dehydrate (Object → Database)
```php
private function dehydrate(SCD_Campaign $campaign): array
```
- Converts Campaign object to database format
- Encodes JSON fields
- Removes unnecessary fields
- Formats data for database storage

---

## 3. CAMPAIGN MANAGER (SCD_Campaign_Manager)

### Location
`/includes/core/campaigns/class-campaign-manager.php`

### Responsibilities
- Business logic for campaign operations
- Validation and data preparation
- Campaign lifecycle management (create, update, delete, activate, pause)
- Integration with other services (scheduler, compiler, analytics)

### Priority System
- Higher numbers = higher priority (1-5 range)
- Default: 3
- When multiple campaigns apply to same product:
  1. Sorted by priority descending (5, 4, 3, 2, 1)
  2. First matching campaign wins
  3. Others blocked for that product

### Key Methods

#### Campaign Lifecycle

##### Create
```php
public function create(array $data): SCD_Campaign|WP_Error
```
- Validates data
- Prepares for creation
- Saves via repository
- Triggers activation hooks if needed
- Schedules campaign events

##### Update
```php
public function update(int $id, array $data): SCD_Campaign|WP_Error
```
- Finds existing campaign
- Updates with new data
- Saves via repository
- Recompiles if product selection changed

##### Delete
```php
public function delete(int $id): WP_Error|true
```
- Soft delete (moves to trash)
- Verifies ownership

##### Activate/Pause
```php
public function activate(int $id): WP_Error|true
public function pause(int $id): WP_Error|true
```
- Changes campaign status
- Triggers compilation for dynamic product selection
- Schedules automatic deactivation if end date set

##### Duplicate
```php
public function duplicate(int $id): SCD_Campaign|WP_Error
```
- Creates copy of campaign
- Adjusts name (adds "-copy")
- Clears dates and sets to draft

#### Data Management
```php
public function get_campaigns(array $args): array
public function get_campaign(int $id): SCD_Campaign|null
public function count_campaigns(array $criteria): int
public function get_status_counts(): array
```

#### Advanced Operations
```php
public function check_conflicts(SCD_Campaign $campaign): array
  // Finds conflicting campaigns

public function compile_campaign(int $id): WP_Error|true
  // Compiles product selection for random/smart selection types

public function apply_product_conditions(int $id): array
  // Updates product list based on conditions
```

#### Hooks & Events
Fires various actions for extensibility:
- `scd_campaign_created` - After campaign creation
- `scd_campaign_activated` - When campaign activated (triggers compilation)
- `scd_campaign_updated` - After campaign update
- `scd_campaign_deleted` - After campaign deletion

---

## 4. CAMPAIGNS LIST TABLE (SCD_Campaigns_List_Table)

### Location
`/includes/admin/components/class-campaigns-list-table.php`

### Extends
`WP_List_Table` - WordPress standard list table class

### Columns
1. **cb** - Checkbox (bulk actions)
2. **name** - Campaign name with actions
3. **discount** - Discount type and value
4. **products** - Product selection summary
5. **status** - Status badge
6. **schedule** - Start/end dates
7. **priority** - Priority level indicator
8. **health** - Campaign health score
9. **performance** - Performance metrics
10. **created** - Creation date and user

### Column Renderers

#### Name Column (`column_name`)
- Displays campaign name with link to edit
- Shows recurring badge (if applicable)
- Shows description below name
- Displays row actions based on capabilities:
  - Edit, Activate, Deactivate, Duplicate, Move to Trash
  - Quick Edit (inline editing)
  - Restore (in trash view)
  - Delete Permanently (in trash view)
  - Stop Recurring (if recurring enabled)

#### Status Column (`column_status`)
```php
public function column_status($item): string
```
- Uses `SCD_Badge_Helper::status_badge()`
- Shows visual badge with status label

#### Discount Column (`column_discount`)
- Percentage: Shows "X%"
- Fixed: Shows formatted currency
- Tiered: Shows "N tiers"
- BOGO: Shows "Buy X Get Y" with discount
- Spend Threshold: Shows "N thresholds"

#### Products Column (`column_products`)
- All Products badge
- Specific Products: Count badge
- Random Products: Count with "Pending" if not yet selected
- Smart Selection: Count with "Pending" if pending compilation
- Uses selection type badges with tooltips

#### Schedule Column (`column_schedule`)
- Shows Start date/time
- Shows End date/time or "No end date"
- Uses site timezone for display

#### Health Column (`column_health`)
```php
public function column_health($item): string
```
- Uses `SCD_Campaign_Health_Service` for analysis
- Shows health score (0-100) with icon
- Icon legend:
  - 🟢 Excellent (90+)
  - 🟡 Good (70-89)
  - 🟠 Fair (50-69)
  - ⚠️ Poor (30-49)
  - 🔴 Critical (<30)
- Shows critical issues and warnings in tooltip
- Includes coverage data calculation

#### Performance Column (`column_performance`)
- Shows revenue generated, orders count, conversion rate
- "No data yet" if no performance metrics available

#### Created Column (`column_created`)
- Shows creation date/time
- Shows creator user name
- Uses site timezone for display

### Bulk Actions
```php
public function get_bulk_actions(): array
```
- Activate / Deactivate
- Move to Trash / Delete Permanently
- Restore (trash view)
- Stop Recurring

### Sorting
Sortable columns:
- name
- status
- created (by created_at)
- schedule (by start_date)
- priority

### Pagination
- Items per page: 20 (configurable via screen options)
- Respects current page and sorting parameters

### Filtering
- Status filter dropdown: All, Active, Scheduled, Paused, Expired, Draft, Trash
- Search functionality: Searches campaign name and description
- Views: Status counts at top for quick navigation

### Capabilities Integration
```php
private $capability_manager: SCD_Admin_Capability_Manager
```
- Checks user capabilities for each action
- Hides/shows columns and actions based on permissions
- Examples:
  - `scd_create_campaigns` - Create/duplicate
  - `scd_edit_campaigns` - Edit/quick edit
  - `scd_activate_campaigns` - Activate/deactivate
  - `scd_delete_campaigns` - Delete/trash

### Recurring Campaign Support
- Detects parent vs. child campaigns
- Shows badges:
  - Parent with recurring: "🔄 Daily/Weekly/Monthly"
  - Parent with recurring stopped: "⏸️ Stopped"
  - Child campaign: "↳ Occurrence #X"

### Quick Edit
```php
public function inline_edit(): void
```
- Inline editing form for:
  - Name
  - Status
  - Priority
  - Discount Value
  - Start Date
  - End Date
- Uses AJAX for save
- Nonce-protected

### Trash Management
- WordPress-style trash system
- Soft delete with restoration capability
- Empty Trash button (with confirmation)
- Trash count in views

---

## 5. CAMPAIGN FORMATTING & SERIALIZATION

### Campaign Formatter (SCD_Campaign_Formatter)

#### Purpose
Prepares campaign data for different display contexts

#### Format Method
```php
public function format(SCD_Campaign $campaign, string $context): array
```

Contexts:
- **list** - Campaign list table (default)
- **detail** - Single campaign view
- **edit** - Edit form display
- **api** - REST API response
- **export** - CSV/export format

#### Formatting Methods
- `format_name()` - Escapes and adds draft indicator
- `format_status()` - Returns status with label, class, icon
- `format_discount()` - Returns type, value, display, badge
- `format_schedule()` - Formats start/end dates
- `format_priority()` - Formats priority badge
- `format_detail_data()` - Extended display data
- `format_edit_data()` - Form display data
- `format_export_data()` - Export format
- `format_api_data()` - REST API format

#### Status Labels & Icons
```
draft     → Draft (dashicons-edit)
active    → Active (dashicons-yes-alt)
paused    → Paused (dashicons-controls-pause)
scheduled → Scheduled (dashicons-clock)
expired   → Expired (dashicons-dismiss)
archived  → Archived (dashicons-archive)
```

### Campaign Serializer (SCD_Campaign_Serializer)

#### Purpose
Handles serialization/deserialization for API responses

#### Serialize
```php
public function serialize(SCD_Campaign $campaign, array $context = []): array
```

Context options:
- `include_meta` - Include can_edit, can_delete metadata
- `include_stats` - Include performance statistics
- `include_products` - Include product list details
- `include_split_datetime` - Include separate date/time components

Output format:
- ISO 8601 timestamps (RFC 3339)
- DateTime objects formatted as 'c' (ISO 8601)
- HATEOAS links for navigation

#### Deserialize
```php
public function deserialize(array $data): array
```
- Validates incoming API data
- Sanitizes all inputs
- Handles datetime parsing
- Returns validated array for creation

#### Collection Serialization
```php
public function serialize_collection(array $campaigns, array $context): array
```
- Serializes multiple campaigns with same context
- Useful for list endpoints

---

## 6. STATUS TRANSITIONS & WORKFLOW

### Campaign Statuses
```
draft
  ├─→ active (when activated)
  ├─→ scheduled (when schedule set)
  └─→ archived (when archived)

scheduled
  ├─→ active (when start date reached)
  ├─→ paused (when manually paused)
  ├─→ draft (reset to draft)
  └─→ archived

active
  ├─→ paused (when manually paused)
  ├─→ expired (when end date reached)
  └─→ archived

paused
  ├─→ active (when resumed)
  ├─→ scheduled (reschedule)
  ├─→ draft (reset)
  ├─→ expired
  └─→ archived

expired
  ├─→ draft (reset with new schedule)
  └─→ archived

archived
  └─→ draft (restore and reset)
```

### Lifecycle
1. **Creation** → draft status
2. **Configuration** → edit while draft
3. **Activation** → active/scheduled status
4. **Operation** → campaign applies discounts
5. **Completion** → expires or manually paused
6. **Archival** → archived for historical tracking
7. **Restoration** → can revert to draft for reuse

---

## 7. KEY PATTERNS & BEST PRACTICES

### Data Access Pattern (Repository)
```
SCD_Campaign_Manager → SCD_Campaign_Repository → Database
                    → SCD_Cache_Manager
```

### Object Hydration/Dehydration
```
Database Row → hydrate() → SCD_Campaign Object → to_array() → REST API
```

### Validation Layers
1. Campaign Manager validates input data
2. Campaign object validates structure
3. Repository validates on save
4. Capability manager validates access

### Concurrency Control
- Optimistic locking via version field
- ConcurrentModificationException on conflict
- Prevents lost updates in concurrent edits

### Soft Delete Pattern
```
delete() → Sets deleted_at timestamp
find()  → Excludes deleted_at IS NULL by default
restore() → Clears deleted_at timestamp
```

### Caching Strategy
- Multi-level: Database, object, list views
- TTL: 30 minutes for active campaigns
- Invalidation: On save/delete operations
- Product-specific cache for discount application

### DateTime Handling
```
Database: Always UTC (Y-m-d H:i:s format)
Campaign.timezone: Display timezone (separate from data)
API Response: ISO 8601 format
UI Display: Uses site timezone via wp_date()
```

### Security
- All database queries use prepared statements
- Input sanitization (text_field, textarea, email, etc.)
- Output escaping (esc_html, esc_attr, esc_url, wp_kses_post)
- Nonce verification for form actions
- Capability checks for all operations
- Ownership verification for user-created campaigns

---

## 8. DATABASE SCHEMA REFERENCE

### Campaigns Table
```sql
CREATE TABLE wp_scd_campaigns (
  id INT PRIMARY KEY AUTO_INCREMENT,
  uuid VARCHAR(36) UNIQUE,
  name VARCHAR(255) NOT NULL,
  slug VARCHAR(255) UNIQUE,
  description LONGTEXT,
  status VARCHAR(50) DEFAULT 'draft',
  priority INT DEFAULT 3,
  settings JSON,
  metadata JSON,
  template_id INT,
  created_by INT,
  updated_by INT,
  starts_at DATETIME UTC,
  ends_at DATETIME UTC,
  timezone VARCHAR(50),
  product_selection_type VARCHAR(50),
  product_ids JSON,
  category_ids JSON,
  tag_ids JSON,
  discount_type VARCHAR(50),
  discount_value DECIMAL(10,2),
  discount_rules JSON,
  created_at DATETIME UTC,
  updated_at DATETIME UTC,
  version INT DEFAULT 1,
  deleted_at DATETIME NULL,
  
  INDEX(status),
  INDEX(created_by),
  INDEX(created_at),
  INDEX(deleted_at)
)
```

---

## 9. COMMON OPERATIONS WORKFLOW

### Creating a Campaign
```php
$data = [
    'campaign_name' => 'Summer Sale',
    'discount_type' => 'percentage',
    'discount_value' => 20.0,
    'product_selection_type' => 'specific_products',
    'selected_products' => [1, 2, 3],
    'start_date' => '2025-06-01',
    'start_time' => '00:00:00',
    'end_date' => '2025-06-30',
    'end_time' => '23:59:59',
];

$campaign = $manager->create($data);
// Returns: SCD_Campaign | WP_Error
```

### Retrieving Campaigns
```php
// Single campaign
$campaign = $repository->find(1);

// By criteria
$active = $repository->get_active();
$drafts = $repository->find_by_status('draft');

// With pagination
$args = ['limit' => 20, 'offset' => 0, 'orderby' => 'created_at'];
$campaigns = $repository->find_all($args);

// Search
$args = ['search' => 'sale'];
$campaigns = $manager->get_campaigns($args);
```

### Updating Campaign
```php
$data = ['discount_value' => 25.0];
$updated = $manager->update($campaign_id, $data);
```

### Activating Campaign
```php
$result = $manager->activate($campaign_id);
// Sets status to 'active'
// Triggers compilation if needed
// Schedules automatic deactivation if end date set
```

### For Display
```php
// Format for list table
$formatted = $formatter->format($campaign, 'list');

// Serialize for API
$serialized = $serializer->serialize($campaign, [
    'include_meta' => true,
    'include_stats' => true
]);
```

---

## Summary

The campaign management system is architecturally sound with:
- Clear separation of concerns (Entity, Repository, Manager)
- Type-safe operations with proper validation
- Comprehensive caching for performance
- Soft delete for data preservation
- Optimistic locking for concurrency
- Flexible data model with settings/metadata for extensibility
- Security-first approach with sanitization and escaping
- Rich admin UI with bulk actions and filtering
- Support for complex discount types and product selection methods
