# Product-Level Bonus Tracking Implementation Plan

## Overview

This document outlines the implementation of product-level bonus tracking to handle try-on orders with partial returns. The system will track bonus points earned per product, allowing for accurate deductions when items are returned.

## Business Problem

**Current Issue:**
- Customer orders 5 items to try (10,000₽ total)
- Order marked Complete → 1,000 bonus points awarded
- Customer returns 4 items, keeps 1 (2,000₽)
- Customer retains 1,000 points but should only have 200 points

**Solution:**
Track bonus calculations at the product line-item level so we can deduct points for returned items while maintaining synchronization with OpenCart's core `customer_reward` table.

## Database Schema

### New Table: `ocus_customer_bonus_items`

Minimal table that links order products to bonus points. Leverages existing `ocus_order_product` and `ocus_return` tables for all product details.

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

**Design Philosophy:**
- **Minimal duplication**: Product details (name, model, price, quantity) already exist in `ocus_order_product`
- **Return tracking**: Return details (quantity, reason) already exist in `ocus_return`
- **Flexible matching**: Stores both `product_id` AND `order_product_id` for flexibility
- **Why product_id?**: Customers often don't remember which order a product came from in try-on scenarios
- **Simple lookups**: Join with `ocus_order_product` to get full product details when needed

### Relationship with Existing Tables

**Existing: `ocus_order_product`**
- Fields: order_product_id, order_id, product_id, name, model, quantity, price, total, tax, reward
- **Already has all product line details**
- **No changes needed**

**Existing: `ocus_return`**
- Fields: return_id, order_id, product_id, customer_id, product, model, quantity, opened, return_reason_id, return_action_id, return_status_id, cost, date_added
- **Already tracks returns with quantities**
- **No changes needed**

**Extended: `ocus_customer_reward`** (OpenCart Core + Our Extensions)
- Fields: customer_reward_id, customer_id, order_id, description, points, **bonus_type**, **bonus_metadata**, date_added, date_expires
- **Extended with bonus_type and bonus_metadata columns**
- Replaces need for separate customer_bonus_transaction table
- OpenCart core code unaffected (ignores new columns)
- Single source of truth - no synchronization issues

**New: `ocus_customer_bonus_items`**
- Stores ONLY: order_product_id → bonus_points
- Join with `ocus_order_product` to get product details
- Join with `ocus_return` (via product_id + order_id) to get return details

## Data Flow

### 1. Order Completion (Bonus Award)

```
Order Complete
    ↓
catalog/model/extension/module/bonus_manager::awardBonusesForOrder()
    ↓
For each order product:
    - Calculate bonus points for this item
    - Insert into customer_bonus_items
    ↓
Sum all product bonuses
    ↓
Insert ONE entry into customer_reward:
    - points = total_bonus
    - bonus_type = 'order_complete'
    - bonus_metadata = '{"bonus_pct": 10, ...}'
```

### 2. Product Return (Bonus Deduction)

```
Product Return Created in ocus_return
    ↓
Admin approves return (return_status_id changes)
    ↓
Trigger: returnProductBonuses($return_id)
    ↓
Get return details from ocus_return:
    - order_id, product_id, quantity (returned qty)
    ↓
Find matching order_product_id from ocus_order_product:
    - WHERE order_id = X AND product_id = Y
    ↓
Look up bonus_points from ocus_customer_bonus_items:
    - WHERE order_product_id = Z
    ↓
Calculate deduction:
    deduction = (return_qty / original_qty) * bonus_points
    ↓
Insert ONE negative entry into customer_reward:
    - points = -deduction
    - bonus_type = 'return_deduction'
    - bonus_metadata = '{"return_id": 88, "product_id": 102}'
    - description = 'Return deduction #XXX'
    ↓
Update ocus_customer_bonus_items:
    - bonus_points -= deduction (or delete if fully returned)
```

## Implementation Steps

### Phase 1: Database Migration
- [x] Create table schema
- [ ] Write migration SQL script
- [ ] Test migration on development database
- [ ] Verify indexes and foreign key constraints

