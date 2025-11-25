-- ============================================
-- Delayed Sync Strategy
-- Only sync orders after they're "complete" (not during creation)
-- ============================================

-- CONCEPT: Add a "sync_ready" flag that gets set when order is fully created
-- This prevents syncing intermediate steps during order creation

-- Add sync_ready column to queue table (if not exists)
ALTER TABLE ocus_order_sync_queue
ADD COLUMN IF NOT EXISTS sync_ready TINYINT DEFAULT 0 AFTER sync_status;

-- ============================================
-- Modified ORDER INSERT trigger
-- ============================================

DROP TRIGGER IF EXISTS `ocus_order_after_insert_sync_delayed`;

DELIMITER $$

CREATE TRIGGER `ocus_order_after_insert_sync_delayed`
AFTER INSERT ON `ocus_order`
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
    -- Insert with sync_ready = 0 (will be marked ready later)
    INSERT INTO `ocus_order_sync_queue` (
      `source_db`, `table_name`, `operation`, `record_id`, `parent_order_id`,
      `data_json`, `sync_status`, `sync_ready`
    ) VALUES (
      current_source, 'order', 'INSERT', NEW.order_id, NEW.order_id,
      JSON_OBJECT('order_id', NEW.order_id, 'firstname', NEW.firstname,
                  'total', NEW.total, 'order_status_id', NEW.order_status_id),
      'pending', 0  -- NOT ready yet
    );
  END IF;
END$$

DELIMITER ;

-- ============================================
-- Modified ORDER UPDATE trigger
-- Marks order as sync_ready when status changes from 0 to active status
-- ============================================

DROP TRIGGER IF EXISTS `ocus_order_after_update_sync_delayed`;

DELIMITER $$

CREATE TRIGGER `ocus_order_after_update_sync_delayed`
AFTER UPDATE ON `ocus_order`
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
    -- If order status changed from 0 to something else, mark all related queue items as ready
    IF OLD.order_status_id = 0 AND NEW.order_status_id > 0 THEN
      -- Mark all pending queue items for this order as sync_ready
      UPDATE ocus_order_sync_queue
      SET sync_ready = 1
      WHERE parent_order_id = NEW.order_id
        AND sync_status = 'pending'
        AND sync_ready = 0;
    END IF;
  END IF;
END$$

DELIMITER ;

-- ============================================
-- Modified Worker Query
-- ============================================
-- Worker should fetch only sync_ready items:
--
-- SELECT * FROM ocus_order_sync_queue
-- WHERE sync_status = 'pending' AND sync_ready = 1
-- ORDER BY id ASC LIMIT 10;
--
-- For immediate sync (testing), you can manually mark orders ready:
-- UPDATE ocus_order_sync_queue SET sync_ready = 1 WHERE parent_order_id = 108030;

SELECT 'Delayed sync strategy created' AS status;
SELECT 'IMPORTANT: Worker must be updated to check sync_ready = 1' AS note;
