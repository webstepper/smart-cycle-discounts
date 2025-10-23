# WordPress Plugin Development Rules for Claude Code

## 🚨 CORE PRINCIPLES (NEVER VIOLATE)
**NEVER run build commands**
### 1. **ALWAYS BE HONEST**
- Report actual system state - never assume fixes work
- Verify every change before confirming completion
- If unsure, ask for file contents or test results

### 2. **FIX ROOT CAUSES**
- NO band-aid solutions or quick hacks
- NO helper files as workarounds
- Resolve underlying architecture/logic issues
- Remove symptoms by fixing the disease

### 3. **FOLLOW ENGINEERING PRINCIPLES**
- **YAGNI**: Don't add features until needed
- **KISS**: Choose simplest working solution
- **DRY**: Eliminate all duplication

### 4. **MAINTAIN CLEAN CODEBASE**
- Remove obsolete code after implementing new logic
- Delete unused files and comments
- Keep everything modular and maintainable

## 📋 WORKFLOW REQUIREMENTS

### Before ANY Changes:
1. **ASK FOR FILES** - Never recommend without seeing code
2. **ANALYZE DEPENDENCIES** - Review all related files
3. **ASSESS IMPACT** - Consider ripple effects
4. **VERIFY CONTEXT** - Understand existing architecture

### During Implementation:
1. **INTEGRATE PROPERLY** - Connect seamlessly to existing system
2. **FOLLOW STANDARDS** - Apply WordPress coding conventions
3. **MAINTAIN CONSISTENCY** - Match existing patterns
4. **DOCUMENT CHANGES** - Comment complex logic

### After Changes:
1. **VERIFY FUNCTIONALITY** - Test the implementation
2. **CLEAN UP** - Remove old code and temporary fixes
3. **VALIDATE STANDARDS** - Check WordPress compliance
4. **CONFIRM INTEGRATION** - Ensure proper connection

## 🔒 WORDPRESS CODING STANDARDS (MANDATORY)

### PHP Standards
```php
// CORRECT WordPress PHP:
if ( 'value' === $variable ) {  // Yoda conditions
    $array = array( 'key' => 'value' );  // array() syntax
    function_name( $arg1, $arg2 );  // Spaces inside parentheses
    $string = 'Single quotes default';  // Single quotes
    $wpdb->prepare( "SELECT * FROM %s WHERE id = %d", $table, $id );  // Always prepare
    if ( current_user_can( 'edit_posts' ) ) {  // Capability checks
        // Tab indentation (not spaces)
    }
}
```

### JavaScript Standards (ES5 for WordPress.org)
```javascript
// CORRECT WordPress JavaScript:
( function( $ ) {
    'use strict';
    
    if ( condition ) {  // Spaces inside parentheses
        var myVariable = 'value';  // camelCase, single quotes
        functionName( arg1, arg2 );  // Spaces inside parentheses
    }
    
} )( jQuery );  // jQuery wrapper pattern
```

### CSS Standards
```css
/* CORRECT WordPress CSS */
#comment-form {  /* Lowercase with hyphens */
    display: block;  /* Display first */
    position: relative;  /* Positioning second */
    margin: 10px;  /* Box model third */
    color: #fff;  /* Colors fourth */
    /* Tab indentation */
}
```

### HTML Standards
```html
<!-- CORRECT WordPress HTML -->
<div class="container" id="main-content">
    <input type="text" name="field" value="" />  <!-- Self-closing with space -->
    <label for="field">Label Text</label>
</div>
```

### Naming Conventions (Option 1 - Industry Standard)

**JavaScript Layer (camelCase):**
```javascript
// CORRECT JavaScript naming:
var productSelectionType = 'all_products';
var campaignName = 'Summer Sale';
var usageLimitPerCustomer = 10;

// Function and method names
function handleSelectionTypeChange() {}
this.updateProductCounts = function() {};

// Object properties in JavaScript
var data = {
    campaignName: 'Test',
    productIds: [1, 2, 3],
    discountType: 'percentage'
};
```

**PHP Layer (snake_case):**
```php
// CORRECT PHP naming (WordPress standard):
$product_selection_type = 'all_products';
$campaign_name = 'Summer Sale';  
$usage_limit_per_customer = 10;

// Function names
function handle_selection_type_change() {}
$this->update_product_counts();

// Array keys for database/forms
$data = array(
    'campaign_name' => 'Test',
    'product_ids' => array( 1, 2, 3 ),
    'discount_type' => 'percentage'
);
```

