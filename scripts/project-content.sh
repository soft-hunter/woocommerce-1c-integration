#!/bin/bash
# Project Content Extractor
# Creates a single file with project structure and PHP, CSS, and JS files from src folder

# Create dist folder if it doesn't exist
mkdir -p dist

# Output file path
OUTPUT_FILE="dist/project_contents.txt"

# Visual tree structure (for entire project)
echo "==== VISUAL TREE STRUCTURE (ENTIRE PROJECT) ====" > "$OUTPUT_FILE"
if command -v tree >/dev/null 2>&1; then
  tree -I "vendor|node_modules|.git|dist" . >> "$OUTPUT_FILE"
else
  echo "Tree command not installed. Install with: apt-get install tree (Ubuntu/Debian) or brew install tree (macOS)" >> "$OUTPUT_FILE"
fi

# # Separate tree for just src directory
# echo -e "\n==== VISUAL TREE STRUCTURE (SRC ONLY) ====" >> "$OUTPUT_FILE"
# if command -v tree >/dev/null 2>&1; then
#   tree -I "vendor|node_modules|.git" src >> "$OUTPUT_FILE"
# else
#   echo "Tree command not installed." >> "$OUTPUT_FILE"
# fi

# Flat file list (only PHP, CSS, JS from src)
echo -e "\n==== PROJECT STRUCTURE (SRC FILES) ====" >> "$OUTPUT_FILE"
find ./src -type f \( -name "*.php" -o -name "*.css" -o -name "*.js" \) | sort >> "$OUTPUT_FILE"

# File contents (only PHP, CSS, JS from src)
echo -e "\n==== FILE CONTENTS ====" >> "$OUTPUT_FILE"
for file in $(find ./src -type f \( -name "*.php" -o -name "*.css" -o -name "*.js" \) | sort); do
  echo -e "\n\n=== $file ===" >> "$OUTPUT_FILE"
  cat "$file" >> "$OUTPUT_FILE"
done

echo "Project contents extracted to $OUTPUT_FILE"