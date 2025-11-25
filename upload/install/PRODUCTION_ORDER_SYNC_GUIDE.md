# Production Order Sync Guide - OC2 ↔ OC3

## Overview

This system provides bidirectional order synchronization between OpenCart 2 (OC2) and OpenCart 3 (OC3) using a queue-based worker architecture with order-level sync strategy.

## ⚠ IMPORTANT: MySQL Version Requirements

### MySQL 5.6
**Production servers running MySQL 5.6 MUST use the `*_mysql56.sql` trigger files.**

MySQL 5.6 does not support `JSON_OBJECT()` function. All MySQL 5.6 compatible files use `CONCAT()` to manually build JSON strings and `SUBSTRING_INDEX()` instead of `REGEXP_SUBSTR()`.

### MySQL 5.7+ / 8.x
Can use either regular `.sql` files OR `*_mysql56.sql` files (both work).

### Check Your MySQL Version
```sql
SELECT VERSION();
```

If output shows `5.6.x`, use MySQL 5.6 files.
If output shows `5.7.x` or `8.x.x`, use regular files.

## Architecture

### Key Components

1. **Database Triggers** - Capture order changes and queue them
2. **Sync Queue Table** (`ocus_order_sync_queue`) - Stores pending sync operations
3. **Sync Log Table** (`ocus_order_sync_log`) - Audit trail of completed sync operations
4. **Worker Script** - Processes queue and syncs data between databases
5. **Order Mapping Table** (`ocus_order_id_map`) - Tracks order relationships between OC2 and OC3

### Sync Strategy: Order-Level Sync

Instead of syncing each table modification individually, we sync **complete orders**:
- Triggers only on `order` and `order_history` tables
- Worker fetches ALL related data (products, options, totals) from source
- Worker syncs everything in one transaction
- **Result**: 96.6% fewer queue entries

## Active Database Triggers

### OC3 Triggers
- `ocus_order_after_insert_sync` - Queue order creation
- `ocus_order_after_update_sync` - Queue order updates
- `ocus_order_history_after_insert_sync` - Queue history entries
- `ocus_order_history_after_update_sync` - Queue history updates
- `ocus_order_history_after_delete_sync` - Queue history deletions
- `ocus_customer_activity_after_insert_sync` - Queue customer activities
- `ocus_odoo_order_map_after_insert_sync` - Queue Odoo mappings
- `ocus_odoo_order_map_after_update_sync` - Queue Odoo mapping updates
- `ocus_order_to_sdek_after_insert_sync` - Queue SDEK data
- `ocus_order_to_sdek_after_update_sync` - Queue SDEK updates

### OC2 Triggers
- `ocus_order_after_insert_sync` - Queue order creation
- `ocus_order_after_update_sync` - Queue order updates
- `ocus_order_history_after_insert_sync_oc2` - Queue history entries
- `ocus_order_history_after_update_sync_oc2` - Queue history updates
- `ocus_order_history_after_delete_sync_oc2` - Queue history deletions
- `ocus_customer_activity_after_insert_sync` - Queue customer activities
- `ocus_odoo_order_map_after_insert_sync` - Queue Odoo mappings
- `ocus_odoo_order_map_after_update_sync` - Queue Odoo mapping updates
- `ocus_order_to_sdek_after_insert_sync` - Queue SDEK data
- `ocus_order_to_sdek_after_update_sync` - Queue SDEK updates

## Production Files

### Required SQL Files (in /install directory)

**For MySQL 5.6:**
```
order_triggers_simplified_oc3_mysql56.sql      # Order table triggers for OC3
order_triggers_simplified_oc2_mysql56.sql      # Order table triggers for OC2
order_history_triggers_oc3_mysql56.sql         # Order history triggers for OC3
order_history_triggers_oc2_mysql56.sql         # Order history triggers for OC2
customer_activity_triggers_oc3_mysql56.sql     # Customer activity triggers for OC3
customer_activity_triggers_oc2_mysql56.sql     # Customer activity triggers for OC2
odoo_order_map_triggers_oc3_mysql56.sql        # Odoo integration triggers for OC3
odoo_order_map_triggers_oc2_mysql56.sql        # Odoo integration triggers for OC2
order_to_sdek_triggers_oc3_mysql56.sql         # CDEK shipping triggers for OC3
order_to_sdek_triggers_oc2_mysql56.sql         # CDEK shipping triggers for OC2
all_triggers_mysql56.sql                       # ALL triggers in one file (optional)
drop_all_sync_triggers.sql                     # Script to remove ALL triggers
drop_related_table_triggers.sql                # Script to remove old triggers
```

**For MySQL 5.7+ / 8.x:**
```
order_triggers_simplified_oc3.sql        # Order table triggers for OC3
order_triggers_simplified_oc2.sql        # Order table triggers for OC2
order_history_triggers_oc3.sql           # Order history triggers for OC3
order_history_triggers_oc2.sql           # Order history triggers for OC2
customer_activity_triggers_oc3.sql       # Customer activity triggers for OC3
customer_activity_triggers_oc2.sql       # Customer activity triggers for OC2
odoo_order_map_triggers_oc3.sql          # Odoo integration triggers for OC3
odoo_order_map_triggers_oc2.sql          # Odoo integration triggers for OC2
order_to_sdek_triggers_oc3.sql           # CDEK shipping triggers for OC3
order_to_sdek_triggers_oc2.sql           # CDEK shipping triggers for OC2
drop_related_table_triggers.sql          # Script to remove old triggers
```

### Required PHP Files
```
cli/order_sync_worker.php                # Main sync worker
cli/order_sync_config.php                # Database configuration (create from template)
cli/order_sync_config.php.template       # Configuration template
cli/order_sync_worker.php.backup_*       # Backups (for rollback)
```

### Documentation
```
install/PRODUCTION_ORDER_SYNC_GUIDE.md   # This file
install/OPTIMIZED_SYNC_STRATEGY.md       # Technical implementation details
install/ORDER_LEVEL_SYNC_IMPLEMENTED.md  # Implementation summary
```

### Archived Files
All obsolete files moved to:
```
install/archive_obsolete_sync_files/     # Old implementation files
```

## Installation Procedure

### Step 1: Check MySQL Version
```sql
SELECT VERSION();
```

If version is `5.6.x`, proceed with MySQL 5.6 files.
If version is `5.7.x` or `8.x.x`, use regular files.

### Step 2: Install Database Triggers

**For MySQL 5.6 (Production):**
```bash
# Run these SQL files on OC3 database
mysql -u your_user -p your_oc3_database < install/order_triggers_simplified_oc3_mysql56.sql
mysql -u your_user -p your_oc3_database < install/order_history_triggers_oc3_mysql56.sql
mysql -u your_user -p your_oc3_database < install/customer_activity_triggers_oc3_mysql56.sql
mysql -u your_user -p your_oc3_database < install/odoo_order_map_triggers_oc3_mysql56.sql
mysql -u your_user -p your_oc3_database < install/order_to_sdek_triggers_oc3_mysql56.sql

# Run these SQL files on OC2 database
mysql -u your_user -p your_oc2_database < install/order_triggers_simplified_oc2_mysql56.sql
mysql -u your_user -p your_oc2_database < install/order_history_triggers_oc2_mysql56.sql
mysql -u your_user -p your_oc2_database < install/customer_activity_triggers_oc2_mysql56.sql
mysql -u your_user -p your_oc2_database < install/odoo_order_map_triggers_oc2_mysql56.sql
mysql -u your_user -p your_oc2_database < install/order_to_sdek_triggers_oc2_mysql56.sql

# Cleanup old triggers (run once per database)
mysql -u your_user -p your_oc3_database < install/drop_related_table_triggers.sql
mysql -u your_user -p your_oc2_database < install/drop_related_table_triggers.sql
```

**For MySQL 5.7+ / 8.x (Development):**
```bash
# Run these SQL files on OC3 database
mysql -u root a1627-unqs-oc3 < install/order_triggers_simplified_oc3.sql
mysql -u root a1627-unqs-oc3 < install/order_history_triggers_oc3.sql
mysql -u root a1627-unqs-oc3 < install/customer_activity_triggers_oc3.sql
mysql -u root a1627-unqs-oc3 < install/odoo_order_map_triggers_oc3.sql
mysql -u root a1627-unqs-oc3 < install/order_to_sdek_triggers_oc3.sql

# Run these SQL files on OC2 database
mysql -u root a1627-unqs-oc < install/order_triggers_simplified_oc2.sql
mysql -u root a1627-unqs-oc < install/order_history_triggers_oc2.sql
mysql -u root a1627-unqs-oc < install/customer_activity_triggers_oc2.sql
mysql -u root a1627-unqs-oc < install/odoo_order_map_triggers_oc2.sql
mysql -u root a1627-unqs-oc < install/order_to_sdek_triggers_oc2.sql

# Cleanup old triggers (run once per database)
mysql -u root a1627-unqs-oc3 < install/drop_related_table_triggers.sql
mysql -u root a1627-unqs-oc < install/drop_related_table_triggers.sql
```

### Step 3: Configure Worker

1. Copy template:
```bash
cp cli/order_sync_config.php.template cli/order_sync_config.php
```

2. Edit `cli/order_sync_config.php` with your database credentials
3. **IMPORTANT**: Each database (OC2 and OC3) can have different:
   - Database hosts (servers)
   - Database names
   - Usernames
   - Passwords
   - Ports

### Step 4: Verify Installation

```sql
-- Check triggers installed in OC3 (should show 10 triggers)
SELECT TRIGGER_NAME, EVENT_MANIPULATION, EVENT_OBJECT_TABLE
FROM INFORMATION_SCHEMA.TRIGGERS
WHERE TRIGGER_SCHEMA = 'your_oc3_database'
  AND TRIGGER_NAME LIKE '%sync%'
ORDER BY EVENT_OBJECT_TABLE, TRIGGER_NAME;

-- Check triggers installed in OC2 (should show 10 triggers)
SELECT TRIGGER_NAME, EVENT_MANIPULATION, EVENT_OBJECT_TABLE
FROM INFORMATION_SCHEMA.TRIGGERS
WHERE TRIGGER_SCHEMA = 'your_oc2_database'
  AND TRIGGER_NAME LIKE '%sync%'
ORDER BY EVENT_OBJECT_TABLE, TRIGGER_NAME;
```

### Step 5: Test Worker
```bash
php cli/order_sync_worker.php
```

Should output:
```
Order Sync Worker - Processing queue...
No pending items to sync.
Worker completed.
```

## Database Schema

### Sync Queue Table Structure
```sql
CREATE TABLE `ocus_order_sync_queue` (
  `id` int NOT NULL AUTO_INCREMENT,
  `source_db` enum('oc2','oc3') NOT NULL,
  `table_name` varchar(64) NOT NULL,
  `operation` enum('INSERT','UPDATE','DELETE') NOT NULL,
  `record_id` int NOT NULL,
  `parent_order_id` int NOT NULL,
  `data_json` longtext NOT NULL,
  `sync_status` enum('pending','synced','error','skip','cancelled','superseded') DEFAULT 'pending',
  `sync_ready` tinyint(1) DEFAULT '1',
  `error_message` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `synced_at` datetime DEFAULT NULL,
  `retry_count` int DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `sync_status` (`sync_status`),
  KEY `parent_order_id` (`parent_order_id`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Queue Status Values
- `pending` - Waiting to be synced
- `synced` - Successfully synchronized
- `error` - Failed to sync (check error_message)
- `skip` - Manually skipped
- `cancelled` - Auto-cancelled (debouncing)
- `superseded` - Replaced by newer entry

### Sync Log Table Structure
```sql
CREATE TABLE `ocus_order_sync_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sync_queue_id` int DEFAULT NULL,
  `operation` varchar(64) NOT NULL,
  `source_db` enum('oc2','oc3') NOT NULL,
  `target_db` enum('oc2','oc3') NOT NULL,
  `order_id_source` int DEFAULT NULL,
  `order_id_target` int DEFAULT NULL,
  `status` enum('success','error','warning') NOT NULL,
  `message` text,
  `execution_time_ms` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_queue_id` (`sync_queue_id`),
  KEY `idx_created` (`created_at`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Detailed sync operation logs';
```

### Log Table Purpose
The `ocus_order_sync_log` table provides detailed audit trail of all sync operations:
- **Performance tracking**: Records execution time for each operation
- **Error debugging**: Captures detailed error messages
- **Audit trail**: Complete history of what was synced and when
- **Monitoring**: Query by status to find failed operations

### Order ID Mapping Table Structure
```sql
CREATE TABLE `ocus_order_id_map` (
  `id` int NOT NULL AUTO_INCREMENT,
  `oc2_order_id` int DEFAULT NULL COMMENT 'Order ID in OC2 database',
  `oc3_order_id` int DEFAULT NULL COMMENT 'Order ID in OC3 database',
  `last_synced_from` enum('oc2','oc3') NOT NULL COMMENT 'Last sync direction',
  `last_synced_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `sync_enabled` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Enable/disable sync for this order',
  `sync_lock` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Prevents infinite loop during sync',
  `date_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `notes` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_oc2_order` (`oc2_order_id`),
  UNIQUE KEY `idx_oc3_order` (`oc3_order_id`),
  KEY `idx_synced_from` (`last_synced_from`,`last_synced_at`),
  KEY `idx_sync_lock` (`sync_lock`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Maps order IDs between OC2 and OC3';
```

### Order Mapping Table Purpose
The `ocus_order_id_map` table tracks the relationship between orders in both databases:
- **Location**: Maintained in OC3 database only (worker reads from OC3)
- **ID Mapping**: Links OC2 order IDs to OC3 order IDs (usually same ID)
- **Sync Direction**: Records which database the order was last synced from
- **Circular Prevention**: Uses `sync_lock` flag to prevent infinite sync loops
- **Sync Control**: Allows disabling sync for specific orders via `sync_enabled`
- **Audit**: Tracks when each order was last synced

**Note**: The table structure exists in both databases but only OC3's table is actively used.

## Running the Worker

### Manual Execution
```bash
/usr/local/opt/php@7.3/bin/php /Users/max/Sites/opencart/upload/cli/order_sync_worker.php
```

### Cron Job Setup
Add to crontab for automatic syncing every minute:
```bash
* * * * * /usr/local/opt/php@7.3/bin/php /Users/max/Sites/opencart/upload/cli/order_sync_worker.php >> /Users/max/Sites/opencart/upload/logs/order_sync.log 2>&1
```

Or every 5 minutes:
```bash
*/5 * * * * /usr/local/opt/php@7.3/bin/php /Users/max/Sites/opencart/upload/cli/order_sync_worker.php >> /Users/max/Sites/opencart/upload/logs/order_sync.log 2>&1
```

### Worker Configuration

**Configuration File**: `cli/order_sync_config.php`

**Setup Steps**:
1. Copy template: `cp cli/order_sync_config.php.template cli/order_sync_config.php`
2. Edit `order_sync_config.php` with your database credentials
3. Each database (OC2 and OC3) can have different credentials

**OC3 Database Settings**:
```php
define('OC3_DB_HOST', '127.0.0.1');      // Database host
define('OC3_DB_USER', 'your_username');  // Database user
define('OC3_DB_PASS', 'your_password');  // Database password
define('OC3_DB_NAME', 'a1627-unqs-oc3'); // Database name
define('OC3_DB_PREFIX', 'ocus_');        // Table prefix
define('OC3_DB_PORT', 3306);             // Database port
```

**OC2 Database Settings**:
```php
define('OC2_DB_HOST', '127.0.0.1');      // Can be different server
define('OC2_DB_USER', 'your_username');  // Can be different user
define('OC2_DB_PASS', 'your_password');  // Can be different password
define('OC2_DB_NAME', 'a1627-unqs-oc');  // Database name
define('OC2_DB_PREFIX', 'ocus_');        // Table prefix
define('OC2_DB_PORT', 3306);             // Database port
```

**Security Notes**:
- Keep `order_sync_config.php` secure and outside public web directory
- Use strong, unique passwords for each database
- Consider creating dedicated sync users with minimal permissions
- The config file is excluded from git via `.gitignore`

## Monitoring & Maintenance

### Check Queue Status
```sql
-- Overall queue health
SELECT source_db, sync_status, COUNT(*) as count
FROM ocus_order_sync_queue
GROUP BY source_db, sync_status;

-- Pending items
SELECT COUNT(*) as pending_count
FROM ocus_order_sync_queue
WHERE sync_status = 'pending';

-- Recent errors
SELECT * FROM ocus_order_sync_queue
WHERE sync_status = 'error'
ORDER BY created_at DESC
LIMIT 10;
```

### Check Specific Order
```sql
SELECT table_name, operation, sync_status, created_at
FROM ocus_order_sync_queue
WHERE parent_order_id = <ORDER_ID>
ORDER BY id;
```

### Check Sync Logs
```sql
-- Recent sync operations
SELECT id, operation, source_db, target_db, order_id_source,
       status, execution_time_ms, created_at
FROM ocus_order_sync_log
ORDER BY created_at DESC
LIMIT 20;

-- Failed sync operations
SELECT id, operation, order_id_source, message, created_at
FROM ocus_order_sync_log
WHERE status = 'error'
ORDER BY created_at DESC
LIMIT 10;

-- Performance metrics
SELECT operation,
       COUNT(*) as operations,
       AVG(execution_time_ms) as avg_time_ms,
       MAX(execution_time_ms) as max_time_ms
FROM ocus_order_sync_log
WHERE status = 'success'
  AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
GROUP BY operation;

-- Sync logs for specific order
SELECT id, operation, status, message, execution_time_ms, created_at
FROM ocus_order_sync_log
WHERE order_id_source = <ORDER_ID> OR order_id_target = <ORDER_ID>
ORDER BY created_at;
```

### Check Order Mappings
```sql
-- View all order mappings
SELECT id, oc2_order_id, oc3_order_id, last_synced_from,
       last_synced_at, sync_enabled, sync_lock
FROM ocus_order_id_map
ORDER BY last_synced_at DESC
LIMIT 20;

-- Check mapping for specific order
SELECT * FROM ocus_order_id_map
WHERE oc2_order_id = <ORDER_ID> OR oc3_order_id = <ORDER_ID>;

-- Find orders with sync disabled
SELECT oc2_order_id, oc3_order_id, notes
FROM ocus_order_id_map
WHERE sync_enabled = 0;

-- Check for stuck sync locks (should be 0)
SELECT id, oc2_order_id, oc3_order_id, last_synced_at
FROM ocus_order_id_map
WHERE sync_lock = 1;
```

### Cleanup Old Entries
```sql
-- Remove synced queue entries older than 30 days
DELETE FROM ocus_order_sync_queue
WHERE sync_status = 'synced'
  AND synced_at < DATE_SUB(NOW(), INTERVAL 30 DAY);

-- Remove cancelled/superseded queue entries older than 7 days
DELETE FROM ocus_order_sync_queue
WHERE sync_status IN ('cancelled', 'superseded')
  AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY);

-- Remove successful log entries older than 90 days
DELETE FROM ocus_order_sync_log
WHERE status = 'success'
  AND created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);

-- Keep error logs indefinitely for debugging (or set custom retention)
-- DELETE FROM ocus_order_sync_log
-- WHERE status = 'error'
--   AND created_at < DATE_SUB(NOW(), INTERVAL 180 DAY);
```

## Performance Metrics

### Before Optimization
- Queue entries per order: ~773
- Worker runs needed: 40+
- Sync time: ~5 minutes

### After Optimization
- Queue entries per order: ~26 (96.6% reduction)
- Worker runs needed: 1-2
- Sync time: <1 second

## Troubleshooting

### Problem: Items not syncing
**Check:**
1. Worker is running: `ps aux | grep order_sync_worker`
2. Queue has pending items: Check query above
3. Database connections working
4. Check error_message in failed items

### Problem: Duplicate orders
**Solution:**
Check `ocus_order_id_map` table for proper mappings:
```sql
SELECT * FROM ocus_order_id_map WHERE oc2_order_id = <ORDER_ID>;
```

### Problem: Circular sync loop
**Check:**
Should have 0 reverse sync entries. The `@sync_in_progress` flag prevents this:
```sql
-- Should be 0 for orders synced FROM OC2
SELECT COUNT(*) FROM ocus_order_sync_queue
WHERE parent_order_id = <ORDER_ID>
  AND source_db = 'oc3';
```

### Problem: Related tables not syncing
**Check:**
Worker should log "Syncing related tables". If not, verify:
1. Order sync completed successfully
2. Worker has latest code with `syncCompleteOrderRelatedTables()` method

## Rollback Instructions

If needed to rollback to previous version:

```bash
# Restore old worker
cp /Users/max/Sites/opencart/upload/cli/order_sync_worker.php.backup_before_order_level_sync \
   /Users/max/Sites/opencart/upload/cli/order_sync_worker.php

# Re-apply old triggers (from archive)
mysql -u root -D a1627-unqs-oc3 < install/archive_obsolete_sync_files/order_related_tables_triggers_debounced_oc3.sql
mysql -u root -D a1627-unqs-oc < install/archive_obsolete_sync_files/order_related_tables_triggers_debounced_oc2.sql
```

## Security Features

1. **SQL Injection Protection**: All queries use proper escaping
2. **Sync Lock**: `@sync_in_progress` prevents circular loops
3. **Transaction Safety**: Syncs wrapped in transactions
4. **Error Handling**: Failed syncs marked with error status
5. **Audit Trail**: Complete history in queue table

## Support

For issues or questions:
1. Check logs: `/Users/max/Sites/opencart/upload/logs/order_sync.log`
2. Review queue errors: Query sync_status = 'error'
3. Check this documentation
4. Review archived implementation docs in `/install/archive_obsolete_sync_files/`

## Version History

- **v3.0** (2025-11-10): Order-level sync implementation, 96.6% performance improvement
- **v2.0**: Debouncing + consolidation strategy (archived)
- **v1.0**: Initial bidirectional sync implementation (archived)
