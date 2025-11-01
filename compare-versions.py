#!/usr/bin/env python3
"""
Smart Cycle Discounts - Version Comparison Tool

Compares FREE and PREMIUM versions to show differences.

Usage:
    python3 compare-versions.py
"""

import zipfile
import os

PLUGIN_DIR = os.path.dirname(os.path.abspath(__file__))
PARENT_DIR = os.path.dirname(PLUGIN_DIR)

FREE_ZIP = os.path.join(PARENT_DIR, 'smart-cycle-discounts-free.zip')
PREMIUM_ZIP = os.path.join(PARENT_DIR, 'smart-cycle-discounts.zip')


def get_file_list(zip_path):
    """Get list of files in ZIP."""
    if not os.path.exists(zip_path):
        return set()

    with zipfile.ZipFile(zip_path, 'r') as z:
        return set(z.namelist())


def format_size(bytes_size):
    """Format bytes to human-readable size."""
    for unit in ['B', 'KB', 'MB', 'GB']:
        if bytes_size < 1024.0:
            return f"{bytes_size:.1f} {unit}"
        bytes_size /= 1024.0
    return f"{bytes_size:.1f} TB"


def main():
    print("=" * 80)
    print("Smart Cycle Discounts - Version Comparison")
    print("=" * 80)
    print("")

    # Check if builds exist
    if not os.path.exists(FREE_ZIP):
        print(f"❌ FREE version not found: {FREE_ZIP}")
        print("   Run: python3 build-free.py")
        return

    if not os.path.exists(PREMIUM_ZIP):
        print(f"⚠️  PREMIUM version not found: {PREMIUM_ZIP}")
        print("   Run: python3 build.py")
        print("")

    # Get file sizes
    free_size = os.path.getsize(FREE_ZIP) if os.path.exists(FREE_ZIP) else 0
    premium_size = os.path.getsize(PREMIUM_ZIP) if os.path.exists(PREMIUM_ZIP) else 0

    print("📦 Package Sizes:")
    print(f"   FREE version:    {format_size(free_size)}")
    if premium_size > 0:
        print(f"   PREMIUM version: {format_size(premium_size)}")
        savings = premium_size - free_size
        savings_pct = (savings / premium_size * 100) if premium_size > 0 else 0
        print(f"   Size reduction:  {format_size(savings)} ({savings_pct:.1f}% smaller)")
    print("")

    # Compare file lists
    free_files = get_file_list(FREE_ZIP)
    premium_files = get_file_list(PREMIUM_ZIP) if os.path.exists(PREMIUM_ZIP) else set()

    if premium_files:
        only_in_premium = premium_files - free_files
        only_in_free = free_files - premium_files

        print(f"📊 File Count:")
        print(f"   FREE version:    {len(free_files)} files")
        print(f"   PREMIUM version: {len(premium_files)} files")
        print(f"   Excluded:        {len(only_in_premium)} files")
        print("")

        if only_in_premium:
            print("🎯 PRO-Only Files (excluded from FREE):")
            print("")

            # Group by category
            analytics = [f for f in only_in_premium if 'analytics' in f.lower()]
            api = [f for f in only_in_premium if '/api/' in f]
            discounts = [f for f in only_in_premium if 'tiered' in f or 'bogo' in f or 'spend-threshold' in f]
            recurring = [f for f in only_in_premium if 'recurring' in f.lower()]
            export_import = [f for f in only_in_premium if 'export' in f or 'import' in f]
            other = [f for f in only_in_premium if f not in analytics + api + discounts + recurring + export_import]

            if analytics:
                print(f"   📊 Analytics ({len(analytics)} files):")
                for f in sorted(analytics)[:5]:
                    print(f"      - {f.replace('smart-cycle-discounts/', '')}")
                if len(analytics) > 5:
                    print(f"      ... and {len(analytics) - 5} more")
                print("")

            if api:
                print(f"   🔌 REST API ({len(api)} files):")
                for f in sorted(api)[:5]:
                    print(f"      - {f.replace('smart-cycle-discounts/', '')}")
                if len(api) > 5:
                    print(f"      ... and {len(api) - 5} more")
                print("")

            if discounts:
                print(f"   💰 Advanced Discounts ({len(discounts)} files):")
                for f in sorted(discounts):
                    print(f"      - {f.replace('smart-cycle-discounts/', '')}")
                print("")

            if recurring:
                print(f"   🔁 Recurring Campaigns ({len(recurring)} files):")
                for f in sorted(recurring):
                    print(f"      - {f.replace('smart-cycle-discounts/', '')}")
                print("")

            if export_import:
                print(f"   📤 Export/Import ({len(export_import)} files):")
                for f in sorted(export_import):
                    print(f"      - {f.replace('smart-cycle-discounts/', '')}")
                print("")

            if other:
                print(f"   🛠️  Other PRO Files ({len(other)} files):")
                for f in sorted(other)[:10]:
                    print(f"      - {f.replace('smart-cycle-discounts/', '')}")
                if len(other) > 10:
                    print(f"      ... and {len(other) - 10} more")
                print("")

        if only_in_free:
            print(f"⚠️  Files only in FREE (unexpected): {len(only_in_free)}")
            for f in sorted(only_in_free)[:5]:
                print(f"   - {f}")
            print("")

    # Compare Freemius configuration
    print("🔧 Freemius Configuration:")
    print("")

    with zipfile.ZipFile(FREE_ZIP, 'r') as z:
        with z.open('smart-cycle-discounts/smart-cycle-discounts.php') as f:
            free_config = f.read().decode('utf-8')

    if os.path.exists(PREMIUM_ZIP):
        with zipfile.ZipFile(PREMIUM_ZIP, 'r') as z:
            with z.open('smart-cycle-discounts/smart-cycle-discounts.php') as f:
                premium_config = f.read().decode('utf-8')

        import re

        def extract_config(content):
            config = {}
            config['is_premium'] = re.search(r"'is_premium'\s*=>\s*(\w+)", content)
            config['has_premium'] = re.search(r"'has_premium_version'\s*=>\s*(\w+)", content)
            config['has_paid'] = re.search(r"'has_paid_plans'\s*=>\s*(\w+)", content)
            config['suffix'] = re.search(r"'premium_suffix'\s*=>\s*'([^']+)'", content)
            return {k: v.group(1) if v else 'NOT FOUND' for k, v in config.items()}

        free_cfg = extract_config(free_config)
        premium_cfg = extract_config(premium_config)

        print("   Setting                 | FREE        | PREMIUM")
        print("   " + "-" * 59)
        print(f"   is_premium              | {free_cfg['is_premium']:<11} | {premium_cfg['is_premium']}")
        print(f"   has_premium_version     | {free_cfg['has_premium']:<11} | {premium_cfg['has_premium']}")
        print(f"   has_paid_plans          | {free_cfg['has_paid']:<11} | {premium_cfg['has_paid']}")
        print(f"   premium_suffix          | {free_cfg['suffix']:<11} | {premium_cfg['suffix']}")
        print("")

    print("✅ Version comparison complete!")
    print("")
    print("=" * 80)


if __name__ == '__main__':
    main()
