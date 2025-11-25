-- ============================================
-- Customer Activity Sync Triggers (OC3) - MySQL 5.6 Compatible
-- Syncs customer activity from OC3 to OC2
-- Uses CONCAT instead of JSON_OBJECT for MySQL 5.6 compatibility
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
  DECLARE json_data TEXT;

  SET current_source = 'oc3';

  -- Check if sync is in progress
  IF @sync_in_progress = 1 THEN
    SET should_sync = 0;
  END IF;

  -- Try to find related order_id from the activity data
  -- Activity keys like 'order_account', 'order_guest' may contain order info
  IF NEW.key LIKE '%order%' THEN
    -- Try to extract order_id from data field (MySQL 5.6 compatible)
    SET related_order_id = CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(NEW.data, ':', 2), ':', -1) AS UNSIGNED);

    -- Verify this order exists and check sync_lock
    IF related_order_id IS NOT NULL AND EXISTS (
      SELECT 1 FROM ocus_order WHERE order_id = related_order_id
    ) THEN
      -- Check if this order is being synced
      IF EXISTS (
        SELECT 1 FROM ocus_order_id_map
        WHERE oc3_order_id = related_order_id AND sync_lock = 1
      ) THEN
        SET should_sync = 0;
      END IF;
    END IF;
  END IF;

  IF should_sync = 1 THEN
    -- Build JSON manually using CONCAT for MySQL 5.6 compatibility
    SET json_data = CONCAT(
      '{',
        '"customer_activity_id":', IFNULL(NEW.customer_activity_id, 'null'), ',',
        '"customer_id":', IFNULL(NEW.customer_id, 'null'), ',',
        '"key":"', REPLACE(IFNULL(NEW.key, ''), '"', '\\"'), '",',
        '"data":"', REPLACE(IFNULL(NEW.data, ''), '"', '\\"'), '",',
        '"ip":"', REPLACE(IFNULL(NEW.ip, ''), '"', '\\"'), '",',
        '"date_added":"', IFNULL(NEW.date_added, ''), '"',
      '}'
    );

    INSERT INTO `ocus_order_sync_queue` (
      `source_db`, `table_name`, `operation`, `record_id`, `parent_order_id`, `data_json`, `sync_status`
    ) VALUES (
      current_source, 'customer_activity', 'INSERT', NEW.customer_activity_id, related_order_id,
      json_data, 'pending'
    );
  END IF;
END$$

DELIMITER ;

SELECT 'Customer activity trigger created for OC3 (MySQL 5.6 compatible)' AS status;
