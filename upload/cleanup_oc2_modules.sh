#!/bin/bash

################################################################################
# OC2 Module Cleanup Script
# This script analyzes and removes obsolete OpenCart 2.x modules from OC3
#
# Usage: ./cleanup_oc2_modules.sh
#
# What it does:
# 1. Auto-detects database credentials from config.php
# 2. Analyzes all OC2 modules in admin/controller/module/
# 3. Categorizes them (SAFE, OBSOLETE, IN USE, UNCERTAIN)
# 4. Interactively asks which to remove
# 5. Creates backup before removal
# 6. Cleans up files and database entries
#
# Works on both local development and production servers!
################################################################################

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

################################################################################
# CONFIGURATION
################################################################################
BACKUP_DIR="oc2_modules_backup_$(date +%Y%m%d_%H%M%S)"
CONFIG_FILE="config.php"

################################################################################
# DO NOT EDIT BELOW THIS LINE
################################################################################

echo -e "${GREEN}======================================${NC}"
echo -e "${GREEN}OC2 Module Cleanup Script${NC}"
echo -e "${GREEN}======================================${NC}"
echo ""

# Check if running from correct directory
if [ ! -f "$CONFIG_FILE" ]; then
    echo -e "${RED}Error: This script must be run from the OpenCart root directory${NC}"
    echo -e "${RED}(The directory containing config.php)${NC}"
    exit 1
fi

# Read database configuration from config.php
echo "Reading database configuration from $CONFIG_FILE..."
DB_HOSTNAME=$(grep "define('DB_HOSTNAME'" "$CONFIG_FILE" | sed "s/.*define('DB_HOSTNAME',[ ]*'\([^']*\)'.*/\1/")
DB_USERNAME=$(grep "define('DB_USERNAME'" "$CONFIG_FILE" | sed "s/.*define('DB_USERNAME',[ ]*'\([^']*\)'.*/\1/")
DB_PASSWORD=$(grep "define('DB_PASSWORD'" "$CONFIG_FILE" | sed "s/.*define('DB_PASSWORD',[ ]*'\([^']*\)'.*/\1/")
DB_DATABASE=$(grep "define('DB_DATABASE'" "$CONFIG_FILE" | sed "s/.*define('DB_DATABASE',[ ]*'\([^']*\)'.*/\1/")
DB_PREFIX=$(grep "define('DB_PREFIX'" "$CONFIG_FILE" | sed "s/.*define('DB_PREFIX',[ ]*'\([^']*\)'.*/\1/")

# Validate configuration was read
if [ -z "$DB_DATABASE" ] || [ -z "$DB_PREFIX" ]; then
    echo -e "${RED}Error: Could not read database configuration from $CONFIG_FILE${NC}"
    exit 1
fi

# Build MySQL command
if [ -z "$DB_PASSWORD" ]; then
    MYSQL_CMD="mysql -u $DB_USERNAME -h $DB_HOSTNAME $DB_DATABASE"
else
    MYSQL_CMD="mysql -u $DB_USERNAME -p$DB_PASSWORD -h $DB_HOSTNAME $DB_DATABASE"
fi

echo -e "${GREEN}Database configuration loaded${NC}"
echo ""

################################################################################
# Step 1: Analyze OC2 Modules
################################################################################
echo -e "${CYAN}======================================${NC}"
echo -e "${CYAN}Step 1: Analyzing OC2 Modules${NC}"
echo -e "${CYAN}======================================${NC}"
echo ""

# Get installed modules from database
INSTALLED_MODULES=$($MYSQL_CMD -sN -e "SELECT code FROM ${DB_PREFIX}extension WHERE type = 'module';" 2>/dev/null)

# Get modules with instances
MODULES_WITH_INSTANCES=$($MYSQL_CMD -sN -e "SELECT DISTINCT code FROM ${DB_PREFIX}module;" 2>/dev/null)

# Arrays to categorize modules
declare -a SAFE_TO_REMOVE
declare -a OBSOLETE_MODULES
declare -a IN_USE_MODULES
declare -a UNCERTAIN_MODULES

