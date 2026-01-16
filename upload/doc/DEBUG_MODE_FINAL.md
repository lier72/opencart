# Debug Mode - Final Implementation

## Summary

Debug mode has been simplified to use **log files only** - no session storage, no visual widgets.

## How It Works

When `DEBUG_MODE = true` in the model, the following information is logged to `/Users/max/Sites/storage/logs/error.log`:

### 1. User Preferences (when getPersonalizedProducts is called)
```
=== USER PREFERENCES DEBUG ===
User Type: guest | ID: c5230eb06463fd068cc001e5ced0f8d104ed2d8c74713dd88a7f49c13c6919e7
Sizes: {"42 1/3 us(9)":3}
Colors: {"Красный (#FF0000)":1,"Белый (#FFFFFF)":2}
Genders: {"Women":1,"Men":2}
Sports: {"Бадминтон":1}
==============================
```

### 2. Top 5 Scored Products (after sorting)
```
=== TOP 5 SCORED PRODUCTS ===
#1 Product ID: 20343 | Model: AYTP039-3 | Score: 8 | Name: Кроссовки для бадминтона Cloud Ace (красные)
#2 Product ID: 20347 | Model: AYTP041-3 | Score: 8 | Name: Кроссовки для бадминтона Saga N10 (красный)
#3 Product ID: 20351 | Model: AYTL203-2 | Score: 8 | Name: Кроссовки для бадминтона Astra (темно-красные)
#4 Product ID: 20352 | Model: AYTM119-2 | Score: 8 | Name: Кроссовки для бадминтона Jetta (темно-красные)
#5 Product ID: 20373 | Model: AYTL199-2 | Score: 8 | Name: Кроссовки для бадминтона Jazz (красно-салатовые)
==============================
```

## Enable/Disable Debug Mode

**Admin Panel**: Navigate to Extensions → Extensions → Modules → Adaptive Filter

In the module settings, you'll find a "Debug Mode" dropdown at the top of the page:
- **Enabled**: Logs detailed debug information to error.log
- **Disabled**: No debug logging (recommended for production)

The setting is stored as `module_adaptive_filter_debug_mode` in the configuration.

## JavaScript Console Logging

**File**: `catalog/view/theme/journal3/template/extension/module/adaptive_filter_assets.twig` (line 554)

```javascript
// Debug flag - set to true to enable console logging
var ADAPTIVE_FILTER_DEBUG = true;  // Change to false in production
```

## What Was Removed

- ❌ Debug widget template (deleted)
- ❌ renderDebugWidget() controller method (removed)
- ❌ getDebugData() model method (removed)
- ❌ Session storage of debug info (removed - was causing session bloat)
- ❌ Complex score breakdown logging (simplified)
- ❌ Hardcoded DEBUG_MODE constant (replaced with admin setting)

## What Remains

- ✅ Simple user preferences logging
- ✅ Top 5 products with scores logging
- ✅ Performance metrics logging
- ✅ JavaScript console debug flag

## Benefits

1. **No Session Bloat**: Debug data is not stored in session (was storing 300+ products worth of data)
2. **No UI Interference**: No visual widget blocking the screen
3. **Clean Logs**: Easy to read log output in error.log
4. **Fast**: No overhead from session storage operations
5. **Production Safe**: Just set DEBUG_MODE = false

## Viewing Debug Output

Tail the log file in real-time:
```bash
tail -f /Users/max/Sites/storage/logs/error.log
```

Search for debug sections:
```bash
grep -A 10 "USER PREFERENCES DEBUG" /Users/max/Sites/storage/logs/error.log
grep -A 7 "TOP 5 SCORED PRODUCTS" /Users/max/Sites/storage/logs/error.log
```

## Before Production Deployment

**PHP Debug Mode:**
- Navigate to admin panel: Extensions → Extensions → Modules → Adaptive Filter
- Set "Debug Mode" to **Disabled**
- Click Save

**JavaScript Debug Mode (optional):**
```javascript
var ADAPTIVE_FILTER_DEBUG = false;  // adaptive_filter_assets.twig line 554
```

## Files Modified

1. **catalog/model/extension/module/adaptive_filter.php**
   - Removed hardcoded DEBUG_MODE constant
   - Added isDebugMode() method that checks config setting
   - Replaced all `self::DEBUG_MODE` with `$this->isDebugMode()`
   - Removed session storage of debug data (was causing session bloat)
   - Removed getDebugData() method
   - Simplified top 5 logging with concise breakdown
   - User preferences logging

2. **admin/controller/extension/module/adaptive_filter.php**
   - Added debug mode setting handling (lines 83-88)

3. **admin/view/template/extension/module/adaptive_filter.twig**
   - Added Debug Mode dropdown with warning message

4. **catalog/controller/extension/module/adaptive_filter.php**
   - Removed renderDebugWidget() method

5. **catalog/controller/common/footer.php**
   - Removed debug widget rendering call

6. **catalog/view/theme/journal3/template/common/footer.twig**
   - Removed debug widget output variable

7. **catalog/view/theme/journal3/template/extension/module/adaptive_filter_debug.twig**
   - File deleted

## Status

✅ **Complete** - Debug mode is now simple, efficient, and production-ready
