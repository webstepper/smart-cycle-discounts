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

### 🔄 Automatic Case Conversion System

**CRITICAL: The plugin has automatic bidirectional case conversion. NEVER add manual conversions!**

#### Inbound: JavaScript → PHP (AJAX Router)

**Location**: `includes/admin/ajax/class-ajax-router.php` (line 223)

```php
// AUTOMATIC CONVERSION - NO MANUAL MAPPING NEEDED
$request_data = self::camel_to_snake_keys( $request_data );
// Delegates to: WSSCD_Case_Converter::camel_to_snake( $data );
```

**What It Does:**
- ALL AJAX requests automatically convert camelCase → snake_case
- Runs BEFORE any handler processes the data
- Recursive: converts nested arrays and objects

**Example Data Flow:**
```javascript
// 1. JavaScript sends (camelCase):
$.post(ajaxurl, {
    campaignName: 'Summer Sale',
    productIds: [1, 2, 3],
    discountType: 'percentage',
    tiers: [
        { minQuantity: 5, discountValue: 10 },
        { minQuantity: 10, discountValue: 20 }
    ]
});

// 2. AJAX Router automatically converts to (snake_case):
// {
//     campaign_name: 'Summer Sale',
//     product_ids: [1, 2, 3],
//     discount_type: 'percentage',
//     tiers: [
//         { min_quantity: 5, discount_value: 10 },
//         { min_quantity: 10, discount_value: 20 }
//     ]
// }

// 3. Handler receives snake_case data:
public function handle( array $request_data ) {
    $campaign_name = $request_data['campaign_name'];  // ✅ Already converted
    $tiers = $request_data['tiers'];  // ✅ Nested arrays converted too
}
```

#### Outbound: PHP → JavaScript (Asset Localizer)

**Location**: `includes/admin/assets/class-asset-localizer.php` (lines 423-424)

```php
// AUTOMATIC CONVERSION - NO MANUAL MAPPING NEEDED
$localized_data = $this->snake_to_camel_keys( $this->data[ $object_name ] );
wp_localize_script( $handle, $object_name, $localized_data );
// Delegates to: WSSCD_Case_Converter::snake_to_camel( $data );
```

**What It Does:**
- ALL `wp_localize_script()` data automatically converts snake_case → camelCase
- Runs BEFORE data reaches JavaScript
- Recursive: converts nested arrays and objects

**Example Data Flow:**
```php
// 1. PHP prepares data (snake_case):
$wizard_data = array(
    'campaign_id' => 123,
    'product_selection_type' => 'specific_products',
    'discount_rules' => array(
        'discount_type' => 'tiered',
        'tiers' => array(
            array( 'min_quantity' => 5, 'discount_value' => 10 ),
            array( 'min_quantity' => 10, 'discount_value' => 20 )
        )
    )
);

// 2. Asset Localizer automatically converts to (camelCase):
// window.scdWizardData = {
//     campaignId: 123,
//     productSelectionType: 'specific_products',
//     discountRules: {
//         discountType: 'tiered',
//         tiers: [
//             { minQuantity: 5, discountValue: 10 },
//             { minQuantity: 10, discountValue: 20 }
//         ]
//     }
// };

// 3. JavaScript receives camelCase data:
var campaignId = window.scdWizardData.campaignId;  // ✅ Already converted
var tiers = window.scdWizardData.discountRules.tiers;  // ✅ Nested objects converted
```

#### ⚠️ WHEN NOT TO ADD MANUAL CONVERSIONS

**❌ NEVER DO THIS - Redundant Manual Mapping:**
```php
// ❌ WRONG: AJAX Router already did this conversion
public function handle( array $request_data ) {
    // Manual conversion is REDUNDANT - data is already in snake_case!
    $mapped_data = array();
    foreach ( $request_data['tiers'] as $tier ) {
        $mapped_data[] = array(
            'min_quantity' => $tier['minQuantity'] ?? $tier['min_quantity'],  // UNNECESSARY
            'discount_value' => $tier['discountValue'] ?? $tier['discount_value']  // UNNECESSARY
        );
    }
}
```

