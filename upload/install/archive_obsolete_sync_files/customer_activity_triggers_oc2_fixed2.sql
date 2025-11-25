-- ============================================
-- Customer Activity Table Sync Triggers
-- ============================================

DROP TRIGGER IF EXISTS `ocus_customer_activity_after_insert_sync`;

DELIMITER $$

CREATE TRIGGER `ocus_customer_activity_after_insert_sync`
AFTER INSERT ON `ocus_customer_activity`
FOR EACH ROW
BEGIN
  DECLARE current_source ENUM('oc2', 'oc3');
  DECLARE should_sync TINYINT DEFAULT 1;
  DECLARE related_order_id INT DEFAULT NULL;

  SET current_source = 'oc2';

  -- Check if sync is in progress
  IF @sync_in_progress = 1 THEN
    SET should_sync = 0;
  END IF;
  -- Try to find related order_id from the activity data
  -- Activity keys like 'order_account', 'order_guest' may contain order info
  -- We'll extract order_id if the key contains 'order'
  IF NEW.key LIKE '%order%' THEN
    -- Try to extract order_id from data field (it's typically stored as JSON or serialized)
    -- For simplicity, we'll check if data contains a number that could be an order_id
    SET related_order_id = CAST(REGEXP_SUBSTR(NEW.data, '[0-9]+') AS UNSIGNED);

    -- Verify this order exists and check sync_lock
    IF related_order_id IS NOT NULL AND EXISTS (
      SELECT 1 FROM ocus_order WHERE order_id = related_order_id
    ) THEN
      -- Check if this order is being synced
      IF EXISTS (
        SELECT 1 FROM ocus_order_id_map
        WHERE oc2_order_id = related_order_id AND sync_lock = 1
      ) THEN
        SET should_sync = 0;
      END IF;
    END IF;
  END IF;

  IF should_sync = 1 THEN
    INSERT INTO `ocus_order_sync_queue` (
      `source_db`, `table_name`, `operation`, `record_id`, `parent_order_id`, `data_json`, `sync_status`
    ) VALUES (
      current_source, 'customer_activity', 'INSERT', NEW.activity_id, related_order_id,
      JSON_OBJECT(
        'activity_id', NEW.activity_id,
        'customer_id', NEW.customer_id,
        'key', NEW.key,
        'data', NEW.data,
        'ip', NEW.ip,
        'date_added', NEW.date_added
      ), 'pending'
    );
  END IF;
END$$

DELIMITER ;

-- ============================================
-- Verify trigger was created
-- ============================================
SELECT 'Customer activity trigger created successfully' AS status;
SELECT TRIGGER_NAME, EVENT_MANIPULATION, EVENT_OBJECT_TABLE
FROM information_schema.TRIGGERS
WHERE TRIGGER_SCHEMA = DATABASE()
  AND EVENT_OBJECT_TABLE = 'ocus_customer_activity'
ORDER BY EVENT_OBJECT_TABLE, EVENT_MANIPULATION;
