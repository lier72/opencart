-- ============================================
-- Queue Consolidation Procedure - FINAL
-- Uses fully derived tables to avoid MySQL error 1093
-- ============================================

-- ============================================
-- OC3 Consolidation Procedure
-- ============================================
DELIMITER $$

DROP PROCEDURE IF EXISTS consolidate_order_queue_oc3$$

CREATE PROCEDURE consolidate_order_queue_oc3(IN target_order_id INT)
BEGIN
  DECLARE rows_affected INT;

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

  -- 2. Keep only LATEST UPDATE for order table (supersede older ones)
  -- First, find all UPDATE ids that have a newer UPDATE
  CREATE TEMPORARY TABLE IF NOT EXISTS temp_supersede_updates (id INT);
  TRUNCATE temp_supersede_updates;

  INSERT INTO temp_supersede_updates
  SELECT q1.id
  FROM ocus_order_sync_queue q1
  INNER JOIN ocus_order_sync_queue q2
    ON q2.parent_order_id = q1.parent_order_id
    AND q2.source_db = q1.source_db
    AND q2.table_name = q1.table_name
    AND q2.operation = q1.operation
    AND q2.sync_status = 'pending'
    AND q2.sync_ready = 1
    AND q2.id > q1.id
  WHERE q1.parent_order_id = target_order_id
    AND q1.source_db = 'oc3'
    AND q1.table_name = 'order'
    AND q1.operation = 'UPDATE'
    AND q1.sync_status = 'pending'
    AND q1.sync_ready = 1;

  UPDATE ocus_order_sync_queue q1
  INNER JOIN temp_supersede_updates t ON q1.id = t.id
  SET q1.sync_status = 'superseded',
      q1.error_message = 'Superseded by newer UPDATE';

  -- 3. If both INSERT and UPDATE for order, supersede INSERT
  CREATE TEMPORARY TABLE IF NOT EXISTS temp_supersede_inserts (id INT);
  TRUNCATE temp_supersede_inserts;

  INSERT INTO temp_supersede_inserts
  SELECT q1.id
  FROM ocus_order_sync_queue q1
  WHERE q1.parent_order_id = target_order_id
    AND q1.source_db = 'oc3'
    AND q1.table_name = 'order'
    AND q1.operation = 'INSERT'
    AND q1.sync_status = 'pending'
    AND q1.sync_ready = 1
    AND EXISTS (
      SELECT 1 FROM (
        SELECT id FROM ocus_order_sync_queue
        WHERE parent_order_id = target_order_id
          AND source_db = 'oc3'
          AND table_name = 'order'
          AND operation = 'UPDATE'
          AND sync_status = 'pending'
          AND sync_ready = 1
      ) AS q2_derived
    );

  UPDATE ocus_order_sync_queue q1
  INNER JOIN temp_supersede_inserts t ON q1.id = t.id
  SET q1.sync_status = 'superseded',
      q1.error_message = 'Superseded by UPDATE (INSERT becomes unnecessary)';

  DROP TEMPORARY TABLE IF EXISTS temp_supersede_updates;
  DROP TEMPORARY TABLE IF EXISTS temp_supersede_inserts;
END$$

-- ============================================
-- OC2 Consolidation Procedure
-- ============================================
DROP PROCEDURE IF EXISTS consolidate_order_queue_oc2$$

CREATE PROCEDURE consolidate_order_queue_oc2(IN target_order_id INT)
BEGIN
  DECLARE rows_affected INT;

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

  -- 2. Keep only LATEST UPDATE for order table (supersede older ones)
  CREATE TEMPORARY TABLE IF NOT EXISTS temp_supersede_updates (id INT);
  TRUNCATE temp_supersede_updates;

  INSERT INTO temp_supersede_updates
  SELECT q1.id
  FROM ocus_order_sync_queue q1
  INNER JOIN ocus_order_sync_queue q2
    ON q2.parent_order_id = q1.parent_order_id
    AND q2.source_db = q1.source_db
    AND q2.table_name = q1.table_name
    AND q2.operation = q1.operation
    AND q2.sync_status = 'pending'
    AND q2.sync_ready = 1
    AND q2.id > q1.id
  WHERE q1.parent_order_id = target_order_id
    AND q1.source_db = 'oc2'
    AND q1.table_name = 'order'
    AND q1.operation = 'UPDATE'
    AND q1.sync_status = 'pending'
    AND q1.sync_ready = 1;

  UPDATE ocus_order_sync_queue q1
  INNER JOIN temp_supersede_updates t ON q1.id = t.id
  SET q1.sync_status = 'superseded',
      q1.error_message = 'Superseded by newer UPDATE';

  -- 3. If both INSERT and UPDATE for order, supersede INSERT
  CREATE TEMPORARY TABLE IF NOT EXISTS temp_supersede_inserts (id INT);
  TRUNCATE temp_supersede_inserts;

  INSERT INTO temp_supersede_inserts
  SELECT q1.id
  FROM ocus_order_sync_queue q1
  WHERE q1.parent_order_id = target_order_id
    AND q1.source_db = 'oc2'
    AND q1.table_name = 'order'
    AND q1.operation = 'INSERT'
    AND q1.sync_status = 'pending'
    AND q1.sync_ready = 1
    AND EXISTS (
      SELECT 1 FROM (
        SELECT id FROM ocus_order_sync_queue
        WHERE parent_order_id = target_order_id
          AND source_db = 'oc2'
          AND table_name = 'order'
          AND operation = 'UPDATE'
          AND sync_status = 'pending'
          AND sync_ready = 1
      ) AS q2_derived
    );

  UPDATE ocus_order_sync_queue q1
  INNER JOIN temp_supersede_inserts t ON q1.id = t.id
  SET q1.sync_status = 'superseded',
      q1.error_message = 'Superseded by UPDATE (INSERT becomes unnecessary)';

  DROP TEMPORARY TABLE IF EXISTS temp_supersede_updates;
  DROP TEMPORARY TABLE IF EXISTS temp_supersede_inserts;
END$$

DELIMITER ;

-- ============================================
-- Install procedures in BOTH databases
-- ============================================
SELECT 'Queue consolidation procedures created for OC3 and OC2 (FINAL)' AS status;
SELECT 'Call consolidate_order_queue_oc3(order_id) before processing OC3 queue' AS usage_oc3;
SELECT 'Call consolidate_order_queue_oc2(order_id) before processing OC2 queue' AS usage_oc2;
