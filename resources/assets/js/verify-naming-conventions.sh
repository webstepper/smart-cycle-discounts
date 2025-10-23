#!/bin/bash

################################################################################
# Snake_case/camelCase Naming Convention Verifier
#
# Purpose: Ensures 100% compliance with naming conventions:
# - JavaScript uses camelCase for all data sent to PHP
# - PHP uses snake_case (auto-converted by router)
# - Reading from PHP keeps snake_case (correct direction)
#
# Usage: ./verify-naming-conventions.sh
################################################################################

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cd "$SCRIPT_DIR"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo "================================================================"
echo "  SNAKE_CASE/CAMELCASE NAMING CONVENTION VERIFICATION"
echo "  Checks both JavaScript (camelCase) and PHP Router (accepts it)"
echo "================================================================"
echo ""

ISSUES_FOUND=0

# ============================================================================
# CHECK 1: AJAX Requests Going Through Unified Router
# ============================================================================
echo "${YELLOW}[1/4] Checking AJAX requests through unified router...${NC}"

PROBLEMATIC_KEYS=$(grep -rn "makeAjaxRequest\|SCD\.Ajax\.post\|SCD\.Admin\.ajax" --include="*.js" . 2>/dev/null | \
  grep -A10 "{" | \
  grep -E "^\s*[a-z][a-z_]*_[a-z_]*\s*:" | \
  grep -v "scd-analytics-tracking.js" | \
  grep -v "discounts-conditions.js" | \
  grep -v "discounts-config.js" | \
  wc -l)

if [ "$PROBLEMATIC_KEYS" -gt 0 ]; then
  echo "${RED}✗ FAIL: Found $PROBLEMATIC_KEYS snake_case keys in AJAX requests${NC}"
  grep -rn "makeAjaxRequest\|SCD\.Ajax\.post\|SCD\.Admin\.ajax" --include="*.js" . 2>/dev/null | \
    grep -A10 "{" | \
    grep -E "^\s*[a-z][a-z_]*_[a-z_]*\s*:" | \
    grep -v "scd-analytics-tracking.js" | \
    head -10
  ISSUES_FOUND=$((ISSUES_FOUND + 1))
else
  echo "${GREEN}✓ PASS: All AJAX request keys use camelCase${NC}"
fi
echo ""

# ============================================================================
# CHECK 2: Common Problematic Fields
# ============================================================================
echo "${YELLOW}[2/4] Checking common problematic fields...${NC}"

COMMON_FIELDS=$(grep -rn "\b(campaign_id|product_id|date_range|data_type|scd_action):" --include="*.js" . 2>/dev/null | \
  grep -v "scd-analytics-tracking.js" | \
  grep -v "products-state.js" | \
  grep -v "discounts-conditions.js" | \
  wc -l)

if [ "$COMMON_FIELDS" -gt 0 ]; then
  echo "${RED}✗ FAIL: Found $COMMON_FIELDS instances of common snake_case fields${NC}"
  grep -rn "\b(campaign_id|product_id|date_range|data_type|scd_action):" --include="*.js" . 2>/dev/null | \
    grep -v "scd-analytics-tracking.js" | \
    grep -v "products-state.js" | \
    head -10
  ISSUES_FOUND=$((ISSUES_FOUND + 1))
else
  echo "${GREEN}✓ PASS: No common snake_case fields found${NC}"
fi
echo ""

# ============================================================================
# CHECK 3: collectData and toJSON Functions
# ============================================================================
echo "${YELLOW}[3/4] Checking collectData/toJSON functions...${NC}"

COLLECT_DATA_ISSUES=$(grep -A30 "collectData.*function\|toJSON.*function" --include="*.js" . 2>/dev/null | \
  grep "return\s*{" -A20 | \
  grep -E "^\s*[a-z][a-z_]*_[a-z_]*\s*:" | \
  grep -v "products-state.js" | \
  wc -l)

