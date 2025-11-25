-- ============================================
-- Fix sync_status ENUM Column
-- Add 'cancelled' and 'superseded' values
-- ============================================

-- OC3 Database
ALTER TABLE ocus_order_sync_queue
MODIFY COLUMN sync_status ENUM('pending','synced','error','skip','cancelled','superseded')
DEFAULT 'pending';

SELECT 'sync_status ENUM updated - added cancelled and superseded values' AS status;

-- Verify the change
SHOW COLUMNS FROM ocus_order_sync_queue LIKE 'sync_status';
