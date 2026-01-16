# Loyalty Program Levels - Implementation Summary

## Overview
This document describes the automatic customer group upgrade system based on purchase totals within a defined program period.

## Core Concept
- Customers automatically move to better pricing tiers (customer groups) as they reach spending thresholds
- Loyalty levels are calculated based on purchases within the **current program year** (default: Jan 1 - Dec 31)
- Better customer groups = better product prices (existing OpenCart feature)
- All configuration stored in `ocus_setting` table as JSON

## Database Changes

### Settings Added to `ocus_setting` Table

1. **`module_bonus_manager_loyalty_status`** (value: `1`)
   - Enable/disable automatic loyalty upgrades
   - Set to `0` to disable the feature entirely

2. **`module_bonus_manager_loyalty_levels`** (JSON array)
   - Defines customer group tiers and spending thresholds
   - Default configuration:
   ```json
   [
     {"customer_group_id": 1, "min_total_spent": 0},
     {"customer_group_id": 2, "min_total_spent": 50000},
     {"customer_group_id": 6, "min_total_spent": 100000}
   ]
   ```
   - Meaning:
     - **Group 1 (Default Customer)**: 0₽ - 49,999₽
     - **Group 2 (Sportsmen)**: 50,000₽ - 99,999₽
     - **Group 6 (Friend -15%)**: 100,000₽+

3. **`module_bonus_manager_loyalty_info_id`** (value: `12`)
   - Points to information page that describes the loyalty program
   - Used for linking from registration widget

4. **`module_bonus_manager_loyalty_period_start`** (default: `01-01`)
   - Program period start date (MM-DD format)
   - Default: January 1st
   - Program runs for 1 year from this date

### Information Page Created

**Information ID**: 12

**English Content**:
- Title: "Loyalty Program Levels"
- Describes 3-tier system with benefits
- Explains bonus points rules
- How automatic upgrades work

**Russian Content**:
- Title: "Уровни программы лояльности"
- Detailed explanation in Russian
- Same structure as English version

## Code Implementation

### Admin Model: `admin/model/extension/module/bonus_manager.php`

#### New Methods Added:

1. **`checkAndUpgradeCustomer($customer_id)`**
   - Main method to check if customer qualifies for upgrade
   - Calculates total spent in current program period
   - Finds highest level customer qualifies for
   - Updates `customer_group_id` if different
   - Logs upgrade to system log
   - **Returns**: `true` if upgraded, `false` otherwise

2. **`getCustomerTotalSpent($customer_id)`**
   - Calculates customer's total spending within current program period
   - Only counts orders with `order_status_id = accrual_status_id` (default: 5 = Complete)
   - **Program Period Logic**:
     - Gets current year
     - Determines program start date (e.g., 2026-01-01)
     - If we're before start date, uses previous year's period
     - Calculates period end (1 year from start)
     - Sums order totals within this date range
   - **Returns**: Float amount spent

3. **`getLoyaltyLevels()`**
   - Retrieves loyalty levels configuration from settings
   - Parses JSON and sorts by `min_total_spent` ascending
   - **Returns**: Array of level configurations

4. **`saveLoyaltyLevels($levels)`**
   - Saves loyalty levels configuration to settings table
   - Accepts array, converts to JSON
   - **Returns**: Boolean success status

### Catalog Model: `catalog/model/extension/module/bonus_manager.php`

#### Modified Method:

**`awardBonusesForOrder($order_id)`**
- Added call to `checkCustomerLoyaltyUpgrade()` after bonuses are awarded
- This ensures loyalty level is checked every time an order is completed

#### New Method Added:

**`checkCustomerLoyaltyUpgrade($customer_id)`**
- Private helper method
- Loads admin model and calls `checkAndUpgradeCustomer()`
- Logs result
- This bridges catalog → admin model communication

### Controller: `catalog/controller/extension/module/bonus_display.php`

#### Modified Method:

**`registerWidget()`**
- Added loyalty program information page link
- Gets `module_bonus_manager_loyalty_info_id` from settings
- Generates URL: `information/information&information_id=12`
- Passes to template as `loyalty_info_link`
- Also passes `language_code` for conditional text

### Template: `catalog/view/theme/journal3/template/extension/module/bonus_display_register.twig`

#### Added Section:

New "Loyalty Program Link" block that:
- Shows between benefits list and register button
- Links to information page (ID 12)
- Bilingual text (Russian/English)
- Styled with white text on semi-transparent background
- Includes star icon and arrow icon

## How It Works

### Workflow Example:

