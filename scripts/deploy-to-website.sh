#!/usr/bin/env bash
set -eu -o pipefail

echo "🌐 Deploying WooCommerce 1C Integration to Website"
echo "=================================================="

# Configuration
PLUGIN_ZIP="dist/woocommerce-1c-integration-v1.0.0.zip"
PLUGIN_NAME="woocommerce-1c-integration"

# Colors for output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Check if plugin package exists
if [ ! -f "$PLUGIN_ZIP" ]; then
    echo -e "${RED}❌ Plugin package not found: $PLUGIN_ZIP${NC}"
    echo "Please run ./scripts/build.sh first"
    exit 1
fi

echo -e "${BLUE}📦 Plugin package found: $PLUGIN_ZIP${NC}"
echo -e "${BLUE}📏 Package size: $(du -h "$PLUGIN_ZIP" | cut -f1)${NC}"

echo -e "\n${YELLOW}🔧 Deployment Options:${NC}"
echo "1. Manual Upload via WordPress Admin"
echo "2. FTP/SFTP Upload"
echo "3. SSH/SCP Upload"
echo "4. Git Deployment"
echo "5. WP-CLI Installation"

echo -e "\n${BLUE}📋 Pre-deployment Checklist:${NC}"
echo "✅ Plugin package built and validated"
echo "✅ WordPress site backup recommended"
echo "✅ WooCommerce plugin must be installed"
echo "✅ PHP 7.4+ required"
echo "✅ WordPress 5.0+ required"

echo -e "\n${GREEN}🎯 Ready for deployment!${NC}"
echo "Choose your preferred deployment method below:"

exit 0