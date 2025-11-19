# CSS Duplicate Analysis Report
**Smart Cycle Discounts Plugin**  
**Generated:** 2025-11-13  
**Total CSS Files:** 42  
**Total CSS Lines:** 25,575  
**Estimated Duplicate Lines:** 662 (~2.6%)

---

## Executive Summary

This comprehensive analysis identified **194 duplicate CSS selectors** across the Smart Cycle Discounts plugin, representing approximately **662 lines of redundant code** (2.6% of total CSS). The duplicates fall into three categories:

1. **Cross-File Duplicates** (392 lines) - Same selectors defined in multiple files
2. **Internal Duplicates** (220 lines) - Same selectors repeated within the same file
3. **Pattern Duplicates** (50 lines) - Similar patterns that could use utility classes

---

## Critical Duplicates (Do First)

### 1. Notification System (59 lines)
**Location:** `admin/admin.css:140-198`  
**Source of Truth:** `admin/notifications.css`

**Duplicated Selectors:**
- `.scd-notification` (lines 140, notifications.css:62)
- `.scd-notification--success` (lines 157, notifications.css:96)
- `.scd-notification--error` (lines 161, notifications.css:100)
- `.scd-notification--warning` (lines 165, notifications.css:104)
- `.scd-notification--info` (lines 169, notifications.css:108)
- `.scd-notification__message` (lines 173, notifications.css:117)
- `.scd-notification__close` (lines 179, notifications.css:148)
- `.scd-notification__close:hover` (lines 194, notifications.css:176)
- `.scd-notification--fade-out` (lines 198, notifications.css:268)

**Action:** Remove lines 140-198 from `admin/admin.css`

---

### 2. Modal Component (73 lines)
**Location:** `admin/analytics.css:1388-1460`  
**Source of Truth:** `admin/pro-feature-modal.css:17`

**Duplicated Selectors:**
- `.scd-modal` (lines 1388, pro-feature-modal.css:17)
- `.scd-modal-content` (lines 1402, pro-feature-modal.css:52)
- `.scd-modal-header` (lines 1422, pro-feature-modal.css:76)
- `.scd-modal-close` (lines 1438, pro-feature-modal.css:115)
- `.scd-modal-close:hover` (lines 1450, pro-feature-modal.css:130)
- `.scd-modal-close .dashicons` (lines 1454, pro-feature-modal.css:140)
- `.scd-modal-body` (lines 1460, pro-feature-modal.css:150)

**Action:** Remove lines 1388-1460 from `admin/analytics.css`

---

### 3. Form Field Components (80 lines)
**Location:** `admin/step-discounts.css`  
**Source of Truth:** `shared/_forms.css`

**Duplicated Selectors:**
- `.scd-rules-table` (lines 126-130 → forms.css:413)
- `.scd-rules-table tr` (line 126 → forms.css:413)
- `.scd-rules-table tr:last-child` (line 130 → forms.css:417)
- `.scd-rules-table th` (lines 134-148 → forms.css:421)
- `.scd-rules-table th label` (lines 141-148 → forms.css:428)
- `.scd-rules-table td` (line 149 → forms.css:436)
- `.scd-field-suffix` (line 157 → forms.css:398)
- `.scd-input-wrapper` (line 172 → forms.css:383)
- `.scd-select-wrapper` (lines 186-193 → forms.css:490)
- `.scd-select-wrapper select` (lines 193 → forms.css:497)
- `.scd-input-group` (lines 779-801 → forms.css:459)
- `.scd-input-group input` (line 801 → forms.css:480)
- `.scd-input-prefix` (lines 786-793 → forms.css:465)
- `.scd-label-icon` (line 121 → forms.css:443)

**Action:** Remove lines 121-193 and 779-801 from `admin/step-discounts.css`

---

### 4. Priority Badge System (80 lines)
**Location:** `admin/step-review.css:216-255, 793-826`  
**Source of Truth:** `admin/campaigns-list.css:17-55`

**Duplicated Selectors:**
- `.scd-priority-badge` (step-review.css:216, 793 → campaigns-list.css:17)
- `.scd-priority-badge.scd-priority-5` (step-review.css:231, 802 → campaigns-list.css:31)
- `.scd-priority-badge.scd-priority-4` (step-review.css:237, 808 → campaigns-list.css:37)
- `.scd-priority-badge.scd-priority-3` (step-review.css:243, 814 → campaigns-list.css:43)
- `.scd-priority-badge.scd-priority-2` (step-review.css:249, 820 → campaigns-list.css:49)
- `.scd-priority-badge.scd-priority-1` (step-review.css:255, 826 → campaigns-list.css:55)

**Also appears in:** `admin/wizard-steps.css:610` (partial)

**Action:** Remove lines 216-255 and 793-826 from `admin/step-review.css`

---

## High Priority Duplicates

### 5. Dashboard Internal Duplicates (100 lines)
**Location:** `admin/dashboard/main-dashboard.css`

