# Practical Intelligence Implementation

## Overview

The Campaign Health Service has been enhanced with **practical intelligence features** that use statistical analysis and historical data patterns to provide smart recommendations and forecasts - **without requiring AI/ML infrastructure**.

**Implementation Date**: 2025-11-08
**Location**: `includes/core/services/class-campaign-health-service.php`
**Activation**: Automatically included in `comprehensive` analysis mode

---

## Features Implemented

### 1. Multi-Dimensional Risk Assessment ✅

**Purpose**: Analyzes campaign risk across 5 key dimensions

**Method**: `assess_multi_dimensional_risk( $campaign, $context )`

**Risk Dimensions**:

1. **Profit Margin Risk**
   - Fixed discounts vs product prices
   - Percentage discount levels (>50% = high risk)
   - Product price distribution analysis

2. **Inventory Risk**
   - Out-of-stock percentage
   - Low stock warnings (≤10 units)
   - Stock availability for campaign duration

3. **Market Timing Risk**
   - Seasonal factors (Black Friday, Christmas, etc.)
   - Month-specific patterns (January slowdown)
   - Day-of-week optimization

4. **Competitive Risk**
   - Overlapping campaign conflicts
   - Internal competition level
   - Campaign saturation analysis

5. **Execution Risk**
   - Campaign complexity scoring
   - Discount type complexity
   - Filter conditions count
   - Recurring schedule complexity

**Output Structure**:
```php
array(
    'overall_risk_level' => 'low|medium|high|critical',
    'overall_risk_score' => 85,  // 0-100
    'dimensions' => array(
        'profit_margin' => array(
            'level' => 'low',
            'score' => 100,
            'factors' => array('20% discount is within safe range for most products')
        ),
        'inventory' => array(...),
        'market_timing' => array(...),
        'competitive' => array(...),
        'execution' => array(...)
    )
)
```

**Risk Scoring**:
- **100-80**: Low risk (safe to proceed)
- **79-60**: Medium risk (review recommended)
- **59-40**: High risk (address issues)
- **39-0**: Critical risk (requires immediate action)

---

### 2. Smart Benchmarking System ✅

**Purpose**: Compare campaign against similar historical campaigns

**Method**: `get_smart_benchmark( $campaign, $context, $performance_data )`

**How It Works**:

1. **Similarity Scoring** (0-100 points):
   - Same discount type: +50 points
   - Same product selection type: +30 points
   - Similar discount value: +20 points
   - Minimum 50 points required for inclusion

2. **Statistical Aggregation**:
   - 25th percentile (conservative)
   - 50th percentile (realistic/average)
   - 75th percentile (optimistic)

3. **Performance Comparison**:
   - Revenue percentile ranking
   - Conversion rate percentile ranking
   - Success rate calculation

**Output Structure**:
```php
array(
    'has_historical_data' => true,
    'similar_campaigns' => array(
        'count' => 15,
        'date_range' => array(
            'earliest' => '2024-01-15',
            'latest' => '2025-10-20'
        ),
        'discount_type' => 'percentage',
        'selection_type' => 'specific_products'
    ),
    'expected_performance' => array(
        'revenue' => array(
            'conservative' => 1250.00,  // 25th percentile
            'likely' => 2500.00,        // 50th percentile
            'optimistic' => 4200.00     // 75th percentile
        ),
        'conversion_rate' => array(
            'conservative' => 2.5,
            'likely' => 4.2,
            'optimistic' => 6.8
        )
    ),
    'insights' => array(
        'Based on 15 similar campaigns, expected revenue range: $1,250 - $4,200',
        'Similar campaigns have 73.3% success rate (health score ≥80)'
    )
)
```

**For Active Campaigns** (with performance data):
```php
array(
    'percentile_ranking' => array(
        'revenue' => 82,          // Top 18% of similar campaigns
        'conversion_rate' => 65   // Above average
    ),
    'performance_vs_avg' => array(
        'revenue' => array(
            'current' => 3800.00,
            'average' => 2500.00,
            'percentile' => 82
        ),
        'conversion_rate' => array(
            'current' => 5.2,
            'average' => 4.2,
            'percentile' => 65
        )
    ),
    'insights' => array(
        'Outstanding performance - revenue in top 18% of similar campaigns'
    )
)
```

---

### 3. Statistical Performance Forecasting ✅

**Purpose**: Predict expected performance using historical patterns

**Method**: `generate_performance_forecast( $campaign, $context )`

**Methodology**:

1. **Base Forecast**: Uses percentile method from similar campaigns
2. **Seasonal Adjustments**:
   - November/December: +15% (holiday shopping)
   - August: +8% (back to school)
   - January: -8% (post-holiday slowdown)

