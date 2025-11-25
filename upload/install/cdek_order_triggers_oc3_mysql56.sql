-- ============================================
-- CDEK Order Triggers for OC3 - MySQL 5.6 Compatible
-- Syncs cdek_order data from OC3 to OC2
-- Uses CONCAT instead of JSON_OBJECT
-- Uses INSERT IGNORE to handle duplicate queue entries
-- ============================================

DELIMITER $$

DROP TRIGGER IF EXISTS `ocus_cdek_order_after_insert_sync`$$
CREATE TRIGGER `ocus_cdek_order_after_insert_sync`
AFTER INSERT ON `ocus_cdek_order`
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
        '"order_id":', IFNULL(NEW.order_id, 'null'), ',',
        '"dispatch_id":', IFNULL(NEW.dispatch_id, 'null'), ',',
        '"act_number":"', REPLACE(IFNULL(NEW.act_number, ''), '"', '\\"'), '",',
        '"dispatch_number":"', REPLACE(IFNULL(NEW.dispatch_number, ''), '"', '\\"'), '",',
        '"cdek_number":"', REPLACE(IFNULL(NEW.cdek_number, ''), '"', '\\"'), '",',
        '"return_dispatch_number":"', REPLACE(IFNULL(NEW.return_dispatch_number, ''), '"', '\\"'), '",',
        '"city_id":', IFNULL(NEW.city_id, '0'), ',',
        '"city_name":"', REPLACE(IFNULL(NEW.city_name, ''), '"', '\\"'), '",',
        '"city_postcode":', IFNULL(NEW.city_postcode, 'null'), ',',
        '"recipient_city_id":', IFNULL(NEW.recipient_city_id, '0'), ',',
        '"recipient_city_name":"', REPLACE(IFNULL(NEW.recipient_city_name, ''), '"', '\\"'), '",',
        '"recipient_city_postcode":', IFNULL(NEW.recipient_city_postcode, 'null'), ',',
        '"recipient_name":"', REPLACE(IFNULL(NEW.recipient_name, ''), '"', '\\"'), '",',
        '"recipient_email":"', REPLACE(IFNULL(NEW.recipient_email, ''), '"', '\\"'), '",',
        '"phone":"', REPLACE(IFNULL(NEW.phone, ''), '"', '\\"'), '",',
        '"tariff_id":', IFNULL(NEW.tariff_id, '0'), ',',
        '"mode_id":', IFNULL(NEW.mode_id, '0'), ',',
        '"status_id":"', REPLACE(IFNULL(NEW.status_id, ''), '"', '\\"'), '",',
        '"reason_id":', IFNULL(NEW.reason_id, '0'), ',',
        '"delay_id":', IFNULL(NEW.delay_id, 'null'), ',',
        '"delivery_recipient_cost":', IFNULL(NEW.delivery_recipient_cost, '0.0000'), ',',
        '"cod":', IFNULL(NEW.cod, '0.0000'), ',',
        '"cod_fact":', IFNULL(NEW.cod_fact, '0.0000'), ',',
        '"comment":"', REPLACE(IFNULL(NEW.comment, ''), '"', '\\"'), '",',
        '"seller_name":"', REPLACE(IFNULL(NEW.seller_name, ''), '"', '\\"'), '",',
        '"address_street":"', REPLACE(IFNULL(NEW.address_street, ''), '"', '\\"'), '",',
        '"address_house":"', REPLACE(IFNULL(NEW.address_house, ''), '"', '\\"'), '",',
        '"address_flat":"', REPLACE(IFNULL(NEW.address_flat, ''), '"', '\\"'), '",',
        '"address_pvz_code":"', REPLACE(IFNULL(NEW.address_pvz_code, ''), '"', '\\"'), '",',
        '"delivery_cost":', IFNULL(NEW.delivery_cost, '0.0000'), ',',
        '"delivery_last_change":"', REPLACE(IFNULL(NEW.delivery_last_change, ''), '"', '\\"'), '",',
        '"delivery_date":"', REPLACE(IFNULL(NEW.delivery_date, ''), '"', '\\"'), '",',
        '"delivery_recipient_name":"', REPLACE(IFNULL(NEW.delivery_recipient_name, ''), '"', '\\"'), '",',
        '"currency":"', REPLACE(IFNULL(NEW.currency, 'RUB'), '"', '\\"'), '",',
        '"currency_cod":"', REPLACE(IFNULL(NEW.currency_cod, 'RUB'), '"', '\\"'), '",',
        '"last_exchange":"', REPLACE(IFNULL(NEW.last_exchange, ''), '"', '\\"'), '"',
      '}'
    );

    INSERT IGNORE INTO `ocus_order_sync_queue` (
      `source_db`, `table_name`, `operation`, `record_id`, `parent_order_id`, `data_json`, `sync_status`
    ) VALUES (
      current_source, 'cdek_order', 'INSERT', NEW.order_id, NEW.order_id,
      json_data, 'pending'
    );
  END IF;
