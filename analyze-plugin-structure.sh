#!/bin/bash

# Script: analyze-plugin-structure.sh v5 (Ultra-Simple)
# Purpose: Fast plugin structure analysis
# Usage: ./analyze-plugin-structure.sh

# Configuration
OUTPUT_FILE="PLUGIN-STRUCTURE.md"

echo "🔍 Analyzing Plugin Structure..."
echo "📍 Directory: $(pwd)"
echo ""

# Create temporary files
PHP_FILE="/tmp/php_$$.txt"
JS_FILE="/tmp/js_$$.txt"
CSS_FILE="/tmp/css_$$.txt"

# Clear temp files
> "$PHP_FILE"
> "$JS_FILE"
> "$CSS_FILE"

echo "📊 Finding PHP files..."
find . -name "*.php" -type f \
    -not -path "./vendor/*" \
    -not -path "./node_modules/*" \
    -not -path "./.git/*" \
    2>/dev/null | while read -r file; do
    
    loc=$(wc -l < "$file" 2>/dev/null || echo "0")
    folder=$(dirname "$file" | sed 's|^\./||')
    filename=$(basename "$file")
    
    echo "${folder}|${filename}|${loc}" >> "$PHP_FILE"
    
    # Show progress every 10 files
    count=$(wc -l < "$PHP_FILE")
    if [ $((count % 10)) -eq 0 ]; then
        echo -ne "\r   Processed: ${count} files"
    fi
done
echo -e "\r   Found: $(wc -l < "$PHP_FILE") PHP files    "

echo "📊 Finding JavaScript files..."
find . -name "*.js" -type f \
    -not -path "./vendor/*" \
    -not -path "./node_modules/*" \
    -not -path "./.git/*" \
    2>/dev/null | while read -r file; do
    
    loc=$(wc -l < "$file" 2>/dev/null || echo "0")
    folder=$(dirname "$file" | sed 's|^\./||')
    filename=$(basename "$file")
    
    echo "${folder}|${filename}|${loc}" >> "$JS_FILE"
done
echo "   Found: $(wc -l < "$JS_FILE") JS files"

echo "📊 Finding CSS/SCSS files..."
find . \( -name "*.css" -o -name "*.scss" \) -type f \
    -not -path "./vendor/*" \
    -not -path "./node_modules/*" \
    -not -path "./.git/*" \
    2>/dev/null | while read -r file; do
    
    loc=$(wc -l < "$file" 2>/dev/null || echo "0")
    folder=$(dirname "$file" | sed 's|^\./||')
    filename=$(basename "$file")
    
    echo "${folder}|${filename}|${loc}" >> "$CSS_FILE"
done
echo "   Found: $(wc -l < "$CSS_FILE") CSS/SCSS files"

echo ""
echo "📝 Generating report..."

# Sort all files by folder, then by LOC
sort -t'|' -k1,1 -k3,3rn "$PHP_FILE" -o "$PHP_FILE"
sort -t'|' -k1,1 -k3,3rn "$JS_FILE" -o "$JS_FILE"
sort -t'|' -k1,1 -k3,3rn "$CSS_FILE" -o "$CSS_FILE"

# Start generating markdown
cat > "$OUTPUT_FILE" << 'EOF'
# Smart Cycle Discounts - Plugin Structure Analysis

> **Purpose:** Complete file listing organized by type and folder, sorted by LOC

---

## Table of Contents

