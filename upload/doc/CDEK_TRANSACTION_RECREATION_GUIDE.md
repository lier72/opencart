# CDEK Payment Transaction Recreation Guide

## Overview

This guide explains how to recreate or update Odoo `payment.transaction` records for CDEK COD orders using the provided utility scripts.

## When to Use These Tools

Use these scripts when you need to:
- ✅ Recreate missing payment transactions in Odoo
- ✅ Update transactions with current CDEK payment data
- ✅ Fix transactions that were created before the integration was implemented
- ✅ Bulk process multiple orders at once
- ✅ Update transactions after CDEK order status changed

## Available Scripts

### 1. Command-Line Script (Advanced)

**File:** [admin/recreate_cdek_transactions.php](admin/recreate_cdek_transactions.php:1)

**Best for:**
- Running from cron jobs
- Automated batch processing
- Scripted workflows

### 2. Interactive Script (Recommended)

**File:** [admin/recreate_cdek_transactions_simple.php](admin/recreate_cdek_transactions_simple.php:1)

**Best for:**
- Manual one-time processing
- Exploring available orders
- User-friendly interface

## Usage Instructions

### Method 1: Interactive Script (Easiest)

```bash
cd /Users/max/Sites/opencart/upload
/usr/local/opt/php@7.3/bin/php admin/recreate_cdek_transactions_simple.php
```

**Menu Options:**

```
=== CDEK Transaction Recreation Tool ===

1. List recent COD CDEK orders
2. Process specific order IDs
3. Process all delivered COD CDEK orders
4. Exit

Enter choice (1-4):
```

**Option 1: List Orders**
- Shows last 20 COD CDEK orders
- Displays: Order ID, Date, Total, CDEK Number, Status, Email, Odoo sync status
- Use this to find order IDs you want to process

**Option 2: Process Specific Orders**
- Enter comma-separated order IDs
- Example: `114320, 114321, 114322`
- Processes each order individually

**Option 3: Process All Delivered Orders**
- Automatically finds all delivered COD CDEK orders
- Shows count and asks for confirmation
- Processes in bulk

### Method 2: Command-Line Script

**Usage:**
```bash
# Single order
/usr/local/opt/php@7.3/bin/php admin/recreate_cdek_transactions.php 114320

# Multiple orders
/usr/local/opt/php@7.3/bin/php admin/recreate_cdek_transactions.php 114320 114321 114322

# Or edit the script and add order IDs to the $order_ids array
```

**Edit Script Method:**
```php
// Open admin/recreate_cdek_transactions.php
// Find line ~60 and edit:
$order_ids = array(
    114320,
    114321,
    114322,
);
```

Then run:
```bash
/usr/local/opt/php@7.3/bin/php admin/recreate_cdek_transactions.php
```

## What the Scripts Do

### Step-by-Step Process:

1. **Validates Order**
   - Checks if order exists
   - Verifies payment method is `cod_cdek`
   - Ensures CDEK dispatch exists

2. **Fetches CDEK Data**
   - Authenticates with CDEK API
   - Gets order info by CDEK tracking number
   - Extracts payment amount from `delivery_detail`

3. **Updates Odoo Transaction**
   - Calls `updateCdekPaymentTransaction()` model method
   - Creates transaction if missing
   - Updates existing transaction if found
   - Sets amount, acquirer_reference, tx_url

4. **Reports Results**
   - Shows success/failure for each order
   - Provides summary statistics

## Output Examples

### Successful Processing:

```
----------------------------------------
Processing Order #114320
----------------------------------------
Payment: cod_cdek
CDEK Dispatch: 12402cf4-09c5-4a8e-946c-4e094b41d201
CDEK Number: 10189617644
CDEK Status: DELIVERED

Fetching CDEK order data from API...
✓ CDEK data retrieved - Payment sum: 9034

Updating Odoo payment.transaction...
✓ SUCCESS: Updated payment.transaction ID: 12345 with amount: 9034.00

========================================
SUMMARY
========================================
Total orders processed: 1
✓ Successful: 1
✗ Failed: 0
⊘ Skipped: 0
========================================
```

