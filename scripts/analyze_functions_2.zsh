#!/bin/bash

# Define the plugin directory - replace with your actual plugin path if different
PLUGIN_DIR="."

# Function to display header
print_header() {
  echo "----------------------------------------"
  echo "$1"
  echo "----------------------------------------"
}

# List all PHP files
print_header "PHP FILES"
find "$PLUGIN_DIR" -type f -name "*.php" | sort

# Extract and list all classes
print_header "PHP CLASSES"
find "$PLUGIN_DIR" -type f -name "*.php" -exec grep -l "class " {} \; | sort | while read -r file; do
  echo "FILE: $file"
  grep -n "^[[:space:]]*class [A-Za-z0-9_]\+" "$file" | sed 's/class //'
  echo ""
done

# Extract and list all functions
print_header "PHP FUNCTIONS"
find "$PLUGIN_DIR" -type f -name "*.php" -exec grep -l "function " {} \; | sort | while read -r file; do
  echo "FILE: $file"
  grep -n "function [A-Za-z0-9_]\+" "$file" | sed 's/.*function //'
  echo ""
done