#!/bin/bash

################################################################################
# SEOMatic Removal Script
# This script safely removes the SEOMatic module from OpenCart 3.x
#
# Usage: ./remove_seomatic.sh
#
# What it does:
# 1. Auto-detects database credentials from config.php
# 2. Creates a backup of all SEOMatic files
# 3. Removes SEOMatic directories and files
# 4. Cleans up database settings
# 5. Verifies removal
#
# Works on both local development and production servers!
################################################################################

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

################################################################################
# CONFIGURATION
################################################################################
# Database credentials are auto-detected from config.php
# Backup configuration
BACKUP_DIR="seomatic_backup_$(date +%Y%m%d_%H%M%S)"
CONFIG_FILE="config.php"

################################################################################
# DO NOT EDIT BELOW THIS LINE
################################################################################

echo -e "${GREEN}================================${NC}"
echo -e "${GREEN}SEOMatic Removal Script${NC}"
echo -e "${GREEN}================================${NC}"
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

# Build MySQL command with or without password
if [ -z "$DB_PASSWORD" ]; then
    MYSQL_CMD="mysql -u $DB_USERNAME -h $DB_HOSTNAME $DB_DATABASE"
else
    MYSQL_CMD="mysql -u $DB_USERNAME -p$DB_PASSWORD -h $DB_HOSTNAME $DB_DATABASE"
fi

# Display configuration
echo -e "${GREEN}Database configuration loaded:${NC}"
echo "  Database: $DB_DATABASE"
echo "  Host: $DB_HOSTNAME"
echo "  User: $DB_USERNAME"
echo "  Prefix: $DB_PREFIX"
echo "  Backup Dir: $BACKUP_DIR"
echo ""

# Confirm before proceeding
echo -e "${YELLOW}This will remove SEOMatic module from your OpenCart installation.${NC}"
echo -e "${YELLOW}A backup will be created in: $BACKUP_DIR${NC}"
echo ""
read -p "Do you want to continue? (yes/no): " -r
echo ""
if [[ ! $REPLY =~ ^[Yy][Ee][Ss]$ ]]; then
    echo "Aborted."
    exit 0
fi

################################################################################
# Step 1: Create Backup
################################################################################
echo -e "${GREEN}Step 1: Creating backup...${NC}"
mkdir -p "$BACKUP_DIR"

# Array of directories to backup and remove
DIRECTORIES=(
    "catalog/controller/seomatic"
    "catalog/model/seomatic"
    "admin/language/english/seomatic"
    "admin/controller/seomatic"
    "admin/model/seomatic"
    "admin/view/stylesheet/seomatic"
    "admin/view/javascript/seomatic"
    "system/helper/seomatic"
)

# Array of individual files to backup and remove
FILES=(
    "admin/language/english/module/seomatic.php"
    "admin/controller/module/seomatic.php"
    "admin/view/image/seomatic-loading-large.gif"
)

# Backup directories
for dir in "${DIRECTORIES[@]}"; do
    if [ -d "$dir" ]; then
        echo "  Backing up directory: $dir"
        mkdir -p "$BACKUP_DIR/$(dirname "$dir")"
        cp -r "$dir" "$BACKUP_DIR/$dir"
    else
        echo "  Directory not found (skipping): $dir"
    fi
done

# Backup individual files
for file in "${FILES[@]}"; do
    if [ -f "$file" ]; then
        echo "  Backing up file: $file"
        mkdir -p "$BACKUP_DIR/$(dirname "$file")"
        cp "$file" "$BACKUP_DIR/$file"
    else
        echo "  File not found (skipping): $file"
    fi
done

# Backup database settings
echo "  Backing up database settings..."
$MYSQL_CMD -e "SELECT * FROM ${DB_PREFIX}setting WHERE \`key\` LIKE '%seomatic%';" > "$BACKUP_DIR/seomatic_db_settings.txt" 2>/dev/null

# Create SQL restore script
echo "  Creating database restore script..."
cat > "$BACKUP_DIR/restore_db_settings.sql" << 'EOF'
-- SEOMatic Database Restore Script
-- Run this file to restore SEOMatic database settings if needed
-- Usage: mysql -u root -p database_name < restore_db_settings.sql

EOF

$MYSQL_CMD -e "SELECT CONCAT('INSERT INTO ${DB_PREFIX}setting (setting_id, store_id, code, \`key\`, value, serialized) VALUES (', setting_id, ', ', store_id, ', \"', code, '\", \"', \`key\`, '\", \"', REPLACE(value, '\"', '\\\\\"'), '\", ', serialized, ');') FROM ${DB_PREFIX}setting WHERE \`key\` LIKE '%seomatic%';" 2>/dev/null >> "$BACKUP_DIR/restore_db_settings.sql"

if [ $? -eq 0 ]; then
    echo -e "${GREEN}  Backup created successfully${NC}"
else
    echo -e "${YELLOW}  Warning: Could not backup database${NC}"
    echo -e "${YELLOW}  Please check your database credentials in the script${NC}"
    read -p "Continue anyway? (yes/no): " -r
    if [[ ! $REPLY =~ ^[Yy][Ee][Ss]$ ]]; then
        echo "Aborted."
        exit 1
    fi
fi

echo -e "${GREEN}Backup completed: $BACKUP_DIR${NC}"
echo ""

################################################################################
# Step 2: Remove Files
################################################################################
echo -e "${GREEN}Step 2: Removing SEOMatic files...${NC}"

