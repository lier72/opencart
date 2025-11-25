-- ============================================
-- Order Triggers for OC3 - MySQL 5.6 Compatible
-- Uses CONCAT instead of JSON_OBJECT
-- ============================================
-- IMPORTANT: Replace database name before running:
-- Find: `DATABASE_NAME_HERE`
-- Replace with your actual OC3 database name
-- ============================================

DELIMITER $$

DROP TRIGGER IF EXISTS `ocus_order_after_insert_sync`$$
CREATE TRIGGER `ocus_order_after_insert_sync`
AFTER INSERT ON `ocus_order`
FOR EACH ROW
BEGIN
  DECLARE current_source ENUM('oc2', 'oc3');
  DECLARE should_sync TINYINT DEFAULT 1;
  DECLARE json_data TEXT;

  SET current_source = 'oc3';

  IF @sync_in_progress = 1 THEN
    SET should_sync = 0;
  END IF;

  IF should_sync = 1 THEN
    SET json_data = CONCAT(
      '{',
        '"order_id":', IFNULL(NEW.order_id, 'null'), ',',
        '"firstname":"', REPLACE(IFNULL(NEW.firstname, ''), '"', '\\"'), '",',
        '"lastname":"', REPLACE(IFNULL(NEW.lastname, ''), '"', '\\"'), '",',
        '"email":"', REPLACE(IFNULL(NEW.email, ''), '"', '\\"'), '",',
        '"telephone":"', REPLACE(IFNULL(NEW.telephone, ''), '"', '\\"'), '",',
        '"total":', IFNULL(NEW.total, '0'), ',',
        '"order_status_id":', IFNULL(NEW.order_status_id, '0'),
      '}'
    );

    INSERT INTO `ocus_order_sync_queue` (
      `source_db`, `table_name`, `operation`, `record_id`, `parent_order_id`,
      `data_json`, `sync_status`, `sync_ready`, `created_at`
    ) VALUES (
      current_source, 'order', 'INSERT', NEW.order_id, NEW.order_id,
      json_data, 'pending', 1, NOW()
    );
  END IF;
END$$

DROP TRIGGER IF EXISTS `ocus_order_after_update_sync`$$
CREATE TRIGGER `ocus_order_after_update_sync`
AFTER UPDATE ON `ocus_order`
FOR EACH ROW
BEGIN
  DECLARE current_source ENUM('oc2', 'oc3');
  DECLARE should_sync TINYINT DEFAULT 1;
  DECLARE json_data TEXT;

  SET current_source = 'oc3';

  IF @sync_in_progress = 1 THEN
    SET should_sync = 0;
  END IF;

  IF should_sync = 1 THEN
    SET json_data = CONCAT(
      '{',
        '"order_id":', IFNULL(NEW.order_id, 'null'), ',',
        '"firstname":"', REPLACE(IFNULL(NEW.firstname, ''), '"', '\\"'), '",',
        '"lastname":"', REPLACE(IFNULL(NEW.lastname, ''), '"', '\\"'), '",',
        '"email":"', REPLACE(IFNULL(NEW.email, ''), '"', '\\"'), '",',
        '"telephone":"', REPLACE(IFNULL(NEW.telephone, ''), '"', '\\"'), '",',
        '"total":', IFNULL(NEW.total, '0'), ',',
        '"order_status_id":', IFNULL(NEW.order_status_id, '0'),
      '}'
    );

    INSERT INTO `ocus_order_sync_queue` (
      `source_db`, `table_name`, `operation`, `record_id`, `parent_order_id`,
      `data_json`, `sync_status`, `sync_ready`, `created_at`
    ) VALUES (
      current_source, 'order', 'UPDATE', NEW.order_id, NEW.order_id,
      json_data, 'pending', 1, NOW()
    );
  END IF;
END$$

DELIMITER ;

SELECT 'Order triggers (INSERT, UPDATE) installed successfully for OC3 (MySQL 5.6)' AS status;
