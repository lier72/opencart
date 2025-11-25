-- ============================================
-- DROP ALL SYNC TRIGGERS - OC2 & OC3
-- Use this to completely remove all order sync triggers
-- ============================================

-- ============================================
-- OC3 Database - Drop All Triggers
-- ============================================
USE `a1627-unqs-oc3`;

-- Order triggers
DROP TRIGGER IF EXISTS `ocus_order_after_insert_sync`;
DROP TRIGGER IF EXISTS `ocus_order_after_update_sync`;

-- Order history triggers
DROP TRIGGER IF EXISTS `ocus_order_history_after_insert_sync`;
DROP TRIGGER IF EXISTS `ocus_order_history_after_update_sync`;
DROP TRIGGER IF EXISTS `ocus_order_history_after_delete_sync`;

-- Customer activity triggers
DROP TRIGGER IF EXISTS `ocus_customer_activity_after_insert_sync`;

-- Odoo order map triggers
DROP TRIGGER IF EXISTS `ocus_odoo_order_map_after_insert_sync`;
DROP TRIGGER IF EXISTS `ocus_odoo_order_map_after_update_sync`;

-- Order to SDEK triggers
DROP TRIGGER IF EXISTS `ocus_order_to_sdek_after_insert_sync`;
DROP TRIGGER IF EXISTS `ocus_order_to_sdek_after_update_sync`;

-- Related table triggers (should already be dropped, but included for safety)
DROP TRIGGER IF EXISTS `ocus_order_product_after_insert_sync`;
DROP TRIGGER IF EXISTS `ocus_order_product_after_delete_sync`;
DROP TRIGGER IF EXISTS `ocus_order_option_after_insert_sync`;
DROP TRIGGER IF EXISTS `ocus_order_option_after_delete_sync`;
DROP TRIGGER IF EXISTS `ocus_order_total_after_insert_sync`;
DROP TRIGGER IF EXISTS `ocus_order_total_after_delete_sync`;

SELECT 'All sync triggers dropped from OC3 database' AS status;

-- ============================================
-- OC2 Database - Drop All Triggers
-- ============================================
USE `a1627-unqs-oc`;

-- Order triggers
DROP TRIGGER IF EXISTS `ocus_order_after_insert_sync`;
DROP TRIGGER IF EXISTS `ocus_order_after_update_sync`;

-- Order history triggers
DROP TRIGGER IF EXISTS `ocus_order_history_after_insert_sync_oc2`;
DROP TRIGGER IF EXISTS `ocus_order_history_after_update_sync_oc2`;
DROP TRIGGER IF EXISTS `ocus_order_history_after_delete_sync_oc2`;

-- Customer activity triggers
DROP TRIGGER IF EXISTS `ocus_customer_activity_after_insert_sync`;

-- Odoo order map triggers
DROP TRIGGER IF EXISTS `ocus_odoo_order_map_after_insert_sync`;
DROP TRIGGER IF EXISTS `ocus_odoo_order_map_after_update_sync`;

-- Order to SDEK triggers
DROP TRIGGER IF EXISTS `ocus_order_to_sdek_after_insert_sync`;
DROP TRIGGER IF EXISTS `ocus_order_to_sdek_after_update_sync`;

-- Related table triggers (should already be dropped, but included for safety)
DROP TRIGGER IF EXISTS `ocus_order_product_after_insert_sync_oc2`;
DROP TRIGGER IF EXISTS `ocus_order_product_after_delete_sync_oc2`;
DROP TRIGGER IF EXISTS `ocus_order_option_after_insert_sync_oc2`;
DROP TRIGGER IF EXISTS `ocus_order_option_after_delete_sync_oc2`;
DROP TRIGGER IF EXISTS `ocus_order_total_after_insert_sync_oc2`;
DROP TRIGGER IF EXISTS `ocus_order_total_after_delete_sync_oc2`;

SELECT 'All sync triggers dropped from OC2 database' AS status;

-- ============================================
-- Verify All Triggers Removed
-- ============================================
SELECT 'Remaining sync triggers in OC3:' AS info;
USE `a1627-unqs-oc3`;
SELECT TRIGGER_NAME, EVENT_MANIPULATION, EVENT_OBJECT_TABLE
FROM INFORMATION_SCHEMA.TRIGGERS
WHERE TRIGGER_SCHEMA = 'a1627-unqs-oc3'
  AND TRIGGER_NAME LIKE '%sync%'
ORDER BY EVENT_OBJECT_TABLE, TRIGGER_NAME;

SELECT 'Remaining sync triggers in OC2:' AS info;
USE `a1627-unqs-oc`;
SELECT TRIGGER_NAME, EVENT_MANIPULATION, EVENT_OBJECT_TABLE
FROM INFORMATION_SCHEMA.TRIGGERS
WHERE TRIGGER_SCHEMA = 'a1627-unqs-oc'
  AND TRIGGER_NAME LIKE '%sync%'
ORDER BY EVENT_OBJECT_TABLE, TRIGGER_NAME;

-- ============================================
-- Summary
-- ============================================
SELECT '
TRIGGERS DROPPED:
-----------------
OC3:
  - ocus_order_after_insert_sync
  - ocus_order_after_update_sync
  - ocus_order_history_after_insert_sync
  - ocus_order_history_after_update_sync
  - ocus_order_history_after_delete_sync
  - ocus_customer_activity_after_insert_sync
  - ocus_odoo_order_map_after_insert_sync
  - ocus_odoo_order_map_after_update_sync
  - ocus_order_to_sdek_after_insert_sync
  - ocus_order_to_sdek_after_update_sync

OC2:
  - ocus_order_after_insert_sync
  - ocus_order_after_update_sync
  - ocus_order_history_after_insert_sync_oc2
  - ocus_order_history_after_update_sync_oc2
  - ocus_order_history_after_delete_sync_oc2
  - ocus_customer_activity_after_insert_sync
  - ocus_odoo_order_map_after_insert_sync
  - ocus_odoo_order_map_after_update_sync
  - ocus_order_to_sdek_after_insert_sync
  - ocus_order_to_sdek_after_update_sync

NOTE: The sync queue table (ocus_order_sync_queue) is NOT dropped.
      To drop it, run: DROP TABLE ocus_order_sync_queue;
' AS Summary;
