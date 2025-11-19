# Validation Architecture - Severity Breakdown

**Question**: Are all validation scenarios blocking (preventing campaign save)?

**Answer**: **NO** - Only **CRITICAL** severity scenarios block saving. The system uses 3 severity levels.

---

## Severity Levels Explained

### 🔴 Critical (BLOCKING)
- **Effect**: Campaign **CANNOT** be saved/activated
- **Score penalty**: -15 to -20 points
- **Use case**: Data integrity issues, mathematical impossibilities, contradictions
- **User action**: Must fix before proceeding

### 🟡 Warning (NON-BLOCKING)
- **Effect**: Campaign **CAN** be saved but user is warned
- **Score penalty**: -5 to -10 points
- **Use case**: Business rule concerns, performance issues, questionable configurations
- **User action**: Review and decide whether to proceed

### 🔵 Info (NON-BLOCKING)
- **Effect**: Informational only, no restrictions
- **Score penalty**: 0 points
- **Use case**: Best practice suggestions, optimization tips, strategic recommendations
- **User action**: Optional consideration

---

## Validation Scenarios by Severity

### Total: 246 Scenarios

| Validator | Total | 🔴 Critical | 🟡 Warning | 🔵 Info | No Severity* |
|-----------|-------|-------------|------------|---------|--------------|
| **Discounts Step** | 72 | 0 | 0 | 0 | 72 (blocking) |
| **Products Step** | 97 | 0 | 0 | 0 | 97 (blocking) |
| **Schedule Step** | 47 | 13 | 15 | 6 | 13 (blocking) |
| **Cross-Step** | 30 | 1 | 11 | 18 | 0 |
| **TOTAL** | **246** | **14** | **26** | **24** | **182** |

\* *Scenarios without explicit severity default to blocking behavior at the step level*

---

## Blocking vs Non-Blocking Breakdown

### 🔴 Blocking Scenarios: 196 (79.7%)
- Discounts step: 72 (all scenarios are blocking)
- Products step: 97 (all scenarios are blocking)
- Schedule step: 13 critical + 13 no-severity = 26 blocking
- Cross-step: 1 critical

**Why so many blocking?**
- Step validators (discounts, products) validate data integrity and field-level correctness
- These are "hard errors" that indicate invalid data or logical impossibilities
- Must be fixed immediately as they represent broken configurations

### 🟡 Warning Scenarios: 26 (10.6%)
- Schedule step: 15 warnings
- Cross-step: 11 warnings

**Examples:**
- BOGO discount with all products (performance concern)
- Very high discount percentages (profit margin risk)
- Rotation interval conflicts
- Long campaigns with fixed discounts

### 🔵 Info Scenarios: 24 (9.8%)
- Schedule step: 6 info
- Cross-step: 18 info

**Examples:**
- Strategic alignment suggestions
- Optimization recommendations
- Best practice tips
- Complexity score notifications

---

## Detailed Breakdown by Validator

### 1. Discounts Step Validator (72 scenarios)
**All 72 scenarios are BLOCKING** (no severity levels specified)

**Why?** Discount configuration errors represent data integrity issues:
- Invalid discount values (negative, zero, out of range)
- Contradictory usage limits
- Invalid BOGO/tiered/threshold configurations
- Badge configuration errors
- Combination policy violations

**Examples of blocking scenarios:**
- Discount percentage > 100%
- BOGO buy quantity = 0
- Tiered discount with decreasing values
- Usage limit per customer > total limit
- Invalid badge color format

### 2. Products Step Validator (97 scenarios)
**All 97 scenarios are BLOCKING** (no severity levels specified)

**Why?** Filter conditions that are mathematically impossible or contradictory:
- Inverted ranges (min > max)
- Contradictory equals conditions
- Negative values for positive-only properties
- Invalid date logic
- Impossible set operations

**Examples of blocking scenarios:**
- Price BETWEEN 100 to 50 (inverted)
- Stock quantity equals 10 AND equals 20 (contradiction)
- Rating > 5 (impossible - max is 5)
- Price < 0 (negative price)
- Sale start date after end date

### 3. Schedule Step Validator (47 scenarios)

