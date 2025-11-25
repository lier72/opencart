-- ============================================
-- Order Related Tables Sync Triggers
-- Tables: order_product, order_option, order_total, odoo_order_map, order_to_sdek
-- ============================================

-- ============================================
-- 1. ORDER_PRODUCT Triggers
-- ============================================

DROP TRIGGER IF EXISTS `ocus_order_product_after_insert_sync`;
DROP TRIGGER IF EXISTS `ocus_order_product_after_update_sync`;
DROP TRIGGER IF EXISTS `ocus_order_product_after_delete_sync`;

DELIMITER $$

CREATE TRIGGER `ocus_order_product_after_insert_sync`
AFTER INSERT ON `ocus_order_product`
FOR EACH ROW
BEGIN
  DECLARE current_source ENUM('oc2', 'oc3');
  DECLARE should_sync TINYINT DEFAULT 1;

  SET current_source = 'oc3';

  IF EXISTS (SELECT 1 FROM ocus_order_id_map WHERE oc3_order_id = NEW.order_id AND sync_lock = 1) THEN
    SET should_sync = 0;
  END IF;

  IF should_sync = 1 THEN
    INSERT INTO `ocus_order_sync_queue` (
      `source_db`, `table_name`, `operation`, `record_id`, `parent_order_id`, `data_json`, `sync_status`
    ) VALUES (
      current_source, 'order_product', 'INSERT', NEW.order_product_id, NEW.order_id,
      JSON_OBJECT(
        'order_product_id', NEW.order_product_id, 'order_id', NEW.order_id, 'product_id', NEW.product_id,
        'name', NEW.name, 'model', NEW.model, 'quantity', NEW.quantity, 'price', NEW.price,
        'total', NEW.total, 'tax', NEW.tax, 'reward', NEW.reward, 'sku', NEW.sku,
        'base_price', NEW.base_price, 'cost', NEW.cost, 'upc', NEW.upc
      ), 'pending'
    );
  END IF;
END$$

CREATE TRIGGER `ocus_order_product_after_delete_sync`
AFTER DELETE ON `ocus_order_product`
FOR EACH ROW
BEGIN
  DECLARE current_source ENUM('oc2', 'oc3');
  DECLARE should_sync TINYINT DEFAULT 1;

  SET current_source = 'oc3';

  IF EXISTS (SELECT 1 FROM ocus_order_id_map WHERE oc3_order_id = OLD.order_id AND sync_lock = 1) THEN
    SET should_sync = 0;
  END IF;

  IF should_sync = 1 THEN
    INSERT INTO `ocus_order_sync_queue` (
      `source_db`, `table_name`, `operation`, `record_id`, `parent_order_id`, `data_json`, `sync_status`
    ) VALUES (
      current_source, 'order_product', 'DELETE', OLD.order_product_id, OLD.order_id,
      JSON_OBJECT('order_product_id', OLD.order_product_id, 'order_id', OLD.order_id), 'pending'
    );
  END IF;
END$$

-- ============================================
-- 2. ORDER_OPTION Triggers
-- ============================================

DROP TRIGGER IF EXISTS `ocus_order_option_after_insert_sync`;
DROP TRIGGER IF EXISTS `ocus_order_option_after_delete_sync`;

CREATE TRIGGER `ocus_order_option_after_insert_sync`
AFTER INSERT ON `ocus_order_option`
FOR EACH ROW
BEGIN
  DECLARE current_source ENUM('oc2', 'oc3');
  DECLARE should_sync TINYINT DEFAULT 1;

  SET current_source = 'oc3';

  IF EXISTS (SELECT 1 FROM ocus_order_id_map WHERE oc3_order_id = NEW.order_id AND sync_lock = 1) THEN
    SET should_sync = 0;
  END IF;

  IF should_sync = 1 THEN
    INSERT INTO `ocus_order_sync_queue` (
      `source_db`, `table_name`, `operation`, `record_id`, `parent_order_id`, `data_json`, `sync_status`
    ) VALUES (
      current_source, 'order_option', 'INSERT', NEW.order_option_id, NEW.order_id,
      JSON_OBJECT(
        'order_option_id', NEW.order_option_id, 'order_id', NEW.order_id,
        'order_product_id', NEW.order_product_id, 'product_option_id', NEW.product_option_id,
        'product_option_value_id', NEW.product_option_value_id, 'name', NEW.name,
        'value', NEW.value, 'type', NEW.type
      ), 'pending'
    );
  END IF;
END$$

