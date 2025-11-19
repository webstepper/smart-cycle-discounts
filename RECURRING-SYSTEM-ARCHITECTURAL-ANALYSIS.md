# Recurring System - Architectural Analysis & Edge Cases

## Status: 🔴 FUNDAMENTAL ARCHITECTURAL ISSUES

**Date**: 2025-11-16
**Severity**: Critical - System not viable for long-term campaigns
**Impact**: All recurring campaigns over 1-3 months

---

## The Core Problem

**The recurring system "snapshots" campaign state at creation time and assumes it will remain valid indefinitely.**

This fundamental design flaw means:
- Products referenced today may not exist in 6 months
- Prices valid today will change
- Categories may be reorganized
- Business rules will evolve
- Store configuration will change

**Result**: Future occurrences will fail or behave incorrectly.

---

## Edge Cases & Failure Scenarios

### 1. Product Lifecycle Changes (CRITICAL)

#### Scenario: Product Deletion
**Setup**: Black Friday campaign on "Winter Jackets" category, recurring monthly for 12 months

**Month 1**: Campaign created
- Products: Jacket A, Jacket B, Jacket C
- All products exist ✅

**Month 4**: Store reorganization
- Jacket B discontinued and deleted ❌
- Occurrence materializes with reference to deleted product
- **Result**: Product selection breaks, campaign may not run

**Month 8**: New arrivals
- Jacket D, E, F added to category
- **Result**: New products NOT included in recurring occurrences (using snapshot)

**What Happens**:
```php
// Occurrence cache stores: product_ids = [101, 102, 103]
// Month 4: Product 102 deleted from database
// Materialization attempts to create campaign with products [101, 102, 103]
// Product 102 doesn't exist → What happens?
```

**Current Behavior**: ❌ Unknown - likely silent failure or partial campaign

#### Scenario: Product Out of Stock
**Setup**: Flash sale on specific products, recurring weekly

**Week 1**: All products in stock ✅
**Week 5**: Product A sold out, backordered ❌
**Week 10**: Product B discontinued ❌

**Result**: Campaign continues running on out-of-stock products

---

### 2. Category/Taxonomy Changes (CRITICAL)

#### Scenario: Category Restructuring
**Setup**: "25% off Electronics" recurring monthly for 12 months

**Month 1**: Structure
```
Electronics (ID: 50)
  ├── Laptops (ID: 51)
  ├── Phones (ID: 52)
  └── Tablets (ID: 53)
```

**Month 6**: Store reorganization
```
Tech Products (ID: 100) ← NEW
  ├── Computers (ID: 101) ← Laptops moved here
  └── Mobile (ID: 102) ← Phones + Tablets merged
Electronics (ID: 50) ← DELETED
```

**What Happens**:
```php
// Occurrence cache stores: condition['value'] = 50 (Electronics category)
// Month 6: Category 50 no longer exists
// Condition: "category equals 50"
// Result: No products match → Empty campaign
```

**Current Behavior**: ❌ Campaign materializes with 0 products

#### Scenario: Tag/Attribute Changes
**Setup**: "Sale items" tag-based campaign

**Issue**: Tags are user-managed and change constantly
- Products tagged "sale" → Tag removed
- New products need "sale" tag
- Tag renamed "clearance"

---

### 3. Price Changes (HIGH IMPACT)

#### Scenario: Price Inflation
**Setup**: "50% off products under $100" recurring monthly for 12 months

**Month 1**: Product prices: $50, $75, $80
- Discount amount: $25, $37.50, $40
- Revenue impact: Moderate ✅

**Month 6**: Supplier increases prices 30%
- Product prices: $65, $97.50, $104
- Same 50% discount: $32.50, $48.75, $52
- **Revenue impact**: 30% higher than planned ❌

**Month 12**: Further price increases
- Product prices: $80, $120, $130
- Product $80: Still qualifies, 50% = $40 discount
- Products $120, $130: No longer under $100, excluded from campaign
- **Result**: Campaign scope shrinks unexpectedly

#### Scenario: Cost Changes
**Setup**: Campaign with profit margin warnings

**Month 1**: Cost $40, Price $100, 50% discount = $50 sale price
- Profit: $10 ✅
- Margin: 20% ✅

**Month 6**: Supplier cost increases to $55
- Same $50 sale price
- **Loss**: -$5 per unit ❌
- **Margin**: -10% ❌

**What Happens**: Campaign continues running at a loss, no warnings

---

