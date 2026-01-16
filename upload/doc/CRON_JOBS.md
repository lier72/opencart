# Adaptive Filter - Cron Jobs

This document describes the available cron jobs for the Adaptive Filter module.

## Available Cron Jobs

### 1. Guest Preference Cleanup (Daily)

**File**: `admin/cli_adaptive_filter_cleanup.php`

**Purpose**: Removes old guest preference records that haven't been accessed in 30 days.

**Cron Schedule**: Daily at 2:00 AM
```bash
0 2 * * * cd /path/to/opencart/admin && php cli_adaptive_filter_cleanup.php
```

**What it does**:
- Checks if the Adaptive Filter module is enabled
- Deletes guest preferences where `last_seen < 30 days ago`
- Reports deleted count and remaining records

**Example Output**:
```
[2025-12-29 02:00:00] Starting guest preference cleanup...
[2025-12-29 02:00:00] Cleanup completed! Deleted 45 old guest records (older than 30 days)
[2025-12-29 02:00:00] Remaining guest records: 123
```

## Setup Instructions

### For Local Development (MAMP/XAMPP)

1. Make the script executable:
```bash
chmod +x /path/to/opencart/admin/cli_adaptive_filter_cleanup.php
```

2. Add to crontab:
```bash
crontab -e
```

3. Add this line:
```bash
0 2 * * * /usr/local/bin/php cli_adaptive_filter_cleanup.php >> /Users/max/Sites/storage/logs/cleanup.log 2>&1
```

### For Production Server

1. SSH into your server

2. Add to crontab:
```bash
crontab -e
```

3. Add this line (adjust paths as needed):
```bash
0 2 * * * cd /var/www/html/admin && php cli_adaptive_filter_cleanup.php >> /var/log/adaptive_filter_cleanup.log 2>&1
```

## Removed Scripts

### cli_adaptive_filter_decay.php (REMOVED)

This script was removed because:
- Referenced `applyDecay()` method that doesn't exist in the model
- No decay configuration exists in module settings
- Decay feature was never implemented
- The script would have failed if executed

## Database Tables

The cleanup script works with the `guest_preferences` table:

```sql
CREATE TABLE `ocus_guest_preferences` (
  `guest_hash` varchar(64) NOT NULL,
  `sizes` json DEFAULT NULL,
  `colors` json DEFAULT NULL,
  `genders` json DEFAULT NULL,
  `sports` json DEFAULT NULL,
  `smart_sorting_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `last_seen` datetime NOT NULL,
  PRIMARY KEY (`guest_hash`),
  KEY `idx_last_seen` (`last_seen`)
);
```

## Monitoring

To check when the cleanup last ran and what it did:

```bash
# If logging to file
tail -f /path/to/cleanup.log

# Check current guest records
mysql -u root -p database_name -e "SELECT COUNT(*) FROM ocus_guest_preferences"

# Check oldest guest record
mysql -u root -p database_name -e "SELECT MIN(last_seen) FROM ocus_guest_preferences"
```

## Troubleshooting

### Script doesn't run
- Check PHP path: `which php`
- Check file permissions: `ls -la cli_adaptive_filter_cleanup.php`
- Run manually to see errors: `php cli_adaptive_filter_cleanup.php`

### Module disabled message
The script checks if `module_adaptive_filter_status` is enabled. If you see "Module disabled - skipping", enable the module in admin panel.

### No records deleted
This is normal if all guest preferences have been active in the last 30 days.