**✅ CORRECT: Trust the Auto-Conversion:**
```php
// ✅ CORRECT: Data is already in snake_case from AJAX Router
public function handle( array $request_data ) {
    // Use data directly - it's already converted!
    $tiers = $request_data['tiers'];
    foreach ( $tiers as $tier ) {
        $min_qty = $tier['min_quantity'];  // ✅ Already snake_case
        $discount = $tier['discount_value'];  // ✅ Already snake_case
    }
}
```

#### Legitimate Transformations (Not Case Conversion)

**Some transformations are architectural, not case-related:**

```php
// ✅ CORRECT: Flattening nested structures (architectural transformation)
if ( 'bogo' === $discount_type && isset( $discount_rules['bogo_config'] ) ) {
    $bogo_config = $discount_rules['bogo_config'];
    $discount_config = array_merge(
        $discount_config,
        array(
            'buy_quantity' => $bogo_config['buy_quantity'] ?? 1,
            'get_quantity' => $bogo_config['get_quantity'] ?? 1,
            'get_discount_percentage' => $bogo_config['discount_percent'] ?? 100,  // Field name mapping
        )
    );
}
```

**Why This Is OK:**
- Not case conversion (already snake_case)
- Flattens nested `bogo_config` object to flat structure
- Maps `discount_percent` (DB) → `get_discount_percentage` (strategy) - different field names

#### Utility Class Reference

**Location**: `includes/utilities/class-case-converter.php`

```php
// Bidirectional conversion utility
class WSSCD_Case_Converter {
    // JavaScript → PHP (used by AJAX Router)
    public static function camel_to_snake( $data );

    // PHP → JavaScript (used by Asset Localizer)
    public static function snake_to_camel( $data );
}
```

#### Summary: Trust the System

1. **JavaScript → PHP**: AJAX Router converts ALL incoming data automatically
2. **PHP → JavaScript**: Asset Localizer converts ALL outgoing data automatically
3. **Your Code**: Use data as-is, it's already in the correct case
4. **Manual Conversions**: Only add for architectural transformations (structure, field mapping), NEVER for case

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
$field.after('<div class="wsscd-field-error">' + message + '</div>');
$field.siblings('.wsscd-field-error-message').remove();
```

**ValidationError automatically integrates with NotificationService:**
- Field errors show as red borders (inline)
- Summary notification shows at top (global banner via NotificationService)
- Full WCAG 2.1 AA accessibility compliance
- Screen reader announcements
- Proper ARIA attributes

**Component-Specific showError Methods:**
Component modules have `showError()` methods that internally delegate to `ValidationError.show()`. This is the correct pattern - keep the component API but use centralized implementation.

## 🛡️ SECURITY REQUIREMENTS

### ALWAYS Implement:
1. **Nonce Verification**: `wp_verify_nonce( $_POST['nonce'], 'action' )`
2. **Capability Checks**: `current_user_can( 'capability' )`
3. **Input Sanitization**: `sanitize_text_field()`, `sanitize_email()`, etc.
4. **Output Escaping**: `esc_html()`, `esc_attr()`, `esc_url()`
5. **Database Security**: `$wpdb->prepare()` for ALL queries

### Input Sanitization Rules

**ALWAYS sanitize `$_GET`, `$_POST`, `$_REQUEST` - even for display logic:**
```php
// ❌ WRONG: Using $_GET without sanitization
if ( ! isset( $_GET['action'] ) ) {
    return true;
}

// ✅ CORRECT: Always sanitize, even for simple checks
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Safe: read-only display context check.
$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';
if ( empty( $action ) ) {
    return true;
}
```

### Context-Aware Output Escaping

**Use different escaping functions based on context:**
```php
// HTML content
echo esc_html( $text );

// HTML attributes
echo '<div class="' . esc_attr( $class ) . '">';

// URLs
echo '<a href="' . esc_url( $url ) . '">';

