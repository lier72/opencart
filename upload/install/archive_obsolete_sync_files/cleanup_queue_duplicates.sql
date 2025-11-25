-- ============================================
-- Cleanup Duplicate Queue Entries
-- Keep only the LATEST pending entry for each order
-- ============================================

-- Step 1: Find IDs to skip (keep only latest per order)
CREATE TEMPORARY TABLE temp_ids_to_skip AS
SELECT q1.id
FROM ocus_order_sync_queue q1
WHERE q1.sync_status = 'pending'
  AND q1.table_name = 'order'
  AND EXISTS (
    SELECT 1 FROM ocus_order_sync_queue q2
    WHERE q2.record_id = q1.record_id
      AND q2.table_name = 'order'
      AND q2.sync_status = 'pending'
      AND q2.id > q1.id  -- Keep the newer one
  );

-- Step 2: Mark old duplicate entries as 'skip'
UPDATE ocus_order_sync_queue
SET sync_status = 'skip'
WHERE id IN (SELECT id FROM temp_ids_to_skip);

DROP TEMPORARY TABLE temp_ids_to_skip;

-- Step 2: Show cleanup summary
SELECT
  'Cleanup Summary' as report,
  (SELECT COUNT(*) FROM ocus_order_sync_queue WHERE sync_status = 'skip') as skipped_duplicates,
  (SELECT COUNT(*) FROM ocus_order_sync_queue WHERE sync_status = 'pending') as remaining_pending,
  (SELECT COUNT(*) FROM ocus_order_sync_queue WHERE sync_status = 'synced') as already_synced;

-- Step 3: Show queue breakdown
SELECT sync_status, operation, COUNT(*) as count
FROM ocus_order_sync_queue
GROUP BY sync_status, operation
ORDER BY sync_status, operation;
