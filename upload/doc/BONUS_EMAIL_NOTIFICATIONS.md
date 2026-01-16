# Bonus Email Notifications - Implementation Summary

## Overview
Email notifications for bonus points are implemented using direct controller calls, following OpenCart's standard patterns (similar to how `mail/order` works).

## Architecture

### 1. Bonus Awarded Email

**Trigger Point:** When order status changes to "Complete" (or configured accrual status)

**Flow:**
1. Admin changes order status → `model/checkout/order/addOrderHistory()` is called
2. Loader automatically triggers `catalog/model/checkout/order/addOrderHistory/after` event
3. Event handler `extension/module/bonus_manager/awardBonusesOnOrderComplete` executes
4. Calls `awardBonusesForOrder()` which:
   - Calculates bonuses for each product using `getProductBonus()`
   - Inserts bonus points into `customer_reward` table
   - **Directly calls** `mail/bonus/awarded` controller

**Code Location:** [catalog/model/extension/module/bonus_manager.php:79](catalog/model/extension/module/bonus_manager.php#L79)

```php
// Send email notification
$this->load->controller('mail/bonus/awarded', array($order_info, $rounded_bonus));
```

**Email Controller:** [catalog/controller/mail/bonus.php:7](catalog/controller/mail/bonus.php#L7)

### 2. Bonus Spent Email

**Trigger Point:** When order is confirmed (reaches processing/complete status) and customer used bonus points

**Flow:**
1. Order reaches processing/complete status
2. `model/checkout/order/addOrderHistory()` calls `confirm()` on all order totals
3. `model/extension/total/reward/confirm()` is called
4. Deducts bonus points from `customer_reward` table
5. **Directly calls** `mail/bonus/spent` controller

**Code Location:** [catalog/model/extension/total/reward.php:76](catalog/model/extension/total/reward.php#L76)

```php
// Send email notification about spent bonuses
$this->load->controller('mail/bonus/spent', array($order_info, $points));
```

**Email Controller:** [catalog/controller/mail/bonus.php:125](catalog/controller/mail/bonus.php#L125)

## Why Direct Controller Calls?

### This approach follows OpenCart's core patterns:

1. **mail/order** - Called directly from order processing code
2. **mail/affiliate** - Called directly from affiliate registration
3. **No unnecessary events** - Events are used for hooking into existing methods, not for notifications

### Benefits:

- ✅ **Simple and maintainable** - Easy to trace the flow
- ✅ **No event overhead** - No need to register/manage extra events
- ✅ **Standard OpenCart pattern** - Follows how core does email notifications
- ✅ **Centralized configuration** - Email settings in one place (admin module)

## Email Configuration

Both email types can be enabled/disabled and customized in the admin panel:

**Bonus Awarded:**
- `module_bonus_manager_email_awarded_status` - Enable/disable
- `module_bonus_manager_email_awarded_subject` - Email subject template
- `module_bonus_manager_email_awarded_body` - Email body template (HTML)

**Bonus Spent:**
- `module_bonus_manager_email_spent_status` - Enable/disable
- `module_bonus_manager_email_spent_subject` - Email subject template
- `module_bonus_manager_email_spent_body` - Email body template (HTML)

## Available Placeholders

### Bonus Awarded Email:
- `{customer_firstname}`, `{customer_lastname}`
- `{order_id}` - Order number
- `{bonus_amount}` - Amount of bonuses awarded
- `{current_balance}` - Current total bonus balance
- `{max_usage_percent}` - Maximum % of order that can be paid with bonuses
- `{store_name}`, `{store_url}`
- `{date_awarded}` - Date bonuses were awarded
- `{account_url}` - Link to customer account
- `{order_url}` - Link to order details

### Bonus Spent Email:
- `{customer_firstname}`, `{customer_lastname}`
- `{order_id}` - Order number
- `{points_spent}` - Amount of bonuses spent
- `{current_balance}` - Current total bonus balance (after spending)
- `{store_name}`, `{store_url}`
- `{date_spent}` - Date bonuses were spent
- `{account_url}` - Link to customer account
- `{order_url}` - Link to order details

## Testing

### Test Bonus Awarded Email:
1. Enable "Bonus Awarded Notification" in admin
2. Place a test order with a registered customer
3. Change order status to "Complete" (or configured accrual status) through admin
4. Customer should receive email with bonus award details

### Test Bonus Spent Email:
1. Enable "Bonus Spent Notification" in admin
2. Customer must have bonus points in their account
3. Place order and apply bonus points at checkout
4. Confirm order (change to processing/complete status)
5. Customer should receive email with bonus spending details

## Code Improvements Made

1. **Eliminated code duplication** - `awardBonusesForOrder()` now uses `getProductBonus()` instead of duplicating logic
2. **Removed unnecessary custom events** - No need for `catalog/model/extension/module/bonus_manager/awarded` event
3. **Follows OpenCart patterns** - Direct controller calls like core email notifications
4. **Proper configuration checks** - Both email methods check if notifications are enabled before sending

## Files Modified

1. **catalog/model/extension/module/bonus_manager.php** - Calls `mail/bonus/awarded` directly
2. **catalog/model/extension/total/reward.php** - Calls `mail/bonus/spent` directly
3. **catalog/controller/mail/bonus.php** - Email handlers (already existed, added config check for spent)
4. **Database** - Removed unnecessary `bonus_email_awarded` event

## Database Events

Only one event is needed for the bonus system:

```sql
SELECT * FROM ocus_event WHERE code LIKE '%bonus%';

event_id: 57
code: bonus_manager_order_complete
trigger: catalog/model/checkout/order/addOrderHistory/after
action: extension/module/bonus_manager/awardBonusesOnOrderComplete
status: 1
```

This event handles bonus accrual. Email notifications are handled by direct controller calls within the bonus accrual logic.
