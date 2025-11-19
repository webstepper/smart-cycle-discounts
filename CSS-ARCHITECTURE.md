# Smart Cycle Discounts - CSS Architecture

Complete Structure & Dependency Map

---

## Layer 1: Foundation (Priority 1-3)

**Purpose:** Base variables, theme colors, and utilities that ALL other styles depend on

### 1. scd-theme-colors (Priority 1)
- **File:** `shared/_theme-colors.css`
- **Deps:** NONE (root dependency)
- **Purpose:** WordPress admin color scheme variables (`--wp-admin-theme-color-*`)
- **Scope:** ALL plugin pages

### 2. scd-variables (Priority 2)
- **File:** `shared/_variables.css`
- **Deps:** `scd-theme-colors`
- **Content:** Classic/WordPress Native Base Values:
  - Typography (fonts, sizes, weights, line-heights)
  - Spacing (xxs → xxxl: 2px → 40px)
  - Layout (containers, component heights, navigation)
  - Borders & Radius (0 → 4px, sharp corners)
  - **Shadows:** NONE (WordPress native = flat design)
  - **Transitions:** 0ms (instant interactions)
  - Colors (text, background, surface, borders)
  - Z-index scale (base → notification: 1 → 9999)
- **Scope:** ALL plugin pages

### 3. scd-theme (Priority 2) - DYNAMIC LOADING
- **Files:** `themes/theme-{classic|enhanced}.css`
- **Deps:** `scd-variables`, `scd-theme-colors`
- **Logic:** PHP determines theme from settings (default: 'classic')

#### Option A: theme-classic.css (41 lines - MINIMAL)
- NO variable overrides (uses base WordPress native values)
- ONLY !important rules for hardcoded transitions/animations
- WordPress native focus styles
- When active: `body.scd-theme-classic`

#### Option B: theme-enhanced.css (151 lines - COMPREHENSIVE)
Overrides ALL Classic values with Enhanced modern values:
- Icons: 16px → 18px
- Buttons: 30px → 40px
- Navigation: 60px → 80px
- Radius: 0-4px → 4-16px (rounded corners)
- Shadows: none → enabled (depth & hierarchy)
- Transitions: 0ms → 0.1-0.5s (smooth animations)
- Spacing: Compact → Generous
- Visual enhancements: hover effects, transforms
- When active: `body.scd-theme-enhanced`

### 4. scd-utilities (Priority 3)
- **File:** `shared/_utilities.css`
- **Deps:** `scd-variables`
- **Purpose:** Utility classes (spacing, display, text alignment, colors)
- **Scope:** ALL plugin pages

---

## Layer 2: Shared Components (Priority 4-8)

**Purpose:** Reusable UI components used across multiple pages

### Priority 4
- **scd-badges** - `shared/_badges.css` (Deps: scd-variables)
- **scd-loader** - `shared/loader.css` (Deps: scd-variables)

### Priority 5
- **scd-components** - `shared/_components.css`
  - Deps: scd-variables, scd-utilities, scd-badges
  - Components: Cards, panels, lists, tabs, modals

### Priority 6-7
- **scd-forms** (Priority 6) - `shared/_forms.css` (Deps: scd-variables, scd-theme-colors)
- **scd-buttons** (Priority 7) - `shared/_buttons.css` (Deps: scd-variables, scd-theme-colors)

### Priority 8
- **scd-pro-feature-unavailable** - `shared/pro-feature-unavailable.css`
  - Deps: scd-variables, scd-components
  - Scope: scd-campaigns only

---

## Layer 3: Base Admin (Priority 10)

**Purpose:** Foundation admin styles that page-specific CSS extends

### scd-admin (Priority 10)
- **File:** `admin/admin.css`
- **Deps:** scd-variables, scd-utilities, scd-components, scd-forms, scd-buttons, scd-badges
- **Purpose:** Base admin interface styles
- **Scope:** scd-campaigns, scd-analytics

### Admin Extensions
- **scd-validation-ui** (Deps: scd-admin)
- **scd-validation** (Deps: scd-admin)
- **scd-notifications** (Deps: scd-admin)

---

## Layer 4: Page-Specific Styles (Priority 10-16)

**Purpose:** Styles for specific admin pages