### Phase 2: Model Updates

**File: `catalog/model/extension/module/bonus_manager.php`**

#### 2.1 Modify `awardBonusesForOrder()`

**Current behavior:**
```php
public function awardBonusesForOrder($order_id, $customer_id, $order_total) {
    // Calculate total bonus
    // Insert into customer_bonus_transaction
    // Update customer_reward
}
```

**New behavior:**
```php
public function awardBonusesForOrder($order_id, $customer_id, $order_total) {
    // Get order products from ocus_order_product
    $query = $this->db->query("
        SELECT order_product_id, product_id, name, model, quantity, price, total
        FROM " . DB_PREFIX . "order_product
        WHERE order_id = " . (int)$order_id
    );

    $total_bonus = 0;
    $bonus_percentage = $this->getBonusPercentage($customer_id); // Get current bonus %

    // Calculate and store bonus per product
    foreach ($query->rows as $product) {
        // Calculate bonus for this product line
        $product_bonus = (int)($product['total'] * ($bonus_percentage / 100));
        $total_bonus += $product_bonus;

        // Insert into ocus_customer_bonus_items
        $this->db->query("
            INSERT INTO " . DB_PREFIX . "customer_bonus_items
            (order_id, product_id, order_product_id, bonus_points, date_added)
            VALUES (
                " . (int)$order_id . ",
                " . (int)$product['product_id'] . ",
                " . (int)$product['order_product_id'] . ",
                " . (int)$product_bonus . ",
                NOW()
            )
        ");
    }

    // Insert into customer_reward (simplified - ONE insert!)
    $this->db->query("
        INSERT INTO " . DB_PREFIX . "customer_reward
        (customer_id, order_id, description, points, bonus_type, bonus_metadata, date_added)
        VALUES (
            " . (int)$customer_id . ",
            " . (int)$order_id . ",
            'Order #" . $order_id . " bonus',
            " . (int)$total_bonus . ",
            'order_complete',
            '{\"bonus_pct\":" . $bonus_percentage . "}',
            NOW()
        )
    ");

    return $total_bonus;
}
```

#### 2.2 Add New Methods