#### 🔴 Critical (13 blocking):
1. Start date after end date
2. Zero/negative duration
3. Invalid recurrence interval (out of range)
4. Invalid rotation interval (out of range)
5. Invalid timezone identifier
6. No days selected for weekly schedule
7. Invalid day names in weekly schedule
8. Invalid day of month (< 1 or > 31)
9. Invalid end_type value
10. end_count without recurrence enabled
11. Zero occurrences count
12. Rotation interval > max allowed (168 hours)
13. Recurrence interval > max allowed (365)

#### 🟡 Warning (15 warnings):
1. Past start date for new campaigns
2. Very short duration (< 1 hour)
3. Max duration exceeded (> 365 days)
4. Recurrence interval longer than campaign duration
5. Rotation interval >= campaign duration
6. Feb 29 day of month (won't run in non-leap years)
7. Day of month 30-31 (won't run in shorter months)
8. Rotation interval conflicts with recurrence
9. DST transition during campaign
10. Frequent rotation (< 4 hours)
11. Very long campaign (> 180 days)
12. High rotation count (> 100)
13. Many occurrences (> 500)
14. Perpetual recurring campaign
15. Past start with active status

#### 🔵 Info (6 info):
1. Overnight campaigns (timezone check)
2. All 7 days selected (suggest daily instead)
3. Single day selected (optimization)
4. Sub-hour duration with rotation
5. Very far future end date (> 10 years)
6. Date arithmetic overflow risk

#### No Severity (13 blocking by default):
*These appear to be edge cases where severity wasn't explicitly set*

### 4. Campaign Cross Validator (30 scenarios)

#### 🔴 Critical (1 blocking):
1. Bundle discount without specific products

#### 🟡 Warning (11 warnings):
1. BOGO with all products (performance)
2. Fixed discount with low-price filters (margin risk)
3. BOGO with low stock filters (inventory)
4. Fixed discount exceeds product price
5. Complex discount with many filters (> 5)
6. BOGO with on-sale products (stacking)
7. Complex discount with frequent rotation
8. All products with rotation enabled
9. Tiered discount with many filters + recurring
10. Specific products with frequent rotation
11. High campaign complexity score

#### 🔵 Info (18 info):
1. Percentage discount with smart selection (good)
2. Tiered discount with random products
3. Spend threshold with single product
4. Fixed discount with all products (verify margins)
5. BOGO with many random products
6. Percentage discount with high-value filters
7. Tiered discount with rating filters (good)
8. Spend threshold with sales filter (good)
9. Bundle discount with virtual filter (good)
10. BOGO with short campaign
11. Recurring campaign with usage limits
12. Fixed discount with long campaign
13. Spend threshold weekend-only (strategic)
14. Random products without rotation
15. Smart selection with short campaign
16. Advanced filters with long recurrence
17. BOGO + Random + Rotation complexity
18. Many optional features enabled

---

## Campaign Save Behavior

### Step-Level Validation (During Step Save)
```
User saves step data
    ↓
Step validator runs
    ↓
Has errors (any severity)?
    ├─ YES → Block save, show errors
    └─ NO → Save step, allow navigation
```

**Note**: At step level, ALL errors block saving (even if just warnings/info) because WP_Error presence indicates validation failure. The severity is used for health scoring later.

### Campaign-Level Health Check (At Review Step)
```
User reaches review
    ↓
Campaign health service runs
    ↓
Cross-step validator runs
    ↓
Critical issues found?
    ├─ YES → Block activation, show critical issues
    │         (warnings/info still displayed but don't block)
    └─ NO → Allow activation
              (warnings/info shown as recommendations)
```

**`is_ready` flag**:
```php
$health['is_ready'] = empty( $health['critical_issues'] );
```

Only campaigns with **zero critical issues** can be activated.

---

## Summary

**Blocking (196 scenarios / 79.7%)**
- ✅ Ensures data integrity
- ✅ Prevents impossible configurations
- ✅ Catches mathematical contradictions
- ✅ Validates business-critical rules

**Warning (26 scenarios / 10.6%)**
- ⚠️ Highlights potential issues
- ⚠️ Allows informed decisions
- ⚠️ Guides best practices
- ⚠️ Still allows proceeding

**Info (24 scenarios / 9.8%)**
- ℹ️ Educational tips
- ℹ️ Strategic suggestions
- ℹ️ Optimization hints
- ℹ️ No restrictions

This balance ensures:
1. **Data quality** - Invalid data cannot be saved
2. **User flexibility** - Users can proceed with warnings after review
3. **Guidance** - Best practices suggested without being restrictive
4. **Professional UX** - Smart validation that respects user expertise