1. **Customer Registration**
   - New customer registers → assigned to Group 1 (Default Customer)
   - Sees loyalty program link in registration widget
   - Can click to learn about levels

2. **First Purchase**
   - Customer places order for 30,000₽
   - Order status changes to "Complete" (status_id = 5)
   - `awardBonusesForOrder()` is triggered
   - Bonuses are awarded
   - `checkCustomerLoyaltyUpgrade()` is called
   - Total spent calculated: 30,000₽ (within current year)
   - Still below 50,000₽ threshold → no upgrade
   - Customer remains in Group 1

3. **Second Purchase (Upgrade)**
   - Customer places another order for 25,000₽
   - Order completes
   - Bonuses awarded
   - `checkCustomerLoyaltyUpgrade()` is called
   - Total spent calculated: 30,000₽ + 25,000₽ = 55,000₽
   - **Exceeds 50,000₽ threshold!**
   - Customer upgraded to Group 2 (Sportsmen)
   - `customer_group_id` updated in database
   - Upgrade logged: `LOYALTY UPGRADE: Customer #123 upgraded from group #1 to group #2 (spent: 55000)`

4. **Price Update**
   - Customer's next visit to site
   - Product prices automatically reflect Group 2 pricing
   - Better discounts applied (configured in OpenCart product prices)

### Annual Program Period:

**Example with Jan 1 start date:**

- **2026 Program Period**: Jan 1, 2026 00:00:00 → Dec 31, 2026 23:59:59
- Customer purchases in 2026 count toward 2026 levels
- On January 1, 2027, a new period starts
- Customer's 2027 purchases count toward 2027 levels

**Important Behavior:**
- Customer groups are NOT reset on Jan 1
- Customer keeps their current group until they stop qualifying
- If customer doesn't purchase in 2027, they'll stay in their current group
- If they do purchase but don't reach threshold, they'll be downgraded to appropriate level

## Installation

### Already Completed:
1. ✅ Ran `admin/bonus_loyalty_levels_install.sql`
2. ✅ Created information page (ID 12)
3. ✅ Added 3 settings to `ocus_setting` table
4. ✅ Updated all code files

### Still TODO (If Needed):
1. **Admin UI for Managing Levels**
   - Add tab in Bonus Manager admin panel
   - Form to add/edit/delete loyalty levels
   - Configure program period start date
   - Enable/disable auto-upgrades

2. **Cron Job for Periodic Checks** (Optional)
   - Check all customers periodically
   - Useful if you want to upgrade customers even without new orders
   - Can run monthly: "Check if any customers crossed threshold"

3. **Customer Notification** (Optional)
   - Send email when customer is upgraded
   - "Congratulations! You've reached Sportsmen level"

4. **Admin Reporting** (Optional)
   - Dashboard widget showing level distribution
   - "50 customers in Default, 30 in Sportsmen, 20 in Friend-15"

## Configuration Options

### Current Settings:

| Setting | Value | Description |
|---------|-------|-------------|
| `module_bonus_manager_loyalty_status` | `1` | Enable auto-upgrades |
| `module_bonus_manager_loyalty_levels` | JSON | Level thresholds |
| `module_bonus_manager_loyalty_info_id` | `12` | Info page ID |
| `module_bonus_manager_loyalty_period_start` | `01-01` | Program start date |
| `module_bonus_manager_accrual_status_id` | `5` | Order status that counts (Complete) |

### To Change Program Period Start Date:

```sql
INSERT INTO ocus_setting (store_id, code, `key`, value, serialized)
VALUES (0, 'module_bonus_manager', 'module_bonus_manager_loyalty_period_start', '04-01', 0)
ON DUPLICATE KEY UPDATE value = '04-01';
```

This would change program to run April 1 - March 31.

### To Add a New Loyalty Level:

```sql
UPDATE ocus_setting
SET value = '[{"customer_group_id":1,"min_total_spent":0},{"customer_group_id":2,"min_total_spent":50000},{"customer_group_id":6,"min_total_spent":100000},{"customer_group_id":7,"min_total_spent":200000}]'
WHERE `key` = 'module_bonus_manager_loyalty_levels';
```

This adds a 4th level (Group 7) at 200,000₽.

## Files Modified

### Database:
- `/admin/bonus_loyalty_levels_install.sql` (NEW)
- `ocus_setting` table (3 new rows)
- `ocus_information` table (1 new row, ID 12)
- `ocus_information_description` table (2 new rows, EN + RU)
- `ocus_information_to_store` table (1 new row)
- `ocus_information_to_layout` table (1 new row)

