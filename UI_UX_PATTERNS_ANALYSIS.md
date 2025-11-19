# Smart Cycle Discounts - UI/UX Patterns & Components Analysis

## Overview

The Smart Cycle Discounts plugin implements a comprehensive, cohesive UI/UX system built on WordPress admin conventions with enhanced accessibility, sophisticated component architecture, and professional visual design patterns.

---

## 1. MODAL/OVERLAY PATTERNS

### PHP Modal Component (class-modal-component.php)

**Architecture:**
- Server-side class `SCD_Modal_Component` 
- Generates standalone modal HTML + inline styles + JavaScript
- Supports dashicons and custom SVG icons
- Full accessibility compliance with ARIA attributes

**Key Features:**

```php
// Configuration-driven modal creation
$modal = new SCD_Modal_Component( array(
    'id'             => 'campaign-confirm',
    'title'          => 'Confirm Action',
    'content'        => 'Are you sure?',
    'icon'           => 'dashicons-warning',
    'icon_type'      => 'dashicons', // or 'svg'
    'dismissible'    => true,
    'buttons'        => array(
        array(
            'text'   => 'Confirm',
            'class'  => 'button button-primary',
            'action' => 'confirm'
        ),
        array(
            'text'   => 'Cancel',
            'class'  => 'button',
            'action' => 'close'
        )
    )
) );

$modal->render(); // Outputs HTML + styles + scripts
```

**CSS Structure:**
```css
/* Z-index Strategy: 100000 for modals */
.scd-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 100000;
    display: none;
    align-items: center;
    justify-content: center;
}

.scd-modal.scd-modal--visible {
    display: flex;
}

.scd-modal__overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.6);
}

.scd-modal__container {
    position: relative;
    max-width: 500px;
    width: 90%;
    background: #fff;
    border-radius: 4px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
    animation: scd-modal-slide-in 0.3s ease-out;
}
```

**Animation Pattern:**
```css
@keyframes scd-modal-slide-in {
    from {
        transform: translateY(-30px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}
```

**JavaScript API:**
```javascript
// Auto-initialized by PHP component
window.SCD.Modal = {
    show: function(modalId) {
        var $modal = $('#' + modalId);
        $modal.addClass('scd-modal--visible').fadeIn(200).css('display', 'flex');
        $('body').addClass('scd-modal-open');
    },
    hide: function(modalId) {
        var $modal = $('#' + modalId);
        $modal.removeClass('scd-modal--visible').fadeOut(200);
        $('body').removeClass('scd-modal-open');
    },
    hideAll: function() {
        $('.scd-modal').removeClass('scd-modal--visible').hide();
        $('body').removeClass('scd-modal-open');
    }
};
```

**Accessibility:**
- Close button has `aria-label="Close modal"`
- Modal viewport properly centered
- Backdrop prevents interaction with background
- Body overflow hidden when modal open
- Escape key support built-in
- Click on overlay closes modal

**Responsive Design:**
```css
@media screen and (max-width: 600px) {
    .scd-modal__container {
        width: 95%;
        margin: 20px;
    }
    
    .scd-modal__actions {
        flex-direction: column; /* Stack buttons vertically */
    }
    
    .scd-modal__actions .button {
        width: 100%;
        margin: 5px 0;
    }
}
```

---

## 2. BADGE SYSTEM

### PHP Badge Helper (class-badge-helper.php)

**Architecture:**
- Static factory methods for consistent badge generation
- Supports multiple badge types with semantic naming
- CSS-driven styling with theme variable integration

**Badge Types:**

```php
// Generic badge
SCD_Badge_Helper::badge('Text', 'scd-badge', 'Optional title');

// Feature badges
SCD_Badge_Helper::pro_badge();    // Orange PRO badge
SCD_Badge_Helper::free_badge();   // Green FREE badge

// Status badges
SCD_Badge_Helper::status_badge('active');     // Green active
SCD_Badge_Helper::status_badge('scheduled');  // Blue scheduled
SCD_Badge_Helper::status_badge('expired');    // Red expired
SCD_Badge_Helper::status_badge('draft');      // Yellow draft
SCD_Badge_Helper::status_badge('paused');     // Gray paused

// Health badges
SCD_Badge_Helper::health_badge('healthy');    // Green
SCD_Badge_Helper::health_badge('warning');    // Yellow
SCD_Badge_Helper::health_badge('alert');      // Red
SCD_Badge_Helper::health_badge('info');       // Blue

// Priority badge (1-5)
SCD_Badge_Helper::priority_badge(3);

// Product selection badge
SCD_Badge_Helper::product_badge(
    'All Products',
    'all_products',
    'Select all products for this discount',
    false // is_empty
);
```

