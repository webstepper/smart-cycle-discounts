#!/bin/bash
#
# Smart Cycle Discounts - Production Build Script
#
# Simple wrapper around build.py for convenience
#

# Get the directory where this script is located
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

# Run the Python build script
python3 "$SCRIPT_DIR/build.py"
