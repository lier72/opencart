# Odoo Order Status Cron Job - Documentation

## Overview

The Odoo Order Status Cron system monitors OpenCart orders and their synchronization with Odoo ERP, identifying issues that need administrative attention and sending automated email notifications.

## Files Created/Modified

1. **Model Method** - [admin/model/extension/module/odoo_connector.php](admin/model/extension/module/odoo_connector.php)
   - Added `cronCheckOrderStatuses()` - Main cron method
   - Added `sendOrderStatusNotificationEmail()` - Email notification helper
   - Added `buildEmailMessage()` - HTML email builder

2. **Cron Wrapper** - [admin/odoo_order_status_cron.php](admin/odoo_order_status_cron.php)
   - Standalone script for cron execution
   - Can be run manually or via crontab

## What the Cron Does

The cron job performs the following checks:

### 1. Sync Order Statuses
Calls the existing `syncOpenCartOrderState()` method to update all order statuses between OpenCart and Odoo.

### 2. Identify Orders NOT Created in Odoo
Finds OpenCart orders that don't exist in the `odoo_order_map` table:
- Excludes cancelled orders (status 0, 9, 13)
- Only checks orders from the last 30 days
- These orders need manual creation in Odoo

### 3. Identify Draft Orders in Odoo
Finds orders that exist in Odoo but are still in "draft" state:
- Checks `odoo_order_map` for orders with `odoo_order_state LIKE '%draft%'`
- Only checks orders modified in the last 7 days
- These orders need to be confirmed in Odoo

### 4. Check CDEK Delivery Status
Finds orders in the CDEK delivery system that are in early stages (CREATED or ACCEPTED):
- **Only checks orders with status: CREATED or ACCEPTED**
- These are orders submitted to CDEK but not yet progressing
- Checks orders with delivery date within 3 days
- Filters to orders updated in last 14 days
- These orders may need follow-up with delivery agent

### 5. Send Email Notification
If any issues are found:
- Sends an HTML formatted email to the admin email address
- Includes detailed tables for each issue category
- Provides direct links to order details in admin panel
- Lists all errors encountered during processing

## Installation & Setup

### 1. Verify Files are in Place

Ensure these files exist:
```
/admin/model/extension/module/odoo_connector.php (modified)
/admin/odoo_order_status_cron.php (new)
```

### 2. Set Up Cron Job

Add to your crontab (adjust paths as needed):

```bash
# Run daily at 9 AM
0 9 * * * cd /path/to/opencart/upload/admin && /usr/bin/php odoo_order_status_cron.php >> /path/to/logs/odoo_cron.log 2>&1

# Or run twice daily at 9 AM and 5 PM
0 9,17 * * * cd /path/to/opencart/upload/admin && /usr/bin/php odoo_order_status_cron.php >> /path/to/logs/odoo_cron.log 2>&1
```

For local development (using the project setup):
```bash
0 9 * * * cd /Users/max/Sites/opencart/upload/admin && /usr/bin/php odoo_order_status_cron.php >> /Users/max/Sites/storage/logs/odoo_cron.log 2>&1
```

### 3. Manual Testing

Test the cron script manually:

```bash
# Basic run
cd /Users/max/Sites/opencart/upload/admin
php odoo_order_status_cron.php

# Debug mode (more verbose output)
php odoo_order_status_cron.php debug
```

## Email Configuration

The cron uses OpenCart's mail settings. Ensure these are configured in:
**System > Settings > Edit Store > Mail**

Required settings:
- Mail Engine (mail, smtp, etc.)
- SMTP Hostname (if using SMTP)
- SMTP Username/Password (if using SMTP)
- Store Email (used as admin notification address)

## Output Format

### Console Output
```
====================================
Odoo Order Status Cron Job
Started: 2025-12-18 09:00:00
Debug mode: OFF
====================================

Running order status check...

====================================
RESULTS:
====================================
Success: YES
Message: Found 5 orders needing attention. Email notification sent.

Orders NOT in Odoo: 2
  - Order #12345 (John Doe)
  - Order #12346 (Jane Smith)

Orders in DRAFT state: 2
  - Order #12347 (Odoo: 5678)
  - Order #12348 (Odoo: 5679)

CDEK orders not received: 1
  - Order #12349 (CDEK: 1234567890, Status: CREATED)

====================================
Completed: 2025-12-18 09:05:23
====================================
```

