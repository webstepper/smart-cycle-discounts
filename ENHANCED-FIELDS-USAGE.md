# Enhanced Form Fields - Global Usage Guide

The beautiful field styling from the "Configure Discount Rules" section is now available globally throughout the plugin.

## What Was Added

### CSS (_forms.css)
- `.scd-enhanced-input` - Premium styled input fields
- `.scd-enhanced-select` - Premium styled select dropdowns
- `.scd-input-wrapper` - Flex container for input + suffix
- `.scd-field-suffix` - Descriptive label after input
- `.scd-rules-table` - Enhanced form table styling
- `.scd-label-icon` - Optional icon before label
- `.scd-input-group` - Input with prefix (e.g., $, %)
- `.scd-input-prefix` - Currency/unit prefix
- `.scd-select-wrapper` - Custom styled select wrapper

### PHP Helper Function (template-wrapper.php)
- `scd_wizard_enhanced_field()` - Complete field with all enhancements

## Usage Examples

### Example 1: Basic Field with Suffix

```php
<table class="form-table scd-rules-table">
    <?php
    scd_wizard_enhanced_field( array(
        'id'          => 'cache_duration',
        'name'        => 'cache_duration',
        'label'       => __( 'Cache Duration', 'smart-cycle-discounts' ),
        'type'        => 'number',
        'value'       => get_option( 'scd_cache_duration', '60' ),
        'min'         => '0',
        'step'        => '1',
        'tooltip'     => __( 'How long to cache data', 'smart-cycle-discounts' ),
        'suffix'      => __( 'minutes', 'smart-cycle-discounts' ),
        'placeholder' => '60'
    ) );
    ?>
</table>
```

### Example 2: Field with Icon and Tooltip

```php
<table class="form-table scd-rules-table">
    <?php
    scd_wizard_enhanced_field( array(
        'id'       => 'max_products',
        'name'     => 'max_products',
        'label'    => __( 'Maximum Products', 'smart-cycle-discounts' ),
        'type'     => 'number',
        'value'    => get_option( 'scd_max_products', '100' ),
        'min'      => '1',
        'icon'     => 'products',
        'tooltip'  => __( 'Maximum number of products per campaign', 'smart-cycle-discounts' ),
        'suffix'   => __( 'products', 'smart-cycle-discounts' )
    ) );
    ?>
</table>
```

### Example 3: Required Field with Validation

```php
<table class="form-table scd-rules-table">
    <?php
    scd_wizard_enhanced_field( array(
        'id'       => 'discount_percentage',
        'name'     => 'discount_percentage',
        'label'    => __( 'Discount Amount', 'smart-cycle-discounts' ),
        'type'     => 'number',
        'value'    => $discount_value,
        'min'      => '1',
        'max'      => '100',
        'step'     => '1',
        'required' => true,
        'icon'     => 'tag',
        'tooltip'  => __( 'Percentage off regular price', 'smart-cycle-discounts' ),
        'suffix'   => '%'
    ) );
    ?>
</table>
```

### Example 4: Manual HTML (Without Helper Function)

```php
<table class="form-table scd-rules-table">
    <tr>
        <th scope="row">
            <label for="email_limit">
                <span class="scd-label-icon">
                    <span class="dashicons dashicons-email"></span>
                </span>
                <?php esc_html_e( 'Email Limit', 'smart-cycle-discounts' ); ?>
                <span class="scd-field-helper" data-tooltip="<?php esc_attr_e( 'Maximum emails per hour', 'smart-cycle-discounts' ); ?>">
                    <span class="dashicons dashicons-editor-help"></span>
                </span>
            </label>
        </th>
        <td>
            <div class="scd-input-wrapper">
                <input type="number"
                       id="email_limit"
                       name="email_limit"
                       value="100"
                       min="0"
                       class="scd-enhanced-input"
                       placeholder="100">
                <span class="scd-field-suffix"><?php esc_html_e( 'emails/hour', 'smart-cycle-discounts' ); ?></span>
            </div>
        </td>
    </tr>
</table>
```

### Example 5: Input with Currency Prefix

```php
<div class="scd-input-wrapper">
    <div class="scd-input-group">
        <span class="scd-input-prefix">$</span>
        <input type="number"
               id="minimum_order"
               name="minimum_order"
               value="50.00"
               step="0.01"
               class="scd-enhanced-input">
    </div>
    <span class="scd-field-suffix"><?php esc_html_e( 'minimum', 'smart-cycle-discounts' ); ?></span>
</div>
```

### Example 6: Input with Percentage Prefix