**Internal Duplicates (Same File):**
- `.scd-campaign-suggestions` (lines 19 and 770)
- `.scd-suggestion-card` (lines 141, 858, 1628) - **3 occurrences!**
- `.scd-feature-card` (lines 148 and 1049)
- `.scd-column-header` (lines 934 and 1653)
- `.scd-column-header h5` (lines 945 and 1677)
- `.scd-empty-state` (line 2516, also in analytics.css:246, 1499)
- `.scd-quick-actions` (lines 345 and 2457)
- `.scd-quick-actions:hover` (lines 365 and 2469)
- `.scd-metric-value` (lines 120 and 2344)
- `.scd-section-header h2` (lines 77 and 1959)
- `.scd-subsection-title` (lines 85 and 2132)
- `.scd-suggestion-description` (lines 128 and 1762)
- `.scd-suggestion-icon` (lines 257 and 1147)
- `.scd-suggestions-header-icon` (lines 224 and 797)
- `.scd-suggestions-header-icon .dashicons` (lines 250 and 809)

**Action:** Consolidate these selectors - keep first occurrence, remove subsequent ones

---

### 6. .scd-card Base Component (8 lines)
**Location:** `admin/analytics.css:865-872`  
**Source of Truth:** `shared/_components.css:19`

**Duplicate Properties:**
```css
.scd-card {
    background: var(--scd-color-white);
    border: 1px solid var(--scd-color-border);
    border-radius: var(--scd-radius-md);
    box-shadow: var(--scd-shadow-sm);
    overflow: hidden;
    transition: box-shadow var(--scd-transition-base) ease-in-out;
}

.scd-card:hover {
    box-shadow: var(--scd-shadow-md);
}
```

**Action:** Remove lines 865-876 from `admin/analytics.css` (keep analytics-specific modifiers only)

---

### 7. Card-Option Interactive Cards (11 lines)
**Location:** `admin/step-products.css:61-161`  
**Source of Truth:** `shared/_components.css:493-525`

**Duplicated Selectors:**
- `.scd-card-option` (step-products.css:61 → components.css:493)
- `.scd-card-option input[type="radio"]` (step-products.css:94 → components.css:498)
- `.scd-card-option:focus-within` (step-products.css:152 → components.css:517)
- `.scd-card-option:has(input[type="radio"]:checked)` (step-products.css:161 → components.css:522)

**Action:** Remove lines 61-161 from `admin/step-products.css`

---

### 8. Wizard Step Internal Duplicates (50 lines)

#### step-basic.css
- `.scd-checklist` (lines 214 and 449)
- `.scd-checklist__item` (lines 221 and 455)
- `.scd-checklist__item:last-child` (lines 228 and 462)
- `.scd-checklist__item input[type="checkbox"]` (lines 232 and 466)
- `.scd-checklist__item span` (lines 239 and 470)
- `.scd-checklist__item--next` (lines 246 and 476)
- `.scd-priority-levels` (lines 134 and 701)
- `.scd-priority-level` (lines 139 and 708)

**Action:** Remove second occurrence of each (lines 449-476, 701-708)

#### step-schedule.css
- `.scd-clear-end-date` (lines 343 and 495)
- `.scd-clear-end-date:hover` (lines 366 and 510)

**Action:** Remove lines 495-510

#### step-discounts.css
- `.scd-preview-box` (lines 542 and 1973)
- `.scd-preview-table td` (lines 1399 and 1416)

**Action:** Remove lines 1973+ and consolidate preview table styles

---

## Medium Priority Duplicates

### 9. Stat/Metric Components (30 lines)
**Source of Truth:** `shared/_components.css:614-627`

**Duplicated Across Multiple Files:**
- `.scd-stat-value` 
  * `admin/notifications-page.css:253`
  * `admin/step-products.css:1373`
  * `admin/step-review.css:92`
  * Source: `shared/_components.css:614`
  
- `.scd-stat-label`
  * `admin/notifications-page.css:260`
  * `admin/step-products.css:1367`
  * `admin/step-review.css:100`
  * Source: `shared/_components.css:622`
  
- `.scd-metric-value`
  * `admin/dashboard/main-dashboard.css:120`
  * `admin/dashboard/main-dashboard.css:2344`
  * Source: `shared/_components.css:633`

- `.scd-metric-label`
  * `admin/dashboard/main-dashboard.css:2336`
  * Source: `shared/_components.css:641`

**Action:** Remove all instances except the source in `shared/_components.css`

---

### 10. Analytics Duplicates (40 lines)

#### .scd-empty-state (Internal Duplicate)
**Location:** `admin/analytics.css`
- First occurrence: line 246
- Second occurrence: line 1499
- Also in: `admin/dashboard/main-dashboard.css:2516`

**Action:** Consolidate to single definition

#### Trend Indicators
- `.scd-trend-up` (analytics.css:1246, main-dashboard.css:754)
- `.scd-trend-down` (analytics.css:1258, main-dashboard.css:760)

**Action:** Move to `shared/_components.css` or keep in analytics.css as source