```php
/**
 * Process bonus deduction when a return is approved
 *
 * @param int $return_id Return ID from ocus_return table
 * @return bool Success status
 */
public function returnProductBonuses($return_id) {
    // Get return details
    $query = $this->db->query("
        SELECT r.order_id, r.product_id, r.customer_id, r.quantity as return_qty
        FROM " . DB_PREFIX . "return r
        WHERE r.return_id = " . (int)$return_id
    );

    if (!$query->num_rows) {
        return false;
    }

    $return = $query->row;

    // If order_id is specified in return, use it directly
    // Otherwise, find available orders with bonus points for this product
    if ($return['order_id'] > 0) {
        // Specific order provided
        $op_query = $this->db->query("
            SELECT order_product_id, quantity as original_qty
            FROM " . DB_PREFIX . "order_product
            WHERE order_id = " . (int)$return['order_id'] . "
            AND product_id = " . (int)$return['product_id'] . "
        ");
    } else {
        // No specific order - find oldest order with this product that has bonus points
        $op_query = $this->db->query("
            SELECT op.order_product_id, op.quantity as original_qty, op.order_id
            FROM " . DB_PREFIX . "order_product op
            INNER JOIN " . DB_PREFIX . "customer_bonus_items bi
                ON bi.order_product_id = op.order_product_id
            WHERE op.product_id = " . (int)$return['product_id'] . "
            AND bi.bonus_points > 0
            ORDER BY op.order_id ASC
            LIMIT 1
        ");
    }

    if (!$op_query->num_rows) {
        return false;
    }

    $order_product_id = $op_query->row['order_product_id'];
    $original_qty = $op_query->row['original_qty'];

    // Get bonus points for this product
    $bonus_query = $this->db->query("
        SELECT bonus_points
        FROM " . DB_PREFIX . "customer_bonus_items
        WHERE order_product_id = " . (int)$order_product_id
    ");

    if (!$bonus_query->num_rows) {
        return false; // No bonus awarded for this product
    }

    $bonus_points = $bonus_query->row['bonus_points'];

    // Calculate deduction (proportional to returned quantity)
    $deduction = (int)round(($return['return_qty'] / $original_qty) * $bonus_points);

    if ($deduction <= 0) {
        return false;
    }

    // Insert negative entry into customer_reward (simplified - ONE insert!)
    $this->db->query("
        INSERT INTO " . DB_PREFIX . "customer_reward
        (customer_id, order_id, description, points, bonus_type, bonus_metadata, date_added)
        VALUES (
            " . (int)$return['customer_id'] . ",
            " . (int)$return['order_id'] . ",
            'Return deduction #" . (int)$return_id . "',
            " . (-$deduction) . ",
            'return_deduction',
            '{\"return_id\":" . (int)$return_id . ",\"product_id\":" . (int)$return['product_id'] . "}',
            NOW()
        )
    ");

    // Update bonus_items table (reduce or delete)
    if ($return['return_qty'] >= $original_qty) {
        // Full return - delete the record
        $this->db->query("
            DELETE FROM " . DB_PREFIX . "customer_bonus_items
            WHERE order_product_id = " . (int)$order_product_id
        );
    } else {
        // Partial return - reduce bonus_points
        $this->db->query("
            UPDATE " . DB_PREFIX . "customer_bonus_items
            SET bonus_points = bonus_points - " . (int)$deduction . "
            WHERE order_product_id = " . (int)$order_product_id
        );
    }

    return true;
}

/**
 * Get bonus items for an order (for admin display)
 *
 * @param int $order_id
 * @return array Bonus items with product details
 */
public function getOrderBonusItems($order_id) {
    $query = $this->db->query("
        SELECT
            bi.bonus_item_id,
            bi.order_product_id,
            bi.bonus_points,
            bi.date_added,
            op.product_id,
            op.name as product_name,
            op.model,
            op.quantity,
            op.price,
            op.total
        FROM " . DB_PREFIX . "customer_bonus_items bi
        LEFT JOIN " . DB_PREFIX . "order_product op
            ON bi.order_product_id = op.order_product_id
        WHERE bi.order_id = " . (int)$order_id . "
        ORDER BY bi.bonus_item_id
    ");

    return $query->rows;
}
```

### Phase 3: Admin Interface

Add a new "Bonus Points" tab to the order info page with:
- Table showing per-product bonus breakdown
- "Deduct & Create Return" button for each product
- Button triggers both bonus deduction AND creates return entry in `ocus_return` table

**File: `admin/controller/sale/order.php`**

Add to the `info()` method:
```php
// Load bonus items for this order
$this->load->model('extension/module/bonus_manager');
$data['bonus_items'] = $this->model_extension_module_bonus_manager->getOrderBonusItems($order_id);
```

Add new AJAX handler method:
```php
public function deductBonusAndCreateReturn() {
    $json = array();

    if ($this->request->server['REQUEST_METHOD'] == 'POST') {
        $order_id = (int)$this->request->post['order_id'];
        $product_id = (int)$this->request->post['product_id'];
        $quantity = (int)$this->request->post['quantity'];

        // Get order info
        $this->load->model('sale/order');
        $order_info = $this->model_sale_order->getOrder($order_id);

        // Get product details from order_product
        $query = $this->db->query("
            SELECT * FROM " . DB_PREFIX . "order_product
            WHERE order_id = " . $order_id . " AND product_id = " . $product_id
        ");

        if ($query->num_rows) {
            $product = $query->row;

            // Insert into ocus_return table (status = Complete)
            $this->db->query("
                INSERT INTO " . DB_PREFIX . "return SET
                order_id = " . $order_id . ",
                product_id = " . $product_id . ",
                customer_id = " . (int)$order_info['customer_id'] . ",
                firstname = '" . $this->db->escape($order_info['firstname']) . "',
                lastname = '" . $this->db->escape($order_info['lastname']) . "',
                email = '" . $this->db->escape($order_info['email']) . "',
                telephone = '" . $this->db->escape($order_info['telephone']) . "',
                product = '" . $this->db->escape($product['name']) . "',
                model = '" . $this->db->escape($product['model']) . "',
                quantity = " . $quantity . ",
                opened = 0,
                return_reason_id = 1,
                return_action_id = 1,
                return_status_id = 3,
                comment = 'Created via bonus deduction',
                date_ordered = '" . $order_info['date_added'] . "',
                date_added = NOW(),
                date_modified = NOW()
            ");

            $return_id = $this->db->getLastId();

            // Deduct bonus points
            $this->load->model('extension/module/bonus_manager');
            $success = $this->model_extension_module_bonus_manager->returnProductBonuses($return_id);

            if ($success) {
                $json['success'] = 'Return #' . $return_id . ' created and bonus points deducted';
            } else {
                $json['error'] = 'Failed to deduct bonus points';
            }
        } else {
            $json['error'] = 'Product not found in order';
        }
    }

    $this->response->addHeader('Content-Type: application/json');
    $this->response->setOutput(json_encode($json));
}
```

