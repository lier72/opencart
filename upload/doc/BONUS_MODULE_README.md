# Bonus Manager Module - Installation & Usage Guide

## Overview
This module implements an intelligent bonus/cashback system for OpenCart 3.x that:
- ✅ Automatically awards bonuses when orders are completed
- ✅ Calculates bonuses per product based on customer group and category
- ✅ **Automatically excludes products with >15% discount** (configurable)
- ✅ **Excludes entire order if order-wide discount >15%**
- ✅ **Does NOT award bonuses if customer used bonuses to pay for the order**
- ✅ Prevents double accrual
- ✅ Fully configurable via admin panel

## Installation Steps

### 1. Files Installed
The following files have been created:

**Admin:**
- `admin/controller/extension/module/bonus_manager.php`
- `admin/model/extension/module/bonus_manager.php`
- `admin/view/template/extension/module/bonus_manager.twig`
- `admin/language/ru-ru/extension/module/bonus_manager.php`
- `admin/language/en-gb/extension/module/bonus_manager.php`

**Catalog:**
- `catalog/controller/extension/module/bonus_manager.php`
- `catalog/model/extension/module/bonus_manager.php`
- `catalog/language/ru-ru/extension/module/bonus_manager.php`
- `catalog/language/en-gb/extension/module/bonus_manager.php`

**Modified:**
- `catalog/controller/product/product.php` (added bonus calculation)

**Database:**
- Table: `ocus_bonus_settings` (created)

### 2. Install the Module

1. **Login to Admin Panel**: `http://localhost/~max/oc3.uniqsport.ru/admin/`

2. **Navigate to**: Extensions → Extensions → Modules

3. **Find "Bonus Manager"** in the list

4. **Click Install** (green + button)
   - This will create the event handler for automatic bonus accrual
   - Event: `bonus_manager_order_complete`

5. **Click Edit** (blue pencil button) to configure

### 3. Configure the Module