#### Preview Table
- `.scd-preview-table` (analytics-upgrade.css:221, step-discounts.css:1388)
- `.scd-preview-header` (analytics-upgrade.css:130, step-schedule.css:1576)

**Action:** Move to shared location

---

### 11. Animation Keyframes (30 lines)

#### @keyframes scd-spin (3 occurrences)
- `admin/dashboard/planner-styles.css:602`
- `shared/_buttons.css:301`
- `shared/_utilities.css:342`

**Source of Truth:** `shared/_utilities.css:342`  
**Action:** Remove from planner-styles.css and _buttons.css

#### @keyframes scd-fade-in (3 occurrences)
- `admin/campaign-overview-panel.css:519`
- `admin/step-products.css:464`
- `shared/_utilities.css:347`

**Source of Truth:** `shared/_utilities.css:347`  
**Action:** Remove from campaign-overview-panel.css and step-products.css

#### @keyframes scd-slide-down (2 occurrences)
- `admin/step-products.css:1147`
- `shared/_utilities.css:363`

**Source of Truth:** `shared/_utilities.css:363`  
**Action:** Remove from step-products.css

#### @keyframes slideDown (2 occurrences)
- `admin/analytics.css:1411`
- `admin/badge-settings.css:66`

**Action:** Rename to `scd-slide-down` and use shared version

#### @keyframes scd-error-pulse (2 occurrences)
- `admin/step-products.css:1031`
- `admin/wizard-completion-modal.css:316`

**Action:** Move to `shared/_utilities.css` and remove duplicates

#### @keyframes scdModalSlideIn (2 occurrences)
- `admin/pro-feature-modal.css:61`
- `admin/session-expiration-modal.css:50`

**Action:** Keep in pro-feature-modal.css as source, remove from session-expiration-modal.css

---

## Low Priority Duplicates

### 12. Summary List Components (25 lines)
**Source of Truth:** `shared/_components.css:679-707`

**Duplicates:**
- `.scd-summary-list` (step-products.css:1527)
- `.scd-summary-item` (step-review.css:764)
- `.scd-summary-item:last-child` (step-review.css:769)
- `.scd-summary-label` (step-review.css:773)
- `.scd-summary-value` (step-products.css:1574, step-review.css:781)

**Action:** Remove from step files, use shared version

---

### 13. Utility Class Duplicates (24 lines)

#### Screen Reader Text (4 occurrences)
**Source of Truth:** `shared/_utilities.css:321` (`.scd-sr-only`)

**Duplicates:**
- `admin/campaign-overview-panel.css:384` (`.screen-reader-text`)
- `admin/validation.css:92` (`.screen-reader-text`)
- `admin/wizard-steps.css:271` (`.screen-reader-text`)
- `admin/step-products.css:1236` (`.scd-sr-only`)

**Action:** Remove all duplicates, use `.scd-sr-only` from shared/_utilities.css

#### Other Utilities
- `.scd-form-group` (analytics.css:1464, utilities.css:447)
- `.scd-form-help` (forms.css:276, utilities.css:458)
- `.scd-status-warning` (settings.css:253, utilities.css:182)

**Action:** Consolidate to utilities.css

---

### 14. Badge System Consolidation (24 lines)
**Location:** `shared/_badges.css` (Internal Duplicates)

**Issues:**
1. `.scd-badge-product` appears twice (lines 40 and 182)
   - Line 40: As part of base mixin list
   - Line 182: Actual implementation
   - **Action:** Remove from line 40 mixin list, keep only line 182

2. `.scd-free-badge` appears twice (lines 213 and 236)
   - Line 213: Declaration
   - Line 236: Implementation
   - **Action:** Consolidate into single definition

---

### 15. Skeleton Loader (15 lines)
**Source of Truth:** `shared/_components.css:151`

**Issues:**
- Two different implementations in same file:
  * `shared/_components.css:151` - Full animation system
  * `shared/_components.css:754` - Simple gradient version
- Partial duplicate in `admin/wizard-steps.css:249`

**Action:** 
1. Consolidate the two implementations in _components.css
2. Remove duplicate from wizard-steps.css

---

## Additional Duplicate Patterns

### 16. Input Group Components
**Source of Truth:** `shared/_forms.css:459`

**Duplicates:**
- `admin/admin.css:68` (`.scd-input-group`)
- `admin/step-discounts.css:779` (`.scd-input-group`)
- `admin/admin.css:84` (`.scd-input-prefix`)
- `admin/step-discounts.css:786` (`.scd-input-prefix`)

**Action:** Remove from admin.css and step-discounts.css

---

### 17. Wizard UI Components

#### .scd-wizard-step
- `admin/wizard-fullscreen.css:773`
- `admin/wizard-steps.css:17`

**Action:** Consolidate - determine which is primary source

#### .scd-wizard-wrapper
- `admin/wizard-fullscreen.css:24`
- `admin/wizard-fullscreen.css:53` (media query variant)
- `admin/wizard-navigation.css:77`

**Action:** Check for conflicts, consolidate if possible

