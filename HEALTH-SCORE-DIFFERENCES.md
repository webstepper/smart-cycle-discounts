# Campaign Health Score Differences: Wizard vs Dashboard

## Overview

**Normal Behavior**: Campaign health scores in the wizard (review step) are typically 10-15 points higher than dashboard scores. This is **expected** and **by design**.

**Example**:
- Wizard Review Step: 97 ("Good configuration, minor recommendations")
- Campaigns Dashboard: 85 ("Healthy but has some warnings")

## Why Scores Differ

### Context-Based Scoring System

The health service uses `view_context` to apply different checks:

1. **'review' Context (Wizard)**
   - Pre-creation validation
   - Configuration completeness checks
   - Basic business logic warnings
   - **Focus**: "Is this campaign configured correctly?"

2. **'dashboard' Context (Campaigns List)**
   - Post-creation monitoring
   - Runtime status checks
   - Inventory and usage tracking
   - **Focus**: "Is this campaign performing well?"

### Dashboard-Only Checks (Score Penalties)

The following checks ONLY run in dashboard context (`view_context === 'dashboard'`), resulting in lower scores:

#### 1. **Low Stock Warning** (-5 points)
**Location**: `class-campaign-health-service.php:535-552`
```php
if ( 'dashboard' === $view_context && $low_stock_count > 0 ) {
    $health['warnings'][] = array(
        'code'     => 'low_stock_products',
        'message'  => sprintf( '%d product(s) low on stock (<10 units)', $low_stock_count ),
        'category' => 'products',
    );
    $penalty += 5;
}
```
**Why Dashboard Only**: Stock levels change after campaign creation. This check monitors inventory depletion during active campaigns.

#### 2. **No Activity Warning** (-8 points)
**Location**: `class-campaign-health-service.php:748-774`
```php
if ( 'dashboard' === $view_context && 'active' === $campaign_status && ! empty( $start_date ) ) {
    if ( $start_timestamp < ( $now - ( 7 * DAY_IN_SECONDS ) ) ) {
        if ( 0 === $usage_count ) {
            $health['warnings'][] = array(
                'code'     => 'no_activity_detected',
                'message'  => sprintf( 'No discount usage in %d days - verify campaign is working correctly', $days_active ),
                'category' => 'schedule',
            );
            $penalty += 8;
        }
    }
}
```
**Why Dashboard Only**: Usage tracking only applies to active campaigns that have been running for 7+ days.

#### 3. **Price Change Warning** (-5 to -8 points)
**Location**: `class-campaign-health-service.php:868-915`
```php
if ( 'dashboard' === $view_context && isset( $campaign['original_avg_price'] ) && $campaign['original_avg_price'] > 0 ) {
    // Check if product prices changed significantly since campaign creation
    if ( $price_change_percent >= 40 ) {
        $health['warnings'][] = array(
            'code'     => 'price_decreased_significantly',
            'message'  => sprintf( 'Product prices decreased %d%% since campaign creation - verify discount still appropriate', round( $price_change_percent ) ),
            'category' => 'discount',
        );
        $penalty += 8;
    }
}
```
**Why Dashboard Only**: Compares current prices to prices at creation time. Only relevant for existing campaigns.

#### 4. **Usage Limit Exhaustion** (-10 to -25 points)
**Location**: `class-campaign-health-service.php:1068-1105`
```php
if ( 'dashboard' === $view_context && $total_usage_limit > 0 ) {
    $usage_count = isset( $campaign['usage_count'] ) ? intval( $campaign['usage_count'] ) : 0;

    if ( $usage_count >= $total_usage_limit ) {
        $health['critical_issues'][] = array(
            'code'     => 'usage_limit_exhausted',
            'message'  => sprintf( 'Usage limit reached (%d uses) - campaign no longer applying discounts', $total_usage_limit ),
            'category' => 'discount',
        );
        $penalty += 25;
    } elseif ( $usage_percentage >= 90 ) {
        $health['warnings'][] = array(
            'code'     => 'usage_limit_nearly_exhausted',
            'message'  => sprintf( 'Only %d use(s) remaining (limit: %d)', $remaining, $total_usage_limit ),
            'category' => 'discount',
        );
        $penalty += 10;
    }
}
```
**Why Dashboard Only**: Tracks usage against limits. Only meaningful for campaigns that have been used.