#### General Settings Tab:
- **Status**: Enabled
- **Discount Threshold**: 15% (products/orders with >15% discount won't earn bonuses)
- **Max Bonus Usage**: 30% (maximum percentage of cart subtotal that can be paid with bonuses)
- **Accrual Order Status**: Complete (bonuses awarded when order status changes to "Complete")
- **Excluded Categories**: Select any categories that should never earn bonuses (e.g., gift cards)

#### Bonus Settings Tab:
This is where you configure bonus percentages per customer group and category.

**Example configurations:**
```
Customer Group: Default    | Category: All (0)      | Bonus: 5%
Customer Group: Wholesale  | Category: All (0)      | Bonus: 3%
Customer Group: Default    | Category: Sports (64)  | Bonus: 7%
```

**Rules:**
- Category ID = 0 means "default for all categories"
- Specific category settings override the default
- If a product belongs to multiple categories, the first match is used

**How to add:**
1. Select Customer Group
2. Enter Category ID (or 0 for all)
3. Enter Bonus Percent (e.g., 5.00)
4. Click "Add Setting"

#### Statistics Tab:
View bonus program statistics:
- Total bonuses issued
- Total bonuses redeemed
- Active bonuses
- Number of customers with bonuses
- Recent bonus transactions

### 4. Verify Installation

Check that the event handler was created:
```sql
SELECT * FROM ocus_event WHERE code = 'bonus_manager_order_complete';
```

Should show:
- code: `bonus_manager_order_complete`
- trigger: `catalog/model/checkout/order/addOrderHistory/after`
- action: `extension/module/bonus_manager/awardBonusesOnOrderComplete`
- status: 1

## How It Works

### Bonus Accrual (Automatic)

When an order status changes to "Complete" (or configured status):

1. **Check if bonuses already awarded** (prevents double accrual)

2. **Check if customer used bonuses for this order**:
   - Query `order_total` table for `code = 'reward'`
   - **If bonuses were spent: EXIT** (no bonuses earned when bonuses are spent)

3. **Calculate order-wide discount**:
   - Get subtotal from `order_total` table
   - Get all discounts (coupons, vouchers, etc.)
   - Calculate: `discount_pct = (discounts / subtotal) × 100`
   - **If > 15%: EXIT** (no bonuses for entire order)

4. **For each product in order**:
   - Check if product category is excluded → SKIP
   - Calculate product discount: `(base_price - paid_price) / base_price × 100`
   - **If product discount > 15%: SKIP** this product
   - Get bonus % from `bonus_settings` for customer_group + category
   - Calculate: `bonus = product_subtotal × bonus_percent / 100`
   - Add to total_bonus

5. **Insert into customer_reward table**:
   ```sql
   INSERT INTO ocus_customer_reward
   (customer_id, order_id, points, description, date_added)
   VALUES (123, 456, 250, 'Бонусы за заказ #456', NOW())
   ```

### Bonus Spending (Using Bonuses at Checkout)

Customers can use their earned bonuses to get discounts on future orders:

**How it works:**
- 1 bonus = 1 RUB discount
- Maximum usage: configurable percentage of cart subtotal (default 30%)
- Example: Cart total = 1000 ₽, max usage = 30% → customer can use max 300 bonuses

**Requirements:**
- Customer must be logged in
- `total_reward` extension must be enabled
- Customer must have sufficient bonus balance

**Configuration:**
```sql
-- Enable reward total
INSERT INTO ocus_setting (store_id, code, `key`, value, serialized) VALUES
(0, 'total_reward', 'total_reward_status', '1', 0),
(0, 'total_reward', 'total_reward_sort_order', '3', 0);

-- Set max usage percentage (30% default)
INSERT INTO ocus_setting (store_id, code, `key`, value, serialized) VALUES
(0, 'module_bonus_manager', 'module_bonus_manager_max_usage_percent', '30', 0);
```

**Checkout Flow:**
1. Customer adds products to cart
2. At checkout, they see "Use Reward Points" option
3. System calculates max allowed: `min(customer_points, cart_subtotal * 30%)`
4. Customer enters amount of bonuses to use
5. Discount is applied proportionally across all cart products
6. Used bonuses are deducted from balance when order is confirmed

### Bonus Display on Product Page

Product pages now have variables available:
- `$data['bonus_amount']` - Numeric bonus value (e.g., 250)
- `$data['bonus_text']` - Formatted text (e.g., "Вы получите 250 ₽ бонусами")
- `$data['has_heavy_discount']` - Boolean, true if discount >15%

**To display in Journal3 template**, add to product template:
```twig
{% if bonus_text %}
<div class="bonus-info">
  <i class="fa fa-gift"></i> {{ bonus_text }}
</div>
{% endif %}

{% if has_heavy_discount %}
<div class="no-bonus-info">
  <i class="fa fa-exclamation-circle"></i> Бонусы не начисляются (спецпредложение)
</div>
{% endif %}
```

## Testing Scenarios

### Test 1: Normal Purchase (Should Award Bonuses)
1. Create test product: Price = 1000 ₽, no special price
2. Set bonus for Default group: 5%
3. Create order, set status to "Complete"
4. Check `ocus_customer_reward` table
5. **Expected**: 50 bonuses awarded (1000 × 5%)

### Test 2: Product with 20% Discount (Should NOT Award Bonuses)
1. Product: Price = 1000 ₽, Special = 800 ₽ (20% off)
2. Create order, complete it
3. **Expected**: No bonuses (discount >15%)

### Test 3: Product with 10% Discount (Should Award Bonuses)
1. Product: Price = 1000 ₽, Special = 900 ₽ (10% off)
2. Set bonus: 5%
3. **Expected**: 45 bonuses awarded (900 × 5%)

### Test 4: Order with Coupon >15% (Should NOT Award Bonuses)
1. Products subtotal: 1000 ₽
2. Apply coupon: -200 ₽ (20% discount)
3. Complete order
4. **Expected**: No bonuses for entire order

### Test 5: Excluded Category (Should NOT Award Bonuses)
1. Add Category ID to "Excluded Categories" setting
2. Create order with product from that category
3. **Expected**: No bonuses

### Test 6: Different Customer Groups
1. Set: Default group = 5%, Wholesale group = 3%
2. Create order for wholesale customer
3. **Expected**: 3% bonuses (not 5%)

### Test 7: Prevent Double Accrual
1. Complete an order (bonuses awarded)
2. Change status back to Pending
3. Change status to Complete again
4. **Expected**: No additional bonuses (already awarded)

### Test 8: Bonus Spending with 30% Limit
1. Customer has 1000 bonuses
2. Cart total: 500 ₽
3. Max allowed usage: 500 × 30% = 150 bonuses
4. Customer tries to use 200 bonuses
5. **Expected**: Error message, max 150 bonuses allowed

### Test 9: Bonus Spending - Full Flow
1. Customer has 500 bonuses
2. Cart total: 2000 ₽ (max 30% = 600 bonuses)
3. Customer uses 300 bonuses
4. Order total reduced by 300 ₽
5. Complete order
6. Check customer_reward table
7. **Expected**: -300 points entry for the order, NO new positive points awarded

### Test 10: No Bonuses When Bonuses Are Spent
1. Customer has 1000 bonuses
2. Cart: 2000 ₽ worth of products (would normally earn 5% = 100 bonuses)
3. Customer uses 300 bonuses at checkout
4. Pays: 1700 ₽
5. Complete order
6. Check `order_total` for `code = 'reward'`
7. Check `customer_reward` table
8. **Expected**: Only -300 entry (spent), NO +100 entry (not earned because bonuses were used)

## SQL Queries for Testing

### Check bonuses awarded for an order:
```sql
SELECT * FROM ocus_customer_reward
WHERE order_id = 123;
```

### View customer's total bonus balance:
```sql
SELECT customer_id, SUM(points) as balance
FROM ocus_customer_reward
GROUP BY customer_id;
```

### View all bonus settings:
```sql
SELECT bs.*, cgd.name as group_name
FROM ocus_bonus_settings bs
LEFT JOIN ocus_customer_group_description cgd ON bs.customer_group_id = cgd.customer_group_id
WHERE cgd.language_id = 2;
```

### Recent bonus transactions:
```sql
SELECT cr.*, o.order_id, CONCAT(c.firstname, ' ', c.lastname) as customer
FROM ocus_customer_reward cr
LEFT JOIN ocus_order o ON cr.order_id = o.order_id
LEFT JOIN ocus_customer c ON cr.customer_id = c.customer_id
WHERE cr.points > 0
ORDER BY cr.date_added DESC
LIMIT 10;
```

## Configuration Options

### Module Settings (stored in `ocus_setting`):
- `module_bonus_manager_status` - Enable/disable (1/0)
- `module_bonus_manager_discount_threshold` - Discount % threshold (default: 15)
- `module_bonus_manager_accrual_status_id` - Order status ID for accrual
- `module_bonus_manager_excluded_categories` - Array of category IDs

### Order Statuses (get ID from `ocus_order_status`):
- Complete: Usually ID = 5
- Processing: Usually ID = 2
- Shipped: Usually ID = 3

## Logs

Bonus accrual activity is logged to OpenCart error log:
```
Location: /Users/max/Sites/storage/logs/error.log
```

**Log messages:**
- `BONUS: Awarded X bonuses for order #123`
- `BONUS: Bonuses already awarded for order #123`
- `BONUS: Order #123 has 20% discount. No bonuses awarded.`
- `BONUS: Product #456 has 18% discount. Skipped.`

## Uninstallation

1. Go to Extensions → Extensions → Modules
2. Find "Bonus Manager"
3. Click Uninstall (red - button)
   - This removes the event handler
   - Does NOT delete the database table or bonus data

To completely remove:
```sql
DROP TABLE ocus_bonus_settings;
DELETE FROM ocus_setting WHERE `key` LIKE 'module_bonus_manager%';
```

## Phase 2 Features (Future)

The following features are planned for Phase 2:
- Bonus expiration (180 days)
- Email notifications (30 days, 7 days before expiration)
- Customer tier system (Bronze/Silver/Gold/Pro)
- Enhanced UX with header widget
- Analytics dashboard

## Support & Troubleshooting

### Bonuses not being awarded:
1. Check module is enabled in admin
2. Verify event handler exists: `SELECT * FROM ocus_event WHERE code = 'bonus_manager_order_complete'`
3. Check order status ID matches configuration
4. Review error.log for bonus-related messages
5. Verify discount threshold settings

### Bonus calculation seems wrong:
1. Check bonus_settings table for customer_group_id
2. Verify product categories are correct
3. Check for special prices causing >15% discount
4. Test order discount calculation (coupons, etc.)

### Frontend not showing bonuses:
1. Verify `$data['bonus_text']` is being set in product controller
2. Check Journal3 template is using the variable
3. Clear OpenCart cache (System → Maintenance → clear cache)

## Database Schema

```sql
CREATE TABLE `ocus_bonus_settings` (
  `bonus_setting_id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_group_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL DEFAULT 0,
  `bonus_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `date_added` datetime NOT NULL,
  `date_modified` datetime NOT NULL,
  PRIMARY KEY (`bonus_setting_id`),
  KEY `customer_group_id` (`customer_group_id`),
  KEY `category_id` (`category_id`),
  UNIQUE KEY `group_category` (`customer_group_id`, `category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Uses existing OpenCart table:
```sql
ocus_customer_reward (
  customer_reward_id INT AUTO_INCREMENT,
  customer_id INT,
  order_id INT,
  description TEXT,
  points INT,
  date_added DATETIME
)
```

---

**Module Version**: 1.0 (Phase 1)
**OpenCart Version**: 3.0.3.6
**Created**: 2026-01-06
**Author**: Custom Development
