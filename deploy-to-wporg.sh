#!/bin/bash
#
# Deploy Smart Cycle Discounts to WordPress.org SVN
#
# Usage: ./deploy-to-wporg.sh [version]
#        ./deploy-to-wporg.sh /path/to/smart-cycle-discounts-free.x.x.x.zip
#
# Examples:
#   ./deploy-to-wporg.sh 1.1.6          # Uses default SCD-FREE folder
#   ./deploy-to-wporg.sh ~/Downloads/smart-cycle-discounts-free-1.1.6.zip
#
# This script:
# 1. Extracts the free version zip
# 2. Copies files to SVN trunk
# 3. Handles new/deleted files
# 4. Creates version tag
# 5. Commits to WordPress.org
#

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuration
SVN_DIR="$HOME/svn-deploy/smart-cycle-discounts"
PLUGIN_SLUG="smart-cycle-discounts"
TEMP_DIR="/tmp/${PLUGIN_SLUG}-deploy"
# Default folder for Freemius free version downloads
SCD_FREE_DIR="/mnt/c/Users/Alienware/Local Sites/vvmdov/app/public/wp-content/plugins/SCD-FREE"

# Function to print colored output
print_status() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if argument provided
if [ -z "$1" ]; then
    print_error "Usage: $0 <version> or $0 /path/to/zip"
    print_error "Examples:"
    print_error "  $0 1.1.6"
    print_error "  $0 ~/Downloads/smart-cycle-discounts-free-1.1.6.zip"
    exit 1
fi

# Determine if argument is version number or path
if [[ "$1" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
    # Argument is a version number - use default SCD-FREE folder
    VERSION="$1"
    ZIP_FILE="${SCD_FREE_DIR}/smart-cycle-discounts-free.${VERSION}.zip"
    print_status "Using default SCD-FREE folder..."
else
    # Argument is a path
    ZIP_FILE="$1"
    # Extract version from zip filename
    VERSION=$(echo "$ZIP_FILE" | grep -oP '\d+\.\d+\.\d+' | tail -1)
    if [ -z "$VERSION" ]; then
        print_warning "Could not extract version from filename."
        read -p "Enter version number (e.g., 1.0.2): " VERSION
    fi
fi

# Verify zip file exists
if [ ! -f "$ZIP_FILE" ]; then
    print_error "Zip file not found: $ZIP_FILE"
    print_error "Make sure you downloaded the free version from Freemius Dashboard."
    exit 1
fi

print_status "Deploying version $VERSION to WordPress.org..."

# Clean up temp directory
rm -rf "$TEMP_DIR"
mkdir -p "$TEMP_DIR"

# Extract zip using Python (more portable)
print_status "Extracting zip file..."
python3 -c "import zipfile; z = zipfile.ZipFile('$ZIP_FILE'); z.extractall('$TEMP_DIR')"

# Navigate to SVN directory
print_status "Updating SVN repository..."
cd "$SVN_DIR"
svn update

# Copy files to trunk
print_status "Copying files to trunk..."
cp -r "$TEMP_DIR/$PLUGIN_SLUG/"* trunk/

# Handle new files (? status)
print_status "Adding new files..."
svn status trunk/ | grep '^?' | awk '{print $2}' | xargs -I{} svn add {} 2>/dev/null || true

# Handle deleted files (! status)
print_status "Removing deleted files..."
svn status trunk/ | grep '^!' | awk '{print $2}' | xargs -I{} svn rm {} 2>/dev/null || true

# Show status
print_status "Changes to be committed:"
svn status

# Confirm before proceeding
echo ""
read -p "Create tag $VERSION and commit? (y/n): " CONFIRM
if [ "$CONFIRM" != "y" ] && [ "$CONFIRM" != "Y" ]; then
    print_warning "Deployment cancelled."
    exit 0
fi

# Check if tag already exists
if [ -d "tags/$VERSION" ]; then
    print_warning "Tag $VERSION already exists. Updating existing tag..."
    svn rm "tags/$VERSION" -m "Removing old tag $VERSION for update"
    svn update
fi

# Create version tag
print_status "Creating tag $VERSION..."
svn cp trunk "tags/$VERSION"

# Commit
print_status "Committing to WordPress.org..."
svn commit -m "Version $VERSION"

# Clean up
rm -rf "$TEMP_DIR"

print_status "========================================="
print_status "Successfully deployed version $VERSION!"
print_status "========================================="
print_status ""
print_status "View your plugin at:"
print_status "https://wordpress.org/plugins/$PLUGIN_SLUG/"
