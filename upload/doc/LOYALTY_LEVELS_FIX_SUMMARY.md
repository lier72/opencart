# Loyalty Levels - Fix Summary

## Issue Identified

The loyalty levels admin form was not saving settings correctly due to how OpenCart handles serialized data.

## Root Cause

OpenCart's `editSetting()` method:
- Detects arrays and JSON-encodes them with `serialized=1`
- BUT also calls `$this->db->escape()` which HTML-encodes the JSON
- This breaks `json_decode()` when loading settings

## Solution Implemented

### 1. Form Field Changes
**File**: `admin/view/template/extension/module/bonus_manager.twig`

Changed form field names to use OpenCart's array notation:
- Before: `loyalty_level[0][customer_group_id]`
- After: `module_bonus_manager_loyalty_levels[0][customer_group_id]`

Removed JSON serialization JavaScript - now sends raw array data.

### 2. Controller Updates
**File**: `admin/controller/extension/module/bonus_manager.php`

Updated to handle both array and JSON string formats:
```php
if (is_array($loyalty_levels_data)) {
    $levels = $loyalty_levels_data;  // Already an array
} else {
    $levels = json_decode($loyalty_levels_data, true);  // Decode JSON string
}
```

### 3. Model Updates
**File**: `admin/model/extension/module/bonus_manager.php`

Updated `getLoyaltyLevels()` to handle both formats:
```php
// If already an array (serialized=1), use directly
if (!is_array($levels)) {
    // If JSON string (old format), decode with HTML entity handling
    $levels_json = html_entity_decode($levels, ENT_QUOTES, 'UTF-8');
    $levels = json_decode($levels_json, true);
}
```

Updated `checkAndUpgradeCustomer()` to use `getLoyaltyLevels()` method instead of direct JSON decoding.

## How It Works Now

### Saving Process:
1. User fills out loyalty levels form in admin
2. Form submits with array structure: `module_bonus_manager_loyalty_levels[0][customer_group_id]=1`
3. OpenCart's `editSetting()` receives array
4. Converts to JSON and saves with `serialized=1`
5. ✅ Data stored correctly in database

### Loading Process:
1. OpenCart loads settings and `json_decode()`s serialized data
2. `$config->get('module_bonus_manager_loyalty_levels')` returns array
3. Controller and models handle array format
4. ✅ Data displays correctly in admin form

### Upgrade Process (Automatic on Order Completion):
1. Order completes → `awardBonusesForOrder()` triggered in **catalog model**
2. Calls `checkCustomerLoyaltyUpgrade()` in **catalog model**
3. `getLoyaltyLevels()` retrieves config
4. `getCustomerTotalSpent()` calculates total spent in current period
5. Finds highest qualifying loyalty level
6. Updates customer group if threshold reached
7. ✅ Customer automatically upgraded

**Architecture Note:** All loyalty logic resides in the **catalog model** where orders are processed. The admin model only provides read-only methods for displaying levels in the admin UI.

## Database Schema

Current loyalty levels in database:
```sql
SELECT * FROM ocus_setting WHERE `key` = 'module_bonus_manager_loyalty_levels';
```

Result:
```json
{
  "value": "[{\"customer_group_id\":\"1\",\"min_total_spent\":\"0\"},{\"customer_group_id\":\"8\",\"min_total_spent\":\"100000\"},{\"customer_group_id\":\"6\",\"min_total_spent\":\"300000\"}]",
  "serialized": 1
}
```

## Files Modified

1. **admin/view/template/extension/module/bonus_manager.twig**
   - Changed field names to array notation
   - Removed JSON serialization JavaScript
   - Removed hidden JSON field

2. **admin/controller/extension/module/bonus_manager.php**
   - Updated loyalty levels loading to handle both array and JSON formats

3. **admin/model/extension/module/bonus_manager.php**
   - Updated `getLoyaltyLevels()` to handle both formats
   - Updated `checkAndUpgradeCustomer()` to use `getLoyaltyLevels()`

## Testing

The admin interface now:
- ✅ Displays existing loyalty levels correctly
- ✅ Allows adding/removing levels
- ✅ Saves changes persistently
- ✅ Loads saved settings on page refresh
- ✅ Handles both old (JSON string) and new (array) formats

## Current Configuration

Your loyalty levels:
- **Group 1 (Default)**: 0₽ - Starting level
- **Group 8**: 100,000₽ - Second tier
- **Group 6**: 300,000₽ - Top tier

Customers automatically upgrade when their annual spending reaches these thresholds.

---
**Date**: 2026-01-08
**Status**: ✅ Working
