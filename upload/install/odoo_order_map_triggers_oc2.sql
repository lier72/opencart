-- ============================================
-- Odoo Order Map Sync Triggers (OC2)
-- Syncs odoo_order_map from OC2 to OC3
-- ============================================

DROP TRIGGER IF EXISTS `ocus_odoo_order_map_after_insert_sync`;
DROP TRIGGER IF EXISTS `ocus_odoo_order_map_after_update_sync`;

DELIMITER $$

CREATE TRIGGER `ocus_odoo_order_map_after_insert_sync`
AFTER INSERT ON `ocus_odoo_order_map`
FOR EACH ROW
BEGIN
  DECLARE current_source ENUM('oc2', 'oc3');
  DECLARE should_sync TINYINT DEFAULT 1;

  SET current_source = 'oc2';

  -- Check if sync is in progress
  IF @sync_in_progress = 1 THEN
    SET should_sync = 0;
  END IF;

  -- Check if the related order is being synced
  IF EXISTS (
    SELECT 1 FROM ocus_order_id_map
    WHERE oc2_order_id = NEW.opencart_order_id AND sync_lock = 1
  ) THEN
    SET should_sync = 0;
  END IF;

  IF should_sync = 1 THEN
    INSERT INTO `ocus_order_sync_queue` (
      `source_db`, `table_name`, `operation`, `record_id`, `parent_order_id`,
      `data_json`, `sync_status`
    ) VALUES (
      current_source, 'odoo_order_map', 'INSERT', NEW.id, NEW.opencart_order_id,
      JSON_OBJECT(
        'id', NEW.id,
        'opencart_order_id', NEW.opencart_order_id,
        'odoo_order_id', NEW.odoo_order_id,
        'opencart_order_state', NEW.opencart_order_state,
        'odoo_order_state', NEW.odoo_order_state,
        'created_by', NEW.created_by,
        'modified_on', NEW.modified_on,
        'is_sync', NEW.is_sync
      ), 'pending'
    );
  END IF;
END$$

CREATE TRIGGER `ocus_odoo_order_map_after_update_sync`
AFTER UPDATE ON `ocus_odoo_order_map`
FOR EACH ROW
BEGIN
  DECLARE current_source ENUM('oc2', 'oc3');
  DECLARE should_sync TINYINT DEFAULT 1;
  DECLARE has_changes TINYINT DEFAULT 0;

  SET current_source = 'oc2';

  -- Check if sync is in progress
  IF @sync_in_progress = 1 THEN
    SET should_sync = 0;
  END IF;

  -- Check if the related order is being synced
  IF EXISTS (
    SELECT 1 FROM ocus_order_id_map
    WHERE oc2_order_id = NEW.opencart_order_id AND sync_lock = 1
  ) THEN
    SET should_sync = 0;
  END IF;

  -- Only sync if relevant fields changed
  IF NEW.opencart_order_state != OLD.opencart_order_state OR
     NEW.odoo_order_state != OLD.odoo_order_state OR
     NEW.is_sync != OLD.is_sync THEN
    SET has_changes = 1;
  END IF;

  IF should_sync = 1 AND has_changes = 1 THEN
    INSERT INTO `ocus_order_sync_queue` (
      `source_db`, `table_name`, `operation`, `record_id`, `parent_order_id`,
      `data_json`, `sync_status`
    ) VALUES (
      current_source, 'odoo_order_map', 'UPDATE', NEW.id, NEW.opencart_order_id,
      JSON_OBJECT(
        'id', NEW.id,
        'opencart_order_id', NEW.opencart_order_id,
        'odoo_order_id', NEW.odoo_order_id,
        'opencart_order_state', NEW.opencart_order_state,
        'odoo_order_state', NEW.odoo_order_state,
        'created_by', NEW.created_by,
        'modified_on', NEW.modified_on,
        'is_sync', NEW.is_sync
      ), 'pending'
    );
  END IF;
END$$

DELIMITER ;

SELECT 'Odoo order map triggers created for OC2' AS status;