### 4. Conditional Logic Failures (CRITICAL)

#### Scenario: Cart Total Conditions
**Setup**: "Free shipping on orders > $100" recurring for 12 months

**Month 1**: Average cart $120, works great ✅

**Month 6**: Inflation + price increases
- Average cart still ~3 items
- Cart value now $150
- Condition still "$100" (from snapshot)
- **Result**: Over-generous discount, revenue loss

#### Scenario: Customer Segmentation
**Setup**: "First-time customers only" recurring monthly

**Issue**: Customer database changes constantly
- New customers each month
- "First-time" logic becomes stale
- How to re-evaluate for each occurrence?

**Current Behavior**: ❌ Uses snapshot logic, doesn't re-evaluate

#### Scenario: Stock-Based Conditions
**Setup**: "Products with stock > 10 units" recurring weekly

**Issue**: Stock changes hourly
- Snapshot captures stock at creation
- Week 2: Stock levels completely different
- Condition outdated immediately

---

### 5. WooCommerce/Store Configuration Changes

#### Scenario: Currency Change
**Setup**: Campaign created with USD, recurring for 12 months

**Month 5**: Store switches to EUR
- All prices in EUR
- Discount amounts in USD (from snapshot)
- **Result**: Complete calculation failure

#### Scenario: Tax Settings Change
**Setup**: Campaign with "prices include tax"

**Month 3**: Store changes to "prices exclude tax"
- Discount calculations use wrong base
- Final prices incorrect
- Customer confusion

#### Scenario: Shipping Method Changes
**Setup**: Campaign with "Free shipping on orders > $50"

**Month 4**: New shipping zones added
- Old logic doesn't account for new zones
- International orders get free shipping unintentionally
- Revenue loss

---

### 6. Performance & Scale Issues

#### Scenario: High-Frequency Long-Duration
**Setup**: Daily campaign recurring for 12 months = 365 occurrences

**Database Growth**:
```sql
-- wp_scd_recurring_cache table
365 occurrences × 1 parent = 365 rows
10 daily campaigns × 365 = 3,650 rows/year
```

**ActionScheduler Queue**:
```
365 scheduled events per campaign
Check every occurrence: 5 min before start
Database queries: 365 × 2 = 730 queries/year per campaign
```

**Memory Issues**:
- Cache generation: All 365 occurrences in memory
- Materialization: Clone entire campaign 365 times
- Performance degradation over time

#### Scenario: Cache Horizon Limitations
**Current**: 90-day cache horizon

**Issue**: For 12-month campaigns
- Only next 90 days cached
- Cache regenerates every 90 days
- Gap between cache and actual occurrences
- What happens if cache regeneration fails?

---

### 7. User Intent & Change Management

#### Scenario: Mid-Flight Changes
**Setup**: 12-month recurring campaign created in January

**March**: User wants to change discount from 20% to 30%
- 2 months already materialized (past)
- 1 month active (present)
- 9 months cached (future)

**Questions**:
1. Do we update future occurrences? ✅ Probably yes
2. Do we update active occurrence? 🤔 Maybe?
3. Do we retroactively fix past occurrences? ❌ Probably no
4. How do we know user wants to update ALL vs just parent?

**Current Behavior**: ❌ No mechanism to update cached occurrences

#### Scenario: Partial Cancellation
**User**: "I want to cancel Christmas week occurrence but keep others"

**Current System**: ❌ Can only delete entire parent (deletes all occurrences)

---

### 8. Data Integrity & Cascading Failures

#### Scenario: Parent Campaign Deleted
**Setup**: Recurring campaign with 50 future occurrences

**User**: Deletes parent campaign
**Database**: Foreign key CASCADE deletes all cached occurrences ✅
**ActionScheduler**: Events still scheduled ❌

**What Happens**:
```
1. Cached occurrences deleted from database
2. ActionScheduler event fires: "Materialize occurrence #10"
3. Handler queries database for occurrence #10
4. Not found → What now?
```

**Current Behavior**: ❌ Unknown - likely error logged, event marked failed

#### Scenario: Materialization Failures
**Setup**: Occurrence scheduled to materialize

**Attempt 1**: Product validation fails (product deleted)
- Retry scheduled (1 hour later) ✅

**Attempt 2**: Still fails
- Retry scheduled (1 hour later) ✅

**Attempt 3**: Still fails
- Email admin, mark as failed ✅

**Then What?**:
- Occurrence marked failed in cache
- Stays in cache forever?
- No way to retry manually?
- No way to fix and re-materialize?