**CSS Badge Patterns (_badges.css):**

```css
/* Base badge mixin applied to all badge types */
.scd-badge-status--active,
.scd-badge-status--inactive,
.scd-badge-health--healthy,
/* ... all badge types ... */
{
    display: inline-flex;
    align-items: center;
    gap: var(--scd-spacing-xs);
    padding: 2px var(--scd-spacing-sm);
    font-size: 11px;
    font-weight: var(--scd-font-weight-medium);
    line-height: 1.5;
    text-align: center;
    white-space: nowrap;
    text-transform: uppercase;
    border-radius: 3px;
    transition: all var(--scd-transition-fast) var(--scd-ease-in-out);
}

/* Status badge colors */
.scd-badge-status--active {
    background-color: var(--scd-color-success-bg, rgba(0, 163, 42, 0.25));
    color: var(--scd-badge-active, #186a3b);
}

.scd-badge-status--expired {
    background-color: var(--scd-color-error-bg, rgba(214, 54, 56, 0.25));
    color: var(--scd-badge-expired, #a93226);
}

/* PRO/FREE badges with different styling */
.scd-pro-badge {
    background: #ff9600;
    color: #fff;
    border: none;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
}

.scd-free-badge {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

/* Product selection badge */
.scd-badge-product {
    background-color: rgba(246, 248, 250, 1);
    color: #24292F;
    border: 1px solid rgba(208, 215, 222, 1);
    text-transform: none;
    cursor: help;
}

.scd-badge-product:hover {
    background-color: #FFFFFF;
    border-color: #57606A;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
}

/* Empty/Pending state */
.scd-badge-product--empty {
    background-color: #FFFFFF;
    border-style: dashed;
    color: #57606A;
    font-style: italic;
}

/* Icon support within badges */
.scd-badge-status--active .dashicons,
.scd-badge-health--healthy .dashicons {
    width: 14px;
    height: 14px;
    font-size: 14px;
    line-height: 1;
}

/* Loading/Skeleton badge */
.scd-badge--loading {
    background: linear-gradient(
        90deg,
        var(--scd-color-surface) 25%,
        var(--scd-color-surface-dark) 50%,
        var(--scd-color-surface) 75%
    );
    background-size: 200% 100%;
    animation: scd-shimmer 1.2s ease-in-out infinite;
    color: transparent;
    min-width: 60px;
}

@keyframes scd-shimmer {
    0% {
        background-position: 200% 0;
    }
    100% {
        background-position: -200% 0;
    }
}
```

**Badge Groups:**
```css
.scd-badge-group {
    display: inline-flex;
    gap: var(--scd-spacing-xs);
    flex-wrap: wrap;
}
```

---

## 3. NOTIFICATION SYSTEM

### NotificationService (notification-service.js)

**Architecture:**
- Singleton global service: `SCD.Shared.NotificationService`
- Toast-style notifications with auto-dismiss
- Full accessibility compliance (ARIA live regions)
- Network status monitoring
- Session expiration handling

**API Methods:**

```javascript
// Shorthand methods
SCD.Shared.NotificationService.success('Campaign saved successfully!');
SCD.Shared.NotificationService.error('Failed to save campaign');
SCD.Shared.NotificationService.warning('Please review your settings');
SCD.Shared.NotificationService.info('Processing your request...');

// Full control
SCD.Shared.NotificationService.show(
    'Custom message',
    'success', // Type: success, error, warning, info
    5000,      // Duration in ms (0 = permanent)
    {
        id: 'unique-notification-id',
        replace: true // Replace existing notification with same ID
    }
);

// Management
SCD.Shared.NotificationService.hide(notification);
SCD.Shared.NotificationService.hideAll();
SCD.Shared.NotificationService.dismiss('notification-id');
SCD.Shared.NotificationService.destroy(); // Full cleanup
```

**HTML Structure:**

