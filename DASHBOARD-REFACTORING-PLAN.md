# Dashboard Service Refactoring Plan

## Executive Summary

The current `class-dashboard-service.php` has grown to **1,615 lines** and violates the Single Responsibility Principle. Before implementing the new timeline feature, we must refactor it into smaller, focused services.

**Goal**: Split Dashboard Service into 3 focused services following SOLID principles while maintaining backward compatibility.

---

## Current State Analysis

### File: `includes/services/class-dashboard-service.php` (1,615 lines)

**Responsibility Breakdown**:

| Responsibility | Lines | Percentage | Methods |
|---------------|-------|------------|---------|
| **Campaign Suggestions Logic** | ~440 | 27% | `get_campaign_suggestions()`, `calculate_event_date()`, `calculate_suggestion_window()`, `format_suggestion()`, `enrich_suggestions_with_campaigns()`, `filter_weekend_sale_by_major_events()` |
| **Campaign Display Preparation** | ~340 | 21% | `get_recent_campaigns()`, `get_timeline_campaigns()`, `prepare_campaign_for_display()`, `calculate_time_data()`, `calculate_urgency_data()`, `format_status_data()` |
| **Campaign Health Mapping** | ~240 | 15% | `get_health_metrics()`, `map_health_scores()`, `calculate_health_score()` |
| **Core Dashboard Data** | ~190 | 12% | `get_dashboard_data()`, `get_wc_store_metrics()`, `get_total_campaigns()` |
| **Caching System** | ~220 | 14% | Cache helper methods |
| **Other Utilities** | ~185 | 11% | Misc helper methods |

**Problems**:
1. **God Class Anti-Pattern**: Single class has too many responsibilities
2. **Difficult Testing**: Hard to unit test individual concerns
3. **Future Scalability**: Adding timeline feature would push this to 2,000+ lines
4. **Tight Coupling**: Everything depends on one massive service

---

## Proposed Refactored Architecture

### Service Hierarchy

```
┌─────────────────────────────────────────────────────────────┐
│                  SCD_Dashboard_Service                       │
│                    (Orchestrator - 650 lines)                │
│                                                              │
│  - Dependency injection of sub-services                      │
│  - Public API delegation                                     │
│  - Backward compatibility layer                              │
│  - Core dashboard data assembly                              │
└──────────────────┬───────────────────────────────────────────┘
                   │
        ┌──────────┴──────────┬──────────────────────┐
        │                     │                      │
        ▼                     ▼                      ▼
┌──────────────────┐  ┌──────────────────┐  ┌─────────────────┐
│  Suggestions     │  │  Display         │  │  Health         │
│  Service         │  │  Service         │  │  Service        │
│  (450 lines)     │  │  (350 lines)     │  │  (240 lines)    │
│                  │  │                  │  │                 │
│  - Event dates   │  │  - Recent camps  │  │  - Metrics      │
│  - Windows       │  │  - Timeline      │  │  - Scores       │
│  - Formatting    │  │  - Preparation   │  │  - Mapping      │
└──────────────────┘  └──────────────────┘  └─────────────────┘
```

### File Structure

```
/includes/services/
├── class-dashboard-service.php              (REFACTORED - 650 lines)
├── class-campaign-suggestions-service.php   (NEW - 450 lines)
├── class-campaign-display-service.php       (NEW - 350 lines)
└── class-campaign-health-service.php        (ALREADY EXISTS - will use)
```

---

## Detailed Service Specifications

### 1. Dashboard Service (Orchestrator - 650 lines)

**File**: `includes/services/class-dashboard-service.php`

**Responsibilities**:
- Dependency injection and service orchestration
- Public API delegation to sub-services
- Core dashboard data assembly (`get_dashboard_data()`)
- WooCommerce store metrics (`get_wc_store_metrics()`)
- Caching layer for dashboard data
- Backward compatibility for existing API