END$$

DROP TRIGGER IF EXISTS `ocus_cdek_order_after_update_sync`$$
CREATE TRIGGER `ocus_cdek_order_after_update_sync`
AFTER UPDATE ON `ocus_cdek_order`
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
        '"order_id":', IFNULL(NEW.order_id, 'null'), ',',
        '"dispatch_id":', IFNULL(NEW.dispatch_id, 'null'), ',',
        '"act_number":"', REPLACE(IFNULL(NEW.act_number, ''), '"', '\\"'), '",',
        '"dispatch_number":"', REPLACE(IFNULL(NEW.dispatch_number, ''), '"', '\\"'), '",',
        '"cdek_number":"', REPLACE(IFNULL(NEW.cdek_number, ''), '"', '\\"'), '",',
        '"return_dispatch_number":"', REPLACE(IFNULL(NEW.return_dispatch_number, ''), '"', '\\"'), '",',
        '"city_id":', IFNULL(NEW.city_id, '0'), ',',
        '"city_name":"', REPLACE(IFNULL(NEW.city_name, ''), '"', '\\"'), '",',
        '"city_postcode":', IFNULL(NEW.city_postcode, 'null'), ',',
        '"recipient_city_id":', IFNULL(NEW.recipient_city_id, '0'), ',',
        '"recipient_city_name":"', REPLACE(IFNULL(NEW.recipient_city_name, ''), '"', '\\"'), '",',
        '"recipient_city_postcode":', IFNULL(NEW.recipient_city_postcode, 'null'), ',',
        '"recipient_name":"', REPLACE(IFNULL(NEW.recipient_name, ''), '"', '\\"'), '",',
        '"recipient_email":"', REPLACE(IFNULL(NEW.recipient_email, ''), '"', '\\"'), '",',
        '"phone":"', REPLACE(IFNULL(NEW.phone, ''), '"', '\\"'), '",',
        '"tariff_id":', IFNULL(NEW.tariff_id, '0'), ',',
        '"mode_id":', IFNULL(NEW.mode_id, '0'), ',',
        '"status_id":"', REPLACE(IFNULL(NEW.status_id, ''), '"', '\\"'), '",',
        '"reason_id":', IFNULL(NEW.reason_id, '0'), ',',
        '"delay_id":', IFNULL(NEW.delay_id, 'null'), ',',
        '"delivery_recipient_cost":', IFNULL(NEW.delivery_recipient_cost, '0.0000'), ',',
        '"cod":', IFNULL(NEW.cod, '0.0000'), ',',
        '"cod_fact":', IFNULL(NEW.cod_fact, '0.0000'), ',',
        '"comment":"', REPLACE(IFNULL(NEW.comment, ''), '"', '\\"'), '",',
        '"seller_name":"', REPLACE(IFNULL(NEW.seller_name, ''), '"', '\\"'), '",',
        '"address_street":"', REPLACE(IFNULL(NEW.address_street, ''), '"', '\\"'), '",',
        '"address_house":"', REPLACE(IFNULL(NEW.address_house, ''), '"', '\\"'), '",',
        '"address_flat":"', REPLACE(IFNULL(NEW.address_flat, ''), '"', '\\"'), '",',
        '"address_pvz_code":"', REPLACE(IFNULL(NEW.address_pvz_code, ''), '"', '\\"'), '",',
        '"delivery_cost":', IFNULL(NEW.delivery_cost, '0.0000'), ',',
        '"delivery_last_change":"', REPLACE(IFNULL(NEW.delivery_last_change, ''), '"', '\\"'), '",',
        '"delivery_date":"', REPLACE(IFNULL(NEW.delivery_date, ''), '"', '\\"'), '",',
        '"delivery_recipient_name":"', REPLACE(IFNULL(NEW.delivery_recipient_name, ''), '"', '\\"'), '",',
        '"currency":"', REPLACE(IFNULL(NEW.currency, 'RUB'), '"', '\\"'), '",',
        '"currency_cod":"', REPLACE(IFNULL(NEW.currency_cod, 'RUB'), '"', '\\"'), '",',
        '"last_exchange":"', REPLACE(IFNULL(NEW.last_exchange, ''), '"', '\\"'), '"',
      '}'
    );

    INSERT IGNORE INTO `ocus_order_sync_queue` (
      `source_db`, `table_name`, `operation`, `record_id`, `parent_order_id`, `data_json`, `sync_status`
    ) VALUES (
      current_source, 'cdek_order', 'UPDATE', NEW.order_id, NEW.order_id,
      json_data, 'pending'
    );
  END IF;
END$$

DELIMITER ;

SELECT 'CDEK order triggers (INSERT, UPDATE) installed successfully for OC3 (MySQL 5.6)' AS status;