// JavaScript strings
echo 'var name = "' . esc_js( $name ) . '";';

// Allow safe HTML (user content with allowed tags)
echo wp_kses_post( $html_content );
```

**For email templates or dynamic content with multiple variable types:**
```php
// Context-aware escaping based on variable name suffix
private function replace_variables( string $content, array $variables ): string {
    foreach ( $variables as $key => $value ) {
        if ( ! is_string( $value ) ) {
            continue;
        }

        // URL variables: use URL escaping
        if ( preg_match( '/_url$/', $key ) ) {
            $escaped_value = esc_url( $value );
        // Pre-formatted HTML variables: allow safe HTML tags
        } elseif ( preg_match( '/_(summary|list|html|table|message)$/', $key ) ) {
            $escaped_value = wp_kses_post( $value );
        // Default: HTML-escape plain text
        } else {
            $escaped_value = esc_html( $value );
        }

        $content = str_replace( '{' . $key . '}', $escaped_value, $content );
    }
    return $content;
}
```

### Database Query Security

**Table names cannot be parameterized - use PHPCS ignore with explanation:**
```php
// ✅ CORRECT: Table name from trusted source with PHPCS ignore
$table = $wpdb->prefix . 'wsscd_campaigns';

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from trusted $wpdb->prefix.
$result = $wpdb->get_var(
    $wpdb->prepare(
        "SELECT name FROM {$table} WHERE id = %d",
        $id
    )
);
```

**For migrations with schema changes:**
```php
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Migration ALTER TABLE; table name from trusted source.
$wpdb->query(
    "ALTER TABLE {$table_name} ADD COLUMN new_column VARCHAR(255)"
);
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
```

### Variable Prefixing in Templates

**ALL variables in template files must use plugin prefix:**
```php
// ❌ WRONG: Generic variable name
$count = count( $items );

// ✅ CORRECT: Prefixed variable name
$wsscd_count = count( $wsscd_items );
```

### WordPress Core Hooks

**When using WordPress core filters/actions, add PHPCS ignore:**
```php
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- This is a WordPress core filter, not a custom hook.
if ( ! apply_filters( 'plugins_auto_update_enabled', true ) ) {
    return false;
}
```

## 🏗️ PLUGIN ARCHITECTURE PATTERNS

### Your Plugin Structure:
- **Prefix Convention**: `WSSCD_` for PHP classes, `wsscd_` for functions/hooks, `wsscd-` for assets (5-character prefix for WordPress.org compliance)
- **Service Container**: Dependency injection throughout
- **Asset Management**: Admin_Asset_Manager, Script_Registry, Style_Registry
- **Theme Management**: WSSCD_Theme_Manager for centralized theme operations
- **Modular Wizard**: Each step has state management, API, and orchestrator
- **MVC Pattern**: Separate views from business logic
- **Singleton Main Class**: Single initialization point
- **HPOS Compatibility**: Modern WooCommerce support

### Theme Management System

**WSSCD_Theme_Manager** (`includes/utilities/class-theme-manager.php`) - Centralized theme management

**Always use Theme_Manager for theme operations. Never access settings directly.**

```php
// ✅ CORRECT: Get current theme
$theme = WSSCD_Theme_Manager::get_current_theme();

// ✅ CORRECT: Check if theme is valid
if ( WSSCD_Theme_Manager::is_valid_theme( $theme ) ) {
    // Do something
}

// ✅ CORRECT: Get theme dropdown options
$options = WSSCD_Theme_Manager::get_theme_options();

// ✅ CORRECT: Get default theme
$default = WSSCD_Theme_Manager::get_default_theme();

// ✅ CORRECT: Get body class
$class = WSSCD_Theme_Manager::get_theme_body_class();

// ✅ CORRECT: Get CSS filename with validation
$filename = WSSCD_Theme_Manager::get_validated_theme_filename( $theme );

// ❌ WRONG: Direct settings access
$settings = get_option( 'wsscd_settings', array() );
$theme = $settings['general']['admin_theme'] ?? 'classic';  // Don't do this!

