-- ============================================
-- Order History Triggers for OC3
-- Syncs order_history table changes to OC2
-- ============================================

DROP TRIGGER IF EXISTS `ocus_order_history_after_insert_sync`;
DROP TRIGGER IF EXISTS `ocus_order_history_after_update_sync`;
DROP TRIGGER IF EXISTS `ocus_order_history_after_delete_sync`;

DELIMITER $$

-- ============================================
-- ORDER HISTORY INSERT Trigger
-- ============================================
CREATE TRIGGER `ocus_order_history_after_insert_sync`
AFTER INSERT ON `ocus_order_history`
FOR EACH ROW
BEGIN
  DECLARE current_source ENUM('oc2', 'oc3');
  DECLARE should_sync TINYINT DEFAULT 1;

  SET current_source = 'oc3';

  -- Check if sync is in progress
  IF @sync_in_progress = 1 THEN
    SET should_sync = 0;
  END IF;

  IF should_sync = 1 THEN
    INSERT INTO `ocus_order_sync_queue` (
      `source_db`, `table_name`, `operation`, `record_id`, `parent_order_id`,
      `data_json`, `sync_status`, `sync_ready`, `created_at`
    ) VALUES (
      current_source,
      'order_history',
      'INSERT',
      NEW.order_history_id,
      NEW.order_id,
      JSON_OBJECT(
        'order_history_id', NEW.order_history_id,
        'order_id', NEW.order_id,
        'order_status_id', NEW.order_status_id,
        'notify', NEW.notify,
        'comment', NEW.comment,
        'date_added', NEW.date_added
      ),
      'pending',
      1,  -- Order history is always ready (created after order completion)
      NOW()
    );
  END IF;
END$$

-- ============================================
-- ORDER HISTORY UPDATE Trigger
-- ============================================
CREATE TRIGGER `ocus_order_history_after_update_sync`
AFTER UPDATE ON `ocus_order_history`
FOR EACH ROW
BEGIN
  DECLARE current_source ENUM('oc2', 'oc3');
  DECLARE should_sync TINYINT DEFAULT 1;

  SET current_source = 'oc3';

  -- Check if sync is in progress
  IF @sync_in_progress = 1 THEN
    SET should_sync = 0;
  END IF;

  IF should_sync = 1 THEN
    INSERT INTO `ocus_order_sync_queue` (
      `source_db`, `table_name`, `operation`, `record_id`, `parent_order_id`,
      `data_json`, `sync_status`, `sync_ready`, `created_at`
    ) VALUES (
      current_source,
      'order_history',
      'UPDATE',
      NEW.order_history_id,
      NEW.order_id,
      JSON_OBJECT(
        'order_history_id', NEW.order_history_id,
        'order_id', NEW.order_id,
        'order_status_id', NEW.order_status_id,
        'notify', NEW.notify,
        'comment', NEW.comment,
        'date_added', NEW.date_added
      ),
      'pending',
      1,  -- Order history updates are always ready
      NOW()
    );
  END IF;
END$$

-- ============================================
-- ORDER HISTORY DELETE Trigger
-- ============================================
CREATE TRIGGER `ocus_order_history_after_delete_sync`
AFTER DELETE ON `ocus_order_history`
FOR EACH ROW
BEGIN
  DECLARE current_source ENUM('oc2', 'oc3');
  DECLARE should_sync TINYINT DEFAULT 1;

  SET current_source = 'oc3';

  -- Check if sync is in progress
  IF @sync_in_progress = 1 THEN
    SET should_sync = 0;
  END IF;

  IF should_sync = 1 THEN
    -- Cancel any pending INSERT for this same record (debouncing)
    UPDATE ocus_order_sync_queue
    SET sync_status = 'cancelled', error_message = 'Cancelled - record deleted before sync'
    WHERE table_name = 'order_history'
      AND record_id = OLD.order_history_id
      AND operation = 'INSERT'
      AND sync_status = 'pending'
      AND created_at > DATE_SUB(NOW(), INTERVAL 5 SECOND);

    -- Only queue DELETE if there was no recent INSERT to cancel
    IF ROW_COUNT() = 0 THEN
      INSERT INTO `ocus_order_sync_queue` (
        `source_db`, `table_name`, `operation`, `record_id`, `parent_order_id`,
        `data_json`, `sync_status`, `sync_ready`, `created_at`
      ) VALUES (
        current_source,
        'order_history',
        'DELETE',
        OLD.order_history_id,
        OLD.order_id,
        JSON_OBJECT(
          'order_history_id', OLD.order_history_id,
          'order_id', OLD.order_id
        ),
        'pending',
        1,  -- Order history deletes are always ready
        NOW()
      );
    END IF;
  END IF;
END$$

DELIMITER ;

SELECT 'Order history triggers created for OC3' AS status;
