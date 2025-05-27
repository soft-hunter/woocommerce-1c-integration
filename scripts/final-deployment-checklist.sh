#!/usr/bin/env bash
set -eu -o pipefail

echo "🚀 Final Deployment Checklist for WooCommerce 1C Integration"
echo "============================================================="

# Colors for output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "\n${BLUE}📋 Pre-deployment Verification${NC}"

# 1. Run all tests one final time
echo "🔍 Running final validation..."
php scripts/validate-plugin.php > /dev/null 2>&1
if [ $? -eq 0 ]; then
    echo -e "  ${GREEN}✅ Plugin validation: PASSED${NC}"
else
    echo -e "  ❌ Plugin validation: FAILED"
    exit 1
fi

# 2. Check code quality
echo "🔧 Running code quality check..."
php scripts/check-code-quality.php > /dev/null 2>&1
if [ $? -eq 0 ]; then
    echo -e "  ${GREEN}✅ Code quality: PASSED${NC}"
else
    echo -e "  ❌ Code quality: FAILED"
    exit 1
fi

# 3. Run pre-deployment check
echo "🚀 Running pre-deployment check..."
./scripts/pre-deployment-check.sh > /dev/null 2>&1
if [ $? -eq 0 ]; then
    echo -e "  ${GREEN}✅ Pre-deployment check: PASSED${NC}"
else
    echo -e "  ❌ Pre-deployment check: FAILED"
    exit 1
fi

# 4. Build the plugin
echo "🏗️ Building plugin package..."
./scripts/build.sh > /dev/null 2>&1
if [ $? -eq 0 ]; then
    echo -e "  ${GREEN}✅ Plugin build: SUCCESSFUL${NC}"
else
    echo -e "  ❌ Plugin build: FAILED"
    exit 1
fi

# 5. Verify package contents
echo "📦 Verifying package contents..."
if [ -f "dist/woocommerce-1c-integration-v1.0.0.zip" ]; then
    echo -e "  ${GREEN}✅ Package created successfully${NC}"
    
    # Show package size
    size=$(du -h "dist/woocommerce-1c-integration-v1.0.0.zip" | cut -f1)
    echo -e "  📏 Package size: ${size}"
    
    # List package contents
    echo "  📁 Package contents:"
    unzip -l "dist/woocommerce-1c-integration-v1.0.0.zip" | head -20
else
    echo -e "  ❌ Package not found"
    exit 1
fi

echo -e "\n${BLUE}🎯 Deployment Targets${NC}"
echo "The plugin is ready for deployment to:"
echo "  • WordPress.org Plugin Repository"
echo "  • GitHub Releases"
echo "  • Private distribution"
echo "  • Client installations"

echo -e "\n${BLUE}📋 Post-deployment Checklist${NC}"
echo "After deployment, remember to:"
echo "  • Test installation on a staging site"
echo "  • Verify WooCommerce compatibility"
echo "  • Test 1C integration functionality"
echo "  • Monitor error logs"
echo "  • Update documentation if needed"

echo -e "\n${GREEN}🎉 CONGRATULATIONS!${NC}"
echo "Your WooCommerce 1C Integration plugin is ready for deployment!"
echo -e "Package: ${YELLOW}dist/woocommerce-1c-integration-v1.0.0.zip${NC}"

exit 0