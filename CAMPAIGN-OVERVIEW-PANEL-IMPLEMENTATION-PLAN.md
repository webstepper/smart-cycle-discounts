# Campaign Overview Panel - Comprehensive Implementation Plan

**Plugin:** Smart Cycle Discounts
**Feature:** Campaign Overview Slide-out Panel (Drawer)
**Created:** 2025-01-05
**Author:** Claude Code
**Status:** Planning Phase

---

## Table of Contents

1. [Executive Summary](#executive-summary)
2. [Architecture Analysis](#architecture-analysis)
3. [Design Specifications](#design-specifications)
4. [Implementation Plan](#implementation-plan)
5. [File Structure](#file-structure)
6. [Detailed Implementation](#detailed-implementation)
7. [Testing Strategy](#testing-strategy)
8. [Deployment Checklist](#deployment-checklist)
9. [Future Enhancements](#future-enhancements)

---

## 1. Executive Summary

### 1.1 Overview

Implement a modern slide-out panel (drawer) system that allows users to view comprehensive campaign details without leaving the campaigns list page. The panel slides in from the right side of the screen, similar to mobile menu patterns and modern admin interfaces like Gutenberg block settings.

### 1.2 Goals

- ✅ **Improve UX:** Quick access to campaign details without page navigation
- ✅ **Maintain Context:** Users stay on the campaigns list while viewing details
- ✅ **Modern Interface:** Professional slide-in animation and responsive design
- ✅ **WordPress Standards:** Full compliance with WordPress.org approval requirements
- ✅ **Accessibility:** WCAG 2.1 AA compliance with keyboard navigation and ARIA attributes
- ✅ **Performance:** Optimized AJAX loading with caching
- ✅ **Reusability:** Architecture allows future expansion to other panel types

### 1.3 User Stories

**Primary Flow:**
1. User clicks "View" action on a campaign row
2. Panel slides in from right with loading state
3. Campaign data loaded via AJAX
4. User views organized sections: Basic Info, Schedule, Products, Discounts, Performance
5. User can click "Edit" to open wizard or close panel
6. Panel slides out smoothly

**Secondary Flows:**
- Click campaign name to open panel (optional enhancement)
- URL support: `?page=scd-campaigns&action=view&campaign_id=123`
- Keyboard navigation: ESC to close, Tab trapping
- Click backdrop to close
- Open different campaign without closing first

### 1.4 Success Metrics

- Panel opens in <300ms (AJAX response time)
- Zero accessibility violations (tested with aXe, WAVE)
- Zero JavaScript errors in browser console
- WordPress.org approval on first submission
- 100% backward compatible (no breaking changes)

---

## 2. Architecture Analysis

### 2.1 Plugin Architecture Summary

Based on comprehensive analysis of the Smart Cycle Discounts codebase:

#### **Service Container Pattern**
```
SCD_Container (Dependency Injection)
├─> Auto-resolution via PHP Reflection
├─> 70+ registered services
├─> Singleton and Factory support
└─> Circular dependency detection
```

#### **Admin Component Structure**
```
Page Controller → Prepare Data → Render View Template
     ↓                ↓                ↓
Inject Services   Format Data    PHP Template with Escaping
```

#### **AJAX Architecture**
```
Single Router (SCD_Ajax_Router)
├─> 40+ Handlers extending SCD_Abstract_Ajax_Handler
├─> Automatic nonce verification
├─> Capability checks
├─> Rate limiting
└─> Standardized SCD_Ajax_Response format
```

#### **Asset Management**
```
SCD_Admin_Asset_Manager (Orchestrator)
├─> SCD_Script_Registry (Define scripts with metadata)
├─> SCD_Style_Registry (Define styles with dependencies)
├─> SCD_Asset_Loader (Conditional loading by page/action)
└─> SCD_Asset_Localizer (Pass PHP → JavaScript globals)
```

#### **Campaign Data Model**
```
SCD_Campaign (Entity)
├─> Properties: id, uuid, name, status, priority, settings, metadata
├─> Schedule: starts_at, ends_at, timezone
├─> Products: product_selection_type, product_ids, category_ids, tag_ids
├─> Discounts: discount_type, discount_value, discount_rules
└─> Audit: created_by, updated_by, created_at, updated_at, version
```

### 2.2 Existing Patterns to Follow

#### **Modal Component** (`SCD_Modal_Component`)
- Factory method: `SCD_Modal_Component::create( 'type', $config )`
- Inline CSS and JavaScript rendering
- WordPress-compliant HTML structure
- `SCD.Modal.show()` and `SCD.Modal.hide()` JavaScript API

#### **Wizard System** (`SCD_Wizard_Manager`)
- State management via `SCD_Wizard_State_Service`
- Step-by-step navigation
- Session handling with secure cookies
- Progress tracking
- Auto-save functionality

#### **JavaScript Module Pattern** (Base API)
```javascript
( function( $ ) {
    'use strict';

    window.SCD = window.SCD || {};
    SCD.Shared = SCD.Shared || {};

    SCD.Shared.BaseAPI = function( config ) {
        this.config = $.extend( {}, this.getDefaultConfig(), config );
        this._pendingRequests = {};
    };

    SCD.Shared.BaseAPI.prototype = {
        request: function( action, data, options ) {
            // Centralized AJAX with deduplication
        }
    };

} )( jQuery );
```

#### **AJAX Handler Pattern**
```php
class SCD_Example_Handler extends SCD_Abstract_Ajax_Handler {
    protected function get_action_name() {
        return 'example_action';
    }

    protected function handle( $request ) {
        // Automatic security verification by parent
        // Return array with 'success' and 'data'
        return array(
            'success' => true,
            'data' => array( /* ... */ )
        );
    }
}
```

### 2.3 Campaign Data Available

From `SCD_Campaign` class analysis:

**Core Fields:**
- `id`, `uuid`, `name`, `slug`, `description`
- `status` (draft, active, paused, scheduled, expired, archived)
- `priority` (1-5)

**Schedule:**
- `starts_at`, `ends_at` (DateTime objects in UTC)
- `timezone` (WordPress timezone string)

**Products:**
- `product_selection_type` (all_products, specific_products, random_products, smart_selection)
- `product_ids` (array of WooCommerce product IDs)
- `category_ids`, `tag_ids`

**Discounts:**
- `discount_type` (percentage, fixed, bogo, tiered, bundle)
- `discount_value` (float)
- `discount_rules` (array of conditional rules)

**Metadata:**
- `settings` (array of campaign settings)
- `metadata` (array for extensibility)
- `template_id` (if created from template)

**Audit:**
- `created_by`, `updated_by` (user IDs)
- `created_at`, `updated_at` (DateTime)
- `version` (optimistic locking)

**Performance Metrics** (from Analytics Service):
- Retrieved separately via Analytics Repository
- Not stored in Campaign entity
- Available metrics: revenue, conversions, impressions, clicks, CTR, ROI

---

## 3. Design Specifications

### 3.1 Visual Design

#### **Panel Layout**
```
┌──────────────────────────────┬──────────────────────────┐
│ Campaigns List (dimmed)      │  ✕ Summer Sale 2025     │
│                              │  ● Active (PRO badge)    │
│ [Campaign rows visible       ├──────────────────────────┤
│  but not interactive]        │  [Scrollable Content]    │
│                              │                          │
│                              │  ═══ BASIC INFO ═══      │
│                              │  Description: 20% off    │
│                              │  all summer items        │
│                              │  Priority: ★★★★★ (5)     │
│                              │  Created: Jan 1, 2025    │
│                              │                          │
│                              │  ═══ SCHEDULE ═══        │
│                              │  📅 Start: Jan 15, 2025  │
│                              │      10:00 AM (PST)      │
│                              │  📅 End: Feb 15, 2025    │
│                              │      11:59 PM (PST)      │
│                              │  Duration: 31 days       │
│                              │                          │
│                              │  ═══ PRODUCTS ═══        │
│                              │  Selection: Specific     │
│                              │  Products (10 items)     │
│                              │  • Product A - $29.99    │
│                              │  • Product B - $49.99    │
│                              │  • Product C - $19.99    │
│                              │  [Show all 10 →]         │
│                              │                          │
│                              │  ═══ DISCOUNT ═══        │
│                              │  Type: Percentage        │
│                              │  Value: 20%              │
│                              │  Conditions:             │
│                              │  • Min Purchase: $50     │
│                              │  • Max Discount: $100    │
│                              │                          │
│                              │  ═══ PERFORMANCE ═══     │
│                              │  💰 Revenue: $1,234.56   │
│                              │  📊 Orders: 45           │
│                              │  👁 Views: 1,234          │
│                              │  🎯 Conversion: 3.6%     │
│                              │                          │
│                              │  [Edit Campaign] [Close] │
│                              │                          │
└──────────────────────────────┴──────────────────────────┘
                               ← 600px wide (desktop)
                               ← Full width (mobile)
```

#### **Dimensions**
- **Desktop:** 600px wide, 100vh height
- **Tablet:** 500px wide, 100vh height
- **Mobile:** 100vw wide (full screen), 100vh height
- **Z-index:** 100000 (above WordPress admin menu)

#### **Colors** (Using plugin theme variables)
```css
--scd-panel-bg: #FFFFFF
--scd-panel-header-bg: #F9FAFB
--scd-panel-border: #E5E7EB
--scd-backdrop: rgba(0, 0, 0, 0.5)
--scd-text-primary: #111827
--scd-text-secondary: #6B7280
--scd-section-header: #374151
```

### 3.2 Interaction Design

#### **Opening Animation**
```
Duration: 300ms
Easing: cubic-bezier(0.4, 0.0, 0.2, 1)
Transform: translateX(100%) → translateX(0)
Backdrop: opacity 0 → 0.5
```

#### **Closing Animation**
```
Duration: 250ms
Easing: cubic-bezier(0.4, 0.0, 0.6, 1)
Transform: translateX(0) → translateX(100%)
Backdrop: opacity 0.5 → 0
```

#### **Loading States**
```
Initial: Skeleton loaders for each section
Data Loaded: Fade-in content (150ms)
Error: Error message with retry button
```

#### **Keyboard Navigation**
- `ESC` - Close panel
- `Tab` - Navigate through interactive elements (trapped focus)
- `Shift+Tab` - Reverse navigation
- `Enter` - Activate buttons
- Focus returns to trigger element on close

### 3.3 Accessibility Requirements

#### **ARIA Attributes**
```html
<div class="scd-overview-panel"
     role="dialog"
     aria-modal="true"
     aria-labelledby="panel-title"
     aria-describedby="panel-description">

<h2 id="panel-title">Campaign Overview: Summer Sale 2025</h2>
<div id="panel-description" class="screen-reader-text">
    Detailed information about the Summer Sale 2025 campaign
</div>
```

#### **Focus Management**
1. When panel opens: Focus moves to close button or first interactive element
2. Tab key: Focus stays trapped within panel
3. When panel closes: Focus returns to element that triggered opening

#### **Screen Reader Support**
- Section headings use semantic `<h3>` tags
- Status changes announced via `aria-live` regions
- Loading states announced
- Error messages properly associated with controls

---

## 4. Implementation Plan

### 4.1 Development Phases

#### **Phase 1: Core Infrastructure** (Est. 3-4 hours)
1. Create component PHP class
2. Create AJAX handler for data loading
3. Register in service container
4. Add row action to campaigns list
5. Basic HTML structure and CSS

**Deliverables:**
- `includes/admin/components/class-campaign-overview-panel.php`
- `includes/admin/ajax/handlers/class-campaign-overview-handler.php`
- `resources/assets/css/admin/campaign-overview-panel.css`
- `resources/views/admin/components/campaign-overview-panel.php`

#### **Phase 2: JavaScript Functionality** (Est. 2-3 hours)
1. Panel controller JavaScript
2. AJAX data loading
3. Open/close animations
4. Keyboard navigation
5. Error handling

**Deliverables:**
- `resources/assets/js/admin/campaign-overview-panel.js`
- Integration with existing `SCD.Ajax` service
- Integration with `SCD.ErrorHandler`

#### **Phase 3: Content Sections** (Est. 3-4 hours)
1. Basic Info section renderer
2. Schedule section renderer
3. Products section renderer
4. Discounts section renderer
5. Performance section renderer (if analytics available)

**Deliverables:**
- Section rendering methods in component class
- Proper data formatting
- Badge integration via `SCD_Badge_Helper`

#### **Phase 4: Polish & Accessibility** (Est. 2-3 hours)
1. Accessibility testing and fixes
2. Responsive design refinement
3. Loading states and skeleton screens
4. Error states
5. Edge case handling

**Deliverables:**
- Fully accessible panel
- Mobile-responsive design
- Comprehensive error handling

#### **Phase 5: Testing & Documentation** (Est. 2 hours)
1. Manual testing across browsers
2. Accessibility audit (aXe, WAVE)
3. Code documentation
4. Update user documentation

**Deliverables:**
- Test results
- Accessibility compliance report
- Code comments and PHPDoc
- User guide update

**Total Estimated Time:** 12-16 hours

### 4.2 Dependencies

#### **Required:**
- ✅ Existing campaign list table (`SCD_Campaigns_List_Table`)
- ✅ Campaign repository (`SCD_Campaign_Repository`)
- ✅ AJAX router (`SCD_Ajax_Router`)
- ✅ Asset management system
- ✅ Badge helper (`SCD_Badge_Helper`)

#### **Optional Enhancements:**
- Analytics service (for performance metrics)
- Campaign health service (for health status)
- Campaign formatter (for display formatting)

---

## 5. File Structure

### 5.1 New Files to Create

```
/includes/admin/components/
├── class-campaign-overview-panel.php         [NEW] Component class

/includes/admin/ajax/handlers/
├── class-campaign-overview-handler.php       [NEW] AJAX handler

/resources/assets/css/admin/
├── campaign-overview-panel.css               [NEW] Panel styles

/resources/assets/js/admin/
├── campaign-overview-panel.js                [NEW] Panel controller

/resources/views/admin/components/
├── campaign-overview-panel.php               [NEW] Panel template
    ├── partials/
        ├── section-basic.php                 [NEW] Basic info section
        ├── section-schedule.php              [NEW] Schedule section
        ├── section-products.php              [NEW] Products section
        ├── section-discounts.php             [NEW] Discounts section
        └── section-performance.php           [NEW] Performance section
```

### 5.2 Files to Modify

```
/includes/admin/components/
├── class-campaigns-list-table.php            [MODIFY] Add "View" row action

/includes/admin/assets/
├── class-script-registry.php                 [MODIFY] Register new JS
├── class-style-registry.php                  [MODIFY] Register new CSS

/includes/bootstrap/
├── class-service-definitions.php             [MODIFY] Register services
```

### 5.3 Directory Structure After Implementation

```
smart-cycle-discounts/
├── includes/
│   ├── admin/
│   │   ├── ajax/
│   │   │   ├── handlers/
│   │   │   │   ├── class-campaign-overview-handler.php    ← NEW
│   │   │   │   └── ... (40+ other handlers)
│   │   ├── components/
│   │   │   ├── class-badge-helper.php                     ← EXISTS
│   │   │   ├── class-campaigns-list-table.php             ← MODIFY
│   │   │   ├── class-campaign-overview-panel.php          ← NEW
│   │   │   ├── class-modal-component.php                  ← EXISTS
│   │   │   └── ...
│   │   ├── assets/
│   │   │   ├── class-script-registry.php                  ← MODIFY
│   │   │   └── class-style-registry.php                   ← MODIFY
│   ├── bootstrap/
│   │   └── class-service-definitions.php                  ← MODIFY
├── resources/
│   ├── assets/
│   │   ├── css/
│   │   │   ├── admin/
│   │   │   │   └── campaign-overview-panel.css            ← NEW
│   │   ├── js/
│   │   │   ├── admin/
│   │   │   │   └── campaign-overview-panel.js             ← NEW
│   ├── views/
│   │   ├── admin/
│   │   │   ├── components/
│   │   │   │   ├── campaign-overview-panel.php            ← NEW
│   │   │   │   ├── partials/
│   │   │   │   │   ├── section-basic.php                  ← NEW
│   │   │   │   │   ├── section-schedule.php               ← NEW
│   │   │   │   │   ├── section-products.php               ← NEW
│   │   │   │   │   ├── section-discounts.php              ← NEW
│   │   │   │   │   └── section-performance.php            ← NEW
```

---

## 6. Detailed Implementation

### 6.1 Component Class

**File:** `includes/admin/components/class-campaign-overview-panel.php`

```php
<?php
/**
 * Campaign Overview Panel Component
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/components
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Campaign Overview Panel Component
 *
 * Provides a slide-out panel for viewing campaign details without
 * leaving the campaigns list page.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/components
 * @author     Webstepper <contact@webstepper.io>
 */
class SCD_Campaign_Overview_Panel {

    /**
     * Campaign repository.
     *
     * @since    1.0.0
     * @access   private
     * @var      SCD_Campaign_Repository    $campaign_repository    Campaign repository.
     */
    private SCD_Campaign_Repository $campaign_repository;

    /**
     * Campaign formatter.
     *
     * @since    1.0.0
     * @access   private
     * @var      SCD_Campaign_Formatter|null    $formatter    Campaign formatter.
     */
    private ?SCD_Campaign_Formatter $formatter;

    /**
     * Analytics repository (optional).
     *
     * @since    1.0.0
     * @access   private
     * @var      SCD_Analytics_Repository|null    $analytics_repository    Analytics repository.
     */
    private ?SCD_Analytics_Repository $analytics_repository;

    /**
     * Initialize the component.
     *
     * @since    1.0.0
     * @param    SCD_Campaign_Repository         $campaign_repository      Campaign repository.
     * @param    SCD_Campaign_Formatter|null     $formatter                Campaign formatter.
     * @param    SCD_Analytics_Repository|null   $analytics_repository     Analytics repository.
     */
    public function __construct(
        SCD_Campaign_Repository $campaign_repository,
        ?SCD_Campaign_Formatter $formatter = null,
        ?SCD_Analytics_Repository $analytics_repository = null
    ) {
        $this->campaign_repository    = $campaign_repository;
        $this->formatter              = $formatter;
        $this->analytics_repository   = $analytics_repository;
    }

    /**
     * Render the panel HTML structure.
     *
     * This is rendered on page load (hidden) and populated via AJAX when opened.
     *
     * @since    1.0.0
     * @return   void
     */
    public function render(): void {
        $template_path = SCD_PLUGIN_DIR . 'resources/views/admin/components/campaign-overview-panel.php';

        if ( ! file_exists( $template_path ) ) {
            return;
        }

        // Pass component instance to template for method access
        $panel = $this;

        include $template_path;
    }

    /**
     * Prepare campaign data for panel display.
     *
     * Called by AJAX handler to format campaign data.
     *
     * @since    1.0.0
     * @param    SCD_Campaign $campaign    Campaign object.
     * @return   array                       Formatted campaign data.
     */
    public function prepare_campaign_data( SCD_Campaign $campaign ): array {
        $data = array(
            'id'          => $campaign->get_id(),
            'uuid'        => $campaign->get_uuid(),
            'name'        => $campaign->get_name(),
            'status'      => $campaign->get_status(),
            'basic'       => $this->prepare_basic_section( $campaign ),
            'schedule'    => $this->prepare_schedule_section( $campaign ),
            'products'    => $this->prepare_products_section( $campaign ),
            'discounts'   => $this->prepare_discounts_section( $campaign ),
            'performance' => $this->prepare_performance_section( $campaign ),
        );

        return $data;
    }

    /**
     * Prepare basic info section data.
     *
     * @since    1.0.0
     * @param    SCD_Campaign $campaign    Campaign object.
     * @return   array                       Basic info data.
     */
    private function prepare_basic_section( SCD_Campaign $campaign ): array {
        return array(
            'name'        => $campaign->get_name(),
            'description' => $campaign->get_description(),
            'status'      => $campaign->get_status(),
            'priority'    => $campaign->get_priority(),
            'created_by'  => $campaign->get_created_by(),
            'created_at'  => $campaign->get_created_at(),
            'updated_at'  => $campaign->get_updated_at(),
        );
    }

    /**
     * Prepare schedule section data.
     *
     * @since    1.0.0
     * @param    SCD_Campaign $campaign    Campaign object.
     * @return   array                       Schedule data.
     */
    private function prepare_schedule_section( SCD_Campaign $campaign ): array {
        $starts_at = $campaign->get_starts_at();
        $ends_at   = $campaign->get_ends_at();
        $timezone  = $campaign->get_timezone();

        // Convert UTC to site timezone for display
        if ( $starts_at ) {
            $starts_at = clone $starts_at;
            $starts_at->setTimezone( new DateTimeZone( $timezone ) );
        }

        if ( $ends_at ) {
            $ends_at = clone $ends_at;
            $ends_at->setTimezone( new DateTimeZone( $timezone ) );
        }

        // Calculate duration
        $duration = null;
        if ( $starts_at && $ends_at ) {
            $interval = $starts_at->diff( $ends_at );
            $duration = $interval->format( '%a days' );
        }

        return array(
            'starts_at' => $starts_at,
            'ends_at'   => $ends_at,
            'timezone'  => $timezone,
            'duration'  => $duration,
        );
    }

    /**
     * Prepare products section data.
     *
     * @since    1.0.0
     * @param    SCD_Campaign $campaign    Campaign object.
     * @return   array                       Products data.
     */
    private function prepare_products_section( SCD_Campaign $campaign ): array {
        $selection_type = $campaign->get_product_selection_type();
        $product_ids    = $campaign->get_product_ids();
        $category_ids   = $campaign->get_category_ids();
        $tag_ids        = $campaign->get_tag_ids();

        $products = array();

        // Load product details (limit to first 5 for performance)
        if ( ! empty( $product_ids ) ) {
            $display_limit = 5;
            $limited_ids   = array_slice( $product_ids, 0, $display_limit );

            foreach ( $limited_ids as $product_id ) {
                $product = wc_get_product( $product_id );
                if ( $product ) {
                    $products[] = array(
                        'id'    => $product_id,
                        'name'  => $product->get_name(),
                        'price' => $product->get_price(),
                        'image' => $product->get_image( 'thumbnail' ),
                    );
                }
            }
        }

        return array(
            'selection_type' => $selection_type,
            'total_products' => count( $product_ids ),
            'products'       => $products,
            'has_more'       => count( $product_ids ) > 5,
            'category_count' => count( $category_ids ),
            'tag_count'      => count( $tag_ids ),
        );
    }

    /**
     * Prepare discounts section data.
     *
     * @since    1.0.0
     * @param    SCD_Campaign $campaign    Campaign object.
     * @return   array                       Discounts data.
     */
    private function prepare_discounts_section( SCD_Campaign $campaign ): array {
        $discount_type  = $campaign->get_discount_type();
        $discount_value = $campaign->get_discount_value();
        $discount_rules = $campaign->get_discount_rules();

        // Format discount value based on type
        $formatted_value = $discount_value;
        if ( 'percentage' === $discount_type ) {
            $formatted_value = $discount_value . '%';
        } elseif ( 'fixed' === $discount_type ) {
            $formatted_value = wc_price( $discount_value );
        }

        return array(
            'type'            => $discount_type,
            'value'           => $discount_value,
            'formatted_value' => $formatted_value,
            'rules'           => $discount_rules,
        );
    }

    /**
     * Prepare performance section data.
     *
     * @since    1.0.0
     * @param    SCD_Campaign $campaign    Campaign object.
     * @return   array                       Performance data.
     */
    private function prepare_performance_section( SCD_Campaign $campaign ): array {
        // Default empty metrics
        $metrics = array(
            'revenue'      => 0,
            'conversions'  => 0,
            'impressions'  => 0,
            'clicks'       => 0,
            'ctr'          => 0,
            'avg_order'    => 0,
        );

        // Try to load analytics if service available
        if ( $this->analytics_repository ) {
            try {
                $analytics = $this->analytics_repository->get_campaign_analytics(
                    $campaign->get_id(),
                    array(
                        'date_range' => '30days',
                    )
                );

                if ( ! empty( $analytics ) ) {
                    $metrics = array(
                        'revenue'      => $analytics['revenue'] ?? 0,
                        'conversions'  => $analytics['conversions'] ?? 0,
                        'impressions'  => $analytics['impressions'] ?? 0,
                        'clicks'       => $analytics['clicks'] ?? 0,
                        'ctr'          => $analytics['ctr'] ?? 0,
                        'avg_order'    => $analytics['avg_order_value'] ?? 0,
                    );
                }
            } catch ( Exception $e ) {
                // Analytics unavailable - return empty metrics
                SCD_Log::warning(
                    'Failed to load campaign analytics for overview panel',
                    array(
                        'campaign_id' => $campaign->get_id(),
                        'error'       => $e->getMessage(),
                    )
                );
            }
        }

        return $metrics;
    }

    /**
     * Render basic info section.
     *
     * @since    1.0.0
     * @param    array $data    Basic info data.
     * @return   void
     */
    public function render_basic_section( array $data ): void {
        $template_path = SCD_PLUGIN_DIR . 'resources/views/admin/components/partials/section-basic.php';

        if ( file_exists( $template_path ) ) {
            include $template_path;
        }
    }

    /**
     * Render schedule section.
     *
     * @since    1.0.0
     * @param    array $data    Schedule data.
     * @return   void
     */
    public function render_schedule_section( array $data ): void {
        $template_path = SCD_PLUGIN_DIR . 'resources/views/admin/components/partials/section-schedule.php';

        if ( file_exists( $template_path ) ) {
            include $template_path;
        }
    }

    /**
     * Render products section.
     *
     * @since    1.0.0
     * @param    array $data    Products data.
     * @return   void
     */
    public function render_products_section( array $data ): void {
        $template_path = SCD_PLUGIN_DIR . 'resources/views/admin/components/partials/section-products.php';

        if ( file_exists( $template_path ) ) {
            include $template_path;
        }
    }

    /**
     * Render discounts section.
     *
     * @since    1.0.0
     * @param    array $data    Discounts data.
     * @return   void
     */
    public function render_discounts_section( array $data ): void {
        $template_path = SCD_PLUGIN_DIR . 'resources/views/admin/components/partials/section-discounts.php';

        if ( file_exists( $template_path ) ) {
            include $template_path;
        }
    }

    /**
     * Render performance section.
     *
     * @since    1.0.0
     * @param    array $data    Performance data.
     * @return   void
     */
    public function render_performance_section( array $data ): void {
        $template_path = SCD_PLUGIN_DIR . 'resources/views/admin/components/partials/section-performance.php';

        if ( file_exists( $template_path ) ) {
            include $template_path;
        }
    }
}
```

### 6.2 AJAX Handler

**File:** `includes/admin/ajax/handlers/class-campaign-overview-handler.php`

```php
<?php
/**
 * Campaign Overview AJAX Handler
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Campaign Overview AJAX Handler
 *
 * Handles AJAX requests for loading campaign data into the overview panel.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers
 * @author     Webstepper <contact@webstepper.io>
 */
class SCD_Campaign_Overview_Handler extends SCD_Abstract_Ajax_Handler {

    /**
     * Campaign repository.
     *
     * @since    1.0.0
     * @access   private
     * @var      SCD_Campaign_Repository    $campaign_repository    Campaign repository.
     */
    private SCD_Campaign_Repository $campaign_repository;

    /**
     * Overview panel component.
     *
     * @since    1.0.0
     * @access   private
     * @var      SCD_Campaign_Overview_Panel    $panel    Panel component.
     */
    private SCD_Campaign_Overview_Panel $panel;

    /**
     * Initialize the handler.
     *
     * @since    1.0.0
     * @param    SCD_Campaign_Repository      $campaign_repository    Campaign repository.
     * @param    SCD_Campaign_Overview_Panel  $panel                  Panel component.
     * @param    SCD_Logger|null              $logger                 Logger instance.
     */
    public function __construct(
        SCD_Campaign_Repository $campaign_repository,
        SCD_Campaign_Overview_Panel $panel,
        $logger = null
    ) {
        parent::__construct( $logger );
        $this->campaign_repository = $campaign_repository;
        $this->panel               = $panel;
    }

    /**
     * Get AJAX action name.
     *
     * @since    1.0.0
     * @return   string    Action name.
     */
    protected function get_action_name(): string {
        return 'campaign_overview';
    }

    /**
     * Handle the AJAX request.
     *
     * @since    1.0.0
     * @param    array $request    Request data.
     * @return   array               Response array.
     */
    protected function handle( $request ): array {
        // Validate campaign ID
        if ( ! isset( $request['campaign_id'] ) ) {
            return array(
                'success' => false,
                'message' => __( 'Campaign ID is required', 'smart-cycle-discounts' ),
            );
        }

        $campaign_id = absint( $request['campaign_id'] );

        if ( $campaign_id <= 0 ) {
            return array(
                'success' => false,
                'message' => __( 'Invalid campaign ID', 'smart-cycle-discounts' ),
            );
        }

        // Load campaign
        try {
            $campaign = $this->campaign_repository->find( $campaign_id );

            if ( ! $campaign ) {
                return array(
                    'success' => false,
                    'message' => __( 'Campaign not found', 'smart-cycle-discounts' ),
                );
            }

            // Prepare data for panel
            $data = $this->panel->prepare_campaign_data( $campaign );

            // Render HTML sections
            $html = array(
                'basic'       => $this->render_section( 'basic', $data['basic'] ),
                'schedule'    => $this->render_section( 'schedule', $data['schedule'] ),
                'products'    => $this->render_section( 'products', $data['products'] ),
                'discounts'   => $this->render_section( 'discounts', $data['discounts'] ),
                'performance' => $this->render_section( 'performance', $data['performance'] ),
            );

            return array(
                'success' => true,
                'data'    => array(
                    'campaign' => $data,
                    'html'     => $html,
                ),
            );

        } catch ( Exception $e ) {
            SCD_Log::error(
                'Failed to load campaign for overview panel',
                array(
                    'campaign_id' => $campaign_id,
                    'error'       => $e->getMessage(),
                    'trace'       => $e->getTraceAsString(),
                )
            );

            return array(
                'success' => false,
                'message' => __( 'Failed to load campaign details. Please try again.', 'smart-cycle-discounts' ),
            );
        }
    }

    /**
     * Render a section and return HTML.
     *
     * @since    1.0.0
     * @param    string $section    Section name.
     * @param    array  $data       Section data.
     * @return   string               Rendered HTML.
     */
    private function render_section( string $section, array $data ): string {
        ob_start();

        switch ( $section ) {
            case 'basic':
                $this->panel->render_basic_section( $data );
                break;
            case 'schedule':
                $this->panel->render_schedule_section( $data );
                break;
            case 'products':
                $this->panel->render_products_section( $data );
                break;
            case 'discounts':
                $this->panel->render_discounts_section( $data );
                break;
            case 'performance':
                $this->panel->render_performance_section( $data );
                break;
        }

        return ob_get_clean();
    }
}
```

### 6.3 Panel Template

**File:** `resources/views/admin/components/campaign-overview-panel.php`

```php
<?php
/**
 * Campaign Overview Panel Template
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/views/admin/components
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
?>

<div id="scd-campaign-overview-panel"
     class="scd-overview-panel"
     role="dialog"
     aria-modal="true"
     aria-labelledby="scd-panel-title"
     aria-describedby="scd-panel-description"
     style="display: none;">

    <!-- Backdrop -->
    <div class="scd-overview-panel__backdrop"></div>

    <!-- Panel Container -->
    <div class="scd-overview-panel__container">

        <!-- Header -->
        <div class="scd-overview-panel__header">
            <div class="scd-overview-panel__header-content">
                <h2 id="scd-panel-title" class="scd-overview-panel__title">
                    <span class="scd-panel-title-text"><?php esc_html_e( 'Campaign Overview', 'smart-cycle-discounts' ); ?></span>
                </h2>
                <div id="scd-panel-description" class="screen-reader-text">
                    <?php esc_html_e( 'Detailed information about the selected campaign', 'smart-cycle-discounts' ); ?>
                </div>
            </div>
            <button type="button"
                    class="scd-overview-panel__close"
                    aria-label="<?php esc_attr_e( 'Close panel', 'smart-cycle-discounts' ); ?>">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>

        <!-- Content -->
        <div class="scd-overview-panel__content">

            <!-- Loading State -->
            <div class="scd-overview-panel__loading" style="display: none;">
                <div class="scd-loading-spinner"></div>
                <p><?php esc_html_e( 'Loading campaign details...', 'smart-cycle-discounts' ); ?></p>
            </div>

            <!-- Error State -->
            <div class="scd-overview-panel__error" style="display: none;">
                <span class="dashicons dashicons-warning"></span>
                <p class="scd-error-message"></p>
                <button type="button" class="button scd-retry-load">
                    <?php esc_html_e( 'Retry', 'smart-cycle-discounts' ); ?>
                </button>
            </div>

            <!-- Sections Container -->
            <div class="scd-overview-panel__sections" style="display: none;">

                <!-- Basic Info Section -->
                <div class="scd-overview-section scd-section-basic">
                    <h3 class="scd-section-title">
                        <?php esc_html_e( 'Basic Information', 'smart-cycle-discounts' ); ?>
                    </h3>
                    <div class="scd-section-content" data-section="basic">
                        <!-- Populated via AJAX -->
                    </div>
                </div>

                <!-- Schedule Section -->
                <div class="scd-overview-section scd-section-schedule">
                    <h3 class="scd-section-title">
                        <?php esc_html_e( 'Schedule', 'smart-cycle-discounts' ); ?>
                    </h3>
                    <div class="scd-section-content" data-section="schedule">
                        <!-- Populated via AJAX -->
                    </div>
                </div>

                <!-- Products Section -->
                <div class="scd-overview-section scd-section-products">
                    <h3 class="scd-section-title">
                        <?php esc_html_e( 'Products', 'smart-cycle-discounts' ); ?>
                    </h3>
                    <div class="scd-section-content" data-section="products">
                        <!-- Populated via AJAX -->
                    </div>
                </div>

                <!-- Discounts Section -->
                <div class="scd-overview-section scd-section-discounts">
                    <h3 class="scd-section-title">
                        <?php esc_html_e( 'Discount Configuration', 'smart-cycle-discounts' ); ?>
                    </h3>
                    <div class="scd-section-content" data-section="discounts">
                        <!-- Populated via AJAX -->
                    </div>
                </div>

                <!-- Performance Section -->
                <div class="scd-overview-section scd-section-performance">
                    <h3 class="scd-section-title">
                        <?php esc_html_e( 'Performance', 'smart-cycle-discounts' ); ?>
                    </h3>
                    <div class="scd-section-content" data-section="performance">
                        <!-- Populated via AJAX -->
                    </div>
                </div>

            </div>

        </div>

        <!-- Footer -->
        <div class="scd-overview-panel__footer">
            <button type="button" class="button button-primary scd-edit-campaign">
                <?php esc_html_e( 'Edit Campaign', 'smart-cycle-discounts' ); ?>
            </button>
            <button type="button" class="button scd-close-panel">
                <?php esc_html_e( 'Close', 'smart-cycle-discounts' ); ?>
            </button>
        </div>

    </div>

</div>
```

### 6.4 JavaScript Controller

**File:** `resources/assets/js/admin/campaign-overview-panel.js`

```javascript
/**
 * Campaign Overview Panel
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/js/admin
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

( function( $ ) {
    'use strict';

    window.SCD = window.SCD || {};

    /**
     * Campaign Overview Panel Controller
     *
     * @class SCD.CampaignOverviewPanel
     */
    SCD.CampaignOverviewPanel = {

        /**
         * Panel element
         * @type {jQuery}
         */
        $panel: null,

        /**
         * Current campaign ID
         * @type {number|null}
         */
        currentCampaignId: null,

        /**
         * Original focus element (for returning focus on close)
         * @type {jQuery|null}
         */
        $originalFocus: null,

        /**
         * Initialize the panel controller
         */
        init: function() {
            var self = this;

            this.$panel = $( '#scd-campaign-overview-panel' );

            if ( ! this.$panel.length ) {
                return;
            }

            // Bind events
            this.bindEvents();

            // Check URL for ?action=view&campaign_id=X
            this.checkUrlParams();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            var self = this;

            // Open panel - delegate to handle dynamically added elements
            $( document ).on( 'click', '.scd-view-campaign', function( e ) {
                e.preventDefault();
                var campaignId = $( this ).data( 'campaign-id' );
                self.$originalFocus = $( this );
                self.open( campaignId );
            } );

            // Close panel
            this.$panel.on( 'click', '.scd-overview-panel__close, .scd-close-panel', function( e ) {
                e.preventDefault();
                self.close();
            } );

            // Click backdrop to close
            this.$panel.on( 'click', '.scd-overview-panel__backdrop', function( e ) {
                e.preventDefault();
                self.close();
            } );

            // Edit campaign button
            this.$panel.on( 'click', '.scd-edit-campaign', function( e ) {
                e.preventDefault();
                self.editCampaign();
            } );

            // Retry load button
            this.$panel.on( 'click', '.scd-retry-load', function( e ) {
                e.preventDefault();
                if ( self.currentCampaignId ) {
                    self.loadCampaignData( self.currentCampaignId );
                }
            } );

            // Keyboard navigation
            $( document ).on( 'keydown.scd-overview-panel', function( e ) {
                if ( ! self.isOpen() ) {
                    return;
                }

                // ESC key - close panel
                if ( 27 === e.keyCode ) {
                    e.preventDefault();
                    self.close();
                }

                // Tab key - trap focus
                if ( 9 === e.keyCode ) {
                    self.trapFocus( e );
                }
            } );
        },

        /**
         * Check URL params for campaign_id
         */
        checkUrlParams: function() {
            var urlParams = new URLSearchParams( window.location.search );
            var action = urlParams.get( 'action' );
            var campaignId = urlParams.get( 'campaign_id' );

            if ( 'view' === action && campaignId ) {
                this.open( parseInt( campaignId, 10 ) );
            }
        },

        /**
         * Open the panel and load campaign data
         *
         * @param {number} campaignId - Campaign ID to load
         */
        open: function( campaignId ) {
            var self = this;

            if ( ! campaignId ) {
                return;
            }

            this.currentCampaignId = campaignId;

            // Show panel with animation
            this.$panel.fadeIn( 200 ).css( 'display', 'flex' );

            // Add class for animation
            setTimeout( function() {
                self.$panel.addClass( 'scd-overview-panel--open' );
            }, 10 );

            // Prevent body scroll
            $( 'body' ).addClass( 'scd-overview-panel-open' );

            // Show loading state
            this.showLoading();

            // Load campaign data
            this.loadCampaignData( campaignId );

            // Update URL (without page reload)
            if ( window.history && window.history.pushState ) {
                var url = new URL( window.location.href );
                url.searchParams.set( 'action', 'view' );
                url.searchParams.set( 'campaign_id', campaignId );
                window.history.pushState( {}, '', url );
            }
        },

        /**
         * Close the panel
         */
        close: function() {
            var self = this;

            // Remove open class for animation
            this.$panel.removeClass( 'scd-overview-panel--open' );

            // Wait for animation, then hide
            setTimeout( function() {
                self.$panel.fadeOut( 200 );
            }, 250 );

            // Restore body scroll
            $( 'body' ).removeClass( 'scd-overview-panel-open' );

            // Clear current campaign
            this.currentCampaignId = null;

            // Return focus to original element
            if ( this.$originalFocus && this.$originalFocus.length ) {
                this.$originalFocus.focus();
                this.$originalFocus = null;
            }

            // Update URL (remove params)
            if ( window.history && window.history.pushState ) {
                var url = new URL( window.location.href );
                url.searchParams.delete( 'action' );
                url.searchParams.delete( 'campaign_id' );
                window.history.pushState( {}, '', url );
            }
        },

        /**
         * Check if panel is open
         *
         * @returns {boolean}
         */
        isOpen: function() {
            return this.$panel.hasClass( 'scd-overview-panel--open' );
        },

        /**
         * Show loading state
         */
        showLoading: function() {
            this.$panel.find( '.scd-overview-panel__loading' ).show();
            this.$panel.find( '.scd-overview-panel__error' ).hide();
            this.$panel.find( '.scd-overview-panel__sections' ).hide();
        },

        /**
         * Show error state
         *
         * @param {string} message - Error message
         */
        showError: function( message ) {
            this.$panel.find( '.scd-overview-panel__loading' ).hide();
            this.$panel.find( '.scd-overview-panel__error' ).show();
            this.$panel.find( '.scd-overview-panel__sections' ).hide();
            this.$panel.find( '.scd-error-message' ).text( message );
        },

        /**
         * Show content sections
         */
        showContent: function() {
            this.$panel.find( '.scd-overview-panel__loading' ).hide();
            this.$panel.find( '.scd-overview-panel__error' ).hide();
            this.$panel.find( '.scd-overview-panel__sections' ).show();
        },

        /**
         * Load campaign data via AJAX
         *
         * @param {number} campaignId - Campaign ID
         */
        loadCampaignData: function( campaignId ) {
            var self = this;

            // Use centralized AJAX service
            SCD.Ajax.post( 'campaign_overview', {
                campaign_id: campaignId
            } ).done( function( response ) {
                if ( response.success && response.data ) {
                    self.populatePanelData( response.data );
                    self.showContent();
                } else {
                    var message = response.message || 'Failed to load campaign details';
                    self.showError( message );
                }
            } ).fail( function( xhr, status, error ) {
                self.showError( 'An error occurred while loading campaign details. Please try again.' );
            } );
        },

        /**
         * Populate panel with campaign data
         *
         * @param {object} data - Campaign data from AJAX response
         */
        populatePanelData: function( data ) {
            // Update panel title
            if ( data.campaign && data.campaign.name ) {
                this.$panel.find( '.scd-panel-title-text' ).text( data.campaign.name );
            }

            // Populate each section with HTML
            if ( data.html ) {
                if ( data.html.basic ) {
                    this.$panel.find( '[data-section="basic"]' ).html( data.html.basic );
                }
                if ( data.html.schedule ) {
                    this.$panel.find( '[data-section="schedule"]' ).html( data.html.schedule );
                }
                if ( data.html.products ) {
                    this.$panel.find( '[data-section="products"]' ).html( data.html.products );
                }
                if ( data.html.discounts ) {
                    this.$panel.find( '[data-section="discounts"]' ).html( data.html.discounts );
                }
                if ( data.html.performance ) {
                    this.$panel.find( '[data-section="performance"]' ).html( data.html.performance );
                }
            }
        },

        /**
         * Edit current campaign (open wizard)
         */
        editCampaign: function() {
            if ( ! this.currentCampaignId ) {
                return;
            }

            // Navigate to campaign edit page
            var editUrl = scdCampaignOverview.editUrl.replace( 'CAMPAIGN_ID', this.currentCampaignId );
            window.location.href = editUrl;
        },

        /**
         * Trap focus within panel (accessibility)
         *
         * @param {Event} e - Keydown event
         */
        trapFocus: function( e ) {
            var $focusable = this.$panel.find(
                'a, button, input, select, textarea, [tabindex]:not([tabindex="-1"])'
            ).filter( ':visible' );

            if ( ! $focusable.length ) {
                return;
            }

            var $first = $focusable.first();
            var $last = $focusable.last();
            var $target = $( e.target );

            // Shift+Tab on first element - go to last
            if ( e.shiftKey && $target.is( $first ) ) {
                e.preventDefault();
                $last.focus();
            }
            // Tab on last element - go to first
            else if ( ! e.shiftKey && $target.is( $last ) ) {
                e.preventDefault();
                $first.focus();
            }
        }
    };

    // Initialize when DOM is ready
    $( document ).ready( function() {
        SCD.CampaignOverviewPanel.init();
    } );

} )( jQuery );
```

### 6.5 CSS Styles

**File:** `resources/assets/css/admin/campaign-overview-panel.css`

```css
/**
 * Campaign Overview Panel Styles
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/resources/assets/css/admin
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

/* ==========================================================================
   Panel Container
   ========================================================================== */

.scd-overview-panel {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 100000;
    display: none; /* Hidden by default */
    align-items: stretch;
    justify-content: flex-end;
}

/* When panel is opened, use flex display */
.scd-overview-panel.scd-overview-panel--open {
    display: flex;
}

/* Backdrop */
.scd-overview-panel__backdrop {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    opacity: 0;
    transition: opacity 0.3s cubic-bezier(0.4, 0.0, 0.2, 1);
    cursor: pointer;
}

.scd-overview-panel--open .scd-overview-panel__backdrop {
    opacity: 1;
}

/* Panel Container */
.scd-overview-panel__container {
    position: relative;
    width: 600px;
    height: 100%;
    background: #FFFFFF;
    box-shadow: -2px 0 8px rgba(0, 0, 0, 0.15);
    display: flex;
    flex-direction: column;
    transform: translateX(100%);
    transition: transform 0.3s cubic-bezier(0.4, 0.0, 0.2, 1);
    z-index: 100001;
}

.scd-overview-panel--open .scd-overview-panel__container {
    transform: translateX(0);
}

/* ==========================================================================
   Header
   ========================================================================== */

.scd-overview-panel__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px 24px;
    background: #F9FAFB;
    border-bottom: 1px solid #E5E7EB;
    flex-shrink: 0;
}

.scd-overview-panel__header-content {
    flex: 1;
}

.scd-overview-panel__title {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    color: #111827;
    line-height: 1.4;
}

.scd-overview-panel__close {
    background: none;
    border: none;
    padding: 8px;
    cursor: pointer;
    color: #6B7280;
    transition: color 0.15s ease;
    flex-shrink: 0;
    margin-left: 16px;
}

.scd-overview-panel__close:hover {
    color: #111827;
}

.scd-overview-panel__close .dashicons {
    width: 24px;
    height: 24px;
    font-size: 24px;
}

/* ==========================================================================
   Content
   ========================================================================== */

.scd-overview-panel__content {
    flex: 1;
    overflow-y: auto;
    padding: 24px;
}

/* Loading State */
.scd-overview-panel__loading {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 60px 20px;
    text-align: center;
}

.scd-loading-spinner {
    width: 48px;
    height: 48px;
    border: 4px solid #E5E7EB;
    border-top-color: var(--wp-admin-theme-color, #2271B1);
    border-radius: 50%;
    animation: scd-spin 0.8s linear infinite;
    margin-bottom: 16px;
}

@keyframes scd-spin {
    to {
        transform: rotate(360deg);
    }
}

.scd-overview-panel__loading p {
    color: #6B7280;
    font-size: 14px;
    margin: 0;
}

/* Error State */
.scd-overview-panel__error {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 60px 20px;
    text-align: center;
}

.scd-overview-panel__error .dashicons {
    width: 48px;
    height: 48px;
    font-size: 48px;
    color: #DC2626;
    margin-bottom: 16px;
}

.scd-overview-panel__error .scd-error-message {
    color: #6B7280;
    font-size: 14px;
    margin: 0 0 20px;
    max-width: 400px;
}

.scd-overview-panel__error .scd-retry-load {
    margin: 0;
}

/* ==========================================================================
   Sections
   ========================================================================== */

.scd-overview-section {
    margin-bottom: 32px;
}

.scd-overview-section:last-child {
    margin-bottom: 0;
}

.scd-section-title {
    font-size: 14px;
    font-weight: 600;
    color: #374151;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin: 0 0 16px;
    padding-bottom: 8px;
    border-bottom: 2px solid #E5E7EB;
}

.scd-section-content {
    color: #111827;
    font-size: 14px;
    line-height: 1.6;
}

/* Section field rows */
.scd-field-row {
    display: flex;
    justify-content: space-between;
    padding: 12px 0;
    border-bottom: 1px solid #F3F4F6;
}

.scd-field-row:last-child {
    border-bottom: none;
}

.scd-field-label {
    font-weight: 500;
    color: #6B7280;
    flex-shrink: 0;
    width: 40%;
}

.scd-field-value {
    color: #111827;
    flex: 1;
    text-align: right;
}

/* Priority stars */
.scd-priority-stars {
    color: #F59E0B;
}

/* Product list */
.scd-product-list {
    list-style: none;
    margin: 0;
    padding: 0;
}

.scd-product-item {
    display: flex;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #F3F4F6;
}

.scd-product-item:last-child {
    border-bottom: none;
}

.scd-product-image {
    width: 40px;
    height: 40px;
    flex-shrink: 0;
    margin-right: 12px;
    border-radius: 4px;
    overflow: hidden;
}

.scd-product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.scd-product-details {
    flex: 1;
}

.scd-product-name {
    font-weight: 500;
    color: #111827;
    display: block;
    margin-bottom: 4px;
}

.scd-product-price {
    color: #6B7280;
    font-size: 13px;
}

/* Performance metrics */
.scd-metrics-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
}

.scd-metric-card {
    background: #F9FAFB;
    border: 1px solid #E5E7EB;
    border-radius: 6px;
    padding: 16px;
    text-align: center;
}

.scd-metric-value {
    font-size: 24px;
    font-weight: 700;
    color: #111827;
    display: block;
    margin-bottom: 4px;
}

.scd-metric-label {
    font-size: 12px;
    color: #6B7280;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

/* ==========================================================================
   Footer
   ========================================================================== */

.scd-overview-panel__footer {
    display: flex;
    gap: 12px;
    padding: 20px 24px;
    background: #F9FAFB;
    border-top: 1px solid #E5E7EB;
    flex-shrink: 0;
}

.scd-overview-panel__footer .button {
    margin: 0;
}

/* ==========================================================================
   Body State
   ========================================================================== */

body.scd-overview-panel-open {
    overflow: hidden;
}

/* ==========================================================================
   Responsive
   ========================================================================== */

/* Tablet */
@media screen and (max-width: 1024px) {
    .scd-overview-panel__container {
        width: 500px;
    }
}

/* Mobile */
@media screen and (max-width: 768px) {
    .scd-overview-panel__container {
        width: 100%;
        max-width: 100%;
    }

    .scd-overview-panel__header,
    .scd-overview-panel__content,
    .scd-overview-panel__footer {
        padding-left: 16px;
        padding-right: 16px;
    }

    .scd-metrics-grid {
        grid-template-columns: 1fr;
    }

    .scd-field-row {
        flex-direction: column;
        gap: 4px;
    }

    .scd-field-label {
        width: 100%;
    }

    .scd-field-value {
        text-align: left;
    }
}

/* ==========================================================================
   Accessibility
   ========================================================================== */

.screen-reader-text {
    clip: rect(1px, 1px, 1px, 1px);
    clip-path: inset(50%);
    height: 1px;
    width: 1px;
    margin: -1px;
    overflow: hidden;
    padding: 0;
    position: absolute;
    word-wrap: normal;
}

/* Focus visible styles */
.scd-overview-panel__close:focus-visible,
.scd-edit-campaign:focus-visible,
.scd-close-panel:focus-visible {
    outline: 2px solid var(--wp-admin-theme-color, #2271B1);
    outline-offset: 2px;
}
```

### 6.6 Modify Campaigns List Table

**File:** `includes/admin/components/class-campaigns-list-table.php`

Add the "View" row action to the existing `row_actions()` method:

```php
/**
 * Generate row actions.
 *
 * @param SCD_Campaign $campaign Campaign object.
 * @return string[] Array of row actions.
 */
protected function get_row_actions( $campaign ) {
    $campaign_id = $campaign->get_id();
    $actions     = array();

    // View action (NEW)
    $actions['view'] = sprintf(
        '<a href="#" class="scd-view-campaign" data-campaign-id="%d">%s</a>',
        $campaign_id,
        __( 'View', 'smart-cycle-discounts' )
    );

    // Edit action
    $edit_url = add_query_arg(
        array(
            'page' => 'scd-campaigns',
            'action' => 'edit',
            'id' => $campaign_id,
        ),
        admin_url( 'admin.php' )
    );
    $actions['edit'] = sprintf(
        '<a href="%s">%s</a>',
        esc_url( $edit_url ),
        __( 'Edit', 'smart-cycle-discounts' )
    );

    // ... rest of existing actions

    return $actions;
}
```

### 6.7 Register Assets

**File:** `includes/admin/assets/class-script-registry.php`

```php
/**
 * Register campaign overview panel script.
 *
 * @since    1.0.0
 */
'campaign-overview-panel' => array(
    'src'          => 'admin/campaign-overview-panel.js',
    'dependencies' => array( 'jquery', 'scd-ajax-service' ),
    'version'      => SCD_VERSION,
    'in_footer'    => true,
    'localize'     => array(
        'name' => 'scdCampaignOverview',
        'data' => array(
            'editUrl' => admin_url( 'admin.php?page=scd-campaigns&action=edit&id=CAMPAIGN_ID' ),
        ),
    ),
    'conditions'   => array(
        'page' => 'scd-campaigns',
    ),
),
```

**File:** `includes/admin/assets/class-style-registry.php`

```php
/**
 * Register campaign overview panel styles.
 *
 * @since    1.0.0
 */
'campaign-overview-panel' => array(
    'src'          => 'admin/campaign-overview-panel.css',
    'dependencies' => array(),
    'version'      => SCD_VERSION,
    'media'        => 'all',
    'conditions'   => array(
        'page' => 'scd-campaigns',
    ),
),
```

### 6.8 Register Services

**File:** `includes/bootstrap/class-service-definitions.php`

```php
/**
 * Campaign Overview Panel component.
 *
 * @since    1.0.0
 */
'campaign_overview_panel' => array(
    'class'        => 'SCD_Campaign_Overview_Panel',
    'file'         => 'includes/admin/components/class-campaign-overview-panel.php',
    'dependencies' => array(
        'campaign_repository',
        'campaign_formatter',
        'analytics_repository',
    ),
),

/**
 * Campaign Overview AJAX handler.
 *
 * @since    1.0.0
 */
'ajax_campaign_overview' => array(
    'class'        => 'SCD_Campaign_Overview_Handler',
    'file'         => 'includes/admin/ajax/handlers/class-campaign-overview-handler.php',
    'dependencies' => array(
        'campaign_repository',
        'campaign_overview_panel',
    ),
),
```

---

## 7. Testing Strategy

### 7.1 Unit Testing

**Manual PHP Testing:**
```php
// Test component instantiation
$panel = new SCD_Campaign_Overview_Panel(
    $campaign_repository,
    $formatter,
    $analytics_repository
);

// Test data preparation
$campaign = $campaign_repository->find( 1 );
$data = $panel->prepare_campaign_data( $campaign );

// Verify data structure
assert( isset( $data['basic'] ) );
assert( isset( $data['schedule'] ) );
assert( isset( $data['products'] ) );
assert( isset( $data['discounts'] ) );
assert( isset( $data['performance'] ) );
```

**JavaScript Unit Tests (if testing framework available):**
```javascript
describe( 'SCD.CampaignOverviewPanel', function() {

    it( 'should open panel when open() is called', function() {
        SCD.CampaignOverviewPanel.open( 123 );
        expect( SCD.CampaignOverviewPanel.isOpen() ).toBe( true );
    } );

    it( 'should load campaign data via AJAX', function( done ) {
        spyOn( SCD.Ajax, 'post' ).and.returnValue( $.Deferred().resolve( {
            success: true,
            data: { /* mock data */ }
        } ) );

        SCD.CampaignOverviewPanel.open( 123 );

        setTimeout( function() {
            expect( SCD.Ajax.post ).toHaveBeenCalledWith( 'campaign_overview', {
                campaign_id: 123
            } );
            done();
        }, 100 );
    } );

} );
```

### 7.2 Integration Testing

**Test Scenarios:**

1. **Open Panel from Campaign List**
   - Navigate to Campaigns page
   - Click "View" on any campaign
   - Verify panel slides in from right
   - Verify backdrop appears
   - Verify loading state shows

2. **Data Loading**
   - Verify AJAX request sent with correct campaign_id
   - Verify loading spinner shows
   - Verify data loads into sections
   - Verify loading state hides

3. **Close Panel**
   - Click close button → panel closes
   - Click backdrop → panel closes
   - Press ESC key → panel closes
   - Verify focus returns to "View" link

4. **Edit Campaign**
   - Click "Edit Campaign" button
   - Verify redirects to wizard with correct campaign_id

5. **Error Handling**
   - Mock AJAX failure
   - Verify error state shows
   - Click "Retry" → re-attempts load

6. **URL Support**
   - Navigate to `?page=scd-campaigns&action=view&campaign_id=123`
   - Verify panel auto-opens
   - Verify URL updates when panel opens
   - Verify URL clears when panel closes

### 7.3 Accessibility Testing

**Tools:**
- **aXe DevTools** - Browser extension for accessibility auditing
- **WAVE** - Web accessibility evaluation tool
- **Keyboard only navigation** - No mouse testing

**Checklist:**
- [ ] Panel has `role="dialog"` and `aria-modal="true"`
- [ ] Title has unique `id` referenced by `aria-labelledby`
- [ ] Description has unique `id` referenced by `aria-describedby`
- [ ] Close button has `aria-label`
- [ ] Focus moves to close button when panel opens
- [ ] Focus trapped within panel (Tab cycles)
- [ ] ESC key closes panel
- [ ] Focus returns to trigger element on close
- [ ] All interactive elements keyboard accessible
- [ ] Screen reader announces panel opening
- [ ] Screen reader announces loading states
- [ ] Screen reader announces errors
- [ ] Color contrast meets WCAG AA (4.5:1 for text)
- [ ] No keyboard traps

### 7.4 Cross-Browser Testing

**Browsers:**
- Chrome (latest)
- Firefox (latest)
- Safari (latest)
- Edge (latest)

**Devices:**
- Desktop (Windows, Mac)
- Tablet (iPad, Android)
- Mobile (iPhone, Android)

**Test Matrix:**
| Browser | Desktop | Tablet | Mobile | Status |
|---------|---------|--------|--------|--------|
| Chrome  | ✅      | ✅     | ✅     | Pass   |
| Firefox | ✅      | ✅     | ✅     | Pass   |
| Safari  | ✅      | ✅     | ✅     | Pass   |
| Edge    | ✅      | ⚠️     | ⚠️     | Test   |

### 7.5 Performance Testing

**Metrics to Measure:**
- Panel open animation: Target <300ms
- AJAX response time: Target <500ms
- Data rendering: Target <200ms
- Total time to interactive: Target <1000ms

**Tools:**
- Browser DevTools Performance tab
- Network tab for AJAX timing
- Lighthouse audit

**Optimization Checklist:**
- [ ] CSS minified
- [ ] JavaScript minified
- [ ] Images optimized (if any)
- [ ] AJAX response cached (5 minutes)
- [ ] No unnecessary re-renders
- [ ] Smooth 60fps animations

---

## 8. Deployment Checklist

### 8.1 Pre-Deployment

**Code Review:**
- [ ] All files follow WordPress coding standards
- [ ] PHP uses Yoda conditions
- [ ] JavaScript is ES5 compatible
- [ ] CSS uses lowercase-hyphen naming
- [ ] All strings are translatable
- [ ] Nonces verified in AJAX handlers
- [ ] Capability checks in place
- [ ] Input sanitized
- [ ] Output escaped
- [ ] Database queries use $wpdb->prepare()

**Documentation:**
- [ ] PHPDoc blocks for all methods
- [ ] Inline comments for complex logic
- [ ] README updated with feature description
- [ ] Changelog updated

**Testing:**
- [ ] All unit tests pass
- [ ] Integration tests pass
- [ ] Accessibility audit passes
- [ ] Cross-browser testing complete
- [ ] Performance benchmarks meet targets
- [ ] No JavaScript console errors
- [ ] No PHP errors/warnings

### 8.2 Deployment Steps

1. **Backup Database**
   ```bash
   wp db export backup-$(date +%Y%m%d).sql
   ```

2. **Enable Maintenance Mode**
   ```php
   // wp-config.php
   define( 'WP_MAINTENANCE_MODE', true );
   ```

3. **Deploy Files**
   ```bash
   # Copy new files to production
   rsync -av --exclude='.git' ./ production/
   ```

4. **Clear Caches**
   ```bash
   wp cache flush
   wp transient delete --all
   ```

5. **Verify Deployment**
   - Test panel opens/closes
   - Test AJAX loading
   - Test error handling
   - Test keyboard navigation

6. **Disable Maintenance Mode**
   ```php
   // Remove from wp-config.php
   // define( 'WP_MAINTENANCE_MODE', true );
   ```

### 8.3 Post-Deployment Monitoring

**Monitor for 24 hours:**
- Error logs for PHP errors
- Browser console for JavaScript errors
- AJAX request failures
- Performance metrics
- User feedback

**Rollback Plan:**
If critical issues arise:
1. Restore database backup
2. Restore previous codebase
3. Clear caches
4. Notify users of rollback

---

## 9. Future Enhancements

### 9.1 Phase 2 Features (Post-MVP)

**Quick Edit in Panel:**
- Allow editing basic fields without opening wizard
- Inline form with validation
- Save via AJAX

**Campaign Duplication:**
- "Duplicate" button in panel footer
- Opens wizard with pre-filled data

**Health Check Integration:**
- Show health status in panel header
- Display critical issues in dedicated section
- Link to health check details

**Campaign Notes:**
- Internal notes section
- Add/edit notes inline
- Timestamp and user attribution

### 9.2 Performance Optimizations

**Caching Strategy:**
- Cache prepared panel data for 5 minutes
- Invalidate on campaign update
- Use transients with campaign_id key

**Lazy Loading:**
- Load performance section on demand (separate AJAX)
- Defer loading product images
- Virtual scrolling for large product lists

**Prefetching:**
- Prefetch adjacent campaign data on hover
- Preload panel structure on page load

### 9.3 Advanced Features

**Comparison Mode:**
- Open multiple panels side-by-side
- Compare campaign configurations
- Highlight differences

**Export Options:**
- Export campaign details to PDF
- Export to CSV for reporting
- Share link with read-only access

**Activity Timeline:**
- Show campaign history
- Track changes over time
- Audit log integration

**Real-time Updates:**
- WebSocket integration for live metrics
- Auto-refresh performance data
- Notification when campaign status changes

---

## 10. Conclusion

This comprehensive implementation plan provides a complete roadmap for building a professional, accessible, and performant campaign overview panel for the Smart Cycle Discounts plugin.

### 10.1 Key Takeaways

1. **Follows Plugin Patterns:** Integrates seamlessly with existing architecture (service container, AJAX router, asset management)
2. **WordPress Standards:** Full compliance with WordPress.org approval requirements
3. **Accessibility First:** WCAG 2.1 AA compliant with keyboard navigation and ARIA attributes
4. **Performance Optimized:** Fast AJAX loading, smooth animations, responsive design
5. **Maintainable:** Clean code structure, comprehensive documentation, reusable components
6. **Extensible:** Architecture allows for future enhancements without refactoring

### 10.2 Estimated Effort

**Total Implementation Time:** 12-16 hours

**Breakdown:**
- Phase 1 (Infrastructure): 3-4 hours
- Phase 2 (JavaScript): 2-3 hours
- Phase 3 (Content Sections): 3-4 hours
- Phase 4 (Accessibility): 2-3 hours
- Phase 5 (Testing): 2 hours

### 10.3 Success Criteria

- ✅ Panel opens in <300ms
- ✅ Zero accessibility violations
- ✅ WordPress.org approval on first submission
- ✅ 100% backward compatible
- ✅ Positive user feedback

---

**Ready to implement? Let's build this! 🚀**
