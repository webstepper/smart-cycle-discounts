# SVG Icon System - Quick Reference Guide

## 🚀 Quick Start

### PHP Usage

```php
// Simple icon
<?php echo SCD_Icon_Helper::get( 'check' ); ?>

// Icon with size
<?php echo SCD_Icon_Helper::get( 'warning', array( 'size' => 24 ) ); ?>

// Icon with custom class
<?php echo SCD_Icon_Helper::get( 'delete', array( 'class' => 'text-danger' ) ); ?>

// Direct render (auto-echo)
<?php SCD_Icon_Helper::render( 'info', array( 'size' => 16 ) ); ?>
```

### JavaScript Usage

```javascript
// Simple icon
var html = SCD.IconHelper.get( 'check' );

// Icon with options
var html = SCD.IconHelper.get( 'warning', { size: 24, className: 'my-class' } );

// Convenience methods
var checkIcon = SCD.IconHelper.check();
var closeIcon = SCD.IconHelper.close();
var warningIcon = SCD.IconHelper.warning();
var infoIcon = SCD.IconHelper.info();
var spinnerIcon = SCD.IconHelper.spinner(); // Includes spinning animation

// Add to DOM
$('.container').append( SCD.IconHelper.get( 'check' ) );
```

---

## 📋 Available Icons (155 Total)

### Navigation
- `arrow-left`, `arrow-right`, `arrow-up`, `arrow-down`

### Actions
- `check`, `close`, `add`, `remove`, `edit`, `delete`, `visibility`, `copy`, `undo`, `download`, `upload`, `settings`

### Controls
- `play`, `pause`, `stop`, `repeat`

### Indicators
- `warning`, `info`, `error`, `success`

### Calendar/Time
- `calendar`, `schedule`, `clock`

### Commerce
- `tag`, `cart`, `receipt`, `trending-up`, `trending-down`, `products`, `money`, `money-alt`

### Data/Analytics
- `chart-line`, `chart-bar`, `chart-pie`, `chart-area`, `performance`

### Content
- `format-image`, `images-alt2`, `category`

### Security/Access
- `lock`, `unlock`, `shield-alt`, `shield`

### People/Social
- `groups`, `admin-users`, `superhero`

### Communication
- `megaphone`, `editor-help`, `lightbulb`, `bell`, `email`, `book`

### System/Actions
- `backup`, `update`, `saved`, `admin-generic`, `admin-settings`, `admin-appearance`, `admin-tools`, `admin-site-alt3`, `admin-network`, `admin-links`

### Misc
- `filter`, `search`, `menu`, `more-vert`, `move`, `infinity`, `star-filled`, `awards`, `palmtree`, `marker`, `tickets-alt`, `archive`, `randomize`, `list-view`, `no-alt`

---

## 🎨 Icon Options

### PHP Options

```php
$args = array(
    'class'       => '',              // Additional CSS classes
    'size'        => 20,              // Icon size in pixels (default: 20)
    'color'       => 'currentColor',  // Icon color (CSS value)
    'aria_label'  => '',              // Accessible label for screen readers
    'aria_hidden' => true,            // Hide from screen readers (default: true)
);
```

### JavaScript Options

```javascript
var options = {
    size: 20,              // Icon size in pixels (default: 20)
    className: '',         // Additional CSS classes
    ariaHidden: true       // Hide from screen readers (default: true)
};
```

---

## 🎯 Common Patterns

### Icon in Button (PHP)

```php
<button type="button" class="button">
    <?php echo SCD_Icon_Helper::get( 'check', array( 'size' => 16 ) ); ?>
    Save Changes
</button>
```

### Icon in Button (JavaScript)

```javascript
var $button = $( '<button type="button" class="button"></button>' )
    .append( SCD.IconHelper.get( 'check', { size: 16 } ) + ' ' )
    .append( 'Save Changes' );
```

### Spinner in Loading State

```javascript
// Show spinner
$button.addClass( 'is-loading' )
       .html( SCD.IconHelper.spinner() + ' Loading...' );

// Or with manual class
$icon.addClass( 'scd-icon-spin' );

// Remove spinner
$button.removeClass( 'is-loading' )
       .html( SCD.IconHelper.get( 'check' ) + ' Saved!' );
```

### Icon with Tooltip

```php
<span class="scd-tooltip" title="<?php esc_attr_e( 'Help text here', 'scd' ); ?>">
    <?php echo SCD_Icon_Helper::get( 'info', array( 'size' => 16 ) ); ?>
</span>
```

### Icon in List