**Constructor**:
```php
class SCD_Dashboard_Service {

    private $suggestions_service;
    private $display_service;
    private $health_service;
    private $campaign_repo;

    public function __construct(
        SCD_Campaign_Suggestions_Service $suggestions_service,
        SCD_Campaign_Display_Service $display_service,
        SCD_Campaign_Health_Service $health_service,
        SCD_Campaign_Repository $campaign_repo
    ) {
        $this->suggestions_service = $suggestions_service;
        $this->display_service = $display_service;
        $this->health_service = $health_service;
        $this->campaign_repo = $campaign_repo;
    }
}
```

**Public API (Delegation)**:
```php
// Delegate to Suggestions Service
public function get_campaign_suggestions() {
    return $this->suggestions_service->get_suggestions();
}

// Delegate to Display Service
public function get_recent_campaigns( $limit = 5 ) {
    return $this->display_service->get_recent_campaigns( $limit );
}

public function get_timeline_campaigns( $days = 30 ) {
    return $this->display_service->get_timeline_campaigns( $days );
}

// Delegate to Health Service
public function get_health_metrics() {
    return $this->health_service->get_health_metrics();
}
```

---

### 2. Campaign Suggestions Service (NEW - 450 lines)

**File**: `includes/services/class-campaign-suggestions-service.php`

**Responsibilities**:
- Generate campaign suggestions based on calendar events
- Calculate event dates for major retail events
- Determine optimal suggestion windows (lead time logic)
- Format suggestions for display
- Enrich suggestions with existing campaign data
- Filter weekend sales by major events

**Dependencies**:
```php
class SCD_Campaign_Suggestions_Service {

    private $campaign_repo;
    private $suggestions_registry;

    public function __construct(
        SCD_Campaign_Repository $campaign_repo,
        SCD_Campaign_Suggestions_Registry $suggestions_registry
    ) {
        $this->campaign_repo = $campaign_repo;
        $this->suggestions_registry = $suggestions_registry;
    }
}
```

**Public Methods**:
```php
/**
 * Get campaign suggestions for the dashboard
 *
 * @return array Campaign suggestions with enrichment data
 */
public function get_suggestions() {
    // Implementation moved from Dashboard Service
}

/**
 * Calculate event date for a specific year
 *
 * @param array $event Event definition
 * @param int   $year  Year to calculate for
 * @return int Unix timestamp
 */
public function calculate_event_date( array $event, int $year ) {
    // Implementation moved from Dashboard Service
}

/**
 * Calculate suggestion window (optimal creation window)
 *
 * @param array $event Event definition
 * @param int   $date  Event date timestamp
 * @return array Window start/end timestamps
 */
public function calculate_suggestion_window( array $event, int $date ) {
    // Implementation moved from Dashboard Service
}

/**
 * Format suggestion for display
 *
 * @param array $event Event definition
 * @param int   $date  Event date timestamp
 * @return array Formatted suggestion
 */
public function format_suggestion( array $event, int $date ) {
    // Implementation moved from Dashboard Service
}

/**
 * Enrich suggestions with existing campaign data
 *
 * @param array $suggestions Raw suggestions
 * @return array Enriched suggestions
 */
public function enrich_suggestions_with_campaigns( array $suggestions ) {
    // Implementation moved from Dashboard Service
}

/**
 * Filter weekend sale suggestions by major events
 *
 * @param array $suggestions All suggestions
 * @return array Filtered suggestions
 */
public function filter_weekend_sale_by_major_events( array $suggestions ) {
    // Implementation moved from Dashboard Service
}
```

**Cache Strategy**:
- 5-minute cache for suggestions list
- Invalidate on campaign save/delete hooks
- Cache key: `scd_campaign_suggestions_{hash}`

---

### 3. Campaign Display Service (NEW - 350 lines)

**File**: `includes/services/class-campaign-display-service.php`

