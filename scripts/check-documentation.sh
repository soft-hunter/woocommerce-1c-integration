#!/bin/bash

echo "📚 Checking documentation completeness..."

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Counters
errors=0
warnings=0
success=0

# Check required documentation files
echo "📄 Checking documentation files..."

required_docs=("README.md" "CHANGELOG.md" "LICENSE" "CONTRIBUTING.md")

for doc in "${required_docs[@]}"; do
    if [ -f "$doc" ]; then
        if [ -s "$doc" ]; then
            echo -e "${GREEN}✅ Found and non-empty: $doc${NC}"
            ((success++))
        else
            echo -e "${YELLOW}⚠️ Found but empty: $doc${NC}"
            ((warnings++))
        fi
    else
        echo -e "${RED}❌ Missing: $doc${NC}"
        ((errors++))
    fi
done

# Check README.md content
echo -e "\n📖 Checking README.md content..."

if [ -f "README.md" ]; then
    readme_content=$(cat README.md)
    
    required_sections=("Installation" "Usage" "Configuration" "Requirements" "License")
    
    for section in "${required_sections[@]}"; do
        if echo "$readme_content" | grep -qi "$section"; then
            echo -e "${GREEN}✅ README contains: $section${NC}"
            ((success++))
        else
            echo -e "${YELLOW}⚠️ README missing section: $section${NC}"
            ((warnings++))
        fi
    done
fi

# Check CHANGELOG.md format
echo -e "\n📝 Checking CHANGELOG.md format..."

if [ -f "CHANGELOG.md" ]; then
    changelog_content=$(cat CHANGELOG.md)
    
    if echo "$changelog_content" | grep -q "## \["; then
        echo -e "${GREEN}✅ CHANGELOG follows semantic versioning format${NC}"
        ((success++))
    else
        echo -e "${YELLOW}⚠️ CHANGELOG doesn't follow semantic versioning format${NC}"
        ((warnings++))
    fi
    
    if echo "$changelog_content" | grep -qi "unreleased"; then
        echo -e "${GREEN}✅ CHANGELOG has unreleased section${NC}"
        ((success++))
    else
        echo -e "${YELLOW}⚠️ CHANGELOG missing unreleased section${NC}"
        ((warnings++))
    fi
fi

# Check inline documentation
echo -e "\n💬 Checking inline documentation..."

php_files=$(find src -name "*.php" -type f)
total_files=$(echo "$php_files" | wc -l)
documented_files=0

for file in $php_files; do
    if grep -q "/\*\*" "$file" && grep -q "@package" "$file"; then
        ((documented_files++))
    fi
done

doc_percentage=$((documented_files * 100 / total_files))

if [ $doc_percentage -ge 80 ]; then
    echo -e "${GREEN}✅ Good inline documentation coverage: $doc_percentage%${NC}"
    ((success++))
elif [ $doc_percentage -ge 50 ]; then
    echo -e "${YELLOW}⚠️ Moderate inline documentation coverage: $doc_percentage%${NC}"
    ((warnings++))
else
    echo -e "${RED}❌ Low inline documentation coverage: $doc_percentage%${NC}"
    ((errors++))
fi

# Check for TODO/FIXME comments
echo -e "\n🔍 Checking for TODO/FIXME comments..."

todo_count=$(grep -r "TODO\|FIXME" src/ --include="*.php" | wc -l)

if [ $todo_count -eq 0 ]; then
    echo -e "${GREEN}✅ No TODO/FIXME comments found${NC}"
    ((success++))
else
    echo -e "${YELLOW}⚠️ Found $todo_count TODO/FIXME comments${NC}"
    grep -r "TODO\|FIXME" src/ --include="*.php" | head -5
    ((warnings++))
fi

# Summary
echo -e "\n📊 Documentation Check Summary:"
echo -e "  Success: ${GREEN}$success${NC}"
echo -e "  Warnings: ${YELLOW}$warnings${NC}"
echo -e "  Errors: ${RED}$errors${NC}"

if [ $errors -eq 0 ]; then
    echo -e "\n${GREEN}🎉 Documentation check passed!${NC}"
    exit 0
else
    echo -e "\n${RED}❌ Documentation check failed. Please fix errors.${NC}"
    exit 1
fi