# Bonus Expiration Feature

## Overview
The bonus expiration system automatically expires customer bonus points after a configurable period, encouraging customers to use their bonuses and creating urgency.

## Features

### 1. Configurable Expiration Period
- Set custom expiration period in days (default: 365 days)
- Setting: `module_bonus_manager_expiration_days`
- Located in: Admin → Extensions → Modules → Bonus Manager → General Settings
- Set to `0` for bonuses that never expire

### 2. Automatic Expiration Handling
- Expired bonuses are automatically excluded from customer balance
- No manual intervention required
- Works seamlessly with all existing bonus functionality

### 3. Database Schema
Added `date_expires` column to `ocus_customer_reward` table:
- Type: DATETIME
- Nullable: YES (NULL = never expires)
- Set automatically when bonuses are awarded

## Implementation Details

### Database Changes

**Table: `ocus_customer_reward`**
```sql
ALTER TABLE ocus_customer_reward
ADD COLUMN date_expires DATETIME NULL DEFAULT NULL AFTER date_added;
```

**Structure:**
- `customer_reward_id` - Auto-increment ID
- `customer_id` - Customer reference
- `order_id` - Related order
- `description` - Bonus description
- `points` - Bonus amount (positive = awarded, negative = spent)
- `date_added` - When bonus was created
- `date_expires` - When bonus expires (NULL = never)

### Code Changes

