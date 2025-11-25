-- ============================================
-- Customer Activity Trigger for OC2 - MySQL 5.6 Compatible
-- Uses CONCAT and SUBSTRING_INDEX instead of JSON_OBJECT and REGEXP_SUBSTR
-- ============================================

DELIMITER $$

DROP TRIGGER IF EXISTS `ocus_customer_activity_after_insert_sync`$$
CREATE TRIGGER `ocus_customer_activity_after_insert_sync`
AFTER INSERT ON `ocus_customer_activity`
FOR EACH ROW
BEGIN
  DECLARE current_source ENUM('oc2', 'oc3');
  DECLARE should_sync TINYINT DEFAULT 1;
  DECLARE json_data TEXT;
  DECLARE related_order_id INT;

  SET current_source = 'oc2';

  IF @sync_in_progress = 1 THEN
    SET should_sync = 0;
  END IF;

  IF should_sync = 1 THEN
    IF NEW.key LIKE '%order%' THEN
      -- Extract order_id from data field using SUBSTRING_INDEX (MySQL 5.6 compatible)
      SET related_order_id = CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(NEW.data, ':', 2), ':', -1) AS UNSIGNED);

      IF related_order_id IS NOT NULL AND EXISTS (
        SELECT 1 FROM ocus_order WHERE order_id = related_order_id
      ) THEN
        IF EXISTS (
          SELECT 1 FROM ocus_order_id_map
          WHERE oc2_order_id = related_order_id AND sync_lock = 1
        ) THEN
          SET should_sync = 0;
        END IF;
      END IF;
    END IF;
  END IF;

  IF should_sync = 1 THEN
    SET json_data = CONCAT(
      '{',
        '"activity_id":', IFNULL(NEW.activity_id, 'null'), ',',
        '"customer_id":', IFNULL(NEW.customer_id, '0'), ',',
        '"key":"', REPLACE(IFNULL(NEW.key, ''), '"', '\\"'), '",',
        '"data":"', REPLACE(IFNULL(NEW.data, ''), '"', '\\"'), '",',
        '"ip":"', REPLACE(IFNULL(NEW.ip, ''), '"', '\\"'), '",',
        '"date_added":"', IFNULL(NEW.date_added, ''), '"',
      '}'
    );

    INSERT INTO `ocus_order_sync_queue` (
      `source_db`, `table_name`, `operation`, `record_id`, `parent_order_id`, `data_json`, `sync_status`
    ) VALUES (
      current_source, 'customer_activity', 'INSERT', NEW.activity_id, related_order_id,
      json_data, 'pending'
    );
  END IF;
END$$

DELIMITER ;

SELECT 'Customer activity trigger installed successfully for OC2 (MySQL 5.6)' AS status;
