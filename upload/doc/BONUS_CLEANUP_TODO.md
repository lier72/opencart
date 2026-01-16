# Bonus History Cleanup Strategy

## Problem
The `ocus_customer_reward` table grows indefinitely because we cannot delete old records without breaking the balance calculation. Deleting old awarded points (+) while keeping spent points (-) would result in negative balances.

## Current Situation
- Records are never deleted (after fixing the bug)
- Expired bonuses are marked with "(Expired)" in description
- Queries exclude expired bonuses using `date_expires > NOW()`
- Balance integrity is maintained but table size grows forever

## Proposed Solution: Consolidation Strategy

Instead of deleting old records, **consolidate** them into a single balance-preserving record.

### Implementation Approach

For each customer, periodically (e.g., older than 2 years):

1. **Calculate net balance** of old transactions:
   ```sql
   -- Get sum of all old transactions (both + and -)
   SELECT SUM(points) as net_balance
   FROM ocus_customer_reward
   WHERE customer_id = X
   AND date_added < DATE_SUB(NOW(), INTERVAL 730 DAY)
   ```

2. **Delete old records**:
   ```sql
   DELETE FROM ocus_customer_reward
   WHERE customer_id = X
   AND date_added < DATE_SUB(NOW(), INTERVAL 730 DAY)
   ```

3. **Insert consolidation record** (if net balance != 0):
   ```sql
   INSERT INTO ocus_customer_reward
   SET customer_id = X,
       order_id = 0,
       description = 'Consolidated balance (older than 2 years)',
       points = [net_balance],
       date_added = DATE_SUB(NOW(), INTERVAL 730 DAY),
       date_expires = NULL
   ```

### Example Scenario

**Before consolidation:**
```
customer_id=123:
+1000 points (Dec 2022, expired Dec 2023)
 -500 points (May 2023, spent)
 -500 points (June 2023, spent)
+2000 points (Jan 2024, expires Jan 2025)
 -300 points (March 2024, spent)
+1500 points (Dec 2024, expires Dec 2025)
----
Net balance = 1000 - 500 - 500 + 2000 - 300 + 1500 = 3700
Active balance (excluding expired) = 2000 - 300 + 1500 = 3200
```

**After consolidation (assuming cutoff: Jan 2024):**
```
customer_id=123:
    0 points (Consolidated: +1000 -500 -500 = 0) [CONSOLIDATED]
+2000 points (Jan 2024, expires Jan 2025)
 -300 points (March 2024, spent)
+1500 points (Dec 2024, expires Dec 2025)
----
Net balance = 0 + 2000 - 300 + 1500 = 3200 ✅ (correct!)
Active balance = 2000 - 300 + 1500 = 3200 ✅ (correct!)
```

**If net balance was non-zero:**
```
Before: +1000 -300 -400 = +300 (3 rows)
After: +300 [CONSOLIDATED] (1 row)
```

### Benefits

✅ **Balance integrity preserved** - Net balance remains exactly the same
✅ **Table size reduced** - Old transactions consolidated into single record
✅ **Audit trail maintained** - Consolidation record shows what happened
✅ **No negative balances** - Net balance calculation includes both + and -
✅ **Safe to run repeatedly** - Idempotent operation

### Implementation in Cron Job

