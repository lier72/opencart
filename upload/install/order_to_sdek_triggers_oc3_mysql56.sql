-- ============================================
-- Order to SDEK Triggers for OC3 - MySQL 5.6 Compatible
-- Uses CONCAT instead of JSON_OBJECT
-- ============================================

DELIMITER $$

DROP TRIGGER IF EXISTS `ocus_order_to_sdek_after_insert_sync`$$
CREATE TRIGGER `ocus_order_to_sdek_after_insert_sync`
AFTER INSERT ON `ocus_order_to_sdek`
FOR EACH ROW
BEGIN
  DECLARE current_source ENUM('oc2', 'oc3');
  DECLARE should_sync TINYINT DEFAULT 1;
  DECLARE json_data TEXT;

  SET current_source = 'oc3';

  IF @sync_in_progress = 1 THEN
    SET should_sync = 0;
  END IF;

  IF EXISTS (
    SELECT 1 FROM ocus_order_id_map
    WHERE oc3_order_id = NEW.order_id AND sync_lock = 1
  ) THEN
    SET should_sync = 0;
  END IF;

  IF should_sync = 1 THEN
    SET json_data = CONCAT(
      '{',
        '"order_to_sdek_id":', IFNULL(NEW.order_to_sdek_id, 'null'), ',',
        '"order_id":', IFNULL(NEW.order_id, 'null'), ',',
        '"cityId":', IFNULL(NEW.cityId, 'null'), ',',
        '"pvz_code":"', REPLACE(IFNULL(NEW.pvz_code, ''), '"', '\\"'), '"',
      '}'
    );

    INSERT INTO `ocus_order_sync_queue` (
      `source_db`, `table_name`, `operation`, `record_id`, `parent_order_id`, `data_json`, `sync_status`
    ) VALUES (
      current_source, 'order_to_sdek', 'INSERT', NEW.order_to_sdek_id, NEW.order_id,
      json_data, 'pending'
    );
  END IF;
END$$

DROP TRIGGER IF EXISTS `ocus_order_to_sdek_after_update_sync`$$
CREATE TRIGGER `ocus_order_to_sdek_after_update_sync`
AFTER UPDATE ON `ocus_order_to_sdek`
FOR EACH ROW
BEGIN
  DECLARE current_source ENUM('oc2', 'oc3');
  DECLARE should_sync TINYINT DEFAULT 1;
  DECLARE json_data TEXT;

  SET current_source = 'oc3';

  IF @sync_in_progress = 1 THEN
    SET should_sync = 0;
  END IF;

  IF EXISTS (
    SELECT 1 FROM ocus_order_id_map
    WHERE oc3_order_id = NEW.order_id AND sync_lock = 1
  ) THEN
    SET should_sync = 0;
  END IF;

  IF should_sync = 1 THEN
    SET json_data = CONCAT(
      '{',
        '"order_to_sdek_id":', IFNULL(NEW.order_to_sdek_id, 'null'), ',',
        '"order_id":', IFNULL(NEW.order_id, 'null'), ',',
        '"cityId":', IFNULL(NEW.cityId, 'null'), ',',
        '"pvz_code":"', REPLACE(IFNULL(NEW.pvz_code, ''), '"', '\\"'), '"',
      '}'
    );

    INSERT INTO `ocus_order_sync_queue` (
      `source_db`, `table_name`, `operation`, `record_id`, `parent_order_id`, `data_json`, `sync_status`
    ) VALUES (
      current_source, 'order_to_sdek', 'UPDATE', NEW.order_to_sdek_id, NEW.order_id,
      json_data, 'pending'
    );
  END IF;
END$$

DELIMITER ;

SELECT 'Order to SDEK triggers (INSERT, UPDATE) installed successfully for OC3 (MySQL 5.6)' AS status;