```html
<div id="scd-notifications-container" class="scd-notifications-container">
    <!-- Notifications rendered here -->
    <div id="scd-notification-{id}" class="scd-notification scd-notification--success scd-notification--show" role="alert" aria-live="polite">
        <span class="scd-notification__icon dashicons dashicons-yes-alt" aria-hidden="true"></span>
        <span class="scd-notification__message">Campaign saved successfully!</span>
        <button class="scd-notification__close" aria-label="Close notification" type="button">
            <span class="dashicons dashicons-dismiss"></span>
        </button>
    </div>
</div>
```

**CSS Styling:**

```css
.scd-notifications-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 99999;
    max-width: 400px;
    pointer-events: auto;
}

.scd-notification {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    margin-bottom: 8px;
    background: #fff;
    border-left: 4px solid;
    border-radius: 4px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
    animation: scdSlideDown 200ms ease-out;
}

/* Type variants */
.scd-notification--success { border-left-color: #28a745; }
.scd-notification--error { border-left-color: #dc3545; }
.scd-notification--warning { border-left-color: #ffc107; }
.scd-notification--info { border-left-color: #17a2b8; }

.scd-notification--fade-out {
    animation: scdFadeOut 300ms ease-out forwards;
}

@keyframes scdSlideDown {
    from {
        transform: translateX(400px);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes scdFadeOut {
    from {
        opacity: 1;
    }
    to {
        opacity: 0;
    }
}
```

**Features:**

1. **Auto-Hide with Pause on Hover:**
   ```javascript
   // Automatically pauses and resumes timer when hovering
   notification.$element.on('mouseenter', function() {
       if (notification.timer) {
           clearTimeout(notification.timer);
           notification.remainingTime = /* calculate remaining */;
       }
   });
   ```

2. **Keyboard Support:**
   - Escape key closes notification
   - Focus management for accessibility

3. **Flash Animation for Updates:**
   ```javascript
   notification.$element.addClass('scd-notification--flash');
   notification.$element.one('animationend', function() {
       notification.$element.removeClass('scd-notification--flash');
   });
   ```

4. **Network Status Monitoring:**
   ```javascript
   window.addEventListener('online', function() {
       SCD.Shared.NotificationService.success('Connection restored', {
           duration: 3000,
           id: 'connection-status',
           replace: true
       });
   });

   window.addEventListener('offline', function() {
       SCD.Shared.NotificationService.error(
           'Connection lost. Please check your internet connection.',
           {
               duration: 0, // Permanent until reconnection
               id: 'connection-status',
               replace: true
           }
       );
   });
   ```

5. **Global Event Integration:**
   ```javascript
   // Trigger via jQuery event
   $(document).trigger('scd:notify', {
       message: 'Operation completed',
       type: 'success',
       duration: 3000
   });

   // Step changes clear notifications
   $(document).on('scd:wizard:stepChanged', function() {
       SCD.Shared.NotificationService.hideAll();
   });

   // Session expiration handling
   $(document).on('scd:session:expired', function() {
       SCD.Shared.NotificationService.error(
           'Your session has expired. Please refresh the page.',
           { duration: 0 }
       );
   });
   ```

---

## 4. VALIDATION ERROR COMPONENT

### ValidationError (validation-error.js)

**Architecture:**
- Centralized error display component: `SCD.Components.ValidationError`
- Dual-layer validation: field-level (red borders) + global (notification banner)
- Full WCAG 2.1 AA accessibility compliance
- Seamless integration with NotificationService

**API Methods:**

```javascript
// Show error on single field
SCD.Components.ValidationError.show(
    $field, // jQuery field element
    'This field is required', // Error message
    {
        type: 'error',           // error, warning, info
        animate: true,
        position: 'after-field', // or 'after-container'
        focus: false
    }
);

// Clear error from field
SCD.Components.ValidationError.clear($field);

// Show multiple field errors with optional summary
SCD.Components.ValidationError.showMultiple(
    {
        'campaign_name': 'Campaign name is required',
        'discount_value': 'Discount value must be greater than 0'
    },
    $container, // Form container for context
    {
        clearFirst: true,    // Clear existing errors first
        showSummary: true    // Show NotificationService summary banner
    }
);

// Clear all errors in container
SCD.Components.ValidationError.clearAll($container);

// Announce error to screen readers
SCD.Components.ValidationError.announceError(
    'This field is required',
    'field_name'
);
```

**HTML Structure Generated:**

```html
<!-- Field with error -->
<input type="text" name="campaign_name" aria-invalid="true" aria-describedby="error-campaign_name" class="error" />

<!-- Error message (auto-inserted after field) -->
<div class="scd-field-error" id="error-campaign_name" role="alert">
    This field is required
</div>

<!-- Container with error state -->
<div class="form-group has-error">
    <!-- Form content -->
</div>
```