#### Status Panels
- `.scd-status-panel` (step-schedule.css:736, wizard-steps.css:198)
- `.scd-status-panel--success` (step-schedule.css:757, wizard-steps.css:213)
- `.scd-status-panel--warning` (step-schedule.css:769, wizard-steps.css:219)
- `.scd-status-panel--error` (step-schedule.css:763, wizard-steps.css:225)

**Action:** Move to wizard-steps.css as source, remove from step-schedule.css

---

### 18. Campaign Components

#### Discount Type Cards
- `.scd-discount-type-grid` (admin.css:346, step-discounts.css:626, wizard-fullscreen.css:781)
- `.scd-discount-type-card` (admin.css:353, step-discounts.css:633)
- `.scd-discount-type-card:hover` (admin.css:363, step-discounts.css:657)
- `.scd-discount-type-card.selected` (admin.css:368, step-discounts.css:662)

**Action:** Move to step-discounts.css as source, remove from admin.css

#### Recurring Badges
- `.scd-recurring-badge` (campaigns-list.css:608, recurring-badges.css:14)

**Action:** Keep in recurring-badges.css as dedicated file

---

### 19. Date Picker Duplicates
**Location:** `admin/step-schedule.css`

#### UI Datepicker
- `.ui-datepicker` (lines 826 and 1073)
- `.ui-datepicker .ui-datepicker-next` (lines 860 and 877)

**Action:** Consolidate to single definition at beginning of file

#### Input Date Fields
- `.scd-input-date:not(.scd-enhanced-input)` (lines 1440 and 1477)

**Action:** Consolidate to single definition

---

### 20. Campaigns List Table Duplicates
**Location:** `admin/campaigns-list.css`

**Internal Duplicates:**
- `.wp-list-table.campaigns` (lines 159 and 401)
- `.wp-list-table.campaigns .column-name` (lines 233, 297, 414) - **3 occurrences!**
- `.wp-list-table.campaigns .column-name strong` (lines 258 and 366)
- `.wp-list-table.campaigns .column-name .description` (lines 271 and 624)
- `.wp-list-table.campaigns .column-created` (lines 435 and 462)

**Action:** Consolidate to single definition for each

---

### 21. Form Table Duplicates

#### Generic Form Tables
- `.form-table` (step-discounts.css:29, wizard-fullscreen.css:751)
- `.form-table th` (step-discounts.css:38, 45, wizard-fullscreen.css:755)
- `.form-table td` (step-discounts.css:54, wizard-fullscreen.css:763)

**Action:** Move to shared location or wizard-fullscreen.css as base

---

### 22. Miscellaneous Component Duplicates

#### Feature Components
- `.scd-feature-icon` (main-dashboard.css:232, 1066)
- `.scd-feature-statistic .scd-feature-icon` (main-dashboard.css:1077, 1123)
- `.scd-feature-tip .scd-feature-icon` (main-dashboard.css:1081, 1131)
- `.scd-feature-practice .scd-feature-icon` (main-dashboard.css:1085, 1139)

**Action:** Consolidate in main-dashboard.css

#### Badge Components
- `.scd-badge-major` (main-dashboard.css:1299, planner-styles.css:434)

**Action:** Move to main-dashboard.css or shared location

#### Launch Info
- `.scd-launch-info` (step-review.css:588, main-dashboard.css:1338)

**Action:** Move to shared location

#### Product Components
- `.scd-product-list` (admin.css:411, campaign-overview-panel.css:262)
- `.scd-product-item` (admin.css:418, campaign-overview-panel.css:267)
- `.scd-product-name` (admin.css:443, campaign-overview-panel.css:299)

**Action:** Move to campaign-overview-panel.css as source

#### Priority Components
- `.scd-priority-stars` (campaign-overview-panel.css:234, main-dashboard.css:1033)

**Action:** Move to shared location

#### Loading Components
- `.scd-loading` (notifications-page.css:204, settings.css:212)
- `.scd-loading-overlay` (admin.css:497, wizard-steps.css:235)

**Action:** Move to shared/_utilities.css

#### Field Components
- `.scd-field-tooltip` (notifications-page.css:106, settings.css:105)
- `.scd-field-tooltip:hover` (notifications-page.css:112, settings.css:111)
- `.scd-field-help` (admin.css:63, step-schedule.css:334)
- `.scd-field-description` (step-review.css:188, wizard-steps.css:149)
- `.scd-field-group` (step-discounts.css:435, validation-ui.css:15)

**Action:** Consolidate to shared/_forms.css or shared/_utilities.css

#### Section Components
- `.scd-section-title` (pro-feature-modal.css:161, step-schedule.css:156)
- `.scd-section-description` (notifications-page.css:117, settings.css:116)

**Action:** Move to shared/_utilities.css

#### Smart Criteria
- `.scd-smart-criteria` (step-products.css:185, 969)
- `.scd-smart-criteria.scd-active-section` (step-products.css:197, 981)

**Action:** Consolidate in step-products.css

