# Odoo Payment Transaction Update from CDEK

## Overview

This implementation automatically updates Odoo `payment.transaction` records with actual payment data from CDEK when COD (Cash on Delivery) orders are accepted or delivered. This solves the problem of empty payment transactions that are created initially for deferred payment methods like `cod_cdek`.

## Problem Statement

When an order with `cod_cdek` payment is created in OpenCart and synced to Odoo:
1. A `payment.transaction` record is created in Odoo with `amount = 0` and `state = pending`
2. The actual payment amount is unknown until CDEK processes the delivery
3. The transaction remains empty until manually updated

## Solution

The system now automatically:
1. Monitors CDEK order status changes via cron
2. When status changes to `ACCEPTED` or `DELIVERED` and payment data is available
3. Updates or creates the Odoo `payment.transaction` with actual payment amount
4. Stores CDEK tracking information in the transaction record

## What Was Implemented

### 1. New Model Method: `updateCdekPaymentTransaction()`

**Location:** [admin/model/extension/module/odoo_connector.php:2226](admin/model/extension/module/odoo_connector.php#L2226)

**Features:**
- ✓ Uses existing verified methods (`checkClient`, `getConfig`, `getCurrencyMapping`)
- ✓ Verifies payment method is `cod_cdek`
- ✓ Extracts payment amount from CDEK `delivery_detail->payment_sum`
- ✓ Searches for existing transaction by reference `OC-<order_id>`
- ✓ Updates existing transaction OR creates new one if missing
- ✓ **Does NOT manage transaction state** - leaves payment workflow to Odoo
- ✓ Stores CDEK UUID in `tx_url` field
- ✓ Stores CDEK number in `acquirer_reference` field
- ✓ Uses `checkClient()` to get partner_id from `odoo_client_map`
- ✓ Prevents unnecessary updates (checks if data changed)

### 2. Controller Method: `updateOdooPaymentTransaction()`

**Location:** [admin/controller/extension/module/cdek_integrator.php:4032](admin/controller/extension/module/cdek_integrator.php#L4032)

**Features:**
- ✓ Checks if Odoo connector is installed
- ✓ Calls model method with CDEK data
- ✓ Logs success/failure to error log
- ✓ Displays cron output for monitoring

### 3. CDEK Cron Integration

**Location:** [admin/controller/extension/module/cdek_integrator.php:4008](admin/controller/extension/module/cdek_integrator.php#L4008)

**Triggers on:**
- Status changes to `ACCEPTED`
- Status changes to `DELIVERED`

**Only when status has changed** (not on every cron run if status is same)

## Payment Transaction Fields

### Fields Updated/Created:

| Odoo Field | Source | Description |
|------------|--------|-------------|
| `reference` | `OC-<order_id>` | OpenCart order reference |
| `amount` | `delivery_detail->payment_sum` | Actual amount paid by customer |
| `acquirer_reference` | `cdek_number` | CDEK tracking number (e.g., "10189617644") |
| `tx_url` | `dispatch_number` (UUID) | CDEK order UUID for API queries |
| `currency_id` | Order currency | From `getCurrencyMapping()` method |
| `partner_id` | Customer email | From `checkClient()` via `odoo_client_map` |
| `acquirer_id` | Mapping table | From `getPaymentAcquirerMapping('cod_cdek')` |

**Note:** `state` field is **NOT** managed by this integration - Odoo handles payment workflow states internally.

### Example Transaction Data:

**Before CDEK update:**
```python
{
    'reference': 'OC-114320',
    'amount': 0.0,
    'state': 'pending',  # Managed by Odoo
    'acquirer_reference': False,
    'tx_url': False,
}
```

**After CDEK update (DELIVERED):**
```python
{
    'reference': 'OC-114320',
    'amount': 9034.0,
    'state': 'pending',  # Still managed by Odoo - NOT changed from OpenCart
    'acquirer_reference': '10189617644',
    'tx_url': '12402cf4-09c5-4a8e-946c-4e094b41d201',
}
```

**Note:** The `state` field remains as set by Odoo's payment workflow. OpenCart only updates the amount and CDEK reference data.

## How It Works

### Workflow:

1. **Order Created** → Odoo transaction created with `amount = 0`, `state = pending`

2. **CDEK Cron Runs** → Checks order statuses via CDEK API

3. **Status Changes to ACCEPTED or DELIVERED**:
   - Extracts `payment_sum` from CDEK response
   - Calls `updateOdooPaymentTransaction()`
   - Searches Odoo for transaction with reference `OC-<order_id>`

4. **Transaction Found**:
   - Compares current amount with new payment_sum
   - Updates if changed: amount, acquirer_reference, tx_url
   - Does NOT update state (managed by Odoo)

5. **Transaction Not Found**:
   - Uses `checkClient(email)` to get partner_id from `odoo_client_map`
   - Creates new transaction with amount and CDEK references
   - Does NOT set state (uses Odoo default)

6. **Payment Workflow**:
   - OpenCart provides transaction data only
   - Odoo manages payment states internally
   - No state transitions triggered from OpenCart side

### Update Logic:

The system only updates when:
- Amount has changed
- CDEK reference fields (acquirer_reference, tx_url) are empty and now available

This prevents unnecessary API calls to Odoo.

**Important:** The system does NOT update the `state` field - this is managed by Odoo's payment workflow.

## Database Requirements

### OpenCart Tables Used:

```sql
-- Order must be in Odoo map
SELECT * FROM ocus_odoo_order_map
WHERE opencart_order_id = ?

-- Order payment method must be cod_cdek
SELECT payment_code FROM ocus_order
WHERE order_id = ? AND payment_code = 'cod_cdek'

-- Payment acquirer mapping must exist
SELECT odoo_acquirer_id FROM ocus_odoo_payment_acquirer_map
WHERE opencart_payment_code = 'cod_cdek' AND is_active = 1
```

### Odoo Models Accessed:

```python
# Search/update existing transaction
payment.transaction.search_read([('reference', '=', 'OC-114320')])
payment.transaction.write([tx_id], {...})

# Create new transaction if not found
payment.transaction.create({...})

# Get partner from order
sale.order.read([odoo_order_id], {'fields': ['partner_id']})
```

## Configuration

### Prerequisites:

1. **Odoo connector must be installed** and configured
2. **Customer must be in `odoo_client_map`** - the system uses `checkClient(email)` to get partner_id
3. **Payment acquirer mapping** for `cod_cdek` must exist:
   ```sql
   INSERT INTO ocus_odoo_payment_acquirer_map
   (opencart_payment_code, opencart_payment_name, odoo_acquirer_id, odoo_acquirer_name, is_active)
   VALUES
   ('cod_cdek', 'Оплата при получении (СДЭК)', <odoo_acquirer_id>, 'Cash on Delivery', 1);
   ```
4. **Currency mapping** must be configured via `getCurrencyMapping()`
5. **CDEK orders** must have `cod_cdek` as payment_code

### Checking Configuration:

```bash
# Check if payment acquirer mapping exists
mysql> SELECT * FROM ocus_odoo_payment_acquirer_map WHERE opencart_payment_code = 'cod_cdek';

# Check if customer is mapped (required for partner_id lookup)
mysql> SELECT o.order_id, o.email, ocm.odoo_client_id
       FROM ocus_order o
       LEFT JOIN ocus_odoo_client_map ocm ON LOWER(TRIM(o.email)) = ocm.opencart_email
       WHERE o.payment_code = 'cod_cdek';

# Check currency mapping
mysql> SELECT * FROM ocus_odoo_currency_map WHERE opencart_currency_code = 'RUB';
```

## Logging

### Success Messages:

```
Odoo: Updated payment.transaction ID: 12345 with amount: 9034.00
Odoo: Created payment.transaction ID: 12346 with amount: 8500.00
Odoo: Transaction ID: 12345 already up to date (amount: 9034.00)
```

### Error Messages (logged to error.log):

```
CDEK CRON Odoo Warning: Failed to connect to Odoo: Connection timeout
CDEK CRON Odoo Warning: Error updating payment.transaction: Invalid acquirer_id
Model odoo_connector updateCdekPaymentTransaction: Order #123 not found in Odoo order map
```

### Silent Skips (not logged):

- Order not found in Odoo map (expected for non-synced orders)
- Payment method is not cod_cdek (expected for other payment types)
- Odoo connector not installed

## Testing

### Manual Test:

```bash
# Run CDEK cron
/usr/local/opt/php@7.3/bin/php admin/cdek_integrator_cron.php

# Check output for Odoo messages
# Expected: "Odoo: Updated payment.transaction ID: XXX..."
```

### Verify in Odoo:

1. Open Odoo → Accounting → Payments → Transactions
2. Search for reference: `OC-<order_id>`
3. Verify:
   - Amount matches CDEK payment_sum
   - State is managed by Odoo (not changed from OpenCart)
   - Acquirer Reference = CDEK number
   - Transaction URL = CDEK UUID

### Test Scenarios:

**Scenario 1: New Transaction**
- Order with cod_cdek, synced to Odoo
- No transaction exists yet
- CDEK status → DELIVERED with payment_sum
- **Expected:** New transaction created with all fields

**Scenario 2: Update Existing**
- Transaction exists with amount = 0
- CDEK status → DELIVERED with payment_sum = 9034
- **Expected:** Transaction updated with new amount (state unchanged)

**Scenario 3: Already Up-to-Date**
- Transaction exists with correct amount
- Cron runs again
- **Expected:** No update, message "already up to date"

**Scenario 4: Non-COD Order**
- Order with alfabank payment
- **Expected:** Silently skipped (not logged)

## Troubleshooting

### Transaction not updating:

**Check:**
1. **Customer email is in `odoo_client_map`** - system uses `checkClient(email)`
2. Payment method is `cod_cdek`
3. CDEK response has `delivery_detail->payment_sum`
4. Payment acquirer mapping exists and is active
5. Currency mapping exists for order currency
6. Odoo connection is working (`getConfig()` succeeds)
7. CDEK status is ACCEPTED or DELIVERED
8. Status actually changed (not same as previous)

### Error: "No active payment acquirer mapping":

**Solution:**
```sql
INSERT INTO ocus_odoo_payment_acquirer_map
(opencart_payment_code, opencart_payment_name, odoo_acquirer_id, odoo_acquirer_name, is_active)
VALUES
('cod_cdek', 'Оплата при получении (СДЭК)', <your_odoo_acquirer_id>, 'Cash on Delivery', 1);
```

Get `<your_odoo_acquirer_id>` from Odoo:
```python
# In Odoo shell
self.env['payment.acquirer'].search([('name', 'ilike', 'cash')])
```

### Error: "Partner not found in odoo_client_map":

This means the customer's email is not in the `odoo_client_map` table.

**Solution:**
1. Ensure order was synced to Odoo via `createOdooOrder()` first
2. Check if customer email matches:
   ```sql
   SELECT * FROM ocus_odoo_client_map
   WHERE opencart_email = LOWER(TRIM('<customer_email>'));
   ```
3. If missing, re-sync the order or manually create the mapping

## Files Modified/Created

### Modified:

1. **[admin/model/extension/module/odoo_connector.php](admin/model/extension/module/odoo_connector.php:2227)**
   - Added `updateCdekPaymentTransaction()` method (~178 lines)
   - Uses existing verified methods: `checkClient()`, `getConfig()`, `getCurrencyMapping()`, `getPaymentAcquirerMapping()`
   - Does NOT manage payment.transaction state - leaves to Odoo

2. **[admin/controller/extension/module/cdek_integrator.php](admin/controller/extension/module/cdek_integrator.php:4032)**
   - Added `updateOdooPaymentTransaction()` controller method (~30 lines)
   - Added call in `cron()` method when status changes to ACCEPTED/DELIVERED

### Created:

- **[ODOO_CDEK_PAYMENT_TRANSACTION_README.md](ODOO_CDEK_PAYMENT_TRANSACTION_README.md:1)** - This documentation

## Integration Points

This feature integrates with:

1. **CDEK Cron** - Monitors status changes
2. **Odoo Connector** - Updates payment transactions
3. **COD Payment Status** - Works alongside the COD payment info feature

All three systems work together:
- CDEK Cron → Updates order status
- Odoo Transaction Update → Syncs payment to Odoo (this feature)
- COD Payment Status → Adds detailed comment to order history

## Future Enhancements

Potential improvements:

1. **Reconcile payments** - Automatically reconcile payment.transaction with invoice
2. **Register payment** - Create account.payment when transaction is done
3. **Chatter notification** - Post message to sale.order when payment received
4. **Partial payments** - Handle cases where payment_sum differs from order total
5. **Refunds** - Update transaction when CDEK processes refund
6. **Webhook support** - Real-time updates instead of polling via cron

## Version History

- **2025-12-18** - Initial implementation
  - Created updateCdekPaymentTransaction model method
  - Uses existing verified methods (checkClient, getConfig, etc.)
  - Does NOT manage transaction state - leaves to Odoo
  - Integrated into CDEK cron
  - Supports ACCEPTED and DELIVERED statuses
  - Stores CDEK UUID and tracking number

## Support

For issues:
- Check OpenCart error log: `storage/logs/error.log`
- Enable Odoo debug mode in `odoo_connector.php`: `$debug = True`
- Check CDEK API response format
- Verify Odoo XML-RPC is accessible
