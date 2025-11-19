# Smart Cycle Discounts - UI Pattern Quick Reference

Quick lookup for implementing consistent UI/UX patterns throughout the plugin.

---

## Component Quick Reference

### Modal/Overlay Pattern
**Use for:** Confirm actions, complex dialogs, full-page overlays

**Location:** `/includes/admin/components/class-modal-component.php`

**Implementation:**
```php
$modal = new SCD_Modal_Component( array(
    'id'             => 'action-confirm',
    'title'          => 'Confirm',
    'content'        => 'Are you sure?',
    'icon'           => 'dashicons-warning',
    'dismissible'    => true,
    'buttons'        => array(
        array( 'text' => 'Confirm', 'class' => 'button button-primary', 'action' => 'confirm' ),
        array( 'text' => 'Cancel', 'class' => 'button', 'action' => 'close' )
    )
) );
$modal->render();
```

**JavaScript Control:**
```javascript
SCD.Modal.show('action-confirm');
SCD.Modal.hide('action-confirm');
SCD.Modal.hideAll();
```

**Z-Index:** 100000

---

### Notification System
**Use for:** Success/error feedback, transient messages, network status

**Location:** `/resources/assets/js/admin/notification-service.js`

**API Shortcuts:**
```javascript
SCD.Shared.NotificationService.success('Message');
SCD.Shared.NotificationService.error('Error message');
SCD.Shared.NotificationService.warning('Warning message');
SCD.Shared.NotificationService.info('Info message');
```

**Full Control:**
```javascript
SCD.Shared.NotificationService.show('Message', 'success', 5000, {
    id: 'unique-id',
    replace: true  // Replace if same ID exists
});
```

**Features:**
- Auto-dismiss configurable
- Pause on hover
- Keyboard support (Escape)
- Network status monitoring
- Live region announcements (WCAG AA)

**Z-Index:** 99999

---

### Badge System
**Use for:** Status indicators, feature tags, quick visual scanning

**Location:** `/includes/admin/components/class-badge-helper.php`

**Available Badges:**
```php
// Feature badges
SCD_Badge_Helper::pro_badge();        // Orange PRO
SCD_Badge_Helper::free_badge();       // Green FREE

// Status badges
SCD_Badge_Helper::status_badge('active');     // Green
SCD_Badge_Helper::status_badge('inactive');   // Gray
SCD_Badge_Helper::status_badge('scheduled');  // Blue
SCD_Badge_Helper::status_badge('expired');    // Red
SCD_Badge_Helper::status_badge('draft');      // Yellow
SCD_Badge_Helper::status_badge('paused');     // Gray

// Health badges
SCD_Badge_Helper::health_badge('healthy');    // Green
SCD_Badge_Helper::health_badge('warning');    // Yellow
SCD_Badge_Helper::health_badge('alert');      // Red
SCD_Badge_Helper::health_badge('info');       // Blue

// Priority
SCD_Badge_Helper::priority_badge(3);  // 1-5

// Product selection
SCD_Badge_Helper::product_badge('All Products', 'all_products');
```

---

### Validation Error Display
**Use for:** Form validation, field-level feedback

**Location:** `/resources/assets/js/validation/validation-error.js`

**Single Field Error:**
```javascript
SCD.Components.ValidationError.show(
    $field,
    'This field is required',
    { type: 'error' }
);
```

**Multiple Field Errors:**
```javascript
SCD.Components.ValidationError.showMultiple(
    {
        'field_name': 'Field is required',
        'email': 'Invalid email address'
    },
    $container,
    { clearFirst: true, showSummary: true }
);
```

**Clear Errors:**
```javascript
SCD.Components.ValidationError.clear($field);
SCD.Components.ValidationError.clearAll($container);
```

**Features:**
- ARIA attributes (aria-invalid, aria-describedby)
- Field borders turn red
- Error message with role="alert"
- Screen reader announcements
- Integrates with NotificationService

---

### Cards Component
**Use for:** Content containers, option selection, grouped information

**Location:** CSS: `/resources/assets/css/shared/_components.css`

**Base Card:**
```html
<div class="scd-card">
    <div class="scd-card__header">
        <h3 class="scd-card__title">
            <span class="dashicons dashicons-admin-tools"></span>
            Title
        </h3>
        <p class="scd-card__subtitle">Subtitle text</p>
    </div>
    <div class="scd-card__content">
        Card content here
    </div>
    <div class="scd-card__footer">
        Footer actions
    </div>
</div>
```

**Card Variants:**
```html
<!-- Compact -->
<div class="scd-card scd-card--compact">...</div>

<!-- Surface background -->
<div class="scd-card scd-card--surface">...</div>

<!-- Selected state -->
<div class="scd-card scd-card--selected">...</div>

<!-- Interactive option (radio button) -->
<div class="scd-card scd-card--interactive scd-card-option">
    <input type="radio" name="option" value="1" />
    <div class="scd-card__content">Option text</div>
</div>

<!-- Pro feature unavailable -->
<div class="scd-card scd-card--unavailable">
    <div class="scd-card__content">PRO Feature</div>
</div>
```

---

## CSS Design Tokens

**Location:** `/resources/assets/css/shared/_variables.css`

### Colors
```css
--scd-color-primary: #2271b1;
--scd-color-secondary: #13546a;
--scd-color-success: #00a32a;
--scd-color-error: #dc3545;
--scd-color-warning: #ffc107;
--scd-color-text: #1d2327;
--scd-color-text-muted: #646970;
--scd-color-background: #fff;
--scd-color-surface: #f1f1f1;
--scd-color-border: #ddd;
```

