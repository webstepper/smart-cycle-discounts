#!/usr/bin/env python3
"""
===============================================================================
                    DO NOT DELETE THIS FILE
===============================================================================
This build.py script is ESSENTIAL for creating WordPress-installable releases.
It generates clean ZIP files containing only production files.

DO NOT REMOVE - Required for plugin distribution and WordPress.org submission.
===============================================================================

Build script for Smart Cycle Discounts WordPress plugin.

Creates a clean, WordPress-installable ZIP file by including only the
directories and files needed for production. Any file not explicitly
listed here will NOT be in the build.

Usage: python3 build.py

Output: Creates ZIP file in parent directory (../smart-cycle-discounts-X.X.X.zip)
"""

import os
import zipfile
import re


# Plugin configuration
PLUGIN_SLUG = 'smart-cycle-discounts'

# ---------------------------------------------------------------------------
# Production file manifest (inclusion-based)
# ---------------------------------------------------------------------------
# Only these directories and root files will be in the build.
# Everything else is automatically excluded.
#
# DIRECTORIES THAT EXIST BUT ARE EXCLUDED (dev/build only):
# - .git/                 Version control
# - .github/              GitHub Actions workflows
# - .claude/              Claude Code settings
# - .wordpress-org/       SVN assets for WordPress.org (banners, icons)
# - assets/               Old/empty asset structure (placeholder index.php only)
# - banner-export/        Banner export tool
# - bin/                  Development scripts
# - frames/               Animation frames (temp files)
# - resources/assets/scss/ SCSS source files (not compiled CSS)
# - tests/                PHPUnit tests
# - vendor/               Dev dependencies (except freemius/)
# - Webstepper.io/        Website content
#
# ROOT FILES THAT ARE EXCLUDED (dev/docs only):
# - *.md (ARCHITECTURE.md, CLAUDE.md, etc.)
# - *.py (build.py, prefix-migration.py)
# - *.sh (deploy-to-wporg.sh)
# - *.json (composer.json, package.json, elementor-*.json)
# - *.xml (phpunit*.xml)
# - *.phar (composer.phar)
# - *.png, *.gif, *.svg, *.html (dev assets at root)
# - *.po at root (translation files should be in languages/)
# - .gitignore
# ---------------------------------------------------------------------------

# Root-level files to include
INCLUDE_ROOT_FILES = [
    'smart-cycle-discounts.php',  # Main plugin file
    'index.php',                  # Security index
    'readme.txt',                 # Required by WordPress.org
    'LICENSE',                    # GPL-3.0 license
]

# Directories to include recursively (all contents included)
INCLUDE_DIRS = [
    'includes',                   # PHP classes and logic (~308 files)
    'resources/assets/css',       # Compiled stylesheets
    'resources/assets/js',        # JavaScript files (~102 files)
    'resources/assets/vendor',    # Bundled libs (Chart.js, Tom Select)
    'resources/assets/images',    # Plugin images
    'resources/views',            # PHP view templates
    'templates',                  # Email templates
    'languages',                  # Translation files (.po, .pot)
    'vendor/freemius',            # Freemius SDK (loaded via vendor/freemius/start.php)
]

