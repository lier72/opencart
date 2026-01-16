# Retail Price Bug - Deep Dive Analysis

## Problem Summary
The `retail_price` sometimes shows the same value as `price` (or `special`), when it should always show the default customer group's special price.

## Root Cause Analysis

### 1. Dynamic Config Substitution (catalog/controller/startup/startup.php:117-126)
```php
$this->config->set('customer_default_group_id', $this->config->get('config_customer_group_id'));
// Store the ORIGINAL default group (usually 1 - Retail)

if (isset($this->session->data['customer']) && isset($this->session->data['customer']['customer_group_id'])) {
    $this->config->set('config_customer_group_id', $this->session->data['customer']['customer_group_id']);
} elseif ($this->customer->isLogged()) {
    $this->config->set('config_customer_group_id', $this->customer->getGroupId());
} elseif (isset($this->session->data['guest']) && isset($this->session->data['guest']['customer_group_id'])) {
    $this->config->set('config_customer_group_id', $this->session->data['guest']['customer_group_id']);
}
// NOW config_customer_group_id is CHANGED to logged-in customer's group
```

**Key insight:** The system stores the original default group in `customer_default_group_id` BEFORE swapping `config_customer_group_id` for logged-in customers.

### 2. SQL Query Problem in getProduct() (catalog/model/catalog/product.php:8)

**Original problematic query:**
```sql
(SELECT price FROM product_special ps 
 WHERE ps.product_id = p.product_id 
 AND ps.customer_group_id IN (SELECT DISTINCT conf.value FROM setting conf WHERE conf.key = 'config_customer_group_id') 
 ...) AS retail_special
```

**Current (my) fix:**
```sql
(SELECT price FROM product_special ps 
 WHERE ps.product_id = p.product_id 
 AND ps.customer_group_id = '1'
 ...) AS retail_special
```

**Problem with my fix:** It HARDCODES customer_group_id to '1', but what if the default retail group is different in this installation?

### 3. The Real Issue: Wrong Config Value Used

When `getProduct()` is called:
- `config_customer_group_id` = Currently logged-in customer's group (e.g., group 5 - Wholesale)
- `customer_default_group_id` = Original default/retail group (e.g., group 1)

The SQL query should use:
- For `special`: The **current** user's group (config_customer_group_id) ✓ Currently correct
- For `retail_special`: The **default/retail** group (customer_default_group_id) ✗ Currently uses hardcoded '1'

## Why retail_price Shows Same as price/special

### Scenario:
1. Guest visits site → Both groups 1 (default)
2. Logged-in customer group 5 (wholesale) visits → config_customer_group_id = 5, customer_default_group_id = 1
3. getProduct() is called:
   - `special` = Gets price from product_special WHERE customer_group_id = 5 (wholesale special)
   - `retail_special` = Gets price from product_special WHERE customer_group_id = 1 (default special)
4. BUT if there's no special for group 5, OR if both groups have the same special price, they match!

## The Fix Strategy

Instead of hardcoding `'1'`, use `customer_default_group_id` from config:

**Original (before my fix):**
```sql
ps.customer_group_id IN (SELECT DISTINCT conf.value FROM setting conf WHERE conf.key = 'config_customer_group_id')
```
Problem: Reads config_customer_group_id from database, which is the CURRENT user's group, not the default!

**My current fix:**
```sql
ps.customer_group_id = '1'
```
Problem: Assumes retail group is always 1, might break in other installations.

**Optimal fix:**
```sql
ps.customer_group_id = '" . (int)$this->config->get('customer_default_group_id') . "'
```
Uses the PHP config value (set during startup) instead of the database, ensuring correct default group is always used.

## Where This Matters

1. **Product Page** (catalog/controller/product/product.php:285):
   - Checks if `config_customer_group_id != customer_default_group_id`
   - Only shows retail_price to logged-in users from different groups

2. **Cache Keys** (catalog/model/catalog/product.php:263, 279, 295):
   - Uses config_customer_group_id in cache key
   - Different users get different cache keys

3. **All Price Queries** (Multiple methods):
   - getProduct(), getProductSpecials(), getProductDiscounts()
   - All use config_customer_group_id which changes per user

## Conclusion

The real solution is to use `customer_default_group_id` for the retail_special subquery, not a hardcoded value or the config_customer_group_id.

This ensures:
- Retail price is ALWAYS fetched from the default group
- Works regardless of what the actual default group ID is
- Maintains consistency across multi-group pricing systems