### Dashboard Page (scd-dashboard)
1. **scd-main-dashboard** (Priority 15)
   - File: `admin/dashboard/main-dashboard.css`
   - Deps: scd-theme-colors, scd-variables, scd-utilities, scd-badges, scd-components, scd-forms, scd-buttons

2. **scd-planner-styles** (Priority 16)
   - File: `admin/dashboard/planner-styles.css`
   - Deps: scd-main-dashboard

3. **scd-dashboard-upgrade-banner** (Priority 11)
   - File: `admin/dashboard-upgrade-banner.css`

### Campaigns Page (scd-campaigns)
1. **scd-campaigns-list** (Priority 10)
   - File: `admin/campaigns-list.css`
   - Deps: scd-admin

2. **scd-campaign-overview-panel** (Priority 10)
   - File: `admin/campaign-overview-panel.css`
   - Deps: scd-admin, scd-badges

3. **scd-recurring-badges** (Priority 10)
   - File: `admin/recurring-badges.css`
   - Deps: scd-admin

### Settings Page (scd-settings)
- **scd-settings** (Priority 15)
  - File: `admin/settings.css`
  - Deps: scd-theme-colors, scd-variables, scd-utilities, scd-badges, scd-components, scd-forms, scd-buttons

### Tools Page (scd-tools)
- **scd-tools** (Priority 15)
  - File: `admin/tools.css`
  - Deps: scd-theme-colors, scd-variables, scd-utilities, scd-forms, scd-buttons

### Analytics Page (scd-analytics)
1. **scd-analytics** (Priority 15)
   - File: `admin/analytics.css`
   - Deps: scd-admin

2. **scd-analytics-upgrade** (Priority 10)
   - File: `admin/analytics-upgrade.css`
   - Deps: NONE

---

## Layer 5: Wizard-Specific Styles (Conditional - action=wizard)

**Purpose:** Campaign creation wizard interface (multi-step form)
**Condition:** Only loads when `$_GET['action'] === 'wizard'`

### Wizard Core Infrastructure
1. **scd-wizard-navigation**
   - File: `admin/wizard-navigation.css`
   - Purpose: Fixed bottom navigation bar with prev/next/complete buttons
   - Uses: `--scd-nav-height`, `--scd-nav-button-width` variables

2. **scd-wizard-steps**
   - File: `admin/wizard-steps.css`
   - Purpose: Step container and step indicators

3. **scd-wizard-fullscreen**
   - File: `admin/wizard-fullscreen.css`
   - Deps: scd-admin, scd-variables, scd-utilities, scd-components, scd-tom-select-custom
   - Purpose: Fullscreen wizard mode layout

### Wizard Modals
1. **scd-wizard-completion-modal**
   - File: `admin/wizard-completion-modal.css`
   - Purpose: Success modal when campaign is created

2. **scd-session-expiration-modal**
   - File: `admin/session-expiration-modal.css`
   - Purpose: Warning when wizard session about to expire

3. **scd-pro-feature-modal**
   - File: `admin/pro-feature-modal.css`
   - Deps: scd-admin, dashicons
   - Purpose: PRO feature upgrade prompt

### Wizard Step Styles (Priority 20)
1. **scd-wizard-step-basic**
   - File: `admin/step-basic.css`
   - Purpose: Campaign name, description, recurring options

2. **scd-wizard-step-products**
   - File: `admin/step-products.css`
   - Purpose: Product selection with TomSelect, conditions

3. **scd-wizard-step-discounts** ⭐ LARGEST FILE
   - File: `admin/step-discounts.css` (2231 lines)
   - Purpose: Discount type selection, rules, conditions, tiered pricing

4. **scd-badge-settings**
   - File: `admin/badge-settings.css`
   - Deps: scd-admin, wp-color-picker
   - Purpose: Badge customization (text, colors, visibility)

5. **scd-wizard-step-schedule**
   - File: `admin/step-schedule.css`
   - Deps: scd-admin, wp-jquery-ui-dialog, scd-wordpress-color-schemes
   - Purpose: Date/time pickers, timezone, campaign duration

6. **scd-wizard-step-review**
   - File: `admin/step-review.css`
   - Purpose: Final review, health checks, campaign summary

### Wizard Validation & Third-Party
1. **scd-validation**
   - File: `admin/validation.css`
   - Purpose: Field-level validation error display

2. **tom-select**
   - File: `vendor/tom-select/tom-select.css`
   - Purpose: Base TomSelect dropdown styles (third-party)

