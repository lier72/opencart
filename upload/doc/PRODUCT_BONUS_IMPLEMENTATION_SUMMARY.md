# Product-Level Bonus Tracking - Implementation Summary

**Date**: 2026-01-08
**Status**: Ready for Implementation
**Database**: a1627-unqs-oc3
**Table Prefix**: ocus_

---

## Executive Summary

This document outlines the implementation of product-level bonus tracking to handle partial returns in try-on orders. The system will track bonus points earned per product line item, allowing accurate point deductions when items are returned.

**Key Design Principle**: Minimize data duplication by leveraging existing OpenCart tables (`ocus_order_product` and `ocus_return`) for all product details. The new table ONLY stores the mapping: `order_product_id → bonus_points`.

---

## Business Problem

### Current Scenario
1. Customer orders 5 items to try (total: 10,000₽)
2. Order marked Complete → 1,000 bonus points awarded
3. Customer returns 4 items, keeps 1 (value: 2,000₽)
4. **Problem**: Customer retains 1,000 points but should only have 200 points

### Solution
Track bonus points at the product line-item level. When products are returned, deduct the corresponding bonus points proportionally.

---

## Database Schema

### New Table: `ocus_customer_bonus_items`

```sql
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Design Rationale

**Why minimal?**
- Product details (name, model, price, quantity) → Already in `ocus_order_product`
- Return details (quantity, reason, status) → Already in `ocus_return`
- Stores both `product_id` AND `order_product_id` for flexible matching

**Why product_id field?**
- **Try-on reality**: Customers often don't remember which specific order a product came from
- **Flexible returns**: Admin can find and deduct from ANY order containing the returned product
- **Auto-matching**: If no order_id in return, system finds oldest order with bonus points for that product

**Performance:**
- UNIQUE index on `order_product_id` ensures one record per product line
- Index on `order_id` for fast order-level queries
- Index on `product_id` for flexible return matching across orders
- Minimal storage footprint

---

## Data Flow

### 1. Order Completion (Award Bonuses)

```
Order Status → Complete
    ↓
catalog/model/extension/module/bonus_manager::awardBonusesForOrder()
    ↓
Query ocus_order_product for all products in order
    ↓
For each product:
    - Calculate: bonus = product.total * (bonus_percentage / 100)
    - Insert into ocus_customer_bonus_items
    ↓
Sum all product bonuses → total_bonus
    ↓
Insert ONE entry into ocus_customer_reward:
    - points = total_bonus
    - bonus_type = 'order_complete'
    - bonus_metadata = '{"bonus_pct": 10}'
```

### 2. Product Return (Deduct Bonuses)

```
Admin clicks "Deduct & Create Return" on Order Info page
    ↓
JavaScript prompt: "Enter quantity to return"
    ↓
AJAX → admin/controller/sale/order::deductBonusAndCreateReturn()
    ↓
Create entry in ocus_return table
    - return_status_id = 3 (Complete)
    - Links to order_id + product_id
    ↓
Call catalog/model/extension/module/bonus_manager::returnProductBonuses($return_id)
    ↓
Look up order_product_id from ocus_order_product
    ↓
Get bonus_points from ocus_customer_bonus_items
    ↓
Calculate: deduction = (return_qty / original_qty) * bonus_points
    ↓
Insert ONE negative entry into ocus_customer_reward:
    - points = -deduction
    - bonus_type = 'return_deduction'
    - bonus_metadata = '{"return_id": 88, "product_id": 102}'
    ↓
Update ocus_customer_bonus_items:
    - Reduce bonus_points by deduction amount
    - OR delete record if fully returned
```

---

## Implementation Phases

### Phase 1: Database Migration ✅ READY
- **File**: `admin/bonus_product_tracking_migration.sql`
- **Action**: Execute SQL to create `ocus_customer_bonus_items` table
- **Verification**:
  ```sql
  DESCRIBE ocus_customer_bonus_items;
  SHOW INDEX FROM ocus_customer_bonus_items;
  ```

### Phase 2: Model Updates
- **File**: `catalog/model/extension/module/bonus_manager.php`
- **Changes**:
  1. Modify `awardBonusesForOrder()` to:
     - Query `ocus_order_product` for all products
     - Calculate bonus per product
     - Insert into `ocus_customer_bonus_items`
     - Sum total and insert into transaction/reward tables

  2. Add `returnProductBonuses($return_id)` method:
     - Get return details from `ocus_return`
     - Find `order_product_id` from `ocus_order_product`
     - Look up `bonus_points` from `ocus_customer_bonus_items`
     - Calculate proportional deduction
     - Insert negative transactions
     - Update/delete bonus_items record

  3. Add `getOrderBonusItems($order_id)` method:
     - JOIN `ocus_customer_bonus_items` with `ocus_order_product`
     - Return array with product details + bonus points
     - Used for admin display

### Phase 3: Admin Interface
- **File**: `admin/controller/sale/order.php`
  - Add to `info()` method: Load bonus items data
  - Add `deductBonusAndCreateReturn()` AJAX handler:
    - Creates entry in `ocus_return` table
    - Calls `returnProductBonuses()` to deduct points
    - Returns JSON success/error message

- **File**: `admin/view/template/sale/order_info.twig`
  - Add "Bonus Points" tab in navigation
  - Add tab content with:
    - Table showing product breakdown (product, model, qty, price, bonus points)
    - "Deduct & Create Return" button per product
    - JavaScript function to handle AJAX call with quantity prompt

### Phase 4: Return Processing Integration
- **Current**: Manual workflow via Order Info page
- **Future**: Auto-trigger on return status change in `admin/controller/sale/return.php`

### Phase 5: Testing
1. Award bonuses for new order → Verify product-level records created
2. Full return → Verify complete deduction, bonus_items record deleted
3. Partial return → Verify proportional deduction, bonus_points reduced
4. Multiple returns → Return products separately, verify cumulative deductions
5. customer_reward sync → Verify totals match across tables
6. Negative balance prevention → Cannot deduct more than awarded

---

## Critical Synchronization Rules

### ✅ Rule 1: No Synchronization Needed! (Simplified Architecture)

**With the single-table approach, synchronization bugs are IMPOSSIBLE.**

We use ONLY `customer_reward` (with `bonus_type` and `bonus_metadata` columns) for all transactions.

```sql
-- All bonus transactions in one place:
SELECT * FROM customer_reward WHERE customer_id = X;

