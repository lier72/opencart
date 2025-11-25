-- ============================================
-- Queue Consolidation Procedure - FIXED
-- Uses JOIN syntax instead of subqueries to avoid MySQL error 1093
-- ============================================

-- ============================================
-- OC3 Consolidation Procedure
-- ============================================
DELIMITER $$

DROP PROCEDURE IF EXISTS consolidate_order_queue_oc3$$

CREATE PROCEDURE consolidate_order_queue_oc3(IN target_order_id INT)
BEGIN
  -- 1. Cancel INSERT-DELETE pairs (backup for debouncing)
  UPDATE ocus_order_sync_queue q1
  INNER JOIN (
    SELECT DISTINCT q1_inner.id
    FROM ocus_order_sync_queue q1_inner
    INNER JOIN ocus_order_sync_queue q2_inner
      ON q2_inner.parent_order_id = q1_inner.parent_order_id
      AND q2_inner.source_db = q1_inner.source_db
      AND q2_inner.table_name = q1_inner.table_name
      AND q2_inner.record_id = q1_inner.record_id
      AND q2_inner.operation = 'DELETE'
      AND q2_inner.sync_status = 'pending'
      AND q2_inner.id > q1_inner.id
    WHERE q1_inner.parent_order_id = target_order_id
      AND q1_inner.source_db = 'oc3'
      AND q1_inner.table_name IN ('order_product', 'order_option', 'order_total')
      AND q1_inner.operation = 'INSERT'
      AND q1_inner.sync_status = 'pending'
      AND q1_inner.sync_ready = 1
  ) q_to_cancel ON q1.id = q_to_cancel.id
  SET q1.sync_status = 'cancelled',
      q1.error_message = 'Cancelled by consolidation - INSERT-DELETE pair';

  -- 2. Keep only LATEST UPDATE for order table
  UPDATE ocus_order_sync_queue q1
  INNER JOIN (
    SELECT q1_inner.id
    FROM ocus_order_sync_queue q1_inner
    WHERE q1_inner.parent_order_id = target_order_id
      AND q1_inner.source_db = 'oc3'
      AND q1_inner.table_name = 'order'
      AND q1_inner.operation = 'UPDATE'
      AND q1_inner.sync_status = 'pending'
      AND q1_inner.sync_ready = 1
      AND EXISTS (
        SELECT 1 FROM ocus_order_sync_queue q2_inner
        WHERE q2_inner.parent_order_id = target_order_id
          AND q2_inner.source_db = 'oc3'
          AND q2_inner.table_name = 'order'
          AND q2_inner.operation = 'UPDATE'
          AND q2_inner.sync_status = 'pending'
          AND q2_inner.sync_ready = 1
          AND q2_inner.id > q1_inner.id
      )
  ) q_to_supersede ON q1.id = q_to_supersede.id
  SET q1.sync_status = 'superseded',
      q1.error_message = 'Superseded by newer UPDATE';

  -- 3. If both INSERT and UPDATE for order, keep only UPDATE
  UPDATE ocus_order_sync_queue q1
  INNER JOIN (
    SELECT q1_inner.id
    FROM ocus_order_sync_queue q1_inner
    WHERE q1_inner.parent_order_id = target_order_id
      AND q1_inner.source_db = 'oc3'
      AND q1_inner.table_name = 'order'
      AND q1_inner.operation = 'INSERT'
      AND q1_inner.sync_status = 'pending'
      AND q1_inner.sync_ready = 1
      AND EXISTS (
        SELECT 1 FROM ocus_order_sync_queue q2_inner
        WHERE q2_inner.parent_order_id = target_order_id
          AND q2_inner.source_db = 'oc3'
          AND q2_inner.table_name = 'order'
          AND q2_inner.operation = 'UPDATE'
          AND q2_inner.sync_status = 'pending'
          AND q2_inner.sync_ready = 1
      )
  ) q_to_supersede ON q1.id = q_to_supersede.id
  SET q1.sync_status = 'superseded',
      q1.error_message = 'Superseded by UPDATE (INSERT becomes unnecessary)';
END$$

-- ============================================
-- OC2 Consolidation Procedure
-- ============================================
DROP PROCEDURE IF EXISTS consolidate_order_queue_oc2$$