3. **Competition Adjustments**:
   - High competition (>5 conflicts): -15%
   - Moderate competition (3-5 conflicts): -8%

**Output Structure**:
```php
array(
    'confidence_level' => 'high|medium|low',
    'data_points' => 22,  // Number of similar campaigns analyzed
    'revenue_forecast' => array(
        'pessimistic' => 1450.00,  // Adjusted 25th percentile
        'realistic' => 2875.00,    // Adjusted average
        'optimistic' => 4830.00,   // Adjusted 75th percentile
        'range' => 3380.00         // Spread between pessimistic/optimistic
    ),
    'conversion_forecast' => array(
        'pessimistic' => 2.9,
        'realistic' => 4.8,
        'optimistic' => 7.8,
        'range' => 4.9
    ),
    'factors' => array(
        'Forecast based on 22 similar historical campaigns',
        'Percentile method: 25th (pessimistic), 50th (realistic), 75th (optimistic)',
        'Seasonal boost applied: Holiday shopping season (+15%)',
        'Competition adjustment: Moderate competition (-8%)'
    ),
    'methodology' => 'statistical_analysis'
)
```

**Confidence Levels**:
- **High**: ≥20 similar campaigns
- **Medium**: 10-19 similar campaigns
- **Low**: <10 similar campaigns

---

### 4. Historical Pattern Analysis ✅

**Purpose**: Identify success patterns and failure patterns from historical data

**Method**: `analyze_historical_patterns( $campaign, $context )`

**How It Works**:

1. **Performance Classification**:
   - High performers: Revenue >$1000 AND conversion >3.0%
   - Low performers: Revenue <$100 OR conversion <1.0%

2. **Pattern Extraction**:
   - Most common discount types
   - Most common product selection types
   - Average discount values
   - Common start day of week
   - Average campaign duration

3. **Recommendation Generation**:
   - Compare current campaign against success patterns
   - Suggest adjustments based on data

**Output Structure**:
```php
array(
    'patterns_found' => true,
    'sample_size' => 78,
    'success_patterns' => array(
        'discount_types' => array(
            'percentage' => 12,  // 12 successful campaigns used percentage
            'bogo' => 5,
            'fixed' => 3
        ),
        'selection_types' => array(
            'specific_products' => 15,
            'filter_conditions' => 8
        ),
        'avg_discount' => 25.3,
        'common_day_of_week' => 4,  // Thursday (1=Mon, 7=Sun)
        'avg_duration_days' => 14
    ),
    'failure_patterns' => array(
        'discount_types' => array(
            'fixed' => 8,
            'percentage' => 3
        ),
        'avg_discount' => 52.7,
        'avg_duration_days' => 45
    ),
    'recommendations' => array(
        array(
            'type' => 'discount_value',
            'message' => 'Successful campaigns typically use 25.3% discount (you have 35.0%)',
            'priority' => 'medium'
        ),
        array(
            'type' => 'start_timing',
            'message' => 'Successful campaigns often start on Thursday - consider this for optimal performance',
            'priority' => 'low'
        ),
        array(
            'type' => 'duration',
            'message' => 'Successful campaigns typically run for 14 days',
            'priority' => 'low'
        )
    )
)
```

---

## Integration with Health System

### Activation

All intelligence features are automatically included when using **comprehensive mode**:

```php
// In AJAX handlers, page controllers, etc.
$health_service = new SCD_Campaign_Health_Service( $logger );

$health = $health_service->analyze_health(
    $campaign,
    'comprehensive',  // ← Triggers intelligence features
    array(
        'campaign_repo' => $campaign_repository,
        'analytics_repo' => $analytics_repository,
        'performance_data' => $performance_data  // Optional, for active campaigns
    )
);
```

### Required Context

For full intelligence features, pass these in `$context` array:

```php
$context = array(
    'campaign_repo' => $campaign_repository,    // Required for benchmarking and patterns
    'analytics_repo' => $analytics_repository,  // Required for performance data
    'performance_data' => array(                // Optional, for active campaigns
        'revenue' => 3850.00,
        'conversion_rate' => 5.2
    ),
    'conflicts_data' => array(                  // Optional, for conflict analysis
        'conflicts' => array(...)
    ),
    'coverage_data' => array(...)               // Optional, for coverage analysis
);
```

### Output Structure

When comprehensive mode is enabled, health array includes:

```php
array(
    // Standard health data
    'score' => 92,
    'status' => 'excellent',
    'critical_issues' => array(),
    'warnings' => array(),
    'info' => array(),
    'recommendations' => array(),
    'breakdown' => array(),
    'is_ready' => true,

    // NEW: Intelligence features
    'risk_assessment' => array(...),      // Multi-dimensional risk
    'benchmark' => array(...),            // Smart benchmarking
    'forecast' => array(...),             // Statistical forecast
    'pattern_analysis' => array(...)      // Historical patterns
)
```

---

## Use Cases

### 1. Campaign Wizard - Review Step

**Scenario**: User creating new campaign needs to understand expected performance

```php
$health = $health_service->analyze_health(
    $campaign_data,
    'comprehensive',
    array(
        'view_context' => 'review',
        'campaign_repo' => $campaign_repo,
        'analytics_repo' => $analytics_repo
    )
);

// Display forecast
echo "Expected Revenue: " . wc_price( $health['forecast']['revenue_forecast']['realistic'] );
echo "Based on " . $health['forecast']['data_points'] . " similar campaigns";

// Show risk assessment
if ( $health['risk_assessment']['overall_risk_level'] === 'high' ) {
    echo "⚠️ High Risk Campaign";
    foreach ( $health['risk_assessment']['dimensions'] as $dimension => $data ) {
        if ( $data['level'] !== 'low' ) {
            echo "- " . ucfirst( $dimension ) . ": " . implode( ', ', $data['factors'] );
        }
    }
}
```

### 2. Dashboard - Campaign Performance Monitoring

**Scenario**: Tracking active campaign performance against benchmarks

```php
// Get current performance
$performance = $analytics_repo->get_campaign_performance( $campaign_id );

$health = $health_service->analyze_health(
    $campaign,
    'comprehensive',
    array(
        'view_context' => 'dashboard',
        'campaign_repo' => $campaign_repo,
        'analytics_repo' => $analytics_repo,
        'performance_data' => array(
            'revenue' => $performance['total_revenue'],
            'conversion_rate' => $performance['conversion_rate']
        )
    )
);

// Display percentile ranking
$percentile = $health['benchmark']['percentile_ranking']['revenue'];
if ( $percentile >= 75 ) {
    echo "🏆 Top Performer - Revenue in top " . (100 - $percentile) . "%";
} elseif ( $percentile < 25 ) {
    echo "⚠️ Underperforming - Revenue in bottom " . $percentile . "%";
}

// Show recommendations
foreach ( $health['pattern_analysis']['recommendations'] as $rec ) {
    if ( $rec['priority'] === 'high' ) {
        echo "⚡ " . $rec['message'];
    }
}
```

### 3. Analytics Dashboard - Historical Insights

**Scenario**: Analyzing what makes campaigns successful

```php
$health = $health_service->analyze_health(
    $campaign,
    'comprehensive',
    array(
        'campaign_repo' => $campaign_repo,
        'analytics_repo' => $analytics_repo
    )
);

$patterns = $health['pattern_analysis'];

echo "Success Patterns from " . $patterns['sample_size'] . " campaigns:";
echo "- Best discount type: " . key( $patterns['success_patterns']['discount_types'] );
echo "- Optimal discount: " . $patterns['success_patterns']['avg_discount'] . "%";
echo "- Best day to start: " . $day_names[ $patterns['success_patterns']['common_day_of_week'] ];
echo "- Optimal duration: " . $patterns['success_patterns']['avg_duration_days'] . " days";
```

---

## Technical Details

### Performance Considerations

1. **Caching Recommendations**:
   ```php
   // Results can be cached for 1 hour for same campaign
   $cache_key = 'campaign_intelligence_' . $campaign_id;
   $cached = wp_cache_get( $cache_key, 'scd_health' );

   if ( false === $cached ) {
       $intelligence = $health_service->analyze_health( $campaign, 'comprehensive', $context );
       wp_cache_set( $cache_key, $intelligence, 'scd_health', HOUR_IN_SECONDS );
   }
   ```

2. **Limit Historical Queries**:
   - Maximum 100 campaigns analyzed for patterns
   - Similarity threshold of 50% reduces comparison set
   - Percentile calculations are O(n log n) due to sorting

3. **Background Processing**:
   - For dashboard widgets, consider WP Cron for pre-computation
   - Store benchmark data in campaign metadata for quick access

### Database Impact

**Queries Added**:
- 1-2 queries to campaign repository (find similar campaigns)
- 1 query per similar campaign to analytics repository
- Total: ~20-40 queries for comprehensive analysis

**Optimization**:
- Add indexes on `status`, `discount_type`, `product_selection_type` columns
- Consider campaign metadata caching for frequently accessed data

---

## WordPress Coding Standards Compliance