### Error Cases:

```
✗ Order #114320 not found
⊘ Order #114321 is not cod_cdek (payment: alfabank)
✗ Order #114322 has no CDEK dispatch
✗ Failed to get CDEK order data (HTTP 404)
✗ FAILED: Partner not found in odoo_client_map for email: customer@example.com
```

## Common Issues and Solutions

### Issue 1: "Order has no CDEK dispatch"

**Problem:** Order exists but no CDEK tracking

**Solution:**
- Check if order was actually sent via CDEK
- Verify `cdek_order` table has entry for this order
- May need to create CDEK order first

### Issue 2: "Partner not found in odoo_client_map"

**Problem:** Customer not synced to Odoo

**Solution:**
```sql
-- Check if customer exists
SELECT * FROM ocus_odoo_client_map
WHERE opencart_email = LOWER(TRIM('<customer_email>'));

-- If missing, sync the order to Odoo first
-- Or manually create the mapping
```

The order must be synced to Odoo via `createOdooOrder()` before transaction can be created.

### Issue 3: "No payment_sum in CDEK delivery_detail"

**Problem:** Order not yet delivered or payment info not available

**Solution:**
- Wait until order status is DELIVERED
- CDEK only provides payment_sum after delivery
- Script will create transaction with amount = 0 if no payment_sum

### Issue 4: "Failed to authenticate with CDEK API"

**Problem:** CDEK credentials invalid

**Solution:**
```sql
-- Check CDEK credentials
SELECT `value` FROM ocus_setting
WHERE `key` = 'cdek_integrator_setting';

-- Verify account and secure_password fields
```

### Issue 5: "No active payment acquirer mapping found for cod_cdek"

**Problem:** Odoo payment acquirer not configured

**Solution:**
```sql
-- Add mapping
INSERT INTO ocus_odoo_payment_acquirer_map
(opencart_payment_code, opencart_payment_name, odoo_acquirer_id, odoo_acquirer_name, is_active)
VALUES
('cod_cdek', 'Оплата при получении (СДЭК)', <your_odoo_acquirer_id>, 'Cash on Delivery', 1);
```

## Verification

### After Running Script:

1. **Check Script Output**
   - Look for "✓ SUCCESS" messages
   - Note any errors or warnings

2. **Verify in Database**
   ```sql
   -- Check if orders are in Odoo map
   SELECT o.order_id, o.email, oom.odoo_order_id
   FROM ocus_order o
   LEFT JOIN ocus_odoo_order_map oom ON o.order_id = oom.opencart_order_id
   WHERE o.order_id IN (114320, 114321, 114322);

   -- Check customer mapping
   SELECT o.order_id, o.email, ocm.odoo_client_id
   FROM ocus_order o
   LEFT JOIN ocus_odoo_client_map ocm ON LOWER(TRIM(o.email)) = ocm.opencart_email
   WHERE o.order_id IN (114320, 114321, 114322);
   ```

3. **Verify in Odoo**
   - Open: Accounting → Payments → Transactions
   - Search: `OC-114320` (replace with your order ID)
   - Check:
     - Amount matches CDEK payment_sum
     - Acquirer Reference = CDEK number
     - Transaction URL = CDEK UUID

## Bulk Processing Tips

### Process All Delivered Orders:

```bash
# Use interactive script option 3
/usr/local/opt/php@7.3/bin/php admin/recreate_cdek_transactions_simple.php
# Choose option 3
```

### Process Orders by Date Range:

Edit script to add WHERE clause:
```sql
-- In recreate_cdek_transactions_simple.php
-- Modify the query around line 200:
WHERE o.payment_code = 'cod_cdek'
AND co.status_id = 'DELIVERED'
AND o.date_added >= '2025-01-01'
AND o.date_added <= '2025-12-31'
```

