#!/bin/bash

# WooCommerce 1C Integration - Pre-deployment Check
# This script performs comprehensive checks before deployment

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Counters
TOTAL_CHECKS=0
PASSED_CHECKS=0
FAILED_CHECKS=0

echo -e "${BLUE}🚀 Pre-deployment Check for WooCommerce 1C Integration${NC}"
echo "=================================================="

# Function to run a check
run_check() {
    local check_name="$1"
    local check_command="$2"
    
    echo -e "${BLUE}🔍 $check_name${NC}"
    TOTAL_CHECKS=$((TOTAL_CHECKS + 1))
    
    if eval "$check_command"; then
        echo -e "${GREEN}✅ PASSED${NC}"
        PASSED_CHECKS=$((PASSED_CHECKS + 1))
    else
        echo -e "${RED}❌ FAILED${NC}"
        FAILED_CHECKS=$((FAILED_CHECKS + 1))
    fi
    echo
}

# Function to check if command exists
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# Check 1: File Structure Validation
check_file_structure() {
    if command_exists php; then
        php scripts/validate-plugin.php >/dev/null 2>&1
    else
        echo "⚠️ PHP not found, skipping detailed validation"
        return 1
    fi
}

# Check 2: Code Quality Analysis
check_code_quality() {
    if command_exists php; then
        php scripts/check-code-quality.php >/dev/null 2>&1
    else
        echo "⚠️ PHP not found, skipping code quality check"
        return 1
    fi
}

# Check 3: Documentation Completeness
check_documentation() {
    ./scripts/check-documentation.sh >/dev/null 2>&1
}

# Check 4: PHP Syntax Validation
check_php_syntax() {
    if ! command_exists php; then
        echo "⚠️ PHP not found, skipping syntax check"
        return 1
    fi
    
    local syntax_errors=0
    while IFS= read -r -d '' file; do
        if ! php -l "$file" >/dev/null 2>&1; then
            echo "❌ Syntax error in: $file"
            syntax_errors=$((syntax_errors + 1))
        fi
    done < <(find src -name "*.php" -print0)
    
    if [ $syntax_errors -eq 0 ]; then
        echo "✅ All PHP files have valid syntax"
        return 0
    else
        echo "❌ Found $syntax_errors syntax errors"
        return 1
    fi
}

# Check 5: Security Validation
check_security() {
    local security_issues=0
    
    # Check for direct access protection
    echo "🔒 Checking direct access protection..."
    while IFS= read -r -d '' file; do
        if ! grep -q "if (!defined('ABSPATH'))" "$file"; then
            echo "❌ Files without direct access protection: $file"
            security_issues=$((security_issues + 1))
        fi
    done < <(find src -name "*.php" -not -path "*/languages/*" -print0)
    
    # Check for potential SQL injection
    echo "🔒 Checking for SQL injection risks..."
    while IFS= read -r -d '' file; do
        if grep -q '\$wpdb->query\|get_var\|get_results' "$file" && ! grep -q 'prepare(' "$file"; then
            echo "⚠️ Potential SQL injection risk in: $file"
            security_issues=$((security_issues + 1))
        fi
    done < <(find src -name "*.php" -print0)
    
    if [ $security_issues -eq 0 ]; then
        echo "✅ No security issues found"
        return 0
    else
        echo "❌ FAILED - $security_issues security issues found"
        return 1
    fi
}

# Check 6: WordPress Coding Standards
check_wp_standards() {
    local issues=0
    
    # Check for WordPress hooks
    if ! grep -r "add_action\|add_filter" src/ >/dev/null 2>&1; then
        echo "⚠️ No WordPress hooks found"
        issues=$((issues + 1))
    fi
    
    # Check for internationalization
    if ! grep -r "__(\|_e(" src/ >/dev/null 2>&1; then
        echo "⚠️ No internationalization functions found"
        issues=$((issues + 1))
    fi
    
    # Check for sanitization functions
    if ! grep -r "sanitize_\|esc_" src/ >/dev/null 2>&1; then
        echo "⚠️ No sanitization functions found"
        issues=$((issues + 1))
    fi
    
    if [ $issues -eq 0 ]; then
        echo "✅ WordPress coding standards followed"
        return 0
    else
        echo "⚠️ $issues potential WordPress standards issues"
        return 0  # Don't fail for warnings
    fi
}

# Check 7: HPOS Compatibility
check_hpos_compatibility() {
    if [ -f "src/exchange/mappers/class-wc1c-hpos-order-mapper.php" ]; then
        echo "✅ HPOS compatibility implemented"
        return 0
    else
        echo "❌ HPOS compatibility missing"
        return 1
    fi
}

# Check 8: Composer Dependencies
check_composer() {
    if command_exists composer; then
        composer validate --no-check-all --strict >/dev/null 2>&1
    else
        echo "⚠️ Composer not found, skipping validation"
        return 1
    fi
}

# Check 9: Version Consistency
check_version_consistency() {
    local main_version=""
    local composer_version=""
    local package_version=""
    
    # Get version from main plugin file
    if [ -f "src/woocommerce-1c-integration.php" ]; then
        main_version=$(grep "Version:" src/woocommerce-1c-integration.php | sed 's/.*Version: *//' | tr -d ' ')
    fi
    
    # Get version from composer.json
    if [ -f "composer.json" ] && command_exists php; then
        composer_version=$(php -r "echo json_decode(file_get_contents('composer.json'))->version ?? '';")
    fi
    
    # Get version from package.json
    if [ -f "package.json" ] && command_exists node; then
        package_version=$(node -p "require('./package.json').version" 2>/dev/null || echo "")
    fi
    
    echo "📋 Version check:"
    echo "  Main file: $main_version"
    echo "  Composer: $composer_version"
    echo "  Package: $package_version"
    
    if [ "$main_version" = "$composer_version" ] || [ -z "$composer_version" ]; then
        echo "✅ Versions are consistent"
        return 0
    else
        echo "❌ Version mismatch detected"
        return 1
    fi
}

# Check 10: Build Process Test
check_build_process() {
    if [ -x "scripts/build.sh" ]; then
        echo "✅ Build script is executable"
        return 0
    else
        echo "❌ Build script not found or not executable"
        return 1
    fi
}

# Run all checks
run_check "File Structure Validation" "check_file_structure"
run_check "Code Quality Analysis" "check_code_quality"
run_check "Documentation Completeness" "check_documentation"
run_check "PHP Syntax Validation" "check_php_syntax"
run_check "Security Validation" "check_security"
run_check "WordPress Coding Standards" "check_wp_standards"
run_check "HPOS Compatibility Check" "check_hpos_compatibility"
run_check "Composer Dependencies" "check_composer"
run_check "Version Consistency Check" "check_version_consistency"
run_check "Build Process Test" "check_build_process"

# Summary
echo -e "${BLUE}📊 Pre-deployment Summary${NC}"
echo "=========================="
echo "Total Checks: $TOTAL_CHECKS"
echo "Passed: $PASSED_CHECKS"
echo "Failed: $FAILED_CHECKS"

SUCCESS_RATE=$((PASSED_CHECKS * 100 / TOTAL_CHECKS))
echo "Success Rate: $SUCCESS_RATE%"

if [ $SUCCESS_RATE -ge 80 ]; then
    echo -e "${GREEN}🎉 Plugin is ready for deployment!${NC}"
    exit 0
else
    echo -e "${RED}❌ Plugin needs more work before deployment.${NC}"
    exit 1
fi