**CSS Styling:**

```css
/* Field error styling */
.scd-field-error {
    display: block;
    padding: 8px 12px;
    margin-top: 4px;
    background-color: rgba(220, 53, 69, 0.1);
    color: #dc3545;
    font-size: 13px;
    border-radius: 4px;
    border-left: 3px solid #dc3545;
    animation: slideDown 200ms ease-out;
}

/* Field with error state */
input.error,
select.error,
textarea.error {
    border-color: #dc3545 !important;
    background-color: rgba(220, 53, 69, 0.02);
}

.form-group.has-error {
    background-color: rgba(220, 53, 69, 0.02);
    border-radius: 4px;
    padding: 8px;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-5px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
```

**Accessibility Features:**

1. **ARIA Attributes:**
   - `aria-invalid="true"` on error field
   - `aria-describedby="error-{id}"` linking field to error message
   - `role="alert"` on error message for announcements

2. **Screen Reader Announcements:**
   ```javascript
   // Built-in live region announcer
   _$announcer.attr('role', 'status')
              .attr('aria-live', 'polite')
              .attr('aria-atomic', 'true');
   ```

3. **Focus Management:**
   - Option to focus first invalid field
   - Proper tab order maintained
   - Focus visible states preserved

4. **Integration with ValidationService:**
   ```javascript
   // When validation fails, show both field errors AND summary
   if (!result.ok) {
       // Field-level error
       SCD.Components.ValidationError.show($field, errorMessage);
       
       // Global summary via NotificationService
       if (options.showSummary) {
           SCD.Shared.NotificationService.error(
               'Please correct the errors below',
               { duration: 0 }
           );
       }
   }
   ```

---

## 5. SHARED UI COMPONENTS

### Shared JavaScript Modules (resources/assets/js/shared/)

**Key Components:**

#### 1. **base-api.js**
- API communication wrapper
- Request/response handling
- Error normalization

#### 2. **base-orchestrator.js**
- Component lifecycle management
- State persistence
- Event coordination

#### 3. **base-state.js**
- State management base
- Change tracking
- Immutability helpers

#### 4. **error-handler.js**
- Centralized error processing
- User-friendly error messages
- Debug logging

#### 5. **tom-select-base.js**
- Custom select dropdown integration
- Loading states
- Accessibility support

```javascript
// Tom Select with loading indicator
class TomSelectBase {
    renderLoading() {
        return '<div class="spinner" role="status" aria-live="polite" aria-atomic="true">' +
               '<span aria-hidden="true">Loading...</span>' +
               '</div>';
    }
}
```

#### 6. **theme-color-service.js**
- Dynamic theme color management
- CSS custom property injection
- WordPress admin color scheme integration

```javascript
SCD.Shared.ThemeColorService.applyColors({
    primary: '#2271b1',
    secondary: '#135e96',
    success: '#00a32a',
    error: '#dc3545',
    warning: '#ffc107'
});
```

#### 7. **validation-manager.js**
- Field-level validation logic
- Form validation orchestration
- Cross-field validation support

---

### Shared CSS Patterns (resources/assets/css/shared/)

#### **_variables.css**
Comprehensive design token system:

```css
/* Color variables */
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

/* Spacing scale (8px base) */
--scd-spacing-xs: 4px;
--scd-spacing-sm: 8px;
--scd-spacing-md: 12px;
--scd-spacing-lg: 16px;
--scd-spacing-xl: 24px;
--scd-spacing-xxl: 32px;

/* Typography */
--scd-font-size-sm: 12px;
--scd-font-size-base: 14px;
--scd-font-size-md: 15px;
--scd-font-size-lg: 16px;
--scd-font-size-xl: 18px;
--scd-font-weight-normal: 400;
--scd-font-weight-medium: 500;
--scd-font-weight-semibold: 600;

/* Border radius scale */
--scd-radius-sm: 3px;
--scd-radius-md: 6px;
--scd-radius-lg: 8px;

/* Shadows */
--scd-shadow-sm: 0 1px 3px rgba(0,0,0,0.08);
--scd-shadow-md: 0 4px 12px rgba(0,0,0,0.12);
--scd-shadow-lg: 0 8px 24px rgba(0,0,0,0.15);
--scd-shadow-focus: 0 0 0 3px rgba(34, 113, 177, 0.25);

/* Transitions */
--scd-transition-fast: 150ms;
--scd-transition-base: 200ms;
--scd-transition-slow: 300ms;
--scd-ease-in-out: cubic-bezier(0.4, 0, 0.2, 1);
```