### Process Orders Without Transactions:

```sql
-- Find orders missing transactions in Odoo
SELECT o.order_id
FROM ocus_order o
INNER JOIN ocus_cdek_order co ON o.order_id = co.order_id
WHERE o.payment_code = 'cod_cdek'
AND co.status_id = 'DELIVERED'
AND NOT EXISTS (
    -- This assumes you track created transactions
    -- Adjust based on your setup
    SELECT 1 FROM ocus_odoo_order_map oom
    WHERE oom.opencart_order_id = o.order_id
);
```

## Performance Considerations

### Processing Speed:

- **CDEK API:** ~1-2 seconds per order
- **Odoo API:** ~1-2 seconds per transaction
- **Total:** ~2-4 seconds per order

**Recommendation:**
- Process in batches of 50-100 orders
- Avoid running during peak hours
- Monitor API rate limits

### Error Handling:

Scripts automatically:
- ✓ Skip non-COD orders
- ✓ Skip orders without CDEK data
- ✓ Continue on individual failures
- ✓ Provide summary statistics

## Automation

### Set Up Cron Job:

To automatically process delivered orders daily:

```bash
# Add to crontab
0 3 * * * /usr/local/opt/php@7.3/bin/php /path/to/opencart/admin/recreate_cdek_transactions.php >> /path/to/logs/cdek_transactions.log 2>&1
```

Edit script to automatically get delivered orders:
```php
// In recreate_cdek_transactions.php, replace $order_ids array with:
$query = $db->query(
    "SELECT order_id FROM " . DB_PREFIX . "order o
    INNER JOIN " . DB_PREFIX . "cdek_order co ON o.order_id = co.order_id
    WHERE o.payment_code = 'cod_cdek'
    AND co.status_id = 'DELIVERED'
    AND co.cdek_number IS NOT NULL"
);
$order_ids = array_column($query->rows, 'order_id');
```

## Safety Features

Both scripts include:
- ✅ Read-only by default (creates/updates only)
- ✅ No order deletion
- ✅ No payment state changes
- ✅ Detailed logging
- ✅ Error reporting
- ✅ Dry-run compatible

## Logging

### Script Output:

All operations logged to console with:
- ✓ Success indicators
- ✗ Error indicators
- ⊘ Skip indicators
- Detailed messages

### OpenCart Error Log:

Model operations logged to:
```
storage/logs/error.log
```

Search for:
```
Model odoo_connector updateCdekPaymentTransaction
```

## Best Practices

1. **Always test first**
   - Run on 1-2 orders before bulk processing
   - Verify results in Odoo

2. **Check prerequisites**
   - Ensure order synced to Odoo
   - Verify customer in `odoo_client_map`
   - Confirm payment acquirer mapping exists

3. **Monitor results**
   - Review success/failure counts
   - Check error messages
   - Verify in Odoo after completion

4. **Keep logs**
   - Save script output for records
   - Review error.log for issues

## Support

If you encounter issues:

1. Check prerequisites in [ODOO_CDEK_PAYMENT_TRANSACTION_README.md](ODOO_CDEK_PAYMENT_TRANSACTION_README.md:1)
2. Review error messages in script output
3. Check OpenCart error.log
4. Verify Odoo connection is working
5. Ensure CDEK API credentials are valid

## Files

- **[admin/recreate_cdek_transactions.php](admin/recreate_cdek_transactions.php:1)** - Command-line script
- **[admin/recreate_cdek_transactions_simple.php](admin/recreate_cdek_transactions_simple.php:1)** - Interactive script
- **[CDEK_TRANSACTION_RECREATION_GUIDE.md](CDEK_TRANSACTION_RECREATION_GUIDE.md:1)** - This guide

## Version History

- **2025-12-18** - Initial creation
  - Command-line script for batch processing
  - Interactive script with menu system
  - Comprehensive documentation