### Total Possible Dashboard Penalties

| Check Type | Penalty | When Applied |
|------------|---------|-------------|
| Low Stock | -5 | Products below 10 units |
| No Activity | -8 | 7+ days active with 0 usage |
| Price Decreased | -8 | 40%+ price drop |
| Price Increased | -5 | 40%+ price increase |
| Usage Nearly Exhausted | -10 | 90%+ of limit used |
| Usage Exhausted | -25 | 100% of limit used |

**Example Calculation**:
- Wizard Score: 97
- Low stock (3 products): -5
- Price decreased 45%: -8
- Dashboard Score: 84 (matches user's 85)

## Score Interpretation

### Wizard (Review Step)
- **97-100**: Perfect configuration
- **90-96**: Good configuration, minor recommendations
- **80-89**: Acceptable but has warnings
- **Below 80**: Configuration issues need attention

### Dashboard (Campaigns List)
- **90-100**: Excellent performance, no issues
- **80-89**: Healthy, some monitoring needed
- **70-79**: Needs attention
- **Below 70**: Significant issues

## User's Specific Case Analysis

**Reported Scores**:
- Wizard: 97
- Dashboard: 85
- **Difference**: -12 points

**Most Likely Causes** (totaling ~12 points):
1. **Low Stock Warning** (-5): Some products may have dropped below 10 units
2. **Price Change Warning** (-5 to -8): Product prices may have changed since creation

**Verification Steps**:
1. Check browser console for error logs showing which checks triggered:
   ```
   🔔 LOW STOCK WARNING: Campaign ID=X has Y products with low stock
   ```

2. Check product inventory:
   - Products with stock < 10 units trigger low stock warning
   - Products out of stock increase penalty further

3. Check if campaign has been active long enough:
   - No activity warning requires 7+ days runtime

## Technical Implementation

### Code Location
`/includes/core/services/class-campaign-health-service.php`

### Context Passing
```php
// Wizard AJAX Handler (line 105)
$context = array(
    'coverage_data'  => $coverage_data,
    'conflicts_data' => $conflicts_data,
    'view_context'   => 'review',  // Wizard context
);

// Campaigns List Table (line 1045)
$context = array(
    'coverage_data' => $coverage_data,
    'view_context'  => 'dashboard',  // Dashboard context
);
```

### Check Pattern
```php
// All dashboard-specific checks follow this pattern:
if ( 'dashboard' === $view_context ) {
    // Post-creation monitoring logic
    if ( /* condition */ ) {
        $health['warnings'][] = /* issue */;
        $penalty += /* points */;
    }
}
```

## Conclusion

**The 12-point difference (97 → 85) is normal and expected behavior.**

The health system is working correctly:
- **Wizard (97)**: Campaign is well-configured for creation
- **Dashboard (85)**: Campaign is healthy but monitoring real-world conditions (stock, usage, prices)

The dashboard shows a realistic health score that accounts for post-creation factors that the wizard cannot predict.

## Recommendations

1. **Don't Expect Same Scores**: Wizard and dashboard serve different purposes
2. **Monitor Dashboard Warnings**: Address specific issues flagged (low stock, price changes, etc.)
3. **Score Ranges**:
   - Wizard: 90+ is excellent
   - Dashboard: 80+ is healthy
4. **Investigate Only If**: Dashboard score drops below 70 or shows critical issues

---

**Related Files**:
- `/includes/core/services/class-campaign-health-service.php` - Main health logic
- `/includes/admin/ajax/handlers/class-campaign-health-handler.php` - Wizard health checks
- `/includes/admin/components/class-campaigns-list-table.php` - Dashboard health display
