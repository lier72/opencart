#!/bin/bash

# Simple script to restore database settings from backup

BACKUP_DIR=${1:-$(ls -dt oc2_modules_backup_* 2>/dev/null | head -1)}

if [ -z "$BACKUP_DIR" ] || [ ! -f "$BACKUP_DIR/db_settings.txt" ]; then
    echo "Error: No backup found"
    echo "Usage: $0 [backup_directory]"
    exit 1
fi

# Read DB config
DB_HOSTNAME=$(grep "define('DB_HOSTNAME'" config.php | sed "s/.*'\([^']*\)'.*/\1/")
DB_USERNAME=$(grep "define('DB_USERNAME'" config.php | sed "s/.*'\([^']*\)'.*/\1/")
DB_PASSWORD=$(grep "define('DB_PASSWORD'" config.php | sed "s/.*'\([^']*\)'.*/\1/")
DB_DATABASE=$(grep "define('DB_DATABASE'" config.php | sed "s/.*'\([^']*\)'.*/\1/")
DB_PREFIX=$(grep "define('DB_PREFIX'" config.php | sed "s/.*'\([^']*\)'.*/\1/")

if [ -z "$DB_PASSWORD" ]; then
    MYSQL_CMD="mysql -u $DB_USERNAME -h $DB_HOSTNAME $DB_DATABASE"
else
    MYSQL_CMD="mysql -u $DB_USERNAME -p$DB_PASSWORD -h $DB_HOSTNAME $DB_DATABASE"
fi

echo "Restoring database settings from: $BACKUP_DIR"
echo ""

RESTORED=0
while IFS=$'\t' read -r setting_id store_id code key value serialized; do
    [ "$setting_id" = "setting_id" ] && continue
    [ -z "$setting_id" ] && continue
    
    EXISTS=$($MYSQL_CMD -sN -e "SELECT COUNT(*) FROM ${DB_PREFIX}setting WHERE setting_id = $setting_id;" 2>/dev/null)
    
    if [ "$EXISTS" = "0" ]; then
        value_escaped=$(echo "$value" | sed "s/'/''/g")
        $MYSQL_CMD -e "INSERT INTO ${DB_PREFIX}setting (setting_id, store_id, code, \`key\`, value, serialized) VALUES ($setting_id, $store_id, '$code', '$key', '$value_escaped', $serialized);" 2>/dev/null
        echo "✓ Restored: $key"
        RESTORED=$((RESTORED + 1))
    fi
done < "$BACKUP_DIR/db_settings.txt"

echo ""
echo "Restored $RESTORED settings"