#### **_utilities.css**
Utility classes for common patterns:

```css
/* Focus states */
.scd-focus-visible:focus-visible {
    outline: 2px solid transparent;
    outline-offset: 0;
    box-shadow: var(--scd-shadow-focus);
}

/* Transitions */
.scd-transition { transition: all var(--scd-transition-base) var(--scd-ease-in-out); }
.scd-transition-fast { transition: all var(--scd-transition-fast) var(--scd-ease-in-out); }

/* Hover effects */
.scd-hover-lift:hover {
    box-shadow: var(--scd-shadow-md);
}

/* Loading spinner */
.scd-spinner {
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 2px solid var(--scd-color-border);
    border-top-color: var(--scd-color-primary);
    border-radius: 50%;
    animation: scd-spin 0.8s linear infinite;
}
```

#### **_components.css**
Comprehensive component system:

```css
/* Cards */
.scd-card {
    background: var(--scd-color-background);
    border: var(--scd-border-width) solid var(--scd-color-border);
    border-radius: var(--scd-radius-lg);
    padding: var(--scd-spacing-lg);
    margin-bottom: var(--scd-spacing-lg);
    box-shadow: var(--scd-shadow-sm);
}

.scd-card:hover {
    border-color: var(--scd-color-border-dark);
    box-shadow: var(--scd-shadow-md);
}

/* Card variants */
.scd-card--compact { padding: var(--scd-spacing-md); }
.scd-card--surface { background: var(--scd-color-surface); }
.scd-card--selected {
    border-color: var(--scd-color-primary);
    background: var(--scd-color-primary-bg);
    box-shadow: 0 0 0 1px var(--scd-color-primary);
}

/* Interactive card option */
.scd-card-option {
    position: relative;
    cursor: pointer;
}

.scd-card-option input[type="radio"] {
    position: absolute;
    top: var(--scd-spacing-md);
    right: var(--scd-spacing-md);
}

.scd-card-option:has(input[type="radio"]:checked) {
    border-width: 2px;
    border-color: var(--scd-color-primary);
}

/* Pro feature unavailable state */
.scd-card--unavailable {
    background: linear-gradient(to bottom, #fafafa, #f7f7f7);
    border-color: #e5e5e5;
    color: #888;
    pointer-events: none;
    user-select: none;
}

.scd-card--unavailable::before {
    content: '';
    position: absolute;
    inset: -1px;
    background: repeating-linear-gradient(
        45deg,
        transparent,
        transparent 10px,
        rgba(0, 0, 0, 0.02) 10px,
        rgba(0, 0, 0, 0.02) 20px
    );
    border-radius: inherit;
}

/* Card header */
.scd-card__header {
    margin: calc(var(--scd-spacing-lg) * -1 + 1px);
    margin-bottom: var(--scd-spacing-lg);
    padding: var(--scd-spacing-md) var(--scd-spacing-lg);
    background-color: var(--scd-color-surface);
    border: var(--scd-border-width) solid var(--scd-color-border-light);
    border-radius: calc(var(--scd-radius-lg) - 2px) calc(var(--scd-radius-lg) - 2px) 0 0;
    box-shadow: 0 1px 3px var(--scd-shadow-gradient-light);
}

.scd-card__title {
    margin: 0;
    font-size: var(--scd-font-size-large);
    font-weight: var(--scd-font-weight-semibold);
    color: var(--scd-color-text);
}

/* Skeleton loading */
.scd-skeleton-line {
    height: 16px;
    background: linear-gradient(90deg,
        #f0f0f1 25%,
        #e8e9ea 50%,
        #f0f0f1 75%);
    background-size: 200% 100%;
    animation: scd-skeleton-shimmer 1.8s ease-in-out infinite;
    border-radius: 6px;
    margin-bottom: var(--scd-spacing-md);
}

@keyframes scd-skeleton-shimmer {
    0% { background-position: -200% 0; }
    100% { background-position: 200% 0; }
}
```

#### **_badges.css**
[Detailed badge styling covered in section 2]

#### **_buttons.css**
Consistent button styling following WordPress conventions

#### **_forms.css**
Form control standardization

---

## 6. ACCESSIBILITY PATTERNS