# Analyze each OC2 module
OC2_MODULE_DIR="admin/controller/module"
if [ -d "$OC2_MODULE_DIR" ]; then
    for file in $OC2_MODULE_DIR/*.php; do
        if [ ! -f "$file" ]; then
            continue
        fi

        basename="${file##*/}"
        module="${basename%.php}"

        # Skip special files
        if [[ "$module" == "_"* ]]; then
            continue
        fi

        # Check if OC3 version exists
        OC3_FILE="admin/controller/extension/module/${module}.php"
        HAS_OC3_VERSION=false
        if [ -f "$OC3_FILE" ]; then
            HAS_OC3_VERSION=true
        fi

        # Check if installed in database
        IS_INSTALLED=false
        if echo "$INSTALLED_MODULES" | grep -q "^${module}$"; then
            IS_INSTALLED=true
        fi

        # Check if has instances
        HAS_INSTANCES=false
        if echo "$MODULES_WITH_INSTANCES" | grep -q "^${module}$"; then
            HAS_INSTANCES=true
        fi

        # Check for settings
        SETTINGS_COUNT=$($MYSQL_CMD -sN -e "SELECT COUNT(*) FROM ${DB_PREFIX}setting WHERE code = '${module}' OR \`key\` LIKE 'module_${module}%' OR \`key\` LIKE '${module}_%';" 2>/dev/null)

        # Categorize
        if [ "$HAS_OC3_VERSION" = true ] && [ "$IS_INSTALLED" = true ]; then
            # OC3 version exists and is installed - OC2 is obsolete
            OBSOLETE_MODULES+=("$module|OC3 version exists and is active")
        elif [ "$IS_INSTALLED" = true ] || [ "$HAS_INSTANCES" = true ]; then
            # In use but no OC3 version
            IN_USE_MODULES+=("$module|Installed in DB or has instances (settings: $SETTINGS_COUNT)")
        elif [ "$SETTINGS_COUNT" -gt 0 ]; then
            # Has settings but not installed
            UNCERTAIN_MODULES+=("$module|Not installed but has $SETTINGS_COUNT setting(s)")
        elif [ "$HAS_OC3_VERSION" = true ]; then
            # OC3 version exists but neither is installed
            SAFE_TO_REMOVE+=("$module|OC3 version exists, OC2 not needed")
        else
            # Not in DB, no OC3 version, no settings
            SAFE_TO_REMOVE+=("$module|Not in use, no OC3 equivalent")
        fi
    done
fi