### Spacing (8px base)
```css
--scd-spacing-xs: 4px;
--scd-spacing-sm: 8px;
--scd-spacing-md: 12px;
--scd-spacing-lg: 16px;
--scd-spacing-xl: 24px;
--scd-spacing-xxl: 32px;
```

### Border Radius
```css
--scd-radius-sm: 3px;
--scd-radius-md: 6px;
--scd-radius-lg: 8px;
```

### Transitions
```css
--scd-transition-fast: 150ms;
--scd-transition-base: 200ms;
--scd-transition-slow: 300ms;
--scd-ease-in-out: cubic-bezier(0.4, 0, 0.2, 1);
```

### Shadows
```css
--scd-shadow-sm: 0 1px 3px rgba(0,0,0,0.08);
--scd-shadow-md: 0 4px 12px rgba(0,0,0,0.12);
--scd-shadow-lg: 0 8px 24px rgba(0,0,0,0.15);
--scd-shadow-focus: 0 0 0 3px rgba(34, 113, 177, 0.25);
```

---

## Accessibility Checklist

Every UI component must include:

### ARIA Attributes
- [ ] Form fields: `aria-label` or `aria-labelledby`
- [ ] Errors: `aria-invalid="true"` + `aria-describedby="error-id"`
- [ ] Buttons: `aria-label` for icon buttons
- [ ] Dialogs: `role="dialog"` + `aria-labelledby`
- [ ] Live regions: `role="alert"` + `aria-live="polite"`
- [ ] Hidden decorations: `aria-hidden="true"`

### Keyboard Navigation
- [ ] All interactive elements focusable
- [ ] Tab order logical
- [ ] Escape key support
- [ ] Enter key support for buttons
- [ ] Arrow keys for selectable items

### Focus Styling
- [ ] Visible focus indicators (2px outline)
- [ ] Use `:focus-visible` for keyboard focus
- [ ] Sufficient color contrast (4.5:1 for text)

### Screen Readers
- [ ] All images have alt text
- [ ] Form labels present
- [ ] Meaningful heading hierarchy
- [ ] Status messages announced
- [ ] Error messages announced

---

## Responsive Breakpoints

```css
/* Tablet */
@media (max-width: 782px) { /* Adjust for tablet screens */ }

/* Mobile */
@media (max-width: 600px) { /* Adjust for mobile screens */ }

/* RTL Support */
[dir="rtl"] { /* Adjust for right-to-left languages */ }
```

---

## JavaScript Patterns

### Component Initialization
```javascript
(function($) {
    'use strict';
    
    window.SCD = window.SCD || {};
    SCD.Steps = SCD.Steps || {};
    
    SCD.Steps.ComponentOrchestrator = {
        init: function() {
            // Setup
            this.bindEvents();
            this.render();
        },
        
        bindEvents: function() {
            var self = this;
            $(document).on('change', '[name="field"]', function() {
                self.handleChange($(this));
            });
        },
        
        handleChange: function($field) {
            // Handle change
        },
        
        validate: function() {
            return SCD.ValidationManager.validateStep('component');
        }
    };
    
})(jQuery);
```

### Event Triggering
```javascript
// Trigger global notification
$(document).trigger('scd:notify', {
    message: 'Operation completed',
    type: 'success',
    duration: 3000
});

// Listen for wizard step changes
$(document).on('scd:wizard:stepChanged', function() {
    SCD.Shared.NotificationService.hideAll();
    // Reset component state
});
```

---

## Common Z-Index Values

```
Modals:              100000 (.scd-modal)
Notifications:       99999  (.scd-notifications-container)
Tooltips:            100000 (.scd-tooltip)
Modal Overlay:       Fixed inside modal
Select Dropdown:     999+   (auto)
Sticky Headers:      100+   (context-dependent)
```

---

## Common Patterns

### Show Loading State
```javascript
SCD.Shared.UI.showLoading('#container', 'Loading...');

// Later:
SCD.Shared.UI.hideLoading('#container');
```

### Show Confirmation Dialog
```javascript
SCD.Shared.UI.confirm(
    'Confirm Action',
    'Are you sure you want to continue?',
    { dangerous: false }
).done(function(confirmed) {
    if (confirmed) {
        // Perform action
    }
});
```

### Create Inline Notice
```javascript
var html = SCD.Shared.UI.inlineNotice('Success message', 'success');
$('#container').append(html);
```

---

## File Locations Summary

| Component | PHP | CSS | JavaScript |
|-----------|-----|-----|-----------|
| **Modal** | `/includes/admin/components/class-modal-component.php` | Inline | Inline |
| **Notification** | - | Inline | `/resources/assets/js/admin/notification-service.js` |
| **Badge** | `/includes/admin/components/class-badge-helper.php` | `/resources/assets/css/shared/_badges.css` | - |
| **Card** | - | `/resources/assets/css/shared/_components.css` | - |
| **Validation** | - | Inline | `/resources/assets/js/validation/validation-error.js` |
| **Variables** | - | `/resources/assets/css/shared/_variables.css` | - |
| **Utilities** | - | `/resources/assets/css/shared/_utilities.css` | - |

---

## More Information

For complete details, see: `UI_UX_PATTERNS_ANALYSIS.md`
