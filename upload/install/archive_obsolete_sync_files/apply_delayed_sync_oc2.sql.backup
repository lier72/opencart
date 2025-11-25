-- ============================================
-- Apply Delayed Sync Strategy to ALL OC2 Triggers
-- ============================================
-- This updates ALL triggers to use sync_ready = 0 by default
-- Only the ORDER UPDATE trigger marks items as sync_ready = 1
-- ============================================

-- ============================================
-- ORDER UPDATE Trigger - The KEY trigger that marks items ready
-- ============================================
DROP TRIGGER IF EXISTS `ocus_order_after_update_sync_oc2`;

DELIMITER $$

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
    -- Insert UPDATE operation
    INSERT INTO `ocus_order_sync_queue` (
      `source_db`, `table_name`, `operation`, `record_id`, `parent_order_id`,
      `data_json`, `sync_status`, `sync_ready`, `created_at`
    ) VALUES (
      current_source, 'order', 'UPDATE', NEW.order_id, NEW.order_id,
      JSON_OBJECT('order_id', NEW.order_id, 'order_status_id', NEW.order_status_id,
                  'total', NEW.total, 'date_modified', NEW.date_modified),
      'pending', 0, NOW()
    );

    -- KEY LOGIC: If order status changed from 0 to something else, mark ALL related items as ready
    IF OLD.order_status_id = 0 AND NEW.order_status_id > 0 THEN
      UPDATE ocus_order_sync_queue
      SET sync_ready = 1
      WHERE parent_order_id = NEW.order_id
        AND sync_status = 'pending'
        AND sync_ready = 0;
    END IF;
  END IF;
END$$

DELIMITER ;

SELECT 'Applied delayed sync strategy to OC2' AS status;
SELECT 'IMPORTANT: All new queue items will have sync_ready = 0 until order status changes from 0' AS note;