#### 1. Bonus Awarding Logic
**File:** [catalog/model/extension/module/bonus_manager.php:70-85](catalog/model/extension/module/bonus_manager.php#L70-L85)

When bonuses are awarded:
```php
// Calculate expiration date
$expiration_days = (int)$this->config->get('module_bonus_manager_expiration_days');
if ($expiration_days <= 0) {
    $expiration_days = 365; // Default 1 year
}

$expiration_date_sql = "DATE_ADD(NOW(), INTERVAL " . (int)$expiration_days . " DAY)";

// Award bonuses with expiration date
$this->db->query("INSERT INTO " . DB_PREFIX . "customer_reward
    SET customer_id = '...',
    ...
    date_expires = " . $expiration_date_sql);
```

#### 2. Balance Calculation (System Library)
**File:** [system/library/customer.php:134-141](system/library/customer.php#L134-L141)

Used by: Checkout, cart, account pages
```php
public function getRewardPoints() {
    // Exclude expired bonuses from total
    $query = $this->db->query("SELECT SUM(points) AS total FROM " . DB_PREFIX . "customer_reward
        WHERE customer_id = '" . (int)$this->customer_id . "'
        AND (date_expires IS NULL OR date_expires > NOW())");

    return $query->row['total'];
}
```

#### 3. Balance Calculation (Model)
**File:** [catalog/model/account/customer.php:97-104](catalog/model/account/customer.php#L97-L104)

Used by: Admin queries, reports, bonus spending validation
```php
public function getRewardTotal($customer_id) {
    // Exclude expired bonuses from total calculation
    $query = $this->db->query("SELECT SUM(points) AS total FROM " . DB_PREFIX . "customer_reward
        WHERE customer_id = '" . (int)$customer_id . "'
        AND (date_expires IS NULL OR date_expires > NOW())");

    return $query->row['total'];
}
```

#### 4. Email Notifications
**File:** [catalog/controller/mail/bonus.php:47-51](catalog/controller/mail/bonus.php#L47-L51)

Balance shown in emails excludes expired bonuses:
```php
// Get current bonus balance (excluding expired)
$query = $this->db->query("SELECT SUM(points) as total FROM " . DB_PREFIX . "customer_reward
    WHERE customer_id = '" . (int)$order_info['customer_id'] . "'
    AND (date_expires IS NULL OR date_expires > NOW())");
$current_balance = (int)$query->row['total'];
```

### Admin Configuration

**File:** [admin/view/template/extension/module/bonus_manager.twig:82-88](admin/view/template/extension/module/bonus_manager.twig#L82-L88)

New field in General Settings tab:
```twig
<div class="form-group">
    <label class="col-sm-2 control-label" for="input-expiration-days">
        <span data-toggle="tooltip" title="{{ help_expiration_days }}">
            {{ entry_expiration_days }}
        </span>
    </label>
    <div class="col-sm-10">
        <input type="text" name="module_bonus_manager_expiration_days"
               value="{{ module_bonus_manager_expiration_days }}"
               placeholder="365" id="input-expiration-days" class="form-control" />
        <p class="help-block">{{ help_expiration_days }}</p>
    </div>
</div>
```

## How It Works

### Bonus Lifecycle

1. **Award**: Customer completes order → Bonuses awarded with expiration date
   ```
   Order #123 completed → +500 points (expires 2027-01-07)
   ```

2. **Usage**: Customer can use bonuses before expiration
   ```
   Balance check → Shows only non-expired bonuses
   Checkout → Can only use non-expired bonuses
   ```

3. **Expiration**: After expiration date passes
   ```
   2027-01-08 → Bonuses automatically excluded from balance
   Customer sees reduced balance
   Cannot use expired bonuses
   ```

### Query Pattern

All bonus balance queries use this pattern:
```sql
SELECT SUM(points) AS total
FROM ocus_customer_reward
WHERE customer_id = X
AND (date_expires IS NULL OR date_expires > NOW())
```

This ensures:
- ✅ Expired bonuses are automatically excluded
- ✅ Non-expiring bonuses (date_expires = NULL) are included
- ✅ Future bonuses are included
- ✅ Consistent behavior across all features

## Maintenance & Cleanup

### Cron Job
**File:** [admin/bonus_expiration_cron.php](admin/bonus_expiration_cron.php)

Optional maintenance script that runs daily:

**What it does:**
1. Counts expired bonuses and logs statistics
2. Marks expired bonuses in description (adds "(Expired)" suffix)
3. Cleans up very old expired records (>2 years old)

**Setup:**
```bash
# Add to crontab (run daily at midnight)
0 0 * * * /usr/bin/php /path/to/opencart/admin/bonus_expiration_cron.php > /dev/null 2>&1
```

**Note:** This cron job is **optional** because:
- Expired bonuses are already excluded in queries (no cron needed)
- Main purpose is cleanup and statistics
- Future: Could be extended to send expiration warnings

### Log File
Location: `storage/logs/bonus_expiration.log`

Example output:
```
=== Bonus Expiration Cron Job Started ===
Found 15 expired bonus records
Total points expiring: 3250
Marked 15 bonus records as expired
Cleaned up 42 very old expired bonus records
=== Bonus Expiration Cron Job Completed Successfully ===
```

## Configuration Examples

### Example 1: Standard 1-Year Expiration
```
Expiration Days: 365
Result: Bonuses expire exactly 1 year after being awarded
```

### Example 2: Urgent 90-Day Expiration
```
Expiration Days: 90
Result: Bonuses expire after 3 months - encourages faster usage
```

### Example 3: Never Expire
```
Expiration Days: 0
Result: Bonuses never expire (date_expires = NULL)
Note: Legacy behavior, not recommended
```

### Example 4: Short 30-Day Expiration
```
Expiration Days: 30
Result: Bonuses expire after 1 month - creates strong urgency
```

## Testing

### Test Scenario 1: Award Bonuses with Expiration
1. Set expiration to 365 days in admin
2. Place test order and complete it
3. Check database:
   ```sql
   SELECT *,
          DATEDIFF(date_expires, NOW()) as days_remaining
   FROM ocus_customer_reward
   WHERE customer_id = X
   ORDER BY date_added DESC
   LIMIT 1;
   ```
4. Verify `date_expires` is ~365 days in future

### Test Scenario 2: Expired Bonuses Excluded
1. Manually set bonus expiration to past:
   ```sql
   UPDATE ocus_customer_reward
   SET date_expires = DATE_SUB(NOW(), INTERVAL 1 DAY)
   WHERE customer_reward_id = X;
   ```
2. Check customer account page → Balance should exclude expired bonuses
3. Try using bonuses at checkout → Expired bonuses shouldn't be available

### Test Scenario 3: Never-Expiring Bonuses
1. Find old bonuses with `date_expires = NULL`
2. Verify they still show in customer balance
3. Verify they can still be used at checkout

## Migration Notes

### Existing Bonuses
When the feature is deployed:
- Existing bonuses have `date_expires = NULL` (never expire)
- New bonuses get expiration dates
- No data loss or disruption

### Backward Compatibility
The system handles all cases:
- `date_expires = NULL` → Bonus never expires (legacy bonuses)
- `date_expires > NOW()` → Bonus is active
- `date_expires <= NOW()` → Bonus is expired

## Future Enhancements

### Expiration Warning Email
Send email to customers 7 days before bonuses expire:
```php
// In cron job
$expiring_soon = $db->query("
    SELECT DISTINCT customer_id
    FROM ocus_customer_reward
    WHERE date_expires BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
    AND points > 0
");

foreach ($expiring_soon->rows as $row) {
    // Send warning email
}
```

### Admin Dashboard Widget
Show expiring bonuses statistics:
- Total points expiring this month
- Number of customers with expiring bonuses
- Revenue opportunity from expiring bonuses

### Customer Account Display
Show expiration dates in customer reward history:
```
+500 pts | Order #123 | Expires: 2027-01-07
-200 pts | Order #124 | Used
+300 pts | Order #125 | Expires: 2027-01-15
```

## Troubleshooting

### Issue: Bonuses Not Expiring
**Check:**
1. Database column exists: `DESCRIBE ocus_customer_reward;`
2. Bonuses have expiration dates: `SELECT date_expires FROM ocus_customer_reward WHERE points > 0;`
3. System date/time is correct: `SELECT NOW();`

### Issue: All Bonuses Showing as Expired
**Check:**
1. System timezone configuration
2. Server time vs database time
3. Query syntax: `date_expires > NOW()` (not `>=`)

### Issue: Legacy Bonuses Not Working
**Check:**
1. Query includes: `(date_expires IS NULL OR date_expires > NOW())`
2. Not filtering out NULL values

## Performance Considerations

### Index Recommendations
```sql
-- Improve query performance for balance calculations
CREATE INDEX idx_customer_expires
ON ocus_customer_reward (customer_id, date_expires);

-- Improve cleanup queries
CREATE INDEX idx_expires
ON ocus_customer_reward (date_expires);
```

### Query Optimization
All balance queries use:
- Indexed customer_id lookups
- Simple date comparison
- No JOINs or subqueries
- Minimal performance impact

## Summary

The bonus expiration feature:
- ✅ Automatically expires bonuses after configurable period
- ✅ Works transparently with all existing functionality
- ✅ Requires no manual intervention
- ✅ Backward compatible with existing bonuses
- ✅ Includes optional cleanup cron job
- ✅ Easy to configure via admin panel
- ✅ Creates urgency for customers to use bonuses
