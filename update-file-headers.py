#!/usr/bin/env python3
"""
Update file headers across Smart Cycle Discounts plugin for WordPress.org submission.

This script standardizes all file headers with consistent copyright, author, and license information.
"""

import os
import re
from pathlib import Path
from datetime import datetime

# Configuration
PLUGIN_NAME = "Smart Cycle Discounts"
PLUGIN_SLUG = "smart-cycle-discounts"
AUTHOR_NAME = "Webstepper.io"
AUTHOR_EMAIL = "contact@webstepper.io"
COPYRIGHT_YEAR = "2025"
LICENSE = "GPL-3.0-or-later"
LICENSE_URL = "https://www.gnu.org/licenses/gpl-3.0.html"
PLUGIN_URL = "https://webstepper.io/wordpress-plugins/smart-cycle-discounts"

def get_file_description(filepath):
    """Generate appropriate description based on file path and name."""
    path_parts = Path(filepath).parts
    filename = Path(filepath).stem

    # Convert filename to title case description
    if filename.startswith('class-'):
        class_name = filename.replace('class-', '').replace('-', ' ').title()
        return f"{class_name} Class"
    elif filename.startswith('interface-'):
        interface_name = filename.replace('interface-', '').replace('-', ' ').title()
        return f"{interface_name} Interface"
    elif filename.startswith('trait-'):
        trait_name = filename.replace('trait-', '').replace('-', ' ').title()
        return f"{trait_name} Trait"
    else:
        return filename.replace('-', ' ').title()

def get_subpackage(filepath):
    """Determine subpackage based on file path."""
    path_parts = Path(filepath).parts

    # Find 'includes' or 'resources' in path
    try:
        if 'includes' in path_parts:
            idx = path_parts.index('includes')
            subpath = '/'.join(path_parts[idx:])
            return f"SmartCycleDiscounts/{subpath}"
        elif 'resources' in path_parts:
            idx = path_parts.index('resources')
            subpath = '/'.join(path_parts[idx:])
            return f"SmartCycleDiscounts/{subpath}"
    except:
        pass

    return "SmartCycleDiscounts"

def create_php_header(filepath, description=None):
    """Generate standardized PHP file header."""
    if not description:
        description = get_file_description(filepath)

    subpackage = get_subpackage(filepath)

    header = f"""<?php
/**
 * {description}
 *
 * @package    SmartCycleDiscounts
 * @subpackage {subpackage}
 * @author     {AUTHOR_NAME} <{AUTHOR_EMAIL}>
 * @copyright  {COPYRIGHT_YEAR} {AUTHOR_NAME}
 * @license    {LICENSE} {LICENSE_URL}
 * @link       {PLUGIN_URL}
 * @since      1.0.0
 */
"""
    return header

def create_js_header(filepath, description=None):
    """Generate standardized JavaScript file header."""
    if not description:
        description = get_file_description(filepath)

    subpackage = get_subpackage(filepath)

    header = f"""/**
 * {description}
 *
 * @package    SmartCycleDiscounts
 * @subpackage {subpackage}
 * @author     {AUTHOR_NAME} <{AUTHOR_EMAIL}>
 * @copyright  {COPYRIGHT_YEAR} {AUTHOR_NAME}
 * @license    {LICENSE} {LICENSE_URL}
 * @link       {PLUGIN_URL}
 * @since      1.0.0
 */
"""
    return header

def create_css_header(filepath, description=None):
    """Generate standardized CSS file header."""
    if not description:
        description = get_file_description(filepath)

    subpackage = get_subpackage(filepath)

    header = f"""/**
 * {description}
 *
 * @package    SmartCycleDiscounts
 * @subpackage {subpackage}
 * @author     {AUTHOR_NAME} <{AUTHOR_EMAIL}>
 * @copyright  {COPYRIGHT_YEAR} {AUTHOR_NAME}
 * @license    {LICENSE} {LICENSE_URL}
 * @link       {PLUGIN_URL}
 * @since      1.0.0
 */
"""
    return header

def extract_existing_header(content, file_type='php'):
    """Extract existing file header if present."""
    if file_type == 'php':
        # Match PHP doc block after opening <?php tag
        match = re.search(r'<\?php\s*/\*\*.*?\*/', content, re.DOTALL)
    else:
        # Match JS/CSS doc block at start
        match = re.search(r'^\s*/\*\*.*?\*/', content, re.DOTALL)

    if match:
        return match.group(0), match.end()
    return None, 0

def update_php_file(filepath):
    """Update PHP file header."""
    try:
        with open(filepath, 'r', encoding='utf-8') as f:
            content = f.read()

        # Check if this is the main plugin file (has Plugin Name header)
        if 'Plugin Name:' in content[:500]:
            print(f"  ⏭️  Skipping main plugin file: {filepath}")
            return False

        # Extract existing header
        old_header, header_end = extract_existing_header(content, 'php')

        # Generate new header
        new_header = create_php_header(filepath)

        # Find where code actually starts after header
        # Look for declare(strict_types) or ABSPATH check or first class/function
        code_start = header_end

        # Check for declare(strict_types=1);
        has_strict_types = False
        strict_types_match = re.search(r'\ndeclare\(strict_types=1\);', content)
        if strict_types_match:
            has_strict_types = True
            code_start = strict_types_match.end()

        # Get the rest of the code
        remaining_code = content[code_start:].lstrip()

        # Rebuild file
        new_content = new_header

        if has_strict_types:
            new_content += "\ndeclare(strict_types=1);\n"

        new_content += "\n" + remaining_code

        # Write back
        with open(filepath, 'w', encoding='utf-8') as f:
            f.write(new_content)

        return True

    except Exception as e:
        print(f"  ❌ Error processing {filepath}: {e}")
        return False