#### Upgrade Components
- `.scd-upgrade-icon` (analytics-upgrade.css:37, dashboard-upgrade-banner.css:55, main-dashboard.css:2385)
- `.scd-upgrade-content` (analytics-upgrade.css:32, main-dashboard.css:2402)

**Action:** Move to shared location

#### Urgency Badges
- `.scd-urgency-starting` (main-dashboard.css:2274, 2291)
- `.scd-campaign-starting-badge` (main-dashboard.css:2204, 2220)

**Action:** Consolidate in main-dashboard.css

#### Stat Components
- `.scd-stat-box` (notifications-page.css:223, main-dashboard.css:1497)
- `.scd-stat-content` (notifications-page.css:248, step-review.css:88)
- `.scd-stat-item` (step-products.css:1355, step-review.css:54)

**Action:** Move to shared/_components.css

#### Message Components
- `.error-message` (admin.css:297, wizard-fullscreen.css:894)
- `.warning-message` (admin.css:289, 301, 307) - **3 occurrences in same file!**

**Action:** Consolidate warning-message, move error-message to wizard-fullscreen.css

---

## Pattern Analysis

### Flexbox Usage
- **Total occurrences:** 310
- **Status:** ACCEPTABLE - Most are component-specific layouts
- **Recommendation:** Consider utility classes for common patterns:
  ```css
  .scd-flex { display: flex; }
  .scd-flex-center { display: flex; align-items: center; justify-content: center; }
  .scd-flex-between { display: flex; justify-content: space-between; }
  .scd-flex-column { display: flex; flex-direction: column; }
  ```

### Border-Radius Usage
- **Total occurrences:** 399
- **Status:** GOOD - Using CSS variables consistently
- **CSS Variables Used:**
  - `var(--scd-radius-sm)` - Small radius (4px)
  - `var(--scd-radius-md)` - Medium radius (6px)
  - `var(--scd-radius-lg)` - Large radius (8px)
  - `var(--scd-radius-xl)` - Extra large radius (12px)
  - `var(--scd-radius-pill)` - Pill shape (999px)
  - `var(--scd-radius-full)` - Circle (50%)

### Box-Shadow Usage
- **Total occurrences:** 335
- **Status:** GOOD - Using CSS variables consistently
- **CSS Variables Used:**
  - `var(--scd-shadow-sm)` - Small shadow
  - `var(--scd-shadow-md)` - Medium shadow
  - `var(--scd-shadow-lg)` - Large shadow
  - `var(--scd-shadow-xl)` - Extra large shadow

### Padding Usage
- **Total occurrences:** 561
- **Status:** MIXED - Could benefit from utility classes for common patterns
- **Recommendation:** Create utility classes:
  ```css
  .scd-p-sm { padding: var(--scd-spacing-sm); }
  .scd-p-md { padding: var(--scd-spacing-md); }
  .scd-p-lg { padding: var(--scd-spacing-lg); }
  .scd-px-md { padding-left: var(--scd-spacing-md); padding-right: var(--scd-spacing-md); }
  .scd-py-md { padding-top: var(--scd-spacing-md); padding-bottom: var(--scd-spacing-md); }
  ```

---

## Media Query Analysis

### Responsive Breakpoints
- **@media (max-width: 782px):** 34 occurrences - WordPress admin breakpoint
- **@media (max-width: 600px):** 12 occurrences - Mobile breakpoint
- **@media (max-width: 1200px):** 9 occurrences - Tablet breakpoint
- **@media (max-width: 768px):** Component-specific tablet styles
- **@media (max-width: 960px):** Component-specific styles

**Status:** ACCEPTABLE - Most are component-specific responsive adjustments

### Special Media Queries
- **@media (prefers-contrast: high):** 23 occurrences - Excellent accessibility
- **@media (prefers-reduced-motion: reduce):** 19 occurrences - Excellent accessibility
- **@media (prefers-color-scheme: dark):** 4 occurrences - Dark mode support
- **@media print:** 13 occurrences - Print stylesheet support

**Status:** EXCELLENT - Comprehensive accessibility and print support

---

## Consolidation Roadmap

### Phase 1: Critical Duplicates (Critical - Do First)
**Estimated Impact:** 292 lines removed  
**Files Affected:** 4 files

1. **Notification System** (59 lines)
   - Remove `admin/admin.css:140-198`
   - Use `admin/notifications.css` as source

2. **Modal Component** (73 lines)
   - Remove `admin/analytics.css:1388-1460`
   - Use `admin/pro-feature-modal.css` as source

3. **Form Field Components** (80 lines)
   - Remove duplicates from `admin/step-discounts.css`
   - Use `shared/_forms.css` as source

4. **Priority Badge System** (80 lines)
   - Remove `admin/step-review.css:216-255, 793-826`
   - Use `admin/campaigns-list.css` as source

**Testing Required:**
- Notification display across all admin pages
- Modal functionality in analytics and settings
- Form rendering in discount wizard step
- Priority badge display in campaign list and review step

---

