# Debug Logging Changes

## Summary
Added conditional logging to the Adaptive Filter module to reduce console noise in production while maintaining the ability to enable detailed logging for debugging.

## Changes Made

### 1. Added Debug Flag

**File**: [catalog/view/theme/journal3/template/extension/module/adaptive_filter_assets.twig](catalog/view/theme/journal3/template/extension/module/adaptive_filter_assets.twig#L553-L554)

Added global debug flag at the top of the JavaScript section:

```javascript
// Debug flag - set to true to enable console logging
var ADAPTIVE_FILTER_DEBUG = false;
```

### 2. Conditional Console.log Statements

All `console.log()` statements are now wrapped with the debug flag check. When `ADAPTIVE_FILTER_DEBUG = false`, no debug logging occurs.

**Logging Categories Now Conditional:**

1. **Mobile button visibility checks** (Lines 609-616)
   - Logs mobile viewport detection
   - Logs sort parameter detection
   - Logs personalized sorting state

2. **URL parameter detection** (Line 621)
   - Logs when sort is detected from URL

3. **Dropdown value detection** (Lines 628, 636, 640, 645, 650)
   - Logs dropdown value checks
   - Logs dropdown sort parameter extraction
   - Logs fallback to server defaults

4. **Button visibility actions** (Lines 656, 659, 663)
   - Logs when mobile button is shown
   - Logs when mobile button is hidden
   - Logs when mobile container is not found

5. **AJAX completion** (Line 683)
   - Logs when Journal3 AJAX updates complete

### 3. Error Logging Preserved

**Important**: All `console.error()` statements remain **always active** to ensure critical errors are always logged:

- Smart Sorting enable/disable errors
- Preference removal errors
- JSON parse errors
- AJAX fetch errors
- Autocomplete data fetch errors

## How to Use

### Production Mode (Default)
```javascript
var ADAPTIVE_FILTER_DEBUG = false; // No debug logging
```

### Development/Debug Mode
```javascript
var ADAPTIVE_FILTER_DEBUG = true; // Enable detailed logging
```

## Benefits

✅ **Cleaner Console**: No debug noise in production
✅ **Easy Debugging**: Simply set flag to `true` to enable detailed logging
✅ **Error Visibility**: Critical errors always logged regardless of debug flag
✅ **Performance**: Eliminates unnecessary string concatenation and logging calls
✅ **Maintainable**: Single flag controls all debug output

## Files Modified

1. `catalog/view/theme/journal3/template/extension/module/adaptive_filter_assets.twig`
   - Added `ADAPTIVE_FILTER_DEBUG` flag (line 554)
   - Wrapped 11 `console.log()` statements with conditional checks
   - Preserved 8 `console.error()` statements as always-active

## Testing

To verify the changes work correctly:

1. **Test with DEBUG = false** (default)
   - Open browser console
   - Navigate to product listing page
   - Switch to mobile view
   - Change sorting options
   - Console should be clean (no debug logs)

2. **Test with DEBUG = true**
   - Change `ADAPTIVE_FILTER_DEBUG` to `true` in the template
   - Reload page
   - Perform same actions
   - Console should show detailed logging

3. **Test error logging** (always active)
   - Trigger an error condition (e.g., network issue)
   - Verify error messages appear in console regardless of debug flag

## Implementation Details

**Before:**
```javascript
console.log('SHOWING mobile button');
```

**After:**
```javascript
if (ADAPTIVE_FILTER_DEBUG) console.log('SHOWING mobile button');
```

For multi-line logs:
```javascript
if (ADAPTIVE_FILTER_DEBUG) {
  console.log('Mobile button check:', {
    isMobile: isMobile,
    sortParam: sortParam,
    isPersonalizedDefault: isPersonalizedDefault,
    currentURL: window.location.search
  });
}
```

## Completion Status

✅ **COMPLETE** - All debug logging is now conditional and controlled by a single flag
