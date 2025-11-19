#!/usr/bin/env python3
"""
CSS Variable Replacer - CLAUDE.md Compliant
============================================
Replaces hardcoded CSS values with CSS variables.
No build process - direct find/replace in source files.

Usage: python3 css-variable-replacer.py
"""

import os
import re
import sys

# Configuration
CSS_DIR = 'resources/assets/css'
BACKUP_SUFFIX = '.backup'
DRY_RUN = False  # Set to True to preview changes without modifying files

# Comprehensive mapping of hardcoded values to CSS variables
# Organized by category for clarity
REPLACEMENTS = {
    # ========== PADDING ==========
    # Format: (pattern, replacement, description)

    # Single value padding
    (r'\bpadding:\s*2px\b', r'padding: var(--scd-spacing-xxs)', 'padding 2px'),
    (r'\bpadding:\s*4px\b', r'padding: var(--scd-spacing-xs)', 'padding 4px'),
    (r'\bpadding:\s*8px\b', r'padding: var(--scd-padding-small)', 'padding 8px'),
    (r'\bpadding:\s*10px\b', r'padding: var(--scd-padding-sm-large)', 'padding 10px'),
    (r'\bpadding:\s*12px\b', r'padding: var(--scd-padding-compact)', 'padding 12px'),
    (r'\bpadding:\s*14px\b', r'padding: var(--scd-padding-medium)', 'padding 14px'),
    (r'\bpadding:\s*15px\b', r'padding: var(--scd-padding-compact)', 'padding 15px'),
    (r'\bpadding:\s*16px\b', r'padding: var(--scd-padding-large)', 'padding 16px'),
    (r'\bpadding:\s*20px\b', r'padding: var(--scd-padding-section)', 'padding 20px'),
    (r'\bpadding:\s*24px\b', r'padding: var(--scd-padding-section)', 'padding 24px'),
    (r'\bpadding:\s*30px\b', r'padding: var(--scd-padding-spacious)', 'padding 30px'),

    # Two-value padding (vertical horizontal) - Now compose from single values
    (r'\bpadding:\s*8px\s+12px\b', r'padding: var(--scd-padding-small) var(--scd-padding-compact)', 'padding 8px 12px'),
    (r'\bpadding:\s*10px\s+14px\b', r'padding: var(--scd-padding-sm-large) var(--scd-padding-medium)', 'padding 10px 14px'),
    (r'\bpadding:\s*12px\s+16px\b', r'padding: var(--scd-padding-compact) var(--scd-padding-large)', 'padding 12px 16px'),

    # Padding-top, padding-bottom, padding-left, padding-right
    (r'\bpadding-top:\s*8px\b', r'padding-top: var(--scd-spacing-sm)', 'padding-top 8px'),
    (r'\bpadding-top:\s*12px\b', r'padding-top: var(--scd-spacing-md)', 'padding-top 12px'),
    (r'\bpadding-top:\s*16px\b', r'padding-top: var(--scd-spacing-base)', 'padding-top 16px'),
    (r'\bpadding-top:\s*20px\b', r'padding-top: var(--scd-spacing-lg)', 'padding-top 20px'),
    (r'\bpadding-bottom:\s*8px\b', r'padding-bottom: var(--scd-spacing-sm)', 'padding-bottom 8px'),
    (r'\bpadding-bottom:\s*12px\b', r'padding-bottom: var(--scd-spacing-md)', 'padding-bottom 12px'),
    (r'\bpadding-bottom:\s*16px\b', r'padding-bottom: var(--scd-spacing-base)', 'padding-bottom 16px'),
    (r'\bpadding-bottom:\s*20px\b', r'padding-bottom: var(--scd-spacing-lg)', 'padding-bottom 20px'),
    (r'\bpadding-left:\s*12px\b', r'padding-left: var(--scd-spacing-md)', 'padding-left 12px'),
    (r'\bpadding-right:\s*12px\b', r'padding-right: var(--scd-spacing-md)', 'padding-right 12px'),

    # ========== MARGIN ==========
    (r'\bmargin:\s*4px\b', r'margin: var(--scd-spacing-xs)', 'margin 4px'),
    (r'\bmargin:\s*8px\b', r'margin: var(--scd-spacing-sm)', 'margin 8px'),
    (r'\bmargin:\s*12px\b', r'margin: var(--scd-spacing-md)', 'margin 12px'),
    (r'\bmargin:\s*16px\b', r'margin: var(--scd-spacing-base)', 'margin 16px'),
    (r'\bmargin:\s*20px\b', r'margin: var(--scd-spacing-lg)', 'margin 20px'),
    (r'\bmargin:\s*24px\b', r'margin: var(--scd-spacing-xl)', 'margin 24px'),

    (r'\bmargin-top:\s*4px\b', r'margin-top: var(--scd-spacing-xs)', 'margin-top 4px'),
    (r'\bmargin-top:\s*8px\b', r'margin-top: var(--scd-spacing-sm)', 'margin-top 8px'),
    (r'\bmargin-top:\s*12px\b', r'margin-top: var(--scd-spacing-md)', 'margin-top 12px'),
    (r'\bmargin-top:\s*16px\b', r'margin-top: var(--scd-spacing-base)', 'margin-top 16px'),
    (r'\bmargin-top:\s*20px\b', r'margin-top: var(--scd-spacing-lg)', 'margin-top 20px'),
    (r'\bmargin-bottom:\s*8px\b', r'margin-bottom: var(--scd-spacing-sm)', 'margin-bottom 8px'),
    (r'\bmargin-bottom:\s*12px\b', r'margin-bottom: var(--scd-spacing-md)', 'margin-bottom 12px'),
    (r'\bmargin-bottom:\s*16px\b', r'margin-bottom: var(--scd-spacing-base)', 'margin-bottom 16px'),
    (r'\bmargin-bottom:\s*20px\b', r'margin-bottom: var(--scd-spacing-lg)', 'margin-bottom 20px'),
    (r'\bmargin-left:\s*6px\b', r'margin-left: var(--scd-spacing-xs)', 'margin-left 6px'),
    (r'\bmargin-left:\s*12px\b', r'margin-left: var(--scd-spacing-md)', 'margin-left 12px'),

    # ========== GAP ==========
    (r'\bgap:\s*8px\b', r'gap: var(--scd-gap-tight)', 'gap 8px'),
    (r'\bgap:\s*10px\b', r'gap: var(--scd-gap-tight)', 'gap 10px'),
    (r'\bgap:\s*12px\b', r'gap: var(--scd-gap-normal)', 'gap 12px'),
    (r'\bgap:\s*14px\b', r'gap: var(--scd-gap-sm-medium)', 'gap 14px'),
    (r'\bgap:\s*16px\b', r'gap: var(--scd-gap-normal)', 'gap 16px'),
    (r'\bgap:\s*20px\b', r'gap: var(--scd-gap-comfortable)', 'gap 20px'),
    (r'\bgap:\s*24px\b', r'gap: var(--scd-gap-spacious)', 'gap 24px'),
    (r'\bgap:\s*30px\b', r'gap: var(--scd-gap-large)', 'gap 30px'),

    # ========== BORDER RADIUS ==========
    (r'\bborder-radius:\s*0px?\b', r'border-radius: var(--scd-radius-sm)', 'border-radius 0'),
    (r'\bborder-radius:\s*3px\b', r'border-radius: var(--scd-radius-custom)', 'border-radius 3px'),
    (r'\bborder-radius:\s*4px\b', r'border-radius: var(--scd-radius-md)', 'border-radius 4px'),
    (r'\bborder-radius:\s*6px\b', r'border-radius: var(--scd-radius-custom)', 'border-radius 6px'),
    (r'\bborder-radius:\s*8px\b', r'border-radius: var(--scd-radius-md)', 'border-radius 8px'),
    (r'\bborder-radius:\s*9px\b', r'border-radius: var(--scd-radius-md)', 'border-radius 9px'),
    (r'\bborder-radius:\s*12px\b', r'border-radius: var(--scd-radius-lg)', 'border-radius 12px'),

    # ========== FONT SIZE ==========
    (r'\bfont-size:\s*11px\b', r'font-size: var(--scd-font-size-small)', 'font-size 11px'),
    (r'\bfont-size:\s*12px\b', r'font-size: var(--scd-font-size-small)', 'font-size 12px'),
    (r'\bfont-size:\s*13px\b', r'font-size: var(--scd-font-size-base)', 'font-size 13px'),
    (r'\bfont-size:\s*14px\b', r'font-size: var(--scd-font-size-medium)', 'font-size 14px'),
    (r'\bfont-size:\s*16px\b', r'font-size: var(--scd-font-size-large)', 'font-size 16px'),
    (r'\bfont-size:\s*18px\b', r'font-size: var(--scd-font-size-xl)', 'font-size 18px'),
    (r'\bfont-size:\s*20px\b', r'font-size: var(--scd-font-size-xxl)', 'font-size 20px'),
    (r'\bfont-size:\s*24px\b', r'font-size: var(--scd-font-size-xxxl)', 'font-size 24px'),

    # ========== HEIGHT / MIN-HEIGHT ==========
    (r'\bheight:\s*18px\b', r'height: var(--scd-icon-small)', 'height 18px'),
    (r'\bheight:\s*20px\b', r'height: var(--scd-icon-medium)', 'height 20px'),
    (r'\bheight:\s*24px\b', r'height: var(--scd-icon-large)', 'height 24px'),
    (r'\bheight:\s*26px\b', r'height: var(--scd-button-height)', 'height 26px'),
    (r'\bheight:\s*30px\b', r'height: var(--scd-button-height)', 'height 30px'),
    (r'\bheight:\s*32px\b', r'height: var(--scd-input-height-large)', 'height 32px'),
    (r'\bheight:\s*38px\b', r'height: var(--scd-button-height)', 'height 38px'),
    (r'\bheight:\s*42px\b', r'height: var(--scd-input-height)', 'height 42px'),
    (r'\bheight:\s*44px\b', r'height: var(--scd-button-height-large)', 'height 44px'),
    (r'\bheight:\s*48px\b', r'height: var(--scd-input-height-large)', 'height 48px'),

    (r'\bmin-height:\s*26px\b', r'min-height: var(--scd-button-height)', 'min-height 26px'),
    (r'\bmin-height:\s*30px\b', r'min-height: var(--scd-button-height)', 'min-height 30px'),
    (r'\bmin-height:\s*32px\b', r'min-height: var(--scd-input-height-large)', 'min-height 32px'),
    (r'\bmin-height:\s*38px\b', r'min-height: var(--scd-button-height)', 'min-height 38px'),
    (r'\bmin-height:\s*42px\b', r'min-height: var(--scd-input-height)', 'min-height 42px'),

    # ========== WIDTH ==========
    (r'\bwidth:\s*18px\b', r'width: var(--scd-icon-small)', 'width 18px'),
    (r'\bwidth:\s*20px\b', r'width: var(--scd-icon-medium)', 'width 20px'),
    (r'\bwidth:\s*24px\b', r'width: var(--scd-icon-large)', 'width 24px'),
}

