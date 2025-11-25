-- ============================================
-- Order Sync Triggers - Phase 1
-- Start with INSERT only for ocus_order table
-- ============================================

-- Drop existing triggers if any
DROP TRIGGER IF EXISTS `ocus_order_after_insert_sync`;

-- Delimiter change for trigger creation
DELIMITER $$

-- Trigger: Capture new orders
CREATE TRIGGER `ocus_order_after_insert_sync`
AFTER INSERT ON `ocus_order`
FOR EACH ROW
BEGIN
  DECLARE current_source ENUM('oc2', 'oc3');
  DECLARE should_sync TINYINT DEFAULT 1;

  -- Check if sync is in progress
  IF @sync_in_progress = 1 THEN
    SET should_sync = 0;
  END IF;

  -- Determine which database we're in (OC3 for now)
  SET current_source = 'oc3';

  -- Check if this order is already being synced (check if mapping exists with sync_lock)
  -- If order was just created by sync worker, skip adding to queue
  IF EXISTS (
    SELECT 1 FROM ocus_order_id_map
    WHERE oc3_order_id = NEW.order_id AND sync_lock = 1
  ) THEN
    SET should_sync = 0;
  END IF;

  -- Add to sync queue if this is a new local order
  IF should_sync = 1 THEN
    INSERT INTO `ocus_order_sync_queue` (
      `source_db`,
      `table_name`,
      `operation`,
      `record_id`,
      `parent_order_id`,
      `data_json`,
      `sync_status`
    ) VALUES (
      current_source,
      'order',
      'INSERT',
      NEW.order_id,
      NEW.order_id,
      JSON_OBJECT(
        'order_id', NEW.order_id,
        'invoice_no', NEW.invoice_no,
        'invoice_prefix', NEW.invoice_prefix,
        'store_id', NEW.store_id,
        'store_name', NEW.store_name,
        'store_url', NEW.store_url,
        'customer_id', NEW.customer_id,
        'customer_group_id', NEW.customer_group_id,
        'firstname', NEW.firstname,
        'lastname', NEW.lastname,
        'email', NEW.email,
        'telephone', NEW.telephone,
        'fax', NEW.fax,
        'custom_field', NEW.custom_field,
        'payment_firstname', NEW.payment_firstname,
        'payment_lastname', NEW.payment_lastname,
        'payment_company', NEW.payment_company,
        'payment_address_1', NEW.payment_address_1,
        'payment_address_2', NEW.payment_address_2,
        'payment_city', NEW.payment_city,
        'payment_postcode', NEW.payment_postcode,
        'payment_country', NEW.payment_country,
        'payment_country_id', NEW.payment_country_id,
        'payment_zone', NEW.payment_zone,
        'payment_zone_id', NEW.payment_zone_id,
        'payment_address_format', NEW.payment_address_format,
        'payment_custom_field', NEW.payment_custom_field,
        'payment_method', NEW.payment_method,
        'payment_code', NEW.payment_code,
        'shipping_firstname', NEW.shipping_firstname,
        'shipping_lastname', NEW.shipping_lastname,
        'shipping_company', NEW.shipping_company,
        'shipping_address_1', NEW.shipping_address_1,
        'shipping_address_2', NEW.shipping_address_2,
        'shipping_city', NEW.shipping_city,
        'shipping_postcode', NEW.shipping_postcode,
        'shipping_country', NEW.shipping_country,
        'shipping_country_id', NEW.shipping_country_id,
        'shipping_zone', NEW.shipping_zone,
        'shipping_zone_id', NEW.shipping_zone_id,
        'shipping_address_format', NEW.shipping_address_format,
        'shipping_custom_field', NEW.shipping_custom_field,
        'shipping_method', NEW.shipping_method,
        'shipping_code', NEW.shipping_code,
        'comment', NEW.comment,
        'pvz_cdek', NEW.pvz_cdek,
        'total', NEW.total,
        'order_status_id', NEW.order_status_id,
        'affiliate_id', NEW.affiliate_id,
        'commission', NEW.commission,
        'marketing_id', NEW.marketing_id,
        'tracking', NEW.tracking,
        'language_id', NEW.language_id,
        'currency_id', NEW.currency_id,
        'currency_code', NEW.currency_code,
        'currency_value', NEW.currency_value,
        'ip', NEW.ip,
        'forwarded_ip', NEW.forwarded_ip,
        'user_agent', NEW.user_agent,
        'accept_language', NEW.accept_language,
        'date_added', NEW.date_added,
        'date_modified', NEW.date_modified,
        'payment_cost', NEW.payment_cost,
        'shipping_cost', NEW.shipping_cost,
        'extra_cost', NEW.extra_cost
      ),
      'pending'
    );
  END IF;
END$$

DELIMITER ;

-- ============================================
-- Trigger: Capture order updates
-- ============================================

DROP TRIGGER IF EXISTS `ocus_order_after_update_sync`;

DELIMITER $$

