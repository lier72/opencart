# CDEK Integration Refactoring - API-On-Demand History

## Overview

The CDEK integration has been refactored to fetch order history data directly from the CDEK API on-demand instead of storing it locally in 13 separate database tables. This significantly reduces database bloat, eliminates data synchronization issues, and simplifies the architecture.

## What Changed?

### Removed Local Storage
The following 13 database tables are **no longer used** and have been removed:
- `cdek_order_status_history` - Status change history
- `cdek_order_delay_history` - Delivery delay reasons
- `cdek_order_call_history_good` - Successful delivery call attempts
- `cdek_order_call_history_fail` - Failed delivery call attempts
- `cdek_order_call_history_delay` - Rescheduled delivery calls
- `cdek_order_package` - Package information
- `cdek_order_package_item` - Items within packages
- `cdek_order_add_service` - Additional services
- `cdek_order_courier` - Courier pickup details
- `cdek_order_schedule` - Delivery schedules
- `cdek_order_schedule_delay` - Schedule delays
- `cdek_order_reason` - Status reason descriptions
- `cdek_order_call` - Call request details

### Kept Local Storage
These core tables **remain unchanged**:
- `cdek_order` - Main order tracking (UUID, current status, delivery cost, etc.)
- `cdek_dispatch` - Dispatch batches
- `cdek_city` - CDEK city/location reference data
- `order_to_sdek` - OpenCart order to CDEK mapping

## How It Works Now

### API-On-Demand Pattern
When you view order details in the admin panel, the system:

1. Reads basic order info from local `cdek_order` table
2. Makes a single API call to CDEK: `GET /v2/orders/{uuid}`
3. Parses the API response to extract history data
4. Caches the API response for the current request (to avoid multiple API calls)
5. Returns formatted history data matching the original structure

### Method Changes
All public methods maintain **backward compatibility**. The following methods now fetch from API instead of database:

```php
// In admin/model/extension/module/cdek_integrator.php

getStatusHistory($order_id)      // Parses 'statuses' from API
getDelayHistory($order_id)       // Parses 'delay_reasons' from API
getCallHistoryFail($order_id)    // Parses 'calls.failed_calls' from API
getCallHistoryDelay($order_id)   // Parses 'calls.rescheduled_calls' from API
getPackages($order_id)           // Parses 'packages' from API
getPackageItems($pkg_id, $order_id) // Parses 'packages[].items' from API
getAddService($order_id)         // Parses 'services' from API
```

**Note:** Some methods return empty arrays because CDEK API v2 doesn't provide that level of detail:
- `getCallHistoryGood()` - Not available in API v2
- `getCourierCall()` - Not available in same format

## Benefits

### 1. **Reduced Database Size**
- 13 fewer tables = simpler schema
- No redundant data storage
- Easier database maintenance

### 2. **Always Fresh Data**
- No sync issues between local DB and CDEK
- Always shows latest status from CDEK
- No stale data problems

### 3. **Simpler Code**
- No INSERT/UPDATE/DELETE operations for history
- Fewer database queries to maintain
- Less complex data synchronization logic

### 4. **Better Performance** (for most use cases)
- Single API call replaces multiple table joins
- Reduced database load
- Request-level caching prevents duplicate API calls

## Migration Steps

### For Existing Installations

1. **Backup your database** (always do this first!)
   ```bash
   mysqldump -u root a1627-unqs-oc3 > backup_before_cdek_migration.sql
   ```

2. **Deploy the updated code**
   - Upload all modified files
   - Ensure `admin/model/extension/module/cdek_integrator.php` is updated

3. **Run the migration script** to drop old tables:
   ```bash
   php migrate_cdek_remove_history_tables.php
   ```

   Or visit in browser:
   ```
   http://localhost/~max/oc3.uniqsport.ru/migrate_cdek_remove_history_tables.php
   ```

4. **Test the integration**
   - View CDEK orders in admin panel
   - Check that history displays correctly
   - Verify status updates work