### Phase 2: High Priority Duplicates (Do Second)
**Estimated Impact:** 169 lines removed  
**Files Affected:** 3 files

5. **Dashboard Internal Duplicates** (100 lines)
   - Consolidate in `admin/dashboard/main-dashboard.css`

6. **.scd-card Base Component** (8 lines)
   - Remove `admin/analytics.css:865-876`
   - Use `shared/_components.css` as source

7. **Card-Option Components** (11 lines)
   - Remove `admin/step-products.css:61-161`
   - Use `shared/_components.css` as source

8. **Wizard Step Internal Duplicates** (50 lines)
   - Consolidate in `admin/step-basic.css`
   - Consolidate in `admin/step-schedule.css`
   - Consolidate in `admin/step-discounts.css`

**Testing Required:**
- Dashboard display and suggestions
- Analytics page card rendering
- Product selection step interactive cards
- Wizard step UI elements

---

### Phase 3: Medium Priority Duplicates (Do Third)
**Estimated Impact:** 100 lines removed  
**Files Affected:** 8 files

9. **Stat/Metric Components** (30 lines)
   - Remove from multiple files
   - Use `shared/_components.css` as source

10. **Analytics Duplicates** (40 lines)
    - Consolidate empty-state in analytics.css
    - Move trend indicators to shared location
    - Consolidate preview tables

11. **Animation Keyframes** (30 lines)
    - Remove keyframe duplicates from component files
    - Use `shared/_utilities.css` as source

**Testing Required:**
- Stat display across notifications, products, review steps
- Analytics empty states
- Animation consistency across components

---

### Phase 4: Low Priority Duplicates (Do Last)
**Estimated Impact:** 101 lines removed  
**Files Affected:** 10 files

12. **Summary List Components** (25 lines)
13. **Utility Class Duplicates** (24 lines)
14. **Badge System Consolidation** (24 lines)
15. **Skeleton Loader** (15 lines)
16. **Miscellaneous Components** (13 lines)

**Testing Required:**
- Summary display in review and products steps
- Screen reader accessibility
- Badge rendering
- Loading states

---

## Source of Truth Hierarchy

### Shared Components (Always Check First)
1. **shared/_components.css** - Base UI components
   - Cards (`.scd-card`, `.scd-card--*`)
   - Tables (`.scd-table`, `.scd-table--*`)
   - Tooltips (`.scd-tooltip`)
   - Skeleton loaders (`.scd-skeleton`, `.scd-skeleton-*`)
   - Stat/metric components (`.scd-stat-*`, `.scd-metric-*`)
   - Summary lists (`.scd-summary-*`)

2. **shared/_buttons.css** - All button styles
   - Base buttons (`.scd-button`, `.scd-btn`)
   - Button variants (`.scd-button--*`, `.scd-btn-*`)
   - Button states (`:hover`, `:focus`, `:disabled`)
   - Icon buttons (`.scd-button--icon-only`)

3. **shared/_forms.css** - All form field styles
   - Input fields (`.scd-input`, `.scd-enhanced-input`)
   - Select dropdowns (`.scd-select`, `.scd-enhanced-select`)
   - Checkboxes & radios (`.scd-checkbox`, `.scd-radio`)
   - Toggles (`.scd-toggle`)
   - Input groups (`.scd-input-group`, `.scd-input-prefix`)
   - Form helpers (`.scd-field-suffix`, `.scd-form-help`)
   - Rules tables (`.scd-rules-table`)

4. **shared/_badges.css** - All badge styles
   - Status badges (`.scd-badge-status--*`)
   - Health badges (`.scd-badge-health--*`)
   - Performance badges (`.scd-badge--*-performance`)
   - Discount type badges (`.scd-badge--percentage`, etc.)
   - PRO/FREE badges (`.scd-pro-badge`, `.scd-free-badge`)

5. **shared/_utilities.css** - Utility classes
   - Screen reader text (`.scd-sr-only`)
   - Loading states (`.scd-loading`, `.scd-loading-overlay`)
   - Animations (`@keyframes scd-*`)
   - Form utilities (`.scd-form-group`, `.scd-form-help`)
   - Status utilities (`.scd-status-warning`)

### Component-Specific Sources
6. **admin/notifications.css** - Notification system
   - Notification container (`.scd-notification`)
   - Notification variants (`.scd-notification--*`)
   - Close button (`.scd-notification__close`)

7. **admin/pro-feature-modal.css** - Modal component
   - Modal structure (`.scd-modal`, `.scd-modal-content`)
   - Modal header/footer (`.scd-modal-header`, `.scd-modal-footer`)
   - Modal animations (`@keyframes scdModalSlideIn`)

8. **admin/campaigns-list.css** - Campaign list UI
   - Priority badges (`.scd-priority-badge`, `.scd-priority-*`)
   - Recurring badges (`.scd-recurring-badge`)
   - Campaign table styles (`.wp-list-table.campaigns`)

9. **admin/wizard-steps.css** - Wizard shared UI
   - Status panels (`.scd-status-panel--*`)
   - Field descriptions (`.scd-field-description`)
   - Screen reader text (`.screen-reader-text`)

