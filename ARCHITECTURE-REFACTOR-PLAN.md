# Architecture Refactoring Plan for Production

## Current Issues

1. **Vague metadata field** - mixes wizard state, configuration, and runtime data
2. **Can't query conditions** - stored as JSON blob
3. **Unclear compilation flow** - configuration vs compiled data
4. **No audit trail** - can't see when products were compiled

## Recommended Changes (Pre-Production)

### 1. Split Campaign Data into Clear Domains

**Configuration (User Input):**
- product_selection_type ✅ (already dedicated column)
- category_ids ✅ (already dedicated column)
- conditions_logic → NEW dedicated column
- random_product_count → NEW dedicated column
- product_conditions → NEW table (wp_scd_campaign_conditions)

**Compiled Data (System Generated):**
- product_ids ✅ (already dedicated column)
- compiled_at → NEW column
- compilation_method → NEW column ('static', 'random', 'smart', 'conditional')

**Wizard State (Temporary):**
- metadata → KEEP for wizard-only temporary data
- Cleared after campaign creation complete

### 2. New Migration: 007-refactor-campaign-structure.php

```php
<?php
/**
 * Migration 007: Refactor Campaign Structure
 *
 * Improvements:
 * - Move commonly queried fields from metadata to dedicated columns
 * - Create campaign_conditions table for queryable conditions
 * - Add compilation tracking fields
 * - Clean up metadata to only contain wizard temporary state
 */
class SCD_Migration_007_Refactor_Campaign_Structure implements SCD_Migration_Interface {

    public function up(): void {
        global $wpdb;
        $campaigns_table = $wpdb->prefix . 'scd_campaigns';

        // Add dedicated columns for campaign configuration
        $wpdb->query("
            ALTER TABLE {$campaigns_table}
            ADD COLUMN conditions_logic VARCHAR(3) DEFAULT 'all'
                COMMENT 'AND or OR logic for conditions'
                AFTER product_selection_type,
            ADD COLUMN random_product_count INT UNSIGNED DEFAULT 5
                COMMENT 'Number of random products to select'
                AFTER conditions_logic,
            ADD COLUMN compiled_at DATETIME DEFAULT NULL
                COMMENT 'When product_ids were last compiled'
                AFTER product_ids,
            ADD COLUMN compilation_method VARCHAR(20) DEFAULT NULL
                COMMENT 'Method used: static, random, smart, conditional'
                AFTER compiled_at
        ");

        // Create campaign conditions table
        $wpdb->query("
            CREATE TABLE {$wpdb->prefix}scd_campaign_conditions (
                id bigint(20) unsigned AUTO_INCREMENT,
                campaign_id bigint(20) unsigned NOT NULL,
                condition_type VARCHAR(50) NOT NULL COMMENT 'price, stock, category, tag, attribute',
                operator VARCHAR(10) NOT NULL COMMENT '>, <, =, >=, <=, between, in, not_in',
                value_1 VARCHAR(255) NOT NULL,
                value_2 VARCHAR(255) DEFAULT NULL COMMENT 'For between operator',
                mode ENUM('include', 'exclude') DEFAULT 'include',
                sort_order INT UNSIGNED DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY campaign_id (campaign_id),
                KEY condition_type (condition_type),
                KEY mode (mode),
                FOREIGN KEY (campaign_id) REFERENCES {$campaigns_table}(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Migrate existing metadata to new structure
        $this->migrate_existing_data();
    }

    private function migrate_existing_data(): void {
        global $wpdb;
        $campaigns_table = $wpdb->prefix . 'scd_campaigns';

        // Get all campaigns with metadata
        $campaigns = $wpdb->get_results("
            SELECT id, metadata
            FROM {$campaigns_table}
            WHERE metadata IS NOT NULL AND metadata != ''
        ");

        foreach ($campaigns as $campaign) {
            $metadata = json_decode($campaign->metadata, true);

            // Extract and move to dedicated columns
            $updates = array();
            if (isset($metadata['conditions_logic'])) {
                $updates['conditions_logic'] = $metadata['conditions_logic'];
            }
            if (isset($metadata['random_count'])) {
                $updates['random_product_count'] = intval($metadata['random_count']);
            }

            // Update campaign with dedicated column values
            if (!empty($updates)) {
                $wpdb->update(
                    $campaigns_table,
                    $updates,
                    array('id' => $campaign->id)
                );
            }

            // Migrate conditions to new table
            if (!empty($metadata['product_conditions'])) {
                $this->migrate_conditions($campaign->id, $metadata['product_conditions']);
            }

            // Clean metadata - remove migrated fields
            unset(
                $metadata['conditions_logic'],
                $metadata['random_count'],
                $metadata['product_conditions']
            );

            // Update with cleaned metadata
            $wpdb->update(
                $campaigns_table,
                array('metadata' => wp_json_encode($metadata)),
                array('id' => $campaign->id)
            );
        }
    }

    private function migrate_conditions(int $campaign_id, array $conditions): void {
        global $wpdb;
        $conditions_table = $wpdb->prefix . 'scd_campaign_conditions';

        foreach ($conditions as $index => $condition) {
            $wpdb->insert(
                $conditions_table,
                array(
                    'campaign_id' => $campaign_id,
                    'condition_type' => $condition['type'] ?? '',
                    'operator' => $condition['operator'] ?? '=',
                    'value_1' => $condition['value'] ?? '',
                    'value_2' => $condition['value2'] ?? null,
                    'mode' => $condition['mode'] ?? 'include',
                    'sort_order' => $index,
                )
            );
        }
    }

    public function down(): void {
        global $wpdb;

        // Reverse migration - move data back to metadata
        // Drop new table
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}scd_campaign_conditions");

        // Remove new columns
        $campaigns_table = $wpdb->prefix . 'scd_campaigns';
        $wpdb->query("
            ALTER TABLE {$campaigns_table}
            DROP COLUMN conditions_logic,
            DROP COLUMN random_product_count,
            DROP COLUMN compiled_at,
            DROP COLUMN compilation_method
        ");
    }
}
```