### Email Notification
The email includes:
- **Subject:** "Odoo Connector: Orders Needing Attention - YYYY-MM-DD HH:MM:SS"
- **Section 1:** Orders NOT Created in Odoo (red theme)
- **Section 2:** Orders in DRAFT State (orange theme)
- **Section 3:** CDEK Orders Not Received (blue theme)
- **Footer:** Errors (if any) and timestamp

Each section contains a table with:
- Clickable order links to admin panel
- Customer information
- Order totals
- Current statuses
- Recommended actions

## Troubleshooting

### No Email Received
1. Check OpenCart mail settings (System > Settings)
2. Check error logs: `/Users/max/Sites/storage/logs/error.log`
3. Verify admin email is set in config
4. Test with debug mode: `php odoo_order_status_cron.php debug`

### Connection Errors
- Ensure Odoo credentials are correct in `ocus_odoo_config` table
- Check network connectivity to Odoo server
- Verify Odoo server is running

### Database Errors
- Check database credentials in `config.php`
- Verify tables exist: `ocus_odoo_order_map`, `ocus_cdek_order`, `ocus_order`
- Check table permissions

## Reporting/Statistics Suggestions

### Do We Need Reporting Views?

**RECOMMENDATION: YES - Create a dashboard view**

The existing `odoo_order_mapping` controller provides a list view, but adding the following would be beneficial:

### Suggested Additions:

#### 1. Dashboard Widget (High Priority)
**Location:** `admin/controller/extension/module/odoo_connector.php`

Add a `getDashboardStats()` method that returns:
```php
return [
    'orders_not_in_odoo_count' => X,
    'orders_draft_count' => X,
    'orders_cdek_pending_count' => X,
    'last_sync_time' => 'YYYY-MM-DD HH:MM:SS',
    'total_synced_today' => X
];
```

Display this on the main dashboard as a widget showing order sync health at a glance.

#### 2. Order Mapping Statistics Page (Medium Priority)
**Location:** Create `admin/controller/extension/module/odoo_order_statistics.php`

Show:
- **Sync Success Rate:** Percentage of orders successfully synced in last 7/30 days
- **Average Sync Time:** Time between order creation and Odoo sync
- **State Distribution:** Pie chart of order states (draft, sale, done, cancel)
- **CDEK Delivery Progress:** Bar chart of CDEK statuses
- **Failed Sync Log:** Last 50 orders that failed to sync with reasons

#### 3. Enhanced Order Mapping View (Low Priority)
**Modify:** `admin/controller/extension/module/odoo_order_mapping.php`

Add:
- Color-coded status indicators (green=synced, yellow=draft, red=not_in_odoo)
- "Needs Attention" filter button
- Quick action buttons: "Sync Now", "Create in Odoo", "View in Odoo"
- Export to CSV functionality

#### 4. Cron Execution History (Low Priority)
**Create table:** `ocus_odoo_cron_log`

```sql
CREATE TABLE `ocus_odoo_cron_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `execution_date` datetime NOT NULL,
  `orders_not_in_odoo` int DEFAULT 0,
  `orders_draft` int DEFAULT 0,
  `orders_cdek_pending` int DEFAULT 0,
  `email_sent` tinyint(1) DEFAULT 0,
  `errors` text,
  `execution_time` int DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_execution_date` (`execution_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Track each cron execution to show trends over time.

### Implementation Priority:
1. **Dashboard Widget** - Quick wins, high visibility
2. **Statistics Page** - Better insights for management
3. **Cron History Table** - Helps track system health over time
4. **Enhanced Mapping View** - Better UX for daily operations

## SQL Queries Used

### Orders Not in Odoo
```sql
SELECT o.order_id, o.order_status_id, o.date_added, o.date_modified,
       CONCAT(o.firstname, ' ', o.lastname) as customer_name, o.email, o.total
FROM ocus_order o
LEFT JOIN ocus_odoo_order_map m ON o.order_id = m.opencart_order_id
WHERE m.opencart_order_id IS NULL
  AND o.order_status_id NOT IN (0, 9, 13)
  AND o.date_added > DATE_SUB(NOW(), INTERVAL 30 DAY)
ORDER BY o.date_added DESC
```

