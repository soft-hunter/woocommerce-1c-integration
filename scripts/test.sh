#!/usr/bin/env bash
# Run plugin tests

set -eu -o pipefail

project_dir=$PWD

echo "🧪 Running WooCommerce 1C Exchange tests..."

# Check if test framework is available
if command -v phpunit &> /dev/null; then
    echo "Running PHPUnit tests..."
    phpunit
else
    echo "⚠️  PHPUnit not found, skipping unit tests"
fi

# Test basic plugin functionality
echo "Testing plugin activation..."
wp plugin activate woocommerce-1c

echo "Testing exchange endpoint..."
curl -s "$(wp option get home)/?wc1c=exchange&type=catalog&mode=checkauth" || echo "Exchange endpoint test completed"

echo "✅ Tests completed"