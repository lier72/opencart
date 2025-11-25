-- ============================================
-- Odoo Order Map Triggers for OC3 - MySQL 5.6 Compatible
-- Uses CONCAT instead of JSON_OBJECT
-- ============================================

DELIMITER $$

DROP TRIGGER IF EXISTS `ocus_odoo_order_map_after_insert_sync`$$
CREATE TRIGGER `ocus_odoo_order_map_after_insert_sync`
AFTER INSERT ON `ocus_odoo_order_map`
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
    WHERE oc3_order_id = NEW.opencart_order_id AND sync_lock = 1
  ) THEN
    SET should_sync = 0;
  END IF;

  IF should_sync = 1 THEN
    SET json_data = CONCAT(
      '{',
        '"id":', IFNULL(NEW.id, 'null'), ',',
        '"opencart_order_id":', IFNULL(NEW.opencart_order_id, 'null'), ',',
        '"odoo_order_id":', IFNULL(NEW.odoo_order_id, 'null'), ',',
        '"opencart_order_state":"', REPLACE(IFNULL(NEW.opencart_order_state, ''), '"', '\\"'), '",',
        '"odoo_order_state":"', REPLACE(IFNULL(NEW.odoo_order_state, ''), '"', '\\"'), '",',
        '"created_by":"', REPLACE(IFNULL(NEW.created_by, ''), '"', '\\"'), '",',
        '"modified_on":"', IFNULL(NEW.modified_on, ''), '",',
        '"is_sync":', IFNULL(NEW.is_sync, '0'),
      '}'
    );

    INSERT INTO `ocus_order_sync_queue` (
      `source_db`, `table_name`, `operation`, `record_id`, `parent_order_id`, `data_json`, `sync_status`
    ) VALUES (
      current_source, 'odoo_order_map', 'INSERT', NEW.id, NEW.opencart_order_id,
      json_data, 'pending'
    );
  END IF;
END$$

DROP TRIGGER IF EXISTS `ocus_odoo_order_map_after_update_sync`$$
CREATE TRIGGER `ocus_odoo_order_map_after_update_sync`
AFTER UPDATE ON `ocus_odoo_order_map`
FOR EACH ROW
BEGIN
  DECLARE current_source ENUM('oc2', 'oc3');
  DECLARE should_sync TINYINT DEFAULT 1;
  DECLARE has_changes TINYINT DEFAULT 0;
  DECLARE json_data TEXT;

  SET current_source = 'oc3';

  IF @sync_in_progress = 1 THEN
    SET should_sync = 0;
  END IF;

  IF EXISTS (
    SELECT 1 FROM ocus_order_id_map
    WHERE oc3_order_id = NEW.opencart_order_id AND sync_lock = 1
  ) THEN
    SET should_sync = 0;
  END IF;

  IF NEW.opencart_order_state != OLD.opencart_order_state OR
     NEW.odoo_order_state != OLD.odoo_order_state OR
     NEW.is_sync != OLD.is_sync THEN
    SET has_changes = 1;
  END IF;

  IF should_sync = 1 AND has_changes = 1 THEN
    SET json_data = CONCAT(
      '{',
        '"id":', IFNULL(NEW.id, 'null'), ',',
        '"opencart_order_id":', IFNULL(NEW.opencart_order_id, 'null'), ',',
        '"odoo_order_id":', IFNULL(NEW.odoo_order_id, 'null'), ',',
        '"opencart_order_state":"', REPLACE(IFNULL(NEW.opencart_order_state, ''), '"', '\\"'), '",',
        '"odoo_order_state":"', REPLACE(IFNULL(NEW.odoo_order_state, ''), '"', '\\"'), '",',
        '"created_by":"', REPLACE(IFNULL(NEW.created_by, ''), '"', '\\"'), '",',
        '"modified_on":"', IFNULL(NEW.modified_on, ''), '",',
        '"is_sync":', IFNULL(NEW.is_sync, '0'),
      '}'
    );

    INSERT INTO `ocus_order_sync_queue` (
      `source_db`, `table_name`, `operation`, `record_id`, `parent_order_id`, `data_json`, `sync_status`
    ) VALUES (
      current_source, 'odoo_order_map', 'UPDATE', NEW.id, NEW.opencart_order_id,
      json_data, 'pending'
    );
  END IF;
END$$

DELIMITER ;

SELECT 'Odoo order map triggers (INSERT, UPDATE) installed successfully for OC3 (MySQL 5.6)' AS status;