### Orders in Draft State
```sql
SELECT m.opencart_order_id, m.odoo_order_id, m.odoo_order_state,
       m.opencart_order_state, m.modified_on, o.total,
       CONCAT(o.firstname, ' ', o.lastname) as customer_name, o.email
FROM ocus_odoo_order_map m
LEFT JOIN ocus_order o ON m.opencart_order_id = o.order_id
WHERE m.odoo_order_state LIKE '%draft%'
  AND m.modified_on > DATE_SUB(NOW(), INTERVAL 7 DAY)
ORDER BY m.modified_on DESC
```

### CDEK Orders Needing Attention
```sql
SELECT c.order_id, c.dispatch_number, c.cdek_number, c.status_id,
       c.delivery_date, c.last_exchange, c.recipient_name, c.phone,
       o.order_status_id, o.total, o.email
FROM ocus_cdek_order c
LEFT JOIN ocus_order o ON c.order_id = o.order_id
WHERE c.status_id IN ('CREATED', 'ACCEPTED')
  AND c.delivery_date < DATE_ADD(NOW(), INTERVAL 3 DAY)
  AND c.last_exchange > UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 14 DAY))
ORDER BY c.delivery_date ASC
```

## Maintenance

### Regular Checks
- Review cron log files weekly
- Monitor email notifications
- Check error logs if emails stop arriving
- Verify Odoo connection periodically

### Tuning Parameters

You can adjust these values in the model if needed:

```php
// In cronCheckOrderStatuses() method:

// Line 1926: Days to look back for missing orders (default: 30)
AND o.date_added > DATE_SUB(NOW(), INTERVAL 30 DAY)

// Line 1944: Days to check for draft orders (default: 7)
AND m.modified_on > DATE_SUB(NOW(), INTERVAL 7 DAY)

// Line 1964: Days ahead for delivery date check (default: 3)
AND c.delivery_date < DATE_ADD(NOW(), INTERVAL 3 DAY)

// Line 1965: Days to look back for CDEK updates (default: 14)
AND c.last_exchange > UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 14 DAY))
```

## Security Considerations

- The cron script should only be accessible from server (not via web)
- Consider adding IP whitelist if exposing via web interface
- Email contains sensitive order information - ensure admin email is secure
- Log files may contain customer data - secure log directory

## Testing Results

The cron has been tested successfully on the local development environment:

```
====================================
RESULTS:
====================================
Success: YES
Message: Found 5 orders needing attention. Email notification sent.

Orders NOT in Odoo: 2
  - Order #108120 (Ivan Arkhangelskiy)
  - Order #108113 (Иван Виано)

Orders in DRAFT state: 2
  - Order #108098 (Odoo: 4890)
  - Order #108119 (Odoo: 4888)

CDEK orders not received: 1
  - Order #108120 (CDEK: 10199844153, Status: CREATED)
====================================
```

The system successfully:
- Connected to Odoo and synced order statuses
- Identified 2 orders not yet created in Odoo
- Found 2 orders still in draft state
- Detected 1 CDEK order pending warehouse receipt
- Sent HTML email notification to admin

## Version History

- **v1.2** (2025-12-18) - Order status names
  - Replaced numeric order status IDs with Russian status names
  - Updated SQL queries to join order_status table
  - CDEK section now shows both OpenCart status and CDEK status
  - Fallback to "ID: X" if status name not found

- **v1.1** (2025-12-18) - Russian localization and URL fixes
  - Translated email content to Russian
  - Fixed admin URL generation (now uses HTTPS_SERVER constant)
  - Added ruble symbol (₽) to order totals
  - Changed date format to DD.MM.YYYY
  - Updated CDEK query to specifically target CREATED/ACCEPTED statuses

- **v1.0** (2025-12-18) - Initial implementation
  - Basic order status checking
  - Email notifications
  - Three monitoring categories
  - HTML formatted emails with clickable links
  - Database-driven settings loading for cron context
  - Tested and verified on local development environment

## Author

Created by: Claude Code Assistant
For: UniqSport.ru OpenCart 3.0.3.6 Installation
Date: 2025-12-18