# Namespace pollution fixes - classes without scd- prefix
NAMESPACE_FIXES = {
    r'\.form-field\b': r'.scd-form-field',
    r'\.rules-table\b': r'.scd-rules-table',
    r'\.input-wrapper\b': r'.scd-input-wrapper',
    r'\.field-suffix\b': r'.scd-field-suffix',
}


def backup_file(filepath):
    """Create backup of file before modifying."""
    backup_path = filepath + BACKUP_SUFFIX
    if not os.path.exists(backup_path):
        with open(filepath, 'r', encoding='utf-8') as f:
            content = f.read()
        with open(backup_path, 'w', encoding='utf-8') as f:
            f.write(content)
        return True
    return False


def process_file(filepath):
    """Process a single CSS file."""
    try:
        with open(filepath, 'r', encoding='utf-8') as f:
            content = f.read()

        original_content = content
        changes_made = []

        # Apply value replacements
        for pattern, replacement, description in REPLACEMENTS:
            matches = re.findall(pattern, content)
            if matches:
                content = re.sub(pattern, replacement, content)
                changes_made.append(f"  - Replaced {len(matches)} instance(s) of {description}")

        # Apply namespace fixes
        for pattern, replacement in NAMESPACE_FIXES.items():
            matches = re.findall(pattern, content)
            if matches:
                content = re.sub(pattern, replacement, content)
                changes_made.append(f"  - Fixed {len(matches)} namespace issue(s): {pattern}")

        # Only write if changes were made
        if content != original_content:
            if not DRY_RUN:
                backup_file(filepath)
                with open(filepath, 'w', encoding='utf-8') as f:
                    f.write(content)

            print(f"\n✓ Modified: {filepath}")
            for change in changes_made:
                print(change)

            return len(changes_made)

        return 0

    except Exception as e:
        print(f"\n✗ Error processing {filepath}: {str(e)}")
        return 0


