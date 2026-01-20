-- Migration: Revise customer_bonus_items table structure
-- Add columns to store complete product information and expiration date
-- Date: 2026-01-11

-- Extend status enum to include new statuses alongside existing ones
ALTER TABLE `ocus_customer_bonus_items`
MODIFY COLUMN `status` ENUM('active','pending_deduction','deducted','pending','expired','cancelled') NOT NULL DEFAULT 'pending';

-- Add new columns to customer_bonus_items table
ALTER TABLE `ocus_customer_bonus_items`
ADD COLUMN `product_name` VARCHAR(255) NULL AFTER `product_id`,
ADD COLUMN `product_model` VARCHAR(64) NULL AFTER `product_name`,
ADD COLUMN `product_quantity` INT NOT NULL DEFAULT 1 AFTER `product_model`,
ADD COLUMN `product_price` DECIMAL(15,4) NOT NULL DEFAULT 0.0000 AFTER `product_quantity`,
ADD COLUMN `product_total` DECIMAL(15,4) NOT NULL DEFAULT 0.0000 AFTER `product_price`,
ADD COLUMN `bonus_rate` DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER `product_total`,
ADD COLUMN `date_expires` DATETIME NULL AFTER `date_added`,
ADD INDEX `idx_date_expires` (`date_expires`);

-- Comments explaining the revised structure
/*
Revised customer_bonus_items table structure:

Purpose: Track product-level bonus points for orders and returns with complete product information

Columns:
- bonus_item_id: Primary key, auto-increment
- order_id: Reference to ocus_order table
- product_id: Reference to ocus_product table
- product_name: Product name at time of order (stored for historical reference)
- product_model: Product model/SKU at time of order
- product_quantity: Quantity of product in order line
- product_price: Unit price of product
- product_total: Total price for this line (quantity × price)
- order_product_id: Reference to ocus_order_product table (unique constraint)
- bonus_points: Points earned/deducted
  - POSITIVE value: Bonus award for order
  - NEGATIVE value: Bonus deduction for return
- bonus_rate: Percentage rate used to calculate bonus (e.g., 5.00 for 5%)
- status: Current status of the bonus item
  - 'pending': Created but not yet processed (order not complete OR return not approved)
  - 'active': Bonus points awarded/deducted and currently valid
  - 'expired': Bonus points have expired (based on date_expires)
  - 'cancelled': Bonus cancelled (order cancelled or other reason)
- return_id: Reference to ocus_return table (NULL for order bonuses, populated for returns)
- date_added: When the bonus item was created
- date_expires: When the bonus points expire (matches parent customer_reward entry)

Usage:
- For order bonuses: Create with positive bonus_points, status='pending'
  - When order status changes to Complete: Set status='active' and create customer_reward entry
- For returns: Create with negative bonus_points, status='pending'
  - When return approved: Set status='active' and create negative customer_reward entry
- Direction is determined by sign of bonus_points (+ for award, - for deduction)
- Allows complete audit trail of which products earned/lost points
- Stores all product details for reporting even if product is later deleted/modified
*/
