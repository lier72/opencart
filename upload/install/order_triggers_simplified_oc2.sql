-- ============================================
-- Simplified Order Triggers (OC2) - No Delayed Sync
-- All items marked sync_ready = 1 immediately
-- Consolidation procedure handles optimization
-- ============================================

DROP TRIGGER IF EXISTS `ocus_order_after_insert_sync_oc2`;
DROP TRIGGER IF EXISTS `ocus_order_after_update_sync_oc2`;

DELIMITER $$

-- ============================================
-- ORDER INSERT Trigger
-- ============================================
CREATE TRIGGER `ocus_order_after_insert_sync_oc2`
AFTER INSERT ON `ocus_order`
FOR EACH ROW
BEGIN
  DECLARE current_source ENUM('oc2', 'oc3');
  DECLARE should_sync TINYINT DEFAULT 1;

  SET current_source = 'oc2';

  -- Check if sync is in progress
  IF @sync_in_progress = 1 THEN
    SET should_sync = 0;
  END IF;

  IF should_sync = 1 THEN
    INSERT INTO `ocus_order_sync_queue` (
      `source_db`, `table_name`, `operation`, `record_id`, `parent_order_id`,
      `data_json`, `sync_status`, `sync_ready`, `created_at`
    ) VALUES (
      current_source, 'order', 'INSERT', NEW.order_id, NEW.order_id,
      JSON_OBJECT('order_id', NEW.order_id, 'firstname', NEW.firstname,
                  'total', NEW.total, 'order_status_id', NEW.order_status_id),
      'pending', 1, NOW()  -- Always ready
    );
  END IF;
END$$

-- ============================================
-- ORDER UPDATE Trigger
-- ============================================
CREATE TRIGGER `ocus_order_after_update_sync_oc2`
AFTER UPDATE ON `ocus_order`
FOR EACH ROW
BEGIN
  DECLARE current_source ENUM('oc2', 'oc3');
  DECLARE should_sync TINYINT DEFAULT 1;

  SET current_source = 'oc2';

  -- Check if sync is in progress
  IF @sync_in_progress = 1 THEN
    SET should_sync = 0;
  END IF;

  IF should_sync = 1 THEN
    INSERT INTO `ocus_order_sync_queue` (
      `source_db`, `table_name`, `operation`, `record_id`, `parent_order_id`,
      `data_json`, `sync_status`, `sync_ready`, `created_at`
    ) VALUES (
      current_source, 'order', 'UPDATE', NEW.order_id, NEW.order_id,
      JSON_OBJECT('order_id', NEW.order_id, 'order_status_id', NEW.order_status_id,
                  'total', NEW.total, 'date_modified', NEW.date_modified),
      'pending', 1, NOW()  -- Always ready
    );
  END IF;
END$$

DELIMITER ;

SELECT 'Simplified order triggers created for OC2 (no delayed sync)' AS status;
