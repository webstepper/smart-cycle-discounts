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
import shutil
import zipfile
import re
from pathlib import Path
from datetime import datetime


# Plugin configuration
PLUGIN_SLUG = 'smart-cycle-discounts'
BUILD_DIR = 'build'
# Output ZIP to parent plugins directory (C:\Users\Alienware\Local Sites\vvmdov\app\public\wp-content\plugins)
DIST_DIR = None  # Will be set to parent directory at runtime

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


def copy_plugin_files(src_dir, dest_dir):
    """Copy plugin files to build directory, excluding unwanted files."""
    copied_files = 0
    excluded_files = 0

    for root, dirs, files in os.walk(src_dir):
        # Filter out excluded directories
        dirs[:] = [d for d in dirs if not should_exclude(os.path.join(root, d), src_dir)]

        # Calculate relative path
        rel_root = os.path.relpath(root, src_dir)

        # Skip if this directory itself should be excluded
        if rel_root != '.' and should_exclude(root, src_dir):
            continue

        # Create destination directory
        if rel_root == '.':
            dest_root = dest_dir
        else:
            dest_root = os.path.join(dest_dir, rel_root)

        os.makedirs(dest_root, exist_ok=True)

        # Copy files
        for file in files:
            src_file = os.path.join(root, file)

            if should_exclude(src_file, src_dir):
                excluded_files += 1
                continue

            dest_file = os.path.join(dest_root, file)
            shutil.copy2(src_file, dest_file)
            copied_files += 1

    return copied_files, excluded_files


def create_zip(source_dir, output_file):
    """Create ZIP archive of the plugin."""
    with zipfile.ZipFile(output_file, 'w', zipfile.ZIP_DEFLATED) as zipf:
        for root, dirs, files in os.walk(source_dir):
            for file in files:
                file_path = os.path.join(root, file)
                arcname = os.path.relpath(file_path, os.path.dirname(source_dir))
                zipf.write(file_path, arcname)

    return os.path.getsize(output_file)


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

    # Clean up old build directory (but NOT dist_dir - it's the parent plugins folder!)
    print("Cleaning up old build files...")
    if os.path.exists(BUILD_DIR):
        shutil.rmtree(BUILD_DIR)

    # Create directories
    build_plugin_dir = os.path.join(BUILD_DIR, PLUGIN_SLUG)
    os.makedirs(build_plugin_dir, exist_ok=True)
    # Parent directory already exists (it's the plugins folder)

    # Copy plugin files
    print("Copying plugin files...")
    copied, excluded = copy_plugin_files('.', build_plugin_dir)
    print(f"  Copied: {copied} files")
    print(f"  Excluded: {excluded} files/directories")
    print()

    # Create ZIP file
    timestamp = datetime.now().strftime('%Y%m%d-%H%M%S')
    zip_filename = f'{PLUGIN_SLUG}-{version}.zip'
    zip_path = os.path.join(dist_dir, zip_filename)

    print(f"Creating ZIP archive: {zip_filename}")
    zip_size = create_zip(build_plugin_dir, zip_path)
    print(f"  Size: {format_size(zip_size)}")
    print()

    # Clean up build directory (keep dist)
    print("Cleaning up temporary files...")
    shutil.rmtree(BUILD_DIR)

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
