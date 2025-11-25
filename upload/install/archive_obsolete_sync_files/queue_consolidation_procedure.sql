-- ============================================
-- Queue Consolidation Procedure
-- Consolidates redundant queue entries BEFORE worker processes them
-- ============================================

DELIMITER $$

DROP PROCEDURE IF EXISTS consolidate_order_queue$$

CREATE PROCEDURE consolidate_order_queue(IN target_order_id INT)
BEGIN
  -- This procedure consolidates redundant queue entries for a specific order
  -- It should be called by the worker BEFORE processing queue items

  -- 1. Cancel INSERT entries that have a subsequent DELETE for the same record
  UPDATE ocus_order_sync_queue q1
  SET q1.sync_status = 'cancelled',
      q1.error_message = 'Cancelled - record was deleted shortly after insertion'
  WHERE q1.parent_order_id = target_order_id
    AND q1.table_name IN ('order_product', 'order_option', 'order_total')
    AND q1.operation = 'INSERT'
    AND q1.sync_status = 'pending'
    AND EXISTS (
      SELECT 1 FROM ocus_order_sync_queue q2
      WHERE q2.parent_order_id = target_order_id
        AND q2.table_name = q1.table_name
        AND q2.record_id = q1.record_id
        AND q2.operation = 'DELETE'
        AND q2.sync_status = 'pending'
        AND q2.id > q1.id
    );

  -- 2. For INSERT-DELETE-INSERT sequences, cancel the middle DELETE
  UPDATE ocus_order_sync_queue q2
  SET q2.sync_status = 'cancelled',
      q2.error_message = 'Cancelled - intermediate delete in INSERT-DELETE-INSERT sequence'
  WHERE q2.parent_order_id = target_order_id
    AND q2.table_name IN ('order_product', 'order_option', 'order_total')
    AND q2.operation = 'DELETE'
    AND q2.sync_status = 'pending'
    AND EXISTS (
      -- Has a prior INSERT
      SELECT 1 FROM ocus_order_sync_queue q1
      WHERE q1.parent_order_id = target_order_id
        AND q1.table_name = q2.table_name
        AND q1.record_id = q2.record_id
        AND q1.operation = 'INSERT'
        AND q1.id < q2.id
        AND (q1.sync_status = 'pending' OR q1.sync_status = 'cancelled')
    )
    AND EXISTS (
      -- Has a subsequent INSERT
      SELECT 1 FROM ocus_order_sync_queue q3
      WHERE q3.parent_order_id = target_order_id
        AND q3.table_name = q2.table_name
        AND q3.operation = 'INSERT'
        AND q3.sync_status = 'pending'
        AND q3.id > q2.id
    );

  -- 3. Keep only the LATEST INSERT for each order_product/order_total
  UPDATE ocus_order_sync_queue q1
  SET q1.sync_status = 'superseded',
      q1.error_message = 'Superseded by newer insert for same record'
  WHERE q1.parent_order_id = target_order_id
    AND q1.table_name IN ('order_product', 'order_total')
    AND q1.operation = 'INSERT'
    AND q1.sync_status = 'pending'
    AND EXISTS (
      SELECT 1 FROM ocus_order_sync_queue q2
      WHERE q2.parent_order_id = target_order_id
        AND q2.table_name = q1.table_name
        AND q2.operation = 'INSERT'
        AND q2.sync_status = 'pending'
        AND q2.id > q1.id
        -- For order_product/order_total, newer record_id likely means supersedes older
        AND q2.record_id > q1.record_id
    );

END$$

DELIMITER ;

-- ============================================
-- Usage Example
-- ============================================
-- CALL consolidate_order_queue(108030);
--
-- SELECT sync_status, COUNT(*) FROM ocus_order_sync_queue
-- WHERE parent_order_id = 108030
-- GROUP BY sync_status;
