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

    # WordPress.org assets (uploaded separately to SVN, not in plugin ZIP)
    '.wordpress-org',

    # CI/CD and development infrastructure
    '.github',
    'bin',

    # Build and development scripts
    '*.py',  # All Python scripts (build.py, update-file-headers.py, css-variable-replacer.py)
    '*.sh',  # All shell scripts (install-wp-tests.sh, verify-naming-conventions.sh)
    'build',
    'dist',
    'node_modules',
    '.claude',

    # Development PHP files (not part of plugin runtime)
    'abstract-testcase.php',
    'check-jquery-ui-css.php',
    'listener-loader.php',
    'speed-trap-listener.php',
    'test-*.php',  # Test PHP files (test-conditions-integration.js, test-recurring-integration.php, etc.)
    'test-*.js',   # Test JavaScript files
    'C:\\tmp\\*',  # Broken Windows path files/directories

    # Documentation (not needed in production)
    # Note: *.md also excludes vendor/freemius/README.md (intentional - SDK docs not needed)
    '*.md',
    '*.txt',  # All text files except readme.txt (kept via FORCE_INCLUDE)
    'docs',

    # Composer development files
    # Note: composer.json also excludes vendor/freemius/composer.json (intentional - dev dependency info)
    'composer.json',
    'composer.lock',
    'composer.phar',

    # Source assets (exclude SCSS source files, keep compiled CSS/JS)
    'resources/assets/scss',  # Exclude SCSS source files - keep compiled CSS in resources/assets/css/
    # Note: Root 'assets/' directory excluded via should_exclude() root-level check only
    # Do NOT add 'assets' here - it would match 'resources/assets/' due to path component matching
    # ✅ INCLUDED: resources/assets/vendor/ (bundled Chart.js and Tom Select for WordPress.org compliance)

    # Vendor dependencies (exclude all except Freemius SDK)
    # IMPORTANT: Plugin uses vendor/freemius/start.php directly, not Composer autoloader
    'vendor/autoload.php',  # Not used in production (only in tests)
    'vendor/bin',
    'vendor/composer',
    'vendor/dealerdirect',
    'vendor/doctrine',
    'vendor/myclabs',
    'vendor/nikic',
    'vendor/phar-io',
    'vendor/phpcsstandards',
    'vendor/phpunit',
    'vendor/sebastian',
    'vendor/squizlabs',
    'vendor/theseer',
    'vendor/wp-coding-standards',
    'vendor/yoast',
    # ✅ INCLUDED: vendor/freemius/ (production Freemius SDK loaded via vendor/freemius/start.php)

    # Tests
    'tests',
    'phpunit.xml',
    'phpunit.xml.dist',
    'phpunit-*.xml',  # Additional phpunit config files (phpunit-lightweight.xml, phpunit-standalone.xml)
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

    # Screenshots and images (development/documentation only)
    'Screenshot*',
    'screenshot*',
    '*.png',
    '*.jpg',
    '*.jpeg',
    '*.gif',
    '*.svg',
    '*.ico',

    # HTML files (development/test files, not part of plugin)
    '*.html',

    # JSON files (development/config files, not part of plugin)
    '*.json',

    # CSS files in root (development artifacts)
    'freemius-portal.css',

    # Build artifacts
    '*.zip',
]

# Keep these specific files even if they match exclude patterns
FORCE_INCLUDE = [
    'readme.txt',  # Required by WordPress.org
    'LICENSE',     # GPL-3.0 license file (good practice to include)
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
    import fnmatch

    # Convert to relative path for checking
    rel_path = os.path.relpath(path, base_path) if base_path else path
    name = os.path.basename(path)

    # Normalize path separators for consistent matching
    rel_path_normalized = rel_path.replace('\\', '/')

    # Check force include list
    if name in FORCE_INCLUDE or rel_path in FORCE_INCLUDE:
        return False

    # Special case: exclude root-level 'assets/' directory (but NOT 'resources/assets/')
    if rel_path_normalized == 'assets' or rel_path_normalized.startswith('assets/'):
        return True

    # Check exclude patterns
    for pattern in EXCLUDE_PATTERNS:
        # Normalize pattern separators
        pattern_normalized = pattern.replace('\\', '/')

        # Exact match on filename or full relative path
        if name == pattern or rel_path_normalized == pattern_normalized:
            return True

        # Directory path prefix match - pattern must match from start of path
        # e.g., "resources/assets/scss" excludes "resources/assets/scss/file.scss"
        # but "assets" does NOT exclude "resources/assets/css/file.css"
        if '/' in pattern_normalized:
            if rel_path_normalized.startswith(pattern_normalized + '/'):
                return True

        # Wildcard pattern match
        if '*' in pattern:
            if fnmatch.fnmatch(name, pattern) or fnmatch.fnmatch(rel_path_normalized, pattern_normalized):
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