CREATE TRIGGER `ocus_order_option_after_delete_sync`
AFTER DELETE ON `ocus_order_option`
FOR EACH ROW
BEGIN
  DECLARE current_source ENUM('oc2', 'oc3');
  DECLARE should_sync TINYINT DEFAULT 1;

  SET current_source = 'oc3';

  IF EXISTS (SELECT 1 FROM ocus_order_id_map WHERE oc3_order_id = OLD.order_id AND sync_lock = 1) THEN
    SET should_sync = 0;
  END IF;

  IF should_sync = 1 THEN
    INSERT INTO `ocus_order_sync_queue` (
      `source_db`, `table_name`, `operation`, `record_id`, `parent_order_id`, `data_json`, `sync_status`
    ) VALUES (
      current_source, 'order_option', 'DELETE', OLD.order_option_id, OLD.order_id,
      JSON_OBJECT('order_option_id', OLD.order_option_id, 'order_id', OLD.order_id), 'pending'
    );
  END IF;
END$$

-- ============================================
-- 3. ORDER_TOTAL Triggers
-- ============================================

DROP TRIGGER IF EXISTS `ocus_order_total_after_insert_sync`;
DROP TRIGGER IF EXISTS `ocus_order_total_after_delete_sync`;

CREATE TRIGGER `ocus_order_total_after_insert_sync`
AFTER INSERT ON `ocus_order_total`
FOR EACH ROW
BEGIN
  DECLARE current_source ENUM('oc2', 'oc3');
  DECLARE should_sync TINYINT DEFAULT 1;

  SET current_source = 'oc3';

  IF EXISTS (SELECT 1 FROM ocus_order_id_map WHERE oc3_order_id = NEW.order_id AND sync_lock = 1) THEN
    SET should_sync = 0;
  END IF;

  IF should_sync = 1 THEN
    INSERT INTO `ocus_order_sync_queue` (
      `source_db`, `table_name`, `operation`, `record_id`, `parent_order_id`, `data_json`, `sync_status`
    ) VALUES (
      current_source, 'order_total', 'INSERT', NEW.order_total_id, NEW.order_id,
      JSON_OBJECT(
        'order_total_id', NEW.order_total_id, 'order_id', NEW.order_id,
        'code', NEW.code, 'title', NEW.title, 'value', NEW.value, 'sort_order', NEW.sort_order
      ), 'pending'
    );
  END IF;
END$$

CREATE TRIGGER `ocus_order_total_after_delete_sync`
AFTER DELETE ON `ocus_order_total`
FOR EACH ROW
BEGIN
  DECLARE current_source ENUM('oc2', 'oc3');
  DECLARE should_sync TINYINT DEFAULT 1;

  SET current_source = 'oc3';

  IF EXISTS (SELECT 1 FROM ocus_order_id_map WHERE oc3_order_id = OLD.order_id AND sync_lock = 1) THEN
    SET should_sync = 0;
  END IF;

  IF should_sync = 1 THEN
    INSERT INTO `ocus_order_sync_queue` (
      `source_db`, `table_name`, `operation`, `record_id`, `parent_order_id`, `data_json`, `sync_status`
    ) VALUES (
      current_source, 'order_total', 'DELETE', OLD.order_total_id, OLD.order_id,
      JSON_OBJECT('order_total_id', OLD.order_total_id, 'order_id', OLD.order_id), 'pending'
    );
  END IF;
END$$

-- ============================================
-- 4. ODOO_ORDER_MAP Triggers
-- ============================================

DROP TRIGGER IF EXISTS `ocus_odoo_order_map_after_insert_sync`;
DROP TRIGGER IF EXISTS `ocus_odoo_order_map_after_update_sync`;