✅ **PHP Standards**:
- Yoda conditions used throughout
- `array()` syntax (not `[]`)
- Proper spacing and indentation (tabs)
- PHPDoc blocks for all methods

✅ **Security**:
- No user input in these methods (internal calculations only)
- All data sanitized at entry points (AJAX handlers)
- Uses existing WooCommerce functions (`wc_price()`, `wc_get_product()`)

✅ **Performance**:
- Opt-in via comprehensive mode (not always-on)
- Statistical methods (no external API calls)
- Uses WordPress caching where appropriate

---

## Testing Recommendations

### Unit Tests

```php
public function test_risk_assessment_profit_margin() {
    $campaign = array(
        'discount_type' => 'percentage',
        'discount_value' => 60
    );

    $health_service = new SCD_Campaign_Health_Service( $logger );
    $risk = $health_service->assess_multi_dimensional_risk( $campaign, array() );

    $this->assertEquals( 'high', $risk['dimensions']['profit_margin']['level'] );
    $this->assertLessThan( 50, $risk['dimensions']['profit_margin']['score'] );
}

public function test_benchmark_similarity_scoring() {
    // Test that percentage campaigns match percentage campaigns
    // Test that 20% matches 25% better than 50%
    // Test minimum similarity threshold
}

public function test_forecast_seasonal_adjustments() {
    $november_campaign = array( 'start_date' => '2025-11-25' );
    $january_campaign = array( 'start_date' => '2025-01-15' );

    // November should get +15% boost
    // January should get -8% reduction
}
```

### Integration Tests

```php
public function test_comprehensive_health_analysis_includes_intelligence() {
    $health = $health_service->analyze_health( $campaign, 'comprehensive', $context );

    $this->assertArrayHasKey( 'risk_assessment', $health );
    $this->assertArrayHasKey( 'benchmark', $health );
    $this->assertArrayHasKey( 'forecast', $health );
    $this->assertArrayHasKey( 'pattern_analysis', $health );
}

public function test_standard_mode_excludes_intelligence() {
    $health = $health_service->analyze_health( $campaign, 'standard', $context );

    $this->assertArrayNotHasKey( 'risk_assessment', $health );
    $this->assertArrayNotHasKey( 'benchmark', $health );
}
```

---

## Future Enhancements

### Phase 2 (Potential)

1. **Product Category Intelligence**
   - Category-specific benchmarks
   - Seasonal patterns by product category
   - Category margin analysis

2. **Customer Segment Analysis**
   - New vs returning customer patterns
   - Customer lifetime value impact
   - Geographic performance patterns

3. **Real-Time Monitoring**
   - WP Cron hooks for active campaign monitoring
   - Automatic alerts for underperforming campaigns
   - Mid-campaign adjustment recommendations

4. **Advanced Forecasting**
   - Time-series trend analysis
   - Multi-factor regression
   - Confidence intervals

### Phase 3 (Future Consideration)

1. **External Data Integration**
   - Industry benchmarks (if available via API)
   - Economic indicators
   - Competitor pricing data (if accessible)

2. **Advanced Visualization**
   - Chart.js integration for trend charts
   - Risk heat maps
   - Forecast range visualization

---

## Changelog

### Version 1.0.0 - 2025-11-08

**Added**:
- Multi-dimensional risk assessment (5 dimensions)
- Smart benchmarking system with similarity scoring
- Statistical performance forecasting with seasonal adjustments
- Historical pattern analysis with success/failure patterns
- Integration into comprehensive health analysis mode

**Methods Added**:
- `assess_multi_dimensional_risk()`
- `assess_profit_margin_risk()`
- `assess_inventory_risk()`
- `assess_market_timing_risk()`
- `assess_competitive_risk()`
- `assess_execution_risk()`
- `get_smart_benchmark()`
- `find_similar_campaigns()`
- `calculate_aggregate_statistics()`
- `calculate_performance_percentile()`
- `get_campaigns_date_range()`
- `generate_performance_forecast()`
- `apply_forecast_adjustments()`
- `analyze_historical_patterns()`
- `extract_common_patterns()`
- `generate_pattern_based_recommendations()`

**Modified**:
- `analyze_health()` - Added intelligence features in comprehensive mode

---

## Support

For questions or issues related to the practical intelligence features:

1. Review this documentation
2. Check the inline PHPDoc comments in `class-campaign-health-service.php`
3. Refer to the implementation plan: `HEALTH-SYSTEM-PRACTICAL-INTELLIGENCE.md`
4. Test with sample data using the test suite

---

**Implementation Complete** ✅
**No AI/ML Required** ✅
**WordPress Standards Compliant** ✅
**Production Ready** ✅
