#!/usr/bin/env python3
"""
Smart Cycle Discounts - Production Build Script

Creates a clean production ZIP file ready for deployment to WordPress.org or live sites.
Excludes development files, tests, and build tools.

Usage:
    python3 build.py

Output:
    Creates smart-cycle-discounts.zip in parent directory
"""

import zipfile
import os
import sys
from datetime import datetime

# Configuration
PLUGIN_DIR = os.path.dirname(os.path.abspath(__file__))
PARENT_DIR = os.path.dirname(PLUGIN_DIR)
OUTPUT_ZIP = os.path.join(PARENT_DIR, 'smart-cycle-discounts.zip')

# Directories to exclude (development/build files)
EXCLUDE_DIRS = {
    'node_modules',
    'tests',
    'test',
    '.git',
    '.github',
    '.claude',
    '__pycache__',
    '.vscode',
    '.idea'
    # NOTE: 'vendor' is NOT in this list - see line 90 for root-only vendor exclusion
}

# File patterns to exclude
EXCLUDE_PATTERNS = {
    '.DS_Store',
    'Thumbs.db',
    '.gitignore',
    '.gitattributes',
    'package.json',
    'package-lock.json',
    'composer.json',
    'composer.lock',
    'webpack.config.js',
    'phpcs.xml',
    'phpunit.xml',
    'build.py',  # Don't include this script in the ZIP
    '.bak'  # Exclude backup files
}

# Root-level files to exclude
EXCLUDE_ROOT_FILES = {
    'README.md',
    'CHANGELOG.md',
    'LICENSE',
    'screenshot-1.png',
    'screenshot-2.png',
    'screenshot-3.png',
    'screenshot-4.png',
    'CLAUDE.md'
}


def should_exclude(file_path, root_dir):
    """
    Determine if a file should be excluded from the production ZIP.

    Args:
        file_path: Absolute path to the file
        root_dir: Plugin root directory

    Returns:
        bool: True if file should be excluded
    """
    rel_path = os.path.relpath(file_path, root_dir)
    parts = rel_path.split(os.sep)

    # Exclude specified directories
    if any(excluded in parts for excluded in EXCLUDE_DIRS):
        return True

    # Exclude file patterns
    if any(pattern in os.path.basename(file_path) for pattern in EXCLUDE_PATTERNS):
        return True

    # Exclude root-level vendor directory (Composer), EXCEPT vendor/freemius/ (Freemius SDK)
    # Keep resources/assets/vendor (frontend libs)
    if 'vendor' in parts and parts[0] == 'vendor':
        # Allow vendor/freemius/ and its contents
        if len(parts) > 1 and parts[1] == 'freemius':
            return False
        # Exclude all other vendor/ content
        return True

    # Exclude root-level specific files
    if len(parts) == 1 and os.path.basename(file_path) in EXCLUDE_ROOT_FILES:
        return True

    # Exclude broken symlinks
    if os.path.islink(file_path) and not os.path.exists(file_path):
        return True

    return False


def create_production_zip():
    """
    Create production ZIP file with clean plugin code.
    """
    print("=" * 70)
    print("Smart Cycle Discounts - Production Build")
    print("=" * 70)
    print("")

    # Verify plugin directory exists
    if not os.path.isdir(PLUGIN_DIR):
        print(f"❌ Error: Plugin directory not found: {PLUGIN_DIR}")
        sys.exit(1)

    # Check if main plugin file exists
    main_file = os.path.join(PLUGIN_DIR, 'smart-cycle-discounts.php')
    if not os.path.isfile(main_file):
        print(f"❌ Error: Main plugin file not found: {main_file}")
        sys.exit(1)

    print(f"📂 Source: {PLUGIN_DIR}")
    print(f"📦 Output: {OUTPUT_ZIP}")
    print("")
    print("🔨 Building production package...")
    print("")

    files_added = 0
    total_size = 0
    excluded_count = 0

    try:
        with zipfile.ZipFile(OUTPUT_ZIP, 'w', zipfile.ZIP_DEFLATED) as zipf:
            for root, dirs, files in os.walk(PLUGIN_DIR):
                # Remove excluded directories from walk
                original_dirs = dirs[:]
                dirs[:] = [d for d in dirs if d not in EXCLUDE_DIRS]
                excluded_count += len(original_dirs) - len(dirs)

                for file in files:
                    file_path = os.path.join(root, file)

                    # Check if file should be excluded
                    if should_exclude(file_path, PLUGIN_DIR):
                        excluded_count += 1
                        continue

                    # Skip if file doesn't exist (broken symlink)
                    if not os.path.exists(file_path):
                        excluded_count += 1
                        continue

                    # Create archive path (includes plugin directory name)
                    arcname = os.path.join('smart-cycle-discounts', os.path.relpath(file_path, PLUGIN_DIR))

                    # Add to ZIP
                    zipf.write(file_path, arcname)
                    files_added += 1
                    total_size += os.path.getsize(file_path)

        # Get final ZIP size
        zip_size = os.path.getsize(OUTPUT_ZIP)
        compression_ratio = (1 - zip_size / total_size) * 100 if total_size > 0 else 0

        # Success output
        print("✅ Production package created successfully!")
        print("")
        print("📊 Build Statistics:")
        print(f"   Files included: {files_added}")
        print(f"   Files/dirs excluded: {excluded_count}")
        print(f"   Total size: {total_size / (1024 * 1024):.2f} MB")
        print(f"   Compressed size: {zip_size / (1024 * 1024):.2f} MB")
        print(f"   Compression: {compression_ratio:.1f}%")
        print("")
        print("📦 Package Details:")
        print(f"   Location: {OUTPUT_ZIP}")
        print(f"   Ready for deployment to WordPress.org or live sites")
        print("")
        print("=" * 70)

    except Exception as e:
        print(f"❌ Error creating ZIP file: {e}")
        sys.exit(1)


if __name__ == '__main__':
    create_production_zip()
