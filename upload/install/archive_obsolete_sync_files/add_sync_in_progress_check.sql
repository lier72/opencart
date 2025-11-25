-- ============================================
-- Add @sync_in_progress check to ALL triggers
-- This prevents triggers from firing during sync operations
-- Run this on BOTH OC2 and OC3 databases
-- ============================================

-- For order_product INSERT trigger
DROP TRIGGER IF EXISTS `ocus_order_product_after_insert_sync`;

DELIMITER $$

CREATE TRIGGER `ocus_order_product_after_insert_sync`
AFTER INSERT ON `ocus_order_product`
FOR EACH ROW
BEGIN
  DECLARE current_source ENUM('oc2', 'oc3');
  DECLARE should_sync TINYINT DEFAULT 1;

  -- IMPORTANT: Check if sync is in progress
  IF @sync_in_progress = 1 THEN
    SET should_sync = 0;
  END IF;

  -- Determine source (set this based on which DB you're in)
  -- For OC3: SET current_source = 'oc3';
  -- For OC2: SET current_source = 'oc2';
  SET current_source = 'REPLACE_WITH_oc2_OR_oc3';

  IF EXISTS (SELECT 1 FROM ocus_order_id_map WHERE REPLACE_WITH_FIELD_order_id = NEW.order_id AND sync_lock = 1) THEN
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

DELIMITER ;

-- Show instructions
SELECT 'This is a template. Create separate files for OC2 and OC3 with proper replacements.' as note;
