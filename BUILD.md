# Build Instructions

This document explains how to create a production-ready ZIP file for the Smart Cycle Discounts plugin.

## Quick Start

Run one of these commands from the plugin directory:

```bash
# Option 1: Python script (recommended)
python3 build.py

# Option 2: Bash wrapper
./build.sh

# Option 3: From any directory
python3 /path/to/smart-cycle-discounts/build.py
```

## Output

Creates: `smart-cycle-discounts.zip` in the parent directory

The ZIP file is ready for:
- ✅ WordPress.org plugin submission
- ✅ Manual upload to live sites
- ✅ Distribution to clients

## What Gets Included

**Included:**
- ✅ All PHP files (`includes/`, `templates/`, etc.)
- ✅ JavaScript and CSS assets (`resources/assets/`)
- ✅ Tom Select vendor library (`resources/assets/vendor/tom-select/`)
- ✅ Freemius SDK (`freemius/`)
- ✅ Main plugin file (`smart-cycle-discounts.php`)
- ✅ Uninstall script (`uninstall.php`)

**Excluded (Development Files):**
- ❌ `node_modules/` - NPM packages
- ❌ `vendor/` - Composer dependencies (root only)
- ❌ `tests/` - Test files
- ❌ `.git/` - Git repository
- ❌ `.github/` - GitHub workflows
- ❌ `.claude/` - Claude Code configuration
- ❌ Build tools (`webpack.config.js`, `composer.json`, etc.)
- ❌ Documentation (`README.md`, `CHANGELOG.md`, `CLAUDE.md`)
- ❌ IDE configs (`.vscode/`, `.idea/`)

## Requirements

- Python 3.6 or higher
- No additional Python packages required (uses standard library)

## Build Statistics

Typical output:
```
Files included: ~612
Total size: ~9 MB (uncompressed)
Compressed size: ~2.3 MB
Compression: ~75%
```

## Troubleshooting

**"python3: command not found"**
- Windows: Install Python from python.org
- Mac: Python 3 should be pre-installed
- Linux: `sudo apt install python3`

**"Permission denied"**
```bash
chmod +x build.py build.sh
```

**ZIP file too large**
- Check for accidentally included `node_modules/` or `vendor/`
- Verify exclusion rules in `build.py`

## Manual ZIP Creation

If you can't run the scripts, manually ZIP these directories/files:
```
smart-cycle-discounts/
├── freemius/
├── includes/
├── resources/
├── templates/
├── smart-cycle-discounts.php
└── uninstall.php
```

**Important:** The ZIP must contain a `smart-cycle-discounts/` folder, not loose files.

## Verification

After building, verify the ZIP:
```bash
# List contents
unzip -l smart-cycle-discounts.zip | less

# Check size
ls -lh smart-cycle-discounts.zip

# Extract and test
unzip smart-cycle-discounts.zip -d test-extract/
```

## Automation

Add to your deployment workflow:
```bash
# Build and upload in one step
python3 build.py && scp smart-cycle-discounts.zip user@server:/tmp/
```

## Support

If the build script fails, check:
1. You're in the plugin directory
2. Python 3 is installed: `python3 --version`
3. Main plugin file exists: `smart-cycle-discounts.php`
4. No permission issues: `ls -la build.py`