5. **Delete the migration script**
   ```bash
   rm migrate_cdek_remove_history_tables.php
   ```

### For New Installations

No migration needed! The history tables are no longer created during installation.

## Performance Considerations

### API Rate Limits
- CDEK API has rate limits (exact limits depend on your account)
- The system caches API responses per request to minimize API calls
- Only fetches history when viewing specific order details (not in list views)

### When API is Down
- If CDEK API is unavailable, history data won't be displayed
- Core order info (from `cdek_order` table) is still available
- Consider implementing a retry mechanism if needed

### Optimization Tips
1. **Avoid** calling history methods in loops (e.g., when listing 100+ orders)
2. **Use** the cron job to keep `cdek_order` table current status up-to-date
3. **Monitor** API usage to stay within rate limits

## Backward Compatibility

### Method Signatures
All public method signatures remain **unchanged**. Code using these methods will continue to work:

```php
// These still work exactly as before
$history = $model->getStatusHistory($order_id);
$delays = $model->getDelayHistory($order_id);
$packages = $model->getPackages($order_id);
```

### Return Data Structure
Return data structures match the original format to maintain compatibility with the admin panel views.

## Troubleshooting

### Problem: No history data showing
**Solution:** Check that:
1. `dispatch_number` (UUID) exists in `cdek_order` table
2. CDEK API is accessible (check firewall, credentials)
3. Order exists in CDEK system (API v2 only has recent orders)

### Problem: API errors
**Solution:**
1. Check CDEK API credentials in settings
2. Verify the order UUID is valid
3. Check logs in `/Users/max/Sites/storage/logs/`

### Problem: Slow admin panel
**Solution:**
1. Ensure you're not calling history methods in order list views
2. Check API response times
3. Consider adding Redis caching for API responses

## Code Example

### Before (Old Approach - Database)
```php
// Data was stored and retrieved from local tables
$sql = "INSERT INTO cdek_order_status_history SET ...";
$this->db->query($sql);

$status_history = $this->db->query(
    "SELECT * FROM cdek_order_status_history WHERE order_id = " . $order_id
)->rows;
```

### After (New Approach - API)
```php
// Data is fetched from CDEK API on-demand
private function fetchCdekOrderData($order_id) {
    // Get UUID from local cdek_order table
    // Call CDEK API: GET /v2/orders/{uuid}
    // Return parsed entity data
}

public function getStatusHistory($order_id) {
    $cdek_data = $this->fetchCdekOrderData($order_id);
    // Parse and return statuses array
}
```

## Files Modified

### Core Files Changed
1. `admin/model/extension/module/cdek_integrator.php`
   - Added `fetchCdekOrderData()` private method
   - Refactored history getter methods to use API
   - Removed history table INSERT/DELETE operations
   - Removed history table creation in install()
   - Updated `getDispatchInfo()` to fetch from API

### New Files Added
1. `migrate_cdek_remove_history_tables.php` - Migration script
2. `CDEK_HISTORY_REFACTORING_README.md` - This documentation

## Support & Questions

If you encounter issues:
1. Check logs in `/Users/max/Sites/storage/logs/`
2. Verify CDEK API credentials
3. Test API connectivity: `curl https://api.cdek.ru/v2/orders/{uuid}`
4. Review the `fetchCdekOrderData()` method for debugging

## Rollback Procedure

If you need to rollback:

1. Restore database from backup:
   ```bash
   mysql -u root a1627-unqs-oc3 < backup_before_cdek_migration.sql
   ```

2. Restore previous code version from git:
   ```bash
   git checkout HEAD~1 admin/model/extension/module/cdek_integrator.php
   ```

3. Verify everything works

## Future Enhancements

Possible improvements:
1. **Redis caching** - Cache API responses for 5-10 minutes
2. **Webhook integration** - Push updates from CDEK instead of polling
3. **Async API calls** - Use background jobs for history fetching
4. **Fallback mechanism** - Store last-known-good state for offline access

---

**Last Updated:** 2025-12-14
**Version:** 1.0
**Author:** Claude Code Migration Assistant