```php
<ul class="scd-icon-list">
    <li>
        <?php echo SCD_Icon_Helper::get( 'check', array( 'size' => 16 ) ); ?>
        Feature enabled
    </li>
    <li>
        <?php echo SCD_Icon_Helper::get( 'close', array( 'size' => 16 ) ); ?>
        Feature disabled
    </li>
</ul>
```

---

## 🔧 CSS Classes

### Generated Classes

All icons receive these classes automatically:
- `.scd-icon` - Base icon class
- `.scd-icon--{name}` - Specific icon class (e.g., `.scd-icon--check`)

### Size Utilities

```css
.scd-icon-sm  /* 16px */
.scd-icon-md  /* 20px (default) */
.scd-icon-lg  /* 24px */
```

### Animation

```css
.scd-icon-spin  /* Spinning animation (1s rotation) */
```

---

## 🎨 Styling Icons

### Change Icon Color

```css
/* Icons inherit text color by default */
.my-container .scd-icon {
    color: #2271b1; /* WordPress blue */
}
```

### Rotate Icon

```css
.scd-icon--arrow-down.rotated {
    transform: rotate(180deg);
}
```

### Add Hover Effect

```css
button:hover .scd-icon {
    opacity: 0.7;
    transition: opacity 0.2s;
}
```

---

## ⚠️ Important Notes

### DO ✅

- Use `SCD_Icon_Helper` in PHP templates
- Use `SCD.IconHelper` in JavaScript
- Use `.scd-icon-spin` for spinning animations
- Check icon exists before using: `SCD_Icon_Helper::has_icon( 'name' )`

### DON'T ❌

- Don't hardcode SVG paths in templates
- Don't use old `.spin` class (use `.scd-icon-spin`)
- Don't create custom `@keyframes` for spinning (use existing)
- Don't use different animation durations (standardized at 1s)
- Don't modify icon helper classes directly

---

## 🐛 Troubleshooting

### Icon Not Appearing

**Problem**: Icon shows as blank space

**Solutions**:
1. Check icon name is correct: `SCD_Icon_Helper::get_available_icons()`
2. Verify JavaScript is loaded: Check browser console for errors
3. Check CSS is enqueued: Look for `shared/_utilities.css`

### Spinner Not Animating

**Problem**: Icon appears but doesn't spin

**Solutions**:
1. Ensure `.scd-icon-spin` class is added
2. Check CSS is loaded: Look for `@keyframes scd-spin` in browser DevTools
3. Verify no conflicting CSS: Check `transform` is not overridden

### Class Name Mismatch

**Problem**: CSS targeting `.scd-icon--check` not working

**Solutions**:
1. Verify using correct double-dash: `.scd-icon--{name}` (not single dash)
2. Check if JavaScript helper was updated to latest version
3. Clear browser cache and reload

---

## 📚 Advanced Usage

### Check Icon Exists

```php
// PHP
if ( SCD_Icon_Helper::has_icon( 'custom-icon' ) ) {
    echo SCD_Icon_Helper::get( 'custom-icon' );
}
```

```javascript
// JavaScript
if ( typeof scdIcons !== 'undefined' && scdIcons.paths['custom-icon'] ) {
    var icon = SCD.IconHelper.get( 'custom-icon' );
}
```

### Get All Available Icons

```php
// PHP - Returns array of icon names
$icons = SCD_Icon_Helper::get_available_icons();
```

```javascript
// JavaScript - Icons available in scdIcons.paths
if ( typeof scdIcons !== 'undefined' ) {
    var iconNames = Object.keys( scdIcons.paths );
}
```

### Add Icon to RowFactory

```javascript
var config = {
    fields: [
        {
            type: 'text',
            name: 'field_name',
            label: 'Field Label',
            icon: 'check'  // Icon will appear before label
        }
    ],
    removeButton: {
        enabled: true,
        icon: 'delete',  // Icon in remove button
        label: 'Remove'
    }
};
```

---

## 📝 File Locations

**PHP Icon Helper**:
`includes/admin/helpers/class-icon-helper.php`

**JavaScript Icon Helper**:
`resources/assets/js/shared/icon-helper.js`

**Icon Styles & Animation**:
`resources/assets/css/shared/_utilities.css`

**Icon Paths Localization**:
`includes/admin/assets/class-script-registry.php` (line 204-208)

---

## 🔗 Related Documentation

- Main Documentation: `SVG-ICON-SYSTEM-COMPLETE.md`
- WordPress Standards: `CLAUDE.md`
- Asset Management: `includes/admin/assets/README.md`

---

**Last Updated**: 2025-11-18
**System Version**: 2.0.0
**Status**: Production Ready ✅