# Display analysis results
echo -e "${GREEN}Analysis Complete!${NC}"
echo ""
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${GREEN}✓ SAFE TO REMOVE (${#SAFE_TO_REMOVE[@]} modules)${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
if [ ${#SAFE_TO_REMOVE[@]} -eq 0 ]; then
    echo "  (none)"
else
    for item in "${SAFE_TO_REMOVE[@]}"; do
        module="${item%%|*}"
        reason="${item##*|}"
        echo -e "  • ${GREEN}$module${NC} - $reason"
    done
fi
echo ""

echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${YELLOW}⚠ OBSOLETE - OC3 version exists (${#OBSOLETE_MODULES[@]} modules)${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
if [ ${#OBSOLETE_MODULES[@]} -eq 0 ]; then
    echo "  (none)"
else
    for item in "${OBSOLETE_MODULES[@]}"; do
        module="${item%%|*}"
        reason="${item##*|}"
        echo -e "  • ${YELLOW}$module${NC} - $reason"
    done
fi
echo ""

echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${RED}✗ IN USE - Do NOT remove (${#IN_USE_MODULES[@]} modules)${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
if [ ${#IN_USE_MODULES[@]} -eq 0 ]; then
    echo "  (none)"
else
    for item in "${IN_USE_MODULES[@]}"; do
        module="${item%%|*}"
        reason="${item##*|}"
        echo -e "  • ${RED}$module${NC} - $reason"
    done
fi
echo ""

echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${CYAN}? UNCERTAIN - Manual review needed (${#UNCERTAIN_MODULES[@]} modules)${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
if [ ${#UNCERTAIN_MODULES[@]} -eq 0 ]; then
    echo "  (none)"
else
    for item in "${UNCERTAIN_MODULES[@]}"; do
        module="${item%%|*}"
        reason="${item##*|}"
        echo -e "  • ${CYAN}$module${NC} - $reason"
    done
fi
echo ""

################################################################################
# Step 2: Ask user what to remove
################################################################################
echo -e "${CYAN}======================================${NC}"
echo -e "${CYAN}Step 2: Select Modules to Remove${NC}"
echo -e "${CYAN}======================================${NC}"
echo ""

declare -a MODULES_TO_REMOVE

# Ask about SAFE modules
if [ ${#SAFE_TO_REMOVE[@]} -gt 0 ]; then
    echo -e "${GREEN}Remove all ${#SAFE_TO_REMOVE[@]} SAFE TO REMOVE modules?${NC}"
    read -p "(yes/no): " -r
    if [[ $REPLY =~ ^[Yy][Ee][Ss]$ ]]; then
        for item in "${SAFE_TO_REMOVE[@]}"; do
            MODULES_TO_REMOVE+=("${item%%|*}")
        done
        echo -e "${GREEN}✓ Added ${#SAFE_TO_REMOVE[@]} safe modules to removal list${NC}"
    fi
    echo ""
fi

# Ask about OBSOLETE modules
if [ ${#OBSOLETE_MODULES[@]} -gt 0 ]; then
    echo -e "${YELLOW}Remove all ${#OBSOLETE_MODULES[@]} OBSOLETE modules (OC3 versions exist)?${NC}"
    read -p "(yes/no): " -r
    if [[ $REPLY =~ ^[Yy][Ee][Ss]$ ]]; then
        for item in "${OBSOLETE_MODULES[@]}"; do
            MODULES_TO_REMOVE+=("${item%%|*}")
        done
        echo -e "${GREEN}✓ Added ${#OBSOLETE_MODULES[@]} obsolete modules to removal list${NC}"
    fi
    echo ""
fi

# Ask about UNCERTAIN modules
if [ ${#UNCERTAIN_MODULES[@]} -gt 0 ]; then
    echo -e "${CYAN}Review UNCERTAIN modules one by one?${NC}"
    read -p "(yes/no): " -r
    if [[ $REPLY =~ ^[Yy][Ee][Ss]$ ]]; then
        for item in "${UNCERTAIN_MODULES[@]}"; do
            module="${item%%|*}"
            reason="${item##*|}"
            echo ""
            echo -e "  Module: ${CYAN}$module${NC}"
            echo -e "  Reason: $reason"
            read -p "  Remove this module? (yes/no): " -r
            if [[ $REPLY =~ ^[Yy][Ee][Ss]$ ]]; then
                MODULES_TO_REMOVE+=("$module")
                echo -e "  ${GREEN}✓ Added to removal list${NC}"
            else
                echo -e "  ${YELLOW}⊘ Skipped${NC}"
            fi
        done
    fi
    echo ""
fi

# Check if anything to remove
if [ ${#MODULES_TO_REMOVE[@]} -eq 0 ]; then
    echo -e "${YELLOW}No modules selected for removal. Exiting.${NC}"
    exit 0
fi

# Show summary
echo ""
echo -e "${CYAN}======================================${NC}"
echo -e "${CYAN}Removal Summary${NC}"
echo -e "${CYAN}======================================${NC}"
echo ""
echo -e "${YELLOW}The following ${#MODULES_TO_REMOVE[@]} modules will be removed:${NC}"
for module in "${MODULES_TO_REMOVE[@]}"; do
    echo -e "  • $module"
done
echo ""
echo -e "${YELLOW}A backup will be created in: $BACKUP_DIR${NC}"
echo ""

# Final confirmation
read -p "Proceed with removal? (yes/no): " -r
echo ""
if [[ ! $REPLY =~ ^[Yy][Ee][Ss]$ ]]; then
    echo "Aborted."
    exit 0
fi

################################################################################
# Step 3: Create Backup
################################################################################
echo -e "${CYAN}======================================${NC}"
echo -e "${CYAN}Step 3: Creating Backup${NC}"
echo -e "${CYAN}======================================${NC}"
echo ""

mkdir -p "$BACKUP_DIR"

for module in "${MODULES_TO_REMOVE[@]}"; do
    echo "Backing up: $module"

    # Backup controller
    if [ -f "admin/controller/module/${module}.php" ]; then
        mkdir -p "$BACKUP_DIR/admin/controller/module"
        cp "admin/controller/module/${module}.php" "$BACKUP_DIR/admin/controller/module/"
    fi

    # Backup language files (all languages)
    for langdir in admin/language/*/module; do
        if [ -f "${langdir}/${module}.php" ]; then
            lang=$(echo "$langdir" | cut -d'/' -f3)
            mkdir -p "$BACKUP_DIR/admin/language/$lang/module"
            cp "${langdir}/${module}.php" "$BACKUP_DIR/admin/language/$lang/module/"
        fi
    done

    # Backup view files
    if [ -f "admin/view/template/module/${module}.tpl" ]; then
        mkdir -p "$BACKUP_DIR/admin/view/template/module"
        cp "admin/view/template/module/${module}.tpl" "$BACKUP_DIR/admin/view/template/module/"
    fi
    if [ -f "admin/view/template/module/${module}.twig" ]; then
        mkdir -p "$BACKUP_DIR/admin/view/template/module"
        cp "admin/view/template/module/${module}.twig" "$BACKUP_DIR/admin/view/template/module/"
    fi

    # Backup any model files
    if [ -f "admin/model/module/${module}.php" ]; then
        mkdir -p "$BACKUP_DIR/admin/model/module"
        cp "admin/model/module/${module}.php" "$BACKUP_DIR/admin/model/module/"
    fi
done

# Backup database settings
echo "Backing up database settings..."
for module in "${MODULES_TO_REMOVE[@]}"; do
    $MYSQL_CMD -e "SELECT * FROM ${DB_PREFIX}setting WHERE code = '${module}' OR \`key\` LIKE 'module_${module}%' OR \`key\` LIKE '${module}_%';" >> "$BACKUP_DIR/db_settings.txt" 2>/dev/null
done

echo -e "${GREEN}✓ Backup completed: $BACKUP_DIR${NC}"
echo ""

################################################################################
# Step 4: Remove Files
################################################################################
echo -e "${CYAN}======================================${NC}"
echo -e "${CYAN}Step 4: Removing Files${NC}"
echo -e "${CYAN}======================================${NC}"
echo ""

for module in "${MODULES_TO_REMOVE[@]}"; do
    echo "Removing: $module"

    # Remove controller
    rm -f "admin/controller/module/${module}.php" 2>/dev/null && echo "  ✓ Controller removed"

    # Remove language files
    for langdir in admin/language/*/module; do
        rm -f "${langdir}/${module}.php" 2>/dev/null
    done
    echo "  ✓ Language files removed"

    # Remove view files
    rm -f "admin/view/template/module/${module}.tpl" 2>/dev/null
    rm -f "admin/view/template/module/${module}.twig" 2>/dev/null
    echo "  ✓ View files removed"

    # Remove model files
    rm -f "admin/model/module/${module}.php" 2>/dev/null
    echo "  ✓ Model files removed"
done

echo ""

################################################################################
# Step 5: Clean Database
################################################################################
echo -e "${CYAN}======================================${NC}"
echo -e "${CYAN}Step 5: Cleaning Database${NC}"
echo -e "${CYAN}======================================${NC}"
echo ""

for module in "${MODULES_TO_REMOVE[@]}"; do
    echo "Cleaning database for: $module"

    # Remove from extension table
    $MYSQL_CMD -e "DELETE FROM ${DB_PREFIX}extension WHERE type = 'module' AND code = '${module}';" 2>/dev/null

    # Remove settings
    $MYSQL_CMD -e "DELETE FROM ${DB_PREFIX}setting WHERE code = '${module}' OR \`key\` LIKE 'module_${module}%' OR \`key\` LIKE '${module}_%';" 2>/dev/null

    # Remove module instances
    $MYSQL_CMD -e "DELETE FROM ${DB_PREFIX}module WHERE code = '${module}';" 2>/dev/null

    echo "  ✓ Database cleaned"
done

echo ""

################################################################################
# Step 6: Summary
################################################################################
echo -e "${GREEN}======================================${NC}"
echo -e "${GREEN}Cleanup Complete!${NC}"
echo -e "${GREEN}======================================${NC}"
echo ""
echo -e "Removed ${GREEN}${#MODULES_TO_REMOVE[@]}${NC} OC2 modules"
echo -e "Backup location: ${GREEN}$BACKUP_DIR${NC}"
echo ""
echo -e "${YELLOW}Next steps:${NC}"
echo "1. Clear your OpenCart cache:"
echo "   - Admin Panel: System > Maintenance > Clear Cache"
echo ""
echo "2. Clear modification cache (if using OCMOD/VQMOD):"
echo "   - Admin Panel: Extensions > Modifications > Refresh"
echo ""
echo "3. Check your admin Extensions > Modules page"
echo "   - Verify no 'heading_title' entries remain"
echo "   - All module names should display correctly"
echo ""
echo -e "${YELLOW}To restore if needed:${NC}"
echo "  cp -r $BACKUP_DIR/* ./"
echo ""
