-- ============================================
-- Order History Triggers for OC2 - MySQL 5.6 Compatible
-- Uses CONCAT instead of JSON_OBJECT
-- ============================================

DELIMITER $$

DROP TRIGGER IF EXISTS `ocus_order_history_after_insert_sync_oc2`$$
CREATE TRIGGER `ocus_order_history_after_insert_sync_oc2`
AFTER INSERT ON `ocus_order_history`
FOR EACH ROW
BEGIN
  DECLARE current_source ENUM('oc2', 'oc3');
  DECLARE should_sync TINYINT DEFAULT 1;
  DECLARE json_data TEXT;

  SET current_source = 'oc2';

  IF @sync_in_progress = 1 THEN
    SET should_sync = 0;
  END IF;

  IF should_sync = 1 THEN
    SET json_data = CONCAT(
      '{',
        '"order_history_id":', IFNULL(NEW.order_history_id, 'null'), ',',
        '"order_id":', IFNULL(NEW.order_id, 'null'), ',',
        '"order_status_id":', IFNULL(NEW.order_status_id, '0'), ',',
        '"notify":', IFNULL(NEW.notify, '0'), ',',
        '"comment":"', REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(IFNULL(NEW.comment, ''), '\\', '\\\\'), '"', '\\"'), '\n', '\\n'), '\r', '\\r'), '\t', '\\t'), '",',
        '"date_added":"', IFNULL(NEW.date_added, ''), '"',
      '}'
    );

    INSERT INTO `ocus_order_sync_queue` (
      `source_db`, `table_name`, `operation`, `record_id`, `parent_order_id`, `data_json`, `sync_status`
    ) VALUES (
      current_source, 'order_history', 'INSERT', NEW.order_history_id, NEW.order_id,
      json_data, 'pending'
    );
  END IF;
END$$

DROP TRIGGER IF EXISTS `ocus_order_history_after_update_sync_oc2`$$
CREATE TRIGGER `ocus_order_history_after_update_sync_oc2`
AFTER UPDATE ON `ocus_order_history`
FOR EACH ROW
BEGIN
  DECLARE current_source ENUM('oc2', 'oc3');
  DECLARE should_sync TINYINT DEFAULT 1;
  DECLARE json_data TEXT;

  SET current_source = 'oc2';

  IF @sync_in_progress = 1 THEN
    SET should_sync = 0;
  END IF;

  IF should_sync = 1 THEN
    SET json_data = CONCAT(
      '{',
        '"order_history_id":', IFNULL(NEW.order_history_id, 'null'), ',',
        '"order_id":', IFNULL(NEW.order_id, 'null'), ',',
        '"order_status_id":', IFNULL(NEW.order_status_id, '0'), ',',
        '"notify":', IFNULL(NEW.notify, '0'), ',',
        '"comment":"', REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(IFNULL(NEW.comment, ''), '\\', '\\\\'), '"', '\\"'), '\n', '\\n'), '\r', '\\r'), '\t', '\\t'), '",',
        '"date_added":"', IFNULL(NEW.date_added, ''), '"',
      '}'
    );

    INSERT INTO `ocus_order_sync_queue` (
      `source_db`, `table_name`, `operation`, `record_id`, `parent_order_id`, `data_json`, `sync_status`
    ) VALUES (
      current_source, 'order_history', 'UPDATE', NEW.order_history_id, NEW.order_id,
      json_data, 'pending'
    );
  END IF;
END$$

DROP TRIGGER IF EXISTS `ocus_order_history_after_delete_sync_oc2`$$
CREATE TRIGGER `ocus_order_history_after_delete_sync_oc2`
AFTER DELETE ON `ocus_order_history`
FOR EACH ROW
BEGIN
  DECLARE current_source ENUM('oc2', 'oc3');
  DECLARE should_sync TINYINT DEFAULT 1;
  DECLARE json_data TEXT;

  SET current_source = 'oc2';

  IF @sync_in_progress = 1 THEN
    SET should_sync = 0;
  END IF;

  IF should_sync = 1 THEN
    SET json_data = CONCAT(
      '{',
        '"order_history_id":', IFNULL(OLD.order_history_id, 'null'), ',',
        '"order_id":', IFNULL(OLD.order_id, 'null'),
      '}'
    );

    INSERT INTO `ocus_order_sync_queue` (
      `source_db`, `table_name`, `operation`, `record_id`, `parent_order_id`, `data_json`, `sync_status`
    ) VALUES (
      current_source, 'order_history', 'DELETE', OLD.order_history_id, OLD.order_id,
      json_data, 'pending'
    );
  END IF;
END$$

DELIMITER ;

SELECT 'Order history triggers (INSERT, UPDATE, DELETE) installed successfully for OC2 (MySQL 5.6)' AS status;
