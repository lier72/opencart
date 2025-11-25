-- ============================================
-- Order to SDEK Sync Triggers (OC2)
-- Syncs order_to_sdek (CDEK shipping data) from OC2 to OC3
-- ============================================

DROP TRIGGER IF EXISTS `ocus_order_to_sdek_after_insert_sync`;
DROP TRIGGER IF EXISTS `ocus_order_to_sdek_after_update_sync`;

DELIMITER $$

CREATE TRIGGER `ocus_order_to_sdek_after_insert_sync`
AFTER INSERT ON `ocus_order_to_sdek`
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
    WHERE oc2_order_id = NEW.order_id AND sync_lock = 1
  ) THEN
    SET should_sync = 0;
  END IF;

  IF should_sync = 1 THEN
    INSERT INTO `ocus_order_sync_queue` (
      `source_db`, `table_name`, `operation`, `record_id`, `parent_order_id`,
      `data_json`, `sync_status`
    ) VALUES (
      current_source, 'order_to_sdek', 'INSERT', NEW.order_to_sdek_id, NEW.order_id,
      JSON_OBJECT(
        'order_to_sdek_id', NEW.order_to_sdek_id,
        'order_id', NEW.order_id,
        'cityId', NEW.cityId,
        'pvz_code', NEW.pvz_code
      ), 'pending'
    );
  END IF;
END$$

CREATE TRIGGER `ocus_order_to_sdek_after_update_sync`
AFTER UPDATE ON `ocus_order_to_sdek`
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
    WHERE oc2_order_id = NEW.order_id AND sync_lock = 1
  ) THEN
    SET should_sync = 0;
  END IF;

  IF should_sync = 1 THEN
    INSERT INTO `ocus_order_sync_queue` (
      `source_db`, `table_name`, `operation`, `record_id`, `parent_order_id`,
      `data_json`, `sync_status`
    ) VALUES (
      current_source, 'order_to_sdek', 'UPDATE', NEW.order_to_sdek_id, NEW.order_id,
      JSON_OBJECT(
        'order_to_sdek_id', NEW.order_to_sdek_id,
        'order_id', NEW.order_id,
        'cityId', NEW.cityId,
        'pvz_code', NEW.pvz_code
      ), 'pending'
    );
  END IF;
END$$

DELIMITER ;

SELECT 'Order to SDEK triggers created for OC2' AS status;