**File: `admin/view/template/sale/order_info.twig`**

Add new tab in tab navigation:
```twig
<li><a href="#tab-bonus" data-toggle="tab">Bonus Points</a></li>
```

Add tab content:
```twig
<div class="tab-pane" id="tab-bonus">
  {% if bonus_items %}
  <div class="table-responsive">
    <table class="table table-bordered">
      <thead>
        <tr>
          <th>Product</th>
          <th>Model</th>
          <th>Quantity</th>
          <th>Price</th>
          <th>Total</th>
          <th>Bonus Points</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        {% for item in bonus_items %}
        <tr id="bonus-row-{{ item.order_product_id }}">
          <td>{{ item.product_name }}</td>
          <td>{{ item.model }}</td>
          <td>{{ item.quantity }}</td>
          <td>{{ item.price }}</td>
          <td>{{ item.total }}</td>
          <td><strong>{{ item.bonus_points }} pts</strong></td>
          <td>
            <button type="button" class="btn btn-danger btn-xs"
                    onclick="deductBonus({{ item.order_product_id }}, {{ item.product_id }}, {{ item.quantity }})">
              <i class="fa fa-minus-circle"></i> Deduct & Create Return
            </button>
          </td>
        </tr>
        {% endfor %}
      </tbody>
    </table>
  </div>

  <script>
  function deductBonus(orderProductId, productId, maxQty) {
    var quantity = prompt('Enter quantity to return (max ' + maxQty + '):');
    if (quantity && parseInt(quantity) > 0 && parseInt(quantity) <= maxQty) {
      $.ajax({
        url: 'index.php?route=sale/order/deductBonusAndCreateReturn&user_token={{ user_token }}',
        type: 'post',
        dataType: 'json',
        data: {
          order_id: {{ order_id }},
          order_product_id: orderProductId,
          product_id: productId,
          quantity: quantity
        },
        success: function(json) {
          if (json.success) {
            alert(json.success);
            location.reload();
          } else if (json.error) {
            alert('Error: ' + json.error);
          }
        },
        error: function() {
          alert('Failed to process request');
        }
      });
    } else {
      alert('Invalid quantity');
    }
  }
  </script>
  {% else %}
  <p>No bonus points awarded for this order.</p>
  {% endif %}
</div>
```

### Phase 4: Return Processing Integration

The system supports TWO workflows for handling returns:

**Workflow A: Admin-Initiated (via Order Page)**
1. Admin opens order in [admin/controller/sale/order.php](admin/controller/sale/order.php)
2. Clicks "Bonus Points" tab to view product breakdown
3. Clicks "Deduct & Create Return" button for specific product
4. Enters quantity to return
5. System creates entry in `ocus_return` table with `return_status_id = 3` (Complete)
6. System calls `returnProductBonuses($return_id)` to deduct points
7. Points deducted immediately, return is fully processed