// ❌ WRONG: Hardcoded theme names
if ( 'classic' === $theme ) { }  // Use constants instead

// ✅ CORRECT: Use constants
if ( WSSCD_Theme_Manager::THEME_CLASSIC === $theme ) { }
```

**Available Constants:**
- `WSSCD_Theme_Manager::THEME_CLASSIC` - Classic theme identifier
- `WSSCD_Theme_Manager::THEME_ENHANCED` - Enhanced theme identifier
- `WSSCD_Theme_Manager::DEFAULT_THEME` - Default theme (classic)

**Extensibility:**
```php
// Add custom theme via filter
add_filter( 'wsscd_available_themes', function( $themes ) {
    $themes['custom'] = array(
        'label'       => 'Custom Theme',
        'description' => 'My custom theme',
        'file'        => 'theme-custom.css',
    );
    return $themes;
} );

// Override default theme
add_filter( 'wsscd_default_theme', function( $default ) {
    return 'enhanced';
} );
```

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
- [ ] Proper prefixing applied (WSSCD_, wsscd_, wsscd-)
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
 * @package WSSCD_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class WSSCD_Example_Handler {
    
    /**
     * Initialize the handler
     */
    public function __construct() {
        add_action( 'init', array( $this, 'init' ) );
        add_action( 'wp_ajax_wsscd_process', array( $this, 'handle_ajax' ) );
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
        if ( ! wp_verify_nonce( $_POST['nonce'], 'wsscd_nonce' ) ) {
            wp_die( 'Security check failed' );
        }
        
        // Sanitize input
        $data = sanitize_text_field( $_POST['data'] );
        
        // Process and escape output
        wp_send_json_success( array(
            'message' => esc_html__( 'Success', 'smart-cycle-discounts' )
        ) );
    }
}
```

## 🚀 DEPLOYMENT & RELEASE WORKFLOW

### Release Process Overview

The plugin is distributed via:
1. **Freemius** - Pro version sales + free version hosting
2. **WordPress.org** - Free version distribution

### Step-by-Step Release Process

#### Step 1: Make Code Changes
```
Edit files, fix bugs, add features as needed
```

#### Step 2: Update Version Numbers
Update version in THREE places:

**`smart-cycle-discounts.php`**:
```php
* Version: X.X.X           // Line 6 - Plugin header
* @version X.X.X           // Line 24 - Docblock
define( 'WSSCD_VERSION', 'X.X.X' );  // Line ~105 - Constant
```

**`readme.txt`** (Stable tag + changelog + upgrade notice):
```
Stable tag: X.X.X

== Changelog ==
= X.X.X =
* Description of changes

== Upgrade Notice ==
= X.X.X =
Short upgrade description.
```

#### Step 3: Commit & Push to GitHub
```bash
git add -A
git commit -m "Version X.X.X: Description of changes"
git push origin main
```

#### Step 4: Create Git Tag (Triggers Freemius Deploy)
```bash
git tag X.X.X
git push origin X.X.X
```
→ **GitHub Action automatically deploys to Freemius**

#### Step 5: Download Free Version from Freemius
1. Wait ~2 minutes for GitHub Action to complete
2. Go to **Freemius Dashboard** → **Deployment**
3. Download the **free version** zip file to the SCD-FREE folder:
   `C:\Users\Alienware\Local Sites\vvmdov\app\public\wp-content\plugins\SCD-FREE`

#### Step 6: Deploy to WordPress.org
```bash
# Run deploy script with version number (uses SCD-FREE folder automatically)
./deploy-to-wporg.sh X.X.X

# Or specify a custom path if needed:
./deploy-to-wporg.sh /path/to/smart-cycle-discounts-free-X.X.X.zip

# Script requires interactive auth - run SVN commands manually:
cd ~/svn-deploy/smart-cycle-discounts
svn cp trunk tags/X.X.X
svn commit -m "Version X.X.X" --username webstepper
```