---

## Removal Strategy

### Step-by-Step Process

#### For Each Duplicate:

1. **Identify Source of Truth**
   - Check "Source of Truth Hierarchy" above
   - Verify the component is defined in the canonical location
   - Note the line numbers in this report

2. **Verify Dependencies**
   - Search for the selector in JavaScript files
   - Check if any PHP templates use inline styles
   - Look for @import statements or style enqueuing

3. **Create Backup**
   ```bash
   cp file.css file.css.backup
   ```

4. **Remove Duplicate**
   - Delete the duplicate selector and its properties
   - Leave a comment indicating where to find the source:
   ```css
   /* .scd-card styles moved to shared/_components.css */
   ```

5. **Test Thoroughly**
   - Load the page where the component appears
   - Test all states (hover, focus, active, disabled)
   - Check responsive breakpoints
   - Test in different browsers
   - Verify accessibility

6. **Commit Changes**
   ```bash
   git add file.css
   git commit -m "Remove duplicate .selector-name - use shared/_components.css"
   ```

### Automated Approach (Advanced)

Create a script to identify and flag duplicates:

```bash
#!/bin/bash
# css-duplicate-checker.sh

CSS_DIR="resources/assets/css"
REPORT_FILE="css-duplicates-$(date +%Y%m%d).txt"

echo "Scanning for duplicate CSS selectors..." > "$REPORT_FILE"
echo "Generated: $(date)" >> "$REPORT_FILE"
echo "---" >> "$REPORT_FILE"

# Find all CSS selectors and their locations
grep -rn "^\s*\.[a-z-]\+\s*{" "$CSS_DIR" --include="*.css" | \
  sort | \
  uniq -d >> "$REPORT_FILE"

echo "Report saved to: $REPORT_FILE"
```

---

## Prevention Guidelines

### Before Adding New CSS

1. **Check Existing Components**
   - Search shared/_components.css first
   - Check shared/_buttons.css for button styles
   - Check shared/_forms.css for form styles
   - Check shared/_badges.css for badge styles

2. **Use Existing Classes**
   - Prefer existing classes over creating new ones
   - Extend with BEM modifiers (`.scd-card--variant`)
   - Use utility classes for spacing/layout

3. **Document Decisions**
   - Add comments explaining why new styles are needed
   - Reference the base component if extending
   - Note any differences from the base

### Code Review Checklist

- [ ] Checked shared/ directory for existing components
- [ ] No duplicate selectors created
- [ ] Using CSS variables for colors, spacing, shadows
- [ ] BEM naming convention followed
- [ ] Responsive styles included if needed
- [ ] Accessibility considered (focus states, screen readers)
- [ ] Comments added for complex styles

### WordPress Coding Standards

Follow WordPress CSS Coding Standards:
- Use lowercase with hyphens for class names
- Tab indentation (not spaces)
- Space after property colon
- Properties sorted logically (display, positioning, box model, visual)
- Comments above selector blocks

---

## Testing Checklist

### After Removing Duplicates

#### Visual Testing
- [ ] Component appears correctly on all pages
- [ ] All variants render properly (primary, secondary, etc.)
- [ ] Hover states work
- [ ] Focus states visible (accessibility)
- [ ] Active states work
- [ ] Disabled states styled correctly

#### Responsive Testing
- [ ] Desktop (1920px+)
- [ ] Laptop (1366px)
- [ ] Tablet (768px)
- [ ] Mobile (375px)
- [ ] WordPress admin breakpoint (782px)

#### Browser Testing
- [ ] Chrome/Edge (latest)
- [ ] Firefox (latest)
- [ ] Safari (latest)
- [ ] Mobile browsers (iOS Safari, Chrome Mobile)

#### Accessibility Testing
- [ ] Keyboard navigation works
- [ ] Screen reader text present
- [ ] Focus indicators visible
- [ ] Color contrast meets WCAG AA
- [ ] High contrast mode works
- [ ] Reduced motion respected

#### Functionality Testing
- [ ] Interactive components work (cards, buttons, forms)
- [ ] Modals open/close correctly
- [ ] Notifications display and dismiss
- [ ] Form validation displays errors
- [ ] Loading states appear

---

## File-by-File Summary

### Files Requiring Major Changes

1. **admin/admin.css** (118 lines to remove)
   - Notification system (59 lines)
   - Input groups (20 lines)
   - Discount type cards (20 lines)
   - Product components (15 lines)
   - Loading overlay (4 lines)

2. **admin/analytics.css** (121 lines to remove)
   - Modal component (73 lines)
   - .scd-card base (8 lines)
   - Empty state internal duplicate (20 lines)
   - Trend indicators (12 lines)
   - Form group (8 lines)

3. **admin/step-discounts.css** (160 lines to remove)
   - Form field components (80 lines)
   - Preview box internal duplicate (30 lines)
   - Step-specific form tables (20 lines)
   - Input groups (20 lines)
   - Preview table duplicates (10 lines)