**Responsibilities**:
- Fetch recent campaigns for dashboard
- Fetch timeline campaigns (date-filtered)
- Prepare campaigns for display (add calculated fields)
- Calculate time-related data (status, urgency)
- Format status data for UI

**Dependencies**:
```php
class SCD_Campaign_Display_Service {

    private $campaign_repo;

    public function __construct(
        SCD_Campaign_Repository $campaign_repo
    ) {
        $this->campaign_repo = $campaign_repo;
    }
}
```

**Public Methods**:
```php
/**
 * Get recent campaigns for dashboard display
 *
 * @param int $limit Number of campaigns to return
 * @return array Campaigns prepared for display
 */
public function get_recent_campaigns( $limit = 5 ) {
    // Implementation moved from Dashboard Service
}

/**
 * Get campaigns within timeline window
 *
 * @param int $days Number of days for timeline window
 * @return array Campaigns within timeline
 */
public function get_timeline_campaigns( $days = 30 ) {
    // Implementation moved from Dashboard Service
}

/**
 * Prepare campaign for display with calculated fields
 *
 * @param array $campaign Raw campaign data
 * @return array Campaign with display fields
 */
public function prepare_campaign_for_display( array $campaign ) {
    // Implementation moved from Dashboard Service
}

/**
 * Calculate time-related data (days until, status, etc.)
 *
 * @param array $campaign Campaign data
 * @return array Time calculation results
 */
private function calculate_time_data( array $campaign ) {
    // Implementation moved from Dashboard Service
}

/**
 * Calculate urgency data for campaign
 *
 * @param array $campaign Campaign data
 * @return array Urgency indicators
 */
private function calculate_urgency_data( array $campaign ) {
    // Implementation moved from Dashboard Service
}

/**
 * Format status data for UI display
 *
 * @param array $campaign Campaign data
 * @return array Formatted status
 */
private function format_status_data( array $campaign ) {
    // Implementation moved from Dashboard Service
}
```

**Cache Strategy**:
- 5-minute cache for recent campaigns
- Invalidate on campaign state changes
- Cache key: `scd_recent_campaigns_{limit}`

---

### 4. Campaign Health Service (EXISTING - Use as-is)

**File**: `includes/core/services/class-campaign-health-service.php`

**Status**: Already exists and is well-structured. Dashboard Service will simply delegate to it.

---

## Implementation Phases

### Phase 1: Extract Campaign Suggestions Service

**Steps**:
1. Create `includes/services/class-campaign-suggestions-service.php`
2. Copy these methods from Dashboard Service:
   - `get_campaign_suggestions()`
   - `calculate_event_date()`
   - `calculate_suggestion_window()`
   - `format_suggestion()`
   - `enrich_suggestions_with_campaigns()`
   - `filter_weekend_sale_by_major_events()`
3. Update method visibility (make appropriate methods private)
4. Add dependency injection for Campaign Repository
5. Implement caching within the service
6. Update Dashboard Service to delegate to new service

**Testing**:
```php
// Verify suggestions still work
$suggestions = $dashboard_service->get_campaign_suggestions();
// Should return same results as before refactoring
```

### Phase 2: Extract Campaign Display Service

**Steps**:
1. Create `includes/services/class-campaign-display-service.php`
2. Copy these methods from Dashboard Service:
   - `get_recent_campaigns()`
   - `get_timeline_campaigns()`
   - `prepare_campaign_for_display()`
   - `calculate_time_data()`
   - `calculate_urgency_data()`
   - `format_status_data()`
3. Update method visibility (make calculation methods private)
4. Add dependency injection for Campaign Repository
5. Implement caching within the service
6. Update Dashboard Service to delegate to new service

**Testing**:
```php
// Verify recent campaigns still work
$recent = $dashboard_service->get_recent_campaigns( 5 );
// Should return same results as before refactoring
```

### Phase 3: Update Service Container

**File**: `smart-cycle-discounts.php` (main plugin file)