# Remove directories
for dir in "${DIRECTORIES[@]}"; do
    if [ -d "$dir" ]; then
        echo "  Removing directory: $dir"
        rm -rf "$dir"
        if [ ! -d "$dir" ]; then
            echo -e "    ${GREEN}✓ Removed${NC}"
        else
            echo -e "    ${RED}✗ Failed to remove${NC}"
        fi
    fi
done

# Remove individual files
for file in "${FILES[@]}"; do
    if [ -f "$file" ]; then
        echo "  Removing file: $file"
        rm -f "$file"
        if [ ! -f "$file" ]; then
            echo -e "    ${GREEN}✓ Removed${NC}"
        else
            echo -e "    ${RED}✗ Failed to remove${NC}"
        fi
    fi
done

echo ""

################################################################################
# Step 3: Clean Database
################################################################################
echo -e "${GREEN}Step 3: Cleaning database...${NC}"

# Remove from extension table
echo "  Removing from extension table..."
$MYSQL_CMD -e "DELETE FROM ${DB_PREFIX}extension WHERE code = 'seomatic';" 2>/dev/null
ROWS_DELETED=$($MYSQL_CMD -sN -e "SELECT ROW_COUNT();" 2>/dev/null)
if [ "$ROWS_DELETED" != "" ]; then
    echo -e "    ${GREEN}✓ Removed $ROWS_DELETED row(s)${NC}"
fi

# Remove settings
echo "  Removing settings..."
$MYSQL_CMD -e "DELETE FROM ${DB_PREFIX}setting WHERE \`key\` LIKE '%seomatic%';" 2>/dev/null
ROWS_DELETED=$($MYSQL_CMD -sN -e "SELECT ROW_COUNT();" 2>/dev/null)
if [ "$ROWS_DELETED" != "" ]; then
    echo -e "    ${GREEN}✓ Removed $ROWS_DELETED row(s)${NC}"
fi

# Remove modules
echo "  Removing module instances..."
$MYSQL_CMD -e "DELETE FROM ${DB_PREFIX}module WHERE code = 'seomatic';" 2>/dev/null
ROWS_DELETED=$($MYSQL_CMD -sN -e "SELECT ROW_COUNT();" 2>/dev/null)
if [ "$ROWS_DELETED" != "" ]; then
    echo -e "    ${GREEN}✓ Removed $ROWS_DELETED row(s)${NC}"
fi

# Check for any remaining seomatic data
REMAINING=$($MYSQL_CMD -sN -e "SELECT COUNT(*) FROM ${DB_PREFIX}setting WHERE \`key\` LIKE '%seomatic%';" 2>/dev/null)

if [ "$REMAINING" = "0" ]; then
    echo -e "${GREEN}  Database cleaned successfully${NC}"
else
    echo -e "${YELLOW}  Warning: $REMAINING seomatic setting(s) still remain${NC}"
    echo -e "${YELLOW}  You may need to manually check the database${NC}"
fi

echo ""

################################################################################
# Step 4: Verification
################################################################################
echo -e "${GREEN}Step 4: Verifying removal...${NC}"

FAILED=0

# Check directories
for dir in "${DIRECTORIES[@]}"; do
    if [ -d "$dir" ]; then
        echo -e "  ${RED}✗ Directory still exists: $dir${NC}"
        FAILED=1
    fi
done

# Check files
for file in "${FILES[@]}"; do
    if [ -f "$file" ]; then
        echo -e "  ${RED}✗ File still exists: $file${NC}"
        FAILED=1
    fi
done

# Check database
DB_COUNT=$($MYSQL_CMD -sN -e "SELECT COUNT(*) FROM ${DB_PREFIX}setting WHERE \`key\` LIKE '%seomatic%';" 2>/dev/null)
if [ "$DB_COUNT" != "0" ] && [ "$DB_COUNT" != "" ]; then
    echo -e "  ${YELLOW}⚠ Database still contains $DB_COUNT seomatic setting(s)${NC}"
fi

if [ $FAILED -eq 0 ]; then
    echo -e "${GREEN}  ✓ All SEOMatic files successfully removed!${NC}"
else
    echo -e "${YELLOW}  ⚠ Some files could not be removed. Check permissions.${NC}"
fi

echo ""
echo -e "${GREEN}================================${NC}"
echo -e "${GREEN}Removal Complete!${NC}"
echo -e "${GREEN}================================${NC}"
echo ""
echo -e "Backup location: ${GREEN}$BACKUP_DIR${NC}"
echo ""
echo -e "${YELLOW}Next steps:${NC}"
echo "1. Clear your OpenCart cache:"
echo "   - Admin Panel: System > Maintenance > Clear Cache"
echo "   - Or manually delete: system/storage/cache/*"
echo ""
echo "2. Clear modification cache (if using OCMOD/VQMOD):"
echo "   - Admin Panel: Extensions > Modifications > Refresh"
echo ""
echo "3. Test your admin panel to ensure no errors"
echo ""
echo -e "${YELLOW}To restore if needed:${NC}"
echo "  # Restore files:"
echo "  cp -r $BACKUP_DIR/* ./"
echo ""
echo "  # Restore database (if needed):"
echo "  mysql -u $DB_USERNAME -p -h $DB_HOSTNAME $DB_DATABASE < $BACKUP_DIR/restore_db_settings.sql"
echo ""
echo -e "${GREEN}Log saved to: remove_seomatic.log${NC}"
echo ""