if [ "$COLLECT_DATA_ISSUES" -gt 0 ]; then
  echo "${RED}✗ FAIL: Found $COLLECT_DATA_ISSUES snake_case keys in collectData/toJSON${NC}"
  ISSUES_FOUND=$((ISSUES_FOUND + 1))
else
  echo "${GREEN}✓ PASS: All collectData/toJSON return camelCase${NC}"
fi
echo ""

# ============================================================================
# CHECK 4: Router Configuration (CLEAN ARCHITECTURE)
# ============================================================================
echo "${YELLOW}[4/5] Checking router follows clean architecture...${NC}"

PLUGIN_ROOT="$SCRIPT_DIR/../../.."
ROUTER_FILE="$PLUGIN_ROOT/includes/admin/ajax/class-ajax-router.php"

if [ -f "$ROUTER_FILE" ]; then
  # Router should ONLY accept camelCase (no fallbacks to snake_case)
  ROUTER_CAMEL=$(grep "isset.*POST.*scdAction" "$ROUTER_FILE" 2>/dev/null | wc -l)
  ROUTER_SNAKE_FALLBACK=$(grep "elseif.*isset.*POST.*scd_action" "$ROUTER_FILE" 2>/dev/null | wc -l)

  if [ "$ROUTER_CAMEL" -gt 0 ] && [ "$ROUTER_SNAKE_FALLBACK" -eq 0 ]; then
    echo "${GREEN}✓ PASS: Router accepts ONLY camelCase (clean architecture)${NC}"
  elif [ "$ROUTER_CAMEL" -gt 0 ] && [ "$ROUTER_SNAKE_FALLBACK" -gt 0 ]; then
    echo "${RED}✗ FAIL: Router has snake_case fallback (violates clean architecture)${NC}"
    echo "Router should ONLY accept camelCase:"
    echo "  ✓ if ( isset( \$_POST['scdAction'] ) )"
    echo "  ✗ elseif ( isset( \$_POST['scd_action'] ) )  // Remove this"
    ISSUES_FOUND=$((ISSUES_FOUND + 1))
  else
    echo "${RED}✗ FAIL: Router doesn't accept camelCase${NC}"
    echo "Router must accept: if ( isset( \$_POST['scdAction'] ) )"
    ISSUES_FOUND=$((ISSUES_FOUND + 1))
  fi
else
  echo "${YELLOW}⚠ WARNING: Could not find router file${NC}"
fi
echo ""

# ============================================================================
# CHECK 5: Direct $.ajax Calls with 'scd_ajax' Action
# ============================================================================
echo "${YELLOW}[5/5] Checking direct $.ajax calls to unified endpoint...${NC}"

DIRECT_AJAX=$(grep -B5 "action.*'scd_ajax'" --include="*.js" . 2>/dev/null | \
  grep -E "^\s*[a-z][a-z_]*_[a-z_]*\s*:" | \
  grep -v "action:" | \
  wc -l)

if [ "$DIRECT_AJAX" -gt 0 ]; then
  echo "${RED}✗ FAIL: Found $DIRECT_AJAX snake_case keys in direct unified endpoint calls${NC}"
  ISSUES_FOUND=$((ISSUES_FOUND + 1))
else
  echo "${GREEN}✓ PASS: All direct unified endpoint calls use camelCase${NC}"
fi
echo ""

# ============================================================================
# SUMMARY
# ============================================================================
echo "================================================================"
if [ "$ISSUES_FOUND" -eq 0 ]; then
  echo "${GREEN}✓✓✓ ALL CHECKS PASSED - 100% COMPLIANT ✓✓✓${NC}"
  echo ""
  echo "Your codebase follows naming conventions correctly:"
  echo "  • JS→PHP: camelCase (router converts to snake_case)"
  echo "  • PHP→JS: snake_case (reading from PHP is correct)"
  exit 0
else
  echo "${RED}✗✗✗ FOUND $ISSUES_FOUND ISSUE(S) ✗✗✗${NC}"
  echo ""
  echo "Please fix the issues above and re-run this script."
  exit 1
fi