-- Filter by type:
SELECT * FROM customer_reward WHERE customer_id = X AND bonus_type = 'order_complete';
SELECT * FROM customer_reward WHERE customer_id = X AND bonus_type = 'return_deduction';

-- Total (OpenCart compatible):
SELECT SUM(points) FROM customer_reward WHERE customer_id = X;
```

### ✅ Rule 2: Product Items Sum to Order Total (Initially)
```sql
SUM(customer_bonus_items.bonus_points WHERE order_id = Y)
  =
customer_reward.points WHERE order_id = Y AND bonus_type = 'order_complete'
```
After returns, product items may be reduced/deleted, but customer_reward keeps full history.

### ✅ Rule 3: Returns Create Negative Entries (Simplified!)
- Insert: `customer_reward` (points = -N, bonus_type = 'return_deduction')
- Update: `customer_bonus_items` (bonus_points -= N) OR delete if fully returned

**That's it! Just ONE insert instead of TWO.**

### ✅ Rule 4: Prevent Over-Deduction
```
deduction_amount <= current bonus_points for that order_product_id
```
Check current value before deduction, prevent negative bonus_points.

---

## Example Scenario

### Order #12345: Customer orders 3 products

**ocus_order_product:**
| order_product_id | product_id | name      | quantity | price   | total    |
|------------------|------------|-----------|----------|---------|----------|
| 501              | 101        | Product A | 2        | 2000.00 | 4000.00  |
| 502              | 102        | Product B | 1        | 3000.00 | 3000.00  |
| 503              | 103        | Product C | 3        | 1000.00 | 3000.00  |

**Bonus %**: 10%
**Total**: 10,000₽ → 1,000 bonus points

### Step 1: Order Complete

**ocus_customer_bonus_items:**
| bonus_item_id | order_id | product_id | order_product_id | bonus_points |
|---------------|----------|------------|------------------|--------------|
| 1             | 12345    | 101        | 501              | 400          |
| 2             | 12345    | 102        | 502              | 300          |
| 3             | 12345    | 103        | 503              | 300          |

**ocus_customer_reward:** (Extended with bonus_type and bonus_metadata)
| customer_reward_id | customer_id | order_id | points | bonus_type      | description        |
|--------------------|-------------|----------|--------|-----------------|---------------------|
| 1234               | 55          | 12345    | 1000   | order_complete  | Order #12345 bonus  |

### Step 2: Customer Returns Product B (fully)

**ocus_return:**
| return_id | order_id | product_id | quantity | return_status_id |
|-----------|----------|------------|----------|------------------|
| 88        | 12345    | 102        | 1        | 3                |

**Deduction**: 300 points

**ocus_customer_bonus_items:** (Record DELETED)
| bonus_item_id | order_id | product_id | order_product_id | bonus_points |
|---------------|----------|------------|------------------|--------------|
| 1             | 12345    | 101        | 501              | 400          |
| ~~2~~         | ~~12345~~ | ~~102~~   | ~~502~~          | ~~300~~      |
| 3             | 12345    | 103        | 503              | 300          |

**ocus_customer_reward:**
| customer_reward_id | customer_id | order_id | points | bonus_type        | description           |
|--------------------|-------------|----------|--------|-------------------|-----------------------|
| 1234               | 55          | 12345    | 1000   | order_complete    | Order #12345 bonus    |
| 1235               | 55          | 12345    | -300   | return_deduction  | Return deduction #88  |

**Customer Balance**: 1000 - 300 = 700 points ✅

### Step 3: Customer Returns 2 of 3 Product C (partial)

**ocus_return:**
| return_id | order_id | product_id | quantity | return_status_id |
|-----------|----------|------------|----------|------------------|
| 89        | 12345    | 103        | 2        | 3                |

**Deduction**: (2/3) * 300 = 200 points

**ocus_customer_bonus_items:** (Record UPDATED)
| bonus_item_id | order_id | product_id | order_product_id | bonus_points |
|---------------|----------|------------|------------------|--------------|
| 1             | 12345    | 101        | 501              | 400          |
| 3             | 12345    | 103        | 503              | ~~300~~ 100  |

**Customer Balance**: 700 - 200 = 500 points ✅

**Final Result**: Customer keeps Product A (2 units) + Product C (1 unit) → 500 bonus points (correct!)

### Step 4: Flexible Return Matching (Customer Doesn't Remember Order)

**Scenario**: Customer wants to return Product A but doesn't remember if it was from order #12345 or order #12999.

**How it works:**
1. Admin creates return with `product_id = 101` but `order_id = 0` (or leaves it unspecified)
2. System queries:
   ```sql
   SELECT op.order_product_id, op.quantity, bi.bonus_points
   FROM ocus_order_product op
   INNER JOIN ocus_customer_bonus_items bi ON bi.order_product_id = op.order_product_id
   WHERE op.product_id = 101
   AND bi.bonus_points > 0
   ORDER BY op.order_id ASC  -- Oldest first
   LIMIT 1
   ```
3. System finds order #12345 has Product A with 400 bonus points remaining
4. Deducts proportionally from that order

**Benefit**: Admin doesn't need to search through order history - system finds it automatically!

---

## Migration Strategy

### For Existing Orders
**Option 1 (Recommended)**: New orders only
- Start tracking from implementation date forward
- Old orders cannot benefit from granular return handling
- Clearly documented cutoff date

**Option 2**: Retroactive fill
- Create script to analyze past orders
- Populate `ocus_customer_bonus_items` for historical data
- Only useful if returns are common for old orders

**Recommendation**: Option 1 - New orders only.

---

## Rollback Plan

If issues arise:
1. **Database**:
   ```sql
   DROP TABLE ocus_customer_bonus_items;
   ALTER TABLE ocus_customer_reward DROP COLUMN bonus_type;
   ALTER TABLE ocus_customer_reward DROP COLUMN bonus_metadata;
   ```
2. **Code**: Comment out new logic in `awardBonusesForOrder()`
3. **Admin**: Hide "Bonus Points" tab in order info
4. **Impact**: Returns to order-level tracking (current behavior)

**Data Safety**: `customer_reward` table extended with backward-compatible columns. Existing OpenCart code unaffected.

---

## Security Considerations

1. **Prevent double deduction**: Check `quantity_returned` doesn't exceed original `quantity`
2. **Validate ownership**: Ensure `customer_id` matches before deducting
3. **Audit trail**: Transaction tables keep full history (never delete, only insert negatives)
4. **Admin permissions**: Only authorized users can access deduction feature
5. **SQL injection**: Use prepared statements / `(int)` casting for all IDs

---

## Performance Considerations

1. **Indexes**:
   - UNIQUE on `order_product_id` → Fast lookup
   - Index on `order_id` → Fast order-level queries

2. **Table size**: Minimal - 1 row per order line item (same size as `ocus_order_product`)

3. **Query optimization**: JOINs with `ocus_order_product` are fast due to matching indexes

4. **No locks**: All operations are INSERTs or single-row UPDATEs/DELETEs

---

## Files to Create/Modify

### New Files
1. ✅ `admin/bonus_product_tracking_migration.sql` - Database migration
2. ✅ `BONUS_PRODUCT_TRACKING_PLAN.md` - Detailed implementation plan
3. ✅ `PRODUCT_BONUS_IMPLEMENTATION_SUMMARY.md` - This document

### Files to Modify
1. `catalog/model/extension/module/bonus_manager.php`
   - Modify: `awardBonusesForOrder()`
   - Add: `returnProductBonuses($return_id)`
   - Add: `getOrderBonusItems($order_id)`

2. `admin/controller/sale/order.php`
   - Modify: `info()` method to load bonus items
   - Add: `deductBonusAndCreateReturn()` AJAX handler

3. `admin/view/template/sale/order_info.twig`
   - Add: "Bonus Points" tab navigation
   - Add: Bonus breakdown table with deduction buttons
   - Add: JavaScript AJAX handler

---

## Next Steps

1. **Review**: User reviews this document and migration SQL
2. **Test Database**: Run migration on development database
3. **Implement Model**: Modify `catalog/model/extension/module/bonus_manager.php`
4. **Implement Controller**: Modify `admin/controller/sale/order.php`
5. **Implement View**: Modify `admin/view/template/sale/order_info.twig`
6. **Test**: Execute all test cases (see Phase 5)
7. **Deploy**: Push to production after successful testing

---

**Status**: ✅ Planning Complete - Ready for Phase 1 Implementation
**Created**: 2026-01-08
**Last Updated**: 2026-01-08
**Architecture**: Minimal, Efficient, OpenCart-Compatible
