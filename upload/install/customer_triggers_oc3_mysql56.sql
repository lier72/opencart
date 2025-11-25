-- ============================================
-- Customer Sync Triggers for OC3 - MySQL 5.6 Compatible
-- Syncs customer data from OC3 to OC2
-- Uses CONCAT instead of JSON_OBJECT
-- ============================================

DELIMITER $$

DROP TRIGGER IF EXISTS `ocus_customer_after_insert_sync`$$
CREATE TRIGGER `ocus_customer_after_insert_sync`
AFTER INSERT ON `ocus_customer`
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
        '"customer_id":', IFNULL(NEW.customer_id, 'null'), ',',
        '"customer_group_id":', IFNULL(NEW.customer_group_id, '0'), ',',
        '"store_id":', IFNULL(NEW.store_id, '0'), ',',
        '"language_id":', IFNULL(NEW.language_id, '0'), ',',
        '"firstname":"', REPLACE(IFNULL(NEW.firstname, ''), '"', '\\"'), '",',
        '"lastname":"', REPLACE(IFNULL(NEW.lastname, ''), '"', '\\"'), '",',
        '"email":"', REPLACE(IFNULL(NEW.email, ''), '"', '\\"'), '",',
        '"telephone":"', REPLACE(IFNULL(NEW.telephone, ''), '"', '\\"'), '",',
        '"fax":"', REPLACE(IFNULL(NEW.fax, ''), '"', '\\"'), '",',
        '"password":"', REPLACE(IFNULL(NEW.password, ''), '"', '\\"'), '",',
        '"salt":"', REPLACE(IFNULL(NEW.salt, ''), '"', '\\"'), '",',
        '"newsletter":', IFNULL(NEW.newsletter, '0'), ',',
        '"address_id":', IFNULL(NEW.address_id, '0'), ',',
        '"custom_field":"', REPLACE(IFNULL(NEW.custom_field, ''), '"', '\\"'), '",',
        '"ip":"', REPLACE(IFNULL(NEW.ip, ''), '"', '\\"'), '",',
        '"status":', IFNULL(NEW.status, '0'), ',',
        '"safe":', IFNULL(NEW.safe, '0'), ',',
        '"token":"', REPLACE(IFNULL(NEW.token, ''), '"', '\\"'), '",',
        '"code":"', REPLACE(IFNULL(NEW.code, ''), '"', '\\"'), '",',
        '"date_added":"', IFNULL(NEW.date_added, ''), '",',
        '"approved":', IFNULL(NEW.approved, '0'),
      '}'
    );

    INSERT INTO `ocus_order_sync_queue` (
      `source_db`, `table_name`, `operation`, `record_id`, `parent_order_id`, `data_json`, `sync_status`
    ) VALUES (
      current_source, 'customer', 'INSERT', NEW.customer_id, NULL,
      json_data, 'pending'
    );
  END IF;
END$$

DROP TRIGGER IF EXISTS `ocus_customer_after_update_sync`$$
CREATE TRIGGER `ocus_customer_after_update_sync`
AFTER UPDATE ON `ocus_customer`
FOR EACH ROW
BEGIN
  DECLARE current_source ENUM('oc2', 'oc3');
  DECLARE should_sync TINYINT DEFAULT 1;
  DECLARE json_data TEXT;

  SET current_source = 'oc3';

  IF @sync_in_progress = 1 THEN
    SET should_sync = 0;
  END IF;

  -- Only sync if significant fields have changed (ignore session fields like ip, token, cart, wishlist)
  IF should_sync = 1 THEN
    IF (OLD.customer_group_id = NEW.customer_group_id AND
        OLD.store_id = NEW.store_id AND
        OLD.language_id = NEW.language_id AND
        OLD.firstname = NEW.firstname AND
        OLD.lastname = NEW.lastname AND
        OLD.email = NEW.email AND
        OLD.telephone = NEW.telephone AND
        OLD.fax = NEW.fax AND
        OLD.password = NEW.password AND
        OLD.salt = NEW.salt AND
        OLD.newsletter = NEW.newsletter AND
        OLD.address_id = NEW.address_id AND
        OLD.custom_field = NEW.custom_field AND
        OLD.status = NEW.status AND
        OLD.approved = NEW.approved AND
        OLD.safe = NEW.safe AND
        OLD.code = NEW.code) THEN
      SET should_sync = 0;
    END IF;
  END IF;

  IF should_sync = 1 THEN
    SET json_data = CONCAT(
      '{',
        '"customer_id":', IFNULL(NEW.customer_id, 'null'), ',',
        '"customer_group_id":', IFNULL(NEW.customer_group_id, '0'), ',',
        '"store_id":', IFNULL(NEW.store_id, '0'), ',',
        '"language_id":', IFNULL(NEW.language_id, '0'), ',',
        '"firstname":"', REPLACE(IFNULL(NEW.firstname, ''), '"', '\\"'), '",',
        '"lastname":"', REPLACE(IFNULL(NEW.lastname, ''), '"', '\\"'), '",',
        '"email":"', REPLACE(IFNULL(NEW.email, ''), '"', '\\"'), '",',
        '"telephone":"', REPLACE(IFNULL(NEW.telephone, ''), '"', '\\"'), '",',
        '"fax":"', REPLACE(IFNULL(NEW.fax, ''), '"', '\\"'), '",',
        '"password":"', REPLACE(IFNULL(NEW.password, ''), '"', '\\"'), '",',
        '"salt":"', REPLACE(IFNULL(NEW.salt, ''), '"', '\\"'), '",',
        '"newsletter":', IFNULL(NEW.newsletter, '0'), ',',
        '"address_id":', IFNULL(NEW.address_id, '0'), ',',
        '"custom_field":"', REPLACE(IFNULL(NEW.custom_field, ''), '"', '\\"'), '",',
        '"ip":"', REPLACE(IFNULL(NEW.ip, ''), '"', '\\"'), '",',
        '"status":', IFNULL(NEW.status, '0'), ',',
        '"safe":', IFNULL(NEW.safe, '0'), ',',
        '"token":"', REPLACE(IFNULL(NEW.token, ''), '"', '\\"'), '",',
        '"code":"', REPLACE(IFNULL(NEW.code, ''), '"', '\\"'), '",',
        '"date_added":"', IFNULL(NEW.date_added, ''), '",',
        '"approved":', IFNULL(NEW.approved, '0'),
      '}'
    );

    INSERT INTO `ocus_order_sync_queue` (
      `source_db`, `table_name`, `operation`, `record_id`, `parent_order_id`, `data_json`, `sync_status`
    ) VALUES (
      current_source, 'customer', 'UPDATE', NEW.customer_id, NULL,
      json_data, 'pending'
    );
  END IF;
END$$

DELIMITER ;

SELECT 'Customer triggers (INSERT, UPDATE) installed successfully for OC3 (MySQL 5.6)' AS status;
