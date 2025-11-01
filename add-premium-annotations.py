#!/usr/bin/env python3
"""
Add @freemius_premium_only annotations to PRO files

This script adds the Freemius premium annotation to all files
that should be excluded from the FREE version.

Freemius will automatically strip these files when generating
the free version.

Usage:
    python3 add-premium-annotations.py
"""

import os
import sys

PLUGIN_DIR = os.path.dirname(os.path.abspath(__file__))

# Files that should be marked as premium-only
PREMIUM_ONLY_FILES = [
    # Analytics (entire directory)
    'includes/core/analytics/class-analytics-collector.php',
    'includes/core/analytics/class-analytics-controller.php',
    'includes/core/analytics/class-analytics-data.php',
    'includes/core/analytics/class-metrics-calculator.php',
    'includes/core/analytics/class-report-generator.php',
    'includes/core/analytics/class-export-service.php',
    'includes/core/analytics/class-activity-tracker.php',
    'includes/core/analytics/abstract-analytics-handler.php',
    'includes/core/analytics/trait-analytics-helpers.php',

    # Analytics pages
    'includes/admin/pages/class-analytics-page.php',
    'includes/admin/pages/class-analytics-dashboard.php',

    # REST API
    'includes/api/class-rest-api-manager.php',
    'includes/api/class-api-authentication.php',
    'includes/api/class-api-permissions.php',
    'includes/api/class-request-schemas.php',
    'includes/api/endpoints/class-campaigns-controller.php',
    'includes/api/endpoints/class-discounts-controller.php',

    # Advanced discount strategies
    'includes/core/discounts/strategies/class-tiered-strategy.php',
    'includes/core/discounts/strategies/class-bogo-strategy.php',
    'includes/core/discounts/strategies/class-spend-threshold-strategy.php',

    # Recurring campaigns
    'includes/class-recurring-handler.php',

    # Export/Import
    'includes/admin/ajax/handlers/class-export-handler.php',
    'includes/admin/ajax/handlers/class-import-export-handler.php',
    'includes/admin/ajax/handlers/class-import-handler.php',

    # Analytics AJAX handlers
    'includes/admin/ajax/handlers/class-campaign-performance-handler.php',
    'includes/admin/ajax/handlers/class-revenue-trend-handler.php',
    'includes/admin/ajax/handlers/class-top-products-handler.php',
    'includes/admin/ajax/handlers/class-main-dashboard-data-handler.php',
]


def add_premium_annotation(file_path):
    """
    Add @freemius_premium_only annotation to a PHP file.

    Args:
        file_path: Full path to PHP file

    Returns:
        bool: True if annotation was added, False if already present
    """
    if not os.path.exists(file_path):
        print(f"⚠️  File not found: {file_path}")
        return False

    try:
        with open(file_path, 'r', encoding='utf-8') as f:
            content = f.read()

        # Check if already annotated
        if '@freemius_premium_only' in content:
            return False

        # Find the docblock after <?php
        lines = content.split('\n')

        # Find where to insert
        insert_line = None
        in_docblock = False

        for i, line in enumerate(lines):
            stripped = line.strip()

            # Skip <?php line
            if stripped == '<?php':
                continue

            # Found start of docblock
            if stripped.startswith('/**'):
                in_docblock = True
                continue

            # Found end of docblock
            if in_docblock and stripped == '*/':
                insert_line = i
                break

        if insert_line is None:
            print(f"⚠️  Could not find docblock in: {file_path}")
            return False

        # Insert annotation before closing */
        lines.insert(insert_line, ' * @freemius_premium_only')

        # Write back
        with open(file_path, 'w', encoding='utf-8') as f:
            f.write('\n'.join(lines))

        return True

    except Exception as e:
        print(f"❌ Error processing {file_path}: {e}")
        return False


def main():
    print("=" * 70)
    print("Adding @freemius_premium_only Annotations")
    print("=" * 70)
    print("")

    annotated = 0
    skipped = 0
    errors = 0

    for rel_path in PREMIUM_ONLY_FILES:
        file_path = os.path.join(PLUGIN_DIR, rel_path)

        result = add_premium_annotation(file_path)

        if result is True:
            annotated += 1
            print(f"✅ Annotated: {rel_path}")
        elif result is False:
            if os.path.exists(file_path):
                skipped += 1
                print(f"⏭️  Already annotated: {rel_path}")
            else:
                errors += 1
        else:
            errors += 1

    print("")
    print("=" * 70)
    print("Summary")
    print("=" * 70)
    print(f"✅ Files annotated: {annotated}")
    print(f"⏭️  Already annotated: {skipped}")
    print(f"❌ Errors: {errors}")
    print("")

    if annotated > 0:
        print("🎯 Next Steps:")
        print("   1. Review the changes (git diff)")
        print("   2. Test that premium version still works")
        print("   3. Build: python3 build.py")
        print("   4. Upload to Freemius dashboard")
        print("   5. Freemius will auto-generate FREE version")
        print("")

    print("=" * 70)


if __name__ == '__main__':
    main()