CREATE PROCEDURE consolidate_order_queue_oc2(IN target_order_id INT)
BEGIN
  -- 1. Cancel INSERT-DELETE pairs (backup for debouncing)
  UPDATE ocus_order_sync_queue q1
  INNER JOIN (
    SELECT DISTINCT q1_inner.id
    FROM ocus_order_sync_queue q1_inner
    INNER JOIN ocus_order_sync_queue q2_inner
      ON q2_inner.parent_order_id = q1_inner.parent_order_id
      AND q2_inner.source_db = q1_inner.source_db
      AND q2_inner.table_name = q1_inner.table_name
      AND q2_inner.record_id = q1_inner.record_id
      AND q2_inner.operation = 'DELETE'
      AND q2_inner.sync_status = 'pending'
      AND q2_inner.id > q1_inner.id
    WHERE q1_inner.parent_order_id = target_order_id
      AND q1_inner.source_db = 'oc2'
      AND q1_inner.table_name IN ('order_product', 'order_option', 'order_total')
      AND q1_inner.operation = 'INSERT'
      AND q1_inner.sync_status = 'pending'
      AND q1_inner.sync_ready = 1
  ) q_to_cancel ON q1.id = q_to_cancel.id
  SET q1.sync_status = 'cancelled',
      q1.error_message = 'Cancelled by consolidation - INSERT-DELETE pair';

  -- 2. Keep only LATEST UPDATE for order table
  UPDATE ocus_order_sync_queue q1
  INNER JOIN (
    SELECT q1_inner.id
    FROM ocus_order_sync_queue q1_inner
    WHERE q1_inner.parent_order_id = target_order_id
      AND q1_inner.source_db = 'oc2'
      AND q1_inner.table_name = 'order'
      AND q1_inner.operation = 'UPDATE'
      AND q1_inner.sync_status = 'pending'
      AND q1_inner.sync_ready = 1
      AND EXISTS (
        SELECT 1 FROM ocus_order_sync_queue q2_inner
        WHERE q2_inner.parent_order_id = target_order_id
          AND q2_inner.source_db = 'oc2'
          AND q2_inner.table_name = 'order'
          AND q2_inner.operation = 'UPDATE'
          AND q2_inner.sync_status = 'pending'
          AND q2_inner.sync_ready = 1
          AND q2_inner.id > q1_inner.id
      )
  ) q_to_supersede ON q1.id = q_to_supersede.id
  SET q1.sync_status = 'superseded',
      q1.error_message = 'Superseded by newer UPDATE';

  -- 3. If both INSERT and UPDATE for order, keep only UPDATE
  UPDATE ocus_order_sync_queue q1
  INNER JOIN (
    SELECT q1_inner.id
    FROM ocus_order_sync_queue q1_inner
    WHERE q1_inner.parent_order_id = target_order_id
      AND q1_inner.source_db = 'oc2'
      AND q1_inner.table_name = 'order'
      AND q1_inner.operation = 'INSERT'
      AND q1_inner.sync_status = 'pending'
      AND q1_inner.sync_ready = 1
      AND EXISTS (
        SELECT 1 FROM ocus_order_sync_queue q2_inner
        WHERE q2_inner.parent_order_id = target_order_id
          AND q2_inner.source_db = 'oc2'
          AND q2_inner.table_name = 'order'
          AND q2_inner.operation = 'UPDATE'
          AND q2_inner.sync_status = 'pending'
          AND q2_inner.sync_ready = 1
      )
  ) q_to_supersede ON q1.id = q_to_supersede.id
  SET q1.sync_status = 'superseded',
      q1.error_message = 'Superseded by UPDATE (INSERT becomes unnecessary)';
END$$

DELIMITER ;

-- ============================================
-- Install procedures in BOTH databases
-- ============================================
SELECT 'Queue consolidation procedures created for OC3 and OC2 (FIXED)' AS status;
SELECT 'Call consolidate_order_queue_oc3(order_id) before processing OC3 queue' AS usage_oc3;
SELECT 'Call consolidate_order_queue_oc2(order_id) before processing OC2 queue' AS usage_oc2;
