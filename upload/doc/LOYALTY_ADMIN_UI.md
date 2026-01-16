# Loyalty Levels - Admin UI Documentation

## Overview
Admin interface for managing the automatic customer group upgrade system based on annual purchase thresholds.

## Accessing the Settings

1. Login to OpenCart Admin
2. Navigate to: **Extensions → Modules → Bonus Manager**
3. Click on the **"Loyalty Levels"** tab

## Settings Available

### 1. Enable Automatic Loyalty Upgrades
- **Setting**: Enable/Disable toggle
- **Default**: Enabled (if installed via SQL script)
- **Description**: When enabled, customers are automatically upgraded to better pricing tiers when they reach spending thresholds
- **Database Key**: `module_bonus_manager_loyalty_status`

### 2. Loyalty Program Information Page
- **Setting**: Dropdown menu of all information pages
- **Default**: Page ID 12 (created by installation script)
- **Description**: Select the information page that explains your loyalty program to customers
- **Database Key**: `module_bonus_manager_loyalty_info_id`
- **Note**: This page link appears in the registration widget for guests

### 3. Program Period Start Date
- **Setting**: Text input (MM-DD format)
- **Default**: `01-01` (January 1st)
- **Description**: The start date of the annual loyalty program period
- **Format**: MM-DD (e.g., `01-01` for Jan 1, `04-01` for April 1)
- **Database Key**: `module_bonus_manager_loyalty_period_start`
- **Example**: If set to `01-01`, the program runs from January 1 - December 31

### 4. Loyalty Level Thresholds
- **Setting**: Dynamic table with add/remove functionality
- **Columns**:
  - **Customer Group**: Dropdown of all customer groups
  - **Minimum Total Spent**: Number input (in ₽)
- **Database Key**: `module_bonus_manager_loyalty_levels` (stored as JSON)

**Default Levels** (from installation script):
```json
[
  {"customer_group_id": 1, "min_total_spent": 0},      // Default Customer - 0₽
  {"customer_group_id": 2, "min_total_spent": 50000},  // Sportsmen - 50,000₽
  {"customer_group_id": 6, "min_total_spent": 100000}  // Friend -15% - 100,000₽
]
```

## How to Configure Loyalty Levels

### Adding a New Level:

1. Go to **Loyalty Levels** tab
2. Click the **"+ Add Level"** button at the bottom of the table
3. Select the **Customer Group** from the dropdown
4. Enter the **Minimum Total Spent** amount (in rubles)
5. Click **Save** at the top right

### Editing an Existing Level:

1. Find the level in the table
2. Change the Customer Group or Minimum Total Spent value
3. Click **Save** at the top right

### Removing a Level:

1. Click the **red minus button** (🗑️) next to the level you want to remove
2. Click **Save** at the top right
3. **Note**: You must have at least one level configured

## Example Configuration

### E-commerce Sporting Goods Store:

| Customer Group | Min. Spent | Benefits |
|---|---|---|
| Default Customer (ID: 1) | 0₽ | Standard prices |
| Sportsmen (ID: 2) | 50,000₽ | 5-10% better prices |
| Friend -15% (ID: 6) | 100,000₽ | 15% discount |
| VIP (ID: 7) | 250,000₽ | 20% discount + priority support |

**Configuration Steps**:
1. Create customer groups in **Customers → Customer Groups** (if not exists)
2. Set up product pricing for each group
3. Configure loyalty levels in Bonus Manager → Loyalty Levels tab
4. Enable automatic upgrades

## How It Works (Behind the Scenes)

### When a Customer Places an Order:

1. **Order Completes**: Order status changes to "Complete" (or configured accrual status)
2. **Bonuses Awarded**: Customer receives bonus points for the order
3. **Loyalty Check Triggered**: `checkAndUpgradeCustomer()` method is called
4. **Total Calculated**: System calculates customer's total spent **within current program year**
5. **Level Evaluated**: System finds the highest level the customer qualifies for
6. **Upgrade Applied**: If customer qualifies for a better group, `customer_group_id` is updated
7. **Prices Updated**: Next time customer views products, they see the new group's prices

### SQL Query (Simplified):
```sql
-- Calculate customer's total spent in current program year
SELECT SUM(total) as total_spent
FROM ocus_order
WHERE customer_id = 123
  AND order_status_id = 5  -- Complete status
  AND date_added >= '2026-01-01 00:00:00'
  AND date_added < '2027-01-01 00:00:00';
```

### Upgrade Logic:
```php
// If customer spent 75,000₽ in current year:
// - Qualifies for: Default (0₽), Sportsmen (50,000₽)
// - Does NOT qualify for: Friend-15 (100,000₽)
// - Customer upgraded to: Sportsmen (highest qualified)
```

## Important Notes

### Program Period Behavior:

1. **Annual Reset**: On January 1st (or configured start date), a new program period begins
2. **Customer Groups NOT Reset**: Customers keep their current group
3. **Spending Resets**: Spending count resets to 0₽ for the new period
4. **Potential Downgrade**: If customer doesn't maintain spending, they may be downgraded when they place their first order of the new period

**Example Scenario**:
- **2026 Period**: Customer spends 120,000₽ → Upgraded to Friend-15%
- **Jan 1, 2027**: New period starts, spending resets to 0₽
- **Customer still in**: Friend-15% group (not automatically downgraded)
- **Feb 15, 2027**: Customer places 10,000₽ order
  - Total 2027 spending: 10,000₽
  - Only qualifies for: Default Customer (0₽)
  - **Downgraded to**: Default Customer

### Preventing Downgrades:

If you want to prevent downgrades, modify line 296-297 in `admin/model/extension/module/bonus_manager.php`:

```php
// Current behavior (allows both upgrades and downgrades):
if ($target_group_id !== $current_group_id) {

// Change to (only allows upgrades):
if ($target_group_id !== $current_group_id && $target_group_id > $current_group_id) {
```

### Testing the System:

1. **Create test customer** with known customer_id
2. **Manually add orders** to database with date_added in current year
3. **Run upgrade check** (happens automatically on order completion)
4. **Check logs**: `grep "LOYALTY" /Users/max/Sites/storage/logs/error.log`
5. **Verify customer_group_id** updated in `ocus_customer` table

## Troubleshooting

### Customers Not Being Upgraded:

1. **Check if feature is enabled**: Loyalty Levels tab → Enable toggle = Yes
2. **Check order status**: Only orders with status = accrual_status_id count (default: Complete)
3. **Check date range**: Only orders within current program year count
4. **Check thresholds**: Ensure min_total_spent values are correct
5. **Check logs**:
   ```bash
   tail -f /Users/max/Sites/storage/logs/error.log | grep LOYALTY
   ```

### Information Page Not Showing:

1. **Check setting**: Loyalty Program Information Page dropdown has page selected
2. **Check page exists**: Information ID 12 should exist in `ocus_information`
3. **Check page status**: Page should be enabled (status = 1)

### Changes Not Saving:

1. **Check permissions**: Admin user must have modify permission for bonus_manager
2. **Check JavaScript errors**: Open browser console and look for errors
3. **Check form submission**: Ensure "Save" button at top is clicked, not browser back button

## Database Structure

### Settings Table (`ocus_setting`):

```sql
-- Loyalty feature toggle
INSERT INTO ocus_setting VALUES (0, 'module_bonus_manager', 'module_bonus_manager_loyalty_status', '1', 0);

-- Program period start
INSERT INTO ocus_setting VALUES (0, 'module_bonus_manager', 'module_bonus_manager_loyalty_period_start', '01-01', 0);

-- Levels (JSON)
INSERT INTO ocus_setting VALUES (0, 'module_bonus_manager', 'module_bonus_manager_loyalty_levels',
  '[{"customer_group_id":1,"min_total_spent":0},{"customer_group_id":2,"min_total_spent":50000}]', 0);

-- Info page
INSERT INTO ocus_setting VALUES (0, 'module_bonus_manager', 'module_bonus_manager_loyalty_info_id', '12', 0);
```

### Customer Table Update:

```sql
-- When customer reaches 50,000₽ threshold
UPDATE ocus_customer
SET customer_group_id = 2
WHERE customer_id = 123;
```

## Files Modified

1. **Controller**: `/admin/controller/extension/module/bonus_manager.php`
   - Added loyalty level data loading
   - Added language strings
   - Added information pages list

2. **View**: `/admin/view/template/extension/module/bonus_manager.twig`
   - Added "Loyalty Levels" tab
   - Added form fields for all loyalty settings
   - Added JavaScript for add/remove level functionality
   - Added form submission handler to serialize JSON

3. **Model**: `/admin/model/extension/module/bonus_manager.php`
   - Added `checkAndUpgradeCustomer()` method
   - Added `getCustomerTotalSpent()` method
   - Added `getLoyaltyLevels()` method
   - Added `saveLoyaltyLevels()` method

## Future Enhancements

Potential improvements for the admin UI:

1. **Bulk Customer Check**: Button to manually check and upgrade all customers
2. **Level Preview**: Show how many customers are in each level
3. **Upgrade History**: Log table showing all customer upgrades/downgrades
4. **Period End Warning**: Alert when program period is about to end
5. **Customer Simulator**: Tool to simulate what level a customer would be at with X spending
6. **Grace Period**: Setting to delay downgrades by X days after period starts
7. **Email Notifications**: Automatically email customers when they're upgraded

---

**Last Updated**: 2026-01-08
**OpenCart Version**: 3.0.3.6
**Module**: Bonus Manager with Loyalty Levels