### PHP Files:
- `/admin/model/extension/module/bonus_manager.php` (added loyalty methods)
- `/catalog/model/extension/module/bonus_manager.php` (added upgrade trigger)
- `/catalog/controller/extension/module/bonus_display.php` (added info link)

### Template Files:
- `/catalog/view/theme/journal3/template/extension/module/bonus_display_register.twig` (added loyalty link)

## Testing

### Manual Test Procedure:

1. **Verify Information Page**
   ```
   http://localhost/~max/oc3.uniqsport.ru/index.php?route=information/information&information_id=12
   ```
   Should show loyalty program description in current language.

2. **Test Registration Widget**
   - Logout
   - Add items to cart
   - View cart
   - Should see registration widget with loyalty program link

3. **Test Automatic Upgrade**
   - Login as test customer
   - Place order for 30,000₽
   - Complete order (change status to Complete in admin)
   - Check customer record: `customer_group_id` should still be 1
   - Place another order for 25,000₽
   - Complete order
   - Check customer record: `customer_group_id` should now be 2

4. **Verify Logs**
   ```
   tail -f /Users/max/Sites/storage/logs/error.log | grep LOYALTY
   ```
   Should show upgrade entries when thresholds are crossed.

5. **Test Program Period**
   - Manually change date in database to test period boundaries
   - Verify only orders within current period are counted

## Troubleshooting

### Customer Not Upgrading:

1. **Check if feature is enabled:**
   ```sql
   SELECT * FROM ocus_setting WHERE `key` = 'module_bonus_manager_loyalty_status';
   ```
   Should return `value = 1`.

2. **Check if levels are configured:**
   ```sql
   SELECT * FROM ocus_setting WHERE `key` = 'module_bonus_manager_loyalty_levels';
   ```
   Should return valid JSON array.

3. **Check logs:**
   ```bash
   grep "LOYALTY" /Users/max/Sites/storage/logs/error.log
   ```

4. **Manually check customer's total:**
   ```sql
   SELECT SUM(total) as total_spent
   FROM ocus_order
   WHERE customer_id = 123
   AND order_status_id = 5
   AND date_added >= '2026-01-01 00:00:00'
   AND date_added < '2027-01-01 00:00:00';
   ```

5. **Manually trigger upgrade:**
   ```php
   $this->load->model('extension/module/bonus_manager');
   $upgraded = $this->model_extension_module_bonus_manager->checkAndUpgradeCustomer(123);
   var_dump($upgraded); // Should be true if upgrade happened
   ```

### Information Page Not Showing:

1. **Verify page exists:**
   ```sql
   SELECT * FROM ocus_information WHERE information_id = 12;
   ```

2. **Verify setting exists:**
   ```sql
   SELECT * FROM ocus_setting WHERE `key` = 'module_bonus_manager_loyalty_info_id';
   ```

3. **Check template variable:**
   Add to template:
   ```twig
   {{ dump(loyalty_info_link) }}
   ```

## Future Enhancements

### Possible Improvements:

1. **Downgrades**
   - Currently customers are never downgraded
   - Could add logic to downgrade if they don't maintain spending in new period

2. **Multi-Year Tracking**
   - Track customer's historical spending across multiple years
   - "Lifetime VIP" status for customers who maintain high spending for 3+ years

3. **Tier Benefits Display**
   - Show customer's current tier on account dashboard
   - "You're a Sportsmen member! 15,000₽ more to reach Friend-15 level"
   - Progress bar to next level

4. **Custom Tier Names**
   - Allow admin to configure tier names in settings
   - Currently hardcoded in information page

5. **Grace Period**
   - Give customers 30-day grace period when new period starts
   - Don't downgrade immediately on Jan 1

6. **Separate Admin Tab**
   - Dedicated "Loyalty Levels" tab in Bonus Manager admin
   - CRUD interface for levels
   - Customer level distribution chart
   - Recent upgrades log

## Notes

- **Important**: Customer groups must be configured with different pricing in OpenCart product management
- The loyalty system only handles automatic group assignment
- Actual price differences are managed through OpenCart's standard customer group pricing
- Make sure to set up group-specific prices for products to see the benefit

## Support

For questions or issues:
1. Check logs: `/Users/max/Sites/storage/logs/error.log`
2. Search for `LOYALTY` or `BONUS` entries
3. Verify database settings are correct
4. Test with small threshold (e.g., 100₽) to verify functionality

---

**Implementation Date**: 2026-01-08
**OpenCart Version**: 3.0.3.6
**Theme**: Journal3