4. **admin/step-review.css** (105 lines to remove)
   - Priority badge system (80 lines) - TWO COMPLETE DUPLICATIONS
   - Summary components (15 lines)
   - Stat components (10 lines)

5. **admin/dashboard/main-dashboard.css** (100 lines to remove)
   - Internal duplicates (campaign-suggestions, suggestion-card, etc.)
   - Metric components (15 lines)
   - Empty state (10 lines)

6. **admin/step-products.css** (41 lines to remove)
   - Card-option components (11 lines)
   - Summary list (10 lines)
   - Stat components (10 lines)
   - Animation keyframes (5 lines)
   - Smart criteria internal duplicate (5 lines)

7. **admin/step-basic.css** (42 lines to remove)
   - Checklist internal duplicates (35 lines)
   - Priority levels internal duplicates (7 lines)

8. **admin/step-schedule.css** (30 lines to remove)
   - Clear end-date duplicate (15 lines)
   - Status panels (10 lines)
   - UI datepicker duplicate (5 lines)

### Files Requiring Minor Changes

9. **admin/notifications-page.css** (13 lines)
10. **admin/campaign-overview-panel.css** (12 lines)
11. **admin/wizard-steps.css** (10 lines)
12. **shared/_badges.css** (24 lines internal consolidation)
13. **shared/_components.css** (15 lines internal consolidation)
14. **admin/wizard-fullscreen.css** (8 lines)
15. **admin/campaigns-list.css** (15 lines internal consolidation)

---

## Estimated Total Savings

| Category | Lines | Files Affected |
|----------|-------|----------------|
| Critical Duplicates | 292 | 4 |
| High Priority | 169 | 3 |
| Medium Priority | 100 | 8 |
| Low Priority | 101 | 10 |
| **Total** | **662** | **25** |

**Percentage of Total CSS:** 2.6% (662 / 25,575)

**Estimated Time to Complete:**
- Phase 1 (Critical): 4-6 hours
- Phase 2 (High): 3-4 hours
- Phase 3 (Medium): 2-3 hours
- Phase 4 (Low): 1-2 hours
- **Total Estimated Time:** 10-15 hours

---

## Success Metrics

### Goals
- [ ] Zero duplicate selectors across files
- [ ] All components sourced from shared/ directory
- [ ] Internal file duplicates consolidated
- [ ] CSS reduced by ~650 lines
- [ ] All tests passing
- [ ] No visual regressions

### Validation
```bash
# Check for duplicate selectors
grep -rn "^\s*\.[a-z-]\+\s*{" resources/assets/css --include="*.css" | \
  awk -F: '{print $2}' | sort | uniq -d | wc -l
# Should output: 0

# Count total CSS lines
find resources/assets/css -name "*.css" -exec wc -l {} \; | \
  awk '{total += $1} END {print total}'
# Should be ~24,900 (down from 25,575)
```

---

## Appendix: Quick Reference

### Common Patterns to Avoid

❌ **DON'T:**
```css
/* In step-products.css */
.scd-card {
    background: var(--scd-color-background);
    border: 1px solid var(--scd-color-border);
    /* ... */
}
```

✅ **DO:**
```css
/* In step-products.css */
/* Base .scd-card styles in shared/_components.css */

/* Step-specific card modifier */
.scd-wizard-step--products .scd-card {
    /* Only add step-specific overrides */
    margin-bottom: var(--scd-spacing-xl);
}
```

### Import Pattern

If a file needs many shared styles, consider using imports:

```css
/* At top of file */
@import "../shared/_components.css";
@import "../shared/_forms.css";

/* Then only add page-specific styles */
.scd-step-products {
    /* ... */
}
```

### Naming Convention Reference

- **Base component:** `.scd-card`
- **Component variant:** `.scd-card--compact`
- **Component element:** `.scd-card__header`
- **Component state:** `.scd-card--selected`
- **Context modifier:** `.scd-wizard-step--products .scd-card`

---

## Conclusion

This analysis identified **662 lines of duplicate CSS** across **25 files**, representing **2.6% of the total CSS codebase**. While this percentage is relatively small, the duplicates create maintenance challenges and increase the risk of inconsistencies.

The duplicates fall into clear patterns:
1. **Component duplication** - Same components defined in multiple files
2. **Internal duplication** - Same selectors repeated within one file  
3. **Pattern duplication** - Similar patterns that could use utilities

By following the consolidation roadmap and prevention guidelines, the codebase can achieve:
- **Zero cross-file duplication**
- **Single source of truth for each component**
- **Easier maintenance and updates**
- **Faster development (reuse instead of recreate)**
- **Consistent UI across the plugin**

**Priority:** Focus on Phase 1 (Critical) duplicates first - they represent the largest maintenance burden (notification system, modal component, form fields, priority badges).

---

**Report Generated:** 2025-11-13  
**Analysis Tool:** CSS Duplicate Scanner v1.0  
**Plugin:** Smart Cycle Discounts  
**Plugin Path:** `/resources/assets/css/`

