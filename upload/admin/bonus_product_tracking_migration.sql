-- ============================================================================
-- Product-Level Bonus Tracking Migration
-- ============================================================================
-- Description: Extends OpenCart's customer_reward table and creates minimal
--              product-level tracking table for handling partial returns
-- Author: Claude Code
-- Date: 2026-01-08
-- Database: a1627-unqs-oc3
-- Table Prefix: ocus_
-- ============================================================================

-- ============================================================================
-- PART 1: Extend customer_reward table (OpenCart core)
-- ============================================================================
-- Instead of creating a duplicate customer_bonus_transaction table,
-- we extend the existing customer_reward table with bonus-specific fields.
-- This maintains OpenCart compatibility while adding our features.

ALTER TABLE ocus_customer_reward
ADD COLUMN IF NOT EXISTS bonus_type VARCHAR(50) DEFAULT 'reward'
    COMMENT 'order_complete, return_deduction, manual_adjustment, loyalty_bonus, etc.'
    AFTER points,
ADD COLUMN IF NOT EXISTS bonus_metadata TEXT
    COMMENT 'JSON metadata for bonus-specific information'
    AFTER bonus_type,
ADD INDEX IF NOT EXISTS idx_bonus_type (bonus_type);

-- ============================================================================
-- PART 2: Create product-level bonus tracking table
-- ============================================================================
-- Minimal table linking order products to bonus points.
-- Leverages existing ocus_order_product and ocus_return for all product data.

CREATE TABLE IF NOT EXISTS `ocus_customer_bonus_items` (
  `bonus_item_id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL COMMENT 'Product ID - allows flexible return matching',
  `order_product_id` int(11) NOT NULL COMMENT 'FK to ocus_order_product',
  `bonus_points` int(11) NOT NULL COMMENT 'Bonus points earned for this product line',
  `date_added` datetime NOT NULL,
  PRIMARY KEY (`bonus_item_id`),
  UNIQUE KEY `idx_order_product` (`order_product_id`),
  KEY `idx_order` (`order_id`),
  KEY `idx_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Links order products to bonus points for return handling';

-- ============================================================================
-- How It Works
-- ============================================================================

-- SIMPLIFIED APPROACH:
-- Instead of maintaining customer_reward + customer_bonus_transaction,
-- we only use customer_reward (with extended fields) for ALL transactions.
--
-- Benefits:
-- - No data duplication
-- - No synchronization issues (single source of truth)
-- - OpenCart compatible (core queries ignore new columns)
-- - Simpler code (one INSERT instead of two)

-- 1. When order completes:
--    - For each product in order, calculate bonus points
--    - Insert into ocus_customer_bonus_items (product-level tracking)
--    - Insert ONE entry into ocus_customer_reward with:
--      * points = total bonus for order
--      * bonus_type = 'order_complete'
--      * bonus_metadata = '{"order_id": 12345, "bonus_pct": 10}'

-- 2. When product returned:
--    - Customer doesn't always remember which order product came from
--    - Find order with product_id that has bonus_points > 0
--    - Calculate proportional deduction
--    - Insert ONE entry into ocus_customer_reward with:
--      * points = -N (negative)
--      * bonus_type = 'return_deduction'
--      * bonus_metadata = '{"return_id": 88, "product_id": 102}'
--    - Update or delete ocus_customer_bonus_items record

-- FLEXIBLE MATCHING: product_id allows matching returns even when customer
-- doesn't remember the exact order. Admin can select from available orders.

-- ============================================================================
-- OpenCart Compatibility
-- ============================================================================

-- OpenCart core queries still work unchanged:
-- SELECT SUM(points) FROM customer_reward WHERE customer_id = X

-- Our enhanced queries:
-- SELECT * FROM customer_reward WHERE customer_id = X AND bonus_type = 'order_complete'
-- SELECT * FROM customer_reward WHERE customer_id = X AND bonus_type = 'return_deduction'

-- ============================================================================
-- Verification Queries
-- ============================================================================

-- Check customer_reward structure:
-- DESCRIBE ocus_customer_reward;

-- Check bonus_items structure:
-- DESCRIBE ocus_customer_bonus_items;

-- Check indexes:
-- SHOW INDEX FROM ocus_customer_reward;
-- SHOW INDEX FROM ocus_customer_bonus_items;

-- ============================================================================
-- Rollback (if needed)
-- ============================================================================

-- To rollback this migration:
-- DROP TABLE IF EXISTS `ocus_customer_bonus_items`;
-- ALTER TABLE ocus_customer_reward DROP COLUMN bonus_type;
-- ALTER TABLE ocus_customer_reward DROP COLUMN bonus_metadata;
-- ALTER TABLE ocus_customer_reward DROP INDEX idx_bonus_type;

-- ============================================================================
-- Migration Complete
-- ============================================================================
