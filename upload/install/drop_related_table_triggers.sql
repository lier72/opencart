-- ============================================
-- Drop Related Table Triggers
-- These triggers are no longer needed as we sync at order-level
-- ============================================

-- ============================================
-- OC3 Database
-- ============================================
USE `a1627-unqs-oc3`;

DROP TRIGGER IF EXISTS `ocus_order_product_after_insert_sync`;
DROP TRIGGER IF EXISTS `ocus_order_product_after_delete_sync`;
DROP TRIGGER IF EXISTS `ocus_order_option_after_insert_sync`;
DROP TRIGGER IF EXISTS `ocus_order_option_after_delete_sync`;
DROP TRIGGER IF EXISTS `ocus_order_total_after_insert_sync`;
DROP TRIGGER IF EXISTS `ocus_order_total_after_delete_sync`;

SELECT 'Dropped order_product, order_option, order_total triggers from OC3' AS status;

-- ============================================
-- OC2 Database
-- ============================================
USE `a1627-unqs-oc`;

DROP TRIGGER IF EXISTS `ocus_order_product_after_insert_sync_oc2`;
DROP TRIGGER IF EXISTS `ocus_order_product_after_delete_sync_oc2`;
DROP TRIGGER IF EXISTS `ocus_order_option_after_insert_sync_oc2`;
DROP TRIGGER IF EXISTS `ocus_order_option_after_delete_sync_oc2`;
DROP TRIGGER IF EXISTS `ocus_order_total_after_insert_sync_oc2`;
DROP TRIGGER IF EXISTS `ocus_order_total_after_delete_sync_oc2`;

SELECT 'Dropped order_product, order_option, order_total triggers from OC2' AS status;

-- ============================================
-- Verify remaining triggers
-- ============================================
SELECT 'Remaining triggers in OC3:' AS info;
USE `a1627-unqs-oc3`;
SHOW TRIGGERS WHERE `Trigger` LIKE '%order%sync%';

SELECT 'Remaining triggers in OC2:' AS info;
USE `a1627-unqs-oc`;
SHOW TRIGGERS WHERE `Trigger` LIKE '%order%sync%';