---

### 9. Validation Timing Issues

#### Scenario: Stale Validations
**Setup**: Campaign validated at creation time

**Month 1**: Validation passes
- Products exist ✅
- Prices reasonable ✅
- Conditions valid ✅

**Month 6**: Conditions change
- Products deleted ❌
- Prices doubled ❌
- Categories reorganized ❌

**Materialization**: No re-validation
- Uses stale snapshot
- Creates invalid campaign instance
- Customers see broken campaign

**What's Missing**: Pre-materialization validation

---

### 10. Complex Interaction Failures

#### Scenario: Multiple Recurring Campaigns
**Setup**:
1. "Daily 10% off Electronics" (12 months)
2. "Weekly 20% off Gaming" (6 months)
3. "Monthly 30% off Laptops" (3 months)

**Overlap**: Gaming Laptop (in all 3 categories)
- Day 1: 10% from campaign #1
- Week 1 Day 1: 20% from campaign #2 (higher)
- Month 1 Week 1 Day 1: 30% from campaign #3 (highest)

**Issues**:
1. Which discount wins?
2. Do they stack?
3. How does priority work with recurring?
4. What if campaign #3 is deleted but occurrences still exist?

---

## Current System Analysis

### What the System Does Now

**Occurrence Cache (`class-occurrence-cache.php`)**:
```php
public function regenerate( int $parent_id, array $recurring, array $schedule ): int {
    // 1. Delete existing pending occurrences
    // 2. Calculate occurrences based on pattern
    // 3. Insert into cache table
    // 4. Return count
}
```

**What It Stores**:
- Parent campaign ID
- Occurrence number (1, 2, 3...)
- Start/end datetime
- Status (pending/active/failed)
- Instance ID (after materialization)

**What It Doesn't Store**:
- ❌ Product selection criteria
- ❌ Discount rules
- ❌ Conditions
- ❌ Validation checksums
- ❌ Expected product count
- ❌ Price snapshots

**Materialization (`class-recurring-handler.php`)**:
```php
public function materialize_occurrence( int $parent_id, int $occurrence_number ): void {
    // 1. Get occurrence from cache
    // 2. Load parent campaign
    // 3. Clone parent campaign data
    // 4. Set new start/end dates
    // 5. Create instance campaign
    // 6. Mark cache as 'active'
}
```

**What It Does**:
- ✅ Clones parent campaign
- ✅ Updates dates
- ✅ Creates new campaign

**What It Doesn't Do**:
- ❌ Validate products still exist
- ❌ Check price changes
- ❌ Re-evaluate conditions
- ❌ Verify categories exist
- ❌ Test campaign viability
- ❌ Provide fallback options
- ❌ Notify admin of issues

---

## Fundamental Design Flaws

### 1. **Snapshot-Based Materialization**
**Flaw**: Assumes parent campaign data is valid forever

**Why It Fails**: Data changes (products, prices, categories)

**Fix Needed**: Dynamic re-evaluation at materialization time

### 2. **No Change Detection**
**Flaw**: System doesn't monitor for invalidating changes

**Why It Fails**: Product deletions, category changes go unnoticed

**Fix Needed**: Change detection hooks + occurrence validation

### 3. **No Pre-Materialization Validation**
**Flaw**: Materializes blindly without checking viability

**Why It Fails**: Creates invalid campaigns that fail in production

**Fix Needed**: Validation gate before materialization

### 4. **Limited Cache Horizon**
**Flaw**: Only 90 days cached, 12-month campaigns need 365

**Why It Fails**: Cache regeneration gaps, potential failures

**Fix Needed**: Extend horizon OR on-demand generation

### 5. **No Fallback Strategies**
**Flaw**: When materialization fails, just retries then gives up

**Why It Fails**: No graceful degradation or admin intervention

**Fix Needed**: Fallback rules + manual approval workflow

### 6. **No Occurrence Lifecycle Management**
**Flaw**: Simple pending → active → failed states

**Why It Fails**: No validation, approval, or review steps

**Fix Needed**: Full lifecycle: pending → validated → approved → materialized → active

### 7. **No User Control Over Instances**
**Flaw**: Can't cancel specific occurrences, only entire parent

**Why It Fails**: All-or-nothing approach, no flexibility

**Fix Needed**: Instance-level management UI

---

## Recommended Solutions

### Solution 1: **Dynamic Materialization** (RECOMMENDED)

Instead of storing snapshot, store CRITERIA and re-evaluate at materialization:

```php
// Current (snapshot):
$instance_data = $parent_campaign->get_all_data(); // Static snapshot

// Proposed (dynamic):
$instance_data = $this->build_occurrence_from_criteria(
    $parent_id,
    $occurrence_date,
    $validation_options = [
        'require_products_exist' => true,
        'validate_price_changes' => true,
        'check_category_structure' => true,
        'max_price_change_percent' => 50,
    ]
);
```

**How It Works**:
1. Store selection CRITERIA, not product IDs
   - "All products in category Electronics"
   - "Products with tag sale"
   - "Products matching conditions X, Y, Z"

2. Re-evaluate criteria at materialization time
   - Query current products matching criteria
   - Use current prices
   - Apply current category structure

3. Validate before materializing
   - Products exist?
   - Prices haven't changed >X%?
   - Categories still valid?
   - Conditions still make sense?

4. Handle validation failures
   - Email admin: "Occurrence #5 can't materialize - products missing"
   - Pause and require approval
   - Use fallback (broader category?)
   - Skip occurrence

**Pros**:
- Always uses current data
- Adapts to changes automatically
- Validates before materializing
- Detects problems early

**Cons**:
- More complex logic
- Performance overhead (re-query products)
- May produce different results than expected

---

### Solution 2: **Change Detection & Alerts**

Monitor for changes that invalidate future occurrences:

```php
// Hook into product deletion
add_action( 'before_delete_post', function( $post_id ) {
    if ( 'product' !== get_post_type( $post_id ) ) {
        return;
    }

    // Find recurring campaigns referencing this product
    $affected_campaigns = $this->find_campaigns_with_product( $post_id );

    foreach ( $affected_campaigns as $campaign_id ) {
        // Mark future occurrences for review
        $this->flag_occurrences_for_validation( $campaign_id );

        // Notify admin
        $this->send_admin_alert(
            "Product #{$post_id} deletion affects recurring campaign #{$campaign_id}",
            [ 'future_occurrences' => $this->count_pending_occurrences( $campaign_id ) ]
        );
    }
} );
```

**What To Monitor**:
- Product deletion
- Category deletion/restructuring
- Price changes > X%
- Tag/attribute changes
- Inventory levels
- Store configuration changes

**Pros**:
- Proactive problem detection
- Admin awareness
- Time to fix before occurrence

**Cons**:
- Lots of hooks needed
- Performance overhead
- Alert fatigue

---

### Solution 3: **Validation Gates**

Add validation step before materialization:

```
Current Flow:
pending → materialize → active

Proposed Flow:
pending → validate → [PASS: approve → materialize → active]
                  → [FAIL: alert → manual_review → (fix or skip)]
```

**Validation Checks**:
```php
public function validate_occurrence( $occurrence_id ) {
    $checks = [
        'products_exist' => $this->check_products_exist( $occurrence ),
        'categories_valid' => $this->check_categories_valid( $occurrence ),
        'prices_reasonable' => $this->check_price_changes( $occurrence ),
        'conditions_valid' => $this->check_conditions( $occurrence ),
        'no_conflicts' => $this->check_campaign_conflicts( $occurrence ),
        'inventory_available' => $this->check_stock_levels( $occurrence ),
    ];

    $passed = array_filter( $checks );
    $failed = array_diff_key( $checks, $passed );

    if ( ! empty( $failed ) ) {
        return new WP_Error( 'validation_failed', 'Occurrence validation failed', $failed );
    }

    return true;
}
```

**Pros**:
- Catches problems before they happen
- Clear failure reasons
- Allows manual intervention

**Cons**:
- Adds complexity
- May block legitimate occurrences
- Requires admin attention

---

### Solution 4: **Occurrence Management UI**

Add admin interface to manage occurrences:

**Features**:
- View all scheduled occurrences
- Edit specific occurrence (dates, products, discount)
- Cancel specific occurrence
- Re-validate occurrence manually
- Approve/reject pending occurrences
- View validation status
- Retry failed occurrences

**Benefits**:
- User control
- Transparency
- Flexibility
- Problem resolution

---

### Solution 5: **Smart Defaults & Limits**

Practical limits to reduce risk:

```php
// Validation rules
const MAX_RECURRING_DURATION_MONTHS = 6; // Max 6 months ahead
const MAX_OCCURRENCES = 50; // Max 50 occurrences
const CACHE_HORIZON_DAYS = 90; // Only cache 90 days ahead
const VALIDATION_INTERVAL_DAYS = 30; // Re-validate every 30 days

// Warnings
if ( $duration_months > 3 ) {
    $warning = "Campaigns longer than 3 months are subject to product/price changes. " .
               "Consider shorter recurring periods with manual review.";
}
```