# Files to skip within included directories
# These patterns catch dev files that might accidentally end up in production dirs
EXCLUDE_FILES_WITHIN = [
    '*.sh',       # Shell scripts
    '*.md',       # Markdown documentation
    '*.map',      # Source maps
    '*.scss',     # SCSS source files
    '*.sass',     # SASS source files
    '*.ts',       # TypeScript source files
    '*.log',      # Log files
    '.gitkeep',   # Git placeholder files
    '.DS_Store',  # macOS metadata
    'Thumbs.db',  # Windows metadata
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


def matches_pattern(filename, pattern):
    """Check if a filename matches a wildcard pattern."""
    import fnmatch
    return fnmatch.fnmatch(filename, pattern)


def should_skip_file(filepath):
    """Check if a file within an included directory should be skipped."""
    name = os.path.basename(filepath)
    for pattern in EXCLUDE_FILES_WITHIN:
        if matches_pattern(name, pattern):
            return True
    return False


def create_zip(source_dir, output_file, plugin_slug):
    """Create ZIP archive containing only production files."""
    included_files = 0
    skipped_files = 0

    with zipfile.ZipFile(output_file, 'w', zipfile.ZIP_DEFLATED) as zipf:
        # 1. Add root-level files
        for filename in INCLUDE_ROOT_FILES:
            filepath = os.path.join(source_dir, filename)
            if os.path.isfile(filepath):
                zipf.write(filepath, f'{plugin_slug}/{filename}')
                included_files += 1
            else:
                print(f"  Warning: root file not found: {filename}")

        # 2. Add included directories recursively
        for inc_dir in INCLUDE_DIRS:
            dir_path = os.path.join(source_dir, inc_dir)
            if not os.path.isdir(dir_path):
                continue

            for root, dirs, files in os.walk(dir_path):
                for filename in files:
                    filepath = os.path.join(root, filename)
                    rel_path = os.path.relpath(filepath, source_dir)

                    if should_skip_file(filepath):
                        skipped_files += 1
                        continue

                    arcname = f'{plugin_slug}/{rel_path}'
                    zipf.write(filepath, arcname)
                    included_files += 1

    return os.path.getsize(output_file), included_files, skipped_files


def format_size(size_bytes):
    """Format file size in human-readable format."""
    for unit in ['B', 'KB', 'MB', 'GB']:
        if size_bytes < 1024.0:
            return f"{size_bytes:.2f} {unit}"
        size_bytes /= 1024.0
    return f"{size_bytes:.2f} TB"


def get_excluded_directories(source_dir):
    """
    Find directories that exist but are not included in the build.
    Returns a list of excluded directory names for transparency.
    """
    excluded = []

    # Get all top-level directories
    for item in os.listdir(source_dir):
        item_path = os.path.join(source_dir, item)
        if not os.path.isdir(item_path):
            continue

        # Skip hidden directories
        if item.startswith('.'):
            excluded.append(f".{item[1:]}/")
            continue

        # Check if this directory (or any subdirectory) is in INCLUDE_DIRS
        is_included = False
        for inc_dir in INCLUDE_DIRS:
            if inc_dir == item or inc_dir.startswith(f"{item}/"):
                is_included = True
                break

        if not is_included:
            excluded.append(f"{item}/")

    return sorted(excluded)


def get_excluded_root_files(source_dir):
    """
    Find root-level files that exist but are not included in the build.
    Returns a list of excluded file names for transparency.
    """
    excluded = []

    for item in os.listdir(source_dir):
        item_path = os.path.join(source_dir, item)
        if not os.path.isfile(item_path):
            continue

        # Skip hidden files
        if item.startswith('.'):
            excluded.append(item)
            continue

        if item not in INCLUDE_ROOT_FILES:
            excluded.append(item)

    return sorted(excluded)


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

    # Show what will be excluded (for transparency)
    print("Directories EXCLUDED from build:")
    excluded_dirs = get_excluded_directories('.')
    for d in excluded_dirs:
        print(f"  - {d}")
    print()

    print("Root files EXCLUDED from build:")
    excluded_files = get_excluded_root_files('.')
    # Group by extension for cleaner output
    if len(excluded_files) > 10:
        by_ext = {}
        for f in excluded_files:
            ext = os.path.splitext(f)[1] or '(no ext)'
            by_ext.setdefault(ext, []).append(f)
        for ext, files in sorted(by_ext.items()):
            print(f"  - {ext}: {len(files)} files")
    else:
        for f in excluded_files:
            print(f"  - {f}")
    print()

    # Create ZIP file
    zip_filename = f'{PLUGIN_SLUG}-{version}.zip'
    zip_path = os.path.join(dist_dir, zip_filename)

    print(f"Creating ZIP archive: {zip_filename}")
    zip_size, included, skipped = create_zip('.', zip_path, PLUGIN_SLUG)
    print(f"  Included:  {included} files")
    print(f"  Skipped:   {skipped} files (matched exclusion patterns)")
    print(f"  Size:      {format_size(zip_size)}")
    print()

    # Success message
    print("=" * 60)
    print("Build completed successfully!")
    print("=" * 60)
    print(f"WordPress-installable ZIP: {zip_path}")
    print()
    print("Installation instructions:")
    print("1. Go to WordPress Admin > Plugins > Add New")
    print("2. Click 'Upload Plugin'")
    print(f"3. Choose {zip_filename}")
    print("4. Click 'Install Now'")
    print("5. Activate the plugin")
    print()


if __name__ == '__main__':
    try:
        main()
    except Exception as e:
        print(f"\nBuild failed: {str(e)}")
        import traceback
        traceback.print_exc()
        exit(1)