#### Step 7: Update Banners (if changed)
```bash
cd ~/svn-deploy/smart-cycle-discounts
cp /path/to/.wordpress-org/banner-*.png assets/
svn commit -m "Updated banners" --username webstepper
```

### Quick Reference Commands

```bash
# 1. Git: Commit and push
git add -A
git commit -m "Version X.X.X: [changes]"
git push origin main
git tag X.X.X
git push origin X.X.X

# 2. Wait ~2 min for GitHub Action → Freemius

# 3. Download free zip from Freemius Dashboard to SCD-FREE folder

# 4. WordPress.org: Deploy (uses SCD-FREE folder automatically)
./deploy-to-wporg.sh X.X.X

# 5. SVN: Manual commit (script prepares files)
cd ~/svn-deploy/smart-cycle-discounts
svn cp trunk tags/X.X.X
svn commit -m "Version X.X.X" --username webstepper

# 6. (Optional) Update banners
cp /path/to/.wordpress-org/banner-*.png assets/
svn commit -m "Updated banners" --username webstepper
```

### Deployment Architecture

```
Code Changes
    ↓
git commit + push
    ↓
git tag X.X.X + push tag
    ↓
┌─────────────────────────────────────┐
│  GitHub Action (AUTOMATIC)          │
│  .github/workflows/freemius-deploy.yml
│  → Creates zip (excludes dev files) │
│  → Uploads to Freemius              │
│  → Freemius splits free/pro         │
└─────────────────────────────────────┘
    ↓
Download free zip to SCD-FREE folder
    ↓
┌─────────────────────────────────────┐
│  Manual: ./deploy-to-wporg.sh X.X.X │
│  → Reads zip from SCD-FREE folder   │
│  → Extracts zip to SVN trunk        │
│  → Creates version tag              │
│  → Commits to WordPress.org         │
└─────────────────────────────────────┘
```

### Key Files & Folders

| File/Folder | Purpose |
|-------------|---------|
| `.github/workflows/freemius-deploy.yml` | GitHub Action for Freemius |
| `deploy-to-wporg.sh` | WordPress.org SVN deploy script |
| `~/svn-deploy/smart-cycle-discounts/` | Local SVN checkout |
| `SCD-FREE/` (in plugins folder) | Free version zip downloads from Freemius |

### GitHub Secrets Required

These must be configured at: `https://github.com/webstepper/smart-cycle-discounts/settings/secrets/actions`

| Secret | Source |
|--------|--------|
| `FREEMIUS_DEV_ID` | Freemius → Profile → Developer ID |
| `FREEMIUS_PLUGIN_ID` | Freemius → Plugin → Settings → Plugin ID |
| `FREEMIUS_PUBLIC_KEY` | Freemius → Plugin → Settings → Public Key |
| `FREEMIUS_SECRET_KEY` | Freemius → Plugin → Settings → Secret Key |

### Files Excluded from Production Build

The GitHub Action excludes these from the Freemius zip:
- `.git*`, `.github/*` - Version control
- `.wordpress-org/*` - SVN assets only
- `tests/*`, `bin/*` - Development/testing
- `*.md`, `*.sh`, `*.py` - Documentation/scripts
- `composer.json`, `composer.lock` - PHP dependencies config
- `phpunit*.xml`, `package*.json` - Config files
- `vendor/*` (except `vendor/freemius/`) - Dev dependencies
- `Webstepper.io/*` - Website content

### Troubleshooting

**GitHub Action failed?**
- Check Actions tab: `https://github.com/webstepper/smart-cycle-discounts/actions`
- Verify all 4 secrets are configured correctly

**SVN commit failed?**
- Run `svn update` in `~/svn-deploy/smart-cycle-discounts/`
- Check SVN credentials

**Version mismatch?**
- Ensure version matches in BOTH `smart-cycle-discounts.php` AND `readme.txt`

---

**Remember**: These rules ensure WordPress.org approval, maintainability, security, and optimal performance. Follow them strictly for every implementation.