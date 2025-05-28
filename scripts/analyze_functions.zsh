#!/bin/zsh

echo "=== WooCommerce 1C Integration - Function Analysis ==="
echo "Date: $(date)"
echo "=================================================="

# Function to extract PHP functions from a file
extract_functions() {
    local file="$1"
    if [[ -f "$file" ]]; then
        echo "\n📁 File: $file"
        echo "$(printf '=%.0s' {1..80})"
        
        # Extract function definitions
        grep -n "function\s\+[a-zA-Z_][a-zA-Z0-9_]*\s*(" "$file" | head -20
        
        # Extract class methods
        grep -n "public\s\+function\s\+[a-zA-Z_][a-zA-Z0-9_]*\s*(" "$file" | head -20
        grep -n "private\s\+function\s\+[a-zA-Z_][a-zA-Z0-9_]*\s*(" "$file" | head -20
        grep -n "protected\s\+function\s\+[a-zA-Z_][a-zA-Z0-9_]*\s*(" "$file" | head -20
        
        # Extract class definitions
        grep -n "class\s\+[a-zA-Z_][a-zA-Z0-9_]*" "$file"
        
        echo "\n"
    else
        echo "❌ File not found: $file"
    fi
}

# Navigate to your project directory
cd ~/woocommerce-1c-integration

echo "🔍 Analyzing main plugin file..."
extract_functions "src/woocommerce-1c-integration.php"

echo "🔍 Analyzing core classes..."
extract_functions "src/includes/class-wc1c.php"
extract_functions "src/includes/class-wc1c-activator.php"
extract_functions "src/includes/class-wc1c-deactivator.php"
extract_functions "src/includes/class-wc1c-loader.php"
extract_functions "src/includes/class-wc1c-logger.php"

echo "🔍 Analyzing admin classes..."
extract_functions "src/admin/class-wc1c-admin.php"

echo "🔍 Analyzing exchange classes..."
extract_functions "src/exchange/class-wc1c-exchange.php"
extract_functions "src/exchange/class-wc1c-auth.php"
extract_functions "src/exchange/class-wc1c-file-handler.php"

echo "🔍 Analyzing public classes..."
extract_functions "src/public/class-wc1c-public.php"

echo "\n📊 Summary:"
echo "=================================================="
echo "Total PHP files found:"
find src -name "*.php" | wc -l

echo "\nTotal functions found:"
find src -name "*.php" -exec grep -l "function\s\+[a-zA-Z_]" {} \; | wc -l

echo "\nTotal classes found:"
find src -name "*.php" -exec grep -l "class\s\+[a-zA-Z_]" {} \; | wc -l

echo "\n🔧 Missing files check:"
echo "=================================================="
required_files=(
    "src/includes/class-wc1c.php"
    "src/includes/class-wc1c-activator.php" 
    "src/includes/class-wc1c-deactivator.php"
    "src/includes/class-wc1c-loader.php"
    "src/admin/class-wc1c-admin.php"
)

for file in "${required_files[@]}"; do
    if [[ -f "$file" ]]; then
        echo "✅ $file"
    else
        echo "❌ $file - MISSING!"
    fi
done

echo "\n🎯 Quick content preview of key files:"
echo "=================================================="

for file in "${required_files[@]}"; do
    if [[ -f "$file" ]]; then
        echo "\n📄 $file (first 10 lines):"
        head -10 "$file"
        echo "..."
    fi
done