CREATE TRIGGER `ocus_odoo_order_map_after_insert_sync`
AFTER INSERT ON `ocus_odoo_order_map`
FOR EACH ROW
BEGIN
  DECLARE current_source ENUM('oc2', 'oc3');
  DECLARE should_sync TINYINT DEFAULT 1;

  SET current_source = 'oc3';

  IF EXISTS (SELECT 1 FROM ocus_order_id_map WHERE oc3_order_id = NEW.opencart_order_id AND sync_lock = 1) THEN
    SET should_sync = 0;
  END IF;

  IF should_sync = 1 THEN
    INSERT INTO `ocus_order_sync_queue` (
      `source_db`, `table_name`, `operation`, `record_id`, `parent_order_id`, `data_json`, `sync_status`
    ) VALUES (
      current_source, 'odoo_order_map', 'INSERT', NEW.id, NEW.opencart_order_id,
      JSON_OBJECT(
        'id', NEW.id, 'opencart_order_id', NEW.opencart_order_id, 'odoo_order_id', NEW.odoo_order_id,
        'opencart_order_state', NEW.opencart_order_state, 'odoo_order_state', NEW.odoo_order_state,
        'created_by', NEW.created_by, 'modified_on', NEW.modified_on, 'is_sync', NEW.is_sync
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

  SET current_source = 'oc3';

  IF EXISTS (SELECT 1 FROM ocus_order_id_map WHERE oc3_order_id = NEW.opencart_order_id AND sync_lock = 1) THEN
    SET should_sync = 0;
  END IF;

  IF NEW.opencart_order_state != OLD.opencart_order_state OR
     NEW.odoo_order_state != OLD.odoo_order_state OR
     NEW.is_sync != OLD.is_sync THEN
    SET has_changes = 1;
  END IF;

  IF should_sync = 1 AND has_changes = 1 THEN
    INSERT INTO `ocus_order_sync_queue` (
      `source_db`, `table_name`, `operation`, `record_id`, `parent_order_id`, `data_json`, `sync_status`
    ) VALUES (
      current_source, 'odoo_order_map', 'UPDATE', NEW.id, NEW.opencart_order_id,
      JSON_OBJECT(
        'id', NEW.id, 'opencart_order_id', NEW.opencart_order_id, 'odoo_order_id', NEW.odoo_order_id,
        'opencart_order_state', NEW.opencart_order_state, 'odoo_order_state', NEW.odoo_order_state,
        'created_by', NEW.created_by, 'modified_on', NEW.modified_on, 'is_sync', NEW.is_sync
      ), 'pending'
    );
  END IF;
END$$

-- ============================================
-- 5. ORDER_TO_SDEK Triggers
-- ============================================

DROP TRIGGER IF EXISTS `ocus_order_to_sdek_after_insert_sync`;
DROP TRIGGER IF EXISTS `ocus_order_to_sdek_after_update_sync`;

CREATE TRIGGER `ocus_order_to_sdek_after_insert_sync`
AFTER INSERT ON `ocus_order_to_sdek`
FOR EACH ROW
BEGIN
  DECLARE current_source ENUM('oc2', 'oc3');
  DECLARE should_sync TINYINT DEFAULT 1;

  SET current_source = 'oc3';

  IF EXISTS (SELECT 1 FROM ocus_order_id_map WHERE oc3_order_id = NEW.order_id AND sync_lock = 1) THEN
    SET should_sync = 0;
  END IF;

  IF should_sync = 1 THEN
    INSERT INTO `ocus_order_sync_queue` (
      `source_db`, `table_name`, `operation`, `record_id`, `parent_order_id`, `data_json`, `sync_status`
    ) VALUES (
      current_source, 'order_to_sdek', 'INSERT', NEW.order_to_sdek_id, NEW.order_id,
      JSON_OBJECT(
        'order_to_sdek_id', NEW.order_to_sdek_id, 'order_id', NEW.order_id,
        'cityId', NEW.cityId, 'pvz_code', NEW.pvz_code
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

  SET current_source = 'oc3';

  IF EXISTS (SELECT 1 FROM ocus_order_id_map WHERE oc3_order_id = NEW.order_id AND sync_lock = 1) THEN
    SET should_sync = 0;
  END IF;

  IF should_sync = 1 THEN
    INSERT INTO `ocus_order_sync_queue` (
      `source_db`, `table_name`, `operation`, `record_id`, `parent_order_id`, `data_json`, `sync_status`
    ) VALUES (
      current_source, 'order_to_sdek', 'UPDATE', NEW.order_to_sdek_id, NEW.order_id,
      JSON_OBJECT(
        'order_to_sdek_id', NEW.order_to_sdek_id, 'order_id', NEW.order_id,
        'cityId', NEW.cityId, 'pvz_code', NEW.pvz_code
      ), 'pending'
    );
  END IF;
END$$

DELIMITER ;

-- ============================================
-- Verify triggers were created
-- ============================================
SELECT 'All related table triggers created successfully' AS status;
SELECT TRIGGER_NAME, EVENT_MANIPULATION, EVENT_OBJECT_TABLE
FROM information_schema.TRIGGERS
WHERE TRIGGER_SCHEMA = DATABASE()
  AND EVENT_OBJECT_TABLE IN ('ocus_order_product', 'ocus_order_option', 'ocus_order_total', 'ocus_odoo_order_map', 'ocus_order_to_sdek')
ORDER BY EVENT_OBJECT_TABLE, EVENT_MANIPULATION;