### 3. Create Campaign Conditions Repository

```php
<?php
/**
 * Campaign Conditions Repository
 *
 * Handles CRUD operations for campaign conditions
 */
class SCD_Campaign_Conditions_Repository {

    public function get_conditions_for_campaign(int $campaign_id): array {
        global $wpdb;
        $table = $wpdb->prefix . 'scd_campaign_conditions';

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                 WHERE campaign_id = %d
                 ORDER BY sort_order ASC",
                $campaign_id
            ),
            ARRAY_A
        );
    }

    public function save_conditions(int $campaign_id, array $conditions): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'scd_campaign_conditions';

        // Delete existing conditions
        $wpdb->delete($table, array('campaign_id' => $campaign_id));

        // Insert new conditions
        foreach ($conditions as $index => $condition) {
            $wpdb->insert(
                $table,
                array(
                    'campaign_id' => $campaign_id,
                    'condition_type' => $condition['type'],
                    'operator' => $condition['operator'],
                    'value_1' => $condition['value'],
                    'value_2' => $condition['value2'] ?? null,
                    'mode' => $condition['mode'] ?? 'include',
                    'sort_order' => $index,
                )
            );
        }

        return true;
    }
}
```

### 4. Update Campaign Class

```php
<?php
/**
 * Updated Campaign Class
 *
 * Clear separation between configuration and compiled data
 */
class SCD_Campaign {

    // Configuration (User Input) - Stored in dedicated columns
    private string $product_selection_type;
    private array $category_ids;
    private string $conditions_logic;  // NEW
    private int $random_product_count; // NEW
    // conditions loaded from wp_scd_campaign_conditions table

    // Compiled Data (System Generated) - Stored in dedicated columns
    private array $compiled_product_ids;
    private ?DateTime $compiled_at;         // NEW - when compiled
    private ?string $compilation_method;    // NEW - how compiled

    // Wizard State (Temporary) - Only in metadata during creation
    private array $metadata; // Wizard-only temporary data

    public function needs_recompilation(): bool {
        // Recompile if:
        // 1. Never compiled (compiled_at is null)
        // 2. Random products and rotation enabled
        // 3. Smart selection and products changed

        if (!$this->compiled_at) {
            return true;
        }

        if ($this->product_selection_type === 'random_products') {
            // Check if rotation is needed
            return true;
        }

        return false;
    }

    public function get_compiled_at(): ?DateTime {
        return $this->compiled_at;
    }

    public function set_compiled_at(DateTime $date): void {
        $this->compiled_at = $date;
    }

    public function get_compilation_method(): ?string {
        return $this->compilation_method;
    }
}
```

### 5. Benefits of This Refactoring

**Before (Current):**
```php
// ❌ Can't query campaigns by condition type
// ❌ Can't see when products were compiled
// ❌ Unclear separation of concerns
$metadata = json_decode($campaign->metadata, true);
$conditions = $metadata['product_conditions'] ?? array();
```

**After (Refactored):**
```php
// ✅ Can query: "Show all campaigns with price conditions"
$campaigns = $wpdb->get_results("
    SELECT DISTINCT c.*
    FROM wp_scd_campaigns c
    JOIN wp_scd_campaign_conditions cc ON c.id = cc.campaign_id
    WHERE cc.condition_type = 'price'
");

// ✅ Clear audit trail
$compiled_at = $campaign->get_compiled_at();
$method = $campaign->get_compilation_method();

// ✅ Clear separation
$conditions_logic = $campaign->get_conditions_logic();  // Dedicated column
$random_count = $campaign->get_random_product_count();  // Dedicated column
```

### 6. Implementation Order

1. **Create migration file** (007-refactor-campaign-structure.php)
2. **Run migration** to add columns and table
3. **Update Campaign class** to use new fields
4. **Update Repository** to read/write new structure
5. **Update Campaign Manager** compilation logic
6. **Test thoroughly** with existing campaigns
7. **Document the new structure**

### 7. What Stays in metadata After Refactoring

**Only wizard temporary state:**
```php
'metadata' => array(
    'wizard_progress' => 'step_3',
    'wizard_started_at' => '2025-11-07 10:00:00',
    'wizard_completed_at' => null,
    'draft_data' => array(...), // Temporary unsaved changes
)
```

**Everything else moves to:**
- Dedicated columns (conditions_logic, random_product_count)
- Separate table (wp_scd_campaign_conditions)
- Settings column (general configuration)

## Migration Timeline

- **Day 1-2**: Create migration and new repository
- **Day 3-4**: Update Campaign class and manager
- **Day 5**: Test with existing campaigns
- **Day 6**: Code review and refinement
- **Day 7**: Final testing and documentation

## Success Criteria

✅ All metadata fields have clear purpose
✅ Can query campaigns by any condition type
✅ Clear audit trail for compilations
✅ No JSON parsing needed for common queries
✅ Foreign key constraints prevent orphaned data
✅ Zero data loss during migration