**Boundary Conversion (Automatic):**
```javascript
// JavaScript field definitions use camelCase
SCD.FieldDefinitions.products = {
    productSelectionType: { /* ... */ },
    campaignName: { /* ... */ }
};

// DOM lookup automatically converts to snake_case
// productSelectionType → product_selection_type (for WordPress forms)
var $field = $('[name="product_selection_type"]');
```

**Wire Format (Preserve snake_case):**
```javascript
// Enum values stay snake_case (shared between PHP/JS)
options: {
    'all_products': 'All Products',        // ✅ Correct
    'specific_products': 'Specific Products' // ✅ Correct
}
// NOT: 'allProducts': 'All Products'      // ❌ Wrong
```

### Notification System

**ALWAYS use `SCD.Shared.NotificationService` for user notifications:**

```javascript
// ✅ CORRECT: Use NotificationService directly
SCD.Shared.NotificationService.success('Campaign saved successfully!');
SCD.Shared.NotificationService.error('Failed to save campaign');
SCD.Shared.NotificationService.warning('Please review your settings');
SCD.Shared.NotificationService.info('Processing your request...');

// With options
SCD.Shared.NotificationService.show('Custom message', 'success', 5000, {
    id: 'unique-notification-id',
    replace: true  // Replace existing notification with same ID
});

// Or trigger via event (for decoupled components)
$(document).trigger('scd:notify', {
    message: 'Operation completed',
    type: 'success',
    duration: 3000
});
```

**❌ DEPRECATED - Do NOT use:**
```javascript
// These are deprecated compatibility wrappers
SCD.Shared.UI.notify(message, type, options);  // Deprecated
SCD.Wizard.showNotification(message, type);     // Deprecated
```

**Why NotificationService?**
- Consistent notification display across the plugin
- Advanced features: pause on hover, replace existing notifications
- Accessibility built-in (ARIA roles, keyboard support)
- Network status monitoring (offline/online detection)
- Session expiration handling
- Auto-clear on step changes in wizard

### Validation System

**ALWAYS use `SCD.ValidationError` for field validation errors:**

The validation system has TWO complementary components:

1. **ValidationError Component** - Inline field-level feedback (red borders, ARIA attributes)
2. **NotificationService** - Global validation summary (banner at top)

```javascript
// ✅ CORRECT: Use ValidationError for field-level errors
if ( window.SCD && window.SCD.ValidationError ) {
    // Show error on specific field
    SCD.ValidationError.show( $field, 'This field is required' );

    // Clear error from field
    SCD.ValidationError.clear( $field );

    // Show multiple field errors with summary notification
    var errors = {
        'campaign_name': 'Campaign name is required',
        'discount_value': 'Discount value must be greater than 0'
    };
    SCD.ValidationError.showMultiple( errors, $container, {
        clearFirst: true,
        showSummary: true  // Automatically uses NotificationService for summary
    });

    // Clear all errors in container
    SCD.ValidationError.clearAll( $container );
}
```

**❌ WRONG - Do NOT manually manipulate error display:**
```javascript
// ❌ Don't do this
$field.addClass('error');
$field.after('<div class="scd-field-error">' + message + '</div>');
$field.siblings('.scd-field-error-message').remove();
```

**ValidationError automatically integrates with NotificationService:**
- Field errors show as red borders (inline)
- Summary notification shows at top (global banner via NotificationService)
- Full WCAG 2.1 AA accessibility compliance
- Screen reader announcements
- Proper ARIA attributes

**Component-Specific showError Methods:**
Components like `campaign-name.js` and `description.js` have `showError()` methods that internally delegate to `ValidationError.show()`. This is the correct pattern - keep the component API but use centralized implementation.

## 🛡️ SECURITY REQUIREMENTS

### ALWAYS Implement:
1. **Nonce Verification**: `wp_verify_nonce( $_POST['nonce'], 'action' )`
2. **Capability Checks**: `current_user_can( 'capability' )`
3. **Input Sanitization**: `sanitize_text_field()`, `sanitize_email()`, etc.
4. **Output Escaping**: `esc_html()`, `esc_attr()`, `esc_url()`
5. **Database Security**: `$wpdb->prepare()` for ALL queries