### ARIA Attributes Usage

**Live Regions:**
```javascript
// Notifications use aria-live
<div role="alert" aria-live="polite" aria-atomic="true">
    Notification content
</div>

// Status updates
<div role="status" aria-live="polite">
    Processing...
</div>

// Loading spinners
<div role="status" aria-live="polite" aria-atomic="true">
    <span aria-hidden="true">Loading...</span>
</div>
```

**Field-Level Accessibility:**
```javascript
// Input with error
<input
    type="email"
    name="email"
    aria-label="Email address"
    aria-invalid="true"
    aria-describedby="error-email"
/>

// Error message
<div id="error-email" role="alert">
    Please enter a valid email address
</div>

// Field help text
<input
    aria-label="Campaign name"
    aria-describedby="campaign-help"
/>
<span id="campaign-help">
    Maximum 50 characters
</span>
```

**Dialog/Modal Patterns:**
```javascript
<div role="dialog" aria-labelledby="modal-title" aria-modal="true">
    <h2 id="modal-title">Confirm Action</h2>
    <!-- Content -->
    <button aria-label="Close dialog">X</button>
</div>
```

**Button States:**
```javascript
// Toggle buttons
<button aria-expanded="false" aria-controls="menu">Menu</button>
<div id="menu" role="region" aria-hidden="true">
    <!-- Menu items -->
</div>

// When expanded:
// - aria-expanded="true"
// - aria-hidden="false"
```

**Form Validation:**
```javascript
// Before validation
<input name="campaign_name" />

// During validation
<input name="campaign_name" aria-busy="true" />

// After validation - Valid
<input name="campaign_name" aria-invalid="false" />

// After validation - Invalid
<input 
    name="campaign_name"
    aria-invalid="true"
    aria-describedby="campaign_name-error"
/>
<div id="campaign_name-error" role="alert">
    This field is required
</div>
```

### Keyboard Navigation

**Focus Management:**
```css
/* Visible focus indicators */
*:focus-visible {
    outline: 2px solid var(--wp-admin-theme-color);
    outline-offset: 2px;
}

/* Modal focus trap */
.scd-modal {
    /* When visible, trap focus within modal */
    /* On Escape key, close modal */
}
```

**Keyboard Support Built Into Components:**
- Modals: Escape key to close
- Notifications: Escape key to dismiss
- Dropdowns: Arrow keys for navigation
- Tabs: Arrow keys + Home/End keys
- Forms: Tab order preserved, Enter to submit

### Screen Reader Support

**Hidden Elements:**
```html
<!-- Icon that shouldn't be read -->
<span class="dashicons dashicons-warning" aria-hidden="true"></span>

<!-- Supporting text for icon -->
<span class="sr-only">Warning:</span> This is important
```

**Form Labels:**
```html
<!-- Explicit label -->
<label for="campaign-name">Campaign Name:</label>
<input id="campaign-name" type="text" />

<!-- Label via aria-label -->
<input aria-label="Campaign name" type="text" />

<!-- Label via aria-labelledby -->
<h3 id="campaign-section">Campaign Settings</h3>
<input aria-labelledby="campaign-section" type="text" />
```

---

## 7. COMPONENT INITIALIZATION PATTERNS

### JavaScript Component Structure

**Standard Component Pattern:**
```javascript
// Base orchestrator for managing component lifecycle
(function($) {
    'use strict';
    
    window.SCD = window.SCD || {};
    SCD.Steps = SCD.Steps || {};
    
    // Orchestrator pattern
    SCD.Steps.BasicOrchestrator = {
        
        // State management
        state: null,
        
        // Initialization
        init: function(stepName) {
            // Setup state
            this.state = SCD.Steps.BasicState.create();
            
            // Bind events
            this.bindEvents();
            
            // Render UI
            this.render();
        },
        
        // Event binding
        bindEvents: function() {
            var self = this;
            
            $(document).on('change', '[name="campaign_name"]', function() {
                self.onFieldChange('campaign_name', $(this).val());
            });
        },
        
        // Data collection
        collectData: function() {
            return {
                campaign_name: $('[name="campaign_name"]').val(),
                // ... other fields
            };
        },
        
        // Validation
        validate: function() {
            var result = SCD.ValidationManager.validateStep('basic');
            if (!result.ok) {
                this.showValidationErrors(result.errors);
            }
            return result;
        },
        
        // Rendering
        render: function() {
            // Update UI based on state
        }
    };
    
})(jQuery);
```