def main():
    """Main execution."""
    print("=" * 70)
    print("CSS Variable Replacer - CLAUDE.md Compliant")
    print("=" * 70)
    print(f"\nSearching for CSS files in: {CSS_DIR}")

    if DRY_RUN:
        print("\n⚠️  DRY RUN MODE - No files will be modified")
    else:
        print("\n⚠️  Files will be modified (backups created with .backup extension)")

    # Check if running interactively
    if sys.stdin.isatty():
        print("\nPress Enter to continue, or Ctrl+C to cancel...")
        try:
            input()
        except KeyboardInterrupt:
            print("\n\nCancelled by user.")
            sys.exit(0)
    else:
        print("\nRunning in non-interactive mode, proceeding automatically...")

    # Find all CSS files
    css_files = []
    for root, dirs, files in os.walk(CSS_DIR):
        for filename in files:
            if filename.endswith('.css') and not filename.endswith('.backup'):
                css_files.append(os.path.join(root, filename))

    print(f"\nFound {len(css_files)} CSS files to process\n")
    print("-" * 70)

    # Process files
    total_changes = 0
    files_modified = 0

    for filepath in css_files:
        changes = process_file(filepath)
        if changes > 0:
            files_modified += 1
            total_changes += changes

    # Summary
    print("\n" + "=" * 70)
    print("SUMMARY")
    print("=" * 70)
    print(f"Files processed: {len(css_files)}")
    print(f"Files modified: {files_modified}")
    print(f"Total changes: {total_changes}")

    if not DRY_RUN:
        print(f"\n✓ Backups saved with {BACKUP_SUFFIX} extension")
        print("✓ All hardcoded values replaced with CSS variables")
        print("\nNext steps:")
        print("1. Test both Enhanced and Classic themes")
        print("2. Verify all styles render correctly")
        print("3. Remove .backup files once verified")
    else:
        print("\n⚠️  This was a DRY RUN - no files were modified")
        print("Set DRY_RUN = False to apply changes")

    print("\n" + "=" * 70)


if __name__ == '__main__':
    main()
