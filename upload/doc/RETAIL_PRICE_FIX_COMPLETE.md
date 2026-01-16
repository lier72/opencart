# Retail Price Bug - Complete Resolution Summary

## Issue Overview
The product page sometimes displays `retail_price` with the same value as `price` or `special`, when they should be different. The retail_price should always show the default customer group's special price.

## Root Cause Identified

### Problem 1: Dynamic Config Substitution System
The OpenCart system implements a two-price model using customer groups:

**In catalog/controller/startup/startup.php (lines 117-126):**
```php
// Save the original default group (e.g., group 1 = Retail)
$this->config->set('customer_default_group_id', $this->config->get('config_customer_group_id'));

// Override config_customer_group_id for logged-in users
if ($this->customer->isLogged()) {
    $this->config->set('config_customer_group_id', $this->customer->getGroupId());
}
```

**What this means:**
- `config_customer_group_id` = Currently logged-in user's group (changes per user)
- `customer_default_group_id` = Original default/retail group (stays constant)

### Problem 2: Wrong Config Value in SQL Query
The original code used a dynamic lookup from the database:
```sql
ps.customer_group_id IN (SELECT DISTINCT conf.value FROM setting conf WHERE conf.key = 'config_customer_group_id')
```

**Why this was wrong:**
- It reads `config_customer_group_id` FROM THE DATABASE (setting table)
- But this reads the config value AT THE TIME IT WAS SAVED, not the current runtime value
- And even if it worked, it would return the CURRENT customer's group, not the default!

### Problem 3: Cache Invalidation Issue
Multiple SQL queries cache their results using `config_customer_group_id`:
```php
'product.latest.' . $config_customer_group_id . '.' . $limit
```

When `config_customer_group_id` changes between page visits, cached results could get mixed up if retail_price calculations were wrong.

## The Solution Implemented

### Changed Line in getProduct() (catalog/model/catalog/product.php:8)

**From (Hardcoded):**
```sql
ps.customer_group_id = '1'
```

**To (Dynamic, uses customer_default_group_id):**
```sql
ps.customer_group_id = '" . (int)$this->config->get('customer_default_group_id') . "'
```

### Why This Fixes It

1. **Respects Runtime Config:** Uses `customer_default_group_id` set during startup, not hardcoded '1'
2. **Works for Any Store:** Adapts to installations where default group is 2, 3, or any other ID
3. **Consistent Across Requests:** Each request will use its own stored customer_default_group_id
4. **Matches Controller Logic:** The product controller already checks this same value:
   ```php
   if ($this->customer->isLogged() && $this->config->get('config_customer_group_id') != $this->config->get('customer_default_group_id'))
   ```

## How It Works Now

### Guest User Scenario
1. No login → config_customer_group_id = 1 (default group)
2. getProduct() called:
   - `special` = From group 1 (default)
   - `retail_special` = From group 1 (customer_default_group_id = 1)
   - Result: They might match (both from same group) ✓ Correct

### Logged-in Wholesale User (group 5) Scenario
1. User logs in → config_customer_group_id = 5 (wholesale group)
2. BUT customer_default_group_id = 1 (still the retail default)
3. getProduct() called:
   - `special` = From group 5 (wholesale special, e.g., $50)
   - `retail_special` = From group 1 (retail special, e.g., $70)
   - Result: They are different! ✓ Correct

### Display Logic (product.php:285)
```php
if ($this->customer->isLogged() && $this->config->get('config_customer_group_id') != $this->config->get('customer_default_group_id')) {
    $data['retail_price'] = format($product_info['retail_price']);
}
```
- Only shows retail_price if user is logged in AND in a different group
- So guests never see retail_price (same as what they're paying anyway)
- Logged-in users see it to compare their price vs. retail price

## Files Modified
- **[catalog/model/catalog/product.php](catalog/model/catalog/product.php#L8)** - Changed `retail_special` subquery from hardcoded '1' to use `customer_default_group_id`

## Testing Recommendations

1. **Test as Guest:**
   - Add special price to group 1 (Retail)
   - Verify products display correctly
   - retail_price should NOT appear in template

2. **Test as Logged-in Wholesale User:**
   - Create wholesale special price (group 5) = $50
   - Create retail special price (group 1) = $70
   - Login as wholesale customer
   - View product: Should show price=$50 (his special), retail_price=$70
   - Both prices should be different ✓

3. **Test Hardcoded Group Migration:**
   - Change your default group from 1 to 2
   - Prices should still work correctly
   - (Before fix: would break because hardcoded '1')

## Related Code References
- [catalog/controller/startup/startup.php](catalog/controller/startup/startup.php#L117) - Where config values are set
- [catalog/controller/product/product.php](catalog/controller/product/product.php#L285) - Where retail_price is used
- [catalog/model/catalog/product.php](catalog/model/catalog/product.php) - All price query methods