3. **scd-tom-select-custom**
   - File: `admin/tom-select-custom.css`
   - Deps: tom-select, scd-admin
   - Purpose: Custom TomSelect theme matching SCD design

---

## Layer 6: Frontend Styles

**Purpose:** Customer-facing styles (shortcodes, widgets, public displays)

### scd-frontend
- **File:** `frontend/frontend.css`
- **Purpose:** Public-facing styles for discount displays, badges
- **Scope:** Public website (non-admin)

---

## Layer 7: WordPress Compatibility

**Purpose:** WordPress core integration and color scheme compatibility

### scd-wordpress-color-schemes (Priority 1)
- **File:** `admin/wordpress-color-schemes.css`
- **Deps:** NONE
- **Purpose:** Compatibility with WP admin color schemes (Fresh, Light, Modern, Blue, Coffee, etc.)
- **Scope:** scd-campaigns, scd-analytics, scd-settings, scd-dashboard

---

## Variable Flow Diagram

```
WordPress Admin Color Picker
         │
         ├──> _theme-colors.css  ──────────────────────────────┐
         │    (--wp-admin-theme-color-*)                       │
         │                                                      │
         │                                                      ▼
         └──> _variables.css (Classic Base)          All Component CSS Files
              • --scd-button-height: 30px            • Use var(--scd-button-height)
              • --scd-shadow-md: none                • Use var(--scd-shadow-md)
              • --scd-transition-base: 0ms           • Use var(--scd-transition-base)
              • --scd-radius-md: 4px                 • Use var(--scd-radius-md)
                       │                                      │
                       │                                      │
              Settings: admin_theme                          │
                       │                                      │
         ┌─────────────┴─────────────┐                      │
         │                             │                      │
         ▼                             ▼                      ▼
  theme-classic.css            theme-enhanced.css     Component Rendering
  • NO overrides               • --scd-button-height: 40px     │
  • Uses base values           • --scd-shadow-md: enabled      │
  • Minimal !important         • --scd-transition-base: 0.2s   │
                               • --scd-radius-md: 8px          │
         │                             │                      │
         └─────────────┬───────────────┘                      │
                       │                                      │
                       └──────────> Final Computed Values <──┘
```

---

## Key Architectural Patterns

### 1. Dependency Hierarchy
Foundation (scd-theme-colors, scd-variables) → Shared (components, forms, buttons) → Base Admin (scd-admin) → Page-Specific → Wizard-Specific

### 2. Theme System (Variables-Only Override)
Base (_variables.css) → Classic Theme (minimal) OR Enhanced Theme (comprehensive) → Component CSS (uses variables)

**Result:** 100% theme-switchable without duplicating styles

### 3. Conditional Loading
Wizard CSS only loads when `action=wizard` (prevents bloat on list view)
Page-specific CSS only loads on respective pages

**Result:** Optimal performance, no unnecessary CSS

### 4. Priority System
- **Priority 1-3:** Foundation (colors, variables, theme, utilities)
- **Priority 4-8:** Shared components (badges, forms, buttons)
- **Priority 10:** Base admin + page-specific
- **Priority 15-16:** Complex pages (dashboard, settings)
- **Priority 20:** Wizard steps (ensure proper cascade)

### 5. Progressive Enhancement
WordPress native (Classic) by default
Enhanced theme adds modern polish as opt-in upgrade
All features work with both themes

---

## File Count Summary

| Category | File Count | Purpose |
|----------|-----------|---------|
| Shared | 10 | Variables, utilities, components, forms, badges |
| Admin | 30 | Dashboard, campaigns, wizard, settings, tools, analytics |
| Wizard | 11 | Navigation, steps, modals |
| Themes | 2 | Classic, enhanced |
| Frontend | 1 | Public-facing styles |
| **TOTAL** | **54** | Organized, modular, maintainable |

---

## Architecture Benefits

✅ **WordPress.org Ready** - Native styling by default
✅ **Progressive Enhancement** - Enhanced theme is opt-in upgrade
✅ **Lighter Default** - No shadows, transitions, or extra CSS overhead
✅ **Simpler Maintenance** - Classic: 41 lines vs old 165 lines
✅ **Better Semantics** - Clear base → override relationship
✅ **Optimal Performance** - Conditional loading prevents bloat
✅ **100% Theme-Switchable** - Zero style duplication between themes