1. [PHP Files](#php-files)
2. [JavaScript Files](#javascript-files)
3. [CSS/SCSS Files](#cssscss-files)
4. [Summary Statistics](#summary-statistics)

---

EOF

# Function to generate section
generate_section() {
    local input_file="$1"
    local section_name="$2"
    
    echo "" >> "$OUTPUT_FILE"
    echo "## ${section_name} Files" >> "$OUTPUT_FILE"
    echo "" >> "$OUTPUT_FILE"
    
    if [ ! -s "$input_file" ]; then
        echo "_No ${section_name} files found._" >> "$OUTPUT_FILE"
        return
    fi
    
    local current_folder=""
    local folder_total=0
    local folder_count=0
    
    while IFS='|' read -r folder filename loc; do
        # New folder?
        if [ "$folder" != "$current_folder" ]; then
            # Print previous folder total
            if [ -n "$current_folder" ]; then
                echo "" >> "$OUTPUT_FILE"
                echo "**Folder Total: ${folder_count} files, ${folder_total} LOC**" >> "$OUTPUT_FILE"
                echo "" >> "$OUTPUT_FILE"
            fi
            
            current_folder="$folder"
            folder_total=0
            folder_count=0
            
            echo "### 📁 \`${folder}/\`" >> "$OUTPUT_FILE"
            echo "" >> "$OUTPUT_FILE"
            echo "| File Name | LOC |" >> "$OUTPUT_FILE"
            echo "|-----------|----:|" >> "$OUTPUT_FILE"
        fi
        
        # Add file
        echo "| \`${filename}\` | ${loc} |" >> "$OUTPUT_FILE"
        
        folder_total=$((folder_total + loc))
        folder_count=$((folder_count + 1))
    done < "$input_file"
    
    # Last folder total
    if [ -n "$current_folder" ]; then
        echo "" >> "$OUTPUT_FILE"
        echo "**Folder Total: ${folder_count} files, ${folder_total} LOC**" >> "$OUTPUT_FILE"
        echo "" >> "$OUTPUT_FILE"
    fi
}

# Generate each section
generate_section "$PHP_FILE" "PHP"
generate_section "$JS_FILE" "JavaScript"
generate_section "$CSS_FILE" "CSS/SCSS"

# Calculate totals
php_count=$(wc -l < "$PHP_FILE" 2>/dev/null || echo 0)
php_loc=$(awk -F'|' '{sum+=$3} END {print sum+0}' "$PHP_FILE" 2>/dev/null || echo 0)

js_count=$(wc -l < "$JS_FILE" 2>/dev/null || echo 0)
js_loc=$(awk -F'|' '{sum+=$3} END {print sum+0}' "$JS_FILE" 2>/dev/null || echo 0)

css_count=$(wc -l < "$CSS_FILE" 2>/dev/null || echo 0)
css_loc=$(awk -F'|' '{sum+=$3} END {print sum+0}' "$CSS_FILE" 2>/dev/null || echo 0)

total_files=$((php_count + js_count + css_count))
total_loc=$((php_loc + js_loc + css_loc))

# Add summary
cat >> "$OUTPUT_FILE" << EOF

---

## Summary Statistics

| File Type | File Count | Total LOC | Avg LOC/File |
|-----------|----------:|----------:|-------------:|
| **PHP** | ${php_count} | ${php_loc} | $((php_count > 0 ? php_loc / php_count : 0)) |
| **JavaScript** | ${js_count} | ${js_loc} | $((js_count > 0 ? js_loc / js_count : 0)) |
| **CSS/SCSS** | ${css_count} | ${css_loc} | $((css_count > 0 ? css_loc / css_count : 0)) |
| **TOTAL** | **${total_files}** | **${total_loc}** | **$((total_files > 0 ? total_loc / total_files : 0))** |

---

## Top 30 Largest Files

| # | File Name | Type | LOC |
|--:|-----------|------|----:|
EOF

# Combine and show top 30
cat "$PHP_FILE" "$JS_FILE" "$CSS_FILE" 2>/dev/null | \
    sort -t'|' -k3,3rn | head -30 | {
    counter=1
    while IFS='|' read -r folder filename loc; do
        case "$filename" in
            *.php) ftype="PHP" ;;
            *.js) ftype="JS" ;;
            *.css|*.scss) ftype="CSS" ;;
            *) ftype="Other" ;;
        esac
        echo "| ${counter} | \`${filename}\` | ${ftype} | ${loc} |" >> "$OUTPUT_FILE"
        ((counter++))
    done
}

echo "" >> "$OUTPUT_FILE"
echo "---" >> "$OUTPUT_FILE"
echo "" >> "$OUTPUT_FILE"
echo "_Generated by analyze-plugin-structure.sh v5_" >> "$OUTPUT_FILE"

# Clean up
rm -f "$PHP_FILE" "$JS_FILE" "$CSS_FILE"

echo ""
echo "✅ Complete!"
echo "📄 Report: ${OUTPUT_FILE}"
echo ""
echo "📊 Summary:"
echo "  PHP:      ${php_count} files (${php_loc} LOC)"
echo "  JS:       ${js_count} files (${js_loc} LOC)"
echo "  CSS/SCSS: ${css_count} files (${css_loc} LOC)"
echo "  ──────────────────────────"
echo "  Total:    ${total_files} files (${total_loc} LOC)"
echo ""
echo "💡 View with: code PLUGIN-STRUCTURE.md"
SCRIPT_END.