```php
// Add to bonus_expiration_cron.php

// Consolidate old transactions (older than 2 years)
$consolidation_date = DATE_SUB(NOW(), INTERVAL 730 DAY);

// Get customers with old transactions
$customers_query = $db->query("
    SELECT DISTINCT customer_id
    FROM " . DB_PREFIX . "customer_reward
    WHERE date_added < DATE_SUB(NOW(), INTERVAL 730 DAY)
");

$consolidated_customers = 0;
$rows_deleted = 0;

foreach ($customers_query->rows as $customer_row) {
    $customer_id = $customer_row['customer_id'];

    // Calculate net balance of old transactions
    $balance_query = $db->query("
        SELECT SUM(points) as net_balance, COUNT(*) as row_count
        FROM " . DB_PREFIX . "customer_reward
        WHERE customer_id = '" . (int)$customer_id . "'
        AND date_added < DATE_SUB(NOW(), INTERVAL 730 DAY)
    ");

    $net_balance = (float)$balance_query->row['net_balance'];
    $row_count = (int)$balance_query->row['row_count'];

    if ($row_count > 0) {
        // Delete old records
        $db->query("
            DELETE FROM " . DB_PREFIX . "customer_reward
            WHERE customer_id = '" . (int)$customer_id . "'
            AND date_added < DATE_SUB(NOW(), INTERVAL 730 DAY)
        ");

        // Insert consolidation record (even if net_balance = 0 for audit)
        $db->query("
            INSERT INTO " . DB_PREFIX . "customer_reward
            SET customer_id = '" . (int)$customer_id . "',
                order_id = 0,
                description = 'Consolidated " . (int)$row_count . " transactions (older than 2 years)',
                points = '" . (float)$net_balance . "',
                date_added = DATE_SUB(NOW(), INTERVAL 730 DAY),
                date_expires = NULL
        ");

        $consolidated_customers++;
        $rows_deleted += $row_count;

        $log->write("Consolidated " . $row_count . " transactions for customer " . $customer_id . " (net: " . $net_balance . ")");
    }
}

if ($consolidated_customers > 0) {
    $log->write("Consolidated " . $rows_deleted . " old transactions for " . $consolidated_customers . " customers");
}
```

### Configuration Option

Add admin setting:
- `module_bonus_manager_consolidation_enabled` (Enable/Disable)
- `module_bonus_manager_consolidation_days` (Default: 730 = 2 years)

### Testing Strategy

1. **Test with zero net balance:**
   - +1000, -500, -500 = 0
   - Should create consolidation record with 0 points

2. **Test with positive net balance:**
   - +1000, -300 = +700
   - Should create consolidation record with +700 points

3. **Test with negative net balance:**
   - +500, -600 = -100
   - Should create consolidation record with -100 points
   - (This shouldn't happen in practice, but must handle correctly)

4. **Verify balance before/after:**
   ```sql
   -- Before consolidation
   SELECT SUM(points) FROM ocus_customer_reward WHERE customer_id = X;

   -- Run consolidation

   -- After consolidation
   SELECT SUM(points) FROM ocus_customer_reward WHERE customer_id = X;

   -- Must be identical!
   ```

### Edge Cases

1. **Customer with only old records:**
   - All records consolidated into one
   - Net balance preserved

2. **Customer with no recent activity:**
   - Old records still consolidated
   - Shows last activity date

3. **Already consolidated records:**
   - Skip records with description "Consolidated"
   - Or allow re-consolidation of old consolidation records

4. **Spent points records (negative):**
   - Included in net balance calculation
   - This is why consolidation works vs deletion

### Database Considerations

**Before consolidation (100K customers, 5 years):**
- ~10M rows (100K customers × 20 transactions/year × 5 years)
- Table size: ~500MB

**After consolidation (2 year cutoff):**
- ~4M rows (100K × 20 × 2) + 100K consolidated
- Table size: ~200MB
- **60% reduction**

### Future Enhancements

1. **Admin Report:**
   - Show consolidation statistics
   - "X rows consolidated, Y MB saved"

2. **Per-Customer Consolidation:**
   - Allow manual consolidation for specific customer
   - Useful for testing or customer requests

3. **Adjustable Thresholds:**
   - Consolidate only if >50 old transactions
   - Skip if net balance is 0 and row_count < 10

4. **Archive Instead of Delete:**
   - Move to `ocus_customer_reward_archive` table
   - Keep for legal/audit purposes

## Priority: Medium

This is not urgent since the table won't grow dangerously fast, but should be implemented before going to production with expiration feature.

## Estimated Time: 2-3 hours

- 1 hour: Core consolidation logic
- 1 hour: Admin settings and configuration
- 1 hour: Testing and verification
