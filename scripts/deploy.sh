#!/usr/bin/env bash
# Deploy plugin to production

set -eu -o pipefail

echo "🚀 Preparing WooCommerce 1C Exchange for deployment..."

# Create deployment package
version=$(grep "Version:" woocommerce-1c.php | sed 's/.*Version: *//')
package_name="woocommerce-1c-v$version"

# Create clean copy
mkdir -p "dist/$package_name"

# Copy files (exclude development files)
rsync -av --exclude-from='.deployignore' . "dist/$package_name/"

# Create zip
cd dist
zip -r "$package_name.zip" "$package_name"

echo "✅ Deployment package created: dist/$package_name.zip"