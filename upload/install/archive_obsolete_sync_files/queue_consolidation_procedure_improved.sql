-- ============================================
-- Queue Consolidation Procedure - IMPROVED
-- Consolidates redundant queue entries for BOTH OC2 and OC3
-- Works together with debouncing triggers
-- ============================================

-- ============================================
-- OC3 Consolidation Procedure
-- ============================================
DELIMITER $$

DROP PROCEDURE IF EXISTS consolidate_order_queue_oc3$$

CREATE PROCEDURE consolidate_order_queue_oc3(IN target_order_id INT)
BEGIN
  -- 1. Cancel INSERT-DELETE pairs (should already be done by debouncing, but double-check)
  UPDATE ocus_order_sync_queue q1
  SET q1.sync_status = 'cancelled',
      q1.error_message = 'Cancelled by consolidation - INSERT-DELETE pair'
  WHERE q1.parent_order_id = target_order_id
    AND q1.source_db = 'oc3'
    AND q1.table_name IN ('order_product', 'order_option', 'order_total')
    AND q1.operation = 'INSERT'
    AND q1.sync_status = 'pending'
    AND q1.sync_ready = 1
    AND EXISTS (
      SELECT 1 FROM ocus_order_sync_queue q2
      WHERE q2.parent_order_id = target_order_id
        AND q2.source_db = 'oc3'
        AND q2.table_name = q1.table_name
        AND q2.record_id = q1.record_id
        AND q2.operation = 'DELETE'
        AND q2.sync_status = 'pending'
        AND q2.id > q1.id
    );

  -- 2. Keep only LATEST UPDATE for order table (multiple UPDATEs to same order)
  UPDATE ocus_order_sync_queue q1
  SET q1.sync_status = 'superseded',
      q1.error_message = 'Superseded by newer UPDATE'
  WHERE q1.parent_order_id = target_order_id
    AND q1.source_db = 'oc3'
    AND q1.table_name = 'order'
    AND q1.operation = 'UPDATE'
    AND q1.sync_status = 'pending'
    AND q1.sync_ready = 1
    AND EXISTS (
      SELECT 1 FROM ocus_order_sync_queue q2
      WHERE q2.parent_order_id = target_order_id
        AND q2.source_db = 'oc3'
        AND q2.table_name = 'order'
        AND q2.operation = 'UPDATE'
        AND q2.sync_status = 'pending'
        AND q2.sync_ready = 1
        AND q2.id > q1.id
    );

  -- 3. If there's both INSERT and UPDATE for order, keep only UPDATE (INSERT already synced or superseded)
  UPDATE ocus_order_sync_queue q1
  SET q1.sync_status = 'superseded',
      q1.error_message = 'Superseded by UPDATE (INSERT becomes unnecessary)'
  WHERE q1.parent_order_id = target_order_id
    AND q1.source_db = 'oc3'
    AND q1.table_name = 'order'
    AND q1.operation = 'INSERT'
    AND q1.sync_status = 'pending'
    AND q1.sync_ready = 1
    AND EXISTS (
      SELECT 1 FROM ocus_order_sync_queue q2
      WHERE q2.parent_order_id = target_order_id
        AND q2.source_db = 'oc3'
        AND q2.table_name = 'order'
        AND q2.operation = 'UPDATE'
        AND q2.sync_status = 'pending'
        AND q2.sync_ready = 1
    );
END$$

-- ============================================
-- OC2 Consolidation Procedure
-- ============================================
DROP PROCEDURE IF EXISTS consolidate_order_queue_oc2$$

CREATE PROCEDURE consolidate_order_queue_oc2(IN target_order_id INT)
BEGIN
  -- 1. Cancel INSERT-DELETE pairs (should already be done by debouncing, but double-check)
  UPDATE ocus_order_sync_queue q1
  SET q1.sync_status = 'cancelled',
      q1.error_message = 'Cancelled by consolidation - INSERT-DELETE pair'
  WHERE q1.parent_order_id = target_order_id
    AND q1.source_db = 'oc2'
    AND q1.table_name IN ('order_product', 'order_option', 'order_total')
    AND q1.operation = 'INSERT'
    AND q1.sync_status = 'pending'
    AND q1.sync_ready = 1
    AND EXISTS (
      SELECT 1 FROM ocus_order_sync_queue q2
      WHERE q2.parent_order_id = target_order_id
        AND q2.source_db = 'oc2'
        AND q2.table_name = q1.table_name
        AND q2.record_id = q1.record_id
        AND q2.operation = 'DELETE'
        AND q2.sync_status = 'pending'
        AND q2.id > q1.id
    );

  -- 2. Keep only LATEST UPDATE for order table (multiple UPDATEs to same order)
  UPDATE ocus_order_sync_queue q1
  SET q1.sync_status = 'superseded',
      q1.error_message = 'Superseded by newer UPDATE'
  WHERE q1.parent_order_id = target_order_id
    AND q1.source_db = 'oc2'
    AND q1.table_name = 'order'
    AND q1.operation = 'UPDATE'
    AND q1.sync_status = 'pending'
    AND q1.sync_ready = 1
    AND EXISTS (
      SELECT 1 FROM ocus_order_sync_queue q2
      WHERE q2.parent_order_id = target_order_id
        AND q2.source_db = 'oc2'
        AND q2.table_name = 'order'
        AND q2.operation = 'UPDATE'
        AND q2.sync_status = 'pending'
        AND q2.sync_ready = 1
        AND q2.id > q1.id
    );

  -- 3. If there's both INSERT and UPDATE for order, keep only UPDATE
  UPDATE ocus_order_sync_queue q1
  SET q1.sync_status = 'superseded',
      q1.error_message = 'Superseded by UPDATE (INSERT becomes unnecessary)'
  WHERE q1.parent_order_id = target_order_id
    AND q1.source_db = 'oc2'
    AND q1.table_name = 'order'
    AND q1.operation = 'INSERT'
    AND q1.sync_status = 'pending'
    AND q1.sync_ready = 1
    AND EXISTS (
      SELECT 1 FROM ocus_order_sync_queue q2
      WHERE q2.parent_order_id = target_order_id
        AND q2.source_db = 'oc2'
        AND q2.table_name = 'order'
        AND q2.operation = 'UPDATE'
        AND q2.sync_status = 'pending'
        AND q2.sync_ready = 1
    );
END$$

DELIMITER ;

-- ============================================
-- Install procedures in BOTH databases
-- ============================================
SELECT 'Queue consolidation procedures created for OC3 and OC2' AS status;
SELECT 'Call consolidate_order_queue_oc3(order_id) before processing OC3 queue' AS usage_oc3;
SELECT 'Call consolidate_order_queue_oc2(order_id) before processing OC2 queue' AS usage_oc2;
