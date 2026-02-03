#!/bin/bash
#
# Deploy Smart Cycle Discounts to WordPress.org SVN
#
# Usage: ./deploy-to-wporg.sh local <version>     # Full plugin from repo, exclude AI only
#        ./deploy-to-wporg.sh <version>           # From Freemius free zip in SCD-FREE
#        ./deploy-to-wporg.sh /path/to/zip        # From custom zip
#
# Examples:
#   ./deploy-to-wporg.sh local 1.5.2             # Deploy full version (this repo), exclude only AI
#   ./deploy-to-wporg.sh 1.5.2                   # Use zip from SCD-FREE folder
#   ./deploy-to-wporg.sh ~/Downloads/smart-cycle-discounts-free-1.5.2.zip
#
# WordPress.org package = source (full or zip) with Cycle AI files removed.
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
# Script dir = plugin root when run from repo
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
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
    print_error "Usage: $0 local <version>  |  $0 <version>  |  $0 /path/to/zip"
    print_error "  local <version>  = full plugin from this repo, exclude only AI"
    print_error "  <version>        = zip from SCD-FREE folder (e.g. 1.5.2)"
    print_error "  /path/to/zip     = custom zip path"
    exit 1
fi

USE_LOCAL=false
if [ "$1" = "local" ]; then
    USE_LOCAL=true
    shift
    if [ -z "$1" ]; then
        print_error "Usage: $0 local <version>"
        exit 1
    fi
    VERSION="$1"
else
    VERSION="$1"
fi

# Navigate to SVN directory
print_status "Updating SVN repository..."
cd "$SVN_DIR"
svn update

if [ "$USE_LOCAL" = true ]; then
    # Deploy full version from repo (script's directory = plugin root), exclude only AI
    print_status "Deploying full version from repo (excluding AI only)..."
    print_status "Source: $SCRIPT_DIR"
    rsync -a --exclude='.git' --exclude='.github' --exclude='node_modules' --exclude='.cursor' --exclude='*.md' --exclude='*.sh' --exclude='*.py' --exclude='phpunit*.xml' --exclude='composer.*' --exclude='.wordpress-org' --exclude='tests' --exclude='bin' --exclude='Webstepper.io' \
        "$SCRIPT_DIR/" trunk/
    # Remove vendor except Freemius
    find trunk/vendor -mindepth 1 -maxdepth 1 ! -name 'freemius' -exec rm -rf {} + 2>/dev/null || true
else
    # Determine if argument is version number or path
    if [[ "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
        ZIP_FILE="${SCD_FREE_DIR}/smart-cycle-discounts-free.${VERSION}.zip"
        print_status "Using zip from SCD-FREE folder..."
    else
        ZIP_FILE="$VERSION"
        VERSION=$(echo "$ZIP_FILE" | grep -oP '\d+\.\d+\.\d+' | tail -1)
        if [ -z "$VERSION" ]; then
            print_warning "Could not extract version from filename."
            read -p "Enter version number (e.g., 1.0.2): " VERSION
        fi
    fi

    if [ ! -f "$ZIP_FILE" ]; then
        print_error "Zip file not found: $ZIP_FILE"
        exit 1
    fi

    print_status "Deploying version $VERSION from zip..."
    rm -rf "$TEMP_DIR"
    mkdir -p "$TEMP_DIR"
    print_status "Extracting zip..."
    python3 -c "import zipfile; z = zipfile.ZipFile('$ZIP_FILE'); z.extractall('$TEMP_DIR')"
    print_status "Copying files to trunk..."
    cp -r "$TEMP_DIR/$PLUGIN_SLUG/"* trunk/
fi

# Remove Cycle AI files from WordPress.org package (Pro-only feature)
print_status "Removing Cycle AI files from WordPress.org package..."
AI_FILES=(
	"trunk/includes/admin/ajax/handlers/class-cycle-ai-create-full-handler.php"
	"trunk/includes/admin/ajax/handlers/class-cycle-ai-handler.php"
	"trunk/includes/services/class-cycle-ai-service.php"
	"trunk/resources/assets/js/admin/cycle-ai-create-full.js"
	"trunk/resources/assets/js/wizard/cycle-ai-suggestions.js"
	"trunk/resources/assets/css/admin/cycle-ai-create-modal.css"
)
for f in "${AI_FILES[@]}"; do
	if [ -f "$SVN_DIR/$f" ]; then
		rm -f "$SVN_DIR/$f"
		print_status "  Removed $f"
	fi
done

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
