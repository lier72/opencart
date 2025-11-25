# MySQL 5.6 Compatibility - Implementation Summary

## Problem
Production server runs MySQL 5.6 which doesn't support:
- `JSON_OBJECT()` function (introduced in MySQL 5.7)
- `REGEXP_SUBSTR()` function (introduced in MySQL 8.0)

Error encountered:
```
Error: FUNCTION a1627-unqs-oc.JSON_OBJECT does not exist
Error No: 1305
```

## Solution
Created MySQL 5.6 compatible versions of all trigger files using:
- `CONCAT()` to manually build JSON strings instead of `JSON_OBJECT()`
- `SUBSTRING_INDEX()` to extract values instead of `REGEXP_SUBSTR()`
- `REPLACE()` to escape quotes in JSON strings
- `IFNULL()` to handle NULL values properly

## Files Created (MySQL 5.6 Compatible)

All files end with `_mysql56.sql`:

1. `order_triggers_simplified_oc3_mysql56.sql`
2. `order_triggers_simplified_oc2_mysql56.sql`
3. `order_history_triggers_oc3_mysql56.sql`
4. `order_history_triggers_oc2_mysql56.sql`
5. `customer_activity_triggers_oc3_mysql56.sql`
6. `customer_activity_triggers_oc2_mysql56.sql`
7. `odoo_order_map_triggers_oc3_mysql56.sql`
8. `odoo_order_map_triggers_oc2_mysql56.sql`
9. `order_to_sdek_triggers_oc3_mysql56.sql`
10. `order_to_sdek_triggers_oc2_mysql56.sql`
11. `all_triggers_mysql56.sql` (all triggers in one file)

## Key Differences

### JSON Construction

**MySQL 5.7+ (JSON_OBJECT):**
```sql
SET json_data = JSON_OBJECT(
  'order_id', NEW.order_id,
  'firstname', NEW.firstname,
  'email', NEW.email,
  'total', NEW.total
);
```

**MySQL 5.6 (CONCAT):**
```sql
SET json_data = CONCAT(
  '{',
    '"order_id":', IFNULL(NEW.order_id, 'null'), ',',
    '"firstname":"', REPLACE(IFNULL(NEW.firstname, ''), '"', '\\"'), '",',
    '"email":"', REPLACE(IFNULL(NEW.email, ''), '"', '\\"'), '",',
    '"total":', IFNULL(NEW.total, '0'),
  '}'
);
```

### Pattern Extraction

**MySQL 8.0+ (REGEXP_SUBSTR):**
```sql
SET related_order_id = CAST(REGEXP_SUBSTR(NEW.data, '[0-9]+') AS UNSIGNED);
```

**MySQL 5.6 (SUBSTRING_INDEX):**
```sql
-- For format "order_id:12345"
SET related_order_id = CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(NEW.data, ':', 2), ':', -1) AS UNSIGNED);
```

## Installation for Production (MySQL 5.6)

```bash
# Check MySQL version first
mysql -u your_user -p -e "SELECT VERSION();"

# If version is 5.6.x, use these files:

# OC3 Database
mysql -u your_user -p your_oc3_database < install/order_triggers_simplified_oc3_mysql56.sql
mysql -u your_user -p your_oc3_database < install/order_history_triggers_oc3_mysql56.sql
mysql -u your_user -p your_oc3_database < install/customer_activity_triggers_oc3_mysql56.sql
mysql -u your_user -p your_oc3_database < install/odoo_order_map_triggers_oc3_mysql56.sql
mysql -u your_user -p your_oc3_database < install/order_to_sdek_triggers_oc3_mysql56.sql

# OC2 Database
mysql -u your_user -p your_oc2_database < install/order_triggers_simplified_oc2_mysql56.sql
mysql -u your_user -p your_oc2_database < install/order_history_triggers_oc2_mysql56.sql
mysql -u your_user -p your_oc2_database < install/customer_activity_triggers_oc2_mysql56.sql
mysql -u your_user -p your_oc2_database < install/odoo_order_map_triggers_oc2_mysql56.sql
mysql -u your_user -p your_oc2_database < install/order_to_sdek_triggers_oc2_mysql56.sql
```

## Database Configuration

Since you have two databases with different names and passwords, use the configuration file:

1. Copy template:
```bash
cp cli/order_sync_config.php.template cli/order_sync_config.php
```

2. Edit `cli/order_sync_config.php`:
```php
// OC3 Database (can have different credentials)
define('OC3_DB_HOST', '127.0.0.1');
define('OC3_DB_USER', 'your_oc3_user');
define('OC3_DB_PASS', 'your_oc3_password');
define('OC3_DB_NAME', 'your_oc3_database');
define('OC3_DB_PREFIX', 'ocus_');
define('OC3_DB_PORT', 3306);

// OC2 Database (can have different credentials)
define('OC2_DB_HOST', '127.0.0.1');
define('OC2_DB_USER', 'your_oc2_user');
define('OC2_DB_PASS', 'your_oc2_password');
define('OC2_DB_NAME', 'your_oc2_database');
define('OC2_DB_PREFIX', 'ocus_');
define('OC2_DB_PORT', 3306);
```

## Verification

Check triggers are installed:
```sql
-- OC3 (should show 10 triggers)
SELECT TRIGGER_NAME, EVENT_MANIPULATION, EVENT_OBJECT_TABLE
FROM INFORMATION_SCHEMA.TRIGGERS
WHERE TRIGGER_SCHEMA = 'your_oc3_database'
  AND TRIGGER_NAME LIKE '%sync%'
ORDER BY EVENT_OBJECT_TABLE, TRIGGER_NAME;

-- OC2 (should show 10 triggers)
SELECT TRIGGER_NAME, EVENT_MANIPULATION, EVENT_OBJECT_TABLE
FROM INFORMATION_SCHEMA.TRIGGERS
WHERE TRIGGER_SCHEMA = 'your_oc2_database'
  AND TRIGGER_NAME LIKE '%sync%'
ORDER BY EVENT_OBJECT_TABLE, TRIGGER_NAME;
```

Test worker:
```bash
php cli/order_sync_worker.php
```

## Documentation Updated

1. `PRODUCTION_FILES_LIST.txt` - Added MySQL 5.6 file listing and installation instructions
2. `PRODUCTION_ORDER_SYNC_GUIDE.md` - Added MySQL version requirements and installation procedure
3. This file - `MYSQL56_COMPATIBILITY_SUMMARY.md`

## Important Notes

- MySQL 5.6 files work on ALL MySQL versions (5.6, 5.7, 8.x)
- MySQL 5.7+ files only work on MySQL 5.7 and above
- Each database (OC2 and OC3) can have completely different:
  - Server hosts
  - Database names
  - Usernames
  - Passwords
  - Ports
- The `order_sync_config.php` file is excluded from git for security