def update_js_file(filepath):
    """Update JavaScript file header."""
    try:
        with open(filepath, 'r', encoding='utf-8') as f:
            content = f.read()

        # Extract existing header
        old_header, header_end = extract_existing_header(content, 'js')

        # Generate new header
        new_header = create_js_header(filepath)

        # Get remaining code
        remaining_code = content[header_end:].lstrip()

        # Rebuild file
        new_content = new_header + "\n" + remaining_code

        # Write back
        with open(filepath, 'w', encoding='utf-8') as f:
            f.write(new_content)

        return True

    except Exception as e:
        print(f"  ❌ Error processing {filepath}: {e}")
        return False

def update_css_file(filepath):
    """Update CSS file header."""
    try:
        with open(filepath, 'r', encoding='utf-8') as f:
            content = f.read()

        # Extract existing header
        old_header, header_end = extract_existing_header(content, 'css')

        # Generate new header
        new_header = create_css_header(filepath)

        # Get remaining code
        remaining_code = content[header_end:].lstrip()

        # Rebuild file
        new_content = new_header + "\n" + remaining_code

        # Write back
        with open(filepath, 'w', encoding='utf-8') as f:
            f.write(new_content)

        return True

    except Exception as e:
        print(f"  ❌ Error processing {filepath}: {e}")
        return False

def main():
    """Main execution."""
    print("=" * 70)
    print(f"Smart Cycle Discounts - File Header Update Script")
    print("=" * 70)
    print(f"Author: {AUTHOR_NAME} <{AUTHOR_EMAIL}>")
    print(f"Copyright: {COPYRIGHT_YEAR}")
    print(f"License: {LICENSE}")
    print("=" * 70)
    print()

    # Get plugin root directory
    plugin_root = Path(__file__).parent

    stats = {
        'php_updated': 0,
        'php_skipped': 0,
        'php_failed': 0,
        'js_updated': 0,
        'js_skipped': 0,
        'js_failed': 0,
        'css_updated': 0,
        'css_skipped': 0,
        'css_failed': 0,
    }

    # Update PHP files in includes/
    print("📝 Updating PHP files in includes/...")
    includes_dir = plugin_root / 'includes'
    if includes_dir.exists():
        for php_file in includes_dir.rglob('*.php'):
            # Skip vendor and node_modules
            if 'vendor' in php_file.parts or 'node_modules' in php_file.parts:
                continue
            if 'freemius' in php_file.parts or 'wordpress-sdk' in str(php_file):
                print(f"  ⏭️  Skipping third-party: {php_file.name}")
                stats['php_skipped'] += 1
                continue

            print(f"  📄 Processing: {php_file.name}")
            if update_php_file(php_file):
                stats['php_updated'] += 1
            else:
                stats['php_failed'] += 1

    # Update JavaScript files
    print("\n📝 Updating JavaScript files in resources/assets/js/...")
    js_dir = plugin_root / 'resources' / 'assets' / 'js'
    if js_dir.exists():
        for js_file in js_dir.rglob('*.js'):
            if 'node_modules' in js_file.parts or 'vendor' in js_file.parts:
                continue
            if 'min.js' in js_file.name:
                print(f"  ⏭️  Skipping minified: {js_file.name}")
                stats['js_skipped'] += 1
                continue

            print(f"  📄 Processing: {js_file.name}")
            if update_js_file(js_file):
                stats['js_updated'] += 1
            else:
                stats['js_failed'] += 1

    # Update CSS files
    print("\n📝 Updating CSS files in resources/assets/css/...")
    css_dir = plugin_root / 'resources' / 'assets' / 'css'
    if css_dir.exists():
        for css_file in css_dir.rglob('*.css'):
            if 'node_modules' in css_file.parts or 'vendor' in css_file.parts:
                continue
            if 'min.css' in css_file.name:
                print(f"  ⏭️  Skipping minified: {css_file.name}")
                stats['css_skipped'] += 1
                continue

            print(f"  📄 Processing: {css_file.name}")
            if update_css_file(css_file):
                stats['css_updated'] += 1
            else:
                stats['css_failed'] += 1

    # Print summary
    print("\n" + "=" * 70)
    print("📊 UPDATE SUMMARY")
    print("=" * 70)
    print(f"PHP Files:")
    print(f"  ✅ Updated: {stats['php_updated']}")
    print(f"  ⏭️  Skipped: {stats['php_skipped']}")
    print(f"  ❌ Failed:  {stats['php_failed']}")
    print()
    print(f"JavaScript Files:")
    print(f"  ✅ Updated: {stats['js_updated']}")
    print(f"  ⏭️  Skipped: {stats['js_skipped']}")
    print(f"  ❌ Failed:  {stats['js_failed']}")
    print()
    print(f"CSS Files:")
    print(f"  ✅ Updated: {stats['css_updated']}")
    print(f"  ⏭️  Skipped: {stats['css_skipped']}")
    print(f"  ❌ Failed:  {stats['css_failed']}")
    print()
    total_updated = stats['php_updated'] + stats['js_updated'] + stats['css_updated']
    total_failed = stats['php_failed'] + stats['js_failed'] + stats['css_failed']
    print(f"🎯 TOTAL: {total_updated} files updated, {total_failed} failed")
    print("=" * 70)

    if total_failed == 0:
        print("\n✅ All file headers updated successfully!")
        print("🚀 Your plugin is now ready for WordPress.org submission!")
    else:
        print(f"\n⚠️  {total_failed} files had errors. Please review manually.")

if __name__ == '__main__':
    main()