**Recommended Limits**:
- Max 6 months recurring duration
- Max 50 occurrences total
- Require validation every 30 days
- Price change threshold: 30%
- Product deletion: Pause campaign

---

## Recommended Implementation Plan

### Phase 1: Critical Bugs (Immediate)
1. Fix indefinite end date bug
2. Add validation for recurring + no duration
3. Support duration_seconds
4. Add basic error logging

**Effort**: 2 hours
**Priority**: P0

### Phase 2: Validation Gates (High Priority)
1. Add pre-materialization validation
2. Check products exist
3. Verify categories valid
4. Detect price changes >50%
5. Email admin on validation failure

**Effort**: 8 hours
**Priority**: P1

### Phase 3: Change Detection (Medium Priority)
1. Hook product deletion
2. Hook category changes
3. Monitor price changes
4. Flag affected occurrences
5. Admin notifications

**Effort**: 12 hours
**Priority**: P2

### Phase 4: Dynamic Materialization (Long-term)
1. Store criteria instead of snapshots
2. Re-evaluate at materialization time
3. Adaptive product selection
4. Fallback strategies

**Effort**: 24 hours
**Priority**: P3 (Future)

### Phase 5: Management UI (Long-term)
1. Occurrence list view
2. Edit/cancel individual occurrences
3. Manual validation
4. Approval workflow

**Effort**: 16 hours
**Priority**: P3 (Future)

---

## Immediate Recommendations

For production use RIGHT NOW:

### 1. **Add Limits**
```php
// In validator
if ( $is_recurring ) {
    $max_duration = 3; // months

    if ( $duration_months > $max_duration ) {
        $errors->add(
            'recurring_too_long',
            sprintf(
                'Recurring campaigns are limited to %d months to ensure data accuracy. ' .
                'For longer campaigns, create multiple shorter recurring campaigns.',
                $max_duration
            ),
            array( 'severity' => 'critical' )
        );
    }
}
```

### 2. **Add Warnings**
```php
// In UI (step-schedule.php)
<div class="scd-recurring-warning">
    <span class="dashicons dashicons-warning"></span>
    <strong>Important:</strong> Recurring campaigns use a snapshot of your current
    product catalog. If products are deleted or prices change significantly during
    the recurring period, future occurrences may need manual adjustment.
</div>
```

### 3. **Add Validation**
```php
// Before materialization
public function materialize_occurrence( $parent_id, $occurrence_number ) {
    // ... existing code ...

    // Validate before materializing
    $validation = $this->validate_materialization( $parent_campaign, $occurrence );

    if ( is_wp_error( $validation ) ) {
        $this->logger->error( 'Occurrence validation failed', [
            'parent_id' => $parent_id,
            'occurrence' => $occurrence_number,
            'errors' => $validation->get_error_messages()
        ] );

        // Email admin
        $this->notify_admin_validation_failure( $parent_id, $occurrence_number, $validation );

        // Mark as failed
        $this->cache->mark_failed( $occurrence['id'], $validation->get_error_message() );

        return;
    }

    // Continue with materialization...
}
```

### 4. **Document Limitations**
Create user-facing documentation explaining:
- Recurring campaigns work best for 1-3 months
- Product changes require manual review
- Longer campaigns need periodic validation
- Best practices for recurring campaigns

---

## Conclusion

**The user's concerns are 100% valid and reveal fundamental architectural issues.**

The current recurring system is:
- ✅ Good for short-term (1-3 month) campaigns with stable products
- ⚠️ Risky for medium-term (3-6 month) campaigns
- ❌ Not viable for long-term (6-12 month) campaigns

**Immediate Action Required**:
1. Fix critical bugs (indefinite end date)
2. Add validation gates
3. Limit recurring duration to 3-6 months
4. Add warnings about data changes
5. Implement basic pre-materialization validation

**Long-term**:
- Redesign with dynamic materialization
- Add change detection
- Build occurrence management UI
- Implement approval workflows

---

**Analysis By**: Claude Code
**Date**: 2025-11-16
**Status**: 🔴 CRITICAL ARCHITECTURAL ISSUES IDENTIFIED
**Recommendation**: Implement Phase 1-2 immediately, plan Phase 3-5 for future releases