## 🏗️ PLUGIN ARCHITECTURE PATTERNS

### Your Plugin Structure:
- **Prefix Convention**: `SCD_` for PHP classes, `scd_` for functions/hooks, `scd-` for assets
- **Service Container**: Dependency injection throughout
- **Asset Management**: Admin_Asset_Manager, Script_Registry, Style_Registry
- **Modular Wizard**: Each step has state management, API, and orchestrator
- **MVC Pattern**: Separate views from business logic
- **Singleton Main Class**: Single initialization point
- **HPOS Compatibility**: Modern WooCommerce support

### File Organization:
```
/plugin-root/
├── /includes/
│   ├── /admin/          # Admin functionality
│   ├── /ajax/           # AJAX handlers
│   ├── /assets/         # Asset management classes
│   ├── /database/       # Database abstraction layer
│   ├── /services/       # Service container & DI
│   └── /views/          # PHP templates (MVC)
├── /assets/
│   ├── /js/            # JavaScript files
│   ├── /css/           # Stylesheets
│   └── /build/         # Webpack output
└── /webpack.config.js   # Build configuration
```

## ⚡ PERFORMANCE GUIDELINES

1. **Conditional Loading**: Load assets only where needed
2. **Efficient Queries**: Optimize database calls, use caching
3. **CDN for Libraries**: Load external dependencies from CDN
4. **Modular JavaScript**: Separate concerns, lazy load when possible
5. **Minimize HTTP Requests**: Combine and minify assets

## 🎯 IMPLEMENTATION CHECKLIST

Before submitting any code:
- [ ] Code follows WordPress PHP standards (Yoda, spacing, array())
- [ ] JavaScript is ES5 compatible with jQuery wrapper
- [ ] CSS uses lowercase-hyphen naming
- [ ] Security measures implemented (nonces, escaping, sanitization)
- [ ] Proper prefixing applied (SCD_, scd_, scd-)
- [ ] Assets enqueued through Asset Management System
- [ ] Database operations use abstraction layer
- [ ] Code is modular and follows DRY principle
- [ ] Root cause fixed, not symptoms
- [ ] Old code cleaned up
- [ ] WordPress hooks properly implemented
- [ ] AJAX handlers follow naming convention
- [ ] Views separated from logic (MVC)

## 🚫 NEVER DO THIS

1. **NEVER** assume a fix works without verification
2. **NEVER** use shorthand PHP tags (`<?` instead of `<?php`)
3. **NEVER** use array literals `[]` - use `array()`
4. **NEVER** skip security measures
5. **NEVER** write global JavaScript variables
6. **NEVER** apply quick hacks instead of proper solutions
7. **NEVER** leave obsolete code after refactoring
8. **NEVER** use spaces for indentation - use tabs
9. **NEVER** violate WordPress naming conventions
10. **NEVER** implement without analyzing existing code first

## 📝 COMMUNICATION PROTOCOL

When working with Claude Code:
1. **Always request files before making recommendations**
2. **Explain the root cause before proposing solutions**
3. **Detail what changes will be made and why**
4. **Confirm successful implementation with evidence**
5. **List any files that should be removed or cleaned up**

## 🎨 EXAMPLE IMPLEMENTATION PATTERN

```php
<?php
/**
 * Example WordPress Plugin Class
 * 
 * @package SCD_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class SCD_Example_Handler {
    
    /**
     * Initialize the handler
     */
    public function __construct() {
        add_action( 'init', array( $this, 'init' ) );
        add_action( 'wp_ajax_scd_process', array( $this, 'handle_ajax' ) );
    }
    
    /**
     * Initialize functionality
     */
    public function init() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        // Implementation following all standards
    }
    
    /**
     * Handle AJAX request
     */
    public function handle_ajax() {
        // Security check
        if ( ! wp_verify_nonce( $_POST['nonce'], 'scd_nonce' ) ) {
            wp_die( 'Security check failed' );
        }
        
        // Sanitize input
        $data = sanitize_text_field( $_POST['data'] );
        
        // Process and escape output
        wp_send_json_success( array(
            'message' => esc_html__( 'Success', 'scd-plugin' )
        ) );
    }
}
```

---

**Remember**: These rules ensure WordPress.org approval, maintainability, security, and optimal performance. Follow them strictly for every implementation.