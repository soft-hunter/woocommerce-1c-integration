#!/usr/bin/env bash
set -eu -o pipefail

echo "🏗️  Building WooCommerce 1C Integration plugin..."

# Get version from main plugin file
version=$(grep "Version:" src/woocommerce-1c-integration.php | sed 's/.*Version: *//' | tr -d ' ')
package_name="woocommerce-1c-integration-v$version"

# Clean previous builds
rm -rf dist
mkdir -p dist

# Create build directory
build_dir="dist/$package_name"
mkdir -p "$build_dir"

# Copy source files
cp -r src/* "$build_dir/"

# Remove development files
rm -f "$build_dir/composer.json"
rm -f "$build_dir/package.json"
rm -rf "$build_dir/node_modules"
rm -rf "$build_dir/vendor"
rm -rf "$build_dir/.git"
rm -rf "$build_dir/.github"
rm -f "$build_dir/.gitignore"

# Create zip package
cd dist
zip -r "$package_name.zip" "$package_name"
cd ..

echo "✅ Package built: dist/$package_name.zip"
echo "📦 Size: $(du -h "dist/$package_name.zip" | cut -f1)"

# Create checksums
cd dist
sha256sum "$package_name.zip" > "$package_name.zip.sha256"
cd ..

echo "🔐 Checksum created: dist/$package_name.zip.sha256"
echo "🎉 Build complete!"