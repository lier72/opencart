# FEDERATED Storage Engine Analysis

## Current Status

**MySQL Version**: 8.3.0 (Homebrew)
**FEDERATED Engine**: Installed but DISABLED

## Tables That Need Sharing

Based on current sync triggers, these 5 tables need to be shared between OC2 and OC3:

1. `ocus_order` - Main order table
2. `ocus_order_product` - Order line items
3. `ocus_order_option` - Product options in orders
4. `ocus_order_total` - Order totals (subtotal, shipping, tax, discount, etc.)
5. `ocus_order_history` - Order status change history

## How FEDERATED Would Work

### Architecture
```
OC3 Database (a1627-unqs-oc3)
├─ ocus_order (REAL TABLE - InnoDB)
├─ ocus_order_product (REAL TABLE - InnoDB)
├─ ocus_order_option (REAL TABLE - InnoDB)
├─ ocus_order_total (REAL TABLE - InnoDB)
└─ ocus_order_history (REAL TABLE - InnoDB)

OC2 Database (a1627-unqs-oc)
├─ ocus_order (FEDERATED → points to OC3)
├─ ocus_order_product (FEDERATED → points to OC3)
├─ ocus_order_option (FEDERATED → points to OC3)
├─ ocus_order_total (FEDERATED → points to OC3)
└─ ocus_order_history (FEDERATED → points to OC3)
```

### What This Means
- OC3 contains the **actual physical data**
- OC2 tables are **virtual pointers** to OC3's tables
- Both systems read/write the **same data**
- No sync needed - changes are instant

## Enabling FEDERATED Engine

### Step 1: Add to MySQL Config
Add to `/usr/local/etc/my.cnf`:
```ini
[mysqld]
federated
```

### Step 2: Restart MySQL
```bash
brew services restart mysql
```

### Step 3: Verify
```sql
SHOW ENGINES;
-- FEDERATED should show "YES"
```

## Implementation Plan

### Phase 1: Backup Current Data
```sql
-- Backup OC2 order tables
CREATE TABLE ocus_order_backup_20251110 AS SELECT * FROM ocus_order;
CREATE TABLE ocus_order_product_backup_20251110 AS SELECT * FROM ocus_order_product;
CREATE TABLE ocus_order_option_backup_20251110 AS SELECT * FROM ocus_order_option;
CREATE TABLE ocus_order_total_backup_20251110 AS SELECT * FROM ocus_order_total;
CREATE TABLE ocus_order_history_backup_20251110 AS SELECT * FROM ocus_order_history;
```

### Phase 2: Create MySQL User for Federated Connection
```sql
-- OC3 needs to allow connections from localhost
CREATE USER IF NOT EXISTS 'federated_user'@'127.0.0.1' IDENTIFIED BY 'federated_pass';
GRANT SELECT, INSERT, UPDATE, DELETE ON `a1627-unqs-oc3`.ocus_order TO 'federated_user'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE, DELETE ON `a1627-unqs-oc3`.ocus_order_product TO 'federated_user'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE, DELETE ON `a1627-unqs-oc3`.ocus_order_option TO 'federated_user'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE, DELETE ON `a1627-unqs-oc3`.ocus_order_total TO 'federated_user'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE, DELETE ON `a1627-unqs-oc3`.ocus_order_history TO 'federated_user'@'127.0.0.1';
FLUSH PRIVILEGES;
```

### Phase 3: Drop Triggers (No Longer Needed)
```sql
-- In OC2
DROP TRIGGER IF EXISTS ocus_order_after_insert_sync_oc2;
DROP TRIGGER IF EXISTS ocus_order_after_update_sync_oc2;
DROP TRIGGER IF EXISTS ocus_order_product_after_insert_sync_oc2;
DROP TRIGGER IF EXISTS ocus_order_product_after_delete_sync_oc2;
DROP TRIGGER IF EXISTS ocus_order_option_after_insert_sync_oc2;
DROP TRIGGER IF EXISTS ocus_order_option_after_delete_sync_oc2;
DROP TRIGGER IF EXISTS ocus_order_total_after_insert_sync_oc2;
DROP TRIGGER IF EXISTS ocus_order_total_after_delete_sync_oc2;
DROP TRIGGER IF EXISTS ocus_order_history_after_insert_sync_oc2;
DROP TRIGGER IF EXISTS ocus_order_history_after_update_sync_oc2;
DROP TRIGGER IF EXISTS ocus_order_history_after_delete_sync_oc2;

-- In OC3
DROP TRIGGER IF EXISTS ocus_order_after_insert_sync;
DROP TRIGGER IF EXISTS ocus_order_after_update_sync;
DROP TRIGGER IF EXISTS ocus_order_product_after_insert_sync;
DROP TRIGGER IF EXISTS ocus_order_product_after_delete_sync;
DROP TRIGGER IF EXISTS ocus_order_option_after_insert_sync;
DROP TRIGGER IF EXISTS ocus_order_option_after_delete_sync;
DROP TRIGGER IF EXISTS ocus_order_total_after_insert_sync;
DROP TRIGGER IF EXISTS ocus_order_total_after_delete_sync;
DROP TRIGGER IF EXISTS ocus_order_history_after_insert_sync;
DROP TRIGGER IF EXISTS ocus_order_history_after_update_sync;
DROP TRIGGER IF EXISTS ocus_order_history_after_delete_sync;
```

### Phase 4: Replace OC2 Tables with FEDERATED
```sql
-- In OC2 database
USE `a1627-unqs-oc`;

-- Drop existing tables
DROP TABLE IF EXISTS ocus_order;
DROP TABLE IF EXISTS ocus_order_product;
DROP TABLE IF EXISTS ocus_order_option;
DROP TABLE IF EXISTS ocus_order_total;
DROP TABLE IF EXISTS ocus_order_history;

-- Create FEDERATED tables
CREATE TABLE ocus_order (
  <same structure as OC3>
) ENGINE=FEDERATED
CONNECTION='mysql://federated_user:federated_pass@127.0.0.1:3306/a1627-unqs-oc3/ocus_order';

-- Repeat for other 4 tables...
```

### Phase 5: Test Operations
```sql
-- Test INSERT
INSERT INTO ocus_order (...) VALUES (...);
-- Check in OC3: SELECT * FROM ocus_order WHERE order_id = X;

-- Test UPDATE
UPDATE ocus_order SET total = 100 WHERE order_id = X;
-- Check in OC3

-- Test DELETE
DELETE FROM ocus_order_product WHERE order_product_id = Y;
-- Check in OC3
```

## Advantages

✅ **Simplicity**: No triggers, no queue, no worker
✅ **Real-time**: Changes visible immediately
✅ **Consistency**: Single source of truth
✅ **Maintainability**: Much easier to understand and debug
✅ **Performance**: No queue processing overhead

## Disadvantages

⚠️ **Single Point of Failure**: If OC3 database is down, OC2 orders fail
⚠️ **Network Overhead**: Small overhead for localhost connections (negligible)
⚠️ **Foreign Keys**: FEDERATED doesn't support foreign key constraints
⚠️ **Transactions**: Limited transaction support across federated tables

## Alternative: Cross-Database Views (Simpler!)

Since both databases are on the same MySQL server, we could use even simpler approach:

```sql
-- In OC2, instead of FEDERATED tables, use cross-database references
-- Option A: Direct cross-database queries in code
SELECT * FROM `a1627-unqs-oc3`.ocus_order WHERE order_id = 123;

-- Option B: Create views (won't work for INSERT/UPDATE though)
CREATE VIEW ocus_order AS SELECT * FROM `a1627-unqs-oc3`.ocus_order;
```

## Recommendation

**For your setup (both DBs on same server), I recommend FEDERATED** because:
1. Both databases on same MySQL instance = zero network latency
2. Eliminates all sync complexity
3. Works with INSERT/UPDATE/DELETE (unlike views)
4. Can easily rollback to separate tables if needed

## Files That Can Be Removed After Migration

1. `install/order_triggers_*.sql` - All trigger files
2. `install/order_related_tables_triggers_*.sql` - All related table triggers
3. `install/order_history_triggers_*.sql` - History triggers
4. `install/queue_consolidation_procedure*.sql` - Consolidation procedures
5. `install/fix_sync_status_enum.sql` - Enum fixes
6. `cli/order_sync_worker.php` - Worker script
7. `install/DEBOUNCING_IMPLEMENTATION.md` - Documentation
8. `install/FINAL_OPTIMIZATION_SUMMARY.md` - Documentation

**Lines of code removed**: ~2000+ lines
**Lines of code added**: ~100 lines (FEDERATED setup)
**Reduction**: 95% less code!