CREATE TRIGGER `ocus_order_after_update_sync`
AFTER UPDATE ON `ocus_order`
FOR EACH ROW
BEGIN
  DECLARE current_source ENUM('oc2', 'oc3');
  DECLARE should_sync TINYINT DEFAULT 1;

  -- Check if sync is in progress
  IF @sync_in_progress = 1 THEN
    SET should_sync = 0;
  END IF;
  DECLARE has_changes TINYINT DEFAULT 0;
  DECLARE existing_pending INT DEFAULT 0;

  -- Determine which database we're in (OC3 for now)
  SET current_source = 'oc3';

  -- Check if this order is being synced (sync_lock check)
  IF EXISTS (
    SELECT 1 FROM ocus_order_id_map
    WHERE oc3_order_id = NEW.order_id AND sync_lock = 1
  ) THEN
    SET should_sync = 0;
  END IF;

  -- Check if there are meaningful changes (skip if only date_modified changed)
  IF NEW.firstname != OLD.firstname OR NEW.lastname != OLD.lastname OR
     NEW.email != OLD.email OR NEW.telephone != OLD.telephone OR
     NEW.order_status_id != OLD.order_status_id OR NEW.total != OLD.total OR
     NEW.comment != OLD.comment OR NEW.pvz_cdek != OLD.pvz_cdek OR
     NEW.payment_city != OLD.payment_city OR NEW.shipping_city != OLD.shipping_city OR
     NEW.payment_method != OLD.payment_method OR NEW.shipping_method != OLD.shipping_method THEN
    SET has_changes = 1;
  END IF;

  -- Check if there's already a pending entry for this order (deduplication)
  SELECT COUNT(*) INTO existing_pending
  FROM ocus_order_sync_queue
  WHERE record_id = NEW.order_id
    AND table_name = 'order'
    AND sync_status = 'pending'
    AND source_db = current_source
  LIMIT 1;

  -- Only add to queue if: should sync AND has changes AND no pending entry exists
  IF should_sync = 1 AND has_changes = 1 AND existing_pending = 0 THEN
    INSERT INTO `ocus_order_sync_queue` (
      `source_db`,
      `table_name`,
      `operation`,
      `record_id`,
      `parent_order_id`,
      `data_json`,
      `sync_status`
    ) VALUES (
      current_source,
      'order',
      'UPDATE',
      NEW.order_id,
      NEW.order_id,
      JSON_OBJECT(
        'order_id', NEW.order_id,
        'invoice_no', NEW.invoice_no,
        'invoice_prefix', NEW.invoice_prefix,
        'store_id', NEW.store_id,
        'store_name', NEW.store_name,
        'store_url', NEW.store_url,
        'customer_id', NEW.customer_id,
        'customer_group_id', NEW.customer_group_id,
        'firstname', NEW.firstname,
        'lastname', NEW.lastname,
        'email', NEW.email,
        'telephone', NEW.telephone,
        'fax', NEW.fax,
        'custom_field', NEW.custom_field,
        'payment_firstname', NEW.payment_firstname,
        'payment_lastname', NEW.payment_lastname,
        'payment_company', NEW.payment_company,
        'payment_address_1', NEW.payment_address_1,
        'payment_address_2', NEW.payment_address_2,
        'payment_city', NEW.payment_city,
        'payment_postcode', NEW.payment_postcode,
        'payment_country', NEW.payment_country,
        'payment_country_id', NEW.payment_country_id,
        'payment_zone', NEW.payment_zone,
        'payment_zone_id', NEW.payment_zone_id,
        'payment_address_format', NEW.payment_address_format,
        'payment_custom_field', NEW.payment_custom_field,
        'payment_method', NEW.payment_method,
        'payment_code', NEW.payment_code,
        'shipping_firstname', NEW.shipping_firstname,
        'shipping_lastname', NEW.shipping_lastname,
        'shipping_company', NEW.shipping_company,
        'shipping_address_1', NEW.shipping_address_1,
        'shipping_address_2', NEW.shipping_address_2,
        'shipping_city', NEW.shipping_city,
        'shipping_postcode', NEW.shipping_postcode,
        'shipping_country', NEW.shipping_country,
        'shipping_country_id', NEW.shipping_country_id,
        'shipping_zone', NEW.shipping_zone,
        'shipping_zone_id', NEW.shipping_zone_id,
        'shipping_address_format', NEW.shipping_address_format,
        'shipping_custom_field', NEW.shipping_custom_field,
        'shipping_method', NEW.shipping_method,
        'shipping_code', NEW.shipping_code,
        'comment', NEW.comment,
        'pvz_cdek', NEW.pvz_cdek,
        'total', NEW.total,
        'order_status_id', NEW.order_status_id,
        'affiliate_id', NEW.affiliate_id,
        'commission', NEW.commission,
        'marketing_id', NEW.marketing_id,
        'tracking', NEW.tracking,
        'language_id', NEW.language_id,
        'currency_id', NEW.currency_id,
        'currency_code', NEW.currency_code,
        'currency_value', NEW.currency_value,
        'ip', NEW.ip,
        'forwarded_ip', NEW.forwarded_ip,
        'user_agent', NEW.user_agent,
        'accept_language', NEW.accept_language,
        'date_added', NEW.date_added,
        'date_modified', NEW.date_modified,
        'payment_cost', NEW.payment_cost,
        'shipping_cost', NEW.shipping_cost,
        'extra_cost', NEW.extra_cost
      ),
      'pending'
    );
  END IF;
END$$

DELIMITER ;

-- ============================================
-- Verify triggers were created
-- ============================================
SELECT 'Triggers created successfully' AS status;
SHOW TRIGGERS WHERE `Table` = 'ocus_order';