**Changes**:
```php
// In main plugin initialization
private function init_services() {
    // Register new services
    $this->container->register( 'campaign_suggestions_service', function( $container ) {
        return new SCD_Campaign_Suggestions_Service(
            $container->get( 'campaign_repository' ),
            $container->get( 'campaign_suggestions_registry' )
        );
    } );

    $this->container->register( 'campaign_display_service', function( $container ) {
        return new SCD_Campaign_Display_Service(
            $container->get( 'campaign_repository' )
        );
    } );

    // Update dashboard service to inject sub-services
    $this->container->register( 'dashboard_service', function( $container ) {
        return new SCD_Dashboard_Service(
            $container->get( 'campaign_suggestions_service' ),
            $container->get( 'campaign_display_service' ),
            $container->get( 'campaign_health_service' ),
            $container->get( 'campaign_repository' )
        );
    } );
}
```

### Phase 4: Final Cleanup and Documentation

**Tasks**:
1. Remove old code from Dashboard Service (methods moved to sub-services)
2. Update PHPDoc blocks for all services
3. Add inline comments for complex logic
4. Update `PLUGIN-STRUCTURE.md` to reflect new architecture
5. Run WordPress Coding Standards check
6. Verify all dashboard functionality still works

**WordPress Coding Standards**:
```bash
vendor/bin/phpcs --standard=WordPress includes/services/class-campaign-suggestions-service.php
vendor/bin/phpcs --standard=WordPress includes/services/class-campaign-display-service.php
vendor/bin/phpcs --standard=WordPress includes/services/class-dashboard-service.php
```

---

## Expected Results

### Before Refactoring:
```
includes/services/class-dashboard-service.php: 1,615 lines
```

### After Refactoring:
```
includes/services/class-dashboard-service.php:              650 lines (-60%)
includes/services/class-campaign-suggestions-service.php:   450 lines (NEW)
includes/services/class-campaign-display-service.php:       350 lines (NEW)
────────────────────────────────────────────────────────────────────
Total:                                                    1,450 lines
```

**Net Reduction**: 165 lines (10% reduction from cleanup and removing duplication)

### Benefits:

1. **Single Responsibility Principle**: Each service has one clear purpose
2. **Easier Testing**: Can unit test suggestions logic independently from display logic
3. **Better Organization**: Related methods grouped together
4. **Future-Proof**: Timeline feature can be added as separate service or integrated cleanly
5. **Maintainability**: Smaller files are easier to understand and modify
6. **Dependency Clarity**: Explicit injection makes dependencies obvious
7. **Backward Compatibility**: Public API remains unchanged

---

## Next Steps (After Refactoring)

Once refactoring is complete and tested:

1. **Implement Timeline Feature**: Add new `SCD_Timeline_Service` using the refactored architecture
2. **Weekly Campaign Definitions**: Integrate with Suggestions Service
3. **Timeline UI**: Build on Display Service foundation

This refactoring creates the solid foundation needed for the timeline feature without creating an overengineered god class.

---

## Timeline Estimate

- **Phase 1** (Suggestions Service): 2-3 hours
- **Phase 2** (Display Service): 2-3 hours
- **Phase 3** (Service Container): 1 hour
- **Phase 4** (Cleanup & Testing): 1-2 hours

**Total**: 6-9 hours of focused development

---

## Risk Assessment

**Low Risk**:
- Public API remains unchanged (backward compatible)
- Delegation pattern is straightforward
- No database schema changes
- No frontend changes required

**Mitigation**:
- Test each phase independently before moving to next
- Keep Dashboard Service as orchestrator (maintains existing API)
- Use dependency injection for easy testing

---

## Approval Checklist

Before proceeding with implementation:

- [ ] Review service boundaries (Suggestions vs Display vs Health)
- [ ] Confirm constructor dependencies are available in service container
- [ ] Verify backward compatibility requirements
- [ ] Approve caching strategy for each service
- [ ] Confirm testing approach for each phase

**Ready to proceed when approved.**
