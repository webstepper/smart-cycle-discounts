#!/usr/bin/env python3
"""
===============================================================================
                    ⚠️  DO NOT DELETE THIS FILE  ⚠️
===============================================================================
This build.py script is ESSENTIAL for creating WordPress-installable releases.
It generates clean ZIP files excluding development files for production use.

DO NOT REMOVE - Required for plugin distribution and WordPress.org submission.
===============================================================================

Build script for Smart Cycle Discounts WordPress plugin.

Creates a clean, WordPress-installable ZIP file by:
- Copying all necessary plugin files
- Excluding development and build files
- Generating a properly named ZIP archive

Usage: python3 build.py
"""

import os
import zipfile
import re
from datetime import datetime


# Plugin configuration
PLUGIN_SLUG = 'smart-cycle-discounts'
# Output ZIP to parent plugins directory (C:\Users\Alienware\Local Sites\vvmdov\app\public\wp-content\plugins)

# Files and directories to exclude from the ZIP
EXCLUDE_PATTERNS = [
    # Version control
    '.git',
    '.gitignore',
    '.gitattributes',

    # Build and development
    'build.py',
    'update-file-headers.py',
    'build',
    'dist',
    'node_modules',
    '.claude',

    # Documentation (not needed in production)
    '*.md',
    'BUILD.md',
    'CLAUDE.md',
    'DASHBOARD-REFACTORING-PLAN.md',
    'JAVASCRIPT-COMPATIBILITY.md',
    'PLUGIN-STRUCTURE.md',
    'SECURITY-AUDIT-REPORT.md',
    'SECURITY-FIXES-REQUIRED.md',
    'TESTING-GUIDE-CATEGORY-SYSTEM.md',
    'WEEKLY-TIMELINE-IMPLEMENTATION-PLAN.md',
    'FINAL-VERIFICATION-REPORT-*.md',
    'plugin_structure_summary.txt',
    'docs',

    # Composer development files
    'composer.json',
    'composer.lock',
    'composer.phar',
    'vendor',

    # Tests
    'tests',
    'phpunit.xml',
    'phpunit.xml.dist',
    '.phpunit.result.cache',

    # IDE and editor files
    '.vscode',
    '.idea',
    '*.sublime-project',
    '*.sublime-workspace',
    '.DS_Store',
    'Thumbs.db',

    # Logs and temporary files
    '*.log',
    'debug.log',
    'error_log',
    '*.tmp',
    '*.bak',
    '*.swp',
    '*~',

    # Build artifacts
    '*.zip',
]

# Keep these specific files even if they match exclude patterns
FORCE_INCLUDE = [
    'readme.txt',  # Required by WordPress.org
]


def get_plugin_version():
    """Extract plugin version from main plugin file."""
    main_file = f'{PLUGIN_SLUG}.php'
    if not os.path.exists(main_file):
        print(f"Warning: Main plugin file {main_file} not found")
        return '1.0.0'

    with open(main_file, 'r', encoding='utf-8') as f:
        content = f.read()
        match = re.search(r'\*\s*Version:\s*([0-9.]+)', content)
        if match:
            return match.group(1)

    return '1.0.0'


def should_exclude(path, base_path=''):
    """Check if a file or directory should be excluded."""
    # Convert to relative path for checking
    rel_path = os.path.relpath(path, base_path) if base_path else path
    name = os.path.basename(path)

    # Check force include list
    if name in FORCE_INCLUDE or rel_path in FORCE_INCLUDE:
        return False

    # Check exclude patterns
    for pattern in EXCLUDE_PATTERNS:
        # Exact match
        if name == pattern or rel_path == pattern:
            return True

        # Directory match
        if pattern in rel_path.split(os.sep):
            return True

        # Wildcard pattern match
        if '*' in pattern:
            import fnmatch
            if fnmatch.fnmatch(name, pattern) or fnmatch.fnmatch(rel_path, pattern):
                return True

    return False


def create_zip(source_dir, output_file, plugin_slug):
    """Create ZIP archive directly from source directory, excluding unwanted files."""
    included_files = 0
    excluded_files = 0

    with zipfile.ZipFile(output_file, 'w', zipfile.ZIP_DEFLATED) as zipf:
        for root, dirs, files in os.walk(source_dir):
            # Filter out excluded directories (modifies dirs in-place to prevent walking into them)
            dirs[:] = [d for d in dirs if not should_exclude(os.path.join(root, d), source_dir)]

            # Calculate relative path
            rel_root = os.path.relpath(root, source_dir)

            # Skip if this directory itself should be excluded
            if rel_root != '.' and should_exclude(root, source_dir):
                continue

            # Process files
            for file in files:
                file_path = os.path.join(root, file)

                # Skip excluded files
                if should_exclude(file_path, source_dir):
                    excluded_files += 1
                    continue

                # Calculate archive name with plugin slug as root
                if rel_root == '.':
                    arcname = f'{plugin_slug}/{file}'
                else:
                    arcname = f'{plugin_slug}/{rel_root}/{file}'

                # Add to ZIP
                zipf.write(file_path, arcname)
                included_files += 1

    return os.path.getsize(output_file), included_files, excluded_files


def format_size(size_bytes):
    """Format file size in human-readable format."""
    for unit in ['B', 'KB', 'MB', 'GB']:
        if size_bytes < 1024.0:
            return f"{size_bytes:.2f} {unit}"
        size_bytes /= 1024.0
    return f"{size_bytes:.2f} TB"


def main():
    """Main build process."""
    print("=" * 60)
    print("Smart Cycle Discounts - WordPress Plugin Builder")
    print("=" * 60)
    print()

    # Get current directory
    script_dir = os.path.dirname(os.path.abspath(__file__))
    os.chdir(script_dir)

    # Set output directory to parent plugins directory
    dist_dir = os.path.abspath(os.path.join(script_dir, '..'))

    # Get plugin version
    version = get_plugin_version()
    print(f"Plugin: {PLUGIN_SLUG}")
    print(f"Version: {version}")
    print(f"Output directory: {dist_dir}")
    print()

    # Create ZIP file directly from current directory
    zip_filename = f'{PLUGIN_SLUG}-{version}.zip'
    zip_path = os.path.join(dist_dir, zip_filename)

    print(f"Creating ZIP archive: {zip_filename}")
    zip_size, included, excluded = create_zip('.', zip_path, PLUGIN_SLUG)
    print(f"  Included: {included} files")
    print(f"  Excluded: {excluded} files/directories")
    print(f"  Size: {format_size(zip_size)}")
    print()

    # Success message
    print("=" * 60)
    print("✓ Build completed successfully!")
    print("=" * 60)
    print(f"WordPress-installable ZIP: {zip_path}")
    print()
    print("Installation instructions:")
    print("1. Go to WordPress Admin → Plugins → Add New")
    print("2. Click 'Upload Plugin'")
    print(f"3. Choose {zip_filename}")
    print("4. Click 'Install Now'")
    print("5. Activate the plugin")
    print()


if __name__ == '__main__':
    try:
        main()
    except Exception as e:
        print(f"\n❌ Build failed: {str(e)}")
        import traceback
        traceback.print_exc()
        exit(1)