### Data Persistence Pattern

```javascript
// Auto-save on field change
$('[name="campaign_name"]').on('change', function() {
    SCD.Steps.BasicAPI.saveField('campaign_name', $(this).val());
});

// Restore from server on page load
SCD.Steps.BasicAPI.loadData().done(function(data) {
    SCD.Steps.BasicState.set(data);
    SCD.Steps.BasicOrchestrator.render();
});
```

---

## 8. THEME COLOR INTEGRATION

### CSS Custom Properties System

**WordPress Admin Integration:**
```javascript
// Get WordPress admin theme color
var wpThemeColor = getComputedStyle(document.documentElement)
    .getPropertyValue('--wp-admin-theme-color');

// Apply SCD-specific theming
document.documentElement.style.setProperty(
    '--scd-color-primary',
    wpThemeColor
);
```

**Color Variants:**
```css
/* Primary color and variants */
--scd-color-primary: #2271b1;
--wp-admin-theme-color-darker-10: #135e96;
--wp-admin-theme-color-darker-20: #0a4b78;

/* Applied in components */
.scd-card__header .dashicons {
    color: var(--wp-admin-theme-color, #2271b1);
}

.scd-card:hover .dashicons {
    color: var(--wp-admin-theme-color-darker-10, #135e96);
}
```

---

## 9. LOADING STATES & TRANSITIONS

### Skeleton Loading Patterns

```css
/* Shimmer animation */
.scd-skeleton-line {
    background: linear-gradient(90deg,
        #f0f0f1 25%,
        #e8e9ea 50%,
        #f0f0f1 75%);
    background-size: 200% 100%;
    animation: scd-skeleton-shimmer 1.8s ease-in-out infinite;
}

/* Staggered animation delays for organic feel */
.scd-card:nth-child(1) .scd-skeleton-line { animation-delay: 0s; }
.scd-card:nth-child(2) .scd-skeleton-line { animation-delay: 0.1s; }
.scd-card:nth-child(3) .scd-skeleton-line { animation-delay: 0.2s; }
.scd-card:nth-child(4) .scd-skeleton-line { animation-delay: 0.3s; }
```

### Spinner Component

```javascript
// Built-in spinner
SCD.UI.showLoading('#step-content', 'Loading campaign...');

// Custom spinner styling
.scd-spinner {
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 2px solid var(--scd-color-border);
    border-top-color: var(--scd-color-primary);
    border-radius: 50%;
    animation: scd-spin 0.8s linear infinite;
}
```

---

## 10. RESPONSIVE DESIGN PATTERNS

### Breakpoints

```css
/* Tablet */
@media (max-width: 782px) {
    .scd-card__header {
        padding: var(--scd-spacing-sm) var(--scd-spacing-md);
    }
}

/* Mobile */
@media (max-width: 600px) {
    .scd-modal__container {
        width: 95%;
        margin: 20px;
    }
    
    .scd-modal__actions {
        flex-direction: column;
    }
    
    .scd-modal__actions .button {
        width: 100%;
        margin: 5px 0;
    }
}

/* RTL Support */
[dir="rtl"] .scd-card-option input[type="radio"] {
    right: auto;
    left: var(--scd-spacing-md);
}
```

---

## IMPLEMENTATION GUIDELINES

### When to Use Each Component

**Modal (class-modal-component.php):**
- Confirm destructive actions
- Complex multi-step dialogs
- Full-page overlays
- Server-generated content

**NotificationService:**
- Transient feedback (save success)
- Network status changes
- Session expiration warnings
- Non-blocking updates

**ValidationError:**
- Form validation feedback
- Field-specific errors
- Inline error messages
- Accessibility-compliant display

**Badges:**
- Status indicators
- Feature tags (PRO/FREE)
- Health indicators
- Quick visual scanning

**Cards:**
- Content containers
- List items
- Option selection
- Information grouping

### Best Practices

1. **Always use centralized components** - Don't create custom modals, notifications, or validation displays
2. **Maintain accessibility** - Use ARIA attributes as demonstrated
3. **Theme with CSS variables** - Don't hardcode colors
4. **Respect focus order** - Don't trap focus or break keyboard navigation
5. **Test with screen readers** - Ensure ARIA patterns work correctly
6. **Responsive first** - Build mobile-friendly from the start
7. **Provide feedback** - Use notifications for all user actions

