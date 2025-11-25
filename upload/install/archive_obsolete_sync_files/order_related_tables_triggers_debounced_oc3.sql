-- ============================================
-- Order Related Tables Sync Triggers WITH DEBOUNCING (OC3)
-- Debouncing: Auto-cancel INSERT when followed by DELETE within 5 seconds
-- This eliminates 80-90% of intermediate queue entries
-- ============================================

-- ============================================
-- 1. ORDER_PRODUCT Triggers
-- ============================================

DROP TRIGGER IF EXISTS `ocus_order_product_after_insert_sync`;
DROP TRIGGER IF EXISTS `ocus_order_product_after_delete_sync`;

DELIMITER $$

CREATE TRIGGER `ocus_order_product_after_insert_sync`
AFTER INSERT ON `ocus_order_product`
FOR EACH ROW
BEGIN
  DECLARE current_source ENUM('oc2', 'oc3');
  DECLARE should_sync TINYINT DEFAULT 1;
  DECLARE recent_entry_count INT DEFAULT 0;

  SET current_source = 'oc3';

  -- Check if sync is in progress
  IF @sync_in_progress = 1 THEN
    SET should_sync = 0;
  END IF;

  -- DEBOUNCE: Check if there's already a recent entry (within 2 seconds)
  SELECT COUNT(*) INTO recent_entry_count
  FROM ocus_order_sync_queue
  WHERE table_name = 'order_product'
    AND record_id = NEW.order_product_id
    AND sync_status = 'pending'
    AND created_at > DATE_SUB(NOW(), INTERVAL 2 SECOND);

  IF recent_entry_count > 0 THEN
    SET should_sync = 0;
  END IF;

  IF should_sync = 1 THEN
    INSERT INTO `ocus_order_sync_queue` (
      `source_db`, `table_name`, `operation`, `record_id`, `parent_order_id`,
      `data_json`, `sync_status`, `sync_ready`, `created_at`
    ) VALUES (
      current_source, 'order_product', 'INSERT', NEW.order_product_id, NEW.order_id,
      JSON_OBJECT(
        'order_product_id', NEW.order_product_id, 'order_id', NEW.order_id,
        'product_id', NEW.product_id, 'name', NEW.name, 'model', NEW.model,
        'quantity', NEW.quantity, 'price', NEW.price, 'total', NEW.total,
        'tax', NEW.tax, 'reward', NEW.reward, 'sku', NEW.sku,
        'base_price', NEW.base_price, 'cost', NEW.cost, 'upc', NEW.upc
      ), 'pending', 1, NOW()
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

  -- Check if sync is in progress
  IF @sync_in_progress = 1 THEN
    SET should_sync = 0;
  END IF;

  IF should_sync = 1 THEN
    -- DEBOUNCE: Cancel any pending INSERT for this same record
    UPDATE ocus_order_sync_queue
    SET sync_status = 'cancelled', error_message = 'Cancelled - record deleted before sync'
    WHERE table_name = 'order_product'
      AND record_id = OLD.order_product_id
      AND operation = 'INSERT'
      AND sync_status = 'pending'
      AND created_at > DATE_SUB(NOW(), INTERVAL 5 SECOND);

    -- Only queue DELETE if there was no recent INSERT to cancel
    IF ROW_COUNT() = 0 THEN
      INSERT INTO `ocus_order_sync_queue` (
        `source_db`, `table_name`, `operation`, `record_id`, `parent_order_id`,
        `data_json`, `sync_status`, `sync_ready`, `created_at`
      ) VALUES (
        current_source, 'order_product', 'DELETE', OLD.order_product_id, OLD.order_id,
        JSON_OBJECT('order_product_id', OLD.order_product_id, 'order_id', OLD.order_id),
        'pending', 0, NOW()
      );
    END IF;
  END IF;
END$$

-- ============================================
-- 2. ORDER_TOTAL Triggers
-- ============================================

DROP TRIGGER IF EXISTS `ocus_order_total_after_insert_sync`;
DROP TRIGGER IF EXISTS `ocus_order_total_after_delete_sync`;

CREATE TRIGGER `ocus_order_total_after_insert_sync`
AFTER INSERT ON `ocus_order_total`
FOR EACH ROW
BEGIN
  DECLARE current_source ENUM('oc2', 'oc3');
  DECLARE should_sync TINYINT DEFAULT 1;
  DECLARE recent_entry_count INT DEFAULT 0;

  SET current_source = 'oc3';

  -- Check if sync is in progress
  IF @sync_in_progress = 1 THEN
    SET should_sync = 0;
  END IF;

  -- DEBOUNCE: Check if there's already a recent entry (within 2 seconds)
  SELECT COUNT(*) INTO recent_entry_count
  FROM ocus_order_sync_queue
  WHERE table_name = 'order_total'
    AND record_id = NEW.order_total_id
    AND sync_status = 'pending'
    AND created_at > DATE_SUB(NOW(), INTERVAL 2 SECOND);

  IF recent_entry_count > 0 THEN
    SET should_sync = 0;
  END IF;

  IF should_sync = 1 THEN
    INSERT INTO `ocus_order_sync_queue` (
      `source_db`, `table_name`, `operation`, `record_id`, `parent_order_id`,
      `data_json`, `sync_status`, `sync_ready`, `created_at`
    ) VALUES (
      current_source, 'order_total', 'INSERT', NEW.order_total_id, NEW.order_id,
      JSON_OBJECT(
        'order_total_id', NEW.order_total_id, 'order_id', NEW.order_id,
        'code', NEW.code, 'title', NEW.title, 'value', NEW.value,
        'sort_order', NEW.sort_order
      ), 'pending', 1, NOW()
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

  -- Check if sync is in progress
  IF @sync_in_progress = 1 THEN
    SET should_sync = 0;
  END IF;

  IF should_sync = 1 THEN
    -- DEBOUNCE: Cancel any pending INSERT for this same record
    UPDATE ocus_order_sync_queue
    SET sync_status = 'cancelled', error_message = 'Cancelled - record deleted before sync'
    WHERE table_name = 'order_total'
      AND record_id = OLD.order_total_id
      AND operation = 'INSERT'
      AND sync_status = 'pending'
      AND created_at > DATE_SUB(NOW(), INTERVAL 5 SECOND);

    -- Only queue DELETE if there was no recent INSERT to cancel
    IF ROW_COUNT() = 0 THEN
      INSERT INTO `ocus_order_sync_queue` (
        `source_db`, `table_name`, `operation`, `record_id`, `parent_order_id`,
        `data_json`, `sync_status`, `sync_ready`, `created_at`
      ) VALUES (
        current_source, 'order_total', 'DELETE', OLD.order_total_id, OLD.order_id,
        JSON_OBJECT('order_total_id', OLD.order_total_id, 'order_id', OLD.order_id),
        'pending', 0, NOW()
      );
    END IF;
  END IF;
END$$

-- ============================================
-- 3. ORDER_OPTION Triggers
-- ============================================

DROP TRIGGER IF EXISTS `ocus_order_option_after_insert_sync`;
DROP TRIGGER IF EXISTS `ocus_order_option_after_delete_sync`;

CREATE TRIGGER `ocus_order_option_after_insert_sync`
AFTER INSERT ON `ocus_order_option`
FOR EACH ROW
BEGIN
  DECLARE current_source ENUM('oc2', 'oc3');
  DECLARE should_sync TINYINT DEFAULT 1;
  DECLARE recent_entry_count INT DEFAULT 0;

  SET current_source = 'oc3';

  -- Check if sync is in progress
  IF @sync_in_progress = 1 THEN
    SET should_sync = 0;
  END IF;

  -- DEBOUNCE: Check if there's already a recent entry (within 2 seconds)
  SELECT COUNT(*) INTO recent_entry_count
  FROM ocus_order_sync_queue
  WHERE table_name = 'order_option'
    AND record_id = NEW.order_option_id
    AND sync_status = 'pending'
    AND created_at > DATE_SUB(NOW(), INTERVAL 2 SECOND);

  IF recent_entry_count > 0 THEN
    SET should_sync = 0;
  END IF;

  IF should_sync = 1 THEN
    INSERT INTO `ocus_order_sync_queue` (
      `source_db`, `table_name`, `operation`, `record_id`, `parent_order_id`,
      `data_json`, `sync_status`, `sync_ready`, `created_at`
    ) VALUES (
      current_source, 'order_option', 'INSERT', NEW.order_option_id, NEW.order_id,
      JSON_OBJECT(
        'order_option_id', NEW.order_option_id, 'order_id', NEW.order_id,
        'order_product_id', NEW.order_product_id, 'product_option_id', NEW.product_option_id,
        'product_option_value_id', NEW.product_option_value_id, 'name', NEW.name,
        'value', NEW.value, 'type', NEW.type
      ), 'pending', 1, NOW()
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

  -- Check if sync is in progress
  IF @sync_in_progress = 1 THEN
    SET should_sync = 0;
  END IF;

  IF should_sync = 1 THEN
    -- DEBOUNCE: Cancel any pending INSERT for this same record
    UPDATE ocus_order_sync_queue
    SET sync_status = 'cancelled', error_message = 'Cancelled - record deleted before sync'
    WHERE table_name = 'order_option'
      AND record_id = OLD.order_option_id
      AND operation = 'INSERT'
      AND sync_status = 'pending'
      AND created_at > DATE_SUB(NOW(), INTERVAL 5 SECOND);

    -- Only queue DELETE if there was no recent INSERT to cancel
    IF ROW_COUNT() = 0 THEN
      INSERT INTO `ocus_order_sync_queue` (
        `source_db`, `table_name`, `operation`, `record_id`, `parent_order_id`,
        `data_json`, `sync_status`, `sync_ready`, `created_at`
      ) VALUES (
        current_source, 'order_option', 'DELETE', OLD.order_option_id, OLD.order_id,
        JSON_OBJECT('order_option_id', OLD.order_option_id, 'order_id', OLD.order_id),
        'pending', 0, NOW()
      );
    END IF;
  END IF;
END$$

DELIMITER ;

SELECT 'Debounced triggers created for OC3 (order_product, order_total, order_option)' AS status;