```php
<div class="scd-input-group scd-input-with-prefix">
    <span class="scd-input-prefix">%</span>
    <input type="number"
           id="tax_rate"
           name="tax_rate"
           value="8.5"
           min="0"
           max="100"
           step="0.1"
           class="scd-enhanced-input">
</div>
```

### Example 7: Enhanced Select Dropdown

```php
<div class="scd-select-wrapper">
    <select id="frequency" name="frequency" class="scd-enhanced-select">
        <option value="hourly"><?php esc_html_e( 'Hourly', 'smart-cycle-discounts' ); ?></option>
        <option value="daily"><?php esc_html_e( 'Daily', 'smart-cycle-discounts' ); ?></option>
        <option value="weekly"><?php esc_html_e( 'Weekly', 'smart-cycle-discounts' ); ?></option>
    </select>
</div>
```

### Example 8: Settings Page Section

```php
<div class="wrap">
    <h1><?php esc_html_e( 'Plugin Settings', 'smart-cycle-discounts' ); ?></h1>

    <form method="post" action="options.php">
        <?php settings_fields( 'scd_settings' ); ?>

        <table class="form-table scd-rules-table">
            <?php
            // Cache Settings
            scd_wizard_enhanced_field( array(
                'id'       => 'cache_ttl',
                'name'     => 'scd_settings[cache_ttl]',
                'label'    => __( 'Cache TTL', 'smart-cycle-discounts' ),
                'type'     => 'number',
                'value'    => $settings['cache_ttl'] ?? '3600',
                'min'      => '0',
                'icon'     => 'clock',
                'tooltip'  => __( 'Cache time-to-live in seconds', 'smart-cycle-discounts' ),
                'suffix'   => __( 'seconds', 'smart-cycle-discounts' )
            ) );

            // Batch Size
            scd_wizard_enhanced_field( array(
                'id'       => 'batch_size',
                'name'     => 'scd_settings[batch_size]',
                'label'    => __( 'Batch Size', 'smart-cycle-discounts' ),
                'type'     => 'number',
                'value'    => $settings['batch_size'] ?? '50',
                'min'      => '1',
                'max'      => '500',
                'icon'     => 'database',
                'tooltip'  => __( 'Number of items processed per batch', 'smart-cycle-discounts' ),
                'suffix'   => __( 'items', 'smart-cycle-discounts' )
            ) );
            ?>
        </table>

        <?php submit_button(); ?>
    </form>
</div>
```

## CSS Classes Reference

### Input Styling
- `.scd-enhanced-input` - Apply to any text/number input
- `.scd-enhanced-select` - Apply to select dropdowns

### Layout Components
- `.scd-input-wrapper` - Wrap input + suffix together
- `.scd-field-suffix` - Text label after input
- `.scd-label-icon` - Icon before label text
- `.scd-input-group` - Container for input with prefix
- `.scd-input-prefix` - Prefix element (e.g., $, %)
- `.scd-select-wrapper` - Wrapper for custom select styling

### Table Structure
- `.scd-rules-table` - Enhanced form table

## Available Parameters

### scd_wizard_enhanced_field() Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `id` | string | Yes | Unique field ID |
| `name` | string | Yes | Field name attribute |
| `label` | string | Yes | Label text |
| `type` | string | No | Input type (default: 'text') |
| `value` | string | No | Field value |
| `placeholder` | string | No | Placeholder text |
| `required` | bool | No | Is field required (default: false) |
| `icon` | string | No | Dashicon name (without 'dashicons-') |
| `tooltip` | string | No | Tooltip help text |
| `suffix` | string | No | Text after input (e.g., 'uses', 'minutes') |
| `min` | string | No | Minimum value (number inputs) |
| `max` | string | No | Maximum value (number inputs) |
| `step` | string | No | Step increment (e.g., '1', '0.01') |
| `class` | string | No | Additional CSS classes |

## Where to Use

This enhanced field pattern can be used in:

✅ Settings pages
✅ Admin configuration screens
✅ Campaign edit pages
✅ Tools pages
✅ Any custom admin form
✅ Meta boxes
✅ Widget settings

## Styling Features

- ✨ Premium look with subtle shadows
- 🎨 Consistent with WordPress admin design
- 📏 Standardized dimensions (38px height)
- 🎯 Clear focus states
- 💪 Maximum width constraint (400px)
- 🔄 Smooth transitions
- ♿ Accessibility compliant
- 📱 Responsive design

## Notes

- The CSS is automatically loaded via `_forms.css` (shared styles)
- The helper function is available anywhere `template-wrapper.php` is included
- All fields use CSS variables for theme compatibility
- Focus states use WordPress admin theme colors
- Tooltips integrate with existing `SCD_Tooltip_Helper` system