**Workflow B: Automated (via Return Approval)**
- If returns are created manually in [admin/controller/sale/return.php](admin/controller/sale/return.php)
- Hook into return status change event
- When `return_status_id` changes to 3 (Complete), trigger bonus deduction
- Requires adding event handler or modifying return controller

**Recommended**: Start with Workflow A (manual via order page), add Workflow B automation later if needed.

### Phase 5: Testing

**Test Cases:**
1. **Full order return**: Award → Full deduction → Balance = 0
2. **Partial return**: Award 1000 → Return 3/5 items → Balance = 400
3. **Multiple returns**: Return different products at different times
4. **Negative balance prevention**: Don't allow deduction beyond available points
5. **customer_reward sync**: Verify total matches across tables
6. **Upgrade level handling**: Returns that drop customer below loyalty threshold

## Data Synchronization Rules

### Rule 1: No Synchronization Needed! (Simplified Architecture)

**With the single-table approach, synchronization bugs are impossible.**

We use ONLY `customer_reward` (with `bonus_type` field) for all transactions. There is no separate table to keep in sync.

```sql
-- All transactions in one place:
SELECT * FROM customer_reward WHERE customer_id = X;

-- Filter by type:
SELECT * FROM customer_reward WHERE customer_id = X AND bonus_type = 'order_complete';
SELECT * FROM customer_reward WHERE customer_id = X AND bonus_type = 'return_deduction';
```

### Rule 2: Product Items Should Sum to Order Bonus (Initially)
```sql
-- At order completion, sum of product bonuses = customer_reward entry
SUM(customer_bonus_items.bonus_points WHERE order_id = Y)
  =
customer_reward.points WHERE order_id = Y AND bonus_type = 'order_complete'
```

**Note**: After returns, individual product bonuses may be reduced/deleted, but customer_reward keeps full history.

### Rule 3: Return Deductions Create Negative Entries
```sql
-- When product returned (simplified - just ONE insert!):
INSERT INTO customer_reward (
    points = -N,
    bonus_type = 'return_deduction',
    bonus_metadata = '{"return_id": 88, "product_id": 102}'
)
UPDATE customer_bonus_items SET bonus_points = bonus_points - N
-- OR DELETE FROM customer_bonus_items if fully returned
```

### Rule 4: Prevent Over-Deduction
```sql
-- Cannot deduct more points than were awarded for that product
deduction_amount <= original bonus_points for that order_product_id
```

**Implementation**: Check current `bonus_points` value before deduction, prevent negative values.

## Migration Strategy

### For Existing Orders

**Problem**: Orders completed before this system won't have product-level records.

**Solution**:
1. **New orders only**: Start tracking from implementation date forward
2. **Retroactive fill** (optional): Create script to analyze past orders and populate bonus_items table

**Recommended**: New orders only, clearly documented cutoff date.

## Security Considerations

1. **Prevent double-deductions**: Check `quantity_returned` doesn't exceed `quantity`
2. **Validate order ownership**: Ensure customer_id matches before deducting
3. **Audit trail**: Keep original values, track modifications with date_modified
4. **Admin permissions**: Only authorized users can deduct points

## Performance Considerations

1. **Indexes**: Added on bonus_transaction_id, order_id, order_product_id
2. **Batch operations**: If processing many returns, batch the customer_reward updates
3. **Query optimization**: Use JOINs to retrieve bonus items with order details in single query

## Rollback Plan

If issues arise:
1. **Data preserved**: customer_bonus_transaction and customer_reward unchanged (except new entries)
2. **Disable returns**: Comment out deduction logic, revert to manual adjustment
3. **Table drop**: Can drop customer_bonus_items table without affecting core functionality

## Timeline Estimate

- **Phase 1** (Database): 1 hour
- **Phase 2** (Model): 4-6 hours
- **Phase 3** (Admin UI): 3-4 hours
- **Phase 4** (Integration): 2-3 hours
- **Phase 5** (Testing): 4-5 hours

**Total**: 14-19 hours development + testing

---

**Status**: ✅ Planning Complete - Ready for Implementation
**Date**: 2026-01-08
**Next Step**: Execute Phase 1 (Database Migration